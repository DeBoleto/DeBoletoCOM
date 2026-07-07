<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class PrivilegiosCategorias extends Model
{
    protected $table = "privilegios_categorias";
    public $timestamps = false;
    protected $primaryKey = 'pk_privilegio_categoria';

    protected $fillable = ['privilegio_categoria', 'menu_orden', 'menu_icono'];

    /* RELATIONSHIP - INICIO */
    public function privilegios() {
        return $this->hasMany('App\Modules\Users\Models\Privilegios', 'pk_privilegio_categoria', 'pk_privilegio_categoria');
    }
    /* RELATIONSHIP - FIN */
}
