<?php
if (!function_exists('normalizarNomeMateriaVisual')) {
    function normalizarNomeMateriaVisual(string $materia): string
    {
        $chave = function_exists('mb_strtolower') ? mb_strtolower($materia, 'UTF-8') : strtolower($materia);
        $chave = strtr($chave, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','ë'=>'e','í'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ú'=>'u','û'=>'u','ü'=>'u','ç'=>'c']);
        return trim((string)preg_replace('/[^a-z0-9]+/', ' ', $chave));
    }
}

if (!function_exists('temaMateriaDashboard')) {
    function temaMateriaDashboard(string $materia): string
    {
        static $cache = [];
        $chave = normalizarNomeMateriaVisual($materia);
        if (isset($cache[$chave])) return $cache[$chave];

        $tipo = match (true) {
            preg_match('/\b(guitarra|violao|ukulele|contrabaixo|baixo eletrico|cavaquinho)\b/', $chave) === 1 => 'guitarra',
            preg_match('/\b(piano|teclado musical|tecladista)\b/', $chave) === 1 => 'piano',
            preg_match('/\b(bateria|percussao|baterista|tambor)\b/', $chave) === 1 => 'bateria',
            preg_match('/\b(musica|musical|canto|vocal|harmonia|melodia|instrumento)\b/', $chave) === 1 => 'musica',
            preg_match('/\b(matematica|calculo|algebra|geometria|estatistica|trigonometria|aritmetica)\b/', $chave) === 1 => 'matematica',
            preg_match('/\b(redacao|escrita|gramatica|ortografia|producao textual)\b/', $chave) === 1 => 'redacao',
            preg_match('/\b(portugues|idioma|ingles|espanhol|frances|italiano|alemao|literatura|lingua|linguistica)\b/', $chave) === 1 => 'linguagens',
            preg_match('/\b(fisica|mecanica quantica|termodinamica|eletromagnetismo|optica)\b/', $chave) === 1 => 'fisica',
            preg_match('/\b(quimica|bioquimica|molecula|laboratorio)\b/', $chave) === 1 => 'quimica',
            preg_match('/\b(biologia|ecologia|meio ambiente|agronomia|botanica|zoologia|genetica|celula|microbiologia)\b/', $chave) === 1 => 'biologia',
            preg_match('/\b(medicina|saude|enfermagem|farmacia|nutricao|psicologia|anatomia|fisiologia|odontologia)\b/', $chave) === 1 => 'saude',
            preg_match('/\b(historia|arqueologia|antiguidade|civilizacao|medieval)\b/', $chave) === 1 => 'historia',
            preg_match('/\b(geografia|geopolitica|cartografia|geologia|climatologia)\b/', $chave) === 1 => 'geografia',
            preg_match('/\b(programacao|computacao|informatica|tecnologia|software|dados|robotica|algoritmo|inteligencia artificial|cyber|ciber)\b/', $chave) === 1 => 'codigo',
            preg_match('/\b(direito|juridico|legislacao|advocacia|lei|leis)\b/', $chave) === 1 => 'direito',
            preg_match('/\b(administracao|economia|contabilidade|financas|marketing|negocios|empreendedorismo|gestao)\b/', $chave) === 1 => 'negocios',
            preg_match('/\b(arte|artes|design|desenho|pintura|fotografia|escultura|animacao)\b/', $chave) === 1 => 'arte',
            preg_match('/\b(engenharia|mecanica|eletrica|eletronica|automotiva|arquitetura|construcao)\b/', $chave) === 1 => 'engenharia',
            preg_match('/\b(astronomia|espaco|cosmologia|astrofisica|universo)\b/', $chave) === 1 => 'astronomia',
            preg_match('/\b(esporte|educacao fisica|futebol|volei|natacao|basquete|atletismo|academia)\b/', $chave) === 1 => 'esporte',
            preg_match('/\b(gastronomia|culinaria|cozinha|confeitaria|panificacao)\b/', $chave) === 1 => 'culinaria',
            preg_match('/\b(filosofia|sociologia|pedagogia|etica|antropologia)\b/', $chave) === 1 => 'ideias',
            default => 'geral',
        };
        return $cache[$chave] = $tipo;
    }
}

if (!function_exists('classeTemaMateria')) {
    function classeTemaMateria(string $materia): string
    {
        return 'materia-theme materia-theme--' . temaMateriaDashboard($materia);
    }
}

if (!function_exists('iconeMateriaDashboard')) {
    function iconeMateriaDashboard(string $materia): string
    {
        static $icones = [
            'matematica' => '<path d="M5 4h14v16H5z"></path><path d="M8 8h8M8 12h2M14 12h2M8 16h2M14 16h2"></path>',
            'linguagens' => '<path d="M4 5h7a4 4 0 0 1 4 4v10H8a4 4 0 0 0-4-4V5Z"></path><path d="M15 9a4 4 0 0 1 5-3.9V15a4 4 0 0 0-5 4"></path>',
            'fisica' => '<ellipse cx="12" cy="12" rx="8" ry="3.2"></ellipse><ellipse cx="12" cy="12" rx="3.2" ry="8" transform="rotate(60 12 12)"></ellipse><ellipse cx="12" cy="12" rx="3.2" ry="8" transform="rotate(120 12 12)"></ellipse><circle cx="12" cy="12" r="1.4"></circle>',
            'quimica' => '<path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 1.8 3h10.4A2 2 0 0 0 19 18l-5-9V3"></path><path d="M7.5 16h9M9 13h6"></path>',
            'biologia' => '<path d="M19 4C11 5 6 10 6 18c8-1 13-6 13-14Z"></path><path d="M5 21c2-6 6-10 11-13"></path>',
            'historia' => '<path d="M4 20h16M6 17h12M7 8h10M8 8v9M12 8v9M16 8v9M5 6l7-3 7 3Z"></path>',
            'geografia' => '<circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3c3 3 4 6 4 9s-1 6-4 9c-3-3-4-6-4-9s1-6 4-9Z"></path>',
            'redacao' => '<path d="M4 20h16"></path><path d="M7 17l9.5-9.5 3 3L10 20H7v-3Z"></path><path d="M15 9l3 3"></path>',
            'codigo' => '<path d="m8 9-4 3 4 3M16 9l4 3-4 3M14 5l-4 14"></path>',
            'musica' => '<path d="M9 18V6l10-2v12"></path><circle cx="6" cy="18" r="3"></circle><circle cx="16" cy="16" r="3"></circle>',
            'guitarra' => '<path d="m12 12 5-5"></path><path d="m16 5 3 3 2-3-2-2-3 2Z"></path><path d="M10 9 4 14a4 4 0 0 0 6 6l5-6Z"></path><circle cx="10.5" cy="14.5" r="1.7"></circle><path d="m5 15 4 4"></path>',
            'piano' => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M6 5v9M10 5v9M14 5v9M18 5v9M5 14h14"></path>',
            'bateria' => '<ellipse cx="12" cy="15" rx="6" ry="3"></ellipse><path d="M6 15v3c0 1.7 2.7 3 6 3s6-1.3 6-3v-3M8 4l8 7M16 4l-8 7M4 8h5M15 8h5"></path>',
            'saude' => '<path d="M12 21s-8-4.7-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.3-8 11-8 11Z"></path><path d="M7.5 13h3l1.2-3 2.1 6 1.2-3h2"></path>',
            'direito' => '<path d="M12 3v18M6 6h12M5 6l-3 6h6L5 6ZM19 6l-3 6h6l-3-6ZM7 21h10"></path>',
            'negocios' => '<path d="M4 19V9M10 19V5M16 19v-7M22 19H2"></path><path d="m4 8 6-4 6 6 5-5"></path>',
            'arte' => '<path d="M12 3a9 9 0 0 0 0 18h1.5a2 2 0 0 0 0-4H12a2 2 0 0 1 0-4h3a6 6 0 0 0 0-12h-3Z"></path><circle cx="7" cy="10" r="1"></circle><circle cx="9" cy="6" r="1"></circle><circle cx="14" cy="6" r="1"></circle>',
            'engenharia' => '<circle cx="12" cy="12" r="3"></circle><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"></path>',
            'astronomia' => '<circle cx="12" cy="12" r="4"></circle><path d="M3 15c3 3 12 2 17-2s-1-6-6-5"></path>',
            'esporte' => '<circle cx="12" cy="12" r="9"></circle><path d="m8 4 4 4 4-4M4 10l5 2-2 6M20 10l-5 2 2 6M9 12h6"></path>',
            'culinaria' => '<path d="M7 3v8M4 3v5a3 3 0 0 0 6 0V3M7 11v10M16 3c-2 2-2 6-2 9h4V3h-2ZM18 12v9"></path>',
            'ideias' => '<path d="M9 18h6M10 22h4"></path><path d="M8 15a7 7 0 1 1 8 0c-1 1-1 2-1 3H9c0-1 0-2-1-3Z"></path>',
            'geral' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3Z"></path><path d="M18.5 16l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8.8-2.2Z"></path>'
        ];
        $tipo = temaMateriaDashboard($materia);
        return '<svg class="materia-icon materia-theme materia-theme--' . $tipo . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icones[$tipo] . '</svg>';
    }
}

if (!function_exists('estrelaHoverNeo')) {
    function estrelaHoverNeo(): string
    {
        return '<svg class="icon-hover-star" viewBox="0 0 120 120" focusable="false" aria-hidden="true">'
            . '<path class="icon-hover-star-shadow" d="M60 6 C66 34 86 54 114 60 C86 66 66 86 60 114 C54 86 34 66 6 60 C34 54 54 34 60 6 Z"></path>'
            . '<path class="icon-hover-star-core" d="M60 18 C65 40 80 55 102 60 C80 65 65 80 60 102 C55 80 40 65 18 60 C40 55 55 40 60 18 Z"></path>'
            . '<path class="icon-hover-star-center" d="M60 34 C64 48 72 56 86 60 C72 64 64 72 60 86 C56 72 48 64 34 60 C48 56 56 48 60 34 Z"></path>'
            . '</svg>';
    }
}


if (!function_exists('cometaProgressoNeo')) {
    function cometaProgressoNeo(): string
    {
        return '<span class="neo-progress-comet" aria-hidden="true">'
            . '<span class="neo-progress-trail"></span>'
            . '<span class="neo-progress-trail neo-progress-trail-2"></span>'
            . '<svg class="neo-progress-star" viewBox="0 0 120 120" focusable="false">'
            . '<path class="neo-progress-star-shadow" d="M60 6 C66 34 86 54 114 60 C86 66 66 86 60 114 C54 86 34 66 6 60 C34 54 54 34 60 6 Z"></path>'
            . '<path class="neo-progress-star-core" d="M60 18 C65 40 80 55 102 60 C80 65 65 80 60 102 C55 80 40 65 18 60 C40 55 55 40 60 18 Z"></path>'
            . '<path class="neo-progress-star-center" d="M60 34 C64 48 72 56 86 60 C72 64 64 72 60 86 C56 72 48 64 34 60 C48 56 56 48 60 34 Z"></path>'
            . '</svg>'
            . '</span>';
    }
}
