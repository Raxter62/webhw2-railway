<?php
// linebot_webhook.php - Line Bot 報名查詢系統
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Taipei');

// ===== Line Bot 設定 =====
require __DIR__ . '/config.php';

// Debug：看 secret 有沒有讀到
error_log('CHANNEL_SECRET length = ' . strlen((string)$CHANNEL_SECRET));


// ===== 驗證 Line Webhook Signature =====
function verifySignature($channelSecret, $httpRequestBody, $signature) {
    $hash = hash_hmac('sha256', $httpRequestBody, $channelSecret, true);
    $expectedSignature = base64_encode($hash);
    return hash_equals($expectedSignature, $signature);
}

// ===== 發送 Line 訊息 =====
function sendLineMessage($accessToken, $replyToken, $messages) {
    $url = 'https://api.line.me/v2/bot/message/reply';
    
    $data = [
        'replyToken' => $replyToken,
        'messages' => $messages
    ];
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

// ===== 查詢報名資料 =====
function queryRegistration($conn, $table, $keyword) {
    $keyword = trim($keyword);
    
    $sql = "SELECT name, sid, dept, email, phone, register_time 
            FROM `{$table}` 
            WHERE name = ? OR sid = ? 
            ORDER BY register_time DESC 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param("ss", $keyword, $keyword);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// ===== 生成回應訊息 =====
function generateResponseMessage($data, $keyword, $baseUrl) {
    if (!$data) {
        return [[
            'type' => 'text',
            'text' => "😢 查無報名資料\n\n查詢關鍵字：{$keyword}\n\n請確認：\n1. 姓名 或 學號(s1234567) 是否正確\n2. 是否已完成報名\n\n如需報名，請前往：\n{$baseUrl}"
        ]];
    }
    
    $time = date('Y/m/d H:i', strtotime($data['register_time']));
    
    $textMessage = "✅ 報名成功！\n\n" .
                   "📋 報名資訊\n" .
                   "━━━━━━━━━━━━\n" .
                   "姓名：{$data['name']}\n" .
                   "學號：{$data['sid']}\n" .
                   "系別：{$data['dept']}\n" .
                   "Email：{$data['email']}\n" .
                   "手機：{$data['phone']}\n" .
                   "報名時間：{$time}\n\n" .
                   "🎉 期待在光年資外見到你！\n" .
                   "活動網頁：{$baseUrl}";
    
    return [[
        'type' => 'text',
        'text' => $textMessage
    ]];
}

// ===== 主程式 =====
try {
    // 取得 POST 資料
    $httpRequestBody = file_get_contents('php://input');
    
    // 檢查是否為 POST 請求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }
    
    // 取得並驗證 Signature
    $headers = getallheaders();
    $signature = $headers['X-Line-Signature'] ?? $headers['x-line-signature'] ?? '';
    
    if (empty($signature)) {
        http_response_code(400);
        exit('Bad Request');
    }
    
    // 驗證簽章
    if (!verifySignature($CHANNEL_SECRET, $httpRequestBody, $signature)) {
        error_log('Signature check failed');
        http_response_code(403);
        exit('Invalid signature');
    }
    
    // 解析 JSON
    $jsonData = json_decode($httpRequestBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        exit('Invalid JSON');
    }
    
    // 檢查事件
    if (!isset($jsonData['events']) || empty($jsonData['events'])) {
        http_response_code(200);
        exit('OK');
    }
    
    // 處理每個事件
    foreach ($jsonData['events'] as $event) {
        // 只處理文字訊息
        if ($event['type'] !== 'message' || $event['message']['type'] !== 'text') {
            continue;
        }
        
        $replyToken = $event['replyToken'];
        $userMessage = trim($event['message']['text']);
        
        // 指令處理：說明
        if ($userMessage === '說明' || $userMessage === 'help' || $userMessage === '?') {
            $helpMessage = [[
                'type' => 'text',
                'text' => "🤖 四系迎新報名查詢機器人\n\n" .
                         "📝 使用方式：\n" .
                         "直接傳送「姓名」或「學號」即可查詢\n\n" .
                         "範例：\n" .
                         "• 陳同學\n" .
                         "• s1234567\n\n" .
                         "💡 提示：\n" .
                         "• 學號格式：s + 7位數字\n" .
                         "• 姓名需完整輸入\n" .
                         "• 大小寫不拘\n\n" .
                         "🔗 立即報名：\n{$BASE_URL}"
            ]];
            sendLineMessage($CHANNEL_ACCESS_TOKEN, $replyToken, $helpMessage);
            continue;
        }
        
        // 空白訊息處理
        if (empty($userMessage)) {
            $emptyMessage = [[
                'type' => 'text',
                'text' => "請輸入姓名或學號查詢報名狀態\n\n輸入「說明」查看使用方式"
            ]];
            sendLineMessage($CHANNEL_ACCESS_TOKEN, $replyToken, $emptyMessage);
            continue;
        }
        
        // 連接資料庫
        $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
        
        if ($conn->connect_error) {
            $errorMessage = [[
                'type' => 'text',
                'text' => "❌ 系統暫時無法使用，請稍後再試"
            ]];
            sendLineMessage($CHANNEL_ACCESS_TOKEN, $replyToken, $errorMessage);
            continue;
        }
        
        $conn->set_charset('utf8mb4');
        
        // 查詢報名資料
        $registrationData = queryRegistration($conn, $TABLE_NAME, $userMessage);
        $conn->close();
        
        // 生成並發送回應
        $responseMessages = generateResponseMessage($registrationData, $userMessage, $BASE_URL);
        sendLineMessage($CHANNEL_ACCESS_TOKEN, $replyToken, $responseMessages);
    }
    
    http_response_code(200);
    echo 'OK';
    
} catch (Exception $e) {
    http_response_code(500);
    echo 'Internal Server Error';
}
?>