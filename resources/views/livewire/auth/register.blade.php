<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
    <h2 class="text-xl font-semibold text-gray-900 mb-6">Create an account</h2>

    <form wire:submit="register" class="space-y-4">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <input
                wire:model="name"
                id="name"
                type="text"
                autocomplete="name"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input
                wire:model="email"
                id="email"
                type="email"
                autocomplete="email"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
            @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input
                wire:model="password"
                id="password"
                type="password"
                autocomplete="new-password"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
            @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <input
                wire:model="password_confirmation"
                id="password_confirmation"
                type="password"
                autocomplete="new-password"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
        </div>

        <button
            type="submit"
            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition-colors"
        >
            Create account
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
        Already have an account?
        <a href="{{ route('login') }}" class="text-indigo-600 hover:underline font-medium">Sign in</a>
    </p>
</div>
