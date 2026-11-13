<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
include_once(__DIR__."/movie-sections.inc.php");

if ($intdbavailable) {
	$lngserieid=f_stringtolong(f_request2("serieid"));
	$lngseasonnumber=f_stringtolong(f_request2("season"));
	$lngepisodenumber=f_stringtolong(f_request2("episode"));
	$intepisodefound=false;
	$lngseasonid=0;
	$lngepisodeid=0;
	$strserietitle="";
	$strserieseasonurl="";
	$strepisodetitle="";
	$strepisodeoverview="";
	$strepisodedatair="";
	$lngepisoderuntime=0;
	$strepisodeproductioncode="";
	$strepisodeepisodetype="";
	$strepisodestillpath="";
	$dblepisodevoteaverage=0;
	$lngepisodevotecount=0;
	$dblepisodeimdbrating=0;
	$lngepisodeimdbvotes=0;
	$strepisodeidimdb="";
	$strepisodeidwikidata="";
	$lngepisodeidtvdb=0;
	$strepisodetimcreditscompleted="";

	if ($lngserieid<=0 || $lngseasonnumber<0 || $lngepisodenumber<=0) {
		$strmessage=get_movie_string('lib_episode_not_found_db');
		f_headerdisplay($strmessage);
		echo "<h1>".$strmessage."</h1>";
		exit;
	}

	$strsqlserie="SELECT TITLE FROM ".$strsqlns."TMDB_SERIE WHERE ID_SERIE = ".f_longtosql($lngserieid)." AND DELETED = 0 ";
	$strserietitle=trim(f_fieldfromquery($strsqlserie));
	if ($struilang!="en") {
		$strsqlserielang="SELECT TITLE FROM ".$strsqlns."TMDB_SERIE_LANG WHERE ID_SERIE = ".f_longtosql($lngserieid)." AND LANG = ".f_stringtosql($struilang)." AND DELETED = 0 ";
		$strserietitlelang=trim(f_fieldfromquery($strsqlserielang));
		if ($strserietitlelang!="") {
			$strserietitle=$strserietitlelang;
		}
	}

	$strsqlseason="SELECT ID_SEASON FROM ".$strsqlns."TMDB_SEASON WHERE ID_SERIE = ".f_longtosql($lngserieid)." AND SEASON_NUMBER = ".f_longtosql($lngseasonnumber)." AND DELETED = 0 ";
	$lngseasonid=f_stringtolong(f_fieldfromquery($strsqlseason));

	$strsqlepisode="SELECT * FROM ".$strsqlns."TMDB_EPISODE WHERE ID_SERIE = ".f_longtosql($lngserieid)." AND SEASON_NUMBER = ".f_longtosql($lngseasonnumber)." AND EPISODE_NUMBER = ".f_longtosql($lngepisodenumber)." AND DELETED = 0 ";
	$resultepisode=f_sqlselect($strsqlepisode);
	if ($resultepisode) {
		if ($rstempepisode=f_sqlresultfetcharray($resultepisode)) {
			$intepisodefound=true;
			$lngepisodeid=f_stringtolong($rstempepisode['ID_EPISODE']);
			if ($lngseasonid==0) {
				$lngseasonid=f_stringtolong($rstempepisode['ID_SEASON']);
			}
			$strepisodetitle=$rstempepisode['TITLE'];
			$strepisodeoverview=$rstempepisode['OVERVIEW'];
			$strepisodedatair=$rstempepisode['DAT_AIR'];
			$lngepisoderuntime=f_stringtolong($rstempepisode['RUNTIME']);
			$strepisodeproductioncode=$rstempepisode['PRODUCTION_CODE'];
			$strepisodeepisodetype=$rstempepisode['EPISODE_TYPE'];
			$strepisodestillpath=$rstempepisode['STILL_PATH'];
			$dblepisodevoteaverage=f_stringtodouble($rstempepisode['VOTE_AVERAGE']);
			$lngepisodevotecount=f_stringtolong($rstempepisode['VOTE_COUNT']);
			$strepisodeidimdb=$rstempepisode['ID_IMDB'];
			$strepisodeidwikidata=$rstempepisode['ID_WIKIDATA'];
			$lngepisodeidtvdb=f_stringtolong($rstempepisode['ID_TVDB']);
			$strepisodetimcreditscompleted=$rstempepisode['TIM_CREDITS_COMPLETED'];
		}
		f_sqlfreeresult($resultepisode);
	}

	if (!$intepisodefound) {
		$strmessage=get_movie_string('lib_episode_not_found_db');
		f_headerdisplay($strmessage);
		echo "<h1>".$strmessage."</h1>";
		exit;
	}

	if ($struilang!="en") {
		$strsqlepisodelang="SELECT * FROM ".$strsqlns."TMDB_EPISODE_LANG WHERE ID_EPISODE = ".f_longtosql($lngepisodeid)." AND ID_SEASON = ".f_longtosql($lngseasonid)." AND ID_SERIE = ".f_longtosql($lngserieid)." AND LANG = ".f_stringtosql($struilang)." AND DELETED = 0 ";
		$resultepisodelang=f_sqlselect($strsqlepisodelang);
		if ($resultepisodelang) {
			if ($rstempepisodelang=f_sqlresultfetcharray($resultepisodelang)) {
				if (trim((string)$rstempepisodelang['TITLE'])!="") {
					$strepisodetitle=$rstempepisodelang['TITLE'];
				}
				if (trim((string)$rstempepisodelang['OVERVIEW'])!="") {
					$strepisodeoverview=$rstempepisodelang['OVERVIEW'];
				}
			}
			f_sqlfreeresult($resultepisodelang);
		}
	}

	//IMDb rating of the episode, read from the raw IMDb import on the episode's own tconst.
	//IMDb rates episodes individually, so this is a SOURCED figure, unlike a season average.
	if ($strepisodeidimdb!="") {
		$strsqlepisodeimdb="SELECT averageRating, numVotes FROM ".$strsqlns."IMDB_MOVIE_RATING_IMPORT WHERE tconst = ".f_stringtosql($strepisodeidimdb);
		$resultepisodeimdb=f_sqlselect($strsqlepisodeimdb);
		if ($resultepisodeimdb) {
			if ($rstempepisodeimdb=f_sqlresultfetcharray($resultepisodeimdb)) {
				$dblepisodeimdbrating=f_stringtodouble((string)$rstempepisodeimdb['averageRating']);
				$lngepisodeimdbvotes=f_stringtolong((string)$rstempepisodeimdb['numVotes']);
			}
			f_sqlfreeresult($resultepisodeimdb);
		}
	}

	$strepisodeinstanceof="";
	$strepisodewikidatainstanceof="";
	$strepisodewikidataeverything="";
	if ($strepisodeidwikidata!="") {
		$strsqlepisodewd="SELECT INSTANCE_OF FROM ".$strsqlns."WIKIDATA_EPISODE_V1 WHERE ID_WIKIDATA = ".f_stringtosql($strepisodeidwikidata)." AND DELETED = 0 ";
		$resultepisodewd=f_sqlselect($strsqlepisodewd);
		if ($resultepisodewd) {
			if ($rstempepisodewd=f_sqlresultfetcharray($resultepisodewd)) {
				$strepisodeinstanceof=trim((string)$rstempepisodewd['INSTANCE_OF']);
			}
			f_sqlfreeresult($resultepisodewd);
		}
		// Le libelle de la classe se lit en V2, repli V1.
		// La requete remplacee n avait aucun repli vers l anglais : la bascule en ajoute
		// un, donc cette page peut afficher un type la ou elle rendait du vide.
		$strepisodewikidatainstanceof=f_getwikidatalabel($strepisodeinstanceof,$struilang);
		// Le bloc Wikidata est rendu plus bas, une fois connues les lignes "Filmed in" / "Set in"
		// qu'il ne doit pas repeter.
		//P166,P1411
	}

	$strepisodedisplaytitle=get_movie_string('lib_episode_episode')." ".$lngepisodenumber;
	if ($strepisodetitle!="") {
		$strepisodedisplaytitle.=" - ".$strepisodetitle;
	}
	$strepisodetmdburl="https://www.themoviedb.org/tv/".$lngserieid."/season/".$lngseasonnumber."/episode/".$lngepisodenumber;
	if ($strepisodeidimdb!="") {
		$strepisodeimdburl="https://www.imdb.com/title/".$strepisodeidimdb."/";
	}
	if ($strepisodeidwikidata!="") {
		$strepisodewikidataurl="https://www.wikidata.org/wiki/".$strepisodeidwikidata;
	}
	$strpagetitle=$strserietitle." - ".get_movie_string('lib_season_season')." ".$lngseasonnumber." - ".$strepisodedisplaytitle;
	//Social preview: the still is the visual shown when the page URL is shared
	$arrsocialepisode=array();
	$arrsocialepisode['description']=$strepisodeoverview;
	if ($strepisodestillpath!="") {
		$arrsocialepisode['image']=f_socialimageurl($strtmdbimagespathbaseurl.$strepisodestillpath);
		//A still is landscape, unlike the 2:3 posters, so the wide card suits it and crops nothing
		$arrsocialepisode['card']="summary_large_image";
		$arrsocialepisode['imagealt']=$strpagetitle;
	}
	f_headerdisplay($strpagetitle,"",$arrsocialepisode);
	$strserieseasonurl="season.php?serieid=".$lngserieid."&season=".$lngseasonnumber;
	$strserieurl="serie.php?serieid=".$lngserieid;

	echo "<h1>".$strpagetitle;
	//Hook entity.h1: badges and links added by the plugins after the title
	echo f_pluginhtml("entity.h1",array("type"=>"episode","id"=>$lngepisodeid,"serieid"=>$lngserieid));
	echo "</h1>";
	if ($strepisodestillpath!="") {
		echo "<p>";
		echo "<a href=\"".$strtmdbimagesoriginalpathbaseurl.$strepisodestillpath."\" target=\"_blank\" rel=\"noreferrer\">";
		echo "<img src=\"".$strtmdbimagespathbaseurl.$strepisodestillpath."\" alt=\"".$strpagetitle."\" title=\"".$strpagetitle."\" style=\"background-color: white;\" />";
		echo "</a>";
		echo "</p>";
	}

	f_moviesectionreset(array("info","overview","cast","crew"));
	ob_start();
	echo f_moviesectionopen("info","🪪",get_movie_string('lib_serie_section_tmdb'),1);
	echo "<p><strong>".get_movie_string('lib_season_parent_serie').":</strong> <a href=\"".$strserieurl."\">".$strserietitle."</a></p>";
	echo "<p><strong>".get_movie_string('lib_episode_parent_season').":</strong> <a href=\"".$strserieseasonurl."\">".get_movie_string('lib_season_season')." ".$lngseasonnumber."</a></p>";
	echo "<p><strong>".get_movie_string('lib_episode_episode_number').":</strong> ".$lngepisodenumber."</p>";
	if ($strepisodetitle!="") {
		echo "<p><strong>".get_movie_string('lib_episode_title').":</strong> ".$strepisodetitle."</p>";
	}
	if ($strepisodedatair!="") {
		echo "<p><strong>".get_movie_string('lib_episode_air_date').":</strong> ".$strepisodedatair."</p>";
	}
	if ($lngepisoderuntime>0) {
		echo "<p><strong>".get_movie_string('lib_episode_runtime').":</strong> ".$lngepisoderuntime." ".get_movie_string('lib_serie_minutes')."</p>";
	}
	if ($strepisodeproductioncode!="") {
		echo "<p><strong>".get_movie_string('lib_episode_production_code').":</strong> ".$strepisodeproductioncode."</p>";
	}
	if ($strepisodeepisodetype!="") {
		echo "<p><strong>".get_movie_string('lib_episode_type').":</strong> ".$strepisodeepisodetype."</p>";
	}
	//IMDb only, like the movie and serie pages. The TMDb pair is deliberately gone: a TMDb
	//episode score rests on a handful of voters, so a freshly aired episode rated 1.0 by three
	//people reads as a massacre next to an absent IMDb figure. The vote count now rides inside
	//the value, "7.8 (15234 votes)", so nothing is lost. Both lines below are the TMDb pair
	//they replace, kept commented for the record like on the serie page.
	//echo "<p><strong>".get_movie_string('lib_episode_vote_average').":</strong> ".$dblepisodevoteaverage."</p>";
	//echo "<p><strong>".get_movie_string('lib_episode_vote_count').":</strong> ".$lngepisodevotecount."</p>";
	$strepisodeimdbrating=f_imdbratingdisplay($dblepisodeimdbrating,$lngepisodeimdbvotes);
	if ($strepisodeimdbrating!="") {
		echo "<p><strong>".get_movie_string('lib_episode_imdb_rating').":</strong> ".$strepisodeimdbrating."</p>";
	}
	echo "<p><strong>".get_movie_string('lib_serie_id_tmdb').":</strong> ";
	if ($lngepisodeid>0) {
		echo "<a href=\"".$strepisodetmdburl."\" target=\"_blank\" rel=\"noreferrer\">";
	}
	echo $lngepisodeid;
	if ($lngepisodeid>0) {
		echo "</a>";
		echo " <a href=\"".$strepisodetmdburl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/tmdb-icon.webp\" width=\"103\" height=\"16\" alt=\"TMDb\" title=\"TMDb\"></a>";
	}
	echo "</p>";
	echo "<p><strong>".get_movie_string('lib_serie_id_imdb').":</strong> ";
	if ($strepisodeidimdb!="") {
		echo "<a href=\"".$strepisodeimdburl."\" target=\"_blank\" rel=\"noreferrer\">";
	}
	echo $strepisodeidimdb;
	if ($strepisodeidimdb!="") {
		echo "</a>";
		echo " <a href=\"".$strepisodeimdburl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"images/IMDb-icon.png\" width=\"31\" height=\"16\" alt=\"IMDb\" title=\"IMDb\"></a>";
	}
	echo "</p>";
	echo "<p><strong>".get_movie_string('lib_serie_id_wikidata').":</strong> ";
	if ($strepisodeidwikidata!="") {
		echo "<a href=\"".$strepisodewikidataurl."\" target=\"_blank\" rel=\"noreferrer\">";
	}
	echo $strepisodeidwikidata;
	if ($strepisodeidwikidata!="") {
		echo "</a>";
		echo " <a href=\"".$strepisodewikidataurl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/Wikidata-logo.svg.png\" width=\"28\" height=\"16\" alt=\"Wikidata\" title=\"Wikidata\"></a>";
	}
	echo "</p>";
	if ($lngepisodeidtvdb>0) {
		echo "<p><strong>".get_movie_string('lib_episode_id_tvdb').":</strong> ".$lngepisodeidtvdb."</p>";
	}

	// Les lieux de l'episode, sur le modele de la fiche serie :
	// T_WC_T2S_EPISODE_LOCATION, remplie depuis le 2026-10-18, porte
	// LOCATION_ROLE sur la jointure, deux lignes separees pour ne pas faire passer un decor de
	// tournage pour un lieu de recit. ID_EPISODE est l'identifiant TMDb, le meme qu'ici.
	// LOCATION_NAME seul et noms vides ecartes, pour les memes raisons que sur la fiche serie.
	$arrt2slocations=array('filming' => "", 'narrative' => "");
	if ($lngepisodeid>0) {
		$strsqlt2slocations="SELECT T_WC_T2S_EPISODE_LOCATION.LOCATION_ROLE, T_WC_T2S_LOCATION.ID_LOCATION, T_WC_T2S_LOCATION.LOCATION_NAME ";
		$strsqlt2slocations.="FROM T_WC_T2S_EPISODE_LOCATION ";
		$strsqlt2slocations.="INNER JOIN T_WC_T2S_LOCATION ON T_WC_T2S_EPISODE_LOCATION.ID_LOCATION = T_WC_T2S_LOCATION.ID_LOCATION ";
		$strsqlt2slocations.="WHERE T_WC_T2S_EPISODE_LOCATION.ID_EPISODE = ".f_longtosql($lngepisodeid)." AND COALESCE(T_WC_T2S_EPISODE_LOCATION.DELETED,0) = 0 AND COALESCE(T_WC_T2S_LOCATION.DELETED,0) = 0 ";
		$strsqlt2slocations.="ORDER BY T_WC_T2S_EPISODE_LOCATION.DISPLAY_ORDER, T_WC_T2S_LOCATION.LOCATION_NAME ";
		$resultt2slocations=f_sqlselect($strsqlt2slocations);
		if ($resultt2slocations) {
			while ($rstemplocation=f_sqlresultfetcharray($resultt2slocations)) {
				$strt2slocationrole=trim((string)$rstemplocation["LOCATION_ROLE"]);
				if (!isset($arrt2slocations[$strt2slocationrole])) {
					continue;
				}
				$lngt2slocationid=f_stringtolong($rstemplocation["ID_LOCATION"]);
				$strt2slocationname=trim((string)$rstemplocation["LOCATION_NAME"]);
				if ($strt2slocationname=="" || $lngt2slocationid<=0) {
					continue;
				}
				if ($arrt2slocations[$strt2slocationrole]!="") {
					$arrt2slocations[$strt2slocationrole].=", ";
				}
				$arrt2slocations[$strt2slocationrole].="<a href=\"location.php?locationid=".$lngt2slocationid."\">".f_htmlspecialchars($strt2slocationname)."</a>";
			}
			f_sqlfreeresult($resultt2slocations);
		}
	}
	if ($arrt2slocations['filming']!="") {
		echo "<p><strong>".get_movie_string('lib_episode_locations_filming').":</strong> ".$arrt2slocations['filming']."</p>";
	}
	if ($arrt2slocations['narrative']!="") {
		echo "<p><strong>".get_movie_string('lib_episode_locations_narrative').":</strong> ".$arrt2slocations['narrative']."</p>";
	}

	if ($strepisodetimcreditscompleted!="") {
		echo "<p><strong>".get_movie_string('lib_person_record_updated').":</strong> ".$strepisodetimcreditscompleted."</p>";
	}
	echo f_moviesectionopen("overview","📝",get_movie_string('lib_episode_overview'),1);
	echo "<p>".str_replace("\r","<br />",str_replace("\n","<br />",$strepisodeoverview))."</p>";

	if ($strepisodeidwikidata!="") {
		$strpropertyurl="property.php?propertyid=P31";
		echo f_moviesectionopen("wikidata","🧬",get_movie_string('lib_serie_section_wikidata'));
		echo "<p><strong><a href=\"".$strpropertyurl."\">".get_movie_string('lib_serie_wikidata_type')."</a>:</strong> ".$strepisodewikidatainstanceof."</p>";
		$strepisodewikidataeverything=f_wikidataallpropertiesv2($struilang,$strepisodeidwikidata,", ",f_t2slocationwikidataexcluded(isset($arrt2slocations) ? $arrt2slocations : array()));
		echo $strepisodewikidataeverything;

		echo f_moviesectionopen("wikipedia","📖",get_movie_string('lib_serie_section_wikipedia'));
		$strepisodewikipediacontent=f_wikipediallsections($struilang,$strepisodeidwikidata,$strsep=", ");
		echo $strepisodewikipediacontent;
	}

	for ($intcredittype=1;$intcredittype<=2;$intcredittype++) {
		switch ($intcredittype) {
		case 1:
			$strtitle=get_movie_string('lib_episode_cast');
			$strcredittype="cast";
			$strsqlpersonepisode="
SELECT T_WC_TMDB_PERSON_EPISODE.ID_PERSON,
T_WC_TMDB_PERSON_EPISODE.CAST_CHARACTER,
T_WC_TMDB_PERSON.NAME,
T_WC_TMDB_PERSON.PROFILE_PATH,
T_WC_TMDB_PERSON.ID_IMDB
FROM T_WC_TMDB_PERSON_EPISODE
INNER JOIN T_WC_TMDB_PERSON ON T_WC_TMDB_PERSON_EPISODE.ID_PERSON = T_WC_TMDB_PERSON.ID_PERSON
WHERE T_WC_TMDB_PERSON_EPISODE.ID_SERIE = ".f_longtosql($lngserieid)."
AND T_WC_TMDB_PERSON_EPISODE.ID_SEASON = ".f_longtosql($lngseasonid)."
AND T_WC_TMDB_PERSON_EPISODE.ID_EPISODE = ".f_longtosql($lngepisodeid)."
AND T_WC_TMDB_PERSON_EPISODE.CREDIT_TYPE = ".f_stringtosql($strcredittype)."
ORDER BY T_WC_TMDB_PERSON_EPISODE.DISPLAY_ORDER, T_WC_TMDB_PERSON_EPISODE.ID_PERSON
";
			break;
		case 2:
			$strtitle=get_movie_string('lib_episode_crew');
			$strcredittype="crew";
			$strsqlpersonepisode="
SELECT T_WC_TMDB_PERSON_EPISODE.ID_PERSON,
T_WC_TMDB_PERSON_EPISODE.CREW_DEPARTMENT,
T_WC_TMDB_PERSON_EPISODE.CREW_JOB,
T_WC_TMDB_PERSON.NAME,
T_WC_TMDB_PERSON.PROFILE_PATH,
T_WC_TMDB_PERSON.ID_IMDB
FROM T_WC_TMDB_PERSON_EPISODE
INNER JOIN T_WC_TMDB_PERSON ON T_WC_TMDB_PERSON_EPISODE.ID_PERSON = T_WC_TMDB_PERSON.ID_PERSON
WHERE T_WC_TMDB_PERSON_EPISODE.ID_SERIE = ".f_longtosql($lngserieid)."
AND T_WC_TMDB_PERSON_EPISODE.ID_SEASON = ".f_longtosql($lngseasonid)."
AND T_WC_TMDB_PERSON_EPISODE.ID_EPISODE = ".f_longtosql($lngepisodeid)."
AND T_WC_TMDB_PERSON_EPISODE.CREDIT_TYPE = ".f_stringtosql($strcredittype)."
ORDER BY T_WC_TMDB_PERSON_EPISODE.DISPLAY_ORDER, T_WC_TMDB_PERSON_EPISODE.ID_PERSON
";
			break;
		}
		echo f_moviesectionopen($strcredittype,($intcredittype==1?"🎭":"🛠️"),$strtitle);
		echo "<table>";
		echo "<tr>";
		$lngpersonidold=0;
		$lngrowindex=0;
		$resultpersonepisode=f_sqlselect($strsqlpersonepisode);
		if ($resultpersonepisode) {
			while ($rstemppersonepisode=f_sqlresultfetcharray($resultpersonepisode)) {
				$strpersoncreditname=$rstemppersonepisode['NAME'];
				$strpersonprofilepath=$rstemppersonepisode['PROFILE_PATH'];
				$strpersonprofilefullpath=f_getpersonprofilepicture($strpersonprofilepath,1);
				$lngpersonid=f_stringtolong($rstemppersonepisode['ID_PERSON']);
				$strpersonurl="person.php?personid=".$lngpersonid;
				switch ($intcredittype) {
				case 1:
					$strpersoncreditcharacter=$rstemppersonepisode['CAST_CHARACTER'];
					$strcharacterurl="character.php?character=".$strpersoncreditcharacter;
					$strcredittext=($intprivate?"<a href=\"".$strcharacterurl."\">".get_movie_string('lib_serie_character').": ".$strpersoncreditcharacter."</a>":get_movie_string('lib_serie_character').": ".$strpersoncreditcharacter);
					break;
				default:
					$strpersoncreditdepartment=$rstemppersonepisode['CREW_DEPARTMENT'];
					$strpersoncreditjob=$rstemppersonepisode['CREW_JOB'];
					$strcredittext=get_movie_string('lib_serie_department').": ".$strpersoncreditdepartment.", ".get_movie_string('lib_serie_job').": ".$strpersoncreditjob;
					break;
				}
				if ($lngpersonidold==$lngpersonid) {
					echo ", ".$strcredittext;
				} else {
					if ($lngrowindex>0) {
						echo "</td></tr><tr>";
					}
					$lngrowindex++;
					echo "<td valign=\"top\">";
					if ($strpersonprofilefullpath!="") {
						echo "<a href=\"".$strpersonurl."\">".f_getpersoncreditimagehtml($strpersonprofilepath,$strpersoncreditname)."<br /></a>";
					}
					echo "</td><td valign=\"top\"><a href=\"".$strpersonurl."\">".$strpersoncreditname."</a> - ".$strcredittext;
				}
				$lngpersonidold=$lngpersonid;
			}
			f_sqlfreeresult($resultpersonepisode);
		}
		echo "</td>";
		echo "</tr>";
		echo "</table>";
	}

	//Hook entity.sections: sections added by the plugins after the core sections
	echo f_pluginhtml("entity.sections",array("type"=>"episode","id"=>$lngepisodeid,"serieid"=>$lngserieid));

	echo f_moviesectionclose();
	// Noyau deja emis hors buffer ; on prepend la barre d'ancres au corps bufferise.
	$strfichebody=ob_get_clean();
	echo f_movieanchorbar();
	echo $strfichebody;
	f_footerdisplay();
} else {
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}
?>
