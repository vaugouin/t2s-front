<?php
/*
 * plugins-test.php - test for lib/plugins.inc.php and for the hook call sites of the core
 *
 * Run it from the repository root, no database, no web server and no network needed:
 *
 *     php test/plugins-test.php
 *
 * Exit code 0 when every case passes, 1 otherwise.
 *
 * It pins: the registry and the order of the listeners, the two kinds of hook, the loader (private
 * requests only, once), the page and asset lookups and their refusal of anything outside the
 * plugin folder, and three properties of the core: every hook it calls is documented in
 * README.md, the entry point x.php answers 404 outside private mode, and no core file still
 * names a file of a plugin.
 */

require_once(__DIR__."/../lib/plugins.inc.php");

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

function f_testrmdir($strdir) {
	foreach (scandir($strdir) as $strname) {
		if ($strname=="." || $strname=="..") {
			continue;
		}
		if (is_dir($strdir."/".$strname)) {
			f_testrmdir($strdir."/".$strname);
		} else {
			unlink($strdir."/".$strname);
		}
	}
	rmdir($strdir);
}

echo "\nWithout any plugin\n";
f_pluginreset();
f_check("an HTML hook returns an empty string",f_pluginhtml("menu.items",array("a"=>1)),"");
f_check("a filter returns its value",f_pluginfilter("lang.strings",array("en"=>array("k"=>"v"))),array("en"=>array("k"=>"v")));
f_check("a filter returns a non-array value untouched",f_pluginfilter("samples.showall",0),0);
f_check("no listener",f_pluginhaslisteners("menu.items"),false);
f_check("no plugin",f_pluginnames(),array());
f_check("an unknown plugin is not active",f_pluginactive("example"),false);
f_check("no page",f_pluginpage("example","report"),null);
f_check("no asset",f_pluginasset("example","style.css"),null);

echo "\nNames\n";
f_check("letters, digits and hyphens",f_pluginnameisvalid("legacy-pages2"),true);
f_check("no uppercase",f_pluginnameisvalid("Example"),false);
f_check("no dot",f_pluginnameisvalid(".."),false);
f_check("no slash",f_pluginnameisvalid("a/b"),false);
f_check("no leading hyphen",f_pluginnameisvalid("-a"),false);
f_check("not empty",f_pluginnameisvalid(""),false);
f_check("a string only",f_pluginnameisvalid(array("a")),false);

echo "\nListeners and their order\n";
f_pluginreset();
f_check("a listener that is not callable is refused",f_pluginhook("menu.items","f_no_such_function_here"),false);
f_pluginhook("menu.items",function($arrcontext) { return "[b]"; });
f_pluginhook("menu.items",function($arrcontext) { return "[a]"; },5);
f_pluginhook("menu.items",function($arrcontext) { return "[c".(isset($arrcontext["x"]) ? $arrcontext["x"] : "")."]"; });
f_pluginhook("menu.items",function($arrcontext) { return "[z]"; },50);
f_check("priority first, then registration order",f_pluginhtml("menu.items",array("x"=>7)),"[a][b][c7][z]");
f_pluginhook("menu.items",function($arrcontext) { return "[first]"; },1);
f_check("a listener added later is sorted in",f_pluginhtml("menu.items"),"[first][a][b][c][z]");
f_pluginhook("value",function($varvalue,$arrcontext) { return $varvalue+1; });
f_pluginhook("value",function($varvalue,$arrcontext) { return $varvalue*(isset($arrcontext["by"]) ? $arrcontext["by"] : 1); },20);
f_check("a filter chains its listeners",f_pluginfilter("value",2,array("by"=>10)),30);
f_pluginhook("nothing",function($arrcontext) { return null; });
f_check("a listener that returns nothing adds nothing",f_pluginhtml("nothing"),"");

echo "\nRegistration\n";
f_pluginreset();
$strroot=str_replace("\\","/",sys_get_temp_dir())."/t2s-plugins-test-".getmypid();
if (is_dir($strroot)) {
	f_testrmdir($strroot);
}
mkdir($strroot."/alpha/assets",0777,true);
mkdir($strroot."/alpha/pages",0777,true);
mkdir($strroot."/beta",0777,true);
mkdir($strroot."/Not-Valid",0777,true);
mkdir($strroot."/empty",0777,true);
file_put_contents($strroot."/secret.inc.php","<?php echo 'outside';");
file_put_contents($strroot."/alpha/pages/report.inc.php","<?php echo 'report';");
file_put_contents($strroot."/alpha/pages/update.inc.php","<?php echo 'update';");
file_put_contents($strroot."/alpha/assets/style.css","body{}");
file_put_contents($strroot."/alpha/assets/notes.txt","x");
file_put_contents($strroot."/alpha/assets/run.php","<?php ");
file_put_contents($strroot."/alpha/plugin.inc.php",'<?php
function f_alpha_menu($arrcontext) { return "<a>alpha</a>"; }
function f_alpha_lang($arrstrings,$arrcontext) { $arrstrings["en"]["lib_alpha"]="Alpha"; return $arrstrings; }
f_pluginregister("alpha",array(
	"dir"=>__DIR__,
	"hooks"=>array("menu.items"=>array("f_alpha_menu",20),"lang.strings"=>"f_alpha_lang","head"=>array("f_alpha_menu",array("f_alpha_menu",5))),
	"pages"=>array(
		"report"=>array("file"=>"pages/report.inc.php"),
		"update"=>array("file"=>"pages/update.inc.php","readwrite"=>true),
		"escape"=>array("file"=>"../secret.inc.php"),
		"missing"=>array("file"=>"pages/missing.inc.php"),
		"Bad Name"=>array("file"=>"pages/report.inc.php")
	)
));
');
file_put_contents($strroot."/beta/plugin.inc.php",'<?php
function f_beta_menu($arrcontext) { return "<a>beta</a>"; }
f_pluginregister("beta",array("dir"=>__DIR__,"hooks"=>array("menu.items"=>"f_beta_menu")));
');
file_put_contents($strroot."/Not-Valid/plugin.inc.php",'<?php f_pluginregister("notvalid",array("dir"=>__DIR__));');

f_check("a public request loads nothing",f_pluginsload(0,$strroot),0);
f_check("still no plugin after a public request",f_pluginnames(),array());
f_pluginreset();
f_check("a missing folder loads nothing",f_pluginsload(1,$strroot."/nowhere"),0);
f_pluginreset();
f_check("a private request loads the two valid folders",f_pluginsload(1,$strroot),2);
f_check("in alphabetical order",f_pluginnames(),array("alpha","beta"));
f_check("the loader runs once per request",f_pluginsload(1,$strroot),0);
f_check("alpha is active",f_pluginactive("alpha"),true);
f_check("a folder with an invalid name is skipped",f_pluginactive("notvalid"),false);
f_check("the same name cannot be registered twice",f_pluginregister("alpha",array("dir"=>$strroot."/alpha")),false);
f_check("an invalid name cannot be registered",f_pluginregister("Alpha!",array()),false);
f_check("menu: beta (10) before alpha (20)",f_pluginhtml("menu.items"),"<a>beta</a><a>alpha</a>");
f_check("a list of listeners on one hook",f_pluginhtml("head"),"<a>alpha</a><a>alpha</a>");
$arrstrings=f_pluginfilter("lang.strings",array("en"=>array("lib_core"=>"Core"),"fr"=>array()));
f_check("the language filter adds a key and keeps the others",$arrstrings["en"],array("lib_core"=>"Core","lib_alpha"=>"Alpha"));

echo "\nPages\n";
$arrpage=f_pluginpage("alpha","report");
f_check("a registered page is found",($arrpage!==null ? array($arrpage["plugin"],$arrpage["page"],basename($arrpage["file"]),$arrpage["readwrite"]) : null),array("alpha","report","report.inc.php",false));
$arrpage=f_pluginpage("alpha","update");
f_check("a read-write page says so",($arrpage!==null ? $arrpage["readwrite"] : null),true);
f_check("a page file outside the plugin folder is refused",f_pluginpage("alpha","escape"),null);
f_check("a page whose file is missing is refused",f_pluginpage("alpha","missing"),null);
f_check("an unknown page",f_pluginpage("alpha","nothing"),null);
f_check("a page of an unknown plugin",f_pluginpage("gamma","report"),null);
f_check("an invalid page name never reaches the registry",f_pluginpage("alpha","../report"),null);
f_check("a page with an invalid name was not registered",f_pluginpage("alpha","Bad Name"),null);
f_check("page URL",f_pluginpageurl("alpha","report",array("id"=>12,"q"=>"a b&c")),"x.php?plugin=alpha&view=report&id=12&q=a%20b%26c");

echo "\nAssets\n";
$arrasset=f_pluginasset("alpha","style.css");
f_check("a stylesheet is found, with its type",($arrasset!==null ? array(basename($arrasset["file"]),$arrasset["type"]) : null),array("style.css","text/css; charset=utf-8"));
f_check("an extension that is not served",f_pluginasset("alpha","notes.txt"),null);
f_check("PHP is never served as an asset",f_pluginasset("alpha","run.php"),null);
f_check("no path in an asset name",f_pluginasset("alpha","../plugin.inc.php"),null);
f_check("no sub-folder in an asset name",f_pluginasset("alpha","sub/style.css"),null);
f_check("a missing asset",f_pluginasset("alpha","other.css"),null);
f_check("an asset of an unknown plugin",f_pluginasset("gamma","style.css"),null);
f_check("asset URL",f_pluginasseturl("alpha","style.css"),"x.php?plugin=alpha&asset=style.css");
f_check("file resolution refuses ..",f_pluginfileinside($strroot."/alpha","../secret.inc.php"),"");
f_check("file resolution refuses an absolute path",f_pluginfileinside($strroot."/alpha",$strroot."/secret.inc.php"),"");
f_check("file resolution refuses an empty folder",f_pluginfileinside("","pages/report.inc.php"),"");
f_testrmdir($strroot);

echo "\nThe entry point, run in a real PHP process\n";
//x.php must answer 404 to a public request, before it loads anything, and to a private request
//that names nothing registered. The child stops before global-light.inc.php in both cases, so no
//database is needed.
function f_runentry($strphpself,$arrget,$strconfig,$strpluginsdir) {
	$strcode='putenv('.var_export("T2S_URL_PREFIXES=".$strconfig,true).');';
	$strcode.='putenv('.var_export("T2S_PLUGINS_DIR=".$strpluginsdir,true).');';
	$strcode.='$_SERVER["PHP_SELF"]='.var_export($strphpself,true).';';
	$strcode.='$_GET='.var_export($arrget,true).';';
	$strcode.='register_shutdown_function(function() { echo " code=".var_export(http_response_code(),true); });';
	$strcode.='require '.var_export(__DIR__."/../lib/plugin-page.inc.php",true).';';
	$strfile=tempnam(sys_get_temp_dir(),"ppt");
	file_put_contents($strfile,"<?php ".$strcode);
	$arroutput=array();
	$lngexit=0;
	exec(escapeshellarg(PHP_BINARY)." ".escapeshellarg($strfile)." 2>&1",$arroutput,$lngexit);
	@unlink($strfile);
	return implode("\n",$arroutput);
}
$strempty=str_replace("\\","/",sys_get_temp_dir())."/t2s-plugins-empty-".getmypid();
mkdir($strempty);
$strmapconfig="/en/:en,/staff/:en:private";
f_check("public request: 404",strpos(f_runentry("/en/x.php",array("plugin"=>"alpha","view"=>"report"),$strmapconfig,$strempty),"404 Not Found")===0,true);
f_check("private request, unknown plugin: 404",strpos(f_runentry("/staff/x.php",array("plugin"=>"alpha","view"=>"report"),$strmapconfig,$strempty),"404 Not Found")===0,true);
f_check("private request, unknown asset: 404",strpos(f_runentry("/staff/x.php",array("plugin"=>"alpha","asset"=>"style.css"),$strmapconfig,$strempty),"404 Not Found")===0,true);
f_check("private request, no parameter: 404",strpos(f_runentry("/staff/x.php",array(),$strmapconfig,$strempty),"404 Not Found")===0,true);
f_check("parameters that are arrays: 404",strpos(f_runentry("/staff/x.php",array("plugin"=>array("a"),"view"=>array("b")),$strmapconfig,$strempty),"404 Not Found")===0,true);
rmdir($strempty);

echo "\nThe core and its hooks\n";
$strreadme=file_get_contents(__DIR__."/../README.md");
$arrhooks=array();
$arrfilesbyhook=array();
foreach (glob(__DIR__."/../lib/*.inc.php") as $strlibfile) {
	if (basename($strlibfile)=="plugins.inc.php") {
		continue;
	}
	if (preg_match_all('/f_plugin(html|filter)\(\s*"([a-z0-9.]+)"/',file_get_contents($strlibfile),$arrmatches,PREG_SET_ORDER)) {
		foreach ($arrmatches as $arrmatch) {
			$arrhooks[$arrmatch[2]]=$arrmatch[1];
			$arrfilesbyhook[$arrmatch[2]][basename($strlibfile)]=1;
		}
	}
}
ksort($arrhooks);
f_check("the core calls hooks",count($arrhooks)>10,true);
foreach ($arrhooks as $strhook=>$strkind) {
	f_check("hook ".$strhook." is documented in README.md",strpos($strreadme,"`".$strhook."`")!==false,true);
}
$strgloballight=file_get_contents(__DIR__."/../lib/global-light.inc.php");
f_check("global-light loads the plugins for a private request only",strpos($strgloballight,"f_pluginsload(f_privatemoderequest());")!==false,true);
$strloader=file_get_contents(__DIR__."/../lib/plugins.inc.php");
f_check("the loader includes nothing for a public request",strpos($strloader,"if (!\$intprivaterequest || \$arrstate[\"loaded\"]) {\n\t\t\treturn 0;")!==false,true);
//A plugin is optional: the core never includes one of its files and never calls one of its functions
$lngdirect=0;
foreach (glob(__DIR__."/../lib/*.inc.php") as $strlibfile) {
	if (in_array(basename($strlibfile),array("plugins.inc.php","plugin-page.inc.php"),true)) {
		continue;
	}
	if (preg_match('/(include|require)(_once)?\s*\([^)]*plugins\//',file_get_contents($strlibfile))) {
		$lngdirect++;
		echo "     ".basename($strlibfile)." includes a plugin file\n";
	}
}
f_check("no core page includes a plugin file",$lngdirect,0);
f_check("plugins/ is ignored by git",preg_match('/^\/?plugins\/$/m',file_get_contents(__DIR__."/../.gitignore")),1);

echo "\n".$lngchecks." checks, ".$lngfailures." failure(s)\n";
exit($lngfailures>0 ? 1 : 0);
?>
