<?php
/**
 ***********************************************************************************************
 * API endpoint to return a list of organizations for the login page
 *
 * This endpoint is intentionally unauthenticated so mobile clients can populate an
 * organization selector before calling auth/login.php.
 ***********************************************************************************************
 */

require_once(__DIR__ . '/../../common_function.php');
header('Content-Type: application/json; charset=utf-8');

try {
    // Only the id and display name are needed by the login org picker. org_uuid /
    // org_shortname are deliberately not exposed on this unauthenticated endpoint.
    $stmt = $gDb->queryPrepared(
        'SELECT org_id, org_longname
            FROM ' . TBL_ORGANIZATIONS . '
            ORDER BY org_longname ASC',
        array(),
        false
    );

    if ($stmt === false) {
        admidioApiError('Database error', 500);
    }

    $organizations = array();
    while ($row = $stmt->fetch()) {
        $organizations[] = array(
            'id' => (int) ($row['org_id'] ?? 0),
            'name' => (string) ($row['org_longname'] ?? ''),
        );
    }

    echo json_encode(array('organizations' => $organizations));
} catch (Exception $exception) {
    admidioApiError('Unable to load organizations', 500, array(
        'exception' => get_class($exception)
    ));
}
