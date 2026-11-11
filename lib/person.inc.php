<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
include_once(__DIR__."/movie-sections.inc.php");  

if ($intdbavailable) {
	//Database is available
	$lngpersonid=f_stringtolong(f_request2("personid"));
	$strimdbid=substr(trim(f_request2("imdbid")),0,15);
	$intpersonfound=false;
	
	if ($lngpersonid==0 && $strimdbid!="") {
		$lngpersonid=f_stringtolong(f_descfromcode($strsqlns."TMDB_PERSON","ID_IMDB","ID_PERSON",f_stringtosql($strimdbid),"",""));
		if ($lngpersonid==0) {
			//Person record NOT found in the database
			$strmessage=get_movie_string('lib_person_not_found');
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
			exit;
		}
	}
	if ($lngpersonid>0) {
		//Read person data from the MySQL database
		$strsqlperson="SELECT * FROM ".$strsqlns."TMDB_PERSON WHERE ID_PERSON = ".f_longtosql($lngpersonid)." AND DELETED = 0 AND TIM_CREDITS_COMPLETED IS NOT NULL ";
		$resultperson=f_sqlselect($strsqlperson);
		if ($resultperson) {
			if ($rstempperson=f_sqlresultfetcharray($resultperson)) {
				$intpersonfound=true;
				$strpersonidimdb=$rstempperson["ID_IMDB"];
				$strpersonbiography=$rstempperson['BIOGRAPHY'];
				$strpersonbirthday=substr($rstempperson['BIRTHDAY'],0,10);
				$strpersondeathday=substr($rstempperson['DEATHDAY'],0,10);
				$intpersongender=f_stringtolong($rstempperson['GENDER']);
				$strpersonprofilepath=$rstempperson['PROFILE_PATH'];
				$strpersonhomepage=$rstempperson['HOMEPAGE_URL'];
				$strpersonname=$rstempperson['NAME'];
				$strpersonplaceofbirth=$rstempperson['PLACE_OF_BIRTH'];
				$strpersoncountryofbirth=$rstempperson['COUNTRY_OF_BIRTH'];
				$dblpersonpopularity=f_stringtodouble($rstempperson['POPULARITY']);
				$strpersonknownfordepartment=$rstempperson['KNOWN_FOR_DEPARTMENT'];
				$intpersonadult=f_stringtolong($rstempperson['ADULT']);
				$strpersonalsoknownas="";
				$strpersontimcreditscompleted=$rstempperson['TIM_CREDITS_COMPLETED'];
				$strpersonidwikidata=$rstempperson["ID_WIKIDATA"];
				if ($strpersonidimdb!="") {
					$strpersonimdburl="https://www.imdb.com/name/".$strpersonidimdb."/";
				}
				if ($strpersonidwikidata!="") {
					$strpersonwikidataurl="https://www.wikidata.org/wiki/".$strpersonidwikidata;
				}
				$strpersontmdburl="https://www.themoviedb.org/person/".$lngpersonid;
				$birth = null;
				if ($strpersonbirthday !== '') {
					$birth = DateTimeImmutable::createFromFormat('Y-m-d|', $strpersonbirthday);
					if ($birth === false) {
					    throw new RuntimeException("Invalid birth date '$strpersonbirthday' (expect YYYY-MM-DD).");
					}
				}
				if ($strpersondeathday !== '') {
					// person is deceased
				    $end = DateTimeImmutable::createFromFormat('Y-m-d|', $strpersondeathday);
				    if ($end === false) {
				        throw new RuntimeException("Invalid death date '$strpersondeathday' (expect YYYY-MM-DD).");
				    }
				} else {
					// still alive
					// freezes "today" at midnight
				    $end = new DateTimeImmutable('today');
				}
				$lngpersonage = null;
				if ($birth !== null) {
					if ($end < $birth) {
					    throw new RuntimeException('End date precedes birth date.');
					}
					$lngpersonage = $end->diff($birth)->y;
				}
			}
			f_sqlfreeresult($resultperson);
		}
		$intpersonisvip=false;
		$strsqlpersonalias="SELECT PERSON_NAME, LANGUAGE_FAMILY
FROM ".$strsqlns."TMDB_PERSON_ALSO_KNOWN_AS
WHERE ID_PERSON = ".f_longtosql($lngpersonid)."
AND DELETED = 0
ORDER BY DISPLAY_ORDER, ID_ROW ";
		$resultpersonalias=f_sqlselect($strsqlpersonalias);
		if ($resultpersonalias) {
			$arrpersonalsoknownas=array();
			while ($rstpersonalias=f_sqlresultfetcharray($resultpersonalias)) {
				$strpersonaliasname=trim((string)$rstpersonalias['PERSON_NAME']);
				$strpersonaliaslanguagefamily=trim((string)$rstpersonalias['LANGUAGE_FAMILY']);
				if ($strpersonaliasname!="") {
					if ($strpersonaliaslanguagefamily!="") {
						$arrpersonalsoknownas[]=$strpersonaliasname." (".$strpersonaliaslanguagefamily.")";
					} else {
						$arrpersonalsoknownas[]=$strpersonaliasname;
					}
				}
			}
			$strpersonalsoknownas=implode(", ",$arrpersonalsoknownas);
			f_sqlfreeresult($resultpersonalias);
		}
		$strsqlperson="SELECT * FROM ".$strsqlns."TMDB_PERSON_SEARCH WHERE ID_PERSON = ".f_longtosql($lngpersonid)." AND DELETED = 0 ";
		//echo "<br />\$strsqlperson=$strsqlperson";
		$resultperson=f_sqlselect($strsqlperson);
		if ($resultperson) {
			if ($rstempperson=f_sqlresultfetcharray($resultperson)) {
				$intpersonisvip=true;
			}
			f_sqlfreeresult($resultperson);
		}
		if ($strpersonidwikidata!="") {
			$strpersoninstanceof="";
			//Read person wikidata data from the MySQL database
			$strsqlperson="SELECT * FROM ".$strsqlns."WIKIDATA_PERSON_V1 WHERE ID_WIKIDATA = ".f_stringtosql($strpersonidwikidata)." AND DELETED = 0 ";
			$resultperson=f_sqlselect($strsqlperson);
			if ($resultperson) {
				if ($rstempperson=f_sqlresultfetcharray($resultperson)) {
					$strpersoninstanceof=trim($rstempperson['INSTANCE_OF']);
					$strpersonaliases=trim($rstempperson['ALIASES']);
				}
				f_sqlfreeresult($resultperson);
			}
			
			$strpersonwikidatainstanceof="";
			// Le libelle de la classe se lit en V2, repli V1.
			// La requete remplacee n avait aucun repli vers l anglais : la bascule en ajoute
			// un, donc cette page peut afficher un type la ou elle rendait du vide.
			$strpersonwikidatainstanceof=f_getwikidatalabel($strpersoninstanceof,$struilang);
			$strpersonwikidataeverything=f_wikidataallpropertiesv2($struilang,$strpersonidwikidata,", ","");
			//P463,P108,P166,P1411,P509,P1196
		}
		
		if ($intpersonfound) {
			//Person record found in the database
			//Display person data
			//Social preview: the profile picture is the visual shown when the page URL is shared
			$arrsocialperson=array();
			$arrsocialperson['description']=$strpersonbiography;
			if ($strpersonprofilepath!="") {
				$arrsocialperson['image']=f_socialimageurl($strtmdbimagespathbaseurl.$strpersonprofilepath);
				//TMDb profile pictures are 2:3, so the w342 rendition is 342x513
				$arrsocialperson['imagewidth']=342;
				$arrsocialperson['imageheight']=513;
				$arrsocialperson['imagealt']=$strpersonname;
			}
			f_headerdisplay($strpersonname,"",$arrsocialperson);
			//echo "<h1><a href=\"person-search.php\">".get_movie_string('lib_person_search')."</a></h1>";
			echo "<h1>".$strpersonname."</h1>";
			if ($strpersonknownfordepartment!="") {
				echo "<h2>".$strpersonknownfordepartment."</h2>";
			}
			if ($strpersonprofilepath!="") {
				echo "<p>";
				echo "<a href=\"".$strtmdbimagesoriginalpathbaseurl.$strpersonprofilepath."\" target=\"_blank\" rel=\"noreferrer\">";
				echo "<img src=\"".$strtmdbimagespathbaseurl.$strpersonprofilepath."\" alt=\"".$strpersonname."\" title=\"".$strpersonname."\" />";
				echo "</a>";
				echo "</p>";
			}
			echo "<p>";
			echo "<a href=\"images.php?personid=".$lngpersonid."\">";
			echo get_movie_string('lib_person_all_images');
			echo "</a>";
			echo "</p>";

			f_moviesectionreset(array("info","biography","cast","crew","ids","wikidata","wikipedia"));
			ob_start();
			echo f_moviesectionopen("info","🪪",($struilang!="en"?"Informations":"Information"),1);

			if ($strpersonbirthday!="") {
				echo "<p><strong>".get_movie_string('lib_person_birth').":</strong> ".$strpersonbirthday."</p>";
			}
			if ($strpersondeathday!="") {
				echo "<p><strong>".get_movie_string('lib_person_death').":</strong> ".$strpersondeathday."</p>";
			}
			if ($lngpersonage !== null) {
				echo "<p><strong>".get_movie_string('lib_person_age').":</strong> ".$lngpersonage." ".get_movie_string('lib_person_age_years')."</p>";
			}
			if ($intpersongender > 0) {
				echo "<p><strong>".get_movie_string('lib_person_gender').":</strong> ";
				if ($intpersongender == 1) {
					echo get_movie_string('lib_person_gender_woman');
				} else if ($intpersongender == 2) {
					echo get_movie_string('lib_person_gender_man');
				}
				echo "</p>";
			}
			if ($strpersonplaceofbirth != "") {
				echo "<p><strong>".get_movie_string('lib_person_place_of_birth').":</strong> ".$strpersonplaceofbirth."</p>";
			}
			if ($strpersoncountryofbirth != "") {
				echo "<p><strong>".get_movie_string('lib_person_country_of_birth').":</strong> ".$strpersoncountryofbirth."</p>";
			}
			if ($strpersonalsoknownas != "") {
				echo "<p><strong>".get_movie_string('lib_person_also_known_as').":</strong> ".$strpersonalsoknownas."</p>";
			}
			if ($strpersonhomepage != "") {
				echo "<p><strong>".get_movie_string('lib_person_homepage').":</strong> <a href=\"".$strpersonhomepage."\" target=\"_blank\" rel=\"noreferrer\">".$strpersonhomepage."</a></p>";
			}
			echo "<p><strong>".get_movie_string('lib_person_popularity').":</strong> ".$dblpersonpopularity."</p>";
			
			if ($intpersonadult) {
				$strpersonadult=get_movie_string('lib_yes');
			} else {
				$strpersonadult=get_movie_string('lib_no');
			}
			echo "<p><strong>".get_movie_string('lib_person_is_adult').":</strong> ".$strpersonadult;
			echo "</p>";
			
			$strt2sgroups="";
			$strsqlt2sgroups="SELECT T_WC_T2S_GROUP.ID_GROUP, T_WC_T2S_GROUP.GROUP_NAME, T_WC_T2S_GROUP.GROUP_NAME_FR ";
			$strsqlt2sgroups.="FROM T_WC_T2S_PERSON_GROUP ";
			$strsqlt2sgroups.="INNER JOIN T_WC_T2S_GROUP ON T_WC_T2S_PERSON_GROUP.ID_GROUP = T_WC_T2S_GROUP.ID_GROUP ";
			$strsqlt2sgroups.="WHERE T_WC_T2S_PERSON_GROUP.ID_PERSON = ".f_longtosql($lngpersonid)." AND T_WC_T2S_PERSON_GROUP.DELETED = 0 AND T_WC_T2S_GROUP.DELETED = 0 ";
			$strsqlt2sgroups.="ORDER BY T_WC_T2S_PERSON_GROUP.DISPLAY_ORDER, T_WC_T2S_GROUP.GROUP_NAME ";
			$resultt2sgroups=f_sqlselect($strsqlt2sgroups);
			if ($resultt2sgroups) {
				while ($rstempgroup=f_sqlresultfetcharray($resultt2sgroups)) {
					$strt2sgroupid=$rstempgroup["ID_GROUP"];
					$strt2sgroupname=trim($rstempgroup[($struilang!="en" && trim((string)$rstempgroup["GROUP_NAME_FR"])!="")?"GROUP_NAME_FR":"GROUP_NAME"]);
					if ($strt2sgroupname=="") {
						continue;
					}
					if ($strt2sgroups!="") {
						$strt2sgroups.=", ";
					}
					$strt2sgroups.="<a href=\"group.php?groupid=".$strt2sgroupid."\">".$strt2sgroupname."</a>";
				}
				f_sqlfreeresult($resultt2sgroups);
			}
			if ($strt2sgroups!="") {
				echo "<p><strong>".get_movie_string('lib_person_groups').":</strong> ".$strt2sgroups."</p>";
			}

			$strt2sdeaths="";
			$strsqlt2sdeaths="SELECT T_WC_T2S_DEATH.ID_DEATH, T_WC_T2S_DEATH.DEATH_NAME, T_WC_T2S_DEATH.DEATH_NAME_FR ";
			$strsqlt2sdeaths.="FROM T_WC_T2S_PERSON_DEATH ";
			$strsqlt2sdeaths.="INNER JOIN T_WC_T2S_DEATH ON T_WC_T2S_PERSON_DEATH.ID_DEATH = T_WC_T2S_DEATH.ID_DEATH ";
			$strsqlt2sdeaths.="WHERE T_WC_T2S_PERSON_DEATH.ID_PERSON = ".f_longtosql($lngpersonid)." AND T_WC_T2S_PERSON_DEATH.DELETED = 0 AND T_WC_T2S_DEATH.DELETED = 0 ";
			$strsqlt2sdeaths.="ORDER BY T_WC_T2S_PERSON_DEATH.DISPLAY_ORDER, T_WC_T2S_DEATH.DEATH_NAME ";
			$resultt2sdeaths=f_sqlselect($strsqlt2sdeaths);
			if ($resultt2sdeaths) {
				while ($rstempdeath=f_sqlresultfetcharray($resultt2sdeaths)) {
					$strt2sdeathid=$rstempdeath["ID_DEATH"];
					$strt2sdeathname=trim($rstempdeath[($struilang!="en" && trim((string)$rstempdeath["DEATH_NAME_FR"])!="")?"DEATH_NAME_FR":"DEATH_NAME"]);
					if ($strt2sdeathname=="") {
						continue;
					}
					if ($strt2sdeaths!="") {
						$strt2sdeaths.=", ";
					}
					$strt2sdeaths.="<a href=\"death.php?deathid=".$strt2sdeathid."\">".$strt2sdeathname."</a>";
				}
				f_sqlfreeresult($resultt2sdeaths);
			}
			if ($strt2sdeaths!="") {
				echo "<p><strong>".get_movie_string('lib_person_deaths').":</strong> ".$strt2sdeaths."</p>";
			}

			$strt2sawards="";
			// One link row per win, so an award won several times is grouped into one line.
			$strsqlt2sawards="SELECT T_WC_T2S_AWARD.ID_AWARD, T_WC_T2S_AWARD.AWARD_NAME, T_WC_T2S_AWARD.AWARD_NAME_FR, MIN(T_WC_T2S_PERSON_AWARD.DISPLAY_ORDER) AS MIN_DISPLAY_ORDER ";
			$strsqlt2sawards.="FROM T_WC_T2S_PERSON_AWARD ";
			$strsqlt2sawards.="INNER JOIN T_WC_T2S_AWARD ON T_WC_T2S_PERSON_AWARD.ID_AWARD = T_WC_T2S_AWARD.ID_AWARD ";
			$strsqlt2sawards.="WHERE T_WC_T2S_PERSON_AWARD.ID_PERSON = ".f_longtosql($lngpersonid)." AND T_WC_T2S_PERSON_AWARD.DELETED = 0 AND T_WC_T2S_AWARD.DELETED = 0 ";
			$strsqlt2sawards.="GROUP BY T_WC_T2S_AWARD.ID_AWARD, T_WC_T2S_AWARD.AWARD_NAME, T_WC_T2S_AWARD.AWARD_NAME_FR ";
			$strsqlt2sawards.="ORDER BY MIN_DISPLAY_ORDER, T_WC_T2S_AWARD.AWARD_NAME ";
			$resultt2sawards=f_sqlselect($strsqlt2sawards);
			if ($resultt2sawards) {
				while ($rstempaward=f_sqlresultfetcharray($resultt2sawards)) {
					$strt2sawardid=$rstempaward["ID_AWARD"];
					$strt2sawardname=trim($rstempaward[($struilang!="en" && trim((string)$rstempaward["AWARD_NAME_FR"])!="")?"AWARD_NAME_FR":"AWARD_NAME"]);
					if ($strt2sawardname=="") {
						continue;
					}
					if ($strt2sawards!="") {
						$strt2sawards.=", ";
					}
					$strt2sawards.="<a href=\"award.php?awardid=".$strt2sawardid."\">".$strt2sawardname."</a>";
				}
				f_sqlfreeresult($resultt2sawards);
			}
			if ($strt2sawards!="") {
				echo "<p><strong>".get_movie_string('lib_person_awards').":</strong> ".$strt2sawards."</p>";
			}

			$strt2snominations="";
			$strsqlt2snominations="SELECT T_WC_T2S_NOMINATION.ID_NOMINATION, T_WC_T2S_NOMINATION.NOMINATION_NAME, T_WC_T2S_NOMINATION.NOMINATION_NAME_FR ";
			$strsqlt2snominations.="FROM T_WC_T2S_PERSON_NOMINATION ";
			$strsqlt2snominations.="INNER JOIN T_WC_T2S_NOMINATION ON T_WC_T2S_PERSON_NOMINATION.ID_NOMINATION = T_WC_T2S_NOMINATION.ID_NOMINATION ";
			$strsqlt2snominations.="WHERE T_WC_T2S_PERSON_NOMINATION.ID_PERSON = ".f_longtosql($lngpersonid)." AND T_WC_T2S_PERSON_NOMINATION.DELETED = 0 AND T_WC_T2S_NOMINATION.DELETED = 0 ";
			$strsqlt2snominations.="ORDER BY T_WC_T2S_PERSON_NOMINATION.DISPLAY_ORDER, T_WC_T2S_NOMINATION.NOMINATION_NAME ";
			$resultt2snominations=f_sqlselect($strsqlt2snominations);
			if ($resultt2snominations) {
				while ($rstempnomination=f_sqlresultfetcharray($resultt2snominations)) {
					$strt2snominationid=$rstempnomination["ID_NOMINATION"];
					$strt2snominationname=trim($rstempnomination[($struilang!="en" && trim((string)$rstempnomination["NOMINATION_NAME_FR"])!="")?"NOMINATION_NAME_FR":"NOMINATION_NAME"]);
					if ($strt2snominationname=="") {
						continue;
					}
					if ($strt2snominations!="") {
						$strt2snominations.=", ";
					}
					$strt2snominations.="<a href=\"nomination.php?nominationid=".$strt2snominationid."\">".$strt2snominationname."</a>";
				}
				f_sqlfreeresult($resultt2snominations);
			}
			if ($strt2snominations!="") {
				echo "<p><strong>".get_movie_string('lib_person_nominations').":</strong> ".$strt2snominations."</p>";
			}
			// Pas de section lieux sur la fiche personne, et c est un choix, pas un oubli
			//. P915 (lieu de tournage) et P840 (lieu du recit) sont des
			// proprietes d oeuvre : Wikidata les pose sur un film ou une serie, jamais sur une
			// personne. Il n existe donc pas de T_WC_T2S_PERSON_LOCATION et aucune n est prevue.
			// Le lieu de naissance ($strpersonplaceofbirth) est une autre donnee, portee par la
			// fiche TMDb de la personne et sans rattachement a T_WC_T2S_LOCATION.
			
			//Hook entity.admin: maintenance lines of the plugins
			echo f_pluginhtml("entity.admin",array("type"=>"person","id"=>$lngpersonid,"vip"=>$intpersonisvip));
			echo "<p><strong>".get_movie_string('lib_person_record_updated').":</strong> ".$strpersontimcreditscompleted;
			//echo " - <a href=\"person-load.php?personid=".$lngpersonid."\">Refresh credits</a>";
			//Hook entity.actions: action links of the plugins on the record status line
			echo f_pluginhtml("entity.actions",array("type"=>"person","id"=>$lngpersonid,"slot"=>"record"));
			echo "</p>";
			//Hook entity.status: lines of the plugins under the record status
			echo f_pluginhtml("entity.status",array("type"=>"person","id"=>$lngpersonid));
			echo f_moviesectionopen("ids","🔗",($struilang!="en"?"Identifiants externes":"External IDs"));
			//if ($intprivate) {
				echo "<p><strong>".get_movie_string('lib_person_id_tmdb').":</strong> ";
				if ($lngpersonid>0) {
					echo "<a href=\"".$strpersontmdburl."\" target=\"_blank\" rel=\"noreferrer\">";
				}
				echo $lngpersonid;
				if ($lngpersonid>0) {
					echo "</a>";
				}
				if ($lngpersonid>0) {
					echo " <a href=\"".$strpersontmdburl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/tmdb-icon.webp\" width=\"103\" height=\"16\" alt=\"TMDb\" title=\"TMDb\"></a>";
				}
				echo "</p>";
			//}
			//if ($intprivate) {
				//echo "<p><strong>ID Wikidata:</strong> ".$strpersonidwikidata."</p>";
				echo "<p><strong>".get_movie_string('lib_person_id_imdb').":</strong> ";
				if ($strpersonidimdb!="") {
					echo "<a href=\"".$strpersonimdburl."\" target=\"_blank\" rel=\"noreferrer\">";
				}
				echo $strpersonidimdb;
				if ($strpersonidimdb!="") {
					echo "</a>";
				}
				if ($strpersonidimdb!="") {
					echo " <a href=\"".$strpersonimdburl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"images/IMDb-icon.png\" width=\"31\" height=\"16\" alt=\"IMDb\" title=\"IMDb\"></a>";
				}
				echo "</p>";
				echo "<p><strong>".get_movie_string('lib_person_id_wikidata').":</strong> ";
				if ($strpersonidwikidata!="") {
					echo "<a href=\"".$strpersonwikidataurl."\" target=\"_blank\" rel=\"noreferrer\">";
				}
				echo $strpersonidwikidata;
				if ($strpersonidwikidata!="") {
					echo "</a>";
				}
				if ($strpersonidwikidata!="") {
					echo " <a href=\"".$strpersonwikidataurl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/Wikidata-logo.svg.png\" width=\"28\" height=\"16\" alt=\"Wikidata\" title=\"Wikidata\"></a>";
				}
				echo "</p>";
				echo f_wikipediaidline($struilang,$strpersonidwikidata);
			//}
			if ($strpersonbiography!="") {
				echo f_moviesectionopen("biography","📝",get_movie_string('lib_person_biography'),1);
				echo "<p>".str_replace("\r","<br />",str_replace("\n","<br />",$strpersonbiography))."</p>";
			}
			
			echo f_moviesectionopen("wikidata","🧬",get_movie_string('lib_person_wikidata'));
			echo f_wikidatamediasection_v2($struilang,$strpersonidwikidata);
			$strpropertyurl="property.php?propertyid=P31";
			echo "<p><strong><a href=\"".$strpropertyurl."\">".get_movie_string('lib_person_type')."</a>:</strong> ".$strpersonwikidatainstanceof."</p>";
			if ($strpersonaliases!="") {
				echo "<p><strong>".get_movie_string('lib_person_aliases').":</strong> ".$strpersonaliases."</p>";
			}
			
			echo $strpersonwikidataeverything;
			
			echo f_moviesectionopen("wikipedia","📖",get_movie_string('lib_person_wikipedia'));
			$strpersonwikipediacontent=f_wikipediallsections($struilang,$strpersonidwikidata,$strsep=", ");
			echo $strpersonwikipediacontent;
			
			for ($intcredittype=1;$intcredittype<=2;$intcredittype++) {
				switch ($intcredittype) {
				case 1:
					//Cast
					$strtitle=get_movie_string('lib_person_cast');
					$strpersoncreditcredittype="cast";
					$strsqlpersonmovie="
SELECT T_WC_TMDB_PERSON_MOVIE.ID_MOVIE AS ID_CONTENT, 
T_WC_TMDB_PERSON_MOVIE.CAST_CHARACTER, 
T_WC_TMDB_MOVIE.TITLE AS TITLE, 
T_WC_TMDB_MOVIE.ORIGINAL_TITLE, 
T_WC_TMDB_MOVIE.POSTER_PATH, 
T_WC_TMDB_MOVIE.DAT_RELEASE AS DAT_RELEASE, 
'' AS DAT_LAST_AIR, 
T_WC_TMDB_MOVIE.ID_IMDB, 
T_WC_IMDB_MOVIE_RATING_IMPORT.averageRating,
T_WC_IMDB_MOVIE_RATING_IMPORT.numVotes,
'movie' AS CONTENT_TYPE 
FROM T_WC_TMDB_PERSON_MOVIE
INNER JOIN T_WC_TMDB_MOVIE ON T_WC_TMDB_PERSON_MOVIE.ID_MOVIE = T_WC_TMDB_MOVIE.ID_MOVIE
LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_MOVIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst 
WHERE T_WC_TMDB_PERSON_MOVIE.ID_PERSON = ".f_longtosql($lngpersonid)." 
AND T_WC_TMDB_PERSON_MOVIE.CREDIT_TYPE = ".f_stringtosql($strpersoncreditcredittype)." 
UNION 
SELECT T_WC_TMDB_PERSON_SERIE.ID_SERIE AS ID_CONTENT, 
T_WC_TMDB_PERSON_SERIE.CAST_CHARACTER, 
T_WC_TMDB_SERIE.TITLE AS TITLE, 
T_WC_TMDB_SERIE.ORIGINAL_TITLE, 
T_WC_TMDB_SERIE.POSTER_PATH, 
T_WC_TMDB_SERIE.DAT_FIRST_AIR AS DAT_RELEASE, 
T_WC_TMDB_SERIE.DAT_LAST_AIR, 
T_WC_TMDB_SERIE.ID_IMDB, 
T_WC_IMDB_MOVIE_RATING_IMPORT.averageRating,
T_WC_IMDB_MOVIE_RATING_IMPORT.numVotes,
'serie' AS CONTENT_TYPE 
FROM T_WC_TMDB_PERSON_SERIE
INNER JOIN T_WC_TMDB_SERIE ON T_WC_TMDB_PERSON_SERIE.ID_SERIE = T_WC_TMDB_SERIE.ID_SERIE
LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_SERIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst 
WHERE T_WC_TMDB_PERSON_SERIE.ID_PERSON = ".f_longtosql($lngpersonid)." 
AND T_WC_TMDB_PERSON_SERIE.CREDIT_TYPE = ".f_stringtosql($strpersoncreditcredittype)." 
ORDER BY DAT_RELEASE DESC, TITLE ;";
					break;
				case 2:
					//Crew
					$strtitle=get_movie_string('lib_person_crew');;
					$strpersoncreditcredittype="crew";
					$strsqlpersonmovie="
SELECT T_WC_TMDB_PERSON_MOVIE.ID_MOVIE AS ID_CONTENT, 
T_WC_TMDB_PERSON_MOVIE.CREW_DEPARTMENT, 
T_WC_TMDB_PERSON_MOVIE.CREW_JOB, 
T_WC_TMDB_MOVIE.TITLE AS TITLE, 
T_WC_TMDB_MOVIE.ORIGINAL_TITLE, 
T_WC_TMDB_MOVIE.POSTER_PATH, 
T_WC_TMDB_MOVIE.DAT_RELEASE AS DAT_RELEASE, 
'' AS DAT_LAST_AIR, 
T_WC_TMDB_MOVIE.ID_IMDB, 
T_WC_IMDB_MOVIE_RATING_IMPORT.averageRating,
T_WC_IMDB_MOVIE_RATING_IMPORT.numVotes,
'movie' AS CONTENT_TYPE 
FROM T_WC_TMDB_PERSON_MOVIE
INNER JOIN T_WC_TMDB_MOVIE ON T_WC_TMDB_PERSON_MOVIE.ID_MOVIE = T_WC_TMDB_MOVIE.ID_MOVIE 
LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_MOVIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst 
WHERE T_WC_TMDB_PERSON_MOVIE.ID_PERSON = ".f_longtosql($lngpersonid)." 
AND T_WC_TMDB_PERSON_MOVIE.CREDIT_TYPE = ".f_stringtosql($strpersoncreditcredittype)." 
UNION 
SELECT T_WC_TMDB_PERSON_SERIE.ID_SERIE AS ID_CONTENT, 
T_WC_TMDB_PERSON_SERIE.CREW_DEPARTMENT, 
T_WC_TMDB_PERSON_SERIE.CREW_JOB, 
T_WC_TMDB_SERIE.TITLE AS TITLE, 
T_WC_TMDB_SERIE.ORIGINAL_TITLE, 
T_WC_TMDB_SERIE.POSTER_PATH, 
T_WC_TMDB_SERIE.DAT_FIRST_AIR AS DAT_RELEASE, 
T_WC_TMDB_SERIE.DAT_LAST_AIR, 
T_WC_TMDB_SERIE.ID_IMDB, 
T_WC_IMDB_MOVIE_RATING_IMPORT.averageRating,
T_WC_IMDB_MOVIE_RATING_IMPORT.numVotes,
'serie' AS CONTENT_TYPE 
FROM T_WC_TMDB_PERSON_SERIE
INNER JOIN T_WC_TMDB_SERIE ON T_WC_TMDB_PERSON_SERIE.ID_SERIE = T_WC_TMDB_SERIE.ID_SERIE 
LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_SERIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst 
WHERE T_WC_TMDB_PERSON_SERIE.ID_PERSON = ".f_longtosql($lngpersonid)." 
AND T_WC_TMDB_PERSON_SERIE.CREDIT_TYPE = ".f_stringtosql($strpersoncreditcredittype)." 
ORDER BY DAT_RELEASE DESC, TITLE ;";
					break;
				}
				echo f_moviesectionopen(($intcredittype==1?"cast":"crew"),($intcredittype==1?"🎭":"🛠️"),$strtitle);
				echo "<table>";
				echo "<tr>";
				//Remembering previous movie/serie id so we can manage several credits for the same movie/serie
				$lngcontentidold=0;
				$strcontenttypeold="";
				$strrowsuffix="";
				$intpersonmoviefound=false;
				$lngrowindex=0;
				//echo "<br />$strsqlpersonmovie";
				$resultpersonmovie=f_sqlselect($strsqlpersonmovie);
				if ($resultpersonmovie) {
					while ($rstemppersonmovie=f_sqlresultfetcharray($resultpersonmovie)) {
						$intpersonmoviefound=true;
						
						$lngcontentid=f_stringtolong($rstemppersonmovie['ID_CONTENT']);
						$strcontenttype=$rstemppersonmovie['CONTENT_TYPE'];
						$strmovietitle=$rstemppersonmovie['TITLE'];
						$strmovieoriginaltitle=$rstemppersonmovie['ORIGINAL_TITLE'];
						$strmovieposterpath=$rstemppersonmovie['POSTER_PATH'];
						$strmovieposterfullpath=f_getmovieposterpicture($strmovieposterpath,2);
						$strpersoncreditreleasedate=$rstemppersonmovie['DAT_RELEASE'];
						$strpersoncreditreleaseyear=substr($strpersoncreditreleasedate,0,4);
						$strpersoncreditdatlastair=$rstemppersonmovie['DAT_LAST_AIR'];
						if ($strpersoncreditdatlastair!="") {
							$strpersoncreditlastairyear=substr($strpersoncreditdatlastair,0,4);
						} else {
							$strpersoncreditlastairyear="";
						}
						if ($strcontenttype=="serie") {
							$lngmovieid=0;
							$lngserieid=$lngcontentid;
							$strlangtable=$strsqlns."TMDB_SERIE_LANG";
							$strkeyfieldname="ID_SERIE";
							$strmovieurl="serie.php?serieid=".$lngserieid;
						} else {
							$lngmovieid=$lngcontentid;
							$lngserieid=0;
							$strlangtable=$strsqlns."TMDB_MOVIE_LANG";
							$strkeyfieldname="ID_MOVIE";
							$strmovieurl="movie.php?movieid=".$lngmovieid;
						}
						if ($strcontenttype=="serie") {
							if ($struilang!="en") {
								//Read translated movie data from the MySQL database
								$strsqlserie="SELECT * FROM ".$strlangtable." WHERE ".$strkeyfieldname." = ".f_longtosql($lngcontentid)." AND LANG = ".f_stringtosql($struilang)." AND DELETED = 0 ";
								$resultserie=f_sqlselect($strsqlserie);
								if ($resultserie) {
									if ($rstempserie=f_sqlresultfetcharray($resultserie)) {
										$strmovietitle=$rstempserie['TITLE'];
									}
									f_sqlfreeresult($resultserie);
								}
							}
							$strmovietitleyear=$strmovietitle." (".$strpersoncreditreleaseyear."-".$strpersoncreditlastairyear.")";
						} else {
							if ($struilang!="en") {
								//Read translated movie data from the MySQL database
								$strsqlmovie="SELECT * FROM ".$strlangtable." WHERE ".$strkeyfieldname." = ".f_longtosql($lngcontentid)." AND LANG = ".f_stringtosql($struilang)." AND DELETED = 0 ";
								$resultmovie=f_sqlselect($strsqlmovie);
								if ($resultmovie) {
									if ($rstempmovie=f_sqlresultfetcharray($resultmovie)) {
										$strmovietitle=$rstempmovie['TITLE'];
									}
									f_sqlfreeresult($resultmovie);
								}
							}
							$strmovietitleyear=$strmovietitle." (".$strpersoncreditreleaseyear.")";
						}
						$strmovieidimdb=trim($rstemppersonmovie['ID_IMDB']);
						$dblaveragerating=$rstemppersonmovie['averageRating'];
						$lngimdbvotes=$rstemppersonmovie['numVotes'];
						if ($intdebug) {
							echo "<br />-------------------------";
							echo "<br />\$lngcontentidold=$lngcontentidold";
							echo "<br />\$lngcontentid=$lngcontentid";
							echo "<br />\$strcontenttypeold=$strcontenttypeold";
							echo "<br />\$strcontenttype=$strcontenttype";
						}
						if ($lngcontentidold!=$lngcontentid || $strcontenttypeold!=$strcontenttype) {
							//New movie/serie, so we finish the previous movie/serie
							//What the plugins print under the row we just finished
							if ($intdebug) {
								echo "<br />New movie/serie";
							}
							echo $strrowsuffix;
							if ($lngrowindex>0) {
								//Not the first movie so we end the previous table row
								echo "</td>";
								echo "</tr>";
								echo "<tr>";
							}
							$lngrowindex++;
							echo "<td valign=\"top\">";
							echo "<a href=\"".$strmovieurl."\">";
							echo "<img src=\"".$strmovieposterfullpath."\" alt=\"".$strmovietitleyear."\" title=\"".$strmovietitleyear."\" style=\"background-color: white;\" >";
							echo "</a>";
							echo "</td>";
							echo "<td valign=\"top\">";
						}
						
						switch ($intcredittype) {
						case 1:
							//Cast
							$strpersoncreditcharacter=$rstemppersonmovie['CAST_CHARACTER'];
							$strpersoncreditdepartment="";
							$strpersoncreditjob="";
							if ($lngcontentidold==$lngcontentid) {
								//Same movie/serie with different credits
								echo ", ".$strpersoncreditcharacter;
							} else {
								//New movie/serie
								//Hook list.row.prefix: badges and links of the plugins before the title
								echo f_pluginhtml("list.row.prefix",array("type"=>($strcontenttype=="serie" ? "serie" : "movie"),"id"=>($strcontenttype=="serie" ? $lngserieid : $lngmovieid),"title"=>$strmovietitle,"originaltitle"=>$strmovieoriginaltitle,"year"=>$strpersoncreditreleaseyear,"imdbid"=>$strmovieidimdb,"context"=>"filmography"));
								echo "<strong><a href=\"".$strmovieurl."\">".$strmovietitleyear."</a></strong>";
								if ($strmovieoriginaltitle!=$strmovietitle) {
									if ($strcontenttype=="serie") {
										$strmovietitleyear=$strmovieoriginaltitle." (".$strpersoncreditreleaseyear."-".$strpersoncreditlastairyear.")";
									} else {
										$strmovietitleyear=$strmovieoriginaltitle." (".$strpersoncreditreleaseyear.")";
									}
									echo " AKA <strong><a href=\"".$strmovieurl."\">".$strmovietitleyear."</a></strong>";
								}
								if ($strmovieidimdb!="") {
									echo " <a href=\"https://www.imdb.com/title/".$strmovieidimdb."/\" target=\"_blank\" rel=\"noreferrer\"><img src=\"images/IMDb-icon.png\" width=\"31\" height=\"16\" alt=\"IMDb\" title=\"IMDb\"></a>";
									//Same treatment as the shared card renderers: the vote count rides inside
									//the value, and an unrated title drops the span instead of showing an empty one
									$strimdbratingcard=f_imdbratingdisplay($dblaveragerating,$lngimdbvotes);
									if ($strimdbratingcard!="") {
										echo " <span alt=\"IMDb rating\" title=\"IMDb rating\">".$strimdbratingcard."</span>";
									}
								}
								echo " : ".$strpersoncreditcharacter;
							}
							break;
						case 2:
							//Crew
							$strpersoncreditcharacter="";
							$strpersoncreditdepartment=$rstemppersonmovie['CREW_DEPARTMENT'];
							$strpersoncreditjob=$rstemppersonmovie['CREW_JOB'];
							if ($lngcontentidold==$lngcontentid) {
								//Same movie/serie with different credits
								echo ", ".$strpersoncreditjob;
							} else {
								//New movie/serie
								//Hook list.row.prefix: badges and links of the plugins before the title
								echo f_pluginhtml("list.row.prefix",array("type"=>($strcontenttype=="serie" ? "serie" : "movie"),"id"=>($strcontenttype=="serie" ? $lngserieid : $lngmovieid),"title"=>$strmovietitle,"originaltitle"=>$strmovieoriginaltitle,"year"=>$strpersoncreditreleaseyear,"imdbid"=>$strmovieidimdb,"context"=>"filmography"));
								echo "<strong><a href=\"".$strmovieurl."\">".$strmovietitleyear."</a></strong>";
								if ($strmovieoriginaltitle!=$strmovietitle) {
									if ($strcontenttype=="serie") {
										$strmovietitleyear=$strmovieoriginaltitle." (".$strpersoncreditreleaseyear."-".$strpersoncreditlastairyear.")";
									} else {
										$strmovietitleyear=$strmovieoriginaltitle." (".$strpersoncreditreleaseyear.")";
									}
									echo " AKA <strong><a href=\"".$strmovieurl."\">".$strmovietitleyear."</a></strong>";
								}
								if ($strmovieidimdb!="") {
									echo " <a href=\"https://www.imdb.com/title/".$strmovieidimdb."/\" target=\"_blank\" rel=\"noreferrer\"><img src=\"images/IMDb-icon.png\" width=\"31\" height=\"16\" alt=\"IMDb\" title=\"IMDb\"></a>";
									//Same treatment as the shared card renderers: the vote count rides inside
									//the value, and an unrated title drops the span instead of showing an empty one
									$strimdbratingcard=f_imdbratingdisplay($dblaveragerating,$lngimdbvotes);
									if ($strimdbratingcard!="") {
										echo " <span alt=\"IMDb rating\" title=\"IMDb rating\">".$strimdbratingcard."</span>";
									}
								}
								echo " : ".$strpersoncreditjob;
							}
							break;
						}
						
						if ($lngcontentidold!=$lngcontentid || $strcontenttypeold!=$strcontenttype) {
							//New movie/serie
							//Hook list.row.suffix: what the plugins print under the row, kept until the row ends
							$strrowsuffix=f_pluginhtml("list.row.suffix",array("type"=>($strcontenttype=="serie" ? "serie" : "movie"),"id"=>($strcontenttype=="serie" ? $lngserieid : $lngmovieid),"title"=>$strmovietitle,"originaltitle"=>$strmovieoriginaltitle,"year"=>$strpersoncreditreleaseyear,"imdbid"=>$strmovieidimdb,"context"=>"filmography"));
						}
						$lngcontentidold=$lngcontentid;
						$strcontenttypeold=$strcontenttype;
					}
					if (1) {
						echo "<br />";
						echo $strrowsuffix;
					}
					f_sqlfreeresult($resultpersonmovie);
				}
				echo "</tr>";
				echo "</table>";
			}
			echo f_moviesectionclose();
			//Hook page.beforefooter: blocks of the plugins at the end of the page
			echo f_pluginhtml("page.beforefooter",array("page"=>"person"));
			// Noyau deja emis hors buffer ; on prepend la barre d'ancres au corps bufferise.
			$strfichebody=ob_get_clean();
			echo f_movieanchorbar();
			echo $strfichebody;
			f_footerdisplay();
		} else {
			//Person record NOT found in the database
			
			$strmessage=get_movie_string('lib_person_not_found');
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
			
			//So we redirect to the person-load.php script to read data from the TMDb API 
			//$strredirectscript="person-load.php?personid=".$lngpersonid;
			//header("Location: ".$strredirectscript);
		}
	}  else {
		//personid URL parameter not set
		$strmessage=get_movie_string('lib_person_id_not_set');
		f_headerdisplay($strmessage);
		echo "<h1>".$strmessage."</h1>";
	}
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
