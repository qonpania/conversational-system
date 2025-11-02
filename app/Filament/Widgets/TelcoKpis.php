<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TelcoKpis extends BaseWidget
{
    protected function getStats(): array
    {
        // Ejemplos (ajusta queries reales)
        $autoservicio = DB::table('conversations')->where('routing_mode','ai')->count();
        $total = DB::table('conversations')->count() ?: 1;

        $negativas = DB::table('conversation_metrics')->where('sentiment_overall','negative')->count();

        $aht = DB::table('conversation_metrics')->avg('avg_response_time');

        return [
            Stat::make('% Autoservicio', number_format(($autoservicio/$total)*100, 0).'%')
                ->description('Conversaciones resueltas solo por IA'),
            Stat::make('Sent. negativas', $negativas)
                ->description('Requieren atención'),
            Stat::make('AHT', $aht ? round($aht).'s' : '—')
                ->description('Average Handle Time'),
        ];
    }
}
