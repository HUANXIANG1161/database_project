<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_login();

$me = current_user();
$uid = (int) $me['id'];

if (is_post()) {
    verify_csrf();
    $with = (int) input('with');
    $content = trim((string) input('content'));
    $isFriend = db_val(
        'SELECT COUNT(*) FROM `friendship` WHERE `status` = "accepted" AND ((`user_id` = ? AND `friend_id` = ?) OR (`user_id` = ? AND `friend_id` = ?))',
        [$uid, $with, $with, $uid]
    );
    if ((int) $isFriend === 0) {
        flash('danger', '只能给好友发送私聊。');
        redirect('/friends.php');
    }
    if ($content === '' || mb_strlen($content, 'UTF-8') > 1000) {
        flash('danger', '消息不能为空，且不能超过 1000 字。');
    } else {
        db_exec('INSERT INTO `message` (`from_id`, `to_id`, `content`, `msg_type`) VALUES (?, ?, ?, "text")', [$uid, $with, $content]);
        db_exec('UPDATE `message` SET `is_read` = 1 WHERE `from_id` = ? AND `to_id` = ? AND `is_read` = 0', [$with, $uid]);
    }
    redirect('/messages.php?with=' . $with);
}

$with = (int) query('with', '0');
$conversations = db_all(
    'SELECT u.`id`, u.`nickname`, u.`avatar`,
            (SELECT m.`content` FROM `message` m WHERE (m.`from_id` = u.`id` AND m.`to_id` = ?) OR (m.`from_id` = ? AND m.`to_id` = u.`id`) ORDER BY m.`id` DESC LIMIT 1) AS `last_message`,
            (SELECT m.`created_at` FROM `message` m WHERE (m.`from_id` = u.`id` AND m.`to_id` = ?) OR (m.`from_id` = ? AND m.`to_id` = u.`id`) ORDER BY m.`id` DESC LIMIT 1) AS `last_at`,
            (SELECT COUNT(*) FROM `message` m WHERE m.`from_id` = u.`id` AND m.`to_id` = ? AND m.`is_read` = 0) AS `unread`
     FROM `friendship` f
     JOIN `user` u ON u.`id` = IF(f.`user_id` = ?, f.`friend_id`, f.`user_id`)
     WHERE f.`status` = "accepted" AND (f.`user_id` = ? OR f.`friend_id` = ?)
     ORDER BY `last_at` DESC, u.`nickname`',
    [$uid, $uid, $uid, $uid, $uid, $uid, $uid, $uid]
);

$withUser = null;
foreach ($conversations as $conversation) {
    if ((int) $conversation['id'] === $with) {
        $withUser = $conversation;
        break;
    }
}
if ($with > 0 && $withUser === null) {
    flash('danger', '只能和好友私聊。');
    redirect('/messages.php');
}
if ($withUser !== null) {
    db_exec('UPDATE `message` SET `is_read` = 1 WHERE `from_id` = ? AND `to_id` = ? AND `is_read` = 0', [$with, $uid]);
    foreach ($conversations as &$conversation) {
        if ((int) $conversation['id'] === $with) {
            $conversation['unread'] = 0;
        }
    }
    unset($conversation);
}
$messages = [];
if ($withUser !== null) {
    $messages = db_all(
        'SELECT * FROM `message`
         WHERE (`from_id` = ? AND `to_id` = ?) OR (`from_id` = ? AND `to_id` = ?)
         ORDER BY `id` ASC',
        [$uid, $with, $with, $uid]
    );
}
$totalUnread = array_sum(array_map(static fn(array $row): int => (int) $row['unread'], $conversations));

$pageTitle = '私聊';
$activeNav = 'friends';
require APP_ROOT . '/app/views/header.php';
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">私聊</h1>
        <p class="text-secondary mb-0">与好友沟通行程细节。未读消息 <?= (int) $totalUnread ?> 条。</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/friends.php')) ?>">好友管理</a>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">会话</div>
            <div class="list-group list-group-flush conversation-list">
                <?php if (!$conversations): ?><div class="list-group-item text-secondary small">还没有好友，先去添加好友。</div><?php endif; ?>
                <?php foreach ($conversations as $conversation): ?>
                    <a class="list-group-item list-group-item-action<?= (int) $conversation['id'] === $with ? ' active' : '' ?>" href="<?= e(url('/messages.php?with=' . (int) $conversation['id'])) ?>">
                        <div class="d-flex align-items-center gap-2">
                            <img class="avatar avatar-sm" src="<?= e(avatar_url($conversation['avatar'])) ?>" alt="">
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex justify-content-between gap-2">
                                    <span class="fw-semibold"><?= e($conversation['nickname']) ?></span>
                                    <?php if ((int) $conversation['unread'] > 0): ?><span class="badge text-bg-danger"><?= (int) $conversation['unread'] ?></span><?php endif; ?>
                                </div>
                                <div class="small text-truncate <?= (int) $conversation['id'] === $with ? 'text-white-50' : 'text-secondary' ?>"><?= e($conversation['last_message'] !== null ? excerpt($conversation['last_message'], 28) : '还没有消息') ?></div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <?php if ($withUser === null): ?>
                <div class="card-body d-flex align-items-center justify-content-center text-secondary" style="min-height: 360px;">选择左侧好友开始聊天。</div>
            <?php else: ?>
                <div class="card-header bg-white d-flex align-items-center gap-2">
                    <img class="avatar avatar-sm" src="<?= e(avatar_url($withUser['avatar'])) ?>" alt="">
                    <a class="fw-semibold text-decoration-none" href="<?= e(url('/profile.php?id=' . (int) $withUser['id'])) ?>"><?= e($withUser['nickname']) ?></a>
                </div>
                <div class="card-body message-thread" id="message-thread">
                    <?php if (!$messages): ?><p class="text-secondary small">还没有消息，打个招呼吧。</p><?php endif; ?>
                    <?php foreach ($messages as $message): ?>
                        <?php $mine = (int) $message['from_id'] === $uid; ?>
                        <div class="message-row <?= $mine ? 'mine' : 'theirs' ?>">
                            <div class="message-bubble">
                                <div><?= nl2br(e($message['content'])) ?></div>
                                <div class="message-time"><?= e(format_dt($message['created_at'])) ?><?= $mine && (int) $message['is_read'] === 1 ? ' · 已读' : '' ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="card-footer bg-white">
                    <form method="post" class="d-flex gap-2 align-items-end">
                        <?= csrf_field() ?>
                        <input type="hidden" name="with" value="<?= (int) $withUser['id'] ?>">
                        <textarea class="form-control flex-grow-1" name="content" rows="2" maxlength="1000" required placeholder="输入消息，Enter 发送需换行时请用 Shift+Enter"></textarea>
                        <button class="btn btn-primary flex-shrink-0" type="submit">发送</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
