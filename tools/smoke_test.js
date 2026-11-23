// 轻量 HTTP 冒烟测试：带 Cookie 登录，逐页检查状态码和 PHP 错误关键字。
const base = 'http://127.0.0.1:8080';
const cookies = new Map();

function cookieHeader() {
  return [...cookies.entries()].map(([k, v]) => `${k}=${v}`).join('; ');
}

async function request(path, options = {}) {
  const headers = { ...(options.headers || {}) };
  const c = cookieHeader();
  if (c) headers.Cookie = c;
  const res = await fetch(base + path, { ...options, headers, redirect: 'manual' });
  const setCookies = res.headers.getSetCookie ? res.headers.getSetCookie() : [];
  for (const raw of setCookies) {
    const pair = raw.split(';')[0];
    const i = pair.indexOf('=');
    if (i > 0) cookies.set(pair.slice(0, i), pair.slice(i + 1));
  }
  const text = await res.text();
  return { status: res.status, location: res.headers.get('location'), text };
}

function tokenOf(html) {
  const m = html.match(/name="_token" value="([^"]+)"/);
  return m ? m[1] : '';
}

async function login(login) {
  const page = await request('/login.php');
  const token = tokenOf(page.text);
  if (!token) throw new Error('登录页未找到 CSRF token');
  const body = new URLSearchParams({ _token: token, login, password: '123456', next: '/index.php' });
  const res = await request('/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString(),
  });
  if (res.status !== 302) throw new Error(`登录 ${login} 失败: HTTP ${res.status}`);
  console.log(`login ${login}: 302 -> ${res.location}`);
}

const errorPattern = /(Fatal error|Parse error|Warning:|Notice:|Deprecated:|Uncaught|SQLSTATE|PDOException)/i;

async function check(path, expect = 200) {
  const expected = Array.isArray(expect) ? expect : [expect];
  const res = await request(path);
  const bad = errorPattern.test(res.text);
  const ok = expected.includes(res.status) && !bad;
  const note = res.location ? ` -> ${res.location}` : '';
  console.log(`${ok ? 'OK  ' : 'FAIL'} ${path} => ${res.status}${note}${bad ? ' [PHP ERROR TEXT]' : ''}`);
  if (!ok) {
    const m = res.text.match(errorPattern);
    if (m) {
      const i = Math.max(0, m.index - 120);
      console.log('     ' + res.text.slice(i, m.index + 400).replace(/\s+/g, ' '));
    }
  }
  return res;
}

(async () => {
  console.log('--- 未登录 ---');
  await check('/');
  await check('/guides.php');
  await check('/guide.php?id=1', [200, 404]);
  await check('/trips.php');
  await check('/trip.php?id=3', [200, 404]);
  await check('/teams.php');
  await check('/team.php?id=3', [200, 404]);
  await check('/route.php?trip_id=3', [200, 404]);
  await check('/resources.php');
  await check('/resource.php?id=1', [200, 404]);
  await check('/resource.php?id=3', [200, 404]);
  await check('/resource.php?id=4', [200, 404]);
  await check('/profile.php?id=1');
  await check('/login.php');
  await check('/register.php');

  console.log('--- alice ---');
  await login('alice');
  for (const [p, expect] of [
    ['/index.php', 200], ['/guides.php', 200], ['/guide.php?id=1', [200, 404]], ['/guide_edit.php', 200], ['/guide_my.php', 200],
    ['/trips.php', 200], ['/trip.php?id=1', [200, 404]], ['/trip_edit.php', 200], ['/trip_my.php', 200],
    ['/teams.php', 200], ['/team.php?id=1', [200, 404]], ['/team_edit.php?id=1', [200, 302, 404]],
    ['/route.php?trip_id=1', [200, 404]], ['/route_edit.php?trip_id=1', [200, 302, 404]], ['/route_history.php?trip_id=1', [200, 404]],
    ['/resources.php', 200], ['/resource.php?id=1', [200, 404]], ['/resource_upload.php', 200],
    ['/friends.php', 200], ['/messages.php', 200], ['/notifications.php', 200], ['/profile.php', 200],
  ]) await check(p, expect);

  console.log('--- admin ---');
  await request('/logout.php');
  await login('admin');
  for (const p of ['/admin/index.php', '/admin/users.php', '/admin/guides.php', '/admin/resources.php']) await check(p);

  console.log('--- done ---');
})().catch(err => { console.error('FAIL', err.message); process.exit(1); });
