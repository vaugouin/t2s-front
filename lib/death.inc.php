<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	$lngdeathid=f_stringtolong(f_request2("deathid"));
	$intfound=false;

	if ($lngdeathid>0) {
		$strsql="SELECT * FROM T_WC_T2S_DEATH WHERE ID_DEATH = ".f_longtosql($lngdeathid);
		$result=f_sqlselect($strsql);
		if ($result) {
			if ($rstemp=f_sqlresultfetcharray($result)) {
				$intfound=true;
				$strdeathname=isset($rstemp['DEATH_NAME']) ? $rstemp['DEATH_NAME'] : '';
				$strdeathsource=isset($rstemp['DEATH_SOURCE']) ? $rstemp['DEATH_SOURCE'] : '';
				$strdeathtype=isset($rstemp['DEATH_TYPE']) ? $rstemp['DEATH_TYPE'] : '';
				$strdeathwikidataid=isset($rstemp['ID_WIKIDATA']) ? trim($rstemp['ID_WIKIDATA']) : '';
				$strdeathprofilepath=isset($rstemp['PROFILE_PATH']) ? $rstemp['PROFILE_PATH'] : '';
				$strdeathwikipediapath=isset($rstemp['WIKIPEDIA_IMAGE_PATH']) ? $rstemp['WIKIPEDIA_IMAGE_PATH'] : '';
				$strdeathoverview=isset($rstemp['OVERVIEW']) ? $rstemp['OVERVIEW'] : '';
			}
			f_sqlfreeresult($result);
		}

		if ($intfound) {
			f_headerdisplay($strdeathname,"",f_socialwikidataentity($strdeathname,$strdeathoverview,$strdeathwikipediapath,$strdeathprofilepath));
			echo "<h1>".f_htmlspecialchars($strdeathname)."</h1>";

			if ($strdeathwikipediapath!="") {
				echo "<p><img src=\"".f_htmlattr($strdeathwikipediapath)."\" alt=\"".f_htmlattr($strdeathname)."\" title=\"".f_htmlattr($strdeathname)."\" style=\"background-color: white; max-width: 100%; height: auto;\" /></p>";
			}
			if ($strdeathprofilepath!="") {
				echo "<p><img src=\"".f_htmlattr(f_getpersonprofilepicture($strdeathprofilepath))."\" alt=\"".f_htmlattr($strdeathname)."\" title=\"".f_htmlattr($strdeathname)."\" style=\"background-color: white;\" /></p>";
			}

			if ($strdeathwikidataid!="") {
				echo "<p><strong>".get_movie_string('lib_movie_id_wikidata').":</strong> <a href=\"".f_htmlattr("https://www.wikidata.org/wiki/".$strdeathwikidataid)."\" target=\"_blank\" rel=\"noreferrer\">".f_htmlspecialchars($strdeathwikidataid)."</a> <a href=\"".f_htmlattr("https://www.wikidata.org/wiki/".$strdeathwikidataid)."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/Wikidata-logo.svg.png\" width=\"28\" height=\"16\" alt=\"Wikidata\" title=\"Wikidata\"></a></p>";
			}

			if ($strdeathsource!="") {
				echo "<p><strong>Source:</strong> ".f_htmlspecialchars($strdeathsource)."</p>";
			}
			if ($strdeathtype!="") {
				echo "<p><strong>Type:</strong> ".f_htmlspecialchars($strdeathtype)."</p>";
			}
			if ($strdeathoverview!="") {
				echo "<h2>".get_movie_string('lib_collection_overview')."</h2>";
				echo "<p>".nl2br(f_htmlspecialchars($strdeathoverview))."</p>";
			}
			if ($strdeathwikidataid!="") {
				// Vingt lignes de lecture V1 et un repli de langue
				// remplaces par un appel. ALIASES etait de la donnee morte, elle disparait.
				$strwditeminstanceofid=f_wikidataiteminstanceof_v2($strdeathwikidataid);
				// Le libelle de la classe se lit en V2, repli V1.
				// f_getwikidatalabel fait le meme repli de langue que les deux requetes
				// qu'elle remplace, mais dans V2 d'abord et V1 seulement en dernier ressort.
				$strwditeminstanceoflabel=f_getwikidatalabel($strwditeminstanceofid,$struilang,$arrwdsourcelabel);
				// Diagnostic de la migration, sous $intprivate comme les autres informations de
				// service : visible sous un prefixe prive, invisible sur le site public. Les DEUX langues,
				// pas seulement celle de l interface, car un prefixe prive peut forcer l anglais alors que le francais
				// est justement ce qu on veut valider.
				//Hook entity.wikidata: service blocks of the plugins above the Wikidata section
				echo f_pluginhtml("entity.wikidata",array("page"=>"death","wikidataids"=>array($strdeathwikidataid,$strwditeminstanceofid),"lang"=>$struilang,"langalt"=>$struilangalt));
				echo "<h2>".get_movie_string('lib_wikidata_section')."</h2>";
				$strpropertyurl="property.php?propertyid=P31";
				echo "<p><strong><a href=\"".$strpropertyurl."\">".get_movie_string('lib_wikidata_type')."</a>:</strong> ".$strwditeminstanceoflabel."</p>";
				$strwikidataeverything=f_wikidataallpropertiesv2($struilang,$strdeathwikidataid,", ");
				echo $strwikidataeverything;

				echo "<h2>".get_movie_string('lib_wikipedia_section')."</h2>";
				$strwikipediacontent=f_wikipediallsections($struilang,$strdeathwikidataid,$strsep=", ");
				echo $strwikipediacontent;
			}

			$lngpersoncount = 0;
			$strsqlpersonscount = "SELECT COUNT(DISTINCT J.ID_PERSON) AS COMPTE FROM T_WC_T2S_PERSON_DEATH J WHERE J.ID_DEATH = ".f_longtosql($lngdeathid);
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
			$strsqlpersons .= "JOIN (SELECT ID_PERSON, MIN(DISPLAY_ORDER) AS MIN_DISPLAY_ORDER FROM T_WC_T2S_PERSON_DEATH WHERE ID_DEATH = ".f_longtosql($lngdeathid)." GROUP BY ID_PERSON) JP ";
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
			echo f_pluginhtml("page.beforefooter",array("page"=>"death"));

			f_footerdisplay();
		} else {
			f_headerdisplay("Death Not Found");
			echo "<h1>Death not found.</h1>";
		}
	} else {
		f_headerdisplay("Invalid ID");
		echo "<h1>Invalid death ID.</h1>";
	}
} else {
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}
?>
