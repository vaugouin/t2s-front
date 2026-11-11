<?php

include_once(__DIR__."/work-properties-format.inc.php");

function f_watchcountryisvalid($strcountrycode) {
	global $strsqlns;
	$strcountrycode=f_workpropertycountrycode($strcountrycode);
	if ($strcountrycode=="") {
		return false;
	}
	$strsql="SELECT COUNT(*) FROM ".$strsqlns."COUNTRY ";
	$strsql.="WHERE COUNTRY_CODE = ".f_stringtosql($strcountrycode)." ";
	$strsql.="AND (DELETED = 0 OR DELETED IS NULL)";
	return f_stringtolong(f_fieldfromquery($strsql))>0;
}

function f_watchcountryresolve() {
	$strcookiename="watch_country";
	$intrequestset=f_requestisfieldset($strcookiename);
	$strcountrycode="";
	if ($intrequestset) {
		$strcountrycode=f_workpropertycountrycode(f_request2($strcookiename));
		if ($strcountrycode!="" && f_watchcountryisvalid($strcountrycode)) {
			setcookie($strcookiename, $strcountrycode, time()+(365*24*60*60), "/");
			$_COOKIE[$strcookiename]=$strcountrycode;
			return $strcountrycode;
		}
		setcookie($strcookiename, "", time()-3600, "/");
		unset($_COOKIE[$strcookiename]);
		return "";
	}
	if (isset($_COOKIE[$strcookiename])) {
		$strcountrycode=f_workpropertycountrycode($_COOKIE[$strcookiename]);
		if ($strcountrycode!="" && f_watchcountryisvalid($strcountrycode)) {
			return $strcountrycode;
		}
	}
	return "";
}

function f_watchcountryoptions($strdisplaylanguage) {
	global $strsqlns;
	$strfieldname=($strdisplaylanguage==="fr"?"COUNTRY_NAME_FR":"COUNTRY_NAME_EN");
	$arrcountries=array();
	$strsql="SELECT COUNTRY_CODE, ".$strfieldname." AS COUNTRY_NAME FROM ".$strsqlns."COUNTRY ";
	$strsql.="WHERE COUNTRY_CODE IS NOT NULL AND (DELETED = 0 OR DELETED IS NULL) ";
	$strsql.="ORDER BY COUNTRY_NAME, COUNTRY_CODE";
	$result=f_sqlselect($strsql);
	if ($result) {
		while ($arrcountry=f_sqlresultfetcharray($result)) {
			$strcountrycode=f_workpropertycountrycode($arrcountry["COUNTRY_CODE"] ?? "");
			if ($strcountrycode=="") {
				continue;
			}
			$strcountryname=trim((string)($arrcountry["COUNTRY_NAME"] ?? ""));
			$arrcountries[$strcountrycode]=($strcountryname!=""?$strcountryname:$strcountrycode);
		}
		f_sqlfreeresult($result);
	}
	return $arrcountries;
}

/**
 * Translate a country code from the map f_watchcountryoptions() already loaded.
 *
 * That map holds every country of T_WC_COUNTRY in the interface language and is
 * built once per page, so the lookup costs nothing. f_countrycodetranslate()
 * issues one query per code, which the release-date list turned into one query
 * per country of release; going through the map removes them all.
 *
 * A code absent from the map (a country row flagged DELETED, which the map
 * filters out) still falls back to that query, so the displayed label never
 * changes. The function_exists() guard keeps this file loadable on its own for
 * test/work-properties-format-test.php, which does not open a database.
 */
function f_countrycodetranslatefrommap($strcountrycode, $arrcountries, $strdisplaylanguage) {
	$strnormalizedcode=f_workpropertycountrycode($strcountrycode);
	if ($strnormalizedcode!="" && isset($arrcountries[$strnormalizedcode])) {
		return $arrcountries[$strnormalizedcode];
	}
	if (function_exists("f_countrycodetranslate")) {
		return f_countrycodetranslate($strcountrycode, $strdisplaylanguage);
	}
	return ($strnormalizedcode!=""?$strnormalizedcode:(string)$strcountrycode);
}

function f_watchcountryselectorhtml($strselectedcountry, $arrcountries, $arrhiddenfields,
		$strselectid, $strlabel, $strchooselabel, $strapplylabel) {
	$strselectedcountry=f_workpropertycountrycode($strselectedcountry);
	$strhtml="<form method=\"get\" class=\"work-property-country-form\">";
	foreach ((array)$arrhiddenfields as $strname=>$strvalue) {
		$strhtml.="<input type=\"hidden\" name=\"".f_workpropertyescape($strname).
			"\" value=\"".f_workpropertyescape($strvalue)."\">";
	}
	$strhtml.="<label for=\"".f_workpropertyescape($strselectid)."\"><strong>".
		f_workpropertyescape($strlabel).":</strong></label> ";
	$strhtml.="<select id=\"".f_workpropertyescape($strselectid)."\" name=\"watch_country\">";
	$strhtml.="<option value=\"\"".($strselectedcountry===""?" selected":"").">".
		f_workpropertyescape($strchooselabel)."</option>";
	foreach ((array)$arrcountries as $strcountrycode=>$strcountryname) {
		$strcountrycode=f_workpropertycountrycode($strcountrycode);
		if ($strcountrycode=="") {
			continue;
		}
		$strhtml.="<option value=\"".f_workpropertyescape($strcountrycode)."\"".
			($strcountrycode===$strselectedcountry?" selected":"").">".
			f_workpropertyescape($strcountryname)." (".f_workpropertyescape($strcountrycode).")</option>";
	}
	$strhtml.="</select> <button type=\"submit\">".f_workpropertyescape($strapplylabel)."</button></form>";
	return $strhtml;
}

?>
