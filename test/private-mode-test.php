<?php
/*
 * private-mode-test.php - test for lib/private-mode.inc.php
 *
 * Run it from the repository root, no database, no web server and no network needed:
 *
 *     php test/private-mode-test.php
 *
 * Exit code 0 when every case passes, 1 otherwise.
 *
 * The URL prefixes are configuration (T2S_URL_PREFIXES): no folder name is written in the code.
 * These cases pin five things: how the prefix list is read, which prefix a path belongs to, what
 * a page learns from its prefix (private mode, language, language switch), that the guard stops a
 * public request with HTTP 403 before anything else runs, and that the read-write database
 * account is only ever asked for behind that guard.
 *
 * The map used here is an example. The names in it mean nothing to the code.
 */

require_once(__DIR__."/../lib/private-mode.inc.php");

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

$strconfig="/en/:en,/fr/:fr,/staff/:en:private,/equipe/:fr:private";

echo "\nReading the prefix list\n";
$arrmap=f_prefixparse($strconfig,"en");
f_check("four entries",count($arrmap),4);
f_check("first entry",$arrmap[0],array("prefix"=>"/en/","lang"=>"en","private"=>0));
f_check("private entry",$arrmap[2],array("prefix"=>"/staff/","lang"=>"en","private"=>1));
f_check("slashes are optional in the configuration",f_prefixparse("en:en, fr/:fr ,/staff:en:private","en"),f_prefixparse("/en/:en,/fr/:fr,/staff/:en:private","en"));
f_check("empty configuration, empty map",f_prefixparse("","en"),array());
f_check("unknown language falls back to the default",f_prefixparse("/x/:de","fr"),array(array("prefix"=>"/x/","lang"=>"fr","private"=>0)));
f_check("no language falls back to the default",f_prefixparse("/x/","fr"),array(array("prefix"=>"/x/","lang"=>"fr","private"=>0)));
f_check("a prefix listed twice keeps its first entry",f_prefixparse("/x/:en,/x/:fr:private","en"),array(array("prefix"=>"/x/","lang"=>"en","private"=>0)));
f_check("an entry without a usable prefix is skipped",f_prefixparse(":en:private,/<x>/:en:private,/ok/:fr","en"),array(array("prefix"=>"/ok/","lang"=>"fr","private"=>0)));
f_check("private is a word, not a position",f_prefixparse("/x/:en:other:private","en"),array(array("prefix"=>"/x/","lang"=>"en","private"=>1)));
f_check("anything else than the word private is public",f_prefixparse("/x/:en:1","en"),array(array("prefix"=>"/x/","lang"=>"en","private"=>0)));
f_check("default language: a known one is kept",f_prefixdefaultlang("fr"),"fr");
f_check("default language: an unknown one becomes the first known",f_prefixdefaultlang("de"),"en");

echo "\nFolder test\n";
f_check("page under the folder",f_pathinfolder("/staff/movie.php","/staff/"),true);
f_check("page under another folder",f_pathinfolder("/fr/movie.php","/staff/"),false);
f_check("folder name inside the path, not at its start",f_pathinfolder("/fr/staff/movie.php","/staff/"),false);
f_check("/en-gb/ is not /en/",f_pathinfolder("/en-gb/movie.php","/en/"),false);
f_check("case sensitive",f_pathinfolder("/STAFF/movie.php","/staff/"),false);
f_check("empty folder never matches",f_pathinfolder("/staff/movie.php",""),false);
f_check("empty path",f_pathinfolder("","/staff/"),false);

echo "\nPrivate and public paths\n";
f_check("/staff/ is private",f_privatemodepath("/staff/movie.php",$arrmap),1);
f_check("/equipe/ is private",f_privatemodepath("/equipe/movie.php",$arrmap),1);
f_check("/fr/ is public",f_privatemodepath("/fr/movie.php",$arrmap),0);
f_check("/en/ is public",f_privatemodepath("/en/movie.php",$arrmap),0);
f_check("unknown folder is public",f_privatemodepath("/other/movie.php",$arrmap),0);
f_check("site root is public",f_privatemodepath("/movie.php",$arrmap),0);
f_check("empty path is public",f_privatemodepath("",$arrmap),0);
f_check("path info after a public script is public",f_privatemodepath("/fr/movie.php/staff/",$arrmap),0);
f_check("relative path is public",f_privatemodepath("staff/movie.php",$arrmap),0);
f_check("with an empty map everything is public",f_privatemodepath("/staff/movie.php",array()),0);
$arrnested=f_prefixparse("/a/:en,/a/b/:fr:private","en");
f_check("the longest listed prefix wins",f_privatemodepath("/a/b/movie.php",$arrnested),1);
f_check("the shorter prefix keeps its own pages",f_privatemodepath("/a/movie.php",$arrnested),0);

echo "\nWhat a page learns from its prefix\n";
$arrstate=f_prefixstate("/fr/movie.php","/fr/movie.php?movieid=11",$arrmap,"en");
f_check("public French page",$arrstate,array("prefix"=>"/fr/","private"=>0,"lang"=>"fr","langalt"=>"en","langalturl"=>"/en/movie.php?movieid=11"));
$arrstate=f_prefixstate("/en/movie.php","/en/movie.php?movieid=11",$arrmap,"en");
f_check("public English page switches to French",$arrstate["langalturl"],"/fr/movie.php?movieid=11");
$arrstate=f_prefixstate("/staff/movie.php","/staff/movie.php?movieid=11",$arrmap,"en");
f_check("private page is private",$arrstate["private"],1);
f_check("private page has its language",$arrstate["lang"],"en");
f_check("the switch of a private page leads to a PUBLIC prefix, never to a private one",$arrstate["langalturl"],"/fr/movie.php?movieid=11");
$arrstate=f_prefixstate("/other/movie.php","/other/movie.php",$arrmap,"fr");
f_check("unlisted prefix: public, default language, no switch",$arrstate,array("prefix"=>"","private"=>0,"lang"=>"fr","langalt"=>"","langalturl"=>""));
$arrstate=f_prefixstate("/en/movie.php","/en/movie.php",f_prefixparse("/en/:en,/staff/:fr:private","en"),"en");
f_check("no public prefix in another language, no switch",array($arrstate["langalt"],$arrstate["langalturl"]),array("",""));

echo "\nCurrent request\n";
f_prefixmap($arrmap);
$strphpselfbackup=isset($_SERVER['PHP_SELF'])?$_SERVER['PHP_SELF']:null;
$_SERVER['PHP_SELF']="/staff/movie.php";
f_check("private request",f_privatemoderequest(),1);
$_SERVER['PHP_SELF']="/fr/movie.php";
f_check("public request",f_privatemoderequest(),0);
unset($_SERVER['PHP_SELF']);
f_check("no PHP_SELF at all is public",f_privatemoderequest(),0);
if ($strphpselfbackup!==null) {
	$_SERVER['PHP_SELF']=$strphpselfbackup;
}

echo "\nThe guard, run in a real PHP process\n";
//f_privatemoderequire() exits, so it is run in a child process, which reads the prefix list from
//its environment exactly as a deployed page does. The child prints a marker after the call: the
//marker must be there for a private path and absent for a public one.
function f_runguard($strphpself,$strconfig) {
	$strcode='putenv('.var_export("T2S_URL_PREFIXES=".$strconfig,true).');';
	$strcode.='$_ENV["T2S_URL_PREFIXES"]='.var_export($strconfig,true).';';
	$strcode.='$_SERVER["T2S_URL_PREFIXES"]='.var_export($strconfig,true).';';
	$strcode.='$_SERVER["PHP_SELF"]='.var_export($strphpself,true).';';
	$strcode.='require '.var_export(__DIR__."/../lib/private-mode.inc.php",true).';';
	$strcode.='f_privatemoderequire();';
	$strcode.='echo "MARKER-AFTER-GUARD code=".var_export(http_response_code(),true);';
	$strfile=tempnam(sys_get_temp_dir(),"pmt");
	file_put_contents($strfile,"<?php ".$strcode);
	$arroutput=array();
	$lngexit=0;
	exec(escapeshellarg(PHP_BINARY)." ".escapeshellarg($strfile)." 2>&1",$arroutput,$lngexit);
	@unlink($strfile);
	return implode("\n",$arroutput);
}
$stroutprivate=f_runguard("/staff/x.php",$strconfig);
f_check("private path goes through",strpos($stroutprivate,"MARKER-AFTER-GUARD")!==false,true);
f_check("private path prints no refusal",strpos($stroutprivate,"403")===false,true);
$stroutprivate=f_runguard("/equipe/x.php",$strconfig);
f_check("second private path goes through",strpos($stroutprivate,"MARKER-AFTER-GUARD")!==false,true);
foreach (array("/fr/x.php","/en/x.php","/elsewhere/x.php","") as $strpublicpath) {
	$stroutpublic=f_runguard($strpublicpath,$strconfig);
	f_check("public path '".$strpublicpath."' stops before the marker",strpos($stroutpublic,"MARKER-AFTER-GUARD")===false,true);
	f_check("public path '".$strpublicpath."' says 403",strpos($stroutpublic,"403 Forbidden")===0,true);
}
$stroutnomap=f_runguard("/staff/x.php","");
f_check("without a prefix list nothing is private: the guard refuses",strpos($stroutnomap,"403 Forbidden")===0,true);

echo "\nThe read-write account is only asked for behind the guard\n";
//No core page writes to the database on its own. The read-write account is asked for in two
//places only: the plugin page entry point, for a page a plugin registered as "readwrite", and the
//search page, when a plugin answers the request.readwrite filter. Both call the guard first, and
//global-light.inc.php applies it a second time. A new file that sets $intdbreadwrite=true fails
//here until it is added to this list with its guard.
$arrexpected=array("plugin-page.inc.php","text2sql-search.inc.php");
$arrfound=array();
foreach (glob(__DIR__."/../lib/*.inc.php") as $strlibfile) {
	$strname=basename($strlibfile);
	if ($strname=="private-mode.inc.php") {
		//Its header comment shows the pattern
		continue;
	}
	$strsource=file_get_contents($strlibfile);
	if (!preg_match('/^\s*\$intdbreadwrite\s*=\s*true\s*;/m',$strsource,$arrmatch,PREG_OFFSET_CAPTURE)) {
		continue;
	}
	$arrfound[]=$strname;
	$lngposreadwrite=$arrmatch[0][1];
	$lngposguard=strrpos(substr($strsource,0,$lngposreadwrite),"f_privatemoderequire();");
	$lngposhelper=strpos($strsource,'include_once(__DIR__."/private-mode.inc.php");');
	$lngposglobal=strpos($strsource,'include(__DIR__."/global-light.inc.php");');
	f_check($strname.": includes private-mode.inc.php",$lngposhelper!==false,true);
	f_check($strname.": guard just before \$intdbreadwrite=true",$lngposguard!==false && $lngposhelper<$lngposguard && trim(substr($strsource,$lngposguard+strlen("f_privatemoderequire();"),$lngposreadwrite-$lngposguard-strlen("f_privatemoderequire();")))=="",true);
	f_check($strname.": \$intdbreadwrite=true before global-light",$lngposglobal!==false && $lngposreadwrite<$lngposglobal,true);
}
sort($arrfound);
f_check("the files that ask for the read-write account",$arrfound,$arrexpected);

$strgloballight=file_get_contents(__DIR__."/../lib/global-light.inc.php");
$lngposlock=strpos($strgloballight,"if (isset(\$intdbreadwrite) && \$intdbreadwrite) {\n\tf_privatemoderequire();\n}");
$lngpossecrets=strpos($strgloballight,'include(__DIR__."/database/global-light-secrets.inc.php");');
$lngposconnect=strpos($strgloballight,'$conn=f_mysqlconnect(');
f_check("global-light: second lock present",$lngposlock!==false,true);
f_check("global-light: second lock before the credentials are loaded",$lngposlock!==false && $lngpossecrets!==false && $lngposlock<$lngpossecrets,true);
f_check("global-light: credentials before the connection",$lngpossecrets!==false && $lngposconnect!==false && $lngpossecrets<$lngposconnect,true);

echo "\nNo folder name in the code\n";
//The prefixes live in the configuration. A literal prefix in a page would be a second source of truth.
$strprivatemode=file_get_contents(__DIR__."/../lib/private-mode.inc.php");
f_check("global-light reads the prefix map",strpos($strgloballight,'$arrprefixstate=f_prefixstate(')!==false,true);
f_check("global-light sets \$intprivate from the map and nowhere else",preg_match_all('/^\s*\$intprivate\s*=/m',$strgloballight),1);
f_check("private-mode reads T2S_URL_PREFIXES",strpos($strprivatemode,"env('T2S_URL_PREFIXES','')")!==false,true);

echo "\n".$lngchecks." checks, ".$lngfailures." failure(s)\n";
exit($lngfailures>0 ? 1 : 0);
?>
