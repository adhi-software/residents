<?php

/**
 * View an invoice details (admins or users with permission)
 */

require_once(__DIR__ . '/../common_function.php');
// Enforce valid login
require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');

global $gDb, $gL10n, $gSettingsManager, $gCurrentUser;

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php';
if (!isUserAuthorizedForBilling($scriptUrl)) {
  $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$previewMode = ((int)admFuncVariableIsValid($_GET, 'preview', 'int') === 1);
$previewUserId = admFuncVariableIsValid($_GET, 'preview_user', 'int');
$previewGroupId = admFuncVariableIsValid($_GET, 'preview_group', 'int');
$returnFilterUserId = admFuncVariableIsValid($_GET, 'filter_user', 'int');
$previewStartParam = admFuncVariableIsValid($_GET, 'preview_start_date', 'date');
$previewInvoiceParam = admFuncVariableIsValid($_GET, 'preview_invoice_date', 'date');
$previewNoteParam = admFuncVariableIsValid($_GET, 'preview_note', 'string');
$previewDataRow = null;
$previewReturnUrl = '';

if ($previewMode) {
  if ($previewUserId <= 0) {
  $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
  }
  $previewOptions = array(
  'start_date' => $previewStartParam,
  'invoice_date' => $previewInvoiceParam,
  'note' => $previewNoteParam,
  'user_id' => $previewUserId
  );
  $previewData = billingBuildInvoicePreviewData($previewGroupId, $previewOptions);
  foreach ($previewData['rows'] as $row) {
  if ((int)$row['user_id'] === $previewUserId) {
      $previewDataRow = $row;
      break;
  }
  }
  if ($previewDataRow === null) {
  $gMessage->show($gL10n->get('BL_PREVIEW_DETAIL_MISSING'));
  }

  // In preview mode, show only billable items (exclude charges already billed for overlapping periods)
  $itemOverlapSql = 'SELECT COUNT(*)
    FROM ' . TBL_BL_INVOICES . ' i
    INNER JOIN ' . TBL_BL_INVOICE_ITEMS . ' it ON it.bii_inv_id = i.biv_id
   WHERE i.biv_usr_id = ?
     AND it.bii_chg_id = ?
     AND it.bii_start_date <= ?
     AND it.bii_end_date >= ?';

  $filteredItems = array();
  $filteredTotal = 0.0;
  $coverageStart = null;
  $coverageEnd = null;
  foreach ((array)($previewDataRow['items'] ?? array()) as $item) {
    $chargeId = (int)($item['charge_id'] ?? 0);
    if ($chargeId <= 0) {
      continue;
    }
    $itemStart = (string)($item['start_date'] ?? '');
    $itemEnd = (string)($item['end_date'] ?? '');
    if ($itemStart === '' || $itemEnd === '') {
      continue;
    }

    $existsCount = (int)$gDb->queryPrepared($itemOverlapSql, array($previewUserId, $chargeId, $itemEnd, $itemStart))->fetchColumn();
    if ($existsCount > 0) {
      continue;
    }

    $filteredItems[] = $item;
    $filteredTotal += (float)($item['amount'] ?? 0);
    if ($coverageStart === null || $itemStart < $coverageStart) {
      $coverageStart = $itemStart;
    }
    if ($coverageEnd === null || $itemEnd > $coverageEnd) {
      $coverageEnd = $itemEnd;
    }
  }
  $previewDataRow['items'] = $filteredItems;
  $previewDataRow['total'] = number_format($filteredTotal, 2, '.', '');
  if ($coverageStart !== null) {
    $previewDataRow['start_date'] = $coverageStart;
  }
  if ($coverageEnd !== null) {
    $previewDataRow['end_date'] = $coverageEnd;
  }

  $id = 0;
  $inv = array(
  'biv_id' => 0,
  'biv_number' => $gL10n->get('BL_PREVIEW_LABEL'),
  'biv_usr_id' => $previewUserId,
  'biv_is_paid' => 0,
  // Invoice type is fixed for display only; do not use in logic
  'biv_type' => 'Open',
  'biv_date' => $previewDataRow['invoice_date'],
  'biv_due_date' => $previewDataRow['due_date'],
  'biv_start_date' => $previewDataRow['start_date'],
  'biv_end_date' => $previewDataRow['end_date'],
  'biv_notes' => (string)($previewDataRow['note'] ?? '')
  );
  $totals = array(
  'amount' => (float)($previewDataRow['total'] ?? 0),
  'currency' => $previewDataRow['currency'] ?? $gSettingsManager->getString('system_currency')
  );
  $items = array();
  foreach ((array)$previewDataRow['items'] as $item) {
  $items[] = array(
      'bii_name' => (string)$item['name'],
      'bii_start_date' => (string)($item['start_date'] ?? ''),
      'bii_end_date' => (string)($item['end_date'] ?? ''),
      'bii_rate' => '',
      'bii_quantity' => '',
      'bii_amount' => number_format((float)($item['amount'] ?? 0), 2, '.', ','),
      'bii_currency' => (string)($item['currency'] ?? $totals['currency'])
  );
  }
  $previewReturnParams = array(
  'tab' => 'invoices',
  'preview' => 1,
  'preview_start_date' => $previewData['parameters']['start_date'] ?? $previewStartParam,
  'preview_invoice_date' => $previewData['parameters']['invoice_date'] ?? $previewInvoiceParam
  );
  if (($previewData['parameters']['note'] ?? $previewNoteParam) !== '') {
  $previewReturnParams['preview_note'] = $previewData['parameters']['note'] ?? $previewNoteParam;
  }
  if ($previewGroupId > 0) {
  $previewReturnParams['filter_group'] = $previewGroupId;
  }
  if ($returnFilterUserId > 0) {
  $previewReturnParams['filter_user'] = $returnFilterUserId;
  }
  $previewReturnUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php', $previewReturnParams);
} else {
  $id = admFuncVariableIsValid($_GET, 'id', 'int');

  $invoice = new TableResidentsInvoice($gDb, $id);
  if ($invoice->isNewRecord()) {
  $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
  }

  // Permission check: admins can view all invoices, regular users can only view their own
  $isAdmin = isBillingAdminBySettings();
  $currentUserId = (int)$gCurrentUser->getValue('usr_id');
  $invoiceOwnerId = (int)$invoice->getValue('biv_usr_id');
  
  if (!$isAdmin && $currentUserId !== $invoiceOwnerId) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
  }

  $inv = array(
  'biv_id' => (int)$invoice->getValue('biv_id'),
  'biv_number' => (string)$invoice->getValue('biv_number'),
  'biv_usr_id' => (int)$invoice->getValue('biv_usr_id'),
  'biv_is_paid' => (int)$invoice->getValue('biv_is_paid'),
  // Invoice type is fixed for display only; do not use in logic
  'biv_type' => 'Open',
  'biv_date' => (string)$invoice->getValue('biv_date'),
  'biv_due_date' => (string)$invoice->getValue('biv_due_date'),
  'biv_start_date' => (string)$invoice->getValue('biv_start_date'),
  'biv_end_date' => (string)$invoice->getValue('biv_end_date'),
  'biv_notes' => (string)$invoice->getValue('biv_notes')
  );

  $totals = billingGetInvoiceTotals($id);
  $items = $invoice->getItems();
}

$currencyLabel = $totals['currency'] ?? $gSettingsManager->getString('system_currency');
$amountFormatted = number_format((float)$totals['amount'], 2, '.', ',');

$isPaid = !$previewMode && (int)($inv['biv_is_paid'] ?? 0) === 1;
$statusLabel = $previewMode ? $gL10n->get('BL_PREVIEW_STATUS') : ($isPaid ? $gL10n->get('BL_PAID') : 'Unpaid');
$badgeClass = $previewMode ? 'bg-info text-dark' : ($isPaid ? 'bg-success' : 'bg-warning text-dark');

// Paid status label for the top highlight pill and summary section.
$paidStatusLabel = $previewMode ? $gL10n->get('BL_PREVIEW_STATUS') : ($isPaid ? $gL10n->get('BL_PAID') : 'Unpaid');

$customer = billingGetUserAddress((int)$inv['biv_usr_id']);
$customerName = $customer['name'] ?? '';
if ($customerName === '') {
  $customerName = $gL10n->get('SYS_USER') . ' #' . (int)$inv['biv_usr_id'];
}

$formatDate = static function ($value) use ($gSettingsManager) {
  if (empty($value)) {
  return '-';
  }
  try {
  $dt = new DateTime($value);
  return $dt->format($gSettingsManager->getString('system_date'));
  } catch (Exception $e) {
  return $value;
  }
};

$page = new HtmlPage('bl-residents-view', $gL10n->get('BL_TITLE'));
$page->setHeadline($gL10n->get('BL_TAB_INVOICES'));
$isAdminDetail = isBillingAdminBySettings();
$ownsInvoice = !$previewMode && isset($gCurrentUser) && (int)$inv['biv_usr_id'] === (int)$gCurrentUser->getValue('usr_id');
$canPayDetail = !$previewMode && !$isPaid && ($ownsInvoice || $isAdminDetail);
billingEnqueueStyles($page);

ob_start();
?>
<style>
  .billing-view .card {
  border: none;
  border-radius: 0.9rem;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
  }

  .billing-view .card+.card {
  margin-top: 1.5rem;
  }

  .billing-view .hero {
  background: #fff;
  color: #0f172a;
  border: 1px solid #edf2f7;
  }

  .billing-view .hero .date-value {
  display: block;
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 0.75rem;
  }

  .billing-view .hero .badge {
  font-size: .85rem;
  padding: .5rem 1.25rem;
  border-radius: 999px;
  }

  .billing-view .meta-label {
  text-transform: uppercase;
  letter-spacing: .08em;
  font-size: .75rem;
  color: #111827;
  }

  .billing-view .table thead th {
  border-bottom: 1px solid #edf2f7;
  text-transform: uppercase;
  font-size: .75rem;
  color: #94a3b8;
  letter-spacing: .08em;
  }

  .billing-view .table td {
  vertical-align: middle;
  }

  /* Fix name column width and truncate long text */
  .billing-view .table td:first-child {
  max-width: 500px;
  }
</style>

<div class="billing-view">
  <div class="card hero mb-4">
  <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
      <div class="mb-3 mb-md-0">
    <div class="meta-label mb-2">
          <?php if ($previewMode) : ?>
      <?php echo htmlspecialchars($gL10n->get('BL_PREVIEW_LABEL')); ?>
          <?php else : ?>
      <?php echo $gL10n->get('BL_NUMBER'); ?> #<?php echo htmlspecialchars((string)$inv['biv_number']); ?>
          <?php endif; ?>
    </div>
    <div class="display-6 fw-semibold mb-2"><?php echo htmlspecialchars((string)$currencyLabel); ?> <?php echo htmlspecialchars($amountFormatted); ?></div>
    <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars((string)$paidStatusLabel); ?></span>
      </div>
      <div class="text-md-end">
    <div class="meta-label mb-1"><?php echo $gL10n->get('BL_DATE'); ?></div>
    <div class="date-value"><?php echo htmlspecialchars($formatDate($inv['biv_date'])); ?></div>
    <div class="meta-label mb-1"><?php echo $gL10n->get('BL_DUE_DATE'); ?></div>
    <div class="date-value"><?php echo htmlspecialchars($formatDate($inv['biv_due_date'])); ?></div>
    <div class="d-flex justify-content-md-end flex-wrap" style="gap:0.5rem;">
          <?php if ($previewMode) : ?>
      <a class="btn btn-outline-secondary" href="<?php echo $previewReturnUrl; ?>">
              <i class="fas fa-arrow-left me-2"></i><?php echo $gL10n->get('BL_BACK'); ?>
      </a>
          <?php else : ?>
      <a class="btn btn-primary" href="<?php echo SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/invoices/pdf.php', array('id' => (int)$inv['biv_id'])); ?>">
              <i class="fas fa-file-pdf me-2"></i><?php echo $gL10n->get('SYS_PDF'); ?>
      </a>
      <?php if ($canPayDetail) : ?>
              <a class="btn btn-primary" href="<?php echo SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/payment_gateway/confirm_pay.php', array('invoice_id' => (int)$inv['biv_id'])); ?>">
        <i class="fas fa-credit-card me-2"></i><?php echo $gL10n->get('BL_PAY_NOW'); ?>
              </a>
      <?php endif; ?>
      <?php if ($isAdminDetail) : ?>
          <?php if (!$isPaid) : ?>
        <a class="btn btn-primary" href="<?php echo SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/invoices/edit.php', array('id' => (int)$inv['biv_id'])); ?>">
                  <i class="fas fa-edit me-2"></i><?php echo $gL10n->get('SYS_EDIT'); ?>
        </a>
              <?php endif; ?>
              <?php $confirmText = htmlspecialchars($gL10n->get('BL_DELETE_INVOICE_CONFIRM'), ENT_QUOTES, 'UTF-8'); ?>
              <form method="post" action="<?php echo ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/invoices/delete.php'; ?>" class="d-inline" onsubmit="return confirm('<?php echo $confirmText; ?>');">
                <input type="hidden" name="id" value="<?php echo (int)$inv['biv_id']; ?>" />
                <input type="hidden" name="admidio-csrf-token" value="<?php echo htmlspecialchars($gCurrentSession->getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>" />
                <button type="submit" class="btn btn-danger text-white">
                  <i class="fas fa-trash me-2"></i><?php echo $gL10n->get('SYS_DELETE'); ?>
                </button>
              </form>
      <?php endif; ?>
          <?php endif; ?>
    </div>
      </div>
  </div>
  </div>

  <div class="row g-4">
  <div class="col-lg-6">
      <div class="card">
    <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
              <div class="meta-label mb-1"><?php echo $gL10n->get('BL_USER'); ?></div>
              <h5 class="mb-0"><?php echo htmlspecialchars($customerName); ?></h5>
      </div>
      <div class="text-muted">#<?php echo (int)$inv['biv_usr_id']; ?></div>
          </div>
          <?php if (!empty($customer['email'])) : ?>
      <div class="mb-2"><i class="fas fa-envelope text-muted me-2"></i><?php echo htmlspecialchars((string)$customer['email']); ?></div>
          <?php endif; ?>
          <?php if (!empty($customer['tel'])) : ?>
      <div class="mb-2"><i class="fas fa-phone text-muted me-2"></i><?php echo htmlspecialchars((string)$customer['tel']); ?></div>
          <?php endif; ?>
          <?php if (!empty($customer['address'])) : ?>
      <div class="text-muted"><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars((string)$customer['address']); ?><?php if (!empty($customer['city'])) {
                                                                                                                                              echo ', ' . htmlspecialchars((string)$customer['city']);
                                                                      } ?></div>
          <?php endif; ?>
    </div>
      </div>
  </div>

  <div class="col-lg-6">
      <div class="card">
    <div class="card-body">
          <div class="row g-3">
      <div class="col-sm-6">
              <div class="meta-label mb-1">Paid Status</div>
              <div class="fw-semibold"><?php echo htmlspecialchars((string)$paidStatusLabel); ?></div>
      </div>
      <div class="col-sm-6">
              <div class="meta-label mb-1"><?php echo $gL10n->get('BL_STATUS'); ?></div>
              <div class="fw-semibold"><?php echo htmlspecialchars((string)$statusLabel); ?></div>
      </div>
      <div class="col-sm-6">
              <div class="meta-label mb-1"><?php echo $gL10n->get('BL_START_DATE'); ?></div>
              <div class="fw-semibold"><?php echo htmlspecialchars($formatDate($inv['biv_start_date'])); ?></div>
      </div>
      <div class="col-sm-6">
              <div class="meta-label mb-1"><?php echo $gL10n->get('BL_END_DATE'); ?></div>
              <div class="fw-semibold"><?php echo htmlspecialchars($formatDate($inv['biv_end_date'])); ?></div>
      </div>
          </div>
    </div>
      </div>
  </div>
  </div>

  <div class="card mt-4">
  <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><?php echo $gL10n->get('BL_INVOICE_ITEMS'); ?></h5>
  </div>
  <div class="card-body p-0">
      <div class="table-responsive">
    <table class="table mb-0">
          <thead>
      <tr>
              <th><?php echo $gL10n->get('SYS_NAME'); ?></th>
              <th><?php echo $gL10n->get('BL_START_DATE'); ?></th>
              <th><?php echo $gL10n->get('BL_END_DATE'); ?></th>
              <th class="text-end"><?php echo $gL10n->get('BL_AMOUNT'); ?></th>
      </tr>
          </thead>
          <tbody>
      <?php if (empty($items)) : ?>
              <tr>
        <td colspan="4" class="text-center text-muted py-4"><?php echo $gL10n->get('SYS_NO_ENTRIES'); ?></td>
              </tr>
      <?php else : ?>
              <?php foreach ($items as $item) :
        $cleanAmount = preg_replace('/[^0-9.,-]/', '', (string)($item['bii_amount'] ?? '0'));
        $amountValue = number_format((float)str_replace(',', '', $cleanAmount), 2, '.', ',');
        $lineCurrency = $item['bii_currency'] ?? $currencyLabel;
              ?>
        <tr>
                  <td class="fw-semibold text-dark"><?php echo htmlspecialchars((string)$item['bii_name']); ?></td>
                  <td class="text-muted"><?php echo htmlspecialchars($formatDate($item['bii_start_date'] ?? '')); ?></td>
                  <td class="text-muted"><?php echo htmlspecialchars($formatDate($item['bii_end_date'] ?? '')); ?></td>
                  <td class="text-end fw-semibold"><?php echo htmlspecialchars((string)$lineCurrency); ?> <?php echo htmlspecialchars($amountValue); ?></td>
        </tr>
              <?php endforeach; ?>
      <?php endif; ?>
          </tbody>
          <tfoot>
      <tr>
              <td colspan="3" class="text-end text-muted"><?php echo $gL10n->get('BL_TOTAL'); ?></td>
              <td class="text-end fw-semibold"><?php echo htmlspecialchars((string)$currencyLabel); ?> <?php echo htmlspecialchars($amountFormatted); ?></td>
      </tr>
          </tfoot>
    </table>
      </div>
  </div>
  </div>

  <?php if (!empty($inv['biv_notes'])) : ?>
  <div class="card mt-4">
      <div class="card-header bg-white border-0">
    <h5 class="mb-0"><?php echo $gL10n->get('BL_NOTES'); ?></h5>
      </div>
      <div class="card-body">
    <?php echo nl2br(htmlspecialchars((string)$inv['biv_notes'])); ?>
      </div>
  </div>
  <?php endif; ?>
</div>
<?php
$page->addHtml(ob_get_clean());
$page->show();
