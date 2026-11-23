<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$me = current_user();
$uid = $me !== null ? (int) $me['id'] : 0;
$q = (string) query('q');
$status = (string) query('status', 'recruiting');
if (!in_array($status, ['recruiting', 'full', 'closed', ''], true)) {
    $status = 'recruiting';
}
$page = max(1, (int) query('page', 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

$where = [
    '(tr.`visibility` = "public" OR tr.`owner_id` = ? OR vc.`leader_id` = ?
      OR EXISTS (SELECT 1 FROM `team_member` tm WHERE tm.`team_id` = vc.`id` AND tm.`user_id` = ? AND tm.`status` = "approved")
      OR (? > 0 AND tr.`visibility` = "team" AND vc.`status` = "recruiting"))',
];
$params = [$uid, $uid, $uid, $uid];
if ($q !== '') {
    $where[] = '(vc.`name` LIKE ? OR vc.`trip_title` LIKE ? OR vc.`destination` LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($status !== '') {
    $where[] = 'vc.`status` = ?';
    $params[] = $status;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$teams = db_all(
    "SELECT vc.*, tr.`visibility`
     FROM `v_team_card` vc
     JOIN `trip` tr ON tr.`id` = vc.`trip_id`
     {$whereSql}
     ORDER BY vc.`created_at` DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);
$hasNext = count($teams) >= $perPage;

$pageTitle = '队伍';
$activeNav = 'teams';
require APP_ROOT . '/app/views/header.php';
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">旅行队伍</h1>
        <p class="text-secondary mb-0">找到同路人，一起分摊路线与住宿。</p>
    </div>
    <?php if ($me !== null): ?>
        <a class="btn btn-outline-primary" href="<?= e(url('/trip_my.php')) ?>">从我的行程组队</a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get" action="<?= e(url('/teams.php')) ?>">
            <div class="col-md-6">
                <label class="form-label small text-secondary" for="q">关键词</label>
                <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="队伍 / 行程 / 目的地">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-secondary" for="status">状态</label>
                <select class="form-select" id="status" name="status">
                    <option value="recruiting"<?= selected($status, 'recruiting') ?>>招募中</option>
                    <option value="full"<?= selected($status, 'full') ?>>已满员</option>
                    <option value="closed"<?= selected($status, 'closed') ?>>已关闭</option>
                    <option value=""<?= selected($status, '') ?>>全部</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">筛选</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/teams.php')) ?>">重置</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3">
    <?php if (!$teams): ?>
        <div class="col-12"><div class="alert alert-light border">没有找到队伍。可以先创建行程，再组建自己的队伍。</div></div>
    <?php endif; ?>
    <?php foreach ($teams as $t): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <?= status_html($t['status']) ?>
                        <span class="small text-secondary"><?= (int) $t['current_members'] ?>/<?= (int) $t['max_members'] ?> 人</span>
                    </div>
                    <h2 class="h5"><a class="text-decoration-none text-body stretched-link" href="<?= e(url('/team.php?id=' . $t['id'])) ?>"><?= e($t['name']) ?></a></h2>
                    <div class="small text-secondary mb-2"><?= e($t['trip_title']) ?> · <?= e($t['destination']) ?></div>
                    <p class="small text-secondary flex-grow-1"><?= e(excerpt($t['description'], 70)) ?></p>
                    <div class="border-top pt-2 d-flex justify-content-between small text-secondary">
                        <span>队长 <?= e($t['leader_nickname']) ?></span>
                        <span><?= e(format_date($t['start_date'])) ?> 出发</span>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($page > 1 || $hasNext): ?>
    <nav class="mt-4" aria-label="队伍分页">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(with_query(['page' => max(1, $page - 1)])) ?>">上一页</a></li>
            <li class="page-item disabled"><span class="page-link">第 <?= $page ?> 页</span></li>
            <li class="page-item <?= !$hasNext ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(with_query(['page' => $page + 1])) ?>">下一页</a></li>
        </ul>
    </nav>
<?php endif; ?>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
