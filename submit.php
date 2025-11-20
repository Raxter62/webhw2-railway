    <?php
// hw2_submit.php - 修正版：使用動態 URL 和資料庫儲存
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Taipei');
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* === 1) 站點設定 === */
require __DIR__ . '/config.php';

/* 寄信設定 */
// Mail settings - host/port kept here; credentials moved to .env
$MAIL_HOST = 'smtp.gmail.com';
$MAIL_PORT = 587;

// Simple .env loader (if you use vlucas/phpdotenv you can replace this)
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($key, $val) = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        $val = trim($val, "\"'"); // remove surrounding quotes
        putenv("$key=$val");
        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
    }
}

// Read mail credentials from environment
$MAIL_USER = getenv('MAIL_USER') ?: '';
$MAIL_PASS = getenv('MAIL_PASS') ?: '';
$MAIL_FROM_NAME = '四系迎新報名';

/* === 2) 載入外部套件 === */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    require __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require __DIR__ . '/PHPMailer/src/SMTP.php';
    require __DIR__ . '/PHPMailer/src/Exception.php';
    require __DIR__ . '/TCPDF/tcpdf.php';
    
    $qrLibPath = __DIR__ . '/phpqrcode/phpqrcode.php';
    if (!file_exists($qrLibPath)) $qrLibPath = __DIR__ . '/phpqrcode/qrlib.php';
    require $qrLibPath;
} catch (Exception $e) {
    die("載入套件失敗: " . $e->getMessage());
}

/* === 3) 收表單 + 驗證 === */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("請使用表單送出");
}

$g = fn($k) => trim($_POST[$k] ?? '');
$name  = $g('name');
$email = $g('email');
$phone = $g('phone');
$home  = $g('home');
$sid   = $g('sid');
$dept  = $g('dept');
$needs = $_POST['needs'] ?? [];
$note  = $g('note');

$required = ['name'=>$name, 'email'=>$email, 'phone'=>$phone, 'home'=>$home, 'sid'=>$sid, 'dept'=>$dept];
foreach ($required as $field=>$val) {
    if ($val === '') {
        die("資料不完整，缺少欄位: {$field}，請回上一頁重新輸入。");
    }
}

/* === 4) 連接資料庫 === */
try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
    
    if ($conn->connect_error) {
        throw new Exception("資料庫連接失敗: " . $conn->connect_error);
    }
    
    // 檢查資料表是否存在
    $checkTable = $conn->query("SHOW TABLES LIKE '{$TABLE_NAME}'");
    if ($checkTable->num_rows == 0) {
        throw new Exception("資料表 {$TABLE_NAME} 不存在，請先建立資料表");
    }
    
    $needsStr = implode(',', $needs);
    $registerTime = date('Y-m-d H:i:s');
    
    // 先插入基本資料，取得 ID
    $sql = "INSERT INTO `{$TABLE_NAME}` (name, email, phone, home, sid, dept, needs, note, register_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("SQL 準備失敗: " . $conn->error);
    }
    
    $stmt->bind_param("sssssssss", $name, $email, $phone, $home, $sid, $dept, $needsStr, $note, $registerTime);
    
    if (!$stmt->execute()) {
        throw new Exception("資料庫寫入失敗: " . $stmt->error);
    }
    
    $registrationId = $conn->insert_id;
    $stmt->close();
    
} catch (Exception $e) {
    die("<h3>資料庫錯誤</h3><p>" . htmlspecialchars($e->getMessage()) . "</p>
         <p>請確認：</p>
         <ul>
         <li>資料庫名稱：{$DB_NAME}</li>
         <li>資料表名稱：{$TABLE_NAME}</li>
         <li>是否已正確建立資料表及欄位</li>
         </ul>");
}

/* === 5) 產生 PDF === */
try {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
    
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    $pdf->SetFont('msungstdlight','', 11);

    $needsTxt = $needs ? implode('、', array_map('htmlspecialchars', $needs)) : '無';
    $noteHtml = nl2br(htmlspecialchars($note));

     $html = <<<HTML
<style>

    .header {
        background-color: #4a2c6b;
        color: #ffffff;
        padding: 20px;
        text-align: center;
        border: 3px solid #7cb342;
        margin-bottom: 20px;
    }
    .header-title {
        font-size: 18px;
        font-weight: bold;
        color: #ffffff;
        margin: 0 0 8px 0;
    }
    .header-subtitle {
        font-size: 11px;
        color: #ffffff;
        margin: 0;
    }
    .info-section {
        background-color: #f8f9fa;
        border-left: 5px solid #7cb342;
        padding: 15px;
        margin: 15px 0;
    }
    .info-row {
        padding: 8px 0;
        border-bottom: 1px dashed #e0e0e0;
        font-size: 11px;
    }
    .label {
        color: #4a2c6b;
        font-weight: bold;
    }
    .value {
        color: #333333;
    }
    .needs-section {
        background-color: #e8f5e9;
        border: 2px solid #7cb342;
        padding: 12px;
        margin: 15px 0;
        font-size: 11px;
    }
    .note-section {
        background-color: #fff3e0;
        border: 2px solid #ff9800;
        padding: 12px;
        margin: 15px 0;
        font-size: 11px;
    }
    .footer {
        text-align: center;
        font-size: 9px;
        color: #666666;
        margin-top: 30px;
        padding-top: 15px;
        border-top: 2px solid #e0e0e0;
    }
    .decorative-line {
        height: 3px;
        background-color: #7cb342;
        margin: 10px 0;
    }
</style>

<table cellpadding="0" cellspacing="0" style="width: 100%; background-color: #4a2c6b; border: 3px solid #7cb342; margin-bottom: 20px;">
    <tr>
        <td style="padding: 28px; text-align: center;">
            <div style="font-size: 16px; font-weight: bold; color: #ffffff; margin-bottom: 8px;">
                四系迎新報名表單
            </div>
            <div style="font-size: 20px; color: #ffffff;">
                緣分讓我們相遇在光年資外
            </div>
        </td>
    </tr>
</table>
<div style="height: 3px; background-color: #7cb342; margin: 10px 0;"></div>
<table cellpadding="8" cellspacing="0" style="width: 100%; background-color: #f8f9fa; border-left: 5px solid #7cb342; margin: 15px 0;">
    <tr>
        <td style="border-bottom: 1px dashed #e0e0e0; border-right: 1px dashed #e0e0e0;">
            <span style="color: #4a2c6b; font-weight: bold;">姓名</span>
        </td>
        <td style="border-bottom: 1px dashed #e0e0e0;">
            <span style="color: #333333;">{$name}</span>
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px dashed #e0e0e0; border-right: 1px dashed #e0e0e0;">
            <span style="color: #4a2c6b; font-weight: bold;">學號</span>
        </td>
        <td style="border-bottom: 1px dashed #e0e0e0;">
            <span style="color: #333333;">{$sid}</span>
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px dashed #e0e0e0; border-right: 1px dashed #e0e0e0;">
            <span style="color: #4a2c6b; font-weight: bold;">系別</span>
        </td>
        <td style="border-bottom: 1px dashed #e0e0e0;">
            <span style="color: #333333;">{$dept}</span>
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px dashed #e0e0e0; border-right: 1px dashed #e0e0e0;">
            <span style="color: #4a2c6b; font-weight: bold;">Email</span>
        </td>
        <td style="border-bottom: 1px dashed #e0e0e0;">
            <span style="color: #333333;">{$email}</span>
        </td>
    </tr>
    <tr>
        <td style="border-bottom: 1px dashed #e0e0e0; border-right: 1px dashed #e0e0e0;">
            <span style="color: #4a2c6b; font-weight: bold;">手機</span>
        </td>
        <td style="border-bottom: 1px dashed #e0e0e0;">
            <span style="color: #333333;">{$phone}</span>
        </td>
    </tr>
    <tr>
        <td style="border-right: 1px dashed #e0e0e0;">
            <span style="color: #4a2c6b; font-weight: bold;">家用電話</span>
        </td>
        <td>
            <span style="color: #333333;">{$home}</span>
        </td>
    </tr>
</table>
<table cellpadding="12" cellspacing="0" style="width: 100%; background-color: #e8f5e9; border: 2px solid #7cb342; margin: 15px 0;">
    <tr>
        <td>
            <strong style="color: #2d5016;">餐食與需求： </strong> {$needsTxt}
        </td>
    </tr>
</table>
<table cellpadding="12" cellspacing="0" style="width: 100%; background-color: #fff3e0; border: 2px solid #ff9800; margin: 15px 0;">
    <tr>
        <td>
            <strong style="color: #e65100;">Note： </strong><br>
            {$noteHtml}
        </td>
    </tr>
</table>
<div class="footer">
    <div class="decorative-line"></div>
    <p>報名時間： {$registerTime}</p>
    <p>恭喜您成功報名"四系迎新"，期待與您在光年資外相見！</p>
</div>
HTML;

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdfContent = $pdf->Output('', 'S');
    
    if (empty($pdfContent)) {
        throw new Exception("PDF 內容產生失敗");
    }
    
    // 將 PDF 內容轉為 Base64 儲存到資料庫
    $pdfBase64 = base64_encode($pdfContent);
    
} catch (Exception $e) {
    die("<h3>PDF 產生失敗</h3><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}

/* === 6) 產生動態 PDF URL === */
// 使用動態腳本 URL，透過 ID 查詢
$pdfURL = rtrim($BASE_URL, '/') . '/view_pdf.php?id=' . $registrationId;
$qrURL = rtrim($BASE_URL, '/') . '/view_qr.php?id=' . $registrationId;

/* === 7) 產生 QRCode (儲存為 Base64) === */
$qrBase64 = '';
try {
    // 使用臨時檔案產生 QR Code
    $tempQR = tempnam(sys_get_temp_dir(), 'qr_');
    QRcode::png($pdfURL, $tempQR, QR_ECLEVEL_M, 8, 2);
    
    if (file_exists($tempQR)) {
        $qrContent = file_get_contents($tempQR);
        $qrBase64 = base64_encode($qrContent);
        unlink($tempQR); // 刪除臨時檔
    }
} catch (Exception $e) {
    error_log("QRCode generation failed: " . $e->getMessage());
}


/* === 7.5) 產生 LINE 加好友 QRCode === */
$lineQrBase64 = '';
$lineAddFriendURL = 'https://lin.ee/e407OkV';  // 您的 LINE 加好友連結

try {
    $tempLineQR = tempnam(sys_get_temp_dir(), 'line_qr_');
    QRcode::png($lineAddFriendURL, $tempLineQR, QR_ECLEVEL_M, 8, 2);
    
    if (file_exists($tempLineQR)) {
        $lineQrContent = file_get_contents($tempLineQR);
        $lineQrBase64 = base64_encode($lineQrContent);
        unlink($tempLineQR);
    }
} catch (Exception $e) {
    error_log("LINE QRCode generation failed: " . $e->getMessage());
}

/* === 8) 更新資料庫:儲存 PDF、QR Code 和 LINE QR Code 的 Base64 === */
try {
    $sql = "UPDATE `{$TABLE_NAME}` SET pdf_content = ?, qr_content = ?, line_qr_content = ?, pdf_path = ?, qr_path = ?, line_add_url = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("SQL 準備失敗: " . $conn->error);
    }
    
    $stmt->bind_param("ssssssi", $pdfBase64, $qrBase64, $lineQrBase64, $pdfURL, $qrURL, $lineAddFriendURL, $registrationId);
    
    if (!$stmt->execute()) {
        throw new Exception("更新資料庫失敗: " . $stmt->error);
    }
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Database update failed: " . $e->getMessage());
}

/* === 9) 寄送確認信 === */
$mailSent = false;
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $MAIL_HOST;
    $mail->Port       = $MAIL_PORT;
    $mail->SMTPAuth   = true;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Username   = $MAIL_USER;
    $mail->Password   = $MAIL_PASS;
    $mail->Timeout    = 30;

    $mail->CharSet = 'UTF-8';
    $mail->setFrom($MAIL_USER, $MAIL_FROM_NAME);
    $mail->addAddress($email, $name);

    $mail->isHTML(true);
    $mail->Subject = '四系迎新報名確認信 - 緣分讓我們相遇在光年資外';
    
    $infoHtml = nl2br(htmlspecialchars(
        "姓名:{$name}\nEmail:{$email}\n手機:{$phone}\n家用電話:{$home}\n學號:{$sid}\n系別:{$dept}\n餐飲與需求:{$needsTxt}\n備註:{$note}"
    ));
    
    // 郵件內容 - 包含 PDF QR Code 和 LINE 加好友 QR Code
    $mail->Body = "
        {$name} 您好,<br><br>
        感謝您報名 <strong>四系迎新「緣分讓我們相遇在光年資外」</strong>!<br><br>
        您的報名資料:<br>
        <div style='font-family:monospace; background:#f6f8fa; padding:15px; border-radius:8px; margin:15px 0; border-left:4px solid #7cb342;'>{$infoHtml}</div>
      
        <div style='margin: 30px 0;'>
            <h3 style='color: #4a2c6b;'>📱 掃描下方 QR Code 查看報名表</h3>
            <div style='text-align:center; margin:20px 0;'>
                <img src='cid:qrcode_image' alt='報名表 QR Code' style='max-width:250px; border:2px solid #4a2c6b; border-radius:8px; padding:10px;' />
            </div>
        </div>
        
        <div style='margin: 30px 0; background: #e8f5e9; padding: 20px; border-radius: 8px; border: 2px solid #7cb342;'>
            <h3 style='color: #2d5016; margin-bottom: 15px;'>💚 加入 LINE 官方帳號接收最新消息</h3>
            <div style='text-align:center; margin:20px 0;'>
                <img src='cid:line_qrcode_image' alt='LINE 加好友 QR Code' style='max-width:250px; border:2px solid #00B900; border-radius:8px; padding:10px; background: white;' />
            </div>
            <p style='text-align:center; color:#2d5016; font-weight:bold;'>掃描 QR Code 或點擊連結加入:</p>
            <p style='text-align:center;'><a href='{$lineAddFriendURL}' style='color:#00B900; font-weight:bold; text-decoration:none;'>{$lineAddFriendURL}</a></p>
        </div>
        
        <p style='font-size:12px; color:#666;'>PDF 報名表和 QR Code 已作為附件一併寄送,請查收。</p>
        <hr style='margin:20px 0; border:none; border-top:1px solid #ddd;'>
        <p style='font-size:11px; color:#999;'>此為系統自動發送的確認信,請勿直接回覆。</p>
    ";
    
    // 附件 1: PDF 報名表
    if (!empty($pdfContent)) {
        $pdfFilename = 'Registration_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $sid) . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $name) . '.pdf';
        $mail->addStringAttachment($pdfContent, $pdfFilename, 'base64', 'application/pdf');
    }
    
    // 附件 2: PDF QR Code 圖片(同時作為內嵌圖片)
    if (!empty($qrBase64)) {
        $qrBinary = base64_decode($qrBase64);
        $qrFilename = 'QRCode_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $sid) . '.png';
        $mail->addStringEmbeddedImage($qrBinary, 'qrcode_image', $qrFilename, 'base64', 'image/png');
        $mail->addStringAttachment($qrBinary, $qrFilename, 'base64', 'image/png');
    }
    
    // 附件 3: LINE 加好友 QR Code 圖片(同時作為內嵌圖片)
    if (!empty($lineQrBase64)) {
        $lineQrBinary = base64_decode($lineQrBase64);
        $lineQrFilename = 'LINE_AddFriend_QRCode.png';
        $mail->addStringEmbeddedImage($lineQrBinary, 'line_qrcode_image', $lineQrFilename, 'base64', 'image/png');
        $mail->addStringAttachment($lineQrBinary, $lineQrFilename, 'base64', 'image/png');
    }
    
    $mail->send();
    $mailSent = true;
} catch (Exception $e) {
    error_log("Mail sending failed: " . $mail->ErrorInfo);
}

$conn->close();

/* === 10) 顯示美化的成功頁面 === */
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>報名成功 - 四系迎新</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Microsoft JhengHei', Arial, sans-serif;
            background: linear-gradient(135deg, #1a0b2e 0%, #2d1b4e 50%, #4a2c6b 100%);
            min-height: 100vh;
            padding: 15px;
            position: relative;
            overflow-y: auto;
        }
        
        body::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(2px 2px at 20% 30%, white, transparent),
                radial-gradient(2px 2px at 60% 70%, white, transparent),
                radial-gradient(1px 1px at 50% 50%, white, transparent),
                radial-gradient(1px 1px at 80% 10%, white, transparent),
                radial-gradient(2px 2px at 90% 60%, white, transparent);
            background-size: 200% 200%;
            animation: twinkle 5s ease-in-out infinite;
            opacity: 0.6;
        }
        
        @keyframes twinkle {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 0.3; }
        }
        
        .line-section {
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            border: 3px solid #00B900;
            border-radius: 10px;
            padding: 25px;
            margin: 20px 0;
            text-align: center;
        }
        
        .line-section h3 {
            color: #2d5016;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .line-qr-image {
            max-width: 280px;
            width: 100%;
            border: 3px solid #00B900;
            border-radius: 10px;
            padding: 15px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin: 15px auto;
        }
        
        .line-link {
            display: inline-block;
            background: #00B900;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 15px;
            transition: all 0.3s ease;
        }
        
        .line-link:hover {
            background: #009900;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 185, 0, 0.3);
        }
        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.5);
            max-width: 900px;
            width: 100%;
            padding: 25px;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
            margin: 20px auto;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #7cb342;
            padding-bottom: 15px;
        }
        
        .header h1 {
            color: #2d1b4e;
            font-size: 22px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .header h1::before {
            content: '✓';
            display: inline-block;
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #7cb342 0%, #a4d65e 100%);
            color: white;
            border-radius: 50%;
            line-height: 32px;
            font-size: 20px;
        }
        
        .subtitle {
            color: #7cb342;
            font-size: 15px;
            font-weight: bold;
        }
        
        .info-box {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8eef5 100%);
            border-left: 4px solid #7cb342;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .info-box h3 {
            color: #2d1b4e;
            margin-bottom: 12px;
            font-size: 16px;
        }
        
        .info-item {
            padding: 6px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            font-size: 14px;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-item strong {
            color: #4a2c6b;
            display: inline-block;
            width: 100px;
        }
        
        .pdf-container {
            margin: 20px 0;
            border: 3px solid #7cb342;
            border-radius: 10px;
            overflow: hidden;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .pdf-container iframe {
            width: 100%;
            height: 600px;
            border: none;
        }
        
        .pdf-header {
            background: linear-gradient(135deg, #7cb342 0%, #a4d65e 100%);
            color: white;
            padding: 12px 15px;
            font-weight: bold;
            font-size: 15px;
        }
        
        .qr-container {
            margin: 20px 0;
            border: 3px solid #4a2c6b;
            border-radius: 10px;
            overflow: hidden;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .qr-header {
            background: linear-gradient(135deg, #4a2c6b 0%, #2d1b4e 100%);
            color: white;
            padding: 12px 15px;
            font-weight: bold;
            font-size: 15px;
        }
        
        .qr-content {
            padding: 30px;
            text-align: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8eef5 100%);
        }
        
        .qr-content img {
            max-width: 300px;
            width: 100%;
            border: 2px solid #4a2c6b;
            border-radius: 10px;
            padding: 15px;
            background: white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .qr-hint {
            color: #666;
            font-size: 14px;
            margin-top: 15px;
            font-style: italic;
        }
        
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .link-box {
            background: #e8f5e9;
            border: 2px solid #7cb342;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .link-box h3 {
            color: #2d1b4e;
            margin-bottom: 10px;
            font-size: 15px;
        }
        
        .link-box a {
            color: #7cb342;
            word-break: break-all;
            text-decoration: none;
            font-size: 13px;
        }
        
        .link-box a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .two-column {
                grid-template-columns: 1fr;
            }
        }
        
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn {
            flex: 1;
            min-width: 150px;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #7cb342 0%, #a4d65e 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(124, 179, 66, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #4a2c6b 0%, #2d1b4e 100%);
            color: white;
        }
        
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 44, 107, 0.4);
        }
        
        .alert {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            color: #856404;
            font-size: 14px;
        }
        
        .success-alert {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 20px;
            }
            
            .pdf-container iframe {
                height: 500px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>報名成功!</h1>
            <div class="subtitle">緣分讓我們相遇在光年資外 ✨</div>
        </div>
        
        <div class="info-box">
            <h3>📋 您的報名資訊</h3>
            <div class="info-item"><strong>姓名：</strong><?= htmlspecialchars($name) ?></div>
            <div class="info-item"><strong>學號：</strong><?= htmlspecialchars($sid) ?></div>
            <div class="info-item"><strong>系別：</strong><?= htmlspecialchars($dept) ?></div>
            <div class="info-item"><strong>Email：</strong><?= htmlspecialchars($email) ?></div>
            <div class="info-item"><strong>手機：</strong><?= htmlspecialchars($phone) ?></div>
            <div class="info-item"><strong>餐飲需求：</strong><?= htmlspecialchars($needsTxt) ?></div>
        </div>
        
        <?php if ($mailSent): ?>
        <div class="alert success-alert">
            ✓ 確認信已成功寄送至您的信箱！請查收。
        </div>
        <?php else: ?>
        <div class="alert">
            ⚠️ 確認信寄送失敗，但您的報名資料已成功儲存！請保存下方連結以便日後查看。
        </div>
        <?php endif; ?>
        
        
        
        <div class="two-column">
            <div class="pdf-container">
                <div class="pdf-header">📄 您的報名表 PDF</div>
                <iframe src="data:application/pdf;base64,<?= $pdfBase64 ?>" type="application/pdf"></iframe>
            </div>
            
            <?php if ($qrBase64): ?>
            <div class="qr-container">
                <div class="qr-header">📱 報名表 QR Code</div>
                <div class="qr-content">
                    <img src="data:image/png;base64,<?= $qrBase64 ?>" alt="QR Code">
                    <div class="qr-hint">掃描此 QR Code 可直接查看報名表 PDF</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- LINE 加好友區塊 -->
        <?php if ($lineQrBase64): ?>
        <div class="line-section">
            <h3>💚 加入 LINE 官方帳號</h3>
            <p style="color: #2d5016; margin-bottom: 15px;">掃描 QR Code 或點擊按鈕加入,接收活動最新消息!</p>
            <img src="data:image/png;base64,<?= $lineQrBase64 ?>" alt="LINE 加好友 QR Code" class="line-qr-image">
            <br>
            <a href="<?= htmlspecialchars($lineAddFriendURL) ?>" target="_blank" class="line-link">
                點我加入 LINE 官方帳號
            </a>
        </div>
        <?php endif; ?>
        
        <div class="btn-group">
            <a href="<?= htmlspecialchars($pdfURL) ?>" class="btn btn-primary" target="_blank">
                📄 開啟報名表
            </a>
            <a href="index.html" class="btn btn-secondary">
                ← 返回首頁
            </a>
        </div>
    </div>
</body>
</html>

