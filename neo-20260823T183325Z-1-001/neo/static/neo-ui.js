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
        var submitter = event.submitter;
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
    });
})();

