<?php

namespace App\Filament\Business\Resources\Inquiries;

use App\Enums\InquiryStatus;
use App\Filament\Business\BusinessResource;
use App\Models\Inquiry;
use App\Support\Ltr;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Inquiries / booking requests from the app (D4): no payment, the store calls back.
 */
class InquiryResource extends BusinessResource
{
    protected static ?string $model = Inquiry::class;

    protected static ?string $permission = Permission::ManageOrders;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('Inquiry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Inquiries');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Inquiry::query()->where('status', InquiryStatus::New)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('name')->label(__('Name')),
            TextEntry::make('phone')->label(__('Phone'))->formatStateUsing(fn (?string $state): ?HtmlString => Ltr::wrap($state))->url(fn (Inquiry $record): string => 'tel:'.$record->phone),
            TextEntry::make('email')->label(__('Email'))->placeholder('—'),
            TextEntry::make('type')->label(__('Type'))->badge()->color('gray'),
            TextEntry::make('vehicle')->label(__('Vehicle'))->state(fn (Inquiry $record): ?string => $record->vehicle?->displayName())->placeholder('—'),
            TextEntry::make('created_at')->label(__('Received'))->dateTime(),
            TextEntry::make('message')->label(__('Message'))->placeholder('—')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('Received'))->since()->sortable(),
                TextColumn::make('name')->label(__('Name'))->searchable(),
                TextColumn::make('phone')->label(__('Phone'))->searchable()->formatStateUsing(fn (?string $state): ?HtmlString => Ltr::wrap($state)),
                TextColumn::make('type')->label(__('Type'))->badge()->color('gray'),
                TextColumn::make('vehicle')->label(__('Vehicle'))->state(fn (Inquiry $record): ?string => $record->vehicle?->displayName())->placeholder('—'),
                SelectColumn::make('status')->label(__('Status'))->options(InquiryStatus::class)->selectablePlaceholder(false),
            ])
            ->filters([SelectFilter::make('status')->options(InquiryStatus::class)])
            ->recordActions([ViewAction::make()]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListInquiries::route('/')];
    }
}
