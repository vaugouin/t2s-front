<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
include_once(__DIR__."/award-format.inc.php");

if (!function_exists('f_awardpagewins')) {
	//Purpose: read the win rows of an award and group them by winner
	//Params: $strsql the query returning one row per win, $strkey the winner id column
	//Returns: an array winner id => list of win rows
	function f_awardpagewins($strsql, $strkey) {
		$arrrows=array();
		$result=f_sqlselect($strsql);
		if ($result) {
			while ($rstemp=f_sqlresultfetcharray($result)) {
				$arrrows[]=$rstemp;
			}
			f_sqlfreeresult($result);
		}
		return f_awardwinsbykey($arrrows, $strkey);
	}
}

if ($intdbavailable) {
	$lngawardid=f_stringtolong(f_request2("awardid"));
	$intfound=false;

	if ($lngawardid>0) {
		$strsql="SELECT * FROM T_WC_T2S_AWARD WHERE ID_AWARD = ".f_longtosql($lngawardid);
		$result=f_sqlselect($strsql);
		if ($result) {
			if ($rstemp=f_sqlresultfetcharray($result)) {
				$intfound=true;
				$strawardname=isset($rstemp['AWARD_NAME']) ? $rstemp['AWARD_NAME'] : '';
				$strawardsource=isset($rstemp['AWARD_SOURCE']) ? $rstemp['AWARD_SOURCE'] : '';
				$strawardtype=isset($rstemp['AWARD_TYPE']) ? $rstemp['AWARD_TYPE'] : '';
				$strawardwikidataid=isset($rstemp['ID_WIKIDATA']) ? trim($rstemp['ID_WIKIDATA']) : '';
				$strawardposterpath=isset($rstemp['POSTER_PATH']) ? $rstemp['POSTER_PATH'] : '';
				$strawardwikipediapath=isset($rstemp['WIKIPEDIA_IMAGE_PATH']) ? $rstemp['WIKIPEDIA_IMAGE_PATH'] : '';
				$strawardoverview=isset($rstemp['OVERVIEW']) ? $rstemp['OVERVIEW'] : '';
			}
			f_sqlfreeresult($result);
		}

		if ($intfound) {
			f_headerdisplay($strawardname,"",f_socialwikidataentity($strawardname,$strawardoverview,$strawardwikipediapath,$strawardposterpath));
			echo "<h1>".f_htmlspecialchars($strawardname)."</h1>";

			if ($strawardwikipediapath!="") {
				echo "<p><img src=\"".f_htmlattr($strawardwikipediapath)."\" alt=\"".f_htmlattr($strawardname)."\" title=\"".f_htmlattr($strawardname)."\" style=\"background-color: white; max-width: 100%; height: auto;\" /></p>";
			}
			if ($strawardposterpath!="") {
				echo "<p><img src=\"".f_htmlattr(f_getmovieposterpicture($strawardposterpath))."\" alt=\"".f_htmlattr($strawardname)."\" title=\"".f_htmlattr($strawardname)."\" style=\"background-color: white;\" /></p>";
			}

			if ($strawardwikidataid!="") {
				echo "<p><strong>".get_movie_string('lib_movie_id_wikidata').":</strong> <a href=\"".f_htmlattr("https://www.wikidata.org/wiki/".$strawardwikidataid)."\" target=\"_blank\" rel=\"noreferrer\">".f_htmlspecialchars($strawardwikidataid)."</a> <a href=\"".f_htmlattr("https://www.wikidata.org/wiki/".$strawardwikidataid)."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/Wikidata-logo.svg.png\" width=\"28\" height=\"16\" alt=\"Wikidata\" title=\"Wikidata\"></a></p>";
			}

			if ($strawardsource!="") {
				echo "<p><strong>Source:</strong> ".f_htmlspecialchars($strawardsource)."</p>";
			}
			if ($strawardtype!="") {
				echo "<p><strong>Type:</strong> ".f_htmlspecialchars($strawardtype)."</p>";
			}
			if ($strawardoverview!="") {
				echo "<h2>".get_movie_string('lib_collection_overview')."</h2>";
				echo "<p>".nl2br(f_htmlspecialchars($strawardoverview))."</p>";
			}
			if ($strawardwikidataid!="") {
				// Vingt lignes de lecture V1 et un repli de langue
				// remplaces par un appel. ALIASES etait de la donnee morte, elle disparait.
				$strwditeminstanceofid=f_wikidataiteminstanceof_v2($strawardwikidataid);
				// 2026-08-18: premier fichier bascule sur V2.
				// f_getwikidatalabel fait le meme repli de langue que les deux requetes
				// qu'elle remplace, mais dans V2 d'abord et V1 seulement en dernier ressort.
				$strwditeminstanceoflabel=f_getwikidatalabel($strwditeminstanceofid,$struilang,$arrwdsourcelabel);
				// Diagnostic de la migration V1 vers V2, sous $intprivate comme les autres
				// informations de service de ce fichier (cf. ligne des liens de
				// telechargement) : visible sous un prefixe prive, invisible sur le site public, sans
				// parametre d URL a retenir. Le bloc porte la provenance du libelle de la
				// recompense elle-meme ET celle de son type : ce sont deux entites
				// distinctes, qui peuvent tres bien etre servies par des sources
				// differentes, et c est justement ce qu on veut voir.
				//Hook entity.wikidata: service blocks of the plugins above the Wikidata section
				echo f_pluginhtml("entity.wikidata",array("page"=>"award","wikidataids"=>array($strawardwikidataid,$strwditeminstanceofid),"lang"=>$struilang,"langalt"=>$struilangalt));
				echo "<h2>".get_movie_string('lib_wikidata_section')."</h2>";
				$strpropertyurl="property.php?propertyid=P31";
				echo "<p><strong><a href=\"".$strpropertyurl."\">".get_movie_string('lib_wikidata_type')."</a>:</strong> ".$strwditeminstanceoflabel."</p>";
				$strwikidataeverything=f_wikidataallpropertiesv2($struilang,$strawardwikidataid,", ");
				echo $strwikidataeverything;

				echo "<h2>".get_movie_string('lib_wikipedia_section')."</h2>";
				$strwikipediacontent=f_wikipediallsections($struilang,$strawardwikidataid,$strsep=", ");
				echo $strwikipediacontent;
			}

			// Movies section with count
			$lngmoviecount = 0;
			// One link row per win, a movie or a series rewarded several times is counted and listed once.
			$strsqlmoviescount = "SELECT COUNT(DISTINCT J.ID_MOVIE) AS COMPTE FROM T_WC_T2S_MOVIE_AWARD J WHERE J.ID_AWARD = ".f_longtosql($lngawardid);
			$resultmoviescount = f_sqlselect($strsqlmoviescount);
			if ($resultmoviescount) {
				if ($rtempcnt = f_sqlresultfetcharray($resultmoviescount)) {
					$lngmoviecount = f_stringtolong($rtempcnt['COMPTE']);
				}
				f_sqlfreeresult($resultmoviescount);
			}
			echo "<h2>".get_movie_string('lib_movies_section').": ".$lngmoviecount."</h2>";
			echo "<table>";
			$strsqlmovies="SELECT M.ID_MOVIE, M.MOVIE_TITLE, M.DAT_RELEASE, M.ID_IMDB, M.IMDB_RATING, M.IMDB_VOTES, M.POSTER_PATH ";
			$strsqlmovies.="FROM T_WC_T2S_MOVIE M ";
			$strsqlmovies.="JOIN (SELECT ID_MOVIE, MIN(DISPLAY_ORDER) AS DISPLAY_ORDER FROM T_WC_T2S_MOVIE_AWARD WHERE ID_AWARD = ".f_longtosql($lngawardid)." GROUP BY ID_MOVIE) J ON M.ID_MOVIE = J.ID_MOVIE ";
			$strsqlmovies.="ORDER BY J.DISPLAY_ORDER, M.DAT_RELEASE DESC";
			// One link row per win: the years of the wins are shown under each movie.
			$arrmoviewins=f_awardpagewins("SELECT ID_MOVIE, AWARD_YEAR FROM T_WC_T2S_MOVIE_AWARD WHERE ID_AWARD = ".f_longtosql($lngawardid), "ID_MOVIE");
			$resultmovies=f_sqlselect($strsqlmovies);
			if ($resultmovies) {
				while ($rstempmovie=f_sqlresultfetcharray($resultmovies)) {
					$strmoviewins=f_awardwinscaption(($arrmoviewins[$rstempmovie['ID_MOVIE']] ?? array()), $struilang, get_movie_string('lib_award_won'), get_movie_string('lib_award_for'));
					f_movielistdisplay($rstempmovie['ID_MOVIE'],$rstempmovie['MOVIE_TITLE'],$rstempmovie['MOVIE_TITLE'],"", $rstempmovie['DAT_RELEASE'],$rstempmovie['ID_IMDB'],$rstempmovie['IMDB_RATING'],$strmoviewins, $rstempmovie['POSTER_PATH'],"",3,0,"float",$rstempmovie['IMDB_VOTES']);
				}
				f_sqlfreeresult($resultmovies);
			}
			echo "</table>";

			// TV Shows section with count
			$lngseriecount = 0;
			$strsqlseriescount = "SELECT COUNT(DISTINCT J.ID_SERIE) AS COMPTE FROM T_WC_T2S_SERIE_AWARD J WHERE J.ID_AWARD = ".f_longtosql($lngawardid);
			$resultseriescount = f_sqlselect($strsqlseriescount);
			if ($resultseriescount) {
				if ($rtempcnt = f_sqlresultfetcharray($resultseriescount)) {
					$lngseriecount = f_stringtolong($rtempcnt['COMPTE']);
				}
				f_sqlfreeresult($resultseriescount);
			}
			echo "<h2>".get_movie_string('lib_tv_shows_section').": ".$lngseriecount."</h2>";
			echo "<table>";
			$strsqlseries="SELECT S.ID_SERIE, S.SERIE_TITLE, S.DAT_FIRST_AIR, S.DAT_LAST_AIR, S.ID_IMDB, S.IMDB_RATING, S.IMDB_VOTES, S.POSTER_PATH ";
			$strsqlseries.="FROM T_WC_T2S_SERIE S ";
			$strsqlseries.="JOIN (SELECT ID_SERIE, MIN(DISPLAY_ORDER) AS DISPLAY_ORDER FROM T_WC_T2S_SERIE_AWARD WHERE ID_AWARD = ".f_longtosql($lngawardid)." GROUP BY ID_SERIE) J ON S.ID_SERIE = J.ID_SERIE ";
			$strsqlseries.="ORDER BY J.DISPLAY_ORDER, S.DAT_FIRST_AIR DESC";
			$arrseriewins=f_awardpagewins("SELECT ID_SERIE, AWARD_YEAR FROM T_WC_T2S_SERIE_AWARD WHERE ID_AWARD = ".f_longtosql($lngawardid), "ID_SERIE");
			$resultseries=f_sqlselect($strsqlseries);
			if ($resultseries) {
				while ($rstempserierow=f_sqlresultfetcharray($resultseries)) {
					$strseriewins=f_awardwinscaption(($arrseriewins[$rstempserierow['ID_SERIE']] ?? array()), $struilang, get_movie_string('lib_award_won'), get_movie_string('lib_award_for'));
					f_serielistdisplay($rstempserierow['ID_SERIE'],$rstempserierow['SERIE_TITLE'],$rstempserierow['SERIE_TITLE'],"", $rstempserierow['DAT_FIRST_AIR'],$rstempserierow['DAT_LAST_AIR'],$rstempserierow['ID_IMDB'],$rstempserierow['IMDB_RATING'],$strseriewins, $rstempserierow['POSTER_PATH'],"",3,0,"float",$rstempserierow['IMDB_VOTES']);
				}
				f_sqlfreeresult($resultseries);
			}
			echo "</table>";

			// Persons section with count and deduplication
			$lngpersoncount = 0;
			$strsqlpersonscount = "SELECT COUNT(DISTINCT J.ID_PERSON) AS COMPTE FROM T_WC_T2S_PERSON_AWARD J WHERE J.ID_AWARD = ".f_longtosql($lngawardid);
			$resultpersonscount = f_sqlselect($strsqlpersonscount);
			if ($resultpersonscount) {
				if ($rtempcnt = f_sqlresultfetcharray($resultpersonscount)) {
					$lngpersoncount = f_stringtolong($rtempcnt['COMPTE']);
				}
				f_sqlfreeresult($resultpersonscount);
			}
			echo "<h2>".get_movie_string('lib_persons_section').": ".$lngpersoncount." ".get_movie_string('lib_person_item')."</h2>";
			echo "<table>";
			$strsqlpersons = "SELECT P.ID_PERSON, P.NAME AS PERSON_NAME, P.BIOGRAPHY, P.BIRTHDAY, P.DEATHDAY, P.ID_IMDB, P.PROFILE_PATH, JP.MIN_DISPLAY_ORDER ";
			$strsqlpersons .= "FROM ".$strsqlns."TMDB_PERSON P ";
			$strsqlpersons .= "JOIN (SELECT ID_PERSON, MIN(DISPLAY_ORDER) AS MIN_DISPLAY_ORDER FROM T_WC_T2S_PERSON_AWARD WHERE ID_AWARD = ".f_longtosql($lngawardid)." GROUP BY ID_PERSON) JP ";
			$strsqlpersons .= "ON P.ID_PERSON = JP.ID_PERSON ";
			$strsqlpersons .= "ORDER BY JP.MIN_DISPLAY_ORDER, P.NAME";
			// For a person, each win also names the movie or the series it was won for.
			$strsqlpersonwins="SELECT W.ID_PERSON, W.AWARD_YEAR, W.ID_MOVIE_FOR, M.MOVIE_TITLE, M.MOVIE_TITLE_FR, W.ID_SERIE_FOR, S.SERIE_TITLE, S.SERIE_TITLE_FR ";
			$strsqlpersonwins.="FROM T_WC_T2S_PERSON_AWARD W ";
			$strsqlpersonwins.="LEFT JOIN T_WC_T2S_MOVIE M ON M.ID_MOVIE = W.ID_MOVIE_FOR ";
			$strsqlpersonwins.="LEFT JOIN T_WC_T2S_SERIE S ON S.ID_SERIE = W.ID_SERIE_FOR ";
			$strsqlpersonwins.="WHERE W.ID_AWARD = ".f_longtosql($lngawardid);
			$arrpersonwins=f_awardpagewins($strsqlpersonwins, "ID_PERSON");
			$resultpersons=f_sqlselect($strsqlpersons);
			if ($resultpersons) {
				while ($rtempperson=f_sqlresultfetcharray($resultpersons)) {
					f_personlistdisplay($rtempperson['ID_PERSON'],$rtempperson['PERSON_NAME'],$rtempperson['BIOGRAPHY'],$rtempperson['BIRTHDAY'],$rtempperson['DEATHDAY'],$rtempperson['ID_IMDB'],$rtempperson['PROFILE_PATH'],3,f_awardwinscaption(($arrpersonwins[$rtempperson['ID_PERSON']] ?? array()), $struilang, get_movie_string('lib_award_won'), get_movie_string('lib_award_for')));
				}
				f_sqlfreeresult($resultpersons);
			}
			echo "</table>";

			//Hook page.beforefooter: blocks of the plugins at the end of the page
			echo f_pluginhtml("page.beforefooter",array("page"=>"award"));
			f_footerdisplay();
		} else {
			f_headerdisplay("Award Not Found");
			echo "<h1>Award not found.</h1>";
		}
	} else {
		f_headerdisplay("Invalid ID");
		echo "<h1>Invalid award ID.</h1>";
	}
} else {
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}
?>
