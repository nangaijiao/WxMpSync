<?php

namespace weapp\WxMpSync\logic;

use think\Db;
use weapp\WxMpSync\model\WxMpSyncModel;
use weapp\WxMpSync\service\WxApiService;

class SyncLogic
{
    private $model;

    public function __construct()
    {
        $this->model = new WxMpSyncModel();
    }

    public function autoSync()
    {
        $config = $this->model->getConfig();
        if (empty($config['auto_sync'])) {
            return ['code' => 0, 'msg' => '自动同步未开启'];
        }

        return $this->syncBatch((int) $config['sync_limit']);
    }

    public function syncBatch($limit = 10)
    {
        $config = $this->model->getConfig();
        if (empty($config['appid']) || empty($config['appsecret'])) {
            return ['code' => 0, 'msg' => '请先配置 AppID 与 AppSecret'];
        }

        $api = new WxApiService($config['appid'], $config['appsecret']);
        $token = $api->getAccessToken();

        $list = $this->getNeedSyncArticles((int) $limit, (int) $config['last_sync_at']);
        $success = 0;
        $failed = 0;

        foreach ($list as $row) {
            try {
                if ($this->model->hasSynced($row['aid'])) {
                    continue;
                }

                $draftResp = $this->syncSingle($row, $config, $api, $token);
                $this->model->addLog([
                    'aid' => (int) $row['aid'],
                    'title' => (string) $row['title'],
                    'status' => 1,
                    'msg' => '同步成功',
                    'wechat_media_id' => isset($draftResp['media_id']) ? (string) $draftResp['media_id'] : '',
                    'response' => json_encode($draftResp, JSON_UNESCAPED_UNICODE),
                ]);
                $success++;
            } catch (\Throwable $e) {
                $this->model->addLog([
                    'aid' => (int) $row['aid'],
                    'title' => (string) $row['title'],
                    'status' => 0,
                    'msg' => $e->getMessage(),
                    'wechat_media_id' => '',
                    'response' => '',
                ]);
                $failed++;
            }
        }

        $this->model->saveConfig(['last_sync_at' => getTime()]);
        return [
            'code' => 1,
            'msg' => "同步完成，成功 {$success} 篇，失败 {$failed} 篇",
            'data' => ['success' => $success, 'failed' => $failed],
        ];
    }

    private function getNeedSyncArticles($limit, $lastSyncAt)
    {
        $query = Db::name('archives')->alias('a')
            ->join('article_content c', 'c.aid = a.aid', 'LEFT')
            ->field('a.aid,a.title,a.litpic,a.seo_description,a.add_time,c.content')
            ->where('a.is_del', 0)
            ->where('a.arcrank', 0)
            ->order('a.aid desc')
            ->limit($limit);

        if ($lastSyncAt > 0) {
            $query->where('a.add_time', '>', $lastSyncAt);
        }

        return $query->select();
    }

    private function syncSingle(array $row, array $config, WxApiService $api, $token)
    {
        $siteDomain = rtrim((string) $config['site_domain'], '/');
        $coverPath = $this->prepareLocalFile($row['litpic'], $siteDomain);
        $coverResp = $api->uploadCover($token, $coverPath);

        $content = (string) $row['content'];
        $content = $this->replaceContentImages($content, $siteDomain, $api, $token);

        $article = [
            'title' => (string) $row['title'],
            'author' => !empty($config['default_author']) ? (string) $config['default_author'] : 'EYOUCMS',
            'digest' => (string) $row['seo_description'],
            'content' => $content,
            'thumb_media_id' => (string) $coverResp['media_id'],
            'show_cover_pic' => 1,
            'need_open_comment' => 0,
            'only_fans_can_comment' => 0,
        ];

        return $api->createDraft($token, $article);
    }

    private function replaceContentImages($content, $siteDomain, WxApiService $api, $token)
    {
        if (!preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches)) {
            return $content;
        }

        $map = [];
        foreach ($matches[1] as $src) {
            if (isset($map[$src])) {
                continue;
            }

            $local = $this->prepareLocalFile($src, $siteDomain);
            $wxUrl = $api->uploadContentImage($token, $local);
            $map[$src] = $wxUrl;
        }

        foreach ($map as $origin => $wxUrl) {
            $content = str_replace($origin, $wxUrl, $content);
        }

        return $content;
    }

    private function prepareLocalFile($path, $siteDomain)
    {
        $path = trim((string) $path);
        if ($path === '') {
            throw new \RuntimeException('图片地址为空');
        }

        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) {
            return $this->downloadRemoteFile($path);
        }

        $root = rtrim(ROOT_PATH, '/');
        $local = $root . '/' . ltrim($path, '/');
        if (is_file($local)) {
            return $local;
        }

        if ($siteDomain !== '') {
            $remote = $siteDomain . '/' . ltrim($path, '/');
            return $this->downloadRemoteFile($remote);
        }

        throw new \RuntimeException('图片不存在：' . $path);
    }

    private function downloadRemoteFile($url)
    {
        $data = @file_get_contents($url);
        if ($data === false) {
            throw new \RuntimeException('远程图片下载失败：' . $url);
        }

        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if ($ext === '') {
            $ext = 'jpg';
        }

        $tmp = RUNTIME_PATH . 'wxmpsync_' . md5($url . microtime(true)) . '.' . $ext;
        file_put_contents($tmp, $data);
        return $tmp;
    }
}
