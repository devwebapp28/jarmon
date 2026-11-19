<?php
$theme = $app['theme'] ?? 'dark';
$accent = $app['accent'] ?? 'indigo';
$base = jm_base_url();
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= e($theme) ?>" data-accent="<?= e($accent) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JarMon</title>
    <link rel="icon" href="<?= e($base) ?>/assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/app.css?v=<?= JARMON_VERSION ?>">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('jm-theme');
                var a = localStorage.getItem('jm-accent');
                if (t) document.documentElement.setAttribute('data-theme', t);
                if (a) document.documentElement.setAttribute('data-accent', a);
            } catch (e) {}
        })();
    </script>
</head>
<body>
<?= $content ?>
<script>window.JM_BASE = <?= json_encode($base) ?>;</script>
<script src="<?= e($base) ?>/assets/js/app.js?v=<?= JARMON_VERSION ?>" defer></script>
</body>
</html>
