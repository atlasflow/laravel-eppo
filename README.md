# laravel-eppo

A Laravel client for the [EPPO Global Database](https://gd.eppo.int) — the European and Mediterranean Plant Protection Organization's register of plants, pests, pathogens and their regulatory status — backed by a durable local cache.

EPPO codes are stable identifiers. `BEMITA` has meant *Bemisia tabaci* since 2002 and will still mean it in twenty years. A cache in front of that data should be measured in months, not minutes, and should survive a Redis restart, a deploy, and a machine rebuild. That is what this package gives you: a database-backed store that never expires by default, revalidates in the background, and is invalidated by asking EPPO what actually changed.

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
php artisan migrate
```

```dotenv
EPPO_API_KEY=your-token
```

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

Two tiers sit in front of the API.

| | Where | Lifetime | Purpose |
|---|---|---|---|
| **L1** | any Laravel cache store | 1 hour | absorbs repeated reads |
| **L2** | `eppo_cache_entries` table | forever | the durable copy |

A read tries L1, then L2, then EPPO. A write fills both.

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

To take the cache out of the picture entirely, set `EPPO_CACHE=false`.

## Data licence

The EPPO Global Database is published by EPPO under its own terms. This package is a client; using it does not grant you rights to the data. See [gd.eppo.int](https://gd.eppo.int) for terms and attribution requirements.

## Credits

Built for [Atlas Core](https://github.com/atlasflow) and released for general use. Endpoint coverage is generated from EPPO's own OpenAPI document, vendored at `resources/openapi.yml`.

## Licence

MIT. See [LICENSE.md](LICENSE.md).
