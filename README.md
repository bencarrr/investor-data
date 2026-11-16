## Running the project

Very Standard Laravel Install, use a sql DB of your choice.

You need PHP 8.3+, Composer and a database (MySQL, or SQLite for a quick local run).

1. Install dependencies and create your `.env`:
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```
2. Set the `DB_*` values in `.env` for your database, then create the tables:
   ```bash
   php artisan migrate
   ```
3. Start the app and a queue worker, each in its own terminal:
   ```bash
   php artisan serve
   php artisan queue:work
   ```
4. Import the sample file, then check the result at the URL in the response's `Location` header:
   ```bash
   curl -i -X POST http://localhost:8000/api/v1/imports -H "Accept: application/json" -F "file=@tests/Fixtures/investors.csv"
   ```
5. Run the tests:
   ```bash
   php artisan test
   ```

## Setup Notes

**Queueing**
Imports run on the queue, so keep a worker running with `php artisan queue:work`. Without one, imports stay `pending`. `DB_QUEUE_RETRY_AFTER` (660 in `.env.example`) must be longer than the job's 600-second timeout, or a slow import can be picked up twice.

**Import File Sizes**
`POST /api/v1/imports` accepts CSVs up to 20 MB, but every layer in front of Laravel has its own upload limit and the smallest one wins. Raise PHP's limits (`upload_max_filesize = 20M`, `post_max_size = 21M`; the default is 2 MB) and your web server's, e.g. for nginx add `client_max_body_size 21m;` to the location that passes the import request to PHP (the default is 1 MB).
Also extend the timeout in your environment

## Endpoints

All endpoints are under `/api/v1` and return JSON (validation errors return `422`).

No Authentication required.

### `POST /api/v1/imports` — import a CSV

Multipart upload with a `file` field. The file must be detected as CSV (`mimes:csv`), be at most 20 MB, and have exactly this header:

```
investor_id,name,age,investment_amount,investment_date
```

```bash
curl -X POST http://localhost:8000/api/v1/imports \
  -H "Accept: application/json" \
  -F "file=@tests/Fixtures/investors.csv"
```

The file is stored and queued, and the response is `202 Accepted` with a `Location` header pointing at the import's status:

```json
{
  "data": {
    "id": 1,
    "status": "pending",
    "total_rows": null,
    "imported_rows": 0,
    "failed_rows": 0,
    "errors": [],
    "failure_reason": null,
    "created_at": "...",
    "updated_at": "..."
  }
}
```

### `GET /api/v1/imports/{id}` — import status

`status` moves from `pending` to `processing`, then to `completed` or `failed`. When it's `completed`, the counts and row errors are filled in. When it's `failed`, `failure_reason` explains why.

```json
{
  "data": {
    "id": 1,
    "status": "completed",
    "total_rows": 200,
    "imported_rows": 200,
    "failed_rows": 0,
    "errors": [],
    "failure_reason": null,
    "created_at": "...",
    "updated_at": "..."
  }
}
```

Rows are checked one at a time:

| Column | Rule |
|---|---|
| `investor_id` | required, integer, ≥ 1 |
| `name` | required, string, ≤ 255 characters |
| `age` | required, integer, 18–120 |
| `investment_amount` | required, number > 0, at most 2 decimal places |
| `investment_date` | required, `d-m-Y` (e.g. `13-11-2024`), not in the future |

Invalid rows are skipped and listed under `errors` with their line number (the header is line 1). `failed_rows` counts every invalid row; `errors` keeps the first 100.

### `GET /api/v1/stats/average-age`

```json
{ "data": { "average_age": 47 } }
```

### `GET /api/v1/stats/average-investment-amount`

```json
{ "data": { "average_investment_amount": 541234.56 } }
```

### `GET /api/v1/stats/total-investments`

```json
{ "data": { "total_investments": 200 } }
```

Averages are `null` when there is no data.

### `GET /api/v1/investors` — list investors

Query parameters: `per_page` (1–1000, default 100), `cursor`, `format` (`json` or `csv`).

```json
{
  "data": [
    { "investor_id": 1001, "name": "Daniel Nelson", "age": 28, "total_investment_amount": "328085.43" }
  ],
  "links": { "first": null, "last": null, "prev": null, "next": "http://localhost:8000/api/v1/investors?cursor=..." },
  "meta": { "path": "...", "per_page": 100, "next_cursor": "...", "prev_cursor": null }
}
```

For CSV, send `?format=csv` or `Accept: text/csv`. The CSV contains every investor (it is not paginated):

```bash
curl http://localhost:8000/api/v1/investors?format=csv -o investors.csv
```

## Design

- **Service layer.** Controllers only handle HTTP. The work is done by `InvestorImportService`, `InvestorStatisticsService` and `InvestorExportService` in `app/Services`.
- **Schema.**
  - `investors` uses the file's `investor_id` as its primary key.
  - `investments` belongs to an investor and is unique on `(investor_id, invested_on)`, so there is one investment per investor per date.
- **Large files (10k+ rows).**
  - The upload request only checks the file type, size and header, stores the file, and queues `ProcessInvestorImport`. It returns immediately, so large files don't hit request timeouts.
  - The job tries up to 3 times. Retrying is safe because the import upserts, so re-running a file never duplicates data. When the import finishes, the stored file is deleted.
  - The import streams the file one line at a time with `SplFileObject`, so the whole file is never held in memory.
  - Valid rows are bulk-upserted 1,000 at a time, two queries per chunk, each chunk in its own transaction.
- **Reads at scale.**
  - Statistics are single SQL aggregates (`AVG` / `COUNT`).
  - The investor list uses cursor pagination, which stays fast deep into the list, and `withSum` to avoid N+1 queries.
  - The CSV export streams rows using `lazyById`, so memory stays flat.

## Assumptions

- If the same investor and date appear more than once, the **last row wins**. The same applies to an investor's name and age. Importing a file again updates existing data instead of duplicating it.
- "Average investment amount" is the mean over all investment rows. In the sample file every investor has one investment, so this equals the mean per investor.
- An investor's "investment amount" in the listing is the sum of their investments.
- Uploads are stored on the default filesystem disk. The worker must be able to read that disk, so with several servers it should be shared storage such as S3.
- Imports can run at the same time. If two files touch the same investor, whichever finishes later wins.

## With more time

- **Validate the whole file before writing.** Today the file is read once and the last row for an investor and date wins silently. I would instead:
  1. Load rows into a staging table (`import_rows`).
  2. Use grouped queries to find any date repeated for the same investor (`GROUP BY investor_id, invested_on HAVING COUNT(*) > 1`) and any investor whose name or age changes between rows.
  3. Reject the whole file if anything is found, listing every conflict with its line numbers.
  4. Write a clean file ordered by `investor_id`, so each chunk holds every row for its investors, all in one transaction.

  This makes imports all-or-nothing and stops data from being overwritten without notice.
- **Atomic imports.** A failure partway through currently leaves earlier chunks committed. A retry re-runs the whole file, so the data ends up correct, but other endpoints can see partial data in the meantime.
- **Progress and notifications.** Update the import's counts after each chunk so clients can show progress, and send a webhook when an import finishes instead of making clients poll.
- **Serialise imports.** Run imports one at a time (a dedicated queue with one worker, or `WithoutOverlapping`) so two files touching the same investors can't overwrite each other unpredictably or deadlock.
- **Clean up old uploads** left behind by failed imports.
- **Cache the statistics** and clear the cache when an import completes.
- **Authentication and rate limiting** (e.g. Sanctum).
- **Stricter CSV detection.** `mimes:csv` inspects the file's content, so an unusual CSV (e.g. a single column) can be detected as plain text and rejected.
- **Very large files.** Use larger chunks or `LOAD DATA INFILE` for millions of rows.
- **Unfinished imports show in stats** While an import is running, the stats and investor endpoints already include the chunks it has saved so far.
