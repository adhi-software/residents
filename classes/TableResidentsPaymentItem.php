<?php
/**
 * TableAccess wrapper for Residents payment item rows.
 */

require_once(__DIR__ . '/TableResidentsBase.php');

class TableResidentsPaymentItem extends TableResidentsBase
{
  public function __construct(Database $database, int $itemId = 0)
  {
    parent::__construct($database, TBL_BL_PAYMENT_ITEMS, 'bpi', $itemId);
  }

  public function assignToPayment(int $paymentId): void
  {
    $this->setValue('bpi_payment_id', $paymentId);
  }

  public function assignInvoice(int $invoiceId): void
  {
    $this->setValue('bpi_inv_id', $invoiceId);
  }

  public function save(bool $updateFingerPrint = true): bool
  {
    $isNew   = $this->isNewRecord();
    $before  = $isNew ? null : BillingHistory::fetchRow($this->db, $this->tableName, $this->keyColumnName, (int)$this->getValue($this->keyColumnName));
    $result  = parent::save($updateFingerPrint);
    if ($result && !$isNew) {
      BillingHistory::log($this->db, TBL_BL_PAYMENT_ITEMS_HIST, $before ?? array(), 'update', $GLOBALS['gCurrentUserId'] ?? null);
    }

    return $result;
  }

  public function delete(): bool
  {
    if ($this->isNewRecord()) {
      return false;
    }

    $id      = (int)$this->getValue($this->keyColumnName);
    $before  = BillingHistory::fetchRow($this->db, $this->tableName, $this->keyColumnName, $id);
    $result  = parent::delete();
    BillingHistory::log($this->db, TBL_BL_PAYMENT_ITEMS_HIST, $before ?? array(), 'delete', $GLOBALS['gCurrentUserId'] ?? null);

    return $result;
  }
}
