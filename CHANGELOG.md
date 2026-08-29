# Changelog

## 0.1.0 — 2026-08-29

First release. Covers every endpoint in EPPO Global Database API v2.0.4:
taxa, countries, RPPOs, the Reporting Service, reference tables and the search
tools. Verified against the live API on 2026-08-29.

### Client

- Typed readonly DTOs and `Collection`s for every response.
- `Eppo::taxon($code)->datasheet()` assembles the whole record — taxonomy,
  names, regulatory listings, distribution, hosts, vectors, standards, photos
  and Reporting Service history — into one `Datasheet`, skipping every section
  `/infos` reports as empty. EPPO's own prose datasheet is not exposed by
  API v2; this covers the same ground from the endpoints that are.
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
