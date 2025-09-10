<?php
// CONFIGURAÇÕES DO BOT
$token = "8362847658:AAHoF5LFmYDZdWPm9Umde9M5dqluhnpUl-g";
$apiURL = "https://api.telegram.org/bot$token/";

// CEP de origem — Belo Horizonte, MG
$cep_origem = "30140071";

// PEGAR MENSAGENS
$update = json_decode(file_get_contents("php://input"), true);

$chat_id = $update["message"]["chat"]["id"] ?? $update["callback_query"]["message"]["chat"]["id"];
$message = $update["message"]["text"] ?? null;
$callback_query = $update["callback_query"]["data"] ?? null;

// ARQUIVO PARA SALVAR OS DADOS
$usuariosFile = "usuarios.json";
if (!file_exists($usuariosFile)) {
    file_put_contents($usuariosFile, "{}");
}
$usuarios = json_decode(file_get_contents($usuariosFile), true);

// FUNÇÃO PARA ENVIAR MENSAGENS
function sendMessage($chat_id, $text, $reply_markup = null) {
    global $apiURL;
    $data = [
        "chat_id" => $chat_id,
        "text" => $text,
        "parse_mode" => "Markdown"
    ];
    if ($reply_markup) {
        $data["reply_markup"] = json_encode($reply_markup);
    }
    file_get_contents($apiURL . "sendMessage?" . http_build_query($data));
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
    if ($reply_markup) {
        $data["reply_markup"] = json_encode($reply_markup);
    }
    file_get_contents($apiURL . "editMessageText?" . http_build_query($data));
}

// FUNÇÃO PARA CALCULAR FRETE PELOS CORREIOS
function calcularFrete($cep_destino, $peso = 1) {
    global $cep_origem;

    $url = "https://www2.correios.com.br/sistemas/precosPrazos/PrecoPrazo.asmx/CalcPrecoPrazo?"
         . http_build_query([
            "nCdEmpresa" => "",
            "sDsSenha" => "",
            "nCdServico" => "04510", // PAC
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
    if ($response === false) {
        return rand(30, 50); // fallback aleatório
    }

    if (preg_match('/<Valor>(.*?)<\/Valor>/', $response, $matches)) {
        $valor = str_replace(",", ".", $matches[1]);
        return (float)$valor > 0 ? (float)$valor : rand(30, 50);
    }

    return rand(30, 50);
}

// INICIO DO BOT
if ($message == "/start") {
    sendMessage($chat_id, "🎭 *Bem-vindo ao Joker NF!*\n\nDigite */comprar* para iniciar o formulário e calcular o frete automaticamente.\n\nPara mais informações, use */info*.");
    exit;
}

// NOVO COMANDO /info
if ($message == "/info") {
    $info = "🔒 *DETALHES TÉCNICOS DAS NOTAS:*\n\n" .
        "✅ Fita preta real (original)\n" .
        "✅ Marca d’água legítima\n" .
        "✅ Holográfico\n" .
        "✅ Papel texturizado de alta gramatura\n" .
        "✅ Tamanho exato das cédulas verdadeiras\n" .
        "✅ Reage à luz UV (negativo e positivo)\n" .
        "✅ Fibras UV embutidas na cédula\n" .
        "✅ Passa em teste com caneta detectora\n\n" .
        "🫡 Referência: @Jokermetodosfree";
    sendMessage($chat_id, $info);
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
if (isset($usuarios[$chat_id])) {
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

            // Botões inline para escolha das cédulas
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

// TRATAMENTO DA ESCOLHA DAS CÉDULAS
if (strpos($callback_query, "cedula_") === 0) {
    $usuarios[$chat_id]["cedulas"] = strtoupper(str_replace("cedula_", "", $callback_query));
    $usuarios[$chat_id]["etapa"] = "quantidade";
    file_put_contents($usuariosFile, json_encode($usuarios));

    // Botões para selecionar a quantidade
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
    sendMessage($chat_id, "🔢 Escolha a *quantidade* desejada:", $keyboard);
}

// TRATAMENTO DA ESCOLHA DA QUANTIDADE
if (strpos($callback_query, "qtd_") === 0) {
    $message_id = $update["callback_query"]["message"]["message_id"];
    $quantidade = str_replace("qtd_", "", $callback_query);

    $precos = [
        "1k" => 170,
        "2k" => 310,
        "3k" => 450,
        "4k" => 580,
        "5k" => 740,
        "10k" => 1320,
        "25k" => 2270,
        "50k" => 0
    ];

    $usuarios[$chat_id]["quantidade"] = strtoupper($quantidade);
    $preco = $precos[$quantidade] ?? 0;

    // Calcula frete com base no CEP do usuário
    $cep_destino = $usuarios[$chat_id]["cep"];
    $frete = ($quantidade === "50k") ? 0 : calcularFrete($cep_destino);

    // Animação interativa
    editMessage($chat_id, $message_id, "🔄 Calculando *quantidade*...");
    sleep(1);
    editMessage($chat_id, $message_id, "📦 Preparando *envio*...");
    sleep(1);
    editMessage($chat_id, $message_id, "🚛 Calculando *frete*...");
    sleep(1);
    editMessage($chat_id, $message_id, "✅ Finalizando seu pedido...");
    sleep(1);

    // Resumo final
    $dados = $usuarios[$chat_id];
    $total = $preco + $frete;

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
        "💳 *Total a Pagar*: R$" . number_format($total, 2, ',', '.') . "\n\n" .
        "📌 *Forma de pagamento:*\n".
        "🔹 PIX: `1aebb1bd-10b7-435e-bd17-03adf4451088`\n\n" .
        "📤 *Após o pagamento, envie o comprovante para*: @RibeiroDo171";

    sendMessage($chat_id, $resumo);

    unset($usuarios[$chat_id]);
    file_put_contents($usuariosFile, json_encode($usuarios));
    exit;
}
?>
