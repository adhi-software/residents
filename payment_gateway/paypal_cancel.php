<?php
/**
 ***********************************************************************************************
 * PayPal payment cancel handler
 *
 * This file handles the cancellation of a PayPal payment.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/paypal_config.php');

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
    residentsPgRedirect(['payment_status' => 'failed', 'payment_message' => 'cancelled']);
}

try {
    // Find the initiated payment record
    $stmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_RE_TRANS . ' WHERE rtr_pg_id = ?', array($token), false);
    $pgPaymentData = $stmt->fetch();

    if ($pgPaymentData) {
        $paymentId = (int)$pgPaymentData['rtr_id'];
        $ownerId = (int)$pgPaymentData['rtr_usr_id'];

        // Update TBL_RE_TRANS status to AB (Aborted)
        $updateSql = 'UPDATE ' . TBL_RE_TRANS . ' SET
                rtr_status = ?,
                rtr_pg_msg = ?,
                rtr_usr_id_change = ?,
                rtr_timestamp_change = ?
        WHERE rtr_id = ?';

        $gDb->queryPrepared($updateSql, array(
            'AB',
            'Cancelled by user',
            $ownerId,
            DATETIME_NOW,
            $paymentId
        ), false);
    }
} catch (\Throwable $e) {
    error_log('PayPal Cancel Error: ' . $e->getMessage());
}

residentsPgRedirect([
    'payment_status' => 'failed',
    'payment_message' => 'cancelled'
]);
