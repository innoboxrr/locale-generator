# Changelog

Todas las modificaciones notables a este proyecto serán documentadas en este archivo.

El formato se basa en [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
y este proyecto sigue [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

### Changed

### Removed

### Fixed

## [2.1.0] - 13-09-2026

Correcciones para usarlo en una aplicación Laravel 13 nueva con un SPA en Vue o
en React.

### Added
 - `locale:translate --force`: vuelve a traducir también las claves que ya tienen traducción.
 - `google_api_key` se lee de `GOOGLE_TRANSLATE_API_KEY` y `lang_path` de `LOCALE_GENERATOR_LANG_PATH`.
 - Tests de comportamiento en `tests/Feature` y `tests/Unit`, incluidos en `phpunit.xml.dist`.

### Changed
 - `locale:translate` solo traduce las claves vacías; antes las traducía todas y pisaba las corregidas a mano. Usa el cliente `Http` de Laravel, envía `format=text` (sin entidades HTML), convierte `pt_BR` en `pt-BR` y acepta `es` o `es.json`.
 - `locale:translate` termina con código distinto de 0 cuando falla: archivo inexistente o inválido, sin clave de API o error de Google. Antes terminaba con 0. Si Google falla a mitad, guarda lo ya traducido.
 - `locale:generate` conserva todo lo que ya tiene el archivo, incluidas las claves que ya no aparecen en el código; antes se eliminaban. Escribe UTF-8 sin escapar y termina el archivo con salto de línea.
 - `RouteServiceProvider` hereda de `Illuminate\Support\ServiceProvider` y registra sus rutas en `boot`.
 - README reescrito: el anterior era el de otro paquete.

### Fixed
 - Con `lang_path` en `null`, `locale:generate` intentaba escribir `/es.json` en la raíz del disco. Ahora usa `lang_path()`. `routes` y `extensions` vacíos equivalen a `resource_path()` y a `php, vue, js, jsx, ts, tsx`, y las rutas relativas se resuelven contra la raíz del proyecto.
 - La extracción aceptaba cualquier `t(` (`alert('x')`, `emit('x')`, `import('x')`). Ahora solo cuentan `__`, `t`, `$t` y `trans` con una cadena literal completa, en comillas simples o dobles, incluidas comillas escapadas, más argumentos y llamadas en varias líneas.
 - Un JSON existente inválido ya no se sobrescribe: el comando falla y lo deja como estaba.
 - La propiedad `$filePath` se declara; asignarla sin declarar está obsoleto desde PHP 8.2.
 - El proveedor de rutas heredaba del `RouteServiceProvider` de Foundation y volvía a cargar las rutas de la aplicación en Laravel 11 o posterior.

### Contrato
 - `locale:generate {locale}` y `locale:translate {filename}` mantienen su firma; se añade la opción `--force`. Las claves de configuración no cambian.

## [1.0.4] - 27-02-2023

### Added

### Changed

### Removed

### Fixed
 - Fix: ResetPasswordRequest.php does not comply with psr-4

## [1.0.3] - 27-02-2023

### Added

### Changed

### Removed
 - Se ha removido el folder .phpunit.cache

### Fixed

## [1.0.1] - 27-02-2023

### Added
 - LoginTest
 - RegisterTest

### Changed

### Removed

### Fixed


## [1.0.0] - 27-02-2023

### Added
 - Se han creado todas las rutas de autenticación
 - Se ha configurado el archivo de configuración para el comportamiento de las rutas
 - Se han requerido las dependencias
 	- laravel/sanctum

### Changed
 - No ha ocurrido ningún cambio

### Removed
 - No se ha eliminado nada

### Fixed
 - No se ha corregido nada
