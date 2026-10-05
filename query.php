<?php

date_default_timezone_set('Asia/Shanghai');

define('IN_CRYPT', true);
define('IN_SHOP', true);
require_once __DIR__ . '/inc/crypt.php';
require_once __DIR__ . '/inc/shop_store.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$cfg     = shop_config();
$dbReady = shop_db_available();
$queryOn = !empty($cfg['query']['enable']);

function shop_q_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $p = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($p[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) return trim($_SERVER['HTTP_X_REAL_IP']);
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function shop_q_rl_dir() {
    static $dir = null;
    if ($dir !== null) return $dir;
    $cands = [
        __DIR__ . '/data',
        __DIR__ . '/tmp',
        rtrim(sys_get_temp_dir(), '/\\') . '/shop_rl_' . substr(md5(__DIR__), 0, 8),
    ];
    foreach ($cands as $d) {
        if ($d === '') continue;
        if (!is_dir($d)) @mkdir($d, 0755, true);
        if (is_dir($d) && is_writable($d)) { $dir = $d; return $dir; }
    }
    $dir = '';
    return $dir;
}
function shop_q_ratelimit($max = 10, $window = 600) {
    $dir = shop_q_rl_dir();
    if ($dir === '') return true;
    $file = $dir . '/shop_rl_' . md5('querypage|' . shop_q_ip());
    $now  = time();
    $fp = @fopen($file, 'c+');
    if (!$fp) return true;
    $ok = true;
    if (flock($fp, LOCK_EX)) {
        $d = json_decode((string)stream_get_contents($fp), true);
        if (!is_array($d) || !isset($d['c'], $d['t']) || ($now - (int)$d['t']) > $window) {
            $d = ['c' => 1, 't' => $now, 'w' => $window, 'max' => $max];
        } else {
            $d['c'] = (int)$d['c'] + 1;
            if ($d['c'] > $max) $ok = false;
        }
        $d['ip'] = shop_q_ip();
        $d['b']  = 'querypage';
        ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($d)); fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    if (random_int(1, 20) === 1) shop_rl_purge();
    return $ok;
}

$orderNo  = isset($_GET['order_no']) ? trim((string)$_GET['order_no']) : '';
$contact  = isset($_GET['contact'])  ? trim((string)$_GET['contact'])  : (isset($_GET['secret']) ? trim((string)$_GET['secret']) : '');
$queried  = false;
$result   = null;
$orders   = [];
$errMsg   = '';

if ($orderNo !== '' || $contact !== '') {
    if (!$queryOn) {
        $errMsg = '订单查询功能暂未开放，请联系管理员。';
    } elseif (!$dbReady) {
        $errMsg = shop_db_unavailable_hint();
    } elseif ($contact === '') {
        $errMsg = '请输入下单时填写的手机号或微信号。';
    } elseif (!shop_q_ratelimit(8, 600)) {
        $errMsg = '查询过于频繁，请 10 分钟后再试。';
    } else {
        $queried = true;
        if ($orderNo !== '') {
            $found = shop_order_get($orderNo);
            if (!$found) {
                $errMsg = '未查询到该订单，请核对订单号是否正确。订单号形如 ORD20260930194043BMXY。';
            } elseif (!shop_order_check_owner($found, $contact)) {
                $errMsg = '订单号与您填写的联系方式不匹配，请核对后重试。';
            } else {
                $result = $found;
            }
        } else {
            $orders = shop_order_search_by_contact($contact, 30);
            if (!$orders) {
                $errMsg = '没有查到该联系方式下的订单。请确认填的是下单时留的手机号或微信号。';
            }
        }
    }
}

$payCfg = $cfg['pay'];

$qFootName  = $payCfg['payee_name'] !== '' ? (string)$payCfg['payee_name'] : '在线商城';
$qLink2Text = (string)($cfg['footer_link2_text'] ?? '联系我们');
$qLink2Url  = trim((string)($cfg['footer_link2_url'] ?? '')) !== '' ? (string)$cfg['footer_link2_url'] : './contact.php';
$canPay = $result && in_array($result['status'], ['pending_payment', 'payment_failed'], true);

$payUrl = trim((string)$payCfg['contact_admin_url']);
if ($payUrl !== '' && !preg_match('#^(https?:)?//#i', $payUrl) && strpos($payUrl, '/') !== 0) {
    $payUrl = './' . ltrim($payUrl, './');
}

$statusColors = [
    'pending_payment' => ['#475569', '#f1f5f9'],
    'awaiting_verify' => ['#0e7490', '#cffafe'],
    'paid'            => ['#047857', '#d1fae5'],
    'payment_failed'  => ['#b91c1c', '#fee2e2'],
    'processing'      => ['#7e22ce', '#f3e8ff'],
    'completed'       => ['#065f46', '#a7f3d0'],
    'cancelled'       => ['#6b7280', '#e5e7eb'],
    'refunding'       => ['#c2410c', '#ffedd5'],
    'refunded'        => ['#9a3412', '#fed7aa'],
];
$sc = $statusColors[$result['status'] ?? ''] ?? ['#475569', '#f1f5f9'];
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?php echo htmlspecialchars($cfg['query']['page_title']); ?> | 在线商城</title>
<link rel="icon" href="https://vip.123pan.cn/1814921676/yk6baz03t0n000dcy3hrlqc16aw5wollDIYPAdrvDIQ0ApxwAwe1Aa==.png" type="image/png">
<script>(function(){try{var t=localStorage.getItem('shopTheme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<style>
:root{
  --bg:#f0f6fb; --bg2:#e6f2f8; --card:#ffffff; --text:#1e293b; --muted:#64748b;
  --line:#e2e8f0; --brand:#3b82f6; --brand2:#14b8a6; --soft:#f8fafc;
  --shadow:0 4px 20px rgba(30,58,138,.07);
}
html[data-theme="dark"]{
  --bg:#0b1220; --bg2:#0f172a; --card:#111c31; --text:#e5edf7; --muted:#93a4bd;
  --line:#1e2b45; --brand:#3b82f6; --brand2:#14b8a6; --soft:#0e1a2e;
  --shadow:0 4px 20px rgba(0,0,0,.35);
}
*{margin:0;padding:0;box-sizing:border-box;}
body{
  font-family:"Microsoft YaHei","PingFang SC",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  background:linear-gradient(135deg,var(--bg) 0%,var(--bg2) 100%);
  color:var(--text);min-height:100vh;padding-bottom:60px;-webkit-font-smoothing:antialiased;
}
a{color:inherit;text-decoration:none;}
.topbar{position:sticky;top:0;z-index:50;background:var(--card);border-bottom:1px solid var(--line);}
.topbar-inner{max-width:900px;margin:0 auto;padding:12px 20px;display:flex;align-items:center;gap:14px;}
.brand{display:flex;align-items:center;gap:9px;font-weight:700;font-size:17px;}
.brand .dot{width:26px;height:26px;border-radius:8px;background:linear-gradient(135deg,var(--brand),var(--brand2));display:flex;align-items:center;justify-content:center;font-size:14px;}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px;}
.tb-link{padding:7px 14px;border-radius:9px;font-size:13.5px;color:var(--muted);border:1px solid var(--line);transition:.2s;white-space:nowrap;}
.tb-link:hover{color:var(--brand);border-color:var(--brand);background:var(--soft);}
.icon-btn{width:36px;height:36px;border-radius:9px;border:1px solid var(--line);background:transparent;color:var(--muted);cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;transition:.2s;}
.icon-btn:hover{color:var(--brand);border-color:var(--brand);}
.wrap{max-width:900px;margin:0 auto;padding:0 20px;}
.hero{text-align:center;padding:36px 10px 24px;}
.hero h1{font-size:27px;font-weight:700;margin-bottom:10px;}
.hero p{color:var(--muted);font-size:14px;line-height:1.7;}
.panel{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:24px;box-shadow:var(--shadow);margin-bottom:20px;}
.panel h2{font-size:16px;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px;}
.fg{margin-bottom:15px;}
.fg label{display:block;font-size:13.5px;font-weight:600;margin-bottom:7px;}
.fg label .lo-hint{display:none;margin-top:7px;font-size:12.5px;color:var(--muted);}
.lo-hint b{color:var(--brand);font-family:Consolas,Monaco,monospace;}
.lo-fill{margin-left:8px;padding:3px 11px;border-radius:20px;border:1px solid var(--brand);background:none;color:var(--brand);font:inherit;font-size:12px;font-weight:600;cursor:pointer;}
.lo-fill:hover{background:var(--brand);color:#fff;}
.pm-label{font-size:12.5px;color:var(--muted);text-align:center;margin:0 0 8px;}
.pay-methods{display:flex;gap:7px;justify-content:center;flex-wrap:wrap;margin:0 0 13px;}
.pm-btn{
  font:inherit;font-size:12.5px;font-weight:600;cursor:pointer;white-space:nowrap;
  display:inline-flex;align-items:center;gap:4px;line-height:1.5;
  padding:6px 15px;border-radius:20px;transition:.15s;
  border:1px solid var(--line);background:var(--shell);color:var(--ink2);
}
.pm-btn:hover{border-color:var(--brand);color:var(--brand);}
.pm-btn.on{background:var(--brand);border-color:var(--brand);color:#fff;}
.hint{font-weight:400;color:var(--muted);font-size:12px;}
.fg input{width:100%;padding:12px 14px;border-radius:10px;border:1px solid var(--line);background:var(--soft);color:var(--text);font-size:14.5px;outline:none;transition:.2s;font-family:inherit;}
.fg input:focus{border-color:var(--brand);background:var(--card);box-shadow:0 0 0 3px rgba(59,130,246,.13);}
.btn{padding:12px 18px;border-radius:11px;border:none;font-size:14.5px;font-weight:600;cursor:pointer;font-family:inherit;transition:.2s;display:flex;align-items:center;justify-content:center;gap:7px;}
.btn:disabled{opacity:.55;cursor:not-allowed;}
.btn-primary{width:100%;background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;box-shadow:0 6px 18px rgba(59,130,246,.3);}
.btn-primary:hover:not(:disabled){filter:brightness(1.08);}
.btn-ghost{background:var(--soft);color:var(--text);border:1px solid var(--line);}
.btn-ghost:hover{border-color:var(--brand);color:var(--brand);}
.btn-success{background:linear-gradient(135deg,#10b981,#059669);color:#fff;box-shadow:0 6px 18px rgba(16,185,129,.3);}
.btn-success:hover:not(:disabled){filter:brightness(1.07);}
.alert{border-radius:11px;padding:13px 16px;font-size:13.5px;line-height:1.7;margin-bottom:18px;display:flex;gap:9px;align-items:flex-start;}
.alert.err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}
.alert.info{background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.22);color:var(--text);}
html[data-theme="dark"] .alert.err{background:#3b1215;border-color:#7f1d1d;color:#fca5a5;}
.status-pill{display:inline-block;padding:5px 15px;border-radius:20px;font-size:14px;font-weight:700;}
.steps{display:flex;gap:6px;margin:18px 0 6px;flex-wrap:wrap;}
.step{flex:1;min-width:92px;text-align:center;font-size:11.5px;color:var(--muted);position:relative;}
.step .dot2{width:24px;height:24px;border-radius:50%;margin:0 auto 6px;display:flex;align-items:center;justify-content:center;font-size:12px;background:var(--soft);border:1.5px solid var(--line);color:var(--muted);font-weight:700;}
.step.done .dot2{background:linear-gradient(135deg,var(--brand),var(--brand2));border-color:transparent;color:#fff;}
.step.done{color:var(--text);font-weight:600;}
.info-row{display:flex;justify-content:space-between;gap:14px;font-size:14px;padding:9px 0;border-bottom:1px dashed var(--line);}
.info-row:last-child{border-bottom:none;}
.info-row .k{color:var(--muted);flex-shrink:0;}
.info-row .v{font-weight:600;text-align:right;word-break:break-all;}
.qr-zone{text-align:center;padding:6px 0 14px;}
.qr-zone img{width:210px;height:210px;object-fit:contain;border-radius:12px;background:#fff;padding:9px;border:1px solid var(--line);}
.qr-zone .qr-missing{width:210px;height:210px;margin:0 auto;border-radius:12px;border:2px dashed var(--line);display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:13px;text-align:center;padding:16px;line-height:1.7;}
.qr-tip{font-size:12.5px;color:var(--muted);margin-top:9px;}
.code-zone{border:1.5px dashed rgba(59,130,246,.45);border-radius:13px;padding:14px 16px;margin-bottom:16px;background:rgba(59,130,246,.05);}
.code-zone .cz-title{font-size:12.5px;color:var(--muted);line-height:1.6;margin-bottom:10px;}
.code-zone .cz-main{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.code-big{font-size:40px;font-weight:800;letter-spacing:10px;color:var(--brand);font-family:"SF Mono",Consolas,Monaco,monospace;padding-left:10px;word-break:break-all;}
.cz-btns{display:flex;flex-direction:column;gap:7px;flex-shrink:0;}
.mini-btn{padding:7px 13px;border-radius:9px;border:1px solid var(--line);background:var(--card);color:var(--muted);font-size:12.5px;cursor:pointer;font-family:inherit;transition:.2s;white-space:nowrap;}
.mini-btn:hover{border-color:var(--brand);color:var(--brand);}
.footer{text-align:center;color:var(--muted);font-size:13px;padding:22px 20px;line-height:1.9;}

.toast-wrap{
  position:fixed;top:72px;right:16px;z-index:400;display:flex;flex-direction:column;gap:9px;
  align-items:flex-end;pointer-events:none;max-width:min(420px,calc(100vw - 32px));
}
.toast{padding:13px 22px;border-radius:12px;font-size:14.5px;font-weight:700;color:#fff;box-shadow:0 12px 34px rgba(0,0,0,.26);animation:tin .3s ease-out;max-width:100%;line-height:1.6;white-space:pre;width:max-content;max-width:none;}

.cfm-mask{position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);z-index:300;display:none;align-items:center;justify-content:center;padding:18px;}
.cfm-mask.show{display:flex;}
.cfm{background:var(--card);border:1px solid var(--line);border-radius:16px;width:100%;max-width:400px;box-shadow:0 28px 70px rgba(0,0,0,.35);animation:pop .2s ease-out;overflow:hidden;}
.cfm-hd{padding:15px 20px;border-bottom:1px solid var(--line);font-size:16px;font-weight:700;color:var(--text);}
.cfm-bd{padding:18px 20px;font-size:14px;line-height:1.8;text-align:center;color:var(--text);}
.cfm-ft{padding:12px 20px;border-top:1px solid var(--line);display:flex;gap:10px;}
.cfm .fg{margin:14px 0 0;}
.cfm .fg input{width:100%;padding:10px 13px;border-radius:9px;border:1px solid var(--line);background:var(--soft);color:var(--text);font-size:14px;outline:none;font-family:inherit;}
.cfm .fg input:focus{border-color:var(--brand);background:var(--card);}
.cfm .fg.invalid input{border-color:#ef4444;}
.cfm .fg .err{display:none;color:#ef4444;font-size:12.5px;margin-top:5px;}
.cfm .fg.invalid .err{display:block;}
@keyframes pop{from{opacity:0;transform:translateY(-12px) scale(.97);}to{opacity:1;transform:none;}}
.toast.ok{background:linear-gradient(135deg,#10b981,#059669);}
.toast.err{background:linear-gradient(135deg,#ef4444,#dc2626);}
.toast.info{background:linear-gradient(135deg,#3b82f6,#2563eb);}
@keyframes tin{from{opacity:0;transform:translateX(12px);}to{opacity:1;transform:none;}}
@media(max-width:640px){
  .hero h1{font-size:22px;} .panel{padding:18px;}
  .code-big{font-size:32px;letter-spacing:7px;}
  .brand span.bt{display:none;}

  .toast-wrap{top:76px;left:10px;right:10px;max-width:none;align-items:stretch;}
  .toast{text-align:center;padding:12px 16px;}
}

.qr-zoomable{cursor:zoom-in;transition:transform .2s,box-shadow .2s;}
.qr-zoomable:hover{transform:scale(1.04);box-shadow:0 10px 26px rgba(0,0,0,.18);}
.qr-zoom{
  position:fixed;inset:0;z-index:350;display:none;flex-direction:column;align-items:center;justify-content:center;
  gap:14px;padding:20px;background:rgba(8,14,26,.9);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
}
.qr-zoom.show{display:flex;}
.qr-zoom img{
  width:min(76vw,56vh);height:min(76vw,56vh);max-width:520px;max-height:520px;object-fit:contain;
  background:#fff;border-radius:16px;padding:12px;box-shadow:0 24px 70px rgba(0,0,0,.5);
}
.qr-zoom .qz-close{
  position:absolute;top:16px;right:16px;width:42px;height:42px;border-radius:50%;border:none;
  background:rgba(255,255,255,.16);color:#fff;font-size:24px;line-height:1;cursor:pointer;transition:.2s;
}
.qr-zoom .qz-close:hover{background:rgba(255,255,255,.3);}
.qr-zoom .qz-info{color:#fff;text-align:center;font-size:14px;line-height:1.8;}
.qr-zoom .qz-info b{color:#7dd3fc;font-size:18px;}
.qr-zoom .qz-code{
  display:block;width:fit-content;margin:10px auto 0;padding:6px 26px;
  border-radius:10px;background:rgba(255,255,255,.14);
  font-family:Consolas,Monaco,monospace;font-size:30px;font-weight:800;letter-spacing:8px;color:#fff;
}
.qr-zoom .qz-hint{max-width:430px;color:rgba(255,255,255,.62);font-size:12.5px;line-height:1.7;text-align:center;}
@media (max-width:640px){
  .qr-zoom img{width:min(88vw,54vh);height:min(88vw,54vh);}
  .qr-zoom .qz-code{font-size:24px;letter-spacing:6px;padding:5px 20px;}
}

.ord-card{
  display:block;border:1px solid var(--line);border-radius:12px;padding:13px 15px;margin-bottom:10px;
  background:var(--card);text-decoration:none;color:inherit;transition:.2s;
}
.ord-card:hover{border-color:var(--brand);box-shadow:0 6px 18px rgba(59,130,246,.13);transform:translateY(-1px);}
.ord-row1{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:7px;}
.ord-no{font-family:Consolas,Monaco,monospace;font-size:13px;font-weight:700;word-break:break-all;}
.ord-row2{display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin-bottom:7px;}
.ord-items{font-size:13.5px;color:var(--muted);word-break:break-all;}
.ord-amt{font-size:16px;font-weight:800;color:var(--brand);white-space:nowrap;}
.ord-row3{display:flex;flex-wrap:wrap;gap:6px 14px;font-size:12px;color:var(--muted);align-items:center;}
.ord-go{margin-left:auto;color:var(--brand);font-weight:600;white-space:nowrap;}</style>
</head>
<body>

<div class="topbar">
  <div class="topbar-inner">
    <a href="./" class="brand"><span class="dot">🔍</span><span class="bt">订单查询</span></a>
    <div class="topbar-right">
      <a href="./" class="tb-link">🛒 去下单</a>
      <button class="icon-btn" id="themeBtn" title="切换明暗主题" onclick="toggleTheme()">🌙</button>
    </div>
  </div>
</div>

<div class="wrap">
  <div class="hero">
    <h1><?php echo htmlspecialchars($cfg['query']['page_title']); ?></h1>
    <p><?php echo nl2br(htmlspecialchars($cfg['query']['page_tip'])); ?></p>
  </div>

  <div class="panel">
    <h2>📄 查询订单</h2>
    <form method="get" action="./query.php" id="queryForm" autocomplete="off">
      <div class="fg">
        <label>手机号 或 微信号 <span class="hint">（必填，写下单时留的那个）</span></label>
        <input type="text" name="contact" id="contactInput" value="<?php echo htmlspecialchars($contact); ?>"
               placeholder="例如 13800001234 或 微信号" required>
      </div>
      <div class="fg">
        <label>订单号 <span class="hint">（选填；留空则列出该联系方式下的全部订单）</span></label>
        <input type="text" name="order_no" id="orderNoInput" value="<?php echo htmlspecialchars($orderNo); ?>"
               placeholder="留空即可，会列出你的全部订单">
        <div id="lastOrderHint" class="lo-hint" style="display:none;"></div>
      </div>
      <button type="submit" class="btn btn-primary">🔍 查询订单</button>
      <button type="button" class="btn btn-ghost" style="width:100%;margin-top:10px;" onclick="clearOrderNo()">🗂 查看我的全部订单</button>
    </form>
  </div>

  <?php if ($errMsg !== ''): ?>
    <div class="alert err">⚠️ <div><?php echo htmlspecialchars($errMsg); ?></div></div>
  <?php endif; ?>

  <?php if ($result): ?>
    <?php
      $st = $result['status'];

      $order = ['pending_payment', 'awaiting_verify', 'paid', 'processing', 'completed'];
      $labels = ['已下单', '已标记付款', '付款已核实', '处理中', '已完成'];
      $idx = array_search($st, $order, true);
      if ($idx === false) $idx = ($st === 'payment_failed') ? 1 : 0;
    ?>
    <div class="panel">
      <h2>🧾 订单详情</h2>

      <div style="text-align:center;padding:6px 0 4px;">
        <span class="status-pill" style="color:<?php echo $sc[0]; ?>;background:<?php echo $sc[1]; ?>;">
          <?php echo htmlspecialchars($result['status_label']); ?>
        </span>
      </div>

      <div class="steps">
        <?php foreach ($labels as $i => $lb): ?>
          <div class="step <?php echo $i <= $idx ? 'done' : ''; ?>">
            <div class="dot2"><?php echo $i <= $idx ? '✓' : ($i + 1); ?></div>
            <div><?php echo htmlspecialchars($lb); ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div style="margin-top:18px;">
        <div class="info-row"><span class="k">订单号</span><span class="v" id="orderNoText"><?php echo htmlspecialchars($result['order_no']); ?></span></div>
        <div class="info-row"><span class="k">联系人</span><span class="v"><?php echo htmlspecialchars($result['customer_name']); ?></span></div>
        <div class="info-row"><span class="k">联系方式</span><span class="v"><?php echo htmlspecialchars($result['phone']); ?></span></div>
        <div class="info-row"><span class="k">微信号</span><span class="v"><?php echo htmlspecialchars($result['wechat']); ?></span></div>
        <div class="info-row"><span class="k">商品</span><span class="v"><?php echo htmlspecialchars($result['items_text']); ?></span></div>
        <div class="info-row"><span class="k">金额</span><span class="v" style="color:var(--brand);font-size:17px;">￥<?php echo shop_price($result['amount']); ?></span></div>
        <div class="info-row"><span class="k">服务方式</span><span class="v"><?php echo htmlspecialchars($result['mode_label']); ?></span></div>
        <?php if ($result['book_time'] !== ''): ?>
          <div class="info-row"><span class="k">预约时间</span><span class="v"><?php echo htmlspecialchars($result['book_time']); ?></span></div>
        <?php endif; ?>
        <?php if (trim($result['remark']) !== ''): ?>
          <div class="info-row"><span class="k">备注</span><span class="v"><?php echo htmlspecialchars($result['remark']); ?></span></div>
        <?php endif; ?>
        <div class="info-row"><span class="k">下单时间</span><span class="v"><?php echo htmlspecialchars($result['created_at']); ?></span></div>
        <?php if ($result['pay_marked_at'] !== ''): ?>
          <div class="info-row"><span class="k">标记付款时间</span><span class="v"><?php echo htmlspecialchars($result['pay_marked_at']); ?></span></div>
        <?php endif; ?>
        <?php if ($result['verified_at'] !== ''): ?>
          <div class="info-row"><span class="k">核实到账时间</span><span class="v"><?php echo htmlspecialchars($result['verified_at']); ?></span></div>
        <?php endif; ?>
        <?php if (trim((string)$result['admin_note']) !== ''): ?>
          <div class="info-row"><span class="k">管理员备注</span><span class="v"><?php echo htmlspecialchars($result['admin_note']); ?></span></div>
        <?php endif; ?>
      </div>

      <div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap;">
        <button class="btn btn-ghost" style="flex:1;min-width:130px;" onclick="copyText('<?php echo htmlspecialchars($result['order_no'], ENT_QUOTES); ?>','订单号已复制')">📋 复制订单号</button>
        <a class="btn btn-ghost" style="flex:1;min-width:130px;" href="./">🛒 继续下单</a>
      </div>
    </div>

    <?php if ($canPay): ?>
      <div class="panel">
        <h2>💳 继续付款</h2>

        <?php if ($st === 'payment_failed'): ?>
          <div class="alert err">⚠️ <div>管理员未能匹配到您的付款。请核对下方备注码后重新付款，或联系管理员协助核对。</div></div>
        <?php endif; ?>

        <?php
          $qrSplit = (($payCfg['qr_mode'] ?? 'aggregate') === 'split');
          $qrList  = [];
          if ($qrSplit) {
              if (trim((string)($payCfg['qr_wechat'] ?? '')) !== '') $qrList['wechat'] = ['💬', '微信支付', trim((string)$payCfg['qr_wechat'])];
              if (trim((string)($payCfg['qr_alipay'] ?? '')) !== '') $qrList['alipay'] = ['🅰', '支付宝',  trim((string)$payCfg['qr_alipay'])];
          }
          $qrFirst = $qrList ? reset($qrList)[2] : trim((string)$payCfg['qr_url']);
        ?>
        <div class="qr-zone">
          <?php if (count($qrList) > 1): ?>
            <div class="pm-label">请选择支付方式</div>
            <div class="pay-methods">
              <?php $qi = 0; foreach ($qrList as $qk => $qv): ?>
                <button type="button" class="pm-btn<?php echo $qi === 0 ? ' on' : ''; ?>" data-qr="<?php echo htmlspecialchars($qv[2]); ?>"
                        onclick="pickPayQr(this)"><?php echo $qv[0] . ' ' . htmlspecialchars($qv[1]); ?></button>
              <?php $qi++; endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if ($qrFirst !== ''): ?>
            <img id="payQrImg" class="qr-zoomable" src="<?php echo htmlspecialchars($qrFirst); ?>" alt="收款码"
                 title="点击放大" onclick="openQrZoom()" referrerpolicy="no-referrer"
                 onerror="this.onerror=null;this.outerHTML='<div class=&quot;qr-missing&quot;>收款码图片加载失败<br>请联系管理员</div>'">
            <div class="qr-tip"><?php
              echo (trim((string)$payCfg['qr_tip']) !== '' ? htmlspecialchars($payCfg['qr_tip']) . ' · ' : '') . '🔍 点击收款码可放大';
            ?></div>
          <?php else: ?>
            <div class="qr-missing">管理员尚未配置收款码<br>请点击下方「联系管理员」</div>
            <?php if (trim((string)$payCfg['qr_tip']) !== ''): ?>
              <div class="qr-tip"><?php echo htmlspecialchars($payCfg['qr_tip']); ?></div>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <div class="code-zone">
          <div class="cz-title">💳 <?php echo (($payCfg["code_mode"] ?? "short") === "wechat") ? "付款时请在「备注 / 附言」中填写您的微信" : "付款备注码"; ?></div>
          <div class="cz-main">
            <?php if (($payCfg['code_mode'] ?? 'short') === 'wechat'): ?>
              <div style="font-size:15px;font-weight:700;color:var(--brand);word-break:break-all;">我的微信：<?php echo htmlspecialchars($result['wechat']); ?></div>
            <?php else: ?>
              <div class="code-big" id="payCodeText"><?php echo htmlspecialchars($result['pay_code']); ?></div>
              <div class="cz-btns">
                <?php if (!empty($payCfg['allow_regenerate'])): ?>
                  <button class="mini-btn" onclick="regenCode()">🔄 换一个</button>
                <?php endif; ?>
                <button class="mini-btn" onclick="copyText('<?php echo htmlspecialchars($result['pay_code'], ENT_QUOTES); ?>','备注码已复制')">📋 复制</button>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <button class="btn btn-success" id="paidBtn" style="width:100%;" onclick="markPaid()">✅ 已完成扫码支付</button>

        <?php if ($payUrl !== ''): ?>
          <a class="btn btn-ghost" style="width:100%;margin-top:10px;" href="<?php echo htmlspecialchars($payUrl); ?>" target="_blank" rel="noopener">
            💬 <?php echo htmlspecialchars($payCfg['contact_admin_text'] ?: '联系管理员'); ?>
          </a>
        <?php endif; ?>

        <?php if (trim((string)$payCfg['tips']) !== ''): ?>
          <div style="font-size:12.5px;color:var(--muted);line-height:1.75;text-align:center;margin-top:14px;">
            <?php echo htmlspecialchars($payCfg['tips']); ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($orders): ?>
    <div class="panel">
      <h2>📋 查询到 <?php echo count($orders); ?> 笔订单 <span class="hint">（最新在前）</span></h2>
      <?php foreach ($orders as $o):
        $c = $statusColors[$o['status']] ?? ['#475569', '#f1f5f9'];
        $payable = in_array($o['status'], ['pending_payment', 'payment_failed'], true);
      ?>
        <a class="ord-card" href="?order_no=<?php echo urlencode($o['order_no']); ?>&amp;contact=<?php echo urlencode($contact); ?>">
          <div class="ord-row1">
            <span class="ord-no"><?php echo htmlspecialchars($o['order_no']); ?></span>
            <span class="status-pill" style="color:<?php echo $c[0]; ?>;background:<?php echo $c[1]; ?>;font-size:12.5px;padding:3px 12px;"><?php echo htmlspecialchars($o['status_label']); ?></span>
          </div>
          <div class="ord-row2">
            <span class="ord-items"><?php echo htmlspecialchars($o['items_text']); ?></span>
            <span class="ord-amt">￥<?php echo shop_price($o['amount']); ?></span>
          </div>
          <div class="ord-row3">
            <span>🕒 <?php echo htmlspecialchars($o['created_at']); ?></span>
            <span><?php echo htmlspecialchars($o['mode_label']); ?><?php echo $o['book_time'] !== '' ? ' · ' . htmlspecialchars($o['book_time']) : ''; ?></span>
            <span class="ord-go"><?php echo $payable ? '去付款 →' : '查看详情 →'; ?></span>
          </div>
        </a>
      <?php endforeach; ?>
      <div class="alert info" style="margin:14px 0 0;">
        🔍 <div>点任意一笔可查看详情；未付款的订单可直接继续付款。</div>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="footer">
  <div><?php echo htmlspecialchars($qFootName); ?></div>
  <div style="margin-top:5px;">
    <a href="./" style="color:var(--brand);">在线下单</a><?php if (trim($qLink2Text) !== ''): ?> · <a href="<?php echo htmlspecialchars($qLink2Url); ?>" style="color:var(--brand);"><?php echo htmlspecialchars($qLink2Text); ?></a><?php endif; ?>
  </div>
</div>

<div class="cfm-mask" id="cfmMask">
  <div class="cfm" role="dialog" aria-modal="true">
    <div class="cfm-hd" id="cfmTitle">确认操作</div>
    <div class="cfm-bd">
      <div id="cfmMsg"></div>
      <div class="fg" id="cfmInputWrap" style="display:none;">
        <input type="text" id="cfmInput" maxlength="64" autocomplete="off">
        <div class="err">请填写正确内容</div>
      </div>
    </div>
    <div class="cfm-ft">
      <button class="btn btn-ghost" style="flex:1;" onclick="closeCfm()">取消</button>
      <button class="btn btn-primary" id="cfmOk" style="flex:1;" onclick="cfmGo()">确定</button>
    </div>
  </div>
</div>

<div class="qr-zoom" id="qrZoom" onclick="if(event.target===this)closeQrZoom()">
  <button class="qz-close" onclick="closeQrZoom()" aria-label="关闭">×</button>
  <img id="qrZoomImg" src="" alt="收款码" referrerpolicy="no-referrer">
  <div class="qz-info" id="qrZoomInfo"></div>
  <div class="qz-hint">长按二维码可保存到相册，再用微信 / 支付宝「扫一扫 → 从相册选取」完成支付；点击空白处或按 Esc 关闭</div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
var ORDER_NO = <?php echo json_encode($result['order_no'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var CAN_PAY  = <?php echo $canPay ? 'true' : 'false'; ?>;

function toggleTheme(){
  var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', cur);
  document.getElementById('themeBtn').textContent = cur === 'dark' ? '☀️' : '🌙';
  try{ localStorage.setItem('shopTheme', cur); }catch(e){}
}
(function(){ document.getElementById('themeBtn').textContent = document.documentElement.getAttribute('data-theme') === 'dark' ? '☀️' : '🌙'; })();

function toast(msg, type){
  var w = document.getElementById('toastWrap');
  var d = document.createElement('div');
  var kind = type || 'info';
  d.className = 'toast ' + kind;
  d.textContent = msg;
  w.appendChild(d);

  var hold = (kind === 'err') ? 3000 : 2000;
  setTimeout(function(){
    d.style.transition = 'opacity .3s, transform .3s';
    d.style.opacity = '0'; d.style.transform = 'translateX(12px)';
    setTimeout(function(){ d.remove(); }, 320);
  }, hold);
}
function copyText(txt, ok){
  var done = function(){ toast(ok || '已复制', 'ok'); };
  if (navigator.clipboard && window.isSecureContext){
    navigator.clipboard.writeText(txt).then(done).catch(function(){ fb(txt, done); });
  } else { fb(txt, done); }
}
function fb(txt, done){
  var ta = document.createElement('textarea');
  ta.value = txt; ta.style.position='fixed'; ta.style.left='-9999px';
  document.body.appendChild(ta); ta.select();
  try{ document.execCommand('copy'); done(); }catch(e){ toast('复制失败，请手动长按选择','err'); }
  ta.remove();
}

(function(){
  try{
    var el = document.getElementById('orderNoInput');
    if (!el) return;
    var wrap = document.getElementById('lastOrderHint');
    var last = localStorage.getItem('shopLastOrder') || '';
    if (!last || el.value || !wrap) return;
    wrap.innerHTML = '上次查询的订单号：<b>' + last.replace(/[<>&]/g, '') + '</b>'
      + '<button type="button" class="lo-fill" onclick="fillLastOrder()">填入</button>';
    wrap.style.display = 'block';
  }catch(e){}
})();
function fillLastOrder(){
  try{
    var el = document.getElementById('orderNoInput');
    var last = localStorage.getItem('shopLastOrder') || '';
    if (el && last){ el.value = last; el.focus(); }
    var w = document.getElementById('lastOrderHint');
    if (w) w.style.display = 'none';
  }catch(e){}
}
function clearOrderNo(){
  var no = document.getElementById('orderNoInput');
  var ct = document.getElementById('contactInput');
  if (no) no.value = '';
  if (ct && ct.value.trim() === ''){ ct.focus(); return; }
  var f = document.getElementById('queryForm');
  if (f) f.submit();
}

var QR_AMOUNT = <?php echo json_encode($result ? shop_price($result['amount']) : '', JSON_UNESCAPED_UNICODE); ?>;
var QR_CODE   = <?php echo json_encode(($result && ($payCfg['code_mode'] ?? 'short') !== 'wechat') ? (string)$result['pay_code'] : '', JSON_UNESCAPED_UNICODE); ?>;

function pickPayQr(btn){
  var img = document.getElementById('payQrImg');
  if (img && btn && btn.getAttribute('data-qr')) img.src = btn.getAttribute('data-qr');
  var box = btn && btn.parentNode;
  if (box) Array.prototype.forEach.call(box.querySelectorAll('.pm-btn'), function(b){ b.classList.remove('on'); });
  if (btn) btn.classList.add('on');
}
function openQrZoom(){
  var el = document.getElementById('payQrImg');
  if (!el) return;
  var info = '';
  if (ORDER_NO) info += '订单号：' + ORDER_NO + '<br>';
  if (QR_AMOUNT) info += '应付金额：<b>￥' + QR_AMOUNT + '</b>';
  if (QR_CODE) info += '<div class="qz-code">' + QR_CODE + '</div>';
  document.getElementById('qrZoomImg').src = el.src;
  document.getElementById('qrZoomInfo').innerHTML = info;
  document.getElementById('qrZoom').classList.add('show');
}
function closeQrZoom(){
  document.getElementById('qrZoom').classList.remove('show');
}

var cfmCb = null, cfmNeedInput = false;

function cfmOpen(title, html, okText, needInput, placeholder, cb){
  document.getElementById('cfmTitle').textContent = title;
  document.getElementById('cfmMsg').innerHTML = html;
  document.getElementById('cfmOk').textContent = okText || '确定';
  cfmNeedInput = !!needInput;
  var wrap = document.getElementById('cfmInputWrap');
  var inp  = document.getElementById('cfmInput');
  wrap.style.display = cfmNeedInput ? '' : 'none';
  wrap.classList.remove('invalid');
  inp.value = '';
  if (placeholder) inp.placeholder = placeholder;
  cfmCb = cb;
  document.getElementById('cfmMask').classList.add('show');
  if (cfmNeedInput) setTimeout(function(){ inp.focus(); }, 120);
}
function askConfirm(html, okText, cb){ cfmOpen('确认操作', html, okText, false, '', cb); }
function askInput(title, html, placeholder, okText, cb){ cfmOpen(title, html, okText, true, placeholder, cb); }
function closeCfm(){
  document.getElementById('cfmMask').classList.remove('show');
  cfmCb = null; cfmNeedInput = false;
}
function cfmGo(){
  if (cfmNeedInput){
    var v = document.getElementById('cfmInput').value.trim();
    if (!v){
      document.getElementById('cfmInputWrap').classList.add('invalid');
      document.getElementById('cfmInput').focus();
      return;
    }
    var fn = cfmCb;
    closeCfm();
    if (typeof fn === 'function') fn(v);
    return;
  }
  var f = cfmCb;
  closeCfm();
  if (typeof f === 'function') f();
}

function markPaid(){
  if (!ORDER_NO || !CAN_PAY) return;
  askConfirm(
    '请确认您已完成扫码付款<br><br>' +
    '订单号：<b>' + ORDER_NO + '</b><br><br>' +
    '<span style="color:var(--muted);font-size:12.5px;">管理员将人工核对到账记录，核实后为您安排技术员。</span>',
    '我已付款',
    function(){
      var btn = document.getElementById('paidBtn');
      if (btn){ btn.disabled = true; btn.textContent = '提交中…'; }
      fetch('api.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'mark_paid', order_no:ORDER_NO})
      })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (!res.ok){ toast(res.msg || '提交失败','err'); if (btn){ btn.disabled=false; btn.textContent='✅ 已完成扫码支付'; } return; }
        toast('已提交付款信息\n等待管理员核实','ok');
        setTimeout(function(){ location.reload(); }, 1200);
      })
      .catch(function(){ toast('网络异常，请稍后重试','err'); if (btn){ btn.disabled=false; btn.textContent='✅ 已完成扫码支付'; } });
    }
  );
}

function regenCode(){
  if (!ORDER_NO) return;
  fetch('api.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'regen_code', order_no:ORDER_NO})
  })
  .then(function(r){ return r.json(); })
  .then(function(res){
    if (!res.ok){ toast(res.msg || '更换失败','err'); return; }
    var el = document.getElementById('payCodeText');
    if (el) el.textContent = res.pay_code;
    toast('备注码已更换为 ' + res.pay_code, 'ok');
  })
  .catch(function(){ toast('网络异常，请稍后重试','err'); });
}

document.getElementById('cfmMask').addEventListener('click', function(e){ if (e.target === this) closeCfm(); });
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape' && document.getElementById('qrZoom').classList.contains('show')){ closeQrZoom(); return; }
  if (!document.getElementById('cfmMask').classList.contains('show')) return;
  if (e.key === 'Escape') closeCfm();
  if (e.key === 'Enter') cfmGo();
});</script>
</body>
</html>
