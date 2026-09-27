<?php

return static function (PDO $pdo): void {
    $metadados = json_encode(['accent' => '#ff7bd5', 'accent2' => '#54d7ff'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $stmt = $pdo->prepare("\n        UPDATE produtos\n        SET nome = ?, descricao = ?, categoria = 'decoracao_perfil', classe_visual = 'frame-art', imagem = ?, metadados_json = ?, preco_cossas = 180, estoque = NULL, limite_por_usuario = 1, ativo = 1, removido_em = NULL\n        WHERE codigo = 'anel_ouro'\n    ");
    $stmt->execute([
        'Moldura Gato Neon',
        'Moldura transparente com orelhinhas neon para decorar o perfil.',
        'static/images/profile-frames/frame-01-gato-neon.png',
        $metadados,
    ]);

    $stmt = $pdo->prepare("\n        UPDATE produtos\n        SET ativo = 0, removido_em = COALESCE(removido_em, NOW())\n        WHERE codigo = 'frame_gato_neon'\n    ");
    $stmt->execute();
};
