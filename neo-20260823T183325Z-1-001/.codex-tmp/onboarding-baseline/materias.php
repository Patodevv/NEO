<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/materia_icon.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$stmtMaterias = $pdo->prepare("
    SELECT m.id, m.nome, COUNT(c.id) AS total,
           COALESCE(pm.nivel, 1) AS nivel_materia,
           COALESCE(pm.xp_total, 0) AS xp_materia
    FROM materias m
    LEFT JOIN conteudos c ON c.materia_id = m.id AND c.user_id = ? AND c.removido_em IS NULL
    LEFT JOIN progresso_materias pm ON pm.materia_id = m.id AND pm.user_id = ?
    GROUP BY m.id, m.nome, pm.nivel, pm.xp_total
    ORDER BY m.nome
");
$stmtMaterias->execute([$usuario['id'], $usuario['id']]);
$materias = $stmtMaterias->fetchAll(PDO::FETCH_ASSOC);
$tituloPagina = 'Matérias';
$paginaAtual  = 'materias';
$usaSidebar = true;
$cssPaginas = ['materias'];
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php require __DIR__ . '/includes/topbar.php'; ?>
    <section class="subjects-folder">
        <div class="subject-grid">
            <?php foreach ($materias as $m): ?>
                <?php if ((int)$m['total'] === 0): ?>
                    <form method="post" action="conteudos.php" class="subject-form" data-ai-loading data-ai-message="Criando seus primeiros livros">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="materia_id" value="<?= (int)$m['id'] ?>">
                        <input type="hidden" name="acao" value="gerar_mais">
                        <button type="submit" class="subject">
                <?php else: ?>
                    <a class="subject" href="conteudos.php?materia_id=<?= (int)$m['id'] ?>">
                <?php endif; ?>
                    <span class="subject-icon">
                        <?= estrelaHoverNeo() ?>
                        <?= iconeMateriaDashboard($m['nome']) ?>
                    </span>
                    <b><?= htmlspecialchars($m['nome']) ?></b>
                    <small>Nível <?= (int)$m['nivel_materia'] ?> · <?= (int)$m['xp_materia'] ?> EXP</small>
                <?php if ((int)$m['total'] === 0): ?>
                        </button>
                    </form>
                <?php else: ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <a class="neo-icon-button neo-star-hover external-studies-link" href="estudos.php">
        <?= estrelaHoverNeo() ?>
        <?= iconeMateriaDashboard('portugues') ?>
        <span>Estudos externos</span>
    </a>
</main>
</body>
</html>
