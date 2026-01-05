<?php
declare(strict_types=1);

/*
 * 	<GetMatches> handler class
 *
 *	@package	sync*gw
 *	@subpackage	MAPI over HTTP support
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\mapi;

use syncgw\lib\Config;
use syncgw\lib\User;
use syncgw\lib\XML;

class mapiGetMatches extends mapiWBXML {

    /**
     * 	Singleton instance of object
     * 	@var mapiGetMatches
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mapiGetMatches {

		if (!self::$_obj) {

            self::$_obj = new self();
			parent::getInstance();
		}

		return self::$_obj;
	}

 	/**
	 * 	Parse <GetMatches> request / response
	 *
	 *	@param	- mapiHTTP::REQ = Decode request; mapiHTTP::RESP = Decode response; mapiHTTP::MKRESP = Create response
	 * 	@return	- new XML structure or null
	 */
	public function Parse(int $mod): ?XML {

		// [MS-OXCMAPIHTTP] 2.2.5.5.1 GetMatches Request Type Request Body
		// [MS-OXCMAPIHTTP] 2.2.5.5.2 GetMatches Request Type Success Response Body
		// [MS-OXCMAPIHTTP] 2.2.5.5.3 GetMatches Request Type Failure Response Body
		// [MS-OXNSPI] 		2.2.8 <State>
		// [MS-OXNSPI] 		2.2.9.1 MinimalEntryID
		// [MS-OXCDATA] 	2.12 Restrictionions
		// [MS-OXCDATA] 	2.12.5 Property Restrictionion Structures
		// [MS-OXCMAPIHTTP] 2.2.1.8 <LargePropertyTagArray> Structure
		// [MS-OXCDATA] 	2.9 PropertyTag Structure
		// [MS-OXCPERM]		2.2.4 PidTagEntryId Property
		// [MS-OXNSPI] 		2.2.9.2 EphemeralEntryID
		// [MS-OXABK] 		2.2.3.12 PidTagDisplayTypeEx
		// [MS-OXCDATA] 	2.11.1 Property Data Types (mapiWBXML::DATA_TYP)
		// [MS-OXNSPI] 		2.2.1.2 Permitted Error Code Values
		// [MS-OXCDATA] 	2.4 Error Codes
		// [MS-OXCRPC] 		3.1.4.1.1 <AuxiliaryBuffer> Extended Buffer Handling
		// [MS-OXCRPC] 		3.1.4.1.1.1.1 rgbAuxIn Input Buffer

		// load skeleton
		if (!($xml = parent::_loadSkel('GetMatches', 'GetMatches', $mod)))
			return null;

		if ($mod == mapiHTTP::REQ || $mod == mapiHTTP::RESP)
			parent::Decode($xml, 'GetMatches');
		else {
			$usr = User::getInstance();

			$email = $usr->getVar('EMailPrime');
			if (Config::getInstance()->getVar(Config::DBG_SCRIPT))
				$email = 'dummy@xxx.com';
			list($val,) = explode('@', $email);
			$xml->xpath('//Value[text()="##username"]');
			$xml->getItem();
			$xml->setVal($val);

			if (!($val = $usr->getVar('SMTPLoginName')))
				$val = $email;

			$xml->xpath('//Value[text()="##smtp"]');
			$xml->getItem();
			$xml->setVal($val);
			$xml->getItem();
			$xml->setVal($val);

			$xml->xpath('//Value[text()="##dn"]');
			$xml->getItem();
			$val = $usr->getVar('AccountName');
			if (Config::getInstance()->getVar(Config::DBG_SCRIPT))
				$val = '010000xxxx000000-Debug';
			$val = '/O=I638513D0/OU=EXCHANGE ADMINISTRATIVE GROUP (FYDIBOHF23SPDLT)/CN=RECIPIENTS/CN='.$val;
			$xml->setVal(strtoupper($val), false);

			$xml->setTop();
		}

		return $xml;
	}

}
