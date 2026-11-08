<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;

class CodigoLogin extends Model
{
    protected $table = 'codigo_login';

    public $timestamps = false;

    protected $primaryKey = 'pk_codigo_login';

    protected $fillable = ['telefono', 'codigo'];
}
