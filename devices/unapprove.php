<?php
/**
 * Unapprove (deactivate) a Mobile Login Device (admins only).
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');

global $gDb, $gL10n, $gCurrentUserId;

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php';
if (!isUserAuthorizedForBilling($scriptUrl)) {
  $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if (!isBillingAdminBySettings()) {
  $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$deviceId = admFuncVariableIsValid($_GET, 'id', 'int');
if ($deviceId <= 0) {
  $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$device = new TableResidentsDevice($gDb, $deviceId);
if ($device->isNewRecord()) {
  $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

$isActive = (int)$device->getValue('bde_is_active');
if ($isActive !== 1) {
  admRedirect(SecurityUtils::encodeUrl(
    ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php',
    array('tab' => 'devices')
  ));
}

$device->setValue('bde_is_active', 0);
$device->setValue('bde_api_key', '');
$device->setValue('bde_usr_id_change', $gCurrentUserId);
$device->setValue('bde_timestamp_change', date('Y-m-d H:i:s'));
$saved = $device->save();

$params = array('tab' => 'devices');
if ($saved) {
  $params['device_status'] = 'unapproved';
} else {
  $params['device_status'] = 'error';
  $params['device_message'] = 'Failed to unapprove device.';
}

admRedirect(SecurityUtils::encodeUrl(
  ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php',
  $params
));
