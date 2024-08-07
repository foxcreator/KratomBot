<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Promocode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index()
    {
        return redirect()->route('admin.dashboard');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function members(Request $request)
    {
        $query = $request->input('table_search');

        if ($query) {
            $members = Member::when($query, function ($queryBuilder) use ($query) {
                return $queryBuilder->where('phone', 'LIKE', '%' . $query . '%')
                    ->orWhere('telegram_id', 'LIKE', '%' . $query . '%');
            })->paginate(20);
        } else {
            $members = Member::paginate(20);
        }
        return view('admin.members.index', compact('members'));
    }

    public function promocodes(Request $request)
    {
        $query = $request->input('table_search');

       if ($query) {
           $promocodes = Promocode::when($query, function ($queryBuilder) use ($query) {
               return $queryBuilder->where('code', 'LIKE', '%' . $query . '%')
                   ->orWhere('store_name', 'LIKE', '%' . $query . '%');
           })->paginate(20);
       } else {
           $promocodes = Promocode::query()->paginate(20);
       }
        return view('admin.promocodes.index', compact('promocodes'));
    }

    public function getStatistics(Request $request)
    {
        $startDate = $request->input('start_date')
            ? Carbon::make($request->input('start_date'))->startOfDay()
            : Carbon::today()->startOfDay();
        $endDate = $request->input('end_date')
            ? Carbon::make($request->input('end_date'))->endOfDay()
            : Carbon::today()->endOfDay();

        $statistics = DB::table('promocodes')
            ->select('store_name', DB::raw('COUNT(*) as usage_count'))
            ->where('is_used', true)
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->groupBy('store_name')
            ->orderBy('store_name')
            ->get();


        return view('admin.promocodes.statistics', compact('statistics', 'startDate', 'endDate'));
    }
}
