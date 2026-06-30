<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrdemServicoResource\Pages;
use App\Filament\Resources\OrdemServicoResource\RelationManagers;
use App\Models\OrdemServico;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\Summarizers\Sum;
use Illuminate\Database\Eloquent\Model;
use Filament\Tables\Filters\Filter;


class OrdemServicoResource extends Resource
{
    protected static ?string $model = OrdemServico::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Ordens de Serviço';

    protected static ?string $navigationGroup = 'Despesas com Veículos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'lg' => 3,
                    ])
                    ->schema([
                        Forms\Components\Select::make('cliente_id')
                            ->relationship('cliente', 'nome')
                            ->searchable()
                            ->required()
                            ->label('Cliente'),

                        Forms\Components\Select::make('fornecedor_id')
                            ->relationship('fornecedor', 'nome')
                            ->searchable()
                            ->default(1)
                            ->required()
                            ->label('Fornecedor'),

                        Forms\Components\Select::make('forma_pagamento_id')
                            ->relationship('formaPagamento', 'nome')
                            ->searchable()
                            ->preload()
                            ->default(1)
                            ->required()
                            ->label('Forma de Pagamento'),

                        Forms\Components\Select::make('veiculo_id')
                            ->required()
                            ->label('Veículo')
                            ->live(onBlur: true)
                            ->relationship('veiculo', 'modelo'),   
                            

                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->default(fn () => auth()->user()->id)
                            ->searchable()
                            ->required()
                            ->label('Realizado por'),

                        Forms\Components\DatePicker::make('data_emissao')
                            ->required()
                            ->default(now())
                            ->label('Data de Emissão'),

                        // Forms\Components\TextInput::make('km_troca')                                   
                        //     ->required()
                        //     ->hint('Km atual inserida automaticamente')
                        //     ->label('KM de Troca'),
                        Forms\Components\ToggleButtons::make('status')
                            ->options([
                                '0' => 'Pendente',
                                '1' => 'Concluído',
                            ])
                            ->colors([
                                '0' => 'danger',
                                '1' => 'success',
                            ])                           
                            ->inline()
                            ->default(0)
                            ->grouped()
                            ->label('')
                            ->required()
                            ->label('Status'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Nº OS')
                    ->alignCenter()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('cliente.nome')
                    ->searchable()
                    ->label('Cliente'),
                // Tables\Columns\TextColumn::make('fornecedor.nome')
                //     ->searchable()
                //     ->label('Fornecedor'),
                // Tables\Columns\TextColumn::make('data_emissao')
                //     ->date('d/m/Y')
                //     ->alignCenter()
                //     ->label('Data de Emissão'),
                Tables\Columns\TextColumn::make('veiculo.modelo')
                    ->searchable()
                    ->label('Veículo'),
                Tables\Columns\TextColumn::make('data_emissao')
                   ->label('Data')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                // Tables\Columns\TextColumn::make('veiculo.placa')
                //     ->searchable()
                //     ->label('Placa'),
                // Tables\Columns\TextColumn::make('status')
                //     ->label('Status')
                //     ->alignCenter()
                //     ->formatStateUsing(fn($state) => match ($state) {
                //         0 => 'Pendente',
                //         1 => 'Concluído',
                //     })
                //     ->badge()
                //     ->color(fn($state) => match ($state) {
                //         0 => 'danger',
                //         1 => 'success',
                //     })
                //     ->icon(fn($state) => match ($state) {
                //         0 => 'heroicon-o-clock',
                //         1 => 'heroicon-o-check',
                //     })
                //     ->sortable()
                //     ->searchable(),
                // Tables\Columns\TextColumn::make('km_troca')
                //     ->alignCenter()
                //     ->label('KM de Troca'),
                Tables\Columns\TextColumn::make('valor_total')
                    ->label('Valor Total')
                    ->summarize(Sum::make()->money('BRL')->label('Total Geral'))
                    ->badge()
                    ->money('BRL', true)
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        '0' => 'Pendente',
                        '1' => 'Concluído',
                    ]),              

                SelectFilter::make('cliente_id')
                    ->label('Cliente (Contrato)')
                    ->relationship('cliente', 'nome'),
                SelectFilter::make('fornecedor_id')
                    ->label('Fornecedor')
                    ->relationship('fornecedor', 'nome'),
                SelectFilter::make('user_id')
                    ->label('Autorizado Por')
                    ->relationship('user', 'name'),
                SelectFilter::make('veiculo_id')
                    ->label('Veículo')
                    ->relationship('veiculo', 'modelo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),                     
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }


    public static function getRelations(): array
    {
        return [
            RelationManagers\ItensRelationManager::class,
        ];
    }

    // public static function getGlobalActions(): array
    // {
    //     return [
    //         Tables\Actions\Action::make('relatorio')
    //             ->label('Relatório de Ordens de Serviço')
    //             ->icon('heroicon-o-printer')
    //             ->color('info')
    //             ->modalHeading('Filtrar Relatório de Ordens de Serviço')
    //             ->form([
    //                 Forms\Components\Select::make('cliente_id')
    //                     ->label('Cliente')
    //                     ->relationship('cliente', 'nome')
    //                     ->searchable()
    //                     ->preload(),
    //                 Forms\Components\Select::make('veiculo_id')
    //                     ->label('Veículo')
    //                     ->relationship('veiculo', 'modelo')
    //                     ->searchable()
    //                     ->preload(),
    //                 Forms\Components\Select::make('fornecedor_id')
    //                     ->label('Fornecedor')
    //                     ->relationship('fornecedor', 'nome')
    //                     ->searchable()
    //                     ->preload(),
    //                 Forms\Components\Select::make('forma_pagamento_id')
    //                     ->label('Forma de Pagamento')
    //                     ->relationship('formaPagamento', 'nome')
    //                     ->searchable()
    //                     ->preload(),
    //                 Forms\Components\DatePicker::make('data_inicio')
    //                     ->label('Data Inicial'),
    //                 Forms\Components\DatePicker::make('data_fim')
    //                     ->label('Data Final'),
    //             ])
    //             ->action(function(array $data) {
    //                 $query = OrdemServico::query();
    //                 if(!empty($data['cliente_id'])) {
    //                     $query->where('cliente_id', $data['cliente_id']);
    //                 }
    //                 if(!empty($data['veiculo_id'])) {
    //                     $query->where('veiculo_id', $data['veiculo_id']);
    //                 }
    //                 if(!empty($data['fornecedor_id'])) {
    //                     $query->where('fornecedor_id', $data['fornecedor_id']);
    //                 }
    //                 if(!empty($data['forma_pagamento_id'])) {
    //                     $query->where('forma_pagamento_id', $data['forma_pagamento_id']);
    //                 }
    //                 if(!empty($data['data_inicio'])) {
    //                     $query->whereDate('data_emissao', '>=', $data['data_inicio']);
    //                 }
    //                 if(!empty($data['data_fim'])) {
    //                     $query->whereDate('data_emissao', '<=', $data['data_fim']);
    //                 }
    //                 $ordemServicoRelatorio = $query->get();
    //                 $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.ordemServico.relatorio', compact('ordemServicoRelatorio'))
    //                     ->setPaper('a4', 'landscape');
    //                 return response()->streamDownload(function() use ($pdf) {
    //                     echo $pdf->stream();
    //                 }, 'ordem_servico_relatorio.pdf');
    //             })
    //     ];
    // }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrdemServicos::route('/'),
            'create' => Pages\CreateOrdemServico::route('/create'),
            'edit' => Pages\EditOrdemServico::route('/{record}/edit'),
        ];
    }
}
