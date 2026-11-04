<?php

return [
    'label' => 'Pack',
    'plural_label' => 'Packs',
    'untitled' => 'Pack sin productos',
    'cannot_publish' => 'El pack no se puede publicar hasta corregir estos errores:',
    'fields' => [
        'shown_here' => 'Sale en esta ficha',
        'title' => 'Título',
        'title_help' => 'Opcional. Se muestra encima del pack en la ficha.',
        'status' => 'Estado',
        'items' => 'Productos',
        'product' => 'Producto',
        'quantity' => 'Cantidad base',
        'show_on_product' => 'Mostrar en su ficha',
        'errors' => 'Errores',
        'updated_at' => 'Actualizado',
    ],
    'status' => [
        'published' => 'Publicado',
        'draft' => 'Borrador',
    ],
    'actions' => [
        'create_with_product' => 'Crear pack con este producto',
        'add_item' => 'Añadir producto',
    ],
    'pages' => [
        'edit' => 'Pack',
        'options' => 'Opciones y enlaces',
    ],
    'options' => [
        'deleted_product' => 'Producto borrado: sus opciones se quedan como están.',
        'stale' => 'El pack ha cambiado desde que abriste esta página. Recárgala para ver los cambios; no se ha guardado nada.',
        'no_options' => 'Sin opciones.',
        'free' => 'Libre',
        'fixed' => 'Fija',
        'link' => 'Enlace :letter',
        'value' => 'Valor',
        'propose' => 'Proponer parejas por nombre',
        'add_row' => 'Añadir pareja',
        'proposed' => '{1} :count pareja propuesta: revísala y guarda.|[2,*] :count parejas propuestas: revísalas y guarda.',
        'proposed_none' => 'No hay valores que se llamen igual para emparejar.',
        'errors' => 'Este pack tiene errores:',
        'errors_published' => 'Este pack está publicado con errores: puede verse incompleto en la ficha.',
        'save_rules' => 'Guardar opciones',
        'save_links' => 'Guardar enlaces',
    ],
];
