<?php

require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/content_lock.php';
require __DIR__ . '/includes/materia_icon.php';
require __DIR__ . '/services/ai.php';

exigirLogin();
$usuario = usuarioAtual($pdo);
$conteudoId = (int)($_GET['conteudo_id'] ?? ($_POST['conteudo_id'] ?? 0));
$stmt = $pdo->prepare("
    SELECT c.*, m.nome AS materia_nome
    FROM conteudos c JOIN materias m ON m.id = c.materia_id
    WHERE c.id = ? AND c.user_id = ? AND c.removido_em IS NULL
");
$stmt->execute([$conteudoId, $usuario['id']]);
$conteudo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$conteudo) {
    header('Location: materias.php');
    exit;
}

$conteudo['dificuldade_adaptativa'] = adaptiveDifficulty($pdo, (int)$usuario['id'], $conteudoId);
$erroIA = '';
$erro = '';
$questoesAtualizadas = false;
$feedbacksIA = [];
$resultado = null;

function salvarQuestoesGeradas(PDO $pdo, int $userId, int $conteudoId, array $geradas, int $dificuldade): int
{
    if (count($geradas) !== 5 || problemasQuestoesLocal($geradas)) {
        throw new RuntimeException('Erro no sistema, tente novamente mais tarde.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO questoes
            (user_id, conteudo_id, enunciado, opcao_a, opcao_b, opcao_c, opcao_d, correta,
             ai_provider, ai_model, dificuldade, explicacao_correta, feedback_a, feedback_b, feedback_c, feedback_d, dica_1, dica_2, dica_3, habilidade, tipo_questao, estilo_prova)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $salvas = 0;

    foreach ($geradas as $questao) {
        $correta = strtoupper(trim((string)($questao['correta'] ?? '')));
        $provider = trim((string)($questao['_ai_provider'] ?? ''));
        $model = trim((string)($questao['_ai_model'] ?? ''));
        if ($provider === '' || $model === '' || strcasecmp($provider, 'Local') === 0 || strcasecmp($model, 'fallback') === 0) {
            throw new RuntimeException('Erro no sistema, tente novamente mais tarde.');
        }

        $campos = ['enunciado', 'opcao_a', 'opcao_b', 'opcao_c', 'opcao_d'];
        $valores = [];
        foreach ($campos as $campo) {
            $valores[$campo] = limparMarcacaoIA((string)($questao[$campo] ?? ''));
        }
        if (!in_array($correta, ['A', 'B', 'C', 'D'], true) || in_array('', $valores, true)) {
            continue;
        }

        $stmt->execute([
            $userId, $conteudoId, $valores['enunciado'], $valores['opcao_a'], $valores['opcao_b'],
            $valores['opcao_c'], $valores['opcao_d'], $correta,
            $provider,
            $model,
            max(1, min(12, (int)($questao['dificuldade'] ?? $dificuldade))),
            limitarPalavrasIA(limparMarcacaoIA((string)($questao['explicacao_correta'] ?? '')), 120) ?: null,
            limitarPalavrasIA(limparMarcacaoIA((string)($questao['feedback_a'] ?? '')), 70) ?: null,
            limitarPalavrasIA(limparMarcacaoIA((string)($questao['feedback_b'] ?? '')), 70) ?: null,
            limitarPalavrasIA(limparMarcacaoIA((string)($questao['feedback_c'] ?? '')), 70) ?: null,
            limitarPalavrasIA(limparMarcacaoIA((string)($questao['feedback_d'] ?? '')), 70) ?: null,
            limitarPalavrasIA(limparMarcacaoIA((string)($questao['dica_1'] ?? '')), 70) ?: null,
            limitarPalavrasIA(limparMarcacaoIA((string)($questao['dica_2'] ?? '')), 70) ?: null,
            limitarPalavrasIA(limparMarcacaoIA((string)($questao['dica_3'] ?? '')), 70) ?: null,
            mb_substr(limparMarcacaoIA((string)($questao['habilidade'] ?? '')), 0, 180) ?: null,
            in_array($questao['tipo_questao'] ?? '', ['conceito','interpretacao','calculo','grafico','aplicacao','multipla_escolha'], true) ? $questao['tipo_questao'] : 'multipla_escolha',
            in_array($questao['estilo_prova'] ?? '', ['geral','enem','vestibular','concurso'], true) ? $questao['estilo_prova'] : 'geral',
        ]);
        $salvas++;
    }

    if ($salvas !== 5) {
        throw new RuntimeException('Erro no sistema, tente novamente mais tarde.');
    }
    return $salvas;
}

function carregarQuestoes(PDO $pdo, int $conteudoId, int $userId): array
{
    $stmt = $pdo->prepare("SELECT * FROM questoes WHERE conteudo_id = ? AND user_id = ? ORDER BY id");
    $stmt->execute([$conteudoId, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function gerarESalvarQuestoes(PDO $pdo, array $conteudo, array $usuario, int $nivel): void
{
    executarOperacaoControladaIA(
        $pdo,
        (int)$usuario['id'],
        'questoes',
        $conteudo['materia_nome'] . ':' . $conteudo['titulo'] . ':' . $nivel,
        static function () use ($pdo, $conteudo, $usuario, $nivel): void {
            $geradas = gerarQuestoes(
                $conteudo['materia_nome'], $conteudo['titulo'], limparMarcacaoIA((string)($conteudo['corpo'] ?? '')),
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
        },
        'conteudo:' . (int)$conteudo['id']
    );
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

$nivelGeracaoQuestoes = max(1, min(12, (int)$conteudo['dificuldade_adaptativa']));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($acao, ['facilitador', 'responder'], true)) {
    $hashPostado = (string)($_POST['question_set_hash'] ?? '');
    $hashAtual = hashQuestoesAtuais($questoes) ?? '';
    if ($hashPostado === '' || $hashAtual === '' || !hash_equals($hashAtual, $hashPostado)) {
        $erro = 'Esta atividade foi atualizada em outra aba. Revise as novas questões antes de responder.';
        $acao = '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'novas_questoes') {
    try {
        gerarESalvarQuestoes($pdo, $conteudo, $usuario, $nivelGeracaoQuestoes);
        $questoes = carregarQuestoes($pdo, $conteudoId, (int)$usuario['id']);
        $questoesAtualizadas = true;
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
        $ajuda = executarOperacaoControladaIA(
            $pdo,
            (int)$usuario['id'],
            'facilitador',
            $questaoId . ':' . $nivelAjuda,
            static function () use ($pdo, $conteudo, $usuario, $questao, $questaoId, $nivelAjuda, $anteriores): array {
                $dica = gerarDicaQuestao(
                    $conteudo['materia_nome'], $conteudo['titulo'], $questao, $nivelAjuda,
                    array_column($anteriores, 'dica'), (int)$conteudo['dificuldade_adaptativa']
                );
                registrarAuditoriaIA($pdo, (int)$usuario['id'], 'facilitador', $questaoId . ':' . $nivelAjuda);
                return registrarAjudaQuestao($pdo, (int)$usuario['id'], $questaoId, $nivelAjuda, $dica);
            },
            'conteudo:' . $conteudoId
        );
        $ajudasPorQuestao[$questaoId][] = $ajuda;
        $usuario = usuarioAtual($pdo);
    } catch (DomainException $e) {
        $erro = $e->getMessage();
    } catch (Throwable $e) {
        error_log('[NEO][facilitador] ' . $e->getMessage());
        $erro = 'Não foi possível preparar a ajuda agora.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'responder' && $questoes) {
    try {
        [$resultado, $feedbacksIA] = executarComBloqueioRecurso(
            $pdo,
            (int)$usuario['id'],
            'conteudo:' . $conteudoId,
            static function () use ($pdo, $usuario, $conteudo, $questoes, $conteudoId): array {
                $respostas = [];
                $pendentesIA = [];
                $feedbacks = [];

                foreach ($questoes as $questao) {
                    $id = (int)$questao['id'];
                    $resposta = strtoupper(trim((string)($_POST['resposta_' . $id] ?? '')));
                    if (!in_array($resposta, ['A', 'B', 'C', 'D'], true)) {
                        throw new DomainException('Responda todas as questões antes de enviar.');
                    }
                    $respostas[$id] = $resposta;
                    $feedbacks[$id] = feedbackPersistido($questao, $resposta);
                    if ($feedbacks[$id] === '') {
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
                    $gerados = executarOperacaoControladaIA(
                        $pdo,
                        (int)$usuario['id'],
                        'feedback',
                        $conteudo['materia_nome'] . ':' . $conteudo['titulo'],
                        static fn(): array => gerarFeedbackQuestoes(
                            $conteudo['materia_nome'], $conteudo['titulo'], $pendentesIA,
                            trim($usuario['gostos'] ?? ''), (int)$conteudo['dificuldade_adaptativa']
                        ),
                        'feedback-conteudo:' . $conteudoId
                    );
                    foreach ($gerados as $id => $feedback) {
                        $feedbacks[(int)$id] = $feedback;
                    }
                }

                foreach ($questoes as $questao) {
                    $id = (int)$questao['id'];
                    if (empty($feedbacks[$id])) {
                        $correta = strtoupper((string)$questao['correta']);
                        $feedbacks[$id] = 'Compare a alternativa escolhida com o conceito central do enunciado. A opção correta é ' . $correta . ' porque corresponde ao que foi explicado no material.';
                    }
                }

                $tempos = is_array($_POST['tempos'] ?? null) ? $_POST['tempos'] : [];
                $metricas = [];
                foreach ($questoes as $questao) {
                    $id = (int)$questao['id'];
                    $tempo = filter_var($tempos[$id] ?? null, FILTER_VALIDATE_FLOAT);
                    $metricas[$id] = ['tempo_segundos' => $tempo !== false && $tempo > 0 && $tempo <= 7200 ? $tempo : null, 'formato' => 'questoes'];
                }
                $resultadoAtividade = registrarResultadoAtividade(
                    $pdo,
                    (int)$usuario['id'],
                    $conteudo,
                    $questoes,
                    $respostas,
                    $feedbacks,
                    $metricas
                );
                return [$resultadoAtividade, $feedbacks];
            }
        );
        $usuario = usuarioAtual($pdo);
    } catch (DomainException $e) {
        $erro = $e->getMessage();
    } catch (Throwable $e) {
        error_log('[NEO][questoes] ' . $e->getMessage());
        $erro = 'Não foi possível registrar a atividade agora. Nenhuma recompensa foi alterada.';
    }
}

$totalQuestoes = count($questoes);
$questoesGeradasTabs = $totalQuestoes > 0;
$atividadeQuestoesConcluida = atividadeAtualConcluida($pdo, (int)$usuario['id'], $conteudoId, $questoes);
$conteudoBloqueadoTabs = $questoesGeradasTabs && !$atividadeQuestoesConcluida;
$tituloPagina = 'Matéria';
$tituloTopbar = 'Matéria';
$paginaAtual = 'materias';
$usaSidebar = true;
$cssPaginas = ['questoes'];
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php require __DIR__ . '/includes/topbar.php'; ?>
    <section class="lesson-page">
        <section class="lesson-header">
            <div class="lesson-title-panel neo-panel">
                <h1><?= htmlspecialchars($conteudo['titulo']) ?></h1>
            </div>
            <a href="conteudos.php?materia_id=<?= (int)$conteudo['materia_id'] ?>" class="lesson-icon-btn" title="Voltar para conteúdos" aria-label="Voltar para conteúdos">
                <?= estrelaHoverNeo() ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M15 18l-6-6 6-6"></path>
                    <path d="M9 12h10"></path>
                </svg>
            </a>
        </section>

        <?php $contentTabActive = 'questoes'; require __DIR__ . '/includes/content_tabs.php'; ?>

    <?php if ($erro): ?>
        <div class="error"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>
    <?php if ($erroIA): ?>
        <div class="error">Não foi possível concluir a geração agora: <?= htmlspecialchars($erroIA) ?></div>
    <?php endif; ?>

        <form method="post" class="question-controls" data-ai-loading data-ai-message="Gerando novas questões">
            <?= campoCsrf() ?>
            <input type="hidden" name="conteudo_id" value="<?= (int)$conteudo['id'] ?>">
            <input type="hidden" name="acao" value="novas_questoes">
            <button type="submit" class="question-generate-card" title="Gerar novas questões" aria-label="Gerar novas questões">
                <?= estrelaHoverNeo() ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M8 5h9a2 2 0 0 1 2 2v12H7a2 2 0 0 1-2-2V8"></path>
                    <path d="M8 5V3"></path>
                    <path d="M8 11h7"></path>
                    <path d="M8 15h5"></path>
                    <path d="M5 8l3-3 3 3"></path>
                </svg>
                <span>Gerar novas questões</span>
            </button>
            <div class="question-difficulty-card" aria-label="Dificuldade das questões">
                <b>Nível <?= (int)$conteudo['dificuldade_adaptativa'] ?></b>
            </div>
        </form>

    <?php if (!$questoes): ?>
        <section class="question-empty neo-panel">
            <b>Nenhuma questão disponível</b>
            <p class="empty">A atividade ainda não pôde ser preparada para este conteúdo.</p>
        </section>
    <?php else: ?>
        <?php if ($resultado): ?>
            <section class="result-panel neo-panel">
                <div class="result-summary">
                    <span>Resultado</span>
                    <strong><?= (int)$resultado['acertos'] ?>/<?= (int)$resultado['total'] ?></strong>
                </div>
                <div class="result-actions">
                    <a href="questoes.php?conteudo_id=<?= (int)$conteudo['id'] ?>" class="result-retry-btn">
                        <?= estrelaHoverNeo() ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M3 12a9 9 0 0 1 15.3-6.4"></path>
                            <path d="M18 3v5h-5"></path>
                            <path d="M21 12a9 9 0 0 1-15.3 6.4"></path>
                            <path d="M6 21v-5h5"></path>
                        </svg>
                        <span>Tentar novamente</span>
                    </a>
                </div>
            </section>
        <?php endif; ?>

        <form method="post" class="question-form" data-ai-loading data-ai-message="Analisando suas respostas">
            <?= campoCsrf() ?>
            <input type="hidden" name="conteudo_id" value="<?= (int)$conteudo['id'] ?>">
            <input type="hidden" name="acao" value="responder">
            <input type="hidden" name="question_set_hash" value="<?= htmlspecialchars(hashQuestoesAtuais($questoes) ?? '') ?>">

            <?php foreach ($questoes as $i => $q): ?>
                <?php
                    $respostaUsuario = strtoupper(trim((string)($_POST['resposta_' . $q['id']] ?? '')));
                    $acertou = $resultado && $respostaUsuario === strtoupper((string)$q['correta']);
                    $ajudas = $ajudasPorQuestao[(int)$q['id']] ?? [];
                    $proximoNivel = count($ajudas) + 1;
                    $rotuloProvedorQuestao = siglaProvedorIA((string)($q['ai_provider'] ?? ''));
                    $nomeProvedorQuestao = nomeProvedorIA((string)($q['ai_provider'] ?? ''));
                ?>
                <article class="question-card" id="questao-<?= (int)$q['id'] ?>" data-question-id="<?= (int)$q['id'] ?>">
                    <?php if ($rotuloProvedorQuestao !== ''): ?>
                        <span class="ai-provider-badge" title="Questão gerada por <?= htmlspecialchars($nomeProvedorQuestao) ?>" aria-label="Questão gerada por <?= htmlspecialchars($nomeProvedorQuestao) ?>">
                            <?= $rotuloProvedorQuestao ?>
                        </span>
                    <?php endif; ?>
                    <div class="question-meta">
                        <span class="tag">QUESTÃO <?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></span>
                    </div>

                    <h2><?= nl2br(htmlspecialchars(limparMarcacaoIA((string)$q['enunciado']))) ?></h2>
                    <div class="options">
                        <?php foreach (['A', 'B', 'C', 'D'] as $letra): ?>
                            <?php
                                $campo = 'opcao_' . strtolower($letra);
                                $estaMarcada = $respostaUsuario === $letra;
                                $classe = $resultado ? ($letra === strtoupper((string)$q['correta']) ? 'certa' : ($estaMarcada ? 'errada' : '')) : '';
                            ?>
                            <label class="opcao-label <?= $classe ?>">
                                <input
                                    type="radio"
                                    name="resposta_<?= (int)$q['id'] ?>"
                                    value="<?= $letra ?>"
                                    <?= $estaMarcada ? 'checked' : '' ?>
                                    <?= $resultado ? 'disabled' : 'required' ?>
                                >
                                <span class="option-letter"><?= $letra ?></span>
                                <span><?= nl2br(htmlspecialchars(limparMarcacaoIA((string)$q[$campo]))) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($ajudas): ?>
                        <div class="facilitator-list">
                            <?php foreach ($ajudas as $ajuda): ?>
                                <div class="facilitator-hint">
                                    <span>Ajuda <?= (int)$ajuda['nivel'] ?></span>
                                    <p><?= nl2br(htmlspecialchars(limparMarcacaoIA((string)$ajuda['dica']))) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$resultado): ?>
                        <div class="question-support">
                            <?php if ($proximoNivel <= 3): ?>
                                <button type="submit" name="facilitador_questao_id" value="<?= (int)$q['id'] ?>" class="facilitator-btn" formnovalidate data-ai-message="Preparando uma ajuda para você">
                                    <?= estrelaHoverNeo() ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                        <path d="M12 3l1.5 4.5L18 9l-4.5 1.5L12 15l-1.5-4.5L6 9l4.5-1.5L12 3Z"></path>
                                        <path d="M19 14l.8 2.2L22 17l-2.2.8L19 20l-.8-2.2L16 17l2.2-.8L19 14Z"></path>
                                    </svg>
                                    <span>Facilitador · <?= $proximoNivel === 1 ? 'grátis' : ($proximoNivel === 2 ? '25 coças' : '40 coças') ?></span>
                                </button>
                            <?php else: ?>
                                <span class="facilitator-complete">Todas as ajudas liberadas</span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="feedback feedback-ia <?= $acertou ? 'feedback-correct' : 'feedback-wrong' ?>">
                            <strong><?= $acertou ? 'Você acertou' : 'Revise esta resposta' ?></strong>
                            <p><?= nl2br(htmlspecialchars(limparMarcacaoIA((string)($feedbacksIA[$q['id']] ?? 'Revise o conceito apresentado no material.')))) ?></p>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>

            <?php if (!$resultado): ?>
                <button type="submit" class="question-submit">
                    <?= estrelaHoverNeo() ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M5 12h13"></path>
                        <path d="M13 6l6 6-6 6"></path>
                    </svg>
                    <span>Enviar respostas</span>
                </button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
    </section>
</main>
<?php if (!$resultado && $questoes): ?>
<script src="static/neo-study-metrics.js?v=<?= $assetVersion('static/neo-study-metrics.js') ?>" defer></script>
<?php endif; ?>
</body>
</html>
