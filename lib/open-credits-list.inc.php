<?php
/*
 * open-credits-list.inc.php - open-credits.php: the movies whose credits open-credits has read
 *
 * Every movie with a published submission in T_WC_OC_*, the latest reading first, 50 per page,
 * with a title filter. Each title links to its movie page, at the open-credits section.
 */
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
include_once(__DIR__."/work-properties-format.inc.php");
require_once(__DIR__."/open-credits-format.inc.php");

$lngocrowsperpage=50;

if ($intdbavailable) {
	$strtitle=get_movie_string('lib_oc_list_title');
	f_headerdisplay($strtitle);
	$strsearch=trim((string)f_request2("q"));
	$lngpage=max(1,f_stringtolong(f_request2("page")));

	$strsql="SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('T_WC_OC_WORK','T_WC_OC_COPY','T_WC_OC_SUBMISSION','T_WC_OC_SEGMENT','T_WC_OC_TEXT_LINE')";
	if (f_stringtolong(f_fieldfromquery($strsql))<5) {
		echo "<h1>".$strtitle."</h1>";
		echo "<p><em>".get_movie_string('lib_movie_oc_no_schema')."</em></p>";
	} else {
		//The works with a published submission, filtered on their titles
		$strfrom="
FROM T_WC_OC_WORK W
LEFT JOIN T_WC_TMDB_MOVIE M ON M.ID_MOVIE = W.ID_MOVIE
WHERE W.DELETED = 0
AND EXISTS (SELECT 1 FROM T_WC_OC_COPY C INNER JOIN T_WC_OC_SUBMISSION S ON S.ID_OC_COPY = C.ID_OC_COPY
            WHERE C.ID_OC_WORK = W.ID_OC_WORK AND C.DELETED = 0 AND S.DELETED = 0 AND S.STATUS = 'published')
";
		if ($strsearch!=="") {
			$strlike=f_stringtosql("%".$strsearch."%");
			$strfrom.="AND (W.ORIGINAL_TITLE LIKE ".$strlike." OR W.TITLE_EN LIKE ".$strlike." OR M.TITLE LIKE ".$strlike.") ";
		}
		$lngtotal=f_stringtolong(f_fieldfromquery("SELECT COUNT(*) ".$strfrom));
		$arrpageinfo=f_workpropertypaginationinfo($lngpage,$lngocrowsperpage,$lngtotal);

		echo "<h1>".$strtitle.": ".$lngtotal." ".get_movie_string('lib_movie_item')."</h1>";
		echo "<p><small>".get_movie_string('lib_oc_list_intro')."</small></p>";
		echo "<form method=\"get\" action=\"open-credits.php\"><p>";
		echo "<input type=\"search\" name=\"q\" value=\"".f_htmlspecialchars($strsearch)."\" placeholder=\"".f_htmlspecialchars(get_movie_string('lib_oc_list_search'))."\" class=\"responsive-input\"> ";
		echo "<input type=\"submit\" value=\"".f_htmlspecialchars(get_movie_string('lib_oc_list_filter'))."\">";
		if ($strsearch!=="") echo " <a href=\"open-credits.php\">".get_movie_string('lib_oc_list_all')."</a>";
		echo "</p></form>";

		if ($lngtotal==0) {
			echo "<p>".get_movie_string($strsearch!=="" ? 'lib_oc_list_none_found' : 'lib_oc_list_none')."</p>";
		} else {
			//The latest submission of each work on the page, and its line count
			$strsql="
SELECT W.ID_MOVIE, COALESCE(M.TITLE, W.TITLE_EN, W.ORIGINAL_TITLE) AS TITLE, W.ORIGINAL_TITLE, W.YEAR,
(SELECT S.ID_OC_SUBMISSION FROM T_WC_OC_COPY C INNER JOIN T_WC_OC_SUBMISSION S ON S.ID_OC_COPY = C.ID_OC_COPY
 WHERE C.ID_OC_WORK = W.ID_OC_WORK AND C.DELETED = 0 AND S.DELETED = 0 AND S.STATUS = 'published'
 ORDER BY S.SUBMITTED_AT DESC LIMIT 1) AS ID_LAST,
(SELECT MAX(S.SUBMITTED_AT) FROM T_WC_OC_COPY C INNER JOIN T_WC_OC_SUBMISSION S ON S.ID_OC_COPY = C.ID_OC_COPY
 WHERE C.ID_OC_WORK = W.ID_OC_WORK AND C.DELETED = 0 AND S.DELETED = 0 AND S.STATUS = 'published') AS LAST_UTC
".$strfrom."
ORDER BY LAST_UTC DESC, W.ID_MOVIE
LIMIT ".(int)$arrpageinfo['rows_per_page']." OFFSET ".(int)$arrpageinfo['offset'];
			$arrrows=array();
			$result=f_sqlselect($strsql);
			if ($result) {
				while ($rs=f_sqlresultfetcharray($result)) $arrrows[]=$rs;
				f_sqlfreeresult($result);
			}
			$arrlines=array();
			$arrids=array();
			foreach ($arrrows as $rs) if ($rs['ID_LAST']) $arrids[]=f_stringtosql($rs['ID_LAST']);
			if ($arrids) {
				$strsql="SELECT G.ID_OC_SUBMISSION, COUNT(L.ID_OC_TEXT_LINE) AS LINES_COUNT FROM T_WC_OC_SEGMENT G
LEFT JOIN T_WC_OC_TEXT_LINE L ON L.ID_OC_SEGMENT = G.ID_OC_SEGMENT AND L.DELETED = 0
WHERE G.DELETED = 0 AND G.ID_OC_SUBMISSION IN (".implode(",",$arrids).") GROUP BY G.ID_OC_SUBMISSION";
				$result=f_sqlselect($strsql);
				if ($result) {
					while ($rs=f_sqlresultfetcharray($result)) $arrlines[(string)$rs['ID_OC_SUBMISSION']]=(int)$rs['LINES_COUNT'];
					f_sqlfreeresult($result);
				}
			}

			$strpagination=f_ocpagination($arrpageinfo['page'],$arrpageinfo['total_pages'],$strsearch,
				get_movie_string('lib_oc_list_previous'),get_movie_string('lib_oc_list_next'));
			echo "<p>".sprintf(get_movie_string('lib_oc_list_summary'),$arrpageinfo['first_row'],$arrpageinfo['last_row'],$lngtotal)."</p>";
			echo $strpagination;
			echo "<table>";
			echo "<tr><th align=\"left\">".get_movie_string('lib_oc_list_movie')."</th><th align=\"right\">".get_movie_string('lib_oc_list_lines')."</th><th align=\"left\">".get_movie_string('lib_oc_list_read')."</th></tr>";
			foreach ($arrrows as $rs) {
				$strname=f_htmlspecialchars($rs['TITLE']);
				if ($rs['ORIGINAL_TITLE'] && $rs['ORIGINAL_TITLE']!==$rs['TITLE']) $strname.=" <small>(".f_htmlspecialchars($rs['ORIGINAL_TITLE']).")</small>";
				if ($rs['YEAR']) $strname.=" ".(int)$rs['YEAR'];
				$strlink=$rs['ID_MOVIE'] ? "<a href=\"movie.php?movieid=".(int)$rs['ID_MOVIE']."#open-credits\">".$strname."</a>" : $strname;
				echo "<tr><td>".$strlink."</td>";
				echo "<td align=\"right\">".($arrlines[(string)$rs['ID_LAST']] ?? 0)."</td>";
				echo "<td>".f_htmlspecialchars(f_ocparistime($rs['LAST_UTC']))."</td></tr>";
			}
			echo "</table>";
			echo $strpagination;
		}
	}
	f_footerdisplay();
} else {
	//Database is NOT available
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}
?>
