<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_login();

$id = (int) query('id');
$guide = null;
if ($id > 0) {
    $guide = db_one('SELECT * FROM `guide` WHERE `id` = ? LIMIT 1', [$id]);
    if ($guide === null) {
        http_response_code(404);
        exit('攻略不存在。');
    }
    if (!can_edit_guide($guide)) {
        http_response_code(403);
        exit('403：无权编辑这篇攻略。');
    }
}

$tags = db_all('SELECT * FROM `tag` ORDER BY `type`, `name`');
$tagIds = array_map(static fn(array $t): int => (int) $t['id'], $tags);
$selectedTagIds = [];
if ($guide !== null) {
    $selectedTagIds = array_map(
        static fn(array $row): int => (int) $row['tag_id'],
        db_all('SELECT `tag_id` FROM `guide_tag` WHERE `guide_id` = ?', [$id])
    );
}

$defaultStatus = $guide !== null ? (string) $guide['status'] : 'draft';
if ($defaultStatus === 'published' && !is_admin()) {
    $defaultStatus = 'pending';
}
$form = [
    'title'       => $guide['title'] ?? '',
    'destination' => $guide['destination'] ?? '',
    'cover'       => $guide['cover'] ?? '',
    'summary'     => $guide['summary'] ?? '',
    'content'     => $guide['content'] ?? '',
    'day_count'   => $guide['day_count'] ?? 1,
    'cost_avg'    => $guide['cost_avg'] ?? '0.00',
    'season'      => $guide['season'] ?? 'all',
    'status'      => $defaultStatus,
];
$errors = [];
$newCover = null;

if (is_post()) {
    verify_csrf();
    $form = [
        'title'       => (string) input('title'),
        'destination' => (string) input('destination'),
        'cover'       => (string) ($guide['cover'] ?? ''),
        'summary'     => (string) input('summary'),
        'content'     => (string) input('content'),
        'day_count'   => (int) input('day_count', 1),
        'cost_avg'    => (string) input('cost_avg', '0'),
        'season'      => (string) input('season', 'all'),
        'status'      => (string) input('status', 'draft'),
    ];
    $selectedTagIds = array_map('intval', (array) input('tags', []));

    if ($form['title'] === '' || mb_strlen($form['title'], 'UTF-8') > 100) {
        $errors[] = '标题不能为空且不超过 100 字。';
    }
    if ($form['destination'] === '' || mb_strlen($form['destination'], 'UTF-8') > 100) {
        $errors[] = '目的地不能为空且不超过 100 字。';
    }
    if ($form['content'] === '') {
        $errors[] = '正文不能为空。';
    }
    if (mb_strlen($form['summary'], 'UTF-8') > 500) {
        $errors[] = '摘要不能超过 500 字。';
    }
    if ($form['day_count'] < 1 || $form['day_count'] > 60) {
        $errors[] = '行程天数应在 1–60 之间。';
    }
    $cost = (float) $form['cost_avg'];
    if ($cost < 0 || $cost > 99999999) {
        $errors[] = '人均花费不合法。';
    }
    $seasons = ['spring', 'summer', 'autumn', 'winter', 'all'];
    if (!in_array($form['season'], $seasons, true)) {
        $form['season'] = 'all';
    }
    $allowedStatus = is_admin() ? ['draft', 'pending', 'published'] : ['draft', 'pending'];
    if (!in_array($form['status'], $allowedStatus, true)) {
        $form['status'] = 'draft';
    }
    if (mb_strlen($form['cover'], 'UTF-8') > 500) {
        $errors[] = '封面 URL 过长。';
    }
    $selectedTagIds = array_values(array_intersect($selectedTagIds, $tagIds));

    // 图片先校验，再落盘；标题等字段有错时不会留下孤儿文件。
    if (!$errors) {
        $uploadError = null;
        $uploadedCover = save_image_upload($_FILES['cover_file'] ?? [], 'covers/guides', $uploadError);
        if ($uploadError !== null) {
            $errors[] = $uploadError;
        } elseif ($uploadedCover !== null) {
            $newCover = $uploadedCover;
            $form['cover'] = $uploadedCover;
        }
    }

    if (!$errors) {
        try {
            db()->beginTransaction();
            if ($guide === null) {
                db_exec(
                    'INSERT INTO `guide`
                     (`author_id`, `title`, `destination`, `cover`, `summary`, `content`, `day_count`, `cost_avg`, `season`, `status`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        (int) current_user()['id'],
                        $form['title'],
                        $form['destination'],
                        $form['cover'] !== '' ? $form['cover'] : null,
                        $form['summary'] !== '' ? $form['summary'] : null,
                        $form['content'],
                        $form['day_count'],
                        $cost,
                        $form['season'],
                        $form['status'],
                    ]
                );
                $id = db_last_id();
            } else {
                db_exec(
                    'UPDATE `guide` SET `title` = ?, `destination` = ?, `cover` = ?, `summary` = ?, `content` = ?,
                     `day_count` = ?, `cost_avg` = ?, `season` = ?, `status` = ?, `updated_at` = NOW() WHERE `id` = ?',
                    [
                        $form['title'],
                        $form['destination'],
                        $form['cover'] !== '' ? $form['cover'] : null,
                        $form['summary'] !== '' ? $form['summary'] : null,
                        $form['content'],
                        $form['day_count'],
                        $cost,
                        $form['season'],
                        $form['status'],
                        $id,
                    ]
                );
            }

            db_exec('DELETE FROM `guide_tag` WHERE `guide_id` = ?', [$id]);
            foreach ($selectedTagIds as $tagId) {
                db_exec('INSERT INTO `guide_tag` (`guide_id`, `tag_id`) VALUES (?, ?)', [$id, $tagId]);
            }

            if ($form['status'] === 'pending') {
                $admins = db_all('SELECT `id` FROM `user` WHERE `role` = "admin" AND `status` = "active"');
                foreach ($admins as $admin) {
                    db_exec(
                        'INSERT INTO `notification` (`user_id`, `type`, `title`, `content`, `link`)
                         VALUES (?, "guide_pending", "有新的攻略待审核", ?, ?)',
                        [(int) $admin['id'], $form['title'], '/admin/guides.php']
                    );
                }
            }

            db()->commit();
            if ($newCover !== null && $guide !== null && !empty($guide['cover']) && $guide['cover'] !== $newCover) {
                delete_upload((string) $guide['cover']);
            }
            flash('success', $form['status'] === 'published' ? '攻略已发布。' : ($form['status'] === 'pending' ? '攻略已提交审核。' : '草稿已保存。'));
            redirect('/guide.php?id=' . $id);
        } catch (Throwable $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            if ($newCover !== null) {
                delete_upload($newCover);
                $form['cover'] = $guide['cover'] ?? '';
                $newCover = null;
            }
            $errors[] = '保存失败：' . $ex->getMessage();
        }
    }
}

$pageTitle = $guide === null ? '发布攻略' : '编辑攻略';
$activeNav = 'guides';
require APP_ROOT . '/app/views/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="h3 mb-1"><?= e($pageTitle) ?></h1>
                <p class="text-secondary mb-0">分享路线、花费与真实体验，帮助后来者少走弯路。</p>
            </div>
            <a class="btn btn-outline-secondary" href="<?= e(url($guide === null ? '/guide_my.php' : '/guide.php?id=' . $id)) ?>">返回</a>
        </div>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form method="post" enctype="multipart/form-data" class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="title">标题 <span class="text-danger">*</span></label>
                        <input class="form-control" id="title" name="title" maxlength="100" required value="<?= e($form['title']) ?>" placeholder="如：厦门三天两夜，人均 800 元">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="destination">目的地 <span class="text-danger">*</span></label>
                        <input class="form-control" id="destination" name="destination" maxlength="100" required value="<?= e($form['destination']) ?>" placeholder="厦门">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="cover_file">封面图片（可选）</label>
                        <input class="form-control" id="cover_file" name="cover_file" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
                        <div class="form-text">JPG / PNG / GIF / WebP，最大 5 MB；不选择则保留当前封面。</div>
                        <?php if ($form['cover'] !== ''): ?>
                            <img class="img-fluid rounded mt-2 cover-preview" src="<?= e(cover_url($form['cover'])) ?>" alt="当前封面">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="season">适合季节</label>
                        <select class="form-select" id="season" name="season">
                            <option value="all"<?= selected($form['season'], 'all') ?>>四季皆宜</option>
                            <option value="spring"<?= selected($form['season'], 'spring') ?>>春季</option>
                            <option value="summer"<?= selected($form['season'], 'summer') ?>>夏季</option>
                            <option value="autumn"<?= selected($form['season'], 'autumn') ?>>秋季</option>
                            <option value="winter"<?= selected($form['season'], 'winter') ?>>冬季</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="day_count">天数</label>
                        <input class="form-control" id="day_count" name="day_count" type="number" min="1" max="60" required value="<?= e($form['day_count']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="cost_avg">人均花费（元）</label>
                        <input class="form-control" id="cost_avg" name="cost_avg" type="number" min="0" step="0.01" required value="<?= e($form['cost_avg']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="status">保存方式</label>
                        <select class="form-select" id="status" name="status">
                            <option value="draft"<?= selected($form['status'], 'draft') ?>>保存草稿</option>
                            <option value="pending"<?= selected($form['status'], 'pending') ?>>提交审核 / 发布</option>
                            <?php if (is_admin()): ?>
                                <option value="published"<?= selected($form['status'], 'published') ?>>直接发布（管理员）</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="summary">摘要</label>
                        <textarea class="form-control" id="summary" name="summary" rows="2" maxlength="500" placeholder="一句话说明路线亮点、适合人群"><?= e($form['summary']) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="content">正文 <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="content" name="content" rows="14" required placeholder="按天写路线、交通、住宿、美食与避坑建议…"><?= e($form['content']) ?></textarea>
                        <div class="form-text">纯文本即可，换行会保留；报告截图时更清晰。</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label d-block">标签</label>
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach ($tags as $tag): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="tags[]" id="tag-<?= (int) $tag['id'] ?>" value="<?= (int) $tag['id'] ?>"<?= checked(in_array((int) $tag['id'], $selectedTagIds, true)) ?>>
                                    <label class="form-check-label" for="tag-<?= (int) $tag['id'] ?>"><?= e($tag['name']) ?> <span class="text-secondary small">(<?= e($tag['type']) ?>)</span></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white p-3 d-flex justify-content-end gap-2">
                <a class="btn btn-outline-secondary" href="<?= e(url('/guide_my.php')) ?>">取消</a>
                <button class="btn btn-primary" type="submit">保存攻略</button>
            </div>
        </form>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
