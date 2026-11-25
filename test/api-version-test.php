<?php
/*
 * api-version-test.php - test for lib/api-version.inc.php
 *
 * Run it from the repository root, no database, no web server and no network needed:
 *
 *     php test/api-version-test.php
 *
 * Exit code 0 when every case passes, 1 otherwise.
 *
 * The version read from GET / filters the samples panel of the search page, so a wrong value
 * is not cosmetic. These cases pin what is accepted from the API, and the cache: fresh, stale,
 * corrupted, and keyed on the URL so a moved API never answers with the old one's version.
 */

require_once(__DIR__."/../lib/api-version.inc.php");

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

echo "\nValid versions\n";
f_check("raw version",f_apiversionisvalid("1.1.19"),true);
f_check("zero parts allowed",f_apiversionisvalid("1.0.0"),true);
f_check("zero-padded cache-key form refused",f_apiversionisvalid("001.001.019"),false);
f_check("two parts refused",f_apiversionisvalid("1.1"),false);
f_check("suffix refused",f_apiversionisvalid("1.1.19-beta"),false);
f_check("markup refused",f_apiversionisvalid("1.1.19<script>"),false);
f_check("non-string refused",f_apiversionisvalid(119),false);

echo "\nReading GET /\n";
f_check("the documented answer",f_apiversionfromresponse('{"message":"hello world! The universal answer is 42","bktrees_ready":true,"api_version":"1.1.19"}'),"1.1.19");
f_check("whitespace trimmed",f_apiversionfromresponse('{"api_version":" 1.1.18 "}'),"1.1.18");
f_check("no api_version (an API that predates the field)",f_apiversionfromresponse('{"message":"hello world!"}'),"");
f_check("invalid version",f_apiversionfromresponse('{"api_version":"latest"}'),"");
f_check("not JSON (an HTML error page from the proxy)",f_apiversionfromresponse("<html>502 Bad Gateway</html>"),"");
f_check("failed call",f_apiversionfromresponse(false),"");
f_check("empty body",f_apiversionfromresponse(""),"");

echo "\nInstance behind a slot\n";
f_check("instance read",f_apiinstancefromresponse('{"api_version":"1.1.20","instance":"violet"}'),"violet");
f_check("instance lowercased",f_apiinstancefromresponse('{"instance":"Brown"}'),"brown");
f_check("no instance (an API that predates the field)",f_apiinstancefromresponse('{"api_version":"1.1.19"}'),"");
f_check("invalid instance",f_apiinstancefromresponse('{"instance":"<b>x</b>"}'),"");
f_check("not JSON",f_apiinstancefromresponse("<html>502</html>"),"");
f_check("blue dot",f_apiinstancechip("blue"),"🔵");
f_check("green dot",f_apiinstancechip("green"),"🟢");
f_check("violet dot",f_apiinstancechip("violet"),"🟣");
f_check("brown dot",f_apiinstancechip("brown"),"🟤");
f_check("unknown instance: white dot",f_apiinstancechip(""),"⚪");

echo "\nInstances by name\n";
$arrinst=f_apiinstanceurls("","http://api.example.test:8186");
f_check("default: four instances",implode(",",array_keys($arrinst)),"blue,green,violet,brown");
f_check("port pair takes the slot host",$arrinst["violet"],"http://api.example.test:8188");
f_check("https slot kept",f_apiinstanceurls("brown=8189","https://api.example.test:8010/")["brown"],"https://api.example.test:8189");
f_check("full URL pair",f_apiinstanceurls("red=http://other.test:9000/","")["red"],"http://other.test:9000");
f_check("port pair without a slot host skipped",count(f_apiinstanceurls("blue=8186","")),0);
f_check("invalid pairs skipped",implode(",",array_keys(f_apiinstanceurls("Bad Name=1,x=javascript:1,ok=8000,nope","http://h.test"))),"ok");
f_check("instance choice read",f_apiinstancechoice("instance-Violet",$arrinst),"violet");
f_check("slot value is not an instance",f_apiinstancechoice("Blue",$arrinst),"");
f_check("unknown instance refused",f_apiinstancechoice("instance-red",$arrinst),"");

echo "\nCache\n";
$strdir=sys_get_temp_dir().DIRECTORY_SEPARATOR."t2s-front-api-version-test-".getmypid();
@mkdir($strdir);
$strfile=f_apiversioncachefile("Green","https://example.test:8187",$strdir);
f_check("file in the given directory",dirname($strfile),$strdir);
f_check("keyed on the URL",f_apiversioncachefile("Green","https://example.test:9999",$strdir)!==$strfile,true);
f_check("keyed on the colour",f_apiversioncachefile("Blue","https://example.test:8187",$strdir)!==$strfile,true);
f_check("absent file",f_apiversioncacheread($strfile,time()),"");
f_check("invalid version not written",f_apiversioncachewrite($strfile,"oops"),false);
f_check("written",f_apiversioncachewrite($strfile,"1.1.19"),true);
$lngmtime=filemtime($strfile);
f_check("fresh read",f_apiversioncacheread($strfile,$lngmtime+10),"1.1.19");
f_check("stale at the TTL",f_apiversioncacheread($strfile,$lngmtime+TEXT2SQL_API_VERSION_CACHE_TTL),"");
file_put_contents($strfile,"garbage");
f_check("corrupted file ignored",f_apiversioncacheread($strfile,filemtime($strfile)),"");
@unlink($strfile);
@rmdir($strdir);

echo "\n".$lngchecks." checks, ".$lngfailures." failure(s)\n";
exit($lngfailures>0 ? 1 : 0);
?>
