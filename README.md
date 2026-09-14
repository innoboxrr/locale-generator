# Locale Generator

Collects the translation keys your Laravel app and its SPA use into `<locale>.json`, keeps the translations you already have, and can fill the missing ones with Google Translate.

```bash
php artisan locale:generate es      # add the keys found in the code to es.json
php artisan locale:translate es     # translate the ones that are still empty
```

## Install

```bash
composer require innoboxrr/locale-generator
```

The service providers are auto-discovered. Publishing the config is optional; its tag is `config`, and passing `--provider` keeps other packages that use the same tag from publishing their files too:

```bash
php artisan vendor:publish --provider="Innoboxrr\LocaleGenerator\Providers\AppServiceProvider" --tag=config
```

## Configuration

| Key | Default | Meaning |
|---|---|---|
| `lang_path` | `env('LOCALE_GENERATOR_LANG_PATH')`, or `lang_path()` when empty | Directory of the `<locale>.json` files. A relative path is resolved against the project root. |
| `routes` | `[resource_path()]` | Directories to scan. `node_modules` and `vendor` are always skipped. |
| `extensions` | `php, vue, js, jsx, ts, tsx` | Extensions of the files to read. |
| `google_api_key` | `env('GOOGLE_TRANSLATE_API_KEY')` | Used by `locale:translate`. |

An empty `routes` or `extensions`, as in a config published by an older version, means the default.

If your SPA loads its translations from another directory, point `lang_path` there:

```dotenv
LOCALE_GENERATOR_LANG_PATH=resources/locales
```

## `locale:generate {locale}`

Extracts the literal keys of `__('…')`, `trans('…')`, `t('…')` and `$t('…')` (also `this.$t('…')`), in single or double quotes, and merges them into `<lang_path>/<locale>.json`.

- The key has to be a complete string literal as the first argument: `t('Hello ' + name)` and template literals are skipped.
- The function name can't be glued to something else: `alert('x')`, `emit('x')`, `import('x')` or `i18n.t('x')` are not keys.
- Entries already in the file are kept as they are, including keys that no longer appear in the code. New keys are appended with an empty value; until they are translated, Laravel's `__()` shows the key itself.
- If the existing file isn't valid JSON, it is left untouched and the command fails.

## `locale:translate {filename} {--force}`

Translates the empty values of `<lang_path>/<filename>.json` with the Google Cloud Translation API (v2), one request per key. The target language is the file name: `es`, or `pt_BR`, sent as `pt-BR`. Both `es` and `es.json` are accepted.

- Set `GOOGLE_TRANSLATE_API_KEY` in `.env`. Without it the command fails without calling Google.
- Existing translations are not overwritten. `--force` translates every key again.
- If Google fails halfway, the translations obtained so far are saved and the command exits with an error.
