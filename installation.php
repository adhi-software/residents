<?php
/**
 ***********************************************************************************************
 * Installation routine for Residents plugin
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Ramsey\Uuid\Uuid;

require_once(__DIR__ . '/../../adm_program/system/common.php');
require_once(__DIR__ . '/common_function.php');
require_once(__DIR__ . '/classes/ConfigTables.php');

// only administrators may run installation
if (!$gCurrentUser->isAdministrator()) {
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$gNavigation->addStartUrl(CURRENT_URL);

$getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'start', 'validValues' => array('start', 'install', 'uninstall')));

$headline = $gL10n->get('BL_INSTALL_HEADLINE');
$page = new HtmlPage('bl-residents-installation', $headline);
billingEnqueueStyles($page);

$isInstalled = tableExistsBILL(TBL_BL_INVOICES);

if ($getMode === 'install') {
    $creator = new ConfigTables();
    $creator->init();
    ensureBillingMenuItem();

    // Seed a default invoice note so the setting is visible immediately after install
    $config = billingReadConfig();
    if (!isset($config['defaults']['invoice_note']) || trim((string)$config['defaults']['invoice_note']) === '') {
        $config['defaults']['invoice_note'] = $gL10n->get('BL_DEFAULT_NOTE_TEXT');
        billingWriteConfig($config);
    }

    $page->addHtml('<p>' . $gL10n->get('BL_INSTALL_DONE') . '</p>');
    $page->addHtml('<a class="btn btn-secondary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/residents.php') . '"><i class="fas fa-file-invoice-dollar"></i> ' . $gL10n->get('BL_OPEN_RESIDENTS') . '</a> ');
    $page->addHtml('<a class="btn btn-danger" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/installation.php', array('mode' => 'uninstall')) . '" onclick="return confirm(\'' . $gL10n->get('BL_UNINSTALL_CONFIRM') . '\');"><i class="fas fa-trash"></i> ' . $gL10n->get('BL_UNINSTALL_RESIDENTS') . '</a>');
} elseif ($getMode === 'uninstall') {
    $creator = new ConfigTables();
    $creator->uninstall();
    removeBillingMenuItem();
    billingDeleteConfig();
    $page->addHtml('<div class="alert alert-success">' . $gL10n->get('BL_UNINSTALL_DONE') . '</div>');
    $page->addHtml('<a class="btn btn-secondary" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/installation.php', array('mode' => 'install')) . '"><i class="fas fa-arrow-circle-right"></i> ' . $gL10n->get('BL_INSTALL') . '</a>');
} else {
    $page->addHtml('<p>' . $gL10n->get('BL_INSTALL_DESC', array('<code>' . TBL_BL_INVOICES . '</code>', '<code>' . TBL_BL_INVOICE_ITEMS . '</code>', '<code>' . TBL_BL_CHARGES . '</code>')) . '</p>');

    if ($isInstalled) {
        $page->addHtml('<div class="alert alert-info">' . $gL10n->get('BL_INSTALL_ALREADY') . '</div>');
    }

    $form = new HtmlForm('installation_start_form', null, $page, array('setFocus' => false));
    if (!$isInstalled) {
        $form->addButton('btnInstall', $gL10n->get('BL_INSTALL'), array('icon' => 'fa-arrow-circle-right', 'link' => SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/installation.php', array('mode' => 'install')), 'class' => 'btn-primary'));
        $page->addHtml($form->show(false));
    }

    if ($isInstalled) {
        $page->addHtml('<a class="btn btn-danger text-white" href="' . SecurityUtils::encodeUrl(ADMIDIO_URL . FOLDER_PLUGINS . PLUGIN_FOLDER_BILL . '/installation.php', array('mode' => 'uninstall')) . '" onclick="return confirm(\'' . $gL10n->get('BL_UNINSTALL_CONFIRM') . '\');"><i class="fas fa-trash"></i> ' . $gL10n->get('BL_UNINSTALL_RESIDENTS') . '</a>');
    }
}

$page->show();
