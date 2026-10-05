<?php

if (!defined('IN_SHOP')) define('IN_SHOP', true);
if (!defined('IN_CRYPT')) define('IN_CRYPT', true);
if (!function_exists('loadEncryptedData')) {
    require_once __DIR__ . '/crypt.php';
}

if (!defined('SHOP_DATA_DIR'))    define('SHOP_DATA_DIR', dirname(__DIR__) . '/data');
if (!defined('SHOP_CONFIG_FILE')) define('SHOP_CONFIG_FILE', SHOP_DATA_DIR . '/shop.json');
if (!defined('SHOP_DB_FILE'))     define('SHOP_DB_FILE', SHOP_DATA_DIR . '/shop.db');

function shop_status_map() {
    return [
        'pending_payment' => ['label' => '待付款',       'color' => '#475569', 'bg' => '#f1f5f9'],
        'awaiting_verify' => ['label' => '待人工核查',   'color' => '#0e7490', 'bg' => '#cffafe'],
        'paid'            => ['label' => '已确认付款',   'color' => '#047857', 'bg' => '#d1fae5'],
        'payment_failed'  => ['label' => '付款失败',     'color' => '#b91c1c', 'bg' => '#fee2e2'],
        'processing'      => ['label' => '处理中',       'color' => '#7e22ce', 'bg' => '#f3e8ff'],
        'completed'       => ['label' => '已完成',       'color' => '#065f46', 'bg' => '#a7f3d0'],
        'expired'         => ['label' => '已失效',       'color' => '#78716c', 'bg' => '#e7e5e4'],
        'cancelled'       => ['label' => '已取消',       'color' => '#6b7280', 'bg' => '#e5e7eb'],
        'refunding'       => ['label' => '退款中',       'color' => '#c2410c', 'bg' => '#ffedd5'],
        'refunded'        => ['label' => '已退款',       'color' => '#9a3412', 'bg' => '#fed7aa'],
    ];
}

function shop_status_tabs() {
    return [
        'all'             => '全部',
        'pending_payment' => '待付款',
        'awaiting_verify' => '待人工核查',
        'paid'            => '已确认付款',
        'payment_failed'  => '付款失败',
        'processing'      => '处理中',
        'completed'       => '已完成',
        'expired'         => '已失效（超时未付款）',
        'cancelled'       => '已取消',
        'refunded'        => '已退款',
    ];
}

function shop_status_label($s) {
    $m = shop_status_map();
    return $m[$s]['label'] ?? $s;
}

function shop_status_valid($s) {
    return array_key_exists($s, shop_status_map());
}

function shop_status_badge($s) {
    $m = shop_status_map();
    $c = $m[$s] ?? ['label' => $s, 'color' => '#475569', 'bg' => '#f1f5f9'];
    return '<span class="shop-badge" style="color:' . $c['color'] . ';background:' . $c['bg'] . ';">'
         . htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8') . '</span>';
}

function shop_default_config() {
    return [
        'page_title'    => '技术服务 · 在线下单',
        'page_subtitle' => '虚拟商品与远程服务，支持立即下单与提前预约',
        'announcement'  => '下单后请扫码付款，并务必填写「付款备注码」；付款完成后请点击「我已付款」，人工核实后会尽快安排技术员联系您。',

        'brand_name'       => '',
        'topbar_query'     => '🔍 查询订单',
        'topbar_back'      => '🛍 回到商城',
        'topbar_back_url'  => './index.php',
        'footer_title'     => '',
        'footer_note'      => '下单后请扫码付款并填写付款备注码，付款完成点击「我已付款」，管理员人工核实后安排技术员联系您。',
        'footer_link1_text' => '订单查询',
        'footer_link1_url'  => './query.php',
        'footer_link2_text' => '联系我们',
        'footer_link2_url'  => './contact.php',
        'pay' => [
            'qr_mode'            => 'aggregate',
            'qr_url'             => '',
            'qr_wechat'          => '',
            'qr_alipay'          => '',
            'qr_tip'             => '微信 / 支付宝 扫码支付',
            'payee_name'         => '',
            'code_mode'          => 'short',
            'code_length'        => 2,
            'allow_regenerate'   => 1,

            'unpaid_expire_minutes' => 30,
            'expired_keep_days'     => 7,
            'contact_admin_url'  => 'Contact.html',
            'contact_admin_text' => '联系管理员',

        'tips'               => '支付时请在「备注 / 附言」中填写上方备注码，方便我们快速核对到账信息。',
        ],
        'form' => [
            'require_wechat'    => 1,
            'require_phone'     => 1,
            'require_book_time' => 1,
            'enable_remark'     => 1,
            'enable_booking'    => 1,
            'notice'            => '收集您的称呼、微信与电话，仅用于安排技术员联系您并提供服务；填写电话与微信是为了方便您在网站「订单查询」中自助查询订单信息与进度。',
        ],
        'query' => [
            'enable'          => 1,
            'need_phone_tail' => 1,
            'page_title'      => '订单查询',
            'page_tip'        => '填写下单时留的手机号或微信号，即可查看您的全部订单（最新在前）；也可以再填订单号只查某一单。',
        ],
        'push' => [
            'enable'    => 1,
            'on_events' => ['created', 'paid'],

            'title'     => '【{site}】{event_label}',
            'template'  => "🛒 {event_label}\n订单号：{order_no}\n备注码：{pay_code}\n商品：{items}\n金额：￥{amount}\n称呼：{name}\n微信：{wechat}\n电话：{phone}\n类型：{mode}\n预约：{book_time}\n备注：{remark}\n状态：{status}\n时间：{created_at}",
            'channels'  => [
                [
                    'key' => 'gotify', 'name' => 'Gotify（自建，安卓 App，推荐）', 'type' => 'gotify', 'enable' => 0,
                    'server' => '', 'token' => '', 'priority' => 5, 'title' => '新订单通知',
                ],
                [
                    'key' => 'ntfy', 'name' => 'ntfy 推送（自建，安卓 App）', 'type' => 'ntfy', 'enable' => 0,
                    'server' => '', 'topic' => '', 'token' => '', 'priority' => 'high', 'tags' => 'shopping_cart', 'title' => '新订单通知',
                ],
                [
                    'key' => 'email', 'name' => '邮件推送（SMTP）', 'type' => 'email', 'enable' => 0,
                    'host' => '', 'port' => 465, 'secure' => 'ssl', 'user' => '', 'pass' => '',
                    'from' => '', 'to' => '', 'from_name' => '在线商城',
                ],
                [
                    'key' => 'webhook', 'name' => '通用 Webhook（企业微信/钉钉/飞书/Bark/Gotify/PushMe）', 'type' => 'webhook', 'enable' => 0,
                    'url' => '', 'method' => 'POST', 'content_type' => 'application/json', 'headers' => '',
                    'body_template' => '{"msgtype":"text","text":{"content":"{text}"}}',
                ],
            ],
        ],

        'contact' => [
            'enable'      => 1,
            'owner_name'  => '站长',
            'title'       => '',
            'subtitle'    => '有疑问随时找我，看到会尽快回复',
            'notice'      => '',
            'wechat'      => '',
            'qq'          => '',
            'phone'       => '',
            'email'       => '',
            'work_hours'  => '',
            'qr_url'      => '',
            'qr_tip'      => '扫码添加微信',
            'douyin_home' => '',
            'douyin_live' => '',
            'douyin_id'   => '',
            'extra'       => '',
            'footer_note' => '',
        ],
        'categories' => [
            ['key' => 'software', 'name' => '软件安装'],
            ['key' => 'maintain', 'name' => '系统维护'],
            ['key' => 'dev',      'name' => '开发服务'],
            ['key' => 'physical', 'name' => '实物商品'],
            ['key' => 'other',    'name' => '其他服务'],
        ],
        'products' => [],
    ];
}

function shop_config($reload = false) {
    static $cfg = null;
    if ($cfg !== null && !$reload) return $cfg;

    $def = shop_default_config();
    $raw = loadEncryptedData(SHOP_CONFIG_FILE, []);
    if (!is_array($raw)) $raw = [];

    foreach ($def as $k => $v) {
        if (!array_key_exists($k, $raw)) $raw[$k] = $v;
    }
    foreach (['pay', 'form', 'query', 'push'] as $g) {
        if (!isset($raw[$g]) || !is_array($raw[$g])) {
            $raw[$g] = $def[$g];
        } else {
            foreach ($def[$g] as $k => $v) {
                if (!array_key_exists($k, $raw[$g])) $raw[$g][$k] = $v;
            }
        }
    }
    if (!isset($raw['categories']) || !is_array($raw['categories'])) $raw['categories'] = $def['categories'];
    if (!isset($raw['products'])   || !is_array($raw['products']))   $raw['products']   = [];

    $legacyTips = '扫码支付完成后请点击「我已付款」；付款时请务必在备注/附言中填写上方备注码，方便我们快速核对。';
    if (($raw['pay']['tips'] ?? null) === $legacyTips) {
        $raw['pay']['tips'] = $def['pay']['tips'];
    }
    $legacyQueryTip = '输入订单号 + 下单时填写的手机号后 4 位，即可查询您的订单进度。';
    if (($raw['query']['page_tip'] ?? null) === $legacyQueryTip) {
        $raw['query']['page_tip'] = $def['query']['page_tip'];
    }

    $legacyNotice = '收集您的称呼、微信与电话，仅用于安排技术员联系您并提供服务；填写电话与微信是为了方便您在网站「订单查询」中自助查询订单信息与进度。我们不会将您的信息用于其他用途。';
    if (($raw['form']['notice'] ?? null) === $legacyNotice) {
        $raw['form']['notice'] = $def['form']['notice'];
    }

    $cfg = $raw;
    return $cfg;
}

function shop_config_save(array $cfg) {
    $ok = saveEncryptedData(SHOP_CONFIG_FILE, $cfg);
    shop_config(true);
    return $ok;
}

function shop_gen_id() {
    return date('YmdHis') . '_' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
}

function shop_categories() {
    $c = shop_config();
    return is_array($c['categories']) ? $c['categories'] : [];
}

function shop_category_name($key) {
    foreach (shop_categories() as $c) {
        if (($c['key'] ?? '') === $key) return $c['name'] ?? $key;
    }
    return $key === '' ? '未分类' : $key;
}

function shop_products_public() {
    $c = shop_config();
    $out = [];
    foreach ((array)$c['products'] as $p) {
        if (($p['status'] ?? 'on') !== 'on') continue;
        $out[] = $p;
    }
    return $out;
}

function shop_find_product($id) {
    $c = shop_config();
    foreach ((array)$c['products'] as $i => $p) {
        if (($p['id'] ?? '') === (string)$id) return ['index' => $i, 'product' => $p];
    }
    return null;
}

function shop_price($v) {
    $v = (float)$v;
    return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
}

function shop_db_available() {
    if (!extension_loaded('pdo_sqlite') || !class_exists('PDO')) return false;
    try {
        return in_array('sqlite', PDO::getAvailableDrivers(), true);
    } catch (Throwable $e) {
        return false;
    }
}

function shop_db_unavailable_hint() {
    return '未检测到 PHP 的 pdo_sqlite 扩展，订单功能暂不可用。'
         . '请在 1Panel →「网站 → PHP 运行环境 → 扩展」中勾选安装 pdo_sqlite（或 sudo apt install php8.x-sqlite3），装好后重启 PHP-FPM。';
}

function shop_db() {
    static $db = null;
    static $tried = false;
    if ($db instanceof PDO) return $db;
    if ($tried) return null;
    $tried = true;

    if (!shop_db_available()) return null;

    try {
        if (!is_dir(SHOP_DATA_DIR)) @mkdir(SHOP_DATA_DIR, 0755, true);
        $db = new PDO('sqlite:' . SHOP_DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec('PRAGMA journal_mode = WAL');
        $db->exec('PRAGMA busy_timeout = 5000');
        $db->exec('PRAGMA synchronous = NORMAL');
        shop_db_migrate($db);
        if (is_file(SHOP_DB_FILE)) @chmod(SHOP_DB_FILE, 0600);
    } catch (Throwable $e) {
        error_log('[shop] SQLite init failed: ' . $e->getMessage());
        $db = null;
    }
    return $db;
}

function shop_db_add_column(PDO $db, $table, $col, $def) {
    try {
        $cols = $db->query("PRAGMA table_info(" . $table . ")")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array($col, $cols, true)) {
            $db->exec("ALTER TABLE " . $table . " ADD COLUMN " . $col . ' ' . $def);
        }
    } catch (Throwable $e) {
    }
}

function shop_pay_method_label($k) {
    $k = (string)$k;
    if ($k === 'wechat') return '微信支付';
    if ($k === 'alipay') return '支付宝';
    return '';
}

function shop_order_set_pay_method($orderNo, $m) {
    $m = (string)$m;
    if ($m !== 'wechat' && $m !== 'alipay') return false;
    $db = shop_db();
    if (!$db) return false;
    try {
        $st = $db->prepare('UPDATE orders SET pay_method = ?, updated_at = ? WHERE order_no = ?');
        $st->execute([$m, date('Y-m-d H:i:s'), $orderNo]);
        return $st->rowCount() >= 0;
    } catch (Throwable $e) {
        return false;
    }
}

function shop_db_migrate(PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS orders (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        order_no      TEXT    NOT NULL UNIQUE,
        pay_code      TEXT    NOT NULL DEFAULT '',
        pay_method    TEXT    NOT NULL DEFAULT '',
        mode          TEXT    NOT NULL DEFAULT 'now',
        status        TEXT    NOT NULL DEFAULT 'pending_payment',
        customer_name TEXT    NOT NULL DEFAULT '',
        wechat        TEXT    NOT NULL DEFAULT '',
        phone         TEXT    NOT NULL DEFAULT '',
        book_date     TEXT    NOT NULL DEFAULT '',
        book_slot     TEXT    NOT NULL DEFAULT '',
        remark        TEXT    NOT NULL DEFAULT '',
        items_json    TEXT    NOT NULL DEFAULT '[]',
        amount        REAL    NOT NULL DEFAULT 0,
        client_ip     TEXT    NOT NULL DEFAULT '',
        ua            TEXT    NOT NULL DEFAULT '',
        pay_marked_at TEXT    NOT NULL DEFAULT '',
        verified_at   TEXT    NOT NULL DEFAULT '',
        verified_by   TEXT    NOT NULL DEFAULT '',
        admin_note    TEXT    NOT NULL DEFAULT '',
        created_at    TEXT    NOT NULL DEFAULT '',
        updated_at    TEXT    NOT NULL DEFAULT ''
    )");
    foreach ([
        "CREATE INDEX IF NOT EXISTS idx_orders_status  ON orders(status)",
        "CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at DESC)",
        "CREATE INDEX IF NOT EXISTS idx_orders_wechat  ON orders(wechat)",
        "CREATE INDEX IF NOT EXISTS idx_orders_phone   ON orders(phone)",
        "CREATE INDEX IF NOT EXISTS idx_orders_code    ON orders(pay_code)",
    ] as $sql) { $db->exec($sql); }

    shop_db_add_column($db, 'orders', 'pay_method', "TEXT NOT NULL DEFAULT ''");

    $db->exec("CREATE TABLE IF NOT EXISTS push_queue (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        order_no   TEXT    NOT NULL DEFAULT '',
        event      TEXT    NOT NULL DEFAULT '',
        channel    TEXT    NOT NULL DEFAULT '',
        payload    TEXT    NOT NULL DEFAULT '',
        attempts   INTEGER NOT NULL DEFAULT 0,
        next_try   INTEGER NOT NULL DEFAULT 0,
        status     TEXT    NOT NULL DEFAULT 'pending',
        last_error TEXT    NOT NULL DEFAULT '',
        created_at TEXT    NOT NULL DEFAULT '',
        sent_at    TEXT    NOT NULL DEFAULT ''
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pq ON push_queue(status, next_try)");

    $db->exec("CREATE TABLE IF NOT EXISTS push_log (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        order_no   TEXT    NOT NULL DEFAULT '',
        event      TEXT    NOT NULL DEFAULT '',
        channel    TEXT    NOT NULL DEFAULT '',
        http_code  INTEGER NOT NULL DEFAULT 0,
        response   TEXT    NOT NULL DEFAULT '',
        success    INTEGER NOT NULL DEFAULT 0,
        created_at TEXT    NOT NULL DEFAULT ''
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_pl ON push_log(created_at DESC)");

    $db->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        at         TEXT    NOT NULL DEFAULT '',
        ip         TEXT    NOT NULL DEFAULT '',
        ua         TEXT    NOT NULL DEFAULT '',
        user       TEXT    NOT NULL DEFAULT '',
        action     TEXT    NOT NULL DEFAULT '',
        target     TEXT    NOT NULL DEFAULT '',
        detail     TEXT    NOT NULL DEFAULT '',
        result     TEXT    NOT NULL DEFAULT ''
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_audit_at ON audit_log(at DESC)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_log(action)");
}

function shop_order_no_new(PDO $db) {
    for ($i = 0; $i < 6; $i++) {
        $no = 'ORD' . date('YmdHis') . strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 4));
        $st = $db->prepare('SELECT 1 FROM orders WHERE order_no = ? LIMIT 1');
        $st->execute([$no]);
        if (!$st->fetchColumn()) return $no;
        usleep(150000);
    }
    return 'ORD' . date('YmdHis') . strtoupper(bin2hex(random_bytes(3)));
}

function shop_pay_code_new(PDO $db, $len = 2) {
    $len = max(2, min(6, (int)$len));
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $max = strlen($alphabet) - 1;
    $since = date('Y-m-d H:i:s', time() - 86400);
    $code = '';
    for ($i = 0; $i < 300; $i++) {
        $code = '';
        for ($j = 0; $j < $len; $j++) {
            $code .= $alphabet[random_int(0, $max)];
        }
        $st = $db->prepare('SELECT COUNT(*) FROM orders WHERE pay_code = ? AND created_at >= ?');
        $st->execute([$code, $since]);
        if ((int)$st->fetchColumn() === 0) return $code;
    }
    return $code . random_int(0, 9);
}

function shop_items_text(array $items) {
    $parts = [];
    foreach ($items as $it) {
        $parts[] = ($it['name'] ?? '') . '×' . ($it['qty'] ?? 1);
    }
    return implode('、', $parts);
}

function shop_order_decode(array $row) {
    $items = json_decode($row['items_json'] ?? '[]', true);
    if (!is_array($items)) $items = [];
    $row['items']        = $items;
    $row['status_label'] = shop_status_label($row['status'] ?? '');
    $row['mode_label']   = ($row['mode'] ?? 'now') === 'booking' ? '提前预约' : '立即服务';
    $row['book_time']    = trim(($row['book_date'] ?? '') . ' ' . ($row['book_slot'] ?? ''));
    $row['items_text']   = shop_items_text($items);
    $row['pay_method']       = (string)($row['pay_method'] ?? '');
    $row['pay_method_label'] = shop_pay_method_label($row['pay_method']);
    return $row;
}

function shop_order_create(array $in) {
    $db = shop_db();
    if (!$db) return shop_db_unavailable_hint();

    $cfg = shop_config();

    $items  = [];
    $amount = 0.0;
    $stockPlan = [];
    foreach ((array)($in['items'] ?? []) as $line) {
        $pid = (string)($line['id'] ?? '');
        $qty = max(1, min(99, (int)($line['qty'] ?? 1)));
        $found = shop_find_product($pid);
        if (!$found) return '商品不存在或已下架';
        $p = $found['product'];
        if (($p['status'] ?? 'on') !== 'on') return '商品已下架：' . ($p['name'] ?? $pid);

        $stock = (int)($p['stock'] ?? -1);
        if ($stock >= 0) {
            $already = $stockPlan[$found['index']] ?? $stock;
            if ($already < $qty) return '库存不足：' . ($p['name'] ?? $pid);
            $stockPlan[$found['index']] = $already - $qty;
        }

        $price = round((float)($p['price'] ?? 0), 2);
        $items[] = [
            'id'    => $p['id'],
            'name'  => $p['name'] ?? '',
            'price' => $price,
            'qty'   => $qty,
            'unit'  => $p['unit'] ?? '次',
            'cat'   => $p['category'] ?? '',
        ];
        $amount += $price * $qty;
    }
    if (!$items) return '请至少选择一件商品';

    $bookDate = '';
    $bookSlot = '';
    if (($in['mode'] ?? 'now') === 'booking') {
        $bookDate = trim((string)($in['book_date'] ?? ''));
        $bookSlot = trim((string)($in['book_slot'] ?? ''));
        if ($bookDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookDate)) return '请选择预约日期';
        if ($bookSlot === '') return '请选择预约时段';
    }
    $mode = (($in['mode'] ?? 'now') === 'booking') ? 'booking' : 'now';

    $wechat  = mb_substr(trim((string)($in['wechat'] ?? '')), 0, 64);
    $payCode = ($cfg['pay']['code_mode'] === 'wechat')
        ? ($wechat !== '' ? $wechat : 'WX')
        : shop_pay_code_new($db, $cfg['pay']['code_length'] ?? 2);

    $now = date('Y-m-d H:i:s');

    try {
        $db->beginTransaction();
        $no = shop_order_no_new($db);
        $st = $db->prepare('INSERT INTO orders
            (order_no, pay_code, pay_method, mode, status, customer_name, wechat, phone, book_date, book_slot,
             remark, items_json, amount, client_ip, ua, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $st->execute([
            $no,
            $payCode,
            (($in['pay_method'] ?? '') === 'wechat' || ($in['pay_method'] ?? '') === 'alipay') ? $in['pay_method'] : '',
            $mode,
            'pending_payment',
            mb_substr(trim((string)($in['name'] ?? '')), 0, 60),
            $wechat,
            mb_substr(trim((string)($in['phone'] ?? '')), 0, 32),
            $bookDate,
            mb_substr($bookSlot, 0, 32),
            mb_substr(trim((string)($in['remark'] ?? '')), 0, 500),
            json_encode($items, JSON_UNESCAPED_UNICODE),
            round($amount, 2),
            mb_substr((string)($in['ip'] ?? ''), 0, 64),
            mb_substr((string)($in['ua'] ?? ''), 0, 255),
            $now,
            $now,
        ]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[shop] create order failed: ' . $e->getMessage());
        return '下单失败，请稍后重试';
    }

    if ($stockPlan) {
        try {
            $fresh = shop_config(true);
            foreach ($stockPlan as $idx => $left) {
                if (isset($fresh['products'][$idx])) {
                    $fresh['products'][$idx]['stock'] = $left;
                }
            }
            saveEncryptedData(SHOP_CONFIG_FILE, $fresh);
            shop_config(true);
        } catch (Throwable $e) {
            error_log('[shop] stock update failed: ' . $e->getMessage());
        }
    }

    return shop_order_get($no);
}

function shop_order_get($orderNo) {
    $db = shop_db();
    if (!$db) return null;
    $st = $db->prepare('SELECT * FROM orders WHERE order_no = ? LIMIT 1');
    $st->execute([$orderNo]);
    $row = $st->fetch();
    return $row ? shop_order_decode($row) : null;
}

function shop_order_check_owner(array $order, $secret) {
    $secret = trim((string)$secret);
    if ($secret === '') return false;

    if (($order['wechat'] ?? '') !== ''
        && mb_strtolower(trim($order['wechat'])) === mb_strtolower($secret)) {
        return true;
    }

    $phone = preg_replace('/\D+/', '', (string)($order['phone'] ?? ''));
    $s     = preg_replace('/\D+/', '', $secret);
    if ($phone !== '' && $s !== '') {
        if ($s === $phone) return true;
        if (strlen($s) >= 4 && substr($phone, -4) === substr($s, -4)) return true;
    }
    return false;
}

function shop_mask_phone($phone) {
    $d = preg_replace('/\D+/', '', (string)$phone);
    if (strlen($d) < 7) return (string)$phone;
    return substr($d, 0, 3) . '****' . substr($d, -4);
}

function shop_order_max_updated_at() {
    $db = shop_db();
    if (!$db) return '';
    try { return (string)$db->query("SELECT COALESCE(MAX(updated_at), '') FROM orders")->fetchColumn(); }
    catch (Throwable $e) { return ''; }
}

// 把一行订单打包成前端要的通知结构
function shop_order_watch_item(array $o, $event) {
    return [
        'event'        => (string)$event,
        'order_no'     => (string)$o['order_no'],
        'name'         => (string)$o['customer_name'],
        'wechat'       => (string)$o['wechat'],
        'phone'        => (string)$o['phone'],
        'items_text'   => (string)$o['items_text'],
        'amount'       => shop_price($o['amount']),
        'status'       => (string)$o['status'],
        'status_label' => (string)$o['status_label'],
        'pay_method_label' => (string)($o['pay_method_label'] ?? ''),
        'created_at'   => (string)$o['created_at'],
        'updated_at'   => (string)$o['updated_at'],
    ];
}

/**
 * 订单监控：返回「需要提醒管理员」的订单，分两类 ——
 *   ① 新订单（id > sinceId）                          → event = new
 *   ② 客户点了「我已付款」（状态变 awaiting_verify）    → event = awaiting_verify
 *
 * ②用 updated_at 水位判断，并用 id <= sinceId 排除新订单，避免重复提醒。
 * 只关心客户发起的动作；管理员自己在后台改状态不会提醒（那没必要）。
 */
function shop_order_watch($sinceId, $sinceTime = '', $limit = 20) {
    $db = shop_db();
    $sinceId   = max(0, (int)$sinceId);
    $sinceTime = trim((string)$sinceTime);
    $limit     = max(1, min(50, (int)$limit));
    $out = ['latest' => $sinceId, 'latest_time' => $sinceTime, 'orders' => []];
    if (!$db) return $out;

    $rows = [];
    try {
        $st = $db->prepare("SELECT * FROM orders WHERE id > ? ORDER BY id ASC LIMIT $limit");
        $st->execute([$sinceId]);
        $rows = $st->fetchAll();
    } catch (Throwable $e) { $rows = []; }

    $changed = [];
    if ($sinceTime !== '') {
        try {
            $st2 = $db->prepare("SELECT * FROM orders WHERE id <= ? AND updated_at > ? AND status = 'awaiting_verify' ORDER BY updated_at ASC LIMIT $limit");
            $st2->execute([$sinceId, $sinceTime]);
            $changed = $st2->fetchAll();
        } catch (Throwable $e) { $changed = []; }
    }

    $maxTime = $sinceTime;
    foreach (array_merge($rows, $changed) as $r) {
        $id = (int)($r['id'] ?? 0);
        if ($id > $out['latest']) $out['latest'] = $id;
        $tm = (string)($r['updated_at'] ?? '');
        if ($tm > $maxTime) $maxTime = $tm;
    }
    $out['latest_time'] = $maxTime;

    foreach ($rows as $r) {
        $o = shop_order_decode($r);
        if (($o['status'] ?? '') === 'expired') continue;
        $out['orders'][] = shop_order_watch_item($o, 'new');
    }
    foreach ($changed as $r) {
        $o = shop_order_decode($r);
        $out['orders'][] = shop_order_watch_item($o, 'awaiting_verify');
    }
    return $out;
}
function shop_order_max_id() {
    $db = shop_db();
    if (!$db) return 0;
    try { return (int)$db->query("SELECT COALESCE(MAX(id), 0) FROM orders")->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

function shop_order_new_since($sinceId, $limit = 20) {
    $db = shop_db();
    $sinceId = max(0, (int)$sinceId);
    $limit   = max(1, min(50, (int)$limit));
    if (!$db) return ['latest' => $sinceId, 'orders' => []];

    try {
        $st = $db->prepare("SELECT * FROM orders WHERE id > ? ORDER BY id ASC LIMIT $limit");
        $st->execute([$sinceId]);
        $rows = $st->fetchAll();
    } catch (Throwable $e) {
        return ['latest' => $sinceId, 'orders' => []];
    }

    $out    = [];
    $latest = $sinceId;
    foreach ($rows as $r) {
        $id = (int)($r['id'] ?? 0);
        if ($id > $latest) $latest = $id;
        $o = shop_order_decode($r);
        if (($o['status'] ?? '') === 'expired') continue;
        $out[] = [
            'order_no'     => (string)$o['order_no'],
            'name'         => (string)$o['customer_name'],
            'wechat'       => (string)$o['wechat'],
            'phone'        => (string)$o['phone'],
            'items_text'   => (string)$o['items_text'],
            'amount'       => shop_price($o['amount']),
            'status'       => (string)$o['status'],
            'status_label' => (string)$o['status_label'],
            'pay_method_label' => (string)($o['pay_method_label'] ?? ''),
            'created_at'   => (string)$o['created_at'],
        ];
    }
    return ['latest' => $latest, 'orders' => $out];
}
function shop_order_search_by_contact($contact, $limit = 30) {
    $db = shop_db();
    if (!$db) return [];

    $contact = trim((string)$contact);
    if ($contact === '') return [];

    $digits = preg_replace('/\D+/', '', $contact);
    $limit  = max(1, min(100, (int)$limit));

    $coarse = [];
    $args   = [];
    if (strlen($digits) >= 6) {
        $coarse[] = "REPLACE(REPLACE(REPLACE(REPLACE(phone,'-',''),' ',''),'+',''),'　','') LIKE ?";
        $args[]   = '%' . $digits . '%';
    }
    $coarse[] = 'wechat LIKE ?';
    $args[]   = '%' . $contact . '%';

    try {
        $st = $db->prepare(
            'SELECT * FROM orders WHERE (' . implode(' OR ', $coarse) . ')'
            . " ORDER BY CASE WHEN status IN ('pending_payment','payment_failed') THEN 0 ELSE 1 END, id DESC LIMIT " . ($limit * 5)
        );
        $st->execute($args);
        $rows = $st->fetchAll();
    } catch (Throwable $e) {
        error_log('[shop] contact search failed: ' . $e->getMessage());
        return [];
    }

    $contactLower = mb_strtolower($contact);
    $out = [];
    foreach ($rows as $row) {
        $o = shop_order_decode($row);
        $hit = false;
        if (strlen($digits) >= 6) {
            $p = preg_replace('/\D+/', '', (string)$o['phone']);
            if ($p !== '' && $p === $digits) $hit = true;
        }
        if (!$hit && (string)$o['wechat'] !== '' && mb_strtolower(trim((string)$o['wechat'])) === $contactLower) {
            $hit = true;
        }
        if ($hit) {
            $out[] = $o;
            if (count($out) >= $limit) break;
        }
    }
    return $out;
}

function shop_order_count_recent_pending($ip, $windowSec = 86400) {
    $ip = trim((string)$ip);
    if ($ip === '') return 0;
    $db = shop_db();
    if (!$db) return 0;
    try {
        $st = $db->prepare("SELECT COUNT(*) FROM orders
                            WHERE client_ip = ? AND created_at >= ?
                              AND status IN ('pending_payment', 'payment_failed')");
        $st->execute([$ip, date('Y-m-d H:i:s', time() - max(60, (int)$windowSec))]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function shop_order_expire_unpaid($minutes = null) {
    if ($minutes === null) {
        $cfg = shop_config();
        $minutes = (int)($cfg['pay']['unpaid_expire_minutes'] ?? 10);
    }
    $minutes = (int)$minutes;
    if ($minutes <= 0) return 0;

    $db = shop_db();
    if (!$db) return 0;

    $now = time();

    try {
        $before = date('Y-m-d H:i:s', $now - $minutes * 60);

        $st = $db->prepare("UPDATE orders SET status = 'expired', updated_at = ?
                            WHERE status IN ('pending_payment', 'payment_failed')
                              AND created_at < ?
                              AND pay_marked_at = ''");
        $st->execute([date('Y-m-d H:i:s'), $before]);
        $n = (int)$st->rowCount();
        if ($n > 0) {
            error_log('[shop] ' . $n . ' 笔超时未付款订单已标记为「已失效」（超过 ' . $minutes . ' 分钟）');
        }
        $keepDays = (int)(shop_config()['pay']['expired_keep_days'] ?? 7);
        if ($keepDays > 0) shop_order_purge_expired($keepDays);
        return $n;
    } catch (Throwable $e) {
        error_log('[shop] 清理超时订单失败：' . $e->getMessage());
        return 0;
    }
}

function shop_rl_dir_path() {
    static $dir = null;
    if ($dir !== null) return $dir;
    $cands = [
        SHOP_DATA_DIR,
        dirname(SHOP_DATA_DIR) . '/tmp',
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

function shop_rl_bucket_label($b) {
    static $m = [
        'create'        => '下单',
        'create_global' => '下单（总闸）',
        'mark_paid'     => '标记已付款',
        'query'         => '订单查询（接口）',
        'regen_code'    => '换备注码',
        'querypage'     => '订单查询页',
        'create-s'      => '下单间隔',
    ];
    if (isset($m[$b])) return $m[$b];
    if (substr($b, -2) === '-s') return '提交间隔（' . substr($b, 0, -2) . '）';
    return $b === '' ? '未知' : $b;
}

function shop_rl_purge($graceSec = 3600) {
    $dir = shop_rl_dir_path();
    if ($dir === '') return 0;
    $graceSec = max(600, (int)$graceSec);
    $now = time();
    $n = 0;

    foreach (['shop_rl_*', 'shop_ts_*'] as $pat) {
        foreach (glob($dir . '/' . $pat) ?: [] as $file) {
            if (!is_file($file)) continue;
            $dead = false;
            $raw  = (string)@file_get_contents($file);

            if ($raw === '') {
                $dead = ($now - (int)@filemtime($file)) > $graceSec;
            } else {
                $j = json_decode($raw, true);
                if (is_array($j) && isset($j['t'])) {
                    $win  = (int)($j['w'] ?? 0);
                    $life = $win > 0 ? max($win, $graceSec) : $graceSec;
                    $dead = ($now - (int)$j['t']) > $life;
                } elseif (is_array($j)) {
                    $dead = ($now - (int)@filemtime($file)) > $graceSec;
                } else {

                    $ts = (int)$raw;
                    $dead = $ts > 0 ? (($now - $ts) > $graceSec) : (($now - (int)@filemtime($file)) > $graceSec);
                }
            }

            if ($dead) { @unlink($file); $n++; }
        }
    }
    return $n;
}

function shop_rl_scan() {
    $dir = shop_rl_dir_path();
    if ($dir === '') return [];
    $now = time();
    $out = [];
    foreach (['shop_rl_*', 'shop_ts_*'] as $pat) {
        foreach (glob($dir . '/' . $pat) ?: [] as $file) {
            if (!is_file($file)) continue;
            $raw = (string)@file_get_contents($file);
            if ($raw === '') { @unlink($file); continue; }
            $j = json_decode($raw, true);
            if (!is_array($j)) {

                $ts = (int)$raw;
                if ($ts <= 0 || ($now - $ts) > 3600) { @unlink($file); continue; }
                $out[] = ['ip' => '(旧格式，未记录)', 'bucket' => '提交间隔', 'count' => 0,
                          'limit' => 0, 'left' => max(0, 10 - ($now - $ts)),
                          'age' => $now - $ts, 'file' => basename($file)];
                continue;
            }
            $ts     = (int)($j['t'] ?? 0);
            $window = (int)($j['w'] ?? 600);
            $age    = $now - $ts;
            if ($ts <= 0 || $age > max($window, 3600)) { @unlink($file); continue; }
            $out[] = [
                'ip'     => (string)($j['ip'] ?? ''),
                'bucket' => shop_rl_bucket_label((string)($j['b'] ?? '')),
                'count'  => (int)($j['c'] ?? 0),
                'limit'  => (int)($j['max'] ?? 0),
                'left'   => max(0, $window - $age),
                'age'    => $age,
                'file'   => basename($file),
            ];
        }
    }

    usort($out, function ($a, $b) {
        if ($a['left'] === $b['left']) return strcmp($a['ip'], $b['ip']);
        return $a['left'] <=> $b['left'];
    });
    return $out;
}

function shop_rl_release_file($file) {
    $dir = shop_rl_dir_path();
    if ($dir === '') return false;
    $file = basename((string)$file);
    if ($file === '' || strpos($file, 'shop_rl_') !== 0 && strpos($file, 'shop_ts_') !== 0) return false;
    $p = $dir . '/' . $file;
    return is_file($p) ? @unlink($p) : false;
}

function shop_rl_release_ip($ip) {
    $ip = trim((string)$ip);
    if ($ip === '') return 0;
    $n = 0;
    foreach (shop_rl_scan() as $r) {
        if ($r['ip'] === $ip && shop_rl_release_file($r['file'])) $n++;
    }
    return $n;
}

function shop_rl_release_all() {
    $n = 0;
    foreach (shop_rl_scan() as $r) {
        if (shop_rl_release_file($r['file'])) $n++;
    }
    return $n;
}

function shop_order_delete_pending_by_ip($ip) {
    $ip = trim((string)$ip);
    if ($ip === '') return 0;
    $db = shop_db();
    if (!$db) return 0;
    try {
        $st = $db->prepare("DELETE FROM orders
                            WHERE client_ip = ?
                              AND status IN ('pending_payment','payment_failed')
                              AND pay_marked_at = ''");
        $st->execute([$ip]);
        return (int)$st->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}

function shop_pending_by_ip($windowSec = 86400, $minCount = 1) {
    $db = shop_db();
    if (!$db) return [];

    $minCount = max(1, (int)$minCount);
    try {
        $st = $db->prepare("SELECT client_ip AS ip, COUNT(*) AS c, MIN(created_at) AS first_at
                            FROM orders
                            WHERE status IN ('pending_payment','payment_failed')
                              AND created_at >= ?
                            GROUP BY client_ip
                            HAVING COUNT(*) >= $minCount
                            ORDER BY c DESC, first_at ASC
                            LIMIT 200");
        $st->execute([date('Y-m-d H:i:s', time() - max(60, (int)$windowSec))]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function shop_order_purge_expired($days = 7) {
    $days = (int)$days;
    if ($days <= 0) return 0;
    $db = shop_db();
    if (!$db) return 0;
    try {
        $st = $db->prepare("DELETE FROM orders WHERE status = 'expired' AND created_at < ?");
        $st->execute([date('Y-m-d H:i:s', time() - $days * 86400)]);
        $n = (int)$st->rowCount();
        if ($n > 0) error_log('[shop] 彻底删除 ' . $n . ' 笔已失效订单（保留 ' . $days . ' 天）');
        return $n;
    } catch (Throwable $e) { return 0; }
}

function shop_expired_count() {
    $db = shop_db();
    if (!$db) return 0;
    try { return (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'expired'")->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

function shop_order_list($opts = []) {

    shop_order_expire_unpaid();
    $db = shop_db();
    if (!$db) {
        return ['total' => 0, 'rows' => [], 'page' => 1, 'pages' => 0, 'per' => 20, 'db_error' => shop_db_unavailable_hint()];
    }

    $status = (string)($opts['status'] ?? '');
    $q      = trim((string)($opts['q'] ?? ''));
    $page   = max(1, (int)($opts['page'] ?? 1));
    $per    = max(1, min(200, (int)($opts['per'] ?? 20)));

    $where = [];
    $args  = [];
    if ($status !== '' && $status !== 'all' && shop_status_valid($status)) {
        $where[] = 'status = ?';
        $args[]  = $status;
    } elseif ($status === '' || $status === 'all') {
        $where[] = "status <> 'expired'";
    }
    if ($q !== '') {
        $where[] = '(order_no LIKE ? OR customer_name LIKE ? OR wechat LIKE ? OR phone LIKE ? OR pay_code LIKE ? OR items_json LIKE ? OR remark LIKE ?)';
        $like = '%' . $q . '%';
        array_push($args, $like, $like, $like, $like, $like, $like, $like);
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $st = $db->prepare("SELECT COUNT(*) FROM orders $whereSql");
    $st->execute($args);
    $total = (int)$st->fetchColumn();

    $pages = $per > 0 ? (int)ceil($total / $per) : 0;
    if ($pages > 0 && $page > $pages) $page = $pages;

    $offset = ($page - 1) * $per;
    $st = $db->prepare("SELECT * FROM orders $whereSql ORDER BY id DESC LIMIT $per OFFSET $offset");
    $st->execute($args);

    $rows = [];
    foreach ($st->fetchAll() as $r) $rows[] = shop_order_decode($r);

    return ['total' => $total, 'rows' => $rows, 'page' => $page, 'pages' => $pages, 'per' => $per];
}

function shop_order_all($status = '', $q = '') {
    $db = shop_db();
    if (!$db) return [];
    $res = shop_order_list(['status' => $status, 'q' => $q, 'page' => 1, 'per' => 200]);
    $rows = $res['rows'];
    if ($res['total'] > 200) {
        $res = shop_order_list(['status' => $status, 'q' => $q, 'page' => 1, 'per' => 200]);

    }
    return $rows;
}

function shop_order_set_status($orderNo, $newStatus, array $extra = []) {
    $db = shop_db();
    if (!$db) return shop_db_unavailable_hint();
    if (!shop_status_valid($newStatus)) return '非法的订单状态';

    $order = shop_order_get($orderNo);
    if (!$order) return '订单不存在';

    $now  = date('Y-m-d H:i:s');
    $sets = ['status = ?', 'updated_at = ?'];
    $args = [$newStatus, $now];

    if (array_key_exists('admin_note', $extra)) {
        $sets[] = 'admin_note = ?';
        $args[] = mb_substr((string)$extra['admin_note'], 0, 500);
    }
    if (array_key_exists('verified_by', $extra)) {
        $sets[] = 'verified_by = ?';
        $args[] = mb_substr((string)$extra['verified_by'], 0, 64);
    }

    if ($newStatus === 'awaiting_verify' && empty($order['pay_marked_at'])) {
        $sets[] = 'pay_marked_at = ?';
        $args[] = $now;
    }

    if ($newStatus === 'paid' && empty($order['verified_at'])) {
        $sets[] = 'verified_at = ?';
        $args[] = $now;
    }

    $args[] = $orderNo;
    $st = $db->prepare('UPDATE orders SET ' . implode(', ', $sets) . ' WHERE order_no = ?');
    $st->execute($args);
    return true;
}

function shop_order_delete($orderNo) {
    $db = shop_db();
    if (!$db) return false;
    $st = $db->prepare('DELETE FROM orders WHERE order_no = ?');
    $st->execute([$orderNo]);
    return $st->rowCount() > 0;
}

function shop_order_delete_many(array $orderNos) {
    $db = shop_db();
    if (!$db) return 0;
    $n  = 0;
    $st = $db->prepare('DELETE FROM orders WHERE order_no = ?');
    foreach ($orderNos as $no) {
        $st->execute([$no]);
        $n += $st->rowCount();
    }
    return $n;
}

function shop_order_stats() {
    $out = [
        'total' => 0, 'today' => 0, 'awaiting_verify' => 0, 'pending_payment' => 0,
        'paid' => 0, 'completed' => 0, 'income' => 0.0, 'by_status' => [],
    ];
    $db = shop_db();
    if (!$db) return $out;

    foreach ($db->query('SELECT status, COUNT(*) AS c FROM orders GROUP BY status')->fetchAll() as $r) {
        $out['by_status'][$r['status']] = (int)$r['c'];
        if ($r['status'] !== 'expired') $out['total'] += (int)$r['c'];
    }
    $out['awaiting_verify'] = $out['by_status']['awaiting_verify'] ?? 0;
    $out['pending_payment'] = $out['by_status']['pending_payment'] ?? 0;
    $out['paid']            = $out['by_status']['paid'] ?? 0;
    $out['completed']       = $out['by_status']['completed'] ?? 0;

    $st = $db->prepare('SELECT COUNT(*) FROM orders WHERE created_at >= ?');
    $st->execute([date('Y-m-d 00:00:00')]);
    $out['today'] = (int)$st->fetchColumn();

    $out['income'] = (float)$db->query(
        "SELECT COALESCE(SUM(amount),0) FROM orders WHERE status IN ('paid','processing','completed')"
    )->fetchColumn();

    return $out;
}

function shop_order_vars(array $order, $event = 'created') {
    $cfg = shop_config();
    $eventMap = [
        'created'  => '客户新下单',
        'paid'     => '客户已标记付款',
        'verified' => '管理员已确认付款',
        'test'     => '测试推送',
    ];
    $bt = trim((string)($order['book_time'] ?? ''));
    $rm = trim((string)($order['remark'] ?? ''));
    return [
        'event'       => $event,
        'event_label' => $eventMap[$event] ?? $event,
        'order_no'    => (string)($order['order_no'] ?? ''),
        'pay_code'    => (string)($order['pay_code'] ?? ''),
        'items'       => (string)($order['items_text'] ?? ''),
        'amount'      => shop_price($order['amount'] ?? 0),
        'name'        => (string)($order['customer_name'] ?? ''),
        'wechat'      => (string)($order['wechat'] ?? ''),
        'phone'       => (string)($order['phone'] ?? ''),
        'mode'        => (string)($order['mode_label'] ?? ''),
        'book_time'   => $bt !== '' ? $bt : '—',
        'remark'      => $rm !== '' ? $rm : '—',
        'status'      => (string)($order['status_label'] ?? ''),
        'created_at'  => (string)($order['created_at'] ?? ''),
        'site'        => (string)($cfg['page_title'] ?? '商城下单'),
    ];
}

function shop_audit_actions() {
    return [
        'shop_admin_login'   => '登录后台',
        'shop_admin_logout'  => '退出登录',
        'shop_admin_passwd'  => '修改后台密码',
        'shop_admin_account' => '修改后台账号/密码',
        'save_shop_page'     => '修改页面文案',
        'save_shop_pay'      => '修改收款与表单',
        'save_shop_push'     => '修改推送设置',
        'save_shop_product'  => '保存商品',
        'save_shop_category' => '保存分类',
        'shop_order_bulk'    => '批量操作订单',
        'shop_order_set'     => '变更订单状态',
        'shop_push_test'     => '测试推送通道',
        'shop_push_process'  => '处理推送重试队列',
        'del_shop_order'     => '删除订单',
        'del_shop_product'   => '删除商品',
        'del_shop_category'  => '删除分类',
        'move_shop_product'  => '调整商品顺序',
        'export_csv'         => '导出订单 CSV',
        'shop_audit_clear'   => '清理审计日志',
        'rl_release'         => '解除限流记录',
        'rl_release_ip'      => '解除该 IP 限流',
        'rl_release_all'     => '清空限流记录',
        'purge_pending'      => '清理未付款订单',
        'purge_ip'           => '删除该 IP 未付款单',
        'purge_expired'      => '彻底删除已失效订单',
    ];
}

function shop_audit_label($action) {
    $m = shop_audit_actions();
    return $m[$action] ?? (string)$action;
}

function shop_audit_add($action, $target = '', $detail = '', $result = '') {
    $db = shop_db();
    if (!$db) return 0;
    try {
        $st = $db->prepare('INSERT INTO audit_log (at, ip, ua, user, action, target, detail, result)
                            VALUES (?,?,?,?,?,?,?,?)');
        $st->execute([
            date('Y-m-d H:i:s'),
            mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
            mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            mb_substr((string)($_SESSION['shop_admin'] ?? ''), 0, 64),
            mb_substr((string)$action, 0, 64),
            mb_substr((string)$target, 0, 128),
            mb_substr((string)$detail, 0, 500),
            mb_substr((string)$result, 0, 255),
        ]);
        return (int)$db->lastInsertId();
    } catch (Throwable $e) {
        error_log('[shop] audit add failed: ' . $e->getMessage());
        return 0;
    }
}

function shop_audit_set_result($id, $result) {
    $id = (int)$id;
    if ($id <= 0) return;
    $db = shop_db();
    if (!$db) return;
    try {
        $st = $db->prepare('UPDATE audit_log SET result = ? WHERE id = ?');
        $st->execute([mb_substr((string)$result, 0, 255), $id]);
    } catch (Throwable $e) {}
}

function shop_audit_list($opts = []) {
    $db = shop_db();
    if (!$db) return ['total' => 0, 'rows' => [], 'page' => 1, 'pages' => 0, 'per' => 30];
    $action = trim((string)($opts['action'] ?? ''));
    $q      = trim((string)($opts['q'] ?? ''));
    $page   = max(1, (int)($opts['page'] ?? 1));
    $per    = max(1, min(200, (int)($opts['per'] ?? 30)));
    $where = []; $args = [];
    if ($action !== '') { $where[] = 'action = ?'; $args[] = $action; }
    if ($q !== '') {
        $where[] = '(target LIKE ? OR detail LIKE ? OR result LIKE ? OR ip LIKE ? OR user LIKE ?)';
        $like = '%' . $q . '%';
        array_push($args, $like, $like, $like, $like, $like);
    }
    $ws = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    try {
        $st = $db->prepare("SELECT COUNT(*) FROM audit_log $ws");
        $st->execute($args);
        $total = (int)$st->fetchColumn();
        $pages = $per > 0 ? (int)ceil($total / $per) : 0;
        if ($pages > 0 && $page > $pages) $page = $pages;
        $off = ($page - 1) * $per;
        $st = $db->prepare("SELECT * FROM audit_log $ws ORDER BY id DESC LIMIT $per OFFSET $off");
        $st->execute($args);
        return ['total' => $total, 'rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'page' => $page, 'pages' => $pages, 'per' => $per];
    } catch (Throwable $e) {
        return ['total' => 0, 'rows' => [], 'page' => 1, 'pages' => 0, 'per' => $per];
    }
}

function shop_audit_counts() {
    $db = shop_db();
    if (!$db) return [];
    try {
        $out = [];
        foreach ($db->query('SELECT action, COUNT(*) c FROM audit_log GROUP BY action ORDER BY c DESC')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(string)$r['action']] = (int)$r['c'];
        }
        return $out;
    } catch (Throwable $e) { return []; }
}

function shop_audit_clear($days = 30) {
    $db = shop_db();
    if (!$db) return 0;
    try {
        if ((int)$days <= 0) {
            $n = (int)$db->exec('DELETE FROM audit_log');
        } else {
            $st = $db->prepare('DELETE FROM audit_log WHERE at < ?');
            $st->execute([date('Y-m-d H:i:s', time() - (int)$days * 86400)]);
            $n = (int)$st->rowCount();
        }
        return $n;
    } catch (Throwable $e) { return 0; }
}
