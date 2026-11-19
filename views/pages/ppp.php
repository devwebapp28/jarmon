<?php
$tab = get_str('tab', 'secrets');
if (!in_array($tab, ['secrets', 'profiles', 'active'], true)) {
    $tab = 'secrets';
}
$do = post_str('do');
$api = jm_api();

if ($do === 'secret-save' && $api) {
    $id = post_str('id');
    $name = post_str('name');
    $password = post_str('password');
    if ($name === '') {
        jm_flash('danger', 'Nama PPP secret wajib diisi.');
        redirect(url('ppp', ['tab' => 'secrets']));
    }
    $payload = [
        'name' => $name,
        'service' => post_str('service', 'any'),
        'profile' => post_str('profile', 'default'),
        'local-address' => post_str('local_address'),
        'remote-address' => post_str('remote_address'),
        'disabled' => post_str('disabled') === 'yes' ? 'yes' : 'no',
        'comment' => post_str('comment'),
    ];
    if ($password !== '') {
        $payload['password'] = $password;
    }
    if ($id !== '') {
        $payload['.id'] = $id;
        $result = api_try('/ppp/secret/set', $payload);
    } else {
        if ($password === '') {
            jm_flash('danger', 'Password wajib diisi untuk secret baru.');
            redirect(url('ppp', ['tab' => 'secrets']));
        }
        $result = api_try('/ppp/secret/add', $payload);
    }
    jm_log('ppp-secret-save', $name);
    jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'PPP secret ' . $name . ' disimpan.' : 'Gagal: ' . $result['error']);
    redirect(url('ppp', ['tab' => 'secrets']));
}
if ($do === 'secret-delete' && $api) {
    $result = api_try('/ppp/secret/remove', ['.id' => post_str('id')]);
    jm_log('ppp-secret-delete', post_str('name'));
    jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'PPP secret dihapus.' : 'Gagal: ' . $result['error']);
    redirect(url('ppp', ['tab' => 'secrets']));
}
if ($do === 'secret-toggle' && $api) {
    $result = api_try('/ppp/secret/set', ['.id' => post_str('id'), 'disabled' => post_str('disabled') === 'true' ? 'no' : 'yes']);
    jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Status secret diperbarui.' : 'Gagal: ' . $result['error']);
    redirect(url('ppp', ['tab' => 'secrets']));
}
if ($do === 'secret-kick' && $api) {
    $name = post_str('name');
    $active = api_comm('/ppp/active/print', ['?name' => $name]);
    $count = 0;
    foreach ($active as $session) {
        $r = api_try('/ppp/active/remove', ['.id' => $session['.id']]);
        if ($r['ok']) $count++;
    }
    jm_flash($count ? 'success' : 'warning', $count ? $count . ' sesi PPP diputus.' : 'Tidak ada sesi aktif.');
    redirect(url('ppp', ['tab' => 'secrets']));
}
if ($do === 'profile-save' && $api) {
    $id = post_str('id');
    $name = post_str('name');
    if ($name === '') {
        jm_flash('danger', 'Nama PPP profile wajib diisi.');
        redirect(url('ppp', ['tab' => 'profiles']));
    }
    $payload = [
        'name' => $name,
        'local-address' => post_str('local_address'),
        'remote-address' => post_str('remote_address'),
        'rate-limit' => post_str('rate_limit'),
        'only-one' => post_str('only_one', 'default'),
        'parent-queue' => post_str('parent_queue', 'none'),
        'dns-server' => post_str('dns_server'),
        'change-tcp-mss' => post_str('change_tcp_mss', 'default'),
    ];
    if ($id !== '') {
        $payload['.id'] = $id;
        $result = api_try('/ppp/profile/set', $payload);
    } else {
        $result = api_try('/ppp/profile/add', $payload);
    }
    jm_log('ppp-profile-save', $name);
    jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'PPP profile ' . $name . ' disimpan.' : 'Gagal: ' . $result['error']);
    redirect(url('ppp', ['tab' => 'profiles']));
}
if ($do === 'profile-delete' && $api) {
    $result = api_try('/ppp/profile/remove', ['.id' => post_str('id')]);
    jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'PPP profile dihapus.' : 'Gagal: ' . $result['error']);
    redirect(url('ppp', ['tab' => 'profiles']));
}
if ($do === 'active-kick' && $api) {
    $result = api_try('/ppp/active/remove', ['.id' => post_str('id')]);
    jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Sesi PPP diputus.' : 'Gagal: ' . $result['error']);
    redirect(url('ppp', ['tab' => 'active']));
}

$secrets = [];
$pppProfiles = [];
$pppActive = [];
if (jm_api_ready()) {
    $secrets = api_comm('/ppp/secret/print');
    $pppProfiles = api_comm('/ppp/profile/print');
    $pppActive = api_comm('/ppp/active/print');
}
$activeNames = [];
foreach ($pppActive as $session) {
    $activeNames[(string) ($session['name'] ?? '')] = true;
}

$editSecret = null;
if ($tab === 'secrets' && get_str('edit') !== '') {
    foreach ($secrets as $secret) {
        if (($secret['.id'] ?? '') === get_str('edit')) {
            $editSecret = $secret;
            break;
        }
    }
}
$editPppProfile = null;
if ($tab === 'profiles' && get_str('edit') !== '') {
    foreach ($pppProfiles as $profile) {
        if (($profile['.id'] ?? '') === get_str('edit')) {
            $editPppProfile = $profile;
            break;
        }
    }
}
$pppProfileNames = array_map(static fn($p) => (string) ($p['name'] ?? ''), $pppProfiles);
$pageTitle = 'PPP';
$activeNav = 'ppp:' . $tab;
?>
<?php page_header('PPP', 'Kelola PPP secrets, profiles, dan sesi aktif.', '<a class="btn" href="' . e(url('ppp', ['tab' => $tab])) . '">' . icon('refresh') . ' Muat ulang</a>'); ?>

<div class="tabs mb-2">
    <a class="tab <?= $tab === 'secrets' ? 'active' : '' ?>" href="<?= e(url('ppp', ['tab' => 'secrets'])) ?>"><?= icon('key') ?> Secrets <span class="badge badge-neutral"><?= count($secrets) ?></span></a>
    <a class="tab <?= $tab === 'profiles' ? 'active' : '' ?>" href="<?= e(url('ppp', ['tab' => 'profiles'])) ?>"><?= icon('settings') ?> Profiles <span class="badge badge-neutral"><?= count($pppProfiles) ?></span></a>
    <a class="tab <?= $tab === 'active' ? 'active' : '' ?>" href="<?= e(url('ppp', ['tab' => 'active'])) ?>"><?= icon('activity') ?> Active <span class="badge badge-neutral"><?= count($pppActive) ?></span></a>
</div>

<?php if ($tab === 'secrets'): ?>
    <div class="grid grid-2-1">
        <div class="card">
            <div class="table-toolbar">
                <div class="search-box"><?= icon('search') ?><input class="input input-sm" type="text" placeholder="Cari secret..." data-search="#secretTable"></div>
                <span class="spacer"></span>
                <span class="badge badge-neutral"><?= count($secrets) ?> secret</span>
            </div>
            <div class="table-wrap">
                <table class="data-table" id="secretTable" data-table data-page-size="<?= (int) setting('page_size', 25) ?>">
                    <thead><tr><th class="sortable">Nama</th><th>Service</th><th>Profile</th><th>Remote</th><th>Status</th><th>Komentar</th><th style="width:100px"></th></tr></thead>
                    <tbody>
                    <?php foreach ($secrets as $secret): ?>
                        <?php $disabled = ($secret['disabled'] ?? '') === 'true'; ?>
                        <tr>
                            <td><a class="user-link" href="<?= e(url('ppp', ['tab' => 'secrets', 'edit' => $secret['.id'] ?? ''])) ?>"><?= e($secret['name'] ?? '-') ?></a></td>
                            <td><span class="badge badge-neutral"><?= e($secret['service'] ?? 'any') ?></span></td>
                            <td><?= e($secret['profile'] ?? 'default') ?></td>
                            <td class="mono"><?= e($secret['remote-address'] ?? '') ?: '-' ?></td>
                            <td>
                                <?php if (isset($activeNames[(string) ($secret['name'] ?? '')])): ?><span class="badge badge-success">Online</span>
                                <?php elseif ($disabled): ?><span class="badge badge-warning">Nonaktif</span>
                                <?php else: ?><span class="badge badge-neutral">Aktif</span><?php endif; ?>
                            </td>
                            <td class="text-small"><?= e($secret['comment'] ?? '') ?></td>
                            <td class="actions-cell">
                                <?php if (isset($activeNames[(string) ($secret['name'] ?? '')])): ?>
                                    <form method="post" action="<?= e(url('ppp', ['tab' => 'secrets'])) ?>" style="display:inline" data-confirm="Putuskan sesi <?= e($secret['name'] ?? '') ?>?">
                                        <?= csrf_field() ?><input type="hidden" name="do" value="secret-kick"><input type="hidden" name="name" value="<?= e($secret['name'] ?? '') ?>">
                                        <button class="icon-btn" type="submit" title="Putuskan" style="color:var(--danger)"><?= icon('power') ?></button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="<?= e(url('ppp', ['tab' => 'secrets'])) ?>" style="display:inline">
                                    <?= csrf_field() ?><input type="hidden" name="do" value="secret-toggle"><input type="hidden" name="id" value="<?= e($secret['.id'] ?? '') ?>"><input type="hidden" name="disabled" value="<?= $disabled ? 'true' : 'false' ?>">
                                    <button class="icon-btn" type="submit" title="<?= $disabled ? 'Aktifkan' : 'Nonaktifkan' ?>"><?= icon($disabled ? 'lock' : 'unlock') ?></button>
                                </form>
                                <form method="post" action="<?= e(url('ppp', ['tab' => 'secrets'])) ?>" style="display:inline" data-confirm="Hapus PPP secret <?= e($secret['name'] ?? '') ?>?">
                                    <?= csrf_field() ?><input type="hidden" name="do" value="secret-delete"><input type="hidden" name="id" value="<?= e($secret['.id'] ?? '') ?>"><input type="hidden" name="name" value="<?= e($secret['name'] ?? '') ?>">
                                    <button class="icon-btn" type="submit" title="Hapus" style="color:var(--danger)"><?= icon('trash') ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div data-table-empty style="display:none"><?= empty_state('key', 'Tidak ada PPP secret', 'Tambahkan secret untuk layanan PPPoE, PPTP, atau L2TP.') ?></div>
        </div>
        <div class="card">
            <div class="card-head"><h3><?= icon($editSecret ? 'edit' : 'plus') ?> <?= $editSecret ? 'Edit secret' : 'Tambah secret' ?></h3></div>
            <form method="post" action="<?= e(url('ppp', ['tab' => 'secrets'])) ?>" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="secret-save">
                <input type="hidden" name="id" value="<?= e($editSecret['.id'] ?? '') ?>">
                <div class="card-body">
                    <div class="form-grid cols-1">
                        <div class="field"><label for="sname">Nama</label><input class="input" id="sname" name="name" value="<?= e($editSecret['name'] ?? '') ?>" required></div>
                        <div class="field"><label for="spass">Password <?= $editSecret ? '<span class="text-muted">(kosong = tidak diubah)</span>' : '' ?></label><input class="input" id="spass" name="password" type="text" value=""></div>
                        <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
                            <div class="field"><label for="sservice">Service</label>
                                <select class="select" id="sservice" name="service">
                                    <?php foreach (['any', 'pppoe', 'pptp', 'l2tp', 'ovpn', 'sstp'] as $service): ?><option value="<?= $service ?>" <?= ($editSecret['service'] ?? 'any') === $service ? 'selected' : '' ?>><?= $service ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field"><label for="sprofile">Profile</label>
                                <select class="select" id="sprofile" name="profile">
                                    <?php if (!in_array((string) ($editSecret['profile'] ?? 'default'), $pppProfileNames, true)): ?><option value="<?= e($editSecret['profile'] ?? 'default') ?>"><?= e($editSecret['profile'] ?? 'default') ?></option><?php endif; ?>
                                    <?php foreach ($pppProfiles as $profile): ?><option value="<?= e($profile['name'] ?? '') ?>" <?= ($editSecret['profile'] ?? '') === ($profile['name'] ?? '') ? 'selected' : '' ?>><?= e($profile['name'] ?? '') ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
                            <div class="field"><label for="slocal">Local address</label><input class="input mono" id="slocal" name="local_address" value="<?= e($editSecret['local-address'] ?? '') ?>" placeholder="opsional"></div>
                            <div class="field"><label for="sremote">Remote address</label><input class="input mono" id="sremote" name="remote_address" value="<?= e($editSecret['remote-address'] ?? '') ?>" placeholder="opsional"></div>
                        </div>
                        <div class="field"><label for="scomment">Komentar</label><input class="input" id="scomment" name="comment" value="<?= e($editSecret['comment'] ?? '') ?>"></div>
                        <label class="switch"><input type="checkbox" name="disabled" value="yes" <?= ($editSecret['disabled'] ?? '') === 'true' ? 'checked' : '' ?>><span class="track"></span> Nonaktifkan secret</label>
                    </div>
                </div>
                <div class="card-foot">
                    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan</button>
                    <?php if ($editSecret): ?><a class="btn btn-ghost" href="<?= e(url('ppp', ['tab' => 'secrets'])) ?>">Batal</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

<?php elseif ($tab === 'profiles'): ?>
    <div class="grid grid-2-1">
        <div class="card">
            <div class="table-toolbar">
                <div class="search-box"><?= icon('search') ?><input class="input input-sm" type="text" placeholder="Cari profile..." data-search="#pppProfileTable"></div>
                <span class="spacer"></span>
                <span class="badge badge-neutral"><?= count($pppProfiles) ?> profile</span>
            </div>
            <div class="table-wrap">
                <table class="data-table" id="pppProfileTable" data-table data-page-size="<?= (int) setting('page_size', 25) ?>">
                    <thead><tr><th class="sortable">Nama</th><th>Local</th><th>Remote</th><th>Rate limit</th><th>Only one</th><th>Parent</th><th style="width:80px"></th></tr></thead>
                    <tbody>
                    <?php foreach ($pppProfiles as $profile): ?>
                        <tr>
                            <td><a class="user-link" href="<?= e(url('ppp', ['tab' => 'profiles', 'edit' => $profile['.id'] ?? ''])) ?>"><?= e($profile['name'] ?? '-') ?></a></td>
                            <td class="mono"><?= e($profile['local-address'] ?? '') ?: '-' ?></td>
                            <td class="mono"><?= e($profile['remote-address'] ?? '') ?: '-' ?></td>
                            <td class="mono text-small"><?= e($profile['rate-limit'] ?? '') ?: '-' ?></td>
                            <td><span class="badge badge-neutral"><?= e($profile['only-one'] ?? 'default') ?></span></td>
                            <td><?= e($profile['parent-queue'] ?? 'none') ?></td>
                            <td class="actions-cell">
                                <a class="icon-btn" href="<?= e(url('ppp', ['tab' => 'profiles', 'edit' => $profile['.id'] ?? ''])) ?>" title="Edit"><?= icon('edit') ?></a>
                                <form method="post" action="<?= e(url('ppp', ['tab' => 'profiles'])) ?>" style="display:inline" data-confirm="Hapus PPP profile <?= e($profile['name'] ?? '') ?>?">
                                    <?= csrf_field() ?><input type="hidden" name="do" value="profile-delete"><input type="hidden" name="id" value="<?= e($profile['.id'] ?? '') ?>">
                                    <button class="icon-btn" type="submit" title="Hapus" style="color:var(--danger)"><?= icon('trash') ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div data-table-empty style="display:none"><?= empty_state('settings', 'Tidak ada PPP profile', 'Buat profile untuk mengatur IP pool dan rate limit PPP.') ?></div>
        </div>
        <div class="card">
            <div class="card-head"><h3><?= icon($editPppProfile ? 'edit' : 'plus') ?> <?= $editPppProfile ? 'Edit profile' : 'Tambah profile' ?></h3></div>
            <form method="post" action="<?= e(url('ppp', ['tab' => 'profiles'])) ?>" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="profile-save">
                <input type="hidden" name="id" value="<?= e($editPppProfile['.id'] ?? '') ?>">
                <div class="card-body">
                    <div class="form-grid cols-1">
                        <div class="field"><label for="pname">Nama profile</label><input class="input" id="pname" name="name" value="<?= e($editPppProfile['name'] ?? '') ?>" required></div>
                        <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
                            <div class="field"><label for="plocal">Local address</label><input class="input mono" id="plocal" name="local_address" value="<?= e($editPppProfile['local-address'] ?? '') ?>" placeholder="192.168.10.1"></div>
                            <div class="field"><label for="premote">Remote address</label><input class="input mono" id="premote" name="remote_address" value="<?= e($editPppProfile['remote-address'] ?? '') ?>" placeholder="pool-pppoe"></div>
                        </div>
                        <div class="field"><label for="prate">Rate limit (up/down)</label><input class="input" id="prate" name="rate_limit" value="<?= e($editPppProfile['rate-limit'] ?? '') ?>" placeholder="2M/10M"></div>
                        <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
                            <div class="field"><label for="ponly">Only one</label>
                                <select class="select" id="ponly" name="only_one">
                                    <?php foreach (['default', 'yes', 'no'] as $val): ?><option value="<?= $val ?>" <?= ($editPppProfile['only-one'] ?? 'default') === $val ? 'selected' : '' ?>><?= $val ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field"><label for="pmss">Change TCP MSS</label>
                                <select class="select" id="pmss" name="change_tcp_mss">
                                    <?php foreach (['default', 'yes', 'no'] as $val): ?><option value="<?= $val ?>" <?= ($editPppProfile['change-tcp-mss'] ?? 'default') === $val ? 'selected' : '' ?>><?= $val ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="field"><label for="pparent">Parent queue</label><input class="input" id="pparent" name="parent_queue" value="<?= e($editPppProfile['parent-queue'] ?? 'none') ?>"></div>
                        <div class="field"><label for="pdns">DNS server</label><input class="input mono" id="pdns" name="dns_server" value="<?= e($editPppProfile['dns-server'] ?? '') ?>" placeholder="1.1.1.1,8.8.8.8"></div>
                    </div>
                </div>
                <div class="card-foot">
                    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan</button>
                    <?php if ($editPppProfile): ?><a class="btn btn-ghost" href="<?= e(url('ppp', ['tab' => 'profiles'])) ?>">Batal</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <div class="card">
        <div class="table-toolbar">
            <div class="search-box"><?= icon('search') ?><input class="input input-sm" type="text" placeholder="Cari sesi PPP..." data-search="#pppActiveTable"></div>
            <span class="spacer"></span>
            <span class="badge badge-neutral"><?= count($pppActive) ?> sesi</span>
        </div>
        <div class="table-wrap">
            <table class="data-table" id="pppActiveTable" data-table data-page-size="<?= (int) setting('page_size', 25) ?>">
                <thead><tr><th class="sortable">Nama</th><th>Service</th><th>Address</th><th>Caller ID</th><th class="sortable num">Uptime</th><th style="width:60px"></th></tr></thead>
                <tbody>
                <?php foreach ($pppActive as $session): ?>
                    <tr>
                        <td><span class="user-link"><?= e($session['name'] ?? '-') ?></span></td>
                        <td><span class="badge badge-neutral"><?= e($session['service'] ?? '-') ?></span></td>
                        <td class="mono"><?= e($session['address'] ?? '-') ?></td>
                        <td class="mono text-small"><?= e($session['caller-id'] ?? '-') ?></td>
                        <td class="num"><?= e(fmt_uptime($session['uptime'] ?? '')) ?></td>
                        <td class="actions-cell">
                            <form method="post" action="<?= e(url('ppp', ['tab' => 'active'])) ?>" style="display:inline" data-confirm="Putuskan sesi <?= e($session['name'] ?? '') ?>?">
                                <?= csrf_field() ?><input type="hidden" name="do" value="active-kick"><input type="hidden" name="id" value="<?= e($session['.id'] ?? '') ?>">
                                <button class="icon-btn" type="submit" title="Putuskan" style="color:var(--danger)"><?= icon('power') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div data-table-empty style="display:none"><?= empty_state('activity', 'Tidak ada sesi PPP aktif', 'Sesi PPPoE yang terhubung akan tampil di sini.') ?></div>
    </div>
<?php endif; ?>
