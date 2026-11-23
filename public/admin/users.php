<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

$me = current_user();
if (is_post()) {
    verify_csrf();
    $id = (int) input('id');
    $target = db_one('SELECT * FROM `user` WHERE `id` = ?', [$id]);
    if ($target === null) {
        flash('danger', '用户不存在。');
    } elseif ($id === (int) $me['id']) {
        flash('danger', '不能禁用当前登录的管理员账号。');
    } elseif ($target['role'] === 'admin' && $target['status'] === 'active'
        && (int) db_val('SELECT COUNT(*) FROM `user` WHERE `role` = "admin" AND `status` = "active"') <= 1) {
        flash('danger', '至少保留一个可用的管理员账号。');
    } else {
        $newStatus = $target['status'] === 'active' ? 'disabled' : 'active';
        db_exec('UPDATE `user` SET `status` = ? WHERE `id` = ?', [$newStatus, $id]);
        if ($newStatus === 'disabled') {
            db_exec(
                'INSERT INTO `notification` (`user_id`, `type`, `title`, `content`, `link`) VALUES (?, "system", ?, ?, "/index.php")',
                [$id, '账号已被禁用', '你的账号已被管理员禁用，如有疑问请联系管理员。']
            );
        }
        flash('success', $newStatus === 'active' ? '账号已启用。' : '账号已禁用。');
    }
    redirect('/admin/users.php');
}

$q = trim((string) query('q'));
$status = (string) query('status');
if (!in_array($status, ['', 'active', 'disabled'], true)) {
    $status = '';
}
$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(`username` LIKE ? OR `nickname` LIKE ? OR `email` LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($status !== '') {
    $where[] = '`status` = ?';
    $params[] = $status;
}
$users = db_all(
    'SELECT u.*,
        (SELECT COUNT(*) FROM `guide` g WHERE g.`author_id` = u.`id`) AS `guide_count`,
        (SELECT COUNT(*) FROM `trip` t WHERE t.`owner_id` = u.`id`) AS `trip_count`
     FROM `user` u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.`id` ASC LIMIT 100',
    $params
);

$pageTitle = '用户管理';
$activeNav = 'admin';
require APP_ROOT . '/app/views/header.php';
require APP_ROOT . '/app/views/admin_nav.php';
?>
<form class="card border-0 shadow-sm mb-3" method="get">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-6"><label class="form-label small" for="q">搜索</label><input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="用户名、昵称或邮箱"></div>
            <div class="col-md-3"><label class="form-label small" for="status">状态</label>
                <select class="form-select" id="status" name="status">
                    <option value="">全部</option>
                    <option value="active"<?= selected($status, 'active') ?>>正常</option>
                    <option value="disabled"<?= selected($status, 'disabled') ?>>已禁用</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit">筛选</button><a class="btn btn-outline-secondary" href="<?= e(url('/admin/users.php')) ?>">重置</a></div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>ID</th><th>用户</th><th>邮箱</th><th>角色</th><th>状态</th><th>攻略/行程</th><th>注册时间</th><th class="text-end">操作</th></tr></thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td class="text-secondary">#<?= (int) $user['id'] ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <img class="avatar avatar-sm" src="<?= e(avatar_url($user['avatar'])) ?>" alt="">
                            <div><a class="fw-semibold text-decoration-none" href="<?= e(url('/profile.php?id=' . (int) $user['id'])) ?>"><?= e($user['nickname']) ?></a><div class="small text-secondary">@<?= e($user['username']) ?></div></div>
                        </div>
                    </td>
                    <td class="small"><?= e($user['email'] ?? '—') ?></td>
                    <td><?= $user['role'] === 'admin' ? '<span class="badge text-bg-dark">管理员</span>' : '<span class="badge text-bg-light">用户</span>' ?></td>
                    <td><?= $user['status'] === 'active' ? '<span class="badge text-bg-success">正常</span>' : '<span class="badge text-bg-secondary">已禁用</span>' ?></td>
                    <td class="small"><?= (int) $user['guide_count'] ?> / <?= (int) $user['trip_count'] ?></td>
                    <td class="small text-secondary"><?= e(format_date($user['created_at'])) ?></td>
                    <td class="text-end">
                        <?php if ((int) $user['id'] !== (int) $me['id']): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('确定<?= $user['status'] === 'active' ? '禁用' : '启用' ?>用户 <?= e($user['nickname']) ?> 吗？');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                <button class="btn btn-sm <?= $user['status'] === 'active' ? 'btn-outline-danger' : 'btn-outline-success' ?>" type="submit"><?= $user['status'] === 'active' ? '禁用' : '启用' ?></button>
                            </form>
                        <?php else: ?><span class="small text-secondary">当前账号</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
