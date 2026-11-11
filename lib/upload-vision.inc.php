<?php
//Same-origin shim between the browser and POST /uploads/vision of the Text2SQL API
//. Included by the two-line shell html/app/upload-vision.php.
//
//WHY THIS FILE EXISTS AT ALL. TEXT2SQL_API_KEY_VALUE is a server secret (global-light.inc.php, from
//the git-ignored lib/database/global-light-secrets.inc.php) and the two colours of the API listen on
//their own ports. A fetch() straight from the page to the API would publish that key in the source
//of every page that carries the search box. So the bytes go to this PHP file, on the same origin as
//the page, and the key is added here, server side. The browser never learns the API URL either.
//
//Two methods, one contract:
//  POST, body = the image itself (not multipart, not base64) -> JSON {image_ref, bytes, ...}
//  GET  ?imageref=<ref> -> the image bytes, so the thumbnail survives a page load
//
//Answers JSON on every error, with a message already translated, because the caller writes it
//straight into the page.

//The includes run inside an output buffer, and what they printed is thrown away rather than sent.
//This endpoint answers JSON or raw image bytes, and both are ruined by a single stray byte in
//front: a PHP notice would turn the JSON into something the caller cannot parse, corrupt an image,
//and, worse, commit the headers before a status code or a Content-Type could be set. A badly
//configured deployment must degrade into a clean error here, not into a page of warnings.
//Everything discarded goes to the error log, where it belongs.
ob_start();
include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
include(__DIR__."/vision-image.inc.php");
if (session_status()===PHP_SESSION_NONE) {
	//Started here, still inside the buffer, because session_start() cannot run once a single byte
	//has been sent. The throttle below reads the session.
	session_start();
}
$strvisionstraynoise=ob_get_clean();
if ($strvisionstraynoise!=="") {
	error_log("picture search: the vision upload shim discarded ".strlen($strvisionstraynoise)." bytes printed while loading: ".substr(trim(strip_tags($strvisionstraynoise)),0,500));
}

//The colour is read exactly as the search page reads it (parameter, then cookie, then the
//configured default), so an image is deposited on the same deployment the question will go to.
//Blue and green share the uploads/ mount on the VPS, so a mismatch is survivable, but a laptop
//checkout has no such mount and the read back would then 404.
$strvisionapicolor=isset($_GET['apicolor'])?trim((string)$_GET['apicolor']):"";
if ($strvisionapicolor==="" && isset($_COOKIE['apicolor'])) {
	$strvisionapicolor=trim((string)$_COOKIE['apicolor']);
}
if ($strvisionapicolor!=="Blue" && $strvisionapicolor!=="Green") {
	$strvisionapicolor=isset($strtext2sqlapicolor)?(string)$strtext2sqlapicolor:"Green";
}
$strvisionapibaseurl=f_visionapibaseurl(
	$strvisionapicolor,
	isset($strtext2sqlapibluedomainurl)?$strtext2sqlapibluedomainurl:"",
	isset($strtext2sqlapigreendomainurl)?$strtext2sqlapigreendomainurl:""
);
$strvisionapikeyname=isset($strtext2sqlapikeyname)?(string)$strtext2sqlapikeyname:"";
$strvisionapikeyvalue=isset($strtext2sqlapikeyvalue)?(string)$strtext2sqlapikeyvalue:"";
$strvisionmethod=isset($_SERVER['REQUEST_METHOD'])?strtoupper((string)$_SERVER['REQUEST_METHOD']):"GET";

if (!function_exists('curl_init')) {
	//The container that runs the site has the extension, the search page could not call the API at
	//all without it. A checkout that does not is a configuration fault, and it must read as one
	//instead of ending in an uncaught Error printed into the response: the output buffer above is
	//already closed by the time the call happens, so a fatal here would reach the caller raw.
	error_log("picture search: the curl extension is missing, the vision upload shim cannot reach the API");
	f_visionjsonresponse(500,array("error" => get_movie_string('lib_t2s_image_error_upload')));
}

if ($strvisionapibaseurl==="" || $strvisionapikeyname==="" || $strvisionapikeyvalue==="") {
	//A missing secret is a deployment fault, not a visitor fault: say so in the log, stay vague in
	//the page, and never echo any part of the key or of the API URL.
	error_log("picture search: the Text2SQL API is not configured for the vision upload shim");
	f_visionjsonresponse(500,array("error" => get_movie_string('lib_t2s_image_error_upload')));
}

if ($strvisionmethod==="GET") {
	//Read back, for the thumbnail of an image that was deposited on an earlier page load. The
	//reference is validated against the house pattern before a URL is built from it, so nothing a
	//client invents can travel; $_GET is read raw for that reason, the whitelist being the filter.
	$strvisionimageref=isset($_GET['imageref'])?trim((string)$_GET['imageref']):"";
	if (!f_visionimagerefisvalid($strvisionimageref)) {
		f_visionjsonresponse(400,array("error" => get_movie_string('lib_t2s_image_error_format')));
	}
	$strvisionurl=f_visionuploadurl($strvisionapibaseurl,$strvisionimageref);
	$objvisioncurl=curl_init();
	curl_setopt_array($objvisioncurl,array(
		CURLOPT_URL => $strvisionurl,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_HTTPHEADER => array($strvisionapikeyname.": ".$strvisionapikeyvalue)
	));
	$strvisionbody=curl_exec($objvisioncurl);
	$lngvisionhttpcode=f_stringtolong(curl_getinfo($objvisioncurl,CURLINFO_HTTP_CODE));
	$strvisioncurlerror=curl_error($objvisioncurl);
	//No curl_close() here, and not by oversight: the handle is freed with the variable since PHP 8,
	//and on PHP 8.5 the call raises a deprecation notice. Everywhere else in this repository the
	//notice would land in a page nobody reads with display_errors on; here it would land in front of
	//the image bytes or of the JSON, after the output buffer has already been closed.
	unset($objvisioncurl);
	if ($strvisionbody===false) {
		error_log("picture search: vision image read back failed: ".$strvisioncurlerror);
		f_visionjsonresponse(502,array("error" => get_movie_string('lib_t2s_image_error_upload')));
	}
	if ($lngvisionhttpcode===410) {
		//The JSON log of a turn outlives the image it names: logs are kept without limit, photos
		//for 30 days. An old image missing is the normal end of the story, not an incident.
		f_visionjsonresponse(410,array("error" => get_movie_string('lib_t2s_image_expired')));
	}
	if ($lngvisionhttpcode!==200) {
		f_visionjsonresponse($lngvisionhttpcode>=400?$lngvisionhttpcode:502,array("error" => get_movie_string('lib_t2s_image_error_upload')));
	}
	if (!headers_sent()) {
		header('Content-Type: '.f_visionmediatype(f_visionformatfromref($strvisionimageref)));
		header('Content-Length: '.strlen($strvisionbody));
		//Private, because the image belongs to whoever deposited it, and short, because the
		//30-day purge can make it disappear under the cache.
		header('Cache-Control: private, max-age=300');
	}
	echo $strvisionbody;
	exit;
}

if ($strvisionmethod!=="POST") {
	f_visionjsonresponse(405,array("error" => get_movie_string('lib_t2s_image_error_upload')));
}

//Modest throttle. This shim is reachable without credentials on the public site, since the search
//box itself is; the counter keeps a bored visitor from filling the shared uploads folder, which is
//purged at 30 days and shared by both colours. A determined client drops the cookie and starts
//over, so this is a speed bump, not a gate: the real ceiling is the API key, which stays here.
//The session itself was opened above, before anything could be written out.
$lngvisionnow=time();
$arrvisionhistory=(isset($_SESSION['visionuploads']) && is_array($_SESSION['visionuploads']))?$_SESSION['visionuploads']:array();
$arrvisionhistory=array_values(array_filter($arrvisionhistory,function($lngstamp) use ($lngvisionnow) {
	return ((int)$lngstamp)>($lngvisionnow-3600);
}));
if (count($arrvisionhistory)>=30) {
	$_SESSION['visionuploads']=$arrvisionhistory;
	f_visionjsonresponse(429,array("error" => get_movie_string('lib_t2s_image_error_toomany')));
}

$lngvisionmaxbytes=f_visionmaxuploadbytes();
$strvisiondeclaredlength=isset($_SERVER['CONTENT_LENGTH'])?(string)$_SERVER['CONTENT_LENGTH']:"";
if ($strvisiondeclaredlength!=="" && ctype_digit($strvisiondeclaredlength) && (int)$strvisiondeclaredlength>$lngvisionmaxbytes) {
	f_visionjsonresponse(413,array("error" => get_movie_string('lib_t2s_image_error_toobig')));
}

$strvisionpayload=file_get_contents('php://input');
if ($strvisionpayload===false) {
	$strvisionpayload="";
}
if ($strvisionpayload==="") {
	//An empty body here is most often post_max_size in php.ini (40M in this repository) silently
	//dropping an oversized payload, so the message names the size rather than the emptiness.
	f_visionjsonresponse(400,array("error" => get_movie_string('lib_t2s_image_error_toobig')));
}
if (strlen($strvisionpayload)>$lngvisionmaxbytes) {
	f_visionjsonresponse(413,array("error" => get_movie_string('lib_t2s_image_error_toobig')));
}

$strvisionformat=f_visionsniffimageformat($strvisionpayload);
if ($strvisionformat==="") {
	//Refused on the bytes, like the API does:
	//neither the extension nor the declared Content-Type decides anything here.
	f_visionjsonresponse(415,array("error" => get_movie_string('lib_t2s_image_error_format')));
}

$strvisionurl=f_visionuploadurl($strvisionapibaseurl);
$objvisioncurl=curl_init();
curl_setopt_array($objvisioncurl,array(
	CURLOPT_URL => $strvisionurl,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_CONNECTTIMEOUT => 10,
	CURLOPT_TIMEOUT => 120,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => 'POST',
	CURLOPT_POSTFIELDS => $strvisionpayload,
	CURLOPT_HTTPHEADER => array(
		$strvisionapikeyname.": ".$strvisionapikeyvalue,
		"Content-Type: ".f_visionmediatype($strvisionformat),
		"Content-Length: ".strlen($strvisionpayload)
	)
));
$strvisionresponse=curl_exec($objvisioncurl);
$lngvisionhttpcode=f_stringtolong(curl_getinfo($objvisioncurl,CURLINFO_HTTP_CODE));
$strvisioncurlerror=curl_error($objvisioncurl);
unset($objvisioncurl);

if ($strvisionresponse===false) {
	error_log("picture search: vision upload transport error: ".$strvisioncurlerror);
	f_visionjsonresponse(502,array("error" => get_movie_string('lib_t2s_image_error_upload')));
}

$arrvisionresult=json_decode((string)$strvisionresponse,true);
if ($lngvisionhttpcode!==200 || !is_array($arrvisionresult) || !isset($arrvisionresult['image_ref'])) {
	//The API answers 413 past its own ceiling and 415 when the bytes are neither JPEG nor PNG. Both
	//are already covered above, so reaching them here means the two sides disagree on a limit; the
	//detail goes to the log, the visitor gets the matching sentence.
	error_log("picture search: vision upload refused by the API, HTTP ".$lngvisionhttpcode.": ".substr((string)$strvisionresponse,0,500));
	switch ($lngvisionhttpcode) {
	case 413:
		f_visionjsonresponse(413,array("error" => get_movie_string('lib_t2s_image_error_toobig')));
		break;
	case 415:
		f_visionjsonresponse(415,array("error" => get_movie_string('lib_t2s_image_error_format')));
		break;
	}
	f_visionjsonresponse(502,array("error" => get_movie_string('lib_t2s_image_error_upload')));
}

$strvisionimageref=(string)$arrvisionresult['image_ref'];
if (!f_visionimagerefisvalid($strvisionimageref)) {
	//The reference is about to be written into a form field and later into a URL. If it does not
	//look like something the API generator produced, the two sides have drifted and the page must
	//not carry it any further.
	error_log("picture search: the API returned an unexpected image_ref: ".substr($strvisionimageref,0,120));
	f_visionjsonresponse(502,array("error" => get_movie_string('lib_t2s_image_error_upload')));
}

$arrvisionhistory[]=$lngvisionnow;
$_SESSION['visionuploads']=$arrvisionhistory;

f_visionjsonresponse(200,array(
	"image_ref" => $strvisionimageref,
	"bytes" => isset($arrvisionresult['bytes'])?f_stringtolong($arrvisionresult['bytes']):strlen($strvisionpayload),
	"image_format" => isset($arrvisionresult['image_format'])?(string)$arrvisionresult['image_format']:$strvisionformat,
	"deposited_at" => isset($arrvisionresult['deposited_at'])?(string)$arrvisionresult['deposited_at']:"",
	"purge_after" => isset($arrvisionresult['purge_after'])?(string)$arrvisionresult['purge_after']:"",
	"api_color" => $strvisionapicolor
));
?>
