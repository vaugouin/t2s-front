<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time',-1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

$stryoutubeid=trim(f_request2("yid"));
$strvideoname="";
$strvideotype="";
$strcontenttype="";
$lngcontentid=0;
$strcontenturl="";

if ($intdbavailable) {
	if ($stryoutubeid!="" && preg_match('/^[A-Za-z0-9_-]{11}$/',$stryoutubeid)) {
		$strsql="SELECT ID_MOVIE, VIDEO_NAME, VIDEO_TYPE FROM ".$strsqlns."TMDB_MOVIE_VIDEO WHERE VIDEO_KEY = ".f_stringtosql($stryoutubeid)." LIMIT 1";
		$result=f_sqlselect($strsql);
		if ($result) {
			if ($rstemp=f_sqlresultfetcharray($result)) {
				$strvideoname=trim((string)$rstemp['VIDEO_NAME']);
				$strvideotype=trim((string)$rstemp['VIDEO_TYPE']);
				$strcontenttype="movie";
				$lngcontentid=f_stringtolong($rstemp['ID_MOVIE']);
			}
			f_sqlfreeresult($result);
		}
		if ($strcontenttype=="") {
			$strsql="SELECT ID_SERIE, VIDEO_NAME, VIDEO_TYPE FROM ".$strsqlns."TMDB_SERIE_VIDEO WHERE VIDEO_KEY = ".f_stringtosql($stryoutubeid)." LIMIT 1";
			$result=f_sqlselect($strsql);
			if ($result) {
				if ($rstemp=f_sqlresultfetcharray($result)) {
					$strvideoname=trim((string)$rstemp['VIDEO_NAME']);
					$strvideotype=trim((string)$rstemp['VIDEO_TYPE']);
					$strcontenttype="serie";
					$lngcontentid=f_stringtolong($rstemp['ID_SERIE']);
				}
				f_sqlfreeresult($result);
			}
		}
		// Build a back link to the parent movie/serie (like images.php)
		if ($strcontenttype!="" && $lngcontentid>0) {
			$strcontenturl=$strcontenttype.".php?".$strcontenttype."id=".$lngcontentid;
		}
	}

	$strpagetitle=($strvideoname!=""?$strvideoname:"YouTube player");
	//Social preview: the video thumbnail is the visual shown when the page URL is shared.
	//Level 2 (hqdefault, 480x360) rather than maxresdefault, which 404s on many older videos and
	//would leave the share with no image at all.
	$arrsocialvideo=array();
	if ($strvideotype!="") {
		$arrsocialvideo['description']=$strvideotype;
	}
	if ($stryoutubeid!="" && preg_match('/^[A-Za-z0-9_-]{11}$/',$stryoutubeid)) {
		$arrsocialvideo['image']=f_socialimageurl(f_getvideothumbnail($stryoutubeid,"youtube",2));
		$arrsocialvideo['imagewidth']=480;
		$arrsocialvideo['imageheight']=360;
		$arrsocialvideo['imagealt']=$strpagetitle;
	}
	f_headerdisplay($strpagetitle,"",$arrsocialvideo);
	echo "<h1><a href=\"yt-player.php\">YouTube player</a></h1>";
	// Back link to the parent movie/serie, mirroring images.php
	if ($strcontenturl!="") {
		echo "<h2><a href=\"".$strcontenturl."\">".get_movie_string('lib_back_to')." ".$strcontenttype."</a></h2>";
	}

	if ($stryoutubeid=="") {
		echo "<p>Missing parameter: yid</p>";
	} else {
		if (!preg_match('/^[A-Za-z0-9_-]{11}$/',$stryoutubeid)) {
			echo "<p>Invalid YouTube id</p>";
		} else {
			if ($strvideoname!="") {
				echo "<h1>".htmlspecialchars($strvideoname,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."</h1>";
				if ($strvideotype!="") {
					echo "<div>".htmlspecialchars($strvideotype,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."</div>";
				}
			}
			$strembedurl="https://www.youtube.com/embed/".$stryoutubeid;
			echo "<div style=\"max-width: 1000px;\">";
			echo "<div style=\"position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden;\">";
			echo "<iframe src=\"".$strembedurl."\" title=\"YouTube video player\" style=\"position:absolute;top:0;left:0;width:100%;height:100%;border:0;\" allow=\"accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share\" allowfullscreen></iframe>";
			echo "</div>";
			echo "</div>";
		}
	}

	f_footerdisplay();
} else {
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}

?>
