<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
include_once(__DIR__."/watch-country.inc.php");

$intapicolorset=f_requestisfieldset("apicolor");
$inteemodelset=f_requestisfieldset("eemodel");
$intt2smodelset=f_requestisfieldset("t2smodel");
$intcomplexmodelset=f_requestisfieldset("complexmodel");
$intresultentitymodelset=f_requestisfieldset("resultentitymodel");

$lngcookieexpires=time()+(365*24*60*60);
$strwatchcountry="";
$arrwatchcountryoptions=array();
if ($intdbavailable) {
	$strwatchcountry=f_watchcountryresolve();
	$arrwatchcountryoptions=f_watchcountryoptions($struilang);
}

$strapicolor=trim(f_request2("apicolor"));
$streemodel=trim(f_request2("eemodel"));
$strt2smodel=trim(f_request2("t2smodel"));
$strcomplexmodel=trim(f_request2("complexmodel"));
$strresultentitymodel=trim(f_request2("resultentitymodel"));
//The single-value answer task is removed; never sent (an old
//answermodel cookie is ignored).
$stranswermodel="";

if (!$intapicolorset) {
	if (isset($_COOKIE['apicolor'])) {
		$strapicolor=trim($_COOKIE['apicolor']);
	}
}
if (!$inteemodelset) {
	if (isset($_COOKIE['eemodel'])) {
		$streemodel=trim($_COOKIE['eemodel']);
	}
}
if (!$intt2smodelset) {
	if (isset($_COOKIE['t2smodel'])) {
		$strt2smodel=trim($_COOKIE['t2smodel']);
	}
}
if (!$intcomplexmodelset) {
	if (isset($_COOKIE['complexmodel'])) {
		$strcomplexmodel=trim($_COOKIE['complexmodel']);
	}
}
if (!$intresultentitymodelset) {
	if (isset($_COOKIE['resultentitymodel'])) {
		$strresultentitymodel=trim($_COOKIE['resultentitymodel']);
	}
}

switch ($strapicolor) {
case "Blue":
	break;
case "Green":
	break;
default:
	$strapicolor=$strtext2sqlapicolor;
	break;
}
if (!in_array($streemodel,$arrallowedmodels,true)) {
	$streemodel="";
}
if (!in_array($strt2smodel,$arrallowedmodels,true)) {
	$strt2smodel="";
}
if (!in_array($strcomplexmodel,$arrallowedcomplexmodels,true)) {
	$strcomplexmodel="";
}
if (!in_array($strresultentitymodel,$arrallowedresultentitymodels,true)) {
	$strresultentitymodel="";
}
if ($streemodel=="") {
	$streemodel=($strapicolor=="Blue"?$strtext2sqlapiblueeellm:$strtext2sqlapigreeneellm);
}
if ($strt2smodel=="") {
	$strt2smodel=($strapicolor=="Blue"?$strtext2sqlapibluet2sllm:$strtext2sqlapigreent2sllm);
}
if ($strcomplexmodel=="") {
	$strcomplexmodel=($strapicolor=="Blue"?$strtext2sqlapibluecomplexllm:$strtext2sqlapigreencomplexllm);
}
if ($strresultentitymodel=="") {
	$strresultentitymodel=($strapicolor=="Blue"?$strtext2sqlapiblueresultentityllm:$strtext2sqlapigreenresultentityllm);
}

if ($intapicolorset) {
	setcookie("apicolor",$strapicolor,$lngcookieexpires,"/");
	$_COOKIE['apicolor']=$strapicolor;
}
if ($inteemodelset) {
	setcookie("eemodel",$streemodel,$lngcookieexpires,"/");
	$_COOKIE['eemodel']=$streemodel;
}
if ($intt2smodelset) {
	setcookie("t2smodel",$strt2smodel,$lngcookieexpires,"/");
	$_COOKIE['t2smodel']=$strt2smodel;
}
if ($intresultentitymodelset) {
	setcookie("resultentitymodel",$strresultentitymodel,$lngcookieexpires,"/");
	$_COOKIE['resultentitymodel']=$strresultentitymodel;
}
if ($intcomplexmodelset) {
	setcookie("complexmodel",$strcomplexmodel,$lngcookieexpires,"/");
	$_COOKIE['complexmodel']=$strcomplexmodel;
}
//An unticked checkbox is not sent at all, so the hidden `fastset` field says
//the form was submitted and the cookie is rewritten from the ticked boxes only.
if (f_requestisfieldset("fastset")) {
	$arrfastticked=array();
	foreach ($arrfasttasks as $strtask=>$strfield) {
		if (trim(f_request2("fast_".$strtask))=="1") {
			$arrfastticked[]=$strtask;
		}
	}
	setcookie("fasttasks",implode(",",$arrfastticked),$lngcookieexpires,"/");
	$_COOKIE['fasttasks']=implode(",",$arrfastticked);
}
$arrfasttasksset=f_text2sqlfasttasks();

$strreturnurl=trim(f_request2("return"));
if ($strreturnurl=="") {
	$strreturnurl="index.php";
}

 // Sanitize return URL to avoid open redirects / javascript: links
 $strreturnurlcandidate=$strreturnurl;
 $strreturnurlcandidate=str_replace(array("\r","\n"),"",$strreturnurlcandidate);
 if (strpos($strreturnurlcandidate,"://")!==false || strpos($strreturnurlcandidate,"\\")!==false) {
 	$strreturnurlcandidate="";
 }
 if (preg_match('/^\s*(?:javascript|data):/i',$strreturnurlcandidate)) {
 	$strreturnurlcandidate="";
 }
 if ($strreturnurlcandidate!="") {
 	if ($strreturnurlcandidate[0]=='/') {
 		$strreturnurlcandidate=ltrim($strreturnurlcandidate,'/');
 	}
 	if (strpos($strreturnurlcandidate,'..')!==false) {
 		$strreturnurlcandidate="";
 	}
 }
 if ($strreturnurlcandidate=="") {
 	$strreturnurl="index.php";
 } else {
 	$strreturnurl=$strreturnurlcandidate;
 }

if ($intdbavailable) {
	f_headerdisplay(get_movie_string('lib_text2sql_settings'));
	echo "<h1><a href=\"text2sql-settings.php\">".get_movie_string('lib_text2sql_settings')."</a></h1>";

	$arrmodelemojis=array(
		"gpt-6-sol"=>"🌞",
		"gpt-6.1-sol"=>"🌞",
		"gpt-6.1-terra"=>"🌍",
		"gpt-6-astra"=>"🌟",
		"gpt-6-luna"=>"🌕",
		"gpt-6-luna-decisions"=>"🎯",
		"claude-opus-5-5"=>"🟧"
	);

	echo "<form method=\"GET\" style=\"margin-top:8px;\">";
	echo "<input type=\"hidden\" name=\"return\" value=\"".htmlspecialchars($strreturnurl,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\">";

	echo "<div style=\"margin-top:8px;\"><strong>".get_movie_string('lib_watch_country')."</strong></div>";
	echo "<select name=\"watch_country\">";
	echo "<option value=\"\"".($strwatchcountry===""?" selected":"").">".
		htmlspecialchars(get_movie_string('lib_watch_country_choose'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."</option>";
	foreach ($arrwatchcountryoptions as $strcountrycode=>$strcountryname) {
		echo "<option value=\"".htmlspecialchars($strcountrycode,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\"".
			($strwatchcountry===$strcountrycode?" selected":"").">".
			htmlspecialchars($strcountryname,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')." (".
			htmlspecialchars($strcountrycode,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').")</option>";
	}
	echo "</select>";

	echo "<div style=\"margin-top:8px;\"><strong>API color</strong></div>";
	foreach (array("Blue","Green") as $strcolor) {
		$intchecked=($strapicolor===$strcolor);
		echo "<label style=\"display:block; margin:4px 0;\">";
		echo "<input type=\"radio\" name=\"apicolor\" value=\"".htmlspecialchars($strcolor,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\"".($intchecked?" checked":"")."> ";
		echo ($strcolor==="Blue"?"🔵 ":"🟢 ").htmlspecialchars($strcolor,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
		echo "</label>";
	}

	echo "<div style=\"margin-top:10px;\"><strong>Entity extraction model</strong></div>";
	foreach ($arrallowedmodels as $strmodel) {
		$intchecked=($streemodel===$strmodel);
		echo "<label style=\"display:block; margin:4px 0;\">";
		echo "<input type=\"radio\" name=\"eemodel\" value=\"".htmlspecialchars($strmodel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\"".($intchecked?" checked":"")."> ";
		echo (isset($arrmodelemojis[$strmodel])?$arrmodelemojis[$strmodel]." ":"").htmlspecialchars($strmodel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
		echo "</label>";
	}

	echo "<div style=\"margin-top:10px;\"><strong>Text-to-SQL model</strong></div>";
	foreach ($arrallowedmodels as $strmodel) {
		$intchecked=($strt2smodel===$strmodel);
		echo "<label style=\"display:block; margin:4px 0;\">";
		echo "<input type=\"radio\" name=\"t2smodel\" value=\"".htmlspecialchars($strmodel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\"".($intchecked?" checked":"")."> ";
		echo (isset($arrmodelemojis[$strmodel])?$arrmodelemojis[$strmodel]." ":"").htmlspecialchars($strmodel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
		echo "</label>";
	}

	echo "<div style=\"margin-top:10px;\"><strong>Complex questions model</strong></div>";
	foreach ($arrallowedcomplexmodels as $strmodel) {
		$intchecked=($strcomplexmodel===$strmodel);
		echo "<label style=\"display:block; margin:4px 0;\">";
		echo "<input type=\"radio\" name=\"complexmodel\" value=\"".htmlspecialchars($strmodel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\"".($intchecked?" checked":"")."> ";
		echo (isset($arrmodelemojis[$strmodel])?$arrmodelemojis[$strmodel]." ":"").htmlspecialchars($strmodel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
		echo "</label>";
	}

	echo "<div style=\"margin-top:10px;\"><strong>Answer-entity classifier model</strong></div>";
	foreach ($arrallowedresultentitymodels as $strmodel) {
		$intchecked=($strresultentitymodel===$strmodel);
		echo "<label style=\"display:block; margin:4px 0;\">";
		echo "<input type=\"radio\" name=\"resultentitymodel\" value=\"".htmlspecialchars($strmodel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\"".($intchecked?" checked":"")."> ";
		echo (isset($arrmodelemojis[$strmodel])?$arrmodelemojis[$strmodel]." ":"").htmlspecialchars($strmodel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
		echo "</label>";
	}

	//OpenAI Fast mode per task.
	$arrfastlabels=array(
		"entity_extraction"=>"Entity extraction",
		"text2sql"=>"Text-to-SQL",
		"complex"=>"Complex questions",
		"result_entity"=>"Answer-entity classifier"
	);
	echo "<div style=\"margin-top:10px;\"><strong>⚡ OpenAI Fast mode (faster, about twice the price, GPT-6 only)</strong></div>";
	echo "<input type=\"hidden\" name=\"fastset\" value=\"1\">";
	foreach ($arrfastlabels as $strtask=>$strlabel) {
		$intchecked=in_array($strtask,$arrfasttasksset,true);
		echo "<label style=\"display:block; margin:4px 0;\">";
		echo "<input type=\"checkbox\" name=\"fast_".htmlspecialchars($strtask,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\" value=\"1\"".($intchecked?" checked":"")."> ";
		echo htmlspecialchars($strlabel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
		echo "</label>";
	}

	echo "<div style=\"margin-top:10px;\">";
	echo "<input type=\"submit\" value=\"Apply\">";
	echo "</div>";
	echo "</form>";

	echo "<p><a href=\"".htmlspecialchars($strreturnurl,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\">Back</a></p>";

	f_footerdisplay();
} else {
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
