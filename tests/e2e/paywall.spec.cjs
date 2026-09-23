const { test, expect } = require('@playwright/test');
const { execFileSync } = require('node:child_process');
const { randomUUID } = require('node:crypto');
const { writeFileSync, unlinkSync } = require('node:fs');
const { join } = require('node:path');
const { tmpdir } = require('node:os');

function wp(code) {
  if (!process.env.QA_WP_PATH) throw new Error('Set QA_WP_PATH to the disposable WordPress installation.');
  return JSON.parse(execFileSync(process.env.QA_PHP_EXECUTABLE || 'php', [process.env.QA_WP_CLI || '/usr/local/bin/wp', '--path=' + process.env.QA_WP_PATH, 'eval', code], {
    encoding: 'utf8', timeout: 20000,
  }));
}

function phpData(data) {
  return `json_decode(base64_decode('${Buffer.from(JSON.stringify(data)).toString('base64')}'), true)`;
}

for (const [caseId, mode, paragraphCount] of [
  ['PW-01', 'builder', 2],
  ['PW-02', 'custom_html', 2],
  ...[1, 3, 10].map(count => ['PC-01', 'builder', count]),
]) {
test(`${caseId}: ${mode} paywall shows ${paragraphCount} paragraphs without sending protected text`, async ({ page }) => {
  const token = randomUUID();
  const paragraphs = Array.from({ length: 12 }, (_, index) => `QA_PARA_${index + 1}_${token}`);
  const secret = `QA_PROTECTED_BODY_${token}`;
  const keys = [
    'memberful_use_global_marketing', 'memberful_use_global_snippets',
    'memberful_global_marketing_override', 'memberful_paragraph_count',
    'memberful_paywall_config', 'memberful_metering_config',
    'memberful_global_marketing_content',
    'memberful_posts_available_to_anybody_subscribed_to_a_plan',
  ];
  const snapshot = wp(`
    $state = array();
    foreach (${phpData(keys)} as $key) {
      $missing = new stdClass();
      $value = get_option($key, $missing);
      $state[$key] = array('exists' => $value !== $missing, 'value' => $value === $missing ? null : $value);
    }
    echo wp_json_encode($state);
  `);
  const recoveryPath = join(tmpdir(), `memberful-qa-${token}.json`);
  writeFileSync(recoveryPath, JSON.stringify({ options: snapshot, postId: null }), { mode: 0o600 });
  let postId;
  try {
    const fixture = wp(`
      update_option('memberful_metering_config', array('enabled' => false));
      update_option('memberful_use_global_marketing', true);
      update_option('memberful_use_global_snippets', true);
      update_option('memberful_global_marketing_override', true);
      update_option('memberful_paragraph_count', ${paragraphCount});
      update_option('memberful_global_marketing_content', '<p>CUSTOM-PAYWALL-HTML</p>');
      Memberful_Paywall_Config::save(array(
        'mode' => '${mode}', 'layout' => 'card',
        'subscribe_url' => 'https://example.invalid/subscribe',
        'sign_in_url' => 'https://example.invalid/sign-in'
      ));
      $post_id = wp_insert_post(array(
        'post_title' => 'QA temporary ${caseId}', 'post_status' => 'publish',
        'post_content' => ${phpData(paragraphs.map(text => `<p>${text}</p>`).join('') + `<p>${secret}</p>`)},
      ), true);
      if (is_wp_error($post_id)) throw new RuntimeException($post_id->get_error_message());
      memberful_wp_set_post_available_to_anybody_subscribed_to_a_plan($post_id, ${process.env.QA_NEGATIVE_CONTROL === '1' ? 'false' : 'true'});
      echo wp_json_encode(array('id' => $post_id, 'url' => get_permalink($post_id)));
    `);
    postId = fixture.id;
    writeFileSync(recoveryPath, JSON.stringify({ options: snapshot, postId }), { mode: 0o600 });
    const response = await page.goto(fixture.url, { waitUntil: 'networkidle' });
    expect(response.status()).toBe(200);
    const source = await response.text();
    expect(source, 'protected text must not be sent over HTTP').not.toContain(secret);
    for (const hidden of paragraphs.slice(paragraphCount)) expect(source).not.toContain(hidden);
    await expect(page.locator('body')).not.toContainText(secret);
    const teaser = page.locator('.memberful-global-teaser-content');
    for (const visible of paragraphs.slice(0, paragraphCount)) await expect(teaser).toContainText(visible);
    await expect(teaser.locator('p')).toHaveCount(paragraphCount);
    if (mode === 'custom_html') {
      await expect(page.locator('.memberful-global-marketing-content')).toHaveText('CUSTOM-PAYWALL-HTML');
      await expect(page.locator('.memberful-paywall')).toHaveCount(0);
      await expect(page.locator('link[href*="/stylesheets/paywall.css"]')).toHaveCount(0);
      expect(await page.locator('style').allTextContents()).toEqual(expect.arrayContaining([
        expect.stringContaining('.memberful-global-teaser-content > :last-child'),
      ]));
      return;
    }
    const card = page.locator('.memberful-paywall--card');
    await expect(card).toBeVisible();
    await expect(card.getByRole('link', { name: 'Subscribe', exact: true })).toHaveAttribute('href', 'https://example.invalid/subscribe');
    const cssLoaded = await page.evaluate(() => [...document.styleSheets].some(sheet => {
      if (!sheet.href?.includes('/stylesheets/paywall.css')) return false;
      try { return sheet.cssRules.length > 0; } catch { return false; }
    }));
    expect(cssLoaded, 'the paywall stylesheet must load and contain rules').toBe(true);
  } finally {
    wp(`
      $post_id = ${postId || 0};
      if ($post_id) wp_delete_post($post_id, true);
      foreach (${phpData(snapshot)} as $key => $item) {
        if ($item['exists']) update_option($key, $item['value']);
        else delete_option($key);
        $missing = new stdClass();
        $actual = get_option($key, $missing);
        if ($item['exists'] ? $actual !== $item['value'] : $actual !== $missing) {
          throw new RuntimeException('Failed to restore ' . $key);
        }
      }
      if ($post_id && get_post($post_id)) throw new RuntimeException('Temporary post was not removed');
      echo wp_json_encode(true);
    `);
    unlinkSync(recoveryPath);
  }
});
}
