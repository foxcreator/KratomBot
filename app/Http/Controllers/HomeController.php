<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Promocode;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function members()
    {
        $members = Member::all();
        return view('admin.members.index', compact('members'));
    }

    public function promocodes()
    {
        $promocodes = Promocode::all();
        return view('admin.promocodes.index', compact('promocodes'));
    }
}
