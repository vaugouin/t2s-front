<?php

/**
 * Side-effect-free formatting helpers for award wins.
 *
 * An award link row is one win: the same award can be won several times by the
 * same movie, series or person, each win with its own year and, for a person,
 * the work it was won for. These helpers turn the win rows into the short text
 * shown beside an award or a winner.
 *
 * This file deliberately opens no database connection so its behavior can be
 * characterized by test/award-format-test.php.
 */

if (!function_exists('f_awardescape')) {
	//Purpose: escape a value for HTML output
	//Params: $strvalue the raw value
	//Returns: the escaped string
	function f_awardescape($strvalue) {
		return htmlspecialchars((string)$strvalue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('f_awardyear')) {
	//Purpose: read the year of a win
	//Params: $strvalue the AWARD_YEAR column, possibly NULL or empty
	//Returns: the year as an integer, 0 when it is not known
	function f_awardyear($strvalue) {
		$stryear=trim((string)$strvalue);
		if (!preg_match('/^[0-9]{4}$/', $stryear)) {
			return 0;
		}
		return (int)$stryear;
	}
}

if (!function_exists('f_awardwinsbykey')) {
	//Purpose: group win rows by the entity or the award they belong to, keeping the order of the rows
	//Params: $arrrows the win rows, $strkey the column to group on
	//Returns: an array key => list of rows; rows without a key are skipped
	function f_awardwinsbykey($arrrows, $strkey) {
		$arrgroups=array();
		foreach ((array)$arrrows as $arrrow) {
			$strvalue=trim((string)($arrrow[$strkey] ?? ""));
			if ($strvalue==="") {
				continue;
			}
			if (!isset($arrgroups[$strvalue])) {
				$arrgroups[$strvalue]=array();
			}
			$arrgroups[$strvalue][]=$arrrow;
		}
		return $arrgroups;
	}
}

if (!function_exists('f_awardworkofwin')) {
	//Purpose: find the work a person won an award for, in one win row
	//Params: $arrwin a win row with ID_MOVIE_FOR, MOVIE_TITLE, MOVIE_TITLE_FR, ID_SERIE_FOR, SERIE_TITLE, SERIE_TITLE_FR; $strlang the interface language
	//Returns: array("url"=>..., "title"=>...) or an empty array when the row names no work with a title
	function f_awardworkofwin($arrwin, $strlang) {
		foreach (array(array("ID_MOVIE_FOR","MOVIE_TITLE","movie.php?movieid="), array("ID_SERIE_FOR","SERIE_TITLE","serie.php?serieid=")) as $arrkind) {
			$lngid=(int)($arrwin[$arrkind[0]] ?? 0);
			if ($lngid<=0) {
				continue;
			}
			$strtitle=trim((string)($arrwin[$arrkind[1]] ?? ""));
			$strtitlelang=trim((string)($arrwin[$arrkind[1]."_FR"] ?? ""));
			if ($strlang!="en" && $strtitlelang!=="") {
				$strtitle=$strtitlelang;
			}
			if ($strtitle==="") {
				continue;
			}
			return array("url"=>$arrkind[2].$lngid, "title"=>$strtitle);
		}
		return array();
	}
}

if (!function_exists('f_awardwinshtml')) {
	//Purpose: the wins of one award by one winner, as a short HTML text: "1934 for Morning Glory, 1968, 1969"
	//Params: $arrwins the win rows of the pair (AWARD_YEAR, and for a person the work columns), $strlang the interface language, $strforformat the "for %s" label
	//Returns: the HTML text, empty when no win carries a year or a work; a repeated entry is written once, known years come first in ascending order
	function f_awardwinshtml($arrwins, $strlang="en", $strforformat="for %s") {
		$arrentries=array();
		foreach ((array)$arrwins as $arrwin) {
			$lngyear=f_awardyear($arrwin["AWARD_YEAR"] ?? "");
			$arrwork=f_awardworkofwin($arrwin, $strlang);
			$strentry="";
			if ($lngyear>0) {
				$strentry=(string)$lngyear;
			}
			if ($arrwork) {
				$strlink="<a href=\"".f_awardescape($arrwork["url"])."\">".f_awardescape($arrwork["title"])."</a>";
				$strentry.=($strentry!==""?" ":"").sprintf(f_awardescape($strforformat), $strlink);
			}
			if ($strentry==="") {
				continue;
			}
			$strsort=sprintf("%d-%04d", ($lngyear>0?0:1), $lngyear);
			if (!isset($arrentries[$strentry])) {
				$arrentries[$strentry]=$strsort;
			}
		}
		asort($arrentries, SORT_STRING);
		return implode(", ", array_keys($arrentries));
	}
}

if (!function_exists('f_awardwinscaption')) {
	//Purpose: the caption under a winner in a list: "Won: 1934 for Morning Glory, 1968"
	//Params: $arrwins the win rows of the pair, $strlang the interface language, $strwonformat the "Won: %s" label, $strforformat the "for %s" label
	//Returns: the HTML caption in a <small> element, empty when there is nothing to say
	function f_awardwinscaption($arrwins, $strlang="en", $strwonformat="Won: %s", $strforformat="for %s") {
		$strwins=f_awardwinshtml($arrwins, $strlang, $strforformat);
		if ($strwins==="") {
			return "";
		}
		return "<small>".sprintf(f_awardescape($strwonformat), $strwins)."</small>";
	}
}
