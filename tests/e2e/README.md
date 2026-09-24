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
| PW-10 (subscriber) | Anonymous visitor is blocked; a logged-in Subscriber with a seeded active plan receives all paragraphs with no paywall, teaser wrapper, or paywall CSS |

Settings are seeded through WP-CLI. PW-10 creates a temporary Subscriber, seeds local
subscription data through the plugin's sync class, and logs in through the WordPress
login form. It does not exercise Memberful SSO, billing, or remote sync. Trial,
download, and administrator variants are not yet covered. The user is deleted in
`finally`, and cleanup verifies deletion.

Admin save/validation, preview,
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

For the member-access negative control, run
`QA_NEGATIVE_CONTROL=1 npm run test:e2e -- --grep PW-10`.
This keeps the post protected and the user logged in, but removes the user's plan
before reloading. It must fail with `entitled member must receive protected text`.

Failure screenshots and reports stay in ignored `tests/e2e/results/`; they may contain
private site data and should not be committed or shared unreviewed. Traces are off.
A private `memberful-qa-<uuid>.json` recovery snapshot is written to the OS temporary
directory before mutation and deleted after successful cleanup. If the process is
forcibly terminated, use its `options` (including each option's `exists` flag) and
`postId` to restore the site before continuing. For PW-10, also delete the temporary
user identified by `userId`. Do not commit that snapshot.
