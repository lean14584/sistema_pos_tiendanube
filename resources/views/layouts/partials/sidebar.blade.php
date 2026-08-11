@php
    use App\Enums\Role;
    use App\Models\Message;
    use App\Models\Product;
    use App\Models\Task;
    use App\Support\Permissions;

    $lowStockCount = Product::lowStockCountCached();
    $unreadMessagesCount = auth()->check() ? Message::unreadFor(auth()->id())->count() : 0;
    $openTasksCount = 0;
    if (auth()->check()) {
        $openTasksQuery = Task::whereIn('status', ['pendiente', 'en_progreso']);
        $openTasksCount = auth()->user()->role === Role::Admin
            ? $openTasksQuery->count()
            : $openTasksQuery->where('assigned_to', auth()->id())->count();
    }

    $navItems = [
        ['module' => 'dashboard', 'pattern' => 'dashboard', 'href' => route('dashboard'), 'label' => 'Dashboard', 'icon' => 'home'],
        ['module' => 'quotes', 'pattern' => 'quotes.*', 'href' => route('quotes.index'), 'label' => 'Presupuestos', 'icon' => 'clipboard-document-list'],
        ['module' => 'invoices', 'pattern' => 'invoices.*', 'href' => route('invoices.index'), 'label' => 'Facturas', 'icon' => 'document-text'],
        ['module' => 'clients', 'pattern' => 'clients.*', 'href' => route('clients.index'), 'label' => 'Clientes', 'icon' => 'users'],
        ['module' => 'products', 'pattern' => 'products.*', 'href' => route('products.index'), 'label' => 'Productos', 'icon' => 'cube', 'badge' => $lowStockCount],
        ['module' => 'categories', 'pattern' => 'categories.*', 'href' => route('categories.index'), 'label' => 'Categorías', 'icon' => 'tag'],
        ['module' => 'providers', 'pattern' => 'providers.*', 'href' => route('providers.index'), 'label' => 'Proveedores', 'icon' => 'truck'],
        ['module' => 'purchases', 'pattern' => 'purchases.*', 'href' => route('purchases.index'), 'label' => 'Compras', 'icon' => 'shopping-cart'],
        ['module' => 'cash-register', 'pattern' => 'cash-register.*', 'href' => route('cash-register.index'), 'label' => 'Caja', 'icon' => 'banknotes'],
        ['module' => 'reports', 'pattern' => 'reports.*', 'href' => route('reports.index'), 'label' => 'Informes', 'icon' => 'chart-bar'],
        ['module' => 'users', 'pattern' => 'users.*', 'href' => route('users.index'), 'label' => 'Usuarios', 'icon' => 'shield-check'],
        ['module' => 'messages', 'pattern' => 'messages.*', 'href' => route('messages.index'), 'label' => 'Mensajes', 'icon' => 'chat-bubble-left-right', 'badge' => $unreadMessagesCount],
        ['module' => 'tasks', 'pattern' => 'tasks.*', 'href' => route('tasks.index'), 'label' => 'Tareas', 'icon' => 'check-circle', 'badge' => $openTasksCount],
        ['module' => 'company-settings', 'pattern' => 'company-settings.*', 'href' => route('company-settings.edit'), 'label' => 'Datos de la empresa', 'icon' => 'building-office'],
        ['module' => 'audit', 'pattern' => 'audit.*', 'href' => route('audit.index'), 'label' => 'Auditoría', 'icon' => 'clipboard-document-check'],
        ['module' => 'libro-iva', 'pattern' => 'libro-iva.*', 'href' => route('libro-iva.index'), 'label' => 'Libro IVA', 'icon' => 'receipt-percent'],
    ];

    $user = auth()->user();
    $visibleItems = $user
        ? array_filter($navItems, fn ($item) => Permissions::canAccess($user->role, $item['module']))
        : [];
@endphp

<div
    x-show="sidebarOpen"
    x-cloak
    x-on:click="sidebarOpen = false"
    x-transition.opacity
    class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"
></div>

<aside
    x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed inset-y-0 left-0 z-40 w-64 shrink-0 border-r border-gray-200 bg-gradient-to-b from-white to-gray-50/50 dark:border-gray-800 dark:from-gray-900 dark:to-gray-900/80 flex flex-col transform transition-transform duration-200 ease-in-out lg:static lg:translate-x-0"
>
    <div class="h-16 flex items-center gap-2.5 px-6 border-b border-gray-200 dark:border-gray-800">
        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center shadow-sm shadow-indigo-600/30">
            <x-heroicon-o-receipt-percent class="w-4 h-4 text-white" />
        </div>
        <span class="font-semibold text-gray-900 dark:text-gray-100 text-lg tracking-tight flex-1">{{ config('app.name') }}</span>
        <button
            type="button"
            x-on:click="sidebarOpen = false"
            aria-label="Cerrar menú"
            class="lg:hidden p-1 -mr-1 rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800"
        >
            <x-heroicon-o-x-mark class="w-5 h-5" />
        </button>
    </div>

    <nav x-on:click="sidebarOpen = false" class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
        @foreach ($visibleItems as $item)
            @php $active = request()->routeIs($item['pattern']); @endphp
            <a
                href="{{ $item['href'] }}"
                wire:navigate
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-all {{ $active
                    ? 'bg-indigo-50 text-indigo-700 shadow-sm ring-1 ring-inset ring-indigo-600/10 dark:bg-indigo-500/10 dark:text-indigo-400 dark:ring-indigo-400/10'
                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100' }}"
            >
                <x-dynamic-component :component="'heroicon-o-' . $item['icon']" class="w-4 h-4" />
                <span class="flex-1">{{ $item['label'] }}</span>
                @if (! empty($item['badge']))
                    <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full bg-red-500 text-white text-[11px] font-semibold">
                        {{ $item['badge'] }}
                    </span>
                @endif
            </a>
        @endforeach
    </nav>

    @if ($user)
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-800">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 shrink-0 rounded-full bg-indigo-100 dark:bg-indigo-500/15 text-indigo-700 dark:text-indigo-400 flex items-center justify-center text-xs font-semibold">
                        {{ strtoupper(mb_substr($user->name, 0, 2)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $user->name }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $user->role->label() }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Cerrar sesión" class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200 hover:scale-110 transition-all">
                        <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4" />
                    </button>
                </form>
            </div>
            <div
                x-data="{
                    isDark: document.documentElement.classList.contains('dark'),
                    toggle() {
                        this.isDark = !this.isDark;
                        document.documentElement.classList.toggle('dark', this.isDark);
                        localStorage.setItem('theme', this.isDark ? 'dark' : 'light');
                    }
                }"
                class="flex items-center justify-between px-2"
            >
                <span class="text-xs text-gray-400 dark:text-gray-500">Tema oscuro</span>
                <button
                    type="button"
                    role="switch"
                    x-bind:aria-checked="isDark"
                    aria-label="Cambiar entre tema claro y oscuro"
                    x-on:click="toggle()"
                    class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full bg-gray-200 transition-colors dark:bg-indigo-600"
                >
                    <span
                        class="flex h-4 w-4 items-center justify-center rounded-full bg-white shadow transition-transform"
                        x-bind:class="isDark ? 'translate-x-6' : 'translate-x-1'"
                    ></span>
                </button>
            </div>
        </div>
    @endif
</aside>
