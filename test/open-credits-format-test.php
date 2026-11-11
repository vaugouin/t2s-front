<?php
/*
 * open-credits-format-test.php - test for lib/open-credits-format.inc.php
 *
 * Run it from the repository root, no database, no web server and no network needed:
 *
 *     php test/open-credits-format-test.php
 *
 * Exit code 0 when every case passes, 1 otherwise.
 *
 * The rows decide what the reader shows as one credit (a name beside its role), so a wrong
 * grouping reads as a wrong credit.
 */

require_once(__DIR__."/../lib/open-credits-format.inc.php");

$lngchecks=0;
$lngfailures=0;

function f_check($strlabel,$varactual,$varexpected) {
	global $lngchecks,$lngfailures;
	$lngchecks++;
	if ($varactual!==$varexpected) {
		$lngfailures++;
		echo "FAIL ".$strlabel."\n  expected: ".var_export($varexpected,true)."\n  actual:   ".var_export($varactual,true)."\n";
	}
}

function f_texts($arrrows) {
	return array_map(function($r) { return implode(" | ",array_column($r,'TEXT')); },$arrrows);
}

function f_line($strtext,$lngy,$lngx,$lngfirst=0,$lngh=30) {
	return array('TEXT'=>$strtext,'Y_PAGE'=>$lngy,'X'=>$lngx,'H'=>$lngh,'FIRST_MS'=>$lngfirst);
}

f_check("time under an hour",f_ocmstime(245000),"4:05");
f_check("time over an hour",f_ocmstime(6723400),"1:52:03");
f_check("negative time",f_ocmstime(-5),"0:00");
f_check("Paris time in summer",f_ocparistime("2026-10-19 10:33:23"),"2026-10-19 12:33 +02:00");
f_check("Paris time in winter",f_ocparistime("2026-12-01 10:33:23"),"2026-12-01 11:33 +01:00");
f_check("no time",f_ocparistime(null),"");

// rolling: a role and its name side by side, a few pixels apart in height, read as one row, left first
$arr=array(f_line("Ridley Scott",1012,900),f_line("Directed by",1000,200),f_line("Produced by",1100,200),f_line("Gordon Carroll",1104,900));
f_check("rolling rows",f_texts(f_ocrows($arr,"rolling")),array("Directed by | Ridley Scott","Produced by | Gordon Carroll"));

// rows half a line height apart or more are two rows
$arr=array(f_line("A",1000,200),f_line("B",1015,200));
f_check("half a line apart",f_texts(f_ocrows($arr,"rolling")),array("A","B"));

// cards: the same height on two cards is two rows, the cards in time order
$arr=array(f_line("Music",500,200,61000),f_line("Jerry Goldsmith",505,900,61500),f_line("Editor",500,200,50000),f_line("Terry Rawlings",500,900,50000));
f_check("cards rows",f_texts(f_ocrows($arr,"cards")),array("Editor | Terry Rawlings","Music | Jerry Goldsmith"));

f_check("no lines",f_ocrows(array(),"cards"),array());

f_check("page url",f_ocpageurl(2,""),"open-credits.php?page=2");
f_check("page url with filter",f_ocpageurl(0,"Gold Rush & co"),"open-credits.php?page=1&q=Gold%20Rush%20%26%20co");
f_check("one page, no links",f_ocpagination(1,1,"","Previous","Next"),"");
f_check("pages around the current one",
	strip_tags(f_ocpagination(5,10,"","Previous","Next")),"Previous 1 … 3 4 5 6 7 … 10 Next");
f_check("first page",strip_tags(f_ocpagination(1,3,"","Previous","Next")),"1 2 3 Next");
f_check("filter kept in links",strpos(f_ocpagination(1,2,"Kid","P","N"),"q=Kid")!==false,true);

echo $lngchecks." checks, ".$lngfailures." failures\n";
exit($lngfailures?1:0);
