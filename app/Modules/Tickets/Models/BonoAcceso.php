<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class BonoAcceso extends Model
{
    protected $table = "bonos_accesos";
    public $timestamps = false;
    protected $primaryKey = 'pk';

    protected $fillable = ['folio', 'escaneado'];
}
