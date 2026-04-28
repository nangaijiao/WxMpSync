<?php

namespace weapp\WxMpSync\controller;

use think\Db;
use think\Request;
use weapp\WxMpSync\logic\SyncLogic;
use weapp\WxMpSync\model\WxMpSyncModel;

class Index
{
    private $model;

    public function __construct()
    {
        $this->model = new WxMpSyncModel();
    }

    public function index()
    {
        if (Request::instance()->isPost()) {
            $post = input('post.');
            $save = [
                'appid' => trim((string) ($post['appid'] ?? '')),
                'appsecret' => trim((string) ($post['appsecret'] ?? '')),
                'site_domain' => rtrim(trim((string) ($post['site_domain'] ?? '')), '/'),
                'default_author' => trim((string) ($post['default_author'] ?? 'EYOUCMS')),
                'auto_sync' => !empty($post['auto_sync']) ? 1 : 0,
                'auto_publish' => !empty($post['auto_publish']) ? 1 : 0,
                'sync_limit' => max(1, (int) ($post['sync_limit'] ?? 10)),
            ];
            $this->model->saveConfig($save);
            return json(['code' => 1, 'msg' => '配置保存成功']);
        }

        $config = $this->model->getConfig();
        $logs = $this->model->getLogs(20);

        return view('index', [
            'config' => $config,
            'logs' => $logs,
        ]);
    }

    public function syncNow()
    {
        $logic = new SyncLogic();
        $res = $logic->syncBatch(10);
        return json($res);
    }
}
