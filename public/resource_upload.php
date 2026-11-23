<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require_login();

$me = current_user();
$uid = (int) $me['id'];
$trips = db_all(
    'SELECT t.`id`, t.`title`, t.`destination`
     FROM `trip` t
     WHERE t.`owner_id` = ?
        OR EXISTS (
            SELECT 1 FROM `team` tm
            JOIN `team_member` tmm ON tmm.`team_id` = tm.`id`
            WHERE tm.`trip_id` = t.`id` AND tmm.`user_id` = ? AND tmm.`status` = "approved"
        )
     ORDER BY t.`start_date` DESC, t.`id` DESC',
    [$uid, $uid]
);
$tripIds = array_map(static fn(array $trip): int => (int) $trip['id'], $trips);

$typeLabels = ['photo' => '照片', 'video' => '视频', 'doc' => '文档'];
$errors = [];
$old = ['type' => 'photo', 'title' => '', 'description' => '', 'location' => '', 'trip_id' => 0, 'cover' => ''];

if (is_post()) {
    verify_csrf();
    $old['type'] = (string) input('type', 'photo');
    $old['title'] = trim((string) input('title'));
    $old['description'] = trim((string) input('description'));
    $old['location'] = trim((string) input('location'));
    $old['trip_id'] = (int) input('trip_id', '0');
    $old['cover'] = trim((string) input('cover'));

    if (!array_key_exists($old['type'], $typeLabels)) {
        $errors[] = '资源类型不合法。';
    }
    if ($old['title'] === '' || mb_strlen($old['title'], 'UTF-8') > 100) {
        $errors[] = '标题必填，且不能超过 100 字。';
    }
    if (mb_strlen($old['description'], 'UTF-8') > 500) {
        $errors[] = '描述不能超过 500 字。';
    }
    if (mb_strlen($old['location'], 'UTF-8') > 100) {
        $errors[] = '地点不能超过 100 字。';
    }
    if ($old['trip_id'] > 0 && !in_array($old['trip_id'], $tripIds, true)) {
        $errors[] = '只能关联自己发起或已加入队伍的行程。';
    }
    if ($old['cover'] !== '' && !preg_match('#^(https?://|/)#i', $old['cover'])) {
        $errors[] = '封面地址必须以 / 或 http(s):// 开头。';
    }

    $file = $_FILES['file'] ?? null;
    $fileUrl = '';
    $coverFileUrl = null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = '请选择要上传的文件。';
    } elseif ((int) $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = '文件上传失败，错误码：' . (int) $file['error'];
    } elseif ((int) $file['size'] > 20 * 1024 * 1024) {
        $errors[] = '文件不能超过 20 MB。';
    } else {
        $allowed = [
            'photo' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'],
            'video' => ['video/mp4' => 'mp4', 'video/webm' => 'webm'],
            'doc'   => ['application/pdf' => 'pdf'],
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file((string) $file['tmp_name']);
        $ext = $allowed[$old['type']][$mime] ?? null;
        if ($ext === null) {
            $errors[] = '文件类型与所选资源类型不匹配（图片 jpg/png/gif/webp、视频 mp4/webm、文档 pdf）。';
        } else {
            $dir = rtrim((string) config('site.upload_dir'), '/\\') . '/resources/' . date('Y/m');
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                $errors[] = '无法创建上传目录。';
            } else {
                $name = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
                    $errors[] = '保存上传文件失败。';
                } else {
                    $fileUrl = rtrim((string) config('site.upload_url'), '/') . '/resources/' . date('Y/m') . '/' . $name;
                }
            }
        }
    }

    // 视频/文档可以额外选择一张封面图；照片直接使用上传的照片。
    if (!$errors && $old['type'] !== 'photo') {
        $coverError = null;
        $coverFileUrl = save_image_upload($_FILES['cover_file'] ?? [], 'covers/resources', $coverError);
        if ($coverError !== null) {
            $errors[] = $coverError;
        }
    }

    if (!$errors) {
        $cover = $old['type'] === 'photo' ? $fileUrl : ($coverFileUrl !== null ? $coverFileUrl : ($old['cover'] !== '' ? $old['cover'] : null));
        try {
            db_exec(
                'INSERT INTO `resource` (`uploader_id`, `trip_id`, `type`, `title`, `description`, `file_url`, `cover`, `location`, `status`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, "published")',
                [
                    $uid,
                    $old['trip_id'] > 0 ? $old['trip_id'] : null,
                    $old['type'],
                    $old['title'],
                    $old['description'] !== '' ? $old['description'] : null,
                    $fileUrl,
                    $cover,
                    $old['location'] !== '' ? $old['location'] : null,
                ]
            );
            $newId = db_last_id();
            flash('success', '资源上传成功。');
            redirect('/resource.php?id=' . $newId);
        } catch (Throwable $ex) {
            if ($fileUrl !== '') {
                $absolute = rtrim((string) config('site.upload_dir'), '/\\') . '/' . ltrim(str_replace((string) config('site.upload_url'), '', $fileUrl), '/');
                if (is_file($absolute)) {
                    @unlink($absolute);
                }
            }
            if ($coverFileUrl !== null) {
                delete_upload($coverFileUrl);
            }
            $errors[] = '保存资源失败：' . $ex->getMessage();
        }
    }
}

$pageTitle = '上传旅行资源';
$activeNav = 'resources';
require APP_ROOT . '/app/views/header.php';
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">上传旅行资源</h1>
        <p class="text-secondary mb-0">支持照片、视频和 PDF 文档，单个文件不超过 20 MB。</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('/resources.php')) ?>">返回资源列表</a>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="type">资源类型</label>
                <select class="form-select" id="type" name="type">
                    <?php foreach ($typeLabels as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($old['type'], $key) ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label" for="title">标题 <span class="text-danger">*</span></label>
                <input class="form-control" id="title" name="title" maxlength="100" required value="<?= e($old['title']) ?>" placeholder="如：鼓浪屿日落照片">
            </div>
            <div class="col-12">
                <label class="form-label" for="description">描述</label>
                <textarea class="form-control" id="description" name="description" rows="3" maxlength="500" placeholder="拍摄时间、地点、行程感受等"><?= e($old['description']) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="location">地点</label>
                <input class="form-control" id="location" name="location" maxlength="100" value="<?= e($old['location']) ?>" placeholder="如：厦门鼓浪屿">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="trip_id">关联行程（可选）</label>
                <select class="form-select" id="trip_id" name="trip_id">
                    <option value="0">不关联</option>
                    <?php foreach ($trips as $trip): ?>
                        <option value="<?= (int) $trip['id'] ?>"<?= selected((string) $old['trip_id'], (string) $trip['id']) ?>><?= e($trip['title']) ?>（<?= e($trip['destination']) ?>）</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-7">
                <label class="form-label" for="file">文件 <span class="text-danger">*</span></label>
                <input class="form-control" id="file" name="file" type="file" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,application/pdf" required>
                <div class="form-text">图片会直接作为封面；视频/文档可另选封面图。</div>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="cover_file">封面图片（视频/文档可选）</label>
                <input class="form-control" id="cover_file" name="cover_file" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
                <div class="form-text">JPG / PNG / GIF / WebP，最大 5 MB。</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="cover">封面地址（可选，上传的封面优先）</label>
                <input class="form-control" id="cover" name="cover" maxlength="500" value="<?= e($old['cover']) ?>" placeholder="/uploads/... 或 https://...">
                <div class="form-text">不选择封面文件时，可填写已有图片地址；照片类型忽略此项。</div>
            </div>
        </div>
    </div>
    <div class="card-footer bg-white p-3 d-flex justify-content-end gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(url('/resources.php')) ?>">取消</a>
        <button class="btn btn-primary" type="submit">上传并发布</button>
    </div>
</form>
<?php require APP_ROOT . '/app/views/footer.php'; ?>
