<div class="neo-page-shell learning-page">
    <?php if ($flash): ?><div class="learning-notice" role="status"><?= $e($flash) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="learning-notice is-error" role="alert"><?= $e($erro) ?></div><?php endif; ?>

    <section class="neo-panel learning-overview" aria-labelledby="learning-title">
        <header class="learning-overview-head">
            <div>
                <span class="neo-page-kicker">Meu aprendizado</span>
                <h1 id="learning-title">Seu plano de estudo</h1>
            </div>
            <a class="learning-button learning-profile-button neo-star-hover" href="register.php?editar=1" data-manel-tip="Ajuste suas preferências para o NEO adaptar matérias, rotina e exemplos ao seu jeito.">
                <?= estrelaHoverNeo() ?><span>Ajustar perfil</span>
            </a>
        </header>

        <div class="learning-overview-grid">
            <?php if ($nextSession): ?>
                <article class="learning-next-mission <?= classeTemaMateria((string)$nextSession['subject']) ?>">
                    <div class="learning-mission-icon" aria-hidden="true"><?= iconeMateriaDashboard((string)$nextSession['subject']) ?></div>
                    <div class="learning-mission-copy">
                        <span>Próxima missão · <?= $e($nextSession['subject']) ?></span>
                        <h2><?= $e($nextSession['content']) ?></h2>
                        <p><?= $e($nextSession['minutes']) ?> min · <?= $e($nextSession['activity']) ?> · <?= $e($nextSession['questions']) ?> questões</p>
                    </div>
                    <a class="learning-mission-action neo-star-hover" href="<?= $nextSession['activity'] === 'Explorar conteúdo + diagnóstico' ? 'livro.php' : 'questoes.php' ?>?conteudo_id=<?= $e($nextSession['content_id']) ?>" data-manel-tip="Comece sua próxima atividade planejada: <?= $e($nextSession['content']) ?>.">
                        <?= estrelaHoverNeo() ?><span>Começar</span><span aria-hidden="true">→</span>
                    </a>
                </article>
            <?php else: ?>
                <article class="learning-next-mission is-empty">
                    <div class="learning-mission-icon" aria-hidden="true">✦</div>
                    <div class="learning-mission-copy"><span>Próxima missão</span><h2><?= $summary['map'] ? 'Semana concluída' : 'Escolha sua primeira matéria' ?></h2><p><?= $summary['map'] ? 'Você pode revisar um conteúdo ou praticar com um simulado.' : 'O NEO monta seu primeiro caminho assim que você escolher o que quer estudar.' ?></p></div>
                    <a class="learning-mission-action neo-star-hover" href="<?= $summary['map'] ? '#mapa' : 'materias.php' ?>" data-manel-tip="<?= $summary['map'] ? 'Veja seu progresso em cada matéria.' : 'Escolha uma matéria para criar seu primeiro caminho de estudos.' ?>"><?= estrelaHoverNeo() ?><span><?= $summary['map'] ? 'Ver progresso' : 'Escolher matéria' ?></span><span aria-hidden="true">→</span></a>
                </article>
            <?php endif; ?>

            <div class="learning-scoreboard" aria-label="Resumo do seu progresso">
                <div><b><?= $e($summary['stats']['answers']) ?></b><span>respostas</span></div>
                <div><b><?= $summary['stats']['accuracy'] === null ? '—' : $e($summary['stats']['accuracy']) . '%' ?></b><span>de acerto</span></div>
                <div><b><?= $e($summary['stats']['active_days_28']) ?></b><span>dias ativos</span></div>
                <div><b><?= $e($pendingReviews) ?></b><span>revisões</span></div>
            </div>
        </div>
    </section>

    <nav class="learning-shortcuts" aria-label="Áreas do meu aprendizado">
        <a class="neo-star-hover" href="#rotina" data-manel-tip="Abra seu plano da semana e escolha uma matéria para ver as próximas atividades."><?= estrelaHoverNeo() ?><span class="learning-shortcut-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3v3M17 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg></span><span><b>Minha semana</b><small><?= $e($summary['routine']['days_done']) ?>/<?= $e($summary['routine']['days_goal']) ?> dias concluídos</small></span></a>
        <a class="neo-star-hover" href="#mapa" data-manel-tip="Veja o nível, o desempenho e as revisões de cada matéria."><?= estrelaHoverNeo() ?><span class="learning-shortcut-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m4 17 5-5 4 3 7-8M4 21h16"/></svg></span><span><b>Meu progresso</b><small><?= $e($masteredCount) ?> conteúdo(s) dominado(s)</small></span></a>
        <a class="neo-star-hover" href="#erros" data-manel-tip="Revise as questões que deram trabalho e entenda onde melhorar."><?= estrelaHoverNeo() ?><span class="learning-shortcut-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h12v18H6zM9 7h6M9 11h6M9 15h3"/></svg></span><span><b>Caderno de erros</b><small><?= count($errors) ?> revisão(ões) recente(s)</small></span></a>
        <a class="neo-star-hover" href="#simulados" data-manel-tip="Monte um desafio com as matérias, conteúdos e dificuldade que você escolher."><?= estrelaHoverNeo() ?><span class="learning-shortcut-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 3h8M9 3v3h6V3M6 5h12v16H6zM9 11h6M9 15h4"/></svg></span><span><b>Simulados</b><small><?= count($recentExams) ?> atividade(s) recente(s)</small></span></a>
    </nav>

    <section id="rotina" class="neo-panel learning-section learning-routine-section">
        <div class="learning-section-head">
            <div><span class="neo-page-kicker">Plano da semana</span><h2>Escolha uma matéria</h2></div>
            <span class="learning-pill"><?= $e($summary['routine']['days_done']) ?>/<?= $e($summary['routine']['days_goal']) ?> dias</span>
        </div>
        <div class="learning-week-browser" data-week-browser>
            <div class="learning-week-folders" data-week-folders>
                <?php foreach ($sessionsBySubject as $subjectKey=>$subject): ?>
                    <?php $routineFolderId = 'rotina-' . adaptiveSlug($subject['name']); ?>
                    <button id="<?= $e($routineFolderId) ?>-trigger" class="learning-week-folder neo-star-hover <?= classeTemaMateria($subject['name']) ?>" type="button" data-week-open="<?= $e($subjectKey) ?>" aria-controls="<?= $e($routineFolderId) ?>-panel" aria-expanded="false" aria-label="Abrir atividades de <?= $e($subject['name']) ?>" data-manel-tip="Mostra as atividades planejadas de <?= $e($subject['name']) ?>.">
                        <?= estrelaHoverNeo() ?>
                        <span class="learning-week-folder-icon" aria-hidden="true"><?= iconeMateriaDashboard($subject['name']) ?></span>
                        <strong><?= $e($subject['name']) ?></strong>
                        <small><?= count($subject['sessions']) ?> atividade(s)</small>
                    </button>
                <?php endforeach; ?>
                <?php if (!$sessionsBySubject): ?><div class="learning-empty"><b><?= $summary['map'] ? 'Semana concluída.' : 'Nenhuma matéria na rotina.' ?></b></div><?php endif; ?>
            </div>
            <?php foreach ($sessionsBySubject as $subjectKey=>$subject): ?>
                <?php $routineFolderId = 'rotina-' . adaptiveSlug($subject['name']); ?>
                <div id="<?= $e($routineFolderId) ?>-panel" class="learning-week-panel <?= classeTemaMateria($subject['name']) ?>" data-week-panel="<?= $e($subjectKey) ?>" aria-labelledby="<?= $e($routineFolderId) ?>-trigger" hidden>
                    <header class="learning-week-panel-head">
                        <button class="learning-week-back neo-star-hover" type="button" data-week-back aria-label="Voltar para as matérias" data-manel-tip="Volta para as pastas das matérias."><?= estrelaHoverNeo() ?><span aria-hidden="true">←</span> Voltar</button>
                        <div><span class="learning-week-panel-icon" aria-hidden="true"><?= iconeMateriaDashboard($subject['name']) ?></span><h3><?= $e($subject['name']) ?></h3></div>
                        <span class="learning-pill"><?= count($subject['sessions']) ?> atividade(s)</span>
                    </header>
                    <div class="learning-week-list">
                        <?php foreach ($subject['sessions'] as $index=>$session): ?>
                            <article class="learning-session <?= $index === 0 ? 'is-next' : '' ?>">
                                <div class="learning-date"><b><?= $e(date('d',strtotime($session['date']))) ?></b><span><?= $e(['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][(int)date('w',strtotime($session['date']))]) ?></span></div>
                                <div class="learning-session-copy"><h3><?= $e($session['content']) ?></h3><p><?= $e($session['minutes']) ?> min · <?= $e($session['questions']) ?> questões</p></div>
                                <a class="learning-button neo-star-hover" href="<?= $session['activity'] === 'Explorar conteúdo + diagnóstico' ? 'livro.php' : 'questoes.php' ?>?conteudo_id=<?= $e($session['content_id']) ?>" data-manel-tip="Abre a atividade <?= $e($session['content']) ?>."><?= estrelaHoverNeo() ?><span><?= $index === 0 ? 'Começar' : 'Abrir' ?></span><span aria-hidden="true">→</span></a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div id="lembretes" class="learning-reminder">
            <div><b>Lembretes</b><?php if ($summary['reminder']): ?><p><?= $e($summary['reminder']['time']) ?> · <?= $e(['diario'=>'Todos os dias','dias_escolhidos'=>'Nos dias escolhidos','semanal'=>'Semanalmente'][$summary['reminder']['frequency']] ?? 'Nos dias escolhidos') ?></p><?php else: ?><p>Desativados no seu perfil.</p><?php endif; ?></div>
            <?php if (!empty($summary['reminder']['due'])): ?><form method="post"><?= campoCsrf() ?><input type="hidden" name="acao" value="lembrete"><input type="hidden" name="lembrete_key" value="<?= $e($summary['reminder']['key']) ?>"><span><?= $e($summary['reminder']['text']) ?></span><button class="learning-button neo-star-hover" type="submit"><?= estrelaHoverNeo() ?>Dispensar</button></form><?php endif; ?>
        </div>
    </section>

    <section id="mapa" class="neo-panel learning-section">
        <div class="learning-section-head"><div><span class="neo-page-kicker">Progresso por matéria</span><h2>O que você já avançou</h2></div><span class="learning-pill"><?= count($summary['map']) ?> conteúdos</span></div>
        <div class="learning-week-browser learning-progress-browser" data-week-browser>
            <div class="learning-week-folders" data-week-folders>
                <?php foreach ($mapBySubject as $subjectId=>$subject): ?>
                    <button id="progresso-<?= $e($subjectId) ?>-trigger" class="learning-week-folder neo-star-hover <?= classeTemaMateria($subject['name']) ?>" type="button" data-week-open="progresso-<?= $e($subjectId) ?>" aria-controls="progresso-<?= $e($subjectId) ?>-panel" aria-expanded="false" aria-label="Abrir progresso de <?= $e($subject['name']) ?>" data-manel-tip="Mostra seu progresso e suas revisões em <?= $e($subject['name']) ?>.">
                        <?= estrelaHoverNeo() ?>
                        <span class="learning-week-folder-icon" aria-hidden="true"><?= iconeMateriaDashboard($subject['name']) ?></span>
                        <strong><?= $e($subject['name']) ?></strong>
                        <small><?= count($subject['contents']) ?> conteúdo(s)</small>
                    </button>
                <?php endforeach; ?>
                <?php if (!$mapBySubject): ?><div class="learning-empty"><b>Seu progresso começa com uma matéria.</b><a class="learning-button" href="materias.php">Escolher matéria →</a></div><?php endif; ?>
            </div>
            <?php foreach ($mapBySubject as $subjectId=>$subject): ?>
                <div id="progresso-<?= $e($subjectId) ?>-panel" class="learning-week-panel <?= classeTemaMateria($subject['name']) ?>" data-week-panel="progresso-<?= $e($subjectId) ?>" aria-labelledby="progresso-<?= $e($subjectId) ?>-trigger" hidden>
                    <header class="learning-week-panel-head">
                        <button class="learning-week-back neo-star-hover" type="button" data-week-back aria-label="Voltar para as matérias" data-manel-tip="Volta para as pastas das matérias."><?= estrelaHoverNeo() ?><span aria-hidden="true">←</span> Voltar</button>
                        <div><span class="learning-week-panel-icon" aria-hidden="true"><?= iconeMateriaDashboard($subject['name']) ?></span><h3><?= $e($subject['name']) ?></h3></div>
                        <span class="learning-pill"><?= count($subject['contents']) ?> conteúdo(s)</span>
                    </header>
                    <div class="learning-week-list">
                        <?php foreach ($subject['contents'] as $content): ?>
                            <article class="learning-content">
                                <div class="learning-content-main"><span class="learning-state <?= $content['status'] === 'Dominado' ? 'is-mastered' : '' ?>"><?= $e($content['status']) ?></span><h3><?= $e($content['titulo']) ?></h3><div class="learning-meter-row"><span><?= $content['samples'] ? $e($content['score']) . '%' : 'Ainda sem medição' ?></span><span><?= $e($content['samples']) ?> resposta(s)</span></div><progress max="100" value="<?= $e($content['score']) ?>" aria-label="Domínio estimado de <?= $e($content['titulo']) ?>"><?= $e($content['score']) ?>%</progress></div>
                                <div class="learning-content-action"><?php if ($content['review_due']): ?><small><?= $e($content['review_due']) ?> revisão(ões) pendente(s)</small><?php else: ?><small>Dificuldade <?= $e($content['difficulty']) ?>/12</small><?php endif; ?><a class="learning-button neo-star-hover" href="questoes.php?conteudo_id=<?= $e($content['id']) ?>"><?= estrelaHoverNeo() ?><span><?= $content['review_due'] ? 'Revisar' : 'Continuar' ?></span><span aria-hidden="true">→</span></a></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="erros" class="neo-panel learning-section learning-tool-section">
        <div class="learning-section-head"><div><span class="neo-page-kicker">Revisões inteligentes</span><h2>Caderno de erros</h2></div><span class="learning-pill"><?= count($errors) ?> recentes</span></div>
        <div class="learning-errors">
            <?php foreach ($errors as $error): ?>
                <details class="learning-error"><summary><span><small><?= $e($error['materia_nome']) ?> · <?= $e($error['titulo']) ?></small><b><?= $e(mb_strimwidth($error['enunciado'],0,140,'…')) ?></b></span><span class="learning-error-status"><span class="learning-state"><?= $error['revisado_em'] ? 'Revisado' : 'Revisar ' . $e(date('d/m',strtotime($error['revisar_em']))) ?></span><i aria-hidden="true">⌄</i></span></summary><div class="learning-error-body"><p><?= nl2br($e($error['enunciado'])) ?></p><div class="learning-error-meta"><span>Sua resposta: <?= $e($error['resposta']) ?></span><span>Correta: <?= $e($error['correta']) ?></span><span>Habilidade: <?= $e($error['habilidade'] ?: 'não identificada') ?></span></div><blockquote><?= nl2br($e($error['explicacao'] ?: 'Reabra o conteúdo para revisar o conceito e solicitar ajuda ao Manel.')) ?></blockquote><form method="post" class="learning-error-form"><?= campoCsrf() ?><input type="hidden" name="acao" value="erro"><input type="hidden" name="erro_id" value="<?= $e($error['id']) ?>"><label>Motivo do erro<select name="tipo_erro"><?php foreach (adaptiveErrorTypes() as $value=>$label): ?><option value="<?= $e($value) ?>" <?= $error['tipo_erro']===$value ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label><label class="learning-check"><input type="checkbox" name="revisado" value="1" <?= $error['revisado_em'] ? 'checked' : '' ?>> Já revisei a explicação</label><button class="learning-button neo-star-hover" type="submit"><?= estrelaHoverNeo() ?>Salvar revisão</button></form><div class="learning-similar"><b>Próxima prática</b><?php foreach ($error['similar'] as $similar): ?><a href="questoes.php?conteudo_id=<?= $e($error['conteudo_id']) ?>#questao-<?= $e($similar['id']) ?>"><?= $e(mb_strimwidth($similar['enunciado'],0,110,'…')) ?> <span aria-hidden="true">↗</span></a><?php endforeach; ?><?php if (!$error['similar']): ?><a href="questoes.php?conteudo_id=<?= $e($error['conteudo_id']) ?>">Gerar uma nova atividade →</a><?php endif; ?></div></div></details>
            <?php endforeach; ?>
        </div>
        <?php if (!$errors): ?><div class="learning-empty"><b>Nenhuma revisão pendente.</b><p>Se uma questão der trabalho, ela aparecerá aqui com a explicação.</p></div><?php endif; ?>
    </section>

    <section id="simulados" class="neo-panel learning-section learning-tool-section">
        <div class="learning-section-head"><div><span class="neo-page-kicker">Modo desafio</span><h2>Simulados</h2></div></div>
        <div class="learning-practice-grid">
            <form method="post" class="learning-exam-builder" id="examBuilder"><?= campoCsrf() ?><input type="hidden" name="acao" value="criar_simulado"><label>Tipo de desafio<select name="tipo" id="examType"><?php foreach (adaptiveExamTypes() as $value=>$label): ?><option value="<?= $e($value) ?>"><?= $e($label) ?></option><?php endforeach; ?></select></label><label data-exam-filter="materia" hidden>Matéria<select name="materia_id"><?php foreach ($subjects as $id=>$name): ?><option value="<?= $e($id) ?>"><?= $e($name) ?></option><?php endforeach; ?></select></label><label data-exam-filter="conteudo" hidden>Conteúdo<select name="conteudo_id"><?php foreach ($summary['map'] as $content): ?><option value="<?= $e($content['id']) ?>"><?= $e($content['materia_nome'] . ' · ' . $content['titulo']) ?></option><?php endforeach; ?></select></label><label data-exam-filter="dificuldade" hidden>Dificuldade<select name="dificuldade"><?php foreach (range(1,12) as $level): ?><option value="<?= $level ?>"><?= $level ?> de 12</option><?php endforeach; ?></select></label><label data-exam-filter="estilo" hidden>Estilo de prova<select name="estilo"><option value="geral">Geral</option><option value="enem">ENEM</option><option value="vestibular">Vestibular</option><option value="concurso">Concurso</option></select></label><div class="learning-exam-numbers"><label>Questões<input type="number" min="1" max="30" value="10" name="quantidade" required></label><label>Minutos<input type="number" min="5" max="150" value="<?= $e($summary['routine']['minutes']) ?>" name="minutos" required></label></div><button class="learning-button is-primary neo-star-hover" type="submit"><?= estrelaHoverNeo() ?><span>Preparar meu simulado</span><span aria-hidden="true">→</span></button></form>
            <div class="learning-recent-exams"><b>Atividades recentes</b><?php foreach ($recentExams as $previous): ?><a href="aprendizado.php?simulado=<?= $e($previous['id']) ?>#simulado-atual"><span><b><?= $e(adaptiveExamTypes()[$previous['tipo']] ?? 'Simulado') ?></b><small><?= $e(date('d/m',strtotime($previous['criado_em']))) ?></small></span><span><?= $previous['concluido_em'] ? $e($previous['acertos']) . ' acerto(s)' : 'Continuar' ?> →</span></a><?php endforeach; ?><?php if (!$recentExams): ?><div class="learning-empty"><b>Seu primeiro desafio aparecerá aqui.</b><p>Monte um simulado quando quiser testar o que aprendeu.</p></div><?php endif; ?></div>
        </div>
    </section>

    <?php if ($exam): ?>
        <section id="simulado-atual" class="neo-panel learning-section">
            <div class="learning-section-head"><div><span class="neo-page-kicker"><?= $exam['concluido_em'] ? 'Resultado' : 'Simulado em andamento' ?></span><h2><?= $e(adaptiveExamTypes()[$exam['tipo']] ?? 'Seu simulado') ?></h2></div><span class="learning-pill"><?= count($exam['questions']) ?> questões · <?= $e($exam['filters']['minutos'] ?? 20) ?> min</span></div>
            <?php if (count($exam['questions']) < (int)($exam['filters']['quantidade_solicitada'] ?? 0)): ?><p class="learning-message">Encontrei <?= count($exam['questions']) ?> questões compatíveis e preparei todas elas.</p><?php endif; ?>
            <?php if ($exam['concluido_em']): ?><div class="learning-result"><b><?= $e($exam['acertos']) ?>/<?= count($exam['questions']) ?></b><p>acertos. O resultado já atualizou seu progresso e suas revisões.</p></div><?php endif; ?>
            <form method="post" id="learningExam" data-finished="<?= $exam['concluido_em'] ? '1' : '0' ?>"><?= campoCsrf() ?><input type="hidden" name="acao" value="responder_simulado"><input type="hidden" name="simulado_id" value="<?= $e($exam['id']) ?>">
                <?php foreach ($exam['questions'] as $index=>$q): $selected=$exam['answers'][$q['id']] ?? ($_POST['respostas'][$q['id']] ?? ''); ?>
                    <fieldset class="learning-question" data-question="<?= $e($q['id']) ?>"><legend><span>Questão <?= $index+1 ?></span><small><?= $e($q['materia_nome']) ?> · <?= $e($q['titulo']) ?></small></legend><p><?= nl2br($e($q['enunciado'])) ?></p><input type="hidden" name="tempos[<?= $e($q['id']) ?>]" value="0" data-question-time><?php foreach (['A','B','C','D'] as $letter): ?><label class="learning-answer <?= $selected === $letter ? 'is-checked ' : '' ?><?= $exam['concluido_em'] && $letter === strtoupper($q['correta']) ? 'is-correct' : '' ?>"><input type="radio" name="respostas[<?= $e($q['id']) ?>]" value="<?= $letter ?>" <?= $selected === $letter ? 'checked' : '' ?> <?= $exam['concluido_em'] ? 'disabled' : 'required' ?>><span><b><?= $letter ?>.</b> <?= $e($q['opcao_' . strtolower($letter)]) ?></span></label><?php endforeach; ?><?php if ($exam['concluido_em']): ?><div class="learning-feedback"><b><?= $selected === strtoupper($q['correta']) ? 'Você acertou!' : 'Vamos revisar este ponto.' ?></b><p><?= nl2br($e(trim((string)($q['feedback_' . strtolower((string)$selected)] ?? '') . "\n" . (string)($q['explicacao_correta'] ?? '')) ?: 'Reabra o conteúdo e peça uma explicação ao Manel.')) ?></p></div><?php endif; ?></fieldset>
                <?php endforeach; ?>
                <?php if (!$exam['concluido_em']): ?><button class="learning-button is-primary neo-star-hover" type="submit"><?= estrelaHoverNeo() ?>Concluir e ver meu resultado →</button><?php endif; ?>
            </form>
        </section>
    <?php endif; ?>

    <section id="descobertas" class="neo-panel learning-section learning-preferences">
        <div class="learning-section-head"><div><span class="neo-page-kicker">Adaptação</span><h2>Preferências de estudo</h2></div></div>
        <div class="learning-insights"><?php foreach ($summary['insights'] as $insight): ?><article class="learning-insight"><span class="learning-insight-source"><?= $e($insight['source']) ?></span><h3><?= $e($insight['title']) ?></h3><p><?= $e($insight['text']) ?></p><details><summary>Alterar esta preferência <span aria-hidden="true">⌄</span></summary><form method="post"><?= campoCsrf() ?><input type="hidden" name="acao" value="corrigir"><input type="hidden" name="insight_key" value="<?= $e($insight['key']) ?>"><label>O que combina mais com você?<select name="valor"><?php foreach (adaptiveCorrectionOptions($insight['key']) as $value=>$label): ?><option value="<?= $e($value) ?>" <?= (string)$insight['value']===(string)$value ? 'selected' : '' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label><button class="learning-button neo-star-hover" type="submit"><?= estrelaHoverNeo() ?>Salvar preferência</button></form></details></article><?php endforeach; ?></div>
    </section>
</div>


