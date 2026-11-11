<?php
/*
 * t2s-representation.inc.php - result representations of the text2sql search
 *
 * The API adds three fields to every /search/text2sql result:
 * `representation` (entity-page | card-grid | number-card | stat-strip | ranked-bars | table),
 * `result_kind` and `result_columns` (one {name, role, unit} per column). The search page draws
 * number-card, stat-strip, ranked-bars and table here; entity-page and card-grid keep the card
 * renderer of text2sql-search.inc.php.
 *
 * MIRROR OF voice-agent (app/static/app.js): chooseText2SqlRepresentation(), formatMetric(),
 * renderScalarCards(), renderRankedBars(), renderRawTable(). Rule set on 2026-10-31:
 * every representation ships on both fronts in the same batch, so a change to the dispatch, a
 * unit format or a fallback here is made there too, and the other way round.
 *
 * Phase 1 of the result representations
 * adds, all optional: `value-cards` (the card grid with the quantity of each row under its card),
 * `focus: true` on the column(s) carrying that quantity, `representation_options` (the valid set,
 * default first: the chips, carried by `render=`), `summary_facts` (numbers only: the caption is
 * written here, in English or French) and `intent`. Every field is read with isset(): a colour or
 * a stored sample predating these fields sends none of them, and the page then draws exactly what it drew
 * before.
 *
 * Phase 2 lot A adds `timeline` (entities on a time axis, drawn here),
 * `sequence-strip` (the card grid numbered: the card renderer, the badge from
 * f_t2srep_sequence_number_html()) and `trend-columns` (one column per year or decade, drawn
 * here), each with its caption. The entity titles of the table are links.
 *
 * Side-effect free on purpose, so test/t2s-representation-test.php loads it alone: no database,
 * no global, no echo. The one lookup that needs global-light (the poster URL of an entity row,
 * for the poster ranking) is handed in as a callable by the page.
 */

//Mirrors renderRankedBars(): a poster ranking only for a small set where every row has a poster
define('T2SREP_POSTER_RANKING_MAX', 12);

function f_t2srep_known() {
	//Purpose: The representations this front draws, in the contract's vocabulary
	return array("entity-page", "card-grid", "value-cards", "sequence-strip", "timeline", "trend-columns",
		"number-card", "stat-strip", "ranked-bars", "table");
}

function f_t2srep_module_reps() {
	//Purpose: The representations drawn by this module; the others keep the card renderer
	return array("timeline", "trend-columns", "number-card", "stat-strip", "ranked-bars", "table");
}

function f_t2srep_is_card_rep($strrep) {
	//Purpose: Tells whether a representation is drawn by the card renderer of the search page.
	//			sequence-strip is the card grid numbered: the cards already carry
	//			the year under the poster, and infinite scroll appends numbered cards to it.
	return in_array((string)$strrep, array("entity-page", "card-grid", "value-cards", "sequence-strip"), true);
}

function f_t2srep_options($jsontext2sql) {
	//Purpose: The chips: `representation_options` restricted to the known
	//			values, default first, always holding `representation`; array() with no hint
	$arroptions=array();
	if (!is_array($jsontext2sql)) {
		return $arroptions;
	}
	$strrep=isset($jsontext2sql['representation']) ? trim((string)$jsontext2sql['representation']) : "";
	if (in_array($strrep, f_t2srep_known(), true)) {
		$arroptions[]=$strrep;
	}
	if (isset($jsontext2sql['representation_options']) && is_array($jsontext2sql['representation_options'])) {
		foreach ($jsontext2sql['representation_options'] as $stroption) {
			$stroption=trim((string)$stroption);
			if (in_array($stroption, f_t2srep_known(), true) && !in_array($stroption, $arroptions, true)) {
				$arroptions[]=$stroption;
			}
		}
	}
	return $arroptions;
}

function f_t2srep_active($jsontext2sql, $strrequested="") {
	//Purpose: The representation to show: the one `render=` names when it is a valid option of
	//			this result, else the API default; "" with no (known) hint
	$arroptions=f_t2srep_options($jsontext2sql);
	$strrequested=trim((string)$strrequested);
	if ($strrequested!="" && in_array($strrequested, $arroptions, true)) {
		return $strrequested;
	}
	//A default this front does not know (a later phase) keeps the card renderer, as before
	$strrep=isset($jsontext2sql['representation']) ? trim((string)$jsontext2sql['representation']) : "";
	return in_array($strrep, f_t2srep_known(), true) ? $strrep : "";
}

function f_t2srep_h($value) {
	//Purpose: HTML-escapes a value (ENT_QUOTES, UTF-8)
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function f_t2srep_is_number($value) {
	//Purpose: Tells whether a cell is a finite number (JS Number.isFinite(Number(v)) on a non-blank value)
	if ($value===null || is_bool($value) || is_array($value)) {
		return false;
	}
	$strvalue=trim((string)$value);
	return $strvalue!=="" && is_numeric($strvalue) && is_finite((float)$strvalue);
}

function f_t2srep_records($arrrows) {
	//Purpose: Returns the data dicts of the API rows ({index, data} items, or plain dicts)
	$arrrecords=array();
	if (!is_array($arrrows)) {
		return $arrrecords;
	}
	foreach ($arrrows as $arritem) {
		if (is_array($arritem) && isset($arritem['data']) && is_array($arritem['data'])) {
			$arrrecords[]=$arritem['data'];
		} elseif (is_array($arritem)) {
			$arrrecords[]=$arritem;
		}
	}
	return $arrrecords;
}

function f_t2srep_columns($jsontext2sql) {
	//Purpose: Returns the result_columns list of the API answer, or an empty array
	if (is_array($jsontext2sql) && isset($jsontext2sql['result_columns']) && is_array($jsontext2sql['result_columns'])) {
		return $jsontext2sql['result_columns'];
	}
	return array();
}

function f_t2srep_pretty_label($strkey) {
	//Purpose: Mirrors prettyLabel(): TOTAL_RUNTIME becomes "Total Runtime"
	$strlabel=str_replace(array("(",")","*")," ",(string)$strkey);
	$strlabel=str_replace(array("_","-")," ",$strlabel);
	$strlabel=trim(preg_replace('/\s+/', ' ', $strlabel));
	if ($strlabel=="") {
		return "Value";
	}
	return ucwords(strtolower($strlabel));
}

function f_t2srep_format_number($dblvalue, $lngdecimals) {
	//Purpose: en-US grouping, like Intl.NumberFormat("en-US"); trailing zeros dropped
	$strout=number_format($dblvalue, $lngdecimals, '.', ',');
	if ($lngdecimals>0 && strpos($strout, '.')!==false) {
		$strout=rtrim(rtrim($strout, '0'), '.');
	}
	return $strout;
}

function f_t2srep_format_minutes($dblvalue) {
	//Purpose: Mirrors formatMinutes(): 1178 gives "1,178 min · 19 h 38"
	$lngtotal=(int)round($dblvalue);
	$lngh=intdiv($lngtotal, 60);
	$lngm=$lngtotal % 60;
	$strhm=$lngh>0 ? $lngh." h ".str_pad((string)$lngm, 2, "0", STR_PAD_LEFT) : $lngm." min";
	return number_format($lngtotal, 0, '.', ',')." min · ".$strhm;
}

function f_t2srep_format_metric($value, $strunit, $strlang='en') {
	//Purpose: Mirrors formatMetric(): formats a metric by its unit, never as money by default
	//Params:	$value: The cell; $strunit: usd | minutes | years | count | ratio | null
	//			$strlang: UI language (en | fr), for "years"
	if ($value===null || trim((string)$value)==="") {
		return "";
	}
	if (!f_t2srep_is_number($value)) {
		return (string)$value;
	}
	$dblvalue=(float)$value;
	switch ((string)$strunit) {
	case "usd":
		return ($dblvalue<0 ? "-$" : "$").number_format(abs(round($dblvalue)), 0, '.', ',');
	case "minutes":
		return f_t2srep_format_minutes($dblvalue);
	case "years":
		$strword=$strlang=="fr" ? ($dblvalue==1 ? "an" : "ans") : ($dblvalue==1 ? "year" : "years");
		return f_t2srep_format_number($dblvalue, 3)." ".$strword;
	case "count":
		return number_format(round($dblvalue), 0, '.', ',');
	case "ratio":
		return "×".number_format($dblvalue, fmod($dblvalue, 1.0)==0.0 ? 0 : 1, '.', '');
	default:
		//No or unknown unit: a neutral number, NEVER currency (the TOTAL_RUNTIME-in-dollars bug)
		if (fmod($dblvalue, 1.0)==0.0) {
			return number_format($dblvalue, 0, '.', ',');
		}
		return f_t2srep_format_number($dblvalue, 2);
	}
}

function f_t2srep_has_entity($arrrecord) {
	//Purpose: Tells whether a hintless record is an entity or media row the card renderer draws
	//			(stands in for cardSpecFromRecord() / detailRequestFromRecord() of voice-agent)
	foreach (array_keys($arrrecord) as $strkey) {
		$strkeyupper=strtoupper((string)$strkey);
		if (strpos($strkeyupper, "ID_")===0 || substr($strkeyupper, -5)=="_PATH" || $strkeyupper=="VIDEO_KEY") {
			return true;
		}
	}
	return false;
}

function f_t2srep_choose($jsontext2sql, $strrequested="") {
	//Purpose: Mirrors chooseText2SqlRepresentation(): the representation this module draws, or ""
	//			when the existing card renderer keeps the result (entity-page, card-grid,
	//			value-cards, unknown)
	//Params:	$jsontext2sql: The decoded /search/text2sql answer
	//			$strrequested: The `render=` override, honoured only when it is a valid option
	//Returns: number-card | stat-strip | ranked-bars | table | ""
	if (!is_array($jsontext2sql)) {
		return "";
	}
	$strrep=isset($jsontext2sql['representation']) ? trim((string)$jsontext2sql['representation']) : "";
	if ($strrep!="") {
		$strrep=f_t2srep_active($jsontext2sql, $strrequested);
		return in_array($strrep, f_t2srep_module_reps(), true) ? $strrep : "";
	}
	//No hint (an API colour predating the hint): a one-row result with no entity column and at least
	//one number is still a number card rather than a card titled by a raw column name
	$arrrecords=f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array());
	if (count($arrrecords)==1 && !f_t2srep_has_entity($arrrecords[0])) {
		foreach ($arrrecords[0] as $value) {
			if (f_t2srep_is_number($value)) {
				return "number-card";
			}
		}
	}
	return "";
}

function f_t2srep_scalar_metrics($arrrecords, $arrcolumns) {
	//Purpose: Mirrors scalarMetricsFromRows(): the metric cells of the one row to draw as cards
	$arrrecord=count($arrrecords)>0 ? $arrrecords[0] : array();
	$arrmetrics=array();
	$intmetadata=false;
	foreach ($arrcolumns as $arrcol) {
		if (is_array($arrcol) && isset($arrcol['role']) && $arrcol['role']=="metric") {
			$intmetadata=true;
			$strname=(string)$arrcol['name'];
			$value=array_key_exists($strname, $arrrecord) ? $arrrecord[$strname] : null;
			$strunit=isset($arrcol['unit']) ? $arrcol['unit'] : null;
			if (f_t2srep_format_metric($value, $strunit)!=="") {
				$arrmetrics[]=array("name"=>$strname, "unit"=>$strunit, "value"=>$value);
			}
		}
	}
	if ($intmetadata) {
		return $arrmetrics;
	}
	//Degraded (no column metadata): every numeric column, bare numbers
	foreach ($arrrecord as $strname => $value) {
		if (f_t2srep_is_number($value)) {
			$arrmetrics[]=array("name"=>(string)$strname, "unit"=>null, "value"=>$value);
		}
	}
	return $arrmetrics;
}

function f_t2srep_label_for_row($arrrecord, $arrcolumns) {
	//Purpose: Mirrors text2SqlLabelForRow(): the label column, else a title column, else the first text
	foreach ($arrcolumns as $arrcol) {
		if (is_array($arrcol) && isset($arrcol['role']) && $arrcol['role']=="label") {
			$strname=(string)$arrcol['name'];
			if (isset($arrrecord[$strname]) && trim((string)$arrrecord[$strname])!=="") {
				return (string)$arrrecord[$strname];
			}
			break;
		}
	}
	foreach (array("EPISODE_TITLE", "SEASON_TITLE", "TITLE", "MOVIE_TITLE", "SERIE_TITLE", "PERSON_NAME",
		"COMPANY_NAME", "NETWORK_NAME", "COLLECTION_NAME", "TOPIC_NAME", "LIST_NAME", "MOVEMENT_NAME",
		"GENRE_NAME", "DESCRIPTION", "DESCRIPTION_FR", "GROUP_NAME", "DEATH_NAME", "AWARD_NAME",
		"NOMINATION_NAME", "SOURCE_WORK_NAME", "ITEM_LABEL", "CONTENT_TITLE") as $strkey) {
		if (isset($arrrecord[$strkey]) && trim((string)$arrrecord[$strkey])!=="") {
			return (string)$arrrecord[$strkey];
		}
	}
	foreach ($arrrecord as $value) {
		if (is_string($value) && trim($value)!=="" && !is_numeric(trim($value))) {
			return $value;
		}
	}
	return "";
}

function f_t2srep_pick_bar_metric($arrrecords, $arrcolumns) {
	//Purpose: Mirrors pickBarMetric(): the metric column the API marks `ordered` (the ranked
	//			quantity), else the first metric marked `focus` (the quantity
	//			the question asked for), else the first metric column, else the first
	//			all-numeric column
	$arrfirstmetric=null;
	$arrfocusmetric=null;
	foreach ($arrcolumns as $arrcol) {
		if (is_array($arrcol) && isset($arrcol['role']) && $arrcol['role']=="metric") {
			$arrmetric=array("name"=>(string)$arrcol['name'], "unit"=>isset($arrcol['unit']) ? $arrcol['unit'] : null);
			if (isset($arrcol['ordered']) && $arrcol['ordered']!=="" && $arrcol['ordered']!==null) {
				return $arrmetric;
			}
			if ($arrfocusmetric===null && !empty($arrcol['focus'])) {
				$arrfocusmetric=$arrmetric;
			}
			if ($arrfirstmetric===null) {
				$arrfirstmetric=$arrmetric;
			}
		}
	}
	if ($arrfocusmetric!==null) {
		return $arrfocusmetric;
	}
	if ($arrfirstmetric!==null) {
		return $arrfirstmetric;
	}
	if (count($arrrecords)==0) {
		return null;
	}
	foreach (array_keys($arrrecords[0]) as $strkey) {
		$intallnumericornull=true;
		$intsomenumeric=false;
		foreach ($arrrecords as $arrrecord) {
			$value=array_key_exists($strkey, $arrrecord) ? $arrrecord[$strkey] : null;
			if ($value!==null && !f_t2srep_is_number($value)) {
				$intallnumericornull=false;
			}
			if (f_t2srep_is_number($value)) {
				$intsomenumeric=true;
			}
		}
		if ($intallnumericornull && $intsomenumeric) {
			return array("name"=>(string)$strkey, "unit"=>null);
		}
	}
	return null;
}

function f_t2srep_metric_bar($value, $dblmax, $strunit, $strlang) {
	//Purpose: Mirrors buildMetricBar(): the track, its fill (at least 2%) and the formatted value
	$lngpct=0;
	if (f_t2srep_is_number($value) && $dblmax>0) {
		$lngpct=max(2, (int)round(((float)$value/$dblmax)*100));
	}
	return "<div class=\"t2sBarBody\"><div class=\"t2sBarTrack\"><div class=\"t2sBarFill\" style=\"width:".$lngpct."%\"></div></div>"
		."<div class=\"t2sBarValue\">".f_t2srep_h(f_t2srep_format_metric($value, $strunit, $strlang))."</div></div>";
}

function f_t2srep_render_scalar_cards($arrrecords, $arrcolumns, $strlang) {
	//Purpose: Mirrors renderScalarCards(): one card per metric (a number card, or a stat strip of 2 to 5)
	$arrmetrics=f_t2srep_scalar_metrics($arrrecords, $arrcolumns);
	if (count($arrmetrics)==0) {
		return f_t2srep_render_table($arrrecords, $arrcolumns, $strlang);
	}
	$strhtml="<div class=\"".(count($arrmetrics)>1 ? "t2sStatStrip" : "t2sNumberCardWrap")."\">";
	foreach ($arrmetrics as $arrmetric) {
		$strhtml.="<div class=\"t2sNumberCard\"><div class=\"t2sNumberValue\">".f_t2srep_h(f_t2srep_format_metric($arrmetric['value'], $arrmetric['unit'], $strlang))."</div>"
			."<div class=\"t2sNumberLabel\">".f_t2srep_h(f_t2srep_pretty_label($arrmetric['name']))."</div></div>";
	}
	return $strhtml."</div>";
}

function f_t2srep_entity_routes() {
	//Purpose: The page of each entity id column, the routes of the card renderer of
	//			text2sql-search.inc.php (`case "ID_...":`): the T2S collection, list and topic have
	//			their own pages (t2scollection.php, t2slist.php, t2stopic.php), the TMDb ones theirs.
	//			ID_CONTENT (the movie+series union) is routed by CONTENT_TYPE, see
	//			f_t2srep_entity_href_for(). Only types the site has a page for are listed.
	return array(
		"ID_MOVIE"=>"movie.php?movieid=", "ID_SERIE"=>"serie.php?serieid=", "ID_PERSON"=>"person.php?personid=",
		"ID_T2S_COLLECTION"=>"t2scollection.php?collectionid=", "ID_COLLECTION"=>"collection.php?collectionid=",
		"ID_COMPANY"=>"company.php?companyid=", "ID_NETWORK"=>"network.php?networkid=",
		"ID_T2S_LIST"=>"t2slist.php?listid=", "ID_LIST"=>"list.php?listid=", "ID_TOPIC"=>"t2stopic.php?topicid=",
		"ID_GROUP"=>"group.php?groupid=", "ID_MOVEMENT"=>"movement.php?movementid=",
		"ID_TECHNICAL"=>"technical.php?technicalid=", "ID_LOCATION"=>"location.php?locationid=",
		"ID_GENRE"=>"genre.php?genreid=", "ID_AWARD"=>"award.php?awardid=",
		"ID_NOMINATION"=>"nomination.php?nominationid=", "ID_DEATH"=>"death.php?deathid=",
		"ID_SOURCE_WORK"=>"sourcework.php?sourceworkid=",
	);
}

function f_t2srep_entity_href_for($arrrecord, $stridcol) {
	//Purpose: The page of the entity one id column of a row names, "" when the
	//			cell is blank or the site has no page for that type
	$stridcol=strtoupper((string)$stridcol);
	if (!is_array($arrrecord) || !isset($arrrecord[$stridcol]) || trim((string)$arrrecord[$stridcol])==="") {
		return "";
	}
	$strid=trim((string)$arrrecord[$stridcol]);
	if ($stridcol=="ID_CONTENT") {
		$strtype=strtolower(isset($arrrecord["CONTENT_TYPE"]) ? (string)$arrrecord["CONTENT_TYPE"] : "movie");
		return (strpos($strtype, "serie")===0 ? "serie.php?serieid=" : "movie.php?movieid=").rawurlencode($strid);
	}
	$arrroutes=f_t2srep_entity_routes();
	if (!isset($arrroutes[$stridcol])) {
		return "";
	}
	if ($stridcol=="ID_SOURCE_WORK") {
		//The card renderer reads it as a number (f_stringtolong)
		$strid=(string)(int)$strid;
	}
	return $arrroutes[$stridcol].rawurlencode($strid);
}

function f_t2srep_entity_href($arrrecord) {
	//Purpose: The page of the entity a result row names, for the label of a bar (the names in
	//			the bars could not be clicked, 2026-11-08); "" when the row names none. Same routes
	//			as the card renderer of text2sql-search.inc.php.
	if (!is_array($arrrecord)) {
		return "";
	}
	$strhref=f_t2srep_entity_href_for($arrrecord, "ID_CONTENT");
	if ($strhref!="") {
		return $strhref;
	}
	foreach (array_keys(f_t2srep_entity_routes()) as $strcol) {
		$strhref=f_t2srep_entity_href_for($arrrecord, $strcol);
		if ($strhref!="") {
			return $strhref;
		}
	}
	return "";
}

function f_t2srep_entity_base($strname) {
	//Purpose: The entity a column name speaks of: ID_T2S_COLLECTION and COLLECTION_NAME both give
	//			COLLECTION, MOVIE_TITLE gives MOVIE; "" for a name that names no entity (TITLE)
	$strname=strtoupper((string)$strname);
	if (strpos($strname, "ID_")===0) {
		$strbase=substr($strname, 3);
	} elseif (preg_match('/^(.+)_(TITLE|NAME)$/', $strname, $arrmatch)) {
		$strbase=$arrmatch[1];
	} else {
		return "";
	}
	if (strpos($strbase, "T2S_")===0) {
		$strbase=substr($strbase, 4);
	}
	return $strbase;
}

function f_t2srep_label_links($arrkeys, $arrcolumns) {
	//Purpose: Which cells of a table row link to an entity page, as the cards do.
	//			Every label whose name pairs with an id column of the row (MOVIE_TITLE / ID_MOVIE,
	//			PERSON_NAME / ID_PERSON, COLLECTION_NAME / ID_T2S_COLLECTION, CONTENT_TITLE /
	//			ID_CONTENT) links to its own id; the label column of result_columns (else the first
	//			*_TITLE / *_NAME column) links to the main id when no name pairs it, unless its
	//			name says it speaks of another entity (a PERSON_NAME next to an ID_MOVIE only).
	//Params:	$arrkeys: the column names of the table, in order; $arrcolumns: result_columns
	//Returns: array(label column => id column)
	$arrroutes=f_t2srep_entity_routes();
	$arrroutes["ID_CONTENT"]="";
	$arrupper=array();
	foreach ($arrkeys as $strkey) {
		$arrupper[strtoupper((string)$strkey)]=(string)$strkey;
	}
	$arrids=array();
	foreach ($arrupper as $strupper=>$strkey) {
		if (isset($arrroutes[$strupper])) {
			$arrids[$strupper]=$strkey;
		}
	}
	$arrlinks=array();
	if (count($arrids)==0) {
		return $arrlinks;
	}
	//1. Pairs evident from the names
	foreach ($arrids as $stridupper=>$stridkey) {
		$strbase=f_t2srep_entity_base($stridupper);
		foreach ($arrupper as $strupper=>$strkey) {
			if (isset($arrids[$strupper]) || isset($arrlinks[$strkey])) {
				continue;
			}
			if (preg_match('/_(TITLE|NAME)$/', $strupper) && f_t2srep_entity_base($strupper)==$strbase) {
				$arrlinks[$strkey]=$stridkey;
				break;
			}
		}
	}
	//2. The label of the row, to the main id
	$strlabel="";
	foreach ($arrcolumns as $arrcol) {
		if (is_array($arrcol) && isset($arrcol['role']) && $arrcol['role']=="label" && isset($arrcol['name']) && isset($arrupper[strtoupper((string)$arrcol['name'])])) {
			$strlabel=$arrupper[strtoupper((string)$arrcol['name'])];
			break;
		}
	}
	if ($strlabel=="") {
		foreach ($arrupper as $strupper=>$strkey) {
			if (preg_match('/(^|_)(TITLE|NAME)$/', $strupper)) {
				$strlabel=$strkey;
				break;
			}
		}
	}
	if ($strlabel=="" || isset($arrlinks[$strlabel]) || isset($arrids[strtoupper($strlabel)])) {
		return $arrlinks;
	}
	$strmain="";
	foreach ($arrcolumns as $arrcol) {
		if (is_array($arrcol) && isset($arrcol['role']) && $arrcol['role']=="entity_id" && isset($arrcol['name']) && isset($arrids[strtoupper((string)$arrcol['name'])])) {
			$strmain=strtoupper((string)$arrcol['name']);
			break;
		}
	}
	if ($strmain=="") {
		$strmain=(string)array_key_first($arrids);
	}
	if (in_array($arrids[$strmain], $arrlinks, true)) {
		return $arrlinks;
	}
	//A label named after an entity type the site knows, other than the main one, is not its title
	$strlabelbase=f_t2srep_entity_base($strlabel);
	$arrbases=array();
	foreach (array_keys($arrroutes) as $strroute) {
		$arrbases[f_t2srep_entity_base($strroute)]=true;
	}
	if ($strlabelbase!="" && isset($arrbases[$strlabelbase]) && $strlabelbase!=f_t2srep_entity_base($strmain)) {
		return $arrlinks;
	}
	$arrlinks[$strlabel]=$arrids[$strmain];
	return $arrlinks;
}

function f_t2srep_render_ranked_bars($arrrecords, $arrcolumns, $strlang, $fnposter=null) {
	//Purpose: Mirrors renderRankedBars(): a poster ranking when every row of a small set has a
	//			poster, horizontal bars otherwise
	//Params:	$fnposter: callable($arrrecord) returning array("url"=>..., "title"=>..., "href"=>...)
	//			or null when the row has no poster; null skips the poster ranking
	$arrmetric=f_t2srep_pick_bar_metric($arrrecords, $arrcolumns);
	if ($arrmetric===null || count($arrrecords)==0) {
		return f_t2srep_render_table($arrrecords, $arrcolumns, $strlang);
	}
	$dblmax=0;
	foreach ($arrrecords as $arrrecord) {
		$value=isset($arrrecord[$arrmetric['name']]) ? $arrrecord[$arrmetric['name']] : null;
		if (f_t2srep_is_number($value)) {
			$dblmax=max($dblmax, (float)$value);
		}
	}
	$arrposters=array();
	if (is_callable($fnposter) && count($arrrecords)<=T2SREP_POSTER_RANKING_MAX) {
		foreach ($arrrecords as $arrrecord) {
			$arrposter=$fnposter($arrrecord);
			if (!is_array($arrposter) || !isset($arrposter['url']) || $arrposter['url']=="") {
				$arrposters=array();
				break;
			}
			$arrposters[]=$arrposter;
		}
	}
	$strhtml="<div class=\"search-poster-results\">";
	if (count($arrposters)==count($arrrecords) && count($arrposters)>0) {
		$strhtml.="<div class=\"search-poster-card-grid t2sPosterRanking\">";
		foreach ($arrrecords as $lngi => $arrrecord) {
			$arrposter=$arrposters[$lngi];
			$strtitle=isset($arrposter['title']) && $arrposter['title']!="" ? $arrposter['title'] : f_t2srep_label_for_row($arrrecord, $arrcolumns);
			$strhref=isset($arrposter['href']) ? (string)$arrposter['href'] : "";
			//Numbered, as the leaderboards do (rank, poster, title, value)
			$strhtml.="<div class=\"search-poster-card t2sRankCard\"><div class=\"t2sRankNumber\" aria-hidden=\"true\">".($lngi+1)."</div>";
			$strhtml.=$strhref!="" ? "<a class=\"search-poster-card-link\" href=\"".f_t2srep_h($strhref)."\">" : "";
			$strhtml.="<div class=\"search-poster-card-media\"><img src=\"".f_t2srep_h($arrposter['url'])."\" alt=\"".f_t2srep_h($strtitle)."\" loading=\"lazy\"></div>";
			$strhtml.="<div class=\"search-poster-card-text\"><div class=\"search-poster-card-title\">".f_t2srep_h($strtitle)."</div></div>";
			$strhtml.=$strhref!="" ? "</a>" : "";
			$value=isset($arrrecord[$arrmetric['name']]) ? $arrrecord[$arrmetric['name']] : null;
			$strhtml.=f_t2srep_metric_bar($value, $dblmax, $arrmetric['unit'], $strlang);
			$strhtml.="</div>";
		}
		$strhtml.="</div>";
	} else {
		$strhtml.="<div class=\"t2sBars\">";
		foreach ($arrrecords as $arrrecord) {
			$strlabel=f_t2srep_label_for_row($arrrecord, $arrcolumns);
			$value=isset($arrrecord[$arrmetric['name']]) ? $arrrecord[$arrmetric['name']] : null;
			$strhref=f_t2srep_entity_href($arrrecord);
			$strlabelhtml=$strhref!="" ? "<a href=\"".f_t2srep_h($strhref)."\">".f_t2srep_h($strlabel)."</a>" : f_t2srep_h($strlabel);
			$strhtml.="<div class=\"t2sBar\"><div class=\"t2sBarLabel\" title=\"".f_t2srep_h($strlabel)."\">".$strlabelhtml."</div>"
				.f_t2srep_metric_bar($value, $dblmax, $arrmetric['unit'], $strlang)."</div>";
		}
		$strhtml.="</div>";
	}
	return $strhtml."</div>";
}

function f_t2srep_render_table($arrrecords, $arrcolumns, $strlang) {
	//Purpose: Mirrors renderRawTable(): the honest fallback, metrics formatted by their unit
	if (count($arrrecords)==0) {
		return "";
	}
	$arrkeys=array();
	$arrunits=array();
	$arrmetrics=array();
	foreach ($arrcolumns as $arrcol) {
		if (is_array($arrcol) && isset($arrcol['name'])) {
			$arrkeys[]=(string)$arrcol['name'];
			//A date column (BIRTH_YEAR, DEATH_YEAR) is shown as is, never with a unit: the API
			//typed a calendar year "years" and the table printed "1,899 years" (2026-11-08)
			$arrunits[(string)$arrcol['name']]=(isset($arrcol['unit']) && (!isset($arrcol['role']) || $arrcol['role']!="date")) ? $arrcol['unit'] : null;
			//A metric without a unit (IMDB_VOTES) gets the neutral number format (1,234,567),
			//as the contract says; ids, years and codes stay as they are (2026-11-08)
			if (isset($arrcol['role']) && $arrcol['role']=="metric") {
				$arrmetrics[(string)$arrcol['name']]=true;
			}
		}
	}
	if (count($arrkeys)==0) {
		foreach ($arrrecords as $arrrecord) {
			foreach (array_keys($arrrecord) as $strkey) {
				if (!in_array((string)$strkey, $arrkeys, true)) {
					$arrkeys[]=(string)$strkey;
				}
			}
		}
	}
	//The title of each row links to its page, as the cards do
	$arrlinks=f_t2srep_label_links($arrkeys, $arrcolumns);
	$strhtml="<div class=\"t2sTableWrap\"><table class=\"t2sTable\"><thead><tr>";
	foreach ($arrkeys as $strkey) {
		$strhtml.="<th>".f_t2srep_h(f_t2srep_pretty_label($strkey))."</th>";
	}
	$strhtml.="</tr></thead><tbody>";
	foreach ($arrrecords as $arrrecord) {
		$strhtml.="<tr>";
		foreach ($arrkeys as $strkey) {
			$value=array_key_exists($strkey, $arrrecord) ? $arrrecord[$strkey] : null;
			$strunit=isset($arrunits[$strkey]) ? $arrunits[$strkey] : null;
			$strcell=($strunit || isset($arrmetrics[$strkey])) ? f_t2srep_format_metric($value, $strunit, $strlang) : ($value===null ? "" : (string)$value);
			$strhref=(isset($arrlinks[$strkey]) && $strcell!=="") ? f_t2srep_entity_href_for($arrrecord, $arrlinks[$strkey]) : "";
			$strhtml.="<td>".($strhref!="" ? "<a href=\"".f_t2srep_h($strhref)."\">".f_t2srep_h($strcell)."</a>" : f_t2srep_h($strcell))."</td>";
		}
		$strhtml.="</tr>";
	}
	return $strhtml."</tbody></table></div>";
}

function f_t2srep_render($strrep, $jsontext2sql, $strlang='en', $fnposter=null) {
	//Purpose: Mirrors renderText2SqlRepresentation(): the HTML of the chosen representation
	//Params:	$strrep: The value f_t2srep_choose() returned (not "")
	//			$jsontext2sql: The decoded /search/text2sql answer
	//			$strlang: UI language; $fnposter: see f_t2srep_render_ranked_bars()
	$arrrecords=f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array());
	$arrcolumns=f_t2srep_columns($jsontext2sql);
	if ($strrep=="ranked-bars") {
		return f_t2srep_render_ranked_bars($arrrecords, $arrcolumns, $strlang, $fnposter);
	}
	if ($strrep=="table") {
		return f_t2srep_render_table($arrrecords, $arrcolumns, $strlang);
	}
	$arrfacts=isset($jsontext2sql['summary_facts']) && is_array($jsontext2sql['summary_facts']) ? $jsontext2sql['summary_facts'] : array();
	if ($strrep=="timeline") {
		return f_t2srep_render_timeline($arrrecords, $arrcolumns, $strlang, $fnposter, $arrfacts);
	}
	if ($strrep=="trend-columns") {
		return f_t2srep_render_trend_columns($arrrecords, $arrcolumns, $strlang, $arrfacts);
	}
	return f_t2srep_render_scalar_cards($arrrecords, $arrcolumns, $strlang);
}

/* ---------------------------------------------------------------------------------------------
 * Phase 1: value cards, the caption from summary_facts, the chips
 * ------------------------------------------------------------------------------------------- */

function f_t2srep_value_columns($arrcolumns) {
	//Purpose: The column(s) whose value a value card shows under each card: every
	//			column marked `focus` (a comparison can name two, budget and revenue), else the
	//			metric marked `ordered`, else the first metric. A focus column whose name extends
	//			one already kept (IMDB_RATING_WEIGHTED after IMDB_RATING) is the same quantity
	//			twice and is dropped.
	//Returns: array of array("name", "unit", "role"), possibly empty
	$arrfocus=array();
	$arrordered=null;
	$arrfirst=null;
	foreach ($arrcolumns as $arrcol) {
		if (!is_array($arrcol) || !isset($arrcol['name'])) {
			continue;
		}
		$strrole=isset($arrcol['role']) ? (string)$arrcol['role'] : "";
		$arrvalue=array("name"=>(string)$arrcol['name'], "unit"=>isset($arrcol['unit']) ? $arrcol['unit'] : null, "role"=>$strrole);
		if (!empty($arrcol['focus']) && ($strrole=="metric" || $strrole=="date")) {
			$intsame=false;
			foreach ($arrfocus as $arrkept) {
				if (strpos($arrvalue['name'], $arrkept['name'])===0) {
					$intsame=true;
				}
			}
			if (!$intsame) {
				$arrfocus[]=$arrvalue;
			}
		}
		if ($strrole=="metric") {
			if ($arrordered===null && isset($arrcol['ordered']) && $arrcol['ordered']!=="" && $arrcol['ordered']!==null) {
				$arrordered=$arrvalue;
			}
			if ($arrfirst===null) {
				$arrfirst=$arrvalue;
			}
		}
	}
	if (count($arrfocus)>0) {
		return $arrfocus;
	}
	if ($arrordered!==null) {
		return array($arrordered);
	}
	return $arrfirst!==null ? array($arrfirst) : array();
}

function f_t2srep_format_value($value, $strunit, $strrole='metric', $strlang='en') {
	//Purpose: The short form of a value, for a value card and the caption: minutes as "2 h 18"
	//			("45 min" under an hour), a year (a date column) as is, everything else as
	//			f_t2srep_format_metric(). A missing value gives "", never "0": TMDb stores 0 for an
	//			unknown runtime or budget, so a 0 in minutes or dollars counts as missing too.
	if ($value===null || trim((string)$value)==="") {
		return "";
	}
	if ($strrole=="date") {
		return f_t2srep_is_number($value) ? (string)(int)round((float)$value) : (string)$value;
	}
	if (f_t2srep_is_number($value) && (float)$value==0.0 && ($strunit=="minutes" || $strunit=="usd")) {
		return "";
	}
	if ($strunit=="minutes" && f_t2srep_is_number($value)) {
		$lngtotal=(int)round((float)$value);
		$lngh=intdiv($lngtotal, 60);
		$lngm=$lngtotal % 60;
		return $lngh>0 ? $lngh." h ".str_pad((string)$lngm, 2, "0", STR_PAD_LEFT) : $lngm." min";
	}
	return f_t2srep_format_metric($value, $strunit, $strlang);
}

function f_t2srep_metric_word($strname, $strlang='en') {
	//Purpose: How the caption and the value cards name a quantity, in the UI language
	//Returns: array("label"=>..., "gender"=>"m"|"f") (the gender serves the French agreement)
	$arrwords=array(
		"RUNTIME"=>array("runtime", "durée", "f"),
		"TOTAL_RUNTIME"=>array("total runtime", "durée totale", "f"),
		"BUDGET"=>array("budget", "budget", "m"),
		"REVENUE"=>array("revenue", "box-office", "m"),
		"PROFIT"=>array("profit", "bénéfice", "m"),
		"IMDB_RATING"=>array("IMDb rating", "note IMDb", "f"),
		"IMDB_RATING_WEIGHTED"=>array("IMDb rating", "note IMDb", "f"),
		"IMDB_RATING_ADJUSTED"=>array("IMDb rating", "note IMDb", "f"),
		"IMDB_VOTES"=>array("IMDb votes", "nombre de votes IMDb", "m"),
		"VOTE_AVERAGE"=>array("TMDb rating", "note TMDb", "f"),
		"VOTE_COUNT"=>array("TMDb votes", "nombre de votes TMDb", "m"),
		"POPULARITY"=>array("popularity", "popularité", "f"),
		"FILM_COUNT"=>array("number of films", "nombre de films", "m"),
		"MOVIE_COUNT"=>array("number of films", "nombre de films", "m"),
		"SERIE_COUNT"=>array("number of series", "nombre de séries", "m"),
		"NUMBER_OF_SEASONS"=>array("number of seasons", "nombre de saisons", "m"),
		"NUMBER_OF_EPISODES"=>array("number of episodes", "nombre d'épisodes", "m"),
		"BIRTH_YEAR"=>array("birth year", "année de naissance", "f"),
		"DEATH_YEAR"=>array("death year", "année de décès", "f"),
	);
	$strkey=strtoupper((string)$strname);
	if (isset($arrwords[$strkey])) {
		return array("label"=>$arrwords[$strkey][$strlang=="fr" ? 1 : 0], "gender"=>$arrwords[$strkey][2]);
	}
	return array("label"=>strtolower(f_t2srep_pretty_label($strname)), "gender"=>"m");
}

function f_t2srep_ucfirst($strtext) {
	//Purpose: Capitalises the first letter of a UTF-8 string ("élevée" included), without
	//			mbstring, which not every PHP that runs the test carries
	$strtext=(string)$strtext;
	if (!preg_match('/^(.)(.*)$/su', $strtext, $arrmatch)) {
		return $strtext;
	}
	$strfirst=strtr($arrmatch[1], array("é"=>"É", "è"=>"È", "ê"=>"Ê", "à"=>"À", "â"=>"Â", "î"=>"Î", "ô"=>"Ô", "û"=>"Û", "ç"=>"Ç"));
	return ucfirst($strfirst).$arrmatch[2];
}

function f_t2srep_card_value_html($arrrecord, $arrvaluecolumns, $strlang='en') {
	//Purpose: The value line(s) of one value card, or "" when the row has none.
	//			One quantity is shown bare ("2 h 18"); two or more carry their name ("Budget
	//			$150,000,000"), so a comparison of budget and revenue reads without a legend.
	$arrlines=array();
	foreach ($arrvaluecolumns as $arrvaluecol) {
		$value=array_key_exists($arrvaluecol['name'], $arrrecord) ? $arrrecord[$arrvaluecol['name']] : null;
		$strvalue=f_t2srep_format_value($value, $arrvaluecol['unit'], $arrvaluecol['role'], $strlang);
		if ($strvalue==="") {
			continue;
		}
		$strlabel="";
		if (count($arrvaluecolumns)>1) {
			$arrword=f_t2srep_metric_word($arrvaluecol['name'], $strlang);
			$strlabel="<span class=\"t2sCardValueLabel\">".f_t2srep_h(f_t2srep_ucfirst($arrword['label']))."</span> ";
		}
		$arrlines[]="<div class=\"t2sCardValue\">".$strlabel.f_t2srep_h($strvalue)."</div>";
	}
	return implode("", $arrlines);
}

function f_t2srep_noun($arrrecords, $arrcolumns, $strlang='en') {
	//Purpose: What the rows are, for the caption, read from the entity column (result_entity is
	//			the classifier's reading of the question, not of the rows)
	//Returns: array("plural"=>..., "gender"=>"m"|"f")
	$arrnouns=array(
		"movie"=>array("films", "films", "m"),
		"serie"=>array("series", "séries", "f"),
		"content"=>array("titles", "titres", "m"),
		"person"=>array("people", "personnes", "f"),
		"row"=>array("rows", "lignes", "f"),
		"result"=>array("results", "résultats", "m"),
	);
	$strkind="row";
	foreach ($arrcolumns as $arrcol) {
		if (is_array($arrcol) && isset($arrcol['role']) && $arrcol['role']=="entity_id") {
			$strname=strtoupper((string)$arrcol['name']);
			if ($strname=="ID_MOVIE") {
				$strkind="movie";
			} elseif ($strname=="ID_SERIE") {
				$strkind="serie";
			} elseif ($strname=="ID_PERSON") {
				$strkind="person";
			} elseif ($strname=="ID_CONTENT") {
				//Films and series mixed are "titles"; one type alone takes its own noun
				$arrtypes=array();
				foreach ($arrrecords as $arrrecord) {
					$arrtypes[isset($arrrecord['CONTENT_TYPE']) ? (string)$arrrecord['CONTENT_TYPE'] : ""]=true;
				}
				$strkind="content";
				if (count($arrtypes)==1 && isset($arrtypes['movie'])) {
					$strkind="movie";
				} elseif (count($arrtypes)==1 && isset($arrtypes['serie'])) {
					$strkind="serie";
				}
			} else {
				$strkind="result";
			}
			break;
		}
	}
	return array("plural"=>$arrnouns[$strkind][$strlang=="fr" ? 1 : 0], "gender"=>$arrnouns[$strkind][2]);
}

function f_t2srep_count_word($lngcount, $strlang='en', $strgender='m') {
	//Purpose: A count as a word up to twelve ("eight", "huit"), as digits beyond
	$arren=array("zero", "one", "two", "three", "four", "five", "six", "seven", "eight", "nine", "ten", "eleven", "twelve");
	$arrfr=array("zéro", "un", "deux", "trois", "quatre", "cinq", "six", "sept", "huit", "neuf", "dix", "onze", "douze");
	$lngcount=(int)$lngcount;
	if ($lngcount<0 || $lngcount>12) {
		return number_format($lngcount, 0, '.', ',');
	}
	if ($strlang=="fr") {
		return $lngcount==1 && $strgender=="f" ? "une" : $arrfr[$lngcount];
	}
	return $arren[$lngcount];
}

function f_t2srep_caption($jsontext2sql, $strlang='en') {
	//Purpose: The caption of a list from `summary_facts`, the sentence the
	//			voice-agent speaks: count, order, one extremum. "Eight films in
	//			collection order; the longest is Harry Potter and the Order of the Phoenix, 2 h 18."
	//			A ranking says "the first N by <metric>" and never a total (decision of 2026-10-31:
	//			`total` is null in phase 1). "" with no summary_facts, fewer than two rows, or no
	//			value column: a plain card grid ("films by Kubrick") keeps no caption, as before,
	//			and its infinite scroll would soon make a count wrong anyway.
	if (!is_array($jsontext2sql) || !isset($jsontext2sql['summary_facts']) || !is_array($jsontext2sql['summary_facts'])) {
		return "";
	}
	$arrfacts=$jsontext2sql['summary_facts'];
	$lngcount=isset($arrfacts['count']) && f_t2srep_is_number($arrfacts['count']) ? (int)$arrfacts['count'] : 0;
	$strvaluecolumn=isset($arrfacts['value_column']) ? (string)$arrfacts['value_column'] : "";
	if ($lngcount<2) {
		return "";
	}
	//Phase 2 lot A: the three new forms have their own sentence (contract,
	//"Spoken" rows); a sequence has no value column and still gets one
	$strrep=isset($jsontext2sql['representation']) ? trim((string)$jsontext2sql['representation']) : "";
	if ($strrep=="timeline") {
		return f_t2srep_caption_timeline($jsontext2sql, $strlang);
	}
	if ($strrep=="sequence-strip") {
		return f_t2srep_caption_sequence($jsontext2sql, $strlang);
	}
	if ($strrep=="trend-columns") {
		return f_t2srep_caption_trend($jsontext2sql, $strlang);
	}
	if ($strvaluecolumn=="") {
		return "";
	}
	$intfr=($strlang=="fr");
	$arrrecords=f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array());
	$arrcolumns=f_t2srep_columns($jsontext2sql);
	$arrnoun=f_t2srep_noun($arrrecords, $arrcolumns, $strlang);
	$intfem=($arrnoun['gender']=="f");
	$strtask=isset($jsontext2sql['intent']) && is_array($jsontext2sql['intent']) && isset($jsontext2sql['intent']['task']) ? (string)$jsontext2sql['intent']['task'] : "";
	$strorder=isset($arrfacts['order']) ? (string)$arrfacts['order'] : "";
	$strdirection=isset($arrfacts['order_direction']) ? strtolower((string)$arrfacts['order_direction']) : "";
	$strcount=f_t2srep_count_word($lngcount, $strlang, $arrnoun['gender']);
	//The lead: a ranking ("the first N by <metric>"), or the count and its order. A ranking is
	//an order on the quantity asked for (or a rank task); a default sort key the question did
	//not ask about (IMDB_RATING_WEIGHTED on "the Godfather trilogy in order") is not one, and a
	//comparison sorted on its quantity is not one either: its order is left to the extremum.
	$intranking=($strorder!="" && $strorder!="collection" && $strorder!="date" && $strtask!="compare"
		&& ($strorder==$strvaluecolumn || $strtask=="rank"));
	if ($intranking) {
		$arrmetric=f_t2srep_metric_word($strorder, $strlang);
		if ($intfr) {
			$strlead="Les ".$strcount." ".($intfem ? "premières" : "premiers")." ".$arrnoun['plural']." par ".$arrmetric['label'];
		} else {
			$strlead="The first ".$strcount." ".$arrnoun['plural']." by ".$arrmetric['label'];
		}
	} else {
		//A metric order that is not a ranking is not worded: it would read as one
		$strlead=f_t2srep_ucfirst($strcount)." ".$arrnoun['plural'];
		if ($strorder=="collection") {
			$strlead.=$intfr ? " dans l'ordre de la collection" : " in collection order";
		} elseif ($strorder=="date") {
			if ($strdirection=="desc") {
				$strlead.=$intfr ? ", du plus récent au plus ancien" : ", most recent first";
			} else {
				$strlead.=$intfr ? " dans l'ordre chronologique" : " in chronological order";
			}
		}
	}
	//The extremum, when the API names one
	$strextreme="";
	$arrtop=isset($arrfacts['top']) && is_array($arrfacts['top']) ? $arrfacts['top'] : null;
	if ($arrtop!==null && isset($arrtop['label']) && trim((string)$arrtop['label'])!=="") {
		$strunit=isset($arrfacts['unit']) ? $arrfacts['unit'] : null;
		$strrole="metric";
		foreach ($arrcolumns as $arrcol) {
			if (is_array($arrcol) && isset($arrcol['name']) && (string)$arrcol['name']==$strvaluecolumn && isset($arrcol['role'])) {
				$strrole=(string)$arrcol['role'];
			}
		}
		$strvalue=f_t2srep_format_value(isset($arrtop['value']) ? $arrtop['value'] : null, $strunit, $strrole, $strlang);
		$intmin=(isset($arrtop['extreme']) && $arrtop['extreme']=="min");
		$strlabel=trim((string)$arrtop['label']);
		if ($strunit=="minutes") {
			if ($intfr) {
				$strwho=$intmin ? ($intfem ? "la plus courte" : "le plus court") : ($intfem ? "la plus longue" : "le plus long");
			} else {
				$strwho=$intmin ? "the shortest" : "the longest";
			}
			$strextreme=$strwho.($intfr ? " est " : " is ").$strlabel;
		} elseif (strtoupper($strvaluecolumn)=="BUDGET") {
			if ($intfr) {
				$strwho=$intmin ? ($intfem ? "la moins chère" : "le moins cher") : ($intfem ? "la plus chère" : "le plus cher");
			} else {
				$strwho=$intmin ? "the least expensive" : "the most expensive";
			}
			$strextreme=$strwho.($intfr ? " est " : " is ").$strlabel;
		} elseif ($intranking) {
			//The lead already names the quantity: "...by IMDb rating; top: <title>, 8.1"
			$strextreme=($intfr ? "en tête : " : "top: ").$strlabel;
		} else {
			$arrmetric=f_t2srep_metric_word($strvaluecolumn, $strlang);
			if ($intfr) {
				$strarticle=preg_match('/^[aeiouyéèêàâîôû]/iu', $arrmetric['label']) ? "l'" : ($arrmetric['gender']=="f" ? "la " : "le ");
				$stradj=$arrmetric['gender']=="f" ? ($intmin ? "la plus basse" : "la plus élevée") : ($intmin ? "le plus bas" : "le plus élevé");
				$strextreme=$strarticle.$arrmetric['label']." ".$stradj." revient à ".$strlabel;
			} else {
				$strextreme="the ".($intmin ? "lowest" : "highest")." ".$arrmetric['label']." goes to ".$strlabel;
			}
		}
		if ($strvalue!=="") {
			$strextreme.=", ".$strvalue;
		}
	}
	return $strlead.($strextreme!="" ? ($intfr ? " ; " : "; ").$strextreme : "").".";
}

function f_t2srep_render_chips($arroptions, $stractive, $arrhrefs, $arrlabels, $strarialabel="") {
	//Purpose: The representation chips, one link per option; "" with fewer than two options.
	//			Each link reloads the page with render=<option> (shareable, survives a reload);
	//			f_t2srep_chips_js() turns the click into a local switch, no API call.
	//Params:	$arrhrefs, $arrlabels: option => URL, option => visible label (built by the page)
	if (count($arroptions)<2) {
		return "";
	}
	$strhtml="<nav class=\"t2sChips\" id=\"t2sRepChips\"".($strarialabel!="" ? " aria-label=\"".f_t2srep_h($strarialabel)."\"" : "").">";
	foreach ($arroptions as $stroption) {
		$intactive=($stroption==$stractive);
		$strhtml.="<a class=\"t2sChip".($intactive ? " t2sChipActive" : "")."\" data-t2srep=\"".f_t2srep_h($stroption)."\" href=\"".f_t2srep_h(isset($arrhrefs[$stroption]) ? $arrhrefs[$stroption] : "")."\"".($intactive ? " aria-current=\"true\"" : "").">"
			.f_t2srep_h(isset($arrlabels[$stroption]) ? $arrlabels[$stroption] : $stroption)."</a>";
	}
	return $strhtml."</nav>";
}

function f_t2srep_view_open($arrreps, $stractive, $strcaptionid="") {
	//Purpose: Opens the box of one view, hidden unless it holds the active representation. The
	//			card view holds card-grid and value-cards at once (the same cards, the values shown
	//			only under t2sShowValues), so switching between the two touches no card and the
	//			cards that infinite scroll appends follow the switch too.
	$intcards=false;
	foreach ($arrreps as $strrep) {
		if (f_t2srep_is_card_rep($strrep)) {
			$intcards=true;
		}
	}
	$intshown=in_array($stractive, $arrreps, true);
	$strclass="t2sRepView".($intcards ? " t2sRepCards" : "").($intcards && $stractive=="value-cards" ? " t2sShowValues" : "")
		.($intcards && $stractive=="sequence-strip" ? " t2sShowSequence" : "");
	return "<div class=\"".$strclass."\" data-t2sreps=\"".f_t2srep_h(implode(" ", $arrreps))."\"".($strcaptionid!="" ? " aria-describedby=\"".f_t2srep_h($strcaptionid)."\"" : "").($intshown ? "" : " hidden").">";
}

function f_t2srep_chips_js() {
	//Purpose: The script of the chips: shows the chosen view, rewrites the URL with replaceState
	//			(the link's own href, so render= is there for a reload or a share) and pokes the
	//			infinite scroll, which stays idle while its grid is hidden. A modified click (new
	//			tab) is left to the browser. A plain string with no PHP value in it, so the test
	//			can run node --check on it.
	//			The views are looked up at click time: the script is echoed right after the chip bar,
	//			before the views exist, and a lookup at load found none (live bug of 2026-11-08: the
	//			chip turned active, the view never changed).
	return "(function(){var objbar=document.getElementById('t2sRepChips');if(!objbar){return;}"
		."function f_t2srepshow(strrep){var i;var arrviews=document.querySelectorAll('.t2sRepView');for(i=0;i<arrviews.length;i++){var objview=arrviews[i];var arrreps=(objview.getAttribute('data-t2sreps')||'').split(' ');objview.hidden=arrreps.indexOf(strrep)<0;if(objview.classList.contains('t2sRepCards')){objview.classList.toggle('t2sShowValues',strrep==='value-cards');objview.classList.toggle('t2sShowSequence',strrep==='sequence-strip');}}"
		."var arrchips=objbar.querySelectorAll('a[data-t2srep]');for(i=0;i<arrchips.length;i++){var intactive=arrchips[i].getAttribute('data-t2srep')===strrep;arrchips[i].classList.toggle('t2sChipActive',intactive);if(intactive){arrchips[i].setAttribute('aria-current','true');}else{arrchips[i].removeAttribute('aria-current');}}"
		."try{window.dispatchEvent(new Event('scroll'));}catch(e){}}"
		."objbar.addEventListener('click',function(e){var objchip=e.target&&e.target.closest?e.target.closest('a[data-t2srep]'):null;if(!objchip){return;}if(e.button!==0||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey){return;}e.preventDefault();f_t2srepshow(objchip.getAttribute('data-t2srep'));if(window.history&&window.history.replaceState){window.history.replaceState(null,'',objchip.getAttribute('href'));}});"
		."})();";
}

/* ---------------------------------------------------------------------------------------------
 * Phase 2 lot A: timeline, sequence-strip, trend-columns. The contract is the
 * fastapi-text2sql README, section "Result representations: the contract for both fronts".
 * timeline and trend-columns are drawn here; sequence-strip is the card grid numbered, so its
 * numbering follows the cards that infinite scroll appends (f_t2srep_sequence_number_html()).
 * ------------------------------------------------------------------------------------------- */

function f_t2srep_year($value) {
	//Purpose: The year of a date cell ("1979-05-25", "1979", 1979), or null when there is none
	//			(blank, "0000-00-00", text)
	if ($value===null || is_bool($value) || is_array($value)) {
		return null;
	}
	$strvalue=trim((string)$value);
	if (f_t2srep_is_number($strvalue)) {
		$lngyear=(int)round((float)$strvalue);
		return $lngyear!=0 ? $lngyear : null;
	}
	if (preg_match('/^(-?\d{1,4})-\d{1,2}(-\d{1,2})?/', $strvalue, $arrmatch)) {
		$lngyear=(int)$arrmatch[1];
		return $lngyear!=0 ? $lngyear : null;
	}
	return null;
}

function f_t2srep_date_column($arrcolumns, $strprefer="") {
	//Purpose: The date column a representation places its rows by: $strprefer when it is a date
	//			column (summary_facts.value_column), else the first `focus` date column, else the
	//			first date column; "" when there is none
	$strfocus="";
	$strfirst="";
	foreach ($arrcolumns as $arrcol) {
		if (!is_array($arrcol) || !isset($arrcol['name']) || !isset($arrcol['role']) || $arrcol['role']!="date") {
			continue;
		}
		$strname=(string)$arrcol['name'];
		if ($strprefer!="" && $strname==$strprefer) {
			return $strname;
		}
		if ($strfocus=="" && !empty($arrcol['focus'])) {
			$strfocus=$strname;
		}
		if ($strfirst=="") {
			$strfirst=$strname;
		}
	}
	return $strfocus!="" ? $strfocus : $strfirst;
}

function f_t2srep_year_span($arrrecords, $strcolumn) {
	//Purpose: The earliest and latest year of a date column, and the rows without one
	//Returns: array("min"=>int|null, "max"=>int|null, "dated"=>n, "undated"=>n)
	$arrspan=array("min"=>null, "max"=>null, "dated"=>0, "undated"=>0);
	foreach ($arrrecords as $arrrecord) {
		$lngyear=($strcolumn!="" && isset($arrrecord[$strcolumn])) ? f_t2srep_year($arrrecord[$strcolumn]) : null;
		if ($lngyear===null) {
			$arrspan['undated']++;
			continue;
		}
		$arrspan['dated']++;
		$arrspan['min']=$arrspan['min']===null ? $lngyear : min($arrspan['min'], $lngyear);
		$arrspan['max']=$arrspan['max']===null ? $lngyear : max($arrspan['max'], $lngyear);
	}
	return $arrspan;
}

function f_t2srep_is_decade_column($strname, $arrrecords) {
	//Purpose: Tells whether a period column holds decades: named so (DECADE, DECENNIE), or every
	//			value a multiple of ten, ten years apart at least
	$strupper=strtoupper((string)$strname);
	if (strpos($strupper, "DECADE")!==false || strpos($strupper, "DECENNIE")!==false || strpos($strupper, "DÉCENNIE")!==false) {
		return true;
	}
	$arryears=array();
	foreach ($arrrecords as $arrrecord) {
		$lngyear=isset($arrrecord[$strname]) ? f_t2srep_year($arrrecord[$strname]) : null;
		if ($lngyear!==null) {
			if ($lngyear % 10!=0) {
				return false;
			}
			$arryears[]=$lngyear;
		}
	}
	if (count($arryears)<2) {
		return false;
	}
	sort($arryears);
	for ($lngi=1; $lngi<count($arryears); $lngi++) {
		if ($arryears[$lngi]-$arryears[$lngi-1]<10) {
			return false;
		}
	}
	return true;
}

function f_t2srep_period_label($lngyear, $intdecade, $strlang='en') {
	//Purpose: A period as written on an axis: a year as is, a decade "1980s" / "années 1980"
	if (!$intdecade) {
		return (string)$lngyear;
	}
	return $strlang=="fr" ? "années ".$lngyear : $lngyear."s";
}

function f_t2srep_timeline_groups($arrrecords, $strcolumn) {
	//Purpose: The rows of a timeline by year, earliest first, the row order kept within a year
	//			(they stack); the rows with no date apart
	//Returns: array("years"=>array(year=>array(row index, ...)), "undated"=>array(row index, ...))
	$arrgroups=array("years"=>array(), "undated"=>array());
	foreach ($arrrecords as $lngi=>$arrrecord) {
		$lngyear=($strcolumn!="" && isset($arrrecord[$strcolumn])) ? f_t2srep_year($arrrecord[$strcolumn]) : null;
		if ($lngyear===null) {
			$arrgroups['undated'][]=$lngi;
		} else {
			$arrgroups['years'][$lngyear][]=$lngi;
		}
	}
	ksort($arrgroups['years']);
	return $arrgroups;
}

function f_t2srep_mark($arrrecord, $arrcolumns, $fnposter, $strclass) {
	//Purpose: One entity of a timeline: its poster thumbnail (when it has one) and its title,
	//			linked to its page as a card is
	$arrposter=is_callable($fnposter) ? $fnposter($arrrecord) : null;
	$strtitle=(is_array($arrposter) && isset($arrposter['title']) && $arrposter['title']!="") ? (string)$arrposter['title'] : f_t2srep_label_for_row($arrrecord, $arrcolumns);
	$strhref=(is_array($arrposter) && isset($arrposter['href']) && $arrposter['href']!="") ? (string)$arrposter['href'] : f_t2srep_entity_href($arrrecord);
	$strurl=(is_array($arrposter) && isset($arrposter['url'])) ? (string)$arrposter['url'] : "";
	$strinner=($strurl!="" ? "<img src=\"".f_t2srep_h($strurl)."\" alt=\"\" loading=\"lazy\">" : "<span class=\"t2sTimelineNoPoster\" aria-hidden=\"true\"></span>")
		."<span class=\"t2sTimelineTitle\">".f_t2srep_h($strtitle)."</span>";
	return "<li class=\"".$strclass."\">".($strhref!="" ? "<a href=\"".f_t2srep_h($strhref)."\" title=\"".f_t2srep_h($strtitle)."\">".$strinner."</a>" : $strinner)."</li>";
}

function f_t2srep_render_timeline($arrrecords, $arrcolumns, $strlang='en', $fnposter=null, $arrfacts=array()) {
	//Purpose: The timeline: the entities on a horizontal axis by the year of the
	//			first focus date column, poster and title per mark, the rows of one year stacked,
	//			the undated rows listed apart. Only the years that hold rows get a column; the gap
	//			between two of them widens with the years between, up to a break mark, so a span of
	//			a century stays readable. The axis scrolls inside its box, never the page.
	$strprefer=(is_array($arrfacts) && isset($arrfacts['value_column'])) ? (string)$arrfacts['value_column'] : "";
	$strcolumn=f_t2srep_date_column($arrcolumns, $strprefer);
	$arrgroups=f_t2srep_timeline_groups($arrrecords, $strcolumn);
	if ($strcolumn=="" || count($arrgroups['years'])==0) {
		return f_t2srep_render_table($arrrecords, $arrcolumns, $strlang);
	}
	$intfr=($strlang=="fr");
	$arryears=array_keys($arrgroups['years']);
	$strlabel=($intfr ? "Frise chronologique, de " : "Timeline, from ").$arryears[0].($intfr ? " à " : " to ").$arryears[count($arryears)-1];
	$strhtml="<div class=\"t2sTimeline\" role=\"region\" aria-label=\"".f_t2srep_h($strlabel)."\" tabindex=\"0\"><ol class=\"t2sTimelineAxis\">";
	$lngprevious=null;
	foreach ($arrgroups['years'] as $lngyear=>$arrindexes) {
		if ($lngprevious!==null) {
			$lnggap=$lngyear-$lngprevious-1;
			if ($lnggap>0) {
				//0.6rem per missing year, up to eight; a longer gap is a break mark
				$strhtml.="<li class=\"t2sTimelineGap".($lnggap>8 ? " t2sTimelineBreak" : "")."\" aria-hidden=\"true\" style=\"width:".(0.6*min($lnggap, 8))."rem\">"
					.($lnggap>8 ? "<span>&hellip;</span>" : "")."</li>";
			}
		}
		$strhtml.="<li class=\"t2sTimelineYear\"><span class=\"t2sTimelineYearLabel\">".f_t2srep_h($lngyear)."</span><ul class=\"t2sTimelineMarks\">";
		foreach ($arrindexes as $lngi) {
			$strhtml.=f_t2srep_mark($arrrecords[$lngi], $arrcolumns, $fnposter, "t2sTimelineMark");
		}
		$strhtml.="</ul></li>";
		$lngprevious=$lngyear;
	}
	$strhtml.="</ol></div>";
	if (count($arrgroups['undated'])>0) {
		$strhtml.="<div class=\"t2sTimelineUndated\"><span class=\"t2sTimelineUndatedLabel\">".($intfr ? "Sans date" : "No date")."</span><ul class=\"t2sTimelineMarks\">";
		foreach ($arrgroups['undated'] as $lngi) {
			$strhtml.=f_t2srep_mark($arrrecords[$lngi], $arrcolumns, $fnposter, "t2sTimelineMark");
		}
		$strhtml.="</ul></div>";
	}
	return $strhtml;
}

function f_t2srep_trend_metric($arrrecords, $arrcolumns, $arrfacts=array()) {
	//Purpose: The quantity a trend draws: summary_facts.value_column, else the focus metric
	//Returns: array("name", "unit") or null
	if (is_array($arrfacts) && isset($arrfacts['value_column']) && (string)$arrfacts['value_column']!="") {
		$strname=(string)$arrfacts['value_column'];
		$strunit=isset($arrfacts['unit']) ? $arrfacts['unit'] : null;
		foreach ($arrcolumns as $arrcol) {
			if (is_array($arrcol) && isset($arrcol['name']) && (string)$arrcol['name']==$strname && isset($arrcol['unit']) && $strunit===null) {
				$strunit=$arrcol['unit'];
			}
		}
		return array("name"=>$strname, "unit"=>$strunit);
	}
	$arrmetric=f_t2srep_pick_bar_metric($arrrecords, $arrcolumns);
	//The all-numeric fallback of the bars can land on the period column itself: no metric then
	if ($arrmetric!==null && f_t2srep_date_column($arrcolumns)==$arrmetric['name']) {
		return null;
	}
	return $arrmetric;
}

function f_t2srep_trend_peak($arrrecords, $strmetric, $arrfacts=array()) {
	//Purpose: The row index of the extremum: summary_facts.top.index when the API names one,
	//			else the highest value; -1 when no row has a number
	if (is_array($arrfacts) && isset($arrfacts['top']) && is_array($arrfacts['top']) && isset($arrfacts['top']['index'])
		&& f_t2srep_is_number($arrfacts['top']['index']) && (int)$arrfacts['top']['index']>=0 && (int)$arrfacts['top']['index']<count($arrrecords)) {
		return (int)$arrfacts['top']['index'];
	}
	$lngpeak=-1;
	foreach ($arrrecords as $lngi=>$arrrecord) {
		$value=isset($arrrecord[$strmetric]) ? $arrrecord[$strmetric] : null;
		if (f_t2srep_is_number($value) && ($lngpeak<0 || (float)$value>(float)$arrrecords[$lngpeak][$strmetric])) {
			$lngpeak=$lngi;
		}
	}
	return $lngpeak;
}

function f_t2srep_render_trend_columns($arrrecords, $arrcolumns, $strlang='en', $arrfacts=array()) {
	//Purpose: The trend: one vertical column per row, in row order (a missing
	//			period is not filled in), x the period ("1980s" / "années 1980" for a decade),
	//			height the metric, the value formatted by its unit. Pure HTML and CSS; each column
	//			carries its period and value as text, so a screen reader reads "1980s: 11". Over
	//			twelve columns the values above the bars are hidden from sight (still read, and in
	//			the tooltip), and the extremum keeps its value.
	$strperiod=f_t2srep_date_column($arrcolumns);
	$arrmetric=f_t2srep_trend_metric($arrrecords, $arrcolumns, $arrfacts);
	if ($strperiod=="" || $arrmetric===null || count($arrrecords)==0) {
		return f_t2srep_render_table($arrrecords, $arrcolumns, $strlang);
	}
	$intdecade=f_t2srep_is_decade_column($strperiod, $arrrecords);
	$dblmax=0;
	foreach ($arrrecords as $arrrecord) {
		$value=isset($arrrecord[$arrmetric['name']]) ? $arrrecord[$arrmetric['name']] : null;
		if (f_t2srep_is_number($value)) {
			$dblmax=max($dblmax, (float)$value);
		}
	}
	$lngpeak=f_t2srep_trend_peak($arrrecords, $arrmetric['name'], $arrfacts);
	$arrword=f_t2srep_metric_word($arrmetric['name'], $strlang);
	$intfr=($strlang=="fr");
	$strlabel=f_t2srep_ucfirst($arrword['label']).($intfr ? ($intdecade ? " par décennie" : " par année") : ($intdecade ? " per decade" : " per year"));
	$strhtml="<figure class=\"t2sTrend".(count($arrrecords)>12 ? " t2sTrendDense" : "")."\" role=\"group\" aria-label=\"".f_t2srep_h($strlabel)."\">"
		."<div class=\"t2sTrendScroll\" tabindex=\"0\"><ol class=\"t2sTrendCols\">";
	foreach ($arrrecords as $lngi=>$arrrecord) {
		$lngyear=isset($arrrecord[$strperiod]) ? f_t2srep_year($arrrecord[$strperiod]) : null;
		$strperiodlabel=$lngyear!==null ? f_t2srep_period_label($lngyear, $intdecade, $strlang) : (isset($arrrecord[$strperiod]) ? (string)$arrrecord[$strperiod] : "");
		$value=isset($arrrecord[$arrmetric['name']]) ? $arrrecord[$arrmetric['name']] : null;
		$strvalue=f_t2srep_format_metric($value, $arrmetric['unit'], $strlang);
		$lngpct=0;
		if (f_t2srep_is_number($value) && $dblmax>0 && (float)$value>0) {
			$lngpct=max(1, (int)round(((float)$value/$dblmax)*100));
		}
		$strhtml.="<li class=\"t2sTrendCol".($lngi==$lngpeak ? " t2sTrendPeak" : "")."\" title=\"".f_t2srep_h($strperiodlabel.": ".$strvalue)."\">"
			."<span class=\"t2sTrendValue\">".f_t2srep_h($strvalue)."</span>"
			."<span class=\"t2sTrendTrack\" aria-hidden=\"true\"><span class=\"t2sTrendBar\" style=\"height:".$lngpct."%\"></span></span>"
			."<span class=\"t2sTrendLabel\">".f_t2srep_h($strperiodlabel)."</span></li>";
	}
	return $strhtml."</ol></div></figure>";
}

function f_t2srep_sequence_number_html($lngnumber) {
	//Purpose: The number of a card under sequence-strip, shown only under .t2sShowSequence (the
	//			badge of the poster ranking). The page passes the offset of its page plus the row,
	//			so the cards infinite scroll appends go on counting.
	return "<div class=\"t2sRankNumber t2sSeqNumber\" aria-hidden=\"true\">".(int)$lngnumber."</div>";
}

function f_t2srep_span_words($arrspan, $strlang='en', $intdecade=false) {
	//Purpose: "from 1979 to 2017" / "de 1979 à 2017", "in 1999" / "en 1999" for one year,
	//			"from the 1960s to the 2020s" / "des années 1960 aux années 2020"; "" with no year
	if ($arrspan['min']===null) {
		return "";
	}
	$intfr=($strlang=="fr");
	if ($arrspan['min']==$arrspan['max']) {
		if ($intdecade) {
			return $intfr ? "dans les années ".$arrspan['min'] : "in the ".$arrspan['min']."s";
		}
		return ($intfr ? "en " : "in ").$arrspan['min'];
	}
	if ($intdecade) {
		return $intfr ? "des années ".$arrspan['min']." aux années ".$arrspan['max'] : "from the ".$arrspan['min']."s to the ".$arrspan['max']."s";
	}
	return $intfr ? "de ".$arrspan['min']." à ".$arrspan['max'] : "from ".$arrspan['min']." to ".$arrspan['max'];
}

function f_t2srep_caption_timeline($jsontext2sql, $strlang='en') {
	//Purpose: The caption of a timeline: count and span ("Four films, from 1979 to 1997."), the
	//			rows with no date counted apart
	$arrfacts=$jsontext2sql['summary_facts'];
	$arrrecords=f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array());
	$arrcolumns=f_t2srep_columns($jsontext2sql);
	$lngcount=(int)$arrfacts['count'];
	$arrnoun=f_t2srep_noun($arrrecords, $arrcolumns, $strlang);
	$intfr=($strlang=="fr");
	$strcolumn=f_t2srep_date_column($arrcolumns, isset($arrfacts['value_column']) ? (string)$arrfacts['value_column'] : "");
	$arrspan=f_t2srep_year_span($arrrecords, $strcolumn);
	$strtext=f_t2srep_ucfirst(f_t2srep_count_word($lngcount, $strlang, $arrnoun['gender']))." ".$arrnoun['plural'];
	$strspan=f_t2srep_span_words($arrspan, $strlang);
	if ($strspan!="") {
		$strtext.=", ".$strspan;
	}
	if ($arrspan['undated']>0 && $arrspan['dated']>0) {
		$strundated=f_t2srep_count_word($arrspan['undated'], $strlang, $arrnoun['gender']);
		$strtext.=$intfr ? " ; ".$strundated." sans date" : "; ".$strundated." without a date";
	}
	return $strtext.".";
}

function f_t2srep_caption_sequence($jsontext2sql, $strlang='en') {
	//Purpose: The caption of a sequence: count, order and span ("Three films in release order,
	//			from 1964 to 1966.")
	$arrfacts=$jsontext2sql['summary_facts'];
	$arrrecords=f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array());
	$arrcolumns=f_t2srep_columns($jsontext2sql);
	$lngcount=(int)$arrfacts['count'];
	$arrnoun=f_t2srep_noun($arrrecords, $arrcolumns, $strlang);
	$intfr=($strlang=="fr");
	$strorder=isset($arrfacts['order']) ? (string)$arrfacts['order'] : "";
	$strdirection=isset($arrfacts['order_direction']) ? strtolower((string)$arrfacts['order_direction']) : "";
	$strcolumn=f_t2srep_date_column($arrcolumns);
	$arrspan=f_t2srep_year_span($arrrecords, $strcolumn);
	$strtext=f_t2srep_ucfirst(f_t2srep_count_word($lngcount, $strlang, $arrnoun['gender']))." ".$arrnoun['plural'];
	$intdesc=false;
	if ($strorder=="collection") {
		$strtext.=$intfr ? " dans l'ordre de la collection" : " in collection order";
	} elseif ($strorder=="date" && $strdirection=="desc") {
		$strtext.=$intfr ? ", du plus récent au plus ancien" : ", most recent first";
		$intdesc=true;
	} elseif ($strorder=="date") {
		$intrelease=preg_match('/RELEASE|FIRST_AIR/', strtoupper($strcolumn))===1;
		$strtext.=$intrelease ? ($intfr ? " dans l'ordre de sortie" : " in release order") : ($intfr ? " dans l'ordre chronologique" : " in chronological order");
	}
	if ($intdesc && $arrspan['min']!==null && $arrspan['min']!=$arrspan['max']) {
		$strtext.=$intfr ? ", de ".$arrspan['max']." à ".$arrspan['min'] : ", from ".$arrspan['max']." to ".$arrspan['min'];
	} else {
		$strspan=f_t2srep_span_words($arrspan, $strlang);
		if ($strspan!="") {
			$strtext.=", ".$strspan;
		}
	}
	return $strtext.".";
}

function f_t2srep_caption_trend($jsontext2sql, $strlang='en') {
	//Purpose: The caption of a trend: span and extremum ("From the 1960s to the 2020s; the peak
	//			is the 1980s, 11 films." / "Des années 1960 aux années 2020 ; le pic est dans les
	//			années 1980, avec 11 films.")
	$arrfacts=$jsontext2sql['summary_facts'];
	$arrrecords=f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array());
	$arrcolumns=f_t2srep_columns($jsontext2sql);
	$intfr=($strlang=="fr");
	$strperiod=f_t2srep_date_column($arrcolumns);
	if ($strperiod=="") {
		return "";
	}
	$intdecade=f_t2srep_is_decade_column($strperiod, $arrrecords);
	$strspan=f_t2srep_span_words(f_t2srep_year_span($arrrecords, $strperiod), $strlang, $intdecade);
	if ($strspan=="") {
		return "";
	}
	$strtext=f_t2srep_ucfirst($strspan);
	$arrmetric=f_t2srep_trend_metric($arrrecords, $arrcolumns, $arrfacts);
	$arrtop=isset($arrfacts['top']) && is_array($arrfacts['top']) ? $arrfacts['top'] : null;
	$lngpeak=($arrmetric!==null && $arrtop!==null) ? f_t2srep_trend_peak($arrrecords, $arrmetric['name'], $arrfacts) : -1;
	if ($lngpeak>=0) {
		$lngyear=isset($arrrecords[$lngpeak][$strperiod]) ? f_t2srep_year($arrrecords[$lngpeak][$strperiod]) : null;
		$value=isset($arrtop['value']) ? $arrtop['value'] : (isset($arrrecords[$lngpeak][$arrmetric['name']]) ? $arrrecords[$lngpeak][$arrmetric['name']] : null);
		$strvalue=f_t2srep_format_value($value, $arrmetric['unit'], 'metric', $strlang);
		//A count of films or series says what it counts: "11 films"
		$arrnouns=array("FILM_COUNT"=>array("film", "films", "film", "films"), "MOVIE_COUNT"=>array("film", "films", "film", "films"),
			"SERIE_COUNT"=>array("series", "series", "série", "séries"));
		$strmetricupper=strtoupper($arrmetric['name']);
		if ($strvalue!=="" && isset($arrnouns[$strmetricupper]) && f_t2srep_is_number($value)) {
			$intone=((float)$value==1.0);
			$strvalue.=" ".$arrnouns[$strmetricupper][($intfr ? 2 : 0)+($intone ? 0 : 1)];
		}
		$intmin=(isset($arrtop['extreme']) && $arrtop['extreme']=="min");
		if ($lngyear!==null && $strvalue!=="") {
			if ($intfr) {
				$strwhen=$intdecade ? "dans les années ".$lngyear : "en ".$lngyear;
				$strtext.=" ; ".($intmin ? "le creux est " : "le pic est ").$strwhen.", avec ".$strvalue;
			} else {
				$strwhen=$intdecade ? "the ".$lngyear."s" : (string)$lngyear;
				$strtext.="; ".($intmin ? "the low point is " : "the peak is ").$strwhen.", ".$strvalue;
			}
		}
	}
	return $strtext.".";
}
