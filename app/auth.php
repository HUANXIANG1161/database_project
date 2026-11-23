<?php
/**
 * 登录态、权限判断与未读数。
 */
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cache = [];
    $id = (int) $_SESSION['user_id'];
    if (array_key_exists($id, $cache)) {
        return $cache[$id];
    }

    $user = db_one(
        'SELECT * FROM `user` WHERE `id` = ? AND `status` = "active" LIMIT 1',
        [$id]
    );
    if ($user === null) {
        unset($_SESSION['user_id']);
        return $cache[$id] = null;
    }
    return $cache[$id] = $user;
}

/**
 * 登录：支持用户名或邮箱。
 * @return array{ok: bool, message: string}
 */
function login_user(string $login, string $password): array
{
    $login = trim($login);
    if ($login === '' || $password === '') {
        return ['ok' => false, 'message' => '请输入账号和密码。'];
    }

    $user = db_one(
        'SELECT * FROM `user` WHERE `username` = ? OR `email` = ? LIMIT 1',
        [$login, $login]
    );
    if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
        return ['ok' => false, 'message' => '账号或密码不正确。'];
    }
    if ($user['status'] !== 'active') {
        return ['ok' => false, 'message' => '该账号已被禁用，请联系管理员。'];
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    unset($_SESSION['_csrf']);

    return ['ok' => true, 'message' => '登录成功。'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) ($params['secure'] ?? false), (bool) ($params['httponly'] ?? true));
    }
    session_destroy();
}

function require_login(): void
{
    if (current_user() !== null) {
        return;
    }
    $next = $_SERVER['REQUEST_URI'] ?? '/';
    flash('warning', '请先登录。');
    redirect('/login.php?next=' . urlencode($next));
}

function is_admin(): bool
{
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('403：需要管理员权限。');
    }
}

function can_edit_guide(array $guide): bool
{
    $user = current_user();
    return $user !== null && ((int) $guide['author_id'] === (int) $user['id'] || is_admin());
}

function can_edit_trip(array $trip): bool
{
    $user = current_user();
    return $user !== null && ((int) $trip['owner_id'] === (int) $user['id'] || is_admin());
}

function can_manage_team(array $team): bool
{
    $user = current_user();
    return $user !== null && ((int) $team['leader_id'] === (int) $user['id'] || is_admin());
}

function is_team_member(int $teamId, ?int $userId = null): bool
{
    $user = current_user();
    $userId ??= $user !== null ? (int) $user['id'] : 0;
    if ($userId <= 0) {
        return false;
    }
    $row = db_one(
        'SELECT t.`leader_id`, tm.`status`
         FROM `team` t
         LEFT JOIN `team_member` tm
           ON tm.`team_id` = t.`id` AND tm.`user_id` = ?
         WHERE t.`id` = ? LIMIT 1',
        [$userId, $teamId]
    );
    if ($row === null) {
        return false;
    }
    return (int) $row['leader_id'] === $userId || $row['status'] === 'approved';
}

/**
 * 路线编辑权限：管理员、行程所有者、队长、已通过队员。
 */
function can_edit_route(int $tripId): bool
{
    $user = current_user();
    if ($user === null) {
        return false;
    }
    if (is_admin()) {
        return true;
    }
    $trip = db_one('SELECT `owner_id` FROM `trip` WHERE `id` = ?', [$tripId]);
    if ($trip === null) {
        return false;
    }
    if ((int) $trip['owner_id'] === (int) $user['id']) {
        return true;
    }
    $team = db_one('SELECT `id`, `leader_id` FROM `team` WHERE `trip_id` = ?', [$tripId]);
    if ($team === null) {
        return false;
    }
    return (int) $team['leader_id'] === (int) $user['id'] || is_team_member((int) $team['id']);
}

function unread_notifications_count(): int
{
    $user = current_user();
    if ($user === null) {
        return 0;
    }
    return (int) db_val(
        'SELECT COUNT(*) FROM `notification` WHERE `user_id` = ? AND `is_read` = 0',
        [$user['id']]
    );
}

function unread_messages_count(): int
{
    $user = current_user();
    if ($user === null) {
        return 0;
    }
    return (int) db_val(
        'SELECT COUNT(*) FROM `message` WHERE `to_id` = ? AND `is_read` = 0',
        [$user['id']]
    );
}
