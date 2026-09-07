<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/materia_icon.php';
require __DIR__ . '/services/ai.php';
require __DIR__ . '/services/content.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$erroIA = '';
$erroAcao = '';
$erroSolicitacao = '';
$conteudoSolicitado = trim((string)($_POST['conteudo_solicitado'] ?? ''));
$abrirModalSolicitacao = false;
$materiaId = (int)($_GET['materia_id'] ?? ($_POST['materia_id'] ?? 0));
$stmt = $pdo->prepare("SELECT * FROM materias WHERE id = ?");
$stmt->execute([$materiaId]);
$materia = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$materia) {
    header('Location: materias.php');
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM conteudos WHERE materia_id = ? AND user_id = ? AND removido_em IS NULL ORDER BY dificuldade, ordem, id");
$stmt->execute([$materiaId, $usuario['id']]);
$conteudos = $stmt->fetchAll(PDO::FETCH_ASSOC);
function salvarConteudosGerados(PDO $pdo, int $userId, int $materiaId, array $gerados, int $dificuldade, int $ordemInicial, array $titulosExistentes): int
{
    $stmtInsert = $pdo->prepare("INSERT INTO conteudos (user_id, materia_id, titulo, status, corpo, ai_provider, ai_model, dificuldade, ordem) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $salvos = 0;
    $titulosNormalizados = [];
    $iniciouTransacao = !$pdo->inTransaction();

    if ($iniciouTransacao) {
        $pdo->beginTransaction();
    }

    try {

        foreach ($titulosExistentes as $tituloExistente) {
        $titulosNormalizados[mb_strtolower(trim($tituloExistente))] = true;
        }

        foreach ($gerados as $conteudoGerado) {
        $titulo = limparMarcacaoIA((string)($conteudoGerado['titulo'] ?? ''));
        $corpo = limparMarcacaoIA((string)($conteudoGerado['corpo'] ?? ''));
        $normalizado = mb_strtolower($titulo);

        if ($titulo !== '' && $corpo !== '' && empty($titulosNormalizados[$normalizado])) {
            $stmtInsert->execute([
                $userId,
                $materiaId,
                $titulo,
                'Gerado pela IA',
                $corpo,
                trim((string)($conteudoGerado['_ai_provider'] ?? 'Local')),
                trim((string)($conteudoGerado['_ai_model'] ?? 'fallback')),
                $dificuldade,
                $ordemInicial + $salvos,
            ]);
            $titulosNormalizados[$normalizado] = true;
            $salvos++;
        }
        }

        if ($salvos !== count($gerados)) {
            throw new RuntimeException('Nem todos os conteúdos passaram pela validação de unicidade.');
        }
        if ($iniciouTransacao) {
            $pdo->commit();
        }
        return $salvos;
    } catch (Throwable $e) {
        if ($iniciouTransacao && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function gerarSeisConteudos(string $materia, string $gostos, array $titulosExistentes, int $nivel): array
{
    $conteudos = [];
    $titulosUsados = $titulosExistentes;

    for ($tentativa = 0; $tentativa < 3 && count($conteudos) < 6; $tentativa++) {
        $gerados = gerarConteudos($materia, $gostos, $titulosUsados, $nivel);

        foreach ($gerados as $conteudoGerado) {
            $titulo = limparMarcacaoIA((string)($conteudoGerado['titulo'] ?? ''));
            $corpo = limparMarcacaoIA((string)($conteudoGerado['corpo'] ?? ''));

            if ($titulo === '' || $corpo === '') {
                continue;
            }

            $jaExiste = false;
            foreach ($titulosUsados as $tituloUsado) {
                if (mb_strtolower(trim($tituloUsado)) === mb_strtolower($titulo)) {
                    $jaExiste = true;
                    break;
                }
            }

            if (!$jaExiste) {
                $conteudos[] = [
                    'titulo' => $titulo,
                    'corpo' => $corpo,
                    '_ai_provider' => $conteudoGerado['_ai_provider'] ?? 'Local',
                    '_ai_model' => $conteudoGerado['_ai_model'] ?? 'fallback',
                ];
                $titulosUsados[] = $titulo;
            }

            if (count($conteudos) >= 6) {
                break 2;
            }
        }
    }

    if (count($conteudos) < 6) {
        throw new Exception('A IA gerou menos de 6 conteudos novos. Tente novamente.');
    }

    return array_slice($conteudos, 0, 6);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'solicitar_conteudo') {
    validarCsrf();
    $conteudoSolicitado = preg_replace('/\s+/u', ' ', $conteudoSolicitado) ?? $conteudoSolicitado;
    $abrirModalSolicitacao = true;

    if (mb_strlen($conteudoSolicitado) < 3 || mb_strlen($conteudoSolicitado) > 180) {
        $erroSolicitacao = 'Digite um conteúdo entre 3 e 180 caracteres.';
    } else {
        try {
            $stmtDuplicado = $pdo->prepare("SELECT id FROM conteudos WHERE materia_id = ? AND user_id = ? AND titulo = ? AND removido_em IS NULL LIMIT 1");
            $stmtDuplicado->execute([$materiaId, $usuario['id'], $conteudoSolicitado]);
            if ($stmtDuplicado->fetchColumn() !== false) {
                throw new DomainException('Esse conteúdo já está na sua lista.');
            }

            $stmtTitulos = $pdo->prepare('SELECT titulo FROM conteudos WHERE materia_id = ? AND user_id = ? AND removido_em IS NULL');
            $stmtTitulos->execute([$materiaId, $usuario['id']]);
            $titulosExistentes = $stmtTitulos->fetchAll(PDO::FETCH_COLUMN);
            $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(ordem), 0) FROM conteudos WHERE materia_id = ? AND user_id = ?");
            $stmtMax->execute([$materiaId, $usuario['id']]);
            $proximaOrdem = (int)$stmtMax->fetchColumn() + 1;
            $nivelUsuario = dificuldadeAdaptativa($pdo, (int)$usuario['id'], $materiaId);

            $salvos = executarOperacaoControladaIA(
                $pdo,
                (int)$usuario['id'],
                'livro',
                $materia['nome'] . ':' . $conteudoSolicitado . ':' . $nivelUsuario,
                static function () use ($pdo, $usuario, $materia, $materiaId, $conteudoSolicitado, $nivelUsuario, $proximaOrdem, $titulosExistentes): int {
                    $livro = gerarLivro(
                        $materia['nome'],
                        $conteudoSolicitado,
                        trim((string)($usuario['gostos'] ?? '')),
                        $nivelUsuario
                    );
                    $livro['titulo'] = $conteudoSolicitado;
                    registrarAuditoriaIA($pdo, (int)$usuario['id'], 'livro', $materia['nome'] . ':' . $conteudoSolicitado . ':' . $nivelUsuario);

                    return salvarConteudosGerados(
                        $pdo,
                        (int)$usuario['id'],
                        $materiaId,
                        [$livro],
                        $nivelUsuario,
                        $proximaOrdem,
                        $titulosExistentes
                    );
                },
                'materia:' . $materiaId
            );

            if ($salvos !== 1) {
                throw new RuntimeException('Não foi possível incluir o conteúdo solicitado.');
            }

            header('Location: conteudos.php?materia_id=' . $materiaId);
            exit;
        } catch (Throwable $e) {
            $erroSolicitacao = $e instanceof DomainException
                ? $e->getMessage()
                : 'Não foi possível gerar esse livro agora. Tente novamente.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar_mais') {
    validarCsrf();
    try {
        $stmtTitulos = $pdo->prepare('SELECT titulo FROM conteudos WHERE materia_id = ? AND user_id = ?');
        $stmtTitulos->execute([$materiaId, $usuario['id']]);
        $titulosExistentes = $stmtTitulos->fetchAll(PDO::FETCH_COLUMN);
        $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(dificuldade), 0), COALESCE(MAX(ordem), 0) FROM conteudos WHERE materia_id = ? AND user_id = ?");
        $stmtMax->execute([$materiaId, $usuario['id']]);
        [$maiorDificuldade, $maiorOrdem] = array_map('intval', $stmtMax->fetch(PDO::FETCH_NUM));
        $proximoNivel = $maiorDificuldade + 1;

        $salvos = executarOperacaoControladaIA(
            $pdo,
            (int)$usuario['id'],
            'conteudos',
            $materia['nome'] . ':' . $proximoNivel,
            static function () use ($pdo, $usuario, $materia, $materiaId, $titulosExistentes, $proximoNivel, $maiorOrdem): int {
                $gerados = gerarSeisConteudos(
                    $materia['nome'],
                    trim($usuario['gostos'] ?? ''),
                    $titulosExistentes,
                    $proximoNivel
                );
                registrarAuditoriaIA($pdo, (int)$usuario['id'], 'conteudos', $materia['nome'] . ':' . $proximoNivel);
                return salvarConteudosGerados(
                    $pdo,
                    (int)$usuario['id'],
                    $materiaId,
                    $gerados,
                    $proximoNivel,
                    $maiorOrdem + 1,
                    $titulosExistentes
                );
            },
            'materia:' . $materiaId
        );

        if ($salvos === 0) {
            throw new Exception('A IA nao retornou conteudos novos o suficiente. Tente novamente.');
        }

        $stmt->execute([$materiaId, $usuario['id']]);
        $conteudos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $erroIA = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'apagar_livro') {
    validarCsrf();
    $conteudoApagarId = (int)($_POST['conteudo_id'] ?? 0);

    if ($conteudoApagarId > 0) {
        try {
            arquivarConteudo($pdo, (int)$usuario['id'], $materiaId, $conteudoApagarId);

            $stmt->execute([$materiaId, $usuario['id']]);
            $conteudos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erroAcao = 'Não foi possível apagar o livro agora.';
        }
    }
}

$totalConteudos = count($conteudos);
$rotuloConteudos = $totalConteudos === 1 ? 'livro' : 'livros';
$tituloPagina = 'Matéria';
$tituloTopbar = 'Matéria';
$paginaAtual  = 'materias';
$usaSidebar = true;
$cssPaginas = ['conteudos'];
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">
    <?php require __DIR__ . '/includes/topbar.php'; ?>
    <section class="content-page">
        <section class="content-header" aria-label="Resumo da matéria">
            <div class="content-summary neo-panel">
                <div class="content-summary-main">
                    <h1><?= htmlspecialchars($materia['nome']) ?></h1>
                    <span class="content-count"><?= $totalConteudos ?> <?= $rotuloConteudos ?></span>
                </div>
            </div>

            <a href="materias.php" class="content-icon-btn" aria-label="Voltar para matérias" title="Voltar para matérias">
                <?= estrelaHoverNeo() ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M15 18l-6-6 6-6"></path>
                    <path d="M9 12h10"></path>
                </svg>
            </a>
        </section>

        <section class="content-list-wrap">


            <?php if ($erroIA): ?>
                <div class="error">Não foi possível gerar os livros agora: <?= htmlspecialchars($erroIA) ?></div>
            <?php endif; ?>

            <?php if ($erroAcao): ?>
                <div class="error"><?= htmlspecialchars($erroAcao) ?></div>
            <?php endif; ?>

            <?php if (!$conteudos): ?>
                <p class="empty">Nenhum livro disponível nessa matéria ainda.</p>
            <?php endif; ?>

            <div class="content-list">
                <?php foreach ($conteudos as $i => $c): ?>
                    <article class="content-row">
                        <a class="content-row-main" href="livro.php?conteudo_id=<?= (int)$c['id'] ?>">
                            <b><?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></b>
                            <span><?= htmlspecialchars($c['titulo']) ?></span>
                        </a>
                        <form method="post" class="content-delete-form" onsubmit="return confirm('Apagar este livro?');">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="acao" value="apagar_livro">
                            <input type="hidden" name="conteudo_id" value="<?= (int)$c['id'] ?>">
                            <button type="submit" class="content-delete-btn" aria-label="Apagar livro">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                    <path d="M4 7h16"></path>
                                    <path d="M10 11v6"></path>
                                    <path d="M14 11v6"></path>
                                    <path d="M6 7l1 13h10l1-13"></path>
                                    <path d="M9 7V4h6v3"></path>
                                </svg>
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
                <form method="post" class="content-generate-row" data-ai-loading data-ai-message="Gerando novos livros">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="acao" value="gerar_mais">
                    <button type="submit" class="content-generate-list-btn">
                        <b>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="M5 5h7a4 4 0 0 1 4 4v10H9a4 4 0 0 0-4-4V5Z"></path>
                                <path d="M16 9a4 4 0 0 1 4-4v10a4 4 0 0 0-4 4"></path>
                                <path d="M12 8v6"></path>
                                <path d="M9 11h6"></path>
                            </svg>
                        </b>
                        <span>Gerar mais livros</span>
                    </button>
                </form>
                <div class="content-request-row">
                    <button type="button" class="content-generate-list-btn content-request-open-btn" data-content-request-open>
                        <b>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="M5 5h7a4 4 0 0 1 4 4v10H9a4 4 0 0 0-4-4V5Z"></path>
                                <path d="M16 9a4 4 0 0 1 4-4v10a4 4 0 0 0-4 4"></path>
                                <path d="m8 11 2 2 4-5"></path>
                            </svg>
                        </b>
                        <span>Pedir um conteúdo</span>
                    </button>
                </div>
            </div>
        </section>
    </section>
</main>

<div class="content-request-modal" data-content-request-modal data-open-on-load="<?= $abrirModalSolicitacao ? '1' : '0' ?>" aria-hidden="true" hidden>
    <button type="button" class="content-request-backdrop" data-content-request-close aria-label="Fechar"></button>
    <section class="content-request-panel" role="dialog" aria-modal="true" aria-labelledby="contentRequestTitle">
        <span class="neo-ai-loader-comet content-request-comet" aria-hidden="true">
            <i></i><i></i><i></i>
            <svg viewBox="0 0 120 120" focusable="false">
                <path class="neo-ai-star-shadow" d="M60 6 C66 34 86 54 114 60 C86 66 66 86 60 114 C54 86 34 66 6 60 C34 54 54 34 60 6 Z"></path>
                <path class="neo-ai-star-core" d="M60 18 C65 40 80 55 102 60 C80 65 65 80 60 102 C55 80 40 65 18 60 C40 55 55 40 60 18 Z"></path>
                <path class="neo-ai-star-center" d="M60 34 C64 48 72 56 86 60 C72 64 64 72 60 86 C56 72 48 64 34 60 C48 56 56 48 60 34 Z"></path>
            </svg>
        </span>
        <button type="button" class="content-request-close" data-content-request-close aria-label="Fechar" title="Fechar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"></path></svg>
        </button>
        <form method="post" class="content-request-form" data-ai-loading data-ai-message="Criando seu livro">
            <?= campoCsrf() ?>
            <input type="hidden" name="acao" value="solicitar_conteudo">
            <input type="hidden" name="materia_id" value="<?= $materiaId ?>">
            <h2 id="contentRequestTitle">Qual conteúdo você quer estudar?</h2>
            <?php if ($erroSolicitacao): ?>
                <div class="error" role="alert"><?= htmlspecialchars($erroSolicitacao) ?></div>
            <?php endif; ?>
            <label class="sr-only" for="conteudo_solicitado">Conteúdo do novo livro</label>
            <input id="conteudo_solicitado" type="text" name="conteudo_solicitado" value="<?= htmlspecialchars($conteudoSolicitado) ?>" minlength="3" maxlength="180" placeholder="Digite o conteúdo" autocomplete="off" required>
            <button type="submit" class="content-request-submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 5h7a4 4 0 0 1 4 4v10H9a4 4 0 0 0-4-4V5Z"></path><path d="M16 9a4 4 0 0 1 4-4v10a4 4 0 0 0-4 4"></path><path d="M12 8v6M9 11h6"></path></svg>
                <span>Gerar livro</span>
            </button>
        </form>
    </section>
</div>
<script>
(function () {
    var modal = document.querySelector('[data-content-request-modal]');
    var input = document.getElementById('conteudo_solicitado');
    if (!modal || !input) return;
    var closeTimer = 0;

    function openModal() {
        window.clearTimeout(closeTimer);
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('content-modal-open');
        window.requestAnimationFrame(function () {
            modal.classList.add('is-open');
            input.focus();
        });
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('content-modal-open');
        closeTimer = window.setTimeout(function () { modal.hidden = true; }, 180);
    }

    document.querySelectorAll('[data-content-request-open]').forEach(function (button) {
        button.addEventListener('click', openModal);
    });
    modal.querySelectorAll('[data-content-request-close]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) closeModal();
    });
    if (modal.dataset.openOnLoad === '1') openModal();
})();
</script>
</body>
</html>
