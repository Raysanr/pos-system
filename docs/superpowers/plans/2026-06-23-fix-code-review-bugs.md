# Code Review Bug Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix 10 confirmed/plausible bugs identified in the June 23 2026 code review, covering lock leaks in sync jobs, a null-batch success-path fallthrough, broken browser back-button behaviour, a poisoned geocoding cache, and missing update columns in reconciliation jobs.

**Architecture:** All fixes are surgical — no refactors, no new abstractions. Each task touches the minimum lines needed to close the defect. Backend fixes land in Jobs and Controllers; frontend fixes land in `layouts/app.blade.php` and the affected page blade scripts.

**Tech Stack:** Laravel 11, PHP 8.2, SQLite, Alpine.js, Chart.js 4, Tailwind CSS.

---

## Affected Files Overview

| File | Change type | Findings addressed |
|------|-------------|-------------------|
| `app/Jobs/SyncCustomersJob.php` | Modify | #1, #7 |
| `app/Jobs/SyncOrdersJob.php` | Modify | #2, #3 |
| `app/Http/Controllers/WebhookController.php` | Modify | #8 |
| `app/Http/Controllers/MapAnalyticsController.php` | Modify | #6 |
| `app/Jobs/RefreshOpenOrdersJob.php` | Modify | #9 |
| `app/Jobs/ReconcileOrdersJob.php` | Modify | #10 |
| `resources/views/layouts/app.blade.php` | Modify | #4, #5 |

---

## Task 1: Fix lock leak in SyncCustomersJob (finding #1) + restore last_synced_at (finding #7)

**What's wrong:**
- `SyncLog::create(...)` and the resume-key setup run *after* the lock is acquired but *before* the `try` block. If the DB throws, execution skips the `finally` → lock is never released → all future customer syncs blocked for 3 700 s.
- `$shop->update(['last_synced_at' => now()])` was removed from the success path and never replaced. Customer-only syncs no longer advance the incremental-sync anchor.

**Files:**
- Modify: `app/Jobs/SyncCustomersJob.php`

- [ ] **Step 1: Verify the current structure before touching anything**

Run:
```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
sed -n '25,82p' app/Jobs/SyncCustomersJob.php
```

Expected: You see `$lock->get()` at ~line 30, `SyncLog::create(...)` at ~line 35 (OUTSIDE try), and `try {` at ~line 48.

- [ ] **Step 2: Restructure handle() — move SyncLog::create and resume setup inside try, add last_synced_at update**

Replace the entire `handle()` method body. The corrected version:

```php
    public function handle(): void
    {
        $shop = PancakeShop::findOrFail($this->shopModelId);

        $lock = Cache::lock("sync_customers_lock_{$shop->id}", 3700);
        if (!$lock->get()) {
            Log::info("SyncCustomersJob: shop {$shop->shop_id} already syncing, skipping duplicate.");
            return;
        }

        try {
            $log = SyncLog::create([
                'shop_id'    => $shop->id,
                'type'       => 'customers',
                'status'     => 'running',
                'started_at' => now(),
            ]);

            $resumeKey = 'sync_customers_page_' . $shop->id;
            $startPage = (int) Cache::get($resumeKey, 1);

            $totalFetched  = 0;
            $totalUpserted = 0;

            $api = PancakeApiService::forShop($shop);

            foreach ($api->getAllCustomers(null, $startPage) as $page => $batch) {
                $totalFetched  += count($batch);
                $totalUpserted += $this->upsertBatch($shop->id, $batch);

                Cache::put($resumeKey, $page + 1, now()->addDays(7));
            }

            Cache::forget($resumeKey);
            $shop->update(['last_synced_at' => now()]);

            $log->update([
                'status'           => 'success',
                'records_fetched'  => $totalFetched,
                'records_upserted' => $totalUpserted,
                'finished_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            $resumedTo = isset($resumeKey) ? (int) Cache::get($resumeKey, $startPage ?? 1) : 1;
            Log::error(
                "SyncCustomersJob failed for shop {$shop->shop_id} " .
                "(next resume page={$resumedTo}): {$e->getMessage()}"
            );
            if (isset($log)) {
                $log->update([
                    'status'           => 'failed',
                    'records_fetched'  => $totalFetched  ?? 0,
                    'records_upserted' => $totalUpserted ?? 0,
                    'error_message'    => $e->getMessage(),
                    'finished_at'      => now(),
                ]);
            }
        } finally {
            $lock->release();
        }
    }
```

- [ ] **Step 3: Verify the file looks correct**

Run:
```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
sed -n '25,90p' app/Jobs/SyncCustomersJob.php
```

Expected: `SyncLog::create(...)` is now inside `try {`, `$lock->release()` is in `finally {`, and `$shop->update(['last_synced_at' => now()])` appears after `Cache::forget($resumeKey)` in the success path.

- [ ] **Step 4: Lint check**

```bash
php -l app/Jobs/SyncCustomersJob.php
```

Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
git add app/Jobs/SyncCustomersJob.php
git commit -m "fix(jobs): move SyncCustomersJob lock scope into try-finally; restore last_synced_at update"
```

---

## Task 2: Fix lock leak in SyncOrdersJob (finding #2)

**What's wrong:** Same structure as Task 1 — `SyncLog::create(...)` and resume-key setup at lines 43–53 are outside the `try` block that starts at line 55. If the DB throws here the lock is held for 3 700 s.

Note: The null-batch bug (finding #3) is fixed in Task 3. Do not fix both simultaneously — this task only moves the scope boundary.

**Files:**
- Modify: `app/Jobs/SyncOrdersJob.php`

- [ ] **Step 1: Confirm the current structure**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
sed -n '32,60p' app/Jobs/SyncOrdersJob.php
```

Expected: `SyncLog::create(...)` is at ~line 43 (OUTSIDE try), `try {` is at ~line 55.

- [ ] **Step 2: Move SyncLog::create and resume-key setup inside try**

Replace the `handle()` method preamble so that the `try` block opens immediately after the early-return guard:

```php
    public function handle(): void
    {
        $shop = PancakeShop::findOrFail($this->shopModelId);

        // One sync per shop at a time — TTL slightly longer than job timeout.
        $lock = Cache::lock("sync_orders_lock_{$shop->id}", 3700);
        if (!$lock->get()) {
            Log::info("SyncOrdersJob: shop {$shop->shop_id} already syncing, skipping.");
            return;
        }

        try {
            $log = SyncLog::create([
                'shop_id'    => $shop->id,
                'type'       => 'orders',
                'status'     => 'running',
                'started_at' => now(),
            ]);

            $resumeKey     = 'sync_orders_page_' . $shop->id . '_' . md5((string) $this->fromDate);
            $startPage     = (int) Cache::get($resumeKey, 1);
            $totalFetched  = 0;
            $totalUpserted = 0;

            $api     = PancakeApiService::forShop($shop);
            $filters = $this->fromDate ? ['from_date' => $this->fromDate] : [];
            $page    = $startPage;

            while (true) {
```

Keep everything from `while (true) {` to the end of `handle()` unchanged. Only the opening of `try {` moves up.

- [ ] **Step 3: Verify the structure**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
sed -n '32,70p' app/Jobs/SyncOrdersJob.php
```

Expected: `try {` appears directly after the early-return guard (no statements between the guard and `try`), and `SyncLog::create(...)` is the first statement inside `try`.

- [ ] **Step 4: Lint check**

```bash
php -l app/Jobs/SyncOrdersJob.php
```

Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
git add app/Jobs/SyncOrdersJob.php
git commit -m "fix(jobs): move SyncOrdersJob SyncLog::create inside try-finally to prevent lock leak"
```

---

## Task 3: Fix null-batch fallthrough in SyncOrdersJob (finding #3)

**What's wrong:** When `getOrderPagesBatch` returns `null` for page `$p`, the code executes `break 2` which exits both the `foreach` and the `while` loop and falls through to the *success* path at lines 93–101: `Cache::forget($resumeKey)` (erasing the resume cursor), `$shop->update(['last_synced_at' => now()])`, and `$log->update(['status' => 'success', ...])`. A partial API failure is therefore silently logged as a successful sync.

**Fix:** Replace the `break 2` in the null-batch branch with a `throw` so the exception is caught by the existing `catch (\Throwable $e)` block, which leaves the resume cursor intact and logs status `failed`.

**Files:**
- Modify: `app/Jobs/SyncOrdersJob.php`

- [ ] **Step 1: Locate the null-batch branch**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
grep -n "batch === null\|break 2\|resuming next" app/Jobs/SyncOrdersJob.php
```

Expected output shows three lines close together (the null check, the warning log, and `break 2`).

- [ ] **Step 2: Replace `break 2` with a throw**

Find this block (approximately lines 68–72):

```php
                    if ($batch === null) {
                        // Fetch failed — cursor already points to $p; next run resumes here.
                        Log::warning("SyncOrdersJob: fetch failed at page {$p}, resuming next run.");
                        break 2;
                    }
```

Replace with:

```php
                    if ($batch === null) {
                        // Throw so the catch block handles failure (keeps resume cursor, logs 'failed').
                        throw new \RuntimeException("Parallel fetch failed at page {$p} — will resume next run.");
                    }
```

- [ ] **Step 3: Verify there is no remaining `break 2` for the null-batch case**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
grep -n "batch === null" app/Jobs/SyncOrdersJob.php
```

Expected: one hit, and the line after it is the `throw`, not `break 2`.

- [ ] **Step 4: Lint check**

```bash
php -l app/Jobs/SyncOrdersJob.php
```

Expected: `No syntax errors detected`

- [ ] **Step 5: Manual trace — confirm the three exit paths behave correctly**

After the fix, trace each `break 2` path mentally:

| Path | Exit mechanism | Reaches `Cache::forget`? | Reaches `last_synced_at`? | Log status |
|------|---------------|--------------------------|--------------------------|------------|
| Empty batch (no more data) | `break 2` | ✅ yes | ✅ yes | success |
| Last partial page (`< PER_PAGE`) | `break 2` | ✅ yes | ✅ yes | success |
| Null batch (API failure) | `throw \RuntimeException` | ❌ no (caught before) | ❌ no | failed |

- [ ] **Step 6: Commit**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
git add app/Jobs/SyncOrdersJob.php
git commit -m "fix(jobs): throw on null batch in SyncOrdersJob to prevent false-success on API failure"
```

---

## Task 4: Add popstate handler for AJAX filter history (finding #4)

**What's wrong:** `history.pushState(null, '', url)` is called on every filter submit (line 405 of `app.blade.php`), but there is no `popstate` listener. When the user presses Back the URL reverts but the displayed data does not re-fetch — URL and data are permanently out of sync.

**Fix:** Add a `popstate` listener that re-runs the AJAX filter update using the URL restored by the browser.

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Find the exact location to insert the listener**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
grep -n "pushState\|popstate\|\.catch(function" resources/views/layouts/app.blade.php
```

Expected: `pushState` at line 405, no `popstate` hits.

- [ ] **Step 2: Insert the popstate listener after the AJAX interceptor IIFE closes**

Find the end of the AJAX interceptor IIFE. It ends with:
```js
    });
})();
```
That closing `})();` is immediately before the Live-accuracy comment block that starts:
```js
// ── Live accuracy: pulse polling + 5-min auto-reconcile ───────────────────────
```

Insert the following block **between** those two sections:

```js
// ── Browser Back/Forward support ──────────────────────────────────────────────
// When pushState is used, Back/Forward changes the URL but not the page content.
// This listener re-runs the current page's filter with the URL the browser restored.
window.addEventListener('popstate', function () {
    if (typeof window.__ajaxFilterUpdate !== 'function') return;
    var form = document.querySelector('.filter-form');
    if (!form) return;

    // Sync the form inputs from the restored URL params.
    var restoredParams = new URLSearchParams(window.location.search);
    restoredParams.forEach(function (val, key) {
        var el = form.querySelector('[name="' + key + '"]');
        if (el) el.value = val;
    });

    var params = new URLSearchParams(new FormData(form));
    var url    = window.location.href;
    var bar    = document.getElementById('filter-progress-bar');
    if (bar) bar.style.display = 'block';
    Promise.resolve(window.__ajaxFilterUpdate(form, params, url))
        .then(function () { if (bar) bar.style.display = 'none'; })
        .catch(function () { if (bar) bar.style.display = 'none'; window.location.reload(); });
});
```

- [ ] **Step 3: Verify the listener appears once and in the right place**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
grep -n "popstate\|pushState" resources/views/layouts/app.blade.php
```

Expected: `pushState` at ~405, `popstate` listener at the line just after the AJAX interceptor IIFE.

- [ ] **Step 4: Commit**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
git add resources/views/layouts/app.blade.php
git commit -m "fix(frontend): add popstate listener so Back/Forward button re-fetches correct filter data"
```

---

## Task 5: Add escape hatch for localStorage filter auto-redirect (finding #5)

**What's wrong:** The localStorage restore IIFE (line 333) calls `window.location.replace(...)` on every page load that has no query params. Users clicking a nav link to reach the default/reset view are immediately redirected back to their last saved filter — there is no opt-out.

**Fix:** Skip the restore when the navigation came from the same page (i.e., it is a user-initiated reset) by checking `document.referrer`. If the referrer path equals the current path, the user deliberately navigated to the clean URL and the restore should not fire.

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Locate the restore logic**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
grep -n "location.replace\|saved !== null\|localStorage.getItem" resources/views/layouts/app.blade.php
```

Expected: hits around lines 332–334.

- [ ] **Step 2: Add the referrer check**

Find this block (approximately lines 328–336):

```js
    } else {
        // No params — restore last-used filter for this page
        try {
            var saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
            if (saved !== null) {
                var qs = new URLSearchParams(saved).toString();
                if (qs) window.location.replace(window.location.pathname + '?' + qs);
            }
        } catch (e) {}
    }
```

Replace with:

```js
    } else {
        // No params — restore last-used filter, unless the user intentionally
        // navigated to this page without params (detected via same-origin referrer).
        try {
            var referrerPath = document.referrer
                ? new URL(document.referrer).pathname
                : null;
            var isReset = referrerPath === window.location.pathname;
            if (!isReset) {
                var saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
                if (saved !== null) {
                    var qs = new URLSearchParams(saved).toString();
                    if (qs) window.location.replace(window.location.pathname + '?' + qs);
                }
            }
        } catch (e) {}
    }
```

- [ ] **Step 3: Verify the change**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
grep -n "isReset\|referrerPath\|location.replace" resources/views/layouts/app.blade.php
```

Expected: `referrerPath`, `isReset`, and `location.replace` all appear together in the same small block.

- [ ] **Step 4: Commit**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
git add resources/views/layouts/app.blade.php
git commit -m "fix(frontend): skip localStorage filter restore when user navigates to reset view"
```

---

## Task 6: Prevent geocoding failure from poisoning the map cache (finding #6)

**What's wrong:** `GeocodingService::attachCoords` was moved *inside* `Cache::remember` (line 27–30 of `MapAnalyticsController.php`). If the geocoding service is temporarily rate-limited or unavailable, `attachCoords` returns null coordinates for some or all locations, and that null-coord payload is stored in cache for 1 800 s. Every subsequent map load in that 30-min window serves unplotted pins.

**Fix:** Wrap the `attachCoords` call in a try/catch. If geocoding throws or returns no data, skip caching the result for the current request (return directly from the closure but force a fresh attempt on the next request by busting the key).

The cleanest approach that doesn't change the architecture: validate the geocoding result inside the closure and throw an exception to prevent `Cache::remember` from storing a bad result (Laravel will not cache a thrown exception — the result is discarded and the closure re-runs on the next request).

**Files:**
- Modify: `app/Http/Controllers/MapAnalyticsController.php`

- [ ] **Step 1: Read the current closure**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
sed -n '27,31p' app/Http/Controllers/MapAnalyticsController.php
```

Expected:
```php
        $mapData  = Cache::remember($cacheKey, 1800, function () use ($shop, ...) {
            $data = $this->getLocationData(...);
            return app(GeocodingService::class)->attachCoords($data, $level, $provinceFilter);
        });
```

- [ ] **Step 2: Add null-result guard inside the closure**

Replace the closure body (keep all the `use` variables and the function signature unchanged):

```php
        $mapData  = Cache::remember($cacheKey, 1800, function () use ($shop, $dateFrom, $dateTo, $status, $level, $provinceFilter, $cityFilter, $productFilter) {
            $data    = $this->getLocationData($shop->id, $dateFrom, $dateTo, $status, $level, $provinceFilter, $cityFilter, $productFilter);
            $result  = app(GeocodingService::class)->attachCoords($data, $level, $provinceFilter);

            // If geocoding returned no coordinates at all and the data set is non-empty,
            // assume a transient geocoding failure — throw so Cache::remember does not store
            // the null-coord payload; next request will retry.
            $hasPoints = count($data) > 0;
            $hasCoords = !empty(array_filter($result, fn($r) => !empty($r['lat']) || !empty($r['lng'])));
            if ($hasPoints && !$hasCoords) {
                throw new \RuntimeException('Geocoding returned no coordinates — skipping cache.');
            }

            return $result;
        });
```

Note: The exception is intentionally uncaught here so `Cache::remember` discards the result. The exception **will** propagate to the controller unless we catch it. Add a catch around the `Cache::remember` call:

```php
        try {
            $mapData = Cache::remember($cacheKey, 1800, function () use ($shop, $dateFrom, $dateTo, $status, $level, $provinceFilter, $cityFilter, $productFilter) {
                $data   = $this->getLocationData($shop->id, $dateFrom, $dateTo, $status, $level, $provinceFilter, $cityFilter, $productFilter);
                $result = app(GeocodingService::class)->attachCoords($data, $level, $provinceFilter);

                $hasPoints = count($data) > 0;
                $hasCoords = !empty(array_filter($result, fn($r) => !empty($r['lat']) || !empty($r['lng'])));
                if ($hasPoints && !$hasCoords) {
                    throw new \RuntimeException('Geocoding returned no coordinates — skipping cache.');
                }

                return $result;
            });
        } catch (\RuntimeException $e) {
            // Transient geocoding failure — serve raw location data without coordinates
            // so the request still succeeds; the next request will retry geocoding.
            Log::warning("MapAnalyticsController: {$e->getMessage()}");
            $mapData = $this->getLocationData($shop->id, $dateFrom, $dateTo, $status, $level, $provinceFilter, $cityFilter, $productFilter);
        }
```

- [ ] **Step 3: Verify the structure**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
sed -n '27,55p' app/Http/Controllers/MapAnalyticsController.php
```

Expected: `try {` wraps `Cache::remember`, `catch (\RuntimeException $e)` handles the transient failure, `$mapData` is always assigned.

- [ ] **Step 4: Check for missing `Log` import**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
grep "use Illuminate\\\\Support\\\\Facades\\\\Log" app/Http/Controllers/MapAnalyticsController.php
```

If the import is missing, add it to the `use` block at the top of the file:
```php
use Illuminate\Support\Facades\Log;
```

- [ ] **Step 5: Lint check**

```bash
php -l app/Http/Controllers/MapAnalyticsController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
git add app/Http/Controllers/MapAnalyticsController.php
git commit -m "fix(map): prevent transient geocoding failure from poisoning 30-min cache entry"
```

---

## Task 7: Fix array_filter stripping falsy order IDs in WebhookController (finding #8)

**What's wrong:** `array_filter([..., $payload['order_id'] ?? null, ...])` uses the default callback which removes all falsy values. `order_id: 0` or `order_id: '0'` would be silently dropped, causing the webhook to fall back to a 15-minute window sync instead of a targeted refresh.

**Fix:** Pass an explicit callback to `array_filter` that only removes `null` values.

**Files:**
- Modify: `app/Http/Controllers/WebhookController.php`

- [ ] **Step 1: Locate the array_filter call**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
grep -n "array_filter\|array_unique\|array_values" app/Http/Controllers/WebhookController.php
```

Expected: one hit at ~line 48.

- [ ] **Step 2: Add explicit null-only callback**

Find:
```php
        $orderIds = array_values(array_unique(array_filter([
            $payload['order_id']        ?? null,
            $payload['id']              ?? null,
            $payload['data']['id']      ?? null,
            $payload['data']['order_id'] ?? null,
        ])));
```

Replace with:
```php
        $orderIds = array_values(array_unique(array_filter([
            $payload['order_id']        ?? null,
            $payload['id']              ?? null,
            $payload['data']['id']      ?? null,
            $payload['data']['order_id'] ?? null,
        ], fn($v) => $v !== null)));
```

- [ ] **Step 3: Lint check**

```bash
php -l app/Http/Controllers/WebhookController.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
git add app/Http/Controllers/WebhookController.php
git commit -m "fix(webhook): use explicit null-only callback in array_filter to preserve order_id 0"
```

---

## Task 8: Add items to RefreshOpenOrdersJob update columns (finding #9)

**What's wrong:** `RefreshOpenOrdersJob::upsertOne` fetches the full order from Pancake's individual-order endpoint (`getOrderById`) including any corrected `items` array, but the upsert column list does not include `items`. Product corrections on open orders are silently dropped.

**Files:**
- Modify: `app/Jobs/RefreshOpenOrdersJob.php`

- [ ] **Step 1: Locate the upsert call**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
grep -n "Order::upsert\|'items'" app/Jobs/RefreshOpenOrdersJob.php
```

Expected: `Order::upsert([...])` at ~line 149 and `'items'` in the `$record` mapping above it (not in the update columns).

- [ ] **Step 2: Add items and health_condition to the update column list**

Find:
```php
        Order::upsert([$record], ['shop_id', 'pancake_id'], [
            'status', 'shipping_status', 'courier', 'tracking_code',
            'is_rts', 'is_returned', 'is_cancelled',
            'return_reason', 'total_price', 'cod_amount',
            'province', 'district', 'ward', 'updated_at',
        ]);
```

Replace with:
```php
        Order::upsert([$record], ['shop_id', 'pancake_id'], [
            'status', 'shipping_status', 'courier', 'tracking_code',
            'is_rts', 'is_returned', 'is_cancelled',
            'return_reason', 'total_price', 'cod_amount',
            'province', 'district', 'ward',
            'items', 'health_condition', 'updated_at',
        ]);
```

`health_condition` is included because it is derived from `items`/notes and should stay consistent with an updated order.

- [ ] **Step 3: Lint check**

```bash
php -l app/Jobs/RefreshOpenOrdersJob.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
git add app/Jobs/RefreshOpenOrdersJob.php
git commit -m "fix(jobs): add items and health_condition to RefreshOpenOrdersJob upsert update columns"
```

---

## Task 9: Add discount and shipping_fee to ReconcileOrdersJob update columns (finding #10)

**What's wrong:** `ReconcileOrdersJob::upsertBatch` runs every 5 minutes but its update-column list excludes `discount` and `shipping_fee`. If a Pancake agent corrects a shipping fee on a recently-created order, the corrected value is ignored until the next hourly `SyncOrdersJob` run.

**Files:**
- Modify: `app/Jobs/ReconcileOrdersJob.php`

- [ ] **Step 1: Locate the upsert call**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
grep -n "Order::upsert" app/Jobs/ReconcileOrdersJob.php
```

Expected: one hit at ~line 142.

- [ ] **Step 2: Add discount, shipping_fee, extra_note, and customer_age to the update column list**

Find:
```php
            Order::upsert($records, ['shop_id', 'pancake_id'], [
                'status', 'shipping_status', 'courier', 'tracking_code',
                'is_rts', 'is_returned', 'is_cancelled',
                'return_reason', 'total_price', 'cod_amount',
                'province', 'district', 'ward',
                'customer_name', 'customer_phone',
                'items', 'utm_data', 'ordered_at', 'updated_at',
            ]);
```

Replace with:
```php
            Order::upsert($records, ['shop_id', 'pancake_id'], [
                'status', 'shipping_status', 'courier', 'tracking_code',
                'is_rts', 'is_returned', 'is_cancelled',
                'return_reason', 'total_price', 'shipping_fee', 'discount', 'cod_amount',
                'province', 'district', 'ward',
                'customer_name', 'customer_phone',
                'extra_note', 'customer_age', 'health_condition',
                'items', 'utm_data', 'ordered_at', 'updated_at',
            ]);
```

- [ ] **Step 3: Lint check**

```bash
php -l app/Jobs/ReconcileOrdersJob.php
```

Expected: `No syntax errors detected`

- [ ] **Step 4: Commit**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
git add app/Jobs/ReconcileOrdersJob.php
git commit -m "fix(jobs): add discount, shipping_fee, and note fields to ReconcileOrdersJob upsert update columns"
```

---

## Final Verification

- [ ] **Run a full PHP lint sweep across all modified files**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
php -l app/Jobs/SyncCustomersJob.php
php -l app/Jobs/SyncOrdersJob.php
php -l app/Jobs/RefreshOpenOrdersJob.php
php -l app/Jobs/ReconcileOrdersJob.php
php -l app/Http/Controllers/WebhookController.php
php -l app/Http/Controllers/MapAnalyticsController.php
```

Expected: `No syntax errors detected` for each.

- [ ] **Confirm all 9 commits are in git log**

```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
git log --oneline -10
```

Expected: 9 `fix(...)` commits, most recent at top.

- [ ] **Smoke test: visit the Dashboard, Customer Analytics, and Map pages in the browser to confirm no PHP errors appear**

Manually navigate to each page. Check the Laravel log for exceptions:
```bash
cd "/Users/junioraiengineer/POS SYSTEM/pancake-analytics"
tail -50 storage/logs/laravel.log
```

Expected: No new exception entries from the modified controllers.

---

## Self-Review Checklist

**Spec coverage (10 findings → 9 tasks, 2 findings per Task 1):**

| Finding | Task | Status |
|---------|------|--------|
| #1 SyncCustomersJob lock leak | Task 1 | ✅ |
| #7 SyncCustomersJob last_synced_at removed | Task 1 | ✅ |
| #2 SyncOrdersJob lock leak | Task 2 | ✅ |
| #3 Null-batch success-path fallthrough | Task 3 | ✅ |
| #4 pushState without popstate | Task 4 | ✅ |
| #5 localStorage traps users | Task 5 | ✅ |
| #6 Geocoding poisons cache | Task 6 | ✅ |
| #8 array_filter strips falsy IDs | Task 7 | ✅ |
| #9 RefreshOpenOrdersJob missing items | Task 8 | ✅ |
| #10 ReconcileOrdersJob missing discount/fee | Task 9 | ✅ |

**No placeholders:** All code blocks are complete and copy-pasteable.

**Type consistency:** `$lock`, `$log`, `$resumeKey`, `$startPage`, `$totalFetched`, `$totalUpserted` are defined before use in all modified methods. The `$mapData` variable is assigned in both the try and catch paths.
