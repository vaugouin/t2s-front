<!DOCTYPE html>
<html>
<head>
    <title>Episode Gallery</title>
    <?php
	//PhotoSwipe 5 (MIT), see lib/image-gallery.inc.php
	include_once(__DIR__."/image-gallery.inc.php");
	echo f_gallerycsshtml();
	?>
<style>
#imagegallery {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}
#imagegallery a img {
    max-width: 300px;
    width: 100%;
    height: auto;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    transition: transform 0.3s;
}
#imagegallery a img:hover {
    transform: scale(1.05);
}
</style>
</head>
<body>
    <?php
include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

$lngserieid=f_stringtolong(f_request2("serieid"));
$lngseasonnumber=f_stringtolong(f_request2("season"));
$lngepisodenumber=f_stringtolong(f_request2("episode"));
$lngseasonid=0;
$lngepisodeid=0;
$strcontenturl="";
$strcontenttype=get_movie_string('lib_episode_episode');

if ($lngserieid>0 && $lngseasonnumber>=0 && $lngepisodenumber>0) {
	$strsqlepisode="SELECT ID_SEASON, ID_EPISODE FROM ".$strsqlns."TMDB_EPISODE WHERE ID_SERIE = ".f_longtosql($lngserieid)." AND SEASON_NUMBER = ".f_longtosql($lngseasonnumber)." AND EPISODE_NUMBER = ".f_longtosql($lngepisodenumber)." AND DELETED = 0 ";
	$resultepisode=f_sqlselect($strsqlepisode);
	if ($resultepisode) {
		if ($rstempepisode=f_sqlresultfetcharray($resultepisode)) {
			$lngseasonid=f_stringtolong($rstempepisode['ID_SEASON']);
			$lngepisodeid=f_stringtolong($rstempepisode['ID_EPISODE']);
		}
		f_sqlfreeresult($resultepisode);
	}
	$strcontenturl="episode.php?serieid=".$lngserieid."&season=".$lngseasonnumber."&episode=".$lngepisodenumber;
}

if (f_requestisfieldset("imagetype")) {
	$strimagetype=f_request2("imagetype");
	switch ($strimagetype) {
	case "poster":
	case "backdrop":
	case "logo":
	case "profile":
	case "still":
	case "all":
		break;
	default:
		$strimagetype="all";
		break;
	}
} else {
	$strimagetype="all";
}
if (f_requestisfieldset("lang")) {
	$strlang=strtolower(substr(trim(f_request2("lang")),0,2));
} else {
	$strlang="";
}
?>
<h1><?php echo get_movie_string('lib_images_gallery'); ?></h1>
<h2><?php echo "<a href=\"".$strcontenturl."\">".get_movie_string('lib_back_to')." ".$strcontenttype."</a>"; ?></h2>
<h2><?php echo ucfirst($strimagetype) ?></h2>

<div id="imagegallery">
<?php
	$strsqlimages="";
	$strsqlimages.="SELECT TYPE_IMAGE, IMAGE_PATH, WIDTH, HEIGHT ";
    $strsqlimages.="FROM ".$strsqlns."TMDB_EPISODE_IMAGE ";
    $strsqlimages.="WHERE ID_SERIE = ".f_longtosql($lngserieid)." ";
    $strsqlimages.="AND ID_SEASON = ".f_longtosql($lngseasonid)." ";
    $strsqlimages.="AND ID_EPISODE = ".f_longtosql($lngepisodeid)." ";
    $strsqlimages.="AND DELETED = 0 ";
    if ($strimagetype!="all") {
	    $strsqlimages.="AND TYPE_IMAGE = ".f_stringtosql($strimagetype)." ";
	}
	if ($strlang!="") {
	    $strsqlimages.="AND (LANG = ".f_stringtosql($strlang)." OR LANG IS NULL OR LANG = '') ";
	}
    $strsqlimages.="ORDER BY TYPE_IMAGE, DISPLAY_ORDER, ID_ROW ";
	$resultimages=f_sqlselect($strsqlimages);
	$images=array();
	if ($resultimages) {
		while ($rstempimage=f_sqlresultfetcharray($resultimages)) {
			$strimagepath=$rstempimage["IMAGE_PATH"];
		    $images[] = [
		        'large' => "https://image.tmdb.org/t/p/w780".$strimagepath,
		        'thumb' => "https://media.themoviedb.org/t/p/w130_and_h195_bestv2".$strimagepath,
		        'title' => ucfirst($rstempimage['TYPE_IMAGE']),
		        'type' => $rstempimage['TYPE_IMAGE'],
		        'width' => f_stringtolong($rstempimage['WIDTH']),
		        'height' => f_stringtolong($rstempimage['HEIGHT'])
		    ];
		}
		f_sqlfreeresult($resultimages);
	}
	foreach ($images as $image) {
		//The link carries data-pswp-width / data-pswp-height, computed from the WIDTH and HEIGHT columns
		echo f_galleryitemhtml($image['large'],$image['thumb'],$image['title'],$image['width'],$image['height'],$image['type']);
	}
?>
</div>

<?php
//PhotoSwipe 5 lightbox, loaded as an ES module (lib/image-gallery.inc.php)
echo f_galleryscripthtml("imagegallery");
?>

</body>
</html>
