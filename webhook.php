<?php
header('Content-Type: application/json');
error_reporting(0);

// Carica le chiavi
if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    define('PAYMENTO_API_KEY', 'MzEyRDI1MjA5MzAyNzVCMzJCOUREMTFFNzk1NzY4OUU=');
    define('BOT_TOKEN', '8657521478:AAEtwCx-Rb8Sdc96oJG_iOWY0lXLdNsjXMs');
    define('CHAT_ID', '-1004302605412');
}

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

$token = isset($data['token']) ? $data['token'] : null;

if (!$token) {
    http_response_code(400);
    echo json_encode(['error' => 'Nessun token fornito.']);
    exit;
}

$verifyUrl = "https://api.paymento.io/v1/payment/verify";

$ch = curl_init($verifyUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Api-Key: ' . PAYMENTO_API_KEY,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['token' => $token]));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$verifyResponse = curl_exec($ch);
$verifyHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$verifyData = json_decode($verifyResponse, true);

if ($verifyHttpCode >= 200 && $verifyHttpCode < 300 && isset($verifyData['status'])) {
    
    if (in_array(strtoupper($verifyData['status']), ['COMPLETED', 'PAID', 'SUCCESS'])) {
        
        $orderId = isset($verifyData['orderId']) ? $verifyData['orderId'] : 'N/A';
        $amount = isset($verifyData['fiatAmount']) ? $verifyData['fiatAmount'] : '0';
        $currency = isset($verifyData['fiatCurrency']) ? $verifyData['fiatCurrency'] : 'EUR';
        
        $message = "🚨 *NUOVO ORDINE PAGATO E VERIFICATO* 🚨\n\n";
        $message .= "🆔 *Ordine:* `#{$orderId}`\n";
        $message .= "💰 *Totale Pagato:* {$amount} {$currency}\n\n";
        
        if (isset($verifyData['metadata']) && !empty($verifyData['metadata']['customer'])) {
            $customer = $verifyData['metadata']['customer'];
            $tgUser = $customer['telegram'] ?? '-';
            $tgLink = str_replace('@', '', $tgUser);
            
            $message .= "👤 *Contatto Cliente:*\n";
            $message .= "Telegram/Phone: {$tgUser} ( [Invia Messaggio](https://t.me/{$tgLink}) )\n\n";
            $message .= "📍 *Dettagli Spedizione:*\n";
            $message .= "Città: " . ($customer['city'] ?? '-') . "\n";
            $message .= "Indirizzo: " . ($customer['address'] ?? '-') . "\n";
            $message .= "CAP: " . ($customer['zip'] ?? '-') . "\n\n";
        }

        if (isset($verifyData['metadata']) && !empty($verifyData['metadata']['items'])) {
            $items = $verifyData['metadata']['items'];
            $message .= "🛒 *Carrello:*\n";
            foreach ($items as $item) {
                $name = $item['name'] ?? 'Prodotto';
                $qty = $item['qty'] ?? 1;
                $message .= "- {$qty}x {$name}\n";
            }
        }

        $telegramUrl = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
        $chTg = curl_init($telegramUrl);
        curl_setopt($chTg, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($chTg, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chTg, CURLOPT_POST, true);
        curl_setopt($chTg, CURLOPT_POSTFIELDS, json_encode([
            'chat_id' => CHAT_ID,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true
        ]));
        curl_setopt($chTg, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($chTg);
        curl_close($chTg);

        echo json_encode(['status' => 'success', 'message' => 'Pagamento verificato e notifica inviata.']);
    } else {
        echo json_encode(['status' => 'ignored', 'message' => 'Pagamento non completato (Stato: ' . $verifyData['status'] . ')']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Verifica fallita.', 'details' => $verifyData]);
}
?>
