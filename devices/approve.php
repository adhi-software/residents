<?php
/**
 ***********************************************************************************************
 * Approve a mobile login device for API access (admins only)
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../system/common.php');
require_once(__DIR__ . '/../../../system/login_valid.php');

global $gDb, $gL10n, $gCurrentUserId;

$scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php';
if (!isUserAuthorizedForResidents($scriptUrl)) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$deviceID = admFuncVariableIsValid($_GET, 'id', 'int');
$isAdmin = isResidentsAdminBySettings();
if (!$isAdmin) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}
$device = new TableResidentsDevice($gDb, $deviceID);

$isActive = (int)$device->getValue('rde_is_active');
if ($deviceID >= 0 && $device->isNewRecord()) {
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}
elseif ($isActive) {
    $gMessage->show($gL10n->get('RE_DEVICES_ALREADY_APPROVED') . ' (ID: ' . $deviceID . ')');
}else{
    // Enforce "one active device per account" at approval time. Two devices can each
    // create a pending request while both are unapproved (the registration/login
    // "already active" check passes for both), so block approving a second one here
    // unless the member is exempt via "Allow Multiple Devices".
    $ownerUserId = (int) $device->getValue('rde_usr_id');
    if (!residentsUserAllowsMultipleDevices($ownerUserId)
        && residentsUserHasOtherActiveDevice($ownerUserId, (string) $device->getValue('rde_device_id'), (int) $device->getValue('rde_org_id'))) {
        $params = array(
            'tab' => 'devices',
            'device_status' => 'error',
            'device_message' => 'This user already has an active device. Unapprove it first, or enable "Allow Multiple Devices" for this user.',
        );
        admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', $params));
    }

    $apiKey = residentsApproveDevice($deviceID, $gCurrentUserId);
    $saved = ($apiKey !== null);
    $params = array('tab' => 'devices');
    if ($saved) {
        $params['device_status'] = 'approved';
    } else {
        $params['device_status'] = 'error';
        $params['device_message'] = 'Failed to approve device.';
    }
    admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', $params));
}
