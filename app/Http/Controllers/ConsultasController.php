<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;

class ConsultasController extends Controller
{
    //

    /*
    public function __construct()
    {
        $this->middleware('cors');
    }
    */
    public function index()
    {
        return view('perrillo');
    }

    public function actualizarMontoSeguro(Request $request)
    {
        $solicitud = $request->input('solicitud');
        $montante = $request->input('montante');
        $solicitudRes    = "";
        $credito  = "";
        $mensaje    = "";
        $solicitudes = DB::connection('produccion')->select("SELECT * FROM SOLICITUDCREDITO WHERE SolicitudCreditoID = $solicitud");
        if(count($solicitudes)==0 || $solicitudes==null)
        {
            $solicitudRes    = null;
            $credito  = null;
            $mensaje    = "La solicitud no existe Lic";
            $success = false;
        }
        else
        {
            try{
                $solicitudRes    = DB::connection('produccion')->statement("UPDATE SOLICITUDCREDITO SET MontoSeguroVida =  $montante WHERE SolicitudCreditoID = $solicitud");
        
                $credito    = DB::connection('produccion')->statement("UPDATE CREDITOS SET MontoSeguroVida = $montante WHERE SolicitudCreditoID = $solicitud");
                DB::disconnect('produccion');
                $success = true;
                $mensaje = "Aplica lic";
            }
            catch(QueryException $e)
            {
                $success    = false;
                $mensaje    = $e->getMessage();
            }
        }
        return json_encode(
                [
                    "Mensaje" => $mensaje,
                    "Estado" => $success,
                    "SolicitudRes" => $solicitudRes,
                    "Credito" => $credito,
                    "solicitud" => $solicitud,
                    "Montante" => $montante
                ]
        );  
    }

    public function grupoAbiertoPorError(Request $request)
    {
        $grupo = $request->input('grupo');
        $update    = "";
        $insert  = "";
        $delete = "";
        $mensaje    = "";

        try{
            $safeUpdate    = DB::connection('produccion')->statement("SET SQL_SAFE_UPDATES = 0");
    
            $grupoEnc = DB::connection('produccion')->select("SELECT * FROM GRUPOSCREDITO WHERE GrupoID = $grupo");
            if(count($grupoEnc)==0 || $grupoEnc==null)
            {
                $update    = null;
                $insert  = null;
                $delete = null;
                $mensaje    = "El grupo no existe Lic";
                $success = false;

                return json_encode(
                    [
                        "Mensaje" => $mensaje,
                        "Estado" => $success,
                        "update" => $update,
                        "insert" => $insert,
                        "delete" => $delete,
                        "grupo" => $grupo,
                    ]
                );  
            }
            $ultimoCiclo = DB::connection('produccion')->select("SELECT MAX(Ciclo) UltimoCiclo FROM `HIS-INTEGRAGRUPOSCRE` WHERE GrupoID =$grupo");
            $cicloOK = $ultimoCiclo[0]->UltimoCiclo;
            $update    = DB::connection('produccion')->statement("update GRUPOSCREDITO set
            CicloActual =$cicloOK,
            EstatusCiclo='C',
            FechaUltCiclo = (select date(FechaRegistro) from `HIS-INTEGRAGRUPOSCRE`  where grupoID=$grupo and ciclo = $cicloOK limit 1)
            where GrupoID=$grupo;");

            $insert = DB::connection('produccion')->statement("insert into INTEGRAGRUPOSCRE 
            select * from `HIS-INTEGRAGRUPOSCRE`  where grupoID=$grupo and ciclo = $cicloOK");

            $delete = DB::connection('produccion')->statement("delete from `HIS-INTEGRAGRUPOSCRE`  where grupoID=$grupo and ciclo = $cicloOK");

            DB::disconnect('produccion');
            $success = true;
            $mensaje = "Aplica lic";
        }
        catch(QueryException $e)
        {
            $success    = false;
            $mensaje    = $e->getMessage();
        }
        return json_encode(
                [
                    "Mensaje" => $mensaje,
                    "Estado" => $success,
                    "update" => $update,
                    "insert" => $insert,
                    "delete" => $delete,
                    "grupo" => $grupo,
                    "cicloCorrecto" => $cicloOK
                ]
        );  

    }

    public function buscarClaveSafi(Request $request)
    {
        $clave = $request->input('clave');
        $query = 'SELECT UsuarioID, Clave, NombreCompleto FROM USUARIOS WHERE Clave LIKE"%'.$clave.'%"';
        $mensaje    = "";
        $resultado = [
            "usuarios" => null,
            "clave" => $clave,
            "mensaje" => $mensaje,
            "consulta" => $query
        ];

        $usuarios = DB::connection('produccion')->select($query);
        if(count($usuarios)==0 || $usuarios == null)
        {
            $resultado["mensaje"] = "No hay usuarios con esa clave Lic";
            DB::disconnect('produccion');
            return json_encode($resultado);
        }
        $resultado["usuarios"] = $usuarios;
        $resultado["mensaje"] = "Ya hay usuarios con esa clave Lic";
        DB::disconnect('produccion');
        return json_encode($resultado);
    }

    public function reasignacionCarteraGrupal(Request $request)
    {
        $grupos = $request->input('grupos');
        $sucursal = $request->input('sucursal');
        $nuevoPromotor = $request->input('promotor');
        $querySucursal = "SELECT * FROM SUCURSALES WHERE SucursalID = $sucursal";
        $queryPromotor = "SELECT * FROM PROMOTORES WHERE PromotorID = $nuevoPromotor";
        $strGrupos = "";
        $resultado = [
            "grupos" => $grupos,
            "sucursal" => $sucursal,
            "promotor" => $nuevoPromotor,
            "mensaje" => "",
            "estado" => false,
            "queryPromotor" => $queryPromotor,
            "querySucursal" => $querySucursal
        ];
        foreach($grupos as $i => $grupo)
        {
            if(is_int($grupo)) {
                $abr = intval($grupo);
                if($i!=count($grupos)-1)
                {
                    $strGrupos.=$grupo.",";
                }
                else
                {
                    $strGrupos.=$grupo;
                }
            } else{
                $resultado["mensaje"] = "GrupoID mal capturado Lic";
                return json_encode($resultado);
            }
            $resultado["strGrupos"]=$strGrupos;
            $queryGrupos = "SELECT * FROM GRUPOSCREDITO WHERE GrupoID = $grupo";
            $grupoEncontrado = DB::connection('produccion')->select($queryGrupos);
            if(count($grupoEncontrado)==0 || $grupoEncontrado == null)
            {
                DB::disconnect('produccion');
                $resultado["mensaje"] = "El grupo $grupo no existe Lic";
                $resultado["queyGrupo"] = $queryGrupos;
                return json_encode($resultado);
            }
        }
        if(!is_int($sucursal)) 
        {
            $resultado["mensaje"] = "Sucursal mal Capturada Lic";
            return json_encode($resultado);
        }
        $sucursalEncontrada = DB::connection('produccion')->select($querySucursal);
        if(count($sucursalEncontrada)==0 || $sucursalEncontrada == null)
        {
            DB::disconnect('produccion');
            $resultado["mensaje"] = "La sucursal $sucursal no existe Lic";
            $resultado["querySucursal"] = $querySucursal;
            return json_encode($resultado);
        }
        if (!is_int($nuevoPromotor))
        {
            $resultado["mensaje"] = "Promotoro mal capturado Lic";
            return json_encode($resultado);
        }
        $promotorEncontrado = DB::connection('produccion')->select($queryPromotor);
        if(count($promotorEncontrado) == 0 || $promotorEncontrado == null)
        {
            DB::disconnect('produccion');
            $resultado["mensaje"] = "No existe el promotor Lic";
            $resultado["queryPromotor"] = $queryPromotor;
            return json_encode($resultado);
        }

        //Pasó las validaciones
        $safeUpdate    = DB::connection('produccion')->statement("SET SQL_SAFE_UPDATES = 0");
        $queryActualizar = "UPDATE SOLICITUDCREDITO sol SET sol.PromotorID = $nuevoPromotor, sol.Sucursal=$sucursal,
        sol.SucursalID=$sucursal
        WHERE sol.SolicitudCreditoID in(
            SELECT inte.SolicitudCreditoID
            FROM INTEGRAGRUPOSCRE inte
            where inte.grupoid in ( $strGrupos ))";
        $queryUpdateGrupo = "UPDATE GRUPOSCREDITO 
        SET SucursalID = $sucursal,
        Sucursal = $sucursal
        WHERE GrupoID IN 
        ($strGrupos)";
        try
        {
            $updateSolicitud = DB::connection('produccion')->statement($queryActualizar);
            $updateGruposCredito = DB::connection('produccion')->statement($queryUpdateGrupo);
            $resultado["estado"] = true;
            $resultado["mensaje"] = "Ya quedo Lic";
            $resultado["updateSolicitud"] = $updateSolicitud;
            $resultado["updateGruposcredito"] = $updateGruposCredito;
            return json_encode($resultado);

        }
        catch(Exception $e)
        {
            $resultado["mensaje"] = "Error mortal";
            $resultado["queryActualizar"] = $queryActualizar;
            $resultado["queryActualizarGrupos"] = $queryUpdateGrupo;
            return json_encode($resultado);
        }
        return json_encode($resultado);
    }

    public function reasignacionCarteraIndividual(Request $request)
    {
        $creditos = $request->input('creditos');
        $sucursal = $request->input('sucursal');
        $nuevoPromotor = $request->input('promotor');
        $querySucursal = "SELECT * FROM SUCURSALES WHERE SucursalID = $sucursal";
        $queryPromotor = "SELECT * FROM PROMOTORES WHERE PromotorID = $nuevoPromotor";
        $strCreditos = "";
        $resultado = [
            "creditos" => $creditos,
            "sucursal" => $sucursal,
            "promotor" => $nuevoPromotor,
            "mensaje" => "",
            "estado" => false,
            "queryPromotor" => $queryPromotor,
            "querySucursal" => $querySucursal
        ];
        foreach($creditos as $i => $credito)
        {
            if(is_int($credito)) {
                if($i!=count($creditos)-1)
                {
                    $strCreditos.=$credito.",";
                }
                else
                {
                    $strCreditos.=$credito;
                }
            } else{
                $resultado["mensaje"] = "GrupoID mal capturado Lic";
                return json_encode($resultado);
            }
            $resultado["strGrupos"]=$strCreditos;
            $queryCreditos = "SELECT * FROM CREDITOS WHERE CreditoID = $credito";
            $grupoEncontrado = DB::connection('produccion')->select($queryCreditos);
            if(count($grupoEncontrado)==0 || $grupoEncontrado == null)
            {
                DB::disconnect('produccion');
                $resultado["mensaje"] = "El credito $credito no existe Lic";
                $resultado["queyCredito"] = $queryCreditos;
                return json_encode($resultado);
            }
        }
        if(!is_int($sucursal)) 
        {
            $resultado["mensaje"] = "Sucursal mal Capturada Lic";
            return json_encode($resultado);
        }
        $sucursalEncontrada = DB::connection('produccion')->select($querySucursal);
        if(count($sucursalEncontrada)==0 || $sucursalEncontrada == null)
        {
            DB::disconnect('produccion');
            $resultado["mensaje"] = "La sucursal $sucursal no existe Lic";
            $resultado["querySucursal"] = $querySucursal;
            return json_encode($resultado);
        }
        if (!is_int($nuevoPromotor))
        {
            $resultado["mensaje"] = "Promotoro mal capturado Lic";
            return json_encode($resultado);
        }
        $promotorEncontrado = DB::connection('produccion')->select($queryPromotor);
        if(count($promotorEncontrado) == 0 || $promotorEncontrado == null)
        {
            DB::disconnect('produccion');
            $resultado["mensaje"] = "No existe el promotor Lic";
            $resultado["queryPromotor"] = $queryPromotor;
            return json_encode($resultado);
        }
        //pasó las validaciones
        $safeUpdate    = DB::connection('produccion')->statement("SET SQL_SAFE_UPDATES = 0");
        $queryActualizar = "UPDATE SOLICITUDCREDITO sol SET PromotorID=$nuevoPromotor, Sucursal=$sucursal, SucursalID=$sucursal
        where SolicitudCreditoID in (
            select SolicitudCreditoID from CREDITOS where CreditoID in 
            ($strCreditos)
        )";
        $queryUpdateClientes = "UPDATE CLIENTES  SET PromotorActual=$nuevoPromotor, Sucursal = $sucursal, SucursalOrigen=$sucursal
                                WHERE ClienteID IN
                                (
                                    select ClienteID from CREDITOS where CreditoID in 
                                    ($strCreditos)
                                )";
        $queryUpdateCreditos = "update CREDITOS
                                SET 
                                SucursalID = $sucursal,
                                Sucursal = $sucursal
                                WHERE CreditoID in 
                                ($strCreditos)";
        try
        {
            $updateSolicitud = DB::connection('produccion')->statement($queryActualizar);
            $updateClientes = DB::connection('produccion')->statement($queryUpdateClientes);
            $updateCreditos = DB::connection('produccion')->statement($queryUpdateCreditos);
            $resultado["estado"] = true;
            $resultado["mensaje"] = "Ya quedo Lic";
            $resultado["updateSolicitud"] = $updateSolicitud;
            $resultado["updateCliente"] = $updateClientes;
            $resultado["updateCredito"] = $updateCreditos;
            return json_encode($resultado);

        }
        catch(Exception $e)
        {
            $resultado["mensaje"] = "Error mortal";
            $resultado["queryActualizar"] = $queryActualizar;
            $resultado["queryActualizarClientes"] = $queryUpdateClientes;
            $resultado["queryActualizarCreditos"] = $queryUpdateCreditos;
            return json_encode($resultado);
        }
        return json_encode($resultado);
    }

    private function reasignacionCarteraGrupalv2($grupos, $sucursal, $nuevoPromotor)
    {
        $querySucursal = "SELECT * FROM SUCURSALES WHERE SucursalID = $sucursal";
        $queryPromotor = "SELECT * FROM PROMOTORES WHERE PromotorID = $nuevoPromotor";
        $strGrupos = "";
        $resultado = [
            "grupos" => $grupos,
            "sucursal" => $sucursal,
            "promotor" => $nuevoPromotor,
            "mensaje" => "",
            "estado" => false,
            "queryPromotor" => $queryPromotor,
            "querySucursal" => $querySucursal
        ];
        foreach($grupos as $i => $grupo)
        {
            if(is_int($grupo)) {
                $abr = intval($grupo);
                if($i!=count($grupos)-1)
                {
                    $strGrupos.=$grupo.",";
                }
                else
                {
                    $strGrupos.=$grupo;
                }
            } else{
                $resultado["mensaje"] = "GrupoID mal capturado Lic";
                return $resultado;
            }
            $resultado["strGrupos"]=$strGrupos;
            $queryGrupos = "SELECT * FROM GRUPOSCREDITO WHERE GrupoID = $grupo";
            $grupoEncontrado = DB::connection('produccion')->select($queryGrupos);
            if(count($grupoEncontrado)==0 || $grupoEncontrado == null)
            {
                DB::disconnect('produccion');
                $resultado["mensaje"] = "El grupo $grupo no existe Lic";
                $resultado["queyGrupo"] = $queryGrupos;
                return $resultado;
            }
        }
        if(!is_int($sucursal)) 
        {
            $resultado["mensaje"] = "Sucursal mal Capturada Lic";
            return $resultado;
        }
        $sucursalEncontrada = DB::connection('produccion')->select($querySucursal);
        if(count($sucursalEncontrada)==0 || $sucursalEncontrada == null)
        {
            DB::disconnect('produccion');
            $resultado["mensaje"] = "La sucursal $sucursal no existe Lic";
            $resultado["querySucursal"] = $querySucursal;
            return $resultado;
        }
        if (!is_int($nuevoPromotor))
        {
            $resultado["mensaje"] = "Promotoro mal capturado Lic";
            return $resultado;
        }
        $promotorEncontrado = DB::connection('produccion')->select($queryPromotor);
        if(count($promotorEncontrado) == 0 || $promotorEncontrado == null)
        {
            DB::disconnect('produccion');
            $resultado["mensaje"] = "No existe el promotor Lic";
            $resultado["queryPromotor"] = $queryPromotor;
            return $resultado;
        }

        //Pasó las validaciones
        $safeUpdate    = DB::connection('produccion')->statement("SET SQL_SAFE_UPDATES = 0");
        $queryActualizar = "UPDATE SOLICITUDCREDITO sol SET sol.PromotorID = $nuevoPromotor, sol.Sucursal=$sucursal,
        sol.SucursalID=$sucursal
        WHERE sol.SolicitudCreditoID in(
            SELECT inte.SolicitudCreditoID
            FROM INTEGRAGRUPOSCRE inte
            where inte.grupoid in ( $strGrupos ))";
        $queryUpdateGrupo = "UPDATE GRUPOSCREDITO 
        SET SucursalID = $sucursal,
        Sucursal = $sucursal
        WHERE GrupoID IN 
        ($strGrupos)";
        try
        {
            $updateSolicitud = DB::connection('produccion')->statement($queryActualizar);
            $updateGruposCredito = DB::connection('produccion')->statement($queryUpdateGrupo);
            $resultado["estado"] = true;
            $resultado["mensaje"] = "Ya quedo Lic";
            $resultado["updateSolicitud"] = $updateSolicitud;
            $resultado["updateGruposcredito"] = $updateGruposCredito;
            return $resultado;

        }
        catch(Exception $e)
        {
            $resultado["mensaje"] = "Error mortal";
            $resultado["queryActualizar"] = $queryActualizar;
            $resultado["queryActualizarGrupos"] = $queryUpdateGrupo;
            return $resultado;
        }
        return $resultado;
    }

    private function reasignacionCarteraIndividualv2($creditos,$sucursal,$nuevoPromotor)
    {
        $querySucursal = "SELECT * FROM SUCURSALES WHERE SucursalID = $sucursal";
        $queryPromotor = "SELECT * FROM PROMOTORES WHERE PromotorID = $nuevoPromotor";
        $strCreditos = "";
        $resultado = [
            "creditos" => $creditos,
            "sucursal" => $sucursal,
            "promotor" => $nuevoPromotor,
            "mensaje" => "",
            "estado" => false,
            "queryPromotor" => $queryPromotor,
            "querySucursal" => $querySucursal
        ];
        foreach($creditos as $i => $credito)
        {
            if(is_int($credito)) {
                if($i!=count($creditos)-1)
                {
                    $strCreditos.=$credito.",";
                }
                else
                {
                    $strCreditos.=$credito;
                }
            } else{
                $resultado["mensaje"] = "GrupoID mal capturado Lic";
                return $resultado;
            }
            $resultado["strGrupos"]=$strCreditos;
            $queryCreditos = "SELECT * FROM CREDITOS WHERE CreditoID = $credito";
            $grupoEncontrado = DB::connection('produccion')->select($queryCreditos);
            if(count($grupoEncontrado)==0 || $grupoEncontrado == null)
            {
                DB::disconnect('produccion');
                $resultado["mensaje"] = "El credito $credito no existe Lic";
                $resultado["queyCredito"] = $queryCreditos;
                return $resultado;
            }
        }
        if(!is_int($sucursal)) 
        {
            $resultado["mensaje"] = "Sucursal mal Capturada Lic";
            return $resultado;
        }
        $sucursalEncontrada = DB::connection('produccion')->select($querySucursal);
        if(count($sucursalEncontrada)==0 || $sucursalEncontrada == null)
        {
            DB::disconnect('produccion');
            $resultado["mensaje"] = "La sucursal $sucursal no existe Lic";
            $resultado["querySucursal"] = $querySucursal;
            return $resultado;
        }
        if (!is_int($nuevoPromotor))
        {
            $resultado["mensaje"] = "Promotoro mal capturado Lic";
            return $resultado;
        }
        $promotorEncontrado = DB::connection('produccion')->select($queryPromotor);
        if(count($promotorEncontrado) == 0 || $promotorEncontrado == null)
        {
            DB::disconnect('produccion');
            $resultado["mensaje"] = "No existe el promotor Lic";
            $resultado["queryPromotor"] = $queryPromotor;
            return $resultado;
        }
        //pasó las validaciones
        $safeUpdate    = DB::connection('produccion')->statement("SET SQL_SAFE_UPDATES = 0");
        $queryActualizar = "UPDATE SOLICITUDCREDITO sol SET PromotorID=$nuevoPromotor, Sucursal=$sucursal, SucursalID=$sucursal
        where SolicitudCreditoID in (
            select SolicitudCreditoID from CREDITOS where CreditoID in 
            ($strCreditos)
        )";
        $queryUpdateClientes = "UPDATE CLIENTES  SET PromotorActual=$nuevoPromotor, Sucursal = $sucursal, SucursalOrigen=$sucursal
                                WHERE ClienteID IN
                                (
                                    select ClienteID from CREDITOS where CreditoID in 
                                    ($strCreditos)
                                )";
        $queryUpdateCreditos = "update CREDITOS
                                SET 
                                SucursalID = $sucursal,
                                Sucursal = $sucursal
                                WHERE CreditoID in 
                                ($strCreditos)";
        try
        {
            $updateSolicitud = DB::connection('produccion')->statement($queryActualizar);
            $updateClientes = DB::connection('produccion')->statement($queryUpdateClientes);
            $updateCreditos = DB::connection('produccion')->statement($queryUpdateCreditos);
            $resultado["estado"] = true;
            $resultado["mensaje"] = "Ya quedo Lic";
            $resultado["updateSolicitud"] = $updateSolicitud;
            $resultado["updateCliente"] = $updateClientes;
            $resultado["updateCredito"] = $updateCreditos;
            return $resultado;

        }
        catch(Exception $e)
        {
            $resultado["mensaje"] = "Error mortal";
            $resultado["queryActualizar"] = $queryActualizar;
            $resultado["queryActualizarClientes"] = $queryUpdateClientes;
            $resultado["queryActualizarCreditos"] = $queryUpdateCreditos;
            return $resultado;
        }
        return $resultado;
    }

    public function reasignacionCartera(Request $request)
    {
        $esGrupal = $request->input("esGrupal");
        if($esGrupal)
        {
            $grupos = $request->input('grupos');
            $sucursal = $request->input('sucursal');
            $nuevoPromotor = $request->input('promotor');
            return json_encode(self::reasignacionCarteraGrupalv2($grupos,$sucursal,$nuevoPromotor));   
        } else
        {
            $creditos = $request->input('creditos');
            $sucursal = $request->input('sucursal');
            $nuevoPromotor = $request->input('promotor');
            return json_encode(self::reasignacionCarteraIndividualv2($creditos,$sucursal,$nuevoPromotor));
        }
    }

    private function solitudesGrupales($GrupoID)
    {
        $query = "SELECT SolicitudCreditoID from SOLICITUDCREDITO WHERE SolicitudCreditoID IN 
                 (SELECT SolicitudCreditoID from INTEGRAGRUPOSCRE WHERE GrupoID = $GrupoID)";
        $solicitudes = DB::connection('produccion')->select($query);
        $solicitudesOk = [];
        foreach ($solicitudes as $solicitud) {
            $solicitudesOk[] = $solicitud->SolicitudCreditoID;
        }
        return $solicitudesOk;
    }

    private function arrayToColons($array)
    {
        $str = "";
        try{
            foreach($array as $i => $value)
            {
                if($i!=count($array)-1)
                    {
                        $str.=$value.",";
                    }
                    else
                    {
                        $str.=$value;
                    }
            }
        }
        catch(Exception $e)
        {
            return $e;
        }
        
        return $str;
    }

    public function buscarCreditosGrupales($solicitudes)
    {
        $strSolicitudes = self::arrayToColons($solicitudes);
        $query = "SELECT CreditoID 
        FROM SOLICITUDCREDITO 
        WHERE SolicitudCreditoID IN 
        (
        $strSolicitudes
        )";
        $creditos = DB::connection('produccion')->select($query);
        $creditosOK = [];
        foreach ($creditos as $credito) {
            $creditosOK[] = $credito->CreditoID;
        }
        return $creditosOK;
    }

    private function buscarSolicitudesIndividuales($creditos)
    {
        $solicitudes = DB::connection('produccion')->select("SELECT SolicitudCreditoID FROM SOLICITUDCREDITO WHERE CreditoID IN ($creditos)");
        $solicitudesOk = [];
        foreach ($solicitudes as $solicitud) {
            $solicitudesOk[] = $solicitud->SolicitudCreditoID;
        }
        return $solicitudesOk;
    }

    public function eliminarCredito(Request $request)
    {
        $mensaje = [
            "estado" => false,
            "query" => null
        ];
        $esGrupal = $request->input("esGrupal");
        if($esGrupal)
        {
            $GrupoID = $request->input('GrupoID');
            $mensaje["GrupoID"] = $GrupoID;
            $solicitudes = self::solitudesGrupales($GrupoID);
            $creditos = self::buscarCreditosGrupales($solicitudes);
            $strSolicitudes = self::arrayToColons($solicitudes);
            $strCreditos = self::arrayToColons($creditos);
        }
        else
        {
            $creditos = $request->input('creditos');
            //$solicitudes = $request->input('solicitudes');
            //$strCreditos = self::arrayToColons($creditos);
            $strCreditos = $creditos;
            //return var_dump(self::arrayToColons($creditos));
            $solicitudes = self::buscarSolicitudesIndividuales($creditos);
            $strSolicitudes = self::arrayToColons($solicitudes);
        }
        $mensaje["creditos"] = $creditos;
        $mensaje["solicitudes"] = $solicitudes;
        $queryDeleteAmor = "DELETE FROM AMORTICREDITO WHERE CreditoID IN ($strCreditos)";
        try
        {
            $deleteAmor = DB::connection('produccion')->statement($queryDeleteAmor);
            $mensaje["estado"] = true;
        }
        catch(Exception $e)
        {
            $mensaje["query"] = $queryDeleteAmor;
        }
        finally
        {
            DB::disconnect('produccion');
        }
        $queryDeleteCent = "DELETE FROM CREDITODOCENT WHERE CreditoID IN ($strCreditos)";
        try
        {
            $deleteDocent = DB::connection('produccion')->statement($queryDeleteCent);
        }
        catch(Exception $e)
        {
            $mensaje["query"] = $queryDeleteCent;
        }
        finally
        {
            DB::disconnect('produccion');
        }
        $queryDeleteReversa = "DELETE FROM REVERSADESCRE WHERE CreditoID IN ($strCreditos)";
        try
        {
            $deleteReversa = DB::connection('produccion')->statement($queryDeleteReversa);
        }
        catch(Exception $e)
        {
            $mensaje["query"] = $queryDeleteReversa;
        }
        finally
        {
            DB::disconnect('produccion');
        }
        $queryDeleteArchivos = "DELETE FROM CREDITOARCHIVOS WHERE CreditoID IN ($strCreditos)";
        try {
            $deleteArchivos = DB::connection('produccion')->statement($queryDeleteArchivos);
        } catch (Exception $e) {
            $mensaje["query"] = $queryDeleteArchivos;
        }
        finally
        {
            DB::disconnect('produccion');
        }
        $queryDeleteCredito = "DELETE FROM CREDITOS WHERE CreditoID IN ($strCreditos)";
        try
        {
            $deleteCredito = DB::connection('produccion')->statement($queryDeleteCredito);
        }
        catch(Exception $e)
        {
            $mensaje["query"] = $queryDeleteCredito;
        }
        finally
        {
            DB::disconnect('produccion');
        }
        $queryUpdateSol = "UPDATE SOLICITUDCREDITO SET CreditoID = 0 WHERE SolicitudCreditoID IN ($strSolicitudes)";
        try
        {
            $updateSolicitud = DB::connection('produccion')->statement($queryUpdateSol);
        }
        catch(Exception $e)
        {
            $mensaje["query"] = $queryUpdateSol;
        }
        finally
        {
            DB::disconnect('produccion');
        }
        $queryDeleteFirma = "DELETE FROM ESQUEMAAUTFIRMA WHERE SolicitudCreditoID IN ($strSolicitudes)";
        try {
            $deleteFirma = DB::connection('produccion')->statement($queryDeleteFirma);
        } catch (Exception $e) {
            $mensaje["query"] = $queryDeleteFirma;
        }
        finally
        {
            DB::disconnect('produccion');
        }
        if($esGrupal)
        {
            $queryEstatus = "UPDATE GRUPOSCREDITO SET EstatusCiclo = 'A' WHERE GrupoID = $GrupoID";
            try {
                $abrirGrupo = DB::connection('produccion')->statement($queryEstatus);
            } catch (Exception $e) {
                $mensaje["query"] = $queryEstatus;
            }
            finally
            {
                DB::disconnect('produccion');
            }
        }
        if($mensaje["query"]==null) $mensaje["estatus"] = true;
        return json_encode($mensaje);
    }

    public function reversaDesembolso(Request $request)
    {
        $esGrupal = $request->input('esGrupal');
        
        if($esGrupal)
        {

        }
    }

    public function altaUsuariosPlataforma(Request $request)
    {
        $nombreCompleto = $request->input('nombre');
        $sucursal = $request->input('sucursal');
        $usuarioSAFI = $request->input('usuarioSAFI');
        $clave = $request->input('clave');
        $password = $request->input('password');
        $roles = $request->input('roles');
        $securePassword = Hash::make($password);
        $queryAgregarUsuario = "INSERT INTO `users` (`usuariosafi`, `nombre`,`clave`, `empleado`, `sucursal_id`, `email`, `celular`, `password`) 
                                VALUES ($usuarioSAFI,'$nombreCompleto', '$clave',0, $sucursal, '', '', '$securePassword')";
        $mensaje = [
            "estatus" => false
        ];

        try {
            $agregarUsuario = DB::connection('operaciones')->statement($queryAgregarUsuario);
            $mensaje["estatus"] = true;
        } catch (Exception $e) {
            $mensaje["query"] = $queryAgregarUsuario;
            $mensaje["error"] = $e;
        }
        $queryID = "SELECT MAX(id) id FROM users";
        $idPlataforma = 0;
        try
        {
            $userID = DB::connection('operaciones')->select($queryID);
            $idPlataforma = $userID[0]->id;
        }
        catch(Exception $e)
        {
            $mensaje["query"] = $queryID;
            $mensaje["error"] = $e;
        }
        $rol = 0;
        $queryRoles = "INSERT INTO role_user (`role_id`,`user_id`,`usuariosafi`) VALUES ($rol,$idPlataforma,0)";
        try
        {
            foreach($roles as $rol)
            {
                $queryRoles = "INSERT INTO role_user (`role_id`,`user_id`,`usuariosafi`) VALUES ($rol,$idPlataforma,0)";
                $insertRol = DB::connection('operaciones')->statement($queryRoles);
            }
        }
        catch(Exception $e)
        {
            $mensaje["query"] = $queryRoles;
            $mensaje["error"] = $e;
        }
        return json_encode($mensaje, JSON_UNESCAPED_UNICODE);
    }

    public function getRolesPlataforma()
    {
        $queryRoles = "SELECT id, rol FROM roles";
        $roles = DB::connection('operaciones')->select($queryRoles);
        DB::disconnect('operaciones');
        return json_encode($roles);
    }

    public function getSucursales()
    {
        $querySucursales = "SELECT id, nombre FROM sucursals";
        $sucursales = DB::connection('operaciones')->select($querySucursales);
        DB::disconnect('operaciones');
        return json_encode($sucursales);
    }

    public function cambiarNombreGrupo(Request $request)
    {
        $GrupoID = $request->input('GrupoID');
        $nuevoNombre = $request->input('nombre');
        $mensaje = [
            "Estado" => false
        ];
        $queryValidate = "SELECT * FROM GRUPOSCREDITO WHERE GrupoID = $GrupoID";
        try
        {
            $grupo = DB::connection('demo')->select($queryValidate);
            if(count($grupo)==0 || $grupo==null)
            {
                $mensaje["mensaje"] = "El grupo no existe Lic";
                return json_encode($mensaje, JSON_UNESCAPED_UNICODE);
            }
        }
        catch(Exception $e)
        {
            $mensaje["query"] = $queryValidate;
            $mensaje["errror"] = $e;
        }

        $queryNombreGrupo = "UPDATE GRUPOSCREDITO SET NombreGrupo = '$nuevoNombre' WHERE GrupoID = $GrupoID";
        try
        {
            $updateNombre = DB::connection('demo')->statement($queryNombreGrupo);
            $mensaje["Estado"] = true;
            $mensaje["mensaje"] = "Ya quedo lic";
        }
        catch(Exception $e)
        {
            $mensaje["query"] = $queryNombreGrupo;
            $mensaje["error"] = $e;
        }
        finally
        {
            DB::disconnect('demo');
        }
        return json_encode($mensaje, JSON_UNESCAPED_UNICODE);
    }
}