<?php
//Same-origin shim between the browser and POST /feedback of the Text2SQL API. Included by the
//shell html/app/feedback.php.
//
//WHY THIS FILE EXISTS. TEXT2SQL_API_KEY_VALUE is a server secret, and a fetch() straight from the
//page to the API would publish it in the source of every search page. So the vote goes to this PHP
//file, on the same origin as the page, and the key is added here, server side. The browser never
//learns the API URL either. Same reasoning, and same shape, as lib/upload-vision.inc.php.
//
//Contract with the browser:
//  POST, JSON body {rating, comment?, question, question_hashed?, request_id?, api_version?,
//  sql_query?, result_entity?, ui_language?}
//  -> 200 {id_feedback, status, cache_bypassed, api_version}
//  -> any other status {error}, a sentence already translated, which the page prints as is
//
//The validation, the payload and the whitelist of the answer live in lib/feedback-ui.inc.php,
//which has no side effect and is covered by test/feedback-test.php.

//The includes run inside an output buffer, and what they printed is thrown away rather than sent:
//one PHP notice in front of the JSON would make it unreadable for the caller, and would commit the
//headers before a status code could be set. Everything discarded goes to the error log.
ob_start();
include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
include(__DIR__."/feedback-ui.inc.php");
if (session_status()===PHP_SESSION_NONE) {
	//Started inside the buffer, because session_start() cannot run once a byte has been sent. The
	//throttle below reads the session.
	session_start();
}
$strfeedbackstraynoise=ob_get_clean();
if ($strfeedbackstraynoise!=="") {
	error_log("result feedback: the shim discarded ".strlen($strfeedbackstraynoise)." bytes printed while loading: ".substr(trim(strip_tags($strfeedbackstraynoise)),0,500));
}

$strfeedbackmethod=isset($_SERVER['REQUEST_METHOD'])?strtoupper((string)$_SERVER['REQUEST_METHOD']):"GET";
if ($strfeedbackmethod!=="POST") {
	if (!headers_sent()) {
		header('Allow: POST');
	}
	f_feedbackjsonresponse(405,array("error" => get_movie_string('lib_t2s_feedback_error')));
}

//The colour is read exactly as the search page reads it (parameter, then cookie, then the
//configured default), so the vote reaches the deployment that answered the question: the request
//id and the cache row it names exist there.
$strfeedbackapicolor=isset($_GET['apicolor'])?trim((string)$_GET['apicolor']):"";
if ($strfeedbackapicolor==="" && isset($_COOKIE['apicolor'])) {
	$strfeedbackapicolor=trim((string)$_COOKIE['apicolor']);
}
if ($strfeedbackapicolor!=="Blue" && $strfeedbackapicolor!=="Green") {
	$strfeedbackapicolor=isset($strtext2sqlapicolor)?(string)$strtext2sqlapicolor:"Green";
}
$strfeedbackurl=f_feedbackapiurl(
	$strfeedbackapicolor,
	isset($strtext2sqlapibluedomainurl)?$strtext2sqlapibluedomainurl:"",
	isset($strtext2sqlapigreendomainurl)?$strtext2sqlapigreendomainurl:""
);
$strfeedbackkeyname=isset($strtext2sqlapikeyname)?(string)$strtext2sqlapikeyname:"";
$strfeedbackkeyvalue=isset($strtext2sqlapikeyvalue)?(string)$strtext2sqlapikeyvalue:"";

if (!function_exists('curl_init')) {
	error_log("result feedback: the curl extension is missing, the shim cannot reach the API");
	f_feedbackjsonresponse(500,array("error" => get_movie_string('lib_t2s_feedback_error')));
}
if ($strfeedbackurl==="" || $strfeedbackkeyname==="" || $strfeedbackkeyvalue==="") {
	//A missing secret is a deployment fault: said in the log, kept vague in the page, and no part
	//of the key or of the API URL is ever echoed.
	error_log("result feedback: the Text2SQL API is not configured for the feedback shim");
	f_feedbackjsonresponse(500,array("error" => get_movie_string('lib_t2s_feedback_error')));
}

//Modest throttle. The shim is reachable without credentials on the public site, since the search
//page is; the counter keeps a loop from filling the feedback table. A client that drops the cookie
//starts over, so this is a speed bump, not a gate.
$lngfeedbacknow=time();
$arrfeedbackhistory=(isset($_SESSION['feedbacksent']) && is_array($_SESSION['feedbacksent']))?$_SESSION['feedbacksent']:array();
$arrfeedbackhistory=array_values(array_filter($arrfeedbackhistory,function($lngstamp) use ($lngfeedbacknow) {
	return ((int)$lngstamp)>($lngfeedbacknow-3600);
}));
if (count($arrfeedbackhistory)>=f_feedbackmaxperhour()) {
	$_SESSION['feedbacksent']=$arrfeedbackhistory;
	f_feedbackjsonresponse(429,array("error" => get_movie_string('lib_t2s_feedback_error_toomany')));
}

$lngfeedbackmaxbytes=f_feedbackmaxbodybytes();
$strfeedbackdeclaredlength=isset($_SERVER['CONTENT_LENGTH'])?(string)$_SERVER['CONTENT_LENGTH']:"";
if ($strfeedbackdeclaredlength!=="" && ctype_digit($strfeedbackdeclaredlength) && (int)$strfeedbackdeclaredlength>$lngfeedbackmaxbytes) {
	f_feedbackjsonresponse(413,array("error" => get_movie_string('lib_t2s_feedback_error')));
}
$strfeedbackbody=file_get_contents('php://input');
if ($strfeedbackbody===false) {
	$strfeedbackbody="";
}
if (strlen($strfeedbackbody)>$lngfeedbackmaxbytes) {
	f_feedbackjsonresponse(413,array("error" => get_movie_string('lib_t2s_feedback_error')));
}

$arrfeedbackcheck=f_feedbackpayload(json_decode($strfeedbackbody,true),isset($struilang)?(string)$struilang:"en");
if (!$arrfeedbackcheck["ok"]) {
	f_feedbackjsonresponse(422,array("error" => get_movie_string('lib_t2s_feedback_error')));
}
$strfeedbackjson=json_encode($arrfeedbackcheck["payload"],JSON_UNESCAPED_UNICODE);
if ($strfeedbackjson===false) {
	f_feedbackjsonresponse(422,array("error" => get_movie_string('lib_t2s_feedback_error')));
}

//Counted before the call rather than after a success: what the throttle protects is the API, and a
//request refused there has cost it as much as one accepted.
$arrfeedbackhistory[]=$lngfeedbacknow;
$_SESSION['feedbacksent']=$arrfeedbackhistory;

$objfeedbackcurl=curl_init();
curl_setopt_array($objfeedbackcurl,array(
	CURLOPT_URL => $strfeedbackurl,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_CONNECTTIMEOUT => 5,
	CURLOPT_TIMEOUT => 15,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => 'POST',
	CURLOPT_POSTFIELDS => $strfeedbackjson,
	CURLOPT_HTTPHEADER => array(
		$strfeedbackkeyname.": ".$strfeedbackkeyvalue,
		"Content-Type: application/json",
		"Content-Length: ".strlen($strfeedbackjson)
	)
));
$strfeedbackresponse=curl_exec($objfeedbackcurl);
$lngfeedbackhttpcode=f_stringtolong(curl_getinfo($objfeedbackcurl,CURLINFO_HTTP_CODE));
$strfeedbackcurlerror=curl_error($objfeedbackcurl);
//No curl_close(): the handle is freed with the variable since PHP 8, and on PHP 8.5 the call
//raises a deprecation notice that would land in front of the JSON (see lib/upload-vision.inc.php).
unset($objfeedbackcurl);

//Sent once, never retried: a feedback row is a vote, and a retry after a timeout could count it
//twice.
if ($strfeedbackresponse===false) {
	error_log("result feedback: transport error: ".$strfeedbackcurlerror);
	f_feedbackjsonresponse(502,array("error" => get_movie_string('lib_t2s_feedback_error')));
}
$arrfeedbackanswer=f_feedbackresponsefields(json_decode((string)$strfeedbackresponse,true));
if ($lngfeedbackhttpcode!==200 || count($arrfeedbackanswer)===0) {
	error_log("result feedback: refused by the API, HTTP ".$lngfeedbackhttpcode.": ".substr((string)$strfeedbackresponse,0,500));
	switch ($lngfeedbackhttpcode) {
	case 422:
		f_feedbackjsonresponse(422,array("error" => get_movie_string('lib_t2s_feedback_error')));
		break;
	case 503:
		f_feedbackjsonresponse(503,array("error" => get_movie_string('lib_t2s_feedback_error_unavailable')));
		break;
	}
	f_feedbackjsonresponse(502,array("error" => get_movie_string('lib_t2s_feedback_error')));
}

f_feedbackjsonresponse(200,$arrfeedbackanswer);
?>
