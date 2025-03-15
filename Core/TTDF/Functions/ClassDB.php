<?php

/**
 * DB Class
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class DB {
    private static $instance = null;
    private $db;

    private function __construct() {
        $this->db = Typecho_Db::get();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new DB();
        }
        return self::$instance;
    }

    /**
     * 获取文章字数
     * @param int $cid 文章cid
     * @return int
     */
    public function getArticleText($cid) {
        $rs = $this->db->fetchRow($this->db->select('table.contents.text')
            ->from('table.contents')
            ->where('table.contents.cid = ?', $cid)
            ->order('table.contents.cid', Typecho_Db::SORT_ASC)
            ->limit(1));
        return $rs['text'] ?? '';
    }
}