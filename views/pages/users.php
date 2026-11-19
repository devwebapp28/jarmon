<?php
$api = jm_api();
$do = post_str('do');
$mode = get_str('mode', 'list');
$profileFilter = get_str('profile', 'all');
$statusFilter = get_str('status', 'all');
$returnUrl = function () use ($profileFilter, $statusFilter) {
    return url('users', ['profile' => $profileFilter, 'status' => $statusFilter]);
};

$servers = jm_api_ready() ? jm_servers() : [];
$profiles = jm_api_ready() ? jm_profiles() : [];
$profileNames = array_map(static fn($p) => $p['name'], $profiles);

$charsets = [
    'lower' => 'abcdefghijkmnprstuvwxyz',
    'upper' => 'ABCDEFGHJKLMNPRSTUVWXYZ',
    'upplow' => 'ABCDEFGHJKLMNPRSTUVWXYZabcdefghijkmnprstuvwxyz',
    'mix' => '23456789abcdefghijkmnprstuvwxyz',
    'mix1' => '23456789ABCDEFGHJKLMNPRSTUVWXYZ',
    'mix2' => '23456789ABCDEFGHJKMNPRSTUVWXYZabcdefghikmnprstuvwxyz',
    'num' => '23456789',
];

/* ------------------------------------------------------------- actions */
if ($do !== '' && !$api) {
    jm_flash('danger', 'Router sedang offline. Perubahan tidak dikirim; data di panel tetap aman.');
    redirect($do === 'generate' ? url('users', ['mode' => 'generate']) : $returnUrl());
}
if ($do !== '' && $api) {
    if ($do === 'create' || $do === 'update') {
        $name = post_str('name');
        $pass = post_str('pass');
        $profile = post_str('profile');
        $server = post_str('server', 'all');
        $timelimit = post_str('timelimit', '0');
        $datalimitValue = (float) post_str('datalimit', '0');
        $unit = post_str('mbgb', '1048576') === '1073741824' ? 1073741824 : 1048576;
        $bytes = (int) round($datalimitValue * $unit);
        $commentRaw = post_str('comment');
        $mac = post_str('mac');
        $disabled = post_str('disabled', 'no') === 'yes' ? 'yes' : 'no';
        $modeUser = $name === $pass ? 'vc' : 'up';

        if ($name === '' || $pass === '' || $profile === '') {
            jm_flash('danger', 'Nama, password, dan profil wajib diisi.');
        } elseif (preg_match('/\s/', $name)) {
            jm_flash('danger', 'Nama pengguna tidak boleh mengandung spasi.');
        } elseif (!in_array($profile, $profileNames, true)) {
            jm_flash('danger', 'Profil yang dipilih tidak ditemukan.');
        } else {
            $comment = $commentRaw;
            if ($comment === '' || (!str_starts_with($comment, 'vc-') && !str_starts_with($comment, 'up-') && !jm_is_expiry_comment($comment))) {
                $comment = jm_user_comment($commentRaw, $modeUser);
            }
            $payload = [
                'server' => $server,
                'name' => $name,
                'password' => $pass,
                'profile' => $profile,
                'limit-uptime' => $timelimit === '' ? '0' : $timelimit,
                'limit-bytes-total' => (string) max(0, $bytes),
                'comment' => $comment,
                'disabled' => $disabled,
            ];
            if ($mac !== '') {
                $payload['mac-address'] = $mac;
            }
            if ($do === 'create') {
                $result = api_try('/ip/hotspot/user/add', $payload);
            } else {
                $payload['.id'] = post_str('id');
                $result = api_try('/ip/hotspot/user/set', $payload);
            }
            if ($result['ok']) {
                jm_log($do === 'create' ? 'user-create' : 'user-update', $name);
                jm_flash('success', $do === 'create' ? 'Pengguna ' . $name . ' berhasil dibuat.' : 'Pengguna ' . $name . ' berhasil diperbarui.');
                if ($do === 'create') {
                    $found = api_comm('/ip/hotspot/user/print', ['?name' => $name]);
                    if ($found) {
                        redirect(url('users', ['mode' => 'edit', 'id' => $found[0]['.id'] ?? '']));
                    }
                }
                redirect($returnUrl());
            }
            jm_flash('danger', 'Gagal menyimpan pengguna: ' . $result['error']);
        }
        redirect($returnUrl());
    }

    if ($do === 'delete' || $do === 'enable' || $do === 'disable' || $do === 'reset' || $do === 'kick') {
        $id = post_str('id');
        $name = post_str('name');
        if ($do === 'delete') {
            $result = api_try('/ip/hotspot/user/remove', ['.id' => $id]);
            if ($result['ok']) {
                jm_log('user-delete', $name);
                jm_flash('success', 'Pengguna ' . $name . ' dihapus.');
            } else {
                jm_flash('danger', 'Gagal menghapus: ' . $result['error']);
            }
            redirect($returnUrl());
        }
        if ($do === 'enable' || $do === 'disable') {
            $result = api_try('/ip/hotspot/user/set', ['.id' => $id, 'disabled' => $do === 'enable' ? 'no' : 'yes']);
            jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Status pengguna diperbarui.' : 'Gagal: ' . $result['error']);
            jm_log('user-' . $do, $name);
            redirect($returnUrl());
        }
        if ($do === 'reset') {
            $comment = post_str('comment');
            $base = jm_strip_expiry_comment($comment);
            if ($base !== '' && !str_starts_with($base, 'vc-') && !str_starts_with($base, 'up-')) {
                $base = jm_user_comment($base, 'vc');
            }
            $result = api_try('/ip/hotspot/user/set', ['.id' => $id, 'limit-uptime' => '0', 'comment' => $base]);
            api_try('/ip/hotspot/user/reset-counters', ['.id' => $id]);
            $schedulers = api_comm('/system/scheduler/print', ['?name' => $name]);
            foreach ($schedulers as $scheduler) {
                api_try('/system/scheduler/remove', ['.id' => $scheduler['.id']]);
            }
            jm_log('user-reset', $name);
            jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Masa aktif ' . $name . ' direset.' : 'Gagal reset: ' . $result['error']);
            redirect(url('users', ['mode' => 'edit', 'id' => $id]));
        }
        if ($do === 'kick') {
            $active = api_comm('/ip/hotspot/active/print', ['?user' => $name]);
            $count = 0;
            foreach ($active as $session) {
                $r = api_try('/ip/hotspot/active/remove', ['.id' => $session['.id']]);
                if ($r['ok']) {
                    $count++;
                }
            }
            jm_log('user-kick', $name);
            jm_flash($count ? 'success' : 'warning', $count ? $count . ' sesi ' . $name . ' diputus.' : 'Tidak ada sesi aktif untuk ' . $name . '.');
            redirect($returnUrl());
        }
    }

    if ($do === 'batch') {
        $ids = post_array('ids');
        $action = post_str('action');
        $okCount = 0;
        $failCount = 0;
        if ($action === 'print' && $ids) {
            $items = [];
            foreach ($ids as $id) {
                $user = jm_user($id);
                if (!$user) {
                    continue;
                }
                $profile = jm_profile((string) $user['profile']);
                $exp = $profile['_exp'] ?? ['validity' => '', 'price' => '', 'sprice' => ''];
                $items[] = [
                    'username' => (string) $user['name'],
                    'password' => (string) ($user['password'] ?? ''),
                    'profile' => (string) $user['profile'],
                    'validity' => (string) ($exp['validity'] ?? ''),
                    'price' => (string) ($exp['price'] ?? ''),
                    'sprice' => (string) ($exp['sprice'] ?? ''),
                    'time_limit' => (string) ($user['limit-uptime'] ?? ''),
                    'data_limit' => (string) ($user['limit-bytes-total'] ?? ''),
                    'comment' => (string) ($user['comment'] ?? ''),
                    'mode' => ((string) ($user['name'] ?? '')) === ((string) ($user['password'] ?? '')) ? 'vc' : 'up',
                ];
            }
            if ($items) {
                $token = jm_store_batch($items, ['label' => 'pilihan pengguna', 'tpl' => post_str('tpl', 'default'), 'qr' => post_str('qr', '1')]);
                redirect(url('print', ['batch' => $token, 'tpl' => post_str('tpl', 'default'), 'qr' => '1']));
            }
            jm_flash('warning', 'Tidak ada pengguna yang bisa dicetak.');
            redirect($returnUrl());
        }
        foreach ($ids as $id) {
            if ($action === 'delete') {
                $result = api_try('/ip/hotspot/user/remove', ['.id' => $id]);
            } else {
                $result = api_try('/ip/hotspot/user/set', ['.id' => $id, 'disabled' => $action === 'enable' ? 'no' : 'yes']);
            }
            $result['ok'] ? $okCount++ : $failCount++;
        }
        $labels = ['delete' => 'dihapus', 'enable' => 'diaktifkan', 'disable' => 'dinonaktifkan'];
        jm_log('user-batch', $action . ' (' . $okCount . ')');
        jm_flash($failCount ? 'warning' : 'success', $okCount . ' pengguna ' . ($labels[$action] ?? 'diproses') . ($failCount ? ', ' . $failCount . ' gagal.' : '.'));
        redirect($returnUrl());
    }

    if ($do === 'generate') {
        $qty = max(1, min(500, post_int('qty', 1)));
        $profileName = post_str('profile');
        if (!in_array($profileName, $profileNames, true)) {
            jm_flash('danger', 'Pilih profil yang valid untuk generate voucher.');
            redirect(url('users', ['mode' => 'generate']));
        }
        $dataValue = (float) post_str('datalimit', '0');
        $dataUnit = post_str('mbgb', '1048576') === '1073741824' ? 1073741824 : 1048576;
        $generated = jm_generate_vouchers([
            'qty' => $qty,
            'server' => post_str('server', 'all'),
            'profile' => $profileName,
            'mode' => post_str('usermode', 'vc'),
            'length' => post_int('length', 6),
            'prefix' => post_str('prefix'),
            'charset' => post_str('charset', 'lower'),
            'timelimit' => post_str('timelimit', '0'),
            'bytes' => (int) round($dataValue * $dataUnit),
            'label' => post_str('comment'),
        ]);
        jm_log('user-generate', $qty . ' voucher ' . $profileName);
        if (!$generated['items']) {
            jm_flash('danger', 'Gagal membuat voucher. ' . $generated['error']);
            redirect(url('users', ['mode' => 'generate']));
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
}

/* ------------------------------------------------------------ list data */
$users = [];
$activeUsers = [];
$activeNames = [];
if (jm_api_ready()) {
    $users = jm_users($profileFilter !== 'all' ? ['profile' => $profileFilter] : []);
    $activeUsers = jm_active_users();
    foreach ($activeUsers as $session) {
        $activeNames[(string) ($session['user'] ?? '')] = true;
    }
}

$list = [];
foreach ($users as $user) {
    $name = (string) ($user['name'] ?? '');
    $pass = (string) ($user['password'] ?? '');
    $comment = (string) ($user['comment'] ?? '');
    $limit = (string) ($user['limit-uptime'] ?? '');
    $isExpired = $limit === '1s';
    $isDisabled = ($user['disabled'] ?? '') === 'true';
    $isActive = isset($activeNames[$name]);
    $isReady = !$isExpired && !$isDisabled && (($user['uptime'] ?? '') === '' || ($user['uptime'] ?? '') === '0s') && (str_starts_with($comment, 'vc-') || str_starts_with($comment, 'up-'));
    if ($statusFilter === 'active' && !$isActive) continue;
    if ($statusFilter === 'expired' && !$isExpired) continue;
    if ($statusFilter === 'disabled' && !$isDisabled) continue;
    if ($statusFilter === 'ready' && !$isReady) continue;
    $user['_status'] = $isDisabled ? 'disabled' : ($isExpired ? 'expired' : ($isActive ? 'active' : ($isReady ? 'ready' : 'used')));
    $user['_mode'] = $name === $pass ? 'vc' : 'up';
    $list[] = $user;
}

if (get_str('export') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="jarmon-users-' . date('Ymd-His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['name', 'password', 'profile', 'server', 'uptime', 'bytes-in', 'bytes-out', 'limit-uptime', 'limit-bytes-total', 'comment', 'disabled']);
    foreach ($list as $user) {
        fputcsv($out, [$user['name'] ?? '', $user['password'] ?? '', $user['profile'] ?? '', $user['server'] ?? '', $user['uptime'] ?? '', $user['bytes-in'] ?? 0, $user['bytes-out'] ?? 0, $user['limit-uptime'] ?? '', $user['limit-bytes-total'] ?? '', $user['comment'] ?? '', $user['disabled'] ?? 'false']);
    }
    fclose($out);
    exit;
}

/* --------------------------------------------------------- form context */
$editUser = null;
if ($mode === 'edit') {
    $editUser = jm_user(get_str('id'));
    if (!$editUser) {
        jm_flash('warning', 'Pengguna tidak ditemukan.');
        redirect(url('users'));
    }
    $editUser['_mode'] = ($editUser['name'] ?? '') === ($editUser['password'] ?? '') ? 'vc' : 'up';
    $mode = 'edit';
}
$formServers = $servers;
$dataLimitValue = '';
$dataLimitUnit = '1048576';
if ($editUser) {
    $bytes = (float) ($editUser['limit-bytes-total'] ?? 0);
    if ($bytes >= 1073741824) {
        $dataLimitValue = rtrim(rtrim(number_format($bytes / 1073741824, 2, '.', ''), '0'), '.');
        $dataLimitUnit = '1073741824';
    } elseif ($bytes > 0) {
        $dataLimitValue = rtrim(rtrim(number_format($bytes / 1048576, 2, '.', ''), '0'), '.');
    }
}
$selectedProfile = $editUser['profile'] ?? ($profileFilter !== 'all' ? $profileFilter : ($profileNames[0] ?? ''));
$expiredComment = $editUser && jm_is_expiry_comment((string) ($editUser['comment'] ?? ''));
$statusCounts = ['all' => count($users), 'active' => 0, 'ready' => 0, 'expired' => 0, 'disabled' => 0];
foreach ($users as $u) {
    $c = (string) ($u['comment'] ?? '');
    if (($u['disabled'] ?? '') === 'true') $statusCounts['disabled']++;
    if (($u['limit-uptime'] ?? '') === '1s') $statusCounts['expired']++;
    if (isset($activeNames[(string) ($u['name'] ?? '')])) $statusCounts['active']++;
    if ((($u['uptime'] ?? '') === '' || ($u['uptime'] ?? '') === '0s') && (str_starts_with($c, 'vc-') || str_starts_with($c, 'up-'))) $statusCounts['ready']++;
}
$pageTitle = $mode === 'add' ? 'Tambah Pengguna' : ($mode === 'edit' ? 'Edit Pengguna' : ($mode === 'generate' ? 'Generate Voucher' : 'Pengguna'));
$activeNav = 'users';
?>

<?php if ($mode === 'add' || $mode === 'edit'): ?>
    <?php
    $u = $editUser;
    $isEdit = $mode === 'edit';
    page_header($isEdit ? 'Edit pengguna' : 'Tambah pengguna', $isEdit ? 'Perbarui data, masa aktif, dan komentar pengguna hotspot.' : 'Buat satu akun hotspot secara manual.', '<a class="btn" href="' . e(url('users')) . '">' . icon('chevron-left') . ' Kembali</a>');
    ?>
    <form method="post" action="<?= e($returnUrl()) ?>" class="grid grid-1-2" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="<?= $isEdit ? 'update' : 'create' ?>">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($u['.id'] ?? '') ?>"><?php endif; ?>

        <div class="stack">
            <div class="card">
                <div class="card-head"><h3><?= icon('user-plus') ?> Informasi akun</h3></div>
                <div class="card-body">
                    <div class="form-grid cols-1">
                        <div class="field">
                            <label for="name">Username</label>
                            <input class="input" id="name" name="name" value="<?= e($u['name'] ?? '') ?>" required autofocus placeholder="misal: vc-a8f3k">
                            <span class="hint">Tanpa spasi. Gunakan nama yang mudah dibaca pelanggan.</span>
                        </div>
                        <div class="field">
                            <label for="pass">Password</label>
                            <div class="pass-wrap">
                                <input class="input" id="pass" name="pass" type="text" value="<?= e($u['password'] ?? '') ?>" required placeholder="Password hotspot">
                                <button type="button" class="pass-toggle" data-pass-toggle="pass" aria-label="Sembunyikan"><?= icon('eye', 'icon-sm') ?></button>
                            </div>
                            <span class="hint">Jika username = password, voucher dicetak dalam mode kode tunggal.</span>
                        </div>
                        <div class="field">
                            <label for="profile">Profil</label>
                            <select class="select" id="profile" name="profile" required>
                                <?php if (!$profiles): ?><option value="">— belum ada profil —</option><?php endif; ?>
                                <?php if (!in_array($selectedProfile, $profileNames, true) && $selectedProfile !== ''): ?><option value="<?= e($selectedProfile) ?>"><?= e($selectedProfile) ?></option><?php endif; ?>
                                <?php foreach ($profiles as $profile): ?>
                                    <option value="<?= e($profile['name']) ?>" <?= $profile['name'] === $selectedProfile ? 'selected' : '' ?>><?= e($profile['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="server">Server hotspot</label>
                            <select class="select" id="server" name="server">
                                <option value="all" <?= ($u['server'] ?? 'all') === 'all' ? 'selected' : '' ?>>all</option>
                                <?php foreach ($servers as $server): ?>
                                    <option value="<?= e($server['name']) ?>" <?= ($u['server'] ?? '') === $server['name'] ? 'selected' : '' ?>><?= e($server['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3><?= icon('clock') ?> Batas penggunaan</h3></div>
                <div class="card-body">
                    <div class="form-grid cols-1">
                        <div class="field">
                            <label for="timelimit">Batas waktu (limit-uptime)</label>
                            <input class="input" id="timelimit" name="timelimit" value="<?= e(($u['limit-uptime'] ?? '') === '1s' ? '' : ($u['limit-uptime'] ?? '')) ?>" placeholder="contoh: 1d, 12h, 30m — kosong berarti tanpa batas">
                            <span class="hint">Format MikroTik: <span class="mono">1d</span>, <span class="mono">12h</span>, <span class="mono">30m</span>.</span>
                        </div>
                        <div class="field">
                            <label for="datalimit">Batas kuota</label>
                            <div class="input-group">
                                <input class="input" id="datalimit" name="datalimit" type="number" min="0" step="0.01" value="<?= e($dataLimitValue) ?>" placeholder="0">
                                <select class="select" name="mbgb" style="width:100px">
                                    <option value="1048576" <?= $dataLimitUnit === '1048576' ? 'selected' : '' ?>>MB</option>
                                    <option value="1073741824" <?= $dataLimitUnit === '1073741824' ? 'selected' : '' ?>>GB</option>
                                </select>
                            </div>
                            <span class="hint">Kosongkan atau isi 0 untuk kuota tanpa batas.</span>
                        </div>
                        <div class="field">
                            <label for="mac">MAC Address <span class="text-muted">(opsional)</span></label>
                            <input class="input" id="mac" name="mac" value="<?= e($u['mac-address'] ?? '') ?>" placeholder="AA:BB:CC:DD:EE:FF">
                            <span class="hint">Kunci akun ke perangkat tertentu.</span>
                        </div>
                        <div class="field">
                            <label for="comment">Komentar</label>
                            <input class="input" id="comment" name="comment" value="<?= e($u['comment'] ?? '') ?>" placeholder="misal: paket 1 hari">
                            <?php if ($expiredComment): ?>
                                <span class="err">Komentar ini berisi tanggal kedaluwarsa. Gunakan tombol Reset untuk memulai ulang masa aktif.</span>
                            <?php endif; ?>
                        </div>
                        <div class="field">
                            <label>Status</label>
                            <label class="switch"><input type="checkbox" name="disabled" value="yes" <?= ($u['disabled'] ?? '') === 'true' ? 'checked' : '' ?>><span class="track"></span> Nonaktifkan akun</label>
                        </div>
                    </div>
                </div>
                <div class="card-foot">
                    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan perubahan</button>
                    <a class="btn btn-ghost" href="<?= e($returnUrl()) ?>">Batal</a>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-head"><h3><?= icon('info') ?> Detail profil</h3></div>
                <div class="card-body" id="profileInfo">
                    <?php $pInfo = jm_profile($selectedProfile); $pexp = $pInfo['_exp'] ?? []; ?>
                    <div class="keyval"><span>Masa berlaku</span><strong><?= e($pexp['validity'] ?? '-') ?: '-' ?></strong></div>
                    <div class="keyval"><span>Harga</span><strong><?= e(money($pexp['price'] ?? 0)) ?></strong></div>
                    <div class="keyval"><span>Harga jual</span><strong><?= e(money($pexp['sprice'] ?? 0)) ?></strong></div>
                    <div class="keyval"><span>Kunci perangkat</span><strong><?= e($pexp['lock'] ?? 'Disable') ?></strong></div>
                    <div class="keyval"><span>Shared users</span><strong><?= e($pInfo['shared-users'] ?? '1') ?></strong></div>
                </div>
            </div>
            <?php if ($isEdit): ?>
                <div class="card">
                    <div class="card-head"><h3><?= icon('activity') ?> Statistik</h3></div>
                    <div class="card-body">
                        <div class="keyval"><span>Uptime</span><strong><?= e(fmt_uptime($u['uptime'] ?? '-')) ?></strong></div>
                        <div class="keyval"><span>Bytes in / out</span><strong><?= e(fmt_bytes($u['bytes-in'] ?? 0)) ?> / <?= e(fmt_bytes($u['bytes-out'] ?? 0)) ?></strong></div>
                        <div class="keyval"><span>Dibuat</span><strong>—</strong></div>
                    </div>
                    <div class="card-foot" style="flex-wrap:wrap">
                        <a class="btn btn-sm" href="<?= e(url('print', ['user' => $u['name'] ?? '', 'mode' => $u['_mode'] ?? 'up'])) ?>" target="_blank"><?= icon('printer') ?> Cetak</a>
                        <a class="btn btn-sm" href="https://wa.me/?text=<?= rawurlencode('Voucher ' . ($u['name'] ?? '') . ' / ' . ($u['password'] ?? '') . ' — ' . setting('company', 'JarMon')) ?>" target="_blank" rel="noopener"><?= icon('external') ?> WhatsApp</a>
                        <form method="post" action="<?= e($returnUrl()) ?>" data-confirm="Hapus pengguna <?= e($u['name'] ?? '') ?>?">
                            <?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= e($u['.id'] ?? '') ?>"><input type="hidden" name="name" value="<?= e($u['name'] ?? '') ?>">
                            <button class="btn btn-sm btn-danger" type="submit"><?= icon('trash') ?> Hapus</button>
                        </form>
                    </div>
                </div>
                <div class="card">
                    <div class="card-head"><h3><?= icon('tools') ?> Aksi lanjutan</h3></div>
                    <div class="card-body">
                        <form method="post" action="<?= e($returnUrl()) ?>" class="mb-2" data-confirm="Reset masa aktif dan hitungan penggunaan <?= e($u['name'] ?? '') ?>?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="do" value="reset">
                            <input type="hidden" name="id" value="<?= e($u['.id'] ?? '') ?>">
                            <input type="hidden" name="name" value="<?= e($u['name'] ?? '') ?>">
                            <input type="hidden" name="comment" value="<?= e($u['comment'] ?? '') ?>">
                            <button class="btn btn-sm btn-warning btn-block" type="submit"><?= icon('refresh') ?> Reset masa aktif</button>
                        </form>
                        <?php if (isset($activeNames[(string) ($u['name'] ?? '')])): ?>
                            <form method="post" action="<?= e($returnUrl()) ?>" data-confirm="Putuskan sesi aktif <?= e($u['name'] ?? '') ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="do" value="kick">
                                <input type="hidden" name="name" value="<?= e($u['name'] ?? '') ?>">
                                <button class="btn btn-sm btn-block" type="submit"><?= icon('power') ?> Putuskan sesi</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-head"><h3><?= icon('spark') ?> Tips</h3></div>
                    <div class="card-body text-small text-muted">
                        <p class="mb-2">Untuk membuat banyak voucher sekaligus, gunakan <a href="<?= e(url('users', ['mode' => 'generate'])) ?>">Generate voucher</a> atau <a href="<?= e(url('vouchers')) ?>">Voucher Studio</a>.</p>
                        <p>Voucher dengan username = password otomatis dicetak sebagai satu kode.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </form>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var select = document.getElementById('profile');
            if (!select) return;
            var info = document.getElementById('profileInfo');
            var data = <?= json_encode(array_map(static function ($p) {
                return ['validity' => $p['_exp']['validity'] ?? '', 'price' => $p['_exp']['price'] ?? '', 'sprice' => $p['_exp']['sprice'] ?? '', 'lock' => $p['_exp']['lock'] ?? 'Disable', 'shared' => $p['shared-users'] ?? '1'];
            }, $profiles), JSON_UNESCAPED_UNICODE) ?>;
            var money = function (v) {
                if (!v || Number(v) <= 0) return '—';
                return '<?= e(setting('currency', 'Rp')) ?> ' + Number(v).toLocaleString('id-ID');
            };
            select.addEventListener('change', function () {
                var d = data[select.value];
                if (!d || !info) return;
                var rows = info.querySelectorAll('.keyval strong');
                if (rows.length >= 5) {
                    rows[0].textContent = d.validity || '-';
                    rows[1].textContent = money(d.price);
                    rows[2].textContent = money(d.sprice);
                    rows[3].textContent = d.lock || 'Disable';
                    rows[4].textContent = d.shared || '1';
                }
            });
        });
    </script>

<?php elseif ($mode === 'generate'): ?>
    <?php page_header('Generate voucher', 'Buat banyak voucher sekaligus, lalu langsung cetak.', '<a class="btn" href="' . e(url('users')) . '">' . icon('chevron-left') . ' Kembali</a>'); ?>
    <form method="post" action="<?= e($returnUrl()) ?>" class="grid grid-1-2" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="generate">
        <div class="stack">
            <div class="card">
                <div class="card-head"><h3><?= icon('ticket') ?> Parameter voucher</h3></div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="field"><label for="qty">Jumlah voucher</label><input class="input" id="qty" name="qty" type="number" min="1" max="500" value="10" required></div>
                        <div class="field"><label for="profile">Profil</label>
                            <select class="select" id="profile" name="profile" required>
                                <?php if (!$profiles): ?><option value="">— belum ada profil —</option><?php endif; ?>
                                <?php foreach ($profiles as $profile): ?>
                                    <option value="<?= e($profile['name']) ?>"><?= e($profile['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field"><label for="usermode">Mode voucher</label>
                            <select class="select" id="usermode" name="usermode">
                                <option value="vc">Username = Password</option>
                                <option value="up">Username &amp; Password</option>
                            </select>
                        </div>
                        <div class="field"><label for="server">Server</label>
                            <select class="select" id="server" name="server">
                                <option value="all">all</option>
                                <?php foreach ($servers as $server): ?><option value="<?= e($server['name']) ?>"><?= e($server['name']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field"><label for="length">Panjang karakter</label>
                            <select class="select" id="length" name="length">
                                <?php foreach ([3, 4, 5, 6, 7, 8, 9, 10] as $len): ?><option value="<?= $len ?>" <?= $len === 6 ? 'selected' : '' ?>><?= $len ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field"><label for="charset">Jenis karakter</label>
                            <select class="select" id="charset" name="charset">
                                <option value="lower">Huruf kecil (abcd)</option>
                                <option value="upper">Huruf besar (ABCD)</option>
                                <option value="upplow">Campur (aBcD)</option>
                                <option value="mix">Huruf + angka (5ab2c)</option>
                                <option value="mix1">Huruf besar + angka (5AB2)</option>
                                <option value="mix2">Campur + angka (5aB2)</option>
                                <option value="num">Angka saja (2345)</option>
                            </select>
                        </div>
                        <div class="field"><label for="prefix">Prefix <span class="text-muted">(opsional)</span></label><input class="input" id="prefix" name="prefix" maxlength="6" placeholder="misal: VC"></div>
                        <div class="field"><label for="comment">Label batch</label><input class="input" id="comment" name="comment" maxlength="12" placeholder="misal: 1D"></div>
                        <div class="field"><label for="timelimit">Batas waktu</label><input class="input" id="timelimit" name="timelimit" placeholder="contoh: 1d / 12h"></div>
                        <div class="field"><label for="datalimit">Batas kuota</label>
                            <div class="input-group">
                                <input class="input" id="datalimit" name="datalimit" type="number" min="0" step="0.01" placeholder="0">
                                <select class="select" name="mbgb" style="width:100px"><option value="1048576">MB</option><option value="1073741824">GB</option></select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-head"><h3><?= icon('printer') ?> Opsi cetak</h3></div>
                <div class="card-body">
                    <div class="tpl-options">
                        <label class="tpl-option active" data-tpl-select>
                            <input type="radio" name="tpl" value="default" checked>
                            <strong>Standar</strong>
                            <div class="tpl-preview"><i class="accent"></i><i class="w60"></i><i class="w40"></i><i></i></div>
                        </label>
                        <label class="tpl-option" data-tpl-select>
                            <input type="radio" name="tpl" value="compact">
                            <strong>Kompakt</strong>
                            <div class="tpl-preview"><i class="accent"></i><i></i><i></i></div>
                        </label>
                        <label class="tpl-option" data-tpl-select>
                            <input type="radio" name="tpl" value="thermal">
                            <strong>Thermal</strong>
                            <div class="tpl-preview"><i class="w40"></i><i></i><i class="w60"></i></div>
                        </label>
                    </div>
                    <div class="field mt-2"><label class="check"><input type="checkbox" name="qr" value="1" checked> Sertakan QR code login</label></div>
                </div>
                <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('zap') ?> Generate &amp; cetak</button></div>
            </div>
        </div>
        <div class="stack">
            <div class="card">
                <div class="card-head"><h3><?= icon('package') ?> Paket cepat</h3></div>
                <div class="card-body">
                    <?php foreach (jm_config()['packages'] as $package): ?>
                        <button type="button" class="btn btn-block mb-2 package-preset" style="justify-content:flex-start"
                            data-profile="<?= e($package['profile'] ?? '') ?>"
                            data-qty="<?= (int) ($package['qty'] ?? 10) ?>"
                            data-validity="<?= e($package['validity'] ?? '') ?>"
                            data-timelimit="<?= e($package['time_limit'] ?? '') ?>"
                            data-data="<?= (int) ($package['data_mb'] ?? 0) ?>"
                            data-comment="<?= e($package['comment'] ?? '') ?>">
                            <span class="text-left" style="flex:1"><strong><?= e($package['name']) ?></strong><br><small class="text-muted"><?= e($package['validity'] ?: '-') ?> &middot; <?= e(money($package['price'] ?? 0)) ?></small></span>
                            <?= icon('chevron-right') ?>
                        </button>
                    <?php endforeach; ?>
                    <a class="btn btn-sm btn-block mt-1" href="<?= e(url('vouchers', ['tab' => 'packages'])) ?>"><?= icon('settings') ?> Kelola paket</a>
                </div>
            </div>
            <div class="card">
                <div class="card-head"><h3><?= icon('info') ?> Tentang voucher</h3></div>
                <div class="card-body text-small text-muted">
                    <p class="mb-2">Setiap voucher mendapat komentar batch agar mudah difilter di daftar pengguna dan dicetak ulang.</p>
                    <p>Voucher akan otomatis mengikuti mode kedaluwarsa profil yang dipilih saat pengguna pertama kali login.</p>
                </div>
            </div>
        </div>
    </form>
    <script>
        document.querySelectorAll('.package-preset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var set = function (id, value) {
                    var el = document.getElementById(id);
                    if (el && value !== undefined && value !== '') el.value = value;
                };
                set('profile', btn.dataset.profile);
                set('qty', btn.dataset.qty);
                set('timelimit', btn.dataset.timelimit);
                set('datalimit', btn.dataset.data && btn.dataset.data !== '0' ? btn.dataset.data : '');
                set('comment', btn.dataset.comment);
                if (btn.dataset.comment) {
                    var prefix = document.getElementById('prefix');
                    if (prefix && !prefix.value) prefix.value = btn.dataset.comment;
                }
                jmToast('Paket diterapkan. Periksa lalu generate.', 'success');
            });
        });
    </script>

<?php else: ?>
    <?php
    $actions = '<a class="btn" href="' . e(url('users', ['mode' => 'generate'])) . '">' . icon('zap') . ' Generate</a>'
        . '<a class="btn" href="' . e(url('users', ['export' => 'csv', 'profile' => $profileFilter, 'status' => $statusFilter])) . '">' . icon('download') . ' CSV</a>'
        . '<a class="btn btn-primary" href="' . e(url('users', ['mode' => 'add'])) . '">' . icon('user-plus') . ' Tambah pengguna</a>';
    page_header('Pengguna hotspot', 'Kelola akun, voucher, dan masa aktif pada router ini.', $actions);
    ?>
    <?php if (!jm_api_ready()): ?>
        <div class="alert alert-warning"><?= icon('alert') ?><div>Daftar pengguna tidak dapat dimuat karena router offline.</div></div>
    <?php endif; ?>
    <div class="card">
        <div class="table-toolbar">
            <div class="search-box"><?= icon('search') ?><input class="input input-sm" type="text" placeholder="Cari pengguna..." data-search="#usersTable"></div>
            <form method="get" action="<?= e(url('users')) ?>" class="row-flex" style="gap:8px">
                <input type="hidden" name="p" value="users">
                <?php if (current_router_slug()): ?><input type="hidden" name="r" value="<?= e(current_router_slug()) ?>"><?php endif; ?>
                <select class="select select-sm" name="profile" data-autosubmit style="width:150px">
                    <option value="all">Semua profil</option>
                    <?php foreach ($profiles as $profile): ?>
                        <option value="<?= e($profile['name']) ?>" <?= $profileFilter === $profile['name'] ? 'selected' : '' ?>><?= e($profile['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="select select-sm" name="status" data-autosubmit style="width:140px">
                    <?php foreach (['all' => 'Semua status', 'active' => 'Online', 'ready' => 'Siap jual', 'expired' => 'Kedaluwarsa', 'disabled' => 'Nonaktif'] as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <span class="spacer"></span>
            <span class="badge badge-neutral"><?= fmt_number($statusCounts['all']) ?> user</span>
            <span class="badge badge-success"><?= fmt_number($statusCounts['active']) ?> online</span>
            <span class="badge badge-accent"><?= fmt_number($statusCounts['ready']) ?> siap</span>
            <span class="badge badge-danger"><?= fmt_number($statusCounts['expired']) ?> expired</span>
        </div>

        <div data-batch-bar style="display:none;align-items:center;gap:9px;padding:10px 18px;border-bottom:1px solid var(--border);background:var(--surface-2);flex-wrap:wrap">
            <span class="text-small text-muted"><b data-batch-count>0</b> dipilih</span>
            <button class="btn btn-sm" type="button" data-batch-submit data-batch-table="usersTable" data-batch-do="print"><?= icon('printer') ?> Cetak</button>
            <button class="btn btn-sm btn-success" type="button" data-batch-submit data-batch-table="usersTable" data-batch-do="enable"><?= icon('unlock') ?> Aktifkan</button>
            <button class="btn btn-sm btn-warning" type="button" data-batch-submit data-batch-table="usersTable" data-batch-do="disable"><?= icon('lock') ?> Nonaktifkan</button>
            <button class="btn btn-sm btn-danger" type="button" data-batch-submit data-batch-table="usersTable" data-batch-do="delete" data-confirm="Hapus semua pengguna terpilih?"><?= icon('trash') ?> Hapus</button>
            <span class="spacer"></span>
            <span class="text-small text-muted">Pilih baris untuk mengaktifkan aksi massal</span>
        </div>

        <div class="table-wrap">
            <table class="data-table" id="usersTable" data-table data-page-size="<?= (int) (setting('page_size', 25)) ?>">
                <thead>
                <tr>
                    <th style="width:38px"><input type="checkbox" aria-label="Pilih semua"></th>
                    <th class="sortable">Username</th>
                    <th>Password</th>
                    <th class="sortable">Profil</th>
                    <th class="sortable">Status</th>
                    <th class="sortable num">Uptime</th>
                    <th class="sortable num">Bytes In</th>
                    <th class="sortable num">Bytes Out</th>
                    <th class="sortable">Komentar</th>
                    <th style="width:120px"></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($list as $user): ?>
                    <?php
                    $badges = [
                        'active' => ['Online', 'success'],
                        'ready' => ['Siap jual', 'accent'],
                        'expired' => ['Kedaluwarsa', 'danger'],
                        'disabled' => ['Nonaktif', 'warning'],
                        'used' => ['Terdaftar', 'neutral'],
                    ];
                    [$statusLabel, $statusClass] = $badges[$user['_status']];
                    ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= e($user['.id'] ?? '') ?>" aria-label="Pilih <?= e($user['name'] ?? '') ?>"></td>
                        <td>
                            <a class="user-link" href="<?= e(url('users', ['mode' => 'edit', 'id' => $user['.id'] ?? ''])) ?>"><?= e($user['name'] ?? '') ?></a>
                            <div class="text-small text-muted mono"><?= e($user['_mode'] === 'vc' ? 'kode tunggal' : 'user &amp; pass') ?></div>
                        </td>
                        <td class="mono text-small">
                            <?php if (($user['password'] ?? '') === ($user['name'] ?? '')): ?>
                                <span class="badge badge-accent">sama</span>
                            <?php else: ?>
                                <?= e($user['password'] ?? '-') ?>
                                <button class="icon-btn" type="button" data-copy-value="<?= e($user['password'] ?? '') ?>" title="Salin password" style="width:26px;height:26px;vertical-align:middle"><?= icon('copy', 'icon-sm') ?></button>
                            <?php endif; ?>
                        </td>
                        <td><?= e($user['profile'] ?? '-') ?></td>
                        <td><span class="badge badge-<?= $statusClass ?>"><?= $statusLabel ?></span></td>
                        <td class="num"><?= e(fmt_uptime($user['uptime'] ?? '')) ?></td>
                        <td class="num"><?= e(fmt_bytes($user['bytes-in'] ?? 0, 0)) ?></td>
                        <td class="num"><?= e(fmt_bytes($user['bytes-out'] ?? 0, 0)) ?></td>
                        <td class="text-small" style="max-width:220px"><span class="truncate" title="<?= e($user['comment'] ?? '') ?>"><?= e($user['comment'] ?? '') ?></span></td>
                        <td class="actions-cell">
                            <a class="icon-btn" href="<?= e(url('users', ['mode' => 'edit', 'id' => $user['.id'] ?? ''])) ?>" title="Edit"><?= icon('edit') ?></a>
                            <a class="icon-btn" href="<?= e(url('print', ['user' => $user['name'] ?? '', 'mode' => $user['_mode']])) ?>" target="_blank" title="Cetak voucher"><?= icon('printer') ?></a>
                            <?php if ($user['_status'] === 'active'): ?>
                                <form method="post" action="<?= e($returnUrl()) ?>" style="display:inline" data-confirm="Putuskan sesi <?= e($user['name'] ?? '') ?>?">
                                    <?= csrf_field() ?><input type="hidden" name="do" value="kick"><input type="hidden" name="name" value="<?= e($user['name'] ?? '') ?>">
                                    <button class="icon-btn" type="submit" title="Putuskan sesi"><?= icon('power') ?></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e($returnUrl()) ?>" style="display:inline" data-confirm="Hapus pengguna <?= e($user['name'] ?? '') ?>?">
                                <?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= e($user['.id'] ?? '') ?>"><input type="hidden" name="name" value="<?= e($user['name'] ?? '') ?>">
                                <button class="icon-btn" type="submit" title="Hapus" style="color:var(--danger)"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div data-table-empty style="display:none"><?= empty_state('users', 'Belum ada pengguna', 'Tambahkan pengguna manual atau generate voucher untuk memulai.') ?></div>
    </div>
<?php endif; ?>
