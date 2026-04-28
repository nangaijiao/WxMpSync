<?php

return [
    'code' => 'WxMpSync',
    'name' => '公众号草稿同步',
    'version' => 'v1.0.1',
    'min_version' => 'v1.5.0',
    'author' => 'Codex',
    'description' => '将EYOUCMS文章自动同步到微信公众号草稿箱（含封面与正文图片处理）',
    'litpic' => '',
    'scene' => 2,
    'permission' => [
        'index' => '管理',
        'syncNow' => '立即同步',
        'doc' => '使用指南',
    ],
    'management' => [
        'href' => url('WxMpSync/Index/index'),
        'target' => '_self',
    ],
];
