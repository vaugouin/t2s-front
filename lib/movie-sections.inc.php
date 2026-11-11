<?php
// movie-sections.inc.php
// Helpers de presentation pour la fiche film reorganisee en accordeons (2026-10-26).
// Rendu HTML uniquement, aucun acces base, aucun echo : sur de l'include_once et testable a part.
//
// Modele d'emploi dans movie.inc.php :
//   f_moviesectionreset(array("info","overview","cast","crew")); ob_start();   // ordre facultatif
//   echo f_moviesectionopen("info","\u{1FAAA}",get_movie_string('lib_movie_section_tmdb'),1);
//   ... contenu de la section ...
//   echo f_moviesectionopen("watch","\u{25B6}",get_movie_string('lib_watch_providers_section'));
//   ... etc ...
//   echo f_moviesectionclose();          // ferme la derniere section ouverte
//   $strbody=ob_get_clean();
//   echo f_movieanchorbar(); echo $strbody;   // la barre d'ancres se construit des sections vues
//
// Chaque f_moviesectionopen() ferme la section precedente si elle est ouverte, puis ouvre la
// nouvelle : les gardes conditionnelles (sections privees absentes) sont ainsi gerees sans etat
// a tenir cote appelant.

// $arrorder (facultatif) : ids des sections a afficher en tete, dans cet ordre ; les autres suivent
// dans leur ordre d'ouverture. Chaque section est alors bufferisee a part (ob_start propre) et
// f_moviesectionclose() rend le tout reordonne : l'ordre d'affichage ne depend plus de l'ordre du code.
function f_moviesectionreset($arrorder=null) {
	$GLOBALS['arrmoviesections']=array();
	$GLOBALS['intmoviesectionopen']=0;
	$GLOBALS['arrmoviesectionorder']=(is_array($arrorder)?$arrorder:null);
	$GLOBALS['arrmoviesectionchunks']=array();
	$GLOBALS['strmoviesectioncurrent']="";
}

// Mode reordonne : range la section en cours (son buffer, ferme) dans les morceaux.
function f_moviesectionstorecurrent() {
	$strid=$GLOBALS['strmoviesectioncurrent'];
	if (!isset($GLOBALS['arrmoviesectionchunks'][$strid])) {
		$GLOBALS['arrmoviesectionchunks'][$strid]="";
	}
	$GLOBALS['arrmoviesectionchunks'][$strid].=ob_get_clean()."</div></details>";
	$GLOBALS['intmoviesectionopen']=0;
}

function f_moviesectionopen($strid,$stricon,$strlabelhtml,$intopen=0) {
	if (!isset($GLOBALS['arrmoviesections'])) {
		f_moviesectionreset();
	}
	$strhtml="";
	$intreorder=!empty($GLOBALS['arrmoviesectionorder']);
	if (!empty($GLOBALS['intmoviesectionopen'])) {
		if ($intreorder) {
			f_moviesectionstorecurrent();
		} else {
			$strhtml.="</div></details>";
		}
	}
	if ($intreorder) {
		// Le HTML rendu par cette fonction est echo dans ce nouveau buffer, propre a la section.
		ob_start();
		$GLOBALS['strmoviesectioncurrent']=$strid;
	}
	$GLOBALS['intmoviesectionopen']=1;
	$GLOBALS['arrmoviesections'][]=array("id"=>$strid,"icon"=>$stricon,"label"=>$strlabelhtml);
	$strhtml.="<details class=\"fiche-section\" id=\"".$strid."\"";
	if ($intopen) {
		$strhtml.=" open";
	}
	$strhtml.="><summary class=\"fiche-section-summary\">";
	$strhtml.="<span class=\"fiche-section-icon\">".$stricon."</span>";
	$strhtml.="<span class=\"fiche-section-title\">".$strlabelhtml."</span>";
	$strhtml.="</summary><div class=\"fiche-section-body\">";
	return $strhtml;
}

function f_moviesectionclose() {
	if (empty($GLOBALS['arrmoviesectionorder'])) {
		if (!empty($GLOBALS['intmoviesectionopen'])) {
			$GLOBALS['intmoviesectionopen']=0;
			return "</div></details>";
		}
		return "";
	}
	if (!empty($GLOBALS['intmoviesectionopen'])) {
		f_moviesectionstorecurrent();
	}
	$arrchunks=$GLOBALS['arrmoviesectionchunks'];
	$arrids=array();
	foreach ($GLOBALS['arrmoviesectionorder'] as $strid) {
		if (isset($arrchunks[$strid])) {
			$arrids[]=$strid;
		}
	}
	foreach (array_keys($arrchunks) as $strid) {
		if (!in_array($strid,$arrids,true)) {
			$arrids[]=$strid;
		}
	}
	$strhtml="";
	foreach ($arrids as $strid) {
		$strhtml.=$arrchunks[$strid];
	}
	// La barre d'ancres suit le meme ordre.
	$arrsections=array();
	foreach ($arrids as $strid) {
		foreach ($GLOBALS['arrmoviesections'] as $arrsection) {
			if ($arrsection['id']===$strid) {
				$arrsections[]=$arrsection;
				break;
			}
		}
	}
	$GLOBALS['arrmoviesections']=$arrsections;
	$GLOBALS['arrmoviesectionchunks']=array();
	$GLOBALS['arrmoviesectionorder']=null;
	return $strhtml;
}

// Enveloppe dans un accordeon autonome le HTML deja produit par une fonction de section
// (open-credits, voisins TMDb, oeuvres adaptees) sans toucher a ces fonctions, partagees avec
// les fiches series. Deux modes :
//   - $strlabel === null : mode "remonter le titre", le premier <h2> du HTML devient le libelle
//     du summary et est retire du corps (sections a titre unique : open-credits, similar, reco) ;
//   - $strlabel fourni : libelle impose, les <h2> internes restent comme sous-titres (sections a
//     titres multiples : oeuvres adaptees films+series, nav collection precedent+suivant).
// Une sortie vide (la fonction n'a rien emis) ne cree aucun accordeon.
function f_moviesectionwrap($strid,$stricon,$strhtml,$strlabel=null,$intopen=0) {
	$strhtml=(string)$strhtml;
	if (trim(strip_tags($strhtml))==="") {
		return $strhtml;
	}
	if ($strlabel===null) {
		if (preg_match('/<h2\b[^>]*>(.*?)<\/h2>/is',$strhtml,$arrmatch)) {
			$strlabel=trim(strip_tags($arrmatch[1]));
			// Retire l'ancre <a name=...></a> qui precede eventuellement ce premier <h2>, puis le <h2>.
			$strhtml=preg_replace('/<a\s+name="[^"]*">\s*<\/a>\s*(?=<h2\b)/i','',$strhtml,1);
			$strhtml=preg_replace('/<h2\b[^>]*>.*?<\/h2>/is','',$strhtml,1);
		} else {
			$strlabel="";
		}
	}
	if (!isset($GLOBALS['arrmoviesections'])) {
		f_moviesectionreset();
	}
	$GLOBALS['arrmoviesections'][]=array("id"=>$strid,"icon"=>$stricon,"label"=>$strlabel);
	$strout="<details class=\"fiche-section\" id=\"".$strid."\"";
	if ($intopen) {
		$strout.=" open";
	}
	$strout.="><summary class=\"fiche-section-summary\">";
	$strout.="<span class=\"fiche-section-icon\">".$stricon."</span>";
	$strout.="<span class=\"fiche-section-title\">".$strlabel."</span>";
	$strout.="</summary><div class=\"fiche-section-body\">".$strhtml."</div></details>";
	return $strout;
}

function f_movieanchorbar() {
	if (empty($GLOBALS['arrmoviesections'])) {
		return "";
	}
	$strhtml="<nav class=\"fiche-anchorbar\" aria-label=\"Sections\">";
	foreach ($GLOBALS['arrmoviesections'] as $arrsection) {
		// Le libelle peut porter du HTML (pastilles ajoutees par un plugin) : la barre n'en garde que le texte.
		$strlabel=trim(strip_tags((string)$arrsection['label']));
		$strhtml.="<a class=\"fiche-anchorlink\" href=\"#".$arrsection['id']."\" title=\"".f_htmlattr($strlabel)."\">";
		$strhtml.="<span class=\"fiche-anchorlink-icon\">".$arrsection['icon']."</span>";
		$strhtml.="<span class=\"fiche-anchorlink-label\">".f_htmlspecialchars($strlabel)."</span>";
		$strhtml.="</a>";
	}
	$strhtml.="</nav>";
	return $strhtml;
}
?>
