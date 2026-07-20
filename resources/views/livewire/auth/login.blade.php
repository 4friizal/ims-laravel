<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
    <h2 class="text-xl font-semibold text-gray-900 mb-6">Masuk ke akun Anda</h2>

    <form
        class="space-y-4"
        wire:submit="login"
    >
        <div>
            <label
                class="block text-sm font-medium text-gray-700 mb-1"
                for="email"
            >Email</label>
            <input
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                id="email"
                wire:model="email"
                type="email"
                autocomplete="email"
            />
            @error('email')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label
                class="block text-sm font-medium text-gray-700 mb-1"
                for="password"
            >Kata Sandi</label>
            <input
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                id="password"
                wire:model="password"
                type="password"
                autocomplete="current-password"
            />
            @error('password')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center">
            <input
                class="rounded border-gray-300 text-indigo-600"
                id="remember"
                wire:model="remember"
                type="checkbox"
            >
            <label
                class="ml-2 text-sm text-gray-600"
                for="remember"
            >Ingat saya</label>
        </div>

        <button
            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-lg text-sm transition-colors"
            type="submit"
        >
            Masuk
        </button>
    </form>

</div>
