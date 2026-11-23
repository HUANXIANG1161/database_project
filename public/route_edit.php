<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_login();

$tripId = (int) query('trip_id');
$trip = db_one('SELECT * FROM `trip` WHERE `id` = ? LIMIT 1', [$tripId]);
if ($trip === null) {
    http_response_code(404);
    exit('行程不存在。');
}
if (!can_edit_route($tripId)) {
    http_response_code(403);
    exit('403：无权编辑该行程的路线。');
}

$route = db_one('SELECT * FROM `route` WHERE `trip_id` = ? LIMIT 1', [$tripId]);
$existing = [];
if ($route !== null) {
    $existing = db_all(
        'SELECT * FROM `route_item` WHERE `route_id` = ? AND `version_no` = ? ORDER BY `day_no`, `seq`',
        [(int) $route['id'], (int) $route['current_version']]
    );
}

$typeLabels = ['spot' => '景点', 'food' => '美食', 'hotel' => '住宿', 'traffic' => '交通', 'other' => '其他'];
$routeTitle = $route['title'] ?? '行程路线';
$formRows = [];
foreach ($existing as $item) {
    $formRows[] = [
        'day_no'    => (int) $item['day_no'],
        'seq'       => (int) $item['seq'],
        'item_type' => $item['item_type'],
        'title'     => $item['title'],
        'address'   => $item['address'] ?? '',
        'start_time'=> $item['start_time'] !== null ? substr((string) $item['start_time'], 0, 5) : '',
        'end_time'  => $item['end_time'] !== null ? substr((string) $item['end_time'], 0, 5) : '',
        'cost'      => $item['cost'],
        'remark'    => $item['remark'] ?? '',
    ];
}
for ($i = 0; $i < 3; $i++) {
    $formRows[] = ['day_no' => 1, 'seq' => count($formRows) + 1, 'item_type' => 'spot', 'title' => '', 'address' => '', 'start_time' => '', 'end_time' => '', 'cost' => '0.00', 'remark' => ''];
}
$errors = [];

if (is_post()) {
    verify_csrf();
    $routeTitle = (string) input('route_title', '行程路线');
    $remark = (string) input('version_remark');
    $days = (array) input('day_no', []);
    $seqs = (array) input('seq', []);
    $types = (array) input('item_type', []);
    $titles = (array) input('title', []);
    $addresses = (array) input('address', []);
    $starts = (array) input('start_time', []);
    $ends = (array) input('end_time', []);
    $costs = (array) input('cost', []);
    $remarks = (array) input('remark', []);
    $count = max(count($days), count($titles));
    $rows = [];
    $seen = [];
    for ($i = 0; $i < $count; $i++) {
        $title = trim((string) ($titles[$i] ?? ''));
        if ($title === '') {
            continue;
        }
        $dayNo = (int) ($days[$i] ?? 1);
        $seq = (int) ($seqs[$i] ?? ($i + 1));
        $type = (string) ($types[$i] ?? 'spot');
        $start = trim((string) ($starts[$i] ?? ''));
        $end = trim((string) ($ends[$i] ?? ''));
        $cost = (float) ($costs[$i] ?? 0);
        if ($dayNo < 1 || $dayNo > 60) {
            $errors[] = '第 ' . ($i + 1) . ' 行：天数应在 1–60 之间。';
        }
        if ($seq < 1 || $seq > 99) {
            $errors[] = '第 ' . ($i + 1) . ' 行：当天顺序应在 1–99 之间。';
        }
        if (!array_key_exists($type, $typeLabels)) {
            $errors[] = '第 ' . ($i + 1) . ' 行：类型不合法。';
        }
        if (mb_strlen($title, 'UTF-8') > 100) {
            $errors[] = '第 ' . ($i + 1) . ' 行：标题不能超过 100 字。';
        }
        if ($start !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start)) {
            $errors[] = '第 ' . ($i + 1) . ' 行：开始时间格式应为 HH:MM。';
        }
        if ($end !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) {
            $errors[] = '第 ' . ($i + 1) . ' 行：结束时间格式应为 HH:MM。';
        }
        // 同一条路线内，开始时间不能晚于结束时间（两项都填写且格式合法时才比较）。
        if (
            $start !== '' && $end !== '' &&
            preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) &&
            preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end) &&
            strcmp($start, $end) > 0
        ) {
            $errors[] = '第 ' . ($i + 1) . ' 行：开始时间不能晚于结束时间。';
        }
        if ($cost < 0) {
            $errors[] = '第 ' . ($i + 1) . ' 行：费用不能为负数。';
        }
        $key = $dayNo . ':' . $seq;
        if (isset($seen[$key])) {
            $errors[] = '第 ' . ($i + 1) . ' 行：第 ' . $dayNo . ' 天的顺序 ' . $seq . ' 重复。';
        }
        $seen[$key] = true;
        $rows[] = [
            'day_no' => $dayNo, 'seq' => $seq, 'item_type' => $type, 'title' => $title,
            'address' => trim((string) ($addresses[$i] ?? '')), 'start_time' => $start !== '' ? $start : null,
            'end_time' => $end !== '' ? $end : null, 'cost' => $cost, 'remark' => trim((string) ($remarks[$i] ?? '')),
        ];
    }
    if (!$rows && !$errors) {
        $errors[] = '至少填写一个路线条目。';
    }
    if (mb_strlen($routeTitle, 'UTF-8') > 100) {
        $errors[] = '路线标题不能超过 100 字。';
    }
    if (mb_strlen($remark, 'UTF-8') > 255) {
        $errors[] = '版本备注不能超过 255 字。';
    }
    $formRows = $rows ?: $formRows;

    if (!$errors) {
        try {
            db()->beginTransaction();
            if ($route === null) {
                db_exec(
                    'INSERT INTO `route` (`trip_id`, `title`, `current_version`, `updated_by`) VALUES (?, ?, 1, ?)',
                    [$tripId, $routeTitle, (int) current_user()['id']]
                );
                $routeId = db_last_id();
                $newVersion = 1;
            } else {
                $locked = db_one('SELECT `current_version` FROM `route` WHERE `id` = ? FOR UPDATE', [(int) $route['id']]);
                $newVersion = (int) $locked['current_version'] + 1;
                $routeId = (int) $route['id'];
                db_exec('UPDATE `route` SET `title` = ?, `updated_by` = ? WHERE `id` = ?', [$routeTitle, (int) current_user()['id'], $routeId]);
            }
            db_exec(
                'INSERT INTO `route_version` (`route_id`, `version_no`, `editor_id`, `remark`) VALUES (?, ?, ?, ?)',
                [$routeId, $newVersion, (int) current_user()['id'], $remark !== '' ? $remark : null]
            );
            foreach ($rows as $row) {
                db_exec(
                    'INSERT INTO `route_item` (`route_id`, `version_no`, `day_no`, `seq`, `item_type`, `title`, `address`, `start_time`, `end_time`, `cost`, `remark`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $routeId, $newVersion, $row['day_no'], $row['seq'], $row['item_type'], $row['title'],
                        $row['address'] !== '' ? $row['address'] : null,
                        $row['start_time'], $row['end_time'], $row['cost'],
                        $row['remark'] !== '' ? $row['remark'] : null,
                    ]
                );
            }
            db_exec('UPDATE `route` SET `current_version` = ? WHERE `id` = ?', [$newVersion, $routeId]);
            db()->commit();
            flash('success', '路线已保存为 v' . $newVersion . '，旧版本仍可在版本历史中查看。');
            redirect('/route.php?trip_id=' . $tripId);
        } catch (Throwable $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $errors[] = '保存失败：' . $ex->getMessage();
        }
    }
}

$pageTitle = '编辑路线';
$activeNav = 'trips';
require APP_ROOT . '/app/views/header.php';
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">编辑路线</h1>
        <p class="text-secondary mb-0"><?= e($trip['title']) ?> · 保存时会生成新版本，旧版本不会丢失。</p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('/route_history.php?trip_id=' . $tripId)) ?>">版本历史</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('/route.php?trip_id=' . $tripId)) ?>">返回路线</a>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <?= csrf_field() ?>
        <div class="row g-3 mb-3">
            <div class="col-md-8">
                <label class="form-label" for="route_title">路线标题</label>
                <input class="form-control" id="route_title" name="route_title" maxlength="100" value="<?= e($routeTitle) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="version_remark">版本备注</label>
                <input class="form-control" id="version_remark" name="version_remark" maxlength="255" placeholder="如：增加第二天行程">
            </div>
        </div>

        <div class="alert alert-light border small">按天填写，顺序号决定同一天内的先后。清空标题即删除该条；保存后会生成新版本。</div>

        <div class="table-responsive">
            <table class="table align-middle route-edit-table">
                <thead class="table-light">
                <tr><th style="width:70px">天</th><th style="width:70px">顺序</th><th style="width:110px">类型</th><th>标题</th><th>地址</th><th style="width:105px">开始</th><th style="width:105px">结束</th><th style="width:100px">费用</th><th>备注</th><th style="width:50px"></th></tr>
                </thead>
                <tbody id="route-rows">
                <?php foreach ($formRows as $i => $row): ?>
                    <tr class="route-edit-row">
                        <td><input class="form-control form-control-sm" type="number" name="day_no[]" min="1" max="60" value="<?= e($row['day_no']) ?>"></td>
                        <td><input class="form-control form-control-sm" type="number" name="seq[]" min="1" max="99" value="<?= e($row['seq']) ?>"></td>
                        <td>
                            <select class="form-select form-select-sm" name="item_type[]">
                                <?php foreach ($typeLabels as $key => $label): ?>
                                    <option value="<?= e($key) ?>"<?= selected($row['item_type'], $key) ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input class="form-control form-control-sm" name="title[]" maxlength="100" value="<?= e($row['title']) ?>" placeholder="如：鼓浪屿"></td>
                        <td><input class="form-control form-control-sm" name="address[]" maxlength="255" value="<?= e($row['address']) ?>"></td>
                        <td><input class="form-control form-control-sm" type="time" name="start_time[]" value="<?= e($row['start_time']) ?>"></td>
                        <td><input class="form-control form-control-sm" type="time" name="end_time[]" value="<?= e($row['end_time']) ?>"></td>
                        <td><input class="form-control form-control-sm" type="number" name="cost[]" min="0" step="0.01" value="<?= e($row['cost']) ?>"></td>
                        <td><input class="form-control form-control-sm" name="remark[]" maxlength="500" value="<?= e($row['remark']) ?>"></td>
                        <td><button class="btn btn-sm btn-outline-danger" type="button" data-remove-route-row aria-label="删除行">×</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button class="btn btn-sm btn-outline-primary" type="button" id="add-route-row">+ 添加一行</button>
    </div>
    <div class="card-footer bg-white p-3 d-flex justify-content-end gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('/route.php?trip_id=' . $tripId)) ?>">取消</a>
        <button class="btn btn-primary" type="submit">保存为新版本</button>
    </div>
</form>

<template id="route-row-template">
    <tr class="route-edit-row">
        <td><input class="form-control form-control-sm" type="number" name="day_no[]" min="1" max="60" value="1"></td>
        <td><input class="form-control form-control-sm" type="number" name="seq[]" min="1" max="99" value="1"></td>
        <td>
            <select class="form-select form-select-sm" name="item_type[]">
                <?php foreach ($typeLabels as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </td>
        <td><input class="form-control form-control-sm" name="title[]" maxlength="100" value=""></td>
        <td><input class="form-control form-control-sm" name="address[]" maxlength="255" value=""></td>
        <td><input class="form-control form-control-sm" type="time" name="start_time[]" value=""></td>
        <td><input class="form-control form-control-sm" type="time" name="end_time[]" value=""></td>
        <td><input class="form-control form-control-sm" type="number" name="cost[]" min="0" step="0.01" value="0.00"></td>
        <td><input class="form-control form-control-sm" name="remark[]" maxlength="500" value=""></td>
        <td><button class="btn btn-sm btn-outline-danger" type="button" data-remove-route-row aria-label="删除行">×</button></td>
    </tr>
</template>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
