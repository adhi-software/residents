<?php
/**
 * Server-side endpoint for the Residents charges DataTable.
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');
require_once(__DIR__ . '/../classes/TableResidentsCharge.php');

global $gDb, $gL10n, $gSettingsManager;

header('Content-Type: application/json');

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php';
if (!isUserAuthorizedForBilling($scriptUrl)) {
  http_response_code(403);
  echo json_encode(array('draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array(), 'error' => $gL10n->get('SYS_NO_RIGHTS')));
  exit;
}

$isAdmin = isBillingAdminBySettings();
if (!$isAdmin) {
  http_response_code(403);
  echo json_encode(array('draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array(), 'error' => $gL10n->get('SYS_NO_RIGHTS')));
  exit;
}

if (!tableExistsBILL(TBL_BL_CHARGES)) {
  echo json_encode(array('draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array()));
  exit;
}

try {
  $draw = admFuncVariableIsValid($_GET, 'draw', 'int', array('requireValue' => true));
  $start = admFuncVariableIsValid($_GET, 'start', 'int', array('requireValue' => true));
  $length = admFuncVariableIsValid($_GET, 'length', 'int', array('requireValue' => true));
  $searchValue = '';
  if (isset($_GET['search'])) {
    $searchValue = admFuncVariableIsValid($_GET['search'], 'value', 'string');
  }
} catch (AdmException $e) {
  echo json_encode(array('draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => array(), 'error' => $e->getMessage()));
  exit;
}

$length = $length < 0 ? 1000 : $length;
$start = max(0, $start);

$orderColumn = 'name';
$orderDirection = 'ASC';
// Include select and ID columns in mapping for sorting behavior when admin view is active
$columnMap = $isAdmin
  ? array(
    0 => 'select',
    1 => 'id',
    2 => 'name',
    3 => 'period',
    4 => 'amount',
    5 => 'roles',
    6 => 'actions'
  )
  : array(
    0 => 'name',
    1 => 'period',
    2 => 'amount',
    3 => 'roles',
    4 => 'actions'
  );
if (isset($_GET['order'][0]['column'])) {
  $columnIndex = (int)$_GET['order'][0]['column'];
  if (array_key_exists($columnIndex, $columnMap)) {
    $orderColumn = $columnMap[$columnIndex];
  }
}
if (isset($_GET['order'][0]['dir'])) {
  $dir = strtoupper((string)$_GET['order'][0]['dir']);
  if ($dir === 'DESC') {
    $orderDirection = 'DESC';
  }
}

$stmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_BL_CHARGES, array(), false);
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : array();
$recordsTotal = count($rows);

$searchNeedle = trim(mb_strtolower($searchValue));
if ($searchNeedle !== '') {
  $rows = array_filter($rows, static function ($row) use ($searchNeedle) {
    $name = mb_strtolower((string)($row['bch_name'] ?? ''));
    $period = mb_strtolower((string)($row['bch_period'] ?? ''));
    $amount = mb_strtolower((string)($row['bch_amount'] ?? ''));
    if ($name !== '' && mb_strpos($name, $searchNeedle) !== false) {
      return true;
    }
    if ($period !== '' && mb_strpos($period, $searchNeedle) !== false) {
      return true;
    }
    return $amount !== '' && mb_strpos($amount, $searchNeedle) !== false;
  });
}

$filteredRows = array_values($rows);
$recordsFiltered = count($filteredRows);

if ($recordsFiltered > 1) {
  $fieldMap = array(
    'id' => 'bch_id',
    'name' => 'bch_name',
    'period' => 'bch_period',
    'amount' => 'bch_amount',
    'roles' => 'bch_role_ids'
  );
  $field = $fieldMap[$orderColumn] ?? 'bch_name';

  usort($filteredRows, static function ($a, $b) use ($field, $orderDirection) {
    $aVal = $a[$field] ?? '';
    $bVal = $b[$field] ?? '';
    if ($field === 'bch_amount' || $field === 'bch_id') {
      $aVal = (float)$aVal;
      $bVal = (float)$bVal;
    } else {
      $aVal = mb_strtolower((string)$aVal);
      $bVal = mb_strtolower((string)$bVal);
    }
    if ($aVal == $bVal) {
      return 0;
    }
    $result = ($aVal < $bVal) ? -1 : 1;
    return ($orderDirection === 'DESC') ? -$result : $result;
  });
}

$pagedRows = ($length >= 0) ? array_slice($filteredRows, $start, $length) : $filteredRows;

$currencyLabel = '';
if (isset($gSettingsManager) && method_exists($gSettingsManager, 'getString')) {
  $currencyLabel = trim((string)$gSettingsManager->getString('system_currency'));
}
$rolesMap = billingGetRoleOptions();
$periodLabels = array();
foreach (TableRoles::getCostPeriods() as $key => $label) {
  $periodLabels[(string)$key] = $label;
}

$data = array();
$chargeModel = new TableResidentsCharge($gDb);
$deleteConfirm = htmlspecialchars($gL10n->get('BL_CHARGERS_DELETE_CONFIRM'), ENT_QUOTES, 'UTF-8');

foreach ($pagedRows as $row) {
  $chargeModel->clear();
  $chargeModel->setArray($row);

  $roleLabels = array();
  foreach ($chargeModel->getRoleIds() as $roleId) {
    if (isset($rolesMap[$roleId])) {
      $roleLabels[] = $rolesMap[$roleId];
    }
  }

  $amountValue = number_format((float)$chargeModel->getValue('bch_amount'), 2, '.', ',');
  $amountDisplay = $currencyLabel !== '' ? $currencyLabel . ' ' . $amountValue : $amountValue;

  $chargeId = (int)$chargeModel->getValue('bch_id');
  $editUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/charges/edit.php', array('id' => $chargeId));
  $deleteUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/charges/delete.php', array('id' => $chargeId));

  $actions = '<a class="admidio-icon-link" title="' . $gL10n->get('SYS_EDIT') . '" href="' . $editUrl . '"><i class="fas fa-edit"></i></a>';
  $actions .= ' <a class="admidio-icon-link text-danger" title="' . $gL10n->get('SYS_DELETE') . '" href="' . $deleteUrl . '" onclick="return confirm(\'' . $deleteConfirm . '\');"><i class="fas fa-trash"></i></a>';

  $periodValue = (string)$chargeModel->getValue('bch_period');
  $periodDisplay = ($periodValue !== '' && isset($periodLabels[$periodValue])) ? $periodLabels[$periodValue] : $periodValue;

  // Selection checkbox for admins (settings-based)
  $selectCol = $isAdmin ? '<input type="checkbox" class="billing-row-select" value="' . $chargeId . '" />' : '';

  $rowData = $isAdmin
    ? array(
      $selectCol,
      $chargeId,
      htmlspecialchars((string)$chargeModel->getValue('bch_name'), ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($periodDisplay, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($amountDisplay, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars(implode(', ', $roleLabels), ENT_QUOTES, 'UTF-8'),
      $actions
    )
    : array(
      htmlspecialchars((string)$chargeModel->getValue('bch_name'), ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($periodDisplay, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars($amountDisplay, ENT_QUOTES, 'UTF-8'),
      htmlspecialchars(implode(', ', $roleLabels), ENT_QUOTES, 'UTF-8'),
      $actions
    );

  $data[] = $rowData;
}

echo json_encode(
  array(
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data
  )
);
