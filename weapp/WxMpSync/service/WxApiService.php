<?php

namespace weapp\WxMpSync\service;

class WxApiService
{
    private $appid;
    private $appsecret;

    public function __construct($appid, $appsecret)
    {
        $this->appid = trim((string) $appid);
        $this->appsecret = trim((string) $appsecret);
    }

    public function getAccessToken()
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->appid . '&secret=' . $this->appsecret;
        $resp = $this->request($url, [], 'GET');

        if (empty($resp['access_token'])) {
            $msg = isset($resp['errmsg']) ? $resp['errmsg'] : 'access_token 获取失败';
            throw new \RuntimeException($msg);
        }

        return $resp['access_token'];
    }

    public function uploadCover($token, $filePath)
    {
        return $this->uploadMaterial($token, $filePath, 'image');
    }

    public function uploadMaterial($token, $filePath, $type = 'image')
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/material/add_material?access_token=' . $token . '&type=' . $type;
        $post = ['media' => new \CURLFile($filePath)];
        $resp = $this->request($url, $post, 'POST', true);

        if (empty($resp['media_id'])) {
            $msg = isset($resp['errmsg']) ? $resp['errmsg'] : '素材上传失败';
            throw new \RuntimeException($msg);
        }

        return $resp;
    }

    public function uploadContentImage($token, $filePath)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token=' . $token;
        $post = ['media' => new \CURLFile($filePath)];
        $resp = $this->request($url, $post, 'POST', true);

        if (empty($resp['url'])) {
            $msg = isset($resp['errmsg']) ? $resp['errmsg'] : '正文图片上传失败';
            throw new \RuntimeException($msg);
        }

        return $resp['url'];
    }

    public function createDraft($token, array $article)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/draft/add?access_token=' . $token;
        $payload = [
            'articles' => [$article],
        ];

        $resp = $this->request($url, json_encode($payload, JSON_UNESCAPED_UNICODE), 'POST', false, ['Content-Type: application/json']);
        if (isset($resp['errcode']) && (int) $resp['errcode'] !== 0) {
            $msg = isset($resp['errmsg']) ? $resp['errmsg'] : '草稿创建失败';
            throw new \RuntimeException($msg);
        }

        return $resp;
    }

    private function request($url, $data = [], $method = 'GET', $isMultipart = false, array $headers = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            if (!$isMultipart && !empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('微信接口请求失败：' . $err);
        }

        curl_close($ch);
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new \RuntimeException('微信接口返回解析失败：' . $raw);
        }

        return $json;
    }
}
