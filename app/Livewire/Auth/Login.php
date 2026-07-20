<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.auth')]
class Login extends Component
{
    #[Validate('required|email', message: [
        'required' => 'Email wajib diisi.',
        'email' => 'Format email tidak valid.',
    ])]
    public string $email = '';

    #[Validate('required|string', message: [
        'required' => 'Kata sandi wajib diisi.',
    ])]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->addError('email', 'Email atau kata sandi yang Anda masukkan tidak sesuai.');

            return;
        }

        session()->regenerate();
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
