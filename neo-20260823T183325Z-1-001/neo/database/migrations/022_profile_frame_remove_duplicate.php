<?php

return static function (PDO $pdo): void {
    $stmt = $pdo->prepare("\n        UPDATE produtos\n        SET ativo = 0, removido_em = COALESCE(removido_em, NOW())\n        WHERE codigo IN ('moldura_gato_neon', 'frame_gato_neon')\n    ");
    $stmt->execute();
};
