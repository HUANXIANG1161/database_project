<?php
/** 公共页头：导航、搜索、未读角标、闪存消息。页面需先 require bootstrap.php。 */
$me = current_user();
$notifCount = unread_notifications_count();
$msgCount = unread_messages_count();
$pageTitle = $pageTitle ?? config('site.name');
$activeNav = $activeNav ?? '';
$q = (string) ($_GET['q'] ?? '');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(config('site.name')) ?></title>
    <link rel="stylesheet" href="<?= e(url('/assets/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/style.css')) ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-body-tertiary">
<nav class="navbar navbar-expand-lg navbar-dark app-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url('/index.php')) ?>">
            <span class="brand-mark"></span> 旅行信息分享
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="切换导航">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link <?= $activeNav === 'home' ? 'active' : '' ?>" href="<?= e(url('/index.php')) ?>">首页</a></li>
                <li class="nav-item"><a class="nav-link <?= $activeNav === 'guides' ? 'active' : '' ?>" href="<?= e(url('/guides.php')) ?>">攻略</a></li>
                <li class="nav-item"><a class="nav-link <?= $activeNav === 'trips' ? 'active' : '' ?>" href="<?= e(url('/trips.php')) ?>">行程</a></li>
                <li class="nav-item"><a class="nav-link <?= $activeNav === 'teams' ? 'active' : '' ?>" href="<?= e(url('/teams.php')) ?>">队伍</a></li>
                <li class="nav-item"><a class="nav-link <?= $activeNav === 'resources' ? 'active' : '' ?>" href="<?= e(url('/resources.php')) ?>">资源</a></li>
                <?php if ($me !== null): ?>
                    <li class="nav-item"><a class="nav-link <?= $activeNav === 'friends' ? 'active' : '' ?>" href="<?= e(url('/friends.php')) ?>">好友</a></li>
                <?php endif; ?>
            </ul>
            <form class="d-flex me-lg-3 my-2 my-lg-0" role="search" action="<?= e(url('/guides.php')) ?>" method="get">
                <input class="form-control form-control-sm me-2" type="search" name="q" value="<?= e($q) ?>" placeholder="搜攻略 / 目的地" aria-label="搜索攻略">
                <button class="btn btn-sm btn-light" type="submit">搜索</button>
            </form>
            <ul class="navbar-nav align-items-lg-center">
                <?php if ($me !== null): ?>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="<?= e(url('/notifications.php')) ?>" title="通知">
                            通知
                            <?php if ($notifCount > 0): ?><span class="badge rounded-pill text-bg-danger nav-badge"><?= $notifCount ?></span><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="<?= e(url('/messages.php')) ?>" title="私聊">
                            私聊
                            <?php if ($msgCount > 0): ?><span class="badge rounded-pill text-bg-warning nav-badge"><?= $msgCount ?></span><?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?= e(avatar_url($me['avatar'])) ?>" alt="" class="rounded-circle nav-avatar">
                            <span><?= e($me['nickname']) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= e(url('/profile.php')) ?>">个人资料</a></li>
                            <li><a class="dropdown-item" href="<?= e(url('/guide_my.php')) ?>">我的攻略</a></li>
                            <li><a class="dropdown-item" href="<?= e(url('/trip_my.php')) ?>">我的行程</a></li>
                            <?php if (is_admin()): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= e(url('/admin/index.php')) ?>">管理后台</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= e(url('/logout.php')) ?>">退出登录</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('/login.php')) ?>">登录</a></li>
                    <li class="nav-item"><a class="btn btn-sm btn-light ms-lg-2" href="<?= e(url('/register.php')) ?>">注册</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4 flex-grow-1">
    <?php foreach (flashes() as $item): ?>
        <div class="alert alert-<?= e($item['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($item['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="关闭"></button>
        </div>
    <?php endforeach; ?>
