<?php
/**
 ***********************************************************************************************
 * PayPal payment response handler
 *
 * This file handles the response from PayPal payment gateway and updates the database.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

ob_start();

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/paypal_common.php');

global $gDb, $gL10n, $gProfileFields;

function residentsPgRedirect(array $params): void
{
    $params['tab'] = $params['tab'] ?? 'invoices';
    $url = SecurityUtils::encodeUrl(
        ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php',
        $params
    );
    header('Location: ' . $url);
    exit;
}

$token = $_GET['token'] ?? '';
if (empty($token)) {
    error_log('PayPal Response: Missing token');
    residentsPgRedirect(['payment_status' => 'failed', 'payment_message' => 'missing_token']);
}

// 1. Get Access Token
$accessToken = paypal_get_access_token();

if (empty($accessToken)) {
    error_log('PayPal Auth Failed in Response');
    residentsPgRedirect(['payment_status' => 'failed', 'payment_message' => 'auth_failed']);
}

// 2. Capture Order
$captureRequest = paypal_capture_order($accessToken, $token);
$captureResponse = json_decode($captureRequest['body'], true);
$status = $captureResponse['status'] ?? '';
$isSuccess = ($status === 'COMPLETED');

// 3. Update Database
try {
    // Find the initiated payment record
    // In paypal_pay.php, we saved the PayPal Order ID into rtr_pg_id
    $stmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_RE_TRANS . ' WHERE rtr_pg_id = ?', array($token), false);
    $pgPaymentData = $stmt->fetch();

    if (!$pgPaymentData) {
        error_log('PayPal Response: Payment record not found for token ' . $token);
        residentsPgRedirect(['payment_status' => 'failed', 'payment_message' => 'payment_not_found']);
    }

    $paymentId = (int)$pgPaymentData['rtr_id'];
    $ownerId = (int)$pgPaymentData['rtr_usr_id'];
    $orgId = (int)$pgPaymentData['rtr_org_id'];
    $amount = $captureResponse['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? $pgPaymentData['rtr_amount'];
    $currency = $pgPaymentData['rtr_currency'];
    $trackingId = $captureResponse['purchase_units'][0]['payments']['captures'][0]['id'] ?? '';

    // Update TBL_RE_TRANS
    $updateSql = 'UPDATE ' . TBL_RE_TRANS . ' SET
            rtr_status = ?,
            rtr_pg_response = ?,
            rtr_pg_msg = ?,
            rtr_usr_id_change = ?,
            rtr_pg_trans_date = ?,
            rtr_timestamp_change = ?
    WHERE rtr_id = ?';

    $gDb->queryPrepared($updateSql, array(
        ($isSuccess ? 'SU' : 'FA'),
        $captureRequest['body'],
        $status,
        $ownerId,
        DATETIME_NOW,
        DATETIME_NOW,
        $paymentId
    ), false);

    if ($isSuccess) {
        // Create entries in bil_payments and bil_payment_items
        $itemStmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_RE_TRANS_ITEMS . ' WHERE rti_pg_payment_id = ?', array($paymentId), false);
        $pgPaymentItems = $itemStmt->fetchAll();

        if ($pgPaymentItems) {
            $insertPaymentSql = 'INSERT INTO ' . TBL_RE_PAYMENTS . ' (
                rpa_status, rpa_date, rpa_pg_pay_method, rpa_pay_type, rpa_trans_id, rpa_usr_id, rpa_org_id, rpa_usr_id_create, rpa_usr_id_change,
                rpa_timestamp_create, rpa_timestamp_change
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

            $gDb->queryPrepared($insertPaymentSql, array(
                'SU', DATETIME_NOW, 'PayPal', 'Online', $trackingId, $ownerId, $orgId, $ownerId, $ownerId, DATETIME_NOW, DATETIME_NOW
            ), false);

            $bilPaymentId = $gDb->lastInsertId();

            if ($bilPaymentId > 0) {
                // Link bil_pg_payments to bil_payments
                $gDb->queryPrepared('UPDATE ' . TBL_RE_TRANS . ' SET rtr_payment_id = ? WHERE rtr_id = ?', array($bilPaymentId, $paymentId), false);

                $insertPaymentItemSql = 'INSERT INTO ' . TBL_RE_PAYMENT_ITEMS . ' (
                    rpi_payment_id, rpi_inv_id, rpi_amount, rpi_currency, rpi_usr_id, rpi_org_id, rpi_usr_id_create, rpi_usr_id_change
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';

                foreach ($pgPaymentItems as $item) {
                    $gDb->queryPrepared($insertPaymentItemSql, array(
                        $bilPaymentId, $item['rti_inv_id'], $item['rti_amount'], $item['rti_currency'],
                        $ownerId, $orgId, $ownerId, $ownerId
                    ), false);

                    // Mark invoice as paid
                    $gDb->queryPrepared('UPDATE ' . TBL_RE_INVOICES . ' SET riv_is_paid = 1 WHERE riv_id = ?', array((int)$item['rti_inv_id']), false);
                }
            }
        }

        residentsPgRedirect([
            'payment_status' => 'success',
            'order_id' => $token,
            'amount' => $amount,
            'currency' => $currency
        ]);
    } else {
        residentsPgRedirect([
            'payment_status' => 'failed',
            'order_id' => $token,
            'payment_message' => $status ?: 'PayPal capture failed'
        ]);
    }
} catch (\Throwable $e) {
    error_log('PayPal Response Error: ' . $e->getMessage());
    residentsPgRedirect(['payment_status' => 'failed', 'payment_message' => 'processing_error']);
}
