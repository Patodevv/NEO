const http = require('node:http');
const { execFile } = require('node:child_process');
const { promisify } = require('node:util');
const path = require('node:path');
const assert = require('node:assert/strict');
const exec = promisify(execFile);
let failPrimary = false;
const calls = [];
const result = { title: 'Ecologia', summary: 'Resumo de ecologia.', questions: Array.from({ length: 2 }, () => ({ prompt: 'Qual a relação?', options: ['Mutualismo', 'Predação', 'Competição', 'Parasitismo'], answer: 0, explanation: 'Os dois organismos se beneficiam.' })) };
const server = http.createServer((request, response) => {
    let body = '';
    request.on('data', chunk => { body += chunk; });
    request.on('end', () => {
        calls.push({ url: request.url, data: JSON.parse(body) });
        response.setHeader('Content-Type', 'application/json');
        if (failPrimary && request.url === '/openai') { response.statusCode = 500; response.end(JSON.stringify({ error: { message: 'Fixture unavailable' } })); return; }
        response.end(JSON.stringify({ choices: [{ message: { content: JSON.stringify(result) } }] }));
    });
});
(async () => {
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    try {
        const url = 'http://127.0.0.1:' + server.address().port;
        const env = { ...process.env, OPENAI_API_KEY: 'fixture-only', OPENAI_API_URL: url + '/openai', GROQ_API_KEY: 'fixture-only', GROQ_API_URL: url + '/groq' };
        const run = async () => JSON.parse((await exec('C:/xampp/php/php.exe', [path.join(__dirname, 'estudos-ai-fixture.php')], { env })).stdout);
        const primary = await run();
        assert.equal(primary.provider, 'OP');
        assert.equal(primary.questions.length, 2);
        assert(calls[0].data.messages[0].content.includes('aluno: 4'));
        assert(calls[0].data.messages[0].content.includes('exatamente 2'));
        failPrimary = true;
        assert.equal((await run()).provider, 'GQ');
        assert(calls.some(call => call.url === '/groq'));
        console.log('PASS: real PHP study generation pipeline, user level, quantity, OpenAI and Groq fallback (local mock providers; no credits used).');
    } finally { await new Promise(resolve => server.close(resolve)); }
})().catch(error => { console.error(error); process.exitCode = 1; });
