<?php

namespace Innoboxrr\LocaleGenerator\Console\Commands\Concerns;

trait ResolvesPaths
{
    /**
     * El directorio de los <locale>.json. Sin configurar, lang_path(): es donde
     * Laravel busca las traducciones JSON.
     */
    protected function langDirectory(): string
    {
        $path = config('locale-generator.lang_path');

        return is_string($path) && $path !== '' ? $this->absolutePath($path) : lang_path();
    }

    /**
     * Una ruta relativa se resuelve contra la raiz del proyecto y no contra el
     * directorio de trabajo, que fuera de artisan no tiene por que ser la raiz.
     */
    protected function absolutePath(string $path): string
    {
        $absolute = str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1
            || str_contains($path, '://');

        return $absolute ? $path : base_path($path);
    }
}
