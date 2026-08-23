<?php
// $paginaAtual precisa estar definida antes do include (ex: 'dashboard', 'materias', 'historico', 'config')
$paginaAtual = $paginaAtual ?? '';
?>
<aside class="sidebar">

    <a href="index.php" class="logo">NEuO<br>MIND</a>

    <a href="index.php" class="nav-btn <?= $paginaAtual === 'dashboard' ? 'active' : '' ?>" title="Dashboard">⌂</a>

    <a href="materias.php" class="nav-btn <?= $paginaAtual === 'materias' ? 'active' : '' ?>" title="Matérias">▦</a>

    <a href="historico.php" class="nav-btn <?= $paginaAtual === 'historico' ? 'active' : '' ?>" title="Histórico">◷</a>

    <div class="nav-bottom">
        <a href="config.php" class="nav-btn <?= $paginaAtual === 'config' ? 'active' : '' ?>" title="Configurações">⚙</a>
    </div>

</aside>
