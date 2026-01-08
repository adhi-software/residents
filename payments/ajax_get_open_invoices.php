<?php
/**
    * AJAX script to fetch open invoices for a user.
    */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');

global $gDb, $gL10n;

// Check if user has rights (same as edit.php)
$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php';
if (!isUserAuthorizedForBilling($scriptUrl)) {
    http_response_code(403);
    echo json_encode(['error' => $gL10n->get('SYS_NO_RIGHTS')]);
    exit;
}

$userId = admFuncVariableIsValid($_GET, 'usr_id', 'int');

if ($userId <= 0) {
    echo json_encode([]);
    exit;
}

// Fetch open invoices via model helper
$openInvoices = TableResidentsInvoice::fetchOpenInvoicesByUser($gDb, $userId);
$invoices = array();

foreach ($openInvoices as $row) {
    $totals = billingGetInvoiceTotals((int)$row['biv_id']);
    $invoices[] = array(
    'id' => (int)$row['biv_id'],
    'number' => $row['biv_number'],
    'date' => $row['biv_date'],
    'amount' => $totals['amount'],
    'currency' => $totals['currency']
    );
}

header('Content-Type: application/json');
echo json_encode($invoices);
