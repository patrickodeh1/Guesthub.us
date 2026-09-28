<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class GuestLayout extends Component
{
    public function __construct(
        public mixed $booking = null,
        public mixed $property = null,
        public ?string $title = null,
        public ?string $state = null,
    ) {}

    public function render(): View|Closure|string
    {
        return view($this->property === null ? 'layouts.cleaning-guest' : 'layouts.guest');
    }
}
