<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 获取随机缩略图URL
 *
 * @param string $base_url 基础URL路径
 * @param int $maxImages 最大图片数量
 * @return string 随机缩略图URL
 */
function get_RandomThumbnail($base_url, $maxImages = 15)
{
    // 生成一个1到$maxImages之间的随机数  
    $rand = mt_rand(1, $maxImages);
    // 构造随机缩略图的URL  
    return $base_url . $rand . '.webp';
}

/**
 * 获取文章缩略图URL
 *
 * @param Widget_Abstract_Contents $widget 文章对象
 * @return string 缩略图URL
 */
function get_ArticleThumbnail($widget)
{
    // 自定义缩略图逻辑  
    if ($customThumb = $widget->fields->ThumbnailUrl) {
        return $customThumb;
    }

    // 尝试从内容中提取图片URL  
    if ($contentThumb = extractImageFromContent($widget->content)) {
        return $contentThumb;
    }

    // 尝试从附件中获取图片URL  
    if ($attachmentThumb = getAttachmentImageUrl($widget)) {
        return $attachmentThumb;
    }

    // 获取默认缩略图路径
    $base_url = '/Assets/images/thumb/'; // 默认缩略图路径  

    // 如果设置了articleImgSpeed，则使用它作为图片的基本URL  
    if (!empty(Helper::options()->articleImgSpeed)) {
        $base_url = Helper::options()->articleImgSpeed;
        // 确保URL以斜杠结尾  
        if (substr($base_url, -1) !== '/') {
            $base_url .= '/';
        }
    } else {
        // 使用themeUrl和默认的图片路径  
        $base_url = $widget->widget('Widget_Options')->themeUrl . $base_url;
    }

    // 调用辅助函数获取随机缩略图  
    return get_RandomThumbnail($base_url);
}

/**
 * 从文章内容中提取图片URL
 *
 * @param string $content 文章内容
 * @return string|null 图片URL或null
 */
function extractImageFromContent($content)
{
    $pattern = '/<img.*?src="(.*?)"[^>]*>/i';
    if (preg_match($pattern, $content, $matches) && strlen($matches[1]) > 7) {
        return htmlspecialchars($matches[1]);
    }
    return null;
}

/**
 * 从附件中获取图片URL
 *
 * @param Widget_Abstract_Contents $widget 文章对象
 * @return string|null 图片URL或null
 */
function getAttachmentImageUrl($widget)
{
    $attach = $widget->attachments(1)->attachment;
    if ($attach && $attach->isImage) {
        return htmlspecialchars($attach->url);
    }
    return null;
}