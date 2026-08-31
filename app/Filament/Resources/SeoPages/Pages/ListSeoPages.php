<?php

namespace App\Filament\Resources\SeoPages\Pages;

use App\Filament\Resources\SeoPages\SeoPageResource;
use App\Models\SeoPage;
use Filament\Resources\Pages\ListRecords;

class ListSeoPages extends ListRecords
{
    protected static string $resource = SeoPageResource::class;

    public function getSubheading(): ?string
    {
        return 'One row for each page of your website. Fill in a title and description to control how it looks in Google.';
    }

    /**
     * Keep the list in step with the site's pages.
     *
     * Writing on a page load is not something to do casually, but this is
     * `firstOrCreate` against a nine-row table on an admin screen: it is
     * idempotent, it costs nothing, and it is the difference between a doctor
     * opening this screen and seeing their website, or seeing "No records" and
     * a Create button asking them to invent a route name.
     */
    public function mount(): void
    {
        parent::mount();

        SeoPage::syncManagedPages();
    }
}
