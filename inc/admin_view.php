<?php

if (!function_exists('csrf_field') || !isset($currentSub)) {
    http_response_code(403);
    exit('Access denied');
}

if (!function_exists('shop_config'))    require_once __DIR__ . '/inc/shop_store.php';
if (!function_exists('shop_push_dispatch')) require_once __DIR__ . '/inc/push.php';

$shopData  = shop_config(true);
$shopSub   = $currentSub;
$dbReady   = shop_db_available();

if ($dbReady) { try { shop_order_expire_unpaid(); } catch (Throwable $e) {} }
$shopStats = $dbReady ? shop_order_stats() : ['total'=>0,'today'=>0,'awaiting_verify'=>0,'pending_payment'=>0,'paid'=>0,'completed'=>0,'income'=>0,'by_status'=>[]];
$pushStats = $dbReady ? shop_push_queue_stats() : ['pending'=>0,'sent'=>0,'failed'=>0];
$shopBase  = '?sub=';

$shopCsrf = (string)($_SESSION['csrf_token'] ?? '');
$shopUrl  = function ($q) use ($shopCsrf) {
    if ($q === '') return $q;
    return $q . (strpos($q, '?') === false ? '?' : '&') . 'csrf=' . urlencode($shopCsrf);
};
?>

<style>

.shop-badge{display:inline-block;padding:3px 11px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;}
.shop-code-badge{
  display:inline-block;margin-top:5px;padding:1px 9px;border-radius:6px;font-size:11.5px;font-weight:700;
  background:var(--primary-soft);color:var(--primary);font-family:Consolas,Monaco,monospace;letter-spacing:1px;
}

.shop-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(146px,1fr));gap:12px;margin-bottom:18px;}
.shop-stat{background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:14px 16px;text-align:center;}
.shop-stat .v{font-size:24px;font-weight:800;line-height:1.2;letter-spacing:-.5px;}
.shop-stat .t{font-size:12.5px;color:var(--text2);margin-top:5px;}
.shop-stat.alert{background:var(--danger-soft);border-color:var(--danger);}
.shop-stat.alert .v{color:var(--danger);}

.shop-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;}
.shop-tab{
  padding:6px 14px;border-radius:20px;font-size:13px;border:1px solid var(--border);background:var(--surface);
  color:var(--text2);text-decoration:none;transition:.2s;white-space:nowrap;
}
.shop-tab:hover{border-color:var(--primary);color:var(--primary);}
.shop-tab.active{background:var(--primary);color:#fff;border-color:var(--primary);font-weight:600;}
.shop-tab .cnt{opacity:.75;font-size:11.5px;margin-left:3px;}

.shop-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:16px;}
.shop-toolbar form{display:flex;gap:8px;flex:1;min-width:240px;margin:0;}
.shop-toolbar input[type=text]{
  flex:1;min-width:150px;padding:9px 13px;border-radius:9px;border:1px solid var(--border);
  background:var(--surface2);color:var(--text);font-size:13.5px;outline:none;font-family:inherit;
}

.shop-table-wrap{overflow-x:auto;border:1px solid var(--border);border-radius:12px;}
table.shop-table{width:100%;border-collapse:collapse;font-size:13.5px;min-width:900px;}
table.shop-table th{
  background:var(--surface2);padding:11px 13px;text-align:left;font-size:12.5px;font-weight:700;
  color:var(--text2);border-bottom:1px solid var(--border);white-space:nowrap;
}
table.shop-table td{padding:11px 13px;border-bottom:1px solid var(--border);vertical-align:middle;color:var(--text);}
table.shop-table tr:last-child td{border-bottom:none;}
table.shop-table tbody tr:hover td{background:var(--surface2);}
table.shop-table .no{font-family:Consolas,Monaco,monospace;font-size:12.5px;font-weight:600;word-break:break-all;}
table.shop-table .sub{color:var(--text3);font-size:12px;margin-top:3px;}
table.shop-table .amt{font-weight:700;color:var(--primary);}
.shop-row-act{display:flex;flex-wrap:wrap;gap:6px;}

.shop-mini{
  padding:5px 11px;border-radius:7px;font-size:12px;border:1px solid var(--border);background:var(--surface);
  color:var(--text2);cursor:pointer;text-decoration:none;white-space:nowrap;transition:.2s;font-family:inherit;
}
.shop-mini:hover{border-color:var(--primary);color:var(--primary);}
.shop-mini.ok{background:var(--ok);border-color:var(--ok);color:#fff;}
.shop-mini.danger{background:var(--danger);border-color:var(--danger);color:#fff;}
.shop-mini.warn{background:var(--warn);border-color:var(--warn);color:#fff;}
.shop-mini.ok:hover,.shop-mini.danger:hover,.shop-mini.warn:hover{filter:brightness(1.08);color:#fff;}

.shop-pager{display:flex;gap:7px;align-items:center;justify-content:center;margin-top:16px;flex-wrap:wrap;}
.shop-pager a,.shop-pager span{
  padding:6px 12px;border-radius:8px;font-size:13px;border:1px solid var(--border);
  color:var(--text2);text-decoration:none;background:var(--surface);
}
.shop-pager .cur{background:var(--primary);color:#fff;border-color:var(--primary);font-weight:600;}

.shop-grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:0 16px;}
.shop-field{margin-bottom:16px;}
.shop-field > label{display:block;font-size:13.5px;font-weight:600;margin-bottom:7px;color:var(--text);}
.shop-field .hint{font-weight:400;color:var(--text3);font-size:12px;}
.shop-field input[type=text],.shop-field input[type=number],.shop-field input[type=password],
.shop-field input[type=email],.shop-field select,.shop-field textarea{
  width:100%;padding:10px 13px;border-radius:9px;border:1px solid var(--border);background:var(--surface2);
  color:var(--text);font-size:13.5px;outline:none;transition:.2s;font-family:inherit;
}
.shop-field input:focus,.shop-field select:focus,.shop-field textarea:focus{
  border-color:var(--primary);background:var(--surface);box-shadow:0 0 0 3px var(--primary-soft);
}
.shop-field textarea{min-height:92px;resize:vertical;line-height:1.65;}
.shop-check{display:flex;align-items:center;gap:8px;font-size:13.5px;margin-bottom:10px;cursor:pointer;color:var(--text);}
.shop-check input{width:16px;height:16px;cursor:pointer;accent-color:var(--primary);}
.shop-radio{display:flex;flex-wrap:wrap;gap:14px;margin-top:4px;}
.shop-radio label{display:flex;align-items:center;gap:7px;font-size:13.5px;cursor:pointer;font-weight:400;}

.shop-card{background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:16px 18px;margin-bottom:16px;}
.shop-card h4{margin:0 0 14px;font-size:14.5px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.shop-card h4 .tag-on{font-size:11px;padding:2px 9px;border-radius:20px;background:var(--ok-soft);color:var(--ok);font-weight:600;}
.shop-card h4 .tag-off{font-size:11px;padding:2px 9px;border-radius:20px;background:var(--surface3);color:var(--text3);font-weight:600;}

.shop-edit-panel{
  border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:26px;
  background:var(--surface);scroll-margin-top:80px;transition:border-color .2s;
}
.shop-edit-panel > h3{margin-top:0;}

.shop-prod-row{
  display:flex;gap:14px;align-items:center;padding:12px 14px;border:1px solid var(--border);
  border-radius:11px;margin-bottom:10px;background:var(--surface2);transition:.2s;
}
.shop-prod-row:hover{border-color:var(--border-strong);}
.shop-prod-row .thumb{
  width:54px;height:54px;flex-shrink:0;border-radius:10px;background:var(--surface3);display:flex;
  align-items:center;justify-content:center;font-size:25px;overflow:hidden;
}
.shop-prod-row .thumb img{width:100%;height:100%;object-fit:cover;}
.shop-prod-row .meta{flex:1;min-width:0;}
.shop-prod-row .meta .nm{font-weight:700;font-size:14px;margin-bottom:4px;color:var(--text);word-break:break-all;}
.shop-prod-row .meta .ds{font-size:12.5px;color:var(--text2);line-height:1.55;word-break:break-all;}
.shop-prod-row .meta .tg{font-size:11.5px;color:var(--text2);margin-top:5px;display:flex;flex-wrap:wrap;gap:5px;}
.shop-prod-row .meta .tg span{background:var(--surface3);padding:1px 8px;border-radius:10px;}
.shop-prod-row .price{font-weight:800;color:var(--primary);font-size:16px;white-space:nowrap;}
.shop-prod-row .acts{display:flex;gap:6px;flex-wrap:wrap;flex-shrink:0;}

.shop-cat-row{
  display:flex;gap:10px;align-items:center;padding:10px 13px;border:1px solid var(--border);
  border-radius:10px;margin-bottom:9px;background:var(--surface2);
}
.shop-cat-row .key{font-family:Consolas,monospace;font-size:12px;color:var(--text3);min-width:96px;}
.shop-cat-row input[type=text]{
  flex:1;padding:8px 12px;border-radius:8px;border:1px solid var(--border);background:var(--surface);
  color:var(--text);font-size:13.5px;font-family:inherit;
}

.shop-qr-preview{
  display:block;width:auto;height:auto;

  max-width:min(170px,42vw);max-height:170px;
  border-radius:12px;border:1px solid var(--border);
  cursor:zoom-in;transition:transform .2s,box-shadow .2s;
}
.shop-qr-preview:hover{transform:scale(1.03);box-shadow:0 10px 26px rgba(0,0,0,.18);}
.shop-qr-zoom-hint{max-width:min(170px,42vw);margin-top:8px;font-size:12px;color:var(--text3);text-align:center;}
.shop-qr-empty{
  max-width:min(170px,42vw);height:150px;border-radius:12px;border:2px dashed var(--border-strong);display:flex;
  align-items:center;justify-content:center;color:var(--text3);font-size:12.5px;text-align:center;padding:14px;line-height:1.7;
}

.qr-zoom{
  position:fixed;inset:0;z-index:10008;display:none;flex-direction:column;align-items:center;justify-content:center;
  gap:12px;padding:20px;background:rgba(8,14,26,.9);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
  overflow:hidden;
}
.qr-zoom.show{display:flex;}
.qr-zoom img{
  flex-shrink:0;width:auto;height:auto;
  max-width:min(88vw,66vh);max-height:min(88vw,66vh);
  border-radius:14px;box-shadow:0 24px 70px rgba(0,0,0,.5);
}
.qr-zoom .qz-close{
  position:absolute;top:16px;right:16px;width:42px;height:42px;border-radius:50%;border:none;
  background:rgba(255,255,255,.16);color:#fff;font-size:24px;line-height:1;cursor:pointer;transition:.2s;
}
.qr-zoom .qz-close:hover{background:rgba(255,255,255,.3);}

.qr-zoom .qz-addr{
  flex-shrink:0;max-width:min(560px,80vw);color:rgba(255,255,255,.5);font-size:11.5px;line-height:1.6;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.qr-zoom .qz-hint{max-width:460px;color:rgba(255,255,255,.65);font-size:12.5px;line-height:1.7;text-align:center;}
.qr-zoom .qz-open{
  display:inline-block;padding:8px 18px;border-radius:9px;border:1px solid rgba(255,255,255,.3);
  color:#fff;font-size:13px;text-decoration:none;transition:.2s;
}
.qr-zoom .qz-open:hover{background:rgba(255,255,255,.14);color:#fff;}
.shop-code-preview{font-size:38px;font-weight:800;letter-spacing:10px;color:var(--primary);font-family:Consolas,Monaco,monospace;padding-left:10px;}

.shop-log{max-height:330px;overflow-y:auto;border:1px solid var(--border);border-radius:11px;}

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

.shop-log-row{display:flex;gap:11px;align-items:flex-start;padding:10px 13px;border-bottom:1px solid var(--border);font-size:12.5px;color:var(--text);}
.shop-log-row:last-child{border-bottom:none;}
.shop-log-row .ok{color:var(--ok);font-weight:700;}
.shop-log-row .no{color:var(--danger);font-weight:700;}
.shop-log-row .tm{color:var(--text3);white-space:nowrap;margin-left:auto;}

.shop-modal-mask{
  display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:10005;align-items:center;
  justify-content:center;padding:20px;backdrop-filter:blur(4px);
}
.shop-modal-mask.show{display:flex;}
.shop-modal{
  background:var(--surface);border:1px solid var(--border);border-radius:16px;max-width:640px;width:100%;
  max-height:88vh;overflow-y:auto;box-shadow:var(--shadow-hover);
}
.shop-modal .hd{display:flex;justify-content:space-between;align-items:center;padding:16px 22px;border-bottom:1px solid var(--border);}
.shop-modal .hd h3{margin:0;font-size:16.5px;color:var(--text);}
.shop-modal .bd{padding:20px 22px;color:var(--text);}
.shop-modal .ft{padding:14px 22px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;flex-shrink:0;}
.shop-dl{display:grid;grid-template-columns:auto 1fr;gap:9px 16px;font-size:13.5px;}
.shop-dl dt{color:var(--text3);white-space:nowrap;}
.shop-dl dd{margin:0;font-weight:600;color:var(--text);word-break:break-all;}
.shop-timeline{margin-top:16px;padding-top:14px;border-top:1px dashed var(--border);}
.shop-timeline .tl{display:flex;gap:10px;font-size:13px;padding:6px 0;color:var(--text2);}
.shop-timeline .tl b{color:var(--text);}

.shop-guide{
  background:var(--info-soft);border:1px solid var(--info-border);border-radius:12px;
  padding:14px 16px;margin-bottom:18px;font-size:13.5px;line-height:1.95;color:var(--text);
}
.shop-guide b{color:var(--info-text);}
.shop-ch{border:1px solid var(--border);border-radius:12px;margin-bottom:12px;background:var(--surface2);overflow:hidden;}
.shop-ch > summary{
  display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:14px 16px;cursor:pointer;
  list-style:none;font-size:14.5px;font-weight:700;color:var(--text);user-select:none;transition:background .2s;
}
.shop-ch > summary::-webkit-details-marker{display:none;}
.shop-ch > summary::before{content:"▸";color:var(--text3);font-size:13px;transition:transform .2s;flex-shrink:0;}
.shop-ch[open] > summary::before{transform:rotate(90deg);}
.shop-ch > summary:hover{background:var(--surface3);}
.shop-ch[open] > summary{border-bottom:1px solid var(--border);}
.shop-ch .ch-state{font-size:11px;padding:2px 9px;border-radius:20px;font-weight:600;flex-shrink:0;}
.shop-ch .ch-state.on{background:var(--ok-soft);color:var(--ok);}
.shop-ch .ch-state.off{background:var(--surface3);color:var(--text3);}
.shop-ch .ch-hint{font-size:12px;font-weight:400;color:var(--text3);}
.shop-ch .ch-target{
  margin-left:auto;font-size:12px;font-weight:400;color:var(--text3);font-family:Consolas,Monaco,monospace;
  max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.shop-ch .ch-body{padding:16px 18px 6px;}
.shop-ch details.shop-sub{border-top:1px dashed var(--border);margin-top:4px;padding-top:12px;}
.shop-ch details.shop-sub > summary{cursor:pointer;font-size:13px;font-weight:600;color:var(--text2);list-style:none;}
.shop-ch details.shop-sub > summary::-webkit-details-marker{display:none;}
.shop-ch details.shop-sub > summary::before{content:"＋ ";color:var(--text3);}
.shop-ch details.shop-sub[open] > summary::before{content:"− ";}

.shop-vars{width:100%;border-collapse:collapse;font-size:13px;}
.shop-vars th,.shop-vars td{text-align:left;padding:8px 10px;border-bottom:1px solid var(--border);vertical-align:top;}
.shop-vars th{color:var(--text3);font-weight:600;font-size:12.5px;background:var(--surface3);}
.shop-vars tr:last-child td{border-bottom:none;}
.shop-vars td:first-child{white-space:nowrap;}
.shop-vars td:nth-child(1){color:var(--text);font-weight:600;}
.shop-vars td:nth-child(2){color:var(--text2);}
.shop-vars td:nth-child(3){color:var(--text2);font-size:12.5px;}
.shop-vars th{color:var(--text2);}
.shop-vars code{background:var(--surface3);padding:1px 7px;border-radius:5px;font-size:12.5px;}

.shop-note{
  margin-top:10px;background:var(--info-soft);border:1px solid var(--info-border);
  border-left:3px solid var(--primary);border-radius:8px;padding:12px 15px;
  font-size:13px;line-height:1.9;color:var(--text);
}
.shop-note-title{font-weight:700;color:var(--info-text);margin-bottom:7px;font-size:13.5px;}
.shop-note ul{margin:0;padding-left:19px;}
.shop-note li{margin-bottom:4px;}
.shop-note li:last-child{margin-bottom:0;}
.shop-note b{color:var(--text);}
.shop-note code{
  background:var(--surface);border:1px solid var(--info-border);padding:1px 7px;
  border-radius:5px;font-size:12.5px;color:var(--info-text);font-family:Consolas,Monaco,monospace;
}

table.shop-table .amt,
table.shop-table .col-amount,
table.shop-table .col-contact,
table.shop-table .col-status,
table.shop-table td:nth-child(3),
table.shop-table .no { white-space: nowrap; }

table.shop-table td .shop-row-act { white-space: normal; }

.shop-remark-chip{
  font: inherit; font-size: 12px; cursor: pointer; white-space: nowrap;
  padding: 3px 10px; border-radius: 999px;
  background: var(--warn-soft); border: 1px solid var(--warn); color: var(--warn);
}
.shop-remark-chip:hover{ filter: brightness(.95); }
.shop-remark-body{
  white-space: pre-wrap; word-break: break-word; line-height: 1.75; font-size: 13.5px;
  background: var(--surface2); border: 1px solid var(--border); border-radius: 10px;
  padding: 14px 16px; max-height: 52vh; overflow: auto;
}

@media (max-width: 768px) {
  .shop-stats { grid-template-columns: repeat(2, 1fr); }
  .shop-grid2 { grid-template-columns: 1fr; }
  .shop-tabs { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .shop-row-act { flex-wrap: wrap; gap: 6px; }
  .shop-mini { font-size: 11.5px; padding: 5px 9px; }
  .shop-edit-panel, .shop-card { padding: 14px; }
  .shop-edit-panel { margin-bottom: 16px; }

  table.shop-table { min-width: 0; }
  table.shop-table th, table.shop-table td { padding: 9px 8px; font-size: 12.5px; }
  table.shop-table th[style*="min-width"] { min-width: 0 !important; }

  table.shop-table .col-contact,
  table.shop-table .col-goods,
  table.shop-table .col-time { display: none; }

  .shop-filter-bar { flex-wrap: wrap; }
  .shop-order-cards { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {

  .shop-stats { grid-template-columns: repeat(2, 1fr); gap: 8px; }
  .shop-stat { padding: 12px; }
  .shop-tab { font-size: 12.5px; padding: 7px 12px; }
}



details.shop-ch.plain{border:none;background:none;border-radius:0;margin:0;overflow:visible;}
details.shop-ch.plain > summary{
  display:inline-flex;align-items:center;gap:5px;
  padding:0;margin-top:7px;background:none;
  font-size:12.5px;font-weight:600;color:var(--primary);line-height:1.5;
}
details.shop-ch.plain > summary:hover{background:none;text-decoration:underline;}
details.shop-ch.plain > summary::before{content:"\25B8";font-size:11px;color:currentColor;}
details.shop-ch.plain[open] > summary{border-bottom:none;}
details.shop-ch.plain > .ch-body{
  padding:9px 12px 10px;margin-top:8px;font-size:12.8px;line-height:1.9;color:var(--text2);
  background:var(--surface2);border-left:3px solid var(--primary-soft);border-radius:0 8px 8px 0;
}
.shop-unit{position:relative;display:block;}
.shop-unit input[type=number]{
  width:100%;padding:10px 62px 10px 14px !important;
  font-size:16px !important;font-weight:700 !important;color:var(--primary) !important;
  letter-spacing:.5px;
}
.shop-unit .u{
  position:absolute;right:0;top:0;bottom:0;width:58px;
  display:flex;align-items:center;justify-content:center;
  background:var(--surface3);border-left:1px solid var(--border);
  border-radius:0 9px 9px 0;font-size:12.5px;font-weight:600;color:var(--text2);
  pointer-events:none;
}
.shop-code-wrap{display:grid;grid-template-columns:auto 1fr;gap:0 26px;align-items:start;margin-top:14px;}
.shop-code-demo{
  text-align:center;padding:14px 18px;background:var(--surface3);
  border-radius:10px;min-width:150px;
}
.shop-code-demo .cd-l{font-size:12px;color:var(--text3);margin-bottom:4px;}
.shop-code-demo .shop-code-preview{padding-left:0;letter-spacing:8px;font-size:36px;}
.shop-code-demo .cd-h{font-size:11.5px;color:var(--text3);margin-top:6px;line-height:1.55;}
@media (max-width:760px){
  .shop-code-wrap{grid-template-columns:1fr;gap:14px;}
  .shop-code-demo{min-width:0;}
}
.shop-filter{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:16px 0;}
.shop-filter select,
.shop-filter input[type=text]{
  height:38px;padding:0 13px;border-radius:9px;border:1px solid var(--border);
  background:var(--surface2);color:var(--text);font-size:13.5px;font-family:inherit;outline:none;
  transition:border-color .2s,background .2s;
}
.shop-filter select:focus,
.shop-filter input[type=text]:focus{border-color:var(--primary);background:var(--surface);}
.shop-filter select{min-width:210px;}
.shop-filter input[type=text]{flex:1;min-width:200px;}
.shop-filter .btn{height:38px;padding:0 22px;display:inline-flex;align-items:center;}
.shop-vars{font-size:12.8px;}
.shop-vars th,.shop-vars td{padding:6px 10px;}
details.shop-ch .ch-body.tpl-open{
  padding:14px 16px 16px;background:var(--primary-soft);
  border-left:3px solid var(--primary);border-radius:0 0 8px 8px;
}
details.shop-ch .ch-body.tpl-open textarea{
  border-color:var(--primary) !important;background:var(--surface) !important;
  box-shadow:0 0 0 3px var(--primary-soft);
}

/* ===== 全后台控件统一标准（覆盖前面各处的零散写法）===== */
.shop-toolbar input[type=text],
.shop-toolbar input[type=search],
.shop-cat-row input[type=text]{
  padding:9px 13px;border-radius:9px;border:1px solid var(--border);
  background:var(--surface2);color:var(--text);font-size:13.5px;font-family:inherit;outline:none;
  transition:border-color .2s,background .2s,box-shadow .2s;
}
.shop-toolbar input[type=text]:focus,
.shop-toolbar input[type=search]:focus,
.shop-cat-row input[type=text]:focus{
  border-color:var(--primary);background:var(--surface);box-shadow:0 0 0 3px var(--primary-soft);
}
.shop-toolbar .btn,
.shop-filter .btn,
.shop-cat-row .btn{height:38px;padding:0 18px;display:inline-flex;align-items:center;justify-content:center;}

/* ===== 自绘勾选框 / 单选：跨浏览器外观一致 ===== */
.shop-check input[type=checkbox],
.shop-check input[type=radio],
.shop-radio input[type=checkbox],
.shop-radio input[type=radio],
.shop-table input[type=checkbox],
#shop-check-all,
.shop-row-check{
  -webkit-appearance:none;appearance:none;margin:0;
  width:17px;height:17px;flex:0 0 17px;position:relative;
  border:1.5px solid var(--border-strong);border-radius:5px;
  background:var(--surface);cursor:pointer;
  transition:background .15s,border-color .15s;
  vertical-align:middle;
}
.shop-check input[type=radio],
.shop-radio input[type=radio]{border-radius:50%;}
.shop-check input[type=checkbox]:hover,
.shop-check input[type=radio]:hover,
.shop-radio input:hover,
.shop-table input[type=checkbox]:hover{border-color:var(--primary);}
.shop-check input[type=checkbox]:checked,
.shop-check input[type=radio]:checked,
.shop-radio input:checked,
.shop-table input[type=checkbox]:checked{
  background:var(--primary);border-color:var(--primary);
}
.shop-check input[type=checkbox]:checked::after,
.shop-table input[type=checkbox]:checked::after{
  content:"";position:absolute;left:5px;top:1.5px;width:4px;height:9px;
  border:solid #fff;border-width:0 2px 2px 0;transform:rotate(45deg);
}
.shop-check input[type=radio]:checked::after,
.shop-radio input[type=radio]:checked::after{
  content:"";position:absolute;left:4.5px;top:4.5px;width:6px;height:6px;
  border-radius:50%;background:#fff;
}
.shop-check input:focus-visible,
.shop-radio input:focus-visible,
.shop-table input[type=checkbox]:focus-visible{
  outline:none;box-shadow:0 0 0 3px var(--primary-soft);
}
.shop-check{margin-bottom:9px;line-height:1.6;}
.shop-table th input[type=checkbox]{margin:0;}

.col-pay{width:100px;white-space:nowrap;}
.shop-table .shop-pm-tag{font-size:11.5px;padding:1px 8px;}
.shop-pm-tag{
  display:inline-flex;align-items:center;gap:4px;
  padding:2px 10px;border-radius:20px;font-size:12.5px;font-weight:600;
  background:var(--ok-soft);color:var(--ok);border:1px solid var(--ok);white-space:nowrap;
}
.shop-pm-tag.agg{
  background:var(--surface3);color:var(--text2);border-color:var(--border-strong);
}
.shop-toolbar input[type=text],
.shop-toolbar input[type=search]{height:38px;padding:0 13px;}
.shop-toolbar .btn,
.shop-toolbar .btn-sm,
.shop-filter .btn,
.shop-filter .btn-sm{height:38px;padding:0 18px;font-size:13.5px;}

.shop-audit-table{min-width:1120px;table-layout:fixed;}
.shop-audit-table th:nth-child(1),.shop-audit-table td:nth-child(1){width:166px;white-space:nowrap;}
.shop-audit-table th:nth-child(2),.shop-audit-table td:nth-child(2){width:134px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.shop-audit-table th:nth-child(3),.shop-audit-table td:nth-child(3){width:236px;}
.shop-audit-table td:nth-child(4){width:auto;line-height:1.7;}
.shop-audit-table th:nth-child(5),.shop-audit-table td:nth-child(5){width:86px;white-space:nowrap;}
.shop-audit-table th:nth-child(6),.shop-audit-table td:nth-child(6){width:114px;white-space:nowrap;font-size:12px;color:var(--text3);}
.shop-audit-table td:nth-child(1){font-size:12.5px;color:var(--text2);}
.shop-audit-table td.a-target{
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
  font-family:Consolas,Monaco,monospace;font-size:12.5px;
}
.shop-audit-table td:nth-child(4) .hint{margin-top:2px;display:block;}

#shopNotifyBtn.on{background:var(--ok);border-color:var(--ok);color:#fff;font-weight:700;}
#shopNotifyBtn.on:hover{filter:brightness(1.06);color:#fff;}

#shopSyncBar{
  display:flex;align-items:center;gap:9px;flex-wrap:wrap;
  margin:0 0 14px;padding:9px 14px;border-radius:9px;
  background:var(--primary-soft);border:1px solid var(--primary);
  font-size:13px;font-weight:600;color:var(--primary);
  animation:ssbIn .25s ease-out;
}
@keyframes ssbIn{from{opacity:0;transform:translateY(-4px);}to{opacity:1;transform:none;}}
#shopSyncBar .ssb-dot{
  width:7px;height:7px;border-radius:50%;background:var(--primary);flex:0 0 7px;
  animation:ssbPulse 1.1s ease-in-out infinite;
}
@keyframes ssbPulse{0%,100%{opacity:1;transform:scale(1);}50%{opacity:.35;transform:scale(.7);}}
#shopSyncBar b{font-weight:800;}
#shopSyncBar .ssb-btn{
  margin-left:auto;padding:4px 14px;border-radius:20px;cursor:pointer;
  border:1px solid var(--primary);background:var(--primary);color:#fff;
  font:inherit;font-size:12.5px;font-weight:700;transition:.15s;
}
#shopSyncBar .ssb-btn:hover{filter:brightness(1.08);}

#shopDiagPanel{
  margin:0 0 14px;padding:12px 15px;border-radius:10px;
  background:var(--surface2);border:1px solid var(--border);
  font-size:12.8px;line-height:1.5;
}
#shopDiagPanel .sd-head{
  display:flex;align-items:center;gap:8px;flex-wrap:wrap;
  font-weight:800;font-size:13.5px;color:var(--text);margin-bottom:10px;
}
#shopDiagPanel .sd-btn{
  margin-left:0;padding:3px 11px;border-radius:20px;cursor:pointer;
  border:1px solid var(--border);background:var(--surface);color:var(--text2);
  font:inherit;font-size:12px;font-weight:600;
}
#shopDiagPanel .sd-btn:hover{border-color:var(--primary);color:var(--primary);}
#shopDiagPanel .sd-btn:first-of-type{margin-left:auto;}
#shopDiagPanel .sd-row{display:flex;gap:10px;padding:3px 0;border-top:1px dashed var(--border);}
#shopDiagPanel .sd-row:first-of-type{border-top:none;}
#shopDiagPanel .sd-k{flex:0 0 148px;color:var(--text3);}
#shopDiagPanel .sd-v{flex:1;min-width:0;word-break:break-all;font-family:Consolas,Monaco,monospace;}
#shopDiagPanel .sd-v.good{color:var(--ok);font-weight:700;}
#shopDiagPanel .sd-v.bad{color:#dc2626;font-weight:700;}
</style>

<?php  ?>
<?php if ($shopSub === 'orders'): ?>
<?php

if ($dbReady && function_exists('shop_push_process_queue')) {
    register_shutdown_function(function () {
        if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
        @ignore_user_abort(true);
        @set_time_limit(20);
        try { shop_push_process_queue(5, 6); } catch (Throwable $e) {}
    });
}
$statusFilter = isset($_GET['status']) ? (string)$_GET['status'] : 'all';
$qFilter      = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$pageNo       = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$list         = $dbReady ? shop_order_list(['status' => $statusFilter, 'q' => $qFilter, 'page' => $pageNo, 'per' => 20])
                         : ['total'=>0,'rows'=>[],'page'=>1,'pages'=>0];
$tabs         = shop_status_tabs();
$byStatus     = $shopStats['by_status'];
$tabUrl = function ($st) use ($qFilter) {
    $u = '?sub=orders&status=' . urlencode($st);
    if ($qFilter !== '') $u .= '&q=' . urlencode($qFilter);
    return $u;
};
?>

<div class="card">
  <h2>商城订单管理</h2>

  <?php if (!$dbReady): ?>
    <div class="alert alert-error">⚠️ <strong>数据库不可用：</strong><?php echo htmlspecialchars(shop_db_unavailable_hint()); ?></div>


  <?php elseif ($shopStats['awaiting_verify'] > 0): ?>
    <div class="alert alert-error" style="background:var(--info-soft);border-color:var(--info-border);color:var(--info-text);">
      🔔 <strong>有 <?php echo (int)$shopStats['awaiting_verify']; ?> 笔订单等待人工核查付款</strong> —— 请核对收款账单后点击「确认已付款」。
    </div>
  <?php endif; ?>

  <div class="shop-stats">
    <div class="shop-stat"><div class="v" style="color:var(--primary);"><?php echo (int)$shopStats['total']; ?></div><div class="t">总订单</div></div>
    <div class="shop-stat"><div class="v" style="color:var(--info-text);"><?php echo (int)$shopStats['today']; ?></div><div class="t">今日订单</div></div>
    <div class="shop-stat <?php echo $shopStats['pending_payment'] > 0 ? 'alert' : ''; ?>"><div class="v" style="color:var(--text3);"><?php echo (int)$shopStats['pending_payment']; ?></div><div class="t">待付款</div></div>
    <div class="shop-stat <?php echo $shopStats['awaiting_verify'] > 0 ? 'alert' : ''; ?>"><div class="v" style="color:var(--danger);"><?php echo (int)$shopStats['awaiting_verify']; ?></div><div class="t">待人工核查</div></div>
    <div class="shop-stat"><div class="v" style="color:var(--ok);"><?php echo (int)$shopStats['paid']; ?></div><div class="t">已确认付款</div></div>
    <div class="shop-stat"><div class="v" style="color:var(--ok);"><?php echo (int)$shopStats['completed']; ?></div><div class="t">已完成</div></div>
    <div class="shop-stat"><div class="v" style="color:var(--warn);">￥<?php echo shop_price($shopStats['income']); ?></div><div class="t">累计收入</div></div>
  </div>

  <?php

  $__expired = $dbReady ? shop_expired_count() : 0;
  $__pendRaw = 0;
  if ($dbReady) {
      $__pgDb = shop_db();
      if ($__pgDb) {
          try { $__pendRaw = (int)$__pgDb->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending_payment','payment_failed')")->fetchColumn(); }
          catch (Throwable $e) { $__pendRaw = 0; }
      }
  }
  ?>
  <?php if ($__expired > 0 && $statusFilter !== 'expired'): ?>
    <div class="alert" style="background:var(--info-soft);border-color:var(--info-border);color:var(--info-text);margin-bottom:14px;">
      ⏳ <strong>有 <?php echo $__expired; ?> 笔订单提交后未付款（已失效）</strong>
      —— 这些订单<strong>记录仍然保留</strong>，可以点开查看客户填写的全部信息（称呼 / 微信 / 电话 / 商品 / 备注 / 下单 IP）。
      <a href="<?php echo htmlspecialchars($tabUrl('expired')); ?>" style="color:inherit;font-weight:700;text-decoration:underline;">立即查看 ›</a>
    </div>
  <?php elseif ($statusFilter !== 'expired' && $__pendRaw > 0): ?>
    <div class="alert" style="background:var(--info-soft);border-color:var(--info-border);color:var(--info-text);margin-bottom:14px;">
      💳 <strong>有 <?php echo $__pendRaw; ?> 笔订单等待客户付款</strong>
      —— 点每行右侧的「详情」可查看客户填写的全部信息。超过设定时间未付款的会转为「已失效」，<strong>记录同样保留</strong>。
    </div>
  <?php endif; ?>

  <div class="shop-tabs">
    <?php foreach ($tabs as $k => $label):
      $cnt = ($k === 'all') ? $shopStats['total'] : ($byStatus[$k] ?? 0);
    ?>
      <a class="shop-tab <?php echo $statusFilter === $k ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($tabUrl($k)); ?>">
        <?php echo htmlspecialchars($label); ?><span class="cnt">(<?php echo (int)$cnt; ?>)</span>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="shop-toolbar">
    <form method="get" action="admin.php">
        <input type="hidden" name="sub" value="orders">
      <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
      <input type="text" name="q" value="<?php echo htmlspecialchars($qFilter); ?>" placeholder="搜索订单号 / 联系人 / 微信 / 电话 / 备注码 / 商品名…">
      <button type="submit" class="btn btn-primary btn-sm" style="flex:0 0 auto;">搜索</button>
      <?php if ($qFilter !== ''): ?>
        <a class="btn btn-secondary btn-sm" style="flex:0 0 auto;" href="?sub=orders&status=<?php echo urlencode($statusFilter); ?>">清空</a>
      <?php endif; ?>
    </form>
    <a class="btn btn-secondary btn-sm" style="flex:0 0 auto;"
       href="?sub=orders&export=csv&status=<?php echo urlencode($statusFilter); ?>&q=<?php echo urlencode($qFilter); ?>">⬇ 导出 CSV</a>
    <button type="button" class="btn btn-secondary btn-sm" id="shopNotifyBtn" onclick="shopAskNotify()" style="flex:0 0 auto;">🔔 桌面通知</button>
    <button type="button" class="btn btn-secondary btn-sm" id="shopDiagBtn" onclick="shopDiag()" style="flex:0 0 auto;">🔧 自检</button>
  </div>

  <div id="shopDiagPanel" style="display:none;"></div>

  <div id="shopSyncBar" style="display:none;">
    <span class="ssb-dot"></span>
    <span>有 <b id="shopSyncCount">1</b> 笔订单变化，列表还没更新</span>
    <button type="button" class="ssb-btn" onclick="shopForceRefresh()">立即刷新</button>
  </div>

  <?php if (!$dbReady): ?>
    <div class="empty">数据库不可用，无法读取订单</div>
  <?php elseif (empty($list['rows'])): ?>
    <div class="empty"><?php echo $qFilter !== '' || $statusFilter !== 'all' ? '没有符合条件的订单' : '暂无订单'; ?></div>
  <?php else: ?>

  <form method="post" id="shop-bulk-form">
    <input type="hidden" name="action" value="shop_order_bulk"><?php echo csrf_field(); ?>
    <input type="hidden" name="back_status" value="<?php echo htmlspecialchars($statusFilter); ?>">
    <input type="hidden" name="back_q" value="<?php echo htmlspecialchars($qFilter); ?>">
    <input type="hidden" name="back_page" value="<?php echo (int)$list['page']; ?>">

    <div style="display:flex;gap:10px;align-items:center;margin-bottom:12px;flex-wrap:wrap;">
      <button type="button" class="btn btn-danger btn-sm" onclick="shopBulkSubmit('delete')">🗑 批量删除</button>
      <button type="button" class="btn btn-success btn-sm" onclick="shopBulkSubmit('paid')">✅ 批量确认已付款</button>
      <button type="button" class="btn btn-secondary btn-sm" onclick="shopBulkSubmit('processing')">🔧 批量转处理中</button>
      <button type="button" class="btn btn-secondary btn-sm" onclick="shopBulkSubmit('completed')">🎉 批量标记完成</button>
      <span id="shop-sel-count" style="font-size:13px;color:var(--text3);">已选 0 条</span>
    </div>

    <div class="shop-table-wrap">
      <table class="shop-table">
        <thead>
          <tr>
            <th style="width:36px;"><input type="checkbox" id="shop-check-all" onclick="shopToggleAll(this)"></th>
            <th>订单号 / 备注码</th>
            <th>联系人</th>
            <th class="col-contact">联系方式</th>
            <th class="col-goods">商品</th>
            <th class="col-remark">备注</th>
            <th class="col-amount">金额</th>
            <th class="col-pay">支付方式</th>
            <th class="col-status">状态</th>
            <th class="col-time">时间</th>
            <th style="min-width:190px;">操作</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($list['rows'] as $o): ?>
          <tr>
            <td><input type="checkbox" class="shop-row-check" name="order_nos[]" value="<?php echo htmlspecialchars($o['order_no'], ENT_QUOTES); ?>" onclick="shopUpdateCount()"></td>
            <td>
              <div class="no"><?php echo htmlspecialchars($o['order_no']); ?></div>
              <span class="shop-code-badge" title="付款备注码"><?php echo htmlspecialchars($o['pay_code']); ?></span>
              <?php if (($o['mode'] ?? 'now') === 'booking'): ?>
                <div class="sub">📅 <?php echo htmlspecialchars($o['book_time']); ?></div>
              <?php endif; ?>
            </td>
            <td><span style="font-weight:600;"><?php echo htmlspecialchars($o['customer_name']); ?></span></td>
            <?php

              $ctBits = [];
              if (trim((string)$o['wechat']) !== '') $ctBits[] = htmlspecialchars($o['wechat']);
              if (trim((string)$o['phone'])  !== '') $ctBits[] = htmlspecialchars($o['phone']);
            ?>
            <td class="col-contact"><?php echo $ctBits ? implode(' / ', $ctBits) : '<span class="sub">—</span>'; ?></td>
            <td class="col-goods"><?php echo htmlspecialchars($o['items_text']); ?></td>
            <td class="col-remark">
              <?php if (trim((string)$o['remark']) !== ''): ?>
                <button type="button" class="shop-remark-chip" onclick="shopRemark('<?php echo htmlspecialchars($o['order_no'], ENT_QUOTES); ?>')">📝 查看备注</button>
              <?php endif; ?>
            </td>
            <td class="amt col-amount">￥<?php echo shop_price($o['amount']); ?></td>
            <td class="col-pay"><?php
              $pmL = (string)($o['pay_method_label'] ?? '');
              if ($pmL !== '') {
                  echo '<span class="shop-pm-tag">' . htmlspecialchars($pmL) . '</span>';
              } elseif (($shopData['pay']['qr_mode'] ?? 'aggregate') !== 'split') {
                  echo '<span class="shop-pm-tag agg">聚合码</span>';
              } else {
                  echo '<span class="sub" title="「微信 / 支付宝分开」模式，但客户下单时未点选支付方式">未选</span>';
              }
            ?></td>
            <td class="col-status"><?php echo shop_status_badge($o['status']); ?></td>
            <td class="col-time">
              <div><?php echo htmlspecialchars($o['created_at']); ?></div>
              <?php if ($o['pay_marked_at'] !== ''): ?><div class="sub">标记付款 <?php echo htmlspecialchars($o['pay_marked_at']); ?></div><?php endif; ?>
            </td>
            <td>
              <div class="shop-row-act">
                <button type="button" class="shop-mini" onclick="shopOrderDetail('<?php echo htmlspecialchars($o['order_no'], ENT_QUOTES); ?>')">详情</button>
                <?php if (in_array($o['status'], ['pending_payment', 'awaiting_verify', 'payment_failed'], true)): ?>
                  <a class="shop-mini ok" href="<?php echo htmlspecialchars($shopUrl('?sub=orders&shop_order_set=' . urlencode($o['order_no']) . '&to=paid&back=' . urlencode($statusFilter))); ?>"
                     onclick="return shopConfirmPaid(this.getAttribute('href'), '<?php echo htmlspecialchars($o['order_no'], ENT_QUOTES); ?>');">确认已付款</a>
                <?php endif; ?>
                <?php if (in_array($o['status'], ['pending_payment', 'awaiting_verify'], true)): ?>
                  <a class="shop-mini danger" href="<?php echo htmlspecialchars($shopUrl('?sub=orders&shop_order_set=' . urlencode($o['order_no']) . '&to=payment_failed&back=' . urlencode($statusFilter))); ?>"
                     onclick="return shopConfirm(this.getAttribute('href'), '标记为「付款失败」？<br><br>订单号：<b><?php echo htmlspecialchars($o['order_no'], ENT_QUOTES); ?></b><br><br>标记后客户可在前台重新付款。', '标记付款失败');">付款失败</a>
                <?php endif; ?>
                <?php if ($o['status'] === 'paid'): ?>
                  <a class="shop-mini warn" href="<?php echo htmlspecialchars($shopUrl('?sub=orders&shop_order_set=' . urlencode($o['order_no']) . '&to=processing&back=' . urlencode($statusFilter))); ?>"
                     onclick="return shopConfirm(this.getAttribute('href'), '将订单标记为「处理中」？<br><br>订单号：<b><?php echo htmlspecialchars($o['order_no'], ENT_QUOTES); ?></b><br><br>表示技术员已开始为该客户服务。', '开始处理');">开始处理</a>
                <?php endif; ?>
                <?php if (in_array($o['status'], ['paid', 'processing'], true)): ?>
                  <a class="shop-mini ok" href="<?php echo htmlspecialchars($shopUrl('?sub=orders&shop_order_set=' . urlencode($o['order_no']) . '&to=completed&back=' . urlencode($statusFilter))); ?>"
                     onclick="return shopConfirm(this.getAttribute('href'), '将订单标记为「已完成」？<br><br>订单号：<b><?php echo htmlspecialchars($o['order_no'], ENT_QUOTES); ?></b><br><br>表示服务已交付完成。', '标记完成');">标记完成</a>
                <?php endif; ?>
                <a class="shop-mini danger" href="javascript:void(0)"
                   onclick="confirmDelete('?sub=orders&del_shop_order=<?php echo urlencode($o['order_no']); ?>&back=<?php echo urlencode($statusFilter); ?>', '订单 <?php echo js_attr($o['order_no']); ?>')">删除</a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="shop-pager">
      <?php
      $pg = (int)$list['page'];
      $tp = (int)$list['pages'];
      $mk = function ($p) use ($statusFilter, $qFilter) {
          return '?sub=orders&status=' . urlencode($statusFilter) . '&q=' . urlencode($qFilter) . '&page=' . $p;
      };
      ?>
      <?php if ($pg > 1): ?><a href="<?php echo htmlspecialchars($mk(1)); ?>">« 首页</a><a href="<?php echo htmlspecialchars($mk($pg - 1)); ?>">‹ 上一页</a><?php endif; ?>
      <span class="cur">第 <?php echo $pg; ?> / <?php echo max(1, $tp); ?> 页 · 共 <?php echo (int)$list['total']; ?> 条</span>
      <?php if ($pg < $tp): ?><a href="<?php echo htmlspecialchars($mk($pg + 1)); ?>">下一页 ›</a><a href="<?php echo htmlspecialchars($mk($tp)); ?>">末页 »</a><?php endif; ?>
    </div>
  </form>

  <div class="shop-modal-mask" id="shop-order-modal" onclick="if(event.target===this)shopCloseDetail()">
    <div class="shop-modal">
      <div class="hd">
        <h3>🧾 订单详情</h3>
        <button class="btn btn-secondary btn-sm" onclick="shopCloseDetail()">关闭</button>
      </div>
      <div class="bd" id="shop-order-detail"></div>
    </div>
  </div>

  <div class="shop-modal-mask" id="shop-remark-modal" onclick="if(event.target===this)shopCloseRemark()">
    <div class="shop-modal" style="max-width:540px;">
      <div class="hd">
        <h3>📝 客户备注</h3>
        <button class="btn btn-secondary btn-sm" onclick="shopCloseRemark()">关闭</button>
      </div>
      <div class="bd">
        <div class="sub" id="shop-remark-meta" style="margin-bottom:10px;"></div>
        <div class="shop-remark-body" id="shop-remark-body"></div>
      </div>
    </div>
  </div>

  <div class="shop-modal-mask" id="shop-confirm" onclick="if(event.target===this)shopConfirmClose()">
    <div class="shop-modal" style="max-width:420px;">
      <div class="hd">
        <h3 id="shop-confirm-title">确认操作</h3>
        <button class="btn btn-secondary btn-sm" onclick="shopConfirmClose()">关闭</button>
      </div>
      <div class="bd" id="shop-confirm-msg" style="font-size:14px;line-height:1.8;"></div>
      <div class="ft">
        <button class="btn btn-secondary btn-sm" onclick="shopConfirmClose()">取消</button>
        <button class="btn btn-success btn-sm" id="shop-confirm-ok" onclick="shopConfirmGo()">确定</button>
      </div>
    </div>
  </div>

  <script>
  var CONF_QR_SPLIT = <?php echo (($shopData['pay']['qr_mode'] ?? 'aggregate') === 'split') ? 'true' : 'false'; ?>;
  var SHOP_ORDERS = <?php
    $brief = [];
    foreach ($list['rows'] as $o) {
        $brief[$o['order_no']] = [
            'order_no'      => $o['order_no'],
            'pay_code'      => $o['pay_code'],
            'pay_method_label' => $o['pay_method_label'] ?? '',
            'status'        => $o['status'],
            'status_label'  => $o['status_label'],
            'customer_name' => $o['customer_name'],
            'wechat'        => $o['wechat'],
            'phone'         => $o['phone'],
            'items_text'    => $o['items_text'],
            'amount'        => shop_price($o['amount']),
            'mode_label'    => $o['mode_label'],
            'book_time'     => $o['book_time'],
            'remark'        => $o['remark'],
            'created_at'    => $o['created_at'],
            'pay_marked_at' => $o['pay_marked_at'],
            'verified_at'   => $o['verified_at'],
            'verified_by'   => $o['verified_by'],
            'admin_note'    => $o['admin_note'],
            'client_ip'     => $o['client_ip'],
        ];
    }
    echo json_encode($brief, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
  ?>;

  function shopEsc(s){
    return String(s == null ? '' : s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function shopToggleAll(cb){
    var list = document.querySelectorAll('.shop-row-check');
    for (var i = 0; i < list.length; i++) list[i].checked = cb.checked;
    shopUpdateCount();
  }
  function shopUpdateCount(){
    var n = document.querySelectorAll('.shop-row-check:checked').length;
    var el = document.getElementById('shop-sel-count');
    if (el) el.textContent = '已选 ' + n + ' 条';
  }

  var shopCfmAction = null;
  var SHOP_BULK_LABEL = {
    paid: '已确认付款', processing: '处理中', completed: '已完成',
    cancelled: '已取消', payment_failed: '付款失败', refunded: '已退款'
  };

  function shopCfmOpen(title, msg, okText, action){
    document.getElementById('shop-confirm-title').textContent = title;
    document.getElementById('shop-confirm-msg').innerHTML = msg;
    document.getElementById('shop-confirm-ok').textContent = okText || '确定';
    shopCfmAction = action;
    document.getElementById('shop-confirm').classList.add('show');
  }
  function shopConfirm(url, msg, okText){
    shopCfmOpen('确认操作', msg, okText, function(){ window.location.href = url; });
    return false;
  }
  function shopConfirmPaid(url, orderNo){
    var o = SHOP_ORDERS[orderNo] || {};
    var msg = '确认已收到该笔款项？<br><br>' +
      '订单号：<b>' + shopEsc(o.order_no || orderNo) + '</b><br>' +
      '付款备注码：<b>' + shopEsc(o.pay_code || '') + '</b><br>' +
      '金额：<b style="color:var(--primary)">￥' + shopEsc(o.amount || '') + '</b><br>' +
      '客户微信：' + shopEsc(o.wechat || '') + '<br><br>' +
      '<span style="color:var(--text3);font-size:12.5px;">请先核对收款账单，确认无误后再点确定。</span>';
    return shopConfirm(url, msg, '确认已收款');
  }
  function shopConfirmDo(msg, okText, cb){ shopCfmOpen('确认操作', msg, okText, cb); }
  function shopConfirmClose(){
    document.getElementById('shop-confirm').classList.remove('show');
    shopCfmAction = null;
  }
  function shopConfirmGo(){
    var f = shopCfmAction;
    shopConfirmClose();
    if (typeof f === 'function') f();
  }
  document.addEventListener('keydown', function(e){
    if (!document.getElementById('shop-confirm').classList.contains('show')) return;
    if (e.key === 'Escape') shopConfirmClose();
    if (e.key === 'Enter') shopConfirmGo();
  });

  function shopBulkSubmit(act){
    var n = document.querySelectorAll('.shop-row-check:checked').length;
    if (!n){
      showMessageModal('warning', '请先勾选订单', '请先勾选要处理的订单');
      return;
    }
    var tip = (act === 'delete')
      ? ('确定要删除所选 <b>' + n + '</b> 条订单吗？<br><br><span style="color:var(--danger);font-size:12.5px;">此操作无法撤销</span>')
      : ('确定将所选 <b>' + n + '</b> 条订单批量设为 <b>' + (SHOP_BULK_LABEL[act] || act) + '</b> 吗？');
    shopConfirmDo(tip, act === 'delete' ? '确认删除' : '确认', function(){
      var f = document.getElementById('shop-bulk-form');
      var input = document.createElement('input');
      input.type = 'hidden'; input.name = 'bulk_action'; input.value = act;
      f.appendChild(input);
      f.submit();
    });
  }

  function shopOrderDetail(no){
    var o = SHOP_ORDERS[no];
    if (!o) return;

    var h = '';
    if (o.status === 'pending_payment' || o.status === 'payment_failed') {
      h += '<div class="alert" style="background:var(--info-soft);border-color:var(--info-border);color:var(--info-text);margin-bottom:14px;">'
         + '💳 该订单<strong>尚未付款</strong>。下面是客户提交的完整信息，可直接联系客户核对。'
         + (o.created_at ? ('下单于 ' + shopEsc(o.created_at) + '。') : '') + '</div>';
    } else if (o.status === 'expired') {
      h += '<div class="alert" style="background:var(--warn-soft);border-color:var(--warn);color:var(--warn);margin-bottom:14px;">'
         + '⏳ 该订单<strong>提交后一直未付款，已自动失效</strong>（不计入统计）。'
         + '记录仍完整保留，下面是客户提交的全部信息。</div>';
    }
    h += '<dl class="shop-dl">' +
      '<dt>订单号</dt><dd>' + shopEsc(o.order_no) + '</dd>' +
      '<dt>付款备注码</dt><dd style="color:var(--primary);letter-spacing:2px;">' + shopEsc(o.pay_code) + '</dd>' +
      '<dt>支付方式</dt><dd>' + (o.pay_method_label
        ? ('<span class="shop-pm-tag">' + shopEsc(o.pay_method_label) + '</span>')
        : (CONF_QR_SPLIT
            ? '<span style="color:var(--text3);">未选（分开模式下客户未点选）</span>'
            : '<span class="shop-pm-tag agg">聚合码</span>')) + '</dd>' +
      '<dt>当前状态</dt><dd>' + shopEsc(o.status_label) + '</dd>' +
      '<dt>称呼</dt><dd>' + shopEsc(o.customer_name) + '</dd>' +
      '<dt>微信</dt><dd>' + shopEsc(o.wechat) + '</dd>' +
      '<dt>电话</dt><dd>' + shopEsc(o.phone) + '</dd>' +
      '<dt>商品</dt><dd>' + shopEsc(o.items_text) + '</dd>' +
      '<dt>金额</dt><dd style="color:var(--primary);">￥' + shopEsc(o.amount) + '</dd>' +
      '<dt>服务方式</dt><dd>' + shopEsc(o.mode_label) + '</dd>' +
      (o.book_time ? '<dt>预约时间</dt><dd>' + shopEsc(o.book_time) + '</dd>' : '') +
      (o.remark ? '<dt>客户备注</dt><dd>' + shopEsc(o.remark) + '</dd>' : '') +
      '<dt>下单时间</dt><dd>' + shopEsc(o.created_at) + '</dd>' +
      (o.client_ip ? '<dt>下单 IP</dt><dd>' + shopEsc(o.client_ip) +
        '<span id="shop-ipgeo" style="color:var(--text3);font-size:12.5px;"></span></dd>' : '') +
      (o.admin_note ? '<dt>管理员备注</dt><dd>' + shopEsc(o.admin_note) + '</dd>' : '') +
    '</dl>';

    h += '<div class="shop-timeline">' +
      '<div class="tl">🛒 <div><b>客户已下单</b> · ' + shopEsc(o.created_at) + '</div></div>' +
      '<div class="tl">💳 <div><b>客户标记已付款</b> · ' + (o.pay_marked_at ? shopEsc(o.pay_marked_at) : '尚未标记') + '</div></div>' +
      '<div class="tl">✅ <div><b>管理员确认到账</b> · ' + (o.verified_at ? (shopEsc(o.verified_at) + (o.verified_by ? '（' + shopEsc(o.verified_by) + '）' : '')) : '尚未确认') + '</div></div>' +
    '</div>';

    h += '<div style="margin-top:18px;display:flex;gap:9px;flex-wrap:wrap;">' +
      '<button type="button" class="btn btn-secondary btn-sm" onclick="shopCopy(\'' + shopEsc(o.pay_code) + '\')">复制备注码</button>' +
      '<button type="button" class="btn btn-secondary btn-sm" onclick="shopCopy(\'' + shopEsc(o.order_no) + '\')">复制订单号</button>' +
      (o.wechat ? '<button type="button" class="btn btn-secondary btn-sm" onclick="shopCopy(\'' + shopEsc(o.wechat) + '\')">复制微信</button>' : '') +
      (o.phone  ? '<button type="button" class="btn btn-secondary btn-sm" onclick="shopCopy(\'' + shopEsc(o.phone)  + '\')">复制电话</button>' : '') +
      '<button type="button" class="btn btn-secondary btn-sm" onclick="shopCopyOrderAll(\'' + shopEsc(o.order_no) + '\')">复制全部订单信息</button>' +
    '</div>';

    document.getElementById('shop-order-detail').innerHTML = h;
    document.getElementById('shop-order-modal').classList.add('show');
    shopIpGeo(o.client_ip);
  }
  function shopCloseDetail(){ document.getElementById('shop-order-modal').classList.remove('show'); }

  function shopRemark(no){
    var o = SHOP_ORDERS[no];
    if (!o) return;
    document.getElementById('shop-remark-meta').textContent =
      o.customer_name + ' · ' + o.order_no + (o.created_at ? ' · ' + o.created_at : '');
    document.getElementById('shop-remark-body').textContent = o.remark || '';
    document.getElementById('shop-remark-modal').classList.add('show');
  }
  function shopCloseRemark(){ document.getElementById('shop-remark-modal').classList.remove('show'); }

  var SHOP_IPGEO = {};
  var SHOP_IPGEO_TEXT = {};
  var SHOP_IPGEO_DIAG = '?sub=orders&shop_ajax=ipgeo_diag';

  function shopIpGeoShow(el, txt, title){
    if (!el) return;
    el.textContent = '';
    el.style.color = '';
    el.appendChild(document.createTextNode(' · ' + txt));
    el.title = title || '';
  }

  function shopIpGeoFail(el, short, detail){
    if (!el) return;
    el.textContent = '';
    el.style.color = '';
    var s = document.createElement('span');
    s.textContent = ' · ' + short;
    s.style.color = '#d97706';
    s.title = detail || '';
    el.appendChild(s);
    var a = document.createElement('a');
    a.href = SHOP_IPGEO_DIAG;
    a.target = '_blank';
    a.rel = 'noopener';
    a.textContent = '诊断';
    a.style.cssText = 'margin-left:6px;color:var(--primary);text-decoration:underline;font-size:12px;';
    a.title = '打开诊断报告（新窗口）';
    el.appendChild(a);
  }
  function shopNewOrderBanner(msg, type, hold){
  var w = document.getElementById('admin-toast-wrap');
  if (!w) return;
  var el = document.createElement('div');
  el.className = 'admin-toast ' + (type || 'ok');
  el.textContent = msg;
  w.appendChild(el);
  setTimeout(function(){
    el.style.transition = 'opacity .3s, transform .3s';
    el.style.opacity = '0'; el.style.transform = 'translateX(12px)';
    setTimeout(function(){ el.remove(); }, 340);
  }, hold || 9000);
}
function shopIpGeo(ip){
    var el = document.getElementById('shop-ipgeo');
    if (!el) return;
    ip = String(ip || '');
    if (!ip) return;

    if (Object.prototype.hasOwnProperty.call(SHOP_IPGEO, ip)){
      var v = SHOP_IPGEO[ip];
      if (v && v.txt) shopIpGeoShow(el, v.txt, v.title);
      else if (v)     shopIpGeoFail(el, v.note || '识别失败', v.detail);
      return;
    }

    if (SHOP_IPGEO[ip] && SHOP_IPGEO[ip].txt) {
      SHOP_IPGEO_TEXT[ip] = SHOP_IPGEO[ip].txt;
      return;
    }

    el.textContent = ' · 识别中…';
    el.style.color = '';
    el.title = '';
    var url = '?sub=orders&shop_ajax=ipgeo&ip=' + encodeURIComponent(ip);

    fetch(url, { credentials: 'same-origin' })
      .then(function(r){
        return r.text().then(function(body){
          var j = null;
          try { j = JSON.parse(body); } catch (e) { j = null; }
          var e = document.getElementById('shop-ipgeo');
          if (!j){

            var head = body.replace(/\s+/g, ' ').slice(0, 160);
            SHOP_IPGEO[ip] = { txt: '', note: '接口未返回数据', detail: 'HTTP ' + r.status + '｜' + head };
            SHOP_IPGEO_TEXT[ip] = '';
            shopIpGeoFail(e, '接口未返回数据', 'HTTP ' + r.status + '｜' + head);
            return;
          }
          if (j.ok && j.location){
            var t = String(j.location);
            SHOP_IPGEO[ip] = { txt: t, title: '识别来源：' + (j.source || '未知') };
            SHOP_IPGEO_TEXT[ip] = t;
            shopIpGeoShow(e, t, '识别来源：' + (j.source || '未知'));
          } else {
            var note = j.note || '未能识别该地址';
            SHOP_IPGEO[ip] = { txt: '', note: note, detail: note };
            SHOP_IPGEO_TEXT[ip] = '';
            shopIpGeoFail(e, note, note);
          }
        });
      })
      .catch(function(err){
        var e = document.getElementById('shop-ipgeo');
        var msg = '请求失败';
        SHOP_IPGEO[ip] = { txt: '', note: msg, detail: String(err) };
        SHOP_IPGEO_TEXT[ip] = '';
        shopIpGeoFail(e, msg, String(err) + '\n（若是 404/500，检查 inc/ip_geo.php 是否已上传）');
      });
  }

  function shopCopyOrderAll(no){
    var o = SHOP_ORDERS[no];
    if (!o){ return; }
    var ip = String(o.client_ip || '');
    if (ip === '' || SHOP_IPGEO_TEXT[ip] !== undefined){
      shopCopy(shopOrderText(no), '全部订单信息');
      return;
    }
    var el = document.getElementById('shop-ipgeo');
    if (el) el.textContent = ' · 识别中…';
    var done = false;
    var finish = function(){
      if (done) return;
      done = true;
      shopCopy(shopOrderText(no), '全部订单信息');
    };
    var url = '?sub=orders&shop_ajax=ipgeo&ip=' + encodeURIComponent(ip);

    fetch(url, { credentials: 'same-origin' })
      .then(function(r){
        return r.text().then(function(body){
          var j = null;
          try { j = JSON.parse(body); } catch (e) { j = null; }
          if (j && j.ok && j.location){
            SHOP_IPGEO_TEXT[ip] = String(j.location);
            SHOP_IPGEO[ip] = { txt: String(j.location), title: '识别来源：' + (j.source || '') };
            if (el) el.textContent = ' · ' + SHOP_IPGEO_TEXT[ip];
          } else {
            SHOP_IPGEO_TEXT[ip] = '';
            if (el) el.textContent = '';
          }
        });
      })
      .catch(function(){ SHOP_IPGEO_TEXT[ip] = ''; finish(); })
      .then(function(){ finish(); });

    setTimeout(finish, 3000);
  }

  function shopOrderText(no){
    var o = SHOP_ORDERS[no];
    if (!o) return '';
    var L = [];
    L.push('【订单信息】');
    L.push('订单号：' + (o.order_no || ''));
    L.push('付款备注码：' + (o.pay_code || ''));
    L.push('当前状态：' + (o.status_label || ''));
    L.push('称呼：' + (o.customer_name || ''));
    L.push('微信：' + (o.wechat || '—'));
    L.push('电话：' + (o.phone || '—'));
    L.push('商品：' + (o.items_text || ''));
    L.push('金额：￥' + (o.amount || '0'));
    L.push('服务方式：' + (o.mode_label || ''));
    if (o.book_time)  L.push('预约时间：' + o.book_time);
    if (o.remark)     L.push('客户备注：' + o.remark);
    L.push('下单时间：' + (o.created_at || ''));
    if (o.client_ip) {

      var geo = SHOP_IPGEO_TEXT[o.client_ip] || '';
      L.push('下单 IP：' + o.client_ip + (geo ? ('（' + geo + '）') : ''));
    }
    if (o.admin_note) L.push('管理员备注：' + o.admin_note);
    return L.join('\n');
  }

  function shopCopyTip(msg){
    var el = document.getElementById('shop-copy-tip');
    if (!el){
      el = document.createElement('div');
      el.id = 'shop-copy-tip';

      el.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:10050;' +
        'background:rgba(28,41,66,.92);color:#fff;font-size:13px;' +
        'padding:9px 15px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.22);' +
        'opacity:0;transition:opacity .18s ease;pointer-events:none;';
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.style.opacity = '1';
    clearTimeout(el._t);
    el._t = setTimeout(function(){ el.style.opacity = '0'; }, 1400);
  }
  function shopCopy(t, label){
    if (t === undefined || t === null) t = '';
    t = String(t);
    if (t === ''){ shopCopyTip('没有内容可复制'); return; }
    var done = function(){ shopCopyTip('✓ 已复制' + (label ? '：' + label : '')); };
    if (navigator.clipboard && window.isSecureContext){
      navigator.clipboard.writeText(t).then(done).catch(function(){ shopCopyFallback(t); done(); });
    } else {
      shopCopyFallback(t); done();
    }
  }
  function shopCopyFallback(t){
    var ta = document.createElement('textarea');
    ta.value = t; ta.style.position='fixed'; ta.style.left='-9999px';
    document.body.appendChild(ta); ta.select();
    try{ document.execCommand('copy'); }catch(e){}
    ta.remove();
  }
  </script>
  <?php endif; ?>
</div>

<?php  ?>

<script>
/* 新订单提醒：右上角横幅 + 浏览器桌面通知（放这里保证零订单时也会输出） */
<?php $__maxOrderId   = function_exists('shop_order_max_id') ? shop_order_max_id() : 0; ?>
<?php $__maxOrderTime = function_exists('shop_order_max_updated_at') ? shop_order_max_updated_at() : ''; ?>
<?php
  // 自动失效相关：付款需要时间，时限设太短会把真付款的客户挡在门外
  $__expMin = (int)($shopData['pay']['unpaid_expire_minutes'] ?? 0);
  $__expSoon = 0;
  if ($dbReady && $__expMin > 0 && function_exists('shop_db')) {
      try {
          $__st = shop_db()->prepare("SELECT COUNT(*) FROM orders WHERE status IN ('pending_payment','payment_failed') AND created_at < ? AND pay_marked_at = ''");
          $__st->execute([date('Y-m-d H:i:s', time() - $__expMin * 60)]);
          $__expSoon = (int)$__st->fetchColumn();
      } catch (Throwable $e) {}
  }
  $__srvExtra = [
      'expire_minutes' => $__expMin,
      'expire_soon'    => $__expSoon,
      'server_time'    => date('Y-m-d H:i:s'),
      'timezone'       => date('T') . ' / ' . (ini_get('date.timezone') ?: '未设置(默认UTC)'),
  ];
?>
var SHOP_SRV_EXTRA = <?php echo json_encode($__srvExtra); ?>;
<?php $__srvFlags = [
    'shop_order_max_id'         => function_exists('shop_order_max_id'),
    'shop_order_max_updated_at' => function_exists('shop_order_max_updated_at'),
    'shop_order_watch'          => function_exists('shop_order_watch'),
    'shop_order_new_since'      => function_exists('shop_order_new_since'),
    'shop_db_add_column'        => function_exists('shop_db_add_column'),
    'shop_pay_method_label'     => function_exists('shop_pay_method_label'),
]; ?>
var SHOP_SRV = <?php echo json_encode($__srvFlags); ?>;
var SHOP_NEW_ORDER_URL = '?sub=orders&shop_ajax=new_orders';
var SHOP_LAST_ORDER_TIME = <?php echo json_encode($__maxOrderTime); ?>;
var SHOP_LAST_ORDER_ID = <?php echo (int)$__maxOrderId; ?>;
(function(){
  var LS_KEY   = 'shopAdminNewOrderId';
  var LS_KEY_T = 'shopAdminNewOrderTime';
  try {
    var v = parseInt(localStorage.getItem(LS_KEY) || '0', 10);
    if (v > SHOP_LAST_ORDER_ID) SHOP_LAST_ORDER_ID = v;
    var vt = localStorage.getItem(LS_KEY_T) || '';
    if (vt > SHOP_LAST_ORDER_TIME) SHOP_LAST_ORDER_TIME = vt;
  } catch (e) {}

  function saveId(id){ try { localStorage.setItem(LS_KEY, String(id)); } catch (e) {} }
  function saveTime(t){ try { localStorage.setItem(LS_KEY_T, String(t)); } catch (e) {} }

  function deskNotify(o){
    try {
      if (typeof Notification === 'undefined' || Notification.permission !== 'granted') return;
      var icon = (document.querySelector('link[rel="icon"]') || {}).href;
      var isPaid = (o.event === 'awaiting_verify') || (o.status === 'awaiting_verify');
      var n = new Notification(isPaid ? ('💰 客户已付款 ￥' + o.amount) : ('🛒 新订单 ￥' + o.amount), {
        body: (o.name ? o.name + ' · ' : '') + (isPaid ? '请到后台核实到账' : o.items_text)
              + '\n' + o.status_label
              + (o.pay_method_label ? ' · ' + o.pay_method_label : '')
              + (isPaid ? '' : (o.wechat ? '\n微信：' + o.wechat : '')),
        tag: o.order_no,
        icon: icon || undefined
      });
      n.onclick = function(){ try { window.focus(); } catch (e) {} n.close(); };
    } catch (e) {}
  }

  /* 长轮询地址：把 new_orders 换成 new_orders_wait（服务端会挂着等，有新订单立刻返回） */
  var POLL_URL = SHOP_NEW_ORDER_URL.replace('shop_ajax=new_orders', 'shop_ajax=new_orders_wait');
  var __polling = false;

  function handleNewOrders(list){
    (list || []).forEach(function(o, i){
      var isPaid = (o.event === 'awaiting_verify') || (o.status === 'awaiting_verify');
      setTimeout(function(){
        if (isPaid) {
          shopNewOrderBanner('💰 客户已标记付款 ￥' + o.amount + ' · ' + (o.name || '客户') + ' · 请核对到账', 'ok', 13000);
        } else {
          shopNewOrderBanner('🛒 新订单 ￥' + o.amount + ' · ' + (o.name || '客户') + ' · ' + o.items_text, 'ok', 9000);
        }
        deskNotify(o);
      }, i * 450);
    });
    if ((list || []).length && window.shopSyncList) window.shopSyncList(list.length);
  }

  /*
   * 长轮询：一个请求返回后立刻发下一个，所以「最小化 / 切到别的标签」时也能秒级收到。
   * 不能用 setInterval —— 后台标签页的定时器会被浏览器限流到 1 分钟一次，
   * 而且「隐藏时跳过」会让最小化期间完全不检查（那正是最需要提醒的时候）。
   */
  window.shopCheckNewOrders = function(){
    if (__polling) return;
    __polling = true;
    (function loop(){
      fetch(POLL_URL + '&since=' + SHOP_LAST_ORDER_ID + '&since_time=' + encodeURIComponent(SHOP_LAST_ORDER_TIME) + '&wait=50')
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (d && d.ok) {
            if (d.latest && d.latest > SHOP_LAST_ORDER_ID) { SHOP_LAST_ORDER_ID = d.latest; saveId(d.latest); }
            if (d.latest_time && d.latest_time > SHOP_LAST_ORDER_TIME) { SHOP_LAST_ORDER_TIME = d.latest_time; saveTime(d.latest_time); }
            handleNewOrders(d.orders);
          }
          loop();
        })
        .catch(function(){
          __polling = false;
          setTimeout(function(){ window.shopCheckNewOrders(); }, 5000);
        });
    })();
  };

/* ---------- 自检：一眼看出浏览器加载的是哪一版、为什么没提醒 ---------- */
  var SHOP_BUILD = '2026-10-02-v7';
  var __diag = { polls: 0, lastUrl: "", lastAt: "", lastOrders: -1, errors: [] };

  window.shopDiagClose = function(){
    var p = document.getElementById("shopDiagPanel");
    if (p) p.style.display = "none";
  };
  window.shopDiagClear = function(){
    try { localStorage.clear(); sessionStorage.clear(); } catch (e) {}
    try { banner("已清空本地记录，刷新后会以当前订单为基线", "info", 5000); } catch (e) {}
  };

  window.shopDiag = function(){
    var p = document.getElementById("shopDiagPanel");
    if (!p) return;
    if (p.style.display === "block") { p.style.display = "none"; return; }

    var rows = [];
    function row(k, v, ok){
      var cls = ok === true ? " good" : (ok === false ? " bad" : "");
      rows.push("<div class='sd-row'><span class='sd-k'>" + k + "</span><span class='sd-v" + cls + "'>" + v + "</span></div>");
    }
    var hasN = (typeof Notification !== "undefined");
    var perm = hasN ? Notification.permission : "-";

    row("页面版本 build", SHOP_BUILD, true);
    row("当前地址", location.href, null);
    row("通知 API", hasN ? "支持" : "不支持（浏览器太旧）", hasN);
    row("安全上下文", window.isSecureContext === false ? "不是 https，通知会被浏览器禁用" : "是 https", window.isSecureContext !== false);
    row("通知授权", perm, perm === "granted");
    row("轮询在跑吗", __polling ? "在跑" : "停了（见下方错误）", !!__polling);
    var srvBad = [];
    try {
      Object.keys(SHOP_SRV || {}).forEach(function(k){
        if (!SHOP_SRV[k]) srvBad.push(k);
      });
    } catch (e) {}
    row("服务端函数", srvBad.length
        ? ("缺 " + srvBad.length + " 个：" + srvBad.join(", ") + " —— inc/shop_store.php 是旧版，请重新上传")
        : "全部就位（6/6）", srvBad.length === 0);
    row("已发起轮询次数", String(__diag.polls) + (__diag.polls === 0 ? "（首个长轮询最长挂 50 秒，属正常）" : ""), null);
    row("最近请求", __diag.lastUrl || "（还没发出去）", null);
    row("最近返回时间", __diag.lastAt || "-", null);
    row("最近拿到订单数", String(__diag.lastOrders), null);
    row("订单号水位 since", String(SHOP_LAST_ORDER_ID), null);
    row("时间水位 since_time", SHOP_LAST_ORDER_TIME || "（空）", null);
    if (typeof SHOP_SRV_EXTRA === 'object' && SHOP_SRV_EXTRA) {
      var em = SHOP_SRV_EXTRA.expire_minutes;
      row("未付款失效时限", em > 0 ? (em + " 分钟") : "已关闭（0）", em === 0 ? null : (em >= 15));
      row("按此设置已超时的待付款单", String(SHOP_SRV_EXTRA.expire_soon) + " 笔", SHOP_SRV_EXTRA.expire_soon === 0);
      row("服务端当前时间", SHOP_SRV_EXTRA.server_time, null);
      row("服务端时区", SHOP_SRV_EXTRA.timezone, null);
    }
    row("列表同步计数", String(__syncCount), null);
    row("错误", __diag.errors.length ? __diag.errors.slice(-3).join(" | ") : "无", __diag.errors.length === 0);

    var head = "<div class='sd-head'>🔧 通知自检"
      + "<button type='button' class='sd-btn' onclick='shopDiagClear()'>清空本地水位</button>"
      + "<button type='button' class='sd-btn' onclick='shopDiagClose()'>关闭</button></div>";
    p.innerHTML = head + rows.join("");
    p.style.display = "block";
  };

  /* 记录每次轮询，方便自检显示 */
  (function(){
    var _f = window.fetch;
    if (!_f) return;
    window.fetch = function(u, o){
      __diag.polls++;
      __diag.lastUrl = String(u);
      return _f.apply(this, arguments).then(function(r){
        __diag.lastAt = new Date().toLocaleTimeString();
        return r;
      }).catch(function(e){
        __diag.errors.push("fetch: " + (e && e.message ? e.message : String(e)));
        throw e;
      });
    };
  })();

  window.shopCheckNewOrders();

  window.shopNotifyState = function(){
    var b = document.getElementById('shopNotifyBtn');
    if (!b) return;
    b.style.display = '';
    b.classList.remove('on');
    if (typeof Notification === 'undefined') {
      b.textContent = '🔔 桌面通知不可用';
      b.title = '当前访问方式不支持浏览器通知。\n'
              + '原因：浏览器只在 https:// 页面（或 localhost）提供通知能力，\n'
              + '用 http:// 或 http://IP 打开后台时这个功能会被浏览器禁用。\n'
              + '（右上角横幅提醒不受影响，仍然可用）';
      return;
    }
    if (window.isSecureContext === false) {
      b.textContent = '🔔 需要 HTTPS';
      b.title = '浏览器要求用 https:// 访问才能发送通知。请改用 https 域名打开后台。\n'
              + '（右上角横幅提醒仍然可用）';
      return;
    }
    if (Notification.permission === 'granted') { b.textContent = '🔔 桌面通知已开启'; b.classList.add('on'); b.title = '已开启：有新订单会弹电脑系统通知'; }
    else if (Notification.permission === 'denied') {
      b.textContent = '🔕 桌面通知被拒绝';
      b.title = '浏览器已拒绝本站通知。点地址栏左侧的锁 / 信息图标，把「通知」改为「允许」后刷新页面';
    } else {
      b.textContent = '🔔 开启桌面通知';
      b.title = '点一下授权，之后有新订单会弹电脑系统通知';
    }
  };

  window.shopAskNotify = function(){
    if (typeof Notification === 'undefined' || window.isSecureContext === false) {
      shopNewOrderBanner('浏览器通知需要 https:// 访问。当前方式不支持，请改用 https 域名打开后台', 'err', 10000);
      return;
    }
    if (Notification.permission === 'granted') { shopNewOrderBanner('桌面通知已经开着啦', 'info', 2600); return; }
    if (Notification.permission === 'denied') {
      shopNewOrderBanner('浏览器已拒绝本站通知，请点地址栏左侧的锁图标把「通知」改为允许', 'err', 9000);
      return;
    }
    try {
      Notification.requestPermission().then(function(p){
        window.shopNotifyState();
        if (p === 'granted') {
          shopNewOrderBanner('桌面通知已开启，来新订单会弹到电脑系统', 'ok', 5000);
          try { new Notification('✅ 桌面通知已开启', { body: '以后有新订单，这里会弹出系统通知' }); } catch (e) {}
          window.shopCheckNewOrders();
        } else {
          shopNewOrderBanner('没有开启桌面通知（新订单仍会在右上角显示横幅）', 'info', 5000);
        }
      });
    } catch (e) {}
  };


  /* ---------- 列表自动同步 ----------
   * 通知只负责「告诉你」，而列表是服务端渲染出来的旧 HTML —— 不刷新就看不到新订单。
   * 所以收到变化后：页面可见且没在操作 → 自动刷新（并恢复滚动位置）；
   * 正在看详情 / 勾了订单 / 在输入框里 → 只显示顶部提示条，等操作完或切回页面再刷。
   */
  var __syncCount = 0;

  function __shopBusy(){
    try {
      if (document.querySelector(".shop-modal,.modal,.shop-detail,.shop-detail-modal")) return true;
      var c = document.querySelectorAll(".shop-row-check:checked,#shop-check-all:checked");
      if (c && c.length) return true;
      var a = document.activeElement;
      if (a && a.tagName && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName)) return true;
    } catch (e) {}
    return false;
  }

  function __shopShowSyncBar(){
    var b = document.getElementById("shopSyncBar");
    var n = document.getElementById("shopSyncCount");
    if (!b) return;
    if (n) n.textContent = String(__syncCount);
    b.style.display = "flex";
  }

  window.shopForceRefresh = function(){
    try { sessionStorage.setItem("shopOrdersScroll", String(window.scrollY || 0)); } catch (e) {}
    location.reload();
  };

  var __syncTimer = 0;

  window.shopSyncList = function(n){
    __syncCount += (n || 1);
    __shopShowSyncBar();
    if (document.hidden) return;
    if (__syncTimer) return;              /* 已经排好队了，别重复刷 */
    /* 延迟 1.8 秒再刷：留时间把横幅和系统通知显示出来，否则用户什么都没看到页面就重载了 */
    __syncTimer = setTimeout(function(){
      __syncTimer = 0;
      if (__shopBusy()) { __shopShowSyncBar(); return; }
      window.shopForceRefresh();
    }, 1800);
  };

  document.addEventListener("visibilitychange", function(){
    if (!document.hidden && __syncCount > 0 && !__shopBusy()) window.shopForceRefresh();
  });

  try {
    var __sy = parseInt(sessionStorage.getItem("shopOrdersScroll") || "0", 10);
    if (__sy > 0) {
      sessionStorage.removeItem("shopOrdersScroll");
      window.addEventListener("load", function(){ window.scrollTo(0, __sy); });
    }
  } catch (e) {}

  window.shopNotifyState();
})();
</script>

<?php elseif ($shopSub === 'products'): ?>
<?php
$products = $shopData['products'];
$cats     = $shopData['categories'];
$editPid  = isset($_GET['pid']) ? (string)$_GET['pid'] : '';
$editProd = null;
if ($editPid !== '') {
    $f = shop_find_product($editPid);
    if ($f) $editProd = $f['product'];
}
$v = function ($k, $d = '') use ($editProd) { return htmlspecialchars((string)($editProd[$k] ?? $d)); };
?>

<div class="card">
  <h2>商品 / 服务项目管理 <span class="hint">（共 <?php echo count($products); ?> 项）</span></h2>
  <div class="tips">这里的每一项都会出现在前台 <code>/shop/</code> 的商品墙中。支持虚拟商品与远程服务；「立即下单」「提前预约」两个按钮可分别开关。</div>

  <div class="shop-edit-panel" id="shop-product-form">
  <h3><?php echo $editProd ? '编辑商品' : '新增商品'; ?></h3>
  <form method="post">
    <input type="hidden" name="action" value="save_shop_product"><?php echo csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo $editProd ? htmlspecialchars($editProd['id'], ENT_QUOTES) : ''; ?>">

    <div class="shop-grid2">
      <div class="shop-field">
        <label>商品 / 服务名称 <span class="hint">（必填）</span></label>
        <input type="text" name="name" value="<?php echo $v('name'); ?>" required placeholder="例如：Steam 代安装">
      </div>
      <div class="shop-field">
        <label>所属分类</label>
        <select name="category">
          <?php foreach ($cats as $c): ?>
            <option value="<?php echo htmlspecialchars($c['key'], ENT_QUOTES); ?>"
              <?php echo (($editProd['category'] ?? '') === $c['key']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($c['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="shop-grid2">
      <div class="shop-field">
        <label>价格（元） <span class="hint">（必填，支持小数）</span></label>
        <input type="number" name="price" step="0.01" min="0" value="<?php echo $v('price', '0'); ?>" required placeholder="例如：15">
      </div>
      <div class="shop-field">
        <label>计价单位</label>
        <input type="text" name="unit" value="<?php echo $v('unit', '次'); ?>" placeholder="次 / 台 / 件 / 月">
      </div>
    </div>

    <div class="shop-grid2">
      <div class="shop-field">
        <label>库存 <span class="hint">（填 -1 表示「不限」，与前台「不限」显示一致）</span></label>
        <input type="number" name="stock" min="-1" value="<?php echo $v('stock', '-1'); ?>" placeholder="-1">
      </div>
      <div class="shop-field">
        <label>标签 <span class="hint">（多个用英文逗号分隔，如：远程协助,游戏平台）</span></label>
        <input type="text" name="tags" value="<?php echo htmlspecialchars(implode(',', (array)($editProd['tags'] ?? []))); ?>" placeholder="远程协助,游戏平台">
      </div>
    </div>

    <div class="shop-field">
      <label>商品图片地址 <span class="hint">（可留空，留空时前台显示分类图标；建议用外链图床，与本站其他模块一致）</span></label>
      <input type="text" name="image" value="<?php echo $v('image'); ?>" placeholder="https://…/steam.png">
    </div>

    <div class="shop-field">
      <label>图标 emoji <span class="hint">（可选，无图片时优先显示，如 🎮）</span></label>
      <input type="text" name="icon" value="<?php echo $v('icon'); ?>" placeholder="🎮" maxlength="8">
    </div>

    <div class="shop-field">
      <label>商品说明 <span class="hint">（显示在卡片上，支持换行）</span></label>
      <textarea name="desc" placeholder="远程协助安装 Steam 客户端，解决地区限制、网络问题，确保正常登录使用。"><?php echo $v('desc'); ?></textarea>
    </div>

    <div class="shop-card">
      <h4>下单方式与状态</h4>
      <label class="shop-check"><input type="checkbox" name="allow_now" value="1" <?php echo (!$editProd || !empty($editProd['allow_now'])) ? 'checked' : ''; ?>> 允许「⚡ 立即下单」</label>
      <label class="shop-check"><input type="checkbox" name="allow_booking" value="1" <?php echo (!$editProd || !empty($editProd['allow_booking'])) ? 'checked' : ''; ?>> 允许「📅 提前预约」</label>
      <label class="shop-check"><input type="checkbox" name="status_on" value="1" <?php echo (!$editProd || ($editProd['status'] ?? 'on') === 'on') ? 'checked' : ''; ?>> 上架（取消勾选即下架，前台立即不显示）</label>
    </div>

    <div style="display:flex;gap:12px;">
      <button type="submit" class="btn btn-primary"><?php echo $editProd ? '保存修改' : '＋ 新增商品'; ?></button>
      <?php if ($editProd): ?>
        <a href="?sub=products" class="btn btn-secondary">取消编辑</a>
      <?php endif; ?>
    </div>
  </form>
  </div>

  <h3>已有商品（按列表顺序展示，可用 ↑↓ 调整）</h3>
  <?php if (empty($products)): ?>
    <div class="empty">暂无商品，请在上方新增</div>
  <?php else: ?>
    <?php foreach ($products as $i => $p): ?>
      <div class="shop-prod-row">
        <div class="thumb">
          <?php if (!empty($p['image'])): ?>
            <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="" onerror="this.outerHTML='<?php echo htmlspecialchars($p['icon'] ?: '🛠️', ENT_QUOTES); ?>'">
          <?php else: ?>
            <?php echo htmlspecialchars($p['icon'] ?: '🛠️'); ?>
          <?php endif; ?>
        </div>
        <div class="meta">
          <div class="nm">
            <?php echo htmlspecialchars($p['name']); ?>
            <?php if (($p['status'] ?? 'on') !== 'on'): ?><span class="shop-badge" style="background:var(--surface3);color:var(--text3);">已下架</span><?php endif; ?>
            <?php if (!empty($p['allow_now'])): ?><span class="shop-badge" style="background:var(--primary-soft);color:var(--primary);">立即下单</span><?php endif; ?>
            <?php if (!empty($p['allow_booking'])): ?><span class="shop-badge" style="background:var(--warn-soft);color:var(--warn);">可预约</span><?php endif; ?>
          </div>
          <div class="ds"><?php echo htmlspecialchars(mb_substr((string)($p['desc'] ?? ''), 0, 70)); ?><?php echo mb_strlen((string)($p['desc'] ?? '')) > 70 ? '…' : ''; ?></div>
          <div class="tg">
            <span>分类：<?php echo htmlspecialchars(shop_category_name((string)($p['category'] ?? ''))); ?></span>
            <span>库存：<?php echo ((int)($p['stock'] ?? -1) < 0) ? '不限' : (int)$p['stock']; ?></span>
            <?php foreach ((array)($p['tags'] ?? []) as $t): ?><span><?php echo htmlspecialchars((string)$t); ?></span><?php endforeach; ?>
          </div>
        </div>
        <div class="price">￥<?php echo shop_price($p['price'] ?? 0); ?><span style="font-size:12px;color:var(--text3);">/<?php echo htmlspecialchars((string)($p['unit'] ?? '次')); ?></span></div>
        <div class="acts">
          <?php if ($i > 0): ?>
            <a class="shop-mini" href="<?php echo htmlspecialchars($shopUrl('?sub=products&move_shop_product=' . urlencode($p['id']) . '&dir=up')); ?>" title="上移">↑</a>
          <?php endif; ?>
          <?php if ($i < count($products) - 1): ?>
            <a class="shop-mini" href="<?php echo htmlspecialchars($shopUrl('?sub=products&move_shop_product=' . urlencode($p['id']) . '&dir=down')); ?>" title="下移">↓</a>
          <?php endif; ?>
          <a class="shop-mini" href="?sub=products&pid=<?php echo urlencode($p['id']); ?>#shop-product-form">编辑</a>
          <a class="shop-mini danger" href="javascript:void(0)"
             onclick="confirmDelete('?sub=products&del_shop_product=<?php echo urlencode($p['id']); ?>', '<?php echo js_attr($p['name']); ?>')">删除</a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php  ?>
<?php elseif ($shopSub === 'categories'): ?>
<?php $cats = $shopData['categories']; ?>

<div class="card">
  <h2>商品分类管理 <span class="hint">（共 <?php echo count($cats); ?> 个分类）</span></h2>
  <div class="tips">
    分类标识（key）用于商品归类，创建后不可修改。删除分类<strong>只会删除分类本身</strong>，已使用该分类的商品不会被改动（仍会显示，只是分类名回退为标识）。
  </div>

  <h3>新增分类</h3>
  <form method="post">
    <input type="hidden" name="action" value="save_shop_category"><?php echo csrf_field(); ?>
    <div class="shop-grid2">
      <div class="shop-field">
        <label>分类名称 <span class="hint">（必填）</span></label>
        <input type="text" name="new_name" required placeholder="例如：游戏代练">
      </div>
      <div class="shop-field">
        <label>分类标识 <span class="hint">（选填：字母/数字/下划线，留空自动生成）</span></label>
        <input type="text" name="new_key" placeholder="game_boost">
      </div>
    </div>
    <button type="submit" class="btn btn-primary" style="margin-bottom:10px;">＋ 新增分类</button>
  </form>

  <h3 style="margin-top:26px;">已有分类</h3>
  <?php if (empty($cats)): ?>
    <div class="empty">暂无分类</div>
  <?php else: ?>
    <?php foreach ($cats as $c): ?>
      <form method="post" class="shop-cat-row">
        <input type="hidden" name="action" value="save_shop_category"><?php echo csrf_field(); ?>
        <input type="hidden" name="old_key" value="<?php echo htmlspecialchars($c['key'], ENT_QUOTES); ?>">
        <span class="key"><?php echo htmlspecialchars($c['key']); ?></span>
        <input type="text" name="name" value="<?php echo htmlspecialchars($c['name']); ?>" required placeholder="分类名称">
        <button type="submit" class="btn btn-secondary btn-sm" style="flex:0 0 auto;">重命名</button>
        <a class="shop-mini danger" style="flex:0 0 auto;" href="javascript:void(0)"
           onclick="confirmDelete('?sub=categories&del_shop_category=<?php echo urlencode($c['key']); ?>', '<?php echo js_attr($c['name']); ?>')">删除</a>
      </form>
    <?php endforeach; ?>
  <?php endif; ?>

</div>

<?php  ?>
<?php elseif ($shopSub === 'page'): ?>
<?php
$payee      = trim((string)($shopData['pay']['payee_name'] ?? ''));
$brandShow  = trim((string)($shopData['brand_name'] ?? ''))    !== '' ? $shopData['brand_name']    : ($payee !== '' ? $payee : '在线下单');
$footerShow = trim((string)($shopData['footer_title'] ?? ''))  !== '' ? $shopData['footer_title']  : (($payee !== '' ? $payee : '在线商城') . ' · 在线下单');
?>

<form method="post">
  <input type="hidden" name="action" value="save_shop_page"><?php echo csrf_field(); ?>

  <div class="card">
    <h2>首页首屏</h2>
    <div class="shop-grid2">
      <div class="shop-field">
        <label>页面标题 <span class="hint">（首页大标题）</span></label>
        <input type="text" name="page_title" value="<?php echo htmlspecialchars($shopData['page_title']); ?>">
      </div>
      <div class="shop-field">
        <label>页面副标题 <span class="hint">（大标题下方那行小字）</span></label>
        <input type="text" name="page_subtitle" value="<?php echo htmlspecialchars($shopData['page_subtitle']); ?>">
      </div>
    </div>
    <div class="shop-field">
      <label>顶部公告 <span class="hint">（显示在商品墙上方，留空则不显示；支持换行）</span></label>
      <textarea name="announcement" rows="1" style="min-height:34px;padding-top:7px;padding-bottom:7px;"><?php echo htmlspecialchars($shopData['announcement']); ?></textarea>
    </div>
    <div class="shop-note">
      <div class="shop-note-title">这一页的文案都会影响两个地方</div>
      <ul>
        <li><b>页面标题</b>同时是推送通知里 <code>{site}</code> 的取值 —— 建议写成你的店名，如「某某技术服务」</li>
        <li><b>页面标题</b>还会作为浏览器标签页的标题</li>
      </ul>
    </div>
  </div>

  <div class="card">
    <h2>顶部导航栏</h2>
    <div class="shop-field">
      <label>店名 <span class="hint">（左上角显示；留空则用「收款与表单」里的收款人名称）</span></label>
      <input type="text" name="brand_name" value="<?php echo htmlspecialchars((string)($shopData['brand_name'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($brandShow); ?>">
    </div>
    <div class="shop-grid2">
      <div class="shop-field">
        <label>「查询订单」按钮文字 <span class="hint">（留空则隐藏该按钮）</span></label>
        <input type="text" name="topbar_query" value="<?php echo htmlspecialchars((string)($shopData['topbar_query'] ?? '🔍 查询订单')); ?>">
      </div>
      <div class="shop-field">
        <label>返回按钮文字 <span class="hint">（留空则隐藏该按钮）</span></label>
        <input type="text" name="topbar_back" value="<?php echo htmlspecialchars((string)($shopData['topbar_back'] ?? '← 返回主站')); ?>">
      </div>
    </div>
    <div class="shop-field">
      <label>返回按钮地址 <span class="hint">（站内相对路径或完整网址；留空用 ../）</span></label>
      <input type="text" name="topbar_back_url" value="<?php echo htmlspecialchars((string)($shopData['topbar_back_url'] ?? '../')); ?>" placeholder="../ 或 ../Contact.html">
    </div>
  </div>

  <div class="card">
    <h2>页脚</h2>
    <div class="shop-field">
      <label>页脚第一行 <span class="hint">（店名那行；留空则用收款人名称）</span></label>
      <input type="text" name="footer_title" value="<?php echo htmlspecialchars((string)($shopData['footer_title'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($footerShow); ?>">
    </div>
    <div class="shop-field">
      <label>页脚说明 <span class="hint">（第二行那句下单提示，留空则不显示）</span></label>
      <textarea name="footer_note" rows="1" style="min-height:34px;padding-top:7px;padding-bottom:7px;"><?php echo htmlspecialchars((string)($shopData['footer_note'] ?? '')); ?></textarea>
    </div>
    <div class="shop-grid2">
      <div class="shop-field">
        <label>页脚链接 1 文字 <span class="hint">（留空则不显示）</span></label>
        <input type="text" name="footer_link1_text" value="<?php echo htmlspecialchars((string)($shopData['footer_link1_text'] ?? '订单查询')); ?>">
      </div>
      <div class="shop-field">
        <label>页脚链接 1 地址 <span class="hint">（留空用 ./query.php）</span></label>
        <input type="text" name="footer_link1_url" value="<?php echo htmlspecialchars((string)($shopData['footer_link1_url'] ?? './query.php')); ?>">
      </div>
      <div class="shop-field">
        <label>页脚链接 2 文字 <span class="hint">（留空则不显示）</span></label>
        <input type="text" name="footer_link2_text" value="<?php echo htmlspecialchars((string)($shopData['footer_link2_text'] ?? '联系我们')); ?>">
      </div>
      <div class="shop-field">
        <label>页脚链接 2 地址 <span class="hint">（订单查询页的底部也用这个）</span></label>
        <input type="text" name="footer_link2_url" value="<?php echo htmlspecialchars((string)($shopData['footer_link2_url'] ?? '../Contact.html')); ?>">
      </div>
    </div>
    <div class="shop-note">
      <div class="shop-note-title">地址怎么写</div>
      <ul>
        <li>站内页面用相对路径：<code>./query.php</code>（同目录）、<code>../Contact.html</code>（上一级）</li>
        <li>外部网址写完整地址：<code>https://example.com/contact</code></li>
        <li>页脚链接 2 同时用于<b>订单查询页</b>的底部，改一处两页同步</li>
      </ul>
    </div>
  </div>

  <div style="margin-bottom:30px;">
    <button type="submit" class="btn btn-primary">保存页面文案</button>
  </div>
</form>

<?php  ?>
<?php elseif ($shopSub === 'pay'): ?>
<?php
$pay  = $shopData['pay'];
$form = $shopData['form'];
$qry  = $shopData['query'];
?>

<form method="post">
  <input type="hidden" name="action" value="save_shop_pay"><?php echo csrf_field(); ?>

  <div class="card">
    <h2>收款码设置</h2>
    <div style="display:flex;gap:22px;flex-wrap:wrap;">
      <div style="flex:0 0 auto;">
        <label style="display:block;font-size:13.5px;font-weight:600;margin-bottom:9px;">当前收款码预览</label>
        <?php if (trim((string)$pay['qr_url']) !== ''): ?>
          <img id="shopQrPreview" class="shop-qr-preview" src="<?php echo htmlspecialchars($pay['qr_url']); ?>" alt="收款码"
               title="点击放大" onclick="shopQrZoom()" referrerpolicy="no-referrer"
               onerror="this.outerHTML='<div class=&quot;shop-qr-empty&quot;>图片加载失败<br>请检查地址</div>'">
          <div class="shop-qr-zoom-hint">🔍 点击可放大图片预览</div>
        <?php else: ?>
          <div class="shop-qr-empty">尚未配置收款码<br>请填写右侧图片地址</div>
        <?php endif; ?>
      </div>
      <div style="flex:1;min-width:260px;">
        <div class="shop-field">
          <label>收款方式 <span class="hint">（决定客户端要不要让客户选支付方式）</span></label>
        <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;">
          <label class="shop-check"><input type="radio" name="qr_mode" value="aggregate" <?php echo (($pay['qr_mode'] ?? 'aggregate') !== 'split') ? 'checked' : ''; ?>>
            <b>聚合码</b>（一张码微信/支付宝都能扫）—— 客户端<strong>不显示</strong>支付方式选择</label>
          <label class="shop-check"><input type="radio" name="qr_mode" value="split" <?php echo (($pay['qr_mode'] ?? 'aggregate') === 'split') ? 'checked' : ''; ?>>
            <b>分开的微信 / 支付宝收款码</b> —— 客户端<strong>显示</strong>支付方式选择</label>
        </div>
        <div class="shop-field" id="qrAggBox">
        <label>聚合收款码地址 <span class="hint">（选了聚合码就填这里；建议用外链图床）</span></label>
        <input type="text" name="qr_url" value="<?php echo htmlspecialchars((string)($pay['qr_url'] ?? '')); ?>" placeholder="https://.../pay.png">
        </div>
        <div class="shop-field" id="qrWxBox">
        <label>💬 微信收款码地址</label>
        <input type="text" name="qr_wechat" value="<?php echo htmlspecialchars((string)($pay['qr_wechat'] ?? '')); ?>" placeholder="https://.../wechat.png">
        </div>
        <div class="shop-field" id="qrAliBox">
        <label>🅰 支付宝收款码地址</label>
        <input type="text" name="qr_alipay" value="<?php echo htmlspecialchars((string)($pay['qr_alipay'] ?? '')); ?>" placeholder="https://.../alipay.png">
        </div>
<script>
(function(){
  function sync(){
    var r = document.querySelector('input[name="qr_mode"]:checked');
    var split = r && r.value === 'split';
    var a = document.getElementById('qrAggBox'), w = document.getElementById('qrWxBox'), p = document.getElementById('qrAliBox');
    if (a) a.style.display = split ? 'none' : '';
    if (w) w.style.display = split ? '' : 'none';
    if (p) p.style.display = split ? '' : 'none';
  }
  Array.prototype.forEach.call(document.querySelectorAll('input[name="qr_mode"]'), function(x){ x.addEventListener('change', sync); });
  sync();
})();
</script>
        <div class="tips" style="margin-bottom:14px;">
          聚合码和「微信/支付宝分开」两种模式<strong>只需配一种</strong>。
          没有聚合码、只有个人收款码时选第二种，把微信和支付宝的码分别填上，
          客户端会先让客户选支付方式再显示对应的码。
        </div>
        </div>
        <div class="shop-grid2">
          <div class="shop-field">
            <label>收款码下方提示</label>
            <input type="text" name="qr_tip" value="<?php echo htmlspecialchars($pay['qr_tip']); ?>" placeholder="微信 / 支付宝 扫码支付">
          </div>
          <div class="shop-field">
            <label>收款方名称 <span class="hint">（页头与页脚显示）</span></label>
            <input type="text" name="payee_name" value="<?php echo htmlspecialchars($pay['payee_name']); ?>" placeholder="你的店名">
          </div>
        </div>
      </div>
    </div>

    <div class="shop-card">
      <div class="shop-radio">
        <label><input type="radio" name="code_mode" value="short" <?php echo ($pay['code_mode'] ?? 'short') === 'short' ? 'checked' : ''; ?>> 系统生成短备注码（推荐）</label>
        <label><input type="radio" name="code_mode" value="wechat" <?php echo ($pay['code_mode'] ?? '') === 'wechat' ? 'checked' : ''; ?>> 直接使用客户微信作为备注码</label>
      </div>
      <div class="shop-code-wrap">
        <div class="shop-code-demo">
          <div class="cd-l">短备注码示例</div>
          <div class="shop-code-preview">EK</div>
          <div class="cd-h">自动生成<br>24 小时内不重复</div>
        </div>
        <div class="shop-grid2">
          <div class="shop-field">
            <label>备注码长度 <span class="hint">（2-6 位，建议 2 位）</span></label>
            <input type="number" name="code_length" min="2" max="6" value="<?php echo (int)$pay['code_length']; ?>">
          </div>
          <div class="shop-field">
            <label>允许客户点「换一个」</label>
            <label class="shop-check" style="margin-top:8px;"><input type="checkbox" name="allow_regenerate" value="1" <?php echo !empty($pay['allow_regenerate']) ? 'checked' : ''; ?>> 允许重新生成备注码</label>
          </div>
        </div>
      </div>
    </div>

    <h3 style="margin-top:22px;">未付款订单与失效清理</h3>
    <?php $__expMin = (int)($pay['unpaid_expire_minutes'] ?? 30); ?>
    <?php if ($__expMin > 0 && $__expMin < 15): ?>
    <div class="alert alert-error" style="margin-top:10px;">
      ⚠️ <strong>当前失效时间只有 <?php echo $__expMin; ?> 分钟，太短了。</strong><br>
      客户扫码付款要切到微信/支付宝、输金额、确认，再回来点「我已付款」，实际往往要 <strong>2~5 分钟</strong>；
      如果期间订单已经变成「已失效」，客户会以为自己付的钱没被记录。<br>
      （好消息：现在超时后回来点「我已付款」仍会自动复活订单并提醒你，但建议还是改成 <strong>30 分钟</strong>。）
    </div>
    <?php endif; ?>
    <div class="tips">
      客户下单后一直不付款的订单会占用「未付款」名额，也可能被人恶意刷单。
      这里控制<strong>多久自动失效</strong>、以及失效后<strong>保留多久</strong>。
    </div>
    <div class="shop-card">
      <div class="shop-grid2">
        <div class="shop-field">
          <label>未付款订单自动失效 <span class="hint">（0 = 关闭；建议 30 分钟，别太短）</span></label>
          <div class="shop-unit">
            <input type="number" name="unpaid_expire_minutes" min="0" max="1440" value="<?php echo (int)($pay['unpaid_expire_minutes'] ?? 10); ?>">
            <span class="u">分钟</span>
          </div>
          <details class="shop-ch plain">
            <summary>这个时间怎么算的？</summary>
            <div class="ch-body">
              超过这个时间仍未付款的订单会被<strong>标记为「已失效」</strong>（不会立刻删掉，记录保留在「已失效」标签页），
              不占未付款名额、也不计入统计。<br>
              只处理「待付款 / 付款失败」的订单 —— 客户一旦点过「我已付款」（变「待人工核查」）就<strong>永远不会被动</strong>。<br>
              <strong>客户超时后回来点「我已付款」仍然有效</strong>：订单会自动复活成「待人工核查」并提醒你，
              不会因为超时而把真付了钱的客户挡在门外。<br>
              ⚠️ <strong>别设太短</strong>：客户扫码 → 输金额 → 确认 → 再回来点按钮，中间还要切换到微信/支付宝，
              实际往往要 2~5 分钟。建议 <strong>30 分钟</strong>（默认值），太短会让正常付款的客户一直被「已失效」打断。
              填 0 则关闭此功能（不推荐，容易被刷单占位）。
            </div>
          </details>
        </div>
        <div class="shop-field">
          <label>「已失效」订单保留 <span class="hint">（0 = 永久保留）</span></label>
          <div class="shop-unit">
            <input type="number" name="expired_keep_days" min="0" max="365" value="<?php echo (int)($pay['expired_keep_days'] ?? 7); ?>">
            <span class="u">天</span>
          </div>
          <details class="shop-ch plain">
            <summary>保留多少天合适？</summary>
            <div class="ch-body">
              超时未付款的订单会变成<strong>「已失效」</strong>：不计入统计、不占未付款名额，但<strong>记录保留</strong>，
              可以在订单管理的「已失效」标签页里查看。<br>
              超过这里设置的天数后才会<strong>彻底删除</strong>。填 0 则永不删除（数据会一直累积）。
            </div>
          </details>
        </div>
      </div>
    </div>

    <div class="shop-grid2">
      <div class="shop-field">
        <label>「联系管理员」链接</label>
        <input type="text" name="contact_admin_url" value="<?php echo htmlspecialchars($pay['contact_admin_url']); ?>" placeholder="Contact.html 或 https://…">
      </div>
      <div class="shop-field">
        <label>「联系管理员」按钮文字</label>
        <input type="text" name="contact_admin_text" value="<?php echo htmlspecialchars($pay['contact_admin_text']); ?>">
      </div>
    </div>
    <div class="shop-field">
      <label>付款页底部提示语</label>
      <textarea name="tips" rows="1" style="min-height:34px;padding-top:7px;padding-bottom:7px;"><?php echo htmlspecialchars($pay['tips']); ?></textarea>
    </div>
  </div>

  <div class="card">
    <h2>下单表单设置</h2>
    <div class="shop-grid2">
      <div>
        <label class="shop-check"><input type="checkbox" name="require_wechat" value="1" <?php echo !empty($form['require_wechat']) ? 'checked' : ''; ?>> 微信必填</label>
        <label class="shop-check"><input type="checkbox" name="require_phone" value="1" <?php echo !empty($form['require_phone']) ? 'checked' : ''; ?>> 电话必填</label>
        <label class="shop-check"><input type="checkbox" name="require_book_time" value="1" <?php echo !empty($form['require_book_time']) ? 'checked' : ''; ?>> 预约时间必填</label>
      </div>
      <div>
        <label class="shop-check"><input type="checkbox" name="enable_booking" value="1" <?php echo !empty($form['enable_booking']) ? 'checked' : ''; ?>> 开启「提前预约」功能</label>
        <label class="shop-check"><input type="checkbox" name="enable_remark" value="1" <?php echo !empty($form['enable_remark']) ? 'checked' : ''; ?>> 显示「备注」输入框</label>
      </div>
    </div>
    <div class="shop-field">
      <label>信息收集用途说明 <span class="hint">（显示在下单弹窗底部；订单查询页不再显示这条，务必写清楚，属于合规要求）</span></label>
      <textarea name="form_notice" rows="1" style="min-height:34px;padding-top:7px;padding-bottom:7px;"><?php echo htmlspecialchars($form['notice']); ?></textarea>
    </div>
  </div>

  <div class="card">
    <h2>订单查询设置</h2>
    <label class="shop-check"><input type="checkbox" name="query_enable" value="1" <?php echo !empty($qry['enable']) ? 'checked' : ''; ?>> 开启前台「订单查询」通道</label>
    <div class="tips" style="margin-top:10px;">
      <strong>查询方式（固定，无需配置）：</strong><br>
      · <strong>联系方式必填</strong>（手机号或微信号）—— 用于确认是本人，防止遍历订单<br>
      · <strong>订单号选填</strong>：填了就精确查这一单；留空则列出该联系方式下的<b>全部订单（最新在前）</b><br>
      · 前台进入查询页时，会用浏览器记住的上次订单号<b>自动填充</b>，客户清空即可看全部
    </div>
    <div class="shop-grid2" style="margin-top:14px;">
      <div class="shop-field">
        <label>查询页标题</label>
        <input type="text" name="query_page_title" value="<?php echo htmlspecialchars($qry['page_title']); ?>">
      </div>
      <div class="shop-field">
        <label>查询页说明</label>
        <input type="text" name="query_page_tip" value="<?php echo htmlspecialchars($qry['page_tip']); ?>">
      </div>
    </div>
  </div>

  <div style="margin-bottom:30px;">
    <button type="submit" class="btn btn-primary">保存全部设置</button>
  </div>
</form>

<div class="qr-zoom" id="shopQrZoom" onclick="if(event.target===this)shopQrZoomClose()">
  <button class="qz-close" onclick="shopQrZoomClose()" aria-label="关闭">×</button>
  <img id="shopQrZoomImg" src="" alt="收款码" referrerpolicy="no-referrer">
  <div class="qz-addr" id="shopQrZoomAddr"></div>
  <a class="qz-open" id="shopQrZoomOpen" href="#" target="_blank" rel="noopener">↗ 在新标签页打开原图</a>
  <div class="qz-hint">这就是客户扫码付款时看到的收款码；满屏查看可确认图片清晰、无裁切。点击空白处或按 Esc 关闭</div>
</div>

<script>

function shopQrZoom(){
  var el = document.getElementById('shopQrPreview');
  if (!el) return;
  var src = el.src;

  var isInline = src.indexOf('data:') === 0;
  document.getElementById('shopQrZoomImg').src = src;
  document.getElementById('shopQrZoomAddr').textContent = isInline ? '（收款码为内嵌图片，无外部地址）' : src;
  var open = document.getElementById('shopQrZoomOpen');
  open.href = src;
  open.style.display = isInline ? 'none' : '';
  document.getElementById('shopQrZoom').classList.add('show');
}
function shopQrZoomClose(){
  document.getElementById('shopQrZoom').classList.remove('show');
}
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape' && document.getElementById('shopQrZoom').classList.contains('show')) shopQrZoomClose();
});
</script>

<?php  ?>
<?php elseif ($shopSub === 'push'): ?>
<?php
$push     = $shopData['push'];
$channels = [];
foreach ((array)$push['channels'] as $ch) {
    $channels[(string)($ch['key'] ?? $ch['type'] ?? '')] = $ch;
}
$events   = (array)$push['on_events'];
$logs     = $dbReady ? shop_push_recent_logs(40) : [];
$canCurl  = function_exists('curl_init');
$canSock  = function_exists('stream_socket_client');
?>

<div class="card">
  <h2>消息推送设置</h2>
  <div class="tips">
    <strong>流程：</strong>客户提交订单 → 订单落库 → 立即推送通知到你手机（不阻塞客户）→ 客户点「我已付款」→ 再次推送 → 你核对收款账单后在「订单管理」点「确认已付款」。<br>
    支持多通道同时推送，任一通道失败会自动进入重试队列，可用下方「测试推送」逐个验证。
  </div>

  <?php if (!$canCurl): ?>
    <div class="alert alert-error" style="background:var(--warn-soft);border-color:var(--warn);color:var(--warn);">
      ⚠️ 未检测到 PHP <strong>curl</strong> 扩展，推送将退化为 stream 方式（一般仍可用，但建议在 1Panel 中启用 curl 扩展）。
    </div>
  <?php endif; ?>
  <?php if (!$canSock): ?>
    <div class="alert alert-error">⚠️ 未检测到 <strong>stream_socket_client</strong>，邮件推送可能无法工作。</div>
  <?php endif; ?>

  <?php if ($dbReady): ?>
    <div class="shop-stats">
      <div class="shop-stat <?php echo $pushStats['pending'] > 0 ? 'alert' : ''; ?>"><div class="v" style="color:var(--warn);"><?php echo (int)$pushStats['pending']; ?></div><div class="t">队列待重试</div></div>
      <div class="shop-stat"><div class="v" style="color:var(--ok);"><?php echo (int)$pushStats['sent']; ?></div><div class="t">已成功推送</div></div>
      <div class="shop-stat <?php echo $pushStats['failed'] > 0 ? 'alert' : ''; ?>"><div class="v" style="color:var(--danger);"><?php echo (int)$pushStats['failed']; ?></div><div class="t">最终失败</div></div>
    </div>
  <?php endif; ?>
</div>

<form method="post">
  <input type="hidden" name="action" value="save_shop_push"><?php echo csrf_field(); ?>

  <div class="card">
    <h2>推送总开关与内容</h2>
    <label class="shop-check"><input type="checkbox" name="push_enable" value="1" <?php echo !empty($push['enable']) ? 'checked' : ''; ?>> 启用消息推送</label>

    <div class="shop-field" style="margin-top:12px;">
      <label>触发时机</label>
      <label class="shop-check"><input type="checkbox" name="ev_created" value="1" <?php echo in_array('created', $events, true) ? 'checked' : ''; ?>> 客户下单时（建单那一刻）</label>
      <label class="shop-check"><input type="checkbox" name="ev_paid" value="1" <?php echo in_array('paid', $events, true) ? 'checked' : ''; ?>> 客户点击「我已付款」时（请求人工复核）</label>
    </div>

    <div class="shop-field">
      <label>通知标题 <span class="hint">（手机通知栏上显示的那一行；留空用默认）</span></label>
      <input type="text" name="push_title" value="<?php echo htmlspecialchars((string)($push['title'] ?? '【{site}】{event_label}')); ?>" placeholder="【{site}】{event_label}">
      <details class="shop-ch plain">
      <summary>✍️ 标题可以怎么写？</summary>
      <div class="ch-body">
        <ul style="margin:0;padding-left:20px;line-height:1.9;">
          <li><b>与商城页面标题一致</b>：填 <code>【{site}】新订单</code>，其中 <code>{site}</code> 会自动替换为「页面文案」里设置的页面标题</li>
          <li><b>完全自定义</b>：不使用变量，直接填写固定文字，例如 <code>你的店名 · 订单提醒</code></li>
          <li><b>附带事件类型</b>：加入 <code>{event_label}</code>，会显示为「客户新下单」「客户已标记付款」</li>
          <li><b>附带订单号</b>：默认不含。如需显示，请自行添加 <code>{order_no}</code></li>
        </ul>
        <div style="margin-top:10px;padding-top:9px;border-top:1px dashed var(--info-border);color:var(--text2);">
          默认值 <code>【{site}】{event_label}</code>　效果示例 <code>【你的店名】客户新下单</code>
        </div>
      </div>
      </details>
    </div>

    <div class="shop-field">
      <label>消息内容模板 <span class="hint">（推送正文；留空则用默认模板）</span></label>
      <details class="shop-ch plain">
        <summary>📝 消息内容模板（点击展开编辑）</summary>
        <div class="ch-body tpl-open">
          <div style="font-size:12.5px;font-weight:600;color:var(--primary);margin-bottom:8px;">👆 在这里直接编辑推送正文（留空则用系统默认模板）</div>
          <textarea name="push_template" style="min-height:190px;font-family:Consolas,Monaco,monospace;font-size:13px;"><?php echo htmlspecialchars($push['template']); ?></textarea>
        </div>
      </details>
    </div>

    <details class="shop-ch plain">
      <summary>📖 可用变量一览（标题和正文模板都能用）</summary>
      <div class="ch-body">
        <table class="shop-vars">
          <thead><tr><th>变量</th><th>含义</th><th>实际会长这样</th></tr></thead>
          <tbody>
            <tr><td><code>{event_label}</code></td><td>事件名</td><td>客户新下单 / 客户已标记付款 / 管理员已确认付款 / 测试推送</td></tr>
            <tr><td><code>{order_no}</code></td><td>订单号</td><td>ORD20260930194200ABCD</td></tr>
            <tr><td><code>{pay_code}</code></td><td>付款备注码（让客户转账时填在附言里的）</td><td>EK</td></tr>
            <tr><td><code>{items}</code></td><td>商品明细</td><td>Steam 代安装×1</td></tr>
            <tr><td><code>{amount}</code></td><td>应付金额（<strong>纯数字，不带 ￥</strong>；要符号就自己写 <code>￥{amount}</code>）</td><td>15</td></tr>
            <tr><td><code>{name}</code></td><td>客户称呼</td><td>张三</td></tr>
            <tr><td><code>{wechat}</code></td><td>客户微信</td><td>zhangsan_wx</td></tr>
            <tr><td><code>{phone}</code></td><td>客户电话</td><td>13800001234</td></tr>
            <tr><td><code>{mode}</code></td><td>服务方式</td><td>立即服务 / 提前预约</td></tr>
            <tr><td><code>{book_time}</code></td><td>客户预约的时间（没填则显示 —）</td><td>2026-10-05 上午（9:00-12:00）</td></tr>
            <tr><td><code>{remark}</code></td><td>客户备注（没填则显示 —）</td><td>电脑是 Win11，麻烦装 D 盘</td></tr>
            <tr><td><code>{status}</code></td><td>订单当前状态</td><td>待付款 / 待人工核查 / 已确认付款</td></tr>
            <tr><td><code>{created_at}</code></td><td>下单时间</td><td>2026-09-30 19:42:15</td></tr>
            <tr><td><code>{site}</code></td><td>商城页面标题（改这里：<strong>收款与表单 → 页面文案 → 页面标题</strong>）</td><td>技术服务 · 在线下单</td></tr>
          </tbody>
        </table>
        <div class="tips" style="margin-top:12px;font-size:12.5px;line-height:1.9;">
          💡 <strong>标题读起来别扭？</strong>因为 <code>{site}</code> 取的是「页面标题」，默认是
          <code>技术服务 · 在线下单</code>，于是通知栏会显示成「【技术服务 · 在线下单】客户新下单」。
          去「收款与表单 → 页面文案 → 页面标题」把它改成你的店名（如 <code>某某技术服务</code>），
          通知标题就顺了。<br>
          下面两个<strong>只在「通用 Webhook」通道的 Body 模板里能用</strong>（企业微信/钉钉/飞书/Bark 等）：<br>
          · <code>{text}</code> —— 整个消息正文（上面模板渲染后的完整内容）<br>
          · <code>{title}</code> —— 通知标题
        </div>
      </div>
    </details>
  </div>

  <?php
  $gotify = $channels['gotify']  ?? [];
  $ntfy   = $channels['ntfy']    ?? [];
  $mail   = $channels['email']   ?? [];
  $hook   = $channels['webhook'] ?? [];
  ?>

  <div class="shop-guide">
    <b>怎么配？</b>点下面的通道标题展开填写，全部填完后再点最下面「保存推送设置」。<br>
    <b>推荐顺序</b>：① <b>Gotify</b>（自建 · 安卓 App · 只需服务器地址+Token）
    → ② <b>ntfy</b>（自建 · 安卓 App · 只需服务器地址+主题）
    → ③ <b>企业微信机器人</b>（用最后的「通用 Webhook」，零部署最省事）
    → ④ <b>邮件</b>（不用装 App）。<br>
    <b>💻 只想在电脑上收？</b>四个通道都支持，最省事的是 <strong>企业微信机器人</strong>（电脑装企业微信桌面版即可），
    其次是 <strong>邮件</strong>（任何邮箱客户端都行）。每个通道展开后都有「电脑端」那一段说明。<br>
    <span style="color:var(--text3);">建议至少启用两个通道互为备份；配好后务必点下方「测试推送」验证一次。</span>
  </div>

  <details class="shop-ch" <?php echo !empty($gotify['enable']) ? 'open' : ''; ?>>
    <summary>
      <span>📱 Gotify（推荐）</span>
      <span class="ch-state <?php echo !empty($gotify['enable']) ? 'on' : 'off'; ?>"><?php echo !empty($gotify['enable']) ? '已启用' : '未启用'; ?></span>
      <span class="ch-hint">只填服务器地址 + 应用 Token 即可</span>
      <?php if (trim((string)($gotify['server'] ?? '')) !== ''): ?>
        <span class="ch-target"><?php echo htmlspecialchars((string)$gotify['server']); ?></span>
      <?php endif; ?>
    </summary>
    <div class="ch-body">
      <div class="tips" style="margin-bottom:14px;">
        自建 Gotify 后，在 WebUI 右上角 <strong>apps</strong> 标签 → <strong>Create Application</strong>，
        把生成的 <strong>Token</strong> 填到下面（Gotify 3 起 Token 只在创建时显示一次，注意留存）。<br>
        手机装官方 <strong>Gotify</strong> App（应用商店 / F-Droid），填服务器地址并用账号登录即可收推送。<br>
        <strong>💻 电脑端</strong>：浏览器打开你的 Gotify 地址（<code>https://gotify.你的域名</code>）
        用账号登录，<strong>WebUI 就是这个收件箱</strong> —— 新订单会实时出现在列表里，页面挂着即可。
      </div>
      <label class="shop-check"><input type="checkbox" name="gotify_enable" value="1" <?php echo !empty($gotify['enable']) ? 'checked' : ''; ?>> 启用 Gotify 通道</label>
      <div class="shop-grid2">
        <div class="shop-field">
          <label>服务器地址</label>
          <input type="text" name="gotify_server" value="<?php echo htmlspecialchars((string)($gotify['server'] ?? '')); ?>" placeholder="https://gotify.你的域名">
        </div>
        <div class="shop-field">
          <label>应用 Token</label>
          <input type="password" name="gotify_token" value="<?php echo htmlspecialchars((string)($gotify['token'] ?? '')); ?>" placeholder="创建应用时生成的 Token" autocomplete="new-password">
        </div>
      </div>
      <details class="shop-sub">
        <summary>高级选项（可选，不改也能正常用）</summary>
        <div class="shop-grid2" style="margin-top:12px;">
          <div class="shop-field">
            <label>消息优先级 <span class="hint">（0-10；Android 上 ≥5 会响铃）</span></label>
            <input type="number" name="gotify_priority" min="0" max="10" value="<?php echo (int)($gotify['priority'] ?? 5); ?>">
          </div>
          <div class="shop-field">
            <label>通知标题前缀</label>
            <input type="text" name="gotify_title" value="<?php echo htmlspecialchars((string)($gotify['title'] ?? '新订单通知')); ?>">
          </div>
        </div>
      </details>
    </div>
  </details>

  <details class="shop-ch" <?php echo !empty($ntfy['enable']) ? 'open' : ''; ?>>
    <summary>
      <span>📡 ntfy</span>
      <span class="ch-state <?php echo !empty($ntfy['enable']) ? 'on' : 'off'; ?>"><?php echo !empty($ntfy['enable']) ? '已启用' : '未启用'; ?></span>
      <span class="ch-hint">只填服务器地址 + 主题(topic) 即可</span>
      <?php if (trim((string)($ntfy['server'] ?? '')) !== ''): ?>
        <span class="ch-target"><?php echo htmlspecialchars((string)$ntfy['server']); ?><?php echo trim((string)($ntfy['topic'] ?? '')) !== '' ? ' / ' . htmlspecialchars((string)$ntfy['topic']) : ''; ?></span>
      <?php endif; ?>
    </summary>
    <div class="ch-body">
      <div class="tips" style="margin-bottom:14px;">
        自建 ntfy 后，手机装官方 <strong>ntfy</strong> App，在 App 里添加你的服务器地址并订阅同一个 topic 即可。<br>
        <strong>💻 电脑端</strong>：① 浏览器打开 <code>https://你的服务器/你的topic</code>（网页版收件箱，挂着就能看）；
        ② ntfy 官方有<strong>桌面 App</strong>（Windows / macOS / Linux），装好后同样填服务器地址 + topic 即可收系统通知。<br>
        服务器部署：<code>docker run -d --name ntfy -p 8080:80 -v /opt/ntfy:/var/cache/ntfy binwiederhier/ntfy serve --cache-file /var/cache/ntfy/cache.db</code><br>
        再用 1Panel 建站点反代到 <code>127.0.0.1:8080</code> 并申请证书（App 要求 HTTPS）。
      </div>
      <label class="shop-check"><input type="checkbox" name="ntfy_enable" value="1" <?php echo !empty($ntfy['enable']) ? 'checked' : ''; ?>> 启用 ntfy 通道</label>
      <div class="shop-grid2">
        <div class="shop-field">
          <label>服务器地址</label>
          <input type="text" name="ntfy_server" value="<?php echo htmlspecialchars((string)($ntfy['server'] ?? '')); ?>" placeholder="https://ntfy.你的域名">
        </div>
        <div class="shop-field">
          <label>Topic（主题名）</label>
          <input type="text" name="ntfy_topic" value="<?php echo htmlspecialchars((string)($ntfy['topic'] ?? '')); ?>" placeholder="your-topic">
        </div>
      </div>
      <details class="shop-sub">
        <summary>高级选项（可选，不改也能正常用）</summary>
        <div class="shop-grid2" style="margin-top:12px;">
          <div class="shop-field">
            <label>访问令牌 <span class="hint">（服务器开启鉴权时填，形如 tk_xxx）</span></label>
            <input type="password" name="ntfy_token" value="<?php echo htmlspecialchars((string)($ntfy['token'] ?? '')); ?>" placeholder="留空表示无需鉴权" autocomplete="new-password">
          </div>
          <div class="shop-field">
            <label>优先级 <span class="hint">（min / low / default / high / max）</span></label>
            <input type="text" name="ntfy_priority" value="<?php echo htmlspecialchars((string)($ntfy['priority'] ?? 'high')); ?>">
          </div>
          <div class="shop-field">
            <label>消息标签 <span class="hint">（emoji 短码，如 shopping_cart）</span></label>
            <input type="text" name="ntfy_tags" value="<?php echo htmlspecialchars((string)($ntfy['tags'] ?? 'shopping_cart')); ?>">
          </div>
          <div class="shop-field">
            <label>通知标题</label>
            <input type="text" name="ntfy_title" value="<?php echo htmlspecialchars((string)($ntfy['title'] ?? '新订单通知')); ?>">
          </div>
        </div>
      </details>
    </div>
  </details>

  <details class="shop-ch" <?php echo !empty($mail['enable']) ? 'open' : ''; ?>>
    <summary>
      <span>📧 邮件推送（SMTP）</span>
      <span class="ch-state <?php echo !empty($mail['enable']) ? 'on' : 'off'; ?>"><?php echo !empty($mail['enable']) ? '已启用' : '未启用'; ?></span>
      <span class="ch-hint">选邮箱类型 → 填账号 + 授权码 + 收件人</span>
      <?php if (trim((string)($mail['to'] ?? '')) !== ''): ?>
        <span class="ch-target">→ <?php echo htmlspecialchars((string)$mail['to']); ?></span>
      <?php endif; ?>
    </summary>
    <div class="ch-body">
      <div class="tips" style="margin-bottom:14px;">
        选好<strong>邮箱类型</strong>会自动填好服务器、端口和加密方式，你只需要填<strong>账号</strong>、
        <strong>授权码</strong>和<strong>收件人</strong>三个值。<br>
        授权码不是登录密码：QQ/163/126 邮箱到网页版「设置 → 账户 → POP3/SMTP服务」里开启后获取；
        Gmail 用「应用专用密码」。<br>
        <strong>💻 电脑端</strong>：任何邮箱客户端都能收（Outlook / Foxmail / 网页邮箱都行），
        建议在客户端里打开<strong>新邮件桌面通知</strong>，这样来单会直接弹窗。
      </div>
      <label class="shop-check"><input type="checkbox" name="email_enable" value="1" <?php echo !empty($mail['enable']) ? 'checked' : ''; ?>> 启用邮件通道</label>
      <div class="shop-grid2">
        <div class="shop-field">
          <label>邮箱类型 <span class="hint">（自动填好服务器参数）</span></label>
          <select id="shopMailPreset" onchange="shopMailPresetChange(this.value)">
            <option value="">— 请选择 —</option>
            <option value="qq">QQ 邮箱</option>
            <option value="163">163 邮箱</option>
            <option value="126">126 邮箱</option>
            <option value="gmail">Gmail</option>
            <option value="outlook">Outlook / Office365</option>
            <option value="custom">自定义 / 企业邮箱</option>
          </select>
        </div>
        <div class="shop-field">
          <label>邮箱账号（也是发件人）</label>
          <input type="text" name="email_user" id="shopMailUser" value="<?php echo htmlspecialchars((string)($mail['user'] ?? '')); ?>" placeholder="you@qq.com">
        </div>
      </div>
      <div class="shop-grid2">
        <div class="shop-field">
          <label>密码 / 授权码</label>
          <input type="password" name="email_pass" value="<?php echo htmlspecialchars((string)($mail['pass'] ?? '')); ?>" placeholder="邮箱授权码（不是登录密码）" autocomplete="new-password">
        </div>
        <div class="shop-field">
          <label>收件人邮箱 <span class="hint">（多个用逗号分隔）</span></label>
          <input type="text" name="email_to" value="<?php echo htmlspecialchars((string)($mail['to'] ?? '')); ?>" placeholder="收通知的邮箱">
        </div>
      </div>
      <details class="shop-sub">
        <summary>服务器参数（选邮箱类型后已自动填好，一般不用改）</summary>
        <div class="shop-grid2" style="margin-top:12px;">
          <div class="shop-field">
            <label>SMTP 服务器</label>
            <input type="text" name="email_host" value="<?php echo htmlspecialchars((string)($mail['host'] ?? '')); ?>" placeholder="smtp.qq.com">
          </div>
          <div class="shop-field">
            <label>端口</label>
            <input type="number" name="email_port" value="<?php echo (int)($mail['port'] ?? 465); ?>" min="1" max="65535">
          </div>
          <div class="shop-field">
            <label>加密方式</label>
            <select name="email_secure">
              <option value="ssl" <?php echo (($mail['secure'] ?? 'ssl') === 'ssl') ? 'selected' : ''; ?>>SSL（通常 465 端口）</option>
              <option value="tls" <?php echo (($mail['secure'] ?? '') === 'tls') ? 'selected' : ''; ?>>STARTTLS（通常 587 端口）</option>
              <option value="none" <?php echo (($mail['secure'] ?? '') === 'none') ? 'selected' : ''; ?>>不加密（通常 25 端口，不推荐）</option>
            </select>
          </div>
          <div class="shop-field">
            <label>发件人显示名</label>
            <input type="text" name="email_from_name" value="<?php echo htmlspecialchars((string)($mail['from_name'] ?? '在线商城')); ?>">
          </div>
          <div class="shop-field">
            <label>发件人邮箱 <span class="hint">（留空则与账号相同）</span></label>
            <input type="text" name="email_from" value="<?php echo htmlspecialchars((string)($mail['from'] ?? '')); ?>">
          </div>
        </div>
      </details>
    </div>
  </details>

  <details class="shop-ch" <?php echo !empty($hook['enable']) ? 'open' : ''; ?>>
    <summary>
      <span>🔗 通用 Webhook</span>
      <span class="ch-state <?php echo !empty($hook['enable']) ? 'on' : 'off'; ?>"><?php echo !empty($hook['enable']) ? '已启用' : '未启用'; ?></span>
      <span class="ch-hint">企业微信 / 钉钉 / 飞书 / Bark / PushMe</span>
      <?php if (trim((string)($hook['url'] ?? '')) !== ''): ?>
        <span class="ch-target"><?php echo htmlspecialchars(mb_substr((string)$hook['url'], 0, 42)); ?></span>
      <?php endif; ?>
    </summary>
    <div class="ch-body">
      <div class="tips" style="margin-bottom:14px;">
        一个通道覆盖 <strong>企业微信 / 钉钉 / 飞书 / Bark / PushMe</strong>：只改 URL 与 Body 模板即可，
        <code>{text}</code> 是完整正文，<code>{title}</code> 是标题。<br>
        <strong>最省事的用法（企业微信机器人，零部署）</strong>：群 → 添加群机器人 → 复制 Webhook 地址填到下面，
        Body 模板保持默认就是企业微信格式。<br>
        <strong>💻 电脑端（强烈推荐）</strong>：电脑装<strong>企业微信桌面版</strong>并登录同一个企业，
        机器人消息会直接推到桌面并<strong>弹系统通知</strong> —— 这是电脑上收新订单最省事的办法。<br>
        钉钉 / 飞书同理：都有电脑版，群机器人消息都会在桌面弹通知。
      </div>
      <label class="shop-check"><input type="checkbox" name="hook_enable" value="1" <?php echo !empty($hook['enable']) ? 'checked' : ''; ?>> 启用 Webhook 通道</label>
      <div class="shop-field">
        <label>请求地址</label>
        <input type="text" name="hook_url" value="<?php echo htmlspecialchars((string)($hook['url'] ?? '')); ?>" placeholder="https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxx">
      </div>
      <div class="shop-grid2">
        <div class="shop-field">
          <label>请求方法</label>
          <select name="hook_method">
            <option value="POST" <?php echo (($hook['method'] ?? 'POST') === 'POST') ? 'selected' : ''; ?>>POST</option>
            <option value="GET" <?php echo (($hook['method'] ?? '') === 'GET') ? 'selected' : ''; ?>>GET</option>
          </select>
        </div>
        <div class="shop-field">
          <label>Body 模板</label>
          <input type="text" name="hook_body" value="<?php echo htmlspecialchars((string)($hook['body_template'] ?? '')); ?>" placeholder='{"msgtype":"text","text":{"content":"{text}"}}'>
        </div>
      </div>
      <details class="shop-sub">
        <summary>高级选项与各平台模板（可选）</summary>
        <div style="margin-top:12px;">
          <div class="shop-field">
            <label>Content-Type</label>
            <input type="text" name="hook_content_type" value="<?php echo htmlspecialchars((string)($hook['content_type'] ?? 'application/json')); ?>">
          </div>
          <div class="shop-field">
            <label>自定义请求头 <span class="hint">（可选，每行一个，如：Authorization: Bearer xxx）</span></label>
            <textarea name="hook_headers" style="min-height:56px;font-family:Consolas,Monaco,monospace;"><?php echo htmlspecialchars((string)($hook['headers'] ?? '')); ?></textarea>
          </div>
          <div class="tips" style="font-size:12.5px;line-height:2;">
            常用 Body 模板：<br>
            · 企业微信：<code>{"msgtype":"text","text":{"content":"{text}"}}</code><br>
            · 钉钉：<code>{"msgtype":"text","text":{"content":"{text}"}}</code><br>
            · 飞书：<code>{"msg_type":"text","content":{"text":"{text}"}}</code><br>
            · Bark：URL 填 <code>https://api.day.app/你的KEY/{title}/{text}?group=订单</code>，方法选 GET，Body 留空
          </div>
        </div>
      </details>
    </div>
  </details>

  <div style="margin-bottom:26px;">
    <button type="submit" class="btn btn-primary">保存推送设置</button>
  </div>
</form>

<div class="card">
  <h2>推送测试与重试</h2>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;">
    <?php foreach (['gotify' => '📱 测试 Gotify', 'ntfy' => '📡 测试 ntfy', 'email' => '📧 测试邮件', 'webhook' => '🔗 测试 Webhook'] as $k => $label): ?>
      <form method="post" style="display:inline;">
        <input type="hidden" name="action" value="shop_push_test"><?php echo csrf_field(); ?>
        <input type="hidden" name="channel" value="<?php echo $k; ?>">
        <button type="submit" class="btn btn-secondary btn-sm"><?php echo $label; ?></button>
      </form>
    <?php endforeach; ?>
    <form method="post" style="display:inline;">
      <input type="hidden" name="action" value="shop_push_process"><?php echo csrf_field(); ?>
      <button type="submit" class="btn btn-secondary btn-sm">♻️ 立即处理重试队列</button>
    </form>
  </div>

  <details class="shop-ch" style="margin-top:4px;">
  <summary>
    <span>🗂 最近推送记录</span>
    <span class="ch-hint">共 <?php echo (int)shop_push_log_count(); ?> 条 · 最新 40 条 · 点开查看</span>
  </summary>
  <div class="ch-body" style="padding:12px 14px;">
  <div style="display:flex;justify-content:flex-end;margin-bottom:10px;">
    <form method="post" onsubmit="return confirm('确定清空全部推送记录吗？此操作不可恢复。');">
      <input type="hidden" name="action" value="shop_push_log_clear"><?php echo csrf_field(); ?>
      <button type="submit" class="btn btn-secondary btn-sm">🗑 清理推送记录</button>
    </form>
  </div>
  <?php if (!$dbReady): ?>
    <div class="empty">数据库不可用</div>
  <?php elseif (empty($logs)): ?>
    <div class="empty">暂无推送记录，可先点上方「测试推送」验证配置</div>
  <?php else: ?>
    <div class="shop-log">
      <?php foreach ($logs as $lg): ?>
        <div class="shop-log-row">
          <span class="<?php echo ((int)$lg['success'] === 1) ? 'ok' : 'no'; ?>"><?php echo ((int)$lg['success'] === 1) ? '✓' : '✕'; ?></span>
          <div style="flex:1;min-width:0;">
            <div><strong><?php echo htmlspecialchars((string)$lg['channel']); ?></strong>
              · <?php echo htmlspecialchars((string)$lg['event']); ?>
              · <?php echo htmlspecialchars((string)$lg['order_no']); ?>
              <?php if ((int)$lg['http_code'] > 0): ?>· HTTP <?php echo (int)$lg['http_code']; ?><?php endif; ?>
            </div>
            <?php if (trim((string)$lg['response']) !== ''): ?>
              <div style="color:var(--text3);word-break:break-all;margin-top:3px;"><?php echo htmlspecialchars(mb_substr((string)$lg['response'], 0, 160)); ?></div>

            <?php endif; ?>
          </div>
          <span class="tm"><?php echo htmlspecialchars((string)$lg['created_at']); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  </div>
  </details>

  <div class="tips" style="margin-top:16px;">
    <strong>没有 cron 的主机怎么办？</strong>本模块已内置「懒重试」：客户访问商城页面或你打开后台订单页时会自动处理到期队列；若你的主机支持计划任务，建议在 1Panel 加一条：
    <code>*/2 * * * * php /网站目录/cron/push_retry.php</code>
  </div>
</div>

<script>

var SHOP_MAIL_PRESETS = {
  qq:      { host:'smtp.qq.com',        port:465, secure:'ssl' },
  '163':   { host:'smtp.163.com',       port:465, secure:'ssl' },
  '126':   { host:'smtp.126.com',       port:465, secure:'ssl' },
  gmail:   { host:'smtp.gmail.com',     port:587, secure:'tls' },
  outlook: { host:'smtp.office365.com', port:587, secure:'tls' }
};
function shopMailPresetChange(key){
  var p = SHOP_MAIL_PRESETS[key];
  if (!p) return;
  var h = document.querySelector('input[name="email_host"]');
  var o = document.querySelector('input[name="email_port"]');
  var s = document.querySelector('select[name="email_secure"]');
  if (h) h.value = p.host;
  if (o) o.value = p.port;
  if (s) s.value = p.secure;
}

(function(){
  try{
    var sel = document.getElementById('shopMailPreset');
    var h = document.querySelector('input[name="email_host"]');
    if (!sel || !h) return;
    var cur = (h.value || '').toLowerCase();
    if (!cur) return;
    for (var k in SHOP_MAIL_PRESETS){
      if (SHOP_MAIL_PRESETS[k].host === cur){ sel.value = k; return; }
    }
    sel.value = 'custom';
  }catch(e){}
})();
</script>

<?php  ?>
<?php elseif ($shopSub === 'security'): ?>
<?php
$rlList   = shop_rl_scan();
$rlDir    = shop_rl_dir_path();
$pendIps  = shop_pending_by_ip(86400, 1);
$pendTot  = 0;
foreach ($pendIps as $r) { $pendTot += (int)$r['c']; }
$expMin   = (int)($pay['unpaid_expire_minutes'] ?? 10);
$rlUrl    = function ($k, $v) { return '?sub=security&' . $k . '=' . urlencode($v)
             . '&csrf=' . urlencode($_SESSION['csrf_token'] ?? ''); };
?>
<div class="card">
  <h2>风控与清理</h2>
  <div class="tips">
    这里管理两类「自动挡下来」的东西：<b>限流封禁</b>（同一 IP 短时间内请求过多）和
    <b>未付款订单</b>（超过设定时间没付款）。<br>
    正常情况下它们都会<strong>自动过期</strong>，不需要你手动处理；这个页面是给
    「误封了真实客户」或「想立刻清掉垃圾单」用的。
  </div>

  <h3>① 限流封禁（<?php echo count($rlList); ?> 条生效中）</h3>
  <?php if ($rlList): ?>
    <div class="tips" style="margin-bottom:10px;">
      剩 <b>0 分钟</b>的记录表示已经到点、下次请求就会自动清掉。
      解除后该 IP 可以立刻重新下单 / 查询。
    </div>
    <div class="table-wrapper">
      <table>
        <thead><tr>
          <th style="width:170px;">IP</th>
          <th style="width:150px;">触发功能</th>
          <th style="width:110px;">次数</th>
          <th style="width:130px;">剩余解封</th>
          <th style="width:120px;">操作</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rlList as $r): ?>
          <tr>
            <td style="word-break:break-all;"><code><?php echo htmlspecialchars($r['ip'] !== '' ? $r['ip'] : '(未记录)'); ?></code></td>
            <td><?php echo htmlspecialchars($r['bucket']); ?></td>
            <td>
              <?php if ($r['limit'] > 0): ?>
                <?php echo (int)$r['count']; ?> / <?php echo (int)$r['limit']; ?>
              <?php else: ?>
                <span class="sub">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php $L = (int)$r['left']; ?>
              <?php if ($L <= 0): ?>
                <span class="sub">已到点</span>
              <?php elseif ($L < 60): ?>
                <?php echo $L; ?> 秒
              <?php else: ?>
                <?php echo (int)ceil($L / 60); ?> 分钟
              <?php endif; ?>
            </td>
            <td>
              <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($rlUrl('rl_release', $r['file'])); ?>"
                 onclick="return confirm('解除这条限流记录？');">解除</a>
              <?php if ($r['ip'] !== '' && $r['ip'] !== '(旧格式，未记录)'): ?>
                <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($rlUrl('rl_release_ip', $r['ip'])); ?>"
                   onclick="return confirm('解除该 IP 的全部限流记录？');">解除该 IP</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="margin-top:12px;">
      <a class="btn btn-danger btn-sm" href="<?php echo htmlspecialchars($rlUrl('rl_release_all', '1')); ?>"
         onclick="return confirm('确定清空全部限流记录吗？\n\n所有 IP 会立刻恢复访问。');">清空全部限流记录</a>
    </div>
  <?php else: ?>
    <div class="empty">当前没有任何 IP 被限流 —— 说明一切正常</div>
  <?php endif; ?>

  <h3 style="margin-top:26px;">② 未付款订单（近 24 小时 <?php echo $pendTot; ?> 笔）</h3>
  <div class="tips" style="margin-bottom:10px;">
    当前设定：超过 <b><?php echo $expMin > 0 ? ($expMin . ' 分钟') : '（已关闭自动失效）'; ?></b>
    未付款的订单会自动删除。
    <?php if ($expMin > 0): ?>
      到了时间它们会自己消失，这里只是给你一个<strong>立刻清理</strong>的入口。
    <?php endif; ?>
  </div>

  <?php if ($pendIps): ?>
    <div class="table-wrapper">
      <table>
        <thead><tr>
          <th style="width:190px;">IP</th>
          <th style="width:110px;">未付款单数</th>
          <th style="width:180px;">最早一笔</th>
          <th>操作</th>
        </tr></thead>
        <tbody>
        <?php foreach ($pendIps as $r): ?>
          <tr>
            <td style="word-break:break-all;"><code><?php echo htmlspecialchars((string)$r['ip']); ?></code></td>
            <td>
              <?php $n = (int)$r['c']; ?>
              <b style="color:<?php echo $n >= 3 ? '#dc2626' : 'inherit'; ?>;"><?php echo $n; ?></b>
              <?php if ($n >= 3): ?><span class="sub">（已达上限）</span><?php endif; ?>
            </td>
            <td class="sub"><?php echo htmlspecialchars((string)$r['first_at']); ?></td>
            <td>
              <a class="btn btn-danger btn-sm" href="<?php echo htmlspecialchars($rlUrl('purge_ip', (string)$r['ip'])); ?>"
                 onclick="return confirm('删除该 IP 的全部未付款订单？\n\n订单会被永久删除，同时解除它的限流记录。');">删除该 IP 的未付款单</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="empty">没有未付款订单</div>
  <?php endif; ?>

  <div style="margin-top:14px;display:flex;gap:9px;flex-wrap:wrap;">
    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($rlUrl('purge_pending', '10')); ?>"
       onclick="return confirm('立即清理超过 10 分钟的未付款订单？');">立即清理 10 分钟前的</a>
    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($rlUrl('purge_pending', '60')); ?>"
       onclick="return confirm('立即清理超过 60 分钟的未付款订单？');">立即清理 1 小时前的</a>
    <a class="btn btn-danger btn-sm" href="<?php echo htmlspecialchars($rlUrl('purge_pending', '1')); ?>"
       onclick="return confirm('确定清理所有超过 1 分钟的未付款订单吗？\n\n正在付款的客户订单也会被删掉，请谨慎使用。');">清理全部未付款单</a>
  </div>

  <h3 style="margin-top:26px;">③ 当前风控规则</h3>
  <div class="table-wrapper">
    <table>
      <thead><tr><th style="width:230px;">项目</th><th>规则</th></tr></thead>
      <tbody>
        <tr><td>下单接口</td><td>同一 IP <b>每 10 分钟最多 8 次</b>；另有全局 60 次 / 分钟总闸</td></tr>
        <tr><td>提交间隔</td><td>两次提交间隔不足 <b>3 秒</b>判定为脚本，直接拒绝</td></tr>
        <tr><td>未付款订单上限</td><td>同一 IP 24 小时内最多留 <b>3 笔</b>未付款订单，超过则拒绝下单</td></tr>
        <tr><td>订单查询</td><td>同一 IP <b>每 10 分钟最多 8 次</b>（接口与查询页分别计算）</td></tr>
        <tr><td>未付款订单自动失效</td><td>超过 <b><?php echo $expMin; ?> 分钟</b>未付款自动删除（在「收款与表单」里可改，0 = 关闭）</td></tr>
        <tr><td>标记已付款 / 换备注码</td><td>分别 20 次 / 10 分钟</td></tr>
        <tr><td>限流缓存目录</td><td><code><?php echo htmlspecialchars($rlDir !== '' ? $rlDir : '（找不到可写目录，限流未生效！）'); ?></code></td></tr>
      </tbody>
    </table>
  </div>

  <h3 style="margin-top:26px;">③ 已失效订单</h3>
  <div class="tips" style="margin-bottom:10px;">
    超时未付款的订单会变成「已失效」——不计入统计、不占未付款名额，但<b>记录保留</b>。
    当前共 <b><?php echo shop_expired_count(); ?></b> 笔。保留
    <b><?php $__kept = (int)($pay['expired_keep_days'] ?? 7); echo $__kept > 0 ? ($__kept . ' 天') : '永久'; ?></b>
    后彻底删除。
  </div>
  <div style="display:flex;gap:9px;flex-wrap:wrap;">
    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars('?sub=orders&status=expired'); ?>">查看已失效订单</a>
    <a class="btn btn-danger btn-sm" href="<?php echo htmlspecialchars($rlUrl('purge_expired', '0')); ?>"
       onclick="return confirm('彻底删除全部「已失效」订单？\n\n这些记录会被永久删除，无法恢复。');">彻底删除全部已失效</a>
  </div>

  <div class="tips tips--warn" style="margin-top:14px;">
    ⚠️ 「删除未付款订单」和「清空限流记录」都是<strong>立即生效且不可撤销</strong>的。
    客户只要点过「我已付款」（订单变成「待人工核查」），就<strong>不在这些清理范围内</strong>，不会被误删。
  </div>
</div>

<?php  ?>
<?php elseif ($shopSub === 'log'): ?>
<?php
$auditLib    = shop_audit_counts();
$auditAction = trim((string)($_GET['a'] ?? ''));
$auditQ      = trim((string)($_GET['aq'] ?? ''));
$auditPage   = max(1, (int)($_GET['ap'] ?? 1));
$auditRes    = shop_audit_list(['action' => $auditAction, 'q' => $auditQ, 'page' => $auditPage, 'per' => 30]);
$auditTotal  = 0;
foreach ($auditLib as $c) { $auditTotal += $c; }
$auditUrl = function ($over = []) use ($auditAction, $auditQ, $auditPage) {
    $p = array_merge(['sub' => 'log', 'a' => $auditAction, 'aq' => $auditQ, 'ap' => $auditPage], $over);
    $p = array_filter($p, function ($v) { return $v !== '' && $v !== null; });
    return '?' . http_build_query($p);
};
?>
<div class="card">
  <h2>操作日志 <span class="hint" style="font-weight:400;font-size:13px;">（共 <?php echo $auditTotal; ?> 条，最新在前）</span></h2>
  <div class="tips">
    记录后台的每一次写操作：<b>谁、什么时候、从哪个 IP、做了什么、结果如何</b>。
    日志存在订单库里（表 <code>audit_log</code>），随 <code>data/shop.db</code> 一起备份。
  </div>

  <form method="get" class="shop-filter">
    <input type="hidden" name="sub" value="log">
    <select name="a" onchange="this.form.submit()">
      <option value="">全部操作（<?php echo $auditTotal; ?>）</option>
      <?php foreach ($auditLib as $k => $c): ?>
        <option value="<?php echo htmlspecialchars($k); ?>" <?php echo $auditAction === $k ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars(shop_audit_label($k)); ?>（<?php echo $c; ?>）
        </option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="aq" value="<?php echo htmlspecialchars($auditQ); ?>"
           placeholder="搜索对象 / 详情 / 结果 / IP / 用户…">
    <button type="submit" class="btn btn-primary">搜索</button>
    <?php if ($auditAction !== '' || $auditQ !== ''): ?>
      <a class="btn btn-secondary" href="?sub=log">清空筛选</a>
    <?php endif; ?>
  </form>

  <?php if (!$auditRes['rows']): ?>
    <div class="shop-empty"><?php echo $auditTotal ? '没有符合条件的日志' : '暂无日志 —— 做一次后台操作就会出现在这里'; ?></div>
  <?php else: ?>
    <div class="shop-table-wrap">
      <table class="shop-table shop-audit-table">
        <thead><tr>
          <th>时间</th>
          <th>操作</th>
          <th>对象</th>
          <th>详情 / 结果</th>
          <th>用户</th>
          <th>IP</th>
        </tr></thead>
        <tbody>
        <?php foreach ($auditRes['rows'] as $r): ?>
          <tr>
            <td><?php echo htmlspecialchars((string)$r['at']); ?></td>
            <td><b><?php echo htmlspecialchars(shop_audit_label((string)$r['action'])); ?></b></td>
            <td class="a-target" title="<?php echo htmlspecialchars((string)$r['target']); ?>"><?php echo $r['target'] !== '' ? htmlspecialchars((string)$r['target']) : '<span class="hint">—</span>'; ?></td>
            <td>
              <?php if ($r['detail'] !== ''): ?><div><?php echo htmlspecialchars((string)$r['detail']); ?></div><?php endif; ?>
              <?php if ($r['result'] !== ''): ?><div class="hint"><?php echo htmlspecialchars((string)$r['result']); ?></div><?php endif; ?>
            </td>
            <td><?php echo $r['user'] !== '' ? htmlspecialchars((string)$r['user']) : '<span class="hint">—</span>'; ?></td>
            <td class="hint"><?php echo htmlspecialchars((string)$r['ip']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($auditRes['pages'] > 1): ?>
      <div style="margin-top:14px;display:flex;gap:10px;align-items:center;">
        <?php if ($auditRes['page'] > 1): ?>
          <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($auditUrl(['ap' => $auditRes['page'] - 1])); ?>">‹ 上一页</a>
        <?php endif; ?>
        <span class="hint">第 <?php echo $auditRes['page']; ?> / <?php echo $auditRes['pages']; ?> 页</span>
        <?php if ($auditRes['page'] < $auditRes['pages']): ?>
          <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars($auditUrl(['ap' => $auditRes['page'] + 1])); ?>">下一页 ›</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <h3 style="margin-top:26px;">清理日志</h3>
  <div class="tips">日志会一直累积。清理<b>不可恢复</b>，建议先确认没有需要留档的记录。</div>
  <div style="display:flex;gap:9px;flex-wrap:wrap;margin-top:12px;">
    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars('?sub=log&audit_clear=30&csrf=' . urlencode((string)($_SESSION['csrf_token'] ?? ''))); ?>"
       onclick="return confirm('清理 30 天前的日志？\n\n更近的日志会保留。');">清理 30 天前</a>
    <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars('?sub=log&audit_clear=90&csrf=' . urlencode((string)($_SESSION['csrf_token'] ?? ''))); ?>"
       onclick="return confirm('清理 90 天前的日志？\n\n更近的日志会保留。');">清理 90 天前</a>
    <a class="btn btn-danger btn-sm" href="<?php echo htmlspecialchars('?sub=log&audit_clear=0&csrf=' . urlencode((string)($_SESSION['csrf_token'] ?? ''))); ?>"
       onclick="return confirm('确定清空全部操作日志吗？\n\n所有记录会被永久删除，无法恢复。');">清空全部</a>
  </div>
</div>

<?php endif; ?>
