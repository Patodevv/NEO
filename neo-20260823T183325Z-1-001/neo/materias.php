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
    LEFT JOIN conteudos c ON c.materia_id = m.id AND c.user_id = ?
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
                <a
                    class="subject"
                    href="conteudos.php?materia_id=<?= (int)$m['id'] ?>"
                    <?= (int)$m['total'] === 0 ? 'data-ai-loading data-ai-message="Criando seus primeiros livros"' : '' ?>
                >
                    <span class="subject-icon">
                        <?= estrelaHoverNeo() ?>
                        <?= iconeMateriaDashboard($m['nome']) ?>
                    </span>
                    <b><?= htmlspecialchars($m['nome']) ?></b>
                    <small>Nível <?= (int)$m['nivel_materia'] ?> · <?= (int)$m['xp_materia'] ?> EXP</small>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
</main>
</body>
</html>
