<?php

namespace App\Modules\Users\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Notifications\UserResetPasswordNotification;
use App\Library\DataTable;
use DB;
use Auth;
use Session;


class Usuarios extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuarios';
    public $timestamps = false;
    protected $primaryKey = 'pk_usuario';

    protected $fillable = ['pk_usuario_tipo', 'nombre', 'apellido_paterno', 'apellido_materno', 'correo', 'password', 'telefono', 'nacimiento_fecha', 'creacion_pk_usuario', 'creacion_fecha', 'modificacion_pk_usuario', 'modificacion_fecha', 'eliminado', 'req_update'];
    protected $hidden = ['password', 'remember_token'];

    /* RELATIONSHIPS - INICIO */
    public function usuarioTipo() {
        return $this->belongsTo('App\Modules\Users\Models\UsuariosTipos', 'pk_usuario_tipo', 'pk_usuario_tipo');
    }

    public function creacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'creacion_pk_usuario');
    }

    public function modificacionUsuario() {
        return $this->belongsTo('App\Modules\Users\Models\Usuarios', 'pk_usuario', 'modificacion_pk_usuario');
    }

    public function privilegios() {
        return $this->belongsToMany('App\Modules\Users\Models\Privilegios', 'usuarios_privilegios', 'pk_usuario', 'pk_privilegio');
    }

    /* RELATIONSHIPS - FIN */

    /**
     * Overrided Methods
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new UserResetPasswordNotification($token));
    }

    public function getEmailForPasswordReset()
    {
        return $this->correo;
    }

    public function routeNotificationFor($driver)
    {
        if (method_exists($this, $method = 'routeNotificationFor'.Str::studly($driver))) {
            return $this->{$method}();
        }

        switch ($driver) {
            case 'database':
                return $this->notifications();
            case 'mail':
                return $this->correo;
            case 'nexmo':
                return $this->telefono;
        }
    }
    //END

    static public function view() {
        $retorno = DB::table('view_usuarios');
        return $retorno;
    }

    static public function viewFind($pk_usuario) {
        $retorno = DB::table('view_usuarios');
        $retorno->where('pk_usuario', '=', $pk_usuario);
        return $retorno->get()->first();
    }

    static public function emailFind($correo) {
        $retorno = DB::table('usuarios');
        $retorno->where('correo', $correo);
        return $retorno->get()->first();
    }

    static public function telefonoFind($telefono) {
        $retorno = DB::table('usuarios');
        $retorno->where('telefono', $telefono);
        return $retorno->get()->first();
    }

    public function updateTelefono($telefono) {
        $retorno = DB::table('usuarios');
        $retorno->where('telefono', $telefono);
        $this['modificacion_fecha'] = date('Y-m-d H:i:s');

        return parent::save($options);
    }

    public function save(array $options = array()) {

        $this['modificacion_pk_usuario'] = (Auth::check())? Auth::user()->pk_usuario : $this['pk_usuario'];
        $this['modificacion_fecha'] = date('Y-m-d H:i:s');

        return parent::save($options);
    }

    public function resetPass($password) {
        $this['modificacion_pk_usuario'] =  $this['pk_usuario'];
        $this['modificacion_fecha'] = date('Y-m-d H:i:s');
        $this['password'] = $password;
        $this['req_update'] = 0;
        return $this->save();
    }

    public function create(array $options = array()) {
        if( $this['pk_usuario'] === null) {
            $this['creacion_pk_usuario'] = (Auth::check())? Auth::user()->pk_usuario : 1;
            $this['creacion_fecha'] = date('Y-m-d H:i:s');
            $this['modificacion_pk_usuario'] = (Auth::check())? Auth::user()->pk_usuario : 1;
            $this['modificacion_fecha'] = date('Y-m-d H:i:s');
            Log::info('Correo Usuario: '.$this['correo']);
            return parent::save($options);
        } else {
            return false;
        }
    }

    public function update(array $attributes = array(), array $options = array()) {
        return $this->save($options);
    }

    public function delete() {
        if( $this['eliminado'] == 0) {
            $this['modificacion_pk_usuario'] = Auth::user()->pk_usuario;
            $this['modificacion_fecha'] = date('Y-m-d H:i:s');
            $this['eliminado'] = 1;
            return save();
        } else {
            return false;
        }
    }

    public static function GetPrivilegio($usuario, $privilegio) {
        $query = (string)"
            select count(up.pk_privilegio) existe
            from usuarios_privilegios up
            where up.pk_usuario = $usuario and up.pk_privilegio = $privilegio";

        // Construir la consulta usando el constructor de consultas de Laravel

//        Log::alert('SQL: '.$query);

        $results = DB::select($query);
        return $results[0];
    }
}
