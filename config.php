<?php
// ===== LINE 設定 =====
$CHANNEL_SECRET = getenv('LINE_CHANNEL_SECRET');
$CHANNEL_ACCESS_TOKEN = getenv('LINE_CHANNEL_ACCESS_TOKEN');

// ===== 資料庫設定 =====
$DB_HOST = getenv('DB_HOST');
$DB_USER = getenv('DB_USER');
$DB_PASS = getenv('DB_PASS');
$DB_NAME = getenv('DB_NAME');
$TABLE_NAME = getenv('TABLE_NAME');

// ===== 站點設定 =====
$BASE_URL = getenv('BASE_URL');