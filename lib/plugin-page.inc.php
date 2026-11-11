<?php
//Plugin pages and plugin static files: the one entry point, html/app/x.php.
//
//	x.php?plugin=<plugin>&view=<page>[&...]   runs a page registered by a plugin
//	x.php?plugin=<plugin>&asset=<file>   serves a file of the assets/ folder of a plugin
//
//Both exist only in private mode. Outside it, or when the plugin, the page or the file is not
//there, the answer is HTTP 404: a public visitor cannot tell an absent plugin from a present one.
//
//A page registered with "readwrite"=>true gets the read-write database account. The private-mode
//lock is applied here, before global-light.inc.php opens the connection, and global-light applies
//it a second time.
//
//The page file is included after global-light.inc.php and language.inc.php: it starts directly
//with its own code, as if those two includes were its first lines.

include_once(__DIR__."/private-mode.inc.php");
include_once(__DIR__."/plugins.inc.php");

if (!function_exists('f_pluginpagenotfound')) {
	function f_pluginpagenotfound() {
		//Purpose: Answer HTTP 404 with a short text and stop
		if (!headers_sent()) {
			http_response_code(404);
			header("Content-Type: text/plain; charset=utf-8");
			header("Cache-Control: no-store");
		}
		echo "404 Not Found";
		exit;
	}
}

if (!f_privatemoderequest()) {
	f_pluginpagenotfound();
}
f_pluginsload(1);

$strpluginname=(isset($_GET['plugin']) && is_string($_GET['plugin']) ? $_GET['plugin'] : "");

if (isset($_GET['asset'])) {
	//Static file of a plugin: no database, no session
	$arrpluginasset=f_pluginasset($strpluginname,(is_string($_GET['asset']) ? $_GET['asset'] : ""));
	if ($arrpluginasset===null) {
		f_pluginpagenotfound();
	}
	header("Content-Type: ".$arrpluginasset["type"]);
	header("Content-Length: ".filesize($arrpluginasset["file"]));
	header("Cache-Control: private, max-age=86400");
	header("X-Content-Type-Options: nosniff");
	readfile($arrpluginasset["file"]);
	exit;
}

$arrpluginpage=f_pluginpage($strpluginname,(isset($_GET['view']) && is_string($_GET['view']) ? $_GET['view'] : ""));
if ($arrpluginpage===null) {
	f_pluginpagenotfound();
}
if ($arrpluginpage["readwrite"]) {
	//This page writes to the database with the read-write account: private mode only.
	//The guard answers HTTP 403 and exits before the connection is opened.
	f_privatemoderequire();
	$intdbreadwrite=true;
}
include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
include($arrpluginpage["file"]);
?>
