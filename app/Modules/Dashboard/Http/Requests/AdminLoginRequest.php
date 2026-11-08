<?php

namespace App\Modules\Dashboard\Http\Requests;

use App\Modules\BoxOffice\Http\Requests\LoginRequest;

class AdminLoginRequest extends LoginRequest
{
    protected function guard(): string
    {
        return 'admin';
    }
}
