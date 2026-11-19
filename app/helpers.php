<?php
/**
 * JarMon - MikroTik Hotspot Manager
 * Core helper functions.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

const JARMON_VERSION = '1.0.0';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function jm_base_url(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir = rtrim(str_replace('/index.php', '', $script), '/');
    return $dir === '' ? '' : $dir;
}

function url(string $path = '', array $params = []): string
{
    $base = jm_base_url() . '/index.php';
    $query = [];
    if ($path !== '') {
        $query['p'] = $path;
    }
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $query[$key] = $value;
    }
    return $base . ($query ? '?' . http_build_query($query) : '');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function current_router_slug(): ?string
{
    if (isset($_GET['r']) && is_string($_GET['r'])) {
        return preg_replace('/[^a-z0-9\-_]/i', '', $_GET['r']);
    }
    return $_SESSION['jm_router'] ?? null;
}

function get_str(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    if (is_array($value)) {
        return $default;
    }
    return trim((string) $value);
}

function post_str(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    if (is_array($value)) {
        return $default;
    }
    return trim((string) $value);
}

function post_int(string $key, int $default = 0): int
{
    return isset($_POST[$key]) && is_numeric($_POST[$key]) ? (int) $_POST[$key] : $default;
}

function post_array(string $key): array
{
    $value = $_POST[$key] ?? [];
    return is_array($value) ? array_values(array_filter(array_map('strval', $value))) : [];
}

function jm_is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function jm_csrf_token(): string
{
    if (empty($_SESSION['jm_csrf'])) {
        $_SESSION['jm_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['jm_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(jm_csrf_token()) . '">';
}

function jm_verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals(jm_csrf_token(), $token)) {
        http_response_code(419);
        jm_render_error(419, 'Sesi formulir kedaluwarsa', 'Muat ulang halaman lalu coba lagi.');
    }
}

/* ---------------------------------------------------------------- config */

function data_path(string $file = ''): string
{
    $base = dirname(__DIR__) . '/data';
    if (!is_dir($base)) {
        @mkdir($base, 0775, true);
    }
    return $file === '' ? $base : $base . '/' . $file;
}

function config_file(): string
{
    return data_path('config.json');
}

function jm_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $file = config_file();
    $defaults = [
        'version' => 1,
        'installed' => false,
        'admin' => ['username' => 'admin', 'hash' => ''],
        'app' => [
            'lang' => 'id',
            'theme' => 'dark',
            'accent' => 'indigo',
            'currency' => 'Rp',
            'timezone' => 'Asia/Jakarta',
            'refresh' => 20,
            'page_size' => 25,
            'idle_timeout' => 30,
            'company' => 'JarMon',
        ],
        'routers' => [],
        'packages' => [],
    ];
    if (!is_file($file)) {
        $config = $defaults;
        return $config;
    }
    $raw = json_decode((string) file_get_contents($file), true);
    if (!is_array($raw)) {
        $raw = [];
    }
    $config = array_replace_recursive($defaults, $raw);
    if (empty($config['packages'])) {
        $config['packages'] = jm_default_packages();
    }
    return $config;
}

function jm_default_packages(): array
{
    $base = [
        'profile' => '',
        'qty' => 10,
        'price' => 0,
        'validity' => '',
        'time_limit' => '',
        'data_mb' => 0,
        'usermode' => 'vc',
        'prefix' => 'VC',
        'length' => 6,
        'charset' => 'mix',
        'tpl' => 'default',
        'qr' => '1',
    ];
    return [
        array_replace($base, ['id' => 'pkg-1h', 'name' => 'Paket 1 Jam', 'price' => 2000, 'validity' => '1h', 'time_limit' => '1h', 'comment' => '1H']),
        array_replace($base, ['id' => 'pkg-1d', 'name' => 'Paket 1 Hari', 'price' => 5000, 'validity' => '1d', 'time_limit' => '1d', 'comment' => '1D']),
        array_replace($base, ['id' => 'pkg-7d', 'name' => 'Paket 7 Hari', 'price' => 25000, 'validity' => '7d', 'time_limit' => '7d', 'comment' => '7D']),
        array_replace($base, ['id' => 'pkg-30d', 'name' => 'Paket 30 Hari', 'price' => 75000, 'validity' => '30d', 'time_limit' => '30d', 'comment' => '30D']),
    ];
}

function jm_config_save(array $config): bool
{
    $file = config_file();
    if (is_file($file)) {
        @copy($file, data_path('config.bak.json'));
    }
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $saved = file_put_contents($file, $json, LOCK_EX) !== false;
    if ($saved) {
        @chmod($file, 0600);
    }
    return $saved;
}

function jm_set(string $path, $value): void
{
    $config = jm_config();
    $segments = explode('.', $path);
    $ref = &$config;
    foreach ($segments as $segment) {
        if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
            $ref[$segment] = [];
        }
        $ref = &$ref[$segment];
    }
    $ref = $value;
    jm_config_save($config);
}

function setting(string $key, $default = null)
{
    $app = jm_config()['app'];
    return array_key_exists($key, $app) ? $app[$key] : $default;
}

function t(string $key, array $replace = []): string
{
    static $strings = null;
    if ($strings === null) {
        $lang = setting('lang', 'id');
        $file = dirname(__DIR__) . '/lang/' . preg_replace('/[^a-z]/', '', $lang) . '.php';
        $strings = is_file($file) ? require $file : [];
    }
    $text = $strings[$key] ?? $key;
    foreach ($replace as $search => $value) {
        $text = str_replace(':' . $search, (string) $value, $text);
    }
    return $text;
}

/* ---------------------------------------------------------------- crypto */

function jm_secret_key(): string
{
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $file = data_path('secret.key');
    if (!is_file($file) || filesize($file) < 16) {
        file_put_contents($file, bin2hex(random_bytes(32)), LOCK_EX);
        @chmod($file, 0600);
    }
    $key = hash('sha256', (string) file_get_contents($file), true);
    return $key;
}

function jm_encrypt(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', jm_secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        return '';
    }
    return base64_encode($iv . $tag . $cipher);
}

function jm_decrypt(?string $payload): string
{
    if (!$payload) {
        return '';
    }
    $raw = base64_decode($payload, true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', jm_secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? '' : $plain;
}

/* ------------------------------------------------------------------ auth */

function jm_is_logged_in(): bool
{
    return !empty($_SESSION['jm_admin']);
}

function jm_is_installed(): bool
{
    $config = jm_config();
    return !empty($config['installed']) && !empty($config['admin']['hash']);
}

function jm_login(string $username, string $password): bool
{
    $admin = jm_config()['admin'];
    if (!hash_equals((string) $admin['username'], $username)) {
        return false;
    }
    if (!password_verify($password, (string) $admin['hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['jm_admin'] = $username;
    $_SESSION['jm_last_seen'] = time();
    jm_log('login', 'Admin masuk');
    return true;
}

function jm_logout(): void
{
    jm_log('logout', 'Admin keluar');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function jm_require_login(): void
{
    if (!jm_is_logged_in()) {
        redirect(url('login'));
    }
    $timeout = (int) setting('idle_timeout', 30);
    if ($timeout > 0 && !empty($_SESSION['jm_last_seen'])) {
        if (time() - (int) $_SESSION['jm_last_seen'] > $timeout * 60) {
            jm_logout();
            session_start();
            jm_flash('warning', 'Sesi berakhir karena tidak ada aktivitas. Silakan masuk kembali.');
            redirect(url('login'));
        }
    }
    $_SESSION['jm_last_seen'] = time();
}

/* ----------------------------------------------------------------- flash */

function jm_flash(string $type, string $message): void
{
    $_SESSION['jm_flashes'][] = ['type' => $type, 'message' => $message];
}

function jm_flashes(): array
{
    $items = $_SESSION['jm_flashes'] ?? [];
    unset($_SESSION['jm_flashes']);
    return $items;
}

/* ------------------------------------------------------------------ logs */

function jm_log(string $action, string $detail = '', string $target = ''): void
{
    $entry = [
        'time' => date('Y-m-d H:i:s'),
        'user' => $_SESSION['jm_admin'] ?? 'system',
        'action' => $action,
        'detail' => mb_substr($detail, 0, 300),
        'router' => $target !== '' ? $target : (string) ($_SESSION['jm_router'] ?? ''),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ];
    @file_put_contents(data_path('audit.log'), json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function jm_audit_read(int $limit = 300): array
{
    $file = data_path('audit.log');
    if (!is_file($file)) {
        return [];
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return [];
    }
    $lines = array_slice($lines, -$limit);
    $rows = [];
    foreach (array_reverse($lines) as $line) {
        $row = json_decode($line, true);
        if (is_array($row)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/* -------------------------------------------------------------- settings */

function jm_routers(): array
{
    return jm_config()['routers'];
}

function jm_router(?string $slug = null): ?array
{
    $routers = jm_routers();
    if ($slug === null) {
        $slug = current_router_slug();
        if ($slug === null || !isset($routers[$slug])) {
            foreach ($routers as $key => $router) {
                $slug = $key;
                break;
            }
        }
    }
    if ($slug === null || !isset($routers[$slug])) {
        return null;
    }
    $router = $routers[$slug];
    $router['slug'] = $slug;
    $router['password'] = jm_decrypt($router['password_enc'] ?? '');
    if (!isset($router['port']) || (int) $router['port'] <= 0) {
        $router['port'] = 8728;
    }
    $router['host'] = trim((string) ($router['host'] ?? ''));
    return $router;
}

function jm_router_save(string $slug, array $data): void
{
    $config = jm_config();
    $config['routers'][$slug] = $data;
    jm_config_save($config);
}

function jm_router_delete(string $slug): void
{
    $config = jm_config();
    unset($config['routers'][$slug]);
    jm_config_save($config);
    $logo = data_path('logo-' . $slug . '.img');
    if (is_file($logo)) {
        @unlink($logo);
    }
}

function jm_slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');
    return $text !== '' ? $text : 'router-' . random_int(100, 999);
}

/* --------------------------------------------------------------- format */

function money($value, bool $withCurrency = true): string
{
    $currency = (string) setting('currency', 'Rp');
    $value = (float) $value;
    $indo = in_array(strtolower($currency), ['rp', 'rp.', 'idr', 'idr.'], true);
    $formatted = $indo ? number_format($value, 0, ',', '.') : number_format($value, 2, '.', ',');
    return $withCurrency ? $currency . ' ' . $formatted : $formatted;
}

function fmt_bytes($size, int $decimals = 2): string
{
    $size = (float) $size;
    $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, $decimals) . ' ' . $units[$i];
}

function fmt_bits($bits): string
{
    $bits = (float) $bits;
    $units = ['bps', 'kbps', 'Mbps', 'Gbps', 'Tbps'];
    $i = 0;
    while ($bits >= 1000 && $i < count($units) - 1) {
        $bits /= 1000;
        $i++;
    }
    return round($bits, $i === 0 ? 0 : 2) . ' ' . $units[$i];
}

function fmt_uptime(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '' || $value === '0s') {
        return '-';
    }
    $parts = [];
    if (preg_match('/(\d+)w/', $value, $m)) {
        $parts[] = $m[1] . 'w';
    }
    if (preg_match('/(\d+)d/', $value, $m)) {
        $parts[] = $m[1] . 'd';
    }
    if (preg_match('/(\d+)h/', $value, $m)) {
        $parts[] = $m[1] . 'h';
    }
    if (preg_match('/(\d+)m(?!s)/', $value, $m)) {
        $parts[] = $m[1] . 'm';
    }
    if (preg_match('/(\d+)s/', $value, $m)) {
        $parts[] = $m[1] . 's';
    }
    return $parts ? implode(' ', $parts) : $value;
}

function jm_date_iso(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }
    $months = ['jan' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04', 'may' => '05', 'jun' => '06', 'jul' => '07', 'aug' => '08', 'sep' => '09', 'oct' => '10', 'nov' => '11', 'dec' => '12'];
    if (preg_match('#^([a-z]{3})/(\d{1,2})/(\d{4})#i', $value, $m)) {
        $month = $months[strtolower($m[1])] ?? '01';
        return $m[3] . '-' . $month . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
    }
    return '';
}

function fmt_date(?string $value): string
{
    $iso = jm_date_iso($value);
    if ($iso !== '') {
        return $iso;
    }
    return trim((string) $value) !== '' ? trim((string) $value) : '-';
}

function jm_expiry_comment_pattern(): string
{
    return '#^(?:[a-z]{3}/\d{1,2}/\d{4}|\d{4}-\d{2}-\d{2}) \d{2}:\d{2}:\d{2}\s*#i';
}

function jm_is_expiry_comment(string $comment): bool
{
    return (bool) preg_match(jm_expiry_comment_pattern(), $comment);
}

function jm_strip_expiry_comment(string $comment): string
{
    return (string) preg_replace(jm_expiry_comment_pattern(), '', $comment);
}

function fmt_number($value): string
{
    return number_format((float) $value);
}

function safe_filename(string $value): string
{
    return preg_replace('/[^A-Za-z0-9._-]+/', '-', $value);
}

function jm_random_string(int $length, string $charset = 'abcdefghijkmnprstuvwxyz23456789'): string
{
    $chars = preg_split('//u', $charset, -1, PREG_SPLIT_NO_EMPTY);
    $count = count($chars);
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        $result .= $chars[random_int(0, $count - 1)];
    }
    return $result;
}

function jm_batches_file(): string
{
    return data_path('batches.json');
}

function jm_batches_load(): array
{
    $file = jm_batches_file();
    if (!is_file($file)) {
        return [];
    }
    $raw = json_decode((string) file_get_contents($file), true);
    if (!is_array($raw) || !isset($raw['batches']) || !is_array($raw['batches'])) {
        return [];
    }
    return $raw['batches'];
}

function jm_batches_write(array $batches): bool
{
    $json = json_encode(['version' => 1, 'batches' => $batches], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $file = jm_batches_file();
    $saved = file_put_contents($file, $json, LOCK_EX) !== false;
    if ($saved) {
        @chmod($file, 0600);
    }
    return $saved;
}

/**
 * Persist a voucher batch on disk so credentials survive reloads,
 * session expiry, theme/settings changes, and logout.
 */
function jm_store_batch(array $items, array $meta = []): string
{
    $token = bin2hex(random_bytes(6));
    $first = $items[0] ?? [];
    $batch = [
        'token' => $token,
        'created' => time(),
        'router' => current_router_slug(),
        'admin' => $_SESSION['jm_admin'] ?? '',
        'count' => count($items),
        'profile' => (string) ($meta['profile'] ?? ($first['profile'] ?? '')),
        'label' => (string) ($meta['label'] ?? ($first['comment'] ?? '')),
        'mode' => (string) ($meta['mode'] ?? ($first['mode'] ?? 'vc')),
        'tpl' => (string) ($meta['tpl'] ?? 'default'),
        'qr' => (string) ($meta['qr'] ?? '1') === '1',
        'items' => $items,
    ];
    $batches = jm_batches_load();
    $batches = [$token => $batch] + $batches;
    if (count($batches) > 60) {
        $batches = array_slice($batches, 0, 60, true);
    }
    jm_batches_write($batches);
    $_SESSION['jm_batches'][$token] = $batch;
    if (count($_SESSION['jm_batches']) > 10) {
        $_SESSION['jm_batches'] = array_slice($_SESSION['jm_batches'], -10, 10, true);
    }
    return $token;
}

function jm_get_batch(string $token): ?array
{
    if ($token === '') {
        return null;
    }
    $batches = jm_batches_load();
    if (isset($batches[$token])) {
        return $batches[$token];
    }
    $session = $_SESSION['jm_batches'][$token] ?? null;
    return is_array($session) ? $session : null;
}

function jm_batches_for_router(?string $slug = null): array
{
    $batches = jm_batches_load();
    $out = [];
    foreach ($batches as $token => $batch) {
        if ($slug !== null && ($batch['router'] ?? null) !== $slug) {
            continue;
        }
        $out[$token] = $batch;
    }
    return $out;
}

function jm_batch_delete(string $token): void
{
    $batches = jm_batches_load();
    unset($batches[$token]);
    jm_batches_write($batches);
    unset($_SESSION['jm_batches'][$token]);
}

/* --------------------------------------------------------------- router labels */

function router_label(?array $router): string
{
    if (!$router) {
        return 'Tanpa Router';
    }
    $name = trim((string) ($router['name'] ?? ''));
    return $name !== '' ? $name : (string) $router['slug'];
}
