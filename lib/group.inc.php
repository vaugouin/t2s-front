<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
include_once(__DIR__."/work-properties-format.inc.php");

if (!function_exists('f_grouptableexists')) {
	function f_grouptableexists($strtablename) {
		//Purpose: Tells whether a table is deployed, so that the sheet survives a partial deployment
		//         (front pulled before the DDL of these tables reached the database)
		//Returns: true when the table exists in the current schema
		$strsql="SELECT COUNT(*) FROM information_schema.TABLES ";
		$strsql.="WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ".f_stringtosql($strtablename);
		return f_stringtolong(f_fieldfromquery($strsql))>0;
	}
}

if (!function_exists('f_groupworkprocessstate')) {
	function f_groupworkprocessstate($strtablemovie, $strtableserie, $intmovieavailable, $intserieavailable) {
		//Purpose: Reads the state of process 74 (T2S_GROUP_WORK) from its server variables. Its
		//         EntityTelemetry may publish under "groupwork" or "group_work", so both are read.
		//         A table that holds rows for some group proves a past run even without telemetry.
		//Returns: array("start" => last start datetime, "end" => last end datetime, "anyrows" => bool)
		global $strsqlns;
		$arrstate=array("start"=>"", "end"=>"", "anyrows"=>false);
		$arrvarnames=array();
		foreach (array("groupwork", "group_work") as $strentity) {
			$arrvarnames[]=f_stringtosql("strtmdbmoviepreprocess".$strentity."startdatetime");
			$arrvarnames[]=f_stringtosql("strtmdbmoviepreprocess".$strentity."enddatetime");
		}
		$strsql="SELECT VAR_NAME, VAR_VALUE FROM ".$strsqlns."SERVER_VARIABLE ";
		$strsql.="WHERE VAR_NAME IN (".implode(",", $arrvarnames).") AND COALESCE(DELETED,0) = 0";
		$result=f_sqlselect($strsql);
		if ($result) {
			while ($rstemp=f_sqlresultfetcharray($result)) {
				$strvalue=trim((string)($rstemp['VAR_VALUE'] ?? ''));
				$strkey=(substr((string)$rstemp['VAR_NAME'], -strlen("startdatetime"))==="startdatetime") ? "start" : "end";
				if ($strvalue>$arrstate[$strkey]) {
					$arrstate[$strkey]=$strvalue;
				}
			}
			f_sqlfreeresult($result);
		}
		foreach (array(array($strtablemovie, $intmovieavailable), array($strtableserie, $intserieavailable)) as $arrtable) {
			if ($arrtable[1] && !$arrstate["anyrows"]) {
				$arrstate["anyrows"]=(f_fieldfromquery("SELECT 1 FROM ".$arrtable[0]." LIMIT 1")!="");
			}
		}
		return $arrstate;
	}
}

if (!function_exists('f_groupworkcreditedmembers')) {
	function f_groupworkcreditedmembers($lnggroupid, $strworktype, $arrworkids) {
		//Purpose: Names of the members of the group credited on each work of the current page, read
		//         from the person credits rather than from process 74, so the sheet shows who is
		//         behind MEMBER_COUNT. One query per list, restricted to the page's works.
		//Returns: array(ID_MOVIE or ID_SERIE => HTML of the linked names, comma separated)
		global $strsqlns;
		$arrmembers=array();
		if (count($arrworkids)===0) {
			return $arrmembers;
		}
		$strcol=($strworktype==="serie" ? "ID_SERIE" : "ID_MOVIE");
		$strcredittable=($strworktype==="serie" ? "T_WC_T2S_PERSON_SERIE" : "T_WC_T2S_PERSON_MOVIE");
		$arrids=array();
		foreach ($arrworkids as $lngworkid) {
			$arrids[]=f_longtosql($lngworkid);
		}
		$strsql="SELECT PC.".$strcol." AS ID_WORK, P.ID_PERSON, P.NAME AS PERSON_NAME, MIN(PG.DISPLAY_ORDER) AS MIN_DISPLAY_ORDER ";
		$strsql.="FROM T_WC_T2S_PERSON_GROUP PG ";
		$strsql.="JOIN ".$strcredittable." PC ON PC.ID_PERSON = PG.ID_PERSON AND COALESCE(PC.DELETED,0) = 0 ";
		$strsql.="JOIN ".$strsqlns."TMDB_PERSON P ON P.ID_PERSON = PG.ID_PERSON ";
		$strsql.="WHERE PG.ID_GROUP = ".f_longtosql($lnggroupid)." AND COALESCE(PG.DELETED,0) = 0 ";
		$strsql.="AND PC.".$strcol." IN (".implode(",", $arrids).") ";
		$strsql.="GROUP BY PC.".$strcol.", P.ID_PERSON, P.NAME ";
		$strsql.="ORDER BY PC.".$strcol.", MIN_DISPLAY_ORDER, P.NAME";
		$result=f_sqlselect($strsql);
		if ($result) {
			while ($rstemp=f_sqlresultfetcharray($result)) {
				$lngworkid=f_stringtolong($rstemp['ID_WORK']);
				$strlink="<a href=\"person.php?personid=".f_stringtolong($rstemp['ID_PERSON'])."\">".f_htmlspecialchars((string)$rstemp['PERSON_NAME'])."</a>";
				$arrmembers[$lngworkid]=(isset($arrmembers[$lngworkid]) ? $arrmembers[$lngworkid].", " : "").$strlink;
			}
			f_sqlfreeresult($result);
		}
		return $arrmembers;
	}
}

if (!function_exists('f_groupworkcaption')) {
	function f_groupworkcaption($lngmembercount, $lngpersoncount, $intgroupcredited, $strmembershtml) {
		//Purpose: The small caption under a work of the group: "5 members out of 6", the marker of a
		//         work on which the group itself is credited, then the names of the credited members.
		//         It travels in the overview argument of f_movielistdisplay / f_serielistdisplay,
		//         which echo it as HTML: every piece is escaped here.
		//Returns: The HTML of the caption
		$strcaption=f_htmlspecialchars(sprintf(get_movie_string($lngmembercount==1 ? 'lib_groupwork_member_of' : 'lib_groupwork_members_of'), $lngmembercount, $lngpersoncount));
		if ($intgroupcredited) {
			$strcaption.=" · <strong title=\"".f_htmlattr(get_movie_string('lib_groupwork_group_credited'))."\">&#9733; ".f_htmlspecialchars(get_movie_string('lib_groupwork_group_credited'))."</strong>";
		}
		if ($strmembershtml!="") {
			$strcaption.="<br /><small>".f_htmlspecialchars(get_movie_string('lib_groupwork_credited_members')).": ".$strmembershtml."</small>";
		}
		return $strcaption;
	}
}

if (!function_exists('f_groupworkpaginationhtml')) {
	function f_groupworkpaginationhtml($lnggroupid, $strworktype, $arrpageinfo, $lngmoviepage, $lngseriepage) {
		//Purpose: Previous / next links of one of the two lists of works (same shape as the source-work
		//         sheet: each link carries the other list's current page)
		//Returns: The HTML of the navigation, or "" when the list has a single page
		$lngtotalpages=max(1, (int)($arrpageinfo["total_pages"] ?? 1));
		if ($lngtotalpages<=1) {
			return "";
		}
		$lngpage=max(1, (int)($arrpageinfo["page"] ?? 1));
		$stranchor=($strworktype==="serie" ? "group-series" : "group-movies");
		$strhtml="<nav class=\"work-property-pagination\">";
		$strhtml.="<p>".f_htmlspecialchars(sprintf(get_movie_string('lib_provider_pagination_summary'),
			(int)$arrpageinfo["first_row"], (int)$arrpageinfo["last_row"], (int)$arrpageinfo["total_rows"], $lngpage, $lngtotalpages))."</p><p>";
		foreach (array(-1, 1) as $lngstep) {
			$lngtarget=$lngpage+$lngstep;
			if ($lngtarget<1 || $lngtarget>$lngtotalpages) {
				continue;
			}
			$strurl="group.php?groupid=".(int)$lnggroupid;
			$strurl.="&movie_page=".($strworktype==="movie" ? $lngtarget : (int)$lngmoviepage);
			$strurl.="&serie_page=".($strworktype==="serie" ? $lngtarget : (int)$lngseriepage);
			$strurl.="#".$stranchor;
			if ($lngstep>0 && $lngpage>1) {
				$strhtml.=" · ";
			}
			$strhtml.="<a rel=\"".($lngstep<0 ? "prev" : "next")."\" href=\"".f_htmlattr($strurl)."\">".f_htmlspecialchars(get_movie_string($lngstep<0 ? 'lib_provider_pagination_previous' : 'lib_provider_pagination_next'))."</a>";
		}
		$strhtml.="</p></nav>";
		return $strhtml;
	}
}

if ($intdbavailable) {
	$lnggroupid=f_stringtolong(f_request2("groupid"));
	$intfound=false;

	if ($lnggroupid>0) {
		$strsql="SELECT * FROM T_WC_T2S_GROUP WHERE ID_GROUP = ".f_longtosql($lnggroupid);
		$result=f_sqlselect($strsql);
		if ($result) {
			if ($rstemp=f_sqlresultfetcharray($result)) {
				$intfound=true;
				$strgroupname=isset($rstemp['GROUP_NAME']) ? $rstemp['GROUP_NAME'] : '';
				$strgroupsource=isset($rstemp['GROUP_SOURCE']) ? $rstemp['GROUP_SOURCE'] : '';
				$strgrouptype=isset($rstemp['GROUP_TYPE']) ? $rstemp['GROUP_TYPE'] : '';
				$strgroupwikidataid=isset($rstemp['ID_WIKIDATA']) ? trim($rstemp['ID_WIKIDATA']) : '';
				$strgroupprofilepath=isset($rstemp['PROFILE_PATH']) ? $rstemp['PROFILE_PATH'] : '';
				$strgroupwikipediapath=isset($rstemp['WIKIPEDIA_IMAGE_PATH']) ? $rstemp['WIKIPEDIA_IMAGE_PATH'] : '';
				$strgroupoverview=isset($rstemp['OVERVIEW']) ? $rstemp['OVERVIEW'] : '';
				$lnggrouppersoncount=f_stringtolong($rstemp['PERSON_COUNT'] ?? 0);
				$strgroupdatcreat=trim((string)($rstemp['DAT_CREAT'] ?? ''));
			}
			f_sqlfreeresult($result);
		}

		if ($intfound) {
			f_headerdisplay($strgroupname,"",f_socialwikidataentity($strgroupname,$strgroupoverview,$strgroupwikipediapath,$strgroupprofilepath));
			echo "<h1>".f_htmlspecialchars($strgroupname)."</h1>";

			if ($strgroupwikipediapath!="") {
				echo "<p><img src=\"".f_htmlattr($strgroupwikipediapath)."\" alt=\"".f_htmlattr($strgroupname)."\" title=\"".f_htmlattr($strgroupname)."\" style=\"background-color: white; max-width: 100%; height: auto;\" /></p>";
			}
			if ($strgroupprofilepath!="") {
				echo "<p><img src=\"".f_htmlattr(f_getpersonprofilepicture($strgroupprofilepath))."\" alt=\"".f_htmlattr($strgroupname)."\" title=\"".f_htmlattr($strgroupname)."\" style=\"background-color: white;\" /></p>";
			}

			if ($strgroupwikidataid!="") {
				echo "<p><strong>".get_movie_string('lib_movie_id_wikidata').":</strong> <a href=\"".f_htmlattr("https://www.wikidata.org/wiki/".$strgroupwikidataid)."\" target=\"_blank\" rel=\"noreferrer\">".f_htmlspecialchars($strgroupwikidataid)."</a> <a href=\"".f_htmlattr("https://www.wikidata.org/wiki/".$strgroupwikidataid)."\" target=\"_blank\" rel=\"noreferrer\"><img src=\"/images/Wikidata-logo.svg.png\" width=\"28\" height=\"16\" alt=\"Wikidata\" title=\"Wikidata\"></a></p>";
			}

			if ($strgroupsource!="") {
				echo "<p><strong>Source:</strong> ".f_htmlspecialchars($strgroupsource)."</p>";
			}
			if ($strgrouptype!="") {
				echo "<p><strong>Type:</strong> ".f_htmlspecialchars($strgrouptype)."</p>";
			}
			if ($strgroupoverview!="") {
				echo "<h2>".get_movie_string('lib_collection_overview')."</h2>";
				echo "<p>".nl2br(f_htmlspecialchars($strgroupoverview))."</p>";
			}
			if ($strgroupwikidataid!="") {
				// Vingt lignes de lecture V1 et un repli de langue
				// remplaces par un appel. ALIASES etait de la donnee morte, elle disparait.
				$strwditeminstanceofid=f_wikidataiteminstanceof_v2($strgroupwikidataid);
				// Le libelle de la classe se lit en V2, repli V1.
				// f_getwikidatalabel fait le meme repli de langue que les deux requetes
				// qu'elle remplace, mais dans V2 d'abord et V1 seulement en dernier ressort.
				$strwditeminstanceoflabel=f_getwikidatalabel($strwditeminstanceofid,$struilang,$arrwdsourcelabel);
				// Diagnostic de la migration, sous $intprivate comme les autres informations de
				// service : visible sous un prefixe prive, invisible sur le site public. Les DEUX langues,
				// pas seulement celle de l interface, car un prefixe prive peut forcer l anglais alors que le francais
				// est justement ce qu on veut valider.
				//Hook entity.wikidata: service blocks of the plugins above the Wikidata section
				echo f_pluginhtml("entity.wikidata",array("page"=>"group","wikidataids"=>array($strgroupwikidataid,$strwditeminstanceofid),"lang"=>$struilang,"langalt"=>$struilangalt));
				echo "<h2>".get_movie_string('lib_wikidata_section')."</h2>";
				$strpropertyurl="property.php?propertyid=P31";
				echo "<p><strong><a href=\"".$strpropertyurl."\">".get_movie_string('lib_wikidata_type')."</a>:</strong> ".$strwditeminstanceoflabel."</p>";
				$strwikidataeverything=f_wikidataallpropertiesv2($struilang,$strgroupwikidataid,", ");
				echo $strwikidataeverything;

				echo "<h2>".get_movie_string('lib_wikipedia_section')."</h2>";
				$strwikipediacontent=f_wikipediallsections($struilang,$strgroupwikidataid,$strsep=", ");
				echo $strwikipediacontent;
			}

			// Persons section with count, following list template style
			$lngpersoncount = 0;
			$strsqlpersonscount = "SELECT COUNT(DISTINCT J.ID_PERSON) AS COMPTE FROM T_WC_T2S_PERSON_GROUP J WHERE J.ID_GROUP = ".f_longtosql($lnggroupid);
			$resultpersonscount = f_sqlselect($strsqlpersonscount);
			if ($resultpersonscount) {
				if ($rtempcnt = f_sqlresultfetcharray($resultpersonscount)) {
					$lngpersoncount = f_stringtolong($rtempcnt['COMPTE']);
				}
				f_sqlfreeresult($resultpersonscount);
			}
			echo "<h2>".get_movie_string('lib_persons_section').": ".$lngpersoncount." ".get_movie_string('lib_person_item')."</h2>";
			echo "<table>";
			// Select unique persons for the group with stable ordering by minimal display_order
			$strsqlpersons = "SELECT P.ID_PERSON, P.NAME AS PERSON_NAME, P.BIOGRAPHY, P.BIRTHDAY, P.DEATHDAY, P.ID_IMDB, P.PROFILE_PATH, JP.MIN_DISPLAY_ORDER ";
			$strsqlpersons .= "FROM ".$strsqlns."TMDB_PERSON P ";
			$strsqlpersons .= "JOIN (SELECT ID_PERSON, MIN(DISPLAY_ORDER) AS MIN_DISPLAY_ORDER FROM T_WC_T2S_PERSON_GROUP WHERE ID_GROUP = ".f_longtosql($lnggroupid)." GROUP BY ID_PERSON) JP ";
			$strsqlpersons .= "ON P.ID_PERSON = JP.ID_PERSON ";
			$strsqlpersons .= "ORDER BY JP.MIN_DISPLAY_ORDER, P.NAME";
			$resultpersons=f_sqlselect($strsqlpersons);
			if ($resultpersons) {
				while ($rtempperson=f_sqlresultfetcharray($resultpersons)) {
					f_personlistdisplay($rtempperson['ID_PERSON'],$rtempperson['PERSON_NAME'],$rtempperson['BIOGRAPHY'],$rtempperson['BIRTHDAY'],$rtempperson['DEATHDAY'],$rtempperson['ID_IMDB'],$rtempperson['PROFILE_PATH'],3);
				}
				f_sqlfreeresult($resultpersons);
			}
			echo "</table>";

			// Les oeuvres du groupe, calculees par le process 74 de tmdb-movie-preprocess
			// (T2S_GROUP_WORK) dans T_WC_T2S_GROUP_MOVIE et T_WC_T2S_GROUP_SERIE.
			// Seules les oeuvres rattachees sont stockees : une liste vide ne dit donc pas a elle seule si
			// le calcul a eu lieu, d'ou la lecture de la telemetrie du process. Les tables peuvent manquer
			// (front tire avant le DDL) : la fiche se contente alors d'une note en mode prive.
			if ($lnggrouppersoncount<=0) {
				$lnggrouppersoncount=$lngpersoncount;
			}
			$strgroupworkmovietable="T_WC_T2S_GROUP_MOVIE";
			$strgroupworkserietable="T_WC_T2S_GROUP_SERIE";
			$intgroupworkmovieavailable=f_grouptableexists($strgroupworkmovietable);
			$intgroupworkserieavailable=f_grouptableexists($strgroupworkserietable);
			if (!$intgroupworkmovieavailable && !$intgroupworkserieavailable) {
				//Hook entity.notice: service messages of the plugins
				echo f_pluginhtml("entity.notice",array("type"=>"group","id"=>$lnggroupid,"notice"=>"works-unavailable"));
			} else {
				$lnggroupworkrowsperpage=50;
				$lngmoviepage=max(1, f_stringtolong(f_request2("movie_page")));
				$lngseriepage=max(1, f_stringtolong(f_request2("serie_page")));

				$lngmoviecount=0;
				if ($intgroupworkmovieavailable) {
					$strsqlcount="SELECT COUNT(DISTINCT J.ID_MOVIE) AS COMPTE FROM ".$strgroupworkmovietable." J ";
					$strsqlcount.="JOIN T_WC_T2S_MOVIE M ON M.ID_MOVIE = J.ID_MOVIE ";
					$strsqlcount.="WHERE J.ID_GROUP = ".f_longtosql($lnggroupid)." AND COALESCE(J.DELETED,0) = 0";
					$lngmoviecount=f_stringtolong(f_fieldfromquery($strsqlcount));
				}
				$arrmoviepageinfo=f_workpropertypaginationinfo($lngmoviepage, $lnggroupworkrowsperpage, $lngmoviecount);
				$lngmoviepage=$arrmoviepageinfo["page"];

				$lngseriecount=0;
				if ($intgroupworkserieavailable) {
					$strsqlcount="SELECT COUNT(DISTINCT J.ID_SERIE) AS COMPTE FROM ".$strgroupworkserietable." J ";
					$strsqlcount.="JOIN T_WC_T2S_SERIE S ON S.ID_SERIE = J.ID_SERIE ";
					$strsqlcount.="WHERE J.ID_GROUP = ".f_longtosql($lnggroupid)." AND COALESCE(J.DELETED,0) = 0";
					$lngseriecount=f_stringtolong(f_fieldfromquery($strsqlcount));
				}
				$arrseriepageinfo=f_workpropertypaginationinfo($lngseriepage, $lnggroupworkrowsperpage, $lngseriecount);
				$lngseriepage=$arrseriepageinfo["page"];

				// Etat du calcul : "jamais calcule" (aucune trace du process 74, ou groupe cree apres
				// sa derniere fin), "en cours" (debut sans fin), "calcule, aucune oeuvre".
				$arrgroupworkstate=f_groupworkprocessstate($strgroupworkmovietable, $strgroupworkserietable, $intgroupworkmovieavailable, $intgroupworkserieavailable);
				$strgroupworkend=$arrgroupworkstate["end"];
				//Hook entity.notice: service messages of the plugins
				echo f_pluginhtml("entity.notice",array("type"=>"group","id"=>$lnggroupid,"notice"=>"works-computed","date"=>$strgroupworkend));
				if ($lngmoviecount==0 && $lngseriecount==0) {
					if ($strgroupworkend=="" && !$arrgroupworkstate["anyrows"]) {
						$strgroupworkmessage=($arrgroupworkstate["start"]!="" ? 'lib_groupwork_running' : 'lib_groupwork_never_computed');
					} elseif ($strgroupworkend!="" && $arrgroupworkstate["start"]>$strgroupworkend) {
						$strgroupworkmessage='lib_groupwork_running';
					} elseif ($strgroupworkend!="" && $strgroupdatcreat!="" && $strgroupdatcreat>substr($strgroupworkend,0,10)) {
						$strgroupworkmessage='lib_groupwork_never_computed';
					} else {
						$strgroupworkmessage='lib_groupwork_none';
					}
					echo "<p><em>".f_htmlspecialchars(get_movie_string($strgroupworkmessage))."</em></p>";
				} else {
					foreach (array("movie", "serie") as $strworktype) {
						$intismovie=($strworktype==="movie");
						if (!($intismovie ? $intgroupworkmovieavailable : $intgroupworkserieavailable)) {
							continue;
						}
						$lngworkcount=($intismovie ? $lngmoviecount : $lngseriecount);
						$arrpageinfo=($intismovie ? $arrmoviepageinfo : $arrseriepageinfo);
						echo "<h2 id=\"".($intismovie ? "group-movies" : "group-series")."\">".get_movie_string($intismovie ? 'lib_groupwork_movies' : 'lib_groupwork_series').": ".$lngworkcount."</h2>";
						if ($lngworkcount==0) {
							continue;
						}
						if ($intismovie) {
							$strsqlworks="SELECT M.ID_MOVIE AS ID_WORK, M.MOVIE_TITLE AS WORK_TITLE, M.MOVIE_TITLE_FR AS WORK_TITLE_FR, M.DAT_RELEASE AS DAT_START, '' AS DAT_END, ";
							$strsqlworks.="M.ID_IMDB, M.IMDB_RATING, M.IMDB_VOTES, M.POSTER_PATH, J.MEMBER_COUNT, J.IS_GROUP_CREDITED ";
							$strsqlworks.="FROM ".$strgroupworkmovietable." J JOIN T_WC_T2S_MOVIE M ON M.ID_MOVIE = J.ID_MOVIE ";
							$strsqlworks.="WHERE J.ID_GROUP = ".f_longtosql($lnggroupid)." AND COALESCE(J.DELETED,0) = 0 ";
							$strsqlworks.="ORDER BY J.DISPLAY_ORDER, J.MEMBER_COUNT DESC, M.DAT_RELEASE, M.ID_MOVIE ";
						} else {
							$strsqlworks="SELECT S.ID_SERIE AS ID_WORK, S.SERIE_TITLE AS WORK_TITLE, S.SERIE_TITLE_FR AS WORK_TITLE_FR, S.DAT_FIRST_AIR AS DAT_START, S.DAT_LAST_AIR AS DAT_END, ";
							$strsqlworks.="S.ID_IMDB, S.IMDB_RATING, S.IMDB_VOTES, S.POSTER_PATH, J.MEMBER_COUNT, J.IS_GROUP_CREDITED ";
							$strsqlworks.="FROM ".$strgroupworkserietable." J JOIN T_WC_T2S_SERIE S ON S.ID_SERIE = J.ID_SERIE ";
							$strsqlworks.="WHERE J.ID_GROUP = ".f_longtosql($lnggroupid)." AND COALESCE(J.DELETED,0) = 0 ";
							$strsqlworks.="ORDER BY J.DISPLAY_ORDER, J.MEMBER_COUNT DESC, S.DAT_FIRST_AIR, S.ID_SERIE ";
						}
						$strsqlworks.="LIMIT ".(int)$arrpageinfo["rows_per_page"]." OFFSET ".(int)$arrpageinfo["offset"];
						$arrworkrows=array();
						$arrworkids=array();
						$resultworks=f_sqlselect($strsqlworks);
						if ($resultworks) {
							while ($rstempwork=f_sqlresultfetcharray($resultworks)) {
								$arrworkrows[]=$rstempwork;
								$arrworkids[]=f_stringtolong($rstempwork['ID_WORK']);
							}
							f_sqlfreeresult($resultworks);
						}
						$arrcreditedmembers=f_groupworkcreditedmembers($lnggroupid, $strworktype, $arrworkids);
						echo "<table>";
						foreach ($arrworkrows as $rstempwork) {
							$lngworkid=f_stringtolong($rstempwork['ID_WORK']);
							// Le titre suit la langue de l'interface, comme sur la fiche d'une oeuvre source
							$strworktitle=trim((string)($rstempwork['WORK_TITLE'] ?? ''));
							$strworktitlefr=trim((string)($rstempwork['WORK_TITLE_FR'] ?? ''));
							if ($struilang!="en" && $strworktitlefr!="") {
								$strworktitle=$strworktitlefr;
							}
							$strcaption=f_groupworkcaption(f_stringtolong($rstempwork['MEMBER_COUNT'] ?? 0), $lnggrouppersoncount, f_stringtolong($rstempwork['IS_GROUP_CREDITED'] ?? 0)==1, $arrcreditedmembers[$lngworkid] ?? "");
							if ($intismovie) {
								f_movielistdisplay($lngworkid,$strworktitle,$strworktitle,"",$rstempwork['DAT_START'],$rstempwork['ID_IMDB'],$rstempwork['IMDB_RATING'],$strcaption,$rstempwork['POSTER_PATH'],"",3,0,"float",$rstempwork['IMDB_VOTES']);
							} else {
								f_serielistdisplay($lngworkid,$strworktitle,$strworktitle,"",$rstempwork['DAT_START'],$rstempwork['DAT_END'],$rstempwork['ID_IMDB'],$rstempwork['IMDB_RATING'],$strcaption,$rstempwork['POSTER_PATH'],"",3,0,"float",$rstempwork['IMDB_VOTES']);
							}
						}
						echo "</table>";
						echo f_groupworkpaginationhtml($lnggroupid, $strworktype, $arrpageinfo, $lngmoviepage, $lngseriepage);
					}
				}
			}

			f_footerdisplay();
		} else {
			f_headerdisplay("Group Not Found");
			echo "<h1>Group not found.</h1>";
		}
	} else {
		f_headerdisplay("Invalid ID");
		echo "<h1>Invalid group ID.</h1>";
	}
} else {
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".$strmessage."</h1>";
}
?>
