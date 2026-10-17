<?php

return [
    'actions' => [
        'group' => 'Vistas',
        'save' => ['label' => 'Guardar vista atual', 'notification' => 'Vista guardada'],
        'update' => ['label' => 'Atualizar ":name" com a vista atual', 'notification' => 'Vista atualizada'],
        'reset' => ['label' => 'Limpar vista'],
        'manage' => ['label' => 'Gerir vistas', 'notification' => 'Vistas atualizadas'],
        'export' => ['label' => 'Exportar vistas'],
        'import' => ['label' => 'Importar vistas', 'notification' => 'Vistas importadas: :count', 'invalid' => 'O ficheiro não é uma exportação de vistas válida.'],
    ],
    'fields' => [
        'name' => 'Nome',
        'icon' => 'Ícone',
        'color' => 'Cor',
        'is_default' => 'Abrir por predefinição',
        'is_public' => 'Partilhar com a minha equipa',
        'as_preset' => 'Guardar como predefinição bloqueada para todos',
        'file' => 'Ficheiro JSON',
    ],
    'colors' => ['primary' => 'Primária', 'gray' => 'Cinzento', 'info' => 'Informação', 'success' => 'Sucesso', 'warning' => 'Aviso', 'danger' => 'Perigo'],
    'tabs' => ['all' => 'Todas'],
];
