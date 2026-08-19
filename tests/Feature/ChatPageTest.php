<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Jobs\BroadcastAgent;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('chat'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the chat page', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('chat'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Chat')
        ->has('conversations')
        ->has('messages')
        ->where('activeConversationId', null));
});

test('the chat page shows the latest conversation by default', function () {
    $user = User::factory()->create();

    $conversation = Conversation::create([
        'id' => (string) \Illuminate\Support\Str::uuid7(),
        'user_id' => $user->id,
        'title' => 'Ma conversation',
    ]);

    ConversationMessage::create([
        'id' => (string) \Illuminate\Support\Str::uuid7(),
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'agent' => \App\Ai\Agents\ChatBot::class,
        'role' => 'user',
        'content' => 'Bonjour !',
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '[]',
        'meta' => '[]',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('chat'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('activeConversationId', $conversation->id)
        ->has('conversations', 1)
        ->where('conversations.0.title', 'Ma conversation')
        ->has('messages', 1)
        ->where('messages.0.content', 'Bonjour !'));
});

test('a user cannot open another user conversation', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $conversation = Conversation::create([
        'id' => (string) \Illuminate\Support\Str::uuid7(),
        'user_id' => $owner->id,
        'title' => 'Privée',
    ]);

    $this
        ->actingAs($other)
        ->get(route('chat', ['conversation' => $conversation->id]))
        ->assertNotFound();
});

test('sending a message queues the chatbot on the private channel', function () {
    Queue::fake();

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('chat.messages.store'), [
            'message' => 'Salut Marxtinus !',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('agent_conversations', [
        'user_id' => $user->id,
    ]);

    Queue::assertPushed(
        BroadcastAgent::class,
        fn (BroadcastAgent $job) => $job->prompt === 'Salut Marxtinus !'
            && 'private-user.'.$user->id === $job->channels[0]->name,
    );
});

test('sending a message continues an existing conversation', function () {
    Queue::fake();

    $user = User::factory()->create();

    $conversation = Conversation::create([
        'id' => (string) \Illuminate\Support\Str::uuid7(),
        'user_id' => $user->id,
        'title' => 'Ma conversation',
    ]);

    $this
        ->actingAs($user)
        ->post(route('chat.messages.store'), [
            'message' => 'Encore un message',
            'conversation_id' => $conversation->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('agent_conversations', 1);
    $this->assertDatabaseHas('agent_conversations', ['id' => $conversation->id]);

    Queue::assertPushed(
        BroadcastAgent::class,
        fn (BroadcastAgent $job) => $job->prompt === 'Encore un message'
            && 'private-user.'.$user->id === $job->channels[0]->name,
    );
});

test('sending a message with a fresh conversation id creates the conversation', function () {
    Queue::fake();

    $user = User::factory()->create();
    $freshConversationId = (string) \Illuminate\Support\Str::uuid();

    $this
        ->actingAs($user)
        ->post(route('chat.messages.store'), [
            'message' => 'Première question !',
            'conversation_id' => $freshConversationId,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('agent_conversations', [
        'id' => $freshConversationId,
        'user_id' => $user->id,
        'title' => 'Première question !',
    ]);

    Queue::assertPushed(
        BroadcastAgent::class,
        fn (BroadcastAgent $job) => $job->prompt === 'Première question !'
            && 'private-user.'.$user->id === $job->channels[0]->name,
    );
});

test('a user cannot send a message to another user conversation', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $other = User::factory()->create();

    $conversation = Conversation::create([
        'id' => (string) \Illuminate\Support\Str::uuid7(),
        'user_id' => $owner->id,
        'title' => 'Privée',
    ]);

    $this
        ->actingAs($other)
        ->post(route('chat.messages.store'), [
            'message' => 'Je piratise !',
            'conversation_id' => $conversation->id,
        ])
        ->assertNotFound();

    Queue::assertNothingPushed();
});

test('sending a message requires a message', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('chat.messages.store'), [
            'message' => '',
        ])
        ->assertSessionHasErrors('message');

    Queue::assertNothingPushed();
});
