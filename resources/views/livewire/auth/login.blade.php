<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
    <h2 class="text-xl font-semibold text-gray-900 mb-6">Sign in to your account</h2>

    <form wire:submit="login" class="space-y-4">
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
                autocomplete="current-password"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
            @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center">
            <input wire:model="remember" id="remember" type="checkbox" class="rounded border-gray-300 text-indigo-600">
            <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
        </div>

        <button
            type="submit"
            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition-colors"
        >
            Sign in
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
        Don't have an account?
        <a href="{{ route('register') }}" class="text-indigo-600 hover:underline font-medium">Register</a>
    </p>
</div>
