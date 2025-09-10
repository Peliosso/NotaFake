<?php
$token = "8362847658:AAHoF5LFmYDZdWPm9Umde9M5dqluhnpUl-g";
$apiURL = "https://api.telegram.org/bot$token/";

$update = json_decode(file_get_contents("php://input"), true);
$chat_id = $update["message"]["chat"]["id"] ?? $update["callback_query"]["message"]["chat"]["id"];
$message = $update["message"]["text"] ?? null;
$callback_query = $update["callback_query"]["data"] ?? null;
$message_id = $update["callback_query"]["message"]["message_id"] ?? null;

$usuariosFile = "usuarios.json";
if(!file_exists($usuariosFile)) file_put_contents($usuariosFile,"{}");
$usuarios = json_decode(file_get_contents($usuariosFile),true);

$precos = [
    "1000" => 170,
    "2000" => 310,
    "3000" => 450,
    "4000" => 580,
    "5000" => 740,
    "10000" => 1320,
    "25000" => 2270,
    "50000" => "A combinar"
];

$chave_pix = "1aebb1bd-10b7-435e-bd17-03adf4451088";

function sendMessage($chat_id,$text,$reply_markup=null){
    global $apiURL;
    $data = ["chat_id"=>$chat_id,"text"=>$text,"parse_mode"=>"Markdown"];
    if($reply_markup) $data["reply_markup"]=json_encode($reply_markup);
    $response = file_get_contents($apiURL."sendMessage?".http_build_query($data));
    return json_decode($response,true);
}

function editMessage($chat_id,$message_id,$text,$reply_markup=null){
    global $apiURL;
    $data = ["chat_id"=>$chat_id,"message_id"=>$message_id,"text"=>$text,"parse_mode"=>"Markdown"];
    if($reply_markup) $data["reply_markup"]=json_encode($reply_markup);
    file_get_contents($apiURL."editMessageText?".http_build_query($data));
}

// /start
if($message=="/start"){
    $keyboard=[
        "inline_keyboard"=>[
            [["text"=>"📖 Como Usar","callback_data"=>"como_usar"]]
        ]
    ];
    sendMessage($chat_id,"🎭 *Olá, seja Bem-vindo ao Joker NF!*\nClique no botão abaixo para aprender a me usar.",$keyboard);
    exit;
}

// COMO USAR
if($callback_query=="como_usar"){
    $texto="📌 *Como usar o bot:*\n\n".
           "1️⃣ Use o comando /comprar para iniciar o formulário.\n".
           "2️⃣ Preencha corretamente todas as informações.\n".
           "3️⃣ Após preencher, será exibida a simulação de frete e o valor total.\n".
           "4️⃣ Realize o pagamento via PIX e clique em 'Já Paguei'.\n".
           "5️⃣ Encaminhe a mensagem final para @RibeiroDo171.";
    $keyboard=[["inline_keyboard"=>[[["text"=>"⬅ Voltar","callback_data"=>"voltar_start"]]]]];
    editMessage($chat_id,$message_id,$texto,$keyboard);
    exit;
}

// VOLTAR
if($callback_query=="voltar_start"){
    $keyboard=[
        "inline_keyboard"=>[
            [["text"=>"📖 Como Usar","callback_data"=>"como_usar"]]
        ]
    ];
    editMessage($chat_id,$message_id,"🎭 *Olá, seja Bem-vindo ao Joker NF!*\nClique no botão abaixo para aprender a me usar.",$keyboard);
    exit;
}

// /comprar
if($message=="/comprar"){
    $usuarios[$chat_id]=["etapa"=>"nome"];
    file_put_contents($usuariosFile,json_encode($usuarios));
    sendMessage($chat_id,"📝 Vamos começar o formulário.\nDigite seu *NOME COMPLETO*:");
    exit;
}

// FORMULÁRIO
if(isset($usuarios[$chat_id])){
    $etapa=$usuarios[$chat_id]["etapa"];
    switch($etapa){
        case "nome":
            $usuarios[$chat_id]["nome"]=$message;
            $usuarios[$chat_id]["etapa"]="rua";
            sendMessage($chat_id,"🏠 Informe sua *RUA*:");
            break;

        case "rua":
            $usuarios[$chat_id]["rua"]=$message;
            $usuarios[$chat_id]["etapa"]="numero";
            sendMessage($chat_id,"🔢 Informe o *NÚMERO* da residência:");
            break;

        case "numero":
            if(!is_numeric($message)){sendMessage($chat_id,"❌ Número inválido! Digite apenas números:"); exit;}
            $usuarios[$chat_id]["numero"]=$message;
            $usuarios[$chat_id]["etapa"]="cep";
            sendMessage($chat_id,"📮 Informe seu *CEP* (apenas números):");
            break;

        case "cep":
            if(!is_numeric($message) || strlen($message)!=8){sendMessage($chat_id,"❌ CEP inválido! Digite um CEP válido:"); exit;}
            $usuarios[$chat_id]["cep"]=$message;
            $usuarios[$chat_id]["etapa"]="cidade";
            sendMessage($chat_id,"🌆 Informe sua *CIDADE*:");
            break;

        case "cidade":
            $usuarios[$chat_id]["cidade"]=$message;
            $usuarios[$chat_id]["etapa"]="estado";
            sendMessage($chat_id,"🏙 Informe seu *ESTADO*:");
            break;

        case "estado":
            $usuarios[$chat_id]["estado"]=$message;
            $usuarios[$chat_id]["etapa"]="bairro";
            sendMessage($chat_id,"📍 Informe seu *BAIRRO*:");
            break;

        case "bairro":
            $usuarios[$chat_id]["bairro"]=$message;
            $usuarios[$chat_id]["etapa"]="cedulas";
            sendMessage($chat_id,"💵 Informe o valor das *CÉDULAS*:");
            break;

        case "cedulas":
            $usuarios[$chat_id]["cedulas"]=$message;
            $usuarios[$chat_id]["etapa"]="quantidade";

            // Cria botões com quantidades
            $keyboard = ["inline_keyboard"=>[]];
            $linha = [];
            foreach(array_keys($precos) as $q){
                $linha[]=["text"=>$q,"callback_data"=>"quant_".$q];
                if(count($linha)==3){
                    $keyboard["inline_keyboard"][]=$linha;
                    $linha=[];
                }
            }
            if(count($linha)>0) $keyboard["inline_keyboard"][]=$linha;

            sendMessage($chat_id,"🔢 Escolha a *QUANTIDADE* desejada clicando em um botão:", $keyboard);
            break;
    }
    file_put_contents($usuariosFile,json_encode($usuarios));
    exit;
}

// Captura seleção de quantidade
if(str_starts_with($callback_query,"quant_")){
    $quantidade = str_replace("quant_","",$callback_query);
    if(!isset($usuarios[$chat_id])) exit;
    $usuarios[$chat_id]["quantidade"]=$quantidade;
    file_put_contents($usuariosFile,json_encode($usuarios));

    $frete = 42;
    $total_texto = is_numeric($precos[$quantidade]) ? "R$".($precos[$quantidade]+$frete)." (incluindo frete R$$frete)" : "A combinar + frete R$$frete";

    // Mensagem "Calculando frete..."
    $calc = sendMessage($chat_id,"⏳ Calculando frete...");
    $calc_id = $calc['result']['message_id'] ?? null;
    sleep(2);

    $dados = $usuarios[$chat_id];
    $resumo = "📝 *Formulário completo*\n\n".
              "👤 Nome: {$dados['nome']}\n".
              "🏠 Rua: {$dados['rua']}, Nº {$dados['numero']}\n".
              "📮 CEP: {$dados['cep']}\n".
              "🌆 Cidade: {$dados['cidade']} - {$dados['estado']}\n".
              "📍 Bairro: {$dados['bairro']}\n".
              "💵 Cédulas: {$dados['cedulas']}\n".
              "🔢 Quantidade: {$dados['quantidade']}\n".
              "🚚 Frete: R$$frete\n".
              "💰 Total: $total_texto\n\n".
              "💸 *Chave PIX:* $chave_pix";

    $keyboard=[
        "inline_keyboard"=>[
            [["text"=>"✅ Já Paguei","callback_data"=>"ja_paguei"]],
            [["text"=>"❌ Não Paguei","callback_data"=>"nao_paguei"]]
        ]
    ];

    editMessage($chat_id,$calc_id,$resumo,$keyboard);
    unset($usuarios[$chat_id]);
    exit;
}

// NÃO PAGUEI
if($callback_query=="nao_paguei"){
    editMessage($chat_id,$message_id,"⚠️ Para prosseguir, é necessário realizar o pagamento via PIX.");
    exit;
}

// JÁ PAGUEI
if($callback_query=="ja_paguei"){
    sendMessage($chat_id,"✅ Pagamento confirmado!\n📨 Encaminhe o formulário acima para @RibeiroDo171 e envie o comprovante de pagamento.");
    exit;
}
?>
