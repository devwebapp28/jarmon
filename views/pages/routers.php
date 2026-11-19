<?php
$mode = get_str('mode', 'list');
$do = post_str('do');
$routers = jm_routers();

if ($do === 'save') {
    $slug = get_str('slug');
    $isEdit = $slug !== '' && isset($routers[$slug]);
    $name = post_str('name');
    $host = preg_replace('/\s+/', '', post_str('host'));
    $user = post_str('user');
    $password = post_str('password');
    $port = max(1, min(65535, post_int('port', 8728)));
    if ($name === '' || $host === '' || $user === '') {
        jm_flash('danger', 'Nama, host, dan user router wajib diisi.');
        redirect(url('routers', ['mode' => $isEdit ? 'edit' : 'add', 'slug' => $slug]));
    }
    if (!$isEdit && $password === '') {
        jm_flash('danger', 'Password API wajib diisi untuk router baru.');
        redirect(url('routers', ['mode' => 'add']));
    }
    $existing = $isEdit ? $routers[$slug] : null;
    if (!$isEdit) {
        $slug = jm_slugify($name);
        $baseSlug = $slug;
        $i = 2;
        while (isset($routers[$slug])) {
            $slug = $baseSlug . '-' . $i++;
        }
    }
    if ($password === '' && $existing) {
        $encrypted = (string) ($existing['password_enc'] ?? '');
    } else {
        $encrypted = jm_encrypt($password);
    }
    $data = [
        'name' => $name,
        'host' => $host,
        'port' => $port,
        'ssl' => post_str('ssl') === 'yes',
        'user' => $user,
        'password_enc' => $encrypted,
        'hotspot_name' => post_str('hotspot_name'),
        'dns_name' => post_str('dns_name'),
        'interface' => post_str('interface'),
        'created_at' => $existing['created_at'] ?? date('Y-m-d H:i:s'),
    ];
    jm_router_save($slug, $data);
    if (!current_router_slug() || !isset($routers[current_router_slug()])) {
        $_SESSION['jm_router'] = $slug;
    }
    jm_reset_api();
    jm_log($isEdit ? 'router-update' : 'router-create', $name);
    jm_flash('success', 'Router ' . $name . ' disimpan.');
    redirect(url('routers'));
}

if ($do === 'delete') {
    $slug = post_str('slug');
    if (isset($routers[$slug])) {
        jm_router_delete($slug);
        if (current_router_slug() === $slug) {
            unset($_SESSION['jm_router']);
        }
        jm_log('router-delete', (string) ($routers[$slug]['name'] ?? $slug));
        jm_flash('success', 'Router dihapus dari JarMon.');
    }
    redirect(url('routers'));
}

if ($do === 'test') {
    $slug = post_str('slug');
    $target = $routers[$slug] ?? null;
    if ($target) {
        $test = new RouterosAPI();
        $test->debug = false;
        $test->port = (int) ($target['port'] ?? 8728);
        $test->ssl = !empty($target['ssl']);
        $test->timeout = 4;
        $test->attempts = 1;
        $ok = false;
        $error = '';
        try {
            $ok = (bool) $test->connect((string) $target['host'], (string) $target['user'], jm_decrypt($target['password_enc'] ?? ''));
            if (!$ok) $error = (string) ($test->error_str ?? '');
        } catch (Throwable $ex) {
            $error = $ex->getMessage();
        }
        jm_flash($ok ? 'success' : 'danger', $ok ? 'Koneksi ke ' . ($target['name'] ?? $slug) . ' berhasil.' : 'Koneksi gagal: ' . ($error ?: 'periksa host, port API, dan kredensial.'));
    }
    redirect(url('routers'));
}

if ($do === 'logo-upload') {
    $slug = post_str('slug');
    if (isset($routers[$slug]) && isset($_FILES['logo']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
        $file = $_FILES['logo'];
        $info = @getimagesize($file['tmp_name']);
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if ($file['size'] > 2 * 1024 * 1024) {
            jm_flash('danger', 'Ukuran logo maksimal 2 MB.');
        } elseif (!$info || !isset($allowed[$info['mime']])) {
            jm_flash('danger', 'Format logo harus PNG, JPG, WEBP, atau GIF.');
        } else {
            move_uploaded_file($file['tmp_name'], data_path('logo-' . $slug . '.img'));
            jm_flash('success', 'Logo voucher diperbarui.');
            jm_log('router-logo', $slug);
        }
    }
    redirect(url('routers', ['mode' => 'edit', 'slug' => $slug]));
}
if ($do === 'logo-delete') {
    $slug = post_str('slug');
    $file = data_path('logo-' . $slug . '.img');
    if (is_file($file)) @unlink($file);
    jm_flash('success', 'Logo dihapus.');
    redirect(url('routers', ['mode' => 'edit', 'slug' => $slug]));
}

$editRouter = null;
$editSlug = get_str('slug');
if ($mode === 'edit' && isset($routers[$editSlug])) {
    $editRouter = $routers[$editSlug];
    $editRouter['slug'] = $editSlug;
} elseif ($mode === 'edit') {
    jm_flash('warning', 'Router tidak ditemukan.');
    redirect(url('routers'));
}
$interfaces = jm_api_ready() ? jm_interfaces() : [];
$hasLogo = $mode === 'edit' && is_file(data_path('logo-' . $editSlug . '.img'));
$pageTitle = 'Router';
$activeNav = 'routers';
?>
<?php if ($mode === 'add' || $mode === 'edit'): ?>
    <?php
    $r = $editRouter;
    $isEdit = $mode === 'edit';
    page_header($isEdit ? 'Edit router' : 'Tambah router', 'Hubungkan JarMon ke layanan API MikroTik.', '<a class="btn" href="' . e(url('routers')) . '">' . icon('chevron-left') . ' Kembali</a>');
    ?>
    <div class="grid grid-2-1">
        <form method="post" action="<?= e(url('routers')) ?>" class="card" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="save">
            <input type="hidden" name="slug" value="<?= e($editSlug) ?>">
            <div class="card-head"><h3><?= icon('router') ?> Konfigurasi koneksi</h3></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="field span-2"><label for="name">Nama router</label><input class="input" id="name" name="name" value="<?= e($r['name'] ?? '') ?>" required placeholder="Router Kantor" autofocus><span class="hint">Nama yang tampil di panel JarMon.</span></div>
                    <div class="field"><label for="host">Host / IP</label><input class="input mono" id="host" name="host" value="<?= e($r['host'] ?? '') ?>" required placeholder="192.168.88.1"></div>
                    <div class="field"><label for="port">Port API</label><input class="input mono" id="port" name="port" type="number" min="1" max="65535" value="<?= e($r['port'] ?? 8728) ?>" required><span class="hint">Default 8728, API SSL 8729.</span></div>
                    <div class="field"><label for="user">User API</label><input class="input" id="user" name="user" value="<?= e($r['user'] ?? '') ?>" required placeholder="admin"></div>
                    <div class="field"><label for="password">Password API <?= $isEdit ? '<span class="text-muted">(kosong = tetap)</span>' : '' ?></label><input class="input" id="password" name="password" type="password" value="" <?= $isEdit ? '' : 'required' ?>></div>
                    <div class="field"><label for="hotspot_name">Nama hotspot</label><input class="input" id="hotspot_name" name="hotspot_name" value="<?= e($r['hotspot_name'] ?? '') ?>" placeholder="Hotspot JarMon"></div>
                    <div class="field"><label for="dns_name">DNS / alamat login</label><input class="input mono" id="dns_name" name="dns_name" value="<?= e($r['dns_name'] ?? '') ?>" placeholder="hotspot.local"><span class="hint">Dipakai pada voucher dan QR login.</span></div>
                    <div class="field"><label for="interface">Interface traffic</label>
                        <input class="input" id="interface" name="interface" list="ifaceList" value="<?= e($r['interface'] ?? '') ?>" placeholder="ether1">
                        <datalist id="ifaceList"><?php foreach ($interfaces as $iface): ?><option value="<?= e($iface['name'] ?? '') ?>"><?php endforeach; ?></datalist>
                    </div>
                    <div class="field"><label>Koneksi</label><label class="switch"><input type="checkbox" name="ssl" value="yes" <?= !empty($r['ssl']) ? 'checked' : '' ?>><span class="track"></span> Gunakan API SSL</label></div>
                </div>
            </div>
            <div class="card-foot">
                <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan router</button>
                <a class="btn btn-ghost" href="<?= e(url('routers')) ?>">Batal</a>
            </div>
        </form>
        <div class="stack">
            <div class="card">
                <div class="card-head"><h3><?= icon('info') ?> Petunjuk</h3></div>
                <div class="card-body text-small text-muted">
                    <p class="mb-2">Aktifkan layanan API di router:</p>
                    <pre class="mono" style="background:var(--surface-2);padding:10px;border-radius:8px;font-size:11.5px;overflow:auto">/ip service enable api
/ip service set api port=8728</pre>
                    <p class="mt-2">Buat user khusus dengan grup <b>full</b> atau minimal <b>write + api</b> untuk keamanan.</p>
                </div>
            </div>
            <?php if ($isEdit): ?>
                <div class="card">
                    <div class="card-head"><h3><?= icon('upload') ?> Logo voucher</h3></div>
                    <div class="card-body">
                        <?php if ($hasLogo): ?>
                            <div style="padding:10px;background:var(--surface-2);border-radius:10px;text-align:center;margin-bottom:10px"><img src="<?= e(url('logo', ['v' => time()])) ?>" alt="Logo" style="max-height:70px;max-width:100%"></div>
                            <form method="post" action="<?= e(url('routers', ['mode' => 'edit', 'slug' => $editSlug])) ?>" data-confirm="Hapus logo voucher?">
                                <?= csrf_field() ?><input type="hidden" name="do" value="logo-delete"><input type="hidden" name="slug" value="<?= e($editSlug) ?>">
                                <button class="btn btn-sm btn-danger btn-block" type="submit"><?= icon('trash') ?> Hapus logo</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="<?= e(url('routers', ['mode' => 'edit', 'slug' => $editSlug])) ?>" enctype="multipart/form-data" class="<?= $hasLogo ? 'mt-2' : '' ?>">
                            <?= csrf_field() ?><input type="hidden" name="do" value="logo-upload"><input type="hidden" name="slug" value="<?= e($editSlug) ?>">
                            <div class="field"><label for="logo">Unggah logo (PNG/JPG)</label><input class="input" id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp,image/gif" required></div>
                            <button class="btn btn-sm btn-block mt-1" type="submit"><?= icon('upload') ?> Unggah</button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-head"><h3><?= icon('activity') ?> Uji koneksi</h3></div>
                    <div class="card-body">
                        <form method="post" action="<?= e(url('routers')) ?>">
                            <?= csrf_field() ?><input type="hidden" name="do" value="test"><input type="hidden" name="slug" value="<?= e($editSlug) ?>">
                            <button class="btn btn-sm btn-block" type="submit"><?= icon('zap') ?> Test koneksi sekarang</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <?php page_header('Router MikroTik', 'Semua router yang terdaftar di JarMon.', '<a class="btn btn-primary" href="' . e(url('routers', ['mode' => 'add'])) . '">' . icon('plus') . ' Tambah router</a>'); ?>
    <?php if (!$routers): ?>
        <div class="card"><div class="card-body"><?= empty_state('server', 'Belum ada router', 'Tambahkan router MikroTik untuk mulai mengelola hotspot.') ?><div class="text-center mt-2"><a class="btn btn-primary" href="<?= e(url('routers', ['mode' => 'add'])) ?>"><?= icon('plus') ?> Tambah router pertama</a></div></div></div>
    <?php else: ?>
        <div class="grid grid-auto">
            <?php foreach ($routers as $slug => $item): ?>
                <?php $isCurrent = current_router_slug() === $slug; ?>
                <div class="card">
                    <div class="card-body">
                        <div class="row-flex mb-2">
                            <span class="kpi-icon <?= $isCurrent ? '' : 'blue' ?>"><?= icon('router') ?></span>
                            <div style="min-width:0">
                                <strong class="truncate" style="display:block"><?= e($item['name'] ?? $slug) ?></strong>
                                <span class="text-small text-muted mono"><?= e($item['host'] ?? '') ?>:<?= e($item['port'] ?? 8728) ?></span>
                            </div>
                            <span class="spacer"></span>
                            <?php if ($isCurrent): ?><span class="badge badge-accent">Aktif</span><?php endif; ?>
                            <?php if (!empty($item['ssl'])): ?><span class="badge badge-info">SSL</span><?php endif; ?>
                        </div>
                        <div class="keyval"><span>Hotspot</span><strong><?= e($item['hotspot_name'] ?? '-') ?: '-' ?></strong></div>
                        <div class="keyval"><span>User API</span><strong><?= e($item['user'] ?? '-') ?></strong></div>
                        <div class="keyval"><span>Interface</span><strong><?= e($item['interface'] ?? '-') ?: '-' ?></strong></div>
                        <div class="keyval"><span>DNS</span><strong class="mono"><?= e($item['dns_name'] ?? '-') ?: '-' ?></strong></div>
                    </div>
                    <div class="card-foot" style="flex-wrap:wrap">
                        <a class="btn btn-sm" href="<?= e(url('dashboard', ['r' => $slug])) ?>"><?= icon('dashboard') ?> Buka</a>
                        <a class="btn btn-sm" href="<?= e(url('routers', ['mode' => 'edit', 'slug' => $slug])) ?>"><?= icon('edit') ?> Edit</a>
                        <form method="post" action="<?= e(url('routers')) ?>" style="display:inline">
                            <?= csrf_field() ?><input type="hidden" name="do" value="test"><input type="hidden" name="slug" value="<?= e($slug) ?>">
                            <button class="btn btn-sm" type="submit"><?= icon('zap') ?> Test</button>
                        </form>
                        <form method="post" action="<?= e(url('routers')) ?>" style="display:inline;margin-left:auto" data-confirm="Hapus router <?= e($item['name'] ?? $slug) ?> dari JarMon? Konfigurasi router tidak berubah.">
                            <?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="slug" value="<?= e($slug) ?>">
                            <button class="icon-btn" type="submit" title="Hapus" style="color:var(--danger)"><?= icon('trash') ?></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
