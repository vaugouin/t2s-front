<?php

/**
 * Side-effect-free formatting helpers for work properties.
 *
 * This file deliberately opens no database connection so its behavior can be
 * characterized by test/work-properties-format-test.php.
 */

function f_workpropertyescape($strvalue) {
	return htmlspecialchars((string)$strvalue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function f_workpropertycountrycode($strvalue) {
	$strcountrycode=strtoupper(trim((string)$strvalue));
	if (!preg_match('/^[A-Z]{2}$/', $strcountrycode)) {
		return "";
	}
	return $strcountrycode;
}

function f_workpropertysnapshotstate($strcompletionmarker, $lngrowcount) {
	if (trim((string)$strcompletionmarker)==="") {
		return "unknown";
	}
	return ((int)$lngrowcount>0?"data":"empty");
}

function f_workpropertywatchmodes() {
	return array("flatrate", "free", "ads", "rent", "buy");
}

function f_workpropertywatchprovidergroups($arrrows) {
	$arrgroups=array();
	foreach (f_workpropertywatchmodes() as $strmode) {
		$arrgroups[$strmode]=array();
	}
	foreach ((array)$arrrows as $arrrow) {
		$strmode=strtolower(trim((string)($arrrow["MONETIZATION_TYPE"] ?? "")));
		if (!array_key_exists($strmode, $arrgroups)) {
			continue;
		}
		$arrgroups[$strmode][]=$arrrow;
	}
	return $arrgroups;
}

function f_workpropertywatchprovidershtml($arrrows, $arrmodelabels,
		$strimagebaseurl, $strproviderfallback, $strwatchcountry="") {
	$arrgroups=f_workpropertywatchprovidergroups($arrrows);
	$strwatchcountry=f_workpropertycountrycode($strwatchcountry);
	$strhtml="<div class=\"work-property-provider-groups\">";
	foreach ($arrgroups as $strmode=>$arrproviders) {
		if (count($arrproviders)==0) {
			continue;
		}
		$strmodelabel=$arrmodelabels[$strmode] ?? $strmode;
		$strhtml.="<section class=\"work-property-provider-group\">";
		$strhtml.="<h3>".f_workpropertyescape($strmodelabel)."</h3>";
		$strhtml.="<ul style=\"display:flex;flex-wrap:wrap;gap:10px;list-style:none;padding:0;\">";
		foreach ($arrproviders as $arrprovider) {
			$lngproviderid=(int)($arrprovider["ID_PROVIDER"] ?? 0);
			$strprovidername=trim((string)($arrprovider["PROVIDER_NAME"] ?? ""));
			if ($strprovidername=="") {
				$strprovidername=trim((string)$strproviderfallback)." #".$lngproviderid;
			}
			$strlogopath=trim((string)($arrprovider["LOGO_PATH"] ?? ""));
			$strhtml.="<li style=\"min-width:150px;max-width:240px;\">";
			if ($lngproviderid>0) {
				$strproviderurl="provider.php?providerid=".$lngproviderid;
				if ($strwatchcountry!="") {
					$strproviderurl.="&watch_country=".rawurlencode($strwatchcountry);
				}
				$strhtml.="<a href=\"".f_workpropertyescape($strproviderurl).
					"\" style=\"display:flex;align-items:center;gap:8px;\">";
			} else {
				$strhtml.="<span style=\"display:flex;align-items:center;gap:8px;\">";
			}
			if ($strlogopath!="") {
				$strhtml.="<img loading=\"lazy\" decoding=\"async\" src=\"".
					f_workpropertyescape(rtrim((string)$strimagebaseurl, "/")."/".ltrim($strlogopath, "/")).
					"\" alt=\"".f_workpropertyescape($strprovidername).
					"\" width=\"92\" style=\"max-width:92px;max-height:48px;object-fit:contain;background:#fff;padding:4px;border-radius:4px;\">";
			}
			$strhtml.="<span>".f_workpropertyescape($strprovidername)."</span>";
			$strhtml.=($lngproviderid>0?"</a>":"</span>")."</li>";
		}
		$strhtml.="</ul></section>";
	}
	$strhtml.="</div>";
	return $strhtml;
}

function f_workpropertyprovidermodes($strmodes, $arrmodelabels) {
	$arrlabels=array();
	foreach (explode(",", (string)$strmodes) as $strmode) {
		$strmode=strtolower(trim($strmode));
		if ($strmode!="" && isset($arrmodelabels[$strmode])) {
			$arrlabels[]=$arrmodelabels[$strmode];
		}
	}
	return array_values(array_unique($arrlabels));
}

function f_workpropertyproviderworkshtml($arrrows, $strworktype, $arrmodelabels,
		$strimagebaseurl, $stremptylabel, $strwatchcountry="") {
	if (count((array)$arrrows)===0) {
		return "<p>".f_workpropertyescape($stremptylabel)."</p>";
	}
	$strwatchcountry=f_workpropertycountrycode($strwatchcountry);
	$stridparameter=($strworktype==="serie"?"serieid":"movieid");
	$strpage=($strworktype==="serie"?"serie.php":"movie.php");
	$strhtml="<ul style=\"display:flex;flex-wrap:wrap;gap:16px;list-style:none;padding:0;\">";
	foreach ((array)$arrrows as $arrrow) {
		$lngworkid=(int)($arrrow["ID_WORK"] ?? 0);
		$strtitle=trim((string)($arrrow["WORK_TITLE"] ?? ""));
		if ($strtitle==="") {
			$strtitle="#".$lngworkid;
		}
		$strposterpath=trim((string)($arrrow["POSTER_PATH"] ?? ""));
		$strdate=trim((string)($arrrow["WORK_DATE"] ?? ""));
		if (strlen($strdate)>=4) {
			$strdate=substr($strdate, 0, 4);
		}
		$arrmodes=f_workpropertyprovidermodes($arrrow["MONETIZATION_TYPES"] ?? "", $arrmodelabels);
		$strworkurl=$strpage."?".$stridparameter."=".$lngworkid;
		if ($strwatchcountry!=="") {
			$strworkurl.="&watch_country=".rawurlencode($strwatchcountry);
		}
		$strhtml.="<li style=\"width:154px;\"><a href=\"".
			f_workpropertyescape($strworkurl)."\">";
		if ($strposterpath!="") {
			$strhtml.="<img loading=\"lazy\" decoding=\"async\" src=\"".
				f_workpropertyescape(rtrim((string)$strimagebaseurl, "/")."/".ltrim($strposterpath, "/")).
				"\" alt=\"".f_workpropertyescape($strtitle)."\" width=\"154\" style=\"display:block;\">";
		}
		$strhtml.="<strong>".f_workpropertyescape($strtitle)."</strong></a>";
		if ($strdate!="") {
			$strhtml.=" <span>(".f_workpropertyescape($strdate).")</span>";
		}
		if (count($arrmodes)>0) {
			$strhtml.="<br><small>".f_workpropertyescape(implode(" · ", $arrmodes))."</small>";
		}
		$strhtml.="</li>";
	}
	$strhtml.="</ul>";
	return $strhtml;
}

function f_workpropertypaginationinfo($lngpage, $lngrowsperpage, $lngtotalrows) {
	$lngpage=max(1, (int)$lngpage);
	$lngrowsperpage=(int)$lngrowsperpage;
	if ($lngrowsperpage<=0) {
		$lngrowsperpage=50;
	}
	$lngtotalrows=max(0, (int)$lngtotalrows);
	$lngtotalpages=max(1, (int)ceil($lngtotalrows/$lngrowsperpage));
	$lngpage=min($lngpage, $lngtotalpages);
	$lngoffset=($lngpage-1)*$lngrowsperpage;
	return array(
		"page"=>$lngpage,
		"rows_per_page"=>$lngrowsperpage,
		"total_rows"=>$lngtotalrows,
		"total_pages"=>$lngtotalpages,
		"offset"=>$lngoffset,
		"first_row"=>($lngtotalrows>0?$lngoffset+1:0),
		"last_row"=>($lngtotalrows>0?min($lngoffset+$lngrowsperpage, $lngtotalrows):0)
	);
}

function f_workpropertyproviderpaginationurl($lngproviderid, $strwatchcountry,
		$lngmoviepage, $lngseriepage, $stranchor) {
	$lngproviderid=max(0, (int)$lngproviderid);
	$strwatchcountry=f_workpropertycountrycode($strwatchcountry);
	$lngmoviepage=max(1, (int)$lngmoviepage);
	$lngseriepage=max(1, (int)$lngseriepage);
	$stranchor=preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$stranchor);
	$strurl="provider.php?providerid=".$lngproviderid;
	if ($strwatchcountry!=="") {
		$strurl.="&watch_country=".rawurlencode($strwatchcountry);
	}
	$strurl.="&movie_page=".$lngmoviepage."&serie_page=".$lngseriepage;
	if ($stranchor!=="") {
		$strurl.="#".$stranchor;
	}
	return $strurl;
}

function f_workpropertyproviderpaginationhtml($lngproviderid, $strwatchcountry,
		$strworktype, $arrpageinfo, $lngmoviepage, $lngseriepage, $arrlabels) {
	$lngtotalrows=(int)($arrpageinfo["total_rows"] ?? 0);
	if ($lngtotalrows<=0) {
		return "";
	}
	$lngpage=max(1, (int)($arrpageinfo["page"] ?? 1));
	$lngtotalpages=max(1, (int)($arrpageinfo["total_pages"] ?? 1));
	$lngfirstrow=max(1, (int)($arrpageinfo["first_row"] ?? 1));
	$lnglastrow=max($lngfirstrow, (int)($arrpageinfo["last_row"] ?? $lngfirstrow));
	$strpageparameter=($strworktype==="serie"?"serie":"movie");
	$stranchor="provider-".($strworktype==="serie"?"series":"movies");
	$strarialabel=(string)($arrlabels["aria"] ?? "Pagination");
	$strsummarytemplate=(string)($arrlabels["summary"] ?? "Showing %1\$d-%2\$d of %3\$d, page %4\$d of %5\$d");
	$strhtml="<nav aria-label=\"".f_workpropertyescape($strarialabel)."\" class=\"work-property-pagination\">";
	$strhtml.="<p>".f_workpropertyescape(sprintf(
		$strsummarytemplate, $lngfirstrow, $lnglastrow, $lngtotalrows, $lngpage, $lngtotalpages))."</p>";
	if ($lngtotalpages>1) {
		$strhtml.="<p>";
		if ($lngpage>1) {
			$lngpreviouspage=$lngpage-1;
			$strpreviousurl=f_workpropertyproviderpaginationurl(
				$lngproviderid, $strwatchcountry,
				($strpageparameter==="movie"?$lngpreviouspage:$lngmoviepage),
				($strpageparameter==="serie"?$lngpreviouspage:$lngseriepage),
				$stranchor
			);
			$strhtml.="<a rel=\"prev\" href=\"".f_workpropertyescape($strpreviousurl)."\">".
				f_workpropertyescape($arrlabels["previous"] ?? "Previous")."</a>";
		}
		if ($lngpage>1 && $lngpage<$lngtotalpages) {
			$strhtml.=" · ";
		}
		if ($lngpage<$lngtotalpages) {
			$lngnextpage=$lngpage+1;
			$strnexturl=f_workpropertyproviderpaginationurl(
				$lngproviderid, $strwatchcountry,
				($strpageparameter==="movie"?$lngnextpage:$lngmoviepage),
				($strpageparameter==="serie"?$lngnextpage:$lngseriepage),
				$stranchor
			);
			$strhtml.="<a rel=\"next\" href=\"".f_workpropertyescape($strnexturl)."\">".
				f_workpropertyescape($arrlabels["next"] ?? "Next")."</a>";
		}
		$strhtml.="</p>";
	}
	$strhtml.="</nav>";
	return $strhtml;
}

function f_adaptationspaginationurl($strhostpage, $lnghostid, $lngmoviepage, $lngseriepage, $stranchor) {
	//Purpose: One page link of an "adapted from this work" list on a movie / serie sheet. The two
	//         lists page independently, so each link carries the other list's current page, like the
	//         source-work sheet it replaces for a film or series source work.
	//Returns: The URL (movie.php?movieid=... or serie.php?serieid=...), with adapt_movie_page /
	//         adapt_serie_page and the anchor
	$strhostpage=($strhostpage==="serie"?"serie":"movie");
	$strparam=($strhostpage==="serie"?"serieid":"movieid");
	$lnghostid=max(0, (int)$lnghostid);
	$lngmoviepage=max(1, (int)$lngmoviepage);
	$lngseriepage=max(1, (int)$lngseriepage);
	$stranchor=preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$stranchor);
	$strurl=$strhostpage.".php?".$strparam."=".$lnghostid;
	$strurl.="&adapt_movie_page=".$lngmoviepage."&adapt_serie_page=".$lngseriepage;
	if ($stranchor!=="") {
		$strurl.="#".$stranchor;
	}
	return $strurl;
}

function f_adaptationspaginationhtml($strhostpage, $lnghostid, $strworktype, $arrpageinfo, $lngmoviepage, $lngseriepage) {
	//Purpose: Previous / next links of one of the two "adapted from this work" lists, mirroring
	//         f_sourceworkpaginationhtml but pointing back to the movie / serie sheet.
	//Returns: The HTML of the navigation, or "" when the list has a single page
	$lngtotalpages=max(1, (int)($arrpageinfo["total_pages"] ?? 1));
	if ($lngtotalpages<=1) {
		return "";
	}
	$lngpage=max(1, (int)($arrpageinfo["page"] ?? 1));
	$stranchor=($strworktype==="serie"?"adapted-series":"adapted-movies");
	$strhtml="<nav class=\"work-property-pagination\">";
	$strhtml.="<p>".f_htmlspecialchars(sprintf(get_movie_string('lib_provider_pagination_summary'),
		(int)$arrpageinfo["first_row"], (int)$arrpageinfo["last_row"], (int)$arrpageinfo["total_rows"], $lngpage, $lngtotalpages))."</p><p>";
	foreach (array(-1, 1) as $lngstep) {
		$lngtarget=$lngpage+$lngstep;
		if ($lngtarget<1 || $lngtarget>$lngtotalpages) {
			continue;
		}
		$strurl=f_adaptationspaginationurl($strhostpage, $lnghostid,
			($strworktype==="movie"?$lngtarget:(int)$lngmoviepage),
			($strworktype==="serie"?$lngtarget:(int)$lngseriepage),
			$stranchor);
		if ($lngstep>0 && $lngpage>1) {
			$strhtml.=" · ";
		}
		$strhtml.="<a rel=\"".($lngstep<0?"prev":"next")."\" href=\"".f_htmlattr($strurl)."\">".f_htmlspecialchars(get_movie_string($lngstep<0?'lib_provider_pagination_previous':'lib_provider_pagination_next'))."</a>";
	}
	$strhtml.="</p></nav>";
	return $strhtml;
}

function f_worksadaptedfromsection($strhostpage, $lnghostid) {
	//Purpose: On a movie / serie sheet whose work is itself a source work (an ID_MOVIE / ID_SERIE
	//         set on a T_WC_T2S_SOURCE_WORK row), lists the works adapted from it, films then series,
	//         each paged on its own (adapt_movie_page / adapt_serie_page). This replaces, for a film
	//         or series source work, the sourcework.php sheet, now 301-redirected to this sheet.
	//Returns: nothing; echoes the two sections, or nothing when the work is not a source work or has
	//         no adaptation on record. The cards go through f_movielistdisplay / f_serielistdisplay,
	//         hooks included, as on every list sheet.
	$strhostpage=($strhostpage==="serie"?"serie":"movie");
	$lnghostid=f_stringtolong($lnghostid);
	if ($lnghostid<=0) {
		return;
	}

	// The source work(s) for which THIS film / series is the original. One in general, but the model
	// does not forbid several, so we aggregate the adaptations of all (DISTINCT on the target).
	$strhostcol=($strhostpage==="serie"?"ID_SERIE":"ID_MOVIE");
	$arrsourceworkids=array();
	$strsqlsw="SELECT ID_SOURCE_WORK FROM T_WC_T2S_SOURCE_WORK WHERE ".$strhostcol." = ".f_longtosql($lnghostid)." AND COALESCE(DELETED,0) = 0";
	$resultsw=f_sqlselect($strsqlsw);
	if ($resultsw) {
		while ($rstempsw=f_sqlresultfetcharray($resultsw)) {
			$lngswid=f_stringtolong($rstempsw["ID_SOURCE_WORK"]);
			if ($lngswid>0) {
				$arrsourceworkids[]=$lngswid;
			}
		}
		f_sqlfreeresult($resultsw);
	}
	if (count($arrsourceworkids)==0) {
		return;
	}
	$strswidlist=implode(",", array_map('intval', $arrsourceworkids));

	// A work is never an adaptation of itself : exclude the host from its own side's list.
	$strexcludemovie=($strhostpage==="movie"?" AND M.ID_MOVIE <> ".f_longtosql($lnghostid):"");
	$strexcludeserie=($strhostpage==="serie"?" AND S.ID_SERIE <> ".f_longtosql($lnghostid):"");

	$lngmoviecount=0;
	$strsqlmoviescount="SELECT COUNT(DISTINCT M.ID_MOVIE) AS COMPTE FROM T_WC_T2S_MOVIE M JOIN T_WC_T2S_MOVIE_SOURCE_WORK J ON M.ID_MOVIE = J.ID_MOVIE WHERE J.ID_SOURCE_WORK IN (".$strswidlist.") AND COALESCE(J.DELETED,0) = 0".$strexcludemovie;
	$resultmoviescount=f_sqlselect($strsqlmoviescount);
	if ($resultmoviescount) {
		if ($rtempcnt=f_sqlresultfetcharray($resultmoviescount)) {
			$lngmoviecount=f_stringtolong($rtempcnt["COMPTE"]);
		}
		f_sqlfreeresult($resultmoviescount);
	}

	$lngseriecount=0;
	$strsqlseriescount="SELECT COUNT(DISTINCT S.ID_SERIE) AS COMPTE FROM T_WC_T2S_SERIE S JOIN T_WC_T2S_SERIE_SOURCE_WORK J ON S.ID_SERIE = J.ID_SERIE WHERE J.ID_SOURCE_WORK IN (".$strswidlist.") AND COALESCE(J.DELETED,0) = 0".$strexcludeserie;
	$resultseriescount=f_sqlselect($strsqlseriescount);
	if ($resultseriescount) {
		if ($rtempcnt=f_sqlresultfetcharray($resultseriescount)) {
			$lngseriecount=f_stringtolong($rtempcnt["COMPTE"]);
		}
		f_sqlfreeresult($resultseriescount);
	}

	if ($lngmoviecount<=0 && $lngseriecount<=0) {
		return;
	}

	$lngrowsperpage=50;
	$lngmoviepage=max(1, f_stringtolong(f_request2("adapt_movie_page")));
	$lngseriepage=max(1, f_stringtolong(f_request2("adapt_serie_page")));
	$arrmoviepageinfo=f_workpropertypaginationinfo($lngmoviepage, $lngrowsperpage, $lngmoviecount);
	$lngmoviepage=$arrmoviepageinfo["page"];
	$arrseriepageinfo=f_workpropertypaginationinfo($lngseriepage, $lngrowsperpage, $lngseriecount);
	$lngseriepage=$arrseriepageinfo["page"];

	if ($lngmoviecount>0) {
		echo "<h2 id=\"adapted-movies\">".get_movie_string('lib_sourcework_movies').": ".$lngmoviecount."</h2>";
		echo "<table>";
		$strsqlmovies="SELECT M.ID_MOVIE, M.MOVIE_TITLE, M.DAT_RELEASE, M.ID_IMDB, M.IMDB_RATING, M.IMDB_VOTES, M.POSTER_PATH ";
		$strsqlmovies.="FROM T_WC_T2S_MOVIE M ";
		$strsqlmovies.="JOIN T_WC_T2S_MOVIE_SOURCE_WORK J ON M.ID_MOVIE = J.ID_MOVIE ";
		$strsqlmovies.="WHERE J.ID_SOURCE_WORK IN (".$strswidlist.") AND COALESCE(J.DELETED,0) = 0".$strexcludemovie." ";
		$strsqlmovies.="GROUP BY M.ID_MOVIE, M.MOVIE_TITLE, M.DAT_RELEASE, M.ID_IMDB, M.IMDB_RATING, M.IMDB_VOTES, M.POSTER_PATH ";
		$strsqlmovies.="ORDER BY MIN(J.DISPLAY_ORDER), M.DAT_RELEASE DESC, M.ID_MOVIE ";
		$strsqlmovies.="LIMIT ".(int)$arrmoviepageinfo["rows_per_page"]." OFFSET ".(int)$arrmoviepageinfo["offset"];
		$resultmovies=f_sqlselect($strsqlmovies);
		if ($resultmovies) {
			while ($rstempmovie=f_sqlresultfetcharray($resultmovies)) {
				f_movielistdisplay($rstempmovie['ID_MOVIE'],$rstempmovie['MOVIE_TITLE'],$rstempmovie['MOVIE_TITLE'],"", $rstempmovie['DAT_RELEASE'],$rstempmovie['ID_IMDB'],$rstempmovie['IMDB_RATING'],"", $rstempmovie['POSTER_PATH'],"",3,0,"float",$rstempmovie['IMDB_VOTES']);
			}
			f_sqlfreeresult($resultmovies);
		}
		echo "</table>";
		echo f_adaptationspaginationhtml($strhostpage, $lnghostid, "movie", $arrmoviepageinfo, $lngmoviepage, $lngseriepage);
	}

	if ($lngseriecount>0) {
		echo "<h2 id=\"adapted-series\">".get_movie_string('lib_sourcework_series').": ".$lngseriecount."</h2>";
		echo "<table>";
		$strsqlseries="SELECT S.ID_SERIE, S.SERIE_TITLE, S.DAT_FIRST_AIR, S.DAT_LAST_AIR, S.ID_IMDB, S.IMDB_RATING, S.IMDB_VOTES, S.POSTER_PATH ";
		$strsqlseries.="FROM T_WC_T2S_SERIE S ";
		$strsqlseries.="JOIN T_WC_T2S_SERIE_SOURCE_WORK J ON S.ID_SERIE = J.ID_SERIE ";
		$strsqlseries.="WHERE J.ID_SOURCE_WORK IN (".$strswidlist.") AND COALESCE(J.DELETED,0) = 0".$strexcludeserie." ";
		$strsqlseries.="GROUP BY S.ID_SERIE, S.SERIE_TITLE, S.DAT_FIRST_AIR, S.DAT_LAST_AIR, S.ID_IMDB, S.IMDB_RATING, S.IMDB_VOTES, S.POSTER_PATH ";
		$strsqlseries.="ORDER BY MIN(J.DISPLAY_ORDER), S.DAT_FIRST_AIR DESC, S.ID_SERIE ";
		$strsqlseries.="LIMIT ".(int)$arrseriepageinfo["rows_per_page"]." OFFSET ".(int)$arrseriepageinfo["offset"];
		$resultseries=f_sqlselect($strsqlseries);
		if ($resultseries) {
			while ($rstempserierow=f_sqlresultfetcharray($resultseries)) {
				f_serielistdisplay($rstempserierow['ID_SERIE'],$rstempserierow['SERIE_TITLE'],$rstempserierow['SERIE_TITLE'],"", $rstempserierow['DAT_FIRST_AIR'],$rstempserierow['DAT_LAST_AIR'],$rstempserierow['ID_IMDB'],$rstempserierow['IMDB_RATING'],"", $rstempserierow['POSTER_PATH'],"",3,0,"float",$rstempserierow['IMDB_VOTES']);
			}
			f_sqlfreeresult($resultseries);
		}
		echo "</table>";
		echo f_adaptationspaginationhtml($strhostpage, $lnghostid, "serie", $arrseriepageinfo, $lngmoviepage, $lngseriepage);
	}
}

function f_workpropertyproviderregionshtml($arrrows, $arrcountrylabels,
		$arrcontentlabels, $strprioritylabel) {
	$arrgroups=array();
	foreach ((array)$arrrows as $arrrow) {
		$strcontenttype=trim((string)($arrrow["CONTENT_TYPE"] ?? ""));
		if (!isset($arrgroups[$strcontenttype])) {
			$arrgroups[$strcontenttype]=array();
		}
		$arrgroups[$strcontenttype][]=$arrrow;
	}
	$strhtml="";
	foreach ($arrgroups as $strcontenttype=>$arrregions) {
		$strcontentlabel=$arrcontentlabels[$strcontenttype] ?? $strcontenttype;
		$strhtml.="<details><summary><strong>".f_workpropertyescape($strcontentlabel).
			" (".count($arrregions).")</strong></summary><ul>";
		foreach ($arrregions as $arrregion) {
			$strcountrycode=f_workpropertycountrycode($arrregion["COUNTRY_CODE"] ?? "");
			$strcountrylabel=$arrcountrylabels[$strcountrycode] ?? $strcountrycode;
			$strhtml.="<li>".f_workpropertyescape($strcountrylabel)." (".
				f_workpropertyescape($strcountrycode).")";
			if (isset($arrregion["PROVIDER_DISPLAY_PRIORITY"]) && $arrregion["PROVIDER_DISPLAY_PRIORITY"]!=="") {
				$strhtml.=" · ".f_workpropertyescape($strprioritylabel).": ".
					(int)$arrregion["PROVIDER_DISPLAY_PRIORITY"];
			}
			$strhtml.="</li>";
		}
		$strhtml.="</ul></details>";
	}
	return $strhtml;
}

function f_workpropertyreleasedescriptors($strjson) {
	if (!is_string($strjson) || trim($strjson)=="") {
		return array();
	}
	$arrdecoded=json_decode($strjson, true);
	if (!is_array($arrdecoded)) {
		return array();
	}
	$arrdescriptors=array();
	foreach ($arrdecoded as $strdescriptor) {
		if (is_scalar($strdescriptor) && trim((string)$strdescriptor)!="") {
			$arrdescriptors[]=trim((string)$strdescriptor);
		}
	}
	return array_values(array_unique($arrdescriptors));
}

function f_workpropertyreleasedisplaydate($arrrow) {
	$strdate=trim((string)($arrrow["TIM_RELEASE"] ?? ""));
	if ($strdate=="") {
		$strdate=trim((string)($arrrow["RELEASE_DATE_RAW"] ?? ""));
	}
	if (strlen($strdate)>=10 && preg_match('/^\d{4}-\d{2}-\d{2}/', $strdate)) {
		return substr($strdate, 0, 10);
	}
	return $strdate;
}

function f_workpropertyreleasecountrygroups($arrrows, $strpreferredcountry="") {
	$arrgroups=array();
	foreach ((array)$arrrows as $arrrow) {
		$strcountrycode=f_workpropertycountrycode($arrrow["COUNTRY_CODE"] ?? "");
		if ($strcountrycode=="") {
			continue;
		}
		if (!isset($arrgroups[$strcountrycode])) {
			$arrgroups[$strcountrycode]=array();
		}
		$arrgroups[$strcountrycode][]=$arrrow;
	}
	ksort($arrgroups, SORT_STRING);
	$strpreferredcountry=f_workpropertycountrycode($strpreferredcountry);
	if ($strpreferredcountry!="" && isset($arrgroups[$strpreferredcountry])) {
		$arrpreferred=$arrgroups[$strpreferredcountry];
		unset($arrgroups[$strpreferredcountry]);
		$arrgroups=array($strpreferredcountry=>$arrpreferred)+$arrgroups;
	}
	return $arrgroups;
}

function f_workpropertyreleasedateshtml($arrrows, $arrcountrylabels, $arrtypelabels,
		$strpreferredcountry, $arrlabels) {
	$arrgroups=f_workpropertyreleasecountrygroups($arrrows, $strpreferredcountry);
	$strhtml="<div class=\"work-property-release-countries\">";
	$intcountryindex=0;
	foreach ($arrgroups as $strcountrycode=>$arrevents) {
		$strcountrylabel=$arrcountrylabels[$strcountrycode] ?? $strcountrycode;
		$intopen=($intcountryindex==0);
		$strhtml.="<details class=\"work-property-release-country\"".($intopen?" open":"").">";
		$strhtml.="<summary><strong>".f_workpropertyescape($strcountrylabel).
			" (".f_workpropertyescape($strcountrycode).")</strong>";
		if ($strcountrycode===f_workpropertycountrycode($strpreferredcountry)) {
			$strhtml.=" · ".f_workpropertyescape($arrlabels["preferred"] ?? "");
		}
		$strhtml.="</summary>";

		$arrbytype=array();
		foreach ($arrevents as $arrevent) {
			$lngtype=(int)($arrevent["RELEASE_TYPE"] ?? 0);
			if (!isset($arrbytype[$lngtype])) {
				$arrbytype[$lngtype]=array();
			}
			$arrbytype[$lngtype][]=$arrevent;
		}
		ksort($arrbytype, SORT_NUMERIC);
		foreach ($arrbytype as $lngtype=>$arrtypeevents) {
			$strtypelabel=$arrtypelabels[$lngtype] ?? ($arrlabels["unknown_type"] ?? "Type")." ".$lngtype;
			$strhtml.="<h3>".f_workpropertyescape($strtypelabel)."</h3><ul>";
			foreach ($arrtypeevents as $arrevent) {
				$strdisplaydate=f_workpropertyreleasedisplaydate($arrevent);
				$strrawdate=trim((string)($arrevent["RELEASE_DATE_RAW"] ?? ""));
				$strhtml.="<li>";
				if ($strdisplaydate!="") {
					$strhtml.="<time".($strrawdate!=""?" datetime=\"".f_workpropertyescape($strrawdate)."\"":"").">".
						f_workpropertyescape($strdisplaydate)."</time>";
				} else {
					$strhtml.=f_workpropertyescape($arrlabels["no_date"] ?? "Date unavailable");
				}
				$arrmetadata=array();
				$strcertification=trim((string)($arrevent["CERTIFICATION"] ?? ""));
				if ($strcertification!="") {
					$arrmetadata[]=($arrlabels["certification"] ?? "Certification").": ".$strcertification;
				}
				$strlanguage=trim((string)($arrevent["LANG"] ?? ""));
				if ($strlanguage!="") {
					$arrmetadata[]=($arrlabels["language"] ?? "Language").": ".$strlanguage;
				}
				$arrdescriptors=f_workpropertyreleasedescriptors($arrevent["DESCRIPTORS_JSON"] ?? "");
				if (count($arrdescriptors)>0) {
					$arrmetadata[]=($arrlabels["descriptors"] ?? "Descriptors").": ".implode(", ", $arrdescriptors);
				}
				if (count($arrmetadata)>0) {
					$strhtml.=" · ".f_workpropertyescape(implode(" · ", $arrmetadata));
				}
				$strnote=trim((string)($arrevent["NOTE"] ?? ""));
				if ($strnote!="") {
					$strhtml.="<br><span><strong>".f_workpropertyescape($arrlabels["note"] ?? "Note").
						":</strong> ".nl2br(f_workpropertyescape($strnote))."</span>";
				}
				$strhtml.="</li>";
			}
			$strhtml.="</ul>";
		}
		$strhtml.="</details>";
		$intcountryindex++;
	}
	$strhtml.="</div>";
	return $strhtml;
}

?>
