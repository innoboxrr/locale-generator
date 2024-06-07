<?php

namespace Innoboxrr\LocaleGenerator\Providers;

use Illuminate\Support\ServiceProvider;
use Innoboxrr\LocaleGenerator\Console\Commands\GenerateLocaleCommand;
use Innoboxrr\LocaleGenerator\Console\Commands\TranslateLocaleCommand;

class AppServiceProvider extends ServiceProvider
{

    public function register()
    {
        
        $this->mergeConfigFrom(__DIR__ . '/../../config/locale-generator.php', 'locale-generator');

        $this->app->bind('command.locale.generate', GenerateLocaleCommand::class);

        $this->app->bind('command.locale.translate', TranslateLocaleCommand::class);
        
        $this->commands([
            'command.locale.generate',
            'command.locale.translate'
        ]);

    }

    public function boot()
    {
        
        // $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'locale-generator');

        if ($this->app->runningInConsole()) {
            
            // $this->publishes([__DIR__.'/../../resources/views' => resource_path('views/vendor/locale-generator'),], 'views');

            $this->publishes([__DIR__.'/../../config/locale-generator.php' => config_path('locale-generator.php')], 'config');

        }

    }
    
}