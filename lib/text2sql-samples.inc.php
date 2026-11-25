<?php
echo "<h1>".get_movie_string('lib_t2s_samples_title')."</h1>";
//Vision samples: the pictures of the image evaluations, published read-only by the web server
//under T2S_VISION_SAMPLES_URL (the evaluator's fixtures folder). One click deposits the picture
//again and opens the search on it (f_visionsamplesjs(), lib/vision-image.inc.php). A picture the
//server does not publish (folder not mounted, file gone) hides its button instead of showing a
//broken image.
$strvisionsamplesbaseurl=rtrim((string)env('T2S_VISION_SAMPLES_URL','/vision-samples'),"/");
echo "<div id=\"visionSampleStatus\" role=\"status\" style=\"display:none; margin:6px 0;\"></div>";
echo "<script>".f_visionsamplesjs(
	isset($strvisionendpointurl) ? $strvisionendpointurl : "upload-vision.php",
	"index.php",
	array(
		"sending"=>(string)get_movie_string('lib_t2s_image_sending'),
		"errorupload"=>(string)get_movie_string('lib_t2s_image_error_upload')
	)
)."</script>";

switch ($struilang) {
    case "fr":
        $lngroot=1;
        break;
    case "en":
        $lngroot=1;
        break;
    default:
        $lngroot=1;
        break;
}

//Start display as h2
f_displaysamplesfromroot($lngroot, 2, $struilang);

function f_displaysamplesfromroot($lngroot, $intlevel=1, $struilang="en") {
    global $strsqlns;
    global $intdebug;
    //Filter samples.showall: a plugin may ask for every sample and category, whatever its
    //evaluation state; the default 0 shows only the samples that have a passing run
    $intsamplesshowall=f_pluginfilter("samples.showall",0);
    global $strapicolor;
    global $strtext2sqlapicolor;
    global $strvisionsamplesbaseurl;
    //The version filters which samples are shown (an execution must exist on
    //it), so it is read from the running API rather than from a hand-written string.
    global $strtext2sqlapiblueeellm;
    global $strtext2sqlapigreeneellm;
    global $strtext2sqlapibluet2sllm;
    global $strtext2sqlapigreent2sllm;

    $strapicolorcurrent=(isset($strapicolor) && $strapicolor!="" ? $strapicolor : $strtext2sqlapicolor);
    if ($strapicolorcurrent=="Blue") {
        $strapiversioncurrent=f_text2sqlapiversion("Blue");
        $streemodelcurrent=$strtext2sqlapiblueeellm;
        $strt2smodelcurrent=$strtext2sqlapibluet2sllm;
    } else {
        $strapiversioncurrent=f_text2sqlapiversion("Green");
        $streemodelcurrent=$strtext2sqlapigreeneellm;
        $strt2smodelcurrent=$strtext2sqlapigreent2sllm;
    }

    $arrver=explode(".",(string)$strapiversioncurrent);
    $lngvermajor=(int)($arrver[0] ?? 0);
    $lngverminor=(int)($arrver[1] ?? 0);
    $lngverpatch=(int)($arrver[2] ?? 0);
    $strapiversioncurrentsql=sprintf("%03d.%03d.%03d",$lngvermajor,$lngverminor,$lngverpatch);

    $lngdisplayed=0;

    $strsql="SELECT * FROM ".$strsqlns."T2S_EVALUATION_CATEGORY WHERE ID_PARENT = ".f_longtosql($lngroot)." AND DELETED = 0 ORDER BY DISPLAY_ORDER ASC ";
    if ($intdebug) {
        echo "<br />".$strsql;
    }

    $result=f_sqlselect($strsql);
    if ($result) {
        while ($rstemp=f_sqlresultfetcharray($result)) {
            $lngevalcatid=$rstemp["ID_T2S_EVALUATION_CATEGORY"];
            $strevalcatdesc=($struilang=="fr" && $rstemp['DESCRIPTION_FR']!="" ? $rstemp['DESCRIPTION_FR'] : $rstemp['DESCRIPTION']);
            if ($intsamplesshowall) {
                echo "<h".$intlevel.">".$strevalcatdesc."</h".$intlevel.">";
                $lngdisplayed+=f_displaysamplesfromroot($lngevalcatid, $intlevel+1, $struilang);
            } else {
                ob_start();
                $lngsub=f_displaysamplesfromroot($lngevalcatid, $intlevel+1, $struilang);
                $strsub=ob_get_clean();
                if ($lngsub>0) {
                    echo "<h".$intlevel.">".$strevalcatdesc."</h".$intlevel.">";
                    echo $strsub;
                    $lngdisplayed+=$lngsub;
                }
            }
        }
        f_sqlfreeresult($result);
    }

    $strsql="SELECT * FROM ".$strsqlns."T2S_EVALUATION WHERE ID_T2S_EVALUATION_CATEGORY = ".f_longtosql($lngroot)." AND IS_SAMPLE = 1 AND DELETED = 0 ";
    if (!$intsamplesshowall) {
        //A text sample needs a passing run on the running API version with the default models.
        //An image sample needs a run on that version that identified the picture
        //(ASSERTIONS_VISION_IDENTIFICATION_SCORE, scored apart from ASSERTIONS_TOTAL_SCORE), whatever
        //the vision model: the bench runs several of them on the same pictures.
        $strsql.="AND EXISTS (SELECT 1 FROM ".$strsqlns."T2S_EVALUATION_EXECUTION x WHERE x.ID_T2S_EVALUATION = ".$strsqlns."T2S_EVALUATION.ID_T2S_EVALUATION AND x.DELETED=0 ";
        $strsql.="AND x.API_VERSION = ".f_stringtosql($strapiversioncurrentsql)." ";
        $strsql.="AND ((IFNULL(".$strsqlns."T2S_EVALUATION.INPUT_KIND,'') <> 'image' ";
        $strsql.="AND x.ENTITY_EXTRACTION_MODEL = ".f_stringtosql($streemodelcurrent)." AND x.TEXT2SQL_MODEL = ".f_stringtosql($strt2smodelcurrent)." ";
        $strsql.="AND x.ASSERTIONS_TOTAL_SCORE IS NOT NULL AND x.ASSERTIONS_TOTAL_SCORE <> 0) ";
        $strsql.="OR (".$strsqlns."T2S_EVALUATION.INPUT_KIND = 'image' AND x.ASSERTIONS_VISION_IDENTIFICATION_SCORE > 0))) ";
    }
    $strsql.="ORDER BY DISPLAY_ORDER ASC ";

    if ($intdebug) {
        echo "<br />".$strsql;
    }

    $result=f_sqlselect($strsql);
    if ($result) {
        $inthasitems=false;
        $intimagesopen=false;
        while ($rstemp=f_sqlresultfetcharray($result)) {
            //Image sample: a thumbnail button instead of a question link. The pictures of one
            //category share one row of thumbnails.
            if (isset($rstemp['INPUT_KIND']) && $rstemp['INPUT_KIND']=="image") {
                $strsamplefile=f_visionsamplefile(isset($rstemp['INPUT_IMAGE_PATH']) ? $rstemp['INPUT_IMAGE_PATH'] : "");
                if ($strsamplefile=="") {
                    continue;
                }
                if (!$intimagesopen) {
                    echo "<div style=\"display:flex; flex-wrap:wrap; gap:8px; margin:4px 0 8px 0;\">";
                    $intimagesopen=true;
                }
                $strsamplesrc=$strvisionsamplesbaseurl."/".rawurlencode($strsamplefile);
                $strsampletitle=get_movie_string('lib_t2s_vision_sample_title');
                echo "<button type=\"button\" class=\"t2s-vision-sample\" data-src=\"".f_htmlattr($strsamplesrc)."\" title=\"".f_htmlattr($strsampletitle)."\" aria-label=\"".f_htmlattr($strsampletitle)."\" style=\"padding:0; border:1px solid #444; border-radius:6px; background:none; cursor:pointer; line-height:0;\">";
                echo "<img src=\"".f_htmlattr($strsamplesrc)."\" alt=\"\" loading=\"lazy\" onerror=\"this.parentNode.style.display='none';\" style=\"height:96px; width:auto; max-width:160px; object-fit:cover; border-radius:5px;\">";
                echo "</button>";
                $lngdisplayed++;
                continue;
            }
            if (!$inthasitems) {
                echo "<ul style=\"margin-top:0; margin-bottom:0; padding-left:18px;\">";
                $inthasitems=true;
            }
            $lngevalid=$rstemp["ID_T2S_EVALUATION"];
            $strevalquestion=($struilang=="fr" && isset($rstemp['QUESTION_FR']) && $rstemp['QUESTION_FR']!="" ? $rstemp['QUESTION_FR'] : $rstemp['QUESTION']);

            $strevalquestion=str_replace("&#039;", "'", $strevalquestion);
            $strevalquestion=str_replace("&amp;", "&", $strevalquestion);
            $strevalquestionencoded=urlencode($strevalquestion);
            $strlink="index.php?search=".$strevalquestionencoded;
            $strevalquestion=str_replace("\n", "<br />", $strevalquestion);
            $strevalquestion=str_replace("\r", " ", $strevalquestion);
            echo "<li><a href=\"".$strlink."\" alt=\"".$strevalquestion."\" title=\"".$strevalquestion."\">".$strevalquestion."</a></li>";
            $lngdisplayed++;
        }
        if ($inthasitems) {
            echo "</ul>";
        }
        if ($intimagesopen) {
            echo "</div>";
        }
        f_sqlfreeresult($result);
    }
    return $lngdisplayed;
}
?>
