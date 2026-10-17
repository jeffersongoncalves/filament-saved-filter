<?php

return [
    'actions' => [
        'group' => 'نماها',
        'save' => ['label' => 'ذخیرهٔ نمای فعلی', 'notification' => 'نما ذخیره شد'],
        'update' => ['label' => 'به‌روزرسانی «:name» با نمای فعلی', 'notification' => 'نما به‌روزرسانی شد'],
        'reset' => ['label' => 'پاک کردن نما'],
        'manage' => ['label' => 'مدیریت نماها', 'notification' => 'نماها به‌روزرسانی شدند'],
        'export' => ['label' => 'خروجی گرفتن از نماها'],
        'import' => ['label' => 'وارد کردن نماها', 'notification' => 'نماهای واردشده: :count', 'invalid' => 'فایل یک خروجی معتبر از نماها نیست.'],
    ],
    'fields' => [
        'name' => 'نام',
        'icon' => 'نماد',
        'color' => 'رنگ',
        'is_default' => 'باز شدن به‌صورت پیش‌فرض',
        'is_public' => 'اشتراک با تیم من',
        'as_preset' => 'ذخیره به‌عنوان پیش‌تنظیم قفل‌شده برای همه',
        'file' => 'فایل JSON',
    ],
    'colors' => ['primary' => 'اصلی', 'gray' => 'خاکستری', 'info' => 'اطلاعات', 'success' => 'موفقیت', 'warning' => 'هشدار', 'danger' => 'خطر'],
    'tabs' => ['all' => 'همه'],
];
