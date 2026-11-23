<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$stats = [
    'guides'    => (int) db_val('SELECT COUNT(*) FROM `guide` WHERE `status` = "published"'),
    'trips'     => (int) db_val('SELECT COUNT(*) FROM `trip`'),
    'teams'     => (int) db_val('SELECT COUNT(*) FROM `team` WHERE `status` = "recruiting"'),
    'resources' => (int) db_val('SELECT COUNT(*) FROM `resource` WHERE `status` = "published"'),
];

$guides = db_all(
    'SELECT * FROM `v_guide_card` WHERE `status` = "published" ORDER BY `created_at` DESC LIMIT 3'
);
$hotGuides = db_all(
    'SELECT * FROM `v_guide_card` WHERE `status` = "published" ORDER BY `like_count` DESC, `created_at` DESC LIMIT 4'
);
$teams = db_all(
    'SELECT * FROM `v_team_card` WHERE `status` IN ("recruiting", "full") ORDER BY `created_at` DESC LIMIT 4'
);
$resources = db_all(
    'SELECT r.*, u.`nickname` AS `uploader_nickname`
     FROM `resource` r
     JOIN `user` u ON u.`id` = r.`uploader_id`
     WHERE r.`status` = "published"
     ORDER BY r.`created_at` DESC LIMIT 4'
);

$pageTitle = '首页';
$activeNav = 'home';
require APP_ROOT . '/app/views/header.php';
?>
<section class="hero-card p-4 p-lg-5 mb-4">
    <div class="row align-items-center g-4">
        <div class="col-lg-7">
            <div class="text-uppercase small fw-semibold text-white-50 mb-2">Travel Together</div>
            <h1 class="display-6 fw-bold mb-3">把旅行前、旅行中、旅行后<br>都装进一个地方</h1>
            <p class="lead text-white-50 mb-4">收集攻略、组建队伍、云端备份路线、分享照片与故事。一次旅行，从计划到回忆，都有同伴。</p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-light btn-lg" href="<?= e(url('/guides.php')) ?>">找攻略</a>
                <a class="btn btn-outline-light btn-lg" href="<?= e(url('/trips.php')) ?>">创建行程</a>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="hero-panel p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="fw-semibold">旅行三阶段</span>
                    <span class="badge text-bg-light">一站式</span>
                </div>
                <div class="hero-step"><span>01</span><div><strong>行前</strong><div class="small text-white-50">攻略筛选 · 队伍组建</div></div></div>
                <div class="hero-step"><span>02</span><div><strong>行中</strong><div class="small text-white-50">路线版本 · 队伍管理</div></div></div>
                <div class="hero-step"><span>03</span><div><strong>行后</strong><div class="small text-white-50">资源分享 · 好友互动</div></div></div>
            </div>
        </div>
    </div>
</section>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card stat-card border-0 shadow-sm"><div class="card-body"><div class="stat-value"><?= $stats['guides'] ?></div><div class="text-secondary small">已发布攻略</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card border-0 shadow-sm"><div class="card-body"><div class="stat-value"><?= $stats['trips'] ?></div><div class="text-secondary small">旅行行程</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card border-0 shadow-sm"><div class="card-body"><div class="stat-value"><?= $stats['teams'] ?></div><div class="text-secondary small">招募中的队伍</div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card border-0 shadow-sm"><div class="card-body"><div class="stat-value"><?= $stats['resources'] ?></div><div class="text-secondary small">分享的资源</div></div></div></div>
</div>

<div class="d-flex justify-content-between align-items-end mb-3">
    <div>
        <h2 class="h4 mb-1">最新攻略</h2>
        <div class="text-secondary small">来自旅行者的真实路线与经验</div>
    </div>
    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/guides.php')) ?>">全部攻略</a>
</div>
<div class="row g-3 mb-4">
    <?php if (!$guides): ?>
        <div class="col-12"><div class="alert alert-light border">还没有攻略，登录后发布第一篇吧。</div></div>
    <?php endif; ?>
    <?php foreach ($guides as $g): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card guide-card h-100 border-0 shadow-sm">
                <a href="<?= e(url('/guide.php?id=' . $g['id'])) ?>">
                    <img src="<?= e(cover_url($g['cover'])) ?>" class="card-img-top guide-cover" alt="<?= e($g['title']) ?>">
                </a>
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge text-bg-light"><?= e($g['destination']) ?></span>
                        <span class="small text-secondary"><?= e($g['day_count']) ?> 天</span>
                    </div>
                    <h3 class="h6"><a class="stretched-link text-decoration-none text-body" href="<?= e(url('/guide.php?id=' . $g['id'])) ?>"><?= e($g['title']) ?></a></h3>
                    <p class="small text-secondary flex-grow-1"><?= e(excerpt($g['summary'], 55)) ?></p>
                    <div class="d-flex justify-content-between align-items-center small text-secondary">
                        <span><?= e($g['author_nickname']) ?></span>
                        <span>赞 <?= (int) $g['like_count'] ?> · 藏 <?= (int) $g['collect_count'] ?></span>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <h2 class="h4 mb-1">热门队伍</h2>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/teams.php')) ?>">全部队伍</a>
        </div>
        <div class="list-group shadow-sm">
            <?php if (!$teams): ?>
                <div class="list-group-item text-secondary">暂时没有招募中的队伍。</div>
            <?php endif; ?>
            <?php foreach ($teams as $t): ?>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-3" href="<?= e(url('/team.php?id=' . $t['id'])) ?>">
                    <div>
                        <div class="fw-semibold"><?= e($t['name']) ?> <?= status_html($t['status']) ?></div>
                        <div class="small text-secondary"><?= e($t['trip_title']) ?> · <?= e($t['destination']) ?> · <?= e(format_date($t['start_date'])) ?> 出发</div>
                    </div>
                    <div class="text-end small text-secondary">
                        <div><?= (int) $t['current_members'] ?>/<?= (int) $t['max_members'] ?> 人</div>
                        <div>队长 <?= e($t['leader_nickname']) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <h2 class="h4 mb-1">热门攻略</h2>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/guides.php?sort=hot')) ?>">更多</a>
        </div>
        <div class="list-group shadow-sm">
            <?php foreach ($hotGuides as $g): ?>
                <a class="list-group-item list-group-item-action" href="<?= e(url('/guide.php?id=' . $g['id'])) ?>">
                    <div class="d-flex justify-content-between gap-2">
                        <span class="fw-semibold"><?= e($g['title']) ?></span>
                        <span class="badge text-bg-danger align-self-start"><?= (int) $g['like_count'] ?> 赞</span>
                    </div>
                    <div class="small text-secondary mt-1"><?= e($g['destination']) ?> · <?= e($g['author_nickname']) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-end mt-4 mb-3">
    <h2 class="h4 mb-1">最新资源</h2>
    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/resources.php')) ?>">全部资源</a>
</div>
<div class="row g-3">
    <?php if (!$resources): ?>
        <div class="col-12"><div class="alert alert-light border">还没有资源，登录后上传第一张照片吧。</div></div>
    <?php endif; ?>
    <?php foreach ($resources as $r): ?>
        <?php $thumb = resource_thumb($r); ?>
        <div class="col-6 col-lg-3">
            <a class="card resource-card border-0 shadow-sm h-100 text-decoration-none" href="<?= e(url('/resource.php?id=' . $r['id'])) ?>">
                <?php if ($thumb !== ''): ?>
                    <img src="<?= e($thumb) ?>" class="card-img-top resource-cover" alt="<?= e($r['title']) ?>">
                <?php else: ?>
                    <div class="resource-cover-placeholder"><?= $r['type'] === 'video' ? '🎬' : '📄' ?></div>
                <?php endif; ?>
                <div class="card-body py-2">
                    <div class="small fw-semibold text-body text-truncate"><?= e($r['title']) ?></div>
                    <div class="small text-secondary"><?= e($r['uploader_nickname']) ?> · <?= e($r['type']) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
