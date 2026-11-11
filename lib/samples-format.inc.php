<?php
/*
 * samples-format.inc.php - pure helpers of samples.php
 *
 * samples.php reads the API's /samples endpoint, the same one the voice-agent launch showcase
 * reads, and shows EVERY sample as a row: its question, then its cards. Its purpose is to check
 * that a showcase sample created in the database does reach that endpoint, and what the
 * voice-agent will make of it.
 *
 * Side-effect free on purpose, so test/samples-format-test.php can load it alone: no database,
 * no global, no echo. The one lookup that needs the database, the synthetic 2:3 master of a
 * company or a network, is handed in as a callable by the page.
 *
 * The card rules mirror cardSpecFromRecord() and buildShowcaseGroup() in voice-agent
 * (app/static/app.js). Keep them aligned: the page is only useful if it predicts what the
 * showcase does. Two rules of the showcase in particular are counted here rather than applied,
 * so that the page can say how a sample will look there instead of hiding what it would drop:
 * a card without an image is skipped, and a group stops at 8 cards.
 */

//Mirrors buildShowcaseGroup(): sample.sim.result.slice(0, 8)
define('SAMPLES_SHOWCASE_MAX_CARDS', 8);

function f_samplesflatten($arrcategories, $arrpath=array()) {
	//Purpose: Walks the /samples category tree into a flat, ordered list of samples
	//Params:	$arrcategories: The "categories" array of the /samples answer (decoded JSON)
	//			$arrpath: Category descriptions above this level (recursion only)
	//Returns: List of array("path"=>array of category descriptions, "sample"=>sample node),
	//			in the API's order (categories by DISPLAY_ORDER, a category's own samples before
	//			its sub-categories, which is the order flattenSampleTree() walks in voice-agent)
	$arrresult=array();
	if (!is_array($arrcategories)) {
		return $arrresult;
	}
	foreach ($arrcategories as $arrcategory) {
		if (!is_array($arrcategory)) {
			continue;
		}
		$arrcurrentpath=$arrpath;
		//Category descriptions are stored HTML-encoded ("Budget &amp; Revenue Queries", and
		//text2sql-samples.inc.php echoes them raw), while the API decodes only the questions.
		//Decoded here so that the page's single escaping does not print "&amp;amp;".
		$arrcurrentpath[]=trim(html_entity_decode((string)($arrcategory["DESCRIPTION"] ?? ""), ENT_QUOTES|ENT_HTML5, 'UTF-8'));
		if (isset($arrcategory["samples"]) && is_array($arrcategory["samples"])) {
			foreach ($arrcategory["samples"] as $arrsample) {
				if (is_array($arrsample)) {
					$arrresult[]=array("path"=>$arrcurrentpath, "sample"=>$arrsample);
				}
			}
		}
		if (isset($arrcategory["categories"]) && is_array($arrcategory["categories"])) {
			$arrresult=array_merge($arrresult, f_samplesflatten($arrcategory["categories"], $arrcurrentpath));
		}
	}
	return $arrresult;
}

//The display orders of the page, in the order of its drop-down list. "api" first,
//it is the default.
const SAMPLES_ORDERS=array("api","idasc","iddesc","status","question");
//"status" order: what needs fixing first. Incomplete (to be fixed at the source), then the samples
//the showcase never shows, worst data problem first, then the ones it shows.
const SAMPLES_STATUS_RANK=array("noassert"=>0,"empty"=>1,"noimage"=>2,"nopreview"=>3,"shown"=>4);

function f_samplesorder($strorder) {
	//Purpose: Whitelists the display order requested by the page's drop-down list
	//Params:	$strorder: Raw "order" request value
	//Returns: One of SAMPLES_ORDERS, "api" (the order /samples serves) for anything else
	$strorder=strtolower(trim((string)$strorder));
	return in_array($strorder, SAMPLES_ORDERS, true) ? $strorder : "api";
}

function f_samplesquestionkey($strquestion) {
	//Purpose: Sort key of a question for the "question" order: case and accents ignored
	//Params:	$strquestion: The question as displayed
	//Returns: Lowercased, accent-free string. Accents are stripped through the intl extension's
	//			Normalizer; without intl the key is only lowercased, so "École" then sorts after "z"
	$strkey=mb_strtolower(trim((string)$strquestion), 'UTF-8');
	if (class_exists('Normalizer')) {
		$strnormalized=Normalizer::normalize($strkey, Normalizer::FORM_D);
		if ($strnormalized!==false) {
			$strkey=preg_replace('/\p{Mn}+/u', '', $strnormalized);
		}
	}
	return $strkey;
}

function f_samplessort($arrflat, $strorder) {
	//Purpose: Reorders the flat list of f_samplesflatten() for display
	//Params:	$arrflat: Output of f_samplesflatten(); for the "status" order each entry must also
	//			carry the "status" key of f_samplesstatus(), which the page adds before sorting
	//			$strorder: Output of f_samplesorder()
	//Returns: The same entries, each with its category path: untouched for "api"; by
	//			ID_T2S_EVALUATION ascending (creation order) for "idasc", descending for "iddesc";
	//			by SAMPLES_STATUS_RANK for "status"; by question, case and accents ignored, for
	//			"question". usort is stable since PHP 8.0, so ties keep the API's order.
	$fnid=function($arritem) {
		return (int)($arritem["sample"]["ID_T2S_EVALUATION"] ?? 0);
	};
	switch ($strorder) {
	case "idasc":
		usort($arrflat, function($arra, $arrb) use ($fnid) { return $fnid($arra) <=> $fnid($arrb); });
		break;
	case "iddesc":
		usort($arrflat, function($arra, $arrb) use ($fnid) { return $fnid($arrb) <=> $fnid($arra); });
		break;
	case "status":
		//An unknown or missing status sorts last rather than first: it is not a known problem.
		$fnrank=function($arritem) {
			return SAMPLES_STATUS_RANK[$arritem["status"] ?? ""] ?? count(SAMPLES_STATUS_RANK);
		};
		usort($arrflat, function($arra, $arrb) use ($fnrank) { return $fnrank($arra) <=> $fnrank($arrb); });
		break;
	case "question":
		foreach ($arrflat as $lngindex=>$arritem) {
			$arrflat[$lngindex]["_questionkey"]=f_samplesquestionkey($arritem["sample"]["QUESTION"] ?? "");
		}
		usort($arrflat, function($arra, $arrb) { return strcmp($arra["_questionkey"], $arrb["_questionkey"]); });
		foreach ($arrflat as $lngindex=>$arritem) {
			unset($arrflat[$lngindex]["_questionkey"]);
		}
		break;
	}
	return $arrflat;
}

function f_samplesimageurl($strpath, $strtmdbbase) {
	//Purpose: Turns an image path of a /samples row into a URL, like tmdbImage() / imageUrl() in voice-agent
	//Params:	$strpath: POSTER_PATH, PROFILE_PATH, LOGO_PATH or WIKIPEDIA_IMAGE_PATH as returned
	//			$strtmdbbase: TMDb base URL with its size, without trailing slash (".../t/p/w342")
	//Returns: Absolute URL, or "" when there is no image
	$strvalue=trim((string)$strpath);
	if ($strvalue=="") {
		return "";
	}
	if (substr($strvalue,0,2)=="//") {
		return "https:".$strvalue;
	}
	if (preg_match('#^https?://#i', $strvalue)) {
		return $strvalue;
	}
	return $strtmdbbase.(substr($strvalue,0,1)=="/" ? "" : "/").$strvalue;
}

function f_samplesyear($strdate) {
	//Purpose: Year of a date as the API returns it ("1999-03-31", "1999-03-31T00:00:00", "")
	//Returns: Four-digit year or ""
	$strvalue=trim((string)$strdate);
	return preg_match('/^(\d{4})/', $strvalue, $arrmatch) ? $arrmatch[1] : "";
}

function f_samplescardspec($arrrecord, $arrbases, $fnsynthetic=null) {
	//Purpose: Describes the card of one /samples row: what it shows and where it leads
	//Params:	$arrrecord: The "data" of one row of simulated_result.result
	//			$arrbases: TMDb base URLs, keys "poster" (w342), "profile" (w185), "logo" (w342)
	//			$fnsynthetic: Optional callable($stritemclass, $lngid) returning the URL of a
	//				synthetic 2:3 master, or "" (companies and networks only, as in voice-agent)
	//Returns: array("kind","id","title","subtitle","url","image"), or null when the row names no
	//			entity this page knows (it is then reported, never silently dropped)
	if (!is_array($arrrecord)) {
		return null;
	}
	$fnvalue=function($strkey) use ($arrrecord) {
		return isset($arrrecord[$strkey]) && $arrrecord[$strkey]!==null ? trim((string)$arrrecord[$strkey]) : "";
	};
	$strmediatype=strtolower($fnvalue("MEDIA_TYPE"));

	if ($fnvalue("ID_MOVIE")!="" || $strmediatype=="movie") {
		$strid=$fnvalue("ID_MOVIE");
		return array(
			"kind"=>"movie",
			"id"=>$strid,
			"title"=>$fnvalue("MOVIE_TITLE")!="" ? $fnvalue("MOVIE_TITLE") : "Movie ".$strid,
			//A mixed movie+series union aliases the movie date as DAT_FIRST_AIR
			"subtitle"=>f_samplesyear($fnvalue("DAT_RELEASE")!="" ? $fnvalue("DAT_RELEASE") : $fnvalue("DAT_FIRST_AIR")),
			"url"=>$strid!="" ? "movie.php?movieid=".rawurlencode($strid) : "",
			"image"=>f_samplesimageurl($fnvalue("POSTER_PATH"), $arrbases["poster"]),
		);
	}
	if ($fnvalue("ID_SERIE")!="" || $strmediatype=="serie") {
		$strid=$fnvalue("ID_SERIE");
		$strfirst=f_samplesyear($fnvalue("DAT_FIRST_AIR"));
		$strlast=f_samplesyear($fnvalue("DAT_LAST_AIR"));
		return array(
			"kind"=>"serie",
			"id"=>$strid,
			"title"=>$fnvalue("SERIE_TITLE")!="" ? $fnvalue("SERIE_TITLE") : "Series ".$strid,
			"subtitle"=>($strfirst!="" || $strlast!="") ? $strfirst."-".$strlast : "",
			"url"=>$strid!="" ? "serie.php?serieid=".rawurlencode($strid) : "",
			"image"=>f_samplesimageurl($fnvalue("POSTER_PATH"), $arrbases["poster"]),
		);
	}
	if ($fnvalue("ID_PERSON")!="") {
		$strid=$fnvalue("ID_PERSON");
		return array(
			"kind"=>"person",
			"id"=>$strid,
			"title"=>$fnvalue("PERSON_NAME")!="" ? $fnvalue("PERSON_NAME") : "Person ".$strid,
			"subtitle"=>$fnvalue("KNOWN_FOR_DEPARTMENT"),
			"url"=>"person.php?personid=".rawurlencode($strid),
			"image"=>f_samplesimageurl($fnvalue("PROFILE_PATH"), $arrbases["profile"]),
		);
	}
	if ($fnvalue("ID_COMPANY")!="" || $fnvalue("ID_NETWORK")!="") {
		$intnetwork=($fnvalue("ID_NETWORK")!="");
		$strkind=$intnetwork ? "network" : "company";
		$strid=$intnetwork ? $fnvalue("ID_NETWORK") : $fnvalue("ID_COMPANY");
		$strname=$intnetwork ? $fnvalue("NETWORK_NAME") : $fnvalue("COMPANY_NAME");
		$strimage="";
		if (is_callable($fnsynthetic)) {
			$strimage=(string)call_user_func($fnsynthetic, $strkind, (int)$strid);
		}
		if ($strimage=="") {
			$strimage=f_samplesimageurl($fnvalue("LOGO_PATH"), $arrbases["logo"]);
		}
		return array(
			"kind"=>$strkind,
			"id"=>$strid,
			"title"=>$strname!="" ? $strname : ($intnetwork ? "Network " : "Company ").$strid,
			"subtitle"=>"",
			"url"=>$strkind.".php?".$strkind."id=".rawurlencode($strid),
			"image"=>$strimage,
		);
	}
	//The secondary entities: one id column, one name column, one detail page each. Same
	//table as the grid renderer of lib/text2sql-search.inc.php; the name columns are the ones
	//SAMPLE_HYDRATION selects in fastapi-text2sql (main.py).
	$arrsecondary=array(
		array("ID_TOPIC","TOPIC_NAME","topic","t2stopic.php?topicid="),
		array("ID_T2S_LIST","LIST_NAME","list","t2slist.php?listid="),
		array("ID_T2S_COLLECTION","COLLECTION_NAME","collection","t2scollection.php?collectionid="),
		array("ID_LOCATION","LOCATION_NAME","location","location.php?locationid="),
		array("ID_MOVEMENT","MOVEMENT_NAME","movement","movement.php?movementid="),
		array("ID_AWARD","AWARD_NAME","award","award.php?awardid="),
		array("ID_NOMINATION","NOMINATION_NAME","nomination","nomination.php?nominationid="),
		array("ID_GROUP","GROUP_NAME","group","group.php?groupid="),
		array("ID_DEATH","DEATH_NAME","death","death.php?deathid="),
		array("ID_TECHNICAL","DESCRIPTION","technical","technical.php?technicalid="),
	);
	foreach ($arrsecondary as $arrentity) {
		list($stridcolumn,$strnamecolumn,$strkind,$strurlprefix)=$arrentity;
		$strid=$fnvalue($stridcolumn);
		if ($strid=="") {
			continue;
		}
		$strimage=f_samplesimageurl($fnvalue("POSTER_PATH"), $arrbases["poster"]);
		if ($strimage=="") {
			$strimage=f_samplesimageurl($fnvalue("WIKIPEDIA_IMAGE_PATH"), $arrbases["poster"]);
		}
		return array(
			"kind"=>$strkind,
			"id"=>$strid,
			"title"=>$fnvalue($strnamecolumn)!="" ? $fnvalue($strnamecolumn) : ucfirst($strkind)." ".$strid,
			"subtitle"=>"",
			"url"=>$strurlprefix.rawurlencode($strid),
			"image"=>$strimage,
		);
	}
	return null;
}

function f_samplesshowcasecount($arrspecs) {
	//Purpose: How many cards the voice-agent showcase would render for this sample
	//Params:	$arrspecs: Card specs of the sample, in row order (null for an unknown row)
	//Returns: Count, after skipping image-less cards and stopping at SAMPLES_SHOWCASE_MAX_CARDS
	//Note: buildShowcaseGroup() slices the first 8 ROWS and only then skips the image-less
	//		ones, so an image-less card among the first eight is a lost slot, not a deferred one.
	$lngcount=0;
	foreach (array_slice($arrspecs, 0, SAMPLES_SHOWCASE_MAX_CARDS) as $arrspec) {
		if (is_array($arrspec) && $arrspec["image"]!="") {
			$lngcount++;
		}
	}
	return $lngcount;
}

function f_samplesstatus($arrsample, $lngshowcasecards) {
	//Purpose: Classifies a sample by what the voice-agent showcase will do with it
	//Params:	$arrsample: One sample node of /samples
	//			$lngshowcasecards: f_samplesshowcasecount() of its cards
	//Returns: "shown"      the showcase can pick it, with cards or a scalar value
	//			"noassert"   no assertion, so no preview: never shown
	//			"nopreview"  count / bound / unparsed assertion: no rows to show, never shown
	//			"empty"      entity rows expected but none hydrated (deleted or unknown ids)
	//			"noimage"    rows exist but none of the first eight has an image: never shown
	$arrsim=(isset($arrsample["simulated_result"]) && is_array($arrsample["simulated_result"])) ? $arrsample["simulated_result"] : null;
	if (!isset($arrsample["assertion"]) || !is_array($arrsample["assertion"])) {
		return "noassert";
	}
	if ($arrsim===null) {
		return "nopreview";
	}
	$strkind=(string)($arrsim["result_kind"] ?? "");
	$arrrows=(isset($arrsim["result"]) && is_array($arrsim["result"])) ? $arrsim["result"] : array();
	if ($strkind=="scalar") {
		return count($arrrows)>0 ? "shown" : "nopreview";
	}
	if ($strkind!="entity_rows") {
		return "nopreview";
	}
	if (count($arrrows)==0) {
		return "empty";
	}
	return $lngshowcasecards>0 ? "shown" : "noimage";
}

function f_samplesscalarvalue($arrsample) {
	//Purpose: The single value of a scalar sample, as the showcase prints it (first cell of row 0)
	//Returns: String, "" when there is none
	$arrrows=$arrsample["simulated_result"]["result"] ?? array();
	if (!is_array($arrrows) || count($arrrows)==0) {
		return "";
	}
	$arrdata=$arrrows[0]["data"] ?? $arrrows[0];
	if (!is_array($arrdata) || count($arrdata)==0) {
		return "";
	}
	$varvalue=reset($arrdata);
	if (is_bool($varvalue)) {
		return $varvalue ? "true" : "false";
	}
	return is_scalar($varvalue) ? (string)$varvalue : json_encode($varvalue, JSON_UNESCAPED_UNICODE);
}

function f_samplescardhtml($arrspec, $intinshowcase, $strnoimagelabel) {
	//Purpose: One poster card of a sample row, linked to the entity's detail page
	//Params:	$arrspec: f_samplescardspec() result (never null here)
	//			$intinshowcase: true when the voice-agent showcase would render this card too
	//			$strnoimagelabel: Text shown in place of a missing image
	//Returns: HTML, every value escaped
	$fnesc=function($strvalue) {
		return htmlspecialchars((string)$strvalue, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
	};
	$strtitle=$arrspec["title"].($arrspec["subtitle"]!="" ? " (".$arrspec["subtitle"].")" : "");
	$strclass="samples-card".($intinshowcase ? "" : " samples-card-out");
	$strhtml="<div class=\"".$strclass."\" title=\"".$fnesc($strtitle)."\">";
	if ($arrspec["url"]!="") {
		$strhtml.="<a href=\"".$fnesc($arrspec["url"])."\">";
	}
	if ($arrspec["image"]!="") {
		$strhtml.="<div class=\"samples-card-media\"><img ".(preg_match('#^https?://[^/]*(wikimedia|wikipedia)\.org/#i',(string)$arrspec["image"]) ? "onload=\"f_wikifit(this)\" " : "")."src=\"".$fnesc($arrspec["image"])."\" alt=\"".$fnesc($strtitle)."\" loading=\"lazy\" decoding=\"async\" /></div>";
	} else {
		$strhtml.="<div class=\"samples-card-media samples-card-noimage\"><span>".$fnesc($strnoimagelabel)."</span></div>";
	}
	$strhtml.="<div class=\"samples-card-title\">".$fnesc($arrspec["title"])."</div>";
	$strhtml.="<div class=\"samples-card-sub\">".$fnesc($arrspec["subtitle"]!="" ? $arrspec["subtitle"] : $arrspec["kind"])."</div>";
	if ($arrspec["url"]!="") {
		$strhtml.="</a>";
	}
	$strhtml.="</div>";
	return $strhtml;
}
?>
