<?php
/*
 * t2s-representation-test.php - test for lib/t2s-representation.inc.php
 *
 * Run it from the repository root, no database and no web server needed:
 *
 *     php test/t2s-representation-test.php
 *
 * Exit code 0 when every case passes, 1 otherwise.
 *
 * The module mirrors voice-agent's result representations (app/static/app.js), so the cases
 * below pin the same values voice-agent was accepted on: the dispatch on the API hint, the unit
 * formats ("1,178 min · 19 h 38", "$1,200,000,000", "×6.4", never a dollar without the usd
 * unit), and the shape of each rendering.
 *
 * Phase 1 runs on real answers, trimmed, in
 * test/t2s-representation-fixtures.php: value-cards and its value per card ("2 h 18", two
 * values for a comparison of budget and revenue), the numbered poster ranking, the chips of
 * representation_options and render=, and the caption built from summary_facts in English and
 * French. The script of the chips goes through node --check (skipped, loudly, without node).
 *
 * Phase 2 lot A adds timeline, sequence-strip and trend-columns on answers
 * recomputed with the API module of that lot (fixtures lota-*): years and decades, the
 * rendering of each, the chips and render=, and their captions in English and French.
 * Then the pages that follow: the caption says "the first N" while a page may follow, the next
 * page of a timeline is a flat list of marks with their years, the loader and caption scripts go
 * through node --check, and the caption the page recounts in JavaScript is checked against the PHP
 * one on the same numbers.
 *
 * Phase 2 lot B adds compare-cards, fact-card and verdict on live answers reclassified with the
 * API module of that lot (fixtures lotb-*): the winner or the tie of each compared quantity, the
 * fact and its homonyms, the Yes or No and its evidence (an empty result and a count of zero
 * included), the chips and their captions in English and French. The last group pins the links of
 * the entity titles in the table.
 *
 * Phase 2 lot C adds histogram, share-bar and flag-list on live answers reclassified with the
 * API module of that lot (fixtures lotc-*): the bins in their order and the empty bin kept, the
 * parts and their labels (GENDER named, one share and its rest), the countries with their flag
 * or their code badge, the chips, and the captions in English and French, word for word those
 * of voice-agent. The flag detection script goes through node --check.
 *
 * Phase 3 lot E adds chain on the answers to the lineage questions (fixtures p3e-*, the walk of the
 * API run on the live graph): the generations, the cards and their links, the badges, the starting
 * point, the cut walk and the loop, the captions in English and French, word for word those of
 * voice-agent.
 *
 * Phase 3 lot C adds hierarchy on the answers to the grouping questions (fixtures p3c-*): the
 * groups and their leaves as the API reads them, the leaf alone after its parent's entity, the
 * third level nested, the captions in English and French, word for word those of voice-agent.
 *
 * Phase 3 lot B adds map on the answers to the map questions of countries (fixtures p3b-*) and
 * the outline html/data/world-110m.json: the shades from the API's steps, the legend, the dots,
 * the countries with no place on the map, the captions in English and French, word for word
 * those of voice-agent. The hover script goes through node --check.
 *
 * Phase 3 lot A adds scatter on the 1.1.20 answers of the scatter evaluations reclassified with
 * the API module of that lot (fixtures p3a-*): the axes, their ticks and titles (money, minutes,
 * years, a log axis of votes), the points and the highest one, the rendering, the chips, and the
 * captions in English and French, word for word those of voice-agent. The tap script goes through
 * node --check.
 */

require_once(__DIR__."/../lib/t2s-representation.inc.php");

$lngchecks=0;
$lngfailures=0;

function f_check($strlabel,$varactual,$varexpected) {
	global $lngchecks,$lngfailures;
	$lngchecks++;
	if ($varactual!==$varexpected) {
		$lngfailures++;
		echo "FAIL ".$strlabel."\n     expected: ".var_export($varexpected,true)."\n     actual:   ".var_export($varactual,true)."\n";
	}
}

function f_contains($strlabel,$strhaystack,$strneedle) {
	f_check($strlabel,strpos($strhaystack,$strneedle)!==false,true);
}

function f_group($strtitle) {
	echo "\n".$strtitle."\n";
}

function f_nodecheck($strlabel,$strcode) {
	//node --check on the script PHP produced, as in vision-image-test.php; skipped, loudly, when
	//node is not on the PATH (a skip is not a pass)
	global $lngchecks,$lngfailures;
	$arrout=array();
	$lngstatus=1;
	exec("node --version 2>&1",$arrout,$lngstatus);
	if ($lngstatus!==0) {
		echo "  SKIP  node --check, ".$strlabel." (node is not on the PATH; a skip is not a pass)\n";
		return;
	}
	$strtempfile=tempnam(sys_get_temp_dir(),"t2srep").".js";
	file_put_contents($strtempfile,$strcode);
	$arrout=array();
	exec("node --check ".escapeshellarg($strtempfile)." 2>&1",$arrout,$lngstatus);
	@unlink($strtempfile);
	$lngchecks++;
	if ($lngstatus!==0) {
		$lngfailures++;
		echo "FAIL node --check rejected ".$strlabel."\n     ".implode("\n     ",$arrout)."\n";
	}
}

f_group("Unit formats (formatMetric)");
f_check("minutes",f_t2srep_format_metric(1178,"minutes"),"1,178 min · 19 h 38");
f_check("minutes under an hour",f_t2srep_format_metric(45,"minutes"),"45 min · 45 min");
f_check("usd",f_t2srep_format_metric(1200000000,"usd"),"$1,200,000,000");
f_check("ratio",f_t2srep_format_metric(6.4,"ratio"),"×6.4");
f_check("ratio whole",f_t2srep_format_metric(3,"ratio"),"×3");
f_check("count",f_t2srep_format_metric("342753","count"),"342,753");
f_check("years en",f_t2srep_format_metric(12,"years","en"),"12 years");
f_check("years fr singular",f_t2srep_format_metric(1,"years","fr"),"1 an");
f_check("no unit is never money",f_t2srep_format_metric(1178,null),"1,178");
f_check("no unit decimal",f_t2srep_format_metric(7.256,null),"7.26");
f_check("text kept",f_t2srep_format_metric("Drama","count"),"Drama");
f_check("blank gives nothing",f_t2srep_format_metric("  ","usd"),"");

f_group("Dispatch (chooseText2SqlRepresentation)");
f_check("number-card hint",f_t2srep_choose(array("representation"=>"number-card","result"=>array())),"number-card");
f_check("ranked-bars hint",f_t2srep_choose(array("representation"=>"ranked-bars")),"ranked-bars");
f_check("table hint",f_t2srep_choose(array("representation"=>"table")),"table");
f_check("card-grid stays with the card renderer",f_t2srep_choose(array("representation"=>"card-grid")),"");
f_check("entity-page stays with the card renderer",f_t2srep_choose(array("representation"=>"entity-page")),"");
$arrscalar=array("result"=>array(array("index"=>0,"data"=>array("TOTAL_RUNTIME"=>1178))));
f_check("hintless one-row number",f_t2srep_choose($arrscalar),"number-card");
f_check("hintless entity row",f_t2srep_choose(array("result"=>array(array("index"=>0,"data"=>array("ID_MOVIE"=>1,"RUNTIME"=>150))))),"");
f_check("hintless many rows",f_t2srep_choose(array("result"=>array(array("data"=>array("N"=>1)),array("data"=>array("N"=>2))))),"");

f_group("Renderings");
$strhtml=f_t2srep_render("number-card",array("result"=>array(array("data"=>array("TOTAL_RUNTIME"=>1178))),
	"result_columns"=>array(array("name"=>"TOTAL_RUNTIME","role"=>"metric","unit"=>"minutes"))));
f_contains("number card wrap",$strhtml,"t2sNumberCardWrap");
f_contains("number card value",$strhtml,"1,178 min · 19 h 38");
f_contains("number card label",$strhtml,"Total Runtime");
f_check("degraded card has no dollar",strpos(f_t2srep_render("number-card",$arrscalar),"$"),false);
$strhtml=f_t2srep_render("stat-strip",array("result"=>array(array("data"=>array("TOTAL_BUDGET"=>100,"TOTAL_REVENUE"=>640))),
	"result_columns"=>array(array("name"=>"TOTAL_BUDGET","role"=>"metric","unit"=>"usd"),array("name"=>"TOTAL_REVENUE","role"=>"metric","unit"=>"usd"))));
f_contains("stat strip",$strhtml,"t2sStatStrip");
f_contains("stat strip second value",$strhtml,"$640");
$arrgenres=array("result"=>array(array("data"=>array("GENRE_NAME"=>"Drama","MOVIE_COUNT"=>230114)),array("data"=>array("GENRE_NAME"=>"Comedy","MOVIE_COUNT"=>137149))),
	"result_columns"=>array(array("name"=>"GENRE_NAME","role"=>"label","unit"=>null),array("name"=>"MOVIE_COUNT","role"=>"metric","unit"=>"count")));
$strhtml=f_t2srep_render("ranked-bars",$arrgenres);
f_contains("bars list",$strhtml,"t2sBars");
//The ranked quantity is the column marked `ordered`, not the first metric
$arrmoviecols=array(array("name"=>"ID_MOVIE","role"=>"entity_id","unit"=>null),array("name"=>"MOVIE_TITLE","role"=>"label","unit"=>null),
	array("name"=>"IMDB_RATING","role"=>"metric","unit"=>null),array("name"=>"RUNTIME","role"=>"metric","unit"=>"minutes"),
	array("name"=>"REVENUE","role"=>"metric","unit"=>"usd","ordered"=>"desc"));
$arrmovierows=array(array("ID_MOVIE"=>1,"MOVIE_TITLE"=>"Avatar","IMDB_RATING"=>7.9,"RUNTIME"=>162,"REVENUE"=>2923706026));
f_check("ordered metric wins",f_t2srep_pick_bar_metric($arrmovierows,$arrmoviecols),array("name"=>"REVENUE","unit"=>"usd"));
f_check("first metric when none is ordered",f_t2srep_pick_bar_metric($arrmovierows,array_slice($arrmoviecols,0,4)),array("name"=>"IMDB_RATING","unit"=>null));
$strhtmlmovies=f_t2srep_render("ranked-bars",array("result"=>array(array("data"=>$arrmovierows[0]),array("data"=>array("ID_MOVIE"=>2,"MOVIE_TITLE"=>"Titanic","IMDB_RATING"=>7.9,"RUNTIME"=>194,"REVENUE"=>2264162353))),
	"result_columns"=>$arrmoviecols));
f_contains("bars draw the ordered column",$strhtmlmovies,"$2,923,706,026");
f_check("bars do not draw the rating",strpos($strhtmlmovies,">7.9<"),false);
f_contains("bar label",$strhtml,">Comedy<");
f_contains("bar full width for the max",$strhtml,"width:100%");
f_contains("bar proportional width",$strhtml,"width:60%");
f_contains("bar value",$strhtml,"230,114");
$fnposter=function($arrrecord) { return array("url"=>"https://img/w342/".$arrrecord["GENRE_NAME"].".jpg","title"=>$arrrecord["GENRE_NAME"],"href"=>""); };
f_contains("poster ranking when every row has a poster",f_t2srep_render("ranked-bars",$arrgenres,"en",$fnposter),"t2sPosterRanking");
$fnnoposter=function($arrrecord) { return $arrrecord["GENRE_NAME"]=="Drama" ? array("url"=>"x") : null; };
f_contains("bars when one poster is missing",f_t2srep_render("ranked-bars",$arrgenres,"en",$fnnoposter),"t2sBars");
$strhtml=f_t2srep_render("table",array("result"=>array(array("data"=>array("COUNTRY_CODE"=>"US","RUNTIME_SUM"=>90))),
	"result_columns"=>array(array("name"=>"COUNTRY_CODE","role"=>"other","unit"=>null),array("name"=>"RUNTIME_SUM","role"=>"metric","unit"=>"minutes"))));
f_contains("table header",$strhtml,"<th>Country Code</th>");
f_contains("table metric formatted",$strhtml,"90 min · 1 h 30");
f_contains("escaping",f_t2srep_render("table",array("result"=>array(array("data"=>array("A"=>"<b>"))))),"&lt;b&gt;");

//Phase 1, on answers of an evaluation campaign of the API
//(test/t2s-representation-fixtures.php, trimmed exports, English and French)
$arrfix=require(__DIR__."/t2s-representation-fixtures.php");

f_group("Dispatch with representation_options and render=");
$arrlotr=$arrfix["2560-en-value-cards-runtime"];
f_check("value-cards is drawn by the card renderer",f_t2srep_choose($arrlotr),"");
f_check("value-cards is the active view",f_t2srep_active($arrlotr),"value-cards");
f_check("options are the chips, default first",f_t2srep_options($arrlotr),array("value-cards","card-grid","ranked-bars","table"));
f_check("render= picks a valid option",f_t2srep_choose($arrlotr,"ranked-bars"),"ranked-bars");
f_check("render=table",f_t2srep_choose($arrlotr,"table"),"table");
f_check("render= naming no option is ignored",f_t2srep_choose($arrfix["2601-en-card-grid-sequence"],"ranked-bars"),"");
f_check("render= naming no option keeps the default",f_t2srep_active($arrfix["2610-en-table-trend"],"value-cards"),"table");
f_check("one option, no chips",f_t2srep_render_chips(f_t2srep_options($arrfix["2610-en-table-trend"]),"table",array(),array()),"");
f_check("no representation_options: the representation alone",f_t2srep_options(array("representation"=>"ranked-bars")),array("ranked-bars"));
f_check("no hint, no options",f_t2srep_options(array("result"=>array())),array());
//A form of a later phase (here an invented "map-pins") is unknown: the cards, and no chip for it
f_check("an unknown default keeps the cards",f_t2srep_choose(array("representation"=>"map-pins","representation_options"=>array("map-pins","table"))),"");
f_check("an unknown option is no chip",f_t2srep_options(array("representation"=>"map-pins","representation_options"=>array("map-pins","card-grid","table"))),array("card-grid","table"));
f_check("stored sample (none of the new fields) unchanged",f_t2srep_choose(array("representation"=>"ranked-bars","result"=>array())),"ranked-bars");
$strchips=f_t2srep_render_chips(array("value-cards","table"),"table",array("value-cards"=>"/app/index.php?search=a&render=value-cards#answer","table"=>"/app/index.php?search=a&render=table#answer"),array("value-cards"=>"Cards with values","table"=>"Table"),"Show the result as");
f_contains("chip href carries render=",$strchips,"href=\"/app/index.php?search=a&amp;render=value-cards#answer\"");
f_contains("active chip marked",$strchips,"class=\"t2sChip t2sChipActive\" data-t2srep=\"table\"");
f_contains("active chip aria-current",$strchips,"aria-current=\"true\">Table</a>");
f_check("card view holds card-grid and value-cards, values shown",f_t2srep_view_open(array("card-grid","value-cards"),"value-cards","t2sCaption"),
	"<div class=\"t2sRepView t2sRepCards t2sShowValues\" data-t2sreps=\"card-grid value-cards\" aria-describedby=\"t2sCaption\">");
f_check("card view under card-grid: values hidden",f_t2srep_view_open(array("card-grid","value-cards"),"card-grid"),
	"<div class=\"t2sRepView t2sRepCards\" data-t2sreps=\"card-grid value-cards\">");
f_check("inactive view hidden",f_t2srep_view_open(array("table"),"value-cards"),"<div class=\"t2sRepView\" data-t2sreps=\"table\" hidden>");
f_nodecheck("the chips script",f_t2srep_chips_js());

f_group("Value cards");
f_check("short minutes",f_t2srep_format_value(138,"minutes"),"2 h 18");
f_check("short minutes under an hour",f_t2srep_format_value(45,"minutes"),"45 min");
f_check("a year column as is",f_t2srep_format_value(1899,"years","date"),"1899");
f_check("usd as money",f_t2srep_format_value(150000000,"usd"),"$150,000,000");
f_check("rating as is",f_t2srep_format_value(8.1,null),"8.1");
f_check("count",f_t2srep_format_value(405,"count"),"405");
f_check("no value, nothing",f_t2srep_format_value(null,"minutes"),"");
f_check("a 0 runtime is unknown, never 0",f_t2srep_format_value(0,"minutes"),"");
f_check("a 0 budget is unknown",f_t2srep_format_value("0","usd"),"");
$arrlotrcols=f_t2srep_value_columns(f_t2srep_columns($arrlotr));
f_check("focus column",$arrlotrcols,array(array("name"=>"RUNTIME","unit"=>"minutes","role"=>"metric")));
$arrlotrrows=f_t2srep_records($arrlotr["result"]);
f_check("value card of the first film",f_t2srep_card_value_html($arrlotrrows[0],$arrlotrcols),"<div class=\"t2sCardValue\">2 h 59</div>");
f_check("value card of the third film",f_t2srep_card_value_html($arrlotrrows[2],$arrlotrcols),"<div class=\"t2sCardValue\">3 h 21</div>");
f_check("API order kept",$arrlotrrows[0]["MOVIE_TITLE"],"The Lord of the Rings: The Fellowship of the Ring");
f_check("a row with no value shows nothing",f_t2srep_card_value_html(array("RUNTIME"=>null),$arrlotrcols),"");
$arrjurassic=$arrfix["2599-en-compare-two-focus"];
$arrjurassiccols=f_t2srep_value_columns(f_t2srep_columns($arrjurassic));
f_check("two focus columns, both shown",count($arrjurassiccols),2);
$strhtml=f_t2srep_card_value_html(f_t2srep_records($arrjurassic["result"])[0],$arrjurassiccols);
f_contains("two values carry their name (budget)",$strhtml,"<span class=\"t2sCardValueLabel\">Budget</span> $150,000,000");
f_contains("two values carry their name (revenue)",$strhtml,"<span class=\"t2sCardValueLabel\">Revenue</span> $1,671,537,444");
f_contains("French names",f_t2srep_card_value_html(f_t2srep_records($arrfix["2599-fr-compare-two-focus"]["result"])[0],$arrjurassiccols,"fr"),"Box-office</span>");
f_check("the same quantity twice is shown once (IMDB_RATING_WEIGHTED)",f_t2srep_value_columns(f_t2srep_columns($arrfix["2573-en-ranked-bars-rating"])),
	array(array("name"=>"IMDB_RATING","unit"=>null,"role"=>"metric")));
f_check("a birth year compared",f_t2srep_card_value_html(f_t2srep_records($arrfix["2596-en-compare-birth-year"]["result"])[0],f_t2srep_value_columns(f_t2srep_columns($arrfix["2596-en-compare-birth-year"]))),
	"<div class=\"t2sCardValue\">1899</div>");
//A films-and-series union that returns films only: the release date, aliased DAT_FIRST_AIR and
//repeated as DAT_LAST_AIR, is shown once, and named "release date" on a film
$arrunioncols=array(array("name"=>"ID_CONTENT","role"=>"entity_id"),array("name"=>"CONTENT_TYPE","role"=>"other"),
	array("name"=>"CONTENT_TITLE","role"=>"label"),array("name"=>"DAT_FIRST_AIR","role"=>"date","focus"=>true),
	array("name"=>"DAT_LAST_AIR","role"=>"date","focus"=>true));
$arrunionrow=array("ID_CONTENT"=>12445,"CONTENT_TYPE"=>"movie","CONTENT_TITLE"=>"Harry Potter and the Deathly Hallows: Part 2","DAT_FIRST_AIR"=>"2011-07-12","DAT_LAST_AIR"=>"2011-07-12");
f_check("union of films and series: the first date only",f_t2srep_value_columns($arrunioncols),
	array(array("name"=>"DAT_FIRST_AIR","unit"=>null,"role"=>"date")));
f_check("union of films and series: the date shown once",f_t2srep_card_value_html($arrunionrow,f_t2srep_value_columns($arrunioncols)),
	"<div class=\"t2sCardValue\">2011-07-12</div>");
f_contains("a film of the union is named by its release date",f_t2srep_card_value_html($arrunionrow,array(array("name"=>"DAT_FIRST_AIR","unit"=>null,"role"=>"date"),array("name"=>"RUNTIME","unit"=>"minutes","role"=>"metric"))),
	"Release date</span>");
f_contains("a series of the union keeps its first air date",f_t2srep_card_value_html(array("CONTENT_TYPE"=>"serie","DAT_FIRST_AIR"=>"2027-01-01","RUNTIME"=>50),array(array("name"=>"DAT_FIRST_AIR","unit"=>null,"role"=>"date"),array("name"=>"RUNTIME","unit"=>"minutes","role"=>"metric")),"fr"),
	"Première diffusion</span>");
f_check("no focus: the ordered metric",f_t2srep_value_columns(array(array("name"=>"IMDB_RATING","role"=>"metric"),array("name"=>"REVENUE","role"=>"metric","unit"=>"usd","ordered"=>"desc"))),
	array(array("name"=>"REVENUE","unit"=>"usd","role"=>"metric")));
f_check("no focus, no order: the first metric",f_t2srep_value_columns(array(array("name"=>"ID_MOVIE","role"=>"entity_id"),array("name"=>"IMDB_RATING","role"=>"metric"))),
	array(array("name"=>"IMDB_RATING","unit"=>null,"role"=>"metric")));
f_check("no metric, no value",f_t2srep_value_columns(array(array("name"=>"ID_MOVIE","role"=>"entity_id"))),array());

f_group("Rankings");
f_check("bars prefer a focus metric when nothing is ordered",f_t2srep_pick_bar_metric(array(),array(array("name"=>"IMDB_RATING","role"=>"metric"),array("name"=>"BUDGET","role"=>"metric","unit"=>"usd","focus"=>true))),
	array("name"=>"BUDGET","unit"=>"usd"));
f_check("the ordered metric still wins over focus",f_t2srep_pick_bar_metric(array(),f_t2srep_columns($arrjurassic)),array("name"=>"REVENUE","unit"=>"usd"));
$arrscorsese=$arrfix["2571-en-ranked-bars-runtime"];
$fnfixposter=function($arrrecord) { return isset($arrrecord["POSTER_PATH"]) && $arrrecord["POSTER_PATH"]!="" ? array("url"=>"https://img/w342".$arrrecord["POSTER_PATH"],"title"=>$arrrecord["MOVIE_TITLE"],"href"=>"movie.php?movieid=".$arrrecord["ID_MOVIE"]) : null; };
$strhtml=f_t2srep_render("ranked-bars",$arrscorsese,"en",$fnfixposter);
f_contains("five films with posters: the poster ranking",$strhtml,"t2sPosterRanking");
f_contains("numbered from 1",$strhtml,"<div class=\"t2sRankNumber\" aria-hidden=\"true\">1</div>");
f_contains("numbered to 5",$strhtml,"<div class=\"t2sRankNumber\" aria-hidden=\"true\">5</div>");
f_check("no sixth rank",strpos($strhtml,">6</div>"),false);
f_contains("the value under the poster",$strhtml,"296 min · 4 h 56");
$arrdirectors=$arrfix["2576-en-ranked-bars-grouped"];
$fnfixprofile=function($arrrecord) { return isset($arrrecord["PROFILE_PATH"]) && $arrrecord["PROFILE_PATH"]!="" ? array("url"=>"https://img/w185".$arrrecord["PROFILE_PATH"],"title"=>$arrrecord["PERSON_NAME"],"href"=>"") : null; };
$strhtml=f_t2srep_render("ranked-bars",$arrdirectors,"en",$fnfixprofile);
f_contains("twenty rows: bars, not posters",$strhtml,"t2sBars");
f_check("twenty rows: no rank badge",strpos($strhtml,"t2sRankNumber"),false);

f_group("Caption from summary_facts");
f_check("value cards in collection order, en",f_t2srep_caption($arrlotr,"en"),
	"Three films in collection order; the longest is The Lord of the Rings: The Return of the King, 3 h 21.");
f_check("value cards in collection order, fr",f_t2srep_caption($arrfix["2560-fr-value-cards-runtime"],"fr"),
	"Trois films dans l'ordre de la collection ; le plus long est Le Seigneur des anneaux : Le Retour du roi, 3 h 21.");
f_check("release order and the most expensive",f_t2srep_caption($arrfix["2568-en-value-cards-budget-release-order"],"en"),
	"13 films in chronological order; the most expensive is Star Wars: The Rise of Skywalker, $489,900,000.");
f_check("a ranking is the first N, en",f_t2srep_caption($arrscorsese,"en"),
	"The first five films by runtime; the longest is The Concert for New York City, 4 h 56.");
f_check("a ranking is the first N, fr",f_t2srep_caption($arrfix["2571-fr-ranked-bars-runtime"],"fr"),
	"Les cinq premiers films par durée ; le plus long est The Concert for New York City, 4 h 56.");
f_check("a ranking by rating",f_t2srep_caption($arrfix["2573-en-ranked-bars-rating"],"en"),
	"The first eight films by IMDb rating; top: Harry Potter and the Deathly Hallows: Part 2, 8.1.");
f_check("a grouped ranking, fr (feminine noun)",f_t2srep_caption($arrfix["2576-fr-ranked-bars-grouped"],"fr"),
	"Les 20 premières personnes par nombre de films ; en tête : Kevin Dunn, 405.");
f_check("the first N, never a total (\"of 342\")",preg_match('/ of [0-9]/',f_t2srep_caption($arrdirectors,"en")),0);
f_check("twenty in digits",f_t2srep_caption($arrdirectors,"en"),"The first 20 people by number of films; top: Kevin Dunn, 405.");
f_check("a comparison is not a ranking, en",f_t2srep_caption($arrfix["2590-en-compare-runtime"],"en"),"Two films; the longest is Titanic, 3 h 14.");
f_check("a comparison is not a ranking, fr",f_t2srep_caption($arrfix["2590-fr-compare-runtime"],"fr"),"Deux films ; le plus long est Titanic, 3 h 14.");
f_check("two focus columns: the extremum of the value column",f_t2srep_caption($arrjurassic,"en"),"Two films; the most expensive is Jurassic World, $150,000,000.");
f_check("two focus columns, fr",f_t2srep_caption($arrfix["2599-fr-compare-two-focus"],"fr"),"Deux films ; le plus cher est Jurassic World, $150,000,000.");
f_check("no top: count and order only",f_t2srep_caption($arrfix["2596-en-compare-birth-year"],"en"),"Two people in chronological order.");
f_check("a plain card grid keeps no caption",f_t2srep_caption($arrfix["2601-en-card-grid-sequence"],"en"),"");
f_check("a table with no value column keeps no caption",f_t2srep_caption($arrfix["2610-en-table-trend"],"en"),"");
f_check("one row, no caption",f_t2srep_caption($arrfix["2554-en-number-card"],"en"),"");
f_check("no summary_facts (older answer), no caption",f_t2srep_caption(array("representation"=>"card-grid","result"=>array()),"en"),"");
$arrshortest=$arrlotr;
$arrshortest["summary_facts"]["top"]=array("label"=>"Fellowship","value"=>178,"extreme"=>"min");
f_check("the shortest",f_t2srep_caption($arrshortest,"en"),"Three films in collection order; the shortest is Fellowship, 2 h 58.");
$arrseries=array("result_columns"=>array(array("name"=>"ID_SERIE","role"=>"entity_id"),array("name"=>"SERIE_TITLE","role"=>"label"),array("name"=>"RUNTIME","role"=>"metric","unit"=>"minutes","focus"=>true)),
	"result"=>array(),"summary_facts"=>array("count"=>2,"value_column"=>"RUNTIME","unit"=>"minutes","order"=>null,"top"=>array("label"=>"Twin Peaks","value"=>47,"extreme"=>"max")));
f_check("French agreement with a feminine noun",f_t2srep_caption($arrseries,"fr"),"Deux séries ; la plus longue est Twin Peaks, 47 min.");
$arrseries["summary_facts"]["value_column"]="POPULARITY";
$arrseries["summary_facts"]["unit"]=null;
$arrseries["summary_facts"]["top"]["value"]=12.5;
f_check("generic extremum, en",f_t2srep_caption($arrseries,"en"),"Two series; the highest popularity goes to Twin Peaks, 12.5.");
f_check("generic extremum, fr",f_t2srep_caption($arrseries,"fr"),"Deux séries ; la popularité la plus élevée revient à Twin Peaks, 12.5.");
f_check("no em dash in any caption",strpos(implode("",array_map(function($j){ return f_t2srep_caption($j,"en").f_t2srep_caption($j,"fr"); },$arrfix)),"\u{2014}"),false);

// Bar labels link to the entity page (2026-11-08)
f_check("href person",f_t2srep_entity_href(array("ID_PERSON"=>1061603,"PERSON_NAME"=>"Kevin Dunn","FILM_COUNT"=>405)),"person.php?personid=1061603");
f_check("href movie+serie union",f_t2srep_entity_href(array("ID_CONTENT"=>1399,"CONTENT_TYPE"=>"serie")),"serie.php?serieid=1399");
f_check("href none",f_t2srep_entity_href(array("COUNTRY_CODE"=>"FR","MOVIE_COUNT"=>3)),"");
f_check("bar label is a link",strpos(f_t2srep_render_ranked_bars(array(array("ID_PERSON"=>1,"PERSON_NAME"=>"A","FILM_COUNT"=>3),array("ID_PERSON"=>2,"PERSON_NAME"=>"B","FILM_COUNT"=>2)),array(array("name"=>"ID_PERSON","role"=>"entity_id","unit"=>null),array("name"=>"PERSON_NAME","role"=>"label","unit"=>null),array("name"=>"FILM_COUNT","role"=>"metric","unit"=>"count","ordered"=>"desc")),"en"),"<a href=\"person.php?personid=1\">A</a>")!==false,true);

//Phase 2 lot A: timeline, sequence-strip, trend-columns, on answers recomputed with
//the module of FASTAPI-TEXT2SQL phase 2 lot A (fixtures lota-*)
f_group("Phase 2 lot A: dispatch and chips");
$arralien=$arrfix["lota-timeline-alien-en"];
$arrdollars=$arrfix["lota-sequence-dollars-en"];
$arrwoody=$arrfix["lota-trend-woody-allen-decades-en"];
f_check("timeline is drawn by this module",f_t2srep_choose($arralien),"timeline");
f_check("timeline chips",f_t2srep_options($arralien),array("timeline","card-grid","value-cards","table"));
f_check("render=card-grid from a timeline",f_t2srep_choose($arralien,"card-grid"),"");
f_check("sequence-strip is the card grid numbered",f_t2srep_choose($arrdollars),"");
f_check("sequence-strip is the active view",f_t2srep_active($arrdollars),"sequence-strip");
f_check("sequence chips",f_t2srep_options($arrdollars),array("sequence-strip","card-grid","table"));
f_check("render=table from a sequence",f_t2srep_choose($arrdollars,"table"),"table");
f_check("trend-columns is drawn by this module",f_t2srep_choose($arrwoody),"trend-columns");
f_check("render=trend-columns only when offered",f_t2srep_choose($arrfix["2610-en-table-trend"],"trend-columns"),"table");
f_check("the three are known (render= accepted)",count(array_intersect(array("timeline","sequence-strip","trend-columns"),f_t2srep_known())),3);
f_check("card view numbered under sequence-strip",f_t2srep_view_open(array("sequence-strip","card-grid"),"sequence-strip","t2sCaption"),
	"<div class=\"t2sRepView t2sRepCards t2sShowSequence\" data-t2sreps=\"sequence-strip card-grid\" aria-describedby=\"t2sCaption\">");
f_check("card view under card-grid: no numbers",f_t2srep_view_open(array("sequence-strip","card-grid"),"card-grid"),
	"<div class=\"t2sRepView t2sRepCards\" data-t2sreps=\"sequence-strip card-grid\">");
f_contains("the chips script toggles the numbers",f_t2srep_chips_js(),"t2sShowSequence");
f_check("sequence number badge",f_t2srep_sequence_number_html(51),"<div class=\"t2sRankNumber t2sSeqNumber\" aria-hidden=\"true\">51</div>");

f_group("Phase 2 lot A: years and periods");
f_check("year of a date",f_t2srep_year("1979-05-25"),1979);
f_check("year of a number",f_t2srep_year(1979),1979);
f_check("year of a numeric string",f_t2srep_year("2001"),2001);
f_check("no year: blank",f_t2srep_year(""),null);
f_check("no year: null",f_t2srep_year(null),null);
f_check("no year: zero date",f_t2srep_year("0000-00-00"),null);
f_check("no year: text",f_t2srep_year("unknown"),null);
f_check("timeline column: the value column",f_t2srep_date_column(f_t2srep_columns($arrfix["lota-timeline-harry-potter-mixed-aliases-en"]),"DAT_FIRST_AIR"),"DAT_FIRST_AIR");
f_check("timeline column: the first focus date",f_t2srep_date_column(array(array("name"=>"DAT_A","role"=>"date"),array("name"=>"DAT_B","role"=>"date","focus"=>true))),"DAT_B");
f_check("sequence column: the first date",f_t2srep_date_column(f_t2srep_columns($arrdollars)),"DAT_FIRST_AIR");
f_check("no date column",f_t2srep_date_column(array(array("name"=>"ID_MOVIE","role"=>"entity_id"))),"");
f_check("decade by name",f_t2srep_is_decade_column("DECADE",array()),true);
f_check("decade by name, fr",f_t2srep_is_decade_column("DECENNIE",array()),true);
f_check("years are not decades",f_t2srep_is_decade_column("RELEASE_YEAR",f_t2srep_records($arrfix["lota-trend-films-per-year-en"]["result"])),false);
f_check("decades by value",f_t2srep_is_decade_column("PERIOD",array(array("PERIOD"=>1960),array("PERIOD"=>1970))),true);
f_check("decade label en",f_t2srep_period_label(1980,true,"en"),"1980s");
f_check("decade label fr",f_t2srep_period_label(1980,true,"fr"),"années 1980");
f_check("year label",f_t2srep_period_label(2023,false,"fr"),"2023");
$arrhp=$arrfix["lota-timeline-harry-potter-mixed-aliases-en"];
$arrhprows=f_t2srep_records($arrhp["result"]);
$arrhpgroups=f_t2srep_timeline_groups($arrhprows,"DAT_FIRST_AIR");
f_check("timeline sorted by year, whatever the SQL order (rating here)",array_keys($arrhpgroups["years"]),array(2001,2002,2004,2005,2007,2009,2010,2011));
$arrstack=array(array("ID_MOVIE"=>1,"MOVIE_TITLE"=>"A","DAT_RELEASE"=>"1999-03-01"),array("ID_MOVIE"=>2,"MOVIE_TITLE"=>"B","DAT_RELEASE"=>null),
	array("ID_MOVIE"=>3,"MOVIE_TITLE"=>"C","DAT_RELEASE"=>"1999-11-01"),array("ID_MOVIE"=>4,"MOVIE_TITLE"=>"D","DAT_RELEASE"=>"1985-01-01"));
$arrstackgroups=f_t2srep_timeline_groups($arrstack,"DAT_RELEASE");
f_check("same year stacked, row order kept",$arrstackgroups["years"][1999],array(0,2));
f_check("undated apart",$arrstackgroups["undated"],array(1));
f_check("span",f_t2srep_year_span($arrstack,"DAT_RELEASE"),array("min"=>1985,"max"=>1999,"dated"=>3,"undated"=>1));

f_group("Phase 2 lot A: timeline rendering");
$strhtml=f_t2srep_render("timeline",$arralien,"en",$fnfixposter);
f_contains("timeline region",$strhtml,"<div class=\"t2sTimeline\" role=\"region\" aria-label=\"Timeline, from 1979 to 1997\" tabindex=\"0\">");
f_contains("a mark links to the entity page",$strhtml,"<a href=\"movie.php?movieid=348\" title=\"Alien\"><img src=\"https://img/w342");
f_contains("the year under the marks",$strhtml,"<span class=\"t2sTimelineYearLabel\">1986</span>");
f_contains("a gap widens with the years between (1979 to 1986)",$strhtml,"style=\"width:3.6rem\"");
f_check("no undated list when every row has a date",strpos($strhtml,"t2sTimelineUndated"),false);
$strhtml=f_t2srep_render("timeline",$arrfix["lota-timeline-alien-fr"],"fr",$fnfixposter);
f_contains("timeline label fr",$strhtml,"aria-label=\"Frise chronologique, de 1979 à 1997\"");
$fncontentposter=function($arrrecord) { return isset($arrrecord["POSTER_PATH"]) ? array("url"=>"https://img/w342".$arrrecord["POSTER_PATH"],"title"=>$arrrecord["CONTENT_TITLE"],"href"=>"movie.php?movieid=".$arrrecord["ID_CONTENT"]) : null; };
$strhtml=f_t2srep_render("timeline",$arrhp,"en",$fncontentposter);
f_contains("mixed aliases: CONTENT_TITLE and DAT_FIRST_AIR",$strhtml,"<span class=\"t2sTimelineYearLabel\">2001</span><ul class=\"t2sTimelineMarks\"><li class=\"t2sTimelineMark\" data-t2syear=\"2001\"><a href=\"movie.php?movieid=671\" title=\"Harry Potter and the Philosopher&#039;s Stone\">");
$strhtml=f_t2srep_render_timeline($arrhprows,f_t2srep_columns($arrhp),"en",null,$arrhp["summary_facts"]);
f_contains("mixed aliases without a poster callable: routed by CONTENT_TYPE",$strhtml,"<a href=\"movie.php?movieid=12445\"");
f_contains("no poster: a placeholder",$strhtml,"t2sTimelineNoPoster");
$arrstackcols=array(array("name"=>"ID_MOVIE","role"=>"entity_id"),array("name"=>"MOVIE_TITLE","role"=>"label"),array("name"=>"DAT_RELEASE","role"=>"date","focus"=>true));
$strhtml=f_t2srep_render_timeline($arrstack,$arrstackcols,"en");
f_contains("two marks stacked in one year",$strhtml,"<span class=\"t2sTimelineYearLabel\">1999</span><ul class=\"t2sTimelineMarks\"><li class=\"t2sTimelineMark\" data-t2syear=\"1999\"><a href=\"movie.php?movieid=1\" title=\"A\">");
f_contains("the second of the year",$strhtml,"movieid=3\" title=\"C\"");
f_contains("a long gap is a break",$strhtml,"t2sTimelineBreak");
f_contains("undated listed apart",$strhtml,"<div class=\"t2sTimelineUndated\"><span class=\"t2sTimelineUndatedLabel\">No date</span><ul class=\"t2sTimelineMarks\"><li class=\"t2sTimelineMark\"><a href=\"movie.php?movieid=2\" title=\"B\">");
f_contains("escaping in a mark",f_t2srep_render_timeline(array(array("ID_MOVIE"=>"1\"><x","MOVIE_TITLE"=>"<b>","DAT_RELEASE"=>"2000")),$arrstackcols,"en"),"&lt;b&gt;");
f_contains("no date at all: the table",f_t2srep_render_timeline(array(array("ID_MOVIE"=>1,"MOVIE_TITLE"=>"A","DAT_RELEASE"=>null)),$arrstackcols,"en"),"t2sTable");

f_group("Phase 2 lot A: trend columns rendering");
$strhtml=f_t2srep_render("trend-columns",$arrwoody,"en");
f_contains("trend figure, accessible name",$strhtml,"<figure class=\"t2sTrend\" role=\"group\" aria-label=\"Number of films per decade\">");
f_contains("decade label",$strhtml,"<span class=\"t2sTrendLabel\">1980s</span>");
f_contains("the peak column",$strhtml,"<li class=\"t2sTrendCol t2sTrendPeak\" title=\"1980s: 11\">");
f_contains("height proportional (2 of 11)",$strhtml,"height:18%");
f_contains("height of the max",$strhtml,"height:100%");
$strhtml=f_t2srep_render("trend-columns",$arrfix["lota-trend-woody-allen-decades-fr"],"fr");
f_contains("decade label fr",$strhtml,"<span class=\"t2sTrendLabel\">années 1980</span>");
f_contains("trend name fr",$strhtml,"aria-label=\"Nombre de films par décennie\"");
$strhtml=f_t2srep_render("trend-columns",$arrfix["lota-trend-avg-budget-usd-en"],"en");
f_contains("dense: 32 columns",$strhtml,"t2sTrend t2sTrendDense");
f_contains("usd formatted",$strhtml,">$11,106,636<");
f_contains("year label",$strhtml,"<span class=\"t2sTrendLabel\">2028</span>");
f_contains("a zero value is a column of height 0",$strhtml,"title=\"2029: $0\"");
$strhtml=f_t2srep_render("trend-columns",$arrfix["lota-trend-avg-runtime-decade-en"],"en");
f_contains("minutes formatted",$strhtml,">69 min · 1 h 09<");
f_contains("in row order, the first period first",$strhtml,"<ol class=\"t2sTrendCols\"><li class=\"t2sTrendCol\" title=\"1870s: 1 min · 1 min\">");
f_check("no metric: the table",strpos(f_t2srep_render_trend_columns(array(array("DECADE"=>1960)),array(array("name"=>"DECADE","role"=>"date")),"en"),"t2sTable")!==false,true);

f_group("Phase 2 lot A: captions (contract, Spoken rows)");
f_check("timeline, en",f_t2srep_caption($arralien,"en"),"Four films, from 1979 to 1997.");
f_check("timeline, fr",f_t2srep_caption($arrfix["lota-timeline-alien-fr"],"fr"),"Quatre films, de 1979 à 1997.");
f_check("timeline, mixed aliases",f_t2srep_caption($arrhp,"en"),"Eight films, from 2001 to 2011.");
f_check("timeline, digits beyond twelve",f_t2srep_caption($arrfix["lota-timeline-nolan-en"],"en"),"18 films, from 1990 to 2026.");
$arrstackanswer=array("representation"=>"timeline","result_columns"=>$arrstackcols,"result"=>$arrstack,
	"summary_facts"=>array("count"=>4,"value_column"=>"DAT_RELEASE","unit"=>null,"order"=>null,"top"=>null));
f_check("timeline with an undated row, en",f_t2srep_caption($arrstackanswer,"en"),"Four films, from 1985 to 1999; one without a date.");
f_check("timeline with an undated row, fr",f_t2srep_caption($arrstackanswer,"fr"),"Quatre films, de 1985 à 1999 ; un sans date.");
f_check("sequence in release order, en",f_t2srep_caption($arrdollars,"en"),"Three films in release order, from 1964 to 1966.");
f_check("sequence in release order, fr",f_t2srep_caption($arrfix["lota-sequence-dollars-fr"],"fr"),"Trois films dans l'ordre de sortie, de 1964 à 1966.");
f_check("sequence in collection order, fr",f_t2srep_caption($arrfix["lota-sequence-indiana-collection-order-fr"],"fr"),"Cinq films dans l'ordre de la collection, de 1981 à 2023.");
f_check("sequence of 54",f_t2srep_caption($arrfix["lota-sequence-mcu-54rows-en"],"en"),"54 films in release order, from 2008 to 2027.");
f_check("a sequence the SQL ordered by rating stays a card grid, no caption",f_t2srep_caption($arrfix["lota-sequence-indiana-collection-order-en"],"en"),"");
$arrdesc=$arrdollars;
$arrdesc["summary_facts"]["order_direction"]="desc";
f_check("sequence most recent first",f_t2srep_caption($arrdesc,"en"),"Three films, most recent first, from 1966 to 1964.");
f_check("trend by decade, en",f_t2srep_caption($arrwoody,"en"),"From the 1960s to the 2020s; the peak is the 1980s, 11 films.");
f_check("trend by decade, fr",f_t2srep_caption($arrfix["lota-trend-woody-allen-decades-fr"],"fr"),"Des années 1960 aux années 2020 ; le pic est dans les années 1980, avec 11 films.");
f_check("trend by year, usd",f_t2srep_caption($arrfix["lota-trend-avg-budget-usd-en"],"en"),"From 2000 to 2099; the peak is 2028, $11,106,636.");
f_check("trend by year, fr",f_t2srep_caption($arrfix["lota-trend-films-per-year-fr"],"fr"),"De 2000 à 2099 ; le pic est en 2023, avec 21,776 films.");
f_check("trend in minutes",f_t2srep_caption($arrfix["lota-trend-avg-runtime-decade-en"],"en"),"From the 1870s to the 2090s; the peak is the 1990s, 1 h 09.");
$arrlow=$arrwoody;
$arrlow["summary_facts"]["top"]=array("label"=>null,"index"=>0,"value"=>2,"extreme"=>"min");
f_check("trend low point",f_t2srep_caption($arrlow,"en"),"From the 1960s to the 2020s; the low point is the 1960s, 2 films.");
$arrnotop=$arrwoody;
$arrnotop["summary_facts"]["top"]=null;
f_check("trend without top: the span alone",f_t2srep_caption($arrnotop,"en"),"From the 1960s to the 2020s.");
f_check("no em dash in any lot A caption",strpos(implode("",array_map(function($j){ return f_t2srep_caption($j,"en").f_t2srep_caption($j,"fr"); },$arrfix)),"\u{2014}"),false);

f_group("Phase 2 lot A: more pages (timeline appends, caption says the first N, then recounts)");
f_check("timeline with more pages, en",f_t2srep_caption($arrfix["lota-timeline-nolan-en"],"en",true),"The first 18 films, from 1990 to 2026.");
f_check("timeline with more pages, fr",f_t2srep_caption($arrfix["lota-timeline-alien-fr"],"fr",true),"Les quatre premiers films, de 1979 à 1997.");
f_check("sequence with more pages",f_t2srep_caption($arrfix["lota-sequence-mcu-54rows-en"],"en",true),"The first 54 films in release order, from 2008 to 2027.");
f_check("value cards with more pages",f_t2srep_caption($arrfix["2568-en-value-cards-budget-release-order"],"en",true),
	"The first 13 films in chronological order; the most expensive is Star Wars: The Rise of Skywalker, $489,900,000.");
f_check("value cards with more pages, fr",f_t2srep_caption($arrfix["2560-fr-value-cards-runtime"],"fr",true),
	"Les trois premiers films dans l'ordre de la collection ; le plus long est Le Seigneur des anneaux : Le Retour du roi, 3 h 21.");
f_check("a ranking is unchanged by more pages",f_t2srep_caption($arrscorsese,"en",true),f_t2srep_caption($arrscorsese,"en"));
f_check("no spec for a ranking",f_t2srep_caption_spec($arrscorsese,"en"),null);
$arrspectimeline=f_t2srep_caption_spec($arrstackanswer,"en");
f_check("timeline spec column",$arrspectimeline["column"],"DAT_RELEASE");
f_check("fill: every page counted",f_t2srep_caption_fill($arrspectimeline,104,1962,2024,3,false),"104 films, from 1962 to 2024; three without a date.");
f_check("fill: one year",f_t2srep_caption_fill($arrspectimeline,2,1999,1999,0,false),"Two films, in 1999.");
f_check("fill: thousands",f_t2srep_caption_fill($arrspectimeline,1250,1900,2020,0,true),"The first 1,250 films, from 1900 to 2020.");
$strhtml=f_t2srep_render("timeline",$arralien,"en",$fnfixposter);
f_contains("a year column carries its year",$strhtml,"<li class=\"t2sTimelineYear\" data-t2syear=\"1986\">");
$strhtml=f_t2srep_render_timeline_page(array("representation"=>"timeline","result_columns"=>$arrstackcols,"result"=>$arrstack,
	"summary_facts"=>array("count"=>4,"value_column"=>"DAT_RELEASE")),"en",null,true);
f_contains("next page: a hidden flat list that says more may follow",$strhtml,"<ul class=\"t2sTimelinePage\" data-has-more=\"1\" hidden>");
f_check("next page: one mark per row",substr_count($strhtml,"<li class=\"t2sTimelineMark\""),4);
f_contains("next page: a dated mark carries its year",$strhtml,"<li class=\"t2sTimelineMark\" data-t2syear=\"1985\"><a href=\"movie.php?movieid=4\"");
f_contains("next page: an undated mark carries none",$strhtml,"<li class=\"t2sTimelineMark\"><a href=\"movie.php?movieid=2\"");
f_contains("next page: the last one says so",f_t2srep_render_timeline_page(array("result"=>array()),"en"),"data-has-more=\"0\"");
$arrtimelinecfg=array("page"=>2,"maxauto"=>4,"more"=>"Load more","loading"=>"Loading","suffix"=>" retrieved","nodate"=>"No date","aria"=>f_t2srep_timeline_aria("fr"));
f_nodecheck("the timeline loader script",f_t2srep_timeline_more_js($arrtimelinecfg));
f_contains("the loader asks for the timeline page",f_t2srep_timeline_more_js($arrtimelinecfg),"searchParams.set('loadmoreview','timeline')");
f_check("the loader config is json, no raw tag",strpos(f_t2srep_timeline_more_js(array("more"=>"</script>")),"</script>"),false);
f_nodecheck("the caption script",f_t2srep_caption_live_js($arrspectimeline));
//The caption the page recounts must be the caption PHP writes: the JavaScript fill runs on the
//same pieces and numbers as the PHP one
$arrfillcases=array(
	array(f_t2srep_caption_spec($arrstackanswer,"en"),104,1962,2024,3,false),
	array(f_t2srep_caption_spec($arrstackanswer,"fr"),104,1962,2024,1,true),
	array(f_t2srep_caption_spec($arrstackanswer,"en"),2,1999,1999,0,false),
	array(f_t2srep_caption_spec($arrstackanswer,"en"),5,null,null,5,false),
	array(f_t2srep_caption_spec($arrfix["lota-sequence-dollars-fr"],"fr"),61,1964,2020,0,true),
	array(f_t2srep_caption_spec($arrdesc,"en"),3,1964,1966,0,false),
	array(f_t2srep_caption_spec($arrfix["lota-sequence-indiana-collection-order-fr"],"fr"),12,1981,2023,0,false),
	array(f_t2srep_caption_spec($arrfix["lota-sequence-mcu-54rows-en"],"en"),1250,2008,2027,0,true),
);
$arrout=array();
$lngstatus=1;
exec("node --version 2>&1",$arrout,$lngstatus);
if ($lngstatus!==0) {
	echo "  SKIP  caption fill, PHP against JavaScript (node is not on the PATH; a skip is not a pass)\n";
} else {
	$strjs=f_t2srep_caption_fill_js()."var arrcases=".json_encode($arrfillcases, JSON_UNESCAPED_UNICODE).";"
		."console.log(JSON.stringify(arrcases.map(function(a){return f_t2srepcaptionfill(a[0],a[1],a[2],a[3],a[4],a[5]);})));";
	$strtempfile=tempnam(sys_get_temp_dir(),"t2srep").".js";
	file_put_contents($strtempfile,$strjs);
	$arrout=array();
	exec("node ".escapeshellarg($strtempfile)." 2>&1",$arrout,$lngstatus);
	@unlink($strtempfile);
	$arrjs=json_decode(implode("",$arrout),true);
	$arrphp=array_map(function($a) { return f_t2srep_caption_fill($a[0],$a[1],$a[2],$a[3],$a[4],$a[5]); },$arrfillcases);
	f_check("caption fill, PHP and JavaScript say the same sentence",$arrjs,$arrphp);
	f_check("caption fill, a reversed sequence",$arrphp[5],"Three films, most recent first, from 1966 to 1964.");
	f_check("caption fill, fr, the first N and one undated",$arrphp[1],"Les 104 premiers films, de 1962 à 2024 ; un sans date.");
}

//Phase 2 lot B: compare-cards, fact-card, verdict, on live answers of API 1.1.20
//reclassified with the module of phase 2 lot B (fixtures lotb-*)
f_group("Phase 2 lot B: dispatch, chips and evidence");
$arrgodfather=$arrfix["lotb-compare-godfather-runtime-en"];
$arrtie=$arrfix["lotb-compare-seasons-tie-en"];
$arrtwofocus=$arrfix["lotb-compare-two-focus-en"];
$arrbornbefore=$arrfix["lotb-compare-born-before-en"];
$arrlawrence=$arrfix["lotb-fact-runtime-en"];
$arrvertigo=$arrfix["lotb-fact-vertigo-homonyms-en"];
$arrchernobyl=$arrfix["lotb-fact-chernobyl-homonym-en"];
$arrjaws=$arrfix["lotb-verdict-yes-en"];
$arrkubrick=$arrfix["lotb-verdict-yes-two-rows-en"];
$arrempty=$arrfix["lotb-verdict-no-empty-en"];
$arrcountzero=$arrfix["lotb-verdict-count-zero-en"];
f_check("the three are known (render= accepted)",count(array_intersect(array("compare-cards","fact-card","verdict"),f_t2srep_known())),3);
f_check("the three are drawn by this module",count(array_intersect(array("compare-cards","fact-card","verdict"),f_t2srep_module_reps())),3);
f_check("compare-cards is drawn by this module",f_t2srep_choose($arrgodfather),"compare-cards");
f_check("compare chips",f_t2srep_options($arrgodfather),array("compare-cards","card-grid","value-cards","ranked-bars","table"));
f_check("render=value-cards from a comparison",f_t2srep_choose($arrgodfather,"value-cards"),"");
f_check("render=value-cards is then the active view",f_t2srep_active($arrgodfather,"value-cards"),"value-cards");
f_check("fact-card is drawn by this module",f_t2srep_choose($arrlawrence),"fact-card");
f_check("fact chips",f_t2srep_options($arrlawrence),array("fact-card","entity-page","table"));
f_check("render=entity-page from a fact: the card renderer",f_t2srep_choose($arrlawrence,"entity-page"),"");
f_check("homonyms offer compare-cards behind the fact",f_t2srep_choose($arrchernobyl,"compare-cards"),"compare-cards");
f_check("verdict is drawn by this module",f_t2srep_choose($arrjaws),"verdict");
f_check("an empty verdict is drawn too",f_t2srep_choose($arrempty),"verdict");
f_check("evidence of one row: the entity page",f_t2srep_verdict_evidence($arrjaws),"entity-page");
f_check("evidence of rows: the card grid",f_t2srep_verdict_evidence($arrkubrick),"card-grid");
f_check("evidence of a count: the number card",f_t2srep_verdict_evidence($arrcountzero),"number-card");
f_check("evidence of an empty result: nothing",f_t2srep_verdict_evidence($arrempty),"");
f_check("card view under a verdict: shown, no values",f_t2srep_view_open(array("verdict","card-grid"),"verdict","t2sCaption"),
	"<div class=\"t2sRepView t2sRepCards\" data-t2sreps=\"verdict card-grid\" aria-describedby=\"t2sCaption\">");
f_check("the same card view under card-grid",strpos(f_t2srep_view_open(array("verdict","card-grid"),"card-grid")," hidden"),false);
f_check("the verdict view hidden under card-grid",f_t2srep_view_open(array("verdict"),"card-grid"),"<div class=\"t2sRepView\" data-t2sreps=\"verdict\" hidden>");
$strchips=f_t2srep_render_chips(f_t2srep_options($arrlawrence),"fact-card",array(),array("fact-card"=>"Fact","entity-page"=>"Card","table"=>"Table"));
f_contains("a fact on one row has its chips",$strchips,"data-t2srep=\"fact-card\"");

f_group("Phase 2 lot B: dates");
f_check("a full date, en",f_t2srep_format_date("1958-05-28","en"),"28 May 1958");
f_check("a full date, fr",f_t2srep_format_date("1958-05-28","fr"),"28 mai 1958");
f_check("the first of the month, fr",f_t2srep_format_date("1958-05-01","fr"),"1er mai 1958");
f_check("no day",f_t2srep_format_date("1958-05-00","en"),"May 1958");
f_check("a year column",f_t2srep_format_date(1910,"fr"),"1910");
f_check("a zero date is nothing",f_t2srep_format_date("0000-00-00","en"),"");
f_check("blank is nothing",f_t2srep_format_date(null,"en"),"");
f_check("text kept",f_t2srep_format_date("circa 1900","en"),"circa 1900");
f_check("a date part is no quantity",f_t2srep_is_date_part("BIRTH_MONTH") && f_t2srep_is_date_part("BIRTH_DAY") && !f_t2srep_is_date_part("BIRTH_YEAR"),true);

f_group("Phase 2 lot B: compare-cards rendering");
$strhtml=f_t2srep_render("compare-cards",$arrgodfather,"en",$fnfixposter);
f_contains("a list named by its lead",$strhtml,"<ul class=\"t2sCompare t2sCompareN3\" aria-label=\"Three titles side by side\">");
f_contains("the winner outlined, linked to its page, poster shown",$strhtml,"<li class=\"t2sCompareCard t2sCompareWinner\"><a class=\"t2sCompareLink\" href=\"movie.php?movieid=240\"><span class=\"t2sCompareMedia\"><img src=\"https://img/w342/8a1lJs7mFyGhGhZZDT1azJUoQiZ.jpg\"");
f_contains("the winning value badged with a word",$strhtml,"<div class=\"t2sCompareQty t2sCompareWin\"><span class=\"t2sCompareQtyName\">Runtime</span><span class=\"t2sCompareQtyValue\">3 h 22</span><span class=\"t2sCompareBadge\">Leads</span></div>");
f_check("one winner only",substr_count($strhtml,"t2sCompareWinner"),1);
f_contains("the other values unmarked",$strhtml,"<div class=\"t2sCompareQty\"><span class=\"t2sCompareQtyName\">Runtime</span><span class=\"t2sCompareQtyValue\">2 h 55</span></div>");
f_contains("a homonym told apart by its year",$strhtml,"<span class=\"t2sCompareYear\">2023</span>");
f_check("no tie line",strpos($strhtml,"t2sCompareTie"),false);
$strhtml=f_t2srep_render("compare-cards",$arrfix["lotb-compare-yes-no-runtime-fr"],"fr",$fnfixposter);
f_contains("French names and badge",$strhtml,"<span class=\"t2sCompareQtyName\">Durée</span><span class=\"t2sCompareQtyValue\">3 h 14</span><span class=\"t2sCompareBadge\">En tête</span>");
f_contains("French lead",$strhtml,"aria-label=\"Deux titres côte à côte\"");
$strhtml=f_t2srep_render("compare-cards",$arrtie,"en",$fnfixposter);
f_check("a tie marks no card",strpos($strhtml,"t2sCompareWinner"),false);
f_check("a tie has no badge",strpos($strhtml,"t2sCompareBadge"),false);
f_contains("a tie says so",$strhtml,"<p class=\"t2sCompareTie\">Tie: same number of seasons, 5</p>");
f_contains("a tie says so, fr",f_t2srep_render("compare-cards",$arrtie,"fr"),"<p class=\"t2sCompareTie\">Égalité : même nombre de saisons, 5</p>");
$strhtml=f_t2srep_render("compare-cards",$arrtwofocus,"en",$fnfixposter);
f_check("two quantities under each of two cards",substr_count($strhtml,"<div class=\"t2sCompareQty"),4);
f_check("two quantities won by the same card",substr_count($strhtml,"t2sCompareBadge"),2);
f_contains("budget",$strhtml,"<span class=\"t2sCompareQtyName\">Budget</span><span class=\"t2sCompareQtyValue\">$237,000,000</span>");
f_contains("revenue",$strhtml,"<span class=\"t2sCompareQtyName\">Revenue</span><span class=\"t2sCompareQtyValue\">$2,264,162,353</span>");
$strhtml=f_t2srep_render("compare-cards",$arrbornbefore,"en",$fnfixprofile);
f_contains("a birth year compared, the first born badged",$strhtml,"<span class=\"t2sCompareQtyName\">Birth year</span><span class=\"t2sCompareQtyValue\">1899</span><span class=\"t2sCompareBadge\">First</span>");
f_check("the month and the day are not compared",strpos($strhtml,"Birth month")===false && strpos($strhtml,"Birth day")===false,true);
f_check("no year line when the date is compared",strpos($strhtml,"t2sCompareYear"),false);
f_contains("a face for a person",$strhtml,"<img src=\"https://img/w185/108fiNM6poRieMg7RIqLJRxdAwG.jpg\"");
f_contains("without the poster callable: the route of the id",f_t2srep_render_compare_cards(f_t2srep_records($arrbornbefore["result"]),f_t2srep_columns($arrbornbefore),"en",null,$arrbornbefore["summary_facts"]),
	"<a class=\"t2sCompareLink\" href=\"person.php?personid=2636\"><span class=\"t2sCompareMedia\"><span class=\"t2sCompareNoPoster\" aria-hidden=\"true\"></span></span><span class=\"t2sCompareTitle\">Alfred Hitchcock</span></a>");
$arrnowinners=$arrfix["lotb-compare-yes-no-runtime-en"];
unset($arrnowinners["summary_facts"]["winners"]);
$strhtml=f_t2srep_render("compare-cards",$arrnowinners,"en");
f_contains("no winners (an older answer): the value columns, unmarked",$strhtml,"<span class=\"t2sCompareQtyValue\">3 h 14</span></div>");
f_check("no winners, no mark",strpos($strhtml,"t2sCompareWinner"),false);
$arrfive=array_fill(0,5,array("ID_MOVIE"=>1,"MOVIE_TITLE"=>"A","RUNTIME"=>100));
f_check("four cards at most",substr_count(f_t2srep_render_compare_cards($arrfive,f_t2srep_columns($arrgodfather),"en"),"<li class=\"t2sCompareCard"),4);
f_contains("a missing value is said, never 0",f_t2srep_render_compare_cards(array(array("ID_MOVIE"=>1,"MOVIE_TITLE"=>"A","RUNTIME"=>0),array("ID_MOVIE"=>2,"MOVIE_TITLE"=>"B","RUNTIME"=>90)),f_t2srep_columns($arrgodfather),"en"),
	"<span class=\"t2sCompareNone\">Not known</span>");
f_contains("escaping in a card",f_t2srep_render_compare_cards(array(array("ID_MOVIE"=>"1\"><x","MOVIE_TITLE"=>"<b>","RUNTIME"=>90)),f_t2srep_columns($arrgodfather),"en"),"&lt;b&gt;");
f_contains("nothing to compare: the table",f_t2srep_render_compare_cards(array(array("ID_MOVIE"=>1,"MOVIE_TITLE"=>"A")),array(array("name"=>"ID_MOVIE","role"=>"entity_id"),array("name"=>"MOVIE_TITLE","role"=>"label")),"en"),"t2sTable");

f_group("Phase 2 lot B: fact-card rendering");
$strhtml=f_t2srep_render("fact-card",$arrlawrence,"en",$fnfixposter);
f_contains("the fact named large",$strhtml,"<div class=\"t2sFactValue\"><span class=\"t2sFactName\">Runtime:</span> <span class=\"t2sFactNumber\">3 h 48</span></div>");
f_contains("the title linked to its page",$strhtml,"<div class=\"t2sFactLabel\"><a href=\"movie.php?movieid=947\">Lawrence of Arabia</a></div>");
f_contains("the poster, out of the keyboard order",$strhtml,"<a class=\"t2sFactMedia\" href=\"movie.php?movieid=947\" tabindex=\"-1\" aria-hidden=\"true\"><img src=\"https://img/w342/AiAm0EtDvyGqNpVoieRw4u65vD1.jpg\"");
f_contains("the year of the entity",$strhtml,"<div class=\"t2sFactYear\">1962</div>");
f_check("no homonyms, no list",strpos($strhtml,"t2sFactOthers"),false);
$strhtml=f_t2srep_render("fact-card",$arrvertigo,"en",$fnfixposter);
f_contains("a release date in words",$strhtml,"<span class=\"t2sFactName\">Release date:</span> <span class=\"t2sFactNumber\">28 May 1958</span>");
f_check("no year line when the fact is the date",strpos($strhtml,"t2sFactYear"),false);
f_contains("the homonyms counted",$strhtml,"<p class=\"t2sFactOthersTitle\">11 other titles with this name</p>");
f_check("the homonyms listed",substr_count($strhtml,"<li>"),11);
f_contains("a homonym linked, with its year",$strhtml,"<li><a href=\"movie.php?movieid=922902\">Vertigo</a> <span class=\"t2sFactOthersMeta\">2016</span></li>");
f_check("the fact itself is not among the homonyms",strpos($strhtml,"<li><a href=\"movie.php?movieid=426\""),false);
$strhtml=f_t2srep_render("fact-card",$arrfix["lotb-fact-vertigo-date-fr"],"fr",$fnfixposter);
f_contains("a release date in French",$strhtml,"<span class=\"t2sFactName\">Date de sortie :</span> <span class=\"t2sFactNumber\">28 mai 1958</span>");
$strhtml=f_t2srep_render("fact-card",$arrfix["lotb-fact-birth-year-en"],"en",$fnfixprofile);
f_contains("born",$strhtml,"<span class=\"t2sFactName\">Born:</span> <span class=\"t2sFactNumber\">1910</span>");
f_contains("a face",$strhtml,"https://img/w185/ca8fRqLDfTJTCamLKHp70up23ET.jpg");
f_contains("born, fr",f_t2srep_render("fact-card",$arrfix["lotb-fact-birth-year-en"],"fr"),"<span class=\"t2sFactName\">Naissance :</span>");
$strhtml=f_t2srep_render("fact-card",$arrchernobyl,"en");
f_contains("one homonym, singular, with its value",$strhtml,"<p class=\"t2sFactOthersTitle\">1 other title with this name</p><ul class=\"t2sFactOthersList\"><li><a href=\"serie.php?serieid=196108\">Chernobyl</a> <span class=\"t2sFactOthersMeta\">2022 · 12</span></li></ul>");
f_contains("a count of episodes",$strhtml,"<span class=\"t2sFactName\">Number of episodes:</span> <span class=\"t2sFactNumber\">5</span>");
f_contains("revenue in dollars",f_t2srep_render("fact-card",$arrfix["lotb-fact-revenue-en"],"en"),"<span class=\"t2sFactNumber\">$2,923,706,026</span>");
f_contains("seasons, fr",f_t2srep_render("fact-card",$arrfix["lotb-fact-seasons-fr"],"fr"),"<span class=\"t2sFactName\">Nombre de saisons :</span> <span class=\"t2sFactNumber\">5</span>");
f_check("others: plural fr",f_t2srep_others_words(11,array(),array(array("name"=>"ID_MOVIE","role"=>"entity_id")),"fr"),"11 autres titres portent ce nom");
f_check("others: singular fr",f_t2srep_others_words(1,array(),array(array("name"=>"ID_MOVIE","role"=>"entity_id")),"fr"),"1 autre titre porte ce nom");
f_check("others: people",f_t2srep_others_words(2,array(),array(array("name"=>"ID_PERSON","role"=>"entity_id")),"en"),"2 other people with this name");
f_check("others: one person, fr",f_t2srep_others_words(1,array(),array(array("name"=>"ID_PERSON","role"=>"entity_id")),"fr"),"1 autre personne porte ce nom");
$arrnofact=$arrlawrence;
unset($arrnofact["summary_facts"]["fact"]);
f_contains("no fact (an older answer): the focus column of the first row",f_t2srep_render("fact-card",$arrnofact,"en"),"<span class=\"t2sFactNumber\">3 h 48</span>");
f_check("no row: nothing",f_t2srep_render_fact_card(array(),array(),"en"),"");

f_group("Phase 2 lot B: verdict rendering");
$strhtml=f_t2srep_render("verdict",$arrjaws,"en",$fnfixposter);
f_check("Yes, large, a word and a sign",$strhtml,"<div class=\"t2sVerdict t2sVerdictYes\" role=\"group\" aria-label=\"Answer: Yes\"><span class=\"t2sVerdictIcon\" aria-hidden=\"true\">✓</span><div class=\"t2sVerdictText\"><div class=\"t2sVerdictWord\">Yes</div><div class=\"t2sVerdictDetail\">1 matching result</div></div></div>");
f_contains("Oui",f_t2srep_render("verdict",$arrfix["lotb-verdict-yes-two-rows-fr"],"fr"),"<div class=\"t2sVerdictWord\">Oui</div><div class=\"t2sVerdictDetail\">2 résultats correspondants</div>");
$strhtml=f_t2srep_render("verdict",$arrempty,"en");
f_check("an empty result: No and nothing in the database, nothing below",$strhtml,
	"<div class=\"t2sVerdict t2sVerdictNo\" role=\"group\" aria-label=\"Answer: No\"><span class=\"t2sVerdictIcon\" aria-hidden=\"true\">✗</span><div class=\"t2sVerdictText\"><div class=\"t2sVerdictWord\">No</div><div class=\"t2sVerdictDetail\">Nothing in the database matches</div></div></div>");
f_contains("an empty result, fr",f_t2srep_render("verdict",$arrfix["lotb-verdict-no-empty-fr"],"fr"),"<div class=\"t2sVerdictWord\">Non</div><div class=\"t2sVerdictDetail\">Rien dans la base ne correspond</div>");
$strhtml=f_t2srep_render("verdict",$arrcountzero,"en");
f_contains("a count of zero: No",$strhtml,"<div class=\"t2sVerdict t2sVerdictNo\" role=\"group\" aria-label=\"Answer: No\">");
f_contains("a count: the number card below",$strhtml,"</div></div><div class=\"t2sNumberCardWrap\"><div class=\"t2sNumberCard\"><div class=\"t2sNumberValue\">0</div>");
f_check("a count: no detail line, the number says it",strpos($strhtml,"t2sVerdictDetail"),false);
f_contains("Réponse : Non",f_t2srep_render("verdict",$arrfix["lotb-verdict-count-zero-fr"],"fr"),"aria-label=\"Réponse : Non\"");
f_contains("no verdict in the facts: the table",f_t2srep_render_verdict(f_t2srep_records($arrjaws["result"]),f_t2srep_columns($arrjaws),"en",array(),"entity-page"),"t2sTable");

f_group("Phase 2 lot B: captions (contract, Spoken rows)");
f_check("compare, en",f_t2srep_caption($arrfix["lotb-compare-yes-no-runtime-en"],"en"),"Two titles side by side; Titanic leads on runtime, 3 h 14.");
f_check("compare, fr",f_t2srep_caption($arrfix["lotb-compare-yes-no-runtime-fr"],"fr"),"Deux titres côte à côte ; Titanic l'emporte en durée, 3 h 14.");
f_check("compare three with a homonym",f_t2srep_caption($arrgodfather,"en"),"Three titles side by side; The Godfather Part II leads on runtime, 3 h 22.");
f_check("compare revenue, fr",f_t2srep_caption($arrfix["lotb-compare-three-revenue-en"],"fr"),"Trois titres côte à côte ; Jurassic Park l'emporte en box-office, $1,058,454,230.");
f_check("compare two quantities",f_t2srep_caption($arrtwofocus,"en"),"Two titles side by side; Avatar leads on budget, $237,000,000; Avatar leads on revenue, $2,923,706,026.");
f_check("compare a date, earliest",f_t2srep_caption($arrfix["lotb-compare-born-first-en"],"en"),"Two people side by side; Charlie Chaplin comes first (1889).");
f_check("compare a date, earliest, fr",f_t2srep_caption($arrfix["lotb-compare-born-before-fr"],"fr"),"Deux personnes côte à côte ; Alfred Hitchcock vient en premier (1899).");
f_check("compare a tie",f_t2srep_caption($arrtie,"en"),"Two titles side by side; same number of seasons, 5.");
f_check("compare a tie, fr",f_t2srep_caption($arrtie,"fr"),"Deux titres côte à côte ; même nombre de saisons, 5.");
$arrlowest=$arrfix["lotb-compare-yes-no-runtime-en"];
$arrlowest["summary_facts"]["winners"][0]=array("column"=>"RUNTIME","unit"=>"minutes","role"=>"metric","index"=>1,"id"=>19995,"label"=>"Avatar","value"=>162,"extreme"=>"min","tie"=>false);
f_check("compare, the minimum",f_t2srep_caption($arrlowest,"en"),"Two titles side by side; Avatar has the lowest runtime, 2 h 42.");
f_check("compare, the minimum, fr",f_t2srep_caption($arrlowest,"fr"),"Deux titres côte à côte ; Avatar a la durée la plus basse, 2 h 42.");
$arrnodirection=$arrfix["lotb-compare-born-first-en"];
$arrnodirection["summary_facts"]["winners"][0]["index"]=null;
f_check("compare a date without direction: no winner said",f_t2srep_caption($arrnodirection,"en"),"Two people side by side.");
f_check("fact, en",f_t2srep_caption($arrlawrence,"en"),"Lawrence of Arabia: runtime, 3 h 48.");
f_check("fact, fr",f_t2srep_caption($arrfix["lotb-fact-seasons-fr"],"fr"),"Breaking Bad : nombre de saisons, 5.");
f_check("fact with homonyms",f_t2srep_caption($arrvertigo,"en"),"Vertigo: release date, 28 May 1958 (11 other titles with this name).");
f_check("fact with homonyms, fr",f_t2srep_caption($arrvertigo,"fr"),"Vertigo : date de sortie, 28 mai 1958 (11 autres titres portent ce nom).");
f_check("fact with one homonym",f_t2srep_caption($arrchernobyl,"en"),"Chernobyl: number of episodes, 5 (1 other title with this name).");
f_check("fact, a date in French",f_t2srep_caption($arrfix["lotb-fact-vertigo-date-fr"],"fr"),"Sueurs froides : date de sortie, 28 mai 1958.");
f_check("fact, a birth year",f_t2srep_caption($arrfix["lotb-fact-birth-year-en"],"en"),"Akira Kurosawa: birth year, 1910.");
f_check("fact, revenue",f_t2srep_caption($arrfix["lotb-fact-revenue-en"],"en"),"Avatar: revenue, $2,923,706,026.");
f_check("verdict, one row",f_t2srep_caption($arrjaws,"en"),"Yes: 1 matching result.");
f_check("verdict, one row, fr",f_t2srep_caption($arrfix["lotb-verdict-yes-fr"],"fr"),"Oui : 1 résultat correspondant.");
f_check("verdict, two rows",f_t2srep_caption($arrkubrick,"en"),"Yes: 2 matching results.");
f_check("verdict, two rows, fr",f_t2srep_caption($arrfix["lotb-verdict-yes-two-rows-fr"],"fr"),"Oui : 2 résultats correspondants.");
f_check("verdict, empty",f_t2srep_caption($arrempty,"en"),"No: nothing in the database matches.");
f_check("verdict, empty, fr",f_t2srep_caption($arrfix["lotb-verdict-no-empty-fr"],"fr"),"Non : rien dans la base ne correspond.");
f_check("verdict, a count of zero",f_t2srep_caption($arrcountzero,"en"),"No: 0.");
f_check("verdict, a count of zero, fr",f_t2srep_caption($arrfix["lotb-verdict-count-zero-fr"],"fr"),"Non : 0.");
$arrcountyes=$arrcountzero;
$arrcountyes["summary_facts"]["verdict"]="yes";
$arrcountyes["result"][0]["data"]["AWARD_WIN_COUNT"]=3;
f_check("verdict, a count above zero",f_t2srep_caption($arrcountyes,"en"),"Yes: 3.");
f_check("verdict, a count above zero, fr",f_t2srep_caption($arrcountyes,"fr"),"Oui : 3.");
$strallb="";
foreach ($arrfix as $strkey=>$arrjson) {
	if (strpos($strkey,"lotb-")===0) {
		$strallb.=f_t2srep_caption($arrjson,"en").f_t2srep_caption($arrjson,"fr");
		foreach (f_t2srep_options($arrjson) as $stroption) {
			$strallb.=f_t2srep_render($stroption,$arrjson,"en",$fnfixposter).f_t2srep_render($stroption,$arrjson,"fr",$fnfixposter);
		}
	}
}
f_check("a verdict never says false",preg_match('/\b(false|faux|fausse)\b/i',$strallb),0);
f_check("no em dash in any lot B caption or rendering",strpos($strallb,"\u{2014}"),false);

f_group("Table: entity titles link to their page");
f_check("label column to its id",f_t2srep_label_links(array("ID_MOVIE","MOVIE_TITLE","RUNTIME"),array(array("name"=>"ID_MOVIE","role"=>"entity_id"),array("name"=>"MOVIE_TITLE","role"=>"label"))),
	array("MOVIE_TITLE"=>"ID_MOVIE"));
f_check("two entities, each label to its own id",f_t2srep_label_links(array("ID_PERSON","PERSON_NAME","ID_MOVIE","MOVIE_TITLE","CHARACTER"),array()),
	array("PERSON_NAME"=>"ID_PERSON","MOVIE_TITLE"=>"ID_MOVIE"));
f_check("T2S collection by name",f_t2srep_label_links(array("ID_T2S_COLLECTION","COLLECTION_NAME"),array()),array("COLLECTION_NAME"=>"ID_T2S_COLLECTION"));
f_check("a label not named after its entity, to the main id",f_t2srep_label_links(array("ID_AWARD","DESCRIPTION"),array(array("name"=>"ID_AWARD","role"=>"entity_id"),array("name"=>"DESCRIPTION","role"=>"label"))),
	array("DESCRIPTION"=>"ID_AWARD"));
f_check("a label named after another entity is not linked",f_t2srep_label_links(array("ID_MOVIE","PERSON_NAME"),array(array("name"=>"ID_MOVIE","role"=>"entity_id"),array("name"=>"PERSON_NAME","role"=>"label"))),
	array());
f_check("no id, no link",f_t2srep_label_links(array("GENRE_NAME","MOVIE_COUNT"),array(array("name"=>"GENRE_NAME","role"=>"label"))),array());
f_check("href of a topic",f_t2srep_entity_href_for(array("ID_TOPIC"=>12),"ID_TOPIC"),"t2stopic.php?topicid=12");
f_check("href of a T2S list",f_t2srep_entity_href_for(array("ID_T2S_LIST"=>3),"ID_T2S_LIST"),"t2slist.php?listid=3");
f_check("href of a T2S collection (as the cards)",f_t2srep_entity_href(array("ID_T2S_COLLECTION"=>7,"COLLECTION_NAME"=>"x")),"t2scollection.php?collectionid=7");
f_check("href of a series in the union",f_t2srep_entity_href_for(array("ID_CONTENT"=>1399,"CONTENT_TYPE"=>"serie"),"ID_CONTENT"),"serie.php?serieid=1399");
f_check("href of a type the site has no page for",f_t2srep_entity_href_for(array("ID_IMDB"=>"tt1"),"ID_IMDB"),"");
f_check("href of a blank id",f_t2srep_entity_href_for(array("ID_MOVIE"=>""),"ID_MOVIE"),"");
$strhtml=f_t2srep_render("table",$arrhp,"en");
f_contains("table title linked (mixed aliases)",$strhtml,"<td><a href=\"movie.php?movieid=671\">Harry Potter and the Philosopher&#039;s Stone</a></td>");
f_check("the id cell itself is not linked",strpos($strhtml,"<td><a href=\"movie.php?movieid=671\">671</a></td>"),false);
$strhtml=f_t2srep_render_table(array(array("ID_PERSON"=>31,"PERSON_NAME"=>"Tom Hanks","ID_MOVIE"=>13,"MOVIE_TITLE"=>"Forrest <Gump>")),array(),"en");
f_contains("person linked",$strhtml,"<td><a href=\"person.php?personid=31\">Tom Hanks</a></td>");
f_contains("movie linked and escaped",$strhtml,"<td><a href=\"movie.php?movieid=13\">Forrest &lt;Gump&gt;</a></td>");
f_contains("id escaped in the href",f_t2srep_render_table(array(array("ID_MOVIE"=>"1\"><script>","MOVIE_TITLE"=>"x")),array(),"en"),"movie.php?movieid=1%22%3E%3Cscript%3E");
f_check("a grouped table with no id has no link",strpos(f_t2srep_render("table",$arrgenres),"<a "),false);

f_group("Phase 2 lot C: histogram");
$arrhr=$arrfix['lotc-histogram-runtime-range-en'];
f_check("histogram is drawn here",f_t2srep_choose($arrhr),"histogram");
f_check("histogram chips",f_t2srep_options($arrhr),array("histogram","table"));
$arrhm=f_t2srep_histogram_model($arrhr['summary_facts'],"en");
$arrbars=array();
foreach ($arrhm['bars'] as $arrbar) {
	$arrbars[]=array($arrbar['axis'],$arrbar['range'],$arrbar['value'],$arrbar['empty'],$arrbar['peak']);
}
f_check("histogram bars in bin order, the empty bin kept",$arrbars,array(
	array("0","0 to 29 min",3.0,false,false),array("30","30 to 59 min",0.0,true,false),array("60","60 to 89 min",4.0,false,false),
	array("90","90 to 119 min",2.0,false,false),array("120","120 to 149 min",5.0,false,true),array("150","150 to 179 min",2.0,false,false),
	array("180","180 to 209 min",2.0,false,false)));
f_check("histogram caption en",f_t2srep_caption($arrhr,"en"),"18 films in bins of 30 min; the most common bin is 120 to 149 min, 5 films.");
f_check("histogram caption fr",f_t2srep_caption($arrfix['lotc-histogram-runtime-range-fr'],"fr"),
	"18 films, par tranches de 30 min ; la tranche la plus fréquente : 120 à 149 min, 5 films.");
f_check("histogram caption, width without an end",f_t2srep_caption($arrfix['lotc-histogram-bond-runtime-en'],"en"),
	"27 films in bins of 10 min; the most common bin is 130 to 139 min, 8 films.");
f_check("histogram caption, money",f_t2srep_caption($arrfix['lotc-histogram-budget-en'],"en"),
	"31 films in bins of $50M; the most common bin is $0 to $50M, 16 films.");
f_check("histogram caption, no unit",f_t2srep_caption($arrfix['lotc-histogram-rating-band-en'],"en"),
	"14,292 films in 10 bins; the most common bin is 6, 4,133 films.");
f_check("histogram caption, series",f_t2srep_caption($arrfix['lotc-histogram-seasons-en'],"en"),
	"375 series in 57 bins; the most common bin is 1, 219 series.");
f_check("histogram axis name en",f_t2srep_histogram_axis_name($arrhm,"en"),"Runtime (min), bins of 30 min");
f_check("histogram axis name fr",f_t2srep_histogram_axis_name(f_t2srep_histogram_model($arrfix['lotc-histogram-runtime-range-fr']['summary_facts'],"fr"),"fr"),
	"Durée (min), tranches de 30 min");
f_check("histogram axis name money",f_t2srep_histogram_axis_name(f_t2srep_histogram_model($arrfix['lotc-histogram-budget-en']['summary_facts'],"en"),"en"),
	"Budget ($), bins of $50M");
$strhtml=f_t2srep_render("histogram",$arrhr,"en");
f_contains("histogram column named by range and count",$strhtml,"<span class=\"t2sSrOnly\">120 to 149 min: 5</span>");
f_contains("histogram peak marked",$strhtml,"t2sHistoCol t2sHistoPeak");
f_contains("histogram empty bin kept",$strhtml,"t2sHistoCol t2sHistoEmpty");
f_contains("histogram axis title",$strhtml,"<figcaption class=\"t2sHistoAxis\">Runtime (min), bins of 30 min</figcaption>");
$strhtml=f_t2srep_render("histogram",$arrfix['lotc-histogram-seasons-en'],"en");
f_contains("a long histogram is dense and scrolls in its box",$strhtml,"t2sHisto t2sHistoDense");
f_check("text bin",f_t2srep_bin_range(array("label"=>"under 90"),array("unit"=>"minutes"),"en"),"under 90");
f_check("step of one",f_t2srep_bin_range(array("low"=>3,"high"=>null),array("unit"=>"count","width"=>1),"fr"),"3");
f_check("no histogram facts, a table",strpos(f_t2srep_render_histogram(array(array("A"=>1)),array(),"en",array()),"t2sTable")!==false,true);

f_group("Phase 2 lot C: share-bar");
$arrst=$arrfix['lotc-share-hbo-type-en'];
f_check("share-bar is drawn here",f_t2srep_choose($arrst),"share-bar");
f_check("share-bar chips",f_t2srep_options($arrst),array("share-bar","ranked-bars","table"));
f_check("share caption en",f_t2srep_caption($arrst,"en"),"375 series counted in six parts: Scripted 49%, Miniseries 38%, Documentary 8% and 3 more.");
f_check("share caption fr",f_t2srep_caption($arrfix['lotc-share-hbo-type-fr'],"fr"),
	"375 séries comptées en six parts : Scripted 49 %, Miniseries 38 %, Documentary 8 % et 3 autres.");
f_check("share caption as a pie",f_t2srep_caption($arrfix['lotc-share-pie-status-en'],"en"),
	"375 series counted in five parts: Ended 74%, Canceled 12%, Returning Series 11% and 2 more.");
f_check("share caption gender codes",f_t2srep_caption($arrfix['lotc-share-gender-codes-en'],"en"),"66 people counted in three parts: women 11%, men 88%, non-binary 2%.");
f_check("share caption CASE labels fr",f_t2srep_caption($arrfix['lotc-share-gender-labels-fr'],"fr"),"65 résultats comptés en deux parts : Femmes 11 %, Hommes 89 %.");
f_check("one share en",f_t2srep_caption($arrfix['lotc-share-single-en'],"en"),"74% of the whole; the rest, 26%.");
f_check("one share fr",f_t2srep_caption($arrfix['lotc-share-single-percent-fr'],"fr"),"74 % de l'ensemble ; le reste, 26 %.");
f_check("one share and its rest add up",f_t2srep_caption($arrfix['lotc-share-single-percent-en'],"en"),"38% of the whole; the rest, 62%.");
f_check("one share has chips (bar and table)",f_t2srep_options($arrfix['lotc-share-single-en']),array("share-bar","number-card","table"));
$arrgm=f_t2srep_share_model($arrfix['lotc-share-gender-codes-en'],"fr");
f_check("gender codes named fr",array($arrgm['segments'][0]['label'],$arrgm['segments'][1]['label'],$arrgm['segments'][2]['label']),array("femmes","hommes","non binaire"));
f_check("percent below one",array(f_t2srep_percent(0.004,"en"),f_t2srep_percent(0.004,"fr"),f_t2srep_percent(0,"en")),array("<1%","<1 %","0%"));
$strhtml=f_t2srep_render("share-bar",$arrst,"en");
f_contains("share legend: label, count, percentage",$strhtml,">Scripted</a></span><span class=\"t2sShareNumbers\">182 · 49%</span>");
f_contains("share bar hidden from screen readers (the legend says it)",$strhtml,"<div class=\"t2sShareBar\" aria-hidden=\"true\">");
f_contains("a wide segment shows its percentage",$strhtml,"title=\"Scripted 49%\">49%</a>");
$strhtml=f_t2srep_render("share-bar",$arrfix['lotc-share-single-en'],"en");
f_contains("one share: the rest striped",$strhtml,"data-hue=\"rest\"");
f_contains("one share: the rest in the legend",$strhtml,"<span class=\"t2sShareName\">The rest</span><span class=\"t2sShareNumbers\">26%</span>");

f_group("Drill-down: the follow-up a bucket asks");
//Word for word the follow-ups of voice-agent (tools/check-result-representations.mjs, "drill"):
//chosen on the live API (2026-11-12), each returned the entities of the bucket as a card grid.
$arrseasons=$arrfix['lotc-histogram-seasons-en'];
$strfollow="Among the items counted in \"How are HBO series distributed by number of seasons?\", list those whose number of seasons is 3.";
$strhtml=f_t2srep_render("histogram",$arrseasons,"en");
f_contains("histogram bin links to its follow-up",$strhtml,"href=\"".f_t2srep_h(f_t2srep_drill_href($strfollow))."\"");
f_contains("histogram bin link named by its count and its question",$strhtml,"aria-label=\"3: 33. ".f_t2srep_h($strfollow)."\"");
f_check("follow-up link is relative to the search page (prefix and private mode kept)",substr(f_t2srep_drill_href($strfollow),0,17),"index.php?search=");
$strbond=f_t2srep_render("histogram",$arrfix['lotc-histogram-bond-runtime-en'],"en");
f_check("empty bins are no links",substr_count($strbond,"t2sHistoEmpty\" title=\"10 to 19 min: 0\"><span class=\"t2sSrOnly\">"),1);
$arrkubrick=$arrfix['lotc-histogram-runtime-range-fr'];
f_check("histogram range fr",f_t2srep_drill_question($arrkubrick['question'],f_t2srep_drill_word($arrkubrick['summary_facts']['histogram']['column'],"fr"),
	f_t2srep_drill_bin_value($arrkubrick['summary_facts']['histogram']['bins'][2],$arrkubrick['summary_facts']['histogram'],"fr"),"fr"),
	"Parmi les éléments comptés dans « Combien de films de Stanley Kubrick y a-t-il dans chaque tranche de durée de 30 minutes ? », liste ceux dont la durée est entre 60 et 89 minutes.");
f_check("bin values",array(
	f_t2srep_drill_bin_value(array("low"=>0,"high"=>null,"value"=>1),array("unit"=>"minutes","width"=>10),"en"),
	f_t2srep_drill_bin_value(array("low"=>50000000,"high"=>99999999,"value"=>1),array("unit"=>"usd","width"=>50000000),"en"),
	f_t2srep_drill_bin_value(array("low"=>3.8,"high"=>null,"value"=>1),array("unit"=>null,"width"=>0.1),"fr"),
	f_t2srep_drill_bin_value(array("low"=>3,"high"=>null,"value"=>4),array("unit"=>"count","width"=>1),"en"),
	f_t2srep_drill_bin_value(array("low"=>null,"label"=>"under 90","value"=>2),array("unit"=>"minutes"),"en"),
	f_t2srep_drill_bin_value(array("low"=>30,"high"=>null,"value"=>0,"empty"=>true),array("unit"=>"minutes","width"=>30),"en")),
	array("between 0 and 9 minutes","between \$50M and \$100M","entre 3.8 et 3.9","3","under 90",""));
$arrpieen=$arrfix['lotc-share-pie-status-en'];
$arrpieenmodel=f_t2srep_share_model($arrpieen,"en");
f_check("share part en",f_t2srep_drill_share_question($arrpieen,$arrpieenmodel['segments'][1],"en"),
	"Among the items counted in \"Show the split of the HBO series by status as a pie\", list those whose status is Canceled.");
$arrpiefr=$arrfix['lotc-share-pie-status-fr'];
$arrpiefrmodel=f_t2srep_share_model($arrpiefr,"fr");
f_check("share part fr",f_t2srep_drill_share_question($arrpiefr,$arrpiefrmodel['segments'][1],"fr"),
	"Parmi les éléments comptés dans « Montre la répartition des séries HBO par statut en camembert », liste ceux dont le statut est Canceled.");
$arrsinglemodel=f_t2srep_share_model($arrfix['lotc-share-single-en'],"en");
f_check("one share and its rest ask nothing",array(f_t2srep_drill_share_question($arrfix['lotc-share-single-en'],$arrsinglemodel['segments'][0],"en"),
	f_t2srep_drill_share_question($arrfix['lotc-share-single-en'],$arrsinglemodel['segments'][1],"en")),array("",""));
f_check("one share alone has no link",strpos(f_t2srep_render("share-bar",$arrfix['lotc-share-single-en'],"en"),"t2sDrill"),false);
$arrperyear=$arrfix['lota-trend-films-per-year-fr'];
$strhtml=f_t2srep_render("trend-columns",$arrperyear,"fr");
f_contains("trend column links to its follow-up fr",$strhtml,"href=\"".f_t2srep_h(f_t2srep_drill_href(
	"Parmi les éléments comptés dans « Combien de films sont sortis chaque année depuis 2000 ? », liste ceux dont l'année de sortie est 2000."))."\"");
$arrdecades=$arrfix['lota-trend-woody-allen-decades-en'];
f_contains("trend decade en",f_t2srep_render("trend-columns",$arrdecades,"en"),f_t2srep_h("Among the items counted in \"How many films did Woody Allen direct per decade?\", list those whose decade is the 1980s."));
f_contains("trend decade fr",f_t2srep_render("trend-columns",$arrfix['lota-trend-woody-allen-decades-fr'],"fr"),
	f_t2srep_h("Parmi les éléments comptés dans « Combien de films Woody Allen a-t-il réalisés par décennie ? », liste ceux dont la décennie est les années 1980."));
f_check("no question, no follow-up",f_t2srep_drill_question("  ",array("label"=>"status","gender"=>"m"),"Ended","en"),"");
$arrnoquestion=$arrseasons;
unset($arrnoquestion['question']);
f_check("an answer without its question draws no link",strpos(f_t2srep_render("histogram",$arrnoquestion,"en"),"t2sDrill"),false);
f_check("drill words",array(f_t2srep_drill_word("RUNTIME_BUCKET","fr"),f_t2srep_drill_word("NUMBER_OF_EPISODES","fr"),f_t2srep_drill_word("ORIGIN_COUNTRY","en")),
	array(array("label"=>"durée","gender"=>"f"),array("label"=>"nombre d'épisodes","gender"=>"m"),array("label"=>"origin country","gender"=>"m")));

f_group("Pie on request: summary_facts.share.shape");
$arrpie=$arrpieen;
$arrpie['summary_facts']['share']['shape']="pie";
$strhtml=f_t2srep_render("share-bar",$arrpie,"en");
f_contains("a pie when asked",$strhtml,"<div class=\"t2sShare t2sSharePie\"><svg class=\"t2sPie\" viewBox=\"0 0 42 42\" aria-hidden=\"true\" focusable=\"false\">");
f_check("one arc per part",substr_count($strhtml,"class=\"t2sPieSeg\""),count($arrpieenmodel['segments']));
f_contains("the first arc from the top",$strhtml,"data-hue=\"0\" cx=\"21\" cy=\"21\" r=\"15.91549\" stroke-dasharray=\"73.27 26.73\" stroke-dashoffset=\"25\"");
f_contains("the second arc after the first",$strhtml,"stroke-dashoffset=\"-48.87\"");
f_contains("an arc links to the follow-up",$strhtml,"<a class=\"t2sPieLink\" href=\"".f_t2srep_h(f_t2srep_drill_href(f_t2srep_drill_share_question($arrpieen,$arrpieenmodel['segments'][1],"en")))."\" tabindex=\"-1\">");
f_contains("the legend of the bar stays",$strhtml,"<ul class=\"t2sShareLegend\">");
f_check("no bar beside the pie",strpos($strhtml,"t2sShareBar"),false);
$arrpie['summary_facts']['share']['shape']="bar";
f_contains("shape bar: the bar",f_t2srep_render("share-bar",$arrpie,"en"),"<div class=\"t2sShareBar\" aria-hidden=\"true\">");
$arrpie['summary_facts']['share']['shape']="treemap";
f_contains("an unknown shape: the bar",f_t2srep_render("share-bar",$arrpie,"en"),"<div class=\"t2sShareBar\" aria-hidden=\"true\">");
f_contains("no shape (an older API): the bar",f_t2srep_render("share-bar",$arrpieen,"en"),"<div class=\"t2sShareBar\" aria-hidden=\"true\">");
$arrsinglepie=$arrfix['lotc-share-single-en'];
$arrsinglepie['summary_facts']['share']['shape']="pie";
$strhtml=f_t2srep_render("share-bar",$arrsinglepie,"en");
f_check("one share as a pie: two arcs, no link",array(substr_count($strhtml,"class=\"t2sPieSeg\""),strpos($strhtml,"t2sPieLink")),array(2,false));
f_contains("one share as a pie: the rest arc",$strhtml,"data-hue=\"rest\" cx=\"21\"");

f_group("Phase 2 lot C: flag-list");
$arrfk=$arrfix['lotc-flags-codes-kurosawa-en'];
f_check("flag-list is drawn here",f_t2srep_choose($arrfk),"flag-list");
f_check("flag-list chips (codes)",f_t2srep_options($arrfk),array("flag-list","ranked-bars","table"));
f_check("flag-list chips (locations)",f_t2srep_options($arrfix['lotc-flags-filmed-mad-max-en']),array("flag-list","card-grid","table"));
f_check("flag emoji",array(bin2hex(f_t2srep_flag_emoji("FR")),f_t2srep_flag_emoji("jp")==="\u{1F1EF}\u{1F1F5}",f_t2srep_flag_emoji("1A"),f_t2srep_flag_emoji("")),
	array("f09f87abf09f87b7",true,"",""));
f_check("flag caption count en",f_t2srep_caption($arrfk,"en"),"The first 13 countries by number of films; Japan leads with 93 films.");
f_check("flag caption count fr",f_t2srep_caption($arrfix['lotc-flags-codes-kurosawa-fr'],"fr"),"Les 13 premiers pays par nombre de films ; Japon arrive en tête avec 93 films.");
f_check("flag caption names en",f_t2srep_caption($arrfix['lotc-flags-filmed-mad-max-en'],"en"),"Four countries: Angola, Australia, Namibia and South Africa.");
f_check("flag caption names fr",f_t2srep_caption($arrfix['lotc-flags-filmed-mad-max-fr'],"fr"),"Quatre pays : Angola, Australie, Namibie et Afrique du Sud.");
f_check("flag caption repeated film",f_t2srep_caption($arrfix['lotc-flags-repeated-lotr-en'],"en"),"Two countries: New Zealand and United States.");
f_check("flag caption more pages",f_t2srep_caption($arrfix['lotc-flags-filmed-mad-max-en'],"en",true),"The first four countries: Angola, Australia, Namibia and South Africa.");
$arrfm=f_t2srep_flag_model($arrfix['lotc-flags-filmed-england-en'],"fr");
$arrnames=array();
foreach ($arrfm['items'] as $arritem) {
	$arrnames[]=$arritem['code']." ".$arritem['name'];
}
f_check("place names: England kept, the others in French",$arrnames,array("GB England","JO Jordanie","MA Maroc","ES Espagne","GB Royaume-Uni"));
$arrnoflag=array();
foreach (f_t2srep_flag_model($arrfix['lotc-flags-codes-no-flag-en'],"en")['items'] as $arritem) {
	if ($arritem['flag']==="") {
		$arrnoflag[]=$arritem['code']." ".$arritem['name'];
	}
}
f_check("codes with no flag",$arrnoflag,array("YU Yugoslavia","SU Soviet Union","XC Czechoslovakia","XK Kosovo"));
$strhtml=f_t2srep_render("flag-list",$arrfk,"en");
f_contains("flag row: badge, emoji, name, bar, count",$strhtml,"<li class=\"t2sFlagRow t2sFlagRowValue\"><span class=\"t2sFlag\" aria-hidden=\"true\"><span class=\"t2sFlagEmoji\">\u{1F1EF}\u{1F1F5}</span><span class=\"t2sFlagCode\">JP</span></span><span class=\"t2sFlagName\">Japan</span><span class=\"t2sFlagBody\">");
f_contains("flag bar at 100% for the leader",$strhtml,"style=\"width:100%\"></span></span><span class=\"t2sFlagValue\">93</span>");
$strhtml=f_t2srep_render("flag-list",$arrfix['lotc-flags-codes-no-flag-en'],"en");
f_contains("a code with no flag keeps its badge",$strhtml,"<span class=\"t2sFlagCode t2sFlagAlways\">SU</span>");
$strhtml=f_t2srep_render("flag-list",$arrfix['lotc-flags-filmed-mad-max-en'],"en");
f_contains("a location row links to its page",$strhtml,"<span class=\"t2sFlagName\"><a href=\"");
f_check("no bar without a count",strpos($strhtml,"t2sFlagBody"),false);
f_nodecheck("the flag detection script",f_t2srep_flags_js());

$strallc="";
foreach ($arrfix as $strkey=>$arrjson) {
	if (strpos($strkey,"lotc-")===0) {
		$strallc.=f_t2srep_caption($arrjson,"en").f_t2srep_caption($arrjson,"fr");
		foreach (f_t2srep_options($arrjson) as $stroption) {
			//No poster callback: the rows of lot C are countries, bins and parts, not films
			$strallc.=f_t2srep_render($stroption,$arrjson,"en").f_t2srep_render($stroption,$arrjson,"fr");
		}
	}
}
f_check("no em dash in any lot C caption or rendering",strpos($strallc,"\u{2014}"),false);
f_check("every lot C fixture has a caption in both languages",array_sum(array_map(function($k) use ($arrfix) {
	return (strpos($k,"lotc-")===0 && (f_t2srep_caption($arrfix[$k],"en")==="" || f_t2srep_caption($arrfix[$k],"fr")==="")) ? 1 : 0;
}, array_keys($arrfix))),0);

f_group("Phase 3 lot A: scatter");
f_check("scatter caption all plotted en",f_t2srep_caption($arrfix['p3a-scatter-kubrick-runtime-rating-en'],"en"),
	"18 films, runtime against IMDb rating; highest IMDb rating: Paths of Glory, 8.4.");
f_check("scatter caption all plotted fr",f_t2srep_caption($arrfix['p3a-scatter-kubrick-runtime-rating-fr'],"fr"),
	"18 films, note IMDb en fonction de la durée ; maximum de la note IMDb : Shining, 8.4.");
f_check("scatter caption left out en",f_t2srep_caption($arrfix['p3a-scatter-nolan-budget-revenue-en'],"en"),
	"18 of 31 films, budget against box-office revenue (13 without a budget or box-office revenue); highest box-office revenue: The Odyssey, $1.8B.");
f_check("scatter caption left out fr",f_t2srep_caption($arrfix['p3a-scatter-nolan-budget-revenue-fr'],"fr"),
	"18 films sur 31, recettes en fonction du budget (13 sans budget ou sans recettes) ; maximum des recettes : L'Odyssée, $1.8B.");
f_check("scatter caption one value missing",f_t2srep_caption($arrfix['p3a-scatter-pixar-budget-rating-en'],"en"),
	"34 of 50 films, budget against IMDb rating (16 without a budget); highest IMDb rating: WALL·E, 8.4.");
f_check("scatter caption year axis fr",f_t2srep_caption($arrfix['p3a-scatter-hitchcock-year-rating-fr'],"fr"),
	"49 films sur 50, note IMDb en fonction de l'année de sortie (1 sans note IMDb) ; maximum de la note IMDb : Rebecca, 8.0.");
f_check("scatter caption series",f_t2srep_caption($arrfix['p3a-scatter-hbo-seasons-rating-en'],"en"),
	"50 series, number of seasons against IMDb rating; highest IMDb rating: Band of Brothers, 9.4.");
f_check("scatter caption more pages en",f_t2srep_caption($arrfix['p3a-scatter-ghibli-runtime-rating-en'],"en",true),
	"The first 50 films, runtime against IMDb rating; highest IMDb rating: Spirited Away, 8.6.");
f_check("scatter caption more pages fr, left out",f_t2srep_caption($arrfix['p3a-scatter-hbo-seasons-rating-fr'],"fr",true),
	"49 des 50 premières séries, note IMDb en fonction du nombre de saisons (1 sans nombre de saisons) ; maximum de la note IMDb : The Franchise, 6.5.");
$fnaxes=function($strkey,$strlang) use ($arrfix) {
	$arrm=f_t2srep_scatter_model($arrfix[$strkey],$strlang);
	$fntexts=function($arrticks) { return array_map(function($t) { return $t['text']; },$arrticks); };
	return array($arrm['x']['title'],$fntexts($arrm['x']['ticks']),$arrm['y']['title'],$fntexts($arrm['y']['ticks']));
};
f_check("scatter axes money",$fnaxes('p3a-scatter-nolan-budget-revenue-en',"en"),
	array("Budget ($)",array("$0","$100M","$200M","$300M"),"Box-office revenue ($)",array("$0","$500M","$1B","$1.5B","$2B")));
f_check("scatter axes log votes",$fnaxes('p3a-scatter-tarantino-votes-rating-en',"en"),
	array("IMDb votes (log scale)",array("10","100","1K","10K","100K","1M","10M"),"IMDb rating",array("4","6","8","10")));
$arrtfr=$fnaxes('p3a-scatter-tarantino-votes-rating-fr',"fr");
f_check("scatter axes log votes fr",$arrtfr[0],"Votes IMDb (échelle log)");
f_check("scatter axes year, padded where a point sits on the edge",$fnaxes('p3a-scatter-hitchcock-year-rating-en',"en"),
	array("Release year",array("1910","1920","1930","1940","1950"),"IMDb rating",array("4","5","6","7","8")));
f_check("scatter axes minutes",$fnaxes('p3a-scatter-potter-runtime-revenue-en',"en"),
	array("Runtime (min)",array("120","130","140","150","160","170"),"Box-office revenue ($)",array("$600M","$800M","$1B","$1.2B","$1.4B")));
$arrsn=f_t2srep_scatter_model($arrfix['p3a-scatter-nolan-budget-revenue-en'],"en");
$arrhp=$arrsn['points'][$arrsn['highlight']];
f_check("scatter highest point",array($arrhp['label'],$arrhp['left'],$arrhp['top'],$arrhp['aria'],$arrhp['highest']),
	array("The Odyssey",83.33,10.19,"The Odyssey: budget $250M, box-office revenue $1.8B",true));
f_check("scatter points kept",count($arrsn['points']),18);
$intinside=true;
foreach ($arrfix as $strkey=>$arrjson) {
	if (strpos($strkey,"p3a-")===0) {
		$arrm=f_t2srep_scatter_model($arrjson,substr($strkey,-2));
		foreach ($arrm['points'] as $arrp) {
			$intinside=$intinside && $arrp['left']>=0 && $arrp['left']<=100 && $arrp['top']>=0 && $arrp['top']<=100;
		}
	}
}
f_check("scatter points inside the plot",$intinside,true);
f_check("scatter tick texts",array(f_t2srep_scatter_tick_text(1250000000,array("unit"=>"usd")),f_t2srep_scatter_tick_text(2500,array("unit"=>"count")),
	f_t2srep_scatter_tick_text(7.5,array("unit"=>"rating")),f_t2srep_scatter_tick_text(1958,array("unit"=>"year")),f_t2srep_scatter_tick_text(0,array("unit"=>"usd"))),
	array("$1.25B","2.5K","7.5","1958","$0"));
f_check("scatter votes value",f_t2srep_scatter_value_text(2482559,array("unit"=>"votes")),"2,482,559");
f_check("scatter chips",f_t2srep_options($arrfix['p3a-scatter-nolan-budget-revenue-en']),array("scatter","value-cards","card-grid","ranked-bars","table"));
f_check("no scatter facts, no model",f_t2srep_scatter_model($arrfix['lotc-share-hbo-type-en'],"en"),null);
$strhtml=f_t2srep_render("scatter",$arrfix['p3a-scatter-nolan-budget-revenue-en'],"en");
f_contains("scatter frame",$strhtml,"<figure class=\"t2sScatter\" role=\"group\" aria-label=\"Budget ($) / Box-office revenue ($)\">");
f_contains("scatter grid is a stretched svg",$strhtml,"<svg class=\"t2sScatterGrid\" viewBox=\"0 0 100 100\" preserveAspectRatio=\"none\" aria-hidden=\"true\">");
f_contains("scatter dot links to its page, highest ringed and selected",$strhtml,"class=\"t2sScatterDot isHighest isSelected\" style=\"left:83.33%;top:10.19%\" title=\"The Odyssey: budget $250M, box-office revenue $1.8B\"");
f_contains("scatter tick label placed by percent",$strhtml,"<span style=\"left:33.33%\">$100M</span>");
f_contains("scatter detail shows the highest point",$strhtml,"<span class=\"t2sScatterDetailValues\">Budget $250M · Box-office revenue $1.8B</span>");
f_contains("scatter hidden table of the points",$strhtml,"<div class=\"t2sSrOnly\"><table><caption>Budget ($) / Box-office revenue ($)</caption>");
f_check("scatter dots, one per point",substr_count($strhtml,"class=\"t2sScatterDot"),18);
f_contains("scatter dot links to the film page",$strhtml,"<a href=\"");
f_nodecheck("the scatter tap script",f_t2srep_scatter_js());
$strallp="";
foreach ($arrfix as $strkey=>$arrjson) {
	if (strpos($strkey,"p3a-")===0) {
		$strallp.=f_t2srep_caption($arrjson,"en").f_t2srep_caption($arrjson,"fr");
		foreach (f_t2srep_options($arrjson) as $stroption) {
			if (in_array($stroption,f_t2srep_module_reps(),true)) {
				$strallp.=f_t2srep_render($stroption,$arrjson,"en").f_t2srep_render($stroption,$arrjson,"fr");
			}
		}
	}
}
//A film title of the data carries one (Thriller\u{2014}A Cruel Documentary): the check is on our words
f_check("no em dash in any phase 3 caption or rendering",strpos(str_replace("Thriller\u{2014}A Cruel Documentary","",$strallp),"\u{2014}"),false);

//Phase 3 lot B: map of countries (fixtures p3b-*, the outline html/data/world-110m.json).
$arrworld=f_t2srep_world_map();
f_check("map outline",array($arrworld['width'],$arrworld['height'],count($arrworld['shapes']),isset($arrworld['dots']['702'],$arrworld['dots']['344'],$arrworld['dots']['470'],$arrworld['dots']['492'],$arrworld['dots']['438'],$arrworld['dots']['048'],$arrworld['dots']['020']),
	$arrworld['alpha2']['250'],$arrworld['alpha2']['826']),array(2000,863,173,true,"FR","GB"));
//The line ending normalised: a Windows checkout may write the final newline as CRLF
f_check("map outline, the same bytes as voice-agent",hash("sha256",str_replace("\r\n","\n",file_get_contents(__DIR__."/../html/data/world-110m.json"))),"4d1092a11836585a3ec2747559f81b365163bd0e095803a2635c507c7e5aa9cc");
f_check("map caption en",f_t2srep_caption($arrfix['p3b-map-films-2020-en'],"en"),
	"The first 50 countries by number of films on the map; United States leads with 3,957 films.");
f_check("map caption fr",f_t2srep_caption($arrfix['p3b-map-coproductions-france-fr'],"fr"),
	"Les 50 premiers pays par nombre de films sur la carte ; Italie arrive en tête avec 2,514 films.");
f_check("map caption series",f_t2srep_caption($arrfix['p3b-map-netflix-series-en'],"en"),
	"The first 50 countries by number of series on the map; United States leads with 1,047 series.");
f_check("map caption without counts",f_t2srep_caption($arrfix['p3b-map-country-locations-en'],"en"),
	"50 countries on the map: Indonesia, England, France, Italy, Mexico and 45 more.");
$arrflagjson=$arrfix['p3b-map-films-2020-en'];
$arrflagjson['representation']="flag-list";
f_check("map: the flag caption unchanged",f_t2srep_caption($arrflagjson,"en"),"The first 50 countries by number of films; United States leads with 3,957 films.");
$arrmf=f_t2srep_map_model($arrfix['p3b-map-films-2020-en'],"en");
f_check("map legend",array($arrmf['legendtitle'],$arrmf['legend']),array("Number of films",array(array("text"=>"39-55","tone"=>0),array("text"=>"57-98","tone"=>1),
	array("text"=>"99-134","tone"=>2),array("text"=>"177-316","tone"=>3),array("text"=>"401-3,957","tone"=>4))));
$arrmn=f_t2srep_map_model($arrfix['p3b-map-netflix-series-en'],"fr");
f_check("map legend fr, one value a step",array($arrmn['legendtitle'],$arrmn['legend'][0]['text']),array("Nombre de séries","2"));
f_check("map top country",array($arrmf['countries'][0]['numeric'],$arrmf['countries'][0]['label'],$arrmf['countries'][0]['tone'],$arrmf['countries'][0]['dot']),
	array("840","United States: 3,957 films",4,null));
$arrmff=f_t2srep_map_model($arrfix['p3b-map-films-2020-fr'],"fr");
f_check("map label fr",$arrmff['countries'][0]['label'],"États-Unis : 3,957 films");
$arrdotted=array();
foreach ($arrmf['countries'] as $arrc) {
	if ($arrc['dot']!==null) {
		$arrdotted[]=array($arrc['name'],$arrc['dot']['left']>0 && $arrc['dot']['left']<100 && $arrc['dot']['top']>0 && $arrc['dot']['top']<100);
	}
}
f_check("map small countries are dots",$arrdotted,array(array("Hong Kong",true),array("Singapore",true)));
f_check("map every country placed",count($arrmf['countries']),50);
$arrml=f_t2srep_map_model($arrfix['p3b-map-country-locations-en'],"en");
$arruk=null;
foreach ($arrml['countries'] as $arrc) {
	if ($arrc['numeric']==="826") {
		$arruk=array($arrc['name'],$arrc['tone']);
	}
}
f_check("map locations: one country, its places named together, no shade",$arruk,array("England, Scotland",null));
f_check("map locations: not on the map",$arrml['offtext'],"Not on the map: Milan, Russian Empire, Venice, Soviet Union, Ancient Rome, Hamburg.");
f_check("map locations: no legend, the name hint",array($arrml['legend'],$arrml['legendtitle'],$arrml['hint']),array(array(),"","Tap or hover over a country to see its name."));
$arrsu=$arrfix['p3b-map-films-2020-fr'];
$arrsu['summary_facts']['countries']['countries'][1]=array_merge($arrsu['summary_facts']['countries']['countries'][1],array("code"=>"SU","numeric"=>null,"name"=>array("en"=>"Soviet Union","fr"=>"Union soviétique")));
$arrmsu=f_t2srep_map_model($arrsu,"fr");
f_check("map off map fr, with the count",$arrmsu['offtext'],"Hors carte : Union soviétique (1,127).");
$arrtones=array();
foreach (array(1,2,3,4,5) as $lngn) {
	$arrrow=array();
	for ($lngi=0;$lngi<$lngn;$lngi++) {
		$arrrow[]=f_t2srep_map_tone($lngi,$lngn);
	}
	$arrtones[]=$arrrow;
}
f_check("map tones",$arrtones,array(array(4),array(0,4),array(0,2,4),array(0,1,3,4),array(0,1,2,3,4)));
f_check("map: no facts, no model",f_t2srep_map_model($arrfix['lotc-share-hbo-type-en'],"en"),null);
f_check("map chips",f_t2srep_options($arrfix['p3b-map-films-2020-en']),array("map","flag-list","ranked-bars","table"));
$strhtml=f_t2srep_render("map",$arrfix['p3b-map-films-2020-en'],"en");
f_contains("map svg",$strhtml,"<svg class=\"t2sMapSvg\" viewBox=\"0 0 2000 863\" role=\"img\" aria-label=\"World map\"><path class=\"t2sMapLand\" d=\"M");
f_contains("map country shaded and named",$strhtml,"<path class=\"t2sMapCountry t2sMapTone4\" d=\"".$arrworld['shapes']['840']."\" tabindex=\"0\" aria-label=\"United States: 3,957 films\" data-t2smap=\"840\"><title>United States: 3,957 films</title></path>");
f_contains("map dot placed by percent",$strhtml,"class=\"t2sMapDot t2sMapTone");
f_check("map: one shaded country per country, the dots apart",array(substr_count($strhtml,"<path class=\"t2sMapCountry"),substr_count($strhtml,"class=\"t2sMapDot")),array(48,2));
f_contains("map hint line",$strhtml,"<p class=\"t2sMapTip\" aria-live=\"polite\">Tap or hover over a country to see its count.</p>");
f_contains("map legend",$strhtml,"<span class=\"t2sMapLegendTitle\">Number of films</span><span class=\"t2sMapLegendItem\"><span class=\"t2sMapSwatch t2sMapTone0\" aria-hidden=\"true\"></span><span>39-55</span></span>");
f_contains("map hidden table",$strhtml,"<div class=\"t2sSrOnly\"><table><thead><tr><th scope=\"col\">Country</th><th scope=\"col\">Number of films</th></tr></thead><tbody><tr><td>United States</td><td>3,957</td></tr>");
$strhtml=f_t2srep_render("map",$arrfix['p3b-map-country-locations-en'],"en");
f_contains("map off-map note",$strhtml,"<p class=\"t2sMapOff\">Not on the map: Milan, Russian Empire, Venice, Soviet Union, Ancient Rome, Hamburg.</p>");
f_contains("map without counts: one tone",$strhtml,"<path class=\"t2sMapCountry t2sMapOn\"");
f_check("map without counts: no legend",strpos($strhtml,"t2sMapLegend"),false);
f_contains("map without the outline: the flag list",f_t2srep_render_map($arrfix['p3b-map-films-2020-en'],"en",array("nothing")),"<ul class=\"t2sFlags\">");
f_nodecheck("the map hover script",f_t2srep_map_js());
$strallm="";
foreach ($arrfix as $strkey=>$arrjson) {
	if (strpos($strkey,"p3b-")===0) {
		$strallm.=f_t2srep_caption($arrjson,"en").f_t2srep_caption($arrjson,"fr").f_t2srep_render("map",$arrjson,"en").f_t2srep_render("map",$arrjson,"fr");
	}
}
f_check("no em dash in any map caption or rendering",strpos($strallm,"\u{2014}"),false);


//Phase 3 lot C: hierarchy (fixtures p3c-*; the sentences are voice-agent's, word for word).
$strp="p3c-hierarchy-";
f_check("hierarchy caption even en",f_t2srep_caption($arrfix[$strp.'middle-earth-trilogies-en'],"en"),"Six films in two trilogies, three in each.");
f_check("hierarchy caption even fr",f_t2srep_caption($arrfix[$strp.'middle-earth-trilogies-en'],"fr"),"Six films en deux trilogies, trois dans chacune.");
f_check("hierarchy caption largest fr",f_t2srep_caption($arrfix[$strp.'mcu-collections-fr'],"fr"),
	"37 films en douze collections ; la plus grande, The Avengers Collection, en compte six.");
f_check("hierarchy caption largest en",f_t2srep_caption($arrfix[$strp.'mcu-collections-fr'],"en"),
	"37 films in twelve collections; the largest, The Avengers Collection, has six.");
f_check("hierarchy caption shared en",f_t2srep_caption($arrfix[$strp.'italy-regions-en'],"en"),
	"44 places in 19 regions (five in more than one); the largest, Lazio, has eleven.");
f_check("hierarchy caption shared fr, more pages",f_t2srep_caption($arrfix[$strp.'italy-regions-fr'],"fr",true),
	"Les 42 premiers lieux en 20 régions (sept dans plusieurs) ; la plus grande, Lazio, en compte onze.");
f_check("hierarchy caption masculine level fr",f_t2srep_caption($arrfix[$strp.'ford-states-en'],"fr"),
	"Douze lieux en neuf États (trois dans plusieurs) ; le plus grand, California, en compte cinq.");
f_check("hierarchy caption three levels en",f_t2srep_caption($arrfix[$strp.'potter-nations-counties-hand-en'],"en"),
	"Seven places in five counties and three nations; the largest, England, has five.");
f_check("hierarchy caption three levels fr",f_t2srep_caption($arrfix[$strp.'potter-nations-counties-hand-en'],"fr"),
	"Sept lieux dans cinq comtés et trois nations ; la plus grande, England, en compte cinq.");
f_check("hierarchy caption more pages en",f_t2srep_caption($arrfix[$strp.'middle-earth-trilogies-en'],"en",true),"The first six films in two trilogies, three in each.");
$arrhme=f_t2srep_hierarchy_model($arrfix[$strp.'middle-earth-trilogies-en'],"en");
$arrhrow=array();
foreach ($arrhme['groups'] as $arrg) {
	$arrhrow[]=array($arrg['name'],$arrg['count'],count($arrg['leaves']));
}
f_check("hierarchy groups",$arrhrow,array(array("The Hobbit Collection",3,3),array("The Lord of the Rings Collection",3,3)));
$arrhl=$arrhme['groups'][1]['leaves'][0];
f_check("hierarchy leaf with year and its row",array($arrhme['image'],$arrhl['title'],$arrhl['year'],$arrhl['index']),
	array(true,"The Lord of the Rings: The Fellowship of the Ring","2001",3));
$arrhmcu=f_t2srep_hierarchy_model($arrfix[$strp.'mcu-collections-fr'],"fr");
f_check("hierarchy leaf alone after its parent",array($arrhmcu['image'],$arrhmcu['groups'][0]['leaves'][0]['record'],$arrhmcu['groups'][0]['leaves'][0]['year']),
	array(false,array("ID_MOVIE"=>102899,"MOVIE_TITLE"=>"Ant-Man"),""));
f_check("hierarchy group record",$arrhmcu['groups'][0]['record'],array("ID_T2S_COLLECTION"=>1776,"COLLECTION_NAME"=>"Ant-Man Collection"));
f_check("hierarchy no id, no group record",$arrhme['groups'][0]['record'],null);
$arrhh=f_t2srep_hierarchy_model($arrfix[$strp.'potter-nations-counties-hand-en'],"en");
$arrhrow=array();
foreach ($arrhh['groups'] as $arrg) {
	$arrsubs=array();
	foreach ($arrg['groups'] as $arrs) {
		$arrtitles=array();
		foreach ($arrs['leaves'] as $arrl) {
			$arrtitles[]=$arrl['title'];
		}
		$arrsubs[]=array($arrs['name'],$arrs['count'],$arrtitles);
	}
	$arrhrow[]=array($arrg['name'],$arrg['count'],count($arrg['leaves']),$arrsubs);
}
f_check("hierarchy three levels",$arrhrow,array(
	array("England",5,0,array(array("Greater London",2,array("Leadenhall Market","London Zoo")),array("North Yorkshire",1,array("Goathland railway station")),array("Oxfordshire",2,array("Christ Church","New College")))),
	array("Scotland",1,0,array(array("Highland",1,array("Glen Nevis")))),array("Wales",1,0,array(array("Pembrokeshire",1,array("Pembrokeshire"))))));
$arrhit=f_t2srep_hierarchy_model($arrfix[$strp.'italy-regions-en'],"en");
$arrcirella=array();
foreach ($arrhit['groups'] as $arrg) {
	foreach ($arrg['leaves'] as $arrl) {
		if ($arrl['title']=="Cirella") {
			$arrcirella[]=$arrg['name'];
		}
	}
}
f_check("hierarchy a shared place under each of its groups",$arrcirella,array("Calabria","Diamante"));
$arrhother=$arrfix[$strp.'middle-earth-trilogies-en'];
$arrhother['summary_facts']['hierarchy']['groups'][0]['label']=null;
$arrho=f_t2srep_hierarchy_model($arrhother,"fr");
f_check("hierarchy an unnamed group reads Other",array($arrho['groups'][0]['name'],$arrho['groups'][0]['unnamed']),array("Autres",true));
f_check("hierarchy: no facts, no model",f_t2srep_hierarchy_model($arrfix['lotc-share-hbo-type-en'],"en"),null);
f_check("hierarchy chips",f_t2srep_options($arrfix[$strp.'italy-regions-en']),array("hierarchy","card-grid","table"));
$fnhposter=function($r) { return isset($r['ID_MOVIE']) ? array("url"=>isset($r['POSTER_PATH']) ? "https://img.test/w92".$r['POSTER_PATH'] : "","title"=>"","href"=>"movie.php?movieid=".$r['ID_MOVIE']) : null; };
$strhtml=f_t2srep_render("hierarchy",$arrfix[$strp.'middle-earth-trilogies-en'],"en",$fnhposter);
f_contains("hierarchy sections and headings",$strhtml,"<div class=\"t2sHier\"><section class=\"t2sHierGroup\"><h3 class=\"t2sHierHead\"><span class=\"t2sHierName\">The Hobbit Collection</span> <span class=\"t2sHierCount\">3</span></h3><ul class=\"t2sHierLeaves\">");
f_contains("hierarchy leaf: poster, title, year, link",$strhtml,"<li class=\"t2sHierLeaf\"><a class=\"t2sHierLink\" href=\"movie.php?movieid=49051\"><img class=\"t2sHierThumb\" src=\"https://img.test/w92/yHA9Fc37VmpUA5UncTxxo3rTGVA.jpg\" alt=\"\" loading=\"lazy\"><span class=\"t2sHierText\"><span class=\"t2sHierTitle\">The Hobbit: An Unexpected Journey</span><span class=\"t2sHierSub\">2012</span></span></a></li>");
$strhtml=f_t2srep_render("hierarchy",$arrfix[$strp.'mcu-collections-fr'],"fr",$fnhposter);
f_contains("hierarchy group linked to its page",$strhtml,"<span class=\"t2sHierName\"><a href=\"t2scollection.php?collectionid=1776\">Ant-Man Collection</a></span>");
f_contains("hierarchy leaf without its parent's poster",$strhtml,"<li class=\"t2sHierLeaf\"><a class=\"t2sHierLink\" href=\"movie.php?movieid=102899\"><span class=\"t2sHierText\"><span class=\"t2sHierTitle\">Ant-Man</span></span></a></li>");
$strhtml=f_t2srep_render("hierarchy",$arrfix[$strp.'potter-nations-counties-hand-en'],"en",$fnhposter);
f_contains("hierarchy third level nested",$strhtml,"<section class=\"t2sHierGroup\"><h3 class=\"t2sHierHead\"><span class=\"t2sHierName\">England</span> <span class=\"t2sHierCount\">5</span></h3><section class=\"t2sHierGroup t2sHierInner\"><h4 class=\"t2sHierHead\"><span class=\"t2sHierName\">Greater London</span> <span class=\"t2sHierCount\">2</span></h4><ul class=\"t2sHierLeaves\">");
f_contains("hierarchy place picture and page",$strhtml,"<a class=\"t2sHierLink\" href=\"location.php?locationid=8390\"><img class=\"t2sHierThumb\" src=\"https://");
$strallh="";
foreach ($arrfix as $strkey=>$arrjson) {
	if (strpos($strkey,"p3c-")===0) {
		$strallh.=f_t2srep_caption($arrjson,"en").f_t2srep_caption($arrjson,"fr").f_t2srep_render("hierarchy",$arrjson,"en").f_t2srep_render("hierarchy",$arrjson,"fr");
	}
}
f_check("no em dash in any hierarchy caption or rendering",strpos($strallh,"\u{2014}"),false);

//Phase 3 lot E: chain (fixtures p3e-*; the sentences are voice-agent's, word for word).
f_check("chain caption p3e-chain-departed-en",f_t2srep_caption($arrfix['p3e-chain-departed-en'],"en"),'Five works from Infernal Affairs (2002) to The Clan (2022); The Departed remakes Infernal Affairs.');
f_check("chain caption p3e-chain-fistful-en",f_t2srep_caption($arrfix['p3e-chain-fistful-en'],"en"),'Three works from Red Harvest (1927) to A Fistful of Dollars (1964); A Fistful of Dollars draws on two of them.');
f_check("chain caption p3e-chain-fistful-fr",f_t2srep_caption($arrfix['p3e-chain-fistful-fr'],"fr"),'Trois œuvres, de La Moisson rouge (1927) à Pour une poignée de dollars (1964) ; Pour une poignée de dollars en reprend deux.');
f_check("chain caption p3e-chain-last-man-standing-en",f_t2srep_caption($arrfix['p3e-chain-last-man-standing-en'],"en"),'Three works from Red Harvest (1927) to Last Man Standing (1996); Last Man Standing remakes Yojimbo.');
f_check("chain caption p3e-chain-magnificent-seven-1960-en",f_t2srep_caption($arrfix['p3e-chain-magnificent-seven-1960-en'],"en"),'Five works from Seven Samurai (1954) to The Magnificent Seven (2016); The Magnificent Seven (1960) remakes Seven Samurai.');
f_check("chain caption p3e-chain-magnificent-seven-2016-en",f_t2srep_caption($arrfix['p3e-chain-magnificent-seven-2016-en'],"en"),'Three films from Seven Samurai (1954) to The Magnificent Seven (2016); The Magnificent Seven (2016) draws on two of them.');
f_check("chain caption p3e-chain-nosferatu-en",f_t2srep_caption($arrfix['p3e-chain-nosferatu-en'],"en"),'Three works from Dracula (1897) to Nosferatu (2024); Nosferatu (2024) draws on two of them.');
f_check("chain caption p3e-chain-nosferatu-fr",f_t2srep_caption($arrfix['p3e-chain-nosferatu-fr'],"fr"),'Trois œuvres, de Dracula (1897) à Nosferatu (2024) ; Nosferatu (2024) en reprend deux.');
f_check("chain caption p3e-chain-red-harvest-cut-hand-en",f_t2srep_caption($arrfix['p3e-chain-red-harvest-cut-hand-en'],"en"),'Five works from Red Harvest (1927) to Last Man Standing (1996) (the lineage goes on beyond five works); Red Harvest directly inspired three of them.');
f_check("chain caption p3e-chain-red-harvest-cut-hand-fr",f_t2srep_caption($arrfix['p3e-chain-red-harvest-cut-hand-fr'],"fr"),'Cinq œuvres, de La Moisson rouge (1927) à Dernier recours (1996) (la filiation continue au-delà de cinq œuvres) ; La Moisson rouge en a inspiré directement trois.');
f_check("chain caption p3e-chain-red-harvest-en",f_t2srep_caption($arrfix['p3e-chain-red-harvest-en'],"en"),'Nine works from Red Harvest (1927) to Django (2023); Red Harvest directly inspired three of them.');
f_check("chain caption p3e-chain-ring-hand-en",f_t2srep_caption($arrfix['p3e-chain-ring-hand-en'],"en"),'Three films from Dog Day Afternoon (1975) to The Dog (2013); Terrorism and Kebab remakes Dog Day Afternoon.');
f_check("chain caption p3e-chain-scarface-en",f_t2srep_caption($arrfix['p3e-chain-scarface-en'],"en"),'Three works from Scarface (1930) to Scarface (1983); Scarface (1983) draws on two of them.');
f_check("chain caption p3e-chain-seven-samurai-en",f_t2srep_caption($arrfix['p3e-chain-seven-samurai-en'],"en"),'Six works from Seven Samurai (1954) to The Magnificent Seven (2016); Seven Samurai directly inspired three of them.');
f_check("chain caption p3e-chain-seven-samurai-fr",f_t2srep_caption($arrfix['p3e-chain-seven-samurai-fr'],"fr"),'Six œuvres, des Sept Samouraïs (1954) aux Sept Mercenaires (2016) ; Les Sept Samouraïs en a inspiré directement trois.');
f_check("chain caption p3e-chain-star-is-born-en",f_t2srep_caption($arrfix['p3e-chain-star-is-born-en'],"en"),'Four films from A Star Is Born (1937) to A Star Is Born (2018); A Star Is Born (2018) draws on three of them.');
f_check("chain caption p3e-chain-star-is-born-fr",f_t2srep_caption($arrfix['p3e-chain-star-is-born-fr'],"fr"),'Quatre films, d\'Une étoile est née (1937) à A Star is Born (2018) ; A Star is Born en reprend trois.');
$strp="p3e-chain-";
$arrm=f_t2srep_chain_model($arrfix[$strp.'star-is-born-en'],"en");
f_check("chain generations by year",array_map(function($g){return array_map(function($c){return $c['year'];},$g);},$arrm['generations']),array(array("1937"),array("1954","1976"),array("2018")));
f_check("chain links of the 2018 card",array_map(function($l){return $l['text'];},$arrm['generations'][2][0]['links']),
	array("Remake of A Star Is Born (1937)","Remake of A Star Is Born (1954)","Remake of A Star Is Born (1976)"));
f_check("chain anchor and label",array($arrm['generations'][2][0]['anchor'],$arrm['generations'][2][0]['label'],$arrm['generations'][0][0]['anchor']),array(true,"A Star Is Born (2018)",false));
f_check("chain record of a film",$arrm['generations'][0][0]['record'],array("ID_MOVIE"=>22692,"MOVIE_TITLE"=>"A Star Is Born","POSTER_PATH"=>"/90EfCmXXWOs5dy7rHTNvGT9T8Kz.jpg"));
$arrm=f_t2srep_chain_model($arrfix[$strp.'last-man-standing-en'],"en");
f_check("chain badge of a novel",array($arrm['generations'][0][0]['badge'],$arrm['generations'][1][0]['badge']),array("Book","Film"));
f_check("chain record of a source work",array_keys($arrm['generations'][0][0]['record']),array("ID_SOURCE_WORK","SOURCE_WORK_NAME","WIKIPEDIA_IMAGE_PATH"));
f_check("chain adaptation link en",$arrm['generations'][1][0]['links'][0]['text'],"Adapted from Red Harvest");
f_check("chain badge fr",f_t2srep_chain_model($arrfix[$strp.'last-man-standing-en'],"fr")['generations'][0][0]['badge'],"Livre");
$arrm=f_t2srep_chain_model($arrfix[$strp.'fistful-fr'],"fr");
f_check("chain links fr, contracted and elided",array_map(function($l){return $l['text'];},$arrm['generations'][2][0]['links']),
	array("Adaptation de La Moisson rouge","Remake du Garde du corps"));
$arrm=f_t2srep_chain_model($arrfix[$strp.'seven-samurai-fr'],"fr");
f_check("chain link fr des",$arrm['generations'][1][0]['links'][0]['text'],"Remake des Sept Samouraïs");
f_check("chain series badge fr",$arrm['generations'][1][1]['badge'],"Série");
$arrm=f_t2srep_chain_model($arrfix[$strp.'ring-hand-en'],"en");
f_check("chain loop marked",$arrm['generations'][0][0]['links'],array(array("text"=>"Remake of The Dog (a loop in the data)","cycle"=>true)));
f_check("chain loop fr",f_t2srep_chain_model($arrfix[$strp.'ring-hand-en'],"fr")['generations'][0][0]['links'][0]['text'],"Remake de The Dog (une boucle dans les données)");
f_check("chain de before a vowel, Le, Les",array(f_t2srep_chain_de("Une étoile est née"),f_t2srep_chain_de("Le Garde du corps"),f_t2srep_chain_de("Les Sept Samouraïs"),f_t2srep_chain_de("Dracula"),f_t2srep_chain_de("Écran")),
	array("d'Une étoile est née","du Garde du corps","des Sept Samouraïs","de Dracula","d'Écran"));
f_check("chain a before Le, Les",array(f_t2srep_chain_a("Les Sept Mercenaires (2016)"),f_t2srep_chain_a("Le Garde du corps"),f_t2srep_chain_a("Dracula")),array("aux Sept Mercenaires (2016)","au Garde du corps","à Dracula"));
f_check("chain model null without the facts",f_t2srep_chain_model($arrfix['p3c-hierarchy-middle-earth-trilogies-en'],"en"),null);
f_check("chain caption empty without the facts",f_t2srep_caption_chain($arrfix['p3c-hierarchy-middle-earth-trilogies-en'],"en"),"");
$arrone=$arrfix[$strp.'last-man-standing-en'];
$arrone['summary_facts']['chain']['edges']=array_slice($arrone['summary_facts']['chain']['edges'],0,1);
f_check("chain of one link is no chain",f_t2srep_chain_model($arrone,"en"),null);
$arrdepth=$arrfix[$strp.'red-harvest-en'];
$arrdepth['summary_facts']['chain']['truncated']=true;
$arrdepth['summary_facts']['chain']['truncated_by']="depth";
f_check("chain caption cut by depth en",f_t2srep_caption($arrdepth,"en"),"Nine works from Red Harvest (1927) to Django (2023) (the lineage goes on beyond four generations); Red Harvest directly inspired three of them.");
$arrdepth['summary_facts']['chain']['truncated_by']="time";
f_check("chain caption cut by time fr",f_t2srep_caption_chain($arrdepth,"fr"),"Neuf œuvres, de Red Harvest (1927) à Django (2023) (le parcours s'est arrêté avant la fin) ; Red Harvest en a inspiré directement trois.");
f_check("chain known and drawn here",array(in_array("chain",f_t2srep_known(),true),in_array("chain",f_t2srep_module_reps(),true)),array(true,true));
f_check("chain options",f_t2srep_options($arrfix[$strp.'departed-en']),array("chain","card-grid","table"));
$fncposter=function($r) { return isset($r['ID_MOVIE']) ? array("url"=>isset($r['POSTER_PATH']) ? "https://img.test/w92".$r['POSTER_PATH'] : "","title"=>"","href"=>"movie.php?movieid=".$r['ID_MOVIE']) : null; };
$strhtml=f_t2srep_render("chain",$arrfix[$strp.'last-man-standing-en'],"en",$fncposter);
f_contains("chain list of generations",$strhtml,"<ol class=\"t2sChain\"><li class=\"t2sChainGen\"><ul class=\"t2sChainWorks\"><li class=\"t2sChainWork\">");
f_contains("chain source work card: picture, badge, page",$strhtml,"<a class=\"t2sChainCard\" href=\"sourcework.php?sourceworkid=6848\" aria-label=\"Red Harvest, 1927\"><img class=\"t2sChainThumb\" src=\"https://upload.wikimedia.org/");
f_contains("chain arrow between generations",$strhtml,"<li class=\"t2sChainGen\"><span class=\"t2sChainArrow\" aria-hidden=\"true\"></span><ul class=\"t2sChainWorks\">");
f_contains("chain starting point",$strhtml,"<li class=\"t2sChainWork isAnchor\"><a class=\"t2sChainCard\" href=\"movie.php?movieid=9333\" aria-label=\"Last Man Standing, 1996\"><img class=\"t2sChainThumb\" src=\"https://img.test/w92/2cWqlkXaKKdl32Waep4FwCQnyH0.jpg\" alt=\"\" loading=\"lazy\"><span class=\"t2sChainText\"><span class=\"t2sChainTitle\">Last Man Standing</span><span class=\"t2sChainMeta\"><span class=\"t2sChainYear\">1996</span> <span class=\"t2sChainBadge\">Film</span></span><span class=\"t2sChainStart\">Starting point</span></span></a><ul class=\"t2sChainLinks\"><li class=\"t2sChainLink\">Remake of Yojimbo</li></ul></li>");
f_contains("chain starting point fr",f_t2srep_render("chain",$arrfix[$strp.'fistful-fr'],"fr",$fncposter),"<span class=\"t2sChainStart\">Point de départ</span>");
f_contains("chain loop line",f_t2srep_render("chain",$arrfix[$strp.'ring-hand-en'],"en"),"<li class=\"t2sChainLink isCycle\">Remake of The Dog (a loop in the data)</li>");
f_contains("chain without facts falls back to the table",f_t2srep_render("chain",$arrone,"en"),"<table");
$strallc="";
foreach ($arrfix as $strkey=>$arrjson) {
	if (strpos($strkey,"p3e-")===0) {
		$strallc.=f_t2srep_caption($arrjson,"en").f_t2srep_caption($arrjson,"fr").f_t2srep_render("chain",$arrjson,"en").f_t2srep_render("chain",$arrjson,"fr");
	}
}
f_check("no em dash in any chain caption or rendering",strpos($strallc,"\u{2014}"),false);

f_group("Percent, gender codes, count aliases");
//The percent unit (PERCENT*, POURCENT*, PCT columns; the value is already a percentage), the
//gender codes named outside the share bar, and the count aliases of the drill-down words. The
//Barbie fixtures are answers that read "1" and "2" and "×49.3"; the PERCENTAGE column of the
//table one is typed "percent", as the API now sends it. The same checks as voice-agent.
f_check("percent metric",array(f_t2srep_format_metric(49.25,"percent","en"),f_t2srep_format_metric(50,"percent","en"),f_t2srep_format_metric(49.25,"percent","fr"),
	f_t2srep_format_metric(0,"percent","en"),f_t2srep_format_metric(100,"percent","fr")),
	array("49.3%","50%","49.3 %","0%","100 %"));
f_check("percent never money nor ratio",array(f_t2srep_format_value(49.25,"percent","metric","en"),f_t2srep_format_value(0,"percent","metric","en")),array("49.3%","0%"));
f_check("ratio unchanged",f_t2srep_format_metric(49.3,"ratio","en"),"×49.3");
$arrpcttable=$arrfix['lotc-table-gender-percent-barbie-en'];
$strhtml=f_t2srep_render("table",$arrpcttable,"en");
f_contains("percent column of the Barbie table",$strhtml,"<td>Women</td><td>33</td><td>49.3%</td>");
f_contains("percent column of the Barbie table, second row",$strhtml,"<td>Men</td><td>34</td><td>50.8%</td>");
f_check("gender label",array(f_t2srep_gender_label("GENDER",2,"en"),f_t2srep_gender_label("GENDER","1","fr"),f_t2srep_gender_label("SEXE",3,"fr"),
	f_t2srep_gender_label("GENRE",1,"fr"),f_t2srep_gender_label("GENDER","Femmes","fr"),f_t2srep_gender_label("GENDER",null,"en")),
	array("men","femmes","non binaire",null,null,null));
$arrbarbie=$arrfix['lotc-share-gender-barbie-ranked-fr'];
$arrbarbierows=f_t2srep_records($arrbarbie['result']);
$arrbarbiecols=f_t2srep_columns($arrbarbie);
f_check("gender bar labels fr",array(f_t2srep_label_for_row($arrbarbierows[0],$arrbarbiecols,"fr"),f_t2srep_label_for_row($arrbarbierows[1],$arrbarbiecols,"fr")),array("Femmes","Hommes"));
f_check("gender bar labels en",array(f_t2srep_label_for_row($arrbarbierows[0],$arrbarbiecols,"en"),f_t2srep_label_for_row($arrbarbierows[1],$arrbarbiecols,"en")),array("Women","Men"));
$arrlabelsfr=$arrfix['lotc-share-gender-labels-fr'];
$arrlabelsfrrows=f_t2srep_records($arrlabelsfr['result']);
f_check("spelled-out labels kept",f_t2srep_label_for_row($arrlabelsfrrows[0],f_t2srep_columns($arrlabelsfr),"fr"),"Femmes");
$strhtml=f_t2srep_render("ranked-bars",$arrbarbie,"fr");
f_contains("gender bars fr",$strhtml,"<div class=\"t2sBarLabel\" title=\"Hommes\">Hommes</div>");
f_contains("gender table fr",f_t2srep_render("table",$arrbarbie,"fr"),"<td>Femmes</td><td>33</td>");
f_check("gender caption ranked fr",f_t2srep_caption($arrbarbie,"fr"),"Deux lignes ; le role count le plus élevé revient aux hommes, 34.");
f_check("gender caption ranked en",f_t2srep_caption($arrbarbie,"en"),"Two rows; the highest role count goes to men, 34.");
$arrbarbieen=$arrfix['lotc-share-gender-barbie-en'];
f_check("gender caption share en",f_t2srep_caption($arrbarbieen,"en"),"67 people counted in two parts: women 49%, men 51%.");
f_check("gender caption share fr",f_t2srep_caption($arrfix['lotc-share-gender-barbie-fr'],"fr"),"67 résultats comptés en deux parts : femmes 49 %, hommes 51 %.");
f_check("gender caption table, three columns",f_t2srep_caption($arrpcttable,"en"),"Two rows; the highest actor count goes to men, 34.");
$arrlowest=$arrbarbie;
$arrlowest['summary_facts']['top']=array("label"=>1,"id"=>null,"index"=>0,"value"=>33,"extreme"=>"min");
f_check("gender lowest fr",f_t2srep_caption($arrlowest,"fr"),"Deux lignes ; le role count le plus bas revient aux femmes, 33.");
f_check("gender lowest en",f_t2srep_caption($arrlowest,"en"),"Two rows; the lowest role count goes to women, 33.");
$arrnonbinary=$arrbarbie;
$arrnonbinary['summary_facts']['top']=array("label"=>3,"id"=>null,"index"=>0,"value"=>5,"extreme"=>"max");
f_check("gender plural fr",f_t2srep_caption($arrnonbinary,"fr"),"Deux lignes ; le role count le plus élevé revient aux non binaires, 5.");
//The API types GENDER "other" (a category): the rows are named all the same, never on entity rows
$arrother=$arrbarbie;
foreach ($arrother['result_columns'] as $lngi=>$arrcol) {
	if ($arrcol['name']=="GENDER") {
		$arrother['result_columns'][$lngi]['role']="other";
	}
}
unset($arrother['summary_facts']['share']);
$arrothercols=f_t2srep_columns($arrother);
f_check("gender column typed other",array(f_t2srep_gender_column($arrothercols),f_t2srep_label_for_row($arrbarbierows[1],$arrothercols,"en")),array("GENDER","Men"));
f_check("no gender column on entity rows",f_t2srep_gender_column(array(array("name"=>"ID_PERSON","role"=>"entity_id"),array("name"=>"GENDER","role"=>"other"))),"");
f_check("no gender column for a metric",f_t2srep_gender_column(array(array("name"=>"GENDER_COUNT","role"=>"metric"))),"");
f_check("gender caption, column typed other",f_t2srep_caption($arrother,"en"),"Two rows; the highest role count goes to men, 34.");
f_contains("gender table, column typed other",f_t2srep_render("table",$arrother,"en"),"<td>Men</td><td>34</td>");
f_check("drill count aliases",array(
	f_t2srep_drill_word("SEASON_COUNT","en"),f_t2srep_drill_word("SEASON_COUNT","fr"),f_t2srep_drill_word("NB_SEASONS","fr"),
	f_t2srep_drill_word("EPISODE_COUNT","fr"),f_t2srep_drill_word("NB_EPISODES","en"),f_t2srep_drill_word("SERIES_COUNT","fr"),
	f_t2srep_drill_word("NUMBER_OF_FILMS","fr"),f_t2srep_drill_word("PERSON_COUNT","en"),f_t2srep_drill_word("ROLE_COUNT","fr"),
	f_t2srep_drill_word("SEASONS","en"),f_t2srep_drill_word("VOTE_COUNT","fr")),
	array(array("label"=>"number of seasons","gender"=>"m"),array("label"=>"nombre de saisons","gender"=>"m"),array("label"=>"nombre de saisons","gender"=>"m"),
		array("label"=>"nombre d'épisodes","gender"=>"m"),array("label"=>"number of episodes","gender"=>"m"),array("label"=>"nombre de séries","gender"=>"m"),
		array("label"=>"nombre de films","gender"=>"m"),array("label"=>"number of people","gender"=>"m"),array("label"=>"role count","gender"=>"m"),
		array("label"=>"seasons","gender"=>"m"),array("label"=>"nombre de votes TMDb","gender"=>"m")));
$arrsc=$arrfix['lotc-histogram-season-count-en'];
$arrscmodel=f_t2srep_histogram_model($arrsc['summary_facts'],"en");
$arrscbins=array_values($arrsc['summary_facts']['histogram']['bins']);
f_check("drill SEASON_COUNT en",f_t2srep_drill_question($arrsc['question'],f_t2srep_drill_word($arrscmodel['column'],"en"),f_t2srep_drill_bin_value($arrscbins[2],$arrscmodel['histogram'],"en"),"en"),
	"Among the items counted in \"How are HBO series distributed by number of seasons?\", list those whose number of seasons is 3.");
f_check("drill SEASON_COUNT fr",f_t2srep_drill_question($arrsc['question'],f_t2srep_drill_word($arrscmodel['column'],"fr"),f_t2srep_drill_bin_value($arrscbins[2],$arrscmodel['histogram'],"fr"),"fr"),
	"Parmi les éléments comptés dans « How are HBO series distributed by number of seasons? », liste ceux dont le nombre de saisons est 3.");

echo "\n".$lngchecks." checks, ".$lngfailures." failure(s)\n";
exit($lngfailures>0 ? 1 : 0);
?>
