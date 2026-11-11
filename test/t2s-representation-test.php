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
 * The last group pins the links of the entity titles in the table.
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
f_contains("mixed aliases: CONTENT_TITLE and DAT_FIRST_AIR",$strhtml,"<span class=\"t2sTimelineYearLabel\">2001</span><ul class=\"t2sTimelineMarks\"><li class=\"t2sTimelineMark\"><a href=\"movie.php?movieid=671\" title=\"Harry Potter and the Philosopher&#039;s Stone\">");
$strhtml=f_t2srep_render_timeline($arrhprows,f_t2srep_columns($arrhp),"en",null,$arrhp["summary_facts"]);
f_contains("mixed aliases without a poster callable: routed by CONTENT_TYPE",$strhtml,"<a href=\"movie.php?movieid=12445\"");
f_contains("no poster: a placeholder",$strhtml,"t2sTimelineNoPoster");
$arrstackcols=array(array("name"=>"ID_MOVIE","role"=>"entity_id"),array("name"=>"MOVIE_TITLE","role"=>"label"),array("name"=>"DAT_RELEASE","role"=>"date","focus"=>true));
$strhtml=f_t2srep_render_timeline($arrstack,$arrstackcols,"en");
f_contains("two marks stacked in one year",$strhtml,"<span class=\"t2sTimelineYearLabel\">1999</span><ul class=\"t2sTimelineMarks\"><li class=\"t2sTimelineMark\"><a href=\"movie.php?movieid=1\" title=\"A\">");
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

echo "\n".$lngchecks." checks, ".$lngfailures." failure(s)\n";
exit($lngfailures>0 ? 1 : 0);
?>
