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
    file_get_contents($apiURL."sendMessage?".http_build_query($data));
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
           "5️⃣ Encaminhe a mensagem final para @RibeiroDo171.\n\n💡 Clique nos botões abaixo para mais detalhes:";
    $keyboard=[
        "inline_keyboard"=>[
            [["text"=>"⬅ Voltar","callback_data"=>"voltar_start"]],
            [["text"=>"💰 Preços","callback_data"=>"precos"]],
            [["text"=>"🔒 Informações das Notas","callback_data"=>"info_notas"]]
        ]
    ];
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

// PREÇOS
if($callback_query=="precos"){
    $texto="💰 *TABELA ATUALIZADA — Setembro 2025*\n\n".
           "• 💵 1K — R\$170\n".
           "• 💵 2K — R\$310\n".
           "• 💵 3K — R\$450\n".
           "• 💵 4K — R\$580\n".
           "• 💵 5K — R\$740\n".
           "• 💵 10K — R\$1.320\n".
           "• 💼 25K — R\$2.270\n".
           "• 💼 50K+ — A combinar diretamente\n\n".
           "📦 *Cédulas disponíveis:*\n100 🐟 | 50 🐯 | 20 🐒 | 200 🐺";
    $keyboard=[["inline_keyboard"=>[[["text"=>"⬅ Voltar","callback_data"=>"como_usar"]]]]];
    editMessage($chat_id,$message_id,$texto,$keyboard);
    exit;
}

// INFORMAÇÕES DAS NOTAS
if($callback_query=="info_notas"){
    $texto="🔒 *DETALHES TÉCNICOS DAS NOTAS:*\n\n".
           "✅ Fita preta real (original)\n".
           "✅ Marca d’água legítima\n".
           "✅ Holográfico\n".
           "✅ Papel texturizado de alta gramatura\n".
           "✅ Tamanho exato das cédulas verdadeiras\n".
           "✅ Reage à luz UV (negativo e positivo)\n".
           "✅ Fibras UV embutidas na cédula\n".
           "✅ Passa em teste com caneta detectora";
    $keyboard=[["inline_keyboard"=>[[["text"=>"⬅ Voltar","callback_data"=>"como_usar"]]]]];
    editMessage($chat_id,$message_id,$texto,$keyboard);
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
            sendMessage($chat_id,"🔢 Informe a *QUANTIDADE* (digite exatamente 1000, 2000, 3000, 4000, 5000, 10000, 25000 ou 50000):");
            break;

        case "quantidade":
            $usuarios[$chat_id]["quantidade"] = $message;
            $quantidade = $message;
            $frete = 42;

            if(!isset($precos[$quantidade])){
                sendMessage($chat_id,"❌ Quantidade inválida! Digite exatamente 1000, 2000, 3000, 4000, 5000, 10000, 25000 ou 50000:");
                exit;
            }

            if(is_numeric($precos[$quantidade])){
                $total = $precos[$quantidade] + $frete;
                $total_texto = "R\$$total (incluindo frete R\$$frete)";
            } else {
                $total_texto = "A combinar + frete R\$$frete";
            }

            // Mensagem de cálculo de frete
            $calc_msg = sendMessage($chat_id,"⏳ Calculando frete...");
            sleep(2); // espera 2 segundos para simular animação

            $dados = $usuarios[$chat_id];
            $resumo = "📝 *Formulário completo*\n\n".
                      "👤 Nome: {$dados['nome']}\n".
                      "🏠 Rua: {$dados['rua']}, Nº {$dados['numero']}\n".
                      "📮 CEP: {$dados['cep']}\n".
                      "🌆 Cidade: {$dados['cidade']} - {$dados['estado']}\n".
                      "📍 Bairro: {$dados['bairro']}\n".
                      "💵 Cédulas: {$dados['cedulas']}\n".
                      "🔢 Quantidade: {$dados['quantidade']}\n".
                      "🚚 Frete: R\$$frete\n".
                      "💰 Total: $total_texto\n\n".
                      "💸 *Chave PIX:* $chave_pix";

            $keyboard=[
                "inline_keyboard"=>[
                    [["text"=>"✅ Já Paguei","callback_data"=>"ja_paguei"]],
                    [["text"=>"❌ Não Paguei","callback_data"=>"nao_paguei"]]
                ]
            ];

            editMessage($chat_id,$message_id,$resumo,$keyboard);
            unset($usuarios[$chat_id]);
            break;
    }
    file_put_contents($usuariosFile,json_encode($usuarios));
}

// NÃO PAGUEI
if($callback_query=="nao_paguei"){
    editMessage($chat_id,$message_id,"⚠️ Para prosseguir, é necessário realizar o pagamento via PIX.");
    exit;
}

// JÁ PAGUEI
if($callback_query=="ja_paguei"){
    $dados = $usuarios[$chat_id] ?? [];
    $resumo_sem_pix = "📝 *Formulário confirmado*\n\n".
                      "👤 Nome: {$dados['nome']}\n".
                      "🏠 Rua: {$dados['rua']}, Nº {$dados['numero']}\n".
                      "📮 CEP: {$dados['cep']}\n".
                      "🌆 Cidade: {$dados['cidade']} - {$dados['estado']}\n".
                      "📍 Bairro: {$dados['bairro']}\n".
                      "💵 Cédulas: {$dados['cedulas']}\n".
                      "🔢 Quantidade: {$dados['quantidade']}\n\n".
                      "✅ Pagamento confirmado!\n".
                      "📨 Encaminhe este formulário para @RibeiroDo171.";
    editMessage($chat_id,$message_id
