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

    /**
     * The findings, run live and memoised for this render.
     *
     * The view asks for both the list and the tally, and without holding the
     * result the audit would run twice per page load.
     *
     * @var Collection<int, array<string, mixed>>|null
     */
    private ?Collection $findings = null;

    /** @return Collection<int, array<string, mixed>> */
    public function getFindings(): Collection
    {
        return $this->findings ??= SeoAudit::run();
    }

    /**
     * @return array<string, int>
     *
     * Refreshes the cached tally the navigation badge reads, rather than
     * reading it. The audit has just run live for this page; leaving the badge
     * to rediscover the same numbers on the next request would be both slower
     * and briefly wrong.
     */
    public function getSummary(): array
    {
        return SeoAudit::refreshSummary($this->getFindings());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recheck')
                ->label('Check again')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                /*
                 | The findings are computed live on every render, so
                 | re-rendering IS the re-check. All this does is drop the
                 | memoised copy held for this request and refresh the cached
                 | tally behind the navigation badge.
                 */
                ->action(function (): void {
                    $this->findings = null;
                    SeoAudit::refreshSummary($this->getFindings());
                }),

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
