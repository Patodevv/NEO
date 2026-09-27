<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/materia_icon.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$stmt = $pdo->prepare("
    SELECT h.*, c.titulo AS conteudo_titulo, c.removido_em AS conteudo_removido_em, m.nome AS materia_nome
    FROM historico h
    JOIN conteudos c ON c.id = h.conteudo_id
    JOIN materias m ON m.id = c.materia_id
    WHERE h.user_id = ? AND c.user_id = ?
    ORDER BY h.data DESC
");
$stmt->execute([$usuario['id'], $usuario['id']]);
$historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalTentativas = count($historico);
$totalAcertos = array_sum(array_map(fn($h) => (int)$h['acertos'], $historico));
$totalQuestoes = array_sum(array_map(fn($h) => (int)$h['total'], $historico));
$mediaGeral = $totalQuestoes > 0 ? round(($totalAcertos / $totalQuestoes) * 100) : 0;
$materiasEstudadas = count(array_unique(array_map(fn($h) => $h['materia_nome'], $historico)));
$tituloPagina = 'Histórico';
$paginaAtual  = 'historico';
$usaSidebar = true;
$cssPaginas = ['historico'];
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php require __DIR__ . '/includes/topbar.php'; ?>

    <div class="neo-page-shell history-page">
        <section class="neo-page-heading neo-panel">
            <div class="neo-page-heading-copy">
                <span class="neo-page-kicker">Atividades</span>
                <h1>Histórico de estudos</h1>
            </div>
            <div class="neo-summary-group" aria-label="Resumo do histórico">
                <span class="neo-summary-pill"><b><?= $totalTentativas ?></b> tentativas</span>
                <span class="neo-summary-pill"><b><?= $mediaGeral ?>%</b> média</span>
                <span class="neo-summary-pill"><b><?= $materiasEstudadas ?></b> matérias</span>
            </div>
        </section>

        <section class="history-list" aria-label="Atividades recentes">
            <?php if (!$historico): ?>
                <div class="neo-empty-state neo-panel">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="8.5"></circle><path d="M12 7.5V12l3.2 2"></path>
                    </svg>
                    <b>Nenhuma atividade registrada</b>
                    <a href="materias.php" class="primary neo-star-hover" data-manel-tip="Abre suas matérias para começar uma atividade."><?= estrelaHoverNeo() ?><span>Começar a estudar</span></a>
                </div>
            <?php endif; ?>

            <?php foreach ($historico as $h): ?>
                <?php
                $pct = $h['total'] > 0 ? round(($h['acertos'] / $h['total']) * 100) : 0;
                $dataHistorico = strtotime($h['data']);
                ?>
                <article class="history-row <?= classeTemaMateria((string)$h['materia_nome']) ?>">
                    <span class="history-activity-icon <?= classeTemaMateria((string)$h['materia_nome']) ?>" aria-hidden="true">
                        <?= iconeMateriaDashboard((string)$h['materia_nome']) ?>
                    </span>

                    <div class="history-row-copy">
                        <b><?= htmlspecialchars($h['conteudo_titulo']) ?></b>
                        <div class="history-row-meta">
                            <span>Questões</span>
                            <span><?= htmlspecialchars($h['materia_nome']) ?></span>
                            <time datetime="<?= htmlspecialchars(date('c', $dataHistorico)) ?>"><?= date('d/m/Y \à\s H:i', $dataHistorico) ?></time>
                        </div>
                        <?php if (!empty($h['recompensado'])): ?>
                            <small class="history-reward">+<?= (int)$h['exp_ganho'] ?> EXP · +<?= (int)$h['cossas_ganhas'] ?> coças</small>
                        <?php elseif (!empty($h['conjunto_hash'])): ?>
                            <small class="history-reward is-muted">Tentativa registrada sem nova recompensa</small>
                        <?php endif; ?>
                    </div>

                    <span class="history-score">
                        <b><?= $pct ?>%</b>
                        <small><?= (int)$h['acertos'] ?> de <?= (int)$h['total'] ?></small>
                    </span>

                    <?php if (empty($h['conteudo_removido_em'])): ?>
                        <a href="livro.php?conteudo_id=<?= (int)$h['conteudo_id'] ?>" class="neo-icon-button neo-star-hover" aria-label="Rever <?= htmlspecialchars($h['conteudo_titulo']) ?>" data-manel-tip="Abre novamente o livro <?= htmlspecialchars($h['conteudo_titulo'], ENT_QUOTES, 'UTF-8') ?>.">
                            <?= estrelaHoverNeo() ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M5 12h14"></path><path d="m14 7 5 5-5 5"></path>
                            </svg>
                        </a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    </div>
</main>
</body>
</html>
