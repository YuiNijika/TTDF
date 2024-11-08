# 欢迎使用Typecho主题模板开发框架！

本框架是鼠子写给自己使用的，还请勿喷！  
现已开源提交至GitHub、Gitee、云原生(cnb.cool)。

## 使用说明

这只是一个简单的开发框架，提供了一些常用的功能以及功能函数。

#### 框架说明

- 框架版本号：1.0.0
- 主要文件
  > inc/Config.php 配置文件
  > inc/get.php Get函数
  > inc/options.php 主题设置

## 函数调用

### 基本函数

首页网址

```
Get::SiteUrl();
```

主题Url

```
Get::ThemeUrl();
```

assets目录Url

```
Get::AssetsUrl();
```

主题版本号

```
Get::ThemeVer();
```

框架版本号

```
Get::FrameworkVer();
```

设置内容

```
Get::Options('');
> Demo: $this->options->title; > Get::Options('title');
> 如果未能成功输出请使用echo再次尝试。
```

#### 文章函数

获取文章标题

```
Get::Title();
```

获取文章日期

```
Get::Date();
```

获取文章分类

```
Get::Category();
```

获取文章标签

```
Get::Tags();
```

获取文章摘要

```
Get::Excerpt();
```

获取文章链接

```
Get::Permalink();
```

获取文章评论数

```
Get::CommentsNum();
```

获取文章内容

```
Get::Content();
```

获取文章数

```
Get::PostsNum();
```

获取页面数

```
Get::PagesNum();
```

获取当前页码

```
Get::CurrentPage();
```

获取当前页面类型

```
Get::Is();
```

获取当前页面标题

```
Get::ArchiveTitle();
```

获取当前页面作者

```
Get::Author();
```

获取当前页面作者链接

```
Get::AuthorPermalink();
```
