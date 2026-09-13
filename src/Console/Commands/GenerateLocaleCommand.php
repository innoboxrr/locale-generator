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

        $this->file = $this->loadJsonFile();

        // Un archivo que no se puede leer lo ha roto alguien a mano: escribir
        // encima borraria lo que tuviera.
        if (! is_array($this->file)) {
            $this->error("{$this->filePath} is not valid JSON; it was left untouched.");

            return self::FAILURE;
        }

        $this->files = $this->getRoutes();

        $this->dictionary = $this->createDictionary();

        $this->updateJsonFile();

        $this->saveJsonFile();

        $this->info('Generating ' . $this->fileName);

        return self::SUCCESS;
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

    /**
     * El contenido del archivo si existe: un array, o null si no es JSON valido.
     */
    public function loadJsonFile()
    {
        if (! file_exists($this->filePath)) {
            return [];
        }

        $content = (string) file_get_contents($this->filePath);

        if (trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Lo que ya tiene el archivo se queda tal cual, incluidas las claves que ya
     * no aparecen en el codigo: una traduccion escrita a mano no se pierde por
     * mover o renombrar un componente. Las claves nuevas se añaden al final,
     * vacias.
     */
    public function updateJsonFile()
    {
        $merged = $this->file;

        foreach ($this->dictionary as $key => $value) {
            if (! array_key_exists($key, $merged)) {
                $merged[$key] = $value;
            }
        }

        return $this->dictionary = $merged;
    }

    public function saveJsonFile()
    {
        $json = json_encode((object) $this->dictionary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        file_put_contents($this->filePath, $json . "\n");
    }
}
