<?php

namespace Innoboxrr\LocaleGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Innoboxrr\LocaleGenerator\Console\Commands\Concerns\ResolvesPaths;

class TranslateLocaleCommand extends Command
{
    use ResolvesPaths;

    public const ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';

    protected $signature = 'locale:translate {filename}
        {--force : Translate every key again, including the ones that already have a translation}';

    protected $description = 'Translates the locale file using Google Translate API';

    public function handle()
    {
        $filename = $this->argument('filename');

        $filePath = $this->getFilePath($filename);

        if (! File::exists($filePath)) {
            $this->error("File $filename does not exist");

            return self::FAILURE;
        }

        $translations = json_decode(File::get($filePath), true);

        if (! is_array($translations)) {
            $this->error("File $filename is not valid JSON");

            return self::FAILURE;
        }

        $targetLanguageCode = $this->getLanguageCodeFromFilename($filename);

        if (empty($targetLanguageCode)) {
            $this->error('Invalid filename');

            return self::FAILURE;
        }

        $apiKey = config('locale-generator.google_api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            $this->error('Set GOOGLE_TRANSLATE_API_KEY in your .env to use locale:translate');

            return self::FAILURE;
        }

        $failure = null;

        $translatedTranslations = $this->translateArray(
            $translations,
            $targetLanguageCode,
            $apiKey,
            (bool) $this->option('force'),
            $failure
        );

        // Se guarda tambien si Google fallo a mitad: lo ya traducido esta pagado.
        File::put(
            $filePath,
            json_encode((object) $translatedTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        );

        if ($failure !== null) {
            $this->error($failure);
            $this->warn('The translations obtained before the error were saved.');

            return self::FAILURE;
        }

        $this->info("Translation for $filename has been completed successfully");

        return self::SUCCESS;
    }

    /**
     * Admite el idioma solo ("es") o con la extension ("es.json").
     */
    public function getFilePath($filename)
    {
        $name = (string) preg_replace('/\.json$/i', '', (string) $filename);

        return $this->langDirectory() . DIRECTORY_SEPARATOR . $name . '.json';
    }

    private function getLanguageCodeFromFilename($filename)
    {
        return pathinfo($filename, PATHINFO_FILENAME);
    }

    /**
     * Una peticion por clave. Se detiene en el primer error y lo deja en
     * $failure, devolviendo lo traducido hasta ese momento.
     */
    private function translateArray(array $translations, string $targetLanguageCode, string $apiKey, bool $force, ?string &$failure = null): array
    {
        // Google espera pt-BR donde Laravel nombra el archivo pt_BR.
        $target = str_replace('_', '-', $targetLanguageCode);

        foreach ($translations as $key => $value) {
            // Una traduccion que ya existe, escrita a mano o de una ejecucion
            // anterior, no se pisa ni se vuelve a pagar, salvo con --force.
            if (! $force && is_string($value) && $value !== '') {
                continue;
            }

            // Solo se traducen cadenas: un grupo anidado se deja como esta.
            if (! is_string($value) && $value !== null) {
                continue;
            }

            try {
                $translatedText = Http::withQueryParameters(['key' => $apiKey])
                    ->asForm()
                    ->post(self::ENDPOINT, [
                        'q' => (string) $key,
                        'target' => $target,
                        // Sin esto Google trata el texto como HTML y devuelve
                        // entidades: "It&#39;s" en lugar de "It's".
                        'format' => 'text',
                    ])
                    ->throw()
                    ->json('data.translations.0.translatedText');
            } catch (RequestException $e) {
                $reason = $e->response->json('error.message');
                $failure = "Google Translate rejected \"{$key}\": " . (is_string($reason) ? $reason : 'HTTP ' . $e->response->status());

                break;
            } catch (ConnectionException) {
                // El mensaje de la conexion lleva la URL, y en ella la clave.
                $failure = "Could not reach Google Translate while translating \"{$key}\"";

                break;
            }

            if (! is_string($translatedText)) {
                $failure = "Google Translate returned no translation for \"{$key}\"";

                break;
            }

            $translations[$key] = $translatedText;
        }

        return $translations;
    }
}
