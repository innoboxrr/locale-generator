<?php

namespace Innoboxrr\LocaleGenerator\Console\Commands;

use Illuminate\Console\Command;
use Innoboxrr\LocaleGenerator\Console\Commands\Concerns\ResolvesPaths;

class GenerateLocaleCommand extends Command
{
    use ResolvesPaths;

    /**
     * Las extensiones que se leen cuando la configuracion no dice ninguna.
     */
    public const DEFAULT_EXTENSIONS = ['php', 'vue', 'js', 'jsx', 'ts', 'tsx'];

    protected $signature = 'locale:generate {locale}';

    protected $description = 'Generates a new locale file';

    protected $routes;

    protected $extensions;

    protected $locale;

    protected $fileName;

    protected string $filePath;

    protected $files;

    protected $dictionary;

    protected $file;

    protected $data;

    public function handle()
    {
        // Se lee al ejecutar y no en el constructor: artisan construye los
        // comandos al arrancar, y un cambio posterior de la configuracion no
        // se veria.
        $this->routes = $this->configuredRoutes();

        $this->extensions = $this->configuredExtensions();

        $this->locale = $this->argument('locale');

        $this->fileName = $this->locale . '.json';

        $this->filePath = $this->getFilePath();

        $this->files = $this->getRoutes();

        $this->dictionary = $this->createDictionary(); // Este es el diccionario con todas las claves

        $this->file = $this->loadJsonFile(); // Este es el archivo si existe

        $this->updateJsonFile(); // Combina el diccionario en conjunto con file

        $this->saveJsonFile(); // Guarda el archivo JSON

        $this->info('Generating ' . $this->fileName);
    }

    public function getFilePath()
    {
        $dir = $this->langDirectory();

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);

            $this->info('Directory created: ' . $dir);
        }

        return $dir . DIRECTORY_SEPARATOR . $this->fileName;
    }

    /**
     * Una lista vacia, o la configuracion publicada antes de que hubiera valores
     * por defecto, recorre resource_path().
     *
     * @return array<int, string>
     */
    protected function configuredRoutes(): array
    {
        $routes = array_values(array_filter(
            (array) config('locale-generator.routes'),
            fn ($route): bool => is_string($route) && $route !== ''
        ));

        if ($routes === []) {
            return [resource_path()];
        }

        return array_map(fn (string $route): string => $this->absolutePath($route), $routes);
    }

    /**
     * @return array<int, string>
     */
    protected function configuredExtensions(): array
    {
        $extensions = array_values(array_filter(
            (array) config('locale-generator.extensions'),
            fn ($extension): bool => is_string($extension) && $extension !== ''
        ));

        return $extensions === [] ? self::DEFAULT_EXTENSIONS : $extensions;
    }

    public function getRoutes(): array
    {
        $scrapedFiles = [];

        foreach ($this->routes as $route) {
            $scrapedFiles = array_merge($scrapedFiles, $this->searchFiles($route));
        }

        return $scrapedFiles;
    }

    public function searchFiles(string $route): array
    {
        $scrapedFiles = [];

        foreach (glob($route . '/*') ?: [] as $file) {
            if (is_file($file) && in_array(pathinfo($file, PATHINFO_EXTENSION), $this->extensions)) {
                $scrapedFiles[] = $file;
            } elseif (is_dir($file) && basename($file) !== 'node_modules' && basename($file) !== 'vendor') {
                $scrapedFiles = array_merge($scrapedFiles, $this->searchFiles($file));
            }
        }

        return $scrapedFiles;
    }

    public function createDictionary()
    {
        // Diccionario de traducciones actualizado
        $updatedTranslations = [];

        // Recorrer los files y extraer los texts a traducir
        foreach ($this->files as $file) {
            $content = file_get_contents($file);

            $lines = explode("\n", $content);

            foreach ($lines as $line) {
                $texts = $this->extractText($line);

                foreach ($texts as $text) {
                    // Verificar si la key ya existe y agregarla si no existe
                    if (! isset($updatedTranslations[$text])) {
                        $updatedTranslations[$text] = '';
                    }
                }
            }
        }

        return $updatedTranslations;
    }

    public function extractText($content)
    {
        // Patrón regex para capturar texto en __(), t(), {{ __() }}, {{ t() }}
        $pattern = "/__\(['\"]([^\"']+)['\"]\)|t\(['\"]([^\"']+)['\"]\)|\{\{__\(['\"]([^\"']+)['\"]\)\}\}|\{\{t\(['\"]([^\"']+)['\"]\)\}\}/";

        preg_match_all($pattern, $content, $matches);

        // Combina todos los grupos de captura y elimina valores vacíos
        $texts = array_merge($matches[1], $matches[2], $matches[3], $matches[4]);
        $texts = array_filter($texts);

        return $texts;
    }

    public function loadJsonFile()
    {
        if (file_exists($this->filePath)) {
            $content = file_get_contents($this->filePath);

            return json_decode($content, true);
        }

        return [];
    }

    public function updateJsonFile()
    {
        foreach ($this->dictionary as $key => $value) {
            if (isset($this->file[$key])) {
                $this->dictionary[$key] = $this->file[$key];
            }
        }

        return $this->dictionary;
    }

    public function saveJsonFile()
    {
        $json = json_encode($this->dictionary, JSON_PRETTY_PRINT);

        file_put_contents($this->filePath, $json);
    }
}
