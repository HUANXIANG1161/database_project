<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$id = (int) query('id');
if ($id <= 0) {
    http_response_code(404);
    $pageTitle = '攻略不存在';
    require APP_ROOT . '/app/views/header.php';
    echo '<div class="alert alert-warning">攻略不存在。</div>';
    require APP_ROOT . '/app/views/footer.php';
    exit;
}

$guide = db_one(
    'SELECT g.*, u.`nickname` AS `author_nickname`, u.`avatar` AS `author_avatar`, u.`intro` AS `author_intro`
     FROM `guide` g JOIN `user` u ON u.`id` = g.`author_id`
     WHERE g.`id` = ? LIMIT 1',
    [$id]
);
if ($guide === null) {
    http_response_code(404);
    $pageTitle = '攻略不存在';
    require APP_ROOT . '/app/views/header.php';
    echo '<div class="alert alert-warning">攻略不存在。</div>';
    require APP_ROOT . '/app/views/footer.php';
    exit;
}

$me = current_user();
$owner = $me !== null && ((int) $guide['author_id'] === (int) $me['id'] || is_admin());
if ($guide['status'] !== 'published' && !$owner) {
    http_response_code(404);
    $pageTitle = '攻略不存在';
    require APP_ROOT . '/app/views/header.php';
    echo '<div class="alert alert-warning">该攻略尚未公开。</div>';
    require APP_ROOT . '/app/views/footer.php';
    exit;
}

$isLiked = false;
$isCollected = false;
if ($me !== null) {
    $isLiked = (int) db_val('SELECT COUNT(*) FROM `like_record` WHERE `user_id` = ? AND `guide_id` = ?', [$me['id'], $id]) > 0;
    $isCollected = (int) db_val('SELECT COUNT(*) FROM `guide_collect` WHERE `user_id` = ? AND `guide_id` = ?', [$me['id'], $id]) > 0;
}

if (is_post()) {
    verify_csrf();
    require_login();
    $action = (string) input('action');
    $uid = (int) $me['id'];

    if ($action === 'like') {
        if ($isLiked) {
            db_exec('DELETE FROM `like_record` WHERE `user_id` = ? AND `guide_id` = ?', [$uid, $id]);
            flash('success', '已取消点赞。');
        } else {
            db_exec('INSERT IGNORE INTO `like_record` (`user_id`, `guide_id`) VALUES (?, ?)', [$uid, $id]);
            flash('success', '已点赞。');
        }
        redirect('/guide.php?id=' . $id);
    }

    if ($action === 'collect') {
        if ($isCollected) {
            db_exec('DELETE FROM `guide_collect` WHERE `user_id` = ? AND `guide_id` = ?', [$uid, $id]);
            flash('success', '已取消收藏。');
        } else {
            db_exec('INSERT IGNORE INTO `guide_collect` (`user_id`, `guide_id`) VALUES (?, ?)', [$uid, $id]);
            flash('success', '已收藏。');
        }
        redirect('/guide.php?id=' . $id);
    }

    if ($action === 'comment') {
        $content = (string) input('content');
        if ($content === '') {
            flash('danger', '评论内容不能为空。');
            redirect('/guide.php?id=' . $id);
        }
        if (mb_strlen($content, 'UTF-8') > 1000) {
            flash('danger', '评论不能超过 1000 字。');
            redirect('/guide.php?id=' . $id);
        }
        $parentId = (int) input('parent_id', 0);
        if ($parentId > 0) {
            $valid = (int) db_val('SELECT COUNT(*) FROM `comment` WHERE `id` = ? AND `guide_id` = ?', [$parentId, $id]);
            if ($valid === 0) {
                $parentId = 0;
            }
        }
        db_exec(
            'INSERT INTO `comment` (`guide_id`, `user_id`, `parent_id`, `content`) VALUES (?, ?, ?, ?)',
            [$id, $uid, $parentId > 0 ? $parentId : null, $content]
        );
        if ((int) $guide['author_id'] !== $uid) {
            db_exec(
                'INSERT INTO `notification` (`user_id`, `type`, `title`, `content`, `link`) VALUES (?, "comment", "攻略收到新评论", ?, ?)',
                [(int) $guide['author_id'], mb_substr($content, 0, 80, 'UTF-8'), '/guide.php?id=' . $id]
            );
        }
        flash('success', '评论已发布。');
        redirect('/guide.php?id=' . $id);
    }
}

db_exec('UPDATE `guide` SET `view_count` = `view_count` + 1 WHERE `id` = ?', [$id]);

$tags = db_all(
    'SELECT t.* FROM `guide_tag` gt JOIN `tag` t ON t.`id` = gt.`tag_id` WHERE gt.`guide_id` = ? ORDER BY t.`name`',
    [$id]
);
$comments = db_all(
    'SELECT c.*, u.`nickname`, u.`avatar`, pu.`nickname` AS `parent_nickname`
     FROM `comment` c
     JOIN `user` u ON u.`id` = c.`user_id`
     LEFT JOIN `comment` pc ON pc.`id` = c.`parent_id`
     LEFT JOIN `user` pu ON pu.`id` = pc.`user_id`
     WHERE c.`guide_id` = ?
     ORDER BY c.`created_at` ASC',
    [$id]
);

$replyTo = (int) query('reply', 0);
$pageTitle = $guide['title'];
$activeNav = 'guides';
require APP_ROOT . '/app/views/header.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(url('/index.php')) ?>">首页</a></li>
        <li class="breadcrumb-item"><a href="<?= e(url('/guides.php')) ?>">攻略</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e(excerpt($guide['title'], 24)) ?></li>
    </ol>
</nav>

<article class="card border-0 shadow-sm mb-4">
    <img src="<?= e(cover_url($guide['cover'])) ?>" class="card-img-top guide-detail-cover" alt="<?= e($guide['title']) ?>">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="flex-grow-1">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <?= status_html($guide['status']) ?>
                    <span class="badge text-bg-light"><?= e($guide['destination']) ?></span>
                    <span class="badge text-bg-light"><?= (int) $guide['day_count'] ?> 天</span>
                    <span class="badge text-bg-light">人均 ¥<?= money($guide['cost_avg']) ?></span>
                </div>
                <h1 class="h2 mb-2"><?= e($guide['title']) ?></h1>
                <p class="text-secondary mb-0"><?= e($guide['summary']) ?></p>
            </div>
            <?php if ($owner): ?>
                <a class="btn btn-outline-primary" href="<?= e(url('/guide_edit.php?id=' . $id)) ?>">编辑攻略</a>
            <?php endif; ?>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-3 py-3 my-3 border-top border-bottom">
            <img src="<?= e(avatar_url($guide['author_avatar'])) ?>" class="rounded-circle" width="40" height="40" alt="">
            <div class="me-auto">
                <div class="fw-semibold"><?= e($guide['author_nickname']) ?></div>
                <div class="small text-secondary"><?= e(format_dt($guide['created_at'])) ?> · 浏览 <?= (int) $guide['view_count'] ?></div>
            </div>
            <form method="post" action="<?= e(url('/guide.php?id=' . $id)) ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="like">
                <button class="btn btn-sm <?= $isLiked ? 'btn-danger' : 'btn-outline-danger' ?>" type="submit">赞 <?= (int) $guide['like_count'] ?></button>
            </form>
            <form method="post" action="<?= e(url('/guide.php?id=' . $id)) ?>" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="collect">
                <button class="btn btn-sm <?= $isCollected ? 'btn-warning' : 'btn-outline-warning' ?>" type="submit">收藏 <?= (int) $guide['collect_count'] ?></button>
            </form>
        </div>

        <?php if ($tags): ?>
            <div class="mb-3">
                <?php foreach ($tags as $tag): ?>
                    <a class="badge rounded-pill text-bg-light border text-decoration-none me-1" href="<?= e(url('/guides.php?tag=' . urlencode($tag['name']))) ?>"><?= e($tag['name']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="guide-content"><?= nl2br(e($guide['content'])) ?></div>
    </div>
</article>

<section class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">评论 <span class="text-secondary">(<?= (int) $guide['comment_count'] ?>)</span></h2>
        </div>

        <?php if ($me !== null): ?>
            <form method="post" action="<?= e(url('/guide.php?id=' . $id)) ?>" class="mb-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="comment">
                <input type="hidden" name="parent_id" value="<?= $replyTo > 0 ? $replyTo : '' ?>">
                <?php if ($replyTo > 0): ?>
                    <div class="alert alert-light border py-2 small">正在回复评论 #<?= $replyTo ?>，<a href="<?= e(url('/guide.php?id=' . $id)) ?>">取消回复</a></div>
                <?php endif; ?>
                <label class="form-label" for="content">写下你的评论</label>
                <textarea class="form-control mb-2" id="content" name="content" rows="3" maxlength="1000" required placeholder="分享你的经验或提问…"></textarea>
                <button class="btn btn-primary" type="submit">发表评论</button>
            </form>
        <?php else: ?>
            <div class="alert alert-light border">请先<a href="<?= e(url('/login.php?next=' . urlencode('/guide.php?id=' . $id))) ?>">登录</a>后参与评论。</div>
        <?php endif; ?>

        <?php if (!$comments): ?>
            <div class="text-secondary">还没有评论，来抢沙发吧。</div>
        <?php endif; ?>
        <?php foreach ($comments as $c): ?>
            <div class="comment-item d-flex gap-3 py-3 border-top" id="comment-<?= (int) $c['id'] ?>">
                <img src="<?= e(avatar_url($c['avatar'])) ?>" class="rounded-circle flex-shrink-0" width="36" height="36" alt="">
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div class="small">
                            <strong><?= e($c['nickname']) ?></strong>
                            <?php if (!empty($c['parent_nickname'])): ?>
                                <span class="text-secondary">回复 @<?= e($c['parent_nickname']) ?></span>
                            <?php endif; ?>
                            <span class="text-secondary ms-2"><?= e(format_dt($c['created_at'])) ?></span>
                        </div>
                        <?php if ($me !== null): ?>
                            <a class="small text-decoration-none" href="<?= e(url('/guide.php?id=' . $id . '&reply=' . $c['id'])) ?>#comment-<?= (int) $c['id'] ?>">回复</a>
                        <?php endif; ?>
                    </div>
                    <div class="mt-1"><?= nl2br(e($c['content'])) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
