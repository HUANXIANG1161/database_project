<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$me = current_user();
$uid = $me !== null ? (int) $me['id'] : 0;
$q = (string) query('q');
$status = (string) query('status');
if (!in_array($status, ['', 'planning', 'ongoing', 'finished'], true)) {
    $status = '';
}
$page = max(1, (int) query('page', 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

$where = [
    '(t.`visibility` = "public" OR t.`owner_id` = ?
      OR EXISTS (
        SELECT 1 FROM `team` te
        WHERE te.`trip_id` = t.`id`
          AND (te.`leader_id` = ? OR EXISTS (
              SELECT 1 FROM `team_member` tm WHERE tm.`team_id` = te.`id` AND tm.`user_id` = ? AND tm.`status` = "approved"
          ))
      ))',
];
$params = [$uid, $uid, $uid];
if ($q !== '') {
    $where[] = '(t.`title` LIKE ? OR t.`destination` LIKE ? OR t.`description` LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($status !== '') {
    $where[] = 't.`status` = ?';
    $params[] = $status;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$trips = db_all(
    "SELECT t.*, u.`nickname` AS `owner_nickname`,
            te.`id` AS `team_id`, te.`name` AS `team_name`, te.`status` AS `team_status`,
            te.`current_members`, te.`max_members`, te.`leader_id`
     FROM `trip` t
     JOIN `user` u ON u.`id` = t.`owner_id`
     LEFT JOIN `team` te ON te.`trip_id` = t.`id`
     {$whereSql}
     ORDER BY t.`start_date` ASC, t.`id` DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);
$hasNext = count($trips) >= $perPage;

$pageTitle = '行程';
$activeNav = 'trips';
require APP_ROOT . '/app/views/header.php';
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">旅行行程</h1>
        <p class="text-secondary mb-0">公开行程和与你有关的队伍行程，都在这里。</p>
    </div>
    <?php if ($me !== null): ?>
        <a class="btn btn-primary" href="<?= e(url('/trip_edit.php')) ?>">创建行程</a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get" action="<?= e(url('/trips.php')) ?>">
            <div class="col-md-6">
                <label class="form-label small text-secondary" for="q">关键词</label>
                <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="标题 / 目的地">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-secondary" for="status">状态</label>
                <select class="form-select" id="status" name="status">
                    <option value="">全部</option>
                    <option value="planning"<?= selected($status, 'planning') ?>>规划中</option>
                    <option value="ongoing"<?= selected($status, 'ongoing') ?>>进行中</option>
                    <option value="finished"<?= selected($status, 'finished') ?>>已结束</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">筛选</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/trips.php')) ?>">重置</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <?php if (!$trips): ?>
        <div class="col-12"><div class="alert alert-light border">没有找到行程。公开行程会展示在这里，也可以创建自己的行程。</div></div>
    <?php endif; ?>
    <?php foreach ($trips as $t): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card trip-card h-100 border-0 shadow-sm">
                <img src="<?= e(cover_url($t['cover'])) ?>" class="card-img-top trip-cover" alt="<?= e($t['title']) ?>">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <?= status_html($t['status']) ?>
                        <span class="small text-secondary"><?= (int) $t['budget'] > 0 ? '预算 ¥' . money($t['budget']) : '预算待定' ?></span>
                    </div>
                    <h2 class="h5"><a class="text-decoration-none text-body stretched-link" href="<?= e(url('/trip.php?id=' . $t['id'])) ?>"><?= e($t['title']) ?></a></h2>
                    <div class="small text-secondary mb-2"><?= e($t['destination']) ?> · <?= e(format_date($t['start_date'])) ?> 至 <?= e(format_date($t['end_date'])) ?></div>
                    <p class="small text-secondary flex-grow-1"><?= e(excerpt($t['description'], 65)) ?></p>
                    <?php if ($t['team_id'] !== null): ?>
                        <div class="border-top pt-2 d-flex justify-content-between align-items-center small">
                            <span><?= e($t['team_name']) ?> <?= status_html($t['team_status']) ?></span>
                            <span class="text-secondary"><?= (int) $t['current_members'] ?>/<?= (int) $t['max_members'] ?> 人</span>
                        </div>
                    <?php else: ?>
                        <div class="border-top pt-2 small text-secondary">还没有组队</div>
                    <?php endif; ?>
                    <div class="small text-secondary mt-2">发起人 <?= e($t['owner_nickname']) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($page > 1 || $hasNext): ?>
    <nav class="mt-4" aria-label="行程分页">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(with_query(['page' => max(1, $page - 1)])) ?>">上一页</a></li>
            <li class="page-item disabled"><span class="page-link">第 <?= $page ?> 页</span></li>
            <li class="page-item <?= !$hasNext ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(with_query(['page' => $page + 1])) ?>">下一页</a></li>
        </ul>
    </nav>
<?php endif; ?>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
