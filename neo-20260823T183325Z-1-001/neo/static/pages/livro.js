(function () {
    'use strict';

    function initBook(reader) {
        var pages = Array.prototype.slice.call(reader.querySelectorAll('[data-book-page]'));
        var previous = reader.querySelector('[data-book-prev]');
        var next = reader.querySelector('[data-book-next]');
        var currentLabel = reader.querySelector('[data-book-current]');
        var progressBar = reader.querySelector('[data-book-progress]');
        var stage = reader.querySelector('[data-book-stage]');
        var spread = reader.querySelector('[data-book-spread]');
        var left = reader.querySelector('[data-book-left]');
        var right = reader.querySelector('[data-book-right]');
        var canvas = reader.querySelector('[data-book-canvas]');
        var context = canvas && canvas.getContext('2d', { alpha: true });
        var spreadStart = 0;
        var turning = false;
        var touchStartX = null;
        var animationFrame = 0;
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var compact = window.matchMedia('(max-width: 720px)').matches;
        var pageStep = compact ? 1 : 2;
        var duration = 760;
        var resizeTimer = 0;
        var canvasSize = null;
        var pageMarkup = pages.map(function (page) { return page.innerHTML; });
        var pageTemplates = pageMarkup.map(function (markup) {
            var template = document.createElement('template');
            template.innerHTML = markup;
            return template.content;
        });

        if (!pages.length || !previous || !next || !stage || !spread || !left || !right || !canvas || !context) return;

        function endPageMarkup() {
            return '<div class="book-end-page" aria-label="Fim do livro">' +
                '<span class="book-end-symbol" aria-hidden="true"><i></i><i></i><i></i></span>' +
                '<strong>Fim deste livro</strong><span>Você chegou ao último tópico.</span></div>';
        }

        function renderPage(slot, index) {
            if (index >= 0 && index < pages.length) {
                if (slot.dataset.visiblePage === String(index)) return;
                slot.replaceChildren(pageTemplates[index].cloneNode(true));
                slot.dataset.visiblePage = String(index);
                slot.setAttribute('aria-label', 'Página ' + (index + 1) + ' de ' + pages.length);
                slot.classList.remove('is-end-page');
                return;
            }
            if (slot.classList.contains('is-end-page')) return;
            slot.innerHTML = endPageMarkup();
            slot.dataset.visiblePage = '';
            slot.setAttribute('aria-label', 'Fim do livro');
            slot.classList.add('is-end-page');
        }

        function updateControls() {
            var lastVisible = Math.min(spreadStart + pageStep, pages.length);
            previous.disabled = spreadStart === 0 || turning;
            next.disabled = spreadStart + pageStep >= pages.length || turning;
            currentLabel.textContent = lastVisible > spreadStart + 1
                ? (spreadStart + 1) + '–' + lastVisible
                : String(spreadStart + 1);
            progressBar.style.width = ((lastVisible / pages.length) * 100) + '%';
        }

        function renderSpread(start) {
            if (compact) {
                left.innerHTML = '';
                left.setAttribute('aria-hidden', 'true');
                renderPage(right, start);
                return;
            }
            left.removeAttribute('aria-hidden');
            renderPage(left, start);
            renderPage(right, start + 1);
        }

        function createPageCover(slot, side) {
            var cover = slot.cloneNode(true);
            cover.removeAttribute('data-book-left');
            cover.removeAttribute('data-book-right');
            cover.removeAttribute('data-visible-page');
            cover.removeAttribute('aria-label');
            cover.setAttribute('aria-hidden', 'true');
            cover.classList.add('book-transition-cover', 'book-transition-cover-' + side);
            return cover;
        }

        function createTurningLeaf(direction, target) {
            var backSide = compact ? 'right' : (direction === 'next' ? 'left' : 'right');
            var frontSlot = direction === 'next' || compact ? right : left;
            var backIndex = compact ? target : (direction === 'next' ? target : target + 1);
            var leaf = document.createElement('div');

            leaf.className = 'book-turn-leaf book-turn-leaf-' + direction +
                (compact ? ' is-compact' : '') +
                (!compact && direction === 'previous' ? ' is-single-page' : '');
            leaf.setAttribute('aria-hidden', 'true');

            if (compact || direction === 'previous') {
                var front = frontSlot.cloneNode(true);
                var back = document.createElement('section');
                front.removeAttribute('data-book-left');
                front.removeAttribute('data-book-right');
                front.removeAttribute('data-visible-page');
                front.removeAttribute('aria-label');
                front.classList.add('book-turn-face', 'book-turn-face-front');
                back.className = 'book-sheet book-sheet-' + backSide + ' book-turn-face book-turn-face-back';
                renderPage(back, backIndex);
                back.removeAttribute('data-visible-page');
                back.removeAttribute('aria-label');
                back.setAttribute('aria-hidden', 'true');
                leaf.appendChild(front);
                leaf.appendChild(back);
                return leaf;
            }

            var segmentCount = 7;
            var pageWidth = spread.clientWidth / 2;
            var segmentWidth = pageWidth / segmentCount;
            var frontSide = 'right';
            var frontIndex = direction === 'next' ? null : target + 1;
            var backSource = direction === 'previous' ? left : null;
            var strips = [];

            for (var index = 0; index < segmentCount; index += 1) {
                var offset = index * segmentWidth;
                var reverseOffset = pageWidth - offset - segmentWidth;
                var strip = document.createElement('div');
                var frontFace = document.createElement('div');
                var backFace = document.createElement('div');
                var frontPage;
                var backPage;

                strip.className = 'book-turn-strip';
                strip.style.width = (segmentWidth + .45) + 'px';
                frontFace.className = 'book-turn-strip-face book-turn-strip-front';
                backFace.className = 'book-turn-strip-face book-turn-strip-back';

                if (direction === 'next') {
                    frontPage = right.cloneNode(true);
                } else {
                    frontPage = document.createElement('section');
                    frontPage.className = 'book-sheet book-sheet-' + frontSide;
                    renderPage(frontPage, frontIndex);
                }

                if (backSource) {
                    backPage = backSource.cloneNode(true);
                } else {
                    backPage = document.createElement('section');
                    backPage.className = 'book-sheet book-sheet-' + backSide;
                    renderPage(backPage, backIndex);
                }

                [frontPage, backPage].forEach(function (page) {
                    page.removeAttribute('data-book-left');
                    page.removeAttribute('data-book-right');
                    page.removeAttribute('data-visible-page');
                    page.removeAttribute('aria-label');
                    page.setAttribute('aria-hidden', 'true');
                    page.classList.add('book-turn-strip-page');
                    page.style.width = pageWidth + 'px';
                });

                frontPage.style.left = -offset + 'px';
                backPage.style.left = -reverseOffset + 'px';
                frontFace.appendChild(frontPage);
                backFace.appendChild(backPage);
                strip.appendChild(frontFace);
                strip.appendChild(backFace);
                leaf.appendChild(strip);
                strips.push({ element: strip, width: segmentWidth, position: index / (segmentCount - 1) });
            }

            leaf._neoStrips = strips;
            return leaf;
        }

        function prepareTurn(target, direction) {
            var state = {
                cover: compact ? null : createPageCover(direction === 'next' ? left : right, direction === 'next' ? 'left' : 'right'),
                leaf: createTurningLeaf(direction, target)
            };

            updateLeaf(state.leaf, 0, direction);
            renderSpread(target);
            if (state.cover) spread.insertBefore(state.cover, canvas);
            spread.insertBefore(state.leaf, canvas);
            return state;
        }

        function revealCover(cover) {
            if (cover) cover.classList.add('is-revealed');
        }

        function removeTurnState(state) {
            if (!state) return;
            if (state.cover) state.cover.remove();
            if (state.leaf) state.leaf.remove();
        }

        function lockPageHeight() {
            var pageWidth = spread.clientWidth / (compact ? 1 : 2);
            if (pageWidth < 1) return;

            var measurer = document.createElement('section');
            measurer.className = 'book-sheet book-page-measurer';
            measurer.style.width = pageWidth + 'px';
            reader.appendChild(measurer);

            var measuredHeight = 0;
            pageMarkup.forEach(function (markup) {
                measurer.innerHTML = markup;
                measuredHeight = Math.max(measuredHeight, measurer.scrollHeight);
            });

            measurer.remove();
            spread.style.height = Math.max(430, Math.ceil(measuredHeight) + 2) + 'px';
        }

        function easeInOut(value) {
            return .5 - Math.cos(Math.PI * value) / 2;
        }

        function resizeCanvas() {
            var rect = stage.getBoundingClientRect();
            var ratio = 1;
            canvas.width = Math.max(1, Math.round(rect.width * ratio));
            canvas.height = Math.max(1, Math.round(rect.height * ratio));
            canvas.style.width = rect.width + 'px';
            canvas.style.height = rect.height + 'px';
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            return { width: rect.width, height: rect.height };
        }

        function updateLeaf(leaf, rawProgress, direction) {
            var eased = easeInOut(rawProgress);
            var curve = Math.sin(Math.PI * eased);

            if (leaf._neoStrips && leaf._neoStrips.length) {
                var baseAngle = direction === 'next' ? -Math.PI * eased : Math.PI * (1 - eased);
                var curlDirection = direction === 'next' ? 1 : -1;
                var curlAmount = curve * .22;
                var x = 0;
                var z = curve * 4;

                leaf._neoStrips.forEach(function (strip) {
                    var localAngle = baseAngle + curlDirection * curlAmount * (strip.position * 2 - 1);
                    strip.element.style.transform = 'translate3d(' + x.toFixed(2) + 'px,-' + (curve * 1.4).toFixed(2) + 'px,' + z.toFixed(2) + 'px) rotateY(' + localAngle.toFixed(5) + 'rad)';
                    x += strip.width * Math.cos(localAngle);
                    z -= strip.width * Math.sin(localAngle);
                });
                return;
            }

            var angle = (direction === 'next' ? -180 : 180) * eased;
            var tilt = (direction === 'next' ? -.65 : .65) * curve;
            var lift = curve * 7;
            var rise = curve * -2;

            leaf.style.transform = 'translate3d(0,' + rise.toFixed(2) + 'px,' + lift.toFixed(2) + 'px) ' +
                'rotateZ(' + tilt.toFixed(3) + 'deg) rotateY(' + angle.toFixed(3) + 'deg)';
        }

        function drawCurl(rawProgress, direction, size) {
            var eased = easeInOut(rawProgress);
            var anchor = size.width / 2;
            var pageWidth = size.width / 2;
            var travelDirection = direction === 'next' ? 1 : -1;
            var edge = anchor + travelDirection * pageWidth * Math.cos(Math.PI * eased);
            var curve = Math.sin(Math.PI * eased);
            var ctx = context;
            ctx.clearRect(0, 0, size.width, size.height);
            if (curve <= .002) return;

            var shadowWidth = 12 + curve * 46;
            var shadowGradient = ctx.createLinearGradient(edge - shadowWidth, 0, edge + shadowWidth, 0);
            shadowGradient.addColorStop(0, 'rgba(0, 0, 0, 0)');
            shadowGradient.addColorStop(.42, 'rgba(0, 0, 0, ' + (.05 + curve * .14) + ')');
            shadowGradient.addColorStop(.52, 'rgba(164, 185, 244, ' + (.04 + curve * .14) + ')');
            shadowGradient.addColorStop(1, 'rgba(0, 0, 0, 0)');
            ctx.fillStyle = shadowGradient;
            ctx.fillRect(edge - shadowWidth, 0, shadowWidth * 2, size.height);

            ctx.save();
            ctx.lineCap = 'round';
            ctx.lineWidth = 1 + curve * 4;
            ctx.strokeStyle = 'rgba(185, 202, 250, ' + (.08 + curve * .2) + ')';
            ctx.beginPath();
            ctx.moveTo(edge, 9 + curve * 5);
            ctx.bezierCurveTo(
                edge - travelDirection * 18 * curve,
                size.height * .28,
                edge + travelDirection * 18 * curve,
                size.height * .72,
                edge,
                size.height - 9 - curve * 5
            );
            ctx.stroke();
            ctx.restore();
        }

        function finishTurn(target, direction, state) {
            spreadStart = target;
            turning = false;
            removeTurnState(state);
            canvas.classList.remove('is-active');
            context.clearRect(0, 0, canvas.width, canvas.height);
            updateControls();
            reader.dispatchEvent(new CustomEvent('neo:book-page', {
                detail: {
                    page: spreadStart + 1,
                    visibleUntil: Math.min(spreadStart + pageStep, pages.length),
                    total: pages.length,
                    direction: direction
                }
            }));
        }

        function turn(direction) {
            if (turning) return;
            var target = spreadStart + (direction === 'next' ? pageStep : -pageStep);
            if (target < 0 || target >= pages.length) return;

            if (reduceMotion) {
                renderSpread(target);
                finishTurn(target, direction, null);
                return;
            }

            turning = true;
            updateControls();
            var size = canvasSize || resizeCanvas();

            canvas.classList.add('is-active');
            var state = prepareTurn(target, direction);

            var startedAt = null;
            var destinationRevealed = compact;

            function animate(now) {
                if (startedAt === null) {
                    startedAt = now;
                    updateLeaf(state.leaf, 0, direction);
                    drawCurl(0, direction, size);
                    animationFrame = requestAnimationFrame(animate);
                    return;
                }

                var elapsed = now - startedAt;
                var amount = Math.min(1, elapsed / duration);

                if (!destinationRevealed && amount >= .94) {
                    revealCover(state.cover);
                    destinationRevealed = true;
                }

                updateLeaf(state.leaf, amount, direction);
                drawCurl(amount, direction, size);
                if (amount < 1) {
                    animationFrame = requestAnimationFrame(animate);
                    return;
                }

                if (!destinationRevealed) revealCover(state.cover);
                finishTurn(target, direction, state);
            }

            cancelAnimationFrame(animationFrame);
            animationFrame = requestAnimationFrame(animate);
        }

        previous.addEventListener('click', function () { turn('previous'); });
        next.addEventListener('click', function () { turn('next'); });
        left.addEventListener('click', function (event) {
            if (event.target.closest('a, button, input, textarea, select')) return;
            turn('previous');
        });
        right.addEventListener('click', function (event) {
            if (event.target.closest('a, button, input, textarea, select')) return;
            turn('next');
        });
        left.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            turn('previous');
        });
        right.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            turn('next');
        });

        reader.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowRight' || event.key === 'PageDown') {
                event.preventDefault();
                turn('next');
            } else if (event.key === 'ArrowLeft' || event.key === 'PageUp') {
                event.preventDefault();
                turn('previous');
            }
        });

        stage.addEventListener('touchstart', function (event) {
            touchStartX = event.changedTouches[0].clientX;
        }, { passive: true });
        stage.addEventListener('touchend', function (event) {
            if (touchStartX === null) return;
            var distance = event.changedTouches[0].clientX - touchStartX;
            touchStartX = null;
            if (Math.abs(distance) < 54) return;
            turn(distance < 0 ? 'next' : 'previous');
        }, { passive: true });

        window.addEventListener('resize', function () {
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(function () {
                if (!turning) {
                    lockPageHeight();
                    canvasSize = resizeCanvas();
                }
            }, 120);
        }, { passive: true });

        reader.tabIndex = 0;
        lockPageHeight();
        renderSpread(spreadStart);
        canvas.hidden = false;
        canvasSize = resizeCanvas();
        drawCurl(0, 'next', canvasSize);
        context.clearRect(0, 0, canvas.width, canvas.height);
        updateControls();

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () {
                if (turning) return;
                lockPageHeight();
                canvasSize = resizeCanvas();
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-book-reader]').forEach(initBook);
    });
})();
