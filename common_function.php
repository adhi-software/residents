<?php
/**
 * Common functions for the Admidio Residents plugin
 */

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/../../adm_program/system/bootstrap/constants.php');
require_once(__DIR__ . '/classes/ResidentsTables.php');

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

// Ensure organization id helper variable exists (plugin code uses $gCurrentOrgId)
if (!isset($gCurrentOrgId) && isset($gCurrentOrganization) && is_object($gCurrentOrganization)) {
  $gCurrentOrgId = (int)$gCurrentOrganization->getValue('org_id');
}

// Admidio 5.0 class aliases if necessary
if (defined('ADMIDIO_VERSION') && !version_compare(ADMIDIO_VERSION, '5.0', '<')) {
  if (class_exists('Admidio\\Roles\\Entity\\RolesRights')) {
  class_alias('Admidio\\Roles\\Entity\\RolesRights', 'RolesRights');
  }
  if (class_exists('Admidio\\Infrastructure\\Utils\\SecurityUtils')) {
  class_alias('Admidio\\Infrastructure\\Utils\\SecurityUtils', 'SecurityUtils');
  }
}

// define plugin specific constants
if (!defined('PLUGIN_FOLDER_BILL')) {
  define('PLUGIN_FOLDER_BILL', '/' . basename(__DIR__));
}
if (!defined('TBL_BL_INVOICES')) {
  define('TBL_BL_INVOICES', TABLE_PREFIX . '_bl_invoices');
}
if (!defined('TBL_BL_INVOICE_ITEMS')) {
  define('TBL_BL_INVOICE_ITEMS',  TABLE_PREFIX . '_bl_invoice_items');
}
if (!defined('TBL_BL_PAYMENTS')) {
  define('TBL_BL_PAYMENTS', TABLE_PREFIX . '_bl_payments');
}
if (!defined('TBL_BL_PAYMENT_ITEMS')) {
  define('TBL_BL_PAYMENT_ITEMS', TABLE_PREFIX . '_bl_payment_items');
}
if (!defined('TBL_BL_TRANS')) {
  define('TBL_BL_TRANS', TABLE_PREFIX . '_bl_trans');
}
if (!defined('TBL_BL_TRANS_ITEMS')) {
  define('TBL_BL_TRANS_ITEMS', TABLE_PREFIX . '_bl_trans_items');
}
if (!defined('TBL_BL_INVOICES_HIST')) {
  define('TBL_BL_INVOICES_HIST', TABLE_PREFIX . '_bl_invoices_hist');
}
if (!defined('TBL_BL_INVOICE_ITEMS_HIST')) {
  define('TBL_BL_INVOICE_ITEMS_HIST', TABLE_PREFIX . '_bl_invoice_items_hist');
}
if (!defined('TBL_BL_PAYMENTS_HIST')) {
  define('TBL_BL_PAYMENTS_HIST', TABLE_PREFIX . '_bl_payments_hist');
}
if (!defined('TBL_BL_PAYMENT_ITEMS_HIST')) {
  define('TBL_BL_PAYMENT_ITEMS_HIST', TABLE_PREFIX . '_bl_payment_items_hist');
}
if (!defined('TBL_BL_CHARGES_HIST')) {
  define('TBL_BL_CHARGES_HIST', TABLE_PREFIX . '_bl_charges_hist');
}
if (!defined('TBL_BL_PG_PAYMENTS')) {
  define('TBL_BL_PG_PAYMENTS', TABLE_PREFIX . '_bl_pg_payments');
}
if (!defined('TBL_BL_PG_PAYMENT_ITEMS')) {
  define('TBL_BL_PG_PAYMENT_ITEMS', TABLE_PREFIX . '_bl_pg_payment_items');
}
if (!defined('TBL_BL_CHARGES')) {
  define('TBL_BL_CHARGES', TABLE_PREFIX . '_bl_charges');
}
if (!defined('TBL_PLUGIN_PREFERENCES')) {
  define('TBL_PLUGIN_PREFERENCES', TABLE_PREFIX . '_plugin_preferences');
}
if (!defined('TBL_BL_DEVICES')) {
  define('TBL_BL_DEVICES', TABLE_PREFIX . '_bl_devices');
}
if (!defined('TBL_BL_DEVICES_HIST')) {
  define('TBL_BL_DEVICES_HIST', TABLE_PREFIX . '_bl_devices_hist');
}

// --- Invoice constants for reuse across plugin files ---
if (!defined('BL_STATUS_OPEN')) {
  define('BL_STATUS_OPEN', 'O');
}
if (!defined('BL_STATUS_CLOSED')) {
  define('BL_STATUS_CLOSED', 'C');
}
if (!defined('BL_TYPE_MEMBERSHIP_CHARGE')) {
  define('BL_TYPE_MEMBERSHIP_CHARGE', 'MC');
}

/**
 * Ensure the Residents plugin stylesheet is only added once per request.
 */
function billingEnqueueStyles(HtmlPage $page): void
{
  static $stylesAdded = false;
  if ($stylesAdded) {
  return;
  }
  $page->addCssFile(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.css');
  $stylesAdded = true;
}

/**
 * Reusable option lists for invoice status and type.
 */
function billingInvoiceStatusOptions(bool $includeEmpty = false): array
{
  global $gL10n;

  $openLabel = isset($gL10n) ? $gL10n->get('BL_OPEN') : 'Open';
  $closedLabel = isset($gL10n) ? $gL10n->get('BL_CLOSED') : 'Paid';

  $opts = array(
  BL_STATUS_OPEN   => $openLabel,
  BL_STATUS_CLOSED => $closedLabel,
  );
  if ($includeEmpty) {
  return array('' => '') + $opts;
  }
  return $opts;
}

function billingInvoiceTypeOptions(bool $includeEmpty = false): array
{
  $opts = array(
  BL_TYPE_MEMBERSHIP_CHARGE => 'Membership charge',
  );
  if ($includeEmpty) {
  return array('' => '') + $opts;
  }
  return $opts;
}

function emailAttachmentLimitPayload(): array
{
  $maxBytes = (int) Email::getMaxAttachmentSize(Email::SIZE_UNIT_BYTE, 0);

  return array(
  'max_total_bytes' => $maxBytes,
  'max_total_mebibytes' => Email::getMaxAttachmentSize(Email::SIZE_UNIT_MEBIBYTE, 2),
  'enabled' => $maxBytes > 0,
  );
}

if (!function_exists('billingFetchUserNameById')) {
  function billingFetchUserNameById(int $userId): string
  {
  global $gDb, $gProfileFields;

  $lnId = (int) $gProfileFields->getProperty('LAST_NAME', 'usf_id');
  $fnId = (int) $gProfileFields->getProperty('FIRST_NAME', 'usf_id');
  $sql = "SELECT u.usr_login_name,
             CONCAT_WS(' ', fn.usd_value, ln.usd_value) AS full_name
          FROM " . TBL_USERS . ' u
       LEFT JOIN ' . TBL_USER_DATA . ' ln ON ln.usd_usr_id = u.usr_id AND ln.usd_usf_id = ?
       LEFT JOIN ' . TBL_USER_DATA . ' fn ON fn.usd_usr_id = u.usr_id AND fn.usd_usf_id = ?
         WHERE u.usr_id = ?';
  $stmt = $gDb->queryPrepared($sql, array($lnId, $fnId, $userId));
  $row = $stmt->fetch();
  if ($row) {
      $name = trim((string)($row['full_name'] ?? ''));
      if ($name !== '') {
    return $name;
      }
      $login = trim((string)($row['usr_login_name'] ?? ''));
      if ($login !== '') {
    return $login;
      }
  }

  return 'User #' . $userId;
  }
}

if (!function_exists('billingFetchUserEmailById')) {
  function billingFetchUserEmailById(int $userId): string
  {
  global $gDb, $gProfileFields;

  $emailUsfId = (int)$gProfileFields->getProperty('EMAIL', 'usf_id');
  if ($emailUsfId <= 0) {
      return '';
  }

  $stmt = $gDb->queryPrepared(
      'SELECT usd_value FROM ' . TBL_USER_DATA . ' WHERE usd_usr_id = ? AND usd_usf_id = ?',
      array($userId, $emailUsfId)
  );
  $email = trim((string)$stmt->fetchColumn());

  return $email;
  }
}

function billingResolveDate(?string $value, ?string $fallback = null): string
{
  $value = trim((string)$value);
  if ($value !== '') {
  $dt = DateTime::createFromFormat('Y-m-d', $value);
  if ($dt instanceof DateTime) {
      return $dt->format('Y-m-d');
  }
  }

  $fallback = trim((string)$fallback);
  if ($fallback !== '') {
  $fallbackDt = DateTime::createFromFormat('Y-m-d', $fallback);
  if ($fallbackDt instanceof DateTime) {
      return $fallbackDt->format('Y-m-d');
  }
  }

  return date('Y-m-d');
}

if (!function_exists('billingFormatDateForUi')) {
  function billingFormatDateForUi($value): string
  {
    global $gSettingsManager;

    $s = trim((string)$value);
    if ($s === '') {
      return '';
    }

    $format = (isset($gSettingsManager) && method_exists($gSettingsManager, 'getString'))
      ? (string)$gSettingsManager->getString('system_date')
      : 'Y-m-d';

    try {
      $candidate = strlen($s) >= 19 ? substr($s, 0, 19) : $s;
      $dt = DateTime::createFromFormat('Y-m-d H:i:s', $candidate);
      if (!$dt) {
        $dt = DateTime::createFromFormat('Y-m-d', substr($s, 0, 10));
      }
      if (!$dt) {
        $dt = new DateTime($s);
      }
      return $dt->format($format);
    } catch (Throwable $e) {
      return strlen($s) >= 10 ? substr($s, 0, 10) : $s;
    }
  }
}

if (!function_exists('billingFormatDateForInput')) {
  function billingFormatDateForInput($value): string
  {
    $s = trim((string)$value);
    if ($s === '') {
      return '';
    }

    try {
      $candidate = strlen($s) >= 19 ? substr($s, 0, 19) : $s;
      $dt = DateTime::createFromFormat('Y-m-d H:i:s', $candidate);
      if (!$dt) {
        $dt = DateTime::createFromFormat('Y-m-d', substr($s, 0, 10));
      }
      if (!$dt) {
        $dt = new DateTime($s);
      }
      return $dt->format('Y-m-d');
    } catch (Throwable $e) {
      return strlen($s) >= 10 ? substr($s, 0, 10) : $s;
    }
  }
}

if (!function_exists('billingFormatDateForApi')) {
  function billingFormatDateForApi($value): string
  {
    $s = trim((string)$value);
    if ($s === '') {
      return '';
    }

    try {
      $candidate = strlen($s) >= 19 ? substr($s, 0, 19) : $s;
      $dt = DateTime::createFromFormat('Y-m-d H:i:s', $candidate);
      if (!$dt) {
        $dt = DateTime::createFromFormat('Y-m-d', substr($s, 0, 10));
      }
      if (!$dt) {
        $dt = new DateTime($s);
      }
      return $dt->format('Y-m-d');
    } catch (Throwable $e) {
      return strlen($s) >= 10 ? substr($s, 0, 10) : $s;
    }
  }
}

function billingChargePeriodMonths(?string $period): int
{
  $period = trim((string)$period);
  if ($period === '') {
  return 1;
  }
  if (!is_numeric($period)) {
  return 1;
  }
  $code = (int)$period;
  if ($code > 0) {
  $months = (int)round(12 / max($code, 1));
  return $months > 0 ? $months : 1;
  }
  if ($code === -1) {
  return 0;
  }
  return 1;
}

function billingFetchChargeDefinitions(): array
{
  global $gDb;

  static $cache = null;
  if ($cache !== null) {
  return $cache;
  }

  if (!tableExistsBILL(TBL_BL_CHARGES)) {
  $cache = array();
  return $cache;
  }

  $cache = array();
  $stmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_BL_CHARGES . ' ORDER BY bch_name ASC', array());
  if ($stmt !== false) {
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $period = (string)($row['bch_period'] ?? '');
      $cache[] = array(
    'id' => (int)($row['bch_id'] ?? 0),
    'name' => (string)($row['bch_name'] ?? ''),
    'amount' => (float)($row['bch_amount'] ?? 0.0),
    'period' => $period,
    'period_months' => billingChargePeriodMonths($period),
    'role_ids' => billingDeserializeRoleIds((string)($row['bch_role_ids'] ?? ''))
      );
  }
  }

  return $cache;
}

function billingFetchUserRoleMap(array $userIds, string $referenceDate): array
{
  global $gDb;

  $map = array();
  if (empty($userIds)) {
  return $map;
  }

  $uniqueUserIds = array_values(array_unique(array_map('intval', $userIds)));
  $placeholders = implode(',', array_fill(0, count($uniqueUserIds), '?'));
  $params = $uniqueUserIds;
  $params[] = $referenceDate;
  $params[] = $referenceDate;

  $sql = 'SELECT mem_usr_id, mem_rol_id
      FROM ' . TBL_MEMBERS . '
           WHERE mem_usr_id IN (' . $placeholders . ')
             AND mem_begin <= ?
             AND (mem_end IS NULL OR mem_end >= ?)';

  $stmt = $gDb->queryPrepared($sql, $params);
  if ($stmt !== false) {
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $uid = (int)($row['mem_usr_id'] ?? 0);
      $rid = (int)($row['mem_rol_id'] ?? 0);
      if ($uid > 0 && $rid > 0) {
    $map[$uid][$rid] = $rid;
      }
  }
  }

  foreach ($map as $uid => $roleSet) {
  $map[$uid] = array_values($roleSet);
  }

  return $map;
}

function billingGetActiveRoleIdsForUser(int $userId): array
{
  if ($userId <= 0) {
      return array();
  }

  $roleMap = billingFetchUserRoleMap(array($userId), date('Y-m-d'));
  return $roleMap[$userId] ?? array();
}

function residentsMessageIsVisibleToUser(TableMessage $message, int $userId): bool
{
  global $gDb;

  if ($userId <= 0) {
  return false;
  }

  if ((int)$message->getValue('msg_usr_id_sender') === $userId) {
  return true;
  }

  if ($message->getValue('msg_type') === TableMessage::MESSAGE_TYPE_PM) {
  $statement = $gDb->queryPrepared(
      'SELECT 1 FROM ' . TBL_MESSAGES_RECIPIENTS . ' WHERE msr_msg_id = ? AND msr_usr_id = ? LIMIT 1',
      array((int)$message->getValue('msg_id'), $userId)
  );
  if ($statement->fetchColumn()) {
      return true;
  }
  }

  return false;
}

function residentsMessageCanDelete(TableMessage $message, int $userId): bool
{
  if ($userId <= 0) {
  return false;
  }

  if ((int)$message->getValue('msg_usr_id_sender') === $userId) {
  return true;
  }

  return residentsMessageIsVisibleToUser($message, $userId);
}

function billingFilterChargesForUser(array $chargeDefinitions, array $userRoleIds, ?int $groupFilter = null): array
{
  if (empty($chargeDefinitions)) {
  return array();
  }

  $userRoles = array_map('intval', $userRoleIds);
  $matches = array();
  foreach ($chargeDefinitions as $charge) {
  $chargeRoles = $charge['role_ids'] ?? array();
  // If a charge has no roles assigned, treat it as global (applies to all active users)
  if (empty($chargeRoles)) {
      $matches[] = $charge;
      continue;
  }
  if ($groupFilter !== null && $groupFilter > 0 && !in_array($groupFilter, $chargeRoles, true)) {
      continue;
  }
  if (empty(array_intersect($userRoles, $chargeRoles))) {
      continue;
  }
  $matches[] = $charge;
  }

  return $matches;
}

/**
 * Calculate monthly membership fee for a user within optional group context.
 * Priority order for base amount & period:
 *   1. Plugin preference section role_<groupId>: keys contribution, period
 *   2. Fallback plugin preference pricing.charge (already assumed monthly)
 * Period normalization supported values (case-insensitive): month, monthly; year, yearly, annual; quarter, quarterly.
 * If period not recognized, assume monthly.
 * Returns array [ 'base' => amountString, 'period' => periodString, 'monthly' => monthlyAmountString ]
 */
function billingCalculateUserCharge(int $userId, ?int $groupId = null): array
{
  $empty = array(
  'base' => number_format(0, 2, '.', ''),
  'period' => '',
  'monthly' => number_format(0, 2, '.', ''),
  'period_code' => null,
  'quantity_factor' => 1.0
  );

  $chargeDefinitions = billingFetchChargeDefinitions();
  if (empty($chargeDefinitions)) {
  return $empty;
  }

  $roleMap = billingFetchUserRoleMap(array($userId), date('Y-m-d'));
  $userRoles = $roleMap[$userId] ?? array();
  if (empty($userRoles)) {
  return $empty;
  }

  $matches = billingFilterChargesForUser($chargeDefinitions, $userRoles, $groupId);
  if (empty($matches)) {
  return $empty;
  }

  $total = 0.0;
  $periodCode = null;
  if (count($matches) === 1) {
  $periodValue = $matches[0]['period'] ?? '';
  if ($periodValue !== '' && is_numeric($periodValue)) {
      $periodCode = (int)$periodValue;
  }
  }

  foreach ($matches as $match) {
  $total += (float)($match['amount'] ?? 0.0);
  }

  $periodLabel = $periodCode !== null ? TableRoles::getCostPeriods($periodCode) : 'Charges';
  $formatted = number_format($total, 2, '.', '');

  return array(
  'base' => $formatted,
  'period' => $periodLabel,
  'monthly' => $formatted,
  'period_code' => $periodCode,
  'quantity_factor' => 1.0
  );
}

/**
 * Check if a table exists (works for MySQL and PostgreSQL when default schema equals DB_NAME/public).
 * Note: PostgreSQL stores unquoted identifiers in lowercase, so we use LOWER() for comparisons.
 */
function tableExistsBILL(string $tableName): bool
{
  global $gDb, $gDbType;

  if ($gDbType === 'pgsql') {
  $sql = 'SELECT COUNT(*) FROM information_schema.tables WHERE table_catalog = ? AND table_schema = current_schema() AND LOWER(table_name) = LOWER(?)';
  $stmt = $gDb->queryPrepared($sql, array(DB_NAME, $tableName));
  } else {
  $sql = 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?';
  $stmt = $gDb->queryPrepared($sql, array(DB_NAME, $tableName));
  }
  return (int)$stmt->fetchColumn() > 0;
}

/**
 * Check if an index exists.
 * Note: PostgreSQL stores unquoted identifiers in lowercase, so we use LOWER() for comparisons.
 */
function indexExistsBILL(string $tableName, string $indexName): bool
{
  global $gDb, $gDbType;

  if ($gDbType === 'pgsql') {
  $sql = 'SELECT COUNT(*) FROM pg_indexes WHERE schemaname = current_schema() AND LOWER(tablename) = LOWER(?) AND LOWER(indexname) = LOWER(?)';
  $stmt = $gDb->queryPrepared($sql, array($tableName, $indexName));
  } else {
  $sql = 'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?';
  $stmt = $gDb->queryPrepared($sql, array(DB_NAME, $tableName, $indexName));
  }
  return (int)$stmt->fetchColumn() > 0;
}

/**
 * Check if a column exists.
 * Note: PostgreSQL stores unquoted identifiers in lowercase, so we use LOWER() for comparisons.
 */
function columnExistsBILL(string $tableName, string $columnName): bool
{
  global $gDb, $gDbType;

  if ($gDbType === 'pgsql') {
  $sql = 'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = current_schema() AND LOWER(table_name) = LOWER(?) AND LOWER(column_name) = LOWER(?)';
  $stmt = $gDb->queryPrepared($sql, array($tableName, $columnName));
  } else {
  $sql = 'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?';
  $stmt = $gDb->queryPrepared($sql, array(DB_NAME, $tableName, $columnName));
  }
  return (int)$stmt->fetchColumn() > 0;
}



/**
 * Check if a FK constraint exists.
 * Note: PostgreSQL stores unquoted identifiers in lowercase, so we use LOWER() for comparisons.
 */
function constraintExistsBILL(string $tableName, string $constraintName): bool
{
  global $gDb, $gDbType;

  if ($gDbType === 'pgsql') {
  $sql = 'SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = current_schema() AND LOWER(table_name) = LOWER(?) AND LOWER(constraint_name) = LOWER(?)';
  $stmt = $gDb->queryPrepared($sql, array($tableName, $constraintName));
  } else {
  $sql = 'SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = ? AND table_name = ? AND constraint_name = ?';
  $stmt = $gDb->queryPrepared($sql, array(DB_NAME, $tableName, $constraintName));
  }
  return (int)$stmt->fetchColumn() > 0;
}

/**
 * Simple authorization check based on menu rights of this plugin.
 */
function isUserAuthorizedForBilling(string $scriptName): bool
{
  global $gDb, $gCurrentUser;

  $sql = 'SELECT men_id, men_com_id FROM ' . TBL_MENU . ' WHERE men_url = ?';
  $stmt = $gDb->queryPrepared($sql, array($scriptName));
  if ($stmt->rowCount() !== 1) {
  return false;
  }
  $row = $stmt->fetch();

  $displayMenu = new RolesRights($gDb, 'menu_view', (int)$row['men_id']);
  $rolesDisplayRight = $displayMenu->getRolesIds();
  return count($rolesDisplayRight) === 0 || $displayMenu->hasRight($gCurrentUser->getRoleMemberships());
}

/**
 * Check if current user is a Residents admin (belongs to any configured admin role) or Admidio administrator.
 */
function isBillingAdmin(): bool
{
  // Plugin admin is defined by configured Admin roles.
  // If none are configured, Admidio administrators may access admin features.
  global $gCurrentUser;
  $gCurrentUser->getRoleMemberships();
  $config = billingReadConfig();
  $roles = $config['access']['admin_roles'] ?? array();
  if (empty($roles)) {
  return isset($gCurrentUser) && $gCurrentUser->isAdministrator();
  }
  foreach ($roles as $roleId) {
  if ($gCurrentUser->isMemberOfRole((int)$roleId)) {
      return true;
  }
  }
  return false;
}

/**
 * Check admin access strictly against configured admin roles (no Admidio admin fallback).
 */
function isBillingAdminBySettings(): bool
{
  global $gCurrentUser;

  if (!isset($gCurrentUser) || !is_object($gCurrentUser)) {
  return false;
  }

  $config = billingReadConfig();
  $roles = $config['access']['admin_roles'] ?? array();

  foreach ($roles as $roleId) {
  if ($gCurrentUser->isMemberOfRole((int)$roleId)) {
      return true;
  }
  }
  return false;
}

/**
 * Check if the current user is a configured Payment Admin (or Billing Admin).
 */
function isPaymentAdmin(): bool
{
  global $gCurrentUser;

  $config = billingReadConfig();
  $roles = $config['access']['payment_admin_roles'] ?? array();
  
  if (empty($roles)) {
  return false;
  }
  
  foreach ($roles as $roleId) {
  if ($gCurrentUser->isMemberOfRole((int)$roleId)) {
      return true;
  }
  }
  return false;
}

/**
 * Check if the current user is leader/administrator of the configured owner group.
 */
// isBillingOwnersLeader removed: no longer used after settings simplification

/**
 * Read plugin config (preferences) stored with BL__ prefix.
 */
function billingReadConfig(): array
{
  global $gDb, $gCurrentOrgId;

  $config = array(
  'access' => array(
      'admin_roles' => array()
  ),
  'owners' => array(
      'group_id' => 0
  ),
  'pricing' => array(
      'charge' => '',
      'period' => '12'
  ),
  'defaults' => array(
      'invoice_note' => '',
      'due_days' => 15
  ),
  'payment_gateway' => array(
      'name' => '',
      'currency' => '',
      'merchant_id' => '',
      'working_key' => '',
      'access_code' => '',
      'redirect_url' => '',
      'cancel_url' => '',
      'gateway_url' => '',
      'timeout' => 15
  )
  );

  $decodeValue = static function (string $value) {
  if (substr($value, 0, 2) === '((' && substr($value, -2) === '))') {
      $val = substr($value, 2, -2);
      return $val === '' ? array() : explode('#_#', $val);
  }
  return $value;
  };

  $sql = 'SELECT prf_name, prf_value FROM ' . TBL_PREFERENCES . ' WHERE prf_name LIKE ? AND prf_org_id = ?';
  $st = $gDb->queryPrepared($sql, array('BL__%', $gCurrentOrgId));
  while ($row = $st->fetch()) {
  $parts = explode('__', $row['prf_name']);
  if (count($parts) >= 3) {
      $section = $parts[1];
      $key = $parts[2];
      $config[$section][$key] = $decodeValue($row['prf_value']);
  }
  }

  return $config;
}

/**
 * Build owner dropdown options depending on the provided group id.
 * If a group id is provided only active members of that role are returned.
 * Otherwise, all valid users with at least one active role membership are returned.
 * Users marked as "Former" (no active role memberships) are excluded.
 *
 * @param int|string $groupId Role id filter (optional)
 *
 * @return array<int,string>
 */
function billingGetOwnerOptions($groupId): array
{
  global $gDb, $gProfileFields;
  $options = array();
  
  // Base query with members filter to exclude Former users (users with no active role memberships)
  $select = 'SELECT DISTINCT u.usr_id, u.usr_login_name,
        fn.usd_value AS firstname, ln.usd_value AS lastname
      FROM ' . TBL_USERS . ' u
      INNER JOIN ' . TBL_MEMBERS . ' m ON m.mem_usr_id = u.usr_id AND m.mem_begin <= ? AND m.mem_end > ?
      INNER JOIN ' . TBL_ROLES . ' r ON r.rol_id = m.mem_rol_id AND r.rol_valid = true
      LEFT JOIN ' . TBL_USER_DATA . ' ln ON ln.usd_usr_id = u.usr_id AND ln.usd_usf_id = ' . (int) $gProfileFields->getProperty('LAST_NAME', 'usf_id') . '
      LEFT JOIN ' . TBL_USER_DATA . ' fn ON fn.usd_usr_id = u.usr_id AND fn.usd_usf_id = ' . (int) $gProfileFields->getProperty('FIRST_NAME', 'usf_id') . '
      WHERE u.usr_valid = true';

  $params = array(DATE_NOW, DATE_NOW);
  
  // If specific group/role is provided, add additional filter
  if (is_numeric($groupId) && (int) $groupId > 0) {
    $select .= ' AND m.mem_rol_id = ?';
    $params[] = (int) $groupId;
  }

  $select .= ' ORDER BY lastname, firstname, u.usr_login_name';

  $stmt = $gDb->queryPrepared($select, $params);
  if ($stmt !== false) {
    while ($row = $stmt->fetch()) {
      $f = trim((string)($row['firstname'] ?? ''));
      $l = trim((string)($row['lastname'] ?? ''));
      $displayName = trim($f . ' ' . $l);
      if ($displayName === '') {
        $displayName = trim((string) ($row['usr_login_name'] ?? ''));
      }
      $options[(int) $row['usr_id']] = $displayName;
    }
  }
  return $options;
}

/**
 * Ensure a user is included in the owner options array.
 * If the user is not already in the array (e.g., they are a "Former" user),
 * their name will be fetched and added.
 *
 * @param array $options The existing owner options array (modified by reference)
 * @param int $userId The user ID to ensure is in the options
 * @return void
 */
function billingEnsureUserInOptions(array &$options, int $userId): void
{
  if ($userId <= 0 || isset($options[$userId])) {
    return;
  }
  
  // Fetch the user's name and add them to the options
  $userName = billingFetchUserNameById($userId);
  if ($userName === '') {
    $userName = 'User #' . $userId;
  }
  $options[$userId] = $userName;
}

/**
 * Fetch all active roles for dropdowns (organization + global roles).
 */
function billingGetRoleOptions(): array
{
  global $gDb, $gCurrentOrganization;
  $roles = array();
  $orgId = isset($gCurrentOrganization) ? (int)$gCurrentOrganization->getValue('org_id') : 0;
  // Follow Admidio core behavior: event roles live in the EVENTS category and should not show up in "group" dropdowns.
  $sql = 'SELECT rol_id, rol_name
            FROM ' . TBL_ROLES . '
      INNER JOIN ' . TBL_CATEGORIES . '
              ON cat_id = rol_cat_id
           WHERE rol_valid = true
             AND cat_name_intern <> \'EVENTS\'
             AND (  cat_org_id = ?
                 OR cat_org_id IS NULL )
        ORDER BY cat_sequence, rol_name';
  $stmt = $gDb->queryPrepared($sql, array($orgId));
  if ($stmt !== false) {
  while ($row = $stmt->fetch()) {
      $roles[(int)$row['rol_id']] = (string)$row['rol_name'];
  }
  }
  return $roles;
}

function billingSerializeRoleIds(array $roleIds): string
{
  $clean = array();
  foreach ($roleIds as $rid) {
  $rid = (int)$rid;
  if ($rid > 0) {
      $clean[$rid] = $rid;
  }
  }
  if (empty($clean)) {
  return '';
  }
  return implode(',', array_values($clean));
}

function billingDeserializeRoleIds(?string $value): array
{
  if ($value === null || trim($value) === '') {
  return array();
  }
  $parts = explode(',', $value);
  $ids = array();
  foreach ($parts as $part) {
  $rid = (int)trim($part);
  if ($rid > 0) {
      $ids[$rid] = $rid;
  }
  }
  return array_values($ids);
}

/**
 * Write config back to DB with prefix BL__
 */
function billingWriteConfig(array $config): void
{
  global $gDb, $gCurrentOrgId;
  foreach ($config as $section => $data) {
    foreach ($data as $key => $value) {
      $plpName = 'BL__' . $section . '__' . $key;
      if (is_array($value)) {
        $value = '((' . implode('#_#', $value) . '))';
      }
      $sqlSel = 'SELECT prf_id FROM ' . TBL_PREFERENCES . ' WHERE prf_name = ? AND prf_org_id = ?';
      $sel = $gDb->queryPrepared($sqlSel, array($plpName, $gCurrentOrgId), false);
      if ($sel === false) {
        continue;
      }
      $row = $sel->fetchObject();
      if (isset($row->prf_id)) {
        $gDb->queryPrepared('UPDATE ' . TBL_PREFERENCES . ' SET prf_value = ? WHERE prf_id = ?', array($value, $row->prf_id), false);
      } else {
        $gDb->queryPrepared('INSERT INTO ' . TBL_PREFERENCES . ' (prf_org_id, prf_name, prf_value) VALUES (?,?,?)', array($gCurrentOrgId, $plpName, $value), false);
      }
    }
  }
  
}

/**
 * Remove all stored Residents plugin preferences (BL__ prefix) for the current org.
 */
function billingDeleteConfig(): void
{
  global $gDb, $gCurrentOrgId;
  $gDb->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_org_id = ? AND prf_name LIKE ?', array($gCurrentOrgId, 'BL__%'), false);
}

function billingGetDefaultInvoiceNote(?array $config = null): string
{
  global $gL10n;
  if ($config === null) {
  $config = billingReadConfig();
  }
  $note = trim((string)($config['defaults']['invoice_note'] ?? ''));
  if ($note === '' && isset($gL10n)) {
  $note = $gL10n->get('BL_DEFAULT_NOTE_TEXT');
  }
  return $note;
}

/**
 * Create plugin menu item under Plugins if missing.
 */
function ensureBillingMenuItem(): void
{
  // Use Ramsey\Uuid if available in Admidio core
  if (!class_exists('Ramsey\Uuid\Uuid')) {
  return;
  }
  $scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php';

  global $gDb, $gL10n;
  $menuTitle = $gL10n->get('BL_TITLE');
  $menuDescription = $gL10n->get('BL_DESC');
  $exists = $gDb->queryPrepared('SELECT men_id FROM ' . TBL_MENU . ' WHERE men_url = ?', array($scriptUrl), false);
  if ($exists !== false && $exists->rowCount() > 0) {
  return;
  }

  $pluginsRow = $gDb->queryPrepared('SELECT men_id FROM ' . TBL_MENU . ' WHERE men_name_intern = ?', array('plugins'), false);
  if ($pluginsRow === false) {
    return;
  }
  $menIdPlugins = (int)$pluginsRow->fetch()['men_id'];

  $sequence = 0;
  $seqStmt = $gDb->queryPrepared('SELECT men_order FROM ' . TBL_MENU . ' WHERE men_men_id_parent = ? ORDER BY men_order ASC', array($menIdPlugins), false);
  if ($seqStmt !== false) {
    while ($r = $seqStmt->fetch()) {
      $sequence = (int)$r['men_order'];
    }
  }
  $orderNew = $sequence + 1;

  $uuid = Ramsey\Uuid\Uuid::uuid4();
  $sql = 'INSERT INTO ' . TBL_MENU . ' (men_com_id, men_men_id_parent, men_uuid, men_node, men_order, men_standard, men_name_intern, men_url, men_icon, men_name, men_description)
      VALUES (NULL, ?, ?, 0, ?, false, ?, ?, ?, ?, ?)';
  $params = array(
  $menIdPlugins,
  (string)$uuid,
  $orderNew,
  'residents',
  $scriptUrl,
  'fa-file-invoice-dollar',
  $menuTitle,
  $menuDescription
  );
  $gDb->queryPrepared($sql, $params, false);
  if (isset($GLOBALS['gCurrentSession'])) {
  $GLOBALS['gCurrentSession']->reloadAllSessions();
  }
}

function removeBillingMenuItem(): void
{
  global $gDb;
  $scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php';
  $gDb->queryPrepared('DELETE FROM ' . TBL_MENU . ' WHERE men_url = ?', array($scriptUrl), false);
  if (isset($GLOBALS['gCurrentSession'])) {
  $GLOBALS['gCurrentSession']->reloadAllSessions();
  }
}

function billingGetInvoiceTotals(int $invoiceId): array
{
  global $gDb, $gSettingsManager;

  $total = 0.0;
  $currency = null;

  $stmt = $gDb->queryPrepared(
    'SELECT bii_amount, bii_currency FROM ' . TBL_BL_INVOICE_ITEMS . ' WHERE bii_inv_id = ?',
    array($invoiceId),
    false
  );

  if ($stmt !== false) {
    while ($row = $stmt->fetch()) {
      $val = (string)($row['bii_amount'] ?? '0');
      // Remove everything except digits, dots, commas, minus
      $val = preg_replace('/[^0-9.,-]/', '', $val);
      // Remove commas (assuming they are thousands separators)
      $amount = (float)str_replace(',', '', $val);
      
      $total += $amount;
      if ($currency === null && !empty($row['bii_currency'])) {
        $currency = (string)$row['bii_currency'];
      }
    }
  }

  if ($currency === null) {
    $currency = $gSettingsManager->getString('system_currency');
  }

  return array(
    'amount' => $total,
    'currency' => $currency
  );
}

function billingNextInvoiceNumberIndex(): int
{
  global $gDb, $gDbType;

  if (!tableExistsBILL(TBL_BL_INVOICES) || !columnExistsBILL(TBL_BL_INVOICES, 'biv_number_index')) {
  return 1;
  }

  // Some legacy/dev data may have biv_number_index unset (0) while biv_number is numeric.
  // Use the greater of (max index) and (max numeric number) to avoid duplicate-key errors.
  if ($gDbType === 'pgsql') {
  $sql = "SELECT GREATEST(\n"
    . "  COALESCE(MAX(biv_number_index), 0),\n"
    . "  COALESCE(MAX(CASE WHEN biv_number ~ '^[0-9]+$' THEN CAST(biv_number AS INTEGER) ELSE 0 END), 0)\n"
    . ")\n"
    . "FROM " . TBL_BL_INVOICES;
  } else {
  $sql = "SELECT GREATEST(\n"
    . "  COALESCE(MAX(biv_number_index), 0),\n"
    . "  COALESCE(MAX(CASE WHEN biv_number REGEXP '^[0-9]+$' THEN CAST(biv_number AS UNSIGNED) ELSE 0 END), 0)\n"
    . ")\n"
    . "FROM " . TBL_BL_INVOICES;
  }

  $stmt = $gDb->query($sql);
  if ($stmt === false) {
  return 1;
  }
  $next = (int)$stmt->fetchColumn() + 1;
  return $next > 0 ? $next : 1;
}

function billingFormatInvoiceNumber(int $index): string
{
  if ($index < 1) {
  $index = 1;
  }
  return (string)$index;
}

function billingBuildInvoicePreviewData(int $groupId, array $options = array()): array
{
  global $gDb, $gSettingsManager;

  $defaultStart = date('Y-m-01');
  $startDate = billingResolveDate($options['start_date'] ?? '', $defaultStart);
  $invoiceDate = billingResolveDate($options['invoice_date'] ?? '', date('Y-m-d'));
  // Use invoice date as the reference for membership activity checks, so back-billing includes current active users
  $referenceDate = $invoiceDate;
  $note = trim((string)($options['note'] ?? ''));
  $userFilterId = isset($options['user_id']) ? (int)$options['user_id'] : 0;

  $currencyLabel = '';
  if (isset($gSettingsManager) && method_exists($gSettingsManager, 'getString')) {
  $currencyLabel = trim((string)$gSettingsManager->getString('system_currency'));
  }
  if ($currencyLabel === '') {
  $currencyLabel = 'USD';
  }

  $chargeDefinitions = billingFetchChargeDefinitions();
  $roleFilter = array();
  $hasGlobalCharges = false;
  foreach ($chargeDefinitions as $chargeDef) {
  $roleIds = $chargeDef['role_ids'];
  if (empty($roleIds)) {
      $hasGlobalCharges = true;
  } else {
      foreach ($roleIds as $rid) {
    $roleFilter[$rid] = $rid;
      }
  }
  }

  $users = array();
  if ($userFilterId > 0) {
  $users[] = $userFilterId;
  } else {
  $params = array($referenceDate, $referenceDate);
  if ($groupId > 0) {
      $sql = 'SELECT DISTINCT u.usr_id FROM ' . TBL_USERS . ' u
          INNER JOIN ' . TBL_MEMBERS . ' m ON m.mem_usr_id = u.usr_id
          INNER JOIN ' . TBL_ROLES . ' r ON r.rol_id = m.mem_rol_id AND r.rol_valid = true
          WHERE u.usr_valid = true
      AND m.mem_begin <= ?
      AND (m.mem_end IS NULL OR m.mem_end >= ?)
      AND m.mem_rol_id = ?
          ORDER BY u.usr_id';
      $params[] = (int)$groupId;
  } elseif (!empty($roleFilter) && !$hasGlobalCharges) {
      $placeholders = implode(',', array_fill(0, count($roleFilter), '?'));
      $sql = 'SELECT DISTINCT m.mem_usr_id AS usr_id
        FROM ' . TBL_MEMBERS . ' m
          INNER JOIN ' . TBL_USERS . ' u ON u.usr_id = m.mem_usr_id
          INNER JOIN ' . TBL_ROLES . ' r ON r.rol_id = m.mem_rol_id AND r.rol_valid = true
               WHERE u.usr_valid = true
                 AND m.mem_begin <= ?
                 AND (m.mem_end IS NULL OR m.mem_end >= ?)
                 AND m.mem_rol_id IN (' . $placeholders . ')
      ORDER BY m.mem_usr_id';
      $params = array_merge($params, array_values($roleFilter));
  } else {
      $sql = 'SELECT DISTINCT u.usr_id FROM ' . TBL_USERS . ' u WHERE u.usr_valid = true ORDER BY u.usr_id';
      $params = array();
  }

  $stmt = $gDb->queryPrepared($sql, $params);
  if ($stmt !== false) {
      while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $users[] = (int)($row['usr_id'] ?? 0);
      }
  }
  }

  if (empty($users) || empty($chargeDefinitions)) {
  return array(
      'users' => array(),
      'rows' => array(),
      'parameters' => array(
    'start_date' => $startDate,
    'invoice_date' => $invoiceDate,
    'note' => $note,
    'user_id' => $userFilterId
      ),
      'currency' => $currencyLabel,
      'total_amount' => number_format(0, 2, '.', ''),
      'summary_end_date' => $startDate
  );
  }

  $roleMap = billingFetchUserRoleMap($users, $referenceDate);
  $rows = array();
  $includedUsers = array();
  $totalAmount = 0.0;
  $summaryEndDate = $startDate;
  $cfg = billingReadConfig();
  $dueDays = (int)($cfg['defaults']['due_days'] ?? 15);
  if ($dueDays <= 0) { $dueDays = 15; }
  $dueDate = date('Y-m-d', strtotime($invoiceDate . ' +' . $dueDays . ' days'));

  foreach ($users as $uid) {
  $userRoles = $roleMap[$uid] ?? array();
  $matches = billingFilterChargesForUser($chargeDefinitions, $userRoles, $groupId > 0 ? $groupId : null);
  if (empty($matches)) {
      continue;
  }

  $items = array();
  $userTotal = 0.0;
  $invoiceStart = null; // YYYY-mm-dd
  $invoiceEnd = null;   // YYYY-mm-dd
  foreach ($matches as $match) {
      $amount = (float)($match['amount'] ?? 0.0);
      $userTotal += $amount;
      $periodMonths = (int)($match['period_months'] ?? 0);
      $itemStartDate = $startDate;
      $itemEndDate = $itemStartDate;
      if ($periodMonths > 0) {
        $itemEndDate = date('Y-m-d', strtotime($itemStartDate . ' +' . $periodMonths . ' months -1 day'));
      }

      $itemStart = $itemStartDate;
      $itemEnd = $itemEndDate;

      if ($invoiceStart === null || $itemStartDate < $invoiceStart) {
        $invoiceStart = $itemStartDate;
      }
      if ($invoiceEnd === null || $itemEndDate > $invoiceEnd) {
        $invoiceEnd = $itemEndDate;
      }
      $items[] = array(
    'name' => (string)$match['name'],
    'type' => 'membership',
    'currency' => $currencyLabel,
    'amount' => number_format($amount, 2, '.', ''),
    'period_code' => $match['period'],
    'period_months' => $periodMonths,
    'start_date' => $itemStart,
    'end_date' => $itemEnd,
    'charge_id' => (int)$match['id']
      );
  }

  $coverageStart = $invoiceStart ?? $startDate;
  $coverageEnd = $invoiceEnd ?? $startDate;
  if ($coverageEnd > $summaryEndDate) {
    $summaryEndDate = $coverageEnd;
  }

  $displayName = billingFetchUserNameById($uid);
  $includedUsers[] = $uid;
  $totalAmount += $userTotal;

  $rows[] = array(
      'user_id' => $uid,
      'display_name' => $displayName !== '' ? $displayName : 'User #' . $uid,
      'start_date' => $coverageStart,
      'end_date' => $coverageEnd,
      'invoice_date' => $invoiceDate,
      'due_date' => $dueDate,
      'note' => $note,
      'items' => $items,
      'total' => number_format($userTotal, 2, '.', ''),
      'currency' => $currencyLabel
  );
  }

  return array(
  'users' => $includedUsers,
  'rows' => $rows,
  'parameters' => array(
      'start_date' => $startDate,
      'invoice_date' => $invoiceDate,
      'note' => $note,
      'user_id' => $userFilterId
  ),
  'currency' => $currencyLabel,
  'total_amount' => number_format($totalAmount, 2, '.', ''),
  'summary_end_date' => $summaryEndDate
  );
}

/**
 * Get payment status code from status string
 */
function billingGetPaymentStatus(string $status): string
{
  global $gL10n;
  $status = trim($status);
  
  // Direct mapping if code is already passed
  if (in_array($status, array('IT', 'SU', 'FA', 'TO', 'IV', 'TE', 'AB'))) {
    return $status;
  }

  $s = strtolower($status);
  
  if ($s === strtolower($gL10n->get('BL_STATUS_INITIATED')) || $s === 'initiated') return 'IT';
  if ($s === strtolower($gL10n->get('BL_STATUS_SUCCESS')) || $s === 'success' || $s === 'captured' || $s === 'authorised') return 'SU';
  if ($s === strtolower($gL10n->get('BL_STATUS_FAILURE')) || $s === 'failure' || $s === 'failed') return 'FA';
  if ($s === strtolower($gL10n->get('BL_STATUS_TIMEOUT')) || $s === 'timeout') return 'TO';
  if ($s === strtolower($gL10n->get('BL_STATUS_INVALID')) || $s === 'invalid') return 'IV';
  if ($s === strtolower($gL10n->get('BL_STATUS_TERMINATE')) || $s === 'terminate') return 'TE';
  if ($s === strtolower($gL10n->get('BL_STATUS_ABORTED')) || $s === 'aborted') return 'AB';

  return 'IV';
}

/**
 * Get localized payment status label from code
 */
function billingGetPaymentStatusLabel(string $code): string
{
  global $gL10n;
  switch ($code) {
    case 'IT': return $gL10n->get('BL_STATUS_INITIATED');
    case 'SU': return $gL10n->get('BL_STATUS_SUCCESS');
    case 'FA': return $gL10n->get('BL_STATUS_FAILURE');
    case 'TO': return $gL10n->get('BL_STATUS_TIMEOUT');
    case 'IV': return $gL10n->get('BL_STATUS_INVALID');
    case 'TE': return $gL10n->get('BL_STATUS_TERMINATE');
    case 'AB': return $gL10n->get('BL_STATUS_ABORTED');
    default: return $code;
  }
}

/**
 * Fetch user address details from profile fields.
 * Returns array with keys: address, city, state, zip, country, tel, email, name
 */
function billingGetUserAddress(int $userId): array
{
  global $gDb, $gProfileFields, $gCurrentUser;

  // Helper closure to get profile value by internal name
  $getProfileVal = function($internalName) use ($gDb, $gProfileFields, $userId) {
    $fid = $gProfileFields->getProperty($internalName, 'usf_id');
    if (!$fid) {
      return '';
    }
    $stmt = $gDb->queryPrepared('SELECT usd_value FROM ' . TBL_USER_DATA . ' WHERE usd_usr_id = ? AND usd_usf_id = ?', array($userId, $fid));
    $res = $stmt->fetch();
    return ($res && isset($res['usd_value'])) ? trim($res['usd_value']) : '';
  };

  $address = $getProfileVal('STREET');
  $city    = $getProfileVal('CITY');
  $zip     = $getProfileVal('POSTCODE');
  $country = $getProfileVal('COUNTRY');
  $tel     = $getProfileVal('MOBILE');
  if ($tel === '') {
    $tel = $getProfileVal('PHONE');
  }
  
  // Get name and email from user table/object if possible, or DB
  $user = new User($gDb, $gProfileFields, $userId);
  $name = trim($user->getValue('FIRST_NAME') . ' ' . $user->getValue('LAST_NAME'));
  $email = $user->getValue('EMAIL');

  return array(
    'address' => $address,
    'city'    => $city,
    'state'   => 'TN',
    'zip'     => $zip,
    'country' => $country,
    'tel'     => $tel,
    'email'   => $email,
    'name'    => $name
  );
}

/**
 * Check for timed out payments (Initiated > 15 mins ago) and update status to TO.
 */
function billingCheckPaymentTimeouts(): void
{
  global $gDb;
  $timeoutMinutes = 15;
  $timeoutDate = date('Y-m-d H:i:s', strtotime("-{$timeoutMinutes} minutes"));
  
  // Update TBL_BL_TRANS
  $updateTimeoutSql = 'UPDATE ' . TBL_BL_TRANS . ' 
                         SET btr_status = ? 
                         WHERE btr_status = ? AND btr_timestamp_create < ?';
                         
  // Never allow a background maintenance update to trigger the SQL error page.
  $gDb->queryPrepared($updateTimeoutSql, array('TO', 'IT', $timeoutDate), false);
}

/**
 * Get total amount for an invoice as float.
 */
function billingGetInvoiceTotalAmount(int $invId): float
{
  $totals = billingGetInvoiceTotals($invId);
  return (float)$totals['amount'];
}

function validateApiKey(): User
{
  global $gDb, $gCurrentUserId, $gProfileFields;

  if (!tableExistsBILL(TBL_BL_DEVICES)) {
    http_response_code(503);
    echo json_encode(['error' => 'Devices table not found. Please run the residents plugin installation first.']);
    exit();
  }

  $headers = function_exists('getallheaders') ? getallheaders() : array();
  $apiKey = null;
  foreach ($headers as $headerName => $headerValue) {
      if (strcasecmp((string) $headerName, 'api_key') === 0 ||
    strcasecmp((string) $headerName, 'apikey') === 0 || strcasecmp((string) $headerName, 'api-key') === 0) {
          $apiKey = trim((string) $headerValue);
          break;
      }
  }

  if ($apiKey === null && isset($_SERVER['HTTP_API_KEY'])) {
      $apiKey = trim((string) $_SERVER['HTTP_API_KEY']);
  }

  if ($apiKey === null || $apiKey === '') {
      http_response_code(400);
      echo json_encode(['error' => 'Missing API key']);
      exit();
  }

  // Ensure API key belongs to an approved device and a valid (enabled) user.
  $sql = 'SELECT d.bde_usr_id, d.bde_is_active
            FROM ' . TBL_BL_DEVICES . ' d
            JOIN ' . TBL_USERS . ' u ON u.usr_id = d.bde_usr_id
           WHERE d.bde_api_key = ?
             AND u.usr_valid = true
           LIMIT 1';
  $row = $gDb->queryPrepared($sql, array($apiKey), false);
  if ($row === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit();
  }
  $deviceRecord = $row->fetch(PDO::FETCH_ASSOC);

  if (!$deviceRecord) {
      http_response_code(403);
      echo json_encode(['error' => 'API key is invalid']);
      exit();
  }

  if (!(bool) $deviceRecord['bde_is_active']) {
      http_response_code(403);
      echo json_encode([
    'status' => 'pending',
    'error' => 'Device request is not approved yet.',
      ]);
      exit();
  }

  $userId = (int) $deviceRecord['bde_usr_id'];
  $user = new User($gDb, $gProfileFields, $userId);

  if ($userId <= 0 || (int) $user->getValue('usr_id') !== $userId) {
      http_response_code(403);
      echo json_encode(['error' => 'API key is invalid']);
      exit();
  }

  $gCurrentUserId = $userId;
  $GLOBALS['gCurrentUser'] = $user;
  $GLOBALS['gValidLogin'] = true;

  return $user;
}

// check if user within 15 minutes 3 wrong login took place -> block user account for 15 minutes
function hasMaxInvalidLogins(User $user): bool
{
    $now = new DateTime();
    $minutesOffset = new DateInterval('PT15M');
    $minutesBefore = $now->sub($minutesOffset);

    if (is_null($user->getValue('usr_date_invalid', 'Y-m-d H:i:s'))) {
        $dateInvalid = $minutesBefore;
    } else {
        $dateInvalid = DateTime::createFromFormat('Y-m-d H:i:s', $user->getValue('usr_date_invalid', 'Y-m-d H:i:s'));
    }

    if ($user->getValue('usr_number_invalid') < User::MAX_INVALID_LOGINS || $minutesBefore->getTimestamp() >= $dateInvalid->getTimestamp()) {
        return false;
    }

    $user->clear();

    return true;
}

  // Check if user is currently member of a role of an organisation
function isMemberOfOrganization(User $user): bool
{
  global $gDb, $gCurrentOrgId;
  $sql = 'SELECT mem_usr_id
            FROM ' . TBL_MEMBERS . '
      INNER JOIN ' . TBL_ROLES . '
              ON rol_id = mem_rol_id
      INNER JOIN ' . TBL_CATEGORIES . '
              ON cat_id = rol_cat_id
            WHERE mem_usr_id = ?
              AND rol_valid  = true
              AND mem_begin <= ?
              AND mem_end    > ?
              AND cat_org_id = ?';
  $queryParams = array((int)$user->getValue('usr_id'), DATE_NOW, DATE_NOW, $gCurrentOrgId);
  $pdoStatement = $gDb->queryPrepared($sql, $queryParams, false);

  if ($pdoStatement === false) {
    return false;
  }

  if ($pdoStatement->rowCount() > 0) {
      return true;
  }

  return false;
}

function handleIncorrectPasswordLogin(User $user): string
{
    // log invalid logins
    if ($user->getValue('usr_number_invalid') >= User::MAX_INVALID_LOGINS) {
        $user->setValue('usr_number_invalid', 1);
    } else {
        $user->setValue('usr_number_invalid', $user->getValue('usr_number_invalid') + 1);
    }

    $user->setValue('usr_date_invalid', DATETIME_NOW);
    $user->saveChangesWithoutRights();
    $user->save(false); // don't update timestamp // TODO Exception handling

    if ($user->getValue('usr_number_invalid') >= User::MAX_INVALID_LOGINS) {
        $user->clear();

        echo json_encode(['error' => 'You have tried to login too many times recently using a wrong password.For security reasons your account has been locked for 15 minutes']);
        exit;
    }

    $user->clear();

    echo json_encode(['error' => 'Password is incorrect']);
    exit;
}

function validateUserLogin(int $userId, string $password){
  global $gDb;
  $user = new User($gDb);
  $user->readDataById($userId);
  
  if (hasMaxInvalidLogins($user)) {
    echo json_encode(['error' => 'You have tried to login too many times recently using a wrong password.For security reasons your account has been locked for 15 minutes']);
    exit;
  }
  
  if (!password_verify($password, $user->getValue('usr_password'))) {
    handleIncorrectPasswordLogin($user);
  }
  
  if (!isMemberOfOrganization($user)) {
    echo json_encode(['error' => 'Your login data were correct but you are not an active member of this organization']);
    exit;
  }
}