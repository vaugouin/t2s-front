<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	//Database is available
	$lnglemmaid=f_stringtolong(f_request2("lemmaid"));
	$intlemmafound=false;
	
	if ($lnglemmaid>0) {
		//Read lemma data from the MySQL database
		$strlemmastable=$strsqlns."TMDB_SPACY_LEMME";
		$strsqllist="SELECT * FROM ".$strlemmastable." WHERE ID_LEMME = ".f_longtosql($lnglemmaid)." ";
		//echo "<br />$strsqllist";
		$resultlist=f_sqlselect($strsqllist);
		if ($resultlist) {
			if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
				$intlemmafound=true;
				$strlemmaname=$rstemplist['NAME'];
				$strmovielemmalabel=$rstemplist['LABEL'];
				//echo "<br />'".$strmovielemmalabel."'";
				$strlemmalib=f_spacylemmalibforlabel($strmovielemmalabel);
			}
			f_sqlfreeresult($resultlist);
		}
		
		if ($intlemmafound) {
			//Keyword record found in the database
			$lngmoviecount=0;
			$strsqllist="SELECT COUNT(T_WC_TMDB_MOVIE_LEMME.ID_MOVIE) AS COMPTE FROM T_WC_TMDB_MOVIE_LEMME 
INNER JOIN T_WC_TMDB_MOVIE ON T_WC_TMDB_MOVIE_LEMME.ID_MOVIE = T_WC_TMDB_MOVIE.ID_MOVIE
WHERE T_WC_TMDB_MOVIE_LEMME.ID_LEMME = ".f_longtosql($lnglemmaid)." 
";
			//echo "<br />$strsqllist";
			$resultlist=f_sqlselect($strsqllist);
			if ($resultlist) {
				if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
					$lngmoviecount=$rstemplist["COMPTE"];
				}
				f_sqlfreeresult($resultlist);
			}
			//Display lemma data
			f_headerdisplay($strlemmaname);
			echo "<h1>".$strlemmalib." : ".$strlemmaname.": ".$lngmoviecount." ".get_movie_string('lib_movie_item')."</h1>";
			//}
			
			$strsqlmovielist="";
			$strsqlmovielist.="SELECT DISTINCT T_WC_TMDB_MOVIE_LEMME.ID_MOVIE, ";
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
			$strsqlmovielist.="FROM T_WC_TMDB_MOVIE_LEMME ";
			$strsqlmovielist.="INNER JOIN T_WC_TMDB_MOVIE ON T_WC_TMDB_MOVIE_LEMME.ID_MOVIE = T_WC_TMDB_MOVIE.ID_MOVIE ";
			$strsqlmovielist.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_MOVIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
			if ($struilang!="en") {
				$strsqlmovielist.="LEFT JOIN T_WC_TMDB_MOVIE_LANG ON T_WC_TMDB_MOVIE_LEMME.ID_MOVIE = T_WC_TMDB_MOVIE_LANG.ID_MOVIE ";
			}
			$strsqlmovielist.="WHERE T_WC_TMDB_MOVIE_LEMME.ID_LEMME = ".f_longtosql($lnglemmaid)." ";
			if ($struilang!="en") {
				$strsqlmovielist.="AND T_WC_TMDB_MOVIE_LANG.LANG = ".f_stringtosql($struilang)." ";
			}
			$strsqlmovielist.="ORDER BY T_WC_TMDB_MOVIE.POPULARITY DESC ";
			
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
			//Hook page.beforefooter: blocks of the plugins at the end of the page
			echo f_pluginhtml("page.beforefooter",array("page"=>"lemma"));
			f_footerdisplay();
		} else {
			//Lemme record NOT found in the database
			$strmessage=get_movie_string('lib_lemma_not_found');
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
		}
	}  else {
		//lemmaid URL parameter not set
		$strmessage=get_movie_string('lib_lemmaid_not_set');
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
