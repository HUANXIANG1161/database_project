// 端到端功能冒烟测试：登录、发布攻略、组队审核、路线版本、资源上传、好友私聊、后台审核。
const base = 'http://127.0.0.1:8080';

class Session {
  constructor(name) {
    this.name = name;
    this.cookies = new Map();
    this.token = '';
  }
  cookieHeader() {
    return [...this.cookies.entries()].map(([k, v]) => `${k}=${v}`).join('; ');
  }
  async req(path, options = {}) {
    const headers = { ...(options.headers || {}) };
    const c = this.cookieHeader();
    if (c) headers.Cookie = c;
    const res = await fetch(base + path, { ...options, headers, redirect: 'manual' });
    const setCookies = res.headers.getSetCookie ? res.headers.getSetCookie() : [];
    for (const raw of setCookies) {
      const pair = raw.split(';')[0];
      const i = pair.indexOf('=');
      if (i > 0) this.cookies.set(pair.slice(0, i), pair.slice(i + 1));
    }
    const text = await res.text();
    return { status: res.status, location: res.headers.get('location'), text };
  }
  get(path) { return this.req(path); }
  async post(path, data) {
    if (!this.token) await this.ensureToken();
    data = { ...data, _token: this.token };
    const body = new URLSearchParams();
    for (const [k, v] of Object.entries(data)) {
      if (Array.isArray(v)) for (const item of v) body.append(k, item);
      else body.append(k, v);
    }
    return this.req(path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    });
  }
  async ensureToken() {
    if (this.token) return this.token;
    for (const path of ['/guide_edit.php', '/login.php']) {
      const res = await this.get(path);
      const m = res.text.match(/name="_token" value="([^"]+)"/);
      if (m) {
        this.token = m[1];
        return this.token;
      }
    }
    throw new Error(this.name + ': no CSRF token');
  }
  async login(login) {
    await this.ensureToken();
    const res = await this.post('/login.php', { _token: this.token, login, password: '123456', next: '/index.php' });
    if (res.status !== 302) throw new Error(`${this.name} login failed: ${res.status}`);
    this.token = '';
    console.log(`  login ${login}: ok`);
    return this;
  }
  async logout() {
    await this.get('/logout.php');
    this.token = '';
  }
}

function assert(cond, message) {
  if (!cond) throw new Error(message);
}

function expectStatus(res, status, label) {
  assert(res.status === status, `${label}: expected ${status}, got ${res.status} ${res.location || ''}`);
  console.log(`  ok ${label}: ${status}${res.location ? ' -> ' + res.location : ''}`);
}

function idFromLocation(loc, key = 'id') {
  const m = loc && loc.match(new RegExp(`[?&]${key}=(\\d+)`));
  assert(m, 'no id in location: ' + loc);
  return m[1];
}

(async () => {
  const alice = new Session('alice');
  const bob = new Session('bob');
  const carol = new Session('carol');
  const admin = new Session('admin');

  console.log('1. 登录');
  await alice.login('alice');
  await bob.login('bob');
  await carol.login('carol');
  await admin.login('admin');

  console.log('2. 发布攻略（待审）');
  await alice.ensureToken();
  let res = await alice.post('/guide_edit.php', {
    _token: alice.token,
    title: '集成测试攻略：厦门小众海岸线',
    destination: '厦门',
    cover: '/uploads/demo/xiamen.svg',
    season: 'spring',
    day_count: 2,
    cost_avg: 680,
    status: 'pending',
    summary: '避开人潮的海岸线走法。',
    content: '第一天：沙坡尾到演武大桥。第二天：环岛路骑行。',
    'tags[]': ['1', '3'],
  });
  expectStatus(res, 302, 'create guide');
  const guideId = idFromLocation(res.location);
  res = await alice.get('/guide.php?id=' + guideId);
  expectStatus(res, 200, 'author view pending guide');
  assert(res.text.includes('厦门小众海岸线'), 'guide title not rendered');

  console.log('3. 攻略点赞与评论');
  res = await bob.post('/guide.php?id=1', { _token: bob.token, action: 'like' });
  expectStatus(res, 302, 'like guide');
  res = await bob.post('/guide.php?id=1', {
    _token: bob.token, action: 'comment', content: '这条海岸线看起来不错，收藏了。', parent_id: '',
  });
  expectStatus(res, 302, 'comment guide');
  res = await bob.get('/guide.php?id=1');
  expectStatus(res, 200, 'guide detail after comment');
  assert(res.text.includes('这条海岸线看起来不错'), 'comment not rendered');

  console.log('4. 创建行程与队伍');
  res = await alice.post('/trip_edit.php', {
    _token: alice.token, title: '集成测试行程：杭州周末', destination: '杭州',
    start_date: '2026-12-05', end_date: '2026-12-07', budget: 1500,
    visibility: 'team', status: 'planning', cover: '/uploads/demo/qingdao.svg',
    description: '西湖与龙井村周末走线。',
  });
  expectStatus(res, 302, 'create trip');
  const tripId = idFromLocation(res.location);

  res = await alice.post('/team_edit.php?trip_id=' + tripId, {
    _token: alice.token, name: '杭州周末同行队', max_members: 4,
    status: 'recruiting', description: '轻松走，住西湖附近。',
  });
  expectStatus(res, 302, 'create team');
  const teamId = idFromLocation(res.location);

  console.log('5. 入队申请与队长审核');
  res = await bob.get('/team.php?id=' + teamId);
  expectStatus(res, 200, 'pending applicant can view team');
  res = await bob.post('/team.php?id=' + teamId, {
    _token: bob.token, action: 'apply', apply_reason: '同城出发，会拍照。',
  });
  expectStatus(res, 302, 'apply team');
  res = await alice.post('/team.php?id=' + teamId, {
    _token: alice.token, action: 'review', user_id: '2', decision: 'approve',
  });
  expectStatus(res, 302, 'approve member');
  res = await alice.get('/team.php?id=' + teamId);
  expectStatus(res, 200, 'team after approve');
  assert(res.text.includes('阿波'), 'approved member not rendered');

  console.log('6. 路线保存与版本历史');
  res = await alice.post('/route_edit.php?trip_id=' + tripId, {
    _token: alice.token, route_title: '杭州周末路线', version_remark: '初版',
    'day_no[]': ['1', '1', '2', '2'],
    'seq[]': ['1', '2', '1', '2'],
    'item_type[]': ['spot', 'food', 'spot', 'food'],
    'title[]': ['西湖断桥', '湖滨午餐', '龙井村', '茶园晚餐'],
    'address[]': ['西湖', '湖滨银泰', '龙井村', '龙井路'],
    'start_time[]': ['09:00', '12:00', '09:30', '18:00'],
    'end_time[]': ['11:30', '13:30', '12:00', '19:30'],
    'cost[]': ['0', '80', '0', '120'],
    'remark[]': ['早到避人流', '', '公交上山', ''],
  });
  expectStatus(res, 302, 'save route v1');
  res = await alice.get('/route.php?trip_id=' + tripId);
  expectStatus(res, 200, 'route v1 page');
  assert(res.text.includes('西湖断桥') && res.text.includes('v1'), 'route item/version missing');

  res = await alice.post('/route_edit.php?trip_id=' + tripId, {
    _token: alice.token, route_title: '杭州周末路线', version_remark: '增加夜游',
    'day_no[]': ['1', '2', '2'],
    'seq[]': ['1', '1', '2'],
    'item_type[]': ['spot', 'spot', 'food'],
    'title[]': ['西湖断桥', '龙井村', '茶园晚餐'],
    'address[]': ['西湖', '龙井村', '龙井路'],
    'start_time[]': ['09:00', '09:30', '18:00'],
    'end_time[]': ['11:30', '12:00', '19:30'],
    'cost[]': ['0', '0', '120'],
    'remark[]': ['早到避人流', '公交上山', ''],
  });
  expectStatus(res, 302, 'save route v2');
  res = await alice.get('/route_history.php?trip_id=' + tripId);
  expectStatus(res, 200, 'route history');
  assert(res.text.includes('v2') && res.text.includes('v1'), 'version history missing');

  console.log('7. 资源上传');
  await alice.ensureToken();
  const png = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2n1cAAAAASUVORK5CYII=',
    'base64'
  );
  const fd = new FormData();
  fd.append('_token', alice.token);
  fd.append('type', 'photo');
  fd.append('title', '集成测试资源：西湖边');
  fd.append('description', '测试上传的 1x1 图片。');
  fd.append('location', '杭州西湖');
  fd.append('trip_id', tripId);
  fd.append('file', new Blob([png], { type: 'image/png' }), 'test.png');
  res = await alice.req('/resource_upload.php', { method: 'POST', body: fd });
  expectStatus(res, 302, 'upload resource');
  const resourceId = idFromLocation(res.location);
  res = await alice.get('/resource.php?id=' + resourceId);
  expectStatus(res, 200, 'resource detail');
  assert(res.text.includes('西湖边'), 'resource title missing');

  console.log('8. 好友与私聊');
  res = await bob.post('/friends.php', { _token: bob.token, action: 'add', user_id: '3' });
  expectStatus(res, 302, 'friend request');
  res = await carol.post('/friends.php', { _token: carol.token, action: 'accept', user_id: '2' });
  expectStatus(res, 302, 'accept friend');
  res = await carol.get('/friends.php');
  expectStatus(res, 200, 'carol friends page');
  assert(res.text.includes('bob') || res.text.includes('阿波'), 'accepted friend missing');
  res = await bob.post('/messages.php', { _token: bob.token, with: '3', content: '路线里龙井村那段公交几点收班？' });
  expectStatus(res, 302, 'send message');
  res = await carol.get('/messages.php?with=2');
  expectStatus(res, 200, 'read conversation');
  assert(res.text.includes('龙井村那段公交'), 'message missing');

  console.log('9. 后台审核攻略');
  res = await admin.post('/admin/guides.php', { _token: admin.token, id: guideId, op: 'published' });
  expectStatus(res, 302, 'admin publish guide');
  const anon = new Session('anon');
  res = await anon.get('/guide.php?id=' + guideId);
  expectStatus(res, 200, 'public guide after approval');
  assert(res.text.includes('厦门小众海岸线'), 'approved guide not public');

  console.log('\nALL INTEGRATION CHECKS PASSED');
})().catch(err => { console.error('\nFAIL:', err.message); process.exit(1); });
