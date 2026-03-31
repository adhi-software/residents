<?php
/**
 ***********************************************************************************************
 * Page to select payment gateway after confirming invoices
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

global $gDb, $gCurrentUser, $gL10n, $gSettingsManager;

$page = new HtmlPage('plg-re-select-gateway', $gL10n->get('RE_SELECT_PAYMENT_GATEWAY'));

// Collect invoice ids from POST
$incomingInvoiceIds = $_POST['invoice_ids'] ?? array();
$selectedInvoiceIds = array_unique(array_filter(array_map('intval', $incomingInvoiceIds), function ($id) {
    return $id > 0;
}));

if (empty($selectedInvoiceIds)) {
    // If no invoices selected, redirect back
    admFuncRedirect(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payment_gateway/confirm_pay.php');
}

// Fetch selected invoices for summary is no longer needed but we keep the loop for total calculation
$userId = (int)$gCurrentUser->getValue('usr_id');

$page->addHtml('<div class="card">
    <div class="card-body">
    <form action="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payment_gateway/ccavenue_pay.php') . '" method="post" id="select_gateway_form">
            <input type="hidden" name="admidio-csrf-token" value="' . $gCurrentSession->getCsrfToken() . '" />');

// Pass along the invoice IDs as hidden inputs
foreach ($selectedInvoiceIds as $id) {
    $page->addHtml('<input type="hidden" name="invoice_ids[]" value="' . (int)$id . '" />');
}

$grandTotal = 0;
$currencySymbol = $gSettingsManager->getString('system_currency');

foreach ($selectedInvoiceIds as $invId) {
    $totals = residentsGetInvoiceTotals($invId);
    $amount = (float)$totals['amount'];
    $currency = $totals['currency'];
    
    if (!empty($currency)) {
        $currencySymbol = $currency;
    }
    
    $grandTotal += $amount;
}

$config = residentsReadConfig();
$gateways = $config['payment_gateways'] ?? array();

if (count($gateways) === 1) {
    $idx = array_key_first($gateways);
    $gw = $gateways[$idx];
    $name = strtoupper($gw['name'] ?? '');
    $actionFile = strtolower($name) . '_pay.php';

    $page->addHtml('
        <div class="card"><div class="card-body text-center p-5">
            <div class="spinner-border text-primary mb-4" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h5 class="mb-3">' . $gL10n->get('SYS_WAIT_RELOAD') . '</h5>
            <p class="text-muted">' . $gL10n->get('RE_TOTAL_PAYABLE') . ': ' . $currencySymbol . ' ' . number_format($grandTotal, 2) . '</p>
            <p class="small text-muted mt-4">Connecting to ' . $name . '...</p>

            <form action="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/payment_gateway/' . $actionFile) . '" method="post" id="auto_submit_form">
                <input type="hidden" name="admidio-csrf-token" value="' . $gCurrentSession->getCsrfToken() . '" />');

    foreach ($selectedInvoiceIds as $id) {
        $page->addHtml('<input type="hidden" name="invoice_ids[]" value="' . (int)$id . '" />');
    }

    $page->addHtml('
                <input type="hidden" name="payment_gateway" value="0" />
            </form>
            <script>document.getElementById("auto_submit_form").submit();</script>
        </div></div>');

    $page->show();
    exit();
}

$page->addHtml('
            <div class="mt-4 mb-4 d-flex flex-column align-items-center" id="gateway_selector_section">
                <h6 class="mb-5 text-muted">' . $gL10n->get('RE_TOTAL_PAYABLE') . ': <span class="fw-bold text-dark">' . $currencySymbol . ' ' . number_format($grandTotal, 2) . '</span></h6>
                <div class="d-flex flex-wrap gap-4 mt-2 justify-content-center">');

foreach ($gateways as $idx => $gw) {
    $name = $gw['name'];
    $logoUrl = "";
    if ($name === "CCAVENUE") {
        $logoUrl = "https://www.ccavenue.com/images/ccavenue_logo.gif";
    } elseif ($name === "PAYPAL") {
        $logoUrl = "https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_37x23.jpg";
    }
    
    $logoSrc = ($name === "CCAVENUE") ? ADMIDIO_URL . '/adm_plugins/residents/payment_gateway/ccavenue_logo.png' : $logoUrl;
    $scaleStyle = ($name === "CCAVENUE") ? 'transform: scale(3.5);' : '';
    
    $page->addHtml('
                    <div class="form-check gateway-option p-0">
                        <input class="form-check-input d-none pg-radio" type="radio" name="payment_gateway" id="pg_' . $idx . '" value="' . $idx . '" data-action="' . strtolower($name) . '_pay.php" required>
                        <label class="form-check-label d-flex align-items-center border rounded p-4 bg-white" for="pg_' . $idx . '" style="min-width: 300px; cursor: pointer; transition: all 0.2s ease-in-out; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                            <div class="d-flex align-items-center w-100">
                                <i class="bi bi-circle me-4 radio-icon" style="font-size: 1.4rem; color: #ced4da;"></i>
                                <div class="gateway-logo-container border rounded bg-white me-4 d-flex align-items-center justify-content-center" style="width: 100px; height: 45px; box-shadow: inset 0 0 3px rgba(0,0,0,0.05); overflow: hidden;">
                                    <img src="' . $logoSrc . '" alt="" style="max-height: 30px; max-width: 90%; object-fit: contain; ' . $scaleStyle . '" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'block\';">
                                    <span class="logo-fallback" style="display: none; font-weight: bold; font-size: 0.8rem; color: #666;">' . substr($name, 0, 2) . '</span>
                                </div>
                                <span class="fw-bold text-uppercase" style="font-size: 1rem; letter-spacing: 0.5px; color: #333;">' . $name . '</span>
                            </div>
                        </label>
                    </div>');
}

$page->addHtml('
                </div>
            </div>

            <style>
                .gateway-option input:checked + label {
                    border-color: #0d6efd !important;
                    background-color: #f8f9ff !important;
                    box-shadow: 0 0 0 0.3rem rgba(13, 110, 253, 0.15);
                }
                .gateway-option input:checked + label .radio-icon::before {
                    content: "\F26B"; 
                    font-family: "bootstrap-icons";
                    color: #0d6efd;
                }
                .gateway-option label:hover {
                    background-color: #f8f9fa !important;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                }
                .radio-icon::before {
                    content: "\F285"; 
                    font-family: "bootstrap-icons";
                }
            </style>
            
            <div class="d-flex justify-content-between mt-5 pt-3 border-top">
                <a href="javascript:history.back()" class="btn btn-secondary pe-4 ps-4">
                    <i class="bi bi-arrow-left me-2"></i>' . $gL10n->get('SYS_BACK') . '
                </a>
                <button type="submit" class="btn btn-primary pe-5 ps-5 fw-bold" id="btn_pay">
                    ' . $gL10n->get('RE_CONFIRM_PAY') . ' <i class="bi bi-shield-check ms-2"></i>
                </button>
            </div>
    </form>
    </div>
</div>');

// Add JS for auto-selection and action update
$page->addHtml('<script>
document.addEventListener("DOMContentLoaded", function() {
    const pgRadios = document.querySelectorAll(".pg-radio");
    const form = document.getElementById("select_gateway_form");
    const currencySymbol = "' . $currencySymbol . '";

    function updateFormAction(radio) {
        const actionFile = radio.getAttribute("data-action");
        const currentAction = form.getAttribute("action");
        const actionPath = currentAction.substring(0, currentAction.lastIndexOf("/") + 1);
        form.setAttribute("action", actionPath + actionFile);
    }

    function autoSelectGateway() {
        if (pgRadios.length === 0) return;
        
        let targetValue = "";
        if (pgRadios.length === 1) {
            targetValue = pgRadios[0].value;
        } else {
            // Check currency for auto-selection
            if (currencySymbol === "₹" || currencySymbol === "Rs." || currencySymbol === "INR") {
                targetValue = "CCAVENUE";
            } else {
                targetValue = "PAYPAL";
            }
        }

        pgRadios.forEach(radio => {
            if (radio.value === targetValue) {
                radio.checked = true;
                updateFormAction(radio);
            }
        });
    }

    pgRadios.forEach(radio => {
        radio.addEventListener("change", function() {
            updateFormAction(this);
        });
    });

    autoSelectGateway();
});
</script>');

$page->addHtml('<div style="height: 50px;"></div>');
$page->show();
