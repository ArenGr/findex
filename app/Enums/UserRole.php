<?php

namespace App\Enums;

enum UserRole: int
{
    /** Super admin - Filament panel access (guard 'admin'). */
    case ADMIN = 1;

    /** Org staff logging in on their organization's behalf (guard 'organization'). */
    case ORGANIZATION = 2;

    /** Common users - the public-facing customer accounts (guard 'web'). */
    case CUSTOMER = 3;

    /** Partners writing/publishing articles on their profile's behalf (guard 'writer'). */
    case WRITER = 4;
}
