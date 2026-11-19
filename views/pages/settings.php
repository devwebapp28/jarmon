<?php
$tab = get_str('tab', 'general');
if (!in_array($tab, ['general', 'admin', 'appearance', 'logo', 'backup'], true)) {
    $tab = 'general';
}
$do = post_str('do');
$config = jm_config();
$app = $config['app'];

if ($do === 'general-save') {
    $config['app']['company'] = post_str('company', 'JarMon');
    $config['app']['currency'] = post_str('currency', 'Rp');
    $config['app']['timezone'] = post_str('timezone', 'Asia/Jakarta');
    $config['app']['refresh'] = max(5, min(300, post_int('refresh', 20)));
    $config['app']['page_size'] = max(10, min(200, post_int('page_size', 25)));
    $config['app']['idle_timeout'] = max(0, min(240, post_int('idle_timeout', 30)));
    if (jm_config_save($config)) {
        jm_log('settings-general', 'Pengaturan umum diperbarui');
        jm_flash('success', 'Pengaturan umum disimpan.');
    } else {
        jm_flash('danger', 'Gagal menyimpan pengaturan.');
    }
    redirect(url('settings', ['tab' => 'general']));
}

if ($do === 'admin-save') {
    $current = post_str('current_password');
    $username = post_str('username');
    $new = post_str('new_password');
    $confirm = post_str('new_password_confirm');
    $admin = $config['admin'];
    if (!password_verify($current, (string) $admin['hash'])) {
        jm_flash('danger', 'Password saat ini salah.');
    } elseif ($username === '') {
        jm_flash('danger', 'Username tidak boleh kosong.');
    } elseif ($new !== '' && strlen($new) < 6) {
        jm_flash('danger', 'Password baru minimal 6 karakter.');
    } elseif ($new !== '' && $new !== $confirm) {
        jm_flash('danger', 'Konfirmasi password baru tidak cocok.');
    } else {
        $config['admin']['username'] = $username;
        if ($new !== '') {
            $config['admin']['hash'] = password_hash($new, PASSWORD_DEFAULT);
        }
        jm_config_save($config);
        $_SESSION['jm_admin'] = $username;
        jm_log('settings-admin', 'Akun admin diperbarui');
        jm_flash('success', 'Akun administrator diperbarui.');
    }
    redirect(url('settings', ['tab' => 'admin']));
}

if ($do === 'appearance-save') {
    $theme = post_str('theme', 'dark');
    $accent = post_str('accent', 'indigo');
    $config['app']['theme'] = in_array($theme, ['dark', 'light'], true) ? $theme : 'dark';
    $config['app']['accent'] = in_array($accent, ['indigo', 'emerald', 'rose', 'amber', 'sky', 'violet'], true) ? $accent : 'indigo';
    jm_config_save($config);
    jm_flash('success', 'Tampilan diperbarui.');
    redirect(url('settings', ['tab' => 'appearance']));
}

if ($do === 'logo-upload' || $do === 'logo-delete') {
    if (!$router) {
        jm_flash('warning', 'Pilih router terlebih dahulu untuk mengatur logo.');
        redirect(url('settings', ['tab' => 'logo']));
    }
    $slug = (string) $router['slug'];
    if ($do === 'logo-delete') {
        $file = data_path('logo-' . $slug . '.img');
        if (is_file($file)) {
            @unlink($file);
        }
        jm_log('settings-logo', 'Hapus logo ' . $slug);
        jm_flash('success', 'Logo voucher ' . router_label($router) . ' dihapus.');
        redirect(url('settings', ['tab' => 'logo', 'r' => $slug]));
    }
    if (isset($_FILES['logo']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
        $file = $_FILES['logo'];
        $info = @getimagesize($file['tmp_name']);
        $allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            jm_flash('danger', 'Ukuran logo maksimal 2 MB.');
        } elseif (!$info || !in_array($info['mime'], $allowed, true)) {
            jm_flash('danger', 'Format logo harus PNG, JPG, WEBP, atau GIF.');
        } else {
            move_uploaded_file($file['tmp_name'], data_path('logo-' . $slug . '.img'));
            jm_log('settings-logo', 'Upload logo ' . $slug);
            jm_flash('success', 'Logo voucher ' . router_label($router) . ' diperbarui.');
        }
    } else {
        jm_flash('danger', 'Berkas logo tidak ditemukan.');
    }
    redirect(url('settings', ['tab' => 'logo', 'r' => $slug]));
}

if ($do === 'restore' && isset($_FILES['backup']) && is_uploaded_file($_FILES['backup']['tmp_name'])) {
    $raw = file_get_contents($_FILES['backup']['tmp_name']);
    $data = json_decode((string) $raw, true);
    if (!is_array($data) || !isset($data['config']) || !is_array($data['config']) || empty($data['config']['admin']['hash'])) {
        jm_flash('danger', 'Berkas backup tidak valid.');
        redirect(url('settings', ['tab' => 'backup']));
    }
    $restored = $data['config'];
    $restored['installed'] = true;
    if (!empty($data['secret']) && is_string($data['secret'])) {
        file_put_contents(data_path('secret.key'), $data['secret'], LOCK_EX);
    }
    if (isset($data['batches']) && is_array($data['batches'])) {
        jm_batches_write($data['batches']);
    }
    if (jm_config_save($restored)) {
        jm_log('settings-restore', 'Backup dipulihkan');
        jm_flash('success', 'Backup berhasil dipulihkan.');
        redirect(url('routers'));
    }
    jm_flash('danger', 'Gagal menulis konfigurasi hasil restore.');
    redirect(url('settings', ['tab' => 'backup']));
}

if (get_str('export') === 'config') {
    $backup = [
        'app' => 'JarMon',
        'version' => JARMON_VERSION,
        'exported_at' => date('c'),
        'secret' => is_file(data_path('secret.key')) ? file_get_contents(data_path('secret.key')) : '',
        'config' => $config,
        'batches' => jm_batches_load(),
    ];
    $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="jarmon-backup-' . date('Ymd-His') . '.json"');
    echo $json;
    exit;
}

$backupReady = is_file(data_path('config.json'));
$auditSize = is_file(data_path('audit.log')) ? filesize(data_path('audit.log')) : 0;
$pageTitle = 'Pengaturan';
$activeNav = 'settings';
$timezones = ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'Asia/Singapore', 'Asia/Kuala_Lumpur', 'Asia/Bangkok', 'Asia/Manila', 'Asia/Dhaka', 'Asia/Kolkata', 'Asia/Dubai', 'UTC', 'Europe/London', 'America/New_York', 'Australia/Sydney'];
?>
<?php page_header('Pengaturan', 'Akun admin, tampilan panel, dan cadangan konfigurasi JarMon.', ''); ?>

<div class="tabs mb-2">
    <a class="tab <?= $tab === 'general' ? 'active' : '' ?>" href="<?= e(url('settings', ['tab' => 'general'])) ?>"><?= icon('settings') ?> Umum</a>
    <a class="tab <?= $tab === 'admin' ? 'active' : '' ?>" href="<?= e(url('settings', ['tab' => 'admin'])) ?>"><?= icon('key') ?> Admin</a>
    <a class="tab <?= $tab === 'appearance' ? 'active' : '' ?>" href="<?= e(url('settings', ['tab' => 'appearance'])) ?>"><?= icon('sun') ?> Tampilan</a>
    <a class="tab <?= $tab === 'logo' ? 'active' : '' ?>" href="<?= e(url('settings', ['tab' => 'logo'])) ?>"><?= icon('upload') ?> Logo</a>
    <a class="tab <?= $tab === 'backup' ? 'active' : '' ?>" href="<?= e(url('settings', ['tab' => 'backup'])) ?>"><?= icon('database') ?> Backup</a>
</div>

<?php if ($tab === 'general'): ?>
    <div class="grid grid-2-1">
        <form method="post" action="<?= e(url('settings', ['tab' => 'general'])) ?>" class="card">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="general-save">
            <div class="card-head"><h3><?= icon('settings') ?> Preferensi umum</h3></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="field"><label for="company">Nama usaha / brand</label><input class="input" id="company" name="company" value="<?= e($app['company'] ?? 'JarMon') ?>"><span class="hint">Tampil pada voucher dan laporan.</span></div>
                    <div class="field"><label for="currency">Mata uang</label><input class="input" id="currency" name="currency" value="<?= e($app['currency'] ?? 'Rp') ?>" maxlength="8"><span class="hint">Contoh: Rp, IDR, $, RM.</span></div>
                    <div class="field"><label for="timezone">Zona waktu</label>
                        <select class="select" id="timezone" name="timezone">
                            <?php foreach ($timezones as $tz): ?><option value="<?= e($tz) ?>" <?= ($app['timezone'] ?? '') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label for="refresh">Interval refresh (detik)</label><input class="input" id="refresh" name="refresh" type="number" min="5" max="300" value="<?= (int) ($app['refresh'] ?? 20) ?>"><span class="hint">Untuk sesi aktif, log, dan traffic.</span></div>
                    <div class="field"><label for="page_size">Baris per halaman</label><input class="input" id="page_size" name="page_size" type="number" min="10" max="200" value="<?= (int) ($app['page_size'] ?? 25) ?>"></div>
                    <div class="field"><label for="idle_timeout">Auto logout (menit)</label><input class="input" id="idle_timeout" name="idle_timeout" type="number" min="0" max="240" value="<?= (int) ($app['idle_timeout'] ?? 30) ?>"><span class="hint">0 = nonaktif.</span></div>
                </div>
            </div>
            <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan pengaturan</button></div>
        </form>
        <div class="stack">
            <div class="card">
                <div class="card-head"><h3><?= icon('info') ?> Info sistem</h3></div>
                <div class="card-body">
                    <div class="keyval"><span>JarMon</span><strong>v<?= JARMON_VERSION ?></strong></div>
                    <div class="keyval"><span>PHP</span><strong><?= e(PHP_VERSION) ?></strong></div>
                    <div class="keyval"><span>Router terdaftar</span><strong><?= count(jm_routers()) ?></strong></div>
                    <div class="keyval"><span>Konfigurasi</span><strong><?= $backupReady ? 'Tersimpan' : 'Belum ada' ?></strong></div>
                    <div class="keyval"><span>Audit log</span><strong><?= e(fmt_bytes($auditSize)) ?></strong></div>
                </div>
            </div>
            <div class="card">
                <div class="card-head"><h3><?= icon('shield') ?> Keamanan</h3></div>
                <div class="card-body text-small text-muted">
                    <p>Pastikan folder <span class="mono">data/</span> tidak dapat diakses langsung dari web. JarMon menyertakan <span class="mono">.htaccess</span> untuk Apache.</p>
                    <p class="mt-2">Gunakan user API terbatas dan pertimbangkan API SSL untuk koneksi jarak jauh.</p>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'admin'): ?>
    <div class="grid grid-2-1">
        <form method="post" action="<?= e(url('settings', ['tab' => 'admin'])) ?>" class="card" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="admin-save">
            <div class="card-head"><h3><?= icon('key') ?> Akun administrator</h3></div>
            <div class="card-body">
                <div class="form-grid cols-1">
                    <div class="field"><label for="username">Username</label><input class="input" id="username" name="username" value="<?= e($config['admin']['username']) ?>" required></div>
                    <div class="field"><label for="current_password">Password saat ini</label><input class="input" id="current_password" name="current_password" type="password" required placeholder="wajib untuk konfirmasi"></div>
                    <div class="divider"></div>
                    <div class="field"><label for="new_password">Password baru</label><input class="input" id="new_password" name="new_password" type="password" placeholder="kosongkan jika tidak diubah"><span class="hint">Minimal 6 karakter.</span></div>
                    <div class="field"><label for="new_password_confirm">Ulangi password baru</label><input class="input" id="new_password_confirm" name="new_password_confirm" type="password"></div>
                </div>
            </div>
            <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('check') ?> Perbarui akun</button></div>
        </form>
        <div class="card">
            <div class="card-head"><h3><?= icon('shield') ?> Tips keamanan</h3></div>
            <div class="card-body text-small text-muted">
                <p class="mb-2">Gunakan password unik dan panjang. JarMon menyimpan password admin sebagai hash bcrypt.</p>
                <p>Semua aksi administratif tercatat di audit log dan dapat dilihat pada menu Sistem &rarr; Audit log.</p>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'appearance'): ?>
    <form method="post" action="<?= e(url('settings', ['tab' => 'appearance'])) ?>" id="appearanceForm">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="appearance-save">
        <div class="card">
            <div class="card-head"><h3><?= icon('sun') ?> Tema panel</h3></div>
            <div class="card-body">
                <div class="grid grid-2">
                    <label class="tpl-option <?= ($app['theme'] ?? 'dark') === 'dark' ? 'active' : '' ?>" style="cursor:pointer">
                        <input type="radio" name="theme" value="dark" <?= ($app['theme'] ?? 'dark') === 'dark' ? 'checked' : '' ?>>
                        <strong><?= icon('moon') ?> Gelap</strong>
                        <div style="height:64px;border-radius:8px;background:linear-gradient(135deg,#0b1020,#1b2440);border:1px solid var(--border)"></div>
                    </label>
                    <label class="tpl-option <?= ($app['theme'] ?? '') === 'light' ? 'active' : '' ?>" style="cursor:pointer">
                        <input type="radio" name="theme" value="light" <?= ($app['theme'] ?? '') === 'light' ? 'checked' : '' ?>>
                        <strong><?= icon('sun') ?> Terang</strong>
                        <div style="height:64px;border-radius:8px;background:linear-gradient(135deg,#ffffff,#eef2f9);border:1px solid var(--border)"></div>
                    </label>
                </div>
                <div class="field mt-3"><label>Warna aksen</label>
                    <div class="row-flex wrap" style="gap:10px">
                        <?php foreach (['indigo' => '#6366f1', 'emerald' => '#10b981', 'rose' => '#f43f5e', 'amber' => '#f59e0b', 'sky' => '#0ea5e9', 'violet' => '#7c3aed'] as $key => $color): ?>
                            <label class="check" style="padding:8px 12px;border:1px solid <?= ($app['accent'] ?? 'indigo') === $key ? $color : 'var(--border)' ?>;border-radius:10px;background:var(--surface-2)">
                                <input type="radio" name="accent" value="<?= $key ?>" <?= ($app['accent'] ?? 'indigo') === $key ? 'checked' : '' ?>>
                                <span style="width:14px;height:14px;border-radius:50%;background:<?= $color ?>;display:inline-block"></span>
                                <?= ucfirst($key) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan tampilan</button></div>
        </div>
    </form>
    <script>
        document.getElementById('appearanceForm').addEventListener('submit', function () {
            try {
                var theme = this.querySelector('input[name="theme"]:checked');
                var accent = this.querySelector('input[name="accent"]:checked');
                if (theme) localStorage.setItem('jm-theme', theme.value);
                if (accent) localStorage.setItem('jm-accent', accent.value);
            } catch (e) {}
        });
    </script>

<?php elseif ($tab === 'logo'): ?>
    <?php
    $hasLogo = $router && is_file(data_path('logo-' . $router['slug'] . '.img'));
    ?>
    <?php if (!$router): ?>
        <div class="card"><div class="card-body">
            <?= empty_state('server', 'Belum ada router', 'Tambahkan router terlebih dahulu untuk mengatur logo voucher.') ?>
            <div class="text-center mt-2"><a class="btn btn-primary" href="<?= e(url('routers', ['mode' => 'add'])) ?>"><?= icon('plus') ?> Tambah router</a></div>
        </div></div>
    <?php else: ?>
        <div class="grid grid-2-1">
            <div class="stack">
                <div class="card">
                    <div class="card-head"><h3><?= icon('upload') ?> Logo voucher</h3><span class="spacer"></span><span class="badge badge-neutral"><?= e(router_label($router)) ?></span></div>
                    <div class="card-body">
                        <?php if (count($routers) > 1): ?>
                            <form method="get" action="<?= e(url('settings')) ?>" class="field mb-2">
                                <input type="hidden" name="p" value="settings">
                                <input type="hidden" name="tab" value="logo">
                                <label for="logoRouter">Router</label>
                                <select class="select" id="logoRouter" name="r" data-autosubmit>
                                    <?php foreach ($routers as $slug => $item): ?>
                                        <option value="<?= e($slug) ?>" <?= $router['slug'] === $slug ? 'selected' : '' ?>><?= e($item['name'] ?? $slug) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="hint">Logo disimpan per router dan dipakai pada voucher router tersebut.</span>
                            </form>
                        <?php endif; ?>
                        <div class="row-flex" style="gap:14px;align-items:flex-start;flex-wrap:wrap">
                            <div class="logo-preview">
                                <?php if ($hasLogo): ?>
                                    <img src="<?= e(url('logo', ['v' => time()])) ?>" alt="Logo voucher">
                                <?php else: ?>
                                    <div class="logo-empty"><?= icon('upload') ?><span>Belum ada logo</span></div>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1;min-width:220px">
                                <p class="text-small text-muted mb-2">Gunakan logo PNG transparan atau JPG, maksimal 2 MB. Logo tampil di bagian atas setiap voucher dan laporan cetak.</p>
                                <form method="post" action="<?= e(url('settings', ['tab' => 'logo'])) ?>" enctype="multipart/form-data">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="do" value="logo-upload">
                                    <div class="field"><label for="logoFile">Pilih berkas logo</label><input class="input" id="logoFile" name="logo" type="file" accept="image/png,image/jpeg,image/webp,image/gif" required></div>
                                    <button class="btn btn-primary btn-block mt-1" type="submit"><?= icon('upload') ?> Unggah logo</button>
                                </form>
                                <?php if ($hasLogo): ?>
                                    <form method="post" action="<?= e(url('settings', ['tab' => 'logo'])) ?>" class="mt-1" data-confirm="Hapus logo voucher <?= e(router_label($router)) ?>?">
                                        <?= csrf_field() ?><input type="hidden" name="do" value="logo-delete">
                                        <button class="btn btn-danger btn-block" type="submit"><?= icon('trash') ?> Hapus logo</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="stack">
                <div class="card">
                    <div class="card-head"><h3><?= icon('info') ?> Tips logo</h3></div>
                    <div class="card-body text-small text-muted">
                        <p class="mb-2">Ukuran ideal sekitar <b>400 x 120 piksel</b> dengan latar transparan agar tajam saat dicetak.</p>
                        <p class="mb-2">Logo juga dapat diunggah dari menu <a href="<?= e(url('routers', ['mode' => 'edit', 'slug' => $router['slug']])) ?>">Router &rarr; Edit</a>.</p>
                        <p>Bila logo belum diatur, voucher hanya menampilkan nama hotspot.</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-head"><h3><?= icon('ticket') ?> Pratinjau cetak</h3></div>
                    <div class="card-body">
                        <a class="btn btn-block" href="<?= e(url('vouchers')) ?>"><?= icon('ticket') ?> Buka Voucher Studio</a>
                        <a class="btn btn-block mt-1" href="<?= e(url('print', ['comment' => 'jarmon'])) ?>" target="_blank"><?= icon('printer') ?> Contoh halaman cetak</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="grid grid-2">
        <div class="card">
            <div class="card-head"><h3><?= icon('download') ?> Unduh backup</h3></div>
            <div class="card-body">
                <p class="text-muted mb-2">Backup berisi konfigurasi JarMon, router, paket, riwayat batch voucher, dan kunci enkripsi agar dapat dipulihkan di server lain.</p>
                <div class="alert alert-warning"><?= icon('alert') ?><div>Simpan berkas backup di tempat aman. Berkas ini setara dengan kunci akses router Anda.</div></div>
                <a class="btn btn-primary btn-block mt-2" href="<?= e(url('settings', ['tab' => 'backup', 'export' => 'config'])) ?>"><?= icon('download') ?> Unduh backup JSON</a>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h3><?= icon('upload') ?> Pulihkan backup</h3></div>
            <form method="post" action="<?= e(url('settings', ['tab' => 'backup'])) ?>" enctype="multipart/form-data" data-confirm="Pulihkan backup ini? Konfigurasi saat ini akan diganti.">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="restore">
                <div class="card-body">
                    <div class="field"><label for="backup">Berkas backup (.json)</label><input class="input" id="backup" name="backup" type="file" accept="application/json,.json" required></div>
                    <p class="text-small text-muted mt-2">Setelah restore, Anda akan diarahkan ke daftar router.</p>
                </div>
                <div class="card-foot"><button class="btn btn-danger" type="submit"><?= icon('upload') ?> Pulihkan</button></div>
            </form>
        </div>
    </div>
    <div class="card mt-2">
        <div class="card-head"><h3><?= icon('database') ?> Berkas data</h3></div>
        <div class="card-body">
            <div class="keyval"><span>config.json</span><strong><?= is_file(data_path('config.json')) ? e(fmt_bytes(filesize(data_path('config.json')))) : 'tidak ada' ?></strong></div>
            <div class="keyval"><span>secret.key</span><strong><?= is_file(data_path('secret.key')) ? 'ada (disembunyikan)' : 'belum dibuat' ?></strong></div>
            <div class="keyval"><span>audit.log</span><strong><?= e(fmt_bytes($auditSize)) ?></strong></div>
            <div class="keyval"><span>batches.json</span><strong><?= is_file(data_path('batches.json')) ? e(fmt_bytes(filesize(data_path('batches.json')))) : 'belum ada' ?></strong></div>
            <div class="keyval"><span>Lokasi</span><strong class="mono"><?= e(data_path()) ?></strong></div>
        </div>
    </div>
<?php endif; ?>
