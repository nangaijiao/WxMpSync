<?php

namespace weapp\WxMpSync;

use think\Db;
use weapp\WxMpSync\logic\SyncLogic;

/**
 * EYOUCMS 插件：WxMpSync
 */
class WxMpSync
{
    public $name = 'WxMpSync';
    public $version = '1.0.0';
    public $author = 'Codex';
    public $description = '将 EYOUCMS 文章同步到微信公众号草稿箱';

    /**
     * 插件安装
     */
    public function install()
    {
        return true;
    }

    /**
     * 插件卸载
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * 计划任务入口（可在系统计划任务中调用）
     */
    public function cron()
    {
        $logic = new SyncLogic();
        return $logic->autoSync();
    }
}
