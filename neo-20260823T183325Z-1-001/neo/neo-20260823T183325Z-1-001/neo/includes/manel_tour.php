<div class="manel-tour" data-manel-tour hidden>
    <svg class="manel-tour-dimmer" aria-hidden="true" focusable="false">
        <path data-tour-shade fill-rule="evenodd"></path>
    </svg>
    <div class="manel-tour-spotlight" data-tour-spotlight aria-hidden="true"></div>
    <svg class="manel-tour-arrow" data-tour-arrow aria-hidden="true" focusable="false">
        <path data-tour-arrow-line></path>
        <path data-tour-arrow-tip></path>
    </svg>
    <section class="manel-tour-bubble" data-tour-bubble role="dialog" aria-labelledby="manelTourTitle" aria-describedby="manelTourSpeech" tabindex="-1">
      <div class="manel-tour-surface" data-tour-surface>
        <header class="manel-tour-heading">
            <strong id="manelTourTitle">Manel</strong>
            <button type="button" class="manel-icon-btn neo-star-hover" data-tour-back aria-label="Passo anterior" title="Voltar">
                <?= estrelaHoverNeo() ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M19 12H5m6-6-6 6 6 6"></path>
                </svg>
            </button>
            <button type="button" class="manel-icon-btn neo-star-hover" data-tour-skip aria-label="Pular tutorial" title="Pular tutorial">
                <?= estrelaHoverNeo() ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" aria-hidden="true">
                    <path d="M6 6l12 12M18 6 6 18"></path>
                </svg>
            </button>
        </header>
        <p id="manelTourSpeech" data-tour-speech aria-live="polite" aria-atomic="true"></p>
        <footer class="manel-tour-controls">
            <button type="button" class="manel-icon-btn neo-star-hover manel-tour-next" data-tour-next>
                <?= estrelaHoverNeo() ?>
                <span data-tour-next-label>Continuar</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 12h14m-6-6 6 6-6 6"></path>
                </svg>
            </button>
        </footer>
      </div>
    </section>
</div>
