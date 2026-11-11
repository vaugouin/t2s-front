<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	//Database is available
	$lngcompanyid=f_stringtolong(f_request2("companyid"));
	$intcompanyfound=false;
	
	if ($lngcompanyid>0) {
		//Read company data from the MySQL database
		//if ($struilang!="en") {
		//	$strcompaniestable=$strsqlns."TMDB_COMPANY_LANG";
		//} else {
		$strcompaniestable=$strsqlns."TMDB_COMPANY";
		//}
		$strcompanyidwikidata="";
		$strsqlcompany="SELECT * FROM ".$strcompaniestable." WHERE ID_COMPANY = ".f_longtosql($lngcompanyid)." ";
		$resultcompany=f_sqlselect($strsqlcompany);
		if ($resultcompany) {
			if ($rstempcompany=f_sqlresultfetcharray($resultcompany)) {
				$intcompanyfound=true;
				$strcompanyname=trim($rstempcompany['NAME']);
				$strcompanydesc=trim($rstempcompany['DESCRIPTION']);
				$strcompanylogopath=trim($rstempcompany['LOGO_PATH']);
				$strcompanyheadquarters=trim($rstempcompany['HEADQUARTERS']);
				$strcompanyorigcountry=trim($rstempcompany['ORIGIN_COUNTRY']);
				$strcompanyidwikidata=isset($rstempcompany['ID_WIKIDATA']) ? trim($rstempcompany['ID_WIKIDATA']) : '';
				$lngcompanyparentid=f_stringtolong($rstempcompany['ID_PARENT']);
				$strcompanytimcreditscompleted=$rstempcompany['TIM_CREDITS_COMPLETED'];
				if ($lngcompanyparentid>0) {
					$strsqlcompanyparent="SELECT * FROM ".$strsqlns."TMDB_COMPANY ";
					$strsqlcompanyparent.="WHERE ID_COMPANY = ".f_longtosql($lngcompanyparentid);
					$resultcompanyparent=f_sqlselect($strsqlcompanyparent);
					if ($resultcompanyparent) {
						if ($rstempcompanyparent=f_sqlresultfetcharray($resultcompanyparent)) {
							$strcompanyparentname=trim($rstempcompanyparent['NAME']);
							$strcompanyparentdesc=trim($rstempcompanyparent['DESCRIPTION']);
							$strcompanyparentheadquarters=trim($rstempcompanyparent['HEADQUARTERS']);
							$strcompanyparentorigcountry=trim($rstempcompanyparent['ORIGIN_COUNTRY']);
							$lngcompanyparentparentid=f_stringtolong($rstempcompanyparent['ID_PARENT']);
							$strcompanyparenturl="company.php?companyid=".$lngcompanyparentid;
						}
						f_sqlfreeresult($resultcompanyparent);
					}
				}
			}
			f_sqlfreeresult($resultcompany);
		}
		
		if ($intcompanyfound) {
			//Company record found in the database
			$lngmoviecount=0;
			$strsqlcompany="SELECT COUNT(T_WC_TMDB_MOVIE_COMPANY.ID_MOVIE) AS COMPTE FROM T_WC_TMDB_MOVIE_COMPANY
INNER JOIN T_WC_TMDB_MOVIE ON T_WC_TMDB_MOVIE_COMPANY.ID_MOVIE = T_WC_TMDB_MOVIE.ID_MOVIE
WHERE T_WC_TMDB_MOVIE_COMPANY.ID_COMPANY = ".f_longtosql($lngcompanyid)."
";
			$resultcompany=f_sqlselect($strsqlcompany);
			if ($resultcompany) {
				if ($rstempcompany=f_sqlresultfetcharray($resultcompany)) {
					$lngmoviecount=$rstempcompany["COMPTE"];
				}
				f_sqlfreeresult($resultcompany);
			}
			$lngseriecount=0;
			$strsqlseriecount="SELECT COUNT(DISTINCT T_WC_TMDB_SERIE_COMPANY.ID_SERIE) AS COMPTE FROM T_WC_TMDB_SERIE_COMPANY ";
			$strsqlseriecount.="INNER JOIN T_WC_TMDB_SERIE ON T_WC_TMDB_SERIE_COMPANY.ID_SERIE = T_WC_TMDB_SERIE.ID_SERIE ";
			$strsqlseriecount.="WHERE T_WC_TMDB_SERIE_COMPANY.ID_COMPANY = ".f_longtosql($lngcompanyid)." ";
			$resultseriecount=f_sqlselect($strsqlseriecount);
			if ($resultseriecount) {
				if ($rstempseriecount=f_sqlresultfetcharray($resultseriecount)) {
					$lngseriecount=$rstempseriecount["COMPTE"];
				}
				f_sqlfreeresult($resultseriecount);
			}
			//Display company data
			$strcompanysyntheticimagepath=f_getsyntheticimagepath("company",$lngcompanyid);
			//Social preview: same image as the page shows, synthetic card first then the TMDb logo
			$arrsocialcompany=array();
			$arrsocialcompany['description']=$strcompanydesc;
			if ($strcompanysyntheticimagepath!="") {
				$arrsocialcompany['image']=f_socialimageurl($strcompanysyntheticimagepath);
			} elseif ($strcompanylogopath!="") {
				$arrsocialcompany['image']=f_socialimageurl($strtmdbimagespathbaseurl.$strcompanylogopath);
			}
			if (isset($arrsocialcompany['image'])) {
				$arrsocialcompany['imagealt']=$strcompanyname;
			}
			f_headerdisplay($strcompanyname,"",$arrsocialcompany);
			echo "<h1>".get_movie_string('lib_company_name')." : ".$strcompanyname." ".": ".$lngmoviecount." ".get_movie_string('lib_movie_item').", ".$lngseriecount." ".get_movie_string('lib_serie_item')."</h1>";
			if ($strcompanysyntheticimagepath!="") {
				//Padded 2:3 logo card produced by the synthetic-images repo (decision #7)
				echo "<p>";
				echo "<a href=\"".f_htmlattr($strcompanysyntheticimagepath)."\" target=\"_blank\" rel=\"noreferrer\">";
				echo "<img src=\"".f_htmlattr($strcompanysyntheticimagepath)."\" alt=\"".f_htmlattr($strcompanyname)."\" title=\"".f_htmlattr($strcompanyname)."\" width=\"300\" />";
				echo "</a>";
				echo "</p>";
			} elseif ($strcompanylogopath!="") {
				echo "<p>";
				echo "<a href=\"".$strtmdbimagesoriginalpathbaseurl.$strcompanylogopath."\" target=\"_blank\" rel=\"noreferrer\">";
				echo "<img src=\"".$strtmdbimagespathbaseurl.$strcompanylogopath."\" alt=\"".$strcompanyname."\" title=\"".$strcompanyname."\" style=\"background-color: white;\" />";
				echo "</a>";
				echo "</p>";
			}
			echo "<p>";
			echo "<a href=\"images.php?companyid=".$lngcompanyid."\">";
			echo get_movie_string('lib_all_images_company');
			echo "</a>";
			echo "</p>";
			if ($strcompanydesc!="") {
				echo "<p><strong>".get_movie_string('lib_description_section')."</strong>: ".$strcompanydesc."</p>";
			}
			if ($strcompanyheadquarters!="") {
				echo "<p><strong>".get_movie_string('lib_headquarters')."</strong>: ".$strcompanyheadquarters."</p>";
			}
			if ($strcompanyorigcountry!="") {
				echo "<p><strong>".get_movie_string('lib_original_country')."</strong>: ".$strcompanyorigcountry."</p>";
			}
			echo "<p><strong>".get_movie_string('lib_person_record_updated').":</strong> ".$strcompanytimcreditscompleted;
			//Hook entity.actions: action links of the plugins on the record status line
			echo f_pluginhtml("entity.actions",array("type"=>"company","id"=>$lngcompanyid,"slot"=>"record"));
			echo "</p>";
			//Hook entity.status: lines of the plugins under the record status
			echo f_pluginhtml("entity.status",array("type"=>"company","id"=>$lngcompanyid));
			if ($strcompanyidwikidata!="") {
				$strcompanywikidataurl="https://www.wikidata.org/wiki/".$strcompanyidwikidata;
				echo "<p><strong>".get_movie_string('lib_movie_id_wikidata').":</strong> ";
				echo "<a href=\"".$strcompanywikidataurl."\" target=\"_blank\" rel=\"noreferrer\">".$strcompanyidwikidata."</a>";
				echo " <a href=\"".$strcompanywikidataurl."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/Wikidata-logo.svg.png\" width=\"28\" height=\"16\" alt=\"Wikidata\" title=\"Wikidata\"></a>";
				echo "</p>";
				echo f_wikidatamediasection_v2($struilang,$strcompanyidwikidata);
				echo "<h2>".get_movie_string('lib_movie_section_wikidata')."</h2>";
				echo "<p>".f_wikidataallpropertiesv2($struilang,$strcompanyidwikidata,", ")."</p>";
				echo "<h2>".get_movie_string('lib_wikipedia_section')."</h2>";
				echo f_wikipediallsections($struilang,$strcompanyidwikidata,", ");
			}
			if ($lngcompanyparentid>0) {
				echo "<p><strong>".get_movie_string('lib_parent_company')."</strong>: <a href=\"".$strcompanyparenturl."\">".$strcompanyparentname."</a></p>";
			}
			
			$intchildfound=false;
			$strsqlcompanychildren="SELECT * FROM ".$strsqlns."TMDB_COMPANY ";
			$strsqlcompanychildren.="WHERE ID_PARENT = ".f_longtosql($lngcompanyid)." ";
			$strsqlcompanychildren.="ORDER BY ID_COMPANY ";
			$resultcompanychildren=f_sqlselect($strsqlcompanychildren);
			if ($resultcompanychildren) {
				while ($rstempcompanychild=f_sqlresultfetcharray($resultcompanychildren)) {
					if (!$intchildfound) {
						$intchildfound=true;
						echo "<h2>".get_movie_string('lib_child_companies')."</h2>";
					}
					$lngcompanychildid=f_stringtolong($rstempcompanychild['ID_COMPANY']);
					$strcompanychildname=trim($rstempcompanychild['NAME']);
					$strcompanychilddesc=trim($rstempcompanychild['DESCRIPTION']);
					$strcompanychildheadquarters=trim($rstempcompanychild['HEADQUARTERS']);
					$strcompanychildorigcountry=trim($rstempcompanychild['ORIGIN_COUNTRY']);
					$strcompanychildurl="company.php?companyid=".$lngcompanychildid;
					
					echo "<p><a href=\"".$strcompanychildurl."\">"."".$strcompanychildname."</a></p>";
					if ($strcompanychilddesc!="") {
						echo "<p>".$strcompanychilddesc."</p>";
					}
					if ($strcompanychildheadquarters!="") {
						echo "<p>".$strcompanychildheadquarters."</p>";
					}
				}
				f_sqlfreeresult($resultcompanychildren);
			}
			
			
			$strsqlmovielist="";
			$strsqlmovielist.="SELECT T_WC_TMDB_MOVIE_COMPANY.ID_MOVIE, ";
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
			$strsqlmovielist.="FROM T_WC_TMDB_MOVIE_COMPANY ";
			$strsqlmovielist.="INNER JOIN T_WC_TMDB_MOVIE ON T_WC_TMDB_MOVIE_COMPANY.ID_MOVIE = T_WC_TMDB_MOVIE.ID_MOVIE ";
			$strsqlmovielist.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_MOVIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
			if ($struilang!="en") {
				$strsqlmovielist.="LEFT JOIN T_WC_TMDB_MOVIE_LANG ON T_WC_TMDB_MOVIE_COMPANY.ID_MOVIE = T_WC_TMDB_MOVIE_LANG.ID_MOVIE ";
			}
			$strsqlmovielist.="WHERE T_WC_TMDB_MOVIE_COMPANY.ID_COMPANY = ".f_longtosql($lngcompanyid)." ";
			if ($struilang!="en") {
				$strsqlmovielist.="AND T_WC_TMDB_MOVIE_LANG.LANG = ".f_stringtosql($struilang)." ";
			}
			$strsqlmovielist.="ORDER BY T_WC_TMDB_MOVIE.POPULARITY DESC ";
			$strsqlmovielist.="LIMIT 1000 ";
			if ($intdebug) {
				echo "<br />".$strsqlmovielist;
			}
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
					$strmoviecompanies=$rstempmovielist['GENRES'];
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
					f_movielistdisplay($lngmovieid,$strmovietitle,$strmovieoriginaltitle,"",$strmoviereleasedate,$strmovieidimdb,$dblaveragerating,$strmovieoverview,$strmovieposterpath,$strmoviecompanies,3,0,"float",$lngimdbvotes);
				}
				f_sqlfreeresult($resultmovielist);
			}
			echo "</table>";

			// ── Series ────────────────────────────────────────────────────────────
			echo "<h2>".get_movie_string('lib_series_section')."</h2>";
			$strsqlserielist="";
			$strsqlserielist.="SELECT T_WC_TMDB_SERIE_COMPANY.ID_SERIE, ";
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
			$strsqlserielist.="FROM T_WC_TMDB_SERIE_COMPANY ";
			$strsqlserielist.="INNER JOIN T_WC_TMDB_SERIE ON T_WC_TMDB_SERIE_COMPANY.ID_SERIE = T_WC_TMDB_SERIE.ID_SERIE ";
			$strsqlserielist.="LEFT JOIN T_WC_IMDB_MOVIE_RATING_IMPORT ON T_WC_TMDB_SERIE.ID_IMDB = T_WC_IMDB_MOVIE_RATING_IMPORT.tconst ";
			if ($struilang!="en") {
				$strsqlserielist.="LEFT JOIN T_WC_TMDB_SERIE_LANG ON T_WC_TMDB_SERIE_COMPANY.ID_SERIE = T_WC_TMDB_SERIE_LANG.ID_SERIE ";
			}
			$strsqlserielist.="WHERE T_WC_TMDB_SERIE_COMPANY.ID_COMPANY = ".f_longtosql($lngcompanyid)." ";
			if ($struilang!="en") {
				$strsqlserielist.="AND T_WC_TMDB_SERIE_LANG.LANG = ".f_stringtosql($struilang)." ";
			}
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
						$strserietitlelang=$rstempserielist['TITLE_LANG'];
						$strserieoverviewlang=trim($rstempserielist['OVERVIEW_LANG']);
						$strserieposterpathlang=$rstempserielist['POSTER_PATH_LANG'];
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

			//Hook page.beforefooter: blocks of the plugins at the end of the page
			echo f_pluginhtml("page.beforefooter",array("page"=>"company"));
			f_footerdisplay();
		} else {
			//Company record NOT found in the database
			$strmessage=get_movie_string('lib_company_not_found');
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
		}
	}  else {
		//companyid URL parameter not set
		$strmessage=get_movie_string('lib_companyid_not_set');
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
