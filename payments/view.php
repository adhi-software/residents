<?php
/**
    * View payment details and related items.
    */

require_once(__DIR__ . '/../common_function.php');
// Enforce valid login
if (file_exists(__DIR__ . '/../../../system/login_valid.php')) {
    require_once(__DIR__ . '/../../../system/login_valid.php');
} else {
    require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');
}

global $gDb, $gL10n, $gProfileFields, $gCurrentUser;

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php';
if (!isUserAuthorizedForBilling($scriptUrl)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$id = admFuncVariableIsValid($_GET, 'id', 'int');
$isAdmin = isPaymentAdmin();
$canViewAll = isBillingAdmin() || $isAdmin;

$paymentRecord = new TableResidentsPayment($gDb, $id);
if ($paymentRecord->isNewRecord()) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$ownerId = (int)$paymentRecord->getValue('bpa_usr_id');
if (!$canViewAll && $ownerId !== (int)$gCurrentUser->getValue('usr_id')) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$paymentData = array(
    'user_name' => $ownerId > 0 ? billingFetchUserNameById($ownerId) : '',
    'bpa_date' => date('d.m.Y H:i', strtotime((string)$paymentRecord->getValue('bpa_date'))),
    'bpa_pay_type' => (string)$paymentRecord->getValue('bpa_pay_type'),
    'bpa_pg_pay_method' => (string)$paymentRecord->getValue('bpa_pg_pay_method'),
    'bpa_trans_id' => (string)$paymentRecord->getValue('bpa_trans_id'),
    'bpa_bank_ref_no' => (string)$paymentRecord->getValue('bpa_bank_ref_no'),
    'bpa_id' => (int)$paymentRecord->getValue('bpa_id')
);

$page = new HtmlPage('plg-billing', $gL10n->get('BL_PAYMENT_DETAILS'));
$page->setHeadline($gL10n->get('BL_TAB_PAYMENTS'));
billingEnqueueStyles($page);

ob_start();
?>
<!-- Payment Details Section -->
<div class="card bg-light mb-4">
    <div class="card-header fw-bold"><?php echo $gL10n->get('BL_PAYMENT_DETAILS'); ?></div>
    <div class="card-body">
    <div class="row g-3 mb-3">
            <div class="col-md-4">
        <label class="form-label fw-bold"><?php echo $gL10n->get('BL_USER'); ?></label>
        <div class="form-control-plaintext"><?php echo htmlspecialchars((string)($paymentData['user_name'] ?? '')); ?></div>
            </div>
            <div class="col-md-8">
        <label class="form-label fw-bold"><?php echo $gL10n->get('BL_PAYMENT_DATE'); ?></label>
        <div class="form-control-plaintext"><?php echo htmlspecialchars((string)$paymentData['bpa_date']); ?></div>
            </div>
    </div>
    <div class="row g-3">
            <div class="col-md-4">
        <label class="form-label fw-bold"><?php echo $gL10n->get('BL_PAYMENT_TYPE'); ?></label>
        <div class="form-control-plaintext">
                    <?php 
                    $type = $paymentData['bpa_pay_type'] ?? '';
                    if ($type === 'Online') echo $gL10n->get('BL_PAYMENT_TYPE_ONLINE');
                    elseif ($type === 'Offline') echo $gL10n->get('BL_PAYMENT_TYPE_OFFLINE');
                    else echo htmlspecialchars((string)$type);
                    ?>
        </div>
            </div>
            <div class="col-md-8">
        <label class="form-label fw-bold"><?php echo $gL10n->get('BL_PAYMENT_METHOD'); ?></label>
        <div class="form-control-plaintext"><?php echo htmlspecialchars((string)$paymentData['bpa_pg_pay_method']); ?></div>
            </div>
    </div>
    </div>
</div>

<!-- Transaction Details Section -->
<div class="card bg-light mb-4">
    <div class="card-header fw-bold"><?php echo $gL10n->get('BL_TRANSACTION_DETAILS'); ?></div>
    <div class="card-body">
    <div class="row g-3">
            <div class="col-md-6">
        <label class="form-label fw-bold"><?php echo $gL10n->get('BL_TRANSACTION_ID'); ?></label>
        <div class="form-control-plaintext"><?php echo htmlspecialchars((string)($paymentData['bpa_trans_id'] ?? '-')); ?></div>
            </div>
            <div class="col-md-6">
        <label class="form-label fw-bold"><?php echo $gL10n->get('BL_BANK_REF_NO'); ?></label>
        <div class="form-control-plaintext"><?php echo htmlspecialchars((string)($paymentData['bpa_bank_ref_no'] ?? '-')); ?></div>
            </div>
    </div>
    </div>
</div>

<!-- Payment Items Section -->
<div class="card bg-light mb-4">
    <div class="card-header fw-bold"><?php echo $gL10n->get('BL_PAYMENT_ITEMS'); ?></div>
    <div class="card-body p-0">
    <div class="table-responsive">
            <table class="table mb-0">
        <thead>
                    <tr>
            <th style="width:25%"><?php echo $gL10n->get('BL_NUMBER'); ?></th>
            <th style="width:75%"><?php echo $gL10n->get('BL_AMOUNT'); ?></th>
                    </tr>
        </thead>
        <tbody>
                    <?php
                    $total = 0.0;
                    $currency = '';
                    $itemRows = $paymentRecord->getItems(true);
                    foreach ($itemRows as $item) {
                        $currency = $item['bpi_currency'] ?: $currency;
                        $amount = (float)$item['bpi_amount'];
                        $total += $amount;

                        $invoiceLink = '-';
                        if (!empty($item['bpi_inv_id'])) {
                            $label = $item['biv_number'] ?? ('#' . (int)$item['bpi_inv_id']);
                            $invoiceLink = '<a href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/invoices/detail.php', array('id' => $item['bpi_inv_id'])) . '">' . htmlspecialchars((string)$label) . '</a>';
            }
                        ?>
                        <tr>
                            <td><?php echo $invoiceLink; ?></td>
                            <td><?php echo htmlspecialchars((string)$item['bpi_currency']) . ' ' . number_format($amount, 2, '.', ''); ?></td>
                        </tr>
                        <?php
                    }
                    ?>
        </tbody>
            </table>
    </div>
    </div>
</div>

<div class="d-flex justify-content-end align-items-center mt-3 flex-wrap" style="gap: 20px;">
    <div>
    <strong><?php echo $gL10n->get('BL_PAYMENT_TOTAL'); ?>: <?php echo htmlspecialchars((string)$currency) . ' ' . number_format($total, 2, '.', ''); ?></strong>
    </div>
    <?php $exportUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/payments/pdf.php', array('id' => $id)); ?>
    <a href="<?php echo $exportUrl; ?>" class="btn btn-primary"><i class="fas fa-file-pdf"></i> <?php echo $gL10n->get('BL_DOWNLOAD_RECEIPT'); ?></a>
    <?php if ($isAdmin && $paymentData['bpa_pay_type'] !== 'Online') : ?>
            <?php $confirmText = htmlspecialchars($gL10n->get('BL_DELETE_PAYMENT_CONFIRM'), ENT_QUOTES, 'UTF-8'); ?>
            <form method="post" action="<?php echo ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/payments/delete.php'; ?>" class="d-inline" onsubmit="return confirm('<?php echo $confirmText; ?>');">
        <input type="hidden" name="id" value="<?php echo (int)$id; ?>" />
        <input type="hidden" name="admidio-csrf-token" value="<?php echo htmlspecialchars($gCurrentSession->getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>" />
        <button type="submit" class="btn btn-danger text-white"><i class="fas fa-trash"></i> <?php echo $gL10n->get('SYS_DELETE'); ?></button>
            </form>
    <?php endif; ?>
</div>
<div style="height: 50px;"></div>

<?php
$page->addHtml(ob_get_clean());
$page->show();

