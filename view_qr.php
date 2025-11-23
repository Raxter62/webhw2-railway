<?php
// ⚠️ 確保 <?php 標籤前沒有任何空白或 BOM
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);  // ✅ 關閉錯誤顯示很重要！

require __DIR__ . '/config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid ID');  // ✅ 使用 exit 而不是 die
}

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
    
    if ($conn->connect_error) {
        throw new Exception("資料庫連接失敗");
    }
    
    $sql = "SELECT qr_content FROM `{$TABLE_NAME}` WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("SQL 準備失敗");
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($qrBase64);
    
    if ($stmt->fetch() && !empty($qrBase64)) {
        $qrBinary = base64_decode($qrBase64);
        
        if ($qrBinary === false || strlen($qrBinary) < 100) {
            throw new Exception("Base64 解碼失敗");
        }
        
        // ✅ 清除之前可能的任何輸出
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // ✅ 設置正確的 headers
        header('Content-Type: image/png');
        header('Content-Length: ' . strlen($qrBinary));
        header('Cache-Control: public, max-age=86400');
        header('Access-Control-Allow-Origin: *');
        
        // ✅ 輸出二進制數據
        echo $qrBinary;
        exit;  // ✅ 立即結束，不要有任何後續輸出
        
    } else {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: text/plain');
        exit('QR Code not found');
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain');
    error_log("view_qr.php error: " . $e->getMessage());
    exit('Error loading QR Code');
}
// ✅ 不要有 ?> 結束標籤