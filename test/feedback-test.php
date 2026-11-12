<?php
/*
 * feedback-test.php - test for lib/feedback-ui.inc.php
 *
 * Run it from the repository root, no database, no network and no web server needed:
 *
 *     php test/feedback-test.php
 *
 * Exit code 0 when every case passes, 1 otherwise.
 *
 * WHAT IT COVERS
 * 1. The validation of what the browser posts to the shim (html/app/feedback.php) and the payload
 *    forwarded to POST /feedback: a strict rating, the length limits, the client fixed by the
 *    server, the omit-when-empty rule.
 * 2. The whitelist of the answer sent back to the browser.
 * 3. The labels: every key the search page asks for exists in English and in French.
 * 4. The JavaScript printed from PHP: rendered with hostile values (quotes, newlines, backslashes,
 *    "</script>", non-ASCII) and checked with node --check, never by reimplementing PHP escaping
 *    (AGENTS.md, "Emitting JavaScript from PHP").
 *
 * The node --check step is skipped, loudly, when node is not on the PATH. A skip is not a pass.
 */

require_once(__DIR__."/../lib/feedback-ui.inc.php");

$lngchecks=0;
$lngfailures=0;

function f_group($strtitle) {
	echo "\n".$strtitle."\n";
}

function f_check($strlabel,$mxdexpected,$mxdactual) {
	global $lngchecks,$lngfailures;
	$lngchecks++;
	if ($mxdexpected===$mxdactual) {
		echo "  ok    ".$strlabel."\n";
	} else {
		$lngfailures++;
		echo "  FAIL  ".$strlabel."\n";
		echo "        expected: ".var_export($mxdexpected,true)."\n";
		echo "        actual  : ".var_export($mxdactual,true)."\n";
	}
}

function f_nodecheck($strlabel,$strcode) {
	global $lngchecks,$lngfailures;
	static $intnodeavailable=null;
	if ($intnodeavailable===null) {
		$arrnodeversion=array();
		$lngnodeversionstatus=1;
		exec("node --version 2>&1",$arrnodeversion,$lngnodeversionstatus);
		$intnodeavailable=($lngnodeversionstatus===0);
	}
	if (!$intnodeavailable) {
		echo "  SKIP  node --check, ".$strlabel." (node is not on the PATH; a skip is not a pass)\n";
		return;
	}
	$strtempfile=tempnam(sys_get_temp_dir(),"feedback").".js";
	file_put_contents($strtempfile,$strcode);
	$arrnodeoutput=array();
	$lngnodestatus=1;
	exec("node --check ".escapeshellarg($strtempfile)." 2>&1",$arrnodeoutput,$lngnodestatus);
	@unlink($strtempfile);
	$lngchecks++;
	if ($lngnodestatus===0) {
		echo "  ok    node --check parses ".$strlabel."\n";
	} else {
		$lngfailures++;
		echo "  FAIL  node --check rejected ".$strlabel."\n";
		echo "        ".implode("\n        ",$arrnodeoutput)."\n";
	}
}

function f_payloadfield($arrinput,$strdefaultlang="en") {
	//The name of the field refused, or "" when the input is accepted
	$arrresult=f_feedbackpayload($arrinput,$strdefaultlang);
	return $arrresult["ok"]?"":$arrresult["field"];
}

$arrbase=array(
	"rating" => 1,
	"question" => "Films directed by a woman in 1975",
	"question_hashed" => "0123456789abcdef",
	"request_id" => "req-42",
	"api_version" => "1.1.19",
	"sql_query" => "SELECT ID_MOVIE FROM T_WC_TMDB_MOVIE LIMIT 1",
	"result_entity" => "movie",
	"ui_language" => "fr"
);

// ---------------------------------------------------------------------------
f_group("f_feedbackratingvalue: a vote is 1 or -1, nothing else");

f_check("1",1,f_feedbackratingvalue(1));
f_check("-1",-1,f_feedbackratingvalue(-1));
f_check("the string \"1\"",1,f_feedbackratingvalue("1"));
f_check("the string \"-1\"",-1,f_feedbackratingvalue("-1"));
f_check("0 is not a vote",0,f_feedbackratingvalue(0));
f_check("2 is not a vote",0,f_feedbackratingvalue(2));
f_check("true is not a vote",0,f_feedbackratingvalue(true));
f_check("1.0 is not a vote",0,f_feedbackratingvalue(1.0));
f_check("\"+1\" is not a vote",0,f_feedbackratingvalue("+1"));
f_check("null is not a vote",0,f_feedbackratingvalue(null));

// ---------------------------------------------------------------------------
f_group("f_feedbacktextlength: characters, not bytes, and invalid UTF-8 refused");

f_check("empty",0,f_feedbacktextlength(""));
f_check("ASCII",5,f_feedbacktextlength("hello"));
f_check("accents count once",9,f_feedbacktextlength("réessayez"));
f_check("an emoji counts once",1,f_feedbacktextlength("\u{1F44D}"));
f_check("invalid UTF-8",-1,f_feedbacktextlength("\xff\xfe"));

// ---------------------------------------------------------------------------
f_group("f_feedbackpayload: what the shim forwards");

$arrok=f_feedbackpayload($arrbase,"en");
f_check("a full vote is accepted",true,$arrok["ok"]);
f_check("client is fixed by the server","t2s-front",$arrok["payload"]["client"]);
f_check("the rating is an integer",1,$arrok["payload"]["rating"]);
f_check("the posted language is kept","fr",$arrok["payload"]["ui_language"]);
f_check("the request id travels","req-42",$arrok["payload"]["request_id"]);
f_check("the SQL travels",$arrbase["sql_query"],$arrok["payload"]["sql_query"]);
f_check("no comment, no comment key",false,array_key_exists("comment",$arrok["payload"]));

$arrinput=$arrbase;
$arrinput["client"]="voice-agent";
$arrresult=f_feedbackpayload($arrinput,"en");
f_check("a client posted by the browser is ignored","t2s-front",$arrresult["payload"]["client"]);

$arrinput=$arrbase;
$arrinput["rating"]=-1;
$arrinput["comment"]="  The 1975 film is missing  ";
$arrresult=f_feedbackpayload($arrinput,"en");
f_check("a thumbs down with a comment is accepted",true,$arrresult["ok"]);
f_check("the comment is trimmed","The 1975 film is missing",$arrresult["payload"]["comment"]);
f_check("the rating is -1",-1,$arrresult["payload"]["rating"]);

$arrinput=$arrbase;
$arrinput["comment"]="   ";
$arrresult=f_feedbackpayload($arrinput,"en");
f_check("a blank comment is left out",false,array_key_exists("comment",$arrresult["payload"]));

$arrinput=array("rating" => -1,"question" => "Who directed Jaws?");
$arrresult=f_feedbackpayload($arrinput,"fr");
f_check("a vote with only a question is accepted",true,$arrresult["ok"]);
f_check("and carries only rating, client, language and question",array("rating","client","ui_language","question"),array_keys($arrresult["payload"]));
f_check("the default language applies","fr",$arrresult["payload"]["ui_language"]);

$arrinput=$arrbase;
$arrinput["ui_language"]="de";
$arrresult=f_feedbackpayload($arrinput,"xx");
f_check("an unknown language falls back to English","en",$arrresult["payload"]["ui_language"]);

$arrinput=$arrbase;
$arrinput["request_id"]=12345;
$arrresult=f_feedbackpayload($arrinput,"en");
f_check("an integer identifier becomes a string","12345",$arrresult["payload"]["request_id"]);

f_check("a body that is not an object","body",f_payloadfield("not json"));
f_check("a body that is null","body",f_payloadfield(null));
$arrinput=$arrbase;
unset($arrinput["rating"]);
f_check("a missing rating","rating",f_payloadfield($arrinput));
$arrinput=$arrbase;
$arrinput["rating"]=0;
f_check("a zero rating","rating",f_payloadfield($arrinput));
$arrinput=$arrbase;
$arrinput["rating"]=5;
f_check("a rating of 5","rating",f_payloadfield($arrinput));
$arrinput=$arrbase;
unset($arrinput["question"]);
f_check("a missing question","question",f_payloadfield($arrinput));
$arrinput=$arrbase;
$arrinput["question"]="   ";
f_check("a blank question","question",f_payloadfield($arrinput));
$arrinput=$arrbase;
$arrinput["question"]=array("a");
f_check("a question that is an array","question",f_payloadfield($arrinput));
$arrinput=$arrbase;
$arrinput["question"]=str_repeat("a",4000);
f_check("a question of exactly 4000 characters","",f_payloadfield($arrinput));
$arrinput["question"]=str_repeat("a",4001);
f_check("a question of 4001 characters","question",f_payloadfield($arrinput));
$arrinput=$arrbase;
$arrinput["question"]=str_repeat("é",4000);
f_check("4000 accented characters are 4000, not 8000 bytes","",f_payloadfield($arrinput));
$arrinput=$arrbase;
$arrinput["comment"]=str_repeat("b",2000);
f_check("a comment of exactly 2000 characters","",f_payloadfield($arrinput));
$arrinput["comment"]=str_repeat("b",2001);
f_check("a comment of 2001 characters","comment",f_payloadfield($arrinput));
$arrinput=$arrbase;
$arrinput["comment"]="\xff\xfe";
f_check("a comment in invalid UTF-8","comment",f_payloadfield($arrinput));
$arrinput=$arrbase;
$arrinput["comment"]=true;
f_check("a comment that is a boolean","comment",f_payloadfield($arrinput));
$arrinput=$arrbase;
$arrinput["sql_query"]=str_repeat("x",50001);
f_check("an oversized SQL query","sql_query",f_payloadfield($arrinput));
$arrinput=$arrbase;
$arrinput["request_id"]=str_repeat("r",201);
f_check("an oversized request id","request_id",f_payloadfield($arrinput));

// ---------------------------------------------------------------------------
f_group("f_feedbackapiurl and f_feedbackresponsefields");

f_check("Blue takes the blue URL","https://blue.example.test/feedback",f_feedbackapiurl("Blue","https://blue.example.test/","https://green.example.test"));
f_check("anything else is green","https://green.example.test/feedback",f_feedbackapiurl("Green","https://blue.example.test","https://green.example.test"));
f_check("no base URL, no URL","",f_feedbackapiurl("Green","https://blue.example.test",""));
f_check(
	"only the whitelisted fields go back",
	array("id_feedback"=>7,"status"=>"recorded","cache_bypassed"=>true,"api_version"=>"1.1.20"),
	f_feedbackresponsefields(array("id_feedback"=>"7","status"=>"recorded","cache_bypassed"=>1,"api_version"=>"1.1.20","debug"=>"secret"))
);
f_check("an answer without an identifier is empty",array(),f_feedbackresponsefields(array("status"=>"recorded")));
f_check("an answer that is not an object is empty",array(),f_feedbackresponsefields("oops"));

// ---------------------------------------------------------------------------
f_group("Labels: every key the search page reads exists in English and in French");

$strlanguagesource=file_get_contents(__DIR__."/../lib/language.inc.php");
$strsearchsource=file_get_contents(__DIR__."/../lib/text2sql-search.inc.php");
$strshimsource=file_get_contents(__DIR__."/../lib/feedback.inc.php");
$arrlabelkeys=array(
	"lib_t2s_feedback_question","lib_t2s_feedback_up","lib_t2s_feedback_down",
	"lib_t2s_feedback_comment_label","lib_t2s_feedback_comment_placeholder",
	"lib_t2s_feedback_send","lib_t2s_feedback_skip","lib_t2s_feedback_sending",
	"lib_t2s_feedback_thanks","lib_t2s_feedback_recompute","lib_t2s_feedback_error",
	"lib_t2s_feedback_error_toomany","lib_t2s_feedback_error_unavailable"
);
foreach ($arrlabelkeys as $strkey) {
	f_check("'".$strkey."' is defined in English and in French",2,substr_count($strlanguagesource,"'".$strkey."' =>"));
	f_check("'".$strkey."' is read by the search page or the shim",true,strpos($strsearchsource,"'".$strkey."'")!==false || strpos($strshimsource,"'".$strkey."'")!==false);
}
//The arrays themselves, loaded the way a page loads them, for the two sentences the ticket names.
$struilang="en";
include(__DIR__."/../lib/language.inc.php");
f_check("English question","Was this result right?",get_movie_string('lib_t2s_feedback_question','en'));
f_check("French question","Ce résultat est-il juste ?",get_movie_string('lib_t2s_feedback_question','fr'));
f_check("English thanks","Thanks, noted.",get_movie_string('lib_t2s_feedback_thanks','en'));
f_check("French thanks","Merci, c'est noté.",get_movie_string('lib_t2s_feedback_thanks','fr'));

// ---------------------------------------------------------------------------
f_group("Markup and script, rendered by PHP and parsed by node");

$arrhostile=array(
	"question" => "Who's \"the\" best?\nLine two \\ backslash </script><script>alert(1)</script> é \u{1F44D}",
	"up" => "Yes \"right\" </button>",
	"down" => "No 'wrong'",
	"commentlabel" => "Comment <b>",
	"commentplaceholder" => "What \"was\" wrong?",
	"send" => "Send & go",
	"skip" => "Skip"
);
$strhtml=f_feedbackblockhtml($arrhostile);
f_check("the block is printed hidden, the script shows it",true,strpos($strhtml,"id=\"t2sFeedback\" class=\"t2sFeedback\" role=\"group\" aria-labelledby=\"t2sFeedbackQuestion\" hidden>")!==false);
f_check("both buttons start unpressed",2,substr_count($strhtml,"aria-pressed=\"false\""));
f_check("the status line is polite",true,strpos($strhtml,"role=\"status\" aria-live=\"polite\"")!==false);
f_check("the comment field has a label",true,strpos($strhtml,"<label for=\"t2sFeedbackComment\"")!==false);
f_check("the comment field is capped at the API limit",true,strpos($strhtml,"maxlength=\"2000\"")!==false);
f_check("labels are escaped in attributes",false,strpos($strhtml,"\"right\" </button>")!==false);
f_check("labels are escaped in text",false,strpos($strhtml,"<b>")!==false);

$strjs=f_feedbackjs(
	array(
		"endpoint" => "/app/feedback.php?apicolor=Blue",
		"question" => $arrhostile["question"],
		"question_hashed" => "abc'def",
		"request_id" => "req\"42",
		"api_version" => "1.1.19",
		"sql_query" => "SELECT '</script>' AS X, \"a\\b\"\nFROM T",
		"result_entity" => "movie",
		"ui_language" => "fr"
	),
	array(
		"sending" => "Envoi...",
		"thanks" => "Merci, c'est noté.",
		"recompute" => "Line\nbreak \"quoted\" \\",
		"error" => "Erreur </script>"
	)
);
f_check("no closing script tag can leak out of the block",false,stripos($strjs,"</script")!==false);
f_check("no real newline inside a string: every line ends a statement or a block",true,(function($strcode) {
	foreach (explode("\n",trim($strcode)) as $strline) {
		$strlast=substr(rtrim($strline),-1);
		if ($strlast!==";" && $strlast!=="{" && $strlast!=="}") {
			return false;
		}
	}
	return true;
})($strjs));
f_check("the endpoint is the same-origin shim",true,strpos($strjs,"var strendpoint=\"\\/app\\/feedback.php?apicolor=Blue\";")!==false);
f_check("the script posts JSON",true,strpos($strjs,"'Content-Type':'application/json'")!==false);
f_check("a click on a thumb posts nothing, it only marks the pending vote",false,(bool)preg_match('/function f_fbrate\([^)]*\)\{[^\n]*f_fbpost/',$strjs));
f_check("Send records the vote with its comment",true,strpos($strjs,"objsend.addEventListener('click',function(){f_fbsubmit(f_fbcomment());});")!==false);
f_check("Skip records the vote without a comment",true,strpos($strjs,"objskip.addEventListener('click',function(){f_fbsubmit('');});")!==false);
f_check("the thumb already recorded does not post again",true,strpos($strjs,"if(lngvalue===lngrating&&lngpending===0){return;}")!==false);
f_check("the script never names the API or its key",false,stripos($strjs,"apikey")!==false || stripos($strjs,"text2sql")!==false);
f_nodecheck("the feedback script with hostile values",$strjs);
f_nodecheck("the feedback script with empty values",f_feedbackjs(array(),array()));

//The search page must keep calling the two builders, with the shim URL and outside loadmore.
f_check("the search page includes the helpers",true,strpos($strsearchsource,"include(__DIR__.\"/feedback-ui.inc.php\");")!==false);
f_check("the search page prints the block",true,strpos($strsearchsource,"echo f_feedbackblockhtml(")!==false);
f_check("the search page prints the script",true,strpos($strsearchsource,"echo f_feedbackjs(")!==false);
f_check("the search page points to the same-origin shim",true,strpos($strsearchsource,"\"/feedback.php?apicolor=\"")!==false);
f_check("the block is guarded against loadmore calls",true,strpos($strsearchsource,"if (!\$intloadmore && trim((string)\$strsearch)!=\"\") {")!==false);
f_check("the shell of the shim includes its body",true,strpos((string)file_get_contents(__DIR__."/../html/app/feedback.php"),"include(__DIR__.\"/../../lib/feedback.inc.php\");")!==false);
f_check("the shim refuses every method but POST",true,strpos($strshimsource,"if (\$strfeedbackmethod!==\"POST\") {")!==false);

// ---------------------------------------------------------------------------
echo "\n".$lngchecks." checks, ".$lngfailures." failure(s).\n";
exit($lngfailures>0 ? 1 : 0);
?>
