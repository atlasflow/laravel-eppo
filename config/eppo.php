<?php

declare(strict_types=1);

use Atlasflow\Eppo\Cache\Ttl;

return [

    /*
    |--------------------------------------------------------------------------
    | API credentials
    |--------------------------------------------------------------------------
    |
    | Generate a token from the dashboard at https://data.eppo.int. It is sent
    | on every request as the `X-Api-Key` header.
    |
    */

    'key' => env('EPPO_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Endpoint
    |--------------------------------------------------------------------------
    |
    | `base_url` is the primary server from the EPPO OpenAPI document. Any URLs
    | in `fallback_urls` are tried, in order, when the primary is unreachable
    | (connection error or 5xx) — never for 4xx, which are answers, not faults.
    |
    */

    'base_url' => env('EPPO_BASE_URL', 'https://api.eppo.int/gd/v2'),

    'fallback_urls' => array_values(array_filter(explode(',', (string) env('EPPO_FALLBACK_URLS', '')))),

    'timeout' => (int) env('EPPO_TIMEOUT', 15),

    'connect_timeout' => (int) env('EPPO_CONNECT_TIMEOUT', 5),

    'user_agent' => env('EPPO_USER_AGENT', 'atlasflow/laravel-eppo'),

    /*
    |--------------------------------------------------------------------------
    | Retries
    |--------------------------------------------------------------------------
    |
    | Applies to connection errors, 429 and 5xx. A 429 always waits for the
    | server's `retry_after` / `Retry-After` value when one is present, and
    | falls back to exponential backoff otherwise.
    |
    */

    'retry' => [
        'times' => (int) env('EPPO_RETRY_TIMES', 3),
        'base_delay_ms' => (int) env('EPPO_RETRY_BASE_DELAY', 250),
        'max_delay_ms' => (int) env('EPPO_RETRY_MAX_DELAY', 10000),
        'jitter' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Client-side throttle
    |--------------------------------------------------------------------------
    |
    | EPPO allows 2000 requests per IP in a 10 second sliding window. This keeps
    | us underneath it across all processes sharing the `store` below. Set
    | `enabled` to false if you throttle elsewhere.
    |
    */

    'throttle' => [
        'enabled' => (bool) env('EPPO_THROTTLE', true),
        'max_requests' => (int) env('EPPO_THROTTLE_MAX', 1800),
        'per_seconds' => (int) env('EPPO_THROTTLE_WINDOW', 10),
        'store' => env('EPPO_THROTTLE_STORE'), // null = default cache store
        'max_wait_seconds' => (int) env('EPPO_THROTTLE_MAX_WAIT', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Caching is OFF by default. Every read goes to EPPO until you turn it on:
    |
    |     EPPO_CACHE=true
    |     php artisan vendor:publish --tag=eppo-migrations
    |     php artisan migrate
    |
    | Once on, two tiers sit in front of the API:
    |
    |   L1  a normal Laravel cache store — short TTL, absorbs repeated reads
    |       inside a request or between nearby requests.
    |   L2  the durable store — a table in your database (`eppo_cache_entries`
    |       by default) meant to be kept for years. EPPO codes are effectively
    |       immutable, so rows never hard-expire; they go *stale* and are
    |       revalidated, not evicted.
    |
    | A read that finds a stale L2 row returns it immediately and queues a
    | refresh (stale-while-revalidate), so a user request never pays for a
    | revalidation. See `eppo:sync` for change-driven invalidation.
    |
    */

    'cache' => [

        /*
         | The master switch. False means no caching of any kind, and no
         | database table is needed or created.
         */
        'enabled' => (bool) env('EPPO_CACHE', false),

        /*
         | Bump this to orphan every existing entry at once — the version is
         | part of the cache key. Old rows stay until `eppo:cache:prune` runs,
         | so a bump is instantly reversible.
         */
        'version' => env('EPPO_CACHE_VERSION', 'v1'),

        'l1' => [
            'enabled' => (bool) env('EPPO_CACHE_L1', true),
            'store' => env('EPPO_CACHE_L1_STORE'), // null = default cache store
            'prefix' => 'eppo',
            'ttl' => (int) env('EPPO_CACHE_L1_TTL', 3600), // seconds
        ],

        /*
         | The durable tier: two tables in your database. Point them at another
         | connection, or rename them, if they collide with something you own.
         | Migrations are only registered while `enabled` is true above and
         | here — nothing is created for an application that does not cache.
         */
        'durable' => [
            'enabled' => (bool) env('EPPO_CACHE_DURABLE', true),
            'connection' => env('EPPO_CACHE_CONNECTION'), // null = default connection
            'table' => env('EPPO_CACHE_TABLE', 'eppo_cache_entries'),
            'sync_table' => env('EPPO_CACHE_SYNC_TABLE', 'eppo_sync_state'),

            /*
             | gzip payloads before storing. Roughly halves the table at the
             | cost of a compress/decompress per miss. Requires ext-zlib.
             */
            'compress' => (bool) env('EPPO_CACHE_COMPRESS', false),

            /*
             | Also persist 404s. EPPO codes that do not exist rarely start
             | existing, and caching the absence stops hot loops hammering the
             | API. Stale time for these is `ttl.negative`.
             */
            'cache_misses' => (bool) env('EPPO_CACHE_MISSES', true),
        ],

        /*
         | Stale-while-revalidate. When a stale entry is served, a refresh job
         | is pushed onto `queue` on `connection`. Set `enabled` to false to
         | revalidate inline (blocking) instead.
         */
        'revalidate' => [
            'enabled' => (bool) env('EPPO_CACHE_SWR', true),
            'connection' => env('EPPO_CACHE_SWR_CONNECTION'),
            'queue' => env('EPPO_CACHE_SWR_QUEUE'),
            'lock_seconds' => 60,
        ],

        /*
         | When the API cannot be reached and a stale durable entry exists,
         | serve the stale copy rather than throwing. This is what makes the
         | package usable offline once warm.
         */
        'serve_stale_on_error' => (bool) env('EPPO_CACHE_SERVE_STALE', true),

        /*
        |----------------------------------------------------------------------
        | Time to stale, per resource
        |----------------------------------------------------------------------
        |
        | Keys are resource identifiers; `*` matches a whole group. A value of
        | `null` means "never goes stale" — the entry is only replaced when
        | something invalidates it explicitly (`eppo:sync`, `Eppo::cache()`
        | calls, or a version bump).
        |
        | Values are seconds; the Ttl helper is only there for readability.
        |
        */
        'ttl' => [
            'default' => Ttl::days(90),

            'status' => Ttl::minutes(1),

            'taxon.overview' => Ttl::days(180),
            'taxon.names' => Ttl::days(180),
            'taxon.taxonomy' => Ttl::days(180),
            'taxon.kingdom' => Ttl::days(365),
            'taxon.infos' => Ttl::days(30),
            'taxon.categorization' => Ttl::days(30),
            'taxon.distribution' => Ttl::days(30),
            'taxon.hosts' => Ttl::days(90),
            'taxon.pests' => Ttl::days(90),
            'taxon.*' => Ttl::days(90),

            'taxons.list' => Ttl::hours(6),

            'country.*' => Ttl::days(30),
            'rppo.*' => Ttl::days(90),

            'reportings.list' => Ttl::days(7),
            'reportings.issue' => Ttl::days(365),
            'reportings.article' => Ttl::days(365),

            'references.*' => Ttl::days(365),

            'tools.name2codes' => Ttl::days(30),
            'tools.search' => Ttl::days(7),

            // Applied to cached 404s (see durable.cache_misses).
            'negative' => Ttl::days(7),
        ],

        /*
        |----------------------------------------------------------------------
        | Hard expiry
        |----------------------------------------------------------------------
        |
        | `null` keeps rows forever — the durable default, and the reason this
        | package exists. Set a number of seconds if you would rather rows be
        | deletable by `eppo:cache:prune` some time after their last hit.
        |
        */
        'keep_for' => env('EPPO_CACHE_KEEP_FOR') === null
            ? null
            : (int) env('EPPO_CACHE_KEEP_FOR'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Change-driven invalidation
    |--------------------------------------------------------------------------
    |
    | `eppo:sync` asks EPPO which codes changed since the last run
    | (`/taxons/list?updatedFromDate=`) and invalidates only those. This is the
    | correct way to bust this cache: it touches the handful of codes that
    | actually moved instead of throwing away years of warm data.
    |
    */

    'sync' => [
        'page_size' => (int) env('EPPO_SYNC_PAGE_SIZE', 1000),

        // How far back the very first sync looks when no state row exists.
        'initial_since' => env('EPPO_SYNC_INITIAL_SINCE', '-1 year'),

        // Re-fetch invalidated resources immediately instead of leaving holes.
        'refresh' => (bool) env('EPPO_SYNC_REFRESH', false),

        // Overlap window, in days, subtracted from the last sync date to
        // absorb EPPO publishing a change dated slightly in the past.
        'overlap_days' => (int) env('EPPO_SYNC_OVERLAP_DAYS', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Warming
    |--------------------------------------------------------------------------
    |
    | Resources `eppo:cache:warm` fetches for each code when `--with` is not
    | given.
    |
    */

    'warm' => [
        'resources' => ['overview', 'names', 'taxonomy', 'categorization', 'distribution', 'hosts', 'pests'],
    ],
];
