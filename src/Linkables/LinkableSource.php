<?php

namespace Javaabu\DynamicMenubuilder\Linkables;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Javaabu\DynamicMenubuilder\Concerns\HasCan;
use Javaabu\DynamicMenubuilder\Contracts\MenuLinkable;

/**
 * A kind of record admins can add to menus: a `MenuLinkable` model, optionally narrowed by a
 * query, so one model can give several sources (one per post type)
 *
 * @implements Arrayable<string, mixed>
 */
class LinkableSource implements Arrayable
{
    use HasCan;

    protected Closure|string|null $label = null;

    protected ?Closure $query = null;

    protected Closure|string|false|null $index_url = null;

    protected Closure|string|null $index_title = null;

    /**
     * @param  class-string<Model&MenuLinkable>  $model
     */
    public function __construct(
        protected string $model,
        protected string $key,
    ) {
        if (! is_a($model, Model::class, true) || ! is_a($model, MenuLinkable::class, true)) {
            throw new InvalidArgumentException("[{$model}] must be an Eloquent model that implements " . MenuLinkable::class . '.');
        }
    }

    /**
     * @param  class-string<Model&MenuLinkable>  $model
     * @param  string|null  $key  defaults to the model's morph alias, or its plural kebab-case name (`blog-posts`)
     */
    public static function make(string $model, ?string $key = null): static
    {
        return new static($model, $key ?? static::defaultKey($model));
    }

    /**
     * @param  class-string<Model>  $model
     */
    public static function defaultKey(string $model): string
    {
        $alias = array_search($model, Relation::morphMap(), true);

        if (is_string($alias)) {
            return $alias;
        }

        return str(class_basename($model))->kebab()->plural()->toString();
    }

    /**
     * The name of the source in the builder (`Pages`, `News`). A string is translated.
     */
    public function label(Closure|string|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Narrow the records the builder offers and menus show: `fn (Builder $query) => $query->where('type', 'news')`
     */
    public function query(?Closure $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * The index page: a URL, a closure that gets the locale, or false for none. By default,
     * the model's `getMenuLinkIndexUrl()`.
     */
    public function indexUrl(Closure|string|false|null $url): static
    {
        $this->index_url = $url;

        return $this;
    }

    /**
     * The default title of an index item. By default, the source's label.
     */
    public function indexTitle(Closure|string|null $title): static
    {
        $this->index_title = $title;

        return $this;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return class-string<Model&MenuLinkable>
     */
    public function getModel(): string
    {
        return $this->model;
    }

    public function getLabel(): string
    {
        if ($this->label instanceof Closure) {
            return (string) app()->call($this->label);
        }

        if ($this->label !== null) {
            return __($this->label);
        }

        return str(class_basename($this->model))->headline()->plural()->toString();
    }

    public function getIndexTitle(?string $locale = null): string
    {
        if ($this->index_title instanceof Closure) {
            return (string) app()->call($this->index_title, ['locale' => $locale ?? app()->getLocale()]);
        }

        if ($this->index_title !== null) {
            return __($this->index_title, [], $locale);
        }

        return $this->getLabel();
    }

    public function getIndexUrl(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        if ($this->index_url === false) {
            return null;
        }

        if ($this->index_url instanceof Closure) {
            return app()->call($this->index_url, ['locale' => $locale]);
        }

        if (is_string($this->index_url)) {
            return $this->index_url;
        }

        return $this->model::getMenuLinkIndexUrl($locale);
    }

    public function hasIndex(): bool
    {
        return filled($this->getIndexUrl());
    }

    /**
     * The records of the source
     */
    public function newQuery(): Builder
    {
        $query = $this->model::query();

        if ($this->query) {
            ($this->query)($query);
        }

        return $query;
    }

    /**
     * Whether a record belongs to the source
     */
    public function contains(Model $record): bool
    {
        if (! $record instanceof $this->model) {
            return false;
        }

        return $this->newQuery()->whereKey($record->getKey())->exists();
    }

    public function find(int|string $id): ?Model
    {
        return $this->newQuery()->find($id);
    }

    /**
     * Records matching a search, for the builder's picker
     *
     * @return array<int, array{id: int|string, label: string}>
     */
    public function search(?string $search = null, int $limit = 20, ?string $locale = null): array
    {
        $query = $this->newQuery();

        if (filled($search)) {
            $query = $this->model::searchMenuLinks($query, $search);
        }

        return $query->limit($limit)
            ->get()
            ->map(fn (Model $record) => [
                'id' => $record->getKey(),
                /** @phpstan-ignore-next-line */
                'label' => $record->getMenuLinkTitle($locale),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{key: string, label: string, has_index: bool, index_title: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->getKey(),
            'label' => $this->getLabel(),
            'has_index' => $this->hasIndex(),
            'index_title' => $this->getIndexTitle(),
        ];
    }
}
