<?php

namespace App\Filament\Pages;

use App\Models\Locacao;
use App\Models\Temp_lucratividade;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class LocacaoPorMes extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.pages.locacao-por-mes';
    protected static ?string $navigationGroup = 'Consultas';
    protected static ?string $title = 'Faturamento Mensal';
    
    protected static string $cacheKey = 'temp_lucratividade_generated';
    protected static int $cacheDuration = 3600; // 1 hora

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        // Verifica se precisa atualizar os dados da tabela temporária
        $this->updateTempTableIfNeeded();
    }

    private function updateTempTableIfNeeded(): void
    {
        // Verifica no cache se os dados estão atualizados
        $lastUpdate = Cache::get(static::$cacheKey);
        
        // Atualiza apenas uma vez por hora ou se a tabela estiver vazia
        if (!$lastUpdate || now()->diffInMinutes($lastUpdate) > 60) {
            $this->populateTempTable();
            Cache::put(static::$cacheKey, now(), static::$cacheDuration);
        }
    }

    private function populateTempTable(): void
    {
        // Desativa chaves estrangeiras para inserção mais rápida
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        // Limpa a tabela de forma otimizada
        DB::table('temp_lucratividades')->truncate();

        // Usa inserção em lote otimizada
        $batchSize = 1000;
        $insertBatch = [];
        
        // Obtém apenas os dados necessários
        $locacoes = Locacao::query()
            ->where('qtd_diarias', '>', 0)
            ->whereNotNull('data_saida')
            ->select(['id', 'cliente_id', 'veiculo_id', 'valor_total_desconto', 'qtd_diarias', 'data_saida'])
            ->cursor();

        foreach ($locacoes as $locacao) {
            $qtd = (int) $locacao->qtd_diarias;
            $valorLocacaoDia = $locacao->valor_total_desconto / $qtd;
            $startDate = Carbon::parse($locacao->data_saida);

            for ($i = 0; $i < $qtd; $i++) {
                $dataDiaria = $startDate->copy()->addDays($i);

                $insertBatch[] = [
                    'cliente_id'   => $locacao->cliente_id,
                    'veiculo_id'   => $locacao->veiculo_id,
                    'data_saida'   => $dataDiaria->format('Y-m-d H:i:s'),
                    'qtd_diaria'   => 1,
                    'valor_diaria' => $valorLocacaoDia,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];

                if (count($insertBatch) >= $batchSize) {
                    DB::table('temp_lucratividades')->insert($insertBatch);
                    $insertBatch = [];
                }
            }
        }

        if (!empty($insertBatch)) {
            DB::table('temp_lucratividades')->insert($insertBatch);
        }

        // Reativa chaves estrangeiras
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Temp_lucratividade::query()
                    ->with([
                        'cliente:id,nome',
                        'veiculo:id,modelo,placa'
                    ])
                    ->select(['id', 'cliente_id', 'veiculo_id', 'data_saida', 'qtd_diaria', 'valor_diaria'])
            )
            ->description('Esta consulta divide as locações pela quantidade de diárias e coloca cada diária da locação na data dos dias em sequência a partir da data da saída, assim teremos o valor da diária por data da utilização do veículo.')
            ->columns([
                TextColumn::make('cliente.nome')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                    
                TextColumn::make('veiculo.modelo')
                    ->sortable()
                    ->searchable()
                    ->label('Veículo')
                    ->toggleable(),
                    
                TextColumn::make('veiculo.placa')
                    ->searchable()
                    ->label('Placa')
                    ->toggleable(),
                    
                TextColumn::make('data_saida')
                    ->label('Data Saída')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                    
                TextColumn::make('qtd_diaria')
                    ->summarize(Count::make()->label('Total de Diárias'))
                    ->alignCenter()
                    ->label('Qtd Diárias')
                    ->toggleable(),
                    
                TextColumn::make('valor_diaria')
                    ->summarize(Sum::make()->money('BRL')->label('Total'))
                    ->money('BRL')
                    ->label('Valor Diária')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('cliente')
                    ->searchable()
                    ->relationship('cliente', 'nome')
                    ->preload()
                    ->multiple()
                    ->label('Cliente'),
                    
                Tables\Filters\SelectFilter::make('veiculo')
                    ->searchable()
                    ->relationship('veiculo', 'placa')
                    ->preload()
                    ->multiple()
                    ->label('Veículo (Placa)'),
                    
                Tables\Filters\Filter::make('datas')
                    ->form([
                        DatePicker::make('data_saida_de')
                            ->label('Saída de:')
                            ->displayFormat('d/m/Y'),
                            
                        DatePicker::make('data_saida_ate')
                            ->label('Saída até:')
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['data_saida_de'] ?? null,
                                fn($query) => $query->whereDate('data_saida', '>=', $data['data_saida_de'])
                            )
                            ->when($data['data_saida_ate'] ?? null,
                                fn($query) => $query->whereDate('data_saida', '<=', $data['data_saida_ate'])
                            );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['data_saida_de'] && !$data['data_saida_ate']) {
                            return null;
                        }
                        
                        $indicators = [];
                        if ($data['data_saida_de']) {
                            $indicators[] = 'A partir de ' . Carbon::parse($data['data_saida_de'])->format('d/m/Y');
                        }
                        if ($data['data_saida_ate']) {
                            $indicators[] = 'Até ' . Carbon::parse($data['data_saida_ate'])->format('d/m/Y');
                        }
                        
                        return 'Período: ' . implode(' - ', $indicators);
                    })
            ])
            ->actions([
                Tables\Actions\Action::make('refresh')
                    ->label('Atualizar Dados')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(function () {
                        $this->populateTempTable();
                        Cache::put(static::$cacheKey, now(), static::$cacheDuration);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Atualizar Dados')
                    ->modalDescription('Isso irá recalcular todos os dados de faturamento diário. Pode levar alguns minutos dependendo da quantidade de locações.')
                    ->modalSubmitActionLabel('Sim, atualizar'),
            ])
            ->bulkActions([
                // Mantém compatibilidade com Filament Excel se estiver instalado
                class_exists('pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction') 
                    ? \pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction::make()
                    : null,
            ])
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50, 100])
            ->deferLoading()
            ->poll('120s') // Atualiza a tabela a cada 2 minutos
            ->striped();
    }

    public static function clearCache(): void
    {
        Cache::forget(static::$cacheKey);
    }
}