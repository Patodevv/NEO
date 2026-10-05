<?php
if (!function_exists('normalizarNomeMateriaVisual')) {
    function normalizarNomeMateriaVisual(string $materia): string
    {
        $chave = function_exists('mb_strtolower') ? mb_strtolower($materia, 'UTF-8') : strtolower($materia);
        $chave = strtr($chave, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','ê'=>'e','ë'=>'e','í'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ú'=>'u','û'=>'u','ü'=>'u','ç'=>'c']);
        return trim((string)preg_replace('/[^a-z0-9]+/', ' ', $chave));
    }
}

if (!function_exists('temaMateriaDashboard')) {
    function temaMateriaDashboard(string $materia): string
    {
        static $cache = [];
        $chave = normalizarNomeMateriaVisual($materia);
        if (isset($cache[$chave])) return $cache[$chave];

        static $regras = [
            'guitarra' => '/\b(guitarra|violao|ukulele|baixo eletrico|contrabaixo|contrabaixo eletrico|cavaquinho)\b/',
            'piano' => '/\b(piano|teclado musical|tecladista|teclas musicais)\b/',
            'bateria' => '/\b(bateria|percussao|baterista|tambor|tambores)\b/',
            'violino' => '/\b(violino|viola classica|violoncelo|cello|contrabaixo acustico|instrumentos de corda|instrumentos de arco)\b/',
            'canto' => '/\b(canto|vocal|voz cantada|tecnica vocal|coral|coro)\b/',
            'sopro' => '/\b(flauta|saxofone|sax|trompete|trombone|clarinete|gaita|instrumentos de sopro)\b/',
            'musica' => '/\b(musica|musical|harmonia|melodia|solfejo|teoria musical|producao musical|dj|instrumento)\b/',
            'jogos' => '/\b(desenvolvimento de jogos|design de jogos|game design|game dev|games|videogame|videogames|unity|unreal engine)\b/',
            'xadrez' => '/\b(xadrez|chess|jogo de tabuleiro)\b/',
            'geometria' => '/\b(geometria|trigonometria|geometria analitica|geometria espacial|formas geometricas)\b/',
            'estatistica' => '/\b(estatistica|probabilidade|analise combinatoria|metodos quantitativos|bioestatistica)\b/',
            'matematica' => '/\b(matematica|calculo|algebra|aritmetica|equacao|funcoes matematicas|raciocinio matematico)\b/',
            'literatura' => '/\b(literatura|poesia|poema|romance|conto|critica literaria|teoria literaria)\b/',
            'redacao' => '/\b(redacao|escrita|gramatica|ortografia|producao textual|copywriting|roteiro textual)\b/',
            'idiomas' => '/\b(idioma|idiomas|ingles|espanhol|frances|italiano|alemao|japones|mandarim|chines|coreano|latim|grego|arabe|russo|libras|lingua estrangeira)\b/',
            'comunicacao' => '/\b(comunicacao|oratoria|jornalismo|relacoes publicas|midia|radio|locucao|apresentacao em publico)\b/',
            'linguagens' => '/\b(portugues|lingua portuguesa|linguistica|semantica|sintaxe|fonetica|morfologia)\b/',
            'eletricidade' => '/\b(eletricidade|eletricista|engenharia eletrica|circuitos eletricos|instalacoes eletricas|eletrotecnica|alta tensao|eletromagnetismo)\b/',
            'fisica' => '/\b(fisica|mecanica quantica|termodinamica|optica|cinematica|dinamica|acustica|relatividade)\b/',
            'eletronica' => '/\b(eletronica|arduino|microcontrolador|sistemas embarcados|placa eletronica|automacao industrial|iot|internet das coisas)\b/',
            'quimica' => '/\b(quimica|bioquimica|quimica organica|quimica inorganica|molecula|reacao quimica|laboratorio quimico)\b/',
            'nutricao' => '/\b(nutricao|dietistica|dieta|alimentacao saudavel|nutrientes|educacao alimentar|tecnologia de alimentos)\b/',
            'veterinaria' => '/\b(veterinaria|medicina veterinaria|saude animal|clinica animal|zootecnia)\b/',
            'odontologia' => '/\b(odontologia|dentista|saude bucal|protese dentaria|ortodontia)\b/',
            'farmacia' => '/\b(farmacia|farmacologia|medicamentos|cosmetologia|toxicologia)\b/',
            'psicologia' => '/\b(psicologia|psicanalise|terapia|saude mental|neuropsicologia|comportamento humano)\b/',
            'medicina' => '/\b(medicina|saude|enfermagem|anatomia|fisiologia|fisioterapia|fonoaudiologia|biomedicina|engenharia biomedica|primeiros socorros|radiologia)\b/',
            'ambiente' => '/\b(meio ambiente|sustentabilidade|ecologia|reciclagem|gestao ambiental|engenharia ambiental|conservacao ambiental|energias renovaveis|energia solar)\b/',
            'agricultura' => '/\b(agricultura|agronomia|engenharia agronomica|agropecuaria|agronegocio|agroecologia|jardinagem|horticultura|cultivo|solo agricola)\b/',
            'biologia' => '/\b(biologia|botanica|zoologia|genetica|celula|celulas|organismo|organismos|microbiologia|biotecnologia|evolucao)\b/',
            'clima' => '/\b(meteorologia|climatologia|clima|previsao do tempo|atmosfera)\b/',
            'geologia' => '/\b(geologia|mineralogia|mineracao|petroleo e gas|paleontologia|sismologia|rochas|fosseis)\b/',
            'geografia' => '/\b(geografia|geopolitica|cartografia|oceanografia|territorio|demografia)\b/',
            'historia' => '/\b(historia(?! da arte| em quadrinhos)|arqueologia|antiguidade|civilizacao|medieval|idade media|historiografia|patrimonio historico)\b/',
            'inteligencia-artificial' => '/\b(inteligencia artificial|machine learning|aprendizado de maquina|deep learning|redes neurais|ia generativa|processamento de linguagem natural)\b/',
            'seguranca-digital' => '/\b(ciberseguranca|cybersecurity|seguranca digital|seguranca da informacao|hacking etico|criptografia|pentest|forense digital)\b/',
            'redes' => '/\b(redes de computadores|infraestrutura de redes|networking|computacao em nuvem|cloud computing|devops|servidores|telecomunicacoes)\b/',
            'dados' => '/\b(dados|ciencia de dados|analise de dados|banco de dados|engenharia de dados|sql|power bi|business intelligence|excel|planilhas)\b/',
            'robotica' => '/\b(robotica|robos|mecatronica|automacao robotica|robot)\b/',
            'hardware' => '/\b(hardware|manutencao de computadores|montagem de computadores|arquitetura de computadores|sistemas operacionais|linux)\b/',
            'codigo' => '/\b(programacao|computacao|informatica|tecnologia(?! de alimentos)|tecnologia da informacao|software|algoritmo|analise de sistemas|desenvolvimento de sistemas|desenvolvimento web|desenvolvimento mobile|desenvolvimento de aplicativos|apps|engenharia de software|python|javascript|typescript|java|php|csharp|cpp|rust|frontend|backend)\b/',
            'direito' => '/\b(direito|juridico|legislacao|advocacia|lei|leis|criminologia|constitucional|processo civil|processo penal)\b/',
            'contabilidade' => '/\b(contabilidade|contabil|auditoria|balanco patrimonial|tributacao|fiscal)\b/',
            'economia' => '/\b(economia|financas|mercado financeiro|investimentos|macroeconomia|microeconomia|trading)\b/',
            'marketing' => '/\b(marketing|publicidade|propaganda|trafego pago|seo|branding|vendas|e commerce|social media|redes sociais)\b/',
            'empreendedorismo' => '/\b(empreendedorismo|startup|plano de negocios|modelo de negocios|inovacao empresarial)\b/',
            'gestao' => '/\b(administracao(?! publica)|gestao|recursos humanos|rh|secretariado|lideranca|gestao de projetos|processos gerenciais)\b/',
            'logistica' => '/\b(logistica|cadeia de suprimentos|supply chain|transporte de cargas|armazenagem|estoque|comercio exterior)\b/',
            'negocios' => '/\b(negocios|comercio|mercado imobiliario|corretagem de imoveis|estrategia empresarial|gestao comercial)\b/',
            'fotografia' => '/\b(fotografia|camera fotografica|fotografo|iluminacao fotografica)\b/',
            'cinema' => '/\b(cinema|audiovisual|roteiro audiovisual|producao de video|edicao de video|filmagem|direcao cinematografica)\b/',
            'teatro' => '/\b(teatro|atuacao|artes cenicas|dramaturgia|cenografia)\b/',
            'danca' => '/\b(danca|bale|ballet|coreografia|samba|forro)\b/',
            'moda' => '/\b(moda|costura|modelagem de roupas|estilismo|design de moda|confeccao textil)\b/',
            'beleza' => '/\b(estetica|beleza|cabeleireiro|barbearia|maquiagem|manicure|cosmetica|visagismo)\b/',
            'animacao' => '/\b(animacao|motion design|modelagem 3d|arte 3d|blender|efeitos visuais|vfx)\b/',
            'design' => '/\b(design|design grafico|ux|ui|figma|photoshop|illustrator|tipografia|identidade visual|desenho tecnico)\b/',
            'arte' => '/\b(arte|artes|desenho|pintura|escultura|ceramica|gravura|quadrinhos|manga|historia da arte)\b/',
            'automotiva' => '/\b(mecanica automotiva|automotiva|automovel|automoveis|carro|carros|motores a combustao)\b/',
            'aviacao' => '/\b(aviacao|aeronautica|aeroespacial|piloto de aviao|pilotagem de drones|drones|manutencao de aeronaves)\b/',
            'engenharia-civil' => '/\b(engenharia civil|construcao|construcao civil|edificacoes|estruturas|pontes|seguranca do trabalho)\b/',
            'arquitetura' => '/\b(arquitetura|urbanismo|paisagismo|projeto arquitetonico|cad|autocad)\b/',
            'oficios' => '/\b(marcenaria|carpintaria|serralheria|encanador|encanamento|refrigeracao|manutencao predial)\b/',
            'nautica' => '/\b(nautica|navegacao|marinha|engenharia naval|construcao naval|barcos|navios)\b/',
            'engenharia' => '/\b(engenharia|mecanica|engenharia mecanica|engenharia de producao|metalurgia|soldagem|manutencao industrial)\b/',
            'astronomia' => '/\b(astronomia|espaco|cosmologia|astrofisica|universo|sistema solar|planetas)\b/',
            'futebol' => '/\b(futebol|futsal|soccer)\b/',
            'basquete' => '/\b(basquete|basquetebol|basketball)\b/',
            'natacao' => '/\b(natacao|nado|esportes aquaticos|hidroginastica)\b/',
            'academia' => '/\b(musculacao|academia|fitness|crossfit|treinamento funcional|yoga|pilates)\b/',
            'esporte' => '/\b(esporte|educacao fisica|volei|voleibol|atletismo|tenis|handebol|artes marciais)\b/',
            'confeitaria' => '/\b(confeitaria|panificacao|bolos|doces|chocolateria|padaria)\b/',
            'culinaria' => '/\b(gastronomia|culinaria|cozinha|chef|barista|cozinha profissional)\b/',
            'educacao' => '/\b(pedagogia|educacao|didatica|ensino|alfabetizacao|educacao infantil|psicopedagogia)\b/',
            'sociologia' => '/\b(sociologia|antropologia|servico social|ciencias sociais|sociedade|cultura social)\b/',
            'religiao' => '/\b(teologia|religiao|religioes|estudos biblicos|biblia|cristianismo|islamismo|budismo)\b/',
            'politica' => '/\b(ciencia politica|politica|cidadania|governo|administracao publica|relacoes internacionais)\b/',
            'seguranca-publica' => '/\b(seguranca publica|policia|bombeiro|defesa civil|investigacao criminal)\b/',
            'ideias' => '/\b(filosofia|etica|logica filosofica|epistemologia|metafisica)\b/',
            'turismo' => '/\b(turismo|hotelaria|hospitalidade|agencia de viagens|guia turistico|eventos)\b/',
        ];

        $tipo = 'geral';
        foreach ($regras as $candidato => $padrao) {
            if (preg_match($padrao, $chave) === 1) {
                $tipo = $candidato;
                break;
            }
        }
        return $cache[$chave] = $tipo;
    }
}

if (!function_exists('classeTemaMateria')) {
    function classeTemaMateria(string $materia): string
    {
        return 'materia-theme materia-theme--' . temaMateriaDashboard($materia);
    }
}

if (!function_exists('iconeMateriaDashboard')) {
    function iconeMateriaDashboard(string $materia): string
    {
        static $icones = [
            'matematica' => '<path d="M5 4h14v16H5z"></path><path d="M8 8h8M8 12h2M14 12h2M8 16h2M14 16h2"></path>',
            'linguagens' => '<path d="M4 5h7a4 4 0 0 1 4 4v10H8a4 4 0 0 0-4-4V5Z"></path><path d="M15 9a4 4 0 0 1 5-3.9V15a4 4 0 0 0-5 4"></path>',
            'fisica' => '<ellipse cx="12" cy="12" rx="8" ry="3.2"></ellipse><ellipse cx="12" cy="12" rx="3.2" ry="8" transform="rotate(60 12 12)"></ellipse><ellipse cx="12" cy="12" rx="3.2" ry="8" transform="rotate(120 12 12)"></ellipse><circle cx="12" cy="12" r="1.4"></circle>',
            'quimica' => '<path d="M9 3h6M10 3v6l-5 9a2 2 0 0 0 1.8 3h10.4A2 2 0 0 0 19 18l-5-9V3"></path><path d="M7.5 16h9M9 13h6"></path>',
            'biologia' => '<path d="M19 4C11 5 6 10 6 18c8-1 13-6 13-14Z"></path><path d="M5 21c2-6 6-10 11-13"></path>',
            'historia' => '<path d="M4 20h16M6 17h12M7 8h10M8 8v9M12 8v9M16 8v9M5 6l7-3 7 3Z"></path>',
            'geografia' => '<circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3c3 3 4 6 4 9s-1 6-4 9c-3-3-4-6-4-9s1-6 4-9Z"></path>',
            'redacao' => '<path d="M4 20h16"></path><path d="M7 17l9.5-9.5 3 3L10 20H7v-3Z"></path><path d="M15 9l3 3"></path>',
            'codigo' => '<path d="m8 9-4 3 4 3M16 9l4 3-4 3M14 5l-4 14"></path>',
            'musica' => '<path d="M9 18V6l10-2v12"></path><circle cx="6" cy="18" r="3"></circle><circle cx="16" cy="16" r="3"></circle>',
            'guitarra' => '<path d="m12 12 5-5"></path><path d="m16 5 3 3 2-3-2-2-3 2Z"></path><path d="M10 9 4 14a4 4 0 0 0 6 6l5-6Z"></path><circle cx="10.5" cy="14.5" r="1.7"></circle><path d="m5 15 4 4"></path>',
            'piano' => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M6 5v9M10 5v9M14 5v9M18 5v9M5 14h14"></path>',
            'bateria' => '<ellipse cx="12" cy="15" rx="6" ry="3"></ellipse><path d="M6 15v3c0 1.7 2.7 3 6 3s6-1.3 6-3v-3M8 4l8 7M16 4l-8 7M4 8h5M15 8h5"></path>',
            'violino' => '<path d="M14 3c2 2 2 5 0 7l-2 2"></path><path d="M10 9c-2-1-4 0-5 2s0 4 2 5l-2 3 2 2 3-3c1 2 4 2 6 0s3-5 1-7"></path><path d="m8 14 3 3M17 3l4 4"></path>',
            'canto' => '<rect x="9" y="3" width="6" height="11" rx="3"></rect><path d="M5 11a7 7 0 0 0 14 0M12 18v3M8 21h8"></path>',
            'sopro' => '<path d="M15 3c-2 2-2 5 0 7l3 3c2 2 2 5 0 7"></path><path d="M15 10 9 16c-2 2-5 2-6 0s0-4 2-5l6-4M8 13l3 3M13 6l3-2"></path><circle cx="16" cy="4" r="1"></circle>',
            'jogos' => '<path d="M8 7h8a5 5 0 0 1 4.7 6.7l-1 3A2.5 2.5 0 0 1 15.5 18L13 15h-2l-2.5 3a2.5 2.5 0 0 1-4.2-1.3l-1-3A5 5 0 0 1 8 7Z"></path><path d="M7 11v4M5 13h4M16 11h.01M18 14h.01"></path>',
            'xadrez' => '<path d="M7 4h10M8 4v5l2 2-3 7h10l-3-7 2-2V4M6 21h12M8 18v3M16 18v3"></path>',
            'geometria' => '<path d="m12 3 9 18H3L12 3Z"></path><circle cx="12" cy="14" r="3"></circle><path d="M7 17h10"></path>',
            'estatistica' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"></path><path d="m4 8 6-5 6 7 5-5"></path>',
            'literatura' => '<path d="M3 5h7a4 4 0 0 1 4 4v11H7a4 4 0 0 0-4-4V5Z"></path><path d="M21 5h-3a4 4 0 0 0-4 4v11h3a4 4 0 0 1 4-4V5Z"></path><path d="M7 9h3M7 12h3M18 9h-1"></path>',
            'idiomas' => '<path d="M4 5h10v8H8l-4 3V5Z"></path><path d="M11 16h5l4 3V9h-3"></path><path d="M7 8h4M9 7v4"></path>',
            'comunicacao' => '<path d="M4 13a8 8 0 0 1 8-8M4 18a13 13 0 0 1 13-13"></path><circle cx="6" cy="18" r="2"></circle><path d="M13 11h7v7h-4l-3 3v-10Z"></path>',
            'eletricidade' => '<path d="m13 2-8 12h7l-1 8 8-12h-7l1-8Z"></path>',
            'eletronica' => '<rect x="6" y="6" width="12" height="12" rx="2"></rect><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M18 9h4M2 15h4M18 15h4M10 10h4v4h-4z"></path>',
            'saude' => '<path d="M12 21s-8-4.7-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6.3-8 11-8 11Z"></path><path d="M7.5 13h3l1.2-3 2.1 6 1.2-3h2"></path>',
            'medicina' => '<path d="M6 3v5a6 6 0 0 0 12 0V3M4 3h4M16 3h4"></path><path d="M12 14v2a4 4 0 0 0 8 0v-1"></path><circle cx="20" cy="13" r="2"></circle>',
            'farmacia' => '<path d="M8 4a4 4 0 0 0 0 8h8a4 4 0 0 0 0-8H8Z"></path><path d="m10 4 4 8"></path><path d="M7 17h10M9 14v7M15 14v7"></path>',
            'nutricao' => '<path d="M12 7c-4-4-9-1-8 5 1 6 4 9 8 9s7-3 8-9c1-6-4-9-8-5Z"></path><path d="M12 7c0-3 2-5 5-5M12 7c2-1 4-1 6 0"></path>',
            'psicologia' => '<path d="M9 4a3 3 0 0 0-3 3v1a3 3 0 0 0-2 3 3 3 0 0 0 2 3v1a3 3 0 0 0 3 3h3V7a3 3 0 0 0-3-3Z"></path><path d="M15 4a3 3 0 0 1 3 3v1a3 3 0 0 1 2 3 3 3 0 0 1-2 3v1a3 3 0 0 1-3 3h-3M8 9h2M14 13h2"></path>',
            'odontologia' => '<path d="M7 4c2-2 4 0 5 0s3-2 5 0c3 3 1 8 0 11s-2 6-4 6c-1 0-1-5-2-5s-1 5-2 5c-2 0-3-3-4-6S4 7 7 4Z"></path>',
            'veterinaria' => '<circle cx="7" cy="8" r="2"></circle><circle cx="17" cy="8" r="2"></circle><circle cx="4" cy="13" r="2"></circle><circle cx="20" cy="13" r="2"></circle><path d="M8 20c-3-2-1-6 4-6s7 4 4 6c-2 1-3 0-4 0s-2 1-4 0Z"></path>',
            'direito' => '<path d="M12 3v18M6 6h12M5 6l-3 6h6L5 6ZM19 6l-3 6h6l-3-6ZM7 21h10"></path>',
            'negocios' => '<path d="M4 19V9M10 19V5M16 19v-7M22 19H2"></path><path d="m4 8 6-4 6 6 5-5"></path>',
            'contabilidade' => '<rect x="4" y="3" width="16" height="18" rx="2"></rect><path d="M8 7h8M8 11h2M14 11h2M8 15h2M14 15h2M8 19h8"></path>',
            'economia' => '<ellipse cx="9" cy="7" rx="5" ry="3"></ellipse><path d="M4 7v4c0 1.7 2.2 3 5 3s5-1.3 5-3V7M10 17c.8 1.2 2.7 2 5 2 2.8 0 5-1.3 5-3v-5"></path><ellipse cx="15" cy="11" rx="5" ry="3"></ellipse>',
            'marketing' => '<path d="M4 13V9l12-5v14L4 13Z"></path><path d="M16 9a3 3 0 0 1 0 6M6 13l2 7h4l-2-6"></path>',
            'gestao' => '<rect x="3" y="7" width="18" height="13" rx="2"></rect><path d="M8 7V4h8v3M3 12h18M9 12v2h6v-2"></path>',
            'empreendedorismo' => '<path d="M14 4c3-2 5-1 6 0 1 1 2 3 0 6l-5 5-6-6 5-5Z"></path><path d="m9 9-4 1-2 4 6 1M15 15l-1 4-4 2-1-6M13 7l4 4"></path>',
            'logistica' => '<path d="M3 6h11v11H3zM14 10h4l3 4v3h-7z"></path><circle cx="7" cy="18" r="2"></circle><circle cx="18" cy="18" r="2"></circle>',
            'arte' => '<path d="M12 3a9 9 0 0 0 0 18h1.5a2 2 0 0 0 0-4H12a2 2 0 0 1 0-4h3a6 6 0 0 0 0-12h-3Z"></path><circle cx="7" cy="10" r="1"></circle><circle cx="9" cy="6" r="1"></circle><circle cx="14" cy="6" r="1"></circle>',
            'fotografia' => '<path d="M4 7h4l2-3h4l2 3h4v13H4V7Z"></path><circle cx="12" cy="13" r="4"></circle><path d="M17 10h.01"></path>',
            'cinema' => '<rect x="3" y="6" width="18" height="14" rx="2"></rect><path d="M3 10h18M7 6l3 4M13 6l3 4"></path><path d="m10 13 5 2.5-5 2.5v-5Z"></path>',
            'teatro' => '<path d="M4 5c4 2 8 2 12 0v7c0 5-3 8-6 8s-6-3-6-8V5Z"></path><path d="M12 7c3 1 6 1 8 0v6c0 4-2 7-5 7M7 11h.01M12 11h.01M7 15c2 1 3 1 5 0M15 12h2"></path>',
            'danca' => '<circle cx="13" cy="4" r="2"></circle><path d="m12 6-3 5 3 3-2 7M12 10l5 3 3-2M9 11l-5 2"></path>',
            'moda' => '<path d="m8 4-5 3 3 5 2-1v10h8V11l2 1 3-5-5-3c-1 2-2 3-4 3S9 6 8 4Z"></path>',
            'beleza' => '<circle cx="6" cy="7" r="3"></circle><circle cx="6" cy="17" r="3"></circle><path d="m8 9 12 10M8 15 20 5"></path>',
            'animacao' => '<path d="M4 5h16v14H4zM4 9h16M8 5v4M16 5v4M8 19v-4M16 19v-4"></path><path d="m10 11 5 2.5-5 2.5v-5Z"></path>',
            'design' => '<path d="m12 3 3 6-3 12-3-12 3-6Z"></path><circle cx="12" cy="10" r="1.5"></circle><path d="M5 5h4M15 5h4M5 19h4M15 19h4"></path>',
            'engenharia' => '<circle cx="12" cy="12" r="3"></circle><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"></path>',
            'automotiva' => '<path d="m5 11 2-5h10l2 5 2 2v5h-2v2h-3v-2H8v2H5v-2H3v-5l2-2Z"></path><path d="M5 11h14M7 15h.01M17 15h.01"></path>',
            'aviacao' => '<path d="m2 16 20-8-2 4-7 3-2 6-2 1v-6l-4 1-3-1ZM9 12 7 6l2-1 4 5"></path>',
            'engenharia-civil' => '<path d="M3 20h18M5 20V9h14v11M8 9V5h8v4M8 13h3M13 13h3M8 17h3M13 17h3"></path>',
            'arquitetura' => '<path d="m3 11 9-8 9 8v10h-7v-6h-4v6H3V11Z"></path><path d="M17 3h4v4M5 5 3 3"></path>',
            'oficios' => '<path d="m14 4 6 6-3 3-6-6 3-3Z"></path><path d="m12 9-8 8v3h3l8-8M5 6h5M7 4v4"></path>',
            'nautica' => '<path d="M12 3v16M8 7h8M5 13c0 5 3 8 7 8s7-3 7-8M5 13l-3 2M19 13l3 2"></path><circle cx="12" cy="4" r="2"></circle>',
            'astronomia' => '<circle cx="12" cy="12" r="4"></circle><path d="M3 15c3 3 12 2 17-2s-1-6-6-5"></path>',
            'esporte' => '<circle cx="12" cy="12" r="9"></circle><path d="m8 4 4 4 4-4M4 10l5 2-2 6M20 10l-5 2 2 6M9 12h6"></path>',
            'futebol' => '<circle cx="12" cy="12" r="9"></circle><path d="m12 8 3 2-1 4h-4l-1-4 3-2ZM7 5l2 5M17 5l-2 5M4 14l6 0M20 14h-6M8 20l2-6M16 20l-2-6"></path>',
            'basquete' => '<circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3c3 3 3 15 0 18M5 6c4 3 10 3 14 0M5 18c4-3 10-3 14 0"></path>',
            'natacao' => '<path d="M2 17c2 2 4 2 6 0 2 2 4 2 6 0 2 2 4 2 8 0M2 21c2 2 4 2 6 0 2 2 4 2 6 0 2 2 4 2 8 0"></path><circle cx="15" cy="6" r="2"></circle><path d="m4 14 6-5 5 3 5-2M10 9l-2-4"></path>',
            'academia' => '<path d="M6 9v6M3 8v8M18 9v6M21 8v8M6 12h12"></path>',
            'culinaria' => '<path d="M7 3v8M4 3v5a3 3 0 0 0 6 0V3M7 11v10M16 3c-2 2-2 6-2 9h4V3h-2ZM18 12v9"></path>',
            'confeitaria' => '<path d="M5 11h14v9H5v-9ZM7 11V8h10v3M9 8V5M12 8V4M15 8V5"></path><path d="M5 15c2 1 3 1 5 0 2 1 3 1 5 0 2 1 3 1 4 0"></path>',
            'ideias' => '<path d="M9 18h6M10 22h4"></path><path d="M8 15a7 7 0 1 1 8 0c-1 1-1 2-1 3H9c0-1 0-2-1-3Z"></path>',
            'educacao' => '<path d="m3 9 9-5 9 5-9 5-9-5Z"></path><path d="M7 12v5c3 2 7 2 10 0v-5M21 9v6"></path>',
            'sociologia' => '<circle cx="8" cy="8" r="3"></circle><circle cx="17" cy="9" r="2.5"></circle><path d="M2 20c0-4 2-7 6-7s6 3 6 7M14 14c4-1 7 2 7 6"></path>',
            'religiao' => '<path d="M4 21h16M6 21V10l6-7 6 7v11M9 21v-6h6v6M12 6v5M9.5 8.5h5"></path>',
            'politica' => '<path d="M3 20h18M5 17h14M6 8h12M7 8v9M12 8v9M17 8v9M4 6l8-3 8 3Z"></path><path d="M12 3v-1"></path>',
            'seguranca-publica' => '<path d="M12 3 4 6v6c0 5 3 8 8 10 5-2 8-5 8-10V6l-8-3Z"></path><path d="m12 8 1.2 2.5 2.8.4-2 2 .5 2.8-2.5-1.3-2.5 1.3.5-2.8-2-2 2.8-.4L12 8Z"></path>',
            'ambiente' => '<path d="M8 4 5 9h5M16 4l3 5-3 5M5 15l3 5h6"></path><path d="m8 4 3 1M19 9l-1 3M8 20l-2-2"></path>',
            'agricultura' => '<path d="M12 21V9M12 13c-5 0-8-3-8-7 5 0 8 3 8 7ZM12 17c5 0 8-3 8-7-5 0-8 3-8 7ZM4 21h16"></path>',
            'clima' => '<path d="M8 17H6a4 4 0 1 1 1-7.9A6 6 0 0 1 19 11a3 3 0 0 1-1 6H8Z"></path><path d="M8 20v1M12 19v2M16 20v1M5 4l1 1M12 2v2M19 4l-1 1"></path>',
            'geologia' => '<path d="m3 20 6-11 4 6 3-5 5 10H3Z"></path><path d="m9 9 3-5 4 6M8 16h9"></path>',
            'inteligencia-artificial' => '<rect x="5" y="6" width="14" height="12" rx="3"></rect><path d="M9 11h.01M15 11h.01M9 15h6M12 2v4M8 3h8M2 10v4M22 10v4"></path>',
            'seguranca-digital' => '<path d="M12 3 4 6v6c0 5 3 8 8 10 5-2 8-5 8-10V6l-8-3Z"></path><rect x="9" y="10" width="6" height="5" rx="1"></rect><path d="M10 10V8a2 2 0 0 1 4 0v2"></path>',
            'redes' => '<circle cx="12" cy="5" r="3"></circle><circle cx="5" cy="18" r="3"></circle><circle cx="19" cy="18" r="3"></circle><path d="m10 7-4 8M14 7l4 8M8 18h8"></path>',
            'dados' => '<ellipse cx="12" cy="5" rx="7" ry="3"></ellipse><path d="M5 5v7c0 1.7 3.1 3 7 3s7-1.3 7-3V5M5 12v7c0 1.7 3.1 3 7 3s7-1.3 7-3v-7"></path>',
            'robotica' => '<rect x="5" y="7" width="14" height="12" rx="3"></rect><path d="M12 3v4M9 3h6M8 12h.01M16 12h.01M9 16h6M2 11v5M22 11v5"></path>',
            'hardware' => '<rect x="6" y="6" width="12" height="12" rx="2"></rect><path d="M10 10h4v4h-4zM9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M18 9h4M2 15h4M18 15h4"></path>',
            'turismo' => '<rect x="5" y="7" width="14" height="14" rx="2"></rect><path d="M9 7V4h6v3M5 12h14M9 10v4M15 10v4"></path><circle cx="17" cy="5" r="2"></circle>',
            'geral' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3Z"></path><path d="M18.5 16l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8.8-2.2Z"></path>'
        ];
        $tipo = temaMateriaDashboard($materia);
        if (!isset($icones[$tipo])) $tipo = 'geral';
        return '<svg class="materia-icon materia-theme materia-theme--' . $tipo . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icones[$tipo] . '</svg>';
    }
}

if (!function_exists('estrelaHoverNeo')) {
    function estrelaHoverNeo(): string
    {
        return '<svg class="icon-hover-star" viewBox="0 0 120 120" focusable="false" aria-hidden="true">'
            . '<path class="icon-hover-star-shadow" d="M60 6 C66 34 86 54 114 60 C86 66 66 86 60 114 C54 86 34 66 6 60 C34 54 54 34 60 6 Z"></path>'
            . '<path class="icon-hover-star-core" d="M60 18 C65 40 80 55 102 60 C80 65 65 80 60 102 C55 80 40 65 18 60 C40 55 55 40 60 18 Z"></path>'
            . '<path class="icon-hover-star-center" d="M60 34 C64 48 72 56 86 60 C72 64 64 72 60 86 C56 72 48 64 34 60 C48 56 56 48 60 34 Z"></path>'
            . '</svg>';
    }
}


if (!function_exists('cometaProgressoNeo')) {
    function cometaProgressoNeo(): string
    {
        return '<span class="neo-progress-comet" aria-hidden="true">'
            . '<span class="neo-progress-trail"></span>'
            . '<span class="neo-progress-trail neo-progress-trail-2"></span>'
            . '<svg class="neo-progress-star" viewBox="0 0 120 120" focusable="false">'
            . '<path class="neo-progress-star-shadow" d="M60 6 C66 34 86 54 114 60 C86 66 66 86 60 114 C54 86 34 66 6 60 C34 54 54 34 60 6 Z"></path>'
            . '<path class="neo-progress-star-core" d="M60 18 C65 40 80 55 102 60 C80 65 65 80 60 102 C55 80 40 65 18 60 C40 55 55 40 60 18 Z"></path>'
            . '<path class="neo-progress-star-center" d="M60 34 C64 48 72 56 86 60 C72 64 64 72 60 86 C56 72 48 64 34 60 C48 56 56 48 60 34 Z"></path>'
            . '</svg>'
            . '</span>';
    }
}
