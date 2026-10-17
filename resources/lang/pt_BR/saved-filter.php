<?php

return [
    'actions' => [
        'group' => 'Visualizações',
        'save' => [
            'label' => 'Salvar visualização atual',
            'notification' => 'Visualização salva',
        ],
        'update' => [
            'label' => 'Atualizar ":name" com a visualização atual',
            'notification' => 'Visualização atualizada',
        ],
        'reset' => [
            'label' => 'Limpar visualização',
        ],
        'manage' => [
            'label' => 'Gerenciar visualizações',
            'description' => 'Renomeie, reordene, defina a padrão ou exclua as visualizações que não usa mais.',
            'submit' => 'Salvar alterações',
            'notification' => 'Visualizações atualizadas',
        ],
        'export' => [
            'label' => 'Exportar visualizações',
        ],
        'import' => [
            'label' => 'Importar visualizações',
            'notification' => 'Visualizações importadas: :count',
            'invalid' => 'O arquivo não é uma exportação de visualizações válida.',
        ],
    ],
    'fields' => [
        'name' => 'Nome',
        'icon' => 'Ícone',
        'color' => 'Cor',
        'is_default' => 'Abrir por padrão',
        'is_public' => 'Compartilhar com minha equipe',
        'as_preset' => 'Salvar como predefinição bloqueada para todos',
        'file' => 'Arquivo JSON',
    ],
    'colors' => [
        'primary' => 'Primária',
        'gray' => 'Cinza',
        'info' => 'Informação',
        'success' => 'Sucesso',
        'warning' => 'Aviso',
        'danger' => 'Perigo',
    ],
    'badges' => [
        'preset' => 'Predefinição',
        'shared' => 'Compartilhada',
    ],
    'tabs' => [
        'all' => 'Todas',
    ],
];
