<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class TotalLucratividade extends BaseWidget
{
    public static function canView(): bool
    {
        // Sua lógica para esconder/mostrar o widget
        return false; 
    }

    protected static ?string $pollingInterval = '30s';
    protected static ?int $sort = 5;

    // Chaves de cache separadas para cada métrica
    protected static string $cacheKeyFaturamento = 'dashboard_lucratividade_faturamento';
    protected static string $cacheKeyCustos = 'dashboard_lucratividade_custos';
    
    // TTL diferente para cada métrica baseado na frequência de mudança
    protected static int $cacheTtlFaturamento = 300; // 5 minutos
    protected static int $cacheTtlCustos = 180; // 3 minutos (custos podem mudar mais frequentemente)

    protected function getCards(): array
    {
        // Buscar faturamento com cache
        $faturamentoTotal = Cache::remember(
            static::$cacheKeyFaturamento,
            static::$cacheTtlFaturamento,
            fn() => DB::table('locacaos')->sum('valor_total_desconto') ?? 0
        );

        // Buscar custos com cache
        $custosTotal = Cache::remember(
            static::$cacheKeyCustos,
            static::$cacheTtlCustos,
            fn() => DB::table('custo_veiculos')->sum('valor') ?? 0
        );

        // Calcular lucro
        $lucroTotal = $faturamentoTotal - $custosTotal;

        // Formatar valores reutilizavelmente
        $formatarValor = fn($valor) => 'R$ ' . number_format($valor, 2, ',', '.');

        // Calcular margem de lucro (evitando divisão por zero)
        $margemLucro = $faturamentoTotal > 0 
            ? round(($lucroTotal / $faturamentoTotal) * 100, 2)
            : 0;

        return [
            Stat::make('Faturamento Total', $formatarValor($faturamentoTotal))
                ->description($this->getFaturamentoDescription($faturamentoTotal))
                ->descriptionIcon($this->getFaturamentoIcon($faturamentoTotal))
                ->color('success')
                ->chart($this->getChartData('faturamento', $faturamentoTotal))
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:shadow-md transition-shadow duration-200',
                    //'title' => 'Clique para detalhes do faturamento',
                ]),
            
            Stat::make('Custos Totais', $formatarValor($custosTotal))
                ->description($this->getCustosDescription($custosTotal))
                ->descriptionIcon($this->getCustosIcon($custosTotal))
                ->color('danger')
                ->chart($this->getChartData('custos', $custosTotal))
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:shadow-md transition-shadow duration-200',
                   // 'title' => 'Clique para detalhes dos custos',
                ]),
            
            Stat::make('Lucro Líquido', $formatarValor($lucroTotal))
                ->description($this->getLucroDescription($lucroTotal, $margemLucro))
                ->descriptionIcon($this->getLucroIcon($lucroTotal))
                ->color($lucroTotal >= 0 ? 'success' : 'danger')
                ->chart($this->getChartData('lucro', $lucroTotal))
                ->extraAttributes([
                    'class' => 'cursor-pointer hover:shadow-md transition-shadow duration-200',
                   // 'title' => 'Clique para detalhes do lucro',
                ]),
        ];
    }

    /**
     * Gera dados de gráfico simples baseado no valor e tipo
     */
    private function getChartData(string $tipo, float $valor): array
    {
        if ($valor == 0) {
            return [0, 0, 0, 0, 0, 0, 0, 0];
        }

        $normalizedValue = log10(abs($valor) + 1);
        
        return match($tipo) {
            'faturamento' => [
                $normalizedValue * 0.9,
                $normalizedValue * 1.1,
                $normalizedValue * 0.85,
                $normalizedValue * 1.2,
                $normalizedValue * 0.8,
                $normalizedValue * 1.15,
                $normalizedValue * 0.95,
                $normalizedValue * 1.05
            ],
            'custos' => [
                $normalizedValue * 0.85,
                $normalizedValue * 1.15,
                $normalizedValue * 0.8,
                $normalizedValue * 1.25,
                $normalizedValue * 0.75,
                $normalizedValue * 1.2,
                $normalizedValue * 0.9,
                $normalizedValue * 1.1
            ],
            'lucro' => [
                $normalizedValue * 0.8,
                $normalizedValue * 1.2,
                $normalizedValue * 0.7,
                $normalizedValue * 1.3,
                $normalizedValue * 0.6,
                $normalizedValue * 1.25,
                $normalizedValue * 0.85,
                $normalizedValue * 1.15
            ],
            default => array_fill(0, 8, $normalizedValue),
        };
    }

    /**
     * Descrições dinâmicas baseadas nos valores
     */
    private function getFaturamentoDescription(float $valor): string
    {
        return $valor > 0 
            ? 'Faturamento acumulado' 
            : 'Sem faturamento registrado';
    }

    private function getCustosDescription(float $valor): string
    {
        return $valor > 0 
            ? 'Custos operacionais totais' 
            : 'Sem custos registrados';
    }

    private function getLucroDescription(float $lucro, float $margem): string
    {
        if ($lucro > 0) {
            return "Lucratividade: {$margem}%";
        } elseif ($lucro < 0) {
            return "Prejuízo: {$margem}%";
        }
        return 'Equilíbrio financeiro';
    }

    /**
     * Ícones dinâmicos baseados nos valores
     */
    private function getFaturamentoIcon(float $valor): string
    {
        return $valor > 0 
            ? 'heroicon-o-currency-dollar' 
            : 'heroicon-o-x-circle';
    }

    private function getCustosIcon(float $valor): string
    {
        return $valor > 0 
            ? 'heroicon-o-wrench-screwdriver' 
            : 'heroicon-o-check-circle';
    }

    private function getLucroIcon(float $lucro): string
    {
        return $lucro >= 0 
            ? 'heroicon-o-chart-bar' 
            : 'heroicon-o-arrow-trending-down';
    }

    /**
     * Método para limpar cache quando necessário
     */
    public static function clearCache(): void
    {
        Cache::forget(static::$cacheKeyFaturamento);
        Cache::forget(static::$cacheKeyCustos);
    }

    /**
     * Método para invalidar cache quando nova locação é criada
     */
    public static function invalidateCacheOnNewLocacao(): void
    {
        Cache::forget(static::$cacheKeyFaturamento);
    }

    /**
     * Método para invalidar cache quando novo custo é adicionado
     */
    public static function invalidateCacheOnNewCusto(): void
    {
        Cache::forget(static::$cacheKeyCustos);
    }

    /**
     * Método para obter estatísticas em tempo real (sem cache)
     */
    public static function getRealTimeStats(): array
    {
        $faturamento = DB::table('locacaos')->sum('valor_total_desconto') ?? 0;
        $custos = DB::table('custo_veiculos')->sum('valor') ?? 0;
        $lucro = $faturamento - $custos;

        return [
            'faturamento' => $faturamento,
            'custos' => $custos,
            'lucro' => $lucro,
        ];
    }

    /**
     * Configuração de polling condicional baseada em ambiente
     */
    protected function getPollingInterval(): ?string
    {
        // Em ambiente de produção, atualiza a cada 30s, em desenvolvimento desativa
        return app()->environment('production') ? '30s' : null;
    }
}