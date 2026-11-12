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
 * `sequence-strip` (the card grid numbered and laid out as one horizontal row: the card renderer,
 * the badge from f_t2srep_sequence_number_html()) and `trend-columns` (one column per year or
 * decade, drawn here), each with its caption. The entity titles of the table are links.
 * A timeline is completed page after page in the browser (f_t2srep_timeline_more_js(), the next
 * pages from f_t2srep_render_timeline_page()), as the cards are by infinite scroll; while a page
 * may follow, its caption and the sequence's say "the first N", then recount what is on screen
 * (f_t2srep_caption_live_js()), as voice-agent does.
 *
 * Phase 2 lot B adds `compare-cards` (two to four entities side by side, the winner of each
 * compared quantity marked, from summary_facts.winners), `fact-card` (one entity and the one
 * value asked for, the homonyms listed below, from summary_facts.fact) and `verdict` (Yes or No,
 * large, from summary_facts.verdict, the evidence drawn by the renderer of the next option: the
 * cards of the search page for rows, the number card for a count, nothing for an empty result).
 * All three are drawn here, each with its caption in f_t2srep_caption().
 *
 * Phase 2 lot C adds `histogram` (one column per bin of summary_facts.histogram, in bin order),
 * `share-bar` (the parts of a whole as one 100% bar with a legend, from summary_facts.share) and
 * `flag-list` (countries with their flag, name and count, from summary_facts.countries; the flag
 * is the code in a badge unless the browser draws flag emoji, f_t2srep_flags_js()). All three
 * are drawn here, each with its caption in f_t2srep_caption().
 *
 * Phase 3 lot A adds `scatter` (one dot per entity over two quantities, from
 * summary_facts.scatter: a hand-written SVG grid with the dots and tick labels in HTML laid over
 * it, the first tap showing a point under the plot, f_t2srep_scatter_js()), with its caption.
 * Phase 3 lot B adds `map` (the countries of summary_facts.countries shaded on a world outline,
 * html/data/world-110m.json, pre-projected, read on the server: no library and no script needed
 * to draw it; f_t2srep_map_js() writes the country under the finger in a line under the map).
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
		"compare-cards", "fact-card", "verdict", "histogram", "share-bar", "flag-list", "scatter", "map", "hierarchy", "chain", "number-card", "stat-strip", "ranked-bars", "table");
}

function f_t2srep_module_reps() {
	//Purpose: The representations drawn by this module; the others keep the card renderer
	return array("timeline", "trend-columns", "compare-cards", "fact-card", "verdict", "histogram", "share-bar", "flag-list", "scatter", "map", "hierarchy", "chain",
		"number-card", "stat-strip", "ranked-bars", "table");
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
	//The question a bucket of a histogram, a trend or a share asks its follow-up from (drill-down)
	$strquestion=isset($jsontext2sql['question']) ? (string)$jsontext2sql['question'] : "";
	if ($strrep=="timeline") {
		return f_t2srep_render_timeline($arrrecords, $arrcolumns, $strlang, $fnposter, $arrfacts);
	}
	if ($strrep=="trend-columns") {
		return f_t2srep_render_trend_columns($arrrecords, $arrcolumns, $strlang, $arrfacts, $strquestion);
	}
	if ($strrep=="compare-cards") {
		return f_t2srep_render_compare_cards($arrrecords, $arrcolumns, $strlang, $fnposter, $arrfacts);
	}
	if ($strrep=="fact-card") {
		return f_t2srep_render_fact_card($arrrecords, $arrcolumns, $strlang, $fnposter, $arrfacts);
	}
	if ($strrep=="verdict") {
		return f_t2srep_render_verdict($arrrecords, $arrcolumns, $strlang, $arrfacts, f_t2srep_verdict_evidence($jsontext2sql));
	}
	//Phase 2 lot C
	if ($strrep=="histogram") {
		return f_t2srep_render_histogram($arrrecords, $arrcolumns, $strlang, $arrfacts, $strquestion);
	}
	if ($strrep=="share-bar") {
		return f_t2srep_render_share_bar($jsontext2sql, $strlang);
	}
	if ($strrep=="flag-list") {
		return f_t2srep_render_flag_list($jsontext2sql, $strlang);
	}
	//Phase 3 lot A
	if ($strrep=="scatter") {
		return f_t2srep_render_scatter($jsontext2sql, $strlang, $fnposter);
	}
	//Phase 3 lot B
	if ($strrep=="map") {
		return f_t2srep_render_map($jsontext2sql, $strlang);
	}
	//Phase 3 lot C
	if ($strrep=="hierarchy") {
		return f_t2srep_render_hierarchy($jsontext2sql, $strlang, $fnposter);
	}
	//Phase 3 lot E
	if ($strrep=="chain") {
		return f_t2srep_render_chain($jsontext2sql, $strlang, $fnposter);
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
	//A union of films and series aliases the release date as DAT_FIRST_AIR and repeats it as
	//DAT_LAST_AIR: the first date of the span is the one asked for, the last one is dropped
	$arrfocusnames=array_column($arrfocus, "name");
	if (in_array("DAT_FIRST_AIR", $arrfocusnames, true) && in_array("DAT_LAST_AIR", $arrfocusnames, true)) {
		$arrfocus=array_values(array_filter($arrfocus, function ($arrvalue) { return $arrvalue['name']!=="DAT_LAST_AIR"; }));
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
		"DAT_RELEASE"=>array("release date", "date de sortie", "f"),
		"DAT_FIRST_AIR"=>array("first aired", "première diffusion", "f"),
		"DAT_LAST_AIR"=>array("last aired", "dernière diffusion", "f"),
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
			//A film of a films-and-series union carries its release date as DAT_FIRST_AIR
			$strwordname=($arrvaluecol['name']=="DAT_FIRST_AIR" && isset($arrrecord['CONTENT_TYPE']) && $arrrecord['CONTENT_TYPE']=="movie") ? "DAT_RELEASE" : $arrvaluecol['name'];
			$arrword=f_t2srep_metric_word($strwordname, $strlang);
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

function f_t2srep_caption($jsontext2sql, $strlang='en', $inthasmore=false) {
	//Purpose: The caption of a list from `summary_facts`, the sentence the
	//			voice-agent speaks: count, order, one extremum. "Eight films in
	//			collection order; the longest is Harry Potter and the Order of the Phoenix, 2 h 18."
	//			A ranking says "the first N by <metric>" and never a total (decision of 2026-10-31:
	//			`total` is null in phase 1). "" with no summary_facts, fewer than two rows, or no
	//			value column: a plain card grid ("films by Kubrick") keeps no caption, as before,
	//			and its infinite scroll would soon make a count wrong anyway.
	//			$inthasmore: the page is full and another may follow; the count is then "the first
	//			N", as voice-agent says it (text2SqlSummaryLine(), hasMore).
	if (!is_array($jsontext2sql) || !isset($jsontext2sql['summary_facts']) || !is_array($jsontext2sql['summary_facts'])) {
		return "";
	}
	$arrfacts=$jsontext2sql['summary_facts'];
	$lngcount=isset($arrfacts['count']) && f_t2srep_is_number($arrfacts['count']) ? (int)$arrfacts['count'] : 0;
	$strvaluecolumn=isset($arrfacts['value_column']) ? (string)$arrfacts['value_column'] : "";
	//Phase 2 lot B: a fact stands on one row and a verdict on none, so they come before the
	//two-row floor of the lists
	$strrepb=isset($jsontext2sql['representation']) ? trim((string)$jsontext2sql['representation']) : "";
	if ($strrepb=="compare-cards") {
		return f_t2srep_caption_compare($jsontext2sql, $strlang);
	}
	if ($strrepb=="fact-card") {
		return f_t2srep_caption_fact($jsontext2sql, $strlang);
	}
	if ($strrepb=="verdict") {
		return f_t2srep_caption_verdict($jsontext2sql, $strlang);
	}
	//Phase 2 lot C: one share stands on one row, so the three come before the floor too
	if ($strrepb=="histogram") {
		return f_t2srep_caption_histogram($jsontext2sql, $strlang);
	}
	if ($strrepb=="share-bar") {
		return f_t2srep_caption_share($jsontext2sql, $strlang);
	}
	if ($strrepb=="flag-list") {
		return f_t2srep_caption_flags($jsontext2sql, $strlang, $inthasmore);
	}
	//Phase 3 lot A
	if ($strrepb=="scatter") {
		return f_t2srep_caption_scatter($jsontext2sql, $strlang, $inthasmore);
	}
	//Phase 3 lot B: the flag-list sentence, placed on the map
	if ($strrepb=="map") {
		return f_t2srep_caption_flags($jsontext2sql, $strlang, $inthasmore, true);
	}
	//Phase 3 lot C
	if ($strrepb=="hierarchy") {
		return f_t2srep_caption_hierarchy($jsontext2sql, $strlang, $inthasmore);
	}
	//Phase 3 lot E: a chain stands on any number of rows (the walk holds it, not the rows)
	if ($strrepb=="chain") {
		return f_t2srep_caption_chain($jsontext2sql, $strlang);
	}
	if ($lngcount<2) {
		return "";
	}
	//Phase 2 lot A: the three new forms have their own sentence (contract,
	//"Spoken" rows); a sequence has no value column and still gets one
	$strrep=isset($jsontext2sql['representation']) ? trim((string)$jsontext2sql['representation']) : "";
	if ($strrep=="timeline" || $strrep=="sequence-strip") {
		return f_t2srep_caption_dated($jsontext2sql, $strlang, $inthasmore);
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
		if ($inthasmore) {
			$strlead=$intfr ? "Les ".$strcount." ".($intfem ? "premières" : "premiers")." ".$arrnoun['plural'] : "The first ".$strcount." ".$arrnoun['plural'];
		} else {
			$strlead=f_t2srep_ucfirst($strcount)." ".$arrnoun['plural'];
		}
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

function f_t2srep_entity_media($arrrecord, $arrcolumns, $fnposter) {
	//Purpose: The title, page and poster (or face) of an entity row: from the poster callable of
	//			the page when it knows the row, else the label and the route of the id column
	//Returns: array("title"=>..., "href"=>..., "url"=>...), "" for what is missing
	$arrposter=is_callable($fnposter) ? $fnposter($arrrecord) : null;
	return array(
		"title"=>(is_array($arrposter) && isset($arrposter['title']) && $arrposter['title']!="") ? (string)$arrposter['title'] : f_t2srep_label_for_row($arrrecord, $arrcolumns),
		"href"=>(is_array($arrposter) && isset($arrposter['href']) && $arrposter['href']!="") ? (string)$arrposter['href'] : f_t2srep_entity_href($arrrecord),
		"url"=>(is_array($arrposter) && isset($arrposter['url'])) ? (string)$arrposter['url'] : "",
	);
}

function f_t2srep_mark($arrrecord, $arrcolumns, $fnposter, $strclass, $lngyear=null) {
	//Purpose: One entity of a timeline: its poster thumbnail (when it has one) and its title,
	//			linked to its page as a card is. $lngyear, when given, is written as data-t2syear:
	//			the script that appends the next pages (f_t2srep_timeline_more_js()) files the
	//			mark under its year and the caption counts the span from it.
	$arrmedia=f_t2srep_entity_media($arrrecord, $arrcolumns, $fnposter);
	$strtitle=$arrmedia['title'];
	$strhref=$arrmedia['href'];
	$strurl=$arrmedia['url'];
	$strinner=($strurl!="" ? "<img src=\"".f_t2srep_h($strurl)."\" alt=\"\" loading=\"lazy\">" : "<span class=\"t2sTimelineNoPoster\" aria-hidden=\"true\"></span>")
		."<span class=\"t2sTimelineTitle\">".f_t2srep_h($strtitle)."</span>";
	return "<li class=\"".$strclass."\"".($lngyear!==null ? " data-t2syear=\"".(int)$lngyear."\"" : "").">".($strhref!="" ? "<a href=\"".f_t2srep_h($strhref)."\" title=\"".f_t2srep_h($strtitle)."\">".$strinner."</a>" : $strinner)."</li>";
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
	$strlabel=str_replace(array("{a}", "{b}"), array($arryears[0], $arryears[count($arryears)-1]), f_t2srep_timeline_aria($strlang));
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
		$strhtml.="<li class=\"t2sTimelineYear\" data-t2syear=\"".(int)$lngyear."\"><span class=\"t2sTimelineYearLabel\">".f_t2srep_h($lngyear)."</span><ul class=\"t2sTimelineMarks\">";
		foreach ($arrindexes as $lngi) {
			$strhtml.=f_t2srep_mark($arrrecords[$lngi], $arrcolumns, $fnposter, "t2sTimelineMark", $lngyear);
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

function f_t2srep_timeline_aria($strlang='en') {
	//Purpose: The accessible name of the timeline axis, {a} and {b} the first and last year (the
	//			script that appends pages rewrites it from the same template)
	return $strlang=="fr" ? "Frise chronologique, de {a} à {b}" : "Timeline, from {a} to {b}";
}

function f_t2srep_render_timeline_page($jsontext2sql, $strlang='en', $fnposter=null, $inthasmore=false) {
	//Purpose: The marks of one more page of a timeline, for the script that appends them
	//			(f_t2srep_timeline_more_js()): a flat hidden list, each dated mark carrying its year
	//			in data-t2syear, the undated ones none, and data-has-more on the list. The date
	//			column is chosen as for the first page, so every page files its rows by the same date.
	$arrrecords=f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array());
	$arrcolumns=f_t2srep_columns($jsontext2sql);
	$arrfacts=isset($jsontext2sql['summary_facts']) && is_array($jsontext2sql['summary_facts']) ? $jsontext2sql['summary_facts'] : array();
	$strcolumn=f_t2srep_date_column($arrcolumns, isset($arrfacts['value_column']) ? (string)$arrfacts['value_column'] : "");
	$strhtml="<ul class=\"t2sTimelinePage\" data-has-more=\"".($inthasmore ? "1" : "0")."\" hidden>";
	foreach ($arrrecords as $arrrecord) {
		$lngyear=($strcolumn!="" && isset($arrrecord[$strcolumn])) ? f_t2srep_year($arrrecord[$strcolumn]) : null;
		$strhtml.=f_t2srep_mark($arrrecord, $arrcolumns, $fnposter, "t2sTimelineMark", $lngyear);
	}
	return $strhtml."</ul>";
}

function f_t2srep_timeline_more_js($arrconfig) {
	//Purpose: The script that completes a timeline. The API answers one page of rows at a time
	//			and the timeline draws what it has, so while it is shown this script fetches the
	//			next pages (the loadmore call of the card infinite scroll, with
	//			loadmoreview=timeline, answered by f_t2srep_render_timeline_page()) and files each
	//			mark under its year, opening a year column where needed and redrawing the gaps as
	//			f_t2srep_render_timeline() does. Like the cards, it loads up to `maxauto` pages on
	//			its own, then waits for a click on its button. A hidden timeline loads nothing; the
	//			chips fire a scroll event on a switch, which wakes it. After each page it asks the
	//			caption to recount (window.f_t2srepcaptionupdate, f_t2srep_caption_live_js()).
	//Params:	$arrconfig: page (the next page), maxauto, more, loading, suffix (the labels of the
	//			card loader), nodate (the undated heading), aria (f_t2srep_timeline_aria())
	//Returns:	A script with no PHP value outside the json_encode()d config
	return "(function(){var objcfg=".json_encode($arrconfig, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP).";"
		."var objbox=document.querySelector('.t2sRepView[data-t2sreps=\"timeline\"] .t2sTimeline');if(!objbox){return;}"
		."var objview=objbox.parentNode;var objaxis=objbox.querySelector('.t2sTimelineAxis');"
		."var objbutton=document.getElementById('t2sTimelineMore');var objstatus=document.getElementById('t2sTimelineStatus');"
		."var intloading=false;var inthasmore=true;var lngnext=objcfg.page;var lngauto=0;"
		."var objurl=new URL(window.location.href);objurl.searchParams.set('render','card');objurl.searchParams.set('loadmore','1');objurl.searchParams.set('loadmoreview','timeline');"
		."function f_count(){return objview.querySelectorAll('.t2sTimelineMark').length;}"
		."function f_status(){var strcount=' ('+f_count()+objcfg.suffix+')';if(objbutton){objbutton.textContent=objcfg.more+strcount;objbutton.style.display=(inthasmore&&!intloading&&lngauto>=objcfg.maxauto)?'inline-block':'none';}if(objstatus){objstatus.textContent=intloading?objcfg.loading+strcount:'';objstatus.style.display=intloading?'block':'none';}}"
		."function f_year(objitem){return parseInt(objitem.getAttribute('data-t2syear'),10);}"
		."function f_yearitem(lngyear){var arryears=objaxis.querySelectorAll('.t2sTimelineYear');var i;for(i=0;i<arryears.length;i++){var lng=f_year(arryears[i]);if(lng===lngyear){return arryears[i];}if(lng>lngyear){break;}}"
			."var objitem=document.createElement('li');objitem.className='t2sTimelineYear';objitem.setAttribute('data-t2syear',String(lngyear));var objlabel=document.createElement('span');objlabel.className='t2sTimelineYearLabel';objlabel.textContent=String(lngyear);var objmarks=document.createElement('ul');objmarks.className='t2sTimelineMarks';objitem.appendChild(objlabel);objitem.appendChild(objmarks);objaxis.insertBefore(objitem,i<arryears.length?arryears[i]:null);return objitem;}"
		."function f_gaps(){var arrgaps=objaxis.querySelectorAll('.t2sTimelineGap');var i;for(i=0;i<arrgaps.length;i++){objaxis.removeChild(arrgaps[i]);}var arryears=objaxis.querySelectorAll('.t2sTimelineYear');"
			."for(i=1;i<arryears.length;i++){var lnggap=f_year(arryears[i])-f_year(arryears[i-1])-1;if(lnggap<=0){continue;}var objgap=document.createElement('li');objgap.className='t2sTimelineGap'+(lnggap>8?' t2sTimelineBreak':'');objgap.setAttribute('aria-hidden','true');objgap.style.width=(Math.round(6*Math.min(lnggap,8))/10)+'rem';if(lnggap>8){var objdots=document.createElement('span');objdots.textContent='\\u2026';objgap.appendChild(objdots);}objaxis.insertBefore(objgap,arryears[i]);}"
			."if(arryears.length){objbox.setAttribute('aria-label',objcfg.aria.replace('{a}',f_year(arryears[0])).replace('{b}',f_year(arryears[arryears.length-1])));}}"
		."function f_undated(){var objundated=objview.querySelector('.t2sTimelineUndated');if(!objundated){objundated=document.createElement('div');objundated.className='t2sTimelineUndated';var objlabel=document.createElement('span');objlabel.className='t2sTimelineUndatedLabel';objlabel.textContent=objcfg.nodate;var objmarks=document.createElement('ul');objmarks.className='t2sTimelineMarks';objundated.appendChild(objlabel);objundated.appendChild(objmarks);objview.insertBefore(objundated,objbox.nextSibling);}return objundated.querySelector('.t2sTimelineMarks');}"
		."function f_merge(objpage){var arrmarks=objpage.querySelectorAll('.t2sTimelineMark');var i;for(i=0;i<arrmarks.length;i++){var stryear=arrmarks[i].getAttribute('data-t2syear');if(stryear){f_yearitem(parseInt(stryear,10)).querySelector('.t2sTimelineMarks').appendChild(arrmarks[i]);}else{f_undated().appendChild(arrmarks[i]);}}if(arrmarks.length){f_gaps();}return arrmarks.length;}"
		."function f_load(intauto){if(!inthasmore||intloading){return;}intloading=true;f_status();objurl.searchParams.set('page',String(lngnext));"
			."fetch(objurl.toString(),{credentials:'same-origin'}).then(function(r){return r.text();}).then(function(strhtml){var objwrap=document.createElement('div');objwrap.innerHTML=strhtml;var objpage=objwrap.querySelector('.t2sTimelinePage');if(!objpage){inthasmore=false;return;}var lngadded=f_merge(objpage);inthasmore=lngadded>0&&objpage.getAttribute('data-has-more')==='1';lngnext+=1;if(intauto){lngauto+=1;}})"
			.".catch(function(){inthasmore=false;}).then(function(){intloading=false;f_status();if(window.f_t2srepcaptionupdate){window.f_t2srepcaptionupdate('timeline',inthasmore);}f_maybe();});}"
		."function f_maybe(){if(!inthasmore||intloading){return;}if(objbox.offsetParent===null){return;}if(lngauto>=objcfg.maxauto){f_status();return;}f_load(true);}"
		."if(objbutton){objbutton.addEventListener('click',function(){lngauto=0;f_load(false);});}"
		."window.addEventListener('scroll',f_maybe,{passive:true});f_status();f_maybe();"
		."})();";
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

function f_t2srep_render_trend_columns($arrrecords, $arrcolumns, $strlang='en', $arrfacts=array(), $strquestion="") {
	//Purpose: The trend: one vertical column per row, in row order (a missing
	//			period is not filled in), x the period ("1980s" / "années 1980" for a decade),
	//			height the metric, the value formatted by its unit. Pure HTML and CSS; each column
	//			carries its period and value as text, so a screen reader reads "1980s: 11". Over
	//			twelve columns the values above the bars are hidden from sight (still read, and in
	//			the tooltip), and the extremum keeps its value. With the question, a column is a link
	//			to its follow-up (drill-down): "... list those whose release year is 2027."
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
		$strinner="<span class=\"t2sTrendValue\">".f_t2srep_h($strvalue)."</span>"
			."<span class=\"t2sTrendTrack\" aria-hidden=\"true\"><span class=\"t2sTrendBar\" style=\"height:".$lngpct."%\"></span></span>"
			."<span class=\"t2sTrendLabel\">".f_t2srep_h($strperiodlabel)."</span>";
		$strfollowup=f_t2srep_drill_question($strquestion, f_t2srep_drill_word($strperiod, $strlang),
			f_t2srep_drill_period_value($lngyear, $intdecade, isset($arrrecord[$strperiod]) ? $arrrecord[$strperiod] : "", $strlang), $strlang);
		if ($strfollowup!=="") {
			$strinner=f_t2srep_drill_link(f_t2srep_drill_href($strfollowup), $strinner, "t2sDrill", $strperiodlabel.($intfr ? " : " : ": ").$strvalue.". ".$strfollowup);
		}
		$strhtml.="<li class=\"t2sTrendCol".($lngi==$lngpeak ? " t2sTrendPeak" : "")."\" title=\"".f_t2srep_h($strperiodlabel.": ".$strvalue)."\">".$strinner."</li>";
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

function f_t2srep_caption_spec($jsontext2sql, $strlang='en') {
	//Purpose: The pieces of the caption of a timeline or a sequence, everything but the numbers:
	//			PHP fills them with the first page (f_t2srep_caption_fill()), the page script with
	//			every row on screen once more pages have come in (f_t2srep_caption_fill_js()), so the
	//			two always say the same sentence.
	//			Timeline: count and span ("Four films, from 1979 to 1997."), the undated rows counted
	//			apart. Sequence: count, order and span ("Three films in release order, from 1964 to
	//			1966."), the span reversed when the most recent comes first.
	//Returns: array or null when the answer is neither a timeline nor a sequence
	if (!is_array($jsontext2sql) || !isset($jsontext2sql['summary_facts']) || !is_array($jsontext2sql['summary_facts'])) {
		return null;
	}
	$strrep=isset($jsontext2sql['representation']) ? trim((string)$jsontext2sql['representation']) : "";
	if ($strrep!="timeline" && $strrep!="sequence-strip") {
		return null;
	}
	$arrfacts=$jsontext2sql['summary_facts'];
	$arrrecords=f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array());
	$arrcolumns=f_t2srep_columns($jsontext2sql);
	$arrnoun=f_t2srep_noun($arrrecords, $arrcolumns, $strlang);
	$intfr=($strlang=="fr");
	$arrwords=array();
	for ($lngi=0; $lngi<=12; $lngi++) {
		$arrwords[]=f_t2srep_count_word($lngi, $strlang, $arrnoun['gender']);
	}
	$arrspec=array(
		"rep"=>$strrep,
		"words"=>$arrwords,
		"all"=>"{N} ".$arrnoun['plural'],
		"first"=>$intfr ? "Les {n} ".($arrnoun['gender']=="f" ? "premières" : "premiers")." ".$arrnoun['plural'] : "The first {n} ".$arrnoun['plural'],
		"order"=>"",
		"desc"=>false,
		"range"=>$intfr ? ", de {a} à {b}" : ", from {a} to {b}",
		"one"=>$intfr ? ", en {a}" : ", in {a}",
		"undated"=>"",
		"column"=>"",
	);
	if ($strrep=="timeline") {
		$arrspec['column']=f_t2srep_date_column($arrcolumns, isset($arrfacts['value_column']) ? (string)$arrfacts['value_column'] : "");
		$arrspec['undated']=$intfr ? " ; {u} sans date" : "; {u} without a date";
		return $arrspec;
	}
	$strorder=isset($arrfacts['order']) ? (string)$arrfacts['order'] : "";
	$strdirection=isset($arrfacts['order_direction']) ? strtolower((string)$arrfacts['order_direction']) : "";
	$arrspec['column']=f_t2srep_date_column($arrcolumns);
	if ($strorder=="collection") {
		$arrspec['order']=$intfr ? " dans l'ordre de la collection" : " in collection order";
	} elseif ($strorder=="date" && $strdirection=="desc") {
		$arrspec['order']=$intfr ? ", du plus récent au plus ancien" : ", most recent first";
		$arrspec['desc']=true;
	} elseif ($strorder=="date") {
		$intrelease=preg_match('/RELEASE|FIRST_AIR/', strtoupper($arrspec['column']))===1;
		$arrspec['order']=$intrelease ? ($intfr ? " dans l'ordre de sortie" : " in release order") : ($intfr ? " dans l'ordre chronologique" : " in chronological order");
	}
	return $arrspec;
}

function f_t2srep_caption_fill($arrspec, $lngcount, $lngmin, $lngmax, $lngundated, $inthasmore=false) {
	//Purpose: The caption of a timeline or a sequence from its pieces and its numbers; mirrored
	//			line for line by f_t2srep_caption_fill_js()
	//Params:	$lngmin, $lngmax: the first and last year (null with no dated row); $lngundated: the
	//			rows with no date; $inthasmore: another page may follow, the count is "the first N"
	$fnword=function ($lngn) use ($arrspec) {
		return ($lngn>=0 && $lngn<=12) ? $arrspec['words'][$lngn] : number_format($lngn, 0, '.', ',');
	};
	$strtext=$inthasmore ? str_replace("{n}", $fnword($lngcount), $arrspec['first']) : str_replace("{N}", f_t2srep_ucfirst($fnword($lngcount)), $arrspec['all']);
	$strtext.=$arrspec['order'];
	if ($lngmin!==null) {
		if ($arrspec['desc'] && $lngmin!=$lngmax) {
			$strtext.=str_replace(array("{a}", "{b}"), array($lngmax, $lngmin), $arrspec['range']);
		} elseif ($lngmin==$lngmax) {
			$strtext.=str_replace("{a}", $lngmin, $arrspec['one']);
		} else {
			$strtext.=str_replace(array("{a}", "{b}"), array($lngmin, $lngmax), $arrspec['range']);
		}
		if ($arrspec['undated']!="" && $lngundated>0) {
			$strtext.=str_replace("{u}", $fnword($lngundated), $arrspec['undated']);
		}
	}
	return $strtext.".";
}

function f_t2srep_caption_fill_js() {
	//Purpose: f_t2srep_caption_fill() in JavaScript, for the page script that recounts the caption
	//			when more pages come in. A plain string, so the test can run it against the PHP.
	return "function f_t2srepcaptionfill(objspec,lngcount,lngmin,lngmax,lngundated,inthasmore){"
		."function f_word(lngn){return (lngn>=0&&lngn<=12)?objspec.words[lngn]:String(lngn).replace(/\\B(?=(\\d{3})+(?!\\d))/g,',');}"
		."var strtext=inthasmore?objspec.first.replace('{n}',f_word(lngcount)):objspec.all.replace('{N}',f_word(lngcount).charAt(0).toUpperCase()+f_word(lngcount).slice(1));"
		."strtext+=objspec.order;"
		."if(lngmin!==null){if(objspec.desc&&lngmin!==lngmax){strtext+=objspec.range.replace('{a}',lngmax).replace('{b}',lngmin);}else if(lngmin===lngmax){strtext+=objspec.one.replace('{a}',lngmin);}else{strtext+=objspec.range.replace('{a}',lngmin).replace('{b}',lngmax);}"
		."if(objspec.undated!==''&&lngundated>0){strtext+=objspec.undated.replace('{u}',f_word(lngundated));}}"
		."return strtext+'.';}";
}

function f_t2srep_caption_dated($jsontext2sql, $strlang='en', $inthasmore=false) {
	//Purpose: The caption of a timeline or a sequence for the rows of the answer
	$arrspec=f_t2srep_caption_spec($jsontext2sql, $strlang);
	if ($arrspec===null) {
		return "";
	}
	$arrrecords=f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array());
	$arrspan=f_t2srep_year_span($arrrecords, $arrspec['column']);
	return f_t2srep_caption_fill($arrspec, (int)$jsontext2sql['summary_facts']['count'], $arrspan['min'], $arrspan['max'], $arrspan['undated'], $inthasmore);
}

function f_t2srep_caption_live_js($arrspec) {
	//Purpose: The script that keeps the caption of a timeline or a sequence true while more pages
	//			come in: it defines window.f_t2srepcaptionupdate(strrep, inthasmore), which the
	//			timeline loader (f_t2srep_timeline_more_js()) and the card loader of the search page
	//			call after each page. It counts what is on screen (the marks of the timeline, the
	//			cards of the grid) and the span from their data-t2syear, then refills the sentence.
	//			A call for the other form is ignored: the caption describes the default one.
	return "(function(){var objspec=".json_encode($arrspec, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP).";"
		.f_t2srep_caption_fill_js()
		."window.f_t2srepcaptionupdate=function(strrep,inthasmore){if(strrep!==objspec.rep){return;}var objcaption=document.getElementById('t2sCaption');if(!objcaption){return;}"
		."var arritems=strrep==='timeline'?document.querySelectorAll('.t2sRepView[data-t2sreps=\"timeline\"] .t2sTimelineMark'):document.querySelectorAll('#search-poster-card-grid .search-poster-card');"
		."var lngmin=null;var lngmax=null;var lngundated=0;var i;for(i=0;i<arritems.length;i++){var lngyear=parseInt(arritems[i].getAttribute('data-t2syear')||'',10);if(isNaN(lngyear)){lngundated++;continue;}lngmin=lngmin===null?lngyear:Math.min(lngmin,lngyear);lngmax=lngmax===null?lngyear:Math.max(lngmax,lngyear);}"
		."if(arritems.length<2){return;}objcaption.textContent=f_t2srepcaptionfill(objspec,arritems.length,lngmin,lngmax,lngundated,inthasmore);};"
		."})();";
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

/* ---------------------------------------------------------------------------------------------
 * Phase 2 lot B: compare-cards, fact-card, verdict. The contract is the fastapi-text2sql README,
 * section "Result representations: the contract for both fronts", and the facts are those of
 * summary_facts (winners, fact, verdict and verdict_basis), never recomputed here when present.
 * ------------------------------------------------------------------------------------------- */

function f_t2srep_format_date($value, $strlang='en') {
	//Purpose: A date cell as a person reads it: "28 May 1958" / "28 mai 1958" ("1er mai" in French),
	//			"May 1958" without a day, a year column ("1910", 1910) as its year; "" when blank or
	//			zero, any other text as is
	if ($value===null || is_bool($value) || is_array($value) || trim((string)$value)==="") {
		return "";
	}
	$strvalue=trim((string)$value);
	if (f_t2srep_is_number($strvalue)) {
		$lngyear=(int)round((float)$strvalue);
		return $lngyear!=0 ? (string)$lngyear : "";
	}
	if (!preg_match('/^(-?\d{1,4})-(\d{1,2})(?:-(\d{1,2}))?/', $strvalue, $arrmatch)) {
		return $strvalue;
	}
	$lngyear=(int)$arrmatch[1];
	$lngmonth=(int)$arrmatch[2];
	$lngday=isset($arrmatch[3]) ? (int)$arrmatch[3] : 0;
	if ($lngyear==0) {
		return "";
	}
	if ($lngmonth<1 || $lngmonth>12) {
		return (string)$lngyear;
	}
	$arrmonths=$strlang=="fr"
		? array("janvier", "février", "mars", "avril", "mai", "juin", "juillet", "août", "septembre", "octobre", "novembre", "décembre")
		: array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
	$strmonth=$arrmonths[$lngmonth-1];
	if ($lngday<1) {
		return $strmonth." ".$lngyear;
	}
	return ($strlang=="fr" && $lngday==1 ? "1er" : (string)$lngday)." ".$strmonth." ".$lngyear;
}

function f_t2srep_quantity_value($value, $strunit, $strrole='metric', $strlang='en') {
	//Purpose: A compared or asked-for quantity, short: a date as f_t2srep_format_date(), a metric as
	//			f_t2srep_format_value() ("3 h 14", "$2,923,706,026"); "" when missing (a 0 runtime too)
	if ($strrole=="date") {
		return f_t2srep_format_date($value, $strlang);
	}
	return f_t2srep_format_value($value, $strunit, $strrole, $strlang);
}

function f_t2srep_quantity_word($strname, $arrrecord=array(), $strlang='en') {
	//Purpose: The name of a quantity for one row: f_t2srep_metric_word(), a film of a films-and-series
	//			union carrying its release date as DAT_FIRST_AIR
	$strwordname=(strtoupper((string)$strname)=="DAT_FIRST_AIR" && is_array($arrrecord) && isset($arrrecord['CONTENT_TYPE']) && $arrrecord['CONTENT_TYPE']=="movie") ? "DAT_RELEASE" : $strname;
	return f_t2srep_metric_word($strwordname, $strlang);
}

function f_t2srep_is_date_part($strname) {
	//Purpose: Tells whether a column is the month or the day of a date (BIRTH_MONTH, BIRTH_DAY), which
	//			the API marks `focus` beside the year but no front compares or names on its own
	return preg_match('/_(MONTH|DAY)$/', strtoupper((string)$strname))===1;
}

function f_t2srep_compare_quantities($arrcolumns, $arrfacts=array()) {
	//Purpose: The quantities compare-cards puts under each card, at most two: one per entry of
	//			summary_facts.winners, with its winner; with no winners (an older answer), the value
	//			columns without a date part, and no winner
	//Returns: array of array("name", "unit", "role", "winner"=>array|null)
	$arrbyname=array();
	foreach ($arrcolumns as $arrcol) {
		if (is_array($arrcol) && isset($arrcol['name'])) {
			$arrbyname[(string)$arrcol['name']]=$arrcol;
		}
	}
	$arrquantities=array();
	if (is_array($arrfacts) && isset($arrfacts['winners']) && is_array($arrfacts['winners'])) {
		foreach ($arrfacts['winners'] as $arrwinner) {
			if (!is_array($arrwinner) || !isset($arrwinner['column']) || trim((string)$arrwinner['column'])==="") {
				continue;
			}
			$strname=(string)$arrwinner['column'];
			$arrcol=isset($arrbyname[$strname]) ? $arrbyname[$strname] : array();
			$strrole=(isset($arrwinner['role']) && $arrwinner['role']!==null) ? (string)$arrwinner['role'] : (isset($arrcol['role']) ? (string)$arrcol['role'] : "metric");
			$strunit=(isset($arrwinner['unit']) && $arrwinner['unit']!==null) ? $arrwinner['unit'] : (isset($arrcol['unit']) ? $arrcol['unit'] : null);
			$arrquantities[]=array("name"=>$strname, "unit"=>$strunit, "role"=>$strrole, "winner"=>$arrwinner);
		}
	}
	if (count($arrquantities)==0) {
		foreach (f_t2srep_value_columns($arrcolumns) as $arrvalue) {
			if (!f_t2srep_is_date_part($arrvalue['name'])) {
				$arrquantities[]=array("name"=>$arrvalue['name'], "unit"=>$arrvalue['unit'], "role"=>$arrvalue['role'], "winner"=>null);
			}
		}
	}
	return array_slice($arrquantities, 0, 2);
}

function f_t2srep_winner_index($arrwinner, $lngcount) {
	//Purpose: The row a winner marks, or -1 on a tie, a date without direction or a bad index
	if (!is_array($arrwinner) || !empty($arrwinner['tie']) || !isset($arrwinner['index']) || !f_t2srep_is_number($arrwinner['index'])) {
		return -1;
	}
	$lngindex=(int)$arrwinner['index'];
	return ($lngindex>=0 && $lngindex<$lngcount) ? $lngindex : -1;
}

function f_t2srep_winner_badge($strextreme, $strlang='en') {
	//Purpose: The text of the badge on a winning value: the mark is a word, never a colour alone
	$intfr=($strlang=="fr");
	switch ((string)$strextreme) {
	case "earliest":
		return $intfr ? "En premier" : "First";
	case "latest":
		return $intfr ? "En dernier" : "Last";
	case "min":
		return $intfr ? "Le plus bas" : "Lowest";
	default:
		return $intfr ? "En tête" : "Leads";
	}
}

function f_t2srep_compare_noun($arrrecords, $arrcolumns, $strlang='en') {
	//Purpose: What compare-cards and the homonyms of a fact speak of: films and series are "titles"
	//			(the contract's wording, a film compared with a series included), people are people
	//Returns: array("plural", "singular", "gender")
	$arrnoun=f_t2srep_noun($arrrecords, $arrcolumns, $strlang);
	$intfr=($strlang=="fr");
	if (in_array($arrnoun['plural'], array("films", "series", "titles", "séries", "titres"), true)) {
		return array("plural"=>$intfr ? "titres" : "titles", "singular"=>$intfr ? "titre" : "title", "gender"=>"m");
	}
	if ($arrnoun['plural']=="people" || $arrnoun['plural']=="personnes") {
		return array("plural"=>$arrnoun['plural'], "singular"=>$intfr ? "personne" : "person", "gender"=>"f");
	}
	return array("plural"=>$arrnoun['plural'], "singular"=>rtrim($arrnoun['plural'], "s"), "gender"=>$arrnoun['gender']);
}

function f_t2srep_compare_lead($arrrecords, $arrcolumns, $strlang='en') {
	//Purpose: "Two titles side by side" / "Deux titres côte à côte", "Two people side by side"
	$arrnoun=f_t2srep_compare_noun($arrrecords, $arrcolumns, $strlang);
	$lngcount=min(4, count($arrrecords));
	return f_t2srep_ucfirst(f_t2srep_count_word($lngcount, $strlang, $arrnoun['gender']))." ".$arrnoun['plural'].($strlang=="fr" ? " côte à côte" : " side by side");
}

function f_t2srep_render_compare_cards($arrrecords, $arrcolumns, $strlang='en', $fnposter=null, $arrfacts=array()) {
	//Purpose: Two to four entities side by side, poster or face linked to their page as the cards
	//			are, the compared quantity (or two) under each card formatted by its unit, the winner
	//			of each quantity outlined and badged with a word. A tie marks no card and says so
	//			under the cards. Four cards make two rows of two on a phone.
	//Params:	$fnposter: see f_t2srep_render_ranked_bars(); $arrfacts: summary_facts (winners)
	$arrrecords=array_slice(array_values($arrrecords), 0, 4);
	$arrquantities=f_t2srep_compare_quantities($arrcolumns, $arrfacts);
	if (count($arrrecords)==0 || count($arrquantities)==0) {
		return f_t2srep_render_table($arrrecords, $arrcolumns, $strlang);
	}
	$intfr=($strlang=="fr");
	//The year under the title tells two homonyms apart, unless a date is what is compared
	$strdatecolumn=f_t2srep_date_column($arrcolumns);
	if (in_array($strdatecolumn, array_column($arrquantities, "name"), true)) {
		$strdatecolumn="";
	}
	$strhtml="<ul class=\"t2sCompare t2sCompareN".count($arrrecords)."\" aria-label=\"".f_t2srep_h(f_t2srep_compare_lead($arrrecords, $arrcolumns, $strlang))."\">";
	foreach ($arrrecords as $lngi=>$arrrecord) {
		$arrmedia=f_t2srep_entity_media($arrrecord, $arrcolumns, $fnposter);
		$intwinner=false;
		$strvalues="";
		foreach ($arrquantities as $arrquantity) {
			$intwin=(f_t2srep_winner_index($arrquantity['winner'], count($arrrecords))===$lngi);
			$intwinner=$intwinner || $intwin;
			$value=array_key_exists($arrquantity['name'], $arrrecord) ? $arrrecord[$arrquantity['name']] : null;
			$strvalue=f_t2srep_quantity_value($value, $arrquantity['unit'], $arrquantity['role'], $strlang);
			$arrword=f_t2srep_quantity_word($arrquantity['name'], $arrrecord, $strlang);
			$strvalues.="<div class=\"t2sCompareQty".($intwin ? " t2sCompareWin" : "")."\"><span class=\"t2sCompareQtyName\">".f_t2srep_h(f_t2srep_ucfirst($arrword['label']))."</span>"
				."<span class=\"t2sCompareQtyValue\">".($strvalue!=="" ? f_t2srep_h($strvalue) : "<span class=\"t2sCompareNone\">".($intfr ? "Non renseigné" : "Not known")."</span>")."</span>"
				.($intwin ? "<span class=\"t2sCompareBadge\">".f_t2srep_h(f_t2srep_winner_badge(isset($arrquantity['winner']['extreme']) ? $arrquantity['winner']['extreme'] : "", $strlang))."</span>" : "")
				."</div>";
		}
		$lngyear=($strdatecolumn!="" && isset($arrrecord[$strdatecolumn])) ? f_t2srep_year($arrrecord[$strdatecolumn]) : null;
		$strinner="<span class=\"t2sCompareMedia\">".($arrmedia['url']!="" ? "<img src=\"".f_t2srep_h($arrmedia['url'])."\" alt=\"\" loading=\"lazy\">" : "<span class=\"t2sCompareNoPoster\" aria-hidden=\"true\"></span>")."</span>"
			."<span class=\"t2sCompareTitle\">".f_t2srep_h($arrmedia['title'])."</span>";
		$strhtml.="<li class=\"t2sCompareCard".($intwinner ? " t2sCompareWinner" : "")."\">"
			.($arrmedia['href']!="" ? "<a class=\"t2sCompareLink\" href=\"".f_t2srep_h($arrmedia['href'])."\">".$strinner."</a>" : "<span class=\"t2sCompareLink\">".$strinner."</span>")
			.($lngyear!==null ? "<span class=\"t2sCompareYear\">".(int)$lngyear."</span>" : "")
			."<div class=\"t2sCompareValues\">".$strvalues."</div></li>";
	}
	$strhtml.="</ul>";
	foreach ($arrquantities as $arrquantity) {
		if (is_array($arrquantity['winner']) && !empty($arrquantity['winner']['tie'])) {
			$strhtml.="<p class=\"t2sCompareTie\">".f_t2srep_h(($intfr ? "Égalité : " : "Tie: ").f_t2srep_compare_tie_words($arrquantity, $arrrecords, $strlang))."</p>";
		}
	}
	return $strhtml;
}

function f_t2srep_compare_tie_words($arrquantity, $arrrecords, $strlang='en') {
	//Purpose: "same runtime, 2 h 02" / "même durée, 2 h 02"
	$arrrecord=count($arrrecords)>0 ? $arrrecords[0] : array();
	$arrword=f_t2srep_quantity_word($arrquantity['name'], $arrrecord, $strlang);
	$value=(is_array($arrquantity['winner']) && isset($arrquantity['winner']['value']) && $arrquantity['winner']['value']!==null) ? $arrquantity['winner']['value'] : (array_key_exists($arrquantity['name'], $arrrecord) ? $arrrecord[$arrquantity['name']] : null);
	$strvalue=f_t2srep_quantity_value($value, $arrquantity['unit'], $arrquantity['role'], $strlang);
	return ($strlang=="fr" ? "même " : "same ").$arrword['label'].($strvalue!=="" ? ", ".$strvalue : "");
}

function f_t2srep_compare_winner_words($arrquantity, $arrrecords, $arrcolumns, $strlang='en') {
	//Purpose: The sentence of one compared quantity: "Titanic leads on runtime, 3 h 14", "Charlie
	//			Chaplin comes first (1889)", "same number of seasons, 5"; "" when nothing is won (a
	//			date without direction)
	$arrwinner=$arrquantity['winner'];
	if (!is_array($arrwinner)) {
		return "";
	}
	if (!empty($arrwinner['tie'])) {
		return f_t2srep_compare_tie_words($arrquantity, $arrrecords, $strlang);
	}
	$lngindex=f_t2srep_winner_index($arrwinner, count($arrrecords));
	if ($lngindex<0) {
		return "";
	}
	$intfr=($strlang=="fr");
	$arrrecord=$arrrecords[$lngindex];
	$strlabel=(isset($arrwinner['label']) && trim((string)$arrwinner['label'])!=="") ? trim((string)$arrwinner['label']) : f_t2srep_label_for_row($arrrecord, $arrcolumns);
	$value=(isset($arrwinner['value']) && $arrwinner['value']!==null) ? $arrwinner['value'] : (array_key_exists($arrquantity['name'], $arrrecord) ? $arrrecord[$arrquantity['name']] : null);
	$strvalue=f_t2srep_quantity_value($value, $arrquantity['unit'], $arrquantity['role'], $strlang);
	$arrword=f_t2srep_quantity_word($arrquantity['name'], $arrrecord, $strlang);
	$strextreme=isset($arrwinner['extreme']) ? (string)$arrwinner['extreme'] : "";
	if ($strextreme=="earliest" || $strextreme=="latest") {
		$strtext=$strlabel.($strextreme=="earliest" ? ($intfr ? " vient en premier" : " comes first") : ($intfr ? " vient en dernier" : " comes last"));
		return $strtext.($strvalue!=="" ? " (".$strvalue.")" : "");
	}
	if ($strextreme=="min") {
		if ($intfr) {
			$strarticle=preg_match('/^[aeiouyéèêàâîôû]/iu', $arrword['label']) ? "l'" : ($arrword['gender']=="f" ? "la " : "le ");
			$strtext=$strlabel." a ".$strarticle.$arrword['label']." ".($arrword['gender']=="f" ? "la plus basse" : "le plus bas");
		} else {
			$strtext=$strlabel." has the lowest ".$arrword['label'];
		}
	} else {
		$strtext=$strlabel.($intfr ? " l'emporte en " : " leads on ").$arrword['label'];
	}
	return $strtext.($strvalue!=="" ? ", ".$strvalue : "");
}

function f_t2srep_caption_compare($jsontext2sql, $strlang='en') {
	//Purpose: The caption of compare-cards: the count and each winner ("Two titles side by side;
	//			Titanic leads on runtime, 3 h 14." / "Deux titres côte à côte ; Titanic l'emporte en
	//			durée, 3 h 14."), a tie said as such ("same number of seasons, 5")
	$arrrecords=array_slice(f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array()), 0, 4);
	if (count($arrrecords)==0) {
		return "";
	}
	$arrcolumns=f_t2srep_columns($jsontext2sql);
	$arrfacts=isset($jsontext2sql['summary_facts']) && is_array($jsontext2sql['summary_facts']) ? $jsontext2sql['summary_facts'] : array();
	$arrparts=array();
	foreach (f_t2srep_compare_quantities($arrcolumns, $arrfacts) as $arrquantity) {
		$strpart=f_t2srep_compare_winner_words($arrquantity, $arrrecords, $arrcolumns, $strlang);
		if ($strpart!=="") {
			$arrparts[]=$strpart;
		}
	}
	$strsep=$strlang=="fr" ? " ; " : "; ";
	return f_t2srep_compare_lead($arrrecords, $arrcolumns, $strlang).(count($arrparts)>0 ? $strsep.implode($strsep, $arrparts) : "").".";
}

function f_t2srep_fact($arrrecords, $arrcolumns, $arrfacts=array()) {
	//Purpose: The one fact of a fact-card: summary_facts.fact, else (an older answer) the first value
	//			column of the first row
	//Returns: array("index", "column", "unit", "role", "value", "label", "others") or null
	$lngcount=count($arrrecords);
	if ($lngcount==0) {
		return null;
	}
	$arrbyname=array();
	foreach ($arrcolumns as $arrcol) {
		if (is_array($arrcol) && isset($arrcol['name'])) {
			$arrbyname[(string)$arrcol['name']]=$arrcol;
		}
	}
	if (is_array($arrfacts) && isset($arrfacts['fact']) && is_array($arrfacts['fact']) && isset($arrfacts['fact']['column']) && trim((string)$arrfacts['fact']['column'])!=="") {
		$arrfact=$arrfacts['fact'];
		$strname=(string)$arrfact['column'];
		$arrcol=isset($arrbyname[$strname]) ? $arrbyname[$strname] : array();
		$lngindex=(isset($arrfact['index']) && f_t2srep_is_number($arrfact['index']) && (int)$arrfact['index']>=0 && (int)$arrfact['index']<$lngcount) ? (int)$arrfact['index'] : 0;
		return array(
			"index"=>$lngindex,
			"column"=>$strname,
			"unit"=>(isset($arrfact['unit']) && $arrfact['unit']!==null) ? $arrfact['unit'] : (isset($arrcol['unit']) ? $arrcol['unit'] : null),
			"role"=>(isset($arrfact['role']) && $arrfact['role']!==null) ? (string)$arrfact['role'] : (isset($arrcol['role']) ? (string)$arrcol['role'] : "metric"),
			"value"=>(isset($arrfact['value']) && $arrfact['value']!==null) ? $arrfact['value'] : (isset($arrrecords[$lngindex][$strname]) ? $arrrecords[$lngindex][$strname] : null),
			"label"=>isset($arrfact['label']) ? trim((string)$arrfact['label']) : "",
			"others"=>(isset($arrfact['others']) && f_t2srep_is_number($arrfact['others'])) ? max(0, (int)$arrfact['others']) : $lngcount-1,
		);
	}
	foreach (f_t2srep_value_columns($arrcolumns) as $arrvalue) {
		if (!f_t2srep_is_date_part($arrvalue['name'])) {
			return array("index"=>0, "column"=>$arrvalue['name'], "unit"=>$arrvalue['unit'], "role"=>$arrvalue['role'],
				"value"=>isset($arrrecords[0][$arrvalue['name']]) ? $arrrecords[0][$arrvalue['name']] : null, "label"=>"", "others"=>$lngcount-1);
		}
	}
	return null;
}

function f_t2srep_fact_name($strname, $arrrecord=array(), $strlang='en') {
	//Purpose: The name of a fact on its card: "Runtime", "Release date", "Born" / "Durée",
	//			"Date de sortie", "Naissance"
	$arrnames=array("BIRTH_YEAR"=>array("Born", "Naissance"), "DEATH_YEAR"=>array("Died", "Décès"));
	$strkey=strtoupper((string)$strname);
	if (isset($arrnames[$strkey])) {
		return $arrnames[$strkey][$strlang=="fr" ? 1 : 0];
	}
	$arrword=f_t2srep_quantity_word($strname, $arrrecord, $strlang);
	return f_t2srep_ucfirst($arrword['label']);
}

function f_t2srep_others_words($lngothers, $arrrecords, $arrcolumns, $strlang='en') {
	//Purpose: "11 other titles with this name" / "11 autres titres portent ce nom", singular for one
	$arrnoun=f_t2srep_compare_noun($arrrecords, $arrcolumns, $strlang);
	$intone=((int)$lngothers==1);
	$strcount=number_format((int)$lngothers, 0, '.', ',');
	if ($strlang=="fr") {
		return $strcount." ".($intone ? "autre ".$arrnoun['singular']." porte" : "autres ".$arrnoun['plural']." portent")." ce nom";
	}
	return $strcount." other ".($intone ? $arrnoun['singular'] : $arrnoun['plural'])." with this name";
}

function f_t2srep_render_fact_card($arrrecords, $arrcolumns, $strlang='en', $fnposter=null, $arrfacts=array()) {
	//Purpose: One entity and the one value the question asks about, named large ("Runtime: 3 h 48",
	//			"Release date: 28 May 1958", "Born: 1910"), its poster or face and its title linked to
	//			its page; the other rows (homonyms of the title, the default sort chose the first)
	//			listed below, compact, each linked to its own page
	$arrrecords=array_values($arrrecords);
	$arrfact=f_t2srep_fact($arrrecords, $arrcolumns, $arrfacts);
	if ($arrfact===null) {
		return f_t2srep_render_table($arrrecords, $arrcolumns, $strlang);
	}
	$intfr=($strlang=="fr");
	$arrrecord=$arrrecords[$arrfact['index']];
	$arrmedia=f_t2srep_entity_media($arrrecord, $arrcolumns, $fnposter);
	$strvalue=f_t2srep_quantity_value($arrfact['value'], $arrfact['unit'], $arrfact['role'], $strlang);
	$strname=f_t2srep_fact_name($arrfact['column'], $arrrecord, $strlang);
	//The year of the row, unless the fact is that date; the homonyms show theirs
	$strdatecolumn=f_t2srep_date_column($arrcolumns);
	$lngyear=($strdatecolumn!="" && $strdatecolumn!=$arrfact['column'] && isset($arrrecord[$strdatecolumn])) ? f_t2srep_year($arrrecord[$strdatecolumn]) : null;
	$strhref=$arrmedia['href'];
	$strmedia=$arrmedia['url']!="" ? "<img src=\"".f_t2srep_h($arrmedia['url'])."\" alt=\"\" loading=\"lazy\">" : "<span class=\"t2sFactNoPoster\"></span>";
	//The poster repeats the title link: kept out of the keyboard order and the screen reader
	$strhtml="<div class=\"t2sFact\"><div class=\"t2sFactMain\">"
		.($strhref!="" ? "<a class=\"t2sFactMedia\" href=\"".f_t2srep_h($strhref)."\" tabindex=\"-1\" aria-hidden=\"true\">".$strmedia."</a>" : "<span class=\"t2sFactMedia\" aria-hidden=\"true\">".$strmedia."</span>")
		."<div class=\"t2sFactBody\"><div class=\"t2sFactLabel\">".($strhref!="" ? "<a href=\"".f_t2srep_h($strhref)."\">".f_t2srep_h($arrmedia['title'])."</a>" : f_t2srep_h($arrmedia['title']))."</div>"
		.($lngyear!==null ? "<div class=\"t2sFactYear\">".(int)$lngyear."</div>" : "")
		."<div class=\"t2sFactValue\"><span class=\"t2sFactName\">".f_t2srep_h($strname).($intfr ? " :" : ":")."</span> "
		."<span class=\"t2sFactNumber\">".($strvalue!=="" ? f_t2srep_h($strvalue) : "<span class=\"t2sCompareNone\">".($intfr ? "Non renseigné" : "Not known")."</span>")."</span></div>"
		."</div></div>";
	if ($arrfact['others']>0 && count($arrrecords)>1) {
		$strhtml.="<div class=\"t2sFactOthers\"><p class=\"t2sFactOthersTitle\">".f_t2srep_h(f_t2srep_others_words($arrfact['others'], $arrrecords, $arrcolumns, $strlang))."</p><ul class=\"t2sFactOthersList\">";
		foreach ($arrrecords as $lngi=>$arrother) {
			if ($lngi==$arrfact['index']) {
				continue;
			}
			$arrothermedia=f_t2srep_entity_media($arrother, $arrcolumns, null);
			$arrmeta=array();
			$lngotheryear=($strdatecolumn!="" && isset($arrother[$strdatecolumn])) ? f_t2srep_year($arrother[$strdatecolumn]) : null;
			if ($lngotheryear!==null) {
				$arrmeta[]=(string)$lngotheryear;
			}
			if ($arrfact['role']!="date") {
				$strothervalue=f_t2srep_quantity_value(isset($arrother[$arrfact['column']]) ? $arrother[$arrfact['column']] : null, $arrfact['unit'], $arrfact['role'], $strlang);
				if ($strothervalue!=="") {
					$arrmeta[]=$strothervalue;
				}
			}
			$strhtml.="<li>".($arrothermedia['href']!="" ? "<a href=\"".f_t2srep_h($arrothermedia['href'])."\">".f_t2srep_h($arrothermedia['title'])."</a>" : f_t2srep_h($arrothermedia['title']))
				.(count($arrmeta)>0 ? " <span class=\"t2sFactOthersMeta\">".f_t2srep_h(implode(" · ", $arrmeta))."</span>" : "")."</li>";
		}
		$strhtml.="</ul></div>";
	}
	return $strhtml."</div>";
}

function f_t2srep_caption_fact($jsontext2sql, $strlang='en') {
	//Purpose: The caption of a fact-card: "<label>: <quantity word>, <value>" ("Lawrence of Arabia:
	//			runtime, 3 h 48." / "Lawrence d'Arabie : durée, 3 h 48."), plus "(11 other titles
	//			with this name)" / "(11 autres titres portent ce nom)" when there are homonyms
	$arrrecords=f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array());
	$arrcolumns=f_t2srep_columns($jsontext2sql);
	$arrfact=f_t2srep_fact($arrrecords, $arrcolumns, $jsontext2sql['summary_facts']);
	if ($arrfact===null) {
		return "";
	}
	$arrrecord=$arrrecords[$arrfact['index']];
	$strlabel=$arrfact['label']!=="" ? $arrfact['label'] : f_t2srep_label_for_row($arrrecord, $arrcolumns);
	$arrword=f_t2srep_quantity_word($arrfact['column'], $arrrecord, $strlang);
	$strvalue=f_t2srep_quantity_value($arrfact['value'], $arrfact['unit'], $arrfact['role'], $strlang);
	$strtext=$strlabel.($strlang=="fr" ? " : " : ": ").$arrword['label'].($strvalue!=="" ? ", ".$strvalue : "");
	if ($arrfact['others']>0) {
		$strtext.=" (".f_t2srep_others_words($arrfact['others'], $arrrecords, $arrcolumns, $strlang).")";
	}
	return $strtext.".";
}

function f_t2srep_verdict_evidence($jsontext2sql) {
	//Purpose: The renderer that draws the evidence of a verdict: the option that follows `verdict`
	//			in representation_options (entity-page or card-grid for rows, number-card for a
	//			count); "" for an empty result, which has nothing to show
	if (!is_array($jsontext2sql) || count(f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array()))==0) {
		return "";
	}
	$arroptions=f_t2srep_options($jsontext2sql);
	$lngposition=array_search("verdict", $arroptions, true);
	if ($lngposition===false || !isset($arroptions[$lngposition+1])) {
		return "";
	}
	return $arroptions[$lngposition+1];
}

function f_t2srep_verdict_parts($arrrecords, $arrcolumns, $arrfacts, $strlang='en') {
	//Purpose: The pieces of a verdict, shared by its block and its caption
	//Returns: array("yes"=>bool, "word"=>"Yes"|"No"|"Oui"|"Non", "detail"=>"2 matching results" or
	//			"nothing in the database matches" or "", "value"=>the count of a count verdict, else ""),
	//			or null when summary_facts carries no verdict
	if (!is_array($arrfacts) || !isset($arrfacts['verdict']) || !in_array(strtolower((string)$arrfacts['verdict']), array("yes", "no"), true)) {
		return null;
	}
	$intfr=($strlang=="fr");
	$intyes=(strtolower((string)$arrfacts['verdict'])=="yes");
	$strbasis=isset($arrfacts['verdict_basis']) ? (string)$arrfacts['verdict_basis'] : "";
	$strdetail="";
	$strvalue="";
	if ($strbasis=="count" && count($arrrecords)>0) {
		$arrmetrics=f_t2srep_scalar_metrics($arrrecords, $arrcolumns);
		if (count($arrmetrics)>0) {
			$strvalue=f_t2srep_format_metric($arrmetrics[0]['value'], $arrmetrics[0]['unit'], $strlang);
		}
	} elseif (count($arrrecords)>0) {
		$lngcount=(isset($arrfacts['count']) && f_t2srep_is_number($arrfacts['count']) && (int)$arrfacts['count']>0) ? (int)$arrfacts['count'] : count($arrrecords);
		$strcount=number_format($lngcount, 0, '.', ',');
		if ($intfr) {
			$strdetail=$strcount." ".($lngcount==1 ? "résultat correspondant" : "résultats correspondants");
		} else {
			$strdetail=$strcount." ".($lngcount==1 ? "matching result" : "matching results");
		}
	} else {
		//Never "it is false": an empty answer cannot tell that from what the database lacks
		$strdetail=$intfr ? "rien dans la base ne correspond" : "nothing in the database matches";
	}
	return array("yes"=>$intyes, "word"=>$intyes ? ($intfr ? "Oui" : "Yes") : ($intfr ? "Non" : "No"), "detail"=>$strdetail, "value"=>$strvalue);
}

function f_t2srep_render_verdict($arrrecords, $arrcolumns, $strlang='en', $arrfacts=array(), $strevidence="") {
	//Purpose: Yes or No, large (a word and a sign, never a colour alone), then the evidence: the
	//			number card for a count; for rows the search page shows its card view under this one
	//			(the cards of entity-page or card-grid), so nothing more is drawn here; nothing for an
	//			empty result
	//Params:	$strevidence: f_t2srep_verdict_evidence() of the answer
	$arrparts=f_t2srep_verdict_parts($arrrecords, $arrcolumns, $arrfacts, $strlang);
	if ($arrparts===null) {
		return f_t2srep_render_table($arrrecords, $arrcolumns, $strlang);
	}
	$intfr=($strlang=="fr");
	$strhtml="<div class=\"t2sVerdict ".($arrparts['yes'] ? "t2sVerdictYes" : "t2sVerdictNo")."\" role=\"group\" aria-label=\"".f_t2srep_h(($intfr ? "Réponse : " : "Answer: ").$arrparts['word'])."\">"
		."<span class=\"t2sVerdictIcon\" aria-hidden=\"true\">".($arrparts['yes'] ? "✓" : "✗")."</span>"
		."<div class=\"t2sVerdictText\"><div class=\"t2sVerdictWord\">".f_t2srep_h($arrparts['word'])."</div>"
		.($arrparts['detail']!=="" ? "<div class=\"t2sVerdictDetail\">".f_t2srep_h(f_t2srep_ucfirst($arrparts['detail']))."</div>" : "")
		."</div></div>";
	if (count($arrrecords)>0 && ($strevidence=="number-card" || $strevidence=="stat-strip")) {
		$strhtml.=f_t2srep_render_scalar_cards($arrrecords, $arrcolumns, $strlang);
	} elseif (count($arrrecords)>0 && $strevidence=="table") {
		$strhtml.=f_t2srep_render_table($arrrecords, $arrcolumns, $strlang);
	}
	return $strhtml;
}

function f_t2srep_caption_verdict($jsontext2sql, $strlang='en') {
	//Purpose: The caption of a verdict: "Yes: 2 matching results." / "Oui : 2 résultats
	//			correspondants.", "No: nothing in the database matches." / "Non : rien dans la base ne
	//			correspond.", for a count "Yes: 3." / "No: 0." / "Oui : 3." / "Non : 0."
	$arrrecords=f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array());
	$arrparts=f_t2srep_verdict_parts($arrrecords, f_t2srep_columns($jsontext2sql), $jsontext2sql['summary_facts'], $strlang);
	if ($arrparts===null) {
		return "";
	}
	$strrest=$arrparts['detail']!=="" ? $arrparts['detail'] : $arrparts['value'];
	return $arrparts['word'].($strrest!=="" ? ($strlang=="fr" ? " : " : ": ").$strrest : "").".";
}

/* ---------------------------------------------------------------------------------------------
 * Phase 2 lot C: histogram, share-bar, flag-list. Drawn here from summary_facts.histogram,
 * .share and .countries, the sentences word for word those of voice-agent
 * (text2SqlHistogramSummary(), text2SqlShareSummary(), text2SqlFlagSummary()). Digits are
 * shared by both languages, as everywhere in the contract.
 * ------------------------------------------------------------------------------------------- */

function f_t2srep_plain_number($value) {
	//Purpose: A number with en-US grouping and two decimals at most (Intl.NumberFormat("en-US", {maximumFractionDigits: 2}))
	if (!f_t2srep_is_number($value)) {
		return (string)$value;
	}
	return f_t2srep_format_number((float)$value, 2);
}

function f_t2srep_short_money($value) {
	//Purpose: Mirrors formatMoney(): $150M, $1.7B, $50K; "$0" for zero (a bin starts there)
	$dblvalue=(float)$value;
	if ($dblvalue<=0) {
		return "$0";
	}
	if ($dblvalue>=1e9) {
		return "$".number_format($dblvalue/1e9, $dblvalue>=1e10 ? 0 : 1, '.', '')."B";
	}
	if ($dblvalue>=1e6) {
		return "$".number_format($dblvalue/1e6, $dblvalue>=1e7 ? 0 : 1, '.', '')."M";
	}
	if ($dblvalue>=1e3) {
		return "$".round($dblvalue/1e3)."K";
	}
	return "$".round($dblvalue);
}

function f_t2srep_counted_noun($strvaluecolumn, $value, $strlang='en') {
	//Purpose: What a count column counts, as the noun of the sentence (FILM_COUNT counts films)
	//Returns: array("word"=>..., "gender"=>"m"|"f")
	$strname=strtoupper((string)$strvaluecolumn);
	$intone=(f_t2srep_is_number($value) && (float)$value==1);
	if (preg_match('/MOVIE|FILM/', $strname)) {
		$arrnoun=array("films", "film", "films", "film", "m");
	} elseif (preg_match('/SERIE|SHOW/', $strname)) {
		$arrnoun=array("series", "series", "séries", "série", "f");
	} elseif (preg_match('/PERSON|PEOPLE|CAST|ACTOR|ACTRESS|DIRECTOR|WINNER|CREW|MEMBER/', $strname)) {
		$arrnoun=array("people", "person", "personnes", "personne", "f");
	} else {
		$arrnoun=array("results", "result", "résultats", "résultat", "m");
	}
	$lngi=($strlang=="fr" ? 2 : 0)+($intone ? 1 : 0);
	return array("word"=>$arrnoun[$lngi], "gender"=>$arrnoun[4]);
}

function f_t2srep_bin_value($value, $strunit) {
	//Purpose: A bin boundary: a plain number, money in the short form
	if (!f_t2srep_is_number($value)) {
		return (string)$value;
	}
	return $strunit=="usd" ? f_t2srep_short_money($value) : f_t2srep_plain_number($value);
}

function f_t2srep_bin_range($arrbin, $arrhistogram, $strlang='en') {
	//Purpose: "120 to 149 min": the bin's own end, else start + width - 1 for whole numbers; the
	//			start alone for a step of 1 or a fractional step. A text bin is its label.
	if (isset($arrbin['label']) && $arrbin['label']!==null && trim((string)$arrbin['label'])!=="") {
		return (string)$arrbin['label'];
	}
	$strunit=isset($arrhistogram['unit']) ? (string)$arrhistogram['unit'] : "";
	$low=isset($arrbin['low']) ? $arrbin['low'] : null;
	$high=isset($arrbin['high']) && f_t2srep_is_number($arrbin['high']) ? (float)$arrbin['high'] : null;
	$width=isset($arrhistogram['width']) && f_t2srep_is_number($arrhistogram['width']) ? (float)$arrhistogram['width'] : null;
	if ($high===null && f_t2srep_is_number($low) && floor((float)$low)==(float)$low && $width!==null && floor($width)==$width && $width>1) {
		$high=(float)$low+$width-1;
	}
	$strsuffix=$strunit=="minutes" ? " min" : "";
	$strlow=f_t2srep_bin_value($low, $strunit);
	if ($high===null || !f_t2srep_is_number($low) || $high==(float)$low) {
		return $strlow.$strsuffix;
	}
	return $strlow." ".($strlang=="fr" ? "à" : "to")." ".f_t2srep_bin_value($high, $strunit).$strsuffix;
}

function f_t2srep_histogram_model($arrfacts, $strlang='en') {
	//Purpose: Mirrors text2SqlHistogramModel(): the bars of summary_facts.histogram, or null
	if (!is_array($arrfacts) || !isset($arrfacts['histogram']) || !is_array($arrfacts['histogram'])) {
		return null;
	}
	$arrhistogram=$arrfacts['histogram'];
	$arrbins=isset($arrhistogram['bins']) && is_array($arrhistogram['bins']) ? array_values($arrhistogram['bins']) : array();
	if (count($arrbins)<2) {
		return null;
	}
	$arrvalues=array();
	foreach ($arrbins as $arrbin) {
		$arrvalues[]=isset($arrbin['value']) && f_t2srep_is_number($arrbin['value']) ? (float)$arrbin['value'] : 0;
	}
	$dblmax=max($arrvalues);
	$lngpeak=isset($arrhistogram['peak']) && f_t2srep_is_number($arrhistogram['peak']) ? (int)$arrhistogram['peak'] : array_search($dblmax, $arrvalues);
	$strunit=isset($arrhistogram['unit']) ? (string)$arrhistogram['unit'] : "";
	$arrbars=array();
	foreach ($arrbins as $lngi=>$arrbin) {
		$dblvalue=$arrvalues[$lngi];
		$intlabel=isset($arrbin['label']) && $arrbin['label']!==null && trim((string)$arrbin['label'])!=="";
		$arrbars[]=array(
			"axis"=>$intlabel ? (string)$arrbin['label'] : f_t2srep_bin_value(isset($arrbin['low']) ? $arrbin['low'] : null, $strunit),
			"range"=>f_t2srep_bin_range($arrbin, $arrhistogram, $strlang),
			"value"=>$dblvalue,
			"valuetext"=>f_t2srep_plain_number($dblvalue),
			"pct"=>$dblmax>0 ? ($dblvalue>0 ? max(1, (int)round($dblvalue/$dblmax*100)) : 0) : 0,
			"empty"=>!empty($arrbin['empty']) || $dblvalue==0,
			"peak"=>($lngi===$lngpeak),
		);
	}
	$dbltotal=isset($arrhistogram['total']) && f_t2srep_is_number($arrhistogram['total']) ? (float)$arrhistogram['total'] : array_sum($arrvalues);
	return array(
		"bars"=>$arrbars,
		"unit"=>$strunit,
		"width"=>isset($arrhistogram['width']) && f_t2srep_is_number($arrhistogram['width']) ? (float)$arrhistogram['width'] : null,
		"column"=>isset($arrhistogram['column']) ? (string)$arrhistogram['column'] : "",
		"valuecolumn"=>isset($arrhistogram['value_column']) ? (string)$arrhistogram['value_column'] : (isset($arrfacts['value_column']) ? (string)$arrfacts['value_column'] : ""),
		"total"=>$dbltotal,
		"unbinned"=>isset($arrhistogram['unbinned']) && f_t2srep_is_number($arrhistogram['unbinned']) ? (float)$arrhistogram['unbinned'] : 0,
		"histogram"=>$arrhistogram,
	);
}

function f_t2srep_histogram_axis_name($arrmodel, $strlang='en') {
	//Purpose: The axis title under the columns: the quantity cut into bins, its unit and the bin width
	$strcolumn=isset($arrmodel['column']) ? (string)$arrmodel['column'] : "";
	$strname=preg_replace('/_(?:BUCKET|BAND|BIN|RANGE|START|MIN|LOW|FROM|DEBUT)(?:_MINUTES)?$/i', '', $strcolumn);
	$strname=preg_replace('/_(?:BUCKET|BAND|BIN|RANGE)$/i', '', $strname);
	if (preg_match('/^(duree|duration|runtime)/i', $strname)) {
		$strname="RUNTIME";
	}
	$arrword=f_t2srep_metric_word($strname!="" ? $strname : $strcolumn, $strlang);
	$strunit=$arrmodel['unit']=="minutes" ? " (min)" : ($arrmodel['unit']=="usd" ? " ($)" : "");
	$strstep="";
	if ($arrmodel['width']!==null && ($arrmodel['unit']=="minutes" || $arrmodel['unit']=="usd")) {
		$strstep=", ".($strlang=="fr" ? "tranches de" : "bins of")." ".($arrmodel['unit']=="usd" ? f_t2srep_short_money($arrmodel['width']) : f_t2srep_plain_number($arrmodel['width'])." min");
	}
	return f_t2srep_ucfirst($arrword['label']).$strunit.$strstep;
}

function f_t2srep_render_histogram($arrrecords, $arrcolumns, $strlang='en', $arrfacts=array(), $strquestion="") {
	//Purpose: The histogram: one column per bin, in bin order, the columns touching; an empty bin
	//			the API filled in keeps its slot (a dashed base). Every column carries its range and
	//			its count as text, so a screen reader reads "120 to 149 min: 5"; the counts show over
	//			the columns up to twelve bins, the peak's always. A long axis scrolls in its box.
	//			With the question, a bin that holds rows is a link to its follow-up (drill-down).
	$arrmodel=f_t2srep_histogram_model($arrfacts, $strlang);
	if ($arrmodel===null) {
		return f_t2srep_render_table($arrrecords, $arrcolumns, $strlang);
	}
	$intfew=(count($arrmodel['bars'])<=12);
	$lngevery=$intfew ? 1 : (int)ceil(count($arrmodel['bars'])/12);
	$straxis=f_t2srep_histogram_axis_name($arrmodel, $strlang);
	$strhtml="<figure class=\"t2sHisto".($intfew ? "" : " t2sHistoDense")."\" role=\"group\" aria-label=\"".f_t2srep_h($straxis)."\">"
		."<div class=\"t2sHistoScroll\" tabindex=\"0\"><ol class=\"t2sHistoCols\" style=\"min-width:".(count($arrmodel['bars'])*($intfew ? 40 : 14))."px\">";
	$arrbins=isset($arrmodel['histogram']['bins']) && is_array($arrmodel['histogram']['bins']) ? array_values($arrmodel['histogram']['bins']) : array();
	$arrword=f_t2srep_drill_word($arrmodel['column'], $strlang);
	foreach ($arrmodel['bars'] as $lngi=>$arrbar) {
		$strtext=$arrbar['range'].($strlang=="fr" ? " : " : ": ").$arrbar['valuetext'];
		$strinner="<span class=\"t2sSrOnly\">".f_t2srep_h($strtext)."</span>"
			."<span class=\"t2sHistoValue\" aria-hidden=\"true\">".((($intfew && !$arrbar['empty']) || $arrbar['peak']) ? f_t2srep_h($arrbar['valuetext']) : "")."</span>"
			."<span class=\"t2sHistoTrack\" aria-hidden=\"true\"><span class=\"t2sHistoBar\" style=\"height:".$arrbar['pct']."%\"></span></span>"
			."<span class=\"t2sHistoLabel\" aria-hidden=\"true\">".(($lngi % $lngevery==0 || $arrbar['peak']) ? f_t2srep_h($arrbar['axis']) : "")."</span>";
		$strfollowup=$arrbar['empty'] ? "" : f_t2srep_drill_question($strquestion, $arrword, f_t2srep_drill_bin_value(isset($arrbins[$lngi]) ? $arrbins[$lngi] : null, $arrmodel['histogram'], $strlang), $strlang);
		if ($strfollowup!=="") {
			$strinner=f_t2srep_drill_link(f_t2srep_drill_href($strfollowup), $strinner, "t2sDrill", $strtext.". ".$strfollowup);
		}
		$strhtml.="<li class=\"t2sHistoCol".($arrbar['peak'] ? " t2sHistoPeak" : "").($arrbar['empty'] ? " t2sHistoEmpty" : "")."\" title=\"".f_t2srep_h($strtext)."\">".$strinner."</li>";
	}
	$strhtml.="</ol></div><figcaption class=\"t2sHistoAxis\">".f_t2srep_h($straxis)."</figcaption>";
	if ($arrmodel['unbinned']>0) {
		$arrnoun=f_t2srep_counted_noun($arrmodel['valuecolumn'], $arrmodel['unbinned'], $strlang);
		$strhtml.="<p class=\"t2sHistoNote\">".f_t2srep_h(f_t2srep_plain_number($arrmodel['unbinned'])." ".$arrnoun['word']
			.($strlang=="fr" ? " sans valeur, hors tranches." : " with no value, outside the bins."))."</p>";
	}
	return $strhtml."</figure>";
}

function f_t2srep_caption_histogram($jsontext2sql, $strlang='en') {
	//Purpose: "18 films in bins of 30 min; the most common bin is 120 to 149 min, 5 films." /
	//			"18 films, par tranches de 30 min ; la tranche la plus fréquente : 120 à 149 min, 5 films."
	$arrmodel=f_t2srep_histogram_model(isset($jsontext2sql['summary_facts']) ? $jsontext2sql['summary_facts'] : null, $strlang);
	if ($arrmodel===null) {
		return "";
	}
	$intfr=($strlang=="fr");
	$arrnoun=f_t2srep_counted_noun($arrmodel['valuecolumn'], $arrmodel['total'], $strlang);
	if ($arrmodel['width']!==null && $arrmodel['unit']=="minutes") {
		$strstep=($intfr ? "par tranches de " : "in bins of ").f_t2srep_plain_number($arrmodel['width'])." min";
	} elseif ($arrmodel['width']!==null && $arrmodel['unit']=="usd") {
		$strstep=($intfr ? "par tranches de " : "in bins of ").f_t2srep_short_money($arrmodel['width']);
	} else {
		$strstep=$intfr ? "en ".count($arrmodel['bars'])." tranches" : "in ".count($arrmodel['bars'])." bins";
	}
	$strlead=f_t2srep_plain_number($arrmodel['total'])." ".$arrnoun['word'].($intfr ? ", " : " ").$strstep;
	$arrpeak=null;
	foreach ($arrmodel['bars'] as $arrbar) {
		if ($arrbar['peak']) {
			$arrpeak=$arrbar;
		}
	}
	if ($arrpeak===null || $arrpeak['value']==0) {
		return f_t2srep_ucfirst($strlead).".";
	}
	$arrpeaknoun=f_t2srep_counted_noun($arrmodel['valuecolumn'], $arrpeak['value'], $strlang);
	$strcount=$arrpeak['valuetext']." ".$arrpeaknoun['word'];
	$strtail=$intfr ? "la tranche la plus fréquente : ".$arrpeak['range'].", ".$strcount : "the most common bin is ".$arrpeak['range'].", ".$strcount;
	return f_t2srep_ucfirst($strlead).($intfr ? " ; " : "; ").$strtail.".";
}

function f_t2srep_percent($dblshare, $strlang='en') {
	//Purpose: A share as a percentage: "49%" / "49 %", "<1%" for a share above 0 and under 1%
	if (!f_t2srep_is_number($dblshare)) {
		return "";
	}
	$dblpct=(float)$dblshare*100;
	$strn=($dblpct>0 && $dblpct<1) ? "<1" : (string)(int)round($dblpct);
	return $strlang=="fr" ? $strn." %" : $strn."%";
}

function f_t2srep_gender_word($value, $strlang='en') {
	//Purpose: TMDb's gender code as a word (0 not set, 1 women, 2 men, 3 non-binary), or null
	$arrwords=array("0"=>array("not set", "non renseigné"), "1"=>array("women", "femmes"), "2"=>array("men", "hommes"), "3"=>array("non-binary", "non binaire"));
	$strkey=trim((string)$value);
	return isset($arrwords[$strkey]) ? $arrwords[$strkey][$strlang=="fr" ? 1 : 0] : null;
}

function f_t2srep_share_model($jsontext2sql, $strlang='en') {
	//Purpose: Mirrors text2SqlShareModel(): the labelled parts of summary_facts.share, or null.
	//			One share and its rest are rounded to add up to 100 (38% and 62%, never 63%).
	if (!is_array($jsontext2sql) || !isset($jsontext2sql['summary_facts']['share']) || !is_array($jsontext2sql['summary_facts']['share'])) {
		return null;
	}
	$arrshare=$jsontext2sql['summary_facts']['share'];
	$arrparts=isset($arrshare['parts']) && is_array($arrshare['parts']) ? array_values($arrshare['parts']) : array();
	if (count($arrparts)==0) {
		return null;
	}
	$intfr=($strlang=="fr");
	$strkind=(isset($arrshare['kind']) && $arrshare['kind']=="single") ? "single" : "parts";
	$intgender=preg_match('/GENDER|SEXE/i', isset($arrshare['label_column']) ? (string)$arrshare['label_column'] : "");
	$dblshown=null;
	foreach ($arrparts as $arrpart) {
		if (empty($arrpart['rest']) && $dblshown===null) {
			$dblshown=isset($arrpart['share']) ? (float)$arrpart['share'] : 0;
		}
	}
	$arrsegments=array();
	foreach ($arrparts as $lngi=>$arrpart) {
		$intrest=!empty($arrpart['rest']);
		if ($intrest) {
			$strlabel=$intfr ? "le reste" : "the rest";
		} elseif ($strkind=="single") {
			$arrword=f_t2srep_metric_word(isset($arrshare['value_column']) ? $arrshare['value_column'] : "", $strlang);
			$strlabel=$arrword['label'];
		} elseif ($intgender && f_t2srep_gender_word(isset($arrpart['label']) ? $arrpart['label'] : null, $strlang)!==null) {
			$strlabel=f_t2srep_gender_word($arrpart['label'], $strlang);
		} else {
			$strlabel=trim((string)(isset($arrpart['label']) ? $arrpart['label'] : ""));
			if ($strlabel==="") {
				$strlabel=$intfr ? "sans valeur" : "no value";
			}
		}
		$dblshare=isset($arrpart['share']) && f_t2srep_is_number($arrpart['share']) ? (float)$arrpart['share'] : 0;
		$dblpctshare=($strkind=="single" && $intrest && $dblshown!==null) ? (100-round($dblshown*100))/100 : $dblshare;
		$value=isset($arrpart['value']) ? $arrpart['value'] : null;
		$arrsegments[]=array(
			"label"=>$strlabel,
			"share"=>$dblshare,
			"pcttext"=>f_t2srep_percent($dblpctshare, $strlang),
			"valuetext"=>($strkind=="single" || !f_t2srep_is_number($value)) ? "" : f_t2srep_plain_number($value),
			"rest"=>$intrest,
			"index"=>isset($arrpart['index']) && f_t2srep_is_number($arrpart['index']) ? (int)$arrpart['index'] : null,
		);
	}
	return array("kind"=>$strkind, "segments"=>$arrsegments, "total"=>isset($arrshare['total']) ? $arrshare['total'] : null,
		"valuecolumn"=>isset($arrshare['value_column']) ? (string)$arrshare['value_column'] : "");
}

function f_t2srep_render_share_bar($jsontext2sql, $strlang='en') {
	//Purpose: The share bar: one 100% bar, a segment per part in the API's order, its percentage
	//			written on every segment of 12% or more; the legend below lists every part with its
	//			swatch, label, count and percentage, so nothing rests on the colour. A part whose row
	//			names an entity links to its page. A part asks its follow-up (drill-down): the
	//			segment and the legend name link to it, the numbers when the name opens the entity;
	//			never one share alone nor its rest. summary_facts.share.shape "pie" draws the
	//			parts as a ring (f_t2srep_pie_svg()) beside the same legend; any other shape, or
	//			none, the bar.
	$arrmodel=f_t2srep_share_model($jsontext2sql, $strlang);
	$arrrecords=f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array());
	if ($arrmodel===null) {
		return f_t2srep_render_table($arrrecords, f_t2srep_columns($jsontext2sql), $strlang);
	}
	$intpie=(isset($jsontext2sql['summary_facts']['share']['shape']) && $jsontext2sql['summary_facts']['share']['shape']==="pie");
	$strbar="";
	$strlegend="";
	$arrdrillhrefs=array();
	foreach ($arrmodel['segments'] as $lngi=>$arrsegment) {
		$strhue=$arrsegment['rest'] ? "rest" : (string)($lngi % 8);
		$strname=f_t2srep_ucfirst($arrsegment['label']);
		$strnumbers=$arrsegment['valuetext']!=="" ? $arrsegment['valuetext']." · ".$arrsegment['pcttext'] : $arrsegment['pcttext'];
		$strfollowup=f_t2srep_drill_share_question($jsontext2sql, $arrsegment, $strlang);
		$strdrillhref=f_t2srep_drill_href($strfollowup);
		$strdrilllabel=$strname.($strlang=="fr" ? " : " : ": ").$strnumbers.". ".$strfollowup;
		$arrdrillhrefs[$lngi]=$strdrillhref;
		$strseginner=$arrsegment['share']>=0.12 ? f_t2srep_h($arrsegment['pcttext']) : "";
		$strsegstyle=" data-hue=\"".$strhue."\" style=\"flex-grow:".f_t2srep_h((string)max($arrsegment['share'], 0.0001))."\"";
		if ($strdrillhref!="") {
			$strbar.="<a class=\"t2sShareSeg t2sDrill\"".$strsegstyle." href=\"".f_t2srep_h($strdrillhref)."\" tabindex=\"-1\" title=\"".f_t2srep_h($strname." ".$arrsegment['pcttext'])."\">".$strseginner."</a>";
		} else {
			$strbar.="<span class=\"t2sShareSeg\"".$strsegstyle." title=\"".f_t2srep_h($strname." ".$arrsegment['pcttext'])."\">".$strseginner."</span>";
		}
		$strhref=($arrsegment['index']!==null && isset($arrrecords[$arrsegment['index']])) ? f_t2srep_entity_href($arrrecords[$arrsegment['index']]) : "";
		if ($strhref!="") {
			$strnamehtml="<a href=\"".f_t2srep_h($strhref)."\">".f_t2srep_h($strname)."</a>";
		} elseif ($strdrillhref!="") {
			$strnamehtml=f_t2srep_drill_link($strdrillhref, f_t2srep_h($strname), "t2sDrill", $strdrilllabel);
		} else {
			$strnamehtml=f_t2srep_h($strname);
		}
		$strnumbershtml=($strhref!="" && $strdrillhref!="") ? f_t2srep_drill_link($strdrillhref, f_t2srep_h($strnumbers), "t2sDrill", $strdrilllabel) : f_t2srep_h($strnumbers);
		$strlegend.="<li class=\"t2sShareItem\"><span class=\"t2sShareSwatch\" data-hue=\"".$strhue."\" aria-hidden=\"true\"></span>"
			."<span class=\"t2sShareName\">".$strnamehtml."</span>"
			."<span class=\"t2sShareNumbers\">".$strnumbershtml."</span></li>";
	}
	if ($intpie) {
		return "<div class=\"t2sShare t2sSharePie\">".f_t2srep_pie_svg($arrmodel['segments'], $arrdrillhrefs)."<ul class=\"t2sShareLegend\">".$strlegend."</ul></div>";
	}
	return "<div class=\"t2sShare\"><div class=\"t2sShareBar\" aria-hidden=\"true\">".$strbar."</div><ul class=\"t2sShareLegend\">".$strlegend."</ul></div>";
}

function f_t2srep_caption_share($jsontext2sql, $strlang='en') {
	//Purpose: "368 series counted in six parts: Scripted 49%, Miniseries 38%, Documentary 8% and 3
	//			more." / "... séries comptées en six parts : ... et 3 autres."; one share alone
	//			"74% of the whole; the rest, 26%." / "74 % de l'ensemble ; le reste, 26 %."
	$arrmodel=f_t2srep_share_model($jsontext2sql, $strlang);
	if ($arrmodel===null) {
		return "";
	}
	$intfr=($strlang=="fr");
	if ($arrmodel['kind']=="single") {
		$arrpart=$arrmodel['segments'][0];
		$strhead=$arrpart['pcttext'].($intfr ? " de l'ensemble" : " of the whole");
		if (count($arrmodel['segments'])>1) {
			return $strhead.($intfr ? " ; le reste, " : "; the rest, ").$arrmodel['segments'][1]['pcttext'].".";
		}
		return $strhead.".";
	}
	$arrnoun=f_t2srep_counted_noun($arrmodel['valuecolumn'], $arrmodel['total'], $strlang);
	$lngk=count($arrmodel['segments']);
	$strcounted=$intfr ? ($arrnoun['gender']=="f" ? "comptées" : "comptés") : "counted";
	$strlead=f_t2srep_plain_number($arrmodel['total'])." ".$arrnoun['word']." ".$strcounted." ".($intfr ? "en " : "in ").f_t2srep_count_word($lngk, $strlang, "f")." parts";
	$arrnamed=array();
	foreach (array_slice($arrmodel['segments'], 0, 3) as $arrsegment) {
		$arrnamed[]=$arrsegment['label']." ".$arrsegment['pcttext'];
	}
	$strlist=implode(", ", $arrnamed);
	if ($lngk>3) {
		$strlist.=$intfr ? " et ".($lngk-3)." ".($lngk-3==1 ? "autre" : "autres") : " and ".($lngk-3)." more";
	}
	return f_t2srep_ucfirst($strlead).($intfr ? " : " : ": ").$strlist.".";
}

/* ---------------------------------------------------------------------------------------------
 * Drill-down on the bucketed views: a histogram bin, a trend column, a share part asks a
 * follow-up question built from the original question and the bucket, in the UI language, with
 * no model call: "Among the items counted in "<question>", list those whose number of seasons is 3."
 * Mirrors voice-agent (text2SqlDrillWord(), text2SqlDrillQuestion(), text2SqlDrillBinValue(),
 * text2SqlDrillPeriodValue(), text2SqlDrillShareQuestion()): the same words and the same rules,
 * change both. Here a bucket is a link to the search page with the follow-up as its question;
 * voice-agent sends it as a typed question.
 * ------------------------------------------------------------------------------------------- */

function f_t2srep_drill_word($strcolumn, $strlang='en') {
	//Purpose: How a follow-up names the quantity or the category of a bucket: the column without
	//			its bin suffix (RUNTIME_BUCKET is RUNTIME), from a table of its own so both fronts
	//			word it the same (their axis titles differ: "seasons" there, "number of seasons"
	//			here), else the column name in plain words
	//Returns: array("label"=>..., "gender"=>"m"|"f") (the gender picks the French article)
	$strname=preg_replace('/_(?:BUCKET|BAND|BIN|RANGE|START|MIN|LOW|FROM|DEBUT)(?:_MINUTES)?$/i', '', (string)$strcolumn);
	$strname=preg_replace('/_(?:BUCKET|BAND|BIN|RANGE)$/i', '', $strname);
	if ($strname=="") {
		$strname=(string)$strcolumn;
	}
	if (preg_match('/^(duree|duration|runtime)/i', $strname)) {
		$strname="RUNTIME";
	}
	$arrwords=array(
		"RUNTIME"=>array("runtime", "durée", "f"),
		"BUDGET"=>array("budget", "budget", "m"),
		"REVENUE"=>array("revenue", "box-office", "m"),
		"IMDB_RATING"=>array("IMDb rating", "note IMDb", "f"),
		"IMDB_RATING_WEIGHTED"=>array("IMDb rating", "note IMDb", "f"),
		"IMDB_RATING_ADJUSTED"=>array("IMDb rating", "note IMDb", "f"),
		"RATING"=>array("rating", "note", "f"),
		"IMDB_VOTES"=>array("number of IMDb votes", "nombre de votes IMDb", "m"),
		"VOTE_AVERAGE"=>array("TMDb rating", "note TMDb", "f"),
		"VOTE_COUNT"=>array("number of TMDb votes", "nombre de votes TMDb", "m"),
		"POPULARITY"=>array("popularity", "popularité", "f"),
		"NUMBER_OF_SEASONS"=>array("number of seasons", "nombre de saisons", "m"),
		"NUMBER_OF_EPISODES"=>array("number of episodes", "nombre d'épisodes", "m"),
		"AGE"=>array("age", "âge", "m"),
		"BIRTH_YEAR"=>array("birth year", "année de naissance", "f"),
		"DEATH_YEAR"=>array("death year", "année de décès", "f"),
		"RELEASE_YEAR"=>array("release year", "année de sortie", "f"),
		"FIRST_AIR_YEAR"=>array("first air year", "année de première diffusion", "f"),
		"YEAR"=>array("year", "année", "f"),
		"ANNEE"=>array("year", "année", "f"),
		"DECADE"=>array("decade", "décennie", "f"),
		"DECENNIE"=>array("decade", "décennie", "f"),
		"DAT_RELEASE"=>array("release date", "date de sortie", "f"),
		"DAT_FIRST_AIR"=>array("first air date", "date de première diffusion", "f"),
		"STATUS"=>array("status", "statut", "m"),
		"SERIE_TYPE"=>array("type", "type", "m"),
		"TYPE"=>array("type", "type", "m"),
		"GENDER"=>array("gender", "genre", "m"),
		"GENRE_NAME"=>array("genre", "genre", "m"),
		"ORIGINAL_LANGUAGE"=>array("original language", "langue originale", "f"),
	);
	$strkey=strtoupper($strname);
	if (isset($arrwords[$strkey])) {
		return array("label"=>$arrwords[$strkey][$strlang=="fr" ? 1 : 0], "gender"=>$arrwords[$strkey][2]);
	}
	return array("label"=>strtolower(f_t2srep_pretty_label($strname)), "gender"=>"m");
}

function f_t2srep_drill_question($strquestion, $arrword, $strvalue, $strlang='en') {
	//Purpose: The follow-up of a bucket: 'Among the items counted in "<question>", list those
	//			whose <word> is <value>.' / 'Parmi les éléments comptés dans « <question> », liste
	//			ceux dont <le|la|l'><word> est <value>.'; "" with no question or no value. Chosen on
	//			the live API among three wordings: "counted in" keeps the conditions of the original
	//			question (the films announced, not every film of the year), and "list" asks for the
	//			rows, not another distribution.
	$strquestion=trim(preg_replace('/\s+/u', ' ', (string)$strquestion));
	$strvalue=trim((string)$strvalue);
	if ($strquestion==="" || $strvalue==="" || !is_array($arrword) || !isset($arrword['label']) || $arrword['label']==="") {
		return "";
	}
	if ($strlang=="fr") {
		$strarticle=preg_match('/^[aeiouyhâàéèêîïôû]/iu', $arrword['label']) ? "l'" : ($arrword['gender']=="f" ? "la " : "le ");
		return "Parmi les éléments comptés dans « ".$strquestion." », liste ceux dont ".$strarticle.$arrword['label']." est ".$strvalue.".";
	}
	return "Among the items counted in \"".$strquestion."\", list those whose ".$arrword['label']." is ".$strvalue.".";
}

function f_t2srep_drill_href($strfollowup) {
	//Purpose: The search page asking the follow-up: relative, so the URL prefix (language, private
	//			mode) of the page is kept, as the samples page links its questions; "" with no question
	return $strfollowup==="" ? "" : "index.php?search=".rawurlencode($strfollowup);
}

function f_t2srep_drill_bin_value($arrbin, $arrhistogram, $strlang='en') {
	//Purpose: The bucket of a histogram bin in a follow-up: its exact value for a step of 1 (or a
	//			bin whose end is its start), "between <low> and <high>" / "entre <low> et <high>"
	//			otherwise (the end: the bin's own, else start + width - 1 for whole numbers, else
	//			start + width), a text bin its label; "<n> minutes" on a runtime. "" for an empty bin.
	if (!is_array($arrbin) || !empty($arrbin['empty']) || !(isset($arrbin['value']) && f_t2srep_is_number($arrbin['value']) && (float)$arrbin['value']>0)) {
		return "";
	}
	if (isset($arrbin['label']) && $arrbin['label']!==null && trim((string)$arrbin['label'])!=="") {
		return trim((string)$arrbin['label']);
	}
	$low=isset($arrbin['low']) ? $arrbin['low'] : null;
	if (!f_t2srep_is_number($low)) {
		return "";
	}
	$strunit=isset($arrhistogram['unit']) ? (string)$arrhistogram['unit'] : "";
	$high=isset($arrbin['high']) && f_t2srep_is_number($arrbin['high']) ? (float)$arrbin['high'] : null;
	$width=isset($arrhistogram['width']) && f_t2srep_is_number($arrhistogram['width']) ? (float)$arrhistogram['width'] : null;
	if ($high===null && $width!==null && $width>0 && $width!=1) {
		$high=(floor((float)$low)==(float)$low && floor($width)==$width) ? (float)$low+$width-1 : (float)$low+$width;
	}
	$strsuffix=$strunit=="minutes" ? " minutes" : "";
	$strlow=f_t2srep_bin_value($low, $strunit);
	if ($high===null || $high==(float)$low) {
		return $strlow.$strsuffix;
	}
	$strhigh=f_t2srep_bin_value($high, $strunit);
	return ($strlang=="fr" ? "entre ".$strlow." et " : "between ".$strlow." and ").$strhigh.$strsuffix;
}

function f_t2srep_drill_period_value($lngyear, $intdecade, $strraw, $strlang='en') {
	//Purpose: The bucket of a trend column in a follow-up: the year, "the 1980s" / "les années
	//			1980" for a decade, the cell as is when it holds no year
	if ($lngyear===null) {
		return trim((string)$strraw);
	}
	if ($intdecade) {
		return $strlang=="fr" ? "les années ".$lngyear : "the ".$lngyear."s";
	}
	return (string)$lngyear;
}

function f_t2srep_drill_share_question($jsontext2sql, $arrsegment, $strlang='en') {
	//Purpose: The follow-up of a share part, "" when the part asks none: one share alone, the rest
	//			of a share, no question or no label column
	$arrshare=(is_array($jsontext2sql) && isset($jsontext2sql['summary_facts']['share']) && is_array($jsontext2sql['summary_facts']['share'])) ? $jsontext2sql['summary_facts']['share'] : null;
	if ($arrshare===null || (isset($arrshare['kind']) && $arrshare['kind']=="single") || !empty($arrsegment['rest'])
		|| !isset($arrshare['label_column']) || (string)$arrshare['label_column']==="") {
		return "";
	}
	$strquestion=isset($jsontext2sql['question']) ? (string)$jsontext2sql['question'] : "";
	return f_t2srep_drill_question($strquestion, f_t2srep_drill_word($arrshare['label_column'], $strlang), $arrsegment['label'], $strlang);
}

function f_t2srep_drill_link($strhref, $strinner, $strclass, $strlabel, $inttabindex=true) {
	//Purpose: A bucket as a link to its follow-up: focusable, named for a screen reader by what it
	//			shows and the question it asks; $inttabindex false keeps it out of the tab order (a
	//			second way to the same link, in a drawing hidden from screen readers)
	return "<a class=\"".f_t2srep_h($strclass)."\" href=\"".f_t2srep_h($strhref)."\""
		.($inttabindex ? " aria-label=\"".f_t2srep_h($strlabel)."\"" : " tabindex=\"-1\"")
		." title=\"".f_t2srep_h($strlabel)."\">".$strinner."</a>";
}

function f_t2srep_pie_svg($arrsegments, $arrhrefs) {
	//Purpose: The pie of a share bar asked as a pie: an SVG ring, one arc per part in the order and
	//			hues of the bar, from the top, clockwise. The ring is a circle of circumference 100,
	//			so an arc of share s is a dash of s*100. Hidden from screen readers: the legend says
	//			it all. An arc whose part asks a follow-up links to it (out of the tab order, the
	//			legend holds the focusable link). Mirrors renderSharePie() of voice-agent.
	$strsvg="<svg class=\"t2sPie\" viewBox=\"0 0 42 42\" aria-hidden=\"true\" focusable=\"false\">"
		."<circle class=\"t2sPieHole\" cx=\"21\" cy=\"21\" r=\"15.91549\"></circle>";
	$dblstart=0;
	$lngcount=count($arrsegments);
	foreach ($arrsegments as $lngi=>$arrsegment) {
		$dbllen=max(0, (float)$arrsegment['share'])*100;
		$strhue=$arrsegment['rest'] ? "rest" : (string)($lngi % 8);
		$dbldash=$lngcount>1 ? max($dbllen-0.6, min($dbllen, 0.4)) : $dbllen;
		$strcircle="<circle class=\"t2sPieSeg\" data-hue=\"".$strhue."\" cx=\"21\" cy=\"21\" r=\"15.91549\""
			." stroke-dasharray=\"".round($dbldash, 3)." ".round(100-$dbldash, 3)."\" stroke-dashoffset=\"".round(25-$dblstart, 3)."\">"
			."<title>".f_t2srep_h(f_t2srep_ucfirst($arrsegment['label'])." ".$arrsegment['pcttext'])."</title></circle>";
		$strhref=isset($arrhrefs[$lngi]) ? $arrhrefs[$lngi] : "";
		$strsvg.=$strhref!="" ? "<a class=\"t2sPieLink\" href=\"".f_t2srep_h($strhref)."\" tabindex=\"-1\">".$strcircle."</a>" : $strcircle;
		$dblstart+=$dbllen;
	}
	return $strsvg."</svg>";
}

function f_t2srep_flag_emoji($strcode) {
	//Purpose: The flag emoji of an ISO 3166-1 alpha-2 code: its two regional-indicator letters
	$strcode=strtoupper(trim((string)$strcode));
	if (!preg_match('/^[A-Z]{2}$/', $strcode)) {
		return "";
	}
	//U+1F1E6 + n is F0 9F 87 A6+n in UTF-8 for every letter: no mbstring needed
	return "\xF0\x9F\x87".chr(0xA6+ord($strcode[0])-65)."\xF0\x9F\x87".chr(0xA6+ord($strcode[1])-65);
}

function f_t2srep_fold_name($strtext) {
	//Purpose: A name folded for comparison: lowercase, the usual accents stripped
	return strtolower(trim(strtr((string)$strtext, array("é"=>"e", "è"=>"e", "ê"=>"e", "ë"=>"e", "à"=>"a", "â"=>"a", "ä"=>"a", "á"=>"a", "å"=>"a",
		"î"=>"i", "ï"=>"i", "í"=>"i", "ô"=>"o", "ö"=>"o", "ó"=>"o", "û"=>"u", "ü"=>"u", "ú"=>"u", "ç"=>"c", "ñ"=>"n", "É"=>"e", "Å"=>"a"))));
}

function f_t2srep_country_name($arrentry, $strlang='en') {
	//Purpose: The name a row shows: the place's own name when it is not the country's (England
	//			stays England under the United Kingdom flag), else the country's name in the UI language
	$arrnames=isset($arrentry['name']) && is_array($arrentry['name']) ? $arrentry['name'] : null;
	$strlabel=trim((string)(isset($arrentry['label']) ? $arrentry['label'] : ""));
	if ($strlabel!=="" && ($arrnames===null || f_t2srep_fold_name($strlabel)!==f_t2srep_fold_name(isset($arrnames['en']) ? $arrnames['en'] : ""))) {
		return $strlabel;
	}
	$strname=$arrnames!==null ? (string)(isset($arrnames[$strlang=="fr" ? "fr" : "en"]) ? $arrnames[$strlang=="fr" ? "fr" : "en"] : "") : "";
	if ($strname!=="") {
		return $strname;
	}
	return $strlabel!=="" ? $strlabel : (string)(isset($arrentry['code']) ? $arrentry['code'] : "");
}

function f_t2srep_flag_model($jsontext2sql, $strlang='en') {
	//Purpose: Mirrors text2SqlFlagModel(): one item per country of summary_facts.countries, or null
	if (!is_array($jsontext2sql) || !isset($jsontext2sql['summary_facts']['countries']) || !is_array($jsontext2sql['summary_facts']['countries'])) {
		return null;
	}
	$arrcountries=$jsontext2sql['summary_facts']['countries'];
	$arrlist=isset($arrcountries['countries']) && is_array($arrcountries['countries']) ? array_values($arrcountries['countries']) : array();
	if (count($arrlist)==0) {
		return null;
	}
	$dblmax=0;
	foreach ($arrlist as $arrentry) {
		if (isset($arrentry['value']) && f_t2srep_is_number($arrentry['value'])) {
			$dblmax=max($dblmax, (float)$arrentry['value']);
		}
	}
	$arritems=array();
	foreach ($arrlist as $arrentry) {
		$value=isset($arrentry['value']) && f_t2srep_is_number($arrentry['value']) ? (float)$arrentry['value'] : null;
		$strcode=strtoupper(trim((string)(isset($arrentry['code']) ? $arrentry['code'] : "")));
		$arritems[]=array(
			"code"=>$strcode,
			"flag"=>(!empty($arrentry['flag']) && $strcode!=="") ? f_t2srep_flag_emoji($strcode) : "",
			"name"=>f_t2srep_country_name($arrentry, $strlang),
			"value"=>$value,
			"valuetext"=>$value===null ? "" : f_t2srep_plain_number($value),
			"pct"=>($value!==null && $dblmax>0) ? max(2, (int)round($value/$dblmax*100)) : 0,
			"index"=>isset($arrentry['index']) && f_t2srep_is_number($arrentry['index']) ? (int)$arrentry['index'] : null,
		);
	}
	return array("items"=>$arritems, "valuecolumn"=>isset($arrcountries['value_column']) ? (string)$arrcountries['value_column'] : "",
		"source"=>isset($arrcountries['source']) ? (string)$arrcountries['source'] : "");
}

function f_t2srep_render_flag_list($jsontext2sql, $strlang='en') {
	//Purpose: The flag list: one row per country, the flag, the name, the count with a thin bar
	//			when the rows carry one. The flag is the code in a badge until f_t2srep_flags_js()
	//			finds that the browser draws flag emoji (Windows draws two letters), then the emoji;
	//			the name is always written. A location row links to its page.
	$arrmodel=f_t2srep_flag_model($jsontext2sql, $strlang);
	$arrrecords=f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array());
	if ($arrmodel===null) {
		return f_t2srep_render_table($arrrecords, f_t2srep_columns($jsontext2sql), $strlang);
	}
	$strhtml="<ul class=\"t2sFlags\">";
	foreach ($arrmodel['items'] as $arritem) {
		$strflag="<span class=\"t2sFlag\" aria-hidden=\"true\">"
			.($arritem['flag']!=="" ? "<span class=\"t2sFlagEmoji\">".$arritem['flag']."</span>" : "")
			."<span class=\"t2sFlagCode".($arritem['flag']!=="" ? "" : " t2sFlagAlways")."\">".f_t2srep_h($arritem['code']!=="" ? $arritem['code'] : "?")."</span></span>";
		$strhref=($arrmodel['source']=="locations" && $arritem['index']!==null && isset($arrrecords[$arritem['index']])) ? f_t2srep_entity_href($arrrecords[$arritem['index']]) : "";
		$strname=$strhref!="" ? "<a href=\"".f_t2srep_h($strhref)."\">".f_t2srep_h($arritem['name'])."</a>" : f_t2srep_h($arritem['name']);
		$strhtml.="<li class=\"t2sFlagRow".($arritem['valuetext']!=="" ? " t2sFlagRowValue" : "")."\">".$strflag."<span class=\"t2sFlagName\">".$strname."</span>";
		if ($arritem['valuetext']!=="") {
			$strhtml.="<span class=\"t2sFlagBody\"><span class=\"t2sBarTrack\" aria-hidden=\"true\"><span class=\"t2sBarFill\" style=\"width:".$arritem['pct']."%\"></span></span>"
				."<span class=\"t2sFlagValue\">".f_t2srep_h($arritem['valuetext'])."</span></span>";
		}
		$strhtml.="</li>";
	}
	return $strhtml."</ul>";
}

function f_t2srep_flags_js() {
	//Purpose: Mirrors text2SqlFlagEmojiSupported(): draws one flag on a canvas and, when colour
	//			pixels come out (a real flag, not two grey letters), sets t2sFlagEmojiOk on the root
	//			element so the emoji replaces the code badge. A plain string with no PHP value in it.
	return "(function(){try{var c=document.createElement('canvas');c.width=24;c.height=24;var x=c.getContext('2d');if(!x){return;}"
		."x.textBaseline='top';x.font='20px sans-serif';x.fillText('\\uD83C\\uDDEB\\uD83C\\uDDF7',0,0);var d=x.getImageData(0,0,24,24).data;"
		."for(var i=0;i<d.length;i+=4){if(d[i+3]>0&&(Math.abs(d[i]-d[i+1])>40||Math.abs(d[i+1]-d[i+2])>40||Math.abs(d[i]-d[i+2])>40)){document.documentElement.classList.add('t2sFlagEmojiOk');return;}}}catch(e){}})();";
}

function f_t2srep_caption_flags($jsontext2sql, $strlang='en', $inthasmore=false, $intonmap=false) {
	//Purpose: With a count: "The first 13 countries by number of films; Japan leads with 93
	//			films." / "Les 13 premiers pays par nombre de films ; Japon arrive en tête avec 93
	//			films."; without: "Four countries: Angola, Australia, Namibia and South Africa." /
	//			"Quatre pays : Angola, Australie, Namibie et Afrique du Sud." On the map ($intonmap,
	//			phase 3 lot B), "on the map" / "sur la carte" after the lead, as voice-agent.
	$arrmodel=f_t2srep_flag_model($jsontext2sql, $strlang);
	if ($arrmodel===null) {
		return "";
	}
	$intfr=($strlang=="fr");
	$lngn=count($arrmodel['items']);
	$strntext=($lngn<=12 && $lngn>1) ? f_t2srep_count_word($lngn, $strlang, "m") : (string)$lngn;
	$strcountries=$intfr ? "pays" : ($lngn==1 ? "country" : "countries");
	$strwhere=$intonmap ? ($intfr ? " sur la carte" : " on the map") : "";
	$arrfacts=$jsontext2sql['summary_facts'];
	$strorder=isset($arrfacts['order']) ? (string)$arrfacts['order'] : "";
	$arrtop=null;
	foreach ($arrmodel['items'] as $arritem) {
		if ($arritem['value']!==null && ($arrtop===null || $arritem['value']>$arrtop['value'])) {
			$arrtop=$arritem;
		}
	}
	if ($arrtop!==null) {
		$intranking=($strorder!="" && $strorder!="collection" && $strorder!="date");
		if ($intranking || $inthasmore) {
			$strlead=$intfr ? "les ".$strntext." premiers pays" : "the first ".$strntext." countries";
			if ($intranking) {
				$arrword=f_t2srep_metric_word($strorder, $strlang);
				$strlead.=($intfr ? " par " : " by ").$arrword['label'];
			}
		} else {
			$strlead=f_t2srep_count_word($lngn, $strlang, "m")." ".$strcountries;
		}
		$arrnoun=f_t2srep_counted_noun($arrmodel['valuecolumn'], $arrtop['value'], $strlang);
		$strcount=$arrtop['valuetext']." ".$arrnoun['word'];
		return f_t2srep_ucfirst($strlead).$strwhere.($intfr ? " ; ".$arrtop['name']." arrive en tête avec ".$strcount : "; ".$arrtop['name']." leads with ".$strcount).".";
	}
	$arrnames=array();
	foreach ($arrmodel['items'] as $arritem) {
		if ($arritem['name']!=="") {
			$arrnames[]=$arritem['name'];
		}
	}
	$arrshown=array_slice($arrnames, 0, 5);
	if (count($arrnames)>5) {
		$strlist=implode(", ", $arrshown)." ".($intfr ? "et ".(count($arrnames)-5)." autres" : "and ".(count($arrnames)-5)." more");
	} elseif (count($arrshown)>1) {
		$strlast=array_pop($arrshown);
		$strlist=implode(", ", $arrshown)." ".($intfr ? "et" : "and")." ".$strlast;
	} else {
		$strlist=implode("", $arrshown);
	}
	$strlead=$inthasmore ? ($intfr ? "les ".$strntext." premiers pays" : "the first ".$strntext." countries") : f_t2srep_count_word($lngn, $strlang, "m")." ".$strcountries;
	return f_t2srep_ucfirst($strlead).$strwhere.($intfr ? " : " : ": ").$strlist.".";
}

/* ---------------------------------------------------------------------------------------------
 * Phase 3 lot B: map of countries. Mirror of voice-agent (text2SqlMapModel(), renderMap()): the
 * same shades, legend, notes and caption, word for word. The outline, html/data/world-110m.json,
 * is Natural Earth 1:110m (public domain) from the npm package world-atlas 2.0.2 (ISC), projected
 * once in Equal Earth by tools/build-world-map.py, the same bytes as voice-agent's: no library,
 * no tile server, no third-party request. The shading steps are the API's (summary_facts.map).
 * ------------------------------------------------------------------------------------------- */

function f_t2srep_world_map($strpath=null) {
	//Purpose: The world outline, decoded once per request, or null when the file cannot be read
	//Params:	$strpath: Another file (tests); default html/data/world-110m.json
	static $arrcache=array();
	$strpath=$strpath!==null ? $strpath : __DIR__."/../html/data/world-110m.json";
	if (!array_key_exists($strpath, $arrcache)) {
		$strjson=is_readable($strpath) ? file_get_contents($strpath) : false;
		$arrworld=$strjson!==false ? json_decode($strjson, true) : null;
		$arrcache[$strpath]=(is_array($arrworld) && isset($arrworld['shapes']) && is_array($arrworld['shapes'])) ? $arrworld : null;
	}
	return $arrcache[$strpath];
}

function f_t2srep_map_tone($lngstep, $lngsteps) {
	//Purpose: Mirrors text2SqlMapTone(): the tone (0 to 4, dim to bright) of a step among k steps
	if ($lngsteps<=1) {
		return 4;
	}
	return (int)round($lngstep*4/($lngsteps-1));
}

function f_t2srep_map_step_text($arrstep) {
	//Purpose: Mirrors text2SqlMapStepText(): "39-55", or "2" for a one-value step
	$strlo=f_t2srep_plain_number($arrstep['min']);
	return ((float)$arrstep['min']==(float)$arrstep['max']) ? $strlo : $strlo."-".f_t2srep_plain_number($arrstep['max']);
}

function f_t2srep_map_model($jsontext2sql, $strlang='en', $arrworld=null) {
	//Purpose: Mirrors text2SqlMapModel(): which country takes which shade, the legend, the hint
	//			line and the countries the outline has no place for
	//Returns: array(width, height, land, countries, rows, legend, legendtitle, hint, offtext), or
	//			null without the facts or the outline
	$arrflags=f_t2srep_flag_model($jsontext2sql, $strlang);
	if ($arrworld===null) {
		$arrworld=f_t2srep_world_map();
	}
	if ($arrflags===null || !is_array($arrworld) || !isset($arrworld['shapes']) || !is_array($arrworld['shapes']) || !isset($jsontext2sql['summary_facts']['map']) || !is_array($jsontext2sql['summary_facts']['map'])) {
		return null;
	}
	$arrlist=array_values($jsontext2sql['summary_facts']['countries']['countries']);
	$arrfacts=$jsontext2sql['summary_facts']['map'];
	$intfr=($strlang=="fr");
	$arrshapes=$arrworld['shapes'];
	$arrdots=isset($arrworld['dots']) && is_array($arrworld['dots']) ? $arrworld['dots'] : array();
	$dblwidth=isset($arrworld['width']) && (float)$arrworld['width']>0 ? (float)$arrworld['width'] : 1;
	$dblheight=isset($arrworld['height']) && (float)$arrworld['height']>0 ? (float)$arrworld['height'] : 1;
	$arrsteps=array();
	foreach ((isset($arrfacts['steps']) && is_array($arrfacts['steps']) ? $arrfacts['steps'] : array()) as $arrstep) {
		if (is_array($arrstep) && isset($arrstep['min'], $arrstep['max']) && f_t2srep_is_number($arrstep['min']) && f_t2srep_is_number($arrstep['max'])) {
			$arrsteps[]=$arrstep;
		}
	}
	$arrgroups=array();
	$arroff=array();
	$arrrows=array();
	foreach ($arrflags['items'] as $lngi=>$arritem) {
		$strnumeric=isset($arrlist[$lngi]['numeric']) ? (string)$arrlist[$lngi]['numeric'] : "";
		$strplaced=($strnumeric!=="" && (isset($arrshapes[$strnumeric]) || isset($arrdots[$strnumeric]))) ? $strnumeric : "";
		$arrrows[]=array("name"=>$arritem['name'], "valuetext"=>$arritem['valuetext'], "onmap"=>$strplaced!=="");
		if ($strplaced==="") {
			$arroff[]=$arritem;
			continue;
		}
		if (!isset($arrgroups[$strplaced])) {
			$arrgroups[$strplaced]=array("numeric"=>$strplaced, "names"=>array(), "value"=>null);
		}
		if ($arritem['name']!=="" && !in_array($arritem['name'], $arrgroups[$strplaced]['names'], true)) {
			$arrgroups[$strplaced]['names'][]=$arritem['name'];
		}
		if ($arritem['value']!==null) {
			$arrgroups[$strplaced]['value']=($arrgroups[$strplaced]['value']===null ? 0 : $arrgroups[$strplaced]['value'])+$arritem['value'];
		}
	}
	$strvaluecolumn=$arrflags['valuecolumn'];
	$arrcountries=array();
	$intwithvalues=false;
	foreach ($arrgroups as $arrgroup) {
		$strnumeric=(string)$arrgroup['numeric'];
		$lngtone=null;
		if (count($arrsteps)>0 && $arrgroup['value']!==null) {
			$lngstep=count($arrsteps)-1;
			foreach ($arrsteps as $lngk=>$arrstep) {
				if ($arrgroup['value']<=(float)$arrstep['max']) {
					$lngstep=$lngk;
					break;
				}
			}
			$lngtone=f_t2srep_map_tone($lngstep, count($arrsteps));
		}
		$strname=implode(", ", $arrgroup['names']);
		$strvaluetext=$arrgroup['value']===null ? "" : f_t2srep_plain_number($arrgroup['value']);
		if ($strvaluetext!=="") {
			$intwithvalues=true;
			$arrnoun=f_t2srep_counted_noun($strvaluecolumn, $arrgroup['value'], $strlang);
			$strlabel=$strname.($intfr ? " : " : ": ").$strvaluetext." ".$arrnoun['word'];
		} else {
			$strlabel=$strname;
		}
		$arrat=isset($arrdots[$strnumeric]) && is_array($arrdots[$strnumeric]) ? $arrdots[$strnumeric] : null;
		$arrcountries[]=array(
			"numeric"=>$strnumeric,
			"name"=>$strname,
			"value"=>$arrgroup['value'],
			"valuetext"=>$strvaluetext,
			"label"=>$strlabel,
			"tone"=>$lngtone,
			"shape"=>isset($arrshapes[$strnumeric]) ? (string)$arrshapes[$strnumeric] : "",
			"dot"=>$arrat!==null ? array("left"=>round($arrat[0]/$dblwidth*10000)/100, "top"=>round($arrat[1]/$dblheight*10000)/100) : null,
		);
	}
	$arrkeys=array();
	foreach (array_keys($arrshapes) as $key) {
		if (!isset($arrgroups[(string)$key])) {
			$arrkeys[]=(string)$key;
		}
	}
	sort($arrkeys, SORT_STRING);
	$strland="";
	foreach ($arrkeys as $strkey) {
		$strland.=$arrshapes[$strkey];
	}
	$strland.=isset($arrworld['other']) ? (string)$arrworld['other'] : "";
	$arrlegend=array();
	foreach ($arrsteps as $lngk=>$arrstep) {
		$arrlegend[]=array("text"=>f_t2srep_map_step_text($arrstep), "tone"=>f_t2srep_map_tone($lngk, count($arrsteps)));
	}
	$stroff="";
	if (count($arroff)>0) {
		$arrnames=array();
		foreach ($arroff as $arritem) {
			$arrnames[]=$arritem['valuetext']!=="" ? $arritem['name']." (".$arritem['valuetext'].")" : $arritem['name'];
		}
		$stroff=($intfr ? "Hors carte : " : "Not on the map: ").implode(", ", $arrnames).".";
	}
	$strlegendtitle="";
	if (count($arrsteps)>0) {
		$arrword=f_t2srep_metric_word($strvaluecolumn, $strlang);
		$strlegendtitle=f_t2srep_ucfirst($arrword['label']);
	}
	return array(
		"width"=>$dblwidth,
		"height"=>$dblheight,
		"land"=>$strland,
		"countries"=>$arrcountries,
		"rows"=>$arrrows,
		"legend"=>$arrlegend,
		"legendtitle"=>$strlegendtitle,
		"hint"=>$intwithvalues
			? ($intfr ? "Touchez ou survolez un pays pour voir son nombre." : "Tap or hover over a country to see its count.")
			: ($intfr ? "Touchez ou survolez un pays pour voir son nom." : "Tap or hover over a country to see its name."),
		"offtext"=>$stroff,
	);
}

function f_t2srep_render_map($jsontext2sql, $strlang='en', $arrworld=null) {
	//Purpose: Mirrors renderMap(): one SVG for the land and the shaded countries, the small
	//			countries as dots laid over it by percentage (so a dot keeps its size at any width),
	//			a line under the map for the country under the finger (f_t2srep_map_js(); without
	//			script each country and dot keeps its title), the legend, the countries the map has
	//			no place for, and a visually hidden table. Without the outline, the flag list.
	$arrmodel=f_t2srep_map_model($jsontext2sql, $strlang, $arrworld);
	if ($arrmodel===null) {
		return f_t2srep_render_flag_list($jsontext2sql, $strlang);
	}
	$intfr=($strlang=="fr");
	$strsvg="<svg class=\"t2sMapSvg\" viewBox=\"0 0 ".$arrmodel['width']." ".$arrmodel['height']."\" role=\"img\" aria-label=\"".($intfr ? "Carte du monde" : "World map")."\">"
		."<path class=\"t2sMapLand\" d=\"".f_t2srep_h($arrmodel['land'])."\"/>";
	$strdots="";
	foreach ($arrmodel['countries'] as $arrcountry) {
		$strtone=$arrcountry['tone']===null ? "t2sMapOn" : "t2sMapTone".$arrcountry['tone'];
		$strlabel=f_t2srep_h($arrcountry['label']);
		if ($arrcountry['shape']!=="") {
			$strsvg.="<path class=\"t2sMapCountry ".$strtone."\" d=\"".f_t2srep_h($arrcountry['shape'])."\" tabindex=\"0\" aria-label=\"".$strlabel."\" data-t2smap=\"".f_t2srep_h($arrcountry['numeric'])."\">"
				."<title>".$strlabel."</title></path>";
		}
		if ($arrcountry['dot']!==null) {
			$strdots.="<span class=\"t2sMapDot ".$strtone."\" role=\"button\" tabindex=\"0\" style=\"left:".$arrcountry['dot']['left']."%;top:".$arrcountry['dot']['top']."%\""
				." title=\"".$strlabel."\" aria-label=\"".$strlabel."\" data-t2smap=\"".f_t2srep_h($arrcountry['numeric'])."\"></span>";
		}
	}
	$strhtml="<div class=\"t2sMap\"><div class=\"t2sMapFrame\">".$strsvg."</svg>".$strdots."</div>"
		."<p class=\"t2sMapTip\" aria-live=\"polite\">".f_t2srep_h($arrmodel['hint'])."</p>";
	if (count($arrmodel['legend'])>0) {
		$strhtml.="<div class=\"t2sMapLegend\"><span class=\"t2sMapLegendTitle\">".f_t2srep_h($arrmodel['legendtitle'])."</span>";
		foreach ($arrmodel['legend'] as $arrstep) {
			$strhtml.="<span class=\"t2sMapLegendItem\"><span class=\"t2sMapSwatch t2sMapTone".$arrstep['tone']."\" aria-hidden=\"true\"></span><span>".f_t2srep_h($arrstep['text'])."</span></span>";
		}
		$strhtml.="</div>";
	}
	if ($arrmodel['offtext']!=="") {
		$strhtml.="<p class=\"t2sMapOff\">".f_t2srep_h($arrmodel['offtext'])."</p>";
	}
	$strhtml.="<div class=\"t2sSrOnly\"><table><thead><tr><th scope=\"col\">".($intfr ? "Pays" : "Country")."</th><th scope=\"col\">"
		.f_t2srep_h($arrmodel['legendtitle']!=="" ? $arrmodel['legendtitle'] : ($intfr ? "Sur la carte" : "On the map"))."</th></tr></thead><tbody>";
	foreach ($arrmodel['rows'] as $arrrow) {
		$strvalue=$arrrow['valuetext']!=="" ? $arrrow['valuetext'] : ($arrrow['onmap'] ? ($intfr ? "oui" : "yes") : ($intfr ? "hors carte" : "not on the map"));
		$strhtml.="<tr><td>".f_t2srep_h($arrrow['name'])."</td><td>".f_t2srep_h($strvalue)."</td></tr>";
	}
	return $strhtml."</tbody></table></div></div>";
}

function f_t2srep_map_js() {
	//Purpose: As voice-agent: hovering, focusing or tapping a country or a dot outlines it (with
	//			its dot or shape) and writes its name and count, its aria-label, in the line under
	//			the map. A plain string with no PHP value in it.
	return "(function(){function show(el){var root=el.closest('.t2sMap');if(!root){return;}var tip=root.querySelector('.t2sMapTip');"
		."if(tip){tip.textContent=el.getAttribute('aria-label')||'';}var old=root.querySelectorAll('.isSelected'),i;for(i=0;i<old.length;i++){old[i].classList.remove('isSelected');}"
		."var marks=root.querySelectorAll('[data-t2smap=\"'+el.getAttribute('data-t2smap')+'\"]');for(i=0;i<marks.length;i++){marks[i].classList.add('isSelected');}}"
		."function find(ev){var t=ev.target;return t&&t.closest?t.closest('.t2sMap [data-t2smap]'):null;}"
		."['mouseover','focusin','click'].forEach(function(n){document.addEventListener(n,function(ev){var el=find(ev);if(el){show(el);}});});})();";
}

/* ---------------------------------------------------------------------------------------------
 * Phase 3 lot A: scatter. Mirror of voice-agent (text2SqlScatterWords(), text2SqlScatterTicks(),
 * text2SqlScatterModel(), text2SqlScatterSummary(), renderScatter()): the same ticks, words and
 * sentences, word for word. The points are the API's (summary_facts.scatter), never re-read from
 * the rows.
 * ------------------------------------------------------------------------------------------- */

function f_t2srep_scatter_words($arraxis, $strlang='en') {
	//Purpose: How an axis is named: array("name", "without", "of"). "budget", "a budget" /
	//			"budget", "budget", "du budget": the name, the object of "without" / "sans", and
	//			the French "de" form ("recettes en fonction du budget")
	//			[EN name, EN without, FR name, FR sans, FR de]
	$arrwords=array(
		"budget"=>array("budget", "a budget", "budget", "budget", "du budget"),
		"revenue"=>array("box-office revenue", "box-office revenue", "recettes", "recettes", "des recettes"),
		"profit"=>array("profit", "a profit", "bénéfice", "bénéfice", "du bénéfice"),
		"runtime"=>array("runtime", "a runtime", "durée", "durée", "de la durée"),
		"seasons"=>array("number of seasons", "a season count", "nombre de saisons", "nombre de saisons", "du nombre de saisons"),
		"episodes"=>array("number of episodes", "an episode count", "nombre d'épisodes", "nombre d'épisodes", "du nombre d'épisodes"),
		"popularity"=>array("popularity", "a popularity", "popularité", "popularité", "de la popularité"),
		"age"=>array("age", "an age", "âge", "âge", "de l'âge"),
		"rating_imdb"=>array("IMDb rating", "an IMDb rating", "note IMDb", "note IMDb", "de la note IMDb"),
		"rating_tmdb"=>array("TMDb rating", "a TMDb rating", "note TMDb", "note TMDb", "de la note TMDb"),
		"rating"=>array("rating", "a rating", "note", "note", "de la note"),
		"votes_imdb"=>array("IMDb votes", "IMDb votes", "votes IMDb", "votes IMDb", "des votes IMDb"),
		"votes"=>array("votes", "votes", "votes", "votes", "des votes"),
		"year_birth"=>array("birth year", "a birth year", "année de naissance", "année de naissance", "de l'année de naissance"),
		"year_death"=>array("death year", "a death year", "année de décès", "année de décès", "de l'année de décès"),
		"year_air"=>array("first air year", "a first air date", "année de première diffusion", "date de diffusion", "de l'année de première diffusion"),
		"year_release"=>array("release year", "a release year", "année de sortie", "année de sortie", "de l'année de sortie"),
		"year"=>array("year", "a year", "année", "année", "de l'année"),
	);
	$strquantity=isset($arraxis['quantity']) ? (string)$arraxis['quantity'] : "";
	$strcolumn=isset($arraxis['column']) ? strtoupper((string)$arraxis['column']) : "";
	$strkey=$strquantity;
	if ($strquantity=="rating") {
		$strkey=preg_match('/IMDB/', $strcolumn) ? "rating_imdb" : (preg_match('/TMDB|VOTE_AVERAGE/', $strcolumn) ? "rating_tmdb" : "rating");
	} elseif ($strquantity=="votes") {
		$strkey=preg_match('/IMDB/', $strcolumn) ? "votes_imdb" : "votes";
	} elseif ($strquantity=="year") {
		$strkey=preg_match('/BIRTH/', $strcolumn) ? "year_birth" : (preg_match('/DEATH/', $strcolumn) ? "year_death"
			: (preg_match('/AIR/', $strcolumn) ? "year_air" : (preg_match('/RELEASE/', $strcolumn) ? "year_release" : "year")));
	}
	if (isset($arrwords[$strkey])) {
		$arrentry=$arrwords[$strkey];
		return $strlang=="fr" ? array("name"=>$arrentry[2], "without"=>$arrentry[3], "of"=>$arrentry[4]) : array("name"=>$arrentry[0], "without"=>$arrentry[1], "of"=>"");
	}
	$arrword=f_t2srep_metric_word($strcolumn, $strlang);
	$strname=$arrword['label'];
	return array("name"=>$strname, "without"=>$strname, "of"=>(preg_match('/^[aeiouyéèêàâîôûh]/iu', $strname) ? "de l'".$strname : "du ".$strname));
}

function f_t2srep_scatter_axis_title($arraxis, $strlang='en') {
	//Purpose: "Budget ($)", "Runtime (min)", "IMDb votes (log scale)" / "Votes IMDb (échelle log)"
	$arrwords=f_t2srep_scatter_words($arraxis, $strlang);
	$strunit=isset($arraxis['unit']) ? (string)$arraxis['unit'] : "";
	$strsuffix=$strunit=="usd" ? " ($)" : ($strunit=="minutes" ? " (min)" : "");
	$strlog=(isset($arraxis['scale']) && $arraxis['scale']=="log") ? ($strlang=="fr" ? " (échelle log)" : " (log scale)") : "";
	return f_t2srep_ucfirst($arrwords['name']).$strsuffix.$strlog;
}

function f_t2srep_trim_number($dblvalue, $lngdigits) {
	//Purpose: Mirrors text2SqlTrimNumber(): at most $lngdigits decimals, trailing zeros dropped
	$strtext=number_format((float)$dblvalue, $lngdigits, '.', '');
	if (strpos($strtext, '.')!==false) {
		$strtext=rtrim(rtrim($strtext, '0'), '.');
	}
	return $strtext==="-0" ? "0" : $strtext;
}

function f_t2srep_scatter_short($dblabs) {
	//Purpose: A positive number in the short form: 1.25B, 150M, 2.5K, 7.5
	if ($dblabs>=1e9) {
		return f_t2srep_trim_number($dblabs/1e9, 2)."B";
	}
	if ($dblabs>=1e6) {
		return f_t2srep_trim_number($dblabs/1e6, 2)."M";
	}
	if ($dblabs>=1e3) {
		return f_t2srep_trim_number($dblabs/1e3, 2)."K";
	}
	return f_t2srep_trim_number($dblabs, 2);
}

function f_t2srep_scatter_tick_text($value, $arraxis) {
	//Purpose: Mirrors text2SqlScatterTickText(): money and big counts short ($150M, 10K), a year bare
	if (!f_t2srep_is_number($value)) {
		return "";
	}
	$dblvalue=(float)$value;
	$strunit=isset($arraxis['unit']) ? (string)$arraxis['unit'] : "";
	if ($strunit=="year") {
		return (string)(int)round($dblvalue);
	}
	$dblabs=abs($dblvalue);
	$strsign=$dblvalue<0 ? "-" : "";
	if ($strunit=="usd") {
		return $strsign."$".f_t2srep_scatter_short($dblabs);
	}
	if ($dblabs>=1000 && $strunit!="minutes") {
		return $strsign.f_t2srep_scatter_short($dblabs);
	}
	return f_t2srep_plain_number($dblvalue);
}

function f_t2srep_scatter_value_text($value, $arraxis) {
	//Purpose: Mirrors text2SqlScatterValueText() (written form): "$160M", "148 min", "1958",
	//			"8.4", "2,482,559"
	if (!f_t2srep_is_number($value)) {
		return "";
	}
	$dblvalue=(float)$value;
	$strunit=isset($arraxis['unit']) ? (string)$arraxis['unit'] : "";
	if ($strunit=="usd") {
		return f_t2srep_short_money($dblvalue);
	}
	if ($strunit=="minutes") {
		return f_t2srep_plain_number($dblvalue)." min";
	}
	if ($strunit=="year") {
		return (string)(int)round($dblvalue);
	}
	if ($strunit=="rating") {
		return number_format($dblvalue, 1, '.', '');
	}
	return f_t2srep_plain_number($dblvalue);
}

function f_t2srep_nice_step($dblraw) {
	//Purpose: The round step (1, 2, 2.5, 5 times a power of ten) at or above $dblraw
	if (!($dblraw>0) || !is_finite($dblraw)) {
		return 1.0;
	}
	$dblpower=pow(10, floor(log10($dblraw)));
	$dblf=$dblraw/$dblpower;
	$dblm=$dblf<=1 ? 1 : ($dblf<=2 ? 2 : ($dblf<=2.5 ? 2.5 : ($dblf<=5 ? 5 : 10)));
	return $dblm*$dblpower;
}

function f_t2srep_scatter_ticks($arraxis) {
	//Purpose: Mirrors text2SqlScatterTicks(): four to six round ticks over the axis's values,
	//			decades on a log axis; the low edge one step further when a point sits on it.
	//Returns: array("log", "lo", "hi", "ticks"=>array(array("value", "pos", "text"))), or null
	if (!isset($arraxis['min']) || !isset($arraxis['max']) || !f_t2srep_is_number($arraxis['min']) || !f_t2srep_is_number($arraxis['max'])) {
		return null;
	}
	$dbllo=(float)$arraxis['min'];
	$dblhi=(float)$arraxis['max'];
	$arrticks=array();
	if (isset($arraxis['scale']) && $arraxis['scale']=="log" && $dbllo>0) {
		$lnge0=(int)floor(log10($dbllo));
		$lnge1=(int)ceil(log10($dblhi));
		if ($lnge1<=$lnge0) {
			$lnge1=$lnge0+1;
		}
		$lngevery=($lnge1-$lnge0)>6 ? 2 : 1;
		for ($lnge=$lnge0; $lnge<=$lnge1; $lnge+=$lngevery) {
			$dblv=pow(10, $lnge);
			$arrticks[]=array("value"=>$dblv, "pos"=>($lnge-$lnge0)/($lnge1-$lnge0)*100, "text"=>f_t2srep_scatter_tick_text($dblv, $arraxis));
		}
		return array("log"=>true, "lo"=>(float)$lnge0, "hi"=>(float)$lnge1, "ticks"=>$arrticks);
	}
	if ($dblhi==$dbllo) {
		$dblpad=$dbllo!=0 ? abs($dbllo)*0.1 : 1;
		$dbllo-=$dblpad;
		$dblhi+=$dblpad;
	}
	$dblstep=f_t2srep_nice_step(($dblhi-$dbllo)/4);
	$strunit=isset($arraxis['unit']) ? (string)$arraxis['unit'] : "";
	$strquantity=isset($arraxis['quantity']) ? (string)$arraxis['quantity'] : "";
	if (in_array($strunit, array("year", "count"), true) || in_array($strquantity, array("seasons", "episodes", "year"), true)) {
		$dblstep=max(1, ceil($dblstep));
	}
	$dblstart=floor($dbllo/$dblstep)*$dblstep;
	$dblend=max(ceil($dblhi/$dblstep)*$dblstep, $dblstart+$dblstep);
	if ($dblstart==$dbllo && $dbllo!=0) {
		$dblstart=($dbllo>0 && $dblstart-$dblstep<0) ? 0 : $dblstart-$dblstep;
	}
	for ($lngi=0; $lngi<=12; $lngi++) {
		$dblv=(float)sprintf('%.12g', $dblstart+$lngi*$dblstep);
		if ($dblv>$dblend+$dblstep/1000) {
			break;
		}
		$arrticks[]=array("value"=>$dblv, "pos"=>($dblv-$dblstart)/($dblend-$dblstart)*100, "text"=>f_t2srep_scatter_tick_text($dblv, $arraxis));
	}
	return array("log"=>false, "lo"=>$dblstart, "hi"=>$dblend, "ticks"=>$arrticks);
}

function f_t2srep_scatter_pos($arrticks, $value) {
	//Purpose: Where a value sits on its axis, 0 to 100
	$dblvalue=(float)$value;
	if ($arrticks['log']) {
		return $dblvalue>0 ? (log10($dblvalue)-$arrticks['lo'])/($arrticks['hi']-$arrticks['lo'])*100 : 0;
	}
	return ($dblvalue-$arrticks['lo'])/($arrticks['hi']-$arrticks['lo'])*100;
}

function f_t2srep_scatter_model($jsontext2sql, $strlang='en') {
	//Purpose: Mirrors text2SqlScatterModel(): the two axes with their titles and ticks, one entry
	//			per point with its place (left and top in percent of the plot), its title and both
	//			values, the highest point marked
	//Returns: array("x", "y", "points", "highlight", "count", "plotted"), or null
	if (!is_array($jsontext2sql) || !isset($jsontext2sql['summary_facts']['scatter']) || !is_array($jsontext2sql['summary_facts']['scatter'])) {
		return null;
	}
	$arrscatter=$jsontext2sql['summary_facts']['scatter'];
	$arrpoints=isset($arrscatter['points']) && is_array($arrscatter['points']) ? $arrscatter['points'] : array();
	if (!isset($arrscatter['x']) || !isset($arrscatter['y']) || !is_array($arrscatter['x']) || !is_array($arrscatter['y']) || count($arrpoints)<2) {
		return null;
	}
	$arrxticks=f_t2srep_scatter_ticks($arrscatter['x']);
	$arryticks=f_t2srep_scatter_ticks($arrscatter['y']);
	if ($arrxticks===null || $arryticks===null) {
		return null;
	}
	$arrxwords=f_t2srep_scatter_words($arrscatter['x'], $strlang);
	$arrywords=f_t2srep_scatter_words($arrscatter['y'], $strlang);
	$lngymax=(isset($arrscatter['extremes']['y_max']) && is_int($arrscatter['extremes']['y_max'])) ? $arrscatter['extremes']['y_max'] : -1;
	$strsep=$strlang=="fr" ? " : " : ": ";
	$arritems=array();
	foreach (array_values($arrpoints) as $lngi=>$arrpoint) {
		$strxtext=f_t2srep_scatter_value_text(isset($arrpoint['x']) ? $arrpoint['x'] : null, $arrscatter['x']);
		$strytext=f_t2srep_scatter_value_text(isset($arrpoint['y']) ? $arrpoint['y'] : null, $arrscatter['y']);
		$strlabel=isset($arrpoint['label']) ? trim((string)$arrpoint['label']) : "";
		if ($strlabel==="") {
			$strlabel=isset($arrpoint['id']) ? (string)$arrpoint['id'] : "";
		}
		$arritems[]=array(
			"index"=>(isset($arrpoint['index']) && is_int($arrpoint['index'])) ? $arrpoint['index'] : null,
			"label"=>$strlabel,
			"left"=>round(f_t2srep_scatter_pos($arrxticks, $arrpoint['x']), 2),
			"top"=>round(100-f_t2srep_scatter_pos($arryticks, $arrpoint['y']), 2),
			"xtext"=>$strxtext,
			"ytext"=>$strytext,
			"values"=>f_t2srep_ucfirst($arrxwords['name'])." ".$strxtext." · ".f_t2srep_ucfirst($arrywords['name'])." ".$strytext,
			"aria"=>$strlabel.$strsep.$arrxwords['name']." ".$strxtext.", ".$arrywords['name']." ".$strytext,
			"highest"=>($lngi===$lngymax),
		);
	}
	return array(
		"x"=>array("title"=>f_t2srep_scatter_axis_title($arrscatter['x'], $strlang), "name"=>$arrxwords['name'], "ticks"=>$arrxticks['ticks']),
		"y"=>array("title"=>f_t2srep_scatter_axis_title($arrscatter['y'], $strlang), "name"=>$arrywords['name'], "ticks"=>$arryticks['ticks']),
		"points"=>$arritems,
		"highlight"=>($lngymax>=0 && $lngymax<count($arritems)) ? $lngymax : 0,
		"count"=>isset($arrscatter['count']) ? (int)$arrscatter['count'] : count($arrpoints),
		"plotted"=>isset($arrscatter['plotted']) ? (int)$arrscatter['plotted'] : count($arrpoints),
	);
}

function f_t2srep_scatter_detail($arrpoint, $arrrecord, $arrcolumns, $fnposter) {
	//Purpose: The line under the plot: poster, title (linked) and both values of one point
	$arrmedia=is_array($arrrecord) ? f_t2srep_entity_media($arrrecord, $arrcolumns, $fnposter) : array("title"=>"", "href"=>"", "url"=>"");
	$strtitle=f_t2srep_h($arrpoint['label']);
	return ($arrmedia['url']!="" ? "<img class=\"t2sScatterPoster\" src=\"".f_t2srep_h($arrmedia['url'])."\" alt=\"\" loading=\"lazy\">" : "")
		."<span class=\"t2sScatterDetailText\"><span class=\"t2sScatterDetailTitle\">"
		.($arrmedia['href']!="" ? "<a href=\"".f_t2srep_h($arrmedia['href'])."\">".$strtitle."</a>" : $strtitle)
		."</span><span class=\"t2sScatterDetailValues\">".f_t2srep_h($arrpoint['values'])."</span></span>";
}

function f_t2srep_render_scatter($jsontext2sql, $strlang='en', $fnposter=null) {
	//Purpose: Mirrors renderScatter(): one dot per point, x across and y up, no chart library. The
	//			grid is a small SVG stretched over the plot (its lines keep one pixel at any size); the
	//			dots and the tick labels are HTML placed by percentage, so the text keeps its size
	//			from a 390 px phone to a desktop. A dot is a 24 px link around a 10 px mark, titled;
	//			f_t2srep_scatter_js() makes the first tap show the point under the plot and the
	//			second follow the link (without script, a tap opens the page). The highest point is
	//			ringed and shown first; a visually hidden table lists every point.
	$arrmodel=f_t2srep_scatter_model($jsontext2sql, $strlang);
	$arrrecords=f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array());
	$arrcolumns=f_t2srep_columns($jsontext2sql);
	if ($arrmodel===null) {
		return f_t2srep_render_table($arrrecords, $arrcolumns, $strlang);
	}
	$strpair=$arrmodel['x']['title']." / ".$arrmodel['y']['title'];
	$strhtml="<figure class=\"t2sScatter\" role=\"group\" aria-label=\"".f_t2srep_h($strpair)."\">"
		."<div class=\"t2sScatterAxisTitle t2sScatterYTitle\" aria-hidden=\"true\">".f_t2srep_h($arrmodel['y']['title'])."</div>"
		."<div class=\"t2sScatterFrame\"><div class=\"t2sScatterYTicks\" aria-hidden=\"true\">";
	foreach ($arrmodel['y']['ticks'] as $arrtick) {
		$strhtml.="<span style=\"top:".round(100-$arrtick['pos'], 2)."%\">".f_t2srep_h($arrtick['text'])."</span>";
	}
	$strhtml.="</div><div class=\"t2sScatterPlot\"><svg class=\"t2sScatterGrid\" viewBox=\"0 0 100 100\" preserveAspectRatio=\"none\" aria-hidden=\"true\">";
	foreach ($arrmodel['x']['ticks'] as $arrtick) {
		$strpos=(string)round($arrtick['pos'], 2);
		$strhtml.="<line x1=\"".$strpos."\" y1=\"0\" x2=\"".$strpos."\" y2=\"100\" class=\"t2sScatterGridLine\" vector-effect=\"non-scaling-stroke\"/>";
	}
	foreach ($arrmodel['y']['ticks'] as $arrtick) {
		$strpos=(string)round(100-$arrtick['pos'], 2);
		$strhtml.="<line x1=\"0\" y1=\"".$strpos."\" x2=\"100\" y2=\"".$strpos."\" class=\"t2sScatterGridLine\" vector-effect=\"non-scaling-stroke\"/>";
	}
	$strhtml.="<line x1=\"0\" y1=\"100\" x2=\"100\" y2=\"100\" class=\"t2sScatterAxisLine\" vector-effect=\"non-scaling-stroke\"/>"
		."<line x1=\"0\" y1=\"0\" x2=\"0\" y2=\"100\" class=\"t2sScatterAxisLine\" vector-effect=\"non-scaling-stroke\"/></svg>";
	$strdetail="";
	foreach ($arrmodel['points'] as $lngi=>$arrpoint) {
		$arrrecord=($arrpoint['index']!==null && isset($arrrecords[$arrpoint['index']])) ? $arrrecords[$arrpoint['index']] : null;
		$arrmedia=is_array($arrrecord) ? f_t2srep_entity_media($arrrecord, $arrcolumns, $fnposter) : array("title"=>"", "href"=>"", "url"=>"");
		$intselected=($lngi===$arrmodel['highlight']);
		$strclass="t2sScatterDot".($arrpoint['highest'] ? " isHighest" : "").($intselected ? " isSelected" : "");
		$strattrs=" class=\"".$strclass."\" style=\"left:".$arrpoint['left']."%;top:".$arrpoint['top']."%\" title=\"".f_t2srep_h($arrpoint['aria'])."\" aria-label=\"".f_t2srep_h($arrpoint['aria'])."\""
			." data-t2stitle=\"".f_t2srep_h($arrpoint['label'])."\" data-t2svalues=\"".f_t2srep_h($arrpoint['values'])."\""
			.($arrmedia['url']!="" ? " data-t2sposter=\"".f_t2srep_h($arrmedia['url'])."\"" : "");
		if ($arrmedia['href']!="") {
			$strhtml.="<a href=\"".f_t2srep_h($arrmedia['href'])."\"".$strattrs."></a>";
		} else {
			$strhtml.="<span role=\"button\" tabindex=\"0\"".$strattrs."></span>";
		}
		if ($intselected) {
			$strdetail=f_t2srep_scatter_detail($arrpoint, $arrrecord, $arrcolumns, $fnposter);
		}
	}
	$strhtml.="</div><div class=\"t2sScatterCorner\"></div><div class=\"t2sScatterXTicks\" aria-hidden=\"true\">";
	foreach ($arrmodel['x']['ticks'] as $arrtick) {
		$strhtml.="<span style=\"left:".round($arrtick['pos'], 2)."%\">".f_t2srep_h($arrtick['text'])."</span>";
	}
	$strhtml.="</div></div><div class=\"t2sScatterAxisTitle t2sScatterXTitle\" aria-hidden=\"true\">".f_t2srep_h($arrmodel['x']['title'])."</div>"
		."<div class=\"t2sScatterDetail\" aria-live=\"polite\">".$strdetail."</div>"
		."<div class=\"t2sSrOnly\"><table><caption>".f_t2srep_h($strpair)."</caption><thead><tr><th scope=\"col\">".($strlang=="fr" ? "Titre" : "Title")."</th>"
		."<th scope=\"col\">".f_t2srep_h($arrmodel['x']['title'])."</th><th scope=\"col\">".f_t2srep_h($arrmodel['y']['title'])."</th></tr></thead><tbody>";
	foreach ($arrmodel['points'] as $arrpoint) {
		$strhtml.="<tr><td>".f_t2srep_h($arrpoint['label'])."</td><td>".f_t2srep_h($arrpoint['xtext'])."</td><td>".f_t2srep_h($arrpoint['ytext'])."</td></tr>";
	}
	return $strhtml."</tbody></table></div></figure>";
}

function f_t2srep_scatter_js() {
	//Purpose: The taps of a scatter, as voice-agent: the first tap (or Enter) on a dot shows its
	//			title, poster and values under the plot, a second tap on the same dot follows its
	//			link. A plain string with no PHP value in it.
	return "(function(){function e(s){var d=document.createElement('span');d.textContent=s;return d.innerHTML;}"
		."function pick(dot,ev){var root=dot.closest('.t2sScatter');if(!root){return;}"
		."if(dot.classList.contains('isSelected')){if(dot.tagName!=='A'){ev.preventDefault();}return;}"
		."ev.preventDefault();var old=root.querySelector('.t2sScatterDot.isSelected');if(old){old.classList.remove('isSelected');}"
		."dot.classList.add('isSelected');var box=root.querySelector('.t2sScatterDetail');if(!box){return;}"
		."var t=e(dot.getAttribute('data-t2stitle')||''),h=dot.getAttribute('href'),p=dot.getAttribute('data-t2sposter');"
		."box.innerHTML=(p?'<img class=\"t2sScatterPoster\" src=\"'+e(p).replace(/\"/g,'&quot;')+'\" alt=\"\" loading=\"lazy\">':'')"
		."+'<span class=\"t2sScatterDetailText\"><span class=\"t2sScatterDetailTitle\">'+(h?'<a href=\"'+e(h).replace(/\"/g,'&quot;')+'\">'+t+'</a>':t)"
		."+'</span><span class=\"t2sScatterDetailValues\">'+e(dot.getAttribute('data-t2svalues')||'')+'</span></span>';}"
		."document.addEventListener('click',function(ev){var dot=ev.target.closest&&ev.target.closest('.t2sScatterDot');if(dot){pick(dot,ev);}});"
		."document.addEventListener('keydown',function(ev){var dot=ev.target.closest&&ev.target.closest('span.t2sScatterDot');"
		."if(dot&&(ev.key==='Enter'||ev.key===' ')){pick(dot,ev);}});})();";
}

function f_t2srep_caption_scatter($jsontext2sql, $strlang='en', $inthasmore=false) {
	//Purpose: Mirrors text2SqlScatterSummary(): "18 films, runtime against IMDb rating; highest
	//			IMDb rating: Paths of Glory, 8.4." / "18 films sur 31, recettes en fonction du
	//			budget (13 sans budget ou sans recettes) ; maximum des recettes : L'Odyssée, $1.8B."
	//			"the first N" while another page may follow
	if (!is_array($jsontext2sql) || !isset($jsontext2sql['summary_facts']['scatter']) || !is_array($jsontext2sql['summary_facts']['scatter'])) {
		return "";
	}
	$arrscatter=$jsontext2sql['summary_facts']['scatter'];
	$arrpoints=isset($arrscatter['points']) && is_array($arrscatter['points']) ? array_values($arrscatter['points']) : array();
	if (!isset($arrscatter['x']) || !isset($arrscatter['y']) || !count($arrpoints)) {
		return "";
	}
	$intfr=($strlang=="fr");
	$lngcount=isset($arrscatter['count']) ? (int)$arrscatter['count'] : count($arrpoints);
	$lngplotted=isset($arrscatter['plotted']) ? (int)$arrscatter['plotted'] : count($arrpoints);
	$arrnoun=f_t2srep_noun(f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array()), f_t2srep_columns($jsontext2sql), $strlang);
	$strnoun=$arrnoun['plural'];
	$strfirst=$arrnoun['gender']=="f" ? "premières" : "premiers";
	if ($lngplotted>=$lngcount) {
		$strlead=$inthasmore ? ($intfr ? "les ".$lngcount." ".$strfirst." ".$strnoun : "the first ".$lngcount." ".$strnoun) : $lngcount." ".$strnoun;
	} elseif ($intfr) {
		$strlead=$inthasmore ? $lngplotted." des ".$lngcount." ".$strfirst." ".$strnoun : $lngplotted." ".$strnoun." sur ".$lngcount;
	} else {
		$strlead=$inthasmore ? $lngplotted." of the first ".$lngcount." ".$strnoun : $lngplotted." of ".$lngcount." ".$strnoun;
	}
	$arrx=f_t2srep_scatter_words($arrscatter['x'], $strlang);
	$arry=f_t2srep_scatter_words($arrscatter['y'], $strlang);
	$strtext=f_t2srep_ucfirst($strlead).", ".($intfr ? $arry['name']." en fonction ".$arrx['of'] : $arrx['name']." against ".$arry['name']);
	$lngleft=$lngcount-$lngplotted;
	if ($lngleft>0) {
		$lngmx=isset($arrscatter['missing']['x']) ? (int)$arrscatter['missing']['x'] : 0;
		$lngmy=isset($arrscatter['missing']['y']) ? (int)$arrscatter['missing']['y'] : 0;
		if ($intfr) {
			$strwhat=($lngmx && $lngmy) ? "sans ".$arrx['without']." ou sans ".$arry['without'] : ($lngmx ? "sans ".$arrx['without'] : "sans ".$arry['without']);
		} else {
			$strwhat="without ".(($lngmx && $lngmy) ? $arrx['without']." or ".$arry['without'] : ($lngmx ? $arrx['without'] : $arry['without']));
		}
		$strtext.=" (".$lngleft." ".$strwhat.")";
	}
	$lngymax=(isset($arrscatter['extremes']['y_max']) && is_int($arrscatter['extremes']['y_max'])) ? $arrscatter['extremes']['y_max'] : -1;
	if ($lngymax>=0 && isset($arrpoints[$lngymax])) {
		$strlabel=isset($arrpoints[$lngymax]['label']) ? trim((string)$arrpoints[$lngymax]['label']) : "";
		if ($strlabel!=="") {
			$strvalue=f_t2srep_scatter_value_text(isset($arrpoints[$lngymax]['y']) ? $arrpoints[$lngymax]['y'] : null, $arrscatter['y']);
			$strtext.=$intfr ? " ; maximum ".$arry['of']." : ".$strlabel.", ".$strvalue : "; highest ".$arry['name'].": ".$strlabel.", ".$strvalue;
		}
	}
	return $strtext.".";
}

/* ---------------------------------------------------------------------------------------------
 * Phase 3 lot C: hierarchy, the rows under their groups
 * ------------------------------------------------------------------------------------------- */

function f_t2srep_hierarchy_kind($strkind) {
	//Purpose: What a level groups (summary_facts.hierarchy.levels[].kind), as voice-agent's
	//			T2S_HIERARCHY_KINDS: array(English plural, French plural, French gender)
	$arrkinds=array(
		"trilogy"=>array("trilogies", "trilogies", "f"), "saga"=>array("sagas", "sagas", "f"), "franchise"=>array("franchises", "franchises", "f"),
		"universe"=>array("universes", "univers", "m"), "collection"=>array("collections", "collections", "f"), "region"=>array("regions", "régions", "f"),
		"state"=>array("states", "États", "m"), "nation"=>array("nations", "nations", "f"), "country"=>array("countries", "pays", "m"),
		"province"=>array("provinces", "provinces", "f"), "county"=>array("counties", "comtés", "m"), "department"=>array("departments", "départements", "m"),
		"city"=>array("cities", "villes", "f"), "actor"=>array("actors", "acteurs", "m"), "director"=>array("directors", "réalisateurs", "m"),
		"company"=>array("companies", "sociétés", "f"), "network"=>array("networks", "chaînes", "f"), "genre"=>array("genres", "genres", "m"),
		"person"=>array("people", "personnes", "f"), "group"=>array("groups", "groupes", "m"),
	);
	$strkind=(string)$strkind;
	return isset($arrkinds[$strkind]) ? $arrkinds[$strkind] : $arrkinds["group"];
}

function f_t2srep_hierarchy_leaf_noun($stridcolumn, $arrrecords) {
	//Purpose: The leaves' noun, as text2SqlHierarchyLeafNoun(): films and series mixed are
	//			titles, one type alone takes its own noun
	$arrleaves=array(
		"ID_MOVIE"=>array("films", "films", "m"), "ID_SERIE"=>array("series", "séries", "f"), "ID_CONTENT"=>array("titles", "titres", "m"),
		"ID_PERSON"=>array("people", "personnes", "f"), "ID_LOCATION"=>array("places", "lieux", "m"),
		"ID_T2S_COLLECTION"=>array("collections", "collections", "f"), "ID_COLLECTION"=>array("collections", "collections", "f"),
		"ID_COMPANY"=>array("companies", "sociétés", "f"), "ID_NETWORK"=>array("networks", "chaînes", "f"),
	);
	$strkey=strtoupper((string)$stridcolumn);
	if ($strkey=="ID_CONTENT") {
		$arrtypes=array();
		foreach ($arrrecords as $arrrecord) {
			$arrtypes[is_array($arrrecord) && isset($arrrecord['CONTENT_TYPE']) ? (string)$arrrecord['CONTENT_TYPE'] : ""]=true;
		}
		if (count($arrtypes)==1 && isset($arrtypes['movie'])) {
			$strkey="ID_MOVIE";
		} elseif (count($arrtypes)==1 && isset($arrtypes['serie'])) {
			$strkey="ID_SERIE";
		}
	}
	return isset($arrleaves[$strkey]) ? $arrleaves[$strkey] : array("results", "résultats", "m");
}

function f_t2srep_hierarchy_model($jsontext2sql, $strlang='en') {
	//Purpose: Mirrors text2SqlHierarchyModel(): the API's groups (summary_facts.hierarchy), never
	//			regrouped here. A group: name, unnamed, count, record (the group's own entity when
	//			its level has an id), groups, leaves; a leaf: index, record, title, year. The leaf's
	//			record is the whole row when the leaf is the row's own entity (leaf.image), else its
	//			id and name alone, so a film beside its collection never takes the collection's
	//			poster nor its page.
	//Returns: array("image"=>bool, "groups"=>array(...)), or null without the facts
	if (!is_array($jsontext2sql) || !isset($jsontext2sql['summary_facts']['hierarchy']) || !is_array($jsontext2sql['summary_facts']['hierarchy'])) {
		return null;
	}
	$arrfacts=$jsontext2sql['summary_facts']['hierarchy'];
	if (!isset($arrfacts['groups']) || !is_array($arrfacts['groups']) || !isset($arrfacts['levels']) || !is_array($arrfacts['levels']) || !count($arrfacts['levels'])
		|| !isset($arrfacts['leaf']) || !is_array($arrfacts['leaf'])) {
		return null;
	}
	$arrrecords=f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array());
	$stridcolumn=isset($arrfacts['leaf']['id_column']) ? (string)$arrfacts['leaf']['id_column'] : "";
	$strlabelcolumn=isset($arrfacts['leaf']['label_column']) ? (string)$arrfacts['leaf']['label_column'] : "";
	$intimage=!empty($arrfacts['leaf']['image']);
	$arrlevels=array_values($arrfacts['levels']);
	$fnleaf=function($lngindex) use ($arrrecords, $stridcolumn, $strlabelcolumn, $intimage) {
		if (!is_int($lngindex) || !isset($arrrecords[$lngindex]) || !is_array($arrrecords[$lngindex])) {
			return null;
		}
		$arrfull=$arrrecords[$lngindex];
		if ($intimage) {
			$arrrecord=$arrfull;
		} else {
			$arrrecord=array();
			foreach (array($stridcolumn, $strlabelcolumn, "CONTENT_TYPE") as $strkey) {
				if ($strkey!="" && isset($arrfull[$strkey])) {
					$arrrecord[$strkey]=$arrfull[$strkey];
				}
			}
		}
		$lngyear=null;
		if ($intimage) {
			$lngyear=f_t2srep_year(isset($arrfull['DAT_RELEASE']) ? $arrfull['DAT_RELEASE'] : (isset($arrfull['DAT_FIRST_AIR']) ? $arrfull['DAT_FIRST_AIR'] : null));
		}
		return array("index"=>$lngindex, "record"=>$arrrecord,
			"title"=>($strlabelcolumn!="" && isset($arrfull[$strlabelcolumn])) ? (string)$arrfull[$strlabelcolumn] : "",
			"year"=>$lngyear===null ? "" : (string)$lngyear);
	};
	$fngroup=function($arrgroup, $lngdepth) use (&$fngroup, $fnleaf, $arrlevels, $strlang) {
		$arrlevel=isset($arrlevels[$lngdepth]) && is_array($arrlevels[$lngdepth]) ? $arrlevels[$lngdepth] : array();
		$intunnamed=!isset($arrgroup['label']) || $arrgroup['label']===null || trim((string)$arrgroup['label'])==="";
		$stridcol=isset($arrlevel['id_column']) ? (string)$arrlevel['id_column'] : "";
		$arrrecord=null;
		if (!$intunnamed && $stridcol!="" && isset($arrgroup['id']) && $arrgroup['id']!==null && (string)$arrgroup['id']!=="") {
			$arrrecord=array($stridcol=>$arrgroup['id'], (isset($arrlevel['column']) ? (string)$arrlevel['column'] : "")=>$arrgroup['label']);
		}
		$arrsubs=array();
		if (isset($arrgroup['groups']) && is_array($arrgroup['groups'])) {
			foreach ($arrgroup['groups'] as $arrsub) {
				$arrsubs[]=$fngroup($arrsub, $lngdepth+1);
			}
		}
		$arrleaves=array();
		if (isset($arrgroup['rows']) && is_array($arrgroup['rows'])) {
			foreach ($arrgroup['rows'] as $lngindex) {
				$arrleaf=$fnleaf(is_numeric($lngindex) ? (int)$lngindex : null);
				if ($arrleaf!==null) {
					$arrleaves[]=$arrleaf;
				}
			}
		}
		return array("name"=>$intunnamed ? ($strlang=="fr" ? "Autres" : "Other") : (string)$arrgroup['label'], "unnamed"=>$intunnamed,
			"count"=>isset($arrgroup['count']) ? (int)$arrgroup['count'] : 0, "record"=>$arrrecord, "groups"=>$arrsubs, "leaves"=>$arrleaves);
	};
	$arrgroups=array();
	foreach ($arrfacts['groups'] as $arrgroup) {
		$arrgroups[]=$fngroup(is_array($arrgroup) ? $arrgroup : array(), 0);
	}
	return array("image"=>$intimage, "groups"=>$arrgroups);
}

function f_t2srep_render_hierarchy($jsontext2sql, $strlang='en', $fnposter=null) {
	//Purpose: Mirrors renderHierarchy(): one section per outer group, an h3 with its name (linked
	//			to its page when the level has an id the site has a page for) and its count, a
	//			third level as h4 sections, the leaves as compact rows in a grid that falls to one
	//			column on a phone: a small poster or face when the leaf is the row's own entity, the
	//			title linked as a card is, the year of a film or a series. Real headings and lists,
	//			no collapsing, no script.
	$arrmodel=f_t2srep_hierarchy_model($jsontext2sql, $strlang);
	$arrcolumns=f_t2srep_columns($jsontext2sql);
	if ($arrmodel===null) {
		return f_t2srep_render_table(f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array()), $arrcolumns, $strlang);
	}
	$fnsection=function($arrgroup, $lngdepth) use (&$fnsection, $arrmodel, $arrcolumns, $fnposter) {
		$strtag=$lngdepth==0 ? "h3" : "h4";
		$strname=f_t2srep_h($arrgroup['name']);
		$strhref="";
		if (is_array($arrgroup['record'])) {
			foreach (array_keys($arrgroup['record']) as $strkey) {
				if (strpos(strtoupper((string)$strkey), "ID_")===0) {
					$strhref=f_t2srep_entity_href_for($arrgroup['record'], $strkey);
				}
			}
		}
		$strhtml="<section class=\"t2sHierGroup".($lngdepth>0 ? " t2sHierInner" : "")."\"><".$strtag." class=\"t2sHierHead\">"
			."<span class=\"t2sHierName".($arrgroup['unnamed'] ? " isUnnamed" : "")."\">".($strhref!="" ? "<a href=\"".f_t2srep_h($strhref)."\">".$strname."</a>" : $strname)."</span> "
			."<span class=\"t2sHierCount\">".(int)$arrgroup['count']."</span></".$strtag.">";
		if (count($arrgroup['groups'])) {
			foreach ($arrgroup['groups'] as $arrsub) {
				$strhtml.=$fnsection($arrsub, $lngdepth+1);
			}
			return $strhtml."</section>";
		}
		$strhtml.="<ul class=\"t2sHierLeaves\">";
		foreach ($arrgroup['leaves'] as $arrleaf) {
			$arrmedia=f_t2srep_entity_media($arrleaf['record'], $arrcolumns, $fnposter);
			$strtitle=$arrleaf['title']!="" ? $arrleaf['title'] : $arrmedia['title'];
			$strurl=$arrmedia['url'];
			if ($strurl=="" && isset($arrleaf['record']['WIKIPEDIA_IMAGE_PATH']) && preg_match('#^https://#', (string)$arrleaf['record']['WIKIPEDIA_IMAGE_PATH'])) {
				//A place shows its picture, as its card does
				$strurl=(string)$arrleaf['record']['WIKIPEDIA_IMAGE_PATH'];
			}
			$strinner="";
			if ($arrmodel['image']) {
				$strinner.=$strurl!="" ? "<img class=\"t2sHierThumb\" src=\"".f_t2srep_h($strurl)."\" alt=\"\" loading=\"lazy\">" : "<span class=\"t2sHierThumb isEmpty\" aria-hidden=\"true\"></span>";
			}
			$strinner.="<span class=\"t2sHierText\"><span class=\"t2sHierTitle\">".f_t2srep_h($strtitle)."</span>"
				.($arrleaf['year']!="" ? "<span class=\"t2sHierSub\">".f_t2srep_h($arrleaf['year'])."</span>" : "")."</span>";
			$strhref=$arrmedia['href'];
			$strhtml.="<li class=\"t2sHierLeaf\">".($strhref!="" ? "<a class=\"t2sHierLink\" href=\"".f_t2srep_h($strhref)."\">".$strinner."</a>" : $strinner)."</li>";
		}
		return $strhtml."</ul></section>";
	};
	$strhtml="<div class=\"t2sHier\">";
	foreach ($arrmodel['groups'] as $arrgroup) {
		$strhtml.=$fnsection($arrgroup, 0);
	}
	return $strhtml."</div>";
}

function f_t2srep_caption_hierarchy($jsontext2sql, $strlang='en', $inthasmore=false) {
	//Purpose: Mirrors text2SqlHierarchySummary(): "Six films in two trilogies, three in each." /
	//			"37 films en douze collections ; la plus grande, The Avengers Collection, en compte
	//			six."; three levels "Seven places in five counties and three nations; ..."; leaves
	//			shared "(five in more than one)" / "(cinq dans plusieurs)"; "the first N" while
	//			another page may follow
	if (!is_array($jsontext2sql) || !isset($jsontext2sql['summary_facts']['hierarchy']) || !is_array($jsontext2sql['summary_facts']['hierarchy'])) {
		return "";
	}
	$arrfacts=$jsontext2sql['summary_facts']['hierarchy'];
	if (!isset($arrfacts['levels']) || !is_array($arrfacts['levels']) || !count($arrfacts['levels']) || !isset($arrfacts['groups']) || !is_array($arrfacts['groups'])
		|| !isset($arrfacts['leaf']) || !is_array($arrfacts['leaf'])) {
		return "";
	}
	$intfr=($strlang=="fr");
	$arrlevels=array_values($arrfacts['levels']);
	$arrnoun=f_t2srep_hierarchy_leaf_noun(isset($arrfacts['leaf']['id_column']) ? $arrfacts['leaf']['id_column'] : "",
		f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array()));
	$strgender=$arrnoun[2];
	$strplural=$intfr ? $arrnoun[1] : $arrnoun[0];
	$strn=f_t2srep_count_word(isset($arrfacts['leaf']['count']) ? (int)$arrfacts['leaf']['count'] : 0, $strlang, $strgender);
	if ($inthasmore) {
		$strlead=$intfr ? "les ".$strn." ".($strgender=="f" ? "premières" : "premiers")." ".$strplural : "the first ".$strn." ".$strplural;
	} else {
		$strlead=$strn." ".$strplural;
	}
	$arrouter=f_t2srep_hierarchy_kind(isset($arrlevels[0]['kind']) ? $arrlevels[0]['kind'] : "");
	$strouter=f_t2srep_count_word(isset($arrlevels[0]['count']) ? (int)$arrlevels[0]['count'] : 0, $strlang, $arrouter[2])." ".($intfr ? $arrouter[1] : $arrouter[0]);
	if (count($arrlevels)>1) {
		$arrinner=f_t2srep_hierarchy_kind(isset($arrlevels[1]['kind']) ? $arrlevels[1]['kind'] : "");
		$strinner=f_t2srep_count_word(isset($arrlevels[1]['count']) ? (int)$arrlevels[1]['count'] : 0, $strlang, $arrinner[2])." ".($intfr ? $arrinner[1] : $arrinner[0]);
		$strlead.=$intfr ? " dans ".$strinner." et ".$strouter : " in ".$strinner." and ".$strouter;
	} else {
		$strlead.=$intfr ? " en ".$strouter : " in ".$strouter;
	}
	$lngshared=isset($arrfacts['shared']) ? (int)$arrfacts['shared'] : 0;
	if ($lngshared>0) {
		$strlead.=$intfr ? " (".f_t2srep_count_word($lngshared, $strlang, $strgender)." dans plusieurs)" : " (".f_t2srep_count_word($lngshared, $strlang, $strgender)." in more than one)";
	}
	$lnglargest=isset($arrfacts['largest']) && is_numeric($arrfacts['largest']) ? (int)$arrfacts['largest'] : -1;
	$arrtop=($lnglargest>=0 && isset($arrfacts['groups'][$lnglargest]) && is_array($arrfacts['groups'][$lnglargest])) ? $arrfacts['groups'][$lnglargest] : null;
	$strtail="";
	if ($arrtop!==null) {
		$strk=f_t2srep_count_word(isset($arrtop['count']) ? (int)$arrtop['count'] : 0, $strlang, $strgender);
		if (!empty($arrfacts['even'])) {
			$strtail=$intfr ? ", ".$strk." dans ".($arrouter[2]=="f" ? "chacune" : "chacun") : ", ".$strk." in each";
		} else {
			$strname=(!isset($arrtop['label']) || $arrtop['label']===null || trim((string)$arrtop['label'])==="") ? ($intfr ? "Autres" : "Other") : (string)$arrtop['label'];
			$strtail=$intfr ? " ; ".($arrouter[2]=="f" ? "la plus grande" : "le plus grand").", ".$strname.", en compte ".$strk : "; the largest, ".$strname.", has ".$strk;
		}
	}
	return f_t2srep_ucfirst($strlead).$strtail.".";
}

/* ---------------------------------------------------------------------------------------------
 * Phase 3 lot E: chain, the lineage of adaptations and remakes from the API's walk
 * ------------------------------------------------------------------------------------------- */

function f_t2srep_chain_facts($jsontext2sql) {
	//Purpose: summary_facts.chain when it holds two links at least, else null. The chain comes
	//			from the API's lineage walk; the rows are never read to build it.
	if (!is_array($jsontext2sql) || !isset($jsontext2sql['summary_facts']['chain']) || !is_array($jsontext2sql['summary_facts']['chain'])) {
		return null;
	}
	$arrchain=$jsontext2sql['summary_facts']['chain'];
	if (!isset($arrchain['nodes']) || !is_array($arrchain['nodes']) || !isset($arrchain['edges']) || !is_array($arrchain['edges']) || count($arrchain['edges'])<2) {
		return null;
	}
	return $arrchain;
}

function f_t2srep_chain_de($strtitle) {
	//Purpose: French "de" before a title, elided or contracted as voice-agent does: du Garde du
	//			corps, des Sept Samouraïs, d'Une étoile est née
	$strtitle=(string)$strtitle;
	if (strpos($strtitle, "Le ")===0) {
		return "du ".substr($strtitle, 3);
	}
	if (strpos($strtitle, "Les ")===0) {
		return "des ".substr($strtitle, 4);
	}
	if (preg_match('/^[aeiouyàâäéèêëîïôöùûüœæ]/iu', $strtitle)) {
		return "d'".$strtitle;
	}
	return "de ".$strtitle;
}

function f_t2srep_chain_a($strtitle) {
	//Purpose: French "à" before a title, contracted: au, aux
	$strtitle=(string)$strtitle;
	if (strpos($strtitle, "Le ")===0) {
		return "au ".substr($strtitle, 3);
	}
	if (strpos($strtitle, "Les ")===0) {
		return "aux ".substr($strtitle, 4);
	}
	return "à ".$strtitle;
}

function f_t2srep_chain_fold($strtext) {
	//Purpose: Lower case for comparing titles, with mbstring when the PHP carries it
	return function_exists('mb_strtolower') ? mb_strtolower((string)$strtext, 'UTF-8') : strtolower((string)$strtext);
}

function f_t2srep_chain_labels($arrnodes) {
	//Purpose: The label of each node (its title, with its year when another work of the chain
	//			shares the title) and its dated label (the title and the year whenever known)
	//Returns: array(labels, dated labels), by node index
	$arrseen=array();
	foreach ($arrnodes as $arrnode) {
		$strkey=f_t2srep_chain_fold(isset($arrnode['title']) ? $arrnode['title'] : "");
		$arrseen[$strkey]=isset($arrseen[$strkey]) ? $arrseen[$strkey]+1 : 1;
	}
	$arrlabels=array();
	$arrdated=array();
	foreach ($arrnodes as $lngi=>$arrnode) {
		$strtitle=isset($arrnode['title']) ? (string)$arrnode['title'] : "";
		$intyear=isset($arrnode['year']) && $arrnode['year']!==null && (string)$arrnode['year']!=="";
		$strdated=$intyear ? $strtitle." (".$arrnode['year'].")" : $strtitle;
		$arrdated[$lngi]=$strdated;
		$arrlabels[$lngi]=($arrseen[f_t2srep_chain_fold($strtitle)]>1 && $intyear) ? $strdated : $strtitle;
	}
	return array($arrlabels, $arrdated);
}

function f_t2srep_chain_badge($arrnode, $strlang='en') {
	//Purpose: The small badge of a work: Film, Series, or the kind of a source work
	$intfr=($strlang=="fr");
	$strtype=isset($arrnode['type']) ? (string)$arrnode['type'] : "";
	if ($strtype=="movie") {
		return "Film";
	}
	if ($strtype=="serie") {
		return $intfr ? "Série" : "Series";
	}
	$arrkinds=array("literary"=>array("Book", "Livre"), "comic"=>array("Comic", "BD"), "stage"=>array("Play", "Pièce"),
		"game"=>array("Game", "Jeu"), "folklore"=>array("Tale", "Conte"), "screen"=>array("Film", "Film"));
	$strkind=isset($arrnode['source_type']) ? (string)$arrnode['source_type'] : "";
	return isset($arrkinds[$strkind]) ? $arrkinds[$strkind][$intfr ? 1 : 0] : ($intfr ? "Œuvre" : "Work");
}

function f_t2srep_chain_model($jsontext2sql, $strlang='en') {
	//Purpose: Mirrors text2SqlChainModel(): the generations of summary_facts.chain.layers, each a
	//			list of cards {index, type, id, title, year, label, anchor, badge, record, links}.
	//			`record` is what a card of that entity carries (its id, its title, its pictures),
	//			`links` one line per link into the work, in the API's order ("Remake of A Star Is
	//			Born (1937)" / "Remake d'Une étoile est née (1937)"), a link closing a cycle marked
	//Returns: array("generations"=>array(...)), or null without a chain of two links
	$arrchain=f_t2srep_chain_facts($jsontext2sql);
	if ($arrchain===null) {
		return null;
	}
	$intfr=($strlang=="fr");
	$arrnodes=array_values($arrchain['nodes']);
	$arrnamed=f_t2srep_chain_labels($arrnodes);
	$arrlabels=$arrnamed[0];
	$arrlinks=array();
	foreach ($arrchain['edges'] as $arredge) {
		if (!is_array($arredge) || !isset($arredge['from']) || !isset($arredge['to']) || !isset($arrnodes[(int)$arredge['from']])) {
			continue;
		}
		$strfrom=$arrlabels[(int)$arredge['from']];
		$strkind=isset($arredge['kind']) ? (string)$arredge['kind'] : "";
		if ($strkind=="remake") {
			$strtext=$intfr ? "Remake ".f_t2srep_chain_de($strfrom) : "Remake of ".$strfrom;
		} elseif ($strkind=="adaptation") {
			$strtext=$intfr ? "Adaptation ".f_t2srep_chain_de($strfrom) : "Adapted from ".$strfrom;
		} else {
			$strtext=$intfr ? "D'après ".$strfrom : "Based on ".$strfrom;
		}
		$intcycle=!empty($arredge['cycle']);
		if ($intcycle) {
			$strtext.=$intfr ? " (une boucle dans les données)" : " (a loop in the data)";
		}
		$arrlinks[(int)$arredge['to']][]=array("text"=>$strtext, "cycle"=>$intcycle);
	}
	$arrlayers=(isset($arrchain['layers']) && is_array($arrchain['layers'])) ? $arrchain['layers'] : array(array_keys($arrnodes));
	$arrgenerations=array();
	foreach ($arrlayers as $arrlayer) {
		$arrcards=array();
		foreach ((array)$arrlayer as $lngindex) {
			$lngindex=(int)$lngindex;
			if (!isset($arrnodes[$lngindex]) || !is_array($arrnodes[$lngindex])) {
				continue;
			}
			$arrnode=$arrnodes[$lngindex];
			$strtype=isset($arrnode['type']) ? (string)$arrnode['type'] : "";
			$strtitle=isset($arrnode['title']) ? (string)$arrnode['title'] : "";
			$strposter=isset($arrnode['poster_path']) ? $arrnode['poster_path'] : null;
			if ($strtype=="movie") {
				$arrrecord=array("ID_MOVIE"=>$arrnode['id'], "MOVIE_TITLE"=>$strtitle, "POSTER_PATH"=>$strposter);
			} elseif ($strtype=="serie") {
				$arrrecord=array("ID_SERIE"=>$arrnode['id'], "SERIE_TITLE"=>$strtitle, "POSTER_PATH"=>$strposter);
			} else {
				$arrrecord=array("ID_SOURCE_WORK"=>$arrnode['id'], "SOURCE_WORK_NAME"=>$strtitle, "POSTER_PATH"=>$strposter,
					"WIKIPEDIA_IMAGE_PATH"=>isset($arrnode['wikipedia_image_path']) ? $arrnode['wikipedia_image_path'] : null);
			}
			$arrrecord=array_filter($arrrecord, function($value) { return $value!==null && $value!==""; });
			$arrcards[]=array("index"=>$lngindex, "type"=>$strtype, "id"=>isset($arrnode['id']) ? $arrnode['id'] : null, "title"=>$strtitle,
				"year"=>(isset($arrnode['year']) && $arrnode['year']!==null) ? (string)$arrnode['year'] : "", "label"=>$arrlabels[$lngindex],
				"anchor"=>!empty($arrnode['anchor']), "badge"=>f_t2srep_chain_badge($arrnode, $strlang), "record"=>$arrrecord,
				"links"=>isset($arrlinks[$lngindex]) ? $arrlinks[$lngindex] : array());
		}
		if (count($arrcards)) {
			$arrgenerations[]=$arrcards;
		}
	}
	return array("generations"=>$arrgenerations);
}

function f_t2srep_render_chain($jsontext2sql, $strlang='en', $fnposter=null) {
	//Purpose: Mirrors renderChain(): an ordered list of generations, left to right on a desktop
	//			and top to bottom on a phone, an arrow between two; the works of a generation
	//			stacked (a fork), each a card (picture, title, year, badge) opening its page as a
	//			card does, with one line per link into it; the starting point outlined and badged.
	//			No graph layout, no script.
	$arrmodel=f_t2srep_chain_model($jsontext2sql, $strlang);
	if ($arrmodel===null) {
		return f_t2srep_render_table(f_t2srep_records(isset($jsontext2sql['result']) ? $jsontext2sql['result'] : array()), f_t2srep_columns($jsontext2sql), $strlang);
	}
	$intfr=($strlang=="fr");
	$strhtml="<ol class=\"t2sChain\">";
	foreach ($arrmodel['generations'] as $lnggen=>$arrcards) {
		$strhtml.="<li class=\"t2sChainGen\">".($lnggen>0 ? "<span class=\"t2sChainArrow\" aria-hidden=\"true\"></span>" : "")."<ul class=\"t2sChainWorks\">";
		foreach ($arrcards as $arrcard) {
			$arrmedia=f_t2srep_entity_media($arrcard['record'], array(), $fnposter);
			$strurl=$arrmedia['url'];
			if ($strurl=="" && isset($arrcard['record']['WIKIPEDIA_IMAGE_PATH']) && preg_match('#^https://#', (string)$arrcard['record']['WIKIPEDIA_IMAGE_PATH'])) {
				$strurl=(string)$arrcard['record']['WIKIPEDIA_IMAGE_PATH'];
			}
			$strhref=$arrmedia['href'];
			$strname=$arrcard['year']!="" ? $arrcard['title'].", ".$arrcard['year'] : $arrcard['title'];
			$strinner=($strurl!="" ? "<img class=\"t2sChainThumb\" src=\"".f_t2srep_h($strurl)."\" alt=\"\" loading=\"lazy\">" : "<span class=\"t2sChainThumb isEmpty\" aria-hidden=\"true\"></span>")
				."<span class=\"t2sChainText\"><span class=\"t2sChainTitle\">".f_t2srep_h($arrcard['title'])."</span>"
				."<span class=\"t2sChainMeta\">".($arrcard['year']!="" ? "<span class=\"t2sChainYear\">".f_t2srep_h($arrcard['year'])."</span> " : "")
				."<span class=\"t2sChainBadge\">".f_t2srep_h($arrcard['badge'])."</span></span>"
				.($arrcard['anchor'] ? "<span class=\"t2sChainStart\">".($intfr ? "Point de départ" : "Starting point")."</span>" : "")."</span>";
			$strhtml.="<li class=\"t2sChainWork".($arrcard['anchor'] ? " isAnchor" : "")."\">"
				.($strhref!="" ? "<a class=\"t2sChainCard\" href=\"".f_t2srep_h($strhref)."\" aria-label=\"".f_t2srep_h($strname)."\">".$strinner."</a>"
					: "<span class=\"t2sChainCard\" role=\"group\" aria-label=\"".f_t2srep_h($strname)."\">".$strinner."</span>");
			if (count($arrcard['links'])) {
				$strhtml.="<ul class=\"t2sChainLinks\">";
				foreach ($arrcard['links'] as $arrlink) {
					$strhtml.="<li class=\"t2sChainLink".($arrlink['cycle'] ? " isCycle" : "")."\">".f_t2srep_h($arrlink['text'])."</li>";
				}
				$strhtml.="</ul>";
			}
			$strhtml.="</li>";
		}
		$strhtml.="</ul></li>";
	}
	return $strhtml."</ol>";
}

function f_t2srep_caption_chain($jsontext2sql, $strlang='en') {
	//Purpose: Mirrors text2SqlChainSummary(): the works, their span, and the starting point's own
	//			link. "Three works from Red Harvest (1927) to Last Man Standing (1996); Last Man
	//			Standing remakes Yojimbo." / "Trois œuvres, de La Moisson rouge (1927) à Dernier
	//			recours (1996) ; Dernier recours est un remake du Garde du corps."; several sources
	//			"draws on three of them" / "en reprend trois"; walking down "directly inspired three
	//			of them" / "en a inspiré directement trois"; a cut walk says where it stopped. The
	//			chain is not paged: no "first N".
	$arrchain=f_t2srep_chain_facts($jsontext2sql);
	if ($arrchain===null) {
		return "";
	}
	$intfr=($strlang=="fr");
	$arrnodes=array_values($arrchain['nodes']);
	$arredges=array_values($arrchain['edges']);
	$arrtypes=array();
	foreach ($arrnodes as $arrnode) {
		$arrtypes[isset($arrnode['type']) ? (string)$arrnode['type'] : ""]=true;
	}
	if (count($arrtypes)==1 && isset($arrtypes['movie'])) {
		$strnoun="films";
		$strgender="m";
	} elseif (count($arrtypes)==1 && isset($arrtypes['serie'])) {
		$strnoun=$intfr ? "séries" : "series";
		$strgender="f";
	} else {
		$strnoun=$intfr ? "œuvres" : "works";
		$strgender="f";
	}
	$arrnamed=f_t2srep_chain_labels($arrnodes);
	$arrlabels=$arrnamed[0];
	$arrdated=$arrnamed[1];
	$strcount=f_t2srep_ucfirst(f_t2srep_count_word(isset($arrchain['count']) ? (int)$arrchain['count'] : count($arrnodes), $strlang, $strgender));
	$lngearliest=(isset($arrchain['earliest']) && is_numeric($arrchain['earliest'])) ? (int)$arrchain['earliest'] : null;
	$lnglatest=(isset($arrchain['latest']) && is_numeric($arrchain['latest'])) ? (int)$arrchain['latest'] : null;
	$lnganchor=(isset($arrchain['anchor']) && is_array($arrchain['anchor']) && count($arrchain['anchor'])) ? (int)$arrchain['anchor'][0] : null;
	if ($lngearliest!==null && $lnglatest!==null && $lngearliest!=$lnglatest && isset($arrdated[$lngearliest]) && isset($arrdated[$lnglatest])) {
		$strs1=$intfr ? $strcount." ".$strnoun.", ".f_t2srep_chain_de($arrdated[$lngearliest])." ".f_t2srep_chain_a($arrdated[$lnglatest])
			: $strcount." ".$strnoun." from ".$arrdated[$lngearliest]." to ".$arrdated[$lnglatest];
	} else {
		$stranchor=($lnganchor!==null && isset($arrlabels[$lnganchor])) ? $arrlabels[$lnganchor] : "";
		$strs1=$intfr ? $strcount." ".$strnoun." dans la filiation ".f_t2srep_chain_de($stranchor) : $strcount." ".$strnoun." in the lineage of ".$stranchor;
	}
	if (!empty($arrchain['truncated'])) {
		$strby=isset($arrchain['truncated_by']) ? (string)$arrchain['truncated_by'] : "";
		if ($strby=="nodes") {
			$lngmax=isset($arrchain['max_nodes']) ? (int)$arrchain['max_nodes'] : 0;
			$strs1.=$intfr ? " (la filiation continue au-delà de ".f_t2srep_count_word($lngmax, $strlang, "f")." œuvres)"
				: " (the lineage goes on beyond ".f_t2srep_count_word($lngmax, $strlang)." works)";
		} elseif ($strby=="depth") {
			$lngmax=isset($arrchain['max_depth']) ? (int)$arrchain['max_depth'] : 0;
			$strs1.=$intfr ? " (la filiation continue au-delà de ".f_t2srep_count_word($lngmax, $strlang, "f")." générations)"
				: " (the lineage goes on beyond ".f_t2srep_count_word($lngmax, $strlang)." generations)";
		} else {
			$strs1.=$intfr ? " (le parcours s'est arrêté avant la fin)" : " (the walk stopped early)";
		}
	}
	$strs2="";
	if ($lnganchor!==null && isset($arrnodes[$lnganchor])) {
		$arrverbs=$intfr ? array("remake"=>"est un remake", "adaptation"=>"est une adaptation", "based_on"=>"s'inspire")
			: array("remake"=>"remakes", "adaptation"=>"is adapted from", "based_on"=>"is based on");
		$fnrel=function($lngchild, $lngparent, $strkind) use ($arrlabels, $arrverbs, $intfr) {
			$strverb=isset($arrverbs[$strkind]) ? $arrverbs[$strkind] : $arrverbs['based_on'];
			return $arrlabels[$lngchild]." ".$strverb." ".($intfr ? f_t2srep_chain_de($arrlabels[$lngparent]) : $arrlabels[$lngparent]);
		};
		$arrparents=array();
		$arrchildren=array();
		foreach ($arredges as $arredge) {
			if (!is_array($arredge) || !empty($arredge['cycle'])) {
				continue;
			}
			if ((int)$arredge['to']==$lnganchor) {
				$arrparents[]=$arredge;
			}
			if ((int)$arredge['from']==$lnganchor) {
				$arrchildren[]=$arredge;
			}
		}
		$strdirection=isset($arrchain['direction']) ? (string)$arrchain['direction'] : "";
		if ($strdirection!="down" && count($arrparents)) {
			if (count($arrparents)==1) {
				$strs2=$fnrel($lnganchor, (int)$arrparents[0]['from'], isset($arrparents[0]['kind']) ? (string)$arrparents[0]['kind'] : "");
			} else {
				$strs2=$intfr ? $arrlabels[$lnganchor]." en reprend ".f_t2srep_count_word(count($arrparents), $strlang, $strgender)
					: $arrlabels[$lnganchor]." draws on ".f_t2srep_count_word(count($arrparents), $strlang)." of them";
			}
		} elseif (count($arrchildren)) {
			if (count($arrchildren)==1) {
				$strs2=$fnrel((int)$arrchildren[0]['to'], $lnganchor, isset($arrchildren[0]['kind']) ? (string)$arrchildren[0]['kind'] : "");
			} else {
				$strs2=$intfr ? $arrlabels[$lnganchor]." en a inspiré directement ".f_t2srep_count_word(count($arrchildren), $strlang, $strgender)
					: $arrlabels[$lnganchor]." directly inspired ".f_t2srep_count_word(count($arrchildren), $strlang)." of them";
			}
		}
	}
	if ($strs2!="") {
		return $intfr ? $strs1." ; ".$strs2."." : $strs1."; ".$strs2.".";
	}
	return $strs1.".";
}
