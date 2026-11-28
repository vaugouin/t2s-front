<?php
/*
 * Characterization tests for the side-effect-free reception helpers.
 * Run from the repository root: php test/reception-format-test.php
 */

require_once(__DIR__."/../lib/reception-format.inc.php");

$lngchecks=0;
$lngfailures=0;

function f_rccheck($strlabel, $strexpected, $stractual) {
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

function f_rcrow($strcode, $strname, $strnamefr, $strdetail="", $strevidence="") {
	return array("LABEL_CODE"=>$strcode, "LABEL_NAME"=>$strname, "LABEL_NAME_FR"=>$strnamefr, "DETAIL"=>$strdetail, "EVIDENCE"=>$strevidence);
}

// One label.
f_rccheck("name only", "Cult film", f_receptionlabelhtml(f_rcrow("cult", "Cult film", "Film culte"), "en"));
f_rccheck("French name", "Film culte", f_receptionlabelhtml(f_rcrow("cult", "Cult film", "Film culte"), "fr"));
f_rccheck("French falls back to English", "Cult film", f_receptionlabelhtml(f_rcrow("cult", "Cult film", ""), "fr"));
f_rccheck("no name, no label", "", f_receptionlabelhtml(f_rcrow("cult", "", ""), "en"));
f_rccheck("quoted sentence as tooltip, escaped",
	"<span class=\"receptionLabel\" title=\"“It became a &quot;cult&quot; film &lt;later&gt;.”\">Cult film</span>",
	f_receptionlabelhtml(f_rcrow("cult", "Cult film", "", "", "It became a \"cult\" film <later>."), "en"));
f_rccheck("detail in parentheses, escaped", "Banned (Ireland, Spain &amp; Portugal)",
	f_receptionlabelhtml(f_rcrow("banned", "Banned", "Interdit", "Ireland, Spain & Portugal"), "en"));
f_rccheck("detail 'other' says nothing", "Controversial", f_receptionlabelhtml(f_rcrow("controversial", "Controversial", "", "other"), "en"));

f_rccheck("label with an id links to its page, sentence kept as title",
	"<a class=\"receptionLabel\" href=\"reception.php?receptionid=10\" title=\"“It has a cult following.”\">Cult film</a>",
	f_receptionlabelhtml(array_merge(f_rcrow("cult", "Cult film", "", "", "It has a cult following."), array("ID_RECEPTION_LABEL"=>"10")), "en"));
f_rccheck("label with an id and no sentence: a plain link",
	"<a class=\"receptionLabel\" href=\"reception.php?receptionid=14\">Banned (Ireland)</a>",
	f_receptionlabelhtml(array_merge(f_rcrow("banned", "Banned (Ireland)", ""), array("ID_RECEPTION_LABEL"=>14)), "en"));

// The block of sentences, readable on a touch screen.
f_rccheck("no sentence, no block", "", f_receptionsentenceshtml(array(f_rcrow("cult", "Cult film", "")), "en", "Sentences"));
f_rccheck("one item per label with a sentence, escaped, French name",
	"<details class=\"receptionSentences\"><summary>Phrases</summary><ul><li><strong>Film culte</strong>: “A &lt;cult&gt; film.”</li></ul></details>",
	f_receptionsentenceshtml(array(f_rcrow("cult", "Cult film", "Film culte", "", "A <cult> film."), f_rcrow("cult", "Cult film", "Film culte", "", "Again."), f_rcrow("banned", "Banned", "Interdit")), "fr", "Phrases"));

// The line of a movie.
f_rccheck("no label, empty line", "", f_receptionlabelshtml(array(), "en"));
f_rccheck("labels in row order, a repeated code shown once", "Mixed reviews, Box office bomb",
	f_receptionlabelshtml(array(f_rcrow("critics_mixed", "Mixed reviews", ""), f_rcrow("box_office_bomb", "Box office bomb", ""), f_rcrow("critics_mixed", "Mixed reviews", "")), "en"));

// The audience grade.
f_rccheck("grade kept", "B+", f_receptioncinemascore(" b+ "));
f_rccheck("F has no sign", "", f_receptioncinemascore("F+"));
f_rccheck("not a grade", "", f_receptioncinemascore("E"));
f_rccheck("missing grade", "", f_receptioncinemascore(null));

echo "\n".$lngchecks." checks, ".$lngfailures." failure(s)\n";
exit($lngfailures>0 ? 1 : 0);
