<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use App\Exceptions\ApplicationException;
use DB;

class UsuariosEventos extends Model
{
    protected $table = "usuarios_eventos";
    public $timestamps = false;
    protected $primaryKey = 'pk_usuario_evento';

    protected $fillable = ['pk_usuario', 'pk_evento', 'eliminado'];

    /* RELATIONSHIPS - INICIO */
    public function eventos() {
        return $this->hasMany('App\Modules\Events\Models\Eventos', 'pk_evento', 'pk_evento');
    }

    public function usuarios() {
        return $this->hasMany('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'pk_usuario');
    }

    public function create($innerTransaction = true) {

        if($innerTransaction) DB::beginTransaction();

        try {
            $this->save();
            if($innerTransaction) DB::commit();
            return true;
        } catch(ApplicationException $exception) {
            if($innerTransaction) DB::rollBack();
            throw $exception;
        }
    }


    static public function view() {
        $retorno = DB::table('view_usuarios_eventos');
        return $retorno;
    }

    static public function viewFind($pk_usuario_evento) {
        $retorno = DB::table('view_usuarios_eventos');
        $retorno->where('pk_usuario_evento', '=', $pk_usuario_evento);
        return $retorno->get()->first();
    }

}
