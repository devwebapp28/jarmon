<?php
/**
 * JarMon - bootstrap and front controller.
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', __DIR__);

/* PHP 8.4+/8.5 deprecations from legacy helper libs should not break the UI. */
error_reporting(E_ALL & ~E_DEPRECATED);

require_once APP_PATH . '/helpers.php';
require_once APP_PATH . '/api.php';
require_once APP_PATH . '/ui.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

date_default_timezone_set((string) setting('timezone', 'Asia/Jakarta'));

$config = jm_config();
$installed = jm_is_installed();
$page = preg_replace('/[^a-z0-9\-]/', '', (string) ($_GET['p'] ?? 'dashboard'));
if ($page === '') {
    $page = 'dashboard';
}
$GLOBALS['jm_page'] = $page;

/* --------------------------------------------------------------- installer */

if (!$installed) {
    if ($page === 'setup') {
        if (jm_is_post()) {
            jm_verify_csrf();
            $username = post_str('username');
            $password = post_str('password');
            $confirm = post_str('password_confirm');
            if ($username === '' || strlen($password) < 6) {
                jm_flash('danger', 'Username wajib diisi dan password minimal 6 karakter.');
            } elseif ($password !== $confirm) {
                jm_flash('danger', 'Konfirmasi password tidak cocok.');
            } else {
                $config['installed'] = true;
                $config['admin'] = [
                    'username' => $username,
                    'hash' => password_hash($password, PASSWORD_DEFAULT),
                ];
                if (!empty(jm_config()['packages'])) {
                    $config['packages'] = jm_config()['packages'];
                } else {
                    $config['packages'] = jm_default_packages();
                }
                jm_config_save($config);
                $_SESSION['jm_admin'] = $username;
                $_SESSION['jm_last_seen'] = time();
                jm_log('install', 'JarMon dipasang');
                jm_flash('success', 'JarMon berhasil dipasang. Sekarang tambahkan router MikroTik Anda.');
                redirect(url('routers', ['mode' => 'add']));
            }
        }
        $GLOBALS['jm_flashes'] = jm_flashes();
        $GLOBALS['jm_app'] = jm_config()['app'];
        jm_render_bare('setup');
    }
    redirect(url('setup'));
}

/* ------------------------------------------------------------------- auth */

if ($page === 'login') {
    if (jm_is_logged_in()) {
        redirect(url('dashboard'));
    }
    if (jm_is_post()) {
        jm_verify_csrf();
        $username = post_str('username');
        $password = post_str('password');
        if (jm_login($username, $password)) {
            jm_flash('success', 'Selamat datang kembali, ' . $username . '.');
            redirect(url('dashboard'));
        }
        jm_flash('danger', 'Username atau password salah.');
        redirect(url('login'));
    }
    $GLOBALS['jm_flashes'] = jm_flashes();
    $GLOBALS['jm_app'] = jm_config()['app'];
    jm_render_bare('login');
}

jm_require_login();

if ($page === 'logout') {
    jm_logout();
    session_start();
    jm_flash('info', 'Anda telah keluar.');
    redirect(url('login'));
}

if (jm_is_post()) {
    jm_verify_csrf();
}

/* ---------------------------------------------------------- router choice */

if (isset($_GET['r']) && is_string($_GET['r'])) {
    $slug = preg_replace('/[^a-z0-9\-_]/i', '', $_GET['r']);
    if (isset(jm_routers()[$slug])) {
        $_SESSION['jm_router'] = $slug;
        jm_reset_api();
    }
}

$router = jm_router();
$routers = jm_routers();
$app = jm_config()['app'];
$flashes = jm_flashes();
$GLOBALS['jm_router'] = $router;
$GLOBALS['jm_routers'] = $routers;
$GLOBALS['jm_app'] = $app;
$GLOBALS['jm_flashes'] = $flashes;

/* --------------------------------------------------------------- routes */

$routes = [
    'dashboard' => ['file' => 'dashboard', 'router' => true],
    'users' => ['file' => 'users', 'router' => true],
    'profiles' => ['file' => 'profiles', 'router' => true],
    'active' => ['file' => 'active', 'router' => true],
    'network' => ['file' => 'network', 'router' => true],
    'logs' => ['file' => 'logs', 'router' => true],
    'vouchers' => ['file' => 'vouchers', 'router' => true],
    'reports' => ['file' => 'reports', 'router' => true],
    'traffic' => ['file' => 'traffic', 'router' => true],
    'ppp' => ['file' => 'ppp', 'router' => true],
    'system' => ['file' => 'system', 'router' => true],
    'routers' => ['file' => 'routers', 'router' => false],
    'settings' => ['file' => 'settings', 'router' => false],
    'about' => ['file' => 'about', 'router' => false],
    'print' => ['file' => 'print', 'router' => true, 'bare' => true],
    'report-print' => ['file' => 'report_print', 'router' => true, 'bare' => true],
];

if ($page === 'traffic-data') {
    header('Content-Type: application/json');
    if (!jm_api_ready()) {
        echo json_encode(['ok' => false, 'error' => jm_api_error()]);
        exit;
    }
    $iface = get_str('iface', (string) ($router['interface'] ?? 'ether1'));
    $tx = 0;
    $rx = 0;
    $result = api_comm('/interface/monitor-traffic', ['interface' => $iface, 'once' => '']);
    if (isset($result[0])) {
        $rx = (int) ($result[0]['rx-bits-per-second'] ?? 0);
        $tx = (int) ($result[0]['tx-bits-per-second'] ?? 0);
    }
    echo json_encode([
        'ok' => true,
        'time' => date('H:i:s'),
        'rx' => $rx,
        'tx' => $tx,
        'rx_label' => fmt_bits($rx),
        'tx_label' => fmt_bits($tx),
    ]);
    exit;
}

if ($page === 'pref') {
    header('Content-Type: application/json');
    $allowed = [];
    $theme = get_str('theme');
    $accent = get_str('accent');
    $lang = get_str('lang');
    if (in_array($theme, ['dark', 'light'], true)) {
        $allowed['theme'] = $theme;
    }
    if (preg_match('/^[a-z]+$/', $accent)) {
        $allowed['accent'] = $accent;
    }
    if (preg_match('/^[a-z]{2}$/', $lang)) {
        $allowed['lang'] = $lang;
    }
    $config = jm_config();
    foreach ($allowed as $key => $value) {
        $config['app'][$key] = $value;
    }
    if ($allowed) {
        jm_config_save($config);
    }
    echo json_encode(['ok' => true, 'app' => $config['app']]);
    exit;
}

if ($page === 'logo') {
    if (!$router) {
        http_response_code(404);
        exit;
    }
    $file = data_path('logo-' . $router['slug'] . '.img');
    if (!is_file($file)) {
        $file = ROOT_PATH . '/assets/img/logo-mark.svg';
        header('Content-Type: image/svg+xml');
        readfile($file);
        exit;
    }
    $info = @getimagesize($file);
    if ($info && isset($info['mime'])) {
        header('Content-Type: ' . $info['mime']);
    }
    header('Cache-Control: private, max-age=60');
    readfile($file);
    exit;
}

$route = $routes[$page] ?? null;
if ($route === null) {
    jm_render_error(404, 'Halaman tidak ditemukan', 'Alamat yang Anda buka tidak tersedia di JarMon.');
}

if (!empty($route['router']) && !$router) {
    jm_flash('warning', 'Pilih atau tambahkan router terlebih dahulu.');
    redirect(url('routers', ['mode' => 'add']));
}

if (!empty($route['router']) && !jm_api_ready()) {
    $GLOBALS['jm_offline'] = true;
}

if (!empty($route['bare'])) {
    jm_render_bare($route['file']);
}

jm_render($route['file']);
