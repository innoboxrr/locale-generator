<?php

namespace Innoboxrr\LocaleGenerator\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TranslateLocaleCommand extends Command
{

    protected $signature = 'locale:translate {filename}';
 
    protected $description = 'Translates the locale file using Google Translate API';

    public function handle()
    {
 
        $filename = $this->argument('filename');
 
        $filePath = $this->getFilePath($filename);

        if (!File::exists($filePath)) {

            $this->error("File $filename does not exist");
            
            return;

        }

        $contents = File::get($filePath);

        $translations = json_decode($contents, true);

        $targetLanguageCode = $this->getLanguageCodeFromFilename($filename);

        if (empty($targetLanguageCode)) {

            $this->error("Invalid filename");
            
            return;

        }

        $translatedTranslations = $this->translateArray($translations, $targetLanguageCode);

        $translatedContents = json_encode($translatedTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        File::put($filePath, $translatedContents);

        $this->info("Translation for $filename has been completed successfully");

    }

    public function getFilePath($filename) 
    {

        $dir = config('innoboxrrlocalegenerator.lang_path', resource_path('lang/json'));

        // Verificar si el directorio existe
        if (!is_dir($dir)) {
            
            // Crear el directorio si no existe
            mkdir($dir, 0755, true);

            $this->info('Directory created: ' . $dir);

        }

        return $dir . '/' . $filename . '.json';

    }

    private function getLanguageCodeFromFilename($filename)
    {

        $languageCode = pathinfo($filename, PATHINFO_FILENAME);
        
        return $languageCode;
    
    }

    private function translateArray(array $translations, string $targetLanguageCode): array
    {

        $translatedTranslations = [];

        $GOOGLE_API_KEY = config('innoboxrrlocalegenerator.google_api_key'); 

        $client = new Client();

        foreach ($translations as $key => $value) {

            $response = $client->post('https://translation.googleapis.com/language/translate/v2', [
                'query' => [
                    'key' => $GOOGLE_API_KEY,
                    'q' => $key,
                    'target' => $targetLanguageCode,
                ],
            ]);

            $responseData = json_decode($response->getBody(), true);

            // Extraer el texto traducido del resultado de la API
            $translatedText = $responseData['data']['translations'][0]['translatedText'];

            $translatedTranslations[$key] = $translatedText;

        }

        return $translatedTranslations;

    }

}
