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
        $updatedTranslations = [];

        // Las claves nuevas se añaden en el orden en que aparecen, y ese orden
        // no puede depender de como liste el disco el sistema operativo.
        $files = $this->files;
        sort($files);

        foreach ($files as $file) {
            // El archivo entero y no linea a linea: una llamada puede partirse
            // en varias lineas.
            foreach ($this->extractText((string) file_get_contents($file)) as $text) {
                if (! array_key_exists($text, $updatedTranslations)) {
                    $updatedTranslations[$text] = '';
                }
            }
        }

        return $updatedTranslations;
    }

    /**
     * Las claves literales de __('…'), t('…'), $t('…') y trans('…').
     *
     * El nombre no puede ir pegado a una letra, un digito, un guion bajo, un
     * punto o un $: alert('x'), emit('x'), import('x') o i18n.t('x') no son
     * claves. $t es la excepcion explicita, y se admite tambien tras un punto
     * (this.$t('x')), que es como se llama desde la Options API de Vue.
     *
     * Solo cuenta una cadena completa como primer argumento, entre comillas
     * simples o dobles: t('Hola ' + nombre) o t(`Hola ${nombre}`) no son
     * claves, y adivinarlas escribiria basura en el archivo.
     *
     * @return array<int, string>
     */
    public function extractText($content)
    {
        $pattern = '/(?:(?<![A-Za-z0-9_.$])(?:__|trans|t)|(?<![A-Za-z0-9_$])\$t)'
            . '\(\s*([\'"])((?:(?!\1)[^\\\\]|\\\\.)*)\1\s*[,)]/s';

        preg_match_all($pattern, (string) $content, $matches);

        $texts = [];

        foreach ($matches[2] as $raw) {
            $text = (string) preg_replace('/\\\\(.)/s', '$1', $raw);

            if ($text !== '') {
                $texts[] = $text;
            }
        }

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
