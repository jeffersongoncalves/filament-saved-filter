<?php

return [
    'actions' => [
        'group' => '视图',
        'save' => ['label' => '保存当前视图', 'notification' => '视图已保存'],
        'update' => ['label' => '用当前视图更新“:name”', 'notification' => '视图已更新'],
        'reset' => ['label' => '清除视图'],
        'revert' => ['label' => '撤销对“:name”的更改'],
        'manage' => ['label' => '管理视图', 'description' => '重命名、排序、设置默认视图，或删除不再使用的视图。', 'submit' => '保存更改', 'notification' => '视图已更新'],
        'export' => ['label' => '导出视图'],
        'import' => ['label' => '导入视图', 'notification' => '已导入视图：:count', 'invalid' => '该文件不是有效的视图导出。'],
    ],
    'fields' => [
        'name' => '名称',
        'icon' => '图标',
        'color' => '颜色',
        'is_default' => '默认打开',
        'is_public' => '与我的团队共享',
        'as_preset' => '保存为对所有人锁定的预设',
        'file' => 'JSON 文件',
    ],
    'colors' => ['primary' => '主要', 'gray' => '灰色', 'info' => '信息', 'success' => '成功', 'warning' => '警告', 'danger' => '危险'],
    'badges' => ['preset' => '预设', 'shared' => '共享', 'modified' => '已修改'],
    'tabs' => ['all' => '全部'],
];
