# Typecho Theme Development Framework v2

> 一个 Typecho 主题开发框架，~~还算不上框架只能说让开发变得更简单些~~

特别感谢[@Sualiu](https://github.com/Sualiu)

### 类与方法

调用方法时值为 true 直接echo输出，如果为 false 则返回值。  
Get::SiteUrl(true) 为 echo 输出  
Get::SiteUrl(false) 为 return 返回值

#### Get 类

获取站点信息及其他通用功能。

| 方法                        | 描述                   | 示例                          |
| --------------------------- | ---------------------- | ----------------------------- |
| Header()                    | 获取 Typecho Header    | <?php Get::Header(); ?>       |
| Footer()                    | 获取 Typecho Footer    | <?php Get::Footer(); ?>       |
| SiteUrl()                   | 获取站点的 URL         | <?php Get::SiteUrl(); ?>      |
| AssetsUrl()                 | 获取主题的资源文件 URL | <?php Get::AssetsUrl(); ?>    |
| TypechoVer()                | 获取 Typecho 版本号    | <?php Get::TypechoVer(); ?>   |
| FrameworkVer()              | 获取框架版本号         | <?php Get::FrameworkVer(); ?> |
| Options($param)             | 获取指定的设置项       | <?php echo Get::Options(); ?> |
| Fields($param)              | 获取自定义字段         | <?php echo Get::Fields(); ?>  |
| Next()                      | 循环输出文章           | <?php Get::Next(); ?>         |
| Need($file)                 | 引入文件               | <?php Get::Need('file'); ?>   |
| Is($type)                   | 获取当前页面类型       | <?php Get::Is('type'); ?>     |
| PageNav($prev, $next)       | 获取分页导航           | <?php Get::PageNav(); ?>      |
| PageLink($link, $type = '') | 获取分页链接           | <?php Get::PageLink(); ?>     |
| Total()                     | 获取文章总数           | <?php Get::Total(); ?>        |
| PageSize()                  | 获取每页文章数         | <?php Get::PageSize(); ?>     |
| CurrentPage()               | 获取当前页码           | <?php Get::CurrentPage(); ?>  |
| Permalink()                 | 获取文章链接           | <?php Get::Permalink();>      |
| Field($field)               | 获取自定义字段         | <?php Get::Field(); ?>        |

#### GetTheme 类

获取主题的相关信息。

| 方法     | 描述           | 示例                       |
| -------- | -------------- | -------------------------- |
| Url()    | 获取主题的 URL | <?php GetTheme::Url();>    |
| Name()   | 获取主题名称   | <?php GetTheme::Name();>   |
| Author() | 获取主题作者   | <?php GetTheme::Author();> |
| Ver()    | 获取主题版本号 | <?php GetTheme::Ver();>    |

#### GetPost 类

获取文章的相关信息。

| 方法              | 描述             | 示例                               |
| ----------------- | ---------------- | ---------------------------------- |
| Title()           | 获取文章标题     | <?php GetPost::Title();>           |
| Date()            | 获取文章日期     | <?php GetPost::Date();>            |
| Category()        | 获取文章分类     | <?php GetPost::Category();>        |
| Tags()            | 获取文章标签     | <?php GetPost::Tags();>            |
| Excerpt()         | 获取文章摘要     | <?php GetPost::Excerpt();>         |
| Permalink()       | 获取文章链接     | <?php GetPost::Permalink();>       |
| Content()         | 获取文章内容     | <?php GetPost::Content();>         |
| PostsNum()        | 获取文章数       | <?php GetPost::PostsNum();>        |
| PagesNum()        | 获取页面数       | <?php GetPost::PagesNum();>        |
| CurrentPage()     | 获取当前页码     | <?php GetPost::CurrentPage();>     |
| ArchiveTitle()    | 获取当前页面标题 | <?php GetPost::ArchiveTitle();>    |
| Author()          | 获取文章作者     | <?php GetPost::Author();>          |
| AuthorPermalink() | 获取作者链接     | <?php GetPost::AuthorPermalink();> |

#### GetComments 类

获取评论的相关信息。

| 方法           | 描述          | 示例                                |
| -------------- | ------------- | ----------------------------------- |
| Comments()     | 获取评论      | <?php GetComments::Comments();>     |
| CommentsPage() | 获取评论页面  | <?php GetComments::CommentsPage();> |
| CommentsList() | 获取评论列表  | <?php GetComments::CommentsList();> |
| CommentsNum()  | 获取评论数    | <?php GetComments::CommentsNum();>  |
| CommentsForm() | 获取评论表单  | <?php GetComments::CommentsForm();> |
| RespondId()    | 获取评论 id   | <?php GetComments::RespondId();>    |
| CancelReply()  | 获取取消回复  | <?php GetComments::CancelReply();>  |
| Remember()     | 获取 Remember | <?php GetComments::Remember();>     |
| PageNav()      | 获取评论分页  | <?php GetComments::PageNav();>      |

#### GetFunctions 类

提供一些常用的功能函数。

| 方法        | 描述         | 示例                              |
| ----------- | ------------ | --------------------------------- |
| TimerStop() | 获取加载时间 | <?php GetFunctions::TimerStop();> |
| ArtCount()  | 获取文章字数 | <?php GetFunctions::ArtCount();>  |

#### GetJsonData 类

提供 Json 数据输出。

> 注意，需要启用 Json 输出，请在 header 文件顶部增加 GetJsonData::Tomori(); 方法

| 方法          | 描述               | 示例                               |
| ------------- | ------------------ | ---------------------------------- |
| Tomori()      | 启用 Json 输出     | <?php GetJsonData::Tomori();>      |
| JsonTitle()   | 获取 Json 数据标题 | <?php GetJsonData::JsonTitle();>   |
| JsonContent() | 获取 Json 数据内容 | <?php GetJsonData::JsonContent();> |

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
