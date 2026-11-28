<?php
/*
 * Page of one critical reception label (for instance "Cult film" or "Box office bomb"):
 * what the label means, then the movies whose English Wikipedia article states it in words.
 * Each movie shows the detail of the label (scope of a record, countries of a ban) and the
 * sentence of the article that states it.
 */
ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
include_once(__DIR__."/movie-sections.inc.php");
include_once(__DIR__."/reception-format.inc.php");

// The movie list stops here, like the genre pages: the best rated come first.
$lngreceptionmaxmovies=1000;

if ($intdbavailable) {
	$lngreceptionid=f_stringtolong(f_request2("receptionid"));
	$intfound=false;

	if ($lngreceptionid>0) {
		$strsql="SELECT * FROM T_WC_T2S_RECEPTION_LABEL WHERE ID_RECEPTION_LABEL = ".f_longtosql($lngreceptionid)." AND DELETED = 0";
		$result=f_sqlselect($strsql);
		if ($result) {
			if ($rstemp=f_sqlresultfetcharray($result)) {
				$intfound=true;
				$strreceptionname=trim((string)$rstemp['LABEL_NAME']);
				$strreceptionnamefr=trim((string)$rstemp['LABEL_NAME_FR']);
				if ($struilang!="en" && $strreceptionnamefr!="") {
					$strreceptionname=$strreceptionnamefr;
				}
				$strreceptiongroup=trim((string)$rstemp['LABEL_GROUP']);
				$strreceptiondescription=trim((string)$rstemp['DESCRIPTION']);
			}
			f_sqlfreeresult($result);
		}

		if ($intfound) {
			f_headerdisplay($strreceptionname,"",f_socialwikidataentity($strreceptionname,$strreceptiondescription,""));
			echo "<h1>".f_htmlspecialchars($strreceptionname)."</h1>";

			f_moviesectionreset(array("info","movies"));
			ob_start();
			echo f_moviesectionopen("info","🪪",get_movie_string('lib_reception_label'),1);
			if ($strreceptiondescription!="") {
				// The descriptions are written in English only.
				echo "<p>".f_htmlspecialchars($strreceptiondescription)."</p>";
			}
			if ($strreceptiongroup!="") {
				echo "<p><strong>".get_movie_string('lib_reception_group').":</strong> ".f_htmlspecialchars(get_movie_string('lib_reception_group_'.$strreceptiongroup))."</p>";
			}
			echo "<p><small>".f_htmlspecialchars(ucfirst(get_movie_string('lib_movie_reception_source'))).".</small></p>";
			// The other labels, as links, so the visitor can move from one to the next.
			$arrothers=array();
			$resultothers=f_sqlselect("SELECT ID_RECEPTION_LABEL, LABEL_NAME, LABEL_NAME_FR, MOVIE_COUNT FROM T_WC_T2S_RECEPTION_LABEL WHERE DELETED = 0 AND ID_RECEPTION_LABEL <> ".f_longtosql($lngreceptionid)." ORDER BY DISPLAY_ORDER");
			if ($resultothers) {
				while ($rstempother=f_sqlresultfetcharray($resultothers)) {
					$strothername=trim((string)$rstempother['LABEL_NAME']);
					if ($struilang!="en" && trim((string)$rstempother['LABEL_NAME_FR'])!="") {
						$strothername=trim((string)$rstempother['LABEL_NAME_FR']);
					}
					$arrothers[]="<a href=\"reception.php?receptionid=".f_stringtolong($rstempother['ID_RECEPTION_LABEL'])."\">".f_htmlspecialchars($strothername)."</a> (".f_stringtolong($rstempother['MOVIE_COUNT']).")";
				}
				f_sqlfreeresult($resultothers);
			}
			if (count($arrothers)>0) {
				echo "<p><strong>".get_movie_string('lib_reception_other_labels').":</strong> ".implode(", ",$arrothers)."</p>";
			}

			$lngmoviecount=f_stringtolong(f_fieldfromquery("SELECT COUNT(*) FROM T_WC_T2S_MOVIE_RECEPTION_LABEL WHERE ID_RECEPTION_LABEL = ".f_longtosql($lngreceptionid)." AND ID_MOVIE IS NOT NULL AND DELETED = 0"));
			$strmoviesheading=get_movie_string('lib_movies_section').": ".$lngmoviecount;
			if ($lngmoviecount>$lngreceptionmaxmovies) {
				$strmoviesheading.=" (".sprintf(get_movie_string('lib_reception_top_shown'),$lngreceptionmaxmovies).")";
			}
			echo f_moviesectionopen("movies","🎬",$strmoviesheading,1);
			echo "<table>";
			$strsqlmovies="SELECT M.ID_MOVIE, M.MOVIE_TITLE, M.DAT_RELEASE, M.ID_IMDB, M.IMDB_RATING, M.IMDB_VOTES, M.POSTER_PATH, J.DETAIL, J.EVIDENCE ";
			$strsqlmovies.="FROM T_WC_T2S_MOVIE M ";
			$strsqlmovies.="JOIN T_WC_T2S_MOVIE_RECEPTION_LABEL J ON M.ID_MOVIE = J.ID_MOVIE ";
			$strsqlmovies.="WHERE J.ID_RECEPTION_LABEL = ".f_longtosql($lngreceptionid)." AND J.DELETED = 0 ";
			$strsqlmovies.="ORDER BY M.IMDB_RATING_WEIGHTED DESC ";
			$strsqlmovies.="LIMIT ".$lngreceptionmaxmovies;
			$resultmovies=f_sqlselect($strsqlmovies);
			if ($resultmovies) {
				while ($rstempmovie=f_sqlresultfetcharray($resultmovies)) {
					// f_movielistdisplay prints this text as HTML: everything in it is escaped here.
					$strcaption="";
					$strdetail=f_receptiondetail("",$rstempmovie['DETAIL']);
					if ($strdetail!="") {
						$strcaption.="<strong>".f_receptionescape($strdetail)."</strong><br />";
					}
					$strevidence=trim((string)$rstempmovie['EVIDENCE']);
					if ($strevidence!="") {
						$strcaption.="<em>“".f_receptionescape($strevidence)."”</em>";
					}
					f_movielistdisplay($rstempmovie['ID_MOVIE'],$rstempmovie['MOVIE_TITLE'],$rstempmovie['MOVIE_TITLE'],"", $rstempmovie['DAT_RELEASE'],$rstempmovie['ID_IMDB'],$rstempmovie['IMDB_RATING'],$strcaption, $rstempmovie['POSTER_PATH'],"",3,0,"float",$rstempmovie['IMDB_VOTES']);
				}
				f_sqlfreeresult($resultmovies);
			}
			echo "</table>";
			echo f_moviesectionclose();

			//Hook page.beforefooter: blocks of the plugins at the end of the page
			echo f_pluginhtml("page.beforefooter",array("page"=>"reception"));
			//The anchor bar is built from the sections seen: it goes before the buffered body
			$strfichebody=ob_get_clean();
			echo f_movieanchorbar();
			echo $strfichebody;

			f_footerdisplay();
		} else {
			f_headerdisplay(get_movie_string('lib_reception_not_found'));
			echo "<h1>".get_movie_string('lib_reception_not_found')."</h1>";
		}
	} else {
		f_headerdisplay("Invalid ID");
		echo "<h1>Invalid reception label ID.</h1>";
	}
} else {
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}
?>
