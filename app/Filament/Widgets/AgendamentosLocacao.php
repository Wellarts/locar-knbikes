<?php

namespace App\Filament\Widgets;

use App\Models\Agendamento;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class AgendamentosLocacao extends BaseWidget
{
    protected static ?int $sort = 9;
    protected static ?string $heading = 'Próximos Agendamentos';
   

    protected function getTableQuery(): Builder
    {
        return Agendamento::query()
            ->with(['cliente:id,nome', 'veiculo:id,modelo,placa'])
            ->where('status', 0)
            ->orderBy('data_saida', 'asc');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('cliente.nome')
                    ->sortable()
                    ->searchable()
                    ->label('Cliente')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('veiculo.modelo')
                    ->sortable()
                    ->searchable()
                    ->label('Veículo')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('veiculo.placa')
                    ->searchable()
                    ->label('Placa')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('data_saida')
                    ->badge()
                    ->label('Data Saída')
                    ->date('d/m/Y')
                    ->color(fn($state): string => $this->getDataSaidaColor($state))
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('hora_saida')
                    ->label('Hora Saída')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('data_retorno')
                    ->label('Data Retorno')
                    ->date('d/m/Y')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('hora_retorno')
                    ->label('Hora Retorno')
                    ->toggleable(),
            ])
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50]);
    }

    private function getDataSaidaColor(string $state): string
    {
        $hoje = Carbon::today();
        $dataSaida = Carbon::parse($state);
        $qtdDias = $hoje->diffInDays($dataSaida, false);

        if ($qtdDias <= 3 && $qtdDias >= 0) {
            return 'danger';
        }

        if ($qtdDias < 0) {
            return 'warning';
        }

        return 'success';
    }

    public static function canView(): bool
    {
        return Agendamento::where('status', 0)->exists();
    }
}
