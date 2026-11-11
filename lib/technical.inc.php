<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	$lngtechnicalid=f_stringtolong(f_request2("technicalid"));
	$intfound=false;

	if ($lngtechnicalid>0) {
		$strsql="SELECT * FROM T_WC_T2S_TECHNICAL WHERE ID_TECHNICAL = ".f_longtosql($lngtechnicalid);
		$result=f_sqlselect($strsql);
		if ($result) {
			if ($rstemp=f_sqlresultfetcharray($result)) {
				$intfound=true;
				$strtechnicallabel=isset($rstemp['WIKIDATA_LABEL']) ? trim((string)$rstemp['WIKIDATA_LABEL']) : '';
				$strtechnicaldescription=isset($rstemp['DESCRIPTION']) ? trim((string)$rstemp['DESCRIPTION']) : '';
				$strtechnicaldescriptionfr=isset($rstemp['DESCRIPTION_FR']) ? trim((string)$rstemp['DESCRIPTION_FR']) : '';
				$strtechnicalname=$strtechnicallabel;
				if ($strtechnicalname=="") {
					$strtechnicalname=$strtechnicaldescription;
				}
				if ($struilang!="en" && $strtechnicaldescriptionfr!="") {
					$strtechnicalname=$strtechnicaldescriptionfr;
				}
				$strtechnicaltype=isset($rstemp['TECHNICAL_TYPE']) ? $rstemp['TECHNICAL_TYPE'] : '';
				$lngtechnicalrecordid=isset($rstemp['ID_RECORD']) ? f_stringtolong($rstemp['ID_RECORD']) : 0;
				$strtechnicalwikidataid=isset($rstemp['ID_WIKIDATA']) ? trim($rstemp['ID_WIKIDATA']) : '';
				$strtechnicalwikipediapath=isset($rstemp['WIKIPEDIA_IMAGE_PATH']) ? $rstemp['WIKIPEDIA_IMAGE_PATH'] : '';
				$strtechnicaloverview=isset($rstemp['OVERVIEW']) ? $rstemp['OVERVIEW'] : '';
				$dblconfidence=isset($rstemp['CONFIDENCE']) ? $rstemp['CONFIDENCE'] : null;
				$strtimwikipediasearch=isset($rstemp['TIM_WIKIPEDIA_SEARCH']) ? $rstemp['TIM_WIKIPEDIA_SEARCH'] : '';
				$lngmoviecountfield=isset($rstemp['MOVIE_COUNT']) ? f_stringtolong($rstemp['MOVIE_COUNT']) : 0;
				$lngseriecountfield=isset($rstemp['SERIE_COUNT']) ? f_stringtolong($rstemp['SERIE_COUNT']) : 0;
				$dblimdbrating=isset($rstemp['IMDB_RATING']) ? $rstemp['IMDB_RATING'] : null;
				$dblimdbratingweighted=isset($rstemp['IMDB_RATING_WEIGHTED']) ? $rstemp['IMDB_RATING_WEIGHTED'] : null;
				$dblpopularity=isset($rstemp['POPULARITY']) ? $rstemp['POPULARITY'] : null;
				$lngdisplayorder=isset($rstemp['DISPLAY_ORDER']) ? f_stringtolong($rstemp['DISPLAY_ORDER']) : 0;
				$strdatcreat=isset($rstemp['DAT_CREAT']) ? $rstemp['DAT_CREAT'] : '';
				$strtimupdated=isset($rstemp['TIM_UPDATED']) ? $rstemp['TIM_UPDATED'] : '';
			}
			f_sqlfreeresult($result);
		}

		if ($intfound) {
			f_headerdisplay($strtechnicalname,"",f_socialwikidataentity($strtechnicalname,$strtechnicaloverview,$strtechnicalwikipediapath));
			echo "<h1>".f_htmlspecialchars($strtechnicalname)."</h1>";

			if ($strtechnicalwikipediapath!="") {
				echo "<p><img src=\"".f_htmlattr($strtechnicalwikipediapath)."\" alt=\"".f_htmlattr($strtechnicalname)."\" title=\"".f_htmlattr($strtechnicalname)."\" style=\"background-color: white; max-width: 100%; height: auto;\" /></p>";
			}

			if ($strtechnicallabel!="") {
				echo "<p><strong>Wikidata label:</strong> ".f_htmlspecialchars($strtechnicallabel)."</p>";
			}
			if ($strtechnicalwikidataid!="") {
				echo "<p><strong>".get_movie_string('lib_movie_id_wikidata').":</strong> <a href=\"".f_htmlattr("https://www.wikidata.org/wiki/".$strtechnicalwikidataid)."\" target=\"_blank\" rel=\"noreferrer\">".f_htmlspecialchars($strtechnicalwikidataid)."</a> <a href=\"".f_htmlattr("https://www.wikidata.org/wiki/".$strtechnicalwikidataid)."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/Wikidata-logo.svg.png\" width=\"28\" height=\"16\" alt=\"Wikidata\" title=\"Wikidata\"></a></p>";
			}
			if ($dblconfidence!==null && $dblconfidence!=="") {
				echo "<p><strong>Confidence:</strong> ".f_htmlspecialchars((string)$dblconfidence)."</p>";
			}
			if ($strtimwikipediasearch!="") {
				echo "<p><strong>Wikipedia search:</strong> ".f_htmlspecialchars($strtimwikipediasearch)."</p>";
			}
			if ($strtechnicaldescription!="") {
				echo "<p><strong>Description:</strong> ".f_htmlspecialchars($strtechnicaldescription)."</p>";
			}
			if ($strtechnicaldescriptionfr!="") {
				echo "<p><strong>Description (FR):</strong> ".f_htmlspecialchars($strtechnicaldescriptionfr)."</p>";
			}
			if ($strtechnicaltype!="") {
				echo "<p><strong>Type:</strong> ".f_htmlspecialchars($strtechnicaltype)."</p>";
			}
			if ($lngmoviecountfield>0) {
				echo "<p><strong>Movie count:</strong> ".f_htmlspecialchars((string)$lngmoviecountfield)."</p>";
			}
			if ($lngseriecountfield>0) {
				echo "<p><strong>Serie count:</strong> ".f_htmlspecialchars((string)$lngseriecountfield)."</p>";
			}
			if ($dblimdbrating!==null && $dblimdbrating!=="") {
				echo "<p><strong>IMDb rating:</strong> ".f_htmlspecialchars((string)$dblimdbrating)."</p>";
			}
			if ($dblimdbratingweighted!==null && $dblimdbratingweighted!=="") {
				echo "<p><strong>IMDb rating (weighted):</strong> ".f_htmlspecialchars((string)$dblimdbratingweighted)."</p>";
			}
			if ($dblpopularity!==null && $dblpopularity!=="") {
				echo "<p><strong>Popularity:</strong> ".f_htmlspecialchars((string)$dblpopularity)."</p>";
			}
			if ($lngdisplayorder>0) {
				echo "<p><strong>Display order:</strong> ".f_htmlspecialchars((string)$lngdisplayorder)."</p>";
			}
			if ($strdatcreat!="") {
				echo "<p><strong>Created:</strong> ".f_htmlspecialchars($strdatcreat)."</p>";
			}
			if ($strtimupdated!="") {
				echo "<p><strong>Updated:</strong> ".f_htmlspecialchars($strtimupdated)."</p>";
			}
			if ($lngtechnicalrecordid>0) {
				echo "<p><strong>Source record:</strong> ".f_htmlspecialchars((string)$lngtechnicalrecordid)."</p>";
			}

			if ($strtechnicaloverview!="") {
				echo "<h2>".get_movie_string('lib_collection_overview')."</h2>";
				echo "<p>".nl2br(f_htmlspecialchars($strtechnicaloverview))."</p>";
			}

			if ($strtechnicalwikidataid!="") {
				// Vingt lignes de lecture V1 et un repli de langue
				// remplaces par un appel. ALIASES etait de la donnee morte, elle disparait.
				$strwditeminstanceofid=f_wikidataiteminstanceof_v2($strtechnicalwikidataid);
				// Le libelle de la classe se lit en V2, repli V1.
				// f_getwikidatalabel fait le meme repli de langue que les deux requetes
				// qu'elle remplace, mais dans V2 d'abord et V1 seulement en dernier ressort.
				$strwditeminstanceoflabel=f_getwikidatalabel($strwditeminstanceofid,$struilang,$arrwdsourcelabel);
				// Diagnostic de la migration, sous $intprivate comme les autres informations de
				// service : visible sous un prefixe prive, invisible sur le site public. Les DEUX langues,
				// pas seulement celle de l interface, car un prefixe prive peut forcer l anglais alors que le francais
				// est justement ce qu on veut valider.
				//Hook entity.wikidata: service blocks of the plugins above the Wikidata section
				echo f_pluginhtml("entity.wikidata",array("page"=>"technical","wikidataids"=>array($strtechnicalwikidataid,$strwditeminstanceofid),"lang"=>$struilang,"langalt"=>$struilangalt));
				echo "<h2>".get_movie_string('lib_wikidata_section')."</h2>";
				$strpropertyurl="property.php?propertyid=P31";
				echo "<p><strong><a href=\"".$strpropertyurl."\">".get_movie_string('lib_wikidata_type')."</a>:</strong> ".$strwditeminstanceoflabel."</p>";
				$strwikidataeverything=f_wikidataallpropertiesv2($struilang,$strtechnicalwikidataid,", ");
				echo $strwikidataeverything;

				echo "<h2>".get_movie_string('lib_wikipedia_section')."</h2>";
				$strwikipediacontent=f_wikipediallsections($struilang,$strtechnicalwikidataid,$strsep=", ");
				echo $strwikipediacontent;
			}

			$lngmoviecount = 0;
			$strsqlmoviescount = "SELECT COUNT(J.ID_MOVIE) AS COMPTE FROM T_WC_T2S_MOVIE_TECHNICAL J WHERE J.ID_TECHNICAL = ".f_longtosql($lngtechnicalid);
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
			$strsqlmovies.="JOIN T_WC_T2S_MOVIE_TECHNICAL J ON M.ID_MOVIE = J.ID_MOVIE ";
			$strsqlmovies.="WHERE J.ID_TECHNICAL = ".f_longtosql($lngtechnicalid)." ";
			$strsqlmovies.="ORDER BY M.IMDB_RATING_WEIGHTED DESC";
			$resultmovies=f_sqlselect($strsqlmovies);
			if ($resultmovies) {
				while ($rstempmovie=f_sqlresultfetcharray($resultmovies)) {
					f_movielistdisplay($rstempmovie['ID_MOVIE'],$rstempmovie['MOVIE_TITLE'],$rstempmovie['MOVIE_TITLE'],"", $rstempmovie['DAT_RELEASE'],$rstempmovie['ID_IMDB'],$rstempmovie['IMDB_RATING'],"", $rstempmovie['POSTER_PATH'],"",3,0,"float",$rstempmovie['IMDB_VOTES']);
				}
				f_sqlfreeresult($resultmovies);
			}
			echo "</table>";

			//Hook page.beforefooter: blocks of the plugins at the end of the page
			echo f_pluginhtml("page.beforefooter",array("page"=>"technical"));

			f_footerdisplay();
		} else {
			f_headerdisplay("Technical Not Found");
			echo "<h1>Technical not found.</h1>";
		}
	} else {
		f_headerdisplay("Invalid ID");
		echo "<h1>Invalid technical ID.</h1>";
	}
} else {
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}
?>
