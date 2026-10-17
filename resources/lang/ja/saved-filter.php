<?php

return [
    'actions' => [
        'group' => 'ビュー',
        'save' => ['label' => '現在のビューを保存', 'notification' => 'ビューを保存しました'],
        'update' => ['label' => '「:name」を現在のビューで更新', 'notification' => 'ビューを更新しました'],
        'reset' => ['label' => 'ビューをクリア'],
        'manage' => ['label' => 'ビューを管理', 'notification' => 'ビューを更新しました'],
        'export' => ['label' => 'ビューをエクスポート'],
        'import' => ['label' => 'ビューをインポート', 'notification' => 'インポートしたビュー: :count', 'invalid' => 'ファイルは有効なビューのエクスポートではありません。'],
    ],
    'fields' => [
        'name' => '名前',
        'icon' => 'アイコン',
        'color' => '色',
        'is_default' => 'デフォルトで開く',
        'is_public' => 'チームと共有',
        'as_preset' => '全員向けのロックされたプリセットとして保存',
        'file' => 'JSONファイル',
    ],
    'colors' => ['primary' => 'プライマリ', 'gray' => 'グレー', 'info' => '情報', 'success' => '成功', 'warning' => '警告', 'danger' => '危険'],
    'tabs' => ['all' => 'すべて'],
];
