<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class GuestLayout extends Component
{
    public function __construct(public mixed $booking, public mixed $property, public ?string $title = null, public ?string $state = null)
    {
    }

    public function render(): View|Closure|string
    {
        return view('layouts.guest');
    }
}
