# Changelog

## 0.2.0 — 2026-08-29

### Added

- `Eppo::taxon($code)->datasheet()` assembles the whole record for a taxon —
  taxonomy, names, regulatory listings, distribution, hosts, pests, vectors in
  both directions, biological control agents, standards, documents, photos and
  Reporting Service history — into one `Datasheet`.

  EPPO writes prose datasheets for its quarantine pests but API v2 does not
  expose them: `/infos` reports `datasheet: 1` and `/taxons/taxon/{code}/
  datasheet` answers 404 "Route not found". Same for `expertise`, `specimens`,
  `eppolinks`, `pathwaypest` and `pathwayhost` — counted, never fetchable.
  This covers the same ground from the endpoints that are exposed.

  The `/infos` call it makes up front keeps it cheap: any section EPPO reports
  as empty is never requested, so a sparse taxon costs five calls rather than
  sixteen, and `$sheet->fetched` says which ones it made. Pass a section list
  to narrow it further.

- `Datasheet` accessors that do the grouping a reader wants: `hostsByClass()`,
  `majorHosts()`, `currentListings()` (listings not since withdrawn),
  `distributionByStatus()`, `namesByLanguage()`, `countries()`, `sections()`,
  `counts()`, and `kingdom()` / `rank()` read off the taxonomy chain rather
  than a second request.

### Fixed

- Configuration set before the service provider registered replaced the whole
  block it belonged to, because Laravel's `mergeConfigFrom` is a shallow
  `array_merge`. An application setting `eppo.cache.enabled` from an early
  bootstrapper silently lost every TTL, the L1 settings and the table names —
  and kept working, on `ttlFor()`'s 90-day fallback. Nested associative arrays
  now merge recursively; lists are still replaced wholesale.

- The Atlas Core credit now links to https://atlascore.cloud.

## 0.1.0 — 2026-08-29

First release. Covers every endpoint in EPPO Global Database API v2.0.4:
taxa, countries, RPPOs, the Reporting Service, reference tables and the search
tools. Verified against the live API on 2026-08-29.

### Client

- Typed readonly DTOs and `Collection`s for every response.
- Retries for 429/5xx honouring `retry_after`, failover to secondary servers,
  and a shared client-side throttle for the 2000 requests / 10 seconds per-IP
  limit.
- Exceptions mirror HTTP status, with a `MissingRecord` marker on the two that
  mean "EPPO holds no such record".

### Cache — off by default

- `EPPO_CACHE=true` enables two tiers: a Laravel cache store in front of two
  tables in your own database. Table names and connection are configurable;
  the migrations are only registered while the cache is on.
- Durable rows never hard-expire. They go stale and are revalidated in the
  background (stale-while-revalidate), under a lock so concurrent readers
  cause one refetch.
- `eppo:sync` reads EPPO's change feed and invalidates only the codes that
  moved, rather than expiring the cache on a timer.
- Negative caching, stale-on-error fallback, per-resource TTLs with wildcards,
  and a `cache.version` prefix that orphans everything without deleting a row.
- Six artisan commands, eight events, two queueable jobs.

### Corrections to EPPO's published spec

Found by running against the live API; each is pinned by a test.

- An unknown but well-formed code answers 400, not 404.
- `/reportings/list` accepts undocumented `limit`/`offset` and misbehaves
  above `limit=1000`.
- A photo's `tags` is an array; renditions are named by pixel dimensions.
- `preferred_name` in a search hit can be null.
- An issue can list the same article twice.
