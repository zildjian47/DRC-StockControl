<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class ScannerLayout extends Component
{
    /**
     * Dapatkan view / isi yang merepresentasikan komponen layout mobile.
     */
    public function render(): View
    {
        return view('layouts.scanner');
    }
}