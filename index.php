<?php
// CONFIGURAÇÕES DO BOT
$token = "8362847658:AAHoF5LFmYDZdWPm9Umde9M5dqluhnpUl-g";
$apiURL = "https://api.telegram.org/bot$token/";
$cep_origem = "30140071"; // Belo Horizonte, MG

// PEGAR MENSAGENS
$update = json_decode(file_get_contents("php://input"), true);
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

// COMANDO /start
if ($message == "/start") {
    sendMessage($chat_id, "🎭 *Bem-vindo ao Joker NF!*\n\nDigite */comprar* para iniciar o formulário.\nPara mais detalhes sobre as notas, use */info*.");
    exit;
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
        "🫡 Referência: @Jokermetodosfree"
    );
    exit;
}

// GERAR CUPOM (SOMENTE VOCÊ)
if (strpos($message, "/gerarcupon") === 0) {
    if ($chat_id != "7926471341") {
        sendMessage($chat_id, "❌ Você não tem permissão para gerar cupons.");
        exit;
    }
    $parts = explode(" ", $message, 4);
    if (!isset($parts[1]) || empty($parts[1]) || !isset($parts[2]) || !isset($parts[3])) {
        sendMessage($chat_id, "❌ Use o formato:\n/gerarcupon MEUCUPOM 25 2025-09-20\n\n(O último parâmetro é a data de validade AAAA-MM-DD)");
        exit;
    }

    $nomeCupom = strtoupper(trim($parts[1]));
    $desconto = (int)$parts[2];
    $validade = $parts[3]; // formato AAAA-MM-DD

    // Valida data
    $dataValida = DateTime::createFromFormat("Y-m-d", $validade);
    if (!$dataValida || $dataValida->format("Y-m-d") !== $validade) {
        sendMessage($chat_id, "❌ Data inválida! Use o formato AAAA-MM-DD.");
        exit;
    }

    if ($desconto < 1 || $desconto > 100) {
        sendMessage($chat_id, "❌ Informe uma porcentagem entre 1 e 100.");
        exit;
    }

    $cupons[$nomeCupom] = [
        "usado" => false,
        "desconto" => $desconto,
        "validade" => $validade
    ];
    file_put_contents($cuponsFile, json_encode($cupons));
    sendMessage($chat_id, "✅ Cupom `$nomeCupom` gerado com *$desconto% de desconto*!\n📅 Válido até: $validade");
    exit;
}

// RESGATAR CUPOM PELO USUÁRIO
if (strpos($message, "/resgatar") === 0) {
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

    // Verifica validade
    $validade = $cupons[$cupomDigitado]["validade"] ?? null;
    if ($validade && strtotime($validade) < strtotime(date("Y-m-d"))) {
        sendMessage($chat_id, "⏰ Este cupom expirou em $validade!");
        exit;
    }

    if ($cupons[$cupomDigitado]["usado"]) {
        sendMessage($chat_id, "❌ Este cupom já foi usado!");
        exit;
    }

    $usuarios[$chat_id]["cupom"] = $cupomDigitado;
    $usuarios[$chat_id]["etapa"] = "nome"; // começa o formulário
    file_put_contents($usuariosFile, json_encode($usuarios));

    $desconto = $cupons[$cupomDigitado]["desconto"] ?? 30;
    sendMessage(
        $chat_id,
        "✅ Cupom aplicado com sucesso! Você receberá *{$desconto}% de desconto* no total.\n\nDigite seu *NOME COMPLETO* para iniciar o formulário:"
    );
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

// ARQUIVO PARA SALVAR STATUS DOS PEDIDOS
$statusFile = "status.json";
if (!file_exists($statusFile)) file_put_contents($statusFile, "{}");
$statuses = json_decode(file_get_contents($statusFile), true);

// COMANDO ADMIN PARA DEFINIR STATUS
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

// TRATAMENTO DOS BOTÕES INLINE PARA STATUS (ADMIN)
if (strpos($callback_query, "status_") === 0) {
    list(, $codigo, $novoStatus) = explode("_", $callback_query, 3);
    $statuses[$codigo] = $novoStatus;
    file_put_contents($statusFile, json_encode($statuses));
    editMessage($chat_id, $message_id, "✅ • Status do pedido `$codigo` definido como: *$novoStatus*");
    exit;
}

// COMANDO /status PARA USUÁRIO
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
            [["text" => "💵 10K — R$1500", "callback_data" => "qtd_10k"]]
        ]
    ];
    sendMessage($chat_id, "📦 Escolha a *QUANTIDADE* de cédulas:", $keyboard);
    exit;
}

// TRATAMENTO DA QUANTIDADE
if (strpos($callback_query, "qtd_") === 0) {
    $usuarios[$chat_id]["quantidade"] = strtoupper(str_replace("qtd_", "", $callback_query));
    $usuarios[$chat_id]["etapa"] = "resumo";
    file_put_contents($usuariosFile, json_encode($usuarios));

    // RESUMO
    $nome = $usuarios[$chat_id]["nome"];
    $rua = $usuarios[$chat_id]["rua"];
    $numero = $usuarios[$chat_id]["numero"];
    $cep = $usuarios[$chat_id]["cep"];
    $cidade = $usuarios[$chat_id]["cidade"];
    $estado = $usuarios[$chat_id]["estado"];
    $bairro = $usuarios[$chat_id]["bairro"];
    $cedulas = $usuarios[$chat_id]["cedulas"];
    $quantidade = $usuarios[$chat_id]["quantidade"];

    $frete = calcularFrete($cep);
    $precoBase = match($quantidade) {
        "1K" => 170,
        "2K" => 310,
        "3K" => 450,
        "4K" => 580,
        "5K" => 740,
        "10K" => 1500,
        default => 0
    };

    $cupom = $usuarios[$chat_id]["cupom"] ?? null;
    $desconto = 0;
    if ($cupom && isset($cupons[$cupom])) {
        $validade = $cupons[$cupom]["validade"] ?? null;
        if (!$validade || strtotime($validade) >= strtotime(date("Y-m-d"))) {
            $desconto = ($precoBase * $cupons[$cupom]["desconto"]) / 100;
        }
    }

    $total = $precoBase + $frete - $desconto;

    $resumo = "📋 *RESUMO DO PEDIDO:*\n\n";
    $resumo .= "👤 Nome: $nome\n";
    $resumo .= "🏠 Endereço: $rua, $numero\n";
    $resumo .= "📍 Bairro: $bairro\n";
    $resumo .= "🌆 Cidade: $cidade - $estado\n";
    $resumo .= "📮 CEP: $cep\n\n";
    $resumo .= "💵 Cédulas: R$ $cedulas\n";
    $resumo .= "📦 Quantidade: $quantidade\n\n";
    $resumo .= "🚚 Frete: R$ " . number_format($frete, 2, ',', '.') . "\n";
    $resumo .= "💰 Subtotal: R$ " . number_format($precoBase, 2, ',', '.') . "\n";
    if ($desconto > 0) {
        $resumo .= "🎁 Desconto: -R$ " . number_format($desconto, 2, ',', '.') . " (Cupom $cupom)\n";
    }
    $resumo .= "💳 *Total a pagar: R$ " . number_format($total, 2, ',', '.') . "*\n";

    sendMessage($chat_id, $resumo);
    exit;
}
?>