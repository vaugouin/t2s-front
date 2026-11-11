<?php
/*
 * api-version.inc.php - the running version of a Text2SQL API colour
 *
 * The versions used to be typed by hand in lib/global-light.inc.php, and they drifted: Green
 * ran 1.1.19 while the front still said 1.1.17. That number is not cosmetic: the samples panel
 * of the search page (lib/text2sql-samples.inc.php) keeps only the samples that have an
 * evaluation run ON THAT VERSION, so a stale string silently empties or skews it after a deploy.
 *
 * The source is now GET / of the API, whose api_version field is the
 * raw strapiversion ("1.1.19"). f_text2sqlapiversion() in global-light.inc.php makes the call;
 * this file holds the parts that need neither a network nor a database, so that
 * test/api-version-test.php can load it alone: reading the answer, and the small file cache that
 * spares the search page a round trip on every load.
 */

//Five minutes: long enough to cost nothing on a busy page, short enough that a deploy shows
//up before anyone wonders why the page still names the previous version.
define('TEXT2SQL_API_VERSION_CACHE_TTL', 300);

function f_apiversionisvalid($strversion) {
	//Purpose: Whether a string is a version as the API writes it ("1.1.19")
	//Returns: true or false. The zero-padded "001.001.019" cache-key form is refused on purpose.
	return is_string($strversion) && preg_match('/^(0|[1-9]\d{0,3})\.(0|[1-9]\d{0,4})\.(0|[1-9]\d{0,5})$/', $strversion)===1;
}

function f_apiversionfromresponse($strresponse) {
	//Purpose: Extracts api_version from the body of GET /
	//Params:	$strresponse: Raw response body, or false when the call failed
	//Returns: The version, or "" when the body is not JSON or carries no valid version
	if (!is_string($strresponse) || $strresponse=="") {
		return "";
	}
	$arrresponse=json_decode($strresponse,true);
	if (!is_array($arrresponse) || !isset($arrresponse["api_version"])) {
		return "";
	}
	$strversion=trim((string)$arrresponse["api_version"]);
	return f_apiversionisvalid($strversion) ? $strversion : "";
}

function f_apiversioncachefile($strcolor, $strbaseurl, $strdir="") {
	//Purpose: Path of the cache file of one colour
	//Params:	$strbaseurl: Part of the key, so that a changed API URL never reads the old answer
	//			$strdir: Directory, the system temp directory by default
	//Returns: Absolute path
	if ($strdir=="") {
		$strdir=sys_get_temp_dir();
	}
	return rtrim($strdir,"/\\").DIRECTORY_SEPARATOR."t2s-front-api-version-".md5($strcolor."|".$strbaseurl).".txt";
}

function f_apiversioncacheread($strfile, $lngnow, $lngttl=TEXT2SQL_API_VERSION_CACHE_TTL) {
	//Purpose: Reads a cached version if it is still fresh
	//Params:	$lngnow: Current timestamp, passed in so the test controls the clock
	//Returns: The version, or "" when absent, stale, unreadable or invalid
	if (!is_file($strfile)) {
		return "";
	}
	$lngmtime=@filemtime($strfile);
	if ($lngmtime===false || $lngnow-$lngmtime>=$lngttl) {
		return "";
	}
	$strversion=trim((string)@file_get_contents($strfile));
	return f_apiversionisvalid($strversion) ? $strversion : "";
}

function f_apiversioncachewrite($strfile, $strversion) {
	//Purpose: Stores a version; a write that fails only costs the next request a call
	//Returns: true when written
	if (!f_apiversionisvalid($strversion)) {
		return false;
	}
	return @file_put_contents($strfile, $strversion, LOCK_EX)!==false;
}
?>
