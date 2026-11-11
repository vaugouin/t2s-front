<?php
//The picture-based search on the web front. This file holds everything of that
//feature that can be decided without a database, a session or a network call: sniffing the format
//of a payload, validating an image_ref, picking the API colour, and building the JavaScript of the
//"+" button, including the event by which that button tells the submit arrow of the search box
//that a photo just came or went.
//
//It is deliberately SIDE-EFFECT FREE and can be required on its own, which is the structural rule
//of this repository (AGENTS.md, "Tests"): lib/global-light.inc.php opens a database connection when
//it loads, so nothing placed there can ever be tested in isolation. The endpoint body that uses
//these helpers lives in lib/upload-vision.inc.php, which does have side effects and is included by
//the two-line shell html/app/upload-vision.php.
//
//Every guard here mirrors one that the API already applies (fastapi-text2sql uploads.py).
//The duplication is on purpose: the front must be able to say "this is not a
//JPEG" in the visitor's language without a round trip, and must never build a URL out of a string
//that came from a client.

if (!function_exists('f_visionmaxuploadbytes')) {
	function f_visionmaxuploadbytes() {
		//25 MB, the same ceiling as uploads.MAX_UPLOAD_IMAGE_BYTES on the API side. The browser
		//resizes before sending, so a real photo lands two orders of magnitude below this; the
		//ceiling exists for what the browser could not resize, not for the normal case.
		return 25*1024*1024;
	}
}

if (!function_exists('f_visionmaximageside')) {
	function f_visionmaximageside() {
		//Long side, in pixels, of what the browser actually uploads. A phone photo is 3 to 12 MB and
		//would cross PHP, the reverse proxy and the API before being downscaled by the vision model
		//anyway; 1600 px keeps a poster or a frame readable and divides the inference bill.
		return 1600;
	}
}

if (!function_exists('f_visionsniffimageformat')) {
	function f_visionsniffimageformat($strbytes) {
		//Purpose: Identify an image by its magic number, ignoring any declared type
		//Params:	$strbytes: The payload, or at least its first eight bytes
		//Returns: "jpg", "png", or "" when the bytes are neither
		//
		//The format is decided by the bytes, never by Content-Type and never by a file name. A
		//client-supplied name is a path-traversal surface and is not read anywhere in this feature.
		$strbytes=(string)$strbytes;
		if (substr($strbytes,0,3)==="\xff\xd8\xff") {
			return "jpg";
		}
		if (substr($strbytes,0,8)==="\x89PNG\r\n\x1a\n") {
			return "png";
		}
		return "";
	}
}

if (!function_exists('f_visionmediatype')) {
	function f_visionmediatype($strformat) {
		//Purpose: Give the HTTP media type of a stored image format
		//Params:	$strformat: "jpg" or "png"
		//Returns: The media type, defaulting to application/octet-stream
		switch ((string)$strformat) {
		case "jpg":
			return "image/jpeg";
		case "png":
			return "image/png";
		}
		return "application/octet-stream";
	}
}

if (!function_exists('f_visionimagerefisvalid')) {
	function f_visionimagerefisvalid($strimageref) {
		//Purpose: Tell whether a string is exactly one name the API generator could have produced
		//Params:	$strimageref: The candidate reference, straight from a URL or a form
		//Returns: true when it matches YYYYMMDD-HHMMSS_<kind>_<version>_<md5>.<jpg|png>
		//
		//Same pattern as uploads._IMAGE_REF_PATTERN. Anything else is refused before a URL is built
		//from it, which is why the raw request value can be read without the site generic input
		//filter: the whitelist is the filter here.
		$strimageref=(string)$strimageref;
		if ($strimageref==="") {
			return false;
		}
		return preg_match('/^\d{8}-\d{6}_[a-z][a-z0-9]*_[A-Za-z0-9][A-Za-z0-9._-]*_[0-9a-f]{32}\.(jpg|png)$/',$strimageref)===1;
	}
}

if (!function_exists('f_visionformatfromref')) {
	function f_visionformatfromref($strimageref) {
		//Purpose: Read the stored format off a valid image_ref
		//Params:	$strimageref: A reference already accepted by f_visionimagerefisvalid()
		//Returns: "jpg", "png", or "" when the reference is not valid
		if (!f_visionimagerefisvalid($strimageref)) {
			return "";
		}
		return strtolower(substr((string)$strimageref,-3));
	}
}

if (!function_exists('f_visionapibaseurl')) {
	function f_visionapibaseurl($strapicolor,$strblueurl,$strgreenurl) {
		//Purpose: Pick the API base URL for a colour, exactly as f_movietext2sqlsearch() does
		//Params:	$strapicolor: "Blue" or anything else
		//			$strblueurl: Base URL of the blue deployment
		//			$strgreenurl: Base URL of the green deployment
		//Returns: The base URL, without a trailing slash
		$strbaseurl=((string)$strapicolor==="Blue"?(string)$strblueurl:(string)$strgreenurl);
		return rtrim($strbaseurl,"/");
	}
}

if (!function_exists('f_visionuploadurl')) {
	function f_visionuploadurl($strbaseurl,$strimageref="") {
		//Purpose: Build the deposit or read-back URL of the API
		//Params:	$strbaseurl: The base URL of the chosen colour
		//			$strimageref: Empty for the deposit, a valid reference for the read back
		//Returns: The full URL, or "" when a reference was given and is not valid
		$strbaseurl=rtrim((string)$strbaseurl,"/");
		if ($strbaseurl==="") {
			return "";
		}
		if ((string)$strimageref==="") {
			return $strbaseurl."/uploads/vision";
		}
		if (!f_visionimagerefisvalid($strimageref)) {
			return "";
		}
		return $strbaseurl."/uploads/vision/".rawurlencode((string)$strimageref);
	}
}

if (!function_exists('f_visionjsonresponse')) {
	function f_visionjsonresponse($lnghttpcode,$arrpayload) {
		//Purpose: End the request with a JSON body, the only thing the shim ever answers besides
		//			image bytes
		//Params:	$lnghttpcode: HTTP status to send
		//			$arrpayload: Associative array to encode
		//Returns: Nothing, the script ends here
		if (!headers_sent()) {
			http_response_code((int)$lnghttpcode);
			header('Content-Type: application/json; charset=utf-8');
			header('Cache-Control: no-store');
		}
		echo json_encode($arrpayload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		exit;
	}
}

if (!function_exists('f_visionerrordetailtext')) {
	function f_visionerrordetailtext($mxddetail) {
		//Purpose: Flatten the "detail" of a FastAPI validation error into one printable line
		//Params:	$mxddetail: A string, a list of entries carrying a "msg", or anything else
		//Returns: The messages joined by a space, or "" when there is nothing printable
		//
		//A 422 never reaches the body of the endpoint, so it is not shaped like Text2SQLResponse and
		//carries no "error" field at all: FastAPI answers with its own {"detail":[{"msg":...}]}.
		//Reading it here is what turns a bare status code into a sentence.
		if (is_string($mxddetail)) {
			return trim($mxddetail);
		}
		if (!is_array($mxddetail)) {
			return "";
		}
		$arrparts=array();
		foreach ($mxddetail as $mxdentry) {
			$strpart="";
			if (is_string($mxdentry)) {
				$strpart=trim($mxdentry);
			} elseif (is_array($mxdentry) && isset($mxdentry['msg'])) {
				$strpart=trim((string)$mxdentry['msg']);
			}
			if ($strpart!=="") {
				$arrparts[]=$strpart;
			}
		}
		return implode(" ",$arrparts);
	}
}

if (!function_exists('f_visionapierrormessage')) {
	function f_visionapierrormessage($arrresponse,$strimageonlylabel="") {
		//Purpose: Give a readable line for an API answer that carries no "error" of its own
		//Params:	$arrresponse: Decoded body of /search/text2sql, as f_movietext2sqlsearch() returns it
		//			$strimageonlylabel: Localized sentence to print on a 422, passed only when the request
		//				was a photo with no words; empty otherwise
		//Returns: The message to print, or "" when the answer already says what happened
		//
		//The ceiling of this feature has a name and a date. A deployment that predates
		//An API that predates the picture search ignores the unknown image_ref field, its validator then sees neither a
		//question nor a hash, and it refuses with a 422 before a single line of the endpoint runs. The
		//omit-when-empty rule of f_movietext2sqlsearch() does not protect that case, since here the
		//field is full rather than empty. So the front says it in a sentence, rather than printing a
		//status code or, worse, rendering a page with nothing in it.
		if (!is_array($arrresponse)) {
			return "";
		}
		if (isset($arrresponse['error']) && trim((string)$arrresponse['error'])!=="") {
			return "";
		}
		if (isset($arrresponse['sql_query'])) {
			return "";
		}
		$strcode=isset($arrresponse['error_code'])?trim((string)$arrresponse['error_code']):"";
		if ($strcode==="422" && (string)$strimageonlylabel!=="") {
			return (string)$strimageonlylabel;
		}
		$strdetail=f_visionerrordetailtext(isset($arrresponse['detail'])?$arrresponse['detail']:null);
		if ($strdetail!=="") {
			return $strdetail;
		}
		if ($strcode!=="") {
			return "HTTP ".$strcode;
		}
		return "";
	}
}

if (!function_exists('f_visionsearchjs')) {
	function f_visionsearchjs($strendpointurl,$lngmaxbytes,$lngmaxside,$arrlabels) {
		//Purpose: Build the JavaScript that drives the "+" button of the search box
		//Params:	$strendpointurl: Same-origin URL of the PHP shim, never the API
		//			$lngmaxbytes: Ceiling applied in the browser before the upload
		//			$lngmaxside: Long side, in pixels, of the resized upload
		//			$arrlabels: Localized strings, keys attached, sending, errorformat, errorbig,
		//				errorupload, errorexpired, and drophint,
		//				dropmultiple, dropnofile
		//Returns: The body of a <script> block, as a string
		//
		//Every value that crosses from PHP into JavaScript goes through json_encode, per AGENTS.md
		//("Emitting JavaScript from PHP"). On 2026-08-29 a single hand-escaped backslash put a real
		//newline inside a JavaScript string literal, which killed a whole <script> block silently:
		//php -l passed, the page rendered, the button did nothing. Nothing here is hand-escaped, and
		//test/vision-image-test.php renders this function and runs node --check on the result.
		$arrlabels=is_array($arrlabels)?$arrlabels:array();
		$strattached=isset($arrlabels['attached'])?(string)$arrlabels['attached']:"";
		$strsending=isset($arrlabels['sending'])?(string)$arrlabels['sending']:"";
		$strerrorformat=isset($arrlabels['errorformat'])?(string)$arrlabels['errorformat']:"";
		$strerrorbig=isset($arrlabels['errorbig'])?(string)$arrlabels['errorbig']:"";
		$strerrorupload=isset($arrlabels['errorupload'])?(string)$arrlabels['errorupload']:"";
		$strerrorexpired=isset($arrlabels['errorexpired'])?(string)$arrlabels['errorexpired']:"";
		$strdrophint=isset($arrlabels['drophint'])?(string)$arrlabels['drophint']:"";
		$strdropmultiple=isset($arrlabels['dropmultiple'])?(string)$arrlabels['dropmultiple']:"";
		$strdropnofile=isset($arrlabels['dropnofile'])?(string)$arrlabels['dropnofile']:"";

		$arrlines=array();
		$arrlines[]="(function(){";
		$arrlines[]="var objbutton=document.getElementById('searchImageButton');";
		$arrlines[]="var objmenu=document.getElementById('searchImageMenu');";
		$arrlines[]="var objmenulibrary=document.getElementById('searchImageMenuLibrary');";
		$arrlines[]="var objmenucamera=document.getElementById('searchImageMenuCamera');";
		$arrlines[]="var objfilelibrary=document.getElementById('searchImageFileLibrary');";
		$arrlines[]="var objfilecamera=document.getElementById('searchImageFileCamera');";
		$arrlines[]="var objref=document.getElementById('searchImageRef');";
		$arrlines[]="var objstatus=document.getElementById('searchImageStatus');";
		$arrlines[]="var objpreview=document.getElementById('searchImagePreview');";
		$arrlines[]="var objmessage=document.getElementById('searchImageMessage');";
		$arrlines[]="var objclear=document.getElementById('searchImageClear');";
		$arrlines[]="var objtextarea=document.getElementById('search');";
		//The relative box of the search box, the one already holding the textarea,
		//the "+" and the submit arrow. It is the drop target, and it is read before the guard
		//below, so a page without the "+" still adds no listener at all.
		$arrlines[]="var objdropzone=document.getElementById('searchImageDropZone');";
		$arrlines[]="if(!objbutton||!objref){return;}";
		$arrlines[]="var strendpoint=".json_encode((string)$strendpointurl).";";
		$arrlines[]="var lngmaxbytes=".json_encode((int)$lngmaxbytes).";";
		$arrlines[]="var lngmaxside=".json_encode((int)$lngmaxside).";";
		$arrlines[]="var strlabelattached=".json_encode($strattached).";";
		$arrlines[]="var strlabelsending=".json_encode($strsending).";";
		$arrlines[]="var strerrorformat=".json_encode($strerrorformat).";";
		$arrlines[]="var strerrorbig=".json_encode($strerrorbig).";";
		$arrlines[]="var strerrorupload=".json_encode($strerrorupload).";";
		$arrlines[]="var strerrorexpired=".json_encode($strerrorexpired).";";
		$arrlines[]="var strlabeldrophint=".json_encode($strdrophint).";";
		$arrlines[]="var strlabeldropmultiple=".json_encode($strdropmultiple).";";
		$arrlines[]="var strerrordropnofile=".json_encode($strdropnofile).";";
		$arrlines[]="var strobjecturl='';";
		//The contact point between two sealed <script> blocks. The submit arrow of
		//the search box lives in another closure (lib/text2sql-search.inc.php) and cannot be reached
		//from here; it now shows itself for an attached photo as well as for typed words, so it has
		//to learn that the hidden field just changed. An event on the field, bubbling to document,
		//keeps both blocks closed and adds nothing to window.
		$arrlines[]="function f_visionnotify(){var objevent=null;if(typeof window.CustomEvent==='function'){objevent=new window.CustomEvent('t2simagerefchange',{bubbles:true});}else if(document.createEvent){objevent=document.createEvent('Event');objevent.initEvent('t2simagerefchange',true,false);}if(objevent&&objref.dispatchEvent){objref.dispatchEvent(objevent);}}";
		$arrlines[]="function f_visionmessage(strtext,intiserror){if(!objmessage){return;}objmessage.textContent=strtext;objmessage.style.color=intiserror?'#ff6b6b':'';}";
		$arrlines[]="function f_visionstatus(intvisible){if(objstatus){objstatus.style.display=intvisible?'flex':'none';}}";
		$arrlines[]="function f_visionreleaseurl(){if(strobjecturl){try{URL.revokeObjectURL(strobjecturl);}catch(e){}strobjecturl='';}}";
		$arrlines[]="function f_visionshowpreview(strsrc){if(!objpreview){return;}if(strsrc){objpreview.src=strsrc;objpreview.style.display='inline-block';}else{objpreview.removeAttribute('src');objpreview.style.display='none';}}";
		$arrlines[]="function f_visionclear(){f_visionreleaseurl();objref.value='';f_visionnotify();f_visionshowpreview('');f_visionmessage('',false);f_visionstatus(false);}";
		$arrlines[]="function f_visionclosemenu(){if(objmenu){objmenu.style.display='none';}objbutton.setAttribute('aria-expanded','false');}";
		$arrlines[]="function f_visionopenmenu(){if(objmenu){objmenu.style.display='block';}objbutton.setAttribute('aria-expanded','true');}";
		$arrlines[]="function f_visionmenuisopen(){return objmenu&&objmenu.style.display!=='none';}";
		$arrlines[]="function f_visionhidecamera(){if(objmenucamera){objmenucamera.style.display='none';}}";
		//A file input honours capture= only where a camera can actually answer it; on a desktop the
		//attribute is ignored and the entry would open the same picker as the other one, which is
		//why the entry is removed rather than left as a duplicate. enumerateDevices lists device
		//kinds without asking for permission, labels stay empty until one is granted.
		$arrlines[]="if(!('capture' in document.createElement('input'))){f_visionhidecamera();}";
		$arrlines[]="else if(navigator.mediaDevices&&navigator.mediaDevices.enumerateDevices){navigator.mediaDevices.enumerateDevices().then(function(arrdevices){var inthascamera=false;var lngi=0;for(lngi=0;lngi<arrdevices.length;lngi++){if(arrdevices[lngi]&&arrdevices[lngi].kind==='videoinput'){inthascamera=true;}}if(!inthascamera){f_visionhidecamera();}},function(){});}";
		$arrlines[]="function f_visionprepare(objfile){return new Promise(function(resolve,reject){var strurl=URL.createObjectURL(objfile);var objimage=new Image();objimage.onload=function(){var lngwidth=objimage.naturalWidth||objimage.width;var lngheight=objimage.naturalHeight||objimage.height;var lnglongside=Math.max(lngwidth,lngheight);var dblscale=(lnglongside>lngmaxside?lngmaxside/lnglongside:1);var intisjpegorpng=(objfile.type==='image/jpeg'||objfile.type==='image/png');if(dblscale>=1&&intisjpegorpng&&objfile.size<=lngmaxbytes){URL.revokeObjectURL(strurl);resolve({blob:objfile,type:objfile.type});return;}var objcanvas=document.createElement('canvas');objcanvas.width=Math.max(1,Math.round(lngwidth*dblscale));objcanvas.height=Math.max(1,Math.round(lngheight*dblscale));var objcontext=objcanvas.getContext?objcanvas.getContext('2d'):null;if(!objcontext){URL.revokeObjectURL(strurl);reject(new Error('canvas'));return;}objcontext.drawImage(objimage,0,0,objcanvas.width,objcanvas.height);if(!objcanvas.toBlob){URL.revokeObjectURL(strurl);reject(new Error('toblob'));return;}objcanvas.toBlob(function(objblob){URL.revokeObjectURL(strurl);if(!objblob){reject(new Error('encode'));return;}resolve({blob:objblob,type:'image/jpeg'});},'image/jpeg',0.85);};objimage.onerror=function(){URL.revokeObjectURL(strurl);reject(new Error('decode'));};objimage.src=strurl;});}";
		$arrlines[]="function f_visionupload(objblob,strtype,strnote){f_visionstatus(true);f_visionmessage(strlabelsending,false);return fetch(strendpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':strtype},body:objblob}).then(function(objresponse){return objresponse.json().then(function(objjson){return {ok:objresponse.ok,body:objjson};},function(){return {ok:false,body:{}};});}).then(function(objresult){if(!objresult.ok||!objresult.body||!objresult.body.image_ref){objref.value='';f_visionnotify();f_visionmessage((objresult.body&&objresult.body.error)?objresult.body.error:strerrorupload,true);return;}objref.value=objresult.body.image_ref;f_visionnotify();f_visionreleaseurl();strobjecturl=URL.createObjectURL(objblob);f_visionshowpreview(strobjecturl);f_visionmessage(strnote?strlabelattached+' '+strnote:strlabelattached,false);if(objtextarea&&objtextarea.focus){objtextarea.focus();}}).catch(function(){objref.value='';f_visionnotify();f_visionmessage(strerrorupload,true);});}";
		$arrlines[]="function f_visionhandlefile(objfile,strnote){if(!objfile){return;}f_visionstatus(true);if(objfile.type&&objfile.type.indexOf('image/')!==0){f_visionmessage(strerrorformat,true);return;}f_visionmessage(strlabelsending,false);f_visionprepare(objfile).then(function(objprepared){if(objprepared.blob.size>lngmaxbytes){f_visionmessage(strerrorbig,true);return null;}return f_visionupload(objprepared.blob,objprepared.type,strnote);}).catch(function(){objref.value='';f_visionnotify();f_visionmessage(strerrorformat,true);});}";
		$arrlines[]="objbutton.addEventListener('click',function(objevent){objevent.preventDefault();objevent.stopPropagation();if(f_visionmenuisopen()){f_visionclosemenu();}else{f_visionopenmenu();}});";
		$arrlines[]="document.addEventListener('click',function(objevent){if(!f_visionmenuisopen()){return;}if(objevent.target===objbutton){return;}if(objmenu&&objmenu.contains&&objmenu.contains(objevent.target)){return;}f_visionclosemenu();});";
		$arrlines[]="document.addEventListener('keydown',function(objevent){if(objevent&&(objevent.key==='Escape'||objevent.keyCode===27)){f_visionclosemenu();}});";
		$arrlines[]="if(objmenulibrary&&objfilelibrary){objmenulibrary.addEventListener('click',function(){f_visionclosemenu();objfilelibrary.click();});}";
		$arrlines[]="if(objmenucamera&&objfilecamera){objmenucamera.addEventListener('click',function(){f_visionclosemenu();objfilecamera.click();});}";
		$arrlines[]="if(objfilelibrary){objfilelibrary.addEventListener('change',function(){f_visionhandlefile(this.files&&this.files.length?this.files[0]:null);this.value='';});}";
		$arrlines[]="if(objfilecamera){objfilecamera.addEventListener('change',function(){f_visionhandlefile(this.files&&this.files.length?this.files[0]:null);this.value='';});}";
		$arrlines[]="if(objclear){objclear.addEventListener('click',function(objevent){objevent.preventDefault();f_visionclear();});}";
		//The image kept across a page load is served back through the same shim, so a purged one
		//fails on the <img> rather than on the question, and says so with the retention in mind.
		$arrlines[]="if(objpreview&&objpreview.getAttribute('src')){objpreview.addEventListener('error',function(){f_visionshowpreview('');f_visionmessage(strerrorexpired,true);});}";
		//Dragging a file onto the search box attaches it, exactly as the "choose
		//a photo" entry of the menu does. Nothing of the chain is rebuilt: f_visionhandlefile()
		//already takes a File rather than an input, because both file inputs pass it this.files[0],
		//and a dropped file is that same object. Resizing, upload through the same-origin shim,
		//thumbnail, hidden field, the t2simagerefchange event and the focus given
		//back to the textarea all follow untouched.
		//
		//A drag is read through dataTransfer.types and never through .files: during dragenter and
		//dragover the browser withholds the files themselves, and only the list of types is
		//readable. 'Files' is a drag from the file manager, 'text/uri-list' a drag from a web page,
		//which carries a URL and no file and is out of scope: it is refused with a sentence rather
		//than in silence. A plain text drag matches neither, so dropping selected text into the
		//textarea keeps working as it always did.
		$arrlines[]="function f_visiondraghastype(objevent,strtype){var objtransfer=(objevent&&objevent.dataTransfer)?objevent.dataTransfer:null;if(!objtransfer||!objtransfer.types){return false;}var arrtypes=objtransfer.types;var lngi=0;for(lngi=0;lngi<arrtypes.length;lngi++){if(arrtypes[lngi]===strtype){return true;}}return false;}";
		$arrlines[]="function f_visiondragcarriesfiles(objevent){return f_visiondraghastype(objevent,'Files');}";
		$arrlines[]="function f_visiondragisforus(objevent){return f_visiondraghastype(objevent,'Files')||f_visiondraghastype(objevent,'text/uri-list');}";
		//The overlay is built here rather than echoed by the page, so its sentence crosses PHP into
		//JavaScript through json_encode like every other label, and so a page carrying the "+" but
		//no drop zone simply never builds it. pointer-events:none is load bearing: an overlay that
		//swallowed the pointer would fire dragleave on the element it covers, the moment it appears.
		$arrlines[]="var objoverlay=null;";
		$arrlines[]="var lngdragdepth=0;";
		$arrlines[]="function f_visionbuildoverlay(){if(objoverlay||!objdropzone){return objoverlay;}objoverlay=document.createElement('div');objoverlay.id='searchImageDropOverlay';objoverlay.setAttribute('aria-hidden','true');objoverlay.textContent=strlabeldrophint;objoverlay.style.cssText='position:absolute; left:0; top:0; right:0; bottom:0; display:none; align-items:center; justify-content:center; text-align:center; padding:0 12px; z-index:40; border:2px dashed #999; border-radius:6px; background:rgba(255,255,255,0.94); color:#222; font-size:15px; pointer-events:none;';objdropzone.appendChild(objoverlay);return objoverlay;}";
		$arrlines[]="function f_visionshowoverlay(intvisible){var objbuilt=f_visionbuildoverlay();if(!objbuilt){return;}objbuilt.style.display=intvisible?'flex':'none';}";
		$arrlines[]="function f_visiondragreset(){lngdragdepth=0;f_visionshowoverlay(false);}";
		//The file manager happily sends several files at once. Only the first image is attached, and
		//the visitor reads that it was: the sentence travels with the file down to the success
		//message, because anything printed before would be overwritten by "sending" two lines later.
		$arrlines[]="function f_visiondropfile(objevent){var objtransfer=objevent?objevent.dataTransfer:null;var arrfiles=(objtransfer&&objtransfer.files)?objtransfer.files:null;var lngfiles=arrfiles?arrfiles.length:0;var objfirst=null;var lngi=0;if(lngfiles===0){f_visionstatus(true);f_visionmessage(strerrordropnofile,true);return;}for(lngi=0;lngi<lngfiles;lngi++){if(!objfirst&&arrfiles[lngi]&&arrfiles[lngi].type&&arrfiles[lngi].type.indexOf('image/')===0){objfirst=arrfiles[lngi];}}if(!objfirst){f_visionstatus(true);f_visionmessage(strerrorformat,true);return;}f_visionhandlefile(objfirst,lngfiles>1?strlabeldropmultiple:'');}";
		//dragover must preventDefault on EVERY event or drop never fires: first cause of drag and
		//drops that "do not work". dragleave fires again on each child crossed, so the entries are
		//counted and the overlay only goes when the count comes back to zero, otherwise it blinks all
		//along the way. And the zone stops the propagation of the drags it takes, so the document
		//guard below cannot overwrite the copy cursor with its own refusal one.
		$arrlines[]="if(objdropzone){";
		$arrlines[]="objdropzone.addEventListener('dragenter',function(objevent){if(!f_visiondragisforus(objevent)){return;}objevent.preventDefault();objevent.stopPropagation();lngdragdepth++;f_visionshowoverlay(true);});";
		$arrlines[]="objdropzone.addEventListener('dragover',function(objevent){if(!f_visiondragisforus(objevent)){return;}objevent.preventDefault();objevent.stopPropagation();if(objevent.dataTransfer){objevent.dataTransfer.dropEffect='copy';}f_visionshowoverlay(true);});";
		$arrlines[]="objdropzone.addEventListener('dragleave',function(objevent){if(!f_visiondragisforus(objevent)){return;}objevent.stopPropagation();lngdragdepth--;if(lngdragdepth<=0){f_visiondragreset();}});";
		$arrlines[]="objdropzone.addEventListener('drop',function(objevent){if(!f_visiondragisforus(objevent)){return;}objevent.preventDefault();objevent.stopPropagation();f_visiondragreset();f_visiondropfile(objevent);});";
		$arrlines[]="}";
		//The guard at the scale of the document. Without it the browser replaces the page with the
		//file dropped beside the box, and the question being typed goes with it, which the back
		//button does not always give back. It only ever speaks for a drag carrying files, so text
		//dropped anywhere else on the page keeps its normal behaviour.
		$arrlines[]="document.addEventListener('dragover',function(objevent){if(!f_visiondragcarriesfiles(objevent)){return;}objevent.preventDefault();if(objevent.dataTransfer){objevent.dataTransfer.dropEffect='none';}});";
		$arrlines[]="document.addEventListener('drop',function(objevent){if(!f_visiondragcarriesfiles(objevent)){return;}objevent.preventDefault();f_visiondragreset();});";
		$arrlines[]="})();";
		return implode("\n",$arrlines)."\n";
	}
}
?>
