<?php

namespace App\Filament\Resources;

use App\Filament\Exports\CustoVeiculoExporter;
use App\Filament\Resources\CustoVeiculoResource\Pages;
use App\Filament\Resources\CustoVeiculoResource\RelationManagers;
use App\Models\Categoria;
use App\Models\ContasPagar;
use App\Models\CustoVeiculo;
use App\Models\FluxoCaixa;
use App\Models\Fornecedor;
use App\Models\Veiculo;
use Filament\Tables\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustoVeiculoResource extends Resource
{
    protected static ?string $model = CustoVeiculo::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Despesas/Manuteções';

    protected static ?string $navigationGroup = 'Despesas com Veículos';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('fornecedor_id')
                    ->searchable()
                    ->label('Fornecedor')
                    ->required()
                    ->options(Fornecedor::all()->pluck('nome', 'id')->toArray()),
                Forms\Components\Select::make('veiculo_id')
                    ->required()
                    ->label('Veículo')
                    ->relationship(
                        name: 'veiculo',
                        modifyQueryUsing: fn(Builder $query) => $query->orderBy('modelo')->orderBy('placa'),
                    )
                    ->getOptionLabelFromRecordUsing(fn(Model $record) => "{$record->modelo} {$record->placa}")
                    ->searchable(['modelo', 'placa']),

                Forms\Components\TextInput::make('km_atual')
                    ->label('Km Atual')
                    ->required(false),
                Forms\Components\Select::make('categoria_id')
                    ->label('Categoria')
                    ->searchable()
                    ->relationship('categoria', 'nome')
                    ->createOptionForm([
                        Forms\Components\TextInput::make('nome')
                            ->label('Nome')
                            ->required(),
                    ]),

                Forms\Components\DatePicker::make('data')
                    ->default(now())
                    ->required(),
                Forms\Components\Textarea::make('descricao')
                    ->label('Descrição')
                    ->autosize()
                    ->columnSpanFull()
                    ->required(false),
                Forms\Components\TextInput::make('valor')
                    ->label('Valor Total')
                    ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 2)
                    ->numeric()
                    ->prefix('R$')
                    ->inputMode('decimal')
                    ->required(),
                Forms\Components\Section::make('Financeiro')
                    ->description(fn($context) => $context === 'create' ? 'Opções para lançar a despesa no financeiro.' : 'As opções financeiras em modo de parcelamento não podem ser editadas. Para alterar, exclua e crie uma nova parcelas. Caso a situação do pagamento foi "Pago", o valor seja ajustado automaticamente no fluxo de caixa.')
                    ->disabled(fn($context) => $context === 'edit')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Toggle::make('financeiro')
                            ->label('Deseja lançar no financeiro?')
                            ->inline(false)
                            ->live()
                            ->default(fn($record) => $record && FluxoCaixa::where('despesa_id', $record->id)->exists()),
                        Forms\Components\ToggleButtons::make('pago')
                            ->label('Situação do pagamento')
                            ->options([
                                'pago' => 'Pago',
                                'a_pagar' => 'A Pagar',
                            ])
                            ->default('pago')
                            ->grouped()
                            ->live()
                            ->hidden(fn($get) => !$get('financeiro')),
                        Forms\Components\TextInput::make('parcelas')
                            ->label('Qtd de Parcelas')
                            ->default(1)
                            ->live()
                            ->visible(fn($get) => $get('financeiro') == true && $get('pago') === 'a_pagar'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('fornecedor.nome')
                    ->label('Fornecedor')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('veiculo.modelo')
                    ->label('Veículo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('veiculo.placa')
                    ->label('Placa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('data')
                    ->sortable()
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('valor')
                    ->summarize(Sum::make()->money('BRL')->label('Total'))
                    ->money('BRL')
                    ->label('Valor Total'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('fornecedor')->searchable()->relationship('fornecedor', 'nome'),
                SelectFilter::make('veiculo_id')
                    ->label('Veículo - (Modelo/Placa)')
                    ->searchable()
                    ->options(
                        fn() => Veiculo::query()
                            ->orderBy('modelo')
                            ->orderBy('placa')
                            ->get()
                            ->mapWithKeys(fn($v) => [$v->id => "{$v->modelo} {$v->placa}"])
                            ->toArray()
                    ),
                Tables\Filters\Filter::make('datas')
                    ->form([
                        DatePicker::make('data_de')
                            ->label('Saída de:'),
                        DatePicker::make('data_ate')
                            ->label('Saída ate:'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['data_de'],
                                fn($query) => $query->whereDate('data', '>=', $data['data_de'])
                            )
                            ->when(
                                $data['data_ate'],
                                fn($query) => $query->whereDate('data', '<=', $data['data_ate'])
                            );
                    })
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(CustoVeiculoExporter::class)
                    ->formats([
                        ExportFormat::Xlsx,
                    ])
                    ->columnMapping(false)
                    ->label('Exportar')
                    ->modalHeading('Confirmar exportação?'),
                Tables\Actions\Action::make('relatorio')
                    ->label('Relatório de Despesas')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->modalHeading('Relatório de Despesas/Manutenções de Veículos')
                    ->form([
                        \Filament\Forms\Components\Fieldset::make('Filtros')
                            ->columns([
                                'sm' => 1,
                                'md' => 2,
                            ])
                            ->schema([
                                Forms\Components\Select::make('veiculo_id')
                                    ->label('Veículo')
                                    ->searchable()
                                    ->options(
                                        fn() => Veiculo::query()
                                            ->orderBy('modelo')
                                            ->orderBy('placa')
                                            ->get()
                                            ->mapWithKeys(fn($v) => [$v->id => "{$v->modelo} {$v->placa}"])
                                            ->toArray()
                                    ),
                                Forms\Components\Select::make('fornecedor_id')
                                    ->label('Fornecedor')
                                    ->searchable()
                                    ->options(Fornecedor::all()->pluck('nome', 'id')->toArray()),
                            ]),
                        \Filament\Forms\Components\Fieldset::make('Datas')
                            ->columns([
                                'sm' => 1,
                                'md' => 2,
                            ])
                            ->schema([
                                DatePicker::make('data_de')
                                    ->label('Data de:'),
                                DatePicker::make('data_ate')
                                    ->label('Data até:'),
                            ]),
                        \Filament\Forms\Components\Fieldset::make('Filtros')
                            ->columns([
                                'sm' => 1,
                                'md' => 2,
                            ])
                            ->schema([
                                Forms\Components\Select::make('categoria_id')
                                    ->label('Categoria')
                                    ->searchable()
                                    ->options(Categoria::all()->pluck('nome', 'id')->toArray()),
                            ]),
                    ])
                    ->action(function (array $data, $livewire) {
                        $params = [];
                        if (!empty($data['veiculo_id'])) {
                            $params['veiculo_id'] = $data['veiculo_id'];
                        }
                        if (!empty($data['fornecedor_id'])) {
                            $params['fornecedor_id'] = $data['fornecedor_id'];
                        }
                        if (!empty($data['data_de'])) {
                            $params['data_de'] = $data['data_de']->format('Y-m-d');
                        }
                        if (!empty($data['data_ate'])) {
                            $params['data_ate'] = $data['data_ate']->format('Y-m-d');
                        }
                        if (!empty($data['categoria_id'])) {
                            $params['categoria_id'] = $data['categoria_id'];
                        }
                        $queryString = http_build_query($params);
                        $url = route('imprimirRelatorioCustoVeiculo') . ($queryString ? ('?' . $queryString) : '');
                        $livewire->js("window.open('{$url}', '_blank')");
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading('Editar custo veículo')
                    ->after(function (Model $record) {
                        if (($record->financeiro == true) && ($record->pago === 'pago')) {
                            // Ao editar uma despesa, atualizar também o lançamento no fluxo de caixa
                            $fluxo = FluxoCaixa::where('despesa_id', $record->id)->first();
                            if ($fluxo) {
                                $valor = $record->valor;
                                if (is_string($valor)) {
                                    $valor = str_replace(['R$', ' ', '.'], ['', '', ''], $valor);
                                    $valor = str_replace(',', '.', $valor);
                                    $valor = (float) $valor;
                                }

                                $fluxo->valor = ($valor * -1);
                                $fluxo->obs = ('Despesa veículo: ' . ($record->veiculo->modelo ?? '') . ' - ' . ($record->veiculo->placa ?? '') . ' - ' . ($record->descricao ?? ''));
                                $fluxo->save();
                                Notification::make()
                                    ->title('Sucesso')
                                    ->body('Lançamento no fluxo de caixa atualizado com sucesso.')
                                    ->persistent()
                                    ->success()
                                    ->send();
                            }
                        } elseif (($record->financeiro == true) && ($record->pago === 'a_pagar')) {
                            Notification::make()
                                ->title('Atenção')
                                ->body('As parcelas não foram atualizadas. Por favor, exclua e crie uma novas parcelas se necessário.')
                                ->persistent()
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\DeleteAction::make()
                    ->after(function (Model $record) {
                        if (($record->financeiro == true) && ($record->pago === 'pago')) {
                            // Ao excluir uma despesa, excluir também o lançamento no fluxo de caixa
                            $fluxo = FluxoCaixa::where('despesa_id', $record->id)->first();
                            if ($fluxo) {
                                $fluxo->delete();
                            }
                        } elseif (($record->financeiro == true) && ($record->pago === 'a_pagar')) {
                            // Ao excluir uma despesa, excluir também os lançamentos no fluxo de caixa relacionados às parcelas
                            $parcelas = ContasPagar::where('despesa_id', $record->id)->where('status', false)->get();
                            foreach ($parcelas as $parcela) {
                                $parcela = ContasPagar::find($parcela->id);
                                if ($parcela) {
                                    $parcela->delete();
                                }
                            }
                            Notification::make()
                                ->title('Sucesso')
                                ->body('Apenas as parcelas em aberto foram excluídas. As parcelas já pagas não foram excluídas, nem seus lançamentos no fluxo de caixa.')
                                ->persistent()
                                ->success()
                                ->send();
                        } else {
                            // Não há lançamento financeiro para excluir

                        }
                    }),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //  Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCustoVeiculos::route('/'),
        ];
    }
}
