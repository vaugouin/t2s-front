<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

if ($intdbavailable) {
	//Database is available
	$strpropertyid=f_request2("propertyid");
	$intpropertyfound=false;
	
	if ($strpropertyid!="") {
		//Read property data from the MySQL database
		$strsqlproperty="SELECT * FROM T_WC_WIKIDATA_PROPERTY WHERE ID_PROPERTY = ".f_stringtosql($strpropertyid)." ";
		$resultproperty=f_sqlselect($strsqlproperty);
		if ($resultproperty) {
			if ($rstempproperty=f_sqlresultfetcharray($resultproperty)) {
				$intpropertyfound=true;
				$strpropertylabel=$rstempproperty['LABEL'];
				$strpropertydesc=$rstempproperty['DESCRIPTION'];
				$strpropertyenname="";
			}
			f_sqlfreeresult($resultproperty);
		}
		
		if ($intpropertyfound) {
			//Property record found in the database
			$lngmoviecount=0;
			/*
			$strsqlproperty="SELECT COUNT(T_WC_TMDB_MOVIE_KEYWORD.ID_MOVIE) AS COMPTE FROM T_WC_TMDB_MOVIE_KEYWORD 
INNER JOIN T_WC_TMDB_MOVIE ON T_WC_TMDB_MOVIE_KEYWORD.ID_MOVIE = T_WC_TMDB_MOVIE.ID_MOVIE
WHERE T_WC_TMDB_MOVIE_KEYWORD.ID_KEYWORD = ".f_longtosql($strpropertyid)." 
";
			$resultproperty=f_sqlselect($strsqlproperty);
			if ($resultproperty) {
				if ($rstempproperty=f_sqlresultfetcharray($resultproperty)) {
					$lngmoviecount=$rstempproperty["COMPTE"];
				}
				f_sqlfreeresult($resultproperty);
			}
			*/
			//Display property data
			f_headerdisplay($strpropertylabel);
			echo "<h1>Property : ".$strpropertylabel.": ".$lngmoviecount." ".get_movie_string('lib_movie_item')."</h1>";
			if ($strpropertydesc!="") {
				echo "<p><strong>".get_movie_string('lib_description_section')."</strong>: ".$strpropertydesc."</p>";
			}
			$strsqlitems="";
			$strsqlitems.="SELECT COUNT(V_WIKIDATA_ITEM_PROPERTY.ID_ROW) AS COMPTE, V_WIKIDATA_ITEM_PROPERTY.ID_ITEM AS ID_WIKIDATA, T_WC_WIKIDATA_ITEM_V1.LABEL AS ITEM_LABEL, T_WC_WIKIDATA_ITEM_V1.DESCRIPTION AS ITEM_DESC ";
			$strsqlitems.="FROM V_WIKIDATA_ITEM_PROPERTY ";
			$strsqlitems.="LEFT JOIN T_WC_WIKIDATA_ITEM_V1 ON V_WIKIDATA_ITEM_PROPERTY.ID_ITEM = T_WC_WIKIDATA_ITEM_V1.ID_WIKIDATA ";
			$strsqlitems.="WHERE V_WIKIDATA_ITEM_PROPERTY.ID_PROPERTY = ".f_stringtosql($strpropertyid)." ";
			//$strsqlitems.="AND T_WC_WIKIDATA_ITEM.LANG = 'en' ";
			$strsqlitems.="GROUP BY V_WIKIDATA_ITEM_PROPERTY.ID_ITEM ";
			$strsqlitems.="ORDER BY V_WIKIDATA_ITEM_PROPERTY.ID_ITEM, T_WC_WIKIDATA_ITEM_V1.LABEL ";
			if ($intdebug) {
				echo "<br />".$strsqlitems;
			}
			$resultitems=f_sqlselect($strsqlitems);
			if ($resultitems) {
				while ($rstempitem=f_sqlresultfetcharray($resultitems)) {
					$lngdisplayorder++;
					if ($lngdisplayorder==1) {
						//$lngitemscount=f_sqlresultrowcount($resultitems);
						//echo "<h1>".$strliballitemss.": ".$lngitemscount." ".$strlibitemsitem."</h1>";
					}
					$stritemid=$rstempitem['ID_WIKIDATA'];
					$stritemlabel=$rstempitem['ITEM_LABEL'];
					$stritemdesc=$rstempitem['ITEM_DESC'];
					//COMPTE id doubled -> investigate!
					$lngitemcount=f_stringtolong($rstempitem['COMPTE']);
					$stritemurl="";
					$stritemurl="wikidata-query.php?search=".$strpropertyid." ".$stritemid;
					if ($stritemlabel=="") {
						$stritemlabel=$stritemid;
					}
					
					echo "<h2><a href=\"".$stritemurl."\">".$stritemlabel."</a> ".$stritemid." "."</h2>";
					if ($stritemdesc!="") {
						echo "<p>";
						echo "".$stritemdesc."";
						echo "</p>";
					}
				}
			}
			
			
			f_footerdisplay();
		} else {
			//Property record NOT found in the database
			$strmessage="Property record NOT found in the database";
			f_headerdisplay($strmessage);
			echo "<h1>".$strmessage."</h1>";
		}
	}  else {
		//propertyid URL parameter not set
		$strmessage="propertyid URL parameter not set";
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
