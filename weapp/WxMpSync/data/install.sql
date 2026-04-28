CREATE TABLE IF NOT EXISTS `__PREFIX__wxmpsync_config` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `appid` varchar(120) NOT NULL DEFAULT '',
  `appsecret` varchar(120) NOT NULL DEFAULT '',
  `site_domain` varchar(255) NOT NULL DEFAULT '',
  `default_author` varchar(60) NOT NULL DEFAULT 'EYOUCMS',
  `auto_sync` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `auto_publish` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `sync_limit` int(10) unsigned NOT NULL DEFAULT '10',
  `last_sync_at` int(10) unsigned NOT NULL DEFAULT '0',
  `created_at` int(10) unsigned NOT NULL DEFAULT '0',
  `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `__PREFIX__wxmpsync_config`
(`id`,`appid`,`appsecret`,`site_domain`,`default_author`,`auto_sync`,`auto_publish`,`sync_limit`,`last_sync_at`,`created_at`,`updated_at`)
VALUES
(1,'','','','EYOUCMS',0,0,10,0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `updated_at` = UNIX_TIMESTAMP();

CREATE TABLE IF NOT EXISTS `__PREFIX__wxmpsync_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `aid` int(10) unsigned NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `msg` varchar(255) NOT NULL DEFAULT '',
  `wechat_media_id` varchar(120) NOT NULL DEFAULT '',
  `response` text,
  `created_at` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_aid` (`aid`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
