<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    public function tokens()
    {
        $tokens = ApiToken::all();
        return view('admin.settings.tokens', compact('tokens'));
    }

    public function saveShopToken(Request $request)
    {
        $storeName = $request->get('store_name');
        $token = $this->generateToken();

        $apiToken = ApiToken::create([
            'token' => $token,
            'store_name' => $storeName,
        ]);

        if ($apiToken) {
            return redirect()->back()->with(['status' => "Токен для $storeName успешно сгенерирован"]);
        }
        return redirect()->back()->with(['error' => "Что то пошло не так. Попробуйте снова"]);

    }

    protected function generateToken()
    {
        do {
            $token = Str::random(20) . Carbon::now()->unix();
        } while (ApiToken::where('token', $token)->exists());

        return $token;
    }
}


