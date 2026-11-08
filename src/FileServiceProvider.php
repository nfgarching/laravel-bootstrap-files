<?php

namespace Nfgarching\Files;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Nfgarching\Chatbot\Datasets\DatasetTargetRegistry;
use Nfgarching\Core\Modals\ModalRegistry;
use Nfgarching\Core\Navigation\NavbarNavigation;
use Nfgarching\Core\Navigation\SidebarNavigation;
use Nfgarching\Core\Traits\PublishesPackageMigrations;
use Nfgarching\Files\Console\ArchiveFiles;
use Nfgarching\Files\Console\InspectFiles;
use Nfgarching\Files\Console\MakeTestCertificate;
use Nfgarching\Files\Console\PruneTrash;
use Nfgarching\Files\Livewire\Import;
use Nfgarching\Files\Livewire\Index;
use Nfgarching\Files\Livewire\Upload;
use Nfgarching\Files\Models\File;

class FileServiceProvider extends ServiceProvider
{
    use PublishesPackageMigrations;

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->bootComponents();
        $this->bootListeners();
        $this->bootLivewire();
        $this->bootChatbotIntegration();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ArchiveFiles::class,
                InspectFiles::class,
                MakeTestCertificate::class,
                PruneTrash::class,
            ]);
        }

        // Papierkorb für ersetzte Originale täglich bereinigen
        if (config('files.archive.own.schedule_prune', true)) {
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule->command('files:prune-trash')->dailyAt('03:17')->withoutOverlapping();
            });
        }

        // Nur aus dem Package laden, wenn NICHT bereits ins Projekt publiziert.
        // Anonyme Migrationsklassen haben keinen Klassennamen, daher wird per
        // Dateiname geprüft, ob eine der Migrationen bereits publiziert wurde.
        if (! $this->migrationsArePublished(__DIR__.'/../database/migrations')) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'files-migrations');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views/layouts', 'files-layout');
        $this->loadViewsFrom(__DIR__.'/../resources/views/pages/disks', 'disks');
        $this->loadViewsFrom(__DIR__.'/../resources/views/pages/files', 'files');

        $this->app->make(ModalRegistry::class)->modal(
            'files-layout::parts.modals',
            order: 90,
            when: fn (): bool => Route::has('files.index'),
        );
        $this->app->make(NavbarNavigation::class)->item(
            'files-layout::parts.navitems',
            // 'core::navbar-files',
            order: 90,
            when: fn (): bool => Route::has('files.index'),
        );
        $this->app->make(SidebarNavigation::class)->section(
            'files-layout::parts.sidebar',
            // 'core::nav-files',
            order: 90,
            when: fn (): bool => Route::has('files.index'),
        );

        $this->publishes([
            __DIR__.'/../config/files.php' => config_path('files.php'),
        ], 'files-config');
        // $this->publishes([
        //     __DIR__.'/../resources/views' => resource_path('views/vendor/files'),
        // ], 'files-views');
    }

    private function bootComponents(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views/components', 'core');

        // Buttons  ========================================================

        Blade::component('core::button.filelink', 'button.filelink');
        Blade::component('core::button.filelink', 'button.file');
        Blade::component('core::button.filelink', 'file.button');
        Blade::component('core::button.filelink', 'file.link');
        Blade::component('core::file.filter.select', 'file.filter.select');

        Blade::component('core::storage.info', 'file.info');
        Blade::component('core::storage.info', 'storage.info');
        Blade::component('core::storage.link', 'storage.link');

        // Cards  =========================================================

        Blade::component('core::card.files', 'card.files');

        // Navigation  ====================================================

        // Blade::component('core::nav-files', 'navigation.files');
    }

    /**
     * Chatbot-Anbindung: Dateien als Dataset-Einträge einer Chat-Session.
     * Läuft nur, wenn das chatbot-Package installiert ist.
     */
    private function bootChatbotIntegration(): void
    {
        if (! class_exists(DatasetTargetRegistry::class)) {
            return;
        }

        config(['chatbot.dataset_models' => array_merge(
            config('chatbot.dataset_models', []),
            [File::class => ['label' => 'Datei', 'display' => 'name']],
        )]);
    }

    private function bootListeners(): void {}

    private function bootLivewire(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views/livewire', 'livewire');

        Livewire::component('file.import', Import::class);
        Livewire::component('file.upload', Upload::class);
        Livewire::component('files.listing', Index::class);
        Livewire::component('listing.files', Index::class);
    }

    /**
     * Register application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/files.php', 'files');
        $this->mergeConfigFrom(__DIR__.'/../config/filesystems.php', 'filesystems');
        // $this->mergeConfigFrom(__DIR__.'/../config/path.php', 'path');

        $loggingConfig = require __DIR__.'/../config/logging.php';
        $this->app['config']->set('logging.channels', array_merge(
            $this->app['config']->get('logging.channels', []),
            $loggingConfig['channels']
        ));
    }
}
