![Laraterms](art/banner.png)

# Laraterms

<p align="center">
    <a href="https://github.com/edulazaro/laraterms/actions/workflows/tests.yml"><img src="https://github.com/edulazaro/laraterms/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
    <a href="https://packagist.org/packages/edulazaro/laraterms"><img src="https://img.shields.io/packagist/v/edulazaro/laraterms" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/edulazaro/laraterms"><img src="https://img.shields.io/packagist/dt/edulazaro/laraterms" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/edulazaro/laraterms"><img src="https://img.shields.io/packagist/php-v/edulazaro/laraterms" alt="PHP Version"></a>
    <a href="https://github.com/edulazaro/laraterms/blob/main/LICENSE.md"><img src="https://img.shields.io/packagist/l/edulazaro/laraterms" alt="License"></a>
</p>

**Polymorphic taxonomies for Laravel.** Define `tags`, `categories` or any custom classification in config. Multi-tenant. Multi-locale. Hierarchical or flat. Spatie-compatible. Cross-locale search via auto-maintained `search_text`. Zero schema opinion beyond two tables.

## Installation

```bash
composer require edulazaro/laraterms
php artisan vendor:publish --tag=laraterms-config
php artisan vendor:publish --tag=laraterms-migrations
php artisan migrate
```

## Define your taxonomies

`config/laraterms.php` ships with `tags` and `categories` as examples. Edit or add what you need:

```php
'taxonomies' => [
    'tags' => [
        'hierarchical'        => false,
        'max_terms_per_model' => null,
        'scope'               => 'tenant',  // or 'global'
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

## Multi-tenant (scope-scoped)

By default taxonomies are **tenant-scoped**: each scope (Organization, Workspace, Team) has its own isolated terms. Define how to resolve the scope of any taxable model:

```php
// AppServiceProvider::boot()
use EduLazaro\Laraterms\Facades\Laraterms;

Laraterms::resolveScopeUsing(fn ($model) => $model->organization ?? null);
```

Or per model:

```php
class Post extends Model
{
    use HasTerms;

    public function termsScope(): ?\Illuminate\Database\Eloquent\Model
    {
        return $this->organization;
    }
}
```

The scope can be a Model, an array (`['type' => 'organization', 'id' => 5]`), a `Scope` value object, or `null` (global). It does not require a morph map: it works with FQCN as `scope_type`.

For taxonomies shared across all tenants (languages, countries), set `scope: 'global'`.

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
$post->syncTerms(['Laravel', 'Vue'], 'tags');           // replace in the taxonomy
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

`whereHasAllTerms()` matches nothing when one of the terms does not exist, since no model can have it. `whereHasAnyTerm()` just ignores the unknown ones.

## Multi-locale (i18n)

Each translatable field has **two columns**: the canonical one (`name`, `description`) and the translations one (`name_translations`, `description_translations`). Spatie-compatible (format: `{"en": "...", "es": "..."}`).

```php
// Single-locale: use only `name`
Term::create(['name' => 'Laravel', 'taxonomy' => 'tags']);
$term->name;   // "Laravel"

// Multi-locale
Term::create([
    'name'              => 'Tag',                       // canonical fallback
    'name_translations' => ['en' => 'Tag', 'es' => 'Etiqueta'],
    'taxonomy'          => 'tags',
]);
$term->name;   // "Etiqueta" if locale=es, "Tag" if locale=en or fallback
```

The accessor on `name` and `description` resolves automatically: active locale, then fallback locale, then canonical column.

### Spatie integration (opt-in, on your side)

If you want the full Spatie API on the `*_translations` columns:

```php
class Term extends \EduLazaro\Laraterms\Models\Term
{
    use \Spatie\Translatable\HasTranslations;
    protected $translatable = ['name_translations', 'description_translations'];
}
```

Our accessor on `name`/`description` keeps working because it reads the raw attributes.

## Cross-locale search

`search_text` is auto-maintained in `saving()` by concatenating all values across all locales. Language-agnostic LIKE search:

```php
Term::search('impuesto')->get();                        // matches even if the user is on /en
Term::where('search_text', 'like', '%laravel%')->get();
Term::whereFullText('search_text', 'laravel')->get();   // if your engine supports FULLTEXT (default migration adds it)
```

## Hierarchical

```php
use EduLazaro\Laraterms\Support\TermTree;

$tree = TermTree::for('categories', $organization);      // Collection of roots with children populated (1 query)
$tree = TermTree::for('categories', $organization, includeGlobal: true);
$tree = TermTree::for('categories', $organization, onlyActive: true);
$tree = TermTree::for('categories');                    // no scope = the global catalog only

foreach (TermTree::flatten($tree) as [$term, $depth]) {
    echo str_repeat('. ', $depth) . $term->name . "\n";
}

$term->ancestors();                                     // Collection<Term> root to parent
$term->breadcrumb(' > ');                               // "Tech > Web > Laravel"
$term->descendantIds();                                 // all descendant ids
```

## Model

```php
$term = Term::findOrCreateByName('Laravel', 'tags', $organization);
Term::inTaxonomy('tags')->ordered()->get();
Term::byHandle('laravel', 'tags')->first();
Term::forScope($org)->inTaxonomy('tags')->get();
Term::forScopeOrGlobal($org)->inTaxonomy('tags')->get();
$term->refreshCount();
```

## Activate / deactivate (soft hide)

Each term has `is_active` (default `true`). Deactivating hides the term from pickers for new attachments, but models already attached keep showing the badge. Useful for "this tag is no longer used, but old posts that have it keep showing it".

```php
$term->deactivate();        // hide from pickers
$term->activate();          // re-activate
Term::active()->inTaxonomy('tags')->forScope($org)->get();        // only active
Term::inactive()->inTaxonomy('tags')->forScope($org)->get();      // only inactive
```

**This is NOT soft-delete.** If you want proper SoftDeletes (with `withTrashed`, `restore`, etc.), extend the model in your app:

```php
class Term extends \EduLazaro\Laraterms\Models\Term {
    use \Illuminate\Database\Eloquent\SoftDeletes;
}
// + migration with $table->softDeletes();
```

## Merge terms (`mergeInto`)

For duplicate cleanup ("we had `laravel` and `Laravel Framework`, merge them into `laravel`"):

```php
$dup = Term::byHandle('laravel-framework', 'tags')->first();
$canonical = Term::byHandle('laravel', 'tags')->first();

$dup->mergeInto($canonical, deactivateSource: true);
// 1. Moves all termables from $dup to $canonical (without duplicating)
// 2. Recalculates terms_count on the canonical
// 3. Deactivates $dup (kept in DB but hidden from pickers).
//    Pass deactivateSource: false for a real delete with cascade.
```

Guard: both must belong to the same taxonomy and the same scope. Throws `InvalidArgumentException` otherwise.

## Facade

```php
use EduLazaro\Laraterms\Facades\Laraterms;

Laraterms::has('tags');
Laraterms::get('tags');                                   // TaxonomyDefinition
Laraterms::handles();                                     // ['tags', 'categories', ...]
Laraterms::register('moods', [...]);                      // runtime
Laraterms::resolveScopeUsing(fn ($m) => $m->organization);
Laraterms::scopeFor($model);                              // Scope VO
```

## Schema

**`terms`**: `id`, `taxonomy`, `scope_type`, `scope_id`, `parent_id`, `name`, `name_translations` (JSON), `handle`, `description`, `description_translations` (JSON), `search_text`, `color`, `sort_order`, `terms_count`, `meta` (JSON), timestamps. Unique on `(scope_type, scope_id, taxonomy, handle)`. FULLTEXT on `search_text` (best-effort, ignored if the engine does not support it).

**`termables`**: polymorphic pivot. `term_id`, `termable_type`, `termable_id`, `sort_order`, timestamps. Unique on `(term_id, termable_type, termable_id)`.

Table names are configurable.

## Exceptions

- `UnknownTaxonomyException`: taxonomy handle not registered.
- `TooManyTermsException`: exceeds `max_terms_per_model`.
- `RequiresHierarchyException`: `parent_id` set on a flat taxonomy.

## License

MIT.
