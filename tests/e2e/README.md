# Incremental functional QA

These tests exercise the locally installed unreleased plugin. Run them on a disposable
test site with the plugin active and WP-CLI database access. They temporarily change
global paywall settings, disable metering, create a post, and restore the original
settings and delete the post in `finally`. Do not run concurrent suites against the
same site or edit these settings during a run.

Install dependencies with `npm ci`, then build the plugin with `npm run build`.
The site must load this checkout (or an installed candidate built from it).

```sh
export QA_WP_PATH=/path/to/wordpress
export QA_PHP_EXECUTABLE=/path/to/php
export QA_WP_CLI=/usr/local/bin/wp
export QA_BROWSER_EXECUTABLE='/Applications/Brave Browser.app/Contents/MacOS/Brave Browser'
npm run test:e2e
```

`QA_WP_PATH` is required. PHP defaults to `php`; WP-CLI defaults to
`/usr/local/bin/wp`. Omit the browser executable to use Playwright's installed
Chromium. MCP is useful for investigating pages; the saved tests run independently
through Playwright Test. No admin password or Memberful account details are stored
in the test files.

Run one case with `npm run test:e2e -- --grep PW-02`.

Implemented coverage:

| Case | Assertions |
| --- | --- |
| PW-01 | Card visible, two teaser paragraphs, Subscribe URL, CSS loaded, protected text absent from response and DOM |
| PW-02 | Custom HTML rendered, two teaser paragraphs, no builder card or stylesheet, legacy fade CSS, no protected text |
| PC-01 (rendering) | Exactly 1, 3, or 10 paragraphs; subsequent paragraphs absent from the response |

Settings are seeded through WP-CLI. Admin save/validation, preview, entitled members,
listings, Beaver Builder, and recipe coverage remain to be added one case at a time.
MR-5 (including the cache-safe fix) and MR-15 are outside this suite's current scope.
Local checkout results are not packaged release-candidate certification.

To verify the leak assertion itself, run:

```sh
QA_NEGATIVE_CONTROL=1 npm run test:e2e -- --grep PW-01
```

This deliberately leaves the temporary post unprotected and must fail with
`protected text must not be sent over HTTP`. A different failure is not a successful
negative control. The cleanup still runs. Then run normally to confirm a pass.

Failure screenshots and reports stay in ignored `tests/e2e/results/`; they may contain
private site data and should not be committed or shared unreviewed. Traces are off.
A private `memberful-qa-<uuid>.json` recovery snapshot is written to the OS temporary
directory before mutation and deleted after successful cleanup. If the process is
forcibly terminated, use its `options` (including each option's `exists` flag) and
`postId` to restore the site before continuing. Do not commit that snapshot.
