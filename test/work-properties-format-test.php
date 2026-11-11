<?php
/*
 * Characterization tests for the side-effect-free work-property helpers.
 * Run from the repository root: php test/work-properties-format-test.php
 */

require_once(__DIR__."/../lib/work-properties-format.inc.php");
require_once(__DIR__."/../lib/work-properties-sql.inc.php");
require_once(__DIR__."/../lib/watch-country.inc.php");

$lngchecks=0;
$lngfailures=0;

function f_wpcheck($strlabel, $strexpected, $stractual) {
	global $lngchecks, $lngfailures;
	$lngchecks++;
	if ($strexpected===$stractual) {
		echo "ok    ".$strlabel."\n";
		return;
	}
	$lngfailures++;
	echo "FAIL  ".$strlabel."\n";
	echo "      expected: ".var_export($strexpected, true)."\n";
	echo "      actual:   ".var_export($stractual, true)."\n";
}

f_wpcheck("country normalized", "FR", f_workpropertycountrycode(" fr "));
f_wpcheck("language is not a country", "", f_workpropertycountrycode("en-US"));
f_wpcheck("empty country stays empty", "", f_workpropertycountrycode(""));
f_wpcheck("null marker is unknown", "unknown", f_workpropertysnapshotstate("", 0));
f_wpcheck("completed marker with no row is empty", "empty", f_workpropertysnapshotstate("2026-08-20 12:00:00", 0));
f_wpcheck("completed marker with rows is data", "data", f_workpropertysnapshotstate("2026-08-20 12:00:00", 2));

$arrproviders=array(
	array("ID_PROVIDER"=>8, "MONETIZATION_TYPE"=>"flatrate", "PROVIDER_NAME"=>"Stream & Go", "LOGO_PATH"=>"/logo.png"),
	array("ID_PROVIDER"=>9, "MONETIZATION_TYPE"=>"rent", "PROVIDER_NAME"=>"", "LOGO_PATH"=>""),
	array("ID_PROVIDER"=>10, "MONETIZATION_TYPE"=>"unsupported", "PROVIDER_NAME"=>"Hidden")
);
$arrgroups=f_workpropertywatchprovidergroups($arrproviders);
f_wpcheck("flatrate kept", "8", (string)$arrgroups["flatrate"][0]["ID_PROVIDER"]);
f_wpcheck("rent kept", "9", (string)$arrgroups["rent"][0]["ID_PROVIDER"]);
f_wpcheck("unknown mode skipped", "2", (string)(count($arrgroups["flatrate"])+count($arrgroups["rent"])));

$strproviderhtml=f_workpropertywatchprovidershtml(
	$arrproviders,
	array("flatrate"=>"Subscription", "rent"=>"Rent"),
	"https://image.tmdb.org/t/p/w342/",
	"Provider",
	"FR"
);
f_wpcheck("provider name escaped", true, strpos($strproviderhtml, "Stream &amp; Go")!==false);
f_wpcheck("logo URL normalized", true, strpos($strproviderhtml, "https://image.tmdb.org/t/p/w342/logo.png")!==false);
f_wpcheck("missing name fallback", true, strpos($strproviderhtml, "Provider #9")!==false);
f_wpcheck("unknown mode not rendered", false, strpos($strproviderhtml, "Hidden")!==false);
f_wpcheck("provider card links to its own page", true, strpos($strproviderhtml, "provider.php?providerid=8&amp;watch_country=FR")!==false);
$strproviderwithoutid=f_workpropertywatchprovidershtml(
	array(array("ID_PROVIDER"=>0, "MONETIZATION_TYPE"=>"free", "PROVIDER_NAME"=>"No ID")),
	array("free"=>"Free"), "", "Provider", "FR");
f_wpcheck("provider without ID stays unlinked", false, strpos($strproviderwithoutid, "provider.php?")!==false);

$arrproviderworks=array(
	array(
		"ID_WORK"=>42,
		"WORK_TITLE"=>"Film <test>",
		"POSTER_PATH"=>"/poster.jpg",
		"WORK_DATE"=>"2024-03-01",
		"MONETIZATION_TYPES"=>"flatrate,rent"
	)
);
$strproviderworkshtml=f_workpropertyproviderworkshtml(
	$arrproviderworks, "movie", array("flatrate"=>"Subscription", "rent"=>"Rent"),
	"https://image.tmdb.org/t/p/w342/", "No movie", "FR");
f_wpcheck("provider work links to movie and keeps country", true,
	strpos($strproviderworkshtml, "movie.php?movieid=42&amp;watch_country=FR")!==false);
f_wpcheck("provider work title escaped", true, strpos($strproviderworkshtml, "Film &lt;test&gt;")!==false);
f_wpcheck("provider work keeps monetization modes", true, strpos($strproviderworkshtml, "Subscription · Rent")!==false);
f_wpcheck("provider work poster normalized", true, strpos($strproviderworkshtml, "https://image.tmdb.org/t/p/w342/poster.jpg")!==false);
$strproviderserieshtml=f_workpropertyproviderworkshtml(
	$arrproviderworks, "serie", array("flatrate"=>"Subscription", "rent"=>"Rent"),
	"https://image.tmdb.org/t/p/w342/", "No series", "fr");
f_wpcheck("provider work links to series and normalizes country", true,
	strpos($strproviderserieshtml, "serie.php?serieid=42&amp;watch_country=FR")!==false);

$arrpageinfo=f_workpropertypaginationinfo(99, 50, 120);
f_wpcheck("pagination clamps page to the last page", "3", (string)$arrpageinfo["page"]);
f_wpcheck("pagination computes final offset", "100", (string)$arrpageinfo["offset"]);
f_wpcheck("pagination computes final visible range", "101-120",
	$arrpageinfo["first_row"]."-".$arrpageinfo["last_row"]);
$arrfirstpageinfo=f_workpropertypaginationinfo(-4, 50, 120);
$strmoviepagination=f_workpropertyproviderpaginationhtml(
	2, "fr", "movie", $arrfirstpageinfo, 1, 3,
	array(
		"aria"=>"Movies",
		"summary"=>"Showing %1\$d-%2\$d of %3\$d · page %4\$d of %5\$d",
		"previous"=>"Previous",
		"next"=>"Next"
	)
);
f_wpcheck("first movie page has no previous link", false, strpos($strmoviepagination, 'rel="prev"')!==false);
f_wpcheck("movie next link preserves country and series page", true, strpos(
	$strmoviepagination,
	"provider.php?providerid=2&amp;watch_country=FR&amp;movie_page=2&amp;serie_page=3#provider-movies"
)!==false);
$strseriepagination=f_workpropertyproviderpaginationhtml(
	2, "FR", "serie", $arrpageinfo, 2, 3,
	array(
		"aria"=>"TV series",
		"summary"=>"Showing %1\$d-%2\$d of %3\$d · page %4\$d of %5\$d",
		"previous"=>"Previous",
		"next"=>"Next"
	)
);
f_wpcheck("last series page has no next link", false, strpos($strseriepagination, 'rel="next"')!==false);
f_wpcheck("series previous link preserves movie page", true, strpos(
	$strseriepagination,
	"provider.php?providerid=2&amp;watch_country=FR&amp;movie_page=2&amp;serie_page=2#provider-series"
)!==false);

$strproviderregionshtml=f_workpropertyproviderregionshtml(
	array(array("CONTENT_TYPE"=>"movie", "COUNTRY_CODE"=>"FR", "PROVIDER_DISPLAY_PRIORITY"=>3)),
	array("FR"=>"France <test>"), array("movie"=>"Movies"), "Priority");
f_wpcheck("provider region escaped", true, strpos($strproviderregionshtml, "France &lt;test&gt; (FR)")!==false);
f_wpcheck("provider priority rendered", true, strpos($strproviderregionshtml, "Priority: 3")!==false);

f_wpcheck("descriptors decoded", "4K|IMAX", implode("|", f_workpropertyreleasedescriptors('["4K","IMAX","4K"]')));
f_wpcheck("invalid descriptors ignored", "0", (string)count(f_workpropertyreleasedescriptors('{bad')));
f_wpcheck("normalized event date", "2026-08-20", f_workpropertyreleasedisplaydate(array("TIM_RELEASE"=>"2026-08-20 00:00:00")));
f_wpcheck("raw event date fallback", "1982-05-25", f_workpropertyreleasedisplaydate(array("RELEASE_DATE_RAW"=>"1982-05-25T00:00:00.000Z")));

$arrreleases=array(
	array("COUNTRY_CODE"=>"US", "RELEASE_TYPE"=>3, "TIM_RELEASE"=>"1982-06-25 00:00:00", "NOTE"=>"Wide <release>"),
	array("COUNTRY_CODE"=>"FR", "RELEASE_TYPE"=>2, "TIM_RELEASE"=>"1982-09-14 00:00:00", "CERTIFICATION"=>"12", "DESCRIPTORS_JSON"=>'["Festival"]')
);
$arrreleasegroups=f_workpropertyreleasecountrygroups($arrreleases, "FR");
f_wpcheck("preferred country first", "FR", (string)array_key_first($arrreleasegroups));

$strreleasehtml=f_workpropertyreleasedateshtml(
	$arrreleases,
	array("FR"=>"France", "US"=>"United States"),
	array(2=>"Limited theatrical", 3=>"Theatrical"),
	"FR",
	array(
		"preferred"=>"shown first",
		"unknown_type"=>"Type",
		"no_date"=>"No date",
		"certification"=>"Certification",
		"language"=>"Language",
		"note"=>"Note",
		"descriptors"=>"Descriptors"
	)
);
f_wpcheck("preferred section opens", true, strpos($strreleasehtml, '<details class="work-property-release-country" open><summary><strong>France (FR)</strong>')!==false);
f_wpcheck("preferred section is before other countries", true, strpos($strreleasehtml, "France (FR)")<strpos($strreleasehtml, "United States (US)"));
f_wpcheck("release note escaped", true, strpos($strreleasehtml, "Wide &lt;release&gt;")!==false);
f_wpcheck("descriptor rendered", true, strpos($strreleasehtml, "Descriptors: Festival")!==false);

$strmovieprovidersql=f_workpropertywatchproviderssql("T_WC_", "movie", 42, "fr");
f_wpcheck("movie provider SQL uses the movie association", true, strpos($strmovieprovidersql, "FROM T_WC_TMDB_MOVIE_WATCH_PROVIDER")!==false);
f_wpcheck("movie provider SQL scopes country", true, strpos($strmovieprovidersql, "COUNTRY_CODE = 'FR'")!==false);
f_wpcheck("movie provider SQL keeps rent separate", true, strpos($strmovieprovidersql, "WHEN 'rent' THEN 4")!==false);

$strserieprovidersql=f_workpropertywatchproviderssql("T_WC_", "serie", 84, "US");
f_wpcheck("serie provider SQL uses ID_SERIE", true, strpos($strserieprovidersql, "WHERE ID_SERIE = 84")!==false);
f_wpcheck("unsupported work type rejected", "", f_workpropertywatchproviderssql("T_WC_", "network", 84, "US"));

$strreleasesql=f_workpropertymoviereleasedatessql("T_WC_", 42, "FR");
f_wpcheck("release SQL reads additive events", true, strpos($strreleasesql, "FROM T_WC_TMDB_MOVIE_RELEASE_DATE")!==false);
f_wpcheck("release SQL prioritizes selected country", true, strpos($strreleasesql, "CASE WHEN COUNTRY_CODE = 'FR' THEN 0")!==false);
f_wpcheck("release SQL never touches canonical DAT_RELEASE", false, strpos($strreleasesql, "DAT_RELEASE")!==false);

$strprovideridentitysql=f_workpropertyprovideridentitysql("T_WC_", "17 OR 1=1");
f_wpcheck("provider identity uses the global dimension", true, strpos($strprovideridentitysql, "FROM T_WC_TMDB_WATCH_PROVIDER")!==false);
f_wpcheck("provider identity casts its ID", true, strpos($strprovideridentitysql, "ID_PROVIDER = 17 LIMIT 1")!==false);
f_wpcheck("provider identity keeps inactive records addressable", false, strpos($strprovideridentitysql, "DELETED = 0")!==false);

$strprovidercatalogsql=f_workpropertyprovidercatalogsql("T_WC_", 17);
f_wpcheck("provider catalog uses movie and TV catalog state", true, strpos($strprovidercatalogsql, "TMDB_WATCH_PROVIDER_CATALOG_STATE")!==false);
$strproviderregionsql=f_workpropertyproviderregionssql("T_WC_", 17);
f_wpcheck("provider regions use the provider namespace", true, strpos($strproviderregionsql, "TMDB_WATCH_PROVIDER_REGION")!==false);

$strproviderworkssql=f_workpropertyproviderworkssql("T_WC_", "movie", 17, "fr", "fr", 50, 100);
f_wpcheck("provider movies use the movie association", true, strpos($strproviderworkssql, "TMDB_MOVIE_WATCH_PROVIDER")!==false);
f_wpcheck("provider works preserve the selected country", true, strpos($strproviderworkssql, "A.COUNTRY_CODE = 'FR'")!==false);
f_wpcheck("provider works localize titles", true, strpos($strproviderworkssql, "TMDB_MOVIE_LANG")!==false);
f_wpcheck("provider works aggregate separate modes", true, strpos($strproviderworkssql, "GROUP_CONCAT(DISTINCT A.MONETIZATION_TYPE")!==false);
f_wpcheck("provider works use bounded pagination", true, strpos($strproviderworkssql, "LIMIT 50 OFFSET 100")!==false);
$strboundedproviderworkssql=f_workpropertyproviderworkssql("T_WC_", "movie", 17, "FR", "en", "250; DROP", -10);
f_wpcheck("provider SQL caps page size and clamps offset", true,
	strpos($strboundedproviderworkssql, "LIMIT 100 OFFSET 0")!==false);
$strprovidermoviecountsql=f_workpropertyproviderworkscountsql("T_WC_", "movie", 17, "fr");
f_wpcheck("provider movie count deduplicates works", true,
	strpos($strprovidermoviecountsql, "COUNT(DISTINCT A.ID_MOVIE)")!==false);
f_wpcheck("provider movie count matches visibility filters", true,
	strpos($strprovidermoviecountsql, "P.TIM_CREDITS_COMPLETED IS NOT NULL")!==false);
$strproviderseriecountsql=f_workpropertyproviderworkscountsql("T_WC_", "serie", 17, "US");
f_wpcheck("provider series count uses the series association", true,
	strpos($strproviderseriecountsql, "TMDB_SERIE_WATCH_PROVIDER")!==false &&
	strpos($strproviderseriecountsql, "COUNT(DISTINCT A.ID_SERIE)")!==false);
f_wpcheck("unsupported provider count type rejected", "",
	f_workpropertyproviderworkscountsql("T_WC_", "network", 17, "FR"));
f_wpcheck("provider SQL never uses network tables", false, strpos(
	$strprovideridentitysql.$strprovidercatalogsql.$strproviderregionsql.$strproviderworkssql.
		$strprovidermoviecountsql.$strproviderseriecountsql,
	"NETWORK")!==false);

$strselectorhtml=f_watchcountryselectorhtml(
	"FR",
	array("FR"=>"France <test>", "US"=>"United States"),
	array("movieid"=>42),
	"movie-watch-country",
	"Viewing country",
	"Choose",
	"Apply"
);
f_wpcheck("selector uses dedicated watch_country name", true, strpos($strselectorhtml, 'name="watch_country"')!==false);
f_wpcheck("selector keeps explicit country", true, strpos($strselectorhtml, 'value="FR" selected')!==false);
f_wpcheck("selector escapes country label", true, strpos($strselectorhtml, 'France &lt;test&gt;')!==false);

$arrcountrymap=array("FR"=>"France", "US"=>"United States");
f_wpcheck("label read from the map", "France",
	f_countrycodetranslatefrommap("FR", $arrcountrymap, "en"));
f_wpcheck("map lookup normalizes the code", "United States",
	f_countrycodetranslatefrommap(" us ", $arrcountrymap, "en"));
// f_countrycodetranslate() lives in global-light.inc.php, which this test never
// loads, so a miss exercises the isolation fallback rather than the query.
f_wpcheck("missing country falls back to its code", "DE",
	f_countrycodetranslatefrommap("de", $arrcountrymap, "en"));
f_wpcheck("non-country value is returned untouched", "en-US",
	f_countrycodetranslatefrommap("en-US", $arrcountrymap, "en"));

echo "\n".$lngchecks." checks, ".$lngfailures." failure(s).\n";
exit($lngfailures>0 ? 1 : 0);

?>
