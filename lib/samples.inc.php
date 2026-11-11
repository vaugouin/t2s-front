<?php
//Every showcase sample of the API, one row each: the question, then its cards.
//The voice-agent launch showcase reads the same GET /samples?set=showcase but only shows a
//random, round-robin selection of it, and silently drops what it cannot render. This page shows
//all of it, says for each sample whether the showcase can display it, and links every card to
//its detail page. Its job is to check that a sample flagged as such in the database reaches the API.
//The card rules live in lib/samples-format.inc.php, side-effect free and tested.
include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
include_once(__DIR__."/samples-format.inc.php");

//Same colour resolution as the search page: request, then cookie, then the site default. The
//cookie is read and never written here, so looking at the other colour does not switch the search.
$strapicolor=trim(f_request2("apicolor"));
if (!f_requestisfieldset("apicolor") && isset($_COOKIE['apicolor'])) {
	$strapicolor=trim($_COOKIE['apicolor']);
}
if ($strapicolor!="Blue" && $strapicolor!="Green") {
	$strapicolor=$strtext2sqlapicolor;
}
//"showcase" is what the voice-agent reads; "sample" is the wider IS_SAMPLE set of the search page.
$strsampleset=strtolower(trim(f_request2("set")));
if ($strsampleset!="sample") {
	$strsampleset="showcase";
}
//Display order. "api" keeps the order /samples serves (category DISPLAY_ORDER, then
//sample DISPLAY_ORDER); the others are re-sorted here, the API is never asked for another order.
$strsampleorder=f_samplesorder(f_request2("order"));
$strapiversion=f_text2sqlapiversion($strapicolor);

f_headerdisplay(get_movie_string('lib_samples_title'));
echo "<h1>".get_movie_string('lib_samples_title')."</h1>";

$fnurl=function($strcolor,$strset) use ($strsampleorder) {
	$arrquery=array("apicolor"=>$strcolor,"set"=>$strset);
	if ($strsampleorder!="api") {
		$arrquery["order"]=$strsampleorder;
	}
	return "samples.php?".http_build_query($arrquery);
};
echo "<p class=\"muted\">".get_movie_string('lib_samples_intro')."</p>";
echo "<p>";
echo "API <strong>".f_htmlspecialchars($strapicolor)."</strong> ".f_htmlspecialchars($strapiversion);
echo " · <code>GET /samples?set=".f_htmlspecialchars($strsampleset)."&amp;ui_language=".f_htmlspecialchars($struilang)."</code>";
$strothercolor=($strapicolor=="Blue" ? "Green" : "Blue");
echo " · <a href=\"".f_htmlattr($fnurl($strothercolor,$strsampleset))."\">".f_htmlspecialchars(get_movie_string('lib_samples_switch_color')." ".$strothercolor)."</a>";
$strotherset=($strsampleset=="showcase" ? "sample" : "showcase");
echo " · <a href=\"".f_htmlattr($fnurl($strapicolor,$strotherset))."\">".f_htmlspecialchars(get_movie_string('lib_samples_set_'.$strotherset))."</a>";
echo "</p>";
//A GET form, so the chosen order stays in a shareable URL with the colour and the set. The button
//is only there without JavaScript; with it, changing the list submits the form.
echo "<form method=\"get\" action=\"samples.php\" class=\"samples-order\">";
echo "<input type=\"hidden\" name=\"apicolor\" value=\"".f_htmlattr($strapicolor)."\" />";
echo "<input type=\"hidden\" name=\"set\" value=\"".f_htmlattr($strsampleset)."\" />";
echo "<label for=\"samplesorder\">".f_htmlspecialchars(get_movie_string('lib_samples_order'))."</label> ";
echo "<select id=\"samplesorder\" name=\"order\" onchange=\"this.form.submit();\">";
foreach (SAMPLES_ORDERS as $strorderoption) {
	echo "<option value=\"".$strorderoption."\"".($strorderoption==$strsampleorder ? " selected=\"selected\"" : "").">".f_htmlspecialchars(get_movie_string('lib_samples_order_'.$strorderoption))."</option>";
}
echo "</select>";
echo "<noscript> <input type=\"submit\" value=\"OK\" /></noscript>";
echo "</form>";

$inthttpcode=0;
$strerror="";
$arranswer=f_text2sqlsamples($strapicolor, $struilang, $strsampleset, $inthttpcode, $strerror);
if ($arranswer===null) {
	echo "<p class=\"samples-error\">".f_htmlspecialchars(get_movie_string('lib_samples_api_error')." ".$strerror)."</p>";
	f_footerdisplay();
	return;
}

$arrbases=array(
	"poster"=>$strtmdbmovieimagespathbaseurl342,
	"profile"=>$strtmdbpersonimagespathbaseurl185,
	"logo"=>$strtmdbimagespathbaseurl,
);
//Same preference as the search cards and the voice-agent: the padded 2:3 master of a company or
//a network when one is validated, the raw logo otherwise. Needs the database, hence the callable.
$fnsynthetic=null;
if (!empty($intdbavailable)) {
	$fnsynthetic=function($stritemclass,$lngid) {
		return f_getsyntheticimagepath($stritemclass,$lngid);
	};
}

//Hook samples.row.actions: links added by the plugins beside a sample (private mode only, since
//plugins load only there)
$fneditlink=function($lngid) {
	if ($lngid<=0) {
		return "";
	}
	return f_pluginhtml("samples.row.actions",array("id"=>(int)$lngid));
};

//First pass: the cards and the showcase status of every sample, computed before sorting since the
//"status" order reads them. The second pass only renders.
$arrflat=f_samplesflatten($arranswer["categories"] ?? array());
foreach ($arrflat as $lngindex=>$arritem) {
	$arrsample=$arritem["sample"];
	$arrsim=(isset($arrsample["simulated_result"]) && is_array($arrsample["simulated_result"])) ? $arrsample["simulated_result"] : array();
	$arrrows=(isset($arrsim["result"]) && is_array($arrsim["result"])) ? $arrsim["result"] : array();
	$strresultkind=(string)($arrsim["result_kind"] ?? "");

	$arrspecs=array();
	if ($strresultkind=="entity_rows") {
		foreach ($arrrows as $arrrow) {
			$arrdata=(is_array($arrrow) && isset($arrrow["data"])) ? $arrrow["data"] : $arrrow;
			$arrspecs[]=array("spec"=>f_samplescardspec($arrdata,$arrbases,$fnsynthetic), "data"=>$arrdata);
		}
	}
	$lngshowcase=f_samplesshowcasecount(array_column($arrspecs,"spec"));
	$arrflat[$lngindex]+=array(
		"sim"=>$arrsim, "rows"=>$arrrows, "resultkind"=>$strresultkind, "specs"=>$arrspecs,
		"showcase"=>$lngshowcase, "status"=>f_samplesstatus($arrsample,$lngshowcase),
	);
}
$arrflat=f_samplessort($arrflat, $strsampleorder);

$arrstatuscount=array("shown"=>0,"noassert"=>0,"nopreview"=>0,"empty"=>0,"noimage"=>0);
$strrows="";
//Samples flagged for the showcase but saved without an assertion: listed at the top with a link
//to their row, since finishing them is what the page is most often opened for.
$arrincomplete=array();
foreach ($arrflat as $arritem) {
	$arrsample=$arritem["sample"];
	$arrsim=$arritem["sim"];
	$arrrows=$arritem["rows"];
	$strresultkind=$arritem["resultkind"];
	$arrspecs=$arritem["specs"];
	$lngshowcase=$arritem["showcase"];
	$strstatus=$arritem["status"];
	$arrstatuscount[$strstatus]++;

	$strquestion=trim((string)($arrsample["QUESTION"] ?? ""));
	$lngid=(int)($arrsample["ID_T2S_EVALUATION"] ?? 0);
	if ($strstatus=="noassert") {
		$arrincomplete[]=array("id"=>$lngid,"question"=>$strquestion);
	}
	$strrows.="<section class=\"samples-row samples-status-".$strstatus."\" id=\"sample-".$lngid."\">";
	$strrows.="<div class=\"samples-path\">".f_htmlspecialchars(implode(" › ",array_filter($arritem["path"],"strlen")))." · #".$lngid.$fneditlink($lngid)."</div>";
	//The question runs the real search, so the simulated cards can be compared with a live answer.
	$strrows.="<div class=\"samples-question\"><a href=\"".f_htmlattr("index.php?search=".rawurlencode($strquestion))."\">".nl2br(f_htmlspecialchars($strquestion))."</a></div>";
	$strrows.="<div class=\"samples-badge\">".f_htmlspecialchars(get_movie_string('lib_samples_status_'.$strstatus));
	if ($strresultkind=="entity_rows") {
		$strrows.=" · ".f_htmlspecialchars(sprintf(get_movie_string('lib_samples_cards_count'),count($arrrows),$lngshowcase));
	}
	$strrows.="</div>";

	if ($strresultkind=="scalar") {
		$strrows.="<div class=\"samples-cards\"><div class=\"samples-scalar\">".f_htmlspecialchars(f_samplesscalarvalue($arrsample))."</div></div>";
	} elseif (count($arrspecs)>0) {
		$strrows.="<div class=\"samples-cards\">";
		$lngposition=0;
		foreach ($arrspecs as $arrentry) {
			if ($arrentry["spec"]===null) {
				//A row naming no entity this page knows: shown raw, so a new entity type is noticed.
				$strrows.="<div class=\"samples-card samples-card-out samples-card-unknown\"><code>".f_htmlspecialchars(json_encode($arrentry["data"],JSON_UNESCAPED_UNICODE))."</code></div>";
			} else {
				$intinshowcase=($lngposition<SAMPLES_SHOWCASE_MAX_CARDS && $arrentry["spec"]["image"]!="");
				$strrows.=f_samplescardhtml($arrentry["spec"],$intinshowcase,get_movie_string('lib_samples_no_image'));
			}
			$lngposition++;
		}
		$strrows.="</div>";
	} else {
		//count / bound / no assertion: nothing to draw, so say what the API holds instead.
		$strdetail=isset($arrsim["expectation"]) ? json_encode($arrsim["expectation"],JSON_UNESCAPED_UNICODE) : (string)($arrsample["assertion"]["raw"] ?? "");
		if ($strdetail!="") {
			$strrows.="<div class=\"samples-detail\"><code>".f_htmlspecialchars($strdetail)."</code></div>";
		}
	}
	$strrows.="</section>";
}

echo "<p class=\"samples-summary\">".f_htmlspecialchars(sprintf(get_movie_string('lib_samples_summary'),count($arrflat),$arrstatuscount["shown"]));
foreach (array("nopreview","empty","noimage") as $strstatus) {
	if ($arrstatuscount[$strstatus]>0) {
		echo " · ".f_htmlspecialchars(get_movie_string('lib_samples_status_'.$strstatus)).": ".$arrstatuscount[$strstatus];
	}
}
echo "</p>";
if (count($arrincomplete)>0) {
	echo "<div class=\"samples-incomplete-list\"><strong>".f_htmlspecialchars(get_movie_string('lib_samples_incomplete_list'))." (".count($arrincomplete).")</strong><ul>";
	foreach ($arrincomplete as $arrentry) {
		echo "<li><a href=\"#sample-".$arrentry["id"]."\">#".$arrentry["id"]."</a>".$fneditlink($arrentry["id"])." ".f_htmlspecialchars($arrentry["question"])."</li>";
	}
	echo "</ul></div>";
}
if (count($arrflat)==0) {
	echo "<p>".get_movie_string('lib_samples_none')."</p>";
}
echo $strrows;

f_footerdisplay();
?>
