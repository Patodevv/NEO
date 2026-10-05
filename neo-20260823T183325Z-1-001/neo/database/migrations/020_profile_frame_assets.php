<?php

return static function (PDO $pdo): void {
    $pdo->exec("
        UPDATE produtos
        SET ativo = 0, removido_em = COALESCE(removido_em, NOW())
        WHERE categoria = 'decoracao_perfil'
          AND codigo IN ('anel_ouro','anel_neon','anel_foco','anel_gato_neon','anel_sapo','anel_coroa')
    ");

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO produtos
            (codigo, nome, descricao, preco_cossas, categoria, classe_visual, metadados_json, imagem, estoque, ativo, temporario, permanente, limite_por_usuario)
        VALUES (?, ?, ?, ?, 'decoracao_perfil', 'frame-art', ?, ?, NULL, 1, 0, 1, 1)
    ");

    $frames = [
        ['moldura_gato_neon', 'Moldura Gato Neon', 'Moldura transparente com orelhas de gato e brilho azul neon.', 360, '#36cfff', 'static/images/profile-frames/frame-01-gato-neon.png'],
        ['moldura_sapo_pop', 'Moldura Sapo Pop', 'Moldura transparente verde com detalhes divertidos de sapo.', 320, '#60e85b', 'static/images/profile-frames/frame-02-sapo-pop.png'],
        ['moldura_asas_celestes', 'Moldura Asas Celestes', 'Moldura transparente com asas, aro azul e detalhes dourados.', 420, '#65dfff', 'static/images/profile-frames/frame-03-asas-celestes.png'],
        ['moldura_raposa_fogo', 'Moldura Raposa de Fogo', 'Moldura transparente com orelhas de raposa e brilho quente.', 390, '#ff8a32', 'static/images/profile-frames/frame-04-raposa-fogo.png'],
        ['moldura_coroa_real', 'Moldura Coroa Real', 'Moldura transparente azul e dourada com coroa.', 460, '#ffd05b', 'static/images/profile-frames/frame-05-coroa-real.png'],
        ['moldura_mago_estelar', 'Moldura Mago Estelar', 'Moldura transparente com chapéu mágico e estrelas.', 430, '#b477ff', 'static/images/profile-frames/frame-06-mago-estelar.png'],
        ['moldura_cristal_azul', 'Moldura Cristal Azul', 'Moldura transparente com cristais azuis e violeta.', 380, '#55d9ff', 'static/images/profile-frames/frame-07-cristal-azul.png'],
        ['moldura_chama_rubi', 'Moldura Chama Rubi', 'Moldura transparente com chamas e gema vermelha.', 410, '#ff5b2e', 'static/images/profile-frames/frame-08-chama-rubi.png'],
        ['moldura_concha_oceano', 'Moldura Concha Oceano', 'Moldura transparente de água, pérolas e concha.', 390, '#53dbff', 'static/images/profile-frames/frame-09-concha-oceano.png'],
        ['moldura_circuito_ciano', 'Moldura Circuito Ciano', 'Moldura transparente tecnológica com brilho ciano.', 370, '#42d8ff', 'static/images/profile-frames/frame-10-circuito-ciano.png'],
        ['moldura_lua_sonho', 'Moldura Lua Sonho', 'Moldura transparente com lua, nuvens e estrelas.', 400, '#3fa8ff', 'static/images/profile-frames/frame-11-lua-sonho.png'],
        ['moldura_musica_neon', 'Moldura Música Neon', 'Moldura transparente com fones, notas e equalizador.', 360, '#2edbff', 'static/images/profile-frames/frame-12-musica-neon.png'],
        ['moldura_dragao_rubi', 'Moldura Dragão Rubi', 'Moldura transparente com chifres e detalhes rubi.', 470, '#ff4538', 'static/images/profile-frames/frame-13-dragao-rubi.png'],
        ['moldura_trevo_verde', 'Moldura Trevo Verde', 'Moldura transparente com folhas, trevos e flores.', 340, '#58df58', 'static/images/profile-frames/frame-14-trevo-verde.png'],
        ['moldura_gema_real', 'Moldura Gema Real', 'Moldura transparente violeta e dourada com gema.', 450, '#b979ff', 'static/images/profile-frames/frame-15-gema-real.png'],
    ];

    foreach ($frames as [$codigo, $nome, $descricao, $preco, $accent, $imagem]) {
        $stmt->execute([
            $codigo,
            $nome,
            $descricao,
            $preco,
            json_encode(['accent' => $accent], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $imagem,
        ]);
    }
};
