# Changelog

## 0.1.0 — unreleased

First release. Covers every endpoint in EPPO Global Database API v2.0.4:
taxa, countries, RPPOs, the Reporting Service, reference tables and the search
tools.

- Two-tier cache: a Laravel store in front of a durable database table.
- Durable entries never hard-expire by default; they go stale and are
  revalidated in the background (stale-while-revalidate).
- `eppo:sync` invalidates only the codes EPPO reports as changed, instead of
  expiring the cache on a timer.
- Negative caching for 404s, stale-on-error fallback when EPPO is unreachable.
- Client-side throttle for the 2000 requests / 10 seconds per-IP limit, plus
  retries that honour `retry_after`.
- Six artisan commands, seven events, two queueable jobs.
