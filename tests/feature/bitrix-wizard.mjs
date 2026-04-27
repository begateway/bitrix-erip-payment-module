/**
 * Walks the Bitrix install wizard headlessly.
 * Run: node bitrix-wizard.mjs
 *
 * Env:
 *   BITRIX_URL     default http://localhost:8088
 *   BITRIX_LICENSE default DEMO
 *   DB_HOST        default mysql
 *   DB_USER        default root
 *   DB_PASS        default root
 *   DB_NAME        default sitemanager
 *   ADMIN_LOGIN    default admin
 *   ADMIN_PASS     default Admin12345!
 *   ADMIN_EMAIL    default admin@example.com
 */
import { chromium } from '@playwright/test';
import { mkdir } from 'node:fs/promises';

const cfg = {
  url:     process.env.BITRIX_URL     || 'http://localhost:8088',
  license: process.env.BITRIX_LICENSE || 'DEMO',
  dbHost:  process.env.DB_HOST        || 'mysql',
  dbUser:  process.env.DB_USER        || 'root',
  dbPass:  process.env.DB_PASS        || 'root',
  dbName:  process.env.DB_NAME        || 'sitemanager',
  adminLogin: process.env.ADMIN_LOGIN || 'admin',
  adminPass:  process.env.ADMIN_PASS  || 'Admin12345!',
  adminEmail: process.env.ADMIN_EMAIL || 'admin@example.com',
  headed: process.env.HEADED === '1',
  shotDir: process.env.SHOT_DIR || '/tmp/bitrix-wizard',
};

await mkdir(cfg.shotDir, { recursive: true });

const browser = await chromium.launch({ headless: !cfg.headed });
const ctx = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'ru-RU' });
const page = await ctx.newPage();

let stepIdx = 0;
async function snap(label) {
  stepIdx++;
  const path = `${cfg.shotDir}/${String(stepIdx).padStart(2, '0')}-${label}.png`;
  await page.screenshot({ path, fullPage: true });
  return path;
}

async function currentStepId() {
  return await page.evaluate(() => {
    const el = document.querySelector('input[name="CurrentStepID"]');
    return el ? el.value : null;
  });
}

async function clickNext() {
  // The wizard's submit can be either a button or input named StepNext.
  const candidates = [
    'input[name="StepNext"]',
    'button[name="StepNext"]',
    'input.wizard-next-button',
    'button.wizard-next-button',
  ];
  for (const sel of candidates) {
    const el = await page.$(sel);
    if (el) {
      await Promise.all([
        page.waitForLoadState('networkidle').catch(() => {}),
        el.click(),
      ]);
      // wizard does its own redirects; settle
      await page.waitForLoadState('networkidle').catch(() => {});
      return true;
    }
  }
  return false;
}

console.log(`>> Bitrix wizard against ${cfg.url}`);
await page.goto(cfg.url, { waitUntil: 'domcontentloaded' });
await snap('initial');

const seen = new Set();
let safety = 30; // hard cap
while (safety-- > 0) {
  const step = await currentStepId();
  console.log(`step #${stepIdx}: ${step ?? '(no step id)'}`);
  if (!step) {
    // Either we're done, or admin login appeared.
    if (await page.$('input[name="USER_LOGIN"]')) {
      console.log('reached admin login form — installation finished');
      break;
    }
    if (await page.$('a[href*="bitrix/admin"]')) {
      console.log('admin link visible — installation finished');
      break;
    }
    const url = page.url();
    if (url.includes('/bitrix/admin/')) {
      console.log('we are inside /bitrix/admin/ — installation finished');
      break;
    }
    await snap('unknown-no-stepid');
    console.log('no CurrentStepID and no known finish marker — bailing');
    break;
  }
  // detect loop
  if (seen.has(step) && stepIdx > 1) {
    await snap(`stuck-${step}`);
    console.log(`!! looped on step ${step} — fields likely missing`);
    break;
  }
  seen.add(step);

  switch (step) {
    case 'agreement':
      await page.check('input#agree_license_id');
      break;
    case 'check_license_key': {
      // Default state has __wiz_lic_key_variant checked, which forces remote
      // registration with bitrix.ru. That fails for offline/demo installs.
      // Uncheck it so the wizard accepts the bundled DEMO trial unattended.
      const ck = await page.$('input#lic_key_variant');
      if (ck && await ck.isChecked()) await ck.uncheck();
      break;
    }
    case 'registration':
    case 'requirements':
    case 'directories_check':
      // no inputs to set
      break;
    case 'create_settings_php':
    case 'create_settings':
    case 'database': {
      // host/user/pass/name field names vary; try the common set
      const map = {
        'input[name="db_type"]': 'MYSQL',
        'input[name="DBType"]': 'MYSQL',
        'input[name="DBHost"]': cfg.dbHost,
        'input[name="DBLogin"]': cfg.dbUser,
        'input[name="DBPassword"]': cfg.dbPass,
        'input[name="DBName"]': cfg.dbName,
        'input[name="utf_mode"]': 'Y',
      };
      for (const [sel, val] of Object.entries(map)) {
        const el = await page.$(sel);
        if (el) {
          const t = await el.getAttribute('type');
          if (t === 'radio' || t === 'checkbox') {
            const elByVal = await page.$(`${sel}[value="${val}"]`);
            if (elByVal) await elByVal.click();
          } else {
            await el.fill(val);
          }
        }
      }
      break;
    }
    case 'install':
    case 'create_modules':
      // backend work — wait for it
      await page.waitForTimeout(3000);
      break;
    case 'select_wizard':
    case 'select_template':
      // Pick the eshop solution by matching its localised label.
      // (Bitrix names the radio group "redio" — a typo we have to live with.)
      await page.evaluate(() => {
        const radios = Array.from(document.querySelectorAll('input[type="radio"]'));
        const eshop = radios.find(r => /Интернет-магазин/i.test(r.closest('td,div,li,label')?.innerText || ''));
        if (eshop) { eshop.checked = true; eshop.click(); }
        else if (radios[0]) radios[0].click();
      });
      break;
    case 'create_admin':
    case 'admin_login':
    case 'admin_user': {
      const map = {
        'input[name="__wiz_login"]': cfg.adminLogin,
        'input[name="__wiz_admin_password"]': cfg.adminPass,
        'input[name="__wiz_admin_password_confirm"]': cfg.adminPass,
        'input[name="__wiz_email"]': cfg.adminEmail,
        'input[name="__wiz_user_name"]': 'Admin',
        'input[name="__wiz_user_surname"]': 'Bitrix',
        // legacy fallback names from older Bitrix installers
        'input[name="USER_LOGIN"]': cfg.adminLogin,
        'input[name="USER_PASSWORD"]': cfg.adminPass,
        'input[name="USER_CONFIRM_PASSWORD"]': cfg.adminPass,
        'input[name="USER_EMAIL"]': cfg.adminEmail,
      };
      for (const [sel, val] of Object.entries(map)) {
        const el = await page.$(sel);
        if (el) await el.fill(val);
      }
      break;
    }
    default:
      console.log(`(no handler for step ${step}; pressing Next blindly)`);
  }

  await snap(step);
  const advanced = await clickNext();
  if (!advanced) {
    // Auto-progress step (install_modules etc.) — wait for the step id to change.
    console.log(`(auto step ${step}: waiting for advance...)`);
    const ok = await page
      .waitForFunction((prev) => {
        const el = document.querySelector('input[name="CurrentStepID"]');
        return !el || el.value !== prev;
      }, step, { timeout: 180_000, polling: 2000 })
      .then(() => true)
      .catch(() => false);
    if (!ok) {
      console.log(`!! step ${step} never advanced after 180s — bailing`);
      break;
    }
  }
  await page.waitForTimeout(800);
}

console.log(`>> wizard exit; screenshots in ${cfg.shotDir}`);
await ctx.close();
await browser.close();
