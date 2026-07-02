<?php
/**
 ***********************************************************************************************
 * Common functions for the Admidio Residents plugin
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../system/common.php');
require_once(__DIR__ . '/../../system/bootstrap/constants.php');
require_once(__DIR__ . '/classes/ResidentsTables.php');
use Admidio\Users\Entity\User;
use Admidio\Messages\Entity\Message;
use Admidio\Infrastructure\Email;

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

// Admidio 5.0+ class aliases for convenient short names
// This plugin requires Admidio 5.0 or higher
if (!class_exists('RolesRights', false)) {
    class_alias('Admidio\\Roles\\Entity\\RolesRights', 'RolesRights');
}
if (!class_exists('TableRoles', false)) {
    class_alias('Admidio\\Roles\\Entity\\Role', 'TableRoles');
}
if (!class_exists('SecurityUtils', false)) {
    class_alias('Admidio\\Infrastructure\\Utils\\SecurityUtils', 'SecurityUtils');
}
if (!class_exists('Database', false)) {
    class_alias('Admidio\\Infrastructure\\Database', 'Database');
}
if (!class_exists('User', false)) {
    class_alias('Admidio\\Users\\Entity\\User', 'User');
}
if (!class_exists('ProfileFields', false)) {
    class_alias('Admidio\\ProfileFields\\ValueObjects\\ProfileFields', 'ProfileFields');
}
if (!class_exists('Organization', false)) {
    class_alias('Admidio\\Organizations\\Entity\\Organization', 'Organization');
}



// define plugin specific constants
if (!defined('PLUGIN_FOLDER_RE')) {
    define('PLUGIN_FOLDER_RE', '/' . basename(__DIR__));
}
if (!defined('TBL_RE_INVOICES')) {
    define('TBL_RE_INVOICES', TABLE_PREFIX . '_re_invoices');
}
if (!defined('TBL_RE_INVOICE_ITEMS')) {
    define('TBL_RE_INVOICE_ITEMS',  TABLE_PREFIX . '_re_invoice_items');
}
if (!defined('TBL_RE_PAYMENTS')) {
    define('TBL_RE_PAYMENTS', TABLE_PREFIX . '_re_payments');
}
if (!defined('TBL_RE_PAYMENT_ITEMS')) {
    define('TBL_RE_PAYMENT_ITEMS', TABLE_PREFIX . '_re_payment_items');
}
if (!defined('TBL_RE_TRANS')) {
    define('TBL_RE_TRANS', TABLE_PREFIX . '_re_trans');
}
if (!defined('TBL_RE_TRANS_ITEMS')) {
    define('TBL_RE_TRANS_ITEMS', TABLE_PREFIX . '_re_trans_items');
}
if (!defined('TBL_RE_INVOICES_HIST')) {
    define('TBL_RE_INVOICES_HIST', TABLE_PREFIX . '_re_invoices_hist');
}
if (!defined('TBL_RE_INVOICE_ITEMS_HIST')) {
    define('TBL_RE_INVOICE_ITEMS_HIST', TABLE_PREFIX . '_re_invoice_items_hist');
}
if (!defined('TBL_RE_PAYMENTS_HIST')) {
    define('TBL_RE_PAYMENTS_HIST', TABLE_PREFIX . '_re_payments_hist');
}
if (!defined('TBL_RE_PAYMENT_ITEMS_HIST')) {
    define('TBL_RE_PAYMENT_ITEMS_HIST', TABLE_PREFIX . '_re_payment_items_hist');
}
if (!defined('TBL_RE_CHARGES_HIST')) {
    define('TBL_RE_CHARGES_HIST', TABLE_PREFIX . '_re_charges_hist');
}
if (!defined('TBL_RE_PG_PAYMENTS')) {
    define('TBL_RE_PG_PAYMENTS', TABLE_PREFIX . '_re_pg_payments');
}
if (!defined('TBL_RE_PG_PAYMENT_ITEMS')) {
    define('TBL_RE_PG_PAYMENT_ITEMS', TABLE_PREFIX . '_re_pg_payment_items');
}
if (!defined('TBL_RE_CHARGES')) {
    define('TBL_RE_CHARGES', TABLE_PREFIX . '_re_charges');
}
if (!defined('TBL_PLUGIN_PREFERENCES')) {
    define('TBL_PLUGIN_PREFERENCES', TABLE_PREFIX . '_plugin_preferences');
}
if (!defined('TBL_RE_DEVICES')) {
    define('TBL_RE_DEVICES', TABLE_PREFIX . '_re_devices');
}
if (!defined('TBL_RE_DEVICES_HIST')) {
    define('TBL_RE_DEVICES_HIST', TABLE_PREFIX . '_re_devices_hist');
}

// --- Invoice constants for reuse across plugin files ---
if (!defined('RE_STATUS_OPEN')) {
    define('RE_STATUS_OPEN', 'O');
}
if (!defined('RE_STATUS_CLOSED')) {
    define('RE_STATUS_CLOSED', 'C');
}

/**
 * Ensure the Residents plugin stylesheet is only added once per request.
 * Also sets the page to full width for consistent layout.
 */
function residentsEnqueueStyles(HtmlPage $page): void
{
    static $stylesAdded = false;
    if ($stylesAdded) {
        return;
    }
    $page->addCssFile(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.css');
    $page->setContentFullWidth();
    $stylesAdded = true;
}

/**
    * Reusable option lists for paid status filter.
    * Uses riv_is_paid values: 0 = Unpaid, 1 = Paid
    * @param string $type 'paid' for payment status filter (Unpaid/Paid)
    * @param bool $includeEmpty Whether to include an empty option at the start
    */
function residentsInvoiceStatusOptions(string $type = 'paid', bool $includeEmpty = false): array
{
    global $gL10n;

    // Paid status filter based on riv_is_paid column (0 = Unpaid, 1 = Paid)
    $unpaidLabel = isset($gL10n) ? $gL10n->get('RE_UNPAID') : 'Unpaid';
    $paidLabel = isset($gL10n) ? $gL10n->get('RE_PAID') : 'Paid';

    $opts = array(
        '0' => $unpaidLabel,
        '1' => $paidLabel,
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

if (!function_exists('residentsFetchUserNameById')) {
    function residentsFetchUserNameById(int $userId): string
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

if (!function_exists('residentsFetchUserEmailById')) {
    function residentsFetchUserEmailById(int $userId): string
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

function residentsResolveDate(?string $value, ?string $fallback = null): string
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

if (!function_exists('residentsFormatDateForUi')) {
    function residentsFormatDateForUi($value): string
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

if (!function_exists('residentsFormatDateTimeForUi')) {
    function residentsFormatDateTimeForUi($value): string
    {
        global $gSettingsManager;

        $s = trim((string)$value);
        if ($s === '') {
            return '';
    }

        $format = 'Y-m-d H:i';
        if (isset($gSettingsManager) && method_exists($gSettingsManager, 'getString')) {
            $dateFormat = trim((string)$gSettingsManager->getString('system_date'));
            $timeFormat = trim((string)$gSettingsManager->getString('system_time'));
            if ($dateFormat !== '' || $timeFormat !== '') {
                $format = trim($dateFormat . ' ' . $timeFormat);
            }
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
            return $dt->format($format);
    } catch (Throwable $e) {
            return strlen($s) >= 16 ? substr($s, 0, 16) : $s;
    }
    }
}

if (!function_exists('residentsFormatDateForInput')) {
    function residentsFormatDateForInput($value): string
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

if (!function_exists('residentsFormatDateForApi')) {
    function residentsFormatDateForApi($value): string
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

function residentsChargePeriodMonths(?string $period): int
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

function residentsFetchChargeDefinitions(?int $orgId = null): array
{
    global $gDb, $gCurrentOrgId;

    // Use provided org_id or fall back to current organization
    $filterOrgId = ($orgId !== null) ? $orgId : (int)$gCurrentOrgId;

    static $cache = array();
    $cacheKey = 'org_' . $filterOrgId;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $cache[$cacheKey] = array();
    
    if ($filterOrgId > 0) {
        $stmt = $gDb->queryPrepared(
            'SELECT * FROM ' . TBL_RE_CHARGES . ' WHERE rch_org_id = ? ORDER BY rch_name ASC',
            array($filterOrgId)
        );
    } else {
        $stmt = $gDb->queryPrepared('SELECT * FROM ' . TBL_RE_CHARGES . ' ORDER BY rch_name ASC', array());
    }
    
    if ($stmt !== false) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $period = (string)($row['rch_period'] ?? '');
            $cache[$cacheKey][] = array(
        'id' => (int)($row['rch_id'] ?? 0),
        'org_id' => (int)($row['rch_org_id'] ?? 0),
        'name' => (string)($row['rch_name'] ?? ''),
        'amount' => (float)($row['rch_amount'] ?? 0.0),
        'period' => $period,
        'period_months' => residentsChargePeriodMonths($period),
        'role_ids' => residentsDeserializeRoleIds((string)($row['rch_role_ids'] ?? ''))
            );
    }
    }

    return $cache[$cacheKey];
}

function residentsFetchUserRoleMap(array $userIds, string $referenceDate): array
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

function residentsGetActiveRoleIdsForUser(int $userId): array
{
    if ($userId <= 0) {
            return array();
    }

    $roleMap = residentsFetchUserRoleMap(array($userId), date('Y-m-d'));
    return $roleMap[$userId] ?? array();
}

function residentsMessageIsVisibleToUser(Message $message, int $userId): bool
{
    global $gDb;

    if ($userId <= 0) {
        return false;
    }

    if ((int)$message->getValue('msg_usr_id_sender') === $userId) {
        return true;
    }

    if ($message->getValue('msg_type') === Message::MESSAGE_TYPE_PM) {
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

function residentsMessageCanDelete(Message $message, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }

    if ((int)$message->getValue('msg_usr_id_sender') === $userId) {
        return true;
    }

    return residentsMessageIsVisibleToUser($message, $userId);
}

function residentsFilterChargesForUser(array $chargeDefinitions, array $userRoleIds, ?int $groupFilter = null): array
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
    * Check if a table exists (works for MySQL and PostgreSQL when default schema equals DB_NAME/public).
    * Note: PostgreSQL stores unquoted identifiers in lowercase, so we use LOWER() for comparisons.
    */
function tableExistsRE(string $tableName): bool
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
function indexExistsRE(string $tableName, string $indexName): bool
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
    * Check if a FK constraint exists.
    * Note: PostgreSQL stores unquoted identifiers in lowercase, so we use LOWER() for comparisons.
    */
function constraintExistsRE(string $tableName, string $constraintName): bool
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
function isUserAuthorizedForResidents(string $scriptName): bool
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
function isResidentsAdmin(): bool
{
    // Plugin admin is defined by configured Admin roles.
    // If none are configured, Admidio administrators may access admin features.
    global $gCurrentUser;
    $gCurrentUser->getRoleMemberships();
    $config = residentsReadConfig();
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
    * Check admin access strictly against configured admin roles.
    */
function isResidentsAdminBySettings(): bool
{
    global $gCurrentUser;

    if (!isset($gCurrentUser) || !is_object($gCurrentUser)) {
        return false;
    }

    $config = residentsReadConfig();
    $roles = $config['access']['admin_roles'] ?? array();

    foreach ($roles as $roleId) {
        if ($gCurrentUser->isMemberOfRole((int)$roleId)) {
            return true;
    }
    }
    return false;
}

/**
    * Check if the current user is a configured Payment Admin (or Residents Admin).
    */
function isPaymentAdmin(): bool
{
    global $gCurrentUser;

    $config = residentsReadConfig();
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
 * Validate that a record belongs to the current organization.
 * If the record belongs to a different organization, shows an error and exits.
 *
 * @param object $record The TableAccess record to validate (must have getValue method)
 * @param string $orgIdField The field name containing the organization ID (e.g., 'riv_org_id', 'rpa_org_id', 'rch_org_id')
 * @param bool $useGMessage If true, uses $gMessage->show(), otherwise uses die()
 * @return void Exits with error if validation fails
 */
function residentsValidateOrganization(object $record, string $orgIdField, bool $useGMessage = true): void
{
    global $gL10n, $gMessage, $gCurrentOrgId;
    
    $recordOrgId = (int)$record->getValue($orgIdField);
    $currentOrgId = (int)$gCurrentOrgId;
    
    if ($recordOrgId !== $currentOrgId) {
        $errorMsg = $gL10n->get('SYS_NO_RIGHTS');
        if ($useGMessage && isset($gMessage)) {
            $gMessage->show($errorMsg);
        } else {
            die($errorMsg);
        }
    }
}

/**
    * Check if the current user is leader/administrator of the configured owner group.
    */
// isResidentsOwnersLeader removed: no longer used after settings simplification

/**
    * Read plugin config (preferences) stored with RE__ prefix.
    */
function residentsReadConfig(): array
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
    // Legacy single gateway config (kept for backward compat with payment flow)
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
    ),
    // New: array of multiple gateway configs
    'payment_gateways' => array()
    );

    $decodeValue = static function (string $value) {
        if (substr($value, 0, 2) === '((' && substr($value, -2) === '))') {
            $val = substr($value, 2, -2);
            return $val === '' ? array() : explode('#_#', $val);
    }
        return $value;
    };

    $sql = 'SELECT prf_name, prf_value FROM ' . TBL_PREFERENCES . ' WHERE prf_name LIKE ? AND prf_org_id = ?';
    $st = $gDb->queryPrepared($sql, array('RE__%', $gCurrentOrgId));
    while ($row = $st->fetch()) {
        $parts = explode('__', $row['prf_name']);
        if (count($parts) >= 3) {
            $section = $parts[1];
            $key = $parts[2];
            // Each payment gateway is stored as a separate row: RE__payment_gateways__0, __1, etc.
            if ($section === 'payment_gateways' && is_numeric($key)) {
                $decoded = json_decode($row['prf_value'], true);
                if (is_array($decoded)) {
                    $config['payment_gateways'][(int)$key] = $decoded;
                }
                continue;
            }
            // Legacy: handle old single-key 'list' format for backward compat
            if ($section === 'payment_gateways' && $key === 'list') {
                $decoded = json_decode($row['prf_value'], true);
                if (is_array($decoded)) {
                    // Only use legacy data if no indexed rows were found
                    if (empty($config['payment_gateways'])) {
                        $config['payment_gateways'] = $decoded;
                    }
                }
                continue;
            }
            $config[$section][$key] = $decodeValue($row['prf_value']);
    }
    }

    // Backward compatibility: if a legacy single payment_gateway exists, ensure it is represented in the list.
    // We check by merchant_id (CCAVENUE) or client_id (PAYPAL) to avoid duplicates if partially migrated.
    if (!empty(trim((string)($config['payment_gateway']['name'] ?? '')))) {
        $legacy = $config['payment_gateway'];
        // Force uppercase for consistency during migration
        $legacy['name'] = strtoupper($legacy['name']);
        
        $alreadyExists = false;
        foreach ($config['payment_gateways'] as $existing) {
            if (($existing['name'] ?? '') === $legacy['name']) {
                if ($legacy['name'] === 'PAYPAL') {
                    if (($existing['client_id'] ?? '') === ($legacy['client_id'] ?? '')) {
                        $alreadyExists = true;
                        break;
                    }
                } else {
                    if (($existing['merchant_id'] ?? '') === ($legacy['merchant_id'] ?? '')) {
                        $alreadyExists = true;
                        break;
                    }
                }
            }
        }
        if (!$alreadyExists) {
            $config['payment_gateways'][] = $legacy;
        }
    }

    // Keep legacy payment_gateway populated from the first gateway (for payment flow compat)
    if (!empty($config['payment_gateways'])) {
        $config['payment_gateway'] = $config['payment_gateways'][0];
    }

    return $config;
}

/**
    * Internal name (usf_name_intern) of the profile field that, when checked for a
    * user, causes their mobile device to be auto-approved on login (skipping the
    * manual admin approval step).
    */
const RE_USF_AUTO_APPROVE_DEVICE = 'RE_AUTO_APPROVE_DEVICE';

/**
    * Internal name (usf_name_intern) of the profile field that, when checked for a
    * user, exempts them from the "one active device per account" restriction so the
    * account may be used on multiple devices.
    */
const RE_USF_ALLOW_MULTIPLE_DEVICES = 'RE_ALLOW_MULTIPLE_DEVICES';

/**
    * Internal name (cat_name_intern) of the dedicated user-field category that groups
    * the two mobile-device profile fields into their own section/header on the Admidio
    * profile screen (instead of mixing them into the default "Basic Data" section).
    */
const RE_USF_DEVICE_CATEGORY = 'RE_DEVICE_SETTINGS';

/**
    * Display name (section header) shown for the device settings category on the
    * profile screen. Plain text on purpose (not a translation id) so it is shown as-is.
    */
const RE_USF_DEVICE_CATEGORY_NAME = 'Device Settings';

/**
    * Read a boolean (checkbox) profile field value for a given user directly from the
    * database. Reads the raw stored value so it works in API contexts where a full
    * User object is not (yet) loaded. A missing row is treated as unchecked (false).
    *
    * @param int $userId The user id (usr_id)
    * @param string $usfNameIntern The internal profile field name (usf_name_intern)
    * @return bool True if the checkbox is checked, otherwise false
    */
function residentsGetUserProfileFlag(int $userId, string $usfNameIntern): bool
{
    global $gDb;

    if ($userId <= 0 || $usfNameIntern === '') {
        return false;
    }

    $sql = 'SELECT ud.usd_value
              FROM ' . TBL_USER_DATA . ' ud
        INNER JOIN ' . TBL_USER_FIELDS . ' uf ON uf.usf_id = ud.usd_usf_id
             WHERE ud.usd_usr_id = ?
               AND uf.usf_name_intern = ?
             LIMIT 1';
    $st = $gDb->queryPrepared($sql, array($userId, $usfNameIntern), false);
    if ($st === false) {
        return false;
    }
    $value = $st->fetchColumn();
    if ($value === false || $value === null) {
        return false;
    }
    return in_array(trim((string) $value), array('1', 'true'), true);
}

/**
    * Whether the given user's mobile devices should be auto-approved on login
    * (skipping the manual admin approval step). Driven by the per-user
    * "Auto-approve Device" profile field.
    *
    * @param int $userId The user id (usr_id)
    * @return bool
    */
function residentsUserHasAutoApproveDevice(int $userId): bool
{
    return residentsGetUserProfileFlag($userId, RE_USF_AUTO_APPROVE_DEVICE);
}

/**
    * Whether the given user is allowed to use the account on multiple devices,
    * i.e. is exempt from the "one active device per account" restriction. Driven by
    * the per-user "Allow Multiple Devices" profile field.
    *
    * @param int $userId The user id (usr_id)
    * @return bool
    */
function residentsUserAllowsMultipleDevices(int $userId): bool
{
    return residentsGetUserProfileFlag($userId, RE_USF_ALLOW_MULTIPLE_DEVICES);
}

/**
    * Whether another (physically different) device is already active for this
    * account within the given organization. Used to enforce the "one active device
    * per account" rule at approval time. Registration and login only block devices
    * when one is already active, so if two devices both send requests while both are
    * still pending, both pending rows are created and an admin could otherwise
    * approve both. This lets the approval path close that gap.
    *
    * @param int    $userId      The user id (usr_id)
    * @param string $deviceIdent The physical device id (rde_device_id) to exclude
    * @param int    $orgId       The organization id (rde_org_id); 0 to ignore org
    * @return bool
    */
function residentsUserHasOtherActiveDevice(int $userId, string $deviceIdent, int $orgId): bool
{
    global $gDb;

    $sql = 'SELECT rde_id FROM ' . TBL_RE_DEVICES . '
            WHERE rde_usr_id = ? AND rde_device_id <> ? AND rde_is_active = 1';
    $params = array($userId, $deviceIdent);
    if ($orgId > 0) {
        $sql .= ' AND rde_org_id = ?';
        $params[] = $orgId;
    }
    $sql .= ' LIMIT 1';

    $stmt = $gDb->queryPrepared($sql, $params, false);
    if ($stmt === false) {
        // On query failure err on the safe side: treat as a conflict so we never
        // approve a second device by accident.
        return true;
    }

    return (bool) $stmt->fetch();
}

/**
    * Resolve the id of the dedicated "Device Settings" user-field category, creating
    * it if it does not exist yet. Idempotent. The category is created
    * organization-independent (cat_org_id NULL) with no view-right restriction, exactly
    * like the built-in "Basic Data" / "Social networks" categories, so it is visible to
    * every member and administrators, and appears as its own section/header on the
    * profile screen.
    *
    * @return int The cat_id of the device settings category, or 0 if it could not be
    *             resolved or created.
    */
function residentsEnsureDeviceProfileCategory(): int
{
    global $gDb;

    // Already present (created by an earlier call / plugin version)?
    $stmt = $gDb->queryPrepared(
        'SELECT cat_id FROM ' . TBL_CATEGORIES . ' WHERE cat_type = ? AND cat_name_intern = ? LIMIT 1',
        array('USF', RE_USF_DEVICE_CATEGORY),
        false
    );
    $catId = ($stmt !== false) ? (int) $stmt->fetchColumn() : 0;
    if ($catId > 0) {
        return $catId;
    }

    try {
        $category = new \Admidio\Categories\Entity\Category($gDb);
        // Allow creation regardless of the current user's admin rights (this runs during
        // install and the automatic upgrade path).
        $category->saveChangesWithoutRights();
        $category->setValue('cat_type', 'USF');
        $category->setValue('cat_name_intern', RE_USF_DEVICE_CATEGORY);
        $category->setValue('cat_name', RE_USF_DEVICE_CATEGORY_NAME);
        // Empty string on a nullable column stores NULL -> organization-independent,
        // matching how the default USF categories (Basic Data, ...) are created.
        $category->setValue('cat_org_id', '');
        $category->save();
        $catId = (int) $category->getValue('cat_id');
    } catch (\Throwable $e) {
        return 0;
    }

    return $catId;
}

/**
    * Create the two per-user device profile fields used by the mobile login flow if
    * they do not already exist. Idempotent, so it is safe to call on every install
    * and on the automatic schema upgrade path (existing installations pick the fields
    * up when the plugin version is bumped).
    *
    * The fields are created as CHECKBOX fields inside a dedicated "Device Settings"
    * user-field category (see residentsEnsureDeviceProfileCategory) so they appear in
    * their own section/header on the Admidio profile screen. Fields that were created
    * by an earlier plugin version inside "Basic Data" are moved into this category.
    *
    * @return void
    */
function residentsEnsureDeviceProfileFields(): void
{
    global $gDb;

    // Resolve the dedicated "Device Settings" category so the two toggles get their own
    // section/header on the profile. Fall back to Basic Data, then any USF category, so
    // the fields can still be created on unusual installations.
    $catId = residentsEnsureDeviceProfileCategory();
    if ($catId <= 0) {
        $catStmt = $gDb->queryPrepared(
            'SELECT cat_id FROM ' . TBL_CATEGORIES . ' WHERE cat_type = ? AND cat_name_intern = ? ORDER BY cat_id LIMIT 1',
            array('USF', 'BASIC_DATA'),
            false
        );
        if ($catStmt !== false) {
            $catId = (int) $catStmt->fetchColumn();
        }
    }
    if ($catId <= 0) {
        $catStmt = $gDb->queryPrepared(
            'SELECT cat_id FROM ' . TBL_CATEGORIES . ' WHERE cat_type = ? ORDER BY cat_id LIMIT 1',
            array('USF'),
            false
        );
        if ($catStmt !== false) {
            $catId = (int) $catStmt->fetchColumn();
        }
    }
    if ($catId <= 0) {
        // No user field category available; nothing we can safely attach to.
        return;
    }

    // Display names for the fields. No description is set on purpose so no help text
    // is shown under the toggles on the profile edit screen.
    $fields = array(
        RE_USF_AUTO_APPROVE_DEVICE => 'Auto-approve Device',
        RE_USF_ALLOW_MULTIPLE_DEVICES => 'Allow Multiple Devices'
    );

    foreach ($fields as $nameIntern => $displayName) {
        // Look up an existing field with this internal name.
        $existsStmt = $gDb->queryPrepared(
            'SELECT usf_id FROM ' . TBL_USER_FIELDS . ' WHERE usf_name_intern = ? LIMIT 1',
            array($nameIntern),
            false
        );
        $existingId = ($existsStmt !== false) ? (int) $existsStmt->fetchColumn() : 0;

        if ($existingId > 0) {
            // Field already exists (e.g. created by an earlier plugin version inside the
            // "Basic Data" category). Move it into the dedicated device settings category
            // so it shows in its own section, and clear any help text. Setting usf_cat_id
            // via the entity also re-computes usf_sequence within the target category.
            try {
                $field = new \Admidio\ProfileFields\Entity\ProfileField($gDb, $existingId);
                $field->saveChangesWithoutRights();
                if ((int) $field->getValue('usf_cat_id') !== $catId) {
                    $field->setValue('usf_cat_id', $catId);
                }
                if ((string) $field->getValue('usf_description') !== '') {
                    $field->setValue('usf_description', '');
                }
                $field->save();
            } catch (\Throwable $e) {
                // Ignore so a single failure does not break plugin load.
            }
            continue;
        }

        try {
            $field = new \Admidio\ProfileFields\Entity\ProfileField($gDb);
            // Allow creation regardless of the current user's admin rights (this runs
            // during install and the automatic upgrade path).
            $field->saveChangesWithoutRights();
            $field->setValue('usf_cat_id', $catId);
            $field->setValue('usf_type', 'CHECKBOX');
            $field->setValue('usf_name_intern', $nameIntern);
            $field->setValue('usf_name', $displayName);
            $field->save();
        } catch (\Throwable $e) {
            // Ignore creation errors so a single failure does not break plugin load.
        }
    }
}

/**
    * Remove the two per-user device profile fields (and their stored user data) and the
    * dedicated "Device Settings" category that were created by
    * residentsEnsureDeviceProfileFields() / residentsEnsureDeviceProfileCategory().
    * Used on plugin uninstall so a later reinstall starts clean.
    *
    * @return void
    */
function residentsRemoveDeviceProfileFields(): void
{
    global $gDb;

    foreach (array(RE_USF_AUTO_APPROVE_DEVICE, RE_USF_ALLOW_MULTIPLE_DEVICES) as $nameIntern) {
        $stmt = $gDb->queryPrepared(
            'SELECT usf_id FROM ' . TBL_USER_FIELDS . ' WHERE usf_name_intern = ? LIMIT 1',
            array($nameIntern),
            false
        );
        if ($stmt === false) {
            continue;
        }
        $usfId = (int) $stmt->fetchColumn();
        if ($usfId <= 0) {
            continue;
        }
        try {
            $field = new \Admidio\ProfileFields\Entity\ProfileField($gDb, $usfId);
            $field->saveChangesWithoutRights();
            $field->delete();
        } catch (\Throwable $e) {
            // Ignore delete errors to keep uninstall usable.
        }
    }

    // Remove the now-empty dedicated category. Deleting a category with fields still
    // attached would fail, so this runs after the fields above have been deleted.
    $catStmt = $gDb->queryPrepared(
        'SELECT cat_id FROM ' . TBL_CATEGORIES . ' WHERE cat_type = ? AND cat_name_intern = ? LIMIT 1',
        array('USF', RE_USF_DEVICE_CATEGORY),
        false
    );
    $catId = ($catStmt !== false) ? (int) $catStmt->fetchColumn() : 0;
    if ($catId > 0) {
        try {
            $category = new \Admidio\Categories\Entity\Category($gDb, $catId);
            $category->saveChangesWithoutRights();
            $category->delete();
        } catch (\Throwable $e) {
            // Ignore delete errors to keep uninstall usable.
        }
    }
}

/**
    * Approve a mobile device for API access: marks it active, ensures it has an
    * API key, and records the activation timestamp. This is the shared "approve
    * action" used by both the admin device-approval page and the auto-approve
    * path during login.
    *
    * @param int $deviceId Primary key (rde_id) of the device to approve
    * @param int|null $changedByUserId User id to record as the approver (optional)
    * @return string|null The device API key on success, or null on failure
    */
function residentsApproveDevice(int $deviceId, ?int $changedByUserId = null): ?string
{
    global $gDb;

    $device = new TableResidentsDevice($gDb, $deviceId);
    if ($device->isNewRecord()) {
        return null;
    }

    // One active device per account (per org). Refuse to approve a second device
    // when another is already active for the same user+org, unless the member is
    // exempt via "Allow Multiple Devices". This is the shared choke point for both
    // the admin approval page and the login auto-approve path, so it guarantees two
    // pending devices for the same account can never both be approved.
    $ownerUserId = (int) $device->getValue('rde_usr_id');
    if (!residentsUserAllowsMultipleDevices($ownerUserId)
        && residentsUserHasOtherActiveDevice($ownerUserId, (string) $device->getValue('rde_device_id'), (int) $device->getValue('rde_org_id'))) {
        return null;
    }

    $apiKey = (string) $device->getValue('rde_api_key');
    if ($apiKey === '') {
        $apiKey = bin2hex(random_bytes(20));
    }

    $device->setValue('rde_is_active', 1);
    $device->setValue('rde_active_date', date('Y-m-d H:i:s'));
    $device->setValue('rde_api_key', $apiKey);
    if ($changedByUserId !== null) {
        $device->setValue('rde_usr_id_change', $changedByUserId);
    }
    $device->setValue('rde_timestamp_change', date('Y-m-d H:i:s'));

    if (!$device->save()) {
        return null;
    }

    return $apiKey;
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
function residentsGetOwnerOptions($groupId): array
{
    global $gDb, $gProfileFields, $gCurrentOrgId;
    $options = array();
    
    // Base query with members filter to exclude Former users (users with no active role memberships)
    // Filter by organization: only return users who are members of roles belonging to current org
    $select = 'SELECT DISTINCT u.usr_id, u.usr_login_name,
        fn.usd_value AS firstname, ln.usd_value AS lastname
            FROM ' . TBL_USERS . ' u
            INNER JOIN ' . TBL_MEMBERS . ' m ON m.mem_usr_id = u.usr_id AND m.mem_begin <= ? AND m.mem_end > ?
            INNER JOIN ' . TBL_ROLES . ' r ON r.rol_id = m.mem_rol_id AND r.rol_valid = true
            INNER JOIN ' . TBL_CATEGORIES . ' c ON c.cat_id = r.rol_cat_id AND (c.cat_org_id = ? OR c.cat_org_id IS NULL)
            LEFT JOIN ' . TBL_USER_DATA . ' ln ON ln.usd_usr_id = u.usr_id AND ln.usd_usf_id = ' . (int) $gProfileFields->getProperty('LAST_NAME', 'usf_id') . '
            LEFT JOIN ' . TBL_USER_DATA . ' fn ON fn.usd_usr_id = u.usr_id AND fn.usd_usf_id = ' . (int) $gProfileFields->getProperty('FIRST_NAME', 'usf_id') . '
            WHERE u.usr_valid = true';

    $params = array(DATE_NOW, DATE_NOW, (int)$gCurrentOrgId);
    
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
function residentsEnsureUserInOptions(array &$options, int $userId): void
{
    if ($userId <= 0 || isset($options[$userId])) {
        return;
    }
    
    // Fetch the user's name and add them to the options
    $userName = residentsFetchUserNameById($userId);
    if ($userName === '') {
        $userName = 'User #' . $userId;
    }
    $options[$userId] = $userName;
}

/**
    * Fetch all active roles for dropdowns (organization + global roles).
    */
function residentsGetRoleOptions(): array
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

function residentsSerializeRoleIds(array $roleIds): string
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

function residentsDeserializeRoleIds(?string $value): array
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
    * Write config back to DB with prefix RE__
    */
function residentsWriteConfig(array $config): void
{
    global $gDb, $gCurrentOrgId;

    // Helper: upsert a single preference key
    $upsertPref = static function (string $plpName, string $value) use ($gDb, $gCurrentOrgId): void {
        $sqlSel = 'SELECT prf_id FROM ' . TBL_PREFERENCES . ' WHERE prf_name = ? AND prf_org_id = ?';
        $sel = $gDb->queryPrepared($sqlSel, array($plpName, $gCurrentOrgId), false);
        if ($sel === false) {
            return;
        }
        $row = $sel->fetchObject();
        if (isset($row->prf_id)) {
            $gDb->queryPrepared('UPDATE ' . TBL_PREFERENCES . ' SET prf_value = ? WHERE prf_id = ?', array($value, $row->prf_id), false);
        } else {
            $gDb->queryPrepared('INSERT INTO ' . TBL_PREFERENCES . ' (prf_org_id, prf_name, prf_value) VALUES (?,?,?)', array($gCurrentOrgId, $plpName, $value), false);
        }
    };

    foreach ($config as $section => $data) {
        // Skip legacy payment_gateway — it's auto-derived from payment_gateways[0] on read
        if ($section === 'payment_gateway') {
            continue;
        }

        // payment_gateways: store each gateway as a separate row to avoid varchar(255) truncation
        if ($section === 'payment_gateways') {
            // Delete all existing gateway rows (both old 'list' key and indexed keys)
            $gDb->queryPrepared(
                'DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_name LIKE ? AND prf_org_id = ?',
                array('RE\_\_payment\_gateways\_\_%', $gCurrentOrgId),
                false
            );
            // Also delete legacy single-gateway keys to keep database clean
            $gDb->queryPrepared(
                'DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_name LIKE ? AND prf_org_id = ?',
                array('RE\_\_payment\_gateway\_\_%', $gCurrentOrgId),
                false
            );
            // Insert each gateway as RE__payment_gateways__0, __1, etc.
            $gateways = is_array($data) ? array_values($data) : array();
            foreach ($gateways as $idx => $gw) {
                $upsertPref('RE__payment_gateways__' . $idx, json_encode($gw));
            }
            continue;
        }

        foreach ($data as $key => $value) {
            $plpName = 'RE__' . $section . '__' . $key;
            if (is_array($value)) {
                $value = '((' . implode('#_#', $value) . '))';
            }
            $upsertPref($plpName, $value);
    }
    }
    
}

/**
    * Remove all stored Residents plugin preferences (RE__ prefix) for the current org.
    * Note: In SQL LIKE, underscore is a wildcard, so we must escape it to match literal 'RE__'
    */
function residentsDeleteConfig(): void
{
    global $gDb;
    // Escape underscores so LIKE matches literal 'RE__' prefix, not 'RE' + any two chars
    $gDb->queryPrepared('DELETE FROM ' . TBL_PREFERENCES . ' WHERE prf_name LIKE ?', array('RE\\_\\_%'), false);
}

function residentsGetDefaultInvoiceNote(?array $config = null): string
{
    global $gL10n;
    if ($config === null) {
        $config = residentsReadConfig();
    }
    $note = trim((string)($config['defaults']['invoice_note'] ?? ''));
    if ($note === '' && isset($gL10n)) {
        $note = $gL10n->get('RE_DEFAULT_NOTE_TEXT');
    }
    return $note;
}

/**
    * Create plugin menu item under Plugins if missing.
    * The menu item is only visible to logged-in users (members of any role).
    */
function ensureResidentsMenuItem(): void
{
    // Use Ramsey\Uuid if available in Admidio core
    if (!class_exists('Ramsey\Uuid\Uuid')) {
        return;
    }
    $scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';

    global $gDb, $gL10n;
    $menuTitle = 'RES_TITLE';
    $menuDescription = 'RES_DESC';

    $menuId = 0;
    $exists = $gDb->queryPrepared('SELECT men_id FROM ' . TBL_MENU . ' WHERE men_url = ?', array($scriptUrl), false);
    if ($exists !== false && $exists->rowCount() > 0) {
        $menuId = (int)$exists->fetchColumn();
    }

    $createdMenu = false;
    if ($menuId <= 0) {
        $pluginsRow = $gDb->queryPrepared('SELECT men_id FROM ' . TBL_MENU . ' WHERE men_name_intern = ?', array('extensions'), false);
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

        // Use integer 0 for boolean columns (works for both MySQL and PostgreSQL)
        $menNode = 0;
        $menStandard = 0;

        $sql = 'INSERT INTO ' . TBL_MENU . ' (men_com_id, men_men_id_parent, men_uuid, men_node, men_order, men_standard, men_name_intern, men_url, men_icon, men_name, men_description)
                VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $params = array(
            $menIdPlugins,
            (string)$uuid,
            $menNode,
            $orderNew,
            $menStandard,
            'residents',
            $scriptUrl,
            'bi-receipt',
            $menuTitle,
            $menuDescription
        );
        $gDb->queryPrepared($sql, $params, false);

        $menuId = (int)$gDb->lastInsertId();
        $createdMenu = ($menuId > 0);
    }

    // Keep menu visible to logged-in users: assign all active roles (across all organizations)
    $changedRights = false;
    if ($menuId > 0 && class_exists('RolesRights')) {
        $rolesStmt = $gDb->queryPrepared(
            'SELECT rol_id FROM ' . TBL_ROLES . '
             INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
             WHERE rol_valid = true',
            array(),
            false
        );
        if ($rolesStmt !== false) {
            $allRoleIds = array();
            while ($row = $rolesStmt->fetch()) {
                $allRoleIds[] = (int)$row['rol_id'];
            }
            if (!empty($allRoleIds)) {
                $rightMenuView = new RolesRights($gDb, 'menu_view', $menuId);
                $currentRoleIds = $rightMenuView->getRolesIds();
                sort($currentRoleIds);
                $sortedAllRoleIds = $allRoleIds;
                sort($sortedAllRoleIds);
                if ($sortedAllRoleIds !== $currentRoleIds) {
                    $rightMenuView->saveRoles($allRoleIds);
                    $changedRights = true;
                }
            }
        }
    }

    if (($createdMenu || $changedRights) && isset($GLOBALS['gCurrentSession'])) {
        $GLOBALS['gCurrentSession']->reloadAllSessions();
    }
}

function removeResidentsMenuItem(): void
{
    global $gDb;
    $scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
    $gDb->queryPrepared('DELETE FROM ' . TBL_MENU . ' WHERE men_url = ?', array($scriptUrl), false);
    if (isset($GLOBALS['gCurrentSession'])) {
        $GLOBALS['gCurrentSession']->reloadAllSessions();
    }
}

function residentsGetInvoiceTotals(int $invoiceId): array
{
    global $gDb, $gSettingsManager;

    $total = 0.0;
    $currency = null;

    $stmt = $gDb->queryPrepared(
    'SELECT rii_amount, rii_currency FROM ' . TBL_RE_INVOICE_ITEMS . ' WHERE rii_inv_id = ?',
    array($invoiceId),
    false
    );

    if ($stmt !== false) {
        while ($row = $stmt->fetch()) {
            $val = (string)($row['rii_amount'] ?? '0');
            // Remove everything except digits, dots, commas, minus
            $val = preg_replace('/[^0-9.,-]/', '', $val);
            // Remove commas (assuming they are thousands separators)
            $amount = (float)str_replace(',', '', $val);
            
            $total += $amount;
            if ($currency === null && !empty($row['rii_currency'])) {
                $currency = (string)$row['rii_currency'];
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

function residentsNextInvoiceNumberIndex(?int $orgId = null): int
{
    global $gDb, $gDbType, $gCurrentOrgId;

    // Use provided org_id or fall back to current organization
    $filterOrgId = ($orgId !== null) ? $orgId : (int)$gCurrentOrgId;
    $orgFilter = ($filterOrgId > 0) ? ' WHERE riv_org_id = ' . $filterOrgId : '';

    // Some legacy/dev data may have riv_number_index unset (0) while riv_number is numeric.
    // Use the greater of (max index) and (max numeric number) to avoid duplicate-key errors.
    if ($gDbType === 'pgsql') {
        $sql = "SELECT GREATEST(\n"
        . "  COALESCE(MAX(riv_number_index), 0),\n"
        . "  COALESCE(MAX(CASE WHEN riv_number ~ '^[0-9]+\$' THEN CAST(riv_number AS INTEGER) ELSE 0 END), 0)\n"
        . ")\n"
        . "FROM " . TBL_RE_INVOICES . $orgFilter;
    } else {
        $sql = "SELECT GREATEST(\n"
        . "  COALESCE(MAX(riv_number_index), 0),\n"
        . "  COALESCE(MAX(CASE WHEN riv_number REGEXP '^[0-9]+\$' THEN CAST(riv_number AS UNSIGNED) ELSE 0 END), 0)\n"
        . ")\n"
        . "FROM " . TBL_RE_INVOICES . $orgFilter;
    }

    $stmt = $gDb->query($sql);
    if ($stmt === false) {
        return 1;
    }
    $next = (int)$stmt->fetchColumn() + 1;
    return $next > 0 ? $next : 1;
}

function residentsFormatInvoiceNumber(int $index): string
{
    if ($index < 1) {
        $index = 1;
    }
    return (string)$index;
}

function residentsBuildInvoicePreviewData(int $groupId, array $options = array()): array
{
    global $gDb, $gSettingsManager, $gCurrentOrgId;

    $defaultStart = date('Y-m-01');
    $startDate = residentsResolveDate($options['start_date'] ?? '', $defaultStart);
    $invoiceDate = residentsResolveDate($options['invoice_date'] ?? '', date('Y-m-d'));
    // Use invoice date as the reference for membership activity checks, so back-invoicing includes current active users
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

    $chargeDefinitions = residentsFetchChargeDefinitions();

    $users = array();
    if ($userFilterId > 0) {
        $users[] = $userFilterId;
    } else {
        if ($groupId > 0) {
            // Filter users by organization and specific group
            $sql = 'SELECT DISTINCT u.usr_id FROM ' . TBL_USERS . ' u
                    INNER JOIN ' . TBL_MEMBERS . ' m ON m.mem_usr_id = u.usr_id
                    INNER JOIN ' . TBL_ROLES . ' r ON r.rol_id = m.mem_rol_id AND r.rol_valid = true
                    INNER JOIN ' . TBL_CATEGORIES . ' c ON c.cat_id = r.rol_cat_id AND (c.cat_org_id = ? OR c.cat_org_id IS NULL)
                    WHERE u.usr_valid = true
                    AND m.mem_begin <= ?
                    AND (m.mem_end IS NULL OR m.mem_end >= ?)
                    AND m.mem_rol_id = ?
                    ORDER BY u.usr_id';
            $params = array((int)$gCurrentOrgId, $referenceDate, $referenceDate, (int)$groupId);
        } else {
            // Filter all valid users by organization membership
            $sql = 'SELECT DISTINCT u.usr_id FROM ' . TBL_USERS . ' u
                    INNER JOIN ' . TBL_MEMBERS . ' m ON m.mem_usr_id = u.usr_id
                    INNER JOIN ' . TBL_ROLES . ' r ON r.rol_id = m.mem_rol_id AND r.rol_valid = true
                    INNER JOIN ' . TBL_CATEGORIES . ' c ON c.cat_id = r.rol_cat_id AND (c.cat_org_id = ? OR c.cat_org_id IS NULL)
                    WHERE u.usr_valid = true
                    AND m.mem_begin <= ?
                    AND (m.mem_end IS NULL OR m.mem_end >= ?)
                    ORDER BY u.usr_id';
            $params = array((int)$gCurrentOrgId, $referenceDate, $referenceDate);
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

    $roleMap = residentsFetchUserRoleMap($users, $referenceDate);
    $rows = array();
    $includedUsers = array();
    $totalAmount = 0.0;
    $summaryEndDate = $startDate;
    $cfg = residentsReadConfig();
    $dueDays = (int)($cfg['defaults']['due_days'] ?? 15);
    if ($dueDays <= 0) { $dueDays = 15; }
    $dueDate = date('Y-m-d', strtotime($invoiceDate . ' +' . $dueDays . ' days'));

    foreach ($users as $uid) {
        $userRoles = $roleMap[$uid] ?? array();
        $matches = residentsFilterChargesForUser($chargeDefinitions, $userRoles, $groupId > 0 ? $groupId : null);
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

        $displayName = residentsFetchUserNameById($uid);
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
function residentsGetPaymentStatus(string $status): string
{
    $status = trim($status);
    
    // Direct mapping if code is already passed
    if (in_array($status, array('IT', 'SU', 'FA', 'TO', 'IV', 'TE', 'AB'))) {
        return $status;
    }

    $s = strtolower($status);
    
    if ($s === 'initiated') return 'IT';
    if ($s === 'success' || $s === 'captured' || $s === 'authorised' || $s === 'authorized') return 'SU';
    if ($s === 'failure' || $s === 'failed') return 'FA';
    if ($s === 'timeout') return 'TO';
    if ($s === 'invalid') return 'IV';
    if ($s === 'terminate') return 'TE';
    if ($s === 'aborted') return 'AB';

    return 'IV';
}

/**
    * Get payment status label from code (gateway-neutral, non-localized).
    */
function residentsGetPaymentStatusLabel(string $code): string
{
    global $gL10n;
    if (!isset($gL10n)) {
        switch ($code) {
            case 'IT': return 'Initiated';
            case 'SU': return 'Success';
            case 'FA': return 'Failure';
            case 'TO': return 'Timeout';
            case 'IV': return 'Invalid';
            case 'TE': return 'Terminate';
            case 'AB': return 'Aborted';
            default: return $code;
        }
    }

    switch ($code) {
        case 'IT': return $gL10n->get('RE_STATUS_INITIATED');
        case 'SU': return $gL10n->get('RE_STATUS_SUCCESS');
        case 'FA': return $gL10n->get('RE_STATUS_FAILURE');
        case 'TO': return $gL10n->get('RE_STATUS_TIMEOUT');
        case 'IV': return $gL10n->get('RE_STATUS_INVALID');
        case 'TE': return $gL10n->get('RE_STATUS_TERMINATE');
        case 'AB': return $gL10n->get('RE_STATUS_ABORTED');
        default: return $code;
    }
}

/**
    * Fetch user address details from profile fields.
    * Returns array with keys: address, city, state, zip, country, tel, email, name
    */
function residentsGetUserAddress(int $userId): array
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
    * Check for timed out payments (Initiated > X mins ago) and update status to TO.
    * Uses timeout value from payment gateway config, defaults to 15 minutes.
    */
function residentsCheckPaymentTimeouts(): void
{
    global $gDb, $pgConf;
    
    // Get timeout from config or default to 15 minutes
    $timeoutMinutes = isset($pgConf['timeout']) && (int)$pgConf['timeout'] > 0 ? (int)$pgConf['timeout'] : 15;
    $timeoutDate = date('Y-m-d H:i:s', strtotime("-{$timeoutMinutes} minutes"));
    
    // Update TBL_RE_TRANS
    $updateTimeoutSql = 'UPDATE ' . TBL_RE_TRANS . ' 
                            SET rtr_status = ? 
                            WHERE rtr_status = ? AND rtr_timestamp_create < ?';
                         
    // Never allow a background maintenance update to trigger the SQL error page.
    $gDb->queryPrepared($updateTimeoutSql, array('TO', 'IT', $timeoutDate), false);
}

/**
    * Get total amount for an invoice as float.
    */
function residentsGetInvoiceTotalAmount(int $invId): float
{
    $totals = residentsGetInvoiceTotals($invId);
    return (float)$totals['amount'];
}

/**
 * Read an inbound request header, trying several spellings so the API behaves the
 * same behind any web server (Apache, nginx, PHP-FPM, ...). Names are matched
 * case-insensitively and '-' / '_' are treated as equivalent. The first non-empty
 * match wins, so newer header names should be listed before legacy ones.
 *
 * @param string[] $names Accepted header names, e.g. ['apikey', 'X-API-Key'].
 * @return string|null Trimmed value, or null when none of the names are present.
 */
function residentsGetRequestHeader(array $names): ?string
{
    // Normalised forms of the accepted names for comparison.
    $wanted = array();
    foreach ($names as $name) {
        $wanted[] = strtolower(str_replace('_', '-', (string) $name));
    }

    // 1) getallheaders() preserves the original header name (works on Apache and nginx).
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $headerName => $headerValue) {
            $norm = strtolower(str_replace('_', '-', (string) $headerName));
            if (in_array($norm, $wanted, true)) {
                $value = trim((string) $headerValue);
                if ($value !== '') {
                    return $value;
                }
            }
        }
    }

    // 2) Portable fallback via $_SERVER (e.g. "apikey" => $_SERVER['HTTP_APIKEY']).
    foreach ($names as $name) {
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', (string) $name));
        if (isset($_SERVER[$serverKey])) {
            $value = trim((string) $_SERVER[$serverKey]);
            if ($value !== '') {
                return $value;
            }
        }
    }

    return null;
}

/**
 * Whether the current request carries an API key (header, query string, or body).
 * Dual-mode endpoints (e.g. the PDF exports) use this to choose API-key auth via
 * validateApiKey() versus browser/session login, and it reads the same sources
 * validateApiKey() does so the two stay in sync.
 */
function residentsApiKeyProvided(): bool
{
    return residentsExtractApiKey() !== null;
}

/**
 * Extract the API key from the request. Accepted sources are the request header
 * (apikey / X-API-Key / Api-Key) and a POST body field (apikey / api_key). The
 * query string is intentionally NOT read: URLs are written to web-server access
 * logs, proxies and browser history, so a key placed there would leak. Returns
 * null when no non-empty key is present.
 */
function residentsExtractApiKey(): ?string
{
    $apiKey = residentsGetRequestHeader(['apikey', 'X-API-Key', 'Api-Key']);
    foreach (['apikey', 'api_key'] as $keyParam) {
        if ($apiKey === null && isset($_POST[$keyParam])) {
            $apiKey = trim((string) $_POST[$keyParam]);
        }
    }
    if ($apiKey === null) {
        return null;
    }
    $apiKey = trim($apiKey);
    return $apiKey === '' ? null : $apiKey;
}

/**
 * Run a throwaway password verification so the "user not found" path costs roughly
 * the same time as the "user found, wrong password" path. Without it, response
 * latency reveals whether a username exists (enumeration). The constant is a valid
 * bcrypt hash, so password_verify() performs the full key-derivation work.
 */
function residentsEqualizeLoginTiming(string $password): void
{
    password_verify($password, '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy');
}

function validateApiKey(): User
{
    global $gDb, $gCurrentUserId, $gProfileFields, $gCurrentOrgId, $gCurrentOrganization, $gSettingsManager, $gCurrentSession;

    $getRequestedOrgId = function (): int {
        $candidates = array();

        // Preferred field is "orgid"; the older "X-Org-Id"/"org_id" names are still accepted.
        $orgIdHeader = residentsGetRequestHeader(['orgid', 'X-Org-Id', 'Org-Id']);
        if ($orgIdHeader !== null) {
            $candidates[] = $orgIdHeader;
        }

        foreach (['orgid', 'org_id'] as $orgParam) {
            if (isset($_GET[$orgParam])) {
                $candidates[] = (string) $_GET[$orgParam];
            }
            if (isset($_POST[$orgParam])) {
                $candidates[] = (string) $_POST[$orgParam];
            }
        }

        foreach ($candidates as $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                continue;
            }
            $id = (int) $raw;
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    };

    $applyOrgContext = function (int $orgId) use ($gDb, &$gCurrentOrgId, &$gCurrentOrganization, &$gSettingsManager, &$gCurrentSession, &$gProfileFields): void {
        if ($orgId <= 0) {
            return;
        }

        $gCurrentOrgId = $orgId;

        if (class_exists('Organization')) {
            $gCurrentOrganization = new Organization($gDb, $orgId);
            $gSettingsManager =& $gCurrentOrganization->getSettingsManager();
            if (isset($gCurrentSession) && is_object($gCurrentSession)) {
                $gCurrentSession->setValue('ses_org_id', $orgId);
            }
        }

        if (class_exists('ProfileFields')) {
            $gProfileFields = new ProfileFields($gDb, $orgId);
            if (isset($gCurrentSession) && is_object($gCurrentSession)) {
                $gCurrentSession->addObject('gProfileFields', $gProfileFields);
            }
        }
    };

    // Read from header or POST body only (never the query string — see
    // residentsExtractApiKey). The query-string org id below is not a secret and
    // stays supported for backwards compatibility.
    $apiKey = residentsExtractApiKey();

    if ($apiKey === null || $apiKey === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing API key', 'code' => 'API_KEY_INVALID']);
            exit();
    }

    $requestedOrgId = $getRequestedOrgId();
    $orgFilter = ($requestedOrgId > 0) ? ' AND d.rde_org_id = ?' : '';
    $params = array($apiKey);
    if ($requestedOrgId > 0) {
        $params[] = $requestedOrgId;
    }
    
    $sql = 'SELECT d.rde_usr_id, d.rde_is_active, d.rde_org_id
            FROM ' . TBL_RE_DEVICES . ' d
            JOIN ' . TBL_USERS . ' u ON u.usr_id = d.rde_usr_id
            WHERE d.rde_api_key = ?
                AND u.usr_valid = true' . $orgFilter . '
            LIMIT 1';
    $row = $gDb->queryPrepared($sql, $params, false);
    if ($row === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit();
    }
    $deviceRecord = $row->fetch(PDO::FETCH_ASSOC);

    if (!$deviceRecord) {
            http_response_code(403);
            echo json_encode(['error' => 'API key is invalid', 'code' => 'API_KEY_INVALID']);
            exit();
    }

    $deviceOrgId = (int) ($deviceRecord['rde_org_id'] ?? 0);
    if ($requestedOrgId > 0 && $deviceOrgId > 0 && $deviceOrgId !== $requestedOrgId) {
        http_response_code(403);
        echo json_encode(['error' => 'API key is invalid']);
        exit();
    }

    if ($deviceOrgId > 0) {
        $applyOrgContext($deviceOrgId);
    }

    if (!(bool) $deviceRecord['rde_is_active']) {
            http_response_code(403);
            echo json_encode([
        'status' => 'pending',
        'error' => 'Device request is not approved yet.',
            ]);
            exit();
    }

    $userId = (int) $deviceRecord['rde_usr_id'];
    $user = new User($gDb, $gProfileFields, $userId);

    if ($userId <= 0 || (int) $user->getValue('usr_id') !== $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'API key is invalid', 'code' => 'API_KEY_INVALID']);
            exit();
    }

    // The API key must not outlive the user's active membership. usr_valid stays
    // true for "former" members, so checking the key alone would keep granting
    // access after someone is removed from all roles or their membership expires.
    // Require a current membership in the effective organisation (same rule the
    // password login enforces), otherwise the key is treated as invalid.
    $effectiveOrgId = $deviceOrgId > 0 ? $deviceOrgId : $requestedOrgId;
    if ($effectiveOrgId > 0) {
        $membershipStmt = $gDb->queryPrepared(
            'SELECT 1
                FROM ' . TBL_MEMBERS . ' m
                INNER JOIN ' . TBL_ROLES . ' r ON r.rol_id = m.mem_rol_id AND r.rol_valid = true
                INNER JOIN ' . TBL_CATEGORIES . ' c ON c.cat_id = r.rol_cat_id
                WHERE m.mem_usr_id = ?
                    AND m.mem_begin <= ?
                    AND m.mem_end > ?
                    AND (c.cat_org_id = ? OR c.cat_org_id IS NULL)
                LIMIT 1',
            [$userId, DATE_NOW, DATE_NOW, $effectiveOrgId],
            false
        );
        if ($membershipStmt === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
            exit();
        }
        if (!$membershipStmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'API key is invalid', 'code' => 'API_KEY_INVALID']);
            exit();
        }
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

        http_response_code(429);
        echo json_encode(['error' => 'You have tried to login too many times recently using a wrong password. For security reasons your account has been locked for 15 minutes.']);
        exit;
    }

    $user->clear();

    http_response_code(401);
    echo json_encode(['error' => 'Invalid username or password']);
    exit;
}

function validateUserLogin(int $userId, string $password){
    global $gDb;
    $user = new User($gDb);
    $user->readDataById($userId);
    
    if (hasMaxInvalidLogins($user)) {
        http_response_code(429);
        echo json_encode(['error' => 'You have tried to login too many times recently using a wrong password. For security reasons your account has been locked for 15 minutes.']);
        exit;
    }
    
    if (!password_verify($password, $user->getValue('usr_password'))) {
        handleIncorrectPasswordLogin($user);
    }
    
    if (!isMemberOfOrganization($user)) {
        // Generic 401 (identical to the wrong-password response) so a valid
        // password on a non-member account cannot be distinguished from an
        // invalid one — avoids confirming that the credentials were correct.
        http_response_code(401);
        echo json_encode(['error' => 'Invalid username or password']);
        exit;
    }
}