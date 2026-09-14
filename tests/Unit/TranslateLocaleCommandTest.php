<?php

namespace Innoboxrr\LocaleGenerator\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Innoboxrr\LocaleGenerator\Console\Commands\TranslateLocaleCommand;
use Innoboxrr\LocaleGenerator\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * La llamada a Google Translate, sin red: Http::fake responde y
 * preventStrayRequests hace fallar cualquier peticion que no este prevista.
 */
final class TranslateLocaleCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'locale-translate-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);

        config()->set('locale-generator.lang_path', $this->directory);
        config()->set('locale-generator.google_api_key', 'test-key');

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        (new Filesystem())->deleteDirectory($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function traduce_cada_clave_vacia_con_la_clave_de_api_el_idioma_y_formato_texto(): void
    {
        $this->fakeGoogle();
        $this->writeLocale('es', ['Save' => '', 'Cancel' => '']);

        $this->artisan('locale:translate', ['filename' => 'es'])->assertSuccessful();

        $this->assertSame(['Save' => 'ES:Save', 'Cancel' => 'ES:Cancel'], $this->readLocale('es'));

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'POST'
                && str_starts_with($request->url(), TranslateLocaleCommand::ENDPOINT)
                && ($query['key'] ?? null) === 'test-key'
                && $request['q'] === 'Save'
                && $request['target'] === 'es'
                && $request['format'] === 'text';
        });
    }

    #[Test]
    public function no_pisa_las_traducciones_existentes_salvo_con_force(): void
    {
        $this->fakeGoogle();
        $this->writeLocale('es', ['Save' => 'Guardar', 'Cancel' => '']);

        $this->artisan('locale:translate', ['filename' => 'es'])->assertSuccessful();

        $this->assertSame(['Save' => 'Guardar', 'Cancel' => 'ES:Cancel'], $this->readLocale('es'));
        Http::assertSentCount(1);

        $this->artisan('locale:translate', ['filename' => 'es', '--force' => true])->assertSuccessful();

        $this->assertSame(['Save' => 'ES:Save', 'Cancel' => 'ES:Cancel'], $this->readLocale('es'));
        Http::assertSentCount(3);
    }

    #[Test]
    public function acepta_la_extension_y_convierte_el_codigo_regional_al_de_google(): void
    {
        $this->fakeGoogle();
        $this->writeLocale('pt_BR', ['Save' => '']);

        $this->artisan('locale:translate', ['filename' => 'pt_BR.json'])->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request['target'] === 'pt-BR');
        $this->assertSame(['Save' => 'ES:Save'], $this->readLocale('pt_BR'));
    }

    #[Test]
    public function sin_clave_de_api_falla_sin_llamar_a_google(): void
    {
        Http::fake();
        config()->set('locale-generator.google_api_key', null);
        $this->writeLocale('es', ['Save' => '']);

        $this->artisan('locale:translate', ['filename' => 'es'])->assertFailed();

        Http::assertNothingSent();
        $this->assertSame(['Save' => ''], $this->readLocale('es'));
    }

    #[Test]
    public function un_error_de_google_guarda_lo_ya_traducido_y_termina_con_error(): void
    {
        Http::fake([
            'translation.googleapis.com/*' => Http::sequence()
                ->push(['data' => ['translations' => [['translatedText' => 'Guardar']]]])
                ->push(['error' => ['code' => 403, 'message' => 'API key not valid.']], 403),
        ]);
        $this->writeLocale('es', ['Save' => '', 'Cancel' => '']);

        $this->artisan('locale:translate', ['filename' => 'es'])
            ->expectsOutputToContain('API key not valid.')
            ->assertFailed();

        $this->assertSame(['Save' => 'Guardar', 'Cancel' => ''], $this->readLocale('es'));
    }

    #[Test]
    public function un_archivo_que_no_existe_termina_con_error(): void
    {
        Http::fake();

        $this->artisan('locale:translate', ['filename' => 'fr'])->assertFailed();

        Http::assertNothingSent();
    }

    private function fakeGoogle(): void
    {
        Http::fake([
            'translation.googleapis.com/*' => fn (Request $request) => Http::response([
                'data' => ['translations' => [['translatedText' => 'ES:' . $request['q']]]],
            ]),
        ]);
    }

    /**
     * @param  array<string, string>  $translations
     */
    private function writeLocale(string $locale, array $translations): void
    {
        file_put_contents($this->directory . DIRECTORY_SEPARATOR . $locale . '.json', json_encode($translations));
    }

    /**
     * @return array<string, mixed>
     */
    private function readLocale(string $locale): array
    {
        $decoded = json_decode((string) file_get_contents($this->directory . DIRECTORY_SEPARATOR . $locale . '.json'), true);

        $this->assertIsArray($decoded);

        return $decoded;
    }
}
