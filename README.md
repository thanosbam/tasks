# tasks

A four-part exercise built around one idea: **taking company records that arrive from several
different sources, cleaning them up, and collapsing them into a single canonical list.**

Written as a take-home assessment for Innovatiespotter.

Each file answers one task and stands on its own — there is no build step, no dependencies and no
test suite. The example inputs are hardcoded at the bottom of each script so it can be run directly.

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

### `task1.php` — field normalization

`CompanyClass::normalizeCompanyData()` takes one raw record and returns the cleaned version, or
`null` if the record isn't usable. The rules are deliberately not symmetric:

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
   which sources it came from. Matching is on exact lowercased names. Near-matches are left alone by
   design: two companies with similar names are two different companies.
2. **Deduplicate and load** — `ROW_NUMBER()` partitioned by `LOWER(name)`, ordered by the source
   priority above, then insert only the `rn = 1` row into `normalized_companies`.
3. **Source breakdown** — how many companies came from each source, most productive first.

`normalized_companies.name` is `UNIQUE`, which enforces at the schema level that deduplication
happened before the insert.

### `task3.js` — parallel download

`downloadAndCombine(apiUrls)` fetches several JSON endpoints at once with `Promise.all` and returns
one flattened array. A failing endpoint logs its error and contributes an empty array instead of
rejecting, so one dead source cannot take down the whole ingest run. Uses the built-in `fetch`, so
it needs **Node 18+** and no packages.

### `task4.php` — wiring it to the database

Connects to PostgreSQL over PDO, seeds the `companies` table with test rows if it is empty
(including a deliberate `OpenAI` duplicate from both `API_1` and `MANUAL`, so the priority rule has
something to resolve), and reads the rows back out.

## Running

```bash
node task3.js                 # Node 18+, no install needed
php  task1.php                # prints a var_dump of the three normalization cases
psql -d <your_db> -f task2.sql
php  task4.php                # needs a running PostgreSQL
```

## Known gaps

`task4.php` is the unfinished one:

- It starts with `require_once 'CompanyClass.php'`, but that file doesn't exist — `CompanyClass`
  lives inline in `task1.php` and needs to be split out into its own file.
- The database credentials are still placeholders (`your_db` / `your_user` / `your_pass`).
- It stops after fetching the raw rows. The last step — running each row through
  `normalizeCompanyData()` and inserting the highest-priority row per company into
  `normalized_companies` — is not written.
