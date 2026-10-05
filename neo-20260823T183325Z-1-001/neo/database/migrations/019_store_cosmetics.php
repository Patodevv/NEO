<?php

return static function (PDO $pdo): void {
    adicionarColunaSeAusente($pdo, 'users', 'tema_site', 'VARCHAR(100) NULL AFTER decoracao_perfil');
    adicionarColunaSeAusente($pdo, 'users', 'skin_manel', 'VARCHAR(100) NULL AFTER tema_site');
    adicionarColunaSeAusente($pdo, 'users', 'cor_nome', 'VARCHAR(100) NULL AFTER skin_manel');
    adicionarColunaSeAusente($pdo, 'produtos', 'metadados_json', 'LONGTEXT NULL AFTER classe_visual');

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO produtos
            (codigo, nome, descricao, preco_cossas, categoria, classe_visual, metadados_json, estoque, ativo, temporario, permanente, limite_por_usuario)
        VALUES (?, ?, ?, ?, ?, ?, ?, NULL, 1, 0, 1, 1)
    ");

    $produtos = [
        [
            'tema_nebula_azul',
            'Tema Nébula Azul',
            'Muda o NEO para um azul espacial mais profundo, com painéis frios e fundo imersivo.',
            420,
            'tema_site',
            'theme-nebula',
            [
                'accent' => '#2f56ff',
                'accent2' => '#72d7ff',
                'page_bg' => '#030817',
                'panel_bg' => 'rgba(9, 24, 58, .92)',
                'control_bg' => 'rgba(7, 18, 44, .9)',
                'border' => 'rgba(88, 126, 255, .34)',
            ],
        ],
        [
            'tema_aurora_verde',
            'Tema Aurora Verde',
            'Muda a interface para uma paleta esverdeada de energia e progresso.',
            360,
            'tema_site',
            'theme-aurora',
            [
                'accent' => '#45e08f',
                'accent2' => '#7df5c1',
                'page_bg' => '#03130f',
                'panel_bg' => 'rgba(8, 38, 34, .9)',
                'control_bg' => 'rgba(7, 29, 28, .86)',
                'border' => 'rgba(69, 224, 143, .3)',
            ],
        ],
        [
            'manel_ciano',
            'Manel Ciano',
            'Troca o brilho do Manel para um ciano mais leve.',
            220,
            'skin_manel',
            'manel-cyan',
            [
                'manel_name' => 'Manel',
                'manel_color' => '#72d7ff',
                'manel_glow' => 'rgba(114, 215, 255, .45)',
            ],
        ],
        [
            'manel_lumi',
            'Lumi',
            'Transforma o assistente em Lumi, com brilho violeta.',
            320,
            'skin_manel',
            'manel-lumi',
            [
                'manel_name' => 'Lumi',
                'manel_color' => '#b58cff',
                'manel_glow' => 'rgba(181, 140, 255, .48)',
            ],
        ],
        [
            'nome_dourado',
            'Nome Dourado',
            'Deixa seu nome em destaque com cor dourada no perfil.',
            180,
            'cor_nome',
            'name-gold',
            [
                'name_color' => '#ffd36a',
            ],
        ],
        [
            'anel_gato_neon',
            'Anel Gato Neon',
            'Anel com orelhas de gato e brilho neon para a foto do perfil.',
            340,
            'decoracao_perfil',
            'ring-cat',
            [
                'accent' => '#ff8fcf',
                'accent2' => '#72d7ff',
                'ring_style' => 'cat',
            ],
        ],
        [
            'anel_sapo',
            'Anel Sapo',
            'Anel verde com detalhes arredondados para a foto do perfil.',
            300,
            'decoracao_perfil',
            'ring-frog',
            [
                'accent' => '#51df76',
                'accent2' => '#baff8a',
                'ring_style' => 'frog',
            ],
        ],
        [
            'anel_coroa',
            'Anel Coroa',
            'Anel real com coroa e brilho quente.',
            460,
            'decoracao_perfil',
            'ring-royal',
            [
                'accent' => '#ff5bd4',
                'accent2' => '#58e5ff',
                'ring_style' => 'royal',
            ],
        ],
    ];

    foreach ($produtos as $produto) {
        [$codigo, $nome, $descricao, $preco, $categoria, $classe, $metadados] = $produto;
        $stmt->execute([
            $codigo,
            $nome,
            $descricao,
            $preco,
            $categoria,
            $classe,
            json_encode($metadados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
};
