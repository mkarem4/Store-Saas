<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;

/**
 * The stock profile page with a page class, so the theme can keep this short
 * form at reading width like the other record forms.
 */
class EditProfile extends BaseEditProfile
{
    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return ['fi-auth-edit-profile-page'];
    }
}
