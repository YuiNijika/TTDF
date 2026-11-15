<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$archive = $viewData['archive'] ?? null;
if (!$archive instanceof \Widget\Archive) {
    return;
}

$user = \Widget\User::alloc();
$options = \Widget\Options::alloc();
?>
<section id="comments" class="ttdf-comments" aria-labelledby="ttdf-comments-heading">
    <?php $archive->comments()->to($comments); ?>
    <h2 id="ttdf-comments-heading" class="ttdf-comments-title">评论</h2>

    <?php if ($comments->have()): ?>
        <?php $comments->listComments(); ?>
        <nav class="ttdf-comment-pagination" aria-label="评论分页">
            <?php $comments->pageNav(); ?>
        </nav>
    <?php endif; ?>

    <?php if ($archive->allow('comment')): ?>
        <div id="<?php $archive->respondId(); ?>" class="ttdf-respond">
            <div class="ttdf-cancel-comment-reply"><?php $comments->cancelReply(); ?></div>
            <h2 id="ttdf-comment-form-heading" class="ttdf-comment-form-title">添加新评论</h2>
            <form
                method="post"
                action="<?php $archive->commentUrl(); ?>"
                id="comment-form"
                class="ttdf-comment-form"
                aria-labelledby="ttdf-comment-form-heading"
            >
                <?php if ($user->hasLogin()): ?>
                    <p class="ttdf-comment-identity">
                        登录身份：
                        <a href="<?php $options->profileUrl(); ?>"><?php $user->screenName(); ?></a>
                        <a href="<?php $options->logoutUrl(); ?>">退出</a>
                    </p>
                <?php else: ?>
                    <p class="ttdf-comment-field">
                        <label for="author">称呼</label>
                        <input
                            type="text"
                            name="author"
                            id="author"
                            value="<?php $archive->remember('author'); ?>"
                            autocomplete="nickname"
                            required
                        >
                    </p>
                    <p class="ttdf-comment-field">
                        <label for="mail">Email</label>
                        <input
                            type="email"
                            name="mail"
                            id="mail"
                            value="<?php $archive->remember('mail'); ?>"
                            autocomplete="email"
                            <?php if ($options->commentsRequireMail): ?>required<?php endif; ?>
                        >
                    </p>
                    <p class="ttdf-comment-field">
                        <label for="url">网站</label>
                        <input
                            type="url"
                            name="url"
                            id="url"
                            value="<?php $archive->remember('url'); ?>"
                            autocomplete="url"
                            <?php if ($options->commentsRequireUrl): ?>required<?php endif; ?>
                        >
                    </p>
                <?php endif; ?>
                <p class="ttdf-comment-field">
                    <label for="textarea">内容</label>
                    <textarea
                        name="text"
                        id="textarea"
                        rows="8"
                        autocomplete="off"
                        required
                    ><?php $archive->remember('text'); ?></textarea>
                </p>
                <p class="ttdf-comment-submit">
                    <button type="submit">提交评论</button>
                </p>
            </form>
        </div>
    <?php else: ?>
        <p class="ttdf-comments-closed">评论已关闭。</p>
    <?php endif; ?>
</section>
