# 欢迎使用TTDF框架
> Typecho Theme Development Framework

## 介绍
TTDF是一个Typecho主题模板开发框架；  
面向小白，免费、简单、开源，提供了一些常用的功能以及调用函数，支持REST API。

### 核心文件
  > inc/Config.php 配置文件  
  > inc/Get.php Get函数  
  > inc/Options.php 主题设置  
  > inc/Functions.php 函数功能  
  > inc/Json.php REST API

---

### 类与方法

#### Get类
获取站点信息及其他通用功能。

| 方法         | 描述                       | 示例                         |
| ------------ | -------------------------- | ---------------------------- |
| Header()     | 获取Typecho Header         | <?php Get::Header(); ?>     |
| Footer()     | 获取Typecho Footer         | <?php Get::Footer(); ?>     | 
| SiteUrl()    | 获取站点的 URL             | <?php Get::SiteUrl(); ?>     |
| AssetsUrl()  | 获取主题的资源文件 URL     | <?php Get::AssetsUrl(); ?>   |
| TypechoVer() | 获取 Typecho 版本号       | <?php Get::TypechoVer(); ?> |
| FrameworkVer() | 获取框架版本号          | <?php Get::FrameworkVer(); ?> |
| Options($param) | 获取指定的设置项      | <?php echo Get::Options(''); ?> |
| Fields($param) | 获取自定义字段      | <?php echo Get::Fields(''); ?> |
| Next()         | 循环输出文章         | <?php Get::Next(); ?>          |
| Need($file)     | 引入文件      | <?php Get::Need('file'); ?> |
| Is($type)       | 获取当前页面类型      | <?php Get::Is('type'); ?> |
| PageNav($prev, $next) | 获取分页导航      | <?php Get::PageNav(); ?> |
| PageLink($link, $type = '') | 获取分页链接      | <?php Get::PageLink(); ?> |
| Total() | 获取文章总数      | <?php Get::Total(); ?> |
| PageSize() | 获取每页文章数      | <?php Get::PageSize(); ?> |
| CurrentPage() | 获取当前页码      | <?php Get::CurrentPage(); ?> |
| Permalink() | 获取文章链接      | <?php Get::Permalink();> |
| Field($field) | 获取自定义字段      | <?php Get::Field(''); ?> |

#### GetTheme 类
获取主题的相关信息。

| 方法   | 描述         | 示例                |
| ------ | ------------ | ------------------- |
| Url()  | 获取主题的 URL | <?php GetTheme::Url();> |
| Name() | 获取主题名称 | <?php GetTheme::Name();> |
| Author() | 获取主题作者 | <?php GetTheme::Author();> |
| Ver()  | 获取主题版本号 | <?php GetTheme::Ver();> |

#### GetPost 类
获取文章的相关信息。

| 方法        | 描述                 | 示例                       |
| ----------- | -------------------- | -------------------------- |
| Title()     | 获取文章标题         | <?php GetPost::Title();>   |
| Date()      | 获取文章日期         | <?php GetPost::Date();>    |
| Category()  | 获取文章分类         | <?php GetPost::Category();> |
| Tags()      | 获取文章标签         | <?php GetPost::Tags();>    |
| Excerpt()   | 获取文章摘要         | <?php GetPost::Excerpt();> |
| Permalink() | 获取文章链接         | <?php GetPost::Permalink();>|
| Content()   | 获取文章内容         | <?php GetPost::Content();>  |
| PostsNum()  | 获取文章数           | <?php GetPost::PostsNum();> |
| PagesNum()  | 获取页面数           | <?php GetPost::PagesNum();> |
| CurrentPage() | 获取当前页码       | <?php GetPost::CurrentPage();> |
| ArchiveTitle() | 获取当前页面标题 | <?php GetPost::ArchiveTitle();> |
| Author()    | 获取文章作者         | <?php GetPost::Author();>   |
| AuthorPermalink() | 获取作者链接     | <?php GetPost::AuthorPermalink();> |

#### GetComments 类
获取评论的相关信息。

| 方法        | 描述                 | 示例                       |
| ----------- | -------------------- | -------------------------- |
| Comments()  | 获取评论             | <?php GetComments::Comments();> |
| CommentsPage() | 获取评论页面     | <?php GetComments::CommentsPage();> |
| CommentsList() | 获取评论列表     | <?php GetComments::CommentsList();> |
| CommentsNum()  | 获取评论数       | <?php GetComments::CommentsNum();> |
| CommentsForm() | 获取评论表单     | <?php GetComments::CommentsForm();> |
| RespondId() | 获取评论id     | <?php GetComments::RespondId();> |
| CancelReply() | 获取取消回复     | <?php GetComments::CancelReply();> |
| Remember() | 获取Remember     | <?php GetComments::Remember();> |
| PageNav() | 获取评论分页     | <?php GetComments::PageNav();> |

#### GetFunctions 类
提供一些常用的功能函数。

| 方法         | 描述                       | 示例                         |
| ------------ | -------------------------- | ---------------------------- |
| TimerStop()  | 获取加载时间               | <?php GetFunctions::TimerStop();> |
| ArtCount()   | 获取文章字数               | <?php GetFunctions::ArtCount();>   |

#### GetJsonData 类
提供Json数据输出。

> 注意，需要启用Json输出，请在header文件顶部增加 GetJsonData::Tomori(); 方法

| 方法         | 描述                       | 示例                         |
| ------------ | -------------------------- | ---------------------------- |
| Tomori()  | 启用Json输出               | <?php GetJsonData::Tomori();> |
| JsonTitle()  | 获取Json数据标题           | <?php GetJsonData::JsonTitle();> |
| JsonContent()   | 获取Json数据内容           | <?php GetJsonData::JsonContent();>   |

##### REST API 使用说明：
获取文章列表：
```
?JsonData=page
?JsonData=page&page=2
```
获取文章详情：
```
?JsonData=common&cid=文章ID
```
获取分类：
```
?JsonData=category                    // 获取所有分类
?JsonData=category&cid=分类ID         // 获取特定分类下的文章
?JsonData=category&cid=分类ID&page=2  // 分页获取分类文章
```
获取标签：
```
?JsonData=tag                    // 获取所有标签
?JsonData=tag&tid=标签ID         // 获取特定标签下的文章
?JsonData=tag&tid=标签ID&page=2  // 分页获取标签文章
```
主要特点：
统一的响应格式
完整的错误处理

支持分页
丰富的文章元数据
主题信息输出
缓存控制
支持文章缩略图和摘要
这个版本提供了一个完整的 RESTful API 实现，可以用于构建前端应用或小程序等。

---

# 更新日志

## 1.1.4
- 新增Get::Fields()方法
> 自定义字段
- 新增Get::Permalink()方法

## 1.1.3
- 优化Get整体代码
- 新增Get::Header()方法
- 新增Get::Footer()方法
- 新增Get::Next()方法
- 新增Get::Total()方法
- 新增Get::PageSize()方法
- 优化GetPost::Tags()方法
- 优化GetPost::Category()方法
> 新增$split、$link、$default参数, 如<?php GetPost::Category(',', true, '暂无分类'); ?>

## 1.1.2
- 优化Json输出
- 优化GetTheme::AssetsUrl()方法
> 改为<?php GetTheme::AssetsUrl(); ?>
- 修改优化assets文件加载方式

## 1.1.1
- 优化Get.php代码结构
- 新增输出错误日志
- 新增Get::PageNav()方法
- 新增Get::PageLink()方法
- 优化GetPost::Tags()方法
- 优化GetPost::Excerpt()方法
- 新增参数$length，可自定义输出字数，如<?php GetPost::Excerpt(100); ?>

## 1.1.0
- 优化Json输出

## 1.0.9
- 新增Json输出

- 优化GetComments::CurrentPage()方法
> 改为自行定义，如<?php GetComments::CommentsNum('无评论', '共1评论','共%s评论'); ?>
- 新增GetComments::CommentsPage()方法
> 获取评论页面，如<?php GetComments::CommentsPage('&laquo; 前一页', '后一页 &raquo;'); ?>
- 新增GetComments::CommentsList()方法
- 新增GetComments::CommentsNum()方法
- 新增GetComments::CommentsForm()方法

## 1.0.8
 - 修复Get::Is()方法
 - 优化Get::Need()方法

## 1.0.7
 - 修复Get::Is()方法
 - 优化Get::Need()方法

## 1.0.6
 - 修复GetPost::Author()方法
 - 新增GetFunctions类
 - 新增TimerStop()方法
 - 新增ArtCount()方法

## 1.0.5
 - 默认Cravatar头像源

## 1.0.4
 - 优化框架结构
 - 新增Demo主题TTDF

## 1.0.3
 - 修复Need()方法

## 1.0.2
 - 修复AssetsUrl()方法
 - 更新inc目录结构为Config
## 1.0.1
 - 新增插件版本号获取
 - 新增Typecho版本号获取
 - 新增主题名称、作者获取
