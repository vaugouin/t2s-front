<?php
//The thumbs up / thumbs down under a search result. This file holds everything of that feature
//that can be decided without a database, a session or a network call: the validation of what the
//browser posts, the payload forwarded to POST /feedback of the Text2SQL API, the whitelist of what
//goes back to the browser, the markup of the block and its JavaScript.
//
//It is deliberately SIDE-EFFECT FREE and can be required on its own (AGENTS.md, "Layout and include
//chain"): lib/global-light.inc.php opens a database connection when it loads, so nothing placed
//there can be tested in isolation. The endpoint body that uses these helpers lives in
//lib/feedback.inc.php, included by the shell html/app/feedback.php; the search page prints the
//block and its script.
//
//The browser never talks to the API: the key is a server secret, so the vote goes to the
//same-origin shim, which adds the key, exactly like the picture upload (lib/upload-vision.inc.php).

if (!function_exists('f_feedbackfieldlimits')) {
	function f_feedbackfieldlimits() {
		//Purpose: Maximum length, in characters, of each text field of a feedback
		//Params:	None
		//Returns: Associative array, field name => maximum number of characters
		//
		//comment and question are the API's own limits: a refusal here is cheaper than a round trip
		//for a 422. The others are generous ceilings, the same as the voice front applies, so that a
		//client cannot make the shim forward an arbitrary amount of text.
		return array(
			"question" => 4000,
			"comment" => 2000,
			"question_hashed" => 200,
			"request_id" => 200,
			"api_version" => 100,
			"sql_query" => 50000,
			"result_entity" => 200
		);
	}
}

if (!function_exists('f_feedbackmaxbodybytes')) {
	function f_feedbackmaxbodybytes() {
		//Purpose: Ceiling of the JSON body the shim accepts from the browser
		//Params:	None
		//Returns: A number of bytes
		//
		//The fields above add up to about 56,000 characters, up to four bytes each in UTF-8, plus the
		//JSON escaping. 256 KB covers the worst legitimate case; anything larger is refused before it
		//is decoded.
		return 256*1024;
	}
}

if (!function_exists('f_feedbackmaxperhour')) {
	function f_feedbackmaxperhour() {
		//Purpose: Number of feedbacks one browser session may send in an hour
		//Params:	None
		//Returns: An integer
		//
		//A person changes their mind and adds a comment: a handful of posts per result. 60 an hour
		//lets someone rate every result of a long session and stops a loop.
		return 60;
	}
}

if (!function_exists('f_feedbacktextlength')) {
	function f_feedbacktextlength($strtext) {
		//Purpose: Count the characters of a UTF-8 string without the mbstring extension
		//Params:	$strtext: The string to measure
		//Returns: The number of characters, or -1 when the string is not valid UTF-8
		//
		//preg with the u modifier counts code points, as the API (Python) does, and refuses invalid
		//UTF-8, which would otherwise make json_encode fail further down.
		$strtext=(string)$strtext;
		if ($strtext==="") {
			return 0;
		}
		$lngcount=preg_match_all('/./su',$strtext);
		if ($lngcount===false) {
			return -1;
		}
		return (int)$lngcount;
	}
}

if (!function_exists('f_feedbackratingvalue')) {
	function f_feedbackratingvalue($mxdrating) {
		//Purpose: Read the rating posted by the browser
		//Params:	$mxdrating: The decoded "rating" value, of any type
		//Returns: 1 or -1, or 0 when the value is anything else
		//
		//Strict on purpose: true, 1.0, "+1" or 2 are not a vote, and the API would refuse them anyway.
		if (is_int($mxdrating) && ($mxdrating===1 || $mxdrating===-1)) {
			return $mxdrating;
		}
		if (is_string($mxdrating) && ($mxdrating==="1" || $mxdrating==="-1")) {
			return (int)$mxdrating;
		}
		return 0;
	}
}

if (!function_exists('f_feedbackpayload')) {
	function f_feedbackpayload($mxdinput,$strdefaultlang="en") {
		//Purpose: Validate what the browser posted and build the body of POST /feedback
		//Params:	$mxdinput: The decoded JSON body, normally an associative array
		//			$strdefaultlang: Interface language used when the posted one is not "en" or "fr"
		//Returns: array("ok"=>bool, "field"=>name of the first invalid field or "", "payload"=>array)
		//
		//client is never read from the browser: it is fixed to "t2s-front", so the API can tell the
		//two fronts apart and a client cannot pose as the other. An empty optional field is left out
		//of the payload rather than sent empty, the same omit-when-empty rule as the search payload,
		//so the API applies its own default.
		$arrresult=array("ok"=>false,"field"=>"","payload"=>array());
		if (!is_array($mxdinput)) {
			$arrresult["field"]="body";
			return $arrresult;
		}
		$arrlimits=f_feedbackfieldlimits();

		$lngrating=f_feedbackratingvalue(isset($mxdinput["rating"])?$mxdinput["rating"]:null);
		if ($lngrating===0) {
			$arrresult["field"]="rating";
			return $arrresult;
		}

		$arrtexts=array();
		foreach ($arrlimits as $strfield => $lngmax) {
			$mxdvalue=array_key_exists($strfield,$mxdinput)?$mxdinput[$strfield]:null;
			if ($mxdvalue===null) {
				$arrtexts[$strfield]="";
				continue;
			}
			if (is_int($mxdvalue)) {
				$mxdvalue=(string)$mxdvalue;
			}
			if (!is_string($mxdvalue)) {
				$arrresult["field"]=$strfield;
				return $arrresult;
			}
			$strvalue=trim($mxdvalue);
			$lnglength=f_feedbacktextlength($strvalue);
			if ($lnglength<0 || $lnglength>$lngmax) {
				$arrresult["field"]=$strfield;
				return $arrresult;
			}
			$arrtexts[$strfield]=$strvalue;
		}
		if ($arrtexts["question"]==="") {
			//The API ties a vote to a question; a vote on nothing is refused here.
			$arrresult["field"]="question";
			return $arrresult;
		}

		$strlang=isset($mxdinput["ui_language"]) && is_string($mxdinput["ui_language"])?strtolower(trim($mxdinput["ui_language"])):"";
		if ($strlang!=="en" && $strlang!=="fr") {
			$strlang=((string)$strdefaultlang==="fr"?"fr":"en");
		}

		$arrpayload=array(
			"rating" => $lngrating,
			"client" => "t2s-front",
			"ui_language" => $strlang,
			"question" => $arrtexts["question"]
		);
		foreach (array("comment","question_hashed","request_id","api_version","sql_query","result_entity") as $strfield) {
			if ($arrtexts[$strfield]!=="") {
				$arrpayload[$strfield]=$arrtexts[$strfield];
			}
		}
		$arrresult["ok"]=true;
		$arrresult["payload"]=$arrpayload;
		return $arrresult;
	}
}

if (!function_exists('f_feedbackapiurl')) {
	function f_feedbackapiurl($strapicolor,$strblueurl,$strgreenurl) {
		//Purpose: Build the URL of POST /feedback on one colour of the API
		//Params:	$strapicolor: "Blue" or anything else (green), as f_movietext2sqlsearch() reads it
		//			$strblueurl: Base URL of the blue deployment
		//			$strgreenurl: Base URL of the green deployment
		//Returns: The full URL, or "" when the base URL of that colour is not configured
		$strbaseurl=rtrim(((string)$strapicolor==="Blue"?(string)$strblueurl:(string)$strgreenurl),"/");
		if ($strbaseurl==="") {
			return "";
		}
		return $strbaseurl."/feedback";
	}
}

if (!function_exists('f_feedbackresponsefields')) {
	function f_feedbackresponsefields($mxdresponse) {
		//Purpose: Keep, from the answer of the API, only what the browser may read
		//Params:	$mxdresponse: The decoded body of a 200 from POST /feedback
		//Returns: array with id_feedback, status, cache_bypassed and api_version, or an empty array
		//			when the answer does not carry an identifier
		//
		//A whitelist rather than a pass-through: whatever the API adds to its answer later does not
		//reach a public page without a decision here.
		if (!is_array($mxdresponse) || !isset($mxdresponse["id_feedback"])) {
			return array();
		}
		return array(
			"id_feedback" => (int)$mxdresponse["id_feedback"],
			"status" => isset($mxdresponse["status"])?(string)$mxdresponse["status"]:"recorded",
			"cache_bypassed" => !empty($mxdresponse["cache_bypassed"]),
			"api_version" => isset($mxdresponse["api_version"])?(string)$mxdresponse["api_version"]:""
		);
	}
}

if (!function_exists('f_feedbackjsonresponse')) {
	function f_feedbackjsonresponse($lnghttpcode,$arrpayload) {
		//Purpose: End the request with a JSON body, the only thing the feedback shim ever answers
		//Params:	$lnghttpcode: HTTP status to send
		//			$arrpayload: Associative array to encode
		//Returns: Nothing, the script ends here
		if (!headers_sent()) {
			http_response_code((int)$lnghttpcode);
			header('Content-Type: application/json; charset=utf-8');
			header('Cache-Control: no-store');
		}
		echo json_encode($arrpayload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
		exit;
	}
}

if (!function_exists('f_feedbackhtmltext')) {
	function f_feedbackhtmltext($strtext) {
		//Purpose: Escape a label for HTML text or a double-quoted attribute
		//Params:	$strtext: The text
		//Returns: The escaped text
		return htmlspecialchars((string)$strtext,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
	}
}

if (!function_exists('f_feedbackblockhtml')) {
	function f_feedbackblockhtml($arrlabels) {
		//Purpose: Build the markup of the feedback block printed once under the answer of a search
		//Params:	$arrlabels: Localized strings, keys question, up, down, commentlabel,
		//				commentplaceholder, send, skip
		//Returns: The HTML of the block, as a string
		//
		//The block is printed hidden and the script shows it: without JavaScript the buttons could
		//not send anything, and a button that does nothing is worse than no button. The emoji are
		//aria-hidden; each button is named by its aria-label, and aria-pressed says which choice
		//stands. The comment field is labelled for screen readers only, its placeholder says the
		//same thing to the eye.
		$arrlabels=is_array($arrlabels)?$arrlabels:array();
		$arrtext=array();
		foreach (array("question","up","down","commentlabel","commentplaceholder","send","skip") as $strkey) {
			$arrtext[$strkey]=f_feedbackhtmltext(isset($arrlabels[$strkey])?$arrlabels[$strkey]:"");
		}
		$arrlimits=f_feedbackfieldlimits();
		$lngmaxcomment=(int)$arrlimits["comment"];
		$strhtml="";
		$strhtml.="<div id=\"t2sFeedback\" class=\"t2sFeedback\" role=\"group\" aria-labelledby=\"t2sFeedbackQuestion\" hidden>";
		$strhtml.="<div class=\"t2sFeedbackRow\">";
		$strhtml.="<span id=\"t2sFeedbackQuestion\" class=\"t2sFeedbackQuestion\">".$arrtext["question"]."</span>";
		$strhtml.="<span class=\"t2sFeedbackButtons\">";
		$strhtml.="<button type=\"button\" id=\"t2sFeedbackUp\" class=\"t2sFeedbackButton\" aria-pressed=\"false\" aria-label=\"".$arrtext["up"]."\" title=\"".$arrtext["up"]."\"><span aria-hidden=\"true\">&#x1F44D;</span></button>";
		$strhtml.="<button type=\"button\" id=\"t2sFeedbackDown\" class=\"t2sFeedbackButton\" aria-pressed=\"false\" aria-label=\"".$arrtext["down"]."\" title=\"".$arrtext["down"]."\"><span aria-hidden=\"true\">&#x1F44E;</span></button>";
		$strhtml.="</span>";
		$strhtml.="</div>";
		$strhtml.="<div id=\"t2sFeedbackCommentRow\" class=\"t2sFeedbackCommentRow\" hidden>";
		$strhtml.="<label for=\"t2sFeedbackComment\" class=\"t2sSrOnly\">".$arrtext["commentlabel"]."</label>";
		$strhtml.="<input type=\"text\" id=\"t2sFeedbackComment\" class=\"t2sFeedbackComment\" maxlength=\"".$lngmaxcomment."\" autocomplete=\"off\" enterkeyhint=\"send\" placeholder=\"".$arrtext["commentplaceholder"]."\">";
		$strhtml.="<button type=\"button\" id=\"t2sFeedbackSend\" class=\"t2sFeedbackAction\">".$arrtext["send"]."</button>";
		$strhtml.="<button type=\"button\" id=\"t2sFeedbackSkip\" class=\"t2sFeedbackAction t2sFeedbackActionSecondary\">".$arrtext["skip"]."</button>";
		$strhtml.="</div>";
		$strhtml.="<div id=\"t2sFeedbackStatus\" class=\"t2sFeedbackStatus\" role=\"status\" aria-live=\"polite\"></div>";
		$strhtml.="</div>";
		return $strhtml;
	}
}

if (!function_exists('f_feedbackjs')) {
	function f_feedbackjs($arrdata,$arrlabels) {
		//Purpose: Build the JavaScript that drives the feedback block
		//Params:	$arrdata: What the vote is about, keys endpoint (same-origin URL of the shim,
		//				never the API), question, question_hashed, request_id, api_version, sql_query,
		//				result_entity, ui_language
		//			$arrlabels: Localized strings, keys sending, thanks, recompute, error
		//Returns: The body of a <script> block, as a string
		//
		//Every value that crosses from PHP into JavaScript goes through json_encode (AGENTS.md,
		//"Emitting JavaScript from PHP"), and test/feedback-test.php renders this function and runs
		//node --check on the result. JSON_HEX_TAG matters here more than elsewhere: the question and
		//the SQL come from the visitor and from the API, and a "</script>" inside either would close
		//the block early.
		//
		//The flow, the same as the voice client's: one record per vote. A click on a thumb marks it
		//pending and opens the comment field; Send (or Enter) records the vote with the comment,
		//Skip (or Escape) records it without one. A click on the other thumb, before or after,
		//makes it the pending vote and reopens the field; a click on the thumb already recorded
		//does nothing, so a vote is never counted twice. On a failure the field stays open with
		//its text, and Send is the retry.
		$arrdata=is_array($arrdata)?$arrdata:array();
		$arrlabels=is_array($arrlabels)?$arrlabels:array();
		$intjsonflags=JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE;
		$arrcontext=array();
		foreach (array("question","question_hashed","request_id","api_version","sql_query","result_entity","ui_language") as $strkey) {
			$arrcontext[$strkey]=isset($arrdata[$strkey])?(string)$arrdata[$strkey]:"";
		}
		$strendpoint=isset($arrdata["endpoint"])?(string)$arrdata["endpoint"]:"";
		$arrtext=array();
		foreach (array("sending","thanks","recompute","error") as $strkey) {
			$arrtext[$strkey]=isset($arrlabels[$strkey])?(string)$arrlabels[$strkey]:"";
		}

		$arrlines=array();
		$arrlines[]="(function(){";
		$arrlines[]="var objroot=document.getElementById('t2sFeedback');";
		$arrlines[]="var objup=document.getElementById('t2sFeedbackUp');";
		$arrlines[]="var objdown=document.getElementById('t2sFeedbackDown');";
		$arrlines[]="var objcommentrow=document.getElementById('t2sFeedbackCommentRow');";
		$arrlines[]="var objcomment=document.getElementById('t2sFeedbackComment');";
		$arrlines[]="var objsend=document.getElementById('t2sFeedbackSend');";
		$arrlines[]="var objskip=document.getElementById('t2sFeedbackSkip');";
		$arrlines[]="var objstatus=document.getElementById('t2sFeedbackStatus');";
		//No fetch, no block: the markup stays hidden rather than offering buttons that cannot send.
		$arrlines[]="if(!objroot||!objup||!objdown||!objstatus||!window.fetch||!window.JSON){return;}";
		$arrlines[]="var strendpoint=".json_encode($strendpoint,$intjsonflags).";";
		$arrlines[]="var objcontext=".json_encode($arrcontext,$intjsonflags).";";
		$arrlines[]="var strlabelsending=".json_encode($arrtext["sending"],$intjsonflags).";";
		$arrlines[]="var strlabelthanks=".json_encode($arrtext["thanks"],$intjsonflags).";";
		$arrlines[]="var strlabelrecompute=".json_encode($arrtext["recompute"],$intjsonflags).";";
		$arrlines[]="var strlabelerror=".json_encode($arrtext["error"],$intjsonflags).";";
		$arrlines[]="var lngrating=0;";
		$arrlines[]="var lngpending=0;";
		$arrlines[]="var intbusy=false;";
		$arrlines[]="function f_fbstatus(strtext,intiserror){objstatus.textContent=strtext;if(intiserror){objstatus.className='t2sFeedbackStatus t2sFeedbackStatusError';}else{objstatus.className='t2sFeedbackStatus';}}";
		$arrlines[]="function f_fbpressed(){var lngshown=lngpending!==0?lngpending:lngrating;objup.setAttribute('aria-pressed',lngshown===1?'true':'false');objdown.setAttribute('aria-pressed',lngshown===-1?'true':'false');}";
		$arrlines[]="function f_fbbusy(intvalue){intbusy=intvalue;objup.disabled=intvalue;objdown.disabled=intvalue;if(objsend){objsend.disabled=intvalue;}if(objskip){objskip.disabled=intvalue;}}";
		$arrlines[]="function f_fbthanks(){return lngrating===-1&&strlabelrecompute?strlabelthanks+' '+strlabelrecompute:strlabelthanks;}";
		$arrlines[]="function f_fbopencomment(){if(!objcommentrow||!objcomment){return;}objcommentrow.hidden=false;if(objcomment.focus){objcomment.focus();}}";
		$arrlines[]="function f_fbclosecomment(){if(!objcommentrow){return;}objcommentrow.hidden=true;if(objcomment){objcomment.value='';}}";
		$arrlines[]="function f_fbcomment(){return objcomment?objcomment.value.replace(/^\s+|\s+$/g,''):'';}";
		//The context is copied whole and the empty keys are dropped, so the shim and the API apply
		//their own default to whatever this page did not know.
		$arrlines[]="function f_fbbody(lngvalue,strcomment){var objbody={rating:lngvalue};var strkey='';for(strkey in objcontext){if(Object.prototype.hasOwnProperty.call(objcontext,strkey)&&objcontext[strkey]!==''){objbody[strkey]=objcontext[strkey];}}if(strcomment){objbody.comment=strcomment;}return objbody;}";
		$arrlines[]="function f_fbpost(lngvalue,strcomment){return fetch(strendpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify(f_fbbody(lngvalue,strcomment))}).then(function(objresponse){return objresponse.json().then(function(objjson){return {ok:objresponse.ok,body:objjson};},function(){return {ok:false,body:{}};});});}";
		$arrlines[]="function f_fbfailure(objresult){var strmessage=(objresult&&objresult.body&&typeof objresult.body.error==='string'&&objresult.body.error)?objresult.body.error:strlabelerror;f_fbstatus(strmessage,true);}";
		$arrlines[]="function f_fbrate(lngvalue){if(intbusy){return;}if(lngvalue===lngrating&&lngpending===0){return;}if(lngvalue===lngrating){lngpending=0;f_fbpressed();f_fbclosecomment();f_fbstatus(f_fbthanks(),false);return;}lngpending=lngvalue;f_fbpressed();f_fbstatus('',false);f_fbopencomment();}";
		$arrlines[]="function f_fbsubmit(strcomment){if(intbusy||lngpending===0){return;}var lngvalue=lngpending;f_fbbusy(true);f_fbstatus(strlabelsending,false);f_fbpost(lngvalue,strcomment).then(function(objresult){f_fbbusy(false);if(!objresult.ok||!objresult.body||!objresult.body.id_feedback){f_fbfailure(objresult);return;}lngrating=lngvalue;lngpending=0;f_fbpressed();f_fbclosecomment();f_fbstatus(f_fbthanks(),false);}).catch(function(){f_fbbusy(false);f_fbfailure(null);});}";
		$arrlines[]="objup.addEventListener('click',function(){f_fbrate(1);});";
		$arrlines[]="objdown.addEventListener('click',function(){f_fbrate(-1);});";
		$arrlines[]="if(objsend){objsend.addEventListener('click',function(){f_fbsubmit(f_fbcomment());});}";
		$arrlines[]="if(objskip){objskip.addEventListener('click',function(){f_fbsubmit('');});}";
		$arrlines[]="if(objcomment){objcomment.addEventListener('keydown',function(objevent){if(objevent.key==='Enter'||objevent.keyCode===13){objevent.preventDefault();f_fbsubmit(f_fbcomment());}else if(objevent.key==='Escape'||objevent.keyCode===27){objevent.preventDefault();f_fbsubmit('');}});}";
		$arrlines[]="objroot.hidden=false;";
		$arrlines[]="})();";
		return implode("\n",$arrlines)."\n";
	}
}
?>
