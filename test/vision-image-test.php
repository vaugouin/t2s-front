<?php
/*
 * vision-image-test.php - test for lib/vision-image.inc.php
 *
 * Run it from the repository root, no database and no web server needed:
 *
 *     php test/vision-image-test.php
 *
 * Exit code 0 when every case passes, 1 otherwise.
 *
 * WHY THIS FILE EXISTS
 * Two classes of defect, both of which have already been paid for once in this repository.
 *
 * 1. The image_ref whitelist. An image_ref arrives from a URL and is used to build a URL to the
 *    API. The pattern below is the one the API generator produces (fastapi-text2sql uploads.py,
 *    _IMAGE_REF_PATTERN); anything else must be refused BEFORE a URL is built from it. The cases
 *    here are the traversal shapes and the near misses.
 *
 * 2. The JavaScript emitted from PHP. On 2026-08-29 a hand-escaped backslash put a real newline
 *    inside a JavaScript string literal and killed a whole <script> block in production, in
 *    silence: php -l passed, the page rendered, the button did nothing. So this test RENDERS
 *    f_visionsearchjs() with labels that carry quotes, newlines, backslashes and non-ASCII, then
 *    runs node --check on the result. It never reimplements PHP escaping: the 2026-08-29 check
 *    did exactly that and certified a page that was already broken.
 *
 * The node --check step is skipped, loudly, when node is not on the PATH. A skip is not a pass.
 */

require_once(__DIR__."/../lib/vision-image.inc.php");

$lngchecks=0;
$lngfailures=0;

function f_group($strtitle) {
	echo "\n".$strtitle."\n";
}

function f_nodecheck($strlabel,$strcode) {
	global $lngchecks,$lngfailures;
	//A real JavaScript parser, run on what PHP actually produced. Never a hand-written unescaper:
	//the 2026-08-29 check did exactly that and certified a page that was already broken.
	static $intnodeavailable=null;
	if ($intnodeavailable===null) {
		$arrnodeversion=array();
		$lngnodeversionstatus=1;
		exec("node --version 2>&1",$arrnodeversion,$lngnodeversionstatus);
		$intnodeavailable=($lngnodeversionstatus===0);
	}
	if (!$intnodeavailable) {
		echo "  SKIP  node --check, ".$strlabel." (node is not on the PATH; a skip is not a pass)\n";
		return;
	}
	$strtempfile=tempnam(sys_get_temp_dir(),"vision").".js";
	file_put_contents($strtempfile,$strcode);
	$arrnodeoutput=array();
	$lngnodestatus=1;
	exec("node --check ".escapeshellarg($strtempfile)." 2>&1",$arrnodeoutput,$lngnodestatus);
	@unlink($strtempfile);
	$lngchecks++;
	if ($lngnodestatus===0) {
		echo "  ok    node --check parses ".$strlabel."\n";
	} else {
		$lngfailures++;
		echo "  FAIL  node --check rejected ".$strlabel."\n";
		echo "        ".implode("\n        ",$arrnodeoutput)."\n";
	}
}

function f_check($strlabel,$strexpected,$stractual) {
	global $lngchecks,$lngfailures;
	$lngchecks++;
	if ($strexpected===$stractual) {
		echo "  ok    ".$strlabel."\n";
	} else {
		$lngfailures++;
		echo "  FAIL  ".$strlabel."\n";
		echo "        expected: ".var_export($strexpected,true)."\n";
		echo "        actual  : ".var_export($stractual,true)."\n";
	}
}

// ---------------------------------------------------------------------------
f_group("f_visionsniffimageformat: the bytes decide, never the declared type");

f_check("JPEG magic number","jpg",f_visionsniffimageformat("\xff\xd8\xff\xe0".str_repeat("\x00",64)));
f_check("PNG magic number","png",f_visionsniffimageformat("\x89PNG\r\n\x1a\n".str_repeat("\x00",64)));
f_check("GIF is not accepted","",f_visionsniffimageformat("GIF89a".str_repeat("\x00",64)));
f_check("a PDF named photo.jpg is still a PDF","",f_visionsniffimageformat("%PDF-1.7\n"));
f_check("plain text","",f_visionsniffimageformat("not an image at all"));
f_check("empty payload","",f_visionsniffimageformat(""));
f_check("two bytes of a JPEG are not enough","",f_visionsniffimageformat("\xff\xd8"));

// ---------------------------------------------------------------------------
f_group("f_visionmediatype");

f_check("jpg","image/jpeg",f_visionmediatype("jpg"));
f_check("png","image/png",f_visionmediatype("png"));
f_check("anything else falls back","application/octet-stream",f_visionmediatype("webp"));

// ---------------------------------------------------------------------------
f_group("f_visionimagerefisvalid: the whitelist is the filter");

$strvalidref="20260920-141233_vision_1.1.19_0123456789abcdef0123456789abcdef.jpg";
$strvalidpng="20260920-141233_vision_1.1.19_0123456789abcdef0123456789abcdef.png";
f_check("a reference the API generator produces",true,f_visionimagerefisvalid($strvalidref));
f_check("the same in PNG",true,f_visionimagerefisvalid($strvalidpng));
f_check("empty",false,f_visionimagerefisvalid(""));
f_check("path traversal",false,f_visionimagerefisvalid("../../etc/passwd"));
f_check("a slash inside an otherwise valid name",false,f_visionimagerefisvalid("20260920-141233_vision_1.1.19_0123456789abcdef0123456789abcdef.jpg/x"));
f_check("a directory prefix",false,f_visionimagerefisvalid("vision/20260920-141233_vision_1.1.19_0123456789abcdef0123456789abcdef.jpg"));
f_check("an uppercase hash",false,f_visionimagerefisvalid("20260920-141233_vision_1.1.19_0123456789ABCDEF0123456789abcdef.jpg"));
f_check("a hash of the wrong length",false,f_visionimagerefisvalid("20260920-141233_vision_1.1.19_0123456789abcdef.jpg"));
f_check("an extension we do not store",false,f_visionimagerefisvalid("20260920-141233_vision_1.1.19_0123456789abcdef0123456789abcdef.gif"));
f_check("no timestamp",false,f_visionimagerefisvalid("vision_1.1.19_0123456789abcdef0123456789abcdef.jpg"));
f_check("a newline after a valid name",false,f_visionimagerefisvalid($strvalidref."\nX"));
f_check("a query string glued to a valid name",false,f_visionimagerefisvalid($strvalidref."?x=1"));

f_check("the format is read off a valid reference","jpg",f_visionformatfromref($strvalidref));
f_check("and off a PNG one","png",f_visionformatfromref($strvalidpng));
f_check("an invalid reference has no format","",f_visionformatfromref("../../etc/passwd"));

// ---------------------------------------------------------------------------
f_group("f_visionapibaseurl and f_visionuploadurl: the colour, then the path");

f_check(
	"Blue takes the blue URL",
	"https://example.test:8010",
	f_visionapibaseurl("Blue","https://example.test:8010","https://example.test:8011")
);
f_check(
	"anything else is green, like f_movietext2sqlsearch",
	"https://example.test:8011",
	f_visionapibaseurl("Green","https://example.test:8010","https://example.test:8011")
);
f_check(
	"an empty colour is green too",
	"https://example.test:8011",
	f_visionapibaseurl("","https://example.test:8010","https://example.test:8011")
);
f_check(
	"a trailing slash does not become a double slash",
	"https://example.org/uploads/vision",
	f_visionuploadurl("https://example.org/")
);
f_check(
	"the read-back URL carries the reference",
	"https://example.org/uploads/vision/".$strvalidref,
	f_visionuploadurl("https://example.org",$strvalidref)
);
f_check(
	"no URL is built from a reference that failed the whitelist",
	"",
	f_visionuploadurl("https://example.org","../../etc/passwd")
);
f_check("no base URL, no URL","",f_visionuploadurl(""));

// ---------------------------------------------------------------------------
f_group("f_visionsearchjs: rendered by PHP, checked by node");

//Labels chosen to break a hand-written escaper: a quote, a double quote, a backslash, a real
//newline, and non-ASCII. Every one of them crosses PHP into JavaScript through json_encode.
$arrlabels=array(
	"attached" => "Image jointe : posez votre question \"à son sujet\".",
	"sending" => "Envoi de l'image...\nPatientez.",
	"errorformat" => "Ce fichier n'est pas une image JPEG ou PNG.",
	"errorbig" => "C:\\photos\\trop-lourde.jpg",
	"errorupload" => "L'envoi a échoué. </script> Réessayez.",
	"errorexpired" => "Image purgée après 30 jours.",
	"drophint" => "Déposez ici une image\npour la joindre à la question.",
	"dropmultiple" => "Plusieurs fichiers déposés\n: seule la \"première\" image a été jointe.",
	"dropnofile" => "Glissez un fichier depuis C:\\Users\\photos\\\nUne image glissée d'une page web ne porte pas de fichier."
);
$strjs=f_visionsearchjs("/app/upload-vision.php?apicolor=Green",25*1024*1024,1600,$arrlabels);

f_check("the script is not empty",true,strlen($strjs)>2000);
//json_encode escapes the slashes by default, which is precisely why the </script> case below is
//safe; the expected literal is therefore the encoded one, never a hand-written string.
f_check("the endpoint is the shim, never the API",true,strpos($strjs,"var strendpoint=".json_encode("/app/upload-vision.php?apicolor=Green").";")!==false);
f_check("no API key can be in it",true,strpos(strtolower($strjs),"x-api-key")===false);

//The real newline of the "sending" label must have been escaped into the JavaScript source. It is
//the exact defect of 2026-08-29: a real newline inside a string literal breaks the whole block.
$arrjslines=explode("\n",$strjs);
$intlinewithsending=-1;
$lngi=0;
for ($lngi=0;$lngi<count($arrjslines);$lngi++) {
	if (strpos($arrjslines[$lngi],"var strlabelsending=")===0) {
		$intlinewithsending=$lngi;
	}
}
f_check("the label carrying a newline is emitted on one single line",true,$intlinewithsending>=0 && substr(trim($arrjslines[$intlinewithsending]),-1)===";");
f_check("and its newline is escaped, not real",true,$intlinewithsending>=0 && strpos($arrjslines[$intlinewithsending],'\\n')!==false);
//A </script> inside a label would end the block early in the browser. json_encode with default
//flags escapes the slash, which is what makes that safe here.
f_check("a closing script tag inside a label cannot end the block",true,strpos($strjs,"</script>")===false);

//The verification that matters: run a real JavaScript parser on what PHP actually produced.
f_nodecheck('the rendered script of the "+" button',$strjs);
// ---------------------------------------------------------------------------
f_group("f_visionerrordetailtext: what a 422 carries instead of an error");

f_check(
	"a FastAPI validation error",
	"Value error, Either question, question_hashed or image_ref must be provided",
	f_visionerrordetailtext(array(array("type"=>"value_error","loc"=>array("body"),"msg"=>"Value error, Either question, question_hashed or image_ref must be provided")))
);
f_check("a plain string detail","Not Found",f_visionerrordetailtext("Not Found"));
f_check("two entries are joined","first second",f_visionerrordetailtext(array(array("msg"=>"first"),array("msg"=>"second"))));
f_check("an entry without a msg is dropped","kept",f_visionerrordetailtext(array(array("loc"=>array("body")),array("msg"=>"kept"))));
f_check("nothing printable","",f_visionerrordetailtext(null));

// ---------------------------------------------------------------------------
f_group("f_visionapierrormessage: the ceiling of the feature, said in words");

//The exact body a deployment that predates the picture search returns for a photo sent with no
//words: Pydantic ignores the unknown image_ref, the validator then sees an empty request, and the
//refusal never reaches the endpoint, so it is not shaped like a Text2SQLResponse.
$strimageonlylabel="This API cannot yet answer a photo sent without a question.";
$arr422=array(
	"detail"=>array(array("type"=>"value_error","loc"=>array("body"),"msg"=>"Value error, Either question, question_hashed or image_ref must be provided")),
	"error_code"=>"422",
	"is_retryable"=>false,
	"provider"=>"text2sql-api"
);
f_check("a photo alone against a colour that ignores image_ref",$strimageonlylabel,f_visionapierrormessage($arr422,$strimageonlylabel));
f_check(
	"the same 422 with words in the box falls back to the detail",
	"Value error, Either question, question_hashed or image_ref must be provided",
	f_visionapierrormessage($arr422,"")
);
f_check("an answer that already carries an error is left alone","",f_visionapierrormessage(array("error"=>"Text2SQL API transport error: timeout","error_code"=>"28"),$strimageonlylabel));
f_check("a successful answer says nothing","",f_visionapierrormessage(array("sql_query"=>"SELECT 1","error"=>null,"justification"=>""),$strimageonlylabel));
f_check("a failure with neither error nor detail still prints its code","HTTP 502",f_visionapierrormessage(array("error_code"=>"502"),$strimageonlylabel));
f_check("a body that is not an array","",f_visionapierrormessage("<html>502 Bad Gateway</html>",$strimageonlylabel));

// ---------------------------------------------------------------------------
f_group('The "+" block tells the arrow that the hidden field changed');

f_check("the script dispatches the contact event",true,strpos($strjs,"'t2simagerefchange'")!==false);
f_check("the event bubbles, since the listener sits on document",true,strpos($strjs,"{bubbles:true}")!==false);

//Every write to the hidden field must be followed by the notification, or the arrow keeps a stale
//state: a photo attached and no arrow, or a photo removed and an arrow that still submits. Nothing
//on the page would look broken, which is exactly why this is counted rather than looked at.
$arrafterwrite=explode("objref.value=",$strjs);
array_shift($arrafterwrite);
$lngwrites=count($arrafterwrite);
$lngnotified=0;
foreach ($arrafterwrite as $strafterwrite) {
	$lngsemicolon=strpos($strafterwrite,";");
	if ($lngsemicolon===false) {
		continue;
	}
	if (strpos(substr($strafterwrite,$lngsemicolon+1),"f_visionnotify();")===0) {
		$lngnotified++;
	}
}
f_check("the script does write the hidden field",true,$lngwrites>=4);
f_check("every write to it notifies the arrow (".$lngwrites." writes)",$lngwrites,$lngnotified);

// ---------------------------------------------------------------------------
f_group("The search-box block, rendered by PHP and parsed by node");

//The other end of the contact point lives in lib/text2sql-search.inc.php, a file that opens a
//database connection when it loads and can never be required here. What is extracted instead is
//the run of literal echo lines between two markers, rendered by a real PHP process, exactly as the
//page renders it. Nothing is unescaped by hand.
$strsearchsource=__DIR__."/../lib/text2sql-search.inc.php";
$arrsourcelines=file($strsearchsource,FILE_IGNORE_NEW_LINES);
$arrblocklines=array();
$intinsideblock=false;
$strinterpolatedline="";
foreach ($arrsourcelines as $strsourceline) {
	$strtrimmed=trim($strsourceline);
	if (strpos($strtrimmed,"//--- search-box script: begin ---")===0) {
		$intinsideblock=true;
		continue;
	}
	if (strpos($strtrimmed,"//--- search-box script: end ---")===0) {
		$intinsideblock=false;
		continue;
	}
	if (!$intinsideblock || $strtrimmed==="" || strpos($strtrimmed,"//")===0) {
		continue;
	}
	//A dollar sign in there would mean PHP interpolation, which this block must not have: a value
	//crossing into JavaScript belongs in a rendering function where json_encode escapes it, the way
	//f_visionsearchjs() does. It would also make this extraction a lie.
	if (strpos($strtrimmed,'echo "')!==0 || substr($strtrimmed,-2)!=='";' || strpos($strtrimmed,'$')!==false) {
		$strinterpolatedline=$strtrimmed;
	}
	$arrblocklines[]=$strtrimmed;
}
f_check("the two markers delimit a block of echo lines",true,count($arrblocklines)>10);
f_check("no PHP interpolation inside it","",$strinterpolatedline);

$strsearchboxjs="";
if (count($arrblocklines)>0 && $strinterpolatedline==="") {
	$strblockfile=tempnam(sys_get_temp_dir(),"searchbox").".php";
	file_put_contents($strblockfile,"<?php\n".implode("\n",$arrblocklines)."\n");
	$arrrendered=array();
	$lngrenderstatus=1;
	exec(escapeshellarg(PHP_BINARY)." ".escapeshellarg($strblockfile)." 2>&1",$arrrendered,$lngrenderstatus);
	@unlink($strblockfile);
	$strsearchboxjs=implode("\n",$arrrendered);
	f_check("PHP renders the block without an error",0,$lngrenderstatus);
}
f_check("the block listens to the contact event",true,strpos($strsearchboxjs,"document.addEventListener('t2simagerefchange',updateSubmitArrow);")!==false);
f_check("it reads the hidden field of the other block",true,strpos($strsearchboxjs,"document.getElementById('searchImageRef')")!==false);
//One predicate, defined once and called by the arrow, the Enter key and the click. A second copy of
//the same condition is how the three ends drift apart.
f_check("one predicate, defined once and called three times",4,substr_count($strsearchboxjs,"hasSearchContent()"));
f_check("no caller tests the typed text on its own any more",false,strpos($strsearchboxjs,"textarea.value.trim().length>0){")!==false);
f_nodecheck("the rendered search-box block",$strsearchboxjs);

// ---------------------------------------------------------------------------
f_group('A file dropped on the search box takes the path of the "+" button');

//The whole ticket rests on one fact of the code: f_visionhandlefile() takes a File, not an input,
//because both file inputs already hand it this.files[0]. A dropped file is that same object, so the
//drop reuses the resizing, the upload through the shim, the thumbnail and the hidden field as they
//are. A second preparation or a second upload appearing here would mean the reuse was abandoned.
f_check("the drop hands its file to the function the menu already calls",true,strpos($strjs,"f_visionhandlefile(objfirst,")!==false);
f_check("and does not open a second upload path",1,substr_count($strjs,"fetch(strendpoint"));
f_check("the drop zone is looked up in the DOM",true,strpos($strjs,"document.getElementById('searchImageDropZone')")!==false);

//dragover must preventDefault on every event or drop never fires. First cause of drag and drops
//that "do not work", and invisible on reading: the page simply ignores the file.
f_check(
	"dragover prevents the default on the zone",
	true,
	strpos($strjs,"objdropzone.addEventListener('dragover',function(objevent){if(!f_visiondragisforus(objevent)){return;}objevent.preventDefault();")!==false
);
f_check("the four drag events are listened to on the zone",4,substr_count($strjs,"objdropzone.addEventListener("));
//dragleave fires again on each child crossed during the trip. Without a counter the overlay blinks
//from the moment the pointer enters the textarea to the moment it leaves the box.
f_check("the overlay is driven by a depth counter",true,strpos($strjs,"lngdragdepth++")!==false && strpos($strjs,"lngdragdepth--")!==false);
//An overlay that swallowed the pointer would fire dragleave on what it covers, as it appears.
f_check("the overlay lets the drag through",true,strpos($strjs,"pointer-events:none")!==false);

//The guard at the scale of the document: without it the browser replaces the page with the file
//dropped beside the box, and the question being typed goes with it.
f_check(
	"a file dropped beside the box does not navigate",
	true,
	strpos($strjs,"document.addEventListener('drop',function(objevent){if(!f_visiondragcarriesfiles(objevent)){return;}objevent.preventDefault();")!==false
);
f_check("and the pointer says so before the drop",true,strpos($strjs,"dropEffect='none'")!==false);
//A drag is read through types and never through files: the browser withholds the files themselves
//until the drop, so a test on .files during dragover is always empty and the overlay never shows.
f_check("the hovering drag is read through dataTransfer.types",true,strpos($strjs,"objtransfer.types")!==false);
f_check("a drag from a web page carries a URL, which is out of scope",true,strpos($strjs,"'text/uri-list'")!==false);
f_check("and is refused with a sentence, not in silence",true,strpos($strjs,"f_visionmessage(strerrordropnofile,true)")!==false);
//The file manager sends several files as readily as one. Only the first image is attached, and the
//sentence saying so travels with the file down to the success message: printed any earlier it would
//be overwritten by "sending" two lines later, and the visitor would never read it.
f_check("only the first image of a multiple drop is attached",true,strpos($strjs,"lngfiles>1?strlabeldropmultiple:''")!==false);
f_check("and the note survives as far as the attached sentence",true,strpos($strjs,"strnote?strlabelattached+' '+strnote:strlabelattached")!==false);
//Nothing is submitted by the drop itself: the arrow and the Enter key
//submit, and they learn about the photo through the event the hidden field dispatches.
f_check("the drop submits nothing by itself",false,strpos($strjs,"form.submit")!==false);

//The same one-line rule as the six labels that predate them: a real newline inside a literal is the
//exact defect of 2026-08-29, and it kills the whole block in silence.
$arrjslabellines=explode("\n",$strjs);
$arrdroplabelvars=array("strlabeldrophint","strlabeldropmultiple","strerrordropnofile");
foreach ($arrdroplabelvars as $strdroplabelvar) {
	$strdroplabelline="";
	foreach ($arrjslabellines as $strjslabelline) {
		if (strpos($strjslabelline,"var ".$strdroplabelvar."=")===0) {
			$strdroplabelline=$strjslabelline;
		}
	}
	f_check("var ".$strdroplabelvar." is emitted on one single line",true,$strdroplabelline!=="" && substr(trim($strdroplabelline),-1)===";");
	f_check("and its newline is escaped, not real",true,$strdroplabelline!=="" && strpos($strdroplabelline,'\\n')!==false);
}

//Three files have to move together, and a label missing from any one of them fails silently: an
//overlay with no sentence, or a key defined in one language and read in the other.
$strsearchsourcetext=file_get_contents($strsearchsource);
$strlanguagesourcetext=file_get_contents(__DIR__."/../lib/language.inc.php");
f_check("the search box carries the id the script looks for",true,strpos($strsearchsourcetext,"id=\\\"searchImageDropZone\\\"")!==false);
$arrdroplabelkeys=array(
	"drophint" => "lib_t2s_image_drop_hint",
	"dropmultiple" => "lib_t2s_image_drop_multiple",
	"dropnofile" => "lib_t2s_image_drop_nofile"
);
foreach ($arrdroplabelkeys as $strdropkey => $strdropstring) {
	f_check("'".$strdropstring."' is defined in English and in French",2,substr_count($strlanguagesourcetext,"'".$strdropstring."' =>"));
	f_check("'".$strdropkey."' is passed to f_visionsearchjs()",true,strpos($strsearchsourcetext,"\"".$strdropkey."\" => (string)get_movie_string('".$strdropstring."')")!==false);
}

// ---------------------------------------------------------------------------
echo "\n".$lngchecks." checks, ".$lngfailures." failure(s).\n";
exit($lngfailures>0 ? 1 : 0);
?>
