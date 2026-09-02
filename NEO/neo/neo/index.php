<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$totalQuestoes = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(acertos),0), COALESCE(SUM(total),0) FROM historico WHERE user_id = ?");
$totalQuestoes->execute([$usuario['id']]);
[$qtdHistorico, $somaAcertos, $somaTotal] = $totalQuestoes->fetch(PDO::FETCH_NUM);
$desempenho = $somaTotal > 0 ? round(($somaAcertos / $somaTotal) * 100) : 0;
$semanaInicio = inicioSemanaNeo()->format('Y-m-d');
$stmtOfensiva = $pdo->prepare("SELECT dias_ativos FROM ofensivas_semanais WHERE user_id = ? AND semana_inicio = ?");
$stmtOfensiva->execute([$usuario['id'], $semanaInicio]);
$diasOfensiva = (int)($stmtOfensiva->fetchColumn() ?: 0);
$stmtSequencia = $pdo->prepare("SELECT semanas_atuais FROM progresso_ofensivas WHERE user_id = ?");
$stmtSequencia->execute([$usuario['id']]);
$sequenciaOfensiva = (int)($stmtSequencia->fetchColumn() ?: 0);
$tituloPagina = 'Início';
$paginaAtual  = 'inicio';
$usaSidebar = true;
$cssPaginas = ['dashboard'];
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <header class="topbar">
        <div class="user-heading">
            <span class="eyebrow">NEOMIND</span>
            <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
            <span class="page-title">Início</span>
        </div>
        <a href="perfil.php" class="profile">
            <?php if (!empty($usuario['foto'])): ?>
                <img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="">
            <?php else: ?>
                <?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?>
            <?php endif; ?>
        </a>
    </header>
    <div class="hero-grid">
        <div class="hero-card">
            <span class="tag">CONTINUE ESTUDANDO</span>
            <h2>Olá, <?= htmlspecialchars($usuario['nome']) ?>!</h2>
            <p>Retome seus estudos de onde parou e mantenha o ritmo.</p>
            <a href="materias.php" class="primary">Ver matérias →</a>
        </div>
        <div class="shop-banner">
            <span class="shop-kicker">LOJA NEO</span>
            <h3>Decorações de perfil</h3>
            <p>Use coças para desbloquear molduras, efeitos e detalhes visuais para o seu perfil.</p>
            <div class="shop-banner-footer">
                <span class="coin"></span>
                <b><?= saldoCossasVisual($usuario) ?> coças</b>
                <a href="loja.php" class="ghost">Abrir loja</a>
            </div>
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
        <small>Geral • Ofensiva: <?= $diasOfensiva ?>/<?= NEO_DIAS_PARA_OFENSIVA_SEMANAL ?> dias • Sequência: <?= $sequenciaOfensiva ?> semana(s)</small>
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
