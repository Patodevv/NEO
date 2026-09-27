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
$recentExams=array_values(array_filter($recentExams,static function(array $previous) use ($pdo,$userId):bool {
    try { adaptiveExam($pdo,$userId,(int)$previous['id']); return true; }
    catch (DomainException) { return false; }
}));
$nextSession=$summary['routine']['sessions'][0] ?? null;
$pendingReviews=array_sum(array_map(static fn(array $content):int => (int)($content['review_due'] ?? 0),$summary['map']));
$masteredCount=count(array_filter($summary['map'],static fn(array $content):bool => ($content['status'] ?? '') === 'Dominado'));
$mapBySubject=[];
foreach ($summary['map'] as $content) {
    $subjectId=(int)$content['materia_id'];
    if (!isset($mapBySubject[$subjectId])) {
        $mapBySubject[$subjectId]=['name'=>(string)$content['materia_nome'],'contents'=>[]];
    }
    $mapBySubject[$subjectId]['contents'][]=$content;
}
$sessionsBySubject=[];
foreach ($summary['routine']['sessions'] as $session) {
    $subjectName=(string)$session['subject'];
    $subjectKey=mb_strtolower($subjectName,'UTF-8');
    if (!isset($sessionsBySubject[$subjectKey])) {
        $sessionsBySubject[$subjectKey]=['name'=>$subjectName,'sessions'=>[]];
    }
    $sessionsBySubject[$subjectKey]['sessions'][]=$session;
}
$e=static fn(mixed $value):string => htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
$tituloPagina='Meu aprendizado';
$paginaAtual='aprendizado';
$usaSidebar=true;
$cssPaginas=['aprendizado'];
$bodyClasses=['neo-learning-page'];
require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/sidebar.php';
?>
<main class="main">
    <?php require __DIR__ . '/includes/topbar.php'; ?>
    <?php require __DIR__ . '/includes/learning-page.php'; ?>
</main>
<script>
(() => {
    document.querySelectorAll('[data-week-browser]').forEach(browser => {
        const folders=browser.querySelector('[data-week-folders]');
        const panels=Array.from(browser.querySelectorAll('[data-week-panel]'));
        let activeTrigger=null;
        browser.querySelectorAll('[data-week-open]').forEach(button => {
            button.addEventListener('click',() => {
                const panel=panels.find(item=>item.dataset.weekPanel===button.dataset.weekOpen);
                if (!panel) return;
                activeTrigger=button;
                folders.hidden=true;
                panels.forEach(item=>{item.hidden=item!==panel;});
                browser.querySelectorAll('[data-week-open]').forEach(item=>item.setAttribute('aria-expanded',item===button?'true':'false'));
                panel.querySelector('[data-week-back]')?.focus();
            });
        });
        browser.querySelectorAll('[data-week-back]').forEach(button => {
            button.addEventListener('click',() => {
                panels.forEach(item=>{item.hidden=true;});
                folders.hidden=false;
                browser.querySelectorAll('[data-week-open]').forEach(item=>item.setAttribute('aria-expanded','false'));
                activeTrigger?.focus();
            });
        });
    });
    const type=document.getElementById('examType');
    const filters=Array.from(document.querySelectorAll('[data-exam-filter]'));
    function updateFilters(){ filters.forEach(label=>{label.hidden=!type || label.dataset.examFilter!==type.value;}); }
    if (type) { type.addEventListener('change',updateFilters); updateFilters(); }
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
