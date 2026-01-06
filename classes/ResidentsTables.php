<?php
/**
 * Compatibility loader for Residents TableAccess wrappers.
 *
 * Older code includes this file, so we forward to the new per-class files.
 */

require_once(__DIR__ . '/TableResidentsCharge.php');
require_once(__DIR__ . '/BillingHistory.php');
require_once(__DIR__ . '/TableResidentsInvoice.php');
require_once(__DIR__ . '/TableResidentsInvoiceItem.php');
require_once(__DIR__ . '/TableResidentsPayment.php');
require_once(__DIR__ . '/TableResidentsPaymentItem.php');
require_once(__DIR__ . '/TableResidentsTransaction.php');
require_once(__DIR__ . '/TableResidentsTransactionItem.php');
require_once(__DIR__ . '/TableResidentsDevice.php');
