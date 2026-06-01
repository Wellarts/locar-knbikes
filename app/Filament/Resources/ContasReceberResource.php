<?php

namespace App\Filament\Resources;

use App\Filament\Exports\ContasPagarExporter;
use App\Filament\Resources\ContasReceberResource\Pages;
use App\Filament\Exports\ContasReceberExporter;
use App\Models\Cliente;
use App\Models\ContasReceber;
use App\Models\FluxoCaixa;
use App\Models\Categoria;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Infolists\Components\Tabs\Tab;
use Illuminate\Support\Facades\DB;

class ContasReceberResource extends Resource
{
    protected static ?string $model = ContasReceber::class;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $title = 'Recebimentos';
    protected static ?string $navigationLabel = 'Recebimentos';
    protected static ?string $navigationGroup = 'Financeiro';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Cliente com busca otimizada
                Forms\Components\Select::make('cliente_id')
                    ->disabled(fn($context) => $context === 'edit')
                    ->label('Cliente')
                    ->searchable()
                    ->getSearchResultsUsing(
                        fn(string $search): array =>
                        Cliente::where('nome', 'like', "%{$search}%")
                            ->limit(50)
                            ->pluck('nome', 'id')
                            ->toArray()
                    )
                    ->getOptionLabelUsing(
                        fn($value): ?string =>
                        Cliente::find($value)?->nome
                    )
                    ->required()
                    ->preload(),

                // Valor Total
                Forms\Components\TextInput::make('valor_total')
                    ->disabled(fn($context) => $context === 'edit')
                    ->label('Valor Total')
                    ->numeric()
                    ->prefix('R$')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                        self::calcularValores($get, $set, $state);
                    }),

                // Categoria com pré-carregamento
                Forms\Components\Select::make('categoria_id')
                    ->label('Categoria')
                    ->relationship('categoria', 'nome')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                    ]),

                // Próxima Parcela
                Forms\Components\Select::make('proxima_parcela')
                    ->hiddenOn('edit')
                    ->options([
                        '7' => 'Semanal',
                        '15' => 'Quinzenal',
                        '30' => 'Mensal',
                    ])
                    ->default(30)
                    ->label('Próximas Parcelas')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        $set('data_vencimento', now()->addDays($get('proxima_parcela'))->format('Y-m-d'));
                    }),

                // Parcelas
                Forms\Components\TextInput::make('parcelas')
                    ->hiddenOn('edit')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        self::calcularValores($get, $set, $get('valor_total'));
                    }),

                // Restante do formulário mantido similar...
                Forms\Components\Select::make('forma_pgmto_id')
                    ->relationship('formaPgmto', 'nome')
                    ->label('Forma de Pagamento')
                    ->required(false),

                Forms\Components\Hidden::make('ordem_parcela')
                    ->default('1'),

                Forms\Components\DatePicker::make('data_vencimento')
                    ->displayFormat('d/m/Y')
                    ->default(now()->addDays(30))
                    ->label("Data do Vencimento")
                    ->required(),

                Forms\Components\Toggle::make('status')
                    ->default(false)
                    ->label('Recebido')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                        if ($state) {
                            $set('valor_recebido', $get('valor_parcela'));
                            $set('data_recebimento', now()->format('Y-m-d'));
                        } else {
                            $set('valor_recebido', 0);
                            $set('data_recebimento', null);
                        }
                    }),

                Forms\Components\TextInput::make('valor_parcela')
                    ->label('Valor Parcela')
                    ->numeric()
                    ->prefix('R$')
                    ->readOnly(),

                Forms\Components\Textarea::make('obs')
                    ->label('Observações')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Função auxiliar para cálculos
     */
    private static function calcularValores(Get $get, Set $set, ?string $valorTotal): void
    {
        $parcelas = (int) $get('parcelas') ?: 1;
        $valorTotal = (float) $valorTotal ?: 0;

        if ($parcelas > 1) {
            $valorParcela = $valorTotal / $parcelas;
            $set('valor_parcela', number_format($valorParcela, 2, '.', ''));
            $set('status', false);
            $set('valor_recebido', 0);
            $set('data_recebimento', null);
        } else {
            $set('valor_parcela', number_format($valorTotal, 2, '.', ''));
            $set('status', true);
            $set('valor_recebido', number_format($valorTotal, 2, '.', ''));
            $set('data_recebimento', now()->format('Y-m-d'));
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('data_vencimento', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('locacao_id')
                    ->label('Locação ID')
                    ->alignCenter()
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cliente.nome')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('ordem_parcela')
                    ->sortable()
                    ->alignCenter()
                    ->label('Parcela Nº')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('data_vencimento')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->badge()
                    ->sortable()
                    ->color(fn($record) => $record->status ? 'success' : ($record->data_vencimento < now() ? 'danger' : 'warning'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('valor_parcela')
                    ->label('Valor Parcela')
                    ->money('BRL')
                    ->summarize(Sum::make()->money('BRL')->label('Total'))
                    ->toggleable(),
                Tables\Columns\IconColumn::make('status')
                    ->label('Recebido')
                    ->icon(fn($record) => $record->status ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                    ->color(fn($record) => $record->status ? 'success' : 'danger')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('valor_recebido')
                    ->label('Valor Recebido')
                    ->money('BRL')
                    ->summarize(Sum::make()->money('BRL')->label('Recebido'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('formaPgmto.nome')
                    ->label('Forma de Pagamento')
                    ->sortable(),

            ])
            ->filters([
                Filter::make('pendentes')
                    ->query(fn($query) => $query->where('status', false))
                    ->default(),

                Filter::make('vencidas')
                    ->query(fn($query) => $query->where('data_vencimento', '<', now())
                        ->where('status', false)),

                SelectFilter::make('cliente')
                    ->relationship('cliente', 'nome')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('categoria')
                    ->relationship('categoria', 'nome')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('data_vencimento')
                    ->form([
                        Forms\Components\DatePicker::make('vencimento_de')
                            ->label('Vencimento de:'),
                        Forms\Components\DatePicker::make('vencimento_ate')
                            ->label('Vencimento até:'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['vencimento_de'],
                                fn($query) => $query->whereDate('data_vencimento', '>=', $data['vencimento_de'])
                            )
                            ->when(
                                $data['vencimento_ate'],
                                fn($query) => $query->whereDate('data_vencimento', '<=', $data['vencimento_ate'])
                            );
                    })
            ])
            ->headerActions([
                Tables\Actions\Action::make('relatorio')
                    ->label('Relatório - PDF')
                    ->color('info')
                    ->icon('heroicon-o-document-text')
                    ->modalHeading('Filtrar Relatório - Recebimentos')
                    ->form([
                        Forms\Components\Fieldset::make('Filtros do Relatório')
                            ->columns(2)
                            ->schema([
                                Forms\Components\Select::make('cliente_id')
                                    ->label('Cliente')
                                    ->relationship('cliente', 'nome')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),

                                Forms\Components\Select::make('categoria_id')
                                    ->label('Categoria')
                                    ->relationship('categoria', 'nome')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),

                                Forms\Components\Select::make('forma_pgmto_id')
                                    ->label('Forma de Pagamento')
                                    ->relationship('formaPgmto', 'nome')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),

                                Forms\Components\Select::make('status')
                                    ->label('Recebido')
                                    ->options([
                                        '' => 'Todos',
                                        '1' => 'Sim',
                                        '0' => 'Não',
                                    ])
                                    ->nullable(),

                                Forms\Components\DatePicker::make('data_vencimento_inicio')
                                    ->label('Vencimento (Início)')
                                    ->displayFormat('d/m/Y')
                                    ->nullable(),

                                Forms\Components\DatePicker::make('data_vencimento_fim')
                                    ->label('Vencimento (Fim)')
                                    ->displayFormat('d/m/Y')
                                    ->nullable(),

                                Forms\Components\DatePicker::make('data_recebimento_inicio')
                                    ->label('Recebimento (Início)')
                                    ->displayFormat('d/m/Y')
                                    ->nullable(),

                                Forms\Components\DatePicker::make('data_recebimento_fim')
                                    ->label('Recebimento (Fim)')
                                    ->displayFormat('d/m/Y')
                                    ->nullable(),
                            ])
                    ])
                     ->action(function (array $data, $livewire) {
                        // Remove apenas as chaves com valores vazios ou nulos, mas mantém 0
                        $filteredData = [];
                        foreach ($data as $key => $value) {
                            if ($value !== '' && $value !== null) {
                                $filteredData[$key] = $value;
                            }
                        }

                        $query = http_build_query($filteredData);
                        $url = route('imprimirContasReceberRelatorio') . '?' . $query;
                        $livewire->js("window.open('{$url}', '_blank')");
                    }),
                Tables\Actions\ExportAction::make()
                    ->exporter(ContasReceberExporter::class)
                    ->formats([
                        ExportFormat::Xlsx,
                    ])
                    ->columnMapping(false)
                    ->label('Exportar Contas')
                    ->modalHeading('Confirmar exportação?'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function ($record) {
                        if ($record->status) {
                            DB::transaction(function () use ($record) {
                                FluxoCaixa::create([
                                    'valor' => $record->valor_parcela,
                                    'tipo'  => 'CREDITO',
                                    'locacao_id' => $record->locacao_id,
                                    'contas_receber_id' => $record->id,
                                    'obs'   => "Recebimento da conta do cliente {$record->cliente->nome} - Forma de Pagamento: {$record->formaPgmto->nome}",
                                ]);
                            });
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->after(function ($record) {
                        if ($record->status) {
                            DB::transaction(function () use ($record) {
                                FluxoCaixa::where('contas_receber_id', $record->id)->delete();
                            });
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function ($records) {
                            DB::transaction(function () use ($records) {
                                foreach ($records as $record) {
                                    if ($record->status) {
                                        FluxoCaixa::where('contas_receber_id', $record->id)->delete();
                                    }
                                }
                            });
                        }),
                    Tables\Actions\BulkAction::make('marcarComoRecebido')
                        ->label('Marcar como recebido')
                        ->icon('heroicon-o-check-circle')
                        ->action(function ($records) {
                            DB::transaction(function () use ($records) {
                                foreach ($records as $record) {
                                    $record->update([
                                        'status' => true,
                                        'data_recebimento' => now(),
                                        'valor_recebido' => $record->valor_parcela,
                                    ]);

                                    FluxoCaixa::create([
                                        'valor' => $record->valor_parcela,
                                        'locacao_id' => $record->locacao_id,
                                        'contas_receber_id' => $record->id,
                                        'tipo' => 'CREDITO',
                                        'obs' => "Recebimento da conta do cliente {$record->cliente->nome} - Forma de Pagamento: " . ($record->formaPgmto ? $record->formaPgmto->nome : 'N/A'),
                                    ]);
                                }
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->deferLoading(); // Carrega dados apenas quando necessário
        //>poll('30s'); // Atualiza a cada 30 segundos
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageContasRecebers::route('/'),
        ];
    }
}
