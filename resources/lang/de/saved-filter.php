<?php

return [
    'actions' => [
        'group' => 'Ansichten',
        'save' => ['label' => 'Aktuelle Ansicht speichern', 'notification' => 'Ansicht gespeichert'],
        'update' => ['label' => '„:name“ mit der aktuellen Ansicht aktualisieren', 'notification' => 'Ansicht aktualisiert'],
        'reset' => ['label' => 'Ansicht zurücksetzen'],
        'revert' => ['label' => 'Änderungen an ":name" verwerfen'],
        'manage' => ['label' => 'Ansichten verwalten', 'description' => 'Benennen Sie Ansichten um, ordnen Sie sie neu, legen Sie die Standardansicht fest oder löschen Sie nicht mehr benötigte.', 'submit' => 'Änderungen speichern', 'notification' => 'Ansichten aktualisiert'],
        'export' => ['label' => 'Ansichten exportieren'],
        'import' => ['label' => 'Ansichten importieren', 'notification' => 'Importierte Ansichten: :count', 'invalid' => 'Die Datei ist kein gültiger Ansichten-Export.'],
    ],
    'fields' => [
        'name' => 'Name',
        'icon' => 'Symbol',
        'color' => 'Farbe',
        'is_default' => 'Standardmäßig öffnen',
        'is_public' => 'Mit meinem Team teilen',
        'as_preset' => 'Als gesperrte Vorlage für alle speichern',
        'file' => 'JSON-Datei',
    ],
    'colors' => ['primary' => 'Primär', 'gray' => 'Grau', 'info' => 'Info', 'success' => 'Erfolg', 'warning' => 'Warnung', 'danger' => 'Gefahr'],
    'badges' => ['preset' => 'Vorlage', 'shared' => 'Geteilt', 'modified' => 'Geändert'],
    'tabs' => ['all' => 'Alle'],
];
