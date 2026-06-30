<?php

namespace App\Filament\Resources\OrdemServicoResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Set;
use Filament\Forms\Get;
use Filament\Tables\Columns\Summarizers\Sum; 

class ItensRelationManager extends RelationManager
{
    protected static string $relationship = 'itens';

    protected static ?string $title = 'Itens da Ordem de Serviço';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('ordem_servico_id')
                    ->default((function ($livewire): int {
                                return $livewire->ownerRecord->id;
                            })),
                Forms\Components\Select::make('peca_servico_id')
                    ->relationship('pecaServico', 'nome')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\Grid::make()
                    ->columns([
                        'default' => 1,
                        'lg' => 1,
                    ])
                    ->schema([
                        Forms\Components\ToggleButtons::make('tipo')
                            ->options([
                                '0' => 'Peça',
                                '1' => 'Serviço',
                            ])
                            ->colors([
                                '0' => 'danger',
                                '1' => 'info',
                            ])
                            ->inline()
                            ->default(0)
                            ->grouped()
                            ->label(''),

                        Forms\Components\TextInput::make('nome')
                            ->required()
                            ->label('Nome da Peça/Serviço'),
                    ])
                        
                    ])
                    ->required()
                    ->label('Peça/Serviço'),                
                Forms\Components\TextInput::make('quantidade')
                    ->numeric()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => $set('valor_total', $state * $get('valor_unitario')))
                    ->required()
                    ->label('Quantidade'),
                Forms\Components\Select::make('tipo')
                    ->options([
                        '1' => 'Preventiva',
                        '2' => 'Corretiva',
                        // '3' => 'Avaria',
                        // '4' => 'Multa',
                         '5' => 'Outros'
                    ])
                    ->required()
                    ->label('Tipo'),
                Forms\Components\Textarea::make('descricao')
                    ->required(false)
                    ->autoSize()
                    ->label('Descrição'),
                Forms\Components\TextInput::make('valor_unitario')
                    ->numeric()
                    ->prefix('R$')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => $set('valor_total', $state * $get('quantidade')))
                    ->required()
                    ->label('Valor Unitário'),
                Forms\Components\TextInput::make('valor_total')
                    ->numeric()
                    ->prefix('R$')
                    ->required()
                    ->label('Valor Total'),

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ordem_servico_id')
            ->columns([
                Tables\Columns\TextColumn::make('pecaServico.nome')
                    ->label('Peça/Serviço'),
                Tables\Columns\TextColumn::make('pecaServico.tipo')
                    ->label('Categoria')
                    ->alignCenter()
                    ->formatStateUsing(fn($state) => match ($state) {
                        '0' => 'Peça',
                        '1' => 'Serviço',
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        '0' => 'danger',
                        '1' => 'info',
                    })
                    ->icon(fn($state) => match ($state) {
                        '0' => 'heroicon-o-wrench',
                        '1' => 'heroicon-o-wrench-screwdriver',
                    })
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->alignCenter()
                    ->color(fn ($state) => match ($state) {
                        '1' => 'success',
                        '2' => 'danger',
                        '3' => 'info',
                        '4' => 'primary',
                        '5' => 'secondary',
                    })
                    ->formatStateUsing(fn ($state) => $state === '1' ? 'Preventiva' : ($state === '2' ? 'Corretiva' : ($state === '3' ? 'Avaria' : ($state === '4' ? 'Multa' : 'Outros')))),
                Tables\Columns\TextColumn::make('descricao')
                    ->label('Descrição'),
                Tables\Columns\TextColumn::make('quantidade')
                    ->alignCenter()
                    ->label('Quantidade'),
                Tables\Columns\TextColumn::make('valor_unitario')
                    ->money('BRL', true)
                    ->label('Valor Unitário'),
                Tables\Columns\TextColumn::make('valor_total')
                    ->summarize(Sum::make()->money('BRL')->label('Total'))
                    ->money('BRL', true)
                    ->label('Valor Total'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Adicionar Item')
                    ->icon('heroicon-s-plus')
                    ->modalHeading('Adicionar Item à Ordem de Serviço')
                    ->after(function($record, $livewire) {
                        $ordemServico = $livewire->ownerRecord;
                        $valorTotal = $ordemServico->itens()->sum('valor_total');
                        $ordemServico->valor_total = $valorTotal;
                        $ordemServico->save();
                    }),
                  Tables\Actions\Action::make('print')
                    ->label('Imprimir Ordem de Serviço')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn($livewire) => route('imprimirDocumento', $livewire->ownerRecord->id))
                    ->openUrlInNewTab(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function($record, $livewire) {
                        $ordemServico = $livewire->ownerRecord;
                        $valorTotal = $ordemServico->itens()->sum('valor_total');
                        $ordemServico->valor_total = $valorTotal;
                        $ordemServico->save();
                    }),
                Tables\Actions\DeleteAction::make()
                   ->after(function($record, $livewire) {
                        $ordemServico = $livewire->ownerRecord;
                        $valorTotal = $ordemServico->itens()->sum('valor_total');
                        $ordemServico->valor_total = $valorTotal;
                        $ordemServico->save();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
