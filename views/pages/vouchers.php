<?php
$tab = get_str('tab', 'studio');
if (!in_array($tab, ['studio', 'quick', 'packages'], true)) {
    $tab = 'studio';
}
$do = post_str('do');
$profiles = jm_api_ready() ? jm_profiles() : [];
$profileNames = array_map(static fn($p) => $p['name'], $profiles);
$packages = jm_config()['packages'];
$servers = jm_api_ready() ? jm_servers() : [];

if (in_array($do, ['generate', 'quick'], true) && !jm_api_ready()) {
    jm_flash('danger', 'Router sedang offline. Data paket tetap tersimpan; coba lagi setelah koneksi pulih.');
    redirect(url('vouchers', ['tab' => $do === 'quick' ? 'quick' : 'studio']));
}

if ($do === 'generate' && jm_api_ready()) {
    $profileName = post_str('profile');
    if (!in_array($profileName, $profileNames, true)) {
        jm_flash('danger', 'Pilih profil yang valid.');
        redirect(url('vouchers'));
    }
    $unit = post_str('mbgb', '1048576') === '1073741824' ? 1073741824 : 1048576;
    $generated = jm_generate_vouchers([
        'qty' => post_int('qty', 10),
        'server' => post_str('server', 'all'),
        'profile' => $profileName,
        'mode' => post_str('usermode', 'vc'),
        'length' => post_int('length', 6),
        'prefix' => post_str('prefix'),
        'charset' => post_str('charset', 'lower'),
        'timelimit' => post_str('timelimit', '0'),
        'bytes' => (int) round((float) post_str('datalimit', '0') * $unit),
        'label' => post_str('comment'),
    ]);
    jm_log('voucher-generate', post_int('qty', 0) . ' voucher ' . $profileName);
    if (!$generated['items']) {
        jm_flash('danger', 'Gagal membuat voucher: ' . $generated['error']);
        redirect(url('vouchers'));
    }
    $token = jm_store_batch($generated['items'], [
        'profile' => $profileName,
        'label' => post_str('comment'),
        'mode' => post_str('usermode', 'vc'),
        'tpl' => post_str('tpl', 'default'),
        'qr' => post_str('qr', '0'),
    ]);
    jm_flash('success', count($generated['items']) . ' voucher dibuat' . ($generated['failed'] ? ', ' . $generated['failed'] . ' gagal' : '') . '.');
    redirect(url('print', ['batch' => $token, 'tpl' => post_str('tpl', 'default'), 'qr' => post_str('qr', '0') === '1' ? '1' : '0']));
}

if ($do === 'quick' && jm_api_ready()) {
    $packageId = post_str('package');
    $package = null;
    foreach ($packages as $item) {
        if (($item['id'] ?? '') === $packageId) {
            $package = $item;
            break;
        }
    }
    if (!$package) {
        jm_flash('danger', 'Paket tidak ditemukan.');
        redirect(url('vouchers', ['tab' => 'quick']));
    }
    if (!in_array((string) $package['profile'], $profileNames, true)) {
        jm_flash('danger', 'Profil paket "' . $package['name'] . '" tidak ditemukan di router. Perbarui paket terlebih dahulu.');
        redirect(url('vouchers', ['tab' => 'packages', 'edit' => $package['id']]));
    }
    $qty = max(1, min(500, post_int('qty', (int) ($package['qty'] ?? 10))));
    $mode = ($package['usermode'] ?? 'vc') === 'up' ? 'up' : 'vc';
    $charset = (string) ($package['charset'] ?? 'mix');
    if (!array_key_exists($charset, jm_charsets())) {
        $charset = 'mix';
    }
    $tpl = (string) ($package['tpl'] ?? 'default');
    if (!in_array($tpl, ['default', 'compact', 'thermal'], true)) {
        $tpl = 'default';
    }
    $qr = ($package['qr'] ?? '1') === '1' ? '1' : '0';
    $generated = jm_generate_vouchers([
        'qty' => $qty,
        'server' => post_str('server', 'all'),
        'profile' => (string) $package['profile'],
        'mode' => $mode,
        'length' => max(4, min(10, (int) ($package['length'] ?? 6))),
        'prefix' => (string) ($package['prefix'] ?? ''),
        'charset' => $charset,
        'timelimit' => (string) ($package['time_limit'] ?? '0'),
        'bytes' => (int) ($package['data_mb'] ?? 0) * 1048576,
        'label' => (string) ($package['comment'] ?? ''),
    ]);
    jm_log('voucher-quick', $qty . ' voucher ' . $package['name']);
    if (!$generated['items']) {
        jm_flash('danger', 'Gagal membuat voucher: ' . $generated['error']);
        redirect(url('vouchers', ['tab' => 'quick']));
    }
    $token = jm_store_batch($generated['items'], [
        'profile' => (string) $package['profile'],
        'label' => (string) ($package['comment'] ?? ''),
        'mode' => $mode,
        'tpl' => $tpl,
        'qr' => $qr,
    ]);
    jm_flash('success', count($generated['items']) . ' voucher paket ' . $package['name'] . ' dibuat.');
    redirect(url('print', ['batch' => $token, 'tpl' => $tpl, 'qr' => $qr]));
}

if ($do === 'package-save') {
    $id = post_str('id');
    $name = post_str('name');
    if ($name === '') {
        jm_flash('danger', 'Nama paket wajib diisi.');
        redirect(url('vouchers', ['tab' => 'packages']));
    }
    $charset = post_str('charset', 'mix');
    if (!array_key_exists($charset, jm_charsets())) {
        $charset = 'mix';
    }
    $tpl = post_str('tpl', 'default');
    if (!in_array($tpl, ['default', 'compact', 'thermal'], true)) {
        $tpl = 'default';
    }
    $package = [
        'id' => $id !== '' ? $id : 'pkg-' . bin2hex(random_bytes(4)),
        'name' => $name,
        'profile' => post_str('profile'),
        'qty' => max(1, post_int('qty', 10)),
        'price' => max(0, (float) post_str('price', '0')),
        'validity' => post_str('validity'),
        'time_limit' => post_str('time_limit'),
        'data_mb' => max(0, post_int('data_mb', 0)),
        'comment' => preg_replace('/[^A-Za-z0-9]/', '', post_str('comment')),
        'usermode' => post_str('usermode', 'vc') === 'up' ? 'up' : 'vc',
        'prefix' => preg_replace('/[^A-Za-z0-9]/', '', post_str('prefix')),
        'length' => max(4, min(10, post_int('length', 6))),
        'charset' => $charset,
        'tpl' => $tpl,
        'qr' => post_str('qr', '0') === '1' ? '1' : '0',
    ];
    $config = jm_config();
    $found = false;
    foreach ($config['packages'] as $index => $existing) {
        if (($existing['id'] ?? '') === $package['id']) {
            $config['packages'][$index] = $package;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $config['packages'][] = $package;
    }
    jm_config_save($config);
    jm_log('package-save', $name);
    jm_flash('success', 'Paket ' . $name . ' disimpan.');
    redirect(url('vouchers', ['tab' => 'packages']));
}

if ($do === 'batch-delete') {
    $token = post_str('token');
    if ($token !== '') {
        jm_batch_delete($token);
        jm_flash('success', 'Riwayat batch dihapus. Voucher di router tidak berubah.');
    }
    redirect(url('vouchers', ['tab' => $tab]));
}

if ($do === 'package-delete') {
    $id = post_str('id');
    $config = jm_config();
    $config['packages'] = array_values(array_filter($config['packages'], static fn($pkg) => ($pkg['id'] ?? '') !== $id));
    jm_config_save($config);
    jm_flash('success', 'Paket dihapus.');
    redirect(url('vouchers', ['tab' => 'packages']));
}

$editPackage = null;
if ($tab === 'packages' && get_str('edit') !== '') {
    foreach ($packages as $pkg) {
        if (($pkg['id'] ?? '') === get_str('edit')) {
            $editPackage = $pkg;
            break;
        }
    }
}
$allBatches = jm_batches_for_router(current_router_slug());
$recentBatches = array_slice($allBatches, 0, 6, true);
$pageTitle = 'Voucher Studio';
$activeNav = 'vouchers:' . $tab;
?>
<?php page_header('Voucher Studio', 'Buat voucher baru, cetak cepat dari paket, atau kelola template paket.', '<a class="btn" href="' . e(url('users')) . '">' . icon('users') . ' Daftar pengguna</a>'); ?>

<div class="tabs mb-2">
    <a class="tab <?= $tab === 'studio' ? 'active' : '' ?>" href="<?= e(url('vouchers')) ?>"><?= icon('ticket') ?> Studio</a>
    <a class="tab <?= $tab === 'quick' ? 'active' : '' ?>" href="<?= e(url('vouchers', ['tab' => 'quick'])) ?>"><?= icon('zap') ?> Cetak cepat</a>
    <a class="tab <?= $tab === 'packages' ? 'active' : '' ?>" href="<?= e(url('vouchers', ['tab' => 'packages'])) ?>"><?= icon('package') ?> Paket</a>
</div>

<?php if ($tab === 'studio'): ?>
    <form method="post" action="<?= e(url('vouchers')) ?>" class="grid grid-2-1" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="generate">
        <div class="stack">
            <div class="card">
                <div class="card-head"><h3><?= icon('ticket') ?> Buat voucher</h3><span class="spacer"></span><span class="badge badge-accent">username otomatis</span></div>
                <div class="card-body">
                    <div class="form-grid cols-3">
                        <div class="field"><label for="qty">Jumlah</label><input class="input" id="qty" name="qty" type="number" min="1" max="500" value="10" required></div>
                        <div class="field"><label for="profile">Profil</label>
                            <select class="select" id="profile" name="profile" required>
                                <?php if (!$profiles): ?><option value="">— belum ada profil —</option><?php endif; ?>
                                <?php foreach ($profiles as $profile): ?><option value="<?= e($profile['name']) ?>"><?= e($profile['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field"><label for="server">Server</label>
                            <select class="select" id="server" name="server"><option value="all">all</option><?php foreach ($servers as $server): ?><option value="<?= e($server['name']) ?>"><?= e($server['name']) ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="field"><label for="usermode">Mode</label><select class="select" id="usermode" name="usermode"><option value="vc">Username = Password</option><option value="up">Username &amp; Password</option></select></div>
                        <div class="field"><label for="length">Panjang</label><select class="select" id="length" name="length"><?php foreach ([4, 5, 6, 7, 8, 9, 10] as $len): ?><option value="<?= $len ?>" <?= $len === 6 ? 'selected' : '' ?>><?= $len ?></option><?php endforeach; ?></select></div>
                        <div class="field"><label for="charset">Karakter</label><select class="select" id="charset" name="charset"><option value="lower">abcd</option><option value="upper">ABCD</option><option value="upplow">aBcD</option><option value="mix">5ab2c</option><option value="mix1">5AB2C</option><option value="mix2">5aB2c</option><option value="num">2345</option></select></div>
                        <div class="field"><label for="prefix">Prefix</label><input class="input" id="prefix" name="prefix" maxlength="6" placeholder="VC"></div>
                        <div class="field"><label for="comment">Label batch</label><input class="input" id="comment" name="comment" maxlength="12" placeholder="1D"></div>
                        <div class="field"><label for="timelimit">Batas waktu</label><input class="input" id="timelimit" name="timelimit" placeholder="1d"></div>
                        <div class="field"><label for="datalimit">Batas kuota</label>
                            <div class="input-group">
                                <input class="input" id="datalimit" name="datalimit" type="number" min="0" step="0.01" placeholder="0">
                                <select class="select" name="mbgb" style="width:92px"><option value="1048576">MB</option><option value="1073741824">GB</option></select>
                            </div>
                        </div>
                        <div class="field span-2"><label>Template cetak</label>
                            <div class="row-flex" style="gap:14px">
                                <label class="check"><input type="radio" name="tpl" value="default" checked> Standar</label>
                                <label class="check"><input type="radio" name="tpl" value="compact"> Kompakt</label>
                                <label class="check"><input type="radio" name="tpl" value="thermal"> Thermal</label>
                                <label class="check"><input type="checkbox" name="qr" value="1" checked> QR code</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-foot">
                    <button class="btn btn-primary" type="submit"><?= icon('zap') ?> Generate &amp; cetak</button>
                    <span class="text-small text-muted">Voucher langsung dibuat di router.</span>
                </div>
            </div>
        </div>
        <div class="stack">
            <div class="card">
                <div class="card-head"><h3><?= icon('package') ?> Paket cepat</h3></div>
                <div class="card-body">
                    <?php if (!$packages): ?>
                        <?= empty_state('package', 'Belum ada paket', 'Tambahkan paket pada tab Paket.') ?>
                    <?php else: ?>
                        <?php foreach ($packages as $package): ?>
                            <button type="button" class="btn btn-block mb-2 preset" style="justify-content:flex-start"
                                data-profile="<?= e($package['profile'] ?? '') ?>"
                                data-qty="<?= (int) ($package['qty'] ?? 10) ?>"
                                data-timelimit="<?= e($package['time_limit'] ?? '') ?>"
                                data-data="<?= (int) ($package['data_mb'] ?? 0) ?>"
                                data-label="<?= e($package['comment'] ?? '') ?>"
                                data-mode="<?= e($package['usermode'] ?? 'vc') ?>"
                                data-prefix="<?= e($package['prefix'] ?? '') ?>"
                                data-length="<?= (int) ($package['length'] ?? 6) ?>"
                                data-charset="<?= e($package['charset'] ?? 'mix') ?>"
                                data-tpl="<?= e($package['tpl'] ?? 'default') ?>"
                                data-qr="<?= e($package['qr'] ?? '1') ?>">
                                <span style="flex:1;text-align:left"><strong><?= e($package['name']) ?></strong><br><small class="text-muted"><?= e($package['validity'] ?: '-') ?> &middot; <?= e(money($package['price'] ?? 0)) ?></small></span>
                                <?= icon('chevron-right') ?>
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-head"><h3><?= icon('printer') ?> Cetak ulang</h3></div>
                <div class="card-body">
                    <form method="get" action="<?= e(url('print')) ?>">
                        <?php if (current_router_slug()): ?><input type="hidden" name="r" value="<?= e(current_router_slug()) ?>"><?php endif; ?>
                        <input type="hidden" name="p" value="print">
                        <div class="field"><label for="commentPrint">Komentar batch</label><input class="input" id="commentPrint" name="comment" placeholder="misal: vc-1D-111926-123"><span class="hint">Cetak semua voucher yang belum terpakai dengan komentar ini.</span></div>
                        <button class="btn btn-sm btn-block mt-1" type="submit"><?= icon('printer') ?> Buka cetak</button>
                    </form>
                    <?php if ($recentBatches): ?>
                        <div class="divider"></div>
                        <div class="text-small text-muted mb-1">Riwayat batch (tersimpan di server)</div>
                        <?= jm_batch_history($recentBatches) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
    <script>
        document.querySelectorAll('.preset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var set = function (id, value) {
                    var el = document.getElementById(id);
                    if (el && value !== undefined && value !== '') el.value = value;
                };
                set('profile', btn.dataset.profile);
                set('qty', btn.dataset.qty);
                set('timelimit', btn.dataset.timelimit);
                set('datalimit', btn.dataset.data && btn.dataset.data !== '0' ? btn.dataset.data : '');
                set('comment', btn.dataset.label);
                set('usermode', btn.dataset.mode);
                set('prefix', btn.dataset.prefix);
                set('length', btn.dataset.length);
                set('charset', btn.dataset.charset);
                if (btn.dataset.label) {
                    var p = document.getElementById('prefix');
                    if (p && !p.value) p.value = btn.dataset.label;
                }
                var tpl = document.querySelector('input[name="tpl"][value="' + btn.dataset.tpl + '"]');
                if (tpl) tpl.checked = true;
                var qr = document.querySelector('input[name="qr"]');
                if (qr) qr.checked = btn.dataset.qr === '1';
                var profileSelect = document.getElementById('profile');
                var exists = profileSelect && Array.prototype.some.call(profileSelect.options, function (opt) { return opt.value === btn.dataset.profile; });
                jmToast(exists ? 'Paket diterapkan ke semua pengaturan voucher.' : 'Paket diterapkan, tetapi profilnya belum ada di router.', exists ? 'success' : 'warning');
            });
        });
    </script>

<?php elseif ($tab === 'quick'): ?>
    <?php
    $readyPackages = 0;
    foreach ($packages as $pkg) {
        if (in_array((string) ($pkg['profile'] ?? ''), $profileNames, true)) {
            $readyPackages++;
        }
    }
    ?>
    <div class="alert alert-info mb-2"><?= icon('database') ?><div>
        <strong>Batch voucher tersimpan otomatis.</strong>
        Kredensial voucher disimpan di server, jadi Anda bisa mencetak ulang tanpa membuat voucher baru atau memasukkan ulang data.
    </div></div>
    <?php if (!$packages): ?>
        <div class="card"><div class="card-body"><?= empty_state('package', 'Belum ada paket', 'Buat paket terlebih dahulu pada tab Paket.') ?></div></div>
    <?php else: ?>
        <div class="row-flex wrap mb-2">
            <span class="badge badge-neutral"><?= $readyPackages ?>/<?= count($packages) ?> paket siap</span>
            <?php if ($readyPackages < count($packages)): ?>
                <span class="badge badge-warning"><?= count($packages) - $readyPackages ?> paket belum terhubung ke profil router</span>
            <?php endif; ?>
            <span class="spacer"></span>
            <a class="btn btn-sm" href="<?= e(url('vouchers', ['tab' => 'packages'])) ?>"><?= icon('settings') ?> Atur paket</a>
        </div>
        <div class="grid grid-3">
            <?php foreach ($packages as $package): ?>
                <?php
                $pkgOk = in_array((string) ($package['profile'] ?? ''), $profileNames, true);
                $pkgTpl = (string) ($package['tpl'] ?? 'default');
                if (!in_array($pkgTpl, ['default', 'compact', 'thermal'], true)) {
                    $pkgTpl = 'default';
                }
                $pkgMode = ($package['usermode'] ?? 'vc') === 'up' ? 'User + Password' : 'Kode tunggal';
                ?>
                <div class="card">
                    <div class="card-body">
                        <div class="row-flex mb-2">
                            <span class="kpi-icon <?= $pkgOk ? '' : 'orange' ?>"><?= icon('package') ?></span>
                            <div style="min-width:0"><strong class="truncate" style="display:block"><?= e($package['name']) ?></strong><div class="text-small text-muted"><?= e($package['profile'] ?: 'profil belum diatur') ?></div></div>
                            <span class="spacer"></span>
                            <?php if (!$pkgOk): ?><span class="badge badge-warning">Profil hilang</span><?php else: ?><span class="badge badge-success">Siap</span><?php endif; ?>
                        </div>
                        <div class="keyval"><span>Harga</span><strong><?= e(money($package['price'] ?? 0)) ?></strong></div>
                        <div class="keyval"><span>Mode voucher</span><strong><?= e($pkgMode) ?></strong></div>
                        <div class="keyval"><span>Masa berlaku</span><strong><?= e($package['validity'] ?: '-') ?></strong></div>
                        <div class="keyval"><span>Batas waktu</span><strong><?= e($package['time_limit'] ?: '-') ?></strong></div>
                        <div class="keyval"><span>Kuota</span><strong><?= (int) ($package['data_mb'] ?? 0) > 0 ? (int) $package['data_mb'] . ' MB' : 'Unlimited' ?></strong></div>
                        <div class="keyval"><span>Template cetak</span><strong><?= e(ucfirst($pkgTpl)) ?> &middot; <?= ($package['qr'] ?? '1') === '1' ? 'QR' : 'tanpa QR' ?></strong></div>
                        <?php if ($pkgOk): ?>
                            <form method="post" action="<?= e(url('vouchers', ['tab' => 'quick'])) ?>" class="row-flex mt-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="do" value="quick">
                                <input type="hidden" name="package" value="<?= e($package['id']) ?>">
                                <input class="input input-sm" type="number" name="qty" min="1" max="500" value="<?= (int) ($package['qty'] ?? 10) ?>" style="width:80px" aria-label="Jumlah" title="Jumlah voucher">
                                <button class="btn btn-primary btn-sm" type="submit" style="flex:1"><?= icon('printer') ?> Buat &amp; cetak</button>
                            </form>
                        <?php else: ?>
                            <a class="btn btn-sm btn-warning btn-block mt-2" href="<?= e(url('vouchers', ['tab' => 'packages', 'edit' => $package['id']])) ?>"><?= icon('settings') ?> Hubungkan profil paket</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card mt-2">
            <div class="card-head"><h3><?= icon('clock') ?> Riwayat batch voucher</h3><span class="spacer"></span><span class="badge badge-neutral"><?= count($allBatches) ?> batch tersimpan</span></div>
            <div class="card-body">
                <?= jm_batch_history(array_slice($allBatches, 0, 8, true), 'Belum ada batch. Buat voucher dari paket di atas.') ?>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="grid grid-2-1">
        <div class="card">
            <div class="card-head"><h3><?= icon('package') ?> Daftar paket</h3><span class="spacer"></span><span class="badge badge-neutral"><?= count($packages) ?> paket</span></div>
            <div class="table-wrap">
                <table class="data-table" data-table data-page-size="20">
                    <thead><tr><th>Nama</th><th>Profil</th><th class="num">Qty</th><th class="num">Harga</th><th>Masa berlaku</th><th>Format</th><th style="width:100px"></th></tr></thead>
                    <tbody>
                    <?php foreach ($packages as $package): ?>
                        <?php $pkgOk = in_array((string) ($package['profile'] ?? ''), $profileNames, true); ?>
                        <tr>
                            <td><strong><?= e($package['name']) ?></strong><div class="text-small text-muted"><?= e($package['time_limit'] ?: '-') ?> &middot; <?= (int) ($package['data_mb'] ?? 0) > 0 ? (int) $package['data_mb'] . ' MB' : 'Unlimited' ?></div></td>
                            <td><?php if (!$pkgOk): ?><span class="badge badge-warning"><?= e($package['profile'] ?: 'belum diatur') ?></span><?php else: ?><?= e($package['profile']) ?><?php endif; ?></td>
                            <td class="num"><?= (int) ($package['qty'] ?? 10) ?></td>
                            <td class="num"><?= e(money($package['price'] ?? 0)) ?></td>
                            <td><?= e($package['validity'] ?: '-') ?></td>
                            <td class="text-small">
                                <span class="badge badge-neutral"><?= ($package['usermode'] ?? 'vc') === 'up' ? 'User+Pass' : 'Kode' ?></span>
                                <span class="badge badge-neutral"><?= e(ucfirst($package['tpl'] ?? 'default')) ?></span>
                            </td>
                            <td class="actions-cell">
                                <a class="icon-btn" href="<?= e(url('vouchers', ['tab' => 'packages', 'edit' => $package['id']])) ?>" title="Edit"><?= icon('edit') ?></a>
                                <form method="post" action="<?= e(url('vouchers', ['tab' => 'packages'])) ?>" style="display:inline" data-confirm="Hapus paket <?= e($package['name']) ?>?">
                                    <?= csrf_field() ?><input type="hidden" name="do" value="package-delete"><input type="hidden" name="id" value="<?= e($package['id']) ?>">
                                    <button class="icon-btn" type="submit" title="Hapus" style="color:var(--danger)"><?= icon('trash') ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h3><?= icon($editPackage ? 'edit' : 'plus') ?> <?= $editPackage ? 'Edit paket' : 'Tambah paket' ?></h3></div>
            <form method="post" action="<?= e(url('vouchers', ['tab' => 'packages'])) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="package-save">
                <input type="hidden" name="id" value="<?= e($editPackage['id'] ?? '') ?>">
                <div class="card-body">
                    <div class="form-grid cols-1">
                        <div class="field"><label for="pname">Nama paket</label><input class="input" id="pname" name="name" value="<?= e($editPackage['name'] ?? '') ?>" required placeholder="Paket 1 Hari"></div>
                        <div class="field"><label for="pprofile">Profil router</label>
                            <select class="select" id="pprofile" name="profile">
                                <option value="">— pilih profil —</option>
                                <?php foreach ($profiles as $profile): ?><option value="<?= e($profile['name']) ?>" <?= ($editPackage['profile'] ?? '') === $profile['name'] ? 'selected' : '' ?>><?= e($profile['name']) ?></option><?php endforeach; ?>
                            </select>
                            <span class="hint">Cetak Cepat memakai profil ini. <?= !$profiles ? 'Router offline: sambungkan router untuk memilih profil.' : '' ?></span>
                        </div>
                        <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
                            <div class="field"><label for="pqty">Qty default</label><input class="input" id="pqty" name="qty" type="number" min="1" value="<?= (int) ($editPackage['qty'] ?? 10) ?>"></div>
                            <div class="field"><label for="pprice">Harga</label><input class="input" id="pprice" name="price" type="number" min="0" step="0.01" value="<?= e($editPackage['price'] ?? 0) ?>"></div>
                        </div>
                        <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
                            <div class="field"><label for="pvalid">Masa berlaku</label><input class="input" id="pvalid" name="validity" value="<?= e($editPackage['validity'] ?? '') ?>" placeholder="1d"></div>
                            <div class="field"><label for="ptime">Batas waktu</label><input class="input" id="ptime" name="time_limit" value="<?= e($editPackage['time_limit'] ?? '') ?>" placeholder="1d"></div>
                        </div>
                        <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
                            <div class="field"><label for="pdata">Kuota (MB)</label><input class="input" id="pdata" name="data_mb" type="number" min="0" value="<?= (int) ($editPackage['data_mb'] ?? 0) ?>"><span class="hint">0 = unlimited</span></div>
                            <div class="field"><label for="plabel">Label voucher</label><input class="input" id="plabel" name="comment" maxlength="12" value="<?= e($editPackage['comment'] ?? '') ?>" placeholder="1D"></div>
                        </div>
                        <div class="divider"></div>
                        <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
                            <div class="field"><label for="pmode">Mode voucher</label>
                                <select class="select" id="pmode" name="usermode">
                                    <option value="vc" <?= ($editPackage['usermode'] ?? 'vc') === 'vc' ? 'selected' : '' ?>>Username = Password</option>
                                    <option value="up" <?= ($editPackage['usermode'] ?? 'vc') === 'up' ? 'selected' : '' ?>>Username &amp; Password</option>
                                </select>
                            </div>
                            <div class="field"><label for="pprefix">Prefix</label><input class="input" id="pprefix" name="prefix" maxlength="6" value="<?= e($editPackage['prefix'] ?? '') ?>" placeholder="VC"><span class="hint">Opsional.</span></div>
                        </div>
                        <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
                            <div class="field"><label for="plength">Panjang karakter</label>
                                <select class="select" id="plength" name="length">
                                    <?php foreach ([4, 5, 6, 7, 8, 9, 10] as $len): ?><option value="<?= $len ?>" <?= (int) ($editPackage['length'] ?? 6) === $len ? 'selected' : '' ?>><?= $len ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field"><label for="pcharset">Jenis karakter</label>
                                <select class="select" id="pcharset" name="charset">
                                    <?php foreach (jm_charsets() as $key => $chars): ?>
                                        <option value="<?= e($key) ?>" <?= ($editPackage['charset'] ?? 'mix') === $key ? 'selected' : '' ?>><?= e($key) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
                            <div class="field"><label for="ptpl">Template cetak</label>
                                <select class="select" id="ptpl" name="tpl">
                                    <?php foreach (['default' => 'Standar', 'compact' => 'Kompakt', 'thermal' => 'Thermal'] as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= ($editPackage['tpl'] ?? 'default') === $key ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field"><label>QR code</label>
                                <label class="check"><input type="checkbox" name="qr" value="1" <?= ($editPackage['qr'] ?? '1') === '1' ? 'checked' : '' ?>> Sertakan QR login</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-foot">
                    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan paket</button>
                    <?php if ($editPackage): ?><a class="btn btn-ghost" href="<?= e(url('vouchers', ['tab' => 'packages'])) ?>">Batal edit</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
