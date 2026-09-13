<?php

namespace Innoboxrr\LocaleGenerator\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Innoboxrr\LocaleGenerator\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * locale:generate contra archivos de ejemplo en un directorio temporal. Ningun
 * test escribe en lang/ ni en resources/ de la aplicacion.
 */
final class GenerateLocaleCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'locale-generator-' . bin2hex(random_bytes(6));

        mkdir($this->directory . '/sources', 0777, true);

        config()->set('locale-generator.routes', [$this->directory . '/sources']);
        config()->set('locale-generator.lang_path', $this->directory . '/lang');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function extrae_las_claves_de_vue_jsx_y_php_al_json_del_idioma(): void
    {
        $this->source('components/Button.vue', "<template>\n  <button>{{ \$t('Save') }}</button>\n</template>\n<script setup>\nconst label = t('Cancel')\n</script>\n");
        $this->source('pages/Home.jsx', "export default () => <h1>{t(\"Welcome\")}</h1>\n");
        $this->source('views/welcome.blade.php', "<p>{{ __('Hello') }}</p>\n");
        $this->source('notes.md', "t('Not scanned')\n");
        $this->source('node_modules/some-package/index.js', "t('Vendored')\n");

        $this->artisan('locale:generate', ['locale' => 'es'])->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            ['Save' => '', 'Cancel' => '', 'Welcome' => '', 'Hello' => ''],
            $this->readJson($this->directory . '/lang/es.json')
        );
    }

    #[Test]
    public function solo_extrae_llamadas_a_las_funciones_de_traduccion_con_una_cadena_literal(): void
    {
        $this->source('tricky.js', <<<'JS'
            alert('alert no es clave')
            this.$emit('emit no es clave')
            emit("emit tampoco")
            import('./import-no-es-clave.js')
            const lazy = () => import("./tampoco.js")
            i18n.t('miembro no es clave')
            obj.__('miembro con guiones no es clave')
            my_t('sufijo no es clave')
            foo$t('dolar pegado no es clave')
            t(`plantilla ${nombre}`)
            t('concatenada ' + nombre)
            t(variable)
            t('')
            t('Clave con :name', { name })
            t( 'Con espacios' )
            $t("Dolar t con dobles")
            this.$t('Dolar t tras this')
            trans('Trans literal')
            __("It's fine")
            __('Escaped \'quote\'')
            t(
                'Multilinea'
            )
            JS);

        $this->source('Controller.php', <<<'PHP'
            <?php
            $message = __('Desde PHP');
            $other = __('Concatenada ' . $name);
            PHP);

        $this->artisan('locale:generate', ['locale' => 'es'])->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [
                'Clave con :name',
                'Con espacios',
                'Dolar t con dobles',
                'Dolar t tras this',
                'Trans literal',
                "It's fine",
                "Escaped 'quote'",
                'Multilinea',
                'Desde PHP',
            ],
            array_keys($this->readJson($this->directory . '/lang/es.json'))
        );
    }

    #[Test]
    public function conserva_las_traducciones_existentes_al_combinar(): void
    {
        $this->source('app.js', "t('Save')\nt('New key')\n");
        mkdir($this->directory . '/lang');
        file_put_contents($this->directory . '/lang/es.json', json_encode([
            'Save' => 'Guardar',
            'Removed from code' => 'Quitada del código',
            'Pending' => '',
        ]));

        $this->artisan('locale:generate', ['locale' => 'es'])->assertSuccessful();

        $this->assertSame(
            [
                'Save' => 'Guardar',
                'Removed from code' => 'Quitada del código',
                'Pending' => '',
                'New key' => '',
            ],
            $this->readJson($this->directory . '/lang/es.json')
        );
        $this->assertStringContainsString('Quitada del código', (string) file_get_contents($this->directory . '/lang/es.json'));
    }

    #[Test]
    public function no_escribe_encima_de_un_json_invalido(): void
    {
        $this->source('app.js', "t('Save')\n");
        mkdir($this->directory . '/lang');
        file_put_contents($this->directory . '/lang/es.json', '{"Save": "Guardar",');

        $this->artisan('locale:generate', ['locale' => 'es'])->assertFailed();

        $this->assertSame('{"Save": "Guardar",', file_get_contents($this->directory . '/lang/es.json'));
    }

    #[Test]
    public function sin_lang_path_escribe_en_el_lang_path_de_laravel(): void
    {
        config()->set('locale-generator.lang_path', null);
        $this->app->useLangPath($this->directory . '/laravel-lang');
        $this->source('app.js', "t('Hello')\n");

        $this->artisan('locale:generate', ['locale' => 'es'])->assertSuccessful();

        $this->assertSame(['Hello' => ''], $this->readJson($this->directory . '/laravel-lang/es.json'));
    }

    #[Test]
    public function una_lang_path_relativa_se_resuelve_contra_la_raiz_del_proyecto(): void
    {
        $relative = 'storage/framework/testing/' . basename($this->directory);
        config()->set('locale-generator.lang_path', $relative);
        $this->source('app.js', "t('Hello')\n");

        // Desde otro directorio de trabajo: la ruta no puede depender de el.
        $cwd = getcwd();
        chdir($this->directory);

        try {
            $this->artisan('locale:generate', ['locale' => 'es'])->assertSuccessful()->run();
        } finally {
            chdir($cwd);
        }

        try {
            $this->assertSame(['Hello' => ''], $this->readJson(base_path($relative . '/es.json')));
        } finally {
            (new Filesystem())->deleteDirectory(base_path($relative));
        }
    }

    /**
     * La configuracion publicada con versiones anteriores trae routes y
     * extensions vacios: tienen que equivaler a los valores por defecto.
     */
    #[Test]
    public function routes_y_extensions_vacios_usan_resource_path_y_las_extensiones_por_defecto(): void
    {
        config()->set('locale-generator.routes', []);
        config()->set('locale-generator.extensions', []);

        $subdirectory = resource_path('locale-generator-' . basename($this->directory));
        (new Filesystem())->ensureDirectoryExists($subdirectory);
        file_put_contents($subdirectory . '/Widget.vue', "<template>{{ \$t('Default resource key') }}</template>\n");

        try {
            $this->artisan('locale:generate', ['locale' => 'es'])->assertSuccessful()->run();
        } finally {
            (new Filesystem())->deleteDirectory($subdirectory);
        }

        $this->assertArrayHasKey('Default resource key', $this->readJson($this->directory . '/lang/es.json'));
    }

    private function source(string $relative, string $contents): void
    {
        $path = $this->directory . '/sources/' . $relative;

        (new Filesystem())->ensureDirectoryExists(dirname($path));

        file_put_contents($path, $contents);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $this->assertFileExists($path);

        $decoded = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($decoded, "{$path} no es JSON valido.");

        return $decoded;
    }
}
