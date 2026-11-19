<?php
$flashes = $flashes ?? [];
$err = '';
$notice = '';
foreach ($flashes as $flash) {
    if ($flash['type'] === 'danger') {
        $err = $flash['message'];
    } elseif ($flash['type'] === 'info' || $flash['type'] === 'warning' || $flash['type'] === 'success') {
        $notice = $flash['message'];
    }
}
?>
<div class="auth-wrap">
    <section class="auth-hero">
        <a class="brand" href="<?= e(url('login')) ?>">
            <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a8 8 0 0 1 16 0"/><path d="M7.5 17.5a5 5 0 0 1 9 0"/><circle cx="12" cy="20" r="1"/><path d="M12 4v3"/></svg></span>
            <span class="brand-text"><strong>JarMon</strong><small>Hotspot Manager</small></span>
        </a>
        <div class="auth-copy">
            <h1>Kelola hotspot MikroTik dengan cara yang lebih rapi.</h1>
            <p>Satu panel untuk voucher, pengguna, profil, laporan penjualan, dan monitoring router. Cepat, ringan, dan enak dipakai dari mana saja.</p>
            <div class="auth-features">
                <div class="auth-feature"><span class="ic"><?= icon('ticket') ?></span> Voucher studio dengan QR &amp; template cetak</div>
                <div class="auth-feature"><span class="ic"><?= icon('money') ?></span> Laporan penjualan otomatis per hari dan bulan</div>
                <div class="auth-feature"><span class="ic"><?= icon('activity') ?></span> Monitoring sesi aktif dan traffic real-time</div>
            </div>
        </div>
        <div class="auth-foot">&copy; <?= date('Y') ?> JarMon &middot; dibuat untuk operator hotspot</div>
    </section>
    <section class="auth-panel">
        <div class="auth-card">
            <h2>Masuk ke panel</h2>
            <p>Gunakan akun administrator JarMon Anda.</p>
            <?php if ($err !== ''): ?>
                <div class="alert alert-danger mt-2"><?= icon('alert') ?><div><?= e($err) ?></div></div>
            <?php endif; ?>
            <?php if ($notice !== ''): ?>
                <div class="alert alert-info mt-2"><?= icon('info') ?><div><?= e($notice) ?></div></div>
            <?php endif; ?>
            <form class="auth-form" method="post" action="<?= e(url('login')) ?>" autocomplete="off">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="username">Username</label>
                    <input class="input" id="username" name="username" type="text" required autofocus placeholder="admin" autocomplete="username">
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <div class="pass-wrap">
                        <input class="input" id="password" name="password" type="password" required placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" autocomplete="current-password">
                        <button type="button" class="pass-toggle" data-pass-toggle="password" aria-label="Lihat password"><?= icon('eye', 'icon-sm') ?></button>
                    </div>
                </div>
                <button class="btn btn-primary btn-block" type="submit" style="height:42px"><?= icon('key') ?> Masuk</button>
            </form>
            <div class="auth-note">JarMon menyimpan kredensial router secara terenkripsi di server Anda.</div>
        </div>
    </section>
</div>
