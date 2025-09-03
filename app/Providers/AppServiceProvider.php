<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Guava\FilamentKnowledgeBase\Filament\Panels\KnowledgeBasePanel;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Налаштування теми для Knowledge Base панелі
        KnowledgeBasePanel::configureUsing(
            fn(KnowledgeBasePanel $panel) => $panel
                ->viteTheme('resources/css/filament/admin/theme.css') // використовуємо ту ж тему, що й для основної панелі
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrap();
    }
}
