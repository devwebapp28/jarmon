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
</head>
<body>
<?= $content ?>
</body>
</html>
