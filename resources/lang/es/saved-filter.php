<?php

return [
    'actions' => [
        'group' => 'Vistas',
        'save' => ['label' => 'Guardar vista actual', 'notification' => 'Vista guardada'],
        'update' => ['label' => 'Actualizar ":name" con la vista actual', 'notification' => 'Vista actualizada'],
        'reset' => ['label' => 'Limpiar vista'],
        'revert' => ['label' => 'Descartar cambios en ":name"'],
        'manage' => ['label' => 'Gestionar vistas', 'description' => 'Cambia el nombre, reordena, define la predeterminada o elimina las vistas que ya no usas.', 'submit' => 'Guardar cambios', 'notification' => 'Vistas actualizadas'],
        'export' => ['label' => 'Exportar vistas'],
        'import' => ['label' => 'Importar vistas', 'notification' => 'Vistas importadas: :count', 'invalid' => 'El archivo no es una exportación de vistas válida.'],
    ],
    'fields' => [
        'name' => 'Nombre',
        'icon' => 'Icono',
        'color' => 'Color',
        'is_default' => 'Abrir por defecto',
        'is_public' => 'Compartir con mi equipo',
        'as_preset' => 'Guardar como ajuste bloqueado para todos',
        'file' => 'Archivo JSON',
    ],
    'colors' => ['primary' => 'Primario', 'gray' => 'Gris', 'info' => 'Información', 'success' => 'Éxito', 'warning' => 'Advertencia', 'danger' => 'Peligro'],
    'badges' => ['preset' => 'Predefinida', 'shared' => 'Compartida', 'modified' => 'Modificada'],
    'tabs' => ['all' => 'Todas'],
];
