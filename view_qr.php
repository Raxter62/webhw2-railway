
<?php
// view_qr.php - 從資料庫讀取 QR Code 並顯示
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

require __DIR__ . '/config.php';

// 獲取 ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    die('Invalid ID');
}

try {
    // 連接資料庫
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
    
    if ($conn->connect_error) {
        throw new Exception("資料庫連接失敗");
    }
    
    // 查詢 QR Code
    $sql = "SELECT qr_content FROM `{$TABLE_NAME}` WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("SQL 準備失敗");
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($qrBase64);
    
    if ($stmt->fetch() && !empty($qrBase64)) {
        // 解碼 Base64
        $qrBinary = base64_decode($qrBase64);
        
        if ($qrBinary === false) {
            throw new Exception("Base64 解碼失敗");
        }
        
        // 輸出圖片
        header('Content-Type: image/png');
        header('Content-Length: ' . strlen($qrBinary));
        header('Cache-Control: public, max-age=86400'); // 快取 1 天
        echo $qrBinary;
        
    } else {
        header('HTTP/1.1 404 Not Found');
        echo 'QR Code not found';
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    error_log("view_qr.php error: " . $e->getMessage());
    echo 'Error loading QR Code';
}
?>