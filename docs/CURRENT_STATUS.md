# Current Status

## Owner Dashboard (Developer Preview)

- The Home route now renders a clean owner-level business dashboard for the Developer role; all other roles retain the existing welcome home.
- Dashboard totals are provided by a reusable `OwnerDashboardService`, use existing customer/supplier/order/stock balance rules, and are cached for one minute with an explicit refresh option.
- The dashboard includes current receivables, payables, cash/bank and net position; period sales, collections and expenses; pending orders; finished stock; material inventory; production; due/action items; branch comparison; and recent activity.
- Disabled client modules do not render their corresponding dashboard cards. Branch comparison respects per-branch module state and Main Branch legacy `NULL branch_id` inclusion.
- Date changes update the inner dashboard without a page reload. Detail views use the app's existing reusable modal utility, and loading state always clears on success or failure.
- No schema migration was required.

## Completed

- Dynamic Branch Module Registry with 50 modules.
- Route-aware global branch switcher in `app.blade.php`.
- Hidden branch preference form outside page forms.
- No manual selector includes on individual pages.
- Main Branch defaults all modules enabled but Developer can disable.
- No locked modules.
- Record Filtering checkbox exists and is saved in metadata.
- Branch Module Management has a searchable all-module overview with visible client, branching, switcher, multi-branch, and record-filtering states.
- Overview chips (`All`, `Branch`, `Filter`, `Off`) filter the module list live and combine with module search.
- Switching between modules on Branch Module Management updates only the detail panel instead of refreshing the full page.
- `shouldFilterRecords()` requires usable selected branch, `record_filtering_enabled`, branch ID support, and readiness.
- `applyScope()` returns unchanged when filtering is off.
- `applyRelatedScope()` filters only when current module and related module are both record-filtered.
- Voucher global + `customer_payments` branch-wise shows all eligible payments.
- Statement 500 fixed by passing `$includeNullBranchRecords` in `Customer.php` closure.
- Reports use global branch context.
- Single branch report uses selected branch branding.
- Multi/all reports use Main Branch branding and selected branch labels.
- Legacy `NULL branch_id` records included only when Main Branch is selected.
- `BranchSerialService` preserves old base number format.
- Existing old records are not renamed.
- Normal CRUD uses toast only, not top-right notification.
- Update available uses app modal.
- `2026_07_13_000002` migration was run.
- Migration status currently says all migrations `Ran`.

## Pending Manual Verification

- Full browser smoke tests.
- Developer UI spacing/scroll behavior.
- Branch selector behavior across target pages.
- Isolation across Main and Play Zone.
- Serial display consistency across toast/index/show/print.

## Known Risks

- `public/images` and `public/favicon.ico` changes look unrelated and must be reviewed before commit.
- `2026_07_11_000006` migration appears untracked while migration status says `Ran`.
- Some older JS still uses browser `alert()`.
- Manual browser tests are still required.

## Release Status

Do not commit/release yet until smoke tests pass.
