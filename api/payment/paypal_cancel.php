<?php
/**
 ***********************************************************************************************
 * PayPal Mobile Cancel Handler
 *
 * Handles user cancellations on mobile devices.
 * Displays a mobile-friendly notification.
 ***********************************************************************************************
 */

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.auto_start', '0');
}

require_once(__DIR__ . '/../../common_function.php');
require_once(__DIR__ . '/../../payment_gateway/paypal_common.php');

ob_clean();

global $gDb;

/**
 * Render mobile-friendly result page
 */
function renderMobileResultPage(bool $success, array $data): void
{
    $statusColor = $success ? '#28a745' : '#dc3545';
    $statusIcon = $success ? '✓' : '✕';
    $statusTitle = $success ? 'Payment Successful' : 'Payment Cancelled';
    $statusMessage = $data['message'] ?? 'Your payment has been cancelled.';
    
    // JavaScript boolean string for script embedding
    $successJs = $success ? 'true' : 'false';
    
    // Build JSON data
    $resultData = json_encode([
        'success' => $success,
        'order_id' => $data['order_id'] ?? null,
        'message' => $statusMessage,
    ]);
    
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Payment Cancelled</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #0B1120 0%, #1a2942 100%);
            min-height: 100vh;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 20px; color: #fff;
        }
        .card { background: #fff; border-radius: 20px; padding: 32px 24px; width: 100%; max-width: 360px; text-align: center; }
        .icon-circle { width: 80px; height: 80px; border-radius: 50%; background: {$statusColor}; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 40px; color: #fff; }
        .title { font-size: 22px; font-weight: 700; color: #1a1a1a; margin-bottom: 8px; }
        .message { font-size: 14px; color: #666; margin-bottom: 24px; line-height: 1.5; }
        .btn { display: block; width: 100%; padding: 16px; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; text-align: center; background: #349aaa; color: #fff; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-circle">{$statusIcon}</div>
        <h1 class="title">{$statusTitle}</h1>
        <p class="message">{$statusMessage}</p>
        <button class="btn" onclick="closePayment()">Back to App</button>
    </div>
    
    <script>
        function closePayment() {
            if (window.ReactNativeWebView) {
                window.ReactNativeWebView.postMessage(JSON.stringify({
                    type: 'PAYMENT_CANCELLED',
                    success: false,
                    data: {$resultData}
                }));
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            if (window.ReactNativeWebView) {
                window.ReactNativeWebView.postMessage(JSON.stringify({
                    type: 'PAYMENT_CANCELLED',
                    success: false,
                    data: {$resultData}
                }));
            }
        });
    </script>
</body>
</html>
HTML;
    exit;
}

$token = $_GET['token'] ?? '';

if (!empty($token)) {
    try {
        $stmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_RE_TRANS . ' WHERE rtr_pg_id = ?', [$token], false);
        $pgPaymentData = $stmt ? $stmt->fetch() : null;
        if ($pgPaymentData) {
            $gDb->queryPrepared('UPDATE ' . TBL_RE_TRANS . ' SET rtr_status = ?, rtr_pg_msg = ?, rtr_timestamp_change = NOW() WHERE rtr_id = ?', ['AB', 'Cancelled by user (Mobile)', (int)$pgPaymentData['rtr_id']], false);
        }
    } catch (\Throwable $e) {
        error_log('Mobile PayPal Cancel Error: ' . $e->getMessage());
    }
}

renderMobileResultPage(false, ['order_id' => $token, 'message' => 'Your payment was cancelled by you.']);
