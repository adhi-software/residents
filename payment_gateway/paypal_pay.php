<?php
/**
 ***********************************************************************************************
 * PayPal redirect - Initiates a PayPal order and redirects the user
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
if (file_exists(__DIR__ . '/../../../system/login_valid.php')) {
    require_once(__DIR__ . '/../../../system/login_valid.php');
} else {
    require_once(__DIR__ . '/../../../system/login_valid.php');
}
require_once(__DIR__ . '/paypal_common.php');

global $gDb, $gProfileFields, $gL10n, $gSettingsManager, $gCurrentUserId, $gCurrentUser, $gCurrentOrganization, $gCurrentOrgId;

// SECURITY: Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_ids'])) {
    try {
        SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token'] ?? '');
    } catch (AdmException $e) {
        $gMessage->show($gL10n->get('SYS_INVALID_CSRF_TOKEN'));
    }
}

// Validate Configuration
if (empty(PAYPAL_CLIENT_ID) || empty(PAYPAL_CLIENT_SECRET) || empty(PAYPAL_API_URL)) {
    $gMessage->show($gL10n->get('RE_PG_CONFIG_MISSING'));
}

$invoiceIds = array();
if (isset($_POST['invoice_ids']) && is_array($_POST['invoice_ids'])) {
    $invoiceIds = array_map('intval', $_POST['invoice_ids']);
} elseif (isset($_GET['invoice_id'])) {
    $invoiceIds[] = admFuncVariableIsValid($_GET, 'invoice_id', 'int');
}
$invoiceIds = array_filter($invoiceIds, function($id) { return $id > 0; });

if (empty($invoiceIds)) {
    $gMessage->show($gL10n->get('RE_NO_DATA'));
}

// Validate all invoices belong to user and are open
$totalAmount = 0.0;
$currency = '';
$ownerId = 0;

foreach ($invoiceIds as $invId) {
    $invoiceStmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_RE_INVOICES . ' WHERE riv_id = ? AND riv_org_id = ?', array($invId, (int)$gCurrentOrgId), false);
    if ($invoiceStmt === false) {
        $gMessage->show($gL10n->get('SYS_DATABASE_ERROR'));
    }
    $invoice = $invoiceStmt->fetch();
    
    if (!$invoice) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
    
    if ((int)$invoice['riv_is_paid'] === 1) {
        $gMessage->show($gL10n->get('RE_INVOICE_ALREADY_PAID') . ' (ID: ' . $invId . ')');
    }
    
    if ($ownerId === 0) {
        $ownerId = (int)$invoice['riv_usr_id'];
    } elseif ($ownerId !== (int)$invoice['riv_usr_id']) {
        $gMessage->show($gL10n->get('RE_PG_INVOICES_SAME_USER'));
    }
    
    $totals = residentsGetInvoiceTotals($invId);
    $totalAmount += (float)$totals['amount'];
    $currency = $totals['currency'];
}

if ($totalAmount <= 0) {
    $gMessage->show($gL10n->get('RE_PAYMENT_FAILED') . ' (Total Amount: ' . $totalAmount . ')');
}

$isAdmin = isResidentsAdmin();
if (!$isAdmin && $ownerId !== (int)$gCurrentUser->getValue('usr_id')) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Mark OLD initiated payments as TIMEOUT first
residentsCheckPaymentTimeouts();

// Check for recent initiated payments
$timeoutMins = (int)PAYPAL_TIMEOUT;
$timeoutTime = date('Y-m-d H:i:s', strtotime('-' . $timeoutMins . ' minutes'));

$initiatedInvoices = array();
foreach ($invoiceIds as $invId) {
    $checkRecentSql = 'SELECT COUNT(*)
            FROM ' . TBL_RE_TRANS_ITEMS . ' i
            JOIN ' . TBL_RE_TRANS . ' p ON p.rtr_id = i.rti_pg_payment_id
            WHERE i.rti_inv_id = ? AND p.rtr_status = ? AND p.rtr_timestamp_create >= ?';
    $checkRecentStmt = $gDb->queryPrepared($checkRecentSql, array($invId, 'IT', $timeoutTime), false);
    if ($checkRecentStmt && $checkRecentStmt->fetchColumn() > 0) {
        $invNumStmt = $gDb->queryPrepared('SELECT riv_number FROM ' . TBL_RE_INVOICES . ' WHERE riv_id = ?', array($invId), false);
        $invNum = $invNumStmt->fetchColumn();
        $initiatedInvoices[] = $invNum;
    }
}

if (!empty($initiatedInvoices)) {
    $gMessage->show(sprintf($gL10n->get('RE_PAYMENT_ALREADY_INITIATED'), $timeoutMins) . ' (Invoices: ' . implode(', ', $initiatedInvoices) . ')');
}

// Check if already paid
$alreadyPaidInvoices = array();
foreach ($invoiceIds as $invId) {
    $checkPaidSql = 'SELECT COUNT(*)
            FROM ' . TBL_RE_TRANS_ITEMS . ' i
            JOIN ' . TBL_RE_TRANS . ' p ON p.rtr_id = i.rti_pg_payment_id
            WHERE i.rti_inv_id = ? AND p.rtr_status = ?';
    $checkPaidStmt = $gDb->queryPrepared($checkPaidSql, array($invId, 'SU'), false);
    if ($checkPaidStmt && $checkPaidStmt->fetchColumn() > 0) {
        $invNumStmt = $gDb->queryPrepared('SELECT riv_number FROM ' . TBL_RE_INVOICES . ' WHERE riv_id = ?', array($invId), false);
        $invNum = $invNumStmt->fetchColumn();
        $alreadyPaidInvoices[] = $invNum;
    }
}

if (!empty($alreadyPaidInvoices)) {
    $gMessage->show($gL10n->get('RE_PAYMENT_ALREADY_PAID') . ' (Invoices: ' . implode(', ', $alreadyPaidInvoices) . ')');
}

// PERSIST Record in bil_pg_payments
$paymentId = 0;
$insertSql = 'INSERT INTO ' . TBL_RE_TRANS . ' (
        rtr_pg_id, rtr_status,
        rtr_amount, rtr_currency, rtr_payment_id, rtr_usr_id, rtr_org_id,
        rtr_pg_pay_method, rtr_pg_msg, rtr_pg_trans_date, rtr_pg_request,
        rtr_pg_response, rtr_usr_id_create, rtr_usr_id_change
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

if ($gDb->queryPrepared($insertSql, array(
    null, 'IT', number_format($totalAmount, 2, '.', ''), $currency, null,
    $ownerId, $gCurrentOrgId, 'PayPal', 'Invoice Payment', null, null, null,
    $ownerId, $ownerId
), false) !== false) {
    $paymentId = (int)$gDb->lastInsertId();
}

if ($paymentId > 0) {
    $insertItemSql = 'INSERT INTO ' . TBL_RE_TRANS_ITEMS . ' (
        rti_pg_payment_id, rti_inv_id,
        rti_amount, rti_currency, rti_usr_id, rti_org_id, rti_usr_id_create, rti_usr_id_change
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';

    foreach ($invoiceIds as $invId) {
        $invTotal = residentsGetInvoiceTotals($invId);
        $gDb->queryPrepared($insertItemSql, array(
            $paymentId, $invId, number_format((float)$invTotal['amount'], 2, '.', ''), $currency,
            $ownerId, $gCurrentOrgId, $ownerId, $ownerId
        ), false);
    }
}

if ($paymentId <= 0) {
    $gMessage->show($gL10n->get('RE_PAYMENT_FAILED'));
}

// 1. Get PayPal Access Token
$accessToken = paypal_get_access_token();

if (empty($accessToken)) {
    $gMessage->show($gL10n->get('RE_PAYMENT_FAILED') . ' (Auth Error)');
}

// Get User Address for PayPal
$addr = residentsGetUserAddress($ownerId);
$currencyCode = paypal_map_currency($currency);

// Sanitize names
$firstName = paypal_sanitize_name(is_object($gCurrentUser) && (int)$gCurrentUser->getValue('usr_id') === $ownerId ? $gCurrentUser->getValue('FIRST_NAME') : 'First');
$lastName = paypal_sanitize_name(is_object($gCurrentUser) && (int)$gCurrentUser->getValue('usr_id') === $ownerId ? $gCurrentUser->getValue('LAST_NAME') : 'Last');
if (empty($firstName)) $firstName = 'First';
if (empty($lastName)) $lastName = 'Last';

// 2. Create PayPal Order
$defaultResponseUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payment_gateway/paypal_response.php';
$defaultCancelUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payment_gateway/paypal_cancel.php';
$returnUrl = !empty($pgConf['redirect_url']) ? $pgConf['redirect_url'] : $defaultResponseUrl;
$cancelUrl = !empty($pgConf['cancel_url']) ? $pgConf['cancel_url'] : $defaultCancelUrl;

$orderData = [
    'intent' => 'CAPTURE',
    'payment_source' => [
        'paypal' => [
            'experience_context' => [
                'brand_name' => substr((is_object($gCurrentOrganization) ? $gCurrentOrganization->getValue('org_longname') : 'Admidio'), 0, 127),
                'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                'landing_page' => 'NO_PREFERENCE',
                'user_action' => 'PAY_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl
            ],
            'email_address' => filter_var($addr['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $addr['email'] : null,
            'name' => [
                'given_name' => $firstName,
                'surname' => $lastName
            ]
        ]
    ],
    'purchase_units' => [
        [
            'custom_id' => (string)$paymentId,
            'amount' => [
                'currency_code' => $currencyCode,
                'value' => number_format($totalAmount, 2, '.', '')
            ]
        ]
    ]
];

// Remove null fields
$orderData['payment_source']['paypal'] = array_filter($orderData['payment_source']['paypal']);

$orderRequest = paypal_create_order($accessToken, $orderData);
$orderResponse = json_decode($orderRequest['body'], true);

if ($orderRequest['code'] === 200 || $orderRequest['code'] === 201) {
    $approvalUrl = '';
    foreach ($orderResponse['links'] as $link) {
        if ($link['rel'] === 'payer-action' || $link['rel'] === 'approve') {
            $approvalUrl = $link['href'];
            break;
        }
    }
    
    $paypalOrderId = $orderResponse['id'];
    
    // Update payment record with PayPal Order ID
    $gDb->queryPrepared(
        'UPDATE ' . TBL_RE_TRANS . ' SET rtr_pg_id = ?, rtr_pg_request = ?, rtr_timestamp_change = NOW() WHERE rtr_id = ?',
        array($paypalOrderId, json_encode($orderData), $paymentId),
        false
    );
    
    if (!empty($approvalUrl)) {
        header('Location: ' . $approvalUrl);
        exit;
    }
}

error_log('PayPal Order Creation Failed: Code ' . $orderRequest['code'] . ' Body: ' . $orderRequest['body']);
$errorMsg = 'PayPal API Error (Code ' . $orderRequest['code'] . ')';
if (!empty($orderResponse['message'])) {
    $errorMsg .= ': ' . $orderResponse['message'];
}
if (!empty($orderResponse['details'])) {
    foreach ($orderResponse['details'] as $detail) {
        $errorMsg .= '<br>- ' . ($detail['field'] ?? '') . ': ' . ($detail['issue'] ?? '') . ' (' . ($detail['description'] ?? '') . ')';
    }
} else {
    $errorMsg .= '<br>Response Body: ' . htmlspecialchars($orderRequest['body']);
}
$gMessage->show($gL10n->get('RE_PAYMENT_FAILED') . '<br>' . $errorMsg);
