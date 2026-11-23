<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_login();

$id = (int) query('id');
$tripId = (int) query('trip_id');
$team = null;
if ($id > 0) {
    $team = db_one('SELECT * FROM `team` WHERE `id` = ? LIMIT 1', [$id]);
    if ($team === null) {
        http_response_code(404);
        exit('队伍不存在。');
    }
    if (!can_manage_team($team)) {
        http_response_code(403);
        exit('403：无权管理这个队伍。');
    }
    $tripId = (int) $team['trip_id'];
}
$trip = db_one('SELECT * FROM `trip` WHERE `id` = ? LIMIT 1', [$tripId]);
if ($trip === null) {
    http_response_code(404);
    exit('行程不存在。');
}
if (!can_edit_trip($trip)) {
    http_response_code(403);
    exit('403：只有行程发起人可以组建队伍。');
}
if ($team === null) {
    $existing = db_one('SELECT `id` FROM `team` WHERE `trip_id` = ? LIMIT 1', [$tripId]);
    if ($existing !== null) {
        redirect('/team_edit.php?id=' . $existing['id']);
    }
}

$form = [
    'name'        => $team['name'] ?? ($trip['title'] . ' 同行队'),
    'max_members' => $team['max_members'] ?? 6,
    'description' => $team['description'] ?? '',
    'status'      => $team['status'] ?? 'recruiting',
];
$errors = [];

if (is_post()) {
    verify_csrf();
    $form = [
        'name'        => (string) input('name'),
        'max_members' => (int) input('max_members', 6),
        'description' => (string) input('description'),
        'status'      => (string) input('status', 'recruiting'),
    ];
    if ($form['name'] === '' || mb_strlen($form['name'], 'UTF-8') > 100) {
        $errors[] = '队伍名称不能为空且不超过 100 字。';
    }
    if ($form['max_members'] < 2 || $form['max_members'] > 50) {
        $errors[] = '队伍人数应在 2–50 之间。';
    }
    if ($team !== null && $form['max_members'] < (int) $team['current_members']) {
        $errors[] = '人数上限不能小于当前成员数 ' . (int) $team['current_members'] . '。';
    }
    if (mb_strlen($form['description'], 'UTF-8') > 500) {
        $errors[] = '队伍介绍不能超过 500 字。';
    }
    if (!in_array($form['status'], ['recruiting', 'full', 'ongoing', 'closed'], true)) {
        $form['status'] = 'recruiting';
    }

    if (!$errors) {
        $description = $form['description'] !== '' ? $form['description'] : null;
        if ($team === null) {
            db_exec(
                'INSERT INTO `team` (`trip_id`, `leader_id`, `name`, `max_members`, `current_members`, `description`, `status`)
                 VALUES (?, ?, ?, ?, 1, ?, ?)',
                [$tripId, (int) current_user()['id'], $form['name'], $form['max_members'], $description, $form['status']]
            );
            $id = db_last_id();
            flash('success', '队伍已创建，可以邀请同伴申请加入。');
        } else {
            db_exec(
                'UPDATE `team` SET `name` = ?, `max_members` = ?, `description` = ?, `status` = ? WHERE `id` = ?',
                [$form['name'], $form['max_members'], $description, $form['status'], $id]
            );
            flash('success', '队伍信息已更新。');
        }
        redirect('/team.php?id=' . $id);
    }
}

$pageTitle = $team === null ? '组建队伍' : '编辑队伍';
$activeNav = 'teams';
require APP_ROOT . '/app/views/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h3 mb-1"><?= e($pageTitle) ?></h1>
                <p class="text-secondary mb-0"><?= e($trip['title']) ?> · <?= e($trip['destination']) ?></p>
            </div>
            <a class="btn btn-outline-secondary" href="<?= e(url('/trip.php?id=' . $tripId)) ?>">返回行程</a>
        </div>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form method="post" class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="name">队伍名称 <span class="text-danger">*</span></label>
                    <input class="form-control" id="name" name="name" maxlength="100" required value="<?= e($form['name']) ?>">
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="max_members">人数上限</label>
                        <input class="form-control" id="max_members" name="max_members" type="number" min="2" max="50" required value="<?= e($form['max_members']) ?>">
                        <div class="form-text">含队长，最少 2 人。</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="status">队伍状态</label>
                        <select class="form-select" id="status" name="status">
                            <option value="recruiting"<?= selected($form['status'], 'recruiting') ?>>招募中</option>
                            <option value="full"<?= selected($form['status'], 'full') ?>>已满员</option>
                            <option value="ongoing"<?= selected($form['status'], 'ongoing') ?>>行程中</option>
                            <option value="closed"<?= selected($form['status'], 'closed') ?>>已关闭</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">队伍介绍</label>
                        <textarea class="form-control" id="description" name="description" rows="4" maxlength="500" placeholder="同行偏好、预算、集合方式…"><?= e($form['description']) ?></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white p-3 d-flex justify-content-end gap-2">
                <a class="btn btn-outline-secondary" href="<?= e(url('/trip.php?id=' . $tripId)) ?>">取消</a>
                <button class="btn btn-primary" type="submit">保存队伍</button>
            </div>
        </form>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
