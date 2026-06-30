@extends('layouts.pdf')

@section('title', 'Relatório de Ordens de Serviço')

@section('content')
<style>
    /* Estilos principais mantidos do layout original */
    body {
        font-family: Arial, sans-serif;
        color: #222;
        font-size: 13px;
        margin: 0;
        padding: 0;
        background: #fff;
    }

    .container {
        max-width: 100%;
        margin: 0;
        background: #fff;
        border-radius: 12px;
        padding: 0;
        box-shadow: 0 2px 16px rgba(0, 0, 0, 0.07);
    }

    h1 {
        text-align: center;
        color: #2d3a4a;
        margin: 24px 0 24px 0;
        font-weight: 700;
        font-size: 1.3em;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 24px;
        background: #fff;
        border-radius: 8px;
    }

    th,
    td {
        padding: 8px;
        border-bottom: 1px solid #e9ecef;
        font-size: 8px;
    }

    th {
        background: #f2f2f4;
        color: #2d3a4a;
        font-weight: 600;
        text-align: right;
    }

    tr:last-child td {
        border-bottom: none;
    }

    td {
        color: #222;
    }

    .status-aberta {
        color: #ce3131;
        font-weight: 600;
    }

    .status-fechada {
        color: #38a169;
        font-weight: 600;
    }

    .status-cancelada {
        color: #e53e3e;
        font-weight: 600;
    }

    /* Estilos para o cabeçalho da empresa */
    .header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }

    .header-table td {
        border: none;
        vertical-align: top;
    }

    .company-logo {
        max-width: 90px;
        height: auto;
    }

    .company-info {
        text-align: right;
        color: #6b7280;
    }

    .company-name {
        font-size: 1.2em;
        color: #000308;
        font-weight: bold;
    }

    .report-date {
        text-align: right;
        margin-top: 10px;
        font-size: 14px;
        color: #000510;
    }

    /* Estilos para sub-tabelas de itens */
    .sub-table {
        width: 100%;
        margin-bottom: 0;
        background: #f9f9fa;
        border-radius: 6px;
    }

    .sub-table th {
        background: #e9ecef;
        font-size: 8px;
        text-align: left;
    }

    .sub-table td {
        font-size: 8px;
        border-bottom: 1px solid #dee2e6;
    }

    .sub-table tr:last-child td {
        border-bottom: none;
    }

    .totals-row {
        text-align: right;
        font-size: 13px;
        background: #f2f2f4;
    }

    /* Estilos para totais gerais */
    .summary-table {
        width: 100%;
        margin-top: 32px;
        border-collapse: collapse;
    }

    .summary-table td {
        padding: 10px;
        border-bottom: 1px solid #e9ecef;
    }

    .summary-header {
        font-size: 10px;
        background: #f2f2f4;
        padding: 10px;
        font-weight: bold;
    }

    .summary-item {
        padding-left: 24px;
    }

    .summary-value {
        text-align: right;
    }

    .spacer-row {
        height: 12px;
    }

    .final-total {
        text-align: right;
        font-size: 12px;
        padding-top: 16px;
    }

    /* Melhorias para impressão */
    @media print {
        body {
            font-size: 11px;
        }
        
        .container {
            box-shadow: none;
        }
        
        .header-table {
            page-break-inside: avoid;
        }
    }
</style>

<div class="container">
    <!-- Cabeçalho com logo e informações da empresa -->
   
    <h1>Relatório de Ordens de Serviço</h1>
    
    <!-- Tabela principal de ordens de serviço -->
    <table>
        <thead>
            <tr>
                <th style="text-align: left;">#</th>
                <th style="text-align: left;">Cliente</th>
                <th style="text-align: left;">Fornecedor</th>
                <th style="text-align: left;">Pagamento</th>
                <th style="text-align: left;">Veículo</th>
                <th style="text-align: left;">Data de Emissão</th>
                <th style="text-align: left;">Autorizado Por</th>
                <th style="text-align: left;">Status</th>
                <th style="text-align: left;">Valor Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ordemServicoRelatorio as $ordem)
                <tr>
                    <td>{{ $ordem->id }}</td>
                    <td>{{ $ordem->cliente->nome ?? '' }}</td>
                    <td>{{ $ordem->fornecedor->nome ?? '' }}</td>
                    <td>{{ $ordem->formaPagamento->nome ?? '' }}</td>
                    <td>
                        {{ $ordem->veiculo->modelo ?? '' }} - 
                        {{ $ordem->veiculo->placa ?? '' }} -
                        {{ $ordem->veiculo->cor ?? '' }}
                    </td>
                    <td>{{ \Carbon\Carbon::parse($ordem->data_emissao ?? $ordem->data_abertura)->format('d/m/Y') }}</td>
                    <td>{{ $ordem->user->name ?? '' }}</td>
                    <td>
                        @if (isset($ordem->status))
                            @if ($ordem->status == 0 || $ordem->status == 'pendente' || $ordem->status == 'aberta')
                                <span class="status-aberta">Pendente</span>
                            @elseif($ordem->status == 1 || $ordem->status == 'concluida' || $ordem->status == 'fechada')
                                <span class="status-fechada">Concluído</span>
                            @else
                                <span class="status-cancelada">Cancelada</span>
                            @endif
                        @endif
                    </td>
                    <td>R$ {{ number_format($ordem->valor_total ?? $ordem->valor, 2, ',', '.') }}</td>
                </tr>
                
                <!-- Sub-tabela de itens da ordem de serviço -->
                @if (isset($ordem->itens) && count($ordem->itens))
                    <tr>
                        <td colspan="9" style="padding: 0;">
                            <table class="sub-table">
                                <thead>
                                    <tr>
                                        <th style="text-align: left;">Categoria</th>
                                        <th style="text-align: left;">Tipo</th>
                                        <th style="text-align: left;">Peça/Serviço</th>
                                        <th style="text-align: left;">Descrição</th>
                                        <th style="text-align: center;">Qtd</th>
                                        <th style="text-align: left;">V. Unitário</th>
                                        <th style="text-align: left;">V. Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $totalPreventiva = 0;
                                        $totalCorretiva = 0;
                                        $totalAvaria = 0;
                                        $totalMulta = 0;
                                        $totalOutros = 0;
                                    @endphp
                                    
                                    @foreach ($ordem->itens as $item)
                                        @php
                                            if ($item->tipo == 1) {
                                                $totalPreventiva += $item->valor_total;
                                            } elseif ($item->tipo == 2) {
                                                $totalCorretiva += $item->valor_total;
                                            } elseif ($item->tipo == 3) {
                                                $totalAvaria += $item->valor_total;
                                            } elseif ($item->tipo == 4) {
                                                $totalMulta += $item->valor_total;
                                            } elseif ($item->tipo == 5) {
                                                $totalOutros += $item->valor_total;
                                            }
                                        @endphp
                                        <tr>
                                            <td>
                                                {{ $item->pecaServico->tipo == 1 ? 'Serviço' : 'Peça' ?? '' }}
                                            </td>
                                            <td>
                                                @php
                                                    $tipos = [
                                                        1 => 'Preventiva',
                                                        2 => 'Corretiva',
                                                        3 => 'Avaria',
                                                        4 => 'Multa',
                                                        5 => 'Outros',
                                                    ];
                                                @endphp
                                                {{ $tipos[$item->tipo] ?? '' }}
                                            </td>
                                            <td>{{ $item->pecaServico->nome ?? '' }}</td>
                                            <td>{{ $item->descricao }}</td>
                                            <td style="text-align: center;">{{ $item->quantidade }}</td>
                                            <td>R$ {{ number_format($item->valor_unitario, 2, ',', '.') }}</td>
                                            <td>R$ {{ number_format($item->valor_total, 2, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                    
                                    <tr>
                                        <td colspan="7" class="totals-row">
                                            <strong>Total Preventiva:</strong> R$ {{ number_format($totalPreventiva, 2, ',', '.') }} &nbsp; | &nbsp;
                                            <strong>Total Corretiva:</strong> R$ {{ number_format($totalCorretiva, 2, ',', '.') }} &nbsp; | &nbsp;                                           
                                            <strong>Total Outros:</strong> R$ {{ number_format($totalOutros, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                @else
                    <tr>
                        <td colspan="9" style="padding-left: 24px; background: #f9f9fa;">
                            <em>Sem itens</em>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <!-- Seção de totais -->
    @php
        $totaisVeiculo = [];
        $totaisCliente = [];
        $totaisFornecedor = [];
        $totaisPagamento = [];
        $totalPreventivaGeral = 0;
        $totalCorretivaGeral = 0;
        $totalAvariaGeral = 0;
        $totalMultaGeral = 0;
        $totalOutrosGeral = 0;
        
        foreach ($ordemServicoRelatorio as $ordem) {
            $valorTotal = $ordem->valor_total ?? ($ordem->valor ?? 0);
            $veiculoKey = ($ordem->veiculo->modelo ?? '') . ' ' . ($ordem->veiculo->placa ?? '');
            $clienteKey = $ordem->cliente->nome ?? '';
            $fornecedorKey = $ordem->fornecedor->nome ?? '';
            $pagamentoKey = $ordem->formaPagamento->nome ?? '';
            
            $totaisVeiculo[$veiculoKey] = ($totaisVeiculo[$veiculoKey] ?? 0) + $valorTotal;
            $totaisCliente[$clienteKey] = ($totaisCliente[$clienteKey] ?? 0) + $valorTotal;
            $totaisFornecedor[$fornecedorKey] = ($totaisFornecedor[$fornecedorKey] ?? 0) + $valorTotal;
            $totaisPagamento[$pagamentoKey] = ($totaisPagamento[$pagamentoKey] ?? 0) + $valorTotal;
            
            if (isset($ordem->itens) && count($ordem->itens)) {
                foreach ($ordem->itens as $item) {
                    if ($item->tipo == 1) {
                        $totalPreventivaGeral += $item->valor_total;
                    } elseif ($item->tipo == 2) {
                        $totalCorretivaGeral += $item->valor_total;
                    } elseif ($item->tipo == 3) {
                        $totalAvariaGeral += $item->valor_total;
                    } elseif ($item->tipo == 4) {
                        $totalMultaGeral += $item->valor_total;
                    } elseif ($item->tipo == 5) {
                        $totalOutrosGeral += $item->valor_total;
                    }
                }
            }
        }
    @endphp

    <table class="summary-table">
        <!-- Totais por Veículo -->
        <tr>
            <td colspan="2" class="summary-header">Totais por Veículo:</td>
        </tr>
        @foreach ($totaisVeiculo as $veiculo => $total)
            <tr>
                <td class="summary-item">{{ $veiculo }}</td>
                <td class="summary-value">R$ {{ number_format($total, 2, ',', '.') }}</td>
            </tr>
        @endforeach
        
        <tr><td colspan="2" class="spacer-row"></td></tr>
        
        <!-- Totais por Cliente -->
        <tr>
            <td colspan="2" class="summary-header">Totais por Cliente:</td>
        </tr>
        @foreach ($totaisCliente as $cliente => $total)
            <tr>
                <td class="summary-item">{{ $cliente }}</td>
                <td class="summary-value">R$ {{ number_format($total, 2, ',', '.') }}</td>
            </tr>
        @endforeach
        
        <tr><td colspan="2" class="spacer-row"></td></tr>
        
        <!-- Totais por Fornecedor -->
        <tr>
            <td colspan="2" class="summary-header">Totais por Fornecedor:</td>
        </tr>
        @foreach ($totaisFornecedor as $fornecedor => $total)
            <tr>
                <td class="summary-item">{{ $fornecedor }}</td>
                <td class="summary-value">R$ {{ number_format($total, 2, ',', '.') }}</td>
            </tr>
        @endforeach
        
        <tr><td colspan="2" class="spacer-row"></td></tr>
        
        <!-- Totais por Pagamento -->
        <tr>
            <td colspan="2" class="summary-header">Totais por Pagamento:</td>
        </tr>
        @foreach ($totaisPagamento as $pagamento => $total)
            <tr>
                <td class="summary-item">{{ $pagamento }}</td>
                <td class="summary-value">R$ {{ number_format($total, 2, ',', '.') }}</td>
            </tr>
        @endforeach
        
        <!-- Totais Gerais por Tipo -->
        <tr>
            <td class="summary-header">Total Geral Preventiva:</td>
            <td class="summary-value">R$ {{ number_format($totalPreventivaGeral, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="summary-header">Total Geral Corretiva:</td>
            <td class="summary-value">R$ {{ number_format($totalCorretivaGeral, 2, ',', '.') }}</td>
        </tr>
        
        <tr>
            <td class="summary-header">Total Geral Outros:</td>
            <td class="summary-value">R$ {{ number_format($totalOutrosGeral, 2, ',', '.') }}</td>
        </tr>
        
        <!-- Total de Ordens -->
        <tr>
            <td colspan="2" class="final-total">
                <strong>Total de Ordens: {{ $ordemServicoRelatorio->count() }}</strong>
            </td>
        </tr>
    </table>
</div>
@endsection