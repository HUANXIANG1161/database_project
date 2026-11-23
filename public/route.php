<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$tripId = (int) query('trip_id');
$trip = db_one('SELECT * FROM `trip` WHERE `id` = ? LIMIT 1', [$tripId]);
if ($trip === null) {
    http_response_code(404);
    exit('行程不存在。');
}

$me = current_user();
$uid = $me !== null ? (int) $me['id'] : 0;
$isOwner = $me !== null && ((int) $trip['owner_id'] === $uid || is_admin());
$team = db_one('SELECT * FROM `team` WHERE `trip_id` = ? LIMIT 1', [$tripId]);
$isTeamMember = false;
if ($team !== null && $me !== null) {
    $isTeamMember = (int) $team['leader_id'] === $uid
        || (int) db_val('SELECT COUNT(*) FROM `team_member` WHERE `team_id` = ? AND `user_id` = ? AND `status` = "approved"', [(int) $team['id'], $uid]) > 0;
}
if ($trip['visibility'] !== 'public' && !$isOwner && !$isTeamMember) {
    http_response_code(404);
    exit('该行程路线未公开。');
}

$route = db_one(
    'SELECT r.*, u.`nickname` AS `editor_nickname`
     FROM `route` r LEFT JOIN `user` u ON u.`id` = r.`updated_by`
     WHERE r.`trip_id` = ? LIMIT 1',
    [$tripId]
);
$items = [];
$versions = [];
if ($route !== null) {
    $items = db_all(
        'SELECT * FROM `route_item` WHERE `route_id` = ? AND `version_no` = ? ORDER BY `day_no`, `seq`',
        [(int) $route['id'], (int) $route['current_version']]
    );
    $versions = db_all('SELECT * FROM `route_version` WHERE `route_id` = ? ORDER BY `version_no` DESC', [(int) $route['id']]);
}
$days = [];
foreach ($items as $item) {
    $days[(int) $item['day_no']][] = $item;
}
$typeLabels = ['spot' => '景点', 'food' => '美食', 'hotel' => '住宿', 'traffic' => '交通', 'other' => '其他'];

$pageTitle = '路线 · ' . $trip['title'];
$activeNav = 'trips';
require APP_ROOT . '/app/views/header.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(url('/trips.php')) ?>">行程</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('/trip.php?id=' . $tripId)) ?>"><?= e(excerpt($trip['title'], 20)) ?></a></li>
        <li class="breadcrumb-item active">路线</li>
    </ol>
</nav>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">路线安排</h1>
        <p class="text-secondary mb-0"><?= e($trip['destination']) ?> · <?= e(format_date($trip['start_date'])) ?> 至 <?= e(format_date($trip['end_date'])) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('/route_history.php?trip_id=' . $tripId)) ?>">版本历史</a>
        <?php if (can_edit_route($tripId)): ?>
            <a class="btn btn-primary" href="<?= e(url('/route_edit.php?trip_id=' . $tripId)) ?>">编辑路线</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($route === null): ?>
    <div class="alert alert-light border">还没有路线。<?php if (can_edit_route($tripId)): ?><a href="<?= e(url('/route_edit.php?trip_id=' . $tripId)) ?>">现在添加</a><?php endif; ?></div>
<?php else: ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between gap-2">
            <div class="fw-semibold"><?= e($route['title']) ?></div>
            <div class="small text-secondary">当前 v<?= (int) $route['current_version'] ?> · 共 <?= count($versions) ?> 个版本 · <?= count($items) ?> 个条目 · 最后由 <?= e($route['editor_nickname'] ?? '') ?> 更新</div>
        </div>
    </div>
<?php endif; ?>

<?php foreach ($days as $dayNo => $dayItems): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white d-flex align-items-center gap-2">
            <span class="route-day-badge">D<?= (int) $dayNo ?></span>
            <span class="fw-semibold">第 <?= (int) $dayNo ?> 天</span>
            <span class="small text-secondary"><?= count($dayItems) ?> 个安排</span>
        </div>
        <div class="card-body">
            <div class="route-timeline">
                <?php foreach ($dayItems as $item): ?>
                    <div class="route-item">
                        <div class="route-time"><?= e($item['start_time'] !== null ? substr((string) $item['start_time'], 0, 5) : '—') ?><?php if (!empty($item['end_time'])): ?><br><span class="text-secondary"><?= e(substr((string) $item['end_time'], 0, 5)) ?></span><?php endif; ?></div>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="badge text-bg-light"><?= e($typeLabels[$item['item_type']] ?? $item['item_type']) ?></span>
                                <strong><?= e($item['title']) ?></strong>
                                <?php if ((float) $item['cost'] > 0): ?><span class="small text-secondary">¥<?= money($item['cost']) ?></span><?php endif; ?>
                            </div>
                            <?php if (!empty($item['address'])): ?><div class="small text-secondary mt-1"><?= e($item['address']) ?></div><?php endif; ?>
                            <?php if (!empty($item['remark'])): ?><div class="small text-secondary mt-1"><?= e($item['remark']) ?></div><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
