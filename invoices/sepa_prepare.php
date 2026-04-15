<?php
/**
 * ***********************************************************************************************
 * Bridge script to trigger the MembershipFee SEPA Export from the Residents plugin.
 * 
 * Two-step flow:
 *   Step 1 – User selects a Due Date on an intermediate configuration page.
 *   Step 2 – Data is "swapped" into MembershipFee profile fields and the XML export is triggered.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 * ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/login_valid.php');

global $gDb, $gL10n, $gProfileFields, $gCurrentOrgId, $gSettingsManager;

// Check authorization
$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
if (!isUserAuthorizedForResidents($scriptUrl)) {
    http_response_code(403);
    die($gL10n->get('SYS_NO_RIGHTS'));
}

// Get invoice IDs from POST (from the DataTable select) or GET (for single tests)
$invoiceIdsRaw = $_POST['ids'] ?? $_GET['ids'] ?? '';
if (is_array($invoiceIdsRaw)) {
    $invoiceIds = array_map('intval', $invoiceIdsRaw);
} else {
    $invoiceIds = array_filter(array_map('intval', explode(',', (string)$invoiceIdsRaw)));
}

if (empty($invoiceIds)) {
    die($gL10n->get('RE_SEPA_NO_INVOICES'));
}

// Check if a due_date was submitted (Step 2) or not (Step 1)
$selectedDueDate = isset($_POST['due_date']) ? trim((string)$_POST['due_date']) : '';
$selectedSequenceType = isset($_POST['sequence_type']) ? trim((string)$_POST['sequence_type']) : 'OOFF';

// ============================================================================
// STEP 1 – Show Due Date Selection Form
// ============================================================================
if ($selectedDueDate === '') {

    // Calculate default due date (latest due date from selected invoices)
    $maxDueDate = '';
    $invoiceSummary = array();
    foreach ($invoiceIds as $invoiceId) {
        $invoice = new TableResidentsInvoice($gDb, $invoiceId);
        if ($invoice->isNewRecord()) {
            continue;
        }

        $userId = (int)$invoice->getValue('riv_usr_id');
        $userName = residentsFetchUserNameById($userId);

        // Get total amount for the invoice
        $sqlSum = 'SELECT SUM(rii_amount) FROM ' . TBL_RE_INVOICE_ITEMS . ' WHERE rii_inv_id = ?';
        $amount = (float)$gDb->queryPrepared($sqlSum, array($invoiceId))->fetchColumn();

        $dueDateRaw = (string)$invoice->getValue('riv_due_date');
        if ($dueDateRaw !== '') {
            $dueDateDt = \DateTime::createFromFormat('Y-m-d', $dueDateRaw) ?: \DateTime::createFromFormat('d.m.Y', $dueDateRaw);
            $dueDate = $dueDateDt ? $dueDateDt->format('Y-m-d') : substr($dueDateRaw, 0, 10);
        } else {
            $dueDate = date('Y-m-d');
        }

        if ($dueDate > $maxDueDate) {
            $maxDueDate = $dueDate;
        }

        $invoiceSummary[] = array(
            'id'       => $invoiceId,
            'user'     => $userName,
            'amount'   => $amount,
            'due_date' => $dueDate
        );
    }

    if ($maxDueDate === '') {
        $maxDueDate = date('Y-m-d');
    }

    // Calculate total amount
    $totalAmount = 0.0;
    foreach ($invoiceSummary as $inv) {
        $totalAmount += $inv['amount'];
    }

    $backUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php?tab=invoices';
    $selfUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/invoices/sepa_prepare.php';
    $csrfToken = $GLOBALS['gCurrentSession']->getCsrfToken();

$page = new HtmlPage('re-sepa-prepare', $gL10n->get('RE_SEPA_EXPORT'));
$page->addHtml('
<iframe name="sepa_download_frame" style="display:none;"></iframe>

<!-- Due Date Selection Form -->
<form id="sepa_date_form" method="post" action="' . htmlspecialchars($selfUrl) . '" target="sepa_download_frame">
    <input type="hidden" name="admidio-csrf-token" value="' . htmlspecialchars($csrfToken) . '" />');

    foreach ($invoiceIds as $id) {
        $page->addHtml('<input type="hidden" name="ids[]" value="' . (int)$id . '" />');
    }

    $page->addHtml('
<div class="card">
    <div class="card-body">
        <div style="max-width: 675px; margin: 0 auto;">
            <div class="row mb-4 justify-content-center">
                <div class="col-auto">
                    <label for="due_date" class="form-label">' . $gL10n->get('RE_DUE_DATE') . '</label>
                    <input type="date" id="due_date" name="due_date" class="form-control"
                           value="' . htmlspecialchars($maxDueDate) . '" required />
                </div>
                <div class="col-auto">
                    <label for="sequence_type" class="form-label">' . $gL10n->get('RE_SEQUENCE_TYPE') . '</label>
                    <input type="text" id="sequence_type" name="sequence_type" class="form-control text-uppercase"
                           value="OOFF" maxlength="4" required />
                </div>
                <div class="col-auto d-flex align-items-start">
                    <div class="py-2 px-4 rounded" style="background-color: #fff8e1; font-size: 0.85rem; color: #555; display: inline-block;">
                        <ul class="mb-0 ps-3" style="list-style-type: disc;">
                            <li>' . htmlspecialchars($gL10n->get('RE_SEPA_FIRST')) . '</li>
                            <li>' . htmlspecialchars($gL10n->get('RE_SEPA_RECURRING')) . '</li>
                            <li>' . htmlspecialchars($gL10n->get('RE_SEPA_FINAL')) . '</li>
                            <li>' . htmlspecialchars($gL10n->get('RE_SEPA_OOFF')) . '</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div style="max-width: 675px; margin: 0 auto;">
        <table class="table table-striped table-hover" id="sepa-user-table">
            <thead>
                <tr>
                    <th>' . $gL10n->get('RE_USER') . '</th>
                    <th class="text-end">' . $gL10n->get('RE_AMOUNT') . '</th>
                </tr>
            </thead>
            <tbody id="sepa-user-tbody">');

    foreach ($invoiceSummary as $inv) {
        $page->addHtml('
                <tr>
                    <td>' . htmlspecialchars($inv['user']) . '</td>
                    <td class="text-end">' . $gSettingsManager->getString('system_currency') . ' ' . number_format($inv['amount'], 2, '.', ',') . '</td>
                </tr>');
    }

    $page->addHtml('
            </tbody>
            <tfoot>
                <tr>
                    <th class="text-end">' . $gL10n->get('RE_TOTAL_PAYABLE') . '</th>
                    <th class="text-end">' . $gSettingsManager->getString('system_currency') . ' ' . number_format($totalAmount, 2, '.', ',') . '</th>
                </tr>
            </tfoot>
        </table>

        <nav id="sepa-pagination" aria-label="User list pagination">
            <ul class="pagination justify-content-center mb-0"></ul>
        </nav>
        </div>

    </div>
</div>
</form>

<div class="d-flex justify-content-end mt-3 gap-2">
    <a href="' . htmlspecialchars($backUrl) . '" class="btn btn-secondary px-4">
        ' . $gL10n->get('SYS_CANCEL') . '
    </a>
    <button type="submit" form="sepa_date_form" class="btn btn-primary px-4 fw-bold" id="btn-export">
        ' . $gL10n->get('RE_SEPA_EXPORT_XML') . ' <i class="bi bi-download ms-2"></i>
    </button>
</div>
');

    $page->addHtml('
<script>
document.addEventListener("DOMContentLoaded", function() {
    var form = document.getElementById("sepa_date_form");
    if (!form) return;

    form.addEventListener("submit", function() {
        var btn = document.getElementById("btn-export");
        var loadingHtml = \'<i class="bi bi-hourglass-split me-2"></i> ' . $gL10n->get('RE_SEPA_GENERATING') . '\';
        if (btn) {
            btn.classList.add("disabled");
            btn.innerHTML = loadingHtml;
        }

        // Redirect after 3 seconds delay
        setTimeout(function() {
            window.location.href = \'' . $backUrl . '\';
        }, 3000);
    });

    // --- Client-side pagination ---
    var rowsPerPage = 10;
    var tbody = document.getElementById("sepa-user-tbody");
    var rows = Array.from(tbody.querySelectorAll("tr"));
    var totalRows = rows.length;
    var totalPages = Math.ceil(totalRows / rowsPerPage);
    var paginationUl = document.querySelector("#sepa-pagination ul");
    var currentPage = 1;

    // Hide pagination if not needed
    if (totalPages <= 1) {
        document.getElementById("sepa-pagination").style.display = "none";
    }

    function showPage(page) {
        currentPage = page;
        var start = (page - 1) * rowsPerPage;
        var end = start + rowsPerPage;

        rows.forEach(function(row, i) {
            row.style.display = (i >= start && i < end) ? "" : "none";
        });

        renderPagination();
    }

    function renderPagination() {
        paginationUl.innerHTML = "";

        // Previous button
        var prevLi = document.createElement("li");
        prevLi.className = "page-item" + (currentPage === 1 ? " disabled" : "");
        prevLi.innerHTML = \'<a class="page-link" href="#">&laquo;</a>\';
        prevLi.addEventListener("click", function(e) {
            e.preventDefault();
            if (currentPage > 1) showPage(currentPage - 1);
        });
        paginationUl.appendChild(prevLi);

        // Page numbers
        for (var p = 1; p <= totalPages; p++) {
            (function(pageNum) {
                var li = document.createElement("li");
                li.className = "page-item" + (pageNum === currentPage ? " active" : "");
                li.innerHTML = \'<a class="page-link" href="#">\' + pageNum + \'</a>\';
                li.addEventListener("click", function(e) {
                    e.preventDefault();
                    showPage(pageNum);
                });
                paginationUl.appendChild(li);
            })(p);
        }

        // Next button
        var nextLi = document.createElement("li");
        nextLi.className = "page-item" + (currentPage === totalPages ? " disabled" : "");
        nextLi.innerHTML = \'<a class="page-link" href="#">&raquo;</a>\';
        nextLi.addEventListener("click", function(e) {
            e.preventDefault();
            if (currentPage < totalPages) showPage(currentPage + 1);
        });
        paginationUl.appendChild(nextLi);
    }

    showPage(1);
});
</script>');

    $page->addHtml('<div style="height: 50px;"></div>');
    $page->show();
    exit;
}

// ============================================================================
// STEP 2 – Perform Data Swap and Trigger Export
// ============================================================================

// 1. Identify the profile field IDs used by MembershipFee
$feeFieldName      = 'FEE' . $gCurrentOrgId;
$dueDateFieldName  = 'DUEDATE' . $gCurrentOrgId;
$sequenceFieldName = 'SEQUENCETYPE' . $gCurrentOrgId;

$feeFieldId      = (int)$gProfileFields->getProperty($feeFieldName, 'usf_id');
$dueDateFieldId  = (int)$gProfileFields->getProperty($dueDateFieldName, 'usf_id');
$sequenceFieldId = (int)$gProfileFields->getProperty($sequenceFieldName, 'usf_id');

if ($feeFieldId <= 0 || $dueDateFieldId <= 0 || $sequenceFieldId <= 0) {
    die($gL10n->get('RE_SEPA_FIELDS_NOT_FOUND'));
}

// Validate the user-supplied due date
$dueDateDt = \DateTime::createFromFormat('Y-m-d', $selectedDueDate);
if (!$dueDateDt) {
    die($gL10n->get('RE_SEPA_INVALID_DATE'));
}
$exportDueDate = $dueDateDt->format('Y-m-d');

// 2. Aggregate data by user to handle multiple invoices per person (Bundling)
$aggregatedData = array();

foreach ($invoiceIds as $invoiceId) {
    $invoice = new TableResidentsInvoice($gDb, $invoiceId);
    if ($invoice->isNewRecord()) {
        continue;
    }

    $userId = (int)$invoice->getValue('riv_usr_id');
    if ($userId <= 0) {
        continue;
    }
    
    // Get total amount for the invoice (sum of items)
    $sqlSum = 'SELECT SUM(rii_amount) FROM ' . TBL_RE_INVOICE_ITEMS . ' WHERE rii_inv_id = ?';
    $amount = (float)$gDb->queryPrepared($sqlSum, array($invoiceId))->fetchColumn();

    if (!isset($aggregatedData[$userId])) {
        $aggregatedData[$userId] = array(
            'amount' => 0.0
        );
    }

    $aggregatedData[$userId]['amount'] += $amount;
}

// 3. Perform the data swap once per user using the user-selected due date
$exportDueDates = array();
foreach ($aggregatedData as $userId => $data) {
    // Use the sequence type selected by the user
    $sequenceType = $selectedSequenceType;

    $updates = array(
        array('id' => $feeFieldId,      'value' => number_format($data['amount'], 2, '.', '')),
        array('id' => $dueDateFieldId,  'value' => $exportDueDate),
        array('id' => $sequenceFieldId, 'value' => $sequenceType)
    );

    foreach ($updates as $update) {
        $sqlCheck = 'SELECT 1 FROM ' . TBL_USER_DATA . ' WHERE usd_usr_id = ? AND usd_usf_id = ?';
        $exists = $gDb->queryPrepared($sqlCheck, array($userId, $update['id']))->fetchColumn();

        if ($exists) {
            $sqlUpd = 'UPDATE ' . TBL_USER_DATA . ' SET usd_value = ? WHERE usd_usr_id = ? AND usd_usf_id = ?';
            $gDb->queryPrepared($sqlUpd, array($update['value'], $userId, $update['id']));
        } else {
            $sqlIns = 'INSERT INTO ' . TBL_USER_DATA . ' (usd_usr_id, usd_usf_id, usd_value) VALUES (?, ?, ?)';
            $gDb->queryPrepared($sqlIns, array($userId, $update['id'], $update['value']));
        }
    }

    // Keep track of combined DueDate+Sequence for the XML POST
    $exportDueDates[] = $exportDueDate . $sequenceType;
}

$uniqueDueDates = array_unique($exportDueDates);

// 4. Directly trigger the MembershipFee SEPA export
$_POST['duedatesepatype'] = $uniqueDueDates;
$_POST['export_file_mode'] = 'xml_file';

require_once(ADMIDIO_PATH . '/adm_plugins/MembershipFee/system/sepa_export.php');

