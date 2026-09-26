<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Taxonomies
    |--------------------------------------------------------------------------
    |
    | Each key is a taxonomy handle, stored in `terms.taxonomy`. `tags` and
    | `categories` are examples: remove them or add your own.
    |
    |   label, label_plural    Human names.
    |   hierarchical           Whether terms can have a parent.
    |   max_terms_per_model    Terms a model may hold in it; null for no limit.
    |   required               Validation hint for your forms.
    |   sort                   'name', 'sort_order' or 'created_at'.
    |   models                 Model classes allowed to use it; null for any.
    |   scope                  'tenant' (default): each scope has its own terms.
    |                          'global': one catalog shared by every scope.
    |   scope_model            The scope model class, as a hint, for tenant ones.
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
    |
    | Rename them before running the migrations if they clash with yours.
    |
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
    | The stable identifier of a term, generated from its name when it is saved
    | without one. Unique within its taxonomy and scope.
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
    |
    | Keep `terms.terms_count` updated as models are tagged and untagged.
    |
    */

    'cache_counts' => true,

];
