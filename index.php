<?php
// CONFIGURAÇÕES DO BOT
$token = "SEU_TOKEN_AQUI";
$apiURL = "https://api.telegram.org/bot$token/";

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

// INICIO DO BOT
if ($message == "/start") {
    $keyboard = [
        "inline_keyboard" => [
            [["text" => "📖 Como Usar", "callback_data" => "como_usar"]]
        ]
    ];
    sendMessage($chat_id, "🎭 *Olá, seja Bem-vindo ao Joker NF!*\n\nClique no botão abaixo para aprender a me usar.", $keyboard);
    exit;
}

// BOTÃO COMO USAR
if ($callback_query == "como_usar") {
    $message_id = $update["callback_query"]["message"]["message_id"];
    $keyboard = [
        "inline_keyboard" => [
            [["text" => "⬅ Voltar", "callback_data" => "voltar_start"]]
        ]
    ];
    editMessage($chat_id, $message_id,
        "📌 *Como usar o bot:*\n\n".
        "1️⃣ Use o comando /comprar\n".
        "2️⃣ Faça o pagamento e preencha o formulário\n".
        "3️⃣ Encaminhe o resumo final para @RibeiroDo171",
        $keyboard
    );
    exit;
}

// VOLTAR AO INÍCIO
if ($callback_query == "voltar_start") {
    $message_id = $update["callback_query"]["message"]["message_id"];
    $keyboard = [
        "inline_keyboard" => [
            [["text" => "📖 Como Usar", "callback_data" => "como_usar"]]
        ]
    ];
    editMessage($chat_id, $message_id, "🎭 *Olá, seja Bem-vindo ao Joker NF!*\n\nClique no botão abaixo para aprender a me usar.", $keyboard);
    exit;
}

// COMANDO /comprar
if ($message == "/comprar") {
    $keyboard = [
        "inline_keyboard" => [
            [["text" => "✅ Já Paguei", "callback_data" => "ja_paguei"]],
            [["text" => "❌ Não Paguei", "callback_data" => "nao_paguei"]]
        ]
    ];
    sendMessage($chat_id,
        "💸 *Dados para pagamento via PIX:*\n\n".
        "🔹 *Chave PIX:* `701.928.226-16`\n".
        "⚠ Após o pagamento, clique em *Já Paguei*.",
        $keyboard
    );
    exit;
}

// BOTÃO NÃO PAGUEI
if ($callback_query == "nao_paguei") {
    $message_id = $update["callback_query"]["message"]["message_id"];
    editMessage($chat_id, $message_id, "⚠️ Para prosseguir, é necessário realizar o pagamento via PIX.");
    exit;
}

// BOTÃO JÁ PAGUEI — INICIA FORMULÁRIO
if ($callback_query == "ja_paguei") {
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
            sendMessage($chat_id, "💵 Informe o valor das *CÉDULAS*:");
            break;

        case "cedulas":
            $usuarios[$chat_id]["cedulas"] = $message;
            $usuarios[$chat_id]["etapa"] = "quantidade";
            sendMessage($chat_id, "🔢 Informe a *QUANTIDADE*:");
            break;

        case "quantidade":
            $usuarios[$chat_id]["quantidade"] = $message;
            $usuarios[$chat_id]["etapa"] = "final";

            $dados = $usuarios[$chat_id];
            $resumo =
                "✅ *Formulário preenchido com sucesso!*\n\n" .
                "👤 Nome: {$dados['nome']}\n" .
                "🏠 Rua: {$dados['rua']}, Nº {$dados['numero']}\n" .
                "📮 CEP: {$dados['cep']}\n" .
                "🌆 Cidade: {$dados['cidade']} - {$dados['estado']}\n" .
                "📍 Bairro: {$dados['bairro']}\n" .
                "💵 Cédulas: {$dados['cedulas']}\n" .
                "🔢 Quantidade: {$dados['quantidade']}\n\n" .
                "📤 Encaminhe esta mensagem para: @RibeiroDo171 junto com o comprovante de pagamento.";
            sendMessage($chat_id, $resumo);

            unset($usuarios[$chat_id]); // Limpa os dados após finalizar
            break;
    }

    file_put_contents($usuariosFile, json_encode($usuarios));
}
?>
