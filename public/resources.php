<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$q = query('q');
$type = query('type');
$tripFilter = (int) query('trip_id');
$sort = query('sort', 'new');
if (!in_array($type, ['', 'photo', 'video', 'doc'], true)) {
    $type = '';
}
if (!in_array($sort, ['new', 'hot'], true)) {
    $sort = 'new';
}
$page = max(1, (int) query('page', '1'));
$perPage = (int) config('site.per_page', 9);
$offset = ($page - 1) * $perPage;

$where = ['r.`status` = "published"'];
$params = [];
if ($q !== '') {
    $where[] = '(r.`title` LIKE ? OR r.`description` LIKE ? OR r.`location` LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($type !== '') {
    $where[] = 'r.`type` = ?';
    $params[] = $type;
}
if ($tripFilter > 0) {
    $where[] = 'r.`trip_id` = ?';
    $params[] = $tripFilter;
}
$whereSql = implode(' AND ', $where);
$orderSql = $sort === 'hot'
    ? 'r.`like_count` DESC, r.`view_count` DESC, r.`created_at` DESC'
    : 'r.`created_at` DESC';

$total = (int) db_val('SELECT COUNT(*) FROM `resource` r WHERE ' . $whereSql, $params);
$resources = db_all(
    'SELECT r.*, u.`nickname`, u.`avatar`, t.`title` AS `trip_title`
     FROM `resource` r
     JOIN `user` u ON u.`id` = r.`uploader_id`
     LEFT JOIN `trip` t ON t.`id` = r.`trip_id`
     WHERE ' . $whereSql . '
     ORDER BY ' . $orderSql . '
     LIMIT ' . $perPage . ' OFFSET ' . $offset,
    $params
);
$totalPages = max(1, (int) ceil($total / $perPage));
$typeLabels = ['photo' => '照片', 'video' => '视频', 'doc' => '文档'];
$typeIcons = ['photo' => '📷', 'video' => '🎬', 'doc' => '📄'];

$pageTitle = '旅行资源';
$activeNav = 'resources';
require APP_ROOT . '/app/views/header.php';
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">旅行资源</h1>
        <p class="text-secondary mb-0">旅行结束后分享照片、视频和文档，共 <?= $total ?> 个资源。</p>
    </div>
    <?php if (current_user() !== null): ?>
        <a class="btn btn-primary" href="<?= e(url('/resource_upload.php')) ?>">上传资源</a>
    <?php endif; ?>
</div>

<form class="card border-0 shadow-sm mb-4" method="get" action="<?= e(url('/resources.php')) ?>">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small" for="q">关键词</label>
                <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="标题、描述或地点">
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="type">类型</label>
                <select class="form-select" id="type" name="type">
                    <option value="">全部</option>
                    <?php foreach ($typeLabels as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($type, $key) ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small" for="sort">排序</label>
                <select class="form-select" id="sort" name="sort">
                    <option value="new"<?= selected($sort, 'new') ?>>最新发布</option>
                    <option value="hot"<?= selected($sort, 'hot') ?>>最受欢迎</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" type="submit">筛选</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/resources.php')) ?>">重置</a>
            </div>
        </div>
        <?php if ($tripFilter > 0): ?><input type="hidden" name="trip_id" value="<?= $tripFilter ?>"><?php endif; ?>
    </div>
</form>

<?php if (!$resources): ?>
    <div class="alert alert-light border">没有找到资源。<?php if (current_user() !== null): ?><a href="<?= e(url('/resource_upload.php')) ?>">上传第一个</a><?php endif; ?></div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($resources as $resource): ?>
            <div class="col-sm-6 col-lg-4">
                <?php $thumb = resource_thumb($resource); ?>
                <div class="card h-100 border-0 shadow-sm resource-card">
                    <a href="<?= e(url('/resource.php?id=' . (int) $resource['id'])) ?>" class="resource-cover">
                        <?php if ($thumb !== ''): ?>
                            <img src="<?= e($thumb) ?>" alt="<?= e($resource['title']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="resource-cover-placeholder"><?= $typeIcons[$resource['type']] ?? '📁' ?></div>
                        <?php endif; ?>
                        <span class="badge text-bg-dark resource-type"><?= e($typeLabels[$resource['type']] ?? $resource['type']) ?></span>
                    </a>
                    <div class="card-body">
                        <h2 class="h6"><a class="stretched-link text-decoration-none" href="<?= e(url('/resource.php?id=' . (int) $resource['id'])) ?>"><?= e($resource['title']) ?></a></h2>
                        <p class="small text-secondary mb-2"><?= e(excerpt($resource['description'] ?? '', 55)) ?></p>
                        <div class="small text-secondary d-flex flex-wrap gap-2">
                            <span><?= e($resource['nickname']) ?></span>
                            <?php if (!empty($resource['location'])): ?><span>· <?= e($resource['location']) ?></span><?php endif; ?>
                            <?php if (!empty($resource['trip_title'])): ?><span>· <?= e(excerpt($resource['trip_title'], 16)) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer bg-white small text-secondary d-flex justify-content-between">
                        <span>浏览 <?= (int) $resource['view_count'] ?></span>
                        <span>赞 <?= (int) $resource['like_count'] ?> · 评论 <?= (int) $resource['comment_count'] ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-4"><ul class="pagination justify-content-center">
            <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e(url('/resources.php' . with_query(['page' => max(1, $page - 1)]))) ?>">上一页</a></li>
            <li class="page-item disabled"><span class="page-link">第 <?= $page ?> / <?= $totalPages ?> 页</span></li>
            <li class="page-item<?= $page >= $totalPages ? ' disabled' : '' ?>"><a class="page-link" href="<?= e(url('/resources.php' . with_query(['page' => min($totalPages, $page + 1)]))) ?>">下一页</a></li>
        </ul></nav>
    <?php endif; ?>
<?php endif; ?>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
