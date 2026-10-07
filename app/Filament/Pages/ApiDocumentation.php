<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Pages\Page;

class ApiDocumentation extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-command-line';
    protected static ?string $navigationLabel = 'Swagger API Docs';
    protected static ?string $title = 'Swagger API Documentation';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.pages.api-documentation';

    public string $activeView = 'swagger';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Interactive OpenAPI 3.1 documentation with parameter testing and live responses.';
    }

    public function toggleView(): void
    {
        $this->activeView = $this->activeView === 'swagger' ? 'modern' : 'swagger';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggle_view')
                ->label(fn (): string => $this->activeView === 'swagger' ? 'Switch to Modern Elements' : 'Switch to Classic Swagger')
                ->icon('heroicon-o-arrows-right-left')
                ->color('primary')
                ->action('toggleView'),

            Action::make('open_fullscreen')
                ->label('Open in New Tab')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => url($this->activeView === 'swagger' ? '/docs/swagger' : '/docs/api'), shouldOpenInNewTab: true),

            Action::make('open_json')
                ->label('OpenAPI JSON')
                ->icon('heroicon-o-code-bracket')
                ->color('gray')
                ->url(url('/docs/api.json'), shouldOpenInNewTab: true),
        ];
    }
}
