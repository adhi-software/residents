<?php
require_once(__DIR__ . '/../../../../adm_program/system/common.php');
require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');

validateApiKey();

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

if ($limit <= 0) {
    $limit = 20;
}
if ($limit > 50) {
    $limit = 50;
}
if ($offset < 0) {
    $offset = 0;
}

$countStmt = $gDb->queryPrepared(
    'SELECT COUNT(*) AS total FROM adm_photos WHERE pho_locked = 0 AND pho_pho_id_parent is NULL',
    array(),
    false
);
$totalRow = $countStmt ? $countStmt->fetch() : false;
if ($countStmt === false) {
    admidioApiError('Database error', 500);
}
$total = $totalRow ? (int) $totalRow['total'] : 0;

$sql = $gDb->queryPrepared(
    'SELECT pho_id, pho_name, pho_quantity, pho_begin, pho_end, pho_description, pho_pho_id_parent
        FROM adm_photos
        WHERE pho_locked = 0 AND pho_pho_id_parent is NULL
        ORDER BY pho_begin DESC, pho_id DESC
        LIMIT ? OFFSET ?',
    array($limit, $offset),
    false
);

if ($sql === false) {
    admidioApiError('Database error', 500);
}

$albums = [];


function getAlbums(array $obj): array
{
    global $gDb; 
    $baseFolder = ADMIDIO_PATH . FOLDER_DATA . '/photos/';
    $photoFiles = [];
    $photoId   = $obj['pho_id'];
    $beginDate = $obj['pho_begin'];

    if ((int) $obj['pho_quantity'] > 0) {
        $folderPath = $baseFolder . $beginDate . '_' . $photoId;
        if (!is_dir($folderPath)) {
            exit;
    }

        // Get all image files inside the folder
        $files = glob($folderPath . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE);
        $photoFiles = [];
        foreach ($files as $filePath) {
            $fileName = pathinfo($filePath, PATHINFO_FILENAME);
            $base64 = base64_encode(file_get_contents($filePath));
            $photoFiles[$fileName] = $base64;
            break;
    }
    }

    return array(
    'id'            => $photoId,
    'name'          => $obj['pho_name'],
    'quantity'      => $obj['pho_quantity'],
    'start'         => $beginDate,
    'end'           => $obj['pho_end'],
    'description'   => $obj['pho_description'],
    'preview'        => $photoFiles,
    );
}

while ($row = $sql->fetch()) {
    $albums[] = getAlbums($row);
}

echo json_encode([
    'albums' => $albums,
    'paging' => [
    'limit' => $limit,
    'offset' => $offset,
    'total' => $total,
    'hasMore' => ($offset + count($albums)) < $total,
    ],
]);
