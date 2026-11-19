<?php
$do = post_str('do');
if ($do === 'kick' && jm_api_ready()) {
    $id = post_str('id');
    $user = post_str('user');
    $result = api_try('/ip/hotspot/active/remove', ['.id' => $id]);
    jm_log('active-kick', $user);
    jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Sesi ' . $user . ' diputus.' : 'Gagal memutus sesi: ' . $result['error']);
    redirect(url('active', ['server' => get_str('server')]));
}

$serverFilter = get_str('server');
$active = [];
$servers = [];
if (jm_api_ready()) {
    $active = jm_active_users();
    $servers = jm_servers();
    if ($serverFilter !== '') {
        $active = array_values(array_filter($active, static fn($session) => (string) ($session['server'] ?? '') === $serverFilter));
    }
}
$totalIn = 0;
$totalOut = 0;
$uniqueUsers = [];
$serverCounts = [];
foreach ($active as $session) {
    $totalIn += (float) ($session['bytes-in'] ?? 0);
    $totalOut += (float) ($session['bytes-out'] ?? 0);
    $uniqueUsers[(string) ($session['user'] ?? '')] = true;
    $srv = (string) ($session['server'] ?? '-');
    $serverCounts[$srv] = ($serverCounts[$srv] ?? 0) + 1;
}
$refresh = (int) setting('refresh', 20);
$pageTitle = 'Hotspot Aktif';
$activeNav = 'active';
?>
<?php page_header('Hotspot aktif', 'Sesi yang sedang terhubung ke router.', '<a class="btn" href="' . e(url('active', ['server' => $serverFilter])) . '">' . icon('refresh') . ' Muat ulang</a>'); ?>

<div class="grid grid-4">
    <div class="card kpi"><div class="kpi-top"><span class="kpi-label">Total sesi</span><span class="kpi-icon blue"><?= icon('wifi') ?></span></div><div class="kpi-value"><?= fmt_number(count($active)) ?></div><div class="kpi-sub"><?= fmt_number(count($uniqueUsers)) ?> pengguna unik</div></div>
    <div class="card kpi"><div class="kpi-top"><span class="kpi-label">Download</span><span class="kpi-icon green"><?= icon('download') ?></span></div><div class="kpi-value" style="font-size:22px"><?= e(fmt_bytes($totalIn)) ?></div><div class="kpi-sub">Total bytes masuk sesi aktif</div></div>
    <div class="card kpi"><div class="kpi-top"><span class="kpi-label">Upload</span><span class="kpi-icon violet"><?= icon('upload') ?></span></div><div class="kpi-value" style="font-size:22px"><?= e(fmt_bytes($totalOut)) ?></div><div class="kpi-sub">Total bytes keluar sesi aktif</div></div>
    <div class="card kpi"><div class="kpi-top"><span class="kpi-label">Server hotspot</span><span class="kpi-icon orange"><?= icon('server') ?></span></div><div class="kpi-value"><?= fmt_number(count($serverCounts)) ?></div><div class="kpi-sub">Auto refresh <b data-autorefresh-label><?= $refresh ?>s</b></div></div>
</div>

<div class="card mt-2" data-autorefresh="<?= $refresh ?>">
    <div class="table-toolbar">
        <div class="search-box"><?= icon('search') ?><input class="input input-sm" type="text" placeholder="Cari user, IP, MAC..." data-search="#activeTable"></div>
        <form method="get" action="<?= e(url('active')) ?>" class="row-flex">
            <input type="hidden" name="p" value="active">
            <?php if (current_router_slug()): ?><input type="hidden" name="r" value="<?= e(current_router_slug()) ?>"><?php endif; ?>
            <select class="select select-sm" name="server" data-autosubmit style="width:170px">
                <option value="">Semua server</option>
                <?php foreach ($servers as $server): ?>
                    <option value="<?= e($server['name'] ?? '') ?>" <?= $serverFilter === ($server['name'] ?? '') ? 'selected' : '' ?>><?= e($server['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <span class="spacer"></span>
        <span class="badge badge-neutral"><?= icon('clock', 'icon-sm') ?> refresh dalam <span data-autorefresh-label><?= $refresh ?>s</span></span>
    </div>
    <div class="table-wrap">
        <table class="data-table" id="activeTable" data-table data-page-size="<?= (int) setting('page_size', 25) ?>">
            <thead>
            <tr>
                <th class="sortable">Server</th>
                <th class="sortable">User</th>
                <th class="sortable">Address</th>
                <th>MAC</th>
                <th class="sortable num">Uptime</th>
                <th class="sortable num">Sisa waktu</th>
                <th class="sortable num">Bytes in</th>
                <th class="sortable num">Bytes out</th>
                <th>Login by</th>
                <th>Komentar</th>
                <th style="width:80px"></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($active as $session): ?>
                <tr>
                    <td><a href="<?= e(url('active', ['server' => $session['server'] ?? ''])) ?>"><span class="badge badge-neutral"><?= e($session['server'] ?? '-') ?></span></a></td>
                    <td><a class="user-link" href="<?= e(url('users', ['mode' => 'edit', 'id' => $session['user'] ?? ''])) ?>"><?= e($session['user'] ?? '-') ?></a></td>
                    <td class="mono"><?= e($session['address'] ?? '-') ?></td>
                    <td class="mono text-small"><?= e($session['mac-address'] ?? '-') ?></td>
                    <td class="num"><?= e(fmt_uptime($session['uptime'] ?? '')) ?></td>
                    <td class="num"><?= e(fmt_uptime($session['session-time-left'] ?? '')) ?></td>
                    <td class="num"><?= e(fmt_bytes($session['bytes-in'] ?? 0, 0)) ?></td>
                    <td class="num"><?= e(fmt_bytes($session['bytes-out'] ?? 0, 0)) ?></td>
                    <td class="text-small"><?= e($session['login-by'] ?? '-') ?></td>
                    <td class="text-small"><span class="truncate" style="max-width:170px;display:inline-block" title="<?= e($session['comment'] ?? '') ?>"><?= e($session['comment'] ?? '') ?></span></td>
                    <td class="actions-cell">
                        <a class="icon-btn" href="<?= e(url('users', ['mode' => 'edit', 'id' => $session['user'] ?? ''])) ?>" title="Edit user"><?= icon('edit') ?></a>
                        <form method="post" action="<?= e(url('active', ['server' => $serverFilter])) ?>" style="display:inline" data-confirm="Putuskan sesi <?= e($session['user'] ?? '') ?>?">
                            <?= csrf_field() ?><input type="hidden" name="do" value="kick"><input type="hidden" name="id" value="<?= e($session['.id'] ?? '') ?>"><input type="hidden" name="user" value="<?= e($session['user'] ?? '') ?>">
                            <button class="icon-btn" type="submit" title="Putuskan" style="color:var(--danger)"><?= icon('power') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div data-table-empty style="display:none"><?= empty_state('wifi-off', 'Tidak ada sesi aktif', 'Sesi pengguna yang terhubung akan muncul di sini.') ?></div>
</div>
