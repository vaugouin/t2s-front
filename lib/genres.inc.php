<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	//Database is available
	//Read lists data from the MySQL database
	//Display list data
	$lngdisplayorder=0;
	f_headerdisplay(get_movie_string('lib_all_genres'));
	$strsqlgenre="SELECT * FROM ".$strsqlns."TMDB_GENRE ";
	$strsqlgenre.="ORDER BY name ";
	$resultgenre=f_sqlselect($strsqlgenre);
	if ($resultgenre) {
		while ($rstempgenre=f_sqlresultfetcharray($resultgenre)) {
			$lngdisplayorder++;
			if ($lngdisplayorder==1) {
				$lnglistcount=f_sqlresultrowcount($resultgenre);
				echo "<h1>".get_movie_string('lib_all_genres').": ".$lnglistcount." ".get_movie_string('lib_genre_item')."</h1>";
			}
			$lnggenreid=f_stringtolong($rstempgenre['id']);
			$strgenrename=trim($rstempgenre['name']);
			
			/*
			if ($struilang!="en") {
				//$strlistshortnamelang=trim(f_descfromcode($strsqlns."TMDB_LIST_LANG","id","SHORT_NAME",f_longtosql($lnggenreid)));
				$strsql="SELECT SHORT_NAME FROM ".$strsqlns."TMDB_LIST_LANG WHERE DELETED = 0 AND id = ".f_longtosql($lnggenreid)." AND LANG = ".f_stringtosql($struilang);
				$strlistshortnamelang=trim(f_fieldfromquery($strsql));
			}
			if ($strlistshortnamelang!="") {
				$strlistshortname=$strlistshortnamelang;
			}
			*/
			$strgenreurl="genre.php?genreid=".$lnggenreid;
			$strlistbadge="";
			//Hook index.row.badge: marks added by the plugins beside the name
			$strlistbadge=f_pluginhtml("index.row.badge",array("type"=>"genre","id"=>$lnggenreid));
			
			echo "<h2><a href=\"".$strgenreurl."\">".$lngdisplayorder.": ".$strgenrename.$strlistbadge."</a></h2>";
		}
		f_sqlfreeresult($resultgenre);
	}
	f_footerdisplay();
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
