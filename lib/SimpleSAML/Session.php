<?php


/**
 * SimpleSAMLphp
 *
 * PHP versions 4 and 5
 *
 * LICENSE: See the COPYING file included in this distribution.
 *
 * @author Andreas �kre Solberg, UNINETT AS. <andreas.solberg@uninett.no>
 */


require_once('SimpleSAML/Configuration.php');
require_once('SimpleSAML/Utilities.php');
require_once('SimpleSAML/Session.php');
require_once('SimpleSAML/XML/MetaDataStore.php');
require_once('SimpleSAML/XML/SAML20/AuthnRequest.php');
require_once('SimpleSAML/XML/AuthnResponse.php');
require_once('SimpleSAML/Bindings/SAML20/HTTPRedirect.php');

/**
 * A class representing a session.
 */
class SimpleSAML_Session {

	const STATE_ONLINE = 1;
	const STATE_LOGOUTINPROGRESS = 2;
	const STATE_LOGGEDOUT = 3;

	private static $instance = null;

	private $configuration = null;
	
	private $authnrequests = array();
	private $authnresponse = null;
	
	private $logoutrequest = null;
	
	private $authenticated = null;
	private $protocol = null;
	private $attributes = null;
	
	
	private $sessionindex = null;
	private $nameid = null;
	private $nameidformat = null;
	
	private $sp_at_idpsessions = array();
	
	// Session duration parameters
	private $sessionstarted = null;
	private $sessionduration = null;

	// private constructor restricts instantiaton to getInstance()
	private function __construct($protocol, SimpleSAML_XML_AuthnResponse $message = null, $authenticated = true) {

		$this->configuration = SimpleSAML_Configuration::getInstance();

		$this->protocol = $protocol;
		$this->authnresponse = $message;
		
		$this->authenticated = $authenticated;
		if ($authenticated) {
			$this->sessionstarted = time();
		}
		
		$this->sessionduration = $this->configuration->getValue('session.duration');
	}
	
	public function add_sp_session($entityid) {
		$this->sp_at_idpsessions[$entityid] = self::STATE_ONLINE;
	}
	
	public function get_next_sp_logout() {
		
		if (!$this->sp_at_idpsessions) return null;
		
		foreach ($this->sp_at_idpsessions AS $entityid => $sp) {
			if ($sp == self::STATE_ONLINE) {
				$this->sp_at_idpsessions[$entityid] = self::STATE_LOGOUTINPROGRESS;
				return $entityid;
			}
		}
		return null;
	}
	
	public function set_sp_logout_completed($entityid) {
		$this->sp_at_idpsessions[$entityid] = self::STATE_LOGGEDOUT;
	}
	
	
	public function dump_sp_sessions() {
		foreach ($this->sp_at_idpsessions AS $entityid => $sp) {
			error_log('Dump sp sessions: ' . $entityid . ' status: ' . $sp);
		}
	}
	
	public function getInstance() {
		if (isset(self::$instance)) {
			return self::$instance;
		} elseif(isset($_SESSION['SimpleSAMLphp_SESSION'])) {
			self::$instance = $_SESSION['SimpleSAMLphp_SESSION'];
			return self::$instance;
		}
		return null;
	}
	
	public static function init($protocol, $message = null, $authenticated = true) {
		
		$preinstance = self::getInstance();
		
		if (isset($preinstance)) {
			if (isset($message)) $preinstance->authnresponse = $message;
			if (isset($authenticated)) $preinstance->setAuthenticated($authenticated);
		} else {	
			self::$instance = new SimpleSAML_Session($protocol, $message, $authenticated);
			$_SESSION['SimpleSAMLphp_SESSION'] = self::$instance;
		}
	}

	public function setAuthnRequest($requestid, SimpleSAML_XML_SAML20_AuthnRequest $xml) {	
		$this->authnrequests[$requestid] = $xml;
	}
	
	public function getAuthnRequest($requestid) {
		return $this->authnrequests[$requestid];
	}
	
	public function setAuthnResponse(SimpleSAML_XML_AuthnResponse $xml) {
		$this->authnresponse = $xml;
	}
	
	public function getAuthnResposne() {
		return $this->authnresponse;
	}
	
	public function setLogoutRequest(SimpleSAML_XML_SAML20_LogoutRequest $lr) {
		$this->logoutrequest = $lr;
	}
	
	public function getLogoutRequest() {
		return $this->logoutrequest;
	}

	public function setSessionIndex($sessionindex) {
		$this->sessionindex = $sessionindex;
	}
	public function getSessionIndex() {
		return $this->sessionindex;
	}
	public function setNameID($nameid) {
		$this->nameid = $nameid;
	}
	public function getNameID() {
		return $this->nameid;
	}
	public function setNameIDformat($nameidformat) {
		$this->nameidformat = $nameidformat;
	}
	public function getNameIDformat() {
		return $this->nameidformat;
	}

	public function setAuthenticated($auth) {
		$this->authenticated = $auth;
		if ($auth) {
			$this->sessionstarted = time();
		}
	}
	
	public function setSessionDuration($duration) {
		$this->sessionduration = $duration;
	}
	
	
	/*
	 * Is the session representing an authenticated user, and is the session still alive.
	 * This function will return false after the user has timed out.
	 */

	public function isValid() {
		if (!$this->isAuthenticated()) return false;
		return $this->remainingTime() > 0;
	}
	
	/*
	 * If the user is authenticated, how much time is left of the session.
	 */
	public function remainingTime() {
		return $this->sessionduration - (time() - $this->sessionstarted);
	}

	/* 
	 * Is the user authenticated. This function does not check the session duration.
	 */
	public function isAuthenticated() {
		return $this->authenticated;
	}
	
	
	
	
	public function getProtocol() {
		return $this->protocol;
	}
	
	public function getAttributes() {
		return $this->attributes;
	}

	public function getAttribute($name) {
		return $this->attributes[$name];
	}

	public function setAttributes($attributes) {
		$this->attributes = $attributes;
	}
	
}

?>