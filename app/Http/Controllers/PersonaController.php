<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PersonasExport;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use DB;
use Carbon\Carbon;

// Models
use App\Persona;
use App\Proyectogeneral;
use App\Proyectourbanizacion;
use App\Deuda;
use App\User;
use App\Detalledeuda;
use App\Sucursal;
use App\PersonasPago;
use App\PersonasPagosMensualidades;
use App\Gestion;
use App\Ventaservicio;
use App\Detalleventaservicio;
use App\Categoriaurbanizacion;

class PersonaController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:personas.create')->only(['create','store']);
        $this->middleware('can:personas.index')->only('index');
        $this->middleware('can:personas.edit')->only(['edit','update']);
        $this->middleware('can:personas.show')->only('show');
        $this->middleware('can:personas.destroy')->only('destroy');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('persona.index');
    }

    public function getPersona()
    {
        return datatables()
            ->eloquent(Persona::query()->orderBy('nombre'))
            ->addColumn('btn_actions', 'persona.partials.btn_actions')
            ->addColumn('nombre_completo', function($row){
                return $row->nombre.' '.$row->apaterno.' '.$row->amaterno;
            })
            ->addColumn('fecha_afiliacion', function($row){
                if(!$row->fecha_afiliacion) return null;
                return date('d/M/Y', strtotime($row->fecha_afiliacion));
            })
            ->addColumn('ultimo_pago', function($row){
                if(!$row->ultimo_pago) return null;

                $ultimo_pago = Carbon::parse($row->ultimo_pago)->floorMonth();
                $fecha_actual = Carbon::now()->floorMonth();
                $anios = intval($ultimo_pago->diffInMonths($fecha_actual) / 12);
                $meses = $ultimo_pago->diffInMonths($fecha_actual) % 12;
                return '
                    '.date('M \d\e Y', strtotime($row->ultimo_pago)).' <br>
                    '.(date('Ym', strtotime($row->ultimo_pago)) < date('Ym') ? '<small> Debe '.($anios ? $anios.' a??o(s)' : '').($anios && $meses ? ' y ' : ' ').($meses ? $meses.' meses' : '').'</small>' : '').'
                ';
            })
            ->addColumn('deuda', function($row){
                if(!$row->ultimo_pago) return null;

                $ultimo_pago = $row->ultimo_pago.'-01';

                // Obtener las gestiones entre el ultimo pago y el a??o actual
                $gestiones = Gestion::where('gestion', '>', date('Y', strtotime($ultimo_pago)))->where('gestion', '<', date('Y'))->where('deleted_at', NULL)->get();
                $monto = 0;
                foreach ($gestiones as $gestion) {
                    $monto += $gestion->mensualidad *12;
                }

                $gestion_inicio = Gestion::where('gestion', date('Y', strtotime($ultimo_pago)))->where('deleted_at', NULL)->first();
                if($gestion_inicio){
                    if($gestion_inicio->gestion != date('Y')){
                        $meses = 12 - date('m', strtotime($ultimo_pago));
                        $monto += $gestion_inicio->mensualidad *$meses;
                    }
                }

                $gestion_actual = Gestion::where('gestion', date('Y'))->where('deleted_at', NULL)->first();
                if($gestion_actual){
                    $meses = date('m');
                    $monto += $gestion_actual->mensualidad *$meses;

                    if($gestion_actual->gestion == date('Y', strtotime($ultimo_pago))){
                        $meses = date('m', strtotime($ultimo_pago));
                        $monto -= $gestion_inicio->mensualidad *$meses;
                    }
                }

                return '<small>Bs.</small> '.number_format($monto > 0 ? $monto : 0, 2, ',', '.');
            })
            ->rawColumns(['nombre_completo', 'fecha_afiliacion', 'ultimo_pago', 'deuda', 'btn_actions'])
            ->toJson();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('persona.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'email' => ['string', 'max:255', 'unique:users']
        ];
        $messages = [
            'email.unique' => 'Este nombre de usuario ya a sido utlizado.'
        ];
        $this->validate($request, $rules, $messages);
        $persona = new Persona;
        $persona->nombre = $request->nombre;
        $persona->apaterno = $request->apaterno;
        $persona->amaterno = $request->amaterno;
        $persona->numeroregistro = $request->numeroregistro;
        $persona->telefono = $request->telefonodomicilio;
        // $persona->telefonooficina = $request->telefonooficina;
        // $persona->telefonocelular = $request->telefonocelular;
        $persona->fecha_afiliacion = $request->fecha_afiliacion;
        $persona->ultimo_pago = $request->ultimo_pago;
        $persona->direccion = $request->direccion;
        $persona->correo = $request->correo;
        $persona->save();
        
        
        if ($request->email) {
            $user = new User;
            $user->name = Str::lower($persona->nombre);
            $user->email = $request->email;
            if ($request->get('password')) {
                $user->password = Hash::make($request->get('password'));
            }
            $user->save();
        }

        toast('Registro insertado con ??xito!','success');
        return redirect()->route('personas.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $persona = Persona::findOrFail($id);
        return view('persona.show',compact('persona'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $persona = Persona::findOrFail($id);
        $user = User::where('persona_id',$persona->id)->first();
        return view('persona.edit',compact('persona','user'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $persona = Persona::findOrFail($id);
        $persona->nombre = $request->nombre;
        $persona->apaterno = $request->apaterno;
        $persona->amaterno = $request->amaterno;
        $persona->numeroregistro = $request->numeroregistro;
        $persona->telefono = $request->telefonodomicilio;
        $persona->fecha_afiliacion = $request->fecha_afiliacion;
        if ($request->ultimo_pago) {
            $persona->ultimo_pago = $request->ultimo_pago;
        }
        $persona->direccion = $request->direccion;
        $persona->correo = $request->correo;
        $persona->update();
        
        //registramos el usuario para el arquitecto

        $useravailable = User::where('persona_id', $persona->id)->first();
        $user = $useravailable ? $useravailable : null;
        if ($request->email) {
            if (!$user) {

                $rules = ['email' => 'required|unique:users'];
                $messages = [
                    'email.unique' => 'Este email ya ha sido utilizado.'
                ];
                $this->validate($request, $rules, $messages);

                $newuser = new User;
                $newuser->name = Str::lower($persona->nombre);
                $newuser->email = $request->email;
                $newuser->persona_id = $persona->id;
                if ($request->get('password')) {
                    $newuser->password = Hash::make($request->get('password'));
                }
                $newuser->save();

                // Asiganr rol de arquitecto
                $newuser->roles()->sync([5]);
            }else{

                $rules = ['email' => "required|unique:users,email,{$user->id}"];
                $messages = [
                    'email.unique' => 'Este nombre ya a sido utlizado.'
                ];
                $this->validate($request, $rules, $messages);

                $user->name = Str::lower($persona->nombre);
                $user->email = $request->email;
                $user->persona_id = $persona->id;
                if ($request->get('password')) {
                    $user->password = Hash::make($request->get('password'));
                }
                $user->update();
            }
        }
        toast('Registro actualizado con ??xito!','success');
        return redirect()->route('personas.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //dd($id);
        $persona = Persona::findOrFail($id);

        if ($persona->condicion == 1) {
            $persona->condicion='0';
            $persona->update();
            toast('Persona inhabilitada!','warning');
            return redirect()->route('personas.index');
        }
        else{
            $persona->condicion='1';
            $persona->update();
            toast('Persona habilitada!','warning');
            return redirect()->route('personas.index');
        }
    }

    // ===================================
    function pagomensualidad_index($id){
        $persona = Persona::find($id);
        $pagos = PersonasPago::where('persona_id', $id)
                    ->where('deleted_at', NULL)->get();
        return view('persona.pagomensualidad', compact('id', 'persona', 'pagos'));
    }

    function pagomensualidad_list($id, $gestion_id){
        $pago = PersonasPagosMensualidades::whereHas('pago', function($q) use($id){
                        $q->where('persona_id', $id);
                    })
                    ->where('gestion_id', $gestion_id)->where('deleted_at', null)->get();
        return response()->json($pago);
    }

    function pagomensualidad_store($id, Request $request){
        // dd($request->all());
        if(!$request->mes){
            toast('Debe seleccionar al menos una mensualidad','warning');
            return redirect()->route('personas.pagomensualidad.index', $id);
        }

        DB::beginTransaction();
        try {
            $personas_pago = PersonasPago::create([
                'user_id' => Auth::user()->id,
                'sucursal_id' => $request->sucursal_id,
                'persona_id' => $id,
                'fecha_pago' => $request->fecha_pago,
                'descuento' => $request->descuento,
                'observacion' => $request->observacion
            ]);

            $ultimo_mes = 0;
            for ($i=0; $i < count($request->mes); $i++) { 
                PersonasPagosMensualidades::create([
                    'personas_pago_id' => $personas_pago->id,
                    'gestion_id' => $request->gestion_id,
                    'mes' => $request->mes[$i],
                    'monto_pagado' => $request->monto_pagado[$i],
                ]);
                $ultimo_mes = $request->mes[$i];
            }

            // actualizar ??ltimo pago de la persona
            $gestion_pagada = Gestion::find($request->gestion_id);
            Persona::where('id', $id)->update([
                'ultimo_pago' => $gestion_pagada->gestion.'-'.str_pad($ultimo_mes, 2, "0", STR_PAD_LEFT)
            ]);

            DB::commit();

            toast('Pago registrado con ??xito','success');
            return redirect()->route('personas.pagomensualidad.index', $id);
        } catch (\Throwable $th) {
            DB::rollback();
            // throw $th;
            toast('Ocurri?? un error','error');
            return redirect()->route('personas.pagomensualidad.index', $id);
        }
    }

    public function pagomensualidad_print($id){
        $pago = PersonasPago::find($id);
        // return view('persona.pagomensualidad-print', compact('pago'));
        $pdf = \PDF::loadview('persona.pagomensualidad-print', compact('pago'));
        return $pdf->stream('Recibo de pago de mensualidad.pdf');
    }

    // ====================================================

    function ventaservicio_index($id){
        $persona = Persona::find($id);
        $ventas = Ventaservicio::where('persona_id', $id)
                    ->where('deleted_at', NULL)->get();
        return view('persona.ventaservicio', compact('id', 'persona', 'ventas'));
    }

    function ventaservicio_store($id, Request $request){
        // dd($request->all());
        DB::beginTransaction();
        try {
            $venta = Ventaservicio::create([
                'user_id' => Auth::user()->id,
                'sucursal_id' => $request->sucursal_id,
                'persona_id' => $id,
                'fecharegistro' => $request->fecharegistro,
                'observacion'  => $request->observacion,
            ]);

            if($request->servicio_id){
                for ($i=0; $i < count($request->servicio_id); $i++) { 
                    Detalleventaservicio::create([
                        'ventaservicio_id' => $venta->id,
                        'servicio_id' => $request->servicio_id[$i],
                        'precio' => $request->precio[$i],
                        'cantidad' => $request->cantidad[$i],
                        'descuento' => 0
                    ]);
                }
            }

            // En caso de pagar alguna gestion en la compra de servicios
            if($request->gestion_id){
                $persona = Persona::find($id);
                $personas_pago = PersonasPago::create([
                    'user_id' => Auth::user()->id,
                    'sucursal_id' => $request->sucursal_id,
                    'persona_id' => $id,
                    'ventaservicio_id' => $venta->id,
                    'fecha_pago' => $request->fecharegistro,
                    'descuento' => 0,
                    'observacion' => 'Pago al momento de pagar servicios.'
                ]);
    
                $ultimo_mes = $request->gestion_mes;
                for ($i=0; $i < $request->gestion_cantidad; $i++) { 
                    PersonasPagosMensualidades::create([
                        'personas_pago_id' => $personas_pago->id,
                        'gestion_id' => $request->gestion_id,
                        'mes' => $ultimo_mes,
                        'monto_pagado' => $request->gestion_precio,
                    ]);
                    $ultimo_mes++;
                }
    
                // actualizar ??ltimo pago de la persona
                $gestion_pagada = Gestion::find($request->gestion_id);
                Persona::where('id', $id)->update([
                    // Se quita un mes porque el bucle for lo recorre 1 vez de mas
                    'ultimo_pago' => $gestion_pagada->gestion.'-'.str_pad($ultimo_mes -1, 2, "0", STR_PAD_LEFT)
                ]);
            }

            DB::commit();

            toast('Venta registrada con ??xito!','success');
            return redirect()->route('personas.ventaservicio.index', $id);

        } catch (\Throwable $th) {
            DB::rollback();
            // dd($th);
            toast('Ocurri?? un error!','error');
            return redirect()->route('personas.ventaservicio.index', $id);
        }
    }

    public function ventaservicio_print($id){
        $venta = Ventaservicio::find($id);
        // return view('persona.ventaservicio-print', compact('venta'));
        $pdf = \PDF::loadview('persona.ventaservicio-print', compact('venta'));
        return $pdf->stream('Recibo de pago de servicio.pdf');
    }

    // ===================================

    function proyectogenerales_index($id){
        $persona = Persona::find($id);
        $proyectos = Proyectogeneral::where('persona_id', $id)
                    ->where('deleted_at', NULL)->get();
        return view('persona.proyectogenerales', compact('id', 'persona', 'proyectos'));
    }

    function proyectogenerales_store($id, Request $request){
        // dd($request->all());
        DB::beginTransaction();
        try {

            $proyecto = Proyectogeneral::create([
                'user_id' => Auth::user()->id,
                'sucursal_id' => $request->sucursal_id,
                'persona_id' => $id,
                'categoriageneral_id' => $request->categoriageneral_id,
                'costocategoria' => $request->costocategoria,
                'proyecto' => $request->proyecto,
                'propietario' => $request->propietario,
                'superficiemts2' => $request->superficiemts2,
                'totalbs' => floatval($request->costocategoria) * $request->superficiemts2,
                'descuento' => 0,
                'fecharegistro' => $request->fecharegistro,
                // 'archivo',
            ]);

            // En caso de pagar alguna gestion en el registro de proyecto
            if($request->gestion_cantidad){
                $persona = Persona::find($id);
                $personas_pago = PersonasPago::create([
                    'user_id' => Auth::user()->id,
                    'sucursal_id' => $request->sucursal_id,
                    'persona_id' => $id,
                    'proyectogeneral_id' => $proyecto->id,
                    'fecha_pago' => $request->fecharegistro,
                    'descuento' => 0,
                    'observacion' => 'Pago al momento de registrar proyecto.'
                ]);
    
                $ultimo_mes = $request->gestion_mes;
                for ($i=0; $i < $request->gestion_cantidad; $i++) { 
                    PersonasPagosMensualidades::create([
                        'personas_pago_id' => $personas_pago->id,
                        'gestion_id' => $request->gestion_id,
                        'mes' => $ultimo_mes,
                        'monto_pagado' => $request->gestion_precio,
                    ]);
                    $ultimo_mes++;
                }
    
                // actualizar ??ltimo pago de la persona
                $gestion_pagada = Gestion::find($request->gestion_id);
                Persona::where('id', $id)->update([
                    // Se quita un mes porque el bucle for lo recorre 1 vez de mas
                    'ultimo_pago' => $gestion_pagada->gestion.'-'.str_pad($ultimo_mes -1, 2, "0", STR_PAD_LEFT)
                ]);
            }

            DB::commit();

            toast('Proyecto registrado con ??xito!','success');
            return redirect()->route('personas.proyectogenerales.index', $id);

        } catch (\Throwable $th) {
            DB::rollback();
            // throw $th;
            toast('Ocurri?? un error','error');
            return redirect()->route('personas.proyectogenerales.index', $id);
        }
    }

    public function proyectogenerales_print($id){
        $proyecto = Proyectogeneral::find($id);
        // return view('persona.proyectogenerales-print', compact('proyecto'));
        $pdf = \PDF::loadview('persona.proyectogenerales-print', compact('proyecto'));
        return $pdf->stream('Recibo de registro de proyecto.pdf');
    }

    // ===================================

    function proyectourbanizacions_index($id){
        $persona = Persona::find($id);
        $proyectos = Proyectourbanizacion::where('persona_id', $id)
                    ->where('deleted_at', NULL)->get();
        $categorias = Categoriaurbanizacion::where('condicion', 1)->get();
        return view('persona.proyectourbanizacions', compact('id', 'persona', 'proyectos', 'categorias'));
    }

    function proyectourbanizacions_store($id, Request $request){
        // dd($request->all());
        DB::beginTransaction();
        try {

            $proyecto = Proyectourbanizacion::create([
                'user_id' => Auth::user()->id,
                'sucursal_id' => $request->sucursal_id,
                'persona_id' => $id,
                'categoriaurbanizacion_id' => $request->categoriaurbanizacion_id,
                'costo_pu_categoria' => $request->costo_pu_categoria,
                'proyecto' => $request->proyecto,
                'propietario' => $request->propietario,
                'superficiemts2' => $request->superficiemts2,
                'totalbs' => floatval($request->costo_pu_categoria) * $request->superficiemts2,
                'descuento' => 0,
                'fecharegistro' => $request->fecharegistro,
                // 'archivo',
            ]);

            // En caso de pagar alguna gestion en el registro de proyecto
            if($request->gestion_cantidad){
                $persona = Persona::find($id);
                $personas_pago = PersonasPago::create([
                    'user_id' => Auth::user()->id,
                    'sucursal_id' => $request->sucursal_id,
                    'persona_id' => $id,
                    'proyectourbanizacion_id' => $proyecto->id,
                    'fecha_pago' => $request->fecharegistro,
                    'descuento' => 0,
                    'observacion' => 'Pago al momento de registrar proyecto.'
                ]);
    
                $ultimo_mes = $request->gestion_mes;
                for ($i=0; $i < $request->gestion_cantidad; $i++) { 
                    PersonasPagosMensualidades::create([
                        'personas_pago_id' => $personas_pago->id,
                        'gestion_id' => $request->gestion_id,
                        'mes' => $ultimo_mes,
                        'monto_pagado' => $request->gestion_precio,
                    ]);
                    $ultimo_mes++;
                }
    
                // actualizar ??ltimo pago de la persona
                $gestion_pagada = Gestion::find($request->gestion_id);
                Persona::where('id', $id)->update([
                    // Se quita un mes porque el bucle for lo recorre 1 vez de mas
                    'ultimo_pago' => $gestion_pagada->gestion.'-'.str_pad($ultimo_mes -1, 2, "0", STR_PAD_LEFT)
                ]);
            }

            DB::commit();

            toast('Proyecto registrado con ??xito!','success');
            return redirect()->route('personas.proyectourbanizacions.index', $id);

        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
            toast('Ocurri?? un error','error');
            return redirect()->route('personas.proyectourbanizacions.index', $id);
        }
    }

    public function proyectourbanizacions_print($id){
        $proyecto = Proyectourbanizacion::find($id);
        // return view('persona.proyectogenerales-print', compact('proyecto'));
        $pdf = \PDF::loadview('persona.proyectogenerales-print', compact('proyecto'));
        return $pdf->stream('Recibo de registro de proyecto.pdf');
    }

    // ===================================

    //Proyectos generales
    public function pg_por_arquitectos_report(Request $request)
    {
        $sucursal_id = $request->sucursal_id;
        $persona_id = $request->persona_id;
        $fechainicio = $request->fechainicio;
        $fechafin = $request->fechafin;

        $proyectogenerals = Proyectogeneral::with('categoriageneral','persona')
            ->where('sucursal_id',$sucursal_id)
            ->where('persona_id',$persona_id)
            ->orderBy(DB::raw('DATE_FORMAT(fecharegistro, "%Y-%m-%d")','asc'))
            ->whereBetween(DB::raw('DATE_FORMAT(fecharegistro, "%Y-%m-%d")'),array($fechainicio,$fechafin))
            ->get();

        $pdf = \PDF::loadview('pdf.pg_por_arquitectos', compact('proyectogenerals'))->setPaper('A4','landscape');
        return $pdf->stream('ARQUITECTO '.$proyectogenerals[0]->persona->nombre.' - '.date('d-m-Y').'.pdf');
    }

    //Proyectos de urbanizacion
    public function pu_por_arquitectos_report(Request $request)
    {
        $sucursal_id = $request->sucursal_id;
        $persona_id = $request->persona_id;
        $fechainicio = $request->fechainicio;
        $fechafin = $request->fechafin;

        $proyectourbanizaciones = Proyectourbanizacion::with('categoriaurbanizacion','persona')
            ->where('sucursal_id',$sucursal_id)
            ->where('persona_id',$persona_id)
            ->orderBy(DB::raw('DATE_FORMAT(fecharegistro, "%Y-%m-%d")','asc'))
            ->whereBetween(DB::raw('DATE_FORMAT(fecharegistro, "%Y-%m-%d")'),array($fechainicio,$fechafin))
            ->get();

        $pdf = \PDF::loadview('pdf.pu_por_arquitectos', compact('proyectourbanizaciones'))->setPaper('A4','landscape');
        return $pdf->stream('ARQUITECTO '.$proyectourbanizaciones[0]->persona->nombre.' - '.date('d-m-Y').'.pdf');
    }

    public function exportExcel()
    {
        return Excel::download(new PersonasExport, 'arquitectos-list.xlsx');
    }
    
    public function exportPDF()
    {
        $personas = Persona::orderBy('nombre','asc')->get();
        // return $personas;
        $pdf = \PDF::loadview('pdf.personas-list', compact('personas'))->setPaper('A4','landscape');
        return $pdf->stream('ARQUITECTOS - '.date('d-m-Y').'.pdf');
    }

    public function pagodeuda_rangofecha_report(Request $request)
    {
        $sucursal_id = $request->sucursal_id;
        $fechainicio = $request->fechainicio;
        $fechafin = $request->fechafin;

        $pagodeudas = Deuda::with('sucursal','persona','tipopago','detalledeudas.mes')
            ->where('sucursal_id',$sucursal_id)
            ->orderBy(DB::raw('DATE_FORMAT(fecharegistro, "%Y-%m-%d")','asc'))
            ->whereBetween(DB::raw('DATE_FORMAT(fecharegistro, "%Y-%m-%d")'),array($fechainicio,$fechafin))
            ->get();

        $deudatotal_Pagos = DB::table('detalledeudas as detdeuda')
            ->join('deudas','deudas.id','=','detdeuda.deuda_id')
            ->select(DB::raw('sum(detdeuda.totalbs) as sumaTotal'))
            ->where('deudas.sucursal_id',$sucursal_id)
            ->whereBetween(DB::raw('DATE_FORMAT(fecharegistro, "%Y-%m-%d")'),array($fechainicio,$fechafin))
            ->get();

        $sucursal = Sucursal::find($sucursal_id);

        $pdf = \PDF::loadview('pdf.arquitecto_pagodeuda_rangofecha', compact('pagodeudas','deudatotal_Pagos','sucursal'));
        return $pdf->stream('PAGO DEUDAS ARQUITECTOS - '.date('d-m-Y').'.pdf');
    }
}
