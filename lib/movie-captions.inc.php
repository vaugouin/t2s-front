<!DOCTYPE html>
<html>
<head>
    <title>Captioned screenshots</title>
<style>
.capgallery {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}
.capcard {
    display: block;
    width: 360px;
    max-width: 100%;
}
.capthumb {
    display: block;
    border-radius: 8px;
    overflow: hidden;
    background: #000;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    aspect-ratio: 16 / 9;
}
.capthumb img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}
.capcard a:hover .capthumb img {
    transform: scale(1.05);
}
.captext {
    display: block;
    margin-top: 6px;
    font-size: 14px;
    line-height: 1.35;
}
.capmodel {
    display: inline-block;
    margin-top: 6px;
    font-size: 11px;
    font-weight: bold;
    opacity: 0.6;
}
.captime {
    display: inline-block;
    margin-top: 6px;
    font-size: 12px;
    font-weight: bold;
    opacity: 0.75;
}
.capnote {
    font-size: 13px;
    opacity: 0.75;
}
</style>
</head>
<body>
    <?php
include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

// Image captioning: every backdrop (screenshot) of a movie with its AI caption
// (T_WC_TMDB_IMAGE_CAPTION, written by the image-captioning repo), in the estimated order of the
// film: placed stills by TIMELINE_ORDER with their start-end interval, then captioned stills the
// model left off the timeline (promotional art, composites), then backdrops without a caption
// (text overlay, or not processed yet). Captions and timeline are estimates for browsing, never
// facts about the film. Layout follows videos.inc.php.
//
// No model is hardcoded: every caption is shown whatever the model that produced it (the table is
// versioned by CAPTION_MODEL, so one backdrop may carry several captions). Each card shows its
// model, and placed stills are kept contiguous per model since TIMELINE_ORDER restarts at 1 for
// each model's own timeline pass.

// Minutes from the start of the film, shown as h:mm.
function f_captionminutes($lngminutes) {
	$lngminutes=intval($lngminutes);
	return intdiv($lngminutes,60).":".str_pad(strval($lngminutes%60),2,"0",STR_PAD_LEFT);
}

$lngcontentid=0;
if (f_requestisfieldset("movieid")) {
	$lngcontentid=f_stringtolong(f_request2("movieid"));
}
$strmovietitle="";
if ($intdbavailable && $lngcontentid>0) {
	$strmovietitle=trim((string)f_descfromcode($strsqlns."TMDB_MOVIE","ID_MOVIE","TITLE",f_longtosql($lngcontentid),"",""));
}
?>
<h1><?php echo get_movie_string('lib_captions_gallery').($strmovietitle!="" ? " : ".htmlspecialchars($strmovietitle) : ""); ?></h1>
<?php if ($lngcontentid>0) { ?>
<h2><?php echo "<a href=\"movie.php?movieid=".$lngcontentid."\">".get_movie_string('lib_back_to')." movie</a>"; ?></h2>
<p class="capnote"><?php echo get_movie_string('lib_captions_note'); ?></p>
<?php } ?>

<?php
	$arrsections=array("placed"=>array(), "unplaced"=>array(), "uncaptioned"=>array());
	if ($intdbavailable && $lngcontentid>0) {
		$strsqlcaptions ="SELECT I.ID_ROW, I.IMAGE_PATH, C.CAPTION, C.CAPTION_MODEL, C.TIMELINE_START_MIN, C.TIMELINE_END_MIN, C.TIMELINE_ORDER ";
		$strsqlcaptions.="FROM ".$strsqlns."TMDB_MOVIE_IMAGE I ";
		$strsqlcaptions.="LEFT JOIN ".$strsqlns."TMDB_IMAGE_CAPTION C ON C.TABLE_SOURCE = 'movie' AND C.ID_ROW = I.ID_ROW ";
		$strsqlcaptions.="WHERE I.ID_MOVIE = ".f_longtosql($lngcontentid)." ";
		$strsqlcaptions.="AND (I.DELETED IS NULL OR I.DELETED = 0) ";
		$strsqlcaptions.="AND I.TYPE_IMAGE = 'backdrop' ";
		$strsqlcaptions.="AND I.IMAGE_PATH IS NOT NULL AND I.IMAGE_PATH <> '' ";
		$strsqlcaptions.="ORDER BY C.CAPTION_MODEL, C.TIMELINE_ORDER, I.DISPLAY_ORDER, I.ID_ROW ";
		$resultcaptions=f_sqlselect($strsqlcaptions);
		if ($resultcaptions) {
			while ($rstempcaption=f_sqlresultfetcharray($resultcaptions)) {
				$strcaption=trim((string)$rstempcaption["CAPTION"]);
				if ($strcaption=="") {
					$arrsections["uncaptioned"][]=$rstempcaption;
				} elseif ($rstempcaption["TIMELINE_ORDER"]!==null) {
					$arrsections["placed"][]=$rstempcaption;
				} else {
					$arrsections["unplaced"][]=$rstempcaption;
				}
			}
			f_sqlfreeresult($resultcaptions);
		}
	}

	$lngimagecount=0;
	$arrsectionlabels=array(
		"placed"=>'lib_captions_section_placed',
		"unplaced"=>'lib_captions_section_unplaced',
		"uncaptioned"=>'lib_captions_section_uncaptioned',
	);
	foreach ($arrsectionlabels as $strsection => $strlabel) {
		if (count($arrsections[$strsection])==0) {
			continue;
		}
		echo "<h2>".get_movie_string($strlabel)." (".count($arrsections[$strsection]).")</h2>\n";
		echo "<div class=\"capgallery\">\n";
		foreach ($arrsections[$strsection] as $rstempcaption) {
			$strimagepath=trim((string)$rstempcaption["IMAGE_PATH"]);
			$strcaption=trim((string)$rstempcaption["CAPTION"]);
			$strelem ="<div class=\"capcard\">";
			$strelem.="<a href=\"".$strtmdbimagesoriginalpathbaseurl.$strimagepath."\" target=\"_blank\" rel=\"noreferrer\">";
			$strelem.="<span class=\"capthumb\"><img loading=\"lazy\" decoding=\"async\" src=\"https://image.tmdb.org/t/p/w780".$strimagepath."\" alt=\"".htmlspecialchars($strcaption)."\"></span>";
			$strelem.="</a>";
			if ($strsection=="placed") {
				$strelem.="<span class=\"captime\">#".intval($rstempcaption["TIMELINE_ORDER"])." &middot; ";
				$strelem.=f_captionminutes($rstempcaption["TIMELINE_START_MIN"])." &ndash; ".f_captionminutes($rstempcaption["TIMELINE_END_MIN"]);
				$strelem.="</span>";
			}
			if ($strcaption!="") {
				$strelem.="<span class=\"captext\">".htmlspecialchars($strcaption)."</span>";
				$strmodel=trim((string)$rstempcaption["CAPTION_MODEL"]);
				if ($strmodel!="") {
					$strelem.="<span class=\"capmodel\">".htmlspecialchars($strmodel)."</span>";
				}
			}
			$strelem.="</div>\n";
			echo $strelem;
			$lngimagecount++;
		}
		echo "</div>\n";
	}
	if ($lngimagecount==0) {
		echo "<p>".get_movie_string('lib_captions_none')."</p>";
	}
?>

</body>
</html>
