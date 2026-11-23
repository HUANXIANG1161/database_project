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
$route = db_one('SELECT * FROM `route` WHERE `trip_id` = ? LIMIT 1', [$tripId]);
if ($route === null) {
    flash('info', '该行程还没有路线。');
    redirect('/route_edit.php?trip_id=' . $tripId);
}

$canEdit = can_edit_route($tripId);
if (is_post() && (string) input('action') === 'restore') {
    if (!$canEdit) {
        http_response_code(403);
        exit('403：无权恢复路线版本。');
    }
    verify_csrf();
    $source = (int) input('version_no');
    $sourceVersion = db_one('SELECT * FROM `route_version` WHERE `route_id` = ? AND `version_no` = ?', [(int) $route['id'], $source]);
    if ($sourceVersion === null) {
        flash('danger', '要恢复的版本不存在。');
        redirect('/route_history.php?trip_id=' . $tripId);
    }
    try {
        db()->beginTransaction();
        $locked = db_one('SELECT `current_version` FROM `route` WHERE `id` = ? FOR UPDATE', [(int) $route['id']]);
        $newVersion = (int) $locked['current_version'] + 1;
        db_exec(
            'INSERT INTO `route_version` (`route_id`, `version_no`, `editor_id`, `remark`) VALUES (?, ?, ?, ?)',
            [(int) $route['id'], $newVersion, (int) $me['id'], '恢复自 v' . $source]
        );
        db_exec(
            'INSERT INTO `route_item` (`route_id`, `version_no`, `day_no`, `seq`, `item_type`, `title`, `address`, `start_time`, `end_time`, `cost`, `remark`)
             SELECT `route_id`, ?, `day_no`, `seq`, `item_type`, `title`, `address`, `start_time`, `end_time`, `cost`, `remark`
             FROM `route_item` WHERE `route_id` = ? AND `version_no` = ?',
            [$newVersion, (int) $route['id'], $source]
        );
        db_exec('UPDATE `route` SET `current_version` = ?, `updated_by` = ? WHERE `id` = ?', [$newVersion, (int) $me['id'], (int) $route['id']]);
        db()->commit();
        flash('success', '已把 v' . $source . ' 恢复为新的 v' . $newVersion . '。');
    } catch (Throwable $ex) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('danger', '恢复失败：' . $ex->getMessage());
    }
    redirect('/route_history.php?trip_id=' . $tripId . '&version=' . $newVersion);
}

$versions = db_all(
    'SELECT rv.*, u.`nickname` AS `editor_nickname`,
            (SELECT COUNT(*) FROM `route_item` ri WHERE ri.`route_id` = rv.`route_id` AND ri.`version_no` = rv.`version_no`) AS `item_count`
     FROM `route_version` rv LEFT JOIN `user` u ON u.`id` = rv.`editor_id`
     WHERE rv.`route_id` = ? ORDER BY rv.`version_no` DESC',
    [(int) $route['id']]
);
$selected = (int) query('version', (string) $route['current_version']);
if (!db_one('SELECT 1 FROM `route_version` WHERE `route_id` = ? AND `version_no` = ?', [(int) $route['id'], $selected])) {
    $selected = (int) $route['current_version'];
}
$items = db_all(
    'SELECT * FROM `route_item` WHERE `route_id` = ? AND `version_no` = ? ORDER BY `day_no`, `seq`',
    [(int) $route['id'], $selected]
);
$days = [];
foreach ($items as $item) {
    $days[(int) $item['day_no']][] = $item;
}
$typeLabels = ['spot' => '景点', 'food' => '美食', 'hotel' => '住宿', 'traffic' => '交通', 'other' => '其他'];

$pageTitle = '路线版本历史';
$activeNav = 'trips';
require APP_ROOT . '/app/views/header.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(url('/trips.php')) ?>">行程</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('/trip.php?id=' . $tripId)) ?>"><?= e(excerpt($trip['title'], 20)) ?></a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('/route.php?trip_id=' . $tripId)) ?>">路线</a></li>
        <li class="breadcrumb-item active">版本历史</li>
    </ol>
</nav>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">路线版本历史</h1>
        <p class="text-secondary mb-0">当前为 v<?= (int) $route['current_version'] ?>。每次保存都会保留旧版本，可查看或恢复。</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/route.php?trip_id=' . $tripId)) ?>">返回路线</a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="list-group shadow-sm">
            <?php foreach ($versions as $version): ?>
                <?php $isCurrent = (int) $version['version_no'] === (int) $route['current_version']; ?>
                <div class="list-group-item<?= (int) $version['version_no'] === $selected ? ' active' : '' ?>">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <a class="fw-semibold<?= (int) $version['version_no'] === $selected ? ' text-white' : '' ?>" href="<?= e(url('/route_history.php?trip_id=' . $tripId . '&version=' . (int) $version['version_no'])) ?>">v<?= (int) $version['version_no'] ?><?= $isCurrent ? '（当前）' : '' ?></a>
                            <div class="small <?= (int) $version['version_no'] === $selected ? 'text-white-50' : 'text-secondary' ?>">
                                <?= e($version['editor_nickname'] ?? '未知') ?> · <?= e(format_dt($version['created_at'])) ?> · <?= (int) $version['item_count'] ?> 条
                            </div>
                            <?php if (!empty($version['remark'])): ?><div class="small <?= (int) $version['version_no'] === $selected ? 'text-white-50' : 'text-secondary' ?>"><?= e($version['remark']) ?></div><?php endif; ?>
                        </div>
                        <?php if ($canEdit && !$isCurrent): ?>
                            <form method="post" onsubmit="return confirm('把 v<?= (int) $version['version_no'] ?> 恢复为新版本？');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="version_no" value="<?= (int) $version['version_no'] ?>">
                                <button class="btn btn-sm btn-outline-primary" type="submit">恢复</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">v<?= $selected ?> 的内容</span>
                <span class="small text-secondary"><?= count($items) ?> 个条目</span>
            </div>
            <div class="card-body">
                <?php if (!$items): ?>
                    <p class="text-secondary mb-0">该版本没有条目。</p>
                <?php endif; ?>
                <?php foreach ($days as $dayNo => $dayItems): ?>
                    <h2 class="h6 mt-3"><span class="route-day-badge">D<?= (int) $dayNo ?></span> 第 <?= (int) $dayNo ?> 天</h2>
                    <div class="route-timeline">
                        <?php foreach ($dayItems as $item): ?>
                            <div class="route-item">
                                <div class="route-time"><?= e($item['start_time'] !== null ? substr((string) $item['start_time'], 0, 5) : '—') ?></div>
                                <div>
                                    <span class="badge text-bg-light"><?= e($typeLabels[$item['item_type']] ?? $item['item_type']) ?></span>
                                    <strong><?= e($item['title']) ?></strong>
                                    <?php if ((float) $item['cost'] > 0): ?><span class="small text-secondary">¥<?= money($item['cost']) ?></span><?php endif; ?>
                                    <?php if (!empty($item['address'])): ?><div class="small text-secondary"><?= e($item['address']) ?></div><?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
