<?php
use App\Http\Controllers\Contrato;
use App\Http\Controllers\FichaAgendamento;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentoController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Route::get('/', function () {
//     return view('welcome');
// });
Route::get('/', function () { return redirect('/admin'); })->name('login');

Route::get('pdf/locacao/{id}',[Contrato::class, 'printLocacao'])->name('imprimirLocacao');
Route::get('pdf/locacao/{locacao}/contrato/{contrato}',[Contrato::class, 'printLocacaoContrato'])->name('imprimirLocacaoContrato');
Route::get('debug/contrato/{id}', [Contrato::class, 'debugTemplate'])->name('debugContrato');
Route::get('pdf/documento/{id}',[DocumentoController::class, 'ordemServico'])->name('imprimirDocumento');
Route::get('pdf/ordemServico',[DocumentoController::class, 'ordemServicoRelatorio'])->name('imprimirOrdemServicoRelatorio');
Route::get('pdf/locacoes',[DocumentoController::class, 'locacoesRelatorio'])->name('imprimirLocacoesRelatorio');
Route::get('pdf/contaspagar',[DocumentoController::class, 'contasPagarRelatorio'])->name('imprimirContasPagarRelatorio');
Route::get('pdf/contaspagar/launch',[DocumentoController::class, 'launchContasPagarRelatorio'])->name('imprimirContasPagarRelatorioLaunch');
Route::get('pdf/contasreceber',[DocumentoController::class, 'contasReceberRelatorio'])->name('imprimirContasReceberRelatorio');
Route::get('pdf/contasreceber/launch',[DocumentoController::class, 'launchContasReceberRelatorio'])->name('imprimirContasReceberRelatorioLaunch');
Route::get('/contrato/variaveis', [\App\Http\Controllers\ContratoController::class, 'variaveis'])
    ->name('contrato.variaveis');
Route::get('pdf/lucratividade-veiculos',[DocumentoController::class, 'veiculosLucratividade'])->name('veiculos-lucratividade.pdf');
Route::get('pdf/fluxo-caixa',[DocumentoController::class, 'fluxoCaixa'])->name('relatorio.fluxo.caixa.pdf');
Route::get('pdf/relatorio-custo-veiculo',[DocumentoController::class, 'relatorioCustoVeiculo'])->name('imprimirRelatorioCustoVeiculo');
