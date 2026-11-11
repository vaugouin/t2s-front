<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	//Database is available
	$stroriginallanguage=f_request2("origlang");
	$intlangfound=false;
	
	if ($stroriginallanguage!="") {
		//Read language data from the MySQL database
		$strsqllang="SELECT * FROM ".$strsqlns."TMDB_LANG_LANG WHERE LANG = ".f_stringtosql($stroriginallanguage)." AND DELETED = 0 ";
		$strsqllang.=" AND LANG_DISPLAY = ".f_stringtosql($struilang)." ";
		$resultlang=f_sqlselect($strsqllang);
		if ($resultlang) {
			if ($rstemplang=f_sqlresultfetcharray($resultlang)) {
				$intlangfound=true;
				$strlangdescription=$rstemplang['DESCRIPTION'];
			}
			f_sqlfreeresult($resultlang);
		}
		
		if ($intlangfound) {
			//Language record found in the database
			$lngmoviecount=0;
			$strsqllist="SELECT COUNT(T_WC_TMDB_MOVIE.ID_MOVIE) AS COMPTE FROM T_WC_TMDB_MOVIE
WHERE T_WC_TMDB_MOVIE.ORIGINAL_LANGUAGE = ".f_stringtosql($stroriginallanguage)."
";
			$resultlist=f_sqlselect($strsqllist);
			if ($resultlist) {
				if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
					$lngmoviecount=$rstemplist["COMPTE"];
				}
				f_sqlfreeresult($resultlist);
			}
			$lngseriecount=0;
			$strsqllist="SELECT COUNT(T_WC_TMDB_SERIE.ID_SERIE) AS COMPTE FROM T_WC_TMDB_SERIE ";
			$strsqllist.="WHERE T_WC_TMDB_SERIE.ORIGINAL_LANGUAGE = ".f_stringtosql($stroriginallanguage)." ";
			$resultlist=f_sqlselect($strsqllist);
			if ($resultlist) {
				if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
					$lngseriecount=$rstemplist["COMPTE"];
				}
				f_sqlfreeresult($resultlist);
			}
			//Display data
			f_headerdisplay($strlangdescription);
					echo "<h1>".$strlangdescription.": ".$lngmoviecount." ".get_movie_string('lib_movie_item').", ".$lngseriecount." ".get_movie_string('lib_serie_item')."</h1>";
			
			echo "<h2>".get_movie_string('lib_movies_section')."</h2>";
			$inttablejoins=false;
			$strsqllangmovie="";
			$strsqllangmovie.="SELECT ";
			$strsqllangmovie.="T_WC_TMDB_MOVIE.ID_MOVIE, ";
			$strsqllangmovie.="T_WC_TMDB_MOVIE.TITLE, ";
			$strsqllangmovie.="T_WC_TMDB_MOVIE.ORIGINAL_TITLE, ";
			$strsqllangmovie.="T_WC_TMDB_MOVIE.DAT_RELEASE, ";
			$strsqllangmovie.="T_WC_TMDB_MOVIE.ID_IMDB, ";
			$strsqllangmovie.="T_WC_TMDB_MOVIE.OVERVIEW, ";
			$strsqllangmovie.="T_WC_TMDB_MOVIE.POSTER_PATH, ";
			$strsqllangmovie.="T_WC_TMDB_MOVIE.GENRES, ";
			if ($struilang!="en" && $inttablejoins) {
				$strsqllangmovie.="T_WC_TMDB_MOVIE_LANG.TITLE AS TITLE_LANG, ";
				$strsqllangmovie.="T_WC_TMDB_MOVIE_LANG.OVERVIEW AS OVERVIEW_LANG, ";
				$strsqllangmovie.="T_WC_TMDB_MOVIE_LANG.POSTER_PATH AS POSTER_PATH_LANG, ";
			}
			$strsqllangmovie.="T_WC_IMDB_MOVIE_RATING_IMPORT.averageRating, T_WC_IMDB_MOVIE_RATING_IMPORT.numVotes ";
			$strsqllangmovie.="FROM T_WC_TMDB_MOVIE ";
			$strsqllangmovie.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_MOVIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
			if ($struilang!="en" && $inttablejoins) {
				$strsqllangmovie.="LEFT JOIN T_WC_TMDB_MOVIE_LANG ON T_WC_TMDB_MOVIE.ID_MOVIE = T_WC_TMDB_MOVIE_LANG.ID_MOVIE ";
			}
			$strsqllangmovie.="WHERE T_WC_TMDB_MOVIE.ORIGINAL_LANGUAGE = ".f_stringtosql($stroriginallanguage)." ";
			if ($struilang!="en" && $inttablejoins) {
				$strsqllangmovie.="AND T_WC_TMDB_MOVIE_LANG.LANG = ".f_stringtosql($struilang)." ";
			}
			$strsqllangmovie.="ORDER BY T_WC_TMDB_MOVIE.POPULARITY DESC ";
			$strsqllangmovie.="LIMIT 1000 ";
			if ($intdebug) {
				echo "<br />\$strsqllangmovie=$strsqllangmovie";
			}
			//exit;
			$intlangmoviefound=false;
			$lngdisplayorder=0;
			echo "<table>";
			$resultlangmovie=f_sqlselect($strsqllangmovie);
			if ($resultlangmovie) {
				while ($rstemplangmovie=f_sqlresultfetcharray($resultlangmovie)) {
					$intlangmoviefound=true;
					
					$strmovietitle=$rstemplangmovie['TITLE'];
					$strmovieoriginaltitle=$rstemplangmovie['ORIGINAL_TITLE'];
					$strmoviereleasedate=$rstemplangmovie['DAT_RELEASE'];
					$lngmovieid=f_stringtolong($rstemplangmovie['ID_MOVIE']);
					$strmovieidimdb=trim($rstemplangmovie['ID_IMDB']);
					$strmovieoverview=trim($rstemplangmovie['OVERVIEW']);
					$strmovieposterpath=$rstemplangmovie['POSTER_PATH'];
					$strmoviegenres=$rstemplangmovie['GENRES'];
					$dblaveragerating=$rstemplangmovie['averageRating'];
					$lngimdbvotes=$rstemplangmovie['numVotes'];
					if ($struilang!="en") {
						$strmovietitlelang="";
						$strmovieoverviewlang="";
						$strmovieposterpathlang="";
						if ($inttablejoins) {
							$strmovietitlelang=$rstemplangmovie['TITLE_LANG'];
							$strmovieoverviewlang=trim($rstemplangmovie['OVERVIEW_LANG']);
							$strmovieposterpathlang=$rstemplangmovie['POSTER_PATH_LANG'];
						} else {
							$strsqllangmovie2="";
							$strsqllangmovie2.="SELECT ";
							$strsqllangmovie2.="T_WC_TMDB_MOVIE_LANG.TITLE AS TITLE_LANG, ";
							$strsqllangmovie2.="T_WC_TMDB_MOVIE_LANG.OVERVIEW AS OVERVIEW_LANG, ";
							$strsqllangmovie2.="T_WC_TMDB_MOVIE_LANG.POSTER_PATH AS POSTER_PATH_LANG ";
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
						}
						if ($strmovietitlelang!="") {
							$strmovietitle=$strmovietitlelang;
						}
						if ($strmovieoverviewlang!="") {
							$strmovieoverview=$strmovieoverviewlang;
						}
						if ($strmovieposterpathlang!="") {
							$strmovieposterpath=$strmovieposterpathlang;
						}
					}
					$lngdisplayorder++;
					f_movielistdisplay($lngmovieid,$strmovietitle,$strmovieoriginaltitle,"",$strmoviereleasedate,$strmovieidimdb,$dblaveragerating,$strmovieoverview,$strmovieposterpath,$strmoviegenres,3,0,"float",$lngimdbvotes);
				}
				f_sqlfreeresult($resultlangmovie);
			}
			echo "</table>";

			// ── Series ────────────────────────────────────────────────────────────
			echo "<h2>".get_movie_string('lib_series_section')."</h2>";
			$strsqllangserie="";
			$strsqllangserie.="SELECT ";
			$strsqllangserie.="T_WC_TMDB_SERIE.ID_SERIE, ";
			$strsqllangserie.="T_WC_TMDB_SERIE.TITLE, ";
			$strsqllangserie.="T_WC_TMDB_SERIE.ORIGINAL_TITLE, ";
			$strsqllangserie.="T_WC_TMDB_SERIE.DAT_FIRST_AIR, ";
			$strsqllangserie.="T_WC_TMDB_SERIE.DAT_LAST_AIR, ";
			$strsqllangserie.="T_WC_TMDB_SERIE.ID_IMDB, ";
			$strsqllangserie.="T_WC_TMDB_SERIE.OVERVIEW, ";
			$strsqllangserie.="T_WC_TMDB_SERIE.POSTER_PATH, ";
			$strsqllangserie.="T_WC_TMDB_SERIE.GENRES, ";
			$strsqllangserie.="T_WC_IMDB_MOVIE_RATING_IMPORT.averageRating, T_WC_IMDB_MOVIE_RATING_IMPORT.numVotes ";
			$strsqllangserie.="FROM T_WC_TMDB_SERIE ";
			$strsqllangserie.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_SERIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
			$strsqllangserie.="WHERE T_WC_TMDB_SERIE.ORIGINAL_LANGUAGE = ".f_stringtosql($stroriginallanguage)." ";
			$strsqllangserie.="ORDER BY T_WC_TMDB_SERIE.POPULARITY DESC ";
			$strsqllangserie.="LIMIT 1000 ";
			$lngdisplayorder=0;
			echo "<table>";
			$resultlangserie=f_sqlselect($strsqllangserie);
			if ($resultlangserie) {
				while ($rstemplangserie=f_sqlresultfetcharray($resultlangserie)) {
					$strserietitle=$rstemplangserie['TITLE'];
					$strserieoriginaltitle=$rstemplangserie['ORIGINAL_TITLE'];
					$strseriereleasedate=$rstemplangserie['DAT_FIRST_AIR'];
					$strserielastairdate=$rstemplangserie['DAT_LAST_AIR'];
					$lngserieid=f_stringtolong($rstemplangserie['ID_SERIE']);
					$strserieidimdb=trim($rstemplangserie['ID_IMDB']);
					$strserieoverview=trim($rstemplangserie['OVERVIEW']);
					$strserieposterpath=$rstemplangserie['POSTER_PATH'];
					$strseriegenres=$rstemplangserie['GENRES'];
					$dblaveragerating=$rstemplangserie['averageRating'];
					$lngimdbvotes=$rstemplangserie['numVotes'];
					if ($struilang!="en") {
						$strserietitlelang="";
						$strserieoverviewlang="";
						$strserieposterpathlang="";
						$strsqllangserietmp="SELECT T_WC_TMDB_SERIE_LANG.TITLE AS TITLE_LANG, T_WC_TMDB_SERIE_LANG.OVERVIEW AS OVERVIEW_LANG, T_WC_TMDB_SERIE_LANG.POSTER_PATH AS POSTER_PATH_LANG ";
						$strsqllangserietmp.="FROM T_WC_TMDB_SERIE_LANG ";
						$strsqllangserietmp.="WHERE T_WC_TMDB_SERIE_LANG.ID_SERIE = ".f_longtosql($lngserieid)." ";
						$strsqllangserietmp.="AND T_WC_TMDB_SERIE_LANG.LANG = ".f_stringtosql($struilang)." ";
						$resultlangserie2=f_sqlselect($strsqllangserietmp);
						if ($resultlangserie2) {
							if ($rstempserielang=f_sqlresultfetcharray($resultlangserie2)) {
								$strserietitlelang=$rstempserielang['TITLE_LANG'];
								$strserieoverviewlang=trim($rstempserielang['OVERVIEW_LANG']);
								$strserieposterpathlang=$rstempserielang['POSTER_PATH_LANG'];
							}
							f_sqlfreeresult($resultlangserie2);
						}
						if ($strserietitlelang!="") { $strserietitle=$strserietitlelang; }
						if ($strserieoverviewlang!="") { $strserieoverview=$strserieoverviewlang; }
						if ($strserieposterpathlang!="") { $strserieposterpath=$strserieposterpathlang; }
					}
					$lngdisplayorder++;
					f_serielistdisplay($lngserieid,$strserietitle,$strserieoriginaltitle,"",$strseriereleasedate,$strserielastairdate,$strserieidimdb,$dblaveragerating,$strserieoverview,$strserieposterpath,$strseriegenres,3,0,"float",$lngimdbvotes);
				}
				f_sqlfreeresult($resultlangserie);
			}
			echo "</table>";

			//Hook page.beforefooter: blocks of the plugins at the end of the page
			echo f_pluginhtml("page.beforefooter",array("page"=>"origlang"));
			if ($strlangbackdroppath!="") {
				echo "<p><a href=\"".$strtmdbimagesoriginalpathbaseurl.$strlangbackdroppath."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"".$strtmdbimagespathbaseurl.$strlangbackdroppath."\" alt=\"".$strlangname."\" title=\"".$strlangname."\" style=\"background-color: white;\" /></a></p>";
			}
			f_footerdisplay();
		} else {
			//Lang record NOT found in the database
			$strmessage=get_movie_string('lib_lang_not_found');
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
			
			//So we redirect to the lang-load.php script to read data from the TMDb API 
			//$strredirectscript="lang-load.php?originallanguage=".$stroriginallanguage;
			//header("Location: ".$strredirectscript);
		}
	}  else {
		//originallanguage URL parameter not set
		$strmessage=get_movie_string('lib_origlang_not_set');
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
