<?php
header('Content-Type: application/json');
error_reporting(0); // Evitiamo che errori PHP rovinino il formato JSON

// Carica le chiavi esportate dall'Admin Panel
if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    // Fallback di sicurezza in caso non hai ancora caricato il file
    define('PAYMENTO_API_KEY', 'MzEyRDI1MjA5MzAyNzVCMzJCOUREMTFFNzk1NzY4OUU=');
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['amount']) || empty($input['order_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Dati ordine non validi inviati dal carrello.']);
    exit;
}

$amount = floatval($input['amount']);
$currency = isset($input['currency']) ? strtoupper($input['currency']) : 'EUR';
$orderId = $input['order_id'];

$host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$basePath = dirname($_SERVER['REQUEST_URI']);
$baseUrl = $host . $basePath;

$paymentoUrl = "https://api.paymento.io/v1/payment/request";

// Ricostruiamo i metadati correttamente in base al tuo frontend
$payload = [
    'fiatAmount' => $amount,
    'fiatCurrency' => $currency,
    'orderId' => $orderId,
    'returnUrl' => $baseUrl . "/index.html?payment=success",
    'metadata' => [
        'customer' => isset($input['customer']) ? $input['customer'] : [],
        'items' => isset($input['items']) ? $input['items'] : []
    ]
];

$ch = curl_init($paymentoUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Api-Key: ' . PAYMENTO_API_KEY,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix provvisorio se il server ha problemi di certificato

$response = curl_exec($ch);

if ($response === false) {
    // Errore fisico del Server (es. non riesce a connettersi a internet)
    $error = curl_error($ch);
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "CURL Error: " . $error]);
    curl_close($ch);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Paymento restituisce { "token": "abc..." } in caso di successo
$responseData = json_decode($response, true);

if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['token'])) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'token' => $responseData['token']
    ]);
} else {
    // Fallimento rifiutato da Paymento
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Paymento API Errore', 
        'raw_response' => $responseData
    ]);
}
?>