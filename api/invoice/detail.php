<?php
global $gDb;
require_once(__DIR__ . '/../../../../adm_program/system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');
$endpointName = 'invoice/detail';

$currentUser = validateApiKey();
$currentUserId = (int) $currentUser->getValue('usr_id');


if (!function_exists('admidioApiLog')) {
  function admidioApiLog(string $message, array $context = array(), string $level = 'error'): void
  {
    global $gLogger;
    $prefix = '[Residents Messages API] ';
    if (isset($gLogger) && method_exists($gLogger, $level)) {
      $gLogger->{$level}($prefix . $message, $context);
      return;
    }
    if (isset($gLogger)) {
      $gLogger->error($prefix . $message, $context);
      return;
    }
    $encoded = empty($context) ? '' : ' ' . json_encode($context);
    error_log($prefix . $message . $encoded);
  }
}

if (!function_exists('admidioApiError')) {
  function admidioApiError(string $message, int $statusCode, array $context = array()): void
  {
    $context['status'] = $statusCode;
    admidioApiLog($message, $context);
    http_response_code($statusCode);
    echo json_encode(array('error' => $message));
    exit();
  }
}

$invId = admFuncVariableIsValid($_GET, 'id', 'string');
if ($invId === '') {
  admidioApiError('Invoice identifier missing', 400, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId
  ));
}

try{
  $invoiceData = new TableResidentsInvoice($gDb, $invId);

  $invoice = (object)[];
  if ($invoiceData->isNewRecord()) {
    admidioApiError('Invoice not found', 404, array(
      'endpoint' => $endpointName,
      'user_id' => $currentUserId
    ));
  }
  
  $ownerId = (int)$invoiceData->getValue('biv_usr_id');
  $isPaid = ((int)$invoiceData->getValue('biv_is_paid') === 1);
  $canPay = (!$isPaid && $ownerId === $currentUserId);
  $total = 0.0;
  $currencyFallback = $gSettingsManager->getString('system_currency');
  $currency = '';
  $itemRows = $invoiceData->getItems();
  $inv_items = [];
  foreach ($itemRows as $item) {
     if ($currency === '' && !empty($item['bii_currency'])) {
        $currency = (string)$item['bii_currency'];
      }
    $amount = (float)$item['bii_amount'];
    $total += $amount;

    $inv_items[] = [
      'name' => $item['bii_name'],
      'start_date' => billingFormatDateForApi((string)($item['bii_start_date'] ?? '')),
      'end_date' => billingFormatDateForApi((string)($item['bii_end_date'] ?? '')),
      'currency' => $currency,
      'amount' => $amount
    ];
  }
  if ($currency === '') {
    $currency = (string)$currencyFallback;
  }

  $invoice = [
    'id' => (int)$invoiceData->getValue('biv_id'),
    'user_name' => $ownerId > 0 ? billingFetchUserNameById($ownerId) : '',
    'biv_usr_id' => (int)$invoiceData->getValue('biv_usr_id'),
    'number' => (string)$invoiceData->getValue('biv_number'),
    'bpa_date' => billingFormatDateForApi((string)$invoiceData->getValue('bpa_date')),
    'biv_is_paid' => (int)$invoiceData->getValue('biv_is_paid'),
    'can_pay' => $canPay,
    'biv_type' => 'Open',
    'biv_date' => billingFormatDateForApi((string)$invoiceData->getValue('biv_date')),
    'biv_due_date' => billingFormatDateForApi((string)$invoiceData->getValue('biv_due_date')),
    'biv_start_date' => billingFormatDateForApi((string)$invoiceData->getValue('biv_start_date')),
    'biv_end_date' => billingFormatDateForApi((string)$invoiceData->getValue('biv_end_date')),
    'biv_notes' => (string)$invoiceData->getValue('biv_notes'),
    'currency_symbol' => $currency,
    'inv_items' => $inv_items,
    'inv_total' => $total
  ];

  echo json_encode([ 'invoice' => $invoice ]);
} catch (Exception $exception) {
  admidioApiError($exception->getMessage(), 500, array(
    'endpoint' => $endpointName,
    'user_id' => $currentUserId,
    'msg_uuid' => $getMsgUuid,
    'exception' => get_class($exception)
  ));
}
