<?php

date_default_timezone_set('Asia/Shanghai');

define('IN_CRYPT', true);
define('IN_SHOP', true);
require_once __DIR__ . '/inc/crypt.php';
require_once __DIR__ . '/inc/shop_store.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$cfg      = shop_config();
$cats     = shop_categories();
$products = shop_products_public();
$dbReady  = shop_db_available();
$payCfg   = $cfg['pay'];
$formCfg  = $cfg['form'];

$catIcon = [
    'software' => '🧩', 'maintain' => '🧹', 'dev' => '🌐',
    'physical' => '📦', 'other' => '🛠️',
];

$clientProducts = [];
foreach ($products as $p) {
    $clientProducts[] = [
        'id'         => (string)($p['id'] ?? ''),
        'name'       => (string)($p['name'] ?? ''),
        'category'   => (string)($p['category'] ?? ''),
        'cat_name'   => shop_category_name((string)($p['category'] ?? '')),
        'tags'       => array_values(array_filter(array_map('strval', (array)($p['tags'] ?? [])))),
        'desc'       => (string)($p['desc'] ?? ''),
        'image'      => (string)($p['image'] ?? ''),
        'icon'       => (string)($p['icon'] ?? ''),
        'price'      => shop_price($p['price'] ?? 0),
        'unit'       => (string)($p['unit'] ?? '次'),
        'stock'      => (int)($p['stock'] ?? -1),
        'allow_now'  => !empty($p['allow_now']),
        'allow_booking' => !empty($p['allow_booking']) && !empty($formCfg['enable_booking']),
    ];
}

$clientCats = [];
foreach ($cats as $c) {
    $clientCats[] = [
        'key'  => (string)($c['key'] ?? ''),
        'name' => (string)($c['name'] ?? ''),

        'icon' => (string)($c['icon'] ?? ($catIcon[$c['key'] ?? ''] ?? '🛠️')),

        'desc' => (string)($c['desc'] ?? ''),
    ];
}

$clientConfig = [
    'form' => [
        'require_wechat'    => !empty($formCfg['require_wechat']),
        'require_phone'     => !empty($formCfg['require_phone']),
        'require_book_time' => !empty($formCfg['require_book_time']),
        'enable_remark'     => !empty($formCfg['enable_remark']),
        'enable_booking'    => !empty($formCfg['enable_booking']),
        'notice'            => (string)$formCfg['notice'],
    ],
    'pay' => [
        'qr_mode'            => ((string)($payCfg['qr_mode'] ?? 'aggregate') === 'split') ? 'split' : 'aggregate',
        'qr_url'             => (string)$payCfg['qr_url'],
        'qr_wechat'          => (string)($payCfg['qr_wechat'] ?? ''),
        'qr_alipay'          => (string)($payCfg['qr_alipay'] ?? ''),
        'qr_tip'             => (string)$payCfg['qr_tip'],
        'payee_name'         => (string)$payCfg['payee_name'],
        'code_mode'          => (string)$payCfg['code_mode'],
        'allow_regenerate'   => !empty($payCfg['allow_regenerate']),
        'contact_admin_url'  => (string)$payCfg['contact_admin_url'],
        'contact_admin_text' => (string)$payCfg['contact_admin_text'],
        'tips'               => (string)$payCfg['tips'],

        'expire_minutes'     => (int)($payCfg['unpaid_expire_minutes'] ?? 10),
    ],
    'dbReady'       => $dbReady,
    'dbHint'        => $dbReady ? '' : shop_db_unavailable_hint(),
    'queryEnable'   => !empty($cfg['query']['enable']),
];
?>
<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($cfg['page_title']); ?> | 在线商城</title>
<meta name="description" content="<?php echo htmlspecialchars($cfg['page_subtitle']); ?>">
<link rel="icon" href="https://vip.123pan.cn/1814921676/yk6baz03t0n000dcy3hrlqc16aw5wollDIYPAdrvDIQ0ApxwAwe1Aa==.png" type="image/png">
<script>(function(){try{var t=localStorage.getItem('shopTheme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
<style>
:root{
  --bg:#f0f6fb; --bg2:#e6f2f8; --card:#ffffff; --text:#1e293b; --muted:#64748b;
  --line:#e2e8f0; --brand:#3b82f6; --brand2:#14b8a6; --soft:#f8fafc; --danger:#dc2626;
  --shadow:0 4px 20px rgba(30,58,138,.07); --shadow-h:0 12px 32px rgba(30,58,138,.14);
}
html[data-theme="dark"]{
  --bg:#0b1220; --bg2:#0f172a; --card:#111c31; --text:#e5edf7; --muted:#93a4bd;
  --line:#1e2b45; --brand:#3b82f6; --brand2:#14b8a6; --soft:#0e1a2e; --danger:#f87171;
  --shadow:0 4px 20px rgba(0,0,0,.35); --shadow-h:0 12px 32px rgba(0,0,0,.5);
}
*{margin:0;padding:0;box-sizing:border-box;}
body{
  font-family:"Microsoft YaHei","PingFang SC",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
  background:linear-gradient(135deg,var(--bg) 0%,var(--bg2) 100%);
  color:var(--text); min-height:100vh; padding-bottom:70px; -webkit-font-smoothing:antialiased;
}
a{color:inherit;text-decoration:none;}

.topbar{
  position:sticky;top:0;z-index:50;background:var(--card);
  border-bottom:1px solid var(--line);
}
.topbar-inner{max-width:1280px;margin:0 auto;padding:12px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;min-width:0;}
.topbar-inner > *{min-width:0;}
.brand{display:flex;align-items:center;gap:9px;font-weight:700;font-size:17px;letter-spacing:.3px;}
.brand .dot{width:26px;height:26px;border-radius:8px;background:linear-gradient(135deg,var(--brand),var(--brand2));display:flex;align-items:center;justify-content:center;font-size:14px;}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px;}
.tb-link{
  padding:7px 14px;border-radius:9px;font-size:13.5px;color:var(--muted);
  border:1px solid var(--line);transition:.2s;white-space:nowrap;
}
.tb-link:hover{color:var(--brand);border-color:var(--brand);background:var(--soft);}
.icon-btn{
  width:36px;height:36px;border-radius:9px;border:1px solid var(--line);background:transparent;
  color:var(--muted);cursor:pointer;font-size:15px;display:flex;align-items:center;justify-content:center;transition:.2s;
}
.icon-btn:hover{color:var(--brand);border-color:var(--brand);}

.wrap{max-width:1280px;margin:0 auto;padding:0 20px;}
.hero{text-align:center;padding:38px 20px 26px;}
.hero h1{font-size:30px;font-weight:700;letter-spacing:.5px;margin-bottom:10px;}
.hero p{color:var(--muted);font-size:15px;line-height:1.7;max-width:760px;margin:0 auto;}
.notice{
  margin:22px auto 0;max-width:1000px;padding:13px 18px;border-radius:12px;font-size:13.5px;line-height:1.7;
  background:linear-gradient(135deg,rgba(59,130,246,.10),rgba(20,184,166,.10));
  border:1px solid rgba(59,130,246,.22);color:var(--text);text-align:left;
}
.warn-box{
  margin:18px auto 0;max-width:1000px;padding:13px 18px;border-radius:12px;font-size:13.5px;line-height:1.7;
  background:#fef2f2;border:1px solid #fecaca;color:#991b1b;text-align:left;
}
html[data-theme="dark"] .warn-box{background:#3b1215;border-color:#7f1d1d;color:#fca5a5;}

.toolbar{display:flex;flex-direction:column;align-items:stretch;gap:12px;margin:20px 0 16px;}
.pills{display:flex;flex-wrap:wrap;gap:8px;}
.pill{
  padding:7px 15px;border-radius:20px;font-size:13px;cursor:pointer;user-select:none;
  border:1px solid var(--line);background:var(--card);color:var(--muted);transition:.2s;white-space:nowrap;
}
.pill:hover{border-color:var(--brand);color:var(--brand);}
.pill.active{background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;border-color:transparent;box-shadow:0 4px 14px rgba(59,130,246,.3);}
.search-row{display:flex;gap:10px;align-items:stretch;}
.search-row .search-box{flex:1 1 auto;width:auto;min-width:0;}
.cat-entry{
  flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;
  padding:0 20px;border-radius:11px;border:1px solid transparent;
  background:linear-gradient(135deg,var(--brand),var(--brand2));
  color:#fff;font-size:13.5px;font-weight:700;letter-spacing:.5px;
  text-decoration:none;white-space:nowrap;transition:.2s;
  box-shadow:0 4px 14px rgba(59,130,246,.25);
}
.cat-entry:hover{filter:brightness(1.07);color:#fff;box-shadow:0 6px 18px rgba(59,130,246,.33);}
.cat-entry:active{transform:scale(.97);}
.search-box{position:relative;width:100%;}
.search-box input{
  width:100%;padding:10px 16px 10px 38px;border-radius:11px;border:1px solid var(--line);
  background:var(--card);color:var(--text);font-size:14px;outline:none;transition:.2s;font-family:inherit;
}
.search-box input:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(59,130,246,.13);}
.search-box .si{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;}

.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px;margin-bottom:32px;}
.card{
  background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px 16px 14px;
  display:flex;flex-direction:column;transition:.25s;box-shadow:var(--shadow);position:relative;overflow:hidden;
}
.card:hover{transform:translateY(-3px);box-shadow:var(--shadow-h);border-color:rgba(59,130,246,.35);}
.card .cat-badge{
  position:absolute;top:11px;left:11px;font-size:11px;padding:2px 9px;border-radius:6px;
  background:rgba(59,130,246,.12);color:var(--brand);font-weight:600;letter-spacing:.2px;
}
.card .thumb{height:68px;display:flex;align-items:center;justify-content:center;margin:12px 0 10px;}
.card .thumb img{max-height:68px;max-width:106px;object-fit:contain;border-radius:9px;}
.card .thumb .emoji{font-size:38px;line-height:1;}
.card h3{font-size:16px;font-weight:700;text-align:center;margin-bottom:8px;letter-spacing:.2px;}
.card .desc{color:var(--muted);font-size:12.5px;line-height:1.6;text-align:center;flex:1;margin-bottom:10px;}
.tags{display:flex;flex-wrap:wrap;gap:5px;justify-content:center;margin-bottom:10px;}
.tag{font-size:11px;padding:2px 9px;border-radius:11px;background:var(--soft);color:var(--muted);border:1px solid var(--line);}
.price-row{text-align:center;margin-bottom:11px;}
.price-row .price{font-size:20px;font-weight:700;color:var(--brand);letter-spacing:-.3px;}
.price-row .unit{font-size:12px;color:var(--muted);margin-left:2px;}
.stock-out{font-size:11.5px;color:var(--danger);display:block;margin-top:3px;}
.card-actions{display:flex;gap:8px;}
.btn-card{
  flex:1;padding:8px 10px;border-radius:9px;font-size:12.5px;font-weight:600;cursor:pointer;
  border:1px solid var(--line);background:var(--card);color:var(--text);transition:.2s;font-family:inherit;
  display:flex;align-items:center;justify-content:center;gap:5px;white-space:nowrap;
}
.btn-card:hover{border-color:var(--brand);color:var(--brand);}
.btn-card.buy{background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;border-color:transparent;}
.btn-card.buy:hover{filter:brightness(1.08);color:#fff;}
.btn-card:disabled{opacity:.45;cursor:not-allowed;filter:grayscale(.5);}

.empty-state{
  grid-column:1/-1;text-align:center;padding:56px 20px;color:var(--muted);
  background:var(--card);border:1px dashed var(--line);border-radius:16px;
}
.empty-state .big{font-size:44px;margin-bottom:14px;}
.footer{text-align:center;color:var(--muted);font-size:13px;padding:26px 20px;border-top:1px solid var(--line);line-height:1.9;}

.mask{
  position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);
  z-index:200;display:none;align-items:center;justify-content:center;padding:18px;overflow-y:auto;
}
.mask.show{display:flex;}
.modal{
  background:var(--card);border-radius:18px;width:100%;max-width:560px;box-shadow:0 28px 70px rgba(0,0,0,.35);
  animation:pop .22s ease-out;max-height:calc(100vh - 36px);display:flex;flex-direction:column;border:1px solid var(--line);
}
.modal.pay{max-width:470px;}
@keyframes pop{from{opacity:0;transform:translateY(-14px) scale(.97);}to{opacity:1;transform:none;}}
.modal-head{
  display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--line);flex-shrink:0;
}
.modal-head h3{font-size:17px;font-weight:700;display:flex;align-items:center;gap:8px;}
.modal-close{
  width:32px;height:32px;border-radius:8px;border:none;background:var(--soft);color:var(--muted);
  cursor:pointer;font-size:17px;line-height:1;transition:.2s;
}
.modal-close:hover{background:#fee2e2;color:#dc2626;}
.modal-body{padding:16px 20px;overflow-y:auto;flex:1 1 auto;min-height:0;}

:root{
  --sb-w: 12px;
  --sb-thumb: rgba(100,120,160,.30);
  --sb-thumb-hover: rgba(100,120,160,.52);
}
html[data-theme="dark"]{
  --sb-thumb: rgba(157,176,208,.24);
  --sb-thumb-hover: rgba(157,176,208,.46);
}

*{ scrollbar-width:thin; scrollbar-color:var(--sb-thumb) transparent; }

::-webkit-scrollbar{ width:var(--sb-w); height:var(--sb-w); }
::-webkit-scrollbar-track,
::-webkit-scrollbar-corner{ background:transparent; }
::-webkit-scrollbar-thumb{
  background:var(--sb-thumb);
  border:3px solid transparent; background-clip:content-box;
  border-radius:99px;
}
::-webkit-scrollbar-thumb:hover{ background:var(--sb-thumb-hover); background-clip:content-box; }
::-webkit-scrollbar-thumb:active{ background:var(--sb-thumb-hover); background-clip:content-box; }

.modal-body{ padding-right:14px; }
.modal-foot{padding:12px 20px;border-top:1px solid var(--line);display:flex;gap:10px;flex-shrink:0;}

.fg{margin-bottom:12px;}
.fg label{display:block;font-size:13.5px;font-weight:600;margin-bottom:5px;color:var(--text);}
.fg label .req{color:#ef4444;margin-left:3px;}
.fg label .hint{font-weight:400;color:var(--muted);font-size:12px;}
.fg input[type=text],.fg input[type=tel],.fg input[type=date],.fg textarea,.fg select{
  width:100%;padding:9px 12px;border-radius:9px;border:1px solid var(--line);background:var(--soft);
  color:var(--text);font-size:14px;outline:none;transition:.2s;font-family:inherit;
}
.fg textarea{resize:vertical;min-height:56px;line-height:1.6;}
.fg input:focus,.fg textarea:focus,.fg select:focus{border-color:var(--brand);background:var(--card);box-shadow:0 0 0 3px rgba(59,130,246,.13);}
.fg .row2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}

.fg2{display:grid;grid-template-columns:1fr 1fr;gap:0 12px;}
.fg .err{color:#ef4444;font-size:12.5px;margin-top:5px;display:none;}
.fg.invalid input,.fg.invalid select,.fg.invalid textarea{border-color:#ef4444;}
.fg.invalid .err{display:block;}
.seg{display:flex;gap:8px;}
.seg button{
  flex:1;padding:9px 6px;border-radius:9px;border:1px solid var(--line);background:var(--soft);color:var(--muted);
  font-size:12.5px;cursor:pointer;transition:.2s;font-family:inherit;font-weight:600;white-space:nowrap;
}
.seg button.active{background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;border-color:transparent;}
.honeypot{position:absolute;left:-9999px;opacity:0;height:0;overflow:hidden;}

.sum-cap{
  display:flex; align-items:center; gap:6px; margin:0 0 8px; padding:0 2px;
  font-size:12px; font-weight:700; color:var(--sum-cap); letter-spacing:.4px;
}

.summary{
  background:var(--sum-track); border:1px solid var(--sum-line);
  border-radius:12px; padding:10px 10px 8px; margin-bottom:12px;
}
.summary .line{
  display:flex; justify-content:space-between; gap:10px; align-items:baseline;
  background:var(--sum-row); border:1px solid var(--sum-line); border-radius:9px;
  font-size:13.5px; padding:8px 11px; margin-bottom:6px; color:var(--r-ink);
}
.summary .line.total{
  background:transparent; border:none; border-top:1px solid var(--sum-line);
  border-radius:0; margin:9px 0 0; padding:9px 2px 2px;
  font-size:14.5px; font-weight:700; color:var(--r-ink);
}
.summary .line.total .amt{color:var(--r-blue); font-size:20px;}
.notice-box{
  background:rgba(59,130,246,.07);border:1px solid rgba(59,130,246,.2);border-radius:11px;
  padding:9px 12px;font-size:12px;line-height:1.65;color:var(--muted);margin-bottom:2px;
}
.btn{
  padding:12px 18px;border-radius:11px;border:none;font-size:14.5px;font-weight:600;cursor:pointer;
  font-family:inherit;transition:.2s;display:flex;align-items:center;justify-content:center;gap:7px;
}
.btn:disabled{opacity:.55;cursor:not-allowed;}
.btn-primary{flex:1;background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;box-shadow:0 6px 18px rgba(59,130,246,.3);}
.btn-primary:hover:not(:disabled){filter:brightness(1.08);transform:translateY(-1px);}
.btn-ghost{background:var(--soft);color:var(--text);border:1px solid var(--line);}
.btn-ghost:hover{border-color:var(--brand);color:var(--brand);}
.btn-success{background:linear-gradient(135deg,#10b981,#059669);color:#fff;box-shadow:0 6px 18px rgba(16,185,129,.3);}
.btn-success:hover:not(:disabled){filter:brightness(1.07);}

.pm-label{font-size:12.5px;color:var(--muted);text-align:center;margin:0 0 8px;}
.pay-methods{display:flex;gap:7px;justify-content:center;flex-wrap:wrap;margin:0 0 13px;}
.pm-btn{
  font:inherit;font-size:12.5px;font-weight:600;cursor:pointer;white-space:nowrap;
  display:inline-flex;align-items:center;gap:4px;
  padding:6px 15px;border-radius:20px;transition:.15s;line-height:1.5;
  border:1px solid var(--line);background:var(--shell);color:var(--ink2);
}
.pm-btn:hover{border-color:var(--brand);color:var(--brand);}
.pm-btn.on{background:var(--brand);border-color:var(--brand);color:#fff;box-shadow:0 2px 8px rgba(59,130,246,.25);}
.qr-zone{text-align:center;padding:6px 0 16px;}

.qr-zone img{
  display:block;margin:0 auto;width:auto;height:auto;

  max-width:min(240px,58vw);max-height:min(240px,34vh);
  border-radius:12px;border:1px solid var(--line);
}
.qr-zone .qr-missing{
  max-width:min(240px,58vw);height:150px;margin:0 auto;border-radius:12px;border:2px dashed var(--line);
  display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:13px;text-align:center;padding:16px;line-height:1.7;
}
.qr-tip{font-size:12.5px;color:var(--muted);margin-top:9px;}
.qr-expire{
  display:inline-block;margin-top:8px;padding:5px 12px;border-radius:9px;
  font-size:12.5px;line-height:1.6;color:#92400e;
  background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.35);
}
.qr-expire b{color:#b45309;}
html[data-theme="dark"] .qr-expire{color:#fcd34d;background:rgba(245,158,11,.16);border-color:rgba(245,158,11,.4);}
html[data-theme="dark"] .qr-expire b{color:#fde68a;}
.code-zone{
  border:1px dashed rgba(59,130,246,.4);border-radius:10px;padding:8px 11px;margin-bottom:10px;
  background:rgba(59,130,246,.05);
}
.code-zone .cz-title{font-size:11.5px;color:var(--muted);line-height:1.4;margin-bottom:5px;display:flex;gap:5px;align-items:center;}
.code-zone .cz-main{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;}
.code-big{
  font-size:24px;font-weight:800;letter-spacing:5px;color:var(--brand);
  font-family:"SF Mono",Consolas,Monaco,monospace;line-height:1.1;padding-left:4px;word-break:break-all;
}
.code-wechat{font-size:14px;font-weight:700;color:var(--brand);word-break:break-all;}
.cz-btns{display:flex;flex-direction:row;gap:6px;flex-shrink:0;flex-wrap:wrap;justify-content:flex-end;}
.mini-btn{
  padding:5px 11px;border-radius:7px;border:1px solid var(--line);background:var(--card);color:var(--muted);font-size:12.5px;
  font-size:12px;cursor:pointer;font-family:inherit;transition:.2s;white-space:nowrap;
}
.mini-btn:hover{border-color:var(--brand);color:var(--brand);}
.info-rows{border-top:1px solid var(--line);padding-top:10px;margin-bottom:10px;}
.info-row{display:flex;justify-content:space-between;gap:14px;font-size:13.5px;padding:4px 0;}
.info-row .k{color:var(--muted);flex-shrink:0;}
.info-row .v{font-weight:600;text-align:right;word-break:break-all;}
.info-row.items .v{font-weight:500;}
.pay-tips{font-size:12px;color:var(--muted);line-height:1.65;text-align:center;margin:10px 0 2px;}
.status-banner{
  border-radius:11px;padding:10px 13px;font-size:13.5px;line-height:1.65;margin-bottom:11px;display:flex;gap:9px;align-items:flex-start;
}
.status-banner.ok{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
.status-banner.wait{background:#cffafe;color:#0e7490;border:1px solid #67e8f9;}
.status-banner.warn{background:#fef3c7;color:#92400e;border:1px solid #fcd34d;}
html[data-theme="dark"] .status-banner.ok{background:#052e21;color:#6ee7b7;border-color:#065f46;}
html[data-theme="dark"] .status-banner.wait{background:#082f38;color:#67e8f9;border-color:#155e75;}
html[data-theme="dark"] .status-banner.warn{background:#3b2a06;color:#fcd34d;border-color:#78350f;}

.toast-wrap{
  position:fixed;top:72px;right:16px;z-index:400;display:flex;flex-direction:column;gap:9px;
  align-items:flex-end;pointer-events:none;max-width:min(420px,calc(100vw - 32px));
}
.toast{
  padding:13px 22px;border-radius:12px;font-size:14.5px;font-weight:700;color:#fff;
  box-shadow:0 12px 34px rgba(0,0,0,.26);animation:tin .3s ease-out;line-height:1.6;
  white-space:pre;width:max-content;max-width:none;
}
.toast.ok{background:linear-gradient(135deg,#10b981,#059669);}
.toast.err{background:linear-gradient(135deg,#ef4444,#dc2626);}
.toast.info{background:linear-gradient(135deg,#3b82f6,#2563eb);}
@keyframes tin{from{opacity:0;transform:translateX(12px);}to{opacity:1;transform:none;}}

.float-order{
  position:fixed;left:20px;bottom:20px;z-index:150;display:none;align-items:center;gap:10px;
  background:var(--card);border:1px solid var(--line);border-radius:13px;padding:11px 16px;
  box-shadow:0 10px 30px rgba(0,0,0,.16);font-size:13px;cursor:pointer;transition:.2s;
}
.float-order:hover{border-color:var(--brand);transform:translateY(-2px);}
.float-order .fo-no{font-weight:700;color:var(--brand);font-family:Consolas,monospace;font-size:12.5px;}
.float-order .fo-x{color:var(--muted);font-size:15px;padding:0 2px;}
.float-order .fo-x:hover{color:#dc2626;}

@media (max-width:640px){
  .hero h1{font-size:23px;} .hero{padding:26px 12px 18px;}
  .grid{grid-template-columns:1fr;gap:16px;}
  .toolbar{flex-direction:column;align-items:stretch;}
  .search-box{width:100%;}
  .search-row .search-box{width:auto;}
  .cat-entry{padding:0 16px;}
  .code-big{font-size:22px;letter-spacing:4px;}
  .fg2{grid-template-columns:1fr;}
  .modal-body{padding:16px;}
  .modal-head{padding:15px 16px;} .modal-foot{padding:14px 16px;}
  .float-order{left:12px;bottom:12px;padding:9px 13px;}

  .toast-wrap{top:76px;left:10px;right:10px;max-width:none;align-items:stretch;}
  .toast{text-align:center;padding:12px 16px;}
  .brand span.bt{display:none;}
}

#cfmMask{z-index:300;}
.qr-zoomable{cursor:zoom-in;transition:transform .2s,box-shadow .2s;}
.qr-zoomable:hover{transform:scale(1.04);box-shadow:0 10px 26px rgba(0,0,0,.18);}
.qr-zoom{
  position:fixed;inset:0;z-index:250;display:none;flex-direction:column;align-items:center;justify-content:center;
  gap:14px;padding:20px;background:rgba(8,14,26,.9);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
}
.qr-zoom.show{display:flex;}

.qr-zoom img{
  display:block;width:auto;height:auto;
  max-width:min(88vw,66vh);max-height:min(88vw,66vh);
  border-radius:14px;box-shadow:0 24px 70px rgba(0,0,0,.5);
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
  .qr-zoom img{max-width:92vw;max-height:62vh;}
  .qr-zoom .qz-code{font-size:24px;letter-spacing:6px;padding:5px 20px;}
}

:root{
  --r-bg:#eef1f7;
  --r-shell:#ffffff;
  --r-card:#e9eefb;
  --r-line:#d7e0f5;
  --r-inner:#f2f5fd;
  --r-pill:#e2e8f5;
  --r-ink:#1c2942;
  --r-ink2:#5b6b8c;
  --r-blue:#2563eb;
  --r-green:#17803d;

  --sum-track:#e9eefb;
  --sum-row:#ffffff;
  --sum-line:#d3ddf2;
  --sum-cap:#6478a0;
}
html[data-theme="dark"]{
  --r-bg:#0b1220; --r-shell:#111c31; --r-card:#16233c; --r-line:#24334f;
  --r-inner:#0f1a2e; --r-pill:#1b2942; --r-ink:#e5edf7; --r-ink2:#93a4bd;
  --r-blue:#60a5fa; --r-green:#34d399;

  --sum-track:#182742;
  --sum-row:#24365a;
  --sum-line:#38507f;
  --sum-cap:#9db0d0;
}
body{ background:var(--r-bg); background-image:none; color:var(--r-ink); }
.wrap{
  max-width:1180px; background:var(--r-shell); border-radius:24px;

  padding:26px 24px 30px; margin:20px auto 24px;
  box-shadow:0 10px 34px rgba(28,41,66,.07);
}
html[data-theme="dark"] .wrap{ box-shadow:0 10px 34px rgba(0,0,0,.4); }

.hero{ padding:8px 16px 20px; border-bottom:1px solid var(--r-line); margin-bottom:20px; }
.hero h1{ font-size:28px; font-weight:800; color:var(--r-ink); letter-spacing:.2px; }
.hero p{ color:var(--r-ink2); font-size:14.5px; }
.notice{ background:var(--r-inner); border:1px solid var(--r-line); color:var(--r-ink2); }

.crumb{
  display:inline-flex; align-items:center; gap:6px; margin:0 0 14px;
  font-size:13.5px; font-weight:600; color:var(--r-ink2);
  background:var(--r-inner); border-radius:999px; padding:7px 15px;
}
.crumb:hover{ color:var(--r-blue); }

.cat-hero{ background:var(--r-card); border:1px solid var(--r-line); border-radius:16px; padding:16px 18px; margin-bottom:18px; }
.cat-hero .top{ display:flex; align-items:center; gap:10px; margin-bottom:10px; }
.cat-hero h1{ flex:1; margin:0; font-size:20px; font-weight:800; color:var(--r-ink); }

.cat-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(330px,1fr)); gap:16px; margin-bottom:18px; }
.cat-card{
  display:flex; flex-direction:column; padding:16px 16px 14px;
  background:var(--r-card); border:1px solid var(--r-line); border-radius:16px;
}
.cat-head{ display:flex; align-items:center; gap:10px; margin-bottom:11px; }
.cat-ico{ font-size:20px; line-height:1; }
.cat-head h3{ flex:1; margin:0; font-size:17px; font-weight:800; color:var(--r-ink); text-align:left; }
.cat-count{
  font-size:12.5px; font-weight:700; color:var(--r-ink);
  background:var(--r-pill); border-radius:999px; padding:3px 12px; white-space:nowrap;
}
.cat-desc{
  background:var(--r-inner); border-radius:11px; padding:11px 13px; margin-bottom:11px;
  font-size:13px; line-height:1.65; color:var(--r-ink2); white-space:pre-line;
}
.cat-items{ display:flex; flex-direction:column; gap:8px; flex:1; }
.prod-row{
  display:flex; align-items:center; gap:10px; width:100%; text-align:left; cursor:pointer;
  font:inherit; color:var(--r-ink);
  background:var(--r-inner); border:1px solid transparent; border-radius:10px; padding:10px 12px;
  transition:background .15s, border-color .15s;
}
.prod-row:hover{ border-color:var(--r-blue); background:var(--r-shell); }
.prod-row:disabled{ opacity:.5; cursor:not-allowed; }
.prod-row .dot{ width:15px; height:15px; border-radius:4px; border:1.5px solid #b9c6e2; flex:0 0 auto; }
html[data-theme="dark"] .prod-row .dot{ border-color:#4a5b7d; }
.prod-row .nm{ flex:1; font-size:14px; font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.prod-row .pr{ font-size:13px; font-weight:700; background:var(--r-pill); border-radius:999px; padding:3px 12px; white-space:nowrap; }
.prod-row .out{ font-size:12px; color:var(--r-ink2); white-space:nowrap; }
.cat-more{
  margin-top:12px; padding:9px; text-align:center; border-radius:10px;
  font-size:13.5px; font-weight:700; color:var(--r-blue); background:var(--r-inner);
}
.cat-more:hover{ background:var(--r-pill); }

.grid{ grid-template-columns:repeat(auto-fill,minmax(255px,1fr)); gap:16px; }
.card{ background:var(--r-card); border:1px solid var(--r-line); border-radius:16px; box-shadow:none; }
.card:hover{ transform:translateY(-2px); border-color:var(--r-blue); box-shadow:0 10px 26px rgba(28,41,66,.12); }
html[data-theme="dark"] .card:hover{ box-shadow:0 10px 26px rgba(0,0,0,.45); }
.card h3{ text-align:left; color:var(--r-ink); }
.card .desc{ text-align:left; color:var(--r-ink2); }
.cat-badge{ background:var(--r-pill); color:var(--r-ink); font-weight:700; }
.price-row{ text-align:left; }
.price-row .price{ color:var(--r-blue); }
.price-row .unit{ color:var(--r-ink2); }
.thumb{ background:var(--r-inner); }
.tags .tag{ background:var(--r-pill); color:var(--r-ink2); }
.btn-card{ border-radius:10px; font-weight:700; }
.btn-card.buy{ background:var(--r-green); border-color:transparent; color:#fff; }
.btn-card.buy:hover{ filter:brightness(1.08); color:#fff; }
.footer{ color:var(--r-ink2); border-top:1px solid var(--r-line); }
.empty-state{ background:var(--r-inner); border:1px dashed var(--r-line); border-radius:16px; color:var(--r-ink2); }

@media (max-width:900px){
  .cat-grid{ grid-template-columns:1fr; }
}
@media (max-width:640px){
  .wrap{ border-radius:0; margin-top:0; padding:16px 13px 24px; }
  .hero{ padding:4px 0 16px; }
  .hero h1{ font-size:22px; }
  .cat-grid{ gap:12px; }
  .cat-card{ padding:14px; }
  .grid{ grid-template-columns:1fr; }
}

.prod-row.on{ background:var(--r-shell); border-color:var(--r-blue); }
.prod-row .dot{ position:relative; transition:background .12s, border-color .12s; }
.prod-row.on .dot{ background:var(--r-blue); border-color:var(--r-blue); }
.prod-row.on .dot::after{
  content:'✓'; position:absolute; inset:0; display:flex; align-items:center; justify-content:center;
  color:#fff; font-size:11px; font-weight:700; line-height:1;
}

.card-actions{ flex-wrap:wrap; }
.btn-card.pick{ background:var(--r-pill); border-color:transparent; color:var(--r-ink); }
.btn-card.pick:hover{ border-color:var(--r-blue); color:var(--r-blue); }
.btn-card.pick.on{ background:var(--r-blue); border-color:var(--r-blue); color:#fff; }

.pick-bar{
  position:fixed; left:50%; bottom:18px; transform:translate(-50%, 130%);
  z-index:60; width:min(1100px, calc(100vw - 28px));
  display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap;
  padding:12px 16px; border-radius:16px;
  background:var(--r-shell); border:1px solid var(--r-line);
  box-shadow:0 12px 34px rgba(28,41,66,.18);
  transition:transform .22s ease, opacity .22s ease; opacity:0; pointer-events:none;
}
.pick-bar.show{ transform:translate(-50%, 0); opacity:1; pointer-events:auto; }
.pb-left{ display:flex; align-items:center; gap:8px; font-size:13.5px; color:var(--r-ink2); }
.pb-ico{ font-size:17px; }
.pb-left b{ color:var(--r-ink); }
.pb-sep{ opacity:.45; }
.pb-amt{ font-size:19px; color:var(--r-blue); letter-spacing:-.3px; }
.pb-right{ display:flex; align-items:center; gap:9px; }
.pb-clear, .pb-submit{
  font:inherit; font-size:13.5px; font-weight:700; cursor:pointer; white-space:nowrap;
  border-radius:10px; padding:9px 18px; transition:.15s;
}
.pb-clear{ background:var(--r-shell); border:1px solid var(--r-line); color:var(--r-ink2); }
.pb-clear:hover{ color:var(--r-ink); border-color:var(--r-blue); }
.pb-submit{ background:var(--r-green); border:1px solid transparent; color:#fff; }
.pb-submit:hover{ filter:brightness(1.08); }
@media (max-width:640px){
  .pick-bar{ left:10px; right:10px; bottom:10px; width:auto; transform:translate(0,130%); border-radius:14px; }
  .pick-bar.show{ transform:translate(0,0); }
  .pb-left{ font-size:12.5px; gap:6px; }
  .pb-amt{ font-size:17px; }
  .pb-clear, .pb-submit{ padding:8px 13px; font-size:12.5px; }
}

.pick-bar{ flex-direction:column; align-items:stretch; gap:10px; padding:0; overflow:hidden; }
.pb-main{ display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; padding:12px 16px; }

.pb-list{ max-height:38vh; overflow:auto; background:var(--sum-track); border-bottom:1px solid var(--sum-line); padding:2px 0; }
.pb-list:empty{ display:none; }

body.modal-open .pick-bar{ display:none !important; }
@media (max-width:640px){
  .pb-list{ max-height:26vh; }
  .pick-bar{ max-height:none; }
  .pb-main{ flex-direction:column; align-items:stretch; gap:9px; padding:10px 12px; }
  .pb-left{ justify-content:center; }
  .pb-right{ display:flex; gap:9px; }
  .pb-right > *{ flex:1 1 0; text-align:center; min-width:0; }
  .pb-clear, .pb-submit{ white-space:nowrap; }
  .topbar-inner{ padding:8px 12px; gap:8px; }
  .topbar-right{ gap:6px; }
  .tb-link{ padding:6px 11px; font-size:12.5px; }
  .icon-btn{ width:34px; height:34px; }
  /* 清单行：窄屏把固定宽度的部件压到最小，并隐藏「/次」单位，
     否则 名称+单位+步进器+小计+删除 的总宽会超过行宽，右侧的 × 被裁掉。 */
}
.pb-item{
  display:flex; align-items:center; gap:10px;
  background:var(--sum-row); border:1px solid var(--sum-line); border-radius:9px;
  margin:6px 9px; padding:9px 11px; font-size:13.5px;
}
.pb-item:last-child{ margin-bottom:6px; }
.pb-nm{ flex:1 1 auto; min-width:0; font-weight:700; color:var(--r-ink); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; word-break:keep-all; }
.pb-unit{ flex:0 0 68px; text-align:right; color:var(--sum-cap); font-size:12.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pb-step{ display:flex; align-items:center; justify-content:center; gap:2px; flex:0 0 74px; }
.pb-step button{
  font:inherit; font-size:15px; line-height:1; cursor:pointer; width:26px; height:26px;
  display:flex; align-items:center; justify-content:center;
  background:var(--sum-track); border:1px solid var(--sum-line); border-radius:7px; color:var(--r-ink);
  transition:background .12s, border-color .12s, color .12s;
}
.pb-step button:hover{ background:var(--r-blue); border-color:var(--r-blue); color:#fff; }
.pb-step b{ min-width:30px; text-align:center; font-size:13.5px; color:var(--r-ink); }
.pb-line{ flex:0 0 58px; text-align:right; font-weight:700; color:var(--r-blue); white-space:nowrap; }
.pb-del{
  font:inherit; font-size:13px; line-height:1; cursor:pointer; flex:0 0 16px; text-align:right;
  width:24px; height:24px; border-radius:7px; border:1px solid transparent;
  background:transparent; color:var(--r-ink2);
}
.pb-del:hover{ color:var(--danger); border-color:var(--danger); }

.prod-row .qty{ font-size:12.5px; font-weight:700; color:var(--r-blue); white-space:nowrap; }
.prod-row .qty:empty{ display:none; }

@media (max-width:640px){
  .pb-item{ gap:5px; margin:4px 4px; padding:8px 7px; font-size:12.5px; }
  .pb-nm{ font-size:12.5px; }
  .pb-unit{ flex:0 0 56px; font-size:11.5px; }
  .pb-step{ flex:0 0 60px; gap:1px; }
  .pb-step button{ width:21px; height:21px; font-size:13px; border-radius:6px; }
  .pb-step b{ min-width:18px; font-size:12px; }
  .pb-line{ flex:0 0 46px; font-size:12.5px; }
  .pb-del{ flex:0 0 13px; font-size:14px; }
}

@media (max-width:420px){
  .cat-entry{padding:0 14px;font-size:13px;letter-spacing:0;}
}

.pay-fail{background:#dc2626 !important;border-color:#dc2626 !important;color:#fff !important;white-space:normal !important;line-height:1.45 !important;height:auto !important;min-height:42px;}
</style>
</head>
<body>

<?php

$brandTxt  = trim((string)($cfg['brand_name'] ?? '')) !== ''
           ? (string)$cfg['brand_name']
           : ($payCfg['payee_name'] !== '' ? (string)$payCfg['payee_name'] : '在线下单');
$tbQuery   = (string)($cfg['topbar_query'] ?? '🔍 查询订单');
$tbBack    = (string)($cfg['topbar_back'] ?? '← 返回主站');
$tbBackUrl = trim((string)($cfg['topbar_back_url'] ?? '')) !== '' ? (string)$cfg['topbar_back_url'] : '../';
$ftTitle   = trim((string)($cfg['footer_title'] ?? '')) !== ''
           ? (string)$cfg['footer_title']
           : (($payCfg['payee_name'] !== '' ? (string)$payCfg['payee_name'] : '在线商城') . ' · 在线下单');
$ftNote    = (string)($cfg['footer_note'] ?? '');
$fl1Text   = (string)($cfg['footer_link1_text'] ?? '订单查询');
$fl1Url    = (string)($cfg['footer_link1_url'] ?? './query.php');
$fl2Text   = (string)($cfg['footer_link2_text'] ?? '联系我们');
$fl2Url    = (string)($cfg['footer_link2_url'] ?? './contact.php');
?>
<div class="topbar">
  <div class="topbar-inner">
    <div class="brand">
      <span class="dot">🛒</span>
      <span class="bt"><?php echo htmlspecialchars($brandTxt); ?></span>
    </div>
    <div class="topbar-right">
      <?php if (trim($tbQuery) !== ''): ?>
        <a href="./query.php" class="tb-link"><?php echo htmlspecialchars($tbQuery); ?></a>
      <?php endif; ?>
      <?php if (trim($tbBack) !== ''): ?>
        <a href="<?php echo htmlspecialchars($tbBackUrl); ?>" class="tb-link"><?php echo htmlspecialchars($tbBack); ?></a>
      <?php endif; ?>
      <button class="icon-btn" id="themeBtn" title="切换明暗主题" onclick="toggleTheme()">🌙</button>
    </div>
  </div>
</div>

<div class="wrap">

  <a class="crumb" id="crumb" href="<?php echo htmlspecialchars($selfUrl ?? './'); ?>" style="display:none;">← 返回全部分类</a>

  <div class="cat-hero" id="catHero" style="display:none;"></div>

  <div class="hero" id="heroBox">
    <h1><?php echo htmlspecialchars($cfg['page_title']); ?></h1>
    <p><?php echo nl2br(htmlspecialchars($cfg['page_subtitle'])); ?></p>
    <?php if (trim((string)$cfg['announcement']) !== ''): ?>
      <div class="notice">📢 <?php echo nl2br(htmlspecialchars($cfg['announcement'])); ?></div>
    <?php endif; ?>
    <?php if (!$dbReady): ?>
      <div class="warn-box">⚠️ 下单功能正在维护中。<?php echo htmlspecialchars(shop_db_unavailable_hint()); ?></div>
    <?php endif; ?>
  </div>

  <div class="toolbar">
    <div class="search-row">
      <div class="search-box">
        <span class="si">🔍</span>
        <input type="text" id="searchInput" placeholder="搜索商品名称、标签或说明…" autocomplete="off">
      </div>
      <a class="cat-entry" href="?cat=all" title="浏览全部服务">全部服务</a>
    </div>
    <div class="pills" id="pills"></div>
  </div>

  <div class="cat-grid" id="catGrid" style="display:none;"></div>

  <div class="grid" id="grid"></div>
</div>

<div class="footer">
  <div><?php echo htmlspecialchars($ftTitle); ?></div>
  <?php if (trim($ftNote) !== ''): ?>
    <div style="margin-top:5px;"><?php echo nl2br(htmlspecialchars($ftNote)); ?></div>
  <?php endif; ?>
  <?php if (trim($fl1Text) !== '' || trim($fl2Text) !== ''): ?>
    <div style="margin-top:5px;">
      <?php if (trim($fl1Text) !== ''): ?><a href="<?php echo htmlspecialchars($fl1Url); ?>" style="color:var(--brand);"><?php echo htmlspecialchars($fl1Text); ?></a><?php endif; ?>
      <?php if (trim($fl1Text) !== '' && trim($fl2Text) !== ''): ?> · <?php endif; ?>
      <?php if (trim($fl2Text) !== ''): ?><a href="<?php echo htmlspecialchars($fl2Url); ?>" style="color:var(--brand);"><?php echo htmlspecialchars($fl2Text); ?></a><?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div class="mask" id="orderMask">
  <div class="modal" role="dialog" aria-modal="true">
    <div class="modal-head">
      <h3>📋 确认订单</h3>
      <button class="modal-close" onclick="closeOrder()" aria-label="关闭">×</button>
    </div>
    <div class="modal-body">
      <form id="orderForm" autocomplete="off" onsubmit="return false;">
        <div class="honeypot"><label>网址</label><input type="text" id="hpWebsite" name="website" tabindex="-1" autocomplete="off"></div>

        <div class="fg2">
          <div class="fg" id="fgName">
            <label>您的称呼<span class="req">*</span></label>
            <input type="text" id="inName" maxlength="60" placeholder="您的姓名或称呼">
            <div class="err">请填写您的称呼</div>
          </div>
          <div class="fg" id="fgWechat">
            <label>您的微信<span class="req" id="reqWechat">*</span></label>
            <input type="text" id="inWechat" maxlength="64" placeholder="微信号 / 手机号">
            <div class="err">请填写您的微信号</div>
          </div>
        </div>

        <div class="fg2">
          <div class="fg" id="fgPhone">
            <label>您的电话<span class="req" id="reqPhone">*</span></label>
            <input type="tel" id="inPhone" maxlength="20" placeholder="手机号">
            <div class="err">请填写正确的联系电话</div>
          </div>
          <div class="fg" id="fgMode" <?php echo empty($formCfg['enable_booking']) ? 'style="display:none"' : ''; ?>>
            <label>服务方式<span class="req">*</span></label>
            <div class="seg">
              <button type="button" id="segNow" class="active" onclick="setMode('now')">⚡ 立即服务</button>
              <button type="button" id="segBook" onclick="setMode('booking')">📅 提前预约</button>
            </div>
          </div>
        </div>

        <div class="fg" id="fgBook" style="display:none;">
          <label>您什么时间有空<span class="req" id="reqBook">*</span></label>
          <div class="row2">
            <input type="date" id="inBookDate">
            <select id="inBookSlot">
              <option value="">选择时段</option>
              <option value="上午（9:00-12:00）">上午（9:00-12:00）</option>
              <option value="下午（13:00-18:00）">下午（13:00-18:00）</option>
              <option value="晚上（19:00-23:00）">晚上（19:00-23:00）</option>
              <option value="随时都可以">随时都可以</option>
              <option value="再约时间（下单后协商）">再约时间（下单后协商）</option>
            </select>
          </div>
          <div class="err">请选择预约日期与时段</div>
        </div>

        <div class="fg" id="fgRemark" <?php echo empty($formCfg['enable_remark']) ? 'style="display:none"' : ''; ?>>
          <label>备注 <span class="hint">（选填，可说明具体需求/电脑情况）</span></label>
          <textarea id="inRemark" maxlength="500" placeholder="补充说明（选填）"></textarea>
        </div>

        <div class="summary" id="orderSummary"></div>
        <div class="notice-box" id="formNotice"></div>
      </form>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost" onclick="closeOrder()" style="flex:0 0 auto;">取消</button>
      <button class="btn btn-primary" id="submitBtn" onclick="submitOrder()">✓ 提交订单</button>
    </div>
  </div>
</div>

<div class="qr-zoom" id="qrZoom" onclick="if(event.target===this)closeQrZoom()">
  <button class="qz-close" onclick="closeQrZoom()" aria-label="关闭">×</button>
  <img id="qrZoomImg" src="" alt="收款码" referrerpolicy="no-referrer">
  <div class="qz-info" id="qrZoomInfo"></div>
  <div class="qz-hint">长按二维码可保存到相册，再用微信 / 支付宝「扫一扫 → 从相册选取」完成支付；点击空白处或按 Esc 关闭</div>
</div>

<div class="mask" id="payMask">
  <div class="modal pay" role="dialog" aria-modal="true">
    <div class="modal-head">
      <h3>💳 扫码付款</h3>
      <button class="modal-close" onclick="closePay()" aria-label="关闭">×</button>
    </div>
    <div class="modal-body" id="payBody"></div>
    <div class="modal-foot" id="payFoot" style="flex-direction:column;"></div>
  </div>
</div>

<div class="mask" id="cfmMask">
  <div class="modal" style="max-width:400px;" role="dialog" aria-modal="true">
    <div class="modal-head">
      <h3 id="cfmTitle">确认操作</h3>
      <button class="modal-close" onclick="closeCfm()" aria-label="关闭">×</button>
    </div>
    <div class="modal-body">
      <div id="cfmMsg" style="font-size:14px;line-height:1.8;text-align:center;padding:2px 0;"></div>
      <div class="fg" id="cfmInputWrap" style="display:none;margin:14px 0 0;">
        <input type="text" id="cfmInput" maxlength="64" autocomplete="off">
        <div class="err" id="cfmErr">请填写正确内容</div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-ghost" style="flex:1;" onclick="closeCfm()">取消</button>
      <button class="btn btn-primary" id="cfmOk" style="flex:1;" onclick="cfmGo()">确定</button>
    </div>
  </div>
</div>

<div class="pick-bar" id="pickBar">
  <div class="pb-list" id="pickList"></div>
  <div class="pb-main">
  <div class="pb-left">
    <span class="pb-ico">🧾</span>
    <span>已选 <b id="pickCount">0</b> 项</span>
    <span class="pb-sep">·</span>
    <span>合计 <b class="pb-amt" id="pickTotal">￥0</b></span>
  </div>
  <div class="pb-right">
    <button type="button" class="pb-clear" onclick="clearPick()">清空选择</button>
    <button type="button" class="pb-submit" onclick="openPickOrder()">提交订单</button>
  </div>
  </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<div class="float-order" id="floatOrder" onclick="goQuery()">
  <span>📌 未完成订单</span>
  <span class="fo-no" id="foNo"></span>
  <span class="fo-x" onclick="event.stopPropagation();dismissFloat()" title="不再提示">×</span>
</div>

<script>

var PRODUCTS = <?php echo json_encode($clientProducts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var CATS     = <?php echo json_encode($clientCats, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var CONF     = <?php echo json_encode($clientConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var CARD_ICON = {software:'🧩', maintain:'🧹', dev:'🌐', physical:'📦', other:'🛠️'};

var FOCUS_CAT = (function(){
  var raw = new URLSearchParams(location.search).get('cat') || '';
  if (raw === 'all') return 'all';
  for (var i=0;i<CATS.length;i++) if (CATS[i].key === raw) return raw;
  return '';
})();

function toggleTheme(){
  var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', cur);
  document.getElementById('themeBtn').textContent = cur === 'dark' ? '☀️' : '🌙';
  try{ localStorage.setItem('shopTheme', cur); }catch(e){}
}
(function(){
  var t = document.documentElement.getAttribute('data-theme');
  document.getElementById('themeBtn').textContent = t === 'dark' ? '☀️' : '🌙';
})();

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

var state = { cat:'all', kw:'', picked:{}, orderItems:[], mode:'now', order:null };

function esc(s){
  return String(s == null ? '' : s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function money(v){ return '￥' + v; }

function renderPills(){
  var html = '<div class="pill active" data-cat="all" onclick="setCat(\'all\')">全部</div>';
  CATS.forEach(function(c){
    var n = PRODUCTS.filter(function(p){ return p.category === c.key; }).length;
    if (!n) return;
    html += '<div class="pill" data-cat="' + esc(c.key) + '" onclick="setCat(\'' + esc(c.key) + '\')">' +
            esc(c.icon) + ' ' + esc(c.name) + '</div>';
  });
  document.getElementById('pills').innerHTML = html;
}

function setCat(k){
  state.cat = k;
  Array.prototype.forEach.call(document.querySelectorAll('#pills .pill'), function(el){
    el.classList.toggle('active', el.getAttribute('data-cat') === k);
  });

  if (FOCUS_CAT){
    FOCUS_CAT = (k === 'all') ? 'all' : k;
    document.getElementById('catHero').innerHTML = catHeroHtml(FOCUS_CAT);
    try{ history.replaceState(null, '', '?cat=' + encodeURIComponent(FOCUS_CAT)); }catch(e){}
  }
  renderGrid();
}

function renderHome(){
  var html = '';
  CATS.forEach(function(c){
    var items = PRODUCTS.filter(function(p){ return p.category === c.key; });
    if (!items.length) return;
    var rows = items.slice(0, 4).map(function(p){
      var out = p.stock === 0;
      var mode = p.allow_now ? 'now' : 'booking';
      return '<button type="button" class="prod-row" data-pick="' + esc(p.id) + '" aria-checked="false" ' +
             (out || !CONF.dbReady ? 'disabled' : '') +
             ' onclick="togglePick(\'' + esc(p.id) + '\')">' +
             '<span class="dot"></span>' +
             '<span class="nm">' + esc(p.name) + '</span>' +
             '<span class="qty"></span>' +
             (out ? '<span class="out">已售罄</span>' : '<span class="pr">' + money(p.price) + '</span>') +
             '</button>';
    }).join('');
    var desc = c.desc ? esc(c.desc) : ('共 ' + items.length + ' 件商品，点击任一项可直接下单');
    html += '<section class="cat-card">' +
      '<div class="cat-head"><span class="cat-ico">' + esc(c.icon || '🗂️') + '</span>' +
      '<h3>' + esc(c.name) + '</h3><span class="cat-count">' + items.length + ' 件</span></div>' +
      '<div class="cat-desc">' + desc + '</div>' +
      '<div class="cat-items">' + rows + '</div>' +

      '<a class="cat-more" href="?cat=' + encodeURIComponent(c.key) + '">进入「' + esc(c.name) + '」分类页 · ' + items.length + ' 件 →</a>' +
      '</section>';
  });
  return html;
}

function catHeroHtml(key){
  if (key === 'all'){
    var n = PRODUCTS.length;
    return '<div class="top"><span class="cat-ico">🗂️</span><h1>全部商品</h1>' +
      '<span class="cat-count">' + n + ' 件</span></div>' +
      '<div class="cat-desc" style="margin:0;">共 ' + n + ' 件商品，点击任一项可直接下单</div>';
  }
  var c = null;
  CATS.forEach(function(x){ if (x.key === key) c = x; });
  var items = PRODUCTS.filter(function(p){ return p.category === key; });
  var desc = (c && c.desc) ? esc(c.desc) : ('共 ' + items.length + ' 件商品，点击任一项可直接下单');
  return '<div class="top"><span class="cat-ico">' + esc((c && c.icon) || '🗂️') + '</span>' +
    '<h1>' + esc((c && c.name) || '商品列表') + '</h1>' +
    '<span class="cat-count">' + items.length + ' 件</span></div>' +
    '<div class="cat-desc" style="margin:0;">' + desc + '</div>';
}

function renderGrid(){
  var grid = document.getElementById('grid');
  var list = PRODUCTS.filter(function(p){
    if (state.cat !== 'all' && p.category !== state.cat) return false;
    if (state.kw){
      var hay = (p.name + ' ' + p.desc + ' ' + (p.tags || []).join(' ') + ' ' + p.cat_name).toLowerCase();
      if (hay.indexOf(state.kw.toLowerCase()) < 0) return false;
    }
    return true;
  });

  if (!list.length){
    grid.innerHTML = '<div class="empty-state"><div class="big">🗂️</div>' +
      (PRODUCTS.length ? '没有找到匹配的商品，换个关键词或分类试试' : '暂无可下单的商品，请稍后再来') + '</div>';
    return;
  }

  var html = '';
  list.forEach(function(p){
    var soldOut = p.stock === 0;
    var icon = CARD_ICON[p.category] || '🛠️';
    var thumb = p.image
      ? '<img src="' + esc(p.image) + '" alt="' + esc(p.name) + '" loading="lazy" ' +
        'onerror="this.onerror=null;this.outerHTML=\'<span class=&quot;emoji&quot;>' + icon + '</span>\'">'
      : '<span class="emoji">' + esc(p.icon || icon) + '</span>';

    var tags = (p.tags || []).map(function(t){ return '<span class="tag">' + esc(t) + '</span>'; }).join('');

    var btns = '';
    btns += '<button type="button" class="btn-card pick" data-pick="' + esc(p.id) + '" ' +
            (soldOut || !CONF.dbReady ? 'disabled' : '') +
            ' onclick="togglePick(\'' + esc(p.id) + '\')">🧾 加入清单</button>';
    if (p.allow_now){
      btns += '<button class="btn-card buy" ' + (soldOut || !CONF.dbReady ? 'disabled' : '') +
              ' onclick="openOrder(\'' + esc(p.id) + '\',\'now\')">⚡ 立即下单</button>';
    }
    if (p.allow_booking){
      btns += '<button class="btn-card" ' + (soldOut || !CONF.dbReady ? 'disabled' : '') +
              ' onclick="openOrder(\'' + esc(p.id) + '\',\'booking\')">📅 提前预约</button>';
    }
    if (!btns) btns = '<button class="btn-card" disabled>暂不可下单</button>';

    html += '<div class="card">' +

      (FOCUS_CAT ? '' : '<div class="cat-badge">' + esc(p.cat_name) + '</div>') +
      '<div class="thumb">' + thumb + '</div>' +
      '<h3>' + esc(p.name) + '</h3>' +
      '<div class="desc">' + esc(p.desc).replace(/\n/g, '<br>') + '</div>' +
      (tags ? '<div class="tags">' + tags + '</div>' : '') +
      '<div class="price-row"><span class="price">' + money(p.price) + '</span><span class="unit">/ ' + esc(p.unit) + '</span>' +
        (soldOut ? '<span class="stock-out">已售罄</span>'
                 : (p.stock > 0 ? '<span class="stock-out" style="color:var(--muted)">剩余 ' + p.stock + ' ' + esc(p.unit) + '</span>' : '')) +
      '</div>' +
      '<div class="card-actions">' + btns + '</div>' +
    '</div>';
  });
  grid.innerHTML = html;
}

document.getElementById('searchInput').addEventListener('input', function(){
  state.kw = this.value.trim();

  if (!FOCUS_CAT){
    var searching = state.kw !== '';
    document.getElementById('grid').style.display = searching ? '' : 'none';
    document.getElementById('catGrid').style.display = searching ? 'none' : '';
  }
  renderGrid();
});

function findProduct(id){
  for (var i = 0; i < PRODUCTS.length; i++) if (PRODUCTS[i].id === id) return PRODUCTS[i];
  return null;
}

function pickedList(){
  var out = [];
  PRODUCTS.forEach(function(p){
    var q = state.picked[p.id];
    if (q) out.push({ p: p, qty: q });
  });
  return out;
}
function pickedTotal(){
  var t = 0;
  pickedList().forEach(function(it){ t += (Number(it.p.price) || 0) * it.qty; });
  return t;
}
function togglePick(pid){
  if (!CONF.dbReady){ toast('下单功能正在维护中', 'err'); return; }
  var p = findProduct(pid);
  if (!p) return;
  if (p.stock === 0){ toast('「' + p.name + '」已售罄', 'err'); return; }
  if (state.picked[pid]) delete state.picked[pid]; else state.picked[pid] = 1;
  syncPickUI();
}

function setQty(pid, d){
  if (!state.picked[pid]) return;
  var q = state.picked[pid] + d;
  if (q < 1) q = 1;
  if (q > 99) q = 99;
  state.picked[pid] = q;
  syncPickUI();
}
function removePick(pid){
  delete state.picked[pid];
  syncPickUI();
}
function clearPick(){
  state.picked = {};
  syncPickUI();
}

function syncPickUI(){
  Array.prototype.forEach.call(document.querySelectorAll('[data-pick]'), function(el){
    var id = el.getAttribute('data-pick');
    var q  = state.picked[id] || 0;
    el.classList.toggle('on', !!q);
    el.setAttribute('aria-checked', q ? 'true' : 'false');
    if (el.classList.contains('btn-card')) {
      el.textContent = q ? ('✓ 已加入' + (q > 1 ? ' ×' + q : '')) : '🧾 加入清单';
    }

    var badge = el.querySelector('.qty');
    if (badge) badge.textContent = q > 1 ? '×' + q : '';
  });
  renderPickList();
  var n = pickedList().length;
  var bar = document.getElementById('pickBar');
  if (!bar) return;
  if (!n){ bar.classList.remove('show'); syncPickBarSpace(); return; }
  document.getElementById('pickCount').textContent = n;
  document.getElementById('pickTotal').textContent = money(pickedTotal());
  bar.classList.add('show');
  syncPickBarSpace();
}

function syncPickBarVisibility(){
  var any = document.querySelector('.mask.show, .qr-zoom.show');
  document.body.classList.toggle('modal-open', !!any);
}

function syncPickBarSpace(){
  var bar = document.getElementById('pickBar');
  if (!bar) return;
  var h = bar.classList.contains('show') ? bar.offsetHeight : 0;

  document.body.style.paddingBottom = (h > 0 ? (h + 34) : 70) + 'px';
}
window.addEventListener('resize', function(){ syncPickBarSpace(); });

function renderPickList(){
  var box = document.getElementById('pickList');
  if (!box) return;
  var list = pickedList();
  if (!list.length){ box.innerHTML = ''; return; }
  var html = '';
  list.forEach(function(it){
    var p = it.p, q = it.qty;
    html += '<div class="pb-item">' +
      '<span class="pb-nm">' + esc(p.name) + '</span>' +
      '<span class="pb-unit">' + money(p.price) + ' / ' + esc(p.unit || '次') + '</span>' +
      '<span class="pb-step">' +
        '<button type="button" onclick="setQty(\'' + esc(p.id) + '\',-1)" aria-label="减少数量">−</button>' +
        '<b>' + q + '</b>' +
        '<button type="button" onclick="setQty(\'' + esc(p.id) + '\',1)" aria-label="增加数量">+</button>' +
      '</span>' +
      '<span class="pb-line">' + money((Number(p.price) || 0) * q) + '</span>' +
      '<button type="button" class="pb-del" onclick="removePick(\'' + esc(p.id) + '\')" aria-label="从清单移除">✕</button>' +
      '</div>';
  });
  box.innerHTML = html;
}

function openPickOrder(){
  var list = pickedList();
  if (!list.length){ toast('请先勾选要下单的商品', 'err'); return; }
  state.orderItems = list;

  var anyNow = false;
  list.forEach(function(it){ if (it.p && it.p.allow_now) anyNow = true; });
  openOrderCart(anyNow ? 'now' : 'booking');
}

function openOrder(pid, mode){
  var p = findProduct(pid);
  if (!p) return;
  state.orderItems = [{ p: p, qty: 1 }];
  openOrderCart(mode);
}

function openOrderCart(mode){
  if (!CONF.dbReady){ toast('下单功能正在维护中', 'err'); return; }
  if (!state.orderItems.length) return;
  var items = state.orderItems;

  var anyNow = false;
  items.forEach(function(it){ if (it.p.allow_now) anyNow = true; });
  setMode(mode === 'booking' || !anyNow ? 'booking' : (mode || 'now'));

  var d = new Date(); d.setDate(d.getDate() + 1);
  var iso = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
  document.getElementById('inBookDate').value = iso;
  document.getElementById('inBookDate').min = new Date().toISOString().slice(0,10);

  document.getElementById('formNotice').textContent = '🔒 ' + CONF.form.notice;
  document.getElementById('reqWechat').style.visibility = CONF.form.require_wechat ? 'visible' : 'hidden';
  document.getElementById('reqPhone').style.visibility  = CONF.form.require_phone ? 'visible' : 'hidden';

  renderSummary();
  clearErrors();
  document.getElementById('orderMask').classList.add('show'); syncPickBarVisibility();
  setTimeout(function(){ document.getElementById('inName').focus(); }, 120);
}

function closeOrder(){ document.getElementById('orderMask').classList.remove('show'); syncPickBarVisibility(); }

function setMode(m){
  state.mode = m;
  document.getElementById('segNow').classList.toggle('active', m === 'now');
  document.getElementById('segBook').classList.toggle('active', m === 'booking');
  document.getElementById('fgBook').style.display = (m === 'booking') ? '' : 'none';
}

function renderSummary(){
  var list = state.orderItems;
  if (!list.length) return;
  var html = '<div class="sum-cap">🧾 本次下单清单</div>';
  list.forEach(function(it){
    var p = it.p, q = it.qty;
    html += '<div class="line"><span>' + esc(p.name) + ' × ' + q + '</span><span>' +
            money((Number(p.price) || 0) * q) + '</span></div>';
  });
  var total = 0;
  list.forEach(function(it){ total += (Number(it.p.price) || 0) * it.qty; });
  html += '<div class="line total"><span>合计' + (list.length > 1 ? '（' + list.length + ' 项）' : '') +
          '</span><span class="amt">' + money(total) + '</span></div>';
  document.getElementById('orderSummary').innerHTML = html;
}

function clearErrors(){
  Array.prototype.forEach.call(document.querySelectorAll('.fg'), function(el){ el.classList.remove('invalid'); });
}
function markInvalid(id){ var el = document.getElementById(id); if (el) el.classList.add('invalid'); }

function submitOrder(){
  if (!state.orderItems.length) return;
  clearErrors();

  var name   = document.getElementById('inName').value.trim();
  var wechat = document.getElementById('inWechat').value.trim();
  var phone  = document.getElementById('inPhone').value.trim();
  var remark = document.getElementById('inRemark').value.trim();
  var bd     = document.getElementById('inBookDate').value;
  var bs     = document.getElementById('inBookSlot').value;

  var bad = false;
  if (!name){ markInvalid('fgName'); bad = true; }
  if (CONF.form.require_wechat && wechat.length < 2){ markInvalid('fgWechat'); bad = true; }
  if (CONF.form.require_phone && phone.replace(/\D/g,'').length < 5){ markInvalid('fgPhone'); bad = true; }
  if (state.mode === 'booking' && (!bd || !bs)){ markInvalid('fgBook'); bad = true; }
  if (bad){ toast('请检查标红的必填项', 'err'); return; }

  var btn = document.getElementById('submitBtn');
  btn.disabled = true; btn.textContent = '提交中…';

  var payload = {
    action: 'create',
    name: name, wechat: wechat, phone: phone, remark: remark,
    mode: state.mode,
    book_date: state.mode === 'booking' ? bd : '',
    book_slot: state.mode === 'booking' ? bs : '',
    website: document.getElementById('hpWebsite').value,
    items: state.orderItems.map(function(it){ return { id: it.p.id, qty: it.qty }; })
  };

  fetch('api.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(payload)
  })
  .then(function(r){ return r.json(); })
  .then(function(res){
    btn.disabled = false; btn.textContent = '✓ 提交订单';
    if (!res.ok){ toast(res.msg || '下单失败', 'err'); return; }
    state.order  = res.order;
    closeOrder();
    clearPick();
    showPay(res.order, res.pay);
    rememberOrder(res.order.order_no);
    toast('下单成功，请扫码付款', 'ok');
  })
  .catch(function(){
    btn.disabled = false; btn.textContent = '✓ 提交订单';
    toast('网络异常，请稍后重试', 'err');
  });
}

function statusBanner(o){
  var s = o.status;
  if (s === 'pending_payment') return '<div class="status-banner wait">⏳ <div><b>等待付款</b></div></div>';
  if (s === 'awaiting_verify') return '<div class="status-banner wait">🔍 <div><b>已提交，等待人工核查</b><br>管理员正在核对收款记录，核实后即为您安排技术员，请保持电话/微信畅通。</div></div>';
  if (s === 'paid')            return '<div class="status-banner ok">✅ <div><b>付款已确认</b><br>管理员已核实到账，正在为您安排服务。</div></div>';
  if (s === 'payment_failed')  return '<div class="status-banner warn">⚠️ <div><b>未匹配到您的付款</b><br>请核对备注码后重新付款，或联系管理员协助。</div></div>';
  if (s === 'processing')      return '<div class="status-banner ok">🔧 <div><b>服务处理中</b><br>技术员正在为您处理，请留意联系方式。</div></div>';
  if (s === 'completed')       return '<div class="status-banner ok">🎉 <div><b>订单已完成</b><br>感谢您的信任，如有问题请联系管理员。</div></div>';
  if (s === 'cancelled')       return '<div class="status-banner warn">🚫 <div><b>订单已取消</b><br>如需继续服务，请重新下单或联系管理员。</div></div>';
  if (s === 'refunding' || s === 'refunded') return '<div class="status-banner warn">💰 <div><b>' + esc(o.status_label) + '</b></div></div>';
  return '<div class="status-banner wait">ℹ️ <div><b>' + esc(o.status_label) + '</b></div></div>';
}

function showPay(o, pay){
  if (!pay) pay = CONF.pay;
  state.pay = pay;
  var canPay = (o.status === 'pending_payment' || o.status === 'payment_failed');
  var html = statusBanner(o);

  if (canPay){
    html += '<div class="qr-zone">';
    var pmList = payMethods();
    if (o.pay_method && !state.payMethod) state.payMethod = o.pay_method;
    var pmCur  = payActiveQr();
    var pmSel  = state.payMethod || (pmList[0] ? pmList[0].k : '');
    if (pmList.length > 1){
      html += '<div class="pm-label">请选择支付方式</div>';
      html += '<div class="pay-methods">';
      pmList.forEach(function(m, i){
        html += '<button type="button" class="pm-btn' + (m.k === pmSel ? ' on' : '') + '" data-pm="' + esc(m.k) + '" onclick="pickPayMethod(\'' + esc(m.k) + '\',this)">' + m.i + ' ' + esc(m.n) + '</button>';
      });
      html += '</div>';
    }
    if (pmCur){
      html += '<img class="qr-zoomable" id="payQrImg" src="' + esc(pmCur) + '" alt="收款码" title="点击放大" onclick="openQrZoom()" referrerpolicy="no-referrer" ' +
              'onerror="this.onerror=null;this.outerHTML=\'<div class=&quot;qr-missing&quot;>收款码图片加载失败<br>请联系管理员</div>\'">';
      html += '<div class="qr-tip">' + (pay.qr_tip ? esc(pay.qr_tip) + ' · ' : '') + '🔍 点击收款码可放大</div>';
    } else {
      html += '<div class="qr-missing">管理员尚未配置收款码<br>请点击下方「联系管理员」</div>';
      if (pay.qr_tip) html += '<div class="qr-tip">' + esc(pay.qr_tip) + '</div>';
    }

    if (pay.expire_minutes > 0){
      html += '<div class="qr-expire">⏳ 请在 <b>' + pay.expire_minutes + ' 分钟</b>内完成付款，超时订单会自动失效</div>';
    }
    html += '</div>';

    html += '<div class="code-zone">' +
      '<div class="cz-title">💳 <span>' + (pay.code_mode === 'wechat' ? '付款时请在「备注 / 附言」中填写您的微信' : '付款备注码') + '</span></div>' +
      '<div class="cz-main">';
    if (pay.code_mode === 'wechat'){
      html += '<div class="code-wechat">我的微信：' + esc(o.wechat) + '</div>';
    } else {
      html += '<div class="code-big" id="payCodeText">' + esc(o.pay_code) + '</div>' +
              '<div class="cz-btns">' +
                (pay.allow_regenerate ? '<button class="mini-btn" onclick="regenCode()">🔄 换一个</button>' : '') +
                '<button class="mini-btn" onclick="copyText(\'' + esc(o.pay_code) + '\',\'备注码已复制\')">📋 复制</button>' +
              '</div>';
    }
    html += '</div></div>';
  }

  html += '<div class="info-rows">' +
    '<div class="info-row"><span class="k">订单号</span><span class="v">' + esc(o.order_no) + '</span></div>' +
    '<div class="info-row"><span class="k">联系人</span><span class="v">' + esc(o.name) + '</span></div>' +
    '<div class="info-row"><span class="k">联系方式</span><span class="v">' + esc(o.phone) + '</span></div>' +
    '<div class="info-row items"><span class="k">商品</span><span class="v">' + esc(o.items_text) + '</span></div>' +
    (o.mode === 'booking' && o.book_time ? '<div class="info-row"><span class="k">预约时间</span><span class="v">' + esc(o.book_time) + '</span></div>' : '') +
    (o.remark ? '<div class="info-row"><span class="k">备注</span><span class="v">' + esc(o.remark) + '</span></div>' : '') +
    '<div class="info-row" style="border-top:1px dashed var(--line);margin-top:7px;padding-top:11px;">' +
      '<span class="k" style="font-size:15px;font-weight:700;color:var(--text);">应付</span>' +
      '<span class="v" style="font-size:22px;color:var(--brand);">' + money(o.amount) + '</span></div>' +
  '</div>';

  if (pay.tips) html += '<div class="pay-tips">' + esc(pay.tips) + '</div>';
  document.getElementById('payBody').innerHTML = html;

  var foot = '';
  if (canPay){
    foot += '<button class="btn btn-success" id="paidBtn" style="width:100%;" onclick="markPaid()">✅ 已完成扫码支付</button>' +
            '<div style="display:flex;gap:10px;width:100%;">' +
              '<button class="btn btn-ghost" style="flex:1;" onclick="copyText(\'' + esc(o.order_no) + '\',\'订单号已复制\')">📋 复制订单号</button>' +
              '<button class="btn btn-ghost" style="flex:1;" onclick="goQuery()">🔍 查询订单</button>' +
            '</div>';
  } else {
    foot += '<button class="btn btn-ghost" style="width:100%;" onclick="goQuery()">🔍 查询订单进度</button>';
  }
  if (pay.contact_admin_url){
    foot += '<a class="btn btn-ghost" style="width:100%;" href="' + esc(pay.contact_admin_url) + '" target="_blank" rel="noopener">💬 ' + esc(pay.contact_admin_text || '联系管理员') + '</a>';
  }
  document.getElementById('payFoot').innerHTML = foot;
  document.getElementById('payMask').classList.add('show'); syncPickBarVisibility();
}

function closePay(){ document.getElementById('payMask').classList.remove('show'); syncPickBarVisibility(); }

function copyText(txt, okMsg){
  var done = function(){ toast(okMsg || '已复制', 'ok'); };
  if (navigator.clipboard && window.isSecureContext){
    navigator.clipboard.writeText(txt).then(done).catch(function(){ fallbackCopy(txt, done); });
  } else {
    fallbackCopy(txt, done);
  }
}
function fallbackCopy(txt, done){
  var ta = document.createElement('textarea');
  ta.value = txt; ta.style.position = 'fixed'; ta.style.left = '-9999px';
  document.body.appendChild(ta); ta.select();
  try{ document.execCommand('copy'); done(); }catch(e){ toast('复制失败，请手动长按选择', 'err'); }
  ta.remove();
}

function payMethods(){
  var p = (state && state.pay) ? state.pay : (CONF.pay || {});
  if (p.qr_mode !== 'split') return [];
  var out = [];
  if (p.qr_wechat) out.push({ k:'wechat', n:'微信支付', i:'💬', u:p.qr_wechat });
  if (p.qr_alipay) out.push({ k:'alipay', n:'支付宝',  i:'🅰', u:p.qr_alipay });
  return out;
}
function payActiveQr(){
  var p = (state && state.pay) ? state.pay : (CONF.pay || {});
  var list = payMethods();
  if (!list.length) return p.qr_url || '';
  var want = state.payMethod || list[0].k;
  for (var i = 0; i < list.length; i++) if (list[i].k === want) return list[i].u;
  return list[0].u;
}
function pickPayMethod(k, btn){
  state.payMethod = k;
  try {
    fetch('api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action:'set_pay_method', order_no:(state.order && state.order.order_no) || '', pay_method:k})
    });
  } catch (e) {}
  var img = document.getElementById('payQrImg');
  var list = payMethods();
  for (var i = 0; i < list.length; i++) if (list[i].k === k && img) img.src = list[i].u;
  var box = btn && btn.parentNode;
  if (box) Array.prototype.forEach.call(box.querySelectorAll('.pm-btn'), function(b){ b.classList.remove('on'); });
  if (btn) btn.classList.add('on');
}
function openQrZoom(){
  var pay = state.pay || CONF.pay;
  var __u = payActiveQr();
  if (!__u) return;
  var o = state.order || {};
  var info = '';
  if (o.order_no) info += '订单号：' + esc(o.order_no) + '<br>';
  if (o.amount)   info += '应付金额：<b>￥' + esc(o.amount) + '</b>';
  if (o.pay_code && pay.code_mode !== 'wechat'){
    info += '<div class="qz-code">' + esc(o.pay_code) + '</div>';
  }
  document.getElementById('qrZoomImg').src = __u;
  document.getElementById('qrZoomInfo').innerHTML = info;
  document.getElementById('qrZoom').classList.add('show'); syncPickBarVisibility();
}
function closeQrZoom(){
  document.getElementById('qrZoom').classList.remove('show'); syncPickBarVisibility();
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
  document.getElementById('cfmMask').classList.add('show'); syncPickBarVisibility();
  if (cfmNeedInput) setTimeout(function(){ inp.focus(); }, 120);
}
function askConfirm(html, okText, cb){ cfmOpen('确认操作', html, okText, false, '', cb); }
function askInput(title, html, placeholder, okText, cb){ cfmOpen(title, html, okText, true, placeholder, cb); }
function closeCfm(){
  document.getElementById('cfmMask').classList.remove('show'); syncPickBarVisibility();
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
  var o = state.order;
  if (!o) return;
  askConfirm(
    '请确认您已完成扫码付款<br><br>' +
    '订单号：<b>' + esc(o.order_no) + '</b><br>' +
    '应付金额：<b style="color:var(--brand)">' + money(o.amount) + '</b><br><br>' +
    '<span style="color:var(--muted);font-size:12.5px;">管理员将人工核对到账记录，核实后为您安排技术员。</span>',
    '我已付款',
    function(){
      var btn = document.getElementById('paidBtn');
      if (btn){ btn.disabled = true; btn.textContent = '提交中…'; }
      fetch('api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action:'mark_paid', order_no:o.order_no, pay_method: state.payMethod || ''})
      })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (!res.ok){
          // 失败原因要「钉」在按钮上 —— 只闪 3 秒的 toast 客户根本来不及看，
          // 结果就是「点了没反应、也没通知」。（最常见：订单已超时失效）
          var why = res.msg || '提交失败，请重试';
          toast(why, 'err', 12000);
          if (btn){ btn.disabled = false; btn.textContent = '⚠ ' + why; btn.classList.add('pay-fail'); }
          return;
        }
        state.order = res.order;
        showPay(res.order, null);
        toast('已提交付款信息，等待管理员核实', 'ok');
        dismissFloat();
      })
      .catch(function(){
        toast('网络异常，请稍后重试', 'err');
        if (btn){ btn.disabled = false; btn.textContent = '✅ 已完成扫码支付'; }
      });
    }
  );
}

function regenCode(){
  var o = state.order;
  if (!o) return;
  fetch('api.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'regen_code', order_no:o.order_no})
  })
  .then(function(r){ return r.json(); })
  .then(function(res){
    if (!res.ok){ toast(res.msg || '更换失败', 'err'); return; }
    o.pay_code = res.pay_code;
    var el = document.getElementById('payCodeText');
    if (el) el.textContent = res.pay_code;
    toast('备注码已更换为 ' + res.pay_code, 'ok');
  })
  .catch(function(){ toast('网络异常，请稍后重试', 'err'); });
}

function goQuery(){
  var no = (state.order && state.order.order_no) || getRemembered();
  location.href = './query.php' + (no ? ('?order_no=' + encodeURIComponent(no)) : '');
}

function rememberOrder(no){
  try{ localStorage.setItem('shopLastOrder', no); }catch(e){}
  showFloat(no);
}
function getRemembered(){
  try{ return localStorage.getItem('shopLastOrder') || ''; }catch(e){ return ''; }
}
function forgetOrder(){
  try{ localStorage.removeItem('shopLastOrder'); }catch(e){}
}
function showFloat(no){
  var el = document.getElementById('floatOrder');
  if (!no){ el.style.display = 'none'; return; }
  document.getElementById('foNo').textContent = no;
  el.style.display = 'flex';
}
function dismissFloat(){
  forgetOrder();
  document.getElementById('floatOrder').style.display = 'none';
}
(function(){ var no = getRemembered(); if (no) showFloat(no); })();

(function initView(){
  if (FOCUS_CAT){

    document.getElementById('catHero').innerHTML = catHeroHtml(FOCUS_CAT);
    document.getElementById('catHero').style.display = '';
    document.getElementById('crumb').style.display = '';
    document.getElementById('heroBox').style.display = 'none';
    document.getElementById('catGrid').style.display = 'none';
    state.cat = FOCUS_CAT;
  } else {

    document.getElementById('catGrid').innerHTML = renderHome();
    document.getElementById('catGrid').style.display = '';
    document.getElementById('grid').style.display = 'none';
    document.getElementById('pills').style.display = 'none';
  }
  renderPills();
  renderGrid();
  syncPickUI();
})();

document.getElementById('orderMask').addEventListener('click', function(e){ if (e.target === this) closeOrder(); });
document.getElementById('payMask').addEventListener('click', function(e){ if (e.target === this) closePay(); });
document.getElementById('cfmMask').addEventListener('click', function(e){ if (e.target === this) closeCfm(); });
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape'){ closeQrZoom(); closeOrder(); closePay(); closeCfm(); }

  if (e.key === 'Enter' && document.getElementById('cfmMask').classList.contains('show')){ cfmGo(); }
});

try{
  fetch('api.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'ping'}), keepalive: true
  }).catch(function(){});
}catch(e){}
</script>
</body>
</html>
