<?php
/**
 ***********************************************************************************************
 * Preferences tab: select roles that act as Residents admins
 *
 * Expects $page, $isAdmin, $config to be available from residents.php
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Image;

global $gDb, $gCurrentOrganization, $gL10n, $gCurrentUser;

if (!$isAdmin) {
    $page->addHtml('<div class="alert alert-warning">'.$gL10n->get('RE_ONLY_ADMIN').'</div>');
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newSel = isset($_POST['admin_roles']) ? array_map('intval', (array)$_POST['admin_roles']) : array();
    $config['access']['admin_roles'] = $newSel;

    // Payment Admin Roles
    $newPaymentSel = isset($_POST['payment_admin_roles']) ? array_map('intval', (array)$_POST['payment_admin_roles']) : array();
    $config['access']['payment_admin_roles'] = $newPaymentSel;

    // Default due date days
    $dueDays = isset($_POST['due_days']) ? (int)$_POST['due_days'] : 15;
    if ($dueDays <= 0) {
        $dueDays = 15;
    }
    $config['defaults']['due_days'] = $dueDays;

    $defaultNoteSetting = isset($_POST['default_note']) ? trim((string)$_POST['default_note']) : '';
    $config['defaults']['invoice_note'] = $defaultNoteSetting;

    // Payment Gateway Configurations (multiple)
    $pgJson = isset($_POST['pg_gateways_json']) ? trim((string)$_POST['pg_gateways_json']) : '[]';
    $pgArray = json_decode($pgJson, true);
    $config['payment_gateways'] = is_array($pgArray) ? array_values($pgArray) : array();

    // Organization logo upload (used in invoice PDFs)
    $orgId = isset($gCurrentOrganization) ? (int)$gCurrentOrganization->getValue('org_id') : 0;
    if ($orgId > 0) {
        $logoDir = ADMIDIO_PATH . FOLDER_DATA . '/residents';
        $logoFile = $logoDir . '/org_logo_' . $orgId . '.png';

        if (!empty($_POST['org_logo_remove'])) {
            try {
                if (class_exists('FileSystemUtils')) {
                    FileSystemUtils::deleteFileIfExists($logoFile);
                } elseif (file_exists($logoFile)) {
                    @unlink($logoFile);
                }
            } catch (Exception $e) {
                // ignore delete errors to keep preferences usable
            }
        } elseif (isset($_FILES['userfile']) && isset($_FILES['userfile']['tmp_name'][0])) {
            $uploadError = (int)($_FILES['userfile']['error'][0] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                // No file was uploaded, skip processing
            } elseif ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                $gMessage->show($gL10n->get('SYS_PHOTO_FILE_TO_LARGE', array(round(PhpIniUtils::getUploadMaxSize() / 1024 ** 2))));
            } elseif ($uploadError !== UPLOAD_ERR_OK) {
                // Other upload errors (partial upload, no tmp dir, write error, extension blocked)
                $gMessage->show($gL10n->get('SYS_NO_PICTURE_SELECTED'));
            } elseif (!file_exists($_FILES['userfile']['tmp_name'][0]) || !is_uploaded_file($_FILES['userfile']['tmp_name'][0])) {
                $gMessage->show($gL10n->get('SYS_NO_PICTURE_SELECTED'));
            } else {
                $imageProperties = getimagesize($_FILES['userfile']['tmp_name'][0]);
                if ($imageProperties === false || !in_array($imageProperties['mime'], array('image/jpeg', 'image/png'), true)) {
                    $gMessage->show($gL10n->get('SYS_PHOTO_FORMAT_INVALID'));
                }

                try {
                    if (class_exists('FileSystemUtils')) {
                        FileSystemUtils::createDirectoryIfNotExists($logoDir);
                    } elseif (!is_dir($logoDir)) {
                        @mkdir($logoDir, 0775, true);
                    }

                    $logoImage = new Image($_FILES['userfile']['tmp_name'][0]);
                    $logoImage->setImageType('png');
                    // Keep aspect ratio inside a reasonable box for PDFs
                    $logoImage->scale(600, 200);
                    $logoImage->copyToFile(null, $logoFile);
                    $logoImage->delete();
                } catch (Exception $e) {
                    $gMessage->show($gL10n->get('SYS_DATABASE_ERROR') . ': ' . htmlspecialchars($e->getMessage()));
                }
            }
        }
    }

    residentsWriteConfig($config);
    // Decide where to redirect based on updated permissions
    $stillCanSeePreferences = isResidentsAdmin();
    $redirectTab = $stillCanSeePreferences ? 'preferences' : 'invoices';
    // Redirect after POST (PRG) to refresh permissions/tabs with appropriate target tab
    admRedirect(SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', array('tab' => $redirectTab, 'pref_status' => 'saved')));
    return;
}

$roles = residentsGetRoleOptions();
$selected = $config['access']['admin_roles'] ?? array();
$selectedPayment = $config['access']['payment_admin_roles'] ?? array();

$orgId = isset($gCurrentOrganization) ? (int)$gCurrentOrganization->getValue('org_id') : 0;
$orgLogoUrl = '';
if ($orgId > 0) {
    $orgLogoPath = ADMIDIO_PATH . FOLDER_DATA . '/residents/org_logo_' . $orgId . '.png';
    if (file_exists($orgLogoPath)) {
        // Use PHP endpoint to serve logo (direct file access is blocked by .htaccess)
        $orgLogoUrl = ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/preferences/get_logo.php?v=' . rawurlencode((string)filemtime($orgLogoPath));
    }
}

$form = new HtmlForm('re_preferences', SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/residents.php', array('tab' => 'preferences')), $page, array('enableFileUpload' => true));
$form->addSelectBox('admin_roles', $gL10n->get('RE_PREF_ADMIN_ROLES'), $roles, array('defaultValue' => $selected, 'multiselect' => true));
$form->addSelectBox('payment_admin_roles', $gL10n->get('RE_PREF_PAYMENT_ADMIN'), $roles, array('defaultValue' => $selectedPayment, 'multiselect' => true));

// Organization Logo (used in invoice PDFs)
// Render as a single grouped box (preview + upload + remove button) to avoid duplicate labels.
$logoBoxHtml = '<div class="border rounded bg-light p-3">'
    . '<div class="d-flex flex-wrap align-items-start" style="gap: 16px;">';

if ($orgLogoUrl !== '') {
    $logoBoxHtml .= '<div class="border rounded bg-white p-2" style="max-width:260px;">'
    . '<img class="img-fluid" src="' . htmlspecialchars($orgLogoUrl) . '" alt="' . htmlspecialchars($gL10n->get('RE_ORG_LOGO')) . '" style="max-height:70px; width:auto; display:block;" />'
    . '</div>';
}

$logoBoxHtml .= '<div style="min-width:260px;">'
    . '<input type="file" class="form-control" name="userfile[]" accept="image/png,image/jpeg" />'
    . '<div class="form-text">' . htmlspecialchars($gL10n->get('RE_ORG_LOGO_HELP')) . '</div>';

if ($orgLogoUrl !== '') {
    $logoBoxHtml .= '<button type="submit" name="org_logo_remove" value="1" class="btn btn-sm btn-danger mt-2">'
    . '<i class="bi bi-trash"></i> ' . htmlspecialchars($gL10n->get('RE_REMOVE_ORG_LOGO'))
    . '</button>';
}

$logoBoxHtml .= '</div>'
    . '</div>'
    . '</div>';

$form->addCustomContent($gL10n->get('RE_ORG_LOGO'), $logoBoxHtml);

// choose single group whose members fill owner dropdown
// Default due date configuration
$form->addInput('due_days', $gL10n->get('RE_PREF_DUE_DAYS'), (string)((int)($config['defaults']['due_days'] ?? 15)), array('maxLength' => 3));
$form->addMultilineTextInput('default_note', $gL10n->get('RE_PREF_DEFAULT_NOTE'), (string)($config['defaults']['invoice_note'] ?? ''), 3);

// Payment Gateway Configuration Section — Multiple Gateways
$pgGateways = $config['payment_gateways'] ?? array();
// Ensure it's a re-indexed array
$pgGateways = array_values($pgGateways);

// Hidden input to carry gateway JSON to POST
$form->addHtml('<input type="hidden" name="pg_gateways_json" id="pg_gateways_json" value="' . htmlspecialchars(json_encode($pgGateways), ENT_QUOTES, 'UTF-8') . '" />');

// Modal for Payment Gateway Configuration (reused for add/edit)
$modalHtml = '
<div class="modal fade" id="pgModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
    <h5 class="modal-title" id="pgModalTitle">'.$gL10n->get('RE_PG_ADD_BTN').'</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                    <div id="pg_modal_error" class="alert alert-danger d-none mb-3" role="alert"></div>
                    <div class="row gx-3" style="--bs-gutter-y: 1.5rem;">
                        <div class="col-12">
                            <label class="form-label fw-bold">'.$gL10n->get('RE_PG_NAME').' <span class="text-danger">*</span></label>
                            <select class="form-select" id="pg_m_name">
                                <option value="CCAVENUE">'.$gL10n->get('RE_PG_TYPE_CCAVENUE').'</option>
                                <option value="PAYPAL">'.$gL10n->get('RE_PG_TYPE_PAYPAL').'</option>
                            </select>
                        </div>

                        <!-- CCAvenue Fields -->
                        <div class="col-12" id="pg_grp_ccavenue">
                            <div class="row gx-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">'.$gL10n->get('RE_PG_MERCHANT_ID').' <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control font-monospace" id="pg_m_merchant_id">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">'.$gL10n->get('RE_PG_ACCESS_CODE').' <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control font-monospace" id="pg_m_access_code">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">'.$gL10n->get('RE_PG_WORKING_KEY').' <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control font-monospace" id="pg_m_working_key">
                                </div>
                            </div>
                        </div>

                        <!-- PayPal Fields -->
                        <div class="col-12 d-none" id="pg_grp_paypal">
                            <div class="row gx-3">
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold">'.$gL10n->get('RE_PG_CLIENT_ID').' <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control font-monospace" id="pg_m_client_id">
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">'.$gL10n->get('RE_PG_CLIENT_SECRET').' <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control font-monospace" id="pg_m_client_secret">
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">'.$gL10n->get('RE_PG_GATEWAY_URL').' <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="pg_m_gateway_url">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">'.$gL10n->get('RE_PG_TIMEOUT').'</label>
                            <input type="number" class="form-control" id="pg_m_timeout" min="1" value="15">
                        </div>
                    </div>
            </div>
            <div class="modal-footer bg-light">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">'.$gL10n->get('RE_CANCEL').'</button>
    <button type="button" class="btn btn-primary px-4" id="pg_btn_modal_save"><i class="bi bi-check-lg me-1"></i> '.$gL10n->get('RE_OK').'</button>
            </div>
    </div>
    </div>
</div>';

// Gateway cards container + Add button
$uiHtml = '
<div class="mb-3 row">
    <label class="col-sm-3 col-form-label">'.$gL10n->get('RE_PG_NAME').'</label>
    <div class="col-sm-9">
    <div id="pg_cards_container"></div>
    <div class="mt-2">
            <button type="button" id="pg_btn_add" class="btn btn-outline-primary border-dashed w-100 p-3 text-center">
        <i class="bi bi-plus-circle fs-2 mb-2 d-block"></i>
        <span class="fw-bold">'.$gL10n->get('RE_PG_ADD_BTN').'</span>
            </button>
    </div>
    </div>
</div>
<style>
#pg_btn_add:hover {
    background-color: #4a9a9a !important;
    border-color: #4a9a9a !important;
    color: #fff !important;
}
</style>';
$form->addHtml($uiHtml);
$form->addHtml($modalHtml);

$form->addSubmitButton('btnSave', $gL10n->get('RE_SAVE'));
$page->addHtml($form->show(false));

// Modal for Delete Confirmation
$page->addHtml('
<div class="modal fade" id="pgDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content border-0 shadow">
            <div class="modal-body p-4 text-center">
    <div class="mb-3 text-danger"><i class="bi bi-exclamation-circle fs-1"></i></div>
    <h5 class="fw-bold mb-2">'.$gL10n->get('RE_PG_DELETE_TITLE').'</h5>
    <p class="text-muted mb-4">'.$gL10n->get('RE_PG_DELETE_CONFIRM').'</p>
    <div class="d-grid gap-2">
            <button type="button" class="btn btn-danger" id="pg_btn_modal_delete_confirm">'.$gL10n->get('RE_DELETE').'</button>
            <button type="button" class="btn btn-light text-muted" data-bs-dismiss="modal" data-dismiss="modal">'.$gL10n->get('RE_CANCEL').'</button>
    </div>
            </div>
    </div>
    </div>
</div>

<script>
$(function(){
    var pgModal = new bootstrap.Modal(document.getElementById("pgModal"));
    var pgDeleteModal = new bootstrap.Modal(document.getElementById("pgDeleteModal"));

    // Gateway data array — initialized from PHP
    var pgGateways = [];
    try {
        pgGateways = JSON.parse(document.getElementById("pg_gateways_json").value || "[]");
    } catch(e) { pgGateways = []; }

    // Currently editing index (-1 = adding new)
    var pgEditIndex = -1;
    // Currently deleting index
    var pgDeleteIndex = -1;

    var fieldKeys = ["name", "merchant_id", "access_code", "working_key", "gateway_url", "timeout", "client_id", "client_secret"];
    var modalFieldIds = ["pg_m_name", "pg_m_merchant_id", "pg_m_access_code", "pg_m_working_key", "pg_m_gateway_url", "pg_m_timeout", "pg_m_client_id", "pg_m_client_secret"];

    function getRequiredFields() {
        var type = $("#pg_m_name").val();
        var common = ["pg_m_name", "pg_m_gateway_url"];
        if (type === "CCAVENUE") return common.concat(["pg_m_merchant_id", "pg_m_access_code", "pg_m_working_key"]);
        if (type === "PAYPAL")   return common.concat(["pg_m_client_id", "pg_m_client_secret"]);
        return common;
    }

    function toggleFields() {
        var type = $("#pg_m_name").val();
        if (type === "PAYPAL") {
            $("#pg_grp_ccavenue").addClass("d-none");
            $("#pg_grp_paypal").removeClass("d-none");
        } else {
            $("#pg_grp_ccavenue").removeClass("d-none");
            $("#pg_grp_paypal").addClass("d-none");
        }
    }

    $("#pg_m_name").on("change", toggleFields);

    // Render all gateway cards from the array
    function renderCards() {
        var container = $("#pg_cards_container");
        container.empty();
        if (pgGateways.length === 0) {
            container.html("<p class=\"text-muted fst-italic mb-0\"><i class=\"bi bi-info-circle me-1\"></i> '.$gL10n->get('RE_NO_GATEWAY_CONFIGURED').'</p>");
        } else {
            pgGateways.forEach(function(gw, idx) {
                var card = ""
                    + "<div class=\"card shadow-sm border-0 bg-light mb-2\">"
                    + "  <div class=\"card-body d-flex justify-content-between align-items-center p-3\">"
                    + "    <div class=\"d-flex align-items-center\">"
                    + "      <div class=\"bg-white rounded-circle p-2 me-3 shadow-sm text-primary\">"
                    + "        <i class=\"bi bi-credit-card fs-4\"></i>"
                    + "      </div>"
                                        + "      <div>"
                    + "        <h6 class=\"mb-0 fw-bold\">" + $("<span>").text(gw.name || "Unnamed").html() + "</h6>"
                    + "      </div>"
                    + "    </div>"
                    + "    <div class=\"btn-group\">"
                    + "      <button type=\"button\" class=\"btn btn-sm btn-light text-primary pg-edit-btn\" data-idx=\"" + idx + "\" title=\"'.$gL10n->get('RE_EDIT').'\"><i class=\"bi bi-pencil\"></i></button>"
                    + "      <button type=\"button\" class=\"btn btn-sm btn-light text-danger pg-delete-btn\" data-idx=\"" + idx + "\" title=\"'.$gL10n->get('RE_DELETE').'\"><i class=\"bi bi-trash\"></i></button>"
                    + "    </div>"
                    + "  </div>"
                    + "</div>";
                container.append(card);
            });
        }

        // Toggle add button visibility based on available types (CCAVENUE, PAYPAL)
        var types = pgGateways.map(function(g){ return g.name; });
        if (types.indexOf("CCAVENUE") >= 0 && types.indexOf("PAYPAL") >= 0) {
            $("#pg_btn_add").addClass("d-none");
        } else {
            $("#pg_btn_add").removeClass("d-none");
        }
        syncHidden();
    }

    // Keep hidden input in sync
    function syncHidden() {
        $("#pg_gateways_json").val(JSON.stringify(pgGateways));
    }

    // Clear modal
    function clearModal() {
        // Update selection availability
        var existingTypes = pgGateways.map(function(g){ return g.name; });
        $("#pg_m_name option").each(function(){
            var val = $(this).val();
            if (existingTypes.indexOf(val) >= 0) {
                $(this).prop("disabled", true);
            } else {
                $(this).prop("disabled", false);
            }
        });

        // Select first non-disabled option
        var $first = $("#pg_m_name option:not(:disabled)").first();
        if ($first.length) {
            $("#pg_m_name").val($first.val());
        }

        modalFieldIds.forEach(function(id) { if(id !== "pg_m_name") $("#" + id).val(""); });
        $("#pg_m_timeout").val("15");
        toggleFields();
        $(".is-invalid").removeClass("is-invalid");
        $("#pg_modal_error").addClass("d-none").text("");
    }

    // Populate modal from a gateway object
    function populateModal(gw) {
        // Enable all for current edit
        $("#pg_m_name option").prop("disabled", false);
        
        // Disable other taken types
        var existingTypes = pgGateways.map(function(g, i){ return i === pgEditIndex ? "" : g.name; });
        $("#pg_m_name option").each(function(){
            var val = $(this).val();
            if (val !== "" && existingTypes.indexOf(val) >= 0) {
                $(this).prop("disabled", true);
            }
        });

        $("#pg_m_name").val(gw.name || "CCAVENUE");
        $("#pg_m_merchant_id").val(gw.merchant_id || "");
        $("#pg_m_access_code").val(gw.access_code || "");
        $("#pg_m_working_key").val(gw.working_key || "");
        $("#pg_m_client_id").val(gw.client_id || "");
        $("#pg_m_client_secret").val(gw.client_secret || "");
        $("#pg_m_gateway_url").val(gw.gateway_url || "");
        $("#pg_m_timeout").val(gw.timeout || 15);
        toggleFields();
    }

    // Read modal fields into a gateway object
    function readModal() {
        var type = $("#pg_m_name").val();
        var gw = {
            name:          type,
            gateway_url:   $("#pg_m_gateway_url").val().trim(),
            timeout:       parseInt($("#pg_m_timeout").val()) || 15
        };
        
        if (type === "PAYPAL") {
            gw.client_id     = $("#pg_m_client_id").val().trim();
            gw.client_secret = $("#pg_m_client_secret").val().trim();
        } else {
            gw.merchant_id   = $("#pg_m_merchant_id").val().trim();
            gw.working_key   = $("#pg_m_working_key").val().trim();
            gw.access_code   = $("#pg_m_access_code").val().trim();
        }
        return gw;
    }

    // Real-time validation removal
    modalFieldIds.forEach(function(id){
        $("#" + id).on("input change", function(){
            if($(this).val().trim() !== "") $(this).removeClass("is-invalid");
            $("#pg_modal_error").addClass("d-none");
        });
    });

    // Add button
    $("#pg_btn_add").on("click", function(e){
        e.preventDefault();
        pgEditIndex = -1;
        clearModal();
        $("#pgModalTitle").text("'.$gL10n->get('RE_PG_ADD_BTN').'");
        pgModal.show();
    });

    // Edit button (delegated)
    $("#pg_cards_container").on("click", ".pg-edit-btn", function(e){
        e.preventDefault();
        pgEditIndex = parseInt($(this).data("idx"));
        clearModal();
        populateModal(pgGateways[pgEditIndex]);
        $("#pgModalTitle").text("'.$gL10n->get('RE_EDIT').'");
        pgModal.show();
    });

    // Delete button (delegated)
    $("#pg_cards_container").on("click", ".pg-delete-btn", function(e){
        e.preventDefault();
        pgDeleteIndex = parseInt($(this).data("idx"));
        pgDeleteModal.show();
    });

    $("#pg_btn_modal_save").on("click", function(){
        var isValid = true;
        var rFields = getRequiredFields();
        modalFieldIds.forEach(function(id){ $("#" + id).removeClass("is-invalid"); });
        $("#pg_modal_error").addClass("d-none").text("");

        rFields.forEach(function(id){
            if($("#" + id).val().trim() === "") {
                $("#" + id).addClass("is-invalid");
                isValid = false;
            }
        });

        if (!isValid) {
            $("#pg_modal_error").text("Please fill in all required fields.").removeClass("d-none");
            return;
        }

        var gw = readModal();
        if (pgEditIndex >= 0) {
            pgGateways[pgEditIndex] = gw;
        } else {
            pgGateways.push(gw);
        }
        renderCards();
        pgModal.hide();
    });

    // Delete confirm
    $("#pg_btn_modal_delete_confirm").on("click", function(){
        if (pgDeleteIndex >= 0 && pgDeleteIndex < pgGateways.length) {
            pgGateways.splice(pgDeleteIndex, 1);
        }
        pgDeleteIndex = -1;
        renderCards();
        pgDeleteModal.hide();
    });

    // Sync hidden field before form submit
    $("#re_preferences").on("submit", function() {
        syncHidden();
    });

    // Initial render
    renderCards();
});
</script>
');

$uninstallUrl = SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_RE . '/installation.php', array('mode' => 'uninstall'));
$page->addHtml('<div class="mt-3"><a class="btn btn-danger text-white" href="' . $uninstallUrl . '" onclick="return confirm(\'' . htmlspecialchars($gL10n->get('RE_UNINSTALL_CONFIRM'), ENT_QUOTES, 'UTF-8') . '\');"font-size: i class="bi bi-trash"></i> ' . $gL10n->get('RE_UNINSTALL_RESIDENTS') . '</a></div>');
