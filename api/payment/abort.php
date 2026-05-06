<?php
/**
 * Mobile Payment Abort API
 *
 * Cancels any initiated (IT status) payment transactions for the given invoices.
 * Called when the user manually closes the payment WebView or an error occurs.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

require_once __DIR__ . '/../../../../system/common.php';
require_once __DIR__ . '/../../common_function.php';

header('Content-Type: application/json; charset=utf-8');

global $gCurrentUser, $gDb, $gCurrentOrgId;

$gCurrentUser = validateApiKey();
$userId = (int)$gCurrentUser->getValue('usr_id');

$invoiceIds = $_POST['invoice_ids'] ?? [];
$invoiceIds = array_map('intval', (array)$invoiceIds);

if (empty($invoiceIds)) {
    echo json_encode(['success' => false, 'error' => 'No invoice IDs provided']);
    exit;
}

$aborted = 0;

try {
    // Find all IT (initiated) payments for these invoices belonging to this user
    $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
    $sql = 'SELECT DISTINCT p.rtr_id
            FROM ' . TBL_RE_TRANS . ' p
            JOIN ' . TBL_RE_TRANS_ITEMS . ' i ON i.rti_pg_payment_id = p.rtr_id
            WHERE p.rtr_status = ?
            AND p.rtr_usr_id = ?
            AND p.rtr_org_id = ?
            AND i.rti_inv_id IN (' . $placeholders . ')';

    $params = array_merge(['IT', $userId, $gCurrentOrgId], $invoiceIds);
    $stmt = $gDb->queryPrepared($sql, $params, false);

    if ($stmt !== false) {
        while ($row = $stmt->fetch()) {
            $paymentId = (int)$row['rtr_id'];
            $gDb->queryPrepared(
                'UPDATE ' . TBL_RE_TRANS . ' SET rtr_status = ?, rtr_pg_msg = ?, rtr_usr_id_change = ?, rtr_timestamp_change = ? WHERE rtr_id = ?',
                ['AB', 'Cancelled by user (Mobile - WebView closed)', $userId, DATETIME_NOW, $paymentId],
                false
            );
            $aborted++;
        }
    }
} catch (\Throwable $e) {
    error_log('Mobile Payment Abort Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Internal error']);
    exit;
}

echo json_encode(['success' => true, 'aborted' => $aborted]);
