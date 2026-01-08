<?php
/**
 ***********************************************************************************************
 * Delete a payment and its related items. Admins only.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');

global $gDb, $gL10n, $gCurrentUser;

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php';
if (!isUserAuthorizedForBilling($scriptUrl)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if (!isPaymentAdmin()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

try {
    SecurityUtils::validateCsrfToken($_POST['admidio-csrf-token'] ?? '');
} catch (Exception $e) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$id = admFuncVariableIsValid($_POST, 'id', 'int');
if ($id <= 0) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$payment = new TableResidentsPayment($gDb, $id);
if ($payment->isNewRecord()) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

if ($payment->getValue('bpa_pay_type') === 'Online') {
    $gMessage->show('Online payments cannot be deleted.');
}

$actingUserId = (int)$gCurrentUser->getValue('usr_id');
$deleted = $payment->deleteWithRelations($actingUserId);

$params = array('tab' => 'payments');
if ($deleted) {
    $params['payment_status'] = 'deleted';
} else {
    $params['payment_status'] = 'error';
    $params['payment_message'] = 'Failed to delete payment.';
}

$redirectUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php', $params);
admRedirect($redirectUrl);
