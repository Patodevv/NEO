<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/services/estudos_externos.php';
$study = manelEstudoExterno(['id' => 101, 'nivel' => 4], ['message' => 'Quero estudar ecologia, com foco em mutualismo.', 'quantity' => 2, 'history' => []]);
echo json_encode($study, JSON_UNESCAPED_UNICODE);
