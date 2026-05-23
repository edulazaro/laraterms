# Laraterms

**Polymorphic taxonomies for Laravel.** Define `tags`, `categories` o cualquier clasificación custom en config. Multi-tenant. Multi-locale. Hierarchical o flat. Spatie-compatible. Búsqueda cross-locale via `search_text` auto-mantenido. Zero opinión de schema más allá de 2 tablas.

## Instalación

```bash
composer require edulazaro/laraterms
php artisan vendor:publish --tag=laraterms-config
php artisan vendor:publish --tag=laraterms-migrations
php artisan migrate
```

## Define tus taxonomías

`config/laraterms.php` ships con `tags` y `categories` como ejemplos. Edita / añade lo que necesites:

```php
'taxonomies' => [
    'tags' => [
        'hierarchical'        => false,
        'max_terms_per_model' => null,
        'scope'               => 'tenant',  // o 'global'
    ],
    'categories' => [
        'hierarchical'        => true,
        'max_terms_per_model' => 1,
        'scope'               => 'tenant',
    ],
    'regions' => [
        'hierarchical' => true,
        'models'       => [\App\Models\Property::class],
    ],
],
```

## Multi-tenant (owner-scoped)

Por defecto las taxonomías son **tenant-scoped** — cada owner (Organización, Workspace, Team) tiene SUS propios términos aislados. Define cómo resolver el owner de cualquier modelo taxable:

```php
// AppServiceProvider::boot()
use EduLazaro\Laraterms\Facades\Laraterms;

Laraterms::resolveOwnerUsing(fn ($model) => $model->organization ?? null);
```

O por modelo:

```php
class Post extends Model
{
    use HasTerms;

    public function termsOwner(): ?\Illuminate\Database\Eloquent\Model
    {
        return $this->organization;
    }
}
```

El owner puede ser un Model, un array `['type' => 'organization', 'id' => 5]`, un value-object `Owner`, o `null` (= global). No requiere morph map registrado — funciona con FQCN como `owner_type`.

Para taxonomías compartidas entre todos los tenants (lenguas, países), pon `scope: 'global'`.

## Make a model taxable

```php
use EduLazaro\Laraterms\Concerns\HasTerms;

class Post extends Model { use HasTerms; }
```

## API

```php
// Attach / sync / detach
$post->attachTerm('Laravel', 'tags');                  // find-or-create
$post->attachTerms(['Laravel', 'PHP'], 'tags');
$post->syncTerms(['Laravel', 'Vue'], 'tags');           // replace en la taxonomía
$post->detachTerm('Laravel', 'tags');
$post->detachAll('tags');

// Read
$post->terms;                                           // all attached
$post->termsIn('tags');                                 // by taxonomy
$post->hasTermsIn('tags');                              // bool

// Query
Post::whereHasTerm('laravel', 'tags')->get();
Post::whereHasAnyTerm(['laravel', 'vue'], 'tags')->get();
Post::whereHasAllTerms(['laravel', 'tutorial'], 'tags')->get();
Post::whereInTaxonomy('categories')->get();
```

## Multi-locale (i18n)

Cada campo translatable tiene **dos columnas**: el canónico (`name`, `description`) y el de traducciones (`name_translations`, `description_translations`). Spatie-compatible (formato `{"en": "...", "es": "..."}`).

```php
// Single-locale — usa solo `name`
Term::create(['name' => 'Laravel', 'taxonomy' => 'tags']);
$term->name;   // "Laravel"

// Multi-locale
Term::create([
    'name'              => 'Tag',                       // fallback canónico
    'name_translations' => ['en' => 'Tag', 'es' => 'Etiqueta'],
    'taxonomy'          => 'tags',
]);
$term->name;   // "Etiqueta" si locale=es, "Tag" si locale=en o fallback
```

El accessor de `name` y `description` resuelve automáticamente: locale activo → fallback locale → columna canónica.

### Integración con Spatie (opt-in, por tu cuenta)

Si quieres la API completa de Spatie sobre los campos `*_translations`:

```php
class Term extends \EduLazaro\Laraterms\Models\Term
{
    use \Spatie\Translatable\HasTranslations;
    protected $translatable = ['name_translations', 'description_translations'];
}
```

Nuestro accessor sobre `name`/`description` sigue funcionando porque lee los atributos crudos.

## Búsqueda cross-locale

`search_text` se mantiene automáticamente en `saving()` concatenando todos los valores de todos los locales. Búsqueda LIKE agnóstica de idioma:

```php
Term::search('impuesto')->get();                        // encuentra aunque el user esté en /en
Term::where('search_text', 'like', '%laravel%')->get();
Term::whereFullText('search_text', 'laravel')->get();   // si tu motor soporta FULLTEXT (default migration lo añade)
```

## Hierarchical

```php
use EduLazaro\Laraterms\Support\TermTree;

$tree = TermTree::for('categories');                    // Collection de roots con children populados (1 query)

foreach (TermTree::flatten($tree) as [$term, $depth]) {
    echo str_repeat('— ', $depth) . $term->name . "\n";
}

$term->ancestors();                                     // Collection<Term> root → parent
$term->breadcrumb(' › ');                               // "Tech › Web › Laravel"
$term->descendantIds();                                 // todos los ids descendientes
```

## Modelo

```php
$term = Term::findOrCreateByName('Laravel', 'tags', $organization);
Term::inTaxonomy('tags')->ordered()->get();
Term::byHandle('laravel', 'tags')->first();
Term::forOwner($org)->inTaxonomy('tags')->get();
Term::forOwnerOrGlobal($org)->inTaxonomy('tags')->get();
$term->refreshCount();
```

## Activar / desactivar (soft hide)

Cada término tiene `is_active` (default `true`). Desactivar = ocultar de pickers de nuevos atachamientos, pero los modelos ya atachados siguen mostrando el badge. Útil para "este tag no se usa más, pero los posts viejos que lo tienen siguen mostrándolo".

```php
$term->deactivate();        // oculta de pickers
$term->activate();          // re-activa
Term::active()->inTaxonomy('tags')->forOwner($org)->get();        // solo activos
Term::inactive()->inTaxonomy('tags')->forOwner($org)->get();      // solo inactivos
```

**NO es soft-delete.** Si quieres SoftDeletes proper (con `withTrashed`, `restore`, etc.), extiende el modelo en tu app:

```php
class Term extends \EduLazaro\Laraterms\Models\Term {
    use \Illuminate\Database\Eloquent\SoftDeletes;
}
// + migration con $table->softDeletes();
```

## Fusionar términos (`mergeInto`)

Para limpieza de duplicados ("teníamos `laravel` y `Laravel Framework`, fusiónalos en `laravel`"):

```php
$dup = Term::byHandle('laravel-framework', 'tags')->first();
$canonical = Term::byHandle('laravel', 'tags')->first();

$dup->mergeInto($canonical, deactivateSource: true);
// 1. Mueve todos los termables de $dup → $canonical (sin duplicar)
// 2. Recalcula terms_count del canonical
// 3. Desactiva $dup (queda en BD pero oculto de pickers).
//    Pasa deactivateSource: false para delete real con cascade.
```

Guard: ambos deben ser de la misma taxonomy y mismo owner. Lanza `InvalidArgumentException` si no.

## Facade

```php
use EduLazaro\Laraterms\Facades\Laraterms;

Laraterms::has('tags');
Laraterms::get('tags');                                   // TaxonomyDefinition
Laraterms::handles();                                     // ['tags', 'categories', ...]
Laraterms::register('moods', [...]);                      // runtime
Laraterms::resolveOwnerUsing(fn ($m) => $m->organization);
Laraterms::ownerFor($model);                              // Owner VO
```

## Schema

**`terms`** — `id`, `taxonomy`, `owner_type`, `owner_id`, `parent_id`, `name`, `name_translations` (JSON), `handle`, `description`, `description_translations` (JSON), `search_text`, `color`, `sort_order`, `terms_count`, `meta` (JSON), timestamps. Único en `(owner_type, owner_id, taxonomy, handle)`. FULLTEXT en `search_text` (best-effort, ignorado si el motor no lo soporta).

**`termables`** — polymorphic pivot. `term_id`, `termable_type`, `termable_id`, `sort_order`, timestamps. Único en `(term_id, termable_type, termable_id)`.

Nombres de tabla configurables.

## Exceptions

- `UnknownTaxonomyException` — handle no registrada
- `TooManyTermsException` — supera `max_terms_per_model`
- `RequiresHierarchyException` — `parent_id` en taxonomía flat

## License

MIT.
# laraterms
