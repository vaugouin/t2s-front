<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	$lngtopicid=f_stringtolong(f_request2("topicid"));
	$intfound=false;

	if ($lngtopicid>0) {
		$strsql="SELECT * FROM T_WC_T2S_TOPIC WHERE ID_TOPIC = ".f_longtosql($lngtopicid);
		$result=f_sqlselect($strsql);
		if ($result) {
			if ($rstemp=f_sqlresultfetcharray($result)) {
				$intfound=true;
				$strtopicname=isset($rstemp['TOPIC_NAME']) ? $rstemp['TOPIC_NAME'] : '';
				if ($struilang!="en" && isset($rstemp['TOPIC_NAME_FR']) && trim((string)$rstemp['TOPIC_NAME_FR'])!="") {
					$strtopicname=$rstemp['TOPIC_NAME_FR'];
				}
				$strtopicsource=isset($rstemp['TOPIC_SOURCE']) ? $rstemp['TOPIC_SOURCE'] : '';
				$strtopictype=isset($rstemp['TOPIC_TYPE']) ? $rstemp['TOPIC_TYPE'] : '';
				$lngtopicrecordid=isset($rstemp['ID_RECORD']) ? f_stringtolong($rstemp['ID_RECORD']) : 0;
				$strtopicwikidataid=isset($rstemp['ID_WIKIDATA']) ? trim($rstemp['ID_WIKIDATA']) : '';
				$strtopicposterpath=isset($rstemp['POSTER_PATH']) ? $rstemp['POSTER_PATH'] : '';
				$strtopicwikipediapath=isset($rstemp['WIKIPEDIA_IMAGE_PATH']) ? $rstemp['WIKIPEDIA_IMAGE_PATH'] : '';
				$strtopicoverview=isset($rstemp['OVERVIEW']) ? $rstemp['OVERVIEW'] : '';
			}
			f_sqlfreeresult($result);
		}

		if ($intfound) {
			f_headerdisplay($strtopicname,"",f_socialwikidataentity($strtopicname,$strtopicoverview,$strtopicwikipediapath,$strtopicposterpath));
			echo "<h1>".f_htmlspecialchars($strtopicname)."</h1>";

			if ($strtopicwikipediapath!="") {
				echo "<p><img src=\"".f_htmlattr($strtopicwikipediapath)."\" alt=\"".f_htmlattr($strtopicname)."\" title=\"".f_htmlattr($strtopicname)."\" style=\"background-color: white; max-width: 100%; height: auto;\" /></p>";
			}
			if ($strtopicposterpath!="") {
				echo "<p><img src=\"".f_htmlattr(f_getmovieposterpicture($strtopicposterpath))."\" alt=\"".f_htmlattr($strtopicname)."\" title=\"".f_htmlattr($strtopicname)."\" style=\"background-color: white;\" /></p>";
			}

			if ($strtopicwikidataid!="") {
				echo "<p><strong>".get_movie_string('lib_movie_id_wikidata').":</strong> <a href=\"".f_htmlattr("https://www.wikidata.org/wiki/".$strtopicwikidataid)."\" target=\"_blank\" rel=\"noreferrer\">".f_htmlspecialchars($strtopicwikidataid)."</a> <a href=\"".f_htmlattr("https://www.wikidata.org/wiki/".$strtopicwikidataid)."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/Wikidata-logo.svg.png\" width=\"28\" height=\"16\" alt=\"Wikidata\" title=\"Wikidata\"></a></p>";
			}

			if ($strtopicsource!="") {
				echo "<p><strong>Source:</strong> ".f_htmlspecialchars($strtopicsource)."</p>";
			}
			if ($strtopictype!="") {
				echo "<p><strong>Type:</strong> ".f_htmlspecialchars($strtopictype)."</p>";
			}
			if ($lngtopicrecordid>0) {
				$strsourceurl="";
				switch (strtolower(trim((string)$strtopicsource))) {
				case "collection":
					$strsourceurl="collection.php?collectionid=".$lngtopicrecordid;
					break;
				case "keyword":
					$strsourceurl="keyword.php?keywordid=".$lngtopicrecordid;
					break;
				case "list":
					$strsourceurl="list.php?listid=".$lngtopicrecordid;
					break;
				}
				if ($strsourceurl!="") {
					echo "<p><strong>Source record:</strong> <a href=\"".f_htmlattr($strsourceurl)."\">".f_htmlspecialchars($lngtopicrecordid)."</a></p>";
				} else {
					echo "<p><strong>Source record:</strong> ".f_htmlspecialchars($lngtopicrecordid)."</p>";
				}
			}
			if ($strtopicoverview!="") {
				echo "<h2>".get_movie_string('lib_collection_overview')."</h2>";
				echo "<p>".nl2br(f_htmlspecialchars($strtopicoverview))."</p>";
			}
			if ($strtopicwikidataid!="") {
				// Vingt lignes de lecture V1 et un repli de langue
				// remplaces par un appel. ALIASES etait de la donnee morte, elle disparait.
				$strwditeminstanceofid=f_wikidataiteminstanceof_v2($strtopicwikidataid);
				// Le libelle de la classe se lit en V2, repli V1.
				// f_getwikidatalabel fait le meme repli de langue que les deux requetes
				// qu'elle remplace, mais dans V2 d'abord et V1 seulement en dernier ressort.
				$strwditeminstanceoflabel=f_getwikidatalabel($strwditeminstanceofid,$struilang,$arrwdsourcelabel);
				// Diagnostic de la migration, sous $intprivate comme les autres informations de
				// service : visible sous un prefixe prive, invisible sur le site public. Les DEUX langues,
				// pas seulement celle de l interface, car un prefixe prive peut forcer l anglais alors que le francais
				// est justement ce qu on veut valider.
				//Hook entity.wikidata: service blocks of the plugins above the Wikidata section
				echo f_pluginhtml("entity.wikidata",array("page"=>"t2stopic","wikidataids"=>array($strtopicwikidataid,$strwditeminstanceofid),"lang"=>$struilang,"langalt"=>$struilangalt));
				echo "<h2>".get_movie_string('lib_wikidata_section')."</h2>";
				$strpropertyurl="property.php?propertyid=P31";
				echo "<p><strong><a href=\"".$strpropertyurl."\">".get_movie_string('lib_wikidata_type')."</a>:</strong> ".$strwditeminstanceoflabel."</p>";
				$strwikidataeverything=f_wikidataallpropertiesv2($struilang,$strtopicwikidataid,", ");
				echo $strwikidataeverything;

				echo "<h2>".get_movie_string('lib_wikipedia_section')."</h2>";
				$strwikipediacontent=f_wikipediallsections($struilang,$strtopicwikidataid,$strsep=", ");
				echo $strwikipediacontent;
			}

			$lngmoviecount = 0;
			$strsqlmoviescount = "SELECT COUNT(J.ID_MOVIE) AS COMPTE FROM T_WC_T2S_MOVIE_TOPIC J WHERE J.ID_TOPIC = ".f_longtosql($lngtopicid);
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
			$strsqlmovies.="JOIN T_WC_T2S_MOVIE_TOPIC J ON M.ID_MOVIE = J.ID_MOVIE ";
			$strsqlmovies.="WHERE J.ID_TOPIC = ".f_longtosql($lngtopicid)." ";
			$strsqlmovies.="ORDER BY J.DISPLAY_ORDER, M.DAT_RELEASE DESC";
			$resultmovies=f_sqlselect($strsqlmovies);
			if ($resultmovies) {
				while ($rstempmovie=f_sqlresultfetcharray($resultmovies)) {
					f_movielistdisplay($rstempmovie['ID_MOVIE'],$rstempmovie['MOVIE_TITLE'],$rstempmovie['MOVIE_TITLE'],"", $rstempmovie['DAT_RELEASE'],$rstempmovie['ID_IMDB'],$rstempmovie['IMDB_RATING'],"", $rstempmovie['POSTER_PATH'],"",3,0,"float",$rstempmovie['IMDB_VOTES']);
				}
				f_sqlfreeresult($resultmovies);
			}
			echo "</table>";

			$lngseriecount = 0;
			$strsqlseriescount = "SELECT COUNT(J.ID_SERIE) AS COMPTE FROM T_WC_T2S_SERIE_TOPIC J WHERE J.ID_TOPIC = ".f_longtosql($lngtopicid);
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
			$strsqlseries.="JOIN T_WC_T2S_SERIE_TOPIC J ON S.ID_SERIE = J.ID_SERIE ";
			$strsqlseries.="WHERE J.ID_TOPIC = ".f_longtosql($lngtopicid)." ";
			$strsqlseries.="ORDER BY J.DISPLAY_ORDER, S.DAT_FIRST_AIR DESC";
			$resultseries=f_sqlselect($strsqlseries);
			if ($resultseries) {
				while ($rstempserierow=f_sqlresultfetcharray($resultseries)) {
					f_serielistdisplay($rstempserierow['ID_SERIE'],$rstempserierow['SERIE_TITLE'],$rstempserierow['SERIE_TITLE'],"", $rstempserierow['DAT_FIRST_AIR'],$rstempserierow['DAT_LAST_AIR'],$rstempserierow['ID_IMDB'],$rstempserierow['IMDB_RATING'],"", $rstempserierow['POSTER_PATH'],"",3,0,"float",$rstempserierow['IMDB_VOTES']);
				}
				f_sqlfreeresult($resultseries);
			}
			echo "</table>";

			//Hook page.beforefooter: blocks of the plugins at the end of the page
			echo f_pluginhtml("page.beforefooter",array("page"=>"t2stopic"));
			f_footerdisplay();
		} else {
			f_headerdisplay("Topic Not Found");
			echo "<h1>Topic not found.</h1>";
		}
	} else {
		f_headerdisplay("Invalid ID");
		echo "<h1>Invalid topic ID.</h1>";
	}
} else {
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}
?>
