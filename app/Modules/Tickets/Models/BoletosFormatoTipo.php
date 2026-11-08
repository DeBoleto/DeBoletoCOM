<?php

namespace App\Modules\Tickets\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de formatos de impresión. `vista` guarda la vista Blade legacy.
 *
 * @property int $pk_boleto_formato_tipo
 * @property string|null $vista
 */
class BoletosFormatoTipo extends Model
{
    public $timestamps = false;

    protected $table = 'boletos_formato_tipo';

    protected $primaryKey = 'pk_boleto_formato_tipo';

    protected $guarded = ['*'];
}
