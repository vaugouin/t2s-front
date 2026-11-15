<?php
//Bring your own OpenAI key: the demo mode of the front.
//
//A URL prefix flagged byok in T2S_URL_PREFIXES (lib/private-mode.inc.php), for example
//
//	T2S_URL_PREFIXES=/en/:en,/fr/:fr,/demo/:en:byok
//
//serves the public pages, but every question sent to the Text2SQL API is paid with the visitor's
//own OpenAI key, never with OPENAI_API_KEY from .env. The visitor types the key once on
//openai-key.php; it is checked with OpenAI (one free call that lists the models), then kept in an
//HttpOnly cookie limited to the prefix, so no script of the page can read it. global-light.inc.php
//reads it back on every request and f_movietext2sqlsearch() sends it in the X-OpenAI-Key header.
//Without a key, the search page shows the key form instead of calling the API.
//
//The key is never written to a log, a file or the database, and never printed back into a page.
//
//The functions below have no side effect: test/byok-test.php checks them without a web server.

if (!function_exists('f_byokcookiename')) {
	function f_byokcookiename() {
		//Returns: name of the cookie that carries the visitor's key
		return "t2sopenaikey";
	}
}

if (!function_exists('f_byokkeywellformed')) {
	function f_byokkeywellformed($strkey) {
		//Purpose: Can this string be an OpenAI key? Shape only: OpenAI decides whether it is valid.
		//Params:	$strkey: candidate key
		//Returns: true when it is 20 to 512 letters, digits, dashes or underscores (sk-..., sk-proj-...)
		return is_string($strkey) && preg_match('/^[A-Za-z0-9_-]{20,512}$/',$strkey)===1;
	}
}

if (!function_exists('f_byokcookiekey')) {
	function f_byokcookiekey($arrcookies) {
		//Purpose: The visitor's key, read from the request cookies
		//Params:	$arrcookies: $_COOKIE
		//Returns: the key when the cookie holds a well-formed one, "" otherwise
		$strname=f_byokcookiename();
		if (!is_array($arrcookies) || !isset($arrcookies[$strname])) {
			return "";
		}
		$strkey=trim((string)$arrcookies[$strname]);
		return (f_byokkeywellformed($strkey) ? $strkey : "");
	}
}

if (!function_exists('f_byokcookiepath')) {
	function f_byokcookiepath($strphpself) {
		//Purpose: Path of the cookie: the folder of the page, so the key never travels to another prefix
		//Params:	$strphpself: PHP_SELF of the request, such as /demo/openai-key.php
		//Returns: /demo/ style path, "/" when the page sits at the root
		$strdir=str_replace("\\","/",dirname((string)$strphpself));
		$strdir=rtrim($strdir,"/");
		return ($strdir=="" || $strdir==".") ? "/" : $strdir."/";
	}
}

if (!function_exists('f_byokcookiepaths')) {
	function f_byokcookiepaths($arrmap,$strphpself) {
		//Purpose: Every path the key cookie is written to: one per demo prefix, so a visitor who
		//			switches language (/demo/ to /demo-fr/) keeps their key, while the key still never
		//			travels to a regular prefix, where the site's own key is used
		//Params:	$arrmap: prefix map (f_prefixmap())
		//			$strphpself: PHP_SELF of the request, the fallback when the map lists no demo prefix
		//Returns: list of /demo/ style paths, never empty
		$arrpaths=array();
		if (is_array($arrmap)) {
			foreach ($arrmap as $arrentry) {
				if (!empty($arrentry["byok"]) && empty($arrentry["private"]) && isset($arrentry["prefix"]) && $arrentry["prefix"]!="") {
					$arrpaths[]=$arrentry["prefix"];
				}
			}
		}
		if (count($arrpaths)==0) {
			$arrpaths[]=f_byokcookiepath($strphpself);
		}
		return array_values(array_unique($arrpaths));
	}
}

if (!function_exists('f_byokopenaimodels')) {
	function f_byokopenaimodels($arrmodels) {
		//Purpose: The models a demo visitor may pick: OpenAI ones only, since any other provider
		//			would be paid with the site's own key, not with the visitor's
		//Params:	$arrmodels: list of model names
		//Returns: the same list without the names that do not start with gpt- or o<digit>
		$arrkept=array();
		foreach ((array)$arrmodels as $strmodel) {
			if (preg_match('/^(gpt-|o[0-9])/',(string)$strmodel)===1) {
				$arrkept[]=$strmodel;
			}
		}
		return $arrkept;
	}
}

if (!function_exists('f_byokreturnurl')) {
	function f_byokreturnurl($strurl) {
		//Purpose: Where to send the visitor once the key is saved, without ever leaving the prefix
		//Params:	$strurl: the return parameter, as received
		//Returns: a page of the same folder with its query string (index.php?search=...), or
		//			index.php when the value is anything else: an absolute URL, a path, a scheme,
		//			a line break. This is what keeps the page from being an open redirect.
		$strurl=(string)$strurl;
		if (preg_match('/^[A-Za-z0-9._-]+\.php(\?[^\s\\\\]*)?$/',$strurl)!==1) {
			return "index.php";
		}
		return $strurl;
	}
}

if (!function_exists('f_byokrequestpage')) {
	function f_byokrequestpage($strrequesturi) {
		//Purpose: The current page and query string, as a return value for the key form
		//Params:	$strrequesturi: REQUEST_URI of the request, such as /demo/index.php?search=x
		//Returns: index.php?search=x style value, already passed through f_byokreturnurl()
		$strrequesturi=(string)$strrequesturi;
		$lngquery=strpos($strrequesturi,"?");
		$strpath=($lngquery===false ? $strrequesturi : substr($strrequesturi,0,$lngquery));
		$strquery=($lngquery===false ? "" : substr($strrequesturi,$lngquery));
		$strpage=basename($strpath);
		if ($strpage=="" || substr($strpath,-1)=="/") {
			$strpage="index.php";
		}
		return f_byokreturnurl($strpage.$strquery);
	}
}

if (!function_exists('f_byokcheckkey')) {
	function f_byokcheckkey($strkey,$lngtimeoutseconds=20) {
		//Purpose: Ask OpenAI whether the key is accepted, with the one free call that lists the models
		//Params:	$strkey: the key typed by the visitor, already well formed
		//Returns: "ok", "refused" (OpenAI said 401 or 403) or "unreachable" (anything else)
		if (!function_exists('curl_init')) {
			return "unreachable";
		}
		$objcurl=curl_init();
		curl_setopt_array($objcurl,array(
			CURLOPT_URL => "https://api.openai.com/v1/models",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => $lngtimeoutseconds,
			CURLOPT_HTTPHEADER => array("Authorization: Bearer ".$strkey)
		));
		curl_exec($objcurl);
		$lnghttpcode=(int)curl_getinfo($objcurl,CURLINFO_HTTP_CODE);
		curl_close($objcurl);
		if ($lnghttpcode>=200 && $lnghttpcode<300) {
			return "ok";
		}
		if ($lnghttpcode==401 || $lnghttpcode==403) {
			return "refused";
		}
		return "unreachable";
	}
}

if (!function_exists('f_byokformhtml')) {
	function f_byokformhtml($strreturn,$strstatus="",$intstored=0) {
		//Purpose: The key form, shown by openai-key.php and by the search page when a question
		//			arrives without a key
		//Params:	$strreturn: page to come back to (f_byokreturnurl() is applied again)
		//			$strstatus: message to show above the field, already translated, "" for none
		//			$intstored: 1 when a key is already stored, to offer "Forget my key"
		//Returns: HTML. Needs get_movie_string() and f_htmlattr(), so global-light.inc.php and
		//			language.inc.php are loaded first.
		$strreturn=f_byokreturnurl($strreturn);
		$strhtml="<div class=\"t2s-byok\">";
		$strhtml.="<h2>".htmlspecialchars(get_movie_string('lib_byok_title'),ENT_QUOTES,'UTF-8')."</h2>";
		$strhtml.="<p>".htmlspecialchars(get_movie_string('lib_byok_intro'),ENT_QUOTES,'UTF-8')."</p>";
		if ($strstatus!="") {
			$strhtml.="<p class=\"t2s-byok-status\" role=\"status\"><strong>".htmlspecialchars($strstatus,ENT_QUOTES,'UTF-8')."</strong></p>";
		}
		$strhtml.="<form method=\"POST\" action=\"openai-key.php\" autocomplete=\"off\">";
		$strhtml.="<input type=\"hidden\" name=\"action\" value=\"save\">";
		$strhtml.="<input type=\"hidden\" name=\"return\" value=\"".f_htmlattr($strreturn)."\">";
		$strhtml.="<p><label for=\"t2sbyokkey\">".htmlspecialchars(get_movie_string('lib_byok_label'),ENT_QUOTES,'UTF-8')."</label><br>";
		$strhtml.="<input type=\"password\" id=\"t2sbyokkey\" name=\"openaikey\" size=\"40\" maxlength=\"512\" placeholder=\"sk-...\" autocomplete=\"off\" autocapitalize=\"off\" spellcheck=\"false\" required></p>";
		$strhtml.="<p><label><input type=\"checkbox\" name=\"remember\" value=\"1\"> ".htmlspecialchars(get_movie_string('lib_byok_remember'),ENT_QUOTES,'UTF-8')."</label></p>";
		$strhtml.="<p><input type=\"submit\" value=\"".f_htmlattr(get_movie_string('lib_byok_save'))."\"></p>";
		$strhtml.="</form>";
		if ($intstored) {
			$strhtml.="<form method=\"POST\" action=\"openai-key.php\">";
			$strhtml.="<input type=\"hidden\" name=\"action\" value=\"forget\">";
			$strhtml.="<input type=\"hidden\" name=\"return\" value=\"".f_htmlattr($strreturn)."\">";
			$strhtml.="<p><input type=\"submit\" value=\"".f_htmlattr(get_movie_string('lib_byok_forget'))."\"></p>";
			$strhtml.="</form>";
		}
		$strhtml.="<p><small>".htmlspecialchars(get_movie_string('lib_byok_privacy'),ENT_QUOTES,'UTF-8')."</small></p>";
		$strhtml.="<p><a href=\"https://platform.openai.com/api-keys\" target=\"_blank\" rel=\"noopener noreferrer\">".htmlspecialchars(get_movie_string('lib_byok_get_key'),ENT_QUOTES,'UTF-8')."</a></p>";
		$strhtml.="</div>";
		return $strhtml;
	}
}
?>
