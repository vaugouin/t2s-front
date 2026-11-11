<?php
/*
 * social-ids-test.php - test for lib/social-ids.inc.php
 *
 * Run it from the repository root, no database, no web server and no network needed:
 *
 *     php test/social-ids-test.php
 *
 * Exit code 0 when every case passes, 1 otherwise.
 *
 * The line must keep four states apart: columns absent (nothing printed), never read,
 * read without any id, read with ids. It must also never print an id unescaped.
 */

require_once(__DIR__."/../lib/social-ids.inc.php");

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

function f_contains($strlabel,$strhaystack,$strneedle,$intexpected=true) {
	f_check($strlabel,strpos($strhaystack,$strneedle)!==false,$intexpected);
}

$arrlabels=array("title"=>"Social networks","unread"=>"not read yet","none"=>"none on TMDb","read"=>"read");

echo "\nStates\n";
$arrabsent=f_socialidsfromrow(array("ID_PERSON"=>287,"NAME"=>"Brad Pitt"));
f_check("columns absent",$arrabsent["state"],"absent");
f_check("columns absent print nothing",f_socialidshtml($arrabsent,$arrlabels),"");
f_check("not an array",f_socialidsfromrow(false)["state"],"absent");

$arrunread=f_socialidsfromrow(array("TIM_EXTERNAL_IDS"=>null,"FACEBOOK_ID"=>null,"INSTAGRAM_ID"=>null,"TWITTER_ID"=>null));
f_check("never read",$arrunread["state"],"unread");
f_contains("never read is said",f_socialidshtml($arrunread,$arrlabels),"<em>not read yet</em>");

$arrnone=f_socialidsfromrow(array("TIM_EXTERNAL_IDS"=>"2026-11-11 21:04:05","FACEBOOK_ID"=>"","INSTAGRAM_ID"=>"","TWITTER_ID"=>""));
f_check("read, none",$arrnone["state"],"read");
f_check("read, none: no ids",$arrnone["ids"],array());
$strnone=f_socialidshtml($arrnone,$arrlabels);
f_contains("read, none is said",$strnone,"<em>none on TMDb</em>");
f_contains("read, none carries the read date",$strnone,"(read 2026-11-11)");
f_contains("read, none is not 'not read'",$strnone,"not read yet",false);

$arrperson=f_socialidsfromrow(array("TIM_EXTERNAL_IDS"=>"2026-11-11 21:04:05","FACEBOOK_ID"=>"","INSTAGRAM_ID"=>"bradpitt","TWITTER_ID"=>"@BradPitt","TIKTOK_ID"=>" ","YOUTUBE_ID"=>"UCabcdefghijklmnopqrstuv"));
f_check("read with ids",$arrperson["state"],"read");
f_check("only non-empty ids kept, in network order",array_keys($arrperson["ids"]),array("INSTAGRAM_ID","TWITTER_ID","YOUTUBE_ID"));
$strperson=f_socialidshtml($arrperson,$arrlabels);
f_contains("Instagram link",$strperson,"href=\"https://www.instagram.com/bradpitt/\"");
f_contains("X link drops the @",$strperson,"href=\"https://x.com/BradPitt\"");
f_contains("X shows the stored value",$strperson,"X: @BradPitt</a>");
f_contains("links separated",$strperson," · ");
f_contains("no Facebook for an empty id",$strperson,"Facebook",false);

echo "\nURLs\n";
f_check("facebook",f_socialidurl("FACEBOOK_ID","FightClub"),"https://www.facebook.com/FightClub");
f_check("facebook with a slash",f_socialidurl("FACEBOOK_ID","pages/Fight Club/123"),"https://www.facebook.com/pages/Fight%20Club/123");
f_check("tiktok",f_socialidurl("TIKTOK_ID","@someone"),"https://www.tiktok.com/@someone");
f_check("youtube channel id",f_socialidurl("YOUTUBE_ID","UCabcdefghijklmnopqrstuv"),"https://www.youtube.com/channel/UCabcdefghijklmnopqrstuv");
f_check("youtube handle",f_socialidurl("YOUTUBE_ID","someone"),"https://www.youtube.com/@someone");
f_check("full https URL kept",f_socialidurl("YOUTUBE_ID","https://www.youtube.com/user/someone"),"https://www.youtube.com/user/someone");
f_check("plain http refused",f_socialidurl("FACEBOOK_ID","http://example.com/x"),"");
f_check("unknown column",f_socialidurl("MYSPACE_ID","someone"),"");
f_check("lone @",f_socialidurl("TWITTER_ID","@"),"");

echo "\nEscaping\n";
$arrevil=f_socialidsfromrow(array("TIM_EXTERNAL_IDS"=>"2026-11-11 21:04:05","INSTAGRAM_ID"=>"a\"><script>x</script>"));
$strevil=f_socialidshtml($arrevil,$arrlabels);
f_contains("no raw script tag",$strevil,"<script>",false);
f_contains("value escaped in the text",$strevil,"&lt;script&gt;");
$arrevilurl=f_socialidsfromrow(array("TIM_EXTERNAL_IDS"=>"2026-11-11 21:04:05","FACEBOOK_ID"=>"javascript:alert(1)"));
f_contains("no javascript: URL",f_socialidshtml($arrevilurl,$arrlabels),"href=\"javascript:",false);

echo "\n".$lngchecks." checks, ".$lngfailures." failure(s)\n";
exit($lngfailures>0 ? 1 : 0);
