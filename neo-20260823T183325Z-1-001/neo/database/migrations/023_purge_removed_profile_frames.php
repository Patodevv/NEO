<?php

return static function (PDO $pdo): void {
    $codigos = [
        'anel_neon',
        'anel_foco',
        'anel_gato_neon',
        'anel_sapo',
        'anel_coroa',
        'moldura_gato_neon',
        'moldura_asas_celestes',
        'moldura_raposa_fogo',
        'moldura_coroa_real',
        'moldura_mago_estelar',
        'moldura_cristal_azul',
        'moldura_chama_rubi',
        'moldura_concha_oceano',
        'moldura_lua_sonho',
        'moldura_dragao_rubi',
        'moldura_trevo_verde',
        'moldura_gema_real',
    ];

    $placeholders = implode(',', array_fill(0, count($codigos), '?'));

    foreach (['decoracao_perfil', 'tema_site', 'skin_manel', 'cor_nome'] as $campo) {
        if (colunaExiste($pdo, 'users', $campo)) {
            $pdo->prepare("UPDATE users SET `{$campo}` = NULL WHERE `{$campo}` IN ({$placeholders})")->execute($codigos);
        }
    }

    $pdo->prepare("
        DELETE cl
        FROM compras_loja cl
        LEFT JOIN produtos p ON p.id = cl.produto_id
        WHERE cl.item_id IN ({$placeholders})
           OR p.codigo IN ({$placeholders})
    ")->execute([...$codigos, ...$codigos]);

    $pdo->prepare("
        DELETE FROM produtos
        WHERE categoria = 'decoracao_perfil'
          AND codigo IN ({$placeholders})
    ")->execute($codigos);
};
