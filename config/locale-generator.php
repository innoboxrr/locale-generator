<?php

return [

    /*
    | Directorio donde se escribe <locale>.json. Sin valor se usa lang_path(),
    | donde Laravel busca las traducciones JSON. Una ruta relativa se resuelve
    | contra la raiz del proyecto, por ejemplo:
    |
    |     LOCALE_GENERATOR_LANG_PATH=resources/locales
    */
    'lang_path' => env('LOCALE_GENERATOR_LANG_PATH'),

    /*
    | Directorios en los que se buscan las claves. Una lista vacia equivale a
    | resource_path(). node_modules y vendor nunca se recorren.
    */
    'routes' => [
        resource_path(),
    ],

    /*
    | Extensiones de los archivos que se leen. Una lista vacia equivale a esta.
    */
    'extensions' => ['php', 'vue', 'js', 'jsx', 'ts', 'tsx'],

    'google_api_key' => null,

];
