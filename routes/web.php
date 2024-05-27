<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ConsultasController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('perrillo',[ConsultasController::class, 'index']);
Route::post('perrillo/actMontSeguro',[ConsultasController::class, 'actualizarMontoSeguro']);

Route::post('perrillo/grupoAbiertoPorError',[ConsultasController::class, 'grupoAbiertoPorError']);
Route::post('perrillo/buscarClaveSafi',[ConsultasController::class, 'buscarClaveSafi']);
Route::post('perrillo/reasignacionCarteraGrupal',[ConsultasController::class, 'reasignacionCarteraGrupal']);
Route::post('perrillo/reasignacionCarteraIndividual',[ConsultasController::class,'reasignacionCarteraIndividual']);
Route::post('perrillo/reasignacionCartera',[ConsultasController::class,'reasignacionCartera']);
Route::post('perrillo/eliminarCredito',[ConsultasController::class,'eliminarCredito']);
Route::get('perrillo/plataforma-roles',[ConsultasController::class,'getRolesPlataforma']);
Route::get('perrillo/plataforma-sucursales',[ConsultasController::class,'getSucursales']);
Route::post('perrillo/usuarioPlataforma',[ConsultasController::class,'altaUsuariosPlataforma']);
Route::post('perrillo/cambiarNombreGrupo',[ConsultasController::class, 'cambiarNombreGrupo']);