<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function transfers(): View
    {
        return view('admin.transfers');
    }

    public function materials(): View
    {
        return view('admin.materials');
    }

    public function workOrders(): View
    {
        return view('admin.work-orders');
    }
}
