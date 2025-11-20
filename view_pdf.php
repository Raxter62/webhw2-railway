<?php
// view_pdf.php - 動態顯示 PDF
mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Taipei');
error_reporting(E_ALL);
ini_set('display_errors', 0); // 關閉顯示錯誤，避免影響 PDF 輸出

/* 資料庫設定 */
require __DIR__ . '/config.php';

/* 取得 ID 參數 */
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('HTTP/1.0 400 Bad Request');
    die('無效的 ID');
}

try {
    /* 連接資料庫 */
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $conn->set_charset('utf8mb4');
    
    if ($conn->connect_error) {
        throw new Exception("資料庫連接失敗");
    }
    
    /* 查詢 PDF 內容 */
    $sql = "SELECT pdf_content, name, sid FROM `{$TABLE_NAME}` WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("SQL 準備失敗: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $pdfContent = $row['pdf_content'];
        $name = $row['name'];
        $sid = $row['sid'];
        
        if (empty($pdfContent)) {
            throw new Exception("PDF 內容不存在");
        }
        
        /* 解碼 Base64 */
        $pdfBinary = base64_decode($pdfContent);
        
        if ($pdfBinary === false || empty($pdfBinary)) {
            throw new Exception("PDF 解碼失敗");
        }
        
        /* 輸出 PDF */
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Registration_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $sid) . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $name) . '.pdf"');
        header('Content-Length: ' . strlen($pdfBinary));
        header('Cache-Control: public, max-age=3600');
        header('Pragma: public');
        
        echo $pdfBinary;
        
    } else {
        header('HTTP/1.0 404 Not Found');
        die('找不到報名資料');
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    error_log("PDF view error: " . $e->getMessage());
    die('系統錯誤：無法顯示 PDF');
}
?>