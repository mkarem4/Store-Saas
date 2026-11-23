<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;

/**
 * Destructive and rarely used page actions (delete, restore, force delete)
 * sit behind one quiet menu button in the page header, so they never outweigh
 * the form's own save button.
 */
class MoreActions
{
    /**
     * @param  array<Action>  $actions
     */
    public static function make(array $actions): ActionGroup
    {
        return ActionGroup::make($actions)
            ->label(__('More actions'))
            ->icon(Heroicon::EllipsisHorizontal)
            ->color('gray')
            ->button()
            ->hiddenLabel()
            ->tooltip(__('More actions'));
    }
}
