const { chromium } = require('playwright-core');
const path = require('path');

const BASE = 'http://127.0.0.1:8080';
const OUT = 'D:\\database_project\\docs\\screenshots';
const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

async function login(page, username) {
  await page.goto(BASE + '/login.php', { waitUntil: 'networkidle' });
  await page.fill('#login', username);
  await page.fill('#password', '123456');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }),
    page.locator('form:has(#login) button[type="submit"]').click()
  ]);
  const nav = await page.locator('nav').innerText();
  if (!nav.includes('通知') && !nav.includes('私聊')) {
    throw new Error(`login failed for ${username}: ${page.url()}`);
  }
}

async function shot(context, url, name, fullPage = false) {
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (err) => errors.push(String(err)));
  const resp = await page.goto(BASE + url, { waitUntil: 'networkidle' });
  await page.screenshot({ path: path.join(OUT, name + '.png'), fullPage });
  console.log(`${name}: HTTP ${resp ? resp.status() : '?'} errors=${errors.length}`);
  await page.close();
}

(async () => {
  const browser = await chromium.launch({
    executablePath: CHROME,
    headless: true,
    args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage']
  });

  const alice = await browser.newContext({ viewport: { width: 1440, height: 1100 } });
  const alicePage = await alice.newPage();
  await login(alicePage, 'alice');
  await alicePage.close();
  await shot(alice, '/trip.php?id=1', 'trip_detail');
  await shot(alice, '/guide.php?id=1', 'guide_detail');
  await shot(alice, '/guide_edit.php?id=1', 'guide_edit');
  await shot(alice, '/team.php?id=1', 'team_detail');
  await shot(alice, '/route.php?trip_id=1', 'route_detail');
  await shot(alice, '/route_edit.php?trip_id=1', 'route_edit');
  await shot(alice, '/route_history.php?trip_id=1', 'route_history');
  await shot(alice, '/resource.php?id=1', 'resource_detail');
  await shot(alice, '/profile.php', 'profile');

  const bob = await browser.newContext({ viewport: { width: 1440, height: 1100 } });
  const bobPage = await bob.newPage();
  await login(bobPage, 'bob');
  await bobPage.close();
  await shot(bob, '/messages.php?with=4', 'messages');
  await shot(bob, '/friends.php', 'friends');

  const admin = await browser.newContext({ viewport: { width: 1440, height: 1100 } });
  const adminPage = await admin.newPage();
  await login(adminPage, 'admin');
  await adminPage.close();
  await shot(admin, '/admin/index.php', 'admin_dashboard');
  await shot(admin, '/admin/guides.php', 'admin_guides');

  await browser.close();
})();
