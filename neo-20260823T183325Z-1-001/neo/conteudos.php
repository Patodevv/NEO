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
$stmt = $pdo->prepare("SELECT * FROM conteudos WHERE materia_id = ? AND user_id = ? AND removido_em IS NULL ORDER BY ordem, id");
$stmt->execute([$materiaId, $usuario['id']]);
$conteudos = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!materiaDisponivelParaUsuario($usuario, (string)$materia['nome'], count($conteudos) > 0)) {
    header('Location: materias.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'solicitar_conteudo') {
    validarCsrf();
    $abrirModalSolicitacao = true;
    try {
            exigirIntroducaoConcluida($pdo, (int)$usuario['id'], $materiaId);
            $conteudoSolicitado = validarTemaEducacional($conteudoSolicitado);
            $stmtDuplicado = $pdo->prepare("SELECT id FROM conteudos WHERE materia_id = ? AND user_id = ? AND titulo = ? AND removido_em IS NULL LIMIT 1");
            $stmtDuplicado->execute([$materiaId, $usuario['id'], $conteudoSolicitado]);
            if ($stmtDuplicado->fetchColumn() !== false) {
                throw new DomainException('Esse conteúdo já está na sua lista.');
            }

            $stmtTitulos = $pdo->prepare('SELECT titulo FROM conteudos WHERE materia_id = ? AND user_id = ? AND removido_em IS NULL');
            $stmtTitulos->execute([$materiaId, $usuario['id']]);
            $titulosExistentes = $stmtTitulos->fetchAll(PDO::FETCH_COLUMN);
            $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(ordem), 0) FROM conteudos WHERE materia_id = ? AND user_id = ? AND removido_em IS NULL");
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
                : 'Erro no sistema, tente novamente mais tarde.';
        }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'gerar_mais') {
    validarCsrf();
    try {
        $primeiroLivroAtual = primeiroLivroMateria($pdo, (int)$usuario['id'], $materiaId);
        if (!$primeiroLivroAtual) {
            executarComBloqueioRecurso($pdo, (int)$usuario['id'], 'materia:' . $materiaId, static fn(): int => criarLivroIntroducaoMateria(
                $pdo,
                (int)$usuario['id'],
                $materiaId,
                (string)$materia['nome'],
                adaptiveInitialDifficulty(adaptiveProfile($usuario), (string)$materia['nome'])
            ));
            $stmt->execute([$materiaId, $usuario['id']]);
            $conteudos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            throw new DomainException('A introdução da matéria foi criada. Conclua esse primeiro livro antes de pedir mais.');
        }
        exigirIntroducaoConcluida($pdo, (int)$usuario['id'], $materiaId);
        $stmtTitulos = $pdo->prepare('SELECT titulo FROM conteudos WHERE materia_id = ? AND user_id = ? AND removido_em IS NULL');
        $stmtTitulos->execute([$materiaId, $usuario['id']]);
        $titulosExistentes = $stmtTitulos->fetchAll(PDO::FETCH_COLUMN);
        $stmtMax = $pdo->prepare("SELECT COALESCE(MAX(dificuldade), 0), COALESCE(MAX(ordem), 0) FROM conteudos WHERE materia_id = ? AND user_id = ? AND removido_em IS NULL");
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
    } catch (DomainException $e) {
        $erroIA = $e->getMessage();
    } catch (Exception $e) {
        $erroIA = 'Erro no sistema, tente novamente mais tarde.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['acao'] ?? ''), ['apagar_livro', 'apagar_livros'], true)) {
    validarCsrf();
    $conteudoIdsApagar = array_values(array_unique(array_filter(array_map(
        'intval',
        (array)($_POST['conteudo_ids'] ?? [$_POST['conteudo_id'] ?? 0])
    ), static fn(int $id): bool => $id > 0)));

    if ($conteudoIdsApagar) {
        try {
            if (function_exists('arquivarConteudos')) {
                arquivarConteudos($pdo, (int)$usuario['id'], $materiaId, $conteudoIdsApagar);
            } else {
                foreach ($conteudoIdsApagar as $conteudoApagarId) {
                    arquivarConteudo($pdo, (int)$usuario['id'], $materiaId, $conteudoApagarId);
                }
            }

            $stmt->execute([$materiaId, $usuario['id']]);
            $conteudos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            header('Location: conteudos.php?materia_id=' . $materiaId);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erroAcao = $e instanceof DomainException
                ? $e->getMessage()
                : 'Não foi possível apagar os livros agora.';
        }
    }
}

$totalConteudos = count($conteudos);
$primeiroLivroMateria = $conteudos[0] ?? null;
$introducaoConcluida = $primeiroLivroMateria && (string)($primeiroLivroMateria['status'] ?? '') === 'Concluído';
$expansaoLiberada = $totalConteudos === 0 || $introducaoConcluida;
$mensagemExpansaoBloqueada = 'Conclua o livro de introdução para liberar novos livros.';
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
    <section class="content-page <?= classeTemaMateria($materia['nome']) ?>">
        <section class="content-header" aria-label="Resumo da matéria">
            <div class="content-summary neo-panel">
                <span class="content-subject-icon" aria-hidden="true"><?= iconeMateriaDashboard($materia['nome']) ?></span>
                <div class="content-summary-main">
                    <h1><?= htmlspecialchars($materia['nome']) ?></h1>
                    <span class="content-count"><?= $totalConteudos ?> <?= $rotuloConteudos ?></span>
                </div>
            </div>

            <div class="content-header-actions">
                <?php if ($conteudos): ?>
                <button type="button" class="content-icon-btn content-delete-toggle neo-star-hover" data-content-delete-toggle aria-label="Selecionar livros para apagar" aria-pressed="false" data-manel-tip="Ativa a seleção de livros. Depois, confirme aqui quais deseja apagar.">
                    <?= estrelaHoverNeo() ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 7h16"></path><path d="M10 11v6M14 11v6"></path><path d="M6 7l1 13h10l1-13M9 7V4h6v3"></path></svg>
                </button>
                <?php endif; ?>
                <a href="materias.php" class="content-icon-btn neo-star-hover" aria-label="Voltar para matérias" data-manel-tip="Volta para a lista das suas matérias.">
                    <?= estrelaHoverNeo() ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M15 18l-6-6 6-6"></path>
                        <path d="M9 12h10"></path>
                    </svg>
                </a>
            </div>
        </section>

        <section class="content-list-wrap">


            <?php if ($erroIA): ?>
                <div class="error"><?= htmlspecialchars($erroIA) ?></div>
            <?php endif; ?>

            <?php if ($erroAcao): ?>
                <div class="error"><?= htmlspecialchars($erroAcao) ?></div>
            <?php endif; ?>

            <?php if (!$conteudos): ?>
                <div class="neo-empty-state content-empty-state">
                    <span aria-hidden="true"><?= iconeMateriaDashboard($materia['nome']) ?></span>
                    <strong>O primeiro livro ainda não foi criado.</strong>
                    <p>Use a opção abaixo para preparar a introdução desta matéria.</p>
                </div>
            <?php endif; ?>

            <div class="content-delete-status" data-content-delete-status hidden role="status">Selecione os livros que deseja apagar.</div>

            <div class="content-list">
                <?php foreach ($conteudos as $i => $c): ?>
                    <article class="content-row neo-star-hover" data-content-item data-content-id="<?= (int)$c['id'] ?>" data-content-title="<?= htmlspecialchars($c['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= estrelaHoverNeo() ?>
                        <a class="content-row-main" href="livro.php?conteudo_id=<?= (int)$c['id'] ?>" data-manel-tip="Abre o livro <?= htmlspecialchars($c['titulo'], ENT_QUOTES, 'UTF-8') ?> para leitura.">
                            <b><?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></b>
                            <span><?= htmlspecialchars($c['titulo']) ?></span>
                        </a>
                        <button type="button" class="content-select-btn" data-content-select aria-label="Selecionar <?= htmlspecialchars($c['titulo'], ENT_QUOTES, 'UTF-8') ?>" aria-pressed="false" data-manel-tip="Seleciona este livro para apagar."><span aria-hidden="true"></span></button>
                    </article>
                <?php endforeach; ?>
                <form method="post" class="content-generate-row" data-ai-loading data-ai-message="<?= $totalConteudos === 0 ? 'Criando a introdução' : 'Gerando novos livros' ?>">
                    <?= campoCsrf() ?>
                    <input type="hidden" name="acao" value="gerar_mais">
                    <span class="content-action-shell"<?= $expansaoLiberada ? '' : ' tabindex="0" data-manel-tip="' . htmlspecialchars($mensagemExpansaoBloqueada, ENT_QUOTES, 'UTF-8') . '"' ?>>
                    <button type="submit" class="content-generate-list-btn neo-star-hover" <?= $expansaoLiberada ? '' : 'disabled' ?> data-manel-tip="<?= $totalConteudos === 0 ? 'Cria o livro de introdução desta matéria.' : 'Gera a próxima sequência de livros desta matéria.' ?>">
                        <?= estrelaHoverNeo() ?>
                        <b>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="M5 5h7a4 4 0 0 1 4 4v10H9a4 4 0 0 0-4-4V5Z"></path>
                                <path d="M16 9a4 4 0 0 1 4-4v10a4 4 0 0 0-4 4"></path>
                                <path d="M12 8v6"></path>
                                <path d="M9 11h6"></path>
                            </svg>
                        </b>
                        <span><?= $totalConteudos === 0 ? 'Criar introdução' : ($expansaoLiberada ? 'Gerar mais livros' : 'Introdução pendente') ?></span>
                    </button>
                    </span>
                </form>
                <div class="content-request-row">
                    <span class="content-action-shell"<?= $expansaoLiberada ? '' : ' tabindex="0" data-manel-tip="' . htmlspecialchars($mensagemExpansaoBloqueada, ENT_QUOTES, 'UTF-8') . '"' ?>>
                    <button type="button" class="content-generate-list-btn content-request-open-btn neo-star-hover" data-content-request-open <?= $expansaoLiberada ? '' : 'disabled' ?> data-manel-tip="Escolhe um tema e cria um livro personalizado nesta matéria.">
                        <?= estrelaHoverNeo() ?>
                        <b>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                                <path d="M5 5h7a4 4 0 0 1 4 4v10H9a4 4 0 0 0-4-4V5Z"></path>
                                <path d="M16 9a4 4 0 0 1 4-4v10a4 4 0 0 0-4 4"></path>
                                <path d="m8 11 2 2 4-5"></path>
                            </svg>
                        </b>
                        <span><?= $expansaoLiberada ? 'Pedir um conteúdo' : 'Leia a introdução primeiro' ?></span>
                    </button>
                    </span>
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
        <button type="button" class="content-request-close neo-star-hover" data-content-request-close aria-label="Fechar" title="Fechar">
            <?= estrelaHoverNeo() ?>
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
            <button type="submit" class="content-request-submit neo-star-hover">
                <?= estrelaHoverNeo() ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 5h7a4 4 0 0 1 4 4v10H9a4 4 0 0 0-4-4V5Z"></path><path d="M16 9a4 4 0 0 1 4-4v10a4 4 0 0 0-4 4"></path><path d="M12 8v6M9 11h6"></path></svg>
                <span>Gerar livro</span>
            </button>
        </form>
    </section>
</div>
<div class="content-delete-modal" data-content-delete-modal aria-hidden="true" hidden>
    <button type="button" class="content-request-backdrop" data-content-delete-close aria-label="Cancelar exclusão" data-manel-tip="Fecha a confirmação sem apagar livros."></button>
    <section class="content-delete-panel" role="dialog" aria-modal="true" aria-labelledby="contentDeleteTitle" aria-describedby="contentDeleteSummary">
        <span class="content-delete-symbol" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"></path><path d="M10 11v6M14 11v6"></path><path d="M6 7l1 13h10l1-13M9 7V4h6v3"></path></svg>
        </span>
        <h2 id="contentDeleteTitle">Apagar livros selecionados?</h2>
        <p id="contentDeleteSummary" data-content-delete-summary></p>
        <form method="post" class="content-delete-confirm-form">
            <?= campoCsrf() ?>
            <input type="hidden" name="acao" value="apagar_livros">
            <div data-content-delete-inputs></div>
            <div class="content-delete-modal-actions">
                <button type="button" class="content-delete-cancel neo-star-hover" data-content-delete-close data-manel-tip="Volta para a lista sem apagar nada."><?= estrelaHoverNeo() ?><span>Cancelar</span></button>
                <button type="submit" class="content-delete-confirm neo-star-hover" data-manel-tip="Apaga da sua conta todos os livros selecionados."><?= estrelaHoverNeo() ?><span>Apagar</span></button>
            </div>
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

(function () {
    var toggle = document.querySelector('[data-content-delete-toggle]');
    var modal = document.querySelector('[data-content-delete-modal]');
    var status = document.querySelector('[data-content-delete-status]');
    var items = Array.from(document.querySelectorAll('[data-content-item]'));
    if (!toggle || !modal || !status || !items.length) return;

    var summary = modal.querySelector('[data-content-delete-summary]');
    var inputs = modal.querySelector('[data-content-delete-inputs]');
    var confirmButton = modal.querySelector('.content-delete-confirm');
    var selected = new Map();
    var selecting = false;
    var closeTimer = 0;

    function updateSelection() {
        items.forEach(function (item) {
            var active = selected.has(item.dataset.contentId);
            item.classList.toggle('is-selected', active);
            item.querySelector('[data-content-select]')?.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        var count = selected.size;
        status.textContent = count
            ? count + (count === 1 ? ' livro selecionado. Pressione a lixeira novamente para confirmar.' : ' livros selecionados. Pressione a lixeira novamente para confirmar.')
            : 'Selecione os livros que deseja apagar.';
        toggle.classList.toggle('has-selection', count > 0);
        toggle.setAttribute('data-manel-tip', count
            ? 'Confirma a exclusão de ' + count + (count === 1 ? ' livro selecionado.' : ' livros selecionados.')
            : 'Saia da seleção com Esc ou escolha pelo menos um livro.');
    }

    function enterSelectionMode() {
        selecting = true;
        document.body.classList.add('content-delete-mode');
        toggle.setAttribute('aria-pressed', 'true');
        status.hidden = false;
        updateSelection();
        items[0].querySelector('[data-content-select]')?.focus();
    }

    function leaveSelectionMode() {
        selecting = false;
        selected.clear();
        document.body.classList.remove('content-delete-mode');
        toggle.setAttribute('aria-pressed', 'false');
        status.hidden = true;
        updateSelection();
    }

    function toggleItem(item) {
        var id = item.dataset.contentId;
        if (selected.has(id)) selected.delete(id);
        else selected.set(id, item.dataset.contentTitle || 'Livro');
        updateSelection();
    }

    function openDeleteModal() {
        var names = Array.from(selected.values());
        inputs.replaceChildren();
        selected.forEach(function (_, id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'conteudo_ids[]';
            input.value = id;
            inputs.appendChild(input);
        });
        summary.textContent = names.length === 1
            ? '“' + names[0] + '” será removido da matéria, da rotina, dos últimos acessos e das atividades ligadas a ele.'
            : names.length + ' livros serão removidos da matéria, da rotina, dos últimos acessos e das atividades ligadas a eles.';
        window.clearTimeout(closeTimer);
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('content-modal-open');
        window.requestAnimationFrame(function () {
            modal.classList.add('is-open');
            confirmButton?.focus();
        });
    }

    function closeDeleteModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('content-modal-open');
        closeTimer = window.setTimeout(function () {
            modal.hidden = true;
            toggle.focus();
        }, 180);
    }

    toggle.addEventListener('click', function () {
        if (!selecting) {
            enterSelectionMode();
            return;
        }
        if (selected.size) openDeleteModal();
    });
    items.forEach(function (item) {
        item.querySelector('[data-content-select]')?.addEventListener('click', function () { toggleItem(item); });
        item.querySelector('.content-row-main')?.addEventListener('click', function (event) {
            if (!selecting) return;
            event.preventDefault();
            toggleItem(item);
        });
    });
    modal.querySelectorAll('[data-content-delete-close]').forEach(function (button) { button.addEventListener('click', closeDeleteModal); });
    document.addEventListener('neo:content-delete-guide', enterSelectionMode);
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        if (!modal.hidden) closeDeleteModal();
        else if (selecting) {
            leaveSelectionMode();
            toggle.focus();
        }
    });
})();
</script>
</body>
</html>


