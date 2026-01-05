<?php
declare(strict_types=1);

/*
 * 	<Disconnect> handler class
 *
 *	@package	sync*gw
 *	@subpackage	MAPI over HTTP support
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\mapi;

use syncgw\lib\XML;

class mapiDisconnect extends mapiWBXML {

    /**
     * 	Singleton instance of object
     * 	@var mapiDisconnect
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mapiDisconnect {

		if (!self::$_obj) {

            self::$_obj = new self();
			parent::getInstance();
		}

		return self::$_obj;
	}

 	/**
	 * 	Parse <Disconnect> request / response
	 *
	 *	@param	- mapiHTTP::REQ = Decode request; mapiHTTP::RESP = Decode response; mapiHTTP::MKRESP = Create response
	 * 	@return	- new XML structure or null
	 */
	public function Parse(int $mod): ?XML {

		// [MS-OXCMAPIHTTP] 2.2.4.3.1 Disconnect Request Type Request Body
		// [MS-OXCMAPIHTTP] 2.2.4.3.2 Disconnect Request Type Success Response Body
		// [MS-OXCMAPIHTTP] 2.2.4.3.3 Disconnect Request Type Failure Response Body
		// [MS-OXNSPI] 		2.2.1.2 Permitted Error Code Values
		// [MS-OXCDATA] 	2.4 Error Codes
		// [MS-OXCRPC] 		3.1.4.1.1 <AuxiliaryBuffer> Extended Buffer Handling
		// [MS-OXCRPC] 		3.1.4.1.1.1.1 rgbAuxIn Input Buffer

		// load skeleton
		if (!($xml = parent::_loadSkel('Disconnect', 'Disconnect', $mod)))
			return null;

		if ($mod == mapiHTTP::REQ || $mod == mapiHTTP::RESP)
			parent::Decode($xml, 'Disconnect');

		return $xml;
	}

}
