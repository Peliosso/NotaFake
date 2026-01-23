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

// TRATAMENTO UNIFICADO DE CALLBACKS (cole no lugar dos ifs de callback)
if (isset($update['callback_query'])) {

    $cb = $update['callback_query'];
    $callback_id = $cb['id'] ?? null;                     // usado para answerCallbackQuery
    $callback_data = $cb['data'] ?? null;                 // o data do botão
    $message_id_cb = $cb['message']['message_id'] ?? null; // id da mensagem do botão
    $chat_id_cb = $cb['message']['chat']['id'] ?? null;   // chat id da mensagem do botão
    $from_id = $cb['from']['id'] ?? null;

    // sempre responda o callback para remover o "loading" no botão
    if ($callback_id) {
        // option: enviar sem texto
        @file_get_contents($apiURL . "answerCallbackQuery?callback_query_id=" . $callback_id);
    }

    // --- Apagar mensagem enviada pelo bot ---
    if ($callback_data === "cpf_del" || $callback_data === "apagar_msg" || $callback_data === "cpf_del") {
        // tenta apagar a mensagem (usar chat_id e message_id vindos do callback)
        if ($chat_id_cb && $message_id_cb) {
            $resp = @file_get_contents($apiURL . "deleteMessage?chat_id=" . $chat_id_cb . "&message_id=" . $message_id_cb);

            // checagem simples: se falhar, avisa o usuário com uma edição ou envio
            if ($resp === false) {
                // Se não conseguir apagar (permissão), edita para indicar que foi pedido apagar
                $txt = "🗑️ Pedido de remoção recebido. Não foi possível apagar automaticamente (permissão).";
                // tenta editar a mensagem (só vai funcionar se o bot puder editar)
                @file_get_contents($apiURL . "editMessageText?" . http_build_query([
                    'chat_id' => $chat_id_cb,
                    'message_id' => $message_id_cb,
                    'text' => $txt,
                    'parse_mode' => 'Markdown'
                ]));
            }
        } else {
            // fallback: tenta apagar usando chat_id global (caso você tenha)
            if (isset($chat_id) && isset($message_id)) {
                @file_get_contents($apiURL . "deleteMessage?chat_id=" . $chat_id . "&message_id=" . $message_id);
            }
        }
        exit;
    }

    // --- Exemplo: tratar cpf_emails/cpf_end/cpf_back usando callback_data ---
    if ($callback_data === "cpf_emails") {
        // ... seu código para montar texto de emails ...
        // lembre-se de usar $chat_id_cb e $message_id_cb ao editar
        $txt = "📧 Emails aqui...";
        $kb = [ "inline_keyboard" => [
            [["text"=>"⬅️ Voltar","callback_data"=>"cpf_back"], ["text"=>"🗑 Apagar","callback_data"=>"cpf_del"]]
        ]];
        @file_get_contents($apiURL . "editMessageText?" . http_build_query([
            'chat_id' => $chat_id_cb,
            'message_id' => $message_id_cb,
            'text' => $txt,
            'reply_markup' => json_encode($kb),
            'parse_mode' => 'Markdown'
        ]));
        exit;
    }

    if ($callback_data === "cpf_end") {
        // ... montar endereços ...
        $txt = "🏠 Endereços aqui...";
        $kb = [ "inline_keyboard" => [
            [["text"=>"⬅️ Voltar","callback_data"=>"cpf_back"], ["text"=>"🗑 Apagar","callback_data"=>"cpf_del"]]
        ]];
        @file_get_contents($apiURL . "editMessageText?" . http_build_query([
            'chat_id' => $chat_id_cb,
            'message_id' => $message_id_cb,
            'text' => $txt,
            'reply_markup' => json_encode($kb),
            'parse_mode' => 'Markdown'
        ]));
        exit;
    }

    if ($callback_data === "cpf_back") {
        $txt = "🕵️ *Consulta CPF*\n\n👇 Escolha abaixo.\n\n🔧 Créditos: @seu_usuario_aqui";
        $kb = [ "inline_keyboard" => [
            [["text"=>"📧 Emails","callback_data"=>"cpf_emails"]],
            [["text"=>"🏠 Endereços","callback_data"=>"cpf_end"]],
            [["text"=>"🗑 Apagar","callback_data"=>"cpf_del"]]
        ]];
        @file_get_contents($apiURL . "editMessageText?" . http_build_query([
            'chat_id' => $chat_id_cb,
            'message_id' => $message_id_cb,
            'text' => $txt,
            'reply_markup' => json_encode($kb),
            'parse_mode' => 'Markdown'
        ]));
        exit;
    }

}

// --- CALLBACK DE INFORMACOES / VOLTAR
if(isset($update->callback_query)){

    $callback = $update->callback_query->data;
    $chat_id_callback = $update->callback_query->message->chat->id;
    $msgid = $update->callback_query->message->message_id;

    // INFO OBITO
    if($callback == "info_obito"){

        $texto_info = "⚠️ *Sobre óbito*\n\n• O falecimento pode demorar até **7 dias** para constar em todos os sistemas.\n• Nem sempre atualiza primeiro no Serasa.\n• Pode constar em 1 sistema e no outro ainda não.\n\n🔙 Clique em *Voltar* para retornar à consulta.";

        $inline_voltar = [
            'inline_keyboard' => [
                [
                    ['text' => "🔙 Voltar", 'callback_data' => "info_voltar"]
                ]
            ]
        ];

        editMessageText($chat_id_callback, $msgid, $texto_info, "Markdown", $inline_voltar);
        exit;
    }

    // VOLTAR
    if($callback == "info_voltar"){
        // aqui você só re-renderiza a consulta padrão

        $texto_normal = "Selecione o que deseja fazer 👇";

        $inline_normal = [
            'inline_keyboard' => [
                [
                    ['text' => "ℹ️ Informações sobre óbito", 'callback_data' => "info_obito"]
                ]
            ]
        ];

        editMessageText($chat_id_callback, $msgid, $texto_normal, "Markdown", $inline_normal);
        exit;
    }
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
        . "🔹 PIX: 4216aab3-7990-4af1-a5fd-73ce85e57deb\n\n"
        . "📤 Após o pagamento, envie o *comprovante* para: @silenciante";

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
    ."💬 Fale diretamente comigo:\n👉 [@silenciante](https://t.me/silenciante)\n\n"
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
        sendMessage($chat_id, "❌ • *Você não tem permissão para usar este comando*.\n💰 Para acessar, fale comigo: @silenciante*");
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

// Importante: Você precisa ter a função 'consultarCpfApi' e as funções do Telegram (sendMessage, editMessage) definidas em algum lugar do seu código.

function comandoConsultaSimulada($chat_id, $cpf) {
    // ID autorizado
    $admin_id = "7834029992"; // só você pode usar
    if ($chat_id != $admin_id) {
        sendMessage($chat_id, "❌ • *Você não tem permissão para usar este comando*.\n💰 Para acessar, fale comigo: @silenciante*");
        exit;
    }

    // Rate limit: 3x por hora
    if (!checkRateLimit($chat_id, 3, 3600)) {
        sendMessage($chat_id, "⛔ • *Limite de uso atingido.*\nVocê só pode usar este comando *3 vezes por hora*. Tente novamente mais tarde.");
        return;
    }

    // Mensagens de etapa (texto que aparecerá durante a edição)
    $etapas = [
        ["text" => "🔄 • *Iniciando módulo de consulta...*",       "sub" => "Acessando infraestrutura"],
        ["text" => "🔐 • *Acessando Cadsus...*",                 "sub" => "Conexão segura estabelecida"],
        ["text" => "⏳ • *Validando CPF no banco de dados...*",  "sub" => "Verificando integridade dos dados"],
        ["text" => "📂 • *Consultando registros do cartório...*", "sub" => "Procurando entradas relevantes"],
        ["text" => "🔎 • *Processando informações...*",          "sub" => "Compilando relatório final"]
    ];

    // Envia mensagem inicial e obtém message_id (usa tua função sendMessage)
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
    $steps = 10; // número de atualizações de progresso
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

    usleep(500000);

    // --- Chamada da API real (a que você passou) ---
    $apiUrl = "https://apis-brasil.shop/apis/apiserasacpf2025.php?cpf=" . urlencode($cpf);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; Bot/1.0)");
    $apiResponse = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $nome = "NÃO ENCONTRADO";
    $nasc_raw = "";
    $nasc_fmt = "NÃO INFORMADO";

    if ($apiResponse === false || $curlErr) {
        $resultado  = "⚠️ *RESULTADO:*\n\n";
        $resultado .= "❌ *Erro ao acessar a API fornecida.*\n";
        $resultado .= "Detalhes: " . ($curlErr ? $curlErr : "Resposta vazia") . "\n\n";
        $resultado .= "🔹 *CPF consultado:* `$cpf`\n";
        $resultado .= "🔹 *Nome:* $nome\n";
        $resultado .= "🔹 *Data de nascimento:* $nasc_fmt\n\n";
        $resultado .= "💬 Precisa de algo a mais? Fala com: @silenciante";

        // salva para "Voltar"
        saveLastResult($chat_id, $resultado);

        // adiciona botão info
        $keyboard = [
            "inline_keyboard" => [
                [
                    ["text" => "ℹ️ Informações", "callback_data" => "info_obito"],
                ]
            ]
        ];
        editMessage($chat_id, $message_id, $resultado, $keyboard);
        return;
    }

    $json = json_decode($apiResponse, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($json['DADOS'])) {
        $dados = $json['DADOS'];
        if (!empty($dados['NOME'])) {
            $nome = $dados['NOME'];
        }
        if (!empty($dados['NASC']) && $dados['NASC'] !== "0000-00-00 00:00:00" && strtoupper($dados['NASC']) !== "NULL") {
            $nasc_raw = $dados['NASC'];
            $parts = preg_split('/\s+/', trim($nasc_raw));
            $datePart = $parts[0];
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datePart, $m)) {
                $nasc_fmt = $m[3] . "/" . $m[2] . "/" . $m[1];
            } else {
                $ts = strtotime($nasc_raw);
                if ($ts !== false) {
                    $nasc_fmt = date("d/m/Y", $ts);
                } else {
                    $nasc_fmt = $nasc_raw;
                }
            }
        }
    } else {
        $resultado  = "⚠️ *RESULTADO:*\n\n";
        $resultado .= "❌ *Resposta da API inválida ou sem dados esperados.*\n";
        $resultado .= "🔹 *CPF consultado:* `$cpf`\n";
        $resultado .= "🔹 *Nome:* $nome\n";
        $resultado .= "🔹 *Data de nascimento:* $nasc_fmt\n\n";
        $resultado .= "💬 Precisa de algo a mais? Fala com: @silenciante";

        saveLastResult($chat_id, $resultado);
        $keyboard = [
            "inline_keyboard" => [
                [
                    ["text" => "ℹ️ Informações", "callback_data" => "info_obito"],
                ]
            ]
        ];
        editMessage($chat_id, $message_id, $resultado, $keyboard);
        return;
    }

    // Monta resultado final com botão "Informações"
    $simulacaoNota = "⚠️ *RESULTADO:*\n\n";
    $resultado  = $simulacaoNota;
    $resultado .= "🔹 *CPF consultado:* `$cpf`\n";
    $resultado .= "🔹 *Nome:* *" . $nome . "*\n";
    $resultado .= "🔹 *Data de nascimento:* `" . $nasc_fmt . "`\n";
    $resultado .= "🔹 *Cartório:* `Oficial de Registro Civil das Pessoas Naturais do 18º Subdistrito – Ipiranga`\n";
    $resultado .= "🔹 *Status da busca:* *CONSULTA REALIZADA*\n";
    $resultado .= "🔹 *Última atualização:* `" . date("d/m/Y H:i:s") . "`\n\n";
    $resultado .= "💬 Precisa de algo a mais? Fala com: @silenciante";

    // salva para "Voltar"
    saveLastResult($chat_id, $resultado);

    // teclado inline com "Informações"
    $keyboard = [
        "inline_keyboard" => [
            [
                ["text" => "ℹ️ Informações", "callback_data" => "info_obito"],
            ]
        ]
    ];

    editMessage($chat_id, $message_id, $resultado, $keyboard);
    return;
}

// ---------------------------------------------------------
// Handler de callback_query (chame este trecho no seu webhook principal)
// ---------------------------------------------------------
function handleCallbackQuery($update) {
    // espera $update ter ['callback_query']
    if (!isset($update['callback_query'])) return;

    $cb = $update['callback_query'];
    $chat_id = $cb['message']['chat']['id'];
    $message_id = $cb['message']['message_id'];
    $data = $cb['data'];
    $callback_id = $cb['id'];

    // resposta curta para remover "carregando" no cliente
    answerCallbackQuery($callback_id, "");

    if ($data === "info_obito") {
        $infoText  = "ℹ️ *Informações importantes sobre o registro de óbito*\n\n";
        $infoText .= "• O registro de óbito é um evento oficial que pode impactar diversos cadastros e sistemas (cartório, previdência, bancos e bases comerciais).\n\n";
        $infoText .= "• *Malefícios / impactos possíveis*: bloqueio de benefícios, suspensão de cadastros, problemas em cadastros de serviços e validações automáticas em empresas que usam bases centralizadas.\n\n";
        $infoText .= "• *Observação importante:* pode demorar até *1 semana* para que a informação conste em todas as bases e sistemas que replicam esses dados.\n";
        $infoText .= "Algumas integrações são assíncronas e dependem de atualizações periódicas.\n\n";
        $infoText .= "Se quiser, toque em *Voltar* para retornar ao resultado anterior.";

        // teclado voltar
        $keyboard = [
            "inline_keyboard" => [
                [
                    ["text" => "🔙 Voltar", "callback_data" => "voltar_obito"],
                ]
            ]
        ];

        // edita a mensagem original com as informações
        editMessage($chat_id, $message_id, $infoText, $keyboard);
        return;
    }

    if ($data === "voltar_obito") {
        // tenta recuperar último resultado salvo
        $last = loadLastResult($chat_id);
        if ($last === false) {
            $fallback = "❌ Não foi possível recuperar o resultado anterior.\nTente realizar a consulta novamente.";
            // teclado: só fechar
            $keyboard = [
                "inline_keyboard" => [
                    [
                        ["text" => "Fechar", "callback_data" => "fechar_dummy"]
                    ]
                ]
            ];
            editMessage($chat_id, $message_id, $fallback, $keyboard);
            return;
        }

        // adiciona novamente botão Informações
        $keyboard = [
            "inline_keyboard" => [
                [
                    ["text" => "ℹ️ Informações", "callback_data" => "info_obito"],
                ]
            ]
        ];
        editMessage($chat_id, $message_id, $last, $keyboard);
        return;
    }

    // caso dummy / outros callbacks
    answerCallbackQuery($callback_id, "Ação não disponível.");
}

// ---------------------------------------------------------
// Rate limit helpers (persist em arquivo JSON simples)
// ---------------------------------------------------------
function checkRateLimit($chat_id, $maxCalls, $perSeconds) {
    $file = __DIR__ . "/usage_{$chat_id}.json";
    $now = microtime(true);

    $timestamps = [];
    if (file_exists($file)) {
        $json = @file_get_contents($file);
        $timestamps = json_decode($json, true);
        if (!is_array($timestamps)) $timestamps = [];
    }

    // remove registros mais antigos que $perSeconds
    $cut = $now - $perSeconds;
    $timestamps = array_filter($timestamps, function($t) use ($cut) {
        return ($t >= $cut);
    });

    if (count($timestamps) >= $maxCalls) {
        // já atingiu limite
        // reescreve limpa para manter arquivo enxuto
        file_put_contents($file, json_encode(array_values($timestamps)));
        return false;
    }

    // permite e adiciona timestamp atual
    $timestamps[] = $now;
    file_put_contents($file, json_encode(array_values($timestamps)));
    return true;
}

// ---------------------------------------------------------
// Save / Load último resultado (para botão Voltar)
// ---------------------------------------------------------
function saveLastResult($chat_id, $text) {
    $file = __DIR__ . "/last_result_{$chat_id}.txt";
    // salvamos o texto bruto (Markdown)
    file_put_contents($file, $text);
}

function loadLastResult($chat_id) {
    $file = __DIR__ . "/last_result_{$chat_id}.txt";
    if (!file_exists($file)) return false;
    $txt = file_get_contents($file);
    return $txt === false ? false : $txt;
}


// --- /cpf com animação progressiva + resultado em TXT COMPLETO ---
if (strpos($message, "/cpf") === 0) {

    $parts = explode(" ", $message);
    if (!isset($parts[1])) {
        sendMessage($chat_id, "❌ Uso correto:\n`/cpf 12345678910`");
        exit;
    }

    $cpf = preg_replace("/\D/", "", $parts[1]);

    $loading = "🔎 Consultando CPF...\n\n⬜⬜⬜⬜⬜⬜⬜⬜⬜⬜ 0%";
    $msg_id = sendMessage($chat_id, $loading);

    function progressoCPF($chat_id, $msg_id, $porcentagem){
        global $apiURL;
        $total = 10;
        $preenchido = floor($porcentagem / 10);
        $bar = str_repeat("🟩", $preenchido) . str_repeat("⬜", $total - $preenchido);
        file_get_contents($apiURL."editMessageText?chat_id=$chat_id&message_id=$msg_id&text=".urlencode("🔎 Consultando CPF...\n\n$bar $porcentagem%"));
    }

    sleep(1); progressoCPF($chat_id,$msg_id,30);
    sleep(1); progressoCPF($chat_id,$msg_id,60);
    sleep(1); progressoCPF($chat_id,$msg_id,90);

    $api = "https://apis-brasil.shop/apis/apiserasacpf2025.php?cpf=$cpf";
    $json = @file_get_contents($api);

    if(!$json){
        file_get_contents($apiURL."editMessageText?chat_id=$chat_id&message_id=$msg_id&text=❌ Sem resposta da API");
        exit;
    }

    $r = json_decode($json, true);

    if(!isset($r["DADOS"])){
        file_get_contents($apiURL."editMessageText?chat_id=$chat_id&message_id=$msg_id&text=❌ CPF não encontrado");
        exit;
    }

    $dados = $r["DADOS"];

    // ===== FUNÇÃO SIGNO =====
    function calcularSigno($data){
        [$ano,$mes,$dia] = explode('-', substr($data,0,10));
        $dia = (int)$dia; $mes = (int)$mes;

        $signos = [
            ['Capricórnio',19],['Aquário',18],['Peixes',20],['Áries',19],
            ['Touro',20],['Gêmeos',20],['Câncer',22],['Leão',22],
            ['Virgem',22],['Libra',22],['Escorpião',21],['Sagitário',21]
        ];

        return ($dia > $signos[$mes-1][1]) ? $signos[$mes][0] ?? 'Capricórnio' : $signos[$mes-1][0];
    }

    $signo = $dados["NASC"] ? calcularSigno($dados["NASC"]) : "Não informado";

    // ===== STATUS ÓBITO =====
    $statusObito = empty($dados["DT_OB"]) ? "✅ Tá viva!" : "☠️ Tá morta!";

    $nomePai = empty($dados["NOME_PAI"]) ? "Comprou cigarro!" : $dados["NOME_PAI"];
    $nomeMae = empty($dados["NOME_MAE"]) ? "Comprou cigarro!" : $dados["NOME_MAE"];

    // ===== TXT =====
    $conteudoTXT =
"==============================
✅ CONSULTA CPF COMPLETA
==============================

👤 NOME: {$dados["NOME"]}
📄 CPF: {$dados["CPF"]}
⚧ SEXO: {$dados["SEXO"]}
🎂 NASCIMENTO: {$dados["NASC"]}
🔮 SIGNO: $signo
💀 STATUS: $statusObito

👩 MÃE: $nomeMae
👨 PAI: $nomePai

💰 RENDA: {$dados["RENDA"]}
📊 MOSAIC: {$dados["CD_MOSAIC"]}

==============================
📞 TELEFONES
==============================\n";

    if(isset($r["TELEFONE"]) && count($r["TELEFONE"]) > 0){
        foreach($r["TELEFONE"] as $t){
            $conteudoTXT .= "- ({$t["DDD"]}) {$t["TELEFONE"]} | Classificação: {$t["CLASSIFICACAO"]}\n";
        }
    } else {
        $conteudoTXT .= "Nenhum telefone encontrado\n";
    }

    $conteudoTXT .= "\n==============================
📧 EMAILS
==============================\n";

    if(isset($r["EMAIL"]) && count($r["EMAIL"]) > 0){
        foreach($r["EMAIL"] as $e){
            $conteudoTXT .= "- {$e["EMAIL"]}\n";
        }
    } else {
        $conteudoTXT .= "Nenhum e-mail encontrado\n";
    }

    $conteudoTXT .= "\n==============================
🏠 ENDEREÇOS
==============================\n";

    if(isset($r["ENDERECOS"]) && count($r["ENDERECOS"]) > 0){
        foreach($r["ENDERECOS"] as $end){
            $conteudoTXT .= "- {$end["LOGR_NOME"]}, {$end["LOGR_NUMERO"]} - {$end["BAIRRO"]} - {$end["CIDADE"]}/{$end["UF"]} CEP {$end["CEP"]}\n";
        }
    } else {
        $conteudoTXT .= "Nenhum endereço encontrado\n";
    }

    $conteudoTXT .= "\n==============================
👪 PARENTES
==============================\n";

    if(isset($r["PARENTES"]) && count($r["PARENTES"]) > 0){
        foreach($r["PARENTES"] as $p){
            $conteudoTXT .= "- {$p["NOME_VINCULO"]} ({$p["VINCULO"]}) CPF: {$p["CPF_VINCULO"]}\n";
        }
    } else {
        $conteudoTXT .= "Nenhum parente registrado\n";
    }

    $conteudoTXT .= "\n==============================
📊 SCORE
==============================\n";

    if(isset($r["SCORE"][0])){
        $s = $r["SCORE"][0];
        $conteudoTXT .= "CSB8: {$s["CSB8"]} ({$s["CSB8_FAIXA"]})\nCSBA: {$s["CSBA"]} ({$s["CSBA_FAIXA"]})\n";
    } else {
        $conteudoTXT .= "Score não disponível\n";
    }

    $conteudoTXT .= "\n==============================
💎 PODER AQUISITIVO
==============================\n";

    if(isset($r["PODER_AQUISITIVO"][0])){
        $pa = $r["PODER_AQUISITIVO"][0];
        $conteudoTXT .= "{$pa["PODER_AQUISITIVO"]} - {$pa["FX_PODER_AQUISITIVO"]}\n";
    } else {
        $conteudoTXT .= "Não informado\n";
    }

    $conteudoTXT .= "\n==============================
🔐 Consulta gerada por:
⚙️ Dono @silenciante
🤖 Bot @notafalsa_bot
💻 Sistema exclusivo
==============================";

    $nomeArquivo = "consulta_cpf_".time().".txt";
    file_put_contents($nomeArquivo, $conteudoTXT);

    file_get_contents($apiURL."deleteMessage?chat_id=$chat_id&message_id=$msg_id");

    $kb = [
        "inline_keyboard" => [
            [
                ["text" => "🗑 Apagar", "callback_data" => "cpf_full_del"],
                ["text" => "💸 Nota Falsa", "url" => "https://t.me/notafalsa_bot"]
            ]
        ]
    ];

// Pega o @username do usuário que enviou a mensagem
$username = isset($update["message"]["from"]["username"]) ? "@".$update["message"]["from"]["username"] : "Desconhecido";

$url = $apiURL."sendDocument";
$post_fields = [
    'chat_id' => $chat_id,
    'document' => new CURLFile(realpath($nomeArquivo)),
    'caption' => "✅ Consulta completa gerada com sucesso!\n\n📄 CPF consultado: {$dados["CPF"]}\n👤 Usuário: {$username}\n\n📁 Clique no arquivo TXT acima para ver o resultado completo.",
    'parse_mode' => 'Markdown',
    'reply_markup' => json_encode($kb)
];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_exec($ch);
    curl_close($ch);

    unlink($nomeArquivo);
    exit;
}

// --- /placa com animação progressiva + resultado em TXT COMPLETO ---
if (strpos($message, "/placa") === 0) {

    $parts = explode(" ", $message);
    if (!isset($parts[1])) {
        sendMessage($chat_id, "❌ Uso correto:\n`/placa ABC1234`");
        exit;
    }

    $placa = strtoupper(preg_replace("/[^A-Za-z0-9]/", "", $parts[1]));

    $loading = "🚗 Consultando PLACA...\n\n⬜⬜⬜⬜⬜⬜⬜⬜⬜⬜ 0%";
    $msg_id = sendMessage($chat_id, $loading);

    function progressoPlacaBarra($chat_id, $msg_id, $porcentagem){
        global $apiURL;
        $total = 10;
        $preenchido = floor($porcentagem / 10);
        $bar = str_repeat("🟩", $preenchido) . str_repeat("⬜", $total - $preenchido);
        @file_get_contents($apiURL."editMessageText?chat_id=$chat_id&message_id=$msg_id&text=".urlencode("🚗 Consultando PLACA...\n\n$bar $porcentagem%"));
    }

    sleep(1); progressoPlacaBarra($chat_id,$msg_id,30);
    sleep(1); progressoPlacaBarra($chat_id,$msg_id,60);
    sleep(1); progressoPlacaBarra($chat_id,$msg_id,90);

    // ==== CONSULTA API PLACA ====
    $url = "https://api-dh.ciphers.systems/api/v2/vehicle/plate/?plate=$placa";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "x-api-key: e5feed09a3d756904a64af230c410fb945a0e34f5ecd5ef496b057df22abec9d",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $json = curl_exec($ch);
    curl_close($ch);

    if(!$json){
        file_get_contents($apiURL."editMessageText?chat_id=$chat_id&message_id=$msg_id&text=❌ Erro ao consultar API");
        exit;
    }

    $r = json_decode($json, true);

    if (!isset($r["data"]) || !is_array($r["data"])) {
        file_get_contents($apiURL."editMessageText?chat_id=$chat_id&message_id=$msg_id&text=❌ Placa não encontrada");
        exit;
    }

    $d = $r["data"];

    $conteudoTXT =
"==============================
✅ CONSULTA DE PLACA COMPLETA
==============================

🚗 PLACA: {$placa}
🏷 MODELO: ".($d["model"] ?? 'Não informado')."
🏭 FABRICANTE: ".($d["brand"] ?? 'Não informado')."
📅 ANO: ".($d["year"] ?? 'Não informado')."
🎨 COR: ".($d["color"] ?? 'Não informado')."
📍 MUNICÍPIO: ".($d["city"] ?? 'Não informado')."
🗺 UF: ".($d["state"] ?? 'Não informado')."

🛠 CHASSI: ".($d["chassis"] ?? 'Não informado')."
🚨 SITUAÇÃO: ".($d["status"] ?? 'Não informado')."

==============================
🔐 Consulta gerada por:
⚙️ Dono @silenciante
🤖 Bot @notafalsa_bot
💻 Sistema exclusivo
==============================";

    $nomeArquivo = "consulta_placa_".time().".txt";
    file_put_contents($nomeArquivo, $conteudoTXT);

    file_get_contents($apiURL."deleteMessage?chat_id=$chat_id&message_id=$msg_id");

    $kb = [
        "inline_keyboard" => [
            [
                ["text" => "🗑 Apagar", "callback_data" => "placa_full_del"],
                ["text" => "💸 Nota Falsa", "url" => "https://t.me/notafalsa_bot"]
            ]
        ]
    ];

    $username = isset($update["message"]["from"]["username"]) ? "@".$update["message"]["from"]["username"] : "Desconhecido";

    $urlSend = $apiURL."sendDocument";
    $post_fields = [
        'chat_id' => $chat_id,
        'document' => new CURLFile(realpath($nomeArquivo)),
        'caption' => "✅ Consulta completa gerada com sucesso!\n\n🚗 Placa consultada: {$placa}\n👤 Usuário: {$username}\n\n📁 Clique no arquivo TXT acima para ver o resultado completo.",
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode($kb)
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
    curl_setopt($ch, CURLOPT_URL, $urlSend);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_exec($ch);
    curl_close($ch);

    unlink($nomeArquivo);
    exit;
}

// --- /nome com animação progressiva + resultado em TXT ---
if (strpos($message, "/nome") === 0) {

    $parts = explode(" ", $message, 2);
    if (!isset($parts[1])) {
        sendMessage($chat_id, "❌ Uso correto:\n`/nome NOME COMPLETO`");
        exit;
    }

    $nome = strtoupper(trim($parts[1]));
    $nome_api = urlencode($nome);

    $loading = "🔎 Consultando NOME...\n\n⬜⬜⬜⬜⬜⬜⬜⬜⬜⬜ 0%";
    $msg_id = sendMessage($chat_id, $loading);

    function progressoNome($chat_id, $msg_id, $porcentagem){
        global $apiURL;
        $total = 10;
        $preenchido = floor($porcentagem / 10);
        $bar = str_repeat("🟩", $preenchido) . str_repeat("⬜", $total - $preenchido);
        file_get_contents($apiURL."editMessageText?chat_id=$chat_id&message_id=$msg_id&text=".urlencode("🔎 Consultando NOME...\n\n$bar $porcentagem%"));
    }

    sleep(1); progressoNome($chat_id,$msg_id,30);
    sleep(1); progressoNome($chat_id,$msg_id,60);
    sleep(1); progressoNome($chat_id,$msg_id,90);

    $api = "https://apis-brasil.shop/apis/apiserasanome2025.php?nome=$nome_api";
    $json = @file_get_contents($api);

    if(!$json){
        file_get_contents($apiURL."editMessageText?chat_id=$chat_id&message_id=$msg_id&text=❌ Sem resposta da API");
        exit;
    }

    $r = json_decode($json, true);

    if(!isset($r[0]["DADOS"])){
        file_get_contents($apiURL."editMessageText?chat_id=$chat_id&message_id=$msg_id&text=❌ Nome não encontrado");
        exit;
    }

    $dados = $r[0]["DADOS"];

    // ===== MONTAR CONTEÚDO TXT =====
    $conteudoTXT =
"==============================
✅ CONSULTA POR NOME
==============================

NOME: {$dados["NOME"]}
CPF: {$dados["CPF"]}
SEXO: {$dados["SEXO"]}
NASCIMENTO: {$dados["NASC"]}
MÃE: {$dados["NOME_MAE"]}
PAI: {$dados["NOME_PAI"]}

==============================
📧 EMAILS
==============================\n";

    if(isset($r[0]["EMAIL"]) && count($r[0]["EMAIL"]) > 0){
        foreach($r[0]["EMAIL"] as $e){
            $conteudoTXT .= "- ".$e["EMAIL"]."\n";
        }
    } else {
        $conteudoTXT .= "Nenhum encontrado\n";
    }

    $conteudoTXT .= "\n==============================
🏠 ENDEREÇOS
==============================\n";

    if(isset($r[0]["ENDERECOS"]) && count($r[0]["ENDERECOS"]) > 0){
        foreach($r[0]["ENDERECOS"] as $end){
            $conteudoTXT .= "- {$end["LOGR_NOME"]}, {$end["LOGR_NUMERO"]} - {$end["BAIRRO"]} - {$end["CIDADE"]}/{$end["UF"]}\n";
        }
    } else {
        $conteudoTXT .= "Nenhum encontrado\n";
    }

    $conteudoTXT .= "\n==============================
🔐 Consulta gerada por:
⚙️ Dono @silenciante
🤖 Bot @notafalsa_bot
💻 Sistema exclusivo
==============================";

    // Criar TXT temporário
    $nomeArquivo = "consulta_nome_".time().".txt";
    file_put_contents($nomeArquivo, $conteudoTXT);

    // Apagar mensagem de carregamento
    file_get_contents($apiURL."deleteMessage?chat_id=$chat_id&message_id=$msg_id");

    // Mensagem de sucesso + botões
    $textoSucesso = "✅ *Consulta realizada com sucesso!*\n\n📄 Clique no arquivo TXT enviado acima para visualizar os dados completos.\n\n⚙️ Dono: @silenciante";

    $kb = [
        "inline_keyboard" => [
            [
                ["text" => "🗑 Apagar", "callback_data" => "nome_full_del"],
                ["text" => "💸 Nota Falsa", "url" => "https://t.me/notafalsa_bot"]
            ]
        ]
    ];

    // Enviar TXT
    $url = $apiURL."sendDocument";
    $post_fields = [
        'chat_id' => $chat_id,
        'document' => new CURLFile(realpath($nomeArquivo)),
        'caption' => $textoSucesso,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode($kb)
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type:multipart/form-data"]);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_exec($ch);
    curl_close($ch);

    unlink($nomeArquivo); // deleta arquivo temporário

    exit;
}

// COMANDOS GLOBAIS (ANTES DO FORMULÁRIO)
if (strpos(trim(strtolower($message)), "/info") === 0) {

    $texto =
        "🔒 *DETALHES TÉCNICOS DAS NOTAS:*\n\n".
        "✅ Fita preta real (original)\n".
        "✅ Marca d'água legítima\n".
        "✅ Holográfico\n".
        "✅ Papel texturizado de alta gramatura\n".
        "✅ Tamanho exato das cédulas verdadeiras\n".
        "✅ Reage à luz UV\n".
        "✅ Fibras UV embutidas\n".
        "✅ Passa em teste com caneta detectora\n\n".
        "🫡 Referência: @artigo_171";

    sendMessage($chat_id, $texto);
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
    "🔹 PIX: `4216aab3-7990-4af1-a5fd-73ce85e57deb`\n\n" .
    "📤 Após o pagamento, envie o comprovante para *@silenciante*.\n\n".
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
        "🔹 PIX: `4216aab3-7990-4af1-a5fd-73ce85e57deb`\n\n" .
        "📤 *Após o pagamento, envie o comprovante para*: @silenciante\n\n" .
        "📦 *Código de rastreio do pedido:* `$codigoRastreio`\n" .
        "Use o comando /status seguido do código para acompanhar seu pedido.";

    editMessage($chat_id, $message_id, $resumo);

    unset($usuarios[$chat_id]);
    file_put_contents($usuariosFile, json_encode($usuarios));
}
?>