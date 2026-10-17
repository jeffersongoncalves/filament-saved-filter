<?php

return [
    'actions' => [
        'group' => 'Görünüşlər',
        'save' => ['label' => 'Cari görünüşü yadda saxla', 'notification' => 'Görünüş yadda saxlanıldı'],
        'update' => ['label' => '":name" görünüşünü cari görünüşlə yenilə', 'notification' => 'Görünüş yeniləndi'],
        'reset' => ['label' => 'Görünüşü təmizlə'],
        'revert' => ['label' => '":name" dəyişikliklərini geri qaytar'],
        'manage' => ['label' => 'Görünüşləri idarə et', 'description' => 'Artıq istifadə etmədiyiniz görünüşlərin adını dəyişin, sıralayın, standartı seçin və ya silin.', 'submit' => 'Dəyişiklikləri yadda saxla', 'notification' => 'Görünüşlər yeniləndi'],
        'email' => ['label' => 'Görünüşü e-poçtla göndər', 'notification' => 'Hesabat e-poçtunuza göndərilir.'],
        'report' => ['label' => 'E-poçt hesabatı', 'description' => 'Bu görünüşü cədvəl üzrə e-poçtla alın. Yalnız siz alırsınız.', 'notification' => 'E-poçt hesabatı saxlanıldı'],
        'export' => ['label' => 'Görünüşləri ixrac et'],
        'import' => ['label' => 'Görünüşləri idxal et', 'notification' => 'İdxal edilən görünüşlər: :count', 'invalid' => 'Fayl etibarlı görünüş ixracı deyil.'],
    ],
    'fields' => [
        'name' => 'Ad',
        'icon' => 'İkon',
        'color' => 'Rəng',
        'is_default' => 'Standart olaraq aç',
        'is_public' => 'Komandamla paylaş',
        'as_preset' => 'Hamı üçün kilidli şablon kimi yadda saxla',
        'file' => 'JSON faylı',
        'report_active' => 'Bu görünüşü mənə e-poçtla göndər',
        'frequency' => 'Tezlik',
        'day' => 'Gün',
        'time' => 'Saat',
        'format' => 'Format',
    ],
    'colors' => ['primary' => 'Əsas', 'gray' => 'Boz', 'info' => 'Məlumat', 'success' => 'Uğur', 'warning' => 'Xəbərdarlıq', 'danger' => 'Təhlükə'],
    'badges' => ['preset' => 'Şablon', 'shared' => 'Paylaşılan', 'modified' => 'Dəyişdirilib'],
    'frequencies' => ['daily' => 'Gündəlik', 'weekly' => 'Həftəlik', 'monthly' => 'Aylıq'],
    'tabs' => ['all' => 'Hamısı'],
];
