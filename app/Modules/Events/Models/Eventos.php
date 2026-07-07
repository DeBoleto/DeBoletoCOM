<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use App\Library\FormatValidation;
use App\Library\DateTimeBuilder;
use App\Library\Errors;
use App\Exceptions\ApplicationException;
use \Exception;
use DB;
use Auth;

use App\Modules\Events\Models\EventosTotales;
use App\Modules\Events\Models\EventosFunciones;
use App\Modules\Events\Models\UsuariosEventos;


class Eventos extends Model
{
    protected $table = "eventos";
    public $timestamps = false;
    protected $primaryKey = 'pk_evento';

    protected $fillable = [
        'pk_usuario', 'pk_escenario', 'pk_evento_status', 'pk_evento_comision_tipo', 'comision', 'evento', 'imagen', 'inicio_fecha', 'fin_fecha',
        'descripcion', 'comentarios', 'creacion_pk_usuario', 'creacion_fecha', 'modificacion_pk_usuario', 'modificacion_fecha', 'eliminado',
        'promocion_porcentaje', 'promocion', 'msi', 'transfer', 'ocultar'
    ];

    /* RELATIONSHIPS - INICIO */

    public function usuario() {
        return $this->belongsTo('App\Modules\Events\Models\EventosStatus', 'pk_evento_status', 'pk_evento_status');
    }

    public function escenario() {
        return $this->belongsTo('App\Modules\Venues\Models\Escenarios', 'pk_escenario', 'pk_escenario');
    }

    public function escenarioFormato() {
        return $this->belongsTo('App\Modules\Venues\Models\EscenariosFormatos', 'pk_escenario_formato', 'pk_escenario_formato');
    }

    public function categorias() {
        return $this->belongsToMany('App\Modules\Events\Models\EventosCategorias', 'eventos_map_eventos_categorias', 'pk_evento', 'pk_evento_categoria');
    }

    public function eventosFunciones() {
        return $this->hasMany('App\Modules\Events\Models\EventosFunciones', 'pk_evento', 'pk_evento');
    }

    public function eventosFuncionesBoletos() {
        return $this->hasMany('App\Modules\Tickets\Models\EventosFuncionesBoletos', 'pk_evento', 'pk_evento');
    }

    public function eventosLocalidades() {
        return $this->hasMany('App\Modules\Events\Models\EventosLocalidades', 'pk_evento', 'pk_evento');
    }

    public function eventoStatus() {
        return $this->belongTo('App\Modules\Events\Models\EventosStatus', 'pk_evento_status', 'pk_evento_status');
    }

    public function eventoComisionTipo() {
        return $this->belongTo('App\Modules\Events\Models\EventosComisionesTipos', 'pk_evento_comision_tipo', 'pk_evento_comision_tipo');
    }

    public function eventosTotales() {
        return $this->hasOne('App\Modules\Events\Models\EventosTotales', 'pk_evento', 'pk_evento');
    }

    public function creacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'creacion_pk_usuario');
    }

    public function modificacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'modificacion_pk_usuario');
    }
    /* RELATIONSHIPS - FIN */


    static public function view() {
        $retorno = DB::table('view_eventos');

        return $retorno;
    }

    static public function viewFind($pk_evento) {
        $retorno = DB::table('view_eventos');
        $retorno->where('pk_evento', '=', $pk_evento);
        return $retorno->get()->first();
    }

    static public function eventoSlideBar() {
        $retorno = DB::table('view_eventos_slide_bar');
        return $retorno->get();
    }

    static public function formatBadgeFecha($fecha) {
        $dt = new \DateTime($fecha);
        $meses = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

        return [
            'dia' => $dt->format('d'),
            'mes' => $meses[(int) $dt->format('n') - 1],
        ];
    }

    static public function proximos($nombre = null, $categoriaSlug = null) {
        $query = self::view()
            ->where('fin_fecha', '>=', date('Y-m-d'))
            ->where('pk_evento_status', 2)
            ->where('ocultar', '0')
            ->orderBy('inicio_fecha', 'ASC');

        if ($nombre) {
            $query->where('evento', 'LIKE', '%' . $nombre . '%');
        }

        if ($categoriaSlug) {
            $categoria = EventosCategorias::where('friendly_url', $categoriaSlug)->first();
            if ($categoria) {
                $query->where('pk_evento_categoria', $categoria->pk_evento_categoria);
            }
        }

        return $query->get();
    }

    static public function viewFindByUrl($url) {
        $retorno = DB::table('view_eventos');
        $retorno->where('friendly_url', '=', $url);
        return $retorno->get()->first();
    }

    public function save(array $options = array()) {

        $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
        $this['modificacion_fecha'] = date('Y-m-d H:i:s');

        return parent::save($options);
    }

    public function create(array $options = array(), $innerTransaction = true) {

        if($innerTransaction) DB::beginTransaction();

        $this->pk_usuario                   = FormatValidation::getPrimaryKey(\Session::get('EVENTO_NEW')['step1']['pk_usuario']);
        $this->pk_escenario                 = FormatValidation::getPrimaryKey(\Session::get('EVENTO_NEW')['step1']['pk_escenario']);
        $this->pk_escenario_formato         = FormatValidation::getPrimaryKey(\Session::get('EVENTO_NEW')['step1']['pk_escenario_formato']);
        $this->pk_evento_status             = FormatValidation::getPrimaryKey(3);
        $this->pk_evento_comision_tipo      = FormatValidation::getPrimaryKey(2);
        $this->pk_boleto_formato_tipo       = FormatValidation::getPrimaryKey(13);
        $this->comision                     = FormatValidation::getUnsignedDecimal(30);
        $this->evento                       = FormatValidation::getValidString(\Session::get('EVENTO_NEW')['step1']['evento']);
        $this->imagen                       = FormatValidation::getValidString(\Session::get('EVENTO_NEW')['step1']['imagen'], '');
        $this->descripcion                  = FormatValidation::getValidString(\Session::get('EVENTO_NEW')['step1']['descripcion'], '');
        $this->comentarios                  = FormatValidation::getValidString(\Session::get('EVENTO_NEW')['step1']['comentarios'], '');
        $this->pk_evento_categoria          = FormatValidation::getPrimaryKey(0);

        try {
            if( $this['pk_evento'] === null) {
                $this['creacion_pk_usuario'] = Auth::user()->pk_usuario;
                $this['creacion_fecha'] = date('Y-m-d H:i:s');
                if($this->save()) {

                    $escenario = \App\Modules\Venues\Models\EscenariosFormatos::find($this['pk_escenario_formato']);
                    $xmlEscenario = \App\Library\XMLConverter::stringToXml($escenario->escenario_xml);

                    $arrFunciones = \Session::get('EVENTO_NEW')['step3']['funciones'];
                    $arrEventosFunciones = array();
                    $funcionMenor = DateTimeBuilder::create($arrFunciones[0]);
                    $funcionMayor = DateTimeBuilder::create($arrFunciones[0]);

                    for($i=0; $i < sizeof($arrFunciones); $i++) {
                        array_push($arrEventosFunciones, new \App\Modules\Events\Models\EventosFunciones([
                            'fecha'                     => $arrFunciones[$i],
                            'creacion_pk_usuario'       => $this['creacion_pk_usuario'],
                            'creacion_fecha'            => $this['creacion_fecha'],
                            'modificacion_pk_usuario'   => $this['modificacion_pk_usuario'],
                            'modificacion_fecha'        => $this['creacion_fecha']
                        ]));

                        $itemFuncion = DateTimeBuilder::create($arrFunciones[$i]);

                        if( $itemFuncion->DateTime != false
                            && $funcionMenor->DateTime != false
                            &&  $itemFuncion->DateTime < $funcionMenor->DateTime ) {
                            $funcionMenor = $arrFunciones[$i];
                        }

                        if( $itemFuncion->DateTime != false
                            && $funcionMayor->DateTime != false
                            &&  $itemFuncion->DateTime > $funcionMayor->DateTime ) {
                            $funcionMenor = $arrFunciones[$i];
                        }

                    }
                    $this->eventosFunciones()->saveMany($arrEventosFunciones);

                    $this->inicio_fecha = $funcionMenor->getDateTimeAtom();
                    $this->fin_fecha = $funcionMayor->getDateTimeAtom();
                    $this->save();

                    $objPeriodoVenta = new \App\Modules\Events\Models\EventosPeriodosVentas();
                    $objPeriodoVenta->pk_evento         = $this['pk_evento'];
                    $objPeriodoVenta->periodo_venta     = 'normal';
                    $objPeriodoVenta->create(false);

                    $arrLocalidades = \Session::get('EVENTO_NEW')['step2']['localidades'];
                    $arrEventosLocalidades = array();
                    for($i = 0; $i < sizeof($xmlEscenario->localities->locality); $i++) {
                        array_push($arrEventosLocalidades, new \App\Modules\Events\Models\EventosLocalidades([
                            'pk_evento_periodo_venta'   => FormatValidation::getPrimaryKey($objPeriodoVenta->pk_evento_periodo_venta),
                            'evento_localidad'          => FormatValidation::getValidString((string)$xmlEscenario->localities->locality[$i]->attributes()['name']),
                            'precio'                    => FormatValidation::getUnsignedDecimal($arrLocalidades[(integer)$xmlEscenario->localities->locality[$i]->attributes()['locality_id']]),
                            'creacion_pk_usuario'       => $this['creacion_pk_usuario'],
                            'creacion_fecha'            => $this['creacion_fecha'],
                            'modificacion_pk_usuario'   => $this['modificacion_pk_usuario'],
                            'modificacion_fecha'        => $this['creacion_fecha']
                        ]));
                    }

                    $this->eventosLocalidades()->saveMany($arrEventosLocalidades);

                    $objEventoTotales = new \App\Modules\Events\Models\EventosTotales();
                    $objEventoTotales->pk_evento                = $this['pk_evento'];
                    $objEventoTotales->total_boletos            = 0;
                    $objEventoTotales->total_boletos_vendidos   = 0;
                    $objEventoTotales->total_venta              = 0;
                    $objEventoTotales->total_comision_aplicacion    = 0;
                    $objEventoTotales->total_comision_pago_linea    = 0;
                    $objEventoTotales->total_utilidad           = 0;
                    $objEventoTotales->create(false);

                    $objEventoUsuarios = new \App\Modules\Events\Models\UsuariosEventos();
                    $objEventoUsuarios->pk_evento   = $this['pk_evento'];
                    $objEventoUsuarios->pk_usuario  = 57;
                    $objEventoUsuarios->eliminado   = 0;
                    $objEventoUsuarios->create(false);

                    $objEventoUsuarios = new \App\Modules\Events\Models\UsuariosEventos();
                    $objEventoUsuarios->pk_evento   = $this['pk_evento'];
                    $objEventoUsuarios->pk_usuario  = 1;
                    $objEventoUsuarios->eliminado   = 0;
                    $objEventoUsuarios->create(false);

                    if($innerTransaction) DB::commit();
                    \Session::forget('EVENTO_NEW');
                    return true;

                } else {
                    throw new ApplicationException('EVENTOS_CREATE_03');
                }
            } else {
                throw new ApplicationException('EVENTOS_CREATE_02');
            }
        } catch(ApplicationException $exception) {
            if($innerTransaction) DB::rollBack();
            throw $exception;
        }

    }

    public function update(array $attributes = array(), array $options = array()) {
        return save($options);
    }

    public function delete() {
        if( $this['eliminado'] == 0) {
            $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
            $this['modificacion_fecha'] = date('Y-m-d H:i:s');
            $this['eliminado'] = 1;
            return save();
        } else {
            return false;
        }
    }


}
