<?php
/**
    * TableAccess wrapper for Residents payments.
    */

require_once(__DIR__ . '/TableResidentsBase.php');

class TableResidentsPayment extends TableResidentsBase
{
    public function __construct(Database $database, int $paymentId = 0)
    {
        parent::__construct($database, TBL_BL_PAYMENTS, 'bpa', $paymentId);
    }

    public function save(bool $updateFingerPrint = true): bool
    {
        $isNew   = $this->isNewRecord();
        $before  = $isNew ? null : BillingHistory::fetchRow($this->db, $this->tableName, $this->keyColumnName, (int)$this->getValue($this->keyColumnName));
        $result  = parent::save($updateFingerPrint);
        if ($result && !$isNew) {
            BillingHistory::log($this->db, TBL_BL_PAYMENTS_HIST, $before ?? array(), 'update', $GLOBALS['gCurrentUserId'] ?? null);
    }

        return $result;
    }

    public function deleteWithRelations(?int $actingUserId = null): bool
    {
        if ($this->isNewRecord()) {
            return false;
    }

        $paymentId = (int)$this->getValue('bpa_id');
        if ($paymentId <= 0) {
            return false;
    }
        $beforePayment = BillingHistory::fetchRow($this->db, $this->tableName, $this->keyColumnName, $paymentId);
        $beforeItems   = BillingHistory::fetchRowsByFk($this->db, TBL_BL_PAYMENT_ITEMS, 'bpi_payment_id', $paymentId);

        $this->db->startTransaction();
        $ok = true;

        $invoiceIds = array();
        $invoiceStmt = $this->db->queryPrepared('SELECT DISTINCT bpi_inv_id FROM ' . TBL_BL_PAYMENT_ITEMS . ' WHERE bpi_payment_id = ?', array($paymentId), false);
        if ($invoiceStmt !== false) {
            while ($row = $invoiceStmt->fetch()) {
                $invId = (int)($row['bpi_inv_id'] ?? 0);
                if ($invId > 0) {
                    $invoiceIds[$invId] = true;
        }
            }
    }

        $ok = $ok && ($this->db->queryPrepared('DELETE FROM ' . TBL_BL_PAYMENT_ITEMS . ' WHERE bpi_payment_id = ?', array($paymentId), false) !== false);

        $statement = $this->db->queryPrepared('SELECT btr_id FROM ' . TBL_BL_TRANS . ' WHERE btr_payment_id = ?', array($paymentId), false);
        if ($statement !== false) {
            while ($row = $statement->fetch()) {
                $ok = $ok && ($this->db->queryPrepared('DELETE FROM ' . TBL_BL_TRANS_ITEMS . ' WHERE bti_pg_payment_id = ?', array((int)$row['btr_id']), false) !== false);
            }
    }

        $ok = $ok && ($this->db->queryPrepared('DELETE FROM ' . TBL_BL_TRANS . ' WHERE btr_payment_id = ?', array($paymentId), false) !== false);

        if (!empty($invoiceIds)) {
            $invoiceIds = array_keys($invoiceIds);
            $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
            $updateFields = array('biv_is_paid = ?', 'biv_timestamp_change = ?');
            $params = array(0, date('Y-m-d H:i:s'));
            if ($actingUserId !== null) {
                $updateFields[] = 'biv_usr_id_change = ?';
                $params[] = $actingUserId;
            }
            $params = array_merge($params, $invoiceIds);
            $sql = 'UPDATE ' . TBL_BL_INVOICES . ' SET ' . implode(', ', $updateFields) . ' WHERE biv_id IN (' . $placeholders . ')';
            $ok = $ok && ($this->db->queryPrepared($sql, $params, false) !== false);
    }

        $result = $ok ? parent::delete() : false;

        if (!$result) {
            $this->db->rollback();
            return false;
    }

        $this->db->endTransaction();

        if ($result) {
            BillingHistory::log($this->db, TBL_BL_PAYMENTS_HIST, $beforePayment ?? array(), 'delete', $GLOBALS['gCurrentUserId'] ?? null);
            foreach ($beforeItems as $row) {
                BillingHistory::log($this->db, TBL_BL_PAYMENT_ITEMS_HIST, $row, 'delete', $GLOBALS['gCurrentUserId'] ?? null);
            }
    }

        return $result;
    }

    public static function fetchList(Database $database, array $filters, array $options): array
    {
        $baseConditions = array();
        $baseParams = array();
        $filterConditions = array();
        $filterParams = array();
        $searchCondition = '';
        $searchParams = array();

        $orgId = $filters['org_id'] ?? null;
        if ($orgId !== null) {
            $baseConditions[] = '(p.bpa_org_id = ? OR p.bpa_org_id IS NULL)';
            $baseParams[] = (int)$orgId;
    }

        $isAdmin = (bool)($filters['is_admin'] ?? false);
        if (!$isAdmin && isset($filters['current_user_id'])) {
            $baseConditions[] = 'p.bpa_usr_id = ?';
            $baseParams[] = (int)$filters['current_user_id'];
    }

        if (!empty($filters['filter_user'])) {
            $filterConditions[] = 'p.bpa_usr_id = ?';
            $filterParams[] = (int)$filters['filter_user'];
    }

        if (!empty($filters['filter_group'])) {
            $filterConditions[] = 'p.bpa_usr_id IN (SELECT mem_usr_id FROM ' . TBL_MEMBERS . ' WHERE mem_rol_id = ? AND mem_end > NOW())';
            $filterParams[] = (int)$filters['filter_group'];
    }

        if (!empty($filters['filter_status'])) {
            $filterConditions[] = 'p.bpa_status = ?';
            $filterParams[] = $filters['filter_status'];
    }

        if (!empty($filters['filter_type'])) {
            $filterConditions[] = 'p.bpa_pay_type = ?';
            $filterParams[] = $filters['filter_type'] === 'offline' ? 'Offline' : 'Online';
    }

        if (!empty($filters['filter_start'])) {
            $filterConditions[] = 'p.bpa_date >= ?';
            $filterParams[] = $filters['filter_start'];
    }

        if (!empty($filters['filter_end'])) {
            $filterConditions[] = 'p.bpa_date <= ?';
            $filterParams[] = $filters['filter_end'] . ' 23:59:59';
    }

        $searchTerm = trim((string)($filters['search'] ?? ''));
        if ($searchTerm !== '') {
            $searchCondition = " (
        LOWER(CONCAT_WS(' ', COALESCE(fn.usd_value, ''), COALESCE(ln.usd_value, ''))) LIKE ?
        OR LOWER(COALESCE(p.bpa_bank_ref_no, '')) LIKE ?
        OR LOWER(COALESCE(p.bpa_pg_pay_method, '')) LIKE ?
            )";
            $searchLike = '%' . strtolower($searchTerm) . '%';
            $searchParams = array($searchLike, $searchLike, $searchLike);
    }

        $whereParts = array_merge($baseConditions, $filterConditions);
        if ($searchCondition !== '') {
            $whereParts[] = $searchCondition;
    }
        $params = array_merge($baseParams, $filterParams, $searchParams);

        $lnId = (int)($options['profile_last_name_id'] ?? 0);
        $fnId = (int)($options['profile_first_name_id'] ?? 0);

        $sql = 'SELECT p.*,
                        CONCAT_WS(\' \', fn.usd_value, ln.usd_value) AS user_name,
                        (SELECT COALESCE(SUM(bpi_amount),0) FROM ' . TBL_BL_PAYMENT_ITEMS . ' WHERE bpi_payment_id = p.bpa_id) AS total_amount,
                        (SELECT bpi_currency FROM ' . TBL_BL_PAYMENT_ITEMS . ' WHERE bpi_payment_id = p.bpa_id ORDER BY bpi_id DESC LIMIT 1) AS total_currency
                                    FROM ' . TBL_BL_PAYMENTS . ' p
                LEFT JOIN ' . TBL_USERS . ' u ON u.usr_id = p.bpa_usr_id
                LEFT JOIN ' . TBL_USER_DATA . ' ln ON ln.usd_usr_id = u.usr_id AND ln.usd_usf_id = ' . $lnId . '
                LEFT JOIN ' . TBL_USER_DATA . ' fn ON fn.usd_usr_id = u.usr_id AND fn.usd_usf_id = ' . $fnId;

        if (count($whereParts) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
    }

        $sortMap = array(
            'no' => 'p.bpa_id',
            'date' => 'p.bpa_date',
            'status' => 'p.bpa_status',
            'method' => 'p.bpa_pg_pay_method',
            'type' => 'p.bpa_pay_type',
            'customer_name' => 'user_name',
            'reference' => 'p.bpa_bank_ref_no',
            'amount' => 'total_amount'
        );
        $sortCol = $filters['sort_col'] ?? 'date';
        $sortDir = strtoupper($filters['sort_dir'] ?? 'DESC');
        $sortDir = in_array($sortDir, array('ASC','DESC'), true) ? $sortDir : 'DESC';
        $orderBy = $sortMap[$sortCol] ?? 'p.bpa_date';

        if (($options['db_type'] ?? '') === 'pgsql') {
            $sql .= ' ORDER BY ' . $orderBy . ' ' . $sortDir . ' NULLS LAST, p.bpa_id DESC';
    } else {
            $sql .= ' ORDER BY ' . $orderBy . ' ' . $sortDir;
            if ($orderBy !== 'p.bpa_id') {
                $sql .= ', p.bpa_id DESC';
            }
    }

        $length = (int)($options['length'] ?? 25);
        $offset = (int)($options['offset'] ?? 0);
        $paramsWithLimit = $params;
        $sql .= ' LIMIT ? OFFSET ?';
        $paramsWithLimit[] = $length;
        $paramsWithLimit[] = $offset;

        $statement = $database->queryPrepared($sql, $paramsWithLimit, false);
        $rows = $statement ? $statement->fetchAll() : array();

        // total count without filters/search (security constraints only)
        $countBaseSql = 'SELECT COUNT(*) FROM ' . TBL_BL_PAYMENTS . ' p';
        if (count($baseConditions) > 0) {
            $countBaseSql .= ' WHERE ' . implode(' AND ', $baseConditions);
    }
        $countBaseStatement = $database->queryPrepared($countBaseSql, $baseParams, false);
        $totalBase = $countBaseStatement ? (int)$countBaseStatement->fetchColumn() : 0;

        $countSql = 'SELECT COUNT(*)
                        FROM ' . TBL_BL_PAYMENTS . ' p
                                    LEFT JOIN ' . TBL_USERS . ' u ON u.usr_id = p.bpa_usr_id
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

    public static function fetchDistinctMethods(Database $database): array
    {
        $methods = array();
        $statement = $database->queryPrepared('SELECT DISTINCT bpa_pg_pay_method AS value FROM ' . TBL_BL_PAYMENTS . ' ORDER BY value', array(), false);
        if ($statement !== false) {
            while ($row = $statement->fetch()) {
                if ($row['value'] !== null && $row['value'] !== '') {
                    $methods[] = $row['value'];
        }
            }
    }

        return $methods;
    }

    public static function fetchUserOptions(Database $database, bool $isAdmin, int $firstNameFieldId, int $lastNameFieldId, ?int $currentUserId, int $groupId = 0): array
    {
        if (!$isAdmin) {
            if ($currentUserId === null) {
                return array();
            }
            return array($currentUserId => billingFetchUserNameById($currentUserId));
    }

        $sql = 'SELECT DISTINCT u.usr_id,
                        (SELECT usd_value FROM ' . TBL_USER_DATA . ' WHERE usd_usr_id = u.usr_id AND usd_usf_id = ?) AS first_name,
                        (SELECT usd_value FROM ' . TBL_USER_DATA . ' WHERE usd_usr_id = u.usr_id AND usd_usf_id = ?) AS last_name
                                    FROM ' . TBL_USERS . ' u';

        $params = array($firstNameFieldId, $lastNameFieldId);

        if ($groupId > 0) {
            $sql .= ' JOIN ' . TBL_MEMBERS . ' m ON m.mem_usr_id = u.usr_id AND m.mem_rol_id = ? AND m.mem_end > NOW()';
            $params[] = $groupId;
    }

        $sql .= ' WHERE u.usr_valid = 1
                            ORDER BY last_name, first_name';

        $statement = $database->queryPrepared($sql, $params, false);
        $options = array();
        if ($statement !== false) {
            while ($row = $statement->fetch()) {
                $options[$row['usr_id']] = trim((string)$row['first_name'] . ' ' . $row['last_name']);
            }
    }

        return $options;
    }

    public function getItems(bool $includeInvoiceNumber = false): array
    {
        if ($this->isNewRecord()) {
            return array();
    }

        $columns = 'pi.*';
        $join = '';
        if ($includeInvoiceNumber) {
            $columns .= ', inv.biv_number';
            $join = ' LEFT JOIN ' . TBL_BL_INVOICES . ' inv ON inv.biv_id = pi.bpi_inv_id';
    }

        $sql = 'SELECT ' . $columns . ' FROM ' . TBL_BL_PAYMENT_ITEMS . ' pi' . $join . ' WHERE pi.bpi_payment_id = ? ORDER BY inv.biv_id ASC';
        $statement = $this->db->queryPrepared($sql, array((int)$this->getValue('bpa_id')), false);

        return $statement ? $statement->fetchAll() : array();
    }

    public function replaceItems(array $items, int $creatorUserId): void
    {
        $paymentId = (int)$this->getValue('bpa_id');
        if ($paymentId <= 0) {
            throw new RuntimeException('Cannot replace payment items on unsaved payment.');
    }
        $existingItems = BillingHistory::fetchRowsByFk($this->db, TBL_BL_PAYMENT_ITEMS, 'bpi_payment_id', $paymentId);

        if ($this->db->queryPrepared('DELETE FROM ' . TBL_BL_PAYMENT_ITEMS . ' WHERE bpi_payment_id = ?', array($paymentId), false) === false) {
            throw new RuntimeException('Failed to delete existing payment items.');
    }
        foreach ($existingItems as $row) {
            BillingHistory::log($this->db, TBL_BL_PAYMENT_ITEMS_HIST, $row, 'delete', $GLOBALS['gCurrentUserId'] ?? null);
    }

        foreach ($items as $item) {
            $amount = trim((string)($item['amount'] ?? ''));
            if ($amount === '') {
                continue;
            }

            $invoiceId = isset($item['invoice_id']) ? (int)$item['invoice_id'] : 0;
            if ($invoiceId <= 0) {
                continue;
            }

            $currency = (string)($item['currency'] ?? '');

            $sql = 'INSERT INTO ' . TBL_BL_PAYMENT_ITEMS . ' (bpi_payment_id, bpi_amount, bpi_currency, bpi_inv_id, bpi_usr_id_create)
                    VALUES (?,?,?,?,?)';
            if ($this->db->queryPrepared($sql, array(
        $paymentId,
        $amount,
        $currency,
        $invoiceId,
        $creatorUserId
            ), false) === false) {
                throw new RuntimeException('Failed to insert payment item.');
            }
    }
    }
}
