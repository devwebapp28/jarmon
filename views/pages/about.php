<?php
$pageTitle = 'Tentang JarMon';
$activeNav = 'about';
$userCount = count(jm_routers());
?>
<?php page_header('Tentang JarMon', 'Panel manajemen hotspot MikroTik yang ringan, rapi, dan mudah dipakai.', ''); ?>

<div class="card" style="overflow:hidden">
    <div style="padding:34px 30px;background:radial-gradient(700px 300px at 10% 0%,rgba(99,102,241,.35),transparent 60%),linear-gradient(135deg,#0b1020,#151d3b 70%);color:#eef2ff">
        <div class="row-flex" style="gap:14px">
            <span class="brand-mark" style="width:48px;height:48px;border-radius:14px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M4 14a8 8 0 0 1 16 0"/><path d="M7.5 17.5a5 5 0 0 1 9 0"/><circle cx="12" cy="20" r="1"/><path d="M12 4v3"/></svg></span>
            <div>
                <div style="font-size:26px;font-weight:750;letter-spacing:-.035em">JarMon</div>
                <div style="font-size:12px;color:rgba(226,232,255,.65)">Hotspot Manager &middot; versi <?= JARMON_VERSION ?></div>
            </div>
        </div>
        <p style="max-width:560px;margin-top:18px;color:rgba(226,232,255,.75);font-size:13.5px;line-height:1.65">Dibangun sebagai alternatif modern untuk pengelolaan hotspot MikroTik: antarmuka bersih, alur voucher cepat, laporan penjualan otomatis, dan dukungan banyak router dalam satu panel.</p>
    </div>
</div>

<div class="grid grid-3 mt-2">
    <?php
    $features = [
        ['ticket', 'Voucher Studio', 'Generate massal, template cetak, QR login, dan cetak cepat dari paket.'],
        ['money', 'Laporan penjualan', 'Rekap harian/bulanan otomatis dari on-login profile, ekspor CSV, dan cetak.'],
        ['activity', 'Monitoring langsung', 'Sesi aktif, traffic real-time, host, DHCP, cookies, dan interface.'],
        ['user-cog', 'Profil fleksibel', 'Mode kedaluwarsa, harga, rate limit, kunci perangkat, dan parent queue.'],
        ['shield', 'Audit & keamanan', 'Kredensial terenkripsi, CSRF token, audit log, dan auto logout.'],
        ['router', 'Multi-router', 'Kelola beberapa router MikroTik dan berpindah konteks dengan cepat.'],
    ];
    foreach ($features as [$ic, $title, $text]): ?>
        <div class="card"><div class="card-body">
            <span class="kpi-icon"><?= icon($ic) ?></span>
            <h3 style="font-size:14px;margin:10px 0 5px"><?= e($title) ?></h3>
            <p class="text-small text-muted"><?= e($text) ?></p>
        </div></div>
    <?php endforeach; ?>
</div>

<div class="grid grid-2 mt-2">
    <div class="card">
        <div class="card-head"><h3><?= icon('zap') ?> Pintasan</h3></div>
        <div class="card-body">
            <div class="keyval"><span>Command palette</span><strong><kbd>Ctrl</kbd> + <kbd>K</kbd></strong></div>
            <div class="keyval"><span>Fokus pencarian tabel</span><strong><kbd>/</kbd></strong></div>
            <div class="keyval"><span>Tutup dialog / menu</span><strong><kbd>Esc</kbd></strong></div>
            <div class="keyval"><span>Cetak halaman voucher</span><strong><kbd>Ctrl</kbd> + <kbd>P</kbd></strong></div>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h3><?= icon('info') ?> Informasi</h3></div>
        <div class="card-body">
            <div class="keyval"><span>Versi</span><strong><?= JARMON_VERSION ?></strong></div>
            <div class="keyval"><span>PHP</span><strong><?= e(PHP_VERSION) ?></strong></div>
            <div class="keyval"><span>Router terdaftar</span><strong><?= $userCount ?></strong></div>
            <div class="keyval"><span>Lisensi</span><strong>GPL v2</strong></div>
            <div class="keyval"><span>Penyimpanan</span><strong class="mono">data/config.json</strong></div>
        </div>
    </div>
</div>

<div class="card mt-2">
    <div class="card-head"><h3><?= icon('spark') ?> Kredit &amp; lisensi</h3></div>
    <div class="card-body text-small text-muted">
        <p class="mb-2">JarMon menggunakan <strong>RouterOS PHP API class</strong> karya Denis Basta beserta kontributor. QR code menggunakan pustaka <strong>QRious</strong>.</p>
        <p>Perangkat lunak ini didistribusikan di bawah GNU General Public License versi 2. Anda bebas memodifikasi dan mendistribusikan ulang sesuai ketentuan lisensi.</p>
        <div class="divider"></div>
        <p class="mb-1"><strong class="text">JarMon <?= JARMON_VERSION ?></strong> — rilis awal</p>
        <ul style="margin:6px 0 0 18px;padding:0;line-height:1.9">
            <li>Kompatibilitas RouterOS v6.43+ sampai v7 terbaru, termasuk format tanggal ISO 7.10+.</li>
            <li>Monitor kedaluwarsa profil berjalan tiap menit dan bisa disinkronkan ulang dari halaman Profil.</li>
            <li>Bebas peringatan deprecated PHP 8.5 pada koneksi API.</li>
            <li>Aksi cepat voucher terhubung penuh dengan profil, mode, prefix, karakter, dan template paket.</li>
            <li>Riwayat batch voucher tersimpan di server; cetak ulang tanpa membuat kredensial baru.</li>
            <li>Perubahan konfigurasi membuat cadangan otomatis dan tidak menghapus router/paket/batch yang ada.</li>
            <li>Layout responsif untuk desktop, tablet, dan mobile; sidebar memiliki scroll independen.</li>
            <li>Upload logo voucher per router melalui Pengaturan &rarr; Logo.</li>
            <li>Dashboard modern dengan metrik langsung dan grafik traffic.</li>
            <li>Voucher studio, paket, cetak cepat, serta tiga template cetak.</li>
            <li>Manajemen pengguna massal, profil kedaluwarsa, dan laporan penjualan.</li>
            <li>Network tools, PPP, scheduler, audit log, dan backup konfigurasi.</li>
        </ul>
    </div>
</div>
