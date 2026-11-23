<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';
require_admin();

$stats = [
    'users'        => (int) db_val('SELECT COUNT(*) FROM `user` WHERE `status` = "active"'),
    'guides'       => (int) db_val('SELECT COUNT(*) FROM `guide` WHERE `status` = "published"'),
    'trips'        => (int) db_val('SELECT COUNT(*) FROM `trip`'),
    'resources'    => (int) db_val('SELECT COUNT(*) FROM `resource` WHERE `status` = "published"'),
    'pending_guide'=> (int) db_val('SELECT COUNT(*) FROM `guide` WHERE `status` = "pending"'),
    'pending_res'  => (int) db_val('SELECT COUNT(*) FROM `resource` WHERE `status` = "pending"'),
    'messages'     => (int) db_val('SELECT COUNT(*) FROM `message`'),
    'reports'      => (int) db_val('SELECT COUNT(*) FROM `notification` WHERE `is_read` = 0'),
];
$pendingGuides = db_all(
    'SELECT g.`id`, g.`title`, g.`destination`, g.`created_at`, u.`nickname`
     FROM `guide` g JOIN `user` u ON u.`id` = g.`author_id`
     WHERE g.`status` = "pending" ORDER BY g.`created_at` ASC LIMIT 5'
);
$pendingResources = db_all(
    'SELECT r.`id`, r.`title`, r.`type`, r.`created_at`, u.`nickname`
     FROM `resource` r JOIN `user` u ON u.`id` = r.`uploader_id`
     WHERE r.`status` = "pending" ORDER BY r.`created_at` ASC LIMIT 5'
);

$pageTitle = '管理后台';
$activeNav = 'admin';
require APP_ROOT . '/app/views/header.php';
require APP_ROOT . '/app/views/admin_nav.php';
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-secondary small">活跃用户</div><div class="display-6 fw-semibold"><?= $stats['users'] ?></div>
            <a class="small" href="<?= e(url('/admin/users.php')) ?>">管理用户 →</a>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-secondary small">已发布攻略</div><div class="display-6 fw-semibold"><?= $stats['guides'] ?></div>
            <a class="small" href="<?= e(url('/admin/guides.php')) ?>">审核攻略 →</a>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-secondary small">行程总数</div><div class="display-6 fw-semibold"><?= $stats['trips'] ?></div>
            <span class="small text-secondary">含私密行程</span>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-secondary small">已发布资源</div><div class="display-6 fw-semibold"><?= $stats['resources'] ?></div>
            <a class="small" href="<?= e(url('/admin/resources.php')) ?>">审核资源 →</a>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="alert alert-warning mb-0">待审攻略：<strong><?= $stats['pending_guide'] ?></strong></div></div>
    <div class="col-md-4"><div class="alert alert-warning mb-0">待审资源：<strong><?= $stats['pending_res'] ?></strong></div></div>
    <div class="col-md-4"><div class="alert alert-light border mb-0">未读通知：<strong><?= $stats['reports'] ?></strong> · 私聊消息：<strong><?= $stats['messages'] ?></strong></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between"><span class="fw-semibold">待审攻略</span><a class="small" href="<?= e(url('/admin/guides.php?status=pending')) ?>">全部</a></div>
            <div class="list-group list-group-flush">
                <?php if (!$pendingGuides): ?><div class="list-group-item text-secondary small">没有待审攻略。</div><?php endif; ?>
                <?php foreach ($pendingGuides as $guide): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div><a class="fw-semibold text-decoration-none" href="<?= e(url('/guide.php?id=' . (int) $guide['id'])) ?>"><?= e(excerpt($guide['title'], 24)) ?></a><div class="small text-secondary"><?= e($guide['nickname']) ?> · <?= e($guide['destination']) ?> · <?= e(format_dt($guide['created_at'])) ?></div></div>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/admin/guides.php?status=pending')) ?>">审核</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between"><span class="fw-semibold">待审资源</span><a class="small" href="<?= e(url('/admin/resources.php?status=pending')) ?>">全部</a></div>
            <div class="list-group list-group-flush">
                <?php if (!$pendingResources): ?><div class="list-group-item text-secondary small">没有待审资源。</div><?php endif; ?>
                <?php foreach ($pendingResources as $resource): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div><a class="fw-semibold text-decoration-none" href="<?= e(url('/resource.php?id=' . (int) $resource['id'])) ?>"><?= e(excerpt($resource['title'], 24)) ?></a><div class="small text-secondary"><?= e($resource['nickname']) ?> · <?= e($resource['type']) ?> · <?= e(format_dt($resource['created_at'])) ?></div></div>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/admin/resources.php?status=pending')) ?>">审核</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
