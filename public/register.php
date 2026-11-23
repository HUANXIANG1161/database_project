<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

if (current_user() !== null) {
    redirect('/index.php');
}

$error = '';
$username = '';
$nickname = '';
$email = '';

if (is_post()) {
    verify_csrf();
    $username = (string) input('username');
    $nickname = (string) input('nickname');
    $email = (string) input('email');
    $password = (string) ($_POST['password'] ?? '');
    $password2 = (string) ($_POST['password_confirm'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $error = '用户名需为 3–50 位字母、数字或下划线。';
    } elseif ($nickname === '' || mb_strlen($nickname, 'UTF-8') > 50) {
        $error = '昵称不能为空，且不超过 50 个字。';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '邮箱格式不正确。';
    } elseif (strlen($password) < 6 || strlen($password) > 72) {
        $error = '密码长度需为 6–72 位。';
    } elseif ($password !== $password2) {
        $error = '两次输入的密码不一致。';
    } elseif ((int) db_val('SELECT COUNT(*) FROM `user` WHERE `username` = ?', [$username]) > 0) {
        $error = '用户名已被占用。';
    } elseif ($email !== '' && (int) db_val('SELECT COUNT(*) FROM `user` WHERE `email` = ?', [$email]) > 0) {
        $error = '邮箱已被注册。';
    } else {
        db_exec(
            'INSERT INTO `user` (`username`, `password_hash`, `nickname`, `email`, `role`, `status`)
             VALUES (?, ?, ?, ?, "user", "active")',
            [$username, password_hash($password, PASSWORD_DEFAULT), $nickname, $email !== '' ? $email : null]
        );
        flash('success', '注册成功，请登录。');
        redirect('/login.php');
    }
}

$pageTitle = '注册';
require APP_ROOT . '/app/views/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-lg-5">
                <h1 class="h4 mb-1">注册新账号</h1>
                <p class="text-secondary mb-4">注册后即可发布攻略、创建行程和组队。</p>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/register.php')) ?>">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="username">用户名</label>
                            <input class="form-control" id="username" name="username" value="<?= e($username) ?>" required>
                            <div class="form-text">3–50 位字母、数字或下划线，登录用。</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="nickname">昵称</label>
                            <input class="form-control" id="nickname" name="nickname" value="<?= e($nickname) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="email">邮箱（可选）</label>
                            <input class="form-control" id="email" name="email" type="email" value="<?= e($email) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password">密码</label>
                            <input class="form-control" id="password" name="password" type="password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password_confirm">确认密码</label>
                            <input class="form-control" id="password_confirm" name="password_confirm" type="password" required>
                        </div>
                    </div>
                    <button class="btn btn-primary w-100 mt-4" type="submit">注册</button>
                </form>

                <p class="text-center text-secondary mt-4 mb-0">
                    已有账号？<a href="<?= e(url('/login.php')) ?>">去登录</a>
                </p>
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
