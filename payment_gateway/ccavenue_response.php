<?php
/**
    ***********************************************************************************************
    * CCAvenue payment response handler
    *
    * This file handles the response from CCAvenue payment gateway and updates the database
    * NOTE: This file must NOT initialize sessions to avoid logout issues with gateway callbacks
    ***********************************************************************************************
    */

// Start output buffering to prevent any output before redirect
ob_start();

// Prevent session auto-start if possible
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.auto_start', '0');
}

// Load plugin-specific bootstrap
require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/ccavenue_config.php');
require_once(__DIR__ . '/ccavenue_crypto.php');

// Note: Gateway callbacks are cross-site POSTs and may not carry session cookies.
// We must NOT use any session-dependent functions here.
// Clear any output that might have been generated
ob_clean();

// Ensure $gDb is available as global
global $gDb;

function billingPgRedirect(array $params): void
{
    $params['tab'] = $params['tab'] ?? 'invoices';
    $url = SecurityUtils::encodeUrl(
    ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php',
    $params
    );
    header('Location: ' . $url);
    exit;
}

// Check if response exists
if (!isset($_POST['encResp'])) {
    // Redirect to payment page with error
    ob_end_clean();
    billingPgRedirect(array(
    'payment_status' => 'failed',
    'payment_message' => 'invalid_response'
    ));
}

// Decrypt response
$encResponse = $_POST['encResp'];
$decResponse = decrypt_ccavenue($encResponse, CCAVENUE_WORKING_KEY);
parse_str($decResponse, $received);

// Attempt to resume the original user session using merchant_param5 (session id)
if (!empty($received['merchant_param5']) && is_string($received['merchant_param5'])) {
    $sessionId = $received['merchant_param5'];
    // Basic validation to avoid invalid session identifiers
    if (preg_match('/^[A-Za-z0-9,-]{6,}$/', $sessionId)) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
    }
        session_id($sessionId);
        session_start();
    } else {
        error_log('CCAvenue response returned invalid session id format.');
    }
}

// Extract common fields with fallbacks
$orderId       = $received['order_id']        ?? ($received['orderid'] ?? ($received['merchant_param4'] ?? ''));
$amount        = $received['amount']          ?? ($received['order_amount'] ?? '');
$currency      = $received['currency']         ?? ($received['order_currency'] ?? '');
if (strtoupper($currency) === 'INR') {
    $currency = '₹';
}
$status        = $received['order_status']    ?? ($received['status'] ?? '');
$merchantParam = $received['merchant_param3']  ?? ($received['merchant_param3'] ?? '');
$invoiceIdRaw  = $received['merchant_param2']  ?? '';
$invoiceId     = (is_numeric($invoiceIdRaw) ? (int)$invoiceIdRaw : 0);
$trackingId    = $received['tracking_id']     ?? '';
$bankRefNo     = $received['bank_ref_no']     ?? '';

// Save response to database
try {
    if ($orderId === '') {
        error_log('Invalid payment response: missing order reference.');
        billingPgRedirect(array(
            'payment_status' => 'failed',
            'payment_message' => 'missing_order'
        ));
    }

    // No CCAvenue-specific tables anymore: we persist into bil_pg_payments only

    // Insert a generic payment record into bil_pg_payments
    // Without a transactions table, use response values as original_* if present


    // Retrieve the bil_pg_payments record first
    $paymentId = (int)$orderId;
    $pgPaymentData = null;
    try {
        $stmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_BL_TRANS . ' WHERE btr_id = ?', array($paymentId), false);
        if ($stmt === false) {
            throw new RuntimeException('Failed to retrieve initiated payment record.');
    }
        $pgPaymentData = $stmt->fetch();
    } catch (Exception $e) {
        error_log('Failed to retrieve payment by id: ' . $e->getMessage());
    }

    if (!$pgPaymentData) {
        error_log('Payment record not found for id: ' . $paymentId);
        ob_end_clean();
        billingPgRedirect(array(
            'payment_status' => 'failed',
            'payment_message' => 'payment_not_found'
        ));
    }
    
    // Get organization ID from payment data (don't rely on session)
    $orgId = isset($pgPaymentData['btr_org_id']) ? (int)$pgPaymentData['btr_org_id'] : null;

    // Extract and format transaction date
    $transDate = $received['trans_date'] ?? '';
    $formattedTransDate = null;
    if ($transDate !== '') {
        $dt = DateTime::createFromFormat('d/m/Y H:i:s', $transDate);
        if ($dt !== false) {
            $formattedTransDate = $dt->format('Y-m-d H:i:s');
    } else {
            // Fallback if format doesn't match
        $formattedTransDate = date('Y-m-d H:i:s');
    }
    } else {
        $formattedTransDate = date('Y-m-d H:i:s');
    }

    // Update existing INIT row in bil_pg_payments by pg_id (order_id) and set final details
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

    if ($gDb->queryPrepared($updateSql, array(
    (!empty($trackingId) ? substr((string)$trackingId, 0, 30) : $pgPaymentData['btr_pg_id']),
    (!empty($bankRefNo) ? substr((string)$bankRefNo, 0, 255) : null),
    billingGetPaymentStatus($status),
    (!empty($amount) ? $amount : null),
    (!empty($currency) ? $currency : null),
    $received['payment_mode']   ?? null,
    ($received['status_message'] ?? $trackingId ?? null),
    $decResponse,
    (is_numeric($merchantParam) ? (int)$merchantParam : null),
    $formattedTransDate,
    $paymentId
    ), false) === false) {
        ob_end_clean();
        billingPgRedirect(array(
            'payment_status' => 'failed',
            'payment_message' => 'db_update_failed'
        ));
    }

    // Determine user_id from merchant_param
    $user_id = 0;
    if (!empty($merchantParam) && is_numeric($merchantParam)) {
        $user_id = (int) $merchantParam;
    }

    // Normalize success status
    $successIndicators = array('Success', 'SUCCESS', 'success', 'Captured', 'CAPTURED', 'AUTHORISED', 'Authorised');
    // CCAvenue generally posts order_status as Success/Aborted/Failure; other gateways sometimes post Captured/Authorised.
    $isSuccess = in_array($status, $successIndicators, true);

    // Create entries in bil_payments and bil_payment_items if payment is successful
    $pgPaymentItemData = null;

    if ($isSuccess) {
        $hasInvoicePaidColumn = columnExistsBILL(TBL_BL_INVOICES, 'biv_is_paid');
        try {
            // Retrieve the bil_pg_payment_items records
            $itemStmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_BL_TRANS_ITEMS . ' WHERE bti_pg_payment_id = ?', array($paymentId), false);
            if ($itemStmt === false) {
                throw new RuntimeException('Failed to load payment items.');
            }
            $pgPaymentItems = $itemStmt->fetchAll();

            if ($pgPaymentItems) {
                // Insert into bil_payments
                $insertPaymentSql = 'INSERT INTO ' . TBL_BL_PAYMENTS . ' (
                bpa_status, bpa_date, bpa_pg_pay_method, bpa_pay_type, bpa_trans_id, bpa_bank_ref_no, bpa_usr_id, bpa_org_id, bpa_usr_id_create, bpa_usr_id_change
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

                $paymentStatus = billingGetPaymentStatus($status);
                $merchantUser = (is_numeric($merchantParam) ? (int)$merchantParam : null);
                if ($gDb->queryPrepared($insertPaymentSql, array(
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
                ), false) === false) {
                    throw new RuntimeException('Failed to insert payment.');
        }

                // Get the inserted payment ID
                $bilPaymentId = $gDb->lastInsertId();

                // Update bil_pg_payments table with the bil_payment reference
                if ($bilPaymentId > 0) {
                    try {
                        $updatePgPaymentSql = 'UPDATE ' . TBL_BL_TRANS . ' SET
                        btr_payment_id = ?,
                        btr_usr_id_change = ?,
                        btr_timestamp_change = NOW()
                            WHERE btr_id = ?';
                        $gDb->queryPrepared($updatePgPaymentSql, array($bilPaymentId, $merchantUser, $paymentId), false);
                    } catch (Exception $e) {
                        error_log('Failed to update bil_pg_payments.bil_payment: ' . $e->getMessage());
                    }
    }

                // Insert into bil_payment_items and update invoices
                if ($bilPaymentId > 0) {
                    $insertPaymentItemSql = 'INSERT INTO ' . TBL_BL_PAYMENT_ITEMS . ' (
                            bpi_payment_id, bpi_inv_id, bpi_amount, bpi_currency, bpi_usr_id, bpi_org_id, bpi_usr_id_create, bpi_usr_id_change
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';

                    foreach ($pgPaymentItems as $item) {
                            if ($gDb->queryPrepared($insertPaymentItemSql, array(
                            $bilPaymentId,
                            $item['bti_inv_id'] ?? null,
                            $item['bti_amount'] ?? null,
                            $item['bti_currency'] ?? null,
                            $merchantUser,
                            $orgId,
                            $merchantUser,
                            $merchantUser
                            ), false) === false) {
                                throw new RuntimeException('Failed to insert payment item.');
                            }

                        // Mark invoice paid flag on success when column exists
                        if ($paymentStatus === 'SU' && $hasInvoicePaidColumn) {
                            if (!empty($item['bti_inv_id'])) {
                                try {
                                    $updateInvoiceSql = 'UPDATE ' . TBL_BL_INVOICES . ' SET biv_is_paid = ? WHERE biv_id = ?';
                    $gDb->queryPrepared($updateInvoiceSql, array(1, (int)$item['bti_inv_id']), false);
        } catch (Exception $e) {
                                    error_log('Failed to update invoice paid flag for invoice ' . $item['bti_inv_id'] . ': ' . $e->getMessage());
        }
                            }
            }
                    }
    }
            } else {
                error_log('Payment Item record not found for payment_id: ' . $paymentId);
            }
    } catch (Exception $e) {
            error_log('Failed to process payment success logic: ' . $e->getMessage());
            // Don't stop redirect, just log
    }
    }


    // Redirect user based on payment status (don't use $gMessage which requires session)
    if ($isSuccess) {
        billingPgRedirect(array(
            'payment_status' => 'success',
            'order_id' => $orderId,
            'tracking_id' => $trackingId,
            'amount' => $amount,
            'invoice_id' => $invoiceId ?: ($pgPaymentItems[0]['bti_inv_id'] ?? null)
        ));
    } else {
        billingPgRedirect(array(
            'payment_status' => 'failed',
            'order_id' => $orderId,
            'invoice_id' => $invoiceId ?: ($pgPaymentItems[0]['bti_inv_id'] ?? null),
            'payment_message' => $received['status_message'] ?? 'Unknown error'
        ));
    }
} catch (Exception $e) {
    error_log('Error processing payment response: ' . $e->getMessage());
    billingPgRedirect(array(
    'payment_status' => 'failed',
    'payment_message' => 'processing_error'
    ));
}

