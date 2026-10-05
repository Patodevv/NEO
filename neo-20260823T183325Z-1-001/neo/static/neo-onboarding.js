(() => {
    'use strict';
    const root = document.querySelector('[data-neo-onboarding]');
    if (!root) return;
    const stage = document.getElementById('neo-setup-stage');
    const thread = root.querySelector('[data-onboarding-thread]');
    const feedback = document.getElementById('neo-setup-feedback');
    const saveStatus = document.getElementById('neo-setup-save');
    const progress = root.querySelector('[role="progressbar"]');
    const companion = document.querySelector('[data-neo-companion]');
    const companionStatus = document.querySelector('.neo-setup-companion-status');
    const enterHint = document.getElementById('neo-setup-enter-hint');
    const backControl = document.getElementById('neo-setup-back');
    const nextControl = document.getElementById('neo-setup-next');
    const buttonStar = document.querySelector('[data-onboarding-button-star]');
    const storageKey = 'neo_onboarding_draft_v1_' + root.dataset.draftKey;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const mobileOnboarding = window.matchMedia('(max-width: 760px)');
    const goalKinds = [{id:'enem',label:'Pontuação no ENEM'}, {id:'nota',label:'Melhorar uma nota (até 10)'}, {id:'questoes',label:'Resolver questões'}, {id:'conteudo',label:'Concluir um conteúdo'}, {id:'prova',label:'Preparar uma prova ou certificação'}];
    const questions = {
        nome: 'Como você quer que eu te chame?',
        gostos: 'Agora quero conhecer você melhor: do que você gosta?',
        ensino: 'Agora me conta: em qual fase de estudos você está?',
        modo: 'Como você quer estudar no NEO?',
        materias: 'Quais matérias você quer estudar primeiro?',
        objetivo: 'Qual é seu principal objetivo estudando comigo?',
        meta: 'Você tem uma meta específica? Tipo uma nota, uma prova ou um prazo?',
        niveis: 'Como você acha que está seu nível nessas matérias?',
        formatos: 'Qual jeito de estudar funciona melhor pra você?',
        explicacao: 'E como você quer que eu te explique as coisas?',
        tempo: 'Quanto tempo você consegue estudar por dia?',
        dias: 'Quantos dias por semana você pretende estudar?',
        ritmo: 'Qual ritmo combina mais com você agora?',
        gamificacao: 'Como você quer que eu use desafios, conquistas e rankings com você?',
        motivacao: 'Que tipo de incentivo mais te ajuda a continuar?'
    };
    let state = null;
    let view = 'welcome';
    let sub = 0;
    let draft = null;
    let busy = false;
    let fieldSerial = 0;
    let submitCurrent = null;
    let faceFlight = null;
    let speechAnimation = null;
    let autoAdvanceTimer = null;
    const navigationEntry = performance.getEntriesByType?.('navigation')?.[0];
    let resetOnLoad = root.dataset.edit !== '1' && (navigationEntry ? navigationEntry.type === 'reload' : performance.navigation?.type === 1);

    function element(tag, className, text) {
        const el = document.createElement(tag);
        if (className) el.className = className;
        if (text !== undefined) el.textContent = text;
        return el;
    }
    function clone(value) { return value === undefined ? undefined : JSON.parse(JSON.stringify(value)); }
    function options(id) { return state.catalog.steps.find(step => step.id === id)?.options || []; }
    function ids() { return state.catalog.steps.map(step => step.id); }
    function labelOf(list, id) { return list.find(item => String(item.id) === String(id))?.label || String(id || ''); }
    function enumLabel(id, value) { return labelOf(options(id), value); }
    function subjectLabel(id) { return id === 'outra' ? state.answers.materias?.other || 'Outra matéria' : labelOf(state.catalog.materias, id); }
    function selectedSubjects() { return state.answers.materias?.selected || []; }
    function face(mood) {
        document.dispatchEvent(new CustomEvent('neo:manel-face-state', {detail:{state:mood}}));
        if (companionStatus) companionStatus.textContent = mood === 'thinking' ? 'Analisando…' : mood === 'error' ? 'Vamos ajustar' : '';
    }
    function waitForLogoIntro() {
        const overlay = document.querySelector('.neo-intro-overlay');
        if (!overlay) return Promise.resolve(false);
        return new Promise(resolve => {
            let done = false;
            const usesParentCurtain = overlay.dataset.parentCurtain === '1';
            const finish = () => {
                if (done) return;
                done = true;
                window.removeEventListener('message', onMessage);
                window.removeEventListener('neo:intro-opened', onOpened);
                resolve(true);
            };
            const onMessage = event => {
                if (!usesParentCurtain && event.data && event.data.type === 'neo-intro-open') finish();
            };
            const onOpened = () => finish();
            window.addEventListener('message', onMessage);
            if (usesParentCurtain) window.addEventListener('neo:intro-opened', onOpened);
            window.setTimeout(finish, usesParentCurtain ? 2600 : 1500);
        });
    }
    function clearError() {
        feedback.hidden = true;
        feedback.textContent = '';
        stage.querySelectorAll('[aria-invalid="true"]').forEach(input => input.removeAttribute('aria-invalid'));
    }
    function error(message, input) {
        feedback.textContent = message;
        feedback.hidden = false;
        if (input) {
            input.setAttribute('aria-invalid','true');
            input.setAttribute('aria-describedby','neo-setup-feedback');
        }
        face('error');
        (input || feedback).focus({preventScroll:true});
        feedback.scrollIntoView({block:'nearest',behavior:reducedMotion.matches ? 'auto' : 'smooth'});
    }
    function remember() {
        if (['welcome','resumo','credentials'].includes(view)) return;
        try { localStorage.setItem(storageKey, JSON.stringify({step:view,sub,draft,updatedAt:Date.now()})); } catch (_) {}
    }
    function forget() { try { localStorage.removeItem(storageKey); } catch (_) {} }
    function setBusy(active) {
        busy = active;
        root.classList.toggle('is-busy',active);
        stage.setAttribute('aria-busy',String(active));
        stage.querySelectorAll('input,textarea,select,button').forEach(input => { input.disabled = active; });
        if (nextControl) nextControl.disabled = active;
        if (active) { face('thinking'); if (saveStatus) saveStatus.textContent = 'Conferindo sua resposta…'; }
    }
    async function api(payload) {
        const controller = new AbortController();
        const timer = window.setTimeout(() => controller.abort(),25000);
        try {
            const request = {credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:{Accept:'application/json'}};
            if (payload) {
                request.method = 'POST';
                request.headers['Content-Type'] = 'application/json';
                request.body = JSON.stringify({...payload,csrf_token:state?.csrf_token || document.querySelector('meta[name="csrf-token"]')?.content || ''});
            }
            const response = await fetch('onboarding-api.php',request);
            let data;
            try { data = await response.json(); } catch (_) { throw new Error('Não consegui conversar com o NEO agora. Tente novamente em instantes.'); }
            if (!response.ok || !data.ok) {
                const failure = new Error(data.error || 'Não consegui salvar essa resposta. Vamos tentar de novo?');
                failure.step = data.step;
                throw failure;
            }
            return data;
        } catch (failure) {
            if (failure.name === 'AbortError' || failure instanceof TypeError) throw new Error('A conexão demorou um pouquinho. Sua resposta continua aqui; tente novamente.');
            throw failure;
        } finally { window.clearTimeout(timer); }
    }
    function applyState(data) { state = {...state,...data,answers:data.answers || state?.answers || {}}; }
    function initialDraft(step) {
        if (Object.prototype.hasOwnProperty.call(state.answers,step)) return clone(state.answers[step]);
        if (['ensino','objetivo'].includes(step)) return {value:'',other:''};
        if (step === 'gostos') return {selected:[],other:'',references:''};
        if (step === 'materias') return {selected:[],other:''};
        if (step === 'niveis') return {};
        if (['formatos','gamificacao'].includes(step)) return [];
        if (step === 'meta') return {defined:null,kind:'',description:'',value:'',deadline:''};
        return '';
    }
    function go(step, nextSub = 0, value) {
        view = step;
        sub = nextSub;
        draft = value === undefined ? initialDraft(step) : value;
        render();
    }
    function localNext() { sub += 1; remember(); render(); }
    async function save(value = draft) {
        if (busy) return;
        clearError();
        remember();
        const savedStep = view;
        setBusy(true);
        try {
            const data = await api({action:'save',step:savedStep,value});
            applyState(data);
            forget();
            setBusy(false);
            face('success');
            if (saveStatus) saveStatus.textContent = 'Resposta salva. Pode continuar de onde parou.';
            go(data.next_step || data.step || 'resumo');
        } catch (failure) {
            setBusy(false);
            if (saveStatus) saveStatus.textContent = 'A resposta ainda não foi salva.';
            if (savedStep === 'meta') {
                if (/descreva|detalhe|ligar|pontuação parece/i.test(failure.message)) sub = 2;
                else if (/prazo|amanhã/i.test(failure.message)) sub = 4;
                else if (/valor|numérico|nota maior|quantidade|pontuação|questões por dia/i.test(failure.message)) sub = 3;
                render();
            }
            error(failure.message);
        }
    }
    function finishSpeech() {
        speechAnimation?.finish(false);
    }
    function clearAutoAdvance() {
        if (autoAdvanceTimer) window.clearTimeout(autoAdvanceTimer);
        autoAdvanceTimer = null;
    }
    function queueAutoSubmit(delay = 1050) {
        clearAutoAdvance();
        autoAdvanceTimer = window.setTimeout(() => {
            autoAdvanceTimer = null;
            if (!busy && submitCurrent) submitCurrent();
        },delay);
    }
    function afterCurrentSpeech(callback, delay = 600) {
        const run = () => {
            clearAutoAdvance();
            autoAdvanceTimer = window.setTimeout(() => {
                autoAdvanceTimer = null;
                if (!busy) callback();
            },delay);
        };
        if (speechAnimation) speechAnimation.after = run;
        else run();
    }
    function resetConversationScroll() {
        requestAnimationFrame(() => {
            thread?.scrollTo({top:0,left:0,behavior:'auto'});
            window.scrollTo({top:0,left:0,behavior:'auto'});
        });
    }
    function assistant(question, hint, count, onFinished) {
        const bubble = element('div','study-book neo-setup-assistant');
        if (count) bubble.append(element('p','neo-setup-subcount',count));
        const heading = element('h2','',question);
        heading.id = 'neo-setup-question';
        heading.tabIndex = -1;
        bubble.append(heading);
        stage.append(bubble);
        resetConversationScroll();
        const texts = [...bubble.querySelectorAll('.neo-setup-subcount, h2, p')].map(node => ({node,text:node.textContent,chars:Array.from(node.textContent)}));
        const total = texts.reduce((sum,item) => sum + item.chars.length,0);
        const mouth = companion?.querySelector('.neo-companion-mouth');
        const restMouth = 'M 128 150 L 172 150';
        const speechShapes = [restMouth,'M 128 147 Q 150 166 172 147','M 142 151 Q 150 140 158 151 Q 150 162 142 151','M 128 147 Q 150 166 172 147'];
        let frame = null;
        let settled = false;
        const animation = {
            after: onFinished || null,
            finish(runAfter = true) {
                if (settled) return;
                settled = true;
                if (frame) cancelAnimationFrame(frame);
                texts.forEach(item => { item.node.textContent = item.text; item.node.classList.remove('is-typing-line'); });
                bubble.classList.remove('is-entering','is-typing');
                stage.classList.remove('is-manel-typing');
                root.classList.remove('is-manel-typing');
                stage.setAttribute('aria-busy',String(busy));
                if (mouth) mouth.setAttribute('d',restMouth);
                face('neutral');
                if (speechAnimation === animation) speechAnimation = null;
                [...stage.children]
                    .filter(child => child !== bubble && !child.classList.contains('neo-setup-assistant'))
                    .forEach(child => child.animate?.([{opacity:0,transform:'translateY(7px)'},{opacity:1,transform:'none'}],{duration:220,easing:'ease-out'}));
                focusQuestion();
                if (runAfter && animation.after) animation.after();
            }
        };
        speechAnimation = animation;
        if (reducedMotion.matches || document.hidden || total === 0) {
            animation.finish();
            return bubble;
        }
        texts.forEach(item => { item.node.textContent = ''; });
        bubble.classList.add('is-entering');
        stage.classList.add('is-manel-typing');
        root.classList.add('is-manel-typing');
        stage.setAttribute('aria-busy','true');
        face('speaking');
        const duration = Math.min(14000,Math.max(800,total / 95 * 1000));
        let started = null;
        let lastCount = -1;
        let lastMouth = -1;
        let current = 0;
        let offset = 0;
        function tick(time) {
            if (settled) return;
            if (started === null) started = time;
            const elapsed = time - started;
            const visibleCount = Math.min(total,Math.floor(total * elapsed / duration));
            if (visibleCount !== lastCount) {
                while (current < texts.length && visibleCount >= offset + texts[current].chars.length) {
                    texts[current].node.textContent = texts[current].text;
                    texts[current].node.classList.remove('is-typing-line');
                    offset += texts[current].chars.length;
                    current++;
                }
                if (current < texts.length) {
                    texts[current].node.classList.add('is-typing-line');
                    texts[current].node.textContent = texts[current].chars.slice(0,visibleCount - offset).join('');
                }
                lastCount = visibleCount;
            }
            const mouthIndex = Math.floor(elapsed / 130) % speechShapes.length;
            if (mouth && mouthIndex !== lastMouth) { mouth.setAttribute('d',speechShapes[mouthIndex]); lastMouth = mouthIndex; }
            if (visibleCount >= total) { animation.finish(); return; }
            frame = requestAnimationFrame(tick);
        }
        requestAnimationFrame(() => {
            if (settled) return;
            bubble.classList.remove('is-entering');
            bubble.classList.add('is-typing');
            bubble.animate?.([{opacity:0,transform:'translate(-8px, -8px) scale(.98)'},{opacity:1,transform:'none'}],{duration:220,easing:'ease-out'});
            frame = requestAnimationFrame(tick);
        });
        return bubble;
    }
    function responseForm() {
        const form = element('form','study-composer neo-setup-reply');
        form.noValidate = true;
        form.setAttribute('aria-labelledby','neo-setup-question');
        form.addEventListener('submit',event => { event.preventDefault(); if (!busy && submitCurrent) submitCurrent(); });
        stage.append(form);
        return form;
    }
    function button(text, callback, className = '', type = 'button') {
        const btn = element('button','neo-icon-button neo-star-hover neo-setup-button ' + className);
        if (buttonStar) btn.append(buttonStar.content.cloneNode(true));
        btn.append(element('span','neo-setup-button-label',text));
        btn.type = type;
        if (callback) btn.addEventListener('click',() => { if (!busy) callback(); });
        return btn;
    }
    function actions(form, callback, text = 'Continuar', back = true) {
        if (callback) {
            submitCurrent = callback;
            if (mobileOnboarding.matches || form.querySelector('input[type="checkbox"]')) {
                if (nextControl) {
                    const label = nextControl.querySelector('span');
                    if (label) label.textContent = 'Seguinte →';
                    nextControl.hidden = false;
                }
            } else if (!form.querySelector('input, textarea, select')) afterCurrentSpeech(callback);
        }
        return null;
    }
    function textField(form, {label,name,value,type = 'text',placeholder = '',hint = '',maxLength = 180,min,max,required = true,onInput,autocomplete}) {
        const wrap = element('label','neo-setup-field');
        const input = element(type === 'textarea' ? 'textarea' : 'input');
        input.id = 'neo-setup-input-' + (++fieldSerial);
        input.name = name || view;
        if (type !== 'textarea') input.type = type;
        input.value = value ?? '';
        input.required = required;
        input.maxLength = maxLength;
        input.placeholder = placeholder || label;
        if (autocomplete) input.autocomplete = autocomplete;
        if (min !== undefined) input.min = min;
        if (max !== undefined) input.max = max;
        if (type === 'number') { input.step = 'any'; input.inputMode = 'decimal'; }
        wrap.htmlFor = input.id;
        input.setAttribute('aria-label',label);
        wrap.append(input);
        input.addEventListener('input',() => { clearError(); onInput?.(input.value); face('thinking'); remember(); });
        input.addEventListener('focus',() => face('thinking'));
        input.addEventListener('keydown',event => {
            if (event.key === 'Enter' && (type !== 'textarea' || !event.shiftKey)) {
                event.preventDefault();
                form.requestSubmit();
            }
        });
        if (['date','time'].includes(type)) input.addEventListener('change',() => queueAutoSubmit(450));
        form.append(wrap);
        return input;
    }
    function validField(input, message) {
        if (!input.value.trim() || !input.checkValidity()) { error(message,input); return false; }
        return true;
    }
    function choiceGrid(form, list, selected, onChange, {multiple = false,wide = false,name = view} = {}) {
        const grid = element('fieldset','neo-setup-choice-grid' + (wide ? ' is-wide' : ''));
        grid.setAttribute('aria-labelledby','neo-setup-question');
        list.forEach(option => {
            const label = element('label','neo-setup-choice');
            const input = element('input');
            input.type = multiple ? 'checkbox' : 'radio';
            input.name = name;
            input.value = String(option.id);
            input.checked = multiple ? (selected || []).map(String).includes(String(option.id)) : String(selected) === String(option.id);
            if (input.checked) label.classList.add('is-checked');
            const text = element('span','',option.label);
            input.addEventListener('focus',() => face('thinking'));
            input.addEventListener('change',() => {
                if (busy) return;
                clearError();
                face('thinking');
                grid.querySelectorAll('.neo-setup-choice').forEach(item => item.classList.toggle('is-checked', item.querySelector('input')?.checked === true));
                onChange(multiple ? [...grid.querySelectorAll('input:checked')].map(item => item.value) : input.value,input);
                remember();
            });
            label.append(input,text);
            grid.append(label);
        });
        form.append(grid);
        return grid;
    }
    function goBack() {
        if (busy) return;
        if (view === 'credentials') { go('resumo'); return; }
        if (sub > 0 && ['meta','niveis'].includes(view)) {
            sub -= 1;
            if (view === 'meta' && sub === 3 && !['enem','nota','questoes'].includes(draft.kind)) sub = 2;
            remember(); render(); return;
        }
        const previous = ids().indexOf(view) - 1;
        if (previous >= 0) go(ids()[previous]);
        else if (state.completed || root.dataset.edit === '1') go('resumo');
        else window.location.assign('login.php?reset_onboarding=1');
    }
    function updateProgress() {
        const total = ids().length + 1;
        const index = ['resumo','credentials'].includes(view) ? ids().length : Math.max(0,ids().indexOf(view));
        const current = view === 'welcome' ? 0 : index + 1;
        if (progress) {
            progress.setAttribute('aria-valuenow',String(current));
            progress.setAttribute('aria-valuemax',String(total));
            progress.firstElementChild.style.width = (current / total * 100) + '%';
        }
        const stepCount = document.getElementById('neo-setup-step-count');
        const stepLabel = document.getElementById('neo-setup-step-label');
        if (stepCount) stepCount.textContent = current ? String(current).padStart(2,'0') + ' de ' + total : 'Vamos começar';
        if (stepLabel) stepLabel.textContent = view === 'credentials' ? 'Seu acesso ao NEO' : view === 'resumo' ? 'Sua personalização' : state.catalog.steps.find(step => step.id === view)?.label || 'Personalização com o Manel';
        const phase = index < 8 ? 0 : index < 12 ? 1 : index < 20 ? 2 : 3;
        root.querySelectorAll('[data-journey]').forEach(item => {
            item.classList.toggle('is-current',Number(item.dataset.journey) === phase);
            item.classList.toggle('is-done',Number(item.dataset.journey) < phase);
        });
    }
    function focusQuestion() {
        requestAnimationFrame(() => {
            const heading = stage.querySelector('h2');
            heading?.focus({preventScroll:true});
            if (heading && (heading.getBoundingClientRect().top < 90 || heading.getBoundingClientRect().top > innerHeight - 130)) heading.scrollIntoView({block:'start',behavior:reducedMotion.matches ? 'auto' : 'smooth'});
        });
    }
    function welcome() {
        view = 'welcome'; sub = 0; draft = null; submitCurrent = null;
        clearError(); updateProgress();
        finishSpeech();
        stage.replaceChildren(); stage.dataset.step = 'welcome'; stage.classList.add('neo-setup-welcome'); stage.setAttribute('aria-busy','false');
        assistant('Fala aí! Sou o Manel, seu assistente virtual.',null,null,() => afterCurrentSpeech(() => {
            assistant('Antes de começar, vou te fazer algumas perguntinhas rápidas pra deixar o NEO com a sua cara.',null,null,() => afterCurrentSpeech(() => go(state.step || 'nome'),1000));
        },1000));
        const hasProgress = Object.keys(state.answers).length > 0;
        if (saveStatus) saveStatus.textContent = hasProgress ? 'Seu progresso está salvo.' : 'Suas respostas serão salvas a cada etapa.';
    }
    function singleStep() {
        let hint = 'Escolha uma opção para continuar automaticamente.';
        let list = options(view);
        let question = questions[view];
        if (view === 'ensino' && state.answers.nome) question = state.answers.nome + ', em qual fase de estudos você está?';
        if (view === 'ritmo') list = list.map(item => ({...item,description:({tranquilo:'Poucas atividades, com mais espaçamento.',normal:'Equilíbrio entre conteúdo, exercícios e revisões.',intensivo:'Mais atividades, revisões e desafios.'})[item.id]}));
        if (view === 'tempo') hint = 'Vou ajustar o tamanho das sessões ao tempo que você tem.';
        if (view === 'dias') hint = 'Seu plano começa com essa frequência e pode se adaptar ao seu ritmo real.';
        if (view === 'explicacao') hint = 'Esse será meu ponto de partida ao te explicar os conteúdos.';
        assistant(question,hint);
        const form = responseForm();
        const hasOther = ['ensino','objetivo'].includes(view);
        let otherField = null;
        const selected = hasOther ? draft.value : draft;
        choiceGrid(form,list,selected,value => {
            if (hasOther) {
                draft.value = value;
                if (value === 'outro') { render(); stage.querySelector('input[type="text"]')?.focus(); }
                else if (!mobileOnboarding.matches) save(draft);
            } else {
                draft = value;
                if (!mobileOnboarding.matches) save();
            }
        },{wide:['modo','objetivo','ritmo','motivacao'].includes(view)});
        if (hasOther && draft.value === 'outro') {
            otherField = textField(form,{label:view === 'ensino' ? 'Me conta qual é sua fase de estudos' : 'O que você quer aprender ou alcançar?',name:view + '_other',value:draft.other,maxLength:140,placeholder:view === 'ensino' ? 'Ex.: curso de música' : 'Ex.: aprender programação para meu trabalho',onInput:value => draft.other = value});
        }
        actions(form,() => {
            if (hasOther && !draft.value) { error('Escolha uma opção para eu entender como você estuda.'); return; }
            if (!hasOther && !draft) { error('Escolha uma opção para continuar.'); return; }
            if (otherField && !validField(otherField,'Me conte um pouquinho sobre seus estudos para eu entender.')) return;
            save();
        });
    }
    function nameStep() {
        assistant(questions.nome,'Pode ser seu primeiro nome ou um apelido. É assim que vou conversar com você.');
        const form = responseForm();
        const input = textField(form,{label:'Seu nome ou apelido',name:'nome',value:draft,maxLength:40,placeholder:'Como seus amigos te chamam?',autocomplete:'nickname',onInput:value => draft = value});
        actions(form,() => { if (validField(input,'Me conta um nome ou apelido válido para eu te chamar.')) save(); });
    }
    function tastesStep() {
        assistant(questions.gostos,'Escolha quantos quiser. Se citar referências exatas, eu só vou usar esses nomes quando a relação estiver correta e realmente ajudar a explicar.');
        const form = responseForm();
        const otherWrap = element('div');
        choiceGrid(form,options('gostos'),draft.selected,value => { draft.selected = value; otherWrap.hidden = !value.includes('outro'); },{multiple:true});
        form.append(otherWrap);
        const other = textField(otherWrap,{label:'Qual outro interesse?',name:'gostos_other',value:draft.other,maxLength:80,placeholder:'Ex.: astronomia, dança, carros, fotografia',onInput:value => draft.other = value});
        otherWrap.hidden = !draft.selected.includes('outro');
        const references = textField(form,{label:'Referências específicas que você gosta (opcional)',name:'gostos_references',type:'textarea',value:draft.references || '',maxLength:180,placeholder:'Ex.: One Piece, Minecraft, Fórmula 1, Taylor Swift',onInput:value => draft.references = value});
        actions(form,() => {
            if (!draft.selected.length) { error('Escolha pelo menos uma coisa de que você gosta. Isso vai me ajudar a adaptar seus estudos.'); return; }
            if (draft.selected.includes('outro') && !validField(other,'Me conte qual é esse outro interesse.')) return;
            if (references.value.trim() && references.value.trim().length < 2) { error('Escreva o nome completo da referência ou deixe esse campo vazio.',references); return; }
            save();
        },'Confirmar meus gostos');
    }
    function subjectsStep() {
        assistant(questions.materias);
        const form = responseForm();
        const otherWrap = element('div');
        choiceGrid(form,state.catalog.materias,draft.selected,value => { draft.selected = value; otherWrap.hidden = !value.includes('outra'); },{multiple:true});
        form.append(otherWrap);
        const other = textField(otherWrap,{label:'Qual outra matéria?',name:'materias_other',value:draft.other,maxLength:70,placeholder:'Ex.: Música, Programação, Filosofia',onInput:value => draft.other = value});
        otherWrap.hidden = !draft.selected.includes('outra');
        actions(form,() => {
            if (!draft.selected.length) { error('Escolha pelo menos uma matéria. Podemos começar com a que mais faz sentido agora.'); return; }
            if (draft.selected.includes('outra') && !validField(other,'Me diga o nome da outra matéria que quer estudar.')) return;
            save();
        },'Confirmar matérias');
    }
    function perItemStep() {
        const items = selectedSubjects();
        sub = Math.min(Math.max(0,sub),items.length - 1);
        const id = items[sub];
        assistant('Como você acha que está seu nível em ' + subjectLabel(id) + '?',null,(sub + 1) + ' de ' + items.length + ' matérias');
        const form = responseForm();
        choiceGrid(form,options('niveis'),draft[id] || '',value => {
            draft[id] = value;
            if (!mobileOnboarding.matches) {
                if (sub + 1 < items.length) localNext();
                else save();
            }
        },{wide:true,name:'niveis_' + id});
        actions(form,() => {
            if (!draft[id]) { error('Escolha o nível que mais combina com você agora.'); return; }
            if (sub + 1 < items.length) localNext();
            else save();
        });
    }
    function goalStep() {
        if (sub === 0) {
            assistant(questions.meta,'Uma meta ajuda a acompanhar sua evolução. Se ainda não souber, tudo bem.');
            const form = responseForm();
            choiceGrid(form,[{id:'sim',label:'Sim, quero definir uma meta'}, {id:'nao',label:'Ainda não tenho uma meta clara'}],draft.defined === null ? '' : draft.defined ? 'sim' : 'nao',value => {
                draft.defined = value === 'sim';
                if (!mobileOnboarding.matches) {
                    if (draft.defined) localNext(); else save({defined:false});
                }
            },{wide:true});
            actions(form,() => {
                if (draft.defined === null) { error('Escolha uma opção para continuar.'); return; }
                if (draft.defined) localNext(); else save({defined:false});
            }); return;
        }
        if (sub === 1) {
            assistant('Que tipo de meta você quer alcançar?','Assim eu consigo te ajudar com um objetivo possível e um prazo que faça sentido.');
            const form = responseForm();
            choiceGrid(form,goalKinds,draft.kind,value => { draft.kind = value; if (!mobileOnboarding.matches) localNext(); },{wide:true,name:'meta_kind'});
            actions(form,() => { if (!draft.kind) { error('Escolha o tipo de meta que você quer alcançar.'); return; } localNext(); }); return;
        }
        if (sub === 2) {
            assistant('Me conta um pouco mais sobre essa meta.','Pode ser melhorar em uma matéria, concluir um conteúdo ou se preparar para uma prova.');
            const form = responseForm();
            const input = textField(form,{label:'Qual objetivo você quer alcançar?',name:'meta_description',type:'textarea',value:draft.description,maxLength:180,placeholder:'Ex.: melhorar minha nota em Matemática neste bimestre',onInput:value => draft.description = value});
            actions(form,() => {
                if (!validField(input,'Descreva sua meta de estudo com um pouco mais de detalhe.')) return;
                sub = ['enem','nota','questoes'].includes(draft.kind) ? 3 : 4; remember(); render();
            }); return;
        }
        if (sub === 3) {
            const kind = draft.kind;
            assistant(kind === 'questoes' ? 'Quantas questões você quer resolver?' : kind === 'enem' ? 'Qual pontuação você quer buscar no ENEM?' : 'Qual nota você quer alcançar?',kind === 'nota' ? 'Vamos usar uma escala de 0 a 10.' : kind === 'enem' ? 'Use uma meta de 1 a 1000 pontos.' : 'No próximo passo, vamos combinar essa quantidade com seu prazo.');
            const form = responseForm();
            const input = textField(form,{label:kind === 'questoes' ? 'Quantidade de questões' : 'Sua meta de nota',name:'meta_value',type:'number',value:draft.value,min:kind === 'nota' ? .1 : 1,max:kind === 'nota' ? 10 : kind === 'enem' ? 1000 : 100000,onInput:value => draft.value = value});
            actions(form,() => { if (validField(input,'Vamos usar um valor possível dentro do intervalo indicado.')) localNext(); }); return;
        }
        assistant('Até quando você quer alcançar essa meta?','Escolha um prazo a partir de amanhã. Vou usar essa data para orientar seu plano.');
        const form = responseForm();
        const tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate() + 1);
        const maxDate = new Date(); maxDate.setFullYear(maxDate.getFullYear() + 5);
        const localDate = date => [date.getFullYear(),String(date.getMonth() + 1).padStart(2,'0'),String(date.getDate()).padStart(2,'0')].join('-');
        const input = textField(form,{label:'Prazo para sua meta',name:'meta_deadline',type:'date',value:draft.deadline,min:localDate(tomorrow),max:localDate(maxDate),onInput:value => draft.deadline = value});
        actions(form,() => { if (validField(input,'Escolha um prazo entre amanhã e os próximos cinco anos.')) save(); },'Salvar minha meta');
    }
    function multiStep() {
        assistant(questions[view],view === 'formatos' ? 'Escolha um ou mais formatos. Também vou aprender com o que funciona na sua prática.' : 'Pode escolher mais de uma opção. Seu plano vai respeitar o que te motiva.');
        const form = responseForm();
        choiceGrid(form,options(view),draft,value => draft = value,{multiple:true,wide:view === 'gamificacao'});
        actions(form,() => { if (!draft.length) { error('Escolha pelo menos uma opção para eu deixar a experiência com a sua cara.'); return; } save(); },'Confirmar escolhas');
    }
    function summaryValue(id) {
        const value = state.answers[id];
        if (value === undefined) return 'Ainda precisamos conversar sobre isso';
        if (id === 'nome') return value;
        if (id === 'gostos') {
            const interests = value.selected.map(item => item === 'outro' ? value.other : enumLabel('gostos',item)).join(', ');
            return value.references ? interests + '\nReferências: ' + value.references : interests;
        }
        if (['ensino','objetivo'].includes(id)) return value.value === 'outro' ? value.other : enumLabel(id,value.value);
        if (id === 'materias') return value.selected.map(subjectLabel).join(', ');
        if (id === 'meta') return value.defined ? value.description + (value.value ? ' · ' + Number(value.value).toLocaleString('pt-BR') + (value.kind === 'questoes' ? ' questões' : ' pontos') : '') + '\nAté ' + new Date(value.deadline + 'T12:00:00').toLocaleDateString('pt-BR') : 'Ainda não tenho uma meta clara';
        if (id === 'niveis') return Object.entries(value).map(([subject,level]) => subjectLabel(subject) + ' — ' + enumLabel('niveis',level)).join('\n');
        if (Array.isArray(value)) return value.map(item => enumLabel(id,item)).join(', ');
        return enumLabel(id,value);
    }
    function summary() {
        assistant('Prontinho' + (state.answers.nome ? ', ' + state.answers.nome : '') + '! Dá uma olhada se entendi tudo certo.','Esse é o ponto de partida do seu NEO. Você pode ajustar qualquer resposta agora e continuar personalizando depois.');
        const list = element('dl','neo-setup-summary');
        state.catalog.steps.forEach(step => {
            const row = element('div','neo-setup-summary-row');
            const detail = element('div');
            detail.append(element('dt','',step.label),element('dd','',summaryValue(step.id)));
            const edit = element('button','neo-setup-summary-edit neo-star-hover');
            if (buttonStar) edit.append(buttonStar.content.cloneNode(true));
            edit.append(element('span','', '↗'));
            edit.type = 'button';
            edit.dataset.editStep = step.id;
            edit.title = 'Editar ' + step.label;
            edit.setAttribute('aria-label','Editar ' + step.label);
            edit.addEventListener('click',() => go(step.id));
            row.append(detail,edit); list.append(row);
        });
        stage.append(list);
        const form = element('form','neo-setup-summary-actions-wrap');
        const confirmSummary = () => (state.authenticated || state.logged_in) ? finish() : go('credentials');
        form.addEventListener('submit',event => { event.preventDefault(); if (!busy) confirmSummary(); });
        const bar = element('div','neo-setup-actions neo-setup-final-actions');
        bar.append(button('Editar respostas',() => { list.querySelector('button')?.focus(); list.scrollIntoView({block:'start',behavior:reducedMotion.matches ? 'auto' : 'smooth'}); },'is-final'));
        bar.append(button(state.authenticated || state.logged_in ? 'Confirmar e entrar no NEO  →' : 'Prosseguir para o e-mail  →',confirmSummary,'is-final'));
        form.append(bar);
        stage.append(form);
    }
    function credentials() {
        assistant('Seu plano está pronto. Vamos guardar seu acesso?','Use seu e-mail e crie uma senha para voltar ao seu NEO de qualquer dispositivo.');
        const form = responseForm();
        const email = textField(form,{label:'Seu e-mail',name:'email',type:'email',maxLength:150,autocomplete:'email',placeholder:'voce@exemplo.com'});
        const password = textField(form,{label:'Crie uma senha',name:'senha',type:'password',maxLength:72,autocomplete:'new-password',hint:'Use pelo menos 8 caracteres (até 72 bytes).'});
        password.minLength = 8;
        const confirm = textField(form,{label:'Repita sua senha',name:'confirmar_senha',type:'password',maxLength:72,autocomplete:'new-password'});
        confirm.minLength = 8;
        actions(form,() => {
            if (!validField(email,'Me informe um e-mail válido para guardar sua conta.')) return;
            if (!validField(password,'Sua senha precisa ter pelo menos 8 caracteres.')) return;
            if (new TextEncoder().encode(password.value).length > 72) { error('Essa senha ficou longa demais. Use uma senha menor, com pelo menos 8 caracteres.',password); return; }
            if (password.value !== confirm.value) { error('As senhas não ficaram iguais. Confira a confirmação.',confirm); return; }
            finish({email:email.value.trim(),senha:password.value,confirmar_senha:confirm.value});
        },'Criar conta e entrar no NEO');
    }
    async function finish(credentials) {
        if (busy) return;
        clearError(); setBusy(true);
        if (saveStatus) saveStatus.textContent = 'Preparando seu NEO…';
        try {
            const data = await api({action:'finish',...(credentials ? {credentials} : {})});
            forget();
            stage.querySelectorAll('input[type="password"]').forEach(input => { input.value = ''; });
            face('success'); if (saveStatus) saveStatus.textContent = 'Tudo pronto. Bem-vindo ao seu NEO!';
            window.location.assign(data.redirect || 'index.php');
        } catch (failure) {
            setBusy(false);
            if (saveStatus) saveStatus.textContent = 'Suas respostas continuam salvas.';
            if (failure.step && ids().includes(failure.step)) go(failure.step);
            error(failure.message);
        }
    }
    function render() {
        if (!state) return;
        clearError();
        clearAutoAdvance();
        finishSpeech();
        stage.replaceChildren(); stage.dataset.step = view; stage.dataset.substep = String(sub);
        stage.classList.remove('neo-setup-welcome'); stage.setAttribute('aria-busy','false');
        submitCurrent = null;
        if (enterHint) enterHint.textContent = '';
        updateProgress(); face('neutral');
        if (backControl) backControl.hidden = view === 'welcome' || view === 'resumo';
        if (nextControl) nextControl.hidden = true;
        if (view === 'resumo') summary();
        else if (view === 'credentials') credentials();
        else if (view === 'nome') nameStep();
        else if (view === 'gostos') tastesStep();
        else if (view === 'materias') subjectsStep();
        else if (view === 'niveis') perItemStep();
        else if (view === 'meta') goalStep();
        else if (['formatos','gamificacao'].includes(view)) multiStep();
        else singleStep();
    }
    if (companion) {
        companion.setAttribute('aria-label','Manel, voltar para a pergunta atual');
        companion.removeAttribute('aria-expanded');
        companion.title = 'Voltar para a pergunta';
        companion.addEventListener('click',focusQuestion);
    }
    backControl?.addEventListener('click',goBack);
    nextControl?.addEventListener('click',() => { if (!busy && submitCurrent) submitCurrent(); });
    async function presentFace(delay = 640) {
        if (root.classList.contains('has-studies')) { stage.hidden = false; return; }
        const slot = root.querySelector('.study-face-slot');
        if (!slot || !companion) { stage.hidden = false; return; }
        if (!reducedMotion.matches && delay > 0) await new Promise(resolve => window.setTimeout(resolve,delay));
        const previous = companion.getBoundingClientRect();
        root.classList.add('has-studies','is-replying');
        root.querySelector('.study-heading').prepend(slot);
        try {
            if (!reducedMotion.matches && companion.animate) {
                const target = companion.getBoundingClientRect();
                faceFlight = companion.animate([
                    {transform:`translate(${previous.x - target.x}px, ${previous.y - target.y}px) scale(${previous.width / target.width}, ${previous.height / target.height})`},
                    {transform:'translate(0, 0) scale(1)'}
                ],{duration:620,easing:'cubic-bezier(.22, 1, .36, 1)'});
                await Promise.race([
                    faceFlight.finished.catch(() => {}),
                    new Promise(resolve => window.setTimeout(resolve,800))
                ]);
            }
        } finally {
            faceFlight?.cancel();
            faceFlight = null;
            companion.style.transform = '';
            root.classList.remove('is-replying');
            stage.hidden = false;
        }
    }
    window.addEventListener('resize',() => faceFlight?.cancel());
    async function initialize() {
        stage.setAttribute('aria-busy','true');
        try {
            const introPlayed = await waitForLogoIntro();
            if (resetOnLoad) {
                forget();
                applyState(await api({action:'reset'}));
                resetOnLoad = false;
            } else {
                applyState(await api());
            }
            await presentFace(introPlayed ? 500 : 640);
            let previous;
            try { previous = JSON.parse(localStorage.getItem(storageKey) || 'null'); } catch (_) {}
            if (saveStatus) saveStatus.textContent = 'Suas respostas são salvas a cada etapa.';
            if (previous && ids().includes(previous.step) && Date.now() - previous.updatedAt < 30 * 86400000 && (state.step === 'resumo' || ids().indexOf(previous.step) <= ids().indexOf(state.step))) go(previous.step,Number(previous.sub) || 0,previous.draft);
            else if (state.step === 'resumo' || root.dataset.edit === '1' && state.completed) go('resumo');
            else welcome();
        } catch (failure) {
            await presentFace();
            stage.setAttribute('aria-busy','false'); stage.replaceChildren();
            assistant('Nossa conversa já vai começar.','Não consegui carregar sua personalização neste momento.');
            stage.append(button('Tentar novamente',initialize,'is-primary'));
            if (saveStatus) saveStatus.textContent = 'Aguardando conexão com o NEO.'; error(failure.message);
        }
    }
    initialize();
})();
