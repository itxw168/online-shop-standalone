<?php
date_default_timezone_set('Asia/Shanghai');

define('IN_CRYPT', true);
define('IN_SHOP', true);
require_once __DIR__ . '/inc/crypt.php';
require_once __DIR__ . '/inc/shop_store.php';

$cfg     = shop_config();
$payCfg  = $cfg['pay'] ?? [];
$c       = $cfg['contact'] ?? [];

if (isset($c['enable']) && !$c['enable']) {
    header('Location: ./index.php');
    exit;
}

$brand = trim((string)($cfg['brand_name'] ?? '')) !== ''
       ? (string)$cfg['brand_name']
       : (trim((string)($payCfg['payee_name'] ?? '')) !== '' ? (string)$payCfg['payee_name'] : '在线商城');

$cTitle = trim((string)($c['title'] ?? ''))    !== '' ? (string)$c['title']    : '联系' . (string)$c['owner_name'];
$cSub   = (string)($c['subtitle'] ?? '');
$notice = trim((string)($c['notice'] ?? ''));
$owner  = (string)($c['owner_name'] ?? '站长');

$wechat = trim((string)($c['wechat'] ?? ''));
$qq     = trim((string)($c['qq'] ?? ''));
$phone  = trim((string)($c['phone'] ?? ''));
$email  = trim((string)($c['email'] ?? ''));

$qrUrl  = trim((string)($c['qr_url'] ?? ''));
$qrTip  = trim((string)($c['qr_tip'] ?? '')) !== '' ? (string)$c['qr_tip'] : '扫码添加微信';

$dyHome = trim((string)($c['douyin_home'] ?? ''));
$dyLive = trim((string)($c['douyin_live'] ?? ''));
$dyId   = trim((string)($c['douyin_id'] ?? ''));

$hours  = trim((string)($c['work_hours'] ?? ''));
$extra  = (string)($c['extra'] ?? '');
$extraLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $extra)), function ($x) { return $x !== ''; }));

$tbQuery   = (string)($cfg['topbar_query'] ?? '🔍 查询订单');
$ftNote    = trim((string)($c['footer_note'] ?? '')) !== ''
           ? (string)$c['footer_note']
           : (string)($cfg['footer_note'] ?? '');

$hasContact = ($wechat !== '' || $qq !== '' || $phone !== '' || $email !== '');
$hasDouyin  = ($dyHome !== '' || $dyLive !== '' || $dyId !== '');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function contact_icon($k) {
    $m = ['wechat' => '💬', 'qq' => '🐧', 'phone' => '📱', 'email' => '✉️'];
    return $m[$k] ?? '•';
}
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#2563eb">
<title><?php echo h($cTitle); ?> · <?php echo h($brand); ?></title>
<script>(function(){try{var t=localStorage.getItem('shopTheme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<style>

:root{
  --bg:#f4f6fb; --surface:#ffffff; --surface2:#eef2f7; --surface3:#e1e7f0;
  --text:#0f172a; --text2:#64748b; --text3:#94a3b8;
  --border:#e5e9f2; --border-strong:#cbd5e1;
  --primary:#2563eb; --primary-hover:#1d4ed8; --primary-soft:rgba(37,99,235,.08);
  --ok:#059669; --warn:#d97706; --danger:#dc2626;
  --shadow:0 1px 2px rgba(15,23,42,.05), 0 10px 28px -16px rgba(15,23,42,.14);
  --ok-soft:#dcfce7; --warn-soft:#fef3c7; --info-soft:#eff6ff; --info-border:#bfdbfe; --info-text:#1d4ed8;
}
html[data-theme="dark"]{
  --bg:#0b1220; --surface:#121b2e; --surface2:#0f1830; --surface3:rgba(148,163,184,.10);
  --text:#e2e8f0; --text2:#94a3b8; --text3:#64748b;
  --border:rgba(148,163,184,.16); --border-strong:rgba(148,163,184,.32);
  --primary:#60a5fa; --primary-hover:#93c5fd; --primary-soft:rgba(96,165,250,.14);
  --ok:#34d399; --warn:#fbbf24; --danger:#f87171;
  --shadow:0 1px 2px rgba(0,0,0,.35), 0 12px 32px -16px rgba(0,0,0,.5);
  --ok-soft:rgba(52,211,153,.14); --warn-soft:rgba(251,191,36,.12);
  --info-soft:rgba(96,165,250,.10); --info-border:rgba(96,165,250,.25); --info-text:#93c5fd;
}
*{box-sizing:border-box;}
body{
  margin:0;background:var(--bg);color:var(--text);
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;
  font-size:15px;line-height:1.7;-webkit-font-smoothing:antialiased;
}
a{color:var(--primary);text-decoration:none;}
a:hover{text-decoration:underline;}

.topbar{
  position:sticky;top:0;z-index:50;background:var(--surface);
  border-bottom:1px solid var(--border);backdrop-filter:saturate(1.6) blur(8px);
}
.topbar-inner{
  max-width:960px;margin:0 auto;padding:12px 20px;
  display:flex;align-items:center;gap:12px;flex-wrap:wrap;
}
.brand{display:flex;align-items:center;gap:9px;font-weight:800;font-size:16px;}
.brand .dot{font-size:19px;}
.brand .badge{
  font-size:11.5px;font-weight:700;padding:2px 8px;border-radius:999px;
  background:var(--primary-soft);color:var(--primary);border:1px solid var(--primary);
}
.topbar .spacer{flex:1;}
.tb-btn{
  padding:7px 14px;border-radius:9px;font-size:13.5px;font-weight:600;
  border:1px solid var(--border);background:var(--surface2);color:var(--text2);
  cursor:pointer;font-family:inherit;transition:.15s;text-decoration:none;display:inline-block;
}
.tb-btn:hover{border-color:var(--primary);color:var(--primary);text-decoration:none;}

.wrap{max-width:960px;margin:0 auto;padding:26px 20px 60px;}
.hero{text-align:center;padding:10px 0 26px;}
.hero h1{font-size:27px;font-weight:800;margin:0 0 9px;letter-spacing:-.02em;}
.hero p{color:var(--text2);margin:0;font-size:14.5px;}

.card{
  background:var(--surface);border:1px solid var(--border);border-radius:16px;
  padding:22px 24px;margin-bottom:18px;box-shadow:var(--shadow);
}
.card h2{font-size:16px;font-weight:700;margin:0 0 4px;display:flex;align-items:center;gap:8px;}
.card .sub{font-size:13px;color:var(--text2);margin:0 0 16px;}

.rows{display:flex;flex-direction:column;gap:10px;}
.row{
  display:flex;align-items:center;gap:12px;padding:13px 15px;
  background:var(--surface2);border:1px solid var(--border);border-radius:12px;
}
.row .ic{font-size:19px;flex-shrink:0;width:26px;text-align:center;}
.row .lb{font-size:12.5px;color:var(--text2);flex-shrink:0;min-width:56px;}
.row .vl{
  flex:1;min-width:0;font-weight:700;font-size:15px;word-break:break-all;
  font-family:"SF Mono",Consolas,Monaco,monospace;letter-spacing:.3px;
}
.row .cp{
  flex-shrink:0;padding:5px 12px;border-radius:8px;font-size:12.5px;font-weight:600;cursor:pointer;
  border:1px solid var(--border-strong);background:var(--surface);color:var(--text2);
  font-family:inherit;transition:.15s;
}
.row .cp:hover{border-color:var(--primary);color:var(--primary);}
.row .cp.done{border-color:var(--ok);color:var(--ok);}

.qr-box{text-align:center;padding:6px 0 2px;}
.qr-box img{
  display:block;margin:0 auto;width:auto;height:auto;
  max-width:min(260px,62vw);max-height:min(260px,38vh);
  border-radius:14px;border:1px solid var(--border);cursor:zoom-in;
  transition:transform .2s,box-shadow .2s;
}
.qr-box img:hover{transform:scale(1.02);box-shadow:0 10px 30px rgba(0,0,0,.16);}
.qr-box .tip{font-size:13px;color:var(--text2);margin-top:12px;}
.qr-missing{
  max-width:min(260px,62vw);height:170px;margin:0 auto;border-radius:14px;
  border:2px dashed var(--border-strong);display:flex;align-items:center;justify-content:center;
  color:var(--text3);font-size:13px;text-align:center;padding:16px;line-height:1.7;
}

.dy{display:flex;gap:12px;flex-wrap:wrap;}
.dy-btn{
  flex:1;min-width:190px;display:flex;align-items:center;gap:12px;
  padding:15px 18px;border-radius:13px;border:1px solid var(--border);
  background:var(--surface2);color:var(--text);transition:.18s;text-decoration:none;
}
.dy-btn:hover{border-color:var(--primary);transform:translateY(-1px);text-decoration:none;}
.dy-btn .di{
  width:40px;height:40px;border-radius:11px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;font-size:20px;
  background:linear-gradient(135deg,#0f172a,#334155);color:#fff;
}
.dy-btn.live .di{background:linear-gradient(135deg,#e11d48,#f43f5e);}
.dy-btn .dt{font-weight:700;font-size:14.5px;line-height:1.35;}
.dy-btn .ds{font-size:12px;color:var(--text2);line-height:1.4;}
.dy-id{
  display:inline-block;margin-top:14px;padding:6px 14px;border-radius:9px;
  background:var(--surface2);border:1px solid var(--border);
  font-family:"SF Mono",Consolas,Monaco,monospace;font-size:13.5px;color:var(--text2);
}

.ul{margin:0;padding-left:20px;color:var(--text2);font-size:14px;}
.ul li{margin-bottom:6px;}
.notice{
  padding:12px 16px;border-radius:12px;font-size:13.5px;line-height:1.7;margin-bottom:18px;
  background:var(--info-soft);border:1px solid var(--info-border);color:var(--info-text);
}
.hours{
  display:inline-flex;align-items:center;gap:8px;padding:9px 15px;border-radius:10px;
  background:var(--warn-soft);color:var(--warn);font-size:13.5px;font-weight:600;
}
.empty{color:var(--text3);font-size:13.5px;text-align:center;padding:22px 0;}

footer{
  max-width:960px;margin:0 auto;padding:26px 20px 40px;text-align:center;
  color:var(--text3);font-size:12.5px;line-height:1.9;border-top:1px solid var(--border);
}
footer a{color:var(--text2);}

.qr-zoom{
  position:fixed;inset:0;z-index:200;display:none;flex-direction:column;
  align-items:center;justify-content:center;gap:14px;padding:20px;
  background:rgba(8,14,26,.9);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
}
.qr-zoom.show{display:flex;}
.qr-zoom img{
  display:block;width:auto;height:auto;
  max-width:min(88vw,66vh);max-height:min(88vw,66vh);
  border-radius:14px;box-shadow:0 24px 70px rgba(0,0,0,.5);
}
.qr-zoom .zc{
  position:absolute;top:16px;right:16px;width:42px;height:42px;border-radius:50%;border:none;
  background:rgba(255,255,255,.16);color:#fff;font-size:24px;line-height:1;cursor:pointer;
}
.qr-zoom .zt{color:rgba(255,255,255,.72);font-size:13px;}

.toast-wrap{position:fixed;top:22px;right:22px;z-index:300;display:flex;flex-direction:column;gap:9px;align-items:flex-end;pointer-events:none;max-width:min(400px,calc(100vw - 32px));}
.toast{
  padding:12px 20px;border-radius:12px;font-size:14px;font-weight:700;color:#fff;
  box-shadow:0 12px 34px rgba(0,0,0,.26);animation:tin .25s ease-out;
  background:linear-gradient(135deg,#10b981,#059669);
  white-space:pre;width:max-content;max-width:none;
}
@keyframes tin{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}

*{scrollbar-width:thin;scrollbar-color:var(--border-strong) transparent;}
::-webkit-scrollbar{width:12px;height:12px;}
::-webkit-scrollbar-track,::-webkit-scrollbar-corner{background:transparent;}
::-webkit-scrollbar-thumb{background:var(--border-strong);border:3px solid transparent;background-clip:content-box;border-radius:99px;}
::-webkit-scrollbar-thumb:hover{background:var(--text3);background-clip:content-box;}

@media (max-width:640px){
  .hero h1{font-size:22px;}
  .card{padding:18px 16px;border-radius:14px;}
  .row{flex-wrap:wrap;}
  .row .vl{flex:1 0 100%;order:3;padding-left:38px;font-size:14.5px;}
  .dy-btn{min-width:100%;}
}
</style>
</head>
<body>

<div class="topbar">
  <div class="topbar-inner">
    <div class="brand">
      <span class="dot">🛒</span>
      <span><?php echo h($brand); ?></span>
      <span class="badge">联系方式</span>
    </div>
    <div class="spacer"></div>
    <a class="tb-btn" href="./index.php">🛍 去下单</a>
    <a class="tb-btn" href="./query.php"><?php echo h($tbQuery); ?></a>
    <button type="button" class="tb-btn" id="themeBtn" onclick="switchTheme()">🌙 深色</button>
  </div>
</div>

<div class="wrap">

  <div class="hero">
    <h1><?php echo h($cTitle); ?></h1>
    <?php if ($cSub !== ''): ?><p><?php echo h($cSub); ?></p><?php endif; ?>
  </div>

  <?php if ($notice !== ''): ?>
    <div class="notice"><?php echo nl2br(h($notice)); ?></div>
  <?php endif; ?>

  <?php if ($hasContact): ?>
  <div class="card">
    <h2>📇 联系方式</h2>
    <p class="sub">点右侧按钮即可复制</p>
    <div class="rows">
      <?php
      $items = [
          ['wechat', '微信号', $wechat],
          ['qq',     'QQ',     $qq],
          ['phone',  '电话',   $phone],
          ['email',  '邮箱',   $email],
      ];
      foreach ($items as $it):
          if ($it[2] === '') continue;
      ?>
        <div class="row">
          <span class="ic"><?php echo contact_icon($it[0]); ?></span>
          <span class="lb"><?php echo h($it[1]); ?></span>
          <span class="vl" id="v-<?php echo h($it[0]); ?>"><?php echo h($it[2]); ?></span>
          <button type="button" class="cp" onclick="copyVal('<?php echo h($it[2]); ?>', this)">复制</button>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($hours !== ''): ?>
      <div style="margin-top:16px;"><span class="hours">🕒 <?php echo h($hours); ?></span></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="card">
    <h2>💬 微信二维码</h2>
    <p class="sub"><?php echo h($qrTip); ?></p>
    <div class="qr-box">
      <?php if ($qrUrl !== ''): ?>
        <img src="<?php echo h($qrUrl); ?>" alt="微信二维码" title="点击放大"
             onclick="openZoom()" referrerpolicy="no-referrer"
             onerror="this.onerror=null;this.outerHTML='&lt;div class=&quot;qr-missing&quot;&gt;二维码加载失败&lt;br&gt;请用上方的微信号添加&lt;/div&gt;'">
      <?php else: ?>
        <div class="qr-missing">暂未上传二维码<br>请用上方的微信号添加</div>
      <?php endif; ?>
      <div class="tip"><?php echo $qrUrl !== '' ? '🔍 点击二维码可放大' : ''; ?></div>
    </div>
  </div>

  <?php if ($hasDouyin): ?>
  <div class="card">
    <h2>🎵 抖音</h2>
    <p class="sub">关注主页看作品，直播间可以直接聊</p>
    <div class="dy">
      <?php if ($dyHome !== ''): ?>
        <a class="dy-btn" href="<?php echo h($dyHome); ?>" target="_blank" rel="noopener noreferrer">
          <span class="di">🎵</span>
          <span>
            <span class="dt">抖音主页</span><br>
            <span class="ds">看作品 · 关注我</span>
          </span>
        </a>
      <?php endif; ?>
      <?php if ($dyLive !== ''): ?>
        <a class="dy-btn live" href="<?php echo h($dyLive); ?>" target="_blank" rel="noopener noreferrer">
          <span class="di">📺</span>
          <span>
            <span class="dt">抖音直播间</span><br>
            <span class="ds">在线答疑 · 实时沟通</span>
          </span>
        </a>
      <?php endif; ?>
    </div>
    <?php if ($dyId !== ''): ?>
      <div><span class="dy-id">抖音号：<?php echo h($dyId); ?></span></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($extraLines): ?>
  <div class="card">
    <h2>📌 补充说明</h2>
    <ul class="ul">
      <?php foreach ($extraLines as $ln): ?>
        <li><?php echo h($ln); ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

</div>

<footer>
  <?php echo h($cTitle); ?> · <?php echo h($brand); ?><br>
  <?php if ($ftNote !== ''): ?><?php echo nl2br(h($ftNote)); ?><br><?php endif; ?>
  <a href="./index.php">在线下单</a> ·
  <a href="./query.php">订单查询</a>
</footer>

<div class="qr-zoom" id="qrZoom" onclick="if(event.target===this)closeZoom()">
  <button class="zc" onclick="closeZoom()" aria-label="关闭">×</button>
  <img id="qrZoomImg" src="" alt="微信二维码" referrerpolicy="no-referrer">
  <div class="zt"><?php echo h($qrTip); ?></div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>

function switchTheme(){
  var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', cur);
  try{ localStorage.setItem('shopTheme', cur); }catch(e){}
  syncThemeBtn();
}
function syncThemeBtn(){
  var b = document.getElementById('themeBtn');
  if (!b) return;
  b.textContent = document.documentElement.getAttribute('data-theme') === 'dark' ? '☀️ 浅色' : '🌙 深色';
}
syncThemeBtn();

function toast(msg){
  var w = document.getElementById('toastWrap');
  if (!w) return;
  var d = document.createElement('div');
  d.className = 'toast';
  d.textContent = msg;
  w.appendChild(d);
  setTimeout(function(){ d.remove(); }, 1600);
}
function copyVal(text, btn){
  var done = function(){
    if (btn){ btn.textContent = '✓ 已复制'; btn.classList.add('done');
      setTimeout(function(){ btn.textContent = '复制'; btn.classList.remove('done'); }, 1500); }
    toast('已复制：' + text);
  };
  var fail = function(){
    var ta = document.createElement('textarea');
    ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select();
    try{ document.execCommand('copy'); done(); }catch(e){ toast('复制失败，请手动选择'); }
    ta.remove();
  };
  if (navigator.clipboard && window.isSecureContext){
    navigator.clipboard.writeText(text).then(done).catch(fail);
  } else { fail(); }
}

function openZoom(){
  var img = document.querySelector('.qr-box img');
  if (!img) return;
  document.getElementById('qrZoomImg').src = img.src;
  document.getElementById('qrZoom').classList.add('show');
}
function closeZoom(){ document.getElementById('qrZoom').classList.remove('show'); }
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeZoom(); });
</script>
</body>
</html>
