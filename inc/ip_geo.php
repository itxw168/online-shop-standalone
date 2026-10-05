<?php

if (!defined('SHOP_GEO_OK')) define('SHOP_GEO_OK', true);

function ip_geo_cache_file() {
    return __DIR__ . '/../data/ip_geo_cache.json';
}

function ip_geo_xdb_file($isV6) {
    $dir = defined('SHOP_GEO_XDB_DIR') ? rtrim(SHOP_GEO_XDB_DIR, '/\\') : (__DIR__ . '/../data');
    return $dir . '/' . ($isV6 ? 'ip2region.v6.xdb' : 'ip2region.xdb');
}

function ip_geo_valid($ip) {
    return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

function ip_geo_reserved_label($ip) {
    if (!ip_geo_valid($ip)) return '';

    if (strpos($ip, ':') === false) {

        $n = @inet_pton($ip);
        if ($n === false || strlen($n) !== 4) return '无效地址';
        $rules = [
            ['0.0.0.0',     '0.255.255.255',     '保留地址'],
            ['10.0.0.0',    '10.255.255.255',    '内网地址（A 类私有）'],
            ['100.64.0.0',  '100.127.255.255',   '运营商级 NAT 地址'],
            ['127.0.0.0',   '127.255.255.255',   '本机回环地址'],
            ['169.254.0.0', '169.254.255.255',   '链路本地地址'],
            ['172.16.0.0',  '172.31.255.255',    '内网地址（B 类私有）'],
            ['192.0.2.0',   '192.0.2.255',       '文档用保留地址'],
            ['192.168.0.0', '192.168.255.255',   '内网地址（C 类私有）'],
            ['198.18.0.0',  '198.19.255.255',    '基准测试保留地址'],
            ['198.51.100.0','198.51.100.255',    '文档用保留地址'],
            ['203.0.113.0', '203.0.113.255',     '文档用保留地址'],
            ['224.0.0.0',   '239.255.255.255',   '组播地址'],
            ['240.0.0.0',   '255.255.255.255',   '保留地址'],
        ];
        foreach ($rules as $r) {
            $a = @inet_pton($r[0]);
            $b = @inet_pton($r[1]);
            if ($a === false || $b === false) continue;
            if (strcmp($n, $a) >= 0 && strcmp($n, $b) <= 0) return $r[2];
        }
        return '';
    }

    $bin = @inet_pton($ip);
    if ($bin === false || strlen($bin) !== 16) return '无效地址';
    $b = array_values(unpack('C16', $bin));
    $all0 = true; $all0exceptLast = true;
    for ($i = 0; $i < 16; $i++) {
        if ($b[$i] !== 0) { $all0 = false; if ($i < 15) $all0exceptLast = false; }
    }
    if ($all0) return '未指定地址';
    if ($all0exceptLast && $b[15] === 1) return '本机回环地址';
    if ($b[0] === 0xfe && ($b[1] & 0xc0) === 0x80) return '链路本地地址';
    if (($b[0] & 0xfe) === 0xfc) return '内网地址（唯一本地地址）';
    if ($b[0] === 0xff) return '组播地址';
    if ($b[0] === 0x20 && $b[1] === 0x01 && $b[2] === 0x0d && $b[3] === 0xb8) return '文档用保留地址';

    if ($b[0] === 0 && $b[1] === 0 && $b[2] === 0 && $b[3] === 0 && $b[4] === 0 && $b[5] === 0
        && $b[6] === 0 && $b[7] === 0 && $b[8] === 0 && $b[9] === 0 && $b[10] === 0xff && $b[11] === 0xff) {
        return ip_geo_reserved_label(long2ip(($b[12] << 24) | ($b[13] << 16) | ($b[14] << 8) | $b[15]));
    }
    return '';
}

function ip_geo_http($url, $timeout = 4) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 2,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'shop-admin-ipgeo/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code >= 200 && $code < 300) ? (string)$body : '';
    }
    if (!ini_get('allow_url_fopen')) return '';
    $ctx = stream_context_create(['http' => [
        'timeout'       => $timeout,
        'ignore_errors' => true,
        'header'        => "User-Agent: shop-admin-ipgeo/1.0\r\n",
    ], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $body = @file_get_contents($url, false, $ctx);
    return is_string($body) ? $body : '';
}

function ip_geo_build($country, $prov, $city, $isp) {
    $clean = function ($s) {
        $s = trim(preg_replace('/\s+/u', ' ', (string)$s));
        return ($s === '0' || $s === '-') ? '' : $s;
    };
    $country = $clean($country);
    $prov    = $clean($prov);
    $city    = $clean($city);
    $isp     = $clean($isp);

    $loc = [];
    if ($country !== '') $loc[] = $country;

    if ($prov !== '' && $prov !== $country) $loc[] = $prov;
    if ($city !== '' && $city !== $prov && $city !== $country) {

        $skip = false;
        foreach ($loc as $x) {
            if ($x === $city) { $skip = true; break; }

            $a = preg_replace('/(省|市|自治区|特别行政区)$/u', '', $x);
            $b = preg_replace('/(省|市|自治区|特别行政区)$/u', '', $city);
            if ($a !== '' && $a === $b) { $skip = true; break; }
        }
        if (!$skip) $loc[] = $city;
    }

    $head = implode(' ', $loc);
    if ($isp !== '') return ($head !== '' ? $head . ' · ' . $isp : $isp);
    return $head;
}

;
function ip_geo_join($parts) {
    $parts = array_values(array_filter(array_map(function ($s) {
        $s = trim(preg_replace('/\s+/u', ' ', (string)$s));
        return ($s === '' || $s === '0' || $s === '-') ? '' : $s;
    }, $parts)));
    $parts = array_values(array_unique($parts));
    if (!$parts) return '';
    $loc = array_slice($parts, 0, 3);
    $isp = array_slice($parts, 3);
    return implode(' ', $loc) . ($isp ? ' · ' . implode(' ', $isp) : '');
}

function ip_geo_norm_country($s) {
    $s = trim(preg_replace('/\s+/u', '', (string)$s));
    if ($s === '') return '';
    static $map = [
        '中華人民共和國' => '中国', '中华人民共和国' => '中国', '中國' => '中国',
        '中華民國' => '中国台湾', '中國台灣' => '中国台湾', '台灣' => '中国台湾', '台湾' => '中国台湾',
        '香港特別行政區' => '中国香港', '香港' => '中国香港',
        '澳門特別行政區' => '中国澳门', '澳门' => '中国澳门', '澳門' => '中国澳门',
        '澳大利亞' => '澳大利亚', '澳洲' => '澳大利亚',
        '新西蘭' => '新西兰', '新加坡共和國' => '新加坡',
        '大韓民國' => '韩国', '韓國' => '韩国',
        '俄羅斯' => '俄罗斯', '俄羅斯聯邦' => '俄罗斯',
        '美國' => '美国', '英國' => '英国', '德國' => '德国', '法國' => '法国',
        '日本國' => '日本', '印度尼西亞' => '印度尼西亚', '馬來西亞' => '马来西亚',
        '泰國' => '泰国', '越南社會主義共和國' => '越南', '菲律賓' => '菲律宾',
        '意大利' => '意大利', '西班牙' => '西班牙', '荷蘭' => '荷兰', '瑞士' => '瑞士',
        '加拿大' => '加拿大', '巴西' => '巴西', '墨西哥' => '墨西哥',
    ];
    return $map[$s] ?? $s;
}

function ip_geo_parse_ipchk(array $j) {
    $country = ip_geo_norm_country($j['country'] ?? '');
    $cc      = strtoupper(trim((string)($j['country_code'] ?? '')));
    $isCN    = ($country === '中国' || $cc === 'CN');

    $geocn = is_array($j['geocn'] ?? null) ? $j['geocn'] : [];
    $qq    = is_array($j['qqwry'] ?? null) ? $j['qqwry'] : [];
    $db    = is_array($j['dbip_city'] ?? null) ? $j['dbip_city'] : [];
    $seg   = explode('|', (string)($j['ip2region'] ?? ''));

    $isp = '';
    foreach ([$qq['isp'] ?? '', $geocn['isp'] ?? '', $j['isp'] ?? '', $j['org'] ?? ''] as $x) {
        $x = trim(preg_replace('/\s+/u', ' ', (string)$x));
        if ($x !== '' && $x !== '0') { $isp = $x; break; }
    }

    if (!$isCN) {

        $prov = trim((string)($j['region'] ?? ''));
        $city = trim((string)($j['city'] ?? ''));
        if ($city === '') {
            $city = trim((string)($db['city'] ?? ''));
            if ($prov === '') $prov = trim((string)($db['administrative_area'] ?? ''));
        }
        if ($isp === '') $isp = trim((string)($j['isp'] ?? ''));
        return ip_geo_mk($country, $prov, $city, '', $isp, 'ipchk.cn');
    }

    $cands = [
        ['p' => (string)($geocn['administrative_area'] ?? ''), 'c' => (string)($geocn['city'] ?? ''), 'src' => 'geocn'],
        ['p' => (count($seg) >= 3 ? $seg[1] : ''),             'c' => (count($seg) >= 3 ? $seg[2] : ''), 'src' => 'ip2region'],
        ['p' => (string)($qq['administrative_area'] ?? ''),    'c' => (string)($qq['city'] ?? ''),    'src' => 'qqwry'],
        ['p' => (string)($j['region'] ?? ''),                  'c' => (string)($j['city'] ?? ''),     'src' => 'ip-api'],
    ];

    $norm = function ($s) {
        $s = trim(preg_replace('/\s+/u', '', (string)$s));
        if ($s === '' || $s === '0') return '';
        return trim(preg_replace('/(特别行政区|维吾尔自治区|回族自治区|壮族自治区|自治区|省|市|自治州|地区)$/u', '', $s));
    };

    $pv = []; $pRaw = [];
    foreach ($cands as $c) {
        $n = $norm($c['p']);
        if ($n === '') continue;
        $pv[$n] = ($pv[$n] ?? 0) + 1;
        if (!isset($pRaw[$n]) || (mb_strpos((string)$c['p'], '省') !== false && mb_strpos($pRaw[$n], '省') === false)) {
            $pRaw[$n] = trim((string)$c['p']);
        }
    }

    $prov = ''; $city = ''; $winP = ''; $winC = '';
    if ($pv) {
        arsort($pv);
        $winP = (string)array_key_first($pv);
        $prov = $pRaw[$winP] ?? $winP;

        $cv = []; $cRaw = [];
        foreach ($cands as $c) {
            $np = $norm($c['p']);
            if ($np !== '' && $np !== $winP) continue;
            $nc = $norm($c['c']);
            if ($nc === '') continue;
            $cv[$nc] = ($cv[$nc] ?? 0) + 1;
            if (!isset($cRaw[$nc]) || (mb_strpos((string)$c['c'], '市') !== false && mb_strpos($cRaw[$nc], '市') === false)) {
                $cRaw[$nc] = trim((string)$c['c']);
            }
        }
        if ($cv) {
            arsort($cv);
            $winC = (string)array_key_first($cv);
            $city = $cRaw[$winC] ?? $winC;
        }
    } else {
        $prov = trim((string)($j['region'] ?? ''));
        $city = trim((string)($j['city'] ?? ''));
    }

    $district = '';
    if ($pv) {
        arsort($pv);
        $pvVals = array_values($pv);
        $top = (int)$pvVals[0];
        $second = (int)($pvVals[1] ?? 0);
        $clearWinner = ($top >= 2 && $top > $second);
        if ($clearWinner) {
            $district = trim((string)($geocn['district'] ?? ''));
        }
    }
    if ($district === '' ) {

        foreach ([$qq['city'] ?? ''] as $x) {
            $x = trim((string)$x);
            if ($x !== '' && $city !== '' && $x !== $city && mb_strpos($x, $city) !== false) {
                $district = mb_substr($x, mb_strlen($city));
                break;
            }
        }
    }

    if ($district !== '' && preg_match('/^(电信|联通|移动|铁通|教育网|广电网|长城宽带)$/u', $district)) {
        $district = '';
    }

    return ip_geo_mk($country, $prov, $city, $district, $isp, 'ipchk.cn');
}

function ip_geo_mk($country, $prov, $city, $district, $isp, $src) {
    return [
        'country'  => trim((string)$country),
        'prov'     => trim((string)$prov),
        'city'     => trim((string)$city),
        'district' => trim((string)$district),
        'isp'      => trim((string)$isp),
        'src'      => (string)$src,
    ];
}

function ip_geo_richness(array $r) {
    $n = 0;
    if ($r['country'] !== '')  $n += 1;
    if ($r['prov'] !== '')     $n += 2;
    if ($r['city'] !== '')     $n += 4;
    if ($r['district'] !== '') $n += 8;
    if ($r['isp'] !== '')      $n += 1;
    return $n;
}

function ip_geo_src_ipchk($ip) {
    $r = ip_geo_http('https://ipchk.cn/v1/location/' . rawurlencode($ip), 5);
    if ($r === '') return null;
    $j = json_decode($r, true);
    if (!is_array($j) || empty($j['ip'])) return null;
    return ip_geo_parse_ipchk($j);
}

function ip_geo_src_ip2location($ip) {
    $r = ip_geo_http('https://api.ip2location.io/?ip=' . rawurlencode($ip), 5);
    if ($r === '') return null;
    $j = json_decode($r, true);
    if (!is_array($j) || empty($j['ip'])) return null;
    if (!empty($j['error'])) return null;
    $cc = strtoupper(trim((string)($j['country_code'] ?? '')));
    $country = trim((string)($j['country_name'] ?? ''));
    if ($cc === 'CN' || $country === 'China') $country = '中国';
    $isp = trim((string)($j['as'] ?? ''));
    if ($isp === '') $isp = trim((string)($j['asn'] ?? ''));
    return ip_geo_mk($country, (string)($j['region_name'] ?? ''), (string)($j['city_name'] ?? ''),
                     '', $isp, 'ip2location');
}

function ip_geo_src_ipapi($ip) {

    $r = ip_geo_http('http://ip-api.com/json/' . rawurlencode($ip)
        . '?lang=zh-CN&fields=status,country,regionName,city,district,isp,org', 5);
    if ($r === '') return null;
    $j = json_decode($r, true);
    if (!is_array($j) || ($j['status'] ?? '') !== 'success') return null;
    $isp = trim((string)($j['isp'] ?? ''));
    if ($isp === '') $isp = trim((string)($j['org'] ?? ''));
    return ip_geo_mk($j['country'] ?? '', $j['regionName'] ?? '', $j['city'] ?? '',
                     $j['district'] ?? '', $isp, 'ip-api.com');
}

function ip_geo_src_ipinfo($ip) {
    $token = defined('IP_GEO_IPINFO_TOKEN') ? trim((string)IP_GEO_IPINFO_TOKEN) : '';
    $url = 'https://ipinfo.io/' . rawurlencode($ip) . '/json' . ($token !== '' ? ('?token=' . rawurlencode($token)) : '');
    $r = ip_geo_http($url, 5);
    if ($r === '') return null;
    $j = json_decode($r, true);
    if (!is_array($j) || empty($j['ip'])) return null;
    $map = ['CN' => '中国', 'US' => '美国', 'JP' => '日本', 'HK' => '中国香港',
            'TW' => '中国台湾', 'SG' => '新加坡', 'KR' => '韩国', 'GB' => '英国', 'DE' => '德国'];
    $cc = strtoupper(trim((string)($j['country'] ?? '')));
    $country = $map[$cc] ?? $cc;
    return ip_geo_mk($country, (string)($j['region'] ?? ''), (string)($j['city'] ?? ''),
                     '', (string)($j['org'] ?? ''), 'ipinfo.io');
}

function ip_geo_src_ipsb($ip) {
    $r = ip_geo_http('https://api.ip.sb/geoip/' . rawurlencode($ip), 5);
    if ($r === '') return null;
    $j = json_decode($r, true);
    if (!is_array($j) || empty($j['ip'])) return null;
    $isp = trim((string)($j['isp'] ?? ''));
    if ($isp === '') $isp = trim((string)($j['organization'] ?? ''));
    return ip_geo_mk($j['country'] ?? '', $j['region'] ?? '', $j['city'] ?? '', '', $isp, 'ip.sb');
}

function ip_geo_src_ipwhois($ip) {
    $r = ip_geo_http('https://ipwho.is/' . rawurlencode($ip), 5);
    if ($r === '') return null;
    $j = json_decode($r, true);
    if (!is_array($j) || empty($j['success'])) return null;
    $conn = is_array($j['connection'] ?? null) ? $j['connection'] : [];
    $isp = trim((string)($conn['isp'] ?? ''));
    if ($isp === '') $isp = trim((string)($conn['org'] ?? ''));
    return ip_geo_mk($j['country'] ?? '', $j['region'] ?? '', $j['city'] ?? '', '', $isp, 'ipwho.is');
}

function ip_geo_src_pconline($ip) {
    if (strpos($ip, ':') !== false) return null;
    $r = ip_geo_http('https://whois.pconline.com.cn/ipJson.jsp?ip=' . rawurlencode($ip) . '&json=true', 5);
    if ($r === '') return null;
    if (function_exists('iconv')) {
        $cv = @iconv('GBK', 'UTF-8//IGNORE', $r);
        if ($cv !== false) $r = $cv;
    } elseif (function_exists('mb_convert_encoding')) {
        $r = @mb_convert_encoding($r, 'UTF-8', 'GBK');
    } else {
        return null;
    }
    $j = json_decode($r, true);
    if (!is_array($j)) return null;
    $addr = trim((string)($j['addr'] ?? ''));
    $prov = trim((string)($j['pro'] ?? ''));
    $city = trim((string)($j['city'] ?? ''));

    if ($city === '' && $addr !== '') {
        $city = $addr;
    }
    if ($prov === '' && $city === '' && $addr === '') return null;
    return ip_geo_mk('', $prov, $city, '', '', 'pconline');
}

function ip_geo_online($ip) {
    $a = null; $b = null;
    try { $a = ip_geo_src_ipchk($ip); }       catch (Throwable $e) {}
    try { $b = ip_geo_src_ip2location($ip); } catch (Throwable $e) {}

    if ($a !== null || $b !== null) {
        if ($a === null) { $m = $b; }
        elseif ($b === null) { $m = $a; }
        else {
            $m = $a;

            if ($m['prov'] === '' && $b['prov'] !== '') $m['prov'] = $b['prov'];

            if ($m['city'] === '' && $b['city'] !== '') $m['city'] = $b['city'];

            if ($m['district'] === '' && $b['district'] !== '') $m['district'] = $b['district'];

            if ($m['country'] === '' && $b['country'] !== '') $m['country'] = $b['country'];

            if ($m['isp'] === '' && $b['isp'] !== '') $m['isp'] = $b['isp'];
            if ($m['prov'] !== '' && $b['prov'] !== '' && $m['prov'] !== $b['prov']
                && $m['city'] === '' && $b['city'] !== '') {
                $m['src'] = $a['src'] . '+' . $b['src'];
            }
        }
        $txt = ip_geo_render($m);
        if ($txt !== '') return ['location' => $txt, 'source' => $m['src']];
    }

    foreach (['ip_geo_src_ipapi', 'ip_geo_src_ipsb', 'ip_geo_src_ipinfo',
              'ip_geo_src_ipwhois', 'ip_geo_src_pconline'] as $fn) {
        try { $r = $fn($ip); } catch (Throwable $e) { $r = null; }
        if ($r === null) continue;
        $txt = ip_geo_render($r);
        if ($txt !== '') return ['location' => $txt, 'source' => $r['src']];
    }
    return null;
}

function ip_geo_render(array $r) {
    $clean = function ($s) {
        $s = trim(preg_replace('/\s+/u', ' ', (string)$s));
        return ($s === '0' || $s === '-' || $s === 'null') ? '' : $s;
    };
    $country = $clean(ip_geo_norm_country($r['country'] ?? ''));
    $prov    = $clean($r['prov'] ?? '');
    $city    = $clean($r['city'] ?? '');
    $dist    = $clean($r['district'] ?? '');
    $isp     = $clean($r['isp'] ?? '');
    if (mb_strlen($isp) > 16) $isp = mb_substr($isp, 0, 15) . '…';

    $strip = function ($s) { return preg_replace('/(省|市|自治区|特别行政区|自治州|地区)$/u', '', (string)$s); };

    if ($city !== '' && $dist !== '') {
        $city = (strpos($city, $dist) !== false) ? $city : ($city . $dist);
    } elseif ($city === '' && $dist !== '') {
        $city = $dist;
    }

    $parts = [];
    if ($country !== '') $parts[] = $country;

    $dropProv = false;
    if ($prov !== '' && $city !== '') {
        $sp = $strip($prov);
        if ($sp !== '' && mb_strpos($city, $sp) === 0) $dropProv = true;
    }
    if (!$dropProv && $prov !== '' && $strip($prov) !== $strip($country)) $parts[] = $prov;

    if ($city !== '') {
        $dup = false;
        foreach ($parts as $p) { if ($strip($p) === $strip($city)) { $dup = true; break; } }
        if (!$dup) $parts[] = $city;
    }

    $head = implode(' ', $parts);
    if ($head === '') return $isp;

    return $isp !== '' ? ($head . ' · ' . $isp) : $head;
}

function ip_geo_u16($s, $o, $be = false) { return $be ? unpack('n', substr($s, $o, 2))[1] : unpack('v', substr($s, $o, 2))[1]; }
function ip_geo_u32($s, $o, $be = false) { return $be ? unpack('N', substr($s, $o, 4))[1] : unpack('V', substr($s, $o, 4))[1]; }

function ip_geo_xdb_search($ip) {
    $isV6 = strpos($ip, ':') !== false;
    $file = ip_geo_xdb_file($isV6);
    if (!is_file($file)) return null;
    $size = (int)@filesize($file);
    if ($size < 256 + 524288) return null;

    $fp = @fopen($file, 'rb');
    if (!$fp) return null;

    try {

        $hdr = (string)fread($fp, 256);
        if (strlen($hdr) !== 256) return null;

        $be = false;
        $idxStart = ip_geo_u32($hdr, 8, false);
        $idxEnd   = ip_geo_u32($hdr, 12, false);
        $okLE = ($idxStart >= 256 + 524288 && $idxEnd <= $size && $idxEnd > $idxStart);
        if (!$okLE) {
            $be = true;
            $idxStart = ip_geo_u32($hdr, 8, true);
            $idxEnd   = ip_geo_u32($hdr, 12, true);
            if (!($idxStart >= 256 + 524288 && $idxEnd <= $size && $idxEnd > $idxStart)) return null;
        }
        $hdrIpVer  = ip_geo_u16($hdr, 16, $be);
        $ptrBytes  = ip_geo_u16($hdr, 18, $be);
        if ($ptrBytes !== 4) return null;
        if ($hdrIpVer !== 0 && $hdrIpVer !== 4 && $hdrIpVer !== 6) return null;
        if ($hdrIpVer === 4 && $isV6) return null;
        if ($hdrIpVer === 6 && !$isV6) return null;

        $entrySize = $isV6 ? 38 : 14;
        if (($idxEnd - $idxStart) % $entrySize !== 0) return null;
        $rows = (int)(($idxEnd - $idxStart) / $entrySize);
        if ($rows <= 0) return null;

        $key = @inet_pton($ip);
        if ($key === false) return null;
        if ($isV6 && strlen($key) !== 16) return null;
        if (!$isV6 && strlen($key) !== 4)  return null;

        $lo = 0;
        $hi = $rows;
        $hit = null;
        $ipLen = $isV6 ? 16 : 4;
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi, 2);
            if (@fseek($fp, $idxStart + $mid * $entrySize) !== 0) return null;
            $e = (string)fread($fp, $entrySize);
            if (strlen($e) !== $entrySize) return null;
            $s = substr($e, 0, $ipLen);
            $t = substr($e, $ipLen, $ipLen);
            if (strcmp($key, $s) < 0)      { $hi = $mid; }
            elseif (strcmp($key, $t) > 0)  { $lo = $mid + 1; }
            else { $hit = $e; break; }
        }
        if ($hit === null) return null;

        $dataLen = ip_geo_u16($hit, $ipLen * 2, $be);
        $dataPtr = ip_geo_u32($hit, $ipLen * 2 + 2, $be);
        if ($dataLen <= 0 || $dataLen > 4096) return null;

        if (@fseek($fp, $dataPtr) !== 0) return null;
        $region = (string)fread($fp, $dataLen);
        if (strlen($region) !== $dataLen) return null;
        $region = trim($region);
        if ($region === '') return null;

        $parts = array_filter(array_map('trim', explode('|', $region)), function ($v) {
            return $v !== '' && $v !== '0';
        });
        $loc = implode(' ', array_values($parts));
        if ($loc === '') return null;

        return ['location' => $loc, 'source' => 'ip2region(' . ($isV6 ? 'v6' : 'v4') . ')'];
    } catch (Throwable $e) {
        return null;
    } finally {
        @fclose($fp);
    }
}

function &ip_geo_cache_store() {
    static $c = null;
    if ($c === null) {
        $c = [];
        $f = ip_geo_cache_file();
        if (is_file($f)) {
            $d = null;
            if (function_exists('loadEncryptedData')) {
                $d = @loadEncryptedData($f, []);
            }
            if (!is_array($d)) {
                $d = json_decode((string)@file_get_contents($f), true);
            }
            if (is_array($d)) $c = $d;
        }
    }
    return $c;
}

function ip_geo_cache_load() {
    $c = &ip_geo_cache_store();
    return $c;
}

function ip_geo_cache_get($ip) {
    $c = &ip_geo_cache_store();
    $e = $c[$ip] ?? null;
    if (!is_array($e)) return null;
    if ((int)($e['t'] ?? 0) < time() - 30 * 86400) return null;
    return ['location' => (string)($e['l'] ?? ''), 'source' => (string)($e['s'] ?? 'cache') . '·缓存'];
}

function ip_geo_cache_put($ip, $location, $source) {
    $c = &ip_geo_cache_store();
    $c[$ip] = ['l' => $location, 's' => $source, 't' => time()];

    if (count($c) > 800) {
        uasort($c, function ($a, $b) { return ((int)($b['t'] ?? 0)) <=> ((int)($a['t'] ?? 0)); });
        $c = array_slice($c, 0, 800, true);
    }
    $f = ip_geo_cache_file();
    if (function_exists('saveEncryptedData')) {
        @saveEncryptedData($f, $c);
    } else {
        @file_put_contents($f, json_encode($c, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}

function ip_geo_lookup($ip, $useCache = true) {
    $ip = trim((string)$ip);
    $out = ['ok' => false, 'ip' => $ip, 'location' => '', 'source' => '', 'note' => ''];

    if ($ip === '') { $out['note'] = '没有记录 IP'; return $out; }
    if (!ip_geo_valid($ip)) { $out['note'] = 'IP 格式无效'; return $out; }

    $reserved = ip_geo_reserved_label($ip);
    if ($reserved !== '') {
        $out['ok'] = true;
        $out['location'] = $reserved;
        $out['source'] = '本地判定';
        return $out;
    }

    if ($useCache) {
        $hit = ip_geo_cache_get($ip);
        if ($hit !== null && $hit['location'] !== '') {
            $out['ok'] = true;
            $out['location'] = $hit['location'];
            $out['source'] = $hit['source'];
            return $out;
        }
    }

    $on = ip_geo_online($ip);
    if ($on !== null) {
        ip_geo_cache_put($ip, $on['location'], $on['source']);
        $out['ok'] = true;
        $out['location'] = $on['location'];
        $out['source'] = $on['source'];
        return $out;
    }

    $off = ip_geo_xdb_search($ip);
    if ($off !== null) {
        ip_geo_cache_put($ip, $off['location'], $off['source']);
        $out['ok'] = true;
        $out['location'] = $off['location'];
        $out['source'] = $off['source'];
        return $out;
    }

    $isV6 = strpos($ip, ':') !== false;
    $xdb = ip_geo_xdb_file($isV6);
    $out['note'] = is_file($xdb)
        ? '在线接口均不可达，离线库也没查到该地址'
        : '在线接口均不可达；未安装离线库（可上传 ' . basename($xdb) . ' 到 data/ 目录）';
    return $out;
}

function ip_geo_selftest() {
    $r = ['online' => [], 'offline' => [], 'cache' => 0];
    foreach ([['8.8.8.8', 'IPv4'], ['2001:4860:4860::8888', 'IPv6']] as $t) {
        $x = ip_geo_lookup($t[0], false);
        $r['online'][] = $t[1] . ' ' . $t[0] . ' → ' . ($x['ok'] ? ($x['location'] . ' [' . $x['source'] . ']') : ('失败：' . $x['note']));
    }
    foreach ([[false, 'ip2region.xdb'], [true, 'ip2region.v6.xdb']] as $t) {
        $f = ip_geo_xdb_file($t[0]);
        $r['offline'][] = $t[1] . '：' . (is_file($f) ? ('已安装 ' . number_format((int)filesize($f)) . ' 字节')
                                                       : '未安装');
    }
    $r['cache'] = count(ip_geo_cache_load());
    return $r;
}

function ip_geo_diag_text() {
    $L = [];
    $L[] = 'IP 归属地 诊断报告';
    $L[] = str_repeat('=', 62);
    $L[] = '时间：' . date('Y-m-d H:i:s');
    $L[] = '';

    $L[] = '【1. 文件与目录】';
    $self = __FILE__;
    $L[] = '  inc/ip_geo.php        ' . (is_file($self) ? '存在（' . number_format(filesize($self)) . ' 字节）' : '缺失');
    $dataDir = dirname(dirname($self)) . '/data';
    $L[] = '  data/ 目录            ' . (is_dir($dataDir) ? (is_writable($dataDir) ? '存在且可写' : '存在但【不可写】') : '不存在');
    $cf = ip_geo_cache_file();
    $L[] = '  缓存文件              ' . (is_file($cf) ? ('存在（' . number_format(filesize($cf)) . ' 字节，' . count(ip_geo_cache_load()) . ' 条）') : '尚未生成（首次查询时创建）');
    $L[] = '  PHP 版本              ' . PHP_VERSION;
    $L[] = '  curl 扩展             ' . (function_exists('curl_init') ? '可用' : '不可用（将退回 file_get_contents）');
    $L[] = '  bcmath 扩展           ' . (function_exists('bccomp') ? '可用' : '未装（已不依赖它，属正常）');
    $L[] = '  iconv / mbstring      ' . (function_exists('iconv') ? 'iconv 可用' : (function_exists('mb_convert_encoding') ? 'mbstring 可用' : '都没有（不影响主流程）'));
    $L[] = '  allow_url_fopen       ' . (ini_get('allow_url_fopen') ? '开启' : '关闭');
    $L[] = '';

    $L[] = '【2. 在线接口连通性】（在线优先，逐个试）';
    foreach ([
        'ip-api.com' => 'http://ip-api.com/json/8.8.8.8?lang=zh-CN&fields=status,country,city',
        'ip.sb'      => 'https://api.ip.sb/geoip/8.8.8.8',
        'ipwho.is'   => 'https://ipwho.is/8.8.8.8',
        'pconline'   => 'https://whois.pconline.com.cn/ipJson.jsp?ip=8.8.8.8&json=true',
    ] as $name => $url) {
        $t0 = microtime(true);
        $body = ip_geo_http($url, 6);
        $ms = (int)round((microtime(true) - $t0) * 1000);
        $L[] = sprintf('  %-11s %s  (%d ms)', $name,
            $body === '' ? '【不可达】' : ('可达，返回 ' . number_format(strlen($body)) . ' 字节'), $ms);
    }
    $L[] = '';

    $L[] = '【3. 离线库】（离线兜底，未安装不影响在线可用）';
    foreach ([[false, 'ip2region.xdb', 'IPv4'], [true, 'ip2region.v6.xdb', 'IPv6']] as $t) {
        $f = ip_geo_xdb_file($t[0]);
        $L[] = sprintf('  %-18s %s', $t[1],
            is_file($f) ? ('已安装（' . number_format(filesize($f)) . ' 字节）') : '未安装');
    }
    $L[] = '  提示：需要离线库就跑 php cron/fetch_ip2region.php';
    $L[] = '';

    $L[] = '【4. 实际查询测试】';
    foreach ([['8.8.8.8', 'IPv4 公网'], ['2001:4860:4860::8888', 'IPv6 公网'],
              ['240e:5a8:1:2::3', 'IPv6 国内'], ['127.0.0.1', '内网']] as $t) {
        $r = ip_geo_lookup($t[0], false);
        $L[] = sprintf('  %-22s %-12s %s', $t[0], $t[1],
            $r['ok'] ? ($r['location'] . '  [' . $r['source'] . ']') : ('【失败】' . $r['note']));
    }
    $L[] = '';

    $L[] = '【5. 结论】';
    $anyOnline = false;
    foreach (['http://ip-api.com/json/8.8.8.8?lang=zh-CN&fields=status', 'https://api.ip.sb/geoip/8.8.8.8'] as $u) {
        if (ip_geo_http($u, 5) !== '') { $anyOnline = true; break; }
    }
    if ($anyOnline) {
        $L[] = '  在线接口可用，功能正常。若后台仍看不到归属地，请确认已上传最新的 shop/admin_view.php。';
    } elseif (is_file(ip_geo_xdb_file(false)) || is_file(ip_geo_xdb_file(true))) {
        $L[] = '  在线接口不可达，但已装离线库，会走离线兜底。';
    } else {
        $L[] = '  ⚠ 在线接口全部不可达，且未装离线库 —— 目前无法识别归属地。';
        $L[] = '    解决：① 确认服务器能访问外网；或 ② 跑 php cron/fetch_ip2region.php 装离线库。';
    }
    $L[] = str_repeat('=', 62);
    return implode("\n", $L) . "\n";
}