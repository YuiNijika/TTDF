# 欢迎使用Typecho主题模板开发框架！

本框架是鼠子写给自己使用的，还请勿喷！  
现已开源提交至GitHub、Gitee、云原生(cnb.cool)。

## 使用说明

这只是一个简单的开发框架，提供了一些常用的功能以及功能函数。

#### 框架说明

- 框架版本号：1.0.1
- 主要文件
  > inc/Config.php 配置文件
  > inc/get.php Get函数
  > inc/options.php 主题设置

# 类与方法

## Get 类
提供站点级别的信息和通用功能。

| 方法         | 描述                       | 示例                         |
| ------------ | -------------------------- | ---------------------------- |
| HelloWorld() | 输出欢迎信息               | <?php Get::HelloWorld(); ?>   |
| SiteUrl()    | 获取站点的 URL             | <?php Get::SiteUrl(); ?>     |
| AssetsUrl()  | 获取主题的资源文件 URL     | <?php Get::AssetsUrl(); ?>   |
| FrameworkVer() | 获取框架版本号          | <?php Get::FrameworkVer(); ?> |
| TypechoVer() | 获取 Typecho 版本号       | <?php Get::TypechoVer(); ?> |
| Options($param) | 获取指定的设置项      | <?php echo Get::Options('title'); ?> |

## GetTheme 类
获取主题的相关信息。

| 方法   | 描述         | 示例                |
| ------ | ------------ | ------------------- |
| Url()  | 获取主题的 URL | <?php GetTheme::Url();> |
| Name() | 获取主题名称 | <?php GetTheme::Name();> |
| Author() | 获取主题作者 | <?php GetTheme::Author();> |
| Ver()  | 获取主题版本号 | <?php GetTheme::Ver();> |

## GetPost 类
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
| CommentsNum() | 获取文章评论数     | <?php GetPost::CommentsNum();> |
| PostsNum()  | 获取文章数           | <?php GetPost::PostsNum();> |
| PagesNum()  | 获取页面数           | <?php GetPost::PagesNum();> |
| CurrentPage() | 获取当前页码       | <?php GetPost::CurrentPage();> |
| ArchiveTitle() | 获取当前页面标题 | <?php GetPost::ArchiveTitle();> |
| Author()    | 获取文章作者         | <?php GetPost::Author();>   |
| AuthorPermalink() | 获取作者链接     | <?php GetPost::AuthorPermalink();> |

### 示例
- 输出站点 URL
```php
<?php Get::SiteUrl(); ?>
```
- 输出主题 URL
```php
<?php GetTheme::Url(); ?>
```
- 输出文章标题
```php
<?php GetPost::Title(); ?>
```
- 输出文章分类
```php
<?php GetPost::Category(); ?>
```
- 引入文件
```php
<?php Get::Need('file.php'); ?>
```

### 更新日志
#### 1.0.1
 - 新增插件版本号获取
 - 新增Typecho版本号获取
 - 新增主题名称、作者获取
