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
//The model each task uses when nothing is chosen: the one the API colour's .env names. The page
//marks it "(default)" so a visitor can see what a reset would give back.
$strdefaulteemodel=($strapicolor=="Blue"?$strtext2sqlapiblueeellm:$strtext2sqlapigreeneellm);
$strdefaultt2smodel=($strapicolor=="Blue"?$strtext2sqlapibluet2sllm:$strtext2sqlapigreent2sllm);
$strdefaultcomplexmodel=($strapicolor=="Blue"?$strtext2sqlapibluecomplexllm:$strtext2sqlapigreencomplexllm);
$strdefaultresultentitymodel=($strapicolor=="Blue"?$strtext2sqlapiblueresultentityllm:$strtext2sqlapigreenresultentityllm);
$strdefaulttag=" <em style=\"opacity:0.7;\">(default)</em>";
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
	//An instance picked by name (private mode) reached this page rewritten to the Green slot by
	//global-light.inc.php; the cookie keeps the name, or the next page would call the Green slot.
	$strapicolorcookie=($strtext2sqlapiinstancechosen!="" ? "instance-".$strtext2sqlapiinstancechosen : $strapicolor);
	setcookie("apicolor",$strapicolorcookie,$lngcookieexpires,"/");
	$_COOKIE['apicolor']=$strapicolorcookie;
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
//Speed per task: one radio group per task, normal, fast or ultrafast. Kept in the two cookies the
//search reads (fasttasks, ultrafasttasks), a task sitting in one of them at most. The hidden
//`speedset` field says the form was submitted.
if (f_requestisfieldset("speedset")) {
	$arrfastticked=array();
	$arrultrafastticked=array();
	foreach ($arrfasttasks as $strtask=>$strfield) {
		$strspeed=trim(f_request2("speed_".$strtask));
		if ($strspeed=="ultrafast" && isset($arrultrafasttasks[$strtask])) {
			$arrultrafastticked[]=$strtask;
		} elseif ($strspeed=="fast") {
			$arrfastticked[]=$strtask;
		}
	}
	setcookie("fasttasks",implode(",",$arrfastticked),$lngcookieexpires,"/");
	$_COOKIE['fasttasks']=implode(",",$arrfastticked);
	setcookie("ultrafasttasks",implode(",",$arrultrafastticked),$lngcookieexpires,"/");
	$_COOKIE['ultrafasttasks']=implode(",",$arrultrafastticked);
}
$arrfasttasksset=f_text2sqlfasttasks();
$arrultrafasttasksset=f_text2sqlultrafasttasks();

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
		"gpt-6.2-sol"=>"🌞",
		"gpt-6.1-terra"=>"🌍",
		"gpt-6-astra"=>"🌟",
		"gpt-6.2-astra"=>"🌟",
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

	//Private mode: every API instance, by name, with its state and the one the front end uses
	//(the instance behind the default slot, $strtext2sqlapicolor). Picking that one stores the slot
	//itself, so the choice follows the next switch of the clients instead of staying on a name.
	if ($intprivate && empty($intbyok) && count($arrtext2sqlapiinstances)>0) {
		$strliveinstance=f_text2sqlapiinstance($strtext2sqlapicolor);
		$strcurrentinstance=($strtext2sqlapiinstancechosen!="" ? $strtext2sqlapiinstancechosen : f_text2sqlapiinstance($strapicolor));
		echo "<div style=\"margin-top:8px;\"><strong>API instance</strong></div>";
		foreach ($arrtext2sqlapiinstances as $strname=>$strurl) {
			$stranswering=f_text2sqlapiinstanceaturl($strurl);
			$intlive=($strliveinstance!="" && $strname===$strliveinstance);
			$strvalue=($intlive ? $strtext2sqlapicolor : "instance-".$strname);
			$intchecked=($strcurrentinstance!="" && $strname===$strcurrentinstance);
			$strport=(string)parse_url($strurl,PHP_URL_PORT);
			$strstate="";
			if ($stranswering=="") {
				$strstate=" <em style=\"opacity:0.7;\">(not answering)</em>";
			} elseif ($stranswering!==$strname) {
				$strstate=" <em style=\"opacity:0.7;\">(answers as ".htmlspecialchars($stranswering,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').")</em>";
			}
			echo "<label style=\"display:block; margin:4px 0;\">";
			echo "<input type=\"radio\" name=\"apicolor\" value=\"".htmlspecialchars($strvalue,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\"".($intchecked?" checked":"")."> ";
			echo f_apiinstancechip($strname)." ".htmlspecialchars(ucfirst($strname),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').($strport!="" ? " (:".htmlspecialchars($strport,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').")" : "");
			echo $strstate;
			if ($intlive) {
				echo " <strong>used by the front end</strong>".$strdefaulttag;
			}
			echo "</label>";
		}
	}

	//Demo mode: no colour choice (global-light.inc.php ignores it). Private mode lists the
	//instances above instead of the two slots.
	$arrcolors=(empty($intbyok) && !($intprivate && count($arrtext2sqlapiinstances)>0) ? array("Blue","Green") : array());
	if (count($arrcolors)>0) {
		echo "<div style=\"margin-top:8px;\"><strong>API color</strong></div>";
	}
	foreach ($arrcolors as $strcolor) {
		$intchecked=($strapicolor===$strcolor);
		echo "<label style=\"display:block; margin:4px 0;\">";
		echo "<input type=\"radio\" name=\"apicolor\" value=\"".htmlspecialchars($strcolor,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\"".($intchecked?" checked":"")."> ";
		//The slot's name, and the instance that answers on it with its own dot
		$strinstance=f_text2sqlapiinstance($strcolor);
		echo f_apiinstancechip($strinstance)." ".htmlspecialchars($strcolor,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').($strinstance!="" ? " (".htmlspecialchars($strinstance,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').")" : "").($strcolor===$strtext2sqlapicolor?$strdefaulttag:"");
		echo "</label>";
	}

	echo "<div style=\"margin-top:10px;\"><strong>Entity extraction model</strong></div>";
	foreach ($arrallowedmodels as $strmodel) {
		$intchecked=($streemodel===$strmodel);
		$intdefault=($strdefaulteemodel===$strmodel);
		echo "<label style=\"display:block; margin:4px 0;\">";
		echo "<input type=\"radio\" name=\"eemodel\" value=\"".htmlspecialchars($strmodel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\"".($intchecked?" checked":"")."> ";
		echo (isset($arrmodelemojis[$strmodel])?$arrmodelemojis[$strmodel]." ":"").htmlspecialchars($strmodel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').($intdefault?$strdefaulttag:"");
		echo "</label>";
	}

	echo "<div style=\"margin-top:10px;\"><strong>Text-to-SQL model</strong></div>";
	foreach ($arrallowedmodels as $strmodel) {
		$intchecked=($strt2smodel===$strmodel);
		$intdefault=($strdefaultt2smodel===$strmodel);
		echo "<label style=\"display:block; margin:4px 0;\">";
		echo "<input type=\"radio\" name=\"t2smodel\" value=\"".htmlspecialchars($strmodel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\"".($intchecked?" checked":"")."> ";
		echo (isset($arrmodelemojis[$strmodel])?$arrmodelemojis[$strmodel]." ":"").htmlspecialchars($strmodel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').($intdefault?$strdefaulttag:"");
		echo "</label>";
	}

	echo "<div style=\"margin-top:10px;\"><strong>Complex questions model</strong></div>";
	foreach ($arrallowedcomplexmodels as $strmodel) {
		$intchecked=($strcomplexmodel===$strmodel);
		$intdefault=($strdefaultcomplexmodel===$strmodel);
		echo "<label style=\"display:block; margin:4px 0;\">";
		echo "<input type=\"radio\" name=\"complexmodel\" value=\"".htmlspecialchars($strmodel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\"".($intchecked?" checked":"")."> ";
		echo (isset($arrmodelemojis[$strmodel])?$arrmodelemojis[$strmodel]." ":"").htmlspecialchars($strmodel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').($intdefault?$strdefaulttag:"");
		echo "</label>";
	}

	echo "<div style=\"margin-top:10px;\"><strong>Answer-entity classifier model</strong></div>";
	foreach ($arrallowedresultentitymodels as $strmodel) {
		$intchecked=($strresultentitymodel===$strmodel);
		$intdefault=($strdefaultresultentitymodel===$strmodel);
		echo "<label style=\"display:block; margin:4px 0;\">";
		echo "<input type=\"radio\" name=\"resultentitymodel\" value=\"".htmlspecialchars($strmodel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\"".($intchecked?" checked":"")."> ";
		echo (isset($arrmodelemojis[$strmodel])?$arrmodelemojis[$strmodel]." ":"").htmlspecialchars($strmodel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').($intdefault?$strdefaulttag:"");
		echo "</label>";
	}

	//Speed per task: three states, so radio buttons. Ultrafast wins over Fast in the API, and it
	//exists only for GPT-6 Astra, GPT-6.1 Sol, GPT-6.2 Astra and GPT-6.2 Sol (Fast on the other models).
	$arrfastlabels=array(
		"entity_extraction"=>"Entity extraction",
		"text2sql"=>"Text-to-SQL",
		"complex"=>"Complex questions",
		"result_entity"=>"Answer-entity classifier",
		"vision"=>"Picture recognition"
	);
	$arrspeedlabels=array(
		"normal"=>"Normal",
		"fast"=>"⚡ Fast (about twice the price)",
		"ultrafast"=>"🚀 Ultrafast (about six times the price, GPT-6 Astra, GPT-6.1 Sol, GPT-6.2 Astra and Sol only)"
	);
	echo "<div style=\"margin-top:10px;\"><strong>Speed per task (OpenAI service tier)</strong></div>";
	echo "<input type=\"hidden\" name=\"speedset\" value=\"1\">";
	foreach ($arrfastlabels as $strtask=>$strlabel) {
		$strcurrent="normal";
		if (in_array($strtask,$arrultrafasttasksset,true)) {
			$strcurrent="ultrafast";
		} elseif (in_array($strtask,$arrfasttasksset,true)) {
			$strcurrent="fast";
		}
		echo "<fieldset style=\"margin:6px 0; border:1px solid #444; border-radius:6px; padding:4px 10px;\">";
		echo "<legend>".htmlspecialchars($strlabel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."</legend>";
		foreach ($arrspeedlabels as $strspeed=>$strspeedlabel) {
			echo "<label style=\"display:block; margin:2px 0;\">";
			echo "<input type=\"radio\" name=\"speed_".htmlspecialchars($strtask,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\" value=\"".$strspeed."\"".($strcurrent===$strspeed?" checked":"")."> ";
			echo htmlspecialchars($strspeedlabel,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').($strspeed==="normal"?$strdefaulttag:"");
			echo "</label>";
		}
		echo "</fieldset>";
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
