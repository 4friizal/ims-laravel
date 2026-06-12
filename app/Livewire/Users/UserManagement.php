<?php

namespace App\Livewire\Users;

use App\Enums\Role;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class UserManagement extends Component
{
    use WithPagination;

    public string $search = '';

    // Modal state
    public bool $showFormModal = false;
    public bool $showDeleteModal = false;

    // Form fields
    public ?int $editingId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $passwordConfirmation = '';
    public string $role = '';

    // Delete target
    public ?int $deletingId = null;
    public string $deletingName = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showFormModal = true;
    }

    public function openEdit(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->passwordConfirmation = '';
        $this->role = '';
        $this->resetErrorBag();
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $isCreating = $this->editingId === null;

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email' . ($isCreating ? '' : ',' . $this->editingId),
            'role' => 'required|in:admin,operator',
        ];

        if ($isCreating || filled($this->password)) {
            $rules['password'] = 'required|string|min:8|same:passwordConfirmation';
            $rules['passwordConfirmation'] = 'required|string';
        }

        $this->validate($rules, [
            'name.required' => 'Name is required.',
            'email.required' => 'Email is required.',
            'email.unique' => 'This email is already taken.',
            'role.required' => 'Role is required.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.same' => 'Passwords do not match.',
            'passwordConfirmation.required' => 'Please confirm the password.',
        ]);

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
        ];

        if ($isCreating || filled($this->password)) {
            $data['password'] = $this->password;
        }

        if ($isCreating) {
            User::create($data);
            session()->flash('success', 'User created successfully.');
        } else {
            User::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'User updated successfully.');
        }

        $this->showFormModal = false;
        $this->resetForm();
    }

    public function openDelete(int $userId): void
    {
        if ($userId === auth()->id()) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }

        $user = User::findOrFail($userId);
        $this->deletingId = $user->id;
        $this->deletingName = $user->name;
        $this->showDeleteModal = true;
    }

    public function confirmDelete(): void
    {
        if ($this->deletingId === auth()->id()) {
            session()->flash('error', 'You cannot delete your own account.');
            $this->showDeleteModal = false;
            return;
        }

        User::findOrFail($this->deletingId)->delete();
        session()->flash('success', 'User deleted successfully.');
        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->deletingName = '';
    }

    public function closeModals(): void
    {
        $this->showFormModal = false;
        $this->showDeleteModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->passwordConfirmation = '';
        $this->role = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        $users = User::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.users.user-management', [
            'users' => $users,
            'roles' => Role::cases(),
        ]);
    }
}