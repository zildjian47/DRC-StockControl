<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class ScannerLayout extends Component
{
    public ?string $backUrl;

    public function __construct(?string $backUrl = null)
    {
        $this->backUrl = $backUrl;
    }

    /**
     * Dapatkan view / isi yang merepresentasikan komponen layout mobile.
     */
    public function render(): View
    {
        return view('layouts.scanner');
    }
}