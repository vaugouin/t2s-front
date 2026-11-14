# AGENTS.md - Agent guide for t2s-front

This file gives an agent the context needed to work on this codebase safely. For the overview, layout, configuration, URL prefixes, tests and deployment, read @README.md; that file is canonical for those topics and is not duplicated here. Assistant-specific files such as @CLAUDE.md only point here.

## Purpose

`t2s-front` is the PHP front-end of a movie, series and person database. It reads a MariaDB database (`T_WC_*` tables) directly and calls a Text2SQL API (`fastapi-text2sql`) for the search page. The same code is served under several URL prefixes, public or private, listed in the configuration. Optional plugins add private features; the repository ships none.

The repository is public. The database, its schema files, the crawlers, the API and the server configuration (web server, containers, MariaDB) live elsewhere and are not documented here.

## Stack

- PHP 8, procedural, no framework, no Composer, no autoloader. Extensions: `mysqli`, `mbstring`, `curl`.
- MariaDB through the `f_mysql*` / `f_sql*` wrapper functions of `lib/global-light.inc.php`.
- HTML, CSS and JavaScript are printed by PHP. One stylesheet, in `html/css/`.
- One third-party script, PhotoSwipe 5, loaded from a CDN with a pinned version (`lib/image-gallery.inc.php`).
- Tests: plain PHP scripts in `test/`, no framework.

## Rules that apply to every change

1. **Version.** Raise `VERSION` in the same commit as any change, code or documentation. PATCH by default, MINOR or MAJOR when the change justifies it. Several commits pushed together may share one bump.
2. **Secrets.** Never commit a credential, a key, a token, a private host name or a server path. The real configuration lives in `.env` and `lib/database/*.inc.php`, all ignored by git; only the `*.example` files are tracked, with placeholders. Never print a secret, or a part of one, in a page or a log. Before a commit, check `git status` for an untracked file under `lib/database/`.
3. **Tests.** Run the test that covers the code you touch, before and after. Run all of them before a release (list in `README.md`).
4. **Documentation.** Keep `README.md` aligned with the behaviour and the configuration; change this file only when the working rules or the pitfalls change.
5. **Text.** UTF-8, LF line endings, tabs for indentation in PHP.

## Layout and include chain

- `html/app/<page>.php` is a shim of three lines: `include(__DIR__."/../../lib/<page>.inc.php");`. The code of the page is `lib/<page>.inc.php`. A new page needs both files.
- Almost every page starts with `include(__DIR__."/global-light.inc.php");` then `include(__DIR__."/language.inc.php");`.
- `lib/global-light.inc.php` (about 7,000 lines) does everything at load time, in this order: loads `lib/../.env` (through `lib/env.inc.php`), sets the API defaults, includes `private-mode.inc.php`, loads the plugins when the request is private (`plugins.inc.php`), includes `database/global-light-secrets.inc.php` (which includes the credentials file), **opens the database connection**, defines the helper functions, then decides the language and `$intprivate` from the URL prefix near the end.
- Includes are always written `__DIR__."/..."`. Do not introduce relative includes or an autoloader.
- Paths in the pages assume the layout described in `README.md`: `../css/...` for the stylesheet, `/images/...` for shared images, `images/...` for the images in `html/app/images/`.

Because `global-light.inc.php` opens a connection when it loads, **nothing inside it can be tested on its own**. A helper worth a test belongs in a side-effect-free file that can be `require`d alone (`private-mode`, `plugins`, `env`, `vision-image`, `feedback-ui`, `samples-format`, `social-ids`, `wikipedia-md`, `work-properties-*`, `movie-sections`, `t2s-representation`, `api-version`, `image-gallery`, `private-mode`). When you extract a helper, put it in such a file.

## Code conventions

- Hungarian prefixes on variables: `$str` string, `$lng` integer, `$int` flag or small integer, `$dbl` float, `$arr` array, `$dat` date, `$rstemp` fetched row, `$result` query result, `$strsql...` SQL text.
- Functions are named `f_<lowercase>` without separators (`f_stringtosql`, `f_headerdisplay`). Pure helper files wrap each function in `if (!function_exists('...'))`.
- Function comments follow the `//Purpose:`, `//Params:`, `//Returns:` shape.
- SQL is built by string concatenation, each fragment ending with a space. Values go through `f_stringtosql()`, `f_longtosql()`, `f_datetimetosql()`; never concatenate a request value directly.
- Request values are read with `f_request2()`, integers with `f_stringtolong(f_request2(...))`, presence with `f_requestisfieldset()`.
- Table names: `$strsqlns."TMDB_MOVIE"` and the literal `T_WC_TMDB_MOVIE` both appear; the prefix is always `T_WC_`.
- User-visible text goes through `get_movie_string('lib_...')`, with the key added to both the `en` and `fr` arrays of `lib/language.inc.php`. A key that only a plugin reads belongs in the `lang.inc.php` of that plugin.
- Part of the comments is in French: the mysqli wrapper layer is shared with another codebase and its comments are kept identical on both sides. New comments are in English.

## Private mode, the prefix map and the plugins

The URL prefix decides everything, and the prefixes are configuration: `T2S_URL_PREFIXES` in `.env` lists them with their language and marks the private ones (`README.md`, "URL prefixes"). The application authenticates nobody; the web server is expected to protect the private prefixes.

- **One source of truth.** The prefix map is read once, in `lib/private-mode.inc.php` (`f_prefixparse`, `f_prefixmap`, `f_prefixofpath`, `f_prefixstate`, `f_privatemodepath`, `f_privatemoderequest`, `f_privatemoderequire`). `global-light.inc.php` calls `f_prefixstate()` to set `$intprivate`, `$struilang`, `$struilangalt` and `$struilangalturl`. Never write a folder name in the code, never derive the language or private mode from anything else. A prefix that is not listed is public: private mode is never the default.
- `$intprivate` is set near the end of `global-light.inc.php`, **after** the database connection is opened. A page cannot use it to decide which account to connect with; `f_privatemoderequest()` answers the same question before the connection.
- **The core holds no private feature.** What only the person who runs the site needs (maintenance links, pages that write, links to tools of one deployment) lives in plugins, outside this repository. The core offers them hooks (`README.md`, "Hooks") and knows none of them by name. Do not add `if ($intprivate) { ... }` blocks to a page: add a hook, or use an existing one, and let a plugin answer. The few `$intprivate` tests left in the pages only choose between two core renderings (the character link of a cast line, the lists shown by `lists.php`).
- **Plugins load in private mode only** (`f_pluginsload(f_privatemoderequest())`). A public request never includes a plugin file, so a public page is the same with or without plugins. Keep it so: never call the loader with anything else, never include a file of `plugins/` from the core.
- A hook call costs one array lookup when nobody listens. An HTML hook is always `echo f_pluginhtml("<name>",array(...));`, with what the call site knows in the context array. A hook is named after the place where it fires, never after who listens, and its context carries the type and the identifier of the record: a column that only a plugin displays is read by that plugin, not selected by the core. Every hook is listed in `README.md`; `test/plugins-test.php` fails for a hook that is not. A new hook needs its row there.
- `f_movielistdisplay` and `f_serielistdisplay` are called positionally from about thirty places: a hook there builds its context from the existing arguments, no parameter is added.
- **The read-write account.** No core page writes to the database on its own. The account is asked for in two places: `lib/plugin-page.inc.php`, for a plugin page registered with `"readwrite"=>true`, and `lib/text2sql-search.inc.php`, when a plugin answers the `request.readwrite` filter. Both call `f_privatemoderequire()` just before `$intdbreadwrite=true`, and `global-light.inc.php` calls it again before the credentials are loaded. `f_privatemoderequire()` answers HTTP 403 and exits when the request is not under a private prefix. `test/private-mode-test.php` fails when another file sets `$intdbreadwrite=true`; update its expected list in the same commit as a legitimate addition.
- `html/app/x.php` (`lib/plugin-page.inc.php`) is the only entry point of the plugin pages and static files. It answers HTTP 404 outside private mode and for anything that is not registered, so that a public visitor cannot tell an absent plugin from a present one. File names never come from the request: the request names a plugin and a page, the registry holds the paths, and `f_pluginfileinside()` refuses whatever resolves outside the plugin folder.

## The host-based `f_env()` switch

`lib/database/global-light-secrets.inc.php` (untracked, created from the example) defines `f_env()`, which returns an environment name from the HTTP host, and a `switch` that includes the matching credentials file. Two pitfalls:

- An environment whose credentials file does not exist leaves `$dbhost` and `$dbuser` undefined: the page then reports the database as unavailable. When a page works under one host name and not under another, look at `f_env()` first.
- On the command line there is no HTTP host: `f_php_server_var()` reads `T2S_HTTP_HOST` and the other `T2S_*` variables of `.env` instead.

The example file ships with a single environment. Keep the tracked example free of real host names.

## MariaDB connections

`f_mysqlconnect()` registers each connection and a shutdown handler closes them all; the docblocks of `f_mysqlregisterconnection()`, `f_mysqlcloseall()` and `f_mysqlcloseconnection()` explain why the registry holds `WeakReference`s, why the handler is registered from inside another shutdown handler, and why `Throwable` is swallowed. Do not simplify any of the three. Anything that opens a connection outside `f_mysqlconnect()` must call `f_mysqlregisterconnection()` itself. Circuit breaker: `T2S_MYSQL_AUTOCLOSE=0` in `.env`.

Guards in this layer test `instanceof mysqli`, never `isset()` and never `=== null`. `mysqli_connect()` returns `false` on refusal (`1040 Too many connections`), both weaker guards let it through, and PHP 8 answers with a `TypeError` exactly where the page was supposed to degrade to "database unavailable". Without a handle, read errors from `mysqli_connect_errno()` / `mysqli_connect_error()`.

## Emitting JavaScript from PHP

The code prints JavaScript from inside PHP double-quoted strings, which crosses two escaping layers. A lost backslash once made PHP print a real newline inside a JavaScript string: the whole `<script>` block failed to parse and a button silently did nothing. `php -l` passes in that case.

1. **Let `json_encode` do the escaping.** Anything that crosses from PHP into JavaScript (a string, a separator, a label, a URL) goes through `json_encode`.
2. **Verify by running PHP, never by reimplementing its rules.** Render through a real PHP process, then `node --check` the output.
3. **Shape to copy:** a side-effect-free function that returns its script (`f_visionsearchjs()` in `lib/vision-image.inc.php`, `f_galleryscripthtml()` in `lib/image-gallery.inc.php`), with a test.

## Image galleries (PhotoSwipe)

`images.inc.php`, `season-images.inc.php` and `episode-images.inc.php` share `lib/image-gallery.inc.php`.

- PhotoSwipe must know the pixel size of each picture before opening it. The pages select `WIDTH` and `HEIGHT` from the `T_WC_TMDB_*_IMAGE` tables and `f_galleryimagesize()` scales them to the `w780` rendition the link points to. A row without a size falls back to the usual ratio of its image type.
- The version is pinned in `f_galleryphotoswipeversion()`. Never load `@latest`.
- The opening transition is a fade: the thumbnails are cropped by the TMDb image server, and PhotoSwipe does not support its zoom transition from such thumbnails.
- Do not reintroduce a library under a copyleft licence: the repository is MIT.

## Stylesheet cache-busting

The dark theme is `html/css/t2s-dark-<date>-<n>.css`, referenced exactly once, in `f_headerdisplay()` of `lib/global-light.inc.php`. Never edit the current file in place: copy it to a new dated name, update that single `echo` line, and delete the previous file in the same commit.

## Wikidata and Wikipedia sections on entity pages

Every page that renders a single entity whose source table has an `ID_WIKIDATA` column must display both a Wikidata section and a Wikipedia section, modelled on `lib/serie.inc.php`:

1. Always print the `<h2>` of `lib_wikidata_section` when `ID_WIKIDATA` is not empty; do not gate it on having properties.
2. Read `INSTANCE_OF` and `ALIASES` from `T_WC_WIKIDATA_ITEM_V1` for the interface language with English fallback; resolve the `INSTANCE_OF` label the same way.
3. Print the type (linked to `property.php?propertyid=P31`), the aliases when there are any, then `f_wikidataallpropertiesv2($struilang, $idwikidata, ", ")`. The older `f_wikidataallproperties` must not be used in new code.
4. Always print the `<h2>` of `lib_wikipedia_section` and append `f_wikipediallsections($struilang, $idwikidata, ", ")`.

Movie, series, person, season and episode pages read their own `WIKIDATA_*_V1` table; leave that alone unless asked. The `CONTENT_MD` rendering of `lib/wikipedia-md.inc.php` follows a Markdown dialect defined by the crawler that writes the column: if the dialect changes, change the renderer and its test together.

## Ratings: IMDb only

No page displays a TMDb rating or vote count. The `echo` lines of `VOTE_AVERAGE` / `VOTE_COUNT` are commented out, not deleted, so the decision stays visible.

- Every surface goes through `f_imdbratingdisplay()`, cards included. It returns `""` when there is no rating and the caller drops the whole line: never a `0` or a dash.
- `f_movielistdisplay` and `f_serielistdisplay` take the vote count as a trailing optional argument. Both are called positionally from about thirty places: never insert an argument in the middle of the signature.
- The vote count comes from the same row as the rating (`f_imdbratingvotes()`).
- Seasons use the precomputed `T_WC_T2S_SEASON.IMDB_RATING`, never a local average.

## Detail pages: collapsible sections

`movie`, `serie`, `season`, `episode` and `person` render their blocks as `<details>` accordions with an icon anchor bar (`lib/movie-sections.inc.php`, usage in its header comment). Entity pages are deliberately not converted.

- The order of the sections is data (the array given to `f_moviesectionreset()`), not code position. Each section is buffered on its own: no unbalanced `ob_start()` across two section openers, no `exit` inside the buffered zone.
- Shared blocks are wrapped with `f_moviesectionwrap($id,$icon,$html,$label=null)`; an empty output creates no accordion.
- Nothing clickable inside a `<summary>`: a click there collapses the section.

## Work properties on movie and series pages

Watch providers and release dates are read from the raw TMDb snapshot tables.

- `watch_country` is an explicit ISO 3166-1 alpha-2 preference, validated against `T_WC_COUNTRY`, in its own cookie. Never derive it from the interface language, the time zone or a production country.
- A completion marker with zero child rows is a successful empty snapshot; `NULL` means not collected.
- Provider identifiers stay in their own namespace (never routed to `network.php`), the five monetization modes stay separate, and the TMDb links and the JustWatch attribution stay in place.
- The pure helpers live in `lib/work-properties-*.inc.php` and must stay loadable without a database.
- In loops, translate country codes with `f_countrycodetranslatefrommap()`, never `f_countrycodetranslate()` (one unindexed scan per code).

## Entity cards in the search result: four places, not one

`lib/text2sql-search.inc.php` renders one API row set through two renderers, `grid` and `card`, and the card renderer walks each row twice. Adding an entity means four edits, and skipping one fails quietly:

1. Grid renderer, `case "ID_<ENTITY>"`: set `$strrowurl` (or `$strcellurl`) to the detail page.
2. Card renderer, first pass, `case "ID_<ENTITY>"`: when `$lngcol==0`, name the section, set `$intisvisible=true` and `$strresultkeycolumn=$strkey`.
3. Card renderer, first pass, the consumed-column list: add every descriptor column the card reads to the fall-through block that sets `$intisvisible=false`, or it renders as a raw value on the poster.
4. Card renderer, second pass, `case "ID_<ENTITY>"`: build `$strcardmedia`, `$strcardtext` and `$strcardreveal`. Copy the nearest sibling (movement, award, nomination).

Two labels go in `lib/language.inc.php`, and the card needs a detail page under `html/app/`. The columns a card may read are declared by the API prompt, not chosen here; fall back to `f_descfromcode()` for anything the API might omit. Entity routes for the representations are written once, in `f_t2srep_entity_routes()`: a new entity page goes there too.

Each card line is floored so every card of a query stacks the same number of lines. The blank second title line is the price of the alignment; the year line is always printed, do not wrap it back in an `if`.

Specific rules:

- **Locations and source works: `DELETED = 1` is load-bearing.** The row is kept so the identifier is never reused. Every read filters `COALESCE(DELETED,0) = 0`, on the descriptor, the junctions and the works.
- Never merge the `filming` and `narrative` roles of a location.
- `LOCATION_NAME_FR` is deliberately read nowhere; `SOURCE_WORK_NAME_FR` is used everywhere.
- `person.php` has no location section and must not grow one.
- The `301` to the work page in `sourcework.inc.php` runs before any output; keep it there.
- `group.php` tests for the existence of its tables (`f_grouptableexists`) instead of assuming it.

## Text2SQL model selectors

Three files, one idiom, and all three must move together. A new selector needs: the per-colour defaults and the allow-list in `global-light.inc.php`; the read / cookie / validate / default / setcookie chain in both `lib/text2sql-search.inc.php` and `lib/text2sql-settings.inc.php`, which duplicate it; and a radio group in the settings form.

- Each parameter (and `image_ref`) is added to the API payload only when it is not empty, so the API applies its own default and the front can talk to an API version that predates the parameter. Do not turn this into "always send the key".
- The API version is read with `f_text2sqlapiversion($strcolor)`; the two version strings in `global-light.inc.php` are only its fallback.
- A model absent from its allow-list is silently reset to the default of the colour. When a model change appears to do nothing, check the allow-list first.

## Picture-based search

1. `TEXT2SQL_API_KEY_VALUE` is a server secret. The browser uploads to the same-origin shim `html/app/upload-vision.php`, which adds the key. Never replace it with a direct `fetch` to the API.
2. The search form is a `GET` form and must stay one; the upload happens in JavaScript before submission.
3. `image_ref` travels with the pagination: hidden field, previous / next links, infinite-scroll URL.
4. A client reference is matched against the whitelist before any URL is built from it, and the format is decided by magic number. This is why the page reads `$_GET['imageref']` raw.
5. The includes of the upload shim run inside an output buffer that is discarded into the error log: one notice in front of the JSON or the image bytes would corrupt the answer.
6. The two `<script>` blocks of the search box communicate through a `t2simagerefchange` event. The inline block sits between `//--- search-box script: begin ---` and `: end ---` markers that the test renders and checks: every line there must stay a literal `echo` with no PHP interpolation.
7. Drag and drop reuses the same chain (`f_visionhandlefile()`); do not add a second upload path.

## Result feedback

The thumbs up / down under the answer follows the same rule as the picture upload: the browser posts to the same-origin shim `html/app/feedback.php`, which adds the key. Never a direct `fetch` to the API, and `client` is fixed by the shim, never read from the browser. A vote is sent once and never retried (a retry could count it twice). The block is printed once per search, never in a `loadmore` call, and above the rows on purpose: below an infinite-scroll grid it would be reached only once every page is in. Details in `README.md`, "Result feedback".

## Samples page and result representations: mirrors of another front

`lib/samples-format.inc.php` and `lib/t2s-representation.inc.php` copy, on purpose, the rules of the `voice-agent` front, so that both fronts draw the same answer the same way. A change to the dispatch, a unit format, a fallback or a caption is made on both sides. The contract (fields, one table per representation, unit formats) is documented in the README of `fastapi-text2sql`.

- Read the representation hint with `isset()` and fall back to the cards when it is absent.
- Never format a number as dollars without the `usd` unit.
- The representation chips re-render locally: every option is rendered when the page is built and the inactive views carry `hidden`. Do not turn a chip into a fetch.
- `render=` is two things: `card` / `grid` is the page mode, kept in the session; a representation name is a per-question override, honoured only when the answer offers it, never written to the session.
- `sequence-strip` is a card view: its numbering is the API `offset` plus the row, so that a second page continues the count. Its grid is one horizontal row (`.t2sShowSequence .search-poster-card-grid`), so the card loader measures the distance to its right end, not to the bottom of the page.
- The timeline is not a card view, yet it must not stop at the first page: `f_t2srep_timeline_more_js()` fetches the next pages with `loadmore=1&loadmoreview=timeline` (answered by `f_t2srep_render_timeline_page()`, marks only) and files each mark under its `data-t2syear`. While a page may follow, the caption of a timeline or a sequence says "the first N"; the page recounts it with `f_t2srep_caption_fill_js()`, the twin of `f_t2srep_caption_fill()`: change both, the test compares them.
- `compare-cards`, `fact-card` and `verdict` read their facts from `summary_facts` (`winners`, `fact`, `verdict`); do not recompute a winner the API names. A verdict on an empty result is the one answer with no row that is still drawn (`$intt2sverdictempty` in the card case of the search page): keep any new "no rows" test after it. The evidence of a verdict on rows is the card view, whose `data-t2sreps` then also holds `verdict`; a count draws its number card inside the verdict view. Never word a No as "false": the fronts say "nothing in the database matches".
- `histogram`, `share-bar` and `flag-list` read `summary_facts.histogram`, `.share` and `.countries`; never re-bin rows, recompute shares or map a place name to a code here, the API does it. The flag is the code in a badge until `f_t2srep_flags_js()` finds that the browser draws flag emoji (Windows draws two letters); no flag image and no third-party asset. A long histogram scrolls inside `.t2sHisto` (`contain: inline-size`), or it widens the page at 390 px. One share alone (`share-bar` on one row) keeps its chips, like a fact card.
- `scatter` reads `summary_facts.scatter`: the points, the axes and the highest point are the API's, never re-read from the rows (a 0 budget is missing there, not here). The ticks, titles and caption are word for word those of voice-agent (`text2SqlScatterTicks()`, `text2SqlScatterSummary()`): change both. The grid is an SVG stretched over the plot (`preserveAspectRatio="none"`, non-scaling strokes) and the dots and tick labels are HTML placed by percentage, so text never scales with the width; a dot is a 24 px link, and `f_t2srep_scatter_js()` makes the first tap show the point under the plot and the second follow the link. The hidden point list is a table inside `div.t2sSrOnly`: a table ignores a 1 px box and would widen the page at 390 px.
- `map` reads `summary_facts.countries` (each entry's ISO `numeric` code) and `summary_facts.map`: the shading steps are the API's, never recomputed. The legend, the notes ("Not on the map: ...") and the caption (the flag-list sentence with "on the map" / "sur la carte") are word for word those of voice-agent (`text2SqlMapModel()`): change both. `html/data/world-110m.json` is built by `tools/build-world-map.py`, never edited by hand, and must stay byte for byte the file voice-agent serves (`test/t2s-representation-test.php` pins its hash): rebuild both together. The map is drawn on the server; `f_t2srep_map_js()` only writes the country under the pointer or the finger in the line under it, and without script each country keeps its `<title>`.
- `map` with `summary_facts.places` is the map of places (`f_t2srep_render_place_map()`); without it, the map of countries above. The places, their coordinates and which of them are areas (a country or a region, drawn as a dashed ring, not a point) are the API's, never looked up here. `f_t2srep_place_map_model()` projects them with the Equal Earth of `tools/build-world-map.py` (the outline's `projection` key), crops the map around them and numbers them in row order; the frame, the marks, the notes ("Not on the map: ...") and the caption (`f_t2srep_caption_places()`) are word for word those of voice-agent (`text2SqlPlaceMapModel()`), and the test pins them: change both. The list under the map links each place as its card does, from the first row of that place (`index`). When the map cannot be drawn (no place located, no outline or no `projection`), `f_t2srep_options()` puts the card grid in its place. `f_t2srep_map_js()` serves both maps: a mark and its list item share a `data-t2smap` key, and the line under the map reads `data-t2stip`.
- `hierarchy` reads `summary_facts.hierarchy`: the groups, their order and their counts are the API's, never regrouped from the rows (a place under two regions is shown under each because the API says so). When `leaf.image` is false the leaf is the second entity of the row (a film beside its collection): `f_t2srep_hierarchy_model()` cuts its record to its id and name, so it never takes the collection's poster nor its page. Headings and lists, no collapsing and no script; the caption (`f_t2srep_caption_hierarchy()`) and the markup classes are word for word those of voice-agent (`text2SqlHierarchySummary()`, `renderHierarchy()`): change both.
- `chain` reads `summary_facts.chain`, the API's lineage walk, never the rows (the rows of the first query stay what they were, the table chip still shows them): the generations are `chain.layers`, the cards `chain.nodes`, the lines under each card the `chain.edges` into it, a link closing a cycle marked as a loop. `f_t2srep_chain_model()` builds the cards and their records (a film, a series or a source work, whose page and picture are those of its own card), `f_t2srep_render_chain()` an ordered list of generations that scrolls inside `.t2sChain` on a desktop and turns into a column under 600 px (no page-level scroll at 390 px), `f_t2srep_caption_chain()` the sentence, with the French "de" and "à" elided or contracted before a title (`f_t2srep_chain_de()`, `f_t2srep_chain_a()`). A chain may stand on one row (the walk holds the works), so it keeps its chips there, as a fact card does. Caption, card lines, badges and markup classes are word for word those of voice-agent (`text2SqlChainSummary()`, `renderChain()`): change both.
- On the samples page nothing is hidden: what the showcase drops is shown dimmed with a status badge.
- The fixtures of `test/t2s-representation-test.php` are trimmed real answers; to add one, keep only the fields the representations read.

## Known dead ends

- `movie.inc.php` and `serie.inc.php` link production countries to `country.php`, which does not exist.
- `lib/about.inc.php` is a long descriptive page written for one deployment: it still describes pages and tools that are not part of this repository.

## Time zone

Dates read by a person are in Europe/Paris, set by name in `global-light.inc.php` (`date_default_timezone_set`). Never use a fixed offset.
