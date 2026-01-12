<?php
/**
    * Mobile CCAvenue start API (HTML response)
    */

require_once __DIR__ . '/../../../../system/common.php';
require_once __DIR__ . '/../../common_function.php';
require_once __DIR__ . '/../../payment_gateway/ccavenue_common.php';

global $gCurrentUser;

// API key auth (NO session)
$apiKey =
    $_GET['api_key']
    ?? $_SERVER['HTTP_API_KEY']
    ?? null;

if (!$apiKey) {
    http_response_code(200); // IMPORTANT: not 401
    echo 'Missing API key';
    exit;
}

$gCurrentUser = validateApiKey();

if (!$gCurrentUser) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$userId = (int)$gCurrentUser->getValue('usr_id');

// Read invoice IDs from query (WebView-friendly)
$invoiceIds = $_GET['invoice_ids'] ?? [];
$invoiceIds = array_map('intval', (array)$invoiceIds);

// Render payment form
renderCcavenueForMobile($invoiceIds, $userId);
