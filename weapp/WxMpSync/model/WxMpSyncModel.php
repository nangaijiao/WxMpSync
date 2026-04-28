<?php

namespace weapp\WxMpSync\model;

use think\Db;

class WxMpSyncModel
{
    private $configTable = 'wxmpsync_config';
    private $logTable = 'wxmpsync_log';

    public function getConfig()
    {
        $row = Db::name($this->configTable)->where('id', 1)->find();
        if (empty($row)) {
            return include WEAPP_PATH . 'WxMpSync/config.php';
        }

        return $row;
    }

    public function saveConfig(array $data)
    {
        $data['updated_at'] = getTime();
        $exists = Db::name($this->configTable)->where('id', 1)->count();
        if ($exists) {
            return Db::name($this->configTable)->where('id', 1)->update($data);
        }

        $data['id'] = 1;
        $data['created_at'] = getTime();
        return Db::name($this->configTable)->insert($data);
    }

    public function addLog(array $data)
    {
        $data['created_at'] = getTime();
        return Db::name($this->logTable)->insert($data);
    }

    public function getLogs($limit = 20)
    {
        return Db::name($this->logTable)
            ->order('id desc')
            ->limit($limit)
            ->select();
    }

    public function hasSynced($aid)
    {
        return (int) Db::name($this->logTable)
            ->where('aid', (int) $aid)
            ->where('status', 1)
            ->count() > 0;
    }
}
