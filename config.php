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

// ===== 郵件設定 =====
$MAIL_HOST = 'smtp.gmail.com';
$MAIL_PORT = 587;
$MAIL_USER = getenv('MAIL_USER') ?: '';
$MAIL_PASS = getenv('MAIL_PASS') ?: '';
$MAIL_FROM_NAME = '四系迎新報名';