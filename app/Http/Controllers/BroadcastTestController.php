<?php

namespace App\Http\Controllers;

use App\Events\MessageATous;
use App\Events\MessagePrive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastTestController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('BroadcastTest');
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        MessageATous::dispatch($data['message']);

        return back()->with('success', 'Message envoyé !');
    }

    public function sendPrivate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        MessagePrive::dispatch($request->user()->id, $data['message']);

        return back()->with('success', 'Message privé envoyé !');
    }
}
