<?php

return [
    'actions' => [
        'group' => 'Koʻrinishlar',
        'save' => ['label' => 'Joriy koʻrinishni saqlash', 'notification' => 'Koʻrinish saqlandi'],
        'update' => ['label' => '":name" koʻrinishini joriy koʻrinish bilan yangilash', 'notification' => 'Koʻrinish yangilandi'],
        'reset' => ['label' => 'Koʻrinishni tozalash'],
        'revert' => ['label' => '":name" o\'zgarishlarini bekor qilish'],
        'manage' => ['label' => 'Koʻrinishlarni boshqarish', 'description' => 'Endi foydalanmaydigan koʻrinishlarni qayta nomlang, tartiblang, standartini tanlang yoki oʻchiring.', 'submit' => 'Oʻzgarishlarni saqlash', 'notification' => 'Koʻrinishlar yangilandi'],
        'email' => ['label' => 'Ko\'rinishni e-pochtaga yuborish', 'notification' => 'Hisobot pochtangizga yuborilmoqda.'],
        'report' => ['label' => 'E-pochta hisoboti', 'description' => 'Bu ko\'rinishni jadval bo\'yicha e-pochtada oling. Faqat siz olasiz.', 'notification' => 'E-pochta hisoboti saqlandi'],
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
        'report_active' => 'Bu ko\'rinishni menga e-pochtada yuborish',
        'frequency' => 'Chastota',
        'day' => 'Kun',
        'time' => 'Vaqt',
        'format' => 'Format',
    ],
    'colors' => ['primary' => 'Asosiy', 'gray' => 'Kulrang', 'info' => 'Maʼlumot', 'success' => 'Muvaffaqiyat', 'warning' => 'Ogohlantirish', 'danger' => 'Xavf'],
    'badges' => ['preset' => 'Andoza', 'shared' => 'Ulashilgan', 'modified' => 'O\'zgartirilgan'],
    'frequencies' => ['daily' => 'Har kuni', 'weekly' => 'Har hafta', 'monthly' => 'Har oy'],
    'tabs' => ['all' => 'Hammasi'],
];
