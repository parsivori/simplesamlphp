<?php
/**
 * Show a 403 Forbidden page about not authorized to access an application.
 *
 * @package simpleSAMLphp
 * @version $Id$
 */

if (!array_key_exists('StateId', $_REQUEST)) {
	throw new SimpleSAML_Error_BadRequest('Missing required StateId query parameter.');
}

$id = $_REQUEST['StateId'];

// sanitize the input
$restartURL = SimpleSAML_Utilities::getURLFromStateID($id);
if (!is_null($restartURL)) {
	SimpleSAML_Utilities::checkURLAllowed($restartURL);
}

$state = SimpleSAML_Auth_State::loadState($id, 'authorize:Authorize');

$globalConfig = SimpleSAML_Configuration::getInstance();
$t = new SimpleSAML_XHTML_Template($globalConfig, 'authorize:authorize_403.php');
if (isset($state['Source']['auth'])) {
    $t->data['LogoutURL'] = SimpleSAML_Module::getModuleURL('core/authenticate.php', array('as' => $state['Source']['auth']))."&logout";
}
header('HTTP/1.0 403 Forbidden');
$t->show();


?>
