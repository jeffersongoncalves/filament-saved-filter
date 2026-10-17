<?php

return [
    'actions' => [
        'group' => 'Weergaven',
        'save' => ['label' => 'Huidige weergave opslaan', 'notification' => 'Weergave opgeslagen'],
        'update' => ['label' => '":name" bijwerken met de huidige weergave', 'notification' => 'Weergave bijgewerkt'],
        'reset' => ['label' => 'Weergave wissen'],
        'manage' => ['label' => 'Weergaven beheren', 'notification' => 'Weergaven bijgewerkt'],
        'export' => ['label' => 'Weergaven exporteren'],
        'import' => ['label' => 'Weergaven importeren', 'notification' => 'Weergaven geïmporteerd: :count', 'invalid' => 'Het bestand is geen geldige export van weergaven.'],
    ],
    'fields' => [
        'name' => 'Naam',
        'icon' => 'Pictogram',
        'color' => 'Kleur',
        'is_default' => 'Standaard openen',
        'is_public' => 'Delen met mijn team',
        'as_preset' => 'Opslaan als vergrendelde voorinstelling voor iedereen',
        'file' => 'JSON-bestand',
    ],
    'colors' => ['primary' => 'Primair', 'gray' => 'Grijs', 'info' => 'Info', 'success' => 'Succes', 'warning' => 'Waarschuwing', 'danger' => 'Gevaar'],
    'tabs' => ['all' => 'Alle'],
];
