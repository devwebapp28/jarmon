<?php
$tab = get_str('tab', 'hosts');
if (!in_array($tab, ['hosts', 'bindings', 'cookies', 'dhcp', 'interfaces'], true)) {
    $tab = 'hosts';
}
$do = post_str('do');
$api = jm_api();

if ($do !== '' && $api) {
    if ($do === 'binding-add') {
        $payload = [
            'type' => post_str('type', 'regular'),
            'mac-address' => post_str('mac'),
            'address' => post_str('address'),
            'to-address' => post_str('to-address'),
            'server' => post_str('server', 'all'),
            'comment' => post_str('comment'),
        ];
        if ($payload['mac-address'] === '' && $payload['address'] === '') {
            jm_flash('danger', 'Isi MAC address atau IP address untuk binding.');
        } else {
            $result = api_try('/ip/hotspot/ip-binding/add', $payload);
            jm_log('binding-add', $payload['mac-address'] . ' ' . $payload['address']);
            jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'IP binding ditambahkan.' : 'Gagal menambah binding: ' . $result['error']);
        }
        redirect(url('network', ['tab' => 'bindings']));
    }
    if ($do === 'binding-toggle') {
        $result = api_try('/ip/hotspot/ip-binding/set', ['.id' => post_str('id'), 'disabled' => post_str('disabled') === 'true' ? 'no' : 'yes']);
        jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Status binding diperbarui.' : 'Gagal: ' . $result['error']);
        redirect(url('network', ['tab' => 'bindings']));
    }
    if ($do === 'binding-delete') {
        $result = api_try('/ip/hotspot/ip-binding/remove', ['.id' => post_str('id')]);
        jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Binding dihapus.' : 'Gagal: ' . $result['error']);
        jm_log('binding-delete', post_str('label'));
        redirect(url('network', ['tab' => 'bindings']));
    }
    if ($do === 'host-binding') {
        $result = api_try('/ip/hotspot/ip-binding/add', [
            'type' => 'bypassed',
            'mac-address' => post_str('mac'),
            'address' => post_str('address'),
            'server' => post_str('server', 'all'),
            'comment' => post_str('comment'),
        ]);
        jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Host dijadikan IP binding (bypassed).' : 'Gagal: ' . $result['error']);
        jm_log('host-binding', post_str('mac'));
        redirect(url('network', ['tab' => 'hosts']));
    }
    if ($do === 'host-delete') {
        $result = api_try('/ip/hotspot/host/remove', ['.id' => post_str('id')]);
        jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Host dihapus.' : 'Gagal: ' . $result['error']);
        redirect(url('network', ['tab' => 'hosts']));
    }
    if ($do === 'cookie-delete') {
        $result = api_try('/ip/hotspot/cookie/remove', ['.id' => post_str('id')]);
        jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Cookie dihapus.' : 'Gagal: ' . $result['error']);
        redirect(url('network', ['tab' => 'cookies']));
    }
    if ($do === 'cookie-clear') {
        $cookies = api_comm('/ip/hotspot/cookie/print');
        $count = 0;
        foreach ($cookies as $cookie) {
            $r = api_try('/ip/hotspot/cookie/remove', ['.id' => $cookie['.id']]);
            if ($r['ok']) $count++;
        }
        jm_log('cookie-clear', (string) $count);
        jm_flash('success', $count . ' cookie dihapus.');
        redirect(url('network', ['tab' => 'cookies']));
    }
    if ($do === 'lease-static') {
        $result = api_try('/ip/dhcp-server/lease/make-static', ['.id' => post_str('id')]);
        jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Lease dijadikan statis.' : 'Gagal: ' . $result['error']);
        redirect(url('network', ['tab' => 'dhcp']));
    }
    if ($do === 'lease-delete') {
        $result = api_try('/ip/dhcp-server/lease/remove', ['.id' => post_str('id')]);
        jm_flash($result['ok'] ? 'success' : 'danger', $result['ok'] ? 'Lease dihapus.' : 'Gagal: ' . $result['error']);
        redirect(url('network', ['tab' => 'dhcp']));
    }
}

$hosts = [];
$bindings = [];
$cookies = [];
$leases = [];
$interfaces = [];
$servers = [];
$view = get_str('view', 'all');
if (jm_api_ready()) {
    $servers = jm_servers();
    if ($tab === 'hosts') {
        $args = [];
        if ($view === 'authorized') $args['?authorized'] = 'yes';
        if ($view === 'bypassed') $args['?bypassed'] = 'yes';
        $hosts = api_comm('/ip/hotspot/host/print', $args);
    } elseif ($tab === 'bindings') {
        $bindings = api_comm('/ip/hotspot/ip-binding/print');
    } elseif ($tab === 'cookies') {
        $cookies = api_comm('/ip/hotspot/cookie/print');
    } elseif ($tab === 'dhcp') {
        $leases = api_comm('/ip/dhcp-server/lease/print');
    } else {
        $interfaces = api_comm('/interface/print');
    }
}
$pageTitle = 'Jaringan';
$activeNav = 'network:' . $tab;
$tabs = [
    'hosts' => ['Hosts', 'laptop'],
    'bindings' => ['IP Binding', 'link'],
    'cookies' => ['Cookies', 'cookie'],
    'dhcp' => ['DHCP Leases', 'server'],
    'interfaces' => ['Interfaces', 'network'],
];
$actions = '';
if ($tab === 'bindings') {
    $actions = '<button class="btn btn-primary" data-modal-open="bindingModal" type="button">' . icon('plus') . ' Tambah binding</button>';
} elseif ($tab === 'cookies' && $cookies) {
    $actions = '<form method="post" action="' . e(url('network', ['tab' => 'cookies'])) . '" data-confirm="Hapus semua cookie hotspot?"><input type="hidden" name="_csrf" value="' . e(jm_csrf_token()) . '"><input type="hidden" name="do" value="cookie-clear"><button class="btn btn-danger" type="submit">' . icon('trash') . ' Hapus semua</button></form>';
}
?>
<?php page_header('Jaringan', 'Host, binding, cookie, DHCP, dan antarmuka router.', $actions); ?>

<div class="tabs mb-2">
    <?php foreach ($tabs as $key => [$label, $ic]): ?>
        <a class="tab <?= $tab === $key ? 'active' : '' ?>" href="<?= e(url('network', ['tab' => $key])) ?>"><?= icon($ic) ?> <?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'hosts'): ?>
    <div class="card">
        <div class="table-toolbar">
            <div class="search-box"><?= icon('search') ?><input class="input input-sm" type="text" placeholder="Cari host..." data-search="#hostsTable"></div>
            <div class="segmented">
                <?php foreach (['all' => 'Semua', 'authorized' => 'Authorized', 'bypassed' => 'Bypassed'] as $key => $label): ?>
                    <a class="<?= $view === $key ? 'active' : '' ?>" href="<?= e(url('network', ['tab' => 'hosts', 'view' => $key])) ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>
            <span class="spacer"></span>
            <span class="badge badge-neutral"><?= fmt_number(count($hosts)) ?> host</span>
        </div>
        <div class="table-wrap">
            <table class="data-table" id="hostsTable" data-table data-page-size="<?= (int) setting('page_size', 25) ?>">
                <thead><tr><th>MAC</th><th>Address</th><th>To address</th><th>Server</th><th>Status</th><th>Komentar</th><th style="width:100px"></th></tr></thead>
                <tbody>
                <?php foreach ($hosts as $host): ?>
                    <tr>
                        <td class="mono"><?= e($host['mac-address'] ?? '-') ?></td>
                        <td class="mono"><?= e($host['address'] ?? '-') ?></td>
                        <td class="mono"><?= e($host['to-address'] ?? '-') ?: '-' ?></td>
                        <td><?= e($host['server'] ?? '-') ?></td>
                        <td>
                            <?php if (($host['authorized'] ?? '') === 'true'): ?><span class="badge badge-success">A</span><?php endif; ?>
                            <?php if (($host['bypassed'] ?? '') === 'true'): ?><span class="badge badge-info">P</span><?php endif; ?>
                            <?php if (($host['DHCP'] ?? '') === 'true'): ?><span class="badge badge-neutral">H</span><?php endif; ?>
                            <?php if (($host['dynamic'] ?? '') === 'true'): ?><span class="badge badge-neutral">D</span><?php endif; ?>
                        </td>
                        <td class="text-small"><?= e($host['comment'] ?? '') ?></td>
                        <td class="actions-cell">
                            <form method="post" action="<?= e(url('network', ['tab' => 'hosts'])) ?>" style="display:inline" data-confirm="Jadikan <?= e($host['mac-address'] ?? '') ?> bypassed binding?">
                                <?= csrf_field() ?><input type="hidden" name="do" value="host-binding">
                                <input type="hidden" name="mac" value="<?= e($host['mac-address'] ?? '') ?>">
                                <input type="hidden" name="address" value="<?= e($host['address'] ?? '') ?>">
                                <input type="hidden" name="server" value="<?= e($host['server'] ?? 'all') ?>">
                                <input type="hidden" name="comment" value="<?= e($host['comment'] ?? '') ?>">
                                <button class="icon-btn" type="submit" title="Buat IP binding"><?= icon('link') ?></button>
                            </form>
                            <form method="post" action="<?= e(url('network', ['tab' => 'hosts'])) ?>" style="display:inline" data-confirm="Hapus host <?= e($host['mac-address'] ?? '') ?>?">
                                <?= csrf_field() ?><input type="hidden" name="do" value="host-delete"><input type="hidden" name="id" value="<?= e($host['.id'] ?? '') ?>">
                                <button class="icon-btn" type="submit" title="Hapus" style="color:var(--danger)"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div data-table-empty style="display:none"><?= empty_state('laptop', 'Tidak ada host', 'Host yang terdeteksi hotspot akan tampil di sini.') ?></div>
    </div>

<?php elseif ($tab === 'bindings'): ?>
    <div class="card">
        <div class="table-toolbar">
            <div class="search-box"><?= icon('search') ?><input class="input input-sm" type="text" placeholder="Cari binding..." data-search="#bindingsTable"></div>
            <span class="spacer"></span>
            <span class="badge badge-neutral"><?= fmt_number(count($bindings)) ?> binding</span>
        </div>
        <div class="table-wrap">
            <table class="data-table" id="bindingsTable" data-table data-page-size="<?= (int) setting('page_size', 25) ?>">
                <thead><tr><th>Komentar</th><th>MAC</th><th>Address</th><th>To address</th><th>Server</th><th>Type</th><th>Status</th><th style="width:110px"></th></tr></thead>
                <tbody>
                <?php foreach ($bindings as $binding): ?>
                    <?php $isDisabled = ($binding['disabled'] ?? '') === 'true'; ?>
                    <tr>
                        <td><?= e($binding['comment'] ?? '') ?: '-' ?></td>
                        <td class="mono"><?= e($binding['mac-address'] ?? '') ?: '-' ?></td>
                        <td class="mono"><?= e($binding['address'] ?? '') ?: '-' ?></td>
                        <td class="mono"><?= e($binding['to-address'] ?? '') ?: '-' ?></td>
                        <td><?= e($binding['server'] ?? 'all') ?></td>
                        <td><span class="badge badge-neutral"><?= e($binding['type'] ?? 'regular') ?></span></td>
                        <td><span class="badge <?= $isDisabled ? 'badge-warning' : 'badge-success' ?>"><?= $isDisabled ? 'Nonaktif' : 'Aktif' ?></span></td>
                        <td class="actions-cell">
                            <form method="post" action="<?= e(url('network', ['tab' => 'bindings'])) ?>" style="display:inline">
                                <?= csrf_field() ?><input type="hidden" name="do" value="binding-toggle"><input type="hidden" name="id" value="<?= e($binding['.id'] ?? '') ?>"><input type="hidden" name="disabled" value="<?= $isDisabled ? 'true' : 'false' ?>">
                                <button class="icon-btn" type="submit" title="<?= $isDisabled ? 'Aktifkan' : 'Nonaktifkan' ?>"><?= icon($isDisabled ? 'lock' : 'unlock') ?></button>
                            </form>
                            <form method="post" action="<?= e(url('network', ['tab' => 'bindings'])) ?>" style="display:inline" data-confirm="Hapus binding ini?">
                                <?= csrf_field() ?><input type="hidden" name="do" value="binding-delete"><input type="hidden" name="id" value="<?= e($binding['.id'] ?? '') ?>"><input type="hidden" name="label" value="<?= e($binding['mac-address'] ?? '') ?>">
                                <button class="icon-btn" type="submit" title="Hapus" style="color:var(--danger)"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div data-table-empty style="display:none"><?= empty_state('link', 'Belum ada binding', 'Tambahkan binding untuk mengizinkan atau memblokir perangkat.') ?></div>
    </div>

    <dialog class="modal" id="bindingModal" data-reset-on-close>
        <div class="modal-head"><h2><?= icon('link') ?> Tambah IP binding</h2><button class="icon-btn" type="button" data-modal-close><?= icon('close') ?></button></div>
        <form method="post" action="<?= e(url('network', ['tab' => 'bindings'])) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="do" value="binding-add">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="field"><label for="btype">Tipe</label><select class="select" id="btype" name="type"><option value="regular">Regular</option><option value="bypassed">Bypassed</option><option value="blocked">Blocked</option></select></div>
                    <div class="field"><label for="bcomment">Komentar</label><input class="input" id="bcomment" name="comment" placeholder="misal: printer kantor"></div>
                    <div class="field"><label for="bmac">MAC Address</label><input class="input mono" id="bmac" name="mac" placeholder="AA:BB:CC:DD:EE:FF"></div>
                    <div class="field"><label for="baddress">IP Address</label><input class="input mono" id="baddress" name="address" placeholder="192.168.88.10"></div>
                    <div class="field"><label for="bto">To Address</label><input class="input mono" id="bto" name="to-address" placeholder="opsional"></div>
                    <div class="field"><label for="bserver">Server</label><select class="select" id="bserver" name="server"><option value="all">all</option><?php foreach ($servers as $server): ?><option value="<?= e($server['name'] ?? '') ?>"><?= e($server['name'] ?? '') ?></option><?php endforeach; ?></select></div>
                </div>
            </div>
            <div class="modal-foot"><button class="btn btn-ghost" type="button" data-modal-close>Batal</button><button class="btn btn-primary" type="submit"><?= icon('check') ?> Simpan binding</button></div>
        </form>
    </dialog>

<?php elseif ($tab === 'cookies'): ?>
    <div class="card">
        <div class="table-toolbar">
            <div class="search-box"><?= icon('search') ?><input class="input input-sm" type="text" placeholder="Cari cookie..." data-search="#cookiesTable"></div>
            <span class="spacer"></span>
            <span class="badge badge-neutral"><?= fmt_number(count($cookies)) ?> cookie</span>
        </div>
        <div class="table-wrap">
            <table class="data-table" id="cookiesTable" data-table data-page-size="<?= (int) setting('page_size', 25) ?>">
                <thead><tr><th>User</th><th>MAC</th><th>Domain</th><th>Expires in</th><th style="width:60px"></th></tr></thead>
                <tbody>
                <?php foreach ($cookies as $cookie): ?>
                    <tr>
                        <td><?= e($cookie['user'] ?? '-') ?></td>
                        <td class="mono"><?= e($cookie['mac-address'] ?? '-') ?></td>
                        <td><?= e($cookie['domain'] ?? '-') ?></td>
                        <td><?= e(fmt_uptime($cookie['expires-in'] ?? '')) ?></td>
                        <td class="actions-cell">
                            <form method="post" action="<?= e(url('network', ['tab' => 'cookies'])) ?>" style="display:inline" data-confirm="Hapus cookie ini?">
                                <?= csrf_field() ?><input type="hidden" name="do" value="cookie-delete"><input type="hidden" name="id" value="<?= e($cookie['.id'] ?? '') ?>">
                                <button class="icon-btn" type="submit" title="Hapus" style="color:var(--danger)"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div data-table-empty style="display:none"><?= empty_state('cookie', 'Tidak ada cookie hotspot', 'Cookie muncul setelah pengguna login dengan opsi cookie.') ?></div>
    </div>

<?php elseif ($tab === 'dhcp'): ?>
    <div class="card">
        <div class="table-toolbar">
            <div class="search-box"><?= icon('search') ?><input class="input input-sm" type="text" placeholder="Cari lease..." data-search="#leaseTable"></div>
            <span class="spacer"></span>
            <span class="badge badge-neutral"><?= fmt_number(count($leases)) ?> lease</span>
        </div>
        <div class="table-wrap">
            <table class="data-table" id="leaseTable" data-table data-page-size="<?= (int) setting('page_size', 25) ?>">
                <thead><tr><th>Address</th><th>MAC</th><th>Server</th><th>Host name</th><th>Status</th><th>Type</th><th style="width:100px"></th></tr></thead>
                <tbody>
                <?php foreach ($leases as $lease): ?>
                    <?php $dynamic = ($lease['dynamic'] ?? '') === 'true'; ?>
                    <tr>
                        <td class="mono"><?= e($lease['address'] ?? '-') ?></td>
                        <td class="mono"><?= e($lease['mac-address'] ?? '-') ?></td>
                        <td><?= e($lease['server'] ?? '-') ?></td>
                        <td><?= e($lease['host-name'] ?? '') ?: '-' ?></td>
                        <td><span class="badge <?= ($lease['status'] ?? '') === 'bound' ? 'badge-success' : 'badge-neutral' ?>"><?= e($lease['status'] ?? '-') ?></span></td>
                        <td><span class="badge badge-neutral"><?= $dynamic ? 'Dynamic' : 'Static' ?></span></td>
                        <td class="actions-cell">
                            <?php if ($dynamic): ?>
                                <form method="post" action="<?= e(url('network', ['tab' => 'dhcp'])) ?>" style="display:inline" data-confirm="Jadikan lease ini statis?">
                                    <?= csrf_field() ?><input type="hidden" name="do" value="lease-static"><input type="hidden" name="id" value="<?= e($lease['.id'] ?? '') ?>">
                                    <button class="icon-btn" type="submit" title="Jadikan statis"><?= icon('lock') ?></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('network', ['tab' => 'dhcp'])) ?>" style="display:inline" data-confirm="Hapus lease <?= e($lease['address'] ?? '') ?>?">
                                <?= csrf_field() ?><input type="hidden" name="do" value="lease-delete"><input type="hidden" name="id" value="<?= e($lease['.id'] ?? '') ?>">
                                <button class="icon-btn" type="submit" title="Hapus" style="color:var(--danger)"><?= icon('trash') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div data-table-empty style="display:none"><?= empty_state('server', 'Tidak ada DHCP lease', 'Lease akan muncul jika DHCP server aktif.') ?></div>
    </div>

<?php else: ?>
    <div class="card">
        <div class="table-toolbar">
            <div class="search-box"><?= icon('search') ?><input class="input input-sm" type="text" placeholder="Cari interface..." data-search="#ifaceTable"></div>
            <span class="spacer"></span>
            <span class="badge badge-neutral"><?= fmt_number(count($interfaces)) ?> interface</span>
        </div>
        <div class="table-wrap">
            <table class="data-table" id="ifaceTable" data-table data-page-size="<?= (int) setting('page_size', 25) ?>">
                <thead><tr><th>Nama</th><th>Type</th><th>Status</th><th class="num">MTU</th><th class="num">RX</th><th class="num">TX</th><th>Komentar</th></tr></thead>
                <tbody>
                <?php foreach ($interfaces as $iface): ?>
                    <?php $running = ($iface['running'] ?? '') === 'true'; ?>
                    <tr>
                        <td class="user-link"><?= e($iface['name'] ?? '-') ?></td>
                        <td><span class="badge badge-neutral"><?= e($iface['type'] ?? '-') ?></span></td>
                        <td>
                            <span class="badge <?= $running ? 'badge-success' : 'badge-danger' ?>"><span class="dot <?= $running ? 'online' : 'offline' ?>"></span><?= $running ? 'Running' : 'Down' ?></span>
                            <?php if (($iface['disabled'] ?? '') === 'true'): ?><span class="badge badge-warning">Disabled</span><?php endif; ?>
                        </td>
                        <td class="num"><?= e($iface['mtu'] ?? '-') ?></td>
                        <td class="num"><?= e(fmt_bytes($iface['rx-byte'] ?? 0)) ?></td>
                        <td class="num"><?= e(fmt_bytes($iface['tx-byte'] ?? 0)) ?></td>
                        <td class="text-small"><?= e($iface['comment'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div data-table-empty style="display:none"><?= empty_state('network', 'Tidak ada interface', 'Interface router akan tampil di sini.') ?></div>
    </div>
<?php endif; ?>
