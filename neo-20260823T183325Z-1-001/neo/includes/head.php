<?php
// Espera $tituloPagina definido. $usuario (array) opcional, usado para pegar a cor salva.
$corSite = (!empty($usuario['cor'])) ? $usuario['cor'] : '#0878ff';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina ?? 'NeoMind') ?> · NeoMind</title>
    <link rel="stylesheet" href="static/style.css">
    <style>:root { --primary: <?= htmlspecialchars($corSite) ?>; }</style>
</head>
<body>
