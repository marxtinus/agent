<?php

use App\Http\Controllers\BroadcastTestController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::prefix('auth')
    ->middleware(['guest'])
    ->group(function () {
        Route::get('{provider}/redirect', [OAuthController::class, 'redirect'])->name('auth.provider.redirect');
        Route::get('{provider}/callback', [OAuthController::class, 'callback'])->name('auth.provider.callback');
    });

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

Route::middleware(['auth'])->group(function () {
    Route::get('chat', [ChatController::class, 'index'])->name('chat');
    Route::post('chat/messages', [ChatController::class, 'store'])->name('chat.messages.store');

    Route::get('broadcast-test', [BroadcastTestController::class, '__invoke'])->name('broadcast-test');
    Route::post('broadcast-test/send', [BroadcastTestController::class, 'send'])->name('broadcast-test.send');
    Route::post('broadcast-test/send-private', [BroadcastTestController::class, 'sendPrivate'])->name('broadcast-test.send-private');
});

require __DIR__.'/settings.php';
