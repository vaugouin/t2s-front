<?php
/*
 * open-credits.inc.php - the open-credits section of the movie page: the credits read on screen
 *
 * A raw reader (raw-first gate of the T2S backlog): the text lines of the latest published
 * submission of the movie, as the open-credits client read them from a copy, segment by segment,
 * loaded by the open-credits importer into T_WC_OC_*. Lines are OCR, not checked by hand: the
 * legend says so, and a line read with a low confidence is greyed. Shown in private and public
 * mode, a single submission being authoritative (charter decision 15).
 *
 * Tells apart: tables not deployed (a note), movie never read, read without any text, read.
 * Needs global-light.inc.php (database) and language.inc.php (labels), already loaded by the page.
 */

require_once(__DIR__."/open-credits-format.inc.php");

if (!function_exists('f_ocsection')) {
	function f_ocsection($lngmovieid) {
		//Purpose: The HTML of the section, title included
		//Returns: a string, empty when there is nothing to say
		$strout="<a name=\"open-credits\"></a><h2>".get_movie_string('lib_movie_oc_title')."</h2>";
		$strsql="SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('T_WC_OC_WORK','T_WC_OC_SUBMISSION','T_WC_OC_TEXT_LINE')";
		if (f_stringtolong(f_fieldfromquery($strsql))<3) {
			return $strout."<p><em>".get_movie_string('lib_movie_oc_no_schema')."</em></p>";
		}
		$strsql="
SELECT S.ID_OC_SUBMISSION, S.CLIENT_NAME, S.CLIENT_VERSION, S.OCR_ENGINE, S.OCR_ENGINE_VERSION, S.PARAMETERS, S.SUBMITTED_AT,
C.HEIGHT, C.DURATION_MS
FROM T_WC_OC_WORK W
INNER JOIN T_WC_OC_COPY C ON C.ID_OC_WORK = W.ID_OC_WORK AND C.DELETED = 0
INNER JOIN T_WC_OC_SUBMISSION S ON S.ID_OC_COPY = C.ID_OC_COPY AND S.DELETED = 0 AND S.STATUS = 'published'
WHERE W.ID_MOVIE = ".f_longtosql($lngmovieid)." AND W.DELETED = 0
ORDER BY S.SUBMITTED_AT DESC
";
		$arrsubs=array();
		$result=f_sqlselect($strsql);
		if ($result) {
			while ($rs=f_sqlresultfetcharray($result)) $arrsubs[]=$rs;
			f_sqlfreeresult($result);
		}
		if (!$arrsubs) return $strout."<p>".get_movie_string('lib_movie_oc_never')."</p>";
		$rssub=$arrsubs[0];
		$arrparams=json_decode((string)$rssub['PARAMETERS'],true);
		$strlang=is_array($arrparams) && isset($arrparams['lang']) ? $arrparams['lang'] : "";

		$strsql="
SELECT G.ID_OC_SEGMENT, G.KIND, G.LAYOUT, G.START_MS, G.END_MS,
L.Y_PAGE, L.X, L.H, L.TEXT, L.READINGS, L.AGREEING, L.CONFIDENCE, L.FIRST_MS
FROM T_WC_OC_SEGMENT G
LEFT JOIN T_WC_OC_TEXT_LINE L ON L.ID_OC_SEGMENT = G.ID_OC_SEGMENT AND L.DELETED = 0
WHERE G.ID_OC_SUBMISSION = ".f_stringtosql($rssub['ID_OC_SUBMISSION'])." AND G.DELETED = 0
ORDER BY G.START_MS, G.DISPLAY_ORDER
";
		$arrsegs=array();
		$lnglines=0;
		$result=f_sqlselect($strsql);
		if ($result) {
			while ($rs=f_sqlresultfetcharray($result)) {
				$strid=$rs['ID_OC_SEGMENT'];
				if (!isset($arrsegs[$strid])) $arrsegs[$strid]=array('KIND'=>$rs['KIND'],'LAYOUT'=>$rs['LAYOUT'],'START_MS'=>$rs['START_MS'],'END_MS'=>$rs['END_MS'],'LINES'=>array());
				if ($rs['TEXT']!==null) {
					$arrsegs[$strid]['LINES'][]=$rs;
					$lnglines++;
				}
			}
			f_sqlfreeresult($result);
		}

		$strout.="<p>".get_movie_string('lib_movie_oc_read')." ".f_htmlspecialchars(f_ocparistime($rssub['SUBMITTED_AT']))
			." · ".f_htmlspecialchars($rssub['CLIENT_NAME']." ".$rssub['CLIENT_VERSION'])
			." · ".f_htmlspecialchars($rssub['OCR_ENGINE']." ".$rssub['OCR_ENGINE_VERSION'].($strlang!=="" ? " (".$strlang.")" : ""))
			.($rssub['HEIGHT'] ? " · ".f_htmlspecialchars($rssub['HEIGHT'])."p" : "")
			." · ".$lnglines." ".get_movie_string('lib_movie_oc_lines');
		if (count($arrsubs)>1) $strout.=" · ".(count($arrsubs)-1)." ".get_movie_string('lib_movie_oc_earlier');
		$strout.="<br /><small>".get_movie_string('lib_movie_oc_legend')." <a href=\"open-credits.php\">".get_movie_string('lib_oc_list_title')."</a></small></p>";
		if ($lnglines==0) return $strout."<p>".get_movie_string('lib_movie_oc_no_text')."</p>";

		foreach ($arrsegs as $arrseg) {
			if (!$arrseg['LINES']) continue;
			$strkind=get_movie_string('lib_movie_oc_kind_'.$arrseg['KIND']);
			if ($strkind==='lib_movie_oc_kind_'.$arrseg['KIND'] || $strkind==="") $strkind=str_replace("_"," ",$arrseg['KIND']);
			$strout.="<h3 style=\"margin:0.8em 0 0.3em\">".f_htmlspecialchars($strkind)." · ".f_ocmstime($arrseg['START_MS'])." – ".f_ocmstime($arrseg['END_MS'])
				." <small>(".f_htmlspecialchars($arrseg['LAYOUT']).")</small></h3>";
			//Compact: one screen row per text line, its parts joined by a dot, in a single column
			$strout.="<div style=\"line-height:1.4;margin:0 0 1.2em\">";
			foreach (f_ocrows($arrseg['LINES'],$arrseg['LAYOUT']) as $arrrow) {
				$arrparts=array();
				foreach ($arrrow as $arrline) {
					$intlow=(float)$arrline['CONFIDENCE']<0.8;
					$strtitle=f_ocmstime($arrline['FIRST_MS'])." · ".get_movie_string('lib_movie_oc_confidence')." "
						.number_format((float)$arrline['CONFIDENCE'],2)." · ".(int)$arrline['AGREEING']."/".(int)$arrline['READINGS']." ".get_movie_string('lib_movie_oc_frames');
					$arrparts[]="<span title=\"".f_htmlspecialchars($strtitle)."\"".($intlow ? " style=\"color:#999\"" : "").">"
						.f_htmlspecialchars($arrline['TEXT'])."</span>";
				}
				$strout.="<div>".implode(" <span style=\"color:#888\">·</span> ",$arrparts)."</div>";
			}
			$strout.="</div>";
		}
		return $strout;
	}
}
