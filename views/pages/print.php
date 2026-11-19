<?php
$tpl = get_str('tpl');
$qrParam = get_str('qr', '');
$qr = $qrParam !== '0';
$items = [];
$source = '';
$sourceLabel = '';
$backUrl = url('vouchers');

if (get_str('batch') !== '') {
    $batch = jm_get_batch(get_str('batch'));
    if ($batch) {
        $items = $batch['items'];
        $source = 'batch=' . get_str('batch');
        $sourceLabel = 'Batch ' . date('d/m H:i', (int) $batch['created']);
        if ($tpl === '') {
            $tpl = (string) ($batch['tpl'] ?? 'default');
        }
        if ($qrParam === '') {
            $qr = !empty($batch['qr']);
        }
    }
} elseif (get_str('comment') !== '') {
    $comment = get_str('comment');
    $source = 'comment=' . rawurlencode($comment);
    $sourceLabel = 'Komentar ' . $comment;
    if (jm_api_ready()) {
        $users = api_comm('/ip/hotspot/user/print', ['?comment' => $comment, '?uptime' => '0s']);
        foreach ($users as $user) {
            $profile = jm_profile((string) ($user['profile'] ?? ''));
            $exp = $profile['_exp'] ?? ['validity' => '', 'price' => '', 'sprice' => ''];
            $items[] = [
                'username' => (string) ($user['name'] ?? ''),
                'password' => (string) ($user['password'] ?? ''),
                'profile' => (string) ($user['profile'] ?? ''),
                'validity' => (string) ($exp['validity'] ?? ''),
                'price' => (string) ($exp['price'] ?? ''),
                'sprice' => (string) ($exp['sprice'] ?? ''),
                'time_limit' => (string) ($user['limit-uptime'] ?? ''),
                'data_limit' => (string) ($user['limit-bytes-total'] ?? ''),
                'comment' => (string) ($user['comment'] ?? ''),
                'mode' => ($user['name'] ?? '') === ($user['password'] ?? '') ? 'vc' : 'up',
            ];
        }
        $backUrl = url('users', ['profile' => 'all']);
    }
} elseif (get_str('user') !== '') {
    $user = jm_api_ready() ? jm_user(get_str('user')) : null;
    if ($user) {
        $profile = jm_profile((string) ($user['profile'] ?? ''));
        $exp = $profile['_exp'] ?? ['validity' => '', 'price' => '', 'sprice' => ''];
        $items[] = [
            'username' => (string) ($user['name'] ?? ''),
            'password' => (string) ($user['password'] ?? ''),
            'profile' => (string) ($user['profile'] ?? ''),
            'validity' => (string) ($exp['validity'] ?? ''),
            'price' => (string) ($exp['price'] ?? ''),
            'sprice' => (string) ($exp['sprice'] ?? ''),
            'time_limit' => (string) ($user['limit-uptime'] ?? ''),
            'data_limit' => (string) ($user['limit-bytes-total'] ?? ''),
            'comment' => (string) ($user['comment'] ?? ''),
            'mode' => ($user['name'] ?? '') === ($user['password'] ?? '') ? 'vc' : 'up',
        ];
        $source = 'user=' . rawurlencode(get_str('user'));
        $sourceLabel = 'Voucher ' . get_str('user');
        $backUrl = url('users', ['mode' => 'edit', 'id' => $user['.id'] ?? '']);
    }
}

if (!in_array($tpl, ['default', 'compact', 'thermal'], true)) {
    $tpl = 'default';
}

$hotspotName = (string) ($router['hotspot_name'] ?? '') ?: (string) ($router['name'] ?? 'Hotspot');
$dns = (string) ($router['dns_name'] ?? '');
$loginHost = $dns !== '' ? $dns : (string) ($router['host'] ?? 'hotspot.local');
$logoFile = data_path('logo-' . ($router['slug'] ?? '') . '.img');
$hasLogo = is_file($logoFile);
$logoUrl = url('logo');
$base = jm_base_url();

$validLabel = static function (string $value): string {
    if ($value === '') return '';
    $unit = substr($value, -1);
    $num = substr($value, 0, -1);
    $map = ['d' => 'hari', 'h' => 'jam', 'm' => 'menit', 'w' => 'minggu'];
    return $num . ' ' . ($map[$unit] ?? $unit);
};
$modeLabel = $tpl === 'compact' ? 'Kompakt' : ($tpl === 'thermal' ? 'Thermal' : 'Standar');
?>
<div class="print-page">
    <div class="print-toolbar">
        <a class="btn btn-sm" href="<?= e($backUrl) ?>"><?= icon('chevron-left') ?> Kembali</a>
        <strong style="margin-left:4px"><?= count($items) ?> voucher</strong>
        <span class="badge badge-neutral"><?= e($sourceLabel ?: 'Pratinjau') ?></span>
        <span class="spacer"></span>
        <div class="segmented">
            <?php foreach (['default' => 'Standar', 'compact' => 'Kompakt', 'thermal' => 'Thermal'] as $key => $label): ?>
                <a class="<?= $tpl === $key ? 'active' : '' ?>" href="?p=print&amp;<?= e($source) ?>&amp;tpl=<?= $key ?>&amp;qr=<?= $qr ? '1' : '0' ?><?= current_router_slug() ? '&amp;r=' . e(current_router_slug()) : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <a class="btn btn-sm" href="?p=print&amp;<?= e($source) ?>&amp;tpl=<?= e($tpl) ?>&amp;qr=<?= $qr ? '0' : '1' ?><?= current_router_slug() ? '&amp;r=' . e(current_router_slug()) : '' ?>"><?= icon('qr') ?> <?= $qr ? 'Tanpa QR' : 'Dengan QR' ?></a>
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()"><?= icon('printer') ?> Cetak</button>
    </div>

    <?php if (!$items): ?>
        <div class="card mt-2" style="max-width:520px;margin:30px auto">
            <div class="card-body">
                <?= empty_state('ticket', 'Tidak ada voucher untuk dicetak', 'Batch mungkin sudah kedaluwarsa. Generate voucher baru dari Voucher Studio.') ?>
                <div class="text-center mt-2"><a class="btn btn-primary" href="<?= e(url('vouchers')) ?>"><?= icon('ticket') ?> Buka Voucher Studio</a></div>
            </div>
        </div>
    <?php else: ?>
        <div class="voucher-sheet tpl-<?= e($tpl) ?>">
            <?php foreach ($items as $index => $item): ?>
                <?php
                $price = (float) ($item['sprice'] ?? 0) > 0 ? (float) $item['sprice'] : (float) ($item['price'] ?? 0);
                $dataLabel = jm_limit_bytes_label($item['data_limit'] ?? 0);
                $timeLabel = $validLabel((string) ($item['time_limit'] ?? ''));
                $validity = $validLabel((string) ($item['validity'] ?? ''));
                $loginUrl = 'http://' . $loginHost . '/login?username=' . rawurlencode((string) $item['username']) . '&password=' . rawurlencode((string) $item['password']);
                $qrId = 'qr-' . $index . '-' . substr(md5((string) $item['username']), 0, 6);
                $isVc = ($item['mode'] ?? 'up') === 'vc';
                ?>
                <div class="voucher">
                    <div class="v-head">
                        <div class="v-brand">
                            <?php if ($hasLogo): ?><img src="<?= e($logoUrl) ?>" alt="logo"><?php endif; ?>
                            <span><?= e($hotspotName) ?></span>
                        </div>
                        <span class="v-num">#<?= $index + 1 ?></span>
                    </div>
                    <div class="v-body">
                        <div class="v-code-block">
                            <?php if ($isVc): ?>
                                <div class="v-label">Kode Voucher</div>
                                <div class="v-code"><?= e($item['username']) ?></div>
                            <?php else: ?>
                                <div class="v-cred">
                                    <div><span class="v-label">Username</span><b><?= e($item['username']) ?></b></div>
                                    <div><span class="v-label">Password</span><b><?= e($item['password']) ?></b></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($qr): ?>
                            <div class="v-qr"><canvas id="<?= e($qrId) ?>" data-qr="<?= e($loginUrl) ?>"></canvas></div>
                        <?php endif; ?>
                    </div>
                    <div class="v-meta">
                        <?php if ($validity): ?><span><?= e($validity) ?></span><?php endif; ?>
                        <?php if ($timeLabel && $timeLabel !== $validity): ?><span><?= e($timeLabel) ?></span><?php endif; ?>
                        <?php if ($dataLabel !== '-'): ?><span><?= e($dataLabel) ?></span><?php endif; ?>
                        <?php if ($price > 0): ?><span class="v-price"><?= e(money($price)) ?></span><?php endif; ?>
                    </div>
                    <div class="v-foot">Login: <b>http://<?= e($loginHost) ?></b></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="<?= e($base) ?>/assets/vendor/qrious.min.js"></script>
<script>
    (function () {
        if (typeof QRious === 'undefined') return;
        document.querySelectorAll('canvas[data-qr]').forEach(function (canvas) {
            try {
                new QRious({
                    element: canvas,
                    value: canvas.getAttribute('data-qr'),
                    size: canvas.classList.contains('qr-lg') ? 240 : 160,
                    background: '#ffffff',
                    foreground: '#0f172a',
                    level: 'M'
                });
            } catch (e) {}
        });
    })();
</script>

<style>
    .print-page { max-width: 1180px; margin: 0 auto; padding: 22px; }
    .voucher-sheet {
        display: grid;
        gap: 10px;
        margin-top: 18px;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .voucher-sheet.tpl-compact { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .voucher-sheet.tpl-thermal { grid-template-columns: minmax(0, 300px); justify-content: center; }
    .voucher {
        background: #fff;
        color: #0f172a;
        border: 1.5px solid #0f172a;
        border-radius: 8px;
        padding: 10px 11px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        break-inside: avoid;
        page-break-inside: avoid;
    }
    .v-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; border-bottom: 1px dashed #64748b; padding-bottom: 6px; }
    .v-brand { display: flex; align-items: center; gap: 6px; font-weight: 800; font-size: 12px; min-width: 0; }
    .v-brand img { height: 20px; width: auto; }
    .v-brand span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .v-num { font-size: 10px; color: #64748b; }
    .v-body { display: flex; align-items: center; gap: 8px; }
    .v-code-block { flex: 1; min-width: 0; text-align: center; }
    .v-label { display: block; font-size: 9px; letter-spacing: .08em; text-transform: uppercase; color: #64748b; margin-bottom: 2px; }
    .v-code { font-family: ui-monospace, Consolas, monospace; font-size: 19px; font-weight: 800; letter-spacing: .06em; border: 1.5px solid #0f172a; border-radius: 6px; padding: 5px 4px; word-break: break-all; }
    .v-cred { display: flex; flex-direction: column; gap: 5px; }
    .v-cred b { display: block; font-family: ui-monospace, Consolas, monospace; font-size: 13px; border: 1.2px solid #0f172a; border-radius: 5px; padding: 2px 5px; overflow-wrap: anywhere; }
    .v-qr canvas { width: 74px; height: 74px; display: block; }
    .v-meta { display: flex; flex-wrap: wrap; gap: 4px 6px; font-size: 9.5px; color: #334155; border-top: 1px dashed #64748b; padding-top: 6px; }
    .v-meta span { background: #f1f5f9; border-radius: 4px; padding: 1px 5px; white-space: nowrap; }
    .v-price { font-weight: 800; color: #0f172a; margin-left: auto; }
    .v-foot { font-size: 9.5px; color: #475569; }
    .v-foot b { color: #0f172a; }
    .tpl-compact .voucher { padding: 8px; gap: 6px; }
    .tpl-compact .v-code { font-size: 16px; }
    .tpl-compact .v-qr canvas { width: 56px; height: 56px; }
    .tpl-thermal .voucher { border-style: dashed; }
    .tpl-thermal .v-code { font-size: 22px; }

    @media print {
        @page { size: A4 portrait; margin: 8mm; }
        html, body { background: #fff !important; }
        .print-toolbar { display: none !important; }
        .print-page { max-width: none; padding: 0; }
        .voucher-sheet { margin: 0; gap: 4mm; grid-template-columns: repeat(3, 1fr); }
        .voucher-sheet.tpl-compact { grid-template-columns: repeat(4, 1fr); gap: 2.5mm; }
        .voucher-sheet.tpl-thermal { grid-template-columns: 58mm; justify-content: flex-start; gap: 3mm; }
        .voucher { border-radius: 4px; padding: 2.5mm; }
        .toast-stack { display: none; }
    }
    @media (max-width: 760px) {
        .voucher-sheet, .voucher-sheet.tpl-compact { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .print-page { padding: 14px; }
    }
    @media (max-width: 480px) {
        .voucher-sheet, .voucher-sheet.tpl-compact { grid-template-columns: 1fr; }
    }
</style>
