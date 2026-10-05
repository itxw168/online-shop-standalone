<?php

if (session_status() === PHP_SESSION_NONE) {
    $__https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    @session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $__https,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    if (!empty($__https)) {
        header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
    }
}

$DATA_DIR = __DIR__ . '/data';
if (!is_dir($DATA_DIR)) { @mkdir($DATA_DIR, 0755, true); }

require_once __DIR__ . '/secret/config.secret.php';

define('IN_CRYPT', true);
require_once __DIR__ . '/inc/crypt.php';
require_once __DIR__ . '/inc/shop_store.php';
require_once __DIR__ . '/inc/push.php';

$ACCOUNT_FILE = $DATA_DIR . '/account.php';

function shop_admin_account() {
    global $ACCOUNT_FILE;
    if (is_file($ACCOUNT_FILE)) {
        $a = @include $ACCOUNT_FILE;
        if (is_array($a) && !empty($a['user']) && !empty($a['hash'])) return $a;
    }

    $a = ['user' => 'admin', 'hash' => password_hash('admin888', PASSWORD_DEFAULT)];
    shop_admin_account_save($a);
    return $a;
}
function shop_admin_account_save(array $a) {
    global $ACCOUNT_FILE;
    $php = "<?php\n// 独立商城后台账号（改动后请勿删除本文件）\nreturn "
         . var_export($a, true) . ";\n";
    file_put_contents($ACCOUNT_FILE, $php);
    @chmod($ACCOUNT_FILE, 0600);
}

// 是否仍在使用默认账号/密码（登录页提示 + 后台横幅提醒）
function shop_using_default_creds(array $a) {
    return (string)($a['user'] ?? '') === 'admin' && password_verify('admin888', (string)($a['hash'] ?? ''));
}
function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars((string)($_SESSION['csrf_token'] ?? '')) . '">';
}
function js_attr($s) {
    return htmlspecialchars(addslashes((string)$s), ENT_QUOTES, 'UTF-8');
}
function shop_admin_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

function shop_login_throttle_file() {
    global $DATA_DIR;
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return $DATA_DIR . '/.login_' . substr(hash('sha256', $ip), 0, 16) . '.json';
}
function shop_login_state() {
    $f = shop_login_throttle_file();
    if (!is_file($f)) return ['fails' => 0, 'until' => 0];
    $d = json_decode((string)@file_get_contents($f), true);
    if (!is_array($d)) return ['fails' => 0, 'until' => 0];
    return ['fails' => (int)($d['fails'] ?? 0), 'until' => (int)($d['until'] ?? 0)];
}
function shop_login_state_save(array $s) {
    @file_put_contents(shop_login_throttle_file(), json_encode($s), LOCK_EX);
    @chmod(shop_login_throttle_file(), 0600);
}
function shop_login_state_clear() {
    @unlink(shop_login_throttle_file());
}

function shop_login_lock_left() {
    $s = shop_login_state();
    return max(0, $s['until'] - time());
}

;
$loginErr = '';
$acct = shop_admin_account();

if (isset($_GET['logout'])) {
    if (isset($_SESSION['shop_admin'])) shop_audit_add('shop_admin_logout', (string)$_SESSION['shop_admin'], '主动退出登录');
    $_SESSION = [];
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'shop_admin_login') {
    $u = (string)($_POST['user'] ?? '');
    $p = (string)($_POST['pass'] ?? '');
    $tk = (string)($_POST['csrf'] ?? '');
    $lock = shop_login_lock_left();
    if ($lock > 0) {

        shop_audit_add('shop_admin_login', $u, '被限流拒绝（剩余 ' . (int)ceil($lock / 60) . ' 分钟）', '拒绝');
        $mins = (int)ceil($lock / 60);
        $loginErr = '尝试次数过多，请 ' . $mins . ' 分钟后再试';
    } elseif (!hash_equals($csrf, $tk)) {
        $loginErr = '会话已过期，请重试';
    } elseif ($u === $acct['user'] && password_verify($p, $acct['hash'])) {
        shop_login_state_clear();
        shop_audit_add('shop_admin_login', $u, '登录成功');
        session_regenerate_id(true);
        $_SESSION['shop_admin'] = $u;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    } else {
        shop_audit_add('shop_admin_login', $u, '密码错误', '失败');
        $st = shop_login_state();
        $st['fails'] = (int)$st['fails'] + 1;
        if ($st['fails'] >= 5) {
            $st['until'] = time() + 600;
            $st['fails'] = 0;
            $loginErr = '密码错误次数过多，已锁定 10 分钟';
        } else {
            $left = 5 - $st['fails'];
            $loginErr = '账号或密码不正确（还可尝试 ' . $left . ' 次）';
        }
        shop_login_state_save($st);
    }
}

$isLogged = isset($_SESSION['shop_admin']);

if (!$isLogged) {
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>在线商城 · 后台登录</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{display:flex;align-items:center;justify-content:center;min-height:100vh;
  background:linear-gradient(135deg,#2563eb 0%,#0f766e 100%);
  font-family:"Microsoft YaHei","PingFang SC",-apple-system,sans-serif;padding:20px;}
.box{background:#fff;padding:44px 40px;border-radius:18px;width:100%;max-width:400px;
  box-shadow:0 24px 64px rgba(15,23,42,.28);}
.box h1{font-size:24px;color:#1e293b;text-align:center;margin-bottom:6px;}
.box .sub{text-align:center;color:#64748b;font-size:13.5px;margin-bottom:30px;}
.box label{display:block;font-size:13px;color:#475569;margin-bottom:7px;font-weight:600;}
.box input{width:100%;padding:11px 14px;border:1px solid #cbd5e1;border-radius:9px;
  font-size:14px;margin-bottom:18px;font-family:inherit;outline:none;transition:.15s;}
.box input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.13);}
.box button{width:100%;padding:12px;border:none;border-radius:9px;cursor:pointer;
  background:linear-gradient(135deg,#2563eb,#0f766e);color:#fff;font-size:15px;
  font-weight:700;font-family:inherit;transition:.15s;}
.box button:hover{filter:brightness(1.08);}
.err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;font-size:13px;
  padding:10px 13px;border-radius:9px;margin-bottom:18px;}
.tip{margin-top:20px;font-size:12.5px;color:#94a3b8;text-align:center;line-height:1.7;}
</style>
</head>
<body>
  <form class="box" method="post">
    <h1>🛒 在线商城后台</h1>
    <div class="sub">请登录后管理商品与订单</div>
    <?php $__lock = shop_login_lock_left(); if ($__lock > 0): ?>
      <div class="err">🔒 尝试次数过多，请 <?php echo (int)ceil($__lock / 60); ?> 分钟后再试</div>
    <?php endif; ?>
    <?php if ($loginErr !== ''): ?><div class="err"><?php echo shop_admin_h($loginErr); ?></div><?php endif; ?>
    <input type="hidden" name="action" value="shop_admin_login">
    <input type="hidden" name="csrf" value="<?php echo shop_admin_h($csrf); ?>">
    <label>账号</label>
    <input type="text" name="user" autocomplete="username" autofocus required>
    <label>密码</label>
    <input type="password" name="pass" autocomplete="current-password" required>
    <button type="submit">登 录</button>
    <?php if (shop_using_default_creds($acct)): ?>
    <div class="tip">默认账号 admin / 密码 admin888<br>登录后请立刻修改</div>
    <?php endif; ?>
  </form>
</body>
</html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'shop_admin_account') {
    $old  = (string)($_POST['old_pass'] ?? '');
    $newU = trim((string)($_POST['new_user'] ?? ''));
    $new  = (string)($_POST['new_pass'] ?? '');
    $tk   = (string)($_POST['csrf'] ?? '');
    $err  = '';
    if (!hash_equals($csrf, $tk)) {
        $err = '会话已过期，请刷新页面后重试';
    } elseif (!password_verify($old, $acct['hash'])) {

        $err = '原密码不正确';
    } elseif ($newU !== '' && !preg_match('/^[A-Za-z0-9_-]{2,32}$/', $newU)) {
        $err = '账号名需 2~32 位，只能用字母、数字、下划线、短横线';
    } elseif ($new !== '' && strlen($new) < 6) {
        $err = '新密码至少 6 位';
    } elseif ($newU === '' && $new === '') {
        $err = '账号名和新密码至少填一项';
    } else {
        $done = [];
        if ($newU !== '' && $newU !== (string)$acct['user']) {
            $acct['user'] = $newU;
            $_SESSION['shop_admin'] = $newU;
            $done[] = '账号名';
        }
        if ($new !== '') {
            $acct['hash'] = password_hash($new, PASSWORD_DEFAULT);
            $done[] = '密码';
        }
        if ($done) {
            shop_admin_account_save($acct);

            shop_audit_add('shop_admin_account', (string)$acct['user'], implode(' + ', $done) . ' 修改成功');
            $_SESSION['save_msg'] = implode(' 和 ', $done) . ' 已修改，请牢记';
            $_SESSION['save_msg_type'] = 'success';
        } else {
            $_SESSION['save_msg'] = '内容没有变化';
            $_SESSION['save_msg_type'] = 'info';
        }
    }
    if ($err !== '') { $_SESSION['save_msg'] = $err; $_SESSION['save_msg_type'] = 'error'; }
    header('Location: ?sub=account');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] !== 'shop_admin_login') {
    $tk = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($csrf, $tk)) {
        $_SESSION['save_msg'] = '操作失败：安全校验未通过，请刷新页面后重试';
        $_SESSION['save_msg_type'] = 'error';
        header('Location: ?sub=' . urlencode((string)($_GET['sub'] ?? 'orders')));
        exit;
    }
}
if (isset($_GET['csrf']) && !hash_equals($csrf, (string)$_GET['csrf'])) {
    $_SESSION['save_msg'] = '操作失败：安全校验未通过，请刷新页面后重试';
    $_SESSION['save_msg_type'] = 'error';
    header('Location: ?sub=' . urlencode((string)($_GET['sub'] ?? 'orders')));
    exit;
}

if (isset($_GET['shop_ajax']) && ($_GET['shop_ajax'] === 'ipgeo' || $_GET['shop_ajax'] === 'ipgeo_diag')) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    @ini_set('display_errors', '0');
    $__geoIp = (string)($_GET['ip'] ?? '');
    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'ip' => '', 'location' => '', 'source' => '',
                              'note' => '服务端致命错误：' . $e['message']], JSON_UNESCAPED_UNICODE);
        }
    });
    $geoFile = __DIR__ . '/inc/ip_geo.php';
    if (!is_file($geoFile)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "【IP 归属地模块缺失】\n\n缺少文件：inc/ip_geo.php\n";
        exit;
    }
    require_once $geoFile;
    if ($_GET['shop_ajax'] === 'ipgeo_diag') {
        header('Content-Type: text/plain; charset=utf-8');
        echo ip_geo_diag_text();
        exit;
    }
    try {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(ip_geo_lookup($__geoIp), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'ip' => $__geoIp, 'location' => '', 'source' => '',
                          'note' => '服务端异常：' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}


if (isset($_GET['shop_ajax']) && $_GET['shop_ajax'] === 'new_orders') {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    @ini_set('display_errors', '0');
    $__since = (int)($_GET['since'] ?? 0);
    $__stime = trim((string)($_GET['since_time'] ?? ''));
    $__res = ['latest' => $__since, 'latest_time' => $__stime, 'orders' => []];
    try {
        if ($__stime === '') $__stime = date('Y-m-d H:i:s');
        if (function_exists('shop_order_watch')) {
            $__res = shop_order_watch($__since, $__stime, 20);
        } elseif (function_exists('shop_order_new_since')) {
            $__r2 = shop_order_new_since($__since, 20);
            $__res = ['latest' => $__r2['latest'], 'latest_time' => $__stime, 'orders' => $__r2['orders']];
        }
    } catch (Throwable $e) {
    }
    echo json_encode(['ok' => true, 'latest' => (int)$__res['latest'], 'latest_time' => (string)($__res['latest_time'] ?? ''), 'orders' => $__res['orders']], JSON_UNESCAPED_UNICODE);
    exit;
}

    // 长轮询版本：wait 秒内没有新订单就挂着等，有新订单立刻返回。
    // 这样最小化/后台标签页也能实时收到（定时器会被浏览器限流，挂着的请求不会）。
    if (isset($_GET['shop_ajax']) && $_GET['shop_ajax'] === 'new_orders_wait') {
        while (ob_get_level() > 0) { @ob_end_clean(); }
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        @ini_set('display_errors', '0');
        $__since = (int)($_GET['since'] ?? 0);
        $__stime = trim((string)($_GET['since_time'] ?? ''));
        $__wait  = max(0, min(50, (int)($_GET['wait'] ?? 0)));
    
        // 关键：必须在等待前释放会话锁，否则同一个后台的其他请求会被卡住
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) { @session_write_close(); }
        @set_time_limit($__wait + 20);
    
        // 水位初始化：客户端没带 since_time（老版本/首次）时，用「现在」当起点，
        // 否则状态变化检测会永远被跳过（因为比较条件是 updated_at > since_time）。
        if ($__stime === '') { $__stime = date('Y-m-d H:i:s'); $__init = true; } else { $__init = false; }
    
        $__res = ['latest' => $__since, 'latest_time' => $__stime, 'orders' => []];
        $__end = microtime(true) + $__wait;
        do {
            try {
                if (function_exists('shop_order_watch')) {
                    $__res = shop_order_watch($__since, $__stime, 20);
                } elseif (function_exists('shop_order_new_since')) {
                    // 降级：旧版 shop_store.php 没有 watch，至少保证新订单能提醒
                    $__r2 = shop_order_new_since($__since, 20);
                    $__res = ['latest' => $__r2['latest'], 'latest_time' => $__stime, 'orders' => $__r2['orders']];
                }
            } catch (Throwable $e) {
            }
            if (!empty($__res['orders']) || $__wait === 0) break;
            if (connection_aborted()) exit;
            usleep(700000);
        } while (microtime(true) < $__end);
    
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'latest' => (int)$__res['latest'], 'latest_time' => (string)($__res['latest_time'] ?? ''), 'orders' => $__res['orders']], JSON_UNESCAPED_UNICODE);
        exit;
    }

$currentModule = 'shop';

$__audit_action = '';
$__audit_target = '';
$__audit_detail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $__audit_action = (string)$_POST['action'];

    foreach (['order_no', 'id', 'name', 'cate_key', 'key'] as $k) {
        if (isset($_POST[$k]) && trim((string)$_POST[$k]) !== '') { $__audit_target = (string)$_POST[$k]; break; }
    }
    if (isset($_POST['order_nos']) && is_array($_POST['order_nos'])) {
        $__audit_target = implode(',', array_slice(array_map('strval', $_POST['order_nos']), 0, 8));
        $__audit_detail = '共 ' . count($_POST['order_nos']) . ' 笔';
    }
    if (isset($_POST['to'])) $__audit_detail = trim($__audit_detail . ' 目标状态=' . (string)$_POST['to']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $__audit_action = 'export_csv';
        $__audit_target = 'status=' . (string)($_GET['status'] ?? 'all') . ' q=' . (string)($_GET['q'] ?? '');
    } elseif (isset($_GET['shop_order_set'])) {
        $__audit_action = 'shop_order_set';
        $__audit_target = (string)$_GET['shop_order_set'];
        $__audit_detail = '改为 ' . (string)($_GET['to'] ?? '');
    } elseif (isset($_GET['del_shop_order'])) {
        $__audit_action = 'del_shop_order';  $__audit_target = (string)$_GET['del_shop_order'];
    } elseif (isset($_GET['del_shop_product'])) {
        $__audit_action = 'del_shop_product'; $__audit_target = (string)$_GET['del_shop_product'];
    } elseif (isset($_GET['del_shop_category'])) {
        $__audit_action = 'del_shop_category'; $__audit_target = (string)$_GET['del_shop_category'];
    } elseif (isset($_GET['move_shop_product'])) {
        $__audit_action = 'move_shop_product'; $__audit_target = (string)$_GET['move_shop_product'];
        $__audit_detail = '方向 ' . (string)($_GET['dir'] ?? 'up');
    } elseif (isset($_GET['audit_clear'])) {
        $__audit_action = 'shop_audit_clear';
        $__audit_detail = '清空 ' . (string)$_GET['audit_clear'] . ' 天前';
    }
}

$__audit_skip = ['shop_admin_login', 'shop_admin_logout'];
if ($__audit_action !== '' && !in_array($__audit_action, $__audit_skip, true)) {

    unset($_SESSION['save_msg'], $_SESSION['save_msg_type']);
    $__audit_id = shop_audit_add($__audit_action, $__audit_target, $__audit_detail);
    if ($__audit_id > 0) {
        register_shutdown_function(function () use ($__audit_id) {
            $msg = (string)($_SESSION['save_msg'] ?? '');
            if ($msg !== '') shop_audit_set_result($__audit_id, $msg);
        });
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $__a = (string)$_POST['action'];
    $__shopAudit = ['save_shop_page','save_shop_pay','save_shop_push','save_shop_product',
                    'save_shop_category','shop_order_bulk','shop_order_set','shop_push_test','shop_push_process','shop_push_log_clear'];
    if (in_array($__a, $__shopAudit, true)) {
        $__tgt = ''; $__det = '';
        foreach (['order_no','id','name','cate_key','key'] as $__k) {
            if (isset($_POST[$__k]) && trim((string)$_POST[$__k]) !== '') { $__tgt = (string)$_POST[$__k]; break; }
        }
        if (isset($_POST['order_nos']) && is_array($_POST['order_nos'])) {
            $__tgt = implode(',', array_slice(array_map('strval', $_POST['order_nos']), 0, 8));
            $__det = '共 ' . count($_POST['order_nos']) . ' 笔';
        }
        if ($__tgt === '') {
            $__tgtMap = [
                'save_shop_page'      => '页面文案',
                'save_shop_pay'       => '收款与表单',
                'save_shop_push'      => '消息推送',
                'shop_push_process'   => '重试队列',
                'shop_push_log_clear' => '推送记录',
            ];
            $__tgt = $__tgtMap[$__a] ?? '';
        }
        unset($_SESSION['save_msg'], $_SESSION['save_msg_type']);
        $__aid = shop_audit_add($__a, $__tgt, $__det);
        if ($__aid > 0) register_shutdown_function(function () use ($__aid) {
            $m = (string)($_SESSION['save_msg'] ?? '');
            if ($m !== '') shop_audit_set_result($__aid, $m);
        });
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $__g = ''; $__gt = ''; $__gd = '';
    if (isset($_GET['export']) && $_GET['export'] === 'csv') { $__g = 'export_csv'; $__gt = 'status=' . (string)($_GET['status'] ?? 'all'); }
    elseif (isset($_GET['shop_order_set'])) { $__g = 'shop_order_set'; $__gt = (string)$_GET['shop_order_set']; $__gd = '改为 ' . (string)($_GET['to'] ?? ''); }
    elseif (isset($_GET['del_shop_order'])) { $__g = 'del_shop_order'; $__gt = (string)$_GET['del_shop_order']; }
    elseif (isset($_GET['del_shop_product'])) { $__g = 'del_shop_product'; $__gt = (string)$_GET['del_shop_product']; }
    elseif (isset($_GET['del_shop_category'])) { $__g = 'del_shop_category'; $__gt = (string)$_GET['del_shop_category']; }
    elseif (isset($_GET['move_shop_product'])) { $__g = 'move_shop_product'; $__gt = (string)$_GET['move_shop_product']; }
    elseif (isset($_GET['rl_release_all'])) { $__g = 'rl_release_all'; }
    elseif (isset($_GET['rl_release_ip'])) { $__g = 'rl_release_ip'; $__gt = (string)$_GET['rl_release_ip']; }
    elseif (isset($_GET['rl_release'])) { $__g = 'rl_release'; $__gt = (string)$_GET['rl_release']; }
    elseif (isset($_GET['purge_ip'])) { $__g = 'purge_ip'; $__gt = (string)$_GET['purge_ip']; }
    elseif (isset($_GET['purge_pending'])) { $__g = 'purge_pending'; $__gd = '阈值 ' . (int)$_GET['purge_pending'] . ' 分钟'; }
    elseif (isset($_GET['purge_expired'])) { $__g = 'purge_expired'; }
    elseif (isset($_GET['audit_clear'])) { $__g = 'shop_audit_clear'; $__gd = '清空 ' . (string)$_GET['audit_clear'] . ' 天前'; }
    $__gtMap = [
        'export_csv'      => '订单导出',
        'rl_release_all'  => '全部限流记录',
        'purge_expired'   => '已失效订单',
        'purge_pending'   => '未付款订单',
        'shop_audit_clear'=> '操作日志',
    ];
    if ($__gt === '' && isset($__gtMap[$__g])) $__gt = $__gtMap[$__g];
    if ($__g !== '') {
        unset($_SESSION['save_msg'], $_SESSION['save_msg_type']);
        $__aid = shop_audit_add($__g, $__gt, $__gd);
        if ($__aid > 0) register_shutdown_function(function () use ($__aid) {
            $m = (string)($_SESSION['save_msg'] ?? '');
            if ($m !== '') shop_audit_set_result($__aid, $m);
        });
    }
}

if (isset($_GET['audit_clear'])) {
    $del = shop_audit_clear((int)$_GET['audit_clear']);
    $_SESSION['save_msg'] = '已清理 ' . $del . ' 条日志';
    $_SESSION['save_msg_type'] = 'success';
    header('Location: ?sub=log'); exit;
}

if (isset($_GET['purge_expired'])) {
    $del = shop_order_purge_expired(1);
    $_SESSION['save_msg'] = '已彻底删除 ' . $del . ' 笔已失效订单';
    $_SESSION['save_msg_type'] = 'success';
    header('Location: ?sub=security'); exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save_contact') {
    $c = shop_config(true);
    $c['contact'] = [
        'enable'      => isset($_POST['enable']) ? 1 : 0,
        'owner_name'  => trim((string)($_POST['owner_name'] ?? '站长')),
        'title'       => trim((string)($_POST['title'] ?? '')),
        'subtitle'    => trim((string)($_POST['subtitle'] ?? '')),
        'notice'      => trim((string)($_POST['notice'] ?? '')),
        'wechat'      => trim((string)($_POST['wechat'] ?? '')),
        'qq'          => trim((string)($_POST['qq'] ?? '')),
        'phone'       => trim((string)($_POST['phone'] ?? '')),
        'email'       => trim((string)($_POST['email'] ?? '')),
        'work_hours'  => trim((string)($_POST['work_hours'] ?? '')),
        'qr_url'      => trim((string)($_POST['qr_url'] ?? '')),
        'qr_tip'      => trim((string)($_POST['qr_tip'] ?? '')),
        'douyin_home' => trim((string)($_POST['douyin_home'] ?? '')),
        'douyin_live' => trim((string)($_POST['douyin_live'] ?? '')),
        'douyin_id'   => trim((string)($_POST['douyin_id'] ?? '')),
        'extra'       => trim((string)($_POST['extra'] ?? '')),
        'footer_note' => trim((string)($_POST['footer_note'] ?? '')),
    ];
    shop_config_save($c);
    $_SESSION['save_msg'] = '联系方式页设置已保存';
    $_SESSION['save_msg_type'] = 'success';
    header('Location: ?sub=contact'); exit;
}

if (isset($_GET['rl_release']) && $currentModule == 'shop') {
    $okDel = shop_rl_release_file((string)$_GET['rl_release']);
    $_SESSION['save_msg'] = $okDel ? '已解除该条限流记录' : '该记录已不存在（可能已自动过期）';
    $_SESSION['save_msg_type'] = $okDel ? 'success' : 'error';
    header('Location: ?sub=security'); exit;
}
if (isset($_GET['rl_release_ip']) && $currentModule == 'shop') {
    $n = shop_rl_release_ip((string)$_GET['rl_release_ip']);
    $_SESSION['save_msg'] = $n > 0 ? ('已解除该 IP 的 ' . $n . ' 条限流记录') : '该 IP 当前没有被限流';
    $_SESSION['save_msg_type'] = $n > 0 ? 'success' : 'error';
    header('Location: ?sub=security'); exit;
}
if (isset($_GET['rl_release_all']) && $currentModule == 'shop') {
    $n = shop_rl_release_all();
    $_SESSION['save_msg'] = '已清空全部限流记录（' . $n . ' 条）';
    $_SESSION['save_msg_type'] = 'success';
    header('Location: ?sub=security'); exit;
}
if (isset($_GET['purge_pending']) && $currentModule == 'shop') {
    $min = (int)$_GET['purge_pending'];
    $n   = shop_order_expire_unpaid($min);
    $_SESSION['save_msg'] = $n > 0 ? ('已清理 ' . $n . ' 笔超过 ' . $min . ' 分钟的未付款订单') : '没有需要清理的订单';
    $_SESSION['save_msg_type'] = 'success';
    header('Location: ?sub=security'); exit;
}
if (isset($_GET['purge_ip']) && $currentModule == 'shop') {
    $ip = (string)$_GET['purge_ip'];
    $n  = shop_order_delete_pending_by_ip($ip);
    $m  = shop_rl_release_ip($ip);
    $_SESSION['save_msg'] = '已删除该 IP 的 ' . $n . ' 笔未付款订单' . ($m > 0 ? ('，并解除 ' . $m . ' 条限流记录') : '');
    $_SESSION['save_msg_type'] = 'success';
    header('Location: ?sub=security'); exit;
}

;

    if (isset($_POST['action']) && $_POST['action'] == 'save_shop_page') {
        $cfg = shop_config(true);

        $cfg['page_title']    = trim((string)($_POST['page_title'] ?? ''));
        $cfg['page_subtitle'] = trim((string)($_POST['page_subtitle'] ?? ''));
        $cfg['announcement']  = trim((string)($_POST['announcement'] ?? ''));

        $cfg['brand_name']      = trim((string)($_POST['brand_name'] ?? ''));
        $cfg['topbar_query']    = trim((string)($_POST['topbar_query'] ?? ''));
        $cfg['topbar_back']     = trim((string)($_POST['topbar_back'] ?? ''));
        $cfg['topbar_back_url'] = trim((string)($_POST['topbar_back_url'] ?? ''));

        $cfg['footer_title']     = trim((string)($_POST['footer_title'] ?? ''));
        $cfg['footer_note']      = trim((string)($_POST['footer_note'] ?? ''));
        $cfg['footer_link1_text'] = trim((string)($_POST['footer_link1_text'] ?? ''));
        $cfg['footer_link1_url']  = trim((string)($_POST['footer_link1_url'] ?? ''));
        $cfg['footer_link2_text'] = trim((string)($_POST['footer_link2_text'] ?? ''));
        $cfg['footer_link2_url']  = trim((string)($_POST['footer_link2_url'] ?? ''));

        $def = shop_default_config();
        foreach (['topbar_back_url', 'footer_link1_url', 'footer_link2_url'] as $k) {
            if ($cfg[$k] === '') $cfg[$k] = $def[$k];
        }
        if ($cfg['page_title'] === '') $cfg['page_title'] = $def['page_title'];

        shop_config_save($cfg);
        $_SESSION['save_msg'] = '页面文案已保存';
        $_SESSION['save_msg_type'] = 'success';
        header('Location: admin.php?sub=page');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] == 'save_shop_pay') {
        $cfg = shop_config(true);

        $cfg['pay']['qr_mode']            = ((string)($_POST['qr_mode'] ?? 'aggregate') === 'split') ? 'split' : 'aggregate';
        $cfg['pay']['qr_url']             = trim((string)($_POST['qr_url'] ?? ''));
        $cfg['pay']['qr_wechat']          = trim((string)($_POST['qr_wechat'] ?? ''));
        $cfg['pay']['qr_alipay']          = trim((string)($_POST['qr_alipay'] ?? ''));
        $cfg['pay']['qr_tip']             = trim((string)($_POST['qr_tip'] ?? ''));
        $cfg['pay']['payee_name']         = trim((string)($_POST['payee_name'] ?? ''));
        $cfg['pay']['code_mode']          = ((string)($_POST['code_mode'] ?? 'short') === 'wechat') ? 'wechat' : 'short';
        $cfg['pay']['code_length']        = max(2, min(6, (int)($_POST['code_length'] ?? 2)));
        $cfg['pay']['allow_regenerate']   = isset($_POST['allow_regenerate']) ? 1 : 0;

        $cfg['pay']['unpaid_expire_minutes'] = max(0, min(1440, (int)($_POST['unpaid_expire_minutes'] ?? 10)));
        $cfg['pay']['expired_keep_days'] = max(0, min(365, (int)($_POST['expired_keep_days'] ?? 7)));
        $cfg['pay']['contact_admin_url']  = trim((string)($_POST['contact_admin_url'] ?? ''));
        $cfg['pay']['contact_admin_text'] = trim((string)($_POST['contact_admin_text'] ?? ''));
        $cfg['pay']['tips']               = trim((string)($_POST['tips'] ?? ''));

        $cfg['form']['require_wechat']    = isset($_POST['require_wechat']) ? 1 : 0;
        $cfg['form']['require_phone']     = isset($_POST['require_phone']) ? 1 : 0;
        $cfg['form']['require_book_time'] = isset($_POST['require_book_time']) ? 1 : 0;
        $cfg['form']['enable_booking']    = isset($_POST['enable_booking']) ? 1 : 0;
        $cfg['form']['enable_remark']     = isset($_POST['enable_remark']) ? 1 : 0;
        $cfg['form']['notice']            = trim((string)($_POST['form_notice'] ?? ''));

        $cfg['query']['enable']           = isset($_POST['query_enable']) ? 1 : 0;

        $cfg['query']['need_phone_tail']  = 1;
        $cfg['query']['page_title']       = trim((string)($_POST['query_page_title'] ?? ''));
        $cfg['query']['page_tip']         = trim((string)($_POST['query_page_tip'] ?? ''));

        shop_config_save($cfg);
        $_SESSION['save_msg'] = '商城设置已保存';
        $_SESSION['save_msg_type'] = 'success';
        header('Location: ?sub=pay');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] == 'save_shop_push') {
        $cfg = shop_config(true);
        $def = shop_default_config();

        $cfg['push']['enable'] = isset($_POST['push_enable']) ? 1 : 0;

        $events = [];
        if (isset($_POST['ev_created'])) $events[] = 'created';
        if (isset($_POST['ev_paid']))    $events[] = 'paid';
        $cfg['push']['on_events'] = $events;

        $tpl = (string)($_POST['push_template'] ?? '');
        $cfg['push']['template'] = trim($tpl) !== '' ? $tpl : $def['push']['template'];

        $ttl = (string)($_POST['push_title'] ?? '');
        $cfg['push']['title'] = trim($ttl) !== '' ? trim($ttl) : $def['push']['title'];

        $byKey = [];
        foreach ((array)$cfg['push']['channels'] as $ch) {
            $k = (string)($ch['key'] ?? $ch['type'] ?? '');
            if ($k !== '') $byKey[$k] = $ch;
        }

        $byKey['gotify'] = array_merge($byKey['gotify'] ?? [], [
            'key'      => 'gotify',
            'type'     => 'gotify',
            'name'     => 'Gotify（自建，安卓 App，推荐）',
            'enable'   => isset($_POST['gotify_enable']) ? 1 : 0,
            'server'   => trim((string)($_POST['gotify_server'] ?? '')),
            'token'    => trim((string)($_POST['gotify_token'] ?? '')),
            'priority' => max(0, min(10, (int)($_POST['gotify_priority'] ?? 5))),
            'title'    => trim((string)($_POST['gotify_title'] ?? '新订单通知')),
        ]);

        $byKey['ntfy'] = array_merge($byKey['ntfy'] ?? [], [
            'key'      => 'ntfy',
            'type'     => 'ntfy',
            'name'     => 'ntfy 推送（自建，安卓 App）',
            'enable'   => isset($_POST['ntfy_enable']) ? 1 : 0,
            'server'   => trim((string)($_POST['ntfy_server'] ?? '')),
            'topic'    => trim((string)($_POST['ntfy_topic'] ?? '')),
            'token'    => trim((string)($_POST['ntfy_token'] ?? '')),
            'priority' => trim((string)($_POST['ntfy_priority'] ?? 'high')),
            'tags'     => trim((string)($_POST['ntfy_tags'] ?? '')),
            'title'    => trim((string)($_POST['ntfy_title'] ?? '新订单通知')),
        ]);

        $byKey['email'] = array_merge($byKey['email'] ?? [], [
            'key'       => 'email',
            'type'      => 'email',
            'name'      => '邮件推送（SMTP）',
            'enable'    => isset($_POST['email_enable']) ? 1 : 0,
            'host'      => trim((string)($_POST['email_host'] ?? '')),
            'port'      => max(1, min(65535, (int)($_POST['email_port'] ?? 465))),
            'secure'    => in_array(($_POST['email_secure'] ?? 'ssl'), ['ssl', 'tls', 'none'], true) ? (string)$_POST['email_secure'] : 'ssl',
            'user'      => trim((string)($_POST['email_user'] ?? '')),
            'pass'      => (string)($_POST['email_pass'] ?? ''),
            'from'      => trim((string)($_POST['email_from'] ?? '')),
            'to'        => trim((string)($_POST['email_to'] ?? '')),
            'from_name' => trim((string)($_POST['email_from_name'] ?? '')),
        ]);

        $byKey['webhook'] = array_merge($byKey['webhook'] ?? [], [
            'key'            => 'webhook',
            'type'           => 'webhook',
            'name'           => '通用 Webhook（企业微信/钉钉/飞书/Bark/Gotify/PushMe）',
            'enable'         => isset($_POST['hook_enable']) ? 1 : 0,
            'url'            => trim((string)($_POST['hook_url'] ?? '')),
            'method'         => ((string)($_POST['hook_method'] ?? 'POST') === 'GET') ? 'GET' : 'POST',
            'content_type'   => trim((string)($_POST['hook_content_type'] ?? 'application/json')),
            'headers'        => trim((string)($_POST['hook_headers'] ?? '')),
            'body_template'  => (string)($_POST['hook_body'] ?? ''),
        ]);

        $ordered = [];
        foreach (['gotify', 'ntfy', 'email', 'webhook'] as $k) {
            if (isset($byKey[$k])) { $ordered[] = $byKey[$k]; unset($byKey[$k]); }
        }
        foreach ($byKey as $ch) $ordered[] = $ch;
        $cfg['push']['channels'] = $ordered;

        shop_config_save($cfg);
        $_SESSION['save_msg'] = '推送设置已保存';
        $_SESSION['save_msg_type'] = 'success';
        header('Location: ?sub=push');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] == 'save_shop_product') {
        $cfg  = shop_config(true);
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            $_SESSION['save_msg'] = '保存失败：商品名称不能为空';
            $_SESSION['save_msg_type'] = 'error';
            header('Location: ?sub=products');
            exit;
        }

        $tags = [];
        foreach (preg_split('/[,，、]+/u', (string)($_POST['tags'] ?? '')) as $t) {
            $t = trim($t);
            if ($t !== '') $tags[] = $t;
        }

        $stock = (int)($_POST['stock'] ?? -1);
        if ($stock < 0) $stock = -1;

        $unit = trim((string)($_POST['unit'] ?? ''));
        if ($unit === '') $unit = '次';

        $item = [
            'id'            => trim((string)($_POST['id'] ?? '')),
            'name'          => $name,
            'category'      => trim((string)($_POST['category'] ?? '')),
            'tags'          => $tags,
            'desc'          => trim((string)($_POST['desc'] ?? '')),
            'image'         => trim((string)($_POST['image'] ?? '')),
            'icon'          => trim((string)($_POST['icon'] ?? '')),
            'price'         => round((float)($_POST['price'] ?? 0), 2),
            'unit'          => $unit,
            'stock'         => $stock,
            'allow_now'     => isset($_POST['allow_now']) ? 1 : 0,
            'allow_booking' => isset($_POST['allow_booking']) ? 1 : 0,
            'status'        => isset($_POST['status_on']) ? 'on' : 'off',
        ];

        $updated = false;
        if ($item['id'] !== '') {
            foreach ($cfg['products'] as $i => $p) {
                if ((string)($p['id'] ?? '') === $item['id']) {
                    $cfg['products'][$i] = $item;
                    $updated = true;
                    break;
                }
            }
        }
        if (!$updated) {
            if ($item['id'] === '') $item['id'] = shop_gen_id();
            $cfg['products'][] = $item;
        }

        shop_config_save($cfg);
        $_SESSION['save_msg'] = $updated ? ('商品「' . $name . '」已更新') : ('商品「' . $name . '」已新增');
        $_SESSION['save_msg_type'] = 'success';
        header('Location: ?sub=products');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] == 'save_shop_category') {
        $cfg  = shop_config(true);
        $cats = is_array($cfg['categories']) ? $cfg['categories'] : [];
        $oldKey = trim((string)($_POST['old_key'] ?? ''));

        if ($oldKey !== '') {
            $newName = trim((string)($_POST['name'] ?? ''));
            if ($newName === '') {
                $_SESSION['save_msg'] = '保存失败：分类名称不能为空';
                $_SESSION['save_msg_type'] = 'error';
                header('Location: ?sub=categories');
                exit;
            }
            foreach ($cats as $i => $c) {
                if ((string)($c['key'] ?? '') === $oldKey) { $cats[$i]['name'] = $newName; break; }
            }
            $_SESSION['save_msg'] = '分类已重命名为「' . $newName . '」';
        } else {
            $newName = trim((string)($_POST['new_name'] ?? ''));
            if ($newName === '') {
                $_SESSION['save_msg'] = '保存失败：分类名称不能为空';
                $_SESSION['save_msg_type'] = 'error';
                header('Location: ?sub=categories');
                exit;
            }
            $newKey = trim((string)($_POST['new_key'] ?? ''));
            if ($newKey === '' || !preg_match('/^[A-Za-z0-9_]{1,32}$/', $newKey)) {
                $newKey = 'cat_' . substr(md5($newName . microtime()), 0, 8);
            }
            foreach ($cats as $c) {
                if ((string)($c['key'] ?? '') === $newKey) {
                    $_SESSION['save_msg'] = '保存失败：分类标识「' . $newKey . '」已存在';
                    $_SESSION['save_msg_type'] = 'error';
                    header('Location: ?sub=categories');
                    exit;
                }
            }
            $cats[] = ['key' => $newKey, 'name' => $newName];
            $_SESSION['save_msg'] = '分类「' . $newName . '」已新增';
        }

        $cfg['categories'] = array_values($cats);
        shop_config_save($cfg);
        $_SESSION['save_msg_type'] = 'success';
        header('Location: ?sub=categories');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] == 'shop_order_bulk') {
        $nos = isset($_POST['order_nos']) && is_array($_POST['order_nos']) ? $_POST['order_nos'] : [];
        $clean = [];
        foreach ($nos as $v) {
            if (is_string($v) && trim($v) !== '') $clean[] = trim($v);
        }
        $act  = (string)($_POST['bulk_action'] ?? '');
        $back = '&status=' . urlencode((string)($_POST['back_status'] ?? 'all'))
              . '&q=' . urlencode((string)($_POST['back_q'] ?? ''))
              . '&page=' . (int)($_POST['back_page'] ?? 1);

        if (empty($clean)) {
            $_SESSION['save_msg'] = '请先勾选要处理的订单';
            $_SESSION['save_msg_type'] = 'error';
            header('Location: ?sub=orders' . $back);
            exit;
        }

        if ($act === 'delete') {
            $n = shop_order_delete_many($clean);
            $_SESSION['save_msg'] = '已删除 ' . (int)$n . ' 条订单';
            $_SESSION['save_msg_type'] = 'success';
        } elseif (in_array($act, ['paid', 'processing', 'completed', 'cancelled', 'payment_failed', 'refunded'], true)) {
            $n = 0;
            foreach ($clean as $no) {
                $r = shop_order_set_status($no, $act, ['verified_by' => (string)($_SESSION['username'] ?? '')]);
                if ($r === true) $n++;
            }
            $_SESSION['save_msg'] = '已将 ' . (int)$n . ' 条订单更新为「' . shop_status_label($act) . '」';
            $_SESSION['save_msg_type'] = 'success';
        } else {
            $_SESSION['save_msg'] = '未知的批量操作';
            $_SESSION['save_msg_type'] = 'error';
        }
        header('Location: ?sub=orders' . $back);
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] == 'shop_push_test') {
        $res = shop_push_send_test((string)($_POST['channel'] ?? ''));
        $_SESSION['save_msg'] = $res['msg'];
        $_SESSION['save_msg_type'] = !empty($res['ok']) ? 'success' : 'error';
        header('Location: ?sub=push');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] == 'shop_push_log_clear') {
    $n = shop_push_log_clear();
    $_SESSION['save_msg'] = '已清理 ' . $n . ' 条推送记录';
    $_SESSION['save_msg_type'] = 'success';
    header('Location: ?sub=push');
    exit;
}
if (isset($_POST['action']) && $_POST['action'] == 'shop_push_process') {
        $n = shop_push_process_queue(20, 25);
        $_SESSION['save_msg'] = '已处理重试队列，成功发送 ' . (int)$n . ' 条';
        $_SESSION['save_msg_type'] = 'success';
        header('Location: ?sub=push');
        exit;
    }

    if (isset($_GET['shop_order_set']) && $currentModule == 'shop') {
        if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_GET['csrf'] ?? ''))) {
            $_SESSION['save_msg'] = '操作失败：安全校验未通过，请刷新页面后重试';
            $_SESSION['save_msg_type'] = 'error';
            header('Location: ?sub=orders');
            exit;
        }
        $no   = (string)$_GET['shop_order_set'];
        $to   = (string)($_GET['to'] ?? '');
        $back = '&status=' . urlencode((string)($_GET['back'] ?? 'all'));
        $r = shop_order_set_status($no, $to, ['verified_by' => (string)($_SESSION['username'] ?? '')]);
        if ($r === true) {
            $_SESSION['save_msg'] = '订单 ' . $no . ' 已更新为「' . shop_status_label($to) . '」';
            $_SESSION['save_msg_type'] = 'success';
        } else {
            $_SESSION['save_msg'] = is_string($r) && $r !== '' ? $r : '操作失败';
            $_SESSION['save_msg_type'] = 'error';
        }
        header('Location: ?sub=orders' . $back);
        exit;
    }

    if (isset($_GET['move_shop_product']) && $currentModule == 'shop') {
        if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_GET['csrf'] ?? ''))) {
            $_SESSION['save_msg'] = '操作失败：安全校验未通过，请刷新页面后重试';
            $_SESSION['save_msg_type'] = 'error';
            header('Location: ?sub=products');
            exit;
        }
        $mid = (string)$_GET['move_shop_product'];
        $dir = ((string)($_GET['dir'] ?? 'up') === 'down') ? 'down' : 'up';
        $cfg = shop_config(true);
        $idx = -1;
        foreach ($cfg['products'] as $i => $p) {
            if ((string)($p['id'] ?? '') === $mid) { $idx = $i; break; }
        }
        if ($idx >= 0) {
            $swap = ($dir === 'up') ? $idx - 1 : $idx + 1;
            if ($swap >= 0 && $swap < count($cfg['products'])) {
                $tmp = $cfg['products'][$swap];
                $cfg['products'][$swap] = $cfg['products'][$idx];
                $cfg['products'][$idx] = $tmp;
                shop_config_save($cfg);
                $_SESSION['save_msg'] = '顺序已调整';
                $_SESSION['save_msg_type'] = 'success';
            }
        }
        header('Location: ?sub=products');
        exit;
    }

    if (isset($_GET['del_shop_product']) && $currentModule == 'shop') {
        $cfg = shop_config(true);
        $did = (string)$_GET['del_shop_product'];
        $kept = [];
        $hit  = false;
        foreach ($cfg['products'] as $p) {
            if ((string)($p['id'] ?? '') === $did) { $hit = true; continue; }
            $kept[] = $p;
        }
        if ($hit) {
            $cfg['products'] = $kept;
            shop_config_save($cfg);
        }
        $_SESSION['save_msg'] = $hit ? '商品已删除' : '商品不存在';
        $_SESSION['save_msg_type'] = $hit ? 'success' : 'error';
        header('Location: ?sub=products');
        exit;
    }

    if (isset($_GET['del_shop_category']) && $currentModule == 'shop') {
        $cfg = shop_config(true);
        $dkey = (string)$_GET['del_shop_category'];
        $kept = [];
        $hit  = false;
        foreach ((array)$cfg['categories'] as $c) {
            if ((string)($c['key'] ?? '') === $dkey) { $hit = true; continue; }
            $kept[] = $c;
        }
        if ($hit) {
            $cfg['categories'] = array_values($kept);
            shop_config_save($cfg);
        }
        $_SESSION['save_msg'] = $hit ? '分类已删除（已引用该分类的商品不会被改动）' : '分类不存在';
        $_SESSION['save_msg_type'] = $hit ? 'success' : 'error';
        header('Location: ?sub=categories');
        exit;
    }

    if (isset($_GET['del_shop_order']) && $currentModule == 'shop') {
        $no = (string)$_GET['del_shop_order'];
        $ok = shop_order_delete($no);
        $_SESSION['save_msg'] = $ok ? ('订单 ' . $no . ' 已删除') : '订单不存在或删除失败';
        $_SESSION['save_msg_type'] = $ok ? 'success' : 'error';
        header('Location: ?sub=orders&status=' . urlencode((string)($_GET['back'] ?? 'all')));
        exit;
    }

    if (isset($_GET['export']) && $_GET['export'] === 'csv' && $currentModule == 'shop') {
        $rows = shop_order_all((string)($_GET['status'] ?? ''), (string)($_GET['q'] ?? ''));
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="shop-orders-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        if ($out) {
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['订单号', '付款备注码', '状态', '称呼', '微信', '电话', '商品', '金额', '服务方式',
                           '预约时间', '客户备注', '下单时间', '标记付款时间', '核实到账时间', '操作人', '管理员备注']);
            foreach ($rows as $o) {
                fputcsv($out, [
                    $o['order_no'], $o['pay_code'], $o['status_label'], $o['customer_name'], $o['wechat'],
                    $o['phone'], $o['items_text'], shop_price($o['amount']), $o['mode_label'], $o['book_time'],
                    $o['remark'], $o['created_at'], $o['pay_marked_at'], $o['verified_at'], $o['verified_by'], $o['admin_note'],
                ]);
            }
            fclose($out);
        }
        exit;
    }

if (isset($_GET['audit_clear']) && hash_equals((string)$_SESSION['csrf_token'], (string)($_GET['csrf'] ?? ''))) {
    $days = (int)$_GET['audit_clear'];
    $del  = shop_audit_clear($days);
    shop_audit_add('shop_audit_clear', '', $days <= 0 ? '全部清空' : ('清空 ' . $days . ' 天前'), '删除 ' . $del . ' 条');
    $_SESSION['save_msg'] = '已清理 ' . $del . ' 条日志';
    $_SESSION['save_msg_type'] = 'success';
    header('Location: ?sub=log');
    exit;
}

$shopData = shop_config(true);
$shopSub  = (string)($_GET['sub'] ?? 'orders');
if (($_GET['sub'] ?? '') === 'audit') { header('Location: ?sub=log'); exit; }
if (!in_array($shopSub, ['orders', 'products', 'categories', 'page', 'pay', 'push', 'security', 'account', 'audit', 'log', 'contact', 'guide'], true)) {
    $shopSub = 'orders';
}
$currentSub = $shopSub;

$shopUrl = function ($q) {
    $q = (string)$q;
    
    
    $q = str_replace(['?&', '&&'], ['?', '&'], $q);
    return $q === '' ? '?' : $q;
};

$saveMsg     = (string)($_SESSION['save_msg'] ?? '');
$saveMsgType = (string)($_SESSION['save_msg_type'] ?? 'success');
unset($_SESSION['save_msg'], $_SESSION['save_msg_type']);

$pendingCount = 0;
if (shop_db_available()) {
    $st = shop_order_stats();
    $pendingCount = (int)($st['awaiting_verify'] ?? 0) + (int)($st['pending_payment'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>在线商城 · 管理后台</title>
<script>
(function(){try{
  var t = localStorage.getItem('shopAdminTheme');
  if (t) document.documentElement.setAttribute('data-theme', t);
}catch(e){}})();
</script>
<style>

:root {
    --bg: #f4f6fb;
    --surface: #ffffff;
    --surface2: #eef2f7;
    --surface3: #e1e7f0;
    --text: #0f172a;
    --text2: #5c6878;
    --text3: #7d8794;
    --border: #e5e9f2;
    --border-strong: #cbd5e1;
    --primary: #2563eb;
    --primary-hover: #1d4ed8;
    --primary-soft: rgba(37,99,235,.08);
    --sidebar-bg1: #0d1526;
    --sidebar-bg2: #111c33;
    --ok: #059669;
    --ok-soft: #dcfce7;
    --danger: #dc2626;
    --danger-soft: #fee2e2;
    --warn: #d97706;
    --warn-soft: #fef3c7;
    --info-soft: #eff6ff;
    --info-border: #bfdbfe;
    --info-text: #1d4ed8;
    --shadow-card: 0 1px 2px rgba(15,23,42,.05), 0 10px 28px -16px rgba(15,23,42,.14);
    --shadow-hover: 0 2px 4px rgba(15,23,42,.06), 0 14px 34px -16px rgba(15,23,42,.20);
    --flash-color: rgba(245,158,11,0.55);
    --flash-border: #f59e0b;
}

[data-theme="dark"] {
    --bg: #0b1220;
    --surface: #121b2e;
    --surface2: #0f1830;
    --surface3: rgba(148,163,184,.10);
    --text: #e2e8f0;
    --text2: #a3aec0;
    --text3: #8592a6;
    --border: rgba(148,163,184,.16);
    --border-strong: rgba(148,163,184,.32);
    --primary: #60a5fa;
    --primary-hover: #93c5fd;
    --primary-soft: rgba(96,165,250,.14);
    --sidebar-bg1: #0a1120;
    --sidebar-bg2: #0e1830;
    --ok: #34d399;
    --ok-soft: rgba(52,211,153,.14);
    --danger: #f87171;
    --danger-soft: rgba(248,113,113,.14);
    --warn: #fbbf24;
    --warn-soft: rgba(251,191,36,.12);
    --info-soft: rgba(96,165,250,.10);
    --info-border: rgba(96,165,250,.25);
    --info-text: #93c5fd;
    --shadow-card: 0 1px 2px rgba(0,0,0,.35), 0 12px 32px -16px rgba(0,0,0,.5);
    --shadow-hover: 0 2px 6px rgba(0,0,0,.4), 0 16px 38px -16px rgba(0,0,0,.55);
    --flash-color: rgba(251,191,36,0.6);
    --flash-border: #fbbf24;
}

[data-theme="dark"] .btn-success,
[data-theme="dark"] .btn-danger,
[data-theme="dark"] .btn-warning {
    color: #08111f;
}

@keyframes flashHighlight {
    0%   { outline-color: transparent; border-color: var(--border); }
    12%  { outline-color: var(--flash-color); border-color: var(--flash-border); }
    35%  { outline-color: var(--flash-color); border-color: var(--flash-border); }
    100% { outline-color: transparent; border-color: var(--border); }
}
.flash-highlight {
    outline: 4px solid transparent;
    outline-offset: 0;
    animation: flashHighlight 1.8s ease-out;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
    background: var(--bg);
    min-height: 100vh;
    color: var(--text);
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 260px;
    height: 100vh;
    background: linear-gradient(180deg, var(--sidebar-bg1) 0%, var(--sidebar-bg2) 100%);
    color: #fff;
    box-shadow: 4px 0 24px rgba(0,0,0,0.15);
    z-index: 1000;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s ease;
}
.sidebar.collapsed {
    transform: translateX(-100%);
}
.sidebar::-webkit-scrollbar {
    width: 6px;
}
.sidebar::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.2);
    border-radius: 3px;
}

.sidebar .logo {
    padding: 0 16px;
    background: rgba(255,255,255,0.05);
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: flex-start;
    height: 60px;
    min-height: 60px;
    box-sizing: border-box;
}
.sidebar .logo h2 {
    font-size: 16.5px;
    font-weight: 700;
    margin: 0 0 3px 0;
    color: #60a5fa;
    line-height: 1.25;
    letter-spacing: .2px;
}
.sidebar .logo p {
    font-size: 10.5px;
    color: #7c8ba1;
    letter-spacing: 0.3px;
    margin: 0;
    line-height: 1.3;
}

.sidebar .nav-menu {
    list-style: none;
    padding: 8px;
    flex: 1;
}
.sidebar .nav-menu li {
    margin-bottom: 1px;
}

.sidebar .nav-group-title {
    padding: 10px 12px 4px;
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.sidebar .nav-group-title::before {
    content: '';
    width: 2px;
    height: 8px;
    background: #3b82f6;
    border-radius: 1px;
}

.sidebar .nav-menu a.nav-level-2 {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    padding: 8px 10px;
    color: #cbd5e1;
    text-decoration: none;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.15s ease;
    position: relative;
    cursor: pointer;
}
.sidebar .nav-menu a.nav-level-2:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.08);
}
.sidebar .nav-menu a.nav-level-2 .nav-text {
    display: flex;
    align-items: center;
    gap: 0;
}
.sidebar .nav-menu a.nav-level-2 .arrow {
    font-size: 10px;
    transition: transform 0.15s ease;
    opacity: 0.5;
}
.sidebar .nav-menu a.nav-level-2.expanded .arrow {
    transform: rotate(90deg);
    opacity: 1;
}
.sidebar .nav-menu a.nav-level-2.active {
    color: #fff;
    background: rgba(59, 130, 246, 0.15);
}

.sidebar .nav-level-3-container {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.2s ease;
    margin-left: 12px;
    border-left: 1px solid rgba(255, 255, 255, 0.06);
    padding-left: 8px;
}
.sidebar .nav-level-3-container.expanded {
    max-height: 500px;
}

.sidebar .nav-menu a.nav-level-3 {
    display: block;
    padding: 7px 12px;
    color: #94a3b8;
    text-decoration: none;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 400;
    transition: all 0.15s ease;
    margin: 2px 0;
}
.sidebar .nav-menu a.nav-level-3:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.06);
}
.sidebar .nav-menu a.nav-level-3.active {
    color: #60a5fa;
    background: rgba(59, 130, 246, 0.1);
    font-weight: 500;
}

.sidebar .nav-menu a.nav-level-2.no-children {
    cursor: pointer;
}

.sidebar .version-info {
    padding: 14px 18px;
    border-top: 1px solid rgba(255,255,255,0.1);
    text-align: center;
    background: rgba(0,0,0,0.2);
}
.sidebar .version-info span {
    display: block;
    color: #64748b;
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 0.3px;
}
.sidebar .version-info .dev {
    margin-top: 3px;
    color: #475569;
    font-size: 10.5px;
    font-weight: 500;
    letter-spacing: 0.2px;
}

.header {
    position: fixed;
    top: 0;
    left: 260px;
    right: 0;
    height: 60px;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    z-index: 100;
    transition: left 0.2s ease;
}
body.sidebar-collapsed .header {
    left: 0;
}
.header .header-content {
    display: flex;
    align-items: center;
    gap: 10px;
}
.header h1 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text);
}
.header p {
    font-size: 12px;
    color: var(--text2);
}
.header .user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}
.header .user-info span {
    color: var(--text2);
    font-size: 11px;
    padding: 4px 10px;
    background: var(--surface3);
    border-radius: 12px;
    font-weight: 500;
}
.header .logout {
    color: #fff;
    text-decoration: none;
    padding: 6px 14px;
    background: var(--primary);
    border-radius: 5px;
    font-size: 11px;
    font-weight: 500;
    transition: all 0.15s ease;
}
.header .logout:hover {
    background: var(--primary-hover);
}

.container {
    margin-left: 260px;
    padding: 76px 20px 20px;
    max-width: calc(100vw - 260px);
    min-height: 100vh;
    transition: margin-left 0.2s ease, max-width 0.2s ease;
}

body.sidebar-collapsed .container {
    margin-left: 0;
    max-width: 100vw;
}

.sidebar-toggle {
    position: fixed;
    top: 16px;
    left: 260px;
    width: 28px;
    height: 28px;
    background: var(--primary);
    border: 2px solid var(--surface);
    border-radius: 50%;
    color: #fff;
    font-size: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1001;
    transition: all 0.2s ease;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    transform: translateX(-50%);
}

.sidebar-toggle:hover {
    background: var(--primary-hover);
}

body.sidebar-collapsed .sidebar-toggle {
    left: 14px;
    transform: none;
}

body.sidebar-collapsed .header { padding-left: 58px; }
body.sidebar-collapsed .header h1 { padding-left: 0; }

body.sidebar-collapsed .container {
    padding-left: 44px;
}

body:not(.sidebar-collapsed) .header h1 {
    padding-left: 0;
}

.sidebar-toggle .toggle-icon {
    font-size: 11px;
    line-height: 1;
    transform: none;
}
.sidebar-toggle .toggle-icon .ic-open   { display: inline; }
.sidebar-toggle .toggle-icon .ic-closed { display: none; }
body.sidebar-collapsed .sidebar-toggle .toggle-icon .ic-open   { display: none; }
body.sidebar-collapsed .sidebar-toggle .toggle-icon .ic-closed { display: inline; }

.card {
    background: var(--surface);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: var(--shadow-card);
    border: 1px solid var(--border);
    transition: box-shadow 0.15s ease;
}
.card:hover {
    box-shadow: var(--shadow-hover);
}
.card h2 {
    font-size: 17px;
    color: var(--text);
    margin-bottom: 18px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}
.card h2::before {
    content: '';
    width: 3px;
    height: 16px;
    background: var(--primary);
    border-radius: 2px;
}
.card h3 {
    font-size: 15px;
    color: var(--text);
    margin: 20px 0 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}

.form-group {
    margin-bottom: 16px;
}
.form-group label {
    display: block;
    font-size: 12px;
    color: var(--text2);
    margin-bottom: 5px;
    font-weight: 500;
}
.form-group label .hint {
    color: var(--text3);
    font-weight: normal;
    font-size: 11px;
    margin-left: 4px;
}
.form-group .required {
    color: var(--danger);
    font-weight: 600;
    margin-left: 2px;
}
.form-group input,
.form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 13px;
    font-family: inherit;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    background: var(--surface);
    color: var(--text);
}
.form-group input:hover,
.form-group textarea:hover {
    border-color: var(--border-strong);
}
.form-group input:focus,
.form-group textarea:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-soft);
    background: var(--surface);
}
.form-group textarea {
    min-height: 90px;
    resize: vertical;
    line-height: 1.5;
}
.form-group select {
    max-width: 280px;
    padding: 8px 28px 8px 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 13px;
    font-family: inherit;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    background: var(--surface);
    color: var(--text);
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%2394a3b8' d='M5 6L0 0h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 10px 6px;
    cursor: pointer;
}
.form-group select:hover {
    border-color: var(--border-strong);
}
.form-group select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-soft);
    background-color: var(--surface);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath fill='%233b82f6' d='M5 6L0 0h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 10px 6px;
}
.sw-select.open .sw-select-trigger { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
.sw-select.open .sw-select-arrow { transform: rotate(180deg); }
.sw-select.open .sw-select-menu { display: block; }
.sw-select-option.selected { background: var(--primary-soft); color: var(--primary); font-weight: 500; }
.sw-select-option.selected .sw-opt-tag { background: transparent; color: var(--primary); }
.form-actions {
    margin-top: 16px;
}
.category-header.expanded {
    background: var(--primary-soft);
    border-color: var(--primary);
}

.edit-panel {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
}

.btn {
    padding: 7px 16px;
    border: none;
    border-radius: 5px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.15s ease;
    font-family: inherit;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    text-decoration: none;
}
.btn-primary {
    background: var(--primary);
    color: #fff;
}
.btn-primary:hover {
    background: var(--primary-hover);
}
.btn-secondary {
    background: var(--surface3);
    color: var(--text);
    border: 1px solid var(--border);
    font-weight: 600;
}
.btn-secondary:hover {
    background: var(--border);
    color: var(--text);
}
.btn-danger {
    background: var(--danger);
    color: #fff;
}
.btn-danger:hover {
    filter: brightness(.9);
}
.btn-success {
    background: var(--ok);
    color: #fff;
}
.btn-success:hover {
    filter: brightness(.92);
}
.btn-sm {
    padding: 6px 12px;
    font-size: 12.5px;
}
.tips--warn  { background: var(--warn-soft); border-color: var(--warn);        color: var(--warn); }
.tips--muted { background: var(--surface2);  border-color: var(--border);      color: var(--text2); }

.tips {
    background: var(--info-soft);
    border: 1px solid var(--info-border);
    border-radius: 6px;
    padding: 10px 14px;
    margin-bottom: 16px;
    font-size: 12px;
    color: var(--info-text);
    line-height: 1.5;
}
.tips strong {
    color: var(--info-text);
    font-weight: 600;
}
.item-actions .edit {
    background: var(--primary);
    color: #fff;
}
.item-actions .edit:hover {
    background: var(--primary-hover);
}
.item-actions .del {
    background: var(--danger-soft);
    color: var(--danger);
}
.item-actions .del:hover {
    background: var(--danger);
    color: #fff;
}

.empty {
    text-align: center;
    padding: 50px 20px;
    color: var(--text3);
    font-size: 14px;
    background: var(--surface2);
    border-radius: 10px;
    border: 2px dashed var(--border);
}

.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
    line-height: 1.65;
}
.alert-error {
    background: var(--danger-soft);
    color: var(--danger);
    border: 1px solid transparent;
}
.status.published {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 500;
    background: var(--ok-soft);
    color: var(--ok);
}
.status.draft {
    display: inline-block;
    padding: 1px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 500;
    background: var(--surface3);
    color: var(--text2);
}

.dynamic-row {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 8px;
    padding: 8px 10px;
    background: var(--surface2);
    border-radius: 6px;
    border: 1px solid var(--border);
    transition: all 0.15s ease;
}
.dynamic-row:hover {
    background: var(--surface3);
    border-color: var(--border-strong);
}
.dynamic-row input {
    flex: 1;
    padding: 7px 10px;
    border: 1px solid var(--border);
    border-radius: 5px;
    font-size: 12px;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    background: var(--surface);
    color: var(--text);
}
.dynamic-row input:hover {
    border-color: var(--border-strong);
}
.dynamic-row input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-soft);
}
.dynamic-row .btn-danger {
    padding: 6px 10px;
}

.table-wrapper {
    overflow-x: auto;
    margin-top: 10px;
    border-radius: 6px;
    border: 1px solid var(--border);
}
table {
    width: 100%;
    border-collapse: collapse;
    background: var(--surface);
}
th, td {
    padding: 11px 13px;
    text-align: left;
    vertical-align: middle;
    border-bottom: 1px solid var(--border);
}
th {
    background: var(--surface2);
    color: var(--text2);
    font-weight: 600;
    font-size: 12.5px;
}
td {
    font-size: 13px;
    color: var(--text);
    line-height: 1.5;
}

tbody tr:last-child td { border-bottom: none; }
tbody tr:hover {
    background: var(--surface2);
}

@media (max-width: 768px) {
    .header { padding: 18px 20px; }
    .header h1 { font-size: 20px; }
    .header .logout { padding: 8px 16px; font-size: 13px; }
    .container { margin-left: 0; max-width: 100vw; padding: 76px 12px 16px; }
    body.sidebar-collapsed .container { padding-left: 12px; }
    .card { padding: 20px; }
    .card h2 { font-size: 18px; }
    .item-row { flex-direction: column; gap: 12px; align-items: flex-start; }
    .item-actions { width: 100%; }
    .item-actions a { flex: 1; text-align: center; }
    .dynamic-row { flex-direction: column; }
    .dynamic-row input { width: 100%; }

    .sidebar { transform: translateX(-100%); }
    body:not(.sidebar-collapsed) .sidebar { transform: translateX(0); }
    .header { left: 0; }
    .sidebar-toggle { left: 12px; transform: none; }
}

.item-info, .item-title, .item-meta,
td, th,
.tips, .alert, .empty,
.card h2, .card h3, .quick-add-card h3,
.category-header, .source-item,
#sw-editing-banner, #sw-check-progress,
#list-search-result td,
#confirm-modal-text, #message-modal-text,
.header .user-info span,
code {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.list-search-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 13px;
    outline: none;
    background: var(--surface);
    color: var(--text);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.list-search-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-soft);
}
.form-group .sw-select { width: 100%; }

[data-theme="dark"] #message-modal-content,
[data-theme="dark"] #confirm-modal-content { background: var(--surface) !important; }
[data-theme="dark"] #message-modal-title,
[data-theme="dark"] #confirm-modal-title { color: var(--text) !important; }
[data-theme="dark"] #message-modal-text,
[data-theme="dark"] #confirm-modal-text { color: var(--text2) !important; }
[data-theme="dark"] #confirm-modal-cancel { background: var(--surface3) !important; color: var(--text2) !important; border-color: var(--border) !important; }
[data-theme="dark"] .card h4[style] { color: #93c5fd !important; }
[data-theme="dark"] .tips { background: var(--surface2) !important; border-color: var(--border) !important; color: var(--text2) !important; }
[data-theme="dark"] .tips strong,
[data-theme="dark"] .tips b { color: var(--text) !important; }
[data-theme="dark"] .alert[style] { background: var(--surface2) !important; color: var(--text) !important; }
[data-theme="dark"] .alert[style] strong { color: var(--danger) !important; }
[data-theme="dark"] div[style*="fef3c7"],
[data-theme="dark"] div[style*="ecfdf5"],
[data-theme="dark"] div[style*="eff6ff"],
[data-theme="dark"] div[style*="f9fafb"],
[data-theme="dark"] div[style*="f3f4f6"],
[data-theme="dark"] div[style*="f8fafc"] { background: var(--surface2) !important; border-color: var(--border) !important; }
[data-theme="dark"] div[style*="fef3c7"] strong,
[data-theme="dark"] div[style*="fef3c7"] b { color: var(--warn) !important; }
[data-theme="dark"] span[style*="e0e7ff"] { background: rgba(99,102,241,.20) !important; color: #a5b4fc !important; }
[data-theme="dark"] span[style*="fef3c7"] { background: var(--warn-soft) !important; color: var(--warn) !important; }
[data-theme="dark"] span[style*="dcfce7"] { background: var(--ok-soft) !important; color: var(--ok) !important; }
[data-theme="dark"] span[style*="fee2e2"] { background: var(--danger-soft) !important; color: var(--danger) !important; }
[data-theme="dark"] span[style*="f1f5f9"] { background: var(--surface3) !important; color: var(--text2) !important; }
[data-theme="dark"] span[style*="color: #dc2626"] { color: var(--danger) !important; }
[data-theme="dark"] span[style*="color:#dc2626"] { color: var(--danger) !important; }

html {
    scroll-behavior: smooth;
    scroll-padding-top: 80px;
}

.card[id],
[id^="edit-"],
form[id],
h3[id],
.quick-add-card,
[id^="software-list-"],
[id^="edit-item-"] {
    scroll-margin-top: 80px;
}

@media (prefers-reduced-motion: reduce) {
    html { scroll-behavior: auto; }
}

select {
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 8px 10px;
    font-size: 13px;
    font-family: inherit;
    outline: none;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
select:hover { border-color: var(--border-strong); }
select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
select:disabled { background: var(--surface2); color: var(--text3); cursor: not-allowed; }
option { background: var(--surface); color: var(--text); }
option:checked { background: var(--primary-soft); color: var(--primary); }

[data-theme="dark"] div[style*="f8fafc"] h4[style],
[data-theme="dark"] div[style*="ecfdf5"] h4[style],
[data-theme="dark"] div[style*="eff6ff"] h4[style] { color: #93c5fd !important; }
[data-theme="dark"] div[style*="f8fafc"] p[style],
[data-theme="dark"] div[style*="ecfdf5"] p[style],
[data-theme="dark"] div[style*="eff6ff"] p[style] { color: var(--text2) !important; }
[data-theme="dark"] div[style*="f8fafc"] span[style*="color:#"],
[data-theme="dark"] div[style*="ecfdf5"] span[style*="color:#"],
[data-theme="dark"] div[style*="eff6ff"] span[style*="color:#"] { color: #93c5fd !important; }

[data-theme="dark"] div[style*="background:#fff"],
[data-theme="dark"] div[style*="background: #fff"],
[data-theme="dark"] div[style*="background:white"],
[data-theme="dark"] div[style*="background: white"] { background: var(--surface) !important; }

[data-theme="dark"] span[style*="color:#ef4444"],
[data-theme="dark"] span[style*="color: #ef4444"] { color: var(--danger) !important; }
[data-theme="dark"] [style*="color:#64748b"],
[data-theme="dark"] [style*="color: #64748b"] { color: var(--text2) !important; }
[data-theme="dark"] [style*="color:#6b7280"],
[data-theme="dark"] [style*="color: #6b7280"] { color: var(--text3) !important; }
[data-theme="dark"] [style*="color:#9ca3af"],
[data-theme="dark"] [style*="color: #9ca3af"] { color: var(--text3) !important; }
[data-theme="dark"] [style*="color:#4b5563"],
[data-theme="dark"] [style*="color: #4b5563"] { color: var(--text2) !important; }
</style>
<style>
@keyframes messageModalIn {
    0% { opacity: 0; transform: translateY(-10px) scale(0.98); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes messageModalOut {
    0% { opacity: 1; transform: translateY(0) scale(1); }
    100% { opacity: 0; transform: translateY(-8px) scale(0.98); }
}
.message-modal-out {
    animation: messageModalOut 0.15s ease-out forwards;
}

#admin-toast-wrap {
    position: fixed;
    top: 72px;
    right: 16px;
    z-index: 10002;
    display: flex;
    flex-direction: column;
    gap: 9px;
    align-items: flex-end;
    pointer-events: none;
    max-width: calc(100vw - 20px);
}
.admin-toast {
    padding: 13px 22px;
    border-radius: 12px;
    font-size: 14.5px;
    font-weight: 700;
    color: #fff;
    box-shadow: 0 12px 34px rgba(0, 0, 0, .26);
    animation: adminToastIn .3s ease-out;
    line-height: 1.6;
    white-space: pre;
    width: max-content;
    max-width: none;
    text-align: left;
}
.admin-toast.ok   { background: linear-gradient(135deg, #10b981, #059669); }
.admin-toast.err  { background: linear-gradient(135deg, #ef4444, #dc2626); }
.admin-toast.info { background: linear-gradient(135deg, #3b82f6, #2563eb); }

@keyframes adminToastIn {
    from { opacity: 0; transform: translateX(12px); }
    to   { opacity: 1; transform: none; }
}
@media (max-width: 640px) {
    #admin-toast-wrap { top: 68px; left: 10px; right: 10px; max-width: none; align-items: stretch; }
    .admin-toast { text-align: center; padding: 12px 16px; }
}
</style>
<style>
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
</style>
<style>

.header .logo { display: flex; align-items: center; gap: 9px; }
.header .logo h1 { font-size: 15.5px; font-weight: 700; margin: 0; }
.header .logo .badge {
  font-size: 11.5px; font-weight: 700; padding: 2px 8px; border-radius: 999px;
  background: var(--primary-soft); color: var(--primary); border: 1px solid var(--primary);
}
.header .user-info { margin-left: auto; display: flex; align-items: center; gap: 10px; }
.header .user-info .who { font-size: 13px; color: var(--text2); }

.container { padding: 74px 20px 40px; }

body.sidebar-collapsed .container { margin-left: 0 !important; }
body.sidebar-collapsed .header   { left: 0 !important; }

*{scrollbar-width:thin;scrollbar-color:var(--border-strong,#94a3b8) transparent;}
::-webkit-scrollbar{width:12px;height:12px;}
::-webkit-scrollbar-track,::-webkit-scrollbar-corner{background:transparent;}
::-webkit-scrollbar-thumb{background:var(--border-strong,#94a3b8);border:3px solid transparent;background-clip:content-box;border-radius:99px;}
::-webkit-scrollbar-thumb:hover{background:var(--text3,#64748b);background-clip:content-box;}
.subnav-item.active{background:var(--primary);color:#fff;border-color:var(--primary);font-weight:600;}

.header .hbtn {
  font: inherit; font-size: 12.5px; font-weight: 600; cursor: pointer; white-space: nowrap;
  padding: 6px 12px; border-radius: 8px; border: 1px solid var(--border);
  background: var(--surface2); color: var(--text2); transition: .15s;
}
.header .hbtn:hover { color: var(--primary); border-color: var(--primary); }
.header .hbtn.danger:hover { color: var(--danger); border-color: var(--danger); }

@media (max-width: 640px) {
  .container { padding: 108px 12px 32px; }
  .header { height: auto !important; min-height: 60px; padding: 8px 12px; flex-wrap: wrap; }
  .header .user-info { margin-left: 0; width: 100%; margin-top: 6px; }
}
</style>
</head>
<body>

<?php

$__shopTabs = [
    'orders'     => ['📋', '订单管理'],
    'products'   => ['📦', '商品管理'],
    'categories' => ['🗂', '分类管理'],
    'page'       => ['✏️', '页面文案'],
    'pay'        => ['💳', '收款与表单'],
    'push'       => ['🔔', '消息推送'],
    'security'   => ['🛡', '风控与清理'],
    'log'        => ['🧾', '操作日志'],
];
?>
<button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" title="展开 / 折叠侧边栏">
  <span class="toggle-icon"><span class="ic-open">◀</span><span class="ic-closed">▶</span></span>
</button>

<div class="sidebar" id="sidebar">
  <div class="logo">
    <h2>在线商城</h2>
    <p>独立站管理后台</p>
  </div>
  <ul class="nav-menu">
    <li class="nav-group-title">商城管理</li>
    <?php foreach ($__shopTabs as $__k => $__v): ?>
      <a class="nav-level-2 no-children <?php echo $shopSub === $__k ? 'active' : ''; ?>"
         href="?sub=<?php echo urlencode($__k); ?>">
        <span class="nav-text"><?php echo $__v[0]; ?> <?php echo $__v[1]; ?></span>
      </a>
    <?php endforeach; ?>

    <li class="nav-group-title">系统</li>
    <a class="nav-level-2 no-children <?php echo $shopSub === 'guide' ? 'active' : ''; ?>" href="?sub=guide">
      <span class="nav-text">📖 使用说明</span>
    </a>
    <a class="nav-level-2 no-children <?php echo $shopSub === 'contact' ? 'active' : ''; ?>" href="?sub=contact">
      <span class="nav-text">📇 联系方式页</span>
    </a>
    <a class="nav-level-2 no-children <?php echo $shopSub === 'account' ? 'active' : ''; ?>" href="?sub=account">
      <span class="nav-text">🔑 账号设置</span>
    </a>
    <a class="nav-level-2 no-children" href="index.php" target="_blank">
      <span class="nav-text">👁 查看前台</span>
    </a>
  </ul>
  <div class="version-info">
    <span>独立版 v1.0</span>
    <span class="dev">开源版 · MIT 协议</span>
  </div>
</div>

<header class="header">
  <div class="logo">
    <span style="font-size:19px;">🛒</span>
    <h1>在线商城</h1>
    <span class="badge">独立站</span>
  </div>
  <div class="user-info">
    <a class="hbtn" href="index.php" target="_blank">👁 查看前台</a>
    <button type="button" class="hbtn" onclick="shopAdminTheme()" id="themeBtn">🌙 深色</button>
    <span class="who">👤 <?php echo shop_admin_h((string)($_SESSION['shop_admin'] ?? '')); ?></span>
    <a class="hbtn danger" href="?logout=1" onclick="return shopLogoutConfirm();">退出登录</a>
  </div>
</header>
<div id="message-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;justify-content:center;align-items:center;padding:20px;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);" onclick="closeMessageModal()">
    <div id="message-modal-content" style="background:#ffffff;border-radius:12px;max-width:360px;width:100%;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.25);animation:messageModalIn 0.25s ease-out;position:relative;" onclick="event.stopPropagation()">
        <div style="padding:28px 20px 20px 20px;text-align:center;">
            <div id="message-modal-icon" style="width:56px;height:56px;margin:0 auto 16px auto;border-radius:50%;display:flex;justify-content:center;align-items:center;font-size:24px;font-weight:700;">
            </div>
            <h3 id="message-modal-title" style="margin:0 0 10px 0;font-size:16px;font-weight:600;color:#1e293b;">提示</h3>
            <p id="message-modal-text" style="margin:0 0 20px 0;color:#64748b;font-size:13px;line-height:1.5;">消息内容</p>
            <button id="message-modal-btn" onclick="closeMessageModal()" class="btn btn-primary" style="width:100%;height:36px;">
                确定
            </button>
        </div>
    </div>
</div>

<div id="admin-toast-wrap"></div>

<style>
@keyframes messageModalIn {
    0% { opacity: 0; transform: translateY(-10px) scale(0.98); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes messageModalOut {
    0% { opacity: 1; transform: translateY(0) scale(1); }
    100% { opacity: 0; transform: translateY(-8px) scale(0.98); }
}
.message-modal-out {
    animation: messageModalOut 0.15s ease-out forwards;
}

#admin-toast-wrap {
    position: fixed;
    top: 72px;
    right: 16px;
    z-index: 10002;
    display: flex;
    flex-direction: column;
    gap: 9px;
    align-items: flex-end;
    pointer-events: none;
    max-width: calc(100vw - 20px);
}
.admin-toast {
    padding: 13px 22px;
    border-radius: 12px;
    font-size: 14.5px;
    font-weight: 700;
    color: #fff;
    box-shadow: 0 12px 34px rgba(0, 0, 0, .26);
    animation: adminToastIn .3s ease-out;
    line-height: 1.6;
    white-space: pre;
    width: max-content;
    max-width: none;
    text-align: left;
}
.admin-toast.ok   { background: linear-gradient(135deg, #10b981, #059669); }
.admin-toast.err  { background: linear-gradient(135deg, #ef4444, #dc2626); }
.admin-toast.info { background: linear-gradient(135deg, #3b82f6, #2563eb); }

@keyframes adminToastIn {
    from { opacity: 0; transform: translateX(12px); }
    to   { opacity: 1; transform: none; }
}
@media (max-width: 640px) {
    #admin-toast-wrap { top: 68px; left: 10px; right: 10px; max-width: none; align-items: stretch; }
    .admin-toast { text-align: center; padding: 12px 16px; }
}

/* ===== 全后台控件统一标准 =====
   输入框：高 38px / 圆角 9px / 字号 13.5px / 底色 --surface2
   按钮  ：主 13.5px、小 12.5px，圆角 9px                                          */
.form-group input,
.form-group textarea,
.form-group select {
    padding: 9px 13px;
    border-radius: 9px;
    font-size: 13.5px;
    background: var(--surface2);
}
.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    border-color: var(--primary);
    background: var(--surface);
    box-shadow: 0 0 0 3px var(--primary-soft);
}
.form-group select { padding-right: 30px; background-color: var(--surface2); }
.btn {
    padding: 9px 18px;
    border-radius: 9px;
    font-size: 13.5px;
    font-weight: 600;
}
.btn-sm {
    padding: 7px 13px;
    font-size: 12.5px;
}
.checkbox-label,
.checkbox-label input { vertical-align: middle; }
input[type=checkbox],
input[type=radio] { accent-color: var(--primary); }
</style>

<div id="confirm-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10001;justify-content:center;align-items:center;padding:20px;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);" onclick="closeConfirmModal()">
    <div id="confirm-modal-content" style="background:#ffffff;border-radius:12px;max-width:380px;width:100%;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.25);animation:messageModalIn 0.25s ease-out;" onclick="event.stopPropagation()">
        <div style="padding:24px 20px 16px 20px;text-align:center;">
            <div style="width:48px;height:48px;margin:0 auto 12px auto;border-radius:50%;display:flex;justify-content:center;align-items:center;font-size:20px;background:#f59e0b;color:#fff;">!</div>
            <h3 id="confirm-modal-title" style="margin:0 0 6px 0;font-size:16px;font-weight:600;color:#1e293b;">确认操作</h3>
            <p id="confirm-modal-text" style="margin:0;color:#64748b;font-size:13px;line-height:1.5;">确定要执行此操作吗？</p>
        </div>
        <div style="padding:0 20px 20px 20px;display:flex;gap:10px;">
            <button id="confirm-modal-cancel" onclick="closeConfirmModal()" class="btn btn-secondary" style="flex:1;height:36px;">取消</button>
            <button id="confirm-modal-btn" onclick="executeConfirm()" class="btn btn-danger" style="flex:1;height:36px;">确定删除</button>
        </div>
    </div>
</div>

<?php if ($saveMsg !== ''): ?>
<script>window.__SHOP_SAVE_MSG__ = <?php echo json_encode(
    ['m' => $saveMsg, 't' => $saveMsgType === 'error' ? 'err' : ($saveMsgType === 'info' ? 'info' : 'ok')],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<?php endif; ?>

<div class="container">
<?php if (shop_using_default_creds($acct)): ?>
<div class="alert alert-error" style="margin-bottom:16px;">
  🔔 <strong>当前仍在使用默认账号/密码</strong> —— 请尽快到「账号设置」修改账号名或密码。<br>
  若不修改，后台登录页将<strong>一直显示默认账号提示</strong>，且后台存在被他人登录的风险。<a href="?sub=account" style="color:inherit;font-weight:700;text-decoration:underline;">立即去修改 ›</a>
</div>
<?php endif; ?>
<?php if ($shopSub === 'guide'): ?>
  <?php require __DIR__ . '/inc/guide.php'; ?>
<?php elseif ($shopSub === 'contact'): ?>
  <?php require __DIR__ . '/inc/contact_view.php'; ?>
<?php elseif ($shopSub === 'account'): ?>
  <?php  ?>
  <div class="card">
    <h2>账号设置</h2>
    <div class="tips">
      当前登录账号：<b><?php echo shop_admin_h((string)($acct['user'] ?? '')); ?></b><br>
      账号名和密码都能在这里改；<strong>账号名留空表示不改</strong>，新密码留空表示只改账号名。
      任一项改动都需要先验证原密码。
    </div>
    <form method="post" class="edit-panel" style="max-width:460px;">
      <input type="hidden" name="action" value="shop_admin_account">
      <?php echo csrf_field(); ?>
      <div class="form-group">
        <label>登录账号名 <span class="hint">（留空 = 不改；2~32 位字母/数字/下划线/短横线）</span></label>
        <input type="text" name="new_user" autocomplete="username"
               value="<?php echo shop_admin_h((string)($acct['user'] ?? '')); ?>">
      </div>
      <div class="form-group">
        <label>原密码 <span class="required">*</span> <span class="hint">（改账号名或改密码都要填）</span></label>
        <input type="password" name="old_pass" autocomplete="current-password" required>
      </div>
      <div class="form-group">
        <label>新密码 <span class="hint">（留空 = 不改；至少 6 位）</span></label>
        <input type="password" name="new_pass" autocomplete="new-password">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">保存</button>
      </div>
    </form>

    <h3 style="margin-top:26px;">部署信息</h3>
    <div class="tips tips--muted">
      数据目录：<code><?php echo shop_admin_h($DATA_DIR); ?></code><br>
      商品配置：<code>data/shop.json</code>（加密）<br>
      订单数据库：<code>data/shop.db</code>（SQLite）<br>
      账号文件：<code>data/account.php</code><br>
      加密密钥：<code>secret/config.secret.php</code>
    </div>
  </div>
<?php else: ?>
  <?php require __DIR__ . '/inc/admin_view.php'; ?>
<?php endif; ?>
</div>

<script>
var CSRF_TOKEN = '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>';

function withCsrf(url) {
    if (!url) return url;
    var hashIdx = url.indexOf('#');
    var sep = url.indexOf('?') >= 0 ? '&' : '?';
    var token = 'csrf=' + encodeURIComponent(CSRF_TOKEN);
    return hashIdx < 0 ? url + sep + token : url.slice(0, hashIdx) + sep + token + url.slice(hashIdx);
}

function escHtmlText(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function toggleTheme() {
    var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try { localStorage.setItem('adminTheme', next); } catch (e) {}
    updateThemeIcon();
}
function updateThemeIcon() {
    var b = document.getElementById('themeToggle');
    if (b) b.textContent = document.documentElement.getAttribute('data-theme') === 'dark' ? '☀️' : '🌙';
}
document.addEventListener('DOMContentLoaded', updateThemeIcon);

let pendingDeleteUrl = null;
let pendingConfirmCb  = null;

function _openConfirmModal(title, html, okText, isDanger) {
    const modal = document.getElementById('confirm-modal');
    const t = document.getElementById('confirm-modal-title');
    const text = document.getElementById('confirm-modal-text');
    const btn = document.getElementById('confirm-modal-btn');
    if (!modal || !text) return;
    if (t) t.textContent = title || '确认操作';
    text.innerHTML = html;
    if (btn) {
        btn.textContent = okText || '确定';
        btn.className = 'btn ' + (isDanger === false ? 'btn-primary' : 'btn-danger');
    }
    modal.style.display = 'flex';
}

function askConfirm(html, cb, opts) {
    opts = opts || {};
    pendingDeleteUrl = null;
    pendingConfirmCb = cb;
    _openConfirmModal(opts.title || '确认操作', html, opts.okText || '确定', opts.danger);
}

function alertModal(message, title) {
    showMessageModal('warning', title || '提示', message);
}

function confirmDelete(url, itemName) {
    pendingConfirmCb = null;
    pendingDeleteUrl = url;
    _openConfirmModal('确认删除',
        '确定要删除「' + escHtmlText(itemName) + '」吗？<br><span style="color:var(--danger);font-size:12px;">此操作无法撤销</span>',
        '确定删除', true);
}

function closeConfirmModal() {
    const m = document.getElementById('confirm-modal');
    if (m) m.style.display = 'none';
    pendingDeleteUrl = null;
    pendingConfirmCb = null;
}

function executeConfirm() {
    const cb  = pendingConfirmCb;
    const url = pendingDeleteUrl;
    closeConfirmModal();
    if (typeof cb === 'function') { cb(); return; }
    if (url) window.location.href = withCsrf(url);
}

function confirmDeleteCategory(url, categoryName, softwareCount) {
    if (softwareCount > 0) {
        showMessageModal('warning', '无法删除', '该分类下还有 ' + softwareCount + ' 个软件，请先删除分类内的软件再删除分类。');
        return;
    }
    pendingConfirmCb = null;
    pendingDeleteUrl = url;
    _openConfirmModal('确认删除分类',
        '确定要删除分类「' + escHtmlText(categoryName) + '」吗？<br><span style="color:var(--danger);font-size:12px;">此操作无法撤销</span>',
        '确定删除', true);
}

function showError(message) {
    showMessageModal('error', '操作失败', message);
}

function showMessageModal(type, title, message) {
    const modal = document.getElementById('message-modal');
    const icon = document.getElementById('message-modal-icon');
    const modalTitle = document.getElementById('message-modal-title');
    const modalText = document.getElementById('message-modal-text');
    const modalBtn = document.getElementById('message-modal-btn');

    if (type === 'success') {
        icon.innerHTML = '✓';
        icon.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
        icon.style.color = '#fff';
        icon.style.boxShadow = '0 8px 20px rgba(16, 185, 129, 0.3)';
        modalTitle.style.color = '#065f46';
        modalBtn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
        modalBtn.style.boxShadow = '0 4px 12px rgba(16, 185, 129, 0.25)';
    } else if (type === 'warning') {
        icon.innerHTML = '!';
        icon.style.background = 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)';
        icon.style.color = '#fff';
        icon.style.boxShadow = '0 8px 20px rgba(245, 158, 11, 0.3)';
        modalTitle.style.color = '#92400e';
        modalBtn.style.background = 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)';
        modalBtn.style.boxShadow = '0 4px 12px rgba(245, 158, 11, 0.25)';
    } else {
        icon.innerHTML = '✗';
        icon.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
        icon.style.color = '#fff';
        icon.style.boxShadow = '0 8px 20px rgba(239, 68, 68, 0.3)';
        modalTitle.style.color = '#991b1b';
        modalBtn.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
        modalBtn.style.boxShadow = '0 4px 12px rgba(239, 68, 68, 0.25)';
    }

    modalTitle.textContent = title;

    if (!message || message.trim() === '' || message === title) {
        modalText.style.display = 'none';
    } else {
        modalText.style.display = 'block';
        modalText.innerHTML = message;
    }

    modalBtn.onmouseover = function() {
        this.style.transform = 'translateY(-1px)';
    };
    modalBtn.onmouseout = function() {
        this.style.transform = 'translateY(0)';
    };

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeMessageModal() {
    const modal = document.getElementById('message-modal');
    const content = document.getElementById('message-modal-content');

    content.classList.add('message-modal-out');

    setTimeout(() => {
        modal.style.display = 'none';
        content.classList.remove('message-modal-out');
        document.body.style.overflow = '';
    }, 150);
}

function toggleSubMenu(menuId, element) {
    const submenu = document.getElementById('submenu-' + menuId);
    const isExpanded = submenu.classList.contains('expanded');

    document.querySelectorAll('.nav-level-3-container.expanded').forEach(function(el) {
        if (el.id !== 'submenu-' + menuId) {
            el.classList.remove('expanded');
            el.previousElementSibling.classList.remove('expanded');
        }
    });

    if (isExpanded) {
        submenu.classList.remove('expanded');
        element.classList.remove('expanded');
    } else {
        submenu.classList.add('expanded');
        element.classList.add('expanded');
    }
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('collapsed');
    document.body.classList.toggle('sidebar-collapsed');

    const isCollapsed = sidebar.classList.contains('collapsed');
    localStorage.setItem('sidebarCollapsed', isCollapsed ? '1' : '0');
    syncSidebarIcon();
}

function syncSidebarIcon(){}

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');

    const isCollapsed = (window.innerWidth <= 768) || localStorage.getItem('sidebarCollapsed') === '1';
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
        document.body.classList.add('sidebar-collapsed');
    }
    if (typeof syncSidebarIcon === 'function') syncSidebarIcon();

    const activeParent = document.querySelector('.nav-level-2.active');
    if (activeParent && activeParent.classList.contains('expanded')) {
        const menuId = activeParent.getAttribute('onclick').match(/'([^']+)'/)[1];
        const submenu = document.getElementById('submenu-' + menuId);
        if (submenu) {
            submenu.classList.add('expanded');
        }
    }
});

function removeRow(el) {
    el.parentElement.remove();
}

function addRow(containerId, nameField, urlField, sortField) {
    const container = document.getElementById(containerId);
    const row = document.createElement('div');
    row.className = 'dynamic-row';
    row.innerHTML = (sortField ? '<input type="number" name="' + sortField + '[]" placeholder="排序" min="0" max="9999" style="width:80px;">' : '') + `
        <input type="text" name="${nameField}[]" placeholder="链接名称">
        <input type="url" name="${urlField}[]" placeholder="链接地址">
        <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">删除</button>
    `;
    container.appendChild(row);
}

function addRowSimple(containerId, fieldName, placeholder) {
    const container = document.getElementById(containerId);
    const row = document.createElement('div');
    row.className = 'dynamic-row';
    row.innerHTML = `
        <input type="text" name="${fieldName}" placeholder="${placeholder}">
        <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">删除</button>
    `;
    container.appendChild(row);
}

function shopAdminTheme(){
  var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', cur);
  try{ localStorage.setItem('shopAdminTheme', cur); }catch(e){}
  syncThemeBtn();
}
function syncThemeBtn(){
  var b = document.getElementById('themeBtn');
  if (!b) return;
  var dark = document.documentElement.getAttribute('data-theme') === 'dark';
  b.textContent = dark ? '☀️ 浅色' : '🌙 深色';
}
syncThemeBtn();

function shopLogoutConfirm(){
  askConfirm('确定要退出登录吗？', function(){ location.href = '?logout=1'; },
             { title: '退出登录', okText: '退出', danger: true });
  return false;
}

(function(){
  var d = window.__SHOP_SAVE_MSG__;
  if (!d) return;
  setTimeout(function(){
    var w = document.getElementById('admin-toast-wrap');
    if (!w) return;
    var el = document.createElement('div');
    el.className = 'admin-toast ' + (d.t === 'err' ? 'err' : (d.t === 'info' ? 'info' : 'ok'));
    el.textContent = (d.t === 'err' ? '✕ ' : (d.t === 'info' ? 'ℹ ' : '✓ ')) + d.m;
    w.appendChild(el);
    setTimeout(function(){
      el.style.transition = 'opacity .3s, transform .3s';
      el.style.opacity = '0'; el.style.transform = 'translateX(12px)';
      setTimeout(function(){ el.remove(); }, 340);
    }, 2000);
  }, 120);
})();

document.addEventListener('keydown', function(e){
  if (e.key === 'Escape'){ closeMessageModal(); closeConfirmModal(); }
});
</script>
</body>
</html>
