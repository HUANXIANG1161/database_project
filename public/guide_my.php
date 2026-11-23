<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_login();

$me = current_user();
$guides = db_all(
    'SELECT * FROM `guide` WHERE `author_id` = ? ORDER BY `updated_at` DESC, `id` DESC',
    [(int) $me['id']]
);

$pageTitle = '我的攻略';
$activeNav = 'guides';
require APP_ROOT . '/app/views/header.php';
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">我的攻略</h1>
        <p class="text-secondary mb-0">草稿、待审核和已发布的内容都在这里。</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/guide_edit.php')) ?>">写一篇攻略</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th>标题</th>
                <th>目的地</th>
                <th>状态</th>
                <th>数据</th>
                <th>更新时间</th>
                <th class="text-end">操作</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$guides): ?>
                <tr><td colspan="6" class="text-center text-secondary py-4">还没有攻略，点击右上角开始写第一篇。</td></tr>
            <?php endif; ?>
            <?php foreach ($guides as $g): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($g['title']) ?></div>
                        <div class="small text-secondary"><?= e(excerpt($g['summary'], 45)) ?></div>
                    </td>
                    <td><?= e($g['destination']) ?></td>
                    <td><?= status_html($g['status']) ?></td>
                    <td class="small text-secondary">浏览 <?= (int) $g['view_count'] ?><br>赞 <?= (int) $g['like_count'] ?> · 藏 <?= (int) $g['collect_count'] ?> · 评 <?= (int) $g['comment_count'] ?></td>
                    <td class="small text-secondary"><?= e(format_dt($g['updated_at'])) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/guide.php?id=' . $g['id'])) ?>">查看</a>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/guide_edit.php?id=' . $g['id'])) ?>">编辑</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
