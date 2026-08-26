<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function socialiteUser(array $attributes = []): SocialiteUser
{
    $user = new SocialiteUser;

    $user->map([
        'id' => $attributes['id'] ?? 'github-12345',
        'nickname' => $attributes['nickname'] ?? 'johndoe',
        'name' => $attributes['name'] ?? 'John Doe',
        'email' => array_key_exists('email', $attributes) ? $attributes['email'] : 'john@example.com',
    ]);

    $user->setToken('fake-token');

    return $user;
}

test('guests are redirected to the github provider', function () {
    Socialite::fake('github', socialiteUser());

    $this->get(route('auth.provider.redirect', ['provider' => 'github']))
        ->assertRedirect('https://socialite.fake/github/authorize');
});

test('guests are redirected to the google provider', function () {
    Socialite::fake('google', socialiteUser());

    $this->get(route('auth.provider.redirect', ['provider' => 'google']))
        ->assertRedirect('https://socialite.fake/google/authorize');
});

test('unsupported providers return a 404', function () {
    Socialite::fake('twitter', socialiteUser());

    $this->get(route('auth.provider.redirect', ['provider' => 'twitter']))
        ->assertNotFound();

    $this->get(route('auth.provider.callback', ['provider' => 'twitter']))
        ->assertNotFound();
});

test('new users are created with a verified email and personal team on first login', function () {
    Socialite::fake('github', socialiteUser());

    $response = $this->get(route('auth.provider.callback', ['provider' => 'github']));

    $this->assertAuthenticated();

    $user = User::where('email', 'john@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->socialite_provider)->toBe('github')
        ->and($user->socialite_id)->toBe('github-12345')
        ->and($user->password)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->personalTeam()->is_personal)->toBeTrue();

    $response->assertRedirect(route('chat'));
});

test('existing users are linked to the provider when the email matches', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
    ]);

    Socialite::fake('github', socialiteUser());

    $this->get(route('auth.provider.callback', ['provider' => 'github']))
        ->assertRedirect(route('chat'));

    $this->assertAuthenticatedAs($user);

    $user->refresh();

    expect($user->socialite_provider)->toBe('github')
        ->and($user->socialite_id)->toBe('github-12345');

    expect(User::where('email', 'john@example.com')->count())->toBe(1);
});

test('returning social users are authenticated without creating duplicates', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'socialite_provider' => 'github',
        'socialite_id' => 'github-12345',
        'password' => null,
    ]);

    Socialite::fake('github', socialiteUser());

    $this->get(route('auth.provider.callback', ['provider' => 'github']))
        ->assertRedirect(route('chat'));

    $this->assertAuthenticatedAs($user);

    expect(User::count())->toBe(1);
});

test('users without an email from the provider get a fallback email', function () {
    Socialite::fake('github', socialiteUser(['email' => null]));

    $this->get(route('auth.provider.callback', ['provider' => 'github']));

    $user = User::where('socialite_id', 'github-12345')->first();

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('github.github-12345@example.com');

    $this->assertAuthenticatedAs($user);
});
