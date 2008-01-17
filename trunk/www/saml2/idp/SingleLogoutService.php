<?php


require_once('../../../www/_include.php');


require_once('SimpleSAML/Utilities.php');
require_once('SimpleSAML/Session.php');
require_once('SimpleSAML/Metadata/MetaDataStorageHandler.php');
require_once('SimpleSAML/XML/SAML20/LogoutRequest.php');
require_once('SimpleSAML/XML/SAML20/LogoutResponse.php');
require_once('SimpleSAML/Bindings/SAML20/HTTPRedirect.php');
//require_once('SimpleSAML/Bindings/SAML20/HTTPPost.php');
require_once('SimpleSAML/XHTML/Template.php');


$config = SimpleSAML_Configuration::getInstance();
$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();

$idpentityid = $metadata->getMetaDataCurrentEntityID('saml20-idp-hosted');

$session = SimpleSAML_Session::getInstance();

/*
 * If we get an LogoutRequest then we initiate the logout process.
 */
if (isset($_GET['SAMLRequest'])) {

	$binding = new SimpleSAML_Bindings_SAML20_HTTPRedirect($config, $metadata);
	$logoutrequest = $binding->decodeLogoutRequest($_GET);


	/* Check if we have a valid session. */
	if($session === NULL) {
		/* Invalid session. To prevent the user from being unable to
		 * log out from the service provider, we should just return a
		 * LogoutResponse pretending that the logout was successful to
		 * the SP that sent the LogoutRequest.
		 */

		$spentityid = $logoutrequest->getIssuer();

		/* Generate the response. */
		$response = new SimpleSAML_XML_SAML20_LogoutResponse($config,
			$metadata);
		$responseText = $response->generate($idpentityid, $spentityid,
			$logoutrequest->getRequestID(), 'IdP');

		/* Retrieve the relay state from the request. */
		$relayState = $logoutrequest->getRelayState();

		/* Send the response using the HTTP-Redirect binding. */
		$binding = new SimpleSAML_Bindings_SAML20_HTTPRedirect($config,
			$metadata);
		$binding->sendMessage($responseText, $idpentityid, $spentityid, $relayState,
			'SingleLogoutService', 'SAMLResponse', 'IdP');
		exit;
	}

	
	$session->setAuthenticated(false);

	//$requestid = $authnrequest->getRequestID();
	//$session->setAuthnRequest($requestid, $authnrequest);
	
	//echo '<pre>' . htmlentities($logoutrequest->getXML()) . '</pre>';
	
	error_log('IdP LogoutService: got Logoutrequest from ' . $logoutrequest->getIssuer() . '  ');
	
	$session->set_sp_logout_completed($logoutrequest->getIssuer() );
	$session->setLogoutRequest($logoutrequest);

/*
 * We receive a Logout Response to a Logout Request that we have issued earlier.
 */
} elseif (isset($_GET['SAMLResponse'])) {

	$binding = new SimpleSAML_Bindings_SAML20_HTTPRedirect($config, $metadata);
	$loginresponse = $binding->decodeLogoutResponse($_GET);
	
	$session->set_sp_logout_completed($loginresponse->getIssuer());
	
	error_log('IdP LogoutService: got LogoutResponse from ' . $loginresponse->getIssuer() . '  ');
}


/* Dump the current sessions (for debugging). */
$session->dump_sp_sessions();


/*
 * We proceed to send logout requests to all remaining SPs.
 */
$spentityid = $session->get_next_sp_logout();
if ($spentityid) {

	error_log('IdP LogoutService: next SP ' . $spentityid);

	try {
		$lr = new SimpleSAML_XML_SAML20_LogoutRequest($config, $metadata);
	
		// ($issuer, $receiver, $nameid, $nameidformat, $sessionindex, $mode) {
		$req = $lr->generate($idpentityid, $spentityid, $session->getNameID(), $session->getNameIDFormat(), $session->getSessionIndex(), 'IdP');
		
		$httpredirect = new SimpleSAML_Bindings_SAML20_HTTPRedirect($config, $metadata);
		
		$relayState = SimpleSAML_Utilities::selfURL();
		if (isset($_GET['RelayState'])) {
			$relayState = $_GET['RelayState'];
			/* Remove any magic quotes that php may have added. */
			if(get_magic_quotes_gpc()) {
				$relayState = stripslashes($relayState);
			}
		}
		
		//$request, $remoteentityid, $relayState = null, $endpoint = 'SingleLogoutService', $direction = 'SAMLRequest', $mode = 'SP'
		$httpredirect->sendMessage($req, $idpentityid, $spentityid, $relayState, 'SingleLogoutService', 'SAMLRequest', 'IdP');
		
		exit();

	} catch(Exception $exception) {
		
		$et = new SimpleSAML_XHTML_Template($config, 'error.php');
		
		$et->data['header'] = 'Error sending logout request to service';
		$et->data['message'] = 'Some error occured when trying to issue the logout response, and send it to the SP.';	
		$et->data['e'] = $exception;
		
		$et->show();
		exit(0);
	}


}

/*
 * Logout procedure is done and we send a Logout Response back to the SP
 */
error_log('IdP LogoutService:  SPs done ');
try {

	$logoutrequest = $session->getLogoutRequest();
	if (!$logoutrequest) {
		throw new Exception('Could not get reference to the logout request.');
	}

	$rg = new SimpleSAML_XML_SAML20_LogoutResponse($config, $metadata);
	
	// generate($issuer, $receiver, $inresponseto, $mode )
	
	$logoutResponseXML = $rg->generate($idpentityid, $logoutrequest->getIssuer(), $logoutrequest->getRequestID(), 'IdP');
	
	//	echo '<pre>' . htmlentities($logoutResponseXML) . '</pre>';
	//	exit();
	
	$httpredirect = new SimpleSAML_Bindings_SAML20_HTTPRedirect($config, $metadata);
	
	$relayState = SimpleSAML_Utilities::selfURL();
	if (isset($_GET['RelayState'])) {
		$relayState = $_GET['RelayState'];
		/* Remove any magic quotes that php may have added. */
		if(get_magic_quotes_gpc()) {
			$relayState = stripslashes($relayState);
		}
	}
	
	//$request, $remoteentityid, $relayState = null, $endpoint = 'SingleLogoutService', $direction = 'SAMLRequest', $mode = 'SP'
	$httpredirect->sendMessage($logoutResponseXML, $idpentityid, $logoutrequest->getIssuer(), $relayState, 'SingleLogoutService', 'SAMLResponse', 'IdP');

} catch(Exception $exception) {
	
	$et = new SimpleSAML_XHTML_Template($config, 'error.php');
	
	$et->data['header'] = 'Error sending response to service';
	$et->data['message'] = 'Some error occured when trying to issue the logout response, and send it to the SP.';	
	$et->data['e'] = $exception;
	
	$et->show();

}



?>