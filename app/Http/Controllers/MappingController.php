<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class MappingController extends Controller
{
    /**
     * Show the form for registering a new file format.
     */
    public function showRegisterForm(): View
    {
        return view('register_form');
    }
}