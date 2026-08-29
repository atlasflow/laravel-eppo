# laravel-eppo

A Laravel client for the [EPPO Global Database](https://gd.eppo.int) — the European and Mediterranean Plant Protection Organization's register of plants, pests, pathogens and their regulatory status — backed by a durable local cache.

EPPO codes are stable identifiers. `BEMITA` has meant *Bemisia tabaci* since 2002 and will still mean it in twenty years. A cache in front of that data should be measured in months, not minutes, and should survive a Redis restart, a deploy, and a machine rebuild. So the optional cache here is **a table in your own database** — it never expires by default, revalidates in the background, and is invalidated by asking EPPO what actually changed.

Caching is off until you turn it on. Out of the box this is a plain API client with no tables and no migrations.

```php
use Atlasflow\Eppo\Facades\Eppo;

Eppo::taxon('BEMITA')->overview()->prefname;      // "Bemisia tabaci"
Eppo::taxon('BEMITA')->hosts()->pluck('prefname');
Eppo::tools()->codeFor('tomato leaf curl virus'); // "TYLCV0"
Eppo::country('FR')->categorization();            // everything France regulates
```

## Requirements

- PHP 8.3+
- Laravel 11, 12 or 13
- An EPPO API key — free, from the dashboard at [data.eppo.int](https://data.eppo.int)

## Install

```bash
composer require atlasflow/laravel-eppo
```

```dotenv
EPPO_API_KEY=your-token
```

That is a working client. To add the durable cache:

```dotenv
EPPO_CACHE=true
```

```bash
php artisan vendor:publish --tag=eppo-migrations
php artisan migrate
```

The migrations are only registered while `EPPO_CACHE=true`, so an application that does not cache never grows a table it did not ask for.

The config file is optional:

```bash
php artisan vendor:publish --tag=eppo-config
```

## Reading data

Every method returns typed, readonly objects — `Illuminate\Support\Collection` for lists, so `pluck`, `firstWhere` and `groupBy` work as you would expect.

### Taxa

```php
$taxon = Eppo::taxon('BEMITA');

$taxon->overview();               // Taxon: prefname, is_active, replacedby, dates
$taxon->infos();                  // how many records exist of each kind
$taxon->names();                  // scientific names, common names, translations
$taxon->preferredName();          // shortcut for the preferred scientific name
$taxon->taxonomy();               // kingdom → phylum → … → species
$taxon->kingdom();
$taxon->categorization();         // A1/A2 listing per country, with years
$taxon->distribution();           // where it is recorded, and with what status
$taxon->hosts();                  // for a pest: its host plants
$taxon->pests();                  // for a plant: pests recorded on it
$taxon->vectors();                // organisms that transmit it
$taxon->vectorOf();               // organisms it transmits
$taxon->biologicalControlAgents();
$taxon->biologicalControlAgentOf();
$taxon->photos();
$taxon->documents();
$taxon->standards();              // EPPO Standards (PM 7/…)
$taxon->reportingArticles();
$taxon->exists();                 // false for a code EPPO does not hold
```

### The whole record at once

EPPO writes prose datasheets for its quarantine pests, but API v2 does not expose them — `/infos` reports `datasheet: 1` and there is no route to fetch it. `datasheet()` assembles the equivalent from the endpoints that *are* exposed:

```php
$sheet = Eppo::taxon('XYLEFA')->datasheet();

$sheet->name();                   // "Xylella fastidiosa"
$sheet->kingdom();                // "Bacteria" — from the taxonomy, not a second call
$sheet->rank();                   // "Species"
$sheet->sections();               // which of the 14 sections carry records
$sheet->counts();                 // how many records in each

$sheet->hostsByClass();           // grouped, commonest group first
$sheet->majorHosts();             // just the major ones
$sheet->currentListings();        // regulatory listings not since withdrawn, by list
$sheet->distributionByStatus();   // grouped by status code
$sheet->namesByLanguage();
$sheet->countries();
```

One `/infos` call up front says which sections have any records, and the empty ones are never requested. A taxon EPPO holds little about costs five calls rather than sixteen; `$sheet->fetched` tells you which ones it actually made. Narrow it further when you only need part:

```php
Eppo::taxon('BEMITA')->datasheet(['names', 'distribution', 'hosts']);
```

Two caveats, both upstream. `documents` is counted but the endpoint returns `[]` for every code tried. And `/infos` counts five more things — `expertise`, `specimens`, `eppolinks`, `pathwaypest`, `pathwayhost` — that have no route at all in v2, so they are reported in `$sheet->infos` and nowhere else.

A deprecated code tells you so:

```php
$taxon = Eppo::taxon('OLDCOD')->overview();

if (! $taxon->isUsable()) {
    $current = $taxon->replacedBy;   // follow it
}
```

### Countries and RPPOs

```php
Eppo::country('FR')->overview();        // name, continent, subdivisions
Eppo::country('FR')->categorization();  // what France regulates
Eppo::country('FR')->presence();        // pests recorded in France

Eppo::rppo('9A')->overview();           // EPPO itself, and its members
Eppo::rppo('9A')->categorization();
```

### Search

```php
use Atlasflow\Eppo\Resources\ToolsResource;

Eppo::tools()->nameToCodes('Bemisia tabaci');
Eppo::tools()->codeFor('Bemisia tabaci');
Eppo::tools()->search('Bemisia', ToolsResource::MODE_CONTAINS);
```

### Reference tables and the Reporting Service

```php
Eppo::references()->countries();
Eppo::references()->countriesStates();
Eppo::references()->qLists();                    // A1 list, A2 list, …
Eppo::references()->distributionStatuses();
Eppo::references()->pestHostClassifications();
Eppo::references()->vectorClassifications();
Eppo::references()->rppos();

Eppo::reportings()->list();
Eppo::reportings()->issue(1);
Eppo::reportings()->article(99);
```

### The taxon index

```php
// One page.
Eppo::taxons()->list(limit: 100, offset: 0);

// Every code, lazily.
foreach (Eppo::taxons()->cursor() as $taxon) {
    // …
}

// Only what moved since a date.
foreach (Eppo::taxons()->changedSince('2026-01-01') as $taxon) {
    // …
}
```

### Anything not wrapped yet

```php
Eppo::raw('/taxons/taxon/BEMITA/overview', resource: 'taxon.overview', subject: 'BEMITA');
```

It goes through the same cache, throttle and retry logic as everything else.

## The cache

Off by default. `EPPO_CACHE=true` turns on two tiers in front of the API.

| | Where | Lifetime | Purpose |
|---|---|---|---|
| **L1** | any Laravel cache store | 1 hour | absorbs repeated reads |
| **L2** | a table in your database | forever | the durable copy |

A read tries L1, then L2, then EPPO. A write fills both.

The durable tier is two ordinary tables you own — `eppo_cache_entries` and `eppo_sync_state` — and you decide where they live:

```dotenv
EPPO_CACHE=true
EPPO_CACHE_TABLE=eppo_cache_entries
EPPO_CACHE_SYNC_TABLE=eppo_sync_state
EPPO_CACHE_CONNECTION=          # blank = your default connection
EPPO_CACHE_DURABLE=true         # false = L1 only, nothing persisted
EPPO_CACHE_COMPRESS=false       # gzip payloads; roughly halves the table
```

Both migrations read those names from config, so renaming works before or after you migrate. `Atlasflow\Eppo\Cache\Models\EppoCacheEntry` is an ordinary Eloquent model over the table if you want to report on it.

Durable entries carry two independent clocks:

- **`stale_at`** — when the copy should be revalidated. Configurable per resource; `null` means never.
- **`expires_at`** — when the row may be deleted. `null` by default, which is the point: entries are revalidated, not evicted.

### Stale-while-revalidate

When a read finds a stale entry it returns it immediately and queues a `RefreshCacheEntry` job. Nobody waits for EPPO on the read path, and a lock ensures one refresh however many readers arrive at once.

```php
'revalidate' => [
    'enabled' => true,        // false = revalidate inline instead
    'queue' => 'low',
    'lock_seconds' => 60,
],
```

### Time to stale

```php
'ttl' => [
    'default' => Ttl::days(90),
    'taxon.overview' => Ttl::days(180),
    'taxon.distribution' => Ttl::days(30),   // this one genuinely moves
    'references.*' => Ttl::days(365),        // these barely move at all
    'tools.search' => Ttl::days(7),
    'negative' => Ttl::days(7),              // cached 404s
],
```

Keys are resource identifiers. An exact match wins, then the nearest `group.*` wildcard, then `default`. `Ttl::FOREVER` (`null`) means the entry only changes when something invalidates it explicitly.

### Busting it

Four levers, coarsest last:

```php
Eppo::taxon('BEMITA')->forget();                    // one code, every resource
Eppo::cache()->forgetSubject('BEMITA');             // same thing
Eppo::cache()->forgetResource('taxon.distribution'); // one resource, every code
Eppo::cache()->forgetResource('references.*');       // a whole group
Eppo::cache()->flush();                              // everything
```

```bash
php artisan eppo:cache:forget BEMITA
php artisan eppo:cache:forget --resource=taxon.*
php artisan eppo:cache:forget --all
```

There is also a global switch. `cache.version` is part of every cache key, so bumping it orphans the whole cache in one edit — and reverting it brings the cache straight back, because nothing was deleted:

```dotenv
EPPO_CACHE_VERSION=v2
```

`php artisan eppo:cache:prune` is what actually deletes: hard-expired rows, and rows left behind by an older version.

### Keeping it honest: `eppo:sync`

Expiring everything on a timer throws away thousands of unchanged records to catch the handful that moved. EPPO publishes a change feed instead, so this asks which codes changed and invalidates only those:

```bash
php artisan eppo:sync
php artisan eppo:sync --since=2026-01-01 --refresh
php artisan eppo:sync --dry-run
```

Each run records where it finished, so the next one picks up from there (minus a two-day overlap, in case EPPO backdates a change). Schedule it and forget it:

```php
use Atlasflow\Eppo\Jobs\SyncEppoChanges;

Schedule::job(new SyncEppoChanges)->dailyAt('03:00');
Schedule::command('eppo:cache:refresh')->hourly();   // top up stale entries
Schedule::command('eppo:cache:prune')->weekly();
```

### Warming

```bash
php artisan eppo:cache:warm BEMITA GOSHI --with=overview,names,distribution
php artisan eppo:cache:warm --file=codes.txt
php artisan eppo:cache:warm --references
php artisan eppo:cache:warm                 # top up everything already cached
```

Because the durable store is a plain table, a warm cache is portable: dump `eppo_cache_entries`, restore it in CI or on a new host, and the application starts warm.

### Working offline

If EPPO is unreachable and a stale copy exists, the stale copy is served rather than an exception thrown (`cache.serve_stale_on_error`, on by default). A 404 is an answer, not a fault, so it is never masked this way — and it is cached too, so a hot loop over a missing code does not hammer the API.

## Errors

Everything extends `Atlasflow\Eppo\Exceptions\EppoException`.

| Exception | When |
|---|---|
| `ConfigurationException` | no API key |
| `InvalidArgumentException` | a malformed code, caught before the request |
| `BadRequestException` | 400 |
| `AuthenticationException` | 401 — key missing or inactive |
| `AuthorizationException` | 403 |
| `NotFoundException` | 404, live or cached |
| `RateLimitException` | 429, after retries; carries `retryAfter` |
| `ServerException` | 5xx, after retries |
| `ConnectionException` | unreachable, all servers exhausted |
| `ThrottleException` | the local throttle would have blocked too long |

## Rate limiting

EPPO allows 2000 requests per IP in a 10-second sliding window. A shared, cache-backed counter keeps every worker on a host collectively under it, and a 429 is retried honouring the server's `retry_after`.

```php
'throttle' => [
    'enabled' => true,
    'max_requests' => 1800,
    'per_seconds' => 10,
    'max_wait_seconds' => 12,
],
```

## Events

```php
use Atlasflow\Eppo\Events\{CacheHit, CacheMissed, StaleEntryServed, EntryStored,
    EntryInvalidated, TaxonChanged, RequestSucceeded, RequestFailed};
```

`TaxonChanged` is the useful one: it fires during `eppo:sync` for every code EPPO reports as changed, which is where you hook your own denormalised tables.

`EntryStored` carries a `changed` flag telling you whether the refetched payload actually differed — useful for measuring how often EPPO data really moves.

## Commands

| Command | What it does |
|---|---|
| `eppo:status` | API health and cache statistics |
| `eppo:sync` | invalidate the codes EPPO reports as changed |
| `eppo:cache:warm` | pre-fetch records into the durable store |
| `eppo:cache:refresh` | re-fetch stale entries in bulk |
| `eppo:cache:forget` | bust by subject, by resource, or entirely |
| `eppo:cache:prune` | delete expired and orphaned rows |

## Testing against it

The package uses Laravel's HTTP client, so `Http::fake()` works normally. Events are dispatched through the container, so `Event::fake()` sees them.

```php
Http::fake(['*' => Http::response(['eppocode' => 'BEMITA', 'prefname' => 'Bemisia tabaci'])]);

expect(Eppo::taxon('BEMITA')->overview()->prefname)->toBe('Bemisia tabaci');
```

The cache is off by default, so tests see the network layer unless you turn it on.

## Environment reference

| Variable | Default | Meaning |
|---|---|---|
| `EPPO_API_KEY` | — | required; from data.eppo.int |
| `EPPO_BASE_URL` | `https://api.eppo.int/gd/v2` | primary server |
| `EPPO_FALLBACK_URLS` | — | comma-separated, tried on connection or 5xx failure |
| `EPPO_TIMEOUT` / `EPPO_CONNECT_TIMEOUT` | `15` / `5` | seconds |
| `EPPO_RETRY_TIMES` | `3` | attempts for 429, 5xx and connection errors |
| `EPPO_THROTTLE` | `true` | local rate limiter |
| `EPPO_THROTTLE_MAX` / `EPPO_THROTTLE_WINDOW` | `1800` / `10` | requests per window, seconds |
| **`EPPO_CACHE`** | **`false`** | **master switch; nothing is cached or migrated until this is true** |
| `EPPO_CACHE_VERSION` | `v1` | bump to orphan every entry at once |
| `EPPO_CACHE_DURABLE` | `true` | the database tier |
| `EPPO_CACHE_CONNECTION` | default | which database connection holds the tables |
| `EPPO_CACHE_TABLE` | `eppo_cache_entries` | the durable table |
| `EPPO_CACHE_SYNC_TABLE` | `eppo_sync_state` | sync high-water mark |
| `EPPO_CACHE_COMPRESS` | `false` | gzip payloads (needs ext-zlib) |
| `EPPO_CACHE_MISSES` | `true` | persist "no such record" answers |
| `EPPO_CACHE_L1` / `EPPO_CACHE_L1_STORE` / `EPPO_CACHE_L1_TTL` | `true` / default / `3600` | the hot tier |
| `EPPO_CACHE_SWR` | `true` | stale-while-revalidate |
| `EPPO_CACHE_SERVE_STALE` | `true` | serve stale data when EPPO is unreachable |
| `EPPO_CACHE_KEEP_FOR` | — | seconds until a row may be pruned; blank keeps forever |
| `EPPO_SYNC_PAGE_SIZE` / `EPPO_SYNC_OVERLAP_DAYS` | `1000` / `2` | change-feed paging and rewind |
| `EPPO_SYNC_INITIAL_SINCE` | `-1 year` | how far back a first sync looks |

## Where EPPO's API differs from its spec

Verified against the live API on 2026-08-29, and pinned by the live test suite:

- **An unknown but well-formed code answers `400 {"code":400,"error":"Bad request"}`, not 404.** Only the Reporting Service endpoints return 404. Since this package validates code shape before sending, both statuses implement `MissingRecord` — catch that rather than `NotFoundException`, and `exists()` already does.
- **`/reportings/list` takes undocumented `limit` and `offset`.** Without them you get the first 100 of 510-plus issues; above `limit=1000` the endpoint returns two rows instead of an error, so the client clamps there.
- **A photo's `tags` is an array**, not a string, and its renditions are named by pixel dimensions (`1024x0`, `220x130`) rather than size words — hence `largest()`, `thumbnail()` and a `url()` that defaults to the widest.
- **`preferred_name` in a search hit is null** for a name EPPO no longer prefers (`statuscode` `"N"`).
- **An issue can list the same article twice**; `ReportingIssueDetail` deduplicates by article id.
- **`/taxons/taxon/{code}/documents` returns `[]`** even when `infos.documents` is non-zero. That is upstream; the endpoint is wired up and will start returning data if EPPO fixes it.

## Data licence

The EPPO Global Database is published by EPPO under its own terms. This package is a client; using it does not grant you rights to the data. See [gd.eppo.int](https://gd.eppo.int) for terms and attribution requirements.

## Credits

Built for [Atlas Core](https://atlascore.cloud) and released for general use. Endpoint coverage is derived from EPPO's own OpenAPI document, vendored at `resources/openapi.yml`, and corrected against the live API where the two disagree.

`tests/Live` holds contract tests that run against the real database. They are skipped unless you give them a key:

```bash
EPPO_API_KEY=your-token vendor/bin/pest tests/Live
```

## Licence

MIT. See [LICENSE.md](LICENSE.md).
