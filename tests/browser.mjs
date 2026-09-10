import assert from 'node:assert/strict';
import fs from 'node:fs';
import {execFileSync} from 'node:child_process';
import {createRequire} from 'node:module';
const require = createRequire(import.meta.url);
const {chromium} = require('../.tools/browser/node_modules/playwright');
const browser = await chromium.launch({executablePath:process.env.EE_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe',headless:true});
const base = 'http://127.0.0.1:8097';
const artifacts = '.tools/artifacts';
fs.mkdirSync(artifacts,{recursive:true});
const errors = [];
let checks = 0;
function check(condition,label) { assert.ok(condition,label); checks++; console.log('PASS: '+label); }
function local(action, id = '') { return execFileSync(process.env.EE_PHP || '.tools/php/php.exe',['tests/local-state.php',action,String(id)],{encoding:'utf8'}); }
async function sign(page, touch = false) {
  const canvas = page.locator('canvas'); await canvas.scrollIntoViewIfNeeded();
  const box = await canvas.boundingBox();
  if (touch) {
    const cdp = await page.context().newCDPSession(page);
    const start = {x:Math.round(box.x+box.width*.15),y:Math.round(box.y+box.height*.55)};
    await cdp.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[start]});
    for (let i=1;i<=35;i++) await cdp.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x:Math.round(start.x+i*box.width*.017),y:Math.round(start.y+Math.sin(i*.55)*24)}]});
    await cdp.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]}); await cdp.detach();
  } else {
    await page.mouse.move(box.x+40,box.y+90); await page.mouse.down();
    for (let i=1;i<=35;i++) await page.mouse.move(box.x+40+i*5,box.y+90+Math.sin(i*.5)*25);
    await page.mouse.up();
  }
}
async function fill(page, received = false) {
  await page.goto(base+'/?pagename=empfaengererklaerung'); await page.locator('.ee-form').waitFor({state:'visible'});
  check(await page.locator('[name=sender]').inputValue()==='Paradise X Sales GmbH\nGothaer Straße 4\n40880 Ratingen','fixed sender displayed');
  check(await page.locator('[name=sender]').evaluate(el=>el.readOnly),'sender cannot be edited');
  await page.locator('[data-next]').click(); check(await page.locator('#ee-tracking-error').innerText() !== '', 'required fields visible before proceeding');
  for (const [name,text] of Object.entries({tracking:'003404347382'+Date.now(),carrier:'DHL',recipient:'Anna Müller\nGartenstraße 24\n20095 Hamburg',contents:'Eine dunkelblaue Jacke, Größe M.'})) await page.locator(`[name=${name}]`).fill(text);
  const validTracking = await page.locator('[name=tracking]').inputValue();
  for (const invalid of ['003404347382ABC', '123456789012345', '003404347382 123']) {
    await page.locator('[name=tracking]').fill(invalid); await page.locator('[data-next]').click();
    check((await page.locator('#ee-tracking-error').innerText()).includes('003404347382'),'browser blocks invalid tracking '+invalid);
  }
  await page.locator('[name=tracking]').fill(validTracking);
  await page.locator('[data-next]').click();
  await page.locator(`[name=receipt][value=${received?'received':'not_received'}]`).check();
  if (received) { await page.locator('[name=received_date]').fill(await page.locator('[name=date]').inputValue()); }
  else { await page.locator('[name=unknown]').check(); }
  await page.locator('[name=cod][value=yes]').check();
  check(await page.locator('[data-condition=cod]').isVisible(),'COD fields appear');
  await page.locator('[name=cod_payment][value=branch]').check();
  await page.locator('[name=cod][value=no]').check();
  check(await page.locator('[name=cod_payment][value=branch]').isDisabled(),'inactive COD fields disabled');
  for (const [name,text] of Object.entries({first_name:'Anna',last_name:'Müller',address:'Gartenstraße 24\n20095 Hamburg',email:'anna@example.test'})) await page.locator(`[name=${name}]`).fill(text);
  await page.locator('[data-next]').click();
  check(!(await page.locator('.ee-review').innerText()).includes('Nachnahmebetrag'),'review omits inactive COD choice');
  await page.locator('[name=place]').fill('Hamburg'); await page.locator('[name=confirmed]').check();
}
try {
  local('reset-rate');
  const context = await browser.newContext({viewport:{width:1365,height:1000}}); const page = await context.newPage();
  page.on('pageerror',e=>errors.push(e.message));
  await fill(page);
  await page.locator('[data-submit]').click(); check((await page.locator('#ee-signature-error').innerText()).includes('unterschreiben'),'empty signature blocked');
  await sign(page); await page.locator('[name=place]').fill('Hamburg Mitte');
  check((await page.locator('.ee-signature-note').innerText()).includes('erneut'),'editing invalidates existing signature');
  await page.locator('[data-submit]').click(); check(await page.locator('.ee-success').isHidden(),'invalidated signature cannot submit');
  await sign(page); await page.locator('.ee-app').screenshot({path:artifacts+'/form-review-desktop.png'});
  let submitted;
  page.on('request',r=>{if(r.method()==='POST' && r.url().includes('declarations')) submitted = r.postDataJSON();});
  await page.locator('[data-submit]').click(); await page.locator('.ee-success').waitFor({state:'visible',timeout:30000});
  check((await page.locator('.ee-mail-message').innerText()).includes('übergeben'),'desktop submission succeeds');
  const url = await page.locator('.ee-download').getAttribute('href');
  const document = await context.request.get(url); check(document.status()===200 && (await document.body()).subarray(0,5).toString()==='%PDF-','public token downloads PDF');
  fs.writeFileSync(artifacts+'/browser.pdf',await document.body());
  const parsed = new URL(url); const id = parsed.searchParams.get('id');
  const noToken = new URL(url); noToken.searchParams.delete('token');
  check((await context.request.get(noToken.toString())).status()===403,'anonymous download without token denied');
  const forged = new URL(url); forged.searchParams.set('token','0'.repeat(64));
  check((await context.request.get(forged.toString())).status()===403,'forged token denied');
  const retry = await context.request.post(base+'/?rest_route=/empfaengererklaerung/v1/declarations',{data:submitted});
  check(retry.status()===200 && (await retry.json()).reference===(await page.locator('.ee-reference').innerText()).replace('Vorgangsnummer: ',''),'network retry returns existing declaration');
  local('expire',id); check((await context.request.get(url)).status()===403,'expired token denied');
  await page.locator('.ee-app').screenshot({path:artifacts+'/success-desktop.png'});
  const admin = await context.newPage();
  await admin.goto(base+'/wp-login.php'); await admin.locator('#user_login').fill('ee-admin'); await admin.locator('#user_pass').fill('Local-EE-Test-2026!'); await admin.locator('#wp-submit').click(); await admin.waitForURL('**/wp-admin/');
  await admin.goto(base+'/wp-admin/admin.php?page=ee-archive'); check(await admin.locator('h1').innerText() === 'Empfängererklärungen '+(await admin.locator('.ee-count').innerText()),'admin archive accessible');
  await admin.locator('input[name=s]').fill(submitted.tracking); await Promise.all([admin.waitForURL(u=>u.searchParams.get('s')===submitted.tracking),admin.getByRole('button',{name:'Filtern',exact:true}).click()]);
  check(await admin.locator('tbody input[name="ids[]"]').count()===1,'archive search filters submission');
  await Promise.all([admin.waitForURL(u=>u.searchParams.has('view')),admin.locator('tbody td a strong').click()]);
  const adminUrl = await admin.getByRole('link',{name:'PDF herunterladen'}).getAttribute('href');
  check((await context.request.get(adminUrl)).status()===200,'authorized admin downloads expired customer document');
  const originalBytes = await (await context.request.get(adminUrl)).body();
  await admin.getByRole('link',{name:'Angaben bearbeiten',exact:true}).click();
  check(await admin.locator('#ee-edit-tracking').inputValue()===submitted.tracking,'editor loads archived tracking');
  await admin.locator('#ee-edit-contents').fill('Im Backend korrigierter Sendungsinhalt');
  await admin.locator('#ee-edit-email').fill('korrigiert@example.test');
  const staleForm = await admin.locator('.ee-admin form').evaluate(el=>Object.fromEntries(new FormData(el)));
  const invalidForm = {...staleForm, 'declaration[tracking]':'003404347382ABC'};
  const invalidEdit = await context.request.post(base+'/wp-admin/admin-post.php',{form:invalidForm});
  check((await invalidEdit.text()).includes('nur Ziffern'),'server rejects invalid backend tracking');
  const missingNonce = {...staleForm}; delete missingNonce._wpnonce;
  check((await context.request.post(base+'/wp-admin/admin-post.php',{form:missingNonce})).status()===403,'edit requires valid nonce');
  await Promise.all([admin.waitForURL(u=>u.searchParams.has('updated')),admin.getByRole('button',{name:'Überarbeitung speichern'}).click()]);
  check((await admin.locator('.ee-detail-list').innerText()).includes('Im Backend korrigierter Sendungsinhalt'),'saved correction appears in details');
  check(originalBytes.equals(await (await context.request.get(adminUrl)).body()),'editing preserves original PDF bytes');
  const correctionUrl = await admin.getByRole('link',{name:'Überarbeitetes PDF herunterladen'}).getAttribute('href');
  const correctedPdf = await context.request.get(correctionUrl);
  check(correctedPdf.status()===200 && !originalBytes.equals(await correctedPdf.body()),'corrected PDF downloads separately');
  fs.writeFileSync(artifacts+'/browser-revised.pdf',await correctedPdf.body());
  const anon = await browser.newContext();
  check((await anon.request.get(correctionUrl)).status()===403,'correction download requires administrator'); await anon.close();
  const conflict = await context.request.post(base+'/wp-admin/admin-post.php',{form:staleForm});
  check((await conflict.text()).includes('inzwischen geändert'),'stale edit rejected over HTTP');
  await admin.getByRole('link',{name:'Angaben bearbeiten',exact:true}).click();
  check(await admin.locator('#ee-edit-contents').inputValue()==='Im Backend korrigierter Sendungsinhalt','editor reopens latest correction');
  await admin.getByRole('link',{name:'Abbrechen und zur Detailansicht'}).click();
  await admin.locator('.ee-admin').screenshot({path:artifacts+'/archive-detail.png'});
  local('fail-status',id); await admin.reload(); await Promise.all([admin.waitForURL(u=>u.searchParams.has('resent')),admin.getByRole('button',{name:'Versand erneut versuchen'}).click()]);
  check((await admin.locator('.ee-admin').innerText()).includes('An Mailversand übergeben'),'admin resend works');
  await admin.goto(base+'/wp-admin/admin.php?page=ee-settings');
  await admin.locator('#ee-company').fill('Paketservice Testmarke'); await admin.locator('#ee-accent').fill('#164e63'); await Promise.all([admin.waitForURL(u=>u.searchParams.has('settings-updated')),admin.getByRole('button',{name:'Save Changes'}).click()]);
  check(await admin.locator('#ee-company').inputValue()==='Paketservice Testmarke','settings saved');
  check((await context.request.get(adminUrl)).status()===200,'original document remains accessible after branding change');
  const mobileContext = await browser.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true,deviceScaleFactor:2}); const mobile = await mobileContext.newPage(); mobile.on('pageerror',e=>errors.push(e.message));
  await fill(mobile,true); await sign(mobile,true);
  // The automated form fill can take less than the deliberate 2-second anti-bot minimum.
  await mobile.waitForTimeout(2100);
  check(await mobile.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth),'mobile page has no horizontal overflow');
  await mobile.locator('.ee-app').screenshot({path:artifacts+'/form-review-mobile.png'});
  await mobile.locator('[data-submit]').click();
  try { await mobile.locator('.ee-success').waitFor({state:'visible',timeout:30000}); }
  catch (error) { console.log(await mobile.locator('.ee-app').innerText()); throw error; }
  check(await mobile.locator('.ee-success').isVisible(),'touch signature and mobile submission work');
  const mobileUrl = await mobile.locator('.ee-download').getAttribute('href');
  const mobileId = new URL(mobileUrl).searchParams.get('id');
  const readerContext = await browser.newContext(); const reader = await readerContext.newPage();
  await reader.goto(base+'/wp-login.php'); await reader.locator('#user_login').fill('ee-reader'); await reader.locator('#user_pass').fill('Local-EE-Reader-2026!'); await reader.locator('#wp-submit').click(); await reader.waitForURL('**/wp-admin/profile.php');
  check((await reader.goto(base+'/wp-admin/admin.php?page=ee-archive')).status()===403,'subscriber archive access forbidden');
  check((await readerContext.request.get(adminUrl)).status()===403,'subscriber cannot use administrator download link');
  check((await readerContext.request.post(base+'/wp-admin/admin-post.php',{form:{action:'ee_archive_action',operation:'delete','ids[]':id}})).status()===403,'subscriber cannot delete');
  check((await readerContext.request.post(base+'/wp-admin/admin-post.php',{form:staleForm})).status()===403,'subscriber cannot edit');
  check((await reader.goto(base+'/wp-admin/admin.php?page=ee-archive&edit='+id)).status()===403,'subscriber cannot open editor');
  await admin.goto(base+'/wp-admin/admin.php?page=ee-archive');
  await admin.locator(`[name="ids[]"][value="${id}"]`).check(); await admin.locator(`[name="ids[]"][value="${mobileId}"]`).check();
  check((await admin.locator('[data-selection-count]').innerText())==='2 ausgewählt','bulk selection count');
  await admin.locator('.ee-admin').screenshot({path:artifacts+'/archive.png'});
  admin.once('dialog',dialog=>dialog.accept()); await Promise.all([admin.waitForURL(u=>u.searchParams.has('deleted')),admin.getByRole('button',{name:'Ausgewählte löschen'}).click()]);
  check((await admin.locator('.notice').innerText()).includes('2 Erklärung(en) gelöscht'),'bulk delete confirmed');
  check((await mobileContext.request.get(mobileUrl)).status()===403,'deleted document no longer downloadable');
  await admin.goto(base+'/wp-admin/admin.php?page=ee-settings'); await admin.locator('#ee-company').fill('Paketservice'); await admin.locator('#ee-accent').fill('#205c50'); await Promise.all([admin.waitForURL(u=>u.searchParams.has('settings-updated')),admin.getByRole('button',{name:'Save Changes'}).click()]);
  check(errors.length===0,'no JavaScript runtime errors: '+errors.join('; '));
  console.log(`${checks} browser assertions passed.`);
} finally { await browser.close(); }
