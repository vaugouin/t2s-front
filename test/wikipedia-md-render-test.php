<?php
/*
 * wikipedia-md-render-test.php - test for lib/wikipedia-md.inc.php
 *
 * Run it from the repository root, no database and no web server needed:
 *
 *     php test/wikipedia-md-render-test.php [sample.json [preview.html]]
 *
 * The cases pin the CONTENT_MD dialect the wikipedia-crawler writes (wikipedia_content_md.py):
 * links by title with balanced parentheses, emphasis around links, tables with a header row
 * and "\|" in a cell, nested lists, definition lists, the flattened infobox, and escaping.
 *
 * With a sample.json (the decoded output of a sampling query on the database: a list
 * of [kind, row] pairs, kind S for a section, R for a reference), every section of the sample is
 * also rendered and checked for balanced tags, and preview.html receives the pages for a look
 * in a browser.
 *
 * Exit code 0 when every case passes, 1 otherwise.
 */

require_once(__DIR__."/../lib/wikipedia-md.inc.php");

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
	f_check($strlabel,strpos($strhaystack,$strneedle)!==false ? $strneedle : $strhaystack,$strneedle);
}

// Tags opened and closed in the right order (the renderer writes no void tag but <br />).
function f_balanced($strhtml) {
	preg_match_all('#<(/?)([a-z0-9]+)[^>]*?(/?)>#',$strhtml,$m,PREG_SET_ORDER);
	$arrstack=array();
	foreach ($m as $t) {
		if ($t[3]=="/" || $t[2]=="br") {
			continue;
		}
		if ($t[1]=="") {
			$arrstack[]=$t[2];
		} elseif (!$arrstack || array_pop($arrstack)!==$t[2]) {
			return false;
		}
	}
	return !$arrstack;
}

$arrmap=array("Sergio Leone"=>"person.php?personid=4385","1929 au cinéma"=>"");

// Links
f_check("internal link to a page of the site",
	f_wpmd_inline("by [Sergio Leone](Sergio_Leone)","fr",$arrmap),
	"by <a href=\"person.php?personid=4385\" class=\"wp-int\">Sergio Leone</a>");
f_check("date link loses its link",
	f_wpmd_inline("depuis [1929](1929_au_cinéma) par","fr",$arrmap),
	"depuis 1929 par");
f_check("other title links to Wikipedia, parentheses and accent kept",
	f_wpmd_inline("*[Parasite](Parasite_(film,_2019))*","fr",$arrmap),
	"<em><a href=\"https://fr.wikipedia.org/wiki/Parasite_%28film%2C_2019%29\" target=\"_blank\" rel=\"noopener\" class=\"wp-wiki\">Parasite</a></em>");
f_check("fragment",
	f_wpmd_inline("[Fox](20th_Century_Fox#Fox_Film_Corporation)","fr",$arrmap),
	"<a href=\"https://fr.wikipedia.org/wiki/20th_Century_Fox#Fox_Film_Corporation\" target=\"_blank\" rel=\"noopener\" class=\"wp-wiki\">Fox</a>");
f_check("external link",
	f_wpmd_inline("- **Website**: [www.oscars.org](https://www.oscars.org)","en",array()),
	"- <strong>Website</strong>: <a href=\"https://www.oscars.org\" target=\"_blank\" rel=\"noopener nofollow\" class=\"wp-ext\">www.oscars.org</a>");
f_check("bold around links",
	f_wpmd_inline("**[A](A) and [B](B)**","en",array("A"=>"","B"=>"")),
	"<strong>A and B</strong>");
f_check("markup in the text is escaped",
	f_wpmd_inline("<script>alert(1)</script> & \"x\"","en",array()),
	"&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;x&quot;");
f_check("a javascript: target is not a link and not a title",
	f_wpmd_inline("[x](javascript:alert(1))","en",array("javascript:alert(1)"=>"")),
	"x");
f_check("bold italic title",
	f_wpmd_inline("***[Wings](Wings_(1927_film))*** and ***Il buono***","en",array("Wings (1927 film)"=>"")),
	"<strong><em>Wings</em></strong> and <strong><em>Il buono</em></strong>");
f_check("lone star is not italic",
	f_wpmd_inline("5 * 3 and 5*3","en",array()),
	"5 * 3 and 5*3");

// Blocks
$strhtml=f_wpmd_render("| Year | Film |\n|---|---|\n| 1966 | *[Le Bon](Le_Bon)* |\n| a \\| b | c |","fr",array("Le Bon"=>""));
f_check("table with a header row",
	$strhtml,
	"<div class=\"wp-table-wrap\"><table class=\"wp-table\"><thead><tr><th>Year</th><th>Film</th></tr></thead><tbody><tr><td>1966</td><td><em>Le Bon</em></td></tr><tr><td>a | b</td><td>c</td></tr></tbody></table></div>");
f_check("nested list",
	f_wpmd_render("- a\n  - b\n  - c\n- d","en",array()),
	"<ul><li>a<ul><li>b</li><li>c</li></ul></li><li>d</li></ul>");
f_check("ordered list",
	f_wpmd_render("1. a\n2. b","en",array()),
	"<ol><li>a</li><li>b</li></ol>");
f_check("definition list inside a list item",
	f_wpmd_render("- a\n  **t**\n  : d","en",array()),
	"<ul><li>a<br /><strong>t</strong><br />d</li></ul>");
f_check("definition list",
	f_wpmd_render("**14 nominations**\n: Steven Spielberg\n: Other","en",array()),
	"<dl><dt>14 nominations</dt><dd>Steven Spielberg</dd><dd>Other</dd></dl>");
f_check("lone definition",
	f_wpmd_render("Text.\n\n: La récompense est renommée.","fr",array()),
	"<p>Text.</p><dl><dd>La récompense est renommée.</dd></dl>");
f_check("infobox without the image rows",
	f_wpmd_render("**Infobox**\n\n- **Le Bon**: Le Bon\n- **Directed by**: [Sergio Leone](Sergio_Leone)\n- Single\n\nIntro.","en",$arrmap),
	"<div class=\"wp-table-wrap\"><table class=\"wp-infobox\"><tr><th>Directed by</th><td><a href=\"person.php?personid=4385\" class=\"wp-int\">Sergio Leone</a></td></tr><tr><td colspan=\"2\">Single</td></tr></table></div><p>Intro.</p>");
f_check("headings, quote, paragraph lines",
	f_wpmd_render("#### Sur les films\n\n> Quote one\n> Quote two\n\nLine one\nLine two\n### Notes","fr",array()),
	"<h5>Sur les films</h5><blockquote>Quote one<br />Quote two</blockquote><p>Line one<br />Line two</p><h4>Notes</h4>");

// References
$strhtml=f_wpmd_references(array(
	array("LABEL"=>"[1]","URL"=>"https://example.org/a","TITLE"=>"\"How it works\"","REFERENCE_TEXT"=>"Miller (2017). \"How it works\" . People ."),
	array("LABEL"=>"[N 1]","URL"=>null,"TITLE"=>null,"REFERENCE_TEXT"=>"Soit l'Oscar."),
	array("LABEL"=>"[2]","URL"=>"javascript:alert(1)","TITLE"=>"","REFERENCE_TEXT"=>"Bad <b>"),
),"Notes and references");
f_contains("reference title is the link",$strhtml,"Miller (2017). <a href=\"https://example.org/a\" target=\"_blank\" rel=\"noopener nofollow\" class=\"wp-ext\">&quot;How it works&quot;</a> . People .");
f_contains("note without URL",$strhtml,"<span class=\"wp-ref-label\">[N 1]</span> Soit l&#039;Oscar.</li>");
f_contains("javascript: URL not linked, text escaped",$strhtml,"<span class=\"wp-ref-label\">[2]</span> Bad &lt;b&gt;</li>");
f_contains("summary with the count",$strhtml,"<summary>Notes and references (3)</summary>");
f_check("references balanced",f_balanced($strhtml),true);
f_check("no references, nothing",f_wpmd_references(array(),"x"),"");

// Mentions
$arrlabels=array("person"=>"People","movie"=>"Movies","serie"=>"Series","more"=>"and %d more");
$strhtml=f_wpmd_mentions(array(
	array("KIND"=>"person","ID"=>4385,"LABEL"=>"Sergio Leone","YEAR1"=>1929,"YEAR2"=>1989,"TOTAL"=>3),
	array("KIND"=>"person","ID"=>1259,"LABEL"=>"Ennio <Morricone>","YEAR1"=>1928,"YEAR2"=>null,"TOTAL"=>3),
	array("KIND"=>"movie","ID"=>429,"LABEL"=>"Le Bon","YEAR1"=>1966,"YEAR2"=>null,"TOTAL"=>1),
	array("KIND"=>"serie","ID"=>0,"LABEL"=>"No id","YEAR1"=>null,"YEAR2"=>null,"TOTAL"=>1),
	array("KIND"=>"company","ID"=>5,"LABEL"=>"Unknown kind","YEAR1"=>null,"YEAR2"=>null,"TOTAL"=>1),
),"Mentioned in the Wikipedia article",$arrlabels);
f_check("mentions",
	$strhtml,
	"<div class=\"wp-mentions\"><h3>Mentioned in the Wikipedia article</h3>"
	."<p class=\"wp-mentions-group\"><strong>People (3)</strong> <a href=\"person.php?personid=4385\">Sergio Leone</a> <span class=\"wp-mentions-year\">(1929-1989)</span>, <a href=\"person.php?personid=1259\">Ennio &lt;Morricone&gt;</a> <span class=\"wp-mentions-year\">(1928)</span> <span class=\"wp-mentions-more\">and 1 more</span></p>"
	."<p class=\"wp-mentions-group\"><strong>Movies (1)</strong> <a href=\"movie.php?movieid=429\">Le Bon</a> <span class=\"wp-mentions-year\">(1966)</span></p></div>");
f_check("mentions balanced",f_balanced($strhtml),true);
f_check("no mentions, nothing",f_wpmd_mentions(array(),"x",$arrlabels),"");

// Real crawler output
if (isset($argv[1])) {
	$arrrows=json_decode(file_get_contents($argv[1]),true);
	$arrrefs=array();
	foreach ($arrrows as $arrrow) {
		if ($arrrow[0]=="R") {
			$r=$arrrow[1];
			$arrrefs[$r["q"]][$r["lang"]][$r["section"]][]=array("LABEL"=>$r["label"],"URL"=>$r["url"],"TITLE"=>$r["title"],"REFERENCE_TEXT"=>$r["text"]);
		}
	}
	$strpreview="";
	$lngsections=0;
	foreach ($arrrows as $arrrow) {
		if ($arrrow[0]!="S" || $arrrow[1]["md"]===null) {
			continue;
		}
		$r=$arrrow[1];
		$lngsections++;
		$strhtml=f_wpmd_render($r["md"],$r["lang"],array());
		$strhtml.=f_wpmd_references($arrrefs[$r["q"]][$r["lang"]][$r["order"]] ?? array(),"Notes and references");
		f_check("sample ".$r["q"]." ".$r["lang"]." #".$r["order"]." balanced",f_balanced($strhtml),true);
		f_check("sample ".$r["q"]." ".$r["lang"]." #".$r["order"]." no Markdown link left",preg_match('/\]\([^)\s]+\)/',strip_tags($strhtml)),0);
		f_check("sample ".$r["q"]." ".$r["lang"]." #".$r["order"]." no table row left",preg_match('/^\| /m',strip_tags(str_replace(array("<p>","<br />","</td>","</li>"),"\n",$strhtml))),0);
		$strpreview.="<h3>".f_wpmd_escape($r["q"]." ".$r["lang"]." #".$r["order"]." ".$r["title"])."</h3><div class=\"wp-section\">".$strhtml."</div>\n";
	}
	echo $lngsections." sample sections rendered\n";
	if (isset($argv[2])) {
		//The current stylesheet, whatever its date: the file name changes at each release
		$arrcssfiles=glob(__DIR__."/../html/css/t2s-dark-*.css");
		sort($arrcssfiles);
		$strcss=($arrcssfiles ? file_get_contents(end($arrcssfiles)) : "");
		file_put_contents($argv[2],"<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><style>".$strcss."</style></head><body><div class=\"content-container\">".$strpreview."</div></body></html>");
	}
}

echo $lngchecks." checks, ".$lngfailures." failures\n";
exit($lngfailures ? 1 : 0);
