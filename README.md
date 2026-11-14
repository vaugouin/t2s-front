# t2s-front

PHP front-end for a movie, series and person database. The pages read a MariaDB database (tables `T_WC_*`, built from TMDb, IMDb, Wikidata and Wikipedia data by separate crawlers) and send natural-language questions to a Text2SQL API, which answers with rows that the front renders as cards, tables, rankings, timelines, comparisons or yes / no verdicts.

The repository contains the front code only. It does not contain the database, its schema, the crawlers, the Text2SQL API, or any web server configuration.

Current version: see [VERSION](VERSION).

## What it does

- **Search** (`index.php`): a question in English or French is sent to the Text2SQL API; the answer is drawn as a card grid, a number card, a stat strip, ranked bars, a table, a timeline, trend columns, cards side by side, a fact card, a Yes / No verdict, a histogram, a share bar, a list of countries with their flags, the same countries shaded on a world map, the places of the answer as numbered marks on a map cropped around them (places on one spot share one mark, "1+3"), a scatter plot of two quantities, the rows under their groups (by trilogy, by region), or the lineage of a work (its sources and the remakes and adaptations made from it, generation by generation), depending on the hint returned by the API. A picture can be attached to the question (upload through `upload-vision.php`, which keeps the API key on the server). Under the answer, a thumbs up and a thumbs down let the reader say whether the result is right, with an optional comment (see *Result feedback*).
- **Detail pages** for movies, series, seasons, episodes and persons, with collapsible sections (cast, crew, images, videos, external identifiers, Wikidata properties, Wikipedia sections, watch providers, release dates).
- **Entity pages**: collections, companies, networks, genres, keywords, lists, awards, nominations, locations, movements, groups, source works, technical properties, topics, characters, jobs, languages.
- **Image galleries** (`images.php`, `season-images.php`, `episode-images.php`).
- **Settings** (`text2sql-settings.php`): model selectors per LLM task, kept in cookies.
- **Plugins**: optional folders that add pages, menu entries and blocks to the pages above, in private mode only (see *Plugins*). The repository ships none.

## Layout

```
t2s-front/
  html/                 web root
    app/                one small PHP file per page, plus the images used with a relative path
    css/                the dark theme stylesheet
    data/               world-110m.json, the world outline of the map (built, see below)
    images/             images used with an absolute path (/images/...)
    apple-touch-icon.png, favicon.ico
  lib/                  the code of every page (<page>.inc.php) and the shared libraries
    database/           credentials: only *.example files are tracked
  plugins/              optional, ignored by git: one folder per plugin
  test/                 plain PHP test scripts
  tools/                build-world-map.py, which builds html/data/world-110m.json
  .env.example          runtime configuration template
  VERSION               version of the front
```

The layout is not a preference, the code depends on it:

- Each page in `html/app/` is a three-line file: `include(__DIR__."/../../lib/<page>.inc.php");`. `lib/` must therefore sit beside `html/`, two levels above the page.
- `lib/env.inc.php` loads `lib/../.env`, so `.env` sits beside `html/` and `lib/`.
- `lib/t2s-representation.inc.php` reads the world outline from `lib/../html/data/world-110m.json`.
- The stylesheet is linked as `../css/...` and the shared images as `/images/...`, so the page folder, `css/` and `images/` must be exposed one level under the web root.

`lib/`, `plugins/` and `.env` must stay outside the web root.

## Requirements

- PHP 8 (developed on 8.x; the code uses `str_contains`, `WeakReference` and other PHP 8 features) with the `mysqli`, `mbstring` and `curl` extensions. `json` and `session` are part of a standard build.
- A web server that runs PHP, for example Apache with PHP-FPM. The document root must expose `html/`.
- A MariaDB database with the `T_WC_*` tables, and two accounts on it: one read-only, one read-write.
- A reachable Text2SQL API (the `fastapi-text2sql` project) for the search page.
- A TMDb API token for the pages that call the TMDb API.
- Node.js on the `PATH`, only to run three of the tests.

## Configuration

Nothing secret is tracked. Three files are created at deployment time from the examples:

| File to create | From | Holds |
|---|---|---|
| `.env` | `.env.example` | URL prefixes, default language, public base URL, address of the source repository, Text2SQL API URLs and key, search timeout. Every variable the code reads is listed in `.env.example` |
| `lib/database/global-light-secrets.inc.php` | `lib/database/global-light-secrets.inc.php.example` | TMDb API key and token, the `f_env()` host switch |
| `lib/database/database.inc.php` | `lib/database/database.inc.php.example` | MariaDB host, database name, read-only and read-write accounts |

```bash
cp .env.example .env
cp lib/database/global-light-secrets.inc.php.example lib/database/global-light-secrets.inc.php
cp lib/database/database.inc.php.example lib/database/database.inc.php
# then edit the three copies
```

All three copies are listed in `.gitignore`.

`f_env()`, in the secrets file, picks the credentials file from the host name of the request. The example ships with one environment; add a `case` and a second credentials file to run another one.

A few settings are written in `lib/global-light.inc.php` itself and are meant to be edited there: the API colour in service (`$strtext2sqlapicolor`), the default model of each LLM task, and the name of the stylesheet.

## URL prefixes

The same pages can be served under several URL prefixes. The prefix of the request decides the language of the interface and whether the page runs in private mode (`$intprivate`). The list is configuration, in `.env`; no folder name is written in the code:

```
T2S_URL_PREFIXES=/en/:en,/fr/:fr,/staff/:en:private
T2S_DEFAULT_LANG=en
```

Each entry is `prefix:language`, followed by `:private` when the prefix is a private one. The languages are `en` and `fr`. With the example above:

| Prefix | Language | Mode |
|---|---|---|
| `/en/` | English | public |
| `/fr/` | French | public |
| `/staff/` | English | private |

- A request under a prefix that is not listed is public and uses `T2S_DEFAULT_LANG`. Without `T2S_URL_PREFIXES` the whole site is therefore public, in one language, with no language switch.
- When several listed prefixes match, the longest one wins.
- The language switch of the footer leads to the same page under the first public prefix in the other language. It is not shown when there is none, and it never points to a private prefix.

Only `html/app/` exists in the repository. Every prefix is expected to be an alias or a symbolic link to it, created on the server (`/en/` to `html/app/`, and so on).

Private mode loads the plugins. **The web server must put the private prefixes behind authentication**: the application does not authenticate anyone, it only reads the prefix. Protect them at every entry point of the server, and with a rule on the URL (`<Location>` in Apache), since PHP requests handed to PHP-FPM do not map to a directory.

The prefix map is read in one place, `lib/private-mode.inc.php`. A page that connects with the read-write database account refuses to run outside a private prefix: it answers HTTP 403 before the connection is opened. No page of the core writes to the database on its own; the read-write account is asked for by the plugin entry point (`x.php`) for a plugin page registered as such, and `lib/global-light.inc.php` applies the same refusal to any page that asks for it, as a second lock.

## Plugins

A plugin is a folder `plugins/<name>/` that holds a `plugin.inc.php` file. `plugins/` is ignored by git: a plugin is installed by copying or linking its folder there, and removed by deleting it. `T2S_PLUGINS_DIR` in `.env` names another folder to scan instead.

Two rules hold the design together:

- **Plugins are loaded in private mode only.** A public request never includes a plugin file. With or without plugins, a public page is the same page.
- **The core never names a plugin.** It calls hooks; a hook nobody listens to returns an empty string, or its value unchanged.

A plugin can do three things: listen to hooks, provide pages, and serve static files.

```php
<?php
//plugins/example/plugin.inc.php: registers the plugin and nothing else
function f_example_menu($arrcontext) {
	return "<a href=\"".f_htmlattr(f_pluginpageurl("example","report"))."\">Report</a>";
}
f_pluginregister("example",array(
	"dir"=>__DIR__,
	"hooks"=>array(
		"menu.items"=>"f_example_menu"
	),
	"pages"=>array(
		"report"=>array("file"=>"pages/report.inc.php"),
		"update"=>array("file"=>"pages/update.inc.php","readwrite"=>true)
	)
));
```

- Names of plugins and pages are made of lowercase letters, digits and hyphens.
- `plugin.inc.php` is included from inside a function, and possibly before `lib/global-light.inc.php`: it defines functions and registers, it prints nothing and calls no function of the core at include time. A value its functions read with `global` is set through `$GLOBALS`.
- **Pages.** `x.php?plugin=<name>&view=<page>` runs a registered page. The page file starts directly with its own code: `global-light.inc.php` and `language.inc.php` are already loaded. `"readwrite"=>true` gives the page the read-write database account, behind the private-mode lock. `f_pluginpageurl($strplugin,$strpage,$arrparams)` builds the URL. A `GET` form of a plugin page must carry `plugin` and `view` as hidden fields.
- **Static files.** `x.php?plugin=<name>&asset=<file>` serves a file of the `assets/` folder of the plugin (`css`, `js`, `png`, `jpg`, `webp`, `svg`, `gif`). `f_pluginasseturl($strplugin,$strfile)` builds the URL.
- Outside private mode, or for anything that is not registered, `x.php` answers HTTP 404.
- **Interface strings.** A plugin adds its keys to the two language arrays with the `lang.strings` filter.

### Hooks

`echo f_pluginhtml("<hook>",$arrcontext);` calls an HTML hook: each listener is a `function($arrcontext)` that returns HTML, and the results are concatenated. `f_pluginfilter("<hook>",$varvalue,$arrcontext)` calls a filter: each listener is a `function($varvalue,$arrcontext)` that returns the value, changed or not. Listeners run by ascending priority (default 10), then in registration order. A listener returns its HTML, it does not print it.

HTML hooks:

| Hook | Where | Context |
|---|---|---|
| `head` | end of `<head>` of every page | none |
| `menu.items` | the menu, after the entries of the core | none |
| `page.beforefooter` | end of the pages that list titles, before the footer | `page` |
| `entity.h1` | inside the `<h1>` of a movie, series, season or episode page, after the title | `type`, `id`; movie and series: `title`, `originaltitle`, `year`, `imdbid`; season and episode: `serieid` |
| `entity.info` | movie page, after the facts of the record | `type`, `id`, `title`, `titleyear` |
| `entity.ids` | identifiers block of a movie or series page | `type`, `id`, `imdbid`, `wikidataid` |
| `entity.afterids` | under that identifiers block | `type`, `id`, `imdbid` |
| `entity.sections` | movie, series, season and episode pages, after the sections of the core | same as `entity.h1` |
| `entity.actions` | end of a status line of a person, collection, company, keyword, list or network page | `type`, `id`, `slot` (`record`, `tagging` or `refresh`) |
| `entity.status` | under the record status of the same pages | `type`, `id` |
| `entity.admin` | person page (`vip`) and list page (`createdby`) | `type`, `id` |
| `entity.notice` | group page, about the computation of its works | `type`, `id`, `notice`, `date` |
| `entity.wikidata` | above the Wikidata section of an entity page | `page`, `wikidataids`, `lang`, `langalt` |
| `index.row.badge` | beside a name in the indexes (companies, genres, lists, networks, persons) | `type`, `id`; persons: `index` (`search` or `featured`) |
| `list.row.prefix` | before the title of a movie, series or person row | `type`, `id`, `title`, `originaltitle`, `year`, `imdbid`, and `level` or `context` |
| `list.row.suffix` | under a movie or series row | same |
| `season.episode.row` | season page, under each episode of the table | `seasonid`, `episodeid` |
| `samples.row.actions` | samples page, beside each sample | `id`, `set` (`showcase` or `sample`) |
| `search.result.row` | search page, once per movie or series rendered; the return value is ignored | `type`, `id`, `imdbid`, `render` |
| `search.result.after` | search page, after the result | `keycolumn`, `keyvalues` |

Filters:

| Hook | Value | Context |
|---|---|---|
| `lang.strings` | the two language arrays, `language => key => text` | none |
| `page.url` | URL of a page a plugin may provide, `""` when no plugin does; the core then prints no link | `page`, `params` |
| `request.readwrite` | `false`; a listener returns `true` when the request needs the read-write account (search page only, behind the private-mode lock) | `page` |
| `samples.showall` | `0`; `1` shows every sample and category of the samples page, whatever its evaluation state | none |

`test/plugins-test.php` fails when the core calls a hook that this section does not list.

## Result feedback

The search page prints one feedback block per search, under the answer and its trace, above the rows. It is not repeated by the calls that load the next cards. It shows on every outcome of a question, rows, no row or error, and not when the search carried no question at all (a photo alone).

- **What the reader sees.** "Was this result right?" with a 👍 and a 👎 button (`aria-pressed` marks the choice that stands). A click opens an optional one-line comment with Send and Skip, and the vote is recorded then, once, with or without the comment (Enter sends, Escape skips). A click on the other button, before or after, makes it the vote to record and reopens the comment; a click on the button already recorded does nothing, so a vote is never counted twice. On a failure the comment stays open with its text and Send retries. After a 👎 the status line adds that the answer will be computed again the next time the question is asked: the API stops serving that cached answer. The block is printed hidden and shown by its script, so a browser without JavaScript sees no button that cannot send.
- **What is sent.** `rating` (1 or -1), `comment` when there is one, and what the page knows of the result: `question`, `question_hashed`, `request_id`, `api_version` (from the search answer, else the running version of the colour), `sql_query`, `result_entity`, `ui_language`. Empty values are left out, so the API applies its own default.
- **The shim.** The browser posts JSON to `feedback.php?apicolor=<colour>`, on the same origin, never to the API: the key stays on the server. `lib/feedback.inc.php` accepts `POST` only (405 otherwise), refuses a body over 256 KB (413), validates the rating and the lengths (question 4,000 characters, comment 2,000; 422), applies a throttle of 60 posts an hour per session (429), fixes `client` to `t2s-front`, forwards to `POST /feedback` of the colour that answered the question, and returns only `id_feedback`, `status`, `cache_bypassed` and `api_version`. On a failure it returns `{"error": ...}`, a sentence in the language of the page; a 503 of the API (feedback store unavailable) has its own sentence. A vote is sent once and never retried.
- **Where the code is.** The validation, the payload, the whitelist of the answer, the markup and the script are in `lib/feedback-ui.inc.php`, which has no side effect and is covered by `test/feedback-test.php`. The labels are the `lib_t2s_feedback_*` keys of `lib/language.inc.php`.

## Runtime mounts

One URL path is used by the pages and is not part of the repository. It must be published by the web server from a directory filled by another project:

- `/synthetic-images/`: generated illustrations of entities (the `synthetic-images` project), referenced by the cards and the entity pages.

Without it the pages work and the corresponding images are missing.

## Tests

There is no framework and nothing to install. The tests are plain PHP scripts, run from the repository root, that need neither a database nor a web server nor the network. Each one exits with 0 when every check passes and 1 otherwise.

```bash
php test/api-version-test.php
php test/feedback-test.php                # needs node on the PATH for one check
php test/image-gallery-test.php
php test/open-credits-format-test.php
php test/plugins-test.php
php test/private-mode-test.php
php test/samples-format-test.php          # needs the mbstring extension
php test/social-ids-test.php
php test/t2s-representation-test.php      # needs node on the PATH for one check
php test/vision-image-test.php            # needs node on the PATH for one check
php test/wikipedia-md-render-test.php
php test/work-properties-format-test.php
```

| Script | Covers |
|---|---|
| `api-version-test.php` | `lib/api-version.inc.php`: the version read from the API and its file cache |
| `feedback-test.php` | `lib/feedback-ui.inc.php`: the validation and the payload of the result feedback, the whitelist of the answer, the labels in English and in French, the markup and the JavaScript of the block |
| `image-gallery-test.php` | `lib/image-gallery.inc.php`: the size announced to PhotoSwipe, the gallery markup and script, and the three gallery pages |
| `open-credits-format-test.php` | `lib/open-credits-format.inc.php`: on-screen credits put back into rows |
| `plugins-test.php` | `lib/plugins.inc.php`: registry, listener order, loader, page and asset lookups; the 404 of `x.php`; every hook of the core is documented above |
| `private-mode-test.php` | `lib/private-mode.inc.php`: the prefix list, private and public prefixes, language and language switch, the HTTP 403 guard, and its presence wherever the read-write account is asked for |
| `samples-format-test.php` | `lib/samples-format.inc.php`: the samples showcase rules |
| `t2s-representation-test.php` | `lib/t2s-representation.inc.php`: the result representations of the search page, with fixtures in `t2s-representation-fixtures.php` |
| `vision-image-test.php` | `lib/vision-image.inc.php`: image format sniffing, the image reference whitelist, the JavaScript of the picture search |
| `wikipedia-md-render-test.php` | `lib/wikipedia-md.inc.php`: Wikipedia section Markdown rendered as HTML |
| `work-properties-format-test.php` | `lib/work-properties-*.inc.php`: watch providers and release dates |

`lib/global-light.inc.php` opens the database connection when it is loaded, so nothing inside it can be tested alone. A helper that deserves a test lives in a file that can be included without side effect, like the ones listed above.

`php -l` on every file is a useful extra check, with one limit: it does not see a JavaScript error inside a string that PHP prints.

## Third-party components

| Component | Use | Licence |
|---|---|---|
| [PhotoSwipe](https://photoswipe.com) 5.4.4 | Lightbox of the image galleries. Not vendored: loaded from the jsDelivr CDN as an ES module, version pinned in `lib/image-gallery.inc.php` | MIT |
| YouTube embedded player | Trailers and videos (`yt-player.php`), loaded from youtube.com | YouTube terms of service |
| [Natural Earth](https://www.naturalearthdata.com) 1:110m countries, through the npm package [world-atlas](https://github.com/topojson/world-atlas) 2.0.2 | The world outline of the map of countries, `html/data/world-110m.json` (about 47 KB): projected once, offline, in Equal Earth by `tools/build-world-map.py` into one SVG path per ISO 3166-1 numeric code, Antarctica left out, with a dot for each country too small at that scale (from the package's 1:50m file, or entered by hand for a territory drawn inside another country). The file also carries the projection's constants (`projection`), with which the map of places puts a latitude and a longitude on the same outline. The page draws it on the server: no map library, no tile server, no request to a third party. Rebuild, from the files of the `world-atlas-2.0.2.tgz` tarball: `uv run --no-project --with pycountry==26.2.16 python tools/build-world-map.py <package>/countries-110m.json <package>/countries-50m.json html/data/world-110m.json` (the ISO numeric to alpha-2 table comes from the iso-codes data of pycountry) | Natural Earth: public domain; world-atlas: ISC |

Images and data:

- The posters, backdrops, profiles and logos shown by the pages are served by the TMDb image servers and are not part of this repository. This product uses the TMDb API but is not endorsed or certified by TMDb.
- `html/images/tmdb-icon.webp`, `html/app/images/tmdb-logo-blue-short.svg`, `html/app/images/IMDb-icon.png`, `html/images/Wikidata-logo.svg.png` and `html/images/The_Criterion_Collection_Logo.svg.png` are logos and trademarks of their respective owners. They are used only to label links to those services and are not covered by the MIT licence of this repository.
- `html/images/No_Image_Available.jpg` and `html/images/Wikidata-logo.svg.png` have file names in the Wikimedia Commons style, which suggests they were taken from there. Their origin is not recorded in the project history.
- The origin of the small interface icons (`page_copy.png`, the language flags and the default poster and profile placeholders) is not recorded in the project history.

## Deployment

The server holds a clone of the repository; a release is a `git pull`.

First installation:

1. Clone the repository outside the web root, or so that only `html/` is exposed as the document root.
2. Create the three configuration files (see *Configuration*), and list the URL prefixes in `.env`.
3. Create each URL prefix as an alias or a link to `html/app/`, and put the private ones behind authentication.
4. Publish `/synthetic-images/` if the corresponding data is available.
5. Optional: install plugins under `plugins/`.

Update:

```bash
git pull --ff-only
```

No build step and no cache to clear: PHP reads the new files at the next request. The stylesheet is renamed at each change (`t2s-dark-<date>-<n>.css`), so browsers fetch the new one.

## Versioning

`VERSION` holds the version of the front. It is raised in the same commit as any change.

## Licence

MIT, see [LICENSE](LICENSE).
