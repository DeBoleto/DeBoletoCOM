<?php

namespace App\Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;


class PromocionEventoLocalidades extends Model
{
    protected $table = "promocion_evento_localidades";
    public $timestamps = false;
    protected $primaryKey = 'id';
}
