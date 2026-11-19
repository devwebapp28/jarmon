<?php
$interfaces = jm_api_ready() ? jm_interfaces() : [];
$iface = get_str('iface', (string) ($router['interface'] ?? ''));
if ($iface === '' && $interfaces) {
    $iface = (string) ($interfaces[0]['name'] ?? '');
}
$selected = null;
foreach ($interfaces as $interface) {
    if (($interface['name'] ?? '') === $iface) {
        $selected = $interface;
        break;
    }
}
$running = ($selected['running'] ?? '') === 'true';
$pollUrl = url('traffic-data', ['iface' => $iface]);
$refresh = max(3, (int) setting('refresh', 20));
$pageTitle = 'Traffic Monitor';
$activeNav = 'traffic';
?>
<?php page_header('Traffic monitor', 'Pantau lalu lintas antarmuka router secara langsung.', '<a class="btn" href="' . e(url('traffic', ['iface' => $iface])) . '">' . icon('refresh') . ' Muat ulang</a><a class="btn" href="' . e(url('network', ['tab' => 'interfaces'])) . '">' . icon('network') . ' Interfaces</a>'); ?>

<div class="grid grid-4">
    <div class="card kpi"><div class="kpi-top"><span class="kpi-label">Download</span><span class="kpi-icon blue"><?= icon('download') ?></span></div><div class="kpi-value" style="font-size:23px" data-live-rx>0 bps</div><div class="kpi-sub">RX saat ini</div></div>
    <div class="card kpi"><div class="kpi-top"><span class="kpi-label">Upload</span><span class="kpi-icon violet"><?= icon('upload') ?></span></div><div class="kpi-value" style="font-size:23px" data-live-tx>0 bps</div><div class="kpi-sub">TX saat ini</div></div>
    <div class="card kpi"><div class="kpi-top"><span class="kpi-label">Total RX</span><span class="kpi-icon green"><?= icon('area-chart') ?></span></div><div class="kpi-value" style="font-size:21px"><?= e(fmt_bytes($selected['rx-byte'] ?? 0)) ?></div><div class="kpi-sub">Sejak interface aktif</div></div>
    <div class="card kpi"><div class="kpi-top"><span class="kpi-label">Total TX</span><span class="kpi-icon orange"><?= icon('area-chart') ?></span></div><div class="kpi-value" style="font-size:21px"><?= e(fmt_bytes($selected['tx-byte'] ?? 0)) ?></div><div class="kpi-sub">Sejak interface aktif</div></div>
</div>

<div class="grid grid-2-1 mt-2">
    <div class="card">
        <div class="card-head">
            <h3><?= icon('activity') ?> <?= e($iface !== '' ? $iface : 'Pilih interface') ?></h3>
            <span class="spacer"></span>
            <div class="chart-legend"><span><i style="background:#38bdf8"></i>RX</span><span><i style="background:#a78bfa"></i>TX</span></div>
        </div>
        <div class="card-body">
            <div class="chart-shell">
                <canvas data-traffic-chart data-url="<?= e($pollUrl) ?>" data-interval="<?= $refresh * 1000 ?>" data-rx-target="[data-live-rx]" data-tx-target="[data-live-tx]" style="height:330px"></canvas>
            </div>
        </div>
    </div>
    <div class="stack">
        <div class="card">
            <div class="card-head"><h3><?= icon('network') ?> Interface</h3></div>
            <div class="card-body">
                <form method="get" action="<?= e(url('traffic')) ?>" class="field mb-2">
                    <input type="hidden" name="p" value="traffic">
                    <?php if (current_router_slug()): ?><input type="hidden" name="r" value="<?= e(current_router_slug()) ?>"><?php endif; ?>
                    <label for="iface">Pilih antarmuka</label>
                    <select class="select" id="iface" name="iface" data-autosubmit>
                        <?php foreach ($interfaces as $interface): ?>
                            <option value="<?= e($interface['name'] ?? '') ?>" <?= ($interface['name'] ?? '') === $iface ? 'selected' : '' ?>><?= e($interface['name'] ?? '') ?> (<?= e($interface['type'] ?? '') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <div class="keyval"><span>Status</span><strong><span class="dot <?= $running ? 'online' : 'offline' ?>"></span> <?= $running ? 'Running' : 'Down' ?></strong></div>
                <div class="keyval"><span>Type</span><strong><?= e($selected['type'] ?? '-') ?></strong></div>
                <div class="keyval"><span>MTU</span><strong><?= e($selected['mtu'] ?? '-') ?></strong></div>
                <div class="keyval"><span>MAC</span><strong class="mono"><?= e($selected['mac-address'] ?? '-') ?></strong></div>
                <div class="keyval"><span>Komentar</span><strong><?= e($selected['comment'] ?? '-') ?></strong></div>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h3><?= icon('info') ?> Catatan</h3></div>
            <div class="card-body text-small text-muted">
                <p>Grafik diperbarui setiap <?= $refresh ?> detik menggunakan sampling langsung dari router. Riwayat hanya disimpan di peramban selama halaman terbuka.</p>
            </div>
        </div>
    </div>
</div>
