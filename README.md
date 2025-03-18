# Typecho Theme Development Framework

> 一个 Typecho 主题开发框架 v2 版，~~还算不上框架只能说让开发变得更简单些~~

特别感谢[@Sualiu](https://github.com/Sualiu)

[开发示例](https://www.bilibili.com/video/BV1qLQKYmE6j)

### 类与方法

调用方法时值为 true 直接 echo 输出，如果为 false 则返回值。  
Get::SiteUrl(true) 为 echo 输出  
Get::SiteUrl(false) 为 return 返回值

#### TTDF 类

|    方法    |        描述         |        示例         |
| :--------: | :-----------------: | :-----------------: |
|    Ver     |   获取框架版本号    |    TTDF::Ver();     |
| TypechoVer | 获取 Typecho 版本号 | TTDF::TypechoVer(); |
|  HeadMeta  |   调用 meta 标签    |  TTDF::HeadMeta();  |
| HeadMetaOG |    调用 OG 标签     | TTDF::HeadMetaOG(); |

#### Get 类

获取站点信息及其他通用功能。

|            方法             |       描述       |          示例           |
| :-------------------------: | :--------------: | :---------------------: |
|          SiteUrl()          |  获取站点的 URL  |     Get::SiteUrl();     |
|          PageUrl()          |   获取当前url    |     Get::PageUrl();     |
|         SiteName()          |   获取站点名称   |    Get::SiteName();     |
|       SiteKeywords()        |  获取站点关键词  |  Get::SiteKeywords();   |
|      SiteDescription()      |   获取站点描述   | Get::SiteDescription(); |
|       Options($param)       |    获取配置项    |  Get::Options('name');  |
|       Fields($param)        |     获取字段     |  Get::Fields('name');   |
|          Is($type)          | 判断当前页面类型 |    Get::Is('type');     |
|           Next()            |    返回数组值    |      Get::Next();       |
|    PageNav($prev, $next)    |   获取分页导航   |     Get::PageNav();     |
| PageLink($link, $type = '') |   获取分页链接   |    Get::PageLink();     |
|           Total()           |   获取文章总数   |      Get::Total();      |
|         PageSize()          |  获取每页文章数  |    Get::PageSize();     |
|        CurrentPage()        |   获取当前页码   |   Get::CurrentPage();   |
|         Permalink()         |   获取文章链接   |    Get::Permalink();    |
|        Field($field)        |  获取自定义字段  |      Get::Field();      |

> Get::PageUrl() 方法可自定义输出，示例如下：
> 默认调用  
> Get::PageUrl();  
> 移除所有查询参数  
> Get::PageUrl(true, false, null, true);  
> 屏蔽指定参数  
> Get::PageUrl(true, false, ['foo', 'baz']);  
> 移除所有查询参数并移除端口  
> Get::PageUrl(true, true, null, true);  

#### GetTheme 类

获取主题的相关信息。

| 方法     | 描述           | 示例                |
| -------- | -------------- | ------------------- |
| Url()    | 获取主题的 URL | GetTheme::Url();    |
| Name()   | 获取主题名称   | GetTheme::Name();   |
| Author() | 获取主题作者   | GetTheme::Author(); |
| Ver()    | 获取主题版本号 | GetTheme::Ver();    |

#### GetPost 类

获取文章的相关信息。

|       方法        |       描述       |            示例             |
| :---------------: | :--------------: | :-------------------------: |
|      List()       |   获取文章列表   |      GetPost::List();       |  |
|      Title()      |   获取文章标题   |      GetPost::Title();      |
|      Date()       |   获取文章日期   |      GetPost::Date();       |
|    Category()     |   获取文章分类   |    GetPost::Category();     |
|      Tags()       |   获取文章标签   |      GetPost::Tags();       |
|     Excerpt()     |   获取文章摘要   |     GetPost::Excerpt();     |
|    Permalink()    |   获取文章链接   |    GetPost::Permalink();    |
|     Content()     |   获取文章内容   |     GetPost::Content();     |
|   DB_Content()    |  获取文章md内容  |   GetPost::DB_Content();    |
| DB_Content_Html() | 获取文章html内容 | GetPost::DB_Content_Html(); |
|    WordCount()    |   获取文章字数   |    GetPost::WordCount();    |
|    PostsNum()     |    获取文章数    |    GetPost::PostsNum();     |
|   CurrentPage()   |   获取当前页码   |   GetPost::CurrentPage();   |
|  ArchiveTitle()   | 获取当前页面标题 |  GetPost::ArchiveTitle();   |
|     Author()      |   获取文章作者   |     GetPost::Author();>     |
| AuthorPermalink() |   获取作者链接   | GetPost::AuthorPermalink(); |

> GetPost:List() 方法可自定义输出，示例如下：
> 默认调用
``` php
 while (GetPost::List()) {
    
};
```
> 自定义调用
``` php
<?php 
// 第一个文章列表
$featuredPosts = GetPost::List (
    [
        'pageSize' => 3, 
        'type' => 'category', 
        'mid' => 1
    ]
); 
?>
<?php while ($featuredPosts->next()) { ?>
    <div class="featured-post">
        <h2><?php GetPost::Title() ?></h2>
        <p><?php GetPost::Excerpt(100) ?></p>
        <?php GetPost::Date('Y年m月d日') ?>
    </div>
<?php }; ?>
<?php GetPost::unbindArchive(); // 结束当前绑定 ?>
```

#### GetAuthor 类

获取作者的相关信息。

|    方法     |       描述       |          示例           |
| :---------: | :--------------: | :---------------------: |
|   Name()    | 获取当前页面标题 |   GetAuthor::Name();    |
|  Author()   |   获取文章作者   |  GetAuthor::Author();>  |
| Permalink() |   获取作者链接   | GetAuthor::Permalink(); |

#### GetComments 类

获取评论的相关信息。

|      方法      |     描述      |             示例             |
| :------------: | :-----------: | :--------------------------: |
|   Comments()   |   获取评论    |   GetComments::Comments();   |
| CommentsPage() | 获取评论页面  | GetComments::CommentsPage(); |
| CommentsList() | 获取评论列表  | GetComments::CommentsList(); |
| CommentsNum()  |  获取评论数   | GetComments::CommentsNum();  |
| CommentsForm() | 获取评论表单  | GetComments::CommentsForm(); |
|  RespondId()   |  获取评论 id  |  GetComments::RespondId();>  |
| CancelReply()  | 获取取消回复  | GetComments::CancelReply();  |
|   Remember()   | 获取 Remember |   GetComments::Remember();   |
|   PageNav()    | 获取评论分页  |   GetComments::PageNav();    |

#### GetFunctions 类

提供一些常用的功能函数。

|    方法     |     描述     |            示例            |
| :---------: | :----------: | :------------------------: |
| TimerStop() | 获取加载时间 | GetFunctions::TimerStop(); |

### REST API

一个简单的 REST API，你可以使用它来获取一些数据。

| 调用  |      路由       |      参数      | 描述         |
| :---: | :-------------: | :------------: |
|  Get  |  /API/PostList  | pageSize, page | 获取文章列表 |
|  Get  |  /API/Category  |      cid       | 获取分类列表 |
|  Get  |    /API/Tag     |      tid       | 获取标签列表 |
|  Get  | /API/PostCommon |      cid       | 获取文章数据 |