<?php
/**
    * TableAccess wrapper for Residents payment gateway transactions.
    */

require_once(__DIR__ . '/TableResidentsBase.php');

class TableResidentsTransaction extends TableResidentsBase
{
    public function __construct(Database $database, int $transactionId = 0)
    {
        parent::__construct($database, TBL_BL_TRANS, 'btr', $transactionId);
    }

    public function assignPayment(int $paymentId): void
    {
        $this->setValue('btr_payment_id', $paymentId);
    }

    public function getItems(): array
    {
        if ($this->isNewRecord()) {
            return array();
    }

        $statement = $this->db->queryPrepared(
            'SELECT * FROM ' . TBL_BL_TRANS_ITEMS . ' WHERE bti_pg_payment_id = ? ORDER BY bti_id',
            array((int)$this->getValue('btr_id'))
        );

        return $statement ? $statement->fetchAll() : array();
    }

    public function replaceItems(array $items, int $creatorUserId): void
    {
        if ($this->isNewRecord()) {
            throw new RuntimeException('Cannot replace transaction items on unsaved transaction.');
    }

        $transactionId = (int)$this->getValue('btr_id');
        $this->db->queryPrepared('DELETE FROM ' . TBL_BL_TRANS_ITEMS . ' WHERE bti_pg_payment_id = ?', array($transactionId), false);

        foreach ($items as $item) {
            $invoiceId = isset($item['invoice_id']) ? (int)$item['invoice_id'] : 0;
            if ($invoiceId <= 0) {
                continue;
            }

            $amount = trim((string)($item['amount'] ?? ''));
            if ($amount === '') {
                continue;
            }

            $currency = (string)($item['currency'] ?? '');

            $sql = 'INSERT INTO ' . TBL_BL_TRANS_ITEMS . ' (bti_pg_payment_id, bti_inv_id, bti_amount, bti_currency, bti_usr_id_create)
                    VALUES (?,?,?,?,?)';
            $this->db->queryPrepared($sql, array(
        $transactionId,
        $invoiceId,
        $amount,
        $currency,
        $creatorUserId
            ), false);
    }
    }

    public static function expireInitiated(Database $database, string $thresholdTimestamp): void
    {
        $sql = 'UPDATE ' . TBL_BL_TRANS . ' SET btr_status = ? WHERE btr_status = ? AND btr_pg_trans_date < ?';
        $database->queryPrepared($sql, array('TO', 'IT', $thresholdTimestamp), false);
    }
}
