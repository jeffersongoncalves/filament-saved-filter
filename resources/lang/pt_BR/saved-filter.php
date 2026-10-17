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
        'revert' => [
            'label' => 'Reverter alterações em ":name"',
        ],
        'manage' => [
            'label' => 'Gerenciar visualizações',
            'description' => 'Renomeie, reordene, defina a padrão ou exclua as visualizações que não usa mais.',
            'submit' => 'Salvar alterações',
            'notification' => 'Visualizações atualizadas',
        ],
        'email' => ['label' => 'Enviar visualização por email', 'notification' => 'O relatório está a caminho da sua caixa de entrada.'],
        'report' => ['label' => 'Relatório por email', 'description' => 'Receba esta visualização por email de forma agendada. Só você recebe.', 'notification' => 'Relatório por email salvo'],
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
        'report_active' => 'Enviar esta visualização para mim por email',
        'frequency' => 'Frequência',
        'day' => 'Dia',
        'time' => 'Horário',
        'format' => 'Formato',
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
        'modified' => 'Modificada',
    ],
    'frequencies' => ['daily' => 'Diário', 'weekly' => 'Semanal', 'monthly' => 'Mensal'],
    'tabs' => [
        'all' => 'Todas',
    ],
];
