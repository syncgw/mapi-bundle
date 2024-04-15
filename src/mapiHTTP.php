<?php
declare(strict_types=1);

/*
 * 	Process HTTP input / output
 *
 *	@package	sync*gw
 *	@subpackage	MAPI over HTTP support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\mapi;

use syncgw\lib\Config;
use syncgw\lib\HTTP;
use syncgw\lib\Msg;
use syncgw\lib\XML;

class mapiHTTP extends HTTP {

	// decode request body
	const REQ		= 1;
	// descode response body
	const RESP		= 2;
	// create response body
	const MKRESP	= 3;

    /**
     * 	Singleton instance of object
     * 	@var mapiHTTP
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mapiHTTP {

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

		$xml->addVar('Opt', '<a href="https://learn.microsoft.Object/en-us/openspecs/exchange_server_protocols/ms-oxcmapihttp" target="_blank">[MS-OXCMAPIHTTP]</a> '.
				      'Messaging Application Programming Interface (MAPI) Extensions for HTTP');
		$xml->addVar('Stat', 'v13.0');

		$xml->addVar('Opt', '<a href="https://tools.ietf.org/html/rfc2616" target="_blank">RFC2616</a> '.
				      'Hypertext Transfer Protocol -- HTTP');
		$xml->addVar('Stat', 'v1.1');

		$xml->addVar('Opt', '<a href="https://tools.ietf.org/html/rfc4122" target="_blank">RFC4122</a> '.
				      'A Universally Unique IDentifier (UUID) URN Namespace');
		$xml->addVar('Stat', 'v4.0');
	}

	/**
	 * 	Check HTTP input
	 *
	 * 	@return - HTTP status code
	 */
	public function checkIn(): int {

		// are we responsible?

		// NSPI (Name Service Provider Interface): Address Book Protocol
		// Mainly used by MAPI clients to perform username lookup

		// EMSMDB (Exchange Message Provide): Exchange transport
		// RFR: used to locate the NSPI server
		if (!isset(self::$_http[HTTP::SERVER]['REQUEST_METHOD']) ||
			self::$_http[HTTP::SERVER]['REQUEST_METHOD'] != 'POST' ||
			!isset(self::$_http[HTTP::SERVER]['REQUEST_URI']) ||
			stripos(self::$_http[HTTP::SERVER]['REQUEST_URI'], '/mapi/') === false)
			return 200;

		// save handler
		Config::getInstance()->updVar(Config::HANDLER, 'MAPI');

		// convert binary data to XML
		self::$_http[self::RCV_BODY] = self::_convIn();

		return 200;
	}

	/**
	 * 	Check HTTP output
	 *
	 * 	@return - HTTP status code
	 */
	public function checkOut(): int {

		$cnf = Config::getInstance();

		// output processing
		if ($cnf->getVar(Config::HANDLER) != 'MAPI')
			return 200;

		// do we need to convert back binary data?
		if (Config::getInstance()->getVar(Config::DBG_SCRIPT) == 'mapiDecode' &&
			!is_object(self::$_http[HTTP::SND_BODY]))
			self::$_http[HTTP::SND_BODY] = self::_convOut(self::$_http[HTTP::SND_BODY]);

		// do not use "chunked" transfer-encoding!
		if (is_object(self::$_http[HTTP::SND_BODY])) {

			$data =
			// [MS-OXCMAPIHTTP] 3.1.5.6 Handling a Chunked Response
			// [MS-OXCMAPIHTTP] 2.2.7 Response Meta-Tags
			// The server has queued the request to be processed
			"PROCESSING\r\n".
			"DONE\r\n".
			// [MS-OXCMAPIHTTP] 2.2.3.3.9 X-ElapsedTime Header fld
			// The X-ElapsedTime header specifies the amount of Time, in milliseconds, that the server took to
			// process the request. This header is returned by the server as an additional header in the final
			// response.
			"X-ElapsedTime: 0\r\n".
			// [MS-OXCMAPIHTTP] 2.2.3.3.10 X-StartTime Header fld
			// The X-StartTime header specifies the Time that the server started processing the request. This
			// header is returned by the server as an additional header in the final response. This header
			// follows the date/Time format, as specified in [RFC2616].
			"X-StartTime: ".gmdate(Config::RFC_TIME)."\r\n\r\n".
			self::_convOut(self::$_http[HTTP::SND_BODY]);
		} else
			$data = self::$_http[HTTP::SND_BODY];

		$n = $data ? strlen($data) : 0;

		if ($cnf->getVar(Config::DBG_LEVEL) != Config::DBG_OFF && $n) {

			$rec  = explode("\r\n", $data);
			$data = strlen($rec[5]) ? self::_convOut($rec[5]) : new XML();
			$data->getVar('MetaTags');
			for ($i=0; $i < 4; $i++)
				$data->addVar('MAPIString', $rec[$i]);
		}

		self::$_http[HTTP::SND_BODY] = $data;

		// send header
		if ($n) {

			// [MS-OXCMAPIHTTP] 3.2.5.2 Responding to All Request Type Requests
			self::addHeader('Content-Length',  strval($n));
			self::addHeader('Connection', 'keep-alive');
			self::addHeader('Cache-Control', 'private');
		}

		self::addHeader('Date', gmdate(Config::RFC_TIME));

		self::addBody($data);

		return 200;
	}

	/**
	 * 	Convert binary data to XML
	 *
	 * 	@return - XML document
	 */
	private function _convIn(): ?XML {

		$cmd = isset(self::$_http[self::RCV_HEAD]['X-Requesttype']) ?
					 self::$_http[self::RCV_HEAD]['X-Requesttype'] : 'Undef';
		if (!is_string(self::$_http[self::RCV_BODY]) || !strlen(self::$_http[self::RCV_BODY]))
			return null;

		Msg::InfoMsg(self::$_http[self::RCV_BODY], 'Binary <'.$cmd.'> data received ('.
					   ($n = strlen(self::$_http[self::RCV_BODY])).' bytes 0x'.sprintf('%X', $n).')'.
					   ' First 1024 bytes', 0, 1024);

		$mapi = mapiHandler::getInstance();
		$xml  = $mapi->Parse($cmd, self::REQ);

		if (!is_null($xml))
			$xml->setTop();

		return $xml;
	}

	/**
	 * 	Convert XML data to binary
	 *
	 * 	@param 	- XML document or binary data
	 * 	@return - Binary data or XML object
	 */
	private function _convOut($wrk) {

		$out = '';

		if (!isset(self::$_http[self::SND_HEAD]['X-Requesttype']))
			return $out;

		$cmd = self::$_http[self::SND_HEAD]['X-Requesttype'];

		if (is_object($wrk)) {

			$wbxml = mapiWBXML::getInstance();
			$wrk->setTop();
			$out = $wbxml->Encode($wrk, $cmd);
		} else {

			// set buffer
			self::$_http[self::SND_BODY] = $wrk;
			$mapi = mapiHandler::getInstance();
			$out = $mapi->Parse($cmd, self::RESP);
		}

		if (is_object($wrk)) {

			$wrk->setTop();
			Msg::InfoMsg($wrk, 'New <'.$cmd.'> created');
			Msg::InfoMsg($out, 'New binary <'.$cmd.
					   '> created ('.($n = strlen($out)).' bytes 0x'.sprintf('%X', $n).')'.
					   ' First 1024 bytes', 0, 1024);
		} else  {

			Msg::InfoMsg($out, 'Decoded <'.$cmd.'> response');
			Msg::InfoMsg($wrk, 'Binary <'.$cmd.
					   '> send ('.($n = strlen($wrk)).' bytes 0x'.sprintf('%X', $n).')'.
					   ' First 1024 bytes', 0, 1024);
		}

		return $out;
	}

}
