<?php

namespace App\Filament\Widgets;

use App\Models\Veiculo;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class VeiculoDisponivel extends BaseWidget
{
    protected static ?string $heading = 'Veículos Disponíveis';
    protected static ?int $sort = 3;

    protected function getTableQuery(): Builder
    {
        return Veiculo::query()
            ->select(['id', 'modelo', 'cor', 'ano', 'placa'])
            ->where('status', 1)
            ->where('status_locado', 0)
            ->orderBy('modelo', 'asc');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('modelo')
                    ->badge()
                    ->color('success')
                    ->label('Modelo')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                
                Tables\Columns\TextColumn::make('cor')
                    ->label('Cor')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? 'Não informada'))
                    ->color('gray'),
                
                Tables\Columns\TextColumn::make('ano')
                    ->label('Ano')
                    ->sortable()
                    ->alignCenter()
                    ->color('gray'),
                
                Tables\Columns\TextColumn::make('placa')
                    ->label('Placa')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => strtoupper($state))
                    ->color('gray'),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([5, 10, 15, 20, 25])
            ->emptyStateHeading('Nenhum veículo disponível')
            ->emptyStateDescription('Todos os veículos estão locados ou inativos.')
            ->emptyStateIcon('heroicon-o-truck')
            ->deferLoading()
            ->striped()
            ->defaultSort('modelo', 'asc');
    }

    // public static function canView(): bool
    // {
    //     return Veiculo::where('status', 1)
    //         ->where('status_locado', 0)
    //         ->exists();
    // }

    public static function canView(): bool
    {
        // Sua lógica para esconder/mostrar o widget
        return false; 
    }

    protected function getTablePollingInterval(): ?string
    {
        return '30s';
    }
}