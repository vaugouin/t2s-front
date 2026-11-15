<?php
/*
 * byok-test.php - test for lib/byok.inc.php, the demo mode where the visitor brings their own
 * OpenAI key.
 *
 * Run it from the repository root, no database, no web server and no network needed:
 *
 *     php test/byok-test.php
 *
 * Exit code 0 when every case passes, 1 otherwise.
 *
 * Pinned here: the shape of a key, the cookie read back, the cookie path that keeps the key inside
 * its prefix, the return URL that never leaves the folder (no open redirect), and the wiring of
 * global-light (the site's key is never used in the demo mode) and of the search page (no API
 * call without a key).
 */

require_once(__DIR__."/../lib/byok.inc.php");

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

$strgoodkey="sk-proj-".str_repeat("a",40);

echo "\nShape of a key\n";
f_check("project key",f_byokkeywellformed($strgoodkey),true);
f_check("legacy key",f_byokkeywellformed("sk-".str_repeat("B",48)),true);
f_check("too short",f_byokkeywellformed("sk-abc"),false);
f_check("space inside",f_byokkeywellformed("sk-proj-".str_repeat("a",20)." x"),false);
f_check("line break, the header injection case",f_byokkeywellformed($strgoodkey."\r\nX-Evil: 1"),false);
f_check("not a string",f_byokkeywellformed(null),false);

echo "\nThe cookie\n";
f_check("cookie read back",f_byokcookiekey(array(f_byokcookiename()=>$strgoodkey)),$strgoodkey);
f_check("cookie trimmed",f_byokcookiekey(array(f_byokcookiename()=>" ".$strgoodkey." ")),$strgoodkey);
f_check("no cookie",f_byokcookiekey(array()),"");
f_check("malformed cookie counts as none",f_byokcookiekey(array(f_byokcookiename()=>"x y")),"");
f_check("not an array",f_byokcookiekey(null),"");
f_check("cookie path is the folder of the page",f_byokcookiepath("/demo/openai-key.php"),"/demo/");
f_check("cookie path at the root",f_byokcookiepath("/openai-key.php"),"/");
f_check("nested folder",f_byokcookiepath("/a/b/index.php"),"/a/b/");

$arrdemomap=array(
	array("prefix"=>"/en/","lang"=>"en","private"=>0,"byok"=>0),
	array("prefix"=>"/demo/","lang"=>"en","private"=>0,"byok"=>1),
	array("prefix"=>"/demo-fr/","lang"=>"fr","private"=>0,"byok"=>1),
	array("prefix"=>"/staff/","lang"=>"en","private"=>1,"byok"=>1)
);
f_check("the key cookie goes to every demo prefix, and to no other",f_byokcookiepaths($arrdemomap,"/demo/openai-key.php"),array("/demo/","/demo-fr/"));
f_check("no demo prefix in the map: the folder of the page",f_byokcookiepaths(array(),"/demo/openai-key.php"),array("/demo/"));

echo "\nOpenAI models only\n";
f_check("Claude leaves the list",f_byokopenaimodels(array("gpt-6-sol","claude-opus-5-5","gpt-6-luna-decisions")),array("gpt-6-sol","gpt-6-luna-decisions"));
f_check("Gemini and Gemma leave too, o-series stays",f_byokopenaimodels(array("gemini-2.5-flash","gemma-4","o3")),array("o3"));
f_check("empty list",f_byokopenaimodels(array()),array());

echo "\nThe return URL stays in the folder\n";
f_check("search page kept",f_byokreturnurl("index.php?search=Who+directed+Alien%3F"),"index.php?search=Who+directed+Alien%3F");
f_check("another page kept",f_byokreturnurl("movie.php?id=348"),"movie.php?id=348");
f_check("absolute URL refused",f_byokreturnurl("https://example.org/"),"index.php");
f_check("protocol-relative refused",f_byokreturnurl("//example.org/index.php"),"index.php");
f_check("path refused",f_byokreturnurl("/other/index.php"),"index.php");
f_check("parent folder refused",f_byokreturnurl("../index.php"),"index.php");
f_check("line break refused",f_byokreturnurl("index.php?x=1\r\nLocation: https://example.org/"),"index.php");
f_check("not a php page refused",f_byokreturnurl("javascript:alert(1)"),"index.php");
f_check("empty gives the home page",f_byokreturnurl(""),"index.php");
f_check("request page from REQUEST_URI",f_byokrequestpage("/demo/index.php?search=x"),"index.php?search=x");
f_check("request page of a folder",f_byokrequestpage("/demo/"),"index.php");
f_check("request page of a folder with a query",f_byokrequestpage("/demo/?search=x"),"index.php?search=x");

echo "\nWiring\n";
$strgloballight=file_get_contents(__DIR__."/../lib/global-light.inc.php");
$lngposstate=strpos($strgloballight,'$intbyok=$arrprefixstate["byok"];');
f_check("global-light reads the mode from the prefix map",$lngposstate!==false,true);
f_check("global-light replaces the site's key with the visitor's in the demo mode",strpos($strgloballight,"if (\$intbyok) {\n\t\$stropenaiapikey=f_byokcookiekey(\$_COOKIE);\n")!==false,true);
$strsearch=file_get_contents(__DIR__."/../lib/text2sql-search.inc.php");
$lngposgate=strpos($strsearch,'!empty($intbyok) && $stropenaiapikey==""');
$lngposcall=strpos($strsearch,'$arrtext2sql=f_movietext2sqlsearch(');
f_check("demo mode ignores the colour parameter and cookie",strpos($strgloballight,"unset(\$_GET['apicolor'],\$_POST['apicolor'],\$_COOKIE['apicolor']);")!==false,true);
f_check("demo mode filters the three model lists",substr_count($strgloballight,"=f_byokopenaimodels(\$arrallowed"),3);
f_check("demo mode: these come after the model lists are defined",strpos($strgloballight,"=f_byokopenaimodels(")>strpos($strgloballight,"$arrallowedcomplexmodels=array("),true);
f_check("the search page asks for a key before it can call the API",$lngposgate!==false && $lngposcall!==false && $lngposgate<$lngposcall,true);
$strupload=file_get_contents(__DIR__."/../lib/upload-vision.inc.php");
f_check("the upload shim refuses a demo visitor without a key",strpos($strupload,'!empty($intbyok) && $stropenaiapikey===""')!==false,true);
$strpage=file_get_contents(__DIR__."/../lib/openai-key.inc.php");
f_check("the key page does not exist outside the demo mode",strpos($strpage,"if (!\$intbyok) {\n\thttp_response_code(404);")!==false,true);
f_check("the key cookie is written for each demo prefix",strpos($strpage,'f_byokcookiepaths(f_prefixmap(),$strphpself)')!==false,true);
f_check("the key cookie is HttpOnly",strpos($strpage,'"httponly" => true')!==false,true);

echo "\n".$lngchecks." checks, ".$lngfailures." failure(s)\n";
exit($lngfailures>0 ? 1 : 0);
?>
