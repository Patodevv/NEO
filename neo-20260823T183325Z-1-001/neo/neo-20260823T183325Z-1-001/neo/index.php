<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/materia_icon.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$aprendizado = !empty($usuario['personalizacao_json']) ? adaptiveSummary($pdo, (int)$usuario['id']) : null;
$proximaSessao = $aprendizado['routine']['sessions'][0] ?? null;
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
$nivelAtual = max(1, (int)($usuario['nivel'] ?? 1));
$xpAtual = max(0, (int)($usuario['xp'] ?? 0));
$xpProximo = xpParaProximoNivel($nivelAtual);
$progressoNivel = $xpProximo > 0 ? min(100, round(($xpAtual / $xpProximo) * 100)) : 0;
$tituloPagina = 'Início';
$paginaAtual  = 'inicio';
$usaSidebar = true;
$cssPaginas = ['dashboard'];
$bodyClasses = ['neo-dashboard-page'];
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
        <section class="dashboard-hero-grid">
        <?php if ($aprendizado): ?>
            <section class="dash-panel personal-study-panel" aria-labelledby="routinePanelTitle">
                <div class="personal-study-heading">
                    <span class="panel-label" id="routinePanelTitle">Rotina de estudos</span>
                    <a class="routine-link-button neo-star-hover" href="aprendizado.php#rotina">
                        <?= estrelaHoverNeo() ?>
                        <span>Ver rotina completa</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"></path></svg>
                    </a>
                </div>
                <?php if ($proximaSessao): ?>
                <a class="personal-study-next neo-star-hover <?= classeTemaMateria($proximaSessao['subject']) ?>" href="livro.php?conteudo_id=<?= (int)$proximaSessao['content_id'] ?>">
                    <?= estrelaHoverNeo() ?>
                    <span class="personal-study-icon <?= classeTemaMateria($proximaSessao['subject']) ?>"><?= iconeMateriaDashboard($proximaSessao['subject']) ?></span>
                    <strong>Próxima atividade: <?= htmlspecialchars($proximaSessao['subject']) ?></strong>
                </a>
                <?php else: ?><div class="personal-study-complete"><strong>Meta semanal concluída</strong><span><?= (int)$aprendizado['routine']['days_done'] ?> dias de estudo registrados</span></div><?php endif; ?>
            </section>
        <?php endif; ?>
            <a class="dash-panel shop-panel" href="loja.php">
                <span>Loja Neo</span>
                <strong>Decore seu perfil</strong>
                <small>Use moedas para liberar bordas, anéis e detalhes visuais.</small>
            </a>
        </section>

        <section class="level-row">
            <div class="level-bar neo-progress-track" style="--level-progress: <?= $progressoNivel ?>%;">
                <span class="level-fill neo-progress-fill">
                    <?= cometaProgressoNeo() ?>
                </span>
                <span class="level-badge">Level <?= $nivelAtual ?></span>
            </div>
        </section>

        <section class="dash-panel recent-panel">
            <div class="panel-head">
                <span>Últimos acessos</span>
            </div>
            <div class="recent-grid">
                <?php foreach ($ultimosAcessos as $acesso): ?>
                    <a class="recent-item" href="livro.php?conteudo_id=<?= (int)$acesso['conteudo_id'] ?>">
                        <span class="recent-icon <?= classeTemaMateria($acesso['materia_nome']) ?>">
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
    </section>
</main>
</body>
</html>
