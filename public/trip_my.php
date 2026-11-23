<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_login();

$me = current_user();
$uid = (int) $me['id'];

$owned = db_all(
    'SELECT t.*, te.`id` AS `team_id`, te.`name` AS `team_name`, te.`status` AS `team_status`, te.`current_members`, te.`max_members`
     FROM `trip` t LEFT JOIN `team` te ON te.`trip_id` = t.`id`
     WHERE t.`owner_id` = ? ORDER BY t.`start_date` DESC, t.`id` DESC',
    [$uid]
);
$joined = db_all(
    'SELECT t.*, te.`id` AS `team_id`, te.`name` AS `team_name`, te.`leader_id`, tm.`status` AS `member_status`,
            lu.`nickname` AS `leader_nickname`
     FROM `team_member` tm
     JOIN `team` te ON te.`id` = tm.`team_id`
     JOIN `trip` t ON t.`id` = te.`trip_id`
     JOIN `user` lu ON lu.`id` = te.`leader_id`
     WHERE tm.`user_id` = ? AND tm.`status` IN ("pending", "approved")
     ORDER BY t.`start_date` DESC, t.`id` DESC',
    [$uid]
);

$pageTitle = '我的行程';
$activeNav = 'trips';
require APP_ROOT . '/app/views/header.php';
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">我的行程</h1>
        <p class="text-secondary mb-0">管理自己发起的行程，查看已加入的队伍。</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('/trip_edit.php')) ?>">创建行程</a>
</div>

<h2 class="h5 mb-3">我发起的行程</h2>
<div class="row g-3 mb-4">
    <?php if (!$owned): ?>
        <div class="col-12"><div class="alert alert-light border">还没有发起行程。</div></div>
    <?php endif; ?>
    <?php foreach ($owned as $t): ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <h3 class="h6 mb-1"><a class="text-decoration-none text-body" href="<?= e(url('/trip.php?id=' . $t['id'])) ?>"><?= e($t['title']) ?></a></h3>
                            <div class="small text-secondary"><?= e($t['destination']) ?> · <?= e(format_date($t['start_date'])) ?> 至 <?= e(format_date($t['end_date'])) ?></div>
                        </div>
                        <?= status_html($t['status']) ?>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/trip_edit.php?id=' . $t['id'])) ?>">编辑</a>
                        <?php if ($t['team_id'] !== null): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/team.php?id=' . $t['team_id'])) ?>">队伍 · <?= e($t['team_name']) ?></a>
                        <?php else: ?>
                            <a class="btn btn-sm btn-outline-success" href="<?= e(url('/team_edit.php?trip_id=' . $t['id'])) ?>">组建队伍</a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/route_edit.php?trip_id=' . $t['id'])) ?>">编辑路线</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<h2 class="h5 mb-3">我加入的队伍</h2>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>行程</th><th>队伍</th><th>队长</th><th>我的状态</th><th>日期</th><th></th></tr></thead>
            <tbody>
            <?php if (!$joined): ?>
                <tr><td colspan="6" class="text-center text-secondary py-4">还没有加入任何队伍。</td></tr>
            <?php endif; ?>
            <?php foreach ($joined as $t): ?>
                <tr>
                    <td><div class="fw-semibold"><?= e($t['title']) ?></div><div class="small text-secondary"><?= e($t['destination']) ?></div></td>
                    <td><?= e($t['team_name']) ?> <?= status_html($t['member_status']) ?></td>
                    <td><?= e($t['leader_nickname']) ?></td>
                    <td><?= status_html($t['member_status']) ?></td>
                    <td class="small text-secondary"><?= e(format_date($t['start_date'])) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/team.php?id=' . $t['team_id'])) ?>">查看队伍</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
