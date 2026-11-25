<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Category extends Model
{
    protected $fillable = [
        'legacy_id',
        'parent_id',
        'name',
        'active',
        'type',
        'discount',
        'legacy_lft',
        'legacy_rgt',
        'unas_id',
    ];

    protected $casts = [
        'active' => 'boolean',
        'discount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            if (! $category->isDirty('parent_id') || ! $category->parent_id) {
                return;
            }

            $parent = static::query()->find($category->parent_id);
            $visited = [];

            while ($parent) {
                if ($parent->is($category) || isset($visited[$parent->getKey()])) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Нельзя создать циклическую связь категорий.',
                    ]);
                }

                $visited[$parent->getKey()] = true;
                $parent = $parent->parent_id
                    ? static::query()->find($parent->parent_id)
                    : null;
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function treeLabel(?array $categoriesById = null): string
    {
        return $this->treePath(' / ', $categoriesById);
    }

    public function treePath(
        string $separator = '|',
        ?array $categoriesById = null,
        bool $omitCategoriesRoot = false,
    ): string
    {
        $names = [$this->name];
        $parent = $categoriesById === null
            ? $this->parent
            : ($categoriesById[$this->parent_id] ?? null);
        $visited = [$this->getKey() => true];

        while ($parent && ! isset($visited[$parent->getKey()])) {
            $visited[$parent->getKey()] = true;
            array_unshift($names, $parent->name);
            $parent = $categoriesById === null
                ? $parent->parent
                : ($categoriesById[$parent->parent_id] ?? null);
        }

        if ($omitCategoriesRoot && count($names) > 1 && mb_strtolower(trim($names[0]), 'UTF-8') === 'categories') {
            array_shift($names);
        }

        return implode($separator, $names);
    }
}
