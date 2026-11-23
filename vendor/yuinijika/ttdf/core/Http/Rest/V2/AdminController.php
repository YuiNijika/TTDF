<?php

declare(strict_types=1);

namespace TTDF\Http\Rest\V2;

use TTDF\Http\Request;
use TTDF\Http\Response;
use TTDF\Http\Rest\AdminController as V1AdminController;
use TTDF\Http\Rest\MediaController;

/**
 * Administration endpoints of the v2 API: content, taxonomy, comments, users
 * and the curated site options. Media and diagnostics reuse the v1 controllers
 * because their behaviour is already version independent.
 */
final class AdminController
{
    private readonly Contents $contents;

    private readonly Terms $terms;

    private readonly Comments $comments;

    private readonly Users $users;

    private readonly SiteOptions $options;

    private readonly MediaController $media;

    public function __construct(
        private readonly string $root,
        ?Contents $contents = null,
        ?Terms $terms = null,
        ?Comments $comments = null,
        ?Users $users = null,
        ?SiteOptions $options = null,
        ?MediaController $media = null
    ) {
        $this->contents = $contents ?? new Contents();
        $this->terms = $terms ?? new Terms();
        $this->comments = $comments ?? new Comments();
        $this->users = $users ?? new Users();
        $this->options = $options ?? new SiteOptions();
        $this->media = $media ?? new MediaController();
    }

    public function site(): never
    {
        Response::success($this->options->dashboard());
    }

    public function optionsGet(): never
    {
        Response::success($this->options->all());
    }

    public function optionsPatch(): never
    {
        $body = Payload::body();
        $settings = isset($body['options']) && is_array($body['options']) ? $body['options'] : $body;

        Response::success($this->options->update($settings));
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function contentList(array $identity): never
    {
        $authorId = self::isAdministrator($identity)
            ? Request::queryInt('authorId', 0)
            : (int) ($identity['uid'] ?? 0);

        $result = $this->contents->listing([
            'type' => Request::queryString('type'),
            'status' => Request::queryString('status'),
            'search' => Request::queryString('search'),
            'authorId' => $authorId,
            'page' => Request::queryInt('page', 1),
            'pageSize' => Request::queryInt('pageSize', 20),
        ]);

        Response::success($result['items'], self::meta($result['total']));
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function content(int $cid, array $identity): never
    {
        self::assertCanManage($this->contents, $cid, $identity);

        $row = $this->contents->find($cid);
        if ($row === null) {
            Response::error('Content not found', 404);
        }

        Response::success($row);
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function createContent(string $type, array $identity): never
    {
        $body = Payload::body();
        Payload::require($body, 'title');
        self::assertRequestedAuthor($body, $identity);

        Response::success($this->contents->create($type, $body, (int) $identity['uid']), [], 201);
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function updateContent(string $type, int $cid, array $identity): never
    {
        $body = Payload::body();
        self::assertCanManage($this->contents, $cid, $identity);
        self::assertRequestedAuthor($body, $identity);

        Response::success($this->contents->update($type, $cid, $body, (int) $identity['uid']));
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function deleteContent(string $type, int $cid, array $identity): never
    {
        self::assertCanManage($this->contents, $cid, $identity);

        $this->contents->delete($type, $cid);
        Response::success(['deleted' => $cid, 'type' => $type]);
    }

    public function commentList(): never
    {
        $result = $this->comments->listing([
            'status' => Request::queryString('status'),
            'cid' => Request::queryInt('cid', 0),
            'authorId' => Request::queryInt('authorId', 0),
            'search' => Request::queryString('search'),
            'page' => Request::queryInt('page', 1),
            'pageSize' => Request::queryInt('pageSize', 20),
        ]);

        Response::success($result['items'], self::meta($result['total']));
    }

    public function commentStatus(int $coid): never
    {
        $body = Payload::body();
        Payload::require($body, 'status');

        Response::success($this->comments->setStatus($coid, strtolower(Payload::string($body, 'status', '', 16))));
    }

    public function commentDelete(int $coid): never
    {
        Response::success(['deleted' => $this->comments->delete($coid)]);
    }

    public function commentBulk(): never
    {
        $body = Payload::body();
        Payload::require($body, 'ids', 'action');

        Response::success($this->comments->bulk(
            Payload::ints($body, 'ids', 200),
            Payload::string($body, 'action', '', 32)
        ));
    }

    public function termList(string $type): never
    {
        Response::success($this->terms->all($type, Request::queryInt('parent', -1)));
    }

    public function termCreate(string $type): never
    {
        $body = Payload::body();
        Payload::require($body, 'name');

        Response::success($this->terms->create($type, $body), [], 201);
    }

    public function termUpdate(string $type, int $mid): never
    {
        Response::success($this->terms->update($type, $mid, Payload::body()));
    }

    public function termDelete(string $type, int $mid): never
    {
        Response::success($this->terms->delete($type, $mid));
    }

    public function userList(): never
    {
        $page = max(1, Request::queryInt('page', 1));
        $pageSize = Payload::clamp(Request::queryInt('pageSize', 20), 1, 100, 20);
        $search = Request::queryString('search');

        Response::success(
            $this->users->all($page, $pageSize, $search),
            self::meta($this->users->count($search))
        );
    }

    public function user(int $uid): never
    {
        $user = $this->users->find($uid);
        if ($user === null) {
            Response::error('User not found', 404);
        }

        Response::success($this->users->profile($user));
    }

    public function userCreate(): never
    {
        $body = Payload::body();
        Payload::require($body, 'name', 'mail', 'password');

        Response::success($this->users->create([
            'name' => Payload::string($body, 'name', '', 32),
            'mail' => Payload::string($body, 'mail', '', 150),
            'password' => (string) $body['password'],
            'screenName' => Payload::string($body, 'screenName', '', 32),
            'url' => Payload::string($body, 'url', '', 150),
            'group' => Payload::string($body, 'group', 'contributor', 16),
        ]), [], 201);
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function userUpdate(int $uid, array $identity): never
    {
        $body = Payload::body();
        $input = [];

        foreach (['name', 'screenName', 'mail', 'url', 'group', 'password'] as $key) {
            if (!Payload::has($body, $key)) {
                continue;
            }

            $input[$key] = $key === 'password' ? (string) $body[$key] : Payload::string($body, $key, '', 150);
        }

        if ($input === []) {
            Response::error('Invalid request body', 422, ['body' => '没有需要更新的字段']);
        }

        if (
            (int) $identity['uid'] === $uid
            && isset($input['group'])
            && $input['group'] !== 'administrator'
        ) {
            Response::error('You cannot demote your own account', 409);
        }

        Response::success($this->users->update($uid, $input));
    }

    /**
     * @param array<string, mixed> $identity
     */
    public function userDelete(int $uid, array $identity): never
    {
        if ((int) $identity['uid'] === $uid) {
            Response::error('You cannot delete your own account', 409);
        }

        $this->users->delete($uid);

        Response::success(['deleted' => $uid]);
    }

    public function mediaList(): never
    {
        $this->media->index();
    }

    public function mediaShow(int $id): never
    {
        $this->media->show($id);
    }

    public function mediaUpload(): never
    {
        $this->media->upload();
    }

    public function mediaRename(int $id): never
    {
        $this->media->rename($id);
    }

    public function mediaDelete(int $id): never
    {
        $this->media->delete($id);
    }

    public function diagnostics(): never
    {
        (new V1AdminController($this->root))->diagnostics();
    }

    /**
     * @return array<string, mixed>
     */
    private static function meta(int $total): array
    {
        $page = max(1, Request::queryInt('page', 1));
        $pageSize = Payload::clamp(Request::queryInt('pageSize', 20), 1, 100, 20);

        return [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'lastPage' => $total === 0 ? 0 : (int) ceil($total / $pageSize),
        ];
    }

    /**
     * @param array<string, mixed> $identity
     */
    private static function assertCanManage(
        Contents $contents,
        int $cid,
        array $identity
    ): void {
        if (self::isAdministrator($identity)) {
            return;
        }

        $row = $contents->find($cid);
        if ($row === null) {
            Response::error('Content not found', 404);
        }

        if ((int) ($row['authorId'] ?? 0) !== (int) ($identity['uid'] ?? 0)) {
            Response::error('You can only manage your own content', 403);
        }
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $identity
     */
    private static function assertRequestedAuthor(array $body, array $identity): void
    {
        if (
            self::isAdministrator($identity)
            || !array_key_exists('authorId', $body)
        ) {
            return;
        }

        $requested = is_scalar($body['authorId']) ? (int) $body['authorId'] : 0;
        if ($requested > 0 && $requested !== (int) ($identity['uid'] ?? 0)) {
            Response::error('You cannot assign this content to another author', 403);
        }
    }

    /**
     * @param array<string, mixed> $identity
     */
    private static function isAdministrator(array $identity): bool
    {
        return (string) ($identity['group'] ?? '') === 'administrator';
    }
}
