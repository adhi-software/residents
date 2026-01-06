<?php
/**
 * TableAccess wrapper for devices.
 */

require_once(__DIR__ . '/TableResidentsBase.php');
require_once(__DIR__ . '/BillingHistory.php');

class TableResidentsDevice extends TableResidentsBase
{
  public function __construct(Database $database, int $deviceId = 0)
  {
    parent::__construct($database, TBL_BL_DEVICES, 'bde', $deviceId);
  }

  public static function fetchList(Database $database, array $filters, array $options): array
  {
    $baseConditions = array();
    $baseParams = array();
    $filterConditions = array();
    $filterParams = array();
    $searchCondition = '';
    $searchParams = array();

    $isAdmin = (bool)($filters['is_admin'] ?? false);
    $currentUserId = isset($filters['current_user_id']) ? (int)$filters['current_user_id'] : null;
    if (!$isAdmin && $currentUserId !== null) {
      $baseConditions[] = 'd.bde_usr_id = ?';
      $baseParams[] = $currentUserId;
    }

    if (!empty($filters['filter_group'])) {
      $filterConditions[] = 'EXISTS (
        SELECT 1
                  FROM ' . TBL_MEMBERS . ' m
                  JOIN ' . TBL_ROLES . ' r ON r.rol_id = m.mem_rol_id AND r.rol_valid = true
                  JOIN ' . TBL_CATEGORIES . ' c ON c.cat_id = r.rol_cat_id
                 WHERE m.mem_usr_id = d.bde_usr_id
                   AND m.mem_begin <= ?
                   AND m.mem_end > ?
                   AND m.mem_rol_id = ?
                   AND (c.cat_org_id = ? OR c.cat_org_id IS NULL)
      )';
      $filterParams[] = DATE_NOW;
      $filterParams[] = DATE_NOW;
      $filterParams[] = (int)$filters['filter_group'];
      $filterParams[] = (int)($filters['org_id'] ?? 0);
    }

    if (!empty($filters['filter_user'])) {
      $filterConditions[] = 'd.bde_usr_id = ?';
      $filterParams[] = (int)$filters['filter_user'];
    }

    if (isset($filters['filter_active']) && $filters['filter_active'] !== '' && $filters['filter_active'] !== null) {
      $filterConditions[] = 'd.bde_is_active = ?';
      $filterParams[] = (int)$filters['filter_active'];
    }

    // if (!empty($filters['date_from'])) {
    //     $filterConditions[] = 'd.bde_active_date >= ?';
    //     $filterParams[] = $filters['date_from'];
    // }

    // if (!empty($filters['date_to'])) {
    //     $filterConditions[] = 'd.bde_active_date <= ?';
    //     $filterParams[] = $filters['date_to'];
    // }

    $searchTerm = trim((string)($filters['search'] ?? ''));
    if ($searchTerm !== '') {
      $searchCondition = " (
        LOWER(COALESCE(d.bde_platform, '')) LIKE ?
        OR LOWER(COALESCE(d.bde_device_id, '')) LIKE ?
        OR LOWER(COALESCE(d.bde_brand, '')) LIKE ?
        OR LOWER(COALESCE(d.bde_model, '')) LIKE ?
        OR LOWER(CONCAT_WS(' ', COALESCE(fn.usd_value, ''), COALESCE(ln.usd_value, ''))) LIKE ?
        OR LOWER(COALESCE(u.usr_login_name, '')) LIKE ?
      )";
      $searchLike = '%' . strtolower($searchTerm) . '%';
      $searchParams = array($searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
    }

    $whereParts = array_merge($baseConditions, $filterConditions);
    if ($searchCondition !== '') {
      $whereParts[] = $searchCondition;
    }
    $params = array_merge($baseParams, $filterParams, $searchParams);

    $lnId = (int)($options['profile_last_name_id'] ?? 0);
    $fnId = (int)($options['profile_first_name_id'] ?? 0);

    $sql = 'SELECT d.*,
                   CONCAT_WS(\' \', fn.usd_value, ln.usd_value) AS user_name
                  FROM ' . TBL_BL_DEVICES . ' d
             LEFT JOIN ' . TBL_USERS . ' u ON u.usr_id = d.bde_usr_id
             LEFT JOIN ' . TBL_USER_DATA . ' ln ON ln.usd_usr_id = u.usr_id AND ln.usd_usf_id = ' . $lnId . '
             LEFT JOIN ' . TBL_USER_DATA . ' fn ON fn.usd_usr_id = u.usr_id AND fn.usd_usf_id = ' . $fnId;

    if (count($whereParts) > 0) {
      $sql .= ' WHERE ' . implode(' AND ', $whereParts);
    }

    $sortMap = array(
      'number' => 'd.bde_id',
      'device_id' => 'd.bde_device_id',
      'active_date' => 'd.bde_active_date',
      'active' => 'd.bde_is_active',
      'user' => 'user_name',
      'platform' => 'd.bde_platform',
      'brand' => 'd.bde_brand',
      'model' => 'd.bde_model'
    );
    $sortCol = $filters['sort_col'] ?? 'no';
    $sortDir = strtoupper($filters['sort_dir'] ?? 'DESC');
    $sortDir = in_array($sortDir, array('ASC', 'DESC'), true) ? $sortDir : 'DESC';
    $orderBy = $sortMap[$sortCol] ?? 'd.bde_id';

    if (($options['db_type'] ?? '') === 'pgsql') {
      $sql .= ' ORDER BY ' . $orderBy . ' ' . $sortDir . ' NULLS LAST, d.bde_id DESC';
    } else {
      $sql .= ' ORDER BY ' . $orderBy . ' ' . $sortDir;
      if ($orderBy !== 'd.bde_id') {
        $sql .= ', d.bde_id DESC';
      }
    }

    $length = (int)($options['length'] ?? 25);
    if ($length <= 0) {
      $length = 25;
    }
    $offset = (int)($options['offset'] ?? 0);
    $paramsWithLimit = $params;
    $sql .= ' LIMIT ? OFFSET ?';
    $paramsWithLimit[] = $length;
    $paramsWithLimit[] = $offset;
    $statement = $database->queryPrepared($sql, $paramsWithLimit, false);
    $rows = $statement ? $statement->fetchAll() : array();

    $countBaseSql = 'SELECT COUNT(*) FROM ' . TBL_BL_DEVICES . ' d';
    if (count($baseConditions) > 0) {
      $countBaseSql .= ' WHERE ' . implode(' AND ', $baseConditions);
    }
    $countBaseStatement = $database->queryPrepared($countBaseSql, $baseParams, false);
    $totalBase = $countBaseStatement ? (int)$countBaseStatement->fetchColumn() : 0;

    $countSql = 'SELECT COUNT(*)
                       FROM ' . TBL_BL_DEVICES . ' d
                  LEFT JOIN ' . TBL_USERS . ' u ON u.usr_id = d.bde_usr_id
                  LEFT JOIN ' . TBL_USER_DATA . ' ln ON ln.usd_usr_id = u.usr_id AND ln.usd_usf_id = ' . $lnId . '
                  LEFT JOIN ' . TBL_USER_DATA . ' fn ON fn.usd_usr_id = u.usr_id AND fn.usd_usf_id = ' . $fnId;
    if (count($whereParts) > 0) {
      $countSql .= ' WHERE ' . implode(' AND ', $whereParts);
    }
    $countStatement = $database->queryPrepared($countSql, $params, false);
    $totalCount = $countStatement ? (int)$countStatement->fetchColumn() : count($rows);

    return array(
      'rows' => $rows,
      'total' => $totalCount,
      'total_base' => $totalBase
    );
  }

  /**
     * Save device record and log history for updates.
     */
  public function save(bool $updateFingerPrint = true): bool
  {
    $isNew  = $this->isNewRecord();
    $before = $isNew ? null : BillingHistory::fetchRow($this->db, $this->tableName, $this->keyColumnName, (int)$this->getValue($this->keyColumnName));
    $result = parent::save($updateFingerPrint);
    if (!$isNew) {
      BillingHistory::log($this->db, TBL_BL_DEVICES_HIST, $before ?? array(), 'update', $GLOBALS['gCurrentUserId'] ?? null);
    }
    return $result;
  }

  /**
     * Delete device record and log history.
     */
  public function delete(): bool
  {
    if ($this->isNewRecord()) {
      return false;
    }

    $id      = (int)$this->getValue($this->keyColumnName);
    $before  = BillingHistory::fetchRow($this->db, $this->tableName, $this->keyColumnName, $id);
    $result  = parent::delete();
    BillingHistory::log($this->db, TBL_BL_DEVICES_HIST, $before ?? array(), 'delete', $GLOBALS['gCurrentUserId'] ?? null);

    return $result;
  }
}
