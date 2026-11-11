<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
include_once(__DIR__."/work-properties-format.inc.php");

if (!function_exists('f_locationpaginationhtml')) {
	function f_locationpaginationhtml($lnglocationid, $strlistkey, $arrpageinfo, $arrlocationpages, $strscope="contained") {
		//Purpose: Previous / next links of one of the six lists of the location sheet.
		//         The lists page independently, so each link carries the current page of the five others,
		//         and the scope when it is not the default.
		//Returns: The HTML of the navigation, or "" when the list has a single page
		$lngtotalpages=max(1, (int)($arrpageinfo["total_pages"] ?? 1));
		if ($lngtotalpages<=1) {
			return "";
		}
		$lngpage=max(1, (int)($arrpageinfo["page"] ?? 1));
		$strhtml="<nav class=\"work-property-pagination\">";
		$strhtml.="<p>".f_htmlspecialchars(sprintf(get_movie_string('lib_provider_pagination_summary'),
			(int)$arrpageinfo["first_row"], (int)$arrpageinfo["last_row"], (int)$arrpageinfo["total_rows"], $lngpage, $lngtotalpages))."</p><p>";
		foreach (array(-1, 1) as $lngstep) {
			$lngtarget=$lngpage+$lngstep;
			if ($lngtarget<1 || $lngtarget>$lngtotalpages) {
				continue;
			}
			$strurl="location.php?locationid=".(int)$lnglocationid.($strscope=="direct" ? "&scope=direct" : "");
			foreach ($arrlocationpages as $strkey => $lngkeypage) {
				$lngkeypage=($strkey===$strlistkey ? $lngtarget : max(1, (int)$lngkeypage));
				if ($lngkeypage>1) {
					$strurl.="&".$strkey."_page=".$lngkeypage;
				}
			}
			$strurl.="#location-".str_replace("_", "-", $strlistkey);
			if ($lngstep>0 && $lngpage>1) {
				$strhtml.=" · ";
			}
			$strhtml.="<a rel=\"".($lngstep<0?"prev":"next")."\" href=\"".f_htmlattr($strurl)."\">".f_htmlspecialchars(get_movie_string($lngstep<0?'lib_provider_pagination_previous':'lib_provider_pagination_next'))."</a>";
		}
		$strhtml.="</p></nav>";
		return $strhtml;
	}
}

if (!function_exists('f_locationlinksource')) {
	function f_locationlinksource($strjunction, $strworkcol, $lnglocationid, $strrole, $strscope) {
		//Purpose: The derived table behind one list of the location sheet: one row per
		//         work for the role, with VIA_ID_LOCATION, the place the work is actually linked to.
		//         contained: the links of every place whose ancestor is this one in
		//         T_WC_T2S_LOCATION_ANCESTOR (the place itself is its own ancestor at DEPTH 0), keeping per
		//         work the most specific link (DEPTH 0 first, then the lowest ID_LOCATION), as
		//         /locations/{id} of the API does. direct: the place alone.
		//Returns: SQL text for FROM ( ... ) X, with ID of the work, VIA_ID_LOCATION, DISPLAY_ORDER, RN
		$strwhererole="J.LOCATION_ROLE = ".f_stringtosql($strrole)." AND COALESCE(J.DELETED,0) = 0";
		if ($strscope=="direct") {
			return "SELECT J.".$strworkcol.", J.ID_LOCATION AS VIA_ID_LOCATION, J.DISPLAY_ORDER, 1 AS RN FROM ".$strjunction." J WHERE J.ID_LOCATION = ".f_longtosql($lnglocationid)." AND ".$strwhererole;
		}
		return "SELECT J.".$strworkcol.", J.ID_LOCATION AS VIA_ID_LOCATION, J.DISPLAY_ORDER, ROW_NUMBER() OVER (PARTITION BY J.".$strworkcol." ORDER BY A.DEPTH ASC, J.ID_LOCATION ASC) AS RN FROM T_WC_T2S_LOCATION_ANCESTOR A JOIN ".$strjunction." J ON J.ID_LOCATION = A.ID_LOCATION AND ".$strwhererole." WHERE A.ID_ANCESTOR = ".f_longtosql($lnglocationid);
	}
}

if (!function_exists('f_locationviahtml')) {
	function f_locationviahtml($lnglocationid, $lngviaid, $strvianame) {
		//Purpose: The small "via <place>" of a row linked to a place inside the sheet's place
		//Returns: The HTML, or "" when the work is linked to the sheet's place itself
		if ((int)$lngviaid<=0 || (int)$lngviaid==(int)$lnglocationid || trim((string)$strvianame)=="") {
			return "";
		}
		$strlink="<a href=\"location.php?locationid=".(int)$lngviaid."\">".f_htmlspecialchars($strvianame)."</a>";
		return "<small>".sprintf(f_htmlspecialchars(get_movie_string('lib_location_via')), $strlink)."</small>";
	}
}

if (!function_exists('f_locationtypelabel')) {
	function f_locationtypelabel($strvalue) {
		//Purpose: Translates LOCATION_TYPE ('city', 'country', ...) into the UI language
		//Returns: The label, or the raw value when process 72 grows a type the front does not know yet
		$strvalue=trim((string)$strvalue);
		if ($strvalue=="") {
			return "";
		}
		$strkey="lib_location_type_".preg_replace('/[^a-z0-9_]/', '', strtolower($strvalue));
		$strlabel=get_movie_string($strkey);
		if ($strlabel===$strkey) {
			return str_replace("_", " ", $strvalue);
		}
		return $strlabel;
	}
}

if ($intdbavailable) {
	$lnglocationid=f_stringtolong(f_request2("locationid"));
	$intfound=false;

	if ($lnglocationid>0) {
		// T_WC_T2S_LOCATION garde en DELETED = 1 les lieux sortis du perimetre, pour que leur
		// ID_LOCATION ne soit jamais reattribue : un lieu retire ne doit donc pas s'afficher.
		$strsql="SELECT * FROM T_WC_T2S_LOCATION WHERE ID_LOCATION = ".f_longtosql($lnglocationid)." AND COALESCE(DELETED,0) = 0";
		$result=f_sqlselect($strsql);
		if ($result) {
			if ($rstemp=f_sqlresultfetcharray($result)) {
				$intfound=true;
				$strlocationname=isset($rstemp['LOCATION_NAME']) ? $rstemp['LOCATION_NAME'] : '';
				$strlocationsource=isset($rstemp['LOCATION_SOURCE']) ? $rstemp['LOCATION_SOURCE'] : '';
				$strlocationtype=isset($rstemp['LOCATION_TYPE']) ? $rstemp['LOCATION_TYPE'] : '';
				$strlocationwikidataid=isset($rstemp['ID_WIKIDATA']) ? trim($rstemp['ID_WIKIDATA']) : '';
				$strlocationposterpath=isset($rstemp['POSTER_PATH']) ? $rstemp['POSTER_PATH'] : '';
				// L'image principale de l'article Wikipedia dans la langue de l'interface, puis
				// l'anglaise, puis l'image Wikidata, comme la fiche oeuvre source.
				$strlocationwikipediapath="";
				if ($struilang!="en") {
					$strlocationwikipediapath=trim((string)($rstemp['WIKIPEDIA_MAIN_IMAGE_URL_FR'] ?? ''));
				}
				if ($strlocationwikipediapath=="") {
					$strlocationwikipediapath=trim((string)($rstemp['WIKIPEDIA_MAIN_IMAGE_URL'] ?? ''));
				}
				if ($strlocationwikipediapath=="") {
					$strlocationwikipediapath=trim((string)($rstemp['WIKIPEDIA_IMAGE_PATH'] ?? ''));
				}
				$strlocationoverview=isset($rstemp['OVERVIEW']) ? $rstemp['OVERVIEW'] : '';
			}
			f_sqlfreeresult($result);
		}

		if ($intfound) {
			f_headerdisplay($strlocationname,"",f_socialwikidataentity($strlocationname,$strlocationoverview,$strlocationwikipediapath,$strlocationposterpath));
			echo "<h1>".f_htmlspecialchars($strlocationname)."</h1>";

			if ($strlocationwikipediapath!="") {
				echo "<p><img src=\"".f_htmlattr($strlocationwikipediapath)."\" alt=\"".f_htmlattr($strlocationname)."\" title=\"".f_htmlattr($strlocationname)."\" style=\"background-color: white; max-width: 100%; height: auto;\" /></p>";
			}
			if ($strlocationposterpath!="") {
				echo "<p><img src=\"".f_htmlattr(f_getmovieposterpicture($strlocationposterpath))."\" alt=\"".f_htmlattr($strlocationname)."\" title=\"".f_htmlattr($strlocationname)."\" style=\"background-color: white;\" /></p>";
			}

			if ($strlocationwikidataid!="") {
				echo "<p><strong>".get_movie_string('lib_movie_id_wikidata').":</strong> <a href=\"".f_htmlattr("https://www.wikidata.org/wiki/".$strlocationwikidataid)."\" target=\"_blank\" rel=\"noreferrer\">".f_htmlspecialchars($strlocationwikidataid)."</a> <a href=\"".f_htmlattr("https://www.wikidata.org/wiki/".$strlocationwikidataid)."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/Wikidata-logo.svg.png\" width=\"28\" height=\"16\" alt=\"Wikidata\" title=\"Wikidata\"></a></p>";
			}

			if ($strlocationsource!="") {
				echo "<p><strong>Source:</strong> ".f_htmlspecialchars($strlocationsource)."</p>";
			}
			if ($strlocationtype!="") {
				echo "<p><strong>".get_movie_string('lib_sourcework_type').":</strong> ".f_htmlspecialchars(f_locationtypelabel($strlocationtype))."</p>";
			}
			if ($strlocationoverview!="") {
				echo "<h2>".get_movie_string('lib_collection_overview')."</h2>";
				echo "<p>".nl2br(f_htmlspecialchars($strlocationoverview))."</p>";
			}
			if ($strlocationwikidataid!="") {
				// Meme lecture V2 que movement.php et technical.php :
				// la classe se lit en V2, avec repli V1 dans f_getwikidatalabel.
				$strwditeminstanceofid=f_wikidataiteminstanceof_v2($strlocationwikidataid);
				$strwditeminstanceoflabel=f_getwikidatalabel($strwditeminstanceofid,$struilang,$arrwdsourcelabel);
				// Diagnostic de la migration, en mode prive : visible sous un prefixe prive, invisible
				// sur le site public, et dans les DEUX langues puisqu un prefixe prive peut forcer une langue.
				//Hook entity.wikidata: service blocks of the plugins above the Wikidata section
				echo f_pluginhtml("entity.wikidata",array("page"=>"location","wikidataids"=>array($strlocationwikidataid,$strwditeminstanceofid),"lang"=>$struilang,"langalt"=>$struilangalt));
				echo "<h2>".get_movie_string('lib_wikidata_section')."</h2>";
				$strpropertyurl="property.php?propertyid=P31";
				echo "<p><strong><a href=\"".$strpropertyurl."\">".get_movie_string('lib_wikidata_type')."</a>:</strong> ".$strwditeminstanceoflabel."</p>";
				$strwikidataeverything=f_wikidataallpropertiesv2($struilang,$strlocationwikidataid,", ");
				echo $strwikidataeverything;

				echo "<h2>".get_movie_string('lib_wikipedia_section')."</h2>";
				$strwikipediacontent=f_wikipediallsections($struilang,$strlocationwikidataid,$strsep=", ");
				echo $strwikipediacontent;
			}

			// Un lieu porte deux rattachements distincts, LOCATION_ROLE valant 'filming' (on y a
			// tourne) ou 'narrative' (l action s y deroule). Les melanger ferait passer un decor
			// de tournage pour un lieu de recit : une section par role, films puis series.
			$arrlocationroles=array(
				'filming' => array('movies' => 'lib_location_movies_filming', 'series' => 'lib_location_series_filming', 'episodes' => 'lib_location_episodes_filming'),
				'narrative' => array('movies' => 'lib_location_movies_narrative', 'series' => 'lib_location_series_narrative', 'episodes' => 'lib_location_episodes_narrative'),
			);

			// Six listes paginees a 50 chacune de son cote : Paris compte des milliers
			// de films. Les six numeros de page voyagent ensemble dans chaque lien. Les oeuvres
			// supprimees (DELETED) sont ecartees des listes ET des comptes, comme dans
			// /locations/{id} de l'API.
			$lnglocationrowsperpage=50;
			// Portee : par defaut, les listes comptent aussi les lieux contenus
			// (la France liste les films tournes a Paris), par T_WC_T2S_LOCATION_ANCESTOR ;
			// scope=direct garde le lieu seul. Le lien de bascule ne s'affiche que si le lieu en
			// contient d'autres : sur la Lune, les deux portees sont identiques.
			$strlocationscope=(strtolower(trim((string)f_request2("scope")))=="direct" ? "direct" : "contained");
			$intlocationhaschildren=false;
			$resultchildren=f_sqlselect("SELECT 1 AS HAS_CHILD FROM T_WC_T2S_LOCATION_ANCESTOR WHERE ID_ANCESTOR = ".f_longtosql($lnglocationid)." AND DEPTH > 0 LIMIT 1");
			if ($resultchildren) {
				if (f_sqlresultfetcharray($resultchildren)) {
					$intlocationhaschildren=true;
				}
				f_sqlfreeresult($resultchildren);
			}
			if ($intlocationhaschildren) {
				if ($strlocationscope=="direct") {
					$strscopeurl="location.php?locationid=".(int)$lnglocationid;
					$strscopelabel=sprintf(get_movie_string('lib_location_scope_contained'), $strlocationname);
				} else {
					$strscopeurl="location.php?locationid=".(int)$lnglocationid."&scope=direct";
					$strscopelabel=sprintf(get_movie_string('lib_location_scope_direct'), $strlocationname);
				}
				echo "<p><a href=\"".f_htmlattr($strscopeurl)."\">".f_htmlspecialchars($strscopelabel)."</a></p>";
			}
			$arrlocationpages=array();
			foreach (array_keys($arrlocationroles) as $strlocationrolekey) {
				foreach (array('movie','serie','episode') as $strlocationkind) {
					$strlocationlistkey=$strlocationrolekey."_".$strlocationkind;
					$arrlocationpages[$strlocationlistkey]=max(1, f_stringtolong(f_request2($strlocationlistkey."_page")));
				}
			}

			foreach ($arrlocationroles as $strlocationrole => $arrlocationrolelabels) {
				$lngmoviecount=0;
				$strsqlmoviescount="SELECT COUNT(DISTINCT X.ID_MOVIE) AS COMPTE FROM (".f_locationlinksource("T_WC_T2S_MOVIE_LOCATION", "ID_MOVIE", $lnglocationid, $strlocationrole, $strlocationscope).") X JOIN T_WC_T2S_MOVIE M ON M.ID_MOVIE = X.ID_MOVIE WHERE COALESCE(M.DELETED,0) = 0";
				$resultmoviescount=f_sqlselect($strsqlmoviescount);
				if ($resultmoviescount) {
					if ($rtempcnt=f_sqlresultfetcharray($resultmoviescount)) {
						$lngmoviecount=f_stringtolong($rtempcnt['COMPTE']);
					}
					f_sqlfreeresult($resultmoviescount);
				}
				$strlistkey=$strlocationrole."_movie";
				$arrmoviepageinfo=f_workpropertypaginationinfo($arrlocationpages[$strlistkey], $lnglocationrowsperpage, $lngmoviecount);
				$arrlocationpages[$strlistkey]=$arrmoviepageinfo["page"];
				echo "<h2 id=\"location-".$strlocationrole."-movie\">".get_movie_string($arrlocationrolelabels['movies']).": ".$lngmoviecount."</h2>";
				echo "<table>";
				$strsqlmovies="SELECT M.ID_MOVIE, M.MOVIE_TITLE, M.DAT_RELEASE, M.ID_IMDB, M.IMDB_RATING, M.IMDB_VOTES, M.POSTER_PATH, X.VIA_ID_LOCATION, VL.LOCATION_NAME AS VIA_LOCATION_NAME ";
				$strsqlmovies.="FROM (".f_locationlinksource("T_WC_T2S_MOVIE_LOCATION", "ID_MOVIE", $lnglocationid, $strlocationrole, $strlocationscope).") X ";
				$strsqlmovies.="JOIN T_WC_T2S_MOVIE M ON M.ID_MOVIE = X.ID_MOVIE ";
				$strsqlmovies.="JOIN T_WC_T2S_LOCATION VL ON VL.ID_LOCATION = X.VIA_ID_LOCATION ";
				$strsqlmovies.="WHERE X.RN = 1 AND COALESCE(M.DELETED,0) = 0 ";
				// DISPLAY_ORDER n'a de sens qu'entre les liens d'un meme lieu : en portee contenue, la date seule.
				$strsqlmovies.=($strlocationscope=="direct" ? "ORDER BY X.DISPLAY_ORDER, M.DAT_RELEASE DESC, M.ID_MOVIE " : "ORDER BY M.DAT_RELEASE DESC, M.ID_MOVIE ");
				$strsqlmovies.="LIMIT ".(int)$arrmoviepageinfo["rows_per_page"]." OFFSET ".(int)$arrmoviepageinfo["offset"];
				$resultmovies=f_sqlselect($strsqlmovies);
				if ($resultmovies) {
					while ($rstempmovie=f_sqlresultfetcharray($resultmovies)) {
						f_movielistdisplay($rstempmovie['ID_MOVIE'],$rstempmovie['MOVIE_TITLE'],$rstempmovie['MOVIE_TITLE'],"", $rstempmovie['DAT_RELEASE'],$rstempmovie['ID_IMDB'],$rstempmovie['IMDB_RATING'],f_locationviahtml($lnglocationid, $rstempmovie['VIA_ID_LOCATION'], $rstempmovie['VIA_LOCATION_NAME']), $rstempmovie['POSTER_PATH'],"",3,0,"float",$rstempmovie['IMDB_VOTES']);
					}
					f_sqlfreeresult($resultmovies);
				}
				echo "</table>";
				echo f_locationpaginationhtml($lnglocationid, $strlistkey, $arrmoviepageinfo, $arrlocationpages, $strlocationscope);

				$lngseriecount=0;
				$strsqlseriescount="SELECT COUNT(DISTINCT X.ID_SERIE) AS COMPTE FROM (".f_locationlinksource("T_WC_T2S_SERIE_LOCATION", "ID_SERIE", $lnglocationid, $strlocationrole, $strlocationscope).") X JOIN T_WC_T2S_SERIE S ON S.ID_SERIE = X.ID_SERIE WHERE COALESCE(S.DELETED,0) = 0";
				$resultseriescount=f_sqlselect($strsqlseriescount);
				if ($resultseriescount) {
					if ($rtempcnt=f_sqlresultfetcharray($resultseriescount)) {
						$lngseriecount=f_stringtolong($rtempcnt['COMPTE']);
					}
					f_sqlfreeresult($resultseriescount);
				}
				$strlistkey=$strlocationrole."_serie";
				$arrseriepageinfo=f_workpropertypaginationinfo($arrlocationpages[$strlistkey], $lnglocationrowsperpage, $lngseriecount);
				$arrlocationpages[$strlistkey]=$arrseriepageinfo["page"];
				echo "<h2 id=\"location-".$strlocationrole."-serie\">".get_movie_string($arrlocationrolelabels['series']).": ".$lngseriecount."</h2>";
				echo "<table>";
				$strsqlseries="SELECT S.ID_SERIE, S.SERIE_TITLE, S.DAT_FIRST_AIR, S.DAT_LAST_AIR, S.ID_IMDB, S.IMDB_RATING, S.IMDB_VOTES, S.POSTER_PATH, X.VIA_ID_LOCATION, VL.LOCATION_NAME AS VIA_LOCATION_NAME ";
				$strsqlseries.="FROM (".f_locationlinksource("T_WC_T2S_SERIE_LOCATION", "ID_SERIE", $lnglocationid, $strlocationrole, $strlocationscope).") X ";
				$strsqlseries.="JOIN T_WC_T2S_SERIE S ON S.ID_SERIE = X.ID_SERIE ";
				$strsqlseries.="JOIN T_WC_T2S_LOCATION VL ON VL.ID_LOCATION = X.VIA_ID_LOCATION ";
				$strsqlseries.="WHERE X.RN = 1 AND COALESCE(S.DELETED,0) = 0 ";
				$strsqlseries.=($strlocationscope=="direct" ? "ORDER BY X.DISPLAY_ORDER, S.DAT_FIRST_AIR DESC, S.ID_SERIE " : "ORDER BY S.DAT_FIRST_AIR DESC, S.ID_SERIE ");
				$strsqlseries.="LIMIT ".(int)$arrseriepageinfo["rows_per_page"]." OFFSET ".(int)$arrseriepageinfo["offset"];
				$resultseries=f_sqlselect($strsqlseries);
				if ($resultseries) {
					while ($rstempserierow=f_sqlresultfetcharray($resultseries)) {
						f_serielistdisplay($rstempserierow['ID_SERIE'],$rstempserierow['SERIE_TITLE'],$rstempserierow['SERIE_TITLE'],"", $rstempserierow['DAT_FIRST_AIR'],$rstempserierow['DAT_LAST_AIR'],$rstempserierow['ID_IMDB'],$rstempserierow['IMDB_RATING'],f_locationviahtml($lnglocationid, $rstempserierow['VIA_ID_LOCATION'], $rstempserierow['VIA_LOCATION_NAME']), $rstempserierow['POSTER_PATH'],"",3,0,"float",$rstempserierow['IMDB_VOTES']);
					}
					f_sqlfreeresult($resultseries);
				}
				echo "</table>";
				echo f_locationpaginationhtml($lnglocationid, $strlistkey, $arrseriepageinfo, $arrlocationpages, $strlocationscope);

				// Les episodes, T_WC_T2S_EPISODE_LOCATION remplie depuis le 2026-10-18
				//. 440 lieux ne sont cites que par des episodes : sans ce
				// bloc, leur fiche restait vide. Section affichee seulement quand elle a des lignes,
				// parce que 761 lieux sur 12 978 en ont : un titre a zero sur tous les autres ne dirait
				// rien. Chaque ligne porte la serie, la saison et le numero, avec le lien vers l'episode.
				$lngepisodecount=0;
				$strsqlepisodescount="SELECT COUNT(DISTINCT X.ID_EPISODE) AS COMPTE FROM (".f_locationlinksource("T_WC_T2S_EPISODE_LOCATION", "ID_EPISODE", $lnglocationid, $strlocationrole, $strlocationscope).") X JOIN T_WC_T2S_EPISODE E ON E.ID_EPISODE = X.ID_EPISODE LEFT JOIN T_WC_T2S_SERIE S ON S.ID_SERIE = E.ID_SERIE WHERE COALESCE(E.DELETED,0) = 0 AND COALESCE(S.DELETED,0) = 0";
				$resultepisodescount=f_sqlselect($strsqlepisodescount);
				if ($resultepisodescount) {
					if ($rtempcnt=f_sqlresultfetcharray($resultepisodescount)) {
						$lngepisodecount=f_stringtolong($rtempcnt['COMPTE']);
					}
					f_sqlfreeresult($resultepisodescount);
				}
				if ($lngepisodecount>0) {
					$strlistkey=$strlocationrole."_episode";
					$arrepisodepageinfo=f_workpropertypaginationinfo($arrlocationpages[$strlistkey], $lnglocationrowsperpage, $lngepisodecount);
					$arrlocationpages[$strlistkey]=$arrepisodepageinfo["page"];
					echo "<h2 id=\"location-".$strlocationrole."-episode\">".get_movie_string($arrlocationrolelabels['episodes']).": ".$lngepisodecount."</h2>";
					$strsqlepisodes="SELECT E.ID_SERIE, E.SEASON_NUMBER, E.EPISODE_NUMBER, E.EPISODE_TITLE, E.AIR_YEAR, S.SERIE_TITLE, X.VIA_ID_LOCATION, VL.LOCATION_NAME AS VIA_LOCATION_NAME ";
					$strsqlepisodes.="FROM (".f_locationlinksource("T_WC_T2S_EPISODE_LOCATION", "ID_EPISODE", $lnglocationid, $strlocationrole, $strlocationscope).") X ";
					$strsqlepisodes.="JOIN T_WC_T2S_EPISODE E ON E.ID_EPISODE = X.ID_EPISODE ";
					$strsqlepisodes.="LEFT JOIN T_WC_T2S_SERIE S ON S.ID_SERIE = E.ID_SERIE ";
					$strsqlepisodes.="JOIN T_WC_T2S_LOCATION VL ON VL.ID_LOCATION = X.VIA_ID_LOCATION ";
					$strsqlepisodes.="WHERE X.RN = 1 AND COALESCE(E.DELETED,0) = 0 AND COALESCE(S.DELETED,0) = 0 ";
					$strsqlepisodes.="ORDER BY S.SERIE_TITLE, E.ID_SERIE, E.SEASON_NUMBER, E.EPISODE_NUMBER, E.ID_EPISODE ";
					$strsqlepisodes.="LIMIT ".(int)$arrepisodepageinfo["rows_per_page"]." OFFSET ".(int)$arrepisodepageinfo["offset"];
					$resultepisodes=f_sqlselect($strsqlepisodes);
					if ($resultepisodes) {
						echo "<ul>";
						while ($rstempepisode=f_sqlresultfetcharray($resultepisodes)) {
							$lngepserieid=f_stringtolong($rstempepisode['ID_SERIE']);
							$lngepseasonnumber=f_stringtolong($rstempepisode['SEASON_NUMBER']);
							$lngepepisodenumber=f_stringtolong($rstempepisode['EPISODE_NUMBER']);
							$strepserietitle=trim((string)$rstempepisode['SERIE_TITLE']);
							$strepepisodetitle=trim((string)$rstempepisode['EPISODE_TITLE']);
							$lngepairyear=f_stringtolong($rstempepisode['AIR_YEAR']);
							$strepisodecode=sprintf("S%02dE%02d",$lngepseasonnumber,$lngepepisodenumber);
							echo "<li>";
							if ($strepserietitle!="") {
								echo "<a href=\"serie.php?serieid=".$lngepserieid."\">".f_htmlspecialchars($strepserietitle)."</a> ";
							}
							echo "<a href=\"episode.php?serieid=".$lngepserieid."&season=".$lngepseasonnumber."&episode=".$lngepepisodenumber."\">".$strepisodecode.($strepepisodetitle!="" ? " ".f_htmlspecialchars($strepepisodetitle) : "")."</a>";
							if ($lngepairyear>0) {
								echo " (".$lngepairyear.")";
							}
							$strepvia=f_locationviahtml($lnglocationid, $rstempepisode['VIA_ID_LOCATION'], $rstempepisode['VIA_LOCATION_NAME']);
							if ($strepvia!="") {
								echo " ".$strepvia;
							}
							echo "</li>";
						}
						echo "</ul>";
						f_sqlfreeresult($resultepisodes);
					}
					echo f_locationpaginationhtml($lnglocationid, $strlistkey, $arrepisodepageinfo, $arrlocationpages, $strlocationscope);
				}
			}

			//Hook page.beforefooter: blocks of the plugins at the end of the page
			echo f_pluginhtml("page.beforefooter",array("page"=>"location"));
			f_footerdisplay();
		} else {
			f_headerdisplay("Location Not Found");
			echo "<h1>Location not found.</h1>";
		}
	} else {
		f_headerdisplay("Invalid ID");
		echo "<h1>Invalid location ID.</h1>";
	}
} else {
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}
?>
