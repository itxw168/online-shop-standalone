<?php

if (!defined('IN_SHOP')) define('IN_SHOP', true);
if (!function_exists('shop_config')) {
    require_once __DIR__ . '/shop_store.php';
}

function shop_push_backoff() {
    return [30, 120, 600, 3600, 21600];
}

function shop_push_render($tpl, array $vars) {
    $search  = [];
    $replace = [];
    foreach ($vars as $k => $v) {
        $search[]  = '{' . $k . '}';
        $replace[] = is_scalar($v) ? (string)$v : '';
    }
    return str_replace($search, $replace, (string)$tpl);
}

function shop_push_http($url, $method = 'POST', $body = '', array $headers = [], $timeout = 5) {
    $method = strtoupper($method ?: 'POST');
    if ($method === 'GET' && $body !== '' && strpos($url, '?') === false) {
        $url .= '?' . $body;
        $body = '';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'ShopStandalone/1.0',
        ]);
        if ($body !== '' && $method !== 'GET' && $method !== 'HEAD') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return [
            'ok'    => ($resp !== false) && $code >= 200 && $code < 300,
            'code'  => $code,
            'body'  => is_string($resp) ? $resp : '',
            'error' => $err ?: '',
        ];
    }

    $hdrLines = $headers;
    $opts = [
        'http' => [
            'method'        => $method,
            'header'        => implode("\r\n", $hdrLines),
            'content'       => $body,
            'timeout'       => $timeout,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ];
    $resp = @file_get_contents($url, false, stream_context_create($opts));
    $code = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int)$m[1];
        }
    }
    return [
        'ok'    => ($resp !== false) && $code >= 200 && $code < 300,
        'code'  => $code,
        'body'  => is_string($resp) ? $resp : '',
        'error' => $resp === false ? '请求失败（stream 传输错误）' : '',
    ];
}

function shop_smtp_read($fp) {
    $data = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $data .= $line;

        if (strlen($line) < 4 || $line[3] !== '-') break;
    }
    return $data;
}

function shop_smtp_cmd($fp, $cmd, $expect = [250]) {
    fwrite($fp, $cmd . "\r\n");
    $resp = shop_smtp_read($fp);
    $code = (int)substr($resp, 0, 3);
    return ['code' => $code, 'resp' => $resp, 'ok' => in_array($code, (array)$expect, true)];
}

function shop_smtp_send(array $c, $to, $subject, $body) {
    $host   = trim((string)($c['host'] ?? ''));
    $port   = (int)($c['port'] ?? 465);
    $secure = strtolower(trim((string)($c['secure'] ?? 'ssl')));
    $user   = (string)($c['user'] ?? '');
    $pass   = (string)($c['pass'] ?? '');
    $from   = trim((string)($c['from'] ?? '')) ?: $user;
    $fromName = trim((string)($c['from_name'] ?? ''));

    if ($host === '' || $port <= 0) return ['ok' => false, 'code' => 0, 'error' => 'SMTP 服务器或端口未填写'];
    if ($from === '') return ['ok' => false, 'code' => 0, 'error' => '发件人邮箱未填写'];

    $rcpts = [];
    foreach (preg_split('/[,;，；\s]+/u', (string)$to) as $r) {
        $r = trim($r);
        if ($r !== '' && filter_var($r, FILTER_VALIDATE_EMAIL)) $rcpts[] = $r;
    }
    if (!$rcpts) return ['ok' => false, 'code' => 0, 'error' => '收件人邮箱未填写或格式错误'];

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $ctx = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true],
    ]);
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return ['ok' => false, 'code' => 0, 'error' => "连接 SMTP 失败：$errstr ($errno)"];

    stream_set_timeout($fp, 15);
    $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $hostname = preg_replace('/[^A-Za-z0-9.\-]/', '', $hostname) ?: 'localhost';

    try {
        $greet = shop_smtp_read($fp);
        if ((int)substr($greet, 0, 3) !== 220) {
            fclose($fp);
            return ['ok' => false, 'code' => (int)substr($greet, 0, 3), 'error' => 'SMTP 拒绝连接：' . trim($greet)];
        }

        $r = shop_smtp_cmd($fp, 'EHLO ' . $hostname, [250]);
        if (!$r['ok']) { $r = shop_smtp_cmd($fp, 'HELO ' . $hostname, [250]); }
        if (!$r['ok']) throw new RuntimeException('EHLO 被拒绝：' . trim($r['resp']));

        if ($secure === 'tls') {
            $r = shop_smtp_cmd($fp, 'STARTTLS', [220]);
            if (!$r['ok']) throw new RuntimeException('STARTTLS 被拒绝：' . trim($r['resp']));
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
                throw new RuntimeException('TLS 握手失败');
            }
            shop_smtp_cmd($fp, 'EHLO ' . $hostname, [250]);
        }

        if ($user !== '') {
            $r = shop_smtp_cmd($fp, 'AUTH LOGIN', [334]);
            if (!$r['ok']) throw new RuntimeException('SMTP 不支持 AUTH LOGIN：' . trim($r['resp']));
            $r = shop_smtp_cmd($fp, base64_encode($user), [334]);
            if (!$r['ok']) throw new RuntimeException('SMTP 用户名被拒绝');
            $r = shop_smtp_cmd($fp, base64_encode($pass), [235]);
            if (!$r['ok']) throw new RuntimeException('SMTP 密码/授权码错误：' . trim($r['resp']));
        }

        $r = shop_smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', [250]);
        if (!$r['ok']) throw new RuntimeException('MAIL FROM 被拒绝：' . trim($r['resp']));

        foreach ($rcpts as $rcpt) {
            $r = shop_smtp_cmd($fp, 'RCPT TO:<' . $rcpt . '>', [250, 251]);
            if (!$r['ok']) throw new RuntimeException('收件人被拒绝 ' . $rcpt . '：' . trim($r['resp']));
        }

        $r = shop_smtp_cmd($fp, 'DATA', [354]);
        if (!$r['ok']) throw new RuntimeException('DATA 被拒绝：' . trim($r['resp']));

        $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromHeader = $fromName !== ''
            ? '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>'
            : $from;

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $fromHeader,
            'To: ' . implode(', ', $rcpts),
            'Subject: ' . $subjectEnc,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'X-Mailer: ShopStandalone',
        ];

        $bodyEnc = chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $body)), 76, "\r\n");
        $data = implode("\r\n", $headers) . "\r\n\r\n" . $bodyEnc;

        fwrite($fp, $data . "\r\n.\r\n");
        $resp = shop_smtp_read($fp);
        $code = (int)substr($resp, 0, 3);
        shop_smtp_cmd($fp, 'QUIT', [221]);
        fclose($fp);

        if ($code !== 250) {
            return ['ok' => false, 'code' => $code, 'error' => '发信被拒绝：' . trim($resp)];
        }
        return ['ok' => true, 'code' => $code, 'error' => ''];
    } catch (Throwable $e) {
        @fclose($fp);
        return ['ok' => false, 'code' => 0, 'error' => $e->getMessage()];
    }
}

function shop_push_send_channel(array $ch, $title, $text) {
    $type = strtolower((string)($ch['type'] ?? ''));

    if ($type === 'gotify') {
        $server = rtrim(trim((string)($ch['server'] ?? '')), '/');
        $token  = trim((string)($ch['token'] ?? ''));
        if ($server === '') return ['ok' => false, 'code' => 0, 'resp' => 'Gotify 服务器地址未填写'];
        if ($token === '')  return ['ok' => false, 'code' => 0, 'resp' => 'Gotify 应用 Token 未填写'];
        if (!preg_match('#^https?://#i', $server)) $server = 'https://' . $server;

        $prio = (int)($ch['priority'] ?? 5);
        if ($prio < 0)  $prio = 0;
        if ($prio > 10) $prio = 10;

        $payload = json_encode([
            'title'    => $title,
            'message'  => $text,
            'priority' => $prio,
        ], JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json; charset=UTF-8',
            'X-Gotify-Key: ' . $token,
        ];
        $r = shop_push_http($server . '/message', 'POST', $payload, $headers, 8);
        return [
            'ok'   => $r['ok'],
            'code' => $r['code'],
            'resp' => $r['ok'] ? shop_push_trunc($r['body'], 300) : ($r['error'] ?: shop_push_trunc($r['body'], 300)),
        ];
    }

    if ($type === 'ntfy') {
        $server = rtrim(trim((string)($ch['server'] ?? '')), '/');
        $topic  = trim((string)($ch['topic'] ?? ''));
        if ($server === '' || $topic === '') {
            return ['ok' => false, 'code' => 0, 'resp' => 'ntfy 服务器地址或 topic 未填写'];
        }
        if (!preg_match('#^https?://#i', $server)) $server = 'https://' . $server;

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        $token = trim((string)($ch['token'] ?? ''));
        if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;

        if (trim((string)($ch['title'] ?? '')) !== '') {
            $headers[] = 'X-Title: ' . shop_push_header_safe($ch['title']);
        }
        if (trim((string)($ch['priority'] ?? '')) !== '') {
            $headers[] = 'X-Priority: ' . shop_push_header_safe($ch['priority']);
        }
        if (trim((string)($ch['tags'] ?? '')) !== '') {
            $headers[] = 'X-Tags: ' . shop_push_header_safe($ch['tags']);
        }

        $url = $server . '/' . rawurlencode($topic);
        $r = shop_push_http($url, 'POST', $text, $headers, 6);
        return ['ok' => $r['ok'], 'code' => $r['code'], 'resp' => $r['ok'] ? shop_push_trunc($r['body'], 300) : ($r['error'] ?: shop_push_trunc($r['body'], 300))];
    }

    if ($type === 'email') {
        $r = shop_smtp_send($ch, (string)($ch['to'] ?? ''), $title, $text);
        return ['ok' => (bool)$r['ok'], 'code' => (int)$r['code'], 'resp' => $r['error'] !== '' ? $r['error'] : '发送成功'];
    }

    if ($type === 'webhook') {
        $tpl = (string)($ch['url'] ?? '');
        if (trim($tpl) === '') return ['ok' => false, 'code' => 0, 'resp' => 'Webhook 地址未填写'];

        $vars = [
            'title' => $title,
            'text'  => $text,
            'body'  => $text,
            'message' => $text,
            'code'  => '',
        ];

        $url = shop_push_render($tpl, [
            'title' => rawurlencode($title),
            'text'  => rawurlencode($text),
        ]);

        $vars['title'] = $title;
        $vars['text']  = $text;

        $method = strtoupper(trim((string)($ch['method'] ?? 'POST'))) ?: 'POST';
        $ct     = trim((string)($ch['content_type'] ?? 'application/json'));
        $rawBody = (string)($ch['body_template'] ?? '');
        $body    = shop_push_render($rawBody, $vars);
        $body    = str_replace(['\\n', '\\r'], ["\n", "\r"], $body);

        $headers = [];
        if ($ct !== '') $headers[] = 'Content-Type: ' . $ct;
        foreach (preg_split('/\r\n|\r|\n/', (string)($ch['headers'] ?? '')) as $h) {
            $h = trim($h);
            if ($h !== '' && strpos($h, ':') !== false) $headers[] = $h;
        }

        if ($method === 'GET' && trim($body) === '') {
            $r = shop_push_http($url, 'GET', '', $headers, 6);
        } else {
            $r = shop_push_http($url, $method, $body, $headers, 6);
        }
        return ['ok' => $r['ok'], 'code' => $r['code'], 'resp' => $r['ok'] ? shop_push_trunc($r['body'], 300) : ($r['error'] ?: shop_push_trunc($r['body'], 300))];
    }

    return ['ok' => false, 'code' => 0, 'resp' => '未知的通道类型：' . $type];
}

function shop_push_header_safe($v) {
    return trim(str_replace(["\r", "\n"], ' ', (string)$v));
}

function shop_push_trunc($s, $n = 300) {
    $s = (string)$s;
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n) . '…' : $s;
}

function shop_push_log($orderNo, $event, $channel, $code, $resp, $ok) {
    $db = shop_db();
    if (!$db) return;
    try {
        $st = $db->prepare('INSERT INTO push_log (order_no, event, channel, http_code, response, success, created_at) VALUES (?,?,?,?,?,?,?)');
        $st->execute([
            mb_substr((string)$orderNo, 0, 32),
            mb_substr((string)$event, 0, 16),
            mb_substr((string)$channel, 0, 32),
            (int)$code,
            mb_substr((string)$resp, 0, 500),
            $ok ? 1 : 0,
            date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        error_log('[shop] push log failed: ' . $e->getMessage());
    }
}

function shop_push_log_clear() {
    $db = shop_db();
    if (!$db) return 0;
    try { return (int)$db->exec("DELETE FROM push_log"); }
    catch (Throwable $e) { return 0; }
}

function shop_push_log_count() {
    $db = shop_db();
    if (!$db) return 0;
    try { return (int)$db->query("SELECT COUNT(*) FROM push_log")->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

function shop_push_recent_logs($limit = 50) {
    $db = shop_db();
    if (!$db) return [];
    $limit = max(1, min(200, (int)$limit));
    try {
        return $db->query("SELECT * FROM push_log ORDER BY id DESC LIMIT $limit")->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function shop_push_queue_stats() {
    $out = ['pending' => 0, 'sent' => 0, 'failed' => 0];
    $db = shop_db();
    if (!$db) return $out;
    try {
        foreach ($db->query('SELECT status, COUNT(*) c FROM push_queue GROUP BY status')->fetchAll() as $r) {
            $out[$r['status']] = (int)$r['c'];
        }
    } catch (Throwable $e) {}
    return $out;
}

function shop_push_channels($onlyEnabled = true) {
    $cfg = shop_config();
    $out = [];
    foreach ((array)($cfg['push']['channels'] ?? []) as $ch) {
        if (!is_array($ch)) continue;
        $key = (string)($ch['key'] ?? ($ch['type'] ?? ''));
        if ($key === '') continue;
        if ($onlyEnabled && empty($ch['enable'])) continue;
        $ch['key'] = $key;
        $out[$key] = $ch;
    }
    return $out;
}

function shop_push_dispatch($event, array $order, $ignoreEventFilter = false) {
    $cfg = shop_config();
    if (empty($cfg['push']['enable'])) return;

    $events = (array)($cfg['push']['on_events'] ?? []);
    if (!$ignoreEventFilter && !in_array($event, $events, true)) return;

    $channels = shop_push_channels(true);
    if (!$channels) return;

    $vars  = shop_order_vars($order, $event);
    $text  = shop_push_render($cfg['push']['template'] ?? '{order_no}', $vars);

    $title = shop_push_render((string)($cfg['push']['title'] ?? '【{site}】{event_label}'), $vars);
    if (trim($title) === '') $title = '【' . ($vars['site'] ?: '新订单') . '】' . $vars['event_label'];

    $db = shop_db();
    $queueIds = [];
    if ($db) {
        try {
            $st = $db->prepare('INSERT INTO push_queue (order_no, event, channel, payload, attempts, next_try, status, created_at) VALUES (?,?,?,?,0,?,?,?)');
            foreach ($channels as $key => $ch) {
                $st->execute([
                    mb_substr($vars['order_no'], 0, 32),
                    mb_substr($event, 0, 16),
                    mb_substr($key, 0, 32),
                    $text,
                    time(),
                    'pending',
                    date('Y-m-d H:i:s'),
                ]);
                $queueIds[$key] = (int)$db->lastInsertId();
            }
        } catch (Throwable $e) {
            error_log('[shop] enqueue push failed: ' . $e->getMessage());
        }
    }

    foreach ($channels as $key => $ch) {
        $r = shop_push_send_channel($ch, $title, $text);
        shop_push_log($vars['order_no'], $event, $key, $r['code'], $r['resp'], $r['ok']);

        if ($db && isset($queueIds[$key])) {
            try {
                if ($r['ok']) {
                    $db->prepare('UPDATE push_queue SET status=?, attempts=attempts+1, sent_at=? WHERE id=?')
                       ->execute(['sent', date('Y-m-d H:i:s'), $queueIds[$key]]);
                } else {
                    $backoff = shop_push_backoff();
                    $next = time() + ($backoff[0] ?? 60);
                    $db->prepare('UPDATE push_queue SET status=?, attempts=attempts+1, next_try=?, last_error=? WHERE id=?')
                       ->execute(['pending', $next, mb_substr($r['resp'], 0, 300), $queueIds[$key]]);
                }
            } catch (Throwable $e) {}
        }
    }
}

function shop_push_process_queue($limit = 5, $budgetSec = 6) {
    $db = shop_db();
    if (!$db) return 0;

    $cfg = shop_config();
    if (empty($cfg['push']['enable'])) return 0;

    $channels = shop_push_channels(true);
    if (!$channels) return 0;

    $limit = max(1, min(50, (int)$limit));
    $start = microtime(true);
    $sent  = 0;
    $backoff = shop_push_backoff();
    $maxAttempts = 8;

    try {
        $st = $db->prepare("SELECT * FROM push_queue WHERE status='pending' AND next_try <= ? ORDER BY id ASC LIMIT $limit");
        $st->execute([time()]);
        $rows = $st->fetchAll();
    } catch (Throwable $e) {
        return 0;
    }

    foreach ($rows as $row) {
        if ((microtime(true) - $start) > $budgetSec) break;

        $key = (string)$row['channel'];
        if (!isset($channels[$key])) {

            $db->prepare("UPDATE push_queue SET status='failed', last_error=? WHERE id=?")
               ->execute(['通道已停用', $row['id']]);
            continue;
        }

        $vars = [];
        if ($row['order_no'] !== '') {
            $order = shop_order_get($row['order_no']);
            if ($order) $vars = shop_order_vars($order, (string)$row['event']);
        }
        $site = shop_config();

        if (!$vars) {
            $vars = shop_order_vars(['order_no' => (string)$row['order_no']], (string)$row['event']);
        }
        $title = shop_push_render((string)($site['push']['title'] ?? '【{site}】{event_label}'), $vars);
        if (trim($title) === '') {
            $title = '【' . (($site['page_title'] ?? '') ?: '新订单') . '】' . $vars['event_label'];
        }
        $text  = (string)$row['payload'];

        $r = shop_push_send_channel($channels[$key], $title, $text);
        shop_push_log((string)$row['order_no'], (string)$row['event'], $key, $r['code'], $r['resp'], $r['ok']);

        $attempts = (int)$row['attempts'] + 1;
        try {
            if ($r['ok']) {
                $db->prepare('UPDATE push_queue SET status=?, attempts=?, sent_at=?, last_error=? WHERE id=?')
                   ->execute(['sent', $attempts, date('Y-m-d H:i:s'), '', $row['id']]);
                $sent++;
            } elseif ($attempts >= $maxAttempts) {
                $db->prepare('UPDATE push_queue SET status=?, attempts=?, last_error=? WHERE id=?')
                   ->execute(['failed', $attempts, mb_substr($r['resp'], 0, 300), $row['id']]);
            } else {
                $delay = $backoff[min($attempts - 1, count($backoff) - 1)];
                $db->prepare('UPDATE push_queue SET status=?, attempts=?, next_try=?, last_error=? WHERE id=?')
                   ->execute(['pending', $attempts, time() + $delay, mb_substr($r['resp'], 0, 300), $row['id']]);
            }
        } catch (Throwable $e) {}
    }
    return $sent;
}

function shop_push_requeue_order($orderNo) {
    $db = shop_db();
    if (!$db) return 0;
    $st = $db->prepare("UPDATE push_queue SET status='pending', attempts=0, next_try=? WHERE order_no=? AND status IN ('failed','pending')");
    $st->execute([time(), $orderNo]);
    return $st->rowCount();
}

function shop_push_send_test($channelKey) {
    $channels = shop_push_channels(false);
    if (!isset($channels[$channelKey])) {
        return ['ok' => false, 'msg' => '通道不存在：' . $channelKey, 'detail' => []];
    }
    $ch    = $channels[$channelKey];
    $cfg   = shop_config();
    $dummy = [
        'order_no'      => 'ORD' . date('YmdHis') . 'TEST',
        'pay_code'      => 'TE',
        'items_text'    => '测试商品×1',
        'amount'        => 0.01,
        'customer_name' => '测试客户',
        'wechat'        => 'test_wx',
        'phone'         => '13800000000',
        'mode_label'    => '立即服务',
        'book_time'     => '',
        'remark'        => '这是一条测试推送，收到即代表通道配置正确。',
        'status_label'  => '测试',
        'created_at'    => date('Y-m-d H:i:s'),
    ];
    $vars  = shop_order_vars($dummy, 'test');
    $text  = shop_push_render($cfg['push']['template'] ?? '{order_no}', $vars);

    $title = shop_push_render((string)($cfg['push']['title'] ?? '【{site}】{event_label}'), $vars);
    if (trim($title) === '') $title = '【测试推送】' . ($vars['site'] ?: '商城下单');

    $r = shop_push_send_channel($ch, $title, $text);
    shop_push_log('TEST', 'test', $channelKey, $r['code'], $r['resp'], $r['ok']);

    return [
        'ok'     => (bool)$r['ok'],
        'msg'    => $r['ok'] ? '测试推送已发送，请检查接收端' : ('推送失败：' . $r['resp']),
        'detail' => $r,
    ];
}
