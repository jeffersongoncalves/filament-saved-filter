<?php

return [
    'actions' => [
        'group' => 'ビュー',
        'save' => ['label' => '現在のビューを保存', 'notification' => 'ビューを保存しました'],
        'update' => ['label' => '「:name」を現在のビューで更新', 'notification' => 'ビューを更新しました'],
        'reset' => ['label' => 'ビューをクリア'],
        'revert' => ['label' => '「:name」の変更を元に戻す'],
        'manage' => ['label' => 'ビューを管理', 'description' => 'ビューの名前変更、並べ替え、デフォルトの設定、使わなくなったビューの削除ができます。', 'submit' => '変更を保存', 'notification' => 'ビューを更新しました'],
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
    'badges' => ['preset' => 'プリセット', 'shared' => '共有', 'modified' => '変更あり'],
    'tabs' => ['all' => 'すべて'],
];
