<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_login();

$me = current_user();
$uid = (int) $me['id'];

if (is_post()) {
    verify_csrf();
    $action = (string) input('action');
    if ($action === 'mark_all') {
        db_exec('UPDATE `notification` SET `is_read` = 1 WHERE `user_id` = ? AND `is_read` = 0', [$uid]);
        flash('success', '全部通知已标记为已读。');
    } elseif ($action === 'mark') {
        $id = (int) input('id');
        db_exec('UPDATE `notification` SET `is_read` = 1 WHERE `id` = ? AND `user_id` = ?', [$id, $uid]);
    }
    redirect('/notifications.php');
}

$notifications = db_all('SELECT * FROM `notification` WHERE `user_id` = ? ORDER BY `is_read` ASC, `created_at` DESC LIMIT 50', [$uid]);
$unread = (int) db_val('SELECT COUNT(*) FROM `notification` WHERE `user_id` = ? AND `is_read` = 0', [$uid]);
$typeIcons = ['comment' => '💬', 'like' => '❤️', 'collect' => '⭐', 'team_apply' => '👥', 'team_approve' => '✅', 'friend_request' => '🤝', 'friend_accept' => '🤝', 'guide_review' => '📝', 'resource_review' => '📎', 'system' => '🔔'];

$pageTitle = '通知';
$activeNav = '';
require APP_ROOT . '/app/views/header.php';
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">通知</h1>
        <p class="text-secondary mb-0">未读 <?= $unread ?> 条，共显示最近 <?= count($notifications) ?> 条。</p>
    </div>
    <?php if ($unread > 0): ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_all">
            <button class="btn btn-outline-secondary" type="submit">全部标记已读</button>
        </form>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="list-group list-group-flush">
        <?php if (!$notifications): ?><div class="list-group-item text-secondary">还没有通知。</div><?php endif; ?>
        <?php foreach ($notifications as $notification): ?>
            <?php
            $link = (string) ($notification['link'] ?? '');
            $link = str_replace(
                ['/team_detail.php', '/friend.php', '/guide_detail.php', '/trip_detail.php'],
                ['/team.php', '/friends.php', '/guide.php', '/trip.php'],
                $link
            );
            ?>
            <div class="list-group-item<?= (int) $notification['is_read'] === 0 ? ' notification-unread' : '' ?>">
                <div class="d-flex gap-3">
                    <div class="fs-5"><?= $typeIcons[$notification['type']] ?? '🔔' ?></div>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div class="fw-semibold"><?= e($notification['title']) ?></div>
                            <div class="small text-secondary"><?= e(format_dt($notification['created_at'])) ?></div>
                        </div>
                        <div class="small text-secondary"><?= e($notification['content']) ?></div>
                        <div class="mt-2 d-flex gap-2">
                            <?php if ($link !== ''): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url($link)) ?>">查看</a>
                            <?php endif; ?>
                            <?php if ((int) $notification['is_read'] === 0): ?>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="mark">
                                    <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">标记已读</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
