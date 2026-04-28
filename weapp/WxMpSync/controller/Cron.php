<?php

namespace weapp\WxMpSync\controller;

use weapp\WxMpSync\logic\SyncLogic;

class Cron
{
    public function run()
    {
        $logic = new SyncLogic();
        $res = $logic->autoSync();
        return json($res);
    }
}
