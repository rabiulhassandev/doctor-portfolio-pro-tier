<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * What sort of document the doctor has issued to a patient.
 *
 * Purely descriptive — it drives the icon and the grouping on the patient's
 * dashboard, so a patient hunting for last month's blood test is not reading a
 * flat list of filenames. It carries no access rules; those live in
 * App\Policies\MedicalDocumentPolicy.
 */
enum DocumentKind: string implements HasColor, HasIcon, HasLabel
{
    case Prescription = 'prescription';
    case Report = 'report';
    case Invoice = 'invoice';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Prescription => 'Prescription',
            self::Report => 'Test or scan report',
            self::Invoice => 'Invoice or receipt',
            self::Other => 'Other document',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Prescription => 'success',
            self::Report => 'info',
            self::Invoice => 'warning',
            self::Other => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Prescription => 'heroicon-o-clipboard-document-list',
            self::Report => 'heroicon-o-beaker',
            self::Invoice => 'heroicon-o-receipt-percent',
            self::Other => 'heroicon-o-document',
        };
    }
}
