<?php

return [
    'actions' => [
        'group' => 'Koʻrinishlar',
        'save' => ['label' => 'Joriy koʻrinishni saqlash', 'notification' => 'Koʻrinish saqlandi'],
        'update' => ['label' => '":name" koʻrinishini joriy koʻrinish bilan yangilash', 'notification' => 'Koʻrinish yangilandi'],
        'reset' => ['label' => 'Koʻrinishni tozalash'],
        'manage' => ['label' => 'Koʻrinishlarni boshqarish', 'notification' => 'Koʻrinishlar yangilandi'],
        'export' => ['label' => 'Koʻrinishlarni eksport qilish'],
        'import' => ['label' => 'Koʻrinishlarni import qilish', 'notification' => 'Import qilingan koʻrinishlar: :count', 'invalid' => 'Fayl yaroqli koʻrinishlar eksporti emas.'],
    ],
    'fields' => [
        'name' => 'Nomi',
        'icon' => 'Belgi',
        'color' => 'Rang',
        'is_default' => 'Standart boʻyicha ochish',
        'is_public' => 'Jamoam bilan ulashish',
        'as_preset' => 'Hamma uchun qulflangan andoza sifatida saqlash',
        'file' => 'JSON fayl',
    ],
    'colors' => ['primary' => 'Asosiy', 'gray' => 'Kulrang', 'info' => 'Maʼlumot', 'success' => 'Muvaffaqiyat', 'warning' => 'Ogohlantirish', 'danger' => 'Xavf'],
    'tabs' => ['all' => 'Hammasi'],
];
