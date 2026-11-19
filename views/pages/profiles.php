<?php
$api = jm_api();
$mode = get_str('mode', 'list');
$do = post_str('do');
$profiles = jm_api_ready() ? jm_profiles() : [];
$schedulers = jm_api_ready() ? api_comm('/system/scheduler/print') : [];
$schedulerMap = [];
foreach ($schedulers as $scheduler) {
    $schedulerMap[(string) ($scheduler['name'] ?? '')] = $scheduler;
}

$expModes = [
    '0' => 'None',
    'rem' => 'Remove',
    'ntf' => 'Notice',
    'remc' => 'Remove & Record',
    'ntfc' => 'Notice & Record',
];

if ($do === 'save' && $api) {
    $id = post_str('id');
    $name = preg_replace('/\s+/', '-', post_str('name'));
    $oldName = post_str('oldname');
    $shared = post_str('sharedusers', '1');
    $rate = post_str('ratelimit');
    $expmode = post_str('expmode', '0');
    $validity = post_str('validity');
    $price = post_str('price', '0');
    $sprice = post_str('sprice', '0');
    $lock = post_str('lockunlock', 'Disable') === 'Enable' ? 'Enable' : 'Disable';
    $pool = post_str('ppool', 'none');
    $parent = post_str('parent', 'none');

    if ($name === '') {
        jm_flash('danger', 'Nama profil wajib diisi.');
        redirect(url('profiles', ['mode' => $id ? 'edit' : 'add', 'id' => $id]));
    }
    if (!isset($expModes[$expmode])) {
        $expmode = '0';
    }
    if ($expmode !== '0' && !preg_match('/^\d+[wdhm]$/', $validity)) {
        jm_flash('danger', 'Masa berlaku harus berformat angka + w/d/h/m, contoh 30d atau 12h.');
        redirect(url('profiles', ['mode' => $id ? 'edit' : 'add', 'id' => $id]));
    }
    $onlogin = jm_profile_onlogin($expmode, (string) (float) $price, $validity, (string) (float) $sprice, $lock, $name);
    $payload = [
        'name' => $name,
        'shared-users' => $shared === '' ? '1' : $shared,
        'rate-limit' => $rate,
        'status-autorefresh' => '1m',
        'on-login' => $onlogin,
        'address-pool' => $pool,
        'parent-queue' => $parent,
    ];
    if ($id !== '') {
        $payload['.id'] = $id;
        $result = api_try('/ip/hotspot/user/profile/set', $payload);
    } else {
        $result = api_try('/ip/hotspot/user/profile/add', $payload);
    }
    if (!$result['ok']) {
        jm_flash('danger', 'Gagal menyimpan profil: ' . $result['error']);
        redirect(url('profiles', ['mode' => $id ? 'edit' : 'add', 'id' => $id]));
    }

    if ($oldName !== '' && $oldName !== $name && isset($schedulerMap[$oldName])) {
        api_try('/system/scheduler/remove', ['.id' => $schedulerMap[$oldName]['.id']]);
    }
    $sync = jm_sync_monitor($name, $expmode);
    if (!$sync['ok']) {
        jm_flash('warning', 'Profil tersimpan, tetapi monitor kedaluwarsa gagal dipasang: ' . $sync['error']);
    }
    jm_log('profile-save', $name);
    jm_flash('success', 'Profil ' . $name . ' berhasil disimpan.');
    redirect(url('profiles'));
}

if ($do === 'delete' && $api) {
    $id = post_str('id');
    $name = post_str('name');
    $result = api_try('/ip/hotspot/user/profile/remove', ['.id' => $id]);
    foreach (api_comm('/system/scheduler/print', ['?name' => $name]) as $scheduler) {
        api_try('/system/scheduler/remove', ['.id' => $scheduler['.id']]);
    }
    jm_log('profile-delete', $name);
    jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Profil ' . $name . ' dihapus.' : 'Gagal menghapus profil: ' . $result['error']);
    redirect(url('profiles'));
}

if ($do === 'repair-monitors' && $api) {
    $synced = 0;
    $failed = 0;
    foreach (jm_profiles() as $profile) {
        $profileMode = (string) ($profile['_exp']['mode'] ?? '0');
        if ($profileMode === '0') {
            continue;
        }
        $sync = jm_sync_monitor((string) $profile['name'], $profileMode);
        if ($sync['ok']) {
            $synced++;
        } else {
            $failed++;
        }
    }
    jm_log('profile-monitor-repair', $synced . ' monitor');
    jm_flash($failed ? 'warning' : 'success', $synced . ' monitor kedaluwarsa disinkronkan' . ($failed ? ', ' . $failed . ' gagal.' : '.'));
    redirect(url('profiles'));
}

$editProfile = null;
if ($mode === 'edit') {
    $editProfile = jm_profile(get_str('name'));
    if (!$editProfile) {
        $byId = api_comm('/ip/hotspot/user/profile/print', ['?.id' => get_str('id')]);
        $editProfile = $byId[0] ?? null;
    }
    if (!$editProfile) {
        jm_flash('warning', 'Profil tidak ditemukan.');
        redirect(url('profiles'));
    }
    $editProfile['_exp'] = jm_parse_onlogin((string) ($editProfile['on-login'] ?? ''));
}

$pools = jm_api_ready() ? api_comm('/ip/pool/print') : [];
$queues = jm_api_ready() ? api_comm('/queue/simple/print', ['?dynamic' => 'false']) : [];

$pageTitle = $mode === 'add' ? 'Tambah Profil' : ($mode === 'edit' ? 'Edit Profil' : 'Profil Pengguna');
$activeNav = 'profiles';
?>
<?php if ($mode === 'add' || $mode === 'edit'): ?>
    <?php
    $p = $editProfile;
    $isEdit = $mode === 'edit';
    $exp = $p['_exp'] ?? ['mode' => '0', 'price' => '', 'validity' => '', 'sprice' => '', 'lock' => 'Disable'];
    page_header($isEdit ? 'Edit profil' : 'Tambah profil', 'Profil menentukan paket, harga, dan aturan kedaluwarsa voucher.', '<a class="btn" href="' . e(url('profiles')) . '">' . icon('chevron-left') . ' Kembali</a>');
    ?>
    <form method="post" action="<?= e(url('profiles')) ?>" class="grid grid-2-1" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="save">
        <input type="hidden" name="oldname" value="<?= e($p['name'] ?? '') ?>">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($p['.id'] ?? '') ?>"><?php endif; ?>
        <div class="card">
            <div class="card-head"><h3><?= icon('user-cog') ?> Detail profil</h3></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="field"><label for="name">Nama profil</label><input class="input" id="name" name="name" value="<?= e($p['name'] ?? '') ?>" required autofocus placeholder="misal: 1 Hari"><span class="hint">Tanpa spasi — spasi otomatis diganti tanda hubung.</span></div>
                    <div class="field"><label for="sharedusers">Shared users</label><input class="input" id="sharedusers" name="sharedusers" type="number" min="1" value="<?= e($p['shared-users'] ?? '1') ?>" required></div>
                    <div class="field span-2"><label for="ratelimit">Rate limit (up/down)</label><input class="input" id="ratelimit" name="ratelimit" value="<?= e($p['rate-limit'] ?? '') ?>" placeholder="contoh: 512k/1M"><span class="hint">Kosongkan jika tanpa batas kecepatan.</span></div>
                    <div class="field"><label for="expmode">Mode kedaluwarsa</label>
                        <select class="select" id="expmode" name="expmode">
                            <?php foreach ($expModes as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $exp['mode'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label for="validity">Masa berlaku</label><input class="input" id="validity" name="validity" value="<?= e($exp['validity']) ?>" placeholder="contoh: 30d, 12h, 30m"><span class="hint">Dipakai saat mode kedaluwarsa aktif.</span></div>
                    <div class="field"><label for="price">Harga</label><div class="input-group"><input class="input" id="price" name="price" type="number" step="0.01" min="0" value="<?= e($exp['price'] !== '' ? $exp['price'] : '0') ?>"><span class="suffix"><?= e(setting('currency', 'Rp')) ?></span></div></div>
                    <div class="field"><label for="sprice">Harga jual</label><div class="input-group"><input class="input" id="sprice" name="sprice" type="number" step="0.01" min="0" value="<?= e($exp['sprice'] !== '' ? $exp['sprice'] : '0') ?>"><span class="suffix"><?= e(setting('currency', 'Rp')) ?></span></div></div>
                    <div class="field"><label for="lockunlock">Kunci perangkat</label>
                        <select class="select" id="lockunlock" name="lockunlock">
                            <option value="Disable" <?= ($exp['lock'] ?? '') !== 'Enable' ? 'selected' : '' ?>>Disable</option>
                            <option value="Enable" <?= ($exp['lock'] ?? '') === 'Enable' ? 'selected' : '' ?>>Enable (1 perangkat)</option>
                        </select>
                    </div>
                    <div class="field"><label for="ppool">Address pool</label>
                        <select class="select" id="ppool" name="ppool">
                            <option value="none">none</option>
                            <?php foreach ($pools as $pool): ?><option value="<?= e($pool['name'] ?? '') ?>" <?= ($p['address-pool'] ?? '') === ($pool['name'] ?? '') ? 'selected' : '' ?>><?= e($pool['name'] ?? '') ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field"><label for="parent">Parent queue</label>
                        <select class="select" id="parent" name="parent">
                            <option value="none">none</option>
                            <?php foreach ($queues as $queue): ?><option value="<?= e($queue['name'] ?? '') ?>" <?= ($p['parent-queue'] ?? '') === ($queue['name'] ?? '') ? 'selected' : '' ?>><?= e($queue['name'] ?? '') ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-foot">
                <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan profil</button>
                <a class="btn btn-ghost" href="<?= e(url('profiles')) ?>">Batal</a>
            </div>
        </div>
        <div class="stack">
            <div class="card">
                <div class="card-head"><h3><?= icon('info') ?> Panduan mode</h3></div>
                <div class="card-body text-small text-muted">
                    <p class="mb-2"><strong class="text">Remove</strong> — pengguna dihapus saat masa berlaku habis.</p>
                    <p class="mb-2"><strong class="text">Notice</strong> — pengguna tidak dihapus, tetapi tidak bisa login lagi.</p>
                    <p class="mb-2"><strong class="text">Record</strong> — mencatat transaksi saat login pertama sehingga muncul di laporan penjualan.</p>
                    <div class="divider"></div>
                    <p>Monitor profile otomatis dibuat sebagai scheduler di router saat mode kedaluwarsa aktif.</p>
                </div>
            </div>
            <div class="card">
                <div class="card-head"><h3><?= icon('clock') ?> Format</h3></div>
                <div class="card-body text-small">
                    <div class="keyval"><span>30 hari</span><strong class="mono">30d</strong></div>
                    <div class="keyval"><span>12 jam</span><strong class="mono">12h</strong></div>
                    <div class="keyval"><span>30 menit</span><strong class="mono">30m</strong></div>
                    <div class="keyval"><span>1 minggu</span><strong class="mono">1w</strong></div>
                </div>
            </div>
        </div>
    </form>
<?php else: ?>
    <?php
    $profileActions = '<form method="post" action="' . e(url('profiles')) . '" style="display:inline" data-confirm="Sinkronkan ulang semua monitor kedaluwarsa profil?"><input type="hidden" name="_csrf" value="' . e(jm_csrf_token()) . '"><input type="hidden" name="do" value="repair-monitors"><button class="btn" type="submit">' . icon('refresh') . ' Perbaiki monitor</button></form>'
        . '<a class="btn btn-primary" href="' . e(url('profiles', ['mode' => 'add'])) . '">' . icon('plus') . ' Tambah profil</a>';
    page_header('Profil pengguna', 'Paket harga, batas kecepatan, dan aturan kedaluwarsa.', $profileActions);
    ?>
    <?php if (!jm_api_ready()): ?>
        <div class="alert alert-warning"><?= icon('alert') ?><div>Profil tidak dapat dimuat karena router offline.</div></div>
    <?php endif; ?>
    <div class="card">
        <div class="table-toolbar">
            <div class="search-box"><?= icon('search') ?><input class="input input-sm" type="text" placeholder="Cari profil..." data-search="#profilesTable"></div>
            <span class="spacer"></span>
            <span class="badge badge-neutral"><?= fmt_number(count($profiles)) ?> profil</span>
        </div>
        <div class="table-wrap">
            <table class="data-table" id="profilesTable" data-table data-page-size="<?= (int) setting('page_size', 25) ?>">
                <thead>
                <tr>
                    <th class="sortable">Nama</th>
                    <th class="sortable">Shared</th>
                    <th>Rate limit</th>
                    <th>Mode</th>
                    <th>Masa berlaku</th>
                    <th class="sortable num">Harga</th>
                    <th class="sortable num">Harga jual</th>
                    <th>Monitor</th>
                    <th style="width:120px"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($profiles as $profile): ?>
                    <?php
                    $pexp = $profile['_exp'];
                    $monitor = $schedulerMap[(string) $profile['name']] ?? null;
                    $monitorActive = $monitor && ($monitor['disabled'] ?? '') !== 'true';
                    ?>
                    <tr>
                        <td><a class="user-link" href="<?= e(url('profiles', ['mode' => 'edit', 'name' => $profile['name']])) ?>"><?= e($profile['name']) ?></a></td>
                        <td><?= e($profile['shared-users'] ?? '1') ?></td>
                        <td class="mono text-small"><?= e($profile['rate-limit'] ?? '-') ?: '-' ?></td>
                        <td><?= e($expModes[$pexp['mode']] ?? 'None') ?></td>
                        <td><?= e($pexp['validity']) ?: '-' ?></td>
                        <td class="num"><?= e(money($pexp['price'] ?? 0)) ?></td>
                        <td class="num"><?= e(money($pexp['sprice'] ?? 0)) ?></td>
                        <td>
                            <?php if ($pexp['mode'] === '0'): ?>
                                <span class="badge badge-neutral">Tidak perlu</span>
                            <?php elseif ($monitorActive): ?>
                                <span class="badge badge-success"><span class="dot online"></span> Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-warning"><span class="dot warn"></span> Tidak aktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <a class="icon-btn" href="<?= e(url('users', ['profile' => $profile['name']])) ?>" title="Lihat pengguna"><?= icon('users') ?></a>
                            <a class="icon-btn" href="<?= e(url('profiles', ['mode' => 'edit', 'name' => $profile['name']])) ?>" title="Edit"><?= icon('edit') ?></a>
                            <form method="post" action="<?= e(url('profiles')) ?>" style="display:inline" data-confirm="Hapus profil <?= e($profile['name']) ?>? Pengguna dengan profil ini tidak akan terhapus.">
                                <?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= e($profile['.id'] ?? '') ?>"><input type="hidden" name="name" value="<?= e($profile['name']) ?>">
                                <button class="icon-btn" type="submit" title="Hapus" style="color:var(--danger)"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div data-table-empty style="display:none"><?= empty_state('user-cog', 'Belum ada profil', 'Tambah profil untuk mulai membuat voucher.') ?></div>
    </div>
<?php endif; ?>
