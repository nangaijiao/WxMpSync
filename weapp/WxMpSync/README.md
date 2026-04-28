# WxMpSync（EYOUCMS 插件）

将 EYOUCMS 文章自动同步到微信公众号**草稿箱**，不改动核心文件，兼容 PHP 7.2+。

## 功能

- 读取 `ey_archives` + `ey_article_content` 文章数据：标题、缩略图、摘要、正文。
- 获取微信公众号 `access_token`。
- 上传封面图到微信公众号素材库。
- 上传正文图片到微信图床并替换正文 `<img>` 地址。
- 创建公众号草稿（Draft）。
- 同步日志记录（成功/失败、微信返回信息）。
- 后台配置项：
  - AppID
  - AppSecret
  - 网站域名
  - 默认作者
  - 是否自动同步
  - 是否自动发布（默认仅保留配置，不直接发布）

## 目录结构

```text
weapp/WxMpSync/
├── WxMpSync.php
├── config.php
├── controller/
│   ├── Index.php
│   └── Cron.php
├── logic/
│   └── SyncLogic.php
├── model/
│   └── WxMpSyncModel.php
├── service/
│   └── WxApiService.php
├── template/
│   └── index.htm
├── data/
│   ├── install.sql
│   └── uninstall.sql
├── cron/
│   └── sync.php
└── README.md
```

## 安装步骤

1. 将 `weapp/WxMpSync` 整个目录上传到站点 `weapp/` 目录。
2. 进入 EYOUCMS 后台 > 插件应用，安装 `WxMpSync`。
3. 进入插件配置页面，填写 AppID、AppSecret、网站域名等。
4. 点击“立即同步到草稿箱”进行手动测试。

## 定时任务

### 方式一：调用插件 Cron 控制器（推荐）

在服务器 Crontab 中调用插件执行地址（示例 URL 请按实际后台路由调整）：

```bash
*/10 * * * * /usr/bin/curl -s "https://your-site.com/index.php?m=admin&c=Weapp&a=execute&sm=WxMpSync|Cron|run"
```

### 方式二：使用 CLI 脚本包装

```bash
*/10 * * * * /usr/bin/php /path/to/site/weapp/WxMpSync/cron/sync.php "https://your-site.com/index.php?m=admin&c=Weapp&a=execute&sm=WxMpSync|Cron|run"
```

## 注意事项

- 微信公众号接口存在频率限制，请控制 `sync_limit`。
- 封面图和正文图必须可访问（本地路径或可下载的网络地址）。
- 默认行为是**只入草稿箱**，不会自动群发。
- 若后续要扩展自动发布，可在 `auto_publish=1` 时追加 `freepublish` 接口流程。
