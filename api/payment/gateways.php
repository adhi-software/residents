<?php
/**
 * Mobile Payment Gateways API
 *
 * Returns the list of configured payment gateways for mobile app.
 * Used by InvoicePaymentSelect to dynamically show gateway selection.
 */

require_once __DIR__ . '/../../../../system/common.php';
require_once __DIR__ . '/../../common_function.php';

header('Content-Type: application/json; charset=utf-8');

global $gCurrentUser;

// Validate API Key
$gCurrentUser = validateApiKey();

// Read gateway configuration
$residentsConfig = residentsReadConfig();
$gateways = $residentsConfig['payment_gateways'] ?? array();

$result = [];

foreach ($gateways as $index => $gw) {
    $name = $gw['name'] ?? '';
    if (empty($name)) {
        continue;
    }

    // Map gateway type to its mobile API endpoint script name
    $typeMap = [
        'CCAVENUE' => 'ccavenue',
        'PAYPAL'   => 'paypal',
    ];
    $type = $typeMap[$name] ?? strtolower($name);

    $result[] = [
        'index'        => (int)$index,
        'name'         => $name,
        'type'         => $type,
        'display_name' => $gw['display_name'] ?? ucfirst(strtolower($name)),
        'endpoint'     => $type . '_pay.php',
    ];
}

echo json_encode([
    'success'  => true,
    'gateways' => $result,
    'count'    => count($result),
]);
