<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Teams\CreateTeam;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\HttpFoundation\Response;

class OAuthController extends Controller
{
    /**
     * The supported socialite providers.
     */
    protected const PROVIDERS = ['github', 'google'];

    public function __construct(private CreateTeam $createTeam)
    {
        //
    }

    /**
     * Redirect the user to the provider's authentication page.
     */
    public function redirect(string $provider): Response
    {
        $this->ensureSupportedProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the provider callback and authenticate the user.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->ensureSupportedProvider($provider);

        $socialiteUser = Socialite::driver($provider)->user();

        $user = DB::transaction(fn () => $this->findOrCreateUser($provider, $socialiteUser));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('chat'));
    }

    /**
     * Find an existing user by provider or email, or create a new one.
     */
    private function findOrCreateUser(string $provider, SocialiteUser $socialiteUser): User
    {
        $email = strtolower(trim((string) $socialiteUser->getEmail()));

        $user = User::query()
            ->where('socialite_provider', $provider)
            ->where('socialite_id', $socialiteUser->getId())
            ->first();

        if ($user) {
            return $user;
        }

        $user = $email !== ''
            ? User::query()->where('email', $email)->first()
            : null;

        if ($user) {
            $user->forceFill([
                'socialite_provider' => $provider,
                'socialite_id' => $socialiteUser->getId(),
            ])->save();

            return $user;
        }

        $user = User::create([
            'name' => $this->resolveName($socialiteUser),
            'email' => $email !== '' ? $email : $provider.'.'.$socialiteUser->getId().'@example.com',
            'password' => null,
            'socialite_provider' => $provider,
            'socialite_id' => $socialiteUser->getId(),
            'email_verified_at' => now(),
        ]);

        $this->createTeam->handle($user, $user->name."'s Team", isPersonal: true);

        return $user;
    }

    /**
     * Resolve a display name from the socialite user.
     */
    private function resolveName(SocialiteUser $socialiteUser): string
    {
        return (string) ($socialiteUser->getName()
            ?? $socialiteUser->getNickname()
            ?? $socialiteUser->getEmail()
            ?? 'Utilisateur');
    }

    /**
     * Ensure the given provider is supported.
     */
    private function ensureSupportedProvider(string $provider): void
    {
        abort_unless(in_array($provider, self::PROVIDERS, true), 404);
    }
}
