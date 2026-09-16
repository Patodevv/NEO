<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
exigirLogin();
$usuario = usuarioAtual($pdo);
$tituloPagina = 'Matérias';
$paginaAtual = 'materias';
$usaSidebar = true;
$cssPaginas = ['estudos'];
$bodyClasses = ['study-screen'];
$rostoNoPainelEstudo = true;
$rostoSorridenteNoPainelEstudo = true;
require __DIR__ . '/includes/head.php';
?>
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main study-page is-face-arriving" data-study-page data-user-id="<?= (int)$usuario['id'] ?>">
    <?php require __DIR__ . '/includes/topbar.php'; ?>
    <section class="study-shell" aria-label="Estudos externos com Manel">
    <header class="study-heading">
        <h1 class="sr-only">Estudos externos</h1>
        <div class="study-starters" aria-label="Começar um estudo">
            <button type="button" class="neo-icon-button neo-star-hover" data-study-starter="Quero estudar "><?= estrelaHoverNeo() ?><?= iconeMateriaDashboard('portugues') ?><span>Estudar um tema</span></button>
            <button type="button" class="neo-icon-button neo-star-hover" data-study-starter="Resuma este site e prepare questões sobre ele: "><?= estrelaHoverNeo() ?><?= iconeMateriaDashboard('geografia') ?><span>Resumir um site</span></button>
            <button type="button" class="neo-icon-button neo-star-hover" data-study-starter="Quero revisar este texto: "><?= estrelaHoverNeo() ?><?= iconeMateriaDashboard('historia') ?><span>Revisar um texto</span></button>
        </div>
        <button class="neo-icon-button neo-star-hover" type="button" data-study-back hidden title="Voltar ao início do estudo" aria-label="Voltar ao início do estudo">
            <?= estrelaHoverNeo() ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="m12 5-7 7 7 7M5 12h14"/></svg>
        </button>
        <button class="neo-icon-button neo-star-hover" type="button" data-study-new title="Novo estudo" aria-label="Novo estudo">
            <?= estrelaHoverNeo() ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
        </button>
        <a class="neo-icon-button neo-star-hover" href="materias.php" title="Voltar às matérias" aria-label="Voltar às matérias">
            <?= estrelaHoverNeo() ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="m12 5-7 7 7 7M5 12h14"/></svg>
        </a>
    </header>
    <section class="study-workspace" aria-label="Estudos com Manel">
        <div class="study-thread" data-study-thread>
        <div class="study-face-slot"><?php require __DIR__ . '/includes/companion_face.php'; ?></div>
        <div class="study-messages" data-study-messages aria-label="Conversa de estudo"></div>
        <p class="study-error" data-study-error role="alert" hidden></p>
        </div>
        <form class="study-composer" data-study-form>
            <label class="sr-only" for="study-request">O que você quer estudar?</label>
            <textarea id="study-request" name="message" rows="2" maxlength="4000" required placeholder="O que você quer estudar? Cole um tema, texto ou link…" aria-describedby="study-request-count"></textarea>
            <div class="study-compose-actions">
                <label for="study-quantity">Questões</label>
                <select id="study-quantity" name="quantity">
                    <?php for ($i = 2; $i <= 10; $i++): ?><option value="<?= $i ?>" <?= $i === 5 ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?>
                </select>
                <span id="study-request-count">0/4000</span>
                <button class="neo-icon-button neo-star-hover" type="submit" data-study-send aria-label="Enviar pedido" title="Enviar pedido">
                    <?= estrelaHoverNeo() ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 14-7-7 14-2-7-5-2ZM10 14l9-9"/></svg>
                </button>
                <button class="neo-icon-button neo-star-hover" type="button" data-study-cancel aria-label="Cancelar pedido" title="Cancelar pedido" hidden>
                    <?= estrelaHoverNeo() ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                </button>
            </div>
        </form>
    </section>
    </section>
    <template data-study-button-star><?= estrelaHoverNeo() ?></template>
</main>
<script src="static/estudos.js?v=<?= $assetVersion('static/estudos.js') ?>" defer></script>
</body>
</html>
