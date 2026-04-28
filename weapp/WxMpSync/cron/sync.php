<?php
/**
 * WxMpSync 定时任务脚本示例
 *
 * 用法：
 * php weapp/WxMpSync/cron/sync.php "https://your-site.com/index.php?m=admin&c=Weapp&a=execute&sm=WxMpSync|Cron|run"
 */

if (PHP_SAPI !== 'cli') {
    exit("This script must run in CLI mode.\n");
}

$url = isset($argv[1]) ? trim($argv[1]) : '';
if ($url === '') {
    exit("Missing URL argument.\n");
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$res = curl_exec($ch);
if ($res === false) {
    $err = curl_error($ch);
    curl_close($ch);
    exit("Request failed: {$err}\n");
}
curl_close($ch);

echo $res . PHP_EOL;
