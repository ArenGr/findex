<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Str;

/**
 * Shared by RegisteredOrganizationController and RegisteredWriterController -
 * both need a unique, URL-safe slug generated from a user-submitted name at
 * registration time, with the same "-2", "-3"... collision-suffix behavior.
 */
trait GeneratesUniqueSlug
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function uniqueSlug(string $name, string $modelClass): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $suffix = 2;

        while ($modelClass::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
