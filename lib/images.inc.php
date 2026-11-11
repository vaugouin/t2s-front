<!DOCTYPE html>
<html>
<head>
    <title>Movie Gallery</title>
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

$strcontenttype="";
if (f_requestisfieldset("networkid")) {
	$lngcontentid=f_stringtolong(f_request2("networkid"));
	$strcontenttype="network";
	$strimagetable="T_WC_TMDB_NETWORK_IMAGE";
	$strkeyfieldname="ID_NETWORK";
}
if (f_requestisfieldset("movieid")) {
	$lngcontentid=f_stringtolong(f_request2("movieid"));
	$strcontenttype="movie";
	$strimagetable="T_WC_TMDB_MOVIE_IMAGE";
	$strkeyfieldname="ID_MOVIE";
}
if (f_requestisfieldset("serieid")) {
	$lngcontentid=f_stringtolong(f_request2("serieid"));
	$strcontenttype="serie";
	$strimagetable="T_WC_TMDB_SERIE_IMAGE";
	$strkeyfieldname="ID_SERIE";
}
if (f_requestisfieldset("personid")) {
	$lngcontentid=f_stringtolong(f_request2("personid"));
	$strcontenttype="person";
	$strimagetable="T_WC_TMDB_PERSON_IMAGE";
	$strkeyfieldname="ID_PERSON";
}
if (f_requestisfieldset("collectionid")) {
	$lngcontentid=f_stringtolong(f_request2("collectionid"));
	$strcontenttype="collection";
	$strimagetable="T_WC_TMDB_COLLECTION_IMAGE";
	$strkeyfieldname="ID_COLLECTION";
}
if (f_requestisfieldset("companyid")) {
	$lngcontentid=f_stringtolong(f_request2("companyid"));
	$strcontenttype="company";
	$strimagetable="T_WC_TMDB_COMPANY_IMAGE";
	$strkeyfieldname="ID_COMPANY";
}
if ($strcontenttype!="") {
	//Content type is known
	$strcontenturl=$strcontenttype.".php?".$strcontenttype."id=".$lngcontentid;
}

if (f_requestisfieldset("imagetype")) {
	$strimagetype=f_request2("imagetype");
	switch ($strimagetype) {
	case "poster":
	case "backdrop":
	case "logo":
	case "profile":
	case "all":
		
		break;
	default:
		$strimagetype="all";
		break;
	}
} else {
	//Default image type
	$strimagetype="poster";
	$strimagetype="backdrop";
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
	// The WHERE is index-served (KEY ID_MOVIE / ID_SERIE / …), so the slow part
	// was the browser fetching every thumbnail at once (no lazy-loading, see the <img> below).
	// Also skip soft-deleted rows and order best-first (DISPLAY_ORDER), both indexed.
	$strsqlmovie="";
	$strsqlmovie.="SELECT TYPE_IMAGE, IMAGE_PATH, WIDTH, HEIGHT ";
    $strsqlmovie.="FROM ".$strimagetable." ";
    $strsqlmovie.="WHERE ".$strkeyfieldname." = ".f_longtosql($lngcontentid)." ";
    $strsqlmovie.="AND (DELETED IS NULL OR DELETED = 0) ";
    $strsqlmovie.="AND IMAGE_PATH IS NOT NULL AND IMAGE_PATH <> '' ";
    if ($strimagetype!="all") {
	    $strsqlmovie.="AND TYPE_IMAGE = ".f_stringtosql($strimagetype)." ";
	}
	if ($strlang!="") {
	    $strsqlmovie.="AND (LANG = ".f_stringtosql($strlang)." OR LANG IS NULL OR LANG = '') ";
	}
    $strsqlmovie.="ORDER BY TYPE_IMAGE, DISPLAY_ORDER ";
    //OR TYPE_IMAGE = 'poster'
    //OR TYPE_IMAGE = 'backdrop'
    //echo "<br />\$strsqlmovie=$strsqlmovie";
	$resultmovie=f_sqlselect($strsqlmovie);
	$images=array();
	if ($resultmovie) {
		while ($rstempmovie=f_sqlresultfetcharray($resultmovie)) {
			$strmovieimagepath=$rstempmovie["IMAGE_PATH"];
		    $images[] = [
		        'large' => "https://image.tmdb.org/t/p/w780".$strmovieimagepath,
		        'thumb' => "https://media.themoviedb.org/t/p/w130_and_h195_bestv2".$strmovieimagepath,
		        'title' => ucfirst($rstempmovie['TYPE_IMAGE']), // e.g., 'Poster' or 'Backdrop'
		        'type' => $rstempmovie['TYPE_IMAGE'],
		        'width' => f_stringtolong($rstempmovie['WIDTH']),
		        'height' => f_stringtolong($rstempmovie['HEIGHT'])
		    ];
		}
		f_sqlfreeresult($resultmovie);
	}
	//echo "<br />\$images=$images<br />";
	//print_r($images);
	
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
