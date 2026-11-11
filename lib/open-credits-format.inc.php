<?php
/*
 * open-credits-format.inc.php - pure helpers of the open-credits section of the movie page
 *
 * No database and no output here, so test/open-credits-format-test.php can load it alone
 * (the structural rule of AGENTS.md: global-light.inc.php opens a connection when it loads).
 */

if (!function_exists('f_ocmstime')) {
	function f_ocmstime($lngms) {
		//Purpose: A time in the video, from milliseconds: 1:52:03, or 4:05 under an hour
		$lngs=intdiv(max(0,(int)$lngms),1000);
		$lngh=intdiv($lngs,3600);
		$lngm=intdiv($lngs%3600,60);
		$lngsec=$lngs%60;
		if ($lngh>0) return sprintf("%d:%02d:%02d",$lngh,$lngm,$lngsec);
		return sprintf("%d:%02d",$lngm,$lngsec);
	}
}

if (!function_exists('f_ocparistime')) {
	function f_ocparistime($strutc) {
		//Purpose: T_WC_OC_SUBMISSION stores UTC (its DDL says so); the site shows Paris time with its offset
		if ($strutc===null || $strutc==="") return "";
		$dat=new DateTime($strutc, new DateTimeZone('UTC'));
		$dat->setTimezone(new DateTimeZone('Europe/Paris'));
		return $dat->format('Y-m-d H:i P');
	}
}

if (!function_exists('f_ocrows')) {
	function f_ocrows($arrlines,$strlayout) {
		//Purpose: Puts the text lines of one segment back into screen rows, left to right, so that
		//         a name and its role printed side by side read as one row.
		//         Rolling credits: one tall page, lines ordered by Y_PAGE.
		//         Cards: one card after the other, by FIRST_MS, then by Y_PAGE on the card.
		//         Two lines share a row when their tops are less than half a line height apart.
		//Input:   arrays with Y_PAGE, X, H, FIRST_MS (and whatever else the caller keeps)
		//Returns: a list of rows, each a list of lines ordered by X
		//Cards first: split by FIRST_MS (a new card when a line appears over 2 s after the card's first)
		$arrcards=array();
		if ($strlayout==="rolling") {
			$arrcards[]=$arrlines;
		} else {
			usort($arrlines,function($a,$b) { return (int)$a['FIRST_MS'] <=> (int)$b['FIRST_MS']; });
			$lngstart=null;
			foreach ($arrlines as $arrline) {
				if ($lngstart===null || (int)$arrline['FIRST_MS']-$lngstart>2000) {
					$arrcards[]=array();
					$lngstart=(int)$arrline['FIRST_MS'];
				}
				$arrcards[count($arrcards)-1][]=$arrline;
			}
		}
		$arrrows=array();
		foreach ($arrcards as $arrcard) {
			usort($arrcard,function($a,$b) { return [(int)$a['Y_PAGE'],(int)$a['X']] <=> [(int)$b['Y_PAGE'],(int)$b['X']]; });
			$arrrow=array();
			foreach ($arrcard as $arrline) {
				if ($arrrow && abs((int)$arrline['Y_PAGE']-(int)$arrrow[0]['Y_PAGE'])*2>=max((int)$arrline['H'],(int)$arrrow[0]['H'],1)) {
					$arrrows[]=$arrrow;
					$arrrow=array();
				}
				$arrrow[]=$arrline;
			}
			if ($arrrow) $arrrows[]=$arrrow;
		}
		foreach ($arrrows as $i=>$arrrow) {
			usort($arrrow,function($a,$b) { return (int)$a['X'] <=> (int)$b['X']; });
			$arrrows[$i]=$arrrow;
		}
		return $arrrows;
	}
}

if (!function_exists('f_ocpageurl')) {
	function f_ocpageurl($lngpage,$strsearch) {
		//Purpose: The URL of one page of open-credits.php, keeping the title filter
		$strurl="open-credits.php?page=".max(1,(int)$lngpage);
		if ((string)$strsearch!=="") $strurl.="&q=".rawurlencode((string)$strsearch);
		return $strurl;
	}
}

if (!function_exists('f_ocpagination')) {
	function f_ocpagination($lngpage,$lngtotalpages,$strsearch,$strprevious,$strnext) {
		//Purpose: Previous, page numbers (first, last, and two around the current one, gaps as …), next
		//Returns: HTML, empty when everything fits on one page
		$lngpage=max(1,(int)$lngpage);
		$lngtotalpages=max(1,(int)$lngtotalpages);
		if ($lngtotalpages<=1) return "";
		$arrparts=array();
		if ($lngpage>1) $arrparts[]="<a rel=\"prev\" href=\"".htmlspecialchars(f_ocpageurl($lngpage-1,$strsearch),ENT_QUOTES)."\">".htmlspecialchars($strprevious,ENT_QUOTES)."</a>";
		$lnglast=0;
		for ($i=1;$i<=$lngtotalpages;$i++) {
			if ($i!=1 && $i!=$lngtotalpages && abs($i-$lngpage)>2) continue;
			if ($lnglast && $i>$lnglast+1) $arrparts[]="…";
			$arrparts[]=($i==$lngpage) ? "<strong>".$i."</strong>" : "<a href=\"".htmlspecialchars(f_ocpageurl($i,$strsearch),ENT_QUOTES)."\">".$i."</a>";
			$lnglast=$i;
		}
		if ($lngpage<$lngtotalpages) $arrparts[]="<a rel=\"next\" href=\"".htmlspecialchars(f_ocpageurl($lngpage+1,$strsearch),ENT_QUOTES)."\">".htmlspecialchars($strnext,ENT_QUOTES)."</a>";
		return "<nav class=\"oc-pagination\"><p>".implode(" ",$arrparts)."</p></nav>";
	}
}
