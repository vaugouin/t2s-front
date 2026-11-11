<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	//Database is available
	$lnggenreid=f_stringtolong(f_request2("genreid"));
	$intgenrefound=false;
	
	if ($lnggenreid>0) {
		//Read genre data from the MySQL database
		if ($struilang!="en") {
			$strgenrestable=$strsqlns."TMDB_GENRE_LANG";
		} else {
			$strgenrestable=$strsqlns."TMDB_GENRE";
		}
		$strsqlgenre="SELECT * FROM ".$strgenrestable." WHERE id = ".f_longtosql($lnggenreid)." ";
		$resultgenre=f_sqlselect($strsqlgenre);
		if ($resultgenre) {
			if ($rstempgenre=f_sqlresultfetcharray($resultgenre)) {
				$intgenrefound=true;
				$strgenrename=$rstempgenre['name'];
				$strgenreenname="";
			}
			f_sqlfreeresult($resultgenre);
		}
		if ($struilang!="en") {
			$strsqlgenre="SELECT * FROM ".$strsqlns."TMDB_GENRE WHERE id = ".f_longtosql($lnggenreid)." ";
			$resultgenre=f_sqlselect($strsqlgenre);
			if ($resultgenre) {
				if ($rstempgenre=f_sqlresultfetcharray($resultgenre)) {
					$strgenreenname=$rstempgenre['name'];
				}
				f_sqlfreeresult($resultgenre);
			}
		}
		
		if ($intgenrefound) {
			//Keyword record found in the database
			$lngmoviecount=0;
			$strsqlgenre="SELECT COUNT(T_WC_TMDB_MOVIE_GENRE.ID_MOVIE) AS COMPTE FROM T_WC_TMDB_MOVIE_GENRE 
INNER JOIN T_WC_TMDB_MOVIE ON T_WC_TMDB_MOVIE_GENRE.ID_MOVIE = T_WC_TMDB_MOVIE.ID_MOVIE
WHERE T_WC_TMDB_MOVIE_GENRE.ID_GENRE = ".f_longtosql($lnggenreid)." 
";
			$resultgenre=f_sqlselect($strsqlgenre);
			if ($resultgenre) {
				if ($rstempgenre=f_sqlresultfetcharray($resultgenre)) {
					$lngmoviecount=$rstempgenre["COMPTE"];
				}
				f_sqlfreeresult($resultgenre);
			}
			$lngseriecount=0;
			$strsqlseriecount="SELECT COUNT(T_WC_TMDB_SERIE_GENRE.ID_SERIE) AS COMPTE FROM T_WC_TMDB_SERIE_GENRE INNER JOIN T_WC_TMDB_SERIE ON T_WC_TMDB_SERIE_GENRE.ID_SERIE = T_WC_TMDB_SERIE.ID_SERIE WHERE T_WC_TMDB_SERIE_GENRE.ID_GENRE = ".f_longtosql($lnggenreid)." ";
			$resultseriecount=f_sqlselect($strsqlseriecount);
			if ($resultseriecount) {
				if ($rstempseriecount=f_sqlresultfetcharray($resultseriecount)) {
					$lngseriecount=$rstempseriecount["COMPTE"];
				}
				f_sqlfreeresult($resultseriecount);
			}
			//Display genre data
			//2:3 card chosen in the synthetic-images lab; keyed on the TMDb genre id
			$strgenresyntheticimagepath=f_getsyntheticimagepath("genre",$lnggenreid);
			//Social preview: same image as the page shows
			$arrsocialgenre=array();
			if ($strgenresyntheticimagepath!="") {
				$arrsocialgenre['image']=f_socialimageurl($strgenresyntheticimagepath);
				$arrsocialgenre['imagealt']=$strgenrename;
			}
			f_headerdisplay($strgenrename,"",$arrsocialgenre);
			echo "<h1>".get_movie_string('lib_genre_name')." : ".$strgenrename." (".$strgenreenname.")".": ".$lngmoviecount." ".get_movie_string('lib_movie_item').", ".$lngseriecount." ".get_movie_string('lib_serie_item')."</h1>";
			if ($strgenresyntheticimagepath!="") {
				echo "<p>";
				echo "<a href=\"".f_htmlattr($strgenresyntheticimagepath)."\" target=\"_blank\" rel=\"noreferrer\">";
				echo "<img src=\"".f_htmlattr($strgenresyntheticimagepath)."\" alt=\"".f_htmlattr($strgenrename)."\" title=\"".f_htmlattr($strgenrename)."\" width=\"300\" />";
				echo "</a>";
				echo "</p>";
			}
			//}
			
			$strsqlmovielist="";
			$strsqlmovielist.="SELECT T_WC_TMDB_MOVIE_GENRE.ID_MOVIE, ";
			$strsqlmovielist.="T_WC_TMDB_MOVIE.TITLE, ";
			$strsqlmovielist.="T_WC_TMDB_MOVIE.ORIGINAL_TITLE, ";
			$strsqlmovielist.="T_WC_TMDB_MOVIE.DAT_RELEASE, ";
			$strsqlmovielist.="T_WC_TMDB_MOVIE.ID_IMDB, ";
			$strsqlmovielist.="T_WC_TMDB_MOVIE.OVERVIEW, ";
			$strsqlmovielist.="T_WC_TMDB_MOVIE.POSTER_PATH, ";
			$strsqlmovielist.="T_WC_TMDB_MOVIE.GENRES, ";
			if ($struilang!="en") {
				$strsqlmovielist.="T_WC_TMDB_MOVIE_LANG.TITLE AS TITLE_LANG, ";
				$strsqlmovielist.="T_WC_TMDB_MOVIE_LANG.OVERVIEW AS OVERVIEW_LANG, ";
				$strsqlmovielist.="T_WC_TMDB_MOVIE_LANG.POSTER_PATH AS POSTER_PATH_LANG, ";
			}
			$strsqlmovielist.="T_WC_IMDB_MOVIE_RATING_IMPORT.averageRating, T_WC_IMDB_MOVIE_RATING_IMPORT.numVotes ";
			$strsqlmovielist.="FROM T_WC_TMDB_MOVIE_GENRE ";
			$strsqlmovielist.="INNER JOIN T_WC_TMDB_MOVIE ON T_WC_TMDB_MOVIE_GENRE.ID_MOVIE = T_WC_TMDB_MOVIE.ID_MOVIE ";
			$strsqlmovielist.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_MOVIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
			if ($struilang!="en") {
				$strsqlmovielist.="LEFT JOIN T_WC_TMDB_MOVIE_LANG ON T_WC_TMDB_MOVIE_GENRE.ID_MOVIE = T_WC_TMDB_MOVIE_LANG.ID_MOVIE ";
			}
			$strsqlmovielist.="WHERE T_WC_TMDB_MOVIE_GENRE.ID_GENRE = ".f_longtosql($lnggenreid)." ";
			if ($struilang!="en") {
				$strsqlmovielist.="AND T_WC_TMDB_MOVIE_LANG.LANG = ".f_stringtosql($struilang)." ";
			}
			$strsqlmovielist.="ORDER BY T_WC_TMDB_MOVIE.POPULARITY DESC ";
			$strsqlmovielist.="LIMIT 1000 ";
			
			$intlistmoviefound=false;
			$lngdisplayorder=0;
			echo "<table>";
			$resultmovielist=f_sqlselect($strsqlmovielist);
			if ($resultmovielist) {
				while ($rstempmovielist=f_sqlresultfetcharray($resultmovielist)) {
					$intlistmoviefound=true;
					
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
						$strmovietitlelang=$rstempmovielist['TITLE_LANG'];
						$strmovieoverviewlang=trim($rstempmovielist['OVERVIEW_LANG']);
						$strmovieposterpathlang=$rstempmovielist['POSTER_PATH_LANG'];
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
				f_sqlfreeresult($resultmovielist);
			}
			echo "</table>";
			//Display the TV series that belong to this genre (T_WC_TMDB_SERIE_GENRE)
				$strsqlserielist="";
				$strsqlserielist.="SELECT T_WC_TMDB_SERIE_GENRE.ID_SERIE, ";
				$strsqlserielist.="T_WC_TMDB_SERIE.TITLE, ";
				$strsqlserielist.="T_WC_TMDB_SERIE.ORIGINAL_TITLE, ";
				$strsqlserielist.="T_WC_TMDB_SERIE.DAT_FIRST_AIR, ";
				$strsqlserielist.="T_WC_TMDB_SERIE.DAT_LAST_AIR, ";
				$strsqlserielist.="T_WC_TMDB_SERIE.ID_IMDB, ";
				$strsqlserielist.="T_WC_TMDB_SERIE.OVERVIEW, ";
				$strsqlserielist.="T_WC_TMDB_SERIE.POSTER_PATH, ";
				$strsqlserielist.="T_WC_TMDB_SERIE.GENRES, ";
				if ($struilang!="en") {
					$strsqlserielist.="T_WC_TMDB_SERIE_LANG.TITLE AS TITLE_LANG, ";
					$strsqlserielist.="T_WC_TMDB_SERIE_LANG.OVERVIEW AS OVERVIEW_LANG, ";
					$strsqlserielist.="T_WC_TMDB_SERIE_LANG.POSTER_PATH AS POSTER_PATH_LANG, ";
				}
				$strsqlserielist.="T_WC_IMDB_MOVIE_RATING_IMPORT.averageRating, T_WC_IMDB_MOVIE_RATING_IMPORT.numVotes ";
				$strsqlserielist.="FROM T_WC_TMDB_SERIE_GENRE ";
				$strsqlserielist.="INNER JOIN T_WC_TMDB_SERIE ON T_WC_TMDB_SERIE_GENRE.ID_SERIE = T_WC_TMDB_SERIE.ID_SERIE ";
				$strsqlserielist.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_SERIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
				if ($struilang!="en") {
					$strsqlserielist.="LEFT JOIN T_WC_TMDB_SERIE_LANG ON T_WC_TMDB_SERIE_GENRE.ID_SERIE = T_WC_TMDB_SERIE_LANG.ID_SERIE ";
				}
				$strsqlserielist.="WHERE T_WC_TMDB_SERIE_GENRE.ID_GENRE = ".f_longtosql($lnggenreid)." ";
				if ($struilang!="en") {
					$strsqlserielist.="AND T_WC_TMDB_SERIE_LANG.LANG = ".f_stringtosql($struilang)." ";
				}
				$strsqlserielist.="ORDER BY T_WC_TMDB_SERIE.POPULARITY DESC ";
				$strsqlserielist.="LIMIT 1000 ";
				$intlistseriefound=false;
				echo "<h2>".$lngseriecount." ".get_movie_string('lib_serie_item')."</h2>";
				echo "<table>";
				$resultserielist=f_sqlselect($strsqlserielist);
				if ($resultserielist) {
					while ($rstempserielist=f_sqlresultfetcharray($resultserielist)) {
						$intlistseriefound=true;
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
							$strserietitlelang=$rstempserielist['TITLE_LANG'];
							$strserieoverviewlang=trim($rstempserielist['OVERVIEW_LANG']);
							$strserieposterpathlang=$rstempserielist['POSTER_PATH_LANG'];
							if ($strserietitlelang!="") {
								$strserietitle=$strserietitlelang;
							}
							if ($strserieoverviewlang!="") {
								$strserieoverview=$strserieoverviewlang;
							}
							if ($strserieposterpathlang!="") {
								$strserieposterpath=$strserieposterpathlang;
							}
						}
						f_serielistdisplay($lngserieid,$strserietitle,$strserieoriginaltitle,"",$strseriereleasedate,$strserielastairdate,$strserieidimdb,$dblaveragerating,$strserieoverview,$strserieposterpath,$strseriegenres,3,0,"float",$lngimdbvotes);
					}
					f_sqlfreeresult($resultserielist);
				}
				echo "</table>";
				//Hook page.beforefooter: blocks of the plugins at the end of the page
				echo f_pluginhtml("page.beforefooter",array("page"=>"genre"));
			f_footerdisplay();
		} else {
			//Genre record NOT found in the database
			$strmessage=get_movie_string('lib_genre_not_found');
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
		}
	}  else {
		//genreid URL parameter not set
		$strmessage=get_movie_string('lib_genreid_not_set');
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
