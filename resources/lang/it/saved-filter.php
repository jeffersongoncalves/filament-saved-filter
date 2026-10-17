<?php

return [
    'actions' => [
        'group' => 'Viste',
        'save' => ['label' => 'Salva vista corrente', 'notification' => 'Vista salvata'],
        'update' => ['label' => 'Aggiorna ":name" con la vista corrente', 'notification' => 'Vista aggiornata'],
        'reset' => ['label' => 'Cancella vista'],
        'manage' => ['label' => 'Gestisci viste', 'notification' => 'Viste aggiornate'],
        'export' => ['label' => 'Esporta viste'],
        'import' => ['label' => 'Importa viste', 'notification' => 'Viste importate: :count', 'invalid' => 'Il file non è un\'esportazione di viste valida.'],
    ],
    'fields' => [
        'name' => 'Nome',
        'icon' => 'Icona',
        'color' => 'Colore',
        'is_default' => 'Apri come predefinita',
        'is_public' => 'Condividi con il mio team',
        'as_preset' => 'Salva come preimpostazione bloccata per tutti',
        'file' => 'File JSON',
    ],
    'colors' => ['primary' => 'Primario', 'gray' => 'Grigio', 'info' => 'Info', 'success' => 'Successo', 'warning' => 'Avviso', 'danger' => 'Pericolo'],
    'tabs' => ['all' => 'Tutte'],
];
