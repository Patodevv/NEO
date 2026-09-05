<?php
if (!function_exists('iconeMateriaDashboard')) {
    function iconeMateriaDashboard(string $materia): string
    {
        $chave = function_exists('mb_strtolower') ? mb_strtolower($materia, 'UTF-8') : strtolower($materia);
        $icones = [
            'matemática' => '<path d="M6 7h12M6 12h12M6 17h7"></path><path d="M16 15l2 2 3-4"></path>',
            'matematica' => '<path d="M6 7h12M6 12h12M6 17h7"></path><path d="M16 15l2 2 3-4"></path>',
            'português' => '<path d="M5 5h7a4 4 0 0 1 4 4v10H9a4 4 0 0 0-4-4V5Z"></path><path d="M16 9a4 4 0 0 1 4-4v10a4 4 0 0 0-4 4"></path>',
            'portugues' => '<path d="M5 5h7a4 4 0 0 1 4 4v10H9a4 4 0 0 0-4-4V5Z"></path><path d="M16 9a4 4 0 0 1 4-4v10a4 4 0 0 0-4 4"></path>',
            'física' => '<ellipse cx="12" cy="12" rx="8" ry="3.2"></ellipse><ellipse cx="12" cy="12" rx="3.2" ry="8" transform="rotate(60 12 12)"></ellipse><ellipse cx="12" cy="12" rx="3.2" ry="8" transform="rotate(120 12 12)"></ellipse><circle cx="12" cy="12" r="1.4"></circle>',
            'fisica' => '<ellipse cx="12" cy="12" rx="8" ry="3.2"></ellipse><ellipse cx="12" cy="12" rx="3.2" ry="8" transform="rotate(60 12 12)"></ellipse><ellipse cx="12" cy="12" rx="3.2" ry="8" transform="rotate(120 12 12)"></ellipse><circle cx="12" cy="12" r="1.4"></circle>',
            'química' => '<path d="M10 3h4"></path><path d="M11 3v5l-5 9a3 3 0 0 0 2.6 4h6.8A3 3 0 0 0 18 17l-5-9V3"></path><path d="M8 16h8"></path>',
            'quimica' => '<path d="M10 3h4"></path><path d="M11 3v5l-5 9a3 3 0 0 0 2.6 4h6.8A3 3 0 0 0 18 17l-5-9V3"></path><path d="M8 16h8"></path>',
            'biologia' => '<path d="M19 4c-7 1-12 5-13 12 7-1 12-5 13-12Z"></path><path d="M6 16c3-1 6-4 9-8"></path>',
            'história' => '<path d="M5 5h13a2 2 0 0 1 2 2v12H7a2 2 0 0 1-2-2V5Z"></path><path d="M8 8h8M8 12h6"></path>',
            'historia' => '<path d="M5 5h13a2 2 0 0 1 2 2v12H7a2 2 0 0 1-2-2V5Z"></path><path d="M8 8h8M8 12h6"></path>',
            'geografia' => '<circle cx="12" cy="12" r="8"></circle><path d="M4 12h16M12 4a12 12 0 0 1 0 16M12 4a12 12 0 0 0 0 16"></path>',
            'redação' => '<path d="M4 20h16"></path><path d="M7 17l9.5-9.5 3 3L10 20H7v-3Z"></path><path d="M15 9l3 3"></path>',
            'redacao' => '<path d="M4 20h16"></path><path d="M7 17l9.5-9.5 3 3L10 20H7v-3Z"></path><path d="M15 9l3 3"></path>'
        ];
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">' . ($icones[$chave] ?? '<path d="M5 5h14v14H5z"></path><path d="M8 9h8M8 13h8M8 17h5"></path>') . '</svg>';
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
