<?php
/**
 * Bulk delete Mobile Login Device (admin-only)
 */
use Admidio\Exception; // if available

require_once(__DIR__ . '/../common_function.php');
require_once(__DIR__ . '/../../../adm_program/system/login_valid.php');

try {
  global $gL10n;
  $scriptUrl = FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php';
  if (!isUserAuthorizedForBilling($scriptUrl)) {
    http_response_code(403);
    echo 'FORBIDDEN';
    exit;
  }

  // Security: only admins defined in settings (no Admidio admin fallback)
  if (!isBillingAdminBySettings()) {
    http_response_code(403);
    echo 'FORBIDDEN';
    exit;
  }

  $ids = $_POST['ids'] ?? [];
  if (!is_array($ids)) {
    $ids = [];
  }

  $deleted = 0;
  $failed = 0;
  foreach ($ids as $id) {
    $id = (int)$id;
    if ($id <= 0) {
      continue;
    }
    // Delete Mobile Login Device
    $device = new TableResidentsDevice($gDb);
    if ($device->readDataById($id)) {
      if ($device->delete()) {
        $deleted++;
      } else {
        $failed++;
      }
    }
  }

  if ($failed > 0) {
    http_response_code(500);
    echo 'ERROR';
    exit;
  }

  echo 'OK';
} catch (Throwable $e) {
  http_response_code(500);
  echo 'ERROR';
}
