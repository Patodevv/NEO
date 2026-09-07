<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/materia_icon.php';
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
$stmtAcessos = $pdo->prepare("
    SELECT ua.acessado_em, c.id AS conteudo_id, c.titulo, m.nome AS materia_nome
    FROM ultimos_acessos ua
    JOIN conteudos c ON c.id = ua.conteudo_id AND c.user_id = ua.user_id AND c.removido_em IS NULL
    JOIN materias m ON m.id = ua.materia_id
    WHERE ua.user_id = ?
    ORDER BY ua.acessado_em DESC
    LIMIT 6
");
$stmtAcessos->execute([$usuario['id']]);
$ultimosAcessos = $stmtAcessos->fetchAll(PDO::FETCH_ASSOC);
$stmtLivros = $pdo->prepare("SELECT COUNT(*) FROM ultimos_acessos WHERE user_id = ?");
$stmtLivros->execute([$usuario['id']]);
$livrosAcessados = (int)$stmtLivros->fetchColumn();
$nivelAtual = max(1, (int)($usuario['nivel'] ?? 1));
$xpAtual = max(0, (int)($usuario['xp'] ?? 0));
$xpProximo = xpParaProximoNivel($nivelAtual);
$progressoNivel = $xpProximo > 0 ? min(100, round(($xpAtual / $xpProximo) * 100)) : 0;
$tituloPagina = 'Início';
$paginaAtual  = 'inicio';
$usaSidebar = true;
$cssPaginas = ['dashboard'];
$mostrarDespertarDashboard = !empty($_SESSION['neo_dashboard_awaken']);
if ($mostrarDespertarDashboard) {
    unset($_SESSION['neo_dashboard_awaken']);
}
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main dashboard-main">
    <?php require __DIR__ . '/includes/topbar.php'; ?>
    <section class="dashboard-stack">
        <section class="dash-panel recent-panel">
            <div class="panel-head">
                <span>Últimos acessos</span>
            </div>
            <div class="recent-grid">
                <?php foreach ($ultimosAcessos as $acesso): ?>
                    <a class="recent-item" href="livro.php?conteudo_id=<?= (int)$acesso['conteudo_id'] ?>">
                        <span class="recent-icon">
                            <?= estrelaHoverNeo() ?>
                            <?= iconeMateriaDashboard($acesso['materia_nome']) ?>
                        </span>
                        <b><?= htmlspecialchars($acesso['titulo']) ?></b>
                    </a>
                <?php endforeach; ?>
                <?php for ($i = count($ultimosAcessos); $i < 6; $i++): ?>
                    <span class="recent-item recent-empty">
                        <span class="recent-icon">
                            <?= estrelaHoverNeo() ?>
                        </span>
                        <b>Nenhum acesso</b>
                    </span>
                <?php endfor; ?>
            </div>
        </section>

        <section class="level-row">
            <div class="level-bar neo-progress-track" style="--level-progress: <?= $progressoNivel ?>%;">
                <span class="level-fill neo-progress-fill">
                    <?= cometaProgressoNeo() ?>
                </span>
                <span class="level-badge">Level <?= $nivelAtual ?></span>
            </div>
        </section>

        <section class="dashboard-bottom">
            <a class="dash-panel shop-panel" href="loja.php">
                <span>Loja Neo</span>
                <strong>Decore seu perfil</strong>
                <small>Use coças para liberar bordas, anéis e detalhes visuais.</small>
            </a>
            <section class="dash-panel mini-panel">
                <strong><?= $livrosAcessados ?></strong>
                <span>Livros acessados</span>
            </section>
            <section class="dash-panel mini-panel">
                <strong><?= (int)$somaTotal ?></strong>
                <span>Questões respondidas</span>
            </section>
        </section>
    </section>
</main>
</body>
</html>
