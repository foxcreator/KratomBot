<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Telegram\Bot\Laravel\Facades\Telegram;

class MemberController extends Controller
{
    public function sendMessageForm(Member $member)
    {
        return view('admin.members.send-message', compact('member'));
    }

    public function sendMessage(Request $request, Member $member)
    {
        $request->validate(['message' => 'required|string|max:4096']);
        Telegram::sendMessage([
            'chat_id' => $member->telegram_id,
            'text' => $request->message
        ]);
        return redirect()->back()->with('status', 'Повідомлення надіслано!');
    }
}
