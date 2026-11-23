<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$id = (int) query('id');
$trip = db_one(
    'SELECT t.*, u.`nickname` AS `owner_nickname`, u.`avatar` AS `owner_avatar`
     FROM `trip` t JOIN `user` u ON u.`id` = t.`owner_id`
     WHERE t.`id` = ? LIMIT 1',
    [$id]
);
if ($trip === null) {
    http_response_code(404);
    exit('行程不存在。');
}

$me = current_user();
$uid = $me !== null ? (int) $me['id'] : 0;
$team = db_one('SELECT * FROM `team` WHERE `trip_id` = ? LIMIT 1', [$id]);
$isOwner = $me !== null && ((int) $trip['owner_id'] === $uid || is_admin());
$isLeader = $team !== null && $me !== null && (int) $team['leader_id'] === $uid;
$memberRow = null;
if ($team !== null && $me !== null) {
    $memberRow = db_one('SELECT * FROM `team_member` WHERE `team_id` = ? AND `user_id` = ? LIMIT 1', [(int) $team['id'], $uid]);
}
$isApproved = $memberRow !== null && $memberRow['status'] === 'approved';
$canView = $trip['visibility'] === 'public' || $isOwner || $isLeader || $isApproved;
if (!$canView) {
    http_response_code(404);
    exit('该行程未公开。');
}

if (is_post()) {
    verify_csrf();
    require_login();
    $action = (string) input('action');

    if ($team === null) {
        flash('danger', '这个行程还没有队伍。');
        redirect('/trip.php?id=' . $id);
    }

    if ($action === 'apply') {
        if ($isOwner || $isLeader || $isApproved) {
            flash('info', '你已经是该队伍的成员。');
            redirect('/trip.php?id=' . $id);
        }
        if ($team['status'] !== 'recruiting' || (int) $team['current_members'] >= (int) $team['max_members']) {
            flash('warning', '队伍当前不可申请。');
            redirect('/trip.php?id=' . $id);
        }
        $reason = mb_substr((string) input('apply_reason'), 0, 255, 'UTF-8');
        db_exec(
            'INSERT INTO `team_member` (`team_id`, `user_id`, `status`, `apply_reason`)
             VALUES (?, ?, "pending", ?)
             ON DUPLICATE KEY UPDATE `status` = "pending", `apply_reason` = ?, `joined_at` = NULL, `updated_at` = NOW()',
            [(int) $team['id'], $uid, $reason !== '' ? $reason : null, $reason !== '' ? $reason : null]
        );
        if ((int) $team['leader_id'] !== $uid) {
            db_exec(
                'INSERT INTO `notification` (`user_id`, `type`, `title`, `content`, `link`)
                 VALUES (?, "team_apply", "有新的入队申请", ?, ?)',
                [(int) $team['leader_id'], $me['nickname'] . ' 申请加入 ' . $team['name'], '/team.php?id=' . $team['id']]
            );
        }
        flash('success', '入队申请已提交，等待队长审核。');
        redirect('/trip.php?id=' . $id);
    }

    if ($action === 'cancel') {
        db_exec('DELETE FROM `team_member` WHERE `team_id` = ? AND `user_id` = ? AND `status` = "pending"', [(int) $team['id'], $uid]);
        flash('success', '已取消申请。');
        redirect('/trip.php?id=' . $id);
    }

    if ($action === 'leave') {
        if (!$isApproved || $isLeader) {
            flash('danger', '队长不能直接退出队伍。');
            redirect('/trip.php?id=' . $id);
        }
        try {
            db()->beginTransaction();
            db_exec('UPDATE `team_member` SET `status` = "quit", `joined_at` = NULL, `updated_at` = NOW() WHERE `id` = ?', [(int) $memberRow['id']]);
            db_exec('UPDATE `team` SET `current_members` = GREATEST(`current_members` - 1, 1), `status` = IF(`status` = "full", "recruiting", `status`) WHERE `id` = ?', [(int) $team['id']]);
            db()->commit();
            flash('success', '已退出队伍。');
        } catch (Throwable $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('danger', '退出失败：' . $ex->getMessage());
        }
        redirect('/trip.php?id=' . $id);
    }
}

$members = [];
$pendingCount = 0;
$notices = [];
if ($team !== null) {
    $members = db_all(
        'SELECT tm.*, u.`nickname`, u.`avatar`, u.`username`
         FROM `team_member` tm JOIN `user` u ON u.`id` = tm.`user_id`
         WHERE tm.`team_id` = ? AND tm.`status` = "approved"
         ORDER BY tm.`joined_at` ASC, tm.`id` ASC',
        [(int) $team['id']]
    );
    if ($isLeader || is_admin()) {
        $pendingCount = (int) db_val('SELECT COUNT(*) FROM `team_member` WHERE `team_id` = ? AND `status` = "pending"', [(int) $team['id']]);
    }
    $notices = db_all(
        'SELECT n.*, u.`nickname` AS `publisher_nickname`
         FROM `team_notice` n JOIN `user` u ON u.`id` = n.`publisher_id`
         WHERE n.`team_id` = ? ORDER BY n.`created_at` DESC LIMIT 5',
        [(int) $team['id']]
    );
}

$route = db_one('SELECT * FROM `route` WHERE `trip_id` = ? LIMIT 1', [$id]);
$routeItems = [];
if ($route !== null) {
    $routeItems = db_all(
        'SELECT * FROM `route_item` WHERE `route_id` = ? AND `version_no` = ? ORDER BY `day_no`, `seq`',
        [(int) $route['id'], (int) $route['current_version']]
    );
}
$days = [];
foreach ($routeItems as $item) {
    $days[(int) $item['day_no']][] = $item;
}

$typeLabels = ['spot' => '景点', 'food' => '美食', 'hotel' => '住宿', 'traffic' => '交通', 'other' => '其他'];
$pageTitle = $trip['title'];
$activeNav = 'trips';
require APP_ROOT . '/app/views/header.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(url('/index.php')) ?>">首页</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('/trips.php')) ?>">行程</a></li>
        <li class="breadcrumb-item active"><?= e(excerpt($trip['title'], 24)) ?></li>
    </ol>
</nav>

<div class="card border-0 shadow-sm overflow-hidden mb-4">
    <div class="trip-hero" style="background-image:url('<?= e(cover_url($trip['cover'])) ?>')">
        <div class="trip-hero-overlay p-4 p-lg-5">
            <div class="d-flex flex-wrap gap-2 mb-2">
                <?= status_html($trip['status']) ?>
                <span class="badge text-bg-light"><?= e($trip['visibility'] === 'public' ? '公开' : ($trip['visibility'] === 'team' ? '队伍可见' : '私密')) ?></span>
            </div>
            <h1 class="display-6 fw-bold text-white mb-2"><?= e($trip['title']) ?></h1>
            <div class="text-white-50 mb-3"><?= e($trip['destination']) ?> · <?= e(format_date($trip['start_date'])) ?> 至 <?= e(format_date($trip['end_date'])) ?> · 预算 ¥<?= money($trip['budget']) ?></div>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($isOwner): ?>
                    <a class="btn btn-light" href="<?= e(url('/trip_edit.php?id=' . $id)) ?>">编辑行程</a>
                <?php endif; ?>
                <?php if ($team !== null): ?>
                    <a class="btn btn-outline-light" href="<?= e(url('/team.php?id=' . $team['id'])) ?>">队伍详情</a>
                <?php elseif ($isOwner): ?>
                    <a class="btn btn-light" href="<?= e(url('/team_edit.php?trip_id=' . $id)) ?>">组建队伍</a>
                <?php endif; ?>
                <a class="btn btn-outline-light" href="<?= e(url('/route.php?trip_id=' . $id)) ?>">查看路线</a>
                <?php if (can_edit_route($id)): ?>
                    <a class="btn btn-warning" href="<?= e(url('/route_edit.php?trip_id=' . $id)) ?>">编辑路线</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-body p-4">
        <p class="mb-0 text-secondary"><?= nl2br(e($trip['description'] ?? '这个行程还没有描述。')) ?></p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">路线安排</h2>
                    <?php if ($route !== null): ?>
                        <span class="small text-secondary">当前版本 v<?= (int) $route['current_version'] ?> · <?= count($routeItems) ?> 个条目</span>
                    <?php endif; ?>
                </div>
                <?php if ($route === null || !$routeItems): ?>
                    <div class="text-secondary">还没有路线。<?php if (can_edit_route($id)): ?><a href="<?= e(url('/route_edit.php?trip_id=' . $id)) ?>">去添加第一天</a><?php endif; ?></div>
                <?php endif; ?>
                <?php foreach ($days as $dayNo => $items): ?>
                    <div class="route-day mb-4">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="route-day-badge">D<?= (int) $dayNo ?></span>
                            <span class="text-secondary small"><?= count($items) ?> 个安排</span>
                        </div>
                        <div class="route-timeline">
                            <?php foreach ($items as $item): ?>
                                <div class="route-item">
                                    <div class="route-time"><?= e($item['start_time'] !== null ? substr((string) $item['start_time'], 0, 5) : '—') ?></div>
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
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">队伍</h2>
                    <?php if ($team !== null && ($isLeader || is_admin())): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/team.php?id=' . $team['id'])) ?>">管理</a>
                    <?php endif; ?>
                </div>
                <?php if ($team === null): ?>
                    <div class="text-secondary mb-2">还没有队伍。</div>
                    <?php if ($isOwner): ?><a class="btn btn-primary btn-sm" href="<?= e(url('/team_edit.php?trip_id=' . $id)) ?>">组建队伍</a><?php endif; ?>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-semibold"><?= e($team['name']) ?></div>
                            <div class="small text-secondary"><?= e($team['description'] ?? '') ?></div>
                        </div>
                        <?= status_html($team['status']) ?>
                    </div>
                    <div class="small text-secondary mb-3"><?= (int) $team['current_members'] ?>/<?= (int) $team['max_members'] ?> 人<?php if ($pendingCount > 0): ?> · <?= $pendingCount ?> 个待审核申请<?php endif; ?></div>

                    <div class="vstack gap-2 mb-3">
                        <?php foreach ($members as $m): ?>
                            <div class="d-flex align-items-center gap-2">
                                <img src="<?= e(avatar_url($m['avatar'])) ?>" class="rounded-circle" width="30" height="30" alt="">
                                <span class="small"><?= e($m['nickname']) ?></span>
                                <?php if ((int) $m['user_id'] === (int) $team['leader_id']): ?><span class="badge text-bg-primary">队长</span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($me === null): ?>
                        <a class="btn btn-primary w-100" href="<?= e(url('/login.php?next=' . urlencode('/trip.php?id=' . $id))) ?>">登录后申请加入</a>
                    <?php elseif ($isOwner || $isLeader): ?>
                        <div class="alert alert-light border small mb-0">你是队伍管理者，可在队伍详情中审核申请、发布公告。</div>
                    <?php elseif ($isApproved): ?>
                        <form method="post" action="<?= e(url('/trip.php?id=' . $id)) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="leave">
                            <button class="btn btn-outline-danger w-100" type="submit">退出队伍</button>
                        </form>
                    <?php elseif ($memberRow !== null && $memberRow['status'] === 'pending'): ?>
                        <form method="post" action="<?= e(url('/trip.php?id=' . $id)) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel">
                            <button class="btn btn-outline-secondary w-100" type="submit">已申请，取消申请</button>
                        </form>
                    <?php elseif ($team['status'] === 'recruiting' && (int) $team['current_members'] < (int) $team['max_members']): ?>
                        <form method="post" action="<?= e(url('/trip.php?id=' . $id)) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="apply">
                            <label class="form-label small" for="apply_reason">申请理由</label>
                            <textarea class="form-control form-control-sm mb-2" id="apply_reason" name="apply_reason" rows="2" maxlength="255" placeholder="简单介绍自己，方便队长审核"></textarea>
                            <button class="btn btn-primary w-100" type="submit">申请加入</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-light border small mb-0">队伍当前不可申请。</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($notices): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">队伍公告</h2>
                    <?php foreach ($notices as $n): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <div class="fw-semibold small"><?= e($n['title']) ?></div>
                            <div class="small text-secondary"><?= e($n['content']) ?></div>
                            <div class="text-secondary" style="font-size:.75rem"><?= e($n['publisher_nickname']) ?> · <?= e(format_dt($n['created_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
