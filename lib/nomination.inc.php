<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	$lngnominationid=f_stringtolong(f_request2("nominationid"));
	$intfound=false;

	if ($lngnominationid>0) {
		$strsql="SELECT * FROM T_WC_T2S_NOMINATION WHERE ID_NOMINATION = ".f_longtosql($lngnominationid);
		$result=f_sqlselect($strsql);
		if ($result) {
			if ($rstemp=f_sqlresultfetcharray($result)) {
				$intfound=true;
				$strnominationname=isset($rstemp['NOMINATION_NAME']) ? $rstemp['NOMINATION_NAME'] : '';
				$strnominationsource=isset($rstemp['NOMINATION_SOURCE']) ? $rstemp['NOMINATION_SOURCE'] : '';
				$strnominationtype=isset($rstemp['NOMINATION_TYPE']) ? $rstemp['NOMINATION_TYPE'] : '';
				$strnominationwikidataid=isset($rstemp['ID_WIKIDATA']) ? trim($rstemp['ID_WIKIDATA']) : '';
				$strnominationposterpath=isset($rstemp['POSTER_PATH']) ? $rstemp['POSTER_PATH'] : '';
				$strnominationwikipediapath=isset($rstemp['WIKIPEDIA_IMAGE_PATH']) ? $rstemp['WIKIPEDIA_IMAGE_PATH'] : '';
				$strnominationoverview=isset($rstemp['OVERVIEW']) ? $rstemp['OVERVIEW'] : '';
			}
			f_sqlfreeresult($result);
		}

		if ($intfound) {
			f_headerdisplay($strnominationname,"",f_socialwikidataentity($strnominationname,$strnominationoverview,$strnominationwikipediapath,$strnominationposterpath));
			echo "<h1>".f_htmlspecialchars($strnominationname)."</h1>";

			if ($strnominationwikipediapath!="") {
				echo "<p><img src=\"".f_htmlattr($strnominationwikipediapath)."\" alt=\"".f_htmlattr($strnominationname)."\" title=\"".f_htmlattr($strnominationname)."\" style=\"background-color: white; max-width: 100%; height: auto;\" /></p>";
			}
			if ($strnominationposterpath!="") {
				echo "<p><img src=\"".f_htmlattr(f_getmovieposterpicture($strnominationposterpath))."\" alt=\"".f_htmlattr($strnominationname)."\" title=\"".f_htmlattr($strnominationname)."\" style=\"background-color: white;\" /></p>";
			}

			if ($strnominationwikidataid!="") {
				echo "<p><strong>".get_movie_string('lib_movie_id_wikidata').":</strong> <a href=\"".f_htmlattr("https://www.wikidata.org/wiki/".$strnominationwikidataid)."\" target=\"_blank\" rel=\"noreferrer\">".f_htmlspecialchars($strnominationwikidataid)."</a> <a href=\"".f_htmlattr("https://www.wikidata.org/wiki/".$strnominationwikidataid)."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/Wikidata-logo.svg.png\" width=\"28\" height=\"16\" alt=\"Wikidata\" title=\"Wikidata\"></a></p>";
			}

			if ($strnominationsource!="") {
				echo "<p><strong>Source:</strong> ".f_htmlspecialchars($strnominationsource)."</p>";
			}
			if ($strnominationtype!="") {
				echo "<p><strong>Type:</strong> ".f_htmlspecialchars($strnominationtype)."</p>";
			}
			if ($strnominationoverview!="") {
				echo "<h2>".get_movie_string('lib_collection_overview')."</h2>";
				echo "<p>".nl2br(f_htmlspecialchars($strnominationoverview))."</p>";
			}
			if ($strnominationwikidataid!="") {
				// Vingt lignes de lecture V1 et un repli de langue
				// remplaces par un appel. ALIASES etait de la donnee morte, elle disparait.
				$strwditeminstanceofid=f_wikidataiteminstanceof_v2($strnominationwikidataid);
				// Le libelle de la classe se lit en V2, repli V1.
				// f_getwikidatalabel fait le meme repli de langue que les deux requetes
				// qu'elle remplace, mais dans V2 d'abord et V1 seulement en dernier ressort.
				$strwditeminstanceoflabel=f_getwikidatalabel($strwditeminstanceofid,$struilang,$arrwdsourcelabel);
				// Diagnostic de la migration, sous $intprivate comme les autres informations de
				// service : visible sous un prefixe prive, invisible sur le site public. Les DEUX langues,
				// pas seulement celle de l interface, car un prefixe prive peut forcer l anglais alors que le francais
				// est justement ce qu on veut valider.
				//Hook entity.wikidata: service blocks of the plugins above the Wikidata section
				echo f_pluginhtml("entity.wikidata",array("page"=>"nomination","wikidataids"=>array($strnominationwikidataid,$strwditeminstanceofid),"lang"=>$struilang,"langalt"=>$struilangalt));
				echo "<h2>".get_movie_string('lib_wikidata_section')."</h2>";
				$strpropertyurl="property.php?propertyid=P31";
				echo "<p><strong><a href=\"".$strpropertyurl."\">".get_movie_string('lib_wikidata_type')."</a>:</strong> ".$strwditeminstanceoflabel."</p>";
				$strwikidataeverything=f_wikidataallpropertiesv2($struilang,$strnominationwikidataid,", ");
				echo $strwikidataeverything;

				echo "<h2>".get_movie_string('lib_wikipedia_section')."</h2>";
				$strwikipediacontent=f_wikipediallsections($struilang,$strnominationwikidataid,$strsep=", ");
				echo $strwikipediacontent;
			}

			// Movies section with count
			$lngmoviecount = 0;
			$strsqlmoviescount = "SELECT COUNT(J.ID_MOVIE) AS COMPTE FROM T_WC_T2S_MOVIE_NOMINATION J WHERE J.ID_NOMINATION = ".f_longtosql($lngnominationid);
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
			$strsqlmovies.="JOIN T_WC_T2S_MOVIE_NOMINATION J ON M.ID_MOVIE = J.ID_MOVIE ";
			$strsqlmovies.="WHERE J.ID_NOMINATION = ".f_longtosql($lngnominationid)." ";
			$strsqlmovies.="ORDER BY J.DISPLAY_ORDER, M.DAT_RELEASE DESC";
			$resultmovies=f_sqlselect($strsqlmovies);
			if ($resultmovies) {
				while ($rstempmovie=f_sqlresultfetcharray($resultmovies)) {
					f_movielistdisplay($rstempmovie['ID_MOVIE'],$rstempmovie['MOVIE_TITLE'],$rstempmovie['MOVIE_TITLE'],"", $rstempmovie['DAT_RELEASE'],$rstempmovie['ID_IMDB'],$rstempmovie['IMDB_RATING'],"", $rstempmovie['POSTER_PATH'],"",3,0,"float",$rstempmovie['IMDB_VOTES']);
				}
				f_sqlfreeresult($resultmovies);
			}
			echo "</table>";

			// TV Shows section with count
			$lngseriecount = 0;
			$strsqlseriescount = "SELECT COUNT(J.ID_SERIE) AS COMPTE FROM T_WC_T2S_SERIE_NOMINATION J WHERE J.ID_NOMINATION = ".f_longtosql($lngnominationid);
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
			$strsqlseries.="JOIN T_WC_T2S_SERIE_NOMINATION J ON S.ID_SERIE = J.ID_SERIE ";
			$strsqlseries.="WHERE J.ID_NOMINATION = ".f_longtosql($lngnominationid)." ";
			$strsqlseries.="ORDER BY J.DISPLAY_ORDER, S.DAT_FIRST_AIR DESC";
			$resultseries=f_sqlselect($strsqlseries);
			if ($resultseries) {
				while ($rstempserierow=f_sqlresultfetcharray($resultseries)) {
					f_serielistdisplay($rstempserierow['ID_SERIE'],$rstempserierow['SERIE_TITLE'],$rstempserierow['SERIE_TITLE'],"", $rstempserierow['DAT_FIRST_AIR'],$rstempserierow['DAT_LAST_AIR'],$rstempserierow['ID_IMDB'],$rstempserierow['IMDB_RATING'],"", $rstempserierow['POSTER_PATH'],"",3,0,"float",$rstempserierow['IMDB_VOTES']);
				}
				f_sqlfreeresult($resultseries);
			}
			echo "</table>";

			// Persons section with count and deduplication
			$lngpersoncount = 0;
			$strsqlpersonscount = "SELECT COUNT(DISTINCT J.ID_PERSON) AS COMPTE FROM T_WC_T2S_PERSON_NOMINATION J WHERE J.ID_NOMINATION = ".f_longtosql($lngnominationid);
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
			$strsqlpersons .= "JOIN (SELECT ID_PERSON, MIN(DISPLAY_ORDER) AS MIN_DISPLAY_ORDER FROM T_WC_T2S_PERSON_NOMINATION WHERE ID_NOMINATION = ".f_longtosql($lngnominationid)." GROUP BY ID_PERSON) JP ";
			$strsqlpersons .= "ON P.ID_PERSON = JP.ID_PERSON ";
			$strsqlpersons .= "ORDER BY JP.MIN_DISPLAY_ORDER, P.NAME";
			$resultpersons=f_sqlselect($strsqlpersons);
			if ($resultpersons) {
				while ($rtempperson=f_sqlresultfetcharray($resultpersons)) {
					f_personlistdisplay($rtempperson['ID_PERSON'],$rtempperson['PERSON_NAME'],$rtempperson['BIOGRAPHY'],$rtempperson['BIRTHDAY'],$rtempperson['DEATHDAY'],$rtempperson['ID_IMDB'],$rtempperson['PROFILE_PATH'],3);
				}
				f_sqlfreeresult($resultpersons);
			}
			echo "</table>";

			//Hook page.beforefooter: blocks of the plugins at the end of the page
			echo f_pluginhtml("page.beforefooter",array("page"=>"nomination"));
			f_footerdisplay();
		} else {
			f_headerdisplay("Nomination Not Found");
			echo "<h1>Nomination not found.</h1>";
		}
	} else {
		f_headerdisplay("Invalid ID");
		echo "<h1>Invalid nomination ID.</h1>";
	}
} else {
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}
?>
