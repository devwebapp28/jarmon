<?php
/**
 * JarMon - presentation helpers (icons, navigation, rendering).
 */

function icon(string $name, string $class = ''): string
{
    static $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>',
        'user-cog' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><circle cx="19" cy="11" r="2"/><path d="M19 8v1M19 13v1M16.5 9.5l.8.8M21.5 12.7l-.8.8M21.5 9.5l-.8.8M16.5 12.7l.8-.8"/>',
        'wifi' => '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>',
        'wifi-off' => '<path d="M5 12.55a11 11 0 0 1 5.7-2.91M14.6 9.88a11 11 0 0 1 4.48 2.67"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/><line x1="2" y1="2" x2="22" y2="22"/>',
        'activity' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'monitor' => '<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>',
        'laptop' => '<rect x="3" y="5" width="18" height="12" rx="2"/><line x1="2" y1="20" x2="22" y2="20"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'cookie' => '<path d="M21 12a9 9 0 1 1-9-9 3.5 3.5 0 0 0 4 4 3.5 3.5 0 0 0 4 4 3.5 3.5 0 0 0 1 1z"/><circle cx="8" cy="10" r=".6"/><circle cx="12" cy="15" r=".6"/><circle cx="7" cy="15" r=".6"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'list' => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
        'chart' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'area-chart' => '<path d="M3 3v18h18"/><path d="M7 15l4-5 4 3 4-6"/>',
        'ticket' => '<path d="M3 9V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a3 3 0 0 0 0 6v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a3 3 0 0 0 0-6z"/><path d="M13 5v2M13 11v2M13 17v2"/>',
        'printer' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'package' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'money' => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
        'report' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/>',
        'server' => '<rect x="2" y="3" width="20" height="6" rx="2"/><rect x="2" y="15" width="20" height="6" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/>',
        'network' => '<rect x="9" y="2" width="6" height="6" rx="1.5"/><rect x="2" y="16" width="6" height="6" rx="1.5"/><rect x="16" y="16" width="6" height="6" rx="1.5"/><path d="M12 8v4M5 16v-2h14v2"/>',
        'gauge' => '<path d="M12 14l4-4"/><path d="M20.5 17a9 9 0 1 0-17 0"/><circle cx="12" cy="14" r="1.5"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'tools' => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.4 2.4-2.6-2.6 2.4-2.8z"/>',
        'terminal' => '<polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/>',
        'refresh' => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'plus' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'edit' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>',
        'trash' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'power' => '<path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'key' => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="M10.7 12.3L21 2M17 6l3 3M15 8l2 2"/>',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
        'unlock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.9-.6"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8z"/>',
        'chevron-down' => '<polyline points="6 9 12 15 18 9"/>',
        'chevron-left' => '<polyline points="15 18 9 12 15 6"/>',
        'chevron-right' => '<polyline points="9 18 15 12 9 6"/>',
        'close' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'sort' => '<path d="M11 5h10M11 9h7M11 13h4M3 17l3 3 3-3M6 18V4"/>',
        'qr' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14h1M14 20h1M18 20h3v1"/>',
        'filter' => '<polygon points="22 3 2 3 10 12.5 10 19 14 21 14 12.5 22 3"/>',
        'eye' => '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/>',
        'alert' => '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'menu' => '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.14.34.42.62.77.76H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
        'spark' => '<path d="M12 2l2.2 5.8L20 10l-5.8 2.2L12 18l-2.2-5.8L4 10l5.8-2.2z"/>',
        'cpu' => '<rect x="5" y="5" width="14" height="14" rx="2"/><rect x="9" y="9" width="6" height="6" rx="1"/><path d="M9 2v3M15 2v3M9 19v3M15 19v3M2 9h3M2 15h3M19 9h3M19 15h3"/>',
        'memory' => '<rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 7v10M11 7v10M15 7v10M19 7v10"/>',
        'hdd' => '<rect x="3" y="4" width="18" height="16" rx="2"/><line x1="3" y1="14" x2="21" y2="14"/><circle cx="7" cy="18" r="1"/><circle cx="11" cy="18" r="1"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'zap' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="11"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
        'router' => '<rect x="2" y="13" width="20" height="8" rx="2"/><path d="M6 17h.01M10 17h.01"/><path d="M12 10V5M8 5h8"/>',
        'copy' => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'external' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
    ];
    $path = $icons[$name] ?? $icons['info'];
    return '<svg class="icon ' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function jm_nav(): array
{
    return [
        ['label' => '', 'items' => [
            ['key' => 'dashboard', 'page' => 'dashboard', 'icon' => 'dashboard', 'text' => t('nav_dashboard')],
        ]],
        ['label' => t('group_hotspot'), 'icon' => 'wifi', 'items' => [
            ['key' => 'users', 'page' => 'users', 'icon' => 'users', 'text' => t('nav_users')],
            ['key' => 'profiles', 'page' => 'profiles', 'icon' => 'user-cog', 'text' => t('nav_profiles')],
            ['key' => 'active', 'page' => 'active', 'icon' => 'activity', 'text' => t('nav_active')],
            ['key' => 'network:hosts', 'page' => 'network', 'params' => ['tab' => 'hosts'], 'icon' => 'laptop', 'text' => t('nav_hosts')],
            ['key' => 'network:bindings', 'page' => 'network', 'params' => ['tab' => 'bindings'], 'icon' => 'link', 'text' => t('nav_bindings')],
            ['key' => 'network:cookies', 'page' => 'network', 'params' => ['tab' => 'cookies'], 'icon' => 'cookie', 'text' => t('nav_cookies')],
            ['key' => 'network:dhcp', 'page' => 'network', 'params' => ['tab' => 'dhcp'], 'icon' => 'server', 'text' => t('nav_dhcp')],
            ['key' => 'network:interfaces', 'page' => 'network', 'params' => ['tab' => 'interfaces'], 'icon' => 'network', 'text' => t('nav_interfaces')],
            ['key' => 'logs', 'page' => 'logs', 'icon' => 'list', 'text' => t('nav_log')],
        ]],
        ['label' => t('group_voucher'), 'icon' => 'ticket', 'items' => [
            ['key' => 'vouchers', 'page' => 'vouchers', 'icon' => 'ticket', 'text' => t('nav_studio')],
            ['key' => 'vouchers:quick', 'page' => 'vouchers', 'params' => ['tab' => 'quick'], 'icon' => 'zap', 'text' => t('nav_quickprint')],
            ['key' => 'vouchers:packages', 'page' => 'vouchers', 'params' => ['tab' => 'packages'], 'icon' => 'package', 'text' => t('nav_packages')],
        ]],
        ['label' => t('group_report'), 'icon' => 'chart', 'items' => [
            ['key' => 'reports', 'page' => 'reports', 'icon' => 'money', 'text' => t('nav_reports')],
            ['key' => 'traffic', 'page' => 'traffic', 'icon' => 'area-chart', 'text' => t('nav_traffic')],
        ]],
        ['label' => 'PPP', 'icon' => 'shield', 'items' => [
            ['key' => 'ppp:secrets', 'page' => 'ppp', 'params' => ['tab' => 'secrets'], 'icon' => 'key', 'text' => t('nav_ppp_secrets')],
            ['key' => 'ppp:profiles', 'page' => 'ppp', 'params' => ['tab' => 'profiles'], 'icon' => 'settings', 'text' => t('nav_ppp_profiles')],
            ['key' => 'ppp:active', 'page' => 'ppp', 'params' => ['tab' => 'active'], 'icon' => 'activity', 'text' => t('nav_ppp_active')],
        ]],
        ['label' => t('group_system'), 'icon' => 'tools', 'items' => [
            ['key' => 'system:scheduler', 'page' => 'system', 'params' => ['tab' => 'scheduler'], 'icon' => 'clock', 'text' => t('nav_scheduler')],
            ['key' => 'system:tools', 'page' => 'system', 'params' => ['tab' => 'tools'], 'icon' => 'tools', 'text' => t('nav_tools')],
            ['key' => 'system:audit', 'page' => 'system', 'params' => ['tab' => 'audit'], 'icon' => 'shield', 'text' => t('nav_audit')],
        ]],
        ['label' => t('group_admin'), 'icon' => 'settings', 'items' => [
            ['key' => 'routers', 'page' => 'routers', 'icon' => 'server', 'text' => t('nav_routers')],
            ['key' => 'settings', 'page' => 'settings', 'icon' => 'settings', 'text' => t('nav_settings')],
            ['key' => 'about', 'page' => 'about', 'icon' => 'info', 'text' => t('nav_about')],
        ]],
    ];
}

function jm_nav_item_url(array $item): string
{
    $params = $item['params'] ?? [];
    if (current_router_slug() !== null) {
        $params['r'] = current_router_slug();
    }
    return url($item['page'], $params);
}

function jm_page_meta(): array
{
    $meta = [
        'dashboard' => [t('nav_dashboard'), t('page_dashboard_sub')],
        'users' => [t('nav_users'), t('page_users_sub')],
        'profiles' => [t('nav_profiles'), t('page_profiles_sub')],
        'active' => [t('nav_active'), t('page_active_sub')],
        'network' => [t('nav_network'), t('page_network_sub')],
        'logs' => [t('nav_log'), t('page_logs_sub')],
        'vouchers' => [t('nav_studio'), t('page_vouchers_sub')],
        'reports' => [t('nav_reports'), t('page_reports_sub')],
        'traffic' => [t('nav_traffic'), t('page_traffic_sub')],
        'ppp' => ['PPP', t('page_ppp_sub')],
        'system' => [t('group_system'), t('page_system_sub')],
        'routers' => [t('nav_routers'), t('page_routers_sub')],
        'settings' => [t('nav_settings'), t('page_settings_sub')],
        'about' => [t('nav_about'), t('page_about_sub')],
    ];
    $page = $GLOBALS['jm_page'] ?? 'dashboard';
    return $meta[$page] ?? ['JarMon', ''];
}

function page_header(string $title, string $subtitle = '', string $actions = ''): void
{
    echo '<div class="page-header">';
    echo '<div class="page-header-text"><h1>' . e($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p>' . e($subtitle) . '</p>';
    }
    echo '</div>';
    if ($actions !== '') {
        echo '<div class="page-header-actions">' . $actions . '</div>';
    }
    echo '</div>';
}

function empty_state(string $icon, string $title, string $text = ''): string
{
    return '<div class="empty-state">' . icon($icon) . '<h3>' . e($title) . '</h3>' . ($text !== '' ? '<p>' . e($text) . '</p>' : '') . '</div>';
}

function jm_context(): array
{
    return [
        'app' => $GLOBALS['jm_app'] ?? jm_config()['app'],
        'router' => $GLOBALS['jm_router'] ?? jm_router(),
        'routers' => $GLOBALS['jm_routers'] ?? jm_routers(),
        'flashes' => $GLOBALS['jm_flashes'] ?? [],
        'offline' => !empty($GLOBALS['jm_offline']),
    ];
}

function jm_render(string $view, array $vars = []): void
{
    $vars = array_merge(jm_context(), $vars);
    extract($vars, EXTR_SKIP);
    ob_start();
    require ROOT_PATH . '/views/pages/' . $view . '.php';
    $content = ob_get_clean();
    require ROOT_PATH . '/views/layout.php';
    exit;
}

function jm_render_bare(string $view, array $vars = []): void
{
    $vars = array_merge(jm_context(), $vars);
    extract($vars, EXTR_SKIP);
    ob_start();
    require ROOT_PATH . '/views/pages/' . $view . '.php';
    $content = ob_get_clean();
    require ROOT_PATH . '/views/layout_bare.php';
    exit;
}

function jm_render_error(int $code, string $title, string $message = ''): void
{
    http_response_code($code);
    $app = $GLOBALS['jm_app'] ?? jm_config()['app'];
    $content = '<div class="error-page">' . icon('alert') . '<h1>' . $code . '</h1><h2>' . e($title) . '</h2><p>' . e($message) . '</p><a class="btn btn-primary" href="' . e(url('dashboard')) . '">' . icon('dashboard') . ' ' . t('back_home') . '</a></div>';
    require ROOT_PATH . '/views/layout_error.php';
    exit;
}

function jm_batch_history(array $batches, string $emptyText = 'Belum ada batch voucher tersimpan.'): string
{
    if (!$batches) {
        return '<div class="text-small text-muted" style="padding:10px 0">' . e($emptyText) . '</div>';
    }
    $returnTab = get_str('tab', 'studio');
    $html = '<div class="batch-history">';
    foreach ($batches as $token => $batch) {
        $count = (int) ($batch['count'] ?? count($batch['items'] ?? []));
        $tpl = in_array($batch['tpl'] ?? 'default', ['default', 'compact', 'thermal'], true) ? $batch['tpl'] : 'default';
        $qr = !empty($batch['qr']) ? '1' : '0';
        $profile = (string) ($batch['profile'] ?? '');
        $label = (string) ($batch['label'] ?? '');
        $html .= '<div class="batch-row">';
        $html .= '<span class="batch-dot">' . icon('ticket', 'icon-sm') . '</span>';
        $html .= '<div class="batch-meta"><strong>' . $count . ' voucher</strong>';
        $html .= '<small>' . date('d/m H:i', (int) ($batch['created'] ?? time())) . ($profile !== '' ? ' &middot; ' . e($profile) : '') . ($label !== '' ? ' &middot; ' . e($label) : '') . '</small></div>';
        $html .= '<div class="batch-actions">';
        $html .= '<a class="btn btn-sm" href="' . e(url('print', ['batch' => $token, 'tpl' => $tpl, 'qr' => $qr])) . '" target="_blank" title="Cetak ulang">' . icon('printer') . ' Cetak</a>';
        $html .= '<form method="post" action="' . e(url('vouchers', ['tab' => $returnTab])) . '" data-confirm="Hapus riwayat batch ini? Voucher di router tidak terhapus.">';
        $html .= csrf_field() . '<input type="hidden" name="do" value="batch-delete"><input type="hidden" name="token" value="' . e((string) $token) . '">';
        $html .= '<button class="icon-btn" type="submit" title="Hapus riwayat" style="color:var(--danger)">' . icon('trash') . '</button></form>';
        $html .= '</div></div>';
    }
    $html .= '</div>';
    return $html;
}
