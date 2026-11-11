<?php
/*
 * social-ids.inc.php - the social-network ids of a person, movie or series, read raw
 *
 * The crawler keeps the social-network ids that TMDb publishes in external_ids:
 * FACEBOOK_ID, INSTAGRAM_ID, TWITTER_ID on persons, movies and series, plus TIKTOK_ID
 * and YOUTUBE_ID on persons, and TIM_EXTERNAL_IDS, the time they were read. This file
 * turns one fetched row into one line of the identifiers block. It needs neither a
 * database nor a network, so test/social-ids-test.php loads it alone.
 *
 * Three states must stay distinct on the page:
 *   - TIM_EXTERNAL_IDS NULL: the row has not been read since the columns exist;
 *   - TIM_EXTERNAL_IDS set, every id empty: read, TMDb has none;
 *   - TIM_EXTERNAL_IDS set, some ids: read, one link per id.
 * A fourth one, the columns absent from the row (a database without them), prints
 * nothing, so a partial deployment never breaks the page.
 */

if (!function_exists('f_socialidsnetworks')) {
	function f_socialidsnetworks() {
		//Purpose: The networks, in display order: column => label
		//Returns: Array of column name => network label
		return array(
			"FACEBOOK_ID"=>"Facebook",
			"INSTAGRAM_ID"=>"Instagram",
			"TWITTER_ID"=>"X",
			"TIKTOK_ID"=>"TikTok",
			"YOUTUBE_ID"=>"YouTube",
		);
	}
}

if (!function_exists('f_socialidsfromrow')) {
	function f_socialidsfromrow($arrrow) {
		//Purpose: Reads the social-network state of one fetched person, movie or series row
		//Params:	$arrrow: The row as fetched with SELECT *, or anything else
		//Returns: array("state"=>"absent"|"unread"|"read", "read"=>timestamp or "", "ids"=>array(column=>value))
		//         "ids" only holds the non-empty ids of the columns present in the row
		$arrresult=array("state"=>"absent","read"=>"","ids"=>array());
		if (!is_array($arrrow) || !array_key_exists("TIM_EXTERNAL_IDS",$arrrow)) {
			return $arrresult;
		}
		$strread=trim((string)$arrrow["TIM_EXTERNAL_IDS"]);
		if ($arrrow["TIM_EXTERNAL_IDS"]===null || $strread==="") {
			$arrresult["state"]="unread";
			return $arrresult;
		}
		$arrresult["state"]="read";
		$arrresult["read"]=$strread;
		foreach (f_socialidsnetworks() as $strcolumn=>$strlabel) {
			if (array_key_exists($strcolumn,$arrrow)) {
				$strvalue=trim((string)$arrrow[$strcolumn]);
				if ($strvalue!=="") {
					$arrresult["ids"][$strcolumn]=$strvalue;
				}
			}
		}
		return $arrresult;
	}
}

if (!function_exists('f_socialidpath')) {
	function f_socialidpath($strvalue) {
		//Purpose: URL-encodes an id as a path, segment by segment, so a "/" inside a Facebook id survives
		//Params:	$strvalue: The id
		//Returns: The encoded path, without a leading slash
		$arrsegments=explode("/",trim($strvalue,"/"));
		return implode("/",array_map("rawurlencode",$arrsegments));
	}
}

if (!function_exists('f_socialidurl')) {
	function f_socialidurl($strcolumn,$strvalue) {
		//Purpose: The profile URL of one social-network id
		//Params:	$strcolumn: The column (FACEBOOK_ID, INSTAGRAM_ID, TWITTER_ID, TIKTOK_ID, YOUTUBE_ID)
		//			$strvalue: The id as TMDb stores it
		//Returns: The URL, or "" for an unknown column, an empty id or a non-https URL
		$strvalue=trim((string)$strvalue);
		if ($strvalue==="") {
			return "";
		}
		if (preg_match('#^https?://#i',$strvalue)) {
			//An id stored as a full URL is kept as it is, https only
			return preg_match('#^https://[^\s"<>]+$#i',$strvalue) ? $strvalue : "";
		}
		$strhandle=ltrim($strvalue,"@");
		if ($strhandle==="") {
			return "";
		}
		switch ($strcolumn) {
			case "FACEBOOK_ID":
				return "https://www.facebook.com/".f_socialidpath($strhandle);
			case "INSTAGRAM_ID":
				return "https://www.instagram.com/".f_socialidpath($strhandle)."/";
			case "TWITTER_ID":
				return "https://x.com/".f_socialidpath($strhandle);
			case "TIKTOK_ID":
				return "https://www.tiktok.com/@".f_socialidpath($strhandle);
			case "YOUTUBE_ID":
				//The format TMDb expects is still to be confirmed: a channel id (UC + 22 characters)
				//opens as a channel, anything else as a handle
				if (preg_match('/^UC[A-Za-z0-9_-]{22}$/',$strhandle)) {
					return "https://www.youtube.com/channel/".$strhandle;
				}
				return "https://www.youtube.com/@".f_socialidpath($strhandle);
		}
		return "";
	}
}

if (!function_exists('f_socialidshtml')) {
	function f_socialidshtml($arrstate,$arrlabels) {
		//Purpose: The line of the identifiers block for the social-network ids
		//Params:	$arrstate: The result of f_socialidsfromrow()
		//			$arrlabels: array("title"=>..., "unread"=>..., "none"=>..., "read"=>...), already translated
		//Returns: One <p> line, or "" when the columns are absent
		if (!is_array($arrstate) || !isset($arrstate["state"]) || $arrstate["state"]==="absent") {
			return "";
		}
		$strhtml="<p class=\"social-ids\"><strong>".htmlspecialchars($arrlabels["title"]).":</strong> ";
		if ($arrstate["state"]==="unread") {
			return $strhtml."<em>".htmlspecialchars($arrlabels["unread"])."</em></p>";
		}
		$arrnetworks=f_socialidsnetworks();
		$arrlinks=array();
		foreach ($arrstate["ids"] as $strcolumn=>$strvalue) {
			$strlabel=isset($arrnetworks[$strcolumn]) ? $arrnetworks[$strcolumn] : $strcolumn;
			$strurl=f_socialidurl($strcolumn,$strvalue);
			$strtext=htmlspecialchars($strlabel).": ".htmlspecialchars($strvalue);
			if ($strurl!=="") {
				$arrlinks[]="<a href=\"".htmlspecialchars($strurl)."\" target=\"_blank\" rel=\"noreferrer\">".$strtext."</a>";
			} else {
				$arrlinks[]=$strtext;
			}
		}
		if (count($arrlinks)==0) {
			$strhtml.="<em>".htmlspecialchars($arrlabels["none"])."</em>";
		} else {
			$strhtml.=implode(" · ",$arrlinks);
		}
		$strhtml.=" <small>(".htmlspecialchars($arrlabels["read"])." ".htmlspecialchars(substr($arrstate["read"],0,10)).")</small>";
		return $strhtml."</p>";
	}
}

if (!function_exists('f_socialidslabels')) {
	function f_socialidslabels() {
		//Purpose: The translated labels of the social-network line, from lib/language.inc.php
		//Returns: The $arrlabels array f_socialidshtml() expects
		return array(
			"title"=>get_movie_string('lib_social_ids'),
			"unread"=>get_movie_string('lib_social_ids_unread'),
			"none"=>get_movie_string('lib_social_ids_none'),
			"read"=>get_movie_string('lib_social_ids_read'),
		);
	}
}
