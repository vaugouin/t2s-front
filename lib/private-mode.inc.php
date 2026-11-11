<?php
//URL prefixes of the site: the single place that knows which prefix means which language, and
//which prefix is private.
//
//The same pages are served under several URL prefixes (README, "URL prefixes"). The list is
//configuration, read from .env:
//
//	T2S_URL_PREFIXES=/en/:en,/fr/:fr,/staff/:en:private
//	T2S_DEFAULT_LANG=en
//
//Each entry is prefix:language, followed by :private when the prefix is a private one. No folder
//name is written in the code. A request under a prefix that is not listed is public and uses the
//default language.
//
//Private mode is decided here and nowhere else. The application authenticates nobody: the web
//server is expected to put the private prefixes behind authentication.
//
//A page that connects with the read-write database account ($intdbreadwrite=true) must never run
//under a public prefix. global-light.inc.php opens the database connection when it loads, long
//before it sets $intprivate, so such a page cannot test $intprivate first. This file has no side
//effect, opens no connection, and is loaded BEFORE global-light:
//
//	include_once(__DIR__."/private-mode.inc.php");
//	f_privatemoderequire();
//	$intdbreadwrite=true;
//	include(__DIR__."/global-light.inc.php");
//
//global-light.inc.php reads the same map to set $intprivate, the language and the language
//switch URL, and it calls f_privatemoderequire() itself whenever $intdbreadwrite is true, as a
//second lock for a page that would forget the lines above.
//
//Loadable on its own: test/private-mode-test.php checks it without a database.

include_once(__DIR__."/env.inc.php");

if (!function_exists('f_prefixlanguages')) {
	function f_prefixlanguages() {
		//Returns: the interface languages the front knows, the first one being the fallback
		return array("en","fr");
	}
}

if (!function_exists('f_prefixnormalize')) {
	function f_prefixnormalize($strprefix) {
		//Purpose: Give a prefix its canonical shape, one slash on each side
		//Params:	$strprefix: prefix as written in the configuration, such as en, /en or /en/
		//Returns: /en/ style prefix, "" when nothing usable is left
		$strprefix=trim(trim((string)$strprefix),"/");
		if ($strprefix=="" || !preg_match('/^[A-Za-z0-9._~\/-]+$/',$strprefix)) {
			return "";
		}
		return "/".$strprefix."/";
	}
}

if (!function_exists('f_prefixdefaultlang')) {
	function f_prefixdefaultlang($strdefaultlang=null) {
		//Purpose: Language of a request whose prefix is not in the map
		//Params:	$strdefaultlang: value to validate; null reads T2S_DEFAULT_LANG from the environment
		//Returns: a known language code, the first known language when the value is not one
		$arrlanguages=f_prefixlanguages();
		if ($strdefaultlang===null) {
			f_envinit();
			$strdefaultlang=env('T2S_DEFAULT_LANG','');
		}
		$strdefaultlang=strtolower(trim((string)$strdefaultlang));
		if (!in_array($strdefaultlang,$arrlanguages,true)) {
			$strdefaultlang=$arrlanguages[0];
		}
		return $strdefaultlang;
	}
}

if (!function_exists('f_prefixparse')) {
	function f_prefixparse($strconfig,$strdefaultlang=null) {
		//Purpose: Read the prefix list of the configuration
		//Params:	$strconfig: value of T2S_URL_PREFIXES, entries separated by commas, each one
		//			prefix:language[:private]
		//			$strdefaultlang: language of an entry that names none or an unknown one
		//Returns: list of array("prefix"=>"/en/","lang"=>"en","private"=>0), in the order written.
		//			An entry without a usable prefix is skipped, and so is a prefix already listed.
		$strdefaultlang=f_prefixdefaultlang($strdefaultlang);
		$arrlanguages=f_prefixlanguages();
		$arrmap=array();
		$arrseen=array();
		foreach (explode(",",(string)$strconfig) as $strentry) {
			$arrparts=explode(":",trim($strentry));
			$strprefix=f_prefixnormalize($arrparts[0]);
			if ($strprefix=="" || isset($arrseen[$strprefix])) {
				continue;
			}
			$strlang=(isset($arrparts[1]) ? strtolower(trim($arrparts[1])) : "");
			if (!in_array($strlang,$arrlanguages,true)) {
				$strlang=$strdefaultlang;
			}
			$intentryprivate=0;
			for ($lngpart=2; $lngpart<count($arrparts); $lngpart++) {
				if (strtolower(trim($arrparts[$lngpart]))=="private") {
					$intentryprivate=1;
				}
			}
			$arrseen[$strprefix]=1;
			$arrmap[]=array("prefix"=>$strprefix,"lang"=>$strlang,"private"=>$intentryprivate);
		}
		return $arrmap;
	}
}

if (!function_exists('f_prefixmap')) {
	function f_prefixmap($arrmap=null) {
		//Purpose: The prefix map of the site
		//Params:	$arrmap: a map to install in place of the configured one (tests); null to read
		//Returns: the map, read once from T2S_URL_PREFIXES
		static $arrcurrent=null;
		if (is_array($arrmap)) {
			$arrcurrent=$arrmap;
		}
		if ($arrcurrent===null) {
			f_envinit();
			$arrcurrent=f_prefixparse(env('T2S_URL_PREFIXES',''));
		}
		return $arrcurrent;
	}
}

if (!function_exists('f_pathinfolder')) {
	function f_pathinfolder($strpath,$strfolder) {
		//Purpose: Is this script path located under this URL prefix?
		//Params:	$strpath: script path, PHP_SELF style, such as /en/movie.php
		//			$strfolder: prefix with both slashes, such as /en/
		//Returns: true or false. The comparison is case sensitive, like the URL itself.
		$strpath=(string)$strpath;
		$strfolder=(string)$strfolder;
		if ($strfolder=="") {
			return false;
		}
		return (substr($strpath,0,strlen($strfolder))==$strfolder);
	}
}

if (!function_exists('f_prefixofpath')) {
	function f_prefixofpath($strpath,$arrmap=null,$strdefaultlang=null) {
		//Purpose: The map entry a script path belongs to
		//Params:	$strpath: script path, PHP_SELF style
		//			$arrmap: prefix map, the configured one when null
		//			$strdefaultlang: language of an unlisted prefix, T2S_DEFAULT_LANG when null
		//Returns: the entry of the longest listed prefix the path starts with. When none matches:
		//			array("prefix"=>"","lang"=>default language,"private"=>0), so an unknown prefix
		//			is public. Private mode is never the default.
		if (!is_array($arrmap)) {
			$arrmap=f_prefixmap();
		}
		$arrfound=array("prefix"=>"","lang"=>f_prefixdefaultlang($strdefaultlang),"private"=>0);
		foreach ($arrmap as $arrentry) {
			if (f_pathinfolder($strpath,$arrentry["prefix"]) && strlen($arrentry["prefix"])>strlen($arrfound["prefix"])) {
				$arrfound=$arrentry;
			}
		}
		return $arrfound;
	}
}

if (!function_exists('f_prefixalternate')) {
	function f_prefixalternate($arrentry,$arrmap=null) {
		//Purpose: Where the language switch of the footer leads
		//Params:	$arrentry: entry of the current request, from f_prefixofpath()
		//			$arrmap: prefix map, the configured one when null
		//Returns: the first PUBLIC entry in another language, or null when the map has none. A
		//			private prefix is never offered as a switch target: its link would only raise a
		//			password prompt.
		if (!is_array($arrmap)) {
			$arrmap=f_prefixmap();
		}
		foreach ($arrmap as $arrother) {
			if (!$arrother["private"] && $arrother["lang"]!=$arrentry["lang"]) {
				return $arrother;
			}
		}
		return null;
	}
}

if (!function_exists('f_prefixstate')) {
	function f_prefixstate($strpath,$strrequesturi,$arrmap=null,$strdefaultlang=null) {
		//Purpose: Everything a page needs to know from its URL prefix
		//Params:	$strpath: script path, PHP_SELF style
		//			$strrequesturi: REQUEST_URI of the request, the URL the language switch rewrites
		//			$arrmap, $strdefaultlang: see f_prefixofpath()
		//Returns: array with
		//			"prefix": the matching prefix, "" when unlisted
		//			"private": 1 or 0
		//			"lang": interface language
		//			"langalt": language of the switch, "" when there is nowhere to switch to
		//			"langalturl": URL of the same page under the other language, "" likewise
		$arrentry=f_prefixofpath($strpath,$arrmap,$strdefaultlang);
		$arrstate=array("prefix"=>$arrentry["prefix"],"private"=>($arrentry["private"] ? 1 : 0),"lang"=>$arrentry["lang"],"langalt"=>"","langalturl"=>"");
		$arralternate=f_prefixalternate($arrentry,$arrmap);
		$strrequesturi=(string)$strrequesturi;
		if ($arralternate!==null && $arrentry["prefix"]!="" && f_pathinfolder($strrequesturi,$arrentry["prefix"])) {
			$arrstate["langalt"]=$arralternate["lang"];
			$arrstate["langalturl"]=$arralternate["prefix"].substr($strrequesturi,strlen($arrentry["prefix"]));
		}
		return $arrstate;
	}
}

if (!function_exists('f_privatemodepath')) {
	function f_privatemodepath($strpath,$arrmap=null) {
		//Purpose: Is this script path served in private mode?
		//Params:	$strpath: script path, PHP_SELF style
		//			$arrmap: prefix map, the configured one when null
		//Returns: 1 when the path is under a private prefix, 0 otherwise
		$arrentry=f_prefixofpath($strpath,$arrmap,"en");
		return ($arrentry["private"] ? 1 : 0);
	}
}

if (!function_exists('f_privatemoderequest')) {
	function f_privatemoderequest() {
		//Purpose: Is the current request served in private mode?
		//Returns: 1 or 0. Reads PHP_SELF, the value global-light.inc.php uses.
		$strphpself="";
		if (isset($_SERVER['PHP_SELF'])) {
			$strphpself=(string)$_SERVER['PHP_SELF'];
		}
		return f_privatemodepath($strphpself);
	}
}

if (!function_exists('f_privatemoderequire')) {
	function f_privatemoderequire() {
		//Purpose: Stop the request with HTTP 403 unless it is served in private mode
		//Returns: nothing when the request is private. Otherwise it answers 403 with a short
		//			text and exits: no database connection is opened and nothing is written.
		if (f_privatemoderequest()) {
			return;
		}
		if (!headers_sent()) {
			http_response_code(403);
			header("Content-Type: text/plain; charset=utf-8");
			header("Cache-Control: no-store");
		}
		echo "403 Forbidden: this page is only available in private mode.";
		exit;
	}
}
?>
