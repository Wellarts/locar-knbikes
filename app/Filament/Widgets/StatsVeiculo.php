<?php

namespace App\Filament\Widgets;

use App\Models\Veiculo;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class StatsVeiculo extends BaseWidget
{
    public static function canView(): bool
    {
        // Sua lógica para esconder/mostrar o widget
        return false; 
    }


    protected static ?int $sort = 1;

    // Chaves de cache separadas para melhor granularidade
    protected static string $cacheKeyTotal = 'stats:veiculo:total_active';
    protected static string $cacheKeyLocados = 'stats:veiculo:locados';
    
    // TTL diferente para cada tipo de dado
    protected static int $cacheTtlTotal = 300; // 5 minutos para total ativos (muda menos)
    protected static int $cacheTtlLocados = 60; // 1 minuto para locados (muda mais)

    protected function getCards(): array
    {
        // Buscar dados com cache granular
        $totalActive = Cache::remember(
            static::$cacheKeyTotal, 
            static::$cacheTtlTotal, 
            fn() => Veiculo::where('status', 1)->count()
        );

        $locados = Cache::remember(
            static::$cacheKeyLocados, 
            static::$cacheTtlLocados, 
            fn() => Veiculo::where('status', 1)->where('status_locado', 1)->count()
        );

        $disponiveis = max(0, $totalActive - $locados);

        // Formatação condicional para melhor visualização
        $formatDescription = function($value, $label) {
            return $value > 0 ? $label : 'Sem ' . strtolower($label);
        };

        return [
            Stat::make('Veículos Ativos', number_format($totalActive, 0, ',', '.'))
                ->description($formatDescription($totalActive, 'Ativos'))
                ->descriptionIcon($totalActive > 0 ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                ->color($totalActive > 0 ? 'warning' : 'gray')
                ->chart($this->getChartData('ativos', $totalActive))
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:shadow-md transition-shadow duration-200',
                ]),

            Stat::make('Veículos Locados', number_format($locados, 0, ',', '.'))
                ->description($formatDescription($locados, 'Locados'))
                ->descriptionIcon($locados > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($locados > 0 ? 'danger' : 'gray')
                ->chart($this->getChartData('locados', $locados))
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:shadow-md transition-shadow duration-200',
                ]),

            Stat::make('Veículos Disponíveis', number_format($disponiveis, 0, ',', '.'))
                ->description($formatDescription($disponiveis, 'Disponíveis'))
                ->descriptionIcon($disponiveis > 0 ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                ->color($disponiveis > 0 ? 'success' : 'gray')
                ->chart($this->getChartData('disponiveis', $disponiveis))
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:shadow-md transition-shadow duration-200',
                ]),
        ];
    }

    /**
     * Gera dados de gráfico simples baseado no valor atual
     */
    private function getChartData(string $tipo, int $valor): array
    {
        // Dados de gráfico baseados no tipo e valor
        if ($valor === 0) {
            return [0, 0, 0, 0, 0, 0, 0, 0];
        }

        $baseValue = max(1, min($valor, 10));
        
        return match($tipo) {
            'ativos' => [$baseValue, $baseValue * 1.1, $baseValue * 0.9, $baseValue * 1.2, $baseValue * 0.8, $baseValue * 1.1, $baseValue, $baseValue * 1.05],
            'locados' => [$baseValue, $baseValue * 1.2, $baseValue * 0.8, $baseValue * 1.3, $baseValue * 0.7, $baseValue * 1.1, $baseValue, $baseValue * 1.15],
            'disponiveis' => [$baseValue, $baseValue * 1.05, $baseValue * 0.95, $baseValue * 1.1, $baseValue * 0.9, $baseValue, $baseValue * 0.95, $baseValue * 1.02],
            default => array_fill(0, 8, $baseValue),
        };
    }

    /**
     * Método para limpar cache quando necessário
     */
    public static function clearCache(): void
    {
        Cache::forget(static::$cacheKeyTotal);
        Cache::forget(static::$cacheKeyLocados);
    }

    /**
     * Método para invalidar cache quando o status de um veículo muda
     */
    public static function invalidateCacheOnVeiculoChange(): void
    {
        static::clearCache();
        
        // Opcional: Registrar no log para debugging
        if (config('app.debug')) {
            \Log::info('Cache de StatsVeiculo invalidado devido a mudança em veículo');
        }
    }

    /**
     * Método para obter estatísticas em tempo real (sem cache)
     * Útil para operações críticas ou debugging
     */
    public static function getRealTimeStats(): array
    {
        $totalActive = Veiculo::where('status', 1)->count();
        $locados = Veiculo::where('status', 1)->where('status_locado', 1)->count();
        $disponiveis = max(0, $totalActive - $locados);

        return [
            'total_active' => $totalActive,
            'locados' => $locados,
            'disponiveis' => $disponiveis,
        ];
    }

    /**
     * Override do método para adicionar polling
     */
    protected function getPollingInterval(): ?string
    {
        // Atualiza a cada 60 segundos para dados mais recentes
        return '60s';
    }
}