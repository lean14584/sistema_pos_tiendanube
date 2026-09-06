<?php

namespace App\Http\Controllers;

use App\Support\PromotionPoster;
use Illuminate\Http\Response;

class PromotionPosterController extends Controller
{
    public function __invoke(): Response
    {
        return response(PromotionPoster::generate(), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="ofertas.png"',
        ]);
    }
}
