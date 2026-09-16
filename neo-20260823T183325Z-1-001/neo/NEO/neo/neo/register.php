<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

function cometaCadastroNeo(string $classe = ''): string
{
    $classeExtra = $classe !== '' ? ' ' . htmlspecialchars($classe, ENT_QUOTES, 'UTF-8') : '';

    return '<span class="neo-ai-loader-comet' . $classeExtra . '" aria-hidden="true">'
        . '<i></i><i></i><i></i>'
        . '<svg viewBox="0 0 120 120" focusable="false">'
        . '<path class="neo-ai-star-shadow" d="M60 6 C66 34 86 54 114 60 C86 66 66 86 60 114 C54 86 34 66 6 60 C34 54 54 34 60 6 Z"></path>'
        . '<path class="neo-ai-star-core" d="M60 18 C65 40 80 55 102 60 C80 65 65 80 60 102 C55 80 40 65 18 60 C40 55 55 40 60 18 Z"></path>'
        . '<path class="neo-ai-star-center" d="M60 34 C64 48 72 56 86 60 C72 64 64 72 60 86 C56 72 48 64 34 60 C48 56 56 48 60 34 Z"></path>'
        . '</svg></span>';
}

function fogueteCadastroNeo(): string
{
    return '<svg class="onboarding-rocket" viewBox="0 0 96 104" focusable="false" aria-hidden="true">'
        . '<path class="onboarding-rocket-flame onboarding-rocket-flame-side" d="M18 84L24 102L30 84Z"></path>'
        . '<path class="onboarding-rocket-flame-core" d="M22 85L24 96L27 85Z"></path>'
        . '<path class="onboarding-rocket-flame onboarding-rocket-flame-main" d="M39 82L48 104L57 82Z"></path>'
        . '<path class="onboarding-rocket-flame-core" d="M44 83L48 98L52 83Z"></path>'
        . '<path class="onboarding-rocket-flame onboarding-rocket-flame-side" d="M66 84L72 102L78 84Z"></path>'
        . '<path class="onboarding-rocket-flame-core" d="M70 85L72 96L75 85Z"></path>'
        . '<path class="onboarding-shuttle-wing" d="M40 50L16 72L12 86L42 75Z"></path>'
        . '<path class="onboarding-shuttle-wing" d="M56 50L80 72L84 86L54 75Z"></path>'
        . '<path class="onboarding-shuttle-booster" d="M17 35L24 25L31 35V84H17Z"></path>'
        . '<path class="onboarding-shuttle-booster-cap" d="M17 35L24 25L31 35Z"></path>'
        . '<path class="onboarding-shuttle-booster" d="M65 35L72 25L79 35V84H65Z"></path>'
        . '<path class="onboarding-shuttle-booster-cap" d="M65 35L72 25L79 35Z"></path>'
        . '<path class="onboarding-shuttle-body" d="M48 4C56 16 59 31 58 55L57 83H39L38 55C37 31 40 16 48 4Z"></path>'
        . '<path class="onboarding-shuttle-stripe" d="M44 30H52V79H44Z"></path>'
        . '<path class="onboarding-shuttle-window" d="M42 23L48 16L54 23L53 34H43Z"></path>'
        . '<path class="onboarding-shuttle-detail" d="M24 43V75M72 43V75M48 40V70"></path>'
        . '<path class="onboarding-shuttle-shine" d="M43 12C41 20 40 29 40 41"></path>'
        . '</svg>';
}

if (logado()) {
    header('Location: index.php');
    exit;
}

$erro = '';
$etapaInicial = 1;
$generosPermitidos = ['feminino', 'masculino', 'nao_binario', 'prefiro_nao_informar'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();

    $nome = trim((string)($_POST['nome'] ?? ''));
    $sobrenome = trim((string)($_POST['sobrenome'] ?? ''));
    $idade = filter_var($_POST['idade'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 6, 'max_range' => 120],
    ]);
    $genero = (string)($_POST['genero'] ?? '');
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $senha = (string)($_POST['senha'] ?? '');
    $confirmarSenha = (string)($_POST['confirmar_senha'] ?? '');
    $gostos = trim((string)($_POST['gostos'] ?? ''));
    $diasEstudoSemana = filter_var($_POST['dias_estudo_semana'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 7],
    ]);

    if ($nome === '' || $sobrenome === '' || $idade === false) {
        $erro = 'Preencha nome, sobrenome e uma idade válida.';
        $etapaInicial = 1;
    } elseif (mb_strlen($nome) > 100 || mb_strlen($sobrenome) > 100) {
        $erro = 'Nome ou sobrenome ultrapassou o tamanho permitido.';
        $etapaInicial = 1;
    } elseif (!in_array($genero, $generosPermitidos, true)) {
        $erro = 'Escolha uma opção de gênero.';
        $etapaInicial = 2;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
        $erro = 'Informe um e-mail válido.';
        $etapaInicial = 2;
    } elseif (strlen($senha) < 8) {
        $erro = 'A senha deve ter pelo menos 8 caracteres.';
        $etapaInicial = 2;
    } elseif ($senha !== $confirmarSenha) {
        $erro = 'A confirmação da senha não confere.';
        $etapaInicial = 2;
    } elseif ($gostos === '') {
        $erro = 'Conte um pouco sobre seus gostos e seu jeito de aprender.';
        $etapaInicial = 3;
    } elseif (mb_strlen($gostos) > 2000) {
        $erro = 'O texto de preferências ultrapassou o tamanho permitido.';
        $etapaInicial = 3;
    } elseif ($diasEstudoSemana === false) {
        $erro = 'Escolha entre 1 e 7 dias de estudo por semana.';
        $etapaInicial = 3;
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $erro = 'Já existe uma conta com esse e-mail.';
            $etapaInicial = 2;
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $preferenciasJson = json_encode(['texto' => $gostos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users
                        (nome, sobrenome, idade, genero, email, senha, gostos, preferencias_json, dias_estudo_semana, onboarding_concluido_em)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$nome, $sobrenome, $idade, $genero, $email, $hash, $gostos, $preferenciasJson, $diasEstudoSemana]);

                renovarSessaoAutenticada();
                $_SESSION['user_id'] = (int)$pdo->lastInsertId();
                $_SESSION['neo_dashboard_awaken'] = true;
                unset($_SESSION['neo_intro_login']);
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $erro = 'Já existe uma conta com esse e-mail.';
                    $etapaInicial = 2;
                } else {
                    throw $e;
                }
            }
        }
    }
}

$tituloPagina = 'Criar conta';
$cssPaginas = ['auth'];
$forcarIntroNeo = $_SERVER['REQUEST_METHOD'] !== 'POST';
$bodyClasses = ['auth-onboarding-page'];
require __DIR__ . '/includes/head.php';
?>
<main class="auth-wrap onboarding-wrap">
    <div class="onboarding-space-scene" aria-hidden="true">
        <span class="onboarding-comet-flight onboarding-comet-flight-one"><?= cometaCadastroNeo('onboarding-background-comet') ?></span>
        <span class="onboarding-rocket-flight onboarding-rocket-flight-one"><?= fogueteCadastroNeo() ?></span>
        <?php for ($estrela = 1; $estrela <= 4; $estrela++): ?>
            <span class="onboarding-space-star onboarding-space-star-<?= $estrela ?>"><i></i></span>
        <?php endfor; ?>
        <svg class="onboarding-constellation onboarding-constellation-one" viewBox="0 0 150 100" focusable="false">
            <path class="onboarding-constellation-line" d="M10 70L34 42L61 55L84 21L112 36L138 13M61 55L76 87M112 36L132 73"></path>
            <g class="onboarding-constellation-node">
                <rect x="6" y="66" width="8" height="8" transform="rotate(45 10 70)"></rect><rect x="30" y="38" width="8" height="8" transform="rotate(45 34 42)"></rect><rect x="57" y="51" width="8" height="8" transform="rotate(45 61 55)"></rect><rect x="80" y="17" width="8" height="8" transform="rotate(45 84 21)"></rect><rect x="108" y="32" width="8" height="8" transform="rotate(45 112 36)"></rect><rect x="134" y="9" width="8" height="8" transform="rotate(45 138 13)"></rect><rect x="72" y="83" width="8" height="8" transform="rotate(45 76 87)"></rect><rect x="128" y="69" width="8" height="8" transform="rotate(45 132 73)"></rect>
            </g>
        </svg>
        <svg class="onboarding-constellation onboarding-constellation-two" viewBox="0 0 130 120" focusable="false">
            <path class="onboarding-constellation-line" d="M18 15L47 36L78 27L106 51L83 78L53 69L24 99M47 36L53 69M78 27L83 78"></path>
            <g class="onboarding-constellation-node">
                <rect x="14" y="11" width="8" height="8" transform="rotate(45 18 15)"></rect><rect x="43" y="32" width="8" height="8" transform="rotate(45 47 36)"></rect><rect x="74" y="23" width="8" height="8" transform="rotate(45 78 27)"></rect><rect x="102" y="47" width="8" height="8" transform="rotate(45 106 51)"></rect><rect x="79" y="74" width="8" height="8" transform="rotate(45 83 78)"></rect><rect x="49" y="65" width="8" height="8" transform="rotate(45 53 69)"></rect><rect x="20" y="95" width="8" height="8" transform="rotate(45 24 99)"></rect>
            </g>
        </svg>
    </div>

    <section class="onboarding-shell" data-onboarding data-initial-step="<?= $etapaInicial ?>">
        <?php if ($erro): ?>
            <div class="error onboarding-error" role="alert"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="post" id="onboardingForm" novalidate>
            <?= campoCsrf() ?>
            <div class="onboarding-viewport">
                <header class="onboarding-top">
                    <div class="onboarding-progress" aria-label="Progresso do cadastro">
                        <i class="is-active"></i><i></i><i></i>
                    </div>
                </header>
                <section class="onboarding-panel" data-step="1">
                    <?= cometaCadastroNeo('onboarding-panel-comet') ?>
                    <div class="onboarding-heading">
                        <span class="tag">Primeiro passo</span>
                        <h1>Vamos começar por você</h1>
                    </div>

                    <div class="onboarding-fields onboarding-fields-stacked">
                        <div class="field">
                            <label for="nome">Nome</label>
                            <input id="nome" type="text" name="nome" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" maxlength="100" autocomplete="given-name" required>
                            <small class="field-message">Informe seu nome.</small>
                        </div>
                        <div class="field">
                            <label for="sobrenome">Sobrenome</label>
                            <input id="sobrenome" type="text" name="sobrenome" value="<?= htmlspecialchars($_POST['sobrenome'] ?? '') ?>" maxlength="100" autocomplete="family-name" required>
                            <small class="field-message">Informe seu sobrenome.</small>
                        </div>
                        <div class="field field-age">
                            <label for="idade">Idade</label>
                            <div class="number-control">
                                <button type="button" class="number-step" data-number-step="-1" data-number-target="idade" aria-label="Diminuir idade" title="Diminuir idade">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6 12h12"></path></svg>
                                </button>
                                <input id="idade" type="number" name="idade" value="<?= htmlspecialchars($_POST['idade'] ?? '') ?>" min="6" max="120" inputmode="numeric" required>
                                <button type="button" class="number-step" data-number-step="1" data-number-target="idade" aria-label="Aumentar idade" title="Aumentar idade">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M12 6v12"></path><path d="M6 12h12"></path></svg>
                                </button>
                            </div>
                            <small class="field-message">Use uma idade entre 6 e 120.</small>
                        </div>
                    </div>

                    <div class="onboarding-actions">
                        <a href="login.php" class="onboarding-back" aria-label="Voltar para o login" title="Voltar para o login">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"></path><path d="M9 12h10"></path></svg>
                        </a>
                        <button type="button" class="primary onboarding-next" data-next-step="2" aria-label="Ir para o próximo passo" title="Próximo passo">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13"></path><path d="m13 6 6 6-6 6"></path></svg>
                        </button>
                    </div>
                    <p class="switch-auth onboarding-switch-auth">Já tem conta? <a href="login.php">Entrar</a></p>
                </section>

                <section class="onboarding-panel" data-step="2">
                    <?= cometaCadastroNeo('onboarding-panel-comet') ?>
                    <div class="onboarding-heading">
                        <span class="tag">Sua conta</span>
                        <h1>Dados de acesso</h1>
                    </div>

                    <fieldset class="gender-field">
                        <legend>Gênero</legend>
                        <div class="gender-options">
                            <?php foreach (['feminino' => 'Feminino', 'masculino' => 'Masculino', 'nao_binario' => 'Não binário', 'prefiro_nao_informar' => 'Prefiro não informar'] as $valor => $rotulo): ?>
                                <label class="gender-option">
                                    <input type="radio" name="genero" value="<?= $valor ?>" <?= ($_POST['genero'] ?? '') === $valor ? 'checked' : '' ?> required>
                                    <span><?= $rotulo ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small class="field-message">Escolha uma opção.</small>
                    </fieldset>

                    <div class="onboarding-fields onboarding-fields-stacked account-fields">
                        <div class="field field-wide">
                            <label for="email">E-mail</label>
                            <input id="email" type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" maxlength="150" autocomplete="email" required>
                            <small class="field-message">Digite um e-mail válido.</small>
                        </div>
                        <div class="field">
                            <label for="senha">Senha</label>
                            <div class="password-control">
                                <input id="senha" type="password" name="senha" minlength="8" autocomplete="new-password" required>
                                <button type="button" class="password-toggle" data-password-toggle="senha" aria-label="Mostrar senha" title="Mostrar senha">
                                    <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                                    <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 18"></path><path d="M10.6 6.2A10.7 10.7 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-2.1 2.8"></path><path d="M6.6 6.6C3.6 8.4 2 12 2 12s3.5 6 10 6a9.8 9.8 0 0 0 4.1-.9"></path></svg>
                                </button>
                            </div>
                            <small class="field-message">Use pelo menos 8 caracteres.</small>
                        </div>
                        <div class="field">
                            <label for="confirmar_senha">Confirmar senha</label>
                            <div class="password-control">
                                <input id="confirmar_senha" type="password" name="confirmar_senha" minlength="8" autocomplete="new-password" required>
                                <button type="button" class="password-toggle" data-password-toggle="confirmar_senha" aria-label="Mostrar senha" title="Mostrar senha">
                                    <svg class="eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                                    <svg class="eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 18"></path><path d="M10.6 6.2A10.7 10.7 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-2.1 2.8"></path><path d="M6.6 6.6C3.6 8.4 2 12 2 12s3.5 6 10 6a9.8 9.8 0 0 0 4.1-.9"></path></svg>
                                </button>
                            </div>
                            <small class="field-message">As senhas precisam ser iguais.</small>
                        </div>
                    </div>

                    <div class="onboarding-actions">
                        <button type="button" class="onboarding-back" data-prev-step="1" aria-label="Voltar" title="Voltar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"></path><path d="M9 12h10"></path></svg>
                        </button>
                        <button type="button" class="primary onboarding-next" data-next-step="3" aria-label="Ir para o próximo passo" title="Próximo passo">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13"></path><path d="m13 6 6 6-6 6"></path></svg>
                        </button>
                    </div>
                    <p class="switch-auth onboarding-switch-auth">Já tem conta? <a href="login.php">Entrar</a></p>
                </section>

                <section class="onboarding-panel" data-step="3">
                    <?= cometaCadastroNeo('onboarding-panel-comet') ?>
                    <div class="onboarding-heading">
                        <span class="tag">Seu jeito de aprender</span>
                        <h1>Do que você gosta?</h1>
                    </div>

                    <div class="field preferences-text-field">
                        <label for="gostos">Gostos e estilo de estudo</label>
                        <textarea id="gostos" name="gostos" rows="6" maxlength="2000" required placeholder="Ex: gosto de tecnologia, futebol e música. Aprendo melhor com exemplos simples, comparações e explicações passo a passo."><?= htmlspecialchars($_POST['gostos'] ?? '') ?></textarea>
                        <div class="preferences-meta">
                            <small class="field-message">Escreva pelo menos uma preferência.</small>
                            <span><b data-preference-count>0</b>/2000</span>
                        </div>
                    </div>

                    <div class="field study-days-field">
                        <label for="dias_estudo_semana">Quantos dias você quer estudar por semana?</label>
                        <div class="number-control">
                            <button type="button" class="number-step" data-number-step="-1" data-number-target="dias_estudo_semana" aria-label="Diminuir dias de estudo" title="Diminuir dias de estudo">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M6 12h12"></path></svg>
                            </button>
                            <input id="dias_estudo_semana" type="number" name="dias_estudo_semana" value="<?= htmlspecialchars($_POST['dias_estudo_semana'] ?? '3') ?>" min="1" max="7" inputmode="numeric" required>
                            <button type="button" class="number-step" data-number-step="1" data-number-target="dias_estudo_semana" aria-label="Aumentar dias de estudo" title="Aumentar dias de estudo">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><path d="M12 6v12"></path><path d="M6 12h12"></path></svg>
                            </button>
                        </div>
                        <small class="field-message">Escolha entre 1 e 7 dias.</small>
                    </div>

                    <div class="onboarding-actions">
                        <button type="button" class="onboarding-back" data-prev-step="2" aria-label="Voltar" title="Voltar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"></path><path d="M9 12h10"></path></svg>
                        </button>
                        <button type="submit" class="primary onboarding-finish" aria-label="Finalizar cadastro" title="Finalizar cadastro">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13"></path><path d="m13 6 6 6-6 6"></path></svg>
                        </button>
                    </div>
                    <p class="switch-auth onboarding-switch-auth">Já tem conta? <a href="login.php">Entrar</a></p>
                </section>
            </div>
        </form>
    </section>
</main>

<script>
(function () {
    var root = document.querySelector('[data-onboarding]');
    if (!root) return;

    var form = document.getElementById('onboardingForm');
    var panels = Array.prototype.slice.call(root.querySelectorAll('[data-step]'));
    var indicators = Array.prototype.slice.call(root.querySelectorAll('.onboarding-progress i'));
    var currentStep = Math.max(1, Math.min(3, Number(root.dataset.initialStep) || 1));
    var password = document.getElementById('senha');
    var confirmation = document.getElementById('confirmar_senha');
    var preferencesText = document.getElementById('gostos');
    var preferenceCount = root.querySelector('[data-preference-count]');

    function setPositions() {
        panels.forEach(function (panel) {
            var step = Number(panel.dataset.step);
            panel.classList.toggle('is-active', step === currentStep);
            panel.dataset.position = step < currentStep ? 'before' : (step > currentStep ? 'after' : 'active');
            panel.setAttribute('aria-hidden', step === currentStep ? 'false' : 'true');
        });
        indicators.forEach(function (indicator, index) {
            indicator.classList.toggle('is-active', index < currentStep);
        });
    }

    function markField(input) {
        var field = input.closest('.field');
        if (!field) return;
        field.classList.toggle('is-valid', input.validity.valid && input.value !== '');
        field.classList.toggle('is-invalid', !input.validity.valid);
    }

    function validatePasswords() {
        confirmation.setCustomValidity(confirmation.value && password.value !== confirmation.value ? 'As senhas precisam ser iguais.' : '');
        if (confirmation.value) markField(confirmation);
    }

    function validateStep(step) {
        var panel = root.querySelector('[data-step="' + step + '"]');
        var valid = true;
        if (step === 2) validatePasswords();

        Array.prototype.slice.call(panel.querySelectorAll('input[required], textarea[required]')).forEach(function (input) {
            if (input === preferencesText) {
                input.setCustomValidity(input.value.trim() === '' ? 'Escreva pelo menos uma preferência.' : '');
            }
            if (!input.checkValidity()) {
                valid = false;
                markField(input);
            }
        });

        if (step === 2 && !panel.querySelector('input[name="genero"]:checked')) {
            panel.querySelector('.gender-field').classList.add('is-invalid');
            valid = false;
        }

        if (!valid) {
            var firstInvalid = panel.querySelector('.is-invalid input, input:invalid');
            if (!firstInvalid) firstInvalid = panel.querySelector('textarea:invalid');
            if (firstInvalid) firstInvalid.focus();
        }
        return valid;
    }

    function goTo(step) {
        currentStep = Math.max(1, Math.min(3, step));
        setPositions();
        window.requestAnimationFrame(function () {
            root.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    root.addEventListener('click', function (event) {
        var next = event.target.closest('[data-next-step]');
        var previous = event.target.closest('[data-prev-step]');
        if (next && validateStep(currentStep)) goTo(Number(next.dataset.nextStep));
        if (previous) goTo(Number(previous.dataset.prevStep));
    });

    root.querySelectorAll('input, textarea').forEach(function (input) {
        input.addEventListener('blur', function () { markField(input); });
        input.addEventListener('input', function () {
            if (input === password || input === confirmation) validatePasswords();
            if (input === preferencesText) input.setCustomValidity('');
            if (input.type !== 'radio' && input.type !== 'checkbox') markField(input);
        });
    });

    root.querySelectorAll('input[name="genero"]').forEach(function (input) {
        input.addEventListener('change', function () { root.querySelector('.gender-field').classList.remove('is-invalid'); });
    });

    root.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.dataset.passwordToggle);
            var reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.classList.toggle('is-visible', reveal);
            button.setAttribute('aria-label', reveal ? 'Esconder senha' : 'Mostrar senha');
            button.setAttribute('title', reveal ? 'Esconder senha' : 'Mostrar senha');
        });
    });

    root.querySelectorAll('[data-number-step]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.dataset.numberTarget || 'idade');
            if (!input) return;
            var direction = Number(button.dataset.numberStep) || 0;
            var minimum = Number(input.min) || 0;
            var maximum = Number(input.max) || 120;
            var current = input.value === '' ? minimum - direction : Number(input.value);
            input.value = Math.max(minimum, Math.min(maximum, current + direction));
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.focus();
        });
    });

    function updatePreferences() {
        preferenceCount.textContent = preferencesText.value.length;
    }
    preferencesText.addEventListener('input', updatePreferences);

    form.addEventListener('submit', function (event) {
        for (var step = 1; step <= 3; step += 1) {
            if (!validateStep(step)) {
                event.preventDefault();
                goTo(step);
                return;
            }
        }
    });

    setPositions();
    updatePreferences();
})();
</script>
</body>
</html>
