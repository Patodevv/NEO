<?php

/** Onboarding answers are validated independently of the browser. */
function neoOnboardingOptions(array $labels): array
{
    $options = [];
    foreach ($labels as $id => $label) $options[] = ['id' => (string)$id, 'label' => $label];
    return $options;
}

function neoOnboardingCatalog(): array
{
    static $catalog;
    if ($catalog !== null) return $catalog;
    $enums = [
        'gostos' => ['jogos' => 'Jogos', 'esportes' => 'Esportes', 'musica' => 'Música', 'filmes_series' => 'Filmes e séries', 'livros_historias' => 'Livros e histórias', 'tecnologia' => 'Tecnologia', 'arte' => 'Arte e criatividade', 'natureza_animais' => 'Natureza e animais', 'ciencia_espaco' => 'Ciência e espaço', 'culinaria' => 'Culinária', 'viagens_culturas' => 'Viagens e culturas', 'outro' => 'Outro interesse'],
        'ensino' => ['fundamental' => 'Ensino Fundamental', 'medio' => 'Ensino Médio', 'tecnico' => 'Ensino Técnico', 'superior' => 'Ensino Superior', 'enem' => 'Preparação para ENEM', 'vestibular' => 'Preparação para vestibular', 'concurso' => 'Preparação para concursos', 'independente' => 'Estudo independente', 'outro' => 'Outro'],
        'modo' => ['personalizado' => 'Estudos normais', 'tecnico' => 'Estudos profissionais'],
        'objetivo' => ['notas' => 'Melhorar minhas notas', 'aprender' => 'Aprender uma matéria', 'reforcar' => 'Reforçar conteúdos em que tenho dificuldade', 'provas' => 'Preparar-me para provas', 'enem' => 'Preparar-me para o ENEM', 'vestibular' => 'Preparar-me para vestibular', 'concurso' => 'Preparar-me para concursos', 'profissao' => 'Aprender para uma profissão', 'interesse' => 'Aprender por interesse pessoal', 'revisar' => 'Revisar conteúdos', 'outro' => 'Outro'],
        'niveis' => ['nao_conheco' => 'Não conheço', 'iniciante' => 'Iniciante', 'basico' => 'Básico', 'intermediario' => 'Intermediário', 'avancado' => 'Avançado', 'nao_sei' => 'Não sei meu nível'],
        'formatos' => ['questoes' => 'Questões e exercícios', 'teoria' => 'Conteúdo teórico', 'resumos' => 'Resumos', 'exemplos' => 'Exemplos práticos', 'ia' => 'Explicações da IA', 'revisoes' => 'Revisões', 'desafios' => 'Desafios', 'simulados' => 'Simulados', 'mistura' => 'Mistura de formatos'],
        'explicacao' => ['passo_a_passo' => 'Passo a passo', 'direto' => 'Direto ao ponto', 'exemplos' => 'Com exemplos práticos', 'iniciante' => 'Como se eu fosse iniciante', 'perguntas' => 'Com perguntas para eu pensar', 'resumo' => 'Com resumo primeiro', 'completa' => 'Com explicação completa', 'misturado' => 'Misturado, você escolhe por mim'],
        'tempo' => ['10' => '10 minutos', '20' => '20 minutos', '30' => '30 minutos', '60' => '1 hora', '90' => '1 a 2 horas', '150' => 'Mais de 2 horas'],
        'dias' => ['2' => '1 a 2 dias', '4' => '3 a 4 dias', '5' => '5 dias', '6' => '6 dias', '7' => 'Todos os dias'],
        'ritmo' => ['tranquilo' => 'Tranquilo', 'normal' => 'Normal', 'intensivo' => 'Intensivo'],
        'gamificacao' => ['competir' => 'Gosto de competir', 'desafios' => 'Gosto de desafios', 'aprendizado' => 'Quero focar apenas no aprendizado', 'progresso' => 'Quero acompanhar meu progresso', 'conquistas' => 'Quero metas e conquistas', 'rankings' => 'Quero participar de rankings'],
        'motivacao' => ['diretas' => 'Mensagens diretas', 'motivadoras' => 'Mensagens motivadoras', 'cobranca' => 'Cobrança leve', 'metas_visuais' => 'Metas visuais', 'sequencia' => 'Sequência de estudos', 'porcentagem' => 'Progresso em porcentagem', 'desafios' => 'Desafios curtos', 'pouca' => 'Prefiro pouca motivação, só quero estudar'],
    ];
    $labels = ['nome' => 'Nome ou apelido', 'gostos' => 'Seus gostos', 'ensino' => 'Fase de estudos', 'modo' => 'Tipo de ensino', 'materias' => 'Matérias', 'objetivo' => 'Objetivo principal', 'meta' => 'Meta específica', 'niveis' => 'Seu nível', 'formatos' => 'Formatos de estudo', 'explicacao' => 'Estilo de explicação', 'tempo' => 'Tempo por dia', 'dias' => 'Dias por semana', 'ritmo' => 'Ritmo de estudo', 'gamificacao' => 'Desafios e conquistas', 'motivacao' => 'Incentivos'];
    $steps = [];
    foreach ($labels as $id => $label) $steps[] = ['id' => $id, 'label' => $label, 'options' => neoOnboardingOptions($enums[$id] ?? [])];
    $subjects = [
        'matematica' => ['Matemática', ['operacoes' => 'Operações e números', 'razao' => 'Razão e proporção', 'porcentagem' => 'Porcentagem', 'equacoes' => 'Equações do 2º grau', 'afim' => 'Função afim', 'quadratica' => 'Função quadrática']],
        'portugues' => ['Português', ['leitura' => 'Leitura e interpretação de texto', 'gramatica' => 'Gramática', 'figuras' => 'Figuras de linguagem']],
        'historia' => ['História', ['fontes' => 'Fontes históricas', 'brasil' => 'História do Brasil', 'contemporanea' => 'História contemporânea']],
        'geografia' => ['Geografia', ['cartografia' => 'Cartografia', 'ambiente' => 'Clima e meio ambiente', 'populacao' => 'População e território']],
        'biologia' => ['Biologia', ['celulas' => 'Células e organismos', 'ecologia' => 'Ecologia', 'genetica' => 'Genética']],
        'fisica' => ['Física', ['medidas' => 'Grandezas e unidades', 'mecanica' => 'Movimento e forças', 'energia' => 'Energia e eletricidade']],
        'quimica' => ['Química', ['materia' => 'Matéria e transformações', 'atomos' => 'Átomos e ligações', 'reacoes' => 'Reações químicas']],
        'ingles' => ['Inglês', ['vocabulario' => 'Vocabulário e leitura', 'tempos' => 'Tempos verbais', 'interpretacao' => 'Interpretação de texto em inglês']],
        'redacao' => ['Redação', ['estrutura' => 'Estrutura do texto', 'argumentacao' => 'Argumentação', 'coesao' => 'Coesão e proposta de intervenção']],
        'informatica' => ['Informática', ['fundamentos' => 'Fundamentos de informática', 'seguranca' => 'Segurança digital', 'logica' => 'Lógica e programação']],
        'direito' => ['Direito', ['introducao' => 'Introdução ao Direito', 'constitucional' => 'Direito constitucional', 'administrativo' => 'Direito administrativo']],
        'administracao' => ['Administração', ['fundamentos' => 'Fundamentos da administração', 'planejamento' => 'Planejamento e estratégia', 'processos' => 'Processos e gestão']],
        'outra' => ['Outra', ['fundamentos' => 'Fundamentos da matéria escolhida', 'pratica' => 'Aplicações e prática da matéria escolhida']],
    ];
    $materias = [];
    foreach ($subjects as $id => [$label, $contents]) {
        $items = [];
        foreach ($contents as $key => $title) $items[] = ['id' => $id . '-' . $key, 'label' => $title];
        $materias[] = ['id' => $id, 'label' => $label, 'contents' => $items];
    }
    return $catalog = ['steps' => $steps, 'materias' => $materias, 'enums' => $enums, 'version' => 1];
}

function neoOnboardingStepIds(): array
{
    return array_column(neoOnboardingCatalog()['steps'], 'id');
}

function neoOnboardingEnum(string $step, $value): string
{
    if (!is_string($value) || !array_key_exists($value, neoOnboardingCatalog()['enums'][$step] ?? [])) {
        throw new DomainException('Escolha uma das opções que eu preparei para você.');
    }
    return $value;
}

function neoOnboardingText($value, int $min, int $max, string $message): string
{
    if (!is_string($value)) throw new DomainException($message);
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    if (mb_strlen($value) < $min || mb_strlen($value) > $max || !preg_match('/\p{L}/u', $value) || preg_match('/[<>\x00-\x1f]/u', $value)) throw new DomainException($message);
    $flat = mb_strtolower((string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value));
    if (preg_match('/\b(?:asdf\w*|qwer\w*|zxcv\w*|teste+|fod[ae]\w*|caralho|porra|merda|buceta|put[ao]|desgrac[ao]\w*)\b/i', $flat) || preg_match('/(.)\1{4,}/u', $value)) throw new DomainException($message);
    if (preg_match('/^[a-z\s\-\x27]+$/i', $flat) && !preg_match('/[aeiouy]/i', $flat)) throw new DomainException($message);
    return $value;
}

function neoOnboardingList($value, array $allowed, string $message, int $minimum = 1): array
{
    if (!is_array($value) || !array_is_list($value) || count($value) < $minimum || count($value) > count($allowed)) throw new DomainException($message);
    foreach ($value as $item) if (!is_string($item) || !in_array($item, $allowed, true)) throw new DomainException($message);
    if (count(array_unique($value)) !== count($value)) throw new DomainException($message);
    return $value;
}

function neoOnboardingContents(array $answers): array
{
    $contents = [];
    foreach (neoOnboardingCatalog()['materias'] as $subject) {
        if (!in_array($subject['id'], $answers['materias']['selected'] ?? [], true)) continue;
        foreach ($subject['contents'] as $content) $contents[$content['id']] = $content + ['subject' => $subject['id'], 'subject_label' => $subject['id'] === 'outra' ? ($answers['materias']['other'] ?? 'Outra matéria') : $subject['label']];
    }
    return $contents;
}

function neoOnboardingValidate(string $step, $value, array $answers): mixed
{
    $catalog = neoOnboardingCatalog();
    switch ($step) {
        case 'nome':
            $name = neoOnboardingText($value, 2, 40, 'Me conta um nome ou apelido de 2 a 40 letras, sem palavrões ou sequências aleatórias.');
            if (!preg_match('/^[\p{L}\p{M}][\p{L}\p{M}\s\x27.\-]*$/u', $name)) throw new DomainException('Pode usar seu nome ou apelido, com letras, espaços, ponto ou hífen.');
            return $name;
        case 'gostos':
            if (!is_array($value)) throw new DomainException('Escolha pelo menos uma coisa de que você gosta.');
            $selected = neoOnboardingList($value['selected'] ?? null, array_keys($catalog['enums']['gostos']), 'Escolha pelo menos um interesse da lista.');
            $other = in_array('outro', $selected, true) ? neoOnboardingText($value['other'] ?? '', 2, 80, 'Me conte qual é esse outro interesse com uma palavra ou frase curta.') : '';
            $references = '';
            if (isset($value['references']) && trim((string)$value['references']) !== '') {
                $references = neoOnboardingText($value['references'], 2, 180, 'Use nomes claros e curtos, como One Piece, Minecraft ou Fórmula 1.');
                if (preg_match('/\b(?:ignore|prompt|sistema|instru[cç][aã]o|senha|api|chave|execute|comando|script)\b/iu', $references)) {
                    throw new DomainException('Informe apenas nomes de obras, jogos, esportes, artistas ou temas que você conhece e gosta.');
                }
            }
            return ['selected' => $selected, 'other' => $other, 'references' => $references];
        case 'ensino':
        case 'objetivo':
            if (!is_array($value)) throw new DomainException('Escolha uma opção para eu entender seus estudos.');
            $selected = neoOnboardingEnum($step, $value['value'] ?? null);
            $other = '';
            if ($selected === 'outro') {
                $other = neoOnboardingText($value['other'] ?? '', 5, 140, 'Me explique um pouco melhor como essa resposta se relaciona com seus estudos.');
                if (!preg_match('/estud|aprend|ensin|escol|curso|form|gradu|pesquis|educa|profiss|trein|prova|nota|revis|ler|leitura|idioma|matem|portugu|certifica|conhec|faculd|resid[eê]ncia|m[eé]dic|t[eé]cnic|programa|ci[eê]ncia|m[uú]sica|artes|desenvolv|carreira|trabalho|capacita|habilidade|alfabet|mestrad|doutorad|p[oó]s/iu', $other)) throw new DomainException('Me dê uma pista ligada ao aprendizado, como um curso, uma habilidade ou uma prova.');
            }
            return ['value' => $selected, 'other' => $other];
        case 'materias':
            if (!is_array($value)) throw new DomainException('Escolha pelo menos uma matéria.');
            $selected = neoOnboardingList($value['selected'] ?? null, array_column($catalog['materias'], 'id'), 'Escolha pelo menos uma matéria da lista.');
            $other = in_array('outra', $selected, true) ? neoOnboardingText($value['other'] ?? '', 3, 70, 'Qual é a outra matéria? Use um nome claro, como Música ou Programação.') : '';
            return ['selected' => $selected, 'other' => $other];
        case 'meta':
            if (!is_array($value) || !isset($value['defined']) || !is_bool($value['defined'])) throw new DomainException('Você pode definir uma meta ou dizer que ainda não tem uma meta clara.');
            if (!$value['defined']) return ['defined' => false];
            $kind = $value['kind'] ?? '';
            if (!in_array($kind, ['enem', 'nota', 'questoes', 'conteudo', 'prova'], true)) throw new DomainException('Escolha o tipo da sua meta para eu ajudar no planejamento.');
            $description = neoOnboardingText($value['description'] ?? '', 8, 180, 'Descreva uma meta de estudo com um pouco mais de detalhe.');
            if (!preg_match('/estud|aprend|nota|enem|vestib|concurso|prova|quest|exerc|conte[uú]do|matem|portug|hist[oó]ria|geograf|biolog|f[ií]sica|qu[ií]mica|ingl[eê]s|redac|redaç|inform[aá]t|direito|administr|funç|func|equa|revis|conclu|termin|melhor|aprova|livro|ler|leitur|pontos|certifica|curso|cap[ií]tulo/iu', $description)) throw new DomainException('Vamos ligar essa meta aos estudos: uma nota, prova, quantidade de questões ou conteúdo.');
            $deadline = $value['deadline'] ?? null;
            if (!is_string($deadline)) throw new DomainException('Escolha um prazo válido para sua meta.');
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $deadline);
            $today = new DateTimeImmutable('today');
            if (!$date || $date->format('Y-m-d') !== $deadline || $date < $today->modify('+1 day') || $date > $today->modify('+5 years')) throw new DomainException('Vamos usar um prazo entre amanhã e os próximos 5 anos.');
            $number = null;
            if (in_array($kind, ['enem', 'nota', 'questoes'], true)) {
                $raw = $value['value'] ?? null;
                if (!is_numeric($raw) || is_bool($raw) || !is_finite((float)$raw)) throw new DomainException('Informe um valor numérico para sua meta.');
                $number = (float)$raw;
                $limit = ['enem' => 1000, 'nota' => 10, 'questoes' => 100000][$kind];
                if ($number <= 0 || $number > $limit) throw new DomainException($kind === 'enem' ? 'Para esta meta do ENEM, escolha uma pontuação de 1 a 1000.' : ($kind === 'nota' ? 'Vamos usar uma nota maior que zero e até 10.' : 'Escolha uma quantidade de questões entre 1 e 100.000.'));
                if ($kind === 'questoes' && (floor($number) !== $number || $number / max(1, (int)$today->diff($date)->days) > 250)) throw new DomainException('Vamos manter a meta em até 250 questões por dia e usar uma quantidade inteira.');
            }
            if (preg_match('/(?:enem|nota|pontos)[^\d]{0,15}(\d{4,})|(\d{4,})\s*(?:no enem|pontos)/iu', $description, $match)) {
                $mentioned = (int)($match[1] ?: ($match[2] ?? 0));
                if ($mentioned > 1000) throw new DomainException('Essa pontuação parece alta demais. Para a meta do ENEM, use até 1000 pontos.');
            }
            return ['defined' => true, 'kind' => $kind, 'description' => $description, 'value' => $number, 'deadline' => $deadline];
        case 'niveis':
            if (!is_array($value)) throw new DomainException('Escolha seu nível em cada matéria; “Não sei meu nível” também vale.');
            $result = [];
            foreach ($answers['materias']['selected'] ?? [] as $id) $result[$id] = neoOnboardingEnum('niveis', $value[$id] ?? null);
            if (!$result || count($value) !== count($result)) throw new DomainException('Responda seu nível em todas as matérias escolhidas.');
            return $result;
        case 'formatos':
        case 'gamificacao':
            return neoOnboardingList($value, array_keys($catalog['enums'][$step]), 'Escolha pelo menos uma opção para eu adaptar sua experiência.');
        default:
            if (isset($catalog['enums'][$step])) return neoOnboardingEnum($step, $value);
            throw new DomainException('Essa etapa não existe. Recarregue a conversa para continuar.');
    }
}

function neoOnboardingNextStep(array $answers): string
{
    $validated = [];
    foreach (neoOnboardingStepIds() as $step) {
        if (!array_key_exists($step, $answers)) return $step;
        try { $validated[$step] = neoOnboardingValidate($step, $answers[$step], $validated); }
        catch (DomainException $e) { return $step; }
    }
    return 'resumo';
}

function neoOnboardingSaveAnswer(array $progress, string $step, mixed $value): array
{
    $answers = $progress['answers'] ?? [];
    $steps = neoOnboardingStepIds();
    $index = array_search($step, $steps, true);
    if ($index === false) throw new DomainException('Essa etapa não existe.');
    $next = neoOnboardingNextStep($answers);
    $nextIndex = array_search($next, $steps, true);
    if ($nextIndex !== false && $index > $nextIndex) throw new DomainException('Vamos responder a pergunta anterior antes de seguir.');
    $normalized = neoOnboardingValidate($step, $value, $answers);
    $changed = !array_key_exists($step, $answers) || $answers[$step] !== $normalized;
    $answers[$step] = $normalized;
    if ($changed) {
        $dependencies = [
            'materias' => ['niveis'],
        ];
        foreach ($dependencies[$step] ?? [] as $dependency) unset($answers[$dependency]);
    }
    $progress['answers'] = $answers;
    return $progress;
}

/** Only an opaque random token is stored in the browser; answers remain on the server. */
function neoOnboardingGuestToken(): ?string
{
    $token = $_COOKIE['neo_onboarding_resume'] ?? null;
    return is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token) ? $token : null;
}

function neoOnboardingGuestCookie(?string $token): void
{
    $path = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
    $path = $path === '.' || $path === '/' ? '/' : '/' . trim($path, '/') . '/';
    if (!headers_sent()) {
        setcookie('neo_onboarding_resume', $token ?? '', [
            'expires' => $token === null ? time() - 3600 : time() + 30 * 86400,
            'path' => $path,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    if ($token === null) unset($_COOKIE['neo_onboarding_resume'], $_SESSION['neo_onboarding_guest']);
    else $_COOKIE['neo_onboarding_resume'] = $token;
}

function neoOnboardingLoad(PDO $pdo, ?int $userId): array
{
    if (!$userId) {
        if (is_array($_SESSION['neo_onboarding_guest'] ?? null)) return $_SESSION['neo_onboarding_guest'];
        if ($token = neoOnboardingGuestToken()) {
            $stmt = $pdo->prepare('SELECT answers_json FROM onboarding_guest_progress WHERE token_hash=? AND updated_at >= DATE_SUB(NOW(),INTERVAL 30 DAY)');
            $stmt->execute([hash('sha256', $token)]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $answers = json_decode($row['answers_json'], true);
                return $_SESSION['neo_onboarding_guest'] = ['answers'=>is_array($answers) ? $answers : []];
            }
        }
        return ['answers' => []];
    }
    $stmt = $pdo->prepare('SELECT answers_json FROM onboarding_progress WHERE user_id = ?');
    $stmt->execute([$userId]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) return ['answers' => json_decode($row['answers_json'], true) ?: []];
    $stmt = $pdo->prepare('SELECT nome,personalizacao_json FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) throw new DomainException('Entre novamente para continuar sua personalização.');
    $profile = json_decode((string)($user['personalizacao_json'] ?? ''), true) ?: [];
    $answers = array_intersect_key($profile, array_flip(neoOnboardingStepIds()));
    if (!$answers) {
        try { $answers['nome'] = neoOnboardingValidate('nome', $user['nome'], []); }
        catch (DomainException $e) { $answers = []; }
    }
    return ['answers' => $answers];
}

function neoOnboardingPersist(PDO $pdo, ?int $userId, array $progress): void
{
    if (!$userId) {
        $token = neoOnboardingGuestToken() ?? bin2hex(random_bytes(32));
        // Expiration applies even if a browser keeps an old cookie indefinitely.
        $pdo->exec('DELETE FROM onboarding_guest_progress WHERE updated_at < DATE_SUB(NOW(),INTERVAL 30 DAY)');
        $pdo->prepare('INSERT INTO onboarding_guest_progress (token_hash,answers_json,diagnostic_json) VALUES (?,?,?) ON DUPLICATE KEY UPDATE answers_json=VALUES(answers_json),diagnostic_json=VALUES(diagnostic_json),updated_at=CURRENT_TIMESTAMP')
            ->execute([hash('sha256', $token), json_encode($progress['answers'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), '{}']);
        $_SESSION['neo_onboarding_guest'] = $progress;
        neoOnboardingGuestCookie($token);
        return;
    }
    $pdo->prepare('INSERT INTO onboarding_progress (user_id,answers_json,diagnostic_json) VALUES (?,?,?) ON DUPLICATE KEY UPDATE answers_json=VALUES(answers_json),diagnostic_json=VALUES(diagnostic_json)')
        ->execute([$userId, json_encode($progress['answers'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), '{}']);
}

function neoOnboardingResetGuest(PDO $pdo): void
{
    if ($token = neoOnboardingGuestToken()) {
        $pdo->prepare('DELETE FROM onboarding_guest_progress WHERE token_hash=?')->execute([hash('sha256', $token)]);
    }
    unset($_SESSION['neo_onboarding_guest']);
    neoOnboardingGuestCookie(null);
}

/** A starting sequence, not an official or exhaustive curriculum. */
function neoOnboardingCurriculum(array $answers): array
{
    $available = neoOnboardingContents($answers);
    $phase = $answers['ensino']['value'] ?? 'independente';
    $result = [];
    foreach ($answers['materias']['selected'] ?? [] as $subject) {
        $subjectContents = array_filter($available, static fn($content) => $content['subject'] === $subject);
        if ($subject === 'matematica' && $phase === 'fundamental') {
            $ids = ['matematica-operacoes', 'matematica-razao', 'matematica-porcentagem'];
            $subjectContents = array_intersect_key($subjectContents, array_flip($ids));
        } elseif ($subject === 'matematica' && $phase === 'medio') {
            $ids = ['matematica-razao', 'matematica-afim', 'matematica-quadratica'];
            $subjectContents = array_intersect_key($subjectContents, array_flip($ids));
        } elseif ($phase === 'fundamental') {
            $subjectContents = array_slice($subjectContents, 0, 2, true);
        }
        $result += $subjectContents;
    }
    return $result;
}

function neoOnboardingInterestsText(array $answers): string
{
    $catalog = neoOnboardingCatalog()['enums']['gostos'];
    $labels = [];
    foreach ($answers['gostos']['selected'] ?? [] as $interest) {
        $labels[] = $interest === 'outro' ? ($answers['gostos']['other'] ?? '') : ($catalog[$interest] ?? '');
    }
    return implode(', ', array_values(array_filter($labels, static fn($label): bool => is_string($label) && $label !== '')));
}

function neoOnboardingFinish(PDO $pdo, array $progress, array $credentials, ?int $userId): int
{
    $guest = !$userId;
    $answers = $progress['answers'] ?? [];
    if (neoOnboardingNextStep($answers) !== 'resumo') throw new DomainException('Ainda falta uma resposta importante. Vamos terminar a personalização antes de entrar.');
    // Rebuild all fields rather than copying any browser-provided profile metadata.
    $validated = [];
    foreach (neoOnboardingStepIds() as $step) $validated[$step] = neoOnboardingValidate($step, $answers[$step], $validated);
    $answers = $validated;
    $email = $hash = null;
    if (!$userId) {
        $emailInput = $credentials['email'] ?? null;
        $password = $credentials['senha'] ?? null;
        $confirmation = $credentials['confirmar_senha'] ?? null;
        if (!is_string($emailInput) || mb_strlen($emailInput) > 150 || !filter_var(trim($emailInput), FILTER_VALIDATE_EMAIL)) throw new DomainException('Para guardar seus estudos, informe um e-mail válido.');
        $email = mb_strtolower(trim($emailInput));
        if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72) throw new DomainException('Escolha uma senha de 8 a 72 caracteres para proteger sua conta.');
        if (!is_string($confirmation) || !hash_equals($password, $confirmation)) throw new DomainException('As senhas ainda não conferem. Digite a mesma senha nos dois campos.');
        $hash = password_hash($password, PASSWORD_DEFAULT);
    }
    $pdo->beginTransaction();
    try {
        $existingPreferences = [];
        $existingProfile = [];
        if (!$userId) {
            $stmt = $pdo->prepare('INSERT INTO users (nome,email,senha) VALUES (?,?,?)');
            $stmt->execute([$answers['nome'], $email, $hash]);
            $userId = (int)$pdo->lastInsertId();
        } else {
            $stmt = $pdo->prepare('SELECT id,preferencias_json,personalizacao_json FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$userId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) throw new DomainException('Sua sessão mudou. Entre novamente para salvar seus estudos.');
            $existingPreferences = json_decode((string)$existing['preferencias_json'], true) ?: [];
            $existingProfile = json_decode((string)$existing['personalizacao_json'], true) ?: [];
        }
        $contentMap = [];
        $curriculum = neoOnboardingCurriculum($answers);
        $levelMap = ['nao_conheco' => 1, 'iniciante' => 1, 'basico' => 3, 'intermediario' => 5, 'avancado' => 8, 'nao_sei' => 2];
        $order = 1;
        foreach ($curriculum as $contentId => $content) {
            $pdo->prepare('INSERT IGNORE INTO materias (nome) VALUES (?)')->execute([$content['subject_label']]);
            $stmt = $pdo->prepare('SELECT id FROM materias WHERE nome = ?');
            $stmt->execute([$content['subject_label']]);
            $subjectId = (int)$stmt->fetchColumn();
            $title = $content['label'];
            if ($content['subject'] === 'outra') $title = str_replace('da matéria escolhida', 'de ' . $answers['materias']['other'], $title);
            $stmt = $pdo->prepare('SELECT id FROM conteudos WHERE user_id = ? AND materia_id = ? AND titulo = ? AND removido_em IS NULL LIMIT 1');
            $stmt->execute([$userId, $subjectId, $title]);
            $dbContentId = (int)$stmt->fetchColumn();
            $difficulty = $levelMap[$answers['niveis'][$content['subject']]];
            if (!$dbContentId) {
                $stmt = $pdo->prepare("INSERT INTO conteudos (user_id,materia_id,titulo,status,corpo,dificuldade,ordem) VALUES (?,?,?,'Não iniciado',NULL,?,?)");
                $stmt->execute([$userId, $subjectId, $title, $difficulty, $order]);
                $dbContentId = (int)$pdo->lastInsertId();
            } else {
                $pdo->prepare('UPDATE conteudos SET ordem = ? WHERE id = ? AND user_id = ?')->execute([$order, $dbContentId, $userId]);
            }
            $contentMap[$contentId] = $dbContentId;
            $order++;
        }
        $answers['content_map'] = $contentMap;
        $answers['curriculum_notice'] = 'Sequência inicial de estudo, ajustável ao seu currículo e desempenho.';
        $answers['version'] = 1;
        $answers['updated_at'] = date(DATE_ATOM);
        // Preserve learning observations/corrections owned by the adaptive engine.
        foreach (['corrections', 'insight_corrections'] as $key) if (isset($existingProfile[$key])) $answers[$key] = $existingProfile[$key];
        $existingPreferences['onboarding'] = $answers;
        $existingPreferences['interesses'] = $answers['gostos'];
        $pdo->prepare('UPDATE users SET nome=?,gostos=?,dias_estudo_semana=?,personalizacao_json=?,preferencias_json=?,personalizacao_versao=1,onboarding_concluido_em=NOW() WHERE id=?')
            ->execute([$answers['nome'], neoOnboardingInterestsText($answers), (int)$answers['dias'], json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), json_encode($existingPreferences, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $userId]);
        $pdo->prepare('DELETE FROM onboarding_progress WHERE user_id=?')->execute([$userId]);
        if ($guest && ($token = neoOnboardingGuestToken())) {
            $pdo->prepare('DELETE FROM onboarding_guest_progress WHERE token_hash=?')->execute([hash('sha256', $token)]);
        }
        $pdo->commit();
        if ($guest) neoOnboardingGuestCookie(null);
        return $userId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException && $e->getCode() === '23000' && str_contains($e->getMessage(), 'email')) throw new DomainException('Já existe uma conta com esse e-mail. Entre nessa conta ou use outro e-mail.');
        throw $e;
    }
}

function neoOnboardingState(array $progress, bool $authenticated): array
{
    $step = neoOnboardingNextStep($progress['answers'] ?? []);
    return ['ok' => true, 'catalog' => neoOnboardingCatalog(), 'answers' => $progress['answers'] ?? [], 'step' => $step, 'next_step' => $step, 'completed' => $step === 'resumo', 'authenticated' => $authenticated, 'logged_in' => $authenticated];
}
