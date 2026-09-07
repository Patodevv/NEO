<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

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
    <section class="onboarding-shell" data-onboarding data-initial-step="<?= $etapaInicial ?>">
        <header class="onboarding-top">
            <div class="onboarding-progress" aria-label="Progresso do cadastro">
                <i class="is-active"></i><i></i><i></i>
            </div>
        </header>

        <?php if ($erro): ?>
            <div class="error onboarding-error" role="alert"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="post" id="onboardingForm" novalidate>
            <?= campoCsrf() ?>
            <div class="onboarding-viewport">
                <section class="onboarding-panel" data-step="1">
                    <div class="onboarding-heading">
                        <span class="tag">Primeiro passo</span>
                        <h1>Vamos começar por você</h1>
                        <p>Complete as informações básicas do seu perfil.</p>
                    </div>

                    <div class="onboarding-fields onboarding-fields-two">
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

                    <div class="onboarding-actions onboarding-actions-end">
                        <button type="button" class="primary onboarding-next" data-next-step="2">
                            <span>Seguinte</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13"></path><path d="m13 6 6 6-6 6"></path></svg>
                        </button>
                    </div>
                </section>

                <section class="onboarding-panel" data-step="2">
                    <div class="onboarding-heading">
                        <span class="tag">Sua conta</span>
                        <h1>Dados de acesso</h1>
                        <p>Escolha como você quer entrar no NEO.</p>
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

                    <div class="onboarding-fields onboarding-fields-two account-fields">
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
                        <button type="button" class="primary onboarding-next" data-next-step="3">
                            <span>Seguinte</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13"></path><path d="m13 6 6 6-6 6"></path></svg>
                        </button>
                    </div>
                </section>

                <section class="onboarding-panel" data-step="3">
                    <div class="onboarding-heading">
                        <span class="tag">Seu jeito de aprender</span>
                        <h1>Do que você gosta?</h1>
                        <p>Escreva seus interesses e como você prefere aprender.</p>
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
                        <button type="submit" class="primary onboarding-finish">
                            <span>Finalizar</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>
                        </button>
                    </div>
                </section>
            </div>
        </form>

        <div class="switch-auth">Já tem conta? <a href="login.php">Entrar</a></div>
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
