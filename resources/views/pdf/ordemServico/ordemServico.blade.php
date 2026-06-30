@extends('layouts.pdf')

@section('title', 'Ordem de Serviço')

@section('content')
<style>
    body {
        font-family: Arial, sans-serif;
        color: #222;
        font-size: 13px;
        margin: 0;
        padding: 0%;
    }

    .container {
        max-width: 100%;
        margin: 0%;
        background: #fff;
        border-radius: 12px;
        padding: 0%;
        box-shadow: 0 2px 16px rgba(0, 0, 0, 0.07);
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
        padding: 10px;
        border-bottom: 1px solid #e9ecef;
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

    .logo {
        max-width: 120px;
        border-radius: 6px;
    }

    .signature {
        margin-top: 32px;
        display: flex;
        gap: 48px;
        justify-content: center;
    }

    .signature-box {
        flex: 1;
        text-align: center;
    }

    .signature-line {
        border-bottom: 2px solid #b0b7c3;
        margin: 0 auto 8px auto;
        width: 80%;
        height: 32px;
        display: block;
    }

    .right {
        text-align: right;
    }

    .header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }

    .header-table td {
        border: none;
        vertical-align: top;
    }

    .company-name {
        font-size: 1.2em;
        color: #000308;
        font-weight: bold;
    }

    .company-info {
        text-align: right;
        color: #6b7280;
        font-size: 12px;
    }

    .os-info {
        text-align: right;
        margin-top: 5px;
        font-size: 12px;
    }

    fieldset {
        border: 1px solid #b0b7c3;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 24px;
    }

    legend {
        font-weight: bold;
        color: #2d3a4a;
        padding: 0 8px;
    }

    .data-table th {
        text-align: right;
        width: 15%;
    }

    .data-table td {
        text-align: left;
        width: 35%;
    }

    .items-table th {
        text-align: left;
        font-size: 12px;
    }

    .items-table td {
        font-size: 11px;
    }

    .total-row {
        text-align: right;
        font-size: 16px;
        font-weight: bold;
        padding: 10px;
    }

    /* Melhorias para impressão */
    @media print {
        body {
            font-size: 11px;
        }
        
        .container {
            box-shadow: none;
        }
        
        fieldset {
            page-break-inside: avoid;
        }
    }
</style>

<div class="container">
    <!-- Cabeçalho -->
    <table class="header-table">
         <tr>
            <td colspan="2" class="os-info">
                <strong style="font-size:12pt;color:#000510;">Ordem de Serviço: </strong>
                <span style="color:#6b7280;">OS - 00{{ $ordemServico->id }}</span><br>
                <span style="color:#6b7280;">Data: {{ \Carbon\Carbon::parse($ordemServico->data_emissao)->format('d/m/Y') }}</span>
            </td>
        </tr>
    </table>

    <!-- Dados da Ordem de Serviço -->
    <fieldset>
        <legend>Dados da Ordem de Serviço</legend>
        <table class="data-table">
            <tr>
                <th>Cliente:</th>
                <td colspan="3" style="font-size: 12px">{{ $ordemServico->cliente->nome ?? '' }}</td>
            </tr>
            <tr>
                <th>Pagamento:</th>
                <td style="font-size: 12px">{{ $ordemServico->formaPagamento->nome ?? '' }}</td>
                <th>Veículo:</th>
                <td style="font-size: 12px">{{ $ordemServico->veiculo->modelo ?? '' }}</td>
            </tr>
            <tr>
                <th>Cor:</th>
                <td style="font-size: 12px">{{ $ordemServico->veiculo->cor ?? '' }}</td>
                <th>Realizado por:</th>
                <td style="font-size: 12px">{{ $ordemServico->user->name ?? '' }}</td>
            </tr>
        </table>
    </fieldset>

    <!-- Itens da OS -->
    <fieldset>
        <legend>Itens da Ordem de Serviço</legend>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 10%;">Categoria</th>
                    <th style="width: 12%;">Tipo</th>
                    <th style="width: 20%;">Peça/Serviço</th>
                    <th style="width: 25%;">Descrição</th>
                    <th style="width: 8%; text-align: center;">Qtd</th>
                    <th style="width: 10%;">V. Unitário</th>
                    <th style="width: 10%;">V. Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ordemServico->itens as $index => $item)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $item->pecaServico->tipo == 0 ? 'Peça' : 'Serviço' ?? '' }}</td>
                        <td>
                            @php
                                $tipos = [
                                    1 => 'Preventiva',
                                    2 => 'Corretiva',
                                    3 => 'Avaria',
                                    4 => 'Multa',
                                    5 => 'Outros'
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
            </tbody>
        </table>
        
        <table>
            <tr>
                <td class="total-row">
                    <strong>Total Geral: R$ {{ number_format($ordemServico->valor_total, 2, ',', '.') }}</strong>
                </td>
            </tr>
        </table>
    </fieldset>

    <!-- Assinaturas (comentadas) -->
    {{-- 
    <div class="signature">
        <div class="signature-box">
            <span class="signature-line"></span>
            Assinatura do Cliente
        </div>
        <div class="signature-box">
            <span class="signature-line"></span>
            Assinatura da Empresa
        </div>
    </div>
    --}}
</div>
@endsection