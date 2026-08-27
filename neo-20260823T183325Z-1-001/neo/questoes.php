<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/services/ai.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$erroIA = '';
$questoesAtualizadas = false;
$conteudoId = (int)($_GET['conteudo_id'] ?? ($_POST['conteudo_id'] ?? 0));
$acao = $_POST['acao'] ?? '';
$stmt = $pdo->prepare("
    SELECT c.*, m.nome AS materia_nome
    FROM conteudos c
    JOIN materias m ON m.id = c.materia_id
    WHERE c.id = ? AND c.user_id = ?
");
$stmt->execute([$conteudoId, $usuario['id']]);
$conteudo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$conteudo) {
    header('Location: materias.php');
    exit;
}
function salvarQuestoesGeradas(PDO $pdo, int $userId, int $conteudoId, array $geradas): void
{
    $stmtInsert = $pdo->prepare("
        INSERT INTO questoes
        (user_id, conteudo_id, enunciado, opcao_a, opcao_b, opcao_c, opcao_d, correta)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($geradas as $questaoGerada) {
        $correta = strtoupper(trim($questaoGerada['correta'] ?? ''));
        if (!in_array($correta, ['A', 'B', 'C', 'D'], true)) {
            continue;
        }
        $enunciado = trim($questaoGerada['enunciado'] ?? '');
        $opcaoA = trim($questaoGerada['opcao_a'] ?? '');
        $opcaoB = trim($questaoGerada['opcao_b'] ?? '');
        $opcaoC = trim($questaoGerada['opcao_c'] ?? '');
        $opcaoD = trim($questaoGerada['opcao_d'] ?? '');
        if ($enunciado === '' || $opcaoA === '' || $opcaoB === '' || $opcaoC === '' || $opcaoD === '') {
            continue;
        }
        $stmtInsert->execute([$userId, $conteudoId, $enunciado, $opcaoA, $opcaoB, $opcaoC, $opcaoD, $correta]);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'novas_questoes') {
    try {
        $geradas = gerarQuestoes(
            $conteudo['materia_nome'],
            $conteudo['titulo'],
            $conteudo['corpo'] ?? '',
            trim($usuario['gostos'] ?? ''),
            (int)($usuario['nivel'] ?? 1)
        );
        if (!$geradas) {
            throw new Exception('A IA nao retornou questoes validas.');
        }
        $stmtDelete = $pdo->prepare("DELETE FROM questoes WHERE conteudo_id = ? AND user_id = ?");
        $stmtDelete->execute([$conteudoId, $usuario['id']]);
        salvarQuestoesGeradas($pdo, (int)$usuario['id'], $conteudoId, $geradas);
        $questoesAtualizadas = true;
    } catch (Exception $e) {
        $erroIA = $e->getMessage();
    }
}
$stmt = $pdo->prepare("SELECT * FROM questoes WHERE conteudo_id = ? AND user_id = ? ORDER BY id");
$stmt->execute([$conteudoId, $usuario['id']]);
$questoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$questoes) {
    try {
        $geradas = gerarQuestoes(
            $conteudo['materia_nome'],
            $conteudo['titulo'],
            $conteudo['corpo'] ?? '',
            trim($usuario['gostos'] ?? ''),
            (int)($usuario['nivel'] ?? 1)
        );

        salvarQuestoesGeradas($pdo, (int)$usuario['id'], $conteudoId, $geradas);

        $stmt->execute([$conteudoId, $usuario['id']]);
        $questoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $erroIA = $e->getMessage();
    }
}
$resultado = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'responder' && $questoes) {
    $acertos = 0;
    foreach ($questoes as $q) {
        $respostaUsuario = $_POST['resposta_' . $q['id']] ?? '';
        if ($respostaUsuario === $q['correta']) {
            $acertos++;
        }
    }
    $total = count($questoes);
    $stmt = $pdo->prepare("INSERT INTO historico (user_id, conteudo_id, acertos, total) VALUES (?, ?, ?, ?)");
    $stmt->execute([$usuario['id'], $conteudoId, $acertos, $total]);
    $recompensa = recompensarUsuario($pdo, (int)$usuario['id'], $acertos, $total);
    $usuario = usuarioAtual($pdo);
    $resultado = ['acertos' => $acertos, 'total' => $total, 'recompensa' => $recompensa];
}
$tituloPagina = 'Questões';
$paginaAtual  = 'materias';
$usaSidebar = true;
$cssPaginas = ['questoes'];
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <header class="topbar">
        <div class="user-heading">
            <span class="eyebrow">NEOMIND • <?= htmlspecialchars(strtoupper($conteudo['materia_nome'])) ?></span>
            <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
            <span class="page-title">Questões</span>
        </div>
        <a href="perfil.php" class="profile">
            <?php if (!empty($usuario['foto'])): ?>
                <img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="">
            <?php else: ?>
                <?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?>
            <?php endif; ?>
        </a>
    </header>
    <div class="back-row">
        <a href="livro.php?conteudo_id=<?= (int)$conteudo['id'] ?>" class="back">← Voltar para conteúdo</a>
    </div>
    <?php if (!$questoes): ?>
        <div class="question-card">
            <?php if ($erroIA): ?>
                <div class="error">Nao foi possivel gerar as questoes agora: <?= htmlspecialchars($erroIA) ?></div>
            <?php endif; ?>
            <p class="empty">Ainda nao ha questoes disponiveis para este conteudo.</p>
        </div>
    <?php else: ?>
        <?php if ($questoesAtualizadas): ?>
            <div class="msg-ok">
                Novas questoes geradas. Responde isso ai.
            </div>
        <?php endif; ?>
        <?php if ($resultado): ?>
            <div class="msg-ok">
                ✓ Você acertou <?= $resultado['acertos'] ?> de <?= $resultado['total'] ?> questão(ões). Resultado salvo no histórico.
                +<?= (int)$resultado['recompensa']['xp'] ?> XP e +<?= (int)$resultado['recompensa']['cossas'] ?> coças.
                <?php if (!empty($resultado['recompensa']['subiu_nivel'])): ?>
                    Level <?= (int)$resultado['recompensa']['nivel'] ?> desbloqueado.
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="conteudo_id" value="<?= (int)$conteudo['id'] ?>">
            <input type="hidden" name="acao" value="responder">
            <?php foreach ($questoes as $i => $q): ?>
                <div class="question-card" style="margin-bottom: 18px;">
                    <span class="tag">QUESTÃO <?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?> • <?= htmlspecialchars(strtoupper($conteudo['materia_nome'])) ?></span>

                    <h2><?= htmlspecialchars($q['enunciado']) ?></h2>
                    <div class="options">
                        <?php foreach (['A', 'B', 'C', 'D'] as $letra): ?>
                            <?php
                                $campo = 'opcao_' . strtolower($letra);
                                $marcada = ($resultado && ($_POST['resposta_' . $q['id']] ?? '') === $letra);
                                $classe = '';
                                if ($resultado) {
                                    if ($letra === $q['correta']) {
                                        $classe = 'certa';
                                    } elseif ($marcada) {
                                        $classe = 'errada';
                                    }
                                }
                            ?>
                            <label class="opcao-label <?= $classe ?>">
                                <input
                                    type="radio"
                                    name="resposta_<?= (int)$q['id'] ?>"
                                    value="<?= $letra ?>"
                                    <?= $marcada ? 'checked' : '' ?>
                                    <?= $resultado ? 'disabled' : 'required' ?>
                                >
                                <?= $letra ?>) <?= htmlspecialchars($q[$campo]) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!$resultado): ?>
                        <div class="feedback">Escolha uma alternativa.</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$resultado): ?>
                <button type="submit" class="primary">Enviar respostas →</button>
            <?php endif; ?>
        </form>
        <?php if ($resultado): ?>
            <div class="action-row">
                <a href="questoes.php?conteudo_id=<?= (int)$conteudo['id'] ?>" class="primary">Tentar novamente →</a>
                <form method="post">
                    <input type="hidden" name="conteudo_id" value="<?= (int)$conteudo['id'] ?>">
                    <input type="hidden" name="acao" value="novas_questoes">
                    <button type="submit" class="ghost">Gerar novas questões</button>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
