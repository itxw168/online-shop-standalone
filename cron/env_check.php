<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("403 Forbidden —— 本脚本仅允许命令行运行：php cron/env_check.php\n");
}

define('IN_CRYPT', true);
define('IN_SHOP', true);
require_once __DIR__ . '/../inc/crypt.php';
require_once __DIR__ . '/../inc/shop_store.php';
require_once __DIR__ . '/../inc/push.php';

$ROOT = dirname(__DIR__);
$pass = 0; $fail = 0; $warn = 0; $todo = [];

function line($mark, $label, $detail = '') {
    global $pass, $fail, $warn;
    if ($mark === 'PASS') $pass++;
    elseif ($mark === 'FAIL') $fail++;
    else $warn++;
    $icon = ['PASS' => '✓', 'FAIL' => '✗', 'WARN' => '!', 'INFO' => '·'][$mark] ?? '·';
    printf("  [%s] %-46s %s\n", $icon, $label, $detail);
}
function head($t) { echo "\n" . str_repeat('=', 74) . "\n" . $t . "\n" . str_repeat('=', 74) . "\n"; }

echo "\n商城下单模块 - 部署环境自检\n";
echo "站点目录：" . $ROOT . "\n";
echo "检查时间：" . date('Y-m-d H:i:s') . "\n";

head('1. PHP 运行环境');
$ver = PHP_VERSION;
if (version_compare($ver, '8.0.0', '>=')) {
    line('PASS', 'PHP 版本', $ver . '（推荐）');
} elseif (version_compare($ver, '7.4.0', '>=')) {
    line('WARN', 'PHP 版本', $ver . ' —— 可用，但建议升级到 8.x');
} else {
    line('FAIL', 'PHP 版本', $ver . ' —— 过低，需要 PHP 7.4+');
}

head('2. PHP 扩展依赖');
$exts = [
    'pdo_sqlite' => ['必须', '订单库（data/shop.db）依赖它；缺了商城整个不可用'],
    'openssl'    => ['必须', 'AES 加解密（商城配置与全站数据都依赖）'],
    'mbstring'   => ['必须', '中文长度/截断处理'],
    'json'       => ['必须', '配置与接口数据格式'],
];
foreach ($exts as $e => $info) {
    if (extension_loaded($e)) {
        line('PASS', "扩展 $e", '已启用（' . $info[0] . '）');
    } else {
        line('FAIL', "扩展 $e", '未启用 —— ' . $info[1]);
        $todo[] = "在 1Panel →「网站 → PHP 运行环境 → 扩展」中勾选安装 {$e}，然后重启 PHP-FPM";
    }
}
foreach (['curl' => '推送 HTTP 请求（ntfy / 企业微信等），缺失会退化为 stream 方式',
          'stream_socket_client' => '邮件 SMTP 推送依赖它',
          'session' => '后台登录态'] as $k => $why) {
    $ok = ($k === 'stream_socket_client') ? function_exists($k) : extension_loaded($k);
    line($ok ? 'PASS' : 'WARN', ($k === 'stream_socket_client' ? '函数 ' : '扩展 ') . $k, $ok ? '可用' : ('不可用 —— ' . $why));
    if (!$ok && $k === 'curl') $todo[] = '建议启用 curl 扩展（推送更可靠），1Panel → PHP → 扩展';
}

if (extension_loaded('pdo_sqlite')) {
    $drivers = PDO::getAvailableDrivers();
    line(in_array('sqlite', $drivers, true) ? 'PASS' : 'FAIL', 'PDO sqlite 驱动',
        in_array('sqlite', $drivers, true) ? '可用' : ('不可用，当前驱动：' . implode(',', $drivers)));
}

head('3. 加密密钥');
if (is_file($ROOT . '/secret/config.secret.php')) {
    line('PASS', 'secret/config.secret.php', '存在');
    if (function_exists('validateKey') && validateKey()) {
        line('PASS', 'AES_KEY / AES_IV 格式', '合法（32 / 16 字节）');
    } else {
        line('FAIL', 'AES_KEY / AES_IV 格式', '不合法，加解密会失败');
        $todo[] = '检查 secret/config.secret.php 里的 AES_KEY（64 位 hex）与 AES_IV（32 位 hex）';
    }

    $probe = ['t' => '中文测试', 'n' => 12345];
    $round = json_decrypt(json_encrypt($probe));
    line(($round == $probe) ? 'PASS' : 'FAIL', '加解密往返自检', ($round == $probe) ? '正常' : '异常！密钥可能有问题');
} else {
    line('FAIL', 'secret/config.secret.php', '不存在');
    $todo[] = '上传 secret/config.secret.php（与现有 data/*.json 配套的那个，切勿更换）';
}

head('4. 目录写权限（Web 进程身份：' . (function_exists('posix_getpwuid') && function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown') : '当前用户') . '）');

foreach (['data' => '商城配置与订单库'] as $d => $why) {
    $dir = $ROOT . '/' . $d;
    if (!is_dir($dir)) {
        line('FAIL', "$d/ 目录", '不存在');
        $todo[] = "创建 $d/ 目录并设为 Web 进程可写";
        continue;
    }
    $probe = $dir . '/.env_check_' . bin2hex(random_bytes(4));
    $ok = @file_put_contents($probe, 'x') !== false;
    if ($ok) @unlink($probe);
    line($ok ? 'PASS' : 'FAIL', "$d/ 可写", $ok ? "正常（{$why}）" : "不可写（{$why}）");
    if (!$ok) $todo[] = "把 $d/ 权限改为 755 且属主为 Web 运行用户（1Panel 里 www 或容器用户）";
}

head('5. 商城模块文件完整性');
$need = [

    'index.php'            => '前台商品展示与下单页',
    'query.php'            => '前台订单查询页',
    'api.php'              => '前台接口',
    'admin.php'            => '管理后台（自带登录）',
    'inc/admin_view.php'   => '后台管理界面',
    'inc/crypt.php'        => '加密解密',
    'inc/shop_store.php'   => '订单仓储层',
    'inc/push.php'         => '消息推送核心',
    'secret/config.secret.php' => '本站加密密钥（独立目录 + 应 403）',
    'cron/push_retry.php'  => '推送重试任务',
];
foreach ($need as $f => $why) {
    $p = $ROOT . '/' . $f;
    line(is_file($p) ? 'PASS' : 'FAIL', $f, is_file($p) ? ('已上传 ' . number_format(filesize($p)) . ' 字节') : ('缺失 —— ' . $why));
    if (!is_file($p)) $todo[] = "上传 $f";
}

$adm = $ROOT . '/admin.php';
if (is_file($adm)) {
    $src = (string)@file_get_contents($adm);
    $hooks = [
        'shop_admin_login'    => '独立登录',
        'save_shop_page'      => '页面文案处理器',
        'save_shop_product'   => '商品处理器',
        'save_shop_category'  => '分类处理器',
        'save_shop_pay'       => '收款设置处理器',
        'save_shop_push'      => '推送设置处理器',
        'inc/admin_view.php'  => '后台界面挂载',
    ];
    $miss = [];
    foreach ($hooks as $needle => $why) { if (strpos($src, $needle) === false) $miss[] = $why; }
    line($miss ? 'FAIL' : 'PASS', '独立后台就绪', $miss ? ('缺少：' . implode('、', $miss)) : '登录 / 六个子页 / 写操作全部挂载');
    if ($miss) $todo[] = '重新上传完整版 admin.php';
} else {
    line('FAIL', 'admin.php', '不存在 —— 后台上传后才能管理商品与订单');
}

head('6. 订单库读写演练（真实建表 → 写入 → 读取 → 删除）');
if (!shop_db_available()) {
    line('FAIL', 'SQLite 初始化', '跳过（pdo_sqlite 未启用）');
} else {
    $db = shop_db();
    if (!$db instanceof PDO) {
        line('FAIL', 'SQLite 初始化', '失败，请看 PHP 错误日志');
        $todo[] = '查看 Nginx/PHP 错误日志中 [shop] 开头的记录';
    } else {
        line('PASS', 'SQLite 初始化', '成功');
        $tables = [];
        foreach ($db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll() as $r) $tables[] = $r['name'];
        foreach (['orders', 'push_queue', 'push_log'] as $t) {
            line(in_array($t, $tables, true) ? 'PASS' : 'FAIL', "数据表 $t", in_array($t, $tables, true) ? '已就绪' : '缺失');
        }
        line('INFO', 'WAL 模式', strtolower((string)$db->query('PRAGMA journal_mode')->fetchColumn()));
        line('INFO', '写锁等待(ms)', (string)$db->query('PRAGMA busy_timeout')->fetchColumn());

        $no = shop_order_no_new($db);
        line(preg_match('/^ORD\d{14}[A-Z0-9]{4}$/', $no) ? 'PASS' : 'FAIL', '订单号生成', $no);
        $code = shop_pay_code_new($db, 2);
        line(preg_match('/^[A-Z]{2,3}$/', $code) ? 'PASS' : 'FAIL', '付款备注码生成', $code);

        $probeNo = 'ORD' . date('YmdHis') . 'CHK0';
        try {
            $st = $db->prepare('INSERT INTO orders (order_no, pay_code, mode, status, customer_name, wechat, phone, remark, items_json, amount, client_ip, ua, created_at, updated_at)
                                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $st->execute([$probeNo, 'ZZ', 'now', 'pending_payment', '环境自检', 'env_check', '00000000000',
                          'deploy check', '[{"id":"probe","name":"自检","price":0,"qty":1,"unit":"次","cat":""}]',
                          0, '127.0.0.1', 'env_check', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
            $row = shop_order_get($probeNo);
            line(($row && $row['order_no'] === $probeNo) ? 'PASS' : 'FAIL', '写入并读回订单', $row ? '成功' : '读回失败');
            $del = shop_order_delete($probeNo);
            line(($del && shop_order_get($probeNo) === null) ? 'PASS' : 'FAIL', '删除测试订单并清理干净', $del ? '成功' : '未删除，请手工清理 ' . $probeNo);
        } catch (Throwable $e) {
            line('FAIL', '写入并读回订单', $e->getMessage());
            $todo[] = '数据库写入失败，检查 data/ 权限与磁盘空间';
        }
    }
}

head('7. 业务配置完成度（不影响启动，但影响能不能收到钱/通知）');
$cfg = shop_config(true);
line(is_file(SHOP_CONFIG_FILE) ? 'PASS' : 'WARN', 'data/shop.json 配置文件',
    is_file(SHOP_CONFIG_FILE) ? '已存在' : '尚未生成（进后台「商城下单」保存一次即可）');
line(is_file(SHOP_DB_FILE) ? 'PASS' : 'WARN', 'data/shop.db 订单库',
    is_file(SHOP_DB_FILE) ? ('已存在 ' . number_format(filesize(SHOP_DB_FILE)) . ' 字节') : '尚未生成（首次下单/进订单页时自动创建）');

if (trim((string)$cfg['pay']['qr_url']) !== '') {
    line('PASS', '收款码已配置', mb_substr((string)$cfg['pay']['qr_url'], 0, 46));
} else {
    line('FAIL', '收款码未配置', '客户将无法付款');
    $todo[] = '后台 →「商城下单 → 收款与表单」填写收款码图片地址';
}

$prodCount = count($cfg['products']);
$onCount = 0;
foreach ($cfg['products'] as $p) if (($p['status'] ?? 'on') === 'on') $onCount++;
if ($onCount > 0) {
    line('PASS', '已上架商品', $onCount . ' 个（共 ' . $prodCount . ' 个）');
} else {
    line('FAIL', '没有已上架的商品', '前台商品墙会是空的');
    $todo[] = '后台 →「商城下单 → 商品管理」添加并上架至少一个商品';
}

if (is_file(SHOP_DB_FILE)) {
    try {
        $gdb = new PDO('sqlite:' . SHOP_DB_FILE);
        $gdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $all = $gdb->query('SELECT customer_name, wechat, phone, remark FROM orders')->fetchAll(PDO::FETCH_ASSOC);
        $tot = count($all);
        if ($tot === 0) {
            line('INFO', '订单敏感字段加密', '暂无订单，下单后自动加密');
        } else {
            $plain = 0;
            foreach ($all as $r) {
                foreach (['customer_name', 'wechat', 'phone', 'remark'] as $col) {
                    $v = (string)($r[$col] ?? '');
                    if ($v !== '' && strpos($v, 'enc1:') !== 0) { $plain++; break; }
                }
            }
            if ($plain === 0) {
                line('PASS', '订单敏感字段加密', $tot . ' 条订单全部为密文（姓名/微信/电话/备注/IP）');
            } else {
                line('WARN', '订单敏感字段加密', $plain . ' / ' . $tot . ' 条仍是明文');
                $todo[] = '有订单未加密：确认 inc/shop_store.php 是最新版，并访问一次后台订单页触发自动迁移';
            }
        }
    } catch (Throwable $e) {
        line('WARN', '订单敏感字段加密', '无法检测：' . $e->getMessage());
    }
}

$enabledCh = array_keys(shop_push_channels(true));
if (!empty($cfg['push']['enable']) && $enabledCh) {
    line('PASS', '推送通道已启用', implode('、', $enabledCh));
} else {
    line('FAIL', '推送未配置', '有人下单你不会收到通知');
    $todo[] = '后台 →「商城下单 → 消息推送」开启推送并配置至少一个通道，然后点「测试推送」验证';
}

head('8. 数据保护核对（CLI 无法探测 Nginx，请按提示手工验证）');
line('INFO', 'data/ 目录保护', '请在浏览器无痕窗口访问 https://你的域名/data/shop.db');
line('INFO', '  期望结果', '403 Forbidden（现有 location ~* ^/data/ { deny all; } 已覆盖）');
line('INFO', '后台界面文件直连保护', '访问 https://你的域名/inc/admin_view.php 应返回 403；后台入口是 /admin.php');
line('INFO', '推送重试脚本', 'CLI 用 cron 调用最安全；若要用 URL 触发，需带 token（见文件内注释）');
line('INFO', '本自检脚本', '检查完成后建议删除 cron/env_check.php');

head('检查结果汇总');
printf("  通过 %d 项  警告 %d 项  失败 %d 项\n", $pass, $warn, $fail);
if ($todo) {
    echo "\n  需要你处理的事项：\n";
    foreach (array_values(array_unique($todo)) as $i => $t) {
        echo '   ' . ($i + 1) . ". $t\n";
    }
}
echo "\n";
if ($fail === 0) {
    echo "  === 环境就绪，商城模块可以正常使用 ===\n";
    if ($warn > 0) echo "  （上面的警告项建议一并处理，但不影响基本下单流程）\n";
} else {
    echo "  === 存在 $fail 项必须解决的问题，请先按上面清单处理 ===\n";
}
echo "\n";
exit($fail === 0 ? 0 : 1);
