<?php
/**
    * TableAccess wrapper for Residents transaction item rows.
    */

require_once(__DIR__ . '/TableResidentsBase.php');

class TableResidentsTransactionItem extends TableResidentsBase
{
    public function __construct(Database $database, int $itemId = 0)
    {
        parent::__construct($database, TBL_BL_TRANS_ITEMS, 'bti', $itemId);
    }

    public function assignTransaction(int $transactionId): void
    {
        $this->setValue('bti_pg_payment_id', $transactionId);
    }

    public function assignInvoice(int $invoiceId): void
    {
        $this->setValue('bti_inv_id', $invoiceId);
    }
}
