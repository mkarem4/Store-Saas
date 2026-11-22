<?php

namespace App\Filament\Business\Resources\TimeEntries;

use App\Filament\Business\BusinessResource;
use App\Models\Employee;
use App\Models\TimeEntry;
use App\Services\AttendanceService;
use App\Support\Permission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use UnitEnum;

/**
 * Attendance (M4.3, M4.4, M4.8): owners review auto-closed entries and
 * correct times with a mandatory reason that goes to the audit log.
 */
class TimeEntryResource extends BusinessResource
{
    protected static ?string $model = TimeEntry::class;

    protected static ?string $permission = Permission::ManageAttendance;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Team';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __(static::$navigationGroup);
    }

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('Time entry');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Attendance');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = TimeEntry::query()->where('is_auto_closed', true)->whereNull('reviewed_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('clock_in_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('employee'))
            ->columns([
                TextColumn::make('employee.name')->label(__('Employee'))->searchable(),
                TextColumn::make('clock_in_at')->label(__('Clock in'))->dateTime()->sortable(),
                TextColumn::make('clock_out_at')->label(__('Clock out'))->dateTime()->placeholder(__('Still in')),
                TextColumn::make('hours')->label(__('Hours'))->state(fn (TimeEntry $record): string => number_format($record->minutesWorked() / 60, 2)),
                IconColumn::make('is_auto_closed')->label(__('Auto-closed'))->boolean()->trueColor('danger')->falseIcon(''),
                TextColumn::make('reviewed_at')->label(__('Reviewed'))->since()->placeholder('—')->toggleable(),
                TextColumn::make('clock_in_ip')->label(__('IP'))->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('review_reason')->label(__('Correction reason'))->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('employee_id')->label(__('Employee'))->options(fn (): array => Employee::query()->pluck('name', 'id')->all()),
                TernaryFilter::make('needs_review')->label(__('Needs review'))
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_auto_closed', true)->whereNull('reviewed_at'),
                        false: fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('is_auto_closed', false)->orWhereNotNull('reviewed_at')),
                    ),
                Filter::make('period')->schema([
                    DatePicker::make('from')->label(__('From'))->default(now()->startOfMonth()),
                    DatePicker::make('until')->label(__('Until')),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('clock_in_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('clock_in_at', '<=', $date))),
            ])
            ->recordActions([
                Action::make('review')->label(fn (TimeEntry $record): string => $record->is_auto_closed && $record->reviewed_at === null ? __('Review') : __('Correct'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color(fn (TimeEntry $record): string => $record->is_auto_closed && $record->reviewed_at === null ? 'danger' : 'gray')
                    ->fillForm(fn (TimeEntry $record): array => ['clock_in_at' => $record->clock_in_at, 'clock_out_at' => $record->clock_out_at])
                    ->schema([
                        DateTimePicker::make('clock_in_at')->label(__('Clock in'))->required()->seconds(false),
                        DateTimePicker::make('clock_out_at')->label(__('Clock out'))->after('clock_in_at')->seconds(false),
                        TextInput::make('reason')->label(__('Reason'))->required(),
                    ])
                    ->action(function (TimeEntry $record, array $data): void {
                        try {
                            app(AttendanceService::class)->review(
                                $record,
                                Carbon::parse($data['clock_in_at']),
                                filled($data['clock_out_at'] ?? null) ? Carbon::parse($data['clock_out_at']) : null,
                                $data['reason'],
                                (int) auth()->id(),
                            );
                            Notification::make()->success()->title(__('Saved'))->send();
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();
                        }
                    }),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListTimeEntries::route('/')];
    }
}
