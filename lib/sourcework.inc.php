<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
include_once(__DIR__."/work-properties-format.inc.php");

if (!function_exists('f_sourceworklabel')) {
	function f_sourceworklabel($strprefix, $strvalue) {
		//Purpose: Translates a closed-list value of SOURCE_WORK_TYPE or SOURCE_WORK_FORM
		//         ('literary', 'manga', ...) into the UI language
		//Returns: The label, or the raw value when the list grows a word the front does not know yet
		$strvalue=trim((string)$strvalue);
		if ($strvalue=="") {
			return "";
		}
		$strkey=$strprefix.preg_replace('/[^a-z0-9_]/', '', strtolower($strvalue));
		$strlabel=get_movie_string($strkey);
		if ($strlabel===$strkey) {
			return str_replace("_", " ", $strvalue);
		}
		return $strlabel;
	}
}

if (!function_exists('f_sourceworkpaginationhtml')) {
	function f_sourceworkpaginationhtml($lngsourceworkid, $strworktype, $arrpageinfo, $lngmoviepage, $lngseriepage) {
		//Purpose: Previous / next links of one of the two lists of the source-work sheet. The two
		//         lists page independently, so each link carries the other list's current page.
		//Returns: The HTML of the navigation, or "" when the list has a single page
		$lngtotalpages=max(1, (int)($arrpageinfo["total_pages"] ?? 1));
		if ($lngtotalpages<=1) {
			return "";
		}
		$lngpage=max(1, (int)($arrpageinfo["page"] ?? 1));
		$stranchor=($strworktype==="serie"?"sourcework-series":"sourcework-movies");
		$strhtml="<nav class=\"work-property-pagination\">";
		$strhtml.="<p>".f_htmlspecialchars(sprintf(get_movie_string('lib_provider_pagination_summary'),
			(int)$arrpageinfo["first_row"], (int)$arrpageinfo["last_row"], (int)$arrpageinfo["total_rows"], $lngpage, $lngtotalpages))."</p><p>";
		foreach (array(-1, 1) as $lngstep) {
			$lngtarget=$lngpage+$lngstep;
			if ($lngtarget<1 || $lngtarget>$lngtotalpages) {
				continue;
			}
			$strurl="sourcework.php?sourceworkid=".(int)$lngsourceworkid;
			$strurl.="&movie_page=".($strworktype==="movie"?$lngtarget:(int)$lngmoviepage);
			$strurl.="&serie_page=".($strworktype==="serie"?$lngtarget:(int)$lngseriepage);
			$strurl.="#".$stranchor;
			if ($lngstep>0 && $lngpage>1) {
				$strhtml.=" · ";
			}
			$strhtml.="<a rel=\"".($lngstep<0?"prev":"next")."\" href=\"".f_htmlattr($strurl)."\">".f_htmlspecialchars(get_movie_string($lngstep<0?'lib_provider_pagination_previous':'lib_provider_pagination_next'))."</a>";
		}
		$strhtml.="</p></nav>";
		return $strhtml;
	}
}

if ($intdbavailable) {
	$lngsourceworkid=f_stringtolong(f_request2("sourceworkid"));
	$intfound=false;

	if ($lngsourceworkid>0) {
		// T_WC_T2S_SOURCE_WORK garde en DELETED = 1 les oeuvres sorties du perimetre, pour que leur
		// ID_SOURCE_WORK ne soit jamais reattribue (meme regle que les lieux) : une oeuvre retiree
		// ne doit donc pas s'afficher.
		$strsql="SELECT * FROM T_WC_T2S_SOURCE_WORK WHERE ID_SOURCE_WORK = ".f_longtosql($lngsourceworkid)." AND COALESCE(DELETED,0) = 0";
		$result=f_sqlselect($strsql);
		if ($result) {
			if ($rstemp=f_sqlresultfetcharray($result)) {
				$intfound=true;
				$strsourceworknameen=trim((string)($rstemp['SOURCE_WORK_NAME'] ?? ''));
				$strsourceworknamefr=trim((string)($rstemp['SOURCE_WORK_NAME_FR'] ?? ''));
				// Le nom suit la langue de l'interface. SOURCE_WORK_NAME_FR retombe deja sur l'anglais
				// cote preprocess quand Wikidata n'a pas de libelle francais ; le repli ici couvre
				// le cas inverse.
				if ($struilang!="en" && $strsourceworknamefr!="") {
					$strsourceworkname=$strsourceworknamefr;
				} else {
					$strsourceworkname=($strsourceworknameen!="" ? $strsourceworknameen : $strsourceworknamefr);
				}
				$strsourceworktype=trim((string)($rstemp['SOURCE_WORK_TYPE'] ?? ''));
				$strsourceworkform=trim((string)($rstemp['SOURCE_WORK_FORM'] ?? ''));
				$lngsourceworkyear=f_stringtolong($rstemp['SOURCE_WORK_YEAR'] ?? 0);
				$lngsourceworkmovieid=f_stringtolong($rstemp['ID_MOVIE'] ?? 0);
				$lngsourceworkserieid=f_stringtolong($rstemp['ID_SERIE'] ?? 0);
				$strsourceworksource=trim((string)($rstemp['SOURCE_WORK_SOURCE'] ?? ''));
				$strsourceworkwikidataid=trim((string)($rstemp['ID_WIKIDATA'] ?? ''));
				$strsourceworkposterpath=trim((string)($rstemp['POSTER_PATH'] ?? ''));
				$strsourceworkoverview=(string)($rstemp['OVERVIEW'] ?? '');
				// L'image d'une oeuvre source vient de Wikipedia (process 71) : l'image principale
				// de l'article dans la langue de l'interface, puis l'anglaise, puis l'image Wikidata.
				$strsourceworkwikipediapath="";
				if ($struilang!="en") {
					$strsourceworkwikipediapath=trim((string)($rstemp['WIKIPEDIA_MAIN_IMAGE_URL_FR'] ?? ''));
				}
				if ($strsourceworkwikipediapath=="") {
					$strsourceworkwikipediapath=trim((string)($rstemp['WIKIPEDIA_MAIN_IMAGE_URL'] ?? ''));
				}
				if ($strsourceworkwikipediapath=="") {
					$strsourceworkwikipediapath=trim((string)($rstemp['WIKIPEDIA_IMAGE_PATH'] ?? ''));
				}
			}
			f_sqlfreeresult($result);
		}
		if ($intfound && $strsourceworkname=="") {
			$strsourceworkname=($strsourceworkwikidataid!="" ? $strsourceworkwikidataid : "#".$lngsourceworkid);
		}

		// Une oeuvre source qui EST un film ou une serie T2S a desormais sa fiche riche comme seule
		// URL vivante : on redirige en 301 (avant tout affichage), ce qui couvre aussi les liens de
		// movie.php, serie.php, text2sql-search.php et location.php sans les modifier. Les adaptations
		// s'affichent desormais sur la fiche film / serie (f_worksadaptedfromsection).
		if ($intfound && $lngsourceworkmovieid>0) {
			header("Location: movie.php?movieid=".$lngsourceworkmovieid, true, 301);
			exit;
		}
		if ($intfound && $lngsourceworkserieid>0) {
			header("Location: serie.php?serieid=".$lngsourceworkserieid, true, 301);
			exit;
		}

		if ($intfound) {
			// Quand l'oeuvre source est elle-meme un film ou une serie T2S (Scarface 1932 pour
			// Scarface 1983), elle a sa propre fiche : on y renvoie, et son affiche sert d'image
			// si Wikipedia n'en fournit pas.
			$strsourceworkmovielink="";
			if ($lngsourceworkmovieid>0) {
				$strsqlsrcmovie="SELECT ID_MOVIE, MOVIE_TITLE, MOVIE_TITLE_FR, DAT_RELEASE, POSTER_PATH FROM T_WC_T2S_MOVIE WHERE ID_MOVIE = ".f_longtosql($lngsourceworkmovieid);
				$resultsrcmovie=f_sqlselect($strsqlsrcmovie);
				if ($resultsrcmovie) {
					if ($rstempsrc=f_sqlresultfetcharray($resultsrcmovie)) {
						$strsrctitle=trim((string)($rstempsrc[($struilang!="en" && trim((string)($rstempsrc['MOVIE_TITLE_FR'] ?? ''))!="")?'MOVIE_TITLE_FR':'MOVIE_TITLE'] ?? ''));
						$strsrcyear=substr(trim((string)($rstempsrc['DAT_RELEASE'] ?? '')),0,4);
						if ($strsrcyear!="") { $strsrctitle.=" (".$strsrcyear.")"; }
						$strsourceworkmovielink="<a href=\"movie.php?movieid=".$lngsourceworkmovieid."\">".f_htmlspecialchars($strsrctitle)."</a>";
						if ($strsourceworkposterpath=="") {
							$strsourceworkposterpath=trim((string)($rstempsrc['POSTER_PATH'] ?? ''));
						}
					}
					f_sqlfreeresult($resultsrcmovie);
				}
			}
			$strsourceworkserielink="";
			if ($lngsourceworkserieid>0) {
				$strsqlsrcserie="SELECT ID_SERIE, SERIE_TITLE, SERIE_TITLE_FR, DAT_FIRST_AIR, POSTER_PATH FROM T_WC_T2S_SERIE WHERE ID_SERIE = ".f_longtosql($lngsourceworkserieid);
				$resultsrcserie=f_sqlselect($strsqlsrcserie);
				if ($resultsrcserie) {
					if ($rstempsrc=f_sqlresultfetcharray($resultsrcserie)) {
						$strsrctitle=trim((string)($rstempsrc[($struilang!="en" && trim((string)($rstempsrc['SERIE_TITLE_FR'] ?? ''))!="")?'SERIE_TITLE_FR':'SERIE_TITLE'] ?? ''));
						$strsrcyear=substr(trim((string)($rstempsrc['DAT_FIRST_AIR'] ?? '')),0,4);
						if ($strsrcyear!="") { $strsrctitle.=" (".$strsrcyear.")"; }
						$strsourceworkserielink="<a href=\"serie.php?serieid=".$lngsourceworkserieid."\">".f_htmlspecialchars($strsrctitle)."</a>";
						if ($strsourceworkposterpath=="") {
							$strsourceworkposterpath=trim((string)($rstempsrc['POSTER_PATH'] ?? ''));
						}
					}
					f_sqlfreeresult($resultsrcserie);
				}
			}

			$strsourceworktitle=$strsourceworkname.($lngsourceworkyear>0 ? " (".$lngsourceworkyear.")" : "");
			f_headerdisplay($strsourceworktitle,"",f_socialwikidataentity($strsourceworkname,$strsourceworkoverview,$strsourceworkwikipediapath,$strsourceworkposterpath));
			echo "<h1>".f_htmlspecialchars($strsourceworktitle)."</h1>";

			// Le nom dans l'autre langue, quand il differe : "Au coeur des tenebres" sous
			// "Heart of Darkness", et inversement.
			$strsourceworkothername=($strsourceworkname===$strsourceworknameen ? $strsourceworknamefr : $strsourceworknameen);
			if ($strsourceworkothername!="" && $strsourceworkothername!==$strsourceworkname) {
				echo "<p><strong>".get_movie_string($strsourceworkname===$strsourceworknameen ? 'lib_sourcework_name_fr' : 'lib_sourcework_name_en').":</strong> ".f_htmlspecialchars($strsourceworkothername)."</p>";
			}

			if ($strsourceworkwikipediapath!="") {
				echo "<p><img src=\"".f_htmlattr($strsourceworkwikipediapath)."\" alt=\"".f_htmlattr($strsourceworkname)."\" title=\"".f_htmlattr($strsourceworkname)."\" style=\"background-color: white; max-width: 100%; height: auto;\" /></p>";
			} elseif ($strsourceworkposterpath!="") {
				echo "<p><img src=\"".f_htmlattr(f_getmovieposterpicture($strsourceworkposterpath))."\" alt=\"".f_htmlattr($strsourceworkname)."\" title=\"".f_htmlattr($strsourceworkname)."\" style=\"background-color: white;\" /></p>";
			}

			if ($strsourceworktype!="") {
				echo "<p><strong>".get_movie_string('lib_sourcework_type').":</strong> ".f_htmlspecialchars(f_sourceworklabel('lib_sourcework_type_', $strsourceworktype))."</p>";
			}
			if ($strsourceworkform!="") {
				echo "<p><strong>".get_movie_string('lib_sourcework_form').":</strong> ".f_htmlspecialchars(f_sourceworklabel('lib_sourcework_form_', $strsourceworkform))."</p>";
			}
			if ($lngsourceworkyear>0) {
				echo "<p><strong>".get_movie_string('lib_sourcework_year').":</strong> ".$lngsourceworkyear."</p>";
			}
			if ($strsourceworkmovielink!="") {
				echo "<p><strong>".get_movie_string('lib_sourcework_as_movie').":</strong> ".$strsourceworkmovielink."</p>";
			}
			if ($strsourceworkserielink!="") {
				echo "<p><strong>".get_movie_string('lib_sourcework_as_serie').":</strong> ".$strsourceworkserielink."</p>";
			}
			if ($strsourceworkwikidataid!="") {
				echo "<p><strong>".get_movie_string('lib_movie_id_wikidata').":</strong> <a href=\"".f_htmlattr("https://www.wikidata.org/wiki/".$strsourceworkwikidataid)."\" target=\"_blank\" rel=\"noreferrer\">".f_htmlspecialchars($strsourceworkwikidataid)."</a> <a href=\"".f_htmlattr("https://www.wikidata.org/wiki/".$strsourceworkwikidataid)."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/Wikidata-logo.svg.png\" width=\"28\" height=\"16\" alt=\"Wikidata\" title=\"Wikidata\"></a></p>";
			}
			if ($strsourceworksource!="") {
				echo "<p><strong>Source:</strong> ".f_htmlspecialchars($strsourceworksource)."</p>";
			}
			if (trim($strsourceworkoverview)!="") {
				echo "<h2>".get_movie_string('lib_collection_overview')."</h2>";
				echo "<p>".nl2br(f_htmlspecialchars($strsourceworkoverview))."</p>";
			}

			// Les adaptations, films puis series, chacune paginee de son cote (Dracula compte
			// plusieurs centaines de films) : movie_page et serie_page voyagent ensemble.
			$lngsourceworkrowsperpage=50;
			$lngmoviepage=max(1, f_stringtolong(f_request2("movie_page")));
			$lngseriepage=max(1, f_stringtolong(f_request2("serie_page")));

			$lngmoviecount=0;
			$strsqlmoviescount="SELECT COUNT(DISTINCT J.ID_MOVIE) AS COMPTE FROM T_WC_T2S_MOVIE_SOURCE_WORK J WHERE J.ID_SOURCE_WORK = ".f_longtosql($lngsourceworkid)." AND COALESCE(J.DELETED,0) = 0";
			$resultmoviescount=f_sqlselect($strsqlmoviescount);
			if ($resultmoviescount) {
				if ($rtempcnt=f_sqlresultfetcharray($resultmoviescount)) {
					$lngmoviecount=f_stringtolong($rtempcnt['COMPTE']);
				}
				f_sqlfreeresult($resultmoviescount);
			}
			$arrmoviepageinfo=f_workpropertypaginationinfo($lngmoviepage, $lngsourceworkrowsperpage, $lngmoviecount);
			$lngmoviepage=$arrmoviepageinfo["page"];

			$lngseriecount=0;
			$strsqlseriescount="SELECT COUNT(DISTINCT J.ID_SERIE) AS COMPTE FROM T_WC_T2S_SERIE_SOURCE_WORK J WHERE J.ID_SOURCE_WORK = ".f_longtosql($lngsourceworkid)." AND COALESCE(J.DELETED,0) = 0";
			$resultseriescount=f_sqlselect($strsqlseriescount);
			if ($resultseriescount) {
				if ($rtempcnt=f_sqlresultfetcharray($resultseriescount)) {
					$lngseriecount=f_stringtolong($rtempcnt['COMPTE']);
				}
				f_sqlfreeresult($resultseriescount);
			}
			$arrseriepageinfo=f_workpropertypaginationinfo($lngseriepage, $lngsourceworkrowsperpage, $lngseriecount);
			$lngseriepage=$arrseriepageinfo["page"];

			echo "<h2 id=\"sourcework-movies\">".get_movie_string('lib_sourcework_movies').": ".$lngmoviecount."</h2>";
			echo "<table>";
			$strsqlmovies="SELECT M.ID_MOVIE, M.MOVIE_TITLE, M.DAT_RELEASE, M.ID_IMDB, M.IMDB_RATING, M.IMDB_VOTES, M.POSTER_PATH ";
			$strsqlmovies.="FROM T_WC_T2S_MOVIE M ";
			$strsqlmovies.="JOIN T_WC_T2S_MOVIE_SOURCE_WORK J ON M.ID_MOVIE = J.ID_MOVIE ";
			$strsqlmovies.="WHERE J.ID_SOURCE_WORK = ".f_longtosql($lngsourceworkid)." ";
			$strsqlmovies.="AND COALESCE(J.DELETED,0) = 0 ";
			$strsqlmovies.="ORDER BY J.DISPLAY_ORDER, M.DAT_RELEASE DESC, M.ID_MOVIE ";
			$strsqlmovies.="LIMIT ".(int)$arrmoviepageinfo["rows_per_page"]." OFFSET ".(int)$arrmoviepageinfo["offset"];
			$resultmovies=f_sqlselect($strsqlmovies);
			if ($resultmovies) {
				while ($rstempmovie=f_sqlresultfetcharray($resultmovies)) {
					f_movielistdisplay($rstempmovie['ID_MOVIE'],$rstempmovie['MOVIE_TITLE'],$rstempmovie['MOVIE_TITLE'],"", $rstempmovie['DAT_RELEASE'],$rstempmovie['ID_IMDB'],$rstempmovie['IMDB_RATING'],"", $rstempmovie['POSTER_PATH'],"",3,0,"float",$rstempmovie['IMDB_VOTES']);
				}
				f_sqlfreeresult($resultmovies);
			}
			echo "</table>";
			echo f_sourceworkpaginationhtml($lngsourceworkid, "movie", $arrmoviepageinfo, $lngmoviepage, $lngseriepage);

			echo "<h2 id=\"sourcework-series\">".get_movie_string('lib_sourcework_series').": ".$lngseriecount."</h2>";
			echo "<table>";
			$strsqlseries="SELECT S.ID_SERIE, S.SERIE_TITLE, S.DAT_FIRST_AIR, S.DAT_LAST_AIR, S.ID_IMDB, S.IMDB_RATING, S.IMDB_VOTES, S.POSTER_PATH ";
			$strsqlseries.="FROM T_WC_T2S_SERIE S ";
			$strsqlseries.="JOIN T_WC_T2S_SERIE_SOURCE_WORK J ON S.ID_SERIE = J.ID_SERIE ";
			$strsqlseries.="WHERE J.ID_SOURCE_WORK = ".f_longtosql($lngsourceworkid)." ";
			$strsqlseries.="AND COALESCE(J.DELETED,0) = 0 ";
			$strsqlseries.="ORDER BY J.DISPLAY_ORDER, S.DAT_FIRST_AIR DESC, S.ID_SERIE ";
			$strsqlseries.="LIMIT ".(int)$arrseriepageinfo["rows_per_page"]." OFFSET ".(int)$arrseriepageinfo["offset"];
			$resultseries=f_sqlselect($strsqlseries);
			if ($resultseries) {
				while ($rstempserierow=f_sqlresultfetcharray($resultseries)) {
					f_serielistdisplay($rstempserierow['ID_SERIE'],$rstempserierow['SERIE_TITLE'],$rstempserierow['SERIE_TITLE'],"", $rstempserierow['DAT_FIRST_AIR'],$rstempserierow['DAT_LAST_AIR'],$rstempserierow['ID_IMDB'],$rstempserierow['IMDB_RATING'],"", $rstempserierow['POSTER_PATH'],"",3,0,"float",$rstempserierow['IMDB_VOTES']);
				}
				f_sqlfreeresult($resultseries);
			}
			echo "</table>";
			echo f_sourceworkpaginationhtml($lngsourceworkid, "serie", $arrseriepageinfo, $lngmoviepage, $lngseriepage);

			if ($strsourceworkwikidataid!="") {
				// Meme lecture V2 que location.php : la classe se lit en V2, avec repli V1 dans
				// f_getwikidatalabel. Sections Wikidata et Wikipedia toujours emises (AGENTS.md).
				$strwditeminstanceofid=f_wikidataiteminstanceof_v2($strsourceworkwikidataid);
				$strwditeminstanceoflabel=f_getwikidatalabel($strwditeminstanceofid,$struilang,$arrwdsourcelabel);
				//Hook entity.wikidata: service blocks of the plugins above the Wikidata section
				echo f_pluginhtml("entity.wikidata",array("page"=>"sourcework","wikidataids"=>array($strsourceworkwikidataid,$strwditeminstanceofid),"lang"=>$struilang,"langalt"=>$struilangalt));
				echo "<h2>".get_movie_string('lib_wikidata_section')."</h2>";
				$strpropertyurl="property.php?propertyid=P31";
				echo "<p><strong><a href=\"".$strpropertyurl."\">".get_movie_string('lib_wikidata_type')."</a>:</strong> ".$strwditeminstanceoflabel."</p>";
				$strwikidataeverything=f_wikidataallpropertiesv2($struilang,$strsourceworkwikidataid,", ");
				echo $strwikidataeverything;

				echo "<h2>".get_movie_string('lib_wikipedia_section')."</h2>";
				$strwikipediacontent=f_wikipediallsections($struilang,$strsourceworkwikidataid,$strsep=", ");
				echo $strwikipediacontent;
			}

			//Hook page.beforefooter: blocks of the plugins at the end of the page
			echo f_pluginhtml("page.beforefooter",array("page"=>"sourcework"));
			f_footerdisplay();
		} else {
			f_headerdisplay("Source Work Not Found");
			echo "<h1>Source work not found.</h1>";
		}
	} else {
		f_headerdisplay("Invalid ID");
		echo "<h1>Invalid source work ID.</h1>";
	}
} else {
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}
?>
