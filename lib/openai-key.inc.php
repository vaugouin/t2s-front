<?php
//The visitor's OpenAI key, in the demo mode only (lib/byok.inc.php). Included by the shell
//html/app/openai-key.php.
//
//	GET               the key form; ?return=index.php?search=... is where to come back to
//	POST action=save  checks the key with OpenAI, stores it in an HttpOnly cookie limited to the
//	                  demo prefixes (a session cookie, or 30 days with "remember"), then sends
//	                  the visitor back with a 303
//	POST action=forget  removes the cookie
//
//Under any other prefix the page does not exist (404): the site's own key is never replaced there.

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
include_once(__DIR__."/byok.inc.php");

if (!$intbyok) {
	http_response_code(404);
	header("Content-Type: text/plain; charset=utf-8");
	echo "404 Not Found";
	exit;
}

$arrbyokcookiepaths=f_byokcookiepaths(f_prefixmap(),$strphpself);
$strbyokhost=strtolower((string)(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ""));
//Secure everywhere except on a local checkout served in plain HTTP. Behind the reverse proxy the
//site is HTTPS even though PHP itself only sees HTTP, so the flag cannot be read from the request.
$intbyoksecure=!preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/',$strbyokhost);
$strbyokmethod=strtoupper((string)(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : "GET"));
$strbyokreturn=f_byokreturnurl(isset($_POST['return']) ? $_POST['return'] : (isset($_GET['return']) ? $_GET['return'] : "index.php"));
$strbyokstatus="";

function f_byoksetcookie($strvalue,$lngexpires) {
	//One cookie per demo prefix (f_byokcookiepaths()): the key follows the language switch between
	//demo prefixes and never reaches a regular one.
	global $arrbyokcookiepaths,$intbyoksecure;
	foreach ($arrbyokcookiepaths as $strpath) {
		setcookie(f_byokcookiename(),$strvalue,array(
			"expires" => $lngexpires,
			"path" => $strpath,
			"secure" => (bool)$intbyoksecure,
			"httponly" => true,
			"samesite" => "Lax"
		));
	}
}

header("Cache-Control: no-store");

if ($strbyokmethod=="POST") {
	$strbyokaction=(isset($_POST['action']) ? (string)$_POST['action'] : "");
	if ($strbyokaction=="forget") {
		f_byoksetcookie("",time()-3600);
		$stropenaiapikey="";
		$strbyokstatus=get_movie_string('lib_byok_forgotten');
	} elseif ($strbyokaction=="save") {
		$strbyokkey=trim((string)(isset($_POST['openaikey']) ? $_POST['openaikey'] : ""));
		if (!f_byokkeywellformed($strbyokkey)) {
			$strbyokstatus=get_movie_string('lib_byok_malformed');
		} else {
			$strbyokcheck=f_byokcheckkey($strbyokkey);
			if ($strbyokcheck=="ok") {
				$intbyokremember=(isset($_POST['remember']) && (string)$_POST['remember']=="1");
				f_byoksetcookie($strbyokkey,$intbyokremember ? time()+30*24*3600 : 0);
				http_response_code(303);
				header("Location: ".$strbyokreturn);
				exit;
			}
			$strbyokstatus=get_movie_string($strbyokcheck=="refused" ? 'lib_byok_refused' : 'lib_byok_unreachable');
		}
		unset($strbyokkey);
	}
}

f_headerdisplay(get_movie_string('lib_byok_title'));
echo f_byokformhtml($strbyokreturn,$strbyokstatus,($stropenaiapikey!="" ? 1 : 0));
if ($stropenaiapikey!="" && $strbyokstatus=="") {
	echo "<p>".htmlspecialchars(get_movie_string('lib_byok_stored'),ENT_QUOTES,'UTF-8')." <a href=\"".f_htmlattr($strbyokreturn)."\">".htmlspecialchars(get_movie_string('lib_byok_back'),ENT_QUOTES,'UTF-8')."</a></p>";
}
f_footerdisplay();
?>
