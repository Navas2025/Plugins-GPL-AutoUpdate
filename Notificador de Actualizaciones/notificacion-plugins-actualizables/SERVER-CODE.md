# Código del servidor para check-updates.php

Este archivo debe reemplazar o actualizar el servidor en `https://plugins-wp.online/check-updates.php`

## Código PHP actualizado:

```php
<?php
/**
 * API para verificar actualizaciones de plugins
 * Versión 2.5.4 - Con desencriptación y verificación de firma
 */

header('Content-Type: application/json');

// API Key (misma que en el cliente, ofuscada igual)
function get_api_key() {
    $encoded = 'VENZLTIwMjQtQ0VSWlZIWi1OUFBSRkY=';
    $decoded = base64_decode($encoded);
    return str_rot13($decoded);
}

function get_encryption_key() {
    return hash('sha256', get_api_key());
}

// Verificar API Key
if (empty($_POST['api_key']) || $_POST['api_key'] !== get_api_key()) {
    die(json_encode(['success' => false, 'error' => 'Invalid API key']));
}

// Obtener datos encriptados
$encrypted_data = base64_decode($_POST['data']);
$iv = base64_decode($_POST['iv']);
$signature = $_POST['signature'];

// Verificar firma HMAC
$expected_signature = hash_hmac('sha256', $encrypted_data, get_encryption_key());
if ($signature !== $expected_signature) {
    die(json_encode(['success' => false, 'error' => 'Invalid signature']));
}

// Desencriptar datos
$decrypted_data = openssl_decrypt(
    $encrypted_data,
    'AES-256-CBC',
    get_encryption_key(),
    0,
    $iv
);

if ($decrypted_data === false) {
    die(json_encode(['success' => false, 'error' => 'Decryption failed']));
}

$data = json_decode($decrypted_data, true);

if (!isset($data['plugins']) || !is_array($data['plugins'])) {
    die(json_encode(['success' => false, 'error' => 'Invalid data format']));
}

// Aquí va tu lógica para verificar actualizaciones disponibles
// Ejemplo:

$available_updates = [];

// Conectar a tu base de datos de plugins actualizados
// (Esto depende de cómo tengas organizado tu sistema)

// Ejemplo ficticio:
foreach ($data['plugins'] as $plugin) {
    // Verificar si hay actualización disponible para este plugin
    // $new_version = check_database_for_updates($plugin['slug']);
    
    // Si hay actualización:
    // $available_updates[] = [
    //     'slug' => $plugin['slug'],
    //     'name' => $plugin['name'],
    //     'current_version' => $plugin['version'],
    //     'new_version' => $new_version,
    //     'download_url' => 'https://plugins-wp.online/download/' . $plugin['slug']
    // ];
}

// Responder
echo json_encode([
    'success' => true,
    'updates' => $available_updates,
    'timestamp' => time()
]);
```

## Notas de implementación:

1. **API Key**: Debe coincidir con la del cliente (GPL-2024-PREMIUM-ACCESS ofuscada)
2. **Encriptación**: Se usa AES-256-CBC con IV aleatorio
3. **Firma HMAC**: Verifica integridad de los datos antes de desencriptar
4. **Respuesta**: Debe incluir array de actualizaciones con estructura específica

## Estructura de respuesta esperada:

```json
{
    "success": true,
    "updates": [
        {
            "slug": "plugin-slug",
            "name": "Plugin Name",
            "current_version": "1.0.0",
            "new_version": "1.1.0",
            "download_url": "https://plugins-wp.online/download/plugin-slug"
        }
    ],
    "timestamp": 1234567890
}
```
