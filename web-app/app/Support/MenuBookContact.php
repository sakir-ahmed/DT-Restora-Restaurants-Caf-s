<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Branch;
use App\Models\Setting;

/**
 * How a guest reaches the restaurant, shown on the closing page of the menu book.
 */
final class MenuBookContact
{
    /**
     * @return array{phone: ?string, whatsapp: ?string, facebook: ?string, youtube: ?string, website: string}
     */
    public static function for(Branch $branch): array
    {
        $setting = Setting::current();

        return [
            'phone' => filled($branch->phone) ? (string) $branch->phone : null,
            'whatsapp' => filled($setting->social_whatsapp) ? (string) $setting->social_whatsapp : null,
            'facebook' => filled($setting->social_facebook) ? (string) $setting->social_facebook : null,
            'youtube' => filled($setting->social_youtube) ? (string) $setting->social_youtube : null,
            'website' => url('/'),
        ];
    }
}
