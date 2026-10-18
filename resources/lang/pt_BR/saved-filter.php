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
        'email' => [
            'label' => 'Enviar visualização por email',
            'notification' => 'O relatório está a caminho da sua caixa de entrada.',
        ],
        'report' => [
            'label' => 'Relatório por email',
            'description' => 'Receba esta visualização por email de forma agendada. Só você recebe.',
            'notification' => 'Relatório por email salvo',
        ],
        'my_reports' => [
            'label' => 'Meus relatórios por email',
            'description' => 'Ative, desative ou remova os relatórios que você recebe por email, de todas as tabelas.',
            'notification' => 'Relatórios atualizados',
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
        'view' => 'Visualização',
        'report_active' => 'Enviar esta visualização para mim por email',
        'frequency' => 'Frequência',
        'day' => 'Dia',
        'time' => 'Horário',
        'timezone' => 'Fuso horário',
        'format' => 'Formato',
        'skip_empty' => 'Não enviar se não houver registros',
        'on_conflict' => 'Quando uma visualização tem o mesmo nome',
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
    'frequencies' => [
        'daily' => 'Diário',
        'weekly' => 'Semanal',
        'monthly' => 'Mensal',
    ],
    'conflicts' => [
        'overwrite' => 'Substituir',
        'skip' => 'Ignorar',
        'copy' => 'Importar como cópia',
    ],
    'tabs' => [
        'all' => 'Todas',
    ],
];
