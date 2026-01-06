<?php
require_once(__DIR__ . '/../../../../adm_program/system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');

validateApiKey();

$folderUuid = admFuncVariableIsValid($_GET, 'folder_uuid', 'string');

/**
 * Return current folder + immediate children (folders + files).
 *
 * @param string $startFolderUuid
 * @return array<string, mixed>
 */
function getFolderContents(string $startFolderUuid): array
{
  global $gDb;

  $folder = new TableFolder($gDb);
  $folder->getFolderForDownload($startFolderUuid);

  $parentUuid = null;
  $parentId = $folder->getValue('fol_fol_id_parent');
  if ($parentId !== null && (int) $parentId > 0) {
    $stmtParent = $gDb->queryPrepared(
      'SELECT fol_uuid FROM ' . TBL_FOLDERS . ' WHERE fol_id = ? LIMIT 1',
      array((int) $parentId),
      false
    );
    $parentRow = $stmtParent ? $stmtParent->fetch(PDO::FETCH_ASSOC) : false;
    if ($parentRow && !empty($parentRow['fol_uuid'])) {
      $parentUuid = (string) $parentRow['fol_uuid'];
    }
  }

  $current = array(
    'uuid' => (string) $folder->getValue('fol_uuid'),
    'name' => (string) $folder->getValue('fol_name'),
    'parentUuid' => $parentUuid,
  );

  $entries = array();

  foreach ($folder->getSubfoldersWithProperties() as $subfolder) {
    $entries[] = array(
      'type' => 'folder',
      'uuid' => (string) ($subfolder['fol_uuid'] ?? ''),
      'name' => (string) ($subfolder['fol_name'] ?? ''),
      'description' => (string) ($subfolder['fol_description'] ?? ''),
      'timestamp' => (string) ($subfolder['fol_timestamp'] ?? ''),
    );
  }

  foreach ($folder->getFilesWithProperties() as $file) {
    $fileUuid = (string) ($file['fil_uuid'] ?? '');
    $entries[] = array(
      'type' => 'file',
      'uuid' => $fileUuid,
      'name' => (string) ($file['fil_name'] ?? ''),
      'description' => (string) ($file['fil_description'] ?? ''),
      'timestamp' => (string) ($file['fil_timestamp'] ?? ''),
      'size' => isset($file['fil_size']) ? (int) $file['fil_size'] : 0,
      'counter' => isset($file['fil_counter']) ? (int) $file['fil_counter'] : 0,
      'folder' => array(
        'uuid' => (string) $folder->getValue('fol_uuid'),
        'name' => (string) $folder->getValue('fol_name'),
      ),
      'canDownload' => true,
      'download' => array(
        'url' => SecurityUtils::encodeUrl(
          FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/api/files/files_download_api.php',
          array('file_uuid' => $fileUuid)
        ),
      ),
    );
  }

  usort($entries, function ($a, $b) {
    $typeA = (string) ($a['type'] ?? '');
    $typeB = (string) ($b['type'] ?? '');
    if ($typeA !== $typeB) {
      return $typeA === 'folder' ? -1 : 1;
    }
    $nameA = strtolower((string) ($a['name'] ?? ''));
    $nameB = strtolower((string) ($b['name'] ?? ''));
    return $nameA <=> $nameB;
  });

  return array(
    'currentFolder' => $current,
    'entries' => $entries,
  );
}

try {
  $payload = getFolderContents((string) $folderUuid);
  echo json_encode($payload);
} catch (AdmException $e) {
  $msg = (string) $e->getMessage();
  if ($msg === 'SYS_FOLDER_NO_RIGHTS') {
    http_response_code(403);
    echo json_encode(array('error' => 'No permission to view files.'));
    exit;
  }
  if ($msg === 'SYS_FOLDER_NOT_FOUND') {
    http_response_code(404);
    echo json_encode(array('error' => 'Folder not found.'));
    exit;
  }
  http_response_code(400);
  echo json_encode(array('error' => $msg));
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(array('error' => 'Unable to load files.'));
}
