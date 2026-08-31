<?php

namespace App\Filament\Pages;

use App\Support\SeoAudit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * "What should I fix?" — the entry point to the whole SEO section.
 *
 * First in its navigation group on purpose. The settings screens beside it
 * assume you already know what to fill in; this one tells you, which is the
 * difference between a feature a doctor uses and one they open once.
 *
 * @see SeoAudit
 */
class SeoHealth extends Page
{
    protected string $view = 'filament.pages.seo-health';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Search & visibility';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Health check';

    protected static ?string $title = 'Search health check';

    public function getSubheading(): ?string
    {
        return 'What is stopping this website being found, worst first. Everything here is checked against your real content.';
    }

    /**
     * The badge on the navigation item.
     *
     * Only ever shows a count when something is genuinely broken. A badge that
     * is permanently lit stops meaning anything, so warnings and suggestions
     * are deliberately left out of it — they are visible on the screen itself.
     */
    public static function getNavigationBadge(): ?string
    {
        $critical = SeoAudit::summary()['critical'];

        return $critical > 0 ? (string) $critical : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /** @return Collection<int, array<string, mixed>> */
    public function getFindings(): Collection
    {
        return SeoAudit::run();
    }

    /** @return array<string, int> */
    public function getSummary(): array
    {
        return SeoAudit::summary();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recheck')
                ->label('Check again')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                // Everything is computed on render, so re-rendering IS the
                // re-check. No state to clear, nothing to queue.
                ->action(fn () => null),

            Action::make('robots')
                ->label('View robots.txt')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(route('robots'), shouldOpenInNewTab: true),

            Action::make('sitemap')
                ->label('View sitemap')
                ->icon('heroicon-o-map')
                ->color('gray')
                ->url(route('sitemap'), shouldOpenInNewTab: true),
        ];
    }
}
