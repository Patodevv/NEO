<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/services/estudos_externos.php';
function check(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
foreach (['127.0.0.1', '10.0.0.1', '169.254.169.254', '172.16.0.1', '192.168.1.1', '100.64.0.1', '0.0.0.0', '224.0.0.1', '192.0.2.5', '198.51.100.8', '203.0.113.2', '::1', '::ffff:127.0.0.1'] as $ip) check(!estudoIpPublico($ip), 'Private/reserved IP accepted: ' . $ip);
check(estudoIpPublico('93.184.216.34'), 'Public IP rejected');
foreach (['file:///etc/passwd', 'http://localhost', 'http://service.internal/path', 'http://user:pass@example.org', 'https://example.org:8080', 'https://[::1]/', 'https://example.org\\@127.0.0.1', "https://example.org/\r\nX:yes"] as $url) {
    try { estudoUrlPublica($url); throw new LogicException('Unsafe URL accepted: ' . $url); }
    catch (InvalidArgumentException $expected) {}
}
check(estudoUrlPublica('https://example.org/article')['port'] === 443, 'HTTPS URL');
check(estudoRedirecionamento('https://example.org/a/b', '../c') === 'https://example.org/a/../c', 'Relative redirect');
check(estudoRedirecionamento('https://example.org/a', '//other.example.org/b') === 'https://other.example.org/b', 'Protocol-relative redirect');
$html = '<html><head><title>Fonte</title><script>SECRET_SCRIPT</script></head><body><nav>SECRET_NAV</nav><main><h1>Estudo</h1><p>' . str_repeat('As células formam os tecidos dos organismos. ', 10) . '</p><div hidden>SECRET_HIDDEN</div></main></body></html>';
$page = estudoTextoPagina($html);
check($page['title'] === 'Fonte' && !str_contains($page['text'], 'SECRET'), 'HTML sanitization');
try { estudoTextoPagina('<main>Vazio</main>'); throw new LogicException('Empty source accepted'); } catch (RuntimeException $expected) { check(!$expected instanceof LogicException, 'Empty source accepted'); }
$question = ['prompt' => 'Pergunta?', 'options' => ['A', 'B', 'C', 'D'], 'answer' => 1, 'explanation' => 'Explicação.'];
$study = ['title' => 'Título', 'summary' => 'Resumo.', 'questions' => [$question, $question]];
check(count(estudoValidarResposta($study, 2)['questions']) === 2, 'Valid quiz rejected');
$study['questions'][0]['answer'] = 4;
try { estudoValidarResposta($study, 2); throw new LogicException('Invalid answer accepted'); } catch (RuntimeException $expected) { check(!$expected instanceof LogicException, 'Invalid answer accepted'); }
echo "PASS: public URLs/IPs, redirect handling, HTML extraction, empty pages and quiz validation.\n";
if (in_array('--live', $argv ?? [], true)) {
    $live = estudoLerSite('https://www.php.net/manual/en/book.dom.php');
    echo 'PUBLIC PAGE: ' . $live['title'] . ' (' . mb_strlen($live['text']) . " characters)\n";
}
