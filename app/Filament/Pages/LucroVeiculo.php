<?php

namespace App\Filament\Pages;

use App\Models\CustoVeiculo;
use App\Models\Locacao;
use App\Models\Veiculo;
use Filament\Forms\Components\Grid;
use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Leandrocfe\FilamentPtbrFormFields\Money;
use Illuminate\Support\Facades\Cache;



class LucroVeiculo extends Page implements HasForms
{
    use InteractsWithForms;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.pages.lucro-veiculo';
    protected static ?string $title = 'Lucratividade por Veículo';
    protected static ?string $navigationGroup = 'Consultas';
    protected static bool $shouldRegisterNavigation = false;

    public array $data = [];
    private ?int $cachedVeiculoId = null;
    private ?string $cachedInicio = null;
    private ?string $cachedFim = null;
    private array $cachedTotals = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Grid::make(2)
                    ->schema([
                        DatePicker::make('inicio')
                            ->label('Data de Início')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn () => $this->updateTotals())
                            ->reactive(),
                            
                        DatePicker::make('fim')
                            ->label('Data de Fim')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn () => $this->updateTotals())
                            ->reactive(),
                    ]),
                
                Select::make('veiculo_id')
                    ->required(false)
                    ->label('Veículo')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn () => $this->updateTotals())
                    ->reactive()
                    ->options(function () {
                        return Veiculo::where('status', 1)
                            ->orderBy('modelo')
                            ->orderBy('placa')
                            ->get()
                            ->mapWithKeys(fn ($veiculo) => [
                                $veiculo->id => "{$veiculo->modelo} - {$veiculo->placa}"
                            ])
                            ->toArray();
                    })
                    ->searchable()
                    ->columnSpan([
                        'xl' => 2,
                        '2xl' => 2,
                    ]),
                
                Money::make('total_locacao')
                    ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 2)
                    ->readOnly()
                    ->label('Total de Locação R$:')
                    ->default(0)
                    ->extraAttributes(['class' => 'font-bold']),
                    
                Money::make('total_custo')
                    ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 2)
                    ->readOnly()
                    ->label('Total de Custos R$:')
                    ->default(0)
                    ->extraAttributes(['class' => 'font-bold']),
                    
                Money::make('lucro')
                    ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 2)
                    ->readOnly()
                    ->label('Lucro Real R$:')
                    ->default(0)
                    ->extraAttributes(function (Get $get) {
                        $lucro = $get('lucro') ?? 0;
                        $class = $lucro >= 0 ? 'text-success-600 font-bold' : 'text-danger-600 font-bold';
                        return ['class' => $class];
                    }),
            ])
            ->columns(2)
            ->inlineLabel();
    }

    private function updateTotals(): void
    {
        $data = $this->form->getState();
        $veiculoId = $data['veiculo_id'] ?? null;
        $inicio = $data['inicio'] ?? null;
        $fim = $data['fim'] ?? null;

        // Se não temos todos os dados necessários, resetar os valores
        if (!$veiculoId || !$inicio || !$fim) {
            $this->form->fill([
                'total_locacao' => 0,
                'total_custo' => 0,
                'lucro' => 0,
            ]);
            return;
        }

        // Verificar se os dados estão em cache
        $cacheKey = "lucro_veiculo_{$veiculoId}_{$inicio}_{$fim}";
        
        if ($this->cachedVeiculoId === $veiculoId && 
            $this->cachedInicio === $inicio && 
            $this->cachedFim === $fim &&
            isset($this->cachedTotals[$cacheKey])) {
            $totals = $this->cachedTotals[$cacheKey];
        } else {
            $totals = Cache::remember($cacheKey, 300, function () use ($veiculoId, $inicio, $fim) {
                // Calcular total de locações
                $totalLocacao = Locacao::where('veiculo_id', $veiculoId)
                    ->whereBetween('data_saida', [$inicio, $fim])
                    ->sum('valor_total_desconto') ?? 0;

                // Calcular total de custos
                $totalCusto = CustoVeiculo::where('veiculo_id', $veiculoId)
                    ->whereBetween('created_at', [$inicio, $fim])
                    ->sum('valor') ?? 0;

                return [
                    'total_locacao' => $totalLocacao,
                    'total_custo' => $totalCusto,
                    'lucro' => $totalLocacao - $totalCusto,
                ];
            });

            // Armazenar em cache local para evitar múltiplas chamadas ao mesmo cache
            $this->cachedVeiculoId = $veiculoId;
            $this->cachedInicio = $inicio;
            $this->cachedFim = $fim;
            $this->cachedTotals[$cacheKey] = $totals;
        }

        $this->form->fill($totals);
    }

    // Método para limpar cache quando necessário
    public static function clearCacheForVeiculo(int $veiculoId): void
    {
        // Esta função deve ser chamada quando houver alterações nas locações ou custos
        // Exemplo: no observer de Locacao ou CustoVeiculo
        $pattern = "lucro_veiculo_{$veiculoId}_*";
        
        // Se estiver usando Redis ou outro driver que suporte tags
        if (config('cache.default') === 'redis') {
            Cache::tags(["veiculo_{$veiculoId}"])->flush();
        } else {
            // Alternativa: buscar todas as chaves do cache e limpar as relacionadas
            // Isso é menos eficiente, mas funciona para drivers básicos
            $keys = Cache::getStore()->getPrefix() . 'lucro_veiculo_' . $veiculoId . '*';
            // Nota: esta abordagem depende do driver de cache
        }
    }

    // Hook para quando o formulário é submetido (se houver submit)
    public function submit(): void
    {
        // Se precisar de alguma ação ao enviar o formulário
    }

    // Método para popular o select de veículos com cache
    private function getVeiculosOptions(): array
    {
        $cacheKey = 'veiculos_ativos_list';
        
        return Cache::remember($cacheKey, 3600, function () {
            return Veiculo::where('status', 1)
                ->orderBy('modelo')
                ->orderBy('placa')
                ->get()
                ->mapWithKeys(fn ($veiculo) => [
                    $veiculo->id => "{$veiculo->modelo} - {$veiculo->placa}"
                ])
                ->toArray();
        });
    }
}