<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use TomatoPHP\FilamentUsers\Resources\UserResource\Form\UserForm;
use TomatoPHP\FilamentUsers\Resources\UserResource\Table\UserActions;
use Rmsramos\Activitylog\Actions\ActivityLogTimelineTableAction;
use Filament\Facades\Filament;
use App\Policies\ActivityPolicy;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Services\Vector\PineconeClient::class, \App\Services\Vector\Impl\PineconeHttpClient::class);
        $this->app->bind(\App\Services\Vector\PineconeQueryClient::class, \App\Services\Vector\Impl\PineconeQueryHttpClient::class);
            
        $this->app->bind(\App\Services\Extraction\Extractor::class, \App\Services\Extraction\Impl\HttpExtractor::class);
        $this->app->bind(\App\Services\Embedding\Embedder::class,  \App\Services\Embedding\Impl\HttpEmbedder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentColor::register([
            // 'gray' => Color::Gray,
            'slate' => Color::Slate,
            'zinc' => Color::Zinc,
            'neutral' => Color::Neutral,
            'stone' => Color::Stone,
            'red' => Color::Red,
            'orange' => Color::Orange,
            'amber' => Color::Amber,
            'yellow' => Color::Yellow,
            'lime' => Color::Lime,
            'green' => Color::Green,
            'emerald' => Color::Emerald,
            'teal' => Color::Teal,
            'cyan' => Color::Cyan,
            'sky' => Color::Sky,
            'blue' => Color::Blue,
            'indigo' => Color::Indigo,
            'violet' => Color::Violet,
            'purple' => Color::Purple,
            'fuchsia' => Color::Fuchsia,
            'pink' => Color::Pink,
            'rose' => Color::Rose,
        ]);

        Model::unguard();
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['es']);
        });

        UserForm::register([
            // \Filament\Forms\Components\Select::make('company_id')
            //     ->label('Empresa')
            //     ->native(false)
            //     ->relationship('company', 'name')
            //     ->preload()
            //     ->required(),
            \Filament\Forms\Components\TextInput::make('type')
                ->label('Tipo')
                ->required(),
        ]);

        UserActions::register([
            ActivityLogTimelineTableAction::make('Activities'),
        ]);

        Gate::policy(Activity::class, ActivityPolicy::class);

        Health::checks([
            OptimizedAppCheck::new(),
            DebugModeCheck::new(),
            EnvironmentCheck::new(),
            DatabaseCheck::new(),
            UsedDiskSpaceCheck::new(),
            ScheduleCheck::new(),
        ]);

    }
}
