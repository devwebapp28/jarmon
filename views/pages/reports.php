<?php
$dateFilter = get_str('date');
$monthFilter = get_str('month');
$prefix = get_str('prefix');
$idhr = '';
$idbl = '';
if ($dateFilter !== '' && strtotime($dateFilter)) {
    $idhr = strtolower(date('M', strtotime($dateFilter))) . '/' . date('d', strtotime($dateFilter)) . '/' . date('Y', strtotime($dateFilter));
}
if ($monthFilter !== '' && strtotime($monthFilter . '-01')) {
    $idbl = strtolower(date('M', strtotime($monthFilter . '-01'))) . date('Y', strtotime($monthFilter . '-01'));
}
$periodLabel = 'Semua transaksi';
if ($idhr !== '') {
    $periodLabel = date('d M Y', strtotime($dateFilter));
} elseif ($idbl !== '') {
    $periodLabel = date('F Y', strtotime($monthFilter . '-01'));
}

$do = post_str('do');
if ($do === 'clear' && jm_api_ready() && ($idhr !== '' || $idbl !== '')) {
    $targets = jm_sales_records($idhr, $idbl);
    $count = 0;
    foreach ($targets as $target) {
        $result = api_try('/system/script/remove', ['.id' => $target['id']]);
        if ($result['ok']) $count++;
    }
    jm_log('report-clear', $periodLabel . ' (' . $count . ')');
    jm_flash('success', $count . ' data penjualan dihapus untuk periode ' . $periodLabel . '.');
    redirect(url('reports', ['date' => $dateFilter, 'month' => $monthFilter]));
}

$records = jm_api_ready() ? jm_sales_records($idhr, $idbl) : [];
if ($prefix !== '') {
    $records = array_values(array_filter($records, static fn($row) => str_starts_with((string) $row['user'], $prefix)));
}

$total = 0.0;
$profileTotals = [];
$daily = [];
foreach ($records as $row) {
    $total += (float) $row['price'];
    $profile = (string) $row['profile'];
    $profileTotals[$profile] = ($profileTotals[$profile] ?? 0) + (float) $row['price'];
    $day = fmt_date((string) $row['date']);
    if ($day !== '-') {
        $daily[$day] = ($daily[$day] ?? 0) + (float) $row['price'];
    }
}
arsort($profileTotals);
$chartDays = array_slice($daily, -14, 14, true);
if (count($daily) > 14) {
    $chartDays = array_slice($daily, -14, 14, true);
} else {
    ksort($chartDays);
}
$maxDay = $chartDays ? max($chartDays) : 0;
$avg = count($records) ? $total / count($records) : 0;
$topProfile = $profileTotals ? array_key_first($profileTotals) : '-';

if (get_str('export') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="jarmon-report-' . ($idhr !== '' ? str_replace('/', '-', $idhr) : ($idbl !== '' ? $idbl : 'all')) . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['date', 'time', 'user', 'profile', 'validity', 'price', 'comment', 'source', 'owner']);
    foreach ($records as $row) {
        fputcsv($out, [$row['date'], $row['time'], $row['user'], $row['profile'], $row['validity'], $row['price'], $row['comment'], $row['source'], $row['owner']]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Laporan Penjualan';
$activeNav = 'reports';
$queryBase = array_filter(['date' => $dateFilter, 'month' => $monthFilter, 'prefix' => $prefix]);
?>
<?php
page_header('Laporan penjualan', 'Rekap transaksi voucher yang tercatat otomatis saat pengguna pertama kali login.', '<a class="btn" href="' . e(url('reports', $queryBase)) . '">' . icon('refresh') . ' Muat ulang</a><a class="btn" href="' . e(url('reports', $queryBase + ['export' => 'csv'])) . '">' . icon('download') . ' CSV</a><a class="btn btn-primary" href="' . e(url('report-print', $queryBase)) . '" target="_blank">' . icon('printer') . ' Cetak</a>');
?>

<div class="grid grid-4">
    <div class="card kpi"><div class="kpi-top"><span class="kpi-label">Total pendapatan</span><span class="kpi-icon green"><?= icon('money') ?></span></div><div class="kpi-value" style="font-size:23px"><?= e(money($total)) ?></div><div class="kpi-sub"><?= e($periodLabel) ?></div></div>
    <div class="card kpi"><div class="kpi-top"><span class="kpi-label">Transaksi</span><span class="kpi-icon blue"><?= icon('report') ?></span></div><div class="kpi-value"><?= fmt_number(count($records)) ?></div><div class="kpi-sub">Voucher terjual</div></div>
    <div class="card kpi"><div class="kpi-top"><span class="kpi-label">Rata-rata</span><span class="kpi-icon violet"><?= icon('chart') ?></span></div><div class="kpi-value" style="font-size:22px"><?= e(money($avg)) ?></div><div class="kpi-sub">Per transaksi</div></div>
    <div class="card kpi"><div class="kpi-top"><span class="kpi-label">Profil terlaris</span><span class="kpi-icon orange"><?= icon('package') ?></span></div><div class="kpi-value" style="font-size:19px"><?= e($topProfile) ?></div><div class="kpi-sub"><?= e(money($profileTotals[$topProfile] ?? 0)) ?> pendapatan</div></div>
</div>

<div class="grid grid-2-1 mt-2">
    <div class="card">
        <div class="card-head"><h3><?= icon('chart') ?> Tren pendapatan</h3><span class="spacer"></span><span class="text-small text-muted">14 hari terakhir</span></div>
        <div class="card-body">
            <?php if (!$chartDays): ?>
                <?= empty_state('chart', 'Belum ada data', 'Transaksi muncul setelah voucher digunakan pertama kali.') ?>
            <?php else: ?>
                <div class="bar-chart">
                    <?php foreach ($chartDays as $day => $value): ?>
                        <div class="bar-item" title="<?= e(date('d M', strtotime($day))) ?> — <?= e(money($value)) ?>">
                            <div class="bar-val"><?= e(number_format($value, 0, ',', '.')) ?></div>
                            <div class="bar-track"><i style="height:<?= $maxDay > 0 ? max(4, round($value / $maxDay * 100)) : 0 ?>%"></i></div>
                            <div class="bar-label"><?= e(date('d/m', strtotime($day))) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h3><?= icon('filter') ?> Filter laporan</h3></div>
        <div class="card-body">
            <form method="get" action="<?= e(url('reports')) ?>" class="form-grid cols-1">
                <input type="hidden" name="p" value="reports">
                <?php if (current_router_slug()): ?><input type="hidden" name="r" value="<?= e(current_router_slug()) ?>"><?php endif; ?>
                <div class="field"><label for="date">Tanggal</label><input class="input" id="date" type="date" name="date" value="<?= e($dateFilter) ?>"></div>
                <div class="field"><label for="month">Bulan</label><input class="input" id="month" type="month" name="month" value="<?= e($monthFilter) ?>"></div>
                <div class="field"><label for="prefix">Prefix user</label><input class="input" id="prefix" name="prefix" value="<?= e($prefix) ?>" placeholder="misal: VC"></div>
                <div class="field"><label>&nbsp;</label><button class="btn btn-primary" type="submit"><?= icon('search') ?> Tampilkan</button></div>
            </form>
            <div class="divider"></div>
            <div class="keyval"><span>Total pendapatan</span><strong><?= e(money($total)) ?></strong></div>
            <div class="keyval"><span>Jumlah transaksi</span><strong><?= count($records) ?></strong></div>
            <div class="keyval"><span>Periode</span><strong><?= e($periodLabel) ?></strong></div>
            <?php if ($idhr !== '' || $idbl !== ''): ?>
                <form method="post" action="<?= e(url('reports', array_filter(['date' => $dateFilter, 'month' => $monthFilter]))) ?>" class="mt-2" data-confirm="Hapus semua data penjualan untuk periode <?= e($periodLabel) ?>? Tindakan ini tidak bisa dibatalkan.">
                    <?= csrf_field() ?><input type="hidden" name="do" value="clear">
                    <button class="btn btn-sm btn-danger btn-block" type="submit"><?= icon('trash') ?> Hapus data periode ini</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-1-2 mt-2">
    <div class="card">
        <div class="card-head"><h3><?= icon('package') ?> Pendapatan per profil</h3></div>
        <div class="card-body">
            <?php if (!$profileTotals): ?>
                <?= empty_state('package', 'Belum ada data', '') ?>
            <?php else: ?>
                <?php foreach (array_slice($profileTotals, 0, 6, true) as $profile => $value): ?>
                    <div class="mb-2">
                        <div class="row-flex" style="margin-bottom:5px"><span class="text-small" style="font-weight:600"><?= e($profile ?: 'Tanpa profil') ?></span><span class="spacer text-small text-muted"><?= e(money($value)) ?></span></div>
                        <div class="progress"><i style="width:<?= $total > 0 ? max(3, round($value / $total * 100)) : 0 ?>%"></i></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h3><?= icon('money') ?> Detail transaksi</h3><span class="spacer"></span><span class="badge badge-neutral"><?= count($records) ?> baris</span></div>
        <div class="table-toolbar">
            <div class="search-box"><?= icon('search') ?><input class="input input-sm" type="text" placeholder="Cari transaksi..." data-search="#reportTable"></div>
        </div>
        <div class="table-wrap">
            <table class="data-table" id="reportTable" data-table data-page-size="<?= (int) setting('page_size', 25) ?>">
                <thead><tr><th class="sortable">Tanggal</th><th class="sortable">Jam</th><th class="sortable">User</th><th class="sortable">Profil</th><th>Masa berlaku</th><th class="sortable num">Harga</th><th>Komentar</th></tr></thead>
                <tbody>
                <?php foreach ($records as $row): ?>
                    <tr>
                        <td class="nowrap"><?= e(fmt_date((string) $row['date'])) ?></td>
                        <td class="mono"><?= e((string) $row['time']) ?></td>
                        <td><a class="user-link" href="<?= e(url('users', ['mode' => 'edit', 'id' => $row['user']])) ?>"><?= e((string) $row['user']) ?></a></td>
                        <td><span class="badge badge-neutral"><?= e((string) $row['profile']) ?></span></td>
                        <td><?= e((string) $row['validity']) ?></td>
                        <td class="num"><?= e(money((float) $row['price'])) ?></td>
                        <td class="text-small"><?= e((string) $row['comment']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div data-table-empty style="display:none"><?= empty_state('report', 'Tidak ada transaksi', 'Coba ubah filter tanggal atau bulan.') ?></div>
    </div>
</div>

<style>
    .bar-chart { display: flex; align-items: flex-end; gap: 8px; height: 220px; padding-top: 10px; }
    .bar-item { flex: 1; display: flex; flex-direction: column; align-items: center; height: 100%; min-width: 0; }
    .bar-val { font-size: 9.5px; color: var(--muted-2); margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
    .bar-track { flex: 1; width: 100%; display: flex; align-items: flex-end; background: var(--surface-2); border-radius: 6px; overflow: hidden; }
    .bar-track i { display: block; width: 100%; background: linear-gradient(180deg, var(--accent), var(--accent-2)); border-radius: 6px 6px 0 0; transition: height .3s ease; }
    .bar-label { font-size: 9.5px; color: var(--muted-2); margin-top: 6px; white-space: nowrap; }
</style>
