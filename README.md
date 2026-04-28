diff --git a/weapp/WxMpSync/README.md b/weapp/WxMpSync/README.md
new file mode 100644
index 0000000000000000000000000000000000000000..1f9a2f8e0e35e753f0e1eb4a211e980c73007f3c
--- /dev/null
+++ b/weapp/WxMpSync/README.md
@@ -0,0 +1,76 @@
+# WxMpSync（EYOUCMS 插件）
+
+将 EYOUCMS 文章自动同步到微信公众号**草稿箱**，不改动核心文件，兼容 PHP 7.2+。
+
+> 本插件的 `config.php` 已按 EYOUCMS 官方插件规范补齐 `code/name/version/min_version/permission/management` 必填项，确保可在后台插件列表正常识别显示。
+
+## 功能
+
+- 读取 `ey_archives` + `ey_article_content` 文章数据：标题、缩略图、摘要、正文。
+- 获取微信公众号 `access_token`。
+- 上传封面图到微信公众号素材库。
+- 上传正文图片到微信图床并替换正文 `<img>` 地址。
+- 创建公众号草稿（Draft）。
+- 同步日志记录（成功/失败、微信返回信息）。
+- 后台配置项：
+  - AppID
+  - AppSecret
+  - 网站域名
+  - 默认作者
+  - 是否自动同步
+  - 是否自动发布（默认仅保留配置，不直接发布）
+
+## 目录结构
+
+```text
+weapp/WxMpSync/
+├── WxMpSync.php
+├── config.php
+├── controller/
+│   ├── Index.php
+│   └── Cron.php
+├── logic/
+│   └── SyncLogic.php
+├── model/
+│   └── WxMpSyncModel.php
+├── service/
+│   └── WxApiService.php
+├── template/
+│   └── index.htm
+├── data/
+│   ├── install.sql
+│   └── uninstall.sql
+├── cron/
+│   └── sync.php
+└── README.md
+```
+
+## 安装步骤
+
+1. 将 `weapp/WxMpSync` 整个目录上传到站点 `weapp/` 目录。
+2. 进入 EYOUCMS 后台 > 插件应用，安装 `WxMpSync`。
+3. 进入插件配置页面，填写 AppID、AppSecret、网站域名等。
+4. 点击“立即同步到草稿箱”进行手动测试。
+
+## 定时任务
+
+### 方式一：调用插件 Cron 控制器（推荐）
+
+在服务器 Crontab 中调用插件执行地址（示例 URL 请按实际后台路由调整）：
+
+```bash
+*/10 * * * * /usr/bin/curl -s "https://your-site.com/index.php?m=admin&c=Weapp&a=execute&sm=WxMpSync|Cron|run"
+```
+
+### 方式二：使用 CLI 脚本包装
+
+```bash
+*/10 * * * * /usr/bin/php /path/to/site/weapp/WxMpSync/cron/sync.php "https://your-site.com/index.php?m=admin&c=Weapp&a=execute&sm=WxMpSync|Cron|run"
+```
+
+## 注意事项
+
+- 微信公众号接口存在频率限制，请控制 `sync_limit`。
+- 封面图和正文图必须可访问（本地路径或可下载的网络地址）。
+- 默认行为是**只入草稿箱**，不会自动群发。
+- 若后续要扩展自动发布，可在 `auto_publish=1` 时追加 `freepublish` 接口流程。
diff --git a/weapp/WxMpSync/WxMpSync.php b/weapp/WxMpSync/WxMpSync.php
new file mode 100644
index 0000000000000000000000000000000000000000..829fc061f15fcb1a47ccbde2c74601a160a383c6
--- /dev/null
+++ b/weapp/WxMpSync/WxMpSync.php
@@ -0,0 +1,42 @@
+<?php
+
+namespace weapp\WxMpSync;
+
+use think\Db;
+use weapp\WxMpSync\logic\SyncLogic;
+
+/**
+ * EYOUCMS 插件：WxMpSync
+ */
+class WxMpSync
+{
+    public $name = 'WxMpSync';
+    public $version = '1.0.0';
+    public $author = 'Codex';
+    public $description = '将 EYOUCMS 文章同步到微信公众号草稿箱';
+
+    /**
+     * 插件安装
+     */
+    public function install()
+    {
+        return true;
+    }
+
+    /**
+     * 插件卸载
+     */
+    public function uninstall()
+    {
+        return true;
+    }
+
+    /**
+     * 计划任务入口（可在系统计划任务中调用）
+     */
+    public function cron()
+    {
+        $logic = new SyncLogic();
+        return $logic->autoSync();
+    }
+}
diff --git a/weapp/WxMpSync/config.php b/weapp/WxMpSync/config.php
new file mode 100644
index 0000000000000000000000000000000000000000..678ed182fdcec4303a101a27630ca6d1d4904e7e
--- /dev/null
+++ b/weapp/WxMpSync/config.php
@@ -0,0 +1,21 @@
+<?php
+
+return [
+    'code' => 'WxMpSync',
+    'name' => '公众号草稿同步',
+    'version' => 'v1.0.1',
+    'min_version' => 'v1.5.0',
+    'author' => 'Codex',
+    'description' => '将EYOUCMS文章自动同步到微信公众号草稿箱（含封面与正文图片处理）',
+    'litpic' => '/weapp/WxMpSync/logo.png',
+    'scene' => 2,
+    'permission' => [
+        'index' => '管理',
+        'syncNow' => '立即同步',
+        'doc' => '使用指南',
+    ],
+    'management' => [
+        'href' => url('WxMpSync/Index/index'),
+        'target' => '_self',
+    ],
+];
diff --git a/weapp/WxMpSync/controller/Cron.php b/weapp/WxMpSync/controller/Cron.php
new file mode 100644
index 0000000000000000000000000000000000000000..f44cdf2e5b1ff8c08e2f4d8b0ec68fa80af7c2e5
--- /dev/null
+++ b/weapp/WxMpSync/controller/Cron.php
@@ -0,0 +1,15 @@
+<?php
+
+namespace weapp\WxMpSync\controller;
+
+use weapp\WxMpSync\logic\SyncLogic;
+
+class Cron
+{
+    public function run()
+    {
+        $logic = new SyncLogic();
+        $res = $logic->autoSync();
+        return json($res);
+    }
+}
diff --git a/weapp/WxMpSync/controller/Index.php b/weapp/WxMpSync/controller/Index.php
new file mode 100644
index 0000000000000000000000000000000000000000..ffff738985348dee91bfd2168d4da8da1028164d
--- /dev/null
+++ b/weapp/WxMpSync/controller/Index.php
@@ -0,0 +1,50 @@
+<?php
+
+namespace weapp\WxMpSync\controller;
+
+use think\Request;
+use weapp\WxMpSync\logic\SyncLogic;
+use weapp\WxMpSync\model\WxMpSyncModel;
+
+class Index
+{
+    private $model;
+
+    public function __construct()
+    {
+        $this->model = new WxMpSyncModel();
+    }
+
+    public function index()
+    {
+        if (Request::instance()->isPost()) {
+            $post = input('post.');
+            $save = [
+                'appid' => trim((string) ($post['appid'] ?? '')),
+                'appsecret' => trim((string) ($post['appsecret'] ?? '')),
+                'site_domain' => rtrim(trim((string) ($post['site_domain'] ?? '')), '/'),
+                'default_author' => trim((string) ($post['default_author'] ?? 'EYOUCMS')),
+                'auto_sync' => !empty($post['auto_sync']) ? 1 : 0,
+                'auto_publish' => !empty($post['auto_publish']) ? 1 : 0,
+                'sync_limit' => max(1, (int) ($post['sync_limit'] ?? 10)),
+            ];
+            $this->model->saveConfig($save);
+            return json(['code' => 1, 'msg' => '配置保存成功']);
+        }
+
+        $config = $this->model->getConfig();
+        $logs = $this->model->getLogs(20);
+
+        return view('index', [
+            'config' => $config,
+            'logs' => $logs,
+        ]);
+    }
+
+    public function syncNow()
+    {
+        $logic = new SyncLogic();
+        $res = $logic->syncBatch(10);
+        return json($res);
+    }
+}
diff --git a/weapp/WxMpSync/cron/sync.php b/weapp/WxMpSync/cron/sync.php
new file mode 100644
index 0000000000000000000000000000000000000000..b2ed931773aa1ad978128dc2e110c27044a72a8e
--- /dev/null
+++ b/weapp/WxMpSync/cron/sync.php
@@ -0,0 +1,30 @@
+<?php
+/**
+ * WxMpSync 定时任务脚本示例
+ *
+ * 用法：
+ * php weapp/WxMpSync/cron/sync.php "https://your-site.com/index.php?m=admin&c=Weapp&a=execute&sm=WxMpSync|Cron|run"
+ */
+
+if (PHP_SAPI !== 'cli') {
+    exit("This script must run in CLI mode.\n");
+}
+
+$url = isset($argv[1]) ? trim($argv[1]) : '';
+if ($url === '') {
+    exit("Missing URL argument.\n");
+}
+
+$ch = curl_init();
+curl_setopt($ch, CURLOPT_URL, $url);
+curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
+curl_setopt($ch, CURLOPT_TIMEOUT, 30);
+$res = curl_exec($ch);
+if ($res === false) {
+    $err = curl_error($ch);
+    curl_close($ch);
+    exit("Request failed: {$err}\n");
+}
+curl_close($ch);
+
+echo $res . PHP_EOL;
diff --git a/weapp/WxMpSync/data/install.sql b/weapp/WxMpSync/data/install.sql
new file mode 100644
index 0000000000000000000000000000000000000000..17cb6d7669ad82ccc97142ef9beecf95c5ac03a7
--- /dev/null
+++ b/weapp/WxMpSync/data/install.sql
@@ -0,0 +1,34 @@
+CREATE TABLE IF NOT EXISTS `#@__weapp_wxmpsync_config` (
+  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
+  `appid` varchar(120) NOT NULL DEFAULT '',
+  `appsecret` varchar(120) NOT NULL DEFAULT '',
+  `site_domain` varchar(255) NOT NULL DEFAULT '',
+  `default_author` varchar(60) NOT NULL DEFAULT 'EYOUCMS',
+  `auto_sync` tinyint(1) unsigned NOT NULL DEFAULT '0',
+  `auto_publish` tinyint(1) unsigned NOT NULL DEFAULT '0',
+  `sync_limit` int(10) unsigned NOT NULL DEFAULT '10',
+  `last_sync_at` int(10) unsigned NOT NULL DEFAULT '0',
+  `created_at` int(10) unsigned NOT NULL DEFAULT '0',
+  `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
+  PRIMARY KEY (`id`)
+) ENGINE=MyISAM DEFAULT CHARSET=utf8;
+
+INSERT INTO `#@__weapp_wxmpsync_config`
+(`id`,`appid`,`appsecret`,`site_domain`,`default_author`,`auto_sync`,`auto_publish`,`sync_limit`,`last_sync_at`,`created_at`,`updated_at`)
+VALUES
+(1,'','','','EYOUCMS',0,0,10,0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP())
+ON DUPLICATE KEY UPDATE `updated_at` = UNIX_TIMESTAMP();
+
+CREATE TABLE IF NOT EXISTS `#@__weapp_wxmpsync_log` (
+  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
+  `aid` int(10) unsigned NOT NULL DEFAULT '0',
+  `title` varchar(255) NOT NULL DEFAULT '',
+  `status` tinyint(1) unsigned NOT NULL DEFAULT '0',
+  `msg` varchar(255) NOT NULL DEFAULT '',
+  `wechat_media_id` varchar(120) NOT NULL DEFAULT '',
+  `response` text,
+  `created_at` int(10) unsigned NOT NULL DEFAULT '0',
+  PRIMARY KEY (`id`),
+  KEY `idx_aid` (`aid`),
+  KEY `idx_created_at` (`created_at`)
+) ENGINE=MyISAM DEFAULT CHARSET=utf8;
diff --git a/weapp/WxMpSync/data/uninstall.sql b/weapp/WxMpSync/data/uninstall.sql
new file mode 100644
index 0000000000000000000000000000000000000000..481ed73dcc241bd547a1367db0a3233ef1878527
--- /dev/null
+++ b/weapp/WxMpSync/data/uninstall.sql
@@ -0,0 +1,2 @@
+DROP TABLE IF EXISTS `#@__weapp_wxmpsync_log`;
+DROP TABLE IF EXISTS `#@__weapp_wxmpsync_config`;
diff --git a/weapp/WxMpSync/logic/SyncLogic.php b/weapp/WxMpSync/logic/SyncLogic.php
new file mode 100644
index 0000000000000000000000000000000000000000..4aa6ade8ed8ba6efd15df597e5fe7dba8c267df3
--- /dev/null
+++ b/weapp/WxMpSync/logic/SyncLogic.php
@@ -0,0 +1,184 @@
+<?php
+
+namespace weapp\WxMpSync\logic;
+
+use think\Db;
+use weapp\WxMpSync\model\WxMpSyncModel;
+use weapp\WxMpSync\service\WxApiService;
+
+class SyncLogic
+{
+    private $model;
+
+    public function __construct()
+    {
+        $this->model = new WxMpSyncModel();
+    }
+
+    public function autoSync()
+    {
+        $config = $this->model->getConfig();
+        if (empty($config['auto_sync'])) {
+            return ['code' => 0, 'msg' => '自动同步未开启'];
+        }
+
+        return $this->syncBatch((int) $config['sync_limit']);
+    }
+
+    public function syncBatch($limit = 10)
+    {
+        $config = $this->model->getConfig();
+        if (empty($config['appid']) || empty($config['appsecret'])) {
+            return ['code' => 0, 'msg' => '请先配置 AppID 与 AppSecret'];
+        }
+
+        $api = new WxApiService($config['appid'], $config['appsecret']);
+        $token = $api->getAccessToken();
+
+        $list = $this->getNeedSyncArticles((int) $limit, (int) $config['last_sync_at']);
+        $success = 0;
+        $failed = 0;
+
+        foreach ($list as $row) {
+            try {
+                if ($this->model->hasSynced($row['aid'])) {
+                    continue;
+                }
+
+                $draftResp = $this->syncSingle($row, $config, $api, $token);
+                $this->model->addLog([
+                    'aid' => (int) $row['aid'],
+                    'title' => (string) $row['title'],
+                    'status' => 1,
+                    'msg' => '同步成功',
+                    'wechat_media_id' => isset($draftResp['media_id']) ? (string) $draftResp['media_id'] : '',
+                    'response' => json_encode($draftResp, JSON_UNESCAPED_UNICODE),
+                ]);
+                $success++;
+            } catch (\Throwable $e) {
+                $this->model->addLog([
+                    'aid' => (int) $row['aid'],
+                    'title' => (string) $row['title'],
+                    'status' => 0,
+                    'msg' => $e->getMessage(),
+                    'wechat_media_id' => '',
+                    'response' => '',
+                ]);
+                $failed++;
+            }
+        }
+
+        $this->model->saveConfig(['last_sync_at' => getTime()]);
+        return [
+            'code' => 1,
+            'msg' => "同步完成，成功 {$success} 篇，失败 {$failed} 篇",
+            'data' => ['success' => $success, 'failed' => $failed],
+        ];
+    }
+
+    private function getNeedSyncArticles($limit, $lastSyncAt)
+    {
+        $query = Db::name('archives')->alias('a')
+            ->join('article_content c', 'c.aid = a.aid', 'LEFT')
+            ->field('a.aid,a.title,a.litpic,a.seo_description,a.add_time,c.content')
+            ->where('a.is_del', 0)
+            ->where('a.arcrank', 0)
+            ->order('a.aid desc')
+            ->limit($limit);
+
+        if ($lastSyncAt > 0) {
+            $query->where('a.add_time', '>', $lastSyncAt);
+        }
+
+        return $query->select();
+    }
+
+    private function syncSingle(array $row, array $config, WxApiService $api, $token)
+    {
+        $siteDomain = rtrim((string) $config['site_domain'], '/');
+        $coverPath = $this->prepareLocalFile($row['litpic'], $siteDomain);
+        $coverResp = $api->uploadCover($token, $coverPath);
+
+        $content = (string) $row['content'];
+        $content = $this->replaceContentImages($content, $siteDomain, $api, $token);
+
+        $article = [
+            'title' => (string) $row['title'],
+            'author' => !empty($config['default_author']) ? (string) $config['default_author'] : 'EYOUCMS',
+            'digest' => (string) $row['seo_description'],
+            'content' => $content,
+            'thumb_media_id' => (string) $coverResp['media_id'],
+            'show_cover_pic' => 1,
+            'need_open_comment' => 0,
+            'only_fans_can_comment' => 0,
+        ];
+
+        return $api->createDraft($token, $article);
+    }
+
+    private function replaceContentImages($content, $siteDomain, WxApiService $api, $token)
+    {
+        if (!preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches)) {
+            return $content;
+        }
+
+        $map = [];
+        foreach ($matches[1] as $src) {
+            if (isset($map[$src])) {
+                continue;
+            }
+
+            $local = $this->prepareLocalFile($src, $siteDomain);
+            $wxUrl = $api->uploadContentImage($token, $local);
+            $map[$src] = $wxUrl;
+        }
+
+        foreach ($map as $origin => $wxUrl) {
+            $content = str_replace($origin, $wxUrl, $content);
+        }
+
+        return $content;
+    }
+
+    private function prepareLocalFile($path, $siteDomain)
+    {
+        $path = trim((string) $path);
+        if ($path === '') {
+            throw new \RuntimeException('图片地址为空');
+        }
+
+        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
+            return $this->downloadRemoteFile($path);
+        }
+
+        $root = rtrim(ROOT_PATH, '/');
+        $local = $root . '/' . ltrim($path, '/');
+        if (is_file($local)) {
+            return $local;
+        }
+
+        if ($siteDomain !== '') {
+            $remote = $siteDomain . '/' . ltrim($path, '/');
+            return $this->downloadRemoteFile($remote);
+        }
+
+        throw new \RuntimeException('图片不存在：' . $path);
+    }
+
+    private function downloadRemoteFile($url)
+    {
+        $data = @file_get_contents($url);
+        if ($data === false) {
+            throw new \RuntimeException('远程图片下载失败：' . $url);
+        }
+
+        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
+        if ($ext === '') {
+            $ext = 'jpg';
+        }
+
+        $tmp = RUNTIME_PATH . 'wxmpsync_' . md5($url . microtime(true)) . '.' . $ext;
+        file_put_contents($tmp, $data);
+        return $tmp;
+    }
+}
diff --git a/weapp/WxMpSync/logo.png b/weapp/WxMpSync/logo.png
new file mode 100644
index 0000000000000000000000000000000000000000..3bf104a75dac3e99e7f032516eb4aac01834da11
GIT binary patch
literal 239
zcmeAS@N?(olHy`uVBq!ia0vp^4j|0I0wfs{c7_7U^`0({Ar*{ouNm?+IS9C3ys5Po
z2y}Jd?rq+_Wg7^XZQJ@-vsvQ6b9p;ImWtWUR`qKFO=H7Dua;sXVs3qt|KPE8#(Ji+
zt+F4lar7U0$b7?wrFD03^e#8QP{-a^H|jkf`MccIy)ikh>(Qp|VShi~eyjCbF80RU
z(DE|^FKnGYXj&P&nB>Yy9P?F6mYeaKkMlO)+ux_zZZm&5s=NH9{nVO&%&XXx&vJQu
R(E<8^QB~AZXeVbA2LRpaWLE$H

literal 0
HcmV?d00001

diff --git a/weapp/WxMpSync/model/WxMpSyncModel.php b/weapp/WxMpSync/model/WxMpSyncModel.php
new file mode 100644
index 0000000000000000000000000000000000000000..8e4c9e43032df79459168f3cdaa69af8c121d465
--- /dev/null
+++ b/weapp/WxMpSync/model/WxMpSyncModel.php
@@ -0,0 +1,65 @@
+<?php
+
+namespace weapp\WxMpSync\model;
+
+use think\Db;
+
+class WxMpSyncModel
+{
+    private $configTable = 'weapp_wxmpsync_config';
+    private $logTable = 'weapp_wxmpsync_log';
+
+    public function getConfig()
+    {
+        $row = Db::name($this->configTable)->where('id', 1)->find();
+        if (empty($row)) {
+            return [
+                'appid' => '',
+                'appsecret' => '',
+                'site_domain' => '',
+                'default_author' => 'EYOUCMS',
+                'auto_sync' => 0,
+                'auto_publish' => 0,
+                'sync_limit' => 10,
+                'last_sync_at' => 0,
+            ];
+        }
+
+        return $row;
+    }
+
+    public function saveConfig(array $data)
+    {
+        $data['updated_at'] = getTime();
+        $exists = Db::name($this->configTable)->where('id', 1)->count();
+        if ($exists) {
+            return Db::name($this->configTable)->where('id', 1)->update($data);
+        }
+
+        $data['id'] = 1;
+        $data['created_at'] = getTime();
+        return Db::name($this->configTable)->insert($data);
+    }
+
+    public function addLog(array $data)
+    {
+        $data['created_at'] = getTime();
+        return Db::name($this->logTable)->insert($data);
+    }
+
+    public function getLogs($limit = 20)
+    {
+        return Db::name($this->logTable)
+            ->order('id desc')
+            ->limit($limit)
+            ->select();
+    }
+
+    public function hasSynced($aid)
+    {
+        return (int) Db::name($this->logTable)
+            ->where('aid', (int) $aid)
+            ->where('status', 1)
+            ->count() > 0;
+    }
+}
diff --git a/weapp/WxMpSync/service/WxApiService.php b/weapp/WxMpSync/service/WxApiService.php
new file mode 100644
index 0000000000000000000000000000000000000000..4312239da580b9030d9dd5c43edcf225c0bd88bd
--- /dev/null
+++ b/weapp/WxMpSync/service/WxApiService.php
@@ -0,0 +1,110 @@
+<?php
+
+namespace weapp\WxMpSync\service;
+
+class WxApiService
+{
+    private $appid;
+    private $appsecret;
+
+    public function __construct($appid, $appsecret)
+    {
+        $this->appid = trim((string) $appid);
+        $this->appsecret = trim((string) $appsecret);
+    }
+
+    public function getAccessToken()
+    {
+        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->appid . '&secret=' . $this->appsecret;
+        $resp = $this->request($url, [], 'GET');
+
+        if (empty($resp['access_token'])) {
+            $msg = isset($resp['errmsg']) ? $resp['errmsg'] : 'access_token 获取失败';
+            throw new \RuntimeException($msg);
+        }
+
+        return $resp['access_token'];
+    }
+
+    public function uploadCover($token, $filePath)
+    {
+        return $this->uploadMaterial($token, $filePath, 'image');
+    }
+
+    public function uploadMaterial($token, $filePath, $type = 'image')
+    {
+        $url = 'https://api.weixin.qq.com/cgi-bin/material/add_material?access_token=' . $token . '&type=' . $type;
+        $post = ['media' => new \CURLFile($filePath)];
+        $resp = $this->request($url, $post, 'POST', true);
+
+        if (empty($resp['media_id'])) {
+            $msg = isset($resp['errmsg']) ? $resp['errmsg'] : '素材上传失败';
+            throw new \RuntimeException($msg);
+        }
+
+        return $resp;
+    }
+
+    public function uploadContentImage($token, $filePath)
+    {
+        $url = 'https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token=' . $token;
+        $post = ['media' => new \CURLFile($filePath)];
+        $resp = $this->request($url, $post, 'POST', true);
+
+        if (empty($resp['url'])) {
+            $msg = isset($resp['errmsg']) ? $resp['errmsg'] : '正文图片上传失败';
+            throw new \RuntimeException($msg);
+        }
+
+        return $resp['url'];
+    }
+
+    public function createDraft($token, array $article)
+    {
+        $url = 'https://api.weixin.qq.com/cgi-bin/draft/add?access_token=' . $token;
+        $payload = [
+            'articles' => [$article],
+        ];
+
+        $resp = $this->request($url, json_encode($payload, JSON_UNESCAPED_UNICODE), 'POST', false, ['Content-Type: application/json']);
+        if (isset($resp['errcode']) && (int) $resp['errcode'] !== 0) {
+            $msg = isset($resp['errmsg']) ? $resp['errmsg'] : '草稿创建失败';
+            throw new \RuntimeException($msg);
+        }
+
+        return $resp;
+    }
+
+    private function request($url, $data = [], $method = 'GET', $isMultipart = false, array $headers = [])
+    {
+        $ch = curl_init();
+        curl_setopt($ch, CURLOPT_URL, $url);
+        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
+        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
+        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
+        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
+
+        if (strtoupper($method) === 'POST') {
+            curl_setopt($ch, CURLOPT_POST, true);
+            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
+            if (!$isMultipart && !empty($headers)) {
+                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
+            }
+        }
+
+        $raw = curl_exec($ch);
+        if ($raw === false) {
+            $err = curl_error($ch);
+            curl_close($ch);
+            throw new \RuntimeException('微信接口请求失败：' . $err);
+        }
+
+        curl_close($ch);
+        $json = json_decode($raw, true);
+        if (!is_array($json)) {
+            throw new \RuntimeException('微信接口返回解析失败：' . $raw);
+        }
+
+        return $json;
+    }
+}
diff --git a/weapp/WxMpSync/template/index.htm b/weapp/WxMpSync/template/index.htm
new file mode 100644
index 0000000000000000000000000000000000000000..fc60b57b057f5d37dba5f76addc3f4219e902fe7
--- /dev/null
+++ b/weapp/WxMpSync/template/index.htm
@@ -0,0 +1,101 @@
+<!DOCTYPE html>
+<html>
+<head>
+    <meta charset="utf-8">
+    <title>WxMpSync 配置</title>
+    <style>
+        body{font-family:Arial;margin:20px;background:#f7f7f7;}
+        .box{background:#fff;padding:20px;border-radius:6px;margin-bottom:20px;}
+        label{display:block;margin:10px 0 4px;}
+        input[type=text],input[type=password],input[type=number]{width:460px;padding:8px;}
+        table{width:100%;border-collapse:collapse;background:#fff;}
+        th,td{border:1px solid #ddd;padding:8px;text-align:left;}
+        th{background:#fafafa;}
+        .btn{padding:8px 14px;border:0;background:#0a58ca;color:#fff;cursor:pointer;border-radius:4px;}
+    </style>
+</head>
+<body>
+<div class="box">
+    <h2>WxMpSync 配置</h2>
+    <form id="cfgForm" method="post">
+        <label>AppID</label>
+        <input type="text" name="appid" value="{$config.appid|default=''}">
+        <label>AppSecret</label>
+        <input type="password" name="appsecret" value="{$config.appsecret|default=''}">
+        <label>网站域名（用于拼接相对图片路径）</label>
+        <input type="text" name="site_domain" placeholder="https://www.example.com" value="{$config.site_domain|default=''}">
+        <label>默认作者</label>
+        <input type="text" name="default_author" value="{$config.default_author|default='EYOUCMS'}">
+        <label>单次同步篇数</label>
+        <input type="number" name="sync_limit" value="{$config.sync_limit|default='10'}">
+        <label><input type="checkbox" name="auto_sync" value="1" {if condition="!empty($config.auto_sync)"}checked{/if}> 自动同步（定时任务触发）</label>
+        <label><input type="checkbox" name="auto_publish" value="1" {if condition="!empty($config.auto_publish)"}checked{/if}> 自动发布（默认关闭，仅保留配置）</label>
+        <p>
+            <button class="btn" type="submit">保存配置</button>
+            <button class="btn" type="button" onclick="syncNow()">立即同步到草稿箱</button>
+        </p>
+    </form>
+</div>
+
+<div class="box">
+    <h2>最近同步日志</h2>
+    <table>
+        <thead>
+        <tr>
+            <th>ID</th>
+            <th>文章ID</th>
+            <th>标题</th>
+            <th>状态</th>
+            <th>信息</th>
+            <th>时间</th>
+        </tr>
+        </thead>
+        <tbody>
+        {volist name="logs" id="log"}
+        <tr>
+            <td>{$log.id}</td>
+            <td>{$log.aid}</td>
+            <td>{$log.title}</td>
+            <td>{if condition="$log.status eq 1"}成功{else/}失败{/if}</td>
+            <td>{$log.msg}</td>
+            <td>{$log.created_at|date='Y-m-d H:i:s',###}</td>
+        </tr>
+        {/volist}
+        </tbody>
+    </table>
+</div>
+
+<script>
+    document.getElementById('cfgForm').addEventListener('submit', function (e) {
+        e.preventDefault();
+        var xhr = new XMLHttpRequest();
+        xhr.open('POST', '', true);
+        xhr.onload = function () {
+            try {
+                var res = JSON.parse(xhr.responseText);
+                alert(res.msg || '保存完成');
+                location.reload();
+            } catch (e) {
+                alert('保存失败：' + xhr.responseText);
+            }
+        };
+        xhr.send(new FormData(this));
+    });
+
+    function syncNow() {
+        var xhr = new XMLHttpRequest();
+        xhr.open('POST', "{:weapp_url('WxMpSync/Index/syncNow')}", true);
+        xhr.onload = function () {
+            try {
+                var res = JSON.parse(xhr.responseText);
+                alert(res.msg || '同步完成');
+                location.reload();
+            } catch (e) {
+                alert('同步失败：' + xhr.responseText);
+            }
+        };
+        xhr.send();
+    }
+</script>
+</body>
+</html>
