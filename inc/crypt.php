<?php

if (!defined('IN_CRYPT')) {
    die('Access denied');
}

require_once __DIR__ . '/../secret/config.secret.php';

function validateKey() {
    try {
        $key = hex2bin(AES_KEY);
        $iv = hex2bin(AES_IV);
        return strlen($key) === 32 && strlen($iv) === 16;
    } catch (Exception $e) {
        return false;
    }
}

function json_encrypt($data) {
    if (!validateKey()) {
        error_log('Invalid AES key or IV configuration');
        return json_encode($data);
    }

    $jsonStr = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($jsonStr === false) {
        error_log('Failed to encode JSON');
        return '';
    }

    $key = hex2bin(AES_KEY);
    $iv = hex2bin(AES_IV);

    $encrypted = openssl_encrypt($jsonStr, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($encrypted);
}

function json_decrypt($ciphertext) {
    if (!validateKey()) {
        error_log('Invalid AES key or IV configuration');
        $decoded = json_decode($ciphertext, true);
        return is_array($decoded) ? $decoded : [];
    }

    try {
        $key = hex2bin(AES_KEY);
        $iv = hex2bin(AES_IV);

        $encrypted = base64_decode($ciphertext);
        if ($encrypted === false) {

            $decoded = json_decode($ciphertext, true);
            return is_array($decoded) ? $decoded : [];
        }

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($decrypted === false) {

            $decoded = json_decode($ciphertext, true);
            return is_array($decoded) ? $decoded : [];
        }

        return json_decode($decrypted, true) ?: [];
    } catch (Exception $e) {
        error_log('Decryption error: ' . $e->getMessage());
        $decoded = json_decode($ciphertext, true);
        return is_array($decoded) ? $decoded : [];
    }
}

function str_encrypt($plain) {
    $wrapped = json_encrypt(['v' => (string)$plain]);
    return is_string($wrapped) ? $wrapped : '';
}

function str_decrypt($cipher) {
    $a = json_decrypt((string)$cipher);
    return (is_array($a) && array_key_exists('v', $a)) ? (string)$a['v'] : '';
}

function saveEncryptedData($filePath, $data) {
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $encrypted = json_encrypt($data);
    if (empty($encrypted)) {
        error_log('Failed to encrypt data for: ' . $filePath);
        return false;
    }

    $result = file_put_contents($filePath, $encrypted, LOCK_EX);

    if ($result !== false) {
        chmod($filePath, 0600);
    }

    return $result !== false;
}

function loadEncryptedData($filePath, $default = []) {
    if (!file_exists($filePath) || filesize($filePath) == 0) {
        return $default;
    }

    $content = file_get_contents($filePath);
    if (empty($content)) {
        return $default;
    }

    $decrypted = json_decrypt($content);

    if (empty($decrypted)) {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return $decrypted ?: $default;
}
?>