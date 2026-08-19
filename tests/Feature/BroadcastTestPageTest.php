<?php

use App\Events\MessageATous;
use App\Events\MessagePrive;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('broadcast-test'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the broadcast test page', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('broadcast-test'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('BroadcastTest'));
});

test('the send endpoint broadcasts a message to everyone', function () {
    Event::fake();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('broadcast-test.send'), [
            'message' => 'Bonjour tout le monde !',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Message envoyé !');

    Event::assertDispatched(MessageATous::class, fn (MessageATous $event) => $event->message === 'Bonjour tout le monde !');
});

test('the send endpoint requires a message', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('broadcast-test.send'), [
            'message' => '',
        ]);

    $response->assertSessionHasErrors('message');
});

test('the send-private endpoint broadcasts a message on the private channel', function () {
    Event::fake();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('broadcast-test.send-private'), [
            'message' => 'Message privé !',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Message privé envoyé !');

    Event::assertDispatched(MessagePrive::class, fn (MessagePrive $event) => $event->userId === $user->id && $event->message === 'Message privé !');
});

test('the private channel authorizes the channel owner only', function () {
    config()->set('broadcasting.default', 'pusher');
    config()->set('broadcasting.connections.pusher.app_id', 'test-app');
    config()->set('broadcasting.connections.pusher.key', 'test-key');
    config()->set('broadcasting.connections.pusher.secret', 'test-secret');

    Broadcast::channel('user.{id}', function (User $user, int $id) {
        return (int) $user->id === $id;
    });

    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $response = $this->actingAs($user)->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "user.{$user->id}",
    ]);

    $response->assertOk();

    $denied = $this->actingAs($otherUser)->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "user.{$user->id}",
    ]);

    $denied->assertForbidden();
});
