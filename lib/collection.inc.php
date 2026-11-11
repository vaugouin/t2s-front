<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	//Database is available
	$lngcollectionid=f_stringtolong(f_request2("collectionid"));
	$intcollectionfound=false;
	
	if ($lngcollectionid>0) {
		//Read collection data from the MySQL database
		$strsqlcollection="SELECT * FROM ".$strsqlns."TMDB_COLLECTION WHERE ID_COLLECTION = ".f_longtosql($lngcollectionid)." AND DELETED = 0 AND TIM_CREDITS_COMPLETED IS NOT NULL ";
		$resultcollection=f_sqlselect($strsqlcollection);
		if ($resultcollection) {
			if ($rstempcollection=f_sqlresultfetcharray($resultcollection)) {
				$intcollectionfound=true;
				$strcollectionoverview=$rstempcollection['OVERVIEW'];
				$strcollectionposterpath=$rstempcollection['POSTER_PATH'];
				$strcollectionname=$rstempcollection['NAME'];
				$strcollectionbackdroppath=$rstempcollection['BACKDROP_PATH'];
				$strcollectiontimcreditscompleted=$rstempcollection['TIM_CREDITS_COMPLETED'];
			}
			f_sqlfreeresult($resultcollection);
		}
		
		if ($intcollectionfound) {
			//Collection record found in the database
			$lngmoviecount=0;
			$strsqllist="SELECT COUNT(T_WC_TMDB_MOVIE.ID_MOVIE) AS COMPTE FROM T_WC_TMDB_MOVIE
WHERE T_WC_TMDB_MOVIE.ID_COLLECTION = ".f_longtosql($lngcollectionid)." 
";
			$resultlist=f_sqlselect($strsqllist);
			if ($resultlist) {
				if ($rstemplist=f_sqlresultfetcharray($resultlist)) {
					$lngmoviecount=$rstemplist["COMPTE"];
				}
				f_sqlfreeresult($resultlist);
			}
			//Display collection data
			//Social preview: the poster is the visual shown when the page URL is shared
			$arrsocialcollection=array();
			$arrsocialcollection['description']=$strcollectionoverview;
			if ($strcollectionposterpath!="") {
				$arrsocialcollection['image']=f_socialimageurl($strtmdbimagespathbaseurl.$strcollectionposterpath);
				//TMDb posters are 2:3, so the w342 rendition is 342x513
				$arrsocialcollection['imagewidth']=342;
				$arrsocialcollection['imageheight']=513;
				$arrsocialcollection['imagealt']=$strcollectionname;
			}
			f_headerdisplay($strcollectionname,"",$arrsocialcollection);
			echo "<h1>".$strcollectionname.": ".$lngmoviecount." ".get_movie_string('lib_movie_item')."</h1>";
			if ($strcollectionposterpath!="") {
				echo "<p>";
				echo "<a href=\"".$strtmdbimagesoriginalpathbaseurl.$strcollectionposterpath."\" target=\"_blank\" rel=\"noreferrer\">";
				echo "<img src=\"".$strtmdbimagespathbaseurl.$strcollectionposterpath."\" alt=\"".$strcollectionname."\" title=\"".$strcollectionname."\" style=\"background-color: white;\" />";
				echo "</a>";
				echo "</p>";
			}
			echo "<p>";
			echo "<a href=\"images.php?collectionid=".$lngcollectionid."\">";
			echo get_movie_string('lib_all_images_collection');
			echo "</a>";
			echo "</p>";
			echo "<p><strong>".get_movie_string('lib_person_record_updated').":</strong> ".$strcollectiontimcreditscompleted;
			//echo " - <a href=\"collection-load.php?collectionid=".$lngcollectionid."\">Refresh credits</a>";
			//Hook entity.actions: action links of the plugins on the record status line
			echo f_pluginhtml("entity.actions",array("type"=>"collection","id"=>$lngcollectionid,"slot"=>"record"));
			echo "</p>";
			//Hook entity.status: lines of the plugins under the record status
			echo f_pluginhtml("entity.status",array("type"=>"collection","id"=>$lngcollectionid));
			echo "<h2>".get_movie_string('lib_collection_overview')."</h2>";
			echo "<p>".$strcollectionoverview."</p>";
			echo "<h2>".get_movie_string('lib_movies_section')."</h2>";
			
			$strsqlcollectionmovie="";
			$strsqlcollectionmovie.="SELECT ";
			$strsqlcollectionmovie.="T_WC_TMDB_MOVIE.ID_MOVIE, ";
			$strsqlcollectionmovie.="T_WC_TMDB_MOVIE.TITLE, ";
			$strsqlcollectionmovie.="T_WC_TMDB_MOVIE.ORIGINAL_TITLE, ";
			$strsqlcollectionmovie.="T_WC_TMDB_MOVIE.DAT_RELEASE, ";
			$strsqlcollectionmovie.="T_WC_TMDB_MOVIE.ID_IMDB, ";
			$strsqlcollectionmovie.="T_WC_TMDB_MOVIE.OVERVIEW, ";
			$strsqlcollectionmovie.="T_WC_TMDB_MOVIE.POSTER_PATH, ";
			$strsqlcollectionmovie.="T_WC_TMDB_MOVIE.GENRES, ";
			if ($struilang!="en") {
				$strsqlcollectionmovie.="T_WC_TMDB_MOVIE_LANG.TITLE AS TITLE_LANG, ";
				$strsqlcollectionmovie.="T_WC_TMDB_MOVIE_LANG.OVERVIEW AS OVERVIEW_LANG, ";
				$strsqlcollectionmovie.="T_WC_TMDB_MOVIE_LANG.POSTER_PATH AS POSTER_PATH_LANG, ";
			}
			$strsqlcollectionmovie.="T_WC_IMDB_MOVIE_RATING_IMPORT.averageRating, T_WC_IMDB_MOVIE_RATING_IMPORT.numVotes ";
			$strsqlcollectionmovie.="FROM T_WC_TMDB_MOVIE ";
			$strsqlcollectionmovie.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_MOVIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
			if ($struilang!="en") {
				$strsqlcollectionmovie.="LEFT JOIN T_WC_TMDB_MOVIE_LANG ON T_WC_TMDB_MOVIE.ID_MOVIE = T_WC_TMDB_MOVIE_LANG.ID_MOVIE ";
			}
			$strsqlcollectionmovie.="WHERE T_WC_TMDB_MOVIE.ID_COLLECTION = ".f_longtosql($lngcollectionid)." ";
			if ($struilang!="en") {
				$strsqlcollectionmovie.="AND T_WC_TMDB_MOVIE_LANG.LANG = ".f_stringtosql($struilang)." ";
			}
			$strsqlcollectionmovie.="ORDER BY T_WC_TMDB_MOVIE.DAT_RELEASE DESC ";
			
			$intcollectionmoviefound=false;
			$lngdisplayorder=0;
			echo "<table>";
			$resultcollectionmovie=f_sqlselect($strsqlcollectionmovie);
			if ($resultcollectionmovie) {
				while ($rstempcollectionmovie=f_sqlresultfetcharray($resultcollectionmovie)) {
					$intcollectionmoviefound=true;
					
					$strmovietitle=$rstempcollectionmovie['TITLE'];
					$strmovieoriginaltitle=$rstempcollectionmovie['ORIGINAL_TITLE'];
					$strmoviereleasedate=$rstempcollectionmovie['DAT_RELEASE'];
					$lngmovieid=f_stringtolong($rstempcollectionmovie['ID_MOVIE']);
					$strmovieidimdb=trim($rstempcollectionmovie['ID_IMDB']);
					$strmovieoverview=trim($rstempcollectionmovie['OVERVIEW']);
					$strmovieposterpath=$rstempcollectionmovie['POSTER_PATH'];
					$strmoviegenres=$rstempcollectionmovie['GENRES'];
					$dblaveragerating=$rstempcollectionmovie['averageRating'];
					$lngimdbvotes=$rstempcollectionmovie['numVotes'];
					if ($struilang!="en") {
						$strmovietitlelang="";
						$strmovieoverviewlang="";
						$strmovieposterpathlang="";
						$strmovietitlelang=$rstempcollectionmovie['TITLE_LANG'];
						$strmovieoverviewlang=trim($rstempcollectionmovie['OVERVIEW_LANG']);
						$strmovieposterpathlang=$rstempcollectionmovie['POSTER_PATH_LANG'];
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
				f_sqlfreeresult($resultcollectionmovie);
			}
			echo "</table>";
			//Hook page.beforefooter: blocks of the plugins at the end of the page
			echo f_pluginhtml("page.beforefooter",array("page"=>"collection"));
			if ($strcollectionbackdroppath!="") {
				echo "<p><a href=\"".$strtmdbimagesoriginalpathbaseurl.$strcollectionbackdroppath."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"".$strtmdbimagespathbaseurl.$strcollectionbackdroppath."\" alt=\"".$strcollectionname."\" title=\"".$strcollectionname."\" style=\"background-color: white;\" /></a></p>";
			}
			f_footerdisplay();
		} else {
			//Collection record NOT found in the database
			
			$strmessage=get_movie_string('lib_collection_not_found');
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
			
			//So we redirect to the collection-load.php script to read data from the TMDb API 
			//$strredirectscript="collection-load.php?collectionid=".$lngcollectionid;
			//header("Location: ".$strredirectscript);
		}
	}  else {
		//collectionid URL parameter not set
		$strmessage=get_movie_string('lib_collectionid_not_set');
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
