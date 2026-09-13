<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait GeneratesUniqueSlug
{
    /**
     * @param  class-string<Model>  $modelClass
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
