<?php
/**
 * Payments tab content. Lists captured payments and allows viewing their items.
 */

global $gDb, $gL10n, $gProfileFields, $gCurrentUser, $gSettingsManager, $gCurrentOrgId, $gDbType, $page;

$baseUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php';
$canManage = isPaymentAdmin();
$canCreatePayments = isBillingAdminBySettings();
$canViewAll = $canCreatePayments || $canManage;

if (!tableExistsBILL(TBL_BL_PAYMENTS) || !tableExistsBILL(TBL_BL_PAYMENT_ITEMS)) {
  return;
}

// Show empty-state banner if there are no payments at all, but still render filters/table
$countStmt = $gDb->queryPrepared('SELECT COUNT(*) FROM ' . TBL_BL_PAYMENTS, array());
$totalPayments = $countStmt ? (int)$countStmt->fetchColumn() : 0;
// Continue rendering filters/table even if there are zero payments; suppress empty-state banner.

// Check for timed out payments (Initiated > 15 mins ago)
$timeoutMinutes = 15;
$timeoutDate = date('Y-m-d H:i:s', strtotime("-{$timeoutMinutes} minutes"));
TableResidentsTransaction::expireInitiated($gDb, $timeoutDate);

// flash messages from payment gateway
$paymentStatus = admFuncVariableIsValid($_GET, 'payment_status', 'string');
$paymentMessage = admFuncVariableIsValid($_GET, 'payment_message', 'string');
$paymentMessageMap = array(
  'invalid_response' => $gL10n->get('BL_PAYMENT_MSG_INVALID_RESPONSE'),
  'missing_order' => $gL10n->get('BL_PAYMENT_MSG_MISSING_ORDER'),
  'payment_not_found' => $gL10n->get('BL_PAYMENT_MSG_PAYMENT_NOT_FOUND'),
  'processing_error' => $gL10n->get('BL_PAYMENT_MSG_PROCESSING_ERROR'),
  'Unknown error' => $gL10n->get('BL_PAYMENT_MSG_UNKNOWN')
);

if ($paymentStatus === 'success') {
  $page->addHtml('<div class="alert alert-success mb-3">' . $gL10n->get('BL_PAYMENT_SUCCESS') . '</div>');
} elseif ($paymentStatus === 'failed') {
  $msg = $gL10n->get('BL_PAYMENT_FAILED');
  if ($paymentMessage !== '' && isset($paymentMessageMap[$paymentMessage])) {
    $msg = $paymentMessageMap[$paymentMessage];
  } elseif ($paymentMessage !== '') {
    $msg .= ' (' . htmlspecialchars($paymentMessage) . ')';
  }
  $page->addHtml('<div class="alert alert-danger mb-3">' . $msg . '</div>');
} elseif ($paymentStatus === 'deleted') {
  $page->addHtml('<div class="alert alert-success mb-3">Payment deleted.</div>');
} elseif ($paymentStatus === 'error') {
  $msg = $paymentMessage !== '' ? htmlspecialchars($paymentMessage) : 'Action failed.';
  $page->addHtml('<div class="alert alert-danger mb-3">' . $msg . '</div>');
}

$getUser = admFuncVariableIsValid($_GET, 'filter_user', 'int');
$getType = trim((string)admFuncVariableIsValid($_GET, 'filter_type', 'string'));
$getQ = trim((string)admFuncVariableIsValid($_GET, 'q', 'string'));
$getStart = admFuncVariableIsValid($_GET, 'filter_start', 'date');
$getEnd = admFuncVariableIsValid($_GET, 'filter_end', 'date');
if ($getStart === '' && $getEnd === '') {
  $getStart = date('Y-m-01');
  $getEnd = date('Y-m-t');
}

// Determine default page length for Datatables
$defaultPageLength = (int)$gSettingsManager->getInt('system_datatables_rows');
if ($defaultPageLength <= 0) {
  $defaultPageLength = 25;
}

// filter dropdowns
$getGroup = admFuncVariableIsValid($_GET, 'filter_group', 'int');

// Hide and ignore group/user filters for normal users (only admins can filter across users/groups).
if (!$canViewAll) {
  $getUser = 0;
  $getGroup = 0;
}

$firstNameFieldId = (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id');
$lastNameFieldId = (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id');
$userOptions = TableResidentsPayment::fetchUserOptions($gDb, $canViewAll, $firstNameFieldId, $lastNameFieldId, (int)$gCurrentUser->getValue('usr_id'), $getGroup);





$filterAction = SecurityUtils::encodeUrl($baseUrl, array('tab' => 'payments'));
// Show "New payment" button only to billing admins
if ($canCreatePayments) {
  $page->addHtml('<div class="mb-3 text-start"><a href="'.SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/payments/edit.php').'" class="btn btn-secondary"><i class="fas fa-plus"></i> '.$gL10n->get('BL_ADD_PAYMENT').'</a></div>');
}

if (!$canViewAll) {
  $basicForm = new HtmlForm(
    'payments_filter_basic',
    $filterAction,
    $page,
    array('type' => 'navbar', 'setFocus' => false)
  );
  $basicForm->addInput('tab', '', 'payments', array('type' => 'hidden'));
  $basicForm->addInput('filter_start', $gL10n->get('SYS_START'), $getStart, array('type' => 'date', 'maxLength' => 10));
  $basicForm->addInput('filter_end', $gL10n->get('SYS_END'), $getEnd, array('type' => 'date', 'maxLength' => 10));
  $basicForm->addButton(
    'payments_filter_apply_basic',
    $gL10n->get('SYS_FILTER'),
    array('type' => 'submit', 'icon' => 'fa-filter', 'class' => 'btn btn-primary btn-sm ms-2')
  );

  $basicNavbar = new HtmlNavbar('navbar_payments_filter_basic', '', $page, 'filter');
  $basicNavbar->addForm($basicForm->show());
  $page->addHtml($basicNavbar->show());
  $page->addJavascript(
    "$(function(){ var basicPayForm=$('#payments_filter_basic'); basicPayForm.find('input[type=date]').on('change', function(){ basicPayForm.submit(); }); });",
    true
  );
}

// Show filters only to admins (use same navbar form styling as Invoices)
if ($canViewAll) {
  $roles = billingGetRoleOptions();
  $rolesWithAll = array('0' => $gL10n->get('BL_ALL')) + $roles;
  $userOptionsWithAll = array('0' => $gL10n->get('BL_ALL')) + $userOptions;

  $labelGroup = '<i class="fas fa-users" alt="'.$gL10n->get('BL_GROUP').'" title="'.$gL10n->get('BL_GROUP').'"></i>';
  $labelUser = '<i class="fas fa-user" alt="'.$gL10n->get('BL_USER').'" title="'.$gL10n->get('BL_USER').'"></i>';
  $labelSearch = '<i class="fas fa-search" alt="'.$gL10n->get('SYS_SEARCH').'" title="'.$gL10n->get('SYS_SEARCH').'"></i>';

  $filterNavbar = new HtmlNavbar('navbar_payments_filter', '', $page, 'filter');
  $filterForm = new HtmlForm(
    'payments_filter',
    $filterAction,
    $page,
    array('type' => 'navbar', 'setFocus' => false)
  );
  $filterForm->addInput('tab', '', 'payments', array('type' => 'hidden'));

  $filterForm->addSelectBox(
    'filter_group',
    $labelGroup,
    $rolesWithAll,
    array('defaultValue' => (string)$getGroup, 'showContextDependentFirstEntry' => false)
  );

  $filterForm->addSelectBox(
    'filter_user',
    $labelUser,
    $userOptionsWithAll,
    array('defaultValue' => (string)$getUser, 'showContextDependentFirstEntry' => false)
  );

  $filterForm->addSelectBox(
    'filter_type',
    $gL10n->get('BL_TYPE'),
    array(
      '' => $gL10n->get('BL_ALL'),
      'online' => $gL10n->get('BL_PAYMENT_TYPE_ONLINE'),
      'offline' => $gL10n->get('BL_PAYMENT_TYPE_OFFLINE')
    ),
    array('defaultValue' => (string)$getType, 'showContextDependentFirstEntry' => false)
  );

  $filterForm->addInput('q', $labelSearch, $getQ);
  $filterForm->addInput('filter_start', $gL10n->get('SYS_START'), $getStart, array('type' => 'date', 'maxLength' => 10));
  $filterForm->addInput('filter_end', $gL10n->get('SYS_END'), $getEnd, array('type' => 'date', 'maxLength' => 10));
  $filterForm->addButton(
    'payments_filter_apply',
    $gL10n->get('SYS_FILTER'),
    array('type' => 'submit', 'icon' => 'fa-filter', 'class' => 'btn btn-primary btn-sm ms-2')
  );

  $filterNavbar->addForm($filterForm->show());
  $page->addHtml($filterNavbar->show());

  $page->addJavascript(
    "$(function(){ var payForm=$('#payments_filter'); payForm.find('select, input[type=date], input[name=q]').on('change', function(){ payForm.submit(); }); });",
    true
  );
}

$paymentsStyle = '#table_billing_payments thead{border-top:1px solid #dee2e6;border-bottom:1px solid #dee2e6;background-color:#fff;}#table_billing_payments thead th{font-weight:700;color:#495057;padding:12px 30px 12px 15px !important;white-space:nowrap;position:relative;border:none;background-position: right 5px center !important;}';
$paymentsStyle .= '#table_billing_payments_wrapper .dataTables_length,#table_billing_payments_length{display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;}';
$paymentsStyle .= '#table_billing_payments_wrapper .dataTables_length label,#table_billing_payments_length label{margin-bottom:0;display:flex;align-items:center;gap:0.35rem;white-space:nowrap;}';
$paymentsStyle .= '#table_billing_payments_wrapper .dataTables_length select,#table_billing_payments_length select{width:auto;min-width:70px;display:inline-block;}';
$paymentsStyle .= '#table_billing_payments_filter{display:none!important;}';
if ($canViewAll) {
  $paymentsStyle .= '#table_billing_payments thead th:first-child:before,#table_billing_payments thead th:first-child:after{display:none!important;}';
}
$page->addHtml('<style>'.$paymentsStyle.'</style>');

$serverParams = array(
  'filter_user' => $getUser,
  'filter_group' => $getGroup,
  'filter_type' => $getType,
  'q' => $getQ,
  'filter_start' => $getStart,
  'filter_end' => $getEnd
);
$serverUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/payments/list_data.php', $serverParams);

$table = new HtmlTable('table_billing_payments', $page, $isAdmin, true, 'table table-hover align-middle');
$table->setServerSideProcessing($serverUrl);
$table->setDatatablesRowsPerPage($defaultPageLength);
$table->setDatatablesOrderColumns(array(array(2, 'desc')));
if ($canViewAll) {
  $table->disableDatatablesColumnsSort(array(1,8));
  $table->setColumnAlignByArray(array('center','left','left','left','left','left','right','left'));
  $table->addRowHeadingByArray(array(
    ($canManage ? '<input type="checkbox" id="billing-select-all-payments" />' : ''),
    $gL10n->get('BL_PAYMENT_NUMBER'),
    $gL10n->get('BL_PAYMENT_DATE'),
    $gL10n->get('BL_PAYMENT_METHOD'),
    $gL10n->get('BL_PAYMENT_TYPE'),
    $gL10n->get('BL_CUSTOMER'),
    $gL10n->get('BL_PAYMENT_TOTAL'),
    $gL10n->get('BL_ACTIONS')
  ));
} else {
  $table->disableDatatablesColumnsSort(array(7));
  $table->setColumnAlignByArray(array('left','left','left','left','left','right','left'));
  $table->addRowHeadingByArray(array(
    $gL10n->get('BL_PAYMENT_NUMBER'),
    $gL10n->get('BL_PAYMENT_DATE'),
    $gL10n->get('BL_PAYMENT_METHOD'),
    $gL10n->get('BL_PAYMENT_TYPE'),
    $gL10n->get('BL_CUSTOMER'),
    $gL10n->get('BL_PAYMENT_TOTAL'),
    $gL10n->get('BL_ACTIONS')
  ));
}

if ($canManage) {
    $bulkDeleteUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/payments/delete_all.php');
    $bulkDeleteUrlJs = json_encode($bulkDeleteUrl);
    $paymentsDeleteConfirm = json_encode($gL10n->get('BL_DELETE_PAYMENT_CONFIRM'));
    $paymentsDeleteError = json_encode('Error deleting selected payments');
    $deleteAllLabel = json_encode($gL10n->get('BL_DELETE_ALL'));
    $csrfTokenJs = json_encode($GLOBALS['gCurrentSession']->getCsrfToken());
    $jsPayments = <<<'JS'
        $(function(){
            var bulkDeleteUrl = {{BULK_DELETE_URL}};
            var deleteConfirmMsg = {{DELETE_CONFIRM}};
            var deleteErrorMsg = {{DELETE_ERROR}};
            var deleteButtonLabel = {{DELETE_BUTTON_LABEL}};
            var csrfToken = {{CSRF_TOKEN}};
            
            var tableEl = $('#table_billing_payments');
            var dataTable = tableEl.DataTable();
            var wrapperEl = $('#table_billing_payments_wrapper');
            function locateLengthContainer(){
                var lengthEl = $('#table_billing_payments_length');
                if (lengthEl.length) {
                    return lengthEl;
                }
                lengthEl = wrapperEl.find('.dataTables_length');
                if (lengthEl.length) {
                    return lengthEl;
                }
                return $();
            }
            function ensureDeleteButton(){
                var lengthEl = locateLengthContainer();
                if (!lengthEl.length) {
                    return $();
                }
                var buttonEl = $('#billing-delete-selected-payments');
                if (buttonEl.length) {
                    return buttonEl;
                }
                var newButtonEl = $('<button type="button" id="billing-delete-selected-payments" class="btn btn-danger btn-sm ms-2"><i class="fas fa-trash"></i> ' + deleteButtonLabel + '</button>');
                lengthEl.append(newButtonEl);
                return newButtonEl;
            }
            function bindDeleteButton(buttonEl){
                if (!buttonEl.length || buttonEl.data('billingDeleteBound')) {
                    return;
                }
                buttonEl.data('billingDeleteBound', true).on('click', function(e){
                    e.preventDefault();
                    var ids = [];
                    tableEl.find('tbody input.billing-row-select:checked').each(function(){ ids.push($(this).val()); });
                    if (ids.length === 0) {
                        return;
                    }
                    if (!confirm(deleteConfirmMsg)) { return; }
                    $.ajax({
                        type: 'POST',
                        url: bulkDeleteUrl,
                      data: { ids: ids, 'admidio-csrf-token': csrfToken },
                        success: function(){ location.reload(); },
                        error: function(){ alert(deleteErrorMsg); }
                    });
                });
            }
            function getDeleteButton(){
                var buttonEl = ensureDeleteButton();
                bindDeleteButton(buttonEl);
                return buttonEl;
            }
            function updateDeleteButtonState(){
                var buttonEl = getDeleteButton();
                if (!buttonEl.length) {
                    return;
                }
                var hasSelection = tableEl.find('tbody input.billing-row-select:checked').length > 0;
                buttonEl.prop('disabled', !hasSelection);
            }
            var deleteButtonEl = getDeleteButton();
            dataTable.on('init.dt', function(){
                deleteButtonEl = getDeleteButton();
                updateDeleteButtonState();
            });
            var firstHeader = tableEl.find('thead th').first();
            if (firstHeader.length) {
                firstHeader.removeClass('sorting sorting_asc sorting_desc');
            }
            function syncHeaderCheckboxPayments(){
                var total = tableEl.find('tbody input.billing-row-select').length;
                var selected = tableEl.find('tbody input.billing-row-select:checked').length;
                var hdr = $('#billing-select-all-payments').get(0);
                if (!hdr) { return; }
                hdr.indeterminate = selected > 0 && selected < total;
                hdr.checked = total > 0 && selected === total;
            }
            tableEl.find('thead')
                .on('click', '#billing-select-all-payments', function(e){ e.stopPropagation(); })
                .on('change', '#billing-select-all-payments', function(e){
                    e.stopPropagation();
                    var checked = this.checked;
                    tableEl.find('tbody input.billing-row-select')
                        .prop('checked', checked)
                        .trigger('change');
                    syncHeaderCheckboxPayments();
                });
            function updateInfo(){
                var pageInfo = dataTable.page.info();
                var selected = tableEl.find('tbody input.billing-row-select:checked').length;
                var infoEl = wrapperEl.find('.dataTables_info');
                if (selected > 0){
                    infoEl.text(selected + ' selected');
                } else {
                    infoEl.text('Showing ' + (pageInfo.start + 1) + ' to ' + pageInfo.end + ' of ' + pageInfo.recordsDisplay + ' entries');
                }
            }
            dataTable.on('draw', function(){
                updateInfo();
                syncHeaderCheckboxPayments();
                deleteButtonEl = getDeleteButton();
                updateDeleteButtonState();
            });
            tableEl.on('change', 'input.billing-row-select', function(){
                updateInfo();
                syncHeaderCheckboxPayments();
                updateDeleteButtonState();
            });
            updateInfo();
            syncHeaderCheckboxPayments();
            updateDeleteButtonState();
        });
        JS;
        $jsPayments = strtr($jsPayments, array(
            '{{BULK_DELETE_URL}}' => $bulkDeleteUrlJs,
            '{{DELETE_CONFIRM}}' => $paymentsDeleteConfirm,
            '{{DELETE_ERROR}}' => $paymentsDeleteError,
            '{{DELETE_BUTTON_LABEL}}' => $deleteAllLabel,
          '{{CSRF_TOKEN}}' => $csrfTokenJs,
        ));
        $page->addJavascript("\n".$jsPayments."\n", true);
}

$page->addHtml($table->show(false));


