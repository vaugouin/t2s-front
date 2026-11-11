<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	//Database is available
	$strcharacter=trim(f_request2("character"));
	$intcharacterfound=false;

	if ($strcharacter!="") {
		// Count movies
		$lngmoviecount=0;
		$strsqllist="SELECT COUNT(DISTINCT ID_MOVIE) AS COMPTE FROM T_WC_TMDB_PERSON_MOVIE WHERE CAST_CHARACTER = ".f_stringtosql($strcharacter)." ";
		$resultlist=f_sqlselect($strsqllist);
		if ($resultlist) {
			if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
				$lngmoviecount=$rstemplist["COMPTE"];
			}
			f_sqlfreeresult($resultlist);
		}
		// Count series
		$lngseriecount=0;
		$strsqllist="SELECT COUNT(DISTINCT ID_SERIE) AS COMPTE FROM T_WC_TMDB_PERSON_SERIE WHERE CAST_CHARACTER = ".f_stringtosql($strcharacter)." ";
		$resultlist=f_sqlselect($strsqllist);
		if ($resultlist) {
			if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
				$lngseriecount=$rstemplist["COMPTE"];
			}
			f_sqlfreeresult($resultlist);
		}
		// Count persons
		$lngpersoncount=0;
		$strsqllist="SELECT COUNT(DISTINCT ID_PERSON) AS COMPTE FROM (
			SELECT ID_PERSON FROM T_WC_TMDB_PERSON_MOVIE WHERE CAST_CHARACTER = ".f_stringtosql($strcharacter)."
			UNION
			SELECT ID_PERSON FROM T_WC_TMDB_PERSON_SERIE WHERE CAST_CHARACTER = ".f_stringtosql($strcharacter)."
		) AS sub ";
		$resultlist=f_sqlselect($strsqllist);
		if ($resultlist) {
			if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
				$lngpersoncount=$rstemplist["COMPTE"];
			}
			f_sqlfreeresult($resultlist);
		}

		//Display character data
		f_headerdisplay($strcharacter);
		echo "<h1>".$strcharacter.": ".$lngmoviecount." ".get_movie_string('lib_movie_item').", ".$lngseriecount." ".get_movie_string('lib_serie_item').", ".$lngpersoncount." ".get_movie_string('lib_person_item')."</h1>";

		// ── Section 1: Movies ──────────────────────────────────────────────────
		echo "<h2>".get_movie_string('lib_movies_section')."</h2>";
		$inttablejoins=false;
		$strsqlmovielist="";
		$strsqlmovielist.="SELECT DISTINCT T_WC_TMDB_MOVIE.ID_MOVIE, ";
		$strsqlmovielist.="T_WC_TMDB_MOVIE.TITLE, ";
		$strsqlmovielist.="T_WC_TMDB_MOVIE.ORIGINAL_TITLE, ";
		$strsqlmovielist.="T_WC_TMDB_MOVIE.DAT_RELEASE, ";
		$strsqlmovielist.="T_WC_TMDB_MOVIE.ID_IMDB, ";
		$strsqlmovielist.="T_WC_TMDB_MOVIE.OVERVIEW, ";
		$strsqlmovielist.="T_WC_TMDB_MOVIE.POSTER_PATH, ";
		$strsqlmovielist.="T_WC_TMDB_MOVIE.GENRES, ";
		$strsqlmovielist.="T_WC_IMDB_MOVIE_RATING_IMPORT.averageRating, T_WC_IMDB_MOVIE_RATING_IMPORT.numVotes ";
		$strsqlmovielist.="FROM T_WC_TMDB_MOVIE ";
		$strsqlmovielist.="INNER JOIN T_WC_TMDB_PERSON_MOVIE ON T_WC_TMDB_MOVIE.ID_MOVIE = T_WC_TMDB_PERSON_MOVIE.ID_MOVIE ";
		$strsqlmovielist.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_MOVIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
		$strsqlmovielist.="WHERE T_WC_TMDB_PERSON_MOVIE.CAST_CHARACTER = ".f_stringtosql($strcharacter)." ";
		$strsqlmovielist.="ORDER BY T_WC_TMDB_MOVIE.POPULARITY DESC ";
		$strsqlmovielist.="LIMIT 1000 ";

		$lngdisplayorder=0;
		echo "<table>";
		$resultmovielist=f_sqlselect($strsqlmovielist);
		if ($resultmovielist) {
			while ($rstempmovielist=f_sqlresultfetcharray($resultmovielist)) {
				$strmovietitle=$rstempmovielist['TITLE'];
				$strmovieoriginaltitle=$rstempmovielist['ORIGINAL_TITLE'];
				$strmoviereleasedate=$rstempmovielist['DAT_RELEASE'];
				$lngmovieid=f_stringtolong($rstempmovielist['ID_MOVIE']);
				$strmovieidimdb=trim($rstempmovielist['ID_IMDB']);
				$strmovieoverview=trim($rstempmovielist['OVERVIEW']);
				$strmovieposterpath=$rstempmovielist['POSTER_PATH'];
				$strmoviegenres=$rstempmovielist['GENRES'];
				$dblaveragerating=$rstempmovielist['averageRating'];
					$lngimdbvotes=$rstempmovielist['numVotes'];
				if ($struilang!="en") {
					$strmovietitlelang="";
					$strmovieoverviewlang="";
					$strmovieposterpathlang="";
					$strsqllangmovie2="SELECT T_WC_TMDB_MOVIE_LANG.TITLE AS TITLE_LANG, T_WC_TMDB_MOVIE_LANG.OVERVIEW AS OVERVIEW_LANG, T_WC_TMDB_MOVIE_LANG.POSTER_PATH AS POSTER_PATH_LANG ";
					$strsqllangmovie2.="FROM T_WC_TMDB_MOVIE_LANG ";
					$strsqllangmovie2.="WHERE T_WC_TMDB_MOVIE_LANG.ID_MOVIE = ".f_longtosql($lngmovieid)." ";
					$strsqllangmovie2.="AND T_WC_TMDB_MOVIE_LANG.LANG = ".f_stringtosql($struilang)." ";
					$resultlangmovie2=f_sqlselect($strsqllangmovie2);
					if ($resultlangmovie2) {
						if ($rstemplangmovie2=f_sqlresultfetcharray($resultlangmovie2)) {
							$strmovietitlelang=$rstemplangmovie2['TITLE_LANG'];
							$strmovieoverviewlang=trim($rstemplangmovie2['OVERVIEW_LANG']);
							$strmovieposterpathlang=$rstemplangmovie2['POSTER_PATH_LANG'];
						}
						f_sqlfreeresult($resultlangmovie2);
					}
					if ($strmovietitlelang!="") { $strmovietitle=$strmovietitlelang; }
					if ($strmovieoverviewlang!="") { $strmovieoverview=$strmovieoverviewlang; }
					if ($strmovieposterpathlang!="") { $strmovieposterpath=$strmovieposterpathlang; }
				}
				$lngdisplayorder++;
				f_movielistdisplay($lngmovieid,$strmovietitle,$strmovieoriginaltitle,"",$strmoviereleasedate,$strmovieidimdb,$dblaveragerating,$strmovieoverview,$strmovieposterpath,$strmoviegenres,3,0,"float",$lngimdbvotes);
			}
			f_sqlfreeresult($resultmovielist);
		}
		echo "</table>";

		// ── Section 2: Series ─────────────────────────────────────────────────
		echo "<h2>".get_movie_string('lib_series_section')."</h2>";
		$strsqlserielist="";
		$strsqlserielist.="SELECT DISTINCT T_WC_TMDB_SERIE.ID_SERIE, ";
		$strsqlserielist.="T_WC_TMDB_SERIE.TITLE, ";
		$strsqlserielist.="T_WC_TMDB_SERIE.ORIGINAL_TITLE, ";
		$strsqlserielist.="T_WC_TMDB_SERIE.DAT_FIRST_AIR, ";
		$strsqlserielist.="T_WC_TMDB_SERIE.DAT_LAST_AIR, ";
		$strsqlserielist.="T_WC_TMDB_SERIE.ID_IMDB, ";
		$strsqlserielist.="T_WC_TMDB_SERIE.OVERVIEW, ";
		$strsqlserielist.="T_WC_TMDB_SERIE.POSTER_PATH, ";
		$strsqlserielist.="T_WC_TMDB_SERIE.GENRES, ";
		$strsqlserielist.="T_WC_IMDB_MOVIE_RATING_IMPORT.averageRating, T_WC_IMDB_MOVIE_RATING_IMPORT.numVotes ";
		$strsqlserielist.="FROM T_WC_TMDB_SERIE ";
		$strsqlserielist.="INNER JOIN T_WC_TMDB_PERSON_SERIE ON T_WC_TMDB_SERIE.ID_SERIE = T_WC_TMDB_PERSON_SERIE.ID_SERIE ";
		$strsqlserielist.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_SERIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
		$strsqlserielist.="WHERE T_WC_TMDB_PERSON_SERIE.CAST_CHARACTER = ".f_stringtosql($strcharacter)." ";
		$strsqlserielist.="ORDER BY T_WC_TMDB_SERIE.POPULARITY DESC ";
		$strsqlserielist.="LIMIT 1000 ";

		$lngdisplayorder=0;
		echo "<table>";
		$resultserielist=f_sqlselect($strsqlserielist);
		if ($resultserielist) {
			while ($rstempserielist=f_sqlresultfetcharray($resultserielist)) {
				$strserietitle=$rstempserielist['TITLE'];
				$strserieoriginaltitle=$rstempserielist['ORIGINAL_TITLE'];
				$strseriereleasedate=$rstempserielist['DAT_FIRST_AIR'];
				$strserielastairdate=$rstempserielist['DAT_LAST_AIR'];
				$lngserieid=f_stringtolong($rstempserielist['ID_SERIE']);
				$strserieidimdb=trim($rstempserielist['ID_IMDB']);
				$strserieoverview=trim($rstempserielist['OVERVIEW']);
				$strserieposterpath=$rstempserielist['POSTER_PATH'];
				$strseriegenres=$rstempserielist['GENRES'];
				$dblaveragerating=$rstempserielist['averageRating'];
					$lngimdbvotes=$rstempserielist['numVotes'];
				if ($struilang!="en") {
					$strserietitlelang="";
					$strserieoverviewlang="";
					$strserieposterpathlang="";
					$strsqllangserietmp="SELECT T_WC_TMDB_SERIE_LANG.TITLE AS TITLE_LANG, T_WC_TMDB_SERIE_LANG.OVERVIEW AS OVERVIEW_LANG, T_WC_TMDB_SERIE_LANG.POSTER_PATH AS POSTER_PATH_LANG ";
					$strsqllangserietmp.="FROM T_WC_TMDB_SERIE_LANG ";
					$strsqllangserietmp.="WHERE T_WC_TMDB_SERIE_LANG.ID_SERIE = ".f_longtosql($lngserieid)." ";
					$strsqllangserietmp.="AND T_WC_TMDB_SERIE_LANG.LANG = ".f_stringtosql($struilang)." ";
					$resultlangserie=f_sqlselect($strsqllangserietmp);
					if ($resultlangserie) {
						if ($rstempserielang=f_sqlresultfetcharray($resultlangserie)) {
							$strserietitlelang=$rstempserielang['TITLE_LANG'];
							$strserieoverviewlang=trim($rstempserielang['OVERVIEW_LANG']);
							$strserieposterpathlang=$rstempserielang['POSTER_PATH_LANG'];
						}
						f_sqlfreeresult($resultlangserie);
					}
					if ($strserietitlelang!="") { $strserietitle=$strserietitlelang; }
					if ($strserieoverviewlang!="") { $strserieoverview=$strserieoverviewlang; }
					if ($strserieposterpathlang!="") { $strserieposterpath=$strserieposterpathlang; }
				}
				$lngdisplayorder++;
				f_serielistdisplay($lngserieid,$strserietitle,$strserieoriginaltitle,"",$strseriereleasedate,$strserielastairdate,$strserieidimdb,$dblaveragerating,$strserieoverview,$strserieposterpath,$strseriegenres,3,0,"float",$lngimdbvotes);
			}
			f_sqlfreeresult($resultserielist);
		}
		echo "</table>";

		// ── Section 3: Persons ────────────────────────────────────────────────
		echo "<h2>".get_movie_string('lib_persons_section')."</h2>";
		$strsqlpersonlist="";
		$strsqlpersonlist.="SELECT DISTINCT T_WC_TMDB_PERSON.ID_PERSON, ";
		$strsqlpersonlist.="T_WC_TMDB_PERSON.NAME, ";
		$strsqlpersonlist.="T_WC_TMDB_PERSON.BIOGRAPHY, ";
		$strsqlpersonlist.="T_WC_TMDB_PERSON.BIRTHDAY, ";
		$strsqlpersonlist.="T_WC_TMDB_PERSON.DEATHDAY, ";
		$strsqlpersonlist.="T_WC_TMDB_PERSON.ID_IMDB, ";
		$strsqlpersonlist.="T_WC_TMDB_PERSON.PROFILE_PATH ";
		$strsqlpersonlist.="FROM T_WC_TMDB_PERSON ";
		$strsqlpersonlist.="WHERE T_WC_TMDB_PERSON.ID_PERSON IN ( ";
		$strsqlpersonlist.="SELECT ID_PERSON FROM T_WC_TMDB_PERSON_MOVIE WHERE CAST_CHARACTER = ".f_stringtosql($strcharacter)." ";
		$strsqlpersonlist.="UNION ";
		$strsqlpersonlist.="SELECT ID_PERSON FROM T_WC_TMDB_PERSON_SERIE WHERE CAST_CHARACTER = ".f_stringtosql($strcharacter)." ";
		$strsqlpersonlist.=") ";
		$strsqlpersonlist.="ORDER BY T_WC_TMDB_PERSON.POPULARITY DESC ";
		$strsqlpersonlist.="LIMIT 100 ";

		$lngdisplayorder=0;
		echo "<table>";
		$resultpersonlist=f_sqlselect($strsqlpersonlist);
		if ($resultpersonlist) {
			while ($rstemppersonlist=f_sqlresultfetcharray($resultpersonlist)) {
				$strpersonname=$rstemppersonlist['NAME'];
				$strpersonbiography=trim($rstemppersonlist['BIOGRAPHY']);
				$lngpersonid=f_stringtolong($rstemppersonlist['ID_PERSON']);
				$strpersonidimdb=trim($rstemppersonlist['ID_IMDB']);
				$strpersonbirthday=$rstemppersonlist['BIRTHDAY'];
				$strpersondeathday=$rstemppersonlist['DEATHDAY'];
				$strpersonprofilepath=$rstemppersonlist['PROFILE_PATH'];
				$lngdisplayorder++;
				f_personlistdisplay($lngpersonid,$strpersonname,$strpersonbiography,$strpersonbirthday,$strpersondeathday,$strpersonidimdb,$strpersonprofilepath,3);
			}
			f_sqlfreeresult($resultpersonlist);
		}
		echo "</table>";

		//Hook page.beforefooter: blocks of the plugins at the end of the page
		echo f_pluginhtml("page.beforefooter",array("page"=>"character"));
		f_footerdisplay();
	}  else {
		//characterid URL parameter not set
		$strmessage=get_movie_string('lib_character_not_set');
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
