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
$label = 'Semua transaksi';
if ($idhr !== '') $label = date('d F Y', strtotime($dateFilter));
elseif ($idbl !== '') $label = date('F Y', strtotime($monthFilter . '-01'));
$records = jm_api_ready() ? jm_sales_records($idhr, $idbl) : [];
if ($prefix !== '') {
    $records = array_values(array_filter($records, static fn($row) => str_starts_with((string) $row['user'], $prefix)));
}
$total = 0.0;
$profileTotals = [];
foreach ($records as $row) {
    $total += (float) $row['price'];
    $profileTotals[(string) $row['profile']] = ($profileTotals[(string) $row['profile']] ?? 0) + (float) $row['price'];
}
arsort($profileTotals);
$hotspotName = (string) ($router['hotspot_name'] ?? '') ?: (string) ($router['name'] ?? 'Hotspot');
?>
<div class="report-page">
    <div class="print-toolbar">
        <a class="btn btn-sm" href="<?= e(url('reports', array_filter(['date' => $dateFilter, 'month' => $monthFilter, 'prefix' => $prefix]))) ?>"><?= icon('chevron-left') ?> Kembali</a>
        <span class="spacer"></span>
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()"><?= icon('printer') ?> Cetak</button>
    </div>
    <div class="sheet">
        <div class="r-head">
            <div>
                <div class="r-brand">JarMon</div>
                <h1>Laporan Penjualan</h1>
                <p><?= e($hotspotName) ?> &middot; <?= e(router_label($router)) ?></p>
            </div>
            <div class="r-meta">
                <div><span>Periode</span><b><?= e($label) ?></b></div>
                <div><span>Dicetak</span><b><?= date('d/m/Y H:i') ?></b></div>
                <div><span>Oleh</span><b><?= e($_SESSION['jm_admin'] ?? 'admin') ?></b></div>
            </div>
        </div>
        <div class="r-summary">
            <div><span>Total pendapatan</span><b><?= e(money($total)) ?></b></div>
            <div><span>Jumlah transaksi</span><b><?= fmt_number(count($records)) ?></b></div>
            <div><span>Rata-rata</span><b><?= e(money(count($records) ? $total / count($records) : 0)) ?></b></div>
        </div>
        <table class="r-table">
            <thead><tr><th>#</th><th>Tanggal</th><th>Jam</th><th>User</th><th>Profil</th><th>Masa berlaku</th><th class="num">Harga</th><th>Komentar</th></tr></thead>
            <tbody>
            <?php foreach ($records as $index => $row): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= e(fmt_date((string) $row['date'])) ?></td>
                    <td><?= e((string) $row['time']) ?></td>
                    <td><?= e((string) $row['user']) ?></td>
                    <td><?= e((string) $row['profile']) ?></td>
                    <td><?= e((string) $row['validity']) ?></td>
                    <td class="num"><?= e(money((float) $row['price'], false)) ?></td>
                    <td><?= e((string) $row['comment']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$records): ?>
                <tr><td colspan="8" style="text-align:center;padding:24px">Tidak ada transaksi pada periode ini.</td></tr>
            <?php endif; ?>
            </tbody>
            <tfoot><tr><td colspan="6" class="num">Total</td><td class="num"><b><?= e(money($total, false)) ?></b></td><td></td></tr></tfoot>
        </table>
        <?php if ($profileTotals): ?>
            <div class="r-breakdown">
                <h2>Rekap per profil</h2>
                <table>
                    <thead><tr><th>Profil</th><th class="num">Pendapatan</th><th class="num">Porsi</th></tr></thead>
                    <tbody>
                    <?php foreach ($profileTotals as $profile => $value): ?>
                        <tr><td><?= e($profile ?: 'Tanpa profil') ?></td><td class="num"><?= e(money($value, false)) ?></td><td class="num"><?= $total > 0 ? round($value / $total * 100) : 0 ?>%</td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <div class="r-foot">Dibuat otomatis oleh JarMon v<?= JARMON_VERSION ?> &middot; <?= date('Y') ?></div>
    </div>
</div>
<style>
    .report-page { max-width: 1000px; margin: 0 auto; padding: 20px; }
    .sheet { background: #fff; color: #0f172a; padding: 28px 30px; border-radius: 8px; box-shadow: 0 10px 40px -20px rgba(0,0,0,.3); }
    .r-head { display: flex; justify-content: space-between; gap: 20px; border-bottom: 2px solid #0f172a; padding-bottom: 16px; }
    .r-brand { font-weight: 800; letter-spacing: -.03em; color: #4f46e5; font-size: 14px; }
    .r-head h1 { font-size: 24px; margin: 2px 0 4px; }
    .r-head p { color: #64748b; font-size: 12px; margin: 0; }
    .r-meta { display: flex; flex-direction: column; gap: 4px; font-size: 11px; min-width: 170px; }
    .r-meta div { display: flex; justify-content: space-between; gap: 14px; }
    .r-meta span { color: #64748b; }
    .r-summary { display: flex; gap: 12px; margin: 16px 0; }
    .r-summary > div { flex: 1; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; display: flex; flex-direction: column; gap: 2px; }
    .r-summary span { font-size: 10px; text-transform: uppercase; letter-spacing: .08em; color: #64748b; }
    .r-summary b { font-size: 16px; }
    .r-table, .r-breakdown table { width: 100%; border-collapse: collapse; font-size: 11px; }
    .r-table th, .r-table td, .r-breakdown th, .r-breakdown td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
    .r-table th, .r-breakdown th { background: #f1f5f9; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
    .num { text-align: right !important; }
    .r-table tfoot td { background: #f8fafc; }
    .r-breakdown { margin-top: 18px; max-width: 420px; }
    .r-breakdown h2 { font-size: 13px; margin-bottom: 8px; }
    .r-foot { margin-top: 22px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    @media (max-width: 700px) {
        .report-page { padding: 12px; }
        .sheet { padding: 16px; overflow-x: auto; }
        .r-head { flex-direction: column; }
        .r-meta { width: 100%; }
        .r-summary { flex-direction: column; }
        .r-table { min-width: 620px; }
        .r-breakdown { max-width: none; }
    }
    @media print {
        @page { size: A4; margin: 12mm; }
        html, body { background: #fff !important; }
        .print-toolbar { display: none !important; }
        .report-page { padding: 0; max-width: none; }
        .sheet { box-shadow: none; border-radius: 0; padding: 0; overflow: visible; }
        .r-table { min-width: 0; }
        .r-table tr { page-break-inside: avoid; }
    }
</style>
