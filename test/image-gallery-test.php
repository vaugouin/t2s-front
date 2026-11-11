<?php
/*
 * image-gallery-test.php - test for lib/image-gallery.inc.php
 *
 * Run it from the repository root, no database, no web server and no network needed:
 *
 *     php test/image-gallery-test.php
 *
 * Exit code 0 when every case passes, 1 otherwise.
 *
 * PhotoSwipe opens a picture at the size announced on its link. A wrong size distorts the
 * picture, and a size of 0 opens nothing. These cases pin the size computed from the WIDTH and
 * HEIGHT columns of the image tables, the fallback when a row has no size, and the escaping of
 * the HTML and of the script.
 */

require_once(__DIR__."/../lib/image-gallery.inc.php");

$lngchecks=0;
$lngfailures=0;

function f_check($strlabel,$varactual,$varexpected) {
	global $lngchecks,$lngfailures;
	$lngchecks++;
	if ($varactual!==$varexpected) {
		$lngfailures++;
		echo "FAIL ".$strlabel."\n     expected: ".var_export($varexpected,true)."\n     actual:   ".var_export($varactual,true)."\n";
	}
}

echo "\nSize announced to PhotoSwipe\n";
f_check("poster 2000x3000 scaled to 780",f_galleryimagesize(2000,3000,"poster"),array("width"=>780,"height"=>1170));
f_check("backdrop 3840x2160 scaled to 780",f_galleryimagesize(3840,2160,"backdrop"),array("width"=>780,"height"=>439));
f_check("backdrop 1920x1080",f_galleryimagesize(1920,1080,"backdrop"),array("width"=>780,"height"=>439));
f_check("original narrower than 780 keeps its size",f_galleryimagesize(500,750,"poster"),array("width"=>500,"height"=>750));
f_check("original exactly 780 wide",f_galleryimagesize(780,439,"still"),array("width"=>780,"height"=>439));
f_check("wide logo keeps its ratio",f_galleryimagesize(1560,200,"logo"),array("width"=>780,"height"=>100));
f_check("numeric strings from the database",f_galleryimagesize("2000","3000","poster"),array("width"=>780,"height"=>1170));
f_check("the stored size wins over the type",f_galleryimagesize(1000,1000,"poster"),array("width"=>780,"height"=>780));
f_check("extreme ratio never gives a height of 0",f_galleryimagesize(5000,1,"logo"),array("width"=>780,"height"=>1));

echo "\nFallback without a stored size\n";
f_check("poster without size",f_galleryimagesize(0,0,"poster"),array("width"=>780,"height"=>1170));
f_check("profile without size",f_galleryimagesize(null,null,"profile"),array("width"=>780,"height"=>1170));
f_check("backdrop without size",f_galleryimagesize(0,0,"backdrop"),array("width"=>780,"height"=>439));
f_check("still without size",f_galleryimagesize("","","still"),array("width"=>780,"height"=>439));
f_check("type in capitals",f_galleryimagesize(0,0,"Poster"),array("width"=>780,"height"=>1170));
f_check("unknown type",f_galleryimagesize(0,0,""),array("width"=>780,"height"=>439));
f_check("width only is not a size",f_galleryimagesize(2000,0,"poster"),array("width"=>780,"height"=>1170));
f_check("negative values are not a size",f_galleryimagesize(-5,-5,"backdrop"),array("width"=>780,"height"=>439));

echo "\nGallery item\n";
$stritem=f_galleryitemhtml("https://image.tmdb.org/t/p/w780/abc.jpg","https://media.themoviedb.org/t/p/w130_and_h195_bestv2/abc.jpg","Poster",2000,3000,"poster");
f_check("item markup",$stritem,"<a href=\"https://image.tmdb.org/t/p/w780/abc.jpg\" data-pswp-width=\"780\" data-pswp-height=\"1170\"><img loading=\"lazy\" decoding=\"async\" src=\"https://media.themoviedb.org/t/p/w130_and_h195_bestv2/abc.jpg\" alt=\"Poster\" title=\"Poster\" style=\"max-width: 300px;\"></a>");
$strhostile=f_galleryitemhtml("https://example.test/a.jpg?x=1&y=\"2\"","https://example.test/t.jpg","A \"quoted\" <b>title</b>",0,0,"backdrop");
f_check("quotes escaped in the URL",strpos($strhostile,"href=\"https://example.test/a.jpg?x=1&amp;y=&quot;2&quot;\"")!==false,true);
f_check("markup escaped in the title",strpos($strhostile,"alt=\"A &quot;quoted&quot; &lt;b&gt;title&lt;/b&gt;\"")!==false,true);
f_check("no raw tag from the title",strpos($strhostile,"<b>"),false);
f_check("fallback size on the hostile item",strpos($strhostile,"data-pswp-width=\"780\" data-pswp-height=\"439\"")!==false,true);

echo "\nStylesheet and script\n";
$strversion=f_galleryphotoswipeversion();
f_check("version is pinned to an exact release",preg_match('/^5\.\d+\.\d+$/',$strversion),1);
f_check("stylesheet link",f_gallerycsshtml(),"<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/photoswipe@".$strversion."/dist/photoswipe.css\">");
$strscript=f_galleryscripthtml("imagegallery");
f_check("ES module",strpos($strscript,"<script type=\"module\">")===0,true);
f_check("lightbox import, pinned",strpos($strscript,"import PhotoSwipeLightbox from \"https://cdn.jsdelivr.net/npm/photoswipe@".$strversion."/dist/photoswipe-lightbox.esm.min.js\";")!==false,true);
f_check("core loaded on demand, pinned",strpos($strscript,"pswpModule: () => import(\"https://cdn.jsdelivr.net/npm/photoswipe@".$strversion."/dist/photoswipe.esm.min.js\")")!==false,true);
f_check("gallery selector",strpos($strscript,"gallery: \"#imagegallery\",")!==false,true);
f_check("children are the links",strpos($strscript,"children: 'a',")!==false,true);
f_check("init called",strpos($strscript,"lightbox.init();")!==false,true);
f_check("one script block only",substr_count($strscript,"</script>"),1);
$strscripthostile=f_galleryscripthtml("x\"</script><script>alert(1)//");
f_check("an id cannot close the script block",substr_count($strscripthostile,"</script>"),1);
f_check("an id cannot open a second block",substr_count($strscripthostile,"<script"),1);

echo "\nThe three gallery pages\n";
foreach (array("images","season-images","episode-images") as $strpage) {
	$strsource=file_get_contents(__DIR__."/../lib/".$strpage.".inc.php");
	f_check($strpage.": no lightGallery left",stripos($strsource,"lightgallery"),false);
	f_check($strpage.": selects WIDTH and HEIGHT",strpos($strsource,"SELECT TYPE_IMAGE, IMAGE_PATH, WIDTH, HEIGHT ")!==false,true);
	f_check($strpage.": gallery element",strpos($strsource,"<div id=\"imagegallery\">")!==false,true);
	f_check($strpage.": script on the same element",strpos($strsource,"echo f_galleryscripthtml(\"imagegallery\");")!==false,true);
	f_check($strpage.": stylesheet in the head",strpos($strsource,"echo f_gallerycsshtml();")!==false && strpos($strsource,"echo f_gallerycsshtml();")<strpos($strsource,"</head>"),true);
}

echo "\n".$lngchecks." checks, ".$lngfailures." failure(s)\n";
exit($lngfailures>0 ? 1 : 0);
?>
