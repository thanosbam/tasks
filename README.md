# tasks

A take-home assessment for Innovatiespotter (PHP Developer), built around one idea: **taking company
records that arrive from several different sources, cleaning them up, and collapsing them into a
single canonical list.**

Two of the four tasks were required (the PHP refactor and the PostgreSQL queries); the JavaScript
and PHP-plus-PostgreSQL tasks were optional and I took both on. Each file stands on its own — there
is no build step, no dependencies and no test suite, and the example inputs are hardcoded at the
bottom of each script so it can be run directly.

## The pipeline

```
   API_1        SCRAPER_2        MANUAL           <- raw sources, each with its own quirks
     |              |               |
     +--------------+---------------+
                    |
                    v
            companies (table)                     <- task2.sql: raw landing table, one row per scrape
                    |
                    |  1. group by LOWER(name) to find duplicates
                    |  2. rank duplicates by source trust
                    |  3. keep the single best row per company
                    v
      CompanyClass::normalizeCompanyData()        <- task1.php: field-level cleaning
                    |
                    v
        normalized_companies (table)              <- one row per real-world company (name is UNIQUE)
```

The same company shows up more than once because it was collected more than once. Two things have to
happen before it can be stored: the *record* must be deduplicated (which of these rows do we trust?)
and the *fields* must be normalized (is `" OpenAI "` the same as `"openai"`? is
`"https://openai.com "` the same as `"openai.com"`?).

The middle step is the one that isn't wired up yet. `task2.sql` currently inserts `name` and
`website` into `normalized_companies` exactly as they came out of `companies`, without passing them
through `normalizeCompanyData()` first. Connecting the two is the job of `task4.php`, which is the
unfinished file — see [Known gaps and planned work](#known-gaps-and-planned-work).

### Which duplicate wins

Sources are ranked by how much they can be trusted, most trusted first:

| Priority | Source pattern | Reasoning |
| -------- | -------------- | --------- |
| 1 | `MANUAL%` | entered by a human; treated as authoritative |
| 2 | `API%`    | structured data from an official endpoint |
| 3 | `SCRAPER%`| lifted off a page; the least reliable of the three |

Ties are broken by the earliest `inserted_at`. The patterns are matched with `ILIKE` on purpose, so
`MANUAL_3` or `API_7` slot into the right tier without changing the query.

## The tasks

### `task1.php` — refactoring the normalizer

This task supplied a working-but-broken `CompanyClass` and asked for it to be cleaned up. The
external contract was left alone — the quirks in how each field is treated are part of the original
design, not something I introduced — and the work went into the defects and the readability:

| Problem in the original | Fix |
| ----------------------- | --- |
| `preg_match(..., $cleanWebsite)` referenced a variable that was never defined | matches against the trimmed `$website` |
| `/http?:\/\//i` matched `htt` with an optional `p`, anywhere in the string | `/^https?:\/\//i` — correct optional `s`, anchored to the start |
| `isCompanyDataValid()` checked `isset($data[0])`, a numeric key no input ever has | checks `isset($data['name'])` |
| bare `return;` inside a method declared `?array` | explicit `return null;` |
| the website branch ran even when no website was supplied, then read a possibly-unset key | whole block guarded by `!empty($data['website'])` |
| `$c['website'] == null` — loose comparison | `empty()`, matching the intent of the check |
| the `address` condition had no braces | braced, with the empty-value case folded into it |
| single-letter accumulator `$c` | renamed `$normalized`, with comments on each rule |

The behaviour the refactor preserves:

- **name** — trimmed and lowercased. This is what makes two rows comparable at all.
- **website** — trimmed, and reduced to just the host (`https://openai.com ` -> `openai.com`) but
  *only* when the value really starts with `http://` or `https://`. Something like
  `xhttps://apple.com` is not a URL, so it is passed through untouched rather than mangled by
  `parse_url`. If cleaning leaves nothing behind, the key is dropped from the result entirely.
- **address** — trimmed, but a missing or whitespace-only address becomes an explicit `null` and the
  key is always present.
- A record is rejected outright unless both `name` and `address` keys exist.

The three test inputs at the bottom of the file cover each of those branches: a record with a
blank address, a clean record, and a record with a malformed website and no address (which returns
`null`).

### `task2.sql` — schema and deduplication

Defines both tables, then three queries:

1. **Find duplicates** — group by `LOWER(name)`, report how many times each company appears and
   which sources it came from.
2. **Deduplicate and load** — `ROW_NUMBER()` partitioned by `LOWER(name)`, ordered by the source
   priority above, then insert only the `rn = 1` row into `normalized_companies`.
3. **Source breakdown** — how many companies came from each source, most productive first.

`normalized_companies.name` is `UNIQUE`, which enforces at the schema level that deduplication
happened before the insert.

### `task3.js` — parallel download (optional task)

`downloadAndCombine(apiUrls)` fetches several JSON endpoints at once with `Promise.all` and returns
one flattened array. A failing endpoint logs its error and contributes an empty array instead of
rejecting, so one dead source cannot take down the whole ingest run. Uses the built-in `fetch`, so
it needs **Node 18+** and no packages.

### `task4.php` — wiring it to the database (optional task)

Connects to PostgreSQL over PDO, seeds the `companies` table with test rows if it is empty
(including a deliberate `OpenAI` duplicate from both `API_1` and `MANUAL`, so the priority rule has
something to resolve), and reads the rows back out. This is the file with the most left to do.

## Running

```bash
node task3.js                 # Node 18+, no install needed
php  task1.php                # prints a var_dump of the three normalization cases
psql -d <your_db> -f task2.sql
php  task4.php                # needs a running PostgreSQL
```

## Known gaps and planned work

Where I got to, and what I want to build out next.

**Finish `task4.php` end to end.** It currently stops after fetching the raw rows. The remaining
steps are to run each row through `normalizeCompanyData()`, keep the highest-priority row per
company, write the results back so the database holds only those records, and export the final set
to CSV. Getting there also means:

- splitting `CompanyClass` out of `task1.php` into its own `CompanyClass.php`, which the
  `require_once` at the top of `task4.php` already expects
- replacing the placeholder credentials (`your_db` / `your_user` / `your_pass`) with real config

**Match similar names, not just identical ones.** Duplicate detection currently keys on exact
lowercased names, so `"Acme BV"` and `"Acme B.V."` are still treated as two companies. Trigram
similarity via `pg_trgm` is the direction I'd take this, with a threshold tuned against real data —
the hard part is being confident that a near-match is genuinely the same company and not a
subsidiary or a similarly-named competitor.

**Normalize on the way into `normalized_companies`.** The insert in `task2.sql` carries `name` and
`website` across verbatim, so `canonical_website` is not yet canonical. Once the class is callable
from the loader this becomes part of the same pass.

**Containerize it.** A Docker setup bringing up PostgreSQL pre-loaded with the schema and a test
fixture would make the whole thing runnable in one command instead of needing a local database.
