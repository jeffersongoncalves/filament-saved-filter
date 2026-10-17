<?php

return [
    'actions' => [
        'group' => 'Widoki',
        'save' => ['label' => 'Zapisz bieżący widok', 'notification' => 'Widok zapisany'],
        'update' => ['label' => 'Zaktualizuj „:name” bieżącym widokiem', 'notification' => 'Widok zaktualizowany'],
        'reset' => ['label' => 'Wyczyść widok'],
        'manage' => ['label' => 'Zarządzaj widokami', 'description' => 'Zmieniaj nazwy, kolejność, ustaw widok domyślny lub usuń widoki, których już nie używasz.', 'submit' => 'Zapisz zmiany', 'notification' => 'Widoki zaktualizowane'],
        'export' => ['label' => 'Eksportuj widoki'],
        'import' => ['label' => 'Importuj widoki', 'notification' => 'Zaimportowane widoki: :count', 'invalid' => 'Plik nie jest prawidłowym eksportem widoków.'],
    ],
    'fields' => [
        'name' => 'Nazwa',
        'icon' => 'Ikona',
        'color' => 'Kolor',
        'is_default' => 'Otwieraj domyślnie',
        'is_public' => 'Udostępnij mojemu zespołowi',
        'as_preset' => 'Zapisz jako zablokowany szablon dla wszystkich',
        'file' => 'Plik JSON',
    ],
    'colors' => ['primary' => 'Podstawowy', 'gray' => 'Szary', 'info' => 'Informacja', 'success' => 'Sukces', 'warning' => 'Ostrzeżenie', 'danger' => 'Niebezpieczeństwo'],
    'badges' => ['preset' => 'Szablon', 'shared' => 'Udostępniony'],
    'tabs' => ['all' => 'Wszystkie'],
];
