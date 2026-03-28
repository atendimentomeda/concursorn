<?php
header('Content-Type: application/json');

// 🔑 Configuração da API InvictusPay
$apiUrl   = "https://api.invictuspay.app.br/api/public/v1/transactions";
$apiToken = "9PKp9AjrfYhwi1SMNoqGjbOnyaujybOEJt1UniF7NvodcQRnECdz8QMlrixP";

// 📥 Dados recebidos do front (confirmacao.html)
$input = json_decode(file_get_contents("php://input"), true);

// Validação básica dos dados vindos do seu formulário
if (!$input || empty($input['nome']) || empty($input['cpf']) || empty($input['email'])) {
    echo json_encode([
        "success" => false,
        "message" => "Dados incompletos para gerar PIX"
    ]);
    exit;
}

// ⚡ Hashes do seu novo concurso
$productHash = "n2vxouioea"; 
$offerHash   = "onnfj";      

// Monta payload adaptado para os dados que seu HTML envia
$payload = [
    "amount" => $input['valor'], // O valor já vem em centavos do seu JS (ex: 12000)
    "offer_hash" => $offerHash,
    "payment_method" => "pix",
    "customer" => [
        "name" => $input['nome'],
        "email" => $input['email'],
        "document" => preg_replace('/\D/', '', $input['cpf']), // Remove pontos e traços do CPF
        "phone_number" => preg_replace('/\D/', '', $input['telefone'] ?? "11999999999"),
        // Como o seu formulário de dadospessoais não pede endereço completo, 
        // usamos valores padrão para não travar a API
        "street_name" => "Nao informado",
        "number" => "0",
        "neighborhood" => "Centro",
        "city" => "Natal",
        "state" => "RN",
        "zip_code" => "59000000"
    ],
    "cart" => [[
        "product_hash" => $productHash,
        "title" => $input['titulo'] ?? "Taxa de Inscrição",
        "price" => $input['valor'],
        "quantity" => 1,
        "operation_type" => 1,
        "tangible" => false
    ]],
    "installments" => 1,
    "expire_in_days" => 1,
    "transaction_origin" => "api"
];

// 🔄 Requisição cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl . "?api_token=" . $apiToken,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

// 🔑 AJUSTE PARA O SEU confirmacao.html
// Seu JS espera: data.qrcode_image, data.qrcode_text e data.transaction_id
if (isset($result['hash']) && isset($result['pix'])) {

    $qrCodeText = $result['pix']['pix_qr_code'] ?? "";
    
    // Prioriza a URL da imagem que vem da API, se não existir, usa o Google Charts como fallback
    $qrCodeImg = $result['pix']['qrcode_url'] 
               ?? $result['pix']['qr_code_url'] 
               ?? "https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=" . urlencode($qrCodeText);

    echo json_encode([
        "success" => true,
        "data" => [
            "transaction_id" => $result['hash'],
            "qrcode_image" => $qrCodeImg,
            "qrcode_text" => $qrCodeText
        ]
    ]);

} else {
    echo json_encode([
        "success" => false,
        "message" => "Erro na API InvictusPay",
        "debug" => $result
    ]);
}