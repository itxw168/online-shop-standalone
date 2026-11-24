<?php
/**
 * 容器首次启动初始化：
 * - 生成 secret/config.secret.php（唯一 AES 密钥）
 * - 用演示数据初始化 data/shop.json（加密存储）
 * - 生成后台账号（默认 admin / admin123，可用环境变量覆盖）
 * - 补齐 data/ 与 secret/ 的访问保护文件
 *
 * 已存在的数据不会被覆盖；升级/重启不会重置密码或商城配置。
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$dataDir = $root . '/data';
$secretDir = $root . '/secret';
$seedDir = $root . '/docker/seed';

function shop_init_mkdir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        fwrite(STDERR, "[shop-init] cannot create directory: {$dir}\n");
        exit(1);
    }
}

function shop_init_copy(string $src, string $dst): void
{
    if (!is_file($dst) && is_file($src)) {
        if (!copy($src, $dst)) {
            fwrite(STDERR, "[shop-init] cannot copy {$src} -> {$dst}\n");
            exit(1);
        }
        @chmod($dst, 0640);
    }
}

function shop_init_hex(int $bytes): string
{
    return bin2hex(random_bytes($bytes));
}

shop_init_mkdir($dataDir);
shop_init_mkdir($secretDir);

shop_init_copy($seedDir . '/data.htaccess', $dataDir . '/.htaccess');
shop_init_copy($seedDir . '/data.index.html', $dataDir . '/index.html');
shop_init_copy($seedDir . '/secret.htaccess', $secretDir . '/.htaccess');
shop_init_copy($seedDir . '/secret.index.html', $secretDir . '/index.html');

$secretFile = $secretDir . '/config.secret.php';
if (!is_file($secretFile) || filesize($secretFile) < 32) {
    $content = "<?php\n\n"
        . "define('AES_KEY', '" . shop_init_hex(32) . "');\n\n"
        . "define('AES_IV', '" . shop_init_hex(16) . "');\n\n"
        . "define('ENCRYPTED_SUFFIX', '');\n";
    if (file_put_contents($secretFile, $content, LOCK_EX) === false) {
        fwrite(STDERR, "[shop-init] cannot write {$secretFile}\n");
        exit(1);
    }
    @chmod($secretFile, 0600);
    fwrite(STDOUT, "[shop-init] generated secret/config.secret.php\n");
}

$configFile = $dataDir . '/shop.json';
if (!is_file($configFile) || filesize($configFile) === 0) {
    $seedFile = $seedDir . '/shop.demo.json';
    $raw = is_file($seedFile) ? file_get_contents($seedFile) : false;
    $seed = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($seed)) {
        fwrite(STDERR, "[shop-init] invalid demo seed: {$seedFile}\n");
        exit(1);
    }

    if (!defined('IN_CRYPT')) {
        define('IN_CRYPT', true);
    }
    require_once $root . '/inc/crypt.php';

    if (!saveEncryptedData($configFile, $seed)) {
        fwrite(STDERR, "[shop-init] cannot write demo config: {$configFile}\n");
        exit(1);
    }
    fwrite(STDOUT, "[shop-init] seeded data/shop.json with demo data\n");
}

$adminUser = getenv('SHOP_ADMIN_USER');
$adminUser = is_string($adminUser) && $adminUser !== '' ? $adminUser : 'admin';

$accountFile = $dataDir . '/account.php';
if (!is_file($accountFile) || filesize($accountFile) === 0) {
    $adminPassword = getenv('SHOP_ADMIN_PASSWORD');
    $adminPassword = is_string($adminPassword) && $adminPassword !== '' ? $adminPassword : 'admin123';

    $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        fwrite(STDERR, "[shop-init] password_hash failed\n");
        exit(1);
    }

    $account = "<?php\n\nreturn " . var_export([
        'user' => $adminUser,
        'hash' => $hash,
    ], true) . ";\n";

    if (file_put_contents($accountFile, $account, LOCK_EX) === false) {
        fwrite(STDERR, "[shop-init] cannot write {$accountFile}\n");
        exit(1);
    }
    @chmod($accountFile, 0600);
    fwrite(STDOUT, "[shop-init] created admin account: {$adminUser}\n");
    fwrite(STDOUT, "[shop-init] change the initial password after first login\n");
}

@chmod($dataDir, 0750);
@chmod($secretDir, 0750);

fwrite(STDOUT, "[shop-init] ready\n");
