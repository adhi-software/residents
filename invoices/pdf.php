<?php
/**
 ***********************************************************************************************
 * Render a printable PDF for an invoice
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
// Enforce valid login
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

global $gDb, $gL10n, $gSettingsManager, $gCurrentOrganization;

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php';
if (!isUserAuthorizedForBilling($scriptUrl)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$id = admFuncVariableIsValid($_GET, 'id', 'int');

$invoice = new TableResidentsInvoice($gDb, $id);
if ($invoice->isNewRecord()) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$inv = array(
    'biv_id' => (int)$invoice->getValue('biv_id'),
    'biv_number' => (string)$invoice->getValue('biv_number'),
    'biv_usr_id' => (int)$invoice->getValue('biv_usr_id'),
    'biv_date' => (string)$invoice->getValue('biv_date'),
    'biv_due_date' => (string)$invoice->getValue('biv_due_date'),
    'biv_notes' => (string)$invoice->getValue('biv_notes')
);

$totals = billingGetInvoiceTotals($id);
$currencyLabel = $totals['currency'] ?? $gSettingsManager->getString('system_currency');
$amountFormatted = number_format((float)$totals['amount'], 2, '.', ',');

$items = $invoice->getItems();

$customer = billingGetUserAddress((int)$inv['biv_usr_id']);
$customerName = $customer['name'] ?? $gL10n->get('SYS_USER') . ' #' . (int)$inv['biv_usr_id'];

// Helper for date formatting
function billingPdfFormatDate($value) {
    if (empty($value)) {
        return '-';
    }
    $formatted = billingFormatDateForUi($value);
    return $formatted !== '' ? $formatted : '-';
}



function billingPdfThreeDigitsToWords(int $number): string
{
    $ones = array('', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen');
    $tens = array('', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety');
    $words = '';

    if ($number >= 100) {
        $words .= $ones[intdiv($number, 100)] . ' hundred';
        $number %= 100;
        if ($number > 0) {
        $words .= ' ';
    }
    }

    if ($number >= 20) {
        $words .= $tens[intdiv($number, 10)];
        if ($number % 10 > 0) {
        $words .= '-' . $ones[$number % 10];
    }
    } elseif ($number > 0) {
        $words .= $ones[$number];
    }

    return $words;
}

function billingPdfNumberToWords(int $number): string
{
    if ($number === 0) {
        return 'zero';
    }

    $scales = array('', ' thousand', ' million', ' billion');
    $words = '';
    $scaleIndex = 0;

    while ($number > 0) {
        $chunk = $number % 1000;
        if ($chunk > 0) {
        $chunkWords = billingPdfThreeDigitsToWords($chunk);
        $words = trim($chunkWords . $scales[$scaleIndex] . ' ' . $words);
    }
        $number = intdiv($number, 1000);
        $scaleIndex++;
    }

    return trim($words);
}

function billingPdfAmountToWords(float $amount, string $currencyLabel): string
{
    $integerPart = (int)floor($amount);
    $fractionPart = (int)round(($amount - $integerPart) * 100);
    $words = billingPdfNumberToWords($integerPart);

    if ($currencyLabel !== '') {
        $words .= ' ' . trim($currencyLabel);
    }

    if ($fractionPart > 0) {
        $words .= ' and ' . billingPdfNumberToWords($fractionPart) . ' cents';
    } else {
        $words .= ' only';
    }

    return ucfirst($words);
}

$amountInWords = billingPdfAmountToWords((float)$totals['amount'], '');

// Initialize PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($gCurrentOrganization->getValue('org_longname'));
$pdf->SetTitle($gL10n->get('BL_NUMBER') . ' #' . $inv['biv_number']);
$pdf->SetSubject($gL10n->get('BL_TAB_INVOICES'));

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(TRUE, 15);

// Set font
$pdf->SetFont('dejavusans', '', 10);

// Add a page
$pdf->AddPage();

// Colors & Styling
$orange = '#d35400';
$green = '#28a745';
$red = '#dc3545';
$gray = '#555555';
$lightGray = '#f9f9f9';
$borderColor = '#eeeeee';
$teal = '#3697a8';

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

// Customer Address Block
$customerAddress = '<b>' . htmlspecialchars($customerName) . '</b><br/>';
if (!empty($customer['email'])) {
    $customerAddress .= htmlspecialchars($customer['email']) . '<br/>';
}
if (!empty($customer['tel'])) {
    $customerAddress .= htmlspecialchars($customer['tel']) . '<br/>';
}
$addressLine = '';
if (!empty($customer['address'])) {
    $addressLine .= htmlspecialchars($customer['address']);
}
if (!empty($customer['city'])) {
    if ($addressLine !== '') $addressLine .= ', ';
    $addressLine .= htmlspecialchars($customer['city']);
}
if (!empty($customer['zip'])) {
    if ($addressLine !== '') $addressLine .= ' ';
    $addressLine .= htmlspecialchars($customer['zip']);
}
if (!empty($customer['country'])) {
    if ($addressLine !== '') $addressLine .= ', ';
    $addressLine .= htmlspecialchars($customer['country']);
}
if ($addressLine !== '') {
    $customerAddress .= $addressLine . '<br/>';
}

// Items Rows
$itemsHtml = '';
$counter = 1;
if (empty($items)) {
    $itemsHtml .= '<tr><td colspan="3" align="center" style="color: #999;">' . $gL10n->get('BL_NO_DATA') . '</td></tr>';
} else {
    foreach ($items as $item) {
        $lineTotal = (float)$item['bii_amount'];

        $itemLabel = htmlspecialchars((string)($item['bii_name'] ?? ''));
        $rangeParts = array();
        if (!empty($item['bii_start_date'])) {
            $rangeParts[] = billingPdfFormatDate($item['bii_start_date']);
    }
        if (!empty($item['bii_end_date'])) {
            $rangeParts[] = billingPdfFormatDate($item['bii_end_date']);
    }
        if (!empty($rangeParts)) {
            if (count($rangeParts) === 1) {
                $itemLabel .= ' <span style="font-size:8pt; color:#666;">- ' . $rangeParts[0] . '</span>';
            } else {
                $itemLabel .= ' <span style="font-size:8pt; color:#666;">- ' . $rangeParts[0] . ' To ' . $rangeParts[1] . '</span>';
            }
    }
        $itemsHtml .= '
        <tr>
            <td width="10%" align="center" style="border-bottom: 1px solid #eee;">' . $counter++ . '</td>
            <td width="70%" align="left" style="border-bottom: 1px solid #eee;">' . $itemLabel . '</td>
            <td width="20%" align="right" style="border-bottom: 1px solid #eee;">' . $currencyLabel . ' ' . number_format($lineTotal, 2) . '</td>
        </tr>';
    }
}

// Main HTML Structure
$html = '
<style>
    .invoice-title { font-size: 10pt; color: #ffffff; text-align: right; text-transform: uppercase; }
    .org-name { font-size: 16pt; font-weight: bold; text-transform: uppercase; color: #ffffff; text-align: center; }
    .bill-to-label { font-size: 10pt; font-weight: bold; color: #000; }
    .info-label { font-size: 10pt; color: #000; }
    .info-value { font-size: 10pt; font-weight: bold; color: #000; text-align: right; }
    .table-header { background-color: #f0f0f0; font-weight: bold; text-transform: uppercase; font-size: 9pt; color: #000; }
    .total-label { font-weight: bold; font-size: 10pt; }
    .total-due-label { font-weight: bold; font-size: 10pt; color: #000; background-color: #ffffff; }
    .total-due-value { font-weight: bold; font-size: 10pt; color: #000; background-color: #ffffff; }
    .main-table { border: none; }
    .header-row { background-color: ' . $teal . '; color: #ffffff; }
    .content-cell { background-color: #ffffff; padding: 5px 15px 15px 15px; }
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
            <div class="invoice-title">' . $gL10n->get('BL_INVOICE_TITLE') . ' #' . $inv['biv_number'] . '</div>
                    </td>
    </tr>
            </table>
    </td>
    </tr>
    <tr>
    <td width="100%" class="content-cell">
            <div style="height: 5px;"></div>

            <table border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
                    <td width="50%" valign="top" align="left">
            <table border="0" cellpadding="3" cellspacing="0">
                            <tr>
        <td class="bill-to-label" style="padding-bottom: 5px;">' . $gL10n->get('BL_BILL_TO') . ':</td>
                            </tr>
                            <tr>
        <td>' . $customerAddress . '</td>
                            </tr>
            </table>
                    </td>
                    <td width="50%" valign="top" align="right">
            <table border="0" cellpadding="3" cellspacing="0" style="margin-left: auto;">
                            <tr>
        <td class="info-label" style="padding-right: 10px;">' . $gL10n->get('BL_NUMBER') . ':</td>
        <td class="info-value">' . $inv['biv_number'] . '</td>
                            </tr>
                            <tr>
        <td class="info-label" style="padding-right: 10px;">' . $gL10n->get('BL_DATE') . ':</td>
        <td class="info-value">' . billingPdfFormatDate($inv['biv_date']) . '</td>
                            </tr>
                            <tr>
        <td class="info-label" style="padding-right: 10px;">' . $gL10n->get('BL_DUE_DATE') . ':</td>
        <td class="info-value">' . billingPdfFormatDate($inv['biv_due_date']) . '</td>
                            </tr>
            </table>
                    </td>
    </tr>
            </table>

            <div style="height: 30px;"></div>

            <table border="0" cellpadding="8" cellspacing="0" width="100%">
    <thead>
                    <tr class="table-header">
            <th width="10%" align="center">#</th>
            <th width="70%" align="left">' . $gL10n->get('SYS_DESCRIPTION') . '</th>
            <th width="20%" align="right">' . $gL10n->get('BL_AMOUNT') . '</th>
                    </tr>
    </thead>
    <tbody>
                    ' . $itemsHtml . '
    </tbody>
            </table>

            <div style="height: 10px;"></div>


            <table border="0" cellpadding="5" cellspacing="0" width="100%">
    <tr>
                    <td width="80%" align="right" class="total-due-label">' . $gL10n->get('BL_TOTAL') . ' (' . $currencyLabel . '):</td>
                    <td width="20%" align="right" class="total-due-value">' . $amountFormatted . '</td>
    </tr>
            </table>

            <div style="height: 15px;"></div>

            <table border="0" cellpadding="5" cellspacing="0" width="100%">
    <tr>
                    <td width="100%">
                <div style="font-size: 10pt; text-align: justify;"><b>' . $gL10n->get('BL_AMOUNT_IN_WORDS') . ':</b> ' . $amountInWords . '</div>
                    </td>
    </tr>
    ' . (!empty($inv['biv_notes']) ? '<tr>
                    <td width="100%">
                <div style="font-size: 10pt; text-align: justify;"><b>' . $gL10n->get('BL_NOTES') . ':</b> ' . nl2br(htmlspecialchars($inv['biv_notes'])) . '</div>
                    </td>
    </tr>' : '') . '
            </table>




            <div style="height: 80px;"></div>
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
                    <td width="60%">
            <div style="font-size: 10pt; font-weight: bold;">' . $gL10n->get('BL_DATE_SIMPLE') . ': </div>
                    </td>
                    <td width="40%"></td>
    </tr>
    <tr>
                    <td width="60%">
            <div style="font-size: 10pt; font-weight: bold; padding-top: 5px;">' . $gL10n->get('BL_PLACE') . ': </div>
                    </td>
                    <td width="40%" align="right">
            <div style="font-size: 10pt; font-weight: bold; padding-top: 5px;">' . $gL10n->get('BL_AUTHORIZED_SIGNATURE') . '</div>
                    </td>
    </tr>
            </table>

    </td>
    </tr>
</table>
';

$pdf->writeHTML($html, true, false, true, false, '');

$filename = 'invoice-' . (string)($inv['biv_number'] ?: $inv['biv_id']) . '.pdf';
$pdf->Output($filename, 'D');
exit;
