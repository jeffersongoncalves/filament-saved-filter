<?php

return [
    'actions' => [
        'group' => 'Vues',
        'save' => ['label' => 'Enregistrer la vue actuelle', 'notification' => 'Vue enregistrée'],
        'update' => ['label' => 'Mettre à jour « :name » avec la vue actuelle', 'notification' => 'Vue mise à jour'],
        'reset' => ['label' => 'Effacer la vue'],
        'manage' => ['label' => 'Gérer les vues', 'description' => 'Renommez, réorganisez, choisissez la vue par défaut ou supprimez les vues que vous n’utilisez plus.', 'submit' => 'Enregistrer les modifications', 'notification' => 'Vues mises à jour'],
        'export' => ['label' => 'Exporter les vues'],
        'import' => ['label' => 'Importer des vues', 'notification' => 'Vues importées : :count', 'invalid' => 'Le fichier n’est pas un export de vues valide.'],
    ],
    'fields' => [
        'name' => 'Nom',
        'icon' => 'Icône',
        'color' => 'Couleur',
        'is_default' => 'Ouvrir par défaut',
        'is_public' => 'Partager avec mon équipe',
        'as_preset' => 'Enregistrer comme préréglage verrouillé pour tous',
        'file' => 'Fichier JSON',
    ],
    'colors' => ['primary' => 'Primaire', 'gray' => 'Gris', 'info' => 'Info', 'success' => 'Succès', 'warning' => 'Avertissement', 'danger' => 'Danger'],
    'badges' => ['preset' => 'Préréglage', 'shared' => 'Partagée'],
    'tabs' => ['all' => 'Toutes'],
];
