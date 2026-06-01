<?php

namespace App\Http\Controllers;

use App\Models\Locacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssinafyWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $event      = $request->input('event');   // document.signed, document.refused, etc.
        $documentId = $request->input('data.id');

        Log::info('Assinafy webhook recebido', ['event' => $event, 'document_id' => $documentId]);

        $locacao = Locacao::where('assinafy_document_id', $documentId)->first();

        if (!$locacao) {
            return response()->json(['ok' => true]); // ignora documentos não mapeados
        }

        $novoStatus = match ($event) {
            'document.signed'   => 'signed',
            'document.refused'  => 'refused',
            'document.viewed'   => 'viewed',
            default             => $locacao->assinafy_status,
        };

        $locacao->update(['assinafy_status' => $novoStatus]);

        // Opcional: notificar usuário interno via Filament Notification broadcast
        // \Filament\Notifications\Notification::make()
        //     ->title("Contrato #{$locacao->id} assinado!")
        //     ->success()
        //     ->sendToDatabase(\App\Models\User::first());

        return response()->json(['ok' => true]);
    }
}