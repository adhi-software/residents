<?php
/**
    * PayPal Configuration File
    *
    * Contains client credentials and configuration for PayPal payment gateway
    * Fetched dynamically from database settings
    */

// Fetch configuration from database
$residentsConfig = residentsReadConfig();
$gateways = $residentsConfig['payment_gateways'] ?? array();

// Find PayPal configuration
$pgIndex = $_POST['payment_gateway'] ?? $_GET['payment_gateway'] ?? null;
$pgConf = array();

if (is_numeric($pgIndex) && isset($gateways[(int)$pgIndex])) {
    $pgConf = $gateways[(int)$pgIndex];
} else {
    // Fallback: search for the first PayPal config
    foreach ($gateways as $gw) {
        if (($gw['name'] ?? '') === 'PAYPAL') {
            $pgConf = $gw;
            break;
        }
    }
}

// PayPal Credentials
if (!defined('PAYPAL_CLIENT_ID')) {
    define('PAYPAL_CLIENT_ID', $pgConf['client_id'] ?? '');
}
if (!defined('PAYPAL_CLIENT_SECRET')) {
    define('PAYPAL_CLIENT_SECRET', $pgConf['client_secret'] ?? '');
}

// PayPal API URL
if (!defined('PAYPAL_API_URL')) {
    define('PAYPAL_API_URL', $pgConf['gateway_url'] ?? '');
}

// Timeout
if (!defined('PAYPAL_TIMEOUT')) {
    define('PAYPAL_TIMEOUT', $pgConf['timeout'] ?? 15);
}

/**
 * Helper function to make HTTP requests to PayPal API
 */
function paypal_make_request($url, $method = 'POST', $headers = [], $body = null) {
    if (empty($url)) {
        return ['code' => 0, 'body' => 'Empty URL'];
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_SSLVERSION, 6); // CURL_SSLVERSION_TLSv1_2
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch) . ' (Code: ' . curl_errno($ch) . ')';
        curl_close($ch);
        return ['code' => 0, 'body' => $error_msg];
    }
    curl_close($ch);
    return ['code' => $httpCode, 'body' => $response];
}
