<?php

declare(strict_types=1);

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
?>
<div id="comments">
    <?php $this->comments()->to($comments); ?>

    <?php if ($comments->have()): ?>
        <h2>评论</h2>
        <?php $comments->listComments(); ?>
        <?php $comments->pageNav(); ?>
    <?php endif; ?>

    <?php if ($this->allow('comment')): ?>
        <div id="<?php $this->respondId(); ?>" class="respond">
            <div class="cancel-comment-reply"><?php $comments->cancelReply(); ?></div>

            <h2>添加新评论</h2>
            <form method="post" action="<?php $this->commentUrl(); ?>" id="comment-form">
                <?php if ($this->user->hasLogin()): ?>
                    <p>
                        登录身份：
                        <a href="<?php $this->options->profileUrl(); ?>"><?php $this->user->screenName(); ?></a>
                        <a href="<?php $this->options->logoutUrl(); ?>">退出</a>
                    </p>
                <?php else: ?>
                    <p>
                        <label for="author">称呼</label>
                        <input type="text" name="author" id="author" value="<?php $this->remember('author'); ?>" required>
                    </p>
                    <p>
                        <label for="mail">Email</label>
                        <input type="email" name="mail" id="mail" value="<?php $this->remember('mail'); ?>"
                            <?php if ($this->options->commentsRequireMail): ?>required<?php endif; ?>>
                    </p>
                    <p>
                        <label for="url">网站</label>
                        <input type="url" name="url" id="url" value="<?php $this->remember('url'); ?>"
                            <?php if ($this->options->commentsRequireUrl): ?>required<?php endif; ?>>
                    </p>
                <?php endif; ?>
                <p>
                    <label for="textarea">内容</label>
                    <textarea name="text" id="textarea" rows="8" required><?php $this->remember('text'); ?></textarea>
                </p>
                <p><button type="submit">提交评论</button></p>
            </form>
        </div>
    <?php else: ?>
        <p>评论已关闭。</p>
    <?php endif; ?>
</div>
