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

    (function setupCompanionFace() {
        var face = document.querySelector('[data-neo-companion]');
        if (!face) return;
        var mouth = face.querySelector('.neo-companion-mouth');
        var neutralMouth = mouth ? mouth.getAttribute('d') : '';
        var mouthShapes = {
            neutral: neutralMouth || 'M 128 150 L 172 150',
            happy: 'M 128 147 Q 150 166 172 147',
            surprised: 'M 142 151 Q 150 140 158 151 Q 150 162 142 151',
            sleeping: 'M 132 154 L 168 154'
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
        var animationFrame = null;
        var lastActivityAt = Date.now();
        var lastPointerAt = 0;
        var lastFrameAt = 0;
        var lastSleepScheduleAt = 0;
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
            }, longBlink ? 260 : 130);
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
            }, 1450);
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
            }, 620);
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

        function updateTarget(timestamp) {
            var rect = face.getBoundingClientRect();
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
                var drift = timestamp / 1000;
                wantedX = Math.sin(drift * .85) * 1.45 + Math.sin(drift * .37) * .55;
                wantedY = Math.cos(drift * .58) * .85;
                if (state === 'happy') wantedY -= 1.6;
            }

            targetX = wantedX;
            targetY = wantedY;
        }

        function animateEyes(timestamp) {
            if (document.visibilityState === 'hidden' || document.body.classList.contains('neo-face-docking')) {
                animationFrame = null;
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
            animationFrame = window.requestAnimationFrame(animateEyes);
        }

        document.addEventListener('mousemove', function (event) {
            pointerX = event.clientX;
            pointerY = event.clientY;
            pointerActive = true;
            lastPointerAt = Date.now();
            registerActivity(event);
        });

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
            document.dispatchEvent(new CustomEvent('neo:manel-toggle'));
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
            }, 520);
            speakingTimer = window.setTimeout(function () {
                face.classList.remove('is-speaking');
            }, 1400);
        });

        document.addEventListener('neo:face-arrived', function () {
            if (expressionTimer) window.clearTimeout(expressionTimer);
            clearStateClasses();
            state = 'happy';
            face.classList.add('is-reacting');
            if (mouth) mouth.setAttribute('d', 'M 128 150 Q 150 172 172 150');
            expressionTimer = window.setTimeout(finishExpression, 520);
            if (!animationFrame) {
                lastFrameAt = 0;
                animationFrame = window.requestAnimationFrame(animateEyes);
            }
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
            }
            if (wasTalking && next !== 'speaking') setMouth('neutral');
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
        });

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState !== 'hidden' && !animationFrame) {
                lastFrameAt = 0;
                animationFrame = window.requestAnimationFrame(animateEyes);
            }
        });

        scheduleBlink();
        scheduleMood();
        scheduleSleepCheck();
        animationFrame = window.requestAnimationFrame(animateEyes);
    })();

    (function setupManelAssistant() {
        var panel = document.querySelector('[data-manel-panel]');
        var face = document.querySelector('[data-neo-companion]');
        if (!panel || !face) return;
        if (face.closest('[data-study-page]')) return;

        var messagesEl = panel.querySelector('[data-manel-messages]');
        var suggestionsEl = panel.querySelector('[data-manel-suggestions]');
        var form = panel.querySelector('[data-manel-form]');
        var input = panel.querySelector('[data-manel-input]');
        var sendButton = panel.querySelector('[data-manel-send]');
        var closeButton = panel.querySelector('[data-manel-close]');
        var newButton = panel.querySelector('[data-manel-new]');
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var userId = panel.dataset.userId;
        if (!/^[1-9]\d*$/.test(userId || '')) return;
        // Legacy shared history has no reliable owner; never import it into an account.
        var storageKey = 'neo_manel_thread_v1_user_' + userId;
        var openedKey = 'neo_manel_opened_v1_user_' + userId;
        var controller = null;
        var typingTimer = null;
        var typingCancelled = false;
        var isBusy = false;
        var messages = loadMessages();

        function dispatchFaceState(state) {
            document.dispatchEvent(new CustomEvent('neo:manel-face-state', { detail: { state: state } }));
        }

        function csrfToken() {
            return csrfMeta ? csrfMeta.getAttribute('content') || '' : '';
        }

        function loadMessages() {
            try {
                var parsed = JSON.parse(localStorage.getItem(storageKey) || '[]');
                return Array.isArray(parsed) ? parsed.filter(function (item) {
                    return item && (item.role === 'user' || item.role === 'assistant') && typeof item.content === 'string';
                }).slice(-40) : [];
            } catch (e) {
                return [];
            }
        }

        function saveMessages() {
            try {
                localStorage.setItem(storageKey, JSON.stringify(messages.slice(-40)));
            } catch (e) {}
        }

        function isOpen() {
            return !panel.hidden && panel.classList.contains('is-open');
        }

        function openPanel() {
            panel.hidden = false;
            panel.setAttribute('aria-hidden', 'false');
            face.setAttribute('aria-expanded', 'true');
            window.requestAnimationFrame(function () {
                panel.classList.add('is-open');
                if (input) input.focus();
            });
            try {
                localStorage.setItem(openedKey, '1');
            } catch (e) {}
            dispatchFaceState('listening');
            render();
        }

        function closePanel() {
            panel.classList.remove('is-open');
            panel.setAttribute('aria-hidden', 'true');
            face.setAttribute('aria-expanded', 'false');
            window.setTimeout(function () {
                if (!panel.classList.contains('is-open')) panel.hidden = true;
            }, 180);
            try {
                localStorage.setItem(openedKey, '0');
            } catch (e) {}
        }

        function togglePanel() {
            if (isOpen()) {
                closePanel();
            } else {
                openPanel();
            }
        }

        function contextFromPage() {
            var main = document.querySelector('main');
            var title = document.querySelector('.page-title, .page-title-image, h1');
            var lessonTitle = document.querySelector('.lesson-title-panel h1');
            var subjectTitle = document.querySelector('.content-summary h1');
            var questionTitle = document.querySelector('.question-card h2');
            var params = new URLSearchParams(window.location.search);
            var subjectLink = document.querySelector('a[href*="conteudos.php?materia_id="]');
            var subjectId = params.get('materia_id') || '';
            var path = window.location.pathname.split('/').pop() || 'index.php';
            var text = '';

            if (!subjectId && subjectLink) {
                try {
                    subjectId = new URL(subjectLink.getAttribute('href'), window.location.href).searchParams.get('materia_id') || '';
                } catch (e) {}
            }

            if (document.querySelector('.reader article')) {
                text = document.querySelector('.reader article').innerText || '';
            } else if (main) {
                text = main.innerText || '';
            }

            return {
                page: path.replace('.php', ''),
                title: title ? (title.getAttribute('alt') || title.textContent || '').trim() : document.title,
                url: window.location.pathname + window.location.search,
                subject: subjectTitle ? subjectTitle.textContent.trim() : '',
                subjectId: subjectId,
                bookTitle: lessonTitle ? lessonTitle.textContent.trim() : '',
                bookId: params.get('conteudo_id') || '',
                question: questionTitle ? questionTitle.textContent.trim() : '',
                visibleText: text.replace(/\s+/g, ' ').trim().slice(0, 7000),
                availableActions: availableActions(path, params)
            };
        }

        function availableActions(path, params) {
            var actions = ['navegar', 'conversar', 'resumir', 'explicar'];
            if (params.get('conteudo_id')) actions.push('gerar_questoes');
            if (params.get('materia_id') || document.querySelector('.content-summary h1')) {
                actions.push('gerar_livros', 'pedir_conteudo');
            }
            if (path.indexOf('questoes.php') !== -1) actions.push('dar_dica', 'explicar_erro');
            return actions;
        }

        function escapeHtml(text) {
            return String(text).replace(/[&<>"']/g, function (char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                }[char];
            });
        }

        function inlineMarkdown(text) {
            var html = escapeHtml(text);
            html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
            html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
            html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
            return html;
        }

        function renderMarkdown(text) {
            var source = String(text || '').replace(/\r\n/g, '\n');
            var blocks = source.split(/(```[\s\S]*?```)/g);
            var html = '';

            blocks.forEach(function (block) {
                if (!block) return;
                if (block.indexOf('```') === 0) {
                    var code = block.replace(/^```([a-z0-9_-]+)?\n?/i, '').replace(/```$/i, '');
                    var langMatch = block.match(/^```([a-z0-9_-]+)/i);
                    var lang = langMatch ? langMatch[1] : 'código';
                    html += '<div class="manel-code"><span>' + escapeHtml(lang) + '</span><button type="button" data-copy-code>Copiar</button><pre><code>' + escapeHtml(code.trim()) + '</code></pre></div>';
                    return;
                }

                var lines = block.split('\n');
                var inList = false;
                var inTable = false;
                lines.forEach(function (line) {
                    var trimmed = line.trim();
                    if (trimmed === '') {
                        if (inList) {
                            html += '</ul>';
                            inList = false;
                        }
                        if (inTable) {
                            html += '</tbody></table>';
                            inTable = false;
                        }
                        return;
                    }

                    if (/^\|.+\|$/.test(trimmed)) {
                        if (!inTable) {
                            if (inList) {
                                html += '</ul>';
                                inList = false;
                            }
                            html += '<table><tbody>';
                            inTable = true;
                        }
                        if (/^\|\s*:?-+:?\s*(\|\s*:?-+:?\s*)+\|$/.test(trimmed)) return;
                        html += '<tr>' + trimmed.slice(1, -1).split('|').map(function (cell) {
                            return '<td>' + inlineMarkdown(cell.trim()) + '</td>';
                        }).join('') + '</tr>';
                        return;
                    } else if (inTable) {
                        html += '</tbody></table>';
                        inTable = false;
                    }

                    if (/^#{1,3}\s+/.test(trimmed)) {
                        if (inList) {
                            html += '</ul>';
                            inList = false;
                        }
                        html += '<h4>' + inlineMarkdown(trimmed.replace(/^#{1,3}\s+/, '')) + '</h4>';
                        return;
                    }

                    if (/^[-*]\s+/.test(trimmed)) {
                        if (!inList) {
                            html += '<ul>';
                            inList = true;
                        }
                        html += '<li>' + inlineMarkdown(trimmed.replace(/^[-*]\s+/, '')) + '</li>';
                        return;
                    }

                    if (inList) {
                        html += '</ul>';
                        inList = false;
                    }
                    html += '<p>' + inlineMarkdown(trimmed) + '</p>';
                });

                if (inList) html += '</ul>';
                if (inTable) html += '</tbody></table>';
            });

            return html;
        }

        function addCopyButtons(scope) {
            scope.querySelectorAll('[data-copy-code]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var code = button.parentElement.querySelector('code');
                    if (!code) return;
                    navigator.clipboard.writeText(code.textContent || '').then(function () {
                        button.textContent = 'Copiado';
                        window.setTimeout(function () { button.textContent = 'Copiar'; }, 1200);
                    }).catch(function () {});
                });
            });
        }

        function appendMessage(role, content, pending) {
            var item = document.createElement('article');
            item.className = 'manel-message manel-message-' + role + (pending ? ' is-pending' : '');
            var bubble = document.createElement('div');
            bubble.className = 'manel-message-bubble';
            bubble.innerHTML = pending ? '<span class="manel-typing"><i></i><i></i><i></i></span>' : renderMarkdown(content);
            item.appendChild(bubble);
            messagesEl.appendChild(item);
            messagesEl.scrollTop = messagesEl.scrollHeight;
            addCopyButtons(item);
            return bubble;
        }

        function renderSuggestions(values) {
            suggestionsEl.innerHTML = '';
            panel.classList.remove('is-suggesting');
            void panel.offsetWidth;
            panel.classList.add('is-suggesting');
            (values && values.length ? values : defaultSuggestions()).slice(0, 6).forEach(function (text, index) {
                var button = document.createElement('button');
                button.type = 'button';
                button.textContent = text;
                button.style.setProperty('--suggestion-index', index);
                button.addEventListener('click', function () {
                    input.value = text;
                    autoResize();
                    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                });
                suggestionsEl.appendChild(button);
            });
        }

        function defaultSuggestions() {
            var ctx = contextFromPage();
            if (ctx.bookId) return ['Resumir esse livro', 'Criar questões', 'Flashcards desse livro', 'Revisão de 5 minutos', 'Explicar de forma simples', 'Me testar'];
            if (ctx.subjectId) return ['Gerar mais livros', 'Pedir um conteúdo', 'Ordem de estudo', 'Revisão da matéria', 'O que estudar agora?', 'Voltar para matérias'];
            return ['O que estudar agora?', 'Abrir último conteúdo', 'Abrir matérias', 'Ideias de revisão', 'Plano da semana', 'Me explica um tema'];
        }

        function render() {
            messagesEl.innerHTML = '';
            if (!messages.length) {
                appendMessage('assistant', 'E aí. Sou o **Manel**. Posso te ajudar a estudar, encontrar conteúdos e fazer algumas coisas pelo site. Tenta pedir algo.');
            } else {
                messages.forEach(function (message) {
                    appendMessage(message.role, message.content);
                });
            }
            renderSuggestions(defaultSuggestions());
        }

        function setBusy(active) {
            isBusy = active;
            panel.classList.toggle('is-busy', active);
            var tutorialButton = panel.querySelector('[data-manel-tour-start]');
            if (tutorialButton) tutorialButton.disabled = active;
            sendButton.setAttribute('aria-label', active ? 'Cancelar' : 'Enviar');
            dispatchFaceState(active ? 'thinking' : 'neutral');
        }

        function typeAssistantText(bubble, text, done) {
            var index = 0;
            typingCancelled = false;
            window.clearInterval(typingTimer);
            typingTimer = window.setInterval(function () {
                if (typingCancelled) {
                    window.clearInterval(typingTimer);
                    done(false);
                    return;
                }
                index = Math.min(text.length, index + Math.max(2, Math.ceil(text.length / 140)));
                bubble.innerHTML = renderMarkdown(text.slice(0, index));
                messagesEl.scrollTop = messagesEl.scrollHeight;
                if (index >= text.length) {
                    window.clearInterval(typingTimer);
                    addCopyButtons(bubble);
                    done(true);
                }
            }, 18);
        }

        function executeTool(tool) {
            if (!tool || !tool.name) return;
            if (tool.name === 'navigate' && tool.url) {
                window.setTimeout(function () { window.location.href = tool.url; }, 520);
                return;
            }
            if (tool.name === 'browser_back') {
                window.setTimeout(function () { window.history.back(); }, 420);
                return;
            }
            if (tool.name === 'submit_post' && tool.url && tool.fields) {
                window.setTimeout(function () {
                    if (window.NeoAILoader) window.NeoAILoader.show(tool.message || 'Preparando');
                    var postForm = document.createElement('form');
                    postForm.method = 'post';
                    postForm.action = tool.url;
                    postForm.hidden = true;
                    Object.keys(tool.fields).forEach(function (name) {
                        var hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = name;
                        hidden.value = tool.fields[name];
                        postForm.appendChild(hidden);
                    });
                    document.body.appendChild(postForm);
                    postForm.submit();
                }, 680);
            }
        }

        function sendMessage(text) {
            if (!text || isBusy) return;
            messages.push({ role: 'user', content: text });
            saveMessages();
            appendMessage('user', text);
            input.value = '';
            autoResize();
            renderSuggestions([]);
            var pendingBubble = appendMessage('assistant', '', true);
            setBusy(true);

            controller = new AbortController();
            fetch('manel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken()
                },
                credentials: 'same-origin',
                signal: controller.signal,
                body: JSON.stringify({
                    csrf_token: csrfToken(),
                    message: text,
                    messages: messages.slice(-14),
                    context: contextFromPage()
                })
            }).then(function (response) {
                return response.json().catch(function () {
                    throw new Error('invalid-json');
                }).then(function (data) {
                    if (!response.ok) throw data;
                    return data;
                });
            }).then(function (data) {
                var reply = data.reply || 'Pronto.';
                pendingBubble.parentElement.classList.remove('is-pending');
                typeAssistantText(pendingBubble, reply, function (completed) {
                    setBusy(false);
                    renderSuggestions(data.suggestions || defaultSuggestions());
                    if (!completed) return;
                    messages.push({ role: 'assistant', content: reply });
                    saveMessages();
                    dispatchFaceState(data.error ? 'error' : 'success');
                    executeTool(data.tool);
                });
            }).catch(function (error) {
                if (error && error.name === 'AbortError') {
                    pendingBubble.innerHTML = renderMarkdown('Parei por aqui.');
                } else {
                    pendingBubble.innerHTML = renderMarkdown((error && error.reply) || 'Não consegui responder agora. Tenta novamente em alguns segundos.');
                    dispatchFaceState('error');
                }
                pendingBubble.parentElement.classList.remove('is-pending');
                setBusy(false);
                renderSuggestions(defaultSuggestions());
            });
        }

        function cancelCurrent() {
            typingCancelled = true;
            if (controller) controller.abort();
            setBusy(false);
        }

        function autoResize() {
            if (!input) return;
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 150) + 'px';
        }

        document.addEventListener('neo:manel-toggle', togglePanel);
        document.addEventListener('neo:manel-close', closePanel);
        if (closeButton) closeButton.addEventListener('click', closePanel);
        if (newButton) {
            newButton.addEventListener('click', function () {
                if (isBusy) cancelCurrent();
                messages = [];
                saveMessages();
                render();
                dispatchFaceState('success');
            });
        }
        if (input) {
            input.addEventListener('input', autoResize);
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                }
            });
        }
        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (isBusy) {
                    cancelCurrent();
                    return;
                }
                sendMessage((input.value || '').trim());
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && isOpen()) closePanel();
        });

        render();
        try {
            if (localStorage.getItem(openedKey) === '1' && !document.body.classList.contains('neo-interface-locked')) openPanel();
        } catch (e) {}
    })();

})();
