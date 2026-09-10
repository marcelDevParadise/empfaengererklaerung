import assert from 'node:assert/strict';
import fs from 'node:fs';
import {execFileSync} from 'node:child_process';
import {createRequire} from 'node:module';
const require = createRequire(import.meta.url);
const {chromium} = require('../.tools/browser/node_modules/playwright');
const local = action => execFileSync(process.env.EE_PHP || '.tools/php74/php.exe', ['tests/local-state.php',action], {encoding:'utf8'});
const fixturePath = '.tools/wordpress/wp-content/mu-plugins/ee-update-ui-test.php';
assert.ok(!fs.existsSync(fixturePath), 'test helper must not overwrite an existing file');
assert.ok(fs.readFileSync('.tools/wordpress/wp-config.php','utf8').includes("define('WP_ENVIRONMENT_TYPE', 'local')"));
const fixture = fs.readFileSync('dist/update.json').toString('base64');
// Only the UI is enabled. Actual background updates remain disabled in this test site.
fs.writeFileSync(fixturePath, `<?php
if (wp_get_environment_type() !== 'local') { return; }
add_filter('plugins_auto_update_enabled', '__return_true');
add_filter('pre_http_request', static function ($pre, $args, $url) {
    if ($url !== 'https://github.com/marcelDevParadise/empfaengererklaerung/releases/latest/download/update.json') { return $pre; }
    return ['headers' => [], 'body' => base64_decode('${fixture}'), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => []];
}, 100, 3);
`);
let browser;
let checks = 0;
const check = (value,label) => { assert.ok(value,label); checks++; console.log('PASS: '+label); };
try {
  local('updates-start');
  browser = await chromium.launch({executablePath:process.env.EE_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
  const page = await browser.newPage({viewport:{width:1365,height:1000}});
  const errors = []; page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:8097/wp-login.php');
  await page.locator('#user_login').fill('ee-admin'); await page.locator('#user_pass').fill('Local-EE-Test-2026!');
  await page.locator('#wp-submit').click(); await page.waitForURL('**/wp-admin/');
  await page.goto('http://127.0.0.1:8097/wp-admin/plugins.php');
  const row = page.locator('tr[data-plugin="empfaengererklaerung/empfaengererklaerung.php"]').first();
  check(await row.getByRole('link',{name:'Check for updates',exact:true}).isVisible(),'manual GitHub update check is visible');
  const toggle = row.locator('.toggle-auto-update');
  check(await toggle.getAttribute('data-wp-action')==='enable','native auto-update activation available');
  await toggle.click(); await page.waitForFunction(()=>document.querySelector('tr[data-plugin="empfaengererklaerung/empfaengererklaerung.php"] .toggle-auto-update')?.dataset.wpAction==='disable');
  await page.reload();
  check(await toggle.getAttribute('data-wp-action')==='disable','auto-update choice persists after reload');
  await page.screenshot({path:'.tools/artifacts/updater-plugins.png',fullPage:true});
  await row.getByRole('link',{name:'Check for updates',exact:true}).click();
  await page.waitForURL(url=>url.searchParams.has('puc_update_check_result'));
  check((await page.locator('body').innerText()).includes('up to date'),'manual update check reports current version');
  await toggle.click(); await page.waitForFunction(()=>document.querySelector('tr[data-plugin="empfaengererklaerung/empfaengererklaerung.php"] .toggle-auto-update')?.dataset.wpAction==='enable');
  check(await toggle.getAttribute('data-wp-action')==='enable','automatic updates can be disabled again');
  check(errors.length===0,'no updater UI JavaScript errors');
  console.log(`${checks} updater browser assertions passed.`);
} finally {
  if (browser) await browser.close();
  local('updates-restore');
  fs.unlinkSync(fixturePath);
}
