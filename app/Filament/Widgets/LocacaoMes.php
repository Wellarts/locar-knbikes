<?php

namespace App\Filament\Widgets;

use App\Models\Locacao;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Carbon\Carbon;

class LocacaoMes extends ChartWidget
{
    protected static ?string $heading = 'Locações por Mês';

    protected static ?int $sort = 8;

    public static function canView(): bool
    {
        // Sua lógica para esconder/mostrar o widget
        return false; 
    }

    protected function getData(): array
    {
        // Cache os dados por um período específico, se necessário
        $cacheKey = 'locacoes_por_mes_' . now()->format('Y');
        $data = cache()->remember($cacheKey, 60 * 60, function () {
            return Trend::model(Locacao::class)
                ->dateColumn('data_saida')
                ->between(
                    start: now()->startOfYear(),
                    end: now()->endOfYear(),
                )
                ->perMonth()
                ->sum('valor_total_desconto');
        });

        return [
            'datasets' => [
                [
                    'label' => 'Locações por Mês - Ano: ' . now()->year,
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                ],
            ],
           'labels' => $data->map(fn (TrendValue $value) => Carbon::parse($value->date)->translatedFormat('M')),
            
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
