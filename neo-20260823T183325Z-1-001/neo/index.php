<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$totalQuestoes = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(acertos),0), COALESCE(SUM(total),0) FROM historico WHERE user_id = ?");
$totalQuestoes->execute([$usuario['id']]);
[$qtdHistorico, $somaAcertos, $somaTotal] = $totalQuestoes->fetch(PDO::FETCH_NUM);
$desempenho = $somaTotal > 0 ? round(($somaAcertos / $somaTotal) * 100) : 0;
$tituloPagina = 'Dashboard';
$paginaAtual  = 'dashboard';
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <header class="topbar">
        <div>
            <span class="eyebrow">NEOMIND</span>
            <h1>Dashboard</h1>
        </div>
        <a href="config.php" class="profile"><?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?></a>
    </header>
    <div class="hero-grid">
        <div class="hero-card">
            <span class="tag">CONTINUE ESTUDANDO</span>
            <h2>Olá, <?= htmlspecialchars($usuario['nome']) ?>!</h2>
            <p>Retome seus estudos de onde parou e mantenha o ritmo.</p>
            <a href="materias.php" class="primary">Ver matérias →</a>
        </div>
        <div class="store-card">
            <div class="store-icon">↗</div>
            <h3>Histórico completo</h3>
            <p>Veja seu desempenho em todas as questões respondidas.</p>
            <a href="historico.php" class="ghost">Ver histórico</a>
        </div>
    </div>
    <div class="stats">
        <div class="stat">
            <strong><?= (int)$qtdHistorico ?></strong>
            <span>Tentativas registradas</span>
        </div>
        <div class="stat">
            <strong><?= (int)$somaTotal ?></strong>
            <span>Questões respondidas</span>
        </div>
        <div class="stat">
            <strong><?= (int)$somaAcertos ?></strong>
            <span>Acertos</span>
        </div>
        <div class="stat">
            <strong><?= $desempenho ?>%</strong>
            <span>Desempenho médio</span>
        </div>
    </div>
    <div class="section-title">
        <span>Seu progresso</span>
        <small>Geral</small>
    </div>
    <div class="progress-card">
        <div>
            <div class="progress-head">
                <b>Aproveitamento</b>
                <span><?= (int)$somaAcertos ?> / <?= (int)$somaTotal ?></span>
            </div>
            <div class="progress">
                <i style="width: <?= $desempenho ?>%;"></i>
            </div>
        </div>
        <div class="mini-info">
            <b><?= $desempenho ?>%</b>
            <span>de aproveitamento</span>
        </div>
    </div>
</main>
</body>
</html>
