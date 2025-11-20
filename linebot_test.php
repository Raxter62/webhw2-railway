<?php
// linebot_test.php - Line Bot 設定測試工具
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Taipei');

// 從 linebot_webhook.php 取得設定
require __DIR__ . '/config.php';

$testKeyword = $_GET['keyword'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Line Bot 測試工具</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Microsoft JhengHei', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 28px;
        }
        h2 {
            color: #333;
            margin: 25px 0 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #667eea;
            font-size: 20px;
        }
        .status {
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
        .info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }
        
        .test-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        button:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        .code-block {
            background: #2d3748;
            color: #68d391;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            margin: 10px 0;
        }
        .step {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #667eea;
        }
        .step-number {
            display: inline-block;
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            margin-right: 10px;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
        }
        tr:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 Line Bot 設定測試工具</h1>
        <p style="color: #666; margin-bottom: 20px;">檢查 Line Bot 與資料庫連線狀態</p>

        <h2>1️⃣ 資料庫連線測試</h2>
        <?php
        try {
            $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
            $conn->set_charset('utf8mb4');
            
            if ($conn->connect_error) {
                throw new Exception($conn->connect_error);
            }
            
            echo '<div class="status success">✅ 資料庫連線成功</div>';
            
            // 檢查資料表
            $tableCheck = $conn->query("SHOW TABLES LIKE '{$TABLE_NAME}'");
            if ($tableCheck->num_rows > 0) {
                echo '<div class="status success">✅ 資料表 ' . htmlspecialchars($TABLE_NAME) . ' 存在</div>';
                
                // 統計報名人數
                $countResult = $conn->query("SELECT COUNT(*) as total FROM `{$TABLE_NAME}`");
                $count = $countResult->fetch_assoc()['total'];
                echo '<div class="status info">📊 目前報名人數：' . $count . ' 人</div>';
                
            } else {
                echo '<div class="status error">❌ 資料表 ' . htmlspecialchars($TABLE_NAME) . ' 不存在</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="status error">❌ 資料庫連線失敗：' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        ?>

        <h2>2️⃣ 測試查詢功能</h2>
        <div class="test-form">
            <form method="GET">
                <div class="form-group">
                    <label>輸入姓名或學號測試查詢：</label>
                    <input type="text" name="keyword" placeholder="例如：陳同學 或 s1234567" value="<?= htmlspecialchars($testKeyword) ?>">
                </div>
                <button type="submit">🔍 查詢測試</button>
            </form>
        </div>

        <?php
        if (!empty($testKeyword) && isset($conn)) {
            $sql = "SELECT * FROM `{$TABLE_NAME}` WHERE name = ? OR sid = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $testKeyword, $testKeyword);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $data = $result->fetch_assoc();
                echo '<div class="status success">✅ 找到報名資料！</div>';
                echo '<table>';
                echo '<tr><th>欄位</th><th>值</th></tr>';
                foreach ($data as $key => $value) {
                    echo '<tr><td><strong>' . htmlspecialchars($key) . '</strong></td><td>' . htmlspecialchars($value) . '</td></tr>';
                }
                echo '</table>';
            } else {
                echo '<div class="status warning">⚠️ 查無報名資料：' . htmlspecialchars($testKeyword) . '</div>';
            }
            $stmt->close();
        }
        
        if (isset($conn)) $conn->close();
        ?>

        <h2>3️⃣ Line Developers 設定步驟</h2>
        
        <div class="step">
            <span class="step-number">1</span>
            <strong>建立 Line Bot</strong>
            <p>前往 <a href="https://developers.line.biz/console/" target="_blank">Line Developers Console</a></p>
            <p>建立 Provider → 建立 Messaging API Channel</p>
        </div>

        <div class="step">
            <span class="step-number">2</span>
            <strong>設定 Webhook URL</strong>
            <p>在 Messaging API 設定頁面填入：</p>
            <div class="code-block">
                 https://yzulightyear2026.infinityfree.me/linebot_webhook.php // 替換為公開 URL
            </div>
            <p style="margin-top: 10px;">⚠️ 記得開啟「Use webhook」選項</p>
        </div>

        <div class="step">
            <span class="step-number">3</span>
            <strong>取得憑證資訊</strong>
            <p>從 Line Developers Console 複製：</p>
            <ul style="margin: 10px 0 0 40px;">
                <li><strong>Channel Secret</strong> (Basic settings 頁面)</li>
                <li><strong>Channel Access Token</strong> (Messaging API 頁面，需先 Issue)</li>
            </ul>
        </div>

        <div class="step">
            <span class="step-number">4</span>
            <strong>更新 linebot_webhook.php</strong>
            <p>修改以下兩行：</p>
            <div class="code-block">
$CHANNEL_SECRET = 'YOUR_CHANNEL_SECRET';<br>
$CHANNEL_ACCESS_TOKEN = 'YOUR_CHANNEL_ACCESS_TOKEN';
            </div>
        </div>

        <div class="step">
            <span class="step-number">5</span>
            <strong>測試機器人</strong>
            <p>使用 Line 掃描 Bot 的 QR Code 加好友</p>
            <p>傳送「說明」查看使用方式</p>
            <p>傳送學號或姓名測試查詢功能</p>
        </div>

        <h2>4️⃣ 快速檢查清單</h2>
        <div class="status info">
            <strong>✓ 確認事項：</strong>
            <ul style="margin: 10px 0 0 20px;">
                <li>✅ 資料庫可連線</li>
                <li>✅ 資料表存在且有資料</li>
                <li>⬜ linebot_webhook.php 已上傳</li>
                <li>⬜ Line Bot Channel 已建立</li>
                <li>⬜ Webhook URL 已設定並驗證成功</li>
                <li>⬜ Channel Secret 和 Access Token 已填入</li>
                <li>⬜ 加入 Bot 好友並測試</li>
            </ul>
        </div>

        <h2>5️⃣ 常見問題</h2>
        <div class="status warning">
            <strong>Q1: Webhook 驗證失敗？</strong>
            <p>確認 URL 正確且可從外部訪問，檢查 PHP 錯誤日誌</p>
        </div>
        <div class="status warning">
            <strong>Q2: 機器人沒反應？</strong>
            <p>檢查 Channel Secret 和 Access Token 是否正確</p>
        </div>
        <div class="status warning">
            <strong>Q3: 查詢不到資料？</strong>
            <p>確認輸入的姓名/學號與資料庫完全一致（包含大小寫）</p>
        </div>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #eee; text-align: center; color: #666;">
            <p>四系迎新 Line Bot © 2026</p>
        </div>
    </div>
</body>
</html>