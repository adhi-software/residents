<?php
/**
 ***********************************************************************************************
 * TableAccess wrapper for Residents invoices.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/TableResidentsBase.php');

class TableResidentsInvoice extends TableResidentsBase
{
    public function __construct(Database $database, int $invoiceId = 0)
    {
        parent::__construct($database, TBL_BL_INVOICES, 'biv', $invoiceId);
    }

    public function save(bool $updateFingerPrint = true): bool
    {
        $isNew   = $this->isNewRecord();
        $before  = $isNew ? null : BillingHistory::fetchRow($this->db, $this->tableName, $this->keyColumnName, (int)$this->getValue($this->keyColumnName));
        $result  = parent::save($updateFingerPrint);
        if ($result && !$isNew) {
            BillingHistory::log($this->db, TBL_BL_INVOICES_HIST, $before ?? array(), 'update', $GLOBALS['gCurrentUserId'] ?? null);
    }

        return $result;
    }

    public function deleteWithRelations(): bool
    {
        if ($this->isNewRecord()) {
            return false;
    }

        $invoiceId = (int)$this->getValue('biv_id');
        if ($invoiceId <= 0) {
            return false;
    }

        $beforeInvoice  = BillingHistory::fetchRow($this->db, $this->tableName, $this->keyColumnName, $invoiceId);
        $beforeItems    = BillingHistory::fetchRowsByFk($this->db, TBL_BL_INVOICE_ITEMS, 'bii_inv_id', $invoiceId);
        $beforePayItems = BillingHistory::fetchRowsByFk($this->db, TBL_BL_PAYMENT_ITEMS, 'bpi_inv_id', $invoiceId);

        $this->db->startTransaction();
        $ok = true;

        $ok = $ok && ($this->db->queryPrepared('DELETE FROM ' . TBL_BL_INVOICE_ITEMS . ' WHERE bii_inv_id = ?', array($invoiceId), false) !== false);
        $ok = $ok && ($this->db->queryPrepared('DELETE FROM ' . TBL_BL_PAYMENT_ITEMS . ' WHERE bpi_inv_id = ?', array($invoiceId), false) !== false);
        $ok = $ok && ($this->db->queryPrepared('DELETE FROM ' . TBL_BL_TRANS_ITEMS . ' WHERE bti_inv_id = ?', array($invoiceId), false) !== false);

        $result = $ok ? parent::delete() : false;

        if (!$result) {
            $this->db->rollback();
            return false;
    }

        $this->db->endTransaction();

        if ($result) {
            BillingHistory::log($this->db, TBL_BL_INVOICES_HIST, $beforeInvoice ?? array(), 'delete', $GLOBALS['gCurrentUserId'] ?? null);
            foreach ($beforeItems as $row) {
                BillingHistory::log($this->db, TBL_BL_INVOICE_ITEMS_HIST, $row, 'delete', $GLOBALS['gCurrentUserId'] ?? null);
            }
            foreach ($beforePayItems as $row) {
                BillingHistory::log($this->db, TBL_BL_PAYMENT_ITEMS_HIST, $row, 'delete', $GLOBALS['gCurrentUserId'] ?? null);
            }
    }

        return $result;
    }

    public static function fetchList(Database $database, array $filters, array $options): array
    {
        $hasPaidColumn = function_exists('columnExistsBILL') && columnExistsBILL(TBL_BL_INVOICES, 'biv_is_paid');
        $baseConditions = array();
        $baseParams = array();
        $filterConditions = array();
        $filterParams = array();
        $searchCondition = '';
        $searchParams = array();

        $isAdmin = (bool)($filters['is_admin'] ?? false);
        $currentUserId = isset($filters['current_user_id']) ? (int)$filters['current_user_id'] : null;
        if (!$isAdmin && $currentUserId !== null) {
            $baseConditions[] = 'b.biv_usr_id = ?';
            $baseParams[] = $currentUserId;
    }

        if (!empty($filters['filter_group'])) {
            $filterConditions[] = 'EXISTS (
        SELECT 1
                                    FROM ' . TBL_MEMBERS . ' m
                                    JOIN ' . TBL_ROLES . ' r ON r.rol_id = m.mem_rol_id AND r.rol_valid = true
                                    JOIN ' . TBL_CATEGORIES . ' c ON c.cat_id = r.rol_cat_id
                    WHERE m.mem_usr_id = b.biv_usr_id
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
            $filterConditions[] = 'b.biv_usr_id = ?';
            $filterParams[] = (int)$filters['filter_user'];
    }

        if ($hasPaidColumn && isset($filters['filter_paid']) && $filters['filter_paid'] !== '' && $filters['filter_paid'] !== null) {
            $filterConditions[] = 'b.biv_is_paid = ?';
            $filterParams[] = (int)$filters['filter_paid'];
    }

        if (!empty($filters['date_from'])) {
            $filterConditions[] = 'b.biv_end_date >= ?';
            $filterParams[] = (string)$filters['date_from'];
    }

        if (!empty($filters['date_to'])) {
            $filterConditions[] = 'b.biv_start_date <= ?';
            $filterParams[] = (string)$filters['date_to'];
    }

        $searchTerm = trim((string)($filters['search'] ?? ''));
        if ($searchTerm !== '') {
            $searchCondition = " (
        LOWER(COALESCE(b.biv_number, '')) LIKE ?
        OR LOWER(CONCAT_WS(' ', COALESCE(fn.usd_value, ''), COALESCE(ln.usd_value, ''))) LIKE ?
        OR LOWER(COALESCE(u.usr_login_name, '')) LIKE ?
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

        $sql = 'SELECT b.*,
                    CONCAT_WS(\' \', fn.usd_value, ln.usd_value) AS user_name,
                        (SELECT COALESCE(SUM(bii_amount), 0) FROM ' . TBL_BL_INVOICE_ITEMS . ' WHERE bii_inv_id = b.biv_id) AS total_amount,
                        (SELECT bii_currency FROM ' . TBL_BL_INVOICE_ITEMS . ' WHERE bii_inv_id = b.biv_id ORDER BY bii_id DESC LIMIT 1) AS total_currency
                                    FROM ' . TBL_BL_INVOICES . ' b
                LEFT JOIN ' . TBL_USERS . ' u ON u.usr_id = b.biv_usr_id
                LEFT JOIN ' . TBL_USER_DATA . ' ln ON ln.usd_usr_id = u.usr_id AND ln.usd_usf_id = ' . $lnId . '
                LEFT JOIN ' . TBL_USER_DATA . ' fn ON fn.usd_usr_id = u.usr_id AND fn.usd_usf_id = ' . $fnId;

        if (count($whereParts) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
    }

        $sortMap = array(
            'number' => 'b.biv_number',
            'date' => 'b.biv_date',
            'start_date' => 'b.biv_start_date',
            'end_date' => 'b.biv_end_date',
            'status' => $hasPaidColumn ? 'b.biv_is_paid' : 'b.biv_id',
            'user' => 'user_name',
            'due_date' => 'b.biv_due_date',
            'amount' => 'total_amount'
        );
        $sortCol = $filters['sort_col'] ?? 'date';
        $sortDir = strtoupper($filters['sort_dir'] ?? 'DESC');
        $sortDir = in_array($sortDir, array('ASC', 'DESC'), true) ? $sortDir : 'DESC';
        $orderBy = $sortMap[$sortCol] ?? 'b.biv_date';

        if (($options['db_type'] ?? '') === 'pgsql') {
            $sql .= ' ORDER BY ' . $orderBy . ' ' . $sortDir . ' NULLS LAST, b.biv_id DESC';
    } else {
            $sql .= ' ORDER BY ' . $orderBy . ' ' . $sortDir;
            if ($orderBy !== 'b.biv_id') {
                $sql .= ', b.biv_id DESC';
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

        $countBaseSql = 'SELECT COUNT(*) FROM ' . TBL_BL_INVOICES . ' b';
        if (count($baseConditions) > 0) {
            $countBaseSql .= ' WHERE ' . implode(' AND ', $baseConditions);
    }
        $countBaseStatement = $database->queryPrepared($countBaseSql, $baseParams, false);
        $totalBase = $countBaseStatement ? (int)$countBaseStatement->fetchColumn() : 0;

        $countSql = 'SELECT COUNT(*)
                        FROM ' . TBL_BL_INVOICES . ' b
                                    LEFT JOIN ' . TBL_USERS . ' u ON u.usr_id = b.biv_usr_id
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

    public function getItems(): array
    {
        if ($this->isNewRecord()) {
            return array();
    }

        $statement = $this->db->queryPrepared(
            'SELECT * FROM ' . TBL_BL_INVOICE_ITEMS . ' WHERE bii_inv_id = ? ORDER BY bii_id',
            array((int)$this->getValue('biv_id')),
            false
        );

        return $statement ? $statement->fetchAll() : array();
    }

    public function replaceItems(array $items, int $creatorUserId): void
    {
        $invoiceId = (int)$this->getValue('biv_id');
        if ($invoiceId <= 0) {
            throw new RuntimeException('Cannot replace invoice items on unsaved invoice.');
    }

        $existingItems = BillingHistory::fetchRowsByFk($this->db, TBL_BL_INVOICE_ITEMS, 'bii_inv_id', $invoiceId);

        if ($this->db->queryPrepared('DELETE FROM ' . TBL_BL_INVOICE_ITEMS . ' WHERE bii_inv_id = ?', array($invoiceId), false) === false) {
            throw new RuntimeException('Failed to delete existing invoice items.');
    }
        foreach ($existingItems as $row) {
            BillingHistory::log($this->db, TBL_BL_INVOICE_ITEMS_HIST, $row, 'delete', $GLOBALS['gCurrentUserId'] ?? null);
    }

        foreach ($items as $item) {
            $chargeId = (int)($item['charge_id'] ?? 0);
            $name = trim((string)($item['name'] ?? ''));
            if ($chargeId <= 0 || $name === '') {
                continue;
            }

            $startDateRaw = trim((string)($item['start_date'] ?? ''));
            $endDateRaw = trim((string)($item['end_date'] ?? ''));

            $rateRaw = trim((string)($item['rate'] ?? ''));
            $quantityRaw = trim((string)($item['quantity'] ?? ''));
            $amountRaw = trim((string)($item['amount'] ?? ''));

            $rate = $rateRaw !== '' ? str_replace(',', '', $rateRaw) : null;
            $quantity = $quantityRaw !== '' ? str_replace(',', '', $quantityRaw) : null;
            $amount = $amountRaw !== '' ? str_replace(',', '', $amountRaw) : null;

            $columns = array('bii_inv_id', 'bii_chg_id', 'bii_name');
            $values = array($invoiceId, $chargeId, $name);

            $columns[] = 'bii_start_date';
            $values[] = $startDateRaw !== '' ? $startDateRaw : null;
            $columns[] = 'bii_end_date';
            $values[] = $endDateRaw !== '' ? $endDateRaw : null;

            $columns = array_merge($columns, array('bii_type', 'bii_currency', 'bii_rate', 'bii_quantity', 'bii_amount', 'bii_usr_id_create'));
            $values = array_merge($values, array(
        (string)($item['type'] ?? ''),
        (string)($item['currency'] ?? ''),
        $rate,
        $quantity,
        $amount,
        $creatorUserId
            ));

            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $sql = 'INSERT INTO ' . TBL_BL_INVOICE_ITEMS . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';

            if ($this->db->queryPrepared($sql, $values, false) === false) {
                throw new RuntimeException('Failed to insert invoice item.');
            }
    }
    }

    public static function fetchOpenInvoicesByUser(Database $database, int $userId): array
    {
        $hasPaidColumn = function_exists('columnExistsBILL') && columnExistsBILL(TBL_BL_INVOICES, 'biv_is_paid');
        $sql = 'SELECT * FROM ' . TBL_BL_INVOICES . ' WHERE biv_usr_id = ? AND COALESCE(biv_is_paid, 0) = 0 ORDER BY biv_date ASC, biv_id ASC';
        $statement = $database->queryPrepared($sql, array($userId));

        return $statement ? $statement->fetchAll() : array();
    }
}
