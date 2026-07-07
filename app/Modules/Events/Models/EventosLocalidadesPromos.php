<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class EventosLocalidadesPromos extends Model
{
    protected $table = "eventos_localidades_promos";
    public $timestamps = false;
    protected $primaryKey = 'pk_localidad_promo';

    protected $fillable = [
        'pk_evento_localidad', 'promocion', 'num_boleto', 'pk_usuario', 'fecha_inicio', 'fecha_termino'
    ];

    public function creacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'pk_usuario');
    }

    public function save(array $options = array()) {

        $this['pk_usuario'] = Auth::user()->pk_usuario;

        return parent::save($options);
    }

    public function create(array $options = array()) {
        if( $this['pk_localidad_promo'] === null) {
            $this['pk_usuario'] = Auth::user()->pk_usuario;
            return save($options);
        } else {
            return false;
        }
    }

    public function update(array $attributes = array(), array $options = array()) {
        return save($options);
    }

    public function delete() {
        if( $this['eliminado'] == 0) {
            return save();
        } else {
            return false;
        }
    }


}
