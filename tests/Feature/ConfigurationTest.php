<?php

namespace Innoboxrr\LocaleGenerator\Tests\Feature;

use Innoboxrr\LocaleGenerator\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ConfigurationTest extends TestCase
{
    private const VARIABLES = [
        'GOOGLE_TRANSLATE_API_KEY' => 'clave-desde-el-env',
        'LOCALE_GENERATOR_LANG_PATH' => 'resources/locales',
    ];

    protected function setUp(): void
    {
        // La configuracion del paquete se evalua al registrar su proveedor: las
        // variables tienen que existir antes de crear la aplicacion.
        foreach (self::VARIABLES as $name => $value) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (array_keys(self::VARIABLES) as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    #[Test]
    public function la_clave_de_google_y_lang_path_se_leen_del_env(): void
    {
        $this->assertSame('clave-desde-el-env', config('locale-generator.google_api_key'));
        $this->assertSame('resources/locales', config('locale-generator.lang_path'));
    }
}
