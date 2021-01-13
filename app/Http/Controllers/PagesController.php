<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PagesController extends Controller
{
    public function root()
    {
        return redirect()->route('products.index');
        // return view('pages.root');
    }
}
