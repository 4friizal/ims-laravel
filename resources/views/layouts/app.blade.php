<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <title>{{ config('app.name', 'IMS') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="bg-gray-100 antialiased">
    <div class="flex min-h-screen">
        <aside class="w-64 shrink-0 bg-white border-r border-gray-200 flex flex-col">
            <div class="h-16 flex items-center px-6 border-b border-gray-200">
                <span class="font-semibold text-gray-900">{{ config('app.name', 'IMS') }}</span>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-1">
                <a
                    class="{{ request()->routeIs('dashboard') ? 'bg-gray-100 font-extrabold text-black' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} block rounded-md px-3 py-2 text-sm"
                    href="{{ route('dashboard') }}"
                >Dasbor</a>

                <p class="px-3 pt-4 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Inventaris</p>
                <a
                    class="{{ request()->routeIs('inventory.categories') ? 'bg-gray-100 font-extrabold text-black' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} block rounded-md px-3 py-2 text-sm"
                    href="{{ route('inventory.categories') }}"
                >Kategori</a>
                <a
                    class="{{ request()->routeIs('inventory.raw-materials') ? 'bg-gray-100 font-extrabold text-black' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} block rounded-md px-3 py-2 text-sm"
                    href="{{ route('inventory.raw-materials') }}"
                >Bahan Baku</a>
                <a
                    class="{{ request()->routeIs('inventory.stock') ? 'bg-gray-100 font-extrabold text-black' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} block rounded-md px-3 py-2 text-sm"
                    href="{{ route('inventory.stock') }}"
                >Stok Masuk & Keluar</a>
                <a
                    class="{{ request()->routeIs('inventory.menus') ? 'bg-gray-100 font-extrabold text-black' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} block rounded-md px-3 py-2 text-sm"
                    href="{{ route('inventory.menus') }}"
                >Menu</a>
                <a
                    class="{{ request()->routeIs('inventory.prediction') ? 'bg-gray-100 font-extrabold text-black' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} block rounded-md px-3 py-2 text-sm"
                    href="{{ route('inventory.prediction') }}"
                >Prediksi AI</a>

                @if (auth()->user()->isAdmin())
                    <p class="px-3 pt-4 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">Administrasi</p>
                    <a
                        class="{{ request()->routeIs('user-management') ? 'bg-gray-100 font-extrabold text-black' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }} block rounded-md px-3 py-2 text-sm"
                        href="{{ route('user-management') }}"
                    >Manajemen Pengguna</a>
                @endif
            </nav>

            <div class="border-t border-gray-200 p-4 space-y-3">
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-600 truncate">{{ auth()->user()->name }}</span>
                    <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-medium shrink-0">
                        {{ auth()->user()->role->label() }}
                    </span>
                </div>
                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >
                    @csrf
                    <button
                        class="text-sm text-gray-500 hover:text-gray-700 transition-colors"
                        type="submit"
                    >
                        Keluar
                    </button>
                </form>
            </div>
        </aside>

        <main class="flex-1 px-4 sm:px-6 lg:px-8 py-8">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>

</html>
