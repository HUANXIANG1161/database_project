<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$id = (int) query('id');
$team = db_one(
    'SELECT te.*, tr.`title` AS `trip_title`, tr.`destination`, tr.`start_date`, tr.`end_date`, tr.`visibility`,
            tr.`owner_id` AS `trip_owner_id`, u.`nickname` AS `leader_nickname`, u.`avatar` AS `leader_avatar`
     FROM `team` te
     JOIN `trip` tr ON tr.`id` = te.`trip_id`
     JOIN `user` u ON u.`id` = te.`leader_id`
     WHERE te.`id` = ? LIMIT 1',
    [$id]
);
if ($team === null) {
    http_response_code(404);
    exit('队伍不存在。');
}

$me = current_user();
$uid = $me !== null ? (int) $me['id'] : 0;
$isLeader = $me !== null && ((int) $team['leader_id'] === $uid || is_admin());
$isTripOwner = $me !== null && (int) $team['trip_owner_id'] === $uid;
$memberRow = null;
if ($me !== null) {
    $memberRow = db_one('SELECT * FROM `team_member` WHERE `team_id` = ? AND `user_id` = ? LIMIT 1', [$id, $uid]);
}
$isApproved = $memberRow !== null && $memberRow['status'] === 'approved';
$canView = $isLeader || $isTripOwner || $isApproved || $team['visibility'] === 'public'
    || ($memberRow !== null && $memberRow['status'] === 'pending')
    || ($me !== null && $team['status'] === 'recruiting');
if (!$canView) {
    http_response_code(404);
    exit('该队伍未公开。');
}

if (is_post()) {
    verify_csrf();
    require_login();
    $action = (string) input('action');

    if ($action === 'review' && $isLeader) {
        $applicantId = (int) input('user_id');
        $decision = (string) input('decision');
        if (!in_array($decision, ['approve', 'reject'], true)) {
            flash('danger', '审核操作不合法。');
            redirect('/team.php?id=' . $id);
        }
        try {
            $stmt = db()->prepare('CALL `sp_review_join`(?, ?, ?, ?, @code, @msg)');
            $stmt->execute([$id, $applicantId, $uid, $decision]);
            $stmt->closeCursor();
            $result = db_one('SELECT @code AS `code`, @msg AS `msg`');
            if ($result !== null && (int) $result['code'] === 0) {
                flash('success', (string) $result['msg']);
            } else {
                flash('danger', (string) ($result['msg'] ?? '审核失败。'));
            }
        } catch (Throwable $ex) {
            flash('danger', '审核失败：' . $ex->getMessage());
        }
        redirect('/team.php?id=' . $id);
    }

    if ($action === 'notice' && $isLeader) {
        $title = (string) input('title');
        $content = (string) input('content');
        if ($title === '' || $content === '' || mb_strlen($title, 'UTF-8') > 100 || mb_strlen($content, 'UTF-8') > 1000) {
            flash('danger', '公告标题和内容不能为空，且不超过长度限制。');
            redirect('/team.php?id=' . $id);
        }
        db_exec('INSERT INTO `team_notice` (`team_id`, `publisher_id`, `title`, `content`) VALUES (?, ?, ?, ?)', [$id, $uid, $title, $content]);
        $members = db_all('SELECT `user_id` FROM `team_member` WHERE `team_id` = ? AND `status` = "approved" AND `user_id` <> ?', [$id, $uid]);
        foreach ($members as $m) {
            db_exec(
                'INSERT INTO `notification` (`user_id`, `type`, `title`, `content`, `link`) VALUES (?, "team_notice", ?, ?, ?)',
                [(int) $m['user_id'], '队伍公告：' . $title, mb_substr($content, 0, 100, 'UTF-8'), '/team.php?id=' . $id]
            );
        }
        flash('success', '公告已发布。');
        redirect('/team.php?id=' . $id);
    }

    if ($action === 'remove' && $isLeader) {
        $memberId = (int) input('member_id');
        $member = db_one('SELECT * FROM `team_member` WHERE `id` = ? AND `team_id` = ? LIMIT 1', [$memberId, $id]);
        if ($member === null || $member['status'] !== 'approved') {
            flash('danger', '成员不存在或状态不可移除。');
            redirect('/team.php?id=' . $id);
        }
        if ((int) $member['user_id'] === (int) $team['leader_id']) {
            flash('danger', '不能移除队长。');
            redirect('/team.php?id=' . $id);
        }
        try {
            db()->beginTransaction();
            db_exec('UPDATE `team_member` SET `status` = "removed", `updated_at` = NOW() WHERE `id` = ?', [$memberId]);
            db_exec('UPDATE `team` SET `current_members` = GREATEST(`current_members` - 1, 1), `status` = IF(`status` = "full", "recruiting", `status`) WHERE `id` = ?', [$id]);
            db_exec('INSERT INTO `notification` (`user_id`, `type`, `title`, `content`, `link`) VALUES (?, "team_remove", "你已被移出队伍", ?, ?)', [(int) $member['user_id'], $team['name'], '/team.php?id=' . $id]);
            db()->commit();
            flash('success', '成员已移除。');
        } catch (Throwable $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('danger', '移除失败：' . $ex->getMessage());
        }
        redirect('/team.php?id=' . $id);
    }

    if ($action === 'apply' && !$isLeader && !$isApproved) {
        if ($team['status'] !== 'recruiting' || (int) $team['current_members'] >= (int) $team['max_members']) {
            flash('warning', '队伍当前不可申请。');
            redirect('/team.php?id=' . $id);
        }
        $reason = mb_substr((string) input('apply_reason'), 0, 255, 'UTF-8');
        db_exec(
            'INSERT INTO `team_member` (`team_id`, `user_id`, `status`, `apply_reason`) VALUES (?, ?, "pending", ?)
             ON DUPLICATE KEY UPDATE `status` = "pending", `apply_reason` = ?, `joined_at` = NULL, `updated_at` = NOW()',
            [$id, $uid, $reason !== '' ? $reason : null, $reason !== '' ? $reason : null]
        );
        db_exec(
            'INSERT INTO `notification` (`user_id`, `type`, `title`, `content`, `link`) VALUES (?, "team_apply", "有新的入队申请", ?, ?)',
            [(int) $team['leader_id'], $me['nickname'] . ' 申请加入 ' . $team['name'], '/team.php?id=' . $id]
        );
        flash('success', '申请已提交，等待队长审核。');
        redirect('/team.php?id=' . $id);
    }

    if ($action === 'cancel') {
        db_exec('DELETE FROM `team_member` WHERE `team_id` = ? AND `user_id` = ? AND `status` = "pending"', [$id, $uid]);
        flash('success', '已取消申请。');
        redirect('/team.php?id=' . $id);
    }

    if ($action === 'leave' && $isApproved && !$isLeader) {
        try {
            db()->beginTransaction();
            db_exec('UPDATE `team_member` SET `status` = "quit", `joined_at` = NULL, `updated_at` = NOW() WHERE `id` = ?', [(int) $memberRow['id']]);
            db_exec('UPDATE `team` SET `current_members` = GREATEST(`current_members` - 1, 1), `status` = IF(`status` = "full", "recruiting", `status`) WHERE `id` = ?', [$id]);
            db()->commit();
            flash('success', '已退出队伍。');
        } catch (Throwable $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            flash('danger', '退出失败：' . $ex->getMessage());
        }
        redirect('/team.php?id=' . $id);
    }
}

$members = db_all(
    'SELECT tm.*, u.`nickname`, u.`avatar`, u.`username`
     FROM `team_member` tm JOIN `user` u ON u.`id` = tm.`user_id`
     WHERE tm.`team_id` = ? AND tm.`status` = "approved"
     ORDER BY tm.`joined_at` ASC, tm.`id` ASC',
    [$id]
);
$pending = [];
if ($isLeader) {
    $pending = db_all(
        'SELECT tm.*, u.`nickname`, u.`avatar`, u.`username`
         FROM `team_member` tm JOIN `user` u ON u.`id` = tm.`user_id`
         WHERE tm.`team_id` = ? AND tm.`status` = "pending"
         ORDER BY tm.`created_at` ASC',
        [$id]
    );
}
$notices = db_all(
    'SELECT n.*, u.`nickname` AS `publisher_nickname`
     FROM `team_notice` n JOIN `user` u ON u.`id` = n.`publisher_id`
     WHERE n.`team_id` = ? ORDER BY n.`created_at` DESC LIMIT 10',
    [$id]
);

$pageTitle = $team['name'];
$activeNav = 'teams';
require APP_ROOT . '/app/views/header.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(url('/index.php')) ?>">首页</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('/teams.php')) ?>">队伍</a></li>
        <li class="breadcrumb-item active"><?= e($team['name']) ?></li>
    </ol>
</nav>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <?= status_html($team['status']) ?>
                    <span class="badge text-bg-light"><?= (int) $team['current_members'] ?>/<?= (int) $team['max_members'] ?> 人</span>
                </div>
                <h1 class="h3 mb-1"><?= e($team['name']) ?></h1>
                <p class="text-secondary mb-0"><?= e($team['description'] ?? '这个队伍还没有介绍。') ?></p>
                <div class="small text-secondary mt-2">
                    行程：<a href="<?= e(url('/trip.php?id=' . $team['trip_id'])) ?>"><?= e($team['trip_title']) ?></a>
                    · <?= e($team['destination']) ?> · <?= e(format_date($team['start_date'])) ?> 至 <?= e(format_date($team['end_date'])) ?>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-start">
                <?php if ($isLeader): ?>
                    <a class="btn btn-outline-primary" href="<?= e(url('/team_edit.php?id=' . $id)) ?>">编辑队伍</a>
                <?php endif; ?>
                <a class="btn btn-outline-secondary" href="<?= e(url('/trip.php?id=' . $team['trip_id'])) ?>">查看行程</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">成员</h2>
                <div class="vstack gap-3">
                    <?php foreach ($members as $m): ?>
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?= e(avatar_url($m['avatar'])) ?>" class="rounded-circle" width="36" height="36" alt="">
                            <div class="flex-grow-1">
                                <div class="fw-semibold small"><?= e($m['nickname']) ?> <?php if ((int) $m['user_id'] === (int) $team['leader_id']): ?><span class="badge text-bg-primary">队长</span><?php endif; ?></div>
                                <div class="text-secondary" style="font-size:.78rem">加入于 <?= e(format_dt($m['joined_at'])) ?></div>
                            </div>
                            <?php if ($isLeader && (int) $m['user_id'] !== (int) $team['leader_id']): ?>
                                <form method="post" action="<?= e(url('/team.php?id=' . $id)) ?>" onsubmit="return confirm('确定移除该成员？')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">移除</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($isLeader && $pending): ?>
                    <hr>
                    <h2 class="h6 mb-3">待审核申请</h2>
                    <?php foreach ($pending as $p): ?>
                        <div class="border rounded p-3 mb-2">
                            <div class="d-flex justify-content-between gap-2">
                                <div class="small"><strong><?= e($p['nickname']) ?></strong> <span class="text-secondary">@<?= e($p['username']) ?></span></div>
                                <span class="text-secondary" style="font-size:.78rem"><?= e(format_dt($p['created_at'])) ?></span>
                            </div>
                            <div class="small text-secondary my-2"><?= e($p['apply_reason'] ?? '未填写申请理由') ?></div>
                            <form method="post" action="<?= e(url('/team.php?id=' . $id)) ?>" class="d-flex gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="review">
                                <input type="hidden" name="user_id" value="<?= (int) $p['user_id'] ?>">
                                <button class="btn btn-sm btn-success" name="decision" value="approve" type="submit">通过</button>
                                <button class="btn btn-sm btn-outline-danger" name="decision" value="reject" type="submit">拒绝</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isLeader): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">发布公告</h2>
                    <form method="post" action="<?= e(url('/team.php?id=' . $id)) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="notice">
                        <input class="form-control mb-2" name="title" maxlength="100" required placeholder="公告标题">
                        <textarea class="form-control mb-2" name="content" rows="3" maxlength="1000" required placeholder="集合时间、装备提醒、费用说明…"></textarea>
                        <button class="btn btn-primary" type="submit">发布公告</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">加入队伍</h2>
                <?php if ($me === null): ?>
                    <a class="btn btn-primary w-100" href="<?= e(url('/login.php?next=' . urlencode('/team.php?id=' . $id))) ?>">登录后申请</a>
                <?php elseif ($isLeader): ?>
                    <div class="alert alert-light border small mb-0">你是队长，可以审核申请和管理成员。</div>
                <?php elseif ($isApproved): ?>
                    <form method="post" action="<?= e(url('/team.php?id=' . $id)) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="leave">
                        <button class="btn btn-outline-danger w-100" type="submit">退出队伍</button>
                    </form>
                <?php elseif ($memberRow !== null && $memberRow['status'] === 'pending'): ?>
                    <form method="post" action="<?= e(url('/team.php?id=' . $id)) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <button class="btn btn-outline-secondary w-100" type="submit">已申请，取消申请</button>
                    </form>
                <?php elseif ($team['status'] === 'recruiting' && (int) $team['current_members'] < (int) $team['max_members']): ?>
                    <form method="post" action="<?= e(url('/team.php?id=' . $id)) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="apply">
                        <textarea class="form-control mb-2" name="apply_reason" rows="2" maxlength="255" placeholder="申请理由（可选）"></textarea>
                        <button class="btn btn-primary w-100" type="submit">申请加入</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-light border small mb-0">队伍当前不可申请。</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">队伍公告</h2>
                <?php if (!$notices): ?><div class="text-secondary small">还没有公告。</div><?php endif; ?>
                <?php foreach ($notices as $n): ?>
                    <div class="border-bottom pb-2 mb-2">
                        <div class="fw-semibold small"><?= e($n['title']) ?></div>
                        <div class="small text-secondary"><?= nl2br(e($n['content'])) ?></div>
                        <div class="text-secondary" style="font-size:.75rem"><?= e($n['publisher_nickname']) ?> · <?= e(format_dt($n['created_at'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
