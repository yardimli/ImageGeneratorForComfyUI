<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->withCount([
                'prompts',
                'prompts as images_count' => fn ($query) => $query->whereNotNull('filename'),
                'stories',
                'dictionaryEntries',
            ])
            ->withMax('prompts as last_generation_at', 'created_at')
            ->orderBy('id')
            ->paginate(25);

        $totals = [
            'users' => User::count(),
            'admins' => User::where('is_admin', true)->count(),
            'images' => User::withCount(['prompts as images_count' => fn ($query) => $query->whereNotNull('filename')])->get()->sum('images_count'),
            'stories' => User::withCount('stories')->get()->sum('stories_count'),
        ];

        return view('admin.users.index', compact('users', 'totals'));
    }

    public function impersonate(User $user): RedirectResponse
    {
        if ($user->is(Auth::user())) {
            return back()->with('status', 'You are already signed in as that user.');
        }

        session()->put('impersonator_id', Auth::id());
        Auth::login($user);
        request()->session()->regenerate();

        return redirect()->route('home')->with('status', "You are now viewing DreamCover as {$user->name}.");
    }

    public function stopImpersonating(): RedirectResponse
    {
        $adminId = session()->pull('impersonator_id');
        $admin = $adminId ? User::whereKey($adminId)->where('is_admin', true)->first() : null;
        abort_unless($admin, 403, 'The original administrator account is unavailable.');

        Auth::login($admin);
        request()->session()->regenerate();

        return redirect()->route('admin.users.index')->with('status', 'Returned to your administrator account.');
    }
}
