<?php
/** @var array $app */
/** @var array|null $router */
/** @var array $routers */
/** @var array $flashes */
/** @var bool $offline */
$meta = jm_page_meta();
$pageTitle = $pageTitle ?? $meta[0];
$pageSubtitle = $pageSubtitle ?? $meta[1];
$theme = $app['theme'] ?? 'dark';
$accent = $app['accent'] ?? 'indigo';
$page = $GLOBALS['jm_page'] ?? 'dashboard';
$tab = isset($_GET['tab']) ? preg_replace('/[^a-z0-9\-]/', '', (string) $_GET['tab']) : '';
$tabDefaults = ['network' => 'hosts', 'vouchers' => 'studio', 'ppp' => 'secrets', 'system' => 'scheduler'];
if ($tab === '' && isset($tabDefaults[$page])) {
    $tab = $tabDefaults[$page];
}
$navKey = in_array($page, ['network', 'vouchers', 'ppp', 'system'], true) ? $page . ':' . $tab : $page;
$activeNav = $activeNav ?? $navKey;
$showStatus = $router && !in_array($page, ['settings', 'routers', 'about'], true);
$base = jm_base_url();
$initials = strtoupper(substr((string) ($_SESSION['jm_admin'] ?? 'J'), 0, 1));
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= e($theme) ?>" data-accent="<?= e($accent) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1020">
    <meta name="csrf" content="<?= e(jm_csrf_token()) ?>">
    <title><?= e($pageTitle) ?> &middot; JarMon</title>
    <link rel="icon" href="<?= e($base) ?>/assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/app.css?v=<?= JARMON_VERSION ?>">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('jm-theme');
                var a = localStorage.getItem('jm-accent');
                var s = localStorage.getItem('jm-sidebar');
                if (t) document.documentElement.setAttribute('data-theme', t);
                if (a) document.documentElement.setAttribute('data-accent', a);
                if (s === 'collapsed') document.documentElement.classList.add('sb-collapsed-init');
            } catch (e) {}
        })();
    </script>
</head>
<body class="jm-ready">
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-head">
            <a class="brand" href="<?= e(url('dashboard')) ?>">
                <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a8 8 0 0 1 16 0"/><path d="M7.5 17.5a5 5 0 0 1 9 0"/><circle cx="12" cy="20" r="1"/><path d="M12 4v3"/></svg></span>
                <span class="brand-text"><strong>JarMon</strong><small>Hotspot Manager</small></span>
            </a>
            <button class="sidebar-collapse" type="button" data-sidebar-toggle title="Ciutkan sidebar" aria-label="Ciutkan sidebar"><?= icon('chevron-left', 'icon-sm') ?></button>
        </div>
        <div class="sidebar-scroll">
            <?php foreach (jm_nav() as $group): ?>
                <?php if ($group['label'] !== ''): ?>
                    <div class="nav-group" data-group="<?= e($group['label']) ?>">
                        <div class="nav-label" data-nav-toggle role="button" tabindex="0" aria-expanded="true">
                            <?= icon($group['icon'] ?? 'chevron-right') ?>
                            <span><?= e($group['label']) ?></span>
                            <?= icon('chevron-down', 'chev') ?>
                        </div>
                        <div class="nav-items">
                <?php endif; ?>
                <?php foreach ($group['items'] as $item): ?>
                    <?php
                    $itemKey = $item['key'];
                    $isActive = $activeNav === $itemKey;
                    ?>
                    <a class="nav-item <?= $isActive ? 'active' : '' ?>" href="<?= e(jm_nav_item_url($item)) ?>" title="<?= e($item['text']) ?>">
                        <?= icon($item['icon']) ?>
                        <span><?= e($item['text']) ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if ($group['label'] !== ''): ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <div class="sidebar-foot">
            <?= icon('spark', 'icon-sm') ?>
            <span>JarMon <strong>v<?= JARMON_VERSION ?></strong></span>
        </div>
    </aside>
    <div class="sidebar-backdrop" data-sidebar-close></div>

    <div class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="icon-btn" data-sidebar-toggle type="button" title="Menu"><?= icon('menu') ?></button>
                <span class="text-muted text-small nowrap" style="font-weight:600"><?= e($pageTitle) ?></span>
            </div>
            <div class="topbar-right">
                <?php if ($showStatus): ?>
                    <span class="status-chip" title="<?= e($router['host']) ?>">
                        <span class="dot <?= $offline ? 'offline' : 'online' ?>"></span>
                        <?= $offline ? 'Offline' : 'Terhubung' ?>
                    </span>
                <?php endif; ?>
                <div class="router-switch">
                    <button class="router-pill" data-dropdown="routerMenu" type="button">
                        <?= icon('router') ?>
                        <span><?= e(router_label($router)) ?></span>
                        <?= icon('chevron-down', 'chev') ?>
                    </button>
                    <div class="router-menu" id="routerMenu">
                        <div class="router-menu-head">Router MikroTik</div>
                        <?php if (!$routers): ?>
                            <div class="router-option"><span class="meta"><span>Belum ada router</span><small>Tambahkan router untuk memulai</small></span></div>
                        <?php endif; ?>
                        <?php foreach ($routers as $slug => $item): ?>
                            <a class="router-option <?= ($router && $router['slug'] === $slug) ? 'active' : '' ?>" href="<?= e(url($page === 'print' ? 'dashboard' : $page, array_filter(['tab' => in_array($page, ['network','vouchers','ppp','system'], true) ? $tab : null, 'r' => $slug]))) ?>">
                                <?= icon('router') ?>
                                <span class="meta"><span><?= e($item['name'] ?? $slug) ?></span><small><?= e($item['host'] ?? '') ?>:<?= e($item['port'] ?? 8728) ?></small></span>
                                <?php if ($router && $router['slug'] === $slug): ?><span class="ok"><?= icon('check', 'icon-sm') ?></span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        <div class="router-menu-foot">
                            <a href="<?= e(url('routers')) ?>"><?= icon('settings', 'icon-sm') ?> Kelola router</a>
                            <a href="<?= e(url('routers', ['mode' => 'add'])) ?>"><?= icon('plus', 'icon-sm') ?> Tambah router</a>
                        </div>
                    </div>
                </div>
                <button class="cmd-trigger" data-cmdk type="button">
                    <?= icon('search') ?>
                    <span>Cari menu, halaman...</span>
                    <kbd>Ctrl K</kbd>
                </button>
                <button class="icon-btn" data-theme-toggle type="button" title="Ubah tema">
                    <?= icon('sun') ?>
                </button>
                <div class="user-menu">
                    <button class="icon-btn" data-dropdown="userMenu" type="button" style="width:auto;padding:0 4px;gap:8px" title="<?= e($_SESSION['jm_admin'] ?? 'admin') ?>">
                        <span class="avatar"><?= e($initials) ?></span>
                    </button>
                    <div class="user-menu-panel" id="userMenu">
                        <div class="user-menu-head">
                            <strong><?= e($_SESSION['jm_admin'] ?? 'admin') ?></strong>
                            <small>Administrator JarMon</small>
                        </div>
                        <a href="<?= e(url('settings')) ?>"><?= icon('settings', 'icon-sm') ?> Pengaturan</a>
                        <a href="<?= e(url('system', ['tab' => 'audit'])) ?>"><?= icon('shield', 'icon-sm') ?> Audit log</a>
                        <a href="<?= e(url('about')) ?>"><?= icon('info', 'icon-sm') ?> Tentang JarMon</a>
                        <a class="danger" href="<?= e(url('logout')) ?>"><?= icon('logout', 'icon-sm') ?> Keluar</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="content">
            <?php if ($offline): ?>
                <div class="offline-banner">
                    <?= icon('alert') ?>
                    <div>
                        <strong>Router tidak dapat dihubungi.</strong>
                        <small>Periksa host, port API, user/password, dan pastikan layanan API aktif. <?= e(jm_api_error()) ?></small>
                    </div>
                    <a class="btn btn-sm" style="margin-left:auto" href="<?= e(url('routers', ['mode' => 'edit', 'slug' => $router['slug'] ?? ''])) ?>"><?= icon('edit') ?> Periksa konfigurasi</a>
                </div>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</div>

<div class="toast-stack" id="toastStack">
    <?php foreach ($flashes as $flash): ?>
        <div class="toast <?= e($flash['type']) ?>" data-toast>
            <?= icon($flash['type'] === 'success' ? 'check' : ($flash['type'] === 'danger' ? 'alert' : ($flash['type'] === 'warning' ? 'alert' : 'info'))) ?>
            <div><?= e($flash['message']) ?></div>
            <button type="button" data-toast-close aria-label="Tutup"><?= icon('close', 'icon-sm') ?></button>
        </div>
    <?php endforeach; ?>
</div>

<dialog class="cmdk" id="cmdk">
    <div class="cmdk-search">
        <?= icon('search') ?>
        <input type="text" id="cmdkInput" placeholder="Cari halaman atau aksi..." autocomplete="off">
        <kbd>ESC</kbd>
    </div>
    <div class="cmdk-list" id="cmdkList">
        <?php foreach (jm_nav() as $group): ?>
            <?php if ($group['label'] !== ''): ?><div class="cmdk-group"><?= e($group['label']) ?></div><?php endif; ?>
            <?php foreach ($group['items'] as $item): ?>
                <a class="cmdk-item" href="<?= e(jm_nav_item_url($item)) ?>" data-text="<?= e(strtolower($item['text'] . ' ' . $group['label'])) ?>">
                    <?= icon($item['icon']) ?> <span><?= e($item['text']) ?></span>
                    <?php if ($group['label'] !== ''): ?><span class="hint"><?= e($group['label']) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
    <div class="cmdk-foot"><span><kbd>&uarr;</kbd><kbd>&darr;</kbd> navigasi</span><span><kbd>Enter</kbd> buka</span><span><kbd>Esc</kbd> tutup</span></div>
</dialog>

<script>window.JM_BASE = <?= json_encode($base) ?>; window.JM_PAGE = <?= json_encode($page) ?>; window.JM_REFRESH = <?= (int) ($app['refresh'] ?? 20) ?>;</script>
<script src="<?= e($base) ?>/assets/js/app.js?v=<?= JARMON_VERSION ?>" defer></script>
</body>
</html>
