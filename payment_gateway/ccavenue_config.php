<?php
/**
    * CCAvenue Configuration File
    *
    * Contains merchant credentials and configuration for CCAvenue payment gateway
    * Fetched dynamically from database settings
    */

// Fetch configuration from database
$residentsConfig = residentsReadConfig();
$gateways = $residentsConfig['payment_gateways'] ?? array();
$pgIndex = $_POST['payment_gateway'] ?? $_GET['payment_gateway'] ?? null;
$pgConf = array();

if (is_numeric($pgIndex) && isset($gateways[(int)$pgIndex])) {
    $pgConf = $gateways[(int)$pgIndex];
} else {
    // Fallback: search for the first CCAVENUE config
    foreach ($gateways as $gw) {
        if (($gw['name'] ?? '') === 'CCAVENUE') {
            $pgConf = $gw;
            break;
        }
    }
    // Final fallback to legacy property if search fails
    if (empty($pgConf)) {
        $pgConf = $residentsConfig['payment_gateway'] ?? array();
    }
}

// CCAvenue Credentials
if (!defined('CCAVENUE_ACCESS_CODE')) {
    define('CCAVENUE_ACCESS_CODE', $pgConf['access_code'] ?? '');
}
if (!defined('CCAVENUE_WORKING_KEY')) {
    define('CCAVENUE_WORKING_KEY', $pgConf['working_key'] ?? '');
}
if (!defined('CCAVENUE_MERCHANT_ID')) {
    define('CCAVENUE_MERCHANT_ID', $pgConf['merchant_id'] ?? '');
}

// CCAvenue API URL
if (!defined('CCAVENUE_API_URL')) {
    define('CCAVENUE_API_URL', $pgConf['gateway_url'] ?? '');
}
