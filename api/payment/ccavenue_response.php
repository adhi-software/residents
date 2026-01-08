<?php
/**
    * CCAvenue Mobile Response Handler
    * 
    * Clean HTML response page for mobile WebView (no Admidio theme/session).
    * Displays success/fail message with mobile-friendly styling.
    */

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.auto_start', '0');
}

require_once __DIR__ . '/../../common_function.php';
require_once __DIR__ . '/../../payment_gateway/ccavenue_config.php';
require_once __DIR__ . '/../../payment_gateway/ccavenue_crypto.php';

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
    $statusMessage = $data['message'] ?? ($success ? 'Your payment has been processed successfully.' : 'Your payment could not be completed.');
    
    $orderId = htmlspecialchars($data['order_id'] ?? '-');
    $trackingId = htmlspecialchars($data['tracking_id'] ?? '-');
    $amount = htmlspecialchars($data['amount'] ?? '-');
    $currency = htmlspecialchars($data['currency'] ?? '₹');
    
    // Build JSON data for mobile app to read
    $resultData = json_encode([
        'success' => $success,
        'order_id' => $data['order_id'] ?? null,
        'tracking_id' => $data['tracking_id'] ?? null,
        'amount' => $data['amount'] ?? null,
        'message' => $statusMessage,
    ]);
    
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>{$statusTitle}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
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
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: {$statusColor};
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            color: #fff;
            animation: scaleIn 0.4s ease-out;
        }
        @keyframes scaleIn {
            0% { transform: scale(0); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .title {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        .message {
            font-size: 14px;
            color: #666;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        .details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            text-align: left;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-size: 13px;
            color: #888;
        }
        .detail-value {
            font-size: 13px;
            color: #333;
            font-weight: 600;
        }
        .amount-row .detail-value {
            font-size: 18px;
            color: {$statusColor};
            font-weight: 700;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:active {
            transform: scale(0.98);
        }
        .btn-primary {
            background: #349aaa;
            color: #fff;
        }
        .btn-primary:hover {
            box-shadow: 0 4px 12px rgba(52, 154, 170, 0.4);
        }
        
        /* Hidden data element for mobile app to read */
        #payment-result-data {
            display: none;
        }
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
                <span class="detail-value">{$currency}{$amount}</span>
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
    
    <!-- Hidden element containing result data for React Native to extract -->
    <div id="payment-result-data" data-result='{$resultData}'></div>
    
    <script>
        // For React Native WebView to detect completion
        function closePayment() {
            // Try to communicate with React Native
            if (window.ReactNativeWebView) {
                window.ReactNativeWebView.postMessage(JSON.stringify({
                    type: 'PAYMENT_COMPLETE',
                    success: {$success},
                    data: {$resultData}
                }));
            }
            // Fallback: navigate to custom scheme that mobile app can intercept
            window.location.href = 'madmidio://payment/result?' + 
                'success=' + {$success} + 
                '&order_id=' + encodeURIComponent('{$orderId}') +
                '&tracking_id=' + encodeURIComponent('{$trackingId}');
        }
        
        // Auto-post result to React Native on page load
        document.addEventListener('DOMContentLoaded', function() {
            if (window.ReactNativeWebView) {
                window.ReactNativeWebView.postMessage(JSON.stringify({
                    type: 'PAYMENT_RESULT_LOADED',
                    success: {$success},
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

// ============================================================
// PROCESS CCAVENUE RESPONSE
// ============================================================

if (!isset($_POST['encResp'])) {
    renderMobileResultPage(false, [
        'message' => 'Invalid response from payment gateway.',
        'order_id' => '-',
        'tracking_id' => '-',
        'amount' => '-'
    ]);
}

// Decrypt response
$encResponse = $_POST['encResp'];
$decResponse = decrypt_ccavenue($encResponse, CCAVENUE_WORKING_KEY);
parse_str($decResponse, $received);

// Extract fields
$orderId    = $received['order_id'] ?? ($received['merchant_param4'] ?? '');
$amount     = $received['amount'] ?? ($received['order_amount'] ?? '');
$currency   = $received['currency'] ?? ($received['order_currency'] ?? '');
$status     = $received['order_status'] ?? ($received['status'] ?? '');
$trackingId = $received['tracking_id'] ?? '';
$bankRefNo  = $received['bank_ref_no'] ?? '';
$merchantParam = $received['merchant_param3'] ?? '';
$statusMessage = $received['status_message'] ?? '';

if (strtoupper($currency) === 'INR') {
    $currency = '₹';
}

// Check if payment exists
$paymentId = (int)$orderId;
$pgPaymentData = null;

try {
    $stmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_BL_TRANS . ' WHERE btr_id = ?', [$paymentId], false);
    if ($stmt !== false) {
        $pgPaymentData = $stmt->fetch();
    }
} catch (Exception $e) {
    error_log('Mobile response: Failed to retrieve payment: ' . $e->getMessage());
}

if ($pgPaymentData === null) {
    renderMobileResultPage(false, [
        'message' => 'Database error.',
        'order_id' => $orderId,
        'tracking_id' => $trackingId,
        'amount' => $amount,
        'currency' => $currency
    ]);
}

if (!$pgPaymentData) {
    renderMobileResultPage(false, [
        'message' => 'Payment record not found.',
        'order_id' => $orderId,
        'tracking_id' => $trackingId,
        'amount' => $amount,
        'currency' => $currency
    ]);
}

$orgId = isset($pgPaymentData['btr_org_id']) ? (int)$pgPaymentData['btr_org_id'] : null;

// Format transaction date
$transDate = $received['trans_date'] ?? '';
$formattedTransDate = null;
if ($transDate !== '') {
    $dt = DateTime::createFromFormat('d/m/Y H:i:s', $transDate);
    $formattedTransDate = $dt !== false ? $dt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
} else {
    $formattedTransDate = date('Y-m-d H:i:s');
}

// Update payment record
try {
    $updateSql = 'UPDATE ' . TBL_BL_TRANS . ' SET
        btr_pg_id = ?,
        btr_bank_ref_no = ?,
        btr_status = ?,
        btr_amount = ?,
        btr_currency = ?,
        btr_pg_pay_method = ?,
        btr_pg_msg = ?,
        btr_pg_response = ?,
        btr_usr_id_change = ?,
        btr_pg_trans_date = ?,
        btr_timestamp_change = NOW()
    WHERE btr_id = ?';

    $updated = $gDb->queryPrepared($updateSql, [
        !empty($trackingId) ? substr((string)$trackingId, 0, 30) : $pgPaymentData['btr_pg_id'],
        !empty($bankRefNo) ? substr((string)$bankRefNo, 0, 255) : null,
        billingGetPaymentStatus($status),
        !empty($amount) ? $amount : null,
        !empty($currency) ? $currency : null,
        $received['payment_mode'] ?? null,
        $statusMessage ?: $trackingId,
        $decResponse,
        is_numeric($merchantParam) ? (int)$merchantParam : null,
        $formattedTransDate,
        $paymentId
    ], false);
    if ($updated === false) {
        error_log('Mobile response: Failed to update payment: DB error');
    }
} catch (Exception $e) {
    error_log('Mobile response: Failed to update payment: ' . $e->getMessage());
}

// Check success
$successIndicators = ['Success', 'SUCCESS', 'success', 'Captured', 'CAPTURED', 'AUTHORISED', 'Authorised'];
$isSuccess = in_array($status, $successIndicators, true);

// Process success: create bil_payments and update invoices
if ($isSuccess) {
    $hasInvoicePaidColumn = columnExistsBILL(TBL_BL_INVOICES, 'biv_is_paid');
    
    try {
        $itemStmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_BL_TRANS_ITEMS . ' WHERE bti_pg_payment_id = ?', [$paymentId], false);
        $pgPaymentItems = $itemStmt ? $itemStmt->fetchAll() : array();

        if ($pgPaymentItems) {
            $insertPaymentSql = 'INSERT INTO ' . TBL_BL_PAYMENTS . ' (
                bpa_status, bpa_date, bpa_pg_pay_method, bpa_pay_type, bpa_trans_id, bpa_bank_ref_no, bpa_usr_id, bpa_org_id, bpa_usr_id_create, bpa_usr_id_change
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

            $paymentStatus = billingGetPaymentStatus($status);
            $merchantUser = is_numeric($merchantParam) ? (int)$merchantParam : null;
            
            $insertedPayment = $gDb->queryPrepared($insertPaymentSql, [
                $paymentStatus,
                $formattedTransDate,
                $received['payment_mode'] ?? $pgPaymentData['btr_pg_pay_method'] ?? null,
                'Online',
                $trackingId,
                $bankRefNo,
                $merchantUser,
                $orgId,
                $merchantUser,
                $merchantUser
            ], false);

            if ($insertedPayment === false) {
                error_log('Mobile response: Failed to insert payment: DB error');
                $pgPaymentItems = array();
            }

            $bilPaymentId = $gDb->lastInsertId();

            if ($bilPaymentId > 0) {
                // Link to pg_payment
                $linked = $gDb->queryPrepared(
                    'UPDATE ' . TBL_BL_TRANS . ' SET btr_payment_id = ?, btr_usr_id_change = ?, btr_timestamp_change = NOW() WHERE btr_id = ?',
                    [$bilPaymentId, $merchantUser, $paymentId],
                    false
                );
                if ($linked === false) {
                    error_log('Mobile response: Failed to link payment: DB error');
                }

                // Insert payment items and mark invoices paid
                $insertItemSql = 'INSERT INTO ' . TBL_BL_PAYMENT_ITEMS . ' (
                    bpi_payment_id, bpi_inv_id, bpi_amount, bpi_currency, bpi_usr_id, bpi_org_id, bpi_usr_id_create, bpi_usr_id_change
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';

                foreach ($pgPaymentItems as $item) {
                    $insertedItem = $gDb->queryPrepared($insertItemSql, [
                        $bilPaymentId,
                        $item['bti_inv_id'] ?? null,
                        $item['bti_amount'] ?? null,
                        $item['bti_currency'] ?? null,
                        $merchantUser,
                        $orgId,
                        $merchantUser,
                        $merchantUser
                    ], false);
                    if ($insertedItem === false) {
                        error_log('Mobile response: Failed to insert payment item: DB error');
                        continue;
                    }

                    // Mark invoice as paid
                    if ($paymentStatus === 'SU' && $hasInvoicePaidColumn && !empty($item['bti_inv_id'])) {
                        $marked = $gDb->queryPrepared(
                            'UPDATE ' . TBL_BL_INVOICES . ' SET biv_is_paid = ? WHERE biv_id = ?',
                            [1, (int)$item['bti_inv_id']],
                            false
                        );
                        if ($marked === false) {
                            error_log('Mobile response: Failed to mark invoice paid: DB error');
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Mobile response: Failed to process success: ' . $e->getMessage());
    }
}

// Render result page
renderMobileResultPage($isSuccess, [
    'message' => $isSuccess 
        ? 'Your payment has been processed successfully.' 
        : ($statusMessage ?: 'Your payment could not be completed. Please try again.'),
    'order_id' => $orderId,
    'tracking_id' => $trackingId,
    'amount' => $amount,
    'currency' => $currency
]);
