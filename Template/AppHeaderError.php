<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$TTDF_Error_Page_Title = '404 Not Found';
$TTDF_Error_Page_Description = '404 Not Found';
$TTDF_Error_Page_Keywords = '404, Not Found';
?>
<!doctype html>
<html lang="<?php echo Get::Options('lang', false) ? Get::Options('lang', false) : 'zh-CN' ?>">

<head>
    <meta charset="<?php Get::Options('charset', true) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" />
    <title><?php echo $TTDF_Error_Page_Title; ?></title>
    <meta name="keywords" content="<?php echo $TTDF_Error_Page_Description; ?>" />
    <meta name="description" content="<?php echo $TTDF_Error_Page_Keywords; ?>" />
<?php TTDF_Hook::do_action('load_head', true); ?>
</head>

<body>
    <main id="app">