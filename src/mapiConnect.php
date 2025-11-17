<?php
declare(strict_types=1);

/*
 * 	<Connect> handler class
 *
 *	@package	sync*gw
 *	@subpackage	MAPI over HTTP support
 *	@copyright	(c) 2008 - 2025 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\mapi;

use syncgw\lib\Config;
use syncgw\lib\User;
use syncgw\lib\XML;

class mapiConnect extends mapiWBXML {

    /**
     * 	Singleton instance of object
     * 	@var mapiConnect
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mapiConnect {

		if (!self::$_obj) {

            self::$_obj = new self();
			parent::getInstance();
		}

		return self::$_obj;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
 	 */
	public function getInfo(XML &$xml): void {

		$xml->addVar('Opt', '<a href="https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-lcid" target="_blank">[MS-LCID]</a> '.
				      'Windows Language Code Identifier (LCID) Reference');
		$xml->addVar('Stat', 'v15.0');

	}

	/**
	 * 	Parse <Connect> request / response
	 *
	 *	@param	- mapiHTTP::REQ = Decode request; mapiHTTP::RESP = Decode response; mapiHTTP::MKRESP = Create response
	 * 	@return	- new XML structure or null
	 */
	public function Parse(int $mod): ?XML {

		// [MS-OXCMAPIHTTP] 2.2.4.1.1 Connect Request Type Request Body
		// [MS-OXCMAPIHTTP]	2.2.4.1.2 Connect Request Type Success Response Body
		// [MS-OXCMAPIHTTP]	2.2.4.1.3 Connect Request Type Failure Response Body
		// [MS-OXCRPC] 		3.1.4.1 EcDoConnectEx Method (Opnum 10)
		// [MS-OXNSPI] 		2.2.1.2 Permitted Error Code Values
		// [MS-OXCDATA] 	2.4 Error Codes
		// [MS-OXCRPC] 		2.2.2.1 RPC_HEADER_EXT Structure
		// [MS-OXCRPC] 		2.2.2.2 AUX_HEADER Structure
		// [MS-OXCRPC] 		2.2.2.2.17 AUX_EXORGINFO Auxiliary Block Structure
		// [MS-OXCRPC] 		2.2.2.2.15 AUX_CLIENT_CONTROL Auxiliary Block Structure
		// [MS-OXCRPC] 		2.2.2.2.20 AUX_ENDPOINT_CAPABILITIES Auxiliary Block Structure
		// [MS-OXCRPC] 		3.1.4.1.1 <AuxiliaryBuffer> Extended Buffer Handling
		// [MS-OXCRPC] 		3.1.4.1.1.1.1 rgbAuxIn Input Buffer

		// load skeleton
		if (!($xml = parent::_loadSkel('Connect', 'Connect', $mod)))
			return null;

		if ($mod == mapiHTTP::REQ || $mod == mapiHTTP::RESP)
			parent::Decode($xml, 'Connect');

		// specifies the display name of the user who is specified in the UserDn field of
        // the Connect request type request body
		else {
			$usr = User::getInstance();
        	if ($val = $usr->getVar('DisplayName'))
        		$val = $usr->getVar('EMailPrime');
        	if (Config::getInstance()->getVar(Config::DBG_SCRIPT))
        		$val = 'dummy@xxx.com';
			$xml->updVar('DisplayName', $val);

			$xml->setTop();
		}

		return $xml;
	}

}
