<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	//Database is available
	$lngnetworkid=f_stringtolong(f_request2("networkid"));
	$intnetworkfound=false;
	
	if ($lngnetworkid>0) {
		//Read network data from the MySQL database
		//if ($struilang!="en") {
		//	$strnetworkstable=$strsqlns."TMDB_NETWORK_LANG";
		//} else {
		$strnetworkstable=$strsqlns."TMDB_NETWORK";
		//}
		$strsqlnetwork="SELECT * FROM ".$strnetworkstable." WHERE ID_NETWORK = ".f_longtosql($lngnetworkid)." ";
		$resultnetwork=f_sqlselect($strsqlnetwork);
		if ($resultnetwork) {
			if ($rstempnetwork=f_sqlresultfetcharray($resultnetwork)) {
				$intnetworkfound=true;
				$strnetworkname=trim($rstempnetwork['NAME']);
				$strnetworklogopath=trim($rstempnetwork['LOGO_PATH']);
				$strnetworkorigcountry=trim($rstempnetwork['ORIGIN_COUNTRY']);
				$strnetworktimcreditscompleted=$rstempnetwork['TIM_CREDITS_COMPLETED'];
			}
			f_sqlfreeresult($resultnetwork);
		}
		
		if ($intnetworkfound) {
			//Network record found in the database
			$lngseriecount=0;
			$strsqlnetwork="SELECT COUNT(T_WC_TMDB_SERIE_NETWORK.ID_SERIE) AS COMPTE FROM T_WC_TMDB_SERIE_NETWORK 
INNER JOIN T_WC_TMDB_SERIE ON T_WC_TMDB_SERIE_NETWORK.ID_SERIE = T_WC_TMDB_SERIE.ID_SERIE
WHERE T_WC_TMDB_SERIE_NETWORK.ID_NETWORK = ".f_longtosql($lngnetworkid)." 
";
			$resultnetwork=f_sqlselect($strsqlnetwork);
			if ($resultnetwork) {
				if ($rstempnetwork=f_sqlresultfetcharray($resultnetwork)) {
					$lngseriecount=$rstempnetwork["COMPTE"];
				}
				f_sqlfreeresult($resultnetwork);
			}
			//Display network data
			$strnetworksyntheticimagepath=f_getsyntheticimagepath("network",$lngnetworkid);
			//Social preview: same image as the page shows, synthetic card first then the TMDb logo
			$arrsocialnetwork=array();
			if ($strnetworksyntheticimagepath!="") {
				$arrsocialnetwork['image']=f_socialimageurl($strnetworksyntheticimagepath);
			} elseif ($strnetworklogopath!="") {
				$arrsocialnetwork['image']=f_socialimageurl($strtmdbimagespathbaseurl.$strnetworklogopath);
			}
			if (isset($arrsocialnetwork['image'])) {
				$arrsocialnetwork['imagealt']=$strnetworkname;
			}
			f_headerdisplay($strnetworkname,"",$arrsocialnetwork);
			echo "<h1>".$strnetworkname." ".": ".$lngseriecount." ".get_movie_string('lib_serie_item')."</h1>";
			if ($strnetworksyntheticimagepath!="") {
				//Padded 2:3 logo card produced by the synthetic-images repo (decision #7)
				echo "<p>";
				echo "<a href=\"".f_htmlattr($strnetworksyntheticimagepath)."\" target=\"_blank\" rel=\"noreferrer\">";
				echo "<img src=\"".f_htmlattr($strnetworksyntheticimagepath)."\" alt=\"".f_htmlattr($strnetworkname)."\" title=\"".f_htmlattr($strnetworkname)."\" width=\"300\" />";
				echo "</a>";
				echo "</p>";
			} elseif ($strnetworklogopath!="") {
				echo "<p>";
				echo "<a href=\"".$strtmdbimagesoriginalpathbaseurl.$strnetworklogopath."\" target=\"_blank\" rel=\"noreferrer\">";
				echo "<img src=\"".$strtmdbimagespathbaseurl.$strnetworklogopath."\" alt=\"".$strnetworkname."\" title=\"".$strnetworkname."\" style=\"background-color: white;\" />";
				echo "</a>";
				echo "</p>";
			}
			echo "<p>";
			echo "<a href=\"images.php?networkid=".$lngnetworkid."\">";
			echo get_movie_string('lib_all_images_network');
			echo "</a>";
			echo "</p>";
			if ($strnetworkorigcountry!="") {
				echo "<p><strong>".get_movie_string('lib_original_country')."</strong>: ".$strnetworkorigcountry."</p>";
			}
			
			
			echo "<p><strong>".get_movie_string('lib_person_record_updated').":</strong> ".$strnetworktimcreditscompleted;
			//Hook entity.actions: action links of the plugins on the record status line
			echo f_pluginhtml("entity.actions",array("type"=>"network","id"=>$lngnetworkid,"slot"=>"record"));
			echo "</p>";
			//Hook entity.status: lines of the plugins under the record status
			echo f_pluginhtml("entity.status",array("type"=>"network","id"=>$lngnetworkid));
			$strsqlserielist="";
			$strsqlserielist.="SELECT T_WC_TMDB_SERIE_NETWORK.ID_SERIE, ";
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
			$strsqlserielist.="FROM T_WC_TMDB_SERIE_NETWORK ";
			$strsqlserielist.="INNER JOIN T_WC_TMDB_SERIE ON T_WC_TMDB_SERIE_NETWORK.ID_SERIE = T_WC_TMDB_SERIE.ID_SERIE ";
			$strsqlserielist.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_SERIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
			if ($struilang!="en") {
				$strsqlserielist.="LEFT JOIN T_WC_TMDB_SERIE_LANG ON T_WC_TMDB_SERIE_NETWORK.ID_SERIE = T_WC_TMDB_SERIE_LANG.ID_SERIE ";
			}
			$strsqlserielist.="WHERE T_WC_TMDB_SERIE_NETWORK.ID_NETWORK = ".f_longtosql($lngnetworkid)." ";
			if ($struilang!="en") {
				$strsqlserielist.="AND T_WC_TMDB_SERIE_LANG.LANG = ".f_stringtosql($struilang)." ";
			}
			$strsqlserielist.="ORDER BY T_WC_TMDB_SERIE.POPULARITY DESC ";
			$strsqlserielist.="LIMIT 1000 ";
			if ($intdebug) {
				echo "<br />".$strsqlserielist;
			}
			$intlistseriefound=false;
			$lngdisplayorder=0;
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
					$strserienetworks=$rstempserielist['GENRES'];
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
					$lngdisplayorder++;
					f_serielistdisplay($lngserieid,$strserietitle,$strserieoriginaltitle,"",$strseriereleasedate,$strserielastairdate,$strserieidimdb,$dblaveragerating,$strserieoverview,$strserieposterpath,$strserienetworks,3,0,"float",$lngimdbvotes);
				}
				f_sqlfreeresult($resultserielist);
			}
			echo "</table>";
			//Hook page.beforefooter: blocks of the plugins at the end of the page
			echo f_pluginhtml("page.beforefooter",array("page"=>"network"));
			f_footerdisplay();
		} else {
			//Network record NOT found in the database
			$strmessage=get_movie_string('lib_network_not_found');
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
		}
	}  else {
		//networkid URL parameter not set
		$strmessage=get_movie_string('lib_networkid_not_set');
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
