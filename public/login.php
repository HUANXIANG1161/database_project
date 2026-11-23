<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

if (current_user() !== null) {
    redirect('/index.php');
}

$error = '';
$login = '';
$next = safe_path((string) ($_GET['next'] ?? $_POST['next'] ?? '/index.php'));

if (is_post()) {
    verify_csrf();
    $login = (string) input('login');
    $password = (string) ($_POST['password'] ?? '');
    $result = login_user($login, $password);
    if ($result['ok']) {
        flash('success', '欢迎回来，' . (current_user()['nickname'] ?? ''));
        redirect($next);
    }
    $error = $result['message'];
}

$pageTitle = '登录';
$activeNav = '';
require APP_ROOT . '/app/views/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-lg-5">
                <h1 class="h4 mb-1">登录</h1>
                <p class="text-secondary mb-4">继续记录和分享你的旅行。</p>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('/login.php')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="next" value="<?= e($next) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="login">用户名或邮箱</label>
                        <input class="form-control" id="login" name="login" value="<?= e($login) ?>" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password">密码</label>
                        <input class="form-control" id="password" name="password" type="password" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">登录</button>
                </form>

                <p class="text-center text-secondary mt-4 mb-0">
                    还没有账号？<a href="<?= e(url('/register.php')) ?>">立即注册</a>
                </p>
            </div>
        </div>

        <div class="card border-0 bg-body-tertiary mt-3">
            <div class="card-body small text-secondary">
                <div class="fw-semibold text-body mb-1">演示账号（密码均为 123456）</div>
                alice / bob / carol / dave / admin
            </div>
        </div>
    </div>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
