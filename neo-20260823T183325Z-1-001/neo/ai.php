<?php
require __DIR__ . '/services/ai.php';
try {
    $conteudos = gerar('Biologia');
    echo '<pre>';
    print_r($conteudos);
    echo '</pre>';
} catch (Exception $e) {
    echo 'ERRO: ' . $e->getMessage();
}