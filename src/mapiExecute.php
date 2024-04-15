<?php
declare(strict_types=1);

/*
 * 	<Execute> handler class
 *
 *	@package	sync*gw
 *	@subpackage	MAPI over HTTP support
 *	@copyright	(c) 2008 - 2024 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\mapi;

use syncgw\lib\XML;
use syncgw\rops\ropHandler;

class mapiExecute extends mapiWBXML {

	/**
     * 	Singleton instance of object
     * 	@var mapiExecute
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mapiExecute {

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

		$xml->addVar('Opt', 'LZ77 compression and decompression');
		$xml->addVar('Stat', 'v1.0');

		$xml->addVar('Opt', 'DIRECT2 Encoding Algorithm');
		$xml->addVar('Stat', 'v1.0');

		$xml->addVar('Opt', '<a href="https://winprotocoldoc.blob.core.windows.net/productionwindowsarchives/MS-XCA/%5bMS-XCA%5d.pdf" target="_blank">[MS-XCA]</a> '.
				      'Xpress Compression Algorithm');
		$xml->addVar('Stat', 'v9.0');

	}

	/**
	 * 	Parse <Execute> request / response
	 *
	 *	@param	- mapiHTTP::REQ = Decode request; mapiHTTP::RESP = Decode response; mapiHTTP::MKRESP = Create response
	 * 	@return	- new XML structure or null
	 */
	public function Parse(int $mod): ?XML {

		// [MS-OXCMAPIHTTP] 2.2.4.2.1 Execute Request Type Request Body
		// [MS-OXCMAPIHTTP] 2.2.4.2.2 Execute Request Type Success Response Body
		// [MS-OXCMAPIHTTP] 2.2.4.2.3 Execute Request Type Failure Response Body
		// [MS-OXCRPC] 		3.1.4.2 EcDoRpcExt2 Method (Opnum 11)

		// load skeleton
		if (!($xml = parent::_loadSkel('Execute', 'Execute', $mod)))
			return null;

		$rops = ropHandler::getInstance();

		if ($mod == mapiHTTP::REQ || $mod == mapiHTTP::RESP) {

			// decode firt part
			$xml->setTop();
			parent::Decode($xml, 'Execute');

			// [MS-OXCRPC] 3.1.4.1.1.2 Compression Algorithm
			$xml->xpath('//RPC_HEADER_EXT');
			$xml->getItem();
			$p = $xml->savePos();
			if (strpos($xml->getVar('Flags', false), 'Compressed') !== false) {

				$xml->restorePos($p);

				// save current buffer and position
				$pos  = self::$_pos;
				$head = substr(self::$_wrk, 0, self::$_pos);
				$wrk  = substr(self::$_wrk, self::$_pos, $n = intval($xml->getVar('Size', false)));
				$tail = substr(self::$_wrk, self::$_pos + $n);

				// [MS-OXCRPC] 3.1.4.1.1.2.2 DIRECT2 Encoding Algorithm
				// The basic notion of the DIRECT2 encoding algorithm is that data appears unchanged
				// in the compressed representation
				// [MS-OXCRPC] 3.1.4.1.1.2.1 LZ77 Compression Algorithm
				$wrk = self::_dt2Decode($wrk);
				$this->_msg->InfoMsg($head, 'Heading data ('.strlen($head).')', 0, 1024);
				$this->_msg->InfoMsg($wrk, 'Uncompressed data ('.strlen($wrk).')', 0, 1024);
				$this->_msg->InfoMsg($tail, 'Successive data ('.strlen($tail).')', 0, 1024);

				// restore
				self::$_pos = $pos;
				self::$_wrk = $head.$wrk.$tail;
			}

			// now we can set <RopSize>
			$xml->updVar('RopSize', self::_getInt(2));

			// process Rops
			$xml->getVar('RopsList');
			if (!$rops->Parse($xml, $mod))
				return null;

			// process remaining data
			$xml->getVar('Trailer');
			parent::Decode($xml, 'Trailer');
		} else {

			$xml->getVar('RopsList');

			if (!$rops->Parse($xml, $mod))
				return null;

			// compute size of produced data
			$xml->setTop();
			$buf = self::Encode($xml, 'RopsList');
			$len = strlen($buf);
			$xml->getVar('RopBuffer');
			$xml->updVar('RopSize', strval($len), false);

			$xml->setTop();
			$buf  = self::Encode($xml, 'ServerObjectHandleTable');
			$len += strlen($buf);
			$xml->getVar('RPC_HEADER_EXT');
			$xml->updVar('Size', strval($len), false);
			$xml->getVar('RPC_HEADER_EXT');
			$xml->updVar('ActualSize', strval($len), false);

			// add to total length size of RPC_HEADER_EXT
			$xml->updVar('RopBufferSize', strval($len + 8));
		}
		$xml->setTop();

		return $xml;
	}

	/**
	 * 	DIRECT2 Encoding Algorithm
	 *
	 * 	@param 	- Encoded input buffer
	 * 	@return - Decoded input buffer
	 */
	private function _dt2Decode(string $in): string {

		// set new input buffer
		$ip  = 0;
		$sip = 0;
		$end = strlen($in);
		// test bit mask
		$tst = 0;
		// bit test
		$bit = 0;
		// shared byte
		$shr = 0;

		// output buffer
		$out = '';
		$op  = 0;
		$sop = 0;

		// [MS-OXCRPC] 3.1.4.1.1.2.2 DIRECT2 Encoding Algorithm
		// The basic notion of the DIRECT2 encoding algorithm is that data appears unchanged
		// in the compressed representation
		// https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-wusp/2f6ddb6a-9026-43a3-b1d9-d8a19af3f03f

		$this->_msg->InfoMsg($in, 'DIRECT2 encoded buffer ('.$end.') first 1024 bytes', 0, 1024);

		while ($ip < $end) {

			// [MS-OXCRPC] 3.1.4.1.1.2.2.1 Bitmask
			if (!$bit) {
				$tst = unpack('V', substr($in, $ip, 4))[1];
				$ip += 4;
				$this->_msg->InfoMsg('Bits ['.sprintf('%032b', $tst).'] In:'.self::$_pos.' Out:0 Len:4');
				// set test mask
				$bit = 32;
			}
			// check whether the bit specified by IndicatorBit is set or not
            // set in Indicator. For example, if IndicatorBit has value 4
            // check whether the 4th bit of the value in Indicator is set
			if ((($tst >> --$bit) & 1) == 0) {

				// data is no metadata
				$out[$op++] = $in[$ip++];
			} else {

				// show regulary swapped data
				if ($sop < $op) {

					$this->_msg->InfoMsg('Swap ['.bin2hex(substr($out, $sop)).'] In:'.$sip.' Out:'.$sop.' Len:'.($op - $sop));
					$sip = $ip;
					$sop = $op;
				}

				// [MS-OXCRPC] 3.1.4.1.1.2.2.3 Metadata Offset
				// save start of metadata
				$len = unpack('v', substr($in, $ip, 2))[1];
				$ip += 2;
				$off = intval($len / 8) * -1 - 1;

				// [MS-OXCRPC] 3.1.4.1.1.2.2.4 Match Length
				$len = $len % 8;
				$dbg = '';
				if ($len == 7) { // b'111'

					if (!$shr){

						$shr = unpack('C', substr($in, $ip++, 1))[1];
						$dbg = 'Shared byte ['.sprintf('%08b', $shr).']';
						$len = $shr % 16;
					} else {

						$len = intval($shr / 16);
						$dbg = 'Adding global LEN ['.$len.']';
						$shr = 0;
					}
					if ($len == 15) { // b'1111'

						// additionalbyte
						$len = unpack('C', substr($in, $ip++, 1))[1];
						if ($len == 255) {

							$len = unpack('v', substr($in, $ip, 2))[1];
							// A "full" (all b'1') bit pattern (b'111', b'1111', and b'11111111')
							// means that there is more length in the following 2 bytes
							$ip  += 2;
							$len -= (15 + 7);
							$dbg = 'Next 2 byte [0x'.sprintf('%04X', $len).']';
						}
						else
							$dbg = 'Next byte ['.sprintf('%08b', $len).']';
						$len += 15;
					}
					$len += 7;
				}
				// add minimum match is 3 bytes
				$len += 3;

				$this->_msg->InfoMsg('Meta ['.sprintf('%016b', $len).'] Offset:'.$off.' '.$dbg);
				$sip  = $ip;

				// swap data to output buffer
				while ($len--) {

					$out[$op] = $out[$op + $off];
					$op++;
				}
				if ($sop < $op) {

					$this->_msg->InfoMsg('Copy ['.bin2hex(substr($out, $sop)).'] In:'.$sip.' Out:'.$sop.' Len:'.($op - $sop));
					$sop = $op;
				}
			}
		}

		return $out;
	}

}
