<?php
/*
 * wikipedia-md.inc.php - CONTENT_MD (Wikipedia sections as Markdown) to HTML.
 *
 * The wikipedia-crawler (1.1.0 and later) writes each section of T_WC_WIKIPEDIA_PAGE_LANG_SECTION
 * twice: CONTENT, plain text, and CONTENT_MD, a small Markdown dialect produced by
 * wikipedia_content_md.py. This file turns that dialect into HTML. It is pure: no database,
 * no globals, so test/wikipedia-md-render-test.php runs it offline on real crawler output.
 *
 * The dialect, as the crawler writes it (MarkdownRenderer in wikipedia_content_md.py):
 *   - paragraphs separated by a blank line; lines of one block joined by a newline
 *   - [words](Target_title) for an internal link, written by Wikipedia title with "_" for " ",
 *     an optional "#fragment", balanced parentheses allowed: [x](Parasite_(film,_2019))
 *   - [words](https://...) for an external link
 *   - **bold**, *italic*, ***both*** (a title in bold, as Wikipedia sets the article's own)
 *   - "- " and "1. " lists, nested by two spaces per level; a definition list inside an item
 *     comes as indented "**term**" / ": text" lines
 *   - tables as "| a | b |" rows, "|---|---|" after the header row, "\|" inside a cell
 *   - "**Infobox**" followed by "- **key**: value" lines (the page's infobox, flattened)
 *   - "**term**" then ": text" lines for a definition list, "> " for a quotation
 *   - "#### title" (h4 to h6 of the page), "### title" (an h3 kept inside an end-matter section)
 * Footnote call-outs ([1]) are not in the text: references live in
 * T_WC_WIKIPEDIA_PAGE_LANG_REFERENCE, one row per footnote cited in a section, and are shown
 * under the section by f_wpmd_references().
 *
 * Links: the caller passes $arrlinkmap, keyed by target title (spaces, no fragment):
 *   - a string URL: link there (a movie, person or series page of this site)
 *   - "" : show the words without a link (French date links, red links)
 *   - absent: link to the article on <lang>.wikipedia.org
 * Everything that comes from the page is escaped; only http(s) URLs become links.
 */

function f_wpmd_escape($str) {
	return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function f_wpmd_isurl($str) {
	return (bool)preg_match('#^https?://[^\s"<>]+$#i', (string)$str);
}

function f_wpmd_wikipediaurl($strlang, $strtitle, $strfragment = "") {
	if (!preg_match('/^[a-z]{2,3}(-[a-z]+)?$/', (string)$strlang)) {
		$strlang = "en";
	}
	$strurl = "https://".$strlang.".wikipedia.org/wiki/".str_replace("%2F", "/", rawurlencode(str_replace(" ", "_", $strtitle)));
	if ($strfragment != "") {
		$strurl .= "#".rawurlencode(str_replace(" ", "_", $strfragment));
	}
	return $strurl;
}

// Bold, italic, and both at once, on text that is already escaped. The italic rule is
// md_to_text's: a lone "*" not touching a word character or another "*", so "5*3" or "**" are left alone.
function f_wpmd_emphasis($strhtml) {
	$strhtml = preg_replace('/\*\*\*(\S.*?)\*\*\*/u', '<strong><em>$1</em></strong>', $strhtml);   // a bold title: ***Wings***
	$strhtml = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $strhtml);
	$strhtml = preg_replace('/(?<![\w*])\*(\S[^*]*?)\*(?![\w*])/u', '<em>$1</em>', $strhtml);
	return $strhtml;
}

// One line of inline Markdown to HTML. Links are cut out first and replaced by placeholders
// (\x01n\x02, which escaping leaves untouched), so emphasis may wrap a link, as in
// "*[Wings](Wings_(1927_film))*", without the link's target ever being read as Markdown.
function f_wpmd_inline($strline, $strlang, $arrlinkmap) {
	$arrparts = array();
	$strline = preg_replace_callback('/\[([^\]]+)\]\(((?:[^()\s]|\([^()\s]*\))*)\)/u',
		function ($m) use (&$arrparts, $strlang, $arrlinkmap) {
			$strtext = f_wpmd_emphasis(f_wpmd_escape($m[1]));
			$strtarget = $m[2];
			if (preg_match('#^https?://#i', $strtarget)) {
				$strhtml = f_wpmd_isurl($strtarget)
					? "<a href=\"".f_wpmd_escape($strtarget)."\" target=\"_blank\" rel=\"noopener nofollow\" class=\"wp-ext\">".$strtext."</a>"
					: $strtext;
			} else {
				$intpos = strpos($strtarget, "#");
				$strtitle = str_replace("_", " ", $intpos === false ? $strtarget : substr($strtarget, 0, $intpos));
				$strfragment = $intpos === false ? "" : str_replace("_", " ", substr($strtarget, $intpos + 1));
				if ($strtitle == "") {
					$strhtml = $strtext;          // "#fragment" alone: an anchor on the same page
				} elseif (array_key_exists($strtitle, $arrlinkmap)) {
					$strhref = $arrlinkmap[$strtitle];
					$strhtml = $strhref == "" ? $strtext
						: "<a href=\"".f_wpmd_escape($strhref)."\" class=\"wp-int\">".$strtext."</a>";
				} else {
					$strhtml = "<a href=\"".f_wpmd_escape(f_wpmd_wikipediaurl($strlang, $strtitle, $strfragment))."\" target=\"_blank\" rel=\"noopener\" class=\"wp-wiki\">".$strtext."</a>";
				}
			}
			$arrparts[] = $strhtml;
			return "\x01".(count($arrparts) - 1)."\x02";
		}, $strline);
	$strhtml = f_wpmd_emphasis(f_wpmd_escape(str_replace("\\|", "|", $strline)));
	return preg_replace_callback('/\x01(\d+)\x02/', function ($m) use ($arrparts) {
		return $arrparts[(int)$m[1]];
	}, $strhtml);
}

function f_wpmd_istablerow($strline) {
	return strlen($strline) >= 2 && $strline[0] == "|" && substr(rtrim($strline), -1) == "|";
}

function f_wpmd_istablesep($strline) {
	return (bool)preg_match('/^\|(\s*:?-+:?\s*\|)+\s*$/', $strline);
}

function f_wpmd_islistitem($strline) {
	return (bool)preg_match('/^( *)(-|\d+\.) (.*)$/', $strline);
}

function f_wpmd_tablecells($strline) {
	$strline = trim($strline);
	$strline = substr($strline, 1, -1);
	return array_map('trim', preg_split('/(?<!\\\\)\|/', $strline));
}

function f_wpmd_table($arrlines, $strlang, $arrlinkmap) {
	$arrrows = array();
	$inthead = 0;
	foreach ($arrlines as $i => $strline) {
		if (f_wpmd_istablesep($strline)) {
			if ($i == 1) {
				$inthead = 1;
			}
			continue;
		}
		$arrrows[] = f_wpmd_tablecells($strline);
	}
	if (!$arrrows) {
		return "";
	}
	$strhtml = "<div class=\"wp-table-wrap\"><table class=\"wp-table\">";
	foreach ($arrrows as $i => $arrcells) {
		$strtag = ($inthead && $i == 0) ? "th" : "td";
		if ($inthead && $i == 0) {
			$strhtml .= "<thead>";
		}
		$strhtml .= "<tr>";
		foreach ($arrcells as $strcell) {
			$strhtml .= "<".$strtag.">".f_wpmd_inline($strcell, $strlang, $arrlinkmap)."</".$strtag.">";
		}
		$strhtml .= "</tr>";
		if ($inthead && $i == 0) {
			$strhtml .= "</thead><tbody>";
		}
	}
	$strhtml .= ($inthead ? "</tbody>" : "")."</table></div>";
	return $strhtml;
}

// The infobox, flattened by the crawler into "- **key**: value" lines, back into a two-column
// table. Rows whose value repeats the key are the image and its caption, which this page does
// not show, so they are left out.
function f_wpmd_infobox($arrlines, $strlang, $arrlinkmap) {
	$strrows = "";
	foreach ($arrlines as $strline) {
		$strline = preg_replace('/^\s*- /', '', $strline);
		if (preg_match('/^\*\*(.+?)\*\*: (.*)$/u', $strline, $m)) {
			if (trim($m[1]) == trim($m[2])) {
				continue;
			}
			$strrows .= "<tr><th>".f_wpmd_inline($m[1], $strlang, $arrlinkmap)."</th><td>".f_wpmd_inline($m[2], $strlang, $arrlinkmap)."</td></tr>";
		} elseif (trim($strline) != "") {
			$strrows .= "<tr><td colspan=\"2\">".f_wpmd_inline($strline, $strlang, $arrlinkmap)."</td></tr>";
		}
	}
	return $strrows == "" ? "" : "<div class=\"wp-table-wrap\"><table class=\"wp-infobox\">".$strrows."</table></div>";
}

// Nested lists from "- " / "1. " lines, two spaces per level. An indented line that is not a
// list item (a definition list inside an item) is appended to the current item.
function f_wpmd_list($arrlines, $strlang, $arrlinkmap) {
	$strhtml = "";
	$arrstack = array();          // open lists, innermost last: "ul" or "ol"
	foreach ($arrlines as $strline) {
		if (preg_match('/^( *)(-|\d+\.) (.*)$/', $strline, $m)) {
			$intdepth = intdiv(strlen($m[1]), 2);
			$strtype = $m[2] == "-" ? "ul" : "ol";
			if ($intdepth > count($arrstack)) {
				$intdepth = count($arrstack);   // a jump of two levels opens one
			}
			while (count($arrstack) > $intdepth + 1) {
				$strhtml .= "</li></".array_pop($arrstack).">";
			}
			if (count($arrstack) == $intdepth + 1) {
				$strhtml .= "</li>";
			} else {
				$strhtml .= "<".$strtype.">";
				$arrstack[] = $strtype;
			}
			$strhtml .= "<li>".f_wpmd_inline($m[3], $strlang, $arrlinkmap);
		} else {
			$strtext = trim($strline);
			if ($strtext == "") {
				continue;
			}
			if (substr($strtext, 0, 2) == ": ") {
				$strtext = substr($strtext, 2);
			}
			$strhtml .= ($arrstack ? "<br />" : "").f_wpmd_inline($strtext, $strlang, $arrlinkmap);
		}
	}
	while ($arrstack) {
		$strhtml .= "</li></".array_pop($arrstack).">";
	}
	return $strhtml;
}

function f_wpmd_render($strmd, $strlang, $arrlinkmap = array()) {
	$arrlines = explode("\n", str_replace("\r", "", (string)$strmd));
	$intcount = count($arrlines);
	$strhtml = "";
	$i = 0;
	while ($i < $intcount) {
		$strline = rtrim($arrlines[$i]);
		if (trim($strline) == "") {
			$i++;
			continue;
		}
		// Infobox: the marker, then its "- " lines.
		if ($strline == "**Infobox**") {
			$i++;
			while ($i < $intcount && trim($arrlines[$i]) == "") {
				$i++;
			}
			$arrblock = array();
			while ($i < $intcount && (f_wpmd_islistitem($arrlines[$i]) || substr($arrlines[$i], 0, 2) == "  ")) {
				$arrblock[] = $arrlines[$i++];
			}
			$strhtml .= f_wpmd_infobox($arrblock, $strlang, $arrlinkmap);
			continue;
		}
		if (f_wpmd_istablerow($strline)) {
			$arrblock = array();
			while ($i < $intcount && f_wpmd_istablerow(rtrim($arrlines[$i]))) {
				$arrblock[] = rtrim($arrlines[$i++]);
			}
			$strhtml .= f_wpmd_table($arrblock, $strlang, $arrlinkmap);
			continue;
		}
		if (f_wpmd_islistitem($strline)) {
			$arrblock = array();
			while ($i < $intcount && (f_wpmd_islistitem($arrlines[$i]) || (substr($arrlines[$i], 0, 2) == "  " && trim($arrlines[$i]) != ""))) {
				$arrblock[] = rtrim($arrlines[$i++]);
			}
			$strhtml .= f_wpmd_list($arrblock, $strlang, $arrlinkmap);
			continue;
		}
		// Definition list: "**term**" lines and ": text" lines, the first of them either.
		$blnterm = (bool)preg_match('/^\*\*[^*]+\*\*$/', $strline) && $i + 1 < $intcount && substr($arrlines[$i + 1], 0, 2) == ": ";
		if ($blnterm || substr($strline, 0, 2) == ": ") {
			$strhtml .= "<dl>";
			while ($i < $intcount) {
				$strcur = rtrim($arrlines[$i]);
				if (substr($strcur, 0, 2) == ": ") {
					$strhtml .= "<dd>".f_wpmd_inline(substr($strcur, 2), $strlang, $arrlinkmap)."</dd>";
				} elseif (preg_match('/^\*\*([^*]+)\*\*$/', $strcur, $m)) {
					$strhtml .= "<dt>".f_wpmd_inline($m[1], $strlang, $arrlinkmap)."</dt>";
				} else {
					break;
				}
				$i++;
			}
			$strhtml .= "</dl>";
			continue;
		}
		if (preg_match('/^(#{3,6}) (.+)$/', $strline, $m)) {
			$intlevel = min(6, strlen($m[1]) + 1);   // "###" under the section's h3 is an h4
			$strhtml .= "<h".$intlevel.">".f_wpmd_inline($m[2], $strlang, $arrlinkmap)."</h".$intlevel.">";
			$i++;
			continue;
		}
		if (substr($strline, 0, 2) == "> ") {
			$arrblock = array();
			while ($i < $intcount && substr($arrlines[$i], 0, 2) == "> ") {
				$arrblock[] = f_wpmd_inline(substr(rtrim($arrlines[$i++]), 2), $strlang, $arrlinkmap);
			}
			$strhtml .= "<blockquote>".implode("<br />", $arrblock)."</blockquote>";
			continue;
		}
		// Paragraph: every following line up to a blank line or the start of another block.
		$arrblock = array();
		while ($i < $intcount) {
			$strcur = rtrim($arrlines[$i]);
			if (trim($strcur) == "") {
				break;
			}
			if ($arrblock && ($strcur == "**Infobox**" || f_wpmd_istablerow($strcur) || f_wpmd_islistitem($strcur)
					|| substr($strcur, 0, 2) == ": " || substr($strcur, 0, 2) == "> " || preg_match('/^#{3,6} /', $strcur))) {
				break;
			}
			$arrblock[] = f_wpmd_inline($strcur, $strlang, $arrlinkmap);
			$i++;
		}
		$strhtml .= "<p>".implode("<br />", $arrblock)."</p>";
	}
	return $strhtml;
}

// The footnotes cited in one section, folded under it. The call-outs themselves are not in the
// text (the crawler drops them), so each note carries Wikipedia's own label: [1], [N 2], [a].
// $arrrefs: rows with LABEL, URL, TITLE, REFERENCE_TEXT.
function f_wpmd_references($arrrefs, $strsummary) {
	if (!$arrrefs) {
		return "";
	}
	$strhtml = "<details class=\"wp-refs\"><summary>".f_wpmd_escape($strsummary)." (".count($arrrefs).")</summary><ol>";
	foreach ($arrrefs as $arrref) {
		$strtext = trim((string)($arrref["REFERENCE_TEXT"] ?? ""));
		$strurl = trim((string)($arrref["URL"] ?? ""));
		$strtitle = trim((string)($arrref["TITLE"] ?? ""));
		if ($strtext == "") {
			$strtext = $strtitle != "" ? $strtitle : $strurl;
		}
		$strbody = f_wpmd_escape($strtext);
		if (f_wpmd_isurl($strurl)) {
			$strlink = "<a href=\"".f_wpmd_escape($strurl)."\" target=\"_blank\" rel=\"noopener nofollow\" class=\"wp-ext\">";
			$strescapedtitle = f_wpmd_escape($strtitle);
			$intpos = $strescapedtitle != "" ? strpos($strbody, $strescapedtitle) : false;
			if ($intpos !== false) {
				// The cited title is the link, as on Wikipedia.
				$strbody = substr($strbody, 0, $intpos).$strlink.$strescapedtitle."</a>".substr($strbody, $intpos + strlen($strescapedtitle));
			} else {
				$strbody .= " ".$strlink."&#8599;</a>";
			}
		}
		$strlabel = trim((string)($arrref["LABEL"] ?? ""));
		$strhtml .= "<li>".($strlabel != "" ? "<span class=\"wp-ref-label\">".f_wpmd_escape($strlabel)."</span> " : "").$strbody."</li>";
	}
	return $strhtml."</ol></details>";
}

// The persons, movies and series linked with an entity through Wikipedia, as one block: a title,
// then one line per kind with its count, the names linked to their page of the site, and
// "and N more" when the list was capped. $arrrows: rows with KIND (person, movie, serie), ID,
// LABEL, YEAR1, YEAR2 (birth and death, or the release / first air year) and TOTAL (the kind's
// full count). $arrlabels: person, movie, serie (the line titles) and more ("and %d more").
function f_wpmd_mentions($arrrows, $strtitle, $arrlabels) {
	$arrpages = array("person" => "person.php?personid=", "movie" => "movie.php?movieid=", "serie" => "serie.php?serieid=");
	$arrgroups = array();
	foreach ($arrrows as $arrrow) {
		$strkind = (string)($arrrow["KIND"] ?? "");
		if (isset($arrpages[$strkind]) && (int)($arrrow["ID"] ?? 0) > 0) {
			$arrgroups[$strkind][] = $arrrow;
		}
	}
	if (!$arrgroups) {
		return "";
	}
	$strhtml = "<div class=\"wp-mentions\"><h3>".f_wpmd_escape($strtitle)."</h3>";
	foreach ($arrpages as $strkind => $strpage) {
		if (empty($arrgroups[$strkind])) {
			continue;
		}
		$arritems = array();
		foreach ($arrgroups[$strkind] as $arrrow) {
			$stryear1 = (int)($arrrow["YEAR1"] ?? 0) > 0 ? (string)(int)$arrrow["YEAR1"] : "";
			$stryear2 = (int)($arrrow["YEAR2"] ?? 0) > 0 ? (string)(int)$arrrow["YEAR2"] : "";
			$stryears = $stryear1.($stryear2 != "" ? "-".$stryear2 : "");
			$arritems[] = "<a href=\"".$strpage.(int)$arrrow["ID"]."\">".f_wpmd_escape(trim((string)$arrrow["LABEL"]))."</a>"
				.($stryears != "" ? " <span class=\"wp-mentions-year\">(".$stryears.")</span>" : "");
		}
		$inttotal = max(count($arritems), (int)($arrgroups[$strkind][0]["TOTAL"] ?? 0));
		$strhtml .= "<p class=\"wp-mentions-group\"><strong>".f_wpmd_escape($arrlabels[$strkind] ?? $strkind)." (".$inttotal.")</strong> ".implode(", ", $arritems);
		if ($inttotal > count($arritems)) {
			$strhtml .= " <span class=\"wp-mentions-more\">".f_wpmd_escape(sprintf($arrlabels["more"] ?? "and %d more", $inttotal - count($arritems)))."</span>";
		}
		$strhtml .= "</p>";
	}
	return $strhtml."</div>";
}
