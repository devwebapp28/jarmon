<?php
$online = jm_api_ready();
$info = jm_current_router_info();
$resource = $info['resource'];
$clock = $info['clock'];
$board = $info['routerboard'];
$identity = $info['identity'] !== '' ? $info['identity'] : router_label($router);
$users = $online ? jm_users() : [];
$active = $online ? jm_active_users() : [];
$profiles = $online ? jm_profiles() : [];
$servers = $online ? jm_servers() : [];
$summary = $online ? jm_sales_summary() : ['today' => 0, 'month' => 0, 'today_count' => 0, 'month_count' => 0];

$totalUsers = count($users);
$totalActive = count($active);
$profileCounts = [];
$voucherReady = 0;
$expiredCount = 0;
$disabledCount = 0;
foreach ($users as $u) {
    $p = (string) ($u['profile'] ?? '-');
    $profileCounts[$p] = ($profileCounts[$p] ?? 0) + 1;
    $comment = (string) ($u['comment'] ?? '');
    if ((str_starts_with($comment, 'vc-') || str_starts_with($comment, 'up-')) && (($u['uptime'] ?? '') === '' || ($u['uptime'] ?? '') === '0s')) {
        $voucherReady++;
    }
    if (($u['limit-uptime'] ?? '') === '1s') {
        $expiredCount++;
    }
    if (($u['disabled'] ?? '') === 'true') {
        $disabledCount++;
    }
}
arsort($profileCounts);
$topProfiles = array_slice($profileCounts, 0, 5, true);

$cpu = (float) ($resource['cpu-load'] ?? 0);
$memTotal = (float) ($resource['total-memory'] ?? 0);
$memFree = (float) ($resource['free-memory'] ?? 0);
$memUsedPct = $memTotal > 0 ? round((($memTotal - $memFree) / $memTotal) * 100) : 0;
$hddTotal = (float) ($resource['total-hdd-space'] ?? 0);
$hddFree = (float) ($resource['free-hdd-space'] ?? 0);
$hddUsedPct = $hddTotal > 0 ? round((($hddTotal - $hddFree) / $hddTotal) * 100) : 0;

$iface = (string) ($router['interface'] ?? '');
if ($iface === '') {
    $iface = (string) ($servers[0]['interface'] ?? 'ether1');
}
$chartUrl = url('traffic-data', ['iface' => $iface]);

$logEntries = [];
if ($online) {
    $logs = api_comm('/log/print', ['?topics' => 'hotspot,info,debug']);
    $logEntries = array_slice(array_reverse($logs), 0, 7);
}
$recentActive = array_slice($active, 0, 6);

$pageTitle = 'Dasbor';
$activeNav = 'dashboard';
?>
<?php page_header('Dasbor', 'Ringkasan ' . $identity . ' — ' . ($clock['date'] ?? date('d/m/Y')) . ' ' . ($clock['time'] ?? date('H:i:s')), '<a class="btn" href="' . e(url('active')) . '">' . icon('activity') . ' Sesi aktif</a><a class="btn btn-primary" href="' . e(url('vouchers')) . '">' . icon('ticket') . ' Buat voucher</a>'); ?>

<div class="grid grid-4">
    <div class="card kpi">
        <div class="kpi-top"><span class="kpi-label">Sesi hotspot aktif</span><span class="kpi-icon blue"><?= icon('wifi') ?></span></div>
        <div class="kpi-value"><?= fmt_number($totalActive) ?> <small>sesi</small></div>
        <div class="kpi-sub"><span class="dot online"></span> <?= $online ? 'Router terhubung' : 'Router offline' ?> &middot; <?= fmt_number(count($servers)) ?> server</div>
    </div>
    <div class="card kpi">
        <div class="kpi-top"><span class="kpi-label">Total pengguna</span><span class="kpi-icon green"><?= icon('users') ?></span></div>
        <div class="kpi-value"><?= fmt_number($totalUsers) ?> <small>user</small></div>
        <div class="kpi-sub"><?= fmt_number($expiredCount) ?> kedaluwarsa &middot; <?= fmt_number($disabledCount) ?> nonaktif</div>
    </div>
    <div class="card kpi">
        <div class="kpi-top"><span class="kpi-label">Pendapatan hari ini</span><span class="kpi-icon orange"><?= icon('money') ?></span></div>
        <div class="kpi-value" style="font-size:23px"><?= e(money($summary['today'])) ?></div>
        <div class="kpi-sub"><?= fmt_number($summary['today_count']) ?> transaksi tercatat</div>
    </div>
    <div class="card kpi">
        <div class="kpi-top"><span class="kpi-label">Voucher siap jual</span><span class="kpi-icon violet"><?= icon('ticket') ?></span></div>
        <div class="kpi-value"><?= fmt_number($voucherReady) ?> <small>voucher</small></div>
        <div class="kpi-sub"><a href="<?= e(url('vouchers', ['tab' => 'quick'])) ?>">Cetak cepat</a> &middot; <?= fmt_number(count($profiles)) ?> profil</div>
    </div>
</div>

<div class="grid grid-2-1 mt-2">
    <div class="card">
        <div class="card-head">
            <h3><?= icon('area-chart') ?> Traffic <?= e($iface) ?></h3>
            <span class="spacer"></span>
            <div class="chart-legend"><span><i style="background:#38bdf8"></i>RX</span><span><i style="background:#a78bfa"></i>TX</span></div>
        </div>
        <div class="card-body chart-body">
            <div class="chart-live mb-2">
                <div><span>Download (RX)</span><b data-live-rx style="color:#38bdf8">0 bps</b></div>
                <div><span>Upload (TX)</span><b data-live-tx style="color:#a78bfa">0 bps</b></div>
                <div class="spacer"></div>
                <span class="badge badge-neutral"><?= icon('refresh', 'icon-sm') ?> live</span>
            </div>
            <div class="chart-shell fill">
                <canvas data-traffic-chart data-url="<?= e($chartUrl) ?>" data-interval="<?= (int) (($app['refresh'] ?? 20) * 1000 / 2) ?>" data-rx-target="[data-live-rx]" data-tx-target="[data-live-tx]"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h3><?= icon('cpu') ?> Kesehatan router</h3><span class="spacer"></span><span class="badge <?= $online ? 'badge-success' : 'badge-danger' ?>"><?= $online ? 'Online' : 'Offline' ?></span></div>
        <div class="card-body">
            <div class="resource-row">
                <div class="gauge" style="--pct:<?= min(100, $cpu) ?>;--gauge-color:<?= $cpu > 80 ? 'var(--danger)' : ($cpu > 60 ? 'var(--warning)' : 'var(--success)') ?>"><b><?= (int) $cpu ?>%</b></div>
                <div class="meta"><strong>CPU Load</strong><span><?= e($resource['architecture-name'] ?? '-') ?> &middot; <?= e($resource['version'] ?? '-') ?></span></div>
            </div>
            <div class="resource-row">
                <div class="gauge" style="--pct:<?= $memUsedPct ?>;--gauge-color:var(--info)"><b><?= $memUsedPct ?>%</b></div>
                <div class="meta"><strong>Memori</strong><span><?= e(fmt_bytes(max(0, $memTotal - $memFree))) ?> terpakai dari <?= e(fmt_bytes($memTotal)) ?></span></div>
            </div>
            <div class="resource-row">
                <div class="gauge" style="--pct:<?= $hddUsedPct ?>;--gauge-color:#a78bfa"><b><?= $hddUsedPct ?>%</b></div>
                <div class="meta"><strong>Penyimpanan</strong><span><?= e(fmt_bytes(max(0, $hddTotal - $hddFree))) ?> terpakai dari <?= e(fmt_bytes($hddTotal)) ?></span></div>
            </div>
            <div class="divider"></div>
            <div class="keyval"><span>Board</span><strong><?= e($resource['board-name'] ?? ($board['model'] ?? '-')) ?></strong></div>
            <div class="keyval"><span>Uptime</span><strong><?= e(fmt_uptime($resource['uptime'] ?? '-')) ?></strong></div>
            <div class="keyval"><span>RouterOS</span><strong><?= e($resource['version'] ?? '-') ?></strong></div>
        </div>
    </div>
</div>

<div class="grid grid-3 mt-2">
    <div class="card">
        <div class="card-head"><h3><?= icon('money') ?> Pendapatan</h3><a class="spacer text-small" href="<?= e(url('reports')) ?>">Lihat laporan</a></div>
        <div class="card-body">
            <div class="keyval"><span>Hari ini</span><strong><?= e(money($summary['today'])) ?></strong></div>
            <div class="keyval"><span>Transaksi hari ini</span><strong><?= fmt_number($summary['today_count']) ?></strong></div>
            <div class="keyval"><span>Bulan ini</span><strong><?= e(money($summary['month'])) ?></strong></div>
            <div class="keyval"><span>Transaksi bulan ini</span><strong><?= fmt_number($summary['month_count']) ?></strong></div>
            <div class="mt-2"><a class="btn btn-sm btn-block" href="<?= e(url('reports')) ?>"><?= icon('report') ?> Buka laporan penjualan</a></div>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h3><?= icon('users') ?> Pengguna per profil</h3></div>
        <div class="card-body">
            <?php if (!$topProfiles): ?>
                <?= empty_state('user-cog', 'Belum ada profil', 'Buat profil hotspot terlebih dahulu.') ?>
            <?php else: ?>
                <?php foreach ($topProfiles as $name => $count): ?>
                    <div class="mb-2">
                        <div class="row-flex" style="margin-bottom:5px"><span class="text-small" style="font-weight:600"><?= e($name) ?></span><span class="spacer text-small text-muted"><?= fmt_number($count) ?> user</span></div>
                        <div class="progress"><i style="width:<?= $totalUsers > 0 ? max(3, round($count / $totalUsers * 100)) : 0 ?>%"></i></div>
                    </div>
                <?php endforeach; ?>
                <a class="btn btn-sm btn-block mt-2" href="<?= e(url('profiles')) ?>"><?= icon('settings') ?> Kelola profil</a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h3><?= icon('server') ?> Ringkasan hotspot</h3></div>
        <div class="card-body">
            <div class="keyval"><span>Hotspot server</span><strong><?= fmt_number(count($servers)) ?></strong></div>
            <div class="keyval"><span>Profil pengguna</span><strong><?= fmt_number(count($profiles)) ?></strong></div>
            <div class="keyval"><span>Voucher belum terpakai</span><strong><?= fmt_number($voucherReady) ?></strong></div>
            <div class="keyval"><span>Voucher kedaluwarsa</span><strong><?= fmt_number($expiredCount) ?></strong></div>
            <div class="keyval"><span>Pengguna nonaktif</span><strong><?= fmt_number($disabledCount) ?></strong></div>
            <div class="mt-2"><a class="btn btn-sm btn-block" href="<?= e(url('system', ['tab' => 'tools'])) ?>"><?= icon('tools') ?> Router tools</a></div>
        </div>
    </div>
</div>

<div class="grid grid-2 mt-2">
    <div class="card">
        <div class="card-head"><h3><?= icon('activity') ?> Sesi terbaru</h3><a class="spacer text-small" href="<?= e(url('active')) ?>">Semua sesi</a></div>
        <div class="card-body tight">
            <?php if (!$recentActive): ?>
                <?= empty_state('wifi-off', 'Tidak ada sesi aktif', 'Saat pengguna terhubung, sesinya akan muncul di sini.') ?>
            <?php else: ?>
                <div class="table-wrap short">
                    <table class="data-table">
                        <thead><tr><th>User</th><th>Address</th><th class="num">Uptime</th><th class="num">RX / TX</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentActive as $session): ?>
                            <tr>
                                <td><span class="user-link"><?= e($session['user'] ?? '-') ?></span></td>
                                <td class="mono"><?= e($session['address'] ?? '-') ?></td>
                                <td class="num"><?= e(fmt_uptime($session['uptime'] ?? '')) ?></td>
                                <td class="num"><?= e(fmt_bytes($session['bytes-in'] ?? 0, 0)) ?> / <?= e(fmt_bytes($session['bytes-out'] ?? 0, 0)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h3><?= icon('list') ?> Hotspot log</h3><a class="spacer text-small" href="<?= e(url('logs')) ?>">Semua log</a></div>
        <div class="card-body tight">
            <?php if (!$logEntries): ?>
                <?= empty_state('list', 'Log masih kosong', 'Aktivitas hotspot akan tampil di sini.') ?>
            <?php else: ?>
                <div class="log-list">
                    <?php foreach ($logEntries as $entry): ?>
                        <div class="log-item">
                            <time><?= e($entry['time'] ?? '') ?></time>
                            <div class="msg"><?= e($entry['message'] ?? '') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
