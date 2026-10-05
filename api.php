<?php

date_default_timezone_set('Asia/Shanghai');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

define('IN_CRYPT', true);
define('IN_SHOP', true);
require_once __DIR__ . '/inc/crypt.php';
require_once __DIR__ . '/inc/shop_store.php';
require_once __DIR__ . '/inc/push.php';

function shop_api_out($arr) {
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

function shop_api_err($msg, $extra = []) {
    shop_api_out(array_merge(['ok' => false, 'msg' => $msg], $extra));
}

function shop_api_in() {
    static $data = null;
    if ($data !== null) return $data;

    $raw = file_get_contents('php://input');
    $json = null;
    if (is_string($raw) && trim($raw) !== '') {
        $json = json_decode($raw, true);
    }
    if (is_array($json)) {
        $data = $json;
    } else {
        $data = is_array($_POST) ? $_POST : [];
    }
    return $data;
}

function shop_api_val($key, $default = '') {
    $in = shop_api_in();
    $v = $in[$key] ?? $default;
    if (is_array($v)) return $default;
    return is_string($v) ? trim($v) : $v;
}

function shop_api_arr($key, $default = []) {
    $in = shop_api_in();
    $v = $in[$key] ?? $default;
    return is_array($v) ? $v : $default;
}

function shop_api_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) return trim($_SERVER['HTTP_X_REAL_IP']);
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function shop_api_same_origin() {
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref === '') return true;

    $host = parse_url($ref, PHP_URL_HOST);
    if (!$host) return false;
    $host = strtolower($host);
    $self = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    if ($self !== '' && $host === $self) return true;
    foreach (['localhost', '127.0.0.1'] as $ok) {
        if ($host === $ok) return true;
    }
    return false;
}

function shop_api_ratelimit($bucket, $max, $windowSec) {
    $dir = shop_rl_dir();
    if ($dir === '') return true;
    $file = $dir . '/shop_rl_' . md5($bucket . '|' . shop_api_ip());
    $now  = time();

    $fp = @fopen($file, 'c+');
    if (!$fp) return true;

    $ok = true;
    if (flock($fp, LOCK_EX)) {
        $content = stream_get_contents($fp);
        $d = json_decode((string)$content, true);
        if (!is_array($d) || !isset($d['c'], $d['t']) || ($now - (int)$d['t']) > $windowSec) {
            $d = ['c' => 1, 't' => $now, 'w' => $windowSec, 'max' => $max];
        } else {
            $d['c'] = (int)$d['c'] + 1;
            if ($d['c'] > $max) $ok = false;
        }

        $d['ip'] = shop_api_ip();
        $d['b']  = (string)$bucket;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($d));
        fflush($fp);
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    if (random_int(1, 20) === 1) {
        shop_rl_purge();
    }
    return $ok;
}

function shop_rl_dir() {
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
    error_log('[shop] 找不到可写的限流目录，限流将不生效：' . __DIR__);
    $dir = '';
    return $dir;
}

function shop_api_too_fast($key, $minSec = 3) {
    $dir = shop_rl_dir();
    if ($dir === '') return false;
    $file = $dir . '/shop_ts_' . md5($key . '|' . shop_api_ip());
    $now  = time();

    $raw = is_file($file) ? (string)@file_get_contents($file) : '';
    $last = 0;
    if ($raw !== '') {
        $j = json_decode($raw, true);
        $last = is_array($j) ? (int)($j['t'] ?? 0) : (int)$raw;
    }
    @file_put_contents($file, json_encode(['t' => $now, 'ip' => shop_api_ip(), 'b' => (string)$key]), LOCK_EX);
    if (random_int(1, 20) === 1) shop_rl_purge();
    return ($last > 0 && ($now - $last) < $minSec);
}

function shop_api_defer(callable $fn) {
    register_shutdown_function(function () use ($fn) {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        @ignore_user_abort(true);
        @set_time_limit(25);
        try {
            $fn();
        } catch (Throwable $e) {
            error_log('[shop] deferred task failed: ' . $e->getMessage());
        }
    });
}

function shop_api_pay_payload(array $cfg) {
    $pay = $cfg['pay'];
    $url = trim((string)$pay['contact_admin_url']);
    if ($url !== '' && !preg_match('#^(https?:)?//#i', $url) && strpos($url, '/') !== 0) {
        $url = './' . ltrim($url, './');
    }
    return [
        'qr_mode'            => ((string)($pay['qr_mode'] ?? 'aggregate') === 'split') ? 'split' : 'aggregate',
        'qr_url'             => (string)$pay['qr_url'],
        'qr_wechat'          => (string)($pay['qr_wechat'] ?? ''),
        'qr_alipay'          => (string)($pay['qr_alipay'] ?? ''),
        'qr_tip'             => (string)$pay['qr_tip'],
        'payee_name'         => (string)$pay['payee_name'],
        'code_mode'          => (string)$pay['code_mode'],
        'allow_regenerate'   => !empty($pay['allow_regenerate']),
        'contact_admin_url'  => $url,
        'contact_admin_text' => (string)$pay['contact_admin_text'],
        'tips'               => (string)$pay['tips'],
    ];
}

function shop_api_order_public(array $o) {
    return [
        'order_no'     => $o['order_no'],
        'pay_code'     => $o['pay_code'],
        'pay_method'   => (string)($o['pay_method'] ?? ''),
        'pay_method_label' => (string)($o['pay_method_label'] ?? ''),
        'status'       => $o['status'],
        'status_label' => $o['status_label'],
        'items'        => $o['items'],
        'items_text'   => $o['items_text'],
        'amount'       => shop_price($o['amount']),
        'amount_raw'   => (float)$o['amount'],
        'name'         => $o['customer_name'],
        'phone'        => $o['phone'],
        'wechat'       => $o['wechat'],
        'mode'         => $o['mode'],
        'mode_label'   => $o['mode_label'],
        'book_time'    => $o['book_time'],
        'remark'       => $o['remark'],
        'created_at'   => $o['created_at'],
        'pay_marked_at'=> $o['pay_marked_at'],
        'verified_at'  => $o['verified_at'],
        'admin_note'   => $o['admin_note'],
    ];
}

$action = (string)shop_api_val('action', '');

if (!shop_api_same_origin()) {
    shop_api_err('请求来源校验失败，请从本站页面正常操作');
}

$cfg = shop_config();

switch ($action) {

    case 'create': {
        if (!shop_api_ratelimit('create', 8, 600)) {
            shop_api_err('提交过于频繁，请 10 分钟后再试，或直接联系管理员');
        }
        if (!shop_api_ratelimit('create_global', 60, 60)) {
            shop_api_err('当前访问量较大，请稍后再试');
        }

        if ((string)shop_api_val('website', '') !== '') {
            shop_api_err('提交失败，请刷新页面后重试');
        }

        if (shop_api_too_fast('create', 3)) {
            shop_api_err('提交过于频繁，请稍后再试');
        }

        shop_order_expire_unpaid();

        if (shop_order_count_recent_pending(shop_api_ip(), 86400) >= 3) {
            shop_api_err('您名下还有未完成付款的订单，请先完成付款；如已付款请联系管理员核对，处理后再下单');
        }

        $name   = (string)shop_api_val('name', '');
        $wechat = (string)shop_api_val('wechat', '');
        $phone  = (string)shop_api_val('phone', '');
        $remark = (string)shop_api_val('remark', '');
        $mode   = (string)shop_api_val('mode', 'now');

        if (mb_strlen($name) < 1 || mb_strlen($name) > 60) {
            shop_api_err('请填写您的称呼（1-60 字）');
        }
        if (!empty($cfg['form']['require_wechat']) && mb_strlen($wechat) < 2) {
            shop_api_err('请填写您的微信号');
        }
        if (mb_strlen($wechat) > 64) shop_api_err('微信号过长');
        if (!empty($cfg['form']['require_phone'])) {
            $digits = preg_replace('/\D+/', '', $phone);
            if (strlen($digits) < 5 || strlen($digits) > 20) {
                shop_api_err('请填写正确的联系电话');
            }
        }
        if (mb_strlen($remark) > 500) shop_api_err('备注内容过长（最多 500 字）');

        $itemsIn = shop_api_arr('items', []);
        if (!$itemsIn) {
            $pid = (string)shop_api_val('product_id', '');
            $qty = (int)shop_api_val('qty', 1);
            if ($pid === '') shop_api_err('请选择要下单的商品');
            $itemsIn = [['id' => $pid, 'qty' => $qty]];
        }
        $items = [];
        foreach ($itemsIn as $line) {
            if (!is_array($line)) continue;
            $items[] = ['id' => (string)($line['id'] ?? ''), 'qty' => (int)($line['qty'] ?? 1)];
        }

        $created = shop_order_create([
            'name'      => $name,
            'wechat'    => $wechat,
            'phone'     => $phone,
            'remark'    => $remark,
            'mode'      => $mode,
            'book_date' => (string)shop_api_val('book_date', ''),
            'book_slot' => (string)shop_api_val('book_slot', ''),
            'items'     => $items,
            'ip'        => shop_api_ip(),
            'ua'        => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ]);

        if (!is_array($created)) {
            shop_api_err(is_string($created) && $created !== '' ? $created : '下单失败，请稍后重试');
        }

        shop_api_defer(function () use ($created) {
            shop_push_dispatch('created', $created);
            shop_push_process_queue(3, 5);
        });

        shop_api_out([
            'ok'    => true,
            'msg'   => '下单成功',
            'order' => shop_api_order_public($created),
            'pay'   => shop_api_pay_payload($cfg),
        ]);
        break;
    }

    case 'set_pay_method': {
        $orderNo = (string)shop_api_val('order_no', '');
        $pm      = (string)shop_api_val('pay_method', '');
        if ($orderNo === '' || ($pm !== 'wechat' && $pm !== 'alipay')) {
            shop_api_err('参数不正确');
        }
        $order = shop_order_get($orderNo);
        if (!$order) shop_api_err('订单不存在');
        shop_order_set_pay_method($orderNo, $pm);
        shop_api_out(['ok' => true, 'pay_method' => $pm, 'pay_method_label' => shop_pay_method_label($pm)]);
        break;
    }

    case 'mark_paid': {
        if (!shop_api_ratelimit('mark_paid', 20, 600)) {
            shop_api_err('操作过于频繁，请稍后再试');
        }

        $orderNo = (string)shop_api_val('order_no', '');
        $secret  = (string)shop_api_val('secret', '');
        if ($orderNo === '') {
            shop_api_err('请提供订单号');
        }

        $order = shop_order_get($orderNo);
        if (!$order) shop_api_err('订单不存在，请核对订单号');

        if ($secret !== '' && !shop_order_check_owner($order, $secret)) {
            shop_api_err('联系方式与订单不匹配，请核对后重试');
        }
        // 已失效（超时未付款被自动作废）的订单要特殊对待：真实付款是要花时间的 ——
        // 客户扫码、输金额、确认，再回来点「我已付款」，很容易超过「未付款自动失效」的时限。
        // 冷冰冰地拒绝会让真付了钱的客户什么都提交不了，管理员也收不到任何提醒。
        // 所以允许把 expired 复活成待人工核查，交给管理员核对到账。
        $__revived = false;
        if (!in_array($order['status'], ['pending_payment', 'payment_failed'], true)) {
            if ($order['status'] === 'expired') {
                $__revived = true;
            } else {
                shop_api_err('该订单当前状态为「' . $order['status_label'] . '」，无需重复提交', ['order' => shop_api_order_public($order)]);
            }
        }

        $pm = (string)shop_api_val('pay_method', '');
        if ($pm === 'wechat' || $pm === 'alipay') {
            shop_order_set_pay_method($orderNo, $pm);
        }

        $r = shop_order_set_status($orderNo, 'awaiting_verify');
        if ($r !== true) shop_api_err(is_string($r) ? $r : '操作失败，请稍后重试');

        $fresh = shop_order_get($orderNo);

        shop_api_defer(function () use ($fresh) {
            shop_push_dispatch('paid', $fresh);
            shop_push_process_queue(3, 5);
        });

        shop_api_out([
            'ok'    => true,
            'revived' => $__revived,
            'msg'     => $__revived
                ? '订单曾因超时被标记失效，已把你的付款信息重新提交给管理员核实'
                : '已提交付款信息，等待管理员人工核实',
            'order' => shop_api_order_public($fresh),
            'pay'   => shop_api_pay_payload($cfg),
        ]);
        break;
    }

    case 'query': {
        if (!shop_api_ratelimit('query', 10, 600)) {
            shop_api_err('查询过于频繁，请 10 分钟后再试');
        }
        if (empty($cfg['query']['enable'])) {
            shop_api_err('订单查询功能暂未开放，请联系管理员');
        }

        $orderNo = (string)shop_api_val('order_no', '');
        $secret  = (string)shop_api_val('secret', '');
        if ($orderNo === '') shop_api_err('请输入订单号');
        if ($secret === '') shop_api_err('请输入下单时填写的手机号或微信号');

        $order = shop_order_get($orderNo);
        if (!$order) shop_api_err('未查询到该订单，请核对订单号是否正确');
        if (!shop_order_check_owner($order, $secret)) {
            shop_api_err('订单号与您填写的联系方式不匹配，请核对后重试');
        }

        $pay = in_array($order['status'], ['pending_payment', 'payment_failed'], true)
            ? shop_api_pay_payload($cfg) : null;

        shop_api_out([
            'ok'    => true,
            'msg'   => '查询成功',
            'order' => shop_api_order_public($order),
            'pay'   => $pay,
        ]);
        break;
    }

    case 'regen_code': {
        if (!shop_api_ratelimit('regen_code', 20, 600)) {
            shop_api_err('操作过于频繁，请稍后再试');
        }
        if (empty($cfg['pay']['allow_regenerate'])) {
            shop_api_err('管理员已关闭更换备注码功能');
        }
        if (($cfg['pay']['code_mode'] ?? 'short') !== 'short') {
            shop_api_err('当前为「使用微信作为备注码」模式，无需更换');
        }

        $orderNo = (string)shop_api_val('order_no', '');
        $secret  = (string)shop_api_val('secret', '');
        if ($orderNo === '') shop_api_err('请提供订单号');
        $order = shop_order_get($orderNo);
        if (!$order) shop_api_err('订单不存在');

        if ($secret !== '' && !shop_order_check_owner($order, $secret)) {
            shop_api_err('联系方式与订单不匹配，请核对后重试');
        }
        if ($order['status'] !== 'pending_payment') {
            shop_api_err('订单已进入处理流程，无法更换备注码');
        }

        $db = shop_db();
        if (!$db) shop_api_err(shop_db_unavailable_hint());

        try {
            $code = shop_pay_code_new($db, $cfg['pay']['code_length'] ?? 2);
            $st = $db->prepare('UPDATE orders SET pay_code = ?, updated_at = ? WHERE order_no = ?');
            $st->execute([$code, date('Y-m-d H:i:s'), $orderNo]);
        } catch (Throwable $e) {
            shop_api_err('更换失败，请稍后重试');
        }

        shop_api_out(['ok' => true, 'msg' => '已更换备注码', 'pay_code' => $code]);
        break;
    }

    case 'ping': {
        if (random_int(1, 4) === 1) {
            shop_api_defer(function () {
                shop_push_process_queue(5, 5);
            });
        }
        shop_api_out(['ok' => true, 'msg' => 'pong']);
        break;
    }

    default:
        shop_api_err('未知的接口动作');
}
