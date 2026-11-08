<?php

namespace App\Modules\Orders\Models;

use App\Exceptions\ApplicationException;
use DB;
use Illuminate\Database\Eloquent\Model;

class ContraCargoCompra extends Model
{
    protected $table = 'contracargo_compra';

    public $timestamps = false;

    protected $primaryKey = 'pk_contracargo_compra';

    protected $fillable = ['pk_evento_compra', 'pk_openpay', 'estatus', 'json_respuesta', 'creacion_fecha'];

    public function save(array $options = [])
    {
        return parent::save($options);
    }

    public function create($innerTransaction = true)
    {
        if ($innerTransaction) {
            DB::beginTransaction();
        }
        try {
            if ($this['pk_contracargo_compra'] === null) {
                $this['creacion_fecha'] = date('Y-m-d H:i:s');
                $this->save();
                if ($innerTransaction) {
                    DB::commit();
                }

                return true;
            } else {
                throw new ApplicationException('TICKETS_CREATE_10');
            }
        } catch (ApplicationException $exception) {
            if ($innerTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }
    }

    public function update(array $attributes = [], array $options = [])
    {
        try {
            if ($this['pk_contracargo_compra'] != null) {
                $this->save();
            } else {
                throw new ApplicationException('TICKETS_CREATE_10');
            }
        } catch (ApplicationException $exception) {
            throw $exception;
        }
    }
}
