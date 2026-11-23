<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$q = (string) query('q');
$destination = (string) query('destination');
$season = (string) query('season');
$tag = (string) query('tag');
$sort = (string) query('sort', 'new');
if (!in_array($sort, ['new', 'hot', 'collect', 'comment'], true)) {
    $sort = 'new';
}
$page = max(1, (int) query('page', 1));
$perPage = max(1, (int) config('site.per_page', 9));
$offset = ($page - 1) * $perPage;

$tags = db_all('SELECT * FROM `tag` ORDER BY `type`, `name`');
$guides = [];
$error = '';
try {
    $stmt = db()->prepare('CALL `sp_search_guide`(?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $q !== '' ? $q : null,
        $destination !== '' ? $destination : null,
        $season !== '' ? $season : null,
        $tag !== '' ? $tag : null,
        $sort,
        $perPage,
        $offset,
    ]);
    $guides = $stmt->fetchAll();
    $stmt->closeCursor();
} catch (Throwable $ex) {
    $error = '查询失败：' . $ex->getMessage();
}
$hasNext = count($guides) >= $perPage;

$pageTitle = '攻略';
$activeNav = 'guides';
require APP_ROOT . '/app/views/header.php';
?>
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">旅行攻略</h1>
        <p class="text-secondary mb-0">按目的地、季节和标签筛选，找到适合你的路线。</p>
    </div>
    <?php if (current_user() !== null): ?>
        <a class="btn btn-primary" href="<?= e(url('/guide_edit.php')) ?>">发布攻略</a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get" action="<?= e(url('/guides.php')) ?>">
            <div class="col-md-4">
                <label class="form-label small text-secondary" for="q">关键词</label>
                <input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="标题 / 摘要 / 目的地">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-secondary" for="destination">目的地</label>
                <input class="form-control" id="destination" name="destination" value="<?= e($destination) ?>" placeholder="如：厦门">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-secondary" for="season">季节</label>
                <select class="form-select" id="season" name="season">
                    <option value="">全部</option>
                    <option value="spring"<?= selected($season, 'spring') ?>>春季</option>
                    <option value="summer"<?= selected($season, 'summer') ?>>夏季</option>
                    <option value="autumn"<?= selected($season, 'autumn') ?>>秋季</option>
                    <option value="winter"<?= selected($season, 'winter') ?>>冬季</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-secondary" for="tag">标签</label>
                <select class="form-select" id="tag" name="tag">
                    <option value="">全部</option>
                    <?php foreach ($tags as $t): ?>
                        <option value="<?= e($t['name']) ?>"<?= selected($tag, $t['name']) ?>><?= e($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-secondary" for="sort">排序</label>
                <select class="form-select" id="sort" name="sort">
                    <option value="new"<?= selected($sort, 'new') ?>>最新发布</option>
                    <option value="hot"<?= selected($sort, 'hot') ?>>最多点赞</option>
                    <option value="collect"<?= selected($sort, 'collect') ?>>最多收藏</option>
                    <option value="comment"<?= selected($sort, 'comment') ?>>最多评论</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit">筛选</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('/guides.php')) ?>">重置</a>
            </div>
        </form>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="row g-3">
    <?php if (!$guides): ?>
        <div class="col-12"><div class="alert alert-light border">没有找到符合条件的攻略，换个关键词试试。</div></div>
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
                        <span class="small text-secondary"><?= (int) $g['day_count'] ?> 天 · 人均 ¥<?= money($g['cost_avg']) ?></span>
                    </div>
                    <h2 class="h6"><a class="stretched-link text-decoration-none text-body" href="<?= e(url('/guide.php?id=' . $g['id'])) ?>"><?= e($g['title']) ?></a></h2>
                    <p class="small text-secondary flex-grow-1"><?= e(excerpt($g['summary'], 60)) ?></p>
                    <?php if (!empty($g['tags'])): ?>
                        <div class="mb-2">
                            <?php foreach (explode(',', (string) $g['tags']) as $tagName): ?>
                                <span class="badge rounded-pill text-bg-light border me-1"><?= e($tagName) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between align-items-center small text-secondary">
                        <span><?= e($g['author_nickname']) ?> · <?= e(format_date($g['created_at'])) ?></span>
                        <span>赞 <?= (int) $g['like_count'] ?> · 藏 <?= (int) $g['collect_count'] ?></span>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($page > 1 || $hasNext): ?>
    <nav class="mt-4" aria-label="攻略分页">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e(with_query(['page' => max(1, $page - 1)])) ?>">上一页</a>
            </li>
            <li class="page-item disabled"><span class="page-link">第 <?= $page ?> 页</span></li>
            <li class="page-item <?= !$hasNext ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e(with_query(['page' => $page + 1])) ?>">下一页</a>
            </li>
        </ul>
    </nav>
<?php endif; ?>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
