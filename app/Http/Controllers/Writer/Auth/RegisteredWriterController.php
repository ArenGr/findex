<?php

namespace App\Http\Controllers\Writer\Auth;

use App\Enums\UserRole;
use App\Filament\Resources\Writers\WriterResource;
use App\Http\Controllers\Concerns\GeneratesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Writer;
use App\Services\AdminNotifier;
use App\Support\ValidationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredWriterController extends Controller
{
    use GeneratesUniqueSlug;

    public function create(): View
    {
        return view('writer.auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', ValidationRules::email(), 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'expertise' => ['nullable', 'string', 'max:2000'],
            'topics' => ['nullable', 'string', 'max:500'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $writer = Writer::create([
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name'], Writer::class),
                'expertise' => $validated['expertise'] ?? null,
                'topics' => $validated['topics'] ?? null,
                'is_active' => false,
            ]);

            $user = new User([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);
            $user->forceFill([
                'role' => UserRole::WRITER,
                'writer_id' => $writer->id,
            ])->save();

            AdminNotifier::pendingApproval(
                title: 'New writer awaiting approval',
                body: "{$writer->name} just registered and is inactive until approved.",
                icon: 'heroicon-o-pencil-square',
                reviewUrl: WriterResource::getUrl('edit', ['record' => $writer->getKey()]),
            );

            return $user;
        });

        $user->sendEmailVerificationNotification();

        Auth::guard('writer')->login($user);

        $request->session()->regenerate();

        return redirect()->route('writer.dashboard.index');
    }
}
