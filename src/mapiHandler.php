<?php
declare(strict_types=1);

/*
 * 	MAPI over HTTP handler class
 *
 *	@package	sync*gw
 *	@subpackage	MAPI over HTTP support
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\mapi;

use syncgw\lib\msg;
use syncgw\lib\HTTP;
use syncgw\lib\User;
use syncgw\lib\Util;
use syncgw\lib\XML;
use syncgw\lib\Encoding;
use syncgw\lib\Server;

class mapiHandler extends mapiWBXML {

    /**
     * 	Singleton instance of object
     * 	@var mapiHandler
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mapiHandler {

	   	if (!self::$_obj)
            self::$_obj = new self();

		return self::$_obj;
	}

    /**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
     *	@param 	- true = Provide status information only (if available)
	 */
	public function getInfo(XML &$xml, bool $status): void {

		$xml->addVar('Name', 'MAPI over HTTP handler');

		$xml->addVar('Opt', '<a href="https://learn.microsoft.Object/en-us/openspecs/exchange_server_protocols/ms-oxnpsi" target="_blank">[MS-OXNSPI]</a> '.
				      'Exchange Server Name Service Provider Interface (NSPI) Protocol v13.1');
		$xml->addVar('Stat', 'Implemented');

		$xml->addVar('Opt', '<a href="https://learn.microsoft.Object/en-us/openspecs/exchange_server_protocols/ms-oxcprpt" target="_blank">[MS-OXCPRPT]</a> '.
				      'Property and Stream Object Protocol v17.0');
		$xml->addVar('Stat', 'Implemented');

		$xml->addVar('Opt', '<a href="https://learn.microsoft.Object/en-us/openspecs/exchange_server_protocols/ms-oxcperm" target="_blank">[MS-OXCPERM]</a> '.
				      'Exchange Access and Operation Permissions Protocol v15.0');
		$xml->addVar('Stat', 'Implemented');

		$xml->addVar('Opt', '<a href="https://learn.microsoft.Object/en-us/openspecs/exchange_server_protocols/ms-oxoabk" target="_blank">[MS-OXOABK]</a> '.
				      'Address Book Object Protocol v18.0');
		$xml->addVar('Stat', 'Implemented');

		$srv = Server::getInstance();
		$srv->getSupInfo($xml, $status, 'mapi', [ 'mapiDefs', 'mapiFlags', 'mapiStatus' ]);
	}

	/**
	 * 	Process client request
	 */
	public function Process(): void {

		$http = HTTP::getInstance();
		$usr  = User::getInstance();

		// we assume everything is ok
		$rc  = mapiStatus::OK;
		$out = null;

		// get & set cookie
		foreach ($_COOKIE as $k => $v) {

			if ($k == 'sid') {

				if (strpos($v, ':') !== false)
					list($guid, $cnt) = explode(':', $v);
				else {

					$guid = $v;
					$cnt  = 0;
				}
				break;
			}
		}
		if (!isset($guid)) {
			$guid = Util::WinGUID();
			$cnt = 0;
		}
		$http->addHeader('Set-Cookie', 'sid='.$guid.':'.++$cnt);

		// check authorization
		if (!$usr->Login($http->getHTTPVar('User'), $http->getHTTPVar('Password'))) {

			if ($usr->getVar('Banned'))
				$http->send(456);
			else {

				$http->addHeader('WWW-Authenticate', 'Basic realm=mapiHandler');
				$http->send(401);
			}
			return;
		}

		// swap request type to send header
		$http->addHeader('X-Requesttype', $cmd = $http->getHTTPVar('X-Requesttype'));

		if ($cmd == 'Notificationwait')
			$cmd = 'NotificationWait';

		// [MS-OXCMAPIHTTP] 2.2.3.3.1 X-RequestType Header fld
		switch ($cmd) {
		// Indicates the PING request type for the mailbox server endpoint and address book server endpoint
		// [MS-OXCMAPIHTTP] 3.2.5.3 Responding to a PING Request Type
		case 'PING':
			break;

		// Indicates the Connect request type for the mailbox server endpoint
		case 'Connect':
			$xml = $http->getHTTPVar(HTTP::RCV_BODY);
			self::$_cs = $xml->getVar('DefaultCodePage');
			$out = self::Parse($cmd, mapiHTTP::MKRESP);
			break;

		// Indicates the Bind request type for the address book server endpoint
		case 'Bind':
			$xml = $http->getHTTPVar(HTTP::RCV_BODY);
			$enc = Encoding::getInstance();
			$enc->setEncoding(self::$_cs = $xml->getVar('CodePage'));

		// Indicates the Unbind request type for the address book server endpoint
		case 'Unbind':
		// Indicates the ResolveNames request type for the address book server endpoint
		case 'ResolveNames':
		// Indicates the Disconnect request type for the mailbox server endpoint
		case 'Disconnect':
		// Indicates the GetSpecialTable request type for the address book server endpoint
		case 'GetSpecialTable':
		// Indicates the GetMatches request type for the address book server endpoint
		case 'GetMatches':
		// Indicates the QueryRows request type for the address book server endpoint
		case 'QueryRows':
		// Indicates the DNToMinId request type for the address book server endpoint.
		case 'DNToMId':
		// Indicates the Execute request type for the mailbox server endpoint
		case 'Execute':
		// Indicates the GetProps request type for the address book server endpoint
		case 'GetProps':
		// Indicates the NotificationWait request type for the mailbox server endpoint
		case 'NotificationWait':
			$out = self::Parse($cmd, mapiHTTP::MKRESP);
			break;

		// Indicates the CompareMinIds request type for the address book server endpoint
		case 'CompareMIds':
		// Indicates the GetPropList request type for the address book server endpoint
		case 'GetPropList':
		// Indicates the GetTemplateInfo request type for the address book server endpoint
		case 'GetTemplateInfo':
		// Indicates the ModLinkAtt request type for the address book server endpoint
		case 'ModLinkAtt':
		// Indicates the ModProps request type for the address book server endpoint
		case 'ModProps':
		// Indicates the QueryColumns request type for the address book server endpoint4
		case 'QueryColumns':
		// Indicates the ResortRestrictionion request type for the address book server endpoint
		case 'ResortRestriction':
		// Indicates the SeekEntries request type for the address book server endpoint
		case 'SeekEntries':
		// Indicates the UpdateStat request type for the address book server endpoint
		case 'UpdateStat':
		// Indicates the GetMailboxUrl request type for the specified mailbox server endpoint
		case 'GetMailboxUrl':
		// Indicates the GetAddressBookUrl request type for the address book server endpoint
		case 'GetAddressBookUrl':
			Msg::WarnMsg('Unknown command ['.$cmd.'] - skipping');
			break;

		default:
			$rc = mapiStatus::REQ;
			break;
		}

		if ($cmd != 'PING' && $out)
			$http->addBody($out);

		// [MS-OXCMAPIHTTP] 2.2.3.3.2 X-RequestId Header fld
		// The X-RequestId header field MUST be a Objectbination of a globally unique value in the format
		// of a GUID followed by an increasing decimal counter which MUST increase with every new HTTP
		// request (for example, "{E2EA6C1C-E61B-49E9-9CFB-38184F907552}:123456"). The GUID portion of
		// the X-RequestId header MUST be unique across all Session Contexts and MUST NOT change for
		// the life of the Session Context. The client MUST send this header on every request and the
		// server MUST return this header with the same information in the response back to the client
		$http->addHeader('X-RequestId', $guid.':'.$cnt);

		// [MS-OXCMAPIHTTP] 2.2.3.3.4 X-ClientInfo Header fld
		// The X-ClientInfo header field MUST be a Objectbination of a globally unique value in the format
		// of a GUID followed by a decimal counter (for example, "{2EF33C39-49C8-421C-B876-CDF7F2AC3AA0}:123").
		// The GUID portion of the X-ClientInfo header MUST be unique across all client instances and MUST
		// be the same for all requests from a client instance. The client MUST use a different decimal
		// counter when establishing a new Session Context with the server endpoint. The client can use
		// the same decimal counter when re-establishing a Session Context with the server endpoint.
		// The client MUST send this header on every request and the server MUST return this header with
		// the same information in the response back to the client
		if ($val = $http->getHTTPVar('X-Clientinfo'))
			$http->addHeader('X-ClientInfo', $val);

		// [MS-OXCMAPIHTTP] 2.2.3.3.5 X-PendingPeriod Header fld
		// The X-PendingPeriod header field, returned by the server, specifies the number of milliseconds
		// to be expected between keep-alive PENDING meta-tags in the response stream while the server is
		// executing the request. The default value of this header is 30000 milliseconds (30 seconds)
		$http->addHeader('X-PendingPeriod', '30000');

		// [MS-OXCMAPIHTTP] 2.2.3.3.6 X-ClientApplication Header fld
		// On every request, the client includes the X-ClientApplication header to indicate to the server
		// what version of the client is being used. The value of this header field has the following
		// format: "Outlook/15.xx.xxxx.xxxx".

		// [MS-OXCMAPIHTTP] 2.2.3.3.7 X-ServerApplication Header fld
		// On every response, the server includes the X-ServerApplication header to indicate to the client
		// what server version is being used. The value of this header field has the following format:
		// "Exchange/15.xx.xxxx.xxx".
		$http->addHeader('X-ServerApplication', 'Exchange/15.00.0847.4040'); ##.$cnf->getVar(Config::VERSION));

		// [MS-OXCMAPIHTTP] 2.2.3.3.8 X-ExpirationInfo Header fld
		// The X-ExpirationInfo header is returned by the server in every response to notify the client of
		// the number of milliseconds before the server Times-out the Session Context
		$http->addHeader('X-ExpirationInfo', '900000');

		// [MS-OXCMAPIHTTP] 2.2.3.3.11 X-DeviceInfo Header fld
		// The X-DeviceInfo header specifies information used by devices positioned between the client and
		// server endpoints and is to be considered opaque to the client and server. This field is intended
		// for use by intermediate devices and SHOULD be discarded by the client or server endpoint upon
		// receipt. The client endpoint MUST NOT send this header in a request to a server endpoint. The
		// server MUST NOT send this header in a response to a client endpoint.

		// MS-OXCMAPIHTTP] 2.2.3.3.3 X-ResponseCode Header fld
		$http->addHeader('X-ResponseCode', $rc);

		// [MS-OXCMAPIHTTP] 2.2.2.2 Common Response Format
		if ($rc != mapiStatus::OK)
			$http->addHeader('Content-Type', 'text/html');
		else
			$http->addHeader('Content-Type', 'application/mapi-http');

		// send data
		$http->send(200);
	}

	/**
	 * 	Call Command
	 *
	 *  @param  - Command name
	 *	@param	- mapiHTTP::REQ = Decode request; mapiHTTP::RESP = Decode response; mapiHTTP::MKRESP = Create response
	 * 	@return	- new XML structure or null
	 */
	public function Parse(string $cmd, int $mod): ?XML {

		$class = 'syncgw\\mapi\\mapi'.$cmd;
		$class = $class::getInstance();

		return $class->Parse($mod);
	}

}
