<?php

require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/services/ai.php';

exigirLogin();
$usuario = usuarioAtual($pdo);
$conteudoId = (int)($_GET['conteudo_id'] ?? ($_POST['conteudo_id'] ?? 0));
$stmt = $pdo->prepare("
    SELECT c.*, m.nome AS materia_nome
    FROM conteudos c JOIN materias m ON m.id = c.materia_id
    WHERE c.id = ? AND c.user_id = ?
");
$stmt->execute([$conteudoId, $usuario['id']]);
$conteudo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$conteudo) {
    header('Location: materias.php');
    exit;
}

$conteudo['dificuldade_adaptativa'] = dificuldadeAdaptativa($pdo, (int)$usuario['id'], (int)$conteudo['materia_id']);
$erroIA = '';
$erro = '';
$mensagem = '';
$questoesAtualizadas = false;
$feedbacksIA = [];
$resultado = null;

function salvarQuestoesGeradas(PDO $pdo, int $userId, int $conteudoId, array $geradas, int $dificuldade): int
{
    $stmt = $pdo->prepare("
        INSERT INTO questoes
            (user_id, conteudo_id, enunciado, opcao_a, opcao_b, opcao_c, opcao_d, correta,
             dificuldade, explicacao_correta, feedback_a, feedback_b, feedback_c, feedback_d, dica_1, dica_2, dica_3)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $salvas = 0;

    foreach ($geradas as $questao) {
        $correta = strtoupper(trim((string)($questao['correta'] ?? '')));
        $campos = ['enunciado', 'opcao_a', 'opcao_b', 'opcao_c', 'opcao_d'];
        $valores = [];
        foreach ($campos as $campo) {
            $valores[$campo] = trim((string)($questao[$campo] ?? ''));
        }
        if (!in_array($correta, ['A', 'B', 'C', 'D'], true) || in_array('', $valores, true)) {
            continue;
        }

        $stmt->execute([
            $userId, $conteudoId, $valores['enunciado'], $valores['opcao_a'], $valores['opcao_b'],
            $valores['opcao_c'], $valores['opcao_d'], $correta,
            max(1, min(12, (int)($questao['dificuldade'] ?? $dificuldade))),
            limitarPalavrasIA((string)($questao['explicacao_correta'] ?? ''), 120) ?: null,
            limitarPalavrasIA((string)($questao['feedback_a'] ?? ''), 70) ?: null,
            limitarPalavrasIA((string)($questao['feedback_b'] ?? ''), 70) ?: null,
            limitarPalavrasIA((string)($questao['feedback_c'] ?? ''), 70) ?: null,
            limitarPalavrasIA((string)($questao['feedback_d'] ?? ''), 70) ?: null,
            limitarPalavrasIA((string)($questao['dica_1'] ?? ''), 70) ?: null,
            limitarPalavrasIA((string)($questao['dica_2'] ?? ''), 70) ?: null,
            limitarPalavrasIA((string)($questao['dica_3'] ?? ''), 70) ?: null,
        ]);
        $salvas++;
    }

    if ($salvas < 5) {
        throw new RuntimeException('A validacao reteve questoes demais. Gere uma nova atividade.');
    }
    return $salvas;
}

function carregarQuestoes(PDO $pdo, int $conteudoId, int $userId): array
{
    $stmt = $pdo->prepare("SELECT * FROM questoes WHERE conteudo_id = ? AND user_id = ? ORDER BY id");
    $stmt->execute([$conteudoId, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function gerarESalvarQuestoes(PDO $pdo, array $conteudo, array $usuario): void
{
    $nivel = (int)$conteudo['dificuldade_adaptativa'];
    $geradas = gerarQuestoes(
        $conteudo['materia_nome'], $conteudo['titulo'], $conteudo['corpo'] ?? '',
        trim($usuario['gostos'] ?? ''), $nivel
    );
    if (!$geradas) {
        throw new RuntimeException('A IA nao retornou questoes validas.');
    }
    registrarAuditoriaIA($pdo, (int)$usuario['id'], 'questoes', $conteudo['materia_nome'] . ':' . $conteudo['titulo'] . ':' . $nivel);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM questoes WHERE conteudo_id = ? AND user_id = ?")
            ->execute([(int)$conteudo['id'], (int)$usuario['id']]);
        salvarQuestoesGeradas($pdo, (int)$usuario['id'], (int)$conteudo['id'], $geradas, $nivel);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function feedbackPersistido(array $questao, string $resposta): string
{
    $resposta = strtolower($resposta);
    $correta = strtolower((string)$questao['correta']);
    $explicacao = trim((string)($questao['explicacao_correta'] ?? ''));
    $feedback = trim((string)($questao['feedback_' . $resposta] ?? ''));
    if ($resposta === $correta) {
        return $explicacao ?: $feedback;
    }
    if ($feedback === '' || $explicacao === '') {
        return '';
    }
    return $feedback . "\n\nRaciocínio correto: " . $explicacao;
}

$questoes = carregarQuestoes($pdo, $conteudoId, (int)$usuario['id']);
$acao = (string)($_POST['acao'] ?? '');
if (!empty($_POST['facilitador_questao_id'])) {
    $acao = 'facilitador';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'novas_questoes') {
    try {
        gerarESalvarQuestoes($pdo, $conteudo, $usuario);
        $questoes = carregarQuestoes($pdo, $conteudoId, (int)$usuario['id']);
        $questoesAtualizadas = true;
    } catch (Throwable $e) {
        $erroIA = textoErroIa($e instanceof Exception ? $e : new Exception($e->getMessage()));
    }
}

if (!$questoes) {
    try {
        gerarESalvarQuestoes($pdo, $conteudo, $usuario);
        $questoes = carregarQuestoes($pdo, $conteudoId, (int)$usuario['id']);
    } catch (Throwable $e) {
        $erroIA = textoErroIa($e instanceof Exception ? $e : new Exception($e->getMessage()));
    }
}

$ajudasPorQuestao = [];
$stmt = $pdo->prepare("
    SELECT aq.* FROM ajudas_questoes aq
    JOIN questoes q ON q.id = aq.questao_id
    WHERE aq.user_id = ? AND q.conteudo_id = ? AND q.user_id = ?
    ORDER BY aq.questao_id, aq.nivel
");
$stmt->execute([$usuario['id'], $conteudoId, $usuario['id']]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ajuda) {
    $ajudasPorQuestao[(int)$ajuda['questao_id']][] = $ajuda;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'facilitador' && $questoes) {
    $questaoId = (int)($_POST['facilitador_questao_id'] ?? 0);
    $questao = null;
    foreach ($questoes as $candidata) {
        if ((int)$candidata['id'] === $questaoId) {
            $questao = $candidata;
            break;
        }
    }

    try {
        if (!$questao) {
            throw new DomainException('Questao invalida para este conteúdo.');
        }
        $anteriores = $ajudasPorQuestao[$questaoId] ?? [];
        $nivelAjuda = count($anteriores) + 1;
        if ($nivelAjuda > 3) {
            throw new DomainException('Todas as ajudas progressivas desta questão já foram utilizadas.');
        }
        $dica = gerarDicaQuestao(
            $conteudo['materia_nome'], $conteudo['titulo'], $questao, $nivelAjuda,
            array_column($anteriores, 'dica'), (int)$conteudo['dificuldade_adaptativa']
        );
        registrarAuditoriaIA($pdo, (int)$usuario['id'], 'facilitador', $questaoId . ':' . $nivelAjuda);
        $ajuda = registrarAjudaQuestao($pdo, (int)$usuario['id'], $questaoId, $nivelAjuda, $dica);
        $ajudasPorQuestao[$questaoId][] = $ajuda;
        $usuario = usuarioAtual($pdo);
        $mensagem = $ajuda['custo_cossas'] > 0
            ? 'Ajuda de nível ' . $nivelAjuda . ' liberada por ' . (int)$ajuda['custo_cossas'] . ' coças.'
            : 'Primeira ajuda liberada gratuitamente.';
    } catch (DomainException $e) {
        $erro = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'responder' && $questoes) {
    $respostas = [];
    $pendentesIA = [];
    try {
        foreach ($questoes as $questao) {
            $id = (int)$questao['id'];
            $resposta = strtoupper(trim((string)($_POST['resposta_' . $id] ?? '')));
            if (!in_array($resposta, ['A', 'B', 'C', 'D'], true)) {
                throw new DomainException('Responda todas as questões antes de enviar.');
            }
            $respostas[$id] = $resposta;
            $feedbacksIA[$id] = feedbackPersistido($questao, $resposta);
            if ($feedbacksIA[$id] === '') {
                $pendentesIA[] = [
                    'questao_id' => $id, 'enunciado' => $questao['enunciado'],
                    'opcao_a' => $questao['opcao_a'], 'opcao_b' => $questao['opcao_b'],
                    'opcao_c' => $questao['opcao_c'], 'opcao_d' => $questao['opcao_d'],
                    'resposta_usuario' => $resposta, 'resposta_correta' => $questao['correta'],
                    'resultado' => $resposta === $questao['correta'] ? 'acerto' : 'erro',
                ];
            }
        }

        if ($pendentesIA) {
            $gerados = gerarFeedbackQuestoes(
                $conteudo['materia_nome'], $conteudo['titulo'], $pendentesIA,
                trim($usuario['gostos'] ?? ''), (int)$conteudo['dificuldade_adaptativa']
            );
            foreach ($gerados as $id => $feedback) {
                $feedbacksIA[(int)$id] = $feedback;
            }
        }

        foreach ($questoes as $questao) {
            $id = (int)$questao['id'];
            if (empty($feedbacksIA[$id])) {
                $correta = strtoupper((string)$questao['correta']);
                $feedbacksIA[$id] = 'Compare a alternativa escolhida com o conceito central do enunciado. A opção correta é ' . $correta . ' porque corresponde ao que foi explicado no material.';
            }
        }

        $resultado = registrarResultadoAtividade($pdo, (int)$usuario['id'], $conteudo, $questoes, $respostas, $feedbacksIA);
        $usuario = usuarioAtual($pdo);
    } catch (DomainException $e) {
        $erro = $e->getMessage();
    } catch (Throwable $e) {
        error_log('[NEO][questoes] ' . $e->getMessage());
        $erro = 'Não foi possível registrar a atividade agora. Nenhuma recompensa foi alterada.';
    }
}

$tituloPagina = 'Questões';
$paginaAtual = 'materias';
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
            <?php if (!empty($usuario['foto'])): ?><img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="">
            <?php else: ?><?= htmlspecialchars(strtoupper(substr($usuario['nome'], 0, 1))) ?><?php endif; ?>
        </a>
    </header>

    <div class="back-row"><a href="livro.php?conteudo_id=<?= (int)$conteudo['id'] ?>" class="back">← Voltar para conteúdo</a></div>
    <?php if ($questoesAtualizadas): ?><div class="msg-ok">✓ Novas questões validadas e alinhadas ao seu nível foram geradas.</div><?php endif; ?>
    <?php if ($mensagem): ?><div class="msg-ok"><?= htmlspecialchars($mensagem) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <?php if (!$questoes): ?>
        <div class="question-card">
            <?php if ($erroIA): ?><div class="error">Não foi possível gerar as questões agora: <?= htmlspecialchars($erroIA) ?></div><?php endif; ?>
            <p class="empty">Ainda não há questões disponíveis para este conteúdo.</p>
        </div>
    <?php else: ?>
        <?php if ($resultado): ?>
            <div class="msg-ok">
                ✓ Você acertou <?= (int)$resultado['acertos'] ?> de <?= (int)$resultado['total'] ?> questão(ões). Resultado salvo no histórico.
                <?php if ($resultado['recompensado']): ?>
                    +<?= (int)$resultado['xp'] ?> EXP e +<?= (int)$resultado['cossas'] ?> coças. Matéria no nível <?= (int)$resultado['nivel_materia'] ?>.
                <?php else: ?>
                    <?= ($resultado['motivo_sem_recompensa'] ?? '') === 'limite_diario'
                        ? 'O limite diário de recompensas deste conteúdo foi atingido; o resultado foi salvo sem gerar EXP ou coças.'
                        : 'Esta mesma lista já havia concedido recompensa; a nova tentativa não gerou EXP nem coças.' ?>
                <?php endif; ?>
                <?php if (!empty($resultado['ofensiva']['recompensa'])): ?>
                    Ofensiva semanal <?= (int)$resultado['ofensiva']['sequencia'] ?> concluída: +<?= (int)$resultado['ofensiva']['recompensa'] ?> coças.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <?= campoCsrf() ?>
            <input type="hidden" name="conteudo_id" value="<?= (int)$conteudo['id'] ?>">
            <input type="hidden" name="acao" value="responder">

            <?php foreach ($questoes as $i => $q): ?>
                <?php
                    $respostaUsuario = strtoupper(trim((string)($_POST['resposta_' . $q['id']] ?? '')));
                    $acertou = $resultado && $respostaUsuario === $q['correta'];
                    $ajudas = $ajudasPorQuestao[(int)$q['id']] ?? [];
                ?>
                <div class="question-card" style="margin-bottom: 18px;">
                    <span class="tag">QUESTÃO <?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?> • <?= htmlspecialchars(strtoupper($conteudo['materia_nome'])) ?></span>
                    <h2><?= htmlspecialchars($q['enunciado']) ?></h2>
                    <div class="options">
                        <?php foreach (['A', 'B', 'C', 'D'] as $letra): ?>
                            <?php
                                $campo = 'opcao_' . strtolower($letra);
                                $estaMarcada = $resultado && $respostaUsuario === $letra;
                                $classe = $resultado ? ($letra === $q['correta'] ? 'certa' : ($estaMarcada ? 'errada' : '')) : '';
                            ?>
                            <label class="opcao-label <?= $classe ?>">
                                <input type="radio" name="resposta_<?= (int)$q['id'] ?>" value="<?= $letra ?>" <?= $estaMarcada ? 'checked' : '' ?> <?= $resultado ? 'disabled' : 'required' ?>>
                                <?= $letra ?>) <?= htmlspecialchars($q[$campo]) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ($ajudas as $ajuda): ?>
                        <div class="feedback"><strong>Ajuda <?= (int)$ajuda['nivel'] ?>:</strong> <?= htmlspecialchars($ajuda['dica']) ?></div>
                    <?php endforeach; ?>

                    <?php if (!$resultado): ?>
                        <?php $proximoNivel = count($ajudas) + 1; ?>
                        <div class="feedback">Escolha uma alternativa.</div>
                        <?php if ($proximoNivel <= 3): ?>
                            <button type="submit" name="facilitador_questao_id" value="<?= (int)$q['id'] ?>" class="ghost" formnovalidate>
                                Facilitador — <?= $proximoNivel === 1 ? 'grátis' : ($proximoNivel === 2 ? '25 coças' : '40 coças') ?>
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="feedback feedback-ia">
                            <strong><?= $acertou ? '✓ Você acertou!' : '✗ Sua resposta está incorreta.' ?></strong>
                            <p><?= nl2br(htmlspecialchars($feedbacksIA[$q['id']] ?? 'Revise o conceito apresentado no material.')) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if (!$resultado): ?><button type="submit" class="primary">Enviar respostas →</button><?php endif; ?>
        </form>

        <?php if ($resultado): ?>
            <div class="action-row">
                <a href="questoes.php?conteudo_id=<?= (int)$conteudo['id'] ?>" class="primary">Tentar novamente →</a>
                <form method="post">
                    <?= campoCsrf() ?>
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
