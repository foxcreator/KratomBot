<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promocode;
use Illuminate\Http\Request;

class PostbackController extends Controller
{
    public function check(Request $request)
    {
        $code = $request->input('code');
        $promocode = Promocode::where('code', $code)->where('is_used', false)->first();

        if ($promocode && !$promocode->is_used) {
            return response()->json(['status' => 'success', 'is_used' => $promocode->is_used], 200);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Promocode not found'], 404);
        }
    }

    public function updateStatus(Request $request)
    {
        $code = $request->input('code');
        $status = $request->input('is_used');
        $storeName = $request->attributes->get('store_name');

        if (!$code || !$status || !is_bool($status)) {
            return response()->json(['status' => 'error', 'message' => 'No mandatory parameters'], 404);
        }

        $promocode = Promocode::where('code', $code)->where('is_used', false)->first();

        if ($promocode) {
            $promocode->is_used = $status;
            $promocode->store_name = $storeName;
            $promocode->save();
            return response()->json(['status' => 'success', 'message' => 'Promocode status updated'], 200);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Promocode not found'], 404);
        }
    }
}
