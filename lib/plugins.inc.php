<?php
//Plugins: registry, loader and hooks.
//
//A plugin is a folder <root>/plugins/<name>/ holding a plugin.inc.php file. The folder is not part
//of this repository (plugins/ is ignored by git). Plugins are loaded only for a request served in
//private mode (lib/private-mode.inc.php): a public request never includes a plugin file, so with
//or without plugins a public page is the same page.
//
//plugin.inc.php registers the plugin and nothing else; it must have no other effect when included:
//
//	f_pluginregister("example",array(
//		"dir"=>__DIR__,
//		"hooks"=>array(
//			"menu.items"=>"f_example_menu",
//			"lang.strings"=>array("f_example_lang",20)
//		),
//		"pages"=>array(
//			"report"=>array("file"=>"report.inc.php"),
//			"update"=>array("file"=>"update.inc.php","readwrite"=>true)
//		)
//	));
//
//Two kinds of hook, both documented in README.md ("Hooks"):
//
//	echo f_pluginhtml("menu.items",$arrcontext);          listeners return HTML, concatenated
//	$varvalue=f_pluginfilter("lang.strings",$varvalue,$arrcontext);   listeners return the value
//
//A listener receives ($arrcontext) for an HTML hook and ($varvalue,$arrcontext) for a filter.
//Listeners run by ascending priority (default 10), then in registration order. A hook nobody
//listens to costs one array lookup and returns "" or the value unchanged.
//
//This file has no side effect and needs no database: test/plugins-test.php checks it alone.

if (!function_exists('f_pluginstate')) {
	function &f_pluginstate() {
		//The registry: plugins by name, listeners by hook, and whether the loader has run
		static $arrstate=array("plugins"=>array(),"hooks"=>array(),"sorted"=>array(),"loaded"=>false,"sequence"=>0);
		return $arrstate;
	}
}

if (!function_exists('f_pluginreset')) {
	function f_pluginreset() {
		//Purpose: Empty the registry (tests)
		$arrstate=&f_pluginstate();
		$arrstate=array("plugins"=>array(),"hooks"=>array(),"sorted"=>array(),"loaded"=>false,"sequence"=>0);
	}
}

if (!function_exists('f_pluginnameisvalid')) {
	function f_pluginnameisvalid($strname) {
		//Returns: true for a plugin or page name made of lowercase letters, digits and hyphens,
		//			starting with a letter or a digit. Nothing else ever reaches a file path.
		return (is_string($strname) && preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/',$strname)===1);
	}
}

if (!function_exists('f_pluginhook')) {
	function f_pluginhook($strhook,$varlistener,$lngpriority=10) {
		//Purpose: Attach a listener to a hook
		//Params:	$strhook: hook name
		//			$varlistener: callable; for an HTML hook function($arrcontext) returning a string,
		//			for a filter function($varvalue,$arrcontext) returning the value
		//			$lngpriority: lower runs first, default 10
		//Returns: true when attached, false when the listener is not callable
		if (!is_string($strhook) || $strhook=="" || !is_callable($varlistener)) {
			return false;
		}
		$arrstate=&f_pluginstate();
		$arrstate["sequence"]++;
		$arrstate["hooks"][$strhook][]=array((int)$lngpriority,$arrstate["sequence"],$varlistener);
		unset($arrstate["sorted"][$strhook]);
		return true;
	}
}

if (!function_exists('f_pluginregister')) {
	function f_pluginregister($strname,$arrdefinition) {
		//Purpose: Declare a plugin
		//Params:	$strname: plugin name, also the name of its folder
		//			$arrdefinition: array with
		//				"dir": absolute path of the plugin folder (pass __DIR__)
		//				"hooks": hook name => listener, or => array(listener, priority), or a list of those
		//				"pages": page name => array("file"=>path relative to dir, "readwrite"=>bool)
		//Returns: true when registered; false for an invalid name or a name already registered
		$arrstate=&f_pluginstate();
		if (!f_pluginnameisvalid($strname) || isset($arrstate["plugins"][$strname]) || !is_array($arrdefinition)) {
			return false;
		}
		$arrplugin=array("name"=>$strname,"dir"=>"","pages"=>array());
		if (isset($arrdefinition["dir"])) {
			$arrplugin["dir"]=rtrim(str_replace("\\","/",(string)$arrdefinition["dir"]),"/");
		}
		if (isset($arrdefinition["pages"]) && is_array($arrdefinition["pages"])) {
			foreach ($arrdefinition["pages"] as $strpage=>$arrpage) {
				if (!f_pluginnameisvalid($strpage) || !is_array($arrpage) || !isset($arrpage["file"])) {
					continue;
				}
				$arrplugin["pages"][$strpage]=array("file"=>(string)$arrpage["file"],"readwrite"=>!empty($arrpage["readwrite"]));
			}
		}
		$arrstate["plugins"][$strname]=$arrplugin;
		if (isset($arrdefinition["hooks"]) && is_array($arrdefinition["hooks"])) {
			foreach ($arrdefinition["hooks"] as $strhook=>$varlisteners) {
				//One listener, one array(listener, priority), or a list of either
				if (is_callable($varlisteners) || (is_array($varlisteners) && count($varlisteners)==2 && isset($varlisteners[1]) && is_int($varlisteners[1]) && is_callable($varlisteners[0]))) {
					$varlisteners=array($varlisteners);
				}
				if (!is_array($varlisteners)) {
					continue;
				}
				foreach ($varlisteners as $varlistener) {
					if (is_array($varlistener) && count($varlistener)==2 && isset($varlistener[1]) && is_int($varlistener[1]) && is_callable($varlistener[0])) {
						f_pluginhook($strhook,$varlistener[0],$varlistener[1]);
					} else {
						f_pluginhook($strhook,$varlistener);
					}
				}
			}
		}
		return true;
	}
}

if (!function_exists('f_pluginactive')) {
	function f_pluginactive($strname) {
		//Returns: true when a plugin of that name is registered
		$arrstate=&f_pluginstate();
		return isset($arrstate["plugins"][$strname]);
	}
}

if (!function_exists('f_pluginnames')) {
	function f_pluginnames() {
		//Returns: names of the registered plugins, in registration order
		$arrstate=&f_pluginstate();
		return array_keys($arrstate["plugins"]);
	}
}

if (!function_exists('f_pluginlisteners')) {
	function f_pluginlisteners($strhook) {
		//Returns: the listeners of a hook, ordered by priority then by registration
		$arrstate=&f_pluginstate();
		if (!isset($arrstate["hooks"][$strhook])) {
			return array();
		}
		if (!isset($arrstate["sorted"][$strhook])) {
			$arrlisteners=$arrstate["hooks"][$strhook];
			usort($arrlisteners,function($arra,$arrb) {
				if ($arra[0]!=$arrb[0]) {
					return ($arra[0]<$arrb[0] ? -1 : 1);
				}
				return ($arra[1]<$arrb[1] ? -1 : 1);
			});
			$arrstate["sorted"][$strhook]=array();
			foreach ($arrlisteners as $arrlistener) {
				$arrstate["sorted"][$strhook][]=$arrlistener[2];
			}
		}
		return $arrstate["sorted"][$strhook];
	}
}

if (!function_exists('f_pluginhtml')) {
	function f_pluginhtml($strhook,$arrcontext=array()) {
		//Purpose: Call an HTML hook
		//Params:	$strhook: hook name
		//			$arrcontext: what the call site knows (identifiers, titles, display level...)
		//Returns: the HTML returned by every listener, concatenated in order; "" without listener
		$arrstate=&f_pluginstate();
		if (!isset($arrstate["hooks"][$strhook])) {
			return "";
		}
		$strhtml="";
		foreach (f_pluginlisteners($strhook) as $varlistener) {
			$strhtml.=(string)call_user_func($varlistener,$arrcontext);
		}
		return $strhtml;
	}
}

if (!function_exists('f_pluginfilter')) {
	function f_pluginfilter($strhook,$varvalue,$arrcontext=array()) {
		//Purpose: Call a filter hook
		//Params:	$strhook: hook name
		//			$varvalue: the value each listener receives and returns, changed or not
		//			$arrcontext: what the call site knows
		//Returns: the value after the last listener; $varvalue itself without listener
		$arrstate=&f_pluginstate();
		if (!isset($arrstate["hooks"][$strhook])) {
			return $varvalue;
		}
		foreach (f_pluginlisteners($strhook) as $varlistener) {
			$varvalue=call_user_func($varlistener,$varvalue,$arrcontext);
		}
		return $varvalue;
	}
}

if (!function_exists('f_pluginhaslisteners')) {
	function f_pluginhaslisteners($strhook) {
		//Returns: true when at least one listener is attached to the hook
		$arrstate=&f_pluginstate();
		return isset($arrstate["hooks"][$strhook]);
	}
}

if (!function_exists('f_pluginsdir')) {
	function f_pluginsdir() {
		//Returns: the folder scanned for plugins, <root>/plugins unless T2S_PLUGINS_DIR names another
		//			one (a development checkout of the plugins, for instance). No trailing slash.
		$strdir="";
		if (function_exists('env')) {
			$strdir=trim((string)env('T2S_PLUGINS_DIR',''));
		}
		if ($strdir=="") {
			$strdir=__DIR__."/../plugins";
		}
		return rtrim(str_replace("\\","/",$strdir),"/");
	}
}

if (!function_exists('f_pluginsload')) {
	function f_pluginsload($intprivaterequest,$strdir=null) {
		//Purpose: Include the plugin.inc.php of every plugin folder, once per request
		//Params:	$intprivaterequest: result of f_privatemoderequest(); nothing is loaded when false
		//			$strdir: folder to scan, f_pluginsdir() when null
		//Returns: the number of plugin files included by this call. Folders are taken in
		//			alphabetical order; a folder whose name is not a valid plugin name, or without a
		//			plugin.inc.php, is skipped.
		$arrstate=&f_pluginstate();
		if (!$intprivaterequest || $arrstate["loaded"]) {
			return 0;
		}
		$arrstate["loaded"]=true;
		if ($strdir===null) {
			$strdir=f_pluginsdir();
		}
		if (!is_dir($strdir)) {
			return 0;
		}
		$arrnames=scandir($strdir);
		if (!is_array($arrnames)) {
			return 0;
		}
		sort($arrnames);
		$lngloaded=0;
		foreach ($arrnames as $strname) {
			if (!f_pluginnameisvalid($strname)) {
				continue;
			}
			$strfile=$strdir."/".$strname."/plugin.inc.php";
			if (is_file($strfile)) {
				include_once($strfile);
				$lngloaded++;
			}
		}
		return $lngloaded;
	}
}

if (!function_exists('f_pluginpage')) {
	function f_pluginpage($strplugin,$strpage) {
		//Purpose: Find a page registered by a plugin
		//Params:	$strplugin: plugin name
		//			$strpage: page name
		//Returns: array("plugin"=>..,"page"=>..,"file"=>absolute path,"readwrite"=>bool), or null
		//			when the plugin or the page is unknown, a name is invalid, or the file is missing
		//			or lies outside the plugin folder
		$arrstate=&f_pluginstate();
		if (!f_pluginnameisvalid($strplugin) || !f_pluginnameisvalid($strpage)) {
			return null;
		}
		if (!isset($arrstate["plugins"][$strplugin]["pages"][$strpage])) {
			return null;
		}
		$arrplugin=$arrstate["plugins"][$strplugin];
		$strfile=f_pluginfileinside($arrplugin["dir"],$arrplugin["pages"][$strpage]["file"]);
		if ($strfile=="") {
			return null;
		}
		return array("plugin"=>$strplugin,"page"=>$strpage,"file"=>$strfile,"readwrite"=>$arrplugin["pages"][$strpage]["readwrite"]);
	}
}

if (!function_exists('f_pluginfileinside')) {
	function f_pluginfileinside($strdir,$strrelative) {
		//Purpose: Resolve a file of a plugin and refuse anything that leaves its folder
		//Params:	$strdir: plugin folder
		//			$strrelative: path relative to it
		//Returns: absolute path with forward slashes, "" when the file does not exist or resolves
		//			outside $strdir (.. segments, absolute path, symbolic link pointing elsewhere)
		$strrelative=str_replace("\\","/",(string)$strrelative);
		if ($strdir=="" || $strrelative=="" || substr($strrelative,0,1)=="/" || strpos($strrelative,"..")!==false || strpos($strrelative,":")!==false) {
			return "";
		}
		$strbase=realpath($strdir);
		$strfile=realpath($strdir."/".$strrelative);
		if ($strbase===false || $strfile===false || !is_file($strfile)) {
			return "";
		}
		$strbase=rtrim(str_replace("\\","/",$strbase),"/")."/";
		$strfile=str_replace("\\","/",$strfile);
		if (substr($strfile,0,strlen($strbase))!==$strbase) {
			return "";
		}
		return $strfile;
	}
}

if (!function_exists('f_pluginpageurl')) {
	function f_pluginpageurl($strplugin,$strpage,$arrparams=array()) {
		//Purpose: URL of a plugin page, relative to the page folder
		//Params:	$strplugin, $strpage: names given at registration
		//			$arrparams: query string parameters of the page, name => value
		//Returns: x.php?plugin=<plugin>&view=<page>&..., not HTML-escaped
		$strurl="x.php?plugin=".rawurlencode((string)$strplugin)."&view=".rawurlencode((string)$strpage);
		foreach ($arrparams as $strname=>$varvalue) {
			$strurl.="&".rawurlencode((string)$strname)."=".rawurlencode((string)$varvalue);
		}
		return $strurl;
	}
}

if (!function_exists('f_pluginasseturl')) {
	function f_pluginasseturl($strplugin,$strasset) {
		//Purpose: URL of a static file of a plugin (stylesheet, image), served by the page shim
		//Params:	$strplugin: plugin name
		//			$strasset: file name inside the assets/ folder of the plugin
		//Returns: x.php?plugin=<plugin>&asset=<file>, not HTML-escaped
		return "x.php?plugin=".rawurlencode((string)$strplugin)."&asset=".rawurlencode((string)$strasset);
	}
}

if (!function_exists('f_pluginassettypes')) {
	function f_pluginassettypes() {
		//Returns: the file extensions the asset route serves, with their content type
		return array(
			"css"=>"text/css; charset=utf-8",
			"js"=>"text/javascript; charset=utf-8",
			"png"=>"image/png",
			"jpg"=>"image/jpeg",
			"webp"=>"image/webp",
			"svg"=>"image/svg+xml",
			"gif"=>"image/gif"
		);
	}
}

if (!function_exists('f_pluginasset')) {
	function f_pluginasset($strplugin,$strasset) {
		//Purpose: Find a static file of a plugin
		//Params:	$strplugin: plugin name
		//			$strasset: file name, letters, digits, dot, hyphen and underscore only
		//Returns: array("file"=>absolute path,"type"=>content type), or null when the plugin is
		//			unknown, the name or its extension is not allowed, or the file is not in the
		//			assets/ folder of the plugin
		$arrstate=&f_pluginstate();
		if (!f_pluginnameisvalid($strplugin) || !isset($arrstate["plugins"][$strplugin])) {
			return null;
		}
		if (!is_string($strasset) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,100}$/',$strasset)!==1) {
			return null;
		}
		$strextension=strtolower(pathinfo($strasset,PATHINFO_EXTENSION));
		$arrtypes=f_pluginassettypes();
		if (!isset($arrtypes[$strextension])) {
			return null;
		}
		$strfile=f_pluginfileinside($arrstate["plugins"][$strplugin]["dir"],"assets/".$strasset);
		if ($strfile=="") {
			return null;
		}
		return array("file"=>$strfile,"type"=>$arrtypes[$strextension]);
	}
}
?>
