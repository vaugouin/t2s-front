<?php

include_once(__DIR__."/env.inc.php");
f_envinit();

function is_cli() {
    return (PHP_SAPI === 'cli' || php_sapi_name() === 'cli');
}

function is_fpm() {
    return (PHP_SAPI === 'fpm-fcgi');
}

if (is_cli()) {
    $strendofline="\n";
} else {
    $strendofline="<br />";
}

function f_php_server_var($strvarname) {
	$strresult="";
    if (is_cli()) {
		//CLI
		switch ($strvarname) {
		case "REQUEST_URI":
		case "SCRIPT_NAME":
		case "PHP_SELF":
			$strresult = substr(realpath($_SERVER['argv'][0]),strlen(env('T2S_DOCUMENT_ROOT'))-1);   // or realpath($GLOBALS['argv'][0]);
			break;
		case "SERVER_NAME":
			$strresult=env('T2S_HTTP_HOST');
			break;	
		case "REQUEST_TIME":
			$strresult=time();
			break;
		default:
			$strresult=env('T2S_'.$strvarname);
			break;
		}
	} else {
		//Web server
		if (isset($_SERVER[$strvarname])) {
			$strresult=$_SERVER[$strvarname];
		}
	}
	return $strresult;
}


//Set here the current version of the Text2SQL API
$strtext2sqlapicolor="Blue";
//$strtext2sqlapicolor="Green";

//FALLBACK ONLY: the running version is read from GET / of each colour by
//f_text2sqlapiversion(), cached five minutes. These two strings are used only when the API
//cannot be reached, so they no longer need bumping on each deploy.
$strtext2sqlapiblueversion="1.1.20";
$strtext2sqlapigreenversion="1.1.19";

//2026-10-03: entity extraction and text-to-SQL move to gpt-6-sol, like the API
//defaults, so a web search and a voice search answer with the same models. The decision was
//taken before a rerun of the evaluation and without a Sol bench of entity extraction.
//A visitor who saved a model in the settings keeps it (eemodel / t2smodel cookies).
$strtext2sqlapiblueeellm="gpt-6-sol";
$strtext2sqlapigreeneellm="gpt-6-sol";
//2026-11-10: text-to-SQL moves to gpt-6.1-terra, like the API
//default. Campaign of 2026-11-09 on 1.1.20: as accurate as gpt-6-sol on 1,069 questions in EN
//and FR, about a third of the cost per call. Entity extraction stays on gpt-6-sol until its bench.
//Reverted the same day, like the API: at the standard tier Terra wrote SQL slower than
//Sol (4.14 s against 2.88 s median), too much for the voice path.
$strtext2sqlapibluet2sllm="gpt-6-sol";
$strtext2sqlapigreent2sllm="gpt-6-sol";
//2026-10-17: the complex question moves to gpt-6.1-sol, like the API default. On the
//escalated subset it beat gpt-4o (28 against 24 EN, 38 against 31 FR) and gpt-6-sol, about 2 s
//slower on that step. A visitor who saved a model in the settings keeps it (complexmodel cookie).
$strtext2sqlapibluecomplexllm="gpt-6.1-sol";
$strtext2sqlapigreencomplexllm="gpt-6.1-sol";
//The API exposes five LLM tasks, not three. The answer-entity
//classifier fires on 99.9% of requests and had no selector at all until then; the
//single-value answerer used to borrow the complex-question one.
//2026-09-29: the classifier moves to gpt-6-luna, like the API default. Benched
//against gpt-4o on EN and FR, no more decidable errors beyond its noise floor, ~$0.06 per
//1,000 calls against $1.46, and its latency no longer reaches the response.
$strtext2sqlapiblueresultentityllm="gpt-6-luna";
$strtext2sqlapigreenresultentityllm="gpt-6-luna";
//2026-10-15: the single-value answer task is removed from the API, so
//nothing is sent for it any more and its settings radio group is gone.

//The whole GPT-6 family is selectable for every task: sol (the strong one), astra (the API's
//vision default, a reasoning model) and luna (the cheap one). The API passes any model name
//through, so these two lists are the only gate.
//2026-10-17: gpt-4o, gpt-4.1 and gpt-4.1-mini leave both lists, OpenAI retires them
//on 2027-01-14. A saved cookie naming one of them no longer validates and falls back to the default.
//GPT-6.1 Terra (2026-10-28): gpt-6.1-terra, released 2026-10-26 between Luna and Sol at half Sol's
//price, joins every task as a candidate (entity extraction and text-to-SQL first). It keeps the
//`none` effort and falls into the API's gpt-6 effort family, so it needs no API change.
//2026-10-28: OpenAI models only for every task; claude-sonnet-4-6,
//claude-haiku-4-5-20251001, gemini-2.5-flash, gemma-4 and gemma-4-google leave the list.
//The one exception is claude-opus-5-5, the only Claude model an evaluation campaign has run on
//(1.1.19, EN and FR, entity extraction and text-to-SQL): it stays selectable for those two tasks,
//and this list also feeds the eval graph's filters (t2sevalexecgraph.inc.php).
$arrallowedmodels=array("gpt-6-sol","gpt-6.1-sol","gpt-6.1-terra","gpt-6-astra","gpt-6-luna","claude-opus-5-5");
//2026-10-28: the answer-entity classifier can also pick OpenAI's Decisions
//API (gpt-6-luna in closed-choice mode, POST /v1/decisions). An API that predates this name
//returns nothing from the classifier and keeps the text-to-SQL step's own result_entity.
//2026-10-28: the classifier and the complex question offer OpenAI models only; the
//Claude, Gemini, Gemma, o1 and o3 entries leave these two lists. A saved cookie naming one of
//them no longer validates and falls back to the default.
$arrallowedresultentitymodels=array("gpt-6-sol","gpt-6.1-sol","gpt-6.1-terra","gpt-6-astra","gpt-6-luna","gpt-6-luna-decisions");
$arrallowedcomplexmodels=array(
	"gpt-6-sol",
	"gpt-6.1-sol",
	"gpt-6.1-terra",
	"gpt-6-astra",
	"gpt-6-luna"
);
//2026-10-28: OpenAI Fast mode per task, ticked in the settings and kept in
//the `fasttasks` cookie (comma-separated keys below). Faster, steadier generation for about twice
//the price, GPT-6 models only. Sent only when ticked, so an API that predates the four fields
//never sees them.
$arrfasttasks=array(
	"entity_extraction"=>"llm_fast_entity_extraction",
	"text2sql"=>"llm_fast_text2sql",
	"complex"=>"llm_fast_complex",
	"result_entity"=>"llm_fast_result_entity"
);

//The Fast tasks saved in the cookie, filtered to the known keys.
function f_text2sqlfasttasks() {
	global $arrfasttasks;
	$arrtasks=array();
	if (isset($_COOKIE['fasttasks'])) {
		foreach (explode(",",(string)$_COOKIE['fasttasks']) as $strtask) {
			$strtask=trim($strtask);
			if (isset($arrfasttasks[$strtask]) && !in_array($strtask,$arrtasks,true)) {
				$arrtasks[]=$strtask;
			}
		}
	}
	return $arrtasks;
}

//$intretrievefromcache=false;
//$intstoretocache=false;
$intretrievefromcache=true;
$intstoretocache=true;

//Private mode (lib/private-mode.inc.php): the prefix map used further down to set $intprivate,
//and the second lock of the read-write pages. The database connection is opened a few lines
//below, with the read-write account when $intdbreadwrite is true, so the refusal must happen
//here: a page asking for that account outside a private folder gets HTTP 403 before the
//credentials are even loaded.
include_once(__DIR__."/private-mode.inc.php");
if (isset($intdbreadwrite) && $intdbreadwrite) {
	f_privatemoderequire();
}

//Plugins (lib/plugins.inc.php): loaded for a private request only. A public request never
//includes a plugin file, and every hook called below then returns "" or its value unchanged.
include_once(__DIR__."/plugins.inc.php");
f_pluginsload(f_privatemoderequest());

include(__DIR__."/database/global-light-secrets.inc.php");
$strtext2sqlapibluedomainurl=env('TEXT2SQL_API_BLUE_URL', isset($strtext2sqlapibluedomainurl) ? $strtext2sqlapibluedomainurl : '');
$strtext2sqlapigreendomainurl=env('TEXT2SQL_API_GREEN_URL', isset($strtext2sqlapigreendomainurl) ? $strtext2sqlapigreendomainurl : '');
$strtext2sqlapikeyname=env('TEXT2SQL_API_KEY_NAME', isset($strtext2sqlapikeyname) ? $strtext2sqlapikeyname : '');
$strtext2sqlapikeyvalue=env('TEXT2SQL_API_KEY_VALUE', isset($strtext2sqlapikeyvalue) ? $strtext2sqlapikeyvalue : '');
$strtmdbapidomainurl="https://api.themoviedb.org";
$strlanguagecountry="en-US";
$strlanguage="en";

// Set the default timezone to Paris, France, BEFORE the first getdate(): $strnow (the footer date)
// is computed right below and was UTC when this call came later. php.ini also says
// Europe/Paris since then; this line stays so the page does not depend on the mounted php.ini.
date_default_timezone_set('Europe/Paris');

//Retrieving current date
$dattemp=getdate();
$lngnowday=f_stringtolong($dattemp['mday']);
$lngnowmonth=f_stringtolong($dattemp['mon']);
$lngnowyear=f_stringtolong($dattemp['year']);
$lngnowhour=f_stringtolong($dattemp['hours']);
$lngnowminute=f_stringtolong($dattemp['minutes']);
$lngnowsecond=f_stringtolong($dattemp['seconds']);

$strnowday=sprintf("%02d",$lngnowday);
$strnowmonth=sprintf("%02d",$lngnowmonth);
$strnowyear=sprintf("%04d",$lngnowyear);
$strnowhour=sprintf("%02d",$lngnowhour);
$strnowminute=sprintf("%02d",$lngnowminute);
$strnowsecond=sprintf("%02d",$lngnowsecond);
$strnow=$strnowyear."-".$strnowmonth."-".$strnowday." ".$strnowhour.":".$strnowminute.":".$strnowsecond;

$intexecute=true;
$lngdatabaseversion=1.92;
$userid_session=1;

//Canonical public base URL of the site (SITE_BASE_URL in .env, such as https://www.example.com), used to build
//the absolute URLs required by the social preview tags (og:url, og:image). Empty by default: the tags that need
//an absolute URL are then left out.
$strsitebaseurl=rtrim(trim((string)env('SITE_BASE_URL','')),"/");

if (!function_exists('f_sitename')) {
	function f_sitename() {
		//Purpose: Name of the site, shown in the page titles, the social preview tags and the About page
		//Returns: SITE_NAME of .env, or "T2S" when it is not set
		$strname=trim((string)env('SITE_NAME',''));
		return ($strname!="") ? $strname : "T2S";
	}
}

$strtmdbimagespathbaseurl="https://image.tmdb.org/t/p/w342";
$strtmdbimagesoriginalpathbaseurl="https://image.tmdb.org/t/p/original";
//$strtmdbimagespathbaseurl="https://image.tmdb.org/t/p/w780";
$strtmdbpersonimagespathbaseurl="https://media.themoviedb.org/t/p/w64_and_h64_face";
$strtmdbpersonimagespathbaseurl185="https://media.themoviedb.org/t/p/w185";
$strtmdbmovieimagespathbaseurl="https://media.themoviedb.org/t/p/w130_and_h195_bestv2";
$strtmdbmovieimagespathbaseurl185="https://media.themoviedb.org/t/p/w185";
$strtmdbmovieimagespathbaseurl342="https://image.tmdb.org/t/p/w342";
$strtmdbcollectionimagespathbaseurl="https://media.themoviedb.org/t/p/w130_and_h195_bestv2";


$dbformat="M";
if ($dbhost!="" && $dbuser!="") {
	//MySQL host and username are known, so we can connect to the database
	$conn=f_mysqlconnect("$dbhost","$dbuser","$dbpwd");
	if (f_mysqlerrno())
	{
		$intdbavailable=false;
	}
	else
	{
		f_mysqlselectdb("$dbname");
		if (f_mysqlerrno())
		{
			$intdbavailable=false;
		}
		else
		{
			$intdbavailable=true;
		}
	}
}

function f_htmlspecialchars($val) {
	return htmlspecialchars((string)$val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function f_htmlattr($val) {
	$str=(string)$val;
	$str=str_replace(array("\r","\n")," ",$str);
	$str=trim(preg_replace('/\s+/', ' ', strip_tags($str)));
	return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function f_request2($strfieldname,$intcaptcha=0,$strcomponent7="",$strcomponent8="",$intcomponent14="")
{
	//Purpose: Retrieves field value from a previous form
	//Params:	$strfieldname: Field name
	//Returns: Field value (string)
	//Quote character is substituted so that any value can be used anywhere in a tag or tag parameter, because quote character is always used as a delimiter
	global $intregisterglobals;
	global $intdebug;
	
	if ($intregisterglobals) {
		//Register_globals is On
		global ${$strfieldname};
		${$strfieldname}=str_replace("\'","&#039;",${$strfieldname});
		return str_replace('\"','&#039;',${$strfieldname});
	} else {
		//Register_globals is Off
		//$intpreventinjections=0;
		$intpreventinjections=1;
		//$intdebug=1;
		$strpostval=isset($_POST[$strfieldname]) ? $_POST[$strfieldname] : "";
		$strtemp=isset($_GET[$strfieldname]) ? $_GET[$strfieldname] : $strpostval;
		if ($intdebug && 0) {
			echo "<br />-----------------------------------------------------------";
			echo "<br />f_request2('$strfieldname',$intcaptcha,$strcomponent7,$strcomponent8,$intcomponent14) -> '".$strtemp."'";
		}
		if ($intpreventinjections) {
			//We prevent injections
			if ($intcaptcha!=1) {
				// On verifie la valeur recuperee pour eviter toutes attaques d injection
				if (is_array($strtemp)) {
					if (!empty($strtemp)) {
						$strtemp = f_protect_injection($strtemp,2,$strcomponent7,$strcomponent8,$intcomponent14);
					}
				} else {
					if (strlen($strtemp)>0) {
						$strtemp = f_protect_injection($strtemp,1,$strcomponent7,$strcomponent8,$intcomponent14);
					}
				}
			}
		}
		/*
		//Correction dans l'url
		$strtemp = str_replace('&amp;', '&', $strtemp);
		*/
		$strtemp = str_replace("\'","&#039;",$strtemp);
		if ($intdebug && 0) {
			echo "<br />f_request2('$strfieldname') -> '".$strtemp."'";
		}
		//$intdebug=0;
		return str_replace("%7b$%7b","",str_replace("{\${","",str_replace("\'","&#039;",$strtemp)));
	}
}

function f_requestisfieldset($strfieldname)
{
	if (!isset($_GET[$strfieldname]) && !isset($_POST[$strfieldname])) {
		//This field is not included in the GET or POST variables
		$lngresult=false;
	} else {
		$lngresult=true;
	}
	return $lngresult;
}




function f_stringtolong($strparam,$lngdefault=0)
{
	//Purpose: Converts text to numeric value
	//Params:	strParam: Text to convert
	//Returns: Value

	$lngresult=0;
	if (strlen($strparam)==0)
	{
		//Empty string
		$lngresult=$lngdefault;
	}
	else
	{
		//Conversion is possible
		$lngresult=intval($strparam);
	}
	return $lngresult;
}

function f_stringtodouble($strparam,$dbldefault=0)
{
	//Purpose: Converts text to numeric value
	//Params:	strParam: Text to convert
	//Returns: Value

	$dblresult=0;
	if (strlen($strparam)==0)
	{
		//Empty string
		$dblresult=$dbldefault;
	}
	else
	{
		//Conversion is possible
		$dblresult=doubleval($strparam);
	}
	return $dblresult;
}

function f_stringtosql($strtext)
{
	//$strresult=" '";
	//$strresult=$strresult.str_replace("'","''",$strtext);
	//$strresult=$strresult."'";
	return "'".str_replace("'","''",$strtext)."'";
}

function f_floattosql($dblval)
{
	$strresult=strval($dblval);
	if (strlen($strresult)==0)
	{
		$strresult="0";
	}
	$strresult=str_replace(",",".",$strresult);
	return $strresult;
}

function f_longtosql($lngval)
{
	$strresult=strval($lngval);
	if (strlen($strresult)==0)
	{
		$strresult="0";
	}
	return $strresult;
}

function f_datetosql($intyear,$intmonth,$intday)
{
	//Purpose: Converts a date provided by 3 values (Year, Month and Day) to a date value that can be included in a SQL query
	//				 Transformation depends on the database type (Microsoft Access of SQL Server)
	//Params:	intYear: Year of the date
	//				 intMonth: Month of the date
	//				 intDay: Day of the date
	//Returns: Text expression of the date

	global $dbformat;
	global $strmssqldateformat;

	switch ($dbformat)
	{
	case "A":
		//Microsoft Access database
		break;
	case "S":
		//SQL Server database
		switch ($strmssqldateformat)
		{
		case "\\1/\\2/\\3":
			switch (f_mssqlmode()) {
			case 2:
				//ODBC
				//ymd date format: YYYY-MM-DD
				$strresult="'".sprintf("%04d",$intyear)."-".sprintf("%02d",$intmonth)."-".sprintf("%02d",$intday)."'";
				break;
			case 1:
			default:
				//MSSQL
				//ymd date format: YYYY/MM/DD
				$strresult="'".sprintf("%04d",$intyear)."/".sprintf("%02d",$intmonth)."/".sprintf("%02d",$intday)."'";
				break;
			}
			break;
		case "\\2/\\3/\\1":
			//US date format: MM/DD/YYYY
			$strresult="'".sprintf("%02d",$intmonth)."/".sprintf("%02d",$intday)."/".sprintf("%04d",$intyear)."'";
			break;
		default:
			//FR date format: DD/MM/YYYY
			$strresult="'".sprintf("%02d",$intday)."/".sprintf("%02d",$intmonth)."/".sprintf("%04d",$intyear)."'";
			break;
		}
		break;
	case "M":
	case "":
		//MySQL database
		$strresult="'".sprintf("%04d",$intyear)."-".sprintf("%02d",$intmonth)."-".sprintf("%02d",$intday)."'";
		break;
	case "O":
		//Oracle database
		break;
	}
	return $strresult;
}

function f_datetimetosql($intyear,$intmonth,$intday,$inthour,$intminute,$intsecond)
{
	//Purpose: Converts a date and time provided by 6 values (Year, Month, Day, Hour, Minute and Second) to a date and time value that can be included in a SQL query
	//				 Transformation depends on the database type (Microsoft Access of SQL Server)
	//Params:	intYear: Year of the date
	//				 intMonth: Month of the date
	//				 intDay: Day of the date
	//				 intHour: Hour of the time
	//				 intMinute: Minute of the time
	//				 intSecond: Second of the time
	//Returns: Text expression of the date and time

	global $dbformat;
	global $strmssqldateformat;

	switch ($dbformat)
	{
	case "A":
		//Microsoft Access database
		break;
	case "S":
		//SQL Server database
		switch ($strmssqldateformat)
		{
		case "\\1/\\2/\\3":
			switch (f_mssqlmode()) {
			case 2:
				//ODBC
				//ymd datetime format: YYYY-MM-DD hh:mm:ss
				$strresult="'".sprintf("%04d",$intyear)."-".sprintf("%02d",$intmonth)."-".sprintf("%02d",$intday)." ".sprintf("%02d",$inthour).":".sprintf("%02d",$intminute).":".sprintf("%02d",$intsecond)."'";
				break;
			case 1:
			default:
				//MSSQL
				//ymd datetime format: YYYY/MM/DD hh:mm:ss
				$strresult="'".sprintf("%04d",$intyear)."/".sprintf("%02d",$intmonth)."/".sprintf("%02d",$intday)." ".sprintf("%02d",$inthour).":".sprintf("%02d",$intminute).":".sprintf("%02d",$intsecond)."'";
				break;
			}
			break;
		case "\\2/\\3/\\1":
			//US date format: MM/DD/YYYY
			$strresult="'".sprintf("%02d",$intmonth)."/".sprintf("%02d",$intday)."/".sprintf("%04d",$intyear)." ".sprintf("%02d",$inthour).":".sprintf("%02d",$intminute).":".sprintf("%02d",$intsecond)."'";
			break;
		default:
			//FR date format: DD/MM/YYYY
			$strresult="'".sprintf("%02d",$intday)."/".sprintf("%02d",$intmonth)."/".sprintf("%04d",$intyear)." ".sprintf("%02d",$inthour).":".sprintf("%02d",$intminute).":".sprintf("%02d",$intsecond)."'";
			break;
		}
		break;
	case "M":
	case "":
		//MySQL database (default)
		$strresult="'".sprintf("%04d",$intyear)."-".sprintf("%02d",$intmonth)."-".sprintf("%02d",$intday)." ".sprintf("%02d",$inthour).":".sprintf("%02d",$intminute).":".sprintf("%02d",$intsecond)."'";
		break;
	case "O":
		//Oracle database
		break;
	}
	return $strresult;
}

function f_timetosql($inthour,$intminute,$intsecond)
{
	//Purpose: Converts a time provided by 3 values (Year, Month, Day, Hour, Minute and Second) to a time value that can be included in a SQL query
	//				 Transformation depends on the database type (Microsoft Access of SQL Server)
	//Params:	intHour: Hour of the time
	//				 intMinute: Minute of the time
	//				 intSecond: Second of the time
	//Returns: Text expression of the time

	global $dbformat;

	switch ($dbformat)
	{
	case "A":
		//Microsoft Access database
		break;
	case "S":
		//SQL Server database
		if($intsecond!=null)
		{
			$strresult="'".sprintf("%02d",$inthour).":".sprintf("%02d",$intminute).":".sprintf("%02d",$intsecond)."'";
		}
		else 
			$strresult="'".sprintf("%02d",$inthour).":".sprintf("%02d",$intminute)."'";
		break;
	case "M":
	case "":
		//MySQL database (default)
		if($intsecond!=null)
		{
			$strresult="'".sprintf("%02d",$inthour).":".sprintf("%02d",$intminute).":".sprintf("%02d",$intsecond)."'";
		}
		else 
			$strresult="'".sprintf("%02d",$inthour).":".sprintf("%02d",$intminute)."'";
		break;
	case "O":
		//Oracle database
		break;
	}
	return $strresult;
}

function f_begintrans()
{
//	global $intdebug;
//
//	if ($intdebug)
//	{
//		echo "<br />f_begintrans()";
//	}
	//Begin a transaction if database format allows it

	global $dbformat;

	switch ($dbformat)
	{
	case "A":
		//Microsoft Access database; transaction ok

		break;
	case "S":
		//SQL Server database; transaction ok
		$strsql="BEGIN TRANSACTION;";
		$result = f_mssqlquery($strsql);
		//echo "<br />f_mssqlquery(\"$strsql\") -> ".$result;
		break;
	case "M":
	case "":
		//MySQL database; no transaction
		break;
	case "O":
		//Oracle database; transaction ok

		break;
	}
}

function f_committrans()
{
//	global $intdebug;
//
//	if ($intdebug)
//	{
//		echo "<br />f_committrans()";
//	}
	//Commit a transaction if database format allows it

	global $dbformat;

	switch ($dbformat)
	{
	case "A":
		//Microsoft Access database; transaction ok

		break;
	case "S":
		//SQL Server database; transaction ok
		$strsql="COMMIT TRANSACTION;";
		$result = f_mssqlquery($strsql);
		//echo "<br />f_mssqlquery(\"$strsql\") -> ".$result;
		break;
	case "M":
	case "":
		//MySQL database; no transaction
		break;
	case "O":
		//Oracle database; transaction ok

		break;
	}
}

function f_rollbacktrans()
{
//	global $intdebug;
//
//	if ($intdebug)
//	{
//		echo "<br />f_committrans()";
//	}
	//Rollback a transaction if database format allows it

	global $dbformat;

	switch ($dbformat)
	{
	case "A":
		//Microsoft Access database; transaction ok

		break;
	case "S":
		//SQL Server database; transaction ok
		$strsql="ROLLBACK TRANSACTION;";
		$result = f_mssqlquery($strsql);
		//echo "<br />f_mssqlquery(\"$strsql\") -> ".$result;
		break;
	case "M":
	case "":
		//MySQL database; no transaction
		break;
	case "O":
		//Oracle database; transaction ok

		break;
	}
}

function f_sqlexecute($strsql,$intverbose=true)
{
	global $intdebug;
	global $intsqldebug;
	global $intexecute;
	global $intsqlrecording;
	global $dbformat;
	global $intsqlerr;

	//echo "<br />f_sqlexecute()<br />"."\r\n".$strsql."\r\nsize=".strlen($strsql);
	//echo "<br />f_sqlexecute()<br />"."\r\n".substr($strsql,0,335)."\r\nsize=".strlen($strsql);
	//Execution of SQL query
	if ($intexecute)
	{
		//echo "<br />\$intsqldebug=$intsqldebug";
		if ($intsqldebug)
		{
			echo "<br />\r\n$strsql";
		}
		switch ($dbformat)
		{
		case "A":
			//Microsoft Access database

			break;
		case "S":
			//SQL Server database
			if (!$intdebug)
			{
				$result=@f_mssqlquery($strsql);
			}
			else
			{
				$result=f_mssqlquery($strsql);
			}
			//echo "<br />f_mssqlquery(\"$strsql\") -> ".$result;
			break;
		case "M":
		case "":
			//MySQL database
			if (!$intdebug)
			{
				$result=@f_mysqlquery($strsql);
				if (f_mysqlerrno())
				{
					$intsqlerr=true;
				}
			}
			else
			{
				$result=f_mysqlquery($strsql);
				if (f_mysqlerrno() && $intverbose)
				{
					echo "<br />SQL : '".$strsql."'";
					echo "<br />Error ".f_mysqlerrno()." : ".f_mysqlerror()."\n";
					$intsqlerr=true;
					//exit;
				}
			}
			break;
		case "O":
			//Oracle database

			break;
		}
	}
	if ($intsqlrecording)
	{
		f_sqlrecord($strsql);
	}
	//return 1;
}

function f_sqlinsert($strsql,$intaddstdfields=false)
{
	//This function must be called inside a SQL transaction
	global $intdebug;
	global $intsqldebug;
	global $intexecute;
	global $intsqlrecording;
	global $userid_session;
	global $dbformat;
	global $strsqlns;
	global $intsqlerr;
	global $lngdatabaseversion;

	//if ($intdebug)
	//{
	//	echo "<br />f_sqlinsert()<br />\n".$strsql."\n";
	//}
	if ($intexecute)
	{
		if ($intaddstdfields)
		{
			if ($strsqlns=="T_")
			{
				$strtimupdatedfieldname="DT_MODIFIED";
				$strdatcreatfieldname="DT_CREATED";
			}
			else
			{
				$strtimupdatedfieldname="TIM_UPDATED";
				$strdatcreatfieldname="DAT_CREAT";
			}
			//Computing creation date/time for that new record
			$dattemp=getdate();
			$lngtempday=$dattemp['mday'];
			$lngtempmonth=$dattemp['mon'];
			$lngtempyear=$dattemp['year'];
			$lngtemphour=$dattemp['hours'];
			$lngtempminute=$dattemp['minutes'];
			$lngtempsecond=$dattemp['seconds'];
			$strsqlsep=", ";
			$strsql.=$strsqlsep."DELETED = ".f_longtosql(0);
			$strsql.=$strsqlsep."ID_CREATOR = ".f_longtosql($userid_session);
			$strsql.=$strsqlsep.$strdatcreatfieldname." = ".f_datetosql($lngtempyear,$lngtempmonth,$lngtempday);
			$strsql.=$strsqlsep.$strtimupdatedfieldname." = ".f_datetimetosql($lngtempyear,$lngtempmonth,$lngtempday,$lngtemphour,$lngtempminute,$lngtempsecond);
			$strsql.=$strsqlsep."ID_OWNER = ".f_longtosql($userid_session);
			if ($lngdatabaseversion>=1.71)
			{
				//The ID_USER_UPDATED field should also be updated with the ID of the current user
				$strsql.=$strsqlsep."ID_USER_UPDATED = ".f_longtosql($userid_session);
			}
		}
		//echo "<br />\$intsqldebug=$intsqldebug";
		if ($intsqldebug)
		{
			echo "<br />\r\n$strsql";
		}
		switch ($dbformat)
		{
		case "A":
			//Microsoft Access database

			break;
		case "S":
			//SQL Server database
			$result = f_mssqlquery($strsql);
			//echo "<br />f_mssqlquery(\"$strsql\") -> ".$result;
			//Getting key value for the new record
			$strsqlnewrec="SELECT @@IDENTITY AS COMPTEUR";
			$result=f_mssqlquery($strsqlnewrec);
			//echo "<br />f_mssqlquery(\"$strsqlnewrec\") -> ".$result;
			$rstemp=f_mssqlfetcharray($result);
			$strresult=f_string($rstemp["COMPTEUR"]);
			break;
		case "M":
		case "":
			//MySQL database
			if (!$intdebug)
			{
				$result=@f_mysqlquery($strsql);
				if (@f_mysqlerrno())
				{
					$intsqlerr=true;
				}
				$strresult=f_string(@f_mysqlinsertid());
			}
			else
			{
				$result=f_mysqlquery($strsql);
				if (f_mysqlerrno())
				{
					echo "<br />SQL : '".$strsql."'";
					echo "<br />Error ".f_mysqlerrno()." : ".f_mysqlerror()."\n";
					$intsqlerr=true;
					//exit;
				}
				$strresult=f_string(f_mysqlinsertid());
			}
			break;
		case "O":
			//Oracle database

			break;
		}
		if ($intdebug)
		{
			echo "<br\nNew ID -> ".$strresult;
		}
	}
	if ($intsqlrecording)
	{
		f_sqlrecord($strsql);
	}
	return $strresult;
}

function f_mysqlmode() {
	global $intdebug;
	static $lngmysqlmode;
	
	//Final mode where mysqli is the only way to connect
	//We can connect to MySQL database through mysqli functions
	$lngmysqlmode=2;
	//Transition mode where we have to check between mysql and mysqli functions
	if (f_stringtolong($lngmysqlmode)==0) {
		if (function_exists("mysqli_connect")) {
			//We can connect to MySQL database through mysqli functions
			$lngmysqlmode=2;
		} else {
			//We have to connect to MySQL database through mysql functions
			$lngmysqlmode=1;
		}
	}
	/* 
	//Initial mode where mysql is the only way to connect
	//We have to connect to MySQL database through mysql functions
	$lngmysqlmode=1;
	*/
	return $lngmysqlmode;
}

switch (f_mysqlmode()) {
case 2:
	//mysqli
	$strmysqlmode = "mysqli";
	break;
case 1:
default:
	//mysql
	$strmysqlmode = "mysql";
	break;
}

function f_mysqlconnect($strmysqlhost,$strmysqluser,$strmysqlpwd) {
	global $intdebug;
	global $dbconn;
	
	if ($intdebug) {
		echo "<br />f_mysqlconnect('$strmysqlhost', '$strmysqluser','***')";
	}
	switch (f_mysqlmode()) {
	case 2:
		//mysqli
		//Added the following code because the following options are by default since PHP 8.1:
		//mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		//Disable MySQLi error reporting
		mysqli_report(MYSQLI_REPORT_OFF);
		if ($intdebug) {
			echo "<br />mysqli_connect('$strmysqlhost', '$strmysqluser','***')";
		}
		$dbconn = mysqli_connect($strmysqlhost,$strmysqluser,$strmysqlpwd);
		//Suivi de la connexion pour la fermeture explicite de fin de script (f_mysqlcloseall())
		f_mysqlregisterconnection($dbconn);
		if ($intdebug) {
			// Check connection
			if (mysqli_connect_errno()) {
				echo "<br />Failed to connect to MySQL: " . mysqli_connect_error();
			}
		}
  		break;
	case 1:
	default:
		//mysql
		$dbconn = mysql_connect($strmysqlhost,$strmysqluser,$strmysqlpwd);
		break;
	}
	return $dbconn;
}

function f_mysqlselectdb($strmysqlname, $dbmysqlconn=null) {
	global $intdebug;
	global $dbconn;

	if ($intdebug) {
		echo "<br />f_mysqlselectdb('$strmysqlname', '$dbmysqlconn')";
	}
	switch (f_mysqlmode()) {
	case 2:
		//mysqli
		if ($dbmysqlconn===null) {
			//$dbmysqlconn parameter not set, so we use the default mysql connection 
			if ($intdebug) {
				echo "<br />\$dbmysqlconn parameter not set, so we use the default mysql connection";
				echo "<br />mysqli_select_db('\$dbconn','$strmysqlname')";
			}
			$lngresult=mysqli_select_db($dbconn, $strmysqlname);
		} else {
			//$dbmysqlconn parameter set, so we use it 
			if ($intdebug) {
				echo "<br />\$dbmysqlconn parameter set, so we use it ";
				echo "<br />mysqli_select_db('\$dbmysqlconn','$strmysqlname')";
			}
			$lngresult=mysqli_select_db($dbmysqlconn, $strmysqlname);
		}
		break;
	case 1:
	default:
		//mysql
		if ($dbmysqlconn===null) {
			//$dbmysqlconn parameter not set, so we use the default mysql connection 
			if ($intdebug) {
				echo "<br />\$dbmysqlconn parameter not set, so we use the default mysql connection";
				echo "<br />mysql_select_db('$strmysqlname', '$dbconn')";
			}
			$lngresult=mysql_select_db($strmysqlname, $dbconn);
		} else {
			//$dbmysqlconn parameter set, so we use it 
			if ($intdebug) {
				echo "<br />\$dbmysqlconn parameter set, so we use it ";
				echo "<br />mysql_select_db('$strmysqlname', '$dbmysqlconn')";
			}
			$lngresult=mysql_select_db($strmysqlname, $dbmysqlconn);
		}
		break;
	}
	return $lngresult;
}

function f_mysqlerrno($dbmysqlconn=null) {
	global $intdebug;
	global $dbconn;
	
	$strresult="";
	switch (f_mysqlmode()) {
	case 2:
		//mysqli
		if ($dbmysqlconn===null) {
			//$dbmysqlconn parameter not set, so we use the default mysql connection 
			//NB PHP8 : mysqli_connect() rend FALSE quand la connexion echoue (1040 Too many
			//connections par exemple). isset(false) vaut TRUE -> mysqli_errno(false) etait fatal
			//et masquait la degradation prevue ($intdbavailable=false). Sans handle, le code
			//d erreur est porte par mysqli_connect_errno().
			if ($dbconn instanceof mysqli) {
				$strresult = mysqli_errno($dbconn);
			} else {
				$strresult = mysqli_connect_errno();
			}
		} else {
			//$dbmysqlconn parameter set, so we use it 
			if ($dbmysqlconn instanceof mysqli) {
				$strresult = mysqli_errno($dbmysqlconn);
			} else {
				$strresult = mysqli_connect_errno();
			}
		}
		break;
	case 1:
	default:
		//mysql
		$strresult = mysql_errno();
		break;
	}
	return $strresult;
}

function f_mysqlerror() {
	global $intdebug;
	global $dbconn;
	
	$strresult="";
	switch (f_mysqlmode()) {
	case 2:
		//mysqli
		//Sans handle (connexion refusee), le message d erreur est porte par mysqli_connect_error()
		if ($dbconn instanceof mysqli) {
			$strresult = mysqli_error($dbconn);
		} else {
			$strresult = mysqli_connect_error();
		}
		break;
	case 1:
	default:
		//mysql
		$strresult = mysql_error();
		break;
	}
	return $strresult;
}

function f_mysqlquery($strsqlquery) {
	global $intdebug;
	global $dbconn;
	
	$result="";
	switch (f_mysqlmode()) {
	case 2:
		//mysqli
		if ($intdebug) {
			echo "<br />mysqli_query(\$dbconn,'$strsqlquery')";
		}
		//Pas de handle, pas de requete : mysqli_query(false,...) serait fatal
		if ($dbconn instanceof mysqli) {
			$result = mysqli_query($dbconn,$strsqlquery);
			if ($intdebug) {
				echo " -> ";
				print_r($result);
			}
		}
		break;
	case 1:
	default:
		//mysql
		$result = mysql_query($strsqlquery);
		break;
	}
	return $result;
}

function f_mysqlnumrows($result) {
	global $intdebug;
	
	switch (f_mysqlmode()) {
	case 2:
		//mysqli
		$lngresult = mysqli_num_rows($result);
		break;
	case 1:
	default:
		//mysql
		$lngresult = mysql_num_rows($result);
		break;
	}
	return $lngresult;
}

function f_mysqlinsertid() {
	global $intdebug;
	global $dbconn;
	
	switch (f_mysqlmode()) {
	case 2:
		//mysqli
		//$lngresult = mysqli_insert_id();
		$lngresult = mysqli_insert_id($dbconn);
		break;
	case 1:
	default:
		//mysql
		$lngresult = mysql_insert_id();
		break;
	}
	//echo "<br />f_mysqlinsertid() -> ".$lngresult;
	return $lngresult;
}

function f_mysqlfetcharray($result,$intresulttype=null) {
	global $intdebug;
	
	switch (f_mysqlmode()) {
	case 2:
		//mysqli
		if ($intresulttype===null) {
			$arrresult = mysqli_fetch_array($result);
		} else {
			$arrresult = mysqli_fetch_array($result,$intresulttype);
		}
		break;
	case 1:
	default:
		//mysql
		if ($intresulttype===null) {
			$arrresult = mysql_fetch_array($result);
		} else {
			$arrresult = mysql_fetch_array($result,$intresulttype);
		}
		break;
	}
	return $arrresult;
}

function f_mysqlfetchrow($result,$intresulttype=null) {
	global $intdebug;
	
	//if ($intdebug) {
	//	echo "<br />f_mysqlfetchrow(\$result)";
	//}
	switch (f_mysqlmode()) {
	case 2:
		//mysqli
		if ($intresulttype===null) {
			$arrresult = mysqli_fetch_row($result);
		} else {
			$arrresult = mysqli_fetch_row($result,$intresulttype);
		}
		break;
	case 1:
	default:
		//mysql
		if ($intresulttype===null) {
			$arrresult = mysql_fetch_row($result);
		} else {
			$arrresult = mysql_fetch_row($result,$intresulttype);
		}
		break;
	}
	return $arrresult;
}

function f_mysqlfetchassoc($result,$intresulttype=null) {
	global $intdebug;
	
	switch (f_mysqlmode()) {
	case 2:
		//mysqli
		if ($intresulttype===null) {
			$arrresult = mysqli_fetch_assoc($result);
		} else {
			$arrresult = mysqli_fetch_assoc($result,$intresulttype);
		}
		break;
	case 1:
	default:
		//mysql
		if ($intresulttype===null) {
			$arrresult = mysql_fetch_assoc($result);
		} else {
			$arrresult = mysql_fetch_assoc($result,$intresulttype);
		}
		break;
	}
	return $arrresult;
}

function f_mysqlfetchobject($result) {
	global $intdebug;
	
	switch (f_mysqlmode()) {
	case 2:
		//mysqli
		$arrresult = mysqli_fetch_object($result);
		break;
	case 1:
	default:
		//mysql
		$arrresult = mysql_fetch_object($result);
		break;
	}
	return $arrresult;
}

function f_mysqldataseek($result,$lngpos) {
	global $intdebug;
	
	switch (f_mysqlmode()) {
	case 2:
		//mysqli
		$booresult = mysqli_data_seek($result,$lngpos);
		break;
	case 1:
	default:
		//mysql
		$booresult = mysql_data_seek($result,$lngpos);
		break;
	}
	return $booresult;
}

function f_mysqlfreeresult($result) {
	global $intdebug;
	
	switch (f_mysqlmode()) {
	case 2:
		//mysqli
		$booresult = mysqli_free_result($result);
		break;
	case 1:
	default:
		//mysql
		$booresult = mysql_free_result($result);
		break;
	}
	return $booresult;
}

function f_mysqlclose($dbmysqlconn=null) {
	global $intdebug;
	global $dbconn;
	
	$booresult=false;
	switch (f_mysqlmode()) {
	case 2:
		//mysqli
		//NB PHP8 : mysqli_connect() rend FALSE quand la connexion echoue, et isset(false) vaut TRUE.
		//La garde doit donc etre instanceof mysqli, sinon mysqli_close(false) part en TypeError.
		if ($dbmysqlconn===null) {
			//$dbmysqlconn parameter not set, so we use the default mysql connection 
			if ($dbconn instanceof mysqli) {
				$booresult = $dbconn->close();
			}
		} else {
			//$dbmysqlconn parameter set, so we use it 
			if ($dbmysqlconn instanceof mysqli) {
				$booresult = $dbmysqlconn->close();
			}
		}
		break;
	case 1:
	default:
		//mysql
		if ($dbmysqlconn===null) {
			//$dbmysqlconn parameter not set, so we use the default mysql connection 
			$booresult = mysql_close($dbconn);
		} else {
			//$dbmysqlconn parameter set, so we use it 
			$booresult = mysql_close($dbmysqlconn);
		}
		break;
	}
	return $booresult;
}

//Coupe-circuit de la fermeture automatique des connexions MariaDB en fin de script.
//1 par defaut ; poser T2S_MYSQL_AUTOCLOSE=0 dans .env restaure le comportement anterieur
//sans redeploiement de code.
$intmysqlautoclose=1;
if (trim((string)env("T2S_MYSQL_AUTOCLOSE","1"))==="0") {
	$intmysqlautoclose=0;
}

/**
 * Enregistre une connexion MariaDB pour qu elle soit fermee explicitement en fin de script.
 *
 * Appelee automatiquement par f_mysqlconnect(), donc les pages n ont rien a faire. Seules les
 * connexions ouvertes hors de f_mysqlconnect() (un new mysqli direct, par exemple) doivent
 * l appeler elles-memes.
 *
 * Le registre ne conserve que des WeakReference, et cela n est pas un detail : il ne doit surtout
 * PAS prolonger la duree de vie des handles. PHP ferme deja une connexion des que sa derniere
 * variable disparait ; une reference forte ici la garderait ouverte jusqu a la fin du script, soit
 * l inverse du but recherche. Ce depot n ouvre aujourd hui qu une connexion par page (ligne ~256),
 * mais le registre supporte le motif "bascule puis reconnexion" au cas ou.
 *
 * Cette couche mysqli est partagee avec une autre base de code : toute correction ici doit
 * y etre reportee, et reciproquement.
 *
 * @param mysqli|false|null $dbmysqlconn Connexion rendue par mysqli_connect()
 * @return bool TRUE si la connexion est desormais suivie, FALSE sinon
 */
function f_mysqlregisterconnection($dbmysqlconn) {
	global $arrmysqlconnection;
	static $intshutdownarmed=0;
	
	if (!($dbmysqlconn instanceof mysqli)) {
		//mysqli_connect() a rendu FALSE, ou on tourne sur l ancienne extension mysql : rien a suivre
		return false;
	}
	if (!isset($arrmysqlconnection) || !is_array($arrmysqlconnection)) {
		$arrmysqlconnection=array();
	}
	if (class_exists("WeakReference")) {
		//PHP 7.4+
		$arrmysqlconnection[]=WeakReference::create($dbmysqlconn);
	}
	if (!$intshutdownarmed) {
		//Arme a la premiere connexion seulement : un script qui ne touche pas la base ne paie rien
		$intshutdownarmed=1;
		//Deux niveaux volontairement : une fonction enregistree PENDANT le shutdown est ajoutee en
		//fin de file. La fermeture passe donc apres tous les autres handlers de fin de script, y
		//compris ceux qu un developpement ulterieur ajouterait et qui voudraient encore requeter.
		register_shutdown_function(function() {
			register_shutdown_function("f_mysqlcloseall");
		});
	}
	return true;
}

/**
 * Ferme toutes les connexions MariaDB encore ouvertes enregistrees pendant le script.
 *
 * Branchee sur register_shutdown_function() par f_mysqlregisterconnection(), donc executee quelle
 * que soit la facon dont le script se termine : fin normale, exit(), die(), exception non rattrapee
 * ou erreur fatale.
 *
 * @return int Nombre de connexions effectivement fermees par cet appel
 */
function f_mysqlcloseall() {
	global $intdebug;
	global $dbconn;
	global $arrmysqlconnection;
	global $intmysqlautoclose;
	
	$lngclosed=0;
	if (isset($intmysqlautoclose) && f_stringtolong($intmysqlautoclose)==0) {
		//Coupe-circuit arme : on laisse PHP faire seul, comme avant
		return $lngclosed;
	}
	if (isset($arrmysqlconnection) && is_array($arrmysqlconnection)) {
		foreach ($arrmysqlconnection as $objweakreference) {
			if (!($objweakreference instanceof WeakReference)) {
				continue;
			}
			//NULL quand PHP a deja libere le handle : la connexion est alors deja fermee
			$dbmysqlconn=$objweakreference->get();
			if ($dbmysqlconn instanceof mysqli) {
				$lngclosed+=f_mysqlcloseconnection($dbmysqlconn);
			}
		}
	}
	//Filet pour les cas ou le registre n a pas pu suivre la connexion courante (PHP < 7.4, donc sans
	//WeakReference). Une fermeture en double est absorbee sans dommage par f_mysqlcloseconnection().
	if (isset($dbconn) && $dbconn instanceof mysqli) {
		$lngclosed+=f_mysqlcloseconnection($dbconn);
	}
	$arrmysqlconnection=array();
	if ($intdebug) {
		echo "<br />f_mysqlcloseall() -> ".$lngclosed;
	}
	return $lngclosed;
}

/**
 * Ferme une connexion MariaDB en absorbant les erreurs, fermeture en double comprise.
 *
 * @param mysqli $dbmysqlconn Connexion a fermer
 * @return int 1 si cet appel a bien ferme la connexion, 0 sinon
 */
function f_mysqlcloseconnection($dbmysqlconn) {
	$lngclosed=0;
	try {
		//PHP8 : close() sur une connexion deja fermee leve Error, et tester l etat au prealable
		//(thread_id, ping) leve exactement la meme Error. On tente donc la fermeture et on absorbe.
		if (f_mysqlclose($dbmysqlconn)) {
			$lngclosed=1;
		}
	} catch (Throwable $objthrowable) {
		//Connexion deja fermee ou inutilisable : il n y a plus rien a liberer
	}
	return $lngclosed;
}

function f_sqlselect($strsql)
{
	global $dbformat;
	global $intsqlerr;
	global $intdebug;
	global $intsqldebug;

	if ($intsqldebug)
	{
		echo "<br />\r\n$strsql";
	}
	switch ($dbformat)
	{
	case "A":
		//Microsoft Access database

		break;
	case "S":
		//SQL Server database
		$result=f_mssqlquery($strsql);
		//echo "<br />f_mssqlquery(\"$strsql\") -> ".$result;
		//echo "<br />".$strsql;
		break;
	case "M":
	case "":
		//MySQL database
		if (!$intdebug)
		{
			$result=@f_mysqlquery($strsql);
			if (@f_mysqlerrno())
			{
				$intsqlerr=true;
			}
		}
		else
		{
			$result=f_mysqlquery($strsql);
			if (f_mysqlerrno())
			{
				echo "<br />SQL : '".$strsql."'";
				echo "<br />Error ".f_mysqlerrno()." : ".f_mysqlerror()."\n";
				$intsqlerr=true;
				//exit;
			}
		}
		break;
	case "O":
		//Oracle database

		break;
	}
	return $result;
}

function f_sqlresultrowcount($result)
{
	global $dbformat;
	global $intdebug;

	switch ($dbformat)
	{
	case "A":
		//Microsoft Access database

		break;
	case "S":
		//SQL Server database
		return f_mssqlnumrows($result);
		break;
	case "M":
	case "":
		//MySQL database
		if (!$intdebug)
		{
			return @f_mysqlnumrows($result);
		}
		else
		{
			return f_mysqlnumrows($result);
		}
		break;
	case "O":
		//Oracle database

		break;
	}
}

function f_sqlresultfetcharray($result,$intresulttype=null)
{
	global $dbformat;
	global $intdebug;

	switch ($dbformat)
	{
	case "A":
		//Microsoft Access database

		break;
	case "S":
		//SQL Server database
		return f_mssqlfetcharray($result);
		break;
	case "M":
	case "":
		//MySQL database
		if (!$intdebug)
		{
			return @f_mysqlfetcharray($result,$intresulttype);
		}
		else
		{
			return f_mysqlfetcharray($result,$intresulttype);
		}
		break;
	case "O":
		//Oracle database

		break;
	}
}

function f_sqlerrno()
{
	global $dbformat;

	switch ($dbformat)
	{
	case "A":
		//Microsoft Access database

		break;
	case "S":
		//SQL Server database

		break;
	case "M":
	case "":
		//MySQL database
		return f_mysqlerrno();
		break;
	case "O":
		//Oracle database

		break;
	}
}

function f_sqlfreeresult($result)
{
	global $dbformat;
	global $intdebug;

	if (isset($result))
	{
		switch ($dbformat)
		{
		case "A":
			//Microsoft Access database

			break;
		case "S":
			//SQL Server database
			return f_mssqlfreeresult($result);
			break;
		case "M":
		case "":
			//MySQL database
			if (!$intdebug)
			{
				return @f_mysqlfreeresult($result);
			}
			else
			{
				return f_mysqlfreeresult($result);
			}
			break;
		case "O":
			//Oracle database

			break;
		}
	}
}

function f_sqlinsertarray($strtable,$arrfields,$intaddstdfields=false)
{
	//This function must be called inside a SQL transaction
	global $intdebug;
	global $intfinetuning;
	global $strsqloutputpath;
	global $strlogoutputfilename;
	global $intlog;
	global $intexecute;
	global $userid_session;
	global $strsqlns;
	global $lngdatabaseversion;

	if ($intdebug) {
		echo "<br />f_sqlinsertarray($strtable,\$arrfields,";
		if ($intaddstdfields)
			echo "true";
		else
			echo "false";
		echo ")";
	}
	if ($intexecute)
	{
		if ($intdebug) {
			echo "<br />\$intexecute=true";
		}
		if ($intaddstdfields)
		{
			if ($intdebug) {
				echo "<br />\$intaddstdfields=true";
			}
			if ($strsqlns=="T_")
			{
				$strtimupdatedfieldname="DT_MODIFIED";
				$strdatcreatfieldname="DT_CREATED";
			}
			else
			{
				$strtimupdatedfieldname="TIM_UPDATED";
				$strdatcreatfieldname="DAT_CREAT";
			}
			//Computing creation date/time for that new record
			$dattemp=getdate();
			$lngtempday=$dattemp['mday'];
			$lngtempmonth=$dattemp['mon'];
			$lngtempyear=$dattemp['year'];
			$lngtemphour=$dattemp['hours'];
			$lngtempminute=$dattemp['minutes'];
			$lngtempsecond=$dattemp['seconds'];
			/*
			if ($intdebug) {
				echo "<br />$lngtempyear-$lngtempmonth-$lngtempday $lngtemphour:$lngtempminute:$lngtempsecond";
			}
			*/
			if (!array_key_exists('DELETED', $arrfields)) {
				$arrfields["DELETED"]=f_longtosql(0);
			}
			if (!array_key_exists('ID_CREATOR', $arrfields)) {
				$arrfields["ID_CREATOR"]=f_longtosql($userid_session);
			}
			if (!array_key_exists($strdatcreatfieldname, $arrfields)) {
				$arrfields[$strdatcreatfieldname]=f_datetosql($lngtempyear,$lngtempmonth,$lngtempday);
			}
			if (!array_key_exists($strtimupdatedfieldname, $arrfields)) {
				$arrfields[$strtimupdatedfieldname]=f_datetimetosql($lngtempyear,$lngtempmonth,$lngtempday,$lngtemphour,$lngtempminute,$lngtempsecond);
			}
			if (!array_key_exists('ID_OWNER', $arrfields)) {
				$arrfields["ID_OWNER"]=f_longtosql($userid_session);
			}
			if ($lngdatabaseversion>=1.71)
			{
				//The ID_USER_UPDATED field should also be updated with the ID of the current user
				if (!array_key_exists('ID_USER_UPDATED', $arrfields)) {
					$arrfields["ID_USER_UPDATED"]=f_longtosql($userid_session);
				}
			}
		}
		if ($intdebug) {
			echo "<br />\$arrfields: <br />";
			print_r($arrfields);
		}
		$strfields="";
		$strvalues="";
		foreach ($arrfields as $strkey => $strvalue)
		{
			if ($strfields!="")
			{
				$strfields.=", ";
				$strvalues.=", ";
			}
			$strfields.=$strkey;
			$strvalues.=$strvalue;
		}
		$strsql="INSERT INTO ".$strtable." (".$strfields.") VALUES (".$strvalues.")";
		if ($intdebug)
		{
			echo "<br />$strsql";
		}
		if ($intfinetuning) {
			$strsqloutput=$strsql;
			$strsqloutput.="\r\n";
			f_appendtofile($strsqloutput, $strsqloutputpath."/".$strlogoutputfilename);
		}
		$strresult=f_sqlinsert($strsql,false);
	}
	return $strresult;
}

function f_sqlupdatearray($strtable,$arrfields,$strsqlcond,$intaddstdfields=false)
{
	global $intdebug;
	global $intfinetuning;
	global $strsqloutputpath;
	global $strlogoutputfilename;
	global $intlog;

	if ($intdebug)
	{
		echo "<br />f_sqlupdatearray($strtable,\$arrfields,$strsqlcond,";
		if ($intaddstdfields)
			echo "true";
		else
			echo "false";
		echo ")<br />\$arrfields : ";
		print_r($arrfields);
	}
	$strsqlselect="SELECT * FROM ".$strtable." ".$strsqlcond;
	$result=f_sqlselect($strsqlselect);
	if($rstemp=f_sqlresultfetcharray($result))
	{
		//Updated record does already exist
		if ($intdebug)
		{
			echo "<br />Updated record does already exist";
		}
		$strfields="";
		foreach ($arrfields as $strkey => $strvalue)
		{
			//unset($strtemp);
			$strtemp=$rstemp[$strkey];
			if(is_string($strvalue) && substr($strvalue,0,1)=="'")
			{
				$strtemp=f_stringtosql($strtemp);
			}
			if($strvalue!=$strtemp)
			{
					//echo "<br />\$strvalue=$strvalue ; \$rstemp[\$strkey]=".$strtemp;
					//Value changed for this field
					if ($intfinetuning) {
						echo "<br />Value must change for the '$strkey' field: $strtemp -> $strvalue";
					}
					if ($strfields=="")
					{
						$strfields=" SET ";
					}
					else
					{
						$strfields.=", ";
					}
					$strfields.=$strkey."=".$strvalue;
			}
		}
		if ($strfields!="")
		{
			//One field or more did really change
			$strsql="UPDATE ".$strtable.$strfields;
			if ($intdebug || $intfinetuning)
			{
				echo "<br />$strsql $strsqlcond";
			}
			if ($intfinetuning) {
				$strsqloutput=$strsql.$strsqlcond;
				$strsqloutput.="\r\n";
				f_appendtofile($strsqloutput, $strsqloutputpath."/".$strlogoutputfilename);
			}
			/*
			echo "<br />\$strsql=$strsql";
			echo "<br />\$strsqlcond=$strsqlcond";
			*/
			$strresult=f_sqlupdate($strsql,$strsqlcond,$intaddstdfields);
		}
	}
	else
	{
		//Record requested does not exist already
		//So we must insert a new record
		if ($intdebug)
		{
			echo "<br />Record requested does not exist already. So we must insert a new record";
		}
		return f_sqlinsertarray($strtable,$arrfields,$intaddstdfields);
	}
	f_sqlfreeresult($result);
}

function f_sqlupdate($strsql,$strsqlcond,$intaddstdfields=false)
{
	global $strsqlns;
	global $lngdatabaseversion;
	global $userid_session;
	global $intdebug;

	//This function must be called inside a SQL transaction
	if ($intaddstdfields)
	{
		if ($strsqlns=="T_")
		{
			$strtimupdatedfieldname="DT_MODIFIED";
		}
		else
		{
			$strtimupdatedfieldname="TIM_UPDATED";
		}
		//Computing update date/time for that record
		$dattemp=getdate();
		$lngtempday=$dattemp['mday'];
		$lngtempmonth=$dattemp['mon'];
		$lngtempyear=$dattemp['year'];
		$lngtemphour=$dattemp['hours'];
		$lngtempminute=$dattemp['minutes'];
		$lngtempsecond=$dattemp['seconds'];
		$strsqlsep=", ";
		$strsql.=$strsqlsep.$strtimupdatedfieldname." = ".f_datetimetosql($lngtempyear,$lngtempmonth,$lngtempday,$lngtemphour,$lngtempminute,$lngtempsecond);
		if ($lngdatabaseversion>=1.71)
		{
			//The ID_USER_UPDATED field should also be updated with the ID of the current user
			$strsql.=$strsqlsep."ID_USER_UPDATED = ".f_longtosql($userid_session);
		}
	}
	if ($intdebug)
	{
		echo "<br />".$strsql." ".$strsqlcond;
	}
	return f_sqlexecute($strsql." ".$strsqlcond);
}

function f_sqlupdateassocarray($strtablename,$arrarrays,$strsqlwhere,$stridfield,$intstdfields=false)
{
	global $intdebug;

	if ($intdebug)
	{
		echo "<br />f_sqlupdateassocarray($strtablename,\$arrarrays,$strsqlwhere,$stridfield,";
		if ($intaddstdfields)
			echo "true";
		else
			echo "false";
		echo ")<br />\$arrarrays : ";
		print_r($arrarrays);
		echo "<br />";
	}
	$stridlist="";
	if (is_array($arrarrays)) {
		foreach($arrarrays as $strkey => $arrcouples)
		{
			//Updating existing records or
			//Inserting new records
			f_sqlupdatearray($strtablename,$arrcouples," WHERE ".$stridfield."=".$strkey." AND ".$strsqlwhere,$intstdfields);
			if ($stridlist!="")
			{
				$stridlist.=", ";
			}
			$stridlist.=$strkey;
		}
		$strcond=$strsqlwhere;
		if ($stridlist!="")
		{
			//Deleting records that have not been inserted or updated
			$strcond.=" AND ".$stridfield." NOT IN (".$stridlist.")";
		}
		$strsqlselect="SELECT * FROM ".$strtablename." WHERE ".$strcond;
		if ($intdebug)
		{
			echo "<br />\$strsqlselect=$strsqlselect";
		}
		$result=f_sqlselect($strsqlselect);
		if(f_sqlresultrowcount($result)>0)
		{
			if ($intdebug)
			{
				echo " -> ".f_sqlresultrowcount($result)." Records to delete";
			}
			//One line or more to delete
			$strsqldelete="DELETE FROM ".$strtablename." WHERE ".$strcond;
			if ($intdebug)
			{
				echo "<br />\$strsqldelete=$strsqldelete";
			}
			f_sqlexecute($strsqldelete);
		}
		f_sqlfreeresult($result);
	}
}

function f_sqldelete($strsql)
{
	return f_sqlexecute($strsql);
}

function f_sqlrecordfilename()
{
	global $dbldatabaseversion;
	global $userid_session;

	return "rec-".sprintf("%08d",$dbldatabaseversion)."-".time()."-".sprintf("%05d",$userid_session).".sql";
}

function f_sqlrecord($strsql)
{
	global $strsqlrecfile_session;
	global $intislbaapi;

	if (strlen($strsqlrecfile_session)==0 || !file_exists($strsqlrecfile_session))
	{
		//echo "<br />Creating new SQL export file";
		global $strsqlrecordingpath;
		$strsqlrecfile_session=$strsqlrecordingpath."/".f_sqlrecordfilename();
		if (!$intislbaapi) {
			//Done unless we are handling an API call
			f_sessionstore("strsqlrecfile_session");
		}
	}
	//SQL recording is enabled
	if (substr($strsql,strlen($strsql)-2,2)!="\r\n")
	{
		if (substr($strsql,strlen($strsql)-1,1)!=";")
			$strsql.=";";
		$strsql.="\r\n";
	}
	f_appendtofile($strsql,$strsqlrecfile_session);
}

function f_sqlrerecord($strsql)
{
	global $strsqlrerecfile_session;
	global $intislbaapi;

	if (strlen($strsqlrerecfile_session)==0 || !file_exists($strsqlrerecfile_session))
	{
		global $strsqlrerecordingpath;
		$strsqlrerecfile_session=$strsqlrerecordingpath."/".f_sqlrecordfilename();
		if (!$intislbaapi) {
			//Done unless we are handling an API call
			f_sessionstore("strsqlrerecfile_session");
		}
	}
	//SQL re recording is enabled
	if (substr($strsql,strlen($strsql)-1,1)!=";")
		$strsql.=";";
	$strsql.="\r\n";
	f_appendtofile($strsql,$strsqlrecfile_session);
}

function f_sqltablesupdate($strsqlselect,$strsqlupdate)
{
	global $strtablename;

	$inti=0;
	$result=f_sqlselect($strsqlselect);
	while($rstemp=f_sqlresultfetcharray($result))
	{
		$lngid=$rstemp[ID_TABLE];
		$strtablename=$rstemp[NOM_SQL];

		$strsql=f_substvarauto($strsqlupdate);
		//echo "<br />\$strsql=$strsql";

		//List of possible separators
		$arrseps=array(";%0D%0A",";%0D",";%0D",";\r",";\n");
		foreach ($arrseps as $strsep)
		{
			if (strpos($strsql,$strsep)!==false)
			{
				//Separator found
				break;
			}
		}
		$arrsql=explode($strsep,$strsql);
		f_sqlexecutearray($arrsql);

		$inti++;
		//$strmessage="Tables modifiees";
	}
	f_sqlfreeresult($result);
	return $inti;
}

function f_mysqlrealescapestring($strstring) {
	global $intdebug;
	global $dbconn;

	$strresult=$strstring;
	if ($intdebug && 0) {
		echo "<br />f_mysqlrealescapestring('$strstring')";
	}
	switch (f_mysqlmode()) {
	case 2:
		//mysqli
		//if ($dbmysqlconn===null) {
		//	//$dbmysqlconn parameter not set, so we use the default mysql connection 
		if ($dbconn===null) {
			//If the database is not open yet, we cannot use the mysqli_real_escape_string function 
			$strresult=$strstring;
		} else {
			//$dbconn vaut FALSE quand la connexion a echoue : le test ===null ne suffit pas
			if ($dbconn instanceof mysqli) {
				$strresult=mysqli_real_escape_string($dbconn, $strstring);
			}
		}
		//} else {
		//	//$dbmysqlconn parameter set, so we use it 
		//	$strresult=mysqli_real_escape_string($dbmysqlconn, $strstring);
		//}
		break;
	case 1:
	default:
		//mysql
		//if ($dbmysqlconn===null) {
		//	//$dbmysqlconn parameter not set, so we use the default mysql connection 
		//	//if ($intdebug) {
		//	//	echo "<br />mysql_real_escape_string('$strstring', '$dbmysqlconn')";
		//	//}
		if ($dbconn===null) {
			//If the database is not open yet, we cannot use the mysql_real_escape_string function 
			$strresult=$strstring;
		} else {
			$strresult=mysql_real_escape_string($strstring, $dbconn);
		}
		//} else {
		//	//$dbmysqlconn parameter set, so we use it 
		//	$strresult=mysql_real_escape_string($strstring, $dbmysqlconn);
		//}
		break;
	}
	if ($intdebug && 0) {
		echo "<br />f_mysqlrealescapestring('$strstring') -> ".$strresult;
	}
	return $strresult;
}

function f_filetostring($strfile,$intforce=0)
{
	$strresult="";
	if ($strfile!="")
	{
		if (substr($strfile,0,2)=="//") {
			$strfile="http:".$strfile;
		}
		if (file_exists($strfile) || $intforce)
		{
			//echo "<br />f_filetostring($strfile)";
			$file = fopen($strfile, "r");
			if (!$file)
			{
				return "";
			}
			else
			{
				while (!feof($file))
				{
					$line = fgets($file, 1024);
					$strresult.=$line;
				}
				fclose($file);
				return $strresult;
			}
		}
	}
}

function f_stringtofile($strstring, $strfile)
{
	if ($strfile!="")
	{
		$stroldcontent=f_filetostring($strfile);
		if ($strstring!=$stroldcontent || !file_exists($strfile))
		{
			$file = fopen($strfile, "w");
			if (!$file)
			{
				return "";
			}
			else
			{
				fputs($file,$strstring);
				fclose($file);
			}
		}
	}
}

function f_appendtofile($strstring, $strfile)
{
	if ($strfile!="" && $strstring!="")
	{
		$file = @fopen($strfile, "a");
		if (!$file)
		{
			return "";
		}
		else
		{
			fputs($file,$strstring);
			fclose($file);
		}
	}
}

function f_string($strparam,$strdefault="")
{
	//Purpose: Converts text/NULL to text value
	//Params:	strParam: Text to convert
	//Returns: Value

	$strresult="";
	//Conversion may be possible
	$strresult=strval($strparam);
	if ($strresult=="" && $strdefault!="") {
		$strresult=strval($strdefault);
	}
	return $strresult;
}

function f_fieldstringtoarray($strdescfield)
{
	if (strpos($strdescfield,",")!==false)
		$arrfields=explode(",",$strdescfield);
	else
		$arrfields=explode("|",$strdescfield);
	return $arrfields;
}

function f_descfromcode($strtable,$strfieldcode,$strfielddesc,$intcode,$strwhere="",$strassoctable="")
{
	global $intdebug;
	
	$strresult="";
	if (strlen($strtable)>0 && strlen($strfieldcode)>0 && strlen($strfielddesc)>0 && strlen($intcode)>0)
	{
		/*
		if (strpos($strfielddesc,",")!==false)
			$arrfields=explode(",",$strfielddesc);
		else
			$arrfields=explode("|",$strfielddesc);
		*/
		$arrfields=f_fieldstringtoarray($strfielddesc);
		$strsql="SELECT * ";
		/*
		for ($inti=0; $inti<count($arrfields); $inti++)
		{
			if ($inti>0) {
				$strsql.=", ";
			}
			$strsql.=$arrfields[$inti];
		}
		*/
		$strsql.=" FROM ".$strtable;
		if ($strassoctable!="")
		{
			$strsql.=", ".$strassoctable;
		}
		$strsql.=" WHERE ";
		if ($strassoctable!="") {
			$strsql.=$strtable.".".$strfieldcode;
		} else {
			$strsql.=$strfieldcode;
		}
		$strsql.=" = ".$intcode.($strwhere != "" ? " AND ".$strwhere : "");
		if ($intdebug)
		{
			echo "<br />\$strsql=$strsql";
		}
		$result = f_sqlselect($strsql);
		if (f_sqlresultrowcount($result) > 0)
		{
			$rstemp = f_sqlresultfetcharray($result);
			$strtemp="";
			for ($inti=0; $inti<count($arrfields); $inti++)
			{
				$strtemp.=f_string($rstemp[$arrfields[$inti]])." ";
			}
			$strresult=trim($strtemp);
		}
		f_sqlfreeresult($result);
	}
	return $strresult;
}

function f_getwikidatalabel($strwikidataid,$strlang="fr",&$arrwdsource=null,$intallentitytables=0)
{
	// Libelle d'une entite Wikidata dans une langue (2026-08-18).
	//
	// Remplace le motif dissemine dans une douzaine de fichiers : un SELECT sur
	// T_WC_WIKIDATA_ITEM_V1 avec LANG = <langue>, puis un second avec LANG = 'en' si le
	// premier rend vide. Ce motif faisait deja le bon repli de langue ; il ne lisait que
	// la mauvaise source.
	//
	// ORDRE DES SOURCES : la langue demandee dans V2, puis l'anglais dans V2, puis la
	// ligne V1. Les deux premiers sont dans un meme COALESCE, donc un seul aller-retour
	// par table.
	//
	// POURQUOI L'ANGLAIS AVANT V1, et non l'inverse. Mesure du 2026-08-18 : sur les
	// 100 271 entites que V2 connait sans clef 'fr', 100 186 (99,92 %) portaient le meme
	// texte en 'fr' et en 'en' dans V1, et Wikidata n'a effectivement aucun libelle
	// francais pour elles (Q8093 Nintendo, Q9684 The New York Times). V1 les servait par
	// le repli automatique du service SPARQL, et il avait raison : pour un nom propre,
	// l'anglais EST le bon affichage francais. On reproduit donc ce comportement dans V2
	// au lieu de dependre de V1 pour l'obtenir.
	//
	// LE REPLI V1 RESTE, et il n'est pas decoratif : environ 36 % des libelles francais
	// en viennent encore, faute des entites que V2 n'importe pas.
	// A SUPPRIMER AVEC LES TABLES V1, pas avant.
	//
	// LABELS_JSON avant LABEL_EN : la colonne scalaire n'est renseignee que sur une
	// partie des lignes, le document JSON est la porte d'entree.
	// $arrwdsource, passe par reference, recoit la provenance reelle : source (V2 ou V1),
	// table, et LANGUE EFFECTIVEMENT SERVIE. Sans lui, un libelle affiche ne dit pas d ou
	// il vient, et pendant la migration c est precisement ce qu on a besoin de savoir.
	// Les appelants qui l ignorent ne changent pas : le parametre est optionnel.
	$arrwdsource=array("source"=>"","table"=>"","lang"=>"");
	if ($strwikidataid=="") { return ""; }
	if (!preg_match('/^[a-z]{2,3}$/',$strlang)) { $strlang="fr"; }
	$strjsonpath='$.'.$strlang;
	// T_WC_WIKIDATA_ITEM SEULEMENT PAR DEFAUT (2026-08-18).
	// Un libelle de CLASSE, ce que resolvent les appelants, vit dans ITEM. Elargir a MOVIE,
	// SERIE et PERSON ferait remonter un TITRE DE FILM sur les 7 805 lignes de
	// T_WC_T2S_AWARD dont l ID_WIKIDATA designe une oeuvre, sequelle d un ancien mecanisme d import.
	// Un titre de film affiche comme nom de recompense est pire qu un vide : un blanc se
	// remarque, une valeur plausible et fausse ne se remarque pas.
	// $intallentitytables=1 n a de sens que pour le diagnostic, qui veut precisement voir ou
	// l entite se trouve, y compris quand elle n a rien a y faire.
	$arrwdv2tables=array("T_WC_WIKIDATA_ITEM");
	if ($intallentitytables) { $arrwdv2tables=array("T_WC_WIKIDATA_ITEM","T_WC_WIKIDATA_MOVIE","T_WC_WIKIDATA_SERIE","T_WC_WIKIDATA_PERSON"); }
	foreach ($arrwdv2tables as $strwdv2table)
	{
		// Le CASE rend la langue qui a gagne : c est la seule facon de distinguer un
		// libelle francais d un repli anglais, les deux sortant du meme COALESCE.
		$strsqlwdlabel="SELECT COALESCE(";
		$strsqlwdlabel.="JSON_UNQUOTE(JSON_EXTRACT(LABELS_JSON, ".f_stringtosql($strjsonpath).")), ";
		$strsqlwdlabel.="JSON_UNQUOTE(JSON_EXTRACT(LABELS_JSON, ".f_stringtosql('$.en').")), ";
		$strsqlwdlabel.="NULLIF(LABEL_EN,'')) AS LBL, ";
		$strsqlwdlabel.="CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(LABELS_JSON, ".f_stringtosql($strjsonpath).")) IS NOT NULL THEN ".f_stringtosql($strlang)." ";
		$strsqlwdlabel.="WHEN JSON_UNQUOTE(JSON_EXTRACT(LABELS_JSON, ".f_stringtosql('$.en').")) IS NOT NULL THEN 'en' ";
		$strsqlwdlabel.="WHEN NULLIF(LABEL_EN,'') IS NOT NULL THEN 'en (LABEL_EN)' ";
		$strsqlwdlabel.="ELSE '' END AS LBLLANG ";
		$strsqlwdlabel.="FROM ".$strwdv2table." ";
		$strsqlwdlabel.="WHERE ID_WIKIDATA = ".f_stringtosql($strwikidataid)." LIMIT 1";
		$resultwdlabel=f_sqlselect($strsqlwdlabel);
		if ($resultwdlabel) {
			if ($rstempwdlabel=f_sqlresultfetcharray($resultwdlabel)) {
				$strwdlabel=trim((string)$rstempwdlabel['LBL']);
				$strwdlabellang=trim((string)$rstempwdlabel['LBLLANG']);
				if ($strwdlabel!="") {
					$arrwdsource=array("source"=>"V2","table"=>$strwdv2table,"lang"=>$strwdlabellang);
					f_sqlfreeresult($resultwdlabel);
					return $strwdlabel;
				}
			}
			f_sqlfreeresult($resultwdlabel);
		}
	}
	// Repli V1, a retirer avec les tables V1.
	foreach (array($strlang,"en") as $strwdv1lang)
	{
		$strsqlwdlabel="SELECT LABEL FROM T_WC_WIKIDATA_ITEM_V1 ";
		$strsqlwdlabel.="WHERE ID_WIKIDATA = ".f_stringtosql($strwikidataid)." AND LANG = ".f_stringtosql($strwdv1lang)." LIMIT 1";
		$strwdlabel=trim(f_fieldfromquery($strsqlwdlabel));
		if ($strwdlabel!="") {
			$arrwdsource=array("source"=>"V1","table"=>"T_WC_WIKIDATA_ITEM_V1","lang"=>$strwdv1lang);
			return $strwdlabel;
		}
	}
	return "";
}

function f_fieldfromquery($strsql,$strfield="")
{
	global $intdebug;
	global $intexecute;

	if ($intdebug)
	{
		echo "<br />f_fieldfromquery($strsql,$strfield)";
	}
	if (strlen($strsql)>0)	// && strlen($strfield)>0)
	{
		//Execution of SQL query
		if ($intexecute)
		{
			$result = f_sqlselect($strsql);
			if (f_sqlresultrowcount($result) > 0)
			{
				$rstemp = f_sqlresultfetcharray($result);
				//Result is not empty, so we can read values from database
				if ($strfield=="") {
					//return f_string($rstemp[0]);
					foreach ($rstemp as $strkey => $strvalue) {
						return f_string($strvalue);
					}
				} else {
					return f_string($rstemp[$strfield]);
				}
			}
			f_sqlfreeresult($result);
		}
	}
}

function f_fieldsfromquery($strsql,$strvars,$strfields)
{
	global $intdebug;
	global $intexecute;

	//echo "<br />f_fieldfromquery($strsql,$strfield)";
	if (strlen($strsql)>0 && strlen($strvars)>0 && strlen($strfields)>0)
	{
		//Execution of SQL query
		if ($intexecute)
		{
			$result = f_sqlselect($strsql);
			if (f_sqlresultrowcount($result)>0)
			{
				$rstemp = f_sqlresultfetcharray($result);
				//Result is not empty, so we can read values from database
				$arrvars=explode("|",$strvars);
				$arrfields=f_fieldstringtoarray($strfields);
				for ($inti=0;$inti<count($arrvars) && $inti<count($arrfields);$inti++)
				{
					if (strlen($arrvars[$inti])>0 && strlen($arrfields[$inti])>0)
					{
						global ${$arrvars[$inti]};
						${$arrvars[$inti]}=f_string($rstemp[$arrfields[$inti]]);
					}
				}
				//return f_string($rstemp[$strfield]);
			}
			f_sqlfreeresult($result);
		}
	}
}

function f_striphref($strtext)
{
	//echo "f_striphref(\$strtext)";
	return str_replace("<input type=\"image\" src=","<img src=",preg_replace("/<a [^>]*>/","",str_replace("</a>","",$strtext)));
}

function f_striphtmltags($document)
{
	
	// I didn't use preg eval (//e) since that is only available in PHP 4.0.
	// so, list your entities one by one here. I included some of the
	// more common ones.
							
	$search = array("'<script[^>]*?>.*?</script>'si",	// strip out javascript
					"'<(?:[a-z][a-z0-9-]*|/[a-z][a-z0-9-]*|!)[^<>]*>'si"	// strip out html tags: a tag opens on a letter, "/" or "!", so SQL "<>", "< 5", "<=" survive
					);				
	$replace = array(	"",
						"");
				
	$text = preg_replace($search,$replace,$document);
							
	return $text;
}

function f_protect_antinjectionsql($strvaluerequest) {
	/**
	* Fonction permettant de se premunir contre les injections sql via un liste noires
	* @param $strvaluerequest - string | Valeur a traiter
	* @return $strvaluerequest - string | Retourne la valeur traitee
	*/
	global $intdebug;
	global $strerrormessage;

	if ($intdebug && 0) {
		echo "<br />f_protect_antinjectionsql($strvaluerequest)";
	}
	// Initialisation de variable pour conditionnement de l'erreur
	$countfindwordban = 0;

	// Tableau contenant les mots bannis - regex permet de trouver uniquement les mots tels qu'ils sont defini et non (ex : selection)
	$arrbanlist = array (
		"/\binsert\b/u",
		"/\bselect\b/u",
		"/\bupdate\b/u",
		"/\bdelete\b/u",
		"/\bdistinct\b/u",
		"/\bhaving\b/u",
		"/\btruncate\b/u",
		"/\breplace\b/u",
		"/\bhandler\b/u",
		/* "/\blike\b/u", */
		"/\bprocedure\b/u",
		"/\blimit\b/u",
		"/\border by\b/u",
		"/\bgroup by\b/u"
	);

	// On boucle sur les valeurs du tableau pour y trouver les mots bannis
	foreach ($arrbanlist as $value) {
		// Si true, incrementation de la variable pour message erreur
		if (preg_match($value, $strvaluerequest)) {
			if ($intdebug && 0) {
				echo "<br /> Mot banni detecte : ".$value;
			}
			$countfindwordban++;
		}
	}

	// On supprime UNIQUEMENT tous les mots bannis
	$strvaluerequestnew = preg_replace($arrbanlist, '', $strvaluerequest);

	if ($intdebug && 0) {
		echo "<br> countfindwordban : ".$countfindwordban;
	}
	
	// Si mot(s) banni(s) SQL trouve alors message d'erreur
	if ($countfindwordban>0) {
		$strerrormessage = " -- Tentative d'injection SQL detectee ! -- <br>";
		//$strvaluerequest=$strvaluerequestnew;
	}
	if ($intdebug && 0) {
		echo "<br />f_protect_antinjectionsql($strvaluerequest) -> ".$strvaluerequest;
	}
	return $strvaluerequest;
}

if (!function_exists('f_protect_injection')) {
	/**
	* Fonction permettant de se premunir des divers attaques d injection possible
	* @param $strvalue - string | Valeur a traiter
	* @param $intcase - integer | 1 valeur seul / 2 tableau contenant les valeurs a traiter
	* @param $strcomponent7 - string | Valeur composant colonne 7
	* @param $strcomponent8 - string | Valeur composant colonne 8
	* @return $strprotectvalue - string | Retourne la valeur traitee
	*/
	function f_protect_injection($strvalue,$intcase=1,$strcomponent7="",$strcomponent8="",$intcomponent14="") {
		global $intdebug;
		global $strphpscript;
		global $dbformat;

		$dbformatold=$dbformat;
		$dbformat="M";

		// Initialisation variable, conditionnement niveau de protection
		$intdefaultprotect = 1;
		$boolfindcomponent7 = false;
		$boolfindcomponent8 = false;
		$boolfindcomponent14 = false;
		
		if ($intdebug && 0) {
			echo "<br />f_protect_injection($strvalue,$intcase,$strcomponent7,$strcomponent8,$intcomponent14)";
		}
		if ($intdebug && 0){
			switch ($intcase) {
			case 1:
				echo "<br> Valeur protection debut de traitement : ".$strvalue;
				break;
			case 2:
				echo "<br> Valeur protection debut de traitement : ";
				echo "<pre>";
				var_dump($strvalue);
				echo "</pre>";
				break;
			}
			echo "<br> intcase : ".$intcase;
			echo "<br> strphpscript : ".$strphpscript;
			echo "<br> Composant 7 : ".$strcomponent7;
			echo "<br> Composant 8 : ".$strcomponent8;
			echo "<br> Composant 14 : ".$intcomponent14;
		}
		
		// Recuperation de l'id de l'ecran pour requete ci-dessous
		$intphpscriptid = f_fieldfromquery("SELECT ID_FORM FROM T_WC_FORM WHERE DELETED!=1 AND SCRIPT LIKE ".f_stringtosql($strphpscript));
		
		if ($intdebug && 0)
			echo "<br> intphpscriptid : ".$intphpscriptid;
		
		// Recuperation de la table liee a l'ecran
		$strtablescript = f_fieldfromquery("SELECT NOM_SQL FROM T_WC_TABLE AS TT LEFT JOIN T_WC_FORM_TABLE AS TFT ON TT.ID_TABLE = TFT.ID_TABLE WHERE TFT.ID_FORM = ".f_longtosql($intphpscriptid));
		
		if ($intdebug && 0)
			echo "<br> strtablescript : ".$strtablescript;
		
		if ($strcomponent7!="" || $strcomponent8!="" || $intcomponent14!="") {
			// Tableau contenant les composant necessitant un traitement different sur la protection des donnees
			$arrcomponentlist = array(
				array('C','E'),
				array('C','T'),
				array('ML','ML'),
				array('EA','EA'),
				array('WY','WY'),
				array('C','ML')
			);

			foreach ($arrcomponentlist as $key => $arrcomponent) {
				if (in_array($strcomponent7, $arrcomponent)) {
					$boolfindcomponent7 = true;
					if (in_array($strcomponent8, $arrcomponent)) {
						$boolfindcomponent8 = true;
						break;
					}
				}
			}

			if (strpos($intcomponent14, ';')) {
				foreach (explode(';', $intcomponent14) as $transform) {
					switch ($transform) {
						case 12:
							$boolfindcomponent14 = true;
							break;
					}
				}
			} else {
				if ($intcomponent14 == 12)
					$boolfindcomponent14 = true;
			}
		}

		// Si la totalite des conditions est remplies, alors on n'utilise pas les fonctions suivantes :	htmlspecialchars() - f_striphtmltags() - htmlentites()
		if ($boolfindcomponent14 && $boolfindcomponent7 && $boolfindcomponent8)
			$intdefaultprotect = 2;

		// Table ou on effectue aucun traitement (modification uniquement par les DEV / SuperAdmin)
		$arrnotreatment = array(
			"T_WC_FORM",
			"T_WC_FIELD_FORM",
			"T_WC_FIELD_RESULT",
			"T_WC_FIELD_SEARCH",
			"T_WC_FORM_FIELDS_FORM",
			"T_WC_FORM_FIELDS_LIST",
			"T_WC_FORM_FIELDS_SEARCH",
			"T_WC_TABLE",
			"T_WC_EXPORT_PROFILE",
			"T_WC_EXPORT_FIELD",
			"T_WC_IMPORT_FIELD",
			"T_WC_IMPORT_PROFILE",
			"T_WC_MAIL",
			"T_WC_MAIL_ALERT",
			"T_WC_MAIL_ALERT_TARGET",
			"T_WC_WEBSITE",
			"T_WC_REQUEST",
			"T_WC_TEMPLATE",
			"T_WC_TECHNICAL_DATA",
			"T_WC_QUIZ_CRITERION"
		);

		// On verifie si la table actuel est indique ou non dans les tables sans traitement
		if (in_array($strtablescript, $arrnotreatment))
			$intdefaultprotect = 3;

		if ($intdebug && 0)
			echo "<br> intdefaultprotect : ".$intdefaultprotect;

		switch ($intcase) {
		case 1: // traitement classique
			$strprotectvalue = f_protect_verifchangevalue($strvalue,$intdefaultprotect);
			break;
		case 2: // traitement tableau
			// Creation nouveau tableau contiendra la totalite des valeurs proteger
			$arrvalueprotect = array();
			foreach ($strvalue as $key => $value) {
				// Push des valeurs protegees
				$arrvalueprotect[$key] = f_protect_verifchangevalue($value,$intdefaultprotect);
			}
			
			if ($intdebug && 0){
				echo "<br> Tableau traite : ";
				echo "<pre>";
				var_dump($arrvalueprotect);
				echo "</pre>";
			}
			
			$strprotectvalue = $arrvalueprotect;
			break;
		}
		
		if ($intdebug && 0) {
			echo "<br />f_protect_injection($strvalue,$intcase,$strcomponent7,$strcomponent8,$intcomponent14) -> ".$strprotectvalue;
		}
		
		$dbformat=$dbformatold;
		return $strprotectvalue;
	}
}

if (!function_exists('f_protect_verifchangevalue')) {
	/**
	* Effectue de multiple verification, afin de proteger de differentes attaques (injection sql/xss/injection code)
	* @param $strvalue - string | Valeur a verifier
	* @param $intdefaultprotect - int | Condition permettant un traitement differents selon certaines conditions definis en amont
	* @return $strprotectvalue - string | retourne la valeur proteger
	*/
	function f_protect_verifchangevalue($strvalue,$intdefaultprotect) {
		global $intdebug;
		
		if ($intdebug && 0) {
			echo "<br />f_protect_verifchangevalue($strvalue,$intdefaultprotect)";
		}
		switch ($intdefaultprotect) {
		case 1: // Protection par defaut, totalite des traitements
			// Passage dans plusieurs fonctions de verification (injection SQL, XSS attack, injection HTML)
			$strprotectvalue = f_mysqlrealescapestring(htmlspecialchars(f_striphtmltags(f_protect_antinjectionsql($strvalue))));
			if ($intdebug && 0)
				echo "<br> Valeur suppression caracteres speciaux : ".$strprotectvalue;

			// Suppression des balises HTML
			// $strprotectvalue = @htmlentities($strprotectvalue);
			if ($intdebug && 0)
				echo "<br> Valeur apres passage transformation caracteres speciaux html/js : ".$strprotectvalue;
			break;
		case 2: // protection moindre pour eviter erreur lors de l'enregistrement de certains champ (ex : modeles)
			$strprotectvalue = f_mysqlrealescapestring($strvalue);
			if ($intdebug && 0)
				echo "<br> Valeur suppression caracteres speciaux - traitement spécial : ".$strprotectvalue;
			break;
		case 3: // Aucun traitement effectue sur les donnees provenant de la table T_WC_FORM
			$strprotectvalue = $strvalue;

			if ($intdebug && 0)
				echo "<br> Aucun traitement - T_WC_FORM -";
			break;
		}

		if ($intdefaultprotect!=3) {
			// Verifie si la valeur n est pas un numeric
			if (is_numeric($strprotectvalue)==false)
				$strprotectvalue = str_replace("#", "\#", $strprotectvalue);
			else
				if (strpos($strprotectvalue, '+') || strpos($strprotectvalue, 'x'))
					$strprotectvalue = intval($strprotectvalue);

			if ($intdebug && 0)
				echo "<br> Valeur protection fin de traitement : ".$strprotectvalue."<br>";
		}
		if ($intdebug && 0) {
			echo "<br />f_protect_verifchangevalue($strvalue,$intdefaultprotect) -> ".$strprotectvalue;
		}
		return $strprotectvalue;
	}
}

function f_tmdbapirequest($strtmdbapiurl) {
	global $intdebug;
	global $strtmdbapidomainurl;
	global $strtmdbapikey;
	global $strtmdbapitoken;
	global $inttmdbuseapikey;
	
	$strpostfields="{}";
	
	if ($intdebug) {
		echo "<br />URL=".$strtmdbapidomainurl."/".$strtmdbapiurl;
		//echo "<br />\$strpostfields=$strpostfields";
	}
	if ($inttmdbuseapikey) {
		//Adding the API key to the URL
		$strtmdbapiurl.="?api_key=".$strtmdbapikey;
	}
	//Authentication is now using the API token provided in the HTTP header (see below)
	if ($intdebug) {
		//echo "<br />\$strpostfields: ";
		$strresultdebug=json_encode($strpostfields, JSON_PRETTY_PRINT);
		$stroutput=str_replace("\n","<br />",str_replace("<","&lt;",str_replace(" ","&nbsp;",$strresultdebug)));
		//echo "<br />".$stroutput;
	}
	$curl = curl_init();
	$arrhttpheader=array();
	$arrhttpheader[]="accept: application/json";
	if (!$inttmdbuseapikey) {
		//Adding the API token to the HTTP header
		$arrhttpheader[]="Authorization: Bearer ".$strtmdbapitoken;
	}
	$arrcurlparams=array(
	  CURLOPT_URL => $strtmdbapidomainurl."/".$strtmdbapiurl,
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => '',
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 0,
	  CURLOPT_FOLLOWLOCATION => true,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => 'GET',
	  CURLOPT_HTTPHEADER => $arrhttpheader,
	);
	curl_setopt_array($curl, $arrcurlparams);

	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);
	if ($err) {
		if ($intdebug) {
			echo "<br />cURL Error #:".$err;
		}
	}
	/*
	if ($intdebug) {
		echo "<br />";
		echo $response;
	}
	*/
	return $response;
}


function f_getpersonprofilepicture($strpersonprofilepath,$intdisplaylevel=1) {
	global $strtmdbpersonimagespathbaseurl;
	global $strtmdbpersonimagespathbaseurl185;
	
	if ($strpersonprofilepath=="") {
		$strpersonprofilefullpath="images/person-default-64x64.png";
	} else {
		switch ($intdisplaylevel) {
		case 2:
			$strpersonprofilefullpath=$strtmdbpersonimagespathbaseurl185.$strpersonprofilepath;
			break;
		case 1:
		default:
			$strpersonprofilefullpath=$strtmdbpersonimagespathbaseurl.$strpersonprofilepath;
			break;
		}
		
	}
	return $strpersonprofilefullpath;
}

function f_getcollectionprofilepicture($strcollectionprofilepath) {
	global $strtmdbcollectionimagespathbaseurl;
	
	if ($strcollectionprofilepath=="") {
		$strcollectionprofilefullpath="images/movie-default-130x195.png";
	} else {
		$strcollectionprofilefullpath=$strtmdbcollectionimagespathbaseurl.$strcollectionprofilepath;
	}
	return $strcollectionprofilefullpath;
}

function f_getmovieposterpicture($strmovieposterpath,$intdisplaylevel=3) {
	global $strtmdbmovieimagespathbaseurl;
	global $strtmdbmovieimagespathbaseurl185;
	global $strtmdbmovieimagespathbaseurl342;
	
	if ($strmovieposterpath=="") {
		$strmovieposterfullpath="images/movie-default-130x195.png";
	} else {
		switch ($intdisplaylevel) {
		case 4:
			$strmovieposterfullpath=$strtmdbmovieimagespathbaseurl342.$strmovieposterpath;
			break;
		case 3:
			$strmovieposterfullpath=$strtmdbmovieimagespathbaseurl185.$strmovieposterpath;
			break;
		case 2:
		default:
			$strmovieposterfullpath=$strtmdbmovieimagespathbaseurl.$strmovieposterpath;
			break;
		}
	}
	return $strmovieposterfullpath;
}

function f_getcompanylogopicture($strcompanylogopath) {
	global $strtmdbimagespathbaseurl;
	
	if ($strcompanylogopath=="") {
		$strcompanylogofullpath="images/movie-default-130x195.png";
	} else {
		$strcompanylogofullpath=$strtmdbimagespathbaseurl.$strcompanylogopath;
	}
	return $strcompanylogofullpath;
}

function f_getnetworklogopicture($strnetworklogopath) {
	global $strtmdbimagespathbaseurl;

	if ($strnetworklogopath=="") {
		$strnetworklogofullpath="images/movie-default-130x195.png";
	} else {
		$strnetworklogofullpath=$strtmdbimagespathbaseurl.$strnetworklogopath;
	}
	return $strnetworklogofullpath;
}

function f_getsyntheticimagepath($stritemclass,$lngiditem) {
	//2:3 card master produced by the synthetic-images repo (Project A): padded real logo for
	//companies/networks (decision #7), style-locked synthetic plate for the other classes.
	//IMAGE_PATH already carries the synthetic-images/ prefix relative to the shared_data root,
	//so the public URL only needs the leading slash (Apache vhost aliases /synthetic-images to
	//shared_data/synthetic-images). Returns "" when no validated image exists (or when the
	//T_WC_T2S_SYNTHETIC_IMAGE tables are not deployed yet) so callers fall back to TMDb images.
	global $strsqlns;

	$strsyntheticimagepath="";
	if ($lngiditem>0 && $stritemclass!="") {
		$strsql="SELECT SI.IMAGE_PATH FROM ".$strsqlns."T2S_ENTITY_IMAGE EI ";
		$strsql.="INNER JOIN ".$strsqlns."T2S_SYNTHETIC_IMAGE SI ON SI.ID_SYNTHETIC_IMAGE = EI.ID_SYNTHETIC_IMAGE ";
		$strsql.="WHERE EI.ITEM_CLASS = ".f_stringtosql($stritemclass)." AND EI.ID_ITEM = ".f_longtosql($lngiditem)." ";
		$strsql.="AND EI.IS_CHOSEN = 1 AND (EI.DELETED IS NULL OR EI.DELETED = 0) ";
		$strsql.="AND SI.STATUS = 'generated' AND SI.IS_VALIDATED = 1 AND (SI.DELETED IS NULL OR SI.DELETED = 0) ";
		$strsql.="LIMIT 1 ";
		$result=@f_sqlselect($strsql);
		if ($result) {
			if ($rstemp=f_sqlresultfetcharray($result)) {
				$strimagepath=trim($rstemp['IMAGE_PATH']);
				if ($strimagepath!="") {
					$strsyntheticimagepath="/".$strimagepath;
				}
			}
			f_sqlfreeresult($result);
		}
	}
	return $strsyntheticimagepath;
}

function f_getvideothumbnail($strvideokey,$strvideosite,$intdisplaylevel=1) {
	
	switch (strtolower($strvideosite)) {
	case "youtube":
		if ($strvideokey=="") {
			$strvideothumbnailfullpath="images/movie-default-130x195.png";
		} else {
			switch ($intdisplaylevel) {
			case 3:
				$strvideothumbnailfullpath="https://img.youtube.com/vi/".$strvideokey."/maxresdefault.jpg";
				break;
			case 2:
				$strvideothumbnailfullpath="https://img.youtube.com/vi/".$strvideokey."/hqdefault.jpg";
				break;
			case 1:
			default:
				$strvideothumbnailfullpath="https://img.youtube.com/vi/".$strvideokey."/mqdefault.jpg";
				break;
			}
		}
		break;
	case "vimeo":
		break;
	default:
		break;
	}
	return $strvideothumbnailfullpath;
}

function f_iswikimediaimageurl($strimageurl) {
	//A Wikipedia lead image (upload.wikimedia.org, thumb.wikimedia.org, a
	//wikipedia.org file URL). The placeholder and TMDb posters never match, so they keep
	//the full-bleed cover.
	return preg_match('#^https?://[^/]*(wikimedia|wikipedia)\.org/#i',trim((string)$strimageurl))===1;
}

function f_cardmediawikifit($strcardmedia) {
	//Hooks f_wikifit() on the <img> of a 2:3 card whose image comes from
	//Wikipedia, so a non-2:3 image is shown whole on a dark background instead of cropped.
	//Applied once where every card branch of text2sql-search.inc.php meets, rather than in
	//its twelve Wikipedia fallbacks. Logo cards (-contain) are left alone.
	if (strpos($strcardmedia,"search-poster-card-media-contain")!==false || strpos($strcardmedia,"f_wikifit")!==false) {
		return $strcardmedia;
	}
	if (!preg_match('#<img src="([^"]*)"#',$strcardmedia,$arrmatch) || !f_iswikimediaimageurl(html_entity_decode($arrmatch[1],ENT_QUOTES))) {
		return $strcardmedia;
	}
	return preg_replace('#<img #',"<img onload=\"f_wikifit(this)\" ",$strcardmedia,1);
}

function f_getwikimediaimageurlbywidth($strimageurl,$lngtargetwidth) {
	$lngtargetwidth=(int)$lngtargetwidth;
	if ($lngtargetwidth<=0) {
		return $strimageurl;
	}
	$strimageurl=trim((string)$strimageurl);
	if ($strimageurl=="") {
		return $strimageurl;
	}
	$arrurl=parse_url($strimageurl);
	if (!is_array($arrurl)) {
		return $strimageurl;
	}
	$strhost=isset($arrurl['host'])?(string)$arrurl['host']:"";
	$strscheme=isset($arrurl['scheme'])?(string)$arrurl['scheme']:"https";
	$strpath=isset($arrurl['path'])?(string)$arrurl['path']:"";
	if (stripos($strhost,"upload.wikimedia.org")===false) {
		return $strimageurl;
	}
	if ($strpath=="") {
		return $strimageurl;
	}

	// Normalize: if already a thumb URL, remove /thumb and trailing size segment
	if (strpos($strpath,"/thumb/")!==false) {
		$strpath=str_replace("/thumb/","/",$strpath);
		$arrparts=explode("/",$strpath);
		if (count($arrparts)>=2) {
			$strlast=$arrparts[count($arrparts)-1];
			$strprev=$arrparts[count($arrparts)-2];
			if (preg_match('/^[0-9]+px\-/', $strlast) && preg_match('/\.(jpg|jpeg|png|gif|webp|tif|tiff|svg)$/i', $strprev)) {
				array_pop($arrparts);
				$strpath=implode("/",$arrparts);
			}
		}
	}

	$strfilename=basename($strpath);
	if ($strfilename=="" || $strfilename=="/" || $strfilename==".") {
		return $strimageurl;
	}

	$strdir=rtrim(dirname($strpath),"/");
	if ($strdir=="" || $strdir==".") {
		return $strimageurl;
	}
	$strthumbpath=$strdir."/thumb/".$strfilename."/".$lngtargetwidth."px-".$strfilename;
	return $strscheme."://".$strhost.$strthumbpath;
}

function f_t2scollectionmembers($lngcollectionid) {
	// Cross-type: every member of a T2S collection, movies AND series
	// (T_WC_T2S_MOVIE_COLLECTION + T_WC_T2S_SERIE_COLLECTION), normalized to a common
	// row and returned in release-date order. A T2S collection (CUSTOM_LIST extension) is
	// cross-type: e.g. Star Trek holds both the films and the TV series. Each returned row:
	// ENTITY_TYPE ('movie'|'serie'), ENTITY_ID, TITLE (localized), DAT_SORT (release date /
	// series first-air), DAT_RELEASE, DAT_FIRST_AIR, DAT_LAST_AIR, ID_IMDB, IMDB_RATING,
	// POSTER_PATH. Unknown dates sink to the end.
	global $struilang;
	if ($lngcollectionid<=0) {
		return array();
	}
	$arrmembers=array();
	//IMDB_VOTES comes from the same T2S row as IMDB_RATING, so the figure and its crowd agree
	$strsqlmovies ="SELECT M.ID_MOVIE, M.MOVIE_TITLE, M.MOVIE_TITLE_FR, M.DAT_RELEASE, M.ID_IMDB, M.IMDB_RATING, M.IMDB_VOTES, M.POSTER_PATH ";
	$strsqlmovies.="FROM T_WC_T2S_MOVIE M ";
	$strsqlmovies.="JOIN T_WC_T2S_MOVIE_COLLECTION J ON M.ID_MOVIE = J.ID_MOVIE ";
	$strsqlmovies.="WHERE J.ID_T2S_COLLECTION = ".f_longtosql($lngcollectionid)." AND J.DELETED = 0 ";
	$resultmovies=f_sqlselect($strsqlmovies);
	if ($resultmovies) {
		while ($rstempmovie=f_sqlresultfetcharray($resultmovies)) {
			$strtitle=$rstempmovie['MOVIE_TITLE'];
			if ($struilang!="en" && trim((string)$rstempmovie['MOVIE_TITLE_FR'])!="") { $strtitle=$rstempmovie['MOVIE_TITLE_FR']; }
			$arrmembers[]=array(
				"ENTITY_TYPE"=>"movie","ENTITY_ID"=>f_stringtolong($rstempmovie['ID_MOVIE']),"TITLE"=>$strtitle,
				"DAT_SORT"=>trim((string)$rstempmovie['DAT_RELEASE']),"DAT_RELEASE"=>$rstempmovie['DAT_RELEASE'],
				"DAT_FIRST_AIR"=>"","DAT_LAST_AIR"=>"","ID_IMDB"=>$rstempmovie['ID_IMDB'],
				"IMDB_RATING"=>$rstempmovie['IMDB_RATING'],"IMDB_VOTES"=>$rstempmovie['IMDB_VOTES'],"POSTER_PATH"=>$rstempmovie['POSTER_PATH'],
			);
		}
		f_sqlfreeresult($resultmovies);
	}
	$strsqlseries ="SELECT S.ID_SERIE, S.SERIE_TITLE, S.SERIE_TITLE_FR, S.DAT_FIRST_AIR, S.DAT_LAST_AIR, S.ID_IMDB, S.IMDB_RATING, S.IMDB_VOTES, S.POSTER_PATH ";
	$strsqlseries.="FROM T_WC_T2S_SERIE S ";
	$strsqlseries.="JOIN T_WC_T2S_SERIE_COLLECTION J ON S.ID_SERIE = J.ID_SERIE ";
	$strsqlseries.="WHERE J.ID_T2S_COLLECTION = ".f_longtosql($lngcollectionid)." AND J.DELETED = 0 ";
	$resultseries=f_sqlselect($strsqlseries);
	if ($resultseries) {
		while ($rstempserie=f_sqlresultfetcharray($resultseries)) {
			$strtitle=$rstempserie['SERIE_TITLE'];
			if ($struilang!="en" && trim((string)$rstempserie['SERIE_TITLE_FR'])!="") { $strtitle=$rstempserie['SERIE_TITLE_FR']; }
			$arrmembers[]=array(
				"ENTITY_TYPE"=>"serie","ENTITY_ID"=>f_stringtolong($rstempserie['ID_SERIE']),"TITLE"=>$strtitle,
				"DAT_SORT"=>trim((string)$rstempserie['DAT_FIRST_AIR']),"DAT_RELEASE"=>"",
				"DAT_FIRST_AIR"=>$rstempserie['DAT_FIRST_AIR'],"DAT_LAST_AIR"=>$rstempserie['DAT_LAST_AIR'],"ID_IMDB"=>$rstempserie['ID_IMDB'],
				"IMDB_RATING"=>$rstempserie['IMDB_RATING'],"IMDB_VOTES"=>$rstempserie['IMDB_VOTES'],"POSTER_PATH"=>$rstempserie['POSTER_PATH'],
			);
		}
		f_sqlfreeresult($resultseries);
	}
	// release-date order across both types; unknown (empty) dates sink to the end
	usort($arrmembers,function($a,$b){
		$ea=($a['DAT_SORT']=="")?1:0; $eb=($b['DAT_SORT']=="")?1:0;
		if ($ea!=$eb) { return $ea-$eb; }
		if ($a['DAT_SORT']!=$b['DAT_SORT']) { return ($a['DAT_SORT']<$b['DAT_SORT'])?-1:1; }
		if ($a['ENTITY_TYPE']!=$b['ENTITY_TYPE']) { return strcmp($a['ENTITY_TYPE'],$b['ENTITY_TYPE']); }
		return $a['ENTITY_ID']-$b['ENTITY_ID'];
	});
	return $arrmembers;
}

function f_t2scollectionprevnextdisplay($lngentityid,$strentitytype) {
	// Previous / next member within the entity's T2S collection, ordered by
	// release date. Replaces the old TMDb-collection (movie-only) navigation. A T2S
	// collection is cross-type, so a neighbour may be a movie OR a series, each card routes
	// to movie.php / serie.php accordingly. Works for a movie page ($strentitytype='movie')
	// and a series page ('serie'). If the entity is in several collections, the one whose
	// *next* member releases soonest is chosen (mirrors the API _collection_context).
	// The headings name that collection and link to t2scollection.php.
	global $struilang;
	if ($lngentityid<=0) { return; }
	if ($strentitytype=="movie") {
		$strjointable="T_WC_T2S_MOVIE_COLLECTION"; $stridcolumn="ID_MOVIE";
	} else {
		$strjointable="T_WC_T2S_SERIE_COLLECTION"; $stridcolumn="ID_SERIE";
	}
	$strsqlcids="SELECT ID_T2S_COLLECTION FROM ".$strjointable." WHERE ".$stridcolumn." = ".f_longtosql($lngentityid)." AND DELETED = 0 ";
	$resultcids=f_sqlselect($strsqlcids);
	if (!$resultcids) { return; }
	$arrcollectionids=array();
	while ($rstempcid=f_sqlresultfetcharray($resultcids)) { $arrcollectionids[]=f_stringtolong($rstempcid['ID_T2S_COLLECTION']); }
	f_sqlfreeresult($resultcids);
	// choose the collection whose next member releases soonest (fallback: first the entity is in)
	$arrchosen=null; $arrfallback=null; $strbestnextdate=null;
	foreach ($arrcollectionids as $lngcid) {
		$arrmembers=f_t2scollectionmembers($lngcid);
		$lngcurrentindex=-1;
		for ($i=0;$i<count($arrmembers);$i++) {
			if ($arrmembers[$i]['ENTITY_TYPE']==$strentitytype && $arrmembers[$i]['ENTITY_ID']==$lngentityid) { $lngcurrentindex=$i; break; }
		}
		if ($lngcurrentindex<0) { continue; }
		if ($arrfallback===null) { $arrfallback=array($arrmembers,$lngcurrentindex,$lngcid); }
		if ($lngcurrentindex<count($arrmembers)-1) {
			$strnextdate=$arrmembers[$lngcurrentindex+1]['DAT_SORT'];
			if ($arrchosen===null || ($strnextdate!="" && ($strbestnextdate===null || $strnextdate<$strbestnextdate))) {
				$arrchosen=array($arrmembers,$lngcurrentindex,$lngcid); $strbestnextdate=$strnextdate;
			}
		}
	}
	if ($arrchosen===null) { $arrchosen=$arrfallback; }
	if ($arrchosen===null) { return; }
	$arrmembers=$arrchosen[0]; $lngcurrentindex=$arrchosen[1]; $lngchosenid=$arrchosen[2];
	$strcollectionlink="";
	$resultname=f_sqlselect("SELECT COLLECTION_NAME, COLLECTION_NAME_FR FROM T_WC_T2S_COLLECTION WHERE ID_T2S_COLLECTION = ".f_longtosql($lngchosenid));
	if ($resultname) {
		if ($rstempname=f_sqlresultfetcharray($resultname)) {
			$strcollectionname=trim((string)$rstempname[($struilang!="en" && trim((string)$rstempname["COLLECTION_NAME_FR"])!="")?"COLLECTION_NAME_FR":"COLLECTION_NAME"]);
			if ($strcollectionname!="") {
				$strcollectionlink=(($struilang=="fr")?" : ":": ")."<a href=\"t2scollection.php?collectionid=".$lngchosenid."\">".f_htmlspecialchars($strcollectionname)."</a>";
			}
		}
		f_sqlfreeresult($resultname);
	}
	// previous first, then next
	$arrslots=array();
	if ($lngcurrentindex>0) {
		$arrslots[]=array("row"=>$arrmembers[$lngcurrentindex-1],"key"=>"lib_movie_collection_previous");
	}
	if ($lngcurrentindex<count($arrmembers)-1) {
		$arrslots[]=array("row"=>$arrmembers[$lngcurrentindex+1],"key"=>"lib_movie_collection_next");
	}
	foreach ($arrslots as $arrslot) {
		$arrrow=$arrslot["row"];
		$strheadingkey=$arrslot["key"];
		echo "<a name=\"".$strheadingkey."\"></a><h2>".get_movie_string($strheadingkey).$strcollectionlink."</h2>";
		echo "<table>";
		if ($arrrow['ENTITY_TYPE']=="movie") {
			f_movielistdisplay($arrrow['ENTITY_ID'],$arrrow['TITLE'],$arrrow['TITLE'],"",$arrrow['DAT_RELEASE'],$arrrow['ID_IMDB'],$arrrow['IMDB_RATING'],"",$arrrow['POSTER_PATH'],"",3,0,"float",$arrrow['IMDB_VOTES']);
		} else {
			f_serielistdisplay($arrrow['ENTITY_ID'],$arrrow['TITLE'],$arrrow['TITLE'],"",$arrrow['DAT_FIRST_AIR'],$arrrow['DAT_LAST_AIR'],$arrrow['ID_IMDB'],$arrrow['IMDB_RATING'],"",$arrrow['POSTER_PATH'],"",3,0,"float",$arrrow['IMDB_VOTES']);
		}
		echo "</table>";
	}
}

function f_movietmdbneighboursdisplay($lngmovieid,$strneighbourtable,$strneighbourcolumn,$strheadingkey) {
	// Render a movie's grounded TMDb neighbour set (similar or
	// recommendations, from T_WC_TMDB_MOVIE_SIMILAR / _RECOMMENDATION) as movie cards,
	// ordered by DISPLAY_ORDER. Only neighbours present in T_WC_TMDB_MOVIE are shown, so
	// every card is real and clickable. The cards are therefore built into an array before
	// anything is echoed: the heading carries the number of cards actually rendered, not
	// the number of stored rows, so a neighbour TMDb knows but this database does not is
	// never counted. A movie with no renderable neighbour still renders nothing at all.
	global $struilang;
	if ($lngmovieid<=0) {
		return;
	}
	$strsqlneighbours ="SELECT ".$strneighbourcolumn." AS ID_NEIGHBOUR ";
	$strsqlneighbours.="FROM ".$strneighbourtable." ";
	$strsqlneighbours.="WHERE ID_MOVIE = ".f_longtosql($lngmovieid)." AND (DELETED IS NULL OR DELETED = 0) ";
	$strsqlneighbours.="ORDER BY DISPLAY_ORDER ";
	$resultneighbours=f_sqlselect($strsqlneighbours);
	if (!$resultneighbours) {
		return;
	}
	$arrneighbourids=array();
	while ($rstempneighbour=f_sqlresultfetcharray($resultneighbours)) {
		$lngneighbourid=f_stringtolong($rstempneighbour['ID_NEIGHBOUR']);
		if ($lngneighbourid<=0 || $lngneighbourid==$lngmovieid) {
			continue;
		}
		$arrneighbourids[]=$lngneighbourid;
	}
	f_sqlfreeresult($resultneighbours);
	$arrcards=array();
	foreach ($arrneighbourids as $lngneighbourid) {
		$strsqlmovie ="SELECT T_WC_TMDB_MOVIE.ID_MOVIE, T_WC_TMDB_MOVIE.TITLE, T_WC_TMDB_MOVIE.ORIGINAL_TITLE, T_WC_TMDB_MOVIE.DAT_RELEASE, T_WC_TMDB_MOVIE.ID_IMDB, T_WC_TMDB_MOVIE.OVERVIEW, T_WC_TMDB_MOVIE.POSTER_PATH, T_WC_TMDB_MOVIE.GENRES ";
		//Rating and vote count are read from the same joined row, so the figure and the crowd it
		//rests on can never disagree. Folded into this query rather than looked up per card, which
		//also spares the extra round trip the separate f_descfromcode lookup used to cost.
		$strsqlmovie.=", T_WC_IMDB_MOVIE_RATING_IMPORT.averageRating, T_WC_IMDB_MOVIE_RATING_IMPORT.numVotes ";
		if ($struilang!="en") {
			$strsqlmovie.=", T_WC_TMDB_MOVIE_LANG.TITLE AS TITLE_LANG, T_WC_TMDB_MOVIE_LANG.OVERVIEW AS OVERVIEW_LANG, T_WC_TMDB_MOVIE_LANG.POSTER_PATH AS POSTER_PATH_LANG ";
		}
		$strsqlmovie.="FROM T_WC_TMDB_MOVIE ";
		$strsqlmovie.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_MOVIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
		if ($struilang!="en") {
			$strsqlmovie.="LEFT JOIN T_WC_TMDB_MOVIE_LANG ON T_WC_TMDB_MOVIE.ID_MOVIE = T_WC_TMDB_MOVIE_LANG.ID_MOVIE AND T_WC_TMDB_MOVIE_LANG.LANG = ".f_stringtosql($struilang)." ";
		}
		$strsqlmovie.="WHERE T_WC_TMDB_MOVIE.ID_MOVIE = ".f_longtosql($lngneighbourid)." AND T_WC_TMDB_MOVIE.DELETED = 0 ";
		$resultmovie=f_sqlselect($strsqlmovie);
		if ($resultmovie) {
			if ($rstempmovie=f_sqlresultfetcharray($resultmovie)) {
				$strmovieidimdb=$rstempmovie["ID_IMDB"];
				$strmovieoverview=$rstempmovie['OVERVIEW'];
				$strmoviedatrelease=$rstempmovie['DAT_RELEASE'];
				$strmovieposterpath=$rstempmovie['POSTER_PATH'];
				$strmovietitle=$rstempmovie['TITLE'];
				$strmovieoriginaltitle=$rstempmovie['ORIGINAL_TITLE'];
				$strmoviegenres=$rstempmovie['GENRES'];
				if ($struilang!="en") {
					if (trim((string)$rstempmovie['TITLE_LANG'])!="") { $strmovietitle=$rstempmovie['TITLE_LANG']; }
					if (trim((string)$rstempmovie['OVERVIEW_LANG'])!="") { $strmovieoverview=$rstempmovie['OVERVIEW_LANG']; }
					if (trim((string)$rstempmovie['POSTER_PATH_LANG'])!="") { $strmovieposterpath=$rstempmovie['POSTER_PATH_LANG']; }
				}
				$dblaveragerating=$rstempmovie['averageRating'];
				$lngimdbvotes=$rstempmovie['numVotes'];
				$arrcards[]=array("id"=>$lngneighbourid,"title"=>$strmovietitle,"originaltitle"=>$strmovieoriginaltitle,"datrelease"=>$strmoviedatrelease,"idimdb"=>$strmovieidimdb,"rating"=>$dblaveragerating,"votes"=>$lngimdbvotes,"overview"=>$strmovieoverview,"posterpath"=>$strmovieposterpath,"genres"=>$strmoviegenres);
			}
			f_sqlfreeresult($resultmovie);
		}
	}
	$lngcardcount=count($arrcards);
	if ($lngcardcount<=0) {
		return;
	}
	echo "<a name=\"".$strheadingkey."\"></a><h2>".get_movie_string($strheadingkey).": ".$lngcardcount."</h2>";
	echo "<table>";
	foreach ($arrcards as $arrcard) {
		f_movielistdisplay($arrcard['id'],$arrcard['title'],$arrcard['originaltitle'],"",$arrcard['datrelease'],$arrcard['idimdb'],$arrcard['rating'],$arrcard['overview'],$arrcard['posterpath'],$arrcard['genres'],3,0,"float",$arrcard['votes']);
	}
	echo "</table>";
}

function f_serietmdbneighboursdisplay($lngserieid,$strneighbourtable,$strneighbourcolumn,$strheadingkey) {
	// Series mirror of f_movietmdbneighboursdisplay. Renders a series'
	// grounded TMDb neighbour set (T_WC_TMDB_SERIE_SIMILAR / _RECOMMENDATION) as series
	// cards ordered by DISPLAY_ORDER; only neighbours present in T_WC_TMDB_SERIE show, and
	// the heading counts the cards actually rendered rather than the stored rows.
	global $struilang;
	if ($lngserieid<=0) {
		return;
	}
	$strsqlneighbours ="SELECT ".$strneighbourcolumn." AS ID_NEIGHBOUR ";
	$strsqlneighbours.="FROM ".$strneighbourtable." ";
	$strsqlneighbours.="WHERE ID_SERIE = ".f_longtosql($lngserieid)." AND (DELETED IS NULL OR DELETED = 0) ";
	$strsqlneighbours.="ORDER BY DISPLAY_ORDER ";
	$resultneighbours=f_sqlselect($strsqlneighbours);
	if (!$resultneighbours) {
		return;
	}
	$arrneighbourids=array();
	while ($rstempneighbour=f_sqlresultfetcharray($resultneighbours)) {
		$lngneighbourid=f_stringtolong($rstempneighbour['ID_NEIGHBOUR']);
		if ($lngneighbourid<=0 || $lngneighbourid==$lngserieid) {
			continue;
		}
		$arrneighbourids[]=$lngneighbourid;
	}
	f_sqlfreeresult($resultneighbours);
	$arrcards=array();
	foreach ($arrneighbourids as $lngneighbourid) {
		$strsqlserie ="SELECT T_WC_TMDB_SERIE.ID_SERIE, T_WC_TMDB_SERIE.TITLE, T_WC_TMDB_SERIE.ORIGINAL_TITLE, T_WC_TMDB_SERIE.DAT_FIRST_AIR, T_WC_TMDB_SERIE.DAT_LAST_AIR, T_WC_TMDB_SERIE.ID_IMDB, T_WC_TMDB_SERIE.OVERVIEW, T_WC_TMDB_SERIE.POSTER_PATH, T_WC_TMDB_SERIE.GENRES ";
		//Rating and vote count read from the same joined row, see the note in the movie twin above
		$strsqlserie.=", T_WC_IMDB_MOVIE_RATING_IMPORT.averageRating, T_WC_IMDB_MOVIE_RATING_IMPORT.numVotes ";
		if ($struilang!="en") {
			$strsqlserie.=", T_WC_TMDB_SERIE_LANG.TITLE AS TITLE_LANG, T_WC_TMDB_SERIE_LANG.OVERVIEW AS OVERVIEW_LANG, T_WC_TMDB_SERIE_LANG.POSTER_PATH AS POSTER_PATH_LANG ";
		}
		$strsqlserie.="FROM T_WC_TMDB_SERIE ";
		$strsqlserie.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_SERIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
		if ($struilang!="en") {
			$strsqlserie.="LEFT JOIN T_WC_TMDB_SERIE_LANG ON T_WC_TMDB_SERIE.ID_SERIE = T_WC_TMDB_SERIE_LANG.ID_SERIE AND T_WC_TMDB_SERIE_LANG.LANG = ".f_stringtosql($struilang)." ";
		}
		$strsqlserie.="WHERE T_WC_TMDB_SERIE.ID_SERIE = ".f_longtosql($lngneighbourid)." AND T_WC_TMDB_SERIE.DELETED = 0 ";
		$resultserie=f_sqlselect($strsqlserie);
		if ($resultserie) {
			if ($rstempserie=f_sqlresultfetcharray($resultserie)) {
				$strserieidimdb=$rstempserie["ID_IMDB"];
				$strserieoverview=$rstempserie['OVERVIEW'];
				$strseriedatfirstair=$rstempserie['DAT_FIRST_AIR'];
				$strseriedatlastair=$rstempserie['DAT_LAST_AIR'];
				$strserieposterpath=$rstempserie['POSTER_PATH'];
				$strserietitle=$rstempserie['TITLE'];
				$strserieoriginaltitle=$rstempserie['ORIGINAL_TITLE'];
				$strseriegenres=$rstempserie['GENRES'];
				if ($struilang!="en") {
					if (trim((string)$rstempserie['TITLE_LANG'])!="") { $strserietitle=$rstempserie['TITLE_LANG']; }
					if (trim((string)$rstempserie['OVERVIEW_LANG'])!="") { $strserieoverview=$rstempserie['OVERVIEW_LANG']; }
					if (trim((string)$rstempserie['POSTER_PATH_LANG'])!="") { $strserieposterpath=$rstempserie['POSTER_PATH_LANG']; }
				}
				$dblaveragerating=$rstempserie['averageRating'];
				$lngimdbvotes=$rstempserie['numVotes'];
				$arrcards[]=array("id"=>$lngneighbourid,"title"=>$strserietitle,"originaltitle"=>$strserieoriginaltitle,"datfirstair"=>$strseriedatfirstair,"datlastair"=>$strseriedatlastair,"idimdb"=>$strserieidimdb,"rating"=>$dblaveragerating,"votes"=>$lngimdbvotes,"overview"=>$strserieoverview,"posterpath"=>$strserieposterpath,"genres"=>$strseriegenres);
			}
			f_sqlfreeresult($resultserie);
		}
	}
	$lngcardcount=count($arrcards);
	if ($lngcardcount<=0) {
		return;
	}
	echo "<a name=\"".$strheadingkey."\"></a><h2>".get_movie_string($strheadingkey).": ".$lngcardcount."</h2>";
	echo "<table>";
	foreach ($arrcards as $arrcard) {
		f_serielistdisplay($arrcard['id'],$arrcard['title'],$arrcard['originaltitle'],"",$arrcard['datfirstair'],$arrcard['datlastair'],$arrcard['idimdb'],$arrcard['rating'],$arrcard['overview'],$arrcard['posterpath'],$arrcard['genres'],3,0,"float",$arrcard['votes']);
	}
	echo "</table>";
}

//IMDb rating rendered as one string, with the figure it rests on carried inside the value:
//"7.9 (14360 votes)" for a title or an episode, "8.4 (5 rated episodes)" for a season.
//Returns an EMPTY string when there is no rating, so the caller drops the whole line rather
//than printing a 0 or a dash: an episode nobody has judged yet must show nothing at all.
//A season gets the episode count and never a vote total: its rating is the mean of its
//episodes, so summing their votes would count the same viewers once per episode. The
//parenthesis also says the season figure is DERIVED, which matters because IMDb rates
//titles and episodes but never seasons.
function f_imdbratingdisplay($dblimdbrating,$lngimdbvotes=0,$lngimdbratedepisodes=0) {
	//Cast first: these arguments come straight out of LEFT JOINed columns, so NULL is the
	//normal case for an unrated episode or a season with nothing rated yet
	$dblrating=f_stringtodouble((string)$dblimdbrating);
	if ($dblrating<=0) {
		return "";
	}
	//IMDb serves one decimal, but a rolled-up season average is a raw float
	$strresult=number_format($dblrating,1,".","");
	$lngvotes=f_stringtolong((string)$lngimdbvotes);
	if ($lngvotes>0) {
		return $strresult." (".$lngvotes." ".get_movie_string('lib_imdb_votes').")";
	}
	$lngratedepisodes=f_stringtolong((string)$lngimdbratedepisodes);
	if ($lngratedepisodes>0) {
		return $strresult." (".$lngratedepisodes." ".get_movie_string('lib_imdb_rated_episodes').")";
	}
	return $strresult;
}

//What the OMDb crawler adds on an IMDb id, read raw from
//T_WC_OMDB_TITLE: Rotten Tomatoes and Metacritic scores, the awards summary, the US box
//office. Echoes one paragraph per known value, nothing when the table is absent (crawler
//not deployed yet), the id was never fetched, or OMDb had nothing for it.
function f_omdbdisplay($strimdbid) {
	static $blntableexists=null;
	if ($strimdbid=="") {
		return;
	}
	if ($blntableexists===null) {
		$blntableexists=f_stringtolong(f_fieldfromquery("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'T_WC_OMDB_TITLE'"))>0;
	}
	if (!$blntableexists) {
		return;
	}
	$result=f_sqlselect("SELECT RT_SCORE, METACRITIC_SCORE, METASCORE, AWARDS, BOX_OFFICE_USD, TIM_OMDB_FETCHED FROM T_WC_OMDB_TITLE WHERE RESPONSE_OK = 1 AND ID_IMDB = ".f_stringtosql($strimdbid));
	if (!$result) {
		return;
	}
	if ($rstemp=f_sqlresultfetcharray($result)) {
		$lngrt=f_stringtolong((string)$rstemp['RT_SCORE']);
		$lngmc=f_stringtolong((string)$rstemp['METACRITIC_SCORE']);
		if ($lngmc<=0) {
			$lngmc=f_stringtolong((string)$rstemp['METASCORE']);
		}
		if ((string)$rstemp['RT_SCORE']!="") {
			echo "<p><strong>".get_movie_string('lib_omdb_rotten_tomatoes').":</strong> ".$lngrt."&nbsp;%</p>";
		}
		if ($lngmc>0) {
			echo "<p><strong>".get_movie_string('lib_omdb_metacritic').":</strong> ".$lngmc."/100</p>";
		}
		if (trim((string)$rstemp['AWARDS'])!="") {
			echo "<p><strong>".get_movie_string('lib_omdb_awards').":</strong> ".f_htmlspecialchars($rstemp['AWARDS'])."</p>";
		}
		$dblboxoffice=f_stringtodouble((string)$rstemp['BOX_OFFICE_USD']);
		if ($dblboxoffice>0) {
			echo "<p><strong>".get_movie_string('lib_omdb_box_office_us').":</strong> ".f_moneyformat($dblboxoffice)."</p>";
		}
	}
	f_sqlfreeresult($result);
}

//Budgets and box office from Wikidata, read from
//T_WC_T2S_WORK_FINANCE (process 75), one line per statement: the place (a link to its
//location sheet, "worldwide" for Q13780930), the amount in its own currency, its date.
//Nothing when the table is absent or the work has no statement.
function f_workfinancedisplay($lngitemid,$stritemtype) {
	global $struilang;
	static $blntableexists=null;
	if ($lngitemid<=0) {
		return;
	}
	if ($blntableexists===null) {
		$blntableexists=f_stringtolong(f_fieldfromquery("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'T_WC_T2S_WORK_FINANCE'"))>0;
	}
	if (!$blntableexists) {
		return;
	}
	$strsql="SELECT f.FINANCE_KIND, f.AMOUNT, f.CURRENCY_CODE, f.ID_CURRENCY, f.ID_PLACE_WIKIDATA, f.ID_LOCATION, f.IS_WORLDWIDE, ";
	$strsql.="f.DAT_POINT, f.POINT_PRECISION, f.DAT_START, f.DAT_END, l.LOCATION_NAME, l.LOCATION_NAME_FR ";
	$strsql.="FROM T_WC_T2S_WORK_FINANCE f LEFT JOIN T_WC_T2S_LOCATION l ON l.ID_LOCATION = f.ID_LOCATION ";
	$strsql.="WHERE f.ITEM_TYPE = ".f_stringtosql($stritemtype)." AND f.ID_ITEM = ".f_longtosql($lngitemid)." ";
	$strsql.="ORDER BY f.FINANCE_KIND DESC, f.IS_WORLDWIDE DESC, f.ID_PLACE_WIKIDATA IS NULL, f.AMOUNT DESC";
	$result=f_sqlselect($strsql);
	if (!$result) {
		return;
	}
	$arrlines=array("budget"=>array(),"box_office"=>array());
	while ($rstemp=f_sqlresultfetcharray($result)) {
		$dblamount=f_stringtodouble((string)$rstemp['AMOUNT']);
		if ($dblamount<=0) {
			continue;
		}
		$strcode=trim((string)$rstemp['CURRENCY_CODE']);
		if ($strcode=="USD") {
			$stramount=f_moneyformat($dblamount);
		} else {
			$stramount=(($struilang=="fr")?number_format($dblamount,0,","," "):number_format($dblamount,0))."&nbsp;".f_htmlspecialchars($strcode!=""?$strcode:(string)$rstemp['ID_CURRENCY']);
		}
		$strplace="";
		if (f_stringtolong((string)$rstemp['IS_WORLDWIDE'])==1) {
			$strplace=get_movie_string('lib_finance_worldwide');
		} elseif (f_stringtolong((string)$rstemp['ID_LOCATION'])>0) {
			$strname=($struilang!="en" && trim((string)$rstemp['LOCATION_NAME_FR'])!="")?$rstemp['LOCATION_NAME_FR']:$rstemp['LOCATION_NAME'];
			$strplace="<a href=\"location.php?locationid=".f_stringtolong((string)$rstemp['ID_LOCATION'])."\">".f_htmlspecialchars($strname)."</a>";
		} elseif (trim((string)$rstemp['ID_PLACE_WIKIDATA'])!="") {
			$strplace=f_htmlspecialchars($rstemp['ID_PLACE_WIKIDATA']);
		}
		$strdate=trim((string)$rstemp['DAT_POINT']);
		if ($strdate!="" && f_stringtolong((string)$rstemp['POINT_PRECISION'])==9) {
			$strdate=substr($strdate,0,4);
		} elseif ($strdate=="" && (trim((string)$rstemp['DAT_START'])!="" || trim((string)$rstemp['DAT_END'])!="")) {
			$strdate=trim((string)$rstemp['DAT_START'])."&ndash;".trim((string)$rstemp['DAT_END']);
		}
		$arrlines[$rstemp['FINANCE_KIND']][]=($strplace!=""?$strplace.": ":"").$stramount.($strdate!=""?" (".$strdate.")":"");
	}
	f_sqlfreeresult($result);
	foreach (array("budget"=>"lib_finance_budget_wikidata","box_office"=>"lib_finance_box_office_wikidata") as $strkind=>$strkey) {
		if (count($arrlines[$strkind])>0) {
			echo "<p><strong>".get_movie_string($strkey).":</strong> ".implode("; ",$arrlines[$strkind])."</p>";
		}
	}
}

//Reads an IMDb rating and the vote count it rests on in a SINGLE lookup, by reference.
//Pages that render one card per row used to call f_descfromcode() for the rating alone; asking
//it a second time for numVotes would have doubled an already per-row round trip. More
//importantly, one query means both values necessarily come from the same row, so a card can
//never show a figure next to somebody else's crowd. Both are set to 0 when the title is absent
//from the import, which f_imdbratingdisplay() then renders as nothing at all.
function f_imdbratingvotes($strimdbid,&$dblimdbrating,&$lngimdbvotes) {
	global $strsqlns;
	$dblimdbrating=0;
	$lngimdbvotes=0;
	if (trim((string)$strimdbid)=="") {
		return;
	}
	$strsqlimdb="SELECT averageRating, numVotes FROM ".$strsqlns."IMDB_MOVIE_RATING_IMPORT WHERE tconst = ".f_stringtosql($strimdbid);
	$resultimdb=f_sqlselect($strsqlimdb);
	if ($resultimdb) {
		if ($rstempimdb=f_sqlresultfetcharray($resultimdb)) {
			$dblimdbrating=f_stringtodouble((string)$rstempimdb['averageRating']);
			$lngimdbvotes=f_stringtolong((string)$rstempimdb['numVotes']);
		}
		f_sqlfreeresult($resultimdb);
	}
}

//$lngimdbvotes is appended last rather than placed next to $dblaveragerating on purpose: this
//function is called positionally from some thirty sites, and inserting an argument mid-signature
//would silently shift every following one at any call site left behind, which PHP would not flag.
//It stays optional so a caller that has no vote count renders exactly as before.
function f_movielistdisplay($lngmovieid,$strmovietitle,$strmovieoriginaltitle,$strpersoncreditreleaseyear,$strmoviedatrelease,$strmovieidimdb,$dblaveragerating,$strmovieoverview,$strmovieposterpath,$strmoviegenres="",$intdisplaylevel=1,$dblsimscore=0,$strvaluetype="float",$lngimdbvotes=0) {

	global $struilang;

	global $intdebug;
	
	if ($intdebug) {
		echo "<br />f_movielistdisplay($lngmovieid,'$strmovietitle','$strmovieoriginaltitle',$strpersoncreditreleaseyear,$strmoviedatrelease,'$strmovieidimdb',$dblaveragerating,'$strmovieoverview','$strmovieposterpath','$strmoviegenres',$intdisplaylevel,$dblsimscore=0,'$strvaluetype')";
	}
	$strmovieurl="movie.php?movieid=".$lngmovieid;
	$strmoviereleaseyear=substr($strmoviedatrelease,0,4);
	$strmovietitleyear=$strmovietitle." (".$strmoviereleaseyear.")";
	//Hooks list.row.prefix and list.row.suffix: what the plugins add before the title and under the row.
	//The context is built once; no parameter is added to this function, which is called positionally.
	$arrrowcontext=array("type"=>"movie","id"=>$lngmovieid,"title"=>$strmovietitle,"originaltitle"=>$strmovieoriginaltitle,"year"=>$strmoviereleaseyear,"imdbid"=>$strmovieidimdb,"level"=>$intdisplaylevel);
	
	switch ($intdisplaylevel) {
	case 1:
		//Simplified display
		echo "<tr>";
		echo "<td>";
		echo f_pluginhtml("list.row.prefix",$arrrowcontext);
		echo "<strong><a href=\"".$strmovieurl."\">".$strmovietitle." (".$strmoviereleaseyear.")</a></strong>";
		if ($strmovieoriginaltitle!=$strmovietitle && $strmovieoriginaltitle!="") {
			echo " AKA <strong><a href=\"".$strmovieurl."\">".$strmovieoriginaltitle." (".$strmoviereleaseyear.")</a></strong>";
		}
		if ($strmovieidimdb!="") {
			echo " <a href=\"https://www.imdb.com/title/".$strmovieidimdb."/\" target=\"_blank\" rel=\"noreferrer\"><img src=\"images/IMDb-icon.png\" width=\"31\" height=\"16\" alt=\"IMDb\" title=\"IMDb\"></a>";
			//Routed through f_imdbratingdisplay so a card carries the vote count the rating rests on,
			//and so an unrated title drops the span entirely instead of printing an empty one
			$strimdbratingcard=f_imdbratingdisplay($dblaveragerating,$lngimdbvotes);
			if ($strimdbratingcard!="") {
				echo " <span alt=\"IMDb rating\" title=\"IMDb rating\">".$strimdbratingcard."</span>";
			}
		}
		
		echo f_pluginhtml("list.row.suffix",$arrrowcontext);
		echo "</td>";
		echo "</tr>";
		break;
	case 2:
	case 3:
		//Detailed display
		switch ($intdisplaylevel) {
		case 2:
			$lngwidth=65;
			$lngheight=98;
			break;
		case 3:
			$lngwidth=130;
			$lngheight=195;
			break;
		}
		echo "<tr>";
		echo "<td valign=\"top\"><a href=\"".$strmovieurl."\"><img src=\"".f_getmovieposterpicture($strmovieposterpath,2)."\" alt=\"".$strmovietitleyear."\" title=\"".$strmovietitleyear."\" width=\"".$lngwidth."\" height=\"".$lngheight."\" /></a></td>";
		echo "<td valign=\"top\">";
		echo f_pluginhtml("list.row.prefix",$arrrowcontext);
		echo "<strong><a href=\"".$strmovieurl."\">$strmovietitleyear</a></strong>";
		if ($strmovieoriginaltitle!=$strmovietitle && $strmovieoriginaltitle!="") {
			echo " AKA <strong><a href=\"".$strmovieurl."\">".$strmovieoriginaltitle." (".$strmoviereleaseyear.")</a></strong>";
		}
		if ($strmovieidimdb!="") {
			echo " <a href=\"https://www.imdb.com/title/".$strmovieidimdb."/\" target=\"_blank\" rel=\"noreferrer\"><img src=\"images/IMDb-icon.png\" width=\"31\" height=\"16\" alt=\"IMDb\" title=\"IMDb\"></a>";
			//Routed through f_imdbratingdisplay so a card carries the vote count the rating rests on,
			//and so an unrated title drops the span entirely instead of printing an empty one
			$strimdbratingcard=f_imdbratingdisplay($dblaveragerating,$lngimdbvotes);
			if ($strimdbratingcard!="") {
				echo " <span alt=\"IMDb rating\" title=\"IMDb rating\">".$strimdbratingcard."</span>";
			}
		}
		//echo "<br />$strmoviedatrelease";
		if ($strmovieoverview!="") {
			echo "<br />".$strmovieoverview;
		}
		//if ($intprivate) {
			if ($strmoviegenres!="" && $strmoviegenres!="|") {
				switch ($struilang) {
				case "fr":
					$strmoviegenres=f_tmdbgenrestranslatefr($strmoviegenres);
					break;
				}
				if (substr($strmoviegenres,0,1)=="|") {
					$strmoviegenres=substr($strmoviegenres,1);
				}
				if (substr($strmoviegenres,strlen($strmoviegenres)-1,1)=="|") {
					$strmoviegenres=substr($strmoviegenres,0,strlen($strmoviegenres)-1);
				}
				$strmoviegenresdisp=str_replace("|",", ",$strmoviegenres);
			} else {
				$strmoviegenresdisp="";
			}
			if ($strmoviegenresdisp!="") {
				echo "<br /><strong>".get_movie_string('lib_movie_genres').":</strong> ".$strmoviegenresdisp;
			}
		//}
		if ($dblsimscore>0) {
			if ($strvaluetype=="float") {
				//Float value for a percentage
				$dblsimscorefordisplay=$dblsimscore*100;
				$strsimscore=sprintf("%.1f%%", $dblsimscorefordisplay);
			} else {
				//Int value for occurrence count
				$strsimscore=$dblsimscore."";
			}
			echo "<br /><strong>".get_movie_string('lib_movie_similarity_score').":</strong> ".$strsimscore;
		}
		echo f_pluginhtml("list.row.suffix",$arrrowcontext);
		echo "</td>";
		echo "</tr>";
		break;
	}
}

//See the note on f_movielistdisplay for why $lngimdbvotes is appended last and stays optional.
function f_serielistdisplay($lngserieid,$strserietitle,$strserieoriginaltitle,$strpersoncreditreleaseyear,$strseriedatrelease,$strseriedatlastair="",$strserieidimdb="",$dblaveragerating="",$strserieoverview="",$strserieposterpath="",$strseriegenres="",$intdisplaylevel=1,$dblsimscore=0,$strvaluetype="float",$lngimdbvotes=0) {
	global $strlibserieaisimilarity;
	global $struilang;
	global $strlibseriegenres;
	global $intdebug;
	
	if ($intdebug) {
		echo "<br />f_serielistdisplay($lngserieid,'$strserietitle','$strserieoriginaltitle',$strpersoncreditreleaseyear,$strseriedatrelease,'$strseriedatlastair','$strserieidimdb',$dblaveragerating,'$strserieoverview','$strserieposterpath','$strseriegenres',$intdisplaylevel,$dblsimscore=0,'$strvaluetype')";
	}
	$strserieurl="serie.php?serieid=".$lngserieid;
	$strseriereleaseyear=substr($strseriedatrelease,0,4);
	$strserielastairyear=($strseriedatlastair!="" ? substr($strseriedatlastair,0,4) : "");
	$strserietitleyear=$strserietitle." (".$strseriereleaseyear."-".$strserielastairyear.")";
	//Hooks list.row.prefix and list.row.suffix: what the plugins add before the title and under the row.
	//The context is built once; no parameter is added to this function, which is called positionally.
	$arrrowcontext=array("type"=>"serie","id"=>$lngserieid,"title"=>$strserietitle,"originaltitle"=>$strserieoriginaltitle,"year"=>$strseriereleaseyear,"imdbid"=>$strserieidimdb,"level"=>$intdisplaylevel);
	
	switch ($intdisplaylevel) {
	case 1:
		//Simplified display
		echo "<tr>";
		echo "<td>";
		echo f_pluginhtml("list.row.prefix",$arrrowcontext);
		echo "<strong><a href=\"".$strserieurl."\">".$strserietitleyear."</a></strong>";
		if ($strserieoriginaltitle!=$strserietitle && $strserieoriginaltitle!="") {
			$strserieoriginaltitleyear=$strserieoriginaltitle." (".$strseriereleaseyear."-".$strserielastairyear.")";
			echo " AKA <strong><a href=\"".$strserieurl."\">".$strserieoriginaltitleyear."</a></strong>";
		}
		if ($strserieidimdb!="") {
			echo " <a href=\"https://www.imdb.com/title/".$strserieidimdb."/\" target=\"_blank\" rel=\"noreferrer\"><img src=\"images/IMDb-icon.png\" width=\"31\" height=\"16\" alt=\"IMDb\" title=\"IMDb\"></a>";
			//Routed through f_imdbratingdisplay so a card carries the vote count the rating rests on,
			//and so an unrated title drops the span entirely instead of printing an empty one
			$strimdbratingcard=f_imdbratingdisplay($dblaveragerating,$lngimdbvotes);
			if ($strimdbratingcard!="") {
				echo " <span alt=\"IMDb rating\" title=\"IMDb rating\">".$strimdbratingcard."</span>";
			}
		}

		echo f_pluginhtml("list.row.suffix",$arrrowcontext);
		echo "</td>";
		echo "</tr>";
		break;
	case 2:
	case 3:
		//Detailed display
		switch ($intdisplaylevel) {
		case 2:
			$lngwidth=65;
			$lngheight=98;
			break;
		case 3:
			$lngwidth=130;
			$lngheight=195;
			break;
		}
		echo "<tr>";
		echo "<td valign=\"top\"><a href=\"".$strserieurl."\"><img src=\"".f_getmovieposterpicture($strserieposterpath,2)."\" alt=\"".$strserietitleyear."\" title=\"".$strserietitleyear."\" width=\"".$lngwidth."\" height=\"".$lngheight."\" /></a></td>";
		echo "<td valign=\"top\">";
		echo f_pluginhtml("list.row.prefix",$arrrowcontext);
		echo "<strong><a href=\"".$strserieurl."\">$strserietitleyear</a></strong>";
		if ($strserieoriginaltitle!=$strserietitle && $strserieoriginaltitle!="") {
			$strserieoriginaltitleyear=$strserieoriginaltitle." (".$strseriereleaseyear."-".$strserielastairyear.")";
			echo " AKA <strong><a href=\"".$strserieurl."\">".$strserieoriginaltitleyear."</a></strong>";
		}
		if ($strserieidimdb!="") {
			echo " <a href=\"https://www.imdb.com/title/".$strserieidimdb."/\" target=\"_blank\" rel=\"noreferrer\"><img src=\"images/IMDb-icon.png\" width=\"31\" height=\"16\" alt=\"IMDb\" title=\"IMDb\"></a>";
			//Routed through f_imdbratingdisplay so a card carries the vote count the rating rests on,
			//and so an unrated title drops the span entirely instead of printing an empty one
			$strimdbratingcard=f_imdbratingdisplay($dblaveragerating,$lngimdbvotes);
			if ($strimdbratingcard!="") {
				echo " <span alt=\"IMDb rating\" title=\"IMDb rating\">".$strimdbratingcard."</span>";
			}
		}
		//echo "<br />$strseriedatrelease";
		if ($strserieoverview!="") {
			echo "<br />".$strserieoverview;
		}
		//if ($intprivate) {
			if ($strseriegenres!="" && $strseriegenres!="|") {
				switch ($struilang) {
				case "fr":
					$strseriegenres=f_tmdbgenrestranslatefr($strseriegenres);
					break;
				}
				if (substr($strseriegenres,0,1)=="|") {
					$strseriegenres=substr($strseriegenres,1);
				}
				if (substr($strseriegenres,strlen($strseriegenres)-1,1)=="|") {
					$strseriegenres=substr($strseriegenres,0,strlen($strseriegenres)-1);
				}
				$strseriegenresdisp=str_replace("|",", ",$strseriegenres);
			} else {
				$strseriegenresdisp="";
			}
			if ($strseriegenresdisp!="") {
				echo "<br /><strong>".$strlibseriegenres.":</strong> ".$strseriegenresdisp;
			}
		//}
		if ($dblsimscore>0) {
			if ($strvaluetype=="float") {
				//Float value for a percentage
				$dblsimscorefordisplay=$dblsimscore*100;
				$strsimscore=sprintf("%.1f%%", $dblsimscorefordisplay);
			} else {
				//Int value for occurrence count
				$strsimscore=$dblsimscore."";
			}
			echo "<br /><strong>".$strlibserieaisimilarity.":</strong> ".$strsimscore;
		}
		echo f_pluginhtml("list.row.suffix",$arrrowcontext);
		echo "</td>";
		echo "</tr>";
		break;
	}
}


function f_personlistdisplay($lngpersonid,$strpersonname,$strpersonbiography,$strpersonbirthdate,$strpersondeathdate,$strpersonidimdb,$strpersonprofilepath,$intdisplaylevel=2) {
	global $strlibserieaisimilarity;
	global $struilang;
	global $strlibseriegenres;
	global $intdebug;
	
	if ($intdebug) {
		echo "<br />f_personlistdisplay($lngpersonid,'$strpersonname','$strpersonbiography',$strpersonbirthdate,$strpersondeathdate,'$strpersonidimdb','$strpersonprofilepath',$intdisplaylevel)";
	}
	$strpersonurl="person.php?personid=".$lngpersonid;
	
	switch ($intdisplaylevel) {
	case 1:
		//Simplified display
		echo "<tr>";
		echo "<td>";
		//Hook list.row.prefix: badges and links of the plugins before the name
		echo f_pluginhtml("list.row.prefix",array("type"=>"person","id"=>$lngpersonid,"title"=>$strpersonname,"originaltitle"=>"","year"=>"","imdbid"=>$strpersonidimdb,"level"=>$intdisplaylevel));
		echo "<strong><a href=\"".$strpersonurl."\">".$strpersonname."</a></strong>";
		if ($strpersonidimdb!="") {
			echo " <a href=\"https://www.imdb.com/title/".$strpersonidimdb."/\" target=\"_blank\" rel=\"noreferrer\"><img src=\"images/IMDb-icon.png\" width=\"31\" height=\"16\" alt=\"IMDb\" title=\"IMDb\"></a>";
		}
		echo "</td>";
		echo "</tr>";
		break;
	case 2:
	case 3:
		//Detailed display
		switch ($intdisplaylevel) {
		case 2:
			$lngwidth=65;
			$lngheight=98;
			break;
		case 3:
			$lngwidth=130;
			$lngheight=195;
			break;
		}
		echo "<tr>";
		echo "<td valign=\"top\"><a href=\"".$strpersonurl."\"><img src=\"".f_getpersonprofilepicture($strpersonprofilepath,2)."\" alt=\"".$strpersonname."\" title=\"".$strpersonname."\" width=\"".$lngwidth."\" height=\"".$lngheight."\" style=\"background-color: white;\" /></a></td>";
		echo "<td valign=\"top\">";
		echo "<strong><a href=\"".$strpersonurl."\">$strpersonname</a></strong>";
		if ($strpersonidimdb!="") {
			echo " <a href=\"https://www.imdb.com/name/".$strpersonidimdb."/\" target=\"_blank\" rel=\"noreferrer\"><img src=\"images/IMDb-icon.png\" width=\"31\" height=\"16\" alt=\"IMDb\" title=\"IMDb\"></a>";
		}
		if ($strpersonbiography!="") {
			if (strlen($strpersonbiography)>200) {
				$strpersonbiography=substr($strpersonbiography,0,200)."...";
			}
			echo "<br />".$strpersonbiography;
		}
		echo "</td>";
		echo "</tr>";
		break;
	}
}

function f_tmdbgenrestranslatefr($strmoviegenres) {
	$strmoviegenres = str_replace("|Action|","|Action|",$strmoviegenres);
	$strmoviegenres = str_replace("|Adventure|","|Aventure|",$strmoviegenres);
	$strmoviegenres = str_replace("|Animation|","|Animation|",$strmoviegenres);
	$strmoviegenres = str_replace("|Comedy|","|Comédie|",$strmoviegenres);
	$strmoviegenres = str_replace("|Crime|","|Crime|",$strmoviegenres);
	$strmoviegenres = str_replace("|Documentary|","|Documentaire|",$strmoviegenres);
	$strmoviegenres = str_replace("|Drama|","|Drame|",$strmoviegenres);
	$strmoviegenres = str_replace("|Family|","|Familial|",$strmoviegenres);
	$strmoviegenres = str_replace("|Fantasy|","|Fantastique|",$strmoviegenres);
	$strmoviegenres = str_replace("|History|","|Histoire|",$strmoviegenres);
	$strmoviegenres = str_replace("|Horror|","|Horreur|",$strmoviegenres);
	$strmoviegenres = str_replace("|Music|","|Musique|",$strmoviegenres);
	$strmoviegenres = str_replace("|Mystery|","|Mystère|",$strmoviegenres);
	$strmoviegenres = str_replace("|Romance|","|Romance|",$strmoviegenres);
	$strmoviegenres = str_replace("|Science Fiction|","|Science-Fiction|",$strmoviegenres);
	$strmoviegenres = str_replace("|Thriller|","|Thriller|",$strmoviegenres);
	$strmoviegenres = str_replace("|TV Movie|","|Téléfilm|",$strmoviegenres);
	$strmoviegenres = str_replace("|War|","|Guerre|",$strmoviegenres);
	$strmoviegenres = str_replace("|Western|","|Western|",$strmoviegenres);
	return $strmoviegenres;
}

function f_tmdboriginallanguagetranslate($stroriginallanguage, $strdisplaylanguage) {
	global $strsqlns;
	global $intdebug;
	
	$strsql="SELECT DESCRIPTION FROM ".$strsqlns."TMDB_LANG_LANG WHERE DELETED = 0 AND LANG = ".f_stringtosql($stroriginallanguage)." AND LANG_DISPLAY = ".f_stringtosql($strdisplaylanguage);
	$strresult=trim(f_fieldfromquery($strsql));
	return $strresult;
}

function f_countrycodetranslate($strcountrycode, $strdisplaylanguage) {
	global $strsqlns;
	global $intdebug;
	
	$strfieldname = "COUNTRY_NAME_EN";
	if ($strdisplaylanguage == "fr") {
		$strfieldname = "COUNTRY_NAME_FR";
	}
	
	$strsql="SELECT ".$strfieldname." FROM ".$strsqlns."COUNTRY WHERE COUNTRY_CODE = ".f_stringtosql($strcountrycode);
	if ($intdebug) {
		echo "<br />".$strsql;
	}
	$strresult=trim(f_fieldfromquery($strsql));
	
	// If no result found, return the country code as fallback
	if ($strresult == "") {
		$strresult = $strcountrycode;
	}
	
	return $strresult;
}

function f_moneyformat($dblvalue) {
	global $struilang;
	
	$strresult="";
	switch ($struilang) {
	case "fr":
		$strresult=number_format($dblvalue,0,","," ")."&nbsp;\$";
		break;
	case "en":
	default:
		$strresult="\$".number_format($dblvalue,0);
		break;
	}
	return $strresult;
}

function f_tmdblistname($lnglistid) {
	global $struilang;
	global $strsqlns;
	
	$strresult="";
	$strsql="SELECT NAME FROM ".$strsqlns."TMDB_LIST WHERE DELETED = 0 AND ID_LIST = ".f_longtosql($lnglistid)." ";
	$strresult=trim(f_fieldfromquery($strsql));
	//Is there a short name
	$strsql="SELECT SHORT_NAME FROM ".$strsqlns."TMDB_LIST WHERE DELETED = 0 AND ID_LIST = ".f_longtosql($lnglistid)." ";
	$strtemp=trim(f_fieldfromquery($strsql));
	if ($strtemp!="") {
		$strresult=$strtemp;
	}
	if ($struilang!="en") {
		//Is there a short name for the current language? 
		$strsql="SELECT SHORT_NAME FROM ".$strsqlns."TMDB_LIST_LANG WHERE DELETED = 0 AND ID_LIST = ".f_longtosql($lnglistid)." AND LANG = ".f_stringtosql($struilang)." ";
		//echo "<br />\$strsql=$strsql";
		$strtemp=trim(f_fieldfromquery($strsql));
		if ($strtemp!="") {
			$strresult=$strtemp;
		}
	}
	return $strresult;
}


function f_seriesimilarities($lngserieid) {

}


function f_movietext2sqlsearch($strsearch, $strquestionhashed="", $lngpage=1, $streemodel="", $strt2smodel="", $strcomplexmodel="", $strresultentitymodel="", $stranswermodel="", $strimageref="") {
	global $intdebug;
	global $strapicolor;
	global $struilang;
	global $strtext2sqlapidomainurl;
	global $strtext2sqlapibluedomainurl;
	global $strtext2sqlapigreendomainurl;
	global $strtext2sqlapikeyname;
	global $strtext2sqlapikeyvalue;
	global $intretrievefromcache;
	global $intstoretocache;
	
	//echo "<br />".$strapicolor;
	if ($strapicolor=="Blue") {
		//Blue environment
		$strtext2sqlapidomainurl=$strtext2sqlapibluedomainurl;
	} else {
		//Green environment
		$strtext2sqlapidomainurl=$strtext2sqlapigreendomainurl;
	}
	$strurl = $strtext2sqlapidomainurl."/search/text2sql";
	//echo "<br />".$strurl;
	//echo "<br />\$streemodel=$streemodel - \$strt2smodel=$strt2smodel";
	
	// Prepare the JSON payload according to Text2SQLRequest model
	$payload = array(
		"question" => $strsearch,
		"question_hashed" => $strquestionhashed,
		"page" => $lngpage,
		"ui_language" => $struilang,
		"retrieve_from_cache" => $intretrievefromcache,
		"store_to_cache" => $intstoretocache,
		"complex_question_processing" => true
	);
	if ($streemodel!="") {
		$payload["llm_model_entity_extraction"]=$streemodel;
	}
	if ($strt2smodel!="") {
		$payload["llm_model_text2sql"]=$strt2smodel;
	}
	if ($strcomplexmodel!="") {
		$payload["llm_model_complex"]=$strcomplexmodel;
	}
	//Same omit-when-empty rule as the three above: an empty string
	//means "say nothing", so the API applies its own default. That is what keeps this
	//front compatible with an instance that predates the two parameters, which matters
	//because Blue and Green do not carry the change at the same time.
	if ($strresultentitymodel!="") {
		$payload["llm_model_result_entity"]=$strresultentitymodel;
	}
	//Fast mode, only for the ticked tasks (omit-when-false, same reason).
	global $arrfasttasks;
	foreach (f_text2sqlfasttasks() as $strtask) {
		$payload[$arrfasttasks[$strtask]]=true;
	}
	//The reference of an image deposited beforehand through the same-origin shim
	//(html/app/upload-vision.php, then POST /uploads/vision on the API). Same omit-when-empty rule
	//as the selectors above, and here it is doing real work rather than guarding a corner case:
	//the field is read by the vision task of the API, which is not deployed on either
	//colour yet. Sending it unconditionally would add a key that today's API does not know to a
	//payload it validates, for every question, image or not.
	if ($strimageref!="") {
		$payload["image_ref"]=$strimageref;
	}
	//echo "<br />Payload for Text2SQL API: <br />";
	//print_r($payload);
	
	$json_payload = json_encode($payload);

	//A bounded wait. With no timeout, a saturated API kept one PHP-FPM worker per
	//search for up to max_execution_time (600 s), and with the image's 5 workers the whole site
	//stopped answering, pages without a search included. 90 s sits above the API p99 measured alone
	//(36 s), the same value as voice-agent's TEXT2SQL_TIMEOUT_SECONDS; override it in the env file.
	$lngtimeoutseconds=f_stringtolong(env('TEXT2SQL_TIMEOUT_SECONDS',''));
	if ($lngtimeoutseconds<=0) {
		$lngtimeoutseconds=90;
	}
	$curl = curl_init();
	$arrresponseheaders=array();
	
	curl_setopt_array($curl, array(
		CURLOPT_URL => $strurl,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_CONNECTTIMEOUT => 5,
		CURLOPT_TIMEOUT => $lngtimeoutseconds,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS => $json_payload,
		CURLOPT_HTTPHEADER => array(
			$strtext2sqlapikeyname.": ".$strtext2sqlapikeyvalue,
			"Content-Type: application/json",
			"Content-Length: " . strlen($json_payload)
		),
		CURLOPT_HEADERFUNCTION => function($curl,$strheader) use (&$arrresponseheaders) {
			$lngheaderlen=strlen($strheader);
			$strheadertrim=trim($strheader);
			if ($strheadertrim!="" && strpos($strheadertrim,":")!==false) {
				list($strheadername,$strheadervalue)=explode(":",$strheadertrim,2);
				$arrresponseheaders[strtolower(trim($strheadername))]=trim($strheadervalue);
			}
			return $lngheaderlen;
		}
	));
	$response = curl_exec($curl);
	$intcurlerrno=curl_errno($curl);
	$strcurlerror=curl_error($curl);
	$inthttpcode=f_stringtolong(curl_getinfo($curl,CURLINFO_HTTP_CODE));
	curl_close($curl);
	if ($response===false && $intcurlerrno==28) { //28: CURLE_OPERATION_TIMEDOUT
		//A sentence for the reader rather than curl's "Operation timed out after
		//90001 milliseconds". The API may still finish and cache the answer, hence is_retryable.
		$arrerrorresponse=array(
			"error" => (function_exists('get_movie_string')?(string)get_movie_string('lib_t2s_timeout'):"The search took too long."),
			"error_code" => "timeout",
			"is_retryable" => true,
			"provider" => "text2sql-api"
		);
		return json_encode($arrerrorresponse);
	}
	if ($response===false) {
		$arrerrorresponse=array(
			"error" => "Text2SQL API transport error: ".$strcurlerror,
			"error_code" => ($intcurlerrno>0 ? strval($intcurlerrno) : "curl_error"),
			"is_retryable" => false,
			"provider" => "text2sql-api"
		);
		return json_encode($arrerrorresponse);
	}

	$jsonresponse=json_decode($response,true);
	$inthasvalidjson=(json_last_error()===JSON_ERROR_NONE && is_array($jsonresponse));
	$lngretryafterseconds=0;
	if (isset($arrresponseheaders["retry-after"])) {
		$lngretryafterseconds=floatval($arrresponseheaders["retry-after"]);
	}

	if ($inthttpcode==429) {
		if ($inthasvalidjson) {
			$jsonresponse["error_code"]="429";
			$jsonresponse["is_retryable"]=true;
			if ($lngretryafterseconds>0 && (!isset($jsonresponse["retry_after_seconds"]) || floatval($jsonresponse["retry_after_seconds"])<=0)) {
				$jsonresponse["retry_after_seconds"]=$lngretryafterseconds;
			}
			if (!isset($jsonresponse["provider"]) || trim((string)$jsonresponse["provider"])=="") {
				$jsonresponse["provider"]="text2sql-api";
			}
			return json_encode($jsonresponse);
		}

		$arrerrorresponse=array(
			"error" => "Text2SQL API call failed: 429 Too Many Requests",
			"error_code" => "429",
			"is_retryable" => true,
			"provider" => "text2sql-api"
		);
		if ($lngretryafterseconds>0) {
			$arrerrorresponse["retry_after_seconds"]=$lngretryafterseconds;
		}
		return json_encode($arrerrorresponse);
	}

	if ($inthttpcode>=400) {
		if ($inthasvalidjson) {
			if (!isset($jsonresponse["error_code"]) || trim((string)$jsonresponse["error_code"])=="") {
				$jsonresponse["error_code"]=strval($inthttpcode);
			}
			if (!isset($jsonresponse["is_retryable"])) {
				$jsonresponse["is_retryable"]=false;
			}
			if (!isset($jsonresponse["provider"]) || trim((string)$jsonresponse["provider"])=="") {
				$jsonresponse["provider"]="text2sql-api";
			}
			return json_encode($jsonresponse);
		}

		$arrerrorresponse=array(
			"error" => "Text2SQL API HTTP error: ".$inthttpcode,
			"error_code" => strval($inthttpcode),
			"is_retryable" => false,
			"provider" => "text2sql-api"
		);
		return json_encode($arrerrorresponse);
	}

	if (!$inthasvalidjson) {
		$arrerrorresponse=array(
			"error" => "Text2SQL API returned an invalid JSON response",
			"error_code" => "invalid_json",
			"is_retryable" => false,
			"provider" => "text2sql-api"
		);
		return json_encode($arrerrorresponse);
	}

	//echo $response;
	return $response;
}

include_once(__DIR__."/api-version.inc.php");
include_once(__DIR__."/wikipedia-md.inc.php");

function f_text2sqlapiversion($strcolor) {
	//Purpose: Running version of one Text2SQL API colour, read from GET /
	//Params:	$strcolor: "Blue" or "Green"
	//Returns: "1.1.19"-style version: from this request's memo, else the five-minute file cache,
	//			else the API itself, else the hand-written fallback above when the API is unreachable
	global $strtext2sqlapibluedomainurl;
	global $strtext2sqlapigreendomainurl;
	global $strtext2sqlapikeyname;
	global $strtext2sqlapikeyvalue;
	global $strtext2sqlapiblueversion;
	global $strtext2sqlapigreenversion;
	static $arrmemo=array();

	$strcolor=($strcolor=="Blue" ? "Blue" : "Green");
	if (isset($arrmemo[$strcolor])) {
		return $arrmemo[$strcolor];
	}
	$strbaseurl=($strcolor=="Blue" ? $strtext2sqlapibluedomainurl : $strtext2sqlapigreendomainurl);
	$strfallback=($strcolor=="Blue" ? $strtext2sqlapiblueversion : $strtext2sqlapigreenversion);
	$strcachefile=f_apiversioncachefile($strcolor, $strbaseurl);
	$strversion=f_apiversioncacheread($strcachefile, time());
	if ($strversion=="" && $strbaseurl!="" && function_exists('curl_init')) {
		$curl=curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => rtrim($strbaseurl,"/")."/",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			//Short on purpose: the search page calls this on every load, and a colour that is
			//down must cost it a second or two, not a hang. The fallback then takes over.
			CURLOPT_CONNECTTIMEOUT => 2,
			CURLOPT_TIMEOUT => 4,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_HTTPGET => true,
			CURLOPT_HTTPHEADER => array($strtext2sqlapikeyname.": ".$strtext2sqlapikeyvalue),
		));
		$response=curl_exec($curl);
		$inthttpcode=f_stringtolong(curl_getinfo($curl,CURLINFO_HTTP_CODE));
		curl_close($curl);
		if ($inthttpcode==200) {
			$strversion=f_apiversionfromresponse($response);
			if ($strversion!="") {
				f_apiversioncachewrite($strcachefile, $strversion);
			}
		}
	}
	if ($strversion=="") {
		$strversion=$strfallback;
	}
	$arrmemo[$strcolor]=$strversion;
	return $strversion;
}

function f_text2sqlsamples($strcolor,$strlang="en", $strset="showcase", &$inthttpcode=0, &$strerror="") {
	//Purpose: Reads GET /samples on one colour of the Text2SQL API, as the voice-agent showcase does
	//Params:	$strcolor: "Blue" or "Green"
	//			$strlang: ui_language sent to the API ("en" or "fr")
	//			$strset: "showcase" (IS_SHOWCASE = 1, the voice-agent picks) or "sample" (IS_SAMPLE = 1)
	//			$inthttpcode, $strerror: set by reference, for the page to say what went wrong
	//Returns: Decoded answer (array with "categories"), or null on any failure
	global $strtext2sqlapibluedomainurl;
	global $strtext2sqlapigreendomainurl;
	global $strtext2sqlapikeyname;
	global $strtext2sqlapikeyvalue;

	$strbaseurl=($strcolor=="Blue" ? $strtext2sqlapibluedomainurl : $strtext2sqlapigreendomainurl);
	$strurl=rtrim($strbaseurl,"/")."/samples?".http_build_query(array("ui_language"=>$strlang, "set"=>$strset));
	$curl=curl_init();
	curl_setopt_array($curl, array(
		CURLOPT_URL => $strurl,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		//The whole tree is built and hydrated on every call; it has taken tens of seconds when
		//a malformed assertion blew the payload up, so allow that, but never hang the page.
		CURLOPT_TIMEOUT => 120,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_HTTPGET => true,
		CURLOPT_HTTPHEADER => array($strtext2sqlapikeyname.": ".$strtext2sqlapikeyvalue),
	));
	$response=curl_exec($curl);
	$inthttpcode=f_stringtolong(curl_getinfo($curl,CURLINFO_HTTP_CODE));
	$strcurlerror=curl_error($curl);
	curl_close($curl);
	if ($response===false) {
		$strerror="Transport error: ".$strcurlerror;
		return null;
	}
	if ($inthttpcode>=400) {
		$strerror="HTTP ".$inthttpcode;
		return null;
	}
	$arrresponse=json_decode($response,true);
	if (json_last_error()!==JSON_ERROR_NONE || !is_array($arrresponse)) {
		$strerror="Invalid JSON response";
		return null;
	}
	return $arrresponse;
}

function f_socialdescription($strtext,$lngmaxlength=300) {
	//Purpose: Cleans and shortens a text so that it can be used as a social preview description
	//Params:	$strtext: Raw text, possibly containing HTML or line breaks
	//			$lngmaxlength: Maximum number of characters kept
	//Returns: Single-line plain text truncated on a word boundary (string)
	$strclean=trim(preg_replace('/\s+/'," ",strip_tags((string)$strtext)));
	if ($strclean=="") {
		return "";
	}
	if (mb_strlen($strclean,'UTF-8')<=$lngmaxlength) {
		return $strclean;
	}
	$strshort=mb_substr($strclean,0,$lngmaxlength,'UTF-8');
	$lnglastspace=mb_strrpos($strshort," ",0,'UTF-8');
	if ($lnglastspace>0) {
		$strshort=mb_substr($strshort,0,$lnglastspace,'UTF-8');
	}
	return rtrim($strshort," ,;:.-")."…";
}

function f_socialimageurl($strimageurl) {
	//Purpose: Turns an image URL used on a page into the absolute URL that social crawlers require for og:image
	//Params:	$strimageurl: Absolute URL (TMDb, Wikipedia), or a path rooted at the site such as /synthetic-images/...
	//Returns: Absolute URL, or "" when the image cannot serve as a preview (empty path, or one of the
	//         relative images/*-default-*.png placeholders returned by f_getmovieposterpicture and friends)
	global $strsitebaseurl;

	$strurl=trim((string)$strimageurl);
	if ($strurl=="") {
		return "";
	}
	if (substr($strurl,0,8)=="https://" || substr($strurl,0,7)=="http://") {
		return $strurl;
	}
	if (substr($strurl,0,2)=="//") {
		return "https:".$strurl;
	}
	if (substr($strurl,0,1)=="/") {
		if ($strsitebaseurl=="") {
			//No canonical host configured: a site-rooted path cannot be made absolute
			return "";
		}
		return $strsitebaseurl.$strurl;
	}
	//Anything still relative is a local placeholder, not a visual worth sharing
	return "";
}

function f_socialwikidataentity($strname,$stroverview="",$strwikipediaimageurl="",$strtmdbimagepath="") {
	//Purpose: Builds the social preview array of the Wikidata-backed entity pages (award, death, group,
	//         movement, nomination, t2scollection, t2slist, t2stopic, technical), which all render a
	//         Wikipedia image first and fall back to a TMDb poster or profile picture
	//Params:	$strname: Entity label, used for og:image:alt
	//			$stroverview: Raw overview text, truncated later by f_socialdescription()
	//			$strwikipediaimageurl: WIKIPEDIA_IMAGE_PATH as stored, already a full URL
	//			$strtmdbimagepath: POSTER_PATH or PROFILE_PATH, or "" when the entity has none
	//Returns: Array ready to be passed as the third argument of f_headerdisplay()
	global $strtmdbimagespathbaseurl;

	$arrsocial=array();
	$arrsocial['description']=$stroverview;
	$strimage=f_socialimageurl($strwikipediaimageurl);
	if ($strimage!="") {
		$arrsocial['image']=$strimage;
	} elseif (trim((string)$strtmdbimagepath)!="") {
		//These pages render a small thumbnail (w64 face crop, or w185 poster). Crawlers ignore images
		//under roughly 200px, so the shared visual uses the w342 rendition of the same picture.
		$arrsocial['image']=f_socialimageurl($strtmdbimagespathbaseurl.$strtmdbimagepath);
		//TMDb posters and profile pictures are 2:3, so the w342 rendition is 342x513
		$arrsocial['imagewidth']=342;
		$arrsocial['imageheight']=513;
	}
	if (isset($arrsocial['image'])) {
		$arrsocial['imagealt']=$strname;
	}
	return $arrsocial;
}

function f_socialmetadisplay($strtitle="",$arrsocial=array()) {
	//Purpose: Emits the Open Graph / Twitter Card tags read by WhatsApp, Facebook, X, Slack, Discord... when a page URL is shared
	//Params:	$strtitle: Page title, used as og:title when the caller does not supply one
	//			$arrsocial: Optional overrides - title, description, image, imagewidth, imageheight, imagealt, type, card, url
	//Returns: Nothing (the tags are echoed inside the <head>)
	global $struilang;
	global $strsitebaseurl;

	if (!is_array($arrsocial)) {
		$arrsocial=array();
	}
	$strogtitle=trim((string)(isset($arrsocial['title']) ? $arrsocial['title'] : $strtitle));
	if ($strogtitle=="") {
		$strogtitle=f_sitename();
	}
	$strogdescription=f_socialdescription(isset($arrsocial['description']) ? $arrsocial['description'] : "");
	$strogimage=trim((string)(isset($arrsocial['image']) ? $arrsocial['image'] : ""));
	$strogimagealt=trim((string)(isset($arrsocial['imagealt']) ? $arrsocial['imagealt'] : $strogtitle));
	$lngogimagewidth=f_stringtolong(isset($arrsocial['imagewidth']) ? $arrsocial['imagewidth'] : 0);
	$lngogimageheight=f_stringtolong(isset($arrsocial['imageheight']) ? $arrsocial['imageheight'] : 0);
	$strogtype=trim((string)(isset($arrsocial['type']) ? $arrsocial['type'] : ""));
	if ($strogtype=="") {
		$strogtype="website";
	}
	$strogcard=trim((string)(isset($arrsocial['card']) ? $arrsocial['card'] : ""));
	if ($strogcard=="") {
		//Posters and profile pictures are 2:3 portraits, which summary_large_image would crop badly
		$strogcard="summary";
	}
	$strogurl=trim((string)(isset($arrsocial['url']) ? $arrsocial['url'] : ""));
	if ($strogurl=="") {
		//Always build og:url on the canonical host (SITE_BASE_URL); without one the tag is left out
		$strrequesturi=f_php_server_var("REQUEST_URI");
		if ($strsitebaseurl!="" && substr($strrequesturi,0,1)=="/") {
			$strogurl=$strsitebaseurl.$strrequesturi;
		}
	}

	echo "<meta property=\"og:site_name\" content=\"".f_htmlattr(f_sitename())."\">";
	echo "<meta property=\"og:type\" content=\"".f_htmlattr($strogtype)."\">";
	echo "<meta property=\"og:title\" content=\"".f_htmlattr($strogtitle)."\">";
	echo "<meta property=\"og:locale\" content=\"".($struilang=="fr" ? "fr_FR" : "en_US")."\">";
	if ($strogurl!="") {
		echo "<meta property=\"og:url\" content=\"".f_htmlattr($strogurl)."\">";
	}
	if ($strogdescription!="") {
		echo "<meta name=\"description\" content=\"".f_htmlattr($strogdescription)."\">";
		echo "<meta property=\"og:description\" content=\"".f_htmlattr($strogdescription)."\">";
	}
	if ($strogimage!="") {
		echo "<meta property=\"og:image\" content=\"".f_htmlattr($strogimage)."\">";
		if (substr($strogimage,0,8)=="https://") {
			echo "<meta property=\"og:image:secure_url\" content=\"".f_htmlattr($strogimage)."\">";
		}
		if ($lngogimagewidth>0 && $lngogimageheight>0) {
			echo "<meta property=\"og:image:width\" content=\"".$lngogimagewidth."\">";
			echo "<meta property=\"og:image:height\" content=\"".$lngogimageheight."\">";
		}
		if ($strogimagealt!="") {
			echo "<meta property=\"og:image:alt\" content=\"".f_htmlattr($strogimagealt)."\">";
		}
		echo "<meta name=\"twitter:image\" content=\"".f_htmlattr($strogimage)."\">";
	}
	echo "<meta name=\"twitter:card\" content=\"".f_htmlattr($strogcard)."\">";
	echo "<meta name=\"twitter:title\" content=\"".f_htmlattr($strogtitle)."\">";
	if ($strogdescription!="") {
		echo "<meta name=\"twitter:description\" content=\"".f_htmlattr($strogdescription)."\">";
	}
}

function f_headerdisplay($strtitle="",$strh1extra="",$arrsocial=array()) {
	global $strlibhome;
	global $struilang;
	global $intprivate;

	echo "<!DOCTYPE html>";
	echo "<html lang=\"".f_htmlattr($struilang)."\">";
	echo "<head>";
    echo "<meta charset=\"UTF-8\">";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">";
    echo "<title>".$strtitle." - ".htmlspecialchars(f_sitename(),ENT_QUOTES,'UTF-8')."</title>";
    f_socialmetadisplay($strtitle,$arrsocial);
    echo "<link rel=\"stylesheet\" href=\"../css/t2s-dark-20261112-6.css\">";
	//In <head> so it exists before any card image loads. A Wikipedia image more
	//than 5% away from 2:3 gets t2s-media-fit on its frame (shown whole, dark background).
	echo "<script>function f_wikifit(img){var w=img.naturalWidth,h=img.naturalHeight;if(w&&h&&Math.abs(w/h-2/3)>2/3*0.05&&img.parentNode){img.parentNode.classList.add('t2s-media-fit');}}</script>";
	echo "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"/apple-touch-icon.png?v=20260422-1200\">";
	//Hook head: stylesheets and scripts of the plugins
	echo f_pluginhtml("head");
	echo "</head>";
	echo "<body onLoad='if(!window.location.hash && document.frmmain && document.frmmain.search){document.frmmain.search.focus();}'>";
	echo "<div class=\"container\">";
	echo "<h1>";
	echo "<button type=\"button\" id=\"burgerbutton\" class=\"burger-button\" aria-label=\"".f_htmlattr(get_movie_string('lib_home_menu'))."\" aria-controls=\"burgermenu\" aria-expanded=\"false\" onclick=\"f_burgertoggle()\">&#9776;</button>";
	echo " <a href=\"index.php\">".get_movie_string('lib_home')."</a>";
	if ($strh1extra!="") {
		echo " ".$strh1extra;
	}
	echo " <a href=\"#footer\">▼</a>";
	echo "</h1>";
	echo "<div id=\"burgeroverlay\" class=\"burger-overlay\" onclick=\"f_burgerclose()\"></div>";
	echo "<nav id=\"burgermenu\" class=\"burger-menu\" aria-label=\"".f_htmlattr(get_movie_string('lib_home_menu'))."\">";
	echo "<a href=\"index.php\">".get_movie_string('lib_menu_home_search')."</a>";
	echo "<a href=\"person-search.php\">".get_movie_string('lib_person_search')."</a>";
	echo "<a href=\"movie-search.php\">".get_movie_string('lib_movie_search')."</a>";
	echo "<a href=\"serie-search.php\">".get_movie_string('lib_serie_search')."</a>";
	echo "<a href=\"wikidata-query.php\">".get_movie_string('lib_menu_wikidata')."</a>";
	echo "<a href=\"samples.php\">".get_movie_string('lib_menu_samples')."</a>";
	echo "<a href=\"open-credits.php\">".get_movie_string('lib_menu_open_credits')."</a>";
	//Hook menu.items: entries of the plugins, private mode only since plugins load only there
	echo f_pluginhtml("menu.items");
	echo "<a href=\"text2sql-settings.php\">".get_movie_string('lib_text2sql_settings_short')."</a>";
	echo "<a href=\"about.php\">".get_movie_string('lib_about')."</a>";
	echo "</nav>";
	echo "<script>\n";
	echo "function f_burgertoggle(){var objmenu=document.getElementById('burgermenu');var objoverlay=document.getElementById('burgeroverlay');var objbutton=document.getElementById('burgerbutton');var intopen=objmenu.classList.toggle('burger-open');objoverlay.classList.toggle('burger-open',intopen);objbutton.setAttribute('aria-expanded',intopen?'true':'false');}\n";
	echo "function f_burgerclose(){var objmenu=document.getElementById('burgermenu');var objoverlay=document.getElementById('burgeroverlay');var objbutton=document.getElementById('burgerbutton');objmenu.classList.remove('burger-open');objoverlay.classList.remove('burger-open');objbutton.setAttribute('aria-expanded','false');}\n";
	echo "document.addEventListener('keydown',function(e){if(e.key==='Escape'){f_burgerclose();}});\n";
	echo "</script>";
}

function f_footerdisplay() {
	global $struilang;
	global $struilangalt;
	global $struilangalturl;

	global $strnow;
	
	if ($struilangalt!="") {
		//echo $struilangalt;
		$strtext=get_movie_string('lib_footer_lang_switch')." ".f_tmdboriginallanguagetranslate($struilangalt, $struilang);
		echo "<p>";
		echo "<a name=\"footer\" id=\"footer\" href=\"".$struilangalturl."\">";
		echo "<img src=\"images/lang-".$struilangalt.".png\" width=\"31\" height=\"16\" alt=\"".$strtext."\" title=\"".$strtext."\">&nbsp;";
		echo $strtext;
		echo "</a>";
		echo "</p>";
	}
	echo "<p><small>".get_movie_string('lib_footer_attribution')."</small></p>";
	echo "<p>Date : ".$strnow." <a href=\"#top\">▲</a></p>";
	echo "<p>&nbsp;</p>";
	echo "<p>&nbsp;</p>";
	echo "</div>";
	echo "</body>";
	echo "</html>";
}

function f_spacylemmalibforlabel($strmovielemmalabel) {









	
	$strlemmalib="";
	switch ($strmovielemmalabel) {
	case "NOUN":
		$strlemmalib=get_movie_string('lib_lemma_spacy_noun');
		break;
	case "PROPN":
		$strlemmalib=get_movie_string('lib_lemma_spacy_propn');
		break;
	case "VERB":
		$strlemmalib=get_movie_string('lib_lemma_spacy_verb');
		break;
	case "NUM":
		$strlemmalib=get_movie_string('lib_lemma_spacy_num');
		break;
	case "ADJ":
		$strlemmalib=get_movie_string('lib_lemma_spacy_adj');
		break;
	case "X":
		$strlemmalib=get_movie_string('lib_lemma_spacy_x');
		break;

	case "PER":
		$strlemmalib=get_movie_string('lib_lemma_spacy_per');
		break;
	case "ORG":
		$strlemmalib=get_movie_string('lib_lemma_spacy_org');
		break;
	case "LOC":
		$strlemmalib=get_movie_string('lib_lemma_spacy_loc');
		break;
	case "MISC":
		$strlemmalib=get_movie_string('lib_lemma_spacy_misc');
		break;
	}
	return $strlemmalib;
}

$intdebug=f_stringtolong(f_request2("debug"));
//URL prefix of the request: private mode, interface language and language switch all come from
//the one prefix map of lib/private-mode.inc.php (T2S_URL_PREFIXES in .env). No folder name is
//known here.
$strphpself=(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : "");
$arrprefixstate=f_prefixstate($strphpself,(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ""));
$intprivate=$arrprefixstate["private"];
$struilang=$arrprefixstate["lang"];
//Empty when the map offers no public prefix in another language: the footer then shows no switch
$struilangalt=$arrprefixstate["langalt"];
$struilangalturl=$arrprefixstate["langalturl"];

function f_searchlink($strexpr) {
	$strlink="<a href=\"?search=".urlencode($strexpr)."&val=Rechercher\">".$strexpr."</a><br />";
	return $strlink;
}

function f_wikidataitemproperties($struilang,$stritemidwikidata,$strpropertyid,$strsep=", ") {
	global $intdebug;
	//global $struilang;
	
	$strresult="";
	$strsql="";
	$strsql.="SELECT V_WIKIDATA_ITEM_PROPERTY.ID_ITEM, T_WC_WIKIDATA_ITEM_V1.LABEL, T_WC_WIKIDATA_ITEM_V1.ALIASES, T_WC_WIKIDATA_ITEM_V1.DESCRIPTION ";
	$strsql.="FROM V_WIKIDATA_ITEM_PROPERTY ";
	$strsql.="LEFT JOIN T_WC_WIKIDATA_ITEM_V1 ON V_WIKIDATA_ITEM_PROPERTY.ID_ITEM = T_WC_WIKIDATA_ITEM_V1.ID_WIKIDATA ";
	$strsql.="WHERE T_WC_WIKIDATA_ITEM_V1.LANG = ".f_stringtosql($struilang)." ";
	$strsql.="AND V_WIKIDATA_ITEM_PROPERTY.ID_WIKIDATA = ".f_stringtosql($stritemidwikidata)." ";
	$strsql.="AND V_WIKIDATA_ITEM_PROPERTY.ID_PROPERTY = ".f_stringtosql($strpropertyid)." ";
	$strsql.="ORDER BY V_WIKIDATA_ITEM_PROPERTY.DISPLAY_ORDER ";
	if ($intdebug) {
		echo "<br />".$strsql;
	}
	$result=f_sqlselect($strsql);
	if ($result) {
		while ($rstemp=f_sqlresultfetcharray($result)) {
			$stritemid=$rstemp["ID_ITEM"];
			$stritemlabel=trim($rstemp["LABEL"]);
			$stritemaliases=trim($rstemp["ALIASES"]);
			$stritemdescription=trim($rstemp["DESCRIPTION"]);
			$stritemurl="wikidata-query.php?search=".$strpropertyid." ".$stritemid;
			if ($strresult!="") {
				$strresult.=$strsep;
			}
			$strresult.="<a href=\"".$stritemurl."\">";
			if ($stritemlabel=="") {
				$strresult.=$stritemid;
			} else {
				$strresult.=$stritemlabel;
			}
			$strresult.="</a>";
		}
		f_sqlfreeresult($result);
	}
	return $strresult;
}

function f_wikidataallproperties($struilang,$stritemidwikidata,$strsep=", ",$strexcludedproperties="") {
	global $intdebug;
	global $strsqlns;
	//global $struilang;
	
	$strresult="";
	$arrexcludedpropertysql=array();
	if (trim($strexcludedproperties)!="") {
		$arrexcludedproperties=explode(",",$strexcludedproperties);
		foreach ($arrexcludedproperties as $strtemppropertyid) {
			$strtemppropertyid=strtoupper(trim($strtemppropertyid));
			if ($strtemppropertyid!="" && preg_match('/^P[0-9]+$/',$strtemppropertyid)) {
				$arrexcludedpropertysql[] = f_stringtosql($strtemppropertyid);
			}
		}
		$arrexcludedpropertysql=array_values(array_unique($arrexcludedpropertysql));
	}
	$strsql="";
	$strsql.="SELECT V_WIKIDATA_ITEM_PROPERTY.ID_PROPERTY, T_WC_WIKIDATA_PROPERTY.LABEL AS PROPERTY_LABEL, T_WC_WIKIDATA_PROPERTY.DESCRIPTION AS PROPERTY_DESC, V_WIKIDATA_ITEM_PROPERTY.ID_ITEM, COALESCE(NULLIF(T_WC_WIKIDATA_ITEM_LANG.LABEL,''), T_WC_WIKIDATA_ITEM_EN.LABEL) AS ITEM_LABEL, COALESCE(NULLIF(T_WC_WIKIDATA_ITEM_LANG.DESCRIPTION,''), T_WC_WIKIDATA_ITEM_EN.DESCRIPTION) AS ITEM_DESC, V_WIKIDATA_ITEM_PROPERTY.DISPLAY_ORDER, V_WIKIDATA_ITEM_PROPERTY.TIM_UPDATED ";
	$strsql.="FROM V_WIKIDATA_ITEM_PROPERTY ";
	$strsql.="LEFT JOIN T_WC_WIKIDATA_ITEM_V1 T_WC_WIKIDATA_ITEM_LANG ON V_WIKIDATA_ITEM_PROPERTY.ID_ITEM = T_WC_WIKIDATA_ITEM_LANG.ID_WIKIDATA AND T_WC_WIKIDATA_ITEM_LANG.LANG = ".f_stringtosql($struilang)." ";
	$strsql.="LEFT JOIN T_WC_WIKIDATA_ITEM_V1 T_WC_WIKIDATA_ITEM_EN ON V_WIKIDATA_ITEM_PROPERTY.ID_ITEM = T_WC_WIKIDATA_ITEM_EN.ID_WIKIDATA AND T_WC_WIKIDATA_ITEM_EN.LANG = 'en' ";
	$strsql.="LEFT JOIN T_WC_WIKIDATA_PROPERTY ON V_WIKIDATA_ITEM_PROPERTY.ID_PROPERTY  = T_WC_WIKIDATA_PROPERTY.ID_PROPERTY ";
	$strsql.="WHERE V_WIKIDATA_ITEM_PROPERTY.ID_WIKIDATA = ".f_stringtosql($stritemidwikidata)." ";
	if (count($arrexcludedpropertysql)>0) {
		$strsql.="AND V_WIKIDATA_ITEM_PROPERTY.ID_PROPERTY NOT IN (".implode(",",$arrexcludedpropertysql).") ";
	}
	$strsql.="ORDER BY PROPERTY_LABEL, V_WIKIDATA_ITEM_PROPERTY.DISPLAY_ORDER ";
	if ($intdebug) {
		echo "<br />".$strsql;
	}
	$strpropertyidprev="";
	$stritemupdatedmin="";
	$stritemupdatedmax="";
	$stritemlabelsadded="|";
	$blnpropertyparagraphopen=false;
	$result=f_sqlselect($strsql);
	if ($result) {
		while ($rstemp=f_sqlresultfetcharray($result)) {
			$strpropertyid=trim($rstemp["ID_PROPERTY"]);
			$strpropertylabel=trim($rstemp["PROPERTY_LABEL"]);
			$strpropertydescription=trim($rstemp["PROPERTY_DESC"]);
			$stritemid=$rstemp["ID_ITEM"];
			$stritemlabel=trim($rstemp["ITEM_LABEL"]);
			$stritemdescription=trim($rstemp["ITEM_DESC"]);
			$stritemupdated=trim($rstemp["TIM_UPDATED"]);
			if ($stritemupdatedmin=="" || $stritemupdated<$stritemupdatedmin) {
				$stritemupdatedmin=$stritemupdated;
			}
			if ($stritemupdatedmax=="" || $stritemupdated>$stritemupdatedmax) {
				$stritemupdatedmax=$stritemupdated;
			}
			//$strpropertyurl="property.php?propertyid=".$strpropertyid;
			$strpropertyurl="wikidata-query.php?search=".$strpropertyid;
			$stritemurl="wikidata-query.php?search=".$strpropertyid." ".$stritemid;
			$lngt2spersonid=f_stringtolong(f_descfromcode($strsqlns."T2S_PERSON","ID_WIKIDATA","ID_PERSON",f_stringtosql($stritemid)));
			if ($lngt2spersonid>0) {
				$strtempt2slabel=trim(f_descfromcode($strsqlns."T2S_PERSON","ID_WIKIDATA","PERSON_NAME",f_stringtosql($stritemid)));
				if ($strtempt2slabel!="") {
					$stritemlabel=$strtempt2slabel;
				}
				$stritemurl="person.php?personid=".$lngt2spersonid;
			} else {
				$lngt2smovieid=f_stringtolong(f_descfromcode($strsqlns."T2S_MOVIE","ID_WIKIDATA","ID_MOVIE",f_stringtosql($stritemid)));
				if ($lngt2smovieid>0) {
					$strtempt2slabel=trim(f_descfromcode($strsqlns."T2S_MOVIE","ID_WIKIDATA","MOVIE_TITLE",f_stringtosql($stritemid)));
					if ($strtempt2slabel!="") {
						$stritemlabel=$strtempt2slabel;
					}
					$stritemurl="movie.php?movieid=".$lngt2smovieid;
				} else {
					$lngt2sserieid=f_stringtolong(f_descfromcode($strsqlns."T2S_SERIE","ID_WIKIDATA","ID_SERIE",f_stringtosql($stritemid)));
					if ($lngt2sserieid>0) {
						$strtempt2slabel=trim(f_descfromcode($strsqlns."T2S_SERIE","ID_WIKIDATA","SERIE_TITLE",f_stringtosql($stritemid)));
						if ($strtempt2slabel!="") {
							$stritemlabel=$strtempt2slabel;
						}
						$stritemurl="serie.php?serieid=".$lngt2sserieid;
					}
				}
			}
			if ($strpropertyid!=$strpropertyidprev) {
				//New property
				if (isset($strpropertyresult)) {
					$strresult.=$strpropertyresult;
				}
				if ($blnpropertyparagraphopen) {
					$strresult.="</p>";
				}
				$strpropertyresult="";
				$strresult.="<p><strong><a href=\"".$strpropertyurl."\">".$strpropertylabel."</a>:</strong> ";
				$blnpropertyparagraphopen=true;
				$stritemlabelsadded="|";
			}
			/*
			if (isset($strpropertyresult)) {
				if ($strpropertyresult!="") {
					$strpropertyresult.=$strsep;
				}
			}
			*/
			if (!isset($strpropertyresult)) {
				$strpropertyresult="";
			}
			$strpropertyresult.="<a href=\"".$stritemurl."\">";
			if ($stritemlabel=="") {
				//Item label is empty, is it a person?
				$stritemlabel=trim(f_descfromcode($strsqlns."TMDB_PERSON","ID_WIKIDATA","NAME",f_stringtosql($stritemid)));
			}
			if ($stritemlabel=="") {
				$stritemlabel=trim(f_descfromcode($strsqlns."WIKIDATA_PERSON_V1","ID_WIKIDATA","NAME",f_stringtosql($stritemid)));
			}
			if ($stritemlabel=="") {
				//Item label is still empty, is it a movie?
				$stritemlabel=trim(f_descfromcode($strsqlns."TMDB_MOVIE","ID_WIKIDATA","TITLE",f_stringtosql($stritemid)));
			}
			if ($stritemlabel=="") {
				$stritemlabel=trim(f_descfromcode($strsqlns."WIKIDATA_MOVIE_V1","ID_WIKIDATA","TITLE",f_stringtosql($stritemid)));
			}
			if ($stritemlabel=="") {
				//Item label is still empty, is it a serie?
				$stritemlabel=trim(f_descfromcode($strsqlns."TMDB_SERIE","ID_WIKIDATA","TITLE",f_stringtosql($stritemid)));
			}
			if ($stritemlabel=="") {
				$stritemlabel=trim(f_descfromcode($strsqlns."WIKIDATA_SERIE_V1","ID_WIKIDATA","TITLE",f_stringtosql($stritemid)));
			}
			if ($stritemlabel=="") {
				$stritemlabeltoadd=$stritemid;
			} else {
				$stritemlabeltoadd=$stritemlabel;
			}
			if (strpos($stritemlabelsadded,"|".$stritemlabeltoadd."|")===false) {
				//Item not already added for the current property
				$strpropertyresult.=$stritemlabeltoadd;
				$strpropertyresult.=$strsep;
				$stritemlabelsadded.=$stritemlabeltoadd."|";
			}
			$strpropertyresult.="</a>";
			$strpropertyidprev=$strpropertyid;
		}
		if (isset($strpropertyresult)) {
			$strresult.=$strpropertyresult;
		}
		if ($blnpropertyparagraphopen) {
			$strresult.="</p>";
		}
		if ($stritemupdatedmin!="") {
			$strresult.="<p><strong>Updated:</strong> ";
			$strresult.=$stritemupdatedmin;
			if ($stritemupdatedmax!="") {
				$strresult.=" - ";
				$strresult.=$stritemupdatedmax;
			}
			$strresult.="</p>";
		}
		f_sqlfreeresult($result);
	}
	return $strresult;
}

// ============================================================================
// Wikidata V2 rendering
//
// f_wikidataallpropertiesv2 reads the new statement/typed-value/qualifier
// schema produced by the wikidata-crawler V2 pipeline:
//   T_WC_WIKIDATA_STATEMENT (parent claim)
//   T_WC_WIKIDATA_ITEM_VALUE / STRING_VALUE / EXTERNAL_ID_VALUE /
//     MEDIA_VALUE / TIME_VALUE / QUANTITY_VALUE (main typed value)
//   T_WC_WIKIDATA_STATEMENT_QUALIFIER and the matching qualifier value tables
//   T_WC_WIKIDATA_PROPERTY_METADATA (property label / formatter URL)
//   T_WC_WIKIDATA_MOVIE / SERIE / PERSON / ITEM (entity labels)
// ============================================================================

function f_wikidataiteminstanceof_v2($stritemidwikidata) {
	// Rend la classe principale d'une entite Wikidata, son
	// P31, ou "" si elle n'en a pas.
	//
	// CE QUE CETTE FONCTION REMPLACE. Dix fichiers d'entite portaient, aux lignes 55
	// a 80, exactement le meme bloc de vingt lignes : un SELECT INSTANCE_OF, ALIASES
	// dans T_WC_WIKIDATA_ITEM_V1, puis un second en repli de langue quand l'une des
	// deux colonnes etait vide. Ce n'etait pas dix problemes, c'en etait un, duplique
	// dix fois.
	//
	// ALIASES A DISPARU, ET CE N'EST PAS UNE MIGRATION MAIS UNE SUPPRESSION. La
	// colonne est de la donnee morte depuis l'arbitrage du 2026-08-17 : V1 ne la
	// collecte plus, sa valeur est figee. Il n'y avait rien a repliquer en V2.
	//
	// LE REPLI DE LANGUE DISPARAIT AVEC ELLE. Il n'existait que parce que ITEM_V1
	// fait une ligne par langue et que l'une des deux colonnes pouvait manquer. Une
	// classe n'a pas de langue : un seul chemin suffit.
	//
	// ⚠ NE PAS PRESUPPOSER LA TABLE OU VIT LE QID. Une ceremonie retransmise vit dans
	// T_WC_WIKIDATA_SERIE depuis la reintegration de Q15416 dans SERIES_ROOTS :
	// Q85314819 « 96th Academy Awards » y est, pas dans le cache d'items. On
	// interroge donc la table des statements, qui les couvre toutes, et non une table
	// d'entite. Lecon apprise le 2026-08-07, et redecouverte deux fois depuis.
	//
	// Le rang 'deprecated' est ecarte : Wikidata tient ces valeurs pour fausses.
	// Meme regle de choix que cote preprocessing, 'preferred' d'abord, puis l'ordre
	// des claims dans le dump. IS_BEST_VALUE et DISPLAY_ORDER ne sont jamais
	// remplies par l'ETL, il ne faut pas s'y fier.
	global $strsqlns;

	$strinstanceof="";
	$stritemidwikidata=trim((string)$stritemidwikidata);
	if ($stritemidwikidata=="") {
		return $strinstanceof;
	}

	$strsqlio ="SELECT iv.ID_ITEM ";
	$strsqlio.="FROM ".$strsqlns."WIKIDATA_STATEMENT st ";
	$strsqlio.="INNER JOIN ".$strsqlns."WIKIDATA_ITEM_VALUE iv ON iv.ID_STATEMENT = st.ID_STATEMENT ";
	$strsqlio.="WHERE st.ID_WIKIDATA = ".f_stringtosql($stritemidwikidata)." ";
	$strsqlio.="AND st.ID_PROPERTY = 'P31' ";
	$strsqlio.="AND (st.`RANK` IS NULL OR st.`RANK` <> 'deprecated') ";
	$strsqlio.="ORDER BY (st.`RANK` = 'preferred') DESC, st.ID_STATEMENT ASC ";
	$strsqlio.="LIMIT 1";
	$resultio=f_sqlselect($strsqlio);
	if ($resultio) {
		if ($rstempio=f_sqlresultfetcharray($resultio)) {
			$strinstanceof=trim((string)$rstempio['ID_ITEM']);
		}
		f_sqlfreeresult($resultio);
	}
	return $strinstanceof;
}

function f_wikidataitemlabel_v2($stritemidwikidata,$struilang) {
	// Resolve an ID_WIKIDATA to a display label and (optional) local URL.
	// Returns array("label" => string, "url" => string).
	// "url" is non-empty only when the item maps to a local T2S record
	// (person.php, movie.php, serie.php). Otherwise the caller decides the
	// fallback URL (usually wikidata-query.php).
	global $strsqlns;

	$arrresult=array("label"=>"","url"=>"");
	$stritemidwikidata=trim((string)$stritemidwikidata);
	if ($stritemidwikidata=="") {
		return $arrresult;
	}

	// 1. Local T2S mappings first (give priority to internal IDs)
	$lngt2spersonid=f_stringtolong(f_descfromcode($strsqlns."T2S_PERSON","ID_WIKIDATA","ID_PERSON",f_stringtosql($stritemidwikidata)));
	if ($lngt2spersonid>0) {
		$strlbl=trim(f_descfromcode($strsqlns."T2S_PERSON","ID_WIKIDATA","PERSON_NAME",f_stringtosql($stritemidwikidata)));
		$arrresult["label"]=$strlbl;
		$arrresult["url"]="person.php?personid=".$lngt2spersonid;
		// Do not return yet: if label is empty we still want to enrich it from
		// the V2 entity tables below.
	} else {
		$lngt2smovieid=f_stringtolong(f_descfromcode($strsqlns."T2S_MOVIE","ID_WIKIDATA","ID_MOVIE",f_stringtosql($stritemidwikidata)));
		if ($lngt2smovieid>0) {
			$strlbl=trim(f_descfromcode($strsqlns."T2S_MOVIE","ID_WIKIDATA","MOVIE_TITLE",f_stringtosql($stritemidwikidata)));
			$arrresult["label"]=$strlbl;
			$arrresult["url"]="movie.php?movieid=".$lngt2smovieid;
		} else {
			$lngt2sserieid=f_stringtolong(f_descfromcode($strsqlns."T2S_SERIE","ID_WIKIDATA","ID_SERIE",f_stringtosql($stritemidwikidata)));
			if ($lngt2sserieid>0) {
				$strlbl=trim(f_descfromcode($strsqlns."T2S_SERIE","ID_WIKIDATA","SERIE_TITLE",f_stringtosql($stritemidwikidata)));
				$arrresult["label"]=$strlbl;
				$arrresult["url"]="serie.php?serieid=".$lngt2sserieid;
			}
		}
	}

	if ($arrresult["label"]!="") {
		return $arrresult;
	}

	// 2. Look up the label in the V2 entity tables.
	// LABELS_JSON is {"en":"Foo","fr":"Bar",...}; prefer UI lang, fallback to LABEL_EN.
	$strlangkey="";
	if ($struilang!="" && $struilang!="en" && preg_match('/^[A-Za-z]{2,8}(-[A-Za-z0-9]+)?$/',$struilang)) {
		// JSON path uses double quotes around the lang code to be safe with hyphens.
		$strlangkey=$struilang;
	}
	$arrtables=array(
		"T_WC_WIKIDATA_MOVIE",
		"T_WC_WIKIDATA_SERIE",
		"T_WC_WIKIDATA_PERSON",
		"T_WC_WIKIDATA_ITEM"
	);
	foreach ($arrtables as $strtable) {
		if ($strlangkey!="") {
			$strsel="COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(LABELS_JSON,'$.\"".$strlangkey."\"')),'null'),LABEL_EN)";
		} else {
			$strsel="LABEL_EN";
		}
		$strsql="SELECT ".$strsel." AS LBL FROM ".$strtable." WHERE ID_WIKIDATA = ".f_stringtosql($stritemidwikidata)." AND DELETED = 0 LIMIT 1";
		$strtemp=trim(f_fieldfromquery($strsql));
		if ($strtemp!="" && $strtemp!="null") {
			$arrresult["label"]=$strtemp;
			return $arrresult;
		}
	}

	return $arrresult;
}

function f_wikidataformattimevalue_v2($strraw,$intyear,$intmonth,$intday,$intprecision,$struilang) {
	// Format a Wikidata time value (precision codes: 11=day, 10=month, 9=year,
	// 8=decade, 7=century, 6=millennium). Falls back to the raw value when the
	// structured fields are missing.
	$strraw=trim((string)$strraw);
	$intyear=intval($intyear);
	$intmonth=intval($intmonth);
	$intday=intval($intday);
	$intprecision=intval($intprecision);

	if ($intyear==0 && $strraw!="") {
		if (preg_match('/^([+-]?)0*(\d+)-(\d{1,2})-(\d{1,2})T/',$strraw,$arrmatch)) {
			$intsign=($arrmatch[1]==="-")?-1:1;
			$intyear=$intsign*intval($arrmatch[2]);
			$intmonth=intval($arrmatch[3]);
			$intday=intval($arrmatch[4]);
		}
	}
	if ($intyear==0 && $strraw=="") {
		return "";
	}
	if ($intyear==0) {
		return $strraw;
	}
	$intprec=($intprecision>0)?$intprecision:11;

	$arrmonthsfr=array("janvier","février","mars","avril","mai","juin","juillet","août","septembre","octobre","novembre","décembre");
	$arrmonthsen=array("January","February","March","April","May","June","July","August","September","October","November","December");
	$arrmonths=($struilang=="fr")?$arrmonthsfr:$arrmonthsen;

	if ($intprec>=11 && $intmonth>=1 && $intmonth<=12 && $intday>=1) {
		$strmonth=$arrmonths[$intmonth-1];
		if ($struilang=="fr") {
			return $intday." ".$strmonth." ".$intyear;
		}
		return $strmonth." ".$intday.", ".$intyear;
	}
	if ($intprec>=10 && $intmonth>=1 && $intmonth<=12) {
		return $arrmonths[$intmonth-1]." ".$intyear;
	}
	if ($intprec>=9) {
		return strval($intyear);
	}
	if ($intprec==8) {
		return strval($intyear)."s";
	}
	if ($intprec==7) {
		// century
		if ($intyear>0) {
			return strval(intval(($intyear-1)/100)+1)."th century";
		}
		return strval(intval((abs($intyear)-1)/100)+1)."th century BC";
	}
	if ($intprec==6) {
		// millennium
		if ($intyear>0) {
			return strval(intval(($intyear-1)/1000)+1)."th millennium";
		}
		return strval(intval((abs($intyear)-1)/1000)+1)."th millennium BC";
	}
	return strval($intyear);
}

function f_wikidataformatqualifiers_v2($arrqualifiers,$struilang) {
	// Format the qualifier rows attached to a single statement.
	// Returns a string like 'point in time: 2020; for work: Foo'.
	$strresult="";
	foreach ($arrqualifiers as $rstempq) {
		$strvaluetype=trim($rstempq["VALUE_TYPE"]);
		$strquallabel=trim($rstempq["QUAL_PROPERTY_LABEL"]);
		if ($strquallabel=="") {
			$strquallabel=trim($rstempq["ID_QUALIFIER_PROPERTY"]);
		}
		$strqualvaluetxt="";
		switch ($strvaluetype) {
		case "item":
			$stritemid=trim($rstempq["QV_ITEM_ID"]);
			if ($stritemid!="") {
				// Resolve the qualifier item to a label and (when the item maps
				// to a local T2S record) a local URL. Mirror the main value
				// renderer so a qualifier entity (movie, person, serie, ...) is
				// a hyperlink to its display page, falling back to the Wikidata
				// query page for any item without a local page.
				$arritem=f_wikidataitemlabel_v2($stritemid,$struilang);
				$strqualitemlabel=($arritem["label"]!="")?$arritem["label"]:$stritemid;
				$strqualitemurl=trim($arritem["url"]);
				if ($strqualitemurl=="") {
					$strqualitemurl="wikidata-query.php?search=".$stritemid;
				}
				$strqualvaluetxt="<a href=\"".$strqualitemurl."\">".$strqualitemlabel."</a>";
			}
			break;
		case "string":
			$strqualvaluetxt=trim($rstempq["QV_STRING"]);
			break;
		case "external_id":
			$strqualvaluetxt=trim($rstempq["QV_EXTERNAL_ID"]);
			break;
		case "media":
			$strqualvaluetxt=trim($rstempq["QV_MEDIA_FILE_NAME"]);
			break;
		case "time":
			$strqualvaluetxt=f_wikidataformattimevalue_v2(
				$rstempq["QV_TIME_RAW"],
				$rstempq["QV_TIME_YEAR"],
				$rstempq["QV_TIME_MONTH"],
				$rstempq["QV_TIME_DAY"],
				$rstempq["QV_TIME_PRECISION"],
				$struilang
			);
			break;
		case "quantity":
			$strqualvaluetxt=trim($rstempq["QV_QUANTITY_DISPLAY"]);
			if ($strqualvaluetxt=="") {
				$strtempamt=trim($rstempq["QV_QUANTITY_AMOUNT"]);
				if ($strtempamt!="") {
					$strqualvaluetxt=rtrim(rtrim($strtempamt,"0"),".");
					if ($strqualvaluetxt==="") { $strqualvaluetxt="0"; }
				}
			}
			break;
		}
		if ($strqualvaluetxt!="") {
			if ($strresult!="") { $strresult.="; "; }
			$strresult.=$strquallabel.": ".$strqualvaluetxt;
		}
	}
	return $strresult;
}

function f_t2slocationwikidataexcluded($arrt2slocations) {
	// The Wikidata block of movie.php, serie.php and episode.php used to show P915
	// (filming location) and P840 (narrative location) a second time, below the "Filmed in" /
	// "Set in" lines and linking to wikidata-query.php instead of location.php. A property is
	// excluded only when its line was actually rendered, so a work that T2S has not linked yet
	// keeps the raw Wikidata values rather than losing them.
	$arrexcluded=array();
	if (is_array($arrt2slocations)) {
		if (isset($arrt2slocations['filming']) && $arrt2slocations['filming']!="") {
			$arrexcluded[]="P915";
		}
		if (isset($arrt2slocations['narrative']) && $arrt2slocations['narrative']!="") {
			$arrexcluded[]="P840";
		}
	}
	return implode(",",$arrexcluded);
}

function f_wikidataallpropertiesv2($struilang,$stritemidwikidata,$strsep=", ",$strexcludedproperties="") {
	// V2 replacement for f_wikidataallproperties. Renders every non-deprecated
	// statement attached to the given Wikidata entity, grouped by property, with
	// qualifiers shown in parentheses next to each value.
	//
	// Fallback: if the entity has no row in T_WC_WIKIDATA_STATEMENT (V2 not yet
	// populated for that ID_WIKIDATA, e.g. movement.php, technical.php
	// entities), this function delegates to the legacy V1 renderer instead of
	// returning an empty block.
	global $intdebug;
	global $strsqlns;

	$strresult="";
	$stritemidwikidata=trim((string)$stritemidwikidata);
	if ($stritemidwikidata=="") {
		return $strresult;
	}

	// V1 fallback when V2 has no statements for this entity. A single indexed
	// LIMIT 1 probe on ID_WIKIDATA is cheap; it pays for itself by avoiding the
	// two LEFT-JOIN-heavy V2 queries below when there is no V2 data anyway.
	$strsqlprobe="SELECT 1 FROM T_WC_WIKIDATA_STATEMENT WHERE ID_WIKIDATA = ".f_stringtosql($stritemidwikidata)." AND DELETED = 0 LIMIT 1";
	if ($intdebug) {
		echo "<br />".$strsqlprobe;
	}
	$blnhasv2=false;
	$resultprobe=f_sqlselect($strsqlprobe);
	if ($resultprobe) {
		if (f_sqlresultfetcharray($resultprobe)) {
			$blnhasv2=true;
		}
		f_sqlfreeresult($resultprobe);
	}
	if (!$blnhasv2) {
		return f_wikidataallproperties($struilang,$stritemidwikidata,$strsep,$strexcludedproperties);
	}

	// Build excluded property list (same contract as the V1 function)
	$arrexcludedpropertysql=array();
	if (trim($strexcludedproperties)!="") {
		$arrexcludedproperties=explode(",",$strexcludedproperties);
		foreach ($arrexcludedproperties as $strtemppropertyid) {
			$strtemppropertyid=strtoupper(trim($strtemppropertyid));
			if ($strtemppropertyid!="" && preg_match('/^P[0-9]+$/',$strtemppropertyid)) {
				$arrexcludedpropertysql[]=f_stringtosql($strtemppropertyid);
			}
		}
		$arrexcludedpropertysql=array_values(array_unique($arrexcludedpropertysql));
	}

	// Pre-fetch every qualifier for this entity so we can attach them per
	// statement without N+1 queries.
	$arrqualifiersbystatement=array();
	$strsqlq ="SELECT ";
	$strsqlq.="q.ID_STATEMENT_QUALIFIER, q.ID_STATEMENT, q.ID_QUALIFIER_PROPERTY, q.VALUE_TYPE, q.DISPLAY_ORDER, ";
	$strsqlq.="pmq.PROPERTY_LABEL AS QUAL_PROPERTY_LABEL, pmq.FORMATTER_URL AS QUAL_PROPERTY_FORMATTER_URL, ";
	$strsqlq.="qiv.ID_ITEM AS QV_ITEM_ID, ";
	$strsqlq.="qsv.VALUE_STRING AS QV_STRING, ";
	$strsqlq.="qev.VALUE_EXTERNAL_ID AS QV_EXTERNAL_ID, qev.FORMATTER_URL AS QV_EXTERNAL_FORMATTER_URL, ";
	$strsqlq.="qmv.FILE_NAME AS QV_MEDIA_FILE_NAME, qmv.FILE_PAGE_URL AS QV_MEDIA_PAGE_URL, ";
	$strsqlq.="qtv.RAW_TIME_VALUE AS QV_TIME_RAW, qtv.YEAR_VALUE AS QV_TIME_YEAR, qtv.MONTH_VALUE AS QV_TIME_MONTH, qtv.DAY_VALUE AS QV_TIME_DAY, qtv.TIME_PRECISION AS QV_TIME_PRECISION, ";
	$strsqlq.="qqv.AMOUNT AS QV_QUANTITY_AMOUNT, qqv.UNIT_ID_WIKIDATA AS QV_QUANTITY_UNIT, qqv.DISPLAY_VALUE AS QV_QUANTITY_DISPLAY ";
	$strsqlq.="FROM T_WC_WIKIDATA_STATEMENT_QUALIFIER q ";
	$strsqlq.="INNER JOIN T_WC_WIKIDATA_STATEMENT s ON q.ID_STATEMENT = s.ID_STATEMENT ";
	$strsqlq.="LEFT JOIN T_WC_WIKIDATA_PROPERTY_METADATA pmq ON q.ID_QUALIFIER_PROPERTY = pmq.ID_PROPERTY ";
	$strsqlq.="LEFT JOIN T_WC_WIKIDATA_QUALIFIER_ITEM_VALUE qiv ON q.ID_STATEMENT_QUALIFIER = qiv.ID_STATEMENT_QUALIFIER ";
	$strsqlq.="LEFT JOIN T_WC_WIKIDATA_QUALIFIER_STRING_VALUE qsv ON q.ID_STATEMENT_QUALIFIER = qsv.ID_STATEMENT_QUALIFIER ";
	$strsqlq.="LEFT JOIN T_WC_WIKIDATA_QUALIFIER_EXTERNAL_ID_VALUE qev ON q.ID_STATEMENT_QUALIFIER = qev.ID_STATEMENT_QUALIFIER ";
	$strsqlq.="LEFT JOIN T_WC_WIKIDATA_QUALIFIER_MEDIA_VALUE qmv ON q.ID_STATEMENT_QUALIFIER = qmv.ID_STATEMENT_QUALIFIER ";
	$strsqlq.="LEFT JOIN T_WC_WIKIDATA_QUALIFIER_TIME_VALUE qtv ON q.ID_STATEMENT_QUALIFIER = qtv.ID_STATEMENT_QUALIFIER ";
	$strsqlq.="LEFT JOIN T_WC_WIKIDATA_QUALIFIER_QUANTITY_VALUE qqv ON q.ID_STATEMENT_QUALIFIER = qqv.ID_STATEMENT_QUALIFIER ";
	$strsqlq.="WHERE s.ID_WIKIDATA = ".f_stringtosql($stritemidwikidata)." ";
	$strsqlq.="AND s.DELETED = 0 AND q.DELETED = 0 ";
	$strsqlq.="AND (s.RANK IS NULL OR s.RANK <> 'deprecated') ";
	$strsqlq.="ORDER BY q.ID_STATEMENT, q.DISPLAY_ORDER, q.ID_STATEMENT_QUALIFIER ";
	if ($intdebug) {
		echo "<br />".$strsqlq;
	}
	$resultq=f_sqlselect($strsqlq);
	if ($resultq) {
		while ($rstempq=f_sqlresultfetcharray($resultq)) {
			$lngstmt=f_stringtolong($rstempq["ID_STATEMENT"]);
			if (!isset($arrqualifiersbystatement[$lngstmt])) {
				$arrqualifiersbystatement[$lngstmt]=array();
			}
			$arrqualifiersbystatement[$lngstmt][]=$rstempq;
		}
		f_sqlfreeresult($resultq);
	}

	// Main statement query: join all six typed value tables in a single SELECT.
	$strsql ="SELECT ";
	$strsql.="s.ID_STATEMENT, s.ID_PROPERTY, s.VALUE_TYPE, s.RANK, s.DISPLAY_ORDER, s.TIM_UPDATED, ";
	$strsql.="pm.PROPERTY_LABEL, pm.PROPERTY_DESCRIPTION, pm.FORMATTER_URL AS PROPERTY_FORMATTER_URL, ";
	$strsql.="iv.ID_ITEM AS V_ITEM_ID, ";
	$strsql.="sv.VALUE_STRING AS V_STRING, sv.LANG_CODE AS V_STRING_LANG, ";
	$strsql.="ev.VALUE_EXTERNAL_ID AS V_EXTERNAL_ID, ev.FORMATTER_URL AS V_EXTERNAL_FORMATTER_URL, ";
	$strsql.="mv.FILE_NAME AS V_MEDIA_FILE_NAME, mv.FILE_PAGE_URL AS V_MEDIA_PAGE_URL, mv.FILE_DIRECT_URL AS V_MEDIA_DIRECT_URL, mv.MEDIA_REPOSITORY AS V_MEDIA_REPOSITORY, ";
	$strsql.="tv.RAW_TIME_VALUE AS V_TIME_RAW, tv.YEAR_VALUE AS V_TIME_YEAR, tv.MONTH_VALUE AS V_TIME_MONTH, tv.DAY_VALUE AS V_TIME_DAY, tv.TIME_PRECISION AS V_TIME_PRECISION, ";
	$strsql.="qv.AMOUNT AS V_QUANTITY_AMOUNT, qv.UNIT_ID_WIKIDATA AS V_QUANTITY_UNIT, qv.DISPLAY_VALUE AS V_QUANTITY_DISPLAY ";
	$strsql.="FROM T_WC_WIKIDATA_STATEMENT s ";
	$strsql.="LEFT JOIN T_WC_WIKIDATA_PROPERTY_METADATA pm ON s.ID_PROPERTY = pm.ID_PROPERTY ";
	$strsql.="LEFT JOIN T_WC_WIKIDATA_ITEM_VALUE iv ON s.ID_STATEMENT = iv.ID_STATEMENT ";
	$strsql.="LEFT JOIN T_WC_WIKIDATA_STRING_VALUE sv ON s.ID_STATEMENT = sv.ID_STATEMENT ";
	$strsql.="LEFT JOIN T_WC_WIKIDATA_EXTERNAL_ID_VALUE ev ON s.ID_STATEMENT = ev.ID_STATEMENT ";
	$strsql.="LEFT JOIN T_WC_WIKIDATA_MEDIA_VALUE mv ON s.ID_STATEMENT = mv.ID_STATEMENT ";
	$strsql.="LEFT JOIN T_WC_WIKIDATA_TIME_VALUE tv ON s.ID_STATEMENT = tv.ID_STATEMENT ";
	$strsql.="LEFT JOIN T_WC_WIKIDATA_QUANTITY_VALUE qv ON s.ID_STATEMENT = qv.ID_STATEMENT ";
	$strsql.="WHERE s.ID_WIKIDATA = ".f_stringtosql($stritemidwikidata)." ";
	$strsql.="AND s.DELETED = 0 ";
	$strsql.="AND (s.RANK IS NULL OR s.RANK <> 'deprecated') ";
	if (count($arrexcludedpropertysql)>0) {
		$strsql.="AND s.ID_PROPERTY NOT IN (".implode(",",$arrexcludedpropertysql).") ";
	}
	$strsql.="ORDER BY CASE WHEN s.VALUE_TYPE = 'external_id' THEN 1 ELSE 0 END, COALESCE(pm.PROPERTY_LABEL, s.ID_PROPERTY), s.DISPLAY_ORDER, s.ID_STATEMENT ";
	if ($intdebug) {
		echo "<br />".$strsql;
	}

	$strpropertyidprev="";
	$stritemupdatedmin="";
	$stritemupdatedmax="";
	$strvaluelabelsadded="|";
	$blnpropertyparagraphopen=false;
	$strpropertyresult="";
	$blnidentifiersection=false;

	$result=f_sqlselect($strsql);
	if ($result) {
		while ($rstemp=f_sqlresultfetcharray($result)) {
			$strpropertyid=trim($rstemp["ID_PROPERTY"]);
			$strpropertylabel=trim($rstemp["PROPERTY_LABEL"]);
			if ($strpropertylabel=="") {
				$strpropertylabel=$strpropertyid;
			}
			$strvaluetype=trim($rstemp["VALUE_TYPE"]);
			$lngstatementid=f_stringtolong($rstemp["ID_STATEMENT"]);
			$stritemupdated=trim($rstemp["TIM_UPDATED"]);
			if ($stritemupdatedmin=="" || $stritemupdated<$stritemupdatedmin) {
				$stritemupdatedmin=$stritemupdated;
			}
			if ($stritemupdatedmax=="" || $stritemupdated>$stritemupdatedmax) {
				$stritemupdatedmax=$stritemupdated;
			}

			$strpropertyurl="wikidata-query.php?search=".$strpropertyid;

			$strvaluehtml="";
			$strvaluelabel="";
			switch ($strvaluetype) {
			case "item":
				$stritemid=trim($rstemp["V_ITEM_ID"]);
				if ($stritemid!="") {
					$arritem=f_wikidataitemlabel_v2($stritemid,$struilang);
					$strtempitemlabel=$arritem["label"];
					$strtempitemurl=$arritem["url"];
					if ($strtempitemurl=="") {
						$strtempitemurl="wikidata-query.php?search=".$strpropertyid." ".$stritemid;
					}
					if ($strtempitemlabel=="") {
						$strtempitemlabel=$stritemid;
					}
					$strvaluelabel=$strtempitemlabel;
					$strvaluehtml="<a href=\"".$strtempitemurl."\">".$strtempitemlabel."</a>";
				}
				break;

			case "string":
				$strtempval=trim($rstemp["V_STRING"]);
				if ($strtempval!="") {
					$strvaluelabel=$strtempval;
					$strvaluehtml=$strtempval;
				}
				break;

			case "external_id":
				$strtempval=trim($rstemp["V_EXTERNAL_ID"]);
				$strtempfmt=trim($rstemp["V_EXTERNAL_FORMATTER_URL"]);
				if ($strtempfmt=="") {
					$strtempfmt=trim($rstemp["PROPERTY_FORMATTER_URL"]);
				}
				if ($strtempval!="") {
					$strvaluelabel=$strtempval;
					if ($strtempfmt!="" && strpos($strtempfmt,"\$1")!==false) {
						$strtempurl=str_replace("\$1",rawurlencode($strtempval),$strtempfmt);
						$strvaluehtml="<a href=\"".$strtempurl."\" target=\"_blank\">".$strtempval."</a>";
					} else {
						$strvaluehtml=$strtempval;
					}
				}
				break;

			case "media":
				$strtempfile=trim($rstemp["V_MEDIA_FILE_NAME"]);
				$strtemppage=trim($rstemp["V_MEDIA_PAGE_URL"]);
				if ($strtemppage=="" && $strtempfile!="") {
					$strtemppage="https://commons.wikimedia.org/wiki/File:".rawurlencode(str_replace(" ","_",$strtempfile));
				}
				if ($strtempfile!="") {
					$strvaluelabel=$strtempfile;
					if ($strtemppage!="") {
						$strvaluehtml="<a href=\"".$strtemppage."\" target=\"_blank\">".$strtempfile."</a>";
					} else {
						$strvaluehtml=$strtempfile;
					}
				}
				break;

			case "time":
				$strtemptime=f_wikidataformattimevalue_v2(
					$rstemp["V_TIME_RAW"],
					$rstemp["V_TIME_YEAR"],
					$rstemp["V_TIME_MONTH"],
					$rstemp["V_TIME_DAY"],
					$rstemp["V_TIME_PRECISION"],
					$struilang
				);
				if ($strtemptime!="") {
					$strvaluelabel=$strtemptime;
					$strvaluehtml=$strtemptime;
				}
				break;

			case "quantity":
				$strtempdisplay=trim($rstemp["V_QUANTITY_DISPLAY"]);
				$strtempamount=trim($rstemp["V_QUANTITY_AMOUNT"]);
				$strtempunit=trim($rstemp["V_QUANTITY_UNIT"]);
				$strtempval="";
				if ($strtempdisplay!="") {
					$strtempval=$strtempdisplay;
				} else if ($strtempamount!="") {
					if (strpos($strtempamount,".")!==false) {
						$strtempval=rtrim(rtrim($strtempamount,"0"),".");
					} else {
						$strtempval=$strtempamount;
					}
					if ($strtempval==="") { $strtempval="0"; }
					if ($strtempunit!="" && strtoupper($strtempunit)!="1") {
						$arrunit=f_wikidataitemlabel_v2($strtempunit,$struilang);
						if ($arrunit["label"]!="") {
							$strtempval.=" ".$arrunit["label"];
						}
					}
				}
				if ($strtempval!="") {
					$strvaluelabel=$strtempval;
					$strvaluehtml=$strtempval;
				}
				break;
			}

			// Attach qualifiers (if any) for this statement.
			if ($strvaluehtml!="" && isset($arrqualifiersbystatement[$lngstatementid])) {
				$strqualstr=f_wikidataformatqualifiers_v2($arrqualifiersbystatement[$lngstatementid],$struilang);
				if ($strqualstr!="") {
					$strvaluehtml.=" <em>(".$strqualstr.")</em>";
				}
			}

			if ($strvaluehtml=="") {
				continue;
			}

			if ($strpropertyid!=$strpropertyidprev) {
				if ($strpropertyresult!="") {
					$strresult.=$strpropertyresult;
				}
				if ($blnpropertyparagraphopen) {
					$strresult.="</p>";
				}
				if (!$blnidentifiersection && $strvaluetype=="external_id") {
					$blnidentifiersection=true;
					$strresult.="<hr style=\"margin:6px 0;border:none;border-top:1px solid #ccc;\">";
					$strresult.="<p style=\"margin:2px 0;\"><strong>Identifiers</strong></p>";
				}
				$strpropertyresult="";
				$strresult.="<p style=\"margin:2px 0;\"><strong><a href=\"".$strpropertyurl."\">".$strpropertylabel."</a>:</strong> ";
				$blnpropertyparagraphopen=true;
				$strvaluelabelsadded="|";
			}

			if ($strvaluelabel=="" || strpos($strvaluelabelsadded,"|".$strvaluelabel."|")===false) {
				$strpropertyresult.=$strvaluehtml.$strsep;
				if ($strvaluelabel!="") {
					$strvaluelabelsadded.=$strvaluelabel."|";
				}
			}
			$strpropertyidprev=$strpropertyid;
		}
		if ($strpropertyresult!="") {
			$strresult.=$strpropertyresult;
		}
		if ($blnpropertyparagraphopen) {
			$strresult.="</p>";
		}
		if ($stritemupdatedmin!="") {
			$strresult.="<p style=\"margin:2px 0;\"><strong>Updated:</strong> ";
			$strresult.=$stritemupdatedmin;
			if ($stritemupdatedmax!="" && $stritemupdatedmax!=$stritemupdatedmin) {
				$strresult.=" - ";
				$strresult.=$stritemupdatedmax;
			}
			$strresult.="</p>";
		}
		f_sqlfreeresult($result);
	}
	return $strresult;
}

function f_wikidatamediasection_v2($struilang,$stritemidwikidata,$strheadinglabel="Media") {
	// Renders an HTML block with the media resources resolved by the
	// wikidata-crawler step 112 for the given entity:
	//   - YouTube videos     -> <iframe> embeds + "Watch on YouTube" link
	//   - Commons videos     -> <video controls poster=thumb src=file>
	//   - Commons audio      -> <audio controls src=file>
	//   - Commons images     -> linked <img> thumbnails (grid)
	//   - Commons documents  -> file-name link with icon
	//   - Internet Archive   -> link card (page + download)
	//
	// Reads from T_WC_WIKIDATA_MEDIA_RESOURCE + T_WC_WIKIDATA_MEDIA_RESOURCE_URL.
	// Returns an empty string when the entity has no active media resource;
	// in that case the caller's `echo` is a no-op and no <h2> appears.
	global $intdebug;

	$strresult="";
	$stritemidwikidata=trim((string)$stritemidwikidata);
	if ($stritemidwikidata=="") {
		return $strresult;
	}

	$strsql ="SELECT ";
	$strsql.="mr.ID_MEDIA_RESOURCE, mr.ID_PROPERTY, mr.SOURCE_PLATFORM, mr.SOURCE_IDENTIFIER, ";
	$strsql.="mr.RESOURCE_KIND, mr.RESOURCE_TITLE, mr.FILE_EXTENSION_PRIMARY, ";
	$strsql.="pm.PROPERTY_LABEL, ";
	$strsql.="u.URL_TYPE, u.URL, u.IS_PREFERRED, u.IS_CANONICAL, u.IS_DIRECT_FILE, ";
	$strsql.="u.DISPLAY_ORDER, u.MIME_TYPE, u.FILE_EXTENSION ";
	$strsql.="FROM T_WC_WIKIDATA_MEDIA_RESOURCE mr ";
	$strsql.="LEFT JOIN T_WC_WIKIDATA_MEDIA_RESOURCE_URL u ON u.ID_MEDIA_RESOURCE = mr.ID_MEDIA_RESOURCE AND u.DELETED = 0 AND u.IS_ACTIVE = 1 ";
	$strsql.="LEFT JOIN T_WC_WIKIDATA_PROPERTY_METADATA pm ON pm.ID_PROPERTY = mr.ID_PROPERTY ";
	$strsql.="WHERE mr.ID_WIKIDATA = ".f_stringtosql($stritemidwikidata)." ";
	$strsql.="AND mr.DELETED = 0 AND mr.IS_ACTIVE = 1 ";
	$strsql.="ORDER BY ";
	$strsql.="FIELD(mr.SOURCE_PLATFORM, 'youtube', 'commons', 'internet_archive'), ";
	$strsql.="FIELD(mr.RESOURCE_KIND, 'video', 'audio', 'image', 'document'), ";
	$strsql.="mr.ID_MEDIA_RESOURCE, u.DISPLAY_ORDER ";
	if ($intdebug) {
		echo "<br />".$strsql;
	}

	// Step 1: accumulate one entry per ID_MEDIA_RESOURCE, with a url-type map.
	$arrresources=array();
	$result=f_sqlselect($strsql);
	if ($result) {
		while ($rstemp=f_sqlresultfetcharray($result)) {
			$lngresourceid=f_stringtolong($rstemp["ID_MEDIA_RESOURCE"]);
			if ($lngresourceid<=0) { continue; }
			if (!isset($arrresources[$lngresourceid])) {
				$arrresources[$lngresourceid]=array(
					"platform"       => trim((string)$rstemp["SOURCE_PLATFORM"]),
					"kind"           => trim((string)$rstemp["RESOURCE_KIND"]),
					"identifier"     => trim((string)$rstemp["SOURCE_IDENTIFIER"]),
					"title"          => trim((string)$rstemp["RESOURCE_TITLE"]),
					"ext"            => strtolower(trim((string)$rstemp["FILE_EXTENSION_PRIMARY"])),
					"id_property"    => trim((string)$rstemp["ID_PROPERTY"]),
					"property_label" => trim((string)$rstemp["PROPERTY_LABEL"]),
					"urls"           => array()
				);
			}
			$strurltype=trim((string)$rstemp["URL_TYPE"]);
			$strurl=trim((string)$rstemp["URL"]);
			if ($strurltype!="" && $strurl!="" && !isset($arrresources[$lngresourceid]["urls"][$strurltype])) {
				$arrresources[$lngresourceid]["urls"][$strurltype]=$strurl;
			}
		}
		f_sqlfreeresult($result);
	}
	if (count($arrresources)==0) {
		return $strresult;
	}

	// Step 2: bucket resources by render group.
	$arrgroups=array(
		"youtube"          => array(),
		"commons_video"    => array(),
		"commons_audio"    => array(),
		"commons_image"    => array(),
		"commons_document" => array(),
		"internet_archive" => array(),
	);
	foreach ($arrresources as $arrres) {
		$strplatform=$arrres["platform"];
		$strkind=$arrres["kind"];
		if ($strplatform=="youtube") {
			$arrgroups["youtube"][]=$arrres;
		} else if ($strplatform=="commons") {
			$strkey="commons_".$strkind;
			if (!isset($arrgroups[$strkey])) { $strkey="commons_image"; }
			$arrgroups[$strkey][]=$arrres;
		} else if ($strplatform=="internet_archive") {
			$arrgroups["internet_archive"][]=$arrres;
		}
	}

	$blnempty=true;
	foreach ($arrgroups as $arrlist) {
		if (count($arrlist)>0) { $blnempty=false; break; }
	}
	if ($blnempty) {
		return $strresult;
	}

	$strresult.="<h2>".htmlspecialchars($strheadinglabel,ENT_QUOTES,"UTF-8")."</h2>\n";

	// --- YouTube ---
	if (count($arrgroups["youtube"])>0) {
		$strresult.="<h3>YouTube</h3>\n";
		$strresult.="<div class=\"wikidata-media-list wikidata-media-youtube\">\n";
		foreach ($arrgroups["youtube"] as $arrres) {
			$strembed=isset($arrres["urls"]["embed"])?$arrres["urls"]["embed"]:"";
			$strwatch=isset($arrres["urls"]["watch"])?$arrres["urls"]["watch"]:"";
			if ($strembed=="") { continue; }
			$strresult.="<div class=\"wikidata-media-item\">";
			$strresult.="<iframe width=\"480\" height=\"270\" src=\"".htmlspecialchars($strembed,ENT_QUOTES,"UTF-8")."\" frameborder=\"0\" allow=\"accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture\" allowfullscreen></iframe>";
			if ($strwatch!="") {
				$strresult.=" <p><a href=\"".htmlspecialchars($strwatch,ENT_QUOTES,"UTF-8")."\" target=\"_blank\" rel=\"noopener\">Watch on YouTube</a></p>";
			}
			$strresult.="</div>\n";
		}
		$strresult.="</div>\n";
	}

	// --- Commons videos ---
	if (count($arrgroups["commons_video"])>0) {
		$strresult.="<h3>Wikimedia Commons &mdash; Videos</h3>\n";
		$strresult.="<div class=\"wikidata-media-list wikidata-media-commons-video\">\n";
		foreach ($arrgroups["commons_video"] as $arrres) {
			$strfile=isset($arrres["urls"]["file"])?$arrres["urls"]["file"]:"";
			$strpage=isset($arrres["urls"]["page"])?$arrres["urls"]["page"]:"";
			$strthumb=isset($arrres["urls"]["thumbnail"])?$arrres["urls"]["thumbnail"]:"";
			if ($strfile=="") { continue; }
			$strmime="";
			switch ($arrres["ext"]) {
				case "webm": $strmime="video/webm"; break;
				case "mp4":  $strmime="video/mp4"; break;
				case "ogv":  $strmime="video/ogg"; break;
				case "mov":  $strmime="video/quicktime"; break;
				case "mkv":  $strmime="video/x-matroska"; break;
			}
			$strdisplaytitle=($arrres["title"]!="")?$arrres["title"]:$arrres["identifier"];
			$strresult.="<div class=\"wikidata-media-item\">";
			$strresult.="<video controls width=\"480\"";
			if ($strthumb!="") {
				$strresult.=" poster=\"".htmlspecialchars($strthumb,ENT_QUOTES,"UTF-8")."\"";
			}
			$strresult.=">";
			$strresult.="<source src=\"".htmlspecialchars($strfile,ENT_QUOTES,"UTF-8")."\"";
			if ($strmime!="") {
				$strresult.=" type=\"".$strmime."\"";
			}
			$strresult.=" />";
			$strresult.="</video>";
			$strresult.=" <p>".htmlspecialchars($strdisplaytitle,ENT_QUOTES,"UTF-8");
			if ($strpage!="") {
				$strresult.=" &mdash; <a href=\"".htmlspecialchars($strpage,ENT_QUOTES,"UTF-8")."\" target=\"_blank\" rel=\"noopener\">View on Commons</a>";
			}
			$strresult.="</p></div>\n";
		}
		$strresult.="</div>\n";
	}

	// --- Commons audio ---
	if (count($arrgroups["commons_audio"])>0) {
		$strresult.="<h3>Wikimedia Commons &mdash; Audio</h3>\n";
		$strresult.="<div class=\"wikidata-media-list wikidata-media-commons-audio\">\n";
		foreach ($arrgroups["commons_audio"] as $arrres) {
			$strfile=isset($arrres["urls"]["file"])?$arrres["urls"]["file"]:"";
			$strpage=isset($arrres["urls"]["page"])?$arrres["urls"]["page"]:"";
			if ($strfile=="") { continue; }
			$strmime="";
			switch ($arrres["ext"]) {
				case "mp3":  $strmime="audio/mpeg"; break;
				case "oga":
				case "ogg":  $strmime="audio/ogg"; break;
				case "opus": $strmime="audio/opus"; break;
				case "flac": $strmime="audio/flac"; break;
				case "wav":  $strmime="audio/wav"; break;
			}
			$strdisplaytitle=($arrres["title"]!="")?$arrres["title"]:$arrres["identifier"];
			$strresult.="<div class=\"wikidata-media-item\">";
			$strresult.="<audio controls>";
			$strresult.="<source src=\"".htmlspecialchars($strfile,ENT_QUOTES,"UTF-8")."\"";
			if ($strmime!="") {
				$strresult.=" type=\"".$strmime."\"";
			}
			$strresult.=" />";
			$strresult.="</audio>";
			$strresult.=" <p>".htmlspecialchars($strdisplaytitle,ENT_QUOTES,"UTF-8");
			if ($strpage!="") {
				$strresult.=" &mdash; <a href=\"".htmlspecialchars($strpage,ENT_QUOTES,"UTF-8")."\" target=\"_blank\" rel=\"noopener\">View on Commons</a>";
			}
			$strresult.="</p></div>\n";
		}
		$strresult.="</div>\n";
	}

	// --- Commons images ---
	if (count($arrgroups["commons_image"])>0) {
		$strresult.="<h3>Wikimedia Commons &mdash; Images</h3>\n";
		$strresult.="<div class=\"wikidata-media-list wikidata-media-commons-image\">\n";
		foreach ($arrgroups["commons_image"] as $arrres) {
			$strthumb=isset($arrres["urls"]["thumbnail"])?$arrres["urls"]["thumbnail"]:"";
			$strpage=isset($arrres["urls"]["page"])?$arrres["urls"]["page"]:"";
			$strfile=isset($arrres["urls"]["file"])?$arrres["urls"]["file"]:"";
			$strimgsrc=$strthumb;
			if ($strimgsrc=="") { $strimgsrc=$strfile; }
			if ($strimgsrc=="") { continue; }
			$strdisplaytitle=($arrres["title"]!="")?$arrres["title"]:$arrres["identifier"];
			$strlinkurl=($strpage!="")?$strpage:$strimgsrc;
			$strresult.="<a class=\"wikidata-media-item\" href=\"".htmlspecialchars($strlinkurl,ENT_QUOTES,"UTF-8")."\" target=\"_blank\" rel=\"noopener\">";
			$strresult.="<img src=\"".htmlspecialchars($strimgsrc,ENT_QUOTES,"UTF-8")."\" alt=\"".htmlspecialchars($strdisplaytitle,ENT_QUOTES,"UTF-8")."\" title=\"".htmlspecialchars($strdisplaytitle,ENT_QUOTES,"UTF-8")."\" loading=\"lazy\" style=\"max-width:200px; max-height:200px; margin:4px; background-color:white;\" />";
			$strresult.="</a>\n";
		}
		$strresult.="</div>\n";
	}

	// --- Commons documents (PDF, DjVu, ...) ---
	if (count($arrgroups["commons_document"])>0) {
		$strresult.="<h3>Wikimedia Commons &mdash; Documents</h3>\n";
		$strresult.="<ul class=\"wikidata-media-list wikidata-media-commons-document\">\n";
		foreach ($arrgroups["commons_document"] as $arrres) {
			$strpage=isset($arrres["urls"]["page"])?$arrres["urls"]["page"]:"";
			$strfile=isset($arrres["urls"]["file"])?$arrres["urls"]["file"]:"";
			$strdisplaytitle=($arrres["title"]!="")?$arrres["title"]:$arrres["identifier"];
			$strresult.="<li>&#128196; ".htmlspecialchars($strdisplaytitle,ENT_QUOTES,"UTF-8");
			if ($strpage!="") {
				$strresult.=" &mdash; <a href=\"".htmlspecialchars($strpage,ENT_QUOTES,"UTF-8")."\" target=\"_blank\" rel=\"noopener\">page</a>";
			}
			if ($strfile!="") {
				$strresult.=" | <a href=\"".htmlspecialchars($strfile,ENT_QUOTES,"UTF-8")."\" target=\"_blank\" rel=\"noopener\">file</a>";
			}
			$strresult.="</li>\n";
		}
		$strresult.="</ul>\n";
	}

	// --- Internet Archive ---
	if (count($arrgroups["internet_archive"])>0) {
		$strresult.="<h3>Internet Archive</h3>\n";
		$strresult.="<div class=\"wikidata-media-list wikidata-media-archive\">\n";
		foreach ($arrgroups["internet_archive"] as $arrres) {
			$strpage=isset($arrres["urls"]["page"])?$arrres["urls"]["page"]:"";
			$strfile=isset($arrres["urls"]["file"])?$arrres["urls"]["file"]:"";
			$stridentifier=$arrres["identifier"];
			// Build embed URL on the fly (the resolver did not generate it yet).
			$strembed=($stridentifier!="")?"https://archive.org/embed/".rawurlencode($stridentifier):"";
			$strresult.="<div class=\"wikidata-media-item\">";
			if ($strembed!="") {
				$strresult.="<iframe width=\"480\" height=\"270\" src=\"".htmlspecialchars($strembed,ENT_QUOTES,"UTF-8")."\" frameborder=\"0\" allowfullscreen></iframe>";
			}
			$strresult.=" <p>&#128218; ".htmlspecialchars($stridentifier,ENT_QUOTES,"UTF-8");
			if ($strpage!="") {
				$strresult.=" &mdash; <a href=\"".htmlspecialchars($strpage,ENT_QUOTES,"UTF-8")."\" target=\"_blank\" rel=\"noopener\">page</a>";
			}
			if ($strfile!="") {
				$strresult.=" | <a href=\"".htmlspecialchars($strfile,ENT_QUOTES,"UTF-8")."\" target=\"_blank\" rel=\"noopener\">download</a>";
			}
			$strresult.="</p></div>\n";
		}
		$strresult.="</div>\n";
	}

	return $strresult;
}

function f_wikipediallsections($struilang,$stritemidwikidata,$strsep=", ") {
	global $intdebug;
	global $strsqlns;

	// A section the wikipedia-crawler (1.1.0 and later) has rewritten
	// carries CONTENT_MD, its Markdown, rendered with tables, lists, links and footnotes by
	// lib/wikipedia-md.inc.php. A section not yet recrawled has CONTENT only and keeps the
	// plain-text display, so a page can mix both during the backfill.
	$strresult="";
	$strtimupdated="";
	$arrsections=array();
	$blnmd=false;
	$strsql="";
	$strsql.="SELECT T_WC_WIKIPEDIA_PAGE_LANG_SECTION.TITLE, T_WC_WIKIPEDIA_PAGE_LANG_SECTION.CONTENT, T_WC_WIKIPEDIA_PAGE_LANG_SECTION.CONTENT_MD, ";
	$strsql.="T_WC_WIKIPEDIA_PAGE_LANG_SECTION.DISPLAY_ORDER, T_WC_WIKIPEDIA_PAGE_LANG_SECTION.TIM_UPDATED ";
	$strsql.="FROM T_WC_WIKIPEDIA_PAGE_LANG_SECTION ";
	$strsql.="WHERE T_WC_WIKIPEDIA_PAGE_LANG_SECTION.ID_WIKIDATA = ".f_stringtosql($stritemidwikidata)." ";
	$strsql.="AND T_WC_WIKIPEDIA_PAGE_LANG_SECTION.LANG = ".f_stringtosql($struilang)." ";
	$strsql.="AND T_WC_WIKIPEDIA_PAGE_LANG_SECTION.TITLE NOT IN ('Références','Voir aussi','Liens externes', 'Notes et références', 'Notes et références') ";
	$strsql.="AND T_WC_WIKIPEDIA_PAGE_LANG_SECTION.TITLE NOT IN ('References','See also','External links', 'Notes and references') ";
	$strsql.="ORDER BY T_WC_WIKIPEDIA_PAGE_LANG_SECTION.DISPLAY_ORDER ";
	if ($intdebug) {
		echo "<br />".$strsql;
	}
	$result=f_sqlselect($strsql);
	if ($result) {
		while ($rstemp=f_sqlresultfetcharray($result)) {
			$arrsections[]=$rstemp;
			if (trim((string)$rstemp["CONTENT_MD"])!="") {
				$blnmd=true;
			}
			if ($rstemp["TIM_UPDATED"]>$strtimupdated) {
				$strtimupdated=$rstemp["TIM_UPDATED"];
			}
		}
		f_sqlfreeresult($result);
	}
	$arrlinkmap=array();
	$arrrefs=array();
	if ($blnmd) {
		$arrlinkmap=f_wikipedialinkmap($struilang,$stritemidwikidata);
		$arrrefs=f_wikipediareferences($struilang,$stritemidwikidata);
	}
	foreach ($arrsections as $rstemp) {
		$strtitle=trim($rstemp["TITLE"]);
		$strmd=trim((string)$rstemp["CONTENT_MD"]);
		if ($strmd!="") {
			$intorder=(int)$rstemp["DISPLAY_ORDER"];
			$strresult.="<h3>".f_htmlspecialchars($strtitle)."</h3>";
			$strresult.="<div class=\"wp-section\">".f_wpmd_render($strmd,$struilang,$arrlinkmap);
			if (isset($arrrefs[$intorder])) {
				$strresult.=f_wpmd_references($arrrefs[$intorder],get_movie_string('lib_wikipedia_references'));
			}
			$strresult.="</div>";
		} else {
			$strcontent=str_replace("
","<br />",trim($rstemp["CONTENT"]));
			$strresult.="<h3>".$strtitle."</h3>";
			$strresult.="<p>".$strcontent."</p>";
		}
	}
	// Date Updated
	if ($strtimupdated!="") {
		$strresult .= "<p><strong>" . get_movie_string('lib_wikipedia_date_updated') . ":</strong> ";
		$strresult .= "" . $strtimupdated . "</p>";
		// CC BY-SA 4.0 attribution beside the reused text: the article, the licence, and the
		// fact that the text was adapted (split into sections, reformatted).
		$strresult .= f_wikipediaattribution($struilang,$stritemidwikidata);
	}
	// People, movies and series this article links to, and those whose article links here.
	$arrlabels=f_wikipediamentionlabels();
	$strresult .= f_wpmd_mentions(f_wikipediamentions($struilang,$stritemidwikidata,"forward"),get_movie_string('lib_wikipedia_mentions'),$arrlabels);
	$strresult .= f_wpmd_mentions(f_wikipediamentions($struilang,$stritemidwikidata,"backward"),get_movie_string('lib_wikipedia_mentioned_in'),$arrlabels);
	return $strresult;
}

function f_wikipediamentionlabels() {
	return array(
		"person"=>get_movie_string('lib_wikipedia_mentions_persons'),
		"movie"=>get_movie_string('lib_wikipedia_mentions_movies'),
		"serie"=>get_movie_string('lib_wikipedia_mentions_series'),
		"more"=>get_movie_string('lib_wikipedia_mentions_more'),
	);
}

// The persons, movies and series of the site linked with an entity through its Wikipedia
// article in the UI language, from T_WC_WIKIPEDIA_PAGE_LANG_LINK (wikipedia-crawler 1.1.0+).
//   forward:  the ones the entity's article links to, in the order they first appear in it
//   backward: the ones whose article links to the entity, most popular first
// One UNION query per direction, each kind capped at $intlimit rows; every row carries the
// kind's full count (TOTAL) so the page can say "and 12 more".
// Backward reads the covering index TARGET_LANG_SOURCE (TARGET_ID_WIKIDATA, LANG, ID_WIKIDATA)
// and at most 20,000 source pages: once the backfill is done a country or a city is linked
// from hundreds of thousands of pages, and only the first forty of each kind are shown anyway.
// The same rules live in fastapi-text2sql (_fetch_wikipedia_mentions): change both together.
function f_wikipediamentions($struilang,$stritemidwikidata,$strdirection="forward",$intlimit=0) {
	global $strsqlns;

	$arrrows=array();
	if (!preg_match('/^Q[0-9]+$/',(string)$stritemidwikidata)) {
		return $arrrows;
	}
	if ($intlimit<=0) {
		$intlimit=($strdirection=="forward") ? 60 : 40;
	}
	$strq=f_stringtosql($stritemidwikidata);
	$strlang=f_stringtosql($struilang);
	$blnfr=($struilang=="fr");
	if ($strdirection=="forward") {
		$strsource="SELECT TARGET_ID_WIKIDATA AS QID, MIN(SECTION_DISPLAY_ORDER*100000+DISPLAY_ORDER) AS SORTKEY FROM T_WC_WIKIPEDIA_PAGE_LANG_LINK ";
		$strsource.="WHERE ID_WIKIDATA = ".$strq." AND LANG = ".$strlang." AND TARGET_ID_WIKIDATA IS NOT NULL AND TARGET_ID_WIKIDATA <> ".$strq." AND IS_DATE = 0 ";
		$strsource.="GROUP BY TARGET_ID_WIKIDATA";
	} else {
		$strsource="SELECT DISTINCT ID_WIKIDATA AS QID, 0 AS SORTKEY FROM T_WC_WIKIPEDIA_PAGE_LANG_LINK ";
		$strsource.="WHERE TARGET_ID_WIKIDATA = ".$strq." AND LANG = ".$strlang." AND ID_WIKIDATA <> ".$strq." LIMIT 20000";
	}
	// Kind, table alias, table, id, label, first year, second year.
	$arrkinds=array(
		array("person","p","T2S_PERSON","ID_PERSON","p.PERSON_NAME","p.BIRTH_YEAR","p.DEATH_YEAR"),
		array("movie","m","T2S_MOVIE","ID_MOVIE",$blnfr ? "COALESCE(NULLIF(m.MOVIE_TITLE_FR,''),m.MOVIE_TITLE)" : "m.MOVIE_TITLE","m.RELEASE_YEAR","NULL"),
		array("serie","se","T2S_SERIE","ID_SERIE",$blnfr ? "COALESCE(NULLIF(se.SERIE_TITLE_FR,''),se.SERIE_TITLE)" : "se.SERIE_TITLE","se.FIRST_AIR_YEAR","NULL"),
	);
	$arrparts=array();
	foreach ($arrkinds as $k) {
		$strpart ="(SELECT '".$k[0]."' AS KIND, ".$k[1].".".$k[3]." AS ID, ".$k[4]." AS LABEL, ".$k[5]." AS YEAR1, ".$k[6]." AS YEAR2, COUNT(*) OVER () AS TOTAL ";
		$strpart.="FROM (".$strsource.") x INNER JOIN ".$strsqlns.$k[2]." ".$k[1]." ON ".$k[1].".ID_WIKIDATA = x.QID ";
		$strpart.="WHERE COALESCE(".$k[1].".DELETED,0) = 0 AND COALESCE(".$k[1].".ADULT,0) = 0 ";
		$strpart.="ORDER BY ".($strdirection=="forward" ? "x.SORTKEY" : $k[1].".POPULARITY DESC").", ".$k[1].".".$k[3]." LIMIT ".(int)$intlimit.")";
		$arrparts[]=$strpart;
	}
	$result=f_sqlselect(implode(" UNION ALL ",$arrparts));
	if ($result) {
		while ($rstemp=f_sqlresultfetcharray($result)) {
			$arrrows[]=$rstemp;
		}
		f_sqlfreeresult($result);
	}
	return $arrrows;
}

// Where each internal link of a page's CONTENT_MD points, keyed by Wikipedia title, for
// f_wpmd_render. A link whose target is a movie, person or series of this site stays on the
// site; French date links ("20 octobre", "2009 a la television") and red links lose their link,
// as they lead nowhere useful; every other title is left out of the map and links to Wikipedia.
function f_wikipedialinkmap($struilang,$stritemidwikidata) {
	global $strsqlns;

	$arrlinkmap=array();
	$arrtitlesbyqid=array();
	$strsql="SELECT DISTINCT TARGET_TITLE, IS_DATE, IS_REDLINK, TARGET_ID_WIKIDATA FROM T_WC_WIKIPEDIA_PAGE_LANG_LINK ";
	$strsql.="WHERE ID_WIKIDATA = ".f_stringtosql($stritemidwikidata)." AND LANG = ".f_stringtosql($struilang)." ";
	$result=f_sqlselect($strsql);
	if ($result) {
		while ($rstemp=f_sqlresultfetcharray($result)) {
			$strtarget=(string)$rstemp["TARGET_TITLE"];
			if ((int)$rstemp["IS_DATE"] || (int)$rstemp["IS_REDLINK"]) {
				$arrlinkmap[$strtarget]="";
			} elseif (preg_match('/^Q[0-9]+$/',(string)$rstemp["TARGET_ID_WIKIDATA"])) {
				$arrtitlesbyqid[$rstemp["TARGET_ID_WIKIDATA"]][]=$strtarget;
			}
		}
		f_sqlfreeresult($result);
	}
	if (!$arrtitlesbyqid) {
		return $arrlinkmap;
	}
	$strqids=implode(",",array_map('f_stringtosql',array_keys($arrtitlesbyqid)));
	// Person first, then movie, then series: the order f_wikidataallproperties uses.
	$arrpages=array(
		array("T2S_PERSON","ID_PERSON","person.php?personid="),
		array("T2S_MOVIE","ID_MOVIE","movie.php?movieid="),
		array("T2S_SERIE","ID_SERIE","serie.php?serieid="),
	);
	foreach ($arrpages as $arrpage) {
		$strsql="SELECT ID_WIKIDATA, ".$arrpage[1]." AS ID FROM ".$strsqlns.$arrpage[0]." WHERE ID_WIKIDATA IN (".$strqids.")";
		$result=f_sqlselect($strsql);
		if ($result) {
			while ($rstemp=f_sqlresultfetcharray($result)) {
				$strqid=$rstemp["ID_WIKIDATA"];
				if (!isset($arrtitlesbyqid[$strqid]) || (int)$rstemp["ID"]<=0) {
					continue;
				}
				foreach ($arrtitlesbyqid[$strqid] as $strtarget) {
					if (!array_key_exists($strtarget,$arrlinkmap)) {
						$arrlinkmap[$strtarget]=$arrpage[2].(int)$rstemp["ID"];
					}
				}
				unset($arrtitlesbyqid[$strqid]);
			}
			f_sqlfreeresult($result);
		}
	}
	return $arrlinkmap;
}

// The footnotes of a page, grouped by the DISPLAY_ORDER of the section that cites them.
function f_wikipediareferences($struilang,$stritemidwikidata) {
	$arrrefs=array();
	$strsql="SELECT SECTION_DISPLAY_ORDER, LABEL, URL, TITLE, REFERENCE_TEXT FROM T_WC_WIKIPEDIA_PAGE_LANG_REFERENCE ";
	$strsql.="WHERE ID_WIKIDATA = ".f_stringtosql($stritemidwikidata)." AND LANG = ".f_stringtosql($struilang)." ";
	$strsql.="ORDER BY SECTION_DISPLAY_ORDER, DISPLAY_ORDER";
	$result=f_sqlselect($strsql);
	if ($result) {
		while ($rstemp=f_sqlresultfetcharray($result)) {
			$arrrefs[(int)$rstemp["SECTION_DISPLAY_ORDER"]][]=$rstemp;
		}
		f_sqlfreeresult($result);
	}
	return $arrrefs;
}

// Ligne "Wikipedia" de la section Identifiants externes : lien vers l'article dans la langue
// de l'interface, s'il a ete recolte (T_WC_WIKIPEDIA_PAGE_LANG). Chaine vide sinon.
function f_wikipediaidline($struilang,$stritemidwikidata) {
	if (trim((string)$stritemidwikidata)=="") {
		return "";
	}
	$strtitle="";
	$strurl="";
	$strsql="SELECT WIKIPEDIA_PAGE_TITLE, WIKIPEDIA_PAGE_URL FROM T_WC_WIKIPEDIA_PAGE_LANG ";
	$strsql.="WHERE ID_WIKIDATA = ".f_stringtosql($stritemidwikidata)." AND LANG = ".f_stringtosql($struilang)." ";
	$result=f_sqlselect($strsql);
	if ($result) {
		if ($rstemp=f_sqlresultfetcharray($result)) {
			$strtitle=trim((string)$rstemp["WIKIPEDIA_PAGE_TITLE"]);
			$strurl=trim((string)$rstemp["WIKIPEDIA_PAGE_URL"]);
		}
		f_sqlfreeresult($result);
	}
	if ($strurl=="" && $strtitle!="") {
		$strurl="https://".$struilang.".wikipedia.org/wiki/".rawurlencode(str_replace(" ","_",$strtitle));
	}
	if ($strurl=="") {
		return "";
	}
	$strlabel=($strtitle!=""?$strtitle:$strurl);
	return "<p><strong>".get_movie_string('lib_wikipedia_section')." (".$struilang."):</strong> <a href=\"".htmlspecialchars($strurl, ENT_QUOTES, 'UTF-8')."\" target=\"_blank\" rel=\"noreferrer\">".htmlspecialchars($strlabel, ENT_QUOTES, 'UTF-8')."</a></p>";
}

function f_wikipediaattribution($struilang,$stritemidwikidata) {
	$strtitle="";
	$strurl="";
	$strsql="SELECT WIKIPEDIA_PAGE_TITLE, WIKIPEDIA_PAGE_URL FROM T_WC_WIKIPEDIA_PAGE_LANG ";
	$strsql.="WHERE ID_WIKIDATA = ".f_stringtosql($stritemidwikidata)." AND LANG = ".f_stringtosql($struilang)." ";
	$result=f_sqlselect($strsql);
	if ($result) {
		if ($rstemp=f_sqlresultfetcharray($result)) {
			$strtitle=trim((string)$rstemp["WIKIPEDIA_PAGE_TITLE"]);
			$strurl=trim((string)$rstemp["WIKIPEDIA_PAGE_URL"]);
		}
		f_sqlfreeresult($result);
	}
	if ($strurl=="" && $strtitle!="") {
		$strurl="https://".$struilang.".wikipedia.org/wiki/".rawurlencode(str_replace(" ","_",$strtitle));
	}
	$strarticle=get_movie_string('lib_wikipedia_source_article');
	if ($strtitle!="") {
		if ($struilang=="fr") {
			$strarticle.=" &laquo;&nbsp;".htmlspecialchars($strtitle, ENT_QUOTES, 'UTF-8')."&nbsp;&raquo;";
		} else {
			$strarticle.=" &ldquo;".htmlspecialchars($strtitle, ENT_QUOTES, 'UTF-8')."&rdquo;";
		}
	}
	if ($strurl!="") {
		$strarticle="<a href=\"".htmlspecialchars($strurl, ENT_QUOTES, 'UTF-8')."\" target=\"_blank\" rel=\"noreferrer\">".$strarticle."</a>";
	}
	return "<p><small>".get_movie_string('lib_wikipedia_source')." ".$strarticle.", ".get_movie_string('lib_wikipedia_licence')."</small></p>";
}

function f_extractimdbids($strinputstring,$intunique=1) {
    // Define the regular expression pattern to match '/title/tt.../'
    $pattern = '/\/title\/(tt[0-9]+)\//';

    // Use preg_match_all to find all matches of the pattern in the input string
    preg_match_all($pattern, $strinputstring, $matches);
	if ($intunique) {
		$arrresult = array_unique($matches[1]);
	} else {
		$arrresult = $matches[1];
	}
    // The tt values will be in the second element of the matches array
    return $arrresult;
}

//Define labels
include_once(__DIR__."/language.inc.php");
$strlibhome=get_movie_string('lib_home_menu');

// Colors for similarity: blue
$strbgcolorsimilarity="#33ccff";

// Colors for tag types
$strbgcolorcollection="#33ccff";
$strbgcolorgenre="#33cc99";
$strbgcolorkeyword="#3399ff";
$strbgcolorlist="#66cccc";
$strbgcolorperson="#3399cc";
$strbgcolorcompany="#339999";

// Colors for Part Of Speech
$strbgcolorpropn="#33ccff";
$strbgcolornoun="#33cc99";
$strbgcolorverb="#3399ff";
$strbgcolornum="#339999";
$strbgcoloradj="#3399cc";
$strbgcolorx="#66cccc";

// Colors for Named Entity Recognition
$strbgcolorloc="#ff9966";
$strbgcolormisc="#cc6600";
$strbgcolororg="#ff6600";
$strbgcolorper="#ff0000";

// Colors for search: yellow
$strbgcolorsearch="#ffff99";

?>
