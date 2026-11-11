<?php

include_once(__DIR__."/work-properties-format.inc.php");

function f_workpropertywatchproviderssql($strsqlns, $strworktype, $lngworkid,
		$strcountrycode) {
	$strcountrycode=f_workpropertycountrycode($strcountrycode);
	$lngworkid=(int)$lngworkid;
	if ($lngworkid<=0 || $strcountrycode=="") {
		return "";
	}
	if ($strworktype==="movie") {
		$strtablename=$strsqlns."TMDB_MOVIE_WATCH_PROVIDER";
		$stridcolumn="ID_MOVIE";
	} elseif ($strworktype==="serie") {
		$strtablename=$strsqlns."TMDB_SERIE_WATCH_PROVIDER";
		$stridcolumn="ID_SERIE";
	} else {
		return "";
	}
	$strsql="SELECT ID_PROVIDER, PROVIDER_NAME, LOGO_PATH, ";
	$strsql.="MONETIZATION_TYPE, PROVIDER_DISPLAY_PRIORITY, TMDB_LINK, ";
	$strsql.="DISPLAY_ORDER, TIM_PROVIDER_UPDATED ";
	$strsql.="FROM ".$strtablename." ";
	$strsql.="WHERE ".$stridcolumn." = ".$lngworkid." ";
	$strsql.="AND COUNTRY_CODE = '".$strcountrycode."' ";
	$strsql.="AND (DELETED = 0 OR DELETED IS NULL) ";
	$strsql.="ORDER BY CASE MONETIZATION_TYPE ";
	$strsql.="WHEN 'flatrate' THEN 1 WHEN 'free' THEN 2 WHEN 'ads' THEN 3 ";
	$strsql.="WHEN 'rent' THEN 4 WHEN 'buy' THEN 5 ELSE 6 END, ";
	$strsql.="COALESCE(PROVIDER_DISPLAY_PRIORITY, 2147483647), ";
	$strsql.="COALESCE(DISPLAY_ORDER, 2147483647), ID_PROVIDER";
	return $strsql;
}

function f_workpropertymoviereleasedatessql($strsqlns, $lngmovieid,
		$strpreferredcountry="") {
	$lngmovieid=(int)$lngmovieid;
	if ($lngmovieid<=0) {
		return "";
	}
	$strpreferredcountry=f_workpropertycountrycode($strpreferredcountry);
	$strsql="SELECT COUNTRY_CODE, LANG, CERTIFICATION, NOTE, RELEASE_DATE_RAW, ";
	$strsql.="TIM_RELEASE, RELEASE_TYPE, DESCRIPTORS_JSON, DISPLAY_ORDER ";
	$strsql.="FROM ".$strsqlns."TMDB_MOVIE_RELEASE_DATE ";
	$strsql.="WHERE ID_MOVIE = ".$lngmovieid." ";
	$strsql.="AND (DELETED = 0 OR DELETED IS NULL) ";
	$strsql.="ORDER BY ";
	if ($strpreferredcountry!="") {
		$strsql.="CASE WHEN COUNTRY_CODE = '".$strpreferredcountry."' THEN 0 ELSE 1 END, ";
	}
	$strsql.="COUNTRY_CODE, RELEASE_TYPE, ";
	$strsql.="CASE WHEN TIM_RELEASE IS NULL THEN 1 ELSE 0 END, ";
	$strsql.="TIM_RELEASE, DISPLAY_ORDER, ID_ROW";
	return $strsql;
}

function f_workpropertyprovideridentitysql($strsqlns, $lngproviderid) {
	$lngproviderid=(int)$lngproviderid;
	if ($lngproviderid<=0) {
		return "";
	}
	return "SELECT ID_PROVIDER, PROVIDER_NAME, LOGO_PATH, TIM_PROVIDER_UPDATED, DELETED ".
		"FROM ".$strsqlns."TMDB_WATCH_PROVIDER WHERE ID_PROVIDER = ".$lngproviderid." LIMIT 1";
}

function f_workpropertyprovidercatalogsql($strsqlns, $lngproviderid) {
	$lngproviderid=(int)$lngproviderid;
	if ($lngproviderid<=0) {
		return "";
	}
	$strsql="SELECT C.CONTENT_TYPE, C.PROVIDER_NAME, C.LOGO_PATH, C.TIM_PROVIDER_UPDATED, ";
	$strsql.="S.TIM_COMPLETED FROM ".$strsqlns."TMDB_WATCH_PROVIDER_CATALOG C ";
	$strsql.="LEFT JOIN ".$strsqlns."TMDB_WATCH_PROVIDER_CATALOG_STATE S ";
	$strsql.="ON S.CONTENT_TYPE = C.CONTENT_TYPE ";
	$strsql.="WHERE C.ID_PROVIDER = ".$lngproviderid." AND (C.DELETED = 0 OR C.DELETED IS NULL) ";
	$strsql.="ORDER BY CASE C.CONTENT_TYPE WHEN 'movie' THEN 1 WHEN 'serie' THEN 2 ELSE 3 END";
	return $strsql;
}

function f_workpropertyproviderregionssql($strsqlns, $lngproviderid) {
	$lngproviderid=(int)$lngproviderid;
	if ($lngproviderid<=0) {
		return "";
	}
	$strsql="SELECT CONTENT_TYPE, COUNTRY_CODE, PROVIDER_DISPLAY_PRIORITY, TIM_PROVIDER_UPDATED ";
	$strsql.="FROM ".$strsqlns."TMDB_WATCH_PROVIDER_REGION ";
	$strsql.="WHERE ID_PROVIDER = ".$lngproviderid." AND (DELETED = 0 OR DELETED IS NULL) ";
	$strsql.="ORDER BY CASE CONTENT_TYPE WHEN 'movie' THEN 1 WHEN 'serie' THEN 2 ELSE 3 END, ";
	$strsql.="PROVIDER_DISPLAY_PRIORITY, COUNTRY_CODE";
	return $strsql;
}

function f_workpropertyproviderworkssql($strsqlns, $strworktype, $lngproviderid,
		$strcountrycode, $strdisplaylanguage="en", $lnglimit=50, $lngoffset=0) {
	$lngproviderid=(int)$lngproviderid;
	$strcountrycode=f_workpropertycountrycode($strcountrycode);
	$lnglimit=(int)$lnglimit;
	if ($lnglimit<=0) {
		$lnglimit=50;
	}
	$lnglimit=min($lnglimit, 100);
	$lngoffset=max(0, (int)$lngoffset);
	if ($lngproviderid<=0 || $strcountrycode==="") {
		return "";
	}
	$inttranslated=($strdisplaylanguage!=="en");
	if ($strworktype==="movie") {
		$strassociation=$strsqlns."TMDB_MOVIE_WATCH_PROVIDER";
		$strparent=$strsqlns."TMDB_MOVIE";
		$strlangtable=$strsqlns."TMDB_MOVIE_LANG";
		$stridcolumn="ID_MOVIE";
		$strdatecolumn="DAT_RELEASE";
	} elseif ($strworktype==="serie") {
		$strassociation=$strsqlns."TMDB_SERIE_WATCH_PROVIDER";
		$strparent=$strsqlns."TMDB_SERIE";
		$strlangtable=$strsqlns."TMDB_SERIE_LANG";
		$stridcolumn="ID_SERIE";
		$strdatecolumn="DAT_FIRST_AIR";
	} else {
		return "";
	}
	$strtitleexpression=($inttranslated?"COALESCE(NULLIF(L.TITLE, ''), P.TITLE)":"P.TITLE");
	$strposterexpression=($inttranslated?"COALESCE(NULLIF(L.POSTER_PATH, ''), P.POSTER_PATH)":"P.POSTER_PATH");
	$strsql="SELECT A.".$stridcolumn." AS ID_WORK, ".$strtitleexpression." AS WORK_TITLE, ";
	$strsql.=$strposterexpression." AS POSTER_PATH, P.".$strdatecolumn." AS WORK_DATE, ";
	$strsql.="GROUP_CONCAT(DISTINCT A.MONETIZATION_TYPE ORDER BY FIELD(A.MONETIZATION_TYPE, ";
	$strsql.="'flatrate','free','ads','rent','buy') SEPARATOR ',') AS MONETIZATION_TYPES ";
	$strsql.="FROM ".$strassociation." A INNER JOIN ".$strparent." P ";
	$strsql.="ON P.".$stridcolumn." = A.".$stridcolumn." ";
	if ($inttranslated) {
		$strsql.="LEFT JOIN ".$strlangtable." L ON L.".$stridcolumn." = A.".$stridcolumn." ";
		$strsql.="AND L.LANG = '".str_replace("'", "", substr($strdisplaylanguage, 0, 10))."' ";
	}
	$strsql.="WHERE A.ID_PROVIDER = ".$lngproviderid." AND A.COUNTRY_CODE = '".$strcountrycode."' ";
	$strsql.="AND (A.DELETED = 0 OR A.DELETED IS NULL) AND P.DELETED = 0 ";
	$strsql.="AND P.TIM_CREDITS_COMPLETED IS NOT NULL ";
	$strsql.="GROUP BY A.".$stridcolumn.", ".$strtitleexpression.", ".$strposterexpression.", ";
	$strsql.="P.".$strdatecolumn.", P.POPULARITY ORDER BY P.POPULARITY DESC, A.".$stridcolumn." ";
	$strsql.="LIMIT ".$lnglimit." OFFSET ".$lngoffset;
	return $strsql;
}

function f_workpropertyproviderworkscountsql($strsqlns, $strworktype, $lngproviderid,
		$strcountrycode) {
	$lngproviderid=(int)$lngproviderid;
	$strcountrycode=f_workpropertycountrycode($strcountrycode);
	if ($lngproviderid<=0 || $strcountrycode==="") {
		return "";
	}
	if ($strworktype==="movie") {
		$strassociation=$strsqlns."TMDB_MOVIE_WATCH_PROVIDER";
		$strparent=$strsqlns."TMDB_MOVIE";
		$stridcolumn="ID_MOVIE";
	} elseif ($strworktype==="serie") {
		$strassociation=$strsqlns."TMDB_SERIE_WATCH_PROVIDER";
		$strparent=$strsqlns."TMDB_SERIE";
		$stridcolumn="ID_SERIE";
	} else {
		return "";
	}
	$strsql="SELECT COUNT(DISTINCT A.".$stridcolumn.") FROM ".$strassociation." A ";
	$strsql.="INNER JOIN ".$strparent." P ON P.".$stridcolumn." = A.".$stridcolumn." ";
	$strsql.="WHERE A.ID_PROVIDER = ".$lngproviderid." AND A.COUNTRY_CODE = '".$strcountrycode."' ";
	$strsql.="AND (A.DELETED = 0 OR A.DELETED IS NULL) AND P.DELETED = 0 ";
	$strsql.="AND P.TIM_CREDITS_COMPLETED IS NOT NULL";
	return $strsql;
}

?>
