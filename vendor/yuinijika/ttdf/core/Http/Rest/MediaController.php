<?php

declare(strict_types=1);

namespace TTDF\Http\Rest;

use Throwable;
use Typecho\Db;
use Typecho\Db\Query;
use TTDF\Compat\Upload;
use TTDF\Http\Request;
use TTDF\Http\Response;
use TTDF\Support\Json;

/**
 * Image media library for theme settings.
 *
 * The library uses Typecho's native attachment records and upload handler.
 * Only image attachments are exposed, uploads are MIME-checked, and deletion
 * is restricted to the Typecho upload directory.
 */
final class MediaController
{
    public const MAX_UPLOAD_BYTES = 5_242_880;
    public const DEFAULT_PAGE_SIZE = 40;
    public const MAX_PAGE_SIZE = 60;
    public const MAX_TITLE_LENGTH = 120;

    private const IMAGE_TYPES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'bmp' => ['image/bmp'],
    ];

    private readonly Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? Db::get();
    }

    public function index(): never
    {
        $page = max(1, Request::queryInt('page', 1));
        $pageSize = min(
            self::MAX_PAGE_SIZE,
            max(1, Request::queryInt('pageSize', self::DEFAULT_PAGE_SIZE))
        );
        $search = trim(Request::queryString('search'));

        [$imageCondition, $imageParams] = self::imageCondition();
        $applyFilters = static function (Query $query) use ($search, $imageCondition, $imageParams): Query {
            $query->where('table.contents.type = ?', 'attachment')
                ->where('table.contents.status = ?', 'publish')
                ->where($imageCondition, ...$imageParams);

            if ($search !== '') {
                $query->where('table.contents.title LIKE ?', '%' . $search . '%');
            }

            return $query;
        };

        try {
            $countRow = $this->db->fetchRow(
                $applyFilters(
                    $this->db->select('COUNT(*) AS num')->from('table.contents')
                )
            );
            $total = max(0, (int) ($countRow['num'] ?? 0));
            $lastPage = max(1, (int) ceil($total / $pageSize));
            $page = min($page, $lastPage);

            $rows = $this->db->fetchAll(
                $applyFilters(
                    $this->db->select('cid', 'title', 'text', 'created', 'modified', 'authorId')
                        ->from('table.contents')
                )
                    ->order('table.contents.created', Db::SORT_DESC)
                    ->offset(($page - 1) * $pageSize)
                    ->limit($pageSize)
            );
        } catch (Throwable) {
            Response::error('媒体库读取失败', 500);
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = self::itemFromRow($row);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        Response::success([
            'items' => $items,
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'lastPage' => $lastPage,
        ]);
    }

    public function show(int $id): never
    {
        $item = self::itemFromRow($this->findRow($id) ?? []);
        if ($item === null) {
            Response::error('媒体文件不存在', 404);
        }

        Response::success($item);
    }

    public function upload(): never
    {
        $file = Request::file('file');
        if ($file === null) {
            $declared = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            if ($declared > self::MAX_UPLOAD_BYTES) {
                Response::error('图片不能超过 5 MB', 413);
            }
            Response::error('请选择要上传的图片', 422);
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            Response::error('图片不能超过 5 MB', 413);
        }
        if ($error !== UPLOAD_ERR_OK) {
            Response::error('图片上传失败（错误码 ' . $error . '）', 422);
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $name = (string) ($file['name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || $name === '' || !is_uploaded_file($tmp)) {
            Response::error('上传文件无效', 400);
        }
        if ($size <= 0) {
            $size = (int) @filesize($tmp);
        }
        if ($size > self::MAX_UPLOAD_BYTES) {
            Response::error('图片不能超过 5 MB', 413);
        }

        $image = @getimagesize($tmp);
        $extension = self::imageExtension($name);
        if (!is_array($image) || $extension === '' || !isset(self::IMAGE_TYPES[$extension])) {
            Response::error('仅支持 JPG、PNG、GIF、WebP 与 BMP 图片', 422);
        }

        $mime = strtolower($image['mime']);
        if (!in_array($mime, self::IMAGE_TYPES[$extension], true)) {
            Response::error('图片内容与扩展名不一致', 422);
        }

        if (!class_exists('Widget\\Upload')) {
            Response::error('Typecho 上传组件不可用', 500);
        }

        $uploaded = \Widget\Upload::uploadHandle($file);
        if (!is_array($uploaded) || !isset($uploaded['path'], $uploaded['name'])) {
            Response::error('Typecho 未允许上传该图片类型，请在 Typecho 设置中启用图片文件', 422);
        }

        $path = (string) $uploaded['path'];
        if (!Upload::isUploadPath($path)) {
            Upload::delete($path);
            Response::error('上传路径不合法', 500);
        }

        $title = self::normalizeTitle($uploaded['name'] !== '' ? (string) $uploaded['name'] : $name);
        $now = time();
        $rows = [
            'title' => htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'slug' => 'media-' . bin2hex(random_bytes(8)),
            'created' => $now,
            'modified' => $now,
            'text' => Json::encode($uploaded),
            'order' => 0,
            'authorId' => self::currentUserId(),
            'template' => null,
            'type' => 'attachment',
            'status' => 'publish',
            'password' => null,
            'commentsNum' => 0,
            'allowComment' => 1,
            'allowPing' => 0,
            'allowFeed' => 1,
            'parent' => 0,
        ];

        try {
            $cid = (int) $this->db->query(
                $this->db->insert('table.contents')->rows($rows)
            );
        } catch (Throwable) {
            Upload::delete($path);
            Response::error('媒体记录写入失败', 500);
        }

        if ($cid <= 0) {
            Upload::delete($path);
            Response::error('媒体记录写入失败', 500);
        }

        $item = self::itemFromRow([
            'cid' => $cid,
            'title' => $rows['title'],
            'text' => $rows['text'],
            'created' => $now,
            'modified' => $now,
            'authorId' => $rows['authorId'],
        ]);

        if ($item === null) {
            try {
                @$this->db->query(
                    $this->db->delete('table.contents')
                        ->where('cid = ?', $cid)
                        ->where('type = ?', 'attachment')
                );
            } catch (Throwable) {
                // The upload is still unusable; the guarded file delete below
                // is the important cleanup step.
            }
            Upload::delete($path);
            Response::error('上传完成但无法生成媒体信息', 500);
        }

        Response::success($item, [], 201);
    }

    public function rename(int $id): never
    {
        if ($id <= 0) {
            Response::error('媒体文件不存在', 404);
        }

        $row = $this->findRow($id);
        if ($row === null || self::itemFromRow($row) === null) {
            Response::error('媒体文件不存在', 404);
        }

        $body = Request::json();
        $name = self::normalizeTitle(is_scalar($body['name'] ?? null) ? (string) $body['name'] : '');
        if ($name === '') {
            Response::error('媒体名称不能为空', 422);
        }

        try {
            $this->db->query(
                $this->db->update('table.contents')
                    ->rows([
                        'title' => htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                        'modified' => time(),
                    ])
                    ->where('cid = ?', $id)
                    ->where('type = ?', 'attachment')
            );
        } catch (Throwable) {
            Response::error('媒体重命名失败', 500);
        }

        $item = self::itemFromRow($this->findRow($id) ?? []);
        if ($item === null) {
            Response::error('媒体文件不存在', 404);
        }

        Response::success($item);
    }

    public function delete(int $id): never
    {
        if ($id <= 0) {
            Response::error('媒体文件不存在', 404);
        }

        $row = $this->findRow($id);
        if ($row === null || self::itemFromRow($row) === null) {
            Response::error('媒体文件不存在', 404);
        }

        $metadata = Attachment::metadata((string) ($row['text'] ?? ''));
        if ($metadata['path'] !== '') {
            Upload::delete($metadata['path']);
        }

        try {
            @$this->db->query(
                $this->db->delete('table.comments')->where('cid = ?', $id)
            );
            $deleted = (int) $this->db->query(
                $this->db->delete('table.contents')
                    ->where('cid = ?', $id)
                    ->where('type = ?', 'attachment')
            );
        } catch (Throwable) {
            Response::error('媒体删除失败', 500);
        }

        if ($deleted <= 0) {
            Response::error('媒体记录删除失败', 500);
        }

        Response::success(['deleted' => true, 'id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    public static function itemFromRow(array $row): ?array
    {
        $cid = (int) ($row['cid'] ?? 0);
        if ($cid <= 0) {
            return null;
        }

        $text = (string) ($row['text'] ?? '');
        $metadata = Attachment::metadata($text);
        if (!$metadata['isImage'] || $metadata['path'] === '') {
            return null;
        }

        $url = Attachment::url($text);
        if ($url === '') {
            return null;
        }

        $title = htmlspecialchars_decode((string) ($row['title'] ?? ''), ENT_QUOTES);
        if ($title === '') {
            $title = $metadata['name'];
        }

        return [
            'id' => $cid,
            'title' => $title,
            'name' => $metadata['name'],
            'url' => $url,
            'path' => $metadata['path'],
            'size' => $metadata['size'],
            'type' => $metadata['type'],
            'mime' => $metadata['mime'],
            'created' => (int) ($row['created'] ?? 0),
            'modified' => (int) ($row['modified'] ?? 0),
            'authorId' => (int) ($row['authorId'] ?? 0),
        ];
    }

    public static function normalizeTitle(string $name): string
    {
        $name = strip_tags(trim($name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name);
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if ($name === '') {
            return '';
        }

        return function_exists('mb_substr')
            ? mb_substr($name, 0, self::MAX_TITLE_LENGTH, 'UTF-8')
            : substr($name, 0, self::MAX_TITLE_LENGTH);
    }

    public static function imageExtension(string $name): string
    {
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        return array_key_exists($extension, self::IMAGE_TYPES) ? $extension : '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRow(int $id): ?array
    {
        try {
            $row = $this->db->fetchRow(
                $this->db->select('cid', 'title', 'text', 'created', 'modified', 'authorId')
                    ->from('table.contents')
                    ->where('table.contents.cid = ?', $id)
                    ->where('table.contents.type = ?', 'attachment')
                    ->limit(1)
            );
        } catch (Throwable) {
            return null;
        }

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private static function imageCondition(): array
    {
        $directory = Upload::directory();
        $prefixes = [$directory . '/', '/' . $directory . '/'];

        $mimeConditions = [
            'table.contents.text LIKE ?',
            'table.contents.text LIKE ?',
        ];
        $mimeParams = ['%"mime":"image/%', '%"mime": "image/%'];

        $typeConditions = [];
        $typeParams = [];

        foreach (array_keys(self::IMAGE_TYPES) as $extension) {
            $typeConditions[] = 'table.contents.text LIKE ?';
            $typeParams[] = '%"type":"' . $extension . '"%';
            $typeConditions[] = 'table.contents.text LIKE ?';
            $typeParams[] = '%"type": "' . $extension . '"%';
        }

        $typeOnly = '((' . implode(' OR ', $typeConditions) . ') AND table.contents.text NOT LIKE ?)';
        $typeParams[] = '%"mime"%';

        // Typecho stores attachment JSON both with and without escaped
        // slashes (`\/usr\/uploads` vs `/usr/uploads`), and MySQL treats
        // backslash as a LIKE escape character. Match the path key plus the
        // upload directory name so the predicate works across databases.
        $directoryName = trim((string) basename($directory), '/');
        $jsonPathConditions = [
            'table.contents.text LIKE ?',
            'table.contents.text LIKE ?',
        ];
        $jsonPathParams = ['%"path"%', '%' . $directoryName . '%'];

        $legacyPathConditions = [];
        $legacyParams = [];
        foreach ($prefixes as $prefix) {
            $legacyPathConditions[] = 'table.contents.text LIKE ?';
            $legacyParams[] = $prefix . '%';
        }

        $legacyExtensionConditions = [];
        foreach (array_keys(self::IMAGE_TYPES) as $extension) {
            $legacyExtensionConditions[] = 'table.contents.text LIKE ?';
            $legacyParams[] = '%' . $extension;
        }

        $imageMeta = '((' . implode(' OR ', array_merge($mimeConditions, [$typeOnly])) . '))';
        $jsonPath = '(' . implode(' OR ', $jsonPathConditions) . ')';
        $legacy = '((' . implode(' OR ', $legacyPathConditions) . ') AND ('
            . implode(' OR ', $legacyExtensionConditions) . '))';
        $condition = '((' . $imageMeta . ' AND ' . $jsonPath . ') OR ' . $legacy . ')'
            . ' AND table.contents.text NOT LIKE ?';

        $params = array_merge(
            $mimeParams,
            $typeParams,
            $jsonPathParams,
            $legacyParams,
            ['%..%']
        );

        return ['(' . $condition . ')', $params];
    }

    private static function currentUserId(): int
    {
        try {
            if (class_exists('Widget\\User')) {
                $user = \Widget\User::alloc();
                return (int) $user->uid;
            }
        } catch (Throwable) {
            // Fall back to zero; Typecho's attachment listing does not depend on it.
        }

        return 0;
    }
}
