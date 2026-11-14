<?php
/*
 * Characterization tests for the side-effect-free award helpers.
 * Run from the repository root: php test/award-format-test.php
 */

require_once(__DIR__."/../lib/award-format.inc.php");

$lngchecks=0;
$lngfailures=0;

function f_awcheck($strlabel, $strexpected, $stractual) {
	global $lngchecks, $lngfailures;
	$lngchecks++;
	if ($strexpected===$stractual) {
		echo "ok    ".$strlabel."\n";
		return;
	}
	$lngfailures++;
	echo "FAIL  ".$strlabel."\n";
	echo "      expected: ".var_export($strexpected, true)."\n";
	echo "      actual:   ".var_export($stractual, true)."\n";
}

function f_awwin($stryear, $lngmovie=0, $strmovie="", $strmoviefr="", $lngserie=0, $strserie="", $strseriefr="") {
	return array("AWARD_YEAR"=>$stryear, "ID_MOVIE_FOR"=>$lngmovie, "MOVIE_TITLE"=>$strmovie, "MOVIE_TITLE_FR"=>$strmoviefr, "ID_SERIE_FOR"=>$lngserie, "SERIE_TITLE"=>$strserie, "SERIE_TITLE_FR"=>$strseriefr);
}

// The year of a win.
f_awcheck("year read", 1982, f_awardyear("1982"));
f_awcheck("year as integer", 1982, f_awardyear(1982));
f_awcheck("null year", 0, f_awardyear(null));
f_awcheck("empty year", 0, f_awardyear(""));
f_awcheck("not a year", 0, f_awardyear("82"));

// Grouping keeps the order of the rows and of the first appearance of each key.
$arrgroups=f_awardwinsbykey(array(
	array("ID_AWARD"=>269, "AWARD_YEAR"=>1934),
	array("ID_AWARD"=>12, "AWARD_YEAR"=>1958),
	array("ID_AWARD"=>269, "AWARD_YEAR"=>1968),
	array("ID_AWARD"=>"", "AWARD_YEAR"=>1999),
	array("AWARD_YEAR"=>2000)
), "ID_AWARD");
f_awcheck("groups in order of first appearance", "269,12", implode(",", array_keys($arrgroups)));
f_awcheck("rows of a group in order", "1934,1968", implode(",", array_column($arrgroups["269"], "AWARD_YEAR")));
f_awcheck("rows without a key skipped", 2, count($arrgroups));
f_awcheck("no rows, no groups", array(), f_awardwinsbykey(null, "ID_AWARD"));

// The work of a win.
f_awcheck("movie in English", array("url"=>"movie.php?movieid=31777", "title"=>"Morning Glory"), f_awardworkofwin(f_awwin(1934, 31777, "Morning Glory", "Matin de gloire"), "en"));
f_awcheck("movie in French", array("url"=>"movie.php?movieid=31777", "title"=>"Matin de gloire"), f_awardworkofwin(f_awwin(1934, 31777, "Morning Glory", "Matin de gloire"), "fr"));
f_awcheck("French falls back to the main title", array("url"=>"movie.php?movieid=31777", "title"=>"Morning Glory"), f_awardworkofwin(f_awwin(1934, 31777, "Morning Glory", ""), "fr"));
f_awcheck("series when there is no movie", array("url"=>"serie.php?serieid=688", "title"=>"The West Wing"), f_awardworkofwin(f_awwin(2001, 0, "", "", 688, "The West Wing"), "en"));
f_awcheck("movie before series", "movie.php?movieid=5", f_awardworkofwin(f_awwin(2001, 5, "A", "", 688, "B"), "en")["url"]);
f_awcheck("a work without a title is no work", array(), f_awardworkofwin(f_awwin(2001, 5, "", ""), "en"));
f_awcheck("no work", array(), f_awardworkofwin(array("AWARD_YEAR"=>2001), "en"));

// The wins of a pair.
$arrhepburn=array(
	f_awwin(1982, 11816, "On Golden Pond"),
	f_awwin(1934, 31777, "Morning Glory", "Matin de gloire"),
	f_awwin(1968, 1879, "Guess Who's Coming to Dinner"),
	f_awwin(1969, 18988, "The Lion in Winter")
);
f_awcheck("four wins, ascending, each with its film",
	"1934 for <a href=\"movie.php?movieid=31777\">Morning Glory</a>, 1968 for <a href=\"movie.php?movieid=1879\">Guess Who&#039;s Coming to Dinner</a>, 1969 for <a href=\"movie.php?movieid=18988\">The Lion in Winter</a>, 1982 for <a href=\"movie.php?movieid=11816\">On Golden Pond</a>",
	f_awardwinshtml($arrhepburn, "en", "for %s"));
f_awcheck("French label and title", "1934 pour <a href=\"movie.php?movieid=31777\">Matin de gloire</a>", f_awardwinshtml(array($arrhepburn[1]), "fr", "pour %s"));
f_awcheck("years only (a movie or a series as winner)", "1985, 1990", f_awardwinshtml(array(array("AWARD_YEAR"=>1990), array("AWARD_YEAR"=>1985)), "en"));
f_awcheck("a repeated year is written once", "1990", f_awardwinshtml(array(array("AWARD_YEAR"=>1990), array("AWARD_YEAR"=>"1990")), "en"));
f_awcheck("same year, two works: both kept", "1990 for <a href=\"movie.php?movieid=1\">A</a>, 1990 for <a href=\"movie.php?movieid=2\">B</a>", f_awardwinshtml(array(f_awwin(1990, 1, "A"), f_awwin(1990, 2, "B")), "en"));
f_awcheck("unknown year with a work goes last", "1990, for <a href=\"movie.php?movieid=2\">B</a>", f_awardwinshtml(array(f_awwin(null, 2, "B"), f_awwin(1990)), "en"));
f_awcheck("nothing known, nothing written", "", f_awardwinshtml(array(f_awwin(null), f_awwin("")), "en"));
f_awcheck("no win", "", f_awardwinshtml(array(), "en"));
f_awcheck("title escaped", "2001 for <a href=\"movie.php?movieid=7\">&lt;b&gt;Tom &amp; Jerry&lt;/b&gt;</a>", f_awardwinshtml(array(f_awwin(2001, 7, "<b>Tom & Jerry</b>")), "en"));
f_awcheck("label escaped", "2001 &lt;for&gt; <a href=\"movie.php?movieid=7\">T</a>", f_awardwinshtml(array(f_awwin(2001, 7, "T")), "en", "<for> %s"));

// The caption under a winner.
f_awcheck("caption", "<small>Won: 1985, 1990</small>", f_awardwinscaption(array(array("AWARD_YEAR"=>1990), array("AWARD_YEAR"=>1985)), "en", "Won: %s", "for %s"));
f_awcheck("French caption", "<small>Remporté : 1934 pour <a href=\"movie.php?movieid=31777\">Matin de gloire</a></small>", f_awardwinscaption(array($arrhepburn[1]), "fr", "Remporté : %s", "pour %s"));
f_awcheck("no caption when nothing is known", "", f_awardwinscaption(array(f_awwin(null)), "en"));

echo "\n".$lngchecks." checks, ".$lngfailures." failure(s)\n";
exit($lngfailures>0?1:0);
