<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        $channels = json_decode(Setting::where('key', 'channels')->value('value'), true) ?? [];

        return view('admin.settings.index', compact('settings', 'channels'));
    }

    public function store(Request $request)
    {
        foreach ($request->all() as $key => $value) {
            $setting = Setting::where('key', $key)->first();
            if ($setting) {
                $setting->update([
                    'value' => $key === 'channels' ? json_encode($value) : $value
                ]);
            } else {
                Setting::create([
                    'key' => $key,
                    'value' => $key === 'channels' ? json_encode($value) : $value
                ]);
            }
        }

        return redirect()->back()->with(['status' => 'Настройки обновлены']);
    }
}


