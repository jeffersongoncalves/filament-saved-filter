<?php

return [
    'actions' => [
        'group' => 'Görünümler',
        'save' => ['label' => 'Geçerli görünümü kaydet', 'notification' => 'Görünüm kaydedildi'],
        'update' => ['label' => '":name" görünümünü geçerli görünümle güncelle', 'notification' => 'Görünüm güncellendi'],
        'reset' => ['label' => 'Görünümü temizle'],
        'revert' => ['label' => '":name" değişikliklerini geri al'],
        'manage' => ['label' => 'Görünümleri yönet', 'description' => 'Artık kullanmadığınız görünümleri yeniden adlandırın, sıralayın, varsayılanı belirleyin veya silin.', 'submit' => 'Değişiklikleri kaydet', 'notification' => 'Görünümler güncellendi'],
        'export' => ['label' => 'Görünümleri dışa aktar'],
        'import' => ['label' => 'Görünümleri içe aktar', 'notification' => 'İçe aktarılan görünümler: :count', 'invalid' => 'Dosya geçerli bir görünüm dışa aktarımı değil.'],
    ],
    'fields' => [
        'name' => 'Ad',
        'icon' => 'Simge',
        'color' => 'Renk',
        'is_default' => 'Varsayılan olarak aç',
        'is_public' => 'Ekibimle paylaş',
        'as_preset' => 'Herkes için kilitli ön ayar olarak kaydet',
        'file' => 'JSON dosyası',
    ],
    'colors' => ['primary' => 'Birincil', 'gray' => 'Gri', 'info' => 'Bilgi', 'success' => 'Başarılı', 'warning' => 'Uyarı', 'danger' => 'Tehlike'],
    'badges' => ['preset' => 'Ön ayar', 'shared' => 'Paylaşılan', 'modified' => 'Değiştirildi'],
    'tabs' => ['all' => 'Tümü'],
];
