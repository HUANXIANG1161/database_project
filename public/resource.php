<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$id = (int) query('id');
$resource = db_one(
    'SELECT r.*, u.`nickname`, u.`avatar`, u.`intro`, t.`title` AS `trip_title`
     FROM `resource` r
     JOIN `user` u ON u.`id` = r.`uploader_id`
     LEFT JOIN `trip` t ON t.`id` = r.`trip_id`
     WHERE r.`id` = ? LIMIT 1',
    [$id]
);
if ($resource === null) {
    http_response_code(404);
    exit('资源不存在。');
}
$me = current_user();
$uid = $me !== null ? (int) $me['id'] : 0;
$canManage = $me !== null && ((int) $resource['uploader_id'] === $uid || is_admin());
if ($resource['status'] !== 'published' && !$canManage) {
    http_response_code(404);
    exit('资源不存在或尚未公开。');
}

if (is_post()) {
    $action = (string) input('action');
    if ($action === 'like') {
        require_login();
        verify_csrf();
        $liked = db_one('SELECT `id` FROM `like_record` WHERE `user_id` = ? AND `resource_id` = ?', [$uid, $id]);
        if ($liked !== null) {
            db_exec('DELETE FROM `like_record` WHERE `id` = ?', [(int) $liked['id']]);
        } else {
            db_exec('INSERT INTO `like_record` (`user_id`, `resource_id`) VALUES (?, ?)', [$uid, $id]);
        }
        redirect('/resource.php?id=' . $id);
    }
    if ($action === 'comment') {
        require_login();
        verify_csrf();
        $content = trim((string) input('content'));
        $parentId = (int) input('parent_id', '0');
        if ($content === '') {
            flash('danger', '评论内容不能为空。');
        } elseif (mb_strlen($content, 'UTF-8') > 1000) {
            flash('danger', '评论不能超过 1000 字。');
        } else {
            if ($parentId > 0) {
                $parent = db_one('SELECT `id` FROM `comment` WHERE `id` = ? AND `resource_id` = ?', [$parentId, $id]);
                if ($parent === null) {
                    $parentId = 0;
                }
            }
            db_exec(
                'INSERT INTO `comment` (`resource_id`, `user_id`, `parent_id`, `content`) VALUES (?, ?, ?, ?)',
                [$id, $uid, $parentId > 0 ? $parentId : null, $content]
            );
            if ((int) $resource['uploader_id'] !== $uid) {
                db_exec(
                    'INSERT INTO `notification` (`user_id`, `type`, `title`, `content`, `link`) VALUES (?, "comment", ?, ?, ?)',
                    [(int) $resource['uploader_id'], '资源收到新评论', mb_substr($me['nickname'] . ' 评论了你的资源《' . $resource['title'] . '》', 0, 255), '/resource.php?id=' . $id]
                );
            }
            flash('success', '评论已发布。');
        }
        redirect('/resource.php?id=' . $id . '#comments');
    }
    if ($action === 'hide' && $canManage) {
        verify_csrf();
        db_exec('UPDATE `resource` SET `status` = "hidden" WHERE `id` = ?', [$id]);
        flash('success', '资源已隐藏。');
        redirect('/resources.php');
    }
}

db_exec('UPDATE `resource` SET `view_count` = `view_count` + 1 WHERE `id` = ?', [$id]);
$resource['view_count'] = (int) $resource['view_count'] + 1;
$liked = $me !== null && db_one('SELECT `id` FROM `like_record` WHERE `user_id` = ? AND `resource_id` = ?', [$uid, $id]) !== null;
$comments = db_all(
    'SELECT c.*, u.`nickname`, u.`avatar`, p.`content` AS `parent_content`, pu.`nickname` AS `parent_nickname`
     FROM `comment` c
     JOIN `user` u ON u.`id` = c.`user_id`
     LEFT JOIN `comment` p ON p.`id` = c.`parent_id`
     LEFT JOIN `user` pu ON pu.`id` = p.`user_id`
     WHERE c.`resource_id` = ?
     ORDER BY c.`created_at` ASC',
    [$id]
);
$replyTo = (int) query('reply', '0');
$replyComment = null;
if ($replyTo > 0) {
    foreach ($comments as $comment) {
        if ((int) $comment['id'] === $replyTo) {
            $replyComment = $comment;
            break;
        }
    }
}
$typeLabels = ['photo' => '照片', 'video' => '视频', 'doc' => '文档'];
$typeIcons = ['photo' => '📷', 'video' => '🎬', 'doc' => '📄'];
$fileUrl = (string) $resource['file_url'];

$pageTitle = $resource['title'];
$activeNav = 'resources';
require APP_ROOT . '/app/views/header.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(url('/resources.php')) ?>">资源</a></li>
        <li class="breadcrumb-item active"><?= e(excerpt($resource['title'], 24)) ?></li>
    </ol>
</nav>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <span class="badge text-bg-light"><?= $typeIcons[$resource['type']] ?? '📁' ?> <?= e($typeLabels[$resource['type']] ?? $resource['type']) ?></span>
                    <?= status_html($resource['status']) ?>
                    <?php if (!empty($resource['location'])): ?><span class="small text-secondary">📍 <?= e($resource['location']) ?></span><?php endif; ?>
                </div>
                <h1 class="h3"><?= e($resource['title']) ?></h1>
                <div class="small text-secondary mb-3">
                    <?= e($resource['nickname']) ?> 上传于 <?= e(format_dt($resource['created_at'])) ?>
                    <?php if (!empty($resource['trip_title'])): ?> · 关联行程：<?= e($resource['trip_title']) ?><?php endif; ?>
                </div>
                <?php if (!empty($resource['description'])): ?><p class="mb-3"><?= nl2br(e($resource['description'])) ?></p><?php endif; ?>

                <div class="mb-4">
                    <?php if ($resource['type'] === 'photo'): ?>
                        <img class="img-fluid rounded w-100" src="<?= e(cover_url($fileUrl)) ?>" alt="<?= e($resource['title']) ?>">
                    <?php elseif ($resource['type'] === 'video'): ?>
                        <video class="w-100 rounded bg-black" controls preload="metadata"<?php if (!empty($resource['cover'])): ?> poster="<?= e(cover_url($resource['cover'])) ?>"<?php endif; ?>>
                            <source src="<?= e(url($fileUrl)) ?>">
                            你的浏览器不支持视频播放。
                        </video>
                    <?php else: ?>
                        <?php if (!empty($resource['cover'])): ?>
                            <img class="img-fluid rounded w-100 mb-3" src="<?= e(cover_url($resource['cover'])) ?>" alt="<?= e($resource['title']) ?>">
                        <?php endif; ?>
                        <div class="border rounded p-4 d-flex align-items-center gap-3">
                            <div class="display-5">📄</div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?= e($resource['title']) ?></div>
                                <div class="small text-secondary">文档资源</div>
                            </div>
                            <a class="btn btn-primary" href="<?= e(url($fileUrl)) ?>" target="_blank" rel="noopener">打开 / 下载</a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($me !== null): ?>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="like">
                            <button class="btn <?= $liked ? 'btn-danger' : 'btn-outline-danger' ?>" type="submit">
                                <?= $liked ? '已赞' : '点赞' ?> <span class="badge text-bg-light"><?= (int) $resource['like_count'] ?></span>
                            </button>
                        </form>
                        <a class="btn btn-outline-secondary" href="#comment-form">评论 <?= (int) $resource['comment_count'] ?></a>
                        <?php if ($canManage && $resource['status'] !== 'hidden'): ?>
                            <form method="post" onsubmit="return confirm('隐藏后其他用户将看不到该资源，确定吗？');" class="ms-auto">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="hide">
                                <button class="btn btn-outline-secondary" type="submit">隐藏资源</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <a class="btn btn-outline-danger" href="<?= e(url('/login.php?next=' . rawurlencode('/resource.php?id=' . $id))) ?>">登录后点赞 / 评论</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4" id="comments">
            <div class="card-header bg-white fw-semibold">评论（<?= count($comments) ?>）</div>
            <div class="card-body">
                <?php if (!$comments): ?><p class="text-secondary mb-0">还没有评论，来抢沙发。</p><?php endif; ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="comment-item" id="comment-<?= (int) $comment['id'] ?>">
                        <img class="avatar avatar-sm" src="<?= e(avatar_url($comment['avatar'])) ?>" alt="">
                        <div class="flex-grow-1">
                            <div class="small"><strong><?= e($comment['nickname']) ?></strong> <span class="text-secondary"><?= e(format_dt($comment['created_at'])) ?></span></div>
                            <?php if (!empty($comment['parent_content'])): ?>
                                <div class="comment-quote small text-secondary">回复 <?= e($comment['parent_nickname'] ?? '') ?>：<?= e(excerpt($comment['parent_content'], 80)) ?></div>
                            <?php endif; ?>
                            <div><?= nl2br(e($comment['content'])) ?></div>
                            <?php if ($me !== null): ?><a class="small" href="<?= e(url('/resource.php?id=' . $id . '&reply=' . (int) $comment['id'] . '#comment-form')) ?>">回复</a><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($me !== null): ?>
                    <form method="post" class="mt-3" id="comment-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="comment">
                        <?php if ($replyComment !== null): ?>
                            <input type="hidden" name="parent_id" value="<?= (int) $replyComment['id'] ?>">
                            <div class="alert alert-light border py-2 small d-flex justify-content-between">
                                <span>回复 <?= e($replyComment['nickname']) ?>：<?= e(excerpt($replyComment['content'], 80)) ?></span>
                                <a href="<?= e(url('/resource.php?id=' . $id . '#comment-form')) ?>">取消</a>
                            </div>
                        <?php endif; ?>
                        <label class="form-label" for="content">发表评论</label>
                        <textarea class="form-control" id="content" name="content" rows="3" maxlength="1000" required></textarea>
                        <button class="btn btn-primary mt-2" type="submit">发布评论</button>
                    </form>
                <?php else: ?>
                    <p class="small mt-3 mb-0"><a href="<?= e(url('/login.php?next=' . rawurlencode('/resource.php?id=' . $id))) ?>">登录</a> 后参与评论。</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <img class="avatar avatar-lg" src="<?= e(avatar_url($resource['avatar'])) ?>" alt="">
                <h2 class="h6 mt-2"><?= e($resource['nickname']) ?></h2>
                <p class="small text-secondary"><?= e(excerpt($resource['intro'] ?? '', 60)) ?></p>
                <div class="row text-center small">
                    <div class="col"><div class="fw-semibold"><?= (int) $resource['view_count'] ?></div><div class="text-secondary">浏览</div></div>
                    <div class="col"><div class="fw-semibold"><?= (int) $resource['like_count'] ?></div><div class="text-secondary">点赞</div></div>
                    <div class="col"><div class="fw-semibold"><?= (int) $resource['comment_count'] ?></div><div class="text-secondary">评论</div></div>
                </div>
            </div>
        </div>
        <?php if (!empty($resource['trip_title'])): ?>
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body">
                    <div class="small text-secondary mb-1">关联行程</div>
                    <a class="fw-semibold text-decoration-none" href="<?= e(url('/trip.php?id=' . (int) $resource['trip_id'])) ?>"><?= e($resource['trip_title']) ?></a>
                </div>
            </div>
        <?php endif; ?>
        <a class="btn btn-outline-secondary w-100 mt-3" href="<?= e(url('/resources.php')) ?>">返回资源列表</a>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
