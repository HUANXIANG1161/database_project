<?php
/**
 * 公共函数：配置读取、输出转义、URL、闪存消息、CSRF、格式化、状态徽章。
 */
declare(strict_types=1);

/**
 * 读取 config.php 中的点号路径，例如 config('db.host')。
 */
function config(string $path, mixed $default = null): mixed
{
    $value = $GLOBALS['config'] ?? [];
    foreach (explode('.', $path) as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            return $default;
        }
        $value = $value[$key];
    }
    return $value;
}

/**
 * HTML 转义，所有输出到页面的用户内容都必须经过它。
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * 生成站内 URL；支持外部 http(s) 链接原样返回。
 */
function url(string $path = ''): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $base = rtrim((string) config('site.base_url', ''), '/');
    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/**
 * 只允许跳转到站内路径，防止 open redirect。
 */
function safe_path(string $path): string
{
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
        return '/index.php';
    }
    return $path;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * 读取 POST 字段并去除首尾空白；数组原样返回。
 */
function input(string $key, mixed $default = ''): mixed
{
    $value = $_POST[$key] ?? $default;
    if (is_string($value)) {
        return trim($value);
    }
    return $value;
}

/**
 * 读取 GET 字段并去除首尾空白。
 */
function query(string $key, mixed $default = ''): mixed
{
    $value = $_GET[$key] ?? $default;
    if (is_string($value)) {
        return trim($value);
    }
    return $value;
}

/* ---------------- 闪存消息 ---------------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * 取出并清空闪存消息。
 */
function flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/* ---------------- CSRF ---------------- */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/**
 * 所有 POST 请求统一校验 CSRF；失败直接终止。
 */
function verify_csrf(): void
{
    if (!is_post()) {
        return;
    }
    $token = $_POST['_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('表单已过期，请返回上一页刷新后重试。');
    }
}

/* ---------------- 格式化 ---------------- */

function format_dt(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i', $ts) : $value;
}

function format_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d', $ts) : $value;
}

function money(float|int|string|null $value): string
{
    return number_format((float) $value, 2);
}

function excerpt(?string $text, int $length = 90): string
{
    $plain = trim(strip_tags((string) $text));
    if (mb_strlen($plain, 'UTF-8') <= $length) {
        return $plain;
    }
    return mb_substr($plain, 0, $length, 'UTF-8') . '…';
}

function avatar_url(?string $avatar): string
{
    if ($avatar !== null && $avatar !== '') {
        return url($avatar);
    }
    return url('/assets/avatar.svg');
}

function cover_url(?string $cover): string
{
    if ($cover !== null && $cover !== '') {
        return url($cover);
    }
    return url('/assets/cover.svg');
}

/**
 * 资源缩略图：优先 cover；照片可用 file_url；视频/文档没有封面时返回空串，
 * 页面用类型图标占位，避免把 PDF/MP4 当图片塞进 <img> 导致破图。
 */
function resource_thumb(array $resource): string
{
    $cover = trim((string) ($resource['cover'] ?? ''));
    if ($cover !== '') {
        return cover_url($cover);
    }
    if (($resource['type'] ?? '') === 'photo' && !empty($resource['file_url'])) {
        return cover_url((string) $resource['file_url']);
    }
    return '';
}

/**
 * 状态徽章：返回 [Bootstrap 颜色, 中文文案]。
 */
function status_badge(string $status): array
{
    $map = [
        'draft'     => ['secondary', '草稿'],
        'pending'   => ['warning', '待审核'],
        'published' => ['success', '已发布'],
        'rejected'  => ['danger', '已驳回'],
        'hidden'    => ['dark', '已隐藏'],
        'planning'  => ['info', '规划中'],
        'ongoing'   => ['primary', '进行中'],
        'finished'  => ['secondary', '已结束'],
        'recruiting'=> ['success', '招募中'],
        'full'      => ['warning', '已满员'],
        'closed'    => ['secondary', '已关闭'],
        'approved'  => ['success', '已通过'],
        'quit'      => ['secondary', '已退出'],
        'removed'   => ['dark', '已移除'],
        'active'    => ['success', '正常'],
        'disabled'  => ['danger', '已禁用'],
        'accepted'  => ['success', '已接受'],
        'blocked'   => ['dark', '已拉黑'],
        'pending_friend' => ['warning', '待确认'],
    ];
    return $map[$status] ?? ['secondary', $status];
}

function status_html(string $status): string
{
    [$color, $label] = status_badge($status);
    return '<span class="badge text-bg-' . e($color) . '">' . e($label) . '</span>';
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

/**
 * 生成带当前查询参数的 URL，用于分页/筛选。
 */
function with_query(array $overrides = []): string
{
    $params = $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $qs = http_build_query($params);
    return $qs === '' ? $path : $path . '?' . $qs;
}

/* ---------------- 图片上传 ---------------- */

/**
 * 保存用户上传的图片到 public/uploads/<folder>/YYYY/mm/，返回可访问的 URL。
 *
 * - 没有选择文件时返回 null，且 $error 保持 null（调用方保留原值）；
 * - 文件太大、类型不支持、移动失败时返回 null，并把中文原因写入 $error；
 * - 仅允许 jpg/png/gif/webp，最大 5 MB；不接收 SVG，避免脚本注入。
 *
 * 用法：
 *   $error = null;
 *   $url = save_image_upload($_FILES['cover_file'] ?? [], 'covers/guides', $error);
 */
function save_image_upload(array $file, string $folder, ?string &$error = null): ?string
{
    $error = null;
    $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($code === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($code !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => '图片超过服务器 upload_max_filesize 限制。',
            UPLOAD_ERR_FORM_SIZE  => '图片超过表单允许的大小。',
            UPLOAD_ERR_PARTIAL    => '图片只上传了一部分，请重试。',
            UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录，无法接收图片。',
            UPLOAD_ERR_CANT_WRITE => '服务器无法写入临时文件。',
            UPLOAD_ERR_EXTENSION  => '上传被服务器扩展阻止。',
        ];
        $error = $messages[$code] ?? ('图片上传失败，错误码：' . $code);
        return null;
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        $error = '图片文件为空。';
        return null;
    }
    if ($size > 5 * 1024 * 1024) {
        $error = '图片不能超过 5 MB。';
        return null;
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $error = '没有收到有效的上传文件。';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $ext = $allowed[$mime] ?? null;
    if ($ext === null) {
        $error = '图片格式不支持，请选择 JPG、PNG、GIF 或 WebP。';
        return null;
    }
    if (@getimagesize($tmp) === false) {
        $error = '文件内容不是有效图片。';
        return null;
    }

    $folder = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $folder) ?: 'images';
    $relativeDir = trim($folder, '/') . '/' . date('Y/m');
    $baseDir = rtrim((string) config('site.upload_dir'), '/\\');
    $dir = $baseDir . '/' . $relativeDir;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        $error = '无法创建图片保存目录。';
        return null;
    }

    $name = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
        $error = '保存图片失败，请检查目录权限。';
        return null;
    }

    return rtrim((string) config('site.upload_url'), '/') . '/' . $relativeDir . '/' . $name;
}

/**
 * 删除由本站上传的图片；只处理 /uploads/(covers|avatars|resources)/ 下的文件，
 * 不会误删 demo 占位图或外部 URL。
 */
function delete_upload(?string $url): void
{
    if ($url === null || $url === '') {
        return;
    }
    $prefix = rtrim((string) config('site.upload_url'), '/') . '/';
    if (!str_starts_with($url, $prefix)) {
        return;
    }
    $relative = substr($url, strlen($prefix));
    if (!preg_match('#^(covers|avatars|resources)/#', $relative)) {
        return;
    }

    $base = realpath((string) config('site.upload_dir'));
    $path = realpath(rtrim((string) config('site.upload_dir'), '/\\') . '/' . $relative);
    if ($base !== false && $path !== false && str_starts_with($path, $base . DIRECTORY_SEPARATOR) && is_file($path)) {
        @unlink($path);
    }
}
