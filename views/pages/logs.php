<?php
$topicFilter = get_str('topics', 'hotspot,info,debug');
$logs = [];
if (jm_api_ready()) {
    $args = $topicFilter !== '' ? ['?topics' => $topicFilter] : [];
    $logs = array_reverse(api_comm('/log/print', $args));
    $logs = array_slice($logs, 0, 500);
}
$refresh = (int) setting('refresh', 20);
$pageTitle = 'Log Hotspot';
$activeNav = 'logs';
?>
<?php page_header('Log hotspot', 'Aktivitas router terkini. Data diambil langsung dari RouterOS.', '<a class="btn" href="' . e(url('logs', ['topics' => $topicFilter])) . '">' . icon('refresh') . ' Muat ulang</a><a class="btn" href="' . e(url('system', ['tab' => 'audit'])) . '">' . icon('shield') . ' Audit JarMon</a>'); ?>

<div class="card">
    <div class="table-toolbar">
        <div class="search-box"><?= icon('search') ?><input class="input input-sm" type="text" placeholder="Cari pesan log..." data-search="#logTable"></div>
        <form method="get" action="<?= e(url('logs')) ?>" class="row-flex">
            <input type="hidden" name="p" value="logs">
            <?php if (current_router_slug()): ?><input type="hidden" name="r" value="<?= e(current_router_slug()) ?>"><?php endif; ?>
            <select class="select select-sm" name="topics" data-autosubmit style="width:220px">
                <?php foreach ([
                    'hotspot,info,debug' => 'Hotspot (info & debug)',
                    'hotspot' => 'Hotspot saja',
                    'info' => 'Info',
                    'error' => 'Error',
                    'warning' => 'Warning',
                    'script,info' => 'Script',
                    '' => 'Semua topik',
                ] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $topicFilter === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <span class="spacer"></span>
        <span class="badge badge-neutral"><?= fmt_number(count($logs)) ?> baris</span>
        <span class="badge badge-neutral"><?= icon('clock', 'icon-sm') ?> refresh <span data-autorefresh-label><?= $refresh ?>s</span></span>
    </div>
    <div class="table-wrap" data-autorefresh="<?= $refresh ?>">
        <table class="data-table" id="logTable" data-table data-page-size="<?= (int) setting('page_size', 50) ?>">
            <thead><tr><th style="width:130px">Waktu</th><th style="width:180px">Topik</th><th>Pesan</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $entry): ?>
                <tr>
                    <td class="mono text-small nowrap"><?= e($entry['time'] ?? '') ?></td>
                    <td class="text-small"><span class="badge badge-neutral"><?= e($entry['topics'] ?? '-') ?></span></td>
                    <td class="text-small" style="overflow-wrap:anywhere"><?= e($entry['message'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div data-table-empty style="display:none"><?= empty_state('list', 'Log kosong', 'Belum ada log untuk filter ini.') ?></div>
</div>
