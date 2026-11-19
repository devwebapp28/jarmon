<?php
$tab = get_str('tab', 'scheduler');
if (!in_array($tab, ['scheduler', 'tools', 'audit'], true)) {
    $tab = 'scheduler';
}
$do = post_str('do');
$api = jm_api();

if ($do === 'scheduler-add' && $api) {
    $name = post_str('name');
    $event = post_str('on_event');
    if ($name === '' || $event === '') {
        jm_flash('danger', 'Nama dan isi script scheduler wajib diisi.');
        redirect(url('system', ['tab' => 'scheduler']));
    }
    $payload = [
        'name' => $name,
        'start-time' => post_str('start_time', '00:00:00'),
        'interval' => post_str('interval', '00:01:00'),
        'on-event' => $event,
        'comment' => post_str('comment'),
        'disabled' => post_str('disabled') === 'yes' ? 'yes' : 'no',
    ];
    $startDate = post_str('start_date');
    if ($startDate !== '') {
        $payload['start-date'] = $startDate;
    }
    $result = api_try('/system/scheduler/add', $payload);
    jm_log('scheduler-add', $name);
    jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Scheduler ' . $name . ' dibuat.' : 'Gagal: ' . $result['error']);
    redirect(url('system', ['tab' => 'scheduler']));
}
if ($do === 'scheduler-toggle' && $api) {
    $result = api_try('/system/scheduler/set', ['.id' => post_str('id'), 'disabled' => post_str('disabled') === 'true' ? 'no' : 'yes']);
    jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Status scheduler diperbarui.' : 'Gagal: ' . $result['error']);
    redirect(url('system', ['tab' => 'scheduler']));
}
if ($do === 'scheduler-delete' && $api) {
    $result = api_try('/system/scheduler/remove', ['.id' => post_str('id')]);
    jm_log('scheduler-delete', post_str('name'));
    jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Scheduler dihapus.' : 'Gagal: ' . $result['error']);
    redirect(url('system', ['tab' => 'scheduler']));
}
if ($do === 'identity-save' && $api) {
    $name = post_str('identity');
    if ($name === '') {
        jm_flash('danger', 'Identity tidak boleh kosong.');
    } else {
        $result = api_try('/system/identity/set', ['name' => $name]);
        jm_log('identity-save', $name);
        jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Identity router diperbarui menjadi ' . $name . '.' : 'Gagal: ' . $result['error']);
    }
    redirect(url('system', ['tab' => 'tools']));
}
if ($do === 'reboot' && $api) {
    jm_log('router-reboot', router_label($router));
    $result = api_try('/system/reboot');
    jm_flash('warning', 'Perintah reboot dikirim. Router akan segera tersambung ulang.');
    redirect(url('system', ['tab' => 'tools']));
}
if ($do === 'shutdown' && $api) {
    jm_log('router-shutdown', router_label($router));
    $result = api_try('/system/shutdown');
    jm_flash('warning', 'Perintah shutdown dikirim. Router akan mati.');
    redirect(url('system', ['tab' => 'tools']));
}
if ($do === 'audit-clear') {
    @file_put_contents(data_path('audit.log'), '');
    jm_log('audit-clear', 'Audit log dibersihkan');
    jm_flash('success', 'Audit log JarMon dibersihkan.');
    redirect(url('system', ['tab' => 'audit']));
}

$schedulers = jm_api_ready() ? api_comm('/system/scheduler/print') : [];
$info = jm_current_router_info();
$audit = jm_audit_read(500);
$actionLabels = [
    'login' => ['Masuk', 'success'],
    'logout' => ['Keluar', 'neutral'],
    'install' => ['Instalasi', 'accent'],
    'user-create' => ['Buat user', 'success'],
    'user-update' => ['Ubah user', 'info'],
    'user-delete' => ['Hapus user', 'danger'],
    'user-enable' => ['Aktifkan user', 'success'],
    'user-disable' => ['Nonaktifkan user', 'warning'],
    'user-reset' => ['Reset user', 'warning'],
    'user-kick' => ['Putus sesi', 'warning'],
    'user-generate' => ['Generate voucher', 'accent'],
    'user-batch' => ['Aksi massal', 'info'],
    'profile-save' => ['Simpan profil', 'info'],
    'profile-delete' => ['Hapus profil', 'danger'],
    'voucher-generate' => ['Voucher studio', 'accent'],
    'voucher-quick' => ['Cetak cepat', 'accent'],
    'package-save' => ['Simpan paket', 'info'],
    'binding-add' => ['Tambah binding', 'info'],
    'binding-delete' => ['Hapus binding', 'danger'],
    'host-binding' => ['Binding host', 'info'],
    'router-reboot' => ['Reboot router', 'danger'],
    'router-shutdown' => ['Shutdown router', 'danger'],
    'identity-save' => ['Ubah identity', 'info'],
    'report-clear' => ['Hapus laporan', 'danger'],
    'audit-clear' => ['Bersihkan audit', 'warning'],
];
$pageTitle = 'Sistem';
$activeNav = 'system:' . $tab;
?>
<?php page_header('Sistem', 'Scheduler, alat router, dan jejak aktivitas JarMon.', '<a class="btn" href="' . e(url('system', ['tab' => $tab])) . '">' . icon('refresh') . ' Muat ulang</a>'); ?>

<div class="tabs mb-2">
    <a class="tab <?= $tab === 'scheduler' ? 'active' : '' ?>" href="<?= e(url('system', ['tab' => 'scheduler'])) ?>"><?= icon('clock') ?> Scheduler</a>
    <a class="tab <?= $tab === 'tools' ? 'active' : '' ?>" href="<?= e(url('system', ['tab' => 'tools'])) ?>"><?= icon('tools') ?> Router tools</a>
    <a class="tab <?= $tab === 'audit' ? 'active' : '' ?>" href="<?= e(url('system', ['tab' => 'audit'])) ?>"><?= icon('shield') ?> Audit log</a>
</div>

<?php if ($tab === 'scheduler'): ?>
    <div class="grid grid-2-1">
        <div class="card">
            <div class="table-toolbar">
                <div class="search-box"><?= icon('search') ?><input class="input input-sm" type="text" placeholder="Cari scheduler..." data-search="#schedulerTable"></div>
                <span class="spacer"></span>
                <span class="badge badge-neutral"><?= count($schedulers) ?> scheduler</span>
            </div>
            <div class="table-wrap">
                <table class="data-table" id="schedulerTable" data-table data-page-size="<?= (int) setting('page_size', 25) ?>">
                    <thead><tr><th class="sortable">Nama</th><th>Start</th><th>Interval</th><th>Next run</th><th>Status</th><th>Komentar</th><th style="width:90px"></th></tr></thead>
                    <tbody>
                    <?php foreach ($schedulers as $scheduler): ?>
                        <?php $disabled = ($scheduler['disabled'] ?? '') === 'true'; ?>
                        <tr>
                            <td class="mono text-small"><?= e($scheduler['name'] ?? '-') ?></td>
                            <td class="mono text-small"><?= e($scheduler['start-time'] ?? '-') ?></td>
                            <td class="mono text-small"><?= e($scheduler['interval'] ?? '-') ?></td>
                            <td class="mono text-small"><?= e($scheduler['next-run'] ?? '-') ?></td>
                            <td><span class="badge <?= $disabled ? 'badge-warning' : 'badge-success' ?>"><?= $disabled ? 'Nonaktif' : 'Aktif' ?></span></td>
                            <td class="text-small"><?= e($scheduler['comment'] ?? '') ?></td>
                            <td class="actions-cell">
                                <form method="post" action="<?= e(url('system', ['tab' => 'scheduler'])) ?>" style="display:inline">
                                    <?= csrf_field() ?><input type="hidden" name="do" value="scheduler-toggle"><input type="hidden" name="id" value="<?= e($scheduler['.id'] ?? '') ?>"><input type="hidden" name="disabled" value="<?= $disabled ? 'true' : 'false' ?>">
                                    <button class="icon-btn" type="submit" title="<?= $disabled ? 'Aktifkan' : 'Nonaktifkan' ?>"><?= icon($disabled ? 'lock' : 'unlock') ?></button>
                                </form>
                                <form method="post" action="<?= e(url('system', ['tab' => 'scheduler'])) ?>" style="display:inline" data-confirm="Hapus scheduler <?= e($scheduler['name'] ?? '') ?>?">
                                    <?= csrf_field() ?><input type="hidden" name="do" value="scheduler-delete"><input type="hidden" name="id" value="<?= e($scheduler['.id'] ?? '') ?>"><input type="hidden" name="name" value="<?= e($scheduler['name'] ?? '') ?>">
                                    <button class="icon-btn" type="submit" title="Hapus" style="color:var(--danger)"><?= icon('trash') ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div data-table-empty style="display:none"><?= empty_state('clock', 'Tidak ada scheduler', 'Scheduler dipakai untuk monitor kedaluwarsa voucher.') ?></div>
        </div>
        <div class="card">
            <div class="card-head"><h3><?= icon('plus') ?> Tambah scheduler</h3></div>
            <form method="post" action="<?= e(url('system', ['tab' => 'scheduler'])) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="scheduler-add">
                <div class="card-body">
                    <div class="form-grid cols-1">
                        <div class="field"><label for="scname">Nama</label><input class="input" id="scname" name="name" required placeholder="nama-scheduler"></div>
                        <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
                            <div class="field"><label for="scstart">Start time</label><input class="input mono" id="scstart" name="start_time" value="00:00:00" placeholder="HH:MM:SS"></div>
                            <div class="field"><label for="scinterval">Interval</label><input class="input mono" id="scinterval" name="interval" value="00:01:00" placeholder="HH:MM:SS"></div>
                        </div>
                        <div class="field"><label for="scevent">On event (script)</label><textarea class="textarea mono" id="scevent" name="on_event" rows="5" required placeholder=":log info &quot;hello&quot;"></textarea></div>
                        <div class="field"><label for="sccomment">Komentar</label><input class="input" id="sccomment" name="comment" placeholder="opsional"></div>
                        <label class="switch"><input type="checkbox" name="disabled" value="yes"><span class="track"></span> Nonaktifkan</label>
                    </div>
                </div>
                <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan scheduler</button></div>
            </form>
        </div>
    </div>

<?php elseif ($tab === 'tools'): ?>
    <div class="grid grid-2">
        <div class="card">
            <div class="card-head"><h3><?= icon('info') ?> Informasi router</h3><span class="spacer"></span><span class="badge <?= jm_api_ready() ? 'badge-success' : 'badge-danger' ?>"><?= jm_api_ready() ? 'Online' : 'Offline' ?></span></div>
            <div class="card-body">
                <div class="keyval"><span>Identity</span><strong><?= e($info['identity'] ?: router_label($router)) ?></strong></div>
                <div class="keyval"><span>Board</span><strong><?= e($info['resource']['board-name'] ?? '-') ?></strong></div>
                <div class="keyval"><span>RouterOS</span><strong><?= e($info['resource']['version'] ?? '-') ?></strong></div>
                <div class="keyval"><span>Uptime</span><strong><?= e(fmt_uptime($info['resource']['uptime'] ?? '-')) ?></strong></div>
                <div class="keyval"><span>CPU load</span><strong><?= e($info['resource']['cpu-load'] ?? '0') ?>%</strong></div>
                <div class="keyval"><span>Memori bebas</span><strong><?= e(fmt_bytes($info['resource']['free-memory'] ?? 0)) ?></strong></div>
                <div class="keyval"><span>Penyimpanan bebas</span><strong><?= e(fmt_bytes($info['resource']['free-hdd-space'] ?? 0)) ?></strong></div>
                <div class="keyval"><span>Jam router</span><strong><?= e(($info['clock']['time'] ?? '-') . ' · ' . ($info['clock']['date'] ?? '-')) ?></strong></div>
                <div class="keyval"><span>Timezone</span><strong><?= e($info['clock']['time-zone-name'] ?? '-') ?></strong></div>
            </div>
        </div>
        <div class="stack">
            <div class="card">
                <div class="card-head"><h3><?= icon('edit') ?> Ubah identity</h3></div>
                <form method="post" action="<?= e(url('system', ['tab' => 'tools'])) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="identity-save">
                    <div class="card-body">
                        <div class="field"><label for="identity">Nama identity router</label><input class="input" id="identity" name="identity" value="<?= e($info['identity']) ?>" placeholder="MikroTik"></div>
                    </div>
                    <div class="card-foot"><button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan identity</button></div>
                </form>
            </div>
            <div class="card">
                <div class="card-head"><h3><?= icon('power') ?> Daya router</h3></div>
                <div class="card-body">
                    <p class="text-small text-muted mb-2">Gunakan dengan hati-hati. Router akan kehilangan koneksi saat reboot atau shutdown.</p>
                    <div class="row-flex">
                        <form method="post" action="<?= e(url('system', ['tab' => 'tools'])) ?>" data-confirm="Reboot router <?= e(router_label($router)) ?> sekarang?">
                            <?= csrf_field() ?><input type="hidden" name="do" value="reboot">
                            <button class="btn btn-warning" type="submit"><?= icon('refresh') ?> Reboot</button>
                        </form>
                        <form method="post" action="<?= e(url('system', ['tab' => 'tools'])) ?>" data-confirm="Matikan router <?= e(router_label($router)) ?>? Anda harus menyalakannya secara manual.">
                            <?= csrf_field() ?><input type="hidden" name="do" value="shutdown">
                            <button class="btn btn-danger" type="submit"><?= icon('power') ?> Shutdown</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="card">
        <div class="table-toolbar">
            <div class="search-box"><?= icon('search') ?><input class="input input-sm" type="text" placeholder="Cari aktivitas..." data-search="#auditTable"></div>
            <span class="spacer"></span>
            <span class="badge badge-neutral"><?= count($audit) ?> entri</span>
            <form method="post" action="<?= e(url('system', ['tab' => 'audit'])) ?>" data-confirm="Bersihkan seluruh audit log JarMon?">
                <?= csrf_field() ?><input type="hidden" name="do" value="audit-clear">
                <button class="btn btn-sm btn-danger" type="submit"><?= icon('trash') ?> Bersihkan</button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="data-table" id="auditTable" data-table data-page-size="<?= (int) setting('page_size', 25) ?>">
                <thead><tr><th class="sortable" style="width:160px">Waktu</th><th>Admin</th><th>Aksi</th><th>Detail</th><th>Router</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach ($audit as $entry): ?>
                    <?php [$label, $cls] = $actionLabels[$entry['action'] ?? ''] ?? [$entry['action'] ?? '-', 'neutral']; ?>
                    <tr>
                        <td class="mono text-small nowrap"><?= e($entry['time'] ?? '') ?></td>
                        <td><?= e($entry['user'] ?? '-') ?></td>
                        <td><span class="badge badge-<?= e($cls) ?>"><?= e($label) ?></span></td>
                        <td class="text-small"><?= e($entry['detail'] ?? '') ?></td>
                        <td class="text-small"><?= e($entry['router'] ?? '-') ?: '-' ?></td>
                        <td class="mono text-small"><?= e($entry['ip'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div data-table-empty style="display:none"><?= empty_state('shield', 'Audit log kosong', 'Aktivitas admin JarMon akan tercatat di sini.') ?></div>
    </div>
<?php endif; ?>
