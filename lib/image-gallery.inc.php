<?php
//Image gallery helpers shared by images.inc.php, season-images.inc.php and episode-images.inc.php.
//
//The gallery is a grid of TMDb thumbnails; a click opens the picture in PhotoSwipe 5 (MIT licence,
//https://photoswipe.com), loaded from jsDelivr as an ES module with a pinned version. PhotoSwipe
//needs the pixel size of every picture before it opens it (data-pswp-width / data-pswp-height on
//the link). The T_WC_TMDB_*_IMAGE tables carry the WIDTH and HEIGHT of the original, so the pages
//select them and f_galleryimagesize() scales them to the w780 rendition the link points to.
//
//Nothing here opens a connection, runs a query or prints: the functions return strings, so
//test/image-gallery-test.php can check them alone.

if (!function_exists('f_galleryphotoswipeversion')) {
	function f_galleryphotoswipeversion() {
		//Pinned on purpose: bump it here, in one place, after reading the PhotoSwipe changelog.
		return "5.4.4";
	}
}

if (!function_exists('f_galleryphotoswipebaseurl')) {
	function f_galleryphotoswipebaseurl() {
		return "https://cdn.jsdelivr.net/npm/photoswipe@".f_galleryphotoswipeversion()."/dist/";
	}
}

if (!function_exists('f_gallerylargewidth')) {
	function f_gallerylargewidth() {
		//Width in pixels of the TMDb rendition opened by a click (https://image.tmdb.org/t/p/w780)
		return 780;
	}
}

if (!function_exists('f_galleryimagesize')) {
	function f_galleryimagesize($lngwidth,$lngheight,$strimagetype="") {
		//Purpose: Pixel size to announce to PhotoSwipe for the w780 rendition of a TMDb picture
		//Params:	$lngwidth, $lngheight: size of the original, columns WIDTH and HEIGHT of the
		//			T_WC_TMDB_*_IMAGE row, 0 or NULL when unknown
		//			$strimagetype: TYPE_IMAGE of the row (poster, backdrop, logo, profile, still),
		//			only read when the size is unknown
		//Returns: array("width"=>..,"height"=>..), both at least 1. The original ratio is kept; an
		//			original narrower than 780 px is announced at its own size. Without a stored
		//			size, the usual TMDb ratio of the image type is assumed (2:3 for posters and
		//			profiles, 16:9 otherwise), so the picture still opens, at worst slightly
		//			letterboxed.
		$lngwidth=(int)$lngwidth;
		$lngheight=(int)$lngheight;
		$lnglargewidth=f_gallerylargewidth();
		if ($lngwidth<=0 || $lngheight<=0) {
			switch (strtolower(trim((string)$strimagetype))) {
			case "poster":
			case "profile":
				$lngwidth=2;
				$lngheight=3;
				break;
			default:
				$lngwidth=16;
				$lngheight=9;
				break;
			}
			$lngoutwidth=$lnglargewidth;
		} else {
			$lngoutwidth=min($lngwidth,$lnglargewidth);
		}
		$lngoutheight=(int)round($lngoutwidth*$lngheight/$lngwidth);
		if ($lngoutheight<1) {
			$lngoutheight=1;
		}
		return array("width"=>$lngoutwidth,"height"=>$lngoutheight);
	}
}

if (!function_exists('f_galleryitemhtml')) {
	function f_galleryitemhtml($strlargeurl,$strthumburl,$strtitle,$lngwidth,$lngheight,$strimagetype="") {
		//Purpose: One thumbnail of the gallery, as the link PhotoSwipe opens
		//Params:	$strlargeurl: picture opened by the click
		//			$strthumburl: thumbnail shown in the grid
		//			$strtitle: alt and title of the thumbnail
		//			$lngwidth, $lngheight, $strimagetype: see f_galleryimagesize()
		//Returns: HTML of the <a><img></a> pair. Without JavaScript the link simply opens the
		//			picture.
		$arrsize=f_galleryimagesize($lngwidth,$lngheight,$strimagetype);
		$strtitleattr=htmlspecialchars((string)$strtitle,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
		$strelem="";
		$strelem.="<a href=\"".htmlspecialchars((string)$strlargeurl,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\"";
		$strelem.=" data-pswp-width=\"".$arrsize["width"]."\" data-pswp-height=\"".$arrsize["height"]."\">";
		$strelem.="<img loading=\"lazy\" decoding=\"async\" src=\"".htmlspecialchars((string)$strthumburl,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')."\"";
		$strelem.=" alt=\"".$strtitleattr."\" title=\"".$strtitleattr."\" style=\"max-width: 300px;\">";
		$strelem.="</a>";
		return $strelem;
	}
}

if (!function_exists('f_gallerycsshtml')) {
	function f_gallerycsshtml() {
		//Returns: the <link> of the PhotoSwipe stylesheet, for the <head> of the page
		return "<link rel=\"stylesheet\" href=\"".f_galleryphotoswipebaseurl()."photoswipe.css\">";
	}
}

if (!function_exists('f_galleryscripthtml')) {
	function f_galleryscripthtml($strgalleryid) {
		//Purpose: The script that turns the links of the gallery into a PhotoSwipe lightbox
		//Params:	$strgalleryid: id attribute of the element that holds the links
		//Returns: a <script type="module"> block, the initialization documented at
		//			https://photoswipe.com/getting-started/ : the small Lightbox module is loaded with
		//			the page, the Core module only on the first click. Every value that crosses into
		//			JavaScript goes through json_encode.
		//			Zoom, keyboard arrows, Escape, swipe and pinch are PhotoSwipe defaults. The
		//			opening transition is a fade: the thumbnails are cropped by the TMDb image
		//			server, and PhotoSwipe cannot run its zoom transition from such a thumbnail.
		$strflags=JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT;
		$strlightboxurl=json_encode(f_galleryphotoswipebaseurl()."photoswipe-lightbox.esm.min.js",$strflags);
		$strcoreurl=json_encode(f_galleryphotoswipebaseurl()."photoswipe.esm.min.js",$strflags);
		$strselector=json_encode("#".(string)$strgalleryid,$strflags);
		$strscript="";
		$strscript.="<script type=\"module\">\n";
		$strscript.="import PhotoSwipeLightbox from ".$strlightboxurl.";\n";
		$strscript.="const lightbox = new PhotoSwipeLightbox({\n";
		$strscript.="\tgallery: ".$strselector.",\n";
		$strscript.="\tchildren: 'a',\n";
		$strscript.="\tshowHideAnimationType: 'fade',\n";
		$strscript.="\tpswpModule: () => import(".$strcoreurl.")\n";
		$strscript.="});\n";
		$strscript.="lightbox.init();\n";
		$strscript.="</script>\n";
		return $strscript;
	}
}
?>
