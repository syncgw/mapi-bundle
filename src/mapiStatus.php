<?php
declare(strict_types=1);

/*
 * 	MAPI over HTTP status definitions
 *
 *	@package	sync*gw
 *	@subpackage	MAPI over HTTP support
 *	@copyright	(c) 2008 - 2026 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\mapi;

class mapiStatus {

	// [MS-OXCMAPIHTTP] 2.2.3.3.3 X-ResponseCode Header fld
	const OK 			= '0';
	const ERR			= '1';
	const VERB			= '2';
	const PATH			= '3';
	const HEAD			= '4';
	const REQ			= '5';
	const SESS			= '6';
	const MISS			= '7';
	const ANONYM		= '8';
	const SIZE			= '9';
	const CONTEXT		= '10';
	const PRIV			= '11';
	const BODY			= '12';
	const COOKIE		= '13';
	const IGNORE		= '14';
	const SEQ			= '15';
	const ENDPOINT		= '16';
	const RESP			= '17';
	const DOWN			= '18';
	// [MS-OXCRPC] 3.1.4.2 EcDoRpcExt2 Method (Opnum 11)
	const LENGTH		= '1206';

	const STAT			= [
		self::OK 		=> 'The request was properly formatted and accepted',
		self::ERR		=> 'The request produced an unknown failure',
		self::VERB		=> 'The request has an invalid verb',
		self::PATH		=> 'The request has an invalid path',
		self::HEAD		=> 'The request has an invalid header',
		self::REQ		=> 'The request has an invalid X-RequestType header',
		self::SESS		=> 'The request has an invalid session context cookie',
		self::MISS		=> 'The request has a missing required header',
		self::ANONYM	=> 'The request is anonymous, but anonymous requests are not accepted',
		self::SIZE		=> 'The request is too large',
		self::CONTEXT	=> 'The Session Context is not found',
		self::PRIV		=> 'The client has no privileges to the Session Context',
		self::BODY		=> 'The request body is invalid',
		self::COOKIE	=> 'The request is missing a required cookie',
		self::IGNORE	=> 'This value MUST be ignored by the client',
		self::SEQ		=> 'The request has violated the sequencing requirement of one request at a Time per Session Context',
		self::ENDPOINT	=> 'The endpoint is disabled',
		self::RESP		=> 'The response is invalid',
		self::DOWN		=> 'The endpoint is shutting down',
		self::LENGTH	=> 'The format of the request was found to be invalid',
	];

	/**
	 * 	Get status message
	 *
	 * 	@param	- Status
	 * 	@return	- Description
	 */
	static public function status(string $stat): string {

		return isset(self::MSG[$stat]) ? self::MSG[$stat] : '+++ Status "'.sprintf('%d',$stat).'" not found';
	}

}
