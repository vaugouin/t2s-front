<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

//Filter request.readwrite: a plugin may need the read-write database account for this request.
//Plugins are loaded here, before global-light.inc.php opens the connection, and only in private
//mode; the lock of lib/private-mode.inc.php is applied before the account is asked for.
include_once(__DIR__."/private-mode.inc.php");
include_once(__DIR__."/plugins.inc.php");
f_pluginsload(f_privatemoderequest());
if (f_pluginfilter("request.readwrite",false,array("page"=>"text2sql-search"))) {
	f_privatemoderequire();
	$intdbreadwrite=true;
}

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
//Side-effect-free helpers of the picture-based search: format sniffing, image_ref
//validation, and the JavaScript of the "+" button. The upload itself never happens here, it goes
//through the same-origin shim html/app/upload-vision.php, which is where the API key stays.
include(__DIR__."/vision-image.inc.php");
//Side-effect-free mirror of voice-agent's result representations (number card,
//stat strip, ranked bars, table), chosen from the hint the API returns.
include(__DIR__."/t2s-representation.inc.php");

if (!function_exists('f_htmlspecialchars')) {
	function f_htmlspecialchars($val) {
		return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('f_htmlattr')) {
	function f_htmlattr($val) {
		$str=(string)$val;
		$str=str_replace(array("\r","\n")," ",$str);
		$str=trim(preg_replace('/\s+/', ' ', strip_tags($str)));
		return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

// Builds the hover-reveal panel (E1) for a card.
// $strtitle  - displayed title
// $arrmeta   - list of short meta strings (joined with " · ")
// $strrating - optional rating (float or "")
// $stroverview - optional description/tagline/overview text
if (!function_exists('f_buildcardreveal')) {
	function f_buildcardreveal($strtitle, $arrmeta=array(), $strrating="", $stroverview="") {
		$out="<div class=\"search-poster-card-reveal\">";
		$arrmeta=array_filter($arrmeta, function($v){ return $v!==null && trim((string)$v)!==""; });
		if (count($arrmeta)>0) {
			$out.="<div class=\"search-poster-card-reveal-meta\">".f_htmlspecialchars(implode(" · ", $arrmeta))."</div>";
		}
		if ($strrating!=="" && (float)$strrating>0) {
			$out.="<div class=\"search-poster-card-reveal-rating\">★ ".f_htmlspecialchars(number_format((float)$strrating, 1))."</div>";
		}
		if (trim((string)$stroverview)!=="") {
			$out.="<div class=\"search-poster-card-reveal-overview\">".f_htmlspecialchars($stroverview)."</div>";
		}
		$out.="</div>";
		return $out;
	}
}

if (!function_exists('f_t2s_pretty_label')) {
	function f_t2s_pretty_label($strkey) {
		$strlabel=trim((string)$strkey);
		if ($strlabel=="") {
			return "Value";
		}
		$strlabel=str_replace(array("(",")","*")," ",$strlabel);
		$strlabel=str_replace(array("_","-")," ",$strlabel);
		$strlabel=preg_replace('/\s+/', ' ', $strlabel);
		$strlabel=trim($strlabel);
		if ($strlabel=="") {
			return "Value";
		}
		return ucwords(strtolower($strlabel));
	}
}

if (!function_exists('f_t2s_format_scalar_value')) {
	function f_t2s_format_scalar_value($strkey, $value) {
		$strvalueclean=trim((string)$value);
		if ($strvalueclean=="") {
			return "";
		}
		$strkeyupper=strtoupper(trim((string)$strkey));
		if (is_numeric($strvalueclean)) {
			if ($strkeyupper=="BUDGET" || $strkeyupper=="REVENUE" || strpos($strkeyupper,"AMOUNT")!==false || strpos($strkeyupper,"TOTAL_")===0 || substr($strkeyupper,-4)=="_SUM") {
				return "$".number_format((float)$strvalueclean,0,'.',',');
			}
			if (strpos($strkeyupper,"AVERAGE")!==false || strpos($strkeyupper,"AVG")!==false || strpos($strkeyupper,"MEAN")!==false || $strkeyupper=="VOTE_AVERAGE" || $strkeyupper=="IMDB_RATING") {
				return number_format((float)$strvalueclean,1,'.',',');
			}
			if ((float)$strvalueclean==(float)(int)$strvalueclean) {
				return number_format((float)$strvalueclean,0,'.',',');
			}
			return number_format((float)$strvalueclean,2,'.',',');
		}
		return $strvalueclean;
	}
}

//The poster of an entity row, for the poster ranking of ranked bars, or null
//when the row has none (then the ranking falls back to bars, as in voice-agent).
function f_t2s_rep_poster($rstemprecord) {
	$arrkinds=array(
		array("ID_MOVIE","POSTER_PATH","MOVIE_TITLE","movie.php?movieid="),
		array("ID_SERIE","POSTER_PATH","SERIE_TITLE","serie.php?serieid="),
		array("ID_PERSON","PROFILE_PATH","PERSON_NAME","person.php?personid="),
	);
	//The movie+series UNION (ID_CONTENT, CONTENT_TYPE, CONTENT_TITLE, as for a collection that holds
	//both): "Rank the Harry Potter films by IMDb rating" fell to bars for want of this kind (2026-11-08).
	if (isset($rstemprecord["ID_CONTENT"]) && isset($rstemprecord["POSTER_PATH"]) && trim((string)$rstemprecord["POSTER_PATH"])!="") {
		$strtype=strtolower(isset($rstemprecord["CONTENT_TYPE"]) ? (string)$rstemprecord["CONTENT_TYPE"] : "movie");
		$strhref=(strpos($strtype, "serie")===0 ? "serie.php?serieid=" : "movie.php?movieid=").$rstemprecord["ID_CONTENT"];
		return array("url"=>f_getmovieposterpicture((string)$rstemprecord["POSTER_PATH"],4),"title"=>isset($rstemprecord["CONTENT_TITLE"]) ? (string)$rstemprecord["CONTENT_TITLE"] : "","href"=>$strhref);
	}
	foreach ($arrkinds as $arrkind) {
		if (isset($rstemprecord[$arrkind[0]]) && isset($rstemprecord[$arrkind[1]]) && trim((string)$rstemprecord[$arrkind[1]])!="") {
			$strpath=(string)$rstemprecord[$arrkind[1]];
			$strurl=$arrkind[1]=="PROFILE_PATH" ? f_getpersonprofilepicture($strpath,2) : f_getmovieposterpicture($strpath,4);
			return array("url"=>$strurl,"title"=>isset($rstemprecord[$arrkind[2]]) ? (string)$rstemprecord[$arrkind[2]] : "","href"=>$arrkind[3].$rstemprecord[$arrkind[0]]);
		}
	}
	return null;
}

if (!function_exists('f_t2s_build_aggregate_card')) {
	function f_t2s_build_aggregate_card($rstemprecord) {
		if (!is_array($rstemprecord) || count($rstemprecord)==0) {
			return "";
		}
		$arritems=array();
		foreach ($rstemprecord as $strkey => $strvalue) {
			$strformatted=f_t2s_format_scalar_value($strkey, $strvalue);
			if ($strformatted!="") {
				$arritems[]="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars(f_t2s_pretty_label($strkey))."</div><div class=\"search-poster-card-year\">".f_htmlspecialchars($strformatted)."</div></div>";
			}
		}
		if (count($arritems)==0) {
			return "";
		}
		return implode("", $arritems);
	}
}

$intrenderset=f_requestisfieldset("render");
$intapicolorset=f_requestisfieldset("apicolor");
$inteemodelset=f_requestisfieldset("eemodel");
$intt2smodelset=f_requestisfieldset("t2smodel");
$intcomplexmodelset=f_requestisfieldset("complexmodel");
$intresultentitymodelset=f_requestisfieldset("resultentitymodel");
if (session_status()===PHP_SESSION_NONE) {
	session_start();
}
$lngcookieexpires=time()+(365*24*60*60);
$strrender=trim(strtolower(f_request2("render")));
$strapicolor=trim(f_request2("apicolor"));
$streemodel=trim(f_request2("eemodel"));
$strt2smodel=trim(f_request2("t2smodel"));
$strcomplexmodel=trim(f_request2("complexmodel"));
$strresultentitymodel=trim(f_request2("resultentitymodel"));
//The single-value answer task is removed; never sent (an old
//answermodel cookie is ignored).
$stranswermodel="";

if ($strrender=="poster") {
	$strrender="card";
}
//Render= also carries a representation chosen with the chips (value-cards,
//ranked-bars, table...). It is not the card/grid mode: it overrides the API default for this
//question only, travels in the URL (shareable, survives a reload) and is never written to the
//session, so the next question opens on its own default and a "grid" session stays "grid".
$strt2srepoverride="";
if (in_array($strrender, f_t2srep_known(), true)) {
	$strt2srepoverride=$strrender;
	$strrender="";
	$intrenderset=false;
}
if (!$intrenderset) {
	if (isset($_SESSION['render'])) {
		$strrender=$_SESSION['render'];
	}
}
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
switch ($strrender) {
case "card":
	break;
case "grid":
	break;
default:
	$strrender="card";
	break;
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
//The classifier answers one word out of a short prompt, so it takes the same list as the
//other two fast tasks.
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
//echo "<br />".$strapicolor;
if ($intrenderset) {
	$_SESSION['render']=$strrender;
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
if ($intcomplexmodelset) {
	setcookie("complexmodel",$strcomplexmodel,$lngcookieexpires,"/");
	$_COOKIE['complexmodel']=$strcomplexmodel;
}
if ($intresultentitymodelset) {
	setcookie("resultentitymodel",$strresultentitymodel,$lngcookieexpires,"/");
	$_COOKIE['resultentitymodel']=$strresultentitymodel;
}
$strrenderalt=($strrender=="grid"?"card":"grid");
$strapicoloralt=($strapicolor=="Blue"?"Green":"Blue");
$arrurlparsed=parse_url($_SERVER['REQUEST_URI']);
$arrurlquery=array();
if (isset($arrurlparsed['query'])) {
	parse_str($arrurlparsed['query'],$arrurlquery);
}
$arrurlquery['render']=$strrenderalt;
//$arrurlquery['apicolor']=$strapicoloralt;
$strrenderalturl=(isset($arrurlparsed['path'])?$arrurlparsed['path']:'').(count($arrurlquery)>0?"?".http_build_query($arrurlquery):"").(isset($arrurlparsed['fragment'])?"#".$arrurlparsed['fragment']:'');

$intloadmore=(f_request2("loadmore")=="1");
//A loadmore call names the view it feeds: the cards by default, "timeline" for the script that
//appends the next pages to a timeline (f_t2srep_timeline_more_js())
$strt2sloadmoreview=trim(strtolower(f_request2("loadmoreview")));
//Key column of the result (the identifier that names its entity) and the values met while
//rendering; handed to the search.result.after hook
$strresultkeycolumn="";
$arrresultkeyvalues=array();
$strsearch=trim(f_request2("search"));
$strsearchescaped=$strsearch;
//echo "<br \>\$strsearch='$strsearch'";
$strsearch = str_replace("&\#039;","'",$strsearch);
$strquestionhashed=f_request2("questionhashed");
//The image travels with the question and with the pagination, exactly as
//question_hashed does. The value is read RAW rather than through f_request2(): it is checked
//against the house filename pattern right after, and a whitelist that strict is a stronger filter
//than the generic one, which could quietly reshape a legitimate reference and break the feature
//without a single error message.
$strimageref=isset($_GET['imageref'])?trim((string)$_GET['imageref']):"";
if ($strimageref==="" && isset($_POST['imageref'])) {
	$strimageref=trim((string)$_POST['imageref']);
}
if (!f_visionimagerefisvalid($strimageref)) {
	$strimageref="";
}
//Same-origin URL of the upload shim, in the folder the visitor is browsing: every URL prefix
//serves the same pages, so the shim exists under each of them.
$strvisionshimurl=rtrim(str_replace("\\","/",dirname((string)$strphpself)),"/")."/upload-vision.php";
$strvisionendpointurl=$strvisionshimurl."?apicolor=".rawurlencode($strapicolor);
$strimagerefurlpart=($strimageref!=""?"&imageref=".rawurlencode($strimageref):"");
$lngpage=f_stringtolong(f_string(f_request2("page"),"1"));
//$intdebug=1;
if ($intdebug) {
	echo "<br \>\$strsearch='$strsearch'";
}
$lngmovieid=0;
$lngmaxLength=150;

if ($intdbavailable) {
	//Database is available
	if (!$intloadmore) {
		$strh1extra="<a href=\"text2sql-settings.php\" alt=\"".get_movie_string('lib_text2sql_settings')."\" title=\"".get_movie_string('lib_text2sql_settings')."\" class=\"no-hover\">⚙️</a>";
		switch ($strapicolor) {
		case "Blue":
			$strcoloralturl="index.php?search=".urlencode($strsearch)."&amp;apicolor=Green";
			$strh1extra.=" <a href=\"".$strcoloralturl."\" alt=\"".get_movie_string('lib_t2s_api_blue_to_green')."\" title=\"".get_movie_string('lib_t2s_api_blue_to_green')."\" class=\"no-hover\">🔵</a>";
			break;
		case "Green":
			$strcoloralturl="index.php?search=".urlencode($strsearch)."&amp;apicolor=Blue";
			$strh1extra.=" <a href=\"".$strcoloralturl."\" alt=\"".get_movie_string('lib_t2s_api_green_to_blue')."\" title=\"".get_movie_string('lib_t2s_api_green_to_blue')."\" class=\"no-hover\">🟢</a>";
			break;
		}
		f_headerdisplay($strsearch." ".get_movie_string('lib_text2sql_search'),$strh1extra);
		echo "<form method=\"GET\" name=\"frmmain\" id=\"frmmain\">";
		//The id makes this box the drop target of the drag and drop: a file dragged
		//from the file manager onto it is attached exactly as the "choose a photo" entry attaches
		//it. The listeners are added by f_visionsearchjs(), which returns before adding any when the
		//page carries no "+" button, so no other page of the front is touched.
		echo "<div id=\"searchImageDropZone\" style=\"position: relative;\">";
		//The left padding reserves the room of the "+" exactly as the right padding
		//already reserves the room of the submit arrow; both buttons sit in the same relative box.
		echo "<textarea id=\"search\" name=\"search\" cols=\"50\" rows=\"1\" class=\"responsive-input\" style=\"overflow-y:auto; resize:none; padding-right: 48px; padding-left: 48px;\" placeholder=\"".get_movie_string('lib_t2s_search_placeholder')."\" maxlength=\"10000000\" wrap=\"\">";
		echo str_replace("\\r","\r",str_replace("\\n","\n",str_replace("<","&lt;",$strsearch)));
		echo "</textarea>";
		echo "<button type=\"button\" id=\"searchSubmitArrow\" aria-label=\"Submit\" title=\"Submit\" style=\"display:none; position:absolute; right:8px; top:8px; width:34px; height:34px; border-radius:50%; border:1px solid #999; background:#fff; cursor:pointer; line-height:32px; text-align:center; padding:0;\">&#x2191;</button>";
		//The "+" is a character, not an image: the icon of the copy button is referenced relatively
		//to the served folder, so a picture here would have to be reachable under images/ of every URL prefix.
		echo "<button type=\"button\" id=\"searchImageButton\" aria-haspopup=\"true\" aria-expanded=\"false\" aria-label=\"".f_htmlattr(get_movie_string('lib_t2s_image_add'))."\" title=\"".f_htmlattr(get_movie_string('lib_t2s_image_add'))."\" style=\"position:absolute; left:8px; top:8px; width:34px; height:34px; border-radius:50%; border:1px solid #999; background:#fff; color:#222; cursor:pointer; font-size:20px; line-height:32px; text-align:center; padding:0;\">+</button>";
		echo "<div id=\"searchImageMenu\" role=\"menu\" style=\"display:none; position:absolute; left:8px; top:48px; z-index:30; min-width:190px; background:#fff; color:#222; border:1px solid #999; border-radius:6px; box-shadow:0 2px 6px rgba(0,0,0,0.35); padding:4px 0;\">";
		echo "<button type=\"button\" id=\"searchImageMenuLibrary\" role=\"menuitem\" style=\"display:block; width:100%; background:none; border:none; color:inherit; cursor:pointer; font-size:16px; padding:8px 12px; text-align:left;\">".f_htmlspecialchars(get_movie_string('lib_t2s_image_from_library'))."</button>";
		//The camera entry is removed by the script when no camera can answer it, so a desktop does
		//not get two entries that open the same file picker.
		echo "<button type=\"button\" id=\"searchImageMenuCamera\" role=\"menuitem\" style=\"display:block; width:100%; background:none; border:none; color:inherit; cursor:pointer; font-size:16px; padding:8px 12px; text-align:left;\">".f_htmlspecialchars(get_movie_string('lib_t2s_image_from_camera'))."</button>";
		echo "</div>";
		//No name attribute on the two file inputs: the form is a GET form and must stay one, so the
		//bytes leave outside it, in JavaScript, before the question is submitted. Turning the form
		//into a POST multipart would break every shareable ?search=... URL, the colour switch and
		//the whole pagination.
		echo "<input type=\"file\" id=\"searchImageFileLibrary\" accept=\"image/*\" style=\"display:none;\">";
		echo "<input type=\"file\" id=\"searchImageFileCamera\" accept=\"image/*\" capture=\"environment\" style=\"display:none;\">";
		echo "<input type=\"hidden\" name=\"imageref\" id=\"searchImageRef\" value=\"".f_htmlattr($strimageref)."\">";
		echo "</div>";
		//The thumbnail is served back through the shim rather than kept in the page, so it survives
		//a page load, a pagination click and a colour switch. A purged image fails on the <img>,
		//which the script turns into the retention sentence.
		echo "<div id=\"searchImageStatus\" style=\"display:".($strimageref!=""?"flex":"none")."; align-items:center; gap:8px; margin-top:6px;\">";
		echo "<img id=\"searchImagePreview\" alt=\"".f_htmlattr(get_movie_string('lib_t2s_image_alt'))."\"".($strimageref!=""?" src=\"".f_htmlattr($strvisionshimurl."?imageref=".rawurlencode($strimageref)."&apicolor=".rawurlencode($strapicolor))."\"":"")." style=\"display:".($strimageref!=""?"inline-block":"none")."; max-height:56px; max-width:56px; border-radius:4px;\">";
		echo "<span id=\"searchImageMessage\">".($strimageref!=""?f_htmlspecialchars(get_movie_string('lib_t2s_image_attached')):"")."</span>";
		echo "<button type=\"button\" id=\"searchImageClear\" aria-label=\"".f_htmlattr(get_movie_string('lib_t2s_image_remove'))."\" title=\"".f_htmlattr(get_movie_string('lib_t2s_image_remove'))."\" style=\"background:none; border:none; color:inherit; cursor:pointer; font-size:18px; line-height:1; padding:2px 6px;\">&#x00D7;</button>";
		echo "</div>";
		echo "<script>\n";
		//--- search-box script: begin ---
		//Everything between these two markers is rendered by test/vision-image-test.php and parsed
		//by node --check. Keep every line a plain literal echo, with no PHP interpolation at all:
		//the test refuses a "$" here and tells you to move the code into a rendering function, the
		//way f_visionsearchjs() does, where json_encode can do the escaping.
		echo "(function(){\n";
		echo "var textarea=document.getElementById('search');\n";
		echo "var form=document.getElementById('frmmain');\n";
		echo "var submitArrow=document.getElementById('searchSubmitArrow');\n";
		//The hidden field of the "+" button, read from this closure so the arrow
		//knows whether a photo is attached. The two blocks never share a variable: this one reads
		//the DOM, and the other one tells it when the value changed, through t2simagerefchange.
		echo "var imageRef=document.getElementById('searchImageRef');\n";
		echo "if(!textarea){return;}\n";
		echo "function resizeSearchTextarea(){var rect=textarea.getBoundingClientRect();var viewportHeight=window.innerHeight||document.documentElement.clientHeight||800;var maxHeight=Math.max(80,Math.floor(viewportHeight-rect.top-120));textarea.style.height='auto';textarea.style.overflowY='hidden';var nextHeight=Math.min(textarea.scrollHeight,maxHeight);textarea.style.height=nextHeight+'px';textarea.style.overflowY=textarea.scrollHeight>maxHeight?'auto':'hidden';}\n";
		//One predicate, three callers: the arrow, the Enter key and the click. A
		//photo alone is a question, since the vision pre-stage of the API composes the
		//words from what it reads in it. The front never invents a default question here.
		echo "function hasSearchContent(){var hasText=textarea.value&&textarea.value.trim().length>0;var hasImage=imageRef&&imageRef.value&&imageRef.value.length>0;return hasText||hasImage?true:false;}\n";
		echo "function updateSubmitArrow(){if(!submitArrow){return;}submitArrow.style.display=hasSearchContent()?'block':'none';}\n";
		echo "function refreshSearchTextarea(){resizeSearchTextarea();updateSubmitArrow();}\n";
		echo "function refreshSearchTextareaDelayed(){window.setTimeout(refreshSearchTextarea,0);window.setTimeout(refreshSearchTextarea,50);window.setTimeout(refreshSearchTextarea,250);}\n";
		echo "function submitSearch(){if(form&&form.submit){form.submit();}}\n";
		echo "textarea.addEventListener('input',refreshSearchTextarea);\n";
		echo "textarea.addEventListener('change',refreshSearchTextarea);\n";
		echo "textarea.addEventListener('paste',refreshSearchTextareaDelayed);\n";
		echo "textarea.addEventListener('focus',refreshSearchTextareaDelayed);\n";
		echo "textarea.addEventListener('keyup',refreshSearchTextarea);\n";
		echo "textarea.addEventListener('touchend',refreshSearchTextareaDelayed,{passive:true});\n";
		echo "textarea.addEventListener('keydown',function(e){var isEnter=e&&(e.key==='Enter'||e.keyCode===13||e.which===13);if(isEnter&&!e.shiftKey&&hasSearchContent()){e.preventDefault();submitSearch();}});\n";
		echo "if(submitArrow){submitArrow.addEventListener('click',function(){refreshSearchTextarea();if(hasSearchContent()){submitSearch();}});}\n";
		//The event the "+" block dispatches on the hidden field when it attaches or
		//removes a photo. It bubbles up to document, which is the only thing these two sealed
		//closures share; neither of them puts anything on window.
		echo "document.addEventListener('t2simagerefchange',updateSubmitArrow);\n";
		echo "window.addEventListener('resize',resizeSearchTextarea,{passive:true});\n";
		echo "resizeSearchTextarea();\n";
		echo "updateSubmitArrow();\n";
		echo "})();\n";
		//--- search-box script: end ---
		echo "</script>";
		//The whole script of the "+" is built in a function of a side-effect-free
		//file, where json_encode escapes every string crossing PHP into JavaScript and where
		//test/vision-image-test.php can render it and run node --check on the result.
		echo "<script>\n";
		echo f_visionsearchjs(
			$strvisionendpointurl,
			f_visionmaxuploadbytes(),
			f_visionmaximageside(),
			array(
				"attached" => (string)get_movie_string('lib_t2s_image_attached'),
				"sending" => (string)get_movie_string('lib_t2s_image_sending'),
				"errorformat" => (string)get_movie_string('lib_t2s_image_error_format'),
				"errorbig" => (string)get_movie_string('lib_t2s_image_error_toobig'),
				"errorupload" => (string)get_movie_string('lib_t2s_image_error_upload'),
				"errorexpired" => (string)get_movie_string('lib_t2s_image_expired'),
				"drophint" => (string)get_movie_string('lib_t2s_image_drop_hint'),
				"dropmultiple" => (string)get_movie_string('lib_t2s_image_drop_multiple'),
				"dropnofile" => (string)get_movie_string('lib_t2s_image_drop_nofile')
			)
		);
		echo "</script>";
		//echo "<br />";
		//echo "<input type=\"submit\" name=\"val\" value=\"".get_movie_string('lib_search_button')."\" >";
		echo " ";
		echo "<a href=\"".$strrenderalturl."\">".$strlibswitchrender."</a>";
		echo "</form>";
	}
	//The third term is the whole point of the ticket: without it a form carrying
	//nothing but the hidden imageref would render a page with no result block at all, in silence,
	//and the arrow fixed just above would look broken. The API validator has accepted a request
	//that carries an image_ref alone.
	if ($strsearch!="" || $strquestionhashed!="" || $strimageref!="") {
		//We display the result for the current page
		$arrtext2sql=f_movietext2sqlsearch($strsearch,$strquestionhashed,$lngpage,$streemodel,$strt2smodel,$strcomplexmodel,$strresultentitymodel,$stranswermodel,$strimageref);
		$jsontext2sql=json_decode($arrtext2sql,true);
		if ($intdebug) {
			echo "<br />";
			print_r($jsontext2sql);
		}
		//Read with isset(): a 422 is refused by the validator before the endpoint
		//runs, so it is not shaped like a Text2SQLResponse and carries none of these four keys.
		$strsqlquery=isset($jsontext2sql['sql_query'])?(string)$jsontext2sql['sql_query']:"";
		$strerror=isset($jsontext2sql['error'])?(string)$jsontext2sql['error']:"";
		$stranswer=isset($jsontext2sql['answer']) ? (string)$jsontext2sql['answer'] : "";
		$strjustification=isset($jsontext2sql['justification'])?(string)$jsontext2sql['justification']:"";
		//And turned into a sentence rather than left as a bare status code. The localized label is
		//passed for the one failure this ticket creates: a photo sent with no words to a colour
		//that predates the picture search, ignores image_ref, and then sees an empty request.
		if ($strerror=="") {
			$strerror=f_visionapierrormessage(
				$jsontext2sql,
				($strsearch=="" && $strquestionhashed=="" && $strimageref!=""?(string)get_movie_string('lib_t2s_image_only_unsupported'):"")
			);
		}
		if (!$intloadmore) {
			if ($stranswer!="") {
				echo "<br /><a name=\"answer\"></a>";
				//echo get_movie_string('lib_t2s_answer');
				echo f_htmlspecialchars($stranswer);
			}
			echo "<script>\n";
			echo "(function(){\n";
			echo "var el=document.getElementsByName('answer');\n";
			echo "if(el&&el.length){\n";
			echo "if(!window.location.hash){\n";
			echo "if(window.history&&window.history.replaceState){window.history.replaceState(null,'',window.location.pathname+window.location.search+'#answer');}else{window.location.hash='answer';}\n";
			echo "if(el[0].scrollIntoView){el[0].scrollIntoView({block:'start'});}\n";
			echo "}\n";
			echo "}\n";
			echo "})();\n";
			echo "</script>";
			if ($strerror!="") {
				//The error line is printed OUTSIDE the <details>, so it is not part of the copied
				//wrapper. It carries an id and the copy button reads it back from the DOM, rather
				//than being rebuilt in JavaScript, so it cannot drift from what is displayed.
				echo "<br /><span id=\"t2straceerror\">".get_movie_string('lib_t2s_error').f_htmlspecialchars($strerror)."</span>";
			}
			$strtext2sqlresult=get_movie_string('lib_t2s_click_to_expand');
			//Copy button: the question plus everything shown under the summary, so a run can be
			//pasted whole into a debugging session instead of being retyped or screenshotted.
			$strt2scopylabel=get_movie_string('lib_t2s_copy_trace');
			$strt2scopyquestion=$strsearch;
			if ($strt2scopyquestion=="" && isset($jsontext2sql['question'])) {
				$strt2scopyquestion=(string)$jsontext2sql['question'];
			}
			echo "<details id=\"t2stracedetails\">";
			echo "<summary>".$strtext2sqlresult;
			//The button sits inside the summary so it shows whether the block is expanded or not.
			//preventDefault stops the click from toggling the details, which is what a click on a
			//summary normally does.
			echo " <button type=\"button\" id=\"t2stracecopybutton\" class=\"copy-button\" title=\"".f_htmlattr($strt2scopylabel)."\" aria-label=\"".f_htmlattr($strt2scopylabel)."\" onclick=\"event.preventDefault();event.stopPropagation();f_t2stracecopy();\">";
			echo "<img src=\"images/page_copy.png\" width=\"16\" height=\"16\" alt=\"".f_htmlattr($strt2scopylabel)."\">";
			echo "</button>";
			echo "<span id=\"t2stracecopybuttonfeedback\" class=\"copy-button-feedback\" role=\"status\" aria-live=\"polite\"></span>";
			echo "</summary>";
			//Everything copied lives in this wrapper, so the copy follows what is displayed without
			//having to be rebuilt in parallel and drift from it.
			echo "<div id=\"t2stracebody\">";
			if ($strjustification!="") {
				echo "<br />".get_movie_string('lib_t2s_justification').f_htmlspecialchars($strjustification);
			}
		}
		/*
		if ($intdebug || 1) {
			//Afficher la requête SQL
			echo "<br />$strsqlquery";
		}
		*/
		// Display additional API response information
		if (!$intloadmore) {
			//Every clock the API returns is printed, in pipeline order, and printed even at zero
			//(the timings used to be hidden when their task had not fired, which
			//made a zero indistinguishable from a missing measure). isset() rather than a plain
			//read: an older instance omits the key entirely, and a key it never sent must not
			//print as 0s. The list must stay in step with Text2SQLResponse in the API's main.py.
			$arrt2stimings=array(
				array('entity_extraction_processing_time', 'lib_t2s_entity_extraction_time'),
				array('embeddings_cache_search_time', 'lib_t2s_embeddings_cache_search_time'),
				array('text2sql_processing_time', 'lib_t2s_text2sql_time'),
				array('result_entity_processing_time', 'lib_t2s_result_entity_time'),
				array('complex_question_processing_time', 'lib_t2s_complex_question_time'),
				array('embeddings_processing_time', 'lib_t2s_embeddings_time'),
				//Already folded into embeddings_processing_time above, hence the label saying so.
				array('entity_resolution_planning_time', 'lib_t2s_entity_resolution_planning_time'),
			);
			foreach ($arrt2stimings as $tempt2stiming) {
				if (isset($jsontext2sql[$tempt2stiming[0]])) {
					echo "<br />".get_movie_string($tempt2stiming[1]) . round($jsontext2sql[$tempt2stiming[0]], 3) . "s";
				}
			}
			if (isset($jsontext2sql['query_execution_time'])) {
				echo "<br />".get_movie_string('lib_t2s_query_execution_time') . round($jsontext2sql['query_execution_time'], 3) . "s";
			}
			if (isset($jsontext2sql['total_processing_time'])) {
				echo "<br />".get_movie_string('lib_t2s_total_processing_time') . round($jsontext2sql['total_processing_time'], 3) . "s";
			}
			if (isset($jsontext2sql['messages']) && is_array($jsontext2sql['messages']) && count($jsontext2sql['messages']) > 0) {
				echo "<br /><strong>".get_movie_string('lib_t2s_trace')."</strong>";
				echo "<ol>";
				foreach ($jsontext2sql['messages'] as $tempmesssage) {
					$intposition = isset($tempmesssage['position']) ? (int)$tempmesssage['position'] : 0;
					$strtext = isset($tempmesssage['text']) ? $tempmesssage['text'] : '';
					echo "<li><!--<strong>" . htmlspecialchars($intposition) . "</strong>: -->" . htmlspecialchars($strtext) . "</li>";
				}
				echo "</ol>";
			}
			if (isset($jsontext2sql['cached_exact_question']) && $jsontext2sql['cached_exact_question']) {
				echo "<br /><strong>".get_movie_string('lib_t2s_sql_cache_exact')."</strong>";
			}
			if (isset($jsontext2sql['cached_anonymized_question']) && $jsontext2sql['cached_anonymized_question']) {
				echo "<br /><strong>".get_movie_string('lib_t2s_sql_cache_anonymized')."</strong>";
			}
			if (isset($jsontext2sql['cached_anonymized_question_embedding']) && $jsontext2sql['cached_anonymized_question_embedding']) {
				echo "<br /><strong>".get_movie_string('lib_t2s_sql_cache_embedding')."</strong>";
			}
		}
		if (isset($jsontext2sql['offset']) && $jsontext2sql['offset']) {
			$lngoffset=f_stringtolong($jsontext2sql['offset']);
		}
		if (isset($jsontext2sql['limit']) && $jsontext2sql['limit']) {
			$lnglimit=f_stringtolong($jsontext2sql['limit']);
		}
		if (isset($jsontext2sql['rows_per_page']) && $jsontext2sql['rows_per_page']) {
			$lngrowsperpage=f_stringtolong($jsontext2sql['rows_per_page']);
		}
		if (isset($jsontext2sql['question_hashed']) && $jsontext2sql['question_hashed']) {
			$strquestionhashed=$jsontext2sql['question_hashed'];
		}
		if ($strsearch=="") {
			if (isset($jsontext2sql['question']) && $jsontext2sql['question']) {
				$strsearch=$jsontext2sql['question'];
			}
		}
		if (!$intloadmore) {
			echo "</div>";
			echo "</details>";
			echo "<script>";
			echo "var strt2scopyquestion=".json_encode($strt2scopyquestion).";";
			//Le separateur passe par json_encode plutot que par un litteral echappe a la main :
			//un antislash de moins dans la source PHP et le saut de ligne devient REEL au milieu
			//du litteral JavaScript, ce qui casse tout le bloc <script> en silence. Constate le
			//2026-08-29, bouton inerte en production. json_encode echappe pour nous.
			echo "var strt2scopysep=".json_encode("\n\n").";";
			echo "var strt2scopyok=".json_encode(get_movie_string('lib_t2s_copy_trace_done')).";";
			echo "var strt2scopyko=".json_encode(get_movie_string('lib_copy_failed')).";";
			echo "function f_t2stracefeedback(strmessage){var objfeedback=document.getElementById('t2stracecopybuttonfeedback');if(!objfeedback){return;}objfeedback.textContent=strmessage;window.setTimeout(function(){objfeedback.textContent='';},3000);}";
			//innerText is layout aware and returns an EMPTY string for a display:none subtree, which
			//is exactly what a collapsed <details> is. Reading it as-is would copy the question and
			//nothing else whenever the block was not expanded first, and the button would look broken
			//only some of the time. So open it, read, and put it back as it was. textContent avoids
			//the problem but collapses every line into one, which defeats the purpose.
			//The error message, when there is one, is copied right after the question and before the
			//trace, so a failed run pastes as question / error / trace. The span is absent when the
			//run succeeded, hence the empty string.
			echo "function f_t2straceerror(){var objerror=document.getElementById('t2straceerror');if(!objerror){return '';}return objerror.innerText||objerror.textContent||'';}";
			echo "function f_t2stracetext(){var objdetails=document.getElementById('t2stracedetails');var objbody=document.getElementById('t2stracebody');var strerror=f_t2straceerror();var strhead=strt2scopyquestion+(strerror?strt2scopysep+strerror:'');if(!objbody){return strhead;}var intwasopen=objdetails?objdetails.open:true;if(objdetails&&!intwasopen){objdetails.open=true;}var strtrace=objbody.innerText||objbody.textContent||'';if(objdetails&&!intwasopen){objdetails.open=false;}return strhead+strt2scopysep+strtrace;}";
			//Fallback for a non secure origin or an old browser, where the asynchronous Clipboard API
			//is absent.
			echo "function f_t2stracefallback(strtext){var objtemp=document.createElement('textarea');objtemp.value=strtext;objtemp.setAttribute('readonly','');objtemp.style.position='absolute';objtemp.style.left='-9999px';document.body.appendChild(objtemp);objtemp.select();var intok=false;try{intok=document.execCommand('copy');}catch(e){intok=false;}document.body.removeChild(objtemp);f_t2stracefeedback(intok?strt2scopyok:strt2scopyko);}";
			echo "function f_t2stracecopy(){var strtext=f_t2stracetext();if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(strtext).then(function(){f_t2stracefeedback(strt2scopyok);},function(){f_t2stracefallback(strtext);});}else{f_t2stracefallback(strtext);}}";
			echo "</script>";
		}
		
		// Use the results from the API response instead of executing SQL directly
		$lngrow=0;
		if (isset($jsontext2sql['result']) && is_array($jsontext2sql['result'])) {
			$apiresults = $jsontext2sql['result'];
			$lngrowcount = count($apiresults);

			if (!$intloadmore && $strrender!="card") {
				echo "<h1>";
				if ($lngoffset>0) {
					$lngpageprev=$lngpage-1;
					$strurlprev="?questionhashed=".$strquestionhashed."&search=".urlencode($strsearch).$strimagerefurlpart."&page=".$lngpageprev;
					echo "<a href=".$strurlprev.">&lt;&lt;</a> ";
				}
				echo get_movie_string('lib_t2s_query_result').$lngrowcount.get_movie_string('lib_t2s_rows');
				if ($lngrowcount==$lngrowsperpage) {
					$lngpagenext=$lngpage+1;
					$strurlnext="?questionhashed=".$strquestionhashed."&search=".urlencode($strsearch).$strimagerefurlpart."&page=".$lngpagenext;
					echo " <a href=".$strurlnext.">&gt;&gt;</a>";
				}
				echo "</h1>";
			}
			
			switch ($strrender) {
			case "grid":
				if ($lngrowcount > 0) {
					echo "<table cellpadding=\"0\" cellspacing=\"0\" border=\"1\">";
					foreach ($apiresults as $resultitem) {
						// Each result item has 'index' and 'data' fields
						if (isset($resultitem['data'])) {
							$rstemprecord = $resultitem['data'];
							$lngcol=0;
							if ($lngrow==0) {
								echo "<tr>";
								foreach ($rstemprecord as $strkey => $strvalue) {
									echo "<td>";
									echo f_htmlspecialchars($strkey);
									echo "</td>";
									$lngcol++;
								}
								echo "</tr>";
							}
							echo "<tr>";
							$strrowurl="";
							$lngcol=0;
							foreach ($rstemprecord as $strkey => $strvalue) {
								$strkeyswitch=$strkey;
								$strcell=$strvalue;
								$strcellurl="";
								$intcellishtml=false;
								if ($strkey=="ID_CONTENT") {
									$strrowcontenttype="";
									if (isset($rstemprecord["CONTENT_TYPE"])) {
										$strrowcontenttype=$rstemprecord["CONTENT_TYPE"];
										switch ($strrowcontenttype) {
										case "movie":
											$strkeyswitch="ID_MOVIE";
											break;
										case "serie":
											$strkeyswitch="ID_SERIE";
											break;
										}
										//echo "<br />Content type set. ";
									} else {
										//echo "<br />Content type NOT set. ";
									}
								}
								switch ($strkeyswitch) {
								case "ID_MOVIE":
									if ($strrowurl=="") {
										$strrowurl="movie.php?movieid=".$strvalue;
									} else {
										$strcellurl="movie.php?movieid=".$strvalue;
									}
									//Hook search.result.row: a movie was rendered
									f_pluginhtml("search.result.row",array("type"=>"movie","id"=>f_stringtolong($strvalue),"imdbid"=>(isset($rstemprecord["ID_IMDB"]) ? trim($rstemprecord["ID_IMDB"]) : ""),"render"=>"grid"));
									break;
								case "ID_SERIE":
									if ($strrowurl=="") {
										$strrowurl="serie.php?serieid=".$strvalue;
									} else {
										$strcellurl="serie.php?serieid=".$strvalue;
									}
									//New serie
									//Hook search.result.row: a series was rendered
									f_pluginhtml("search.result.row",array("type"=>"serie","id"=>f_stringtolong($strvalue),"imdbid"=>(isset($rstemprecord["ID_IMDB"]) ? trim($rstemprecord["ID_IMDB"]) : ""),"render"=>"grid"));
									break;
								case "ID_PERSON":
									if ($strrowurl=="") {
										$strrowurl="person.php?personid=".$strvalue;
									} else {
										$strcellurl="person.php?personid=".$strvalue;
									}
									break;
								case "ID_COLLECTION":
									if ($strrowurl=="") {
										$strrowurl="collection.php?collectionid=".$strvalue;
									} else {
										$strcellurl="collection.php?collectionid=".$strvalue;
									}
									break;
								case "ID_LIST":
									if ($strrowurl=="") {
										$strrowurl="list.php?listid=".$strvalue;
									} else {
										$strcellurl="list.php?listid=".$strvalue;
									}
									break;
								case "ID_COMPANY":
									if ($strrowurl=="") {
										$strrowurl="company.php?companyid=".$strvalue;
									} else {
										$strcellurl="company.php?companyid=".$strvalue;
									}
									break;
								case "ID_NETWORK":
									if ($strrowurl=="") {
										$strrowurl="network.php?networkid=".$strvalue;
									} else {
										$strcellurl="network.php?networkid=".$strvalue;
									}
									break;
								case "ID_TOPIC":
									if ($strrowurl=="") {
										$strrowurl="t2stopic.php?topicid=".$strvalue;
									} else {
										$strcellurl="t2stopic.php?topicid=".$strvalue;
									}
									break;
								case "ID_T2S_LIST":
									if ($strrowurl=="") {
										$strrowurl="t2slist.php?listid=".$strvalue;
									} else {
										$strcellurl="t2slist.php?listid=".$strvalue;
									}
									break;
								case "ID_T2S_COLLECTION":
									if ($strrowurl=="") {
										$strrowurl="t2scollection.php?collectionid=".$strvalue;
									} else {
										$strcellurl="t2scollection.php?collectionid=".$strvalue;
									}
									break;
								case "ID_MOVEMENT":
									if ($strrowurl=="") {
										$strrowurl="movement.php?movementid=".$strvalue;
									} else {
										$strcellurl="movement.php?movementid=".$strvalue;
									}
									break;
								case "ID_GROUP":
									if ($strrowurl=="") {
										$strrowurl="group.php?groupid=".$strvalue;
									} else {
										$strcellurl="group.php?groupid=".$strvalue;
									}
									break;
								case "ID_DEATH":
									if ($strrowurl=="") {
										$strrowurl="death.php?deathid=".$strvalue;
									} else {
										$strcellurl="death.php?deathid=".$strvalue;
									}
									break;
								case "ID_AWARD":
									if ($strrowurl=="") {
										$strrowurl="award.php?awardid=".$strvalue;
									} else {
										$strcellurl="award.php?awardid=".$strvalue;
									}
									break;
								case "ID_NOMINATION":
									if ($strrowurl=="") {
										$strrowurl="nomination.php?nominationid=".$strvalue;
									} else {
										$strcellurl="nomination.php?nominationid=".$strvalue;
									}
									break;
								case "ID_TECHNICAL":
									if ($strrowurl=="") {
										$strrowurl="technical.php?technicalid=".$strvalue;
									} else {
										$strcellurl="technical.php?technicalid=".$strvalue;
									}
									break;
								case "ID_GENRE":
									if ($strrowurl=="") {
										$strrowurl="genre.php?genreid=".$strvalue;
									} else {
										$strcellurl="genre.php?genreid=".$strvalue;
									}
									break;
								case "ID_LOCATION":
									if ($strrowurl=="") {
										$strrowurl="location.php?locationid=".$strvalue;
									} else {
										$strcellurl="location.php?locationid=".$strvalue;
									}
									break;
								case "ID_SOURCE_WORK":
									if ($strrowurl=="") {
										$strrowurl="sourcework.php?sourceworkid=".f_stringtolong($strvalue);
									} else {
										$strcellurl="sourcework.php?sourceworkid=".f_stringtolong($strvalue);
									}
									break;
								case "IMAGE_PATH":
								case "POSTER_PATH":
									$strcell="<img src=\"".f_htmlattr(f_getmovieposterpicture($strvalue,3))."\" style=\"background-color: white;\" />";
									$intcellishtml=true;
									break;
								case "PROFILE_PATH":
									$strcell="<img src=\"".f_htmlattr(f_getpersonprofilepicture($strvalue,2))."\" style=\"background-color: white;\" />";
									$intcellishtml=true;
									break;
								case "LOGO_PATH":
									$strcell="<img src=\"".f_htmlattr(f_getcompanylogopicture($strvalue))."\" style=\"background-color: white;\" />";
									$intcellishtml=true;
									break;
								case "WIKIPEDIA_IMAGE_PATH":
									$strcell="<img src=\"".f_htmlattr($strvalue)."\" style=\"background-color: white;\" width=\"300\" />";
									$intcellishtml=true;
									break;
								case "ID_WIKIDATA":
									$strpropertyid="";
									if (isset($rstemprecord["ID_PROPERTY"])) {
										$strpropertyid=trim($rstemprecord["ID_PROPERTY"]);
									}
									$strcellurl="wikidata-query.php?search=".$strpropertyid." ".$strvalue."&val=Search";
									break;
								case "OVERVIEW":
								case "BIOGRAPHY":
									if (mb_strlen($strcell, 'UTF-8') > $lngmaxLength) {
										$strcell=mb_substr($strcell, 0, $lngmaxLength, 'UTF-8') . '...';
									}
									break;
								default:
									break;
								}
								echo "<td>";
								if ($strcellurl!="") {
									echo "<a href=\"".f_htmlattr($strcellurl)."\" target=\"_blank\" rel=\"noopener noreferrer\">";
								} else {
									if ($strrowurl!="") {
										echo "<a href=\"".f_htmlattr($strrowurl)."\">";
									}
								}
								echo ($intcellishtml ? $strcell : f_htmlspecialchars($strcell));
								if ($strcellurl!="") {
									echo "</a>";
								} else {
									if ($strrowurl!="") {
										echo "</a>";
									}
								}
								echo "</td>";
								$lngcol++;
							}
							echo "</tr>";
							$lngrow++;
						}
					}
					echo "</table>";
				}
				break;
			case "card":
				//A one-row aggregate, a ranking or a wide grouped result is drawn from
				//the API representation hint, like voice-agent; entity shapes keep the cards below.
				//Infinite scroll does not apply (no #search-poster-card-grid), so a load-more call
				//prints nothing.
				//Phase 1: value-cards is the card renderer below with the
				//value under each card; the caption comes from summary_facts; the chips of
				//representation_options switch between views ALL rendered from this one answer,
				//the inactive ones hidden, so a click re-renders locally with no API call. A
				//reload or a shared URL carries render= and asks the API once, like any page load
				//(the question is then answered from the API cache, retrieve_from_cache).
				//Phase 2 lot B: a verdict on an empty result has no row and still an answer (No,
				//summary_facts with a count of 0): it is drawn, not the "no results" line
				$intt2sverdictempty=($lngrowcount==0 && isset($jsontext2sql['representation']) && trim((string)$jsontext2sql['representation'])=="verdict"
					&& isset($jsontext2sql['summary_facts']) && is_array($jsontext2sql['summary_facts']));
				$intt2sanswer=($lngrowcount > 0 || $intt2sverdictempty);
				$strt2srepactive=$intt2sanswer ? f_t2srep_active($jsontext2sql, $strt2srepoverride) : "";
				$strt2srep=$intt2sanswer ? f_t2srep_choose($jsontext2sql, $strt2srepoverride) : "";
				$arrt2soptions=$intt2sanswer ? f_t2srep_options($jsontext2sql) : array();
				$arrt2scardreps=array();
				foreach ($arrt2soptions as $strt2soption) {
					if (f_t2srep_is_card_rep($strt2soption)) {
						$arrt2scardreps[]=$strt2soption;
					}
				}
				//No hint, or a default this front does not know: the cards, alone, as before
				$intt2scards=($strt2srep=="" || count($arrt2scardreps)>0);
				if ($strt2srep=="" && count($arrt2scardreps)==0) {
					$arrt2scardreps=array($strt2srepactive!="" ? $strt2srepactive : "card-grid");
				}
				if ($strt2srepactive=="" && count($arrt2scardreps)>0) {
					$strt2srepactive=$arrt2scardreps[0];
				}
				//The evidence of a verdict on rows is the card view (entity-page or card-grid), shown
				//under the Yes / No block: the card view answers to "verdict" too. An empty verdict
				//has no card to show, and no "no results" line under its No.
				if ($intt2sverdictempty) {
					$arrt2scardreps=array();
					$intt2scards=false;
				} elseif (in_array("verdict", $arrt2soptions, true) && f_t2srep_is_card_rep(f_t2srep_verdict_evidence($jsontext2sql))) {
					array_unshift($arrt2scardreps, "verdict");
				}
				//The value under each card, kept hidden until value-cards is the active view
				$arrt2svaluecolumns=in_array("value-cards", $arrt2scardreps, true) ? f_t2srep_value_columns(f_t2srep_columns($jsontext2sql)) : array();
				//Sequence-strip is these cards numbered (the badge shows only under
				//t2sShowSequence). The number starts at the offset of the page, so the cards that
				//infinite scroll appends (a loadmore call, render=card) go on counting.
				$intt2ssequence=in_array("sequence-strip", $arrt2scardreps, true);
				$lngt2ssequencebase=(isset($jsontext2sql['offset']) && f_t2srep_is_number($jsontext2sql['offset'])) ? (int)$jsontext2sql['offset'] : max(0, ($lngpage-1)*(isset($lngrowsperpage) ? (int)$lngrowsperpage : 0));
				$intt2sviews=($intt2sanswer && isset($jsontext2sql['representation']) && trim((string)$jsontext2sql['representation'])!="");
				//A full page may have another behind it (the same test as the card grid's
				//data-has-more): the caption then says "the first N" and the timeline fetches the rest
				$intt2shasmore=(isset($lngrowsperpage) && $lngrowsperpage>0 && $lngrowcount==$lngrowsperpage);
				if ($intloadmore && $strt2sloadmoreview=="timeline") {
					//The next page of a timeline: its marks only, filed by year in the page
					echo f_t2srep_render_timeline_page($jsontext2sql, $struilang, 'f_t2s_rep_poster', $intt2shasmore);
					break;
				}
				//The year of each card under sequence-strip, read by the caption when more cards come in
				$strt2sseqdatecolumn=$intt2ssequence ? f_t2srep_date_column(f_t2srep_columns($jsontext2sql)) : "";
				if (!$intt2sviews && $strt2srep!="") {
					//Hintless one-row number (an API colour predating the hint): as in 1.0.60
					if (!$intloadmore) {
						echo f_t2srep_render($strt2srep, $jsontext2sql, $struilang, 'f_t2s_rep_poster');
					}
					break;
				}
				if (!$intloadmore && $intt2sviews) {
					$strt2scaption=f_t2srep_caption($jsontext2sql, $struilang, $intt2shasmore);
					if ($strt2scaption!="") {
						echo "<p class=\"t2sCaption\" id=\"t2sCaption\">".f_htmlspecialchars($strt2scaption)."</p>";
						//A timeline or a sequence recounts its caption as the next pages come in
						$arrt2scaptionspec=$intt2shasmore ? f_t2srep_caption_spec($jsontext2sql, $struilang) : null;
						if ($arrt2scaptionspec!==null) {
							echo "<script>".f_t2srep_caption_live_js($arrt2scaptionspec)."</script>";
						}
					}
					//No chips on an entity page or a one-row scalar, as voice-agent; a fact, a verdict
					//or one share alone on one row has them (the fact, the Yes / No or the bar, the
					//card, the table)
					$strt2sdefault=trim((string)$jsontext2sql['representation']);
					if (count($arrt2soptions)>1 && ($lngrowcount>1 || ($lngrowcount==1 && ($strt2sdefault=="fact-card" || $strt2sdefault=="verdict" || $strt2sdefault=="share-bar")))) {
						$arrt2shrefs=array();
						$arrt2slabels=array();
						foreach ($arrt2soptions as $strt2soption) {
							$arrt2squery=$arrurlquery;
							unset($arrt2squery['loadmore']);
							$arrt2squery['render']=$strt2soption;
							$arrt2shrefs[$strt2soption]=(isset($arrurlparsed['path'])?$arrurlparsed['path']:'')."?".http_build_query($arrt2squery)."#answer";
							$arrt2slabels[$strt2soption]=get_movie_string('lib_t2s_rep_'.str_replace("-","_",$strt2soption));
						}
						echo f_t2srep_render_chips($arrt2soptions, $strt2srepactive, $arrt2shrefs, $arrt2slabels, get_movie_string('lib_t2s_rep_chips'));
						echo "<script>".f_t2srep_chips_js()."</script>";
					}
					//The views this module draws: the default, and every other option as a hidden
					//alternative
					foreach ($arrt2soptions as $strt2soption) {
						if (in_array($strt2soption, f_t2srep_module_reps(), true)) {
							echo f_t2srep_view_open(array($strt2soption), $strt2srepactive, $strt2scaption!="" ? "t2sCaption" : "");
							echo f_t2srep_render($strt2soption, $jsontext2sql, $struilang, 'f_t2s_rep_poster');
							if ($strt2soption=="flag-list") {
								//The code badges turn into flags where the browser draws flag emoji
								echo "<script>".f_t2srep_flags_js()."</script>";
							}
							if ($strt2soption=="timeline" && $intt2shasmore) {
								//The rest of the rows, page after page, while the timeline is shown
								echo "<div id=\"t2sTimelineStatus\" class=\"muted\" style=\"display:none; margin-top:12px;\"></div>";
								echo "<button type=\"button\" id=\"t2sTimelineMore\" class=\"responsive-input\" style=\"display:none; margin-top:12px;\"></button>";
								echo "<script>".f_t2srep_timeline_more_js(array("page"=>$lngpage+1, "maxauto"=>4,
									"more"=>(string)get_movie_string('lib_t2s_load_more'), "loading"=>(string)get_movie_string('lib_t2s_loading_more'),
									"suffix"=>(string)get_movie_string('lib_t2s_retrieved_count_suffix'), "nodate"=>($struilang=="fr" ? "Sans date" : "No date"),
									"aria"=>f_t2srep_timeline_aria($struilang)))."</script>";
							}
							echo "</div>";
						}
					}
					if ($intt2scards) {
						echo f_t2srep_view_open($arrt2scardreps, $strt2srepactive, $strt2scaption!="" ? "t2sCaption" : "");
					}
				}
				if (!$intt2scards) {
					break;
				}
				if ($lngrowcount > 0) {
					$strpostersectionlabel="";
					echo "<div class=\"search-poster-results\">";
					foreach ($apiresults as $resultitem) {
						// Each result item has 'index' and 'data' fields
						if (isset($resultitem['data'])) {
							$rstemprecord = $resultitem['data'];
							$lngcol=0;
							if ($intdebug) {
								echo "<br />-----------------------------------------------";
								echo "<br />\$lngrow=$lngrow";
							}
							if ($lngrow==0) {
								$arrcolumnsvisible=array();
								foreach ($rstemprecord as $strkey => $strvalue) {
									if ($intdebug) {
										echo "<br />$strkey = $strvalue";
									}
									$intisvisible=false;
									switch ($strkey) {
									case "ID_MOVIE":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_movie');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_PERSON":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_person');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_SERIE":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_serie');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_CONTENT":
										if ($lngcol==0) {
											//A union of films and series that returns one type only is named after it
											$arrcontenttypes=array();
											foreach ($apiresults as $arrcontentitem) {
												if (isset($arrcontentitem['data']['CONTENT_TYPE'])) {
													$arrcontenttypes[(string)$arrcontentitem['data']['CONTENT_TYPE']]=true;
												}
											}
											if (array_keys($arrcontenttypes)===array("movie")) {
												$strlabel=get_movie_string('lib_t2s_label_movie');
											} elseif (array_keys($arrcontenttypes)===array("serie")) {
												$strlabel=get_movie_string('lib_t2s_label_serie');
											} else {
												$strlabel=get_movie_string('lib_t2s_label_movie_serie');
											}
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_TOPIC":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_topic');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_COMPANY":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_company');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_NETWORK":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_network');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_WIKIDATA":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_wikidata');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "IMAGE_PATH":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_image');
											$intisvisible=true;
											if (isset($rstemprecord["ID_MOVIE"])) {
												$strresultkeycolumn="ID_MOVIE";
											} else {
												if (isset($rstemprecord["ID_SERIE"])) {
													$strresultkeycolumn="ID_SERIE";
												} else {
													if (isset($rstemprecord["ID_PERSON"])) {
														$strresultkeycolumn="ID_PERSON";
													} else {
														if (isset($rstemprecord["ID_COMPANY"])) {
															$strresultkeycolumn="ID_COMPANY";
														} else {
															if (isset($rstemprecord["ID_NETWORK"])) {
																$strresultkeycolumn="ID_NETWORK";
															} else {
															}
														}
													}
												}
											}
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_T2S_LIST":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_list');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_T2S_COLLECTION":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_collection');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_MOVEMENT":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_movement');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_GROUP":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_group');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_DEATH":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_death');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_AWARD":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_award');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_NOMINATION":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_nomination');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_TECHNICAL":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_technical');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_GENRE":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_genre');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_LOCATION":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_location');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_SOURCE_WORK":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_source_work');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "ID_ROW":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_image');
											$intisvisible=true;
											$strresultkeycolumn=$strkey;
										} else {
											$intisvisible=false;
										}
										break;
									case "VIDEO_KEY":
										if ($lngcol==0) {
											$strlabel=get_movie_string('lib_t2s_label_video');
											$intisvisible=true;
											if (isset($rstemprecord["ID_MOVIE"])) {
												$strresultkeycolumn="ID_MOVIE";
											} else {
												if (isset($rstemprecord["ID_SERIE"])) {
													$strresultkeycolumn="ID_SERIE";
												} else {
												}
											}
										} else {
											$intisvisible=false;
										}
										break;
									case "PERSON_NAME":
									case "MOVIE_TITLE":
									case "SERIE_TITLE":
									case "CONTENT_TITLE":
									case "CONTENT_TYPE":
									case "DAT_RELEASE":
									case "ID_IMDB":
									case "IMDB_RATING":
									case "IMDB_RATING_ADJUSTED":
									case "IMDB_RATING_WEIGHTED":
									case "IMDB_VOTES":
									case "POSTER_PATH":
									case "POPULARITY":
									case "PROFILE_PATH":
									case "DAT_FIRST_AIR":
									case "DAT_LAST_AIR":
									case "TOPIC_NAME":
									case "TOPIC_TYPE":
									case "TOPIC_SOURCE":
									case "ID_RECORD":
									case "COMPANY_NAME":
									case "NETWORK_NAME":
									case "LOGO_PATH":
									case "TYPE_IMAGE":
									case "VOTE_AVERAGE":
									case "VIDEO_NAME":
									case "VIDEO_SITE":
									case "VIDEO_TYPE":
									case "DAT_PUBLISHED":
									case "LANG":
									case "ITEM_LABEL":
									case "ID_PROPERTY":
								case "LIST_NAME":
								case "LIST_SOURCE":
								case "LIST_TYPE":
								case "COLLECTION_NAME":
								case "COLLECTION_SOURCE":
								case "COLLECTION_TYPE":
								case "MOVEMENT_NAME":
								case "MOVEMENT_SOURCE":
								case "MOVEMENT_TYPE":
								case "LOCATION_NAME":
								case "LOCATION_NAME_FR":
								case "LOCATION_SOURCE":
								case "LOCATION_TYPE":
								case "SOURCE_WORK_NAME":
								case "SOURCE_WORK_NAME_FR":
								case "SOURCE_WORK_TYPE":
								case "SOURCE_WORK_FORM":
								case "SOURCE_WORK_YEAR":
								case "SOURCE_WORK_SOURCE":
								case "WIKIPEDIA_MAIN_IMAGE_URL":
								case "WIKIPEDIA_MAIN_IMAGE_URL_FR":
								case "GROUP_NAME":
								case "GROUP_SOURCE":
								case "GROUP_TYPE":
								case "DEATH_NAME":
								case "DEATH_SOURCE":
								case "DEATH_TYPE":
								case "AWARD_NAME":
								case "AWARD_SOURCE":
								case "AWARD_TYPE":
								case "NOMINATION_NAME":
								case "NOMINATION_SOURCE":
								case "NOMINATION_TYPE":
								case "TECHNICAL_TYPE":
								case "WIKIPEDIA_IMAGE_PATH":
								// Extra fields consumed by the hover-reveal overlay (E1), not rendered as standalone scrims
								case "RUNTIME":
								case "TAGLINE":
								case "NUMBER_OF_SEASONS":
								case "NUMBER_OF_EPISODES":
								case "KNOWN_FOR_DEPARTMENT":
								case "BIRTH_YEAR":
								case "DEATH_YEAR":
								case "OVERVIEW":
								case "DESCRIPTION":
								case "DESCRIPTION_FR":
								case "HEADQUARTERS":
								case "ORIGIN_COUNTRY":
								case "MOVIE_COUNT":
								case "SERIE_COUNT":
								case "GENRE_NAME":
								case "APPLIES_TO_MOVIE":
								case "APPLIES_TO_SERIE":
								case "PERSON_COUNT":
									$strlabel=$strkey;
									$intisvisible=false;
									break;
									default:
										$strlabel=$strkey;
										$intisvisible=true;
										break;
									}
									$arrcolumnsvisible[$strkey]=$intisvisible;
									if ($intisvisible && $strpostersectionlabel=="") {
										$strpostersectionlabel=$strlabel;
									}
									$lngcol++;
								}
								if ($strpostersectionlabel!="") {
									echo "<div class=\"search-poster-results-title\">".$strpostersectionlabel."</div>";
								}
								if ($intloadmore) {
									echo "<div class=\"search-poster-card-grid\">";
								} else {
									echo "<div class=\"search-poster-card-grid\" id=\"search-poster-card-grid\" data-search=\"".f_htmlattr($strsearch)."\" data-questionhashed=\"".f_htmlattr($strquestionhashed)."\" data-page=\"".f_htmlattr($lngpage)."\" data-has-more=\"".f_htmlattr($lngrowcount==$lngrowsperpage?"1":"0")."\" data-render=\"".f_htmlattr($strrender)."\">";
								}
							}
							$strrowurl="";
							$lngcol=0;
							$strcardcontent="";
							$strcardmedia="";
							$strcardtext="";
							$strcardreveal="";
							foreach ($rstemprecord as $strkey => $strvalue) {
								if ($intdebug) {
									echo "<br />$strkey = $strvalue";
								}
								$strkeyswitch=$strkey;
								$strcardmedia="";
								$strcardtext="";
								$strcardreveal="";
								if ($strkey=="ID_CONTENT") {
									$strrowcontenttype="";
									if (isset($rstemprecord["CONTENT_TYPE"])) {
										$strrowcontenttype=$rstemprecord["CONTENT_TYPE"];
										switch ($strrowcontenttype) {
										case "movie":
											$strkeyswitch="ID_MOVIE";
											break;
										case "serie":
											$strkeyswitch="ID_SERIE";
											break;
										}
										//echo "<br />Content type set. ";
									} else {
										//echo "<br />Content type NOT set. ";
									}
								}
								$strcell=$strvalue;
								$strcellurl="";
								$intisvisible=$arrcolumnsvisible[$strkey];
								if ($intisvisible) {
									switch ($strkeyswitch) {
									case "ID_MOVIE":
										$arrresultkeyvalues[]=$strvalue;
										if (isset($rstemprecord["CONTENT_TYPE"])) {
											$strrowcontenttype=$rstemprecord["CONTENT_TYPE"];
										} else {
											$strrowcontenttype="movie";
										}
										if ($strrowurl=="") {
											switch ($strrowcontenttype) {
											case "movie":
												$strrowurl="movie.php?movieid=".$strvalue;
												break;
											case "serie":
												$strrowurl="serie.php?serieid=".$strvalue;
												break;
											}
										} else {
											switch ($strrowcontenttype) {
											case "movie":
												$strcellurl="movie.php?movieid=".$strvalue;
												break;
											case "serie":
												$strcellurl="serie.php?serieid=".$strvalue;
												break;
											}
										}
										if (isset($rstemprecord["POSTER_PATH"])) {
											$strmovieposterpath=$rstemprecord["POSTER_PATH"];
										} else {
											$strmovieposterpath=f_descfromcode("T_WC_T2S_MOVIE","ID_MOVIE","POSTER_PATH",$strvalue);
										}
										if (isset($rstemprecord["MOVIE_TITLE"])) {
											$strmovietitle=$rstemprecord["MOVIE_TITLE"];
										} else {
											switch ($strrowcontenttype) {
											case "movie":
												$strmovietitle=f_descfromcode("T_WC_T2S_MOVIE","ID_MOVIE","MOVIE_TITLE",$strvalue);
												break;
											case "serie":
												$strmovietitle=f_descfromcode("T_WC_T2S_SERIE","ID_SERIE","SERIE_TITLE",$strvalue);
												break;
											}
										}
										if (isset($rstemprecord["DAT_RELEASE"])) {
											$strmoviereleaseyear=substr($rstemprecord["DAT_RELEASE"],0,4);
										} else {
											switch ($strrowcontenttype) {
											case "movie":
												$strmoviereleaseyear=f_descfromcode("T_WC_T2S_MOVIE","ID_MOVIE","RELEASE_YEAR",$strvalue);
												break;
											case "serie":
												$strseriefirstairyear=f_descfromcode("T_WC_TMDB_SERIE","ID_SERIE","FIRST_AIR_YEAR",$strvalue);
												$strserielastairyear=f_descfromcode("T_WC_TMDB_SERIE","ID_SERIE","LAST_AIR_YEAR",$strvalue);
												$strmoviereleaseyear=$strseriefirstairyear."-".$strserielastairyear;
												break;
											}
										}
										
										if ($struilang!="en") {
											//Read translated movie data from the MySQL database
											$strsqlmovie="SELECT * FROM ".$strsqlns."TMDB_MOVIE_LANG WHERE ID_MOVIE = ".f_longtosql($strvalue)." AND LANG = ".f_stringtosql($struilang)." AND DELETED = 0 ";
											$resultmovie=f_sqlselect($strsqlmovie);
											if ($resultmovie) {
												if ($rstempmovie=f_sqlresultfetcharray($resultmovie)) {
													$strmovieposterpath=$rstempmovie['POSTER_PATH'];
													$strmovietitle=$rstempmovie['TITLE'];
												}
												f_sqlfreeresult($resultmovie);
											}
										}
										$strmoviefulltitle=$strmovietitle." (".$strmoviereleaseyear.")";
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr(f_getmovieposterpicture($strmovieposterpath,4))."\" alt=\"".f_htmlattr($strmoviefulltitle)."\" title=\"".f_htmlattr($strmoviefulltitle)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\">";
										$strcardtext.="<div class=\"search-poster-card-title\">".f_htmlspecialchars($strmovietitle)."</div>";
										//Always emitted, empty year included: the line is reserved in CSS so every
										//card of a given query keeps the same text height and the rows stay aligned.
										$strcardtext.="<div class=\"search-poster-card-year\">".f_htmlspecialchars($strmoviereleaseyear)."</div>";
										$strcardtext.="</div>";
										// Hover-reveal (E1)
										$strcardreveal="<div class=\"search-poster-card-reveal\">";
										$arrmeta=array();
										if ($strmoviereleaseyear!="") { $arrmeta[]=$strmoviereleaseyear; }
										$strmovieruntime=isset($rstemprecord["RUNTIME"]) ? $rstemprecord["RUNTIME"] : "";
										if ($strmovieruntime!="" && (int)$strmovieruntime>0) { $arrmeta[]=$strmovieruntime." min"; }
										if (count($arrmeta)>0) {
											$strcardreveal.="<div class=\"search-poster-card-reveal-meta\">".f_htmlspecialchars(implode(" · ",$arrmeta))."</div>";
										}
										$strmovierating=isset($rstemprecord["IMDB_RATING"]) ? $rstemprecord["IMDB_RATING"] : "";
										if ($strmovierating!="" && (float)$strmovierating>0) {
											$strcardreveal.="<div class=\"search-poster-card-reveal-rating\">★ ".f_htmlspecialchars(number_format((float)$strmovierating,1))."</div>";
										}
										$strmovietagline=isset($rstemprecord["TAGLINE"]) ? $rstemprecord["TAGLINE"] : "";
										if ($strmovietagline!="") {
											$strcardreveal.="<div class=\"search-poster-card-reveal-overview\">".f_htmlspecialchars($strmovietagline)."</div>";
										}
										$strcardreveal.="</div>";
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										//Hook search.result.row: a movie was rendered
										f_pluginhtml("search.result.row",array("type"=>"movie","id"=>f_stringtolong($strvalue),"imdbid"=>(isset($rstemprecord["ID_IMDB"]) ? trim($rstemprecord["ID_IMDB"]) : ""),"render"=>"card"));
										break;
									case "ID_SERIE":
										$arrresultkeyvalues[]=$strvalue;
										if (isset($rstemprecord["CONTENT_TYPE"])) {
											$strrowcontenttype=$rstemprecord["CONTENT_TYPE"];
										} else {
											$strrowcontenttype="serie";
										}
										if ($strrowurl=="") {
											switch ($strrowcontenttype) {
											case "movie":
												$strrowurl="movie.php?movieid=".$strvalue;
												break;
											case "serie":
												$strrowurl="serie.php?serieid=".$strvalue;
												break;
											}
										} else {
											switch ($strrowcontenttype) {
											case "movie":
												$strcellurl="movie.php?movieid=".$strvalue;
												break;
											case "serie":
												$strcellurl="serie.php?serieid=".$strvalue;
												break;
											}
										}
										if (isset($rstemprecord["POSTER_PATH"])) {
											$strserieposterpath=$rstemprecord["POSTER_PATH"];
										} else {
											$strserieposterpath=f_descfromcode("T_WC_TMDB_SERIE","ID_SERIE","POSTER_PATH",$strvalue);
										}
										if (isset($rstemprecord["SERIE_TITLE"])) {
											$strserietitle=$rstemprecord["SERIE_TITLE"];
										} else {
											switch ($strrowcontenttype) {
											case "movie":
												$strserietitle=f_descfromcode("T_WC_T2S_MOVIE","ID_MOVIE","MOVIE_TITLE",$strvalue);
												break;
											case "serie":
												$strserietitle=f_descfromcode("T_WC_T2S_SERIE","ID_SERIE","SERIE_TITLE",$strvalue);
												break;
											}
										}	
										if (isset($rstemprecord["DAT_FIRST_AIR"])) {
											$strseriefirstairyear=substr($rstemprecord["DAT_FIRST_AIR"],0,4);
										} else {
											switch ($strrowcontenttype) {
											case "movie":
												$strseriefirstairyear=f_descfromcode("T_WC_TMDB_MOVIE","ID_MOVIE","DAT_RELEASE",$strvalue);
												break;
											case "serie":
												$strseriefirstairyear=f_descfromcode("T_WC_TMDB_SERIE","ID_SERIE","FIRST_AIR_YEAR",$strvalue);
												break;
											}
										}	
										if (isset($rstemprecord["DAT_LAST_AIR"])) {
											$strserielastairyear=substr($rstemprecord["DAT_LAST_AIR"],0,4);
										} else {
											switch ($strrowcontenttype) {
											case "movie":
												$strserielastairyear="";
												break;
											case "serie":
												$strserielastairyear=f_descfromcode("T_WC_TMDB_SERIE","ID_SERIE","LAST_AIR_YEAR",$strvalue);
												break;
											}
										}	
										if ($struilang!="en") {
											//Read translated serie data from the MySQL database
											$strsqlserie="SELECT * FROM ".$strsqlns."TMDB_SERIE_LANG WHERE ID_SERIE = ".f_longtosql($strvalue)." AND LANG = ".f_stringtosql($struilang)." AND DELETED = 0 ";
											$resultserie=f_sqlselect($strsqlserie);
											if ($resultserie) {
												if ($rstempserie=f_sqlresultfetcharray($resultserie)) {
													$strserieposterpath=$rstempserie['POSTER_PATH'];
													$strserietitle=$rstempserie['TITLE'];
												}
												f_sqlfreeresult($resultserie);
											}
										}
										switch ($strrowcontenttype) {
										case "movie":
											$strseriefulltitle=$strserietitle." (".$strseriefirstairyear.")";
											$strserieyeardisplay=$strseriefirstairyear;
											break;
										case "serie":
											$strseriefulltitle=$strserietitle." (".$strseriefirstairyear."-".$strserielastairyear.")";
											$strserieyeardisplay=$strseriefirstairyear."-".$strserielastairyear;
											break;
										}
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr(f_getmovieposterpicture($strserieposterpath,4))."\" alt=\"".f_htmlattr($strseriefulltitle)."\" title=\"".f_htmlattr($strseriefulltitle)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\">";
										$strcardtext.="<div class=\"search-poster-card-title\">".f_htmlspecialchars($strserietitle)."</div>";
										if ($strserieyeardisplay=="-") {
											$strserieyeardisplay="";
										}
										$strcardtext.="<div class=\"search-poster-card-year\">".f_htmlspecialchars($strserieyeardisplay)."</div>";
										$strcardtext.="</div>";
										// Hover-reveal (E1)
										$strcardreveal="<div class=\"search-poster-card-reveal\">";
										$arrmeta=array();
										if ($strserieyeardisplay!="" && $strserieyeardisplay!="-") { $arrmeta[]=$strserieyeardisplay; }
										if ($strrowcontenttype=="serie") {
											$strserieseasons=isset($rstemprecord["NUMBER_OF_SEASONS"]) ? $rstemprecord["NUMBER_OF_SEASONS"] : "";
											if ($strserieseasons!="" && (int)$strserieseasons>0) {
												$arrmeta[]=$strserieseasons.((int)$strserieseasons>1?"\xC2\xA0seasons":"\xC2\xA0season");
											}
											$strserieepisodes=isset($rstemprecord["NUMBER_OF_EPISODES"]) ? $rstemprecord["NUMBER_OF_EPISODES"] : "";
											if ($strserieepisodes!="" && (int)$strserieepisodes>0) {
												$arrmeta[]=$strserieepisodes.((int)$strserieepisodes>1?"\xC2\xA0episodes":"\xC2\xA0episode");
											}
										} else {
											$strserieruntime=isset($rstemprecord["RUNTIME"]) ? $rstemprecord["RUNTIME"] : "";
											if ($strserieruntime!="" && (int)$strserieruntime>0) { $arrmeta[]=$strserieruntime."\xC2\xA0min"; }
										}
										if (count($arrmeta)>0) {
											$strcardreveal.="<div class=\"search-poster-card-reveal-meta\">".f_htmlspecialchars(implode(" · ",$arrmeta))."</div>";
										}
										$strserierating=isset($rstemprecord["IMDB_RATING"]) ? $rstemprecord["IMDB_RATING"] : "";
										if ($strserierating!="" && (float)$strserierating>0) {
											$strcardreveal.="<div class=\"search-poster-card-reveal-rating\">★ ".f_htmlspecialchars(number_format((float)$strserierating,1))."</div>";
										}
										$strserietagline=isset($rstemprecord["TAGLINE"]) ? $rstemprecord["TAGLINE"] : "";
										if ($strserietagline!="") {
											$strcardreveal.="<div class=\"search-poster-card-reveal-overview\">".f_htmlspecialchars($strserietagline)."</div>";
										}
										$strcardreveal.="</div>";
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										//Hook search.result.row: a series was rendered
										f_pluginhtml("search.result.row",array("type"=>"serie","id"=>f_stringtolong($strvalue),"imdbid"=>(isset($rstemprecord["ID_IMDB"]) ? trim($rstemprecord["ID_IMDB"]) : ""),"render"=>"card"));
										break;
									case "ID_PERSON":
										$arrresultkeyvalues[]=$strvalue;
										if ($strrowurl=="") {
											$strrowurl="person.php?personid=".$strvalue;
										} else {
											$strcellurl="person.php?personid=".$strvalue;
										}
										if (isset($rstemprecord["PROFILE_PATH"])) {
											$strpersonprofilepath=$rstemprecord["PROFILE_PATH"];
										} else {
											$strpersonprofilepath=f_descfromcode("T_WC_TMDB_PERSON","ID_PERSON","PROFILE_PATH",$strvalue);
										}
										if (isset($rstemprecord["PERSON_NAME"])) {
											$strpersonname=$rstemprecord["PERSON_NAME"];
										} else {
											$strpersonname=f_descfromcode("T_WC_TMDB_PERSON","ID_PERSON","PERSON_NAME",$strvalue);
										}
										$strpersonfullname=$strpersonname;
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr(f_getpersonprofilepicture($strpersonprofilepath,2))."\" alt=\"".f_htmlattr($strpersonfullname)."\" title=\"".f_htmlattr($strpersonfullname)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($strpersonfullname)."</div></div>";
										// Hover-reveal (E1)
										$strcardreveal="<div class=\"search-poster-card-reveal\">";
										$strpersondept=isset($rstemprecord["KNOWN_FOR_DEPARTMENT"]) ? $rstemprecord["KNOWN_FOR_DEPARTMENT"] : "";
										$strpersonbirthyear=isset($rstemprecord["BIRTH_YEAR"]) ? $rstemprecord["BIRTH_YEAR"] : "";
										$strpersondeathyear=isset($rstemprecord["DEATH_YEAR"]) ? $rstemprecord["DEATH_YEAR"] : "";
										$strpersonlife="";
										if ($strpersonbirthyear!="" && (int)$strpersonbirthyear>0) {
											if ($strpersondeathyear!="" && (int)$strpersondeathyear>0) {
												$strpersonlife=$strpersonbirthyear." – ".$strpersondeathyear;
											} else {
												$strpersonlife="b. ".$strpersonbirthyear;
											}
										}
										if ($strpersondept!="") {
											$strcardreveal.="<div class=\"search-poster-card-reveal-meta\">".f_htmlspecialchars($strpersondept)."</div>";
										}
										if ($strpersonlife!="") {
											$strcardreveal.="<div class=\"search-poster-card-reveal-meta\">".f_htmlspecialchars($strpersonlife)."</div>";
										}
										$strcardreveal.="</div>";
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										break;
									case "ID_TOPIC":
										$arrresultkeyvalues[]=$strvalue;
										if (isset($rstemprecord["TOPIC_TYPE"])) {
											$strtopictype=$rstemprecord["TOPIC_TYPE"];
										} else {
											$strtopictype=f_descfromcode("T_WC_T2S_TOPIC","ID_TOPIC","TOPIC_TYPE",$strvalue);
										}
										if (isset($rstemprecord["TOPIC_SOURCE"])) {
											$strtopicsource=$rstemprecord["TOPIC_SOURCE"];
										} else {
											$strtopicsource=f_descfromcode("T_WC_T2S_TOPIC","ID_TOPIC","TOPIC_SOURCE",$strvalue);
										}
										if (isset($rstemprecord["ID_RECORD"])) {
											$lngtopicrecordid=$rstemprecord["ID_RECORD"];
										} else {
											$lngtopicrecordid=f_descfromcode("T_WC_T2S_TOPIC","ID_TOPIC","ID_RECORD",$strvalue);
										}
										switch ($strtopicsource) {
										case "keyword":
										default:
											$strtopicurl="t2stopic.php?topicid=".$strvalue;
											break;
										}
										if ($strrowurl=="") {
											$strrowurl=$strtopicurl;
										} else {
											$strcellurl=$strtopicurl;
										}
										if (isset($rstemprecord["TOPIC_NAME"])) {
											$strtopicname=$rstemprecord["TOPIC_NAME"];
										} else {
											$strtopicname=f_descfromcode("T_WC_T2S_TOPIC","ID_TOPIC","TOPIC_NAME",$strvalue);
										}
										if (isset($rstemprecord["POSTER_PATH"])) {
											$strtopicposterpath=$rstemprecord["POSTER_PATH"];
										} else {
											$strtopicposterpath=f_descfromcode("T_WC_T2S_TOPIC","ID_TOPIC","POSTER_PATH",$strvalue);
										}
										if (trim($strtopicposterpath)!="") {
											$strimagepath=f_getmovieposterpicture($strtopicposterpath);
										} elseif (isset($rstemprecord["WIKIPEDIA_IMAGE_PATH"]) && trim($rstemprecord["WIKIPEDIA_IMAGE_PATH"])!="") {
											$strimagepath=$rstemprecord["WIKIPEDIA_IMAGE_PATH"];
										} else {
											$strimagepath="/images/No_Image_Available.jpg";
										}
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr($strimagepath)."\" alt=\"".f_htmlattr($strtopicname)."\" title=\"".f_htmlattr($strtopicname)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($strtopicname)."</div></div>";
										$arrmeta=array();
										if ($strtopictype!="") { $arrmeta[]=$strtopictype; }
										if ($strtopicsource!="") { $arrmeta[]=$strtopicsource; }
										$strtopicrating=isset($rstemprecord["IMDB_RATING"]) ? $rstemprecord["IMDB_RATING"] : "";
										$strcardreveal=f_buildcardreveal($strtopicname, $arrmeta, $strtopicrating, "");
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										break;
									case "ID_COMPANY":
										$arrresultkeyvalues[]=$strvalue;
										if ($strrowurl=="") {
											$strrowurl="company.php?companyid=".$strvalue;
										} else {
											$strcellurl="company.php?companyid=".$strvalue;
										}
										if (isset($rstemprecord["COMPANY_NAME"])) {
											$strcompanyname=$rstemprecord["COMPANY_NAME"];
										} else {
											$strcompanyname=f_descfromcode("T_WC_TMDB_COMPANY","ID_COMPANY","COMPANY_NAME",$strvalue);
										}
										if (isset($rstemprecord["LOGO_PATH"])) {
											$strcompanylogo=$rstemprecord["LOGO_PATH"];
										} else {
											$strcompanylogo=f_descfromcode("T_WC_TMDB_COMPANY","ID_COMPANY","LOGO_PATH",$strvalue);
										}
										$strsyntheticimagepath=f_getsyntheticimagepath("company",f_stringtolong($strvalue));
										if ($strsyntheticimagepath!="") {
											//Padded master: already a 2:3 canvas with the baked-in halo
											$strimagepath=$strsyntheticimagepath;
											$strcardmediaclass="search-poster-card-media";
										} else {
											$strimagepath=f_getcompanylogopicture($strcompanylogo);
											//Raw logo fallback: letterbox + halo (no padded master yet)
											$strcardmediaclass=(trim($strcompanylogo)!="") ? "search-poster-card-media search-poster-card-media-contain" : "search-poster-card-media";
										}
										$strcardmedia="<div class=\"".$strcardmediaclass."\"><img src=\"".f_htmlattr($strimagepath)."\" alt=\"".f_htmlattr($strcompanyname)."\" title=\"".f_htmlattr($strcompanyname)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($strcompanyname)."</div></div>";
										$arrmeta=array();
										if (isset($rstemprecord["ORIGIN_COUNTRY"])) { $arrmeta[]=$rstemprecord["ORIGIN_COUNTRY"]; }
										if (isset($rstemprecord["HEADQUARTERS"])) { $arrmeta[]=$rstemprecord["HEADQUARTERS"]; }
										$strcompanydesc=isset($rstemprecord["DESCRIPTION"]) ? $rstemprecord["DESCRIPTION"] : "";
										$strcardreveal=f_buildcardreveal($strcompanyname, $arrmeta, "", $strcompanydesc);
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										break;
									case "ID_NETWORK":
										$arrresultkeyvalues[]=$strvalue;
										if ($strrowurl=="") {
											$strrowurl="network.php?networkid=".$strvalue;
										} else {
											$strcellurl="network.php?networkid=".$strvalue;
										}
										if (isset($rstemprecord["NETWORK_NAME"])) {
											$strnetworkname=$rstemprecord["NETWORK_NAME"];
										} else {
											$strnetworkname=f_descfromcode("T_WC_TMDB_NETWORK","ID_NETWORK","NETWORK_NAME",$strvalue);
										}
										if (isset($rstemprecord["LOGO_PATH"])) {
											$strnetworklogo=$rstemprecord["LOGO_PATH"];
										} else {
											$strnetworklogo=f_descfromcode("T_WC_TMDB_NETWORK","ID_NETWORK","LOGO_PATH",$strvalue);
										}
										$strsyntheticimagepath=f_getsyntheticimagepath("network",f_stringtolong($strvalue));
										if ($strsyntheticimagepath!="") {
											//Padded master: already a 2:3 canvas with the baked-in halo
											$strimagepath=$strsyntheticimagepath;
											$strcardmediaclass="search-poster-card-media";
										} else {
											$strimagepath=f_getnetworklogopicture($strnetworklogo);
											//Raw logo fallback: letterbox + halo (no padded master yet)
											$strcardmediaclass=(trim($strnetworklogo)!="") ? "search-poster-card-media search-poster-card-media-contain" : "search-poster-card-media";
										}
										$strcardmedia="<div class=\"".$strcardmediaclass."\"><img src=\"".f_htmlattr($strimagepath)."\" alt=\"".f_htmlattr($strnetworkname)."\" title=\"".f_htmlattr($strnetworkname)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($strnetworkname)."</div></div>";
										$arrmeta=array();
										if (isset($rstemprecord["ORIGIN_COUNTRY"])) { $arrmeta[]=$rstemprecord["ORIGIN_COUNTRY"]; }
										$strcardreveal=f_buildcardreveal($strnetworkname, $arrmeta, "", "");
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										break;
									case "ID_WIKIDATA":
										$arrresultkeyvalues[]=$strvalue;
										$strpropertyid="";
										if (isset($rstemprecord["ID_PROPERTY"])) {
											$strpropertyid=$rstemprecord["ID_PROPERTY"];
										}
										$strwikidataurl="wikidata-query.php?search=".$strpropertyid." ".$strvalue."&val=Search";
										if ($strrowurl=="") {
											$strrowurl=$strwikidataurl;
										} else {
											$strcellurl=$strwikidataurl;
										}
										if (isset($rstemprecord["ITEM_LABEL"])) {
											$stritemlabel=$rstemprecord["ITEM_LABEL"];
										} else {
											$stritemlabel=f_descfromcode("T_WC_WIKIDATA_ITEM_V1","ID_WIKIDATA","LABEL",$strvalue);
										}
										if (isset($rstemprecord["WIKIPEDIA_IMAGE_PATH"])) {
											$strwikipediapath=$rstemprecord["WIKIPEDIA_IMAGE_PATH"];
										} else {
											$strwikipediapath=f_descfromcode("T_WC_WIKIDATA_ITEM_V1","ID_WIKIDATA","WIKIPEDIA_IMAGE_PATH",f_stringtosql($strvalue));
										}
										if (trim($strwikipediapath)=="") {
											$strwikipediapath="/images/No_Image_Available.jpg";
										}
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr($strwikipediapath)."\" alt=\"".f_htmlattr($stritemlabel)."\" title=\"".f_htmlattr($stritemlabel)."\" width=\"300\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($stritemlabel)."</div></div>";
										$strcardreveal=f_buildcardreveal($stritemlabel, array(), "", "");
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										break;
									case "ID_T2S_LIST":
										$arrresultkeyvalues[]=$strvalue;
										if ($strrowurl=="") {
											$strrowurl="t2slist.php?listid=".$strvalue;
										} else {
											$strcellurl="t2slist.php?listid=".$strvalue;
										}
										if (isset($rstemprecord["LIST_NAME"])) {
											$strlistname=$rstemprecord["LIST_NAME"];
										} else {
											$strlistname=f_descfromcode("T_WC_T2S_LIST","ID_T2S_LIST","LIST_NAME",$strvalue);
										}
										if (isset($rstemprecord["POSTER_PATH"]) && trim($rstemprecord["POSTER_PATH"])!="") {
											$strimagepath=f_getmovieposterpicture($rstemprecord["POSTER_PATH"],4);
										} elseif (isset($rstemprecord["WIKIPEDIA_IMAGE_PATH"]) && trim($rstemprecord["WIKIPEDIA_IMAGE_PATH"])!="") {
											$strimagepath=$rstemprecord["WIKIPEDIA_IMAGE_PATH"];
										} else {
											$strimagepath="/images/No_Image_Available.jpg";
										}
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr($strimagepath)."\" alt=\"".f_htmlattr($strlistname)."\" title=\"".f_htmlattr($strlistname)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($strlistname)."</div></div>";
										$arrmeta=array();
										if (isset($rstemprecord["LIST_TYPE"]) && trim($rstemprecord["LIST_TYPE"])!="") { $arrmeta[]=$rstemprecord["LIST_TYPE"]; }
										$strlistmoviecount=isset($rstemprecord["MOVIE_COUNT"]) ? (int)$rstemprecord["MOVIE_COUNT"] : 0;
										$strlistseriecount=isset($rstemprecord["SERIE_COUNT"]) ? (int)$rstemprecord["SERIE_COUNT"] : 0;
										if ($strlistmoviecount>0) { $arrmeta[]=$strlistmoviecount.($strlistmoviecount>1?" movies":" movie"); }
										if ($strlistseriecount>0) { $arrmeta[]=$strlistseriecount.($strlistseriecount>1?" series":" serie"); }
										$strlistrating=isset($rstemprecord["IMDB_RATING"]) ? $rstemprecord["IMDB_RATING"] : "";
										$strlistoverview=isset($rstemprecord["OVERVIEW"]) ? $rstemprecord["OVERVIEW"] : "";
										$strcardreveal=f_buildcardreveal($strlistname, $arrmeta, $strlistrating, $strlistoverview);
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										break;
									case "ID_T2S_COLLECTION":
										$arrresultkeyvalues[]=$strvalue;
										if ($strrowurl=="") {
											$strrowurl="t2scollection.php?collectionid=".$strvalue;
										} else {
											$strcellurl="t2scollection.php?collectionid=".$strvalue;
										}
										if (isset($rstemprecord["COLLECTION_NAME"])) {
											$strcollectionname=$rstemprecord["COLLECTION_NAME"];
										} else {
											$strcollectionname=f_descfromcode("T_WC_T2S_COLLECTION","ID_T2S_COLLECTION","COLLECTION_NAME",$strvalue);
										}
										if (isset($rstemprecord["POSTER_PATH"]) && trim($rstemprecord["POSTER_PATH"])!="") {
											$strimagepath=f_getmovieposterpicture($rstemprecord["POSTER_PATH"],4);
										} elseif (isset($rstemprecord["WIKIPEDIA_IMAGE_PATH"]) && trim($rstemprecord["WIKIPEDIA_IMAGE_PATH"])!="") {
											$strimagepath=$rstemprecord["WIKIPEDIA_IMAGE_PATH"];
										} else {
											$strimagepath="/images/No_Image_Available.jpg";
										}
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr($strimagepath)."\" alt=\"".f_htmlattr($strcollectionname)."\" title=\"".f_htmlattr($strcollectionname)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($strcollectionname)."</div></div>";
										$arrmeta=array();
										if (isset($rstemprecord["COLLECTION_TYPE"]) && trim($rstemprecord["COLLECTION_TYPE"])!="") { $arrmeta[]=$rstemprecord["COLLECTION_TYPE"]; }
										$strcollmoviecount=isset($rstemprecord["MOVIE_COUNT"]) ? (int)$rstemprecord["MOVIE_COUNT"] : 0;
										$strcollseriecount=isset($rstemprecord["SERIE_COUNT"]) ? (int)$rstemprecord["SERIE_COUNT"] : 0;
										if ($strcollmoviecount>0) { $arrmeta[]=$strcollmoviecount.($strcollmoviecount>1?" movies":" movie"); }
										if ($strcollseriecount>0) { $arrmeta[]=$strcollseriecount.($strcollseriecount>1?" series":" serie"); }
										$strcollrating=isset($rstemprecord["IMDB_RATING"]) ? $rstemprecord["IMDB_RATING"] : "";
										$strcolloverview=isset($rstemprecord["OVERVIEW"]) ? $rstemprecord["OVERVIEW"] : "";
										$strcardreveal=f_buildcardreveal($strcollectionname, $arrmeta, $strcollrating, $strcolloverview);
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										break;
									case "ID_MOVEMENT":
										$arrresultkeyvalues[]=$strvalue;
										if ($strrowurl=="") {
											$strrowurl="movement.php?movementid=".$strvalue;
										} else {
											$strcellurl="movement.php?movementid=".$strvalue;
										}
										if (isset($rstemprecord["MOVEMENT_NAME"])) {
											$strmovementname=$rstemprecord["MOVEMENT_NAME"];
										} else {
											$strmovementname=f_descfromcode("T_WC_T2S_MOVEMENT","ID_MOVEMENT","MOVEMENT_NAME",$strvalue);
										}
										if (isset($rstemprecord["POSTER_PATH"]) && trim($rstemprecord["POSTER_PATH"])!="") {
											$strimagepath=f_getmovieposterpicture($rstemprecord["POSTER_PATH"],4);
										} elseif (isset($rstemprecord["WIKIPEDIA_IMAGE_PATH"]) && trim($rstemprecord["WIKIPEDIA_IMAGE_PATH"])!="") {
											$strimagepath=$rstemprecord["WIKIPEDIA_IMAGE_PATH"];
										} else {
											$strimagepath="/images/No_Image_Available.jpg";
										}
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr($strimagepath)."\" alt=\"".f_htmlattr($strmovementname)."\" title=\"".f_htmlattr($strmovementname)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($strmovementname)."</div></div>";
										$arrmeta=array();
										if (isset($rstemprecord["MOVEMENT_TYPE"]) && trim($rstemprecord["MOVEMENT_TYPE"])!="") { $arrmeta[]=$rstemprecord["MOVEMENT_TYPE"]; }
										$strmovmoviecount=isset($rstemprecord["MOVIE_COUNT"]) ? (int)$rstemprecord["MOVIE_COUNT"] : 0;
										$strmovseriecount=isset($rstemprecord["SERIE_COUNT"]) ? (int)$rstemprecord["SERIE_COUNT"] : 0;
										if ($strmovmoviecount>0) { $arrmeta[]=$strmovmoviecount.($strmovmoviecount>1?" movies":" movie"); }
										if ($strmovseriecount>0) { $arrmeta[]=$strmovseriecount.($strmovseriecount>1?" series":" serie"); }
										$strmovrating=isset($rstemprecord["IMDB_RATING"]) ? $rstemprecord["IMDB_RATING"] : "";
										$strmovoverview=isset($rstemprecord["OVERVIEW"]) ? $rstemprecord["OVERVIEW"] : "";
										$strcardreveal=f_buildcardreveal($strmovementname, $arrmeta, $strmovrating, $strmovoverview);
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										break;
									case "ID_GROUP":
										$arrresultkeyvalues[]=$strvalue;
										if ($strrowurl=="") {
											$strrowurl="group.php?groupid=".$strvalue;
										} else {
											$strcellurl="group.php?groupid=".$strvalue;
										}
										if (isset($rstemprecord["GROUP_NAME"])) {
											$strgroupname=$rstemprecord["GROUP_NAME"];
										} else {
											$strgroupname=f_descfromcode("T_WC_T2S_GROUP","ID_GROUP","GROUP_NAME",$strvalue);
										}
										if (isset($rstemprecord["PROFILE_PATH"]) && trim($rstemprecord["PROFILE_PATH"])!="") {
											$strimagepath=f_getpersonprofilepicture($rstemprecord["PROFILE_PATH"]);
										} elseif (isset($rstemprecord["WIKIPEDIA_IMAGE_PATH"]) && trim($rstemprecord["WIKIPEDIA_IMAGE_PATH"])!="") {
											$strimagepath=$rstemprecord["WIKIPEDIA_IMAGE_PATH"];
										} else {
											$strimagepath="/images/No_Image_Available.jpg";
										}
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr($strimagepath)."\" alt=\"".f_htmlattr($strgroupname)."\" title=\"".f_htmlattr($strgroupname)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($strgroupname)."</div></div>";
										$arrmeta=array();
										if (isset($rstemprecord["GROUP_TYPE"]) && trim($rstemprecord["GROUP_TYPE"])!="") { $arrmeta[]=$rstemprecord["GROUP_TYPE"]; }
										$strgrouppersoncount=isset($rstemprecord["PERSON_COUNT"]) ? (int)$rstemprecord["PERSON_COUNT"] : 0;
										if ($strgrouppersoncount>0) { $arrmeta[]=$strgrouppersoncount.($strgrouppersoncount>1?" persons":" person"); }
										$strgroupoverview=isset($rstemprecord["OVERVIEW"]) ? $rstemprecord["OVERVIEW"] : "";
										$strcardreveal=f_buildcardreveal($strgroupname, $arrmeta, "", $strgroupoverview);
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										break;
									case "ID_GENRE":
										$arrresultkeyvalues[]=$strvalue;
										if ($strrowurl=="") {
											$strrowurl="genre.php?genreid=".$strvalue;
										} else {
											$strcellurl="genre.php?genreid=".$strvalue;
										}
										if (isset($rstemprecord["GENRE_NAME"])) {
											$strgenrename=$rstemprecord["GENRE_NAME"];
										} else {
											$strgenrename=f_descfromcode("T_WC_TMDB_GENRE","id","name",$strvalue);
										}
										$strimagepath="/images/No_Image_Available.jpg";
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr($strimagepath)."\" alt=\"".f_htmlattr($strgenrename)."\" title=\"".f_htmlattr($strgenrename)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($strgenrename)."</div></div>";
										$arrmeta=array();
										$strcardreveal=f_buildcardreveal($strgenrename, $arrmeta, "", "");
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										break;
									case "ID_DEATH":
										$arrresultkeyvalues[]=$strvalue;
										if ($strrowurl=="") {
											$strrowurl="death.php?deathid=".$strvalue;
										} else {
											$strcellurl="death.php?deathid=".$strvalue;
										}
										if (isset($rstemprecord["DEATH_NAME"])) {
											$strdeathname=$rstemprecord["DEATH_NAME"];
										} else {
											$strdeathname=f_descfromcode("T_WC_T2S_DEATH","ID_DEATH","DEATH_NAME",$strvalue);
										}
										if (isset($rstemprecord["PROFILE_PATH"]) && trim($rstemprecord["PROFILE_PATH"])!="") {
											$strimagepath=f_getpersonprofilepicture($rstemprecord["PROFILE_PATH"]);
										} elseif (isset($rstemprecord["WIKIPEDIA_IMAGE_PATH"]) && trim($rstemprecord["WIKIPEDIA_IMAGE_PATH"])!="") {
											$strimagepath=$rstemprecord["WIKIPEDIA_IMAGE_PATH"];
										} else {
											$strimagepath="/images/No_Image_Available.jpg";
										}
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr($strimagepath)."\" alt=\"".f_htmlattr($strdeathname)."\" title=\"".f_htmlattr($strdeathname)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($strdeathname)."</div></div>";
										$arrmeta=array();
										if (isset($rstemprecord["DEATH_TYPE"]) && trim($rstemprecord["DEATH_TYPE"])!="") { $arrmeta[]=$rstemprecord["DEATH_TYPE"]; }
										$strdeathpersoncount=isset($rstemprecord["PERSON_COUNT"]) ? (int)$rstemprecord["PERSON_COUNT"] : 0;
										if ($strdeathpersoncount>0) { $arrmeta[]=$strdeathpersoncount.($strdeathpersoncount>1?" persons":" person"); }
										$strdeathoverview=isset($rstemprecord["OVERVIEW"]) ? $rstemprecord["OVERVIEW"] : "";
										$strcardreveal=f_buildcardreveal($strdeathname, $arrmeta, "", $strdeathoverview);
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										break;
									case "ID_AWARD":
										$arrresultkeyvalues[]=$strvalue;
										if ($strrowurl=="") {
											$strrowurl="award.php?awardid=".$strvalue;
										} else {
											$strcellurl="award.php?awardid=".$strvalue;
										}
										if (isset($rstemprecord["AWARD_NAME"])) {
											$strawardname=$rstemprecord["AWARD_NAME"];
										} else {
											$strawardname=f_descfromcode("T_WC_T2S_AWARD","ID_AWARD","AWARD_NAME",$strvalue);
										}
										if (isset($rstemprecord["POSTER_PATH"]) && trim($rstemprecord["POSTER_PATH"])!="") {
											$strimagepath=f_getmovieposterpicture($rstemprecord["POSTER_PATH"],4);
										} elseif (isset($rstemprecord["WIKIPEDIA_IMAGE_PATH"]) && trim($rstemprecord["WIKIPEDIA_IMAGE_PATH"])!="") {
											$strimagepath=$rstemprecord["WIKIPEDIA_IMAGE_PATH"];
										} else {
											$strimagepath="/images/No_Image_Available.jpg";
										}
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr($strimagepath)."\" alt=\"".f_htmlattr($strawardname)."\" title=\"".f_htmlattr($strawardname)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($strawardname)."</div></div>";
										$arrmeta=array();
										if (isset($rstemprecord["AWARD_TYPE"]) && trim($rstemprecord["AWARD_TYPE"])!="") { $arrmeta[]=$rstemprecord["AWARD_TYPE"]; }
										$strawardmoviecount=isset($rstemprecord["MOVIE_COUNT"]) ? (int)$rstemprecord["MOVIE_COUNT"] : 0;
										$strawardseriecount=isset($rstemprecord["SERIE_COUNT"]) ? (int)$rstemprecord["SERIE_COUNT"] : 0;
										$strawardpersoncount=isset($rstemprecord["PERSON_COUNT"]) ? (int)$rstemprecord["PERSON_COUNT"] : 0;
										if ($strawardmoviecount>0) { $arrmeta[]=$strawardmoviecount.($strawardmoviecount>1?" movies":" movie"); }
										if ($strawardseriecount>0) { $arrmeta[]=$strawardseriecount.($strawardseriecount>1?" series":" serie"); }
										if ($strawardpersoncount>0) { $arrmeta[]=$strawardpersoncount.($strawardpersoncount>1?" persons":" person"); }
										$strawardrating=isset($rstemprecord["IMDB_RATING"]) ? $rstemprecord["IMDB_RATING"] : "";
										$strawardoverview=isset($rstemprecord["OVERVIEW"]) ? $rstemprecord["OVERVIEW"] : "";
										$strcardreveal=f_buildcardreveal($strawardname, $arrmeta, $strawardrating, $strawardoverview);
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										break;
									case "ID_NOMINATION":
										$arrresultkeyvalues[]=$strvalue;
										if ($strrowurl=="") {
											$strrowurl="nomination.php?nominationid=".$strvalue;
										} else {
											$strcellurl="nomination.php?nominationid=".$strvalue;
										}
										if (isset($rstemprecord["NOMINATION_NAME"])) {
											$strnominationname=$rstemprecord["NOMINATION_NAME"];
										} else {
											$strnominationname=f_descfromcode("T_WC_T2S_NOMINATION","ID_NOMINATION","NOMINATION_NAME",$strvalue);
										}
										if (isset($rstemprecord["POSTER_PATH"]) && trim($rstemprecord["POSTER_PATH"])!="") {
											$strimagepath=f_getmovieposterpicture($rstemprecord["POSTER_PATH"],4);
										} elseif (isset($rstemprecord["WIKIPEDIA_IMAGE_PATH"]) && trim($rstemprecord["WIKIPEDIA_IMAGE_PATH"])!="") {
											$strimagepath=$rstemprecord["WIKIPEDIA_IMAGE_PATH"];
										} else {
											$strimagepath="/images/No_Image_Available.jpg";
										}
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr($strimagepath)."\" alt=\"".f_htmlattr($strnominationname)."\" title=\"".f_htmlattr($strnominationname)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($strnominationname)."</div></div>";
										$arrmeta=array();
										if (isset($rstemprecord["NOMINATION_TYPE"]) && trim($rstemprecord["NOMINATION_TYPE"])!="") { $arrmeta[]=$rstemprecord["NOMINATION_TYPE"]; }
										$strnommoviecount=isset($rstemprecord["MOVIE_COUNT"]) ? (int)$rstemprecord["MOVIE_COUNT"] : 0;
										$strnomseriecount=isset($rstemprecord["SERIE_COUNT"]) ? (int)$rstemprecord["SERIE_COUNT"] : 0;
										$strnompersoncount=isset($rstemprecord["PERSON_COUNT"]) ? (int)$rstemprecord["PERSON_COUNT"] : 0;
										if ($strnommoviecount>0) { $arrmeta[]=$strnommoviecount.($strnommoviecount>1?" movies":" movie"); }
										if ($strnomseriecount>0) { $arrmeta[]=$strnomseriecount.($strnomseriecount>1?" series":" serie"); }
										if ($strnompersoncount>0) { $arrmeta[]=$strnompersoncount.($strnompersoncount>1?" persons":" person"); }
										$strnomrating=isset($rstemprecord["IMDB_RATING"]) ? $rstemprecord["IMDB_RATING"] : "";
										$strnomoverview=isset($rstemprecord["OVERVIEW"]) ? $rstemprecord["OVERVIEW"] : "";
										$strcardreveal=f_buildcardreveal($strnominationname, $arrmeta, $strnomrating, $strnomoverview);
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										break;
									case "ID_TECHNICAL":
										$arrresultkeyvalues[]=$strvalue;
										if ($strrowurl=="") {
											$strrowurl="technical.php?technicalid=".$strvalue;
										} else {
											$strcellurl="technical.php?technicalid=".$strvalue;
										}
										if (isset($rstemprecord["DESCRIPTION"])) {
											$strtechnicalname=$rstemprecord["DESCRIPTION"];
										} else {
											$strtechnicalname=f_descfromcode("T_WC_T2S_TECHNICAL","ID_TECHNICAL","DESCRIPTION",$strvalue);
										}
										if ($struilang!="en" && isset($rstemprecord["DESCRIPTION_FR"]) && trim($rstemprecord["DESCRIPTION_FR"])!="") {
											$strtechnicalname=$rstemprecord["DESCRIPTION_FR"];
										}
										if (isset($rstemprecord["WIKIPEDIA_IMAGE_PATH"]) && trim($rstemprecord["WIKIPEDIA_IMAGE_PATH"])!="") {
											$strimagepath=$rstemprecord["WIKIPEDIA_IMAGE_PATH"];
										} else {
											$strimagepath="/images/No_Image_Available.jpg";
										}
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr($strimagepath)."\" alt=\"".f_htmlattr($strtechnicalname)."\" title=\"".f_htmlattr($strtechnicalname)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($strtechnicalname)."</div></div>";
										$arrmeta=array();
										if (isset($rstemprecord["TECHNICAL_TYPE"]) && trim($rstemprecord["TECHNICAL_TYPE"])!="") { $arrmeta[]=$rstemprecord["TECHNICAL_TYPE"]; }
										$strtechmoviecount=isset($rstemprecord["MOVIE_COUNT"]) ? (int)$rstemprecord["MOVIE_COUNT"] : 0;
										$strtechseriecount=isset($rstemprecord["SERIE_COUNT"]) ? (int)$rstemprecord["SERIE_COUNT"] : 0;
										if ($strtechmoviecount>0) { $arrmeta[]=$strtechmoviecount.($strtechmoviecount>1?" movies":" movie"); }
										if ($strtechseriecount>0) { $arrmeta[]=$strtechseriecount.($strtechseriecount>1?" series":" serie"); }
										$strtechrating=isset($rstemprecord["IMDB_RATING"]) ? $rstemprecord["IMDB_RATING"] : (isset($rstemprecord["IMDB_RATING_WEIGHTED"]) ? $rstemprecord["IMDB_RATING_WEIGHTED"] : "");
										$strtechoverview=isset($rstemprecord["OVERVIEW"]) ? $rstemprecord["OVERVIEW"] : "";
										$strcardreveal=f_buildcardreveal($strtechnicalname, $arrmeta, $strtechrating, $strtechoverview);
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										break;
									case "ID_LOCATION":
										// Les lieux avaient pour seule carte celle de Wikidata, faute de read-model. Depuis
										// T_WC_T2S_LOCATION ils se lisent comme les autres entites T2S, et la carte porte les
										// memes champs que la carte mouvement : image, nom, type, comptes, note et resume.
										$arrresultkeyvalues[]=$strvalue;
										if ($strrowurl=="") {
											$strrowurl="location.php?locationid=".$strvalue;
										} else {
											$strcellurl="location.php?locationid=".$strvalue;
										}
										if (isset($rstemprecord["LOCATION_NAME"])) {
											$strlocationname=$rstemprecord["LOCATION_NAME"];
										} else {
											$strlocationname=f_descfromcode("T_WC_T2S_LOCATION","ID_LOCATION","LOCATION_NAME",$strvalue);
										}
										if (isset($rstemprecord["POSTER_PATH"]) && trim($rstemprecord["POSTER_PATH"])!="") {
											$strimagepath=f_getmovieposterpicture($rstemprecord["POSTER_PATH"],4);
										} elseif (isset($rstemprecord["WIKIPEDIA_IMAGE_PATH"]) && trim($rstemprecord["WIKIPEDIA_IMAGE_PATH"])!="") {
											$strimagepath=$rstemprecord["WIKIPEDIA_IMAGE_PATH"];
										} else {
											$strimagepath="/images/No_Image_Available.jpg";
										}
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr($strimagepath)."\" alt=\"".f_htmlattr($strlocationname)."\" title=\"".f_htmlattr($strlocationname)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($strlocationname)."</div></div>";
										$arrmeta=array();
										if (isset($rstemprecord["LOCATION_TYPE"]) && trim($rstemprecord["LOCATION_TYPE"])!="") { $arrmeta[]=$rstemprecord["LOCATION_TYPE"]; }
										$strlocmoviecount=isset($rstemprecord["MOVIE_COUNT"]) ? (int)$rstemprecord["MOVIE_COUNT"] : 0;
										$strlocseriecount=isset($rstemprecord["SERIE_COUNT"]) ? (int)$rstemprecord["SERIE_COUNT"] : 0;
										if ($strlocmoviecount>0) { $arrmeta[]=$strlocmoviecount.($strlocmoviecount>1?" movies":" movie"); }
										if ($strlocseriecount>0) { $arrmeta[]=$strlocseriecount.($strlocseriecount>1?" series":" serie"); }
										// EPISODE_COUNT, lu seulement si l'API le rend.
										$strlocepisodecount=isset($rstemprecord["EPISODE_COUNT"]) ? (int)$rstemprecord["EPISODE_COUNT"] : 0;
										if ($strlocepisodecount>0) { $arrmeta[]=$strlocepisodecount.($strlocepisodecount>1?" episodes":" episode"); }
										$strlocrating=isset($rstemprecord["IMDB_RATING"]) ? $rstemprecord["IMDB_RATING"] : "";
										$strlocoverview=isset($rstemprecord["OVERVIEW"]) ? $rstemprecord["OVERVIEW"] : "";
										$strcardreveal=f_buildcardreveal($strlocationname, $arrmeta, $strlocrating, $strlocoverview);
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										break;
									case "ID_SOURCE_WORK":
										// Oeuvres sources, "d'apres" : roman, manga, piece, jeu, ou
										// film et serie eux-memes. Meme carte que le lieu, plus l'annee de l'oeuvre et
										// le nom dans la langue de l'interface. Colonnes lues : celles que le prompt de
										// l'API declare sous "Source works - return", avec repli sur la table T2S.
										$lngsourceworkid=f_stringtolong($strvalue);
										$arrresultkeyvalues[]=$strvalue;
										if ($strrowurl=="") {
											$strrowurl="sourcework.php?sourceworkid=".$lngsourceworkid;
										} else {
											$strcellurl="sourcework.php?sourceworkid=".$lngsourceworkid;
										}
										$strsourceworkname="";
										if ($struilang!="en") {
											if (isset($rstemprecord["SOURCE_WORK_NAME_FR"])) {
												$strsourceworkname=trim((string)$rstemprecord["SOURCE_WORK_NAME_FR"]);
											} else {
												$strsourceworkname=trim((string)f_descfromcode("T_WC_T2S_SOURCE_WORK","ID_SOURCE_WORK","SOURCE_WORK_NAME_FR",$lngsourceworkid));
											}
										}
										if ($strsourceworkname=="") {
											if (isset($rstemprecord["SOURCE_WORK_NAME"])) {
												$strsourceworkname=trim((string)$rstemprecord["SOURCE_WORK_NAME"]);
											} else {
												$strsourceworkname=trim((string)f_descfromcode("T_WC_T2S_SOURCE_WORK","ID_SOURCE_WORK","SOURCE_WORK_NAME",$lngsourceworkid));
											}
										}
										// L'image d'une oeuvre vient de Wikipedia (process 71), que l'API ne renvoie
										// pas forcement : POSTER_PATH (vide pour un livre), WIKIPEDIA_IMAGE_PATH, puis
										// l'image principale de l'article lue dans la table.
										$strimagepath="";
										if (isset($rstemprecord["POSTER_PATH"]) && trim((string)$rstemprecord["POSTER_PATH"])!="") {
											$strimagepath=f_getmovieposterpicture($rstemprecord["POSTER_PATH"],4);
										} elseif (isset($rstemprecord["WIKIPEDIA_IMAGE_PATH"]) && trim((string)$rstemprecord["WIKIPEDIA_IMAGE_PATH"])!="") {
											$strimagepath=$rstemprecord["WIKIPEDIA_IMAGE_PATH"];
										} else {
											if ($struilang!="en") {
												$strimagepath=trim((string)f_descfromcode("T_WC_T2S_SOURCE_WORK","ID_SOURCE_WORK","WIKIPEDIA_MAIN_IMAGE_URL_FR",$lngsourceworkid));
											}
											if ($strimagepath=="") {
												$strimagepath=trim((string)f_descfromcode("T_WC_T2S_SOURCE_WORK","ID_SOURCE_WORK","WIKIPEDIA_MAIN_IMAGE_URL",$lngsourceworkid));
											}
										}
										if ($strimagepath=="") {
											$strimagepath="/images/No_Image_Available.jpg";
										}
										$strsourceworkyear=isset($rstemprecord["SOURCE_WORK_YEAR"]) ? trim((string)$rstemprecord["SOURCE_WORK_YEAR"]) : "";
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr($strimagepath)."\" alt=\"".f_htmlattr($strsourceworkname)."\" title=\"".f_htmlattr($strsourceworkname)."\" /></div>";
										// La ligne de l'annee est toujours emise, vide comprise (hauteur constante).
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($strsourceworkname)."</div><div class=\"search-poster-card-year\">".f_htmlspecialchars($strsourceworkyear)."</div></div>";
										$arrmeta=array();
										if (isset($rstemprecord["SOURCE_WORK_FORM"]) && trim((string)$rstemprecord["SOURCE_WORK_FORM"])!="") {
											$arrmeta[]=str_replace("_"," ",$rstemprecord["SOURCE_WORK_FORM"]);
										} elseif (isset($rstemprecord["SOURCE_WORK_TYPE"]) && trim((string)$rstemprecord["SOURCE_WORK_TYPE"])!="") {
											$arrmeta[]=$rstemprecord["SOURCE_WORK_TYPE"];
										}
										$strswmoviecount=isset($rstemprecord["MOVIE_COUNT"]) ? (int)$rstemprecord["MOVIE_COUNT"] : 0;
										$strswseriecount=isset($rstemprecord["SERIE_COUNT"]) ? (int)$rstemprecord["SERIE_COUNT"] : 0;
										if ($strswmoviecount>0) { $arrmeta[]=$strswmoviecount.($strswmoviecount>1?" movies":" movie"); }
										if ($strswseriecount>0) { $arrmeta[]=$strswseriecount.($strswseriecount>1?" series":" serie"); }
										$strswrating=isset($rstemprecord["IMDB_RATING"]) ? $rstemprecord["IMDB_RATING"] : "";
										$strswoverview=isset($rstemprecord["OVERVIEW"]) ? $rstemprecord["OVERVIEW"] : "";
										$strcardreveal=f_buildcardreveal($strsourceworkname, $arrmeta, $strswrating, $strswoverview);
										$strcell=$strcardmedia.$strcardtext.$strcardreveal;
										break;
									case "ID_ROW":
										// Image-wall row (person_image / movie_image / serie_image result_entity): each
										// T_WC_T2S_*_IMAGE row is one image, its path carried in POSTER_PATH (aliased from
										// IMAGE_PATH). Render the image itself, linked to its parent entity, instead of
										// falling through to the raw ID_ROW value.
										$arrresultkeyvalues[]=$strvalue;
										$strimagerawpath="";
										if (isset($rstemprecord["POSTER_PATH"])) {
											$strimagerawpath=$rstemprecord["POSTER_PATH"];
										} elseif (isset($rstemprecord["IMAGE_PATH"])) {
											$strimagerawpath=$rstemprecord["IMAGE_PATH"];
										}
										$strrowtypeimage=isset($rstemprecord["TYPE_IMAGE"]) ? strtolower(trim((string)$rstemprecord["TYPE_IMAGE"])) : "";
										$stralttext="";
										$strimageurl="";
										if (isset($rstemprecord["ID_PERSON"])) {
											$strrowurl="person.php?personid=".$rstemprecord["ID_PERSON"];
											$stralttext=f_descfromcode("T_WC_T2S_PERSON","ID_PERSON","PERSON_NAME",$rstemprecord["ID_PERSON"]);
											$strimageurl=f_getpersonprofilepicture($strimagerawpath,2);
										} elseif (isset($rstemprecord["ID_MOVIE"])) {
											$strrowurl="movie.php?movieid=".$rstemprecord["ID_MOVIE"];
											$stralttext=f_descfromcode("T_WC_T2S_MOVIE","ID_MOVIE","MOVIE_TITLE",$rstemprecord["ID_MOVIE"]);
											$strimageurl=f_getmovieposterpicture($strimagerawpath,4);
										} elseif (isset($rstemprecord["ID_SERIE"])) {
											$strrowurl="serie.php?serieid=".$rstemprecord["ID_SERIE"];
											$stralttext=f_descfromcode("T_WC_T2S_SERIE","ID_SERIE","SERIE_TITLE",$rstemprecord["ID_SERIE"]);
											$strimageurl=f_getmovieposterpicture($strimagerawpath,4);
										} else {
											$strimageurl=f_getmovieposterpicture($strimagerawpath,4);
										}
										if ($strimagerawpath=="") {
											// Not an image row after all: fall back to the raw value so nothing is hidden.
											$strcell=f_htmlspecialchars($strvalue);
										} else {
											if ($strrowtypeimage=="logo" || $strrowtypeimage=="backdrop") {
												$strcardmediaclass="search-poster-card-media search-poster-card-media-contain";
											} else {
												$strcardmediaclass="search-poster-card-media";
											}
											$strcardmedia="<div class=\"".$strcardmediaclass."\"><img src=\"".f_htmlattr($strimageurl)."\" alt=\"".f_htmlattr($stralttext)."\" title=\"".f_htmlattr($stralttext)."\" /></div>";
											$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($stralttext)."</div></div>";
											$strcell=$strcardmedia.$strcardtext;
										}
										break;
									case "IMAGE_PATH":
									case "POSTER_PATH":
										$lngrecordid=0;
										$strrowurl="";
										$strahref="";
										$stralttext="";
										if (isset($rstemprecord["ID_PERSON"])) {
											$lngrecordid=$rstemprecord["ID_PERSON"];
											$strrowurl="person.php?personid=".$lngrecordid;
											$stralttext=f_descfromcode("T_WC_T2S_PERSON","ID_PERSON","PERSON_NAME",$lngrecordid);
										}
										if (isset($rstemprecord["ID_MOVIE"])) {
											$lngrecordid=$rstemprecord["ID_MOVIE"];
											$strrowurl="movie.php?movieid=".$lngrecordid;
											$strmovietitle=f_descfromcode("T_WC_T2S_MOVIE","ID_MOVIE","MOVIE_TITLE",$lngrecordid);
											$strmoviereleaseyear=f_descfromcode("T_WC_T2S_MOVIE","ID_MOVIE","RELEASE_YEAR",$lngrecordid);
											$strmoviefulltitle=$strmovietitle." (".$strmoviereleaseyear.")";
											$stralttext=$strmoviefulltitle;
											/*
											if ($intdebug) {
												echo "<br />f_descfromcode(T_WC_T2S_MOVIE, ID_MOVIE, MOVIE_TITLE, $lngrecordid)";
												echo "<br />-> ".$strmovietitle;
												echo "<br />f_descfromcode(T_WC_T2S_MOVIE, ID_MOVIE, RELEASE_YEAR, $lngrecordid)";
												echo "<br />-> ".$strmoviereleaseyear;
												echo "<br />\$strmoviefulltitle = ".$strmoviefulltitle;
												echo "<br />\$stralttext = ".$stralttext;
											}
											*/
										}
										if (isset($rstemprecord["ID_SERIE"])) {
											$lngrecordid=$rstemprecord["ID_SERIE"];
											$strrowurl="serie.php?serieid=".$lngrecordid;
											$strserietitle=f_descfromcode("T_WC_T2S_SERIE","ID_SERIE","SERIE_TITLE",$lngrecordid);
											$strseriefirstairyear=f_descfromcode("T_WC_T2S_SERIE","ID_SERIE","FIRST_AIR_YEAR",$lngrecordid);
											$strserielastairyear=f_descfromcode("T_WC_T2S_SERIE","ID_SERIE","LAST_AIR_YEAR",$lngrecordid);
											$strseriefulltitle=$strserietitle." (".$strseriefirstairyear."-".$strserielastairyear.")";
											$stralttext=$strseriefulltitle;
											/*
											if ($intdebug) {
												echo "<br />f_descfromcode(T_WC_T2S_SERIE, ID_SERIE, SERIE_TITLE, $lngrecordid)";
												echo "<br />-> ".$strserietitle;
												echo "<br />f_descfromcode(T_WC_T2S_SERIE, ID_SERIE, FIRST_AIR_YEAR, $lngrecordid)";
												echo "<br />-> ".$strseriefirstairyear;
												echo "<br />f_descfromcode(T_WC_T2S_SERIE, ID_SERIE, LAST_AIR_YEAR, $lngrecordid)";
												echo "<br />-> ".$strserielastairyear;
												echo "<br />\$strseriefulltitle = ".$strseriefulltitle;
											}
											*/
										}
										if (isset($rstemprecord["ID_COMPANY"])) {
											$lngrecordid=$rstemprecord["ID_COMPANY"];
											$strrowurl="company.php?companyid=".$lngrecordid;
											$stralttext=f_descfromcode("T_WC_TMDB_COMPANY","ID_COMPANY","COMPANY_NAME",$lngrecordid);
											/*
											if ($intdebug) {
												echo "<br />f_descfromcode(T_WC_T2S_SERIE, ID_COMPANY, COMPANY_NAME, $lngrecordid)";
												echo "<br />-> ".$stralttext;
											}
											*/
										}
										if (isset($rstemprecord["ID_NETWORK"])) {
											$lngrecordid=$rstemprecord["ID_NETWORK"];
											$strrowurl="network.php?networkid=".$lngrecordid;
											$stralttext=f_descfromcode("T_WC_T2S_NETWORK","ID_NETWORK","NETWORK_NAME",$lngrecordid);
											/*
											if ($intdebug) {
												echo "<br />f_descfromcode(T_WC_T2S_NETWORK, ID_NETWORK, NETWORK_NAME, $lngrecordid)";
												echo "<br />-> ".$stralttext;
											}
											*/
										}
										if ($lngrecordid>0) {
											$arrresultkeyvalues[]=$lngrecordid;
										}
										//Logo images (any entity: movie, serie, company, network): letterbox onto the
										//off-white canvas (matches the padded masters) instead of cropping to fill 2:3.
										//Company/network rows fall back to entity detection when the SQL did not
										//select TYPE_IMAGE (their galleries are logos by definition).
										$strrowtypeimage=isset($rstemprecord["TYPE_IMAGE"]) ? strtolower(trim((string)$rstemprecord["TYPE_IMAGE"])) : "";
										if ($strrowtypeimage=="logo"
											|| ($strrowtypeimage=="" && (isset($rstemprecord["ID_COMPANY"]) || isset($rstemprecord["ID_NETWORK"])))) {
											$strcardmediaclass="search-poster-card-media search-poster-card-media-contain";
										} else {
											$strcardmediaclass="search-poster-card-media";
										}
										$strcardmedia="<div class=\"".$strcardmediaclass."\"><img src=\"".f_htmlattr(f_getmovieposterpicture($strvalue,4))."\" alt=\"".f_htmlattr($stralttext)."\" title=\"".f_htmlattr($stralttext)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($stralttext)."</div></div>";
										$strcell=$strcardmedia.$strcardtext;
										break;
									case "PROFILE_PATH":
										$stralttext="";
										if (isset($rstemprecord["ID_PERSON"])) {
											$lngrecordid=$rstemprecord["ID_PERSON"];
											$strrowurl="person.php?personid=".$lngrecordid;
											$stralttext=f_descfromcode("T_WC_T2S_PERSON","ID_PERSON","PERSON_NAME",$lngrecordid);
										}
										if ($lngrecordid>0) {
											$arrresultkeyvalues[]=$lngrecordid;
										}
										$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr(f_getpersonprofilepicture($strvalue,2))."\" alt=\"".f_htmlattr($stralttext)."\" title=\"".f_htmlattr($stralttext)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($stralttext)."</div></div>";
										$strcell=$strcardmedia.$strcardtext;
										break;
									case "LOGO_PATH":
										$stralttext="";
										$strcardmedia="<div class=\"search-poster-card-media search-poster-card-media-contain\"><img src=\"".f_htmlattr(f_getcompanylogopicture($strvalue))."\" alt=\"".f_htmlattr($stralttext)."\" title=\"".f_htmlattr($stralttext)."\" /></div>";
										$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($stralttext)."</div></div>";
										$strcell=$strcardmedia.$strcardtext;
										break;
									//case "WIKIPEDIA_IMAGE_PATH":
									//	$stralttext="";
									//	$strcell="<img src=\"".$strvalue."\" alt=\"".$stralttext."\" title=\"".$stralttext."\" style=\"background-color: white;\" width=\"300\" /><br />".$stralttext;
									//	break;
									case "VIDEO_KEY":
										if (isset($rstemprecord["ID_MOVIE"])) {
											$lngrecordid=$rstemprecord["ID_MOVIE"];
											//$strrowurl="movie.php?movieid=".$lngrecordid;
											$strmovietitle=f_descfromcode("T_WC_T2S_MOVIE","ID_MOVIE","MOVIE_TITLE",$lngrecordid);
											$strmoviereleaseyear=f_descfromcode("T_WC_T2S_MOVIE","ID_MOVIE","RELEASE_YEAR",$lngrecordid);
											$strmoviefulltitle=$strmovietitle." (".$strmoviereleaseyear.")";
											$stralttext=$strmoviefulltitle;
										}
										if (isset($rstemprecord["ID_SERIE"])) {
											$lngrecordid=$rstemprecord["ID_SERIE"];
											//$strrowurl="serie.php?serieid=".$lngrecordid;
											$strserietitle=f_descfromcode("T_WC_T2S_SERIE","ID_SERIE","SERIE_TITLE",$lngrecordid);
											$strseriefirstairyear=f_descfromcode("T_WC_T2S_SERIE","ID_SERIE","FIRST_AIR_YEAR",$lngrecordid);
											$strserielastairyear=f_descfromcode("T_WC_T2S_SERIE","ID_SERIE","LAST_AIR_YEAR",$lngrecordid);
											$strseriefulltitle=$strserietitle." (".$strseriefirstairyear."-".$strserielastairyear.")";
											$stralttext=$strseriefulltitle;
										}
										if ($lngrecordid>0) {
											$arrresultkeyvalues[]=$lngrecordid;
										}
										$strvideosite=$rstemprecord["VIDEO_SITE"];
										$strvideoname=(isset($rstemprecord["VIDEO_NAME"]) ? (string)$rstemprecord["VIDEO_NAME"] : "");
										switch (strtolower($strvideosite)) {
										case "youtube":
											$strrowurl="yt-player.php?yid=".urlencode($strvalue);
											$strcellurl=$strrowurl;
											$strcardmedia="<div class=\"search-poster-card-media\"><img src=\"".f_htmlattr(f_getvideothumbnail($strvalue,$strvideosite,1))."\" alt=\"".f_htmlattr(trim($stralttext." ".$strvideoname))."\" title=\"".f_htmlattr(trim($stralttext." ".$strvideoname))."\" /></div>";
											$strcardtext="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_htmlspecialchars($stralttext)."</div><div class=\"search-poster-card-year\">".f_htmlspecialchars($strvideoname)."</div></div>";
											$strcell=$strcardmedia.$strcardtext;
											break;
										case "vimeo":
											break;
										default:
											break;
										}
										break;
									default:
										$strvalueclean=trim((string)$strvalue);
										if ($strkeyswitch=="BUDGET" || $strkeyswitch=="REVENUE") {
											if ($strvalueclean!="" && is_numeric($strvalueclean)) {
												$strcell="$".number_format((float)$strvalueclean,0,'.',',');
											}
										} else {
											if ($strkeyswitch=="RUNTIME") {
												if ($strvalueclean!="" && is_numeric($strvalueclean)) {
													$lngmins=(int)$strvalueclean;
													$lnghours=(int)floor($lngmins/60);
													$lngrest=(int)($lngmins%60);
													if ($lnghours>0) {
														$strcell=$lnghours."h ".str_pad((string)$lngrest,2,"0",STR_PAD_LEFT)."m";
													} else {
														$strcell=$lngrest."m";
													}
												}
											} else {
												if ($strkeyswitch=="VOTE_COUNT") {
													if ($strvalueclean!="" && is_numeric($strvalueclean)) {
														$strcell=number_format((float)$strvalueclean,0,'.',',');
													}
												} else {
													if ($strkeyswitch=="VOTE_AVERAGE" || $strkeyswitch=="IMDB_RATING") {
														if ($strvalueclean!="" && is_numeric($strvalueclean)) {
															$strcell=number_format((float)$strvalueclean,1,'.','')."/10";
														}
													} else {
														if ($strkeyswitch=="DAT_RELEASE") {
															$lngtime=@strtotime($strvalueclean);
															if ($strvalueclean!="" && $lngtime!==false) {
																$strcell=date("M j, Y",$lngtime);
															}
														} else {
															if (substr($strkeyswitch,0,3)=="IS_") {
																if ($strvalueclean!="") {
																	$inttrue=($strvalueclean=="1" || strtolower($strvalueclean)=="true" || strtolower($strvalueclean)=="yes");
																	$intfalse=($strvalueclean=="0" || strtolower($strvalueclean)=="false" || strtolower($strvalueclean)=="no");
																	if ($inttrue) {
																		$strcell=get_movie_string('lib_t2s_true');
																	} else {
																		if ($intfalse) {
																			$strcell=get_movie_string('lib_t2s_false');
																		}
																	}
																}
															} else {
																if ($strkeyswitch=="ID_CRITERION_SPINE") {
																	if ($strvalueclean!="") {
																		$strcell="Spine #".f_htmlspecialchars($strvalueclean);
																	}
																}
															}
														}
													}
												}
											}
										}
										break;
									}
									if ($strcardcontent=="") {
										if ($strcellurl!="") {
											$strcardcontent.="<a class=\"search-poster-card-link\" href=\"".f_htmlattr($strcellurl)."\" target=\"_blank\" rel=\"noopener noreferrer\">";
										} else {
											if ($strrowurl!="") {
												$strcardcontent.="<a class=\"search-poster-card-link\" href=\"".f_htmlattr($strrowurl)."\">";
											}
										}
									}
									if ($strcardmedia!="" || $strcardtext!="") {
												if ($strcardmedia!="" && strpos($strcardcontent, "search-poster-card-media")===false) {
													$strcardmedia=f_cardmediawikifit($strcardmedia);
													if ($strcardreveal!="" && strpos($strcardcontent, "search-poster-card-reveal")===false) {
														$strcardmedia=substr($strcardmedia, 0, -6).$strcardreveal."</div>";
													}
													$strcardcontent.=$strcardmedia;
												}
												if ($strcardtext!="") {
													$strcardcontent.=$strcardtext;
												}
									} else {
										//A meta line that shows the value column itself is hidden under
										//value-cards (t2sCardValueSource), or the value appeared twice (seen live, 2026-11-08)
										$strmetaclass=in_array((string)$strkey, array_column($arrt2svaluecolumns, "name"), true) ? " t2sCardValueSource" : "";
										$strcardcontent.="<div class=\"search-poster-card-text search-poster-card-meta".$strmetaclass."\">".f_htmlspecialchars($strcell)."</div>";
									}
									$lngcol++;
								}
							}	
							if ($strcardcontent!="") {
								//The quantity of the row, shown only under value-cards. Inside the
								//card link, under the title: the link fills the card (height 100%) and the card
								//clips its overflow, so a value placed after the link was cut off (seen live,
								//2026-11-08).
								if (count($arrt2svaluecolumns)>0) {
									$strcardcontent.=f_t2srep_card_value_html($rstemprecord, $arrt2svaluecolumns, $struilang);
								}
								if ($strcellurl!="" || $strrowurl!="") {
									$strcardcontent.="</a>";
								}
								$lngt2sseqyear=($strt2sseqdatecolumn!="" && isset($rstemprecord[$strt2sseqdatecolumn])) ? f_t2srep_year($rstemprecord[$strt2sseqdatecolumn]) : null;
								echo "<div class=\"search-poster-card\"".($lngt2sseqyear!==null ? " data-t2syear=\"".(int)$lngt2sseqyear."\"" : "").">".($intt2ssequence ? f_t2srep_sequence_number_html($lngt2ssequencebase+$lngrow+1) : "").$strcardcontent."</div>";
							} else {
								$straggregatecontent=f_t2s_build_aggregate_card($rstemprecord);
								if ($straggregatecontent!="") {
									echo "<div class=\"search-poster-card\">".$straggregatecontent."</div>";
								}
							}
							$lngrow++;
						}
					}
					if ($lngrow>0) {
						echo "</div>";
					}
					if (!$intloadmore) {
						echo "<div id=\"search-poster-card-grid-loader\" class=\"muted\" style=\"display:none; margin-top:12px;\"></div>";
						echo "<button type=\"button\" id=\"search-poster-card-grid-loadmore\" class=\"responsive-input\" style=\"display:none; margin-top:12px;\"></button>";
						echo "<div id=\"search-poster-card-grid-end\" class=\"muted\" style=\"display:none; margin-top:12px;\"></div>";
						echo "<script>\n";
						echo "(function(){\n";
						echo "var grid=document.getElementById('search-poster-card-grid');\n";
						echo "var loader=document.getElementById('search-poster-card-grid-loader');\n";
						echo "var loadMoreBtn=document.getElementById('search-poster-card-grid-loadmore');\n";
						echo "var endMessage=document.getElementById('search-poster-card-grid-end');\n";
						echo "if(!grid){return;}\n";
						echo "var loading=false;\n";
						echo "var hasMore=grid.getAttribute('data-has-more')==='1';\n";
						echo "var nextPage=parseInt(grid.getAttribute('data-page')||'1',10)+1;\n";
						echo "var loadMoreLabel=".json_encode((string)get_movie_string('lib_t2s_load_more')).";\n";
						echo "var noMoreLabel=".json_encode((string)get_movie_string('lib_t2s_no_more_results')).";\n";
						echo "var loadingLabel=".json_encode((string)get_movie_string('lib_t2s_loading_more')).";\n";
						echo "var retrievedCountSuffix=".json_encode((string)get_movie_string('lib_t2s_retrieved_count_suffix')).";\n";
						echo "var preloadDistance=Math.max(window.innerHeight,900);\n";
						echo "var lastTriggerBottom=null;\n";
						// Cap auto-scroll: after this many auto-loaded pages, require a click to load more.
						// Prevents runaway loading for broad queries like "movies".
						echo "var maxAutoPages=4;\n";
						echo "var autoPagesLoaded=0;\n";
						echo "var baseUrl=new URL(window.location.href);\n";
						echo "baseUrl.searchParams.set('render','card');\n";
						echo "baseUrl.searchParams.set('loadmore','1');\n";
						echo "function getLoadedCount(){return grid.querySelectorAll('.search-poster-card').length;}\n";
						echo "function formatLabel(base){return base+' ('+getLoadedCount()+retrievedCountSuffix+')';}\n";
						echo "function refreshStatusText(){if(loader){loader.textContent=formatLabel(loadingLabel);}if(loadMoreBtn){loadMoreBtn.textContent=formatLabel(loadMoreLabel);}if(endMessage){endMessage.textContent=formatLabel(noMoreLabel);}}\n";
						echo "function setLoading(on){refreshStatusText();if(loader){loader.style.display=on?'block':'none';}}\n";
						echo "function setEndMessage(on){refreshStatusText();if(endMessage){endMessage.style.display=on?'block':'none';}}\n";
						echo "function setLoadMoreButton(on){refreshStatusText();if(loadMoreBtn){loadMoreBtn.style.display=on?'inline-block':'none';}}\n";
						// Under sequence-strip the grid is one horizontal row that scrolls inside its frame:
						// the next page is due when its right end comes near, and its bottom never moves.
						echo "function isStrip(){return !!(grid.closest&&grid.closest('.t2sShowSequence'));}\n";
						echo "function getRemainingDistance(){if(isStrip()){return grid.scrollWidth-grid.scrollLeft-grid.clientWidth;}var rect=grid.getBoundingClientRect();return rect.bottom-window.innerHeight;}\n";
						// The grid can sit in a hidden view (another chip is active); a hidden
						// grid measures 0 and would load page after page, so it waits until it is shown.
						echo "function shouldAutoLoad(){if(!hasMore||loading){return false;}if(grid.offsetParent===null){return false;}if(autoPagesLoaded>=maxAutoPages){return false;}return getRemainingDistance()<(isStrip()?Math.max(grid.clientWidth,600):preloadDistance);}\n";
						echo "function loadNext(isAuto){if(!hasMore||loading){return;}loading=true;setEndMessage(false);setLoadMoreButton(false);lastTriggerBottom=grid.getBoundingClientRect().bottom;setLoading(true);baseUrl.searchParams.set('page',String(nextPage));fetch(baseUrl.toString(),{credentials:'same-origin'}).then(function(r){return r.text();}).then(function(html){var wrap=document.createElement('div');wrap.innerHTML=html;var cards=wrap.querySelectorAll('.search-poster-card');if(!cards.length){hasMore=false;grid.setAttribute('data-has-more','0');refreshStatusText();setEndMessage(true);return;}cards.forEach(function(card){grid.appendChild(card);});refreshStatusText();var nextGrid=wrap.querySelector('.search-poster-card-grid');if(nextGrid&&nextGrid.getAttribute('data-has-more')==='0'){hasMore=false;grid.setAttribute('data-has-more','0');setEndMessage(true);}else if(cards.length<50){hasMore=false;grid.setAttribute('data-has-more','0');setEndMessage(true);}nextPage+=1;if(isAuto){autoPagesLoaded+=1;}}).catch(function(){hasMore=false;grid.setAttribute('data-has-more','0');refreshStatusText();setEndMessage(true);}).finally(function(){loading=false;setLoading(false);if(hasMore&&autoPagesLoaded>=maxAutoPages){setLoadMoreButton(true);}if(window.f_t2srepcaptionupdate){window.f_t2srepcaptionupdate('sequence-strip',hasMore);}});}\n";
						echo "function maybeLoadNext(){if(!shouldAutoLoad()){if(hasMore&&!loading&&autoPagesLoaded>=maxAutoPages){setLoadMoreButton(true);}return;}var currentBottom=grid.getBoundingClientRect().bottom;if(!isStrip()&&lastTriggerBottom!==null&&Math.abs(currentBottom-lastTriggerBottom)<120){return;}loadNext(true);}\n";
						echo "if(loadMoreBtn){loadMoreBtn.addEventListener('click',function(){autoPagesLoaded=0;loadNext(false);});}\n";
						echo "window.addEventListener('scroll',maybeLoadNext,{passive:true});\n";
						echo "grid.addEventListener('scroll',maybeLoadNext,{passive:true});\n";
						echo "window.addEventListener('resize',function(){preloadDistance=Math.max(window.innerHeight,900);maybeLoadNext();},{passive:true});\n";
						echo "refreshStatusText();\n";
						echo "if(!hasMore){setEndMessage(true);}\n";
						echo "maybeLoadNext();\n";
						echo "})();\n";
						echo "</script>";
					}
					echo "</div>";
				} else {
					if (!$intloadmore) {
						echo "<div class=\"search-poster-results\"><div class=\"muted\" style=\"margin-top:12px;\">".get_movie_string('lib_t2s_no_results')."</div></div>";
					}
				}
				if (!$intloadmore && $intt2sviews) {
					//Closes the card view opened above
					echo "</div>";
				}
				break;
			}
			if (!$intloadmore && $strresultkeycolumn!="" && count($arrresultkeyvalues)>0) {
				//Hook search.result.after: what the plugins do with the key column of the result and the
				//identifiers collected while it was rendered
				echo f_pluginhtml("search.result.after",array("keycolumn"=>$strresultkeycolumn,"keyvalues"=>array_values(array_unique($arrresultkeyvalues))));
			}
			if (!$intloadmore && $strrender!="card") {
				echo "<h1>";
				if ($lngoffset>0) {
					$lngpageprev=$lngpage-1;
					$strurlprev="?questionhashed=".$strquestionhashed."&search=".urlencode($strsearch).$strimagerefurlpart."&page=".$lngpageprev;
					echo "<a href=".$strurlprev.">&lt;&lt;</a> ";
				}
				echo get_movie_string('lib_t2s_query_result').$lngrowcount.get_movie_string('lib_t2s_rows');
				if ($lngrowcount==$lngrowsperpage) {
					$lngpagenext=$lngpage+1;
					$strurlnext="?questionhashed=".$strquestionhashed."&search=".urlencode($strsearch).$strimagerefurlpart."&page=".$lngpagenext;
					echo " <a href=".$strurlnext.">&gt;&gt;</a>";
				}
				echo "</h1>";
			}
		} else {
			// Handle case where no results or error in API response
			if (isset($jsontext2sql['result'][0]['error'])) {
				if (!$intloadmore) {
					echo "<h1>".get_movie_string('lib_t2s_query_error')."</h1>";
					echo "<p>".get_movie_string('lib_t2s_error') . htmlspecialchars($jsontext2sql['result'][0]['error']) . "</p>";
				}
			} else {
				if (!$intloadmore) {
					echo "<h1>".get_movie_string('lib_t2s_no_results')."</h1>";
				}
			}
		}
		
		//echo "<h1>Query result: ".$lngrow." row(s)</h1>";
		
		if (!$intloadmore && isset($interror)) {
			if ($interror) {
				$strmessage=$jsontext2sql['status_message'];
				f_headerdisplay($strmessage);
				echo "<h1>".$strmessage."</h1>";
			}
		}
	} else {
		//search URL parameter not set
		/*
		$strmessage="movieid URL parameter not set";
		f_headerdisplay($strmessage);
		echo "<h1>".$strmessage."</h1>";
		*/
	}
	if (!$intloadmore) {
		//Hook page.beforefooter: blocks of the plugins at the end of the page
		echo f_pluginhtml("page.beforefooter",array("page"=>"text2sql-search"));
	}
	if (!$intloadmore) {
		$strsearchscope="all";
		include(__DIR__."/text2sql-samples.inc.php");
		f_footerdisplay();
	}
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
