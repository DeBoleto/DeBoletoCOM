<?php

namespace App\Modules\BoxOffice\Models;

use Illuminate\Database\Eloquent\Model;

class CortesCajas extends Model
{
    protected $table = "cortes_cajas";
    public $timestamps = false;
    protected $primaryKey = 'pk_corte_caja';

    protected $fillable = [
        'pk_usuario',
        'fecha_apertura',
        'fecha_cierre',
        'fondo_inicial',
        'total_calculado',
        'total_fisico',
        'diferencia',
        'estatus',
        'observaciones'
    ];

    /* RELATIONSHIP - INICIO */
    public function usuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'pk_usuario');
    }

    public function compras() {
        return $this->hasMany('App\Modules\Orders\Models\EventosCompras', 'pk_corte_caja', 'pk_corte_caja');
    }
    /* RELATIONSHIP - FIN */
}
