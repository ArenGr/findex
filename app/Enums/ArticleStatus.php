<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ArticleStatus: string implements HasColor, HasLabel
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SUBMITTED => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }
}
