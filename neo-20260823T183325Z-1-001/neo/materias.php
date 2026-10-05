<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/materia_icon.php';
require __DIR__ . '/services/ai.php';
require __DIR__ . '/services/content.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$perfilEstudo = adaptiveProfile($usuario);
$erroMateria = '';
$materiaSolicitada = trim((string)($_POST['materia_solicitada'] ?? ''));
$abrirFormularioMateria = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'apagar_materia') {
    validarCsrf();
    try {
        $materiaIds = $_POST['materia_ids'] ?? [$_POST['materia_id'] ?? 0];
        if (!is_array($materiaIds)) $materiaIds = [$materiaIds];
        $materiaIds = array_values(array_unique(array_filter(array_map('intval', $materiaIds), static fn(int $id): bool => $id > 0)));
        if (!$materiaIds || count($materiaIds) > 24) {
            throw new DomainException('Selecione ao menos uma matéria válida.');
        }
        foreach ($materiaIds as $materiaId) {
            arquivarMateriaUsuario($pdo, $usuario, $materiaId);
        }
        header('Location: materias.php', true, 303);
        exit;
    } catch (Throwable $e) {
        error_log('[NEO][apagar-materia] ' . get_class($e) . ': ' . $e->getMessage());
        $erroMateria = $e instanceof DomainException
            ? $e->getMessage()
            : 'Não foi possível apagar a matéria agora.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'adicionar_materia') {
    validarCsrf();
    $abrirFormularioMateria = true;
    try {
        $materiaSolicitada = validarTemaEducacional($materiaSolicitada, 'matéria');
        try {
            $materiaSolicitada = executarOperacaoControladaIA(
                $pdo,
                (int)$usuario['id'],
                'correcao_materia',
                $materiaSolicitada,
                fn() => corrigirNomeMateriaIA($materiaSolicitada),
                'correcao_materia'
            );
        } catch (DomainException) {
            // A correção é opcional: o usuário ainda pode criar a matéria quando a cota de IA acabar.
            $materiaSolicitada = formatarNomeMateria($materiaSolicitada);
        }
        $materiaSolicitada = formatarNomeMateria(validarTemaEducacional($materiaSolicitada, 'matéria'));
        $stmtTotal = $pdo->prepare('SELECT COUNT(DISTINCT materia_id) FROM conteudos WHERE user_id = ? AND removido_em IS NULL');
        $stmtTotal->execute([(int)$usuario['id']]);
        if ((int)$stmtTotal->fetchColumn() >= 24) {
            throw new DomainException('Você já atingiu o limite de 24 matérias na sua biblioteca.');
        }

        $stmtMateria = $pdo->prepare('SELECT id, nome FROM materias WHERE nome = ? LIMIT 1');
        $stmtMateria->execute([$materiaSolicitada]);
        $materiaExistente = $stmtMateria->fetch(PDO::FETCH_ASSOC);

        if (!$materiaExistente) {
            $pdo->prepare('INSERT IGNORE INTO materias (nome) VALUES (?)')->execute([$materiaSolicitada]);
            $stmtMateria->execute([$materiaSolicitada]);
            $materiaExistente = $stmtMateria->fetch(PDO::FETCH_ASSOC);
        }
        if (!$materiaExistente) {
            throw new RuntimeException('Não foi possível criar a matéria.');
        }

        $novaMateriaId = (int)$materiaExistente['id'];
        $nomeMateria = (string)$materiaExistente['nome'];
        $stmtDuplicada = $pdo->prepare('SELECT COUNT(*) FROM conteudos WHERE user_id = ? AND materia_id = ? AND removido_em IS NULL');
        $stmtDuplicada->execute([(int)$usuario['id'], $novaMateriaId]);
        if ((int)$stmtDuplicada->fetchColumn() > 0) {
            throw new DomainException('Essa matéria já está na sua lista.');
        }

        // Remove resíduos criados pela antiga exclusão por arquivamento antes de recriar a matéria.
        arquivarMateriaUsuario($pdo, $usuario, $novaMateriaId, true);
        $perfilEstudo = adaptiveProfile($usuario);

        $nivel = adaptiveInitialDifficulty($perfilEstudo, $nomeMateria);
        $introId = executarComBloqueioRecurso(
            $pdo,
            (int)$usuario['id'],
            'materia:' . $novaMateriaId,
            static fn(): int => criarLivroIntroducaoMateria($pdo, (int)$usuario['id'], $novaMateriaId, $nomeMateria, $nivel)
        );
        if ($introId <= 0) {
            throw new RuntimeException('Não foi possível criar a introdução dessa matéria.');
        }

        adicionarMateriaAoPerfil($pdo, $usuario, $nomeMateria);
        header('Location: conteudos.php?materia_id=' . $novaMateriaId);
        exit;
    } catch (Throwable $e) {
        $erroMateria = $e instanceof DomainException
            ? $e->getMessage()
            : 'Erro no sistema, tente novamente mais tarde.';
    }
}

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
$materias = array_values(array_filter($materias, static fn(array $materia): bool =>
    materiaDisponivelParaUsuario($usuario, (string)$materia['nome'], (int)$materia['total'] > 0)
));
usort($materias, static function (array $a, array $b) use ($usuario): int {
    $aEscolhida = materiaDisponivelParaUsuario($usuario, (string)$a['nome'], false);
    $bEscolhida = materiaDisponivelParaUsuario($usuario, (string)$b['nome'], false);
    return ($bEscolhida <=> $aEscolhida) ?: strcasecmp((string)$a['nome'], (string)$b['nome']);
});
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
        <?php if ($erroMateria && !$abrirFormularioMateria): ?>
            <div class="error subject-list-error" role="alert"><?= htmlspecialchars($erroMateria) ?></div>
        <?php endif; ?>
        <div class="subject-grid">
            <?php foreach ($materias as $m): ?>
                <div class="subject-item" data-subject-item data-subject-id="<?= (int)$m['id'] ?>" data-subject-name="<?= htmlspecialchars($m['nome']) ?>">
                <?php if ((int)$m['total'] === 0): ?>
                    <form method="post" action="conteudos.php" class="subject-form" data-ai-loading data-ai-message="Criando seus primeiros livros">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="materia_id" value="<?= (int)$m['id'] ?>">
                        <input type="hidden" name="acao" value="gerar_mais">
                        <button type="submit" class="subject neo-star-hover <?= classeTemaMateria($m['nome']) ?>" data-manel-tip="Cria o livro de introdução de <?= htmlspecialchars($m['nome'], ENT_QUOTES, 'UTF-8') ?>.">
                <?php else: ?>
                    <a class="subject neo-star-hover <?= classeTemaMateria($m['nome']) ?>" href="conteudos.php?materia_id=<?= (int)$m['id'] ?>" data-manel-tip="Abre a matéria <?= htmlspecialchars($m['nome'], ENT_QUOTES, 'UTF-8') ?> e mostra seus livros.">
                <?php endif; ?>
                    <?= estrelaHoverNeo() ?>
                    <span class="subject-selection-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 12 4 4 8-8"></path></svg>
                    </span>
                    <span class="subject-icon <?= classeTemaMateria($m['nome']) ?>">
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
                </div>
        <?php endforeach; ?>
            <button type="button" class="subject subject-add neo-star-hover" data-subject-add-open aria-label="Adicionar uma matéria" data-manel-tip="Abre o formulário para criar uma matéria e seu livro de introdução.">
                <?= estrelaHoverNeo() ?>
                <span class="subject-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg>
                </span>
                <b>Adicionar matéria</b>
                    <small>Começar pela introdução</small>
            </button>
            <button type="button" class="subject subject-delete-toggle neo-star-hover" data-subject-delete-toggle aria-pressed="false" aria-label="Selecionar matérias para excluir" data-manel-tip="Ativa a seleção das matérias que você deseja apagar.">
                <?= estrelaHoverNeo() ?>
                <span class="subject-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16"></path><path d="M9 7V4h6v3"></path><path d="M7 7l1 13h8l1-13"></path><path d="M10 11v5M14 11v5"></path></svg>
                </span>
                <b data-delete-title>Excluir matérias</b>
                <small data-delete-status>Selecionar matérias</small>
            </button>
        </div>
    </section>
    <a class="neo-icon-button neo-star-hover external-studies-link" href="estudos.php" data-manel-tip="Abre os estudos externos com o Manel.">
        <?= estrelaHoverNeo() ?>
        <?= iconeMateriaDashboard('portugues') ?>
        <span>Estudos externos</span>
    </a>
</main>

<div class="subject-request-modal" data-subject-request-modal data-open-on-load="<?= $abrirFormularioMateria ? '1' : '0' ?>" aria-hidden="true" hidden>
    <button type="button" class="subject-request-backdrop" data-subject-add-close aria-label="Fechar"></button>
    <section class="subject-request-panel" role="dialog" aria-modal="true" aria-labelledby="subjectRequestTitle">
        <button type="button" class="subject-request-close neo-star-hover" data-subject-add-close aria-label="Fechar" title="Fechar">
            <?= estrelaHoverNeo() ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"></path></svg>
        </button>
        <form method="post" class="subject-request-form" data-ai-loading data-ai-message="Criando a introdução da matéria">
            <?= campoCsrf() ?>
            <input type="hidden" name="acao" value="adicionar_materia">
            <h2 id="subjectRequestTitle">Qual matéria você quer estudar?</h2>
            <p>Digite só o nome da área. O NEO vai montar os primeiros livros conforme seu perfil.</p>
            <?php if ($erroMateria): ?>
                <div class="error" role="alert"><?= htmlspecialchars($erroMateria) ?></div>
            <?php endif; ?>
            <label class="sr-only" for="materia_solicitada">Nome da matéria</label>
            <input id="materia_solicitada" type="text" name="materia_solicitada" value="<?= htmlspecialchars($materiaSolicitada) ?>" minlength="2" maxlength="70" placeholder="Ex.: Programação, Música ou Mecânica" autocomplete="off" required>
            <button type="submit" class="subject-request-submit neo-star-hover" data-manel-tip="Cria a matéria e prepara seu livro de introdução.">
                <?= estrelaHoverNeo() ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"></path></svg>
                <span>Criar matéria</span>
            </button>
        </form>
    </section>
</div>

<div class="subject-delete-modal" data-subject-delete-modal aria-hidden="true" hidden>
    <button type="button" class="subject-request-backdrop" data-subject-delete-close aria-label="Cancelar exclusão"></button>
    <section class="subject-delete-panel" role="dialog" aria-modal="true" aria-labelledby="subjectDeleteTitle" aria-describedby="subjectDeleteText">
        <span class="subject-delete-warning" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="M10.3 3.8 2.4 18a2 2 0 0 0 1.8 3h15.6a2 2 0 0 0 1.8-3L13.7 3.8a2 2 0 0 0-3.4 0Z"></path></svg>
        </span>
        <h2 id="subjectDeleteTitle">Apagar matérias selecionadas?</h2>
        <p id="subjectDeleteText" data-subject-delete-summary></p>
        <div class="subject-delete-buttons">
            <button type="button" class="subject-delete-cancel neo-star-hover" data-subject-delete-close data-manel-tip="Fecha a confirmação sem apagar matérias."><?= estrelaHoverNeo() ?>Cancelar</button>
            <form method="post" data-subject-delete-form>
                <?= campoCsrf() ?>
                <input type="hidden" name="acao" value="apagar_materia">
                <div data-subject-delete-inputs></div>
                <button type="submit" class="subject-delete-confirm neo-star-hover" data-manel-tip="Apaga as matérias selecionadas e todos os vínculos delas na sua conta."><?= estrelaHoverNeo() ?>Apagar</button>
            </form>
        </div>
    </section>
</div>
<script>
(function () {
    var modal = document.querySelector('[data-subject-request-modal]');
    var input = document.getElementById('materia_solicitada');
    if (!modal || !input) return;
    var closeTimer = 0;
    function openModal() {
        window.clearTimeout(closeTimer);
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('subject-modal-open');
        window.requestAnimationFrame(function () {
            modal.classList.add('is-open');
            input.focus();
        });
    }
    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('subject-modal-open');
        closeTimer = window.setTimeout(function () { modal.hidden = true; }, 180);
    }
    document.querySelectorAll('[data-subject-add-open]').forEach(function (button) { button.addEventListener('click', openModal); });
    modal.querySelectorAll('[data-subject-add-close]').forEach(function (button) { button.addEventListener('click', closeModal); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && !modal.hidden) closeModal(); });
    if (modal.dataset.openOnLoad === '1') openModal();
})();

(function () {
    var toggle = document.querySelector('[data-subject-delete-toggle]');
    var items = Array.from(document.querySelectorAll('[data-subject-item]'));
    var modal = document.querySelector('[data-subject-delete-modal]');
    var inputs = modal && modal.querySelector('[data-subject-delete-inputs]');
    var summary = modal && modal.querySelector('[data-subject-delete-summary]');
    var status = toggle && toggle.querySelector('[data-delete-status]');
    if (!toggle || !modal || !inputs || !summary || !status) return;
    var selecting = false;
    var selected = new Map();
    var closeTimer = 0;

    function updateSelection() {
        items.forEach(function (item) {
            var active = selected.has(item.dataset.subjectId);
            item.classList.toggle('is-selected', active);
            item.querySelector('.subject')?.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        var count = selected.size;
        status.textContent = count ? count + (count === 1 ? ' selecionada' : ' selecionadas') + ' · confirmar' : 'Clique de novo para cancelar';
        toggle.classList.toggle('has-selection', count > 0);
    }

    function leaveSelectionMode() {
        selecting = false;
        selected.clear();
        document.body.classList.remove('subject-delete-mode');
        toggle.setAttribute('aria-pressed', 'false');
        updateSelection();
    }

    function openDeleteModal() {
        var names = Array.from(selected.values());
        inputs.replaceChildren();
        selected.forEach(function (_, id) {
            var input = document.createElement('input');
            input.type = 'hidden'; input.name = 'materia_ids[]'; input.value = id;
            inputs.appendChild(input);
        });
        summary.textContent = names.length === 1
            ? names[0] + ' e seus livros serão retirados da sua biblioteca.'
            : names.length + ' matérias e seus livros serão retirados da sua biblioteca.';
        window.clearTimeout(closeTimer);
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('subject-modal-open');
        window.requestAnimationFrame(function () { modal.classList.add('is-open'); });
    }

    function closeDeleteModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('subject-modal-open');
        closeTimer = window.setTimeout(function () { modal.hidden = true; }, 180);
    }

    toggle.addEventListener('click', function () {
        if (!selecting) {
            selecting = true;
            document.body.classList.add('subject-delete-mode');
            toggle.setAttribute('aria-pressed', 'true');
            status.textContent = 'Clique de novo para cancelar';
            return;
        }
        if (!selected.size) {
            leaveSelectionMode();
            return;
        }
        if (selected.size) openDeleteModal();
    });

    items.forEach(function (item) {
        item.querySelector('.subject')?.addEventListener('click', function (event) {
            if (!selecting) return;
            event.preventDefault();
            var id = item.dataset.subjectId;
            if (selected.has(id)) selected.delete(id);
            else selected.set(id, item.dataset.subjectName || 'Matéria');
            updateSelection();
        });
    });

    modal.querySelectorAll('[data-subject-delete-close]').forEach(function (button) { button.addEventListener('click', closeDeleteModal); });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        if (!modal.hidden) closeDeleteModal();
        else if (selecting) leaveSelectionMode();
    });
})();
</script>
</body>
</html>

