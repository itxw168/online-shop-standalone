<?php

define('IN_CRYPT', true);
define('IN_SHOP', true);
require_once __DIR__ . '/../inc/crypt.php';
require_once __DIR__ . '/../inc/shop_store.php';
require_once __DIR__ . '/../inc/push.php';

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    $expected = md5(AES_KEY . 'shop_push_retry');
    $given    = isset($_GET['token']) ? (string)$_GET['token'] : '';
    if ($given === '' || !hash_equals($expected, $given)) {
        header('HTTP/1.1 403 Forbidden');
        echo "403 Forbidden\n";
        echo "如需通过网址触发，请使用带 token 的地址（token = md5(AES_KEY . 'shop_push_retry')）。\n";
        exit;
    }

    if (function_exists('fastcgi_finish_request')) {
        echo "已开始处理推送重试队列…\n";
        @fastcgi_finish_request();
    }
}

@ignore_user_abort(true);
@set_time_limit(55);

if (!shop_db_available()) {
    echo "[shop] 未检测到 pdo_sqlite 扩展，无法处理队列。\n";
    exit;
}

$stats = shop_push_queue_stats();
echo "[shop] 处理前队列：待重试 {$stats['pending']} / 已成功 {$stats['sent']} / 已失败 {$stats['failed']}\n";

$sent = shop_push_process_queue(30, 45);

$after = shop_push_queue_stats();
echo "[shop] 本次成功发送 {$sent} 条；处理后队列：待重试 {$after['pending']} / 已成功 {$after['sent']} / 已失败 {$after['failed']}\n";
echo "[shop] 完成时间：" . date('Y-m-d H:i:s') . "\n";
