<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("本脚本仅限命令行运行\n");
}

$ROOT = dirname(__DIR__);
$DATA = $ROOT . '/data';
if (!is_dir($DATA)) @mkdir($DATA, 0755, true);

define('SHOP_GEO_OK', true);
require_once $ROOT . '/inc/ip_geo.php';

$MIRRORS = [
    'https://raw.githubusercontent.com/lionsoul2014/ip2region/master/data/%s',
    'https://cdn.jsdelivr.net/gh/lionsoul2014/ip2region@master/data/%s',
    'https://raw.gitmirror.com/lionsoul2014/ip2region/master/data/%s',
    'https://ghproxy.net/https://raw.githubusercontent.com/lionsoul2014/ip2region/master/data/%s',
    'https://gitee.com/lionsoul/ip2region/raw/master/data/%s',
];

$TARGETS = [
    ['file' => 'ip2region.xdb',    'label' => 'IPv4', 'min' => 3000000],
    ['file' => 'ip2region.v6.xdb', 'label' => 'IPv6', 'min' => 1000000],
];

function out($s = '') { echo $s . "\n"; }
function hr() { out(str_repeat('=', 72)); }

function try_download($url, $dest) {
    $fp = @fopen($dest, 'wb');
    if (!$fp) return 0;
    $ok = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'ip2region-fetch/1.0',
        ]);
        $ok = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $ok = $ok && $code >= 200 && $code < 300;
    } else {
        $body = @file_get_contents($url, false, stream_context_create(['http' => [
            'timeout' => 180, 'follow_location' => 1, 'max_redirects' => 4,
            'header' => "User-Agent: ip2region-fetch/1.0\r\n",
        ], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]));
        if (is_string($body) && $body !== '') { fwrite($fp, $body); $ok = true; }
    }
    fclose($fp);
    return $ok ? (int)@filesize($dest) : 0;
}

hr();
out('ip2region 离线库 下载并校验');
hr();
out('站点目录：' . $ROOT);
out();

$allOk = true;
foreach ($TARGETS as $t) {
    $dest = $DATA . '/' . $t['file'];
    hr();
    out("【{$t['label']}】{$t['file']}");

    if (is_file($dest) && filesize($dest) >= $t['min']) {
        out('  已存在（' . number_format(filesize($dest)) . ' 字节），跳过下载');
        continue;
    }

    $done = false;
    foreach ($MIRRORS as $m) {
        $url = sprintf($m, $t['file']);
        out('  尝试 ' . parse_url($url, PHP_URL_HOST) . ' …');
        $tmp = $dest . '.part';
        @unlink($tmp);
        $n = try_download($url, $tmp);
        if ($n >= $t['min']) {
            @rename($tmp, $dest);
            out('  ✓ 下载成功：' . number_format($n) . ' 字节');
            $done = true;
            break;
        }
        @unlink($tmp);
        out('    ✗ 失败或不完整（' . number_format($n) . ' 字节）');
    }

    if (!$done) {
        out('  ✗ 所有镜像都下载失败');
        out('    你可以手动下载后上传到：' . $dest);
        out('    官方地址：https://github.com/lionsoul2014/ip2region/tree/master/data');
        $allOk = false;
    }
}

hr();
out('校验');
hr();

function chk($label, $cond, $extra = '') {
    out(sprintf('  [%s] %-34s %s', $cond ? '✓' : '✗', $label, $extra));
    return (bool)$cond;
}
$pass = 0; $total = 0;
function chk2($label, $cond, $extra = '') {
    global $pass, $total;
    $total++;
    if (chk($label, $cond, $extra)) $pass++;
}

foreach ([[false, 'IPv4', 'ip2region.xdb', '8.8.8.8', '美国'],
          [true,  'IPv6', 'ip2region.v6.xdb', '2001:4860:4860::8888', '']] as $t) {
    $f = $DATA . '/' . $t[2];
    chk2($t[1] . ' 文件存在', is_file($f), is_file($f) ? number_format(filesize($f)) . ' 字节' : '缺失');
    if (!is_file($f)) continue;

    $fp = @fopen($f, 'rb');
    $hdr = $fp ? (string)fread($fp, 256) : '';
    if ($fp) fclose($fp);
    $idxStart = strlen($hdr) === 256 ? unpack('V', substr($hdr, 8, 4))[1] : 0;
    $idxEnd   = strlen($hdr) === 256 ? unpack('V', substr($hdr, 12, 4))[1] : 0;
    $size     = (int)filesize($f);
    chk2($t[1] . ' 头部结构正常',
         $idxStart >= 256 + 524288 && $idxEnd <= $size && $idxEnd > $idxStart,
         "索引 {$idxStart}-{$idxEnd} / 文件 {$size}");

    $r = ip_geo_xdb_search($t[3]);
    chk2($t[1] . " 试查 {$t[3]}", $r !== null,
         $r ? ('→ ' . $r['location']) : '查不到（文件可能是坏的）');
}

$r = ip_geo_lookup('127.0.0.1');
chk2('内网地址本地判定', $r['ok'] && $r['source'] === '本地判定', '→ ' . $r['location']);

hr();
out("结果：{$pass} / {$total} 项通过");
out();
out('说明：');
out('  · 离线库只在「在线接口全部不可达」时才会用到，属于兜底；');
out('  · 装了以后，断网 / 内网环境下也能识别 IP 归属地；');
out('  · 不想装可以忽略本脚本，功能不受影响（前提是服务器能访问外网）。');
hr();

exit(($pass === $total && $allOk) ? 0 : 1);
