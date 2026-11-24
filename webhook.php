<?php
// 1. CONFIGURACIÓN: Define tu token secreto (el mismo que pondrás en Meta)
$verify_token = "mi_token_secreto_123"; 

// 2. VERIFICACIÓN (GET): Meta llama a esta URL para validar
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    // Si el token coincide, responde con el challenge en texto plano
    if ($mode === 'subscribe' && $token === $verify_token) {
        http_response_code(200);
        echo $challenge;
    } else {
        http_response_code(403);
        echo "Forbidden";
    }
    exit;
}

// 3. RECEPCIÓN DE MENSAJES (POST): Meta envía los mensajes aquí
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtiene el cuerpo JSON crudo que envía WhatsApp
    $input = file_get_contents('php://input');
    
    // (Opcional pero recomendado) Validar la firma para seguridad
    // $app_secret = "TU_APP_SECRET"; 
    // $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    // if (!validarFirma($input, $signature, $app_secret)) { http_response_code(403); exit; }

    // Interpretar el JSON
    $data = json_decode($input, true);

    // Verificar si hay mensajes entrantes
    if (isset($data['entry'][0]['changes'][0]['value']['messages'])) {
        $message = $data['entry'][0]['changes'][0]['value']['messages'][0];
        $from = $message['from']; // Número del cliente
        $text = $message['text']['body'] ?? ''; // Texto del mensaje

        // --- LÓGICA DE INTERPRETACIÓN ---
        // Aquí es donde "interpretas" el mensaje. Por ejemplo, guardarlo en un log.
        $log = "Mensaje de $from: $text\n";
        file_put_contents('mensajes.log', $log, FILE_APPEND);

        // Ejemplo de respuesta automática (requiere configurar el envío con tu Token de Acceso)
        // enviarMensaje($from, "Recibí tu mensaje: " . $text);
    }

    // SIEMPRE responder 200 OK a Meta para que no reintente el envío
    http_response_code(200);
    echo "EVENT_RECEIVED";
    exit;
}

// Función auxiliar para validar la firma (Seguridad HMAC)
function validarFirma($payload, $signature, $secret) {
    if (empty($signature)) return false;
    $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected, $signature);
}
?>