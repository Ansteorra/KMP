<?php
/** Public offline shell: never render session identity, flash, CSRF or private navigation here. */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="kmp-offline-shell" content="1">
    <title>KMP Offline Access</title>
    <?= $this->Vite->css('app') ?>
    <?= $this->Vite->script('controllers') ?>
    <?= $this->Vite->script('index') ?>
</head>
<body>
    <main id="main-content" class="container py-4" tabindex="-1">
        <?= $this->fetch('content') ?>
    </main>
</body>
</html>
