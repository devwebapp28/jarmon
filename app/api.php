<?php
/**
 * JarMon - RouterOS API layer and shared repository helpers.
 */

require_once dirname(__DIR__) . '/lib/routeros_api.class.php';

function jm_api(bool $fresh = false): ?RouterosAPI
{
    static $api = false;
    static $routerSlug = null;

    $router = jm_router();
    if (!$router) {
        $GLOBALS['jm_api_error'] = 'Belum ada router yang dipilih.';
        return null;
    }
    if (!$fresh && $api instanceof RouterosAPI && $routerSlug === $router['slug']) {
        return $api;
    }

    $routerSlug = $router['slug'];
    $failKey = 'jm_api_fail_' . $router['slug'];
    if (!$fresh && !empty($_SESSION[$failKey]) && (time() - (int) $_SESSION[$failKey]) < 15) {
        $api = null;
        $GLOBALS['jm_api_error'] = $_SESSION['jm_api_error'] ?? 'Koneksi ke router gagal.';
        return null;
    }

    if ($router['host'] === '' || empty($router['user'])) {
        $GLOBALS['jm_api_error'] = 'Host atau user router belum diisi.';
        return null;
    }

    $API = new RouterosAPI();
    $API->debug = false;
    $API->port = (int) $router['port'];
    $API->ssl = !empty($router['ssl']);
    $API->timeout = 4;
    $API->attempts = 1;
    $API->delay = 1;

    $connected = false;
    $error = '';
    try {
        $connected = (bool) $API->connect($router['host'], (string) $router['user'], (string) $router['password']);
        if (!$connected) {
            $error = trim((string) ($API->error_str ?: 'Tidak dapat terhubung ke API router.'));
        }
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }

    if (!$connected) {
        $_SESSION[$failKey] = time();
        $_SESSION['jm_api_error'] = $error !== '' ? $error : 'Tidak dapat terhubung ke API router.';
        $GLOBALS['jm_api_error'] = $_SESSION['jm_api_error'];
        $api = null;
        return null;
    }

    unset($_SESSION[$failKey], $_SESSION['jm_api_error']);
    $GLOBALS['jm_api_error'] = '';
    $api = $API;
    return $api;
}

function jm_api_ready(): bool
{
    return jm_api() instanceof RouterosAPI;
}

function jm_api_error(): string
{
    return (string) ($GLOBALS['jm_api_error'] ?? '');
}

function jm_reset_api(): void
{
    $router = jm_router();
    if ($router) {
        unset($_SESSION['jm_api_fail_' . $router['slug']], $_SESSION['jm_api_error']);
    }
}

function api_comm(string $path, array $args = []): array
{
    $api = jm_api();
    if (!$api) {
        return [];
    }
    try {
        $result = $api->comm($path, $args);
        return is_array($result) ? $result : [];
    } catch (Throwable $ex) {
        $GLOBALS['jm_api_error'] = $ex->getMessage();
        return [];
    }
}

function api_try(string $path, array $args = []): array
{
    $api = jm_api();
    if (!$api) {
        return ['ok' => false, 'error' => jm_api_error(), 'result' => []];
    }
    try {
        $result = $api->comm($path, $args);
        return ['ok' => true, 'error' => '', 'result' => is_array($result) ? $result : []];
    } catch (Throwable $ex) {
        return ['ok' => false, 'error' => $ex->getMessage(), 'result' => []];
    }
}

function api_count(string $path, array $args = []): int{
    $args['count-only'] = '';
    $result = api_comm($path, $args);
    if (isset($result[0]) && is_numeric($result[0])) {
        return (int) $result[0];
    }
    if (isset($result['ret']) && is_numeric($result['ret'])) {
        return (int) $result['ret'];
    }
    return 0;
}

/* ------------------------------------------------------------- repository */

function jm_profiles(): array
{
    static $profiles = null;
    if ($profiles !== null) {
        return $profiles;
    }
    $list = api_comm('/ip/hotspot/user/profile/print');
    $out = [];
    foreach ($list as $profile) {
        if (!isset($profile['name'])) {
            continue;
        }
        $parsed = jm_parse_onlogin((string) ($profile['on-login'] ?? ''));
        $profile['_exp'] = $parsed;
        $out[] = $profile;
    }
    usort($out, static fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
    $profiles = $out;
    return $out;
}

function jm_profile(string $name): ?array
{
    foreach (jm_profiles() as $profile) {
        if ($profile['name'] === $name) {
            return $profile;
        }
    }
    return null;
}

function jm_parse_onlogin(string $onlogin): array
{
    $result = ['mode' => '0', 'price' => '', 'validity' => '', 'sprice' => '', 'lock' => 'Disable'];
    if ($onlogin === '') {
        return $result;
    }
    $parts = explode(',', $onlogin);
    if (count($parts) < 4) {
        return $result;
    }
    $result['mode'] = trim($parts[1] ?? '0') === '' ? '0' : trim($parts[1]);
    $result['price'] = trim($parts[2] ?? '');
    $result['validity'] = trim($parts[3] ?? '');
    $result['sprice'] = trim($parts[4] ?? '');
    $lock = trim($parts[6] ?? '');
    $result['lock'] = $lock === '' ? 'Disable' : $lock;
    return $result;
}

function jm_servers(): array
{
    $servers = api_comm('/ip/hotspot/print');
    return array_values(array_filter($servers, static fn($s) => isset($s['name'])));
}

function jm_interfaces(): array
{
    $list = api_comm('/interface/print');
    return array_values(array_filter($list, static fn($i) => isset($i['name'])));
}

function jm_users(array $filters = []): array
{
    $args = [];
    foreach ($filters as $key => $value) {
        if ($value !== '' && $value !== null) {
            $args['?' . $key] = (string) $value;
        }
    }
    return api_comm('/ip/hotspot/user/print', $args);
}

function jm_user(string $id): ?array
{
    if ($id === '') {
        return null;
    }
    $result = api_comm('/ip/hotspot/user/print', ['?.id' => $id]);
    if (!$result && substr($id, 0, 1) !== '*') {
        $result = api_comm('/ip/hotspot/user/print', ['?name' => $id]);
    }
    return $result[0] ?? null;
}

function jm_active_users(): array
{
    return api_comm('/ip/hotspot/active/print');
}

function jm_current_router_info(): array
{
    static $info = null;
    if ($info !== null) {
        return $info;
    }
    $info = ['identity' => '', 'resource' => [], 'clock' => [], 'routerboard' => [], 'online' => false];
    if (!jm_api_ready()) {
        return $info;
    }
    $identity = api_comm('/system/identity/print');
    $resource = api_comm('/system/resource/print');
    $clock = api_comm('/system/clock/print');
    $board = api_comm('/system/routerboard/print');
    $info['identity'] = (string) ($identity[0]['name'] ?? '');
    $info['resource'] = $resource[0] ?? [];
    $info['clock'] = $clock[0] ?? [];
    $info['routerboard'] = $board[0] ?? [];
    $info['online'] = !empty($info['identity']) || !empty($info['resource']);
    return $info;
}

/* --------------------------------------------------------------- sales */

function jm_sales_records(?string $dateFilter = null, ?string $monthFilter = null, bool $all = false): array
{
    $records = api_comm('/system/script/print');
    $desiredDate = ($dateFilter !== null && $dateFilter !== '') ? jm_date_iso($dateFilter) : '';
    $desiredMonth = ($monthFilter !== null && $monthFilter !== '') ? strtolower($monthFilter) : '';
    $rows = [];
    foreach ($records as $record) {
        $comment = (string) ($record['comment'] ?? '');
        if ($comment !== 'jarmon' && !$all) {
            continue;
        }
        $name = (string) ($record['name'] ?? '');
        if (!str_contains($name, '-|-')) {
            continue;
        }
        $parts = explode('-|-', $name);
        if (count($parts) < 8) {
            continue;
        }
        $rowDate = (string) ($parts[0] ?? '');
        $iso = jm_date_iso($rowDate);
        if ($desiredDate !== '' && $iso !== $desiredDate) {
            continue;
        }
        if ($desiredMonth !== '') {
            $monthKey = $iso !== '' ? strtolower(date('M', strtotime($iso)) . date('Y', strtotime($iso))) : '';
            if ($monthKey !== $desiredMonth) {
                continue;
            }
        }
        $rows[] = [
            'id' => (string) ($record['.id'] ?? ''),
            'date' => $rowDate,
            'iso_date' => $iso,
            'time' => $parts[1] ?? '',
            'user' => $parts[2] ?? '',
            'price' => (float) ($parts[3] ?? 0),
            'address' => $parts[4] ?? '',
            'mac' => $parts[5] ?? '',
            'validity' => $parts[6] ?? '',
            'profile' => $parts[7] ?? '',
            'comment' => $parts[8] ?? '',
            'source' => (string) ($record['source'] ?? ''),
            'owner' => (string) ($record['owner'] ?? ''),
        ];
    }
    usort($rows, static function ($a, $b) {
        $left = strtotime(($a['iso_date'] ?: '1970-01-01') . ' ' . $a['time']);
        $right = strtotime(($b['iso_date'] ?: '1970-01-01') . ' ' . $b['time']);
        return ($right ?: 0) <=> ($left ?: 0);
    });
    return $rows;
}

function jm_month_key(): string
{
    $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
    return $months[(int) date('n') - 1] . date('Y');
}

function jm_day_key(): string
{
    $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
    return $months[(int) date('n') - 1] . '/' . date('d') . '/' . date('Y');
}

function jm_sales_summary(): array
{
    static $summary = null;
    if ($summary !== null) {
        return $summary;
    }
    $summary = ['today' => 0.0, 'today_count' => 0, 'month' => 0.0, 'month_count' => 0];
    $records = jm_sales_records();
    $today = date('Y-m-d');
    $monthPrefix = date('Y-m');
    foreach ($records as $row) {
        $iso = (string) ($row['iso_date'] ?? '');
        if ($iso === '') {
            continue;
        }
        if ($iso === $today) {
            $summary['today'] += $row['price'];
            $summary['today_count']++;
        }
        if (str_starts_with($iso, $monthPrefix)) {
            $summary['month'] += $row['price'];
            $summary['month_count']++;
        }
    }
    return $summary;
}

/* ---------------------------------------------------------- script builders */

function jm_expiry_onlogin(string $mode, string $price, string $validity, string $sprice, string $lock): string
{
    $lockCmd = $lock === 'Enable' ? '[:local mac $"mac-address"; /ip hotspot user set mac-address=$mac [find where name=$user]]' : '';
    $record = ':local mac $"mac-address"; :local now [/system clock get time]; :local rname ($isoDate . "-|-" . $now . "-|-" . $user . "-|-{{PRICE}}-|-" . $address . "-|-" . $mac . "-|-{{VALIDITY}}-|-{{PROFILE}}-|-" . $comment); /system script add name=$rname source=$isoDate comment="jarmon";';
    $record = in_array($mode, ['remc', 'ntfc'], true) ? str_replace(['{{PRICE}}', '{{VALIDITY}}'], [$price, $validity], $record) : '';

    $template = ':put (",{{MODE}},{{PRICE}},{{VALIDITY}},{{SPRICE}},,{{LOCK}},"); {:local date [/system clock get date]; :local mnames ("jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec"); :local isoDate; :if ([:pick $date 4 5] = "-") do={ :set isoDate $date } else={ :local mi ([:find $mnames [:pick $date 0 3]] + 1); :local ms [:tostr $mi]; :if ([:len $ms] = 1) do={ :set ms ("0" . $ms) }; :set isoDate ([:pick $date 7 11] . "-" . $ms . "-" . [:pick $date 4 6]) }; :local comment [/ip hotspot user get [/ip hotspot user find where name="$user"] comment]; :local ucode [:pic $comment 0 2]; :if ($ucode = "vc" or $ucode = "up" or $comment = "") do={ /sys sch add name="$user" disable=no start-date=$date interval="{{VALIDITY}}"; :delay 3s; :local exp [:tostr [/sys sch get [/sys sch find where name="$user"] next-run]]; :local expDate; :local expTime; :if ([:pick $exp 4 5] = "-") do={ :set expDate [:pick $exp 0 10]; :set expTime [:pick $exp 11 19] } else={ :if ([:pick $exp 3 4] = "/") do={ :local ei ([:find $mnames [:pic $exp 0 3]] + 1); :local ems [:tostr $ei]; :if ([:len $ems] = 1) do={ :set ems ("0" . $ems) }; :set expDate ([:pick $exp 7 11] . "-" . $ems . "-" . [:pick $exp 4 6]); :set expTime [:pic $exp 12 20] } else={ :set expDate $isoDate; :set expTime [:pic $exp 0 8] } }; :if ([:len $expDate] < 10) do={ :set expDate $isoDate }; :if ([:len $expTime] < 8) do={ :set expTime "23:59:59" }; :local expComment ($expDate . " " . $expTime); /ip hotspot user set comment="$expComment" [find where name="$user"]; :delay 1s; /sys sch remove [find where name="$user"]; {{RECORD}} {{LOCKCMD}} }; }';

    if ($mode === '0' || $mode === '') {
        if ($price !== '' && $price !== '0') {
            return ':put (",,' . $price . ',,,noexp,' . $lock . ',")' . ($lockCmd !== '' ? '; ' . $lockCmd : '');
        }
        return '';
    }

    return strtr($template, [
        '{{MODE}}' => $mode,
        '{{PRICE}}' => $price,
        '{{VALIDITY}}' => $validity,
        '{{SPRICE}}' => $sprice,
        '{{LOCK}}' => $lock,
        '{{RECORD}}' => $record,
        '{{LOCKCMD}}' => $lockCmd,
    ]);
}

function jm_profile_onlogin(string $mode, string $price, string $validity, string $sprice, string $lock, string $profileName): string
{
    $script = jm_expiry_onlogin($mode, $price, $validity, $sprice, $lock);
    return $mode !== '0' ? str_replace('{{PROFILE}}', $profileName, $script) : $script;
}

function jm_monitor_script(string $profileName, string $mode): string
{
    $action = $mode === 'ntf' || $mode === 'ntfc' ? 'set limit-uptime=1s' : 'remove';
    $template = ':local parseDate do={ :local mnames ("jan","feb","mar","apr","may","jun","jul","aug","sep","oct","nov","dec"); :local d $1; :if ([:pick $d 4 5] = "-") do={ :return [:tonum ([:pick $d 0 4] . [:pick $d 5 7] . [:pick $d 8 10])] } else={ :local mi ([:find $mnames [:pic $d 0 3]] + 1); :local ms [:tostr $mi]; :if ([:len $ms] = 1) do={ :set ms ("0" . $ms) }; :return [:tonum ([:pick $d 7 11] . $ms . [:pick $d 4 6])] } }; :local timeNum do={ :local h [:tonum [:pick $1 0 2]]; :local m [:tonum [:pick $1 3 5]]; :return (($h * 60) + $m) }; :local today [$parseDate [/system clock get date]]; :local curtime [$timeNum [/system clock get time]]; :foreach i in [/ip hotspot user find where profile="{{PROFILE}}"] do={ :local comment [/ip hotspot user get $i comment]; :local uname [/ip hotspot user get $i name]; :local expired false; :if ([:len $comment] >= 19 and [:pick $comment 4 5] = "-") do={ :local expd [$parseDate [:pick $comment 0 10]]; :local expt [$timeNum [:pick $comment 11 19]]; :if (($expd < $today) or ($expd = $today and $expt <= $curtime)) do={ :set expired true } } else={ :if ([:len $comment] >= 20 and [:pick $comment 3 4] = "/") do={ :local expd [$parseDate [:pic $comment 0 11]]; :local expt [$timeNum [:pic $comment 12 20]]; :if (($expd < $today) or ($expd = $today and $expt <= $curtime)) do={ :set expired true } } }; :if ($expired) do={ /ip hotspot user {{ACTION}} $i; /ip hotspot active remove [find where user=$uname] } }';
    return strtr($template, [
        '{{PROFILE}}' => $profileName,
        '{{ACTION}}' => $action,
    ]);
}

/**
 * Create or repair the scheduler that enforces a profile's expiry mode.
 * start-time must be in the past (00:00:00), otherwise RouterOS may schedule
 * the first run hours later and expired users stay connected.
 */
function jm_sync_monitor(string $profileName, string $mode): array
{
    $existing = api_comm('/system/scheduler/print', ['?name' => $profileName]);
    if ($mode === '0' || $mode === '') {
        foreach ($existing as $scheduler) {
            api_try('/system/scheduler/remove', ['.id' => $scheduler['.id']]);
        }
        return ['ok' => true, 'error' => '', 'action' => 'removed'];
    }
    $payload = [
        'name' => $profileName,
        'start-time' => '00:00:00',
        'interval' => '00:01:00',
        'on-event' => jm_monitor_script($profileName, $mode),
        'disabled' => 'no',
        'comment' => 'JarMon monitor profile ' . $profileName,
    ];
    if ($existing) {
        $payload['.id'] = $existing[0]['.id'];
        $result = api_try('/system/scheduler/set', $payload);
        $result['action'] = 'updated';
    } else {
        $result = api_try('/system/scheduler/add', $payload);
        $result['action'] = 'created';
    }
    return $result;
}

function jm_user_comment(string $raw, string $mode): string
{
    $raw = trim($raw);
    if (str_starts_with($raw, 'vc-') || str_starts_with($raw, 'up-')) {
        return $raw;
    }
    return ($mode === 'vc' ? 'vc-' : 'up-') . $raw;
}

function jm_charsets(): array
{
    return [
        'lower' => 'abcdefghijkmnprstuvwxyz',
        'upper' => 'ABCDEFGHJKLMNPRSTUVWXYZ',
        'upplow' => 'ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnprstuvwxyz',
        'mix' => '23456789abcdefghijkmnprstuvwxyz',
        'mix1' => '23456789ABCDEFGHJKLMNPRSTUVWXYZ',
        'mix2' => '23456789ABCDEFGHJKMNPRSTUVWXYZabcdefghijkmnprstuvwxyz',
        'num' => '23456789',
    ];
}

/**
 * Create hotspot vouchers in bulk. Returns ['items' => [], 'failed' => int, 'error' => string].
 */
function jm_generate_vouchers(array $o): array
{
    $qty = max(1, min(500, (int) ($o['qty'] ?? 1)));
    $server = (string) ($o['server'] ?? 'all');
    $profileName = (string) ($o['profile'] ?? '');
    $mode = ($o['mode'] ?? 'vc') === 'up' ? 'up' : 'vc';
    $length = max(3, min(12, (int) ($o['length'] ?? 6)));
    $prefix = preg_replace('/[^A-Za-z0-9]/', '', (string) ($o['prefix'] ?? ''));
    $charsets = jm_charsets();
    $charset = $charsets[(string) ($o['charset'] ?? 'lower')] ?? $charsets['lower'];
    $timelimit = trim((string) ($o['timelimit'] ?? '0'));
    if ($timelimit === '') {
        $timelimit = '0';
    }
    $bytes = max(0, (int) ($o['bytes'] ?? 0));
    $label = preg_replace('/[^A-Za-z0-9]/', '', (string) ($o['label'] ?? ''));
    $profile = jm_profile($profileName);
    if (!$profile) {
        return ['items' => [], 'failed' => $qty, 'error' => 'Profil tidak ditemukan.'];
    }
    $exp = $profile['_exp'] ?? ['validity' => '', 'price' => '', 'sprice' => ''];
    $commentBase = ($mode === 'vc' ? 'vc-' : 'up-') . ($label !== '' ? $label . '-' : '') . date('mdy') . '-' . random_int(100, 999);
    $items = [];
    $failed = 0;
    $error = '';
    for ($i = 0; $i < $qty; $i++) {
        $username = $prefix . jm_random_string($length, $charset);
        $password = $mode === 'vc' ? $username : jm_random_string(max(4, min(8, $length)), '23456789');
        $payload = [
            'server' => $server,
            'name' => $username,
            'password' => $password,
            'profile' => $profileName,
            'limit-uptime' => $timelimit,
            'limit-bytes-total' => (string) $bytes,
            'comment' => $commentBase,
        ];
        $result = api_try('/ip/hotspot/user/add', $payload);
        if ($result['ok']) {
            $items[] = [
                'username' => $username,
                'password' => $password,
                'profile' => $profileName,
                'validity' => (string) ($exp['validity'] ?? ''),
                'price' => (string) ($exp['price'] ?? ''),
                'sprice' => (string) ($exp['sprice'] ?? ''),
                'time_limit' => $timelimit,
                'data_limit' => (string) $bytes,
                'comment' => $commentBase,
                'mode' => $mode,
            ];
        } else {
            $failed++;
            $error = $result['error'];
        }
    }
    return ['items' => $items, 'failed' => $failed, 'error' => $error];
}

function jm_format_dtm(?string $value): string
{
    return fmt_uptime($value);
}

function jm_limit_bytes_label($bytes): string
{
    $bytes = (float) $bytes;
    if ($bytes <= 0) {
        return '-';
    }
    if ($bytes >= 1073741824) {
        return rtrim(rtrim(number_format($bytes / 1073741824, 2, '.', ''), '0'), '.') . ' GB';
    }
    return rtrim(rtrim(number_format($bytes / 1048576, 2, '.', ''), '0'), '.') . ' MB';
}
