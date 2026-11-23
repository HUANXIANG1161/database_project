<?php
/** 管理后台顶部导航，由 public/admin/*.php 在 header 之后引入。 */
$adminTabs = [
    'index.php'     => '概览',
    'users.php'     => '用户管理',
    'guides.php'    => '攻略审核',
    'resources.php' => '资源审核',
];
$currentAdminPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">管理后台</h1>
        <p class="text-secondary mb-0">课程设计精简后台：用户、攻略与资源审核。</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/index.php')) ?>">返回前台</a>
</div>
<ul class="nav nav-pills mb-4">
    <?php foreach ($adminTabs as $file => $label): ?>
        <li class="nav-item">
            <a class="nav-link<?= $currentAdminPage === $file ? ' active' : '' ?>" href="<?= e(url('/admin/' . $file)) ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>
