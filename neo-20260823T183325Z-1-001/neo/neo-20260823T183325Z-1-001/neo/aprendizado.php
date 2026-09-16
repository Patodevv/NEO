<?php
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/materia_icon.php';
require_once __DIR__ . '/services/personalization.php';
exigirLogin();
$usuario=usuarioAtual($pdo);
$userId=(int)$usuario['id'];
$erro='';
$flash=(string)($_SESSION['neo_learning_flash'] ?? '');
unset($_SESSION['neo_learning_flash']);
$examId=(int)($_GET['simulado'] ?? $_POST['simulado_id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validarCsrf();
    try {
        $action=(string)($_POST['acao'] ?? '');
        $destination='aprendizado.php';
        if ($action === 'corrigir') {
            adaptiveSaveCorrection($pdo,$userId,(string)($_POST['insight_key'] ?? ''),(string)($_POST['valor'] ?? ''));
            $_SESSION['neo_learning_flash']='Entendido! Sua correção passa a orientar as próximas recomendações.';
            $destination.='#descobertas';
        } elseif ($action === 'erro') {
            adaptiveUpdateError($pdo,$userId,(int)($_POST['erro_id'] ?? 0),(string)($_POST['tipo_erro'] ?? ''),!empty($_POST['revisado']));
            $_SESSION['neo_learning_flash']='Caderno atualizado. O motivo do erro fica registrado como informado por você.';
            $destination.='#erros';
        } elseif ($action === 'criar_simulado') {
            $examId=adaptiveCreateExam($pdo,$userId,$_POST);
            $destination.='?simulado=' . $examId . '#simulado-atual';
        } elseif ($action === 'responder_simulado') {
            $answers=is_array($_POST['respostas'] ?? null) ? $_POST['respostas'] : [];
            $metrics=[];
            foreach ((is_array($_POST['tempos'] ?? null) ? $_POST['tempos'] : []) as $id=>$seconds) {
                $metrics[(int)$id]=['tempo_segundos'=>$seconds];
            }
            adaptiveGradeExam($pdo,$userId,$examId,$answers,$metrics);
            $_SESSION['neo_learning_flash']='Simulado concluído! Seu mapa e as próximas revisões já foram atualizados.';
            $destination.='?simulado=' . $examId . '#simulado-atual';
        } elseif ($action === 'lembrete') {
            adaptiveDismissReminder($pdo,$userId,(string)($_POST['lembrete_key'] ?? ''));
            $_SESSION['neo_learning_flash']='Combinado. Este lembrete foi dispensado.';
            $destination.='#lembretes';
        } else {
            throw new DomainException('Escolha uma ação válida.');
        }
        header('Location: ' . $destination);
        exit;
    } catch (DomainException $e) {
        $erro=$e->getMessage();
    } catch (Throwable $e) {
        error_log('[NEO][aprendizado] ' . $e->getMessage());
        $erro='Não consegui salvar agora. Tente novamente em instantes.';
    }
}

$summary=adaptiveSummary($pdo,$userId);
$errors=adaptiveErrors($pdo,$userId);
$exam=null;
if ($examId) {
    try { $exam=adaptiveExam($pdo,$userId,$examId); }
    catch (DomainException $e) { $erro=$e->getMessage(); }
}
$subjects=[];
foreach ($summary['map'] as $content) { $subjects[$content['materia_id']]=$content['materia_nome']; }
$stmt=$pdo->prepare('SELECT id,tipo,acertos,criado_em,concluido_em FROM neo_simulados WHERE user_id=? ORDER BY id DESC LIMIT 6');
$stmt->execute([$userId]);
$recentExams=$stmt->fetchAll(PDO::FETCH_ASSOC);
$e=static fn(mixed $value):string => htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
$tituloPagina='Meu aprendizado';
$paginaAtual='aprendizado';
$usaSidebar=true;
$cssPaginas=['aprendizado'];
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/sidebar.php';
?>
<main class="main">
    <?php require __DIR__ . '/includes/topbar.php'; ?>
    <div class="neo-page-shell learning-page">
        <section class="neo-panel learning-hero">
            <div class="learning-hero-copy"><span class="neo-page-kicker">Uma experiência que evolui com você</span><h1>O que o NEO aprendeu<br>sobre você</h1><p>Seu jeito de estudar, seus próximos passos e espaço para ajustar as recomendações.</p><a class="learning-button learning-profile-button neo-star-hover" href="register.php?editar=1"><?= estrelaHoverNeo() ?><span>Rever minha personalização</span><span aria-hidden="true">→</span></a></div>
            <div class="learning-manel" aria-hidden="true"><iframe src="rosto_azul.html" title="Manel" tabindex="-1"></iframe></div>
        </section>
        <?php if ($flash): ?><div class="learning-notice" role="status"><?= $e($flash) ?></div><?php endif; ?>
        <?php if ($erro): ?><div class="learning-notice is-error" role="alert"><?= $e($erro) ?></div><?php endif; ?>

        <nav class="learning-nav" aria-label="Nesta página">
            <a class="neo-star-hover" href="#rotina"><?= estrelaHoverNeo() ?><span>Minha semana</span></a>
            <a class="neo-star-hover" href="#mapa"><?= estrelaHoverNeo() ?><span>Mapa de conhecimento</span></a>
            <a class="neo-star-hover" href="#erros"><?= estrelaHoverNeo() ?><span>Caderno de erros</span></a>
            <a class="neo-star-hover" href="#simulados"><?= estrelaHoverNeo() ?><span>Simulados</span></a>
            <a class="neo-star-hover" href="#descobertas"><?= estrelaHoverNeo() ?><span>Seu jeito de estudar</span></a>
        </nav>

        <div class="learning-stats">
            <article class="neo-panel"><span>Respostas registradas</span><b><?= $e($summary['stats']['answers']) ?></b><small><?= $e($summary['stats']['errors']) ?> erro(s) para aprender</small></article>
            <article class="neo-panel"><span>Taxa de acerto</span><b><?= $summary['stats']['accuracy'] === null ? '—' : $e($summary['stats']['accuracy']) . '%' ?></b><small><?= $summary['stats']['answers'] ? 'Nas respostas acompanhadas' : 'Comece para criar sua referência' ?></small></article>
            <article class="neo-panel"><span>Tempo por resposta</span><b><?= $summary['stats']['avg_seconds'] === null ? '—' : $e($summary['stats']['avg_seconds']) . 's' ?></b><small><?= $e($summary['stats']['timed_answers']) ?> resposta(s) com tempo medido</small></article>
            <article class="neo-panel"><span>Seu ritmo real</span><b><?= $e($summary['stats']['active_days_28']) ?> <em>dias</em></b><small>Com estudos nos últimos 28 dias</small></article>
        </div>

        <section id="rotina" class="neo-panel learning-section">
            <div class="learning-section-head"><div><span class="neo-page-kicker">Próximos passos</span><h2>Sua semana, do seu jeito</h2></div><span class="learning-pill"><?= $e($summary['routine']['days_done']) ?>/<?= $e($summary['routine']['days_goal']) ?> dias estudados</span></div>
            <p class="learning-muted">Sessões de <?= $e($summary['routine']['minutes']) ?> minutos. Recalculadas com sua presença, prioridades, respostas e prazo da meta.</p>
            <?php foreach ($summary['routine']['messages'] as $message): ?><p class="learning-message"><?= $e($message) ?></p><?php endforeach; ?>
            <div class="learning-sessions">
                <?php foreach ($summary['routine']['sessions'] as $session): ?>
                    <article class="learning-session"><div class="learning-date"><b><?= $e(date('d',strtotime($session['date']))) ?></b><span><?= $e(['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][(int)date('w',strtotime($session['date']))]) ?></span></div><div class="learning-session-copy"><small><?= $e($session['subject']) ?> · <?= $e($session['minutes']) ?> min</small><h3><?= $e($session['content']) ?></h3><p><?= $e($session['activity']) ?> · cerca de <?= $e($session['questions']) ?> questões</p></div><a class="learning-button" href="<?= $session['activity'] === 'Explorar conteúdo + diagnóstico' ? 'livro.php' : 'questoes.php' ?>?conteudo_id=<?= $e($session['content_id']) ?>">Começar <span aria-hidden="true">→</span></a></article>
                <?php endforeach; ?>
                <?php if (!$summary['routine']['sessions']): ?><p class="learning-empty"><?= $summary['map'] ? 'Sua meta de dias foi cumprida. Você pode revisar ou criar um simulado quando quiser.' : 'Escolha suas matérias na personalização para montar a primeira semana.' ?></p><?php endif; ?>
            </div>
            <div id="lembretes" class="learning-reminder"><div><b>Seus lembretes</b><p>Lembretes aparecem quando você abre o NEO.</p><?php if ($summary['reminder']): ?><small>Horário escolhido: <?= $e($summary['reminder']['time']) ?> · <?= $e(['diario'=>'Todos os dias','dias_escolhidos'=>'Nos dias escolhidos','semanal'=>'Semanalmente'][$summary['reminder']['frequency']] ?? 'Nos dias escolhidos') ?></small><?php else: ?><small>Desativados na sua personalização.</small><?php endif; ?></div><?php if (!empty($summary['reminder']['due'])): ?><form method="post"><?= campoCsrf() ?><input type="hidden" name="acao" value="lembrete"><input type="hidden" name="lembrete_key" value="<?= $e($summary['reminder']['key']) ?>"><p><?= $e($summary['reminder']['text']) ?></p><button class="learning-button" type="submit">Entendi, Manel</button></form><?php endif; ?></div>
        </section>

        <section id="mapa" class="neo-panel learning-section">
            <div class="learning-section-head"><div><span class="neo-page-kicker">Cada passo conta</span><h2>Seu mapa de conhecimento</h2></div><span class="learning-pill"><?= count($summary['map']) ?> conteúdos</span></div>
            <p class="learning-muted">O domínio é uma estimativa de 0 a 100 baseada nas últimas 30 respostas por conteúdo. Poucas respostas pedem mais diagnóstico. Sem prática, usamos o nível informado para começar.</p>
            <?php if (!$summary['stats']['answers']): ?><p class="learning-message">Ainda estamos nos conhecendo. Seu nível informado orienta a primeira atividade; ele não é uma medida de domínio.</p><?php endif; ?>
            <div class="learning-map">
                <?php foreach ($summary['map'] as $content): ?>
                    <article class="learning-content"><div class="learning-content-top"><span><?= $e($content['materia_nome']) ?></span><span class="learning-state <?= $content['status'] === 'Dominado' ? 'is-mastered' : '' ?>"><?= $e($content['status']) ?></span></div><h3><?= $e($content['titulo']) ?></h3><div class="learning-meter-row"><span><?= $content['samples'] ? $e($content['score']) . '%' : 'Sem medição' ?></span><span><?= $e($content['samples']) ?> resposta(s)</span></div><progress max="100" value="<?= $e($content['score']) ?>" aria-label="Domínio estimado de <?= $e($content['titulo']) ?>"><?= $e($content['score']) ?>%</progress><div class="learning-content-details"><span>Próxima dificuldade: <?= $e($content['difficulty']) ?>/12</span><span>Prioridade <?= $e(['alta'=>'alta','media'=>'média','baixa'=>'baixa'][$content['priority']] ?? 'média') ?></span><?php if ($content['trend'] !== null): ?><span>Evolução: <?= $content['trend'] > 0 ? '+' : '' ?><?= $e($content['trend']) ?> pontos de acerto</span><?php endif; ?><?php if ($content['review_due']): ?><span class="learning-review-due"><?= $e($content['review_due']) ?> revisão(ões) pendente(s)</span><?php endif; ?></div><a class="learning-link" href="questoes.php?conteudo_id=<?= $e($content['id']) ?>"><?= $content['review_due'] ? 'Revisar conteúdo' : 'Continuar aprendendo' ?> <span aria-hidden="true">→</span></a></article>
                <?php endforeach; ?>
            </div>
            <?php if (!$summary['map']): ?><p class="learning-empty">Seu mapa aparece assim que você escolher os primeiros conteúdos.</p><?php endif; ?>
        </section>

        <section id="erros" class="neo-panel learning-section">
            <div class="learning-section-head"><div><span class="neo-page-kicker">Errar também é aprender</span><h2>Seu caderno de erros</h2></div><span class="learning-pill"><?= count($errors) ?> registros recentes</span></div>
            <p class="learning-muted">Guardo a questão, a explicação e a próxima revisão. Você pode contar o motivo do erro; eu não vou adivinhar.</p>
            <?php foreach ($errors as $error): ?>
                <details class="learning-error"><summary><span><small><?= $e($error['materia_nome']) ?> · <?= $e($error['titulo']) ?></small><b><?= $e(mb_strimwidth($error['enunciado'],0,140,'…')) ?></b></span><span class="learning-state"><?= $error['revisado_em'] ? 'Revisado' : 'Revisar ' . $e(date('d/m',strtotime($error['revisar_em']))) ?></span></summary><div class="learning-error-body"><p><?= nl2br($e($error['enunciado'])) ?></p><div class="learning-error-meta"><span>Sua resposta: <?= $e($error['resposta']) ?> · correta: <?= $e($error['correta']) ?></span><span>Tentativa <?= $e($error['tentativa']) ?> · <?= $e($error['formato']) ?></span><span>Habilidade: <?= $e($error['habilidade'] ?: 'Ainda não marcada nesta questão') ?></span><span>Tipo: <?= $e(str_replace('_',' ',$error['tipo_questao'])) ?></span></div><blockquote><?= nl2br($e($error['explicacao'] ?: 'A explicação não foi salva nesta atividade. Reabra o conteúdo para revisar o conceito e solicitar ajuda ao Manel.')) ?></blockquote><form method="post" class="learning-error-form"><?= campoCsrf() ?><input type="hidden" name="acao" value="erro"><input type="hidden" name="erro_id" value="<?= $e($error['id']) ?>"><label>O que aconteceu?<select name="tipo_erro"><?php foreach (adaptiveErrorTypes() as $value=>$label): ?><option value="<?= $e($value) ?>" <?= $error['tipo_erro']===$value ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label><label class="learning-check"><input type="checkbox" name="revisado" value="1" <?= $error['revisado_em'] ? 'checked' : '' ?>> Já revisei a explicação</label><button class="learning-button" type="submit">Salvar revisão</button></form><div class="learning-similar"><b>Pratique questões semelhantes</b><?php foreach ($error['similar'] as $similar): ?><a href="questoes.php?conteudo_id=<?= $e($error['conteudo_id']) ?>#questao-<?= $e($similar['id']) ?>"><?= $e(mb_strimwidth($similar['enunciado'],0,110,'…')) ?> <span aria-hidden="true">↗</span></a><?php endforeach; ?><?php if (!$error['similar']): ?><a href="questoes.php?conteudo_id=<?= $e($error['conteudo_id']) ?>">Gerar uma nova atividade deste conteúdo →</a><?php endif; ?></div></div></details>
            <?php endforeach; ?>
            <?php if (!$errors): ?><div class="learning-empty"><b>Um espaço para suas próximas descobertas.</b><p>Quando você errar uma questão, a revisão aparece aqui automaticamente.</p></div><?php endif; ?>
        </section>

        <section id="simulados" class="neo-panel learning-section">
            <div class="learning-section-head"><div><span class="neo-page-kicker">Um desafio na medida</span><h2>Monte seu simulado</h2></div><span class="learning-pill">8 jeitos de praticar</span></div>
            <p class="learning-muted">Usamos as questões já salvas nos seus conteúdos. Os filtros de estilo só incluem questões identificadas com aquele estilo; se faltarem questões, avisamos.</p>
            <form method="post" class="learning-exam-builder" id="examBuilder"><?= campoCsrf() ?><input type="hidden" name="acao" value="criar_simulado"><label>Como quer praticar?<select name="tipo" id="examType"><?php foreach (adaptiveExamTypes() as $value=>$label): ?><option value="<?= $e($value) ?>"><?= $e($label) ?></option><?php endforeach; ?></select></label><label data-exam-filter="materia" hidden>Matéria<select name="materia_id"><?php foreach ($subjects as $id=>$name): ?><option value="<?= $e($id) ?>"><?= $e($name) ?></option><?php endforeach; ?></select></label><label data-exam-filter="conteudo" hidden>Conteúdo<select name="conteudo_id"><?php foreach ($summary['map'] as $content): ?><option value="<?= $e($content['id']) ?>"><?= $e($content['materia_nome'] . ' · ' . $content['titulo']) ?></option><?php endforeach; ?></select></label><label data-exam-filter="dificuldade" hidden>Dificuldade<select name="dificuldade"><?php foreach (range(1,12) as $level): ?><option value="<?= $level ?>"><?= $level ?> de 12</option><?php endforeach; ?></select></label><label data-exam-filter="estilo" hidden>Estilo de prova<select name="estilo"><option value="geral">Geral</option><option value="enem">ENEM</option><option value="vestibular">Vestibular</option><option value="concurso">Concurso</option></select></label><label>Quantidade de questões<input type="number" min="1" max="30" value="10" name="quantidade" required></label><label>Tempo disponível (minutos)<input type="number" min="5" max="150" value="<?= $e($summary['routine']['minutes']) ?>" name="minutos" required></label><button class="learning-button is-primary" type="submit">Preparar meu simulado <span aria-hidden="true">→</span></button></form>
            <?php if ($recentExams): ?><div class="learning-recent-exams"><b>Seus últimos simulados</b><?php foreach ($recentExams as $previous): ?><a href="aprendizado.php?simulado=<?= $e($previous['id']) ?>#simulado-atual"><span><?= $e(adaptiveExamTypes()[$previous['tipo']] ?? 'Simulado') ?> · <?= $e(date('d/m',strtotime($previous['criado_em']))) ?></span><span><?= $previous['concluido_em'] ? $e($previous['acertos']) . ' acerto(s) · Ver resultado' : 'Continuar →' ?></span></a><?php endforeach; ?></div><?php endif; ?>
        </section>

        <?php if ($exam): ?>
        <section id="simulado-atual" class="neo-panel learning-section">
            <div class="learning-section-head"><div><span class="neo-page-kicker"><?= $exam['concluido_em'] ? 'Mais um passo dado' : 'Concentre-se, uma questão de cada vez' ?></span><h2><?= $e(adaptiveExamTypes()[$exam['tipo']] ?? 'Seu simulado') ?></h2></div><span class="learning-pill"><?= count($exam['questions']) ?> questões · <?= $e($exam['filters']['minutos'] ?? 20) ?> min</span></div>
            <?php if (count($exam['questions']) < (int)($exam['filters']['quantidade_solicitada'] ?? 0)): ?><p class="learning-message">Seu banco tinha <?= count($exam['questions']) ?> questões compatíveis. Preparei todas as disponíveis.</p><?php endif; ?>
            <?php if ($exam['concluido_em']): ?><div class="learning-result"><b><?= $e($exam['acertos']) ?>/<?= count($exam['questions']) ?></b><p>acertos neste simulado. As respostas já estão no seu mapa de aprendizado.</p></div><?php else: ?><p class="learning-muted">O tempo é uma referência para sua sessão. Sua prova fica salva para você continuar depois.</p><?php endif; ?>
            <form method="post" id="learningExam" data-finished="<?= $exam['concluido_em'] ? '1' : '0' ?>"><?= campoCsrf() ?><input type="hidden" name="acao" value="responder_simulado"><input type="hidden" name="simulado_id" value="<?= $e($exam['id']) ?>">
                <?php foreach ($exam['questions'] as $index=>$q): $selected=$exam['answers'][$q['id']] ?? ($_POST['respostas'][$q['id']] ?? ''); ?>
                <fieldset class="learning-question" data-question="<?= $e($q['id']) ?>"><legend><span>Questão <?= $index+1 ?></span><small><?= $e($q['materia_nome']) ?> · <?= $e($q['titulo']) ?></small></legend><p><?= nl2br($e($q['enunciado'])) ?></p><input type="hidden" name="tempos[<?= $e($q['id']) ?>]" value="0" data-question-time><?php foreach (['A','B','C','D'] as $letter): ?><label class="learning-answer <?= $exam['concluido_em'] && $letter === strtoupper($q['correta']) ? 'is-correct' : '' ?>"><input type="radio" name="respostas[<?= $e($q['id']) ?>]" value="<?= $letter ?>" <?= $selected === $letter ? 'checked' : '' ?> <?= $exam['concluido_em'] ? 'disabled' : 'required' ?>><span><b><?= $letter ?>.</b> <?= $e($q['opcao_' . strtolower($letter)]) ?></span></label><?php endforeach; ?><?php if ($exam['concluido_em']): ?><div class="learning-feedback"><b><?= $selected === strtoupper($q['correta']) ? 'Você acertou!' : 'Vamos revisar este ponto.' ?></b><p><?= nl2br($e(trim((string)($q['feedback_' . strtolower((string)$selected)] ?? '') . "\n" . (string)($q['explicacao_correta'] ?? '')) ?: 'Reabra o conteúdo e peça uma explicação ao Manel.')) ?></p></div><?php endif; ?></fieldset>
                <?php endforeach; ?>
                <?php if (!$exam['concluido_em']): ?><button class="learning-button is-primary" type="submit">Concluir e ver meu resultado →</button><?php endif; ?>
            </form>
        </section>
        <?php endif; ?>

        <section id="descobertas" class="neo-panel learning-section">
            <div class="learning-section-head"><div><span class="neo-page-kicker">Você tem a palavra final</span><h2>É assim que você gosta de aprender?</h2></div></div><p class="learning-muted">Eu separo o que você me contou das hipóteses que surgem com a prática. Corrija qualquer ponto e vou usar sua preferência.</p>
            <div class="learning-insights"><?php foreach ($summary['insights'] as $insight): ?><article class="learning-insight"><span class="learning-insight-source"><?= $e($insight['source']) ?></span><h3><?= $e($insight['title']) ?></h3><p><?= $e($insight['text']) ?></p><details><summary>Isso não parece certo</summary><form method="post"><?= campoCsrf() ?><input type="hidden" name="acao" value="corrigir"><input type="hidden" name="insight_key" value="<?= $e($insight['key']) ?>"><label>O que combina mais com você?<select name="valor"><?php foreach (adaptiveCorrectionOptions($insight['key']) as $value=>$label): ?><option value="<?= $e($value) ?>" <?= (string)$insight['value']===(string)$value ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label><button class="learning-button" type="submit">Atualizar meu jeito de estudar</button></form></details></article><?php endforeach; ?></div>
        </section>
    </div>
</main>
<script>
(() => {
    const type=document.getElementById('examType');
    const filters=Array.from(document.querySelectorAll('[data-exam-filter]'));
    function updateFilters(){ filters.forEach(label=>{label.hidden=label.dataset.examFilter!==type.value;}); }
    type.addEventListener('change',updateFilters); updateFilters();
    const form=document.getElementById('learningExam');
    if (!form || form.dataset.finished==='1') return;
    const questions=Array.from(form.querySelectorAll('[data-question]'));
    let active=null; let since=performance.now();
    const times=new Map();
    const save=()=>{ const now=performance.now(); if(active && !document.hidden){times.set(active,(times.get(active)||0)+(now-since)/1000);} since=now; };
    const observer=new IntersectionObserver(entries=>{save(); entries.forEach(entry=>{if(entry.isIntersecting){active=entry.target;}});},{threshold:.5});
    questions.forEach(question=>{observer.observe(question); question.addEventListener('focusin',()=>{save();active=question;});});
    document.addEventListener('visibilitychange',()=>{since=performance.now();});
    form.addEventListener('submit',()=>{save(); questions.forEach(question=>{question.querySelector('[data-question-time]').value=Math.min(7200,times.get(question)||0).toFixed(1);});});
})();
</script>
</body>
</html>
