<?php

namespace App\Services\Marketing;

use App\Models\Marketing\Template;
use Illuminate\Database\Eloquent\Collection;

/**
 * Template lookup + CRUD helpers for the editor and AI layer.
 *
 * The editor calls listByKind() to populate its "Templates" panel.
 * Claude (Faza 2) reads metadata across all active templates to choose
 * the best fit for a given product and post type.
 */
class TemplateService
{
    /**
     * @return Collection<int, Template>
     */
    public function listByKind(string $kind): Collection
    {
        return Template::query()
            ->active()
            ->ofKind($kind)
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Template>
     */
    public function forEngine(string $engine): Collection
    {
        return Template::query()
            ->active()
            ->forEngine($engine)
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }

    public function findBySlug(string $slug): ?Template
    {
        return Template::query()->where('slug', $slug)->first();
    }

    public function create(array $attributes): Template
    {
        return Template::query()->create($attributes);
    }

    public function update(Template $template, array $attributes): Template
    {
        $template->fill($attributes)->save();

        return $template->refresh();
    }

    public function deactivate(Template $template): void
    {
        $template->is_active = false;
        $template->save();
    }
}
