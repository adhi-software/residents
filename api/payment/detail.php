<?php
global $gDb, $gCurrentUser;
require_once(__DIR__ . '/../../../../adm_program/system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');
$endpointName = 'payment/detail';

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');

$payId = admFuncVariableIsValid($_GET, 'id', 'int', ['defaultValue' => 0]);
if ($payId <= 0) {
    admidioApiError('Payment identifier missing', 400, [
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
    ]);
}

try {
    $paymentData = new TableResidentsPayment($gDb, $payId);

    if ($paymentData->isNewRecord()) {
        admidioApiError('Payment not found', 404, [
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'payment_id' => $payId
        ]);
    }

    // Permission check: admins can view all, regular users can only view their own
    $canViewAll = isBillingAdmin() || isPaymentAdmin();
    $ownerId = (int)$paymentData->getValue('bpa_usr_id');
    
    if (!$canViewAll && $ownerId !== $currentUserId) {
        admidioApiError('You do not have permission to view this payment', 403, [
            'endpoint' => $endpointName,
            'user_id' => $currentUserId,
            'payment_id' => $payId
        ]);
    }

    $total = 0.0;
    $currency = '';
    $itemRows = $paymentData->getItems(true);
    $pay_items = [];
    
    foreach ($itemRows as $item) {
        $currency = $item['bpi_currency'] ?: $currency;
        $amount = (float)$item['bpi_amount'];
        $total += $amount;

        $pay_items[] = [
            'inv_no' => $item['biv_number'] ?? ('#' . (int)$item['bpi_inv_id']),
            'currency' => $currency,
            'amount' => $amount
        ];
    }

    $payment = [
    'id' => (int)$paymentData->getValue('bpa_id'),
    'user_name' => $ownerId > 0 ? billingFetchUserNameById($ownerId) : '',
    'bpa_date' => (string)$paymentData->getValue('bpa_date', 'd.m.Y H:i'),
    'bpa_pay_type' => (string)$paymentData->getValue('bpa_pay_type'),
    'bpa_pg_pay_method' => (string)$paymentData->getValue('bpa_pg_pay_method'),
    'bpa_trans_id' => (string)$paymentData->getValue('bpa_trans_id'),
    'bpa_bank_ref_no' => (string)$paymentData->getValue('bpa_bank_ref_no'),
    'pay_items' => $pay_items,
    'pay_total' => $total
    ];

    echo json_encode(['payment' => $payment]);

} catch (Exception $exception) {
    admidioApiError($exception->getMessage(), 500, [
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'payment_id' => $payId,
    'exception' => get_class($exception)
    ]);
}

