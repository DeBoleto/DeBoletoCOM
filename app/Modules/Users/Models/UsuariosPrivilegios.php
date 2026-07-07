<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use App\Library\FormatValidation;
use \Exception;
use DB;
use Auth;

use App\Modules\Users\Models\Usuarios;
use App\Modules\Users\Models\PrivilegiosCategorias;
use App\Modules\Users\Models\Privilegios;

class UsuariosPrivilegios extends Model
{
    protected $table = "usuarios_privilegios";
    public $timestamps = false;
    protected $primaryKey = 'pk_usuario';

    protected $fillable = ['pk_usuario', 'pk_privilegio', 'creacion_pk_usuario', 'creacion_fecha'];

     /* RELATIONSHIPS - INICIO */
    public function privilegios() {
        return $this->hasMany('App\Modules\Users\Models\Privilegios', 'pk_privilegio', 'pk_privilegio');
    }

    public function usuarios() {
        return $this->hasMany('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'pk_usuario');
    }

   /* public function create(array $options = array()) {
        $this['creacion_pk_usuario'] = Auth::user()->pk_usuario;
        $this['creacion_fecha'] = date('Y-m-d H:i:s');
        return save($options);
    }*/
     public function create($innerTransaction = true) {

        if($innerTransaction) DB::beginTransaction();

        try {
            $this['creacion_pk_usuario'] = Auth::user()->pk_usuario;
            $this['creacion_fecha'] = date('Y-m-d H:i:s');
            $this->save();
            if($innerTransaction) DB::commit();
            return true;
        } catch(ApplicationException $exception) {
            if($innerTransaction) DB::rollBack();
            throw $exception;
        }
    }

    public static function getPrivilegiosMenu(Usuarios $objUsuario) {
        $return = array();

        $lstPrivilegiosCategorias = PrivilegiosCategorias::orderBy('menu_orden', 'asc')->orderBy('pk_privilegio_categoria', 'asc')->get();

        foreach ($lstPrivilegiosCategorias as $itemPrivilegioCategoria) {
            $lstPrivilegios = $objUsuario->privilegios
                    ->where('pk_privilegio_categoria', '=', $itemPrivilegioCategoria->pk_privilegio_categoria)
                    ->where('menu', '=', 1)
                    ->where('activo', '=', 1)
                    ->sortBy('menu_orden');
            if(sizeof($lstPrivilegios) > 0 ) {
                array_push($return, array(
                    'categoria'         => $itemPrivilegioCategoria,
                    'privilegios'       => $lstPrivilegios
                ));
            }
        }

        return $return;
    }

    static public function view() {
        $retorno = DB::table('view_usuarios_privilegios');
        return $retorno;
    }

    static public function viewFind($pk_usuario) {
        $retorno = DB::table('view_usuarios_privilegios');
        $retorno->where('pk_usuario', '=', $pk_usuario);
        return $retorno->get()->first();
    }
}
