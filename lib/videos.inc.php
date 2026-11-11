<!DOCTYPE html>
<html>
<head>
    <title>Video Gallery</title>
<style>
#videogallery {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}
.videocard {
    display: block;
    width: 320px;
    max-width: 100%;
    text-decoration: none;
    color: inherit;
}
.videothumb {
    position: relative;
    display: block;
    border-radius: 8px;
    overflow: hidden;
    background: #000;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    aspect-ratio: 16 / 9;
}
.videothumb img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}
.videocard:hover .videothumb img {
    transform: scale(1.05);
}
.videothumb .playicon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 64px;
    height: 64px;
    background: rgba(0,0,0,0.6);
    border-radius: 50%;
}
.videothumb .playicon::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 54%;
    transform: translate(-50%, -50%);
    border-style: solid;
    border-width: 11px 0 11px 19px;
    border-color: transparent transparent transparent #fff;
}
.videocaption {
    display: block;
    margin-top: 6px;
    font-size: 14px;
    line-height: 1.3;
}
.videocaption .vtype {
    opacity: 0.7;
    font-size: 12px;
}
</style>
</head>
<body>
    <?php
include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

// Gallery of all YouTube videos linked to a content (movie / serie),
// sibling of images.php. Reads T_WC_TMDB_MOVIE_VIDEO / T_WC_TMDB_SERIE_VIDEO (ID_* indexed),
// keeps only YouTube rows, and renders each as a lazy-loaded thumbnail linking to the video
// (lazy-loading is the lesson of images.php: don't fetch every thumbnail at once).
$strcontenttype="";
if (f_requestisfieldset("movieid")) {
	$lngcontentid=f_stringtolong(f_request2("movieid"));
	$strcontenttype="movie";
	$strvideotable="T_WC_TMDB_MOVIE_VIDEO";
	$strkeyfieldname="ID_MOVIE";
}
if (f_requestisfieldset("serieid")) {
	$lngcontentid=f_stringtolong(f_request2("serieid"));
	$strcontenttype="serie";
	$strvideotable="T_WC_TMDB_SERIE_VIDEO";
	$strkeyfieldname="ID_SERIE";
}
if ($strcontenttype!="") {
	$strcontenturl=$strcontenttype.".php?".$strcontenttype."id=".$lngcontentid;
}
if (f_requestisfieldset("lang")) {
	$strlang=strtolower(substr(trim(f_request2("lang")),0,2));
} else {
	$strlang="";
}
?>
<h1><?php echo get_movie_string('lib_videos_gallery'); ?></h1>
<?php if ($strcontenttype!="") { ?>
<h2><?php echo "<a href=\"".$strcontenturl."\">".get_movie_string('lib_back_to')." ".$strcontenttype."</a>"; ?></h2>
<?php } ?>

<div id="videogallery">
<?php
	$lngvideocount=0;
	if ($strcontenttype!="" && $lngcontentid>0) {
		$strsqlvideos ="SELECT VIDEO_KEY, VIDEO_NAME, VIDEO_TYPE, OFFICIAL ";
		$strsqlvideos.="FROM ".$strvideotable." ";
		$strsqlvideos.="WHERE ".$strkeyfieldname." = ".f_longtosql($lngcontentid)." ";
		$strsqlvideos.="AND (DELETED IS NULL OR DELETED = 0) ";
		$strsqlvideos.="AND VIDEO_SITE = 'YouTube' ";
		$strsqlvideos.="AND VIDEO_KEY IS NOT NULL AND VIDEO_KEY <> '' ";
		if ($strlang!="") {
			$strsqlvideos.="AND (LANG = ".f_stringtosql($strlang)." OR LANG IS NULL OR LANG = '') ";
		}
		$strsqlvideos.="ORDER BY OFFICIAL DESC, DISPLAY_ORDER ";
		$resultvideos=f_sqlselect($strsqlvideos);
		if ($resultvideos) {
			while ($rstempvideo=f_sqlresultfetcharray($resultvideos)) {
				$strvideokey=trim((string)$rstempvideo["VIDEO_KEY"]);
				if ($strvideokey=="") {
					continue;
				}
				$strvideokeyenc=rawurlencode($strvideokey);
				$strplayerurl="yt-player.php?yid=".$strvideokeyenc; // internal embedded player (iframe)
				$strthumburl="https://img.youtube.com/vi/".$strvideokeyenc."/hqdefault.jpg";
				$strvideoname=trim((string)$rstempvideo["VIDEO_NAME"]);
				$strvideotype=trim((string)$rstempvideo["VIDEO_TYPE"]);
				$strelem ="<a class=\"videocard\" href=\"".$strplayerurl."\">";
				$strelem.="<span class=\"videothumb\"><img loading=\"lazy\" decoding=\"async\" src=\"".$strthumburl."\" alt=\"".htmlspecialchars($strvideoname)."\"><span class=\"playicon\"></span></span>";
				$strelem.="<span class=\"videocaption\">".htmlspecialchars($strvideoname);
				if ($strvideotype!="") {
					$strelem.="<br /><span class=\"vtype\">".htmlspecialchars($strvideotype)."</span>";
				}
				$strelem.="</span>";
				$strelem.="</a>";
				echo $strelem;
				$lngvideocount++;
			}
			f_sqlfreeresult($resultvideos);
		}
	}
	if ($lngvideocount==0) {
		echo "<p>".get_movie_string('lib_videos_none')."</p>";
	}
?>
</div>

</body>
</html>
