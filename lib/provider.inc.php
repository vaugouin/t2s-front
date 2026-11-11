<?php
ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");
include_once(__DIR__."/work-properties-format.inc.php");
include_once(__DIR__."/work-properties-sql.inc.php");
include_once(__DIR__."/watch-country.inc.php");

function f_providerrawtableexists($strtablename) {
	$strsql="SELECT COUNT(*) FROM information_schema.TABLES ";
	$strsql.="WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ".f_stringtosql($strtablename);
	return f_stringtolong(f_fieldfromquery($strsql))>0;
}

function f_providerqueryrows($strsql) {
	$arrrows=array();
	if ($strsql==="") {
		return $arrrows;
	}
	$result=f_sqlselect($strsql);
	if ($result) {
		while ($arrrow=f_sqlresultfetcharray($result)) {
			$arrrows[]=$arrrow;
		}
		f_sqlfreeresult($result);
	}
	return $arrrows;
}

if ($intdbavailable) {
	$lngproviderid=f_stringtolong(f_request2("providerid"));
	if ($lngproviderid>0) {
		$arrproviderrequiredtables=array(
			$strsqlns."TMDB_WATCH_PROVIDER",
			$strsqlns."TMDB_WATCH_PROVIDER_CATALOG",
			$strsqlns."TMDB_WATCH_PROVIDER_CATALOG_STATE",
			$strsqlns."TMDB_WATCH_PROVIDER_REGION"
		);
		$intproviderglobalsavailable=true;
		foreach ($arrproviderrequiredtables as $strproviderrequiredtable) {
			if (!f_providerrawtableexists($strproviderrequiredtable)) {
				$intproviderglobalsavailable=false;
				break;
			}
		}
		if (!$intproviderglobalsavailable) {
			$strmessage=get_movie_string('lib_provider_data_unavailable');
			f_headerdisplay($strmessage);
			echo "<h1>".f_workpropertyescape($strmessage)."</h1>";
		} else {
			$arrproviderrows=f_providerqueryrows(
				f_workpropertyprovideridentitysql($strsqlns, $lngproviderid));
			if (count($arrproviderrows)===0) {
				$strmessage=get_movie_string('lib_provider_not_found');
				f_headerdisplay($strmessage);
				echo "<h1>".f_workpropertyescape($strmessage)."</h1>";
			} else {
				$arrprovider=$arrproviderrows[0];
				$strprovidername=trim((string)($arrprovider['PROVIDER_NAME'] ?? ""));
				if ($strprovidername==="") {
					$strprovidername=get_movie_string('lib_watch_provider')." #".$lngproviderid;
				}
				$strproviderlogopath=trim((string)($arrprovider['LOGO_PATH'] ?? ""));
				$strproviderupdated=trim((string)($arrprovider['TIM_PROVIDER_UPDATED'] ?? ""));
				$intproviderinactive=f_stringtolong($arrprovider['DELETED'] ?? 0)>0;
				$strwatchcountry=f_watchcountryresolve();
				$arrwatchcountryoptions=f_watchcountryoptions($struilang);

				$arrprovidercatalogs=array();
				$arrprovidercatalogs=f_providerqueryrows(
					f_workpropertyprovidercatalogsql($strsqlns, $lngproviderid));
				$arrproviderregions=f_providerqueryrows(
					f_workpropertyproviderregionssql($strsqlns, $lngproviderid));

				$arrprovidermovies=array();
				$arrproviderseries=array();
				$lngproviderrowsperpage=50;
				$lngmoviepage=max(1, f_stringtolong(f_request2("movie_page")));
				$lngseriepage=max(1, f_stringtolong(f_request2("serie_page")));
				$arrmoviepageinfo=f_workpropertypaginationinfo($lngmoviepage, $lngproviderrowsperpage, 0);
				$arrseriepageinfo=f_workpropertypaginationinfo($lngseriepage, $lngproviderrowsperpage, 0);
				if ($strwatchcountry!=="") {
					if (f_providerrawtableexists($strsqlns."TMDB_MOVIE_WATCH_PROVIDER")) {
						$lngprovidermovietotal=f_stringtolong(f_fieldfromquery(
							f_workpropertyproviderworkscountsql(
								$strsqlns, "movie", $lngproviderid, $strwatchcountry)));
						$arrmoviepageinfo=f_workpropertypaginationinfo(
							$lngmoviepage, $lngproviderrowsperpage, $lngprovidermovietotal);
						$lngmoviepage=$arrmoviepageinfo["page"];
						$arrprovidermovies=f_providerqueryrows(f_workpropertyproviderworkssql(
							$strsqlns, "movie", $lngproviderid, $strwatchcountry, $struilang,
							$arrmoviepageinfo["rows_per_page"], $arrmoviepageinfo["offset"]));
					}
					if (f_providerrawtableexists($strsqlns."TMDB_SERIE_WATCH_PROVIDER")) {
						$lngproviderserietotal=f_stringtolong(f_fieldfromquery(
							f_workpropertyproviderworkscountsql(
								$strsqlns, "serie", $lngproviderid, $strwatchcountry)));
						$arrseriepageinfo=f_workpropertypaginationinfo(
							$lngseriepage, $lngproviderrowsperpage, $lngproviderserietotal);
						$lngseriepage=$arrseriepageinfo["page"];
						$arrproviderseries=f_providerqueryrows(f_workpropertyproviderworkssql(
							$strsqlns, "serie", $lngproviderid, $strwatchcountry, $struilang,
							$arrseriepageinfo["rows_per_page"], $arrseriepageinfo["offset"]));
					}
				}

				$strprovidersyntheticimagepath=f_getsyntheticimagepath("provider", $lngproviderid);
				$arrsocialprovider=array();
				if ($strprovidersyntheticimagepath!=="") {
					$arrsocialprovider['image']=f_socialimageurl($strprovidersyntheticimagepath);
				} elseif ($strproviderlogopath!=="") {
					$arrsocialprovider['image']=f_socialimageurl($strtmdbimagespathbaseurl.$strproviderlogopath);
				}
				if (isset($arrsocialprovider['image'])) {
					$arrsocialprovider['imagealt']=$strprovidername;
				}
				f_headerdisplay($strprovidername, "", $arrsocialprovider);
				echo "<h1>".f_workpropertyescape(get_movie_string('lib_provider_name')).": ".
					f_workpropertyescape($strprovidername)."</h1>";

				if ($strprovidersyntheticimagepath!=="") {
					echo "<p><a href=\"".f_htmlattr($strprovidersyntheticimagepath).
						"\" target=\"_blank\" rel=\"noreferrer\"><img src=\"".
						f_htmlattr($strprovidersyntheticimagepath)."\" alt=\"".
						f_htmlattr($strprovidername)."\" width=\"300\"></a></p>";
				} elseif ($strproviderlogopath!=="") {
					$strprovideroriginalurl=$strtmdbimagesoriginalpathbaseurl.$strproviderlogopath;
					$strproviderdisplayurl=$strtmdbimagespathbaseurl.$strproviderlogopath;
					echo "<p><a href=\"".f_htmlattr($strprovideroriginalurl).
						"\" target=\"_blank\" rel=\"noreferrer\"><img src=\"".
						f_htmlattr($strproviderdisplayurl)."\" alt=\"".
						f_htmlattr($strprovidername)."\" style=\"background-color:white;\"></a></p>";
				}

				echo "<p><strong>".f_workpropertyescape(get_movie_string('lib_provider_id')).
					":</strong> ".$lngproviderid."</p>";
				if ($strproviderupdated!=="") {
					echo "<p><strong>".f_workpropertyescape(get_movie_string('lib_provider_record_updated')).
						":</strong> ".f_workpropertyescape($strproviderupdated)."</p>";
				}
				if ($intproviderinactive) {
					echo "<p><strong>".f_workpropertyescape(get_movie_string('lib_provider_inactive'))."</strong></p>";
				}

				echo "<h2>".f_workpropertyescape(get_movie_string('lib_provider_catalogues'))."</h2>";
				if (count($arrprovidercatalogs)===0) {
					echo "<p>".f_workpropertyescape(get_movie_string('lib_provider_catalogues_empty'))."</p>";
				} else {
					echo "<ul>";
					foreach ($arrprovidercatalogs as $arrcatalog) {
						$strcontenttype=trim((string)($arrcatalog['CONTENT_TYPE'] ?? ""));
						if ($strcontenttype==="movie") {
							$strcontentlabel=get_movie_string('lib_provider_catalog_movie');
						} elseif ($strcontenttype==="serie") {
							$strcontentlabel=get_movie_string('lib_provider_catalog_serie');
						} else {
							$strcontentlabel=$strcontenttype;
						}
						$strcatalogupdated=trim((string)($arrcatalog['TIM_COMPLETED'] ?? ""));
						if ($strcatalogupdated==="") {
							$strcatalogupdated=trim((string)($arrcatalog['TIM_PROVIDER_UPDATED'] ?? ""));
						}
						echo "<li>".f_workpropertyescape($strcontentlabel);
						if ($strcatalogupdated!=="") {
							echo " · ".f_workpropertyescape(get_movie_string('lib_provider_catalog_updated')).
								": ".f_workpropertyescape($strcatalogupdated);
						}
						echo "</li>";
					}
					echo "</ul>";
				}

				echo "<h2>".f_workpropertyescape(get_movie_string('lib_provider_regions'))."</h2>";
				if (count($arrproviderregions)===0) {
					echo "<p>".f_workpropertyescape(get_movie_string('lib_provider_regions_empty'))."</p>";
				} else {
					$arrregioncountrylabels=array();
					foreach ($arrproviderregions as $arrregion) {
						$strregioncountry=f_workpropertycountrycode($arrregion['COUNTRY_CODE'] ?? "");
						if ($strregioncountry!=="" && !isset($arrregioncountrylabels[$strregioncountry])) {
							$arrregioncountrylabels[$strregioncountry]=f_countrycodetranslatefrommap(
								$strregioncountry, $arrwatchcountryoptions, $struilang);
						}
					}
					echo f_workpropertyproviderregionshtml(
						$arrproviderregions,
						$arrregioncountrylabels,
						array(
							"movie"=>get_movie_string('lib_provider_catalog_movie'),
							"serie"=>get_movie_string('lib_provider_catalog_serie')
						),
						get_movie_string('lib_provider_priority')
					);
				}

				echo "<h2>".f_workpropertyescape(get_movie_string('lib_provider_available_works'))."</h2>";
				echo f_watchcountryselectorhtml(
					$strwatchcountry,
					$arrwatchcountryoptions,
					array("providerid"=>$lngproviderid),
					"provider-watch-country",
					get_movie_string('lib_watch_country'),
					get_movie_string('lib_watch_country_choose'),
					get_movie_string('lib_watch_country_apply')
				);
				if ($strwatchcountry==="") {
					echo "<p>".f_workpropertyescape(get_movie_string('lib_provider_choose_country'))."</p>";
				} else {
					$arrwatchmodelabels=array(
						"flatrate"=>get_movie_string('lib_watch_mode_flatrate'),
						"free"=>get_movie_string('lib_watch_mode_free'),
						"ads"=>get_movie_string('lib_watch_mode_ads'),
						"rent"=>get_movie_string('lib_watch_mode_rent'),
						"buy"=>get_movie_string('lib_watch_mode_buy')
					);
					$arrpaginationlabels=array(
						"summary"=>get_movie_string('lib_provider_pagination_summary'),
						"previous"=>get_movie_string('lib_provider_pagination_previous'),
						"next"=>get_movie_string('lib_provider_pagination_next')
					);
					echo "<h3 id=\"provider-movies\">".
						f_workpropertyescape(get_movie_string('lib_provider_catalog_movie')).
						" (".$arrmoviepageinfo["total_rows"].")</h3>";
					echo f_workpropertyproviderworkshtml(
						$arrprovidermovies, "movie", $arrwatchmodelabels,
						$strtmdbimagespathbaseurl, get_movie_string('lib_provider_no_movies'),
						$strwatchcountry);
					echo f_workpropertyproviderpaginationhtml(
						$lngproviderid, $strwatchcountry, "movie", $arrmoviepageinfo,
						$lngmoviepage, $lngseriepage,
						array_merge($arrpaginationlabels, array(
							"aria"=>get_movie_string('lib_provider_catalog_movie'))));
					echo "<h3 id=\"provider-series\">".
						f_workpropertyescape(get_movie_string('lib_provider_catalog_serie')).
						" (".$arrseriepageinfo["total_rows"].")</h3>";
					echo f_workpropertyproviderworkshtml(
						$arrproviderseries, "serie", $arrwatchmodelabels,
						$strtmdbimagespathbaseurl, get_movie_string('lib_provider_no_series'),
						$strwatchcountry);
					echo f_workpropertyproviderpaginationhtml(
						$lngproviderid, $strwatchcountry, "serie", $arrseriepageinfo,
						$lngmoviepage, $lngseriepage,
						array_merge($arrpaginationlabels, array(
							"aria"=>get_movie_string('lib_provider_catalog_serie'))));
				}
				echo "<p><small>".f_workpropertyescape(get_movie_string('lib_watch_providers_attribution')).
					"</small></p>";
				f_footerdisplay();
			}
		}
	} else {
		$strmessage=get_movie_string('lib_providerid_not_set');
		f_headerdisplay($strmessage);
		echo "<h1>".f_workpropertyescape($strmessage)."</h1>";
	}
} else {
	$strmessage=get_movie_string('lib_db_not_available');
	f_headerdisplay($strmessage);
	echo "<h1>".f_workpropertyescape($strmessage)."</h1>";
}

?>
