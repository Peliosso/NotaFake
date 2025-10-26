<?php
// CONFIGURAÇÕES DO BOT
$token = "8362847658:AAHoF5LFmYDZdWPm9Umde9M5dqluhnpUl-g";
$apiURL = "https://api.telegram.org/bot$token/";
$cep_origem = "30140071"; // Belo Horizonte, MG

// PEGAR MENSAGENS
$update = json_decode(file_get_contents("php://input"), true);
// Permitir mensagens em grupos
if (isset($update["message"]["chat"]["type"]) && $update["message"]["chat"]["type"] != "private") {
    $message = $update["message"]["text"] ?? "";
    $chat_id = $update["message"]["chat"]["id"];
}
$chat_id = $update["message"]["chat"]["id"] ?? $update["callback_query"]["message"]["chat"]["id"];
$message = $update["message"]["text"] ?? null;
$callback_query = $update["callback_query"]["data"] ?? null;
$message_id = $update["callback_query"]["message"]["message_id"] ?? null;

// ARQUIVOS PARA SALVAR OS DADOS
$usuariosFile = "usuarios.json";
if (!file_exists($usuariosFile)) file_put_contents($usuariosFile, "{}");
$usuarios = json_decode(file_get_contents($usuariosFile), true);

$cuponsFile = "cupons.json";
if (!file_exists($cuponsFile)) file_put_contents($cuponsFile, "{}");
$cupons = json_decode(file_get_contents($cuponsFile), true);

$bloqueadosFile = "bloqueados.json";
if (!file_exists($bloqueadosFile)) file_put_contents($bloqueadosFile, "[]");
$bloqueados = json_decode(file_get_contents($bloqueadosFile), true);

// FUNÇÃO PARA ENVIAR MENSAGENS
function sendMessage($chat_id, $text, $reply_markup = null) {
    global $apiURL;
    $data = [
        "chat_id" => $chat_id,
        "text" => $text,
        "parse_mode" => "Markdown"
    ];
    if ($reply_markup) $data["reply_markup"] = json_encode($reply_markup);
    $response = file_get_contents($apiURL . "sendMessage?" . http_build_query($data));
    return json_decode($response, true)["result"]["message_id"] ?? null;
}

// FUNÇÃO PARA EDITAR MENSAGENS
function editMessage($chat_id, $message_id, $text, $reply_markup = null) {
    global $apiURL;
    $data = [
        "chat_id" => $chat_id,
        "message_id" => $message_id,
        "text" => $text,
        "parse_mode" => "Markdown"
    ];
    if ($reply_markup) $data["reply_markup"] = json_encode($reply_markup);
    file_get_contents($apiURL . "editMessageText?" . http_build_query($data));
}

// FUNÇÃO PARA CALCULAR FRETE
function calcularFrete($cep_destino, $peso = 1) {
    global $cep_origem;
    $url = "https://www2.correios.com.br/sistemas/precosPrazos/PrecoPrazo.asmx/CalcPrecoPrazo?" . http_build_query([
        "nCdEmpresa" => "",
        "sDsSenha" => "",
        "nCdServico" => "04510",
        "sCepOrigem" => $cep_origem,
        "sCepDestino" => $cep_destino,
        "nVlPeso" => $peso,
        "nCdFormato" => 1,
        "nVlComprimento" => 20,
        "nVlAltura" => 5,
        "nVlLargura" => 15,
        "nVlDiametro" => 0,
        "sCdMaoPropria" => "N",
        "nVlValorDeclarado" => 0,
        "sCdAvisoRecebimento" => "N"
    ]);

    $response = @file_get_contents($url);
    if ($response === false) return rand(30, 50);
    if (preg_match('/<Valor>(.*?)<\/Valor>/', $response, $matches)) {
        $valor = str_replace(",", ".", $matches[1]);
        return (float)$valor > 0 ? (float)$valor : rand(30, 50);
    }
    return rand(30, 50);
}

// --- MENU PRINCIPAL /start ---
if ($message == "/start") {
    $keyboard = [
        "inline_keyboard" => [
            [
                ["text" => "⚰️ • Óbito", "callback_data" => "cmd_obito"],
                ["text" => "📄 • Gerar Docs", "callback_data" => "cmd_gerardocs"]
            ],
            [
                ["text" => "☎️ • Chip", "callback_data" => "cmd_chip_direct"],
                ["text" => "💵 • Comprar NF", "callback_data" => "cmd_comprar_direct"]
            ],
            [
                ["text" => "📦 • Adquirir Bot", "callback_data" => "cmd_adquirirbot"]
            ]
        ]
    ];

    sendMessage($chat_id, "🎭 *Bem-vindo ao Joker NF!*\n\nEscolha uma das opções abaixo:", $keyboard);
    exit;
}

// --- CALLBACK /OBITO ---
if ($callback_query == "cmd_obito") {
    $texto = "⚰️ • *Adição de Óbito*\n\n"
        . "*Adicione óbito no CPF desejado via CadSus.*\n\n"
        . "Para testar, envie o comando (exemplo):\n"
        . "`/obito 12345678910`\n\n"
        . "📦 Planos:\n"
        . "• Plano diário: R$15,00\n"
        . "• Plano semanal: R$35,00\n"
        . "• Plano mensal: R$45,00\n\n"
        . "📌 *Forma de pagamento:*\n"
        . "🔹 PIX: 1aebb1bd-10b7-435e-bd17-03adf4451088\n\n"
        . "📤 Após o pagamento, envie o *comprovante* para: @falsifiquei";

    $keyboard = [
        "inline_keyboard" => [
            [["text" => "⬅️ Voltar", "callback_data" => "voltar_menu"]]
        ]
    ];
    editMessage($chat_id, $message_id, $texto, $keyboard);
    exit;
}

// --- CALLBACK /GERAR DOCS ---
if ($callback_query == "cmd_gerardocs") {
    $texto = "📄 *Gerador de Documentos*\n\n"
    ."Use o comando `/gerardoc` para gerar um documento aleatório.";

    $keyboard = [
        "inline_keyboard" => [
            [["text" => "⬅️ Voltar", "callback_data" => "voltar_menu"]]
        ]
    ];
    editMessage($chat_id, $message_id, $texto, $keyboard);
    exit;
}

// --- CALLBACK /ADQUIRIR BOT ---
if ($callback_query == "cmd_adquirirbot") {
    $texto = "🤖 *Deseja adquirir o BOT completo?*\n\n"
    ."💬 Fale diretamente comigo:\n👉 [@falsifiquei](https://t.me/falsifiquei)\n\n"
    ."🌐 Entre também no grupo oficial:\n👉 [Grupo JokerNF](https://t.me/puxadasjoker)\n\n"
    ."⚙️ Inclui todos os módulos: consultas, docs, chips, cupons e sistema de pedidos.";
    
    $keyboard = [
        "inline_keyboard" => [
            [["text" => "⬅️ Voltar", "callback_data" => "voltar_menu"]]
        ]
    ];
    editMessage($chat_id, $message_id, $texto, $keyboard);
    exit;
}

// --- CALLBACK /CHIP DIRETO ---
if ($callback_query == "cmd_chip_direct") {
    // Apaga o menu e substitui pelo conteúdo do /chip
    $keyboard = [
        "inline_keyboard" => [
            [["text" => "⛱️ • RJ", "callback_data" => "chip_RJ"]],
            [["text" => "🧀 • MG", "callback_data" => "chip_MG"]],
            [["text" => "☂️ • SP", "callback_data" => "chip_SP"]],
            [["text" => "🌎 • Outros", "callback_data" => "chip_Outros"]]
        ]
    ];

    editMessage($chat_id, $message_id, "📶 Escolha o *estado* para o chip:", $keyboard);
    exit;
}

// --- CALLBACK /COMPRAR DIRETO ---
if ($callback_query == "cmd_comprar_direct") {
    // Edita a mensagem atual com o início do /comprar
    $usuarios[$chat_id] = ["etapa" => "nome"];
    file_put_contents($usuariosFile, json_encode($usuarios));

    editMessage($chat_id, $message_id, "📝 Vamos começar o formulário.\n\nDigite seu *NOME COMPLETO*:");
    exit;
}

// --- CALLBACK DO BOTÃO VOLTAR ---
if ($callback_query == "voltar_menu") {
    $keyboard = [
        "inline_keyboard" => [
            [
                ["text" => "⚰️ • Óbito", "callback_data" => "cmd_obito"],
                ["text" => "📄 • Gerar Docs", "callback_data" => "cmd_gerardocs"]
            ],
            [
                ["text" => "☎️ • Chip", "callback_data" => "cmd_chip_direct"],
                ["text" => "💵 • Comprar NF", "callback_data" => "cmd_comprar_direct"]
            ],
            [
                ["text" => "📦 • Adquirir Bot", "callback_data" => "cmd_adquirirbot"]
            ]
        ]
    ];

    editMessage($chat_id, $message_id, "🎭 *Bem-vindo ao Joker NF!*\n\nEscolha uma das opções abaixo:", $keyboard);
    exit;
}

// --- COMANDO /consultasim (simulação interativa) ---
// Uso: /consultasim 123.456.789-00
if (strpos($message, "/obito") === 0) {
    $parts = preg_split('/\s+/', trim($message));
    if (!isset($parts[1]) || empty($parts[1])) {
        sendMessage($chat_id, "❌ Uso correto: /obito 12345678910");
        exit;
    }

    $cpf = $parts[1];
    comandoConsultaSimulada($chat_id, $cpf);
    exit;
}

if ($message == "/gerardoc") {
    $admin_id = "7926471341"; // só você pode usar
    if ($chat_id != $admin_id) {
        sendMessage($chat_id, "❌ • *Você não tem permissão para usar este comando*.\n💰 Para acessar, fale comigo: @falsifiquei*");
        exit;
    }

    // animação de “gerando”
    $msg_id = sendMessage($chat_id, "*🌀 • Gerando documento...*");
    sleep(1);
    editMessage($chat_id, $msg_id, "*⚙️ • Processando...*");
    sleep(1);
    editMessage($chat_id, $msg_id, "*📂 • Selecionando documento aleatório...*");
    sleep(1);

    // seleciona imagem aleatória da pasta docs
    $pasta = __DIR__ . "/docs/";
    $arquivos = glob($pasta . "*.{jpg,jpeg,png,webp}", GLOB_BRACE);

    if (empty($arquivos)) {
        editMessage($chat_id, $msg_id, "*❌ Nenhum arquivo encontrado na pasta docs.*");
        exit;
    }

    $arquivo = $arquivos[array_rand($arquivos)];

    // envia a imagem
    $url = "https://api.telegram.org/bot$token/sendPhoto";
    $post_fields = [
        'chat_id' => $chat_id,
        'caption' => "📄 • Documento gerado com sucesso!",
        'photo' => new CURLFile(realpath($arquivo))
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_exec($ch);
    curl_close($ch);

    // edita a mensagem inicial pra indicar sucesso
    editMessage($chat_id, $msg_id, "✅ • *Documento enviado!*");
}

/**
 * comandoConsultaSimulada
 * - Somente ID autorizado pode usar
 * - Animações via editMessage para simular uma consulta interativa
 * - Resultado final claramente marcado como SIMULAÇÃO / NÃO OFICIAL
 */

function comandoConsultaSimulada($chat_id, $cpf) {
    // ID autorizado
    $admin_id = "7926471341"; // só você pode usar
    if ($chat_id != $admin_id) {
        sendMessage($chat_id, "❌ • *Você não tem permissão para usar este comando*.\n💰 Para acessar, fale comigo: @falsifiquei*");
        exit;
    }

    // Mensagens de etapa (texto que aparecerá durante a edição)
    $etapas = [
        ["text" => "🔄 • *Iniciando módulo de consulta...*",       "sub" => "Acessando infraestrutura"],
        ["text" => "🔐 • *Acessando Cadsus...*",                 "sub" => "Conexão segura estabelecida"],
        ["text" => "⏳ • *Validando CPF no banco de dados...*",  "sub" => "Verificando integridade dos dados"],
        ["text" => "📂 • *Consultando registros do cartório...*", "sub" => "Procurando entradas relevantes"],
        ["text" => "🔎 • *Processando informações...*",          "sub" => "Compilando relatório final"]
    ];

    // Envia mensagem inicial e obtém message_id
    $initial = sendMessage($chat_id, "⌛ Iniciando consulta..."); // espera message_id
    if (is_array($initial) && isset($initial['result']['message_id'])) {
        $message_id = $initial['result']['message_id'];
    } else {
        $message_id = $initial;
    }

    if (!$message_id) {
        sendMessage($chat_id, "❌ Erro ao iniciar a consulta. Tente novamente.");
        return;
    }

    // Barra de progresso - 10 segundos no total (dividido por quantos passos quiser)
    $totalSeconds = 10;
    $steps = 10;
    $sleepMicro = intval(($totalSeconds / $steps) * 1000000);

    foreach ($etapas as $index => $etapa) {
        $ticksPerEtapa = intval($steps / count($etapas));
        if ($ticksPerEtapa < 1) $ticksPerEtapa = 1;

        for ($t = 1; $t <= $ticksPerEtapa; $t++) {
            $globalTick = $index * $ticksPerEtapa + $t;
            $percent = min(100, intval(($globalTick / $steps) * 100));
            $barsTotal = 12;
            $filled = intval(($percent / 100) * $barsTotal);
            $bar = "[" . str_repeat("█", $filled) . str_repeat("░", $barsTotal - $filled) . "]";

            $texto = "🔎 *Óbito Cadsus*\n\n";
            $texto .= "*Etapa:* " . $etapa['text'] . "\n";
            $texto .= "_" . $etapa['sub'] . "_\n\n";
            $texto .= "$bar  *{$percent}%*\n";
            $texto .= "`CPF:` $cpf\n\n";
            $texto .= "⌛ Aguardando resposta do serviço...";

            editMessage($chat_id, $message_id, $texto);
            usleep($sleepMicro);
        }
    }

    // --- Chamada à API externa (jokerapisfree) com detecção de challenge JS e follow ---
$apiUrlBase = "https://jokerapisfree.rf.gd/index.php?cpf=" . urlencode($cpf);
$apiResponse = null;
$apiData = null;
$apiError = null;

// arquivo temporário para cookies
$cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'js_challenge_cookies_' . md5($apiUrlBase) . '.txt';
$maxAttempts = 3;
$attempt = 0;

while ($attempt < $maxAttempts && !$apiData) {
    $attempt++;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrlBase);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // NÃO confiar em Location header (estamos lidando com JS)
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept: application/json, text/html, */*"]);
    curl_setopt($ch, CURLOPT_USERAGENT, "ConsultaSimuladaBot/1.0");
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    // define referer para parecer browser
    curl_setopt($ch, CURLOPT_REFERER, "https://jokerapisfree.rf.gd/");

    $apiResponse = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($apiResponse === false || $curlErr) {
        $apiError = "Erro na requisição: " . ($curlErr ?: "sem mensagem");
        // tenta novamente (loop)
        continue;
    }

    // se já for JSON puro, tentamos decodificar
    $trim = ltrim($apiResponse);
    if (strpos($trim, '{') === 0 || strpos($trim, '[') === 0) {
        $decoded = json_decode($apiResponse, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['success']) && $decoded['success'] === true && isset($decoded['resultado'])) {
            $apiData = $decoded['resultado'];
            break;
        } elseif (json_last_error() === JSON_ERROR_NONE && isset($decoded['success'])) {
            // API respondeu JSON porém success=false ou formato inesperado
            $apiError = "API respondeu JSON porém sem sucesso. HTTP {$httpCode}.";
            break;
        }
        // se JSON inválido, continua para tentar novo fetch
    }

    // Detecta HTML challenge com JS que faz "location.href"
    if (stripos($apiResponse, '<script') !== false && preg_match('/location\.href\s*=\s*"(.*?)"/i', $apiResponse, $m)) {
        $redirect = $m[1];
        // se redirect for relativo, transforma em absoluto
        if (parse_url($redirect, PHP_URL_SCHEME) === null) {
            $redirect = rtrim("https://jokerapisfree.rf.gd", '/') . '/' . ltrim($redirect, '/');
        }

        // prepara nova requisição para o redirect (provavelmente contém &i=1)
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $redirect);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Accept: application/json, text/html, */*"]);
        curl_setopt($ch2, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119 Safari/537.36");
        curl_setopt($ch2, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch2, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch2, CURLOPT_REFERER, $apiUrlBase);
        $apiResponse2 = curl_exec($ch2);
        $curlErr2 = curl_error($ch2);
        $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);

        if ($apiResponse2 && stripos(ltrim($apiResponse2), '{') === 0) {
            $decoded2 = json_decode($apiResponse2, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded2['success']) && $decoded2['success'] === true && isset($decoded2['resultado'])) {
                $apiData = $decoded2['resultado'];
                break;
            } else {
                // guarda erro e tenta novamente (ou dá fallback)
                $apiError = "Resposta do redirect não foi JSON válido ou success=false. HTTP {$httpCode2}.";
            }
        } else {
            // a resposta do redirect ainda foi HTML (provavel que precise executar JS real)
            $apiError = "Redirect retornou HTML (challenge persistente) ou JSON inválido. HTTP {$httpCode2}.";
        }

        // tenta novamente a partir do $apiUrlBase (loop) - até atingir $maxAttempts
        continue;
    }

    // Se chegou aqui: nem JSON, nem redir via JS detectado — retorna erro
    $apiError = "Resposta inesperada da API. HTTP {$httpCode}.";
    break;
}

// limpa arquivo de cookie se quiser
// @unlink($cookieFile); // opcional

if (!$apiData && !$apiError) {
    $apiError = "Resposta inválida (JSON inválido) ou sem dados após {$attempt} tentativas.";
}

    // Pequena pausa final para dar sensação de "compilando"
    usleep(500000);

    // Monta resultado final combinando simulação e dados da API (se houver)
    $simulacaoNota = "⚠️ *RESULTADO:*\n\n";

    // Campos fixos simulados
    $resultado  = $simulacaoNota;
    $resultado .= "🪪 *Óbito Adicionado!*\n\n";
    $resultado .= "🔹 *CPF consultado:* `$cpf`\n";
    $resultado .= "🔹 *Cartório:* `Oficial de Registro Civil das Pessoas Naturais do 18º Subdistrito – Ipiranga`\n";
    $resultado .= "🔹 *Status da busca:* *REGISTRO ENCONTRADO*\n";
    $resultado .= "🔹 *Última atualização:* `" . date("d/m/Y H:i:s") . "`\n\n";

    // Se a API respondeu com dados válidos, inclui-os formatados
    if ($apiData) {
        // sanitiza os campos antes de mostrar
        $nome = isset($apiData['nome']) ? $apiData['nome'] : '—';
        $cpfRet = isset($apiData['cpf']) ? $apiData['cpf'] : $cpf;
        $nasc = isset($apiData['data_nascimento']) ? $apiData['data_nascimento'] : '—';
        $genero = isset($apiData['genero']) ? $apiData['genero'] : '—';

        // converte data ISO para dd/mm/YYYY se possível
        $nascFmt = $nasc;
        $d = DateTime::createFromFormat('Y-m-d', $nasc);
        if ($d) $nascFmt = $d->format('d/m/Y');

        $resultado .= "📡 *Dados retornados pela API (jokerapisfree):*\n";
        $resultado .= "  • *Nome:* `$nome`\n";
        $resultado .= "  • *CPF:* `$cpfRet`\n";
        $resultado .= "  • *Nascimento:* `$nascFmt`\n";
        $resultado .= "  • *Gênero:* `$genero`\n\n";
    } else {
        // Se houve erro, informa e anexa resposta bruta pra debug
        $resultado .= "❗ *Dados da API indisponíveis:* " . ($apiError ?: "Resposta não encontrada.") . "\n\n";
        if ($apiResponse) {
            // limita tamanho mostrado pra não poluir mensagem
            $peek = mb_substr($apiResponse, 0, 800);
            $resultado .= "📎 *Resposta bruta (parcial):*\n";
            $resultado .= "```json\n" . $peek . (mb_strlen($apiResponse) > 800 ? "\n... (truncado)" : "") . "\n```\n\n";
        }
    }

    $resultado .= "💬 Precisa de algo a mais? Fala com: @falsifiquei";

    // Edita para o resultado final (usa Markdown)
    editMessage($chat_id, $message_id, $resultado);

    return;
}

// COMANDO /info
if ($message == "/info") {
    sendMessage($chat_id,
        "🔒 *DETALHES TÉCNICOS DAS NOTAS:*\n\n".
        "✅ Fita preta real (original)\n".
        "✅ Marca d’água legítima\n".
        "✅ Holográfico\n".
        "✅ Papel texturizado de alta gramatura\n".
        "✅ Tamanho exato das cédulas verdadeiras\n".
        "✅ Reage à luz UV (negativo e positivo)\n".
        "✅ Fibras UV embutidas na cédula\n".
        "✅ Passa em teste com caneta detectora\n\n".
        "🫡 Referência: @puxadasjoker"
    );
    exit;
}


// --- COMANDO /recado ---
// Uso: /recado 6124243 Notas
if (strpos($message, "/recado") === 0) {
    $admin_id = "7926471341"; // apenas admin pode usar — troque se quiser
    if ($chat_id != $admin_id) {
        sendMessage($chat_id, "❌ Você não tem permissão para enviar recados.");
        exit;
    }

    // Divide em 3 partes: comando, user_id, resto (item com possíveis espaços)
    $parts = preg_split('/\s+/', trim($message), 3);
    if (count($parts) < 3 || empty($parts[1]) || empty($parts[2])) {
        sendMessage($chat_id, "❌ Uso: /recado ID_DO_USUARIO ITEM\nEx: /recado 6124243 Notas Avançadas");
        exit;
    }

    $user_id = $parts[1];
    $item = $parts[2];

    // Grupo de destino (ID fornecido por você)
    $grupo_id = -1002552180485;

    // Monta a mensagem (escape simples para Markdown)
    $safe_user = str_replace(["`","*","_","["], ["","","",""], $user_id);
    $safe_item = str_replace(["`","*","_","["], ["","","",""], $item);

    $texto = "🥳 • *Mais um!*\n\n";
    $texto .= "👤 Usuário: `{$safe_user}`\n";
    $texto .= "🛒 Acabou de adquirir: *{$safe_item}*";

    // Envia para o grupo
    sendMessage($grupo_id, $texto);

    // Confirmação pra quem executou
    sendMessage($chat_id, "✅ Recado enviado para o grupo (ID: {$grupo_id}).");
    exit;
}

// GERAR CUPOM (SOMENTE VOCÊ)
if (strpos($message, "/gerarcupon") === 0) {
    if ($chat_id != "7926471341") {
        sendMessage($chat_id, "❌ Você não tem permissão para gerar cupons.");
        exit;
    }
    $parts = explode(" ", $message, 3);
    if (!isset($parts[1]) || empty($parts[1]) || !isset($parts[2])) {
        sendMessage($chat_id, "❌ Use o formato:\n/gerarcupon MEUCUPOM 25\n\n(O número é a porcentagem de desconto)");
        exit;
    }
    $nomeCupom = strtoupper(trim($parts[1]));
    $desconto = (int)$parts[2];
    if ($desconto < 1 || $desconto > 100) {
        sendMessage($chat_id, "❌ Informe uma porcentagem entre 1 e 100.");
        exit;
    }

    $cupons[$nomeCupom] = ["usado" => false, "desconto" => $desconto];
    file_put_contents($cuponsFile, json_encode($cupons));
    sendMessage($chat_id, "✅ Cupom `$nomeCupom` gerado com *$desconto% de desconto*!");
    exit;
}

// BLOQUEAR USUÁRIO DE USAR CUPOM
if (strpos($message, "/block") === 0) {
    if ($chat_id != "7926471341") { // apenas admin
        sendMessage($chat_id, "❌ Você não tem permissão para isso.");
        exit;
    }

    $parts = explode(" ", $message, 2);
    if (!isset($parts[1]) || !is_numeric($parts[1])) {
        sendMessage($chat_id, "❌ Use: /block ID_DO_USUARIO");
        exit;
    }

    $id = $parts[1];
    if (!in_array($id, $bloqueados)) {
        $bloqueados[] = $id;
        file_put_contents($bloqueadosFile, json_encode($bloqueados));
    }
    sendMessage($chat_id, "🚫 Usuário `$id` bloqueado de usar cupons.");
    exit;
}

// DESBLOQUEAR USUÁRIO
if (strpos($message, "/unblock") === 0) {
    if ($chat_id != "7926471341") { // apenas admin
        sendMessage($chat_id, "❌ Você não tem permissão para isso.");
        exit;
    }

    $parts = explode(" ", $message, 2);
    if (!isset($parts[1]) || !is_numeric($parts[1])) {
        sendMessage($chat_id, "❌ Use: /unblock ID_DO_USUARIO");
        exit;
    }

    $id = $parts[1];
    if (in_array($id, $bloqueados)) {
        $bloqueados = array_diff($bloqueados, [$id]);
        file_put_contents($bloqueadosFile, json_encode(array_values($bloqueados)));
    }
    sendMessage($chat_id, "✅ Usuário `$id` desbloqueado.");
    exit;
}

// RESGATAR CUPOM PELO USUÁRIO
if (strpos($message, "/resgatar") === 0) {
    
       // 🔒 Verifica se o usuário está bloqueado
    if (in_array($chat_id, $bloqueados)) {
        sendMessage($chat_id, "🚫 Você não tem permissão para usar cupons.");
        exit;
    }
    
    $parts = explode(" ", $message, 2);
    if (!isset($parts[1]) || empty($parts[1])) {
        sendMessage($chat_id, "❌ Digite o cupom que deseja resgatar. Exemplo:\n/resgatar MEUCUPOM");
        exit;
    }
    $cupomDigitado = strtoupper(trim($parts[1]));

    if (!isset($cupons[$cupomDigitado])) {
        sendMessage($chat_id, "❌ Cupom inválido!");
        exit;
    }
    if ($cupons[$cupomDigitado]["usado"]) {
        sendMessage($chat_id, "❌ Este cupom já foi usado!");
        exit;
    }

$usuarios[$chat_id]["cupom"] = $cupomDigitado;
$usuarios[$chat_id]["etapa"] = "nome"; // começa o formulário
file_put_contents($usuariosFile, json_encode($usuarios));

$desconto = $cupons[$cupomDigitado]["desconto"] ?? 30; // pega a % do cupom ou 30% padrão
sendMessage(
    $chat_id,
    "✅ Cupom aplicado com sucesso! Você receberá *{$desconto}% de desconto* no total.\n\nDigite seu *NOME COMPLETO* para iniciar o formulário:"
);
exit;
}

// ======= ARQUIVO PRINCIPAL DO BOT =======

// ARQUIVO PARA SALVAR STATUS DOS PEDIDOS
$statusFile = "status.json";
if (!file_exists($statusFile)) file_put_contents($statusFile, "{}");
$statuses = json_decode(file_get_contents($statusFile), true);

// ======= COMANDO ADMIN /setstatus =======
if (strpos($message, "/setstatus") === 0) {
    if ($chat_id != "7926471341") { // ID do admin
        sendMessage($chat_id, "❌ Você não tem permissão para isso.");
        exit;
    }

    $parts = explode(" ", $message, 2);
    if (!isset($parts[1]) || strlen($parts[1]) != 8) {
        sendMessage($chat_id, "❌ Digite o código de rastreio de 8 dígitos. Exemplo:\n/setstatus 12345678");
        exit;
    }

    $codigo = $parts[1];
    $keyboard = [
        "inline_keyboard" => [
            [["text"=>"📦 • Preparando", "callback_data"=>"status_{$codigo}_preparando"]],
            [["text"=>"🚛 • Em Transporte", "callback_data"=>"status_{$codigo}_transporte"]],
            [["text"=>"✅ • Entregue", "callback_data"=>"status_{$codigo}_entregue"]],
            [["text"=>"❌ • Cancelado", "callback_data"=>"status_{$codigo}_cancelado"]]
        ]
    ];

    sendMessage($chat_id, "Escolha o status do pedido `$codigo`:", $keyboard);
    exit;
}

// ======= CALLBACK DO STATUS =======
if (isset($callback_query) && strpos($callback_query, "status_") === 0) {
    $partes = explode("_", $callback_query);
    if (count($partes) < 3) exit;

    $codigo = $partes[1];
    $novoStatus = $partes[2];

    // Atualiza o status
    $statuses[$codigo] = $novoStatus;
    file_put_contents($statusFile, json_encode($statuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $statusTexto = match($novoStatus) {
        "preparando" => "📦 • Preparando",
        "transporte" => "🚛 • Em Transporte",
        "entregue" => "✅ • Entregue",
        "cancelado" => "❌ • Cancelado",
        default => "💰 • Validando Pagamento"
    };

    editMessage($chat_id, $message_id, "✅ Status do pedido `$codigo` atualizado para:\n$statusTexto");
    exit;
}

// ======= COMANDO /status =======
if (strpos($message, "/status") === 0) {
    $parts = explode(" ", $message, 2);
    if (!isset($parts[1]) || strlen($parts[1]) != 8) {
        sendMessage($chat_id, "❌ Digite seu código de rastreio de 8 dígitos. Exemplo:\n/status 12345678");
        exit;
    }

    $codigo = $parts[1];
    if (!isset($statuses[$codigo])) {
        sendMessage($chat_id, "❌ Pedido não encontrado ou ainda sem status definido.");
        exit;
    }

    $status = $statuses[$codigo];
    $statusTexto = match($status) {
        "preparando" => "📦 • Preparando",
        "validando" => "💰 • Validando Pagamento",
        "transporte" => "🚛 • Em Transporte",
        "entregue" => "✅ • Entregue",
        "cancelado" => "❌ • Cancelado",
        default => "💰 • Validando Pagamento"
    };

    sendMessage($chat_id, "📌 ~ Status do seu pedido `$codigo`:\n$statusTexto");
    exit;
}
// --- COMANDO /chip ---
if ($message == "/chip") {
    $keyboard = [
        "inline_keyboard" => [
            [["text" => "⛱️ • RJ", "callback_data" => "chip_RJ"]],
            [["text" => "🧀 • MG", "callback_data" => "chip_MG"]],
            [["text" => "☂️ • SP", "callback_data" => "chip_SP"]],
            [["text" => "🌎 • Outros", "callback_data" => "chip_Outros"]]
        ]
    ];
    sendMessage($chat_id, "📶 Escolha o *estado* para o chip:", $keyboard);
    exit;
}

// --- TRATAMENTO DO CALLBACK DOS CHIPS ---
if (strpos($callback_query, "chip_") === 0) {
    $estado = str_replace("chip_", "", $callback_query);

    // Definir os DDDs por estado
    $ddds = [
        "RJ" => ["21", "22", "24"],
        "MG" => ["31", "32", "33", "34", "35", "37", "38"],
        "SP" => ["11", "12", "13", "14", "15", "16", "17", "18", "19"],
        "Outros" => ["61", "62", "65", "67", "71", "81", "85", "91"] // alguns exemplos
    ];

    $ddd = $ddds[$estado][array_rand($ddds[$estado])];
    $final = rand(10, 99); // últimos 2 dígitos aleatórios

    $numeroFake = "+55 ($ddd) 9***-**$final";

    // --- Animação ---
    editMessage($chat_id, $message_id, "🔄 Validando *número*...");
    sleep(1);
    editMessage($chat_id, $message_id, "📦 Preparando...");
    sleep(1);
    editMessage($chat_id, $message_id, "🚛 Calculando...");
    sleep(5);
    editMessage($chat_id, $message_id, "✅ Finalizando seu pedido...");
    sleep(1);

    // --- Mensagem final ---
    $texto = 
    "📶 *Chip selecionado com sucesso!*\n\n".
    "🗺 Estado: *$estado*\n".
    "📱 Número gerado: `$numeroFake`\n".
    "💰 Valor: *R$15,00*\n\n".
    "📌 *Forma de pagamento:*\n".
    "🔹 PIX: `1aebb1bd-10b7-435e-bd17-03adf4451088`\n\n" .
    "📤 Após o pagamento, envie o comprovante para *@falsifiquei*.\n\n".
    "✅ Seu chip será liberado após a confirmação do pagamento.";

    editMessage($chat_id, $message_id, $texto);
    exit;
}

// COMANDO /comprar
if ($message == "/comprar") {
    $usuarios[$chat_id] = ["etapa" => "nome"];
    file_put_contents($usuariosFile, json_encode($usuarios));
    sendMessage($chat_id, "📝 Vamos começar o formulário.\n\nDigite seu *NOME COMPLETO*:");
    exit;
}

// FORMULÁRIO PASSO A PASSO
if (isset($usuarios[$chat_id]) && $message && !$callback_query) {
    $etapa = $usuarios[$chat_id]["etapa"];

    switch ($etapa) {
        case "nome":
            $usuarios[$chat_id]["nome"] = $message;
            $usuarios[$chat_id]["etapa"] = "rua";
            sendMessage($chat_id, "🏠 Informe sua *RUA*:");
            break;
        case "rua":
            $usuarios[$chat_id]["rua"] = $message;
            $usuarios[$chat_id]["etapa"] = "numero";
            sendMessage($chat_id, "🔢 Informe o *NÚMERO* da residência:");
            break;
        case "numero":
            if (!is_numeric($message)) {
                sendMessage($chat_id, "❌ Número inválido! Digite apenas números:");
                exit;
            }
            $usuarios[$chat_id]["numero"] = $message;
            $usuarios[$chat_id]["etapa"] = "cep";
            sendMessage($chat_id, "📮 Informe seu *CEP* (apenas números):");
            break;
        case "cep":
            if (!is_numeric($message) || strlen($message) != 8) {
                sendMessage($chat_id, "❌ CEP inválido! Digite um CEP válido:");
                exit;
            }
            $usuarios[$chat_id]["cep"] = $message;
            $usuarios[$chat_id]["etapa"] = "cidade";
            sendMessage($chat_id, "🌆 Informe sua *CIDADE*:");
            break;
        case "cidade":
            $usuarios[$chat_id]["cidade"] = $message;
            $usuarios[$chat_id]["etapa"] = "estado";
            sendMessage($chat_id, "🏙 Informe seu *ESTADO*:");
            break;
        case "estado":
            $usuarios[$chat_id]["estado"] = $message;
            $usuarios[$chat_id]["etapa"] = "bairro";
            sendMessage($chat_id, "📍 Informe seu *BAIRRO*:");
            break;
        case "bairro":
            $usuarios[$chat_id]["bairro"] = $message;
            $usuarios[$chat_id]["etapa"] = "cedulas";

            $keyboard = [
                "inline_keyboard" => [
                    [["text" => "💵 100 🐟", "callback_data" => "cedula_100"]],
                    [["text" => "💵 50 🐯", "callback_data" => "cedula_50"]],
                    [["text" => "💵 20 🐒", "callback_data" => "cedula_20"]],
                    [["text" => "💵 200 🐺", "callback_data" => "cedula_200"]]
                ]
            ];
            sendMessage($chat_id, "💸 Escolha o valor das *CÉDULAS*:", $keyboard);
            break;
    }
    file_put_contents($usuariosFile, json_encode($usuarios));
}

// TRATAMENTO DAS CÉDULAS
if (strpos($callback_query, "cedula_") === 0) {
    $usuarios[$chat_id]["cedulas"] = strtoupper(str_replace("cedula_", "", $callback_query));
    $usuarios[$chat_id]["etapa"] = "quantidade";
    file_put_contents($usuariosFile, json_encode($usuarios));

    $keyboard = [
        "inline_keyboard" => [
            [["text" => "💵 1K — R$170", "callback_data" => "qtd_1k"]],
            [["text" => "💵 2K — R$310", "callback_data" => "qtd_2k"]],
            [["text" => "💵 3K — R$450", "callback_data" => "qtd_3k"]],
            [["text" => "💵 4K — R$580", "callback_data" => "qtd_4k"]],
            [["text" => "💵 5K — R$740", "callback_data" => "qtd_5k"]],
            [["text" => "💵 10K — R$1.320", "callback_data" => "qtd_10k"]],
            [["text" => "💼 25K — R$2.270", "callback_data" => "qtd_25k"]],
            [["text" => "💼 50K+ — A combinar", "callback_data" => "qtd_50k"]]
        ]
    ];
    editMessage($chat_id, $message_id, "🔢 Escolha a *quantidade* desejada:", $keyboard);
}

// FINALIZAÇÃO E APLICAÇÃO DO CUPOM
if (strpos($callback_query, "qtd_") === 0) {
    $quantidade = str_replace("qtd_", "", $callback_query);

    $precos = ["1k"=>170,"2k"=>310,"3k"=>450,"4k"=>580,"5k"=>740,"10k"=>1320,"25k"=>2270,"50k"=>0];
    $usuarios[$chat_id]["quantidade"] = strtoupper($quantidade);
    $preco = $precos[$quantidade] ?? 0;

    $cep_destino = $usuarios[$chat_id]["cep"];
    $frete = ($quantidade === "50k") ? 0 : calcularFrete($cep_destino);
    $total = $preco + $frete;

    if (!empty($usuarios[$chat_id]["cupom"])) {
    $cupom = $usuarios[$chat_id]["cupom"];
    $desconto = $cupons[$cupom]["desconto"] ?? 30; // se não achar, usa 30%
    $totalComDesconto = $total * ((100 - $desconto) / 100);
    $cupons[$cupom]["usado"] = true;
    file_put_contents($cuponsFile, json_encode($cupons));
} else {
    $totalComDesconto = $total;
}

    // GERAÇÃO DO CÓDIGO DE RASTREIO AUTOMÁTICO
    $codigoRastreio = str_pad(rand(0, 99999999), 8, "0", STR_PAD_LEFT);
    $usuarios[$chat_id]["codigo_rastreio"] = $codigoRastreio;

    // SALVAR STATUS INICIAL DO PEDIDO
    $statusFile = "status.json";
    if (!file_exists($statusFile)) file_put_contents($statusFile, "{}");
    $statuses = json_decode(file_get_contents($statusFile), true);
    $statuses[$codigoRastreio] = "validando"; // status inicial
    file_put_contents($statusFile, json_encode($statuses));

    editMessage($chat_id, $message_id, "🔄 Calculando *quantidade*...");
    sleep(1);
    editMessage($chat_id, $message_id, "📦 Preparando *envio*...");
    sleep(1);
    editMessage($chat_id, $message_id, "🚛 Calculando *frete*...");
    sleep(5);
    editMessage($chat_id, $message_id, "✅ Finalizando seu pedido...");
    sleep(1);

    $dados = $usuarios[$chat_id];
    $resumo =
        "✅ *Formulário preenchido com sucesso!*\n\n" .
        "👤 Nome: {$dados['nome']}\n" .
        "🏠 Rua: {$dados['rua']}, Nº {$dados['numero']}\n" .
        "📮 CEP: {$dados['cep']}\n" .
        "🌆 Cidade: {$dados['cidade']} - {$dados['estado']}\n" .
        "📍 Bairro: {$dados['bairro']}\n" .
        "💵 Cédulas: {$dados['cedulas']}\n" .
        "🔢 Quantidade: {$usuarios[$chat_id]['quantidade']}\n" .
        "💰 Valor: R$" . number_format($preco, 2, ',', '.') . "\n" .
        "🚛 Frete: R$" . number_format($frete, 2, ',', '.') . "\n" . 
       (!empty($usuarios[$chat_id]["cupom"]) 
    ? "🎟️ Desconto aplicado: {$cupons[$usuarios[$chat_id]['cupom']]['desconto']}%\n" 
    : "") .
        "💳 *Total a Pagar*: R$" . number_format($totalComDesconto, 2, ',', '.') . "\n\n" .
        "📌 *Forma de pagamento:*\n".
        "🔹 PIX: `1aebb1bd-10b7-435e-bd17-03adf4451088`\n\n" .
        "📤 *Após o pagamento, envie o comprovante para*: @falsifiquei\n\n" .
        "📦 *Código de rastreio do pedido:* `$codigoRastreio`\n" .
        "Use o comando /status seguido do código para acompanhar seu pedido.";

    editMessage($chat_id, $message_id, $resumo);

    unset($usuarios[$chat_id]);
    file_put_contents($usuariosFile, json_encode($usuarios));
}
?>