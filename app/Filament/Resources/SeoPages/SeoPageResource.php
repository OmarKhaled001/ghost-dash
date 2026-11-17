<?php

declare(strict_types=1);

namespace App\Filament\Resources\SeoPages;

use App\Filament\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\SeoPages\Pages\CreateSeoPage;
use App\Filament\Resources\SeoPages\Pages\EditSeoPage;
use App\Filament\Resources\SeoPages\Pages\ListSeoPages;
use App\Filament\Support\RasterImage;
use App\Models\SeoPage;
use App\Repositories\Contracts\SeoPageRepository;
use App\Services\SeoService;
use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Override;

/**
 * SEO for individual pages of the website. Site-wide SEO is on the
 * ManageSiteSeo page next to it in the SEO menu.
 */
final class SeoPageResource extends Resource
{
    protected static ?string $model = SeoPage::class;

    protected static ?string $slug = 'seo/pages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'path';

    protected static ?int $navigationSort = 2;

    /**
     * Off, the resource is neither in the menu nor reachable by URL.
     */
    #[Override]
    public static function canAccess(): bool
    {
        return SeoService::enabled() && parent::canAccess();
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return __('seo.pages.label');
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return __('seo.pages.plural');
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return __('seo.navigation');
    }

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('seo.sections.page'))
                ->columns(2)
                ->schema([
                    TextInput::make('path')
                        ->label(__('seo.fields.path'))
                        ->helperText(__('seo.hints.path'))
                        ->placeholder('/about')
                        ->required()
                        ->maxLength(255)
                        ->regex('#^\S+$#')
                        ->rule(fn (?SeoPage $record): Closure => self::uniquePath($record))
                        ->dehydrateStateUsing(fn (string $state): string => SeoService::normalizePath($state))
                        ->columnSpanFull(),

                    TextInput::make('title')
                        ->label(__('seo.fields.title'))
                        ->helperText(__('seo.hints.title'))
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Textarea::make('description')
                        ->label(__('seo.fields.description'))
                        ->helperText(__('seo.hints.description'))
                        ->rows(3)
                        ->maxLength(500)
                        ->columnSpanFull(),

                    TextInput::make('keywords')
                        ->label(__('seo.fields.keywords'))
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),

            Section::make(__('seo.sections.sharing'))
                ->columns(2)
                ->schema([
                    FileUpload::make('og_image_path')
                        ->label(__('seo.fields.og_image_path'))
                        ->helperText(__('seo.hints.og_image_path'))
                        ->image()
                        ->acceptedFileTypes(RasterImage::MIME_TYPES)
                        ->preventFilePathTampering()
                        ->disk('public')
                        ->directory('seo')
                        ->visibility('public')
                        ->maxSize(2048),

                    TextInput::make('canonical_url')
                        ->label(__('seo.fields.canonical_url'))
                        ->helperText(__('seo.hints.canonical_url'))
                        ->url()
                        ->startsWith(['https://', 'http://'])
                        ->maxLength(2048),
                ]),

            Section::make(__('seo.sections.indexing'))
                ->columns(2)
                ->schema([
                    self::robotsSelect('robots_index', __('seo.fields.robots_index')),
                    self::robotsSelect('robots_follow', __('seo.fields.robots_follow')),
                ]),
        ]);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('path')
            ->columns([
                TextColumn::make('path')
                    ->label(__('seo.fields.path'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label(__('seo.fields.title'))
                    ->searchable()
                    ->limit(60)
                    ->placeholder('—'),

                IconColumn::make('robots_index')
                    ->label(__('seo.columns.indexed'))
                    ->boolean()
                    ->placeholder(__('seo.robots.inherit')),

                TextColumn::make('updated_at')
                    ->label(__('seo.columns.updated_at'))
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                self::deleteAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->using(function (Collection $records): void {
                            $records
                                ->whereInstanceOf(SeoPage::class)
                                ->each(fn (SeoPage $page) => resolve(SeoService::class)->delete($page));
                        }),
                ]),
            ]);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index' => ListSeoPages::route('/'),
            'create' => CreateSeoPage::route('/create'),
            'edit' => EditSeoPage::route('/{record}/edit'),
        ];
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->using(function (SeoPage $record): bool {
                resolve(SeoService::class)->delete($record);

                return true;
            });
    }

    /**
     * Paths are compared as they will be stored, so "/about/" is caught as
     * a duplicate of "/about".
     *
     * @return Closure(string, mixed, Closure(string): void): void
     */
    private static function uniquePath(?SeoPage $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record): void {
            if (! is_string($value)) {
                return;
            }

            $existing = resolve(SeoPageRepository::class)->findByPath(SeoService::normalizePath($value));

            if ($existing instanceof SeoPage && $existing->id !== $record?->id) {
                $fail(__('seo.validation.path_taken'));
            }
        };
    }

    /**
     * Yes, no, or (empty) whatever the site-wide setting says.
     */
    private static function robotsSelect(string $name, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->options([1 => __('seo.robots.yes'), 0 => __('seo.robots.no')])
            ->placeholder(__('seo.robots.inherit'))
            ->formatStateUsing(fn (?bool $state): ?int => $state === null ? null : (int) $state)
            ->dehydrateStateUsing(fn (mixed $state): ?bool => $state === null || $state === '' ? null : (bool) $state)
            ->native(false);
    }
}
