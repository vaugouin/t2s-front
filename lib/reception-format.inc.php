<?php

/**
 * Side-effect-free formatting helpers for the critical reception of a movie.
 *
 * A movie carries 0 to n reception labels (for instance "Mixed reviews",
 * "Box office bomb", "Cult film", "Banned"). Each label was stated in words by
 * the movie's Wikipedia article: the row keeps the sentence that states it, and
 * sometimes a detail (the scope of a record, the countries of a ban). These
 * helpers turn the label rows into the one line shown on the movie page.
 *
 * This file deliberately opens no database connection so its behavior can be
 * characterized by test/reception-format-test.php.
 */

if (!function_exists('f_receptionescape')) {
	//Purpose: escape a value for HTML output, text or attribute
	//Params: $strvalue the raw value
	//Returns: the escaped string
	function f_receptionescape($strvalue) {
		return htmlspecialchars((string)$strvalue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('f_receptiondetail')) {
	//Purpose: decide whether the detail of a label is worth showing
	//Params: $strcode the label code, $strdetail the stored detail
	//Returns: the detail to show, "" when there is none or when it says nothing ("other")
	function f_receptiondetail($strcode, $strdetail) {
		$strdetail=trim((string)$strdetail);
		if ($strdetail=="" || strtolower($strdetail)=="other") {
			return "";
		}
		return $strdetail;
	}
}

if (!function_exists('f_receptionlabelhtml')) {
	//Purpose: render one label: its name, the quoted sentence as a tooltip, the detail in parentheses
	//Params: $arrrow a label row (LABEL_CODE, LABEL_NAME, LABEL_NAME_FR, DETAIL, EVIDENCE), $struilang the interface language
	//Returns: the HTML of the label, "" when the row has no name
	function f_receptionlabelhtml($arrrow, $struilang) {
		$strname=trim((string)($arrrow["LABEL_NAME"] ?? ""));
		$strnamefr=trim((string)($arrrow["LABEL_NAME_FR"] ?? ""));
		if ($struilang!="en" && $strnamefr!="") {
			$strname=$strnamefr;
		}
		if ($strname=="") {
			return "";
		}
		$strevidence=trim((string)($arrrow["EVIDENCE"] ?? ""));
		$strhtml=f_receptionescape($strname);
		if ($strevidence!="") {
			// The sentence is quoted word for word from the article: curly quotes mark it as a quotation.
			$strhtml="<span class=\"receptionLabel\" title=\"".f_receptionescape("“".$strevidence."”")."\">".$strhtml."</span>";
		}
		$strdetail=f_receptiondetail((string)($arrrow["LABEL_CODE"] ?? ""), $arrrow["DETAIL"] ?? "");
		if ($strdetail!="") {
			$strhtml.=" (".f_receptionescape($strdetail).")";
		}
		return $strhtml;
	}
}

if (!function_exists('f_receptionlabelshtml')) {
	//Purpose: render the labels of a movie as one comma-separated line, in the order of the rows
	//Params: $arrrows the label rows, $struilang the interface language
	//Returns: the HTML of the line without its heading, "" when there is no label
	function f_receptionlabelshtml($arrrows, $struilang) {
		$arrparts=array();
		$arrseen=array();
		foreach ($arrrows as $arrrow) {
			$strcode=(string)($arrrow["LABEL_CODE"] ?? "");
			if ($strcode!="" && isset($arrseen[$strcode])) {
				continue;
			}
			$strpart=f_receptionlabelhtml($arrrow, $struilang);
			if ($strpart=="") {
				continue;
			}
			$arrseen[$strcode]=true;
			$arrparts[]=$strpart;
		}
		return implode(", ", $arrparts);
	}
}

if (!function_exists('f_receptioncinemascore')) {
	//Purpose: validate an audience grade (A+ to F)
	//Params: $strvalue the stored grade, possibly NULL or empty
	//Returns: the grade, "" when it is missing or not a grade
	function f_receptioncinemascore($strvalue) {
		$strgrade=strtoupper(trim((string)$strvalue));
		if (!preg_match('/^(?:[A-D][+-]?|F)$/', $strgrade)) {
			return "";
		}
		return $strgrade;
	}
}
