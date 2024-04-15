<?php
declare(strict_types=1);

/*
 * 	<DNToMId> handler class
 *
 *	@package	sync*gw
 *	@subpackage	MAPI over HTTP support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\mapi;

use syncgw\lib\HTTP;
use syncgw\lib\XML;

class mapiDNToMId extends mapiWBXML {

    /**
     * 	Singleton instance of object
     * 	@var mapiDNToMId
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mapiDNToMId {

		if (!self::$_obj) {

            self::$_obj = new self();
			parent::getInstance();
		}

		return self::$_obj;
	}

 	/**
	 * 	Parse <DNToMId> request / response
	 *
	 *	@param	- mapiHTTP::REQ = Decode request; mapiHTTP::RESP = Decode response; mapiHTTP::MKRESP = Create response
	 * 	@return	- new XML structure or null
	 */
	public function Parse(int $mod): ?XML {

		// [MS-OXCMAPIHTTP] 2.2.5.4.1 DnToMinId Request Type Request Body
		// [MS-OXCMAPIHTTP] 2.2.5.4.2 DnToMinId Request Type Success Response Body
		// [MS-OXCMAPIHTTP] 2.2.5.4.3 DnToMinId Request Type Failure Response Body
		// [MS-OXNSPI] 		2.2.1.2 Permitted Error Code Values
		// [MS-OXCDATA] 	2.4 Error Codes
		// [MS-OXNSPI] 		2.2.9.1 MinimalEntryID
		// [MS-OXCRPC] 		3.1.4.1.1 <AuxiliaryBuffer> Extended Buffer Handling
		// [MS-OXCRPC] 		3.1.4.1.1.1.1 rgbAuxIn Input Buffer

		// load skeleton
		if (!($xml = parent::_loadSkel('DNToMId', 'DNToMId', $mod)))
			return null;

		if ($mod == mapiHTTP::REQ || $mod == mapiHTTP::RESP)
			parent::Decode($xml, 'DNToMId');
		else {

			$req = HTTP::getInstance()->getHTTPVar(HTTP::RCV_BODY);

			$p = $xml->savePos();
			$xml->updVar('MinimalIdCount', strval($req->xpath('//Name')));
			while ($name = $req->getItem()) {

				$xml->restorePos($p);
				if (stripos($name, '/CN=RECIPIENT') !== false || stripos($name, '/CN=CONFIGURATION') !== false) {

					$xml->updVar('MinimalEntryID', 'Handle01', false);
					continue;
				}
				$xml->restorePos($p);
				if (stripos($name, '/o=') !== false)
					$xml->updVar('MinimalEntryID', 'GlobalAddressList', false);
			}
			$xml->setTop();
		}

		return $xml;
	}

}
