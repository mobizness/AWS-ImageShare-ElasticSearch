<?php

namespace App\Http\Controllers\Pub;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Mail\ForgotUsername;

class HomeController extends Controller
{
    public function index()
    {
        return view('pub.index');    
    }
}