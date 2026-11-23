<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_login();

$id = (int) query('id');
$trip = null;
if ($id > 0) {
    $trip = db_one('SELECT * FROM `trip` WHERE `id` = ? LIMIT 1', [$id]);
    if ($trip === null) {
        http_response_code(404);
        exit('行程不存在。');
    }
    if (!can_edit_trip($trip)) {
        http_response_code(403);
        exit('403：无权编辑这个行程。');
    }
}

$form = [
    'title'       => $trip['title'] ?? '',
    'destination' => $trip['destination'] ?? '',
    'start_date'  => $trip['start_date'] ?? date('Y-m-d'),
    'end_date'    => $trip['end_date'] ?? date('Y-m-d', strtotime('+3 days')),
    'budget'      => $trip['budget'] ?? '0.00',
    'cover'       => $trip['cover'] ?? '',
    'description' => $trip['description'] ?? '',
    'visibility'  => $trip['visibility'] ?? 'private',
    'status'      => $trip['status'] ?? 'planning',
];
$errors = [];
$newCover = null;

if (is_post()) {
    verify_csrf();
    $form = [
        'title'       => (string) input('title'),
        'destination' => (string) input('destination'),
        'start_date'  => (string) input('start_date'),
        'end_date'    => (string) input('end_date'),
        'budget'      => (string) input('budget', '0'),
        'cover'       => (string) ($trip['cover'] ?? ''),
        'description' => (string) input('description'),
        'visibility'  => (string) input('visibility', 'private'),
        'status'      => (string) input('status', 'planning'),
    ];

    if ($form['title'] === '' || mb_strlen($form['title'], 'UTF-8') > 100) {
        $errors[] = '标题不能为空且不超过 100 字。';
    }
    if ($form['destination'] === '' || mb_strlen($form['destination'], 'UTF-8') > 100) {
        $errors[] = '目的地不能为空且不超过 100 字。';
    }
    $startTs = strtotime($form['start_date']);
    $endTs = strtotime($form['end_date']);
    if ($startTs === false || $endTs === false) {
        $errors[] = '请选择有效的出发和返程日期。';
    } elseif ($endTs < $startTs) {
        $errors[] = '返程日期不能早于出发日期。';
    }
    $budget = (float) $form['budget'];
    if ($budget < 0) {
        $errors[] = '预算不能为负数。';
    }
    if (mb_strlen($form['description'], 'UTF-8') > 1000) {
        $errors[] = '行程描述不能超过 1000 字。';
    }
    if (!in_array($form['visibility'], ['private', 'team', 'public'], true)) {
        $form['visibility'] = 'private';
    }
    if (!in_array($form['status'], ['planning', 'ongoing', 'finished'], true)) {
        $form['status'] = 'planning';
    }

    if (!$errors) {
        $uploadError = null;
        $uploadedCover = save_image_upload($_FILES['cover_file'] ?? [], 'covers/trips', $uploadError);
        if ($uploadError !== null) {
            $errors[] = $uploadError;
        } elseif ($uploadedCover !== null) {
            $newCover = $uploadedCover;
            $form['cover'] = $uploadedCover;
        }
    }

    if (!$errors) {
        try {
            if ($trip === null) {
                db_exec(
                    'INSERT INTO `trip` (`owner_id`, `title`, `destination`, `start_date`, `end_date`, `budget`, `cover`, `description`, `visibility`, `status`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        (int) current_user()['id'],
                        $form['title'],
                        $form['destination'],
                        $form['start_date'],
                        $form['end_date'],
                        $budget,
                        $form['cover'] !== '' ? $form['cover'] : null,
                        $form['description'] !== '' ? $form['description'] : null,
                        $form['visibility'],
                        $form['status'],
                    ]
                );
                $id = db_last_id();
                flash('success', '行程已创建，可以继续组建队伍。');
                redirect('/trip.php?id=' . $id);
            }
            db_exec(
                'UPDATE `trip` SET `title` = ?, `destination` = ?, `start_date` = ?, `end_date` = ?, `budget` = ?, `cover` = ?, `description` = ?, `visibility` = ?, `status` = ? WHERE `id` = ?',
                [
                    $form['title'],
                    $form['destination'],
                    $form['start_date'],
                    $form['end_date'],
                    $budget,
                    $form['cover'] !== '' ? $form['cover'] : null,
                    $form['description'] !== '' ? $form['description'] : null,
                    $form['visibility'],
                    $form['status'],
                    $id,
                ]
            );
            if ($newCover !== null && !empty($trip['cover']) && $trip['cover'] !== $newCover) {
                delete_upload((string) $trip['cover']);
            }
            flash('success', '行程已更新。');
            redirect('/trip.php?id=' . $id);
        } catch (Throwable $ex) {
            if ($newCover !== null) {
                delete_upload($newCover);
                $form['cover'] = $trip['cover'] ?? '';
                $newCover = null;
            }
            $errors[] = '保存失败：' . $ex->getMessage();
        }
    }
}

$pageTitle = $trip === null ? '创建行程' : '编辑行程';
$activeNav = 'trips';
require APP_ROOT . '/app/views/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h3 mb-1"><?= e($pageTitle) ?></h1>
                <p class="text-secondary mb-0">先定目的地和日期，再邀请同伴一起完善路线。</p>
            </div>
            <a class="btn btn-outline-secondary" href="<?= e(url($trip === null ? '/trip_my.php' : '/trip.php?id=' . $id)) ?>">返回</a>
        </div>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form method="post" enctype="multipart/form-data" class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="title">行程标题 <span class="text-danger">*</span></label>
                        <input class="form-control" id="title" name="title" maxlength="100" required value="<?= e($form['title']) ?>" placeholder="如：国庆厦门三日游">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="destination">目的地 <span class="text-danger">*</span></label>
                        <input class="form-control" id="destination" name="destination" maxlength="100" required value="<?= e($form['destination']) ?>" placeholder="厦门">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="start_date">出发日期</label>
                        <input class="form-control" id="start_date" name="start_date" type="date" required value="<?= e($form['start_date']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="end_date">返程日期</label>
                        <input class="form-control" id="end_date" name="end_date" type="date" required value="<?= e($form['end_date']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="budget">总预算（元）</label>
                        <input class="form-control" id="budget" name="budget" type="number" min="0" step="0.01" required value="<?= e($form['budget']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="visibility">可见性</label>
                        <select class="form-select" id="visibility" name="visibility">
                            <option value="private"<?= selected($form['visibility'], 'private') ?>>私密（仅自己）</option>
                            <option value="team"<?= selected($form['visibility'], 'team') ?>>队伍可见</option>
                            <option value="public"<?= selected($form['visibility'], 'public') ?>>公开</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="status">行程状态</label>
                        <select class="form-select" id="status" name="status">
                            <option value="planning"<?= selected($form['status'], 'planning') ?>>规划中</option>
                            <option value="ongoing"<?= selected($form['status'], 'ongoing') ?>>进行中</option>
                            <option value="finished"<?= selected($form['status'], 'finished') ?>>已结束</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="cover_file">封面图片（可选）</label>
                        <input class="form-control" id="cover_file" name="cover_file" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
                        <div class="form-text">JPG / PNG / GIF / WebP，最大 5 MB；不选择则保留当前封面。</div>
                        <?php if ($form['cover'] !== ''): ?>
                            <img class="img-fluid rounded mt-2 cover-preview" src="<?= e(cover_url($form['cover'])) ?>" alt="当前封面">
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">行程说明</label>
                        <textarea class="form-control" id="description" name="description" rows="4" maxlength="1000" placeholder="旅行主题、同行人、特别说明…"><?= e($form['description']) ?></textarea>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white p-3 d-flex justify-content-end gap-2">
                <a class="btn btn-outline-secondary" href="<?= e(url('/trip_my.php')) ?>">取消</a>
                <button class="btn btn-primary" type="submit">保存行程</button>
            </div>
        </form>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
