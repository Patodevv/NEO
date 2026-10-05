<?php

return static function (PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS onboarding_guest_progress (
        token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
        answers_json LONGTEXT NOT NULL,
        diagnostic_json LONGTEXT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_guest_draft_expiration (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
