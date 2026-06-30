<?php

namespace App\Filament\Resources;

use App\Filament\Exports\VeiculoExporter;
use App\Filament\Resources\VeiculosLucratividadeResource\Pages;
use App\Filament\Resources\VeiculosLucratividadeResource\RelationManagers;
use App\Filament\Widgets\TotalLucratividade;
use App\Models\Veiculo;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ExportAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VeiculosLucratividadeResource extends Resource
{
    protected static ?string $model = Veiculo::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Lucratividade Geral';

    protected static ?string $navigationGroup = 'Consultas';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    

    
    public static function table(Table $table): Table
    {
        return $table
        ->headerActions([
            ExportAction::make()
                ->exporter(VeiculoExporter::class)
                ->formats([
                    ExportFormat::Xlsx,
                ])
                ->columnMapping(false)
                ->label('Exportar Relatório')
                ->modalHeading('Confirmar exportação?')
                ])
            ->columns([
                TextColumn::make('modelo')
                    ->searchable(),
                TextColumn::make('placa')
                    ->searchable(),
                TextColumn::make('valor_total_locacoes')
                    ->badge()
                    ->color('success')
                    ->money('BRL')
                    ->label('Locações')
                   // ->sortable()
                    ->getStateUsing(fn(Veiculo $record) => $record->Locacao->sum('valor_total_desconto')),
                TextColumn::make('valor')
                    ->badge()
                    ->alignCenter()
                    ->color('danger')
                    ->money('BRL')
                    ->label('Manutenções/Despesas')
                   // ->sortable()
                    ->getStateUsing(fn(Veiculo $record) => $record->CustoVeiculo->sum('valor')),
                TextColumn::make('lucratividade')
                    ->badge()
                    ->color('warning')
                    ->money('BRL')
                    ->getStateUsing(fn(Veiculo $record) => ($record->Locacao->sum('valor_total_desconto') - $record->CustoVeiculo->sum('valor'))),
            ])
            ->filters([
               
            ]);
        
    }

    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVeiculosLucratividades::route('/'),
            
        ];
    }

    
}
