(function () {
    'use strict';

    var loader = document.getElementById('neoAiLoader');
    var loaderMessage = loader ? loader.querySelector('[data-ai-loader-message]') : null;
    var activeRequests = 0;

    function showLoader(message) {
        if (!loader) return;
        activeRequests += 1;
        if (loaderMessage) {
            loaderMessage.textContent = message || 'A IA está preparando tudo';
        }
        loader.hidden = false;
        loader.setAttribute('aria-hidden', 'false');
        document.body.classList.add('neo-ai-busy');
    }

    function hideLoader(force) {
        if (!loader) return;
        activeRequests = force ? 0 : Math.max(0, activeRequests - 1);
        if (activeRequests > 0) return;
        loader.hidden = true;
        loader.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('neo-ai-busy');
    }

    window.NeoAILoader = {
        show: showLoader,
        hide: hideLoader,
        track: function (promise, message) {
            showLoader(message);
            return Promise.resolve(promise).finally(function () {
                hideLoader(false);
            });
        }
    };


    function syncCheckedLabel(label) {
        if (!label) return;
        var input = label.querySelector('input[type="radio"], input[type="checkbox"]');
        label.classList.toggle('is-checked', !!(input && input.checked));
    }

    function syncCheckedLabels(root) {
        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('.learning-answer, .study-option, .neo-setup-choice').forEach(syncCheckedLabel);
    }

    document.addEventListener('change', function (event) {
        var input = event.target;
        if (!input || !input.matches || !input.matches('input[type="radio"], input[type="checkbox"]')) return;
        var label = input.closest('.learning-answer, .study-option, .neo-setup-choice');
        if (!label) return;
        var form = input.form || document;
        if (input.type === 'radio' && input.name) {
            form.querySelectorAll('input[type="radio"]').forEach(function (item) {
                if (item.name === input.name) syncCheckedLabel(item.closest('.learning-answer, .study-option, .neo-setup-choice'));
            });
        } else {
            syncCheckedLabel(label);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { syncCheckedLabels(document); }, { once: true });
    } else {
        syncCheckedLabels(document);
    }

    document.addEventListener('submit', function (event) {
        var form = event.target.closest && event.target.closest('form[data-ai-loading]');
        if (!form || event.defaultPrevented) return;
        if (form.dataset.aiSubmitting === '1') {
            event.preventDefault();
            return;
        }
        form.dataset.aiSubmitting = '1';
        var submitter = event.submitter;
        if (submitter) submitter.setAttribute('aria-disabled', 'true');
        var message = submitter && submitter.dataset.aiMessage
            ? submitter.dataset.aiMessage
            : form.dataset.aiMessage;
        showLoader(message);
    });

    document.addEventListener('click', function (event) {
        var link = event.target.closest && event.target.closest('a[data-ai-loading]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        showLoader(link.dataset.aiMessage);
    });

    document.addEventListener('neo:ai-start', function (event) {
        showLoader(event.detail && event.detail.message);
    });
    document.addEventListener('neo:ai-end', function () {
        hideLoader(false);
    });
    window.addEventListener('pageshow', function () {
        hideLoader(true);
        document.querySelectorAll('form[data-ai-submitting="1"]').forEach(function (form) {
            delete form.dataset.aiSubmitting;
            form.querySelectorAll('[aria-disabled="true"]').forEach(function (button) {
                button.removeAttribute('aria-disabled');
            });
        });
    });

    (function setupMobilePreview() {
        var toggle = document.querySelector('[data-mobile-preview-toggle]');
        if (!toggle) return;
        var key = 'neo_mobile_preview';
        var isFramePage = window.self !== window.top;
        var stage = null;

        if (isFramePage) {
            toggle.classList.add('is-active');
            toggle.setAttribute('aria-pressed', 'true');
            toggle.addEventListener('click', function () {
                window.parent.postMessage({ type: 'neo-mobile-preview-off' }, window.location.origin);
            });
            return;
        }

        function frameUrl() {
            var url = new URL(window.location.href);
            url.searchParams.set('neo_mobile_frame', '1');
            return url.href;
        }

        function removeStage() {
            if (stage) {
                stage.classList.remove('is-active');
                window.setTimeout(function () {
                    if (stage && stage.parentNode) {
                        stage.parentNode.removeChild(stage);
                    }
                    stage = null;
                }, 220);
            }
        }

        function createStage() {
            if (stage) {
                var currentFrame = stage.querySelector('iframe');
                if (currentFrame) currentFrame.src = frameUrl();
                return;
            }
            stage = document.createElement('div');
            stage.className = 'neo-mobile-preview-stage';
            stage.setAttribute('aria-label', 'Simulacao mobile');

            var frame = document.createElement('iframe');
            frame.className = 'neo-mobile-preview-viewport';
            frame.title = 'Simulacao mobile';
            frame.src = frameUrl();
            stage.appendChild(frame);
            document.body.appendChild(stage);
            window.requestAnimationFrame(function () {
                if (stage) stage.classList.add('is-active');
            });
        }

        function setMobilePreview(active) {
            document.documentElement.classList.toggle('neo-mobile-preview-host', active);
            toggle.classList.toggle('is-active', active);
            toggle.setAttribute('aria-pressed', active ? 'true' : 'false');
            try {
                localStorage.setItem(key, active ? '1' : '0');
            } catch (e) {}
            if (active) {
                createStage();
            } else {
                removeStage();
            }
        }

        var initial = false;
        try {
            initial = localStorage.getItem(key) === '1';
        } catch (e) {}
        setMobilePreview(initial);

        toggle.addEventListener('click', function () {
            setMobilePreview(!document.documentElement.classList.contains('neo-mobile-preview-host'));
        });

        window.addEventListener('message', function (event) {
            if (event.origin !== window.location.origin || !event.data || event.data.type !== 'neo-mobile-preview-off') return;
            setMobilePreview(false);
        });
    })();

    (function setupManelTooltips() {
        if (!document.querySelector('[data-neo-companion]') || document.body.classList.contains('neo-onboarding-page')) return;

        var selector = 'a[href],button:not([disabled]),summary,[role="button"]:not([aria-disabled="true"]),[data-manel-tip]:not([disabled]),label,input:not([type="hidden"]):not([disabled]),select:not([disabled]),textarea:not([disabled])';
        var face = document.querySelector('[data-neo-companion]');
        var tooltip = document.createElement('div');
        tooltip.className = 'neo-manel-tooltip';
        tooltip.id = 'neoManelTooltip';
        tooltip.setAttribute('role', 'tooltip');
        tooltip.setAttribute('aria-hidden', 'true');
        tooltip.hidden = true;
        var manelName = clean(face.getAttribute('data-manel-name')) || 'Manel';
        var manelPersonality = clean(face.getAttribute('data-manel-personality')).toLowerCase();
        tooltip.innerHTML = '<span class="neo-manel-tooltip-copy"><b></b><span data-neo-tooltip-text></span></span>';
        var tooltipName = tooltip.querySelector('b');
        if (tooltipName) tooltipName.textContent = manelName;
        document.body.appendChild(tooltip);

        var tooltipText = tooltip.querySelector('[data-neo-tooltip-text]');
        var current = null;
        var showTimer = 0;
        var hideTimer = 0;
        var previousDescription = null;
        var typingTimer = 0;
        var typingToken = 0;

        function clean(value) {
            return String(value || '').replace(/\s+/g, ' ').trim().replace(/[→←↗⌄]+$/u, '').trim();
        }

        function quote(value) {
            var label = clean(value).replace(/^(abrir|ver|ir para|acessar)\s+/iu, '');
            return label ? ' “' + label.slice(0, 88) + '”' : '';
        }

        function personalityLine(message) {
            message = clean(message);
            if (!message) return '';
            if (message.indexOf('Sou o ') === 0 || message.indexOf('Sou a ') === 0) return message;
            return message;
        }

        function visibleLabel(element) {
            if (element.matches('label')) {
                var labelledControl = element.querySelector('input,select,textarea');
                var labelClone = element.cloneNode(true);
                labelClone.querySelectorAll('input,select,textarea,option').forEach(function (control) { control.remove(); });
                return clean(labelClone.textContent || labelledControl?.getAttribute('placeholder') || labelledControl?.getAttribute('aria-label'));
            }
            if (element.matches('input')) {
                var inputId = element.id && window.CSS && typeof window.CSS.escape === 'function' ? window.CSS.escape(element.id) : element.id;
                var label = inputId ? document.querySelector('label[for="' + inputId + '"]') : element.closest('label');
                return clean(label ? label.textContent : element.value);
            }
            if (element.matches('select')) {
                var selectId = element.id && window.CSS && typeof window.CSS.escape === 'function' ? window.CSS.escape(element.id) : element.id;
                var selectLabel = selectId ? document.querySelector('label[for="' + selectId + '"]') : element.closest('label');
                if (!selectLabel) return clean(element.getAttribute('aria-label') || 'uma opção');
                var clone = selectLabel.cloneNode(true);
                clone.querySelectorAll('select,option').forEach(function (control) { control.remove(); });
                return clean(clone.textContent || element.getAttribute('aria-label') || 'uma opção');
            }
            if (element.matches('textarea')) return clean(element.getAttribute('aria-label') || element.getAttribute('placeholder') || 'mensagem');
            if (element.matches('.subject')) return clean(element.querySelector('b')?.textContent);
            if (element.matches('.content-row-main')) return clean(element.querySelector('span')?.textContent);
            if (element.matches('.recent-item')) return clean(element.querySelector('b,strong,.recent-title')?.textContent || element.textContent);
            if (element.matches('.learning-week-folder')) return clean(element.querySelector('strong')?.textContent);
            if (element.matches('summary')) return clean(element.querySelector('b,h2,h3,strong')?.textContent || element.textContent);
            return clean(element.getAttribute('aria-label') || element.textContent || element.getAttribute('value'));
        }

        function routeDescription(element, label) {
            if (!element.matches('a[href]')) return '';
            var url;
            try { url = new URL(element.getAttribute('href'), window.location.href); } catch (error) { return ''; }
            if (url.origin !== window.location.origin) return 'Abre um endereço externo em outra página.';
            var page = url.pathname.split('/').pop() || 'index.php';
            if (element.matches('.content-row-main')) return 'Abre o livro' + quote(label) + ' para leitura.';
            if (element.matches('.subject')) return 'Abre a matéria' + quote(label) + ' e mostra seus livros.';
            if (element.matches('.recent-item')) return 'Retoma o conteúdo' + quote(label) + '.';
            var routes = {
                'index.php': 'Abre a página inicial e sua próxima atividade.',
                'materias.php': 'Abre suas matérias e livros.',
                'historico.php': 'Mostra seu histórico de atividades e resultados.',
                'loja.php': 'Abre a loja de itens visuais do perfil.',
                'aprendizado.php': 'Abre sua rotina, progresso, revisões e simulados.',
                'perfil.php': 'Abre seu perfil e suas conquistas.',
                'config.php': 'Abre as configurações da conta.',
                'estudos.php': 'Abre os estudos externos com o Manel.',
                'conteudos.php': 'Mostra os livros desta matéria.',
                'livro.php': 'Abre este livro para leitura.',
                'questoes.php': 'Abre as questões deste conteúdo.',
                'logout.php': 'Encerra sua sessão no NEO.'
            };
            return routes[page] || (label ? 'Abre' + quote(label) + '.' : 'Abre esta opção.');
        }

        function descriptionFor(element) {
            if (!element || element.matches('[data-manel-tip="off"]')) return '';
            var explicit = clean(element.getAttribute('data-manel-tip'));
            if (explicit) return explicit;
            var savedTitle = clean(element.getAttribute('data-neo-original-title'));
            var label = visibleLabel(element) || savedTitle;
            var normalized = clean(label).toLocaleLowerCase('pt-BR');

            if (element.matches('label') && element.querySelector('input[type="checkbox"],input[type="radio"]')) return 'Seleciona' + quote(label) + '.';
            if (element.matches('input[type="checkbox"],input[type="radio"]')) return 'Seleciona' + quote(label) + '.';
            if (element.matches('input[type="file"]')) return 'Escolhe uma imagem do seu dispositivo para' + quote(label || 'este campo') + '.';
            if (element.matches('input,textarea')) return 'Campo para' + quote(label || element.getAttribute('placeholder') || 'digitar sua resposta') + '.';
            if (element.matches('select')) return 'Escolhe uma opção para' + quote(label || 'este campo') + '.';
            if (element.matches('summary')) return 'Abre ou fecha os detalhes de' + quote(label) + '.';
            if (/apagar|excluir|remover|lixeira/.test(normalized)) return 'Apaga' + quote(label.replace(/^(apagar|excluir|remover)\s*/iu, '')) + ' depois da sua confirmação.';
            if (/cancelar/.test(normalized)) return 'Cancela esta ação e volta sem alterar nada.';
            if (/fechar/.test(normalized)) return 'Fecha esta janela.';
            if (/voltar|anterior/.test(normalized)) return 'Volta para a etapa anterior.';
            if (/adicionar matéria|criar matéria/.test(normalized)) return 'Abre a criação de uma nova matéria.';
            if (/gerar mais livros/.test(normalized)) return 'Cria a próxima sequência de livros desta matéria.';
            if (/pedir um conteúdo/.test(normalized)) return 'Cria um livro sobre o tema que você escolher.';
            if (/preparar meu simulado/.test(normalized)) return 'Monta um simulado com as opções selecionadas.';
            if (/salvar|confirmar|concluir|entrar|enviar|comprar/.test(normalized)) return (label || 'Confirma esta ação') + '.';

            var route = routeDescription(element, label);
            if (route) return route;
            if (element.matches('.learning-week-folder')) return clean(element.getAttribute('aria-label')) + '.';
            if (savedTitle && savedTitle !== label) return savedTitle + '.';
            return label ? 'Usa a opção' + quote(label) + '.' : '';
        }

        function prepare(element) {
            if (!element || !element.matches(selector)) return;
            var title = element.getAttribute('title');
            if (title !== null) {
                if (clean(title)) element.setAttribute('data-neo-original-title', clean(title));
                element.removeAttribute('title');
            }
        }

        function restoreDescription() {
            if (!current) return;
            if (previousDescription === null) current.removeAttribute('aria-describedby');
            else current.setAttribute('aria-describedby', previousDescription);
            previousDescription = null;
        }

        function positionTooltip() {
            if (!face || !face.isConnected) return;
            var margin = 12;
            var gap = 16;
            var minWidth = 178;
            var maxWidth = 330;
            var rect = face.getBoundingClientRect();
            var availableLeft = Math.max(0, rect.left - gap - margin);
            var availableRight = Math.max(0, window.innerWidth - rect.right - gap - margin);
            var availableBelow = Math.max(minWidth, window.innerWidth - margin * 2);
            var side = availableLeft >= minWidth || availableLeft >= availableRight ? 'left' : (availableRight >= minWidth ? 'right' : 'below');
            var allowedWidth = side === 'left' ? availableLeft : (side === 'right' ? availableRight : availableBelow);
            tooltip.classList.remove('is-below', 'is-left', 'is-right');
            tooltip.classList.add(side === 'below' ? 'is-below' : (side === 'right' ? 'is-right' : 'is-left'));
            tooltip.style.maxWidth = Math.max(minWidth, Math.min(maxWidth, allowedWidth)) + 'px';
            var box = tooltip.getBoundingClientRect();
            var left;
            var top;

            if (side === 'left') {
                left = rect.left - box.width - gap;
                top = rect.top + rect.height * 0.48 - box.height / 2;
            } else if (side === 'right') {
                left = rect.right + gap;
                top = rect.top + rect.height * 0.48 - box.height / 2;
            } else {
                left = rect.left + rect.width / 2 - box.width / 2;
                top = rect.bottom + gap;
            }

            left = Math.max(margin, Math.min(window.innerWidth - box.width - margin, left));
            top = Math.max(margin, Math.min(window.innerHeight - box.height - margin, top));

            var arrowY = Math.max(16, Math.min(box.height - 16, rect.top + rect.height * 0.48 - top));
            var arrowX = Math.max(16, Math.min(box.width - 16, rect.left + rect.width / 2 - left));
            tooltip.style.setProperty('--tooltip-arrow-y', Math.round(arrowY) + 'px');
            tooltip.style.setProperty('--tooltip-arrow-x', Math.round(arrowX) + 'px');
            tooltip.style.left = Math.round(left) + 'px';
            tooltip.style.top = Math.round(top) + 'px';
        }

        function typeTooltipText(message) {
            window.clearInterval(typingTimer);
            typingToken += 1;
            var token = typingToken;
            var index = 0;
            tooltipText.textContent = '';
            tooltip.classList.add('is-typing');
            typingTimer = window.setInterval(function () {
                if (token !== typingToken) {
                    window.clearInterval(typingTimer);
                    return;
                }
                index = Math.min(message.length, index + 3);
                tooltipText.textContent = message.slice(0, index);
                if (index >= message.length) {
                    window.clearInterval(typingTimer);
                    tooltip.classList.remove('is-typing');
                    document.dispatchEvent(new CustomEvent('neo:manel-face-state', { detail: { state: 'neutral' } }));
                }
            }, 10);
        }

        function show(element) {
            prepare(element);
            var message = personalityLine(descriptionFor(element));
            if (!message || !element.isConnected) return;
            restoreDescription();
            current = element;
            previousDescription = element.getAttribute('aria-describedby');
            element.setAttribute('aria-describedby', tooltip.id);
            window.clearInterval(typingTimer);
            typingToken += 1;
            tooltip.style.width = '';
            tooltipText.textContent = message;
            tooltip.hidden = false;
            tooltip.setAttribute('aria-hidden', 'false');
            tooltip.classList.remove('is-visible', 'is-typing');
            positionTooltip();
            tooltip.style.width = Math.ceil(tooltip.getBoundingClientRect().width) + 'px';
            typeTooltipText(message);
            window.requestAnimationFrame(function () {
                if (current === element) tooltip.classList.add('is-visible');
            });
            document.dispatchEvent(new CustomEvent('neo:manel-face-state', { detail: { state: 'speaking' } }));
        }

        function hide(immediate) {
            window.clearTimeout(showTimer);
            window.clearTimeout(hideTimer);
            if (!current && tooltip.hidden) return;
            var finish = function () {
                restoreDescription();
                current = null;
                tooltip.hidden = true;
                tooltip.setAttribute('aria-hidden', 'true');
                window.clearInterval(typingTimer);
                typingToken += 1;
                tooltipText.textContent = '';
                tooltip.style.width = '';
                tooltip.classList.remove('is-visible', 'is-below', 'is-left', 'is-right', 'is-typing');
                document.dispatchEvent(new CustomEvent('neo:manel-face-state', { detail: { state: 'neutral' } }));
            };
            tooltip.classList.remove('is-visible');
            if (immediate) finish();
            else hideTimer = window.setTimeout(finish, 130);
        }

        function findInteractive(node) {
            var element = node && node.closest ? node.closest(selector) : null;
            if (!element || element.closest('.neo-manel-tooltip')) return null;
            if (element.matches('[disabled],[aria-disabled="true"]') && !element.hasAttribute('data-manel-tip')) return null;
            if (document.querySelector('[data-manel-tour]:not([hidden])')) return null;
            return element;
        }

        document.querySelectorAll(selector).forEach(prepare);
        document.addEventListener('pointerover', function (event) {
            if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
            var element = findInteractive(event.target);
            if (!element || (event.relatedTarget && element.contains(event.relatedTarget))) return;
            window.clearTimeout(hideTimer);
            window.clearTimeout(showTimer);
            showTimer = window.setTimeout(function () { show(element); }, 320);
        });
        document.addEventListener('pointerout', function (event) {
            var element = findInteractive(event.target);
            if (!element || (event.relatedTarget && element.contains(event.relatedTarget))) return;
            hide(false);
        });
        document.addEventListener('focusin', function (event) {
            var element = findInteractive(event.target);
            if (!element) return;
            window.clearTimeout(showTimer);
            showTimer = window.setTimeout(function () { if (element.matches(':focus-visible')) show(element); }, 120);
        });
        document.addEventListener('focusout', function (event) {
            if (current === findInteractive(event.target)) hide(false);
        });
        document.addEventListener('pointerdown', function () { hide(true); }, true);
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape') hide(true); });
        window.addEventListener('scroll', function () { hide(true); }, { passive: true, capture: true });
        window.addEventListener('resize', function () { if (current) positionTooltip(); }, { passive: true });
    })();

    (function setupCompanionFace() {
        var face = document.querySelector('[data-neo-companion]');
        if (!face) return;
        var mouth = face.querySelector('.neo-companion-mouth');
        var neutralMouth = mouth ? mouth.getAttribute('d') : '';
        var mouthShapes = {
            neutral: neutralMouth || 'M 128 150 L 172 150',
            happy: 'M 128 147 Q 150 166 172 147',
            surprised: 'M 142 151 Q 150 140 158 151 Q 150 162 142 151',
            sleeping: 'M 132 154 L 168 154',
            talkOpen: 'M 135 149 Q 150 166 165 149 Q 150 181 135 149',
            talkWide: 'M 126 146 Q 150 174 174 146',
            talkSmall: 'M 137 151 Q 150 160 163 151'
        };
        var targetX = 0;
        var targetY = 0;
        var currentX = 0;
        var currentY = 0;
        var velocityX = 0;
        var velocityY = 0;
        var pointerX = null;
        var pointerY = null;
        var pointerActive = false;
        var curiousX = 0;
        var curiousY = 0;
        var state = 'neutral';
        var moodTimer = null;
        var blinkTimer = null;
        var expressionTimer = null;
        var sleepTimer = null;
        var wakeTimer = null;
        var speakingTimer = null;
        var reactingTimer = null;
        var tooltipSpeechTimer = null;
        var animationFrame = null;
        var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
        var lastActivityAt = Date.now();
        var lastPointerAt = 0;
        var lastFrameAt = 0;
        var lastSleepScheduleAt = 0;
        var lastActivitySignalAt = 0;
        var lastPointerSignalAt = 0;
        var faceRect = null;
        var sleepDelay = randomBetween(36000, 56000);

        function clamp(value, min, max) {
            return Math.max(min, Math.min(max, value));
        }

        function randomBetween(min, max) {
            return min + Math.random() * (max - min);
        }

        function setMouth(shape) {
            if (mouth && mouthShapes[shape]) {
                mouth.setAttribute('d', mouthShapes[shape]);
            }
        }
        function stopTooltipSpeech() {
            if (tooltipSpeechTimer) window.clearInterval(tooltipSpeechTimer);
            tooltipSpeechTimer = null;
            face.classList.remove('is-tooltip-speaking');
            setMouth('neutral');
        }

        function startTooltipSpeech() {
            if (!mouth) return;
            if (tooltipSpeechTimer) window.clearInterval(tooltipSpeechTimer);
            face.classList.add('is-tooltip-speaking');
            var shapes = ['talkOpen', 'talkSmall', 'talkWide', 'talkSmall'];
            var index = 0;
            setMouth(shapes[index]);
            tooltipSpeechTimer = window.setInterval(function () {
                index = (index + 1) % shapes.length;
                setMouth(shapes[index]);
            }, 130);
        }

        function clearStateClasses() {
            face.classList.remove(
                'is-happy',
                'is-curious',
                'is-surprised',
                'is-blinking',
                'is-sleepy',
                'is-sleeping',
                'is-waking',
                'is-reacting'
            );
        }

        function canPlayExpression() {
            return state === 'neutral' && !face.classList.contains('is-study-talking') && !document.body.classList.contains('neo-face-docking');
        }

        function finishExpression() {
            clearStateClasses();
            setMouth('neutral');
            state = 'neutral';
            expressionTimer = null;
            startEyeAnimation();
        }

        function playExpression(nextState, duration) {
            if (!canPlayExpression()) return false;
            if (expressionTimer) window.clearTimeout(expressionTimer);
            state = nextState;
            face.classList.add('is-' + nextState);
            if (nextState === 'happy') setMouth('happy');
            if (nextState === 'surprised') setMouth('surprised');
            if (nextState === 'curious') {
                curiousX = randomBetween(-8, 8);
                curiousY = randomBetween(-5, 5);
            }
            expressionTimer = window.setTimeout(finishExpression, duration);
            startEyeAnimation();
            return true;
        }

        function playBlink(longBlink) {
            if (!canPlayExpression()) return false;
            state = 'blink';
            face.classList.add('is-blinking');
            expressionTimer = window.setTimeout(function () {
                face.classList.remove('is-blinking');
                state = 'neutral';
                expressionTimer = null;
                startEyeAnimation();
            }, longBlink ? 260 : 130);
            startEyeAnimation();
            return true;
        }

        function scheduleBlink() {
            if (blinkTimer) window.clearTimeout(blinkTimer);
            blinkTimer = window.setTimeout(function () {
                if (document.visibilityState !== 'hidden' && canPlayExpression()) {
                    playBlink(false);
                }
                scheduleBlink();
            }, randomBetween(2800, 7600));
        }

        function scheduleMood() {
            if (moodTimer) window.clearTimeout(moodTimer);
            moodTimer = window.setTimeout(function () {
                if (document.visibilityState !== 'hidden' && canPlayExpression()) {
                    var roll = Math.random();
                    if (roll < .45) {
                        playExpression('curious', randomBetween(680, 980));
                    } else if (roll < .9) {
                        playExpression('happy', randomBetween(720, 1120));
                    } else {
                        playExpression('surprised', randomBetween(420, 650));
                    }
                }
                scheduleMood();
            }, randomBetween(8500, 19000));
        }

        function scheduleSleepCheck() {
            if (sleepTimer) window.clearTimeout(sleepTimer);
            sleepTimer = window.setTimeout(function () {
                if (Date.now() - lastActivityAt >= sleepDelay) {
                    beginSleepSequence();
                } else {
                    scheduleSleepCheck();
                }
            }, sleepDelay);
        }

        function beginSleepSequence() {
            if (state === 'sleeping' || state === 'sleepy' || state === 'waking') return;
            if (expressionTimer) window.clearTimeout(expressionTimer);
            state = 'sleepy';
            clearStateClasses();
            face.classList.add('is-sleepy');
            setMouth('sleeping');
            pointerActive = false;
            targetX = 0;
            targetY = 0;
            expressionTimer = window.setTimeout(function () {
                face.classList.remove('is-sleepy');
                face.classList.add('is-sleeping');
                state = 'sleeping';
                expressionTimer = null;
                startEyeAnimation();
            }, 1450);
            startEyeAnimation();
        }

        function wakeFace() {
            if (state !== 'sleeping' && state !== 'sleepy') return;
            if (expressionTimer) window.clearTimeout(expressionTimer);
            if (wakeTimer) window.clearTimeout(wakeTimer);
            clearStateClasses();
            face.classList.add('is-waking');
            setMouth('neutral');
            state = 'waking';
            wakeTimer = window.setTimeout(function () {
                face.classList.remove('is-waking');
                state = 'neutral';
                wakeTimer = null;
                startEyeAnimation();
            }, 620);
            startEyeAnimation();
        }

        function registerActivity(event) {
            if (event && event.isTrusted === false) return;
            lastActivityAt = Date.now();
            sleepDelay = randomBetween(36000, 56000);
            wakeFace();
            if (lastActivityAt - lastSleepScheduleAt > 1000) {
                lastSleepScheduleAt = lastActivityAt;
                scheduleSleepCheck();
            }
        }

        function refreshFaceRect() {
            faceRect = face.getBoundingClientRect();
            return faceRect;
        }

        function updateTarget(timestamp) {
            var rect = faceRect || refreshFaceRect();
            var centerX = rect.left + rect.width / 2;
            var centerY = rect.top + rect.height / 2;
            var wantedX = 0;
            var wantedY = 0;

            if (state === 'curious') {
                wantedX = curiousX;
                wantedY = curiousY;
            } else if (state === 'sleepy' || state === 'sleeping') {
                wantedX = 0;
                wantedY = 2;
            } else if (pointerActive && pointerX !== null && Date.now() - lastPointerAt < 4200) {
                var dx = (pointerX - centerX) / Math.max(34, rect.width * .55);
                var dy = (pointerY - centerY) / Math.max(34, rect.height * .72);
                var strength = Math.min(1, Math.hypot(dx, dy) / 8);
                wantedX = clamp(dx * (5 + strength * 4), -9, 9);
                wantedY = clamp(dy * (3.8 + strength * 2.2), -6, 6);
                if (state === 'happy') wantedY -= 1.7;
                if (state === 'surprised') wantedY -= .8;
            } else {
                wantedX = 0;
                wantedY = state === 'happy' ? -1.6 : 0;
            }

            targetX = wantedX;
            targetY = wantedY;
        }

        function shouldKeepEyeAnimation() {
            if (state !== 'neutral') return true;
            if (face.classList.contains('is-study-talking') || face.classList.contains('is-thinking')) return true;
            if (pointerActive && Date.now() - lastPointerAt < 1300) return true;
            return Math.abs(targetX - currentX) > .04 || Math.abs(targetY - currentY) > .04 || Math.abs(velocityX) > .03 || Math.abs(velocityY) > .03;
        }

        function startEyeAnimation() {
            if ((reducedMotion && reducedMotion.matches) || animationFrame || document.visibilityState === 'hidden') return;
            lastFrameAt = 0;
            animationFrame = window.requestAnimationFrame(animateEyes);
        }

        function animateEyes(timestamp) {
            if (document.visibilityState === 'hidden' || document.body.classList.contains('neo-face-docking')) {
                animationFrame = null;
                return;
            }
            if (lastFrameAt && timestamp - lastFrameAt < 48) {
                animationFrame = window.requestAnimationFrame(animateEyes);
                return;
            }
            if (!lastFrameAt) lastFrameAt = timestamp;
            var delta = Math.min(32, timestamp - lastFrameAt);
            lastFrameAt = timestamp;

            updateTarget(timestamp);
            var stiffness = state === 'sleepy' || state === 'sleeping' ? .035 : .075;
            var damping = state === 'sleepy' || state === 'sleeping' ? .78 : .72;
            velocityX = (velocityX + (targetX - currentX) * stiffness) * damping;
            velocityY = (velocityY + (targetY - currentY) * stiffness) * damping;
            currentX += velocityX * (delta / 16.67);
            currentY += velocityY * (delta / 16.67);
            face.style.setProperty('--eye-x', currentX.toFixed(2) + 'px');
            face.style.setProperty('--eye-y', currentY.toFixed(2) + 'px');
            if (!shouldKeepEyeAnimation()) {
                animationFrame = null;
                lastFrameAt = 0;
                return;
            }
            animationFrame = window.requestAnimationFrame(animateEyes);
        }

        document.addEventListener('mousemove', function (event) {
            if (event.timeStamp - lastPointerSignalAt < 120) return;
            lastPointerSignalAt = event.timeStamp;
            pointerX = event.clientX;
            pointerY = event.clientY;
            pointerActive = true;
            lastPointerAt = Date.now();
            startEyeAnimation();
            if (event.timeStamp - lastActivitySignalAt > 400) {
                lastActivitySignalAt = event.timeStamp;
                registerActivity(event);
            }
        }, { passive: true });

        document.addEventListener('mouseleave', function () {
            pointerActive = false;
            registerActivity();
        });

        window.addEventListener('mouseout', function (event) {
            if (!event.relatedTarget && !event.toElement) {
                pointerActive = false;
                registerActivity(event);
            }
        });

        ['click', 'keydown', 'scroll', 'touchstart', 'pointerdown'].forEach(function (eventName) {
            document.addEventListener(eventName, registerActivity, { passive: true });
        });

        face.addEventListener('click', function () {
            if (face.classList.contains('is-study-talking')) return;
            if (state === 'sleeping' || state === 'sleepy') {
                wakeFace();
                return;
            }
            if (state !== 'neutral' && state !== 'blink') return;
            if (expressionTimer) window.clearTimeout(expressionTimer);
            state = 'happy';
            clearStateClasses();
            face.classList.add('is-speaking', 'is-reacting');
            setMouth('happy');
            if (speakingTimer) window.clearTimeout(speakingTimer);
            if (reactingTimer) window.clearTimeout(reactingTimer);
            reactingTimer = window.setTimeout(function () {
                face.classList.remove('is-reacting');
                state = 'neutral';
                setMouth('neutral');
                startEyeAnimation();
            }, 520);
            speakingTimer = window.setTimeout(function () {
                face.classList.remove('is-speaking');
            }, 1400);
            startEyeAnimation();
        });

        document.addEventListener('neo:face-arrived', function () {
            refreshFaceRect();
            if (expressionTimer) window.clearTimeout(expressionTimer);
            clearStateClasses();
            state = 'happy';
            face.classList.add('is-reacting');
            if (mouth) mouth.setAttribute('d', 'M 128 150 Q 150 172 172 150');
            expressionTimer = window.setTimeout(finishExpression, 520);
            startEyeAnimation();
        });

        document.addEventListener('neo:manel-face-state', function (event) {
            var next = event.detail && event.detail.state ? event.detail.state : 'neutral';
            var wasTalking = face.classList.contains('is-study-talking');
            face.classList.toggle('is-study-talking', next === 'speaking');
            if (next === 'speaking') {
                window.clearTimeout(expressionTimer);
                window.clearTimeout(reactingTimer);
                window.clearTimeout(wakeTimer);
                lastActivityAt = Date.now();
                clearStateClasses();
                state = 'neutral';
                startTooltipSpeech();
            }
            if (wasTalking && next !== 'speaking') stopTooltipSpeech();
            face.classList.toggle('is-thinking', next === 'thinking');
            face.classList.toggle('is-error', next === 'error');
            if (next === 'success') {
                playExpression('happy', 900);
            }
            if (next === 'listening') {
                playExpression('curious', 760);
            }
            if (next === 'error') {
                playExpression('surprised', 720);
            }
            if (next === 'speaking' || next === 'thinking') startEyeAnimation();
        });

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState !== 'hidden' && !animationFrame) {
                refreshFaceRect();
                startEyeAnimation();
            }
        });

        window.addEventListener('resize', refreshFaceRect, { passive: true });
        if ('ResizeObserver' in window) {
            new ResizeObserver(refreshFaceRect).observe(face);
        }

        scheduleBlink();
        scheduleMood();
        scheduleSleepCheck();
        refreshFaceRect();
        startEyeAnimation();
    })();


})();







