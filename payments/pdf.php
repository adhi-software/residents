<?php
/**
 ***********************************************************************************************
 * Export payment receipt as PDF
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
// Check if we are in API mode (API Key provided)
$useApiAuth = false;
if (isset($_SERVER['HTTP_API_KEY']) && !empty($_SERVER['HTTP_API_KEY'])) {
    $useApiAuth = true;
} else {
    $headers = function_exists('getallheaders') ? getallheaders() : array();
    foreach ($headers as $headerName => $headerValue) {
        if (strcasecmp((string)$headerName, 'api_key') === 0 || strcasecmp((string)$headerName, 'apikey') === 0 || strcasecmp((string)$headerName, 'api-key') === 0) {
            if (!empty($headerValue)) {
                $useApiAuth = true;
            }
            break;
    }
    }
}

if ($useApiAuth) {
    // API Mode: correct validateApiKey will exit if key is invalid
    validateApiKey();
} else {
    // Browser Mode: Enforce valid login
    if (file_exists(__DIR__ . '/../../../system/login_valid.php')) {
        require_once(__DIR__ . '/../../../system/login_valid.php');
    } else {
        require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');
    }
}

// Include TCPDF
if (file_exists(__DIR__ . '/../../../adm_program/libs/server/tecnickcom/tcpdf/tcpdf.php')) {
    require_once(__DIR__ . '/../../../adm_program/libs/server/tecnickcom/tcpdf/tcpdf.php');
} else {
    die('TCPDF library not found.');
}

global $gDb, $gL10n, $gProfileFields, $gCurrentUser, $gCurrentOrganization, $gSettingsManager;

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php';
if (!isUserAuthorizedForBilling($scriptUrl)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$id = admFuncVariableIsValid($_GET, 'id', 'int');
$isAdmin = isBillingAdmin();

// Fetch payment via TableAccess model
$paymentRecord = new TableResidentsPayment($gDb, $id);
if ($paymentRecord->isNewRecord()) {
    die($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$ownerId = (int)$paymentRecord->getValue('bpa_usr_id');
if (!$isAdmin && $ownerId !== (int)$gCurrentUser->getValue('usr_id')) {
    die($gL10n->get('SYS_NO_RIGHTS'));
}

$paymentData = array(
    'bpa_id' => (int)$paymentRecord->getValue('bpa_id'),
    'bpa_date' => date('d.m.Y H:i', strtotime((string)$paymentRecord->getValue('bpa_date'))),
    'bpa_status' => (string)$paymentRecord->getValue('bpa_status'),
    'bpa_trans_id' => (string)$paymentRecord->getValue('bpa_trans_id'),
    'bpa_bank_ref_no' => (string)$paymentRecord->getValue('bpa_bank_ref_no'),
    'bpa_pg_pay_method' => (string)$paymentRecord->getValue('bpa_pg_pay_method'),
    'bpa_usr_id' => $ownerId,
    'user_name' => $ownerId > 0 ? billingFetchUserNameById($ownerId) : '',
    'user_email' => $ownerId > 0 ? billingFetchUserEmailById($ownerId) : '',
    'user_address' => ''
);

// Fetch customer address
if ($ownerId > 0) {
    $customer = billingGetUserAddress($ownerId);
    $addressParts = array();
    if (!empty($customer['address'])) {
        $addressParts[] = $customer['address'];
    }
    if (!empty($customer['city'])) {
        $addressParts[] = $customer['city'];
    }
    if (!empty($customer['zip'])) {
        $addressParts[] = $customer['zip'];
    }
    if (!empty($customer['country'])) {
        $addressParts[] = $customer['country'];
    }
    $paymentData['user_address'] = implode(', ', $addressParts);
}

$transactionRecord = new TableResidentsTransaction($gDb);
if ($transactionRecord->readDataByColumns(array('btr_payment_id' => $paymentData['bpa_id']))) {
    if ($paymentData['bpa_trans_id'] === '') {
        $paymentData['bpa_trans_id'] = (string)$transactionRecord->getValue('btr_pg_id');
    }
    if ($paymentData['bpa_bank_ref_no'] === '') {
        $paymentData['bpa_bank_ref_no'] = (string)$transactionRecord->getValue('btr_bank_ref_no');
    }
}

$itemRows = $paymentRecord->getItems(true);
$invoiceNumbers = array();
$total = 0.0;
$currency = '';

foreach ($itemRows as $item) {
    $total += (float)$item['bpi_amount'];
    if ($currency === '' && !empty($item['bpi_currency'])) {
        $currency = $item['bpi_currency'];
    }
    if (!empty($item['biv_number'])) {
        $invoiceNumbers[] = $item['biv_number'];
    }
}

$invoiceNoStr = implode(', ', array_unique($invoiceNumbers));

// Build invoice item descriptions with month/year
$itemDescriptions = array();
foreach ($itemRows as $item) {
    if (!empty($item['bpi_inv_id'])) {
        $invoiceObj = new TableResidentsInvoice($gDb, (int)$item['bpi_inv_id']);
        $invoiceItems = $invoiceObj->getItems();
        foreach ($invoiceItems as $invItem) {
            $desc = !empty($invItem['bii_name']) ? $invItem['bii_name'] : '';
            $sortDate = !empty($invItem['bii_start_date']) ? $invItem['bii_start_date'] : '9999-12-31';
            if (!empty($invItem['bii_start_date'])) {
                $desc .= ' (' . date('M Y', strtotime($invItem['bii_start_date'])) . ')';
            }
            if (!empty($desc)) {
                $itemDescriptions[$sortDate . '_' . $desc] = $desc;
            }
    }
    }
}
ksort($itemDescriptions);
$itemDescList = array();
foreach (array_unique($itemDescriptions) as $desc) {
    $itemDescList[] = '- ' . $desc;
}
$itemDescStr = !empty($itemDescList) ? implode(',<br/>', $itemDescList) : '';
if (strlen(strip_tags($itemDescStr)) > 300) {
    $itemDescStr = substr(strip_tags($itemDescStr), 0, 300) . '...';
}

if ($currency === '') {
    $currency = $gSettingsManager->getString('system_currency');
}

// Initialize PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($gCurrentOrganization->getValue('org_longname'));
$pdf->SetTitle('Payment Receipt #' . $paymentData['bpa_id']);
$pdf->SetSubject('Payment Receipt');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Set font to support special characters (e.g. Rupee symbol)
$pdf->SetFont('dejavusans', '', 10);

// Add a page
$pdf->AddPage();

// Colors
$orange = '#d35400';
$green = '#28a745';
$red = '#dc3545';
$gray = '#555555';
$lightGray = '#f9f9f9';
$teal = '#3697a8';

$statusColor = ($paymentData['bpa_status'] === 'SU') ? $green : $red;
$statusLabel = billingGetPaymentStatusLabel($paymentData['bpa_status']);

// Logo
$orgId = isset($gCurrentOrganization) ? (int)$gCurrentOrganization->getValue('org_id') : 0;
$logoPath = '';
if ($orgId > 0) {
    $customLogoPath = ADMIDIO_PATH . FOLDER_DATA . '/residents/org_logo_' . $orgId . '.png';
    if (file_exists($customLogoPath)) {
        $logoPath = $customLogoPath;
    }
}
$orgName = $gCurrentOrganization->getValue('org_longname');
$orgWebsite = $gCurrentOrganization->getValue('org_homepage');

// Build HTML content for TCPDF
$html = '
<style>
    .header { color: ' . $orange . '; font-size: 18pt; font-weight: bold; }
    .message { background-color: ' . $lightGray . '; color: #333; padding: 10px; border-left: 4px solid ' . $orange . '; font-size: 10pt; }
    .section-header { font-size: 10pt; font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px; text-align: center; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 8px; border-bottom: 1px solid #eee; font-size: 10pt; }
    .label { font-weight: bold; color: #000; width: 40%; }
    .value { color: #000; width: 60%; text-align: right; font-weight: bold; }
    .status { color: ' . $statusColor . '; font-weight: bold; }
    .org-name { font-size: 16pt; font-weight: bold; text-transform: uppercase; text-align: center; color: #ffffff; }
    .receipt-title { font-size: 10pt; text-align: right; margin-top: 5px; color: #ffffff; text-transform: uppercase; }
    .welcome-msg { text-align: center; margin-top: 15px; font-size: 10pt; }
    .main-table { border: none; }
    .header-row { background-color: ' . $teal . '; color: #ffffff; }
    .content-cell { background-color: #ffffff; padding: 15px; border-bottom: none; }
</style>

<table border="0" width="100%" cellpadding="0" cellspacing="0" class="main-table">
    <tr class="header-row">
    <td width="100%">
            <table border="0" width="100%" cellpadding="3" cellspacing="0">
        <tr>
                    <td width="15%" align="left" valign="middle" style="border-bottom: none;">
            <img src="' . $logoPath . '" width="50" />
                    </td>
                    <td width="70%" align="center" valign="middle" style="border-bottom: none;">
            <div class="org-name" style="line-height: 1.2;">' . $orgName . '</div>
                    </td>
                    <td width="15%" style="border-bottom: none;"></td>
        </tr>
        <tr>
                    <td width="50%" align="left" valign="middle" style="border-bottom: none;">
                <div style="font-size: 8pt; color: #ffffff; font-weight: bold;">
                            <u>' . $orgWebsite . '</u>
                </div>
                    </td>
                    <td width="50%" align="right" valign="middle" style="border-bottom: none;" colspan="2">
            <div class="receipt-title">' . $gL10n->get('BL_PAYMENT_RECEIPT_TITLE') . '</div>
                    </td>
        </tr>
            </table>
    </td>
    </tr>
    <tr>
    <td width="100%" class="content-cell">


            <div class="section-header">' . $gL10n->get('BL_RECEIPT_DETAILS') . '</div>
            <br/>

            <table cellpadding="5" width="100%">
        <tr>
                    <td width="50%" valign="top">
            <table cellpadding="5" width="100%">
                            <tr>
                <td class="label">Receipt No</td>
                <td class="value">' . $paymentData['bpa_id'] . '</td>
                            </tr>
                            <tr>
                <td class="label">Invoice No</td>
                <td class="value">' . ($invoiceNoStr ?: '-') . '</td>
                            </tr>
                            <tr>
                <td class="label">' . $gL10n->get('BL_AMOUNT') . '</td>
                <td class="value">' . $currency . ' ' . number_format($total, 2, '.', '') . '</td>
                            </tr>
                            <tr>
                <td class="label">' . $gL10n->get('BL_PAYMENT_DATE') . '</td>
                <td class="value">' . $paymentData['bpa_date'] . '</td>
                            </tr>
                            <tr>
                <td class="label">' . $gL10n->get('BL_PAYMENT_METHOD') . '</td>
                <td class="value">' . $paymentData['bpa_pg_pay_method'] . '</td>
                            </tr>
                            <tr>
                <td class="label">Bank Ref No</td>
                <td class="value">' . ($paymentData['bpa_bank_ref_no'] ?? '-') . '</td>
                            </tr>
            </table>
                    </td>
                    <td width="50%" valign="top">
            <table cellpadding="5" width="100%">
                            <tr>
                <td class="label">' . $gL10n->get('BL_CUSTOMER') . '</td>
                <td class="value">' . ($paymentData['user_name'] ?? '') . '</td>
                            </tr>
                            <tr>
                <td class="label">' . $gL10n->get('SYS_EMAIL') . '</td>
                <td class="value">' . ($paymentData['user_email'] ?? '') . '</td>
                            </tr>
                            <tr>
                <td class="label">' . $gL10n->get('SYS_ADDRESS') . '</td>
                <td class="value">' . ($paymentData['user_address'] ?? '-') . '</td>
                            </tr>
                            <tr>
                <td class="label">' . $gL10n->get('BL_TRANSACTION_ID') . '</td>
                <td class="value">' . ($paymentData['bpa_trans_id'] ?? '-') . '</td>
                            </tr>
            </table>
                    </td>
        </tr>
            </table>

            <div style="height: 10px;"></div>
            <div style="text-align: left; font-size: 10pt;">
        <b>Thank you for your payment towards:</b><br/>
        ' . $itemDescStr . '
            </div>
    </td>
    </tr>
</table>
';

$pdf->writeHTML($html, true, false, true, false, '');

// Output
$pdf->Output('payment_receipt_' . $paymentData['bpa_id'] . '.pdf', 'D');
