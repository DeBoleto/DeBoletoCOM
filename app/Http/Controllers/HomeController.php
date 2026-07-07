<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Home/Index', [
            'proposals' => [
                [
                    'id'    => 'a',
                    'name'  => 'Propuesta A — Dark Stage',
                    'desc'  => 'Fondo oscuro con acento verde DeBoleto y glow en cards',
                    'url'   => '/proposals/propuesta-a-dark-stage.html',
                ],
                [
                    'id'    => 'b',
                    'name'  => 'Propuesta B — Festival Vibrante',
                    'desc'  => 'Fondo claro, stats bar, countdown en tiempo real, pills de ciudad',
                    'url'   => '/proposals/propuesta-b-festival-vibrante.html',
                ],
                [
                    'id'    => 'c',
                    'name'  => 'Propuesta C — Gradient Titles',
                    'desc'  => 'Base oscura con títulos de eventos en degradado #2ba17c → #a8f0dc animado',
                    'url'   => '/proposals/propuesta-c-gradient-titles.html',
                ],
            ],
        ]);
    }
}
