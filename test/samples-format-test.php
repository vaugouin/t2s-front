<?php
/*
 * samples-format-test.php - test for lib/samples-format.inc.php
 *
 * Run it from the repository root, no database and no web server needed:
 *
 *     php test/samples-format-test.php
 *
 * Exit code 0 when every case passes, 1 otherwise.
 *
 * samples.php exists to predict what the voice-agent showcase does with each /samples entry,
 * so the cases below pin the rules it copies from voice-agent (cardSpecFromRecord and
 * buildShowcaseGroup in app/static/app.js): which detail page a row links to, which image it
 * uses, the eight-card slice taken BEFORE image-less cards are skipped, and the classification
 * that tells a sample the showcase can draw from one it never will.
 */

require_once(__DIR__."/../lib/samples-format.inc.php");

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

function f_group($strtitle) {
	echo "\n".$strtitle."\n";
}

$arrbases=array("poster"=>"https://img/w342","profile"=>"https://img/w185","logo"=>"https://img/logo");

f_group("Flattening the category tree");
$arrtree=array(
	array("DESCRIPTION"=>"Movies","samples"=>array(array("ID_T2S_EVALUATION"=>1)),"categories"=>array(
		array("DESCRIPTION"=>"Directors","samples"=>array(array("ID_T2S_EVALUATION"=>2),array("ID_T2S_EVALUATION"=>3)),"categories"=>array()),
	)),
	array("DESCRIPTION"=>"Persons","samples"=>array(array("ID_T2S_EVALUATION"=>4)),"categories"=>array()),
);
$arrflat=f_samplesflatten($arrtree);
f_check("four samples",count($arrflat),4);
f_check("API order kept",array_map(function($a){return $a["sample"]["ID_T2S_EVALUATION"];},$arrflat),array(1,2,3,4));
f_check("path of a nested sample",$arrflat[1]["path"],array("Movies","Directors"));
f_check("a non-array tree gives nothing",f_samplesflatten(null),array());
$arrflat=f_samplesflatten(array(array("DESCRIPTION"=>"Budget &amp; Revenue Queries","samples"=>array(array("ID_T2S_EVALUATION"=>9)))));
f_check("stored HTML entities decoded once",$arrflat[0]["path"],array("Budget & Revenue Queries"));

f_group("Image URLs");
f_check("TMDb path",f_samplesimageurl("/abc.jpg","https://img/w342"),"https://img/w342/abc.jpg");
f_check("absolute URL kept",f_samplesimageurl("https://upload.wikimedia.org/x.jpg","https://img/w342"),"https://upload.wikimedia.org/x.jpg");
f_check("protocol-relative URL",f_samplesimageurl("//upload.wikimedia.org/x.jpg","https://img/w342"),"https://upload.wikimedia.org/x.jpg");
f_check("empty path",f_samplesimageurl("  ","https://img/w342"),"");
f_check("null path",f_samplesimageurl(null,"https://img/w342"),"");

f_group("Card specs: link and image per entity");
$arrspec=f_samplescardspec(array("ID_MOVIE"=>603,"MOVIE_TITLE"=>"The Matrix","DAT_RELEASE"=>"1999-03-31","POSTER_PATH"=>"/m.jpg"),$arrbases);
f_check("movie url",$arrspec["url"],"movie.php?movieid=603");
f_check("movie image",$arrspec["image"],"https://img/w342/m.jpg");
f_check("movie year",$arrspec["subtitle"],"1999");
$arrspec=f_samplescardspec(array("ID_MOVIE"=>1,"MOVIE_TITLE"=>"X","DAT_FIRST_AIR"=>"2001-01-01"),$arrbases);
f_check("movie of a mixed union takes DAT_FIRST_AIR",$arrspec["subtitle"],"2001");
$arrspec=f_samplescardspec(array("ID_SERIE"=>1399,"SERIE_TITLE"=>"Game of Thrones","DAT_FIRST_AIR"=>"2011-04-17","DAT_LAST_AIR"=>"2019-05-19","POSTER_PATH"=>"/s.jpg","MEDIA_TYPE"=>"serie"),$arrbases);
f_check("serie url",$arrspec["url"],"serie.php?serieid=1399");
f_check("serie years",$arrspec["subtitle"],"2011-2019");
$arrspec=f_samplescardspec(array("ID_PERSON"=>287,"PERSON_NAME"=>"Brad Pitt","PROFILE_PATH"=>"/p.jpg"),$arrbases);
f_check("person url",$arrspec["url"],"person.php?personid=287");
f_check("person image uses the profile base",$arrspec["image"],"https://img/w185/p.jpg");
$arrspec=f_samplescardspec(array("ID_COMPANY"=>41077,"COMPANY_NAME"=>"A24","LOGO_PATH"=>"/l.png"),$arrbases);
f_check("company url",$arrspec["url"],"company.php?companyid=41077");
f_check("company logo without synthetic",$arrspec["image"],"https://img/logo/l.png");
$fnsynthetic=function($strclass,$lngid){ return $strclass=="network" && $lngid==49 ? "/synthetic-images/n49.webp" : ""; };
$arrspec=f_samplescardspec(array("ID_NETWORK"=>49,"NETWORK_NAME"=>"HBO","LOGO_PATH"=>"/h.png"),$arrbases,$fnsynthetic);
f_check("network url",$arrspec["url"],"network.php?networkid=49");
f_check("network prefers the synthetic master",$arrspec["image"],"/synthetic-images/n49.webp");
$arrsecondary=array(
	array(array("ID_TOPIC"=>5,"TOPIC_NAME"=>"Heist"),"t2stopic.php?topicid=5"),
	array(array("ID_T2S_LIST"=>6,"LIST_NAME"=>"AFI 100"),"t2slist.php?listid=6"),
	array(array("ID_T2S_COLLECTION"=>7,"COLLECTION_NAME"=>"MCU"),"t2scollection.php?collectionid=7"),
	array(array("ID_LOCATION"=>8,"LOCATION_NAME"=>"Paris"),"location.php?locationid=8"),
	array(array("ID_MOVEMENT"=>9,"MOVEMENT_NAME"=>"Nouvelle Vague"),"movement.php?movementid=9"),
	array(array("ID_AWARD"=>10,"AWARD_NAME"=>"Oscar"),"award.php?awardid=10"),
	array(array("ID_NOMINATION"=>11,"NOMINATION_NAME"=>"Best Picture"),"nomination.php?nominationid=11"),
	array(array("ID_GROUP"=>12,"GROUP_NAME"=>"Monty Python"),"group.php?groupid=12"),
	array(array("ID_DEATH"=>13,"DEATH_NAME"=>"Cancer"),"death.php?deathid=13"),
	array(array("ID_TECHNICAL"=>14,"DESCRIPTION"=>"IMAX"),"technical.php?technicalid=14"),
);
foreach ($arrsecondary as $arrcase) {
	$arrspec=f_samplescardspec($arrcase[0],$arrbases);
	f_check("secondary url ".$arrcase[1],$arrspec["url"],$arrcase[1]);
}
$arrspec=f_samplescardspec(array("ID_TOPIC"=>5,"TOPIC_NAME"=>"Heist","POSTER_PATH"=>"","WIKIPEDIA_IMAGE_PATH"=>"https://upload.wikimedia.org/h.jpg"),$arrbases);
f_check("secondary falls back to the Wikipedia image",$arrspec["image"],"https://upload.wikimedia.org/h.jpg");
$arrspec=f_samplescardspec(array("ID_TOPIC"=>5,"TOPIC_NAME"=>"Heist","POSTER_PATH"=>"/t.jpg","WIKIPEDIA_IMAGE_PATH"=>"https://upload.wikimedia.org/h.jpg"),$arrbases);
f_check("secondary prefers the TMDb poster",$arrspec["image"],"https://img/w342/t.jpg");
f_check("unknown row gives null",f_samplescardspec(array("ID_SOMETHING"=>1,"NAME"=>"?"),$arrbases),null);
f_check("non-array row gives null",f_samplescardspec("x",$arrbases),null);
$arrspec=f_samplescardspec(array("ID_MOVIE"=>"1&2"),$arrbases);
f_check("id is URL-encoded",$arrspec["url"],"movie.php?movieid=1%262");

f_group("Showcase count: eight rows sliced first, image-less skipped after");
$arrwith=array("image"=>"x");
$arrwithout=array("image"=>"");
f_check("ten imaged rows give eight",f_samplesshowcasecount(array_fill(0,10,$arrwith)),8);
f_check("an image-less row inside the eight is a lost slot",f_samplesshowcasecount(array_merge(array($arrwithout),array_fill(0,9,$arrwith))),7);
f_check("unknown rows count for nothing",f_samplesshowcasecount(array(null,$arrwith)),1);
f_check("no rows",f_samplesshowcasecount(array()),0);

f_group("Status");
$arrassert=array("raw"=>"ID_MOVIE IN (1)","result_kind"=>"entity_rows");
f_check("no assertion",f_samplesstatus(array("assertion"=>null,"simulated_result"=>null),0),"noassert");
f_check("count assertion",f_samplesstatus(array("assertion"=>$arrassert,"simulated_result"=>array("result_kind"=>"count","result"=>array())),0),"nopreview");
f_check("unparsed assertion",f_samplesstatus(array("assertion"=>$arrassert,"simulated_result"=>null),0),"nopreview");
f_check("scalar with a value",f_samplesstatus(array("assertion"=>$arrassert,"simulated_result"=>array("result_kind"=>"scalar","result"=>array(array("index"=>0,"data"=>array("V"=>3))))),0),"shown");
f_check("entity rows, none hydrated",f_samplesstatus(array("assertion"=>$arrassert,"simulated_result"=>array("result_kind"=>"entity_rows","result"=>array())),0),"empty");
f_check("entity rows, no image",f_samplesstatus(array("assertion"=>$arrassert,"simulated_result"=>array("result_kind"=>"entity_rows","result"=>array(array("data"=>array())))),0),"noimage");
f_check("entity rows, shown",f_samplesstatus(array("assertion"=>$arrassert,"simulated_result"=>array("result_kind"=>"entity_rows","result"=>array(array("data"=>array())))),3),"shown");

f_group("Scalar value");
f_check("first cell",f_samplesscalarvalue(array("simulated_result"=>array("result"=>array(array("index"=>0,"data"=>array("COUNT"=>42,"X"=>1)))))),"42");
f_check("boolean",f_samplesscalarvalue(array("simulated_result"=>array("result"=>array(array("data"=>array("B"=>false)))))),"false");
f_check("nothing",f_samplesscalarvalue(array("simulated_result"=>null)),"");

f_group("Card HTML escaping");
$strhtml=f_samplescardhtml(array("kind"=>"movie","id"=>"1","title"=>"Tom & \"Jerry\" <b>","subtitle"=>"1999","url"=>"movie.php?movieid=1&x=\"","image"=>"https://img/a\".jpg"),true,"No image");
f_check("no raw tag",strpos($strhtml,"<b>"),false);
f_check("title escaped",strpos($strhtml,"Tom &amp; &quot;Jerry&quot; &lt;b&gt;")!==false,true);
f_check("href escaped",strpos($strhtml,"href=\"movie.php?movieid=1&amp;x=&quot;\"")!==false,true);
f_check("image src escaped",strpos($strhtml,"src=\"https://img/a&quot;.jpg\"")!==false,true);
f_check("in-showcase card not dimmed",strpos($strhtml,"samples-card-out"),false);
$strhtml=f_samplescardhtml(array("kind"=>"person","id"=>"2","title"=>"P","subtitle"=>"","url"=>"","image"=>""),false,"No <image>");
f_check("out-of-showcase card dimmed",strpos($strhtml,"samples-card-out")!==false,true);
f_check("no link without url",strpos($strhtml,"<a "),false);
f_check("placeholder label escaped",strpos($strhtml,"No &lt;image&gt;")!==false,true);
f_check("kind shown when no subtitle",strpos($strhtml,">person<")!==false,true);

f_group("Display order");
f_check("default order","api",f_samplesorder(""));
f_check("id ascending accepted","idasc",f_samplesorder(" IdAsc "));
f_check("unknown order falls back to the API order","api",f_samplesorder("idasc;DROP"));
$arrsorttree=array(
	array("DESCRIPTION"=>"B","samples"=>array(array("ID_T2S_EVALUATION"=>30),array("ID_T2S_EVALUATION"=>5)),"categories"=>array(
		array("DESCRIPTION"=>"B1","samples"=>array(array("ID_T2S_EVALUATION"=>12))),
	)),
	array("DESCRIPTION"=>"A","samples"=>array(array("ID_T2S_EVALUATION"=>7))),
);
$arrsortflat=f_samplesflatten($arrsorttree);
$fnids=function($arrflat) {
	return array_map(function($arritem) { return $arritem["sample"]["ID_T2S_EVALUATION"]; }, $arrflat);
};
f_check("api order untouched",$fnids(f_samplessort($arrsortflat,"api")),array(30,5,12,7));
f_check("id ascending",$fnids(f_samplessort($arrsortflat,"idasc")),array(5,7,12,30));
$arrsorted=f_samplessort($arrsortflat,"idasc");
f_check("sorted entry keeps its category path",$arrsorted[2]["path"],array("B","B1"));
f_check("id descending accepted","iddesc",f_samplesorder("iddesc"));
f_check("id descending",$fnids(f_samplessort($arrsortflat,"iddesc")),array(30,12,7,5));
$arrstatusflat=$arrsortflat;
foreach (array("shown","noimage","noassert","shown") as $lngindex=>$strstatus) {
	$arrstatusflat[$lngindex]["status"]=$strstatus;
}
f_check("status order, problems first, ties keep the API order",$fnids(f_samplessort($arrstatusflat,"status")),array(12,5,30,7));
$arrstatusflat[1]["status"]="unheard-of";
f_check("unknown status sorts last",$fnids(f_samplessort($arrstatusflat,"status")),array(12,30,7,5));
$arrquestionflat=$arrsortflat;
foreach (array("zebra","Apple","école","banana") as $lngindex=>$strquestion) {
	$arrquestionflat[$lngindex]["sample"]["QUESTION"]=$strquestion;
}
$arrexpected=class_exists('Normalizer') ? array(5,7,12,30) : array(5,7,30,12);
f_check("question order ignores case (and accents with intl)",$fnids(f_samplessort($arrquestionflat,"question")),$arrexpected);
f_check("question order leaves no helper key",array_key_exists("_questionkey",f_samplessort($arrquestionflat,"question")[0]),false);
f_check("every order has its label pair in the drop-down list",SAMPLES_ORDERS,array("api","idasc","iddesc","status","question"));

echo "\n".$lngchecks." checks, ".$lngfailures." failure(s)\n";
exit($lngfailures>0 ? 1 : 0);
?>
