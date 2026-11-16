<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use TTDF\Support\Escaper;

$archive = $viewData['archive'] ?? null;
if (!$archive instanceof \Widget\Archive) {
    return;
}

$user = \Widget\User::alloc();
$options = \Widget\Options::alloc();

if (!function_exists('threadedComments')) {
    /**
     * Typecho looks up this global callback before using its own comment
     * markup, so the theme can own the HTML without overriding CSS.
     */
    function threadedComments(\Widget\Comments\Archive $comments, \Typecho\Config $options): void
    {
        $classes = ['comment'];
        if ((int) $comments->levels > 0) {
            $classes[] = 'comment--nested';
        }
        if ((int) $comments->authorId > 0) {
            $classes[] = (string) $comments->authorId === (string) $comments->ownerId
                ? 'comment--author'
                : 'comment--user';
        }
        ?>
        <li id="<?php $comments->theId(); ?>" class="<?php echo Escaper::html(implode(' ', $classes)); ?>" itemscope itemtype="http://schema.org/UserComments">
            <div class="comment__avatar">
                <?php $comments->gravatar((int) $options->avatarSize, $options->defaultAvatar, $options->avatarHighRes); ?>
            </div>

            <div class="comment__main">
                <header class="comment__head">
                    <cite class="comment__author" itemprop="creator"><?php $comments->author(); ?></cite>
                    <a class="comment__time" href="<?php $comments->permalink(); ?>">
                        <time datetime="<?php $comments->date('c'); ?>" itemprop="commentTime"><?php $comments->date((string) $options->dateFormat); ?></time>
                    </a>
                    <?php if ((string) $comments->status !== 'approved'): ?>
                        <span class="comment__pending">待审核</span>
                    <?php endif; ?>
                </header>

                <div class="comment__body" itemprop="commentText"><?php $comments->content(); ?></div>

                <div class="comment__actions"><?php $comments->reply((string) $options->replyWord); ?></div>

                <?php if ($comments->children): ?>
                    <div class="comment__children"><?php $comments->threadedComments(); ?></div>
                <?php endif; ?>
            </div>
        </li>
        <?php
    }
}
?>
<section id="comments" class="comments" aria-labelledby="comments-title">
    <?php $archive->comments()->to($comments); ?>

    <h2 id="comments-title" class="comments__title">评论</h2>

    <?php if ($comments->have()): ?>
        <?php $comments->listComments([
            'before' => '<ol class="comment-list">',
            'after' => '</ol>',
            'replyWord' => '回复',
        ]); ?>

        <nav class="pagination" aria-label="评论分页">
            <?php $comments->pageNav('上一页', '下一页'); ?>
        </nav>
    <?php else: ?>
        <p class="comments__empty">暂无评论。</p>
    <?php endif; ?>

    <?php if ($archive->allow('comment')): ?>
        <div id="<?php $archive->respondId(); ?>" class="comment-respond">
            <div class="cancel-comment-reply"><?php $comments->cancelReply(); ?></div>

            <form method="post" action="<?php $archive->commentUrl(); ?>" id="comment-form" class="comment-form ttdf-comment-form">
                <?php if ($user->hasLogin()): ?>
                    <p class="comment-identity">
                        登录身份：
                        <a href="<?php $options->profileUrl(); ?>"><?php $user->screenName(); ?></a>
                        <span aria-hidden="true">·</span>
                        <a href="<?php $options->logoutUrl(); ?>">退出</a>
                    </p>
                <?php else: ?>
                    <p class="comment-field">
                        <label for="author">称呼</label>
                        <input type="text" id="author" name="author" value="<?php $archive->remember('author'); ?>" autocomplete="nickname" required>
                    </p>
                    <p class="comment-field">
                        <label for="mail">邮箱</label>
                        <input type="email" id="mail" name="mail" value="<?php $archive->remember('mail'); ?>" autocomplete="email" spellcheck="false"<?php if ($options->commentsRequireMail): ?> required<?php endif; ?>>
                    </p>
                    <p class="comment-field">
                        <label for="url">网站</label>
                        <input type="url" id="url" name="url" value="<?php $archive->remember('url'); ?>" autocomplete="url" placeholder="https://"<?php if ($options->commentsRequireUrl): ?> required<?php endif; ?>>
                    </p>
                <?php endif; ?>

                <p class="comment-field comment-field--wide">
                    <label for="textarea">内容</label>
                    <textarea id="textarea" name="text" rows="6" autocomplete="off" required><?php $archive->remember('text'); ?></textarea>
                </p>

                <p class="comment-submit">
                    <button class="btn btn--primary" type="submit">提交评论</button>
                </p>
            </form>
        </div>
    <?php else: ?>
        <p class="comments__closed">评论已关闭。</p>
    <?php endif; ?>
</section>
