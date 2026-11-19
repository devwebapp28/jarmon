<?php
$flashes = $flashes ?? [];
$errors = [];
foreach ($flashes as $flash) {
    if ($flash['type'] === 'danger') {
        $errors[] = $flash['message'];
    }
}
?>
<div class="auth-wrap">
    <section class="auth-hero">
        <a class="brand" href="#">
            <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a8 8 0 0 1 16 0"/><path d="M7.5 17.5a5 5 0 0 1 9 0"/><circle cx="12" cy="20" r="1"/><path d="M12 4v3"/></svg></span>
            <span class="brand-text"><strong>JarMon</strong><small>Setup Awal</small></span>
        </a>
        <div class="auth-copy">
            <h1>Siap mengelola hotspot lebih modern.</h1>
            <p>Buat akun administrator untuk mulai menggunakan JarMon. Setelah itu Anda dapat menambahkan router MikroTik dan langsung mengelola voucher.</p>
            <div class="auth-features">
                <div class="auth-feature"><span class="ic"><?= icon('shield') ?></span> Password admin disimpan dengan hash yang aman</div>
                <div class="auth-feature"><span class="ic"><?= icon('lock') ?></span> Kredensial router dienkripsi AES-256-GCM</div>
                <div class="auth-feature"><span class="ic"><?= icon('server') ?></span> Dukungan banyak router dalam satu panel</div>
            </div>
        </div>
        <div class="auth-foot">&copy; <?= date('Y') ?> JarMon</div>
    </section>
    <section class="auth-panel">
        <div class="auth-card">
            <h2>Buat akun administrator</h2>
            <p>Akun ini dipakai untuk masuk ke panel JarMon.</p>
            <?php foreach ($errors as $message): ?>
                <div class="alert alert-danger mt-2"><?= icon('alert') ?><div><?= e($message) ?></div></div>
            <?php endforeach; ?>
            <form class="auth-form" method="post" action="<?= e(url('setup')) ?>" autocomplete="off">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="username">Username admin</label>
                    <input class="input" id="username" name="username" type="text" required autofocus placeholder="admin" value="<?= e(post_str('username', 'admin')) ?>">
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <div class="pass-wrap">
                        <input class="input" id="password" name="password" type="password" required minlength="6" placeholder="Minimal 6 karakter" autocomplete="new-password">
                        <button type="button" class="pass-toggle" data-pass-toggle="password" aria-label="Lihat password"><?= icon('eye', 'icon-sm') ?></button>
                    </div>
                </div>
                <div class="field">
                    <label for="password_confirm">Ulangi password</label>
                    <input class="input" id="password_confirm" name="password_confirm" type="password" required minlength="6" placeholder="Ulangi password" autocomplete="new-password">
                </div>
                <button class="btn btn-primary btn-block" type="submit" style="height:42px"><?= icon('spark') ?> Pasang JarMon</button>
            </form>
            <div class="auth-note">Data konfigurasi disimpan lokal di folder <span class="mono">data/</span> pada server Anda.</div>
        </div>
    </section>
</div>
