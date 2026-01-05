<?php
declare(strict_types=1);

/*
 * 	<GetProps> handler class
 *
 *	@package	sync*gw
 *	@subpackage	MAPI over HTTP support
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\mapi;

use syncgw\lib\Attachment;
use syncgw\lib\Config;
use syncgw\lib\HTTP;
use syncgw\lib\User;
use syncgw\lib\XML;

class mapiGetProps extends mapiWBXML {

    /**
     * 	Singleton instance of object
     * 	@var mapiGetProps
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mapiGetProps {

		if (!self::$_obj) {

            self::$_obj = new self();
			parent::getInstance();
		}

		return self::$_obj;
	}

 	/**
	 * 	Parse <GetProps> request / response
	 *
	 *	@param	- mapiHTTP::REQ = Decode request; mapiHTTP::RESP = Decode response; mapiHTTP::MKRESP = Create response
	 * 	@return	- new XML structure or null
	 */
	public function Parse(int $mod): ?XML {

		// [MS-OXCMAPIHTTP] 2.2.5.7.1 GetProps Request Type Request Body
		// [MS-OXCMAPIHTTP] 2.2.5.7.2 GetProps Request Type Success Response Body
		// [MS-OXCMAPIHTTP] 2.2.5.7.3 GetProps Request Type Failure Response Body
		// [MS-OXNSPI] 		2.2.8 <State>
		// [MS-OXCMAPIHTTP] 2.2.1.8 <LargePropertyTagArray> Structure
		// [MS-OXCDATA] 	2.9 PropertyTag Structure
		// [MS-OXCDATA] 	2.11.1 Property Data Types (mapiHTTP::DATA_TYP)
		// [MS-OXNSPI] 		2.2.1.2 Permitted Error Code Values
		// [MS-OXCDATA] 	2.4 Error Codes
		// [MS-OXCMAPIHTTP] 2.2.5.7.3 GetProps Request Type Failure Response Body
		// [MS-OXCMAPIHTTP] 2.2.1.3 <AddressBookPropertyValueList> Structure
		// [MS-OXCMAPIHTTP] 2.2.1.2 <AddressBookTaggedPropertyValue> Structure
		// [MS-OXCMAPIHTTP] 2.2.1.1 <AddressBookPropertyValue> Structure
		// [MS-OXCRPC] 		3.1.4.1.1 <AuxiliaryBuffer> Extended Buffer Handling
		// [MS-OXCRPC] 		3.1.4.1.1.1.1 rgbAuxIn Input Buffer

		// load skeleton
		if (!($xml = parent::_loadSkel('GetProps', 'GetProps', $mod)))
			return null;

		if ($mod == mapiHTTP::REQ || $mod == mapiHTTP::RESP)
			parent::Decode($xml, 'GetProps');

		if ($mod == mapiHTTP::MKRESP) {

			$http = HTTP::getInstance();
			$req = $http->getHTTPVar(HTTP::RCV_BODY);

			$ou = '/O=I638513D0/OU=EXCHANGE ADMINISTRATIVE GROUP (FYDIBOHF23SPDLT)/CN=';

			$req->xpath('//PropertyId');
			while ($pid = $req->getItem()) {
				switch ($pid) {
				case 'AddressBookObjectGuid':
					//	$xml->updVar('CodePage', 'ISO-8859-1');
					$xml->updVar('PropertyType', 'H');
					$xml->updVar('PropertyID', 'AddressBookObjectGuid');
					$xml->updVar('Count', '16');
					$xml->updVar('GUID', 'AddresssBook');
					break;

				case 'AddressBookHomeMessageDatabase':
					$http = HTTP::getInstance();
					$xml->updVar('CodePage', 'UTF-16LE');
					$xml->updVar('PropertyType', 'S');
					$xml->updVar('PropertyID', 'AddressBookHomeMessageDatabase');
					$xml->xpath('//Property[@C="PropertyType=DATA$PropertyID!AddressBookObjectGuid"]');
					$xml->getItem();
					$val = 'CONFIGURATION/CN=SERVERS/CN='.mapiDefs::GUID['MessageDB'].'@'.$http->getHTTPVar('Host').
						   '/CN=MICROSOFT PRIVATE MDB';
					$xml->updVar('Value', strtoupper($ou.$val), false);
					break;

				case 'AddressBookNetworkAddress':
					$xml->updVar('CodePage', 'UTF-16LE');
					$xml->updVar('PropertyType', 'Error');
					$xml->updVar('PropertyID', 'AddressBookNetworkAddress');
					$xml->getVar('AddressBookTaggedPropertyValue');
					$xml->updVar('ErrCode', 'NotFound', false);
					break;

				case 'EmailAddress':
					$usr = User::getInstance();
					$xml->updVar('CodePage', 'UTF-16LE');
					$xml->updVar('PropertyType', 'S');
					$xml->updVar('PropertyID', 'EmailAddress');
					$xml->xpath('//Property[@C="PropertyType=DATA$PropertyID!AddressBookObjectGuid"]');
					$xml->getItem();
					$val = $usr->getVar('AccountName');
					if ($this->_cnf->getVar(Config::DBG_SCRIPT))
						$val = '010000xxxx000000-Debug';
					$xml->updVar('Value', strtoupper($ou.'RECIPIENTS/CN='.$val), false);
					break;

				case 'AddressBookProxyAddresses':
					$usr = User::getInstance();
					$xml->updVar('CodePage', 'UTF-16LE');
					$xml->updVar('PropertyType', 'M_S');
					$xml->updVar('PropertyID', 'AddressBookProxyAddresses');
					$xml->xpath('//Property[@C="PropertyType=DATA$PropertyID!AddressBookObjectGuid"]');
					$xml->getItem();
					$p = $xml->savePos();
					$xml->updVar('Last', 'true', false);
					$xml->restorePos($p);
					$xml->updVar('Count', '1', false);
					// [MS-OXOABK]		2.2.3.23 PidTagAddressBookProxyAddresses
					$val = $usr->getVar('EMailPrime');
					if (!$val && Config::getInstance()->getVar(Config::DBG_SCRIPT))
						$val = 'dummy@xxx.com';
					$xml->restorePos($p);
					$xml->updVar('Value', 'SMTP:'.$val, false);
					break;

				case 'ThumbnailPhoto':
					$usr = User::getInstance();
					$att = Attachment::getInstance();
					$xml->updVar('CodePage', 'UTF-16LE');
					$xml->updVar('PropertyType', 'H');
					$xml->updVar('PropertyID', 'ThumbnailPhoto');
					$xml->xpath('//Property[@C="PropertyType=DATA$PropertyID!AddressBookObjectGuid"]');
					$xml->getItem();
					if (!($pic = $usr->getVar('Photo')))
						// load default picture
						$pic = 'sgw-9b042e73';
					if (!($pic = $att->read($pic))) {
						$xml->updVar('HasValue', 'false', false);
					} else {
						$p = $xml->savePos();
						$xml->updVar('Count', strval(strlen($pic)), false);
						$xml->restorePos($p);
						$xml->updVar('Value', bin2hex($pic), false);
					}
					break;

				default:
					$this->_msg->WarnMsg('Unknown <PropertyId>'.$pid.'</PropertyId>');
					break;
				}
			}

			$xml->setTop();
		}

		return $xml;
	}

}
