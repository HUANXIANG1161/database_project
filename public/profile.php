<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$me = current_user();
$id = (int) query('id', $me !== null ? (string) $me['id'] : '0');
if ($id <= 0) {
    redirect('/login.php?next=' . rawurlencode('/profile.php'));
}
$user = db_one('SELECT * FROM `user` WHERE `id` = ? LIMIT 1', [$id]);
if ($user === null) {
    http_response_code(404);
    exit('用户不存在。');
}
$isOwn = $me !== null && (int) $me['id'] === $id;
$isFriend = false;
if ($me !== null && !$isOwn) {
    $isFriend = (int) db_val(
        'SELECT COUNT(*) FROM `friendship` WHERE `status` = "accepted" AND ((`user_id` = ? AND `friend_id` = ?) OR (`user_id` = ? AND `friend_id` = ?))',
        [(int) $me['id'], $id, $id, (int) $me['id']]
    ) > 0;
}

$errors = [];
$newAvatar = null;
$form = [
    'nickname' => $user['nickname'],
    'gender'   => $user['gender'],
    'email'    => $user['email'] ?? '',
    'phone'    => $user['phone'] ?? '',
    'intro'    => $user['intro'] ?? '',
    'avatar'   => $user['avatar'] ?? '',
];
if (is_post()) {
    if (!$isOwn) {
        http_response_code(403);
        exit('403：只能修改自己的资料。');
    }
    verify_csrf();
    foreach (['nickname', 'gender', 'email', 'phone', 'intro'] as $key) {
        $form[$key] = trim((string) input($key, ''));
    }
    if ($form['nickname'] === '' || mb_strlen($form['nickname'], 'UTF-8') > 50) {
        $errors[] = '昵称必填，且不能超过 50 字。';
    }
    if (!in_array($form['gender'], ['male', 'female', 'unknown'], true)) {
        $errors[] = '性别取值不合法。';
    }
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = '邮箱格式不正确。';
    }
    if ($form['email'] !== '' && (int) db_val('SELECT COUNT(*) FROM `user` WHERE `email` = ? AND `id` <> ?', [$form['email'], $id]) > 0) {
        $errors[] = '该邮箱已被其他账号使用。';
    }
    if (mb_strlen($form['phone'], 'UTF-8') > 20) {
        $errors[] = '手机号不能超过 20 个字符。';
    }
    if (mb_strlen($form['intro'], 'UTF-8') > 500) {
        $errors[] = '个人简介不能超过 500 字。';
    }

    if (!$errors) {
        $uploadError = null;
        $uploadedAvatar = save_image_upload($_FILES['avatar_file'] ?? [], 'avatars', $uploadError);
        if ($uploadError !== null) {
            $errors[] = $uploadError;
        } elseif ($uploadedAvatar !== null) {
            $newAvatar = $uploadedAvatar;
            $form['avatar'] = $uploadedAvatar;
        }
    }

    if (!$errors) {
        try {
            db_exec(
                'UPDATE `user` SET `nickname` = ?, `gender` = ?, `email` = ?, `phone` = ?, `intro` = ?, `avatar` = ? WHERE `id` = ?',
                [
                    $form['nickname'], $form['gender'],
                    $form['email'] !== '' ? $form['email'] : null,
                    $form['phone'] !== '' ? $form['phone'] : null,
                    $form['intro'] !== '' ? $form['intro'] : null,
                    $form['avatar'] !== '' ? $form['avatar'] : null,
                    $id,
                ]
            );
            if ($newAvatar !== null && !empty($user['avatar']) && $user['avatar'] !== $newAvatar) {
                delete_upload((string) $user['avatar']);
            }
            flash('success', '个人资料已更新。');
            redirect('/profile.php?id=' . $id);
        } catch (Throwable $ex) {
            if ($newAvatar !== null) {
                delete_upload($newAvatar);
                $form['avatar'] = $user['avatar'] ?? '';
                $newAvatar = null;
            }
            $errors[] = '保存失败：' . $ex->getMessage();
        }
    }
}

$guideCount = (int) db_val('SELECT COUNT(*) FROM `guide` WHERE `author_id` = ? AND `status` = "published"', [$id]);
$tripCount = (int) db_val('SELECT COUNT(*) FROM `trip` WHERE `owner_id` = ?', [$id]);
$resourceCount = (int) db_val('SELECT COUNT(*) FROM `resource` WHERE `uploader_id` = ? AND `status` = "published"', [$id]);
$friendCount = (int) db_val('SELECT COUNT(*) FROM `friendship` WHERE `status` = "accepted" AND (`user_id` = ? OR `friend_id` = ?)', [$id, $id]);
$recentGuides = db_all('SELECT * FROM `guide` WHERE `author_id` = ? AND `status` = "published" ORDER BY `created_at` DESC LIMIT 3', [$id]);
$recentResources = db_all('SELECT * FROM `resource` WHERE `uploader_id` = ? AND `status` = "published" ORDER BY `created_at` DESC LIMIT 3', [$id]);
$genderLabels = ['male' => '男', 'female' => '女', 'unknown' => '保密'];

$pageTitle = $user['nickname'] . ' 的个人主页';
$activeNav = '';
require APP_ROOT . '/app/views/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <img class="avatar avatar-xl" src="<?= e(avatar_url($user['avatar'])) ?>" alt="">
                <h1 class="h4 mt-3 mb-1"><?= e($user['nickname']) ?></h1>
                <div class="text-secondary">@<?= e($user['username']) ?><?php if ($user['role'] === 'admin'): ?> · <span class="badge text-bg-dark">管理员</span><?php endif; ?></div>
                <p class="small text-secondary mt-3 mb-2"><?= e($user['intro'] !== null && $user['intro'] !== '' ? $user['intro'] : '这个人还没有写简介。') ?></p>
                <div class="small text-secondary">加入于 <?= e(format_date($user['created_at'])) ?></div>
                <div class="row text-center mt-4">
                    <div class="col"><div class="fw-semibold"><?= $guideCount ?></div><div class="small text-secondary">攻略</div></div>
                    <div class="col"><div class="fw-semibold"><?= $tripCount ?></div><div class="small text-secondary">行程</div></div>
                    <div class="col"><div class="fw-semibold"><?= $resourceCount ?></div><div class="small text-secondary">资源</div></div>
                    <div class="col"><div class="fw-semibold"><?= $friendCount ?></div><div class="small text-secondary">好友</div></div>
                </div>
                <div class="d-grid gap-2 mt-4">
                    <?php if ($isOwn): ?>
                        <a class="btn btn-outline-primary" href="#edit-profile">编辑资料</a>
                        <a class="btn btn-outline-secondary" href="<?= e(url('/guide_my.php')) ?>">我的攻略</a>
                    <?php elseif ($isFriend): ?>
                        <a class="btn btn-primary" href="<?= e(url('/messages.php?with=' . $id)) ?>">发私聊</a>
                    <?php elseif ($me !== null): ?>
                        <a class="btn btn-outline-primary" href="<?= e(url('/friends.php?q=' . rawurlencode($user['username']))) ?>">加为好友</a>
                    <?php else: ?>
                        <a class="btn btn-outline-primary" href="<?= e(url('/login.php?next=' . rawurlencode('/profile.php?id=' . $id))) ?>">登录后联系</a>
                    <?php endif; ?>
                </div>
                <?php if ($isOwn): ?>
                    <hr>
                    <div class="text-start small">
                        <div class="text-secondary">邮箱</div><div class="mb-2"><?= e($user['email'] ?? '未填写') ?></div>
                        <div class="text-secondary">手机</div><div><?= e($user['phone'] ?? '未填写') ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">最近发布的攻略</div>
            <div class="card-body">
                <?php if (!$recentGuides): ?><p class="text-secondary mb-0">还没有公开攻略。</p><?php endif; ?>
                <div class="row g-3">
                    <?php foreach ($recentGuides as $guide): ?>
                        <div class="col-md-4">
                            <a class="text-decoration-none" href="<?= e(url('/guide.php?id=' . (int) $guide['id'])) ?>">
                                <img class="img-fluid rounded mb-2 profile-thumb" src="<?= e(cover_url($guide['cover'])) ?>" alt="">
                                <div class="small fw-semibold text-body"><?= e(excerpt($guide['title'], 24)) ?></div>
                                <div class="small text-secondary"><?= e($guide['destination']) ?></div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white fw-semibold">最近分享的资源</div>
            <div class="card-body">
                <?php if (!$recentResources): ?><p class="text-secondary mb-0">还没有公开资源。</p><?php endif; ?>
                <div class="row g-3">
                    <?php foreach ($recentResources as $resource): ?>
                        <?php $thumb = resource_thumb($resource); ?>
                        <div class="col-md-4">
                            <a class="text-decoration-none" href="<?= e(url('/resource.php?id=' . (int) $resource['id'])) ?>">
                                <?php if ($thumb !== ''): ?>
                                    <img class="img-fluid rounded mb-2 profile-thumb" src="<?= e($thumb) ?>" alt="">
                                <?php else: ?>
                                    <div class="resource-cover-placeholder rounded mb-2"><?= $resource['type'] === 'video' ? '🎬' : '📄' ?></div>
                                <?php endif; ?>
                                <div class="small fw-semibold text-body"><?= e(excerpt($resource['title'], 24)) ?></div>
                                <div class="small text-secondary"><?= e($resource['type']) ?></div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if ($isOwn): ?>
            <div class="card border-0 shadow-sm mt-4" id="edit-profile">
                <div class="card-header bg-white fw-semibold">编辑资料</div>
                <div class="card-body">
                    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
                    <form method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="nickname">昵称</label>
                                <input class="form-control" id="nickname" name="nickname" maxlength="50" required value="<?= e($form['nickname']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="gender">性别</label>
                                <select class="form-select" id="gender" name="gender">
                                    <?php foreach ($genderLabels as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($form['gender'], $key) ?>><?= e($label) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="email">邮箱</label>
                                <input class="form-control" id="email" name="email" type="email" maxlength="100" value="<?= e($form['email']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">手机</label>
                                <input class="form-control" id="phone" name="phone" maxlength="20" value="<?= e($form['phone']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="avatar_file">头像图片</label>
                                <input class="form-control" id="avatar_file" name="avatar_file" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
                                <div class="form-text">JPG / PNG / GIF / WebP，最大 5 MB；不选择则保留当前头像。</div>
                                <img class="avatar avatar-lg mt-2" src="<?= e(avatar_url($form['avatar'])) ?>" alt="当前头像">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="intro">个人简介</label>
                                <textarea class="form-control" id="intro" name="intro" rows="3" maxlength="500"><?= e($form['intro']) ?></textarea>
                            </div>
                        </div>
                        <button class="btn btn-primary mt-3" type="submit">保存资料</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
