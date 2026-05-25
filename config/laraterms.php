<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Taxonomies
    |--------------------------------------------------------------------------
    |
    | Cada key es un handle de taxonomía (string identifier almacenado en
    | `terms.taxonomy`). El paquete ships con `tags` y `categories` como
    | ejemplos — bórralos si no los usas o añade los tuyos.
    |
    | Opciones por taxonomía:
    |
    |   label / label_plural   Human labels.
    |   hierarchical           (bool) Terms pueden tener parent (árbol).
    |   max_terms_per_model    (int|null) Máx. terms por modelo. Null = unlimited.
    |   required               (bool) Hint de validación.
    |   sort                   'name' | 'sort_order' | 'created_at'.
    |   models                 (array|null) Restringe qué modelos usan esta
    |                          taxonomía. Null = cualquier modelo con HasTerms.
    |
    |   scope                  'tenant' (default) | 'global'.
    |                          - tenant: cada scope tiene sus propios terms.
    |                          - global: terms del sistema (scope_type='', scope_id=0).
    |   scope_model            FQCN del modelo Scope cuando scope=tenant (hint).
    |
    */

    'taxonomies' => [

        'tags' => [
            'label'               => 'Tag',
            'label_plural'        => 'Tags',
            'hierarchical'        => false,
            'max_terms_per_model' => null,
            'required'            => false,
            'sort'                => 'name',
            'models'              => null,
            'scope'               => 'tenant',
            'scope_model'         => null,
        ],

        'categories' => [
            'label'               => 'Category',
            'label_plural'        => 'Categories',
            'hierarchical'        => true,
            'max_terms_per_model' => 1,
            'required'            => false,
            'sort'                => 'sort_order',
            'models'              => null,
            'scope'               => 'tenant',
            'scope_model'         => null,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    */

    'tables' => [
        'terms'      => 'terms',
        'termables'  => 'termables',
    ],

    /*
    |--------------------------------------------------------------------------
    | Handle
    |--------------------------------------------------------------------------
    |
    | El `handle` es el identificador estable del término (lo que antes era el
    | "slug"). Se genera automáticamente a partir del `name` plain al guardar
    | si no se proporciona. Único por (scope_type, scope_id, taxonomy).
    |
    */

    'handle' => [
        'locale'                => 'en',
        'separator'             => '-',
        'unique_within_scope'   => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Counts cache
    |--------------------------------------------------------------------------
    */

    'cache_counts' => true,

];
