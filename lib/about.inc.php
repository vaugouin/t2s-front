<?php
//About page: what the site is, where its data comes from and how each source is credited, and
//how the search works. Every sentence is an interface string, in both languages.
//
//SITE_REPOSITORY_URL in .env: address of the public source repository. Optional: without it the
//"Source code" section is left out.

include(__DIR__."/global-light.inc.php");
include(__DIR__."/language.inc.php");

//The title carries the site name (SITE_NAME in .env, see f_sitename)
$straboutitle=sprintf(get_movie_string('lib_about_title'),f_sitename());
f_headerdisplay($straboutitle);
echo "<h1>".htmlspecialchars($straboutitle,ENT_QUOTES,'UTF-8')."</h1>";
echo "<p>".get_movie_string('lib_about_intro')."</p>";

//Search
echo "<h2>".get_movie_string('lib_about_search')."</h2>";
echo "<p>".get_movie_string('lib_about_search_text')."</p>";
echo "<p>".get_movie_string('lib_about_search_limits')."</p>";

//Data sources, each with the credit its terms of use ask for
echo "<h2>".get_movie_string('lib_about_data_sources')."</h2>";
echo "<p>".get_movie_string('lib_about_data_sources_intro')."</p>";

echo "<h3><a href=\"https://www.themoviedb.org/\" target=\"_blank\" rel=\"noreferrer\">The Movie Database (TMDb)</a></h3>";
echo "<p><a href=\"https://www.themoviedb.org/\" target=\"_blank\" rel=\"noreferrer\"><img src=\"images/tmdb-logo-blue-short.svg\" alt=\"The Movie Database (TMDB)\" height=\"16\"></a></p>";
echo "<p><strong>".get_movie_string('lib_about_tmdb_notice')."</strong></p>";
echo "<p>".get_movie_string('lib_about_tmdb_text')."</p>";

echo "<h3><a href=\"https://www.justwatch.com/\" target=\"_blank\" rel=\"noreferrer\">JustWatch</a></h3>";
echo "<p>".get_movie_string('lib_watch_providers_attribution')." ".get_movie_string('lib_about_justwatch_text')."</p>";

echo "<h3><a href=\"https://www.imdb.com/\" target=\"_blank\" rel=\"noreferrer\">IMDb</a></h3>";
echo "<p><strong>".get_movie_string('lib_about_imdb_notice')."</strong></p>";
echo "<p>".get_movie_string('lib_about_imdb_text')."</p>";

echo "<h3><a href=\"https://www.wikidata.org/\" target=\"_blank\" rel=\"noreferrer\">Wikidata</a></h3>";
echo "<p>".get_movie_string('lib_about_wikidata_text')."</p>";

echo "<h3><a href=\"https://www.wikipedia.org/\" target=\"_blank\" rel=\"noreferrer\">Wikipedia</a></h3>";
echo "<p>".get_movie_string('lib_about_wikipedia_text')."</p>";

//Source code
$strrepositoryurl=trim((string)env('SITE_REPOSITORY_URL',''));
if (preg_match('/^https?:\/\//i',$strrepositoryurl)) {
	echo "<h2>".get_movie_string('lib_about_source_code')."</h2>";
	echo "<p>".get_movie_string('lib_about_source_code_text')." <a href=\"".f_htmlattr($strrepositoryurl)."\" target=\"_blank\" rel=\"noreferrer\">".f_htmlspecialchars($strrepositoryurl)."</a></p>";
}

f_footerdisplay();
?>
