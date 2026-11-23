<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $id = (int) input('id');
    $op = (string) input('op');
    $guide = db_one('SELECT * FROM `guide` WHERE `id` = ?', [$id]);
    $allowed = ['published', 'rejected', 'hidden'];
    if ($guide === null) {
        flash('danger', '攻略不存在。');
    } elseif (!in_array($op, $allowed, true)) {
        flash('danger', '操作不合法。');
    } else {
        db_exec('UPDATE `guide` SET `status` = ? WHERE `id` = ?', [$op, $id]);
        $labels = ['published' => '已通过审核', 'rejected' => '未通过审核', 'hidden' => '已被隐藏'];
        db_exec(
            'INSERT INTO `notification` (`user_id`, `type`, `title`, `content`, `link`) VALUES (?, "guide_review", ?, ?, ?)',
            [(int) $guide['author_id'], '攻略审核结果', '你的攻略《' . $guide['title'] . '》' . $labels[$op] . '。', '/guide.php?id=' . $id]
        );
        flash('success', '攻略状态已更新为：' . $labels[$op] . '。');
    }
    redirect('/admin/guides.php' . (query('status') !== '' ? '?status=' . rawurlencode((string) query('status')) : ''));
}

$status = (string) query('status');
if (!in_array($status, ['', 'pending', 'published', 'rejected', 'hidden'], true)) {
    $status = '';
}
$q = trim((string) query('q'));
$where = ['1=1'];
$params = [];
if ($status !== '') {
    $where[] = 'g.`status` = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(g.`title` LIKE ? OR g.`destination` LIKE ? OR u.`nickname` LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
$guides = db_all(
    'SELECT g.*, u.`nickname`, u.`username`
     FROM `guide` g JOIN `user` u ON u.`id` = g.`author_id`
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY FIELD(g.`status`, "pending", "published", "rejected", "hidden"), g.`created_at` DESC
     LIMIT 100',
    $params
);
$counts = [
    'pending'   => (int) db_val('SELECT COUNT(*) FROM `guide` WHERE `status` = "pending"'),
    'published' => (int) db_val('SELECT COUNT(*) FROM `guide` WHERE `status` = "published"'),
    'rejected'  => (int) db_val('SELECT COUNT(*) FROM `guide` WHERE `status` = "rejected"'),
    'hidden'    => (int) db_val('SELECT COUNT(*) FROM `guide` WHERE `status` = "hidden"'),
];

$pageTitle = '攻略审核';
$activeNav = 'admin';
require APP_ROOT . '/app/views/header.php';
require APP_ROOT . '/app/views/admin_nav.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="btn-group">
        <a class="btn btn-sm <?= $status === '' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e(url('/admin/guides.php')) ?>">全部</a>
        <?php foreach (['pending' => '待审核', 'published' => '已发布', 'rejected' => '已拒绝', 'hidden' => '已隐藏'] as $key => $label): ?>
            <a class="btn btn-sm <?= $status === $key ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= e(url('/admin/guides.php?status=' . $key)) ?>"><?= e($label) ?>（<?= $counts[$key] ?>）</a>
        <?php endforeach; ?>
    </div>
    <form class="d-flex gap-2" method="get">
        <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="标题/目的地/作者">
        <button class="btn btn-sm btn-outline-secondary" type="submit">搜索</button>
    </form>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>攻略</th><th>作者</th><th>状态</th><th>数据</th><th>创建时间</th><th class="text-end">操作</th></tr></thead>
            <tbody>
            <?php if (!$guides): ?><tr><td colspan="6" class="text-center text-secondary py-4">没有符合条件的攻略。</td></tr><?php endif; ?>
            <?php foreach ($guides as $guide): ?>
                <tr>
                    <td>
                        <a class="fw-semibold text-decoration-none" href="<?= e(url('/guide.php?id=' . (int) $guide['id'])) ?>"><?= e(excerpt($guide['title'], 30)) ?></a>
                        <div class="small text-secondary"><?= e($guide['destination']) ?> · <?= (int) $guide['day_count'] ?> 天</div>
                    </td>
                    <td class="small"><?= e($guide['nickname']) ?><div class="text-secondary">@<?= e($guide['username']) ?></div></td>
                    <td><?= status_html($guide['status']) ?></td>
                    <td class="small text-secondary">浏览 <?= (int) $guide['view_count'] ?><br>赞 <?= (int) $guide['like_count'] ?> · 评 <?= (int) $guide['comment_count'] ?></td>
                    <td class="small text-secondary"><?= e(format_dt($guide['created_at'])) ?></td>
                    <td class="text-end">
                        <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                            <?php if ($guide['status'] === 'pending'): ?>
                                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $guide['id'] ?>"><input type="hidden" name="op" value="published"><button class="btn btn-sm btn-success" type="submit">通过</button></form>
                                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $guide['id'] ?>"><input type="hidden" name="op" value="rejected"><button class="btn btn-sm btn-outline-danger" type="submit">拒绝</button></form>
                            <?php elseif ($guide['status'] === 'published'): ?>
                                <form method="post" onsubmit="return confirm('隐藏后前台将不可见，确定吗？');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $guide['id'] ?>"><input type="hidden" name="op" value="hidden"><button class="btn btn-sm btn-outline-secondary" type="submit">隐藏</button></form>
                            <?php else: ?>
                                <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $guide['id'] ?>"><input type="hidden" name="op" value="published"><button class="btn btn-sm btn-outline-success" type="submit">发布</button></form>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/guide.php?id=' . (int) $guide['id'])) ?>">查看</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
