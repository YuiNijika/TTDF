# Typecho Theme Development Framework

> 一个 Typecho 主题开发框架 v2 版，~~还算不上框架只能说让开发变得更简单些~~

特别感谢  
[@Sualiu](https://github.com/Sualiu) & [@李初一](https://github.com/DearLicy)

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
|      List()       |   获取文章列表   |      GetPost::List();       |
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
|     Author()      |   获取文章作者   |     GetPost::Author();      |
| AuthorPermalink() |   获取作者链接   | GetPost::AuthorPermalink(); |

> GetPost:List() 方法可自定义输出，示例如下：  
> 默认调用
`` php
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

#### UserInfo 类

获取用户的相关信息。

|                 方法                  |           描述           |              示例              |
| :-----------------------------------: | :----------------------: | :----------------------------: |
|                Name()                 |        获取用户名        |        GetUser::Name();        |
| DisplayName($size, $default, $rating) |         获取昵称         |    GetUser::DisplayName();     |
|               Avatar()                |       获取用户头像       |       GetUser::Avatar();       |
|              AvatarURL()              |       获取用户头像       |     GetUser::AvatarURL();      |
|                Email()                |       获取用户邮箱       |       GetUser::Email();        |
|               WebSite()               |       获取用户网站       |      GetUser::WebSite();       |
|                 Bio()                 |       获取用户简介       |        GetUser::Bio();         |
|                Group()                |        获取用户组        |        GetUser::Role();        |
|             Registered()              |       获取注册时间       |     GetUser::Registered();     |
|              LastLogin()              |     获取最后登录时间     |     GetUser::LastLogin();      |
|              PostCount()              |        获取文章数        |     GetUser::PostCount();      |
|              PageCount()              |       获取页面数量       |     GetUser::PageCount();      |
|              Permalink()              |       获取作者链接       |     GetUser::Permalink();      |


#### Comment 类

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

### TyAjax
> 提供简单易用的 AJAX 请求处理功能

#### 基本使用
``` php
// functions.php

// 需要登录的action示例
function profile($data) {
    $user = Typecho_Widget::widget('Widget_User');
    if (!$user->hasLogin()) {
        TyAjax_send_error('请先登录', 'danger');
    }
    
    TyAjax_send_success('欢迎您！' . $user->name);
}
TyAjax_action('ty_ajax_profile', 'profile');
TyAjax_action('ty_ajax_nopriv_profile', 'profile');

// 注册 AJAX 动作
function ty_web_agent() {
    // 获取浏览器 User Agent
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : 'Unknown';

    // 返回数据
    TyAjax_send_success($user_agent);
}
TyAjax_action('ty_ajax_ty_web_agent', 'ty_web_agent');
TyAjax_action('ty_ajax_nopriv_ty_web_agent', 'ty_web_agent');

// 初始化Ajax
TyAjax_Core::init();
```

#### 前端调用

``` html
<button class="ty-ajax-submit" form-action="profile">获取用户信息</button>
<button class="ty-ajax-submit" form-action="ty_web_agent">获取浏览器信息</button>
```

##### 或者使用自定义表单
``` html
<form>
    <input type="hidden" name="action" value="profile">
    <button type="button" class="ty-ajax-submit">提交</button>
</form>
```

#### 注意事项

 - 必须使用 TyAjax_action 函数注册 AJAX 处理函数：
``` php
TyAjax_action('ty_ajax_[action_name]', 'your_function'); // 需要登录
TyAjax_action('ty_ajax_nopriv_[action_name]', 'your_function'); // 不需要登录
```

 - 必须在最后调用 `TyAjax_Core::init()` 以初始化功能：
``` php
TyAjax_Core::init();
```

 - 前端按钮需要添加 ty-ajax-submit 类，并通过 form-action 属性指定 action 名称
 - 确保 jQuery 已加载（框架会通过 bootcdn 自动加载最新版 jQuery）
 - 如果需要自定义样式，可以覆盖默认的 message.css 文件

#### 响应格式

所有 AJAX 请求将返回 JSON 格式数据，包含以下字段：

``` json
{
    "error": 0, // 0表示成功，1表示错误
    "msg": "操作成功", // 提示消息
    "ys": "", // 消息样式 (success, danger等)
    "data": null // 返回的数据
}
```

#### 高级用法

##### 自定义回调

``` php
TyAjax($('button'), {}, function(response) {
    // 处理成功响应
    console.log(response.data);
}, '正在处理...');
```

##### 禁用加载提示

``` php
TyAjax($('button'), {}, null, 'stop');
```

##### 表单序列化

框架会自动处理表单数据，支持数组形式的表单字段。

### REST API

一个简单的 REST API，你可以使用它来获取一些数据。
> `API`路由及状态在`functions.php`中定义。 
>

全局参数

`page` 控制当前页码，默认为`1`
`pageSize` 控制每页显示条数，默认为`10`
`format` 控制返回格式，支持`html`&`markdown`
`excerptLength` 控制文章摘要长度，默认为`200`

| 调用  |                路由                | 其他参数&路由 |         描述         |
| :---: | :--------------------------------: | :-----------: | :------------------: |
|  Get  |                /API                |     null      |     获取网站信息     |
|  Get  |             /API/posts             |     null      |     获取文章列表     |
|  Get  |             /API/pages             |     null      |     获取页面列表     |
|  Get  |        /API/search/{string}        |    string     |     搜索文章列表     |
|  Get  |       /API/category/{string}       |   mid, slug   |     获取分类列表     |
|  Get  |         /API/tag/{string}          |   mid, slug   |     获取标签列表     |
|  Get  |       /API/content/{string}        |   cid, slug   |     获取内容数据     |
|  Get  |          /API/attachments          |     null      |     获取附件列表     |
|  Get  |           /API/comments            |     post      |     获取评论列表     |
|  Get  |    /API/comments/post/{string}     |    string     |   获取文章评论列表   |
|  Get  |     /API/fields/{name}/{value}     |    string     |   获取字段文章列表   |
|  Get  | /API/advancedFields/{name}/{value} |    string     | 获取高级字段查询列表 |
|  Get  |           /API/options/            |     null      |    获取设置项列表    |
|  Get  |    /API/options/{name}/{value}     |    string     |    获取设置项详情    |
|  Get  |         /API/themeOptions          |     null      |  获取主题设置项列表  |
|  Get  |      /API/themeOptions/{name}      |    string     |  获取主题设置项详情  |

### 字段查询
> TTDF内置了字段查询文章列表功能

#### 普通查询  

普通字段查询文章

```bash
GET /API/fields/{name}/{value}
```

#### 高级查询

##### 复杂查询​​使用 JSON

```bash
GET /api/advancedFields?conditions=[{"name":"color","value":"red"},{"name":"price","operator":">=","value":100}]
```

##### 模糊搜索​

```bash
GET /api/advancedFields/title/%重要%?operator=LIKE
```

#### 查询运算符与值类型规范

##### 运算符对照表

| 运算符 | 名称     | 描述           | 使用示例            |
| ------ | -------- | -------------- | ------------------- |
| =      | 等于     | 完全匹配字段值 | `color=red`         |
| !=     | 不等于   | 排除指定值     | `color!=blue`       |
| >      | 大于     | 数值比较       | `price>100`         |
| <      | 小于     | 数值比较       | `price<200`         |
| >=     | 大于等于 | 数值比较       | `price>=100`        |
| <=     | 小于等于 | 数值比较       | `price<=200`        |
| LIKE   | 模糊匹配 | 支持通配符%    | `title LIKE %重要%` |

##### 值类型定义

| 类型  | 说明               | 典型应用场景      | 示例             |
| ----- | ------------------ | ----------------- | ---------------- |
| str   | 字符串（默认类型） | 文本字段精确匹配  | `category=tech`  |
| int   | 整型数字           | ID/数量等数值比较 | `views>1000`     |
| float | 浮点数字           | 价格等精确数值    | `price<=19.99`   |
| text  | 长文本             | 内容全文检索      | `content=查询词` |

#### 注意事项

 - 字段名和值区分大小写
 - 特殊字符需要进行 URL 编码

### 钩子
> TTDF默认有三个钩子可以挂，分别为`load_head`&`load_foot`&`load_code`

#### 使用方法

注意，`load_head`&`load_foot`为挂载到页面模板，`load_code`为functions代码

在页面 如index.php写
```php
<?php 
TTDF_Hook::add_action('load_foot', function () {
?>
<script type="text/javascript">console.log('TTDF NB')</script>
<?php
});
```

f12打开控制台就会看到打印的`TTDF NB`，挂钩的内容仅在当前页面生效。
也就是说写到`index.php`只有首页才会输出`TTDF NB`，其他页面则没有。