<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h1 class="text-xl font-semibold text-gray-900 mb-1">Dashboard</h1>
        <p class="text-sm text-gray-500">Welcome back, <span class="font-medium text-gray-700">{{ auth()->user()->name }}</span>!</p>

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-indigo-50 rounded-lg p-4 border border-indigo-100">
                <p class="text-xs font-medium text-indigo-500 uppercase tracking-wider">Your Role</p>
                <p class="text-lg font-semibold text-indigo-900 mt-1">{{ auth()->user()->role->label() }}</p>
            </div>
        </div>

        @if(auth()->user()->isAdmin())
            <div class="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                <p class="text-sm font-medium text-amber-800">
                    Administrator — You have full access to all features including user management.
                </p>
            </div>
        @elseif(auth()->user()->isManager())
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm font-medium text-blue-800">
                    Manager — You can manage inventory and view reports.
                </p>
            </div>
        @else
            <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                <p class="text-sm font-medium text-gray-700">
                    Staff — You have access to standard inventory operations.
                </p>
            </div>
        @endif
    </div>
</div>
