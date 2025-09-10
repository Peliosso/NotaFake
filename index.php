<?php
// CONFIGURAÇÕES DO BOT
$token = "8362847658:AAHoF5LFmYDZdWPm9Umde9M5dqluhnpUl-g";
$apiURL = "https://api.telegram.org/bot$token/";

// Chave Mercado Pago
$mp_access_token = "APP_USR-5980007914059821-091004-76b3148bb6f755868cdc791a58c0c292-2678667901";

// PEGAR MENSAGENS
$update = json_decode(file_get_contents("php://input"), true);
$chat_id = $update["message"]["chat"]["id"] ?? $update["callback_query"]["message"]["chat"]["id"];
$message = $update["message"]["text"] ?? null;
$callback_query = $update["callback_query"]["data"] ?? null;
$message_id = $update["callback_query"]["message"]["message_id"] ?? null;

// ARQUIVO PARA SALVAR OS DADOS
$usuariosFile = "usuarios.json";
if (!file_exists($usuariosFile)) file_put_contents($usuariosFile,"{}");
$usuarios = json_decode(file_get_contents($usuariosFile), true);

// FUNÇÕES BÁSICAS
function sendMessage($chat_id, $text, $reply_markup = null) {
    global $apiURL;
    $data = ["chat_id"=>$chat_id,"text"=>$text,"parse_mode"=>"Markdown"];
    if($reply_markup) $data["reply_markup"] = json_encode($reply_markup);
    $res = file_get_contents($apiURL."sendMessage?".http_build_query($data));
    return json_decode($res,true)["result"]["message_id"] ?? null;
}

function editMessage($chat_id, $message_id, $text, $reply_markup = null) {
    global $apiURL;
    $data = ["chat_id"=>$chat_id,"message_id"=>$message_id,"text"=>$text,"parse_mode"=>"Markdown"];
    if($reply_markup) $data["reply_markup"] = json_encode($reply_markup);
    file_get_contents($apiURL."editMessageText?".http_build_query($data));
}

// CALCULO FRETE SIMPLES
function calcularFrete($cep_destino){ return rand(30,50); }

// GERAR PIX MERCADO PAGO
function gerarPixMP($valor, $descricao, $email_cliente, $access_token){
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.mercadopago.com/v1/payments",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            "transaction_amount" => floatval($valor),
            "description" => $descricao,
            "payment_method_id" => "pix",
            "payer" => ["email" => $email_cliente]
        ]),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $access_token",
            "Content-Type: application/json"
        ],
    ]);

    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res,true);

    if(!isset($data['point_of_interaction']['transaction_data'])){
        return ["erro"=>true,"mensagem"=>"Erro ao gerar PIX"];
    }

    $qr_code = $data['point_of_interaction']['transaction_data']['qr_code'] ?? null;
    $copia_cola = $data['point_of_interaction']['transaction_data']['qr_code_base64'] ?? null;
    $pix_id = $data['id'] ?? null;

    return [
        "erro" => false,
        "qr_code" => $qr_code,
        "copia_cola" => $copia_cola,
        "pix_id" => $pix_id
    ];
}

// VERIFICAR PAGAMENTO
function verificarPagamento($pix_id, $access_token){
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.mercadopago.com/v1/payments/$pix_id",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $access_token"
        ]
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res,true);
    return isset($data["status"]) && $data["status"]=="approved";
}

// COMANDOS
if($message=="/start"){
    sendMessage($chat_id,"🎭 *Bem-vindo ao Joker NF!*\nDigite /comprar para iniciar o formulário.\nPara detalhes sobre as notas, use /info.");
    exit;
}

if($message=="/info"){
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

if($message=="/status"){
    if(isset($usuarios[$chat_id]) && isset($usuarios[$chat_id]["pago"]) && $usuarios[$chat_id]["pago"]===true){
        sendMessage($chat_id,"📦 Preparando seu pedido...");
    }
    exit;
}

if($message=="/comprar"){
    $usuarios[$chat_id] = ["etapa"=>"nome"];
    file_put_contents($usuariosFile,json_encode($usuarios));
    sendMessage($chat_id,"📝 Vamos começar o formulário.\nDigite seu *NOME COMPLETO*:");
    exit;
}

// FORMULÁRIO
if(isset($usuarios[$chat_id]) && $message && !$callback_query){
    $etapa = $usuarios[$chat_id]["etapa"];
    switch($etapa){
        case "nome": $usuarios[$chat_id]["nome"]=$message; $usuarios[$chat_id]["etapa"]="rua"; sendMessage($chat_id,"🏠 Informe sua *RUA*:"); break;
        case "rua": $usuarios[$chat_id]["rua"]=$message; $usuarios[$chat_id]["etapa"]="numero"; sendMessage($chat_id,"🔢 Informe o *NÚMERO* da residência:"); break;
        case "numero":
            if(!is_numeric($message)){ sendMessage($chat_id,"❌ Número inválido!"); exit; }
            $usuarios[$chat_id]["numero"]=$message; $usuarios[$chat_id]["etapa"]="cep"; sendMessage($chat_id,"📮 Informe seu *CEP* (apenas números):"); break;
        case "cep":
            if(!is_numeric($message) || strlen($message)!=8){ sendMessage($chat_id,"❌ CEP inválido!"); exit; }
            $usuarios[$chat_id]["cep"]=$message; $usuarios[$chat_id]["etapa"]="cidade"; sendMessage($chat_id,"🌆 Informe sua *CIDADE*:"); break;
        case "cidade": $usuarios[$chat_id]["cidade"]=$message; $usuarios[$chat_id]["etapa"]="estado"; sendMessage($chat_id,"🏙 Informe seu *ESTADO*:"); break;
        case "estado": $usuarios[$chat_id]["estado"]=$message; $usuarios[$chat_id]["etapa"]="bairro"; sendMessage($chat_id,"📍 Informe seu *BAIRRO*:"); break;
        case "bairro":
            $usuarios[$chat_id]["bairro"]=$message;
            $usuarios[$chat_id]["etapa"]="cedulas";
            $keyboard = ["inline_keyboard"=>[
                [["text"=>"💵 100 🐟","callback_data"=>"cedula_100"]],
                [["text"=>"💵 50 🐯","callback_data"=>"cedula_50"]],
                [["text"=>"💵 20 🐒","callback_data"=>"cedula_20"]],
                [["text"=>"💵 200 🐺","callback_data"=>"cedula_200"]]
            ]];
            sendMessage($chat_id,"💸 Escolha o valor das *CÉDULAS*:",$keyboard);
            break;
    }
    file_put_contents($usuariosFile,json_encode($usuarios));
}

// TRATAMENTO CÉDULAS E PIX
if(strpos($callback_query,"cedula_")===0){
    $usuarios[$chat_id]["cedulas"]=str_replace("cedula_","",$callback_query);
    $usuarios[$chat_id]["etapa"]="final";
    file_put_contents($usuariosFile,json_encode($usuarios));

    $dados = $usuarios[$chat_id];
    $frete = calcularFrete($dados["cep"]);

    $valores_cedulas = ["100"=>170,"50"=>310,"20"=>450,"200"=>580];
    $valor_pedido = $valores_cedulas[$dados["cedulas"]] ?? 500;
    $total = $valor_pedido + $frete;

    $resumo="✅ *Formulário preenchido!*\n\n".
        "👤 Nome: {$dados['nome']}\n".
        "🏠 Rua: {$dados['rua']}, Nº {$dados['numero']}\n".
        "📮 CEP: {$dados['cep']}\n".
        "🌆 Cidade: {$dados['cidade']} - {$dados['estado']}\n".
        "📍 Bairro: {$dados['bairro']}\n".
        "💵 Cédulas: {$dados['cedulas']}\n".
        "🚛 Frete: R$".number_format($frete,2,',','.') ."\n\n".
        "📌 Clique no botão abaixo para gerar o PIX:";

    $keyboard=["inline_keyboard"=>[
        [["text"=>"💳 Gerar PIX R$".number_format($total,2,',','.'),"callback_data"=>"gerar_pix_$total"]]
    ]];

    editMessage($chat_id,$message_id,$resumo,$keyboard);
}

// GERAR PIX
if(strpos($callback_query,"gerar_pix_")===0){
    $total = floatval(str_replace("gerar_pix_","",$callback_query));
    $dados = $usuarios[$chat_id];
    $pix = gerarPixMP($total,"Pedido Joker NF","cliente@email.com",$mp_access_token);

    if($pix["erro"]){
        sendMessage($chat_id,"❌ Erro ao gerar PIX: ".$pix["mensagem"]);
        exit;
    }

    $usuarios[$chat_id]["pix_id"]=$pix["pix_id"];
    $usuarios[$chat_id]["pago"]=false;
    file_put_contents($usuariosFile,json_encode($usuarios));

    $resumo_pix = "💳 *PIX GERADO!*\n\n".
        "💰 Total: R$".number_format($total,2,',','.') ."\n".
        "🔹 QR Code: ".$pix["qr_code"]."\n".
        "🔹 Copia e Cola: ".$pix["copia_cola"]."\n".
        "🔹 ID da Transação: ".$pix["pix_id"]."\n\n".
        "📤 Envie o comprovante para @RibeiroDo171";

    editMessage($chat_id,$message_id,$resumo_pix);
}

// VERIFICAR PAGAMENTO PERIÓDICO
foreach($usuarios as $chat=>$info){
    if(isset($info["pix_id"]) && $info["pago"]===false){
        if(verificarPagamento($info["pix_id"],$mp_access_token)){
            $usuarios[$chat]["pago"]=true;
            file_put_contents($usuariosFile,json_encode($usuarios));
            sendMessage($chat,"✅ *Pagamento recebido!* Seu pedido está sendo preparado.");
        }
    }
}
?>
