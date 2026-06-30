<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SomatorioLocacao extends BaseWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        // Sua lógica para esconder/mostrar o widget
        return false; 
    }

    protected function getCards(): array
    {
        $now = Carbon::now();
        $ano = $now->year;
        $mes = $now->month;
        $hoje = $now->toDateString();

        // Chaves de cache separadas para melhor performance e granularidade
        $cacheKeys = [
            'total_geral' => "locacoes_total_geral",
            'total_mes' => "locacoes_total_mes_{$ano}_{$mes}",
            'total_dia' => "locacoes_total_dia_{$hoje}",
        ];

        // Busca dados com cache, usando tempos de expiração diferentes
        $totalGeral = Cache::remember($cacheKeys['total_geral'], now()->addHour(), function () {
            return DB::table('locacaos')
                ->select(DB::raw('COALESCE(SUM(valor_total_desconto), 0) as total'))
                ->value('total');
        });

        $totalMes = Cache::remember($cacheKeys['total_mes'], now()->addMinutes(1), function () use ($ano, $mes) {
            $inicioMes = Carbon::create($ano, $mes, 1)->startOfMonth()->toDateString();
            $fimMes = Carbon::create($ano, $mes, 1)->endOfMonth()->toDateString();

            return DB::table('locacaos')
                ->whereBetween('data_saida', [$inicioMes, $fimMes])
                ->select(DB::raw('COALESCE(SUM(valor_total_desconto), 0) as total'))
                ->value('total');
        });

        $totalDia = Cache::remember($cacheKeys['total_dia'], now()->addMinutes(1), function () use ($hoje) {
            return DB::table('locacaos')
                ->whereDate('data_saida', $hoje)
                ->select(DB::raw('COALESCE(SUM(valor_total_desconto), 0) as total'))
                ->value('total');
        });

        // Formatador reutilizável
        $formatarValor = fn($valor) => number_format($valor, 2, ",", ".");

        return [
            Stat::make('Total Geral de Locações', "R$ " . $formatarValor($totalGeral))
                ->description('Todo Período')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart([7, 3, 4, 5, 6, 3, 5, 8]) // Gráfico de exemplo
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]),

            Stat::make('Total do Mês', "R$ " . $formatarValor($totalMes))
                ->description('Este mês')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning')
                ->chart([5, 6, 3, 5, 8, 7, 4, 5]) // Gráfico de exemplo
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]),

            Stat::make('Total de Hoje', "R$ " . $formatarValor($totalDia))
                ->description('Hoje')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info')
                ->chart([2, 3, 4, 5, 1, 3, 2, 4]) // Gráfico de exemplo
                ->extraAttributes([
                    'class' => 'cursor-pointer',
                ]),
        ];
    }

    // Método para limpar cache quando necessário
    public static function clearCache(): void
    {
        $now = Carbon::now();
        $ano = $now->year;
        $mes = $now->month;
        $hoje = $now->toDateString();

        Cache::forget("locacoes_total_geral");
        Cache::forget("locacoes_total_mes_{$ano}_{$mes}");
        Cache::forget("locacoes_total_dia_{$hoje}");
    }

    // Método para invalidar cache quando nova locação é criada
    public static function invalidateCacheOnNewLocacao(): void
    {
        $now = Carbon::now();
        $ano = $now->year;
        $mes = $now->month;
        $hoje = $now->toDateString();

        // Limpa cache geral (persiste 1 hora, mas pode ser forçado)
        Cache::forget("locacoes_total_geral");
        
        // Limpa cache do mês e dia atual
        Cache::forget("locacoes_total_mes_{$ano}_{$mes}");
        Cache::forget("locacoes_total_dia_{$hoje}");
    }
}