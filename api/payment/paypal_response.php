<?php
/**
 ***********************************************************************************************
 * PayPal Mobile Response Handler
 *
 * Clean HTML response page for mobile WebView (no Admidio theme/session).
 * Displays success/fail message with mobile-friendly styling.
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
    $statusTitle = $success ? 'Payment Successful' : 'Payment Failed';
    $statusTitleKey = $success ? 'RE_PG_PAYMENT_SUCCESSFUL' : 'RE_PG_PAYMENT_FAILED';
    $statusMessage = $data['message'] ?? ($success ? 'Your payment has been processed successfully.' : 'Your payment could not be completed.');
    $statusMessageKey = $data['message_code'] ?? ($success ? 'RE_PG_SUCCESS_MSG' : 'RE_PG_FAILED_MSG');
    
    $orderId = htmlspecialchars((string)($data['order_id'] ?? '-'));
    $trackingId = htmlspecialchars((string)($data['tracking_id'] ?? '-'));
    $amount = htmlspecialchars((string)($data['amount'] ?? '-'));
    $currency = htmlspecialchars((string)($data['currency'] ?? 'USD'));
    
    // JavaScript boolean string for script embedding
    $successJs = $success ? 'true' : 'false';
    
    // Build JSON data for mobile app to read (includes i18n keys)
    $resultData = json_encode([
        'success' => $success,
        'order_id' => $data['order_id'] ?? null,
        'tracking_id' => $data['tracking_id'] ?? null,
        'amount' => $data['amount'] ?? null,
        'message' => $statusMessage,
        'message_code' => $statusMessageKey,
        'title_code' => $statusTitleKey,
    ]);
    
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{$statusTitle}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #0B1120 0%, #1a2942 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #fff;
        }
        .card {
            background: #fff;
            border-radius: 20px;
            padding: 32px 24px;
            width: 100%;
            max-width: 360px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .icon-circle {
            width: 80px; height: 80px; border-radius: 50%;
            background: {$statusColor};
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px; font-size: 40px; color: #fff;
            animation: scaleIn 0.4s ease-out;
        }
        @keyframes scaleIn { 0% { transform: scale(0); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
        .title { font-size: 22px; font-weight: 700; color: #1a1a1a; margin-bottom: 8px; }
        .message { font-size: 14px; color: #666; margin-bottom: 24px; line-height: 1.5; }
        .details { background: #f8f9fa; border-radius: 12px; padding: 16px; margin-bottom: 24px; text-align: left; }
        .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-size: 13px; color: #888; }
        .detail-value { font-size: 13px; color: #333; font-weight: 600; }
        .amount-row .detail-value { font-size: 18px; color: {$statusColor}; font-weight: 700; }
        .btn {
            display: block; width: 100%; padding: 16px; border: none; border-radius: 12px;
            font-size: 16px; font-weight: 700; cursor: pointer; text-decoration: none;
            text-align: center; transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background: #349aaa; color: #fff; }
        .btn-primary:hover { box-shadow: 0 4px 12px rgba(52, 154, 170, 0.4); }
        #payment-result-data { display: none; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-circle">{$statusIcon}</div>
        <h1 class="title">{$statusTitle}</h1>
        <p class="message">{$statusMessage}</p>
        
        <div class="details">
            <div class="detail-row amount-row">
                <span class="detail-label">Amount</span>
                <span class="detail-value">{$currency} {$amount}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Order ID</span>
                <span class="detail-value">{$orderId}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Transaction ID</span>
                <span class="detail-value">{$trackingId}</span>
            </div>
        </div>
        
        <button class="btn btn-primary" onclick="closePayment()">Done</button>
    </div>
    
    <div id="payment-result-data" data-result='{$resultData}'></div>
    
    <script>
        function closePayment() {
            if (window.ReactNativeWebView) {
                window.ReactNativeWebView.postMessage(JSON.stringify({
                    type: 'PAYMENT_COMPLETE',
                    success: {$successJs},
                    data: {$resultData}
                }));
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            if (window.ReactNativeWebView) {
                window.ReactNativeWebView.postMessage(JSON.stringify({
                    type: 'PAYMENT_RESULT_LOADED',
                    success: {$successJs},
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

// -------------------------------------------------------------
// PROCESS PAYPAL RESPONSE
// -------------------------------------------------------------

$token = $_GET['token'] ?? '';
if (empty($token)) {
    renderMobileResultPage(false, ['message' => 'Missing transaction token.', 'order_id' => '-']);
}

// 1. Get Access Token
$accessToken = paypal_get_access_token();
if (empty($accessToken)) {
    renderMobileResultPage(false, ['message' => 'Internal authentication failed.', 'order_id' => $token]);
}

// 2. Capture Order
$captureRequest = paypal_capture_order($accessToken, $token);
$captureResponse = json_decode($captureRequest['body'], true);
$status = $captureResponse['status'] ?? '';
$isSuccess = ($status === 'COMPLETED');

// 3. Update Database
try {
    $stmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_RE_TRANS . ' WHERE rtr_pg_id = ?', [$token], false);
    $pgPaymentData = $stmt ? $stmt->fetch() : null;

    if (!$pgPaymentData) {
        renderMobileResultPage(false, ['message' => 'Payment record not found.', 'order_id' => $token]);
    }

    $paymentId = (int)$pgPaymentData['rtr_id'];
    $ownerId = (int)$pgPaymentData['rtr_usr_id'];
    $orgId = (int)$pgPaymentData['rtr_org_id'];
    $amount = $captureResponse['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? $pgPaymentData['rtr_amount'];
    $currency = $pgPaymentData['rtr_currency'];
    $trackingId = $captureResponse['purchase_units'][0]['payments']['captures'][0]['id'] ?? '';

    $gDb->queryPrepared('UPDATE ' . TBL_RE_TRANS . ' SET rtr_status = ?, rtr_pg_response = ?, rtr_pg_msg = ?, rtr_usr_id_change = ?, rtr_pg_trans_date = ?, rtr_timestamp_change = ? WHERE rtr_id = ?', 
    [($isSuccess ? 'SU' : 'FA'), $captureRequest['body'], $status, $ownerId, DATETIME_NOW, DATETIME_NOW, $paymentId], false);

    if ($isSuccess) {
        $itemStmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_RE_TRANS_ITEMS . ' WHERE rti_pg_payment_id = ?', [$paymentId], false);
        $pgPaymentItems = $itemStmt ? $itemStmt->fetchAll() : array();

        if ($pgPaymentItems) {
            $gDb->queryPrepared('INSERT INTO ' . TBL_RE_PAYMENTS . ' (rpa_status, rpa_date, rpa_pg_pay_method, rpa_pay_type, rpa_trans_id, rpa_usr_id, rpa_org_id, rpa_usr_id_create, rpa_usr_id_change, rpa_timestamp_create, rpa_timestamp_change) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 
            ['SU', DATETIME_NOW, 'PayPal', 'Online', $trackingId, $ownerId, $orgId, $ownerId, $ownerId, DATETIME_NOW, DATETIME_NOW], false);
            $bilPaymentId = $gDb->lastInsertId();

            if ($bilPaymentId > 0) {
                $gDb->queryPrepared('UPDATE ' . TBL_RE_TRANS . ' SET rtr_payment_id = ? WHERE rtr_id = ?', [$bilPaymentId, $paymentId], false);
                foreach ($pgPaymentItems as $item) {
                    $gDb->queryPrepared('INSERT INTO ' . TBL_RE_PAYMENT_ITEMS . ' (rpi_payment_id, rpi_inv_id, rpi_amount, rpi_currency, rpi_usr_id, rpi_org_id, rpi_usr_id_create, rpi_usr_id_change) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', 
                    [$bilPaymentId, $item['rti_inv_id'], $item['rti_amount'], $item['rti_currency'], $ownerId, $orgId, $ownerId, $ownerId], false);
                    $gDb->queryPrepared('UPDATE ' . TBL_RE_INVOICES . ' SET riv_is_paid = 1 WHERE riv_id = ?', [(int)$item['rti_inv_id']], false);
                }
            }
        }
    }
    
    renderMobileResultPage($isSuccess, [
        'order_id' => $token,
        'tracking_id' => $trackingId,
        'amount' => $amount,
        'currency' => $currency,
        'message' => $isSuccess ? null : ($status ?: 'Payment failed at PayPal')
    ]);

} catch (\Throwable $e) {
    error_log('Mobile PayPal Response Error: ' . $e->getMessage());
    renderMobileResultPage(false, ['message' => 'Internal processing error.', 'order_id' => $token]);
}
