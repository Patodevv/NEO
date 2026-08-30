<?php
$corSite = (!empty($usuario['cor'])) ? $usuario['cor'] : '#0878ff';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina ?? 'NeoMind') ?> · NeoMind</title>

    <link rel="stylesheet" href="static/style.css">
    <?php if (!empty($usaSidebar)): ?>
        <link rel="stylesheet" href="static/sidebar.css">
    <?php endif; ?>
    <?php foreach (($cssPaginas ?? []) as $cssPagina): ?>
        <link rel="stylesheet" href="static/pages/<?= htmlspecialchars($cssPagina) ?>.css">
    <?php endforeach; ?>

    <style>
        :root {
            --primary: <?= htmlspecialchars($corSite) ?>;
        }
    </style>
</head>
<body>
