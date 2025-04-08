<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * CSRF 검사를 제외할 URI들
     *
     * @var array<int, string>
     */
    protected $except = [
        '/toggle-favorite',
    ];
}
