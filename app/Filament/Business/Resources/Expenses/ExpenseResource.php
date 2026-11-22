<?php

namespace App\Filament\Business\Resources\Expenses;

use App\Enums\ExpenseSource;
use App\Filament\Business\BusinessPanel;
use App\Filament\Business\BusinessResource;
use App\Filament\Forms\Components\MoneyInput;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Expenses (S8, M5): manual entries, recurring templates (rent), receipt
 * photos. Payroll, payment fees and platform commission are booked by the
 * system and are read-only here.
 */
class ExpenseResource extends BusinessResource
{
    protected static ?string $model = Expense::class;

    protected static ?string $permission = Permission::ManageExpenses;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('Expense');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Expenses');
    }

    /**
     * @return array<int, string>
     */
    public static function categoryOptions(): array
    {
        return ExpenseCategory::query()
            ->where(fn ($query) => $query->whereNull('business_id')->orWhere('business_id', BusinessPanel::business()?->id))
            ->get()
            ->mapWithKeys(fn (ExpenseCategory $category): array => [$category->id => $category->translatedName()])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('expense_category_id')->label(__('Category'))->options(fn (): array => self::categoryOptions())->required(),
            MoneyInput::make('amount_cents')->label(__('Amount'))->required()->minValue(0.01),
            TextInput::make('description')->label(__('Description'))->required(),
            DatePicker::make('expense_date')->label(__('Date'))->default(now())->required(),
            Toggle::make('is_recurring')->label(__('Repeats automatically'))->live()
                ->helperText(__('Creates this expense every period from the next due date.')),
            Select::make('recurrence')->label(__('Every'))->options(['weekly' => __('Week'), 'monthly' => __('Month'), 'yearly' => __('Year')])->default('monthly')
                ->visible(fn (Get $get): bool => (bool) $get('is_recurring'))->required(fn (Get $get): bool => (bool) $get('is_recurring')),
            DatePicker::make('next_due_date')->label(__('Next due date'))
                ->visible(fn (Get $get): bool => (bool) $get('is_recurring'))->required(fn (Get $get): bool => (bool) $get('is_recurring')),
            SpatieMediaLibraryFileUpload::make('receipt')->label(__('Invoice photo'))->collection('receipt')->image()->columnSpanFull(),
            Textarea::make('notes')->label(__('Notes'))->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $currency = BusinessPanel::currency();
        $editable = fn (Expense $record): bool => in_array($record->source, [ExpenseSource::Manual, ExpenseSource::Recurring], true) || $record->is_recurring;

        return $table
            ->defaultSort('expense_date', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category'))
            ->columns([
                TextColumn::make('expense_date')->label(__('Date'))->date()->sortable(),
                TextColumn::make('category.name')->label(__('Category'))->formatStateUsing(fn (Expense $record): string => (string) $record->category?->translatedName())->badge()->color('gray'),
                TextColumn::make('description')->label(__('Description'))->searchable()->limit(50),
                TextColumn::make('amount_cents')->label(__('Amount'))->money($currency, divideBy: 100)->sortable()
                    ->summarize(Sum::make()->money($currency, divideBy: 100)),
                TextColumn::make('source')->label(__('Source'))->badge()->color('gray'),
                IconColumn::make('is_recurring')->label(__('Template'))->boolean()->falseIcon('')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('expense_category_id')->label(__('Category'))->options(fn (): array => self::categoryOptions()),
                SelectFilter::make('source')->options(ExpenseSource::class),
                Filter::make('templates')->label(__('Recurring templates'))->query(fn (Builder $query) => $query->where('is_recurring', true)),
                Filter::make('period')->schema([
                    DatePicker::make('from')->label(__('From'))->default(now()->startOfMonth()),
                    DatePicker::make('until')->label(__('Until')),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('expense_date', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('expense_date', '<=', $date))),
            ])
            ->recordActions([
                EditAction::make()->visible($editable)->after(fn (Expense $record) => self::audit($record, 'expense.updated')),
                DeleteAction::make()->visible($editable)->before(fn (Expense $record) => self::audit($record, 'expense.deleted')),
            ]);
    }

    public static function audit(Expense $expense, string $event): void
    {
        activity('expenses')->performedOn($expense)->causedBy(auth()->user())
            ->withProperties($expense->only(['amount_cents', 'description', 'expense_date']))
            ->log($event);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepare(array $data): array
    {
        $data['source'] = ExpenseSource::Manual;
        $data['created_by'] = auth()->id();

        if (! ($data['is_recurring'] ?? false)) {
            $data['recurrence'] = null;
            $data['next_due_date'] = null;
        }

        return $data;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageExpenses::route('/')];
    }
}
