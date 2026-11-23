<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_login();

$me = current_user();
$uid = (int) $me['id'];

if (is_post()) {
    verify_csrf();
    $action = (string) input('action');
    $targetId = (int) input('user_id');
    if ($targetId === $uid) {
        flash('danger', '不能对自己操作。');
        redirect('/friends.php');
    }
    $target = db_one('SELECT * FROM `user` WHERE `id` = ? AND `status` = "active"', [$targetId]);
    if ($target === null) {
        flash('danger', '用户不存在。');
        redirect('/friends.php');
    }
    $existing = db_one(
        'SELECT * FROM `friendship` WHERE (`user_id` = ? AND `friend_id` = ?) OR (`user_id` = ? AND `friend_id` = ?) LIMIT 1',
        [$uid, $targetId, $targetId, $uid]
    );

    if ($action === 'add') {
        if ($existing !== null) {
            if ($existing['status'] === 'accepted') {
                flash('info', '你们已经是好友了。');
            } elseif ($existing['status'] === 'blocked') {
                flash('danger', '当前无法添加该用户。');
            } elseif ((int) $existing['user_id'] === $uid) {
                flash('info', '好友申请已发送，等待对方处理。');
            } else {
                flash('info', '对方已向你发出申请，请在“待处理申请”中接受。');
            }
        } else {
            db_exec('INSERT INTO `friendship` (`user_id`, `friend_id`, `status`) VALUES (?, ?, "pending")', [$uid, $targetId]);
            db_exec(
                'INSERT INTO `notification` (`user_id`, `type`, `title`, `content`, `link`) VALUES (?, "friend_request", ?, ?, "/friends.php")',
                [$targetId, '新的好友申请', mb_substr($me['nickname'] . ' 请求加你为好友。', 0, 255)]
            );
            flash('success', '好友申请已发送。');
        }
    } elseif ($action === 'accept') {
        if ($existing !== null && $existing['status'] === 'pending' && (int) $existing['friend_id'] === $uid) {
            db_exec('UPDATE `friendship` SET `status` = "accepted" WHERE `id` = ?', [(int) $existing['id']]);
            db_exec('DELETE FROM `friendship` WHERE `user_id` = ? AND `friend_id` = ? AND `status` = "pending"', [$uid, $targetId]);
            db_exec(
                'INSERT INTO `notification` (`user_id`, `type`, `title`, `content`, `link`) VALUES (?, "friend_accept", ?, ?, "/friends.php")',
                [$targetId, '好友申请已通过', mb_substr($me['nickname'] . ' 通过了你的好友申请。', 0, 255)]
            );
            flash('success', '已添加好友。');
        } else {
            flash('danger', '没有找到待处理的好友申请。');
        }
    } elseif ($action === 'reject') {
        if ($existing !== null && $existing['status'] === 'pending' && (int) $existing['friend_id'] === $uid) {
            db_exec('DELETE FROM `friendship` WHERE `id` = ?', [(int) $existing['id']]);
            flash('success', '已拒绝好友申请。');
        } else {
            flash('danger', '没有找到待处理的好友申请。');
        }
    } elseif ($action === 'remove') {
        db_exec(
            'DELETE FROM `friendship` WHERE (`user_id` = ? AND `friend_id` = ?) OR (`user_id` = ? AND `friend_id` = ?)',
            [$uid, $targetId, $targetId, $uid]
        );
        flash('success', '已解除好友关系。');
    } elseif ($action === 'block') {
        if ($existing !== null) {
            db_exec('UPDATE `friendship` SET `status` = "blocked" WHERE `id` = ?', [(int) $existing['id']]);
            db_exec(
                'UPDATE `friendship` SET `status` = "blocked" WHERE (`user_id` = ? AND `friend_id` = ?) OR (`user_id` = ? AND `friend_id` = ?)',
                [$uid, $targetId, $targetId, $uid]
            );
        } else {
            db_exec('INSERT INTO `friendship` (`user_id`, `friend_id`, `status`) VALUES (?, ?, "blocked")', [$uid, $targetId]);
        }
        flash('success', '已拉黑该用户。');
    }
    redirect('/friends.php');
}

$friends = db_all(
    'SELECT u.*, f.`user_id` AS `requester_id`
     FROM `friendship` f
     JOIN `user` u ON u.`id` = IF(f.`user_id` = ?, f.`friend_id`, f.`user_id`)
     WHERE f.`status` = "accepted" AND (f.`user_id` = ? OR f.`friend_id` = ?)
     ORDER BY u.`nickname`',
    [$uid, $uid, $uid]
);
$incoming = db_all(
    'SELECT f.`id` AS `friendship_id`, f.`user_id` AS `requester_id`, u.*
     FROM `friendship` f JOIN `user` u ON u.`id` = f.`user_id`
     WHERE f.`friend_id` = ? AND f.`status` = "pending"
     ORDER BY f.`created_at` DESC',
    [$uid]
);
$outgoing = db_all(
    'SELECT f.`id` AS `friendship_id`, f.`friend_id` AS `target_id`, u.*
     FROM `friendship` f JOIN `user` u ON u.`id` = f.`friend_id`
     WHERE f.`user_id` = ? AND f.`status` = "pending"
     ORDER BY f.`created_at` DESC',
    [$uid]
);

$q = trim((string) query('q'));
$searchResults = [];
if ($q !== '') {
    $searchResults = db_all(
        'SELECT * FROM `user`
         WHERE `id` <> ? AND `status` = "active" AND (`username` LIKE ? OR `nickname` LIKE ?)
         ORDER BY `nickname` LIMIT 12',
        [$uid, '%' . $q . '%', '%' . $q . '%']
    );
}

$pageTitle = '好友';
$activeNav = 'friends';
require APP_ROOT . '/app/views/header.php';
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">好友</h1>
        <p class="text-secondary mb-0">添加同行伙伴，旅行结束后继续分享和聊天。</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= e(url('/messages.php')) ?>">我的私聊</a>
</div>

<?php if ($incoming): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">待处理申请（<?= count($incoming) ?>）</div>
        <div class="list-group list-group-flush">
            <?php foreach ($incoming as $item): ?>
                <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
                    <img class="avatar avatar-sm" src="<?= e(avatar_url($item['avatar'])) ?>" alt="">
                    <div class="flex-grow-1">
                        <a class="fw-semibold text-decoration-none" href="<?= e(url('/profile.php?id=' . (int) $item['id'])) ?>"><?= e($item['nickname']) ?></a>
                        <span class="small text-secondary">@<?= e($item['username']) ?> · <?= e(excerpt($item['intro'] ?? '', 40)) ?></span>
                    </div>
                    <form method="post" class="d-flex gap-1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="accept">
                        <input type="hidden" name="user_id" value="<?= (int) $item['requester_id'] ?>">
                        <button class="btn btn-sm btn-primary" type="submit">接受</button>
                    </form>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="user_id" value="<?= (int) $item['requester_id'] ?>">
                        <button class="btn btn-sm btn-outline-secondary" type="submit">拒绝</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">我的好友（<?= count($friends) ?>）</div>
            <div class="card-body">
                <?php if (!$friends): ?><p class="text-secondary mb-0">还没有好友，搜索并添加同行伙伴吧。</p><?php endif; ?>
                <div class="row g-3">
                    <?php foreach ($friends as $friend): ?>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100 d-flex gap-3">
                                <img class="avatar" src="<?= e(avatar_url($friend['avatar'])) ?>" alt="">
                                <div class="flex-grow-1">
                                    <a class="fw-semibold text-decoration-none" href="<?= e(url('/profile.php?id=' . (int) $friend['id'])) ?>"><?= e($friend['nickname']) ?></a>
                                    <div class="small text-secondary">@<?= e($friend['username']) ?></div>
                                    <div class="small text-secondary mb-2"><?= e(excerpt($friend['intro'] ?? '', 45)) ?></div>
                                    <div class="d-flex gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/messages.php?with=' . (int) $friend['id'])) ?>">私聊</a>
                                        <form method="post" onsubmit="return confirm('解除与 <?= e($friend['nickname']) ?> 的好友关系？');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="user_id" value="<?= (int) $friend['id'] ?>">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">移除</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if ($outgoing): ?>
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white fw-semibold">我发出的申请</div>
                <div class="list-group list-group-flush">
                    <?php foreach ($outgoing as $item): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><?= e($item['nickname']) ?> <span class="small text-secondary">@<?= e($item['username']) ?></span></span>
                            <span class="badge text-bg-warning">等待通过</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">查找旅伴</div>
            <div class="card-body">
                <form method="get" class="d-flex gap-2 mb-3">
                    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="用户名或昵称">
                    <button class="btn btn-primary" type="submit">搜索</button>
                </form>
                <?php if ($q !== '' && !$searchResults): ?><p class="small text-secondary mb-0">没有找到用户。</p><?php endif; ?>
                <?php foreach ($searchResults as $user): ?>
                    <?php
                    $rel = db_one(
                        'SELECT * FROM `friendship` WHERE (`user_id` = ? AND `friend_id` = ?) OR (`user_id` = ? AND `friend_id` = ?) LIMIT 1',
                        [$uid, (int) $user['id'], (int) $user['id'], $uid]
                    );
                    ?>
                    <div class="d-flex align-items-center gap-2 py-2 border-bottom">
                        <img class="avatar avatar-sm" src="<?= e(avatar_url($user['avatar'])) ?>" alt="">
                        <div class="flex-grow-1">
                            <a class="small fw-semibold text-decoration-none" href="<?= e(url('/profile.php?id=' . (int) $user['id'])) ?>"><?= e($user['nickname']) ?></a>
                            <div class="small text-secondary">@<?= e($user['username']) ?></div>
                        </div>
                        <?php if ($rel === null): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                <button class="btn btn-sm btn-outline-primary" type="submit">加好友</button>
                            </form>
                        <?php elseif ($rel['status'] === 'accepted'): ?>
                            <span class="badge text-bg-success">好友</span>
                        <?php elseif ($rel['status'] === 'pending' && (int) $rel['user_id'] === $uid): ?>
                            <span class="badge text-bg-warning">已申请</span>
                        <?php elseif ($rel['status'] === 'pending'): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="accept">
                                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                <button class="btn btn-sm btn-primary" type="submit">接受</button>
                            </form>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">不可添加</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body small text-secondary">
                提示：好友通过后才可以私聊；被拉黑的用户不会出现在好友列表。
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
