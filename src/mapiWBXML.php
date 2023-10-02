<?php
declare(strict_types=1);

/*
 * 	Process Binary MAPI input / output
 *
 *	@package	sync*gw
 *	@subpackage	MAPI over HTTP support
 *	@copyright	(c) 2008 - 2023 Florian Daeumling, Germany. All right reserved
 * 	@license 	LGPL-3.0-or-later
 */

namespace syncgw\mapi;

use syncgw\lib\Config;
use syncgw\lib\Msg;
use syncgw\lib\Encoding;
use syncgw\lib\HTTP;
use syncgw\lib\Server;
use syncgw\lib\XML;
use syncgw\rpc\rpcHandler;

/**
 * Supported attributes
 *
 * Simple data types
 *
 * T="I"			Integer in little-endian byte order
 * T="B"			Boolean (1 byte; restricted to 0 / 1)
 * T="S"			UTF-l16LE (2 byte) encoded string. Must be null terminated variable size
 * T="A"			ASCII (1 byte) encoded string. Must be Either specified with size or null terminated variable size
 * T="G"			[RFC4122] GUID (e.g. "6ba7b810-9dad-11d1-80b4-00c04fd430c8")
 * T="H"			Hex. string (without terminating null). Needs "S(ize" reference field
 * T="T"			A integer representing the number of 100-nanosecond intervals since January 1, 1601
 *
 * Complex data types (input only)
 *
 * T="XA"			self::_getRPC() 			[MS-OXCRPC] 3.1.4.1.1.1.1 rgbAuxIn Input Buffer
 * T="XB"			self::_getRestrictionion() 	Restrictionion. Decode filter
 * T="XC"			self::_getTaggedProperty() 	Data structure(s)
 * T="XD"			self::_getPropertyRow() 	Data structure(s)
 *
 * Currently not supported
 *
 * Float32, 	M_Float32, 		Float64, 	M_Float64
 * Currency, 	M_Currency
 * FloatTime
 * ServerId, 	Restriction, 	RuleAction, null, 		Object, 	Unspecific, 	Error
 *
 * Operators
 *
 * C="X=val[add]"	Check for referenced field. If value is not "val", then tag will be deleted.
 * C="tag!val[add]"	Check for referenced field. If value is "val", then tag will be deleted.
 * 	Possible "val":
 * 	*				Any text or numeric value
 * 	DATA			Referenced tag is of type "S", "A", "H" or "M_*" (input only)
 *  MULTI			Referenced tag is of type "H" or "M_*"
 *  Possible "add":
 *  |				Or
 *  $				And
 *  				Followed by additional check
 * N="tag"			Counter reference. If referenced field is "0", then tag will be deleted; otherwiese repeat as given.
 * S="tag"			Size (reference). If not numeric, pick length of field from referenced tag.
 * V="tag"			Use variable type specifed in tag.
 * J="val"			Jump to position "val" in auxilliary buffer from the beginning of the AUX_HEADER.
 * D="contant"		Use constant from mapiDefs
 * F="constant"		Use flags from constant mapiFlags. Item will be skipped if the "tag" value is "0".
 * Stop="Yes"		Stop <Body> decoding at this tag
 * X="msg"			A message or tag to help identify structure
 *
 * P="0xXX (n)"		Informational: Position in input buffer.
 *
 */

class mapiWBXML {

	// [MS-OXCDATA] 2.12.7.1 BitMaskRestrictionion Structure
	const BITMASK		 			= [
		'Equ'						=> 0x00,		// Perform a bitwise AND operation on the value of the Mask field with the
													// value of the property PropTag field, and test for being equal to 0
		'Neq'						=> 0x01,		// Perform a bitwise AND operation on the value of the Mask field with the
													// value of the property PropTag field, and test for not being equal to 0.
	];

	// [MS-OXCDATA] 2.12.5.1 PropertyRestrictionion Structure
	const COMP 			 			= [
		'Lt'						=> 0x00,		// true if the value of the object's property is less than the specified
													// value.
		'Le'						=> 0x01,		// true if the value of the object's property is less than or equal to
													// the specified value.
		'Gt'						=> 0x02,		// true if the value of the object's property value is greater than the
													// specified value.
		'Ge'						=> 0x03,		// true if the value of the object's property value is greater than or
													// equal to the specified value.
		'Eq'						=> 0x04,		// true if the object's property value equals the specified value.
		'Ne'						=> 0x05,		// true if the object'sp roperty value does not equal the specified value.
		'MemberOfDL'				=> 0x64,		// true if the value of the object's property is in the DL membership of
													// the specified property value. The value of the object's property MUST
													// be an EntryID of a mail-enabled object in the address book. The
													// specified property value MUST be an EntryID of a Distribution List
													// object in the address book.
	];

	// [MS-OXCDATA] 2.12.4.1 ContentRestrictionion Structure
	const FUZZY_LOW		 			= [
		'FullString'				=> 0x0000,		// The value and the value of the column property tag match one
													// another in their entirety.
		'SubString'					=> 0x0001,		// The value matches some portion of the value of the column property tag.
		'Prefix'					=> 0x0002,		// The value matches a starting portion of the value of the property tag.
	];
	const FUZZY_HIGH	 			= [
		'IgnoreCase'				=> 0x0001,		// The Comparison does not consider case.
		'IgnoreSpace'				=> 0x0002,		// The Comparison ignores Unicode-defined nonspacing characters such
													// as diacritical marks.
		'Loose'						=> 0x0004,		// The Comparison results in a match whenever possible, ignoring case
													// and nonspacing characters.
	];

	// [MS-OXCDATA] 2.12 Restrictionions
	const RESTRICTION_OP 	 			= [
		'And'						=> 0x00,		// Logical AND operation applied to a list of subRestrictionions
		'Or'						=> 0x01,		// Logical OR operation applied to a list of subRestrictionions.
		'Not'						=> 0x02,		// Logical NOT operation applied to a subRestrictionion.
		'Content'					=> 0x03,		// Search a property value for specific content.
		'Property'					=> 0x04,		// Compare a property value with a particular value.
		'Compare'					=> 0x05,		// Compare the values of two properties.
		'BitMask'					=> 0x06,		// Perform a bitwise AND operation on a property value with
													// a mask and Compare that with 0 (zero).
		'Size'						=> 0x07,		// Compare the size of a property value to a particular figure.
		'Exist'						=> 0x08,		// Test whether a property has a value.
		'Sub'						=> 0x09,		// Test whether any row of a message's attachment or recipient
													// table satisfies a subRestrictionion.
		'Comment'					=> 0x0a,		// Associates a Comment with a subRestrictionion.
		'Count'						=> 0x0b,		// Limits the number of matches returned from a subRestrictionion.
	];

	/**
     * 	Work buffer
     * 	@var string
     */
    protected static $_wrk = null;

    /**
     * 	Position in buffer
     * 	@var integer
     */
    protected static $_pos;

    /**
     * 	Position in buffer as string
     * 	@var string
     */
    private $_dpos = '';

    /**
     * 	Default code page
     * 	@var string
     */
    protected static $_cs = 'ISO-8859-1';

    /**
     * 	Auxilliary buffer position
     * 	@var integer
     */
    private $_aux;

    /**
     * 	Stop Flag
     * 	@var bool
     */
    private $_stop;

    /**
     * 	Error counter
     * 	@var integer
     */
    protected $_err = 0;

    const MAX_ERR = 10;

	/**
	 * 	Configuration class pointer
	 * 	@var Config
	 */
	protected $_cnf;

    /**
     * 	Singleton instance of object
     * 	@var mapiWBXML
     */
    static private $_obj = null;

    /**
	 *  Get class instance handler
	 *
	 *  @return - Class object
	 */
	public static function getInstance(): mapiWBXML {

		if (!self::$_obj) {

            self::$_obj = new self();
			self::$_obj->_cnf = Config::getInstance();

			// register shutdown function
			Server::getInstance()->regShutdown(__CLASS__);
		}

		return self::$_obj;
	}

	/**
	 * 	Shutdown function
	 */
	public function delInstance(): void {

		if (self::$_obj)
			self::$_obj = null;
	}

	/**
	 * 	Collect information about class
	 *
	 * 	@param 	- Object to store information
  	 */
	public function getInfo(XML &$xml): void {

		$xml->addVar('Opt', '<a href="https://learn.microsoft.Object/en-us/openspecs/exchange_server_protocols/ms-oxcrpc" target="_blank">[MS-UCODEREF]</a> '.
				      'Windows Protocols Unicode Reference');
		$xml->addVar('Stat', 'v15.0');

		$xml->addVar('Opt', '<a href="https://learn.microsoft.Object/en-us/openspecs/exchange_server_protocols/ms-oxcrpc" target="_blank">[MS-OXCRPC]</a> '.
				      'Wire Format Protocol');
		$xml->addVar('Stat', 'v23.1');

		$xml->addVar('Opt', '<a href="https://learn.microsoft.Object/en-us/openspecs/exchange_server_protocols/ms-oxcdata" target="_blank">[MS-OXCDATA]</a> '.
				      'Data Structures');
		$xml->addVar('Stat', 'v18.0');

		$xml->addVar('Opt', '<a href="https://learn.microsoft.Object/en-us/openspecs/exchange_server_protocols/ms-oxcfold" target="_blank">[MS-OXCFOLD]</a> '.
				      'Folder Object Protocol');
		$xml->addVar('Stat', 'v23.2');

		$xml->addVar('Opt', '<a href="https://learn.microsoft.Object/en-us/openspecs/exchange_server_protocols/ms-oxcstor" target="_blank">[MS-OXCSTOR]</a> '.
				      'Store Object Protocol');
		$xml->addVar('Stat', 'v25.0');
	}

	/**
	 * 	Load skeleton
	 *
	 *  @param  - Path to file name
	 *  @param  - Command name
	 *	@param	- mapiHTTP::REQ = Decode request; mapiHTTP::RESP = Decode response; mapiHTTP::MKRESP = Create response
	 * 	@param 	- Initial call
	 * 	@return	- new XML structure
	 */
	protected function _loadSkel(string $path, string $cmd, int $mod, bool $ini = true): ?XML {

		$src = new XML();
		if (!$src->loadFile(Config::getInstance()->getVar(Config::ROOT).'mapi-bundle/assets/'.$path.'.xml'))
		    return null;

		$xml = new XML();
		$xml->loadXML('<syncgw><MetaTags/></syncgw>');
		$xml->getVar('syncgw');

		$err = '';
		if ($ini) {
			$http = HTTP::getInstance();
			self::$_wrk = $http->getHTTPVar($mod == mapiHTTP::RESP ? HTTP::SND_BODY : HTTP::RCV_BODY);

			switch ($mod) {
			case mapiHTTP::REQ:
				break;

			case mapiHTTP::RESP:
				if (strlen(self::$_wrk) < 8)
					$err = 'Err';
				break;

			case mapiHTTP::MKRESP:
				if (($v = self::$_wrk->getVar('ErrCode')) !== null && $v != 'Success')
					$err = 'Err';
				break;
			}

			self::$_pos = 0;
		}

		switch ($mod) {
		case mapiHTTP::REQ:
			$dbg = 'Req';
			$rc  =
			$src->xpath('//'.$cmd.'[@Typ="Req"]');
			break;

		case mapiHTTP::RESP:
			$dbg = 'Resp'.$err;
			$rc  =
			$src->xpath('//'.$cmd.'[@Typ="Resp'.$err.'"]');
			break;

		case mapiHTTP::MKRESP:
			$dbg = 'mkResp';
			$rc  =
			$src->xpath('//'.$cmd.'[@Typ="mkResp'.$err.'"]');
			break;
		}

		if (!$rc)
			Msg::ErrMsg('Error locating [<'.$cmd.' Typ="'.$dbg.'">');
		else
			Msg::InfoMsg('<'.$cmd.' Typ="'.$dbg.'"> located');

		$src->getItem();
		$xml->append($src, false, false);

		$xml->setTop();

		return $xml;
	}

	/**
	 * 	Decode binary input
	 *
	 * 	@param 	- XML skeleton object (Request / Response)
	 * 	@param 	- Tag to start or null for current node
	 * 	@param 	- Recursive flag
	 * 	@return - false = Error; true = Ok
	 */
	public function Decode(XML &$xml, ?string $tag = 'syncgw', bool $rec = false): bool {

		$ip = $xml->savePos();
		$xml->getChild($tag, false);

		if ($tag == 'AUX_HEADER')
			$this->_aux = self::$_pos;

		if (!$rec)
			$this->_stop = false;

		if (self::$_obj->_cnf->getVar(Config::DBG_SCRIPT))
			Msg::InfoMsg('<'.($tag ? $tag : $xml->getName()).'>');

		$run = 0;

		while (!$this->_stop && $xml->getItem() !== null) {

			$stag  = $xml->getName();
			$attr  = $xml->getAttr();

			if ($run++ > 50 || $this->_err > self::MAX_ERR) {

				$this->_stop = true;
				Msg::ErrMsg('Processing aborted - too many Errors!');
				$xml->setTop();
				return false;
			}

			if ($xml->hasChild()) {

				// stop processing?
				if (isset($attr['Stop'])) {

					$this->_stop = true;
					return true;
				}

				// check for referenced field
				if (!self::_check($xml, $attr))
					continue;

				// check for counter reference
				if (isset($attr['N'])) {

					$p = $xml->savePos();
					$xml->setParent();
					for ($i=0; $i < 4; $i++)
						if (!$xml->setParent() || ($val = $xml->getVar($attr['N'], false)) !== null)
							break;
					$xml->restorePos($p);
					if ($val === 0) {

						if (self::$_obj->_cnf->getVar(Config::DBG_SCRIPT))
							Msg::InfoMsg('--- Deleting <'.$stag.'> due to counter <'.$attr['N'].'>');
						$xml->delVar(null);
						continue;
					}
					if ($val > 1) {

						if (self::$_obj->_cnf->getVar(Config::DBG_SCRIPT))
							Msg::InfoMsg('--- Repeating '.($val - 1).' <'.$stag.'> due to counter <'.$attr['N'].'>');
						// we did duplication
						$xml->delAttr('N');
						$xml->dupVar($val - 1);
						$xml->setParent();
						$xml->xpath($stag, false);
						while($xml->getItem() !== null)
							if (!self::Decode($xml, $stag, true))
								return false;
						$xml->restorePos($p);
						continue;
					}
				}

				if (!self::Decode($xml, $stag, true))
					return false;

				continue;
			}

			// stop processing?
			if (isset($attr['Stop'])) {
				$this->_stop = true;
				return true;
			}

			// check for referenced field
			if (!self::_check($xml, $attr))
				continue;

			$xml->setAttr([	'P' => $this->_dpos = sprintf('0x%X %d', self::$_pos, self::$_pos) ]);

			// jump?
			if (isset($attr['J'])) {
				$p = $xml->savePos();
				$xml->setParent();
				$n = $xml->getVar($attr['J'], false);
				$xml->restorePos($p);
				if (!$n)
					continue;
				self::$_pos = $this->_aux + intval($n);
				if (self::$_obj->_cnf->getVar(Config::DBG_SCRIPT))
					Msg::InfoMsg('--- Jumping to <'.$stag.'> for <'.$attr['J'].'>');
			}

			// get size (reference)
			if (isset($attr['S'])) {

				// is it a reference?
				if (!is_numeric($attr['S'])) {

					$p = $xml->savePos();
					$xml->setParent();
					$len = intval($xml->getVar($attr['S'], false));
					$xml->restorePos($p);
				} else
					$len = intval($attr['S']);
			} else
				$len = -1;

			// get value type from other tag
			if (isset($attr['V'])) {

				$p = $xml->savePos();
				for ($i=0; $i < 4; $i++)
					if (!$xml->setParent() || ($val = $xml->getVar($attr['V'], false)) !== null)
						break;
				$xml->restorePos($p);
				if ($val == null) {

					$val = 'ANY';
					if (self::$_obj->_cnf->getVar(Config::DBG_SCRIPT))
						Msg::InfoMsg($xml, 'Replacing invalid data type with "ANY" for ['.$attr['V'].'] at '.$this->_dpos);
				}
				switch ($val) {
				case 'I2':
				case 'I4':
				case 'I8':
					$len = intval(substr($val, 1));
					$val = 'I';

				default:
					break;
				}

				$vals = self::_getData($xml, $val, $len);
				$p = $xml->savePos();
				$xml->setParent();
				$xml->xpath($stag, false);
				foreach ($vals as $val) {

					$xml->getItem();
					$xml->setVal($val);
					if (self::$_obj->_cnf->getVar(Config::DBG_SCRIPT))
						Msg::InfoMsg('<'.$stag.' V="'.$attr['V'].' '.$this->_dpos.'">'.$val.'</'.$stag.'>');
				}
				$xml->restorePos($p);
				continue;
			}

			// tag without a data type
			if (!isset($attr['T']))
				continue;

			switch ($attr['T']) {
			// Integer
			case 'I':
			// Hex. string
			case 'H':
			// GUID
			case 'G':
				$val = self::_getData($xml, $attr['T'], $len)[0];
				// check for flags
				if (isset($attr['F']))
					$val = self::_convFlags(1, $attr['F'], $val);
				// check for constants
				elseif (isset($attr['D']))
					$val = self::_convConst(1, $attr['D'], $val);
				break;

			default:
				$val = self::_getData($xml, $attr['T'], $len)[0];
				break;
			}

			if ($val !== null) {

				if (self::$_obj->_cnf->getVar(Config::DBG_SCRIPT))
					Msg::InfoMsg('<'.$stag.' "'.$this->_dpos.'">'.$val.'</'.$stag.'>');
				$xml->setVal(strval($val));
			}
		}

		$xml->restorePos($ip);

		if (!$rec && !$this->_stop && $tag == 'Trailer') {

				if (($len = strlen(self::$_wrk) - self::$_pos) > 0)
				Msg::ErrMsg(substr(self::$_wrk, self::$_pos), 'Unused buffer (Len= '.$len.' 0x'.
						sprintf('%X', $len).') at '.self::$_pos.' 0x'.sprintf('%X', self::$_pos), 0, 1024);
		}

		return true;

	}

	/**
	 * 	Encode binary input from current position
	 *
	 * 	@param	- Input object
	 *  @param 	- Tag to get childrens from
	 * 	@param 	- Recursive flag
	 * 	@return	- Binary data or null
	 */
	public function Encode(XML $xml, string $tag = 'syncgw', bool $first = true): ?string {

		$out = '';
		$ip = $xml->savePos();

		$xml->getChild($tag, false);
		while (($val = $xml->getItem()) !== null) {

			$attr = $xml->getAttr();
			$tag  = $xml->getName();

			// check for referenced field
			if (!self::_check($xml, $attr))
				continue;

			if ($xml->hasChild()) {

				// counter reference?
				if (isset($attr['N'])) {

					$p = $xml->savePos();
					$n = $xml->getVar($attr['N']);
					$xml->restorePos($p);
					if (!$n) {

						$xml->delVar(null);
						continue;
					}
				}

				$out .= self::Encode($xml, $tag, false);
				continue;
			}

			// get size (reference)
			if (isset($attr['S'])) {

				// is it a reference?
				if (!is_numeric($attr['S'])) {

					$p = $xml->savePos();
					$xml->setParent();
					$len = intval($xml->getVar($attr['S'], false));
					$xml->restorePos($p);
				} else
					$len = intval($attr['S']);
			} else
				$len = -1;

			// get value type from other tag
			if (isset($attr['V'])) {

				$p = $xml->savePos();
				for ($i=0; $i < 4; $i++)
					if (!$xml->setParent() || ($typ = $xml->getVar($attr['V'], false)) !== null)
						break;
				$xml->restorePos($p);

				switch ($typ) {
				case 'I2':
				case 'I4':
				case 'I8':
					$len = intval(substr($typ, 1));
					$typ = 'I';

				default:
					break;
				}

				// check for flags
				if (isset($attr['F']))
					$val = self::_convFlags(2, $attr['F'], $val);

				// check for constants
				elseif (isset($attr['D']))
					$val = self::_convConst(2, $attr['D'], $val);

				$out .= self::_putData($xml, $val, $typ, $len);
				continue;
			}

			// any data type set?
			if (!isset($attr['T']))
				continue;

			switch ($attr['T']) {
			// Hex. string
			case 'H':
			// Integer
			case 'I':
			// GUID
			case 'G':
				// check for flags
				if (isset($attr['F']))
					$val = self::_convFlags(2, $attr['F'], $val);
				// check for constants
				elseif (isset($attr['D']))
					$val = self::_convConst(2, $attr['D'], $val);

			default:
				$out .= self::_putData($xml, $val, $attr['T'], $len);
				break;

			// Complex data type - not supported
			case 'XA':
			case 'XB':
			case 'XC':
			case 'XD':
				Msg::WarnMsg('_putData() complex data type ['.$attr['T'].'] for <'.$xml->getName().'> skipped');
				break;
			}
		}

		$xml->restorePos($ip);

		return $out;
	}

	/**
	 * 	Check tag attributes
	 *
	 * 	@param 	- XML Document
	 * 	@param 	- Attributes
	 * 	@param 	- 1 = Check only; 0 = Delete tag
	 * 	@return	- true = Continue, false = Skip tag
	 */
	private function _check(XML &$xml, array $attr, int $del = 0): bool {

		// any referenced field available?
		if (!isset($attr['C']))
			return true;

		if (self::$_obj->_cnf->getVar(Config::DBG_SCRIPT) && !$del)
			Msg::InfoMsg('Checking <'.$xml->getName().' C="'.$attr['C'].'">');

		// Multiple checks?
		// AND
		if (strpos($attr['C'], '$')) {

			$chks = explode('$', $attr['C']);
			for ($i=0; $i < count($chks); $i++)
				if (!self::_check($xml, [ 'C' => $chks[$i] ]))
					return false;
			return true;
		}
		// OR
		if (strpos($attr['C'], '|')) {

			$chks = explode('|', $attr['C']);
			for ($i=0; $i < count($chks); $i++)
				if (self::_check($xml, [ 'C' => $chks[$i] ], -1))
					return true;
			if (self::$_obj->_cnf->getVar(Config::DBG_SCRIPT))
				Msg::InfoMsg('--- Deleting <'.$xml->getName().'>. It is not in ['.$attr['C'].']');
			$del = 2;
		}

		if ($del < 2) {

			// Equal
			$op = strpos($attr['C'], '=') ?	'=' : '!';
			list($tag, $chk) = explode($op, $attr['C']);

			$p   = $xml->savePos();
			$val = null;
			for ($i=0; $i < 4; $i++) {
				if (!$xml->setParent() || ($val = $xml->getVar($tag, false)) !== null)
					break;
			}
			$xml->restorePos($p);

			if ($chk == 'DATA') {

				if (($op == '=' &&  ($val == 'S' || $val == 'H' || $val == 'A' || substr($val, 0, 2) == 'M_')) ||
				    ($op == '!' && !($val != 'S' || $val == 'H' || $val == 'A' || substr($val, 0, 2) == 'M_')))
					return true;
				if ($del != -1)
					$del = 1;
			} elseif ($chk == 'MULTI') {

				if (($op == '=' &&  ($val == 'H' || substr($val, 0, 2) == 'M_')) ||
					($op == '!' && !($val == 'H' || substr($val, 0, 2) == 'M_')))
					return true;
				if ($del != -1)
					$del = 1;
			} elseif (($op == '=' && $val != $chk) || ($op == '!' && $val == $chk)) {

				if ($del != -1) {

					if (self::$_obj->_cnf->getVar(Config::DBG_SCRIPT))
						Msg::InfoMsg('--- Deleting <'.$xml->getName().'>. Check is ['.$tag.$op.$chk.']');
					$xml->delVar(null);
				}
				return false;
			} else
				$del = 0;
		}

		if ($del > 0) {

			if ($del < 2 && self::$_obj->_cnf->getVar(Config::DBG_SCRIPT))
				Msg::InfoMsg('--- Deleting <'.$xml->getName().'>. It is not ['.$chk.'], is ['.$tag.$op.$val.']');
			$tag = $xml->getName();
			$p = $xml->savePos();
			$xml->setParent();
			$xml->getChild(null, false);
			while ($xml->getItem() !== null) {

				if ($xml->getName() == $tag && $xml->getAttr('C') == $attr['C'])
					$xml->delAttr('C');
			}
			$xml->restorePos($p);
			$xml->delVar(null);

			return false;
		}

		return $del == -1 ? false : true;
	}

	/**
	 * 	Get mapiDefs::DATA_TYP value
	 *
	 *	@param 	- XML Document
	 * 	@param 	- Data type
	 * 	@param 	- Size of data
	 * 	@return - Array with values or null on error
	 */
	protected function _getData(XML &$xml, string $typ, int $len = -1): ?array {

		$vals = [];

		switch ($typ) {
		// Integer
		case 'I':
			$vals[] = self::_getInt($len);
			break;

		// Boolean
		case 'B':
			if (($v = self::_getInt(1)) == 0)
				$vals[] = 'false';
			else {

				if ($v != 1)
					Msg::InfoMsg('Expecting "true" (0x01) boolean value, got [0x'.sprintf('%X', $v).'] '.
							   'at '.$this->_dpos);
				$vals[] = 'true';
			}
			break;

		// UTF-16LE
		case 'S':
			$vals[] = self::_getStr($len);
			break;

		// ASCII
		case 'A':
			// check for inline character set
			$p = $xml->savePos();
			if (!($cs = $xml->getVar('CodePage')))
				// take default character set
				$cs = self::$_cs;
			$xml->restorePos($p);
			$vals[] = self::_getStr($len, $cs);
			break;

		// Hex. string
		case 'H':
			$vals[] = self::_getBin($len);
			break;

		// GUID
		case 'G':
			$vals[] = sprintf('%08X-', self::_getInt(4)).sprintf('%04X-', self::_getInt(2)).
				   	  sprintf('%04X-', self::_getInt(2)).sprintf('%04X-', self::_getInt(2)).
				      sprintf('%04X', self::_getInt(2)).sprintf('%08X', self::_getInt(4));
			break;

		// Time
		case 'T':
			$n = self::_getInt(8);
			$s = substr($n, 0, 11);
			$d = new \DateTime('1601-01-01');
			$d->modify('+'.$s.' seconds');
			$vals[] = $d->format('Y-m-d H:i:s');
			break;

		case 'M_I2':
		case 'M_I4':
		case 'M_I8':
		case 'M_G':
		case 'M_H':
		case 'M_S':
		case 'M_A':
		case 'M_T':
			$p = $xml->savePos();
			$xml->setParent();
			if ($n = $xml->getVar('Count'))
				$vals[] = self::_getData($xml, substr($typ, 2), $len)[0];
			$xml->restorePos($p);
			break;

		// complex data type self::_getRPC()
		case 'XA':
		// complex data type self::_getRestrictionion()
		case 'XB':
		// complex data type self::_getTaggedProperty()
		case 'XC':
		// complex data type self::_getPropertyRow()
		case 'XD':
			if (!isset(mapiDefs::DATA_TYP_COMPLEX[$typ])) {

				Msg::WarnMsg('Unknown complex data type "'.$typ.'" for at '.$this->_dpos);
				$this->_err++;
				return null;
			}
			$typ = mapiDefs::DATA_TYP_COMPLEX[$typ];

			$http = HTTP::getInstance();
			$req  = $http->getHTTPVar(HTTP::RCV_BODY);

			if (self::$_obj->_cnf->getVar(Config::DBG_SCRIPT))
				Msg::InfoMsg('Processing <'.$xml->getName().'> - self::'.$typ.' at '.$this->_dpos);
			if (!self::$typ($xml, $req))
				return null;
			$vals[] = null;
			break;

		// case 'Float32':
		// case 'M_Float32':
		// case 'Float64':
		// case 'M_Float64':
		// case 'Currency':
		// case 'M_Currency':
		// case 'FloatTime':
		// case 'ServerId':
		// case 'Restriction':
		// case 'RuleAction':
		// case 'null':
		// case 'Object':
		// case 'Unspecific':
		// case 'Error':
		default:
			Msg::WarnMsg('Unsupported data type ['.$typ.'] at '.$this->_dpos);
			$vals[] = $typ;
			break;
		}

		return $vals;
	}

	/**
	 * 	Put mapiDefs::DATA_TYP value
	 *
	 *	@param 	- XML Document
	 *	@param 	- Value to store
	 * 	@param 	- Data type
	 * 	@param	- Size of data
	 * 	@return - Binary data
	 */
	protected function _putData(XML &$xml, string $val, string $typ, int $len = -1): string {

		$out = '';

		switch ($typ) {
		// Integer
		case 'I':
			$out = self::_putInt(intval($val), $len);
			break;

		// Boolean
		case 'B':
 	 		$out = $val == 'true' ? chr(0x01) : chr(0x00);
			break;

		// UTF-16LE
		case 'S':
			$out = self::_putStr($val, $len);
			break;

		// ASCII
		case 'A':
			// get inline character set
			$p = $xml->savePos();
			if (!($cs = $xml->getVar('CodePage')))
				// take default character set
				$cs = self::$_cs;
			$xml->restorePos($p);
			$out = self::_putStr($val, $len, $cs);
			break;

		// Hex. string
		case 'H':
			$out = self::_putBin($val, $len);
			break;

		// GUID
		case 'G':
			$v = explode('-', $val);
			$out  = self::_putInt(hexdec($v[0]), 4);
			$out .= self::_putInt(hexdec($v[1]), 2);
			$out .= self::_putInt(hexdec($v[2]), 2);
			$out .= self::_putInt(hexdec($v[3]), 2);
			$out .= self::_putInt(hexdec(substr($v[4], 0, 4)), 2);
			$out .= self::_putInt(hexdec(substr($v[4], 4)), 4);
			break;
			break;

		// Time
		case 'T':
			$d   = new \DateTime($val);
			$o   = new \DateTime('1601-01-01');
			$out = self::_putInt(intval(abs((float)$d->format("U.u") - (float)$o->format("U.u")) * 1000), 8);
			break;

		case 'M_I2':
		case 'M_I4':
		case 'M_I8':
		case 'M_G':
		case 'M_H':
		case 'M_S':
		case 'M_A':
		case 'M_T':
			$t = $xml->getName();
			$p = $xml->savePos();
			$xml->setParent();
			$xml->xpath($t, false);
			while ($xml->getItem() !== null)
				$out .= self::_putData($xml, $val, substr($typ, 2), $len);
			$xml->restorePos($p);
			break;

		// case 'Float32':
		// case 'M_Float32':
		// case 'Float64':
		// case 'M_Float64':
		// case 'Currency':
		// case 'M_Currency':
		// case 'FloatTime':
		// case 'ServerId':
		// case 'Restriction':
		// case 'RuleAction':
		// case 'Unspecific':
		// case 'null':
		// case 'Object':
		// case 'Error':
		default:
			$out = $typ;
			break;
		}

		return $out;
	}

	/**
	 * 	Convert flags
	 *
	 *	@param 	- 1 = Bin2String; 2 = String2Bin
	 *	@param 	- mapiFlags
	 *	@param 	- Value to check
	 * 	@return - Flag string
	 */
	private function _convFlags(int $mod, string $const, string $flags): string {

	 	if (!($a = self::_getConst($const)))
	 		return 'Error';

		if ($mod == 1) {

			$a = array_flip($a);
			$v  = '';
			foreach ($a as $k => $t)
				if ($k & $flags || $k == $flags)
					$v .= $t.'|';
			if (strlen($v))
				$v = substr($v, 0, -1);
		} else {

			$v  = 0;
			foreach ($a as $k => $t)
				if (strpos($flags, $k) !== false)
					$v |= $t;
		}

		return strval($v);
	}

	/**
	 * 	Convert constants
	 *
	 *	@param 	- 1 = Bin2String; 2 = String2Bin
	 *	@param 	- mapiDefs flag structure
	 *	@param 	- Value to check
	 * 	@return - Flag string
	 */
	protected function _convConst(int $mod, string $const, $typ): string {

	 	if (!($a = self::_getConst($const)))
	 		return 'Error';

		if ($mod == 1) {
			$a = array_flip($a);
			if (!isset($a[$typ])) {

				$v = is_numeric($typ) ? sprintf('0x%04X', $typ) : $typ;
				Msg::WarnMsg($a, 'Undefined value ['.$typ.(is_int($typ) ?: sprintf(' 0x%X', $typ)).
							'] in [D="'.$const.'"] at '.$this->_dpos);
				$this->_err++;
			} else
				$v = $a[$typ];
	 	} else {
	 		if (!isset($a[$typ])) {

				Msg::WarnMsg($a, 'Missing value "'.$typ.'" in [D="'.$const.'"] at '.$this->_dpos);
				$this->_err++;
				$v = strval($typ);
	 		} else
				$v = strval($a[$typ]);
	 	}

		return $v;
	}

	/**
	 * 	Get constant array
	 *
	 *	@param 	- Constant name
	 * 	@return - [] or null
	 */
	private function _getConst(string $name): ?array {

		$a = null;
		foreach ([ 'mapi\\mapiDefs', 'mapi\\mapiFlags', 'rops\\ropDefs',
				   'rpc\\rpcDefs', 'ics\\icsDefs', 'ics\\icsFlags' ] as $n) {

			if (defined($n = 'syncgw\\'.$n.'::'.$name)) {

				$a = constant($n);
				break;
			}
		}
	 	if (!$a) {

		 	Msg::WarnMsg('Could not load constant ['.$name.'] in any sub directory');
			$this->_err++;
			return null;
	 	}

	 	return $a;
	}

	/**
	 * 	Get integer
	 *
	 * 	@param	- Max. length of integer
	 * 	@param 	- true = little-endian byte order; false = big endian byte order
	 * 	@return	- Integer
	 */
	protected function _getInt(int $max = 999, bool $little = true): string {

		if (is_null(self::$_wrk) || ($n = strlen(self::$_wrk)) < self::$_pos + $max) {

			$n = self::$_pos + $max - $n;
			Msg::WarnMsg('<Body> is too small (is: '.(isset($n) ? $n : 0).' should be: '.
						(self::$_pos + $max).'). Filling up with '.$n.' 0x00');
			while ($n--)
				self::$_wrk .= chr(0);
			$this->_err++;
		}

		$val = 'Error';
		switch ($max) {
		case 1:
			$val = strval(unpack('C', substr(self::$_wrk, self::$_pos++, 1))[1]);
			break;

		case 2:
			$val = unpack($little ? 'v' : 'n', substr(self::$_wrk, self::$_pos, $max));
			self::$_pos += $max;
			$val = strval(is_array($val) ? $val[1] : $val);
			break;

		case 4:
			$val = unpack($little ? 'V' : 'N', substr(self::$_wrk, self::$_pos, $max));
			self::$_pos += $max;
			$val = strval(is_array($val) ? $val[1] : $val);
			break;

		case 8:
			$val = unpack($little ? 'P' : 'J', substr(self::$_wrk, self::$_pos, $max));
			self::$_pos += $max;
			$val = strval(is_array($val) ? $val[1] : $val);
			break;

		default:
			break;
		}

		return $val;
	}

	/**
	 * 	Put integer (little-endian byte order)
	 *
	 * 	@param	- Value
	 * 	@param	- Max. length of integer
	 * 	@param 	- true = little endian byte order; false = big endian byte order
	 * 	@return - String converted integer
	 */
	private function _putInt(int $val, int $max = 999, bool $little = true): string {

		switch ($max) {
		case 1:
			$val = pack('C', $val);
			break;

		case 2:
			$val = pack($little ? 'v' : 'n', $val);
			break;

		case 4:
			$val = pack($little ? 'V' : 'N', $val);
			break;

		case 8:
			$val = pack($little ? 'P' : 'J', $val);
			break;

		default:
			break;
		}

		return strval($val);
	}

	/**
	 * 	Get string
	 *
	 * 	@param	- Max. length of string or -1 for variable size null terminated
	 * 	@param	- Character set
	 * 	@return	- Extracted string from input buffer
	 */
	private function _getStr(int $max = -1, string $cs = 'UTF-16LE'): string {

		if ($max == -1) {

			if ($cs == 'ANSI' || $cs == 'ISO-8859-1') {

				$end = "\0";
				$len = 1;
			} else {

				$end = "\0\0";
				$len = 2;
			}
			$val = '';
			$max = strlen(self::$_wrk) - 1;
			while (substr(self::$_wrk, self::$_pos, $len) != $end && self::$_pos < $max) {

				$val .= substr(self::$_wrk, self::$_pos, $len);
				self::$_pos += $len;
			}
			// add termination byte
			$val .= $end;
			self::$_pos += $len;
		} else {
			$val = substr(self::$_wrk, self::$_pos, $max);
			self::$_pos += $max;
		}

		$enc = Encoding::getInstance();
		$enc->setEncoding($cs);

		return $enc->import($val);
	}

	/**
	 * 	Put string
	 *
	 *  @param 	- String to store
	 * 	@param	- Max. length of string or -1 for variable size null terminated
	 * 	@param	- Character set
	 * 	@return - New string
	 */
	private function _putStr(string $str, int $max = -1, string $cs = 'UTF-16LE'): string {

		// [MS_OXCDATA] 2.11.1.2 String Property Values
		// Clients SHOULD use string properties in Unicode format.
		// When using strings in Unicode format, string data MUST be encoded as UTF-16LE format
		$enc = Encoding::getInstance();
		$enc->setEncoding($cs);

		if ($cs == 'ANSI' || $cs == 'ISO-8859-1') {

			$end = "\0";
			$len = 1;
		} else {

			$end = "\0\0";
			$len = 2;
		}

		if ($max > -1) {

			$str = $enc->export($str).$end;
			$str = substr($str, 0, $max * $len);
		} else
			$str = $enc->export($str).$end;

		return $str;
	}

	/**
	 * 	Get binary string
	 *
	 * 	@param	- Max. length of string
	 * 	@return	- String from input buffer in hex. format
	 */
	private function _getBin(int $max = 999): string {

		$val = substr(self::$_wrk, self::$_pos, $max);
		self::$_pos += $max;

		return bin2hex($val);
	}

	/**
	 * 	Put binary string
	 *
	 * 	@param 	- Buffer
	 *  @param 	- Hex. string to store
	 * 	@param	- Max. length of string (unused)
	 * 	@return - New string
	 */
	private function _putBin(string $str, int $max = 99): string {

		return hex2bin($str);
	}

	/**
	 * 	Get RPC structure
	 *
	 * 	@param	- XML structure to store data
	 * 	@return - true = Ok; false = Error
	 */
	private function _getRPC(XML &$xml): bool {

		return rpcHandler::getInstance()->Process($xml);
	}

	/**
	 * 	Get Restrictionion structure
	 *
	 * 	@param	- XML structure to store data
	 * 	@param 	- Unused
	 * 	@param 	- Add <Restrictionion> tag
	 * 	@return - true = Ok; false = Error
	 *
	 */
	private function _getRestriction(XML &$xml, $unused, bool $add = false): bool {

		$op = $xml->savePos();
		if ($add)
			$xml->addVar('Restrictionion');

		$a =  array_flip(self::RESTRICTION_OP);
		$p =  [ 'D' => 'RESTRICTION_OP' ];
		$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
		$xml->addVar('Typ', $a[$typ = self::_getInt(1)], false, $p);

		switch ($typ) {
		// [MS-OXCDATA] 2.12.1.1 AndRestrictionion Structure
		case self::RESTRICTION_OP['And']:
		// [MS-OXCDATA] 2.12.2.1 OrRestrictionion Structure
		case self::RESTRICTION_OP['Or']:
			$p  = [ 'T' => 'I', 'S' => '2' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('Count', $n = self::_getInt(2), false, $p);

			while ($n--)
				self::_getRestriction($xml, true);
			break;

		// [MS-OXCDATA] 2.12.3.1 NotRestrictionion Structure
		case self::RESTRICTION_OP['Not']:
			self::_getRestriction($xml, true);
			break;

		// [MS-OXCDATA] 2.12.4.1 ContentRestrictionion Structure
		case self::RESTRICTION_OP['Content']:
			$a  = array_flip(self::FUZZY_LOW);
			$p  = [ 'D' => 'FUZZY_LOW' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('FuzzyLow', $a[self::_getInt(2)], false, $p);

			$n = self::_getInt(2);
			$f = '';
			foreach (self::FUZZY_HIGH as $k => $v)
				if ($n & $v)
					$f .= $k.'|';
			$p  = [ 'F' => 'FUZZY_HIGH' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('FuzzyHigh', strlen($f) ? substr($f, 0, -1) : $f, false, $p);

			$p  = [ 'T' => 'I', 'S' => '4' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('PropertyTag', self::_getInt(4), false, $p);
			self::_getTaggedProperty($xml);
			break;

		// [MS-OXCDATA] 2.12.5.1 PropertyRestrictionion Structure
		case self::RESTRICTION_OP['Property']:
			$a = array_flip(self::COMP);
			$p  = [ 'D' => 'Comp' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('Operator', $a[self::_getInt(1)], false, $p);

			// [MS-OXCDATA] 2.11.4 TaggedPropertyValue Structure
			$xml->addVar('PropTag');

			// [MS-OXCDATA] 2.9 PropertyTag Structure
			$p  = [ 'T' => 'I', 'S' => '2', 'D' => 'DATA_TYP' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$n  = self::_getInt(2);
			$a = array_flip(mapiDefs::DATA_TYP);
			$op = $xml->savePos();
			$xml->addVar('Type', $a[$n], false, $p);

			$p  = [ 'T' => 'I', 'S' => '2', 'D' => 'PID' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$a = array_flip(mapiDefs::PID);
			$xml->addVar('Id', $a[self::_getInt(2)], false, $p);
			$xml->restorePos($op);

			// [MS-OXCDATA] 2.11.2.1 PropertyValue Structure
			$p  = [ 'T' => 'I', 'S' => '2' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('Reserved', self::_getInt(1), false, $p);

			self::_getTaggedProperty($xml);
			break;

		// [MS-OXCDATA] 2.12.6.1 ComparePropertiesRestrictionion Structure
		case self::RESTRICTION_OP['Compare']:
			$a  = array_flip(self::COMP);
			$p  = [ 'D' => 'Comp' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('Operator', $a[self::_getInt(1)], false, $p);

			$p  = [ 'T' => 'I', 'S' => '4' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('PropertyTag', self::_getInt(4), false, $p);

			$p  = [ 'T' => 'I', 'S' => '4' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('PropertyTag', self::_getInt(4), false, $p);
			break;

		// [MS-OXCDATA] 2.12.7.1 BitMaskRestrictionion Structure
		case self::RESTRICTION_OP['BitMask']:
			$a  = array_flip(self::BITMASK);
			$p  = [ 'D' => 'BITMASK' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('Operator', $a[self::_getInt(1)], false, $p);

			$p  = [ 'T' => 'I', 'S' => '4' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('PropertyTag', self::_getInt(4), false, $p);

			$p  = [ 'T' => 'I', 'S' => '4' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('Mask', self::_getInt(4), false, $p);
			break;

		// [MS-OXCDATA] 2.12.8.1 SizeRestrictionion Structure
		case self::RESTRICTION_OP['Size']:
			$a  = array_flip(self::COMP);
			$p  = [ 'D' => 'Comp' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('Operator', $a[self::_getInt(1)], false, $p);

			$p  = [ 'T' => 'I', 'S' => '4' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('PropertyTag', self::_getInt(4), false, $p);

			$p  = [ 'T' => 'I', 'S' => '4' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('Size', self::_getInt(4), false, $p);
			break;

		// [MS-OXCDATA] 2.12.9.1 ExistRestrictionion Structure
		case self::RESTRICTION_OP['Exist']:
			$p  = [ 'T' => 'I', 'S' => '4' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('PropertyTag', self::_getInt(4), false, $p);
			break;

		// [MS-OXCDATA] 2.12.10.1 SubObjectRestrictionion Structure
		case self::RESTRICTION_OP['Sub']:
			// @todo implemntation of SUBRestrictionION
			$p  = [ 'T' => 'I', 'S' => '4' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('SubObject', self::_getInt(4), false, $p);

			self::_getRestriction($xml, true);
			break;

		// [MS-OXCDATA] 2.12.11 CommentRestrictionion Structure
		case self::RESTRICTION_OP['Comment']:
			$p  = [ 'T' => 'I', 'S' => '1' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('TaggedValuesCount', self::_getInt(1), false, $p);

			$xml->setAttr([	'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ]);
			self::_getTaggedProperty($xml);

			$p  = [ 'T' => 'B' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('RestrictionionPresent', ($n = self::_getInt(1)) == 0x01 ? 'true' : 'false', false, $p);

			if ($n)
				self::_getRestriction($xml, true);
			$xml->setAttr([ 'C' => 'RestrictionionPresent/true' ]);
			break;

		// [MS-OXCDATA] 2.12.12 CountRestrictionion Structure
		case self::RESTRICTION_OP['Count']:
			$p  = [ 'T' => 'I', 'S' => '4' ];
			$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('Count', self::_getInt(4), false, $p);
			self::_getRestriction($xml, true);
			break;
		}

		$xml->restorePos($op);

		return true;
	}

	/**
	 * 	Get <TaggedPropertyValue> structure
	 *
	 * 	@param 	- XML to store structure
	 * 	@return - true = Ok; false = Error
	 */
	private function _getTaggedProperty(XML &$xml): bool {

		$op = $xml->savePos();

		// [MS-OXCDATA] 2.11.4 TaggedPropertyValue Structure
		$xml->addvar('TaggedPropertyValue');

		// [MS-OXCDATA] 2.9 PropertyTag Structure
		$p  = [ 'T' => 'I', 'S' => '2', 'D' => 'DATA_TYP' ];
		$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
		$a = array_flip(mapiDefs::DATA_TYP);
		if (isset($a[$n = self::_getInt(2)]))
			$t = $a[$n];
		else {

			$t = sprintf('##DATA_TYP:0x%04X', $n);
			if (self::$_obj->_cnf->getVar(Config::DBG_SCRIPT))
				Msg::WarnMsg('Uknown DATA_TYP ['.sprintf('%02X %d', $n, $n).']');
			$this->_err++;
		}
		$xml->addVar('Type', $t, false, $p);

		$p  = [ 'T' => 'I', 'S' => '2', 'D' => 'PID' ];
		$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
		$a = array_flip(mapiDefs::PID);
		if (isset($a[$n = self::_getInt(2)]))
			$s = $a[$n];
		else {

			$s = sprintf('##PID:0x%04X', $n);
			if (self::$_obj->_cnf->getVar(Config::DBG_SCRIPT))
				Msg::WarnMsg('Uknown PID ['.sprintf('%02X %d', $n, $n).']');
			$this->_err++;
		}
		$xml->addVar('Id', $s, false, $p);

		// [MS_OXCMAPIHTTP] 2.2.1.1 AddressBookPropertyValue Structure
		$d  = '';
		if ($t == 'S' || $t == 'A' || $t == 'H' || substr($t, 0, 2) == 'M_') {

			$p  = [ 'T' => 'B' ];
			$p += [ 'P' => $d = sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('HasValue', self::_getData($xml, 'B', 1, $d)[0], false, $p);

			// [MS-OXCDATA] 2.11.2.1 PropertyValue Structure
			$p  = [ 'V' => 'Type', 'C' => 'HasValue=true' ];
			$p += [ 'P' => $d = sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('Value', self::_getData($xml, $t, -1, $d)[0], false, $p);
		} else {

			// [MS-OXCDATA] 2.11.2.1 PropertyValue Structure
			$p  = [ 'V' => 'Type' ];
			$p += [ 'P' => $d = sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
			$xml->addVar('Value', self::_getData($xml, $t, -1, $d)[0], false, $p);
		}

		$xml->restorePos($op);

		return true;
	}

	/**
	 * 	Get <PropertyRow> structure
	 * 	Caution: Position in request body must be properly set!
	 *
	 * 	@param 	- XML to store structure
	 * 	@param 	- XML request body
	 * 	@return - true = Ok; false = Error
	 */
	private function _getPropertyRow(XML &$xml, XML &$req): bool {

		$op = $xml->savePos();
		$req->xpath('//PropertyTag');

		// [MS-OXCDATA] 2.8.1 PropertyRow Structures
		// <PropertyRow> is set in skeleton

		$mod = self::_convConst(1, 'VALUE_TYP', self::_getInt(1));
		$p   = [ 'T' => 'I', 'S' => '1', 'D' => 'VALUE_TYP' ];
		$p   += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
		$xml->addVar('Flag', $mod, false, $p);

		while ($req->getItem() !== null) {

			// get data type from request buffer
			$p   = $req->savePos();
			$typ = $req->getVar('PropertyType', false);
			$req->restorePos($p);
			$id = $req->getVar('PropertyId', false);

			$p  = [];
			$p  += [ 'Tag' => $id ];
			$xml->addVar('PropetyValue', null, false, $p);
			$vp = $xml->savePos();

			$err = false;
			$len = -1;
			$d   = sprintf('0x%X (%d)', self::$_pos, self::$_pos);

			$xml->restorePos($vp);
			switch ($mod) {
			// [MS-OXCDATA] 2.8.1.1 StandardPropertyRow Structure
			case 'Implied':
				if ($typ == 'Unspecific') {
					// [MS-OXCDATA] 2.11.3 TypedPropertyValue Structure
					$v = self::_convConst(1, 'DATA_TYP', self::_getInt(2));
					$p =  [ 'T' => 'I', 'S' => '2', 'D' => 'DATA_TYP' ];
					$p += [ 'Tag' => $id, 'P' => $d ];
					$xml->addVar('PropertyType', $typ, false, $p);
					return true;
				}
				switch ($typ) {
				case 'I2':
				case 'I4':
				case 'I8':
					$len = intval(substr($typ, 1));
					$typ = 'I';

				default:
					break;
				}
				break;

			// [MS-OXCDATA] 2.8.1.2 FlaggedPropertyRow Structure
			case 'Flagged':
				if ($typ == 'Unspecific')
					return true;

				// [MS-OXCDATA] 2.11.5 FlaggedPropertyValue Structure
				$v = self::_convConst(1, 'VALUE_TYP', self::_getInt(1));
				$p =  [ 'T' => 'I', 'S' => '1', 'D' => 'VALUE_TYP' ];
				$p += [ 'P' => $d ];
				$xml->addVar('Flag', $v, false, $p);
				if ($v != 'Error') {
					switch ($typ) {
					case 'I2':
					case 'I4':
					case 'I8':
						$len = intval(substr($typ, 1));
						$typ = 'I';

					default:
						break;
					}
					break;
				}

			case 'Error':
				$typ = 'I';
				$len = 4;
				$err = true;
				break;

			default:
				return true;
			}

			if ($typ == 'H' || substr($typ, 0, 2) == 'M_') {

				$p =  [ 'T' => 'I', 'S' => '2' ];
				$p += [ 'P' => sprintf('0x%X (%d)', self::$_pos, self::$_pos) ];
				$xml->addVar('Count', $len = self::_getInt(2), false, $p);
				$len = intval($len);
			}

			// [MS-OXCDATA] 2.11.2.1 PropertyValue Structure
			$d = sprintf('0x%X (%d)', self::$_pos, self::$_pos);
			$v = self::_getData($xml, $typ, $len)[0];
			$p = [ 'T' => $typ ];
			if ($len > -1)
				$p += [ 'S' => $len ];
			if ($err) {

				$v = self::_convConst(1, 'ERR_CODE', $v);
				$p += [ 'T' => 'I', 'S' => '4', 'D' => 'ERR_CODE' ];
			}
			$p += [ 'P' => $d ];
			$xml->addVar('Value', $v, false, $p);

			$xml->restorePos($op);
		}

		return true;
	}

}
