@php
    $svgPath = public_path($client_company->logo_svg_path);
    $logoSvg = '';

    if (file_exists($svgPath) && is_readable($svgPath)) {
        $logoSvg = file_get_contents($svgPath);
    }
@endphp

<!-- Logout Modal -->
<div id="logoutModal"
    class="hidden fixed inset-0 z-[99] flex items-center justify-center bg-[var(--overlay-color)] text-xs md:text-sm fade-in">
    <!-- Modal Content -->
    <div class="bg-[var(--secondary-bg-color)] rounded-xl shadow-lg w-80 md:w-full md:max-w-lg p-6 relative">
        <!-- Close Button -->
        <button onclick="closeLogoutModal()"
            class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-all duration-300 ease-in-out cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Modal Body -->
        <div class="modal_body flex items-start">
            <div class="w-1/3 h-1/3 md:w-1/5 md:h-1/5">
                <img src="{{ asset('images/error_icon.png') }}" alt="" class="w-full h-full object-cover">
            </div>
            <div class="content ml-5">
                <h2 class="text-lg md:text-xl font-semibold text-[var(--text-color)]">Logout Account</h2>
                <p class="text-[var(--secondary-text)] mt-1 mb-4 md:mt-2 md:mb-6">Are you sure you want to logout? All of your data will be permanently removed. This action cannot be undone.</p>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex justify-end space-x-3">
            <!-- Cancel Button -->
            <button onclick="closeLogoutModal()"
                class="px-4 py-2 bg-[var(--secondary-bg-color)] border text-[var(--secondary-text)] rounded-md hover:bg-[var(--bg-color)] transition-all duration-300 ease-in-out cursor-pointer">Cancel</button>

            <!-- Logout Form -->
            <form id="logoutForm" method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="px-4 py-2 bg-[var(--danger-color)] text-white rounded-md hover:bg-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">Logout</button>
            </form>
        </div>
    </div>
</div>
<div class="relative w-full md:w-auto md:z-40">
    <aside
        class="bg-[var(--secondary-bg-color)] w-full md:w-16 flex justify-between md:flex-col items-center px-5 py-3 md:px-0 md:py-3 h-full md:h-screen transition-all duration-300 ease-in-out fade-in relative z-40">
        <!-- Logo -->
        <a href="/"
            class="text-[var(--text-color)] p-3 w-14 h-14 flex items-center justify-center group cursor-normal relative">
            {!! $logoSvg !!}
        </a>

        <!-- Mobile Menu Toggle Button -->
        <button id="menuToggle" type="button"
            class="md:hidden flex items-center p-2 text-[var(--text-color)] cursor-pointer">
            <i class="fas fa-bars text-xl transition-all 0.5s ease-in-out"></i>
        </button>

        <!-- Navigation Menu -->
        <nav class="space-y-4 hidden md:flex flex-col my-auto">
            <div class="relative group">
                <x-nav-link-item label="Home"
                {{-- icon="fas fa-home" --}}
                svgIcon='
                <svg class="fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1309.5 1406.28"><path d="M731.41-154.73,379.2-411.07c-80-48.47-177.61-48.47-257.65,0l-352.2,256.34C-337.48-93.92-404.37,26-404.37,156.59V679.77c0,154.14,115.44,279.08,257.85,279.08H647.27c142.41,0,257.86-124.94,257.86-279.08V156.59C905.13,26,838.23-93.92,731.41-154.73ZM462.16,709.05H38.59c-47.35,0-85.88-41.7-85.88-92.95s38.53-93,85.88-93H462.16c47.36,0,85.88,41.69,85.88,93S509.52,709.05,462.16,709.05Z" transform="translate(404.37 447.42)"/></svg>
                '
                href="/" />
            </div>

            <div id="customMenuShortcuts" class="flex flex-col space-y-4">

            </div>

            <div class="relative group">
                <x-nav-link-item label="Menu" icon="fas fa-bars" onclick="generateMenuModal()" />
            </div>
        </nav>

        <div class="relative hidden md:flex group md:pt-3 md:ml-0 md:mt-4 dropdown-trigger">
            <!-- User Avatar -->
            <button type="button" onclick="openDropDown(event, this)"
                class="w-10 h-10 ml-1.5 mb-1 flex items-center justify-center rounded-[41.5%] cursor-pointer transition-all duration-300 ease-in-out text-[var(--text-color)] font-semibold text-lg overflow-hidden">
                @if (Auth::user()->profile_picture == 'default_avatar.png')
                    <img src="{{ asset('images/default_avatar.png') }}" class="w-full h-full object-cover"
                        alt="Avatar">
                @else
                    <img src="{{ asset('storage/uploads/images/' . auth()->user()->profile_picture) }}"
                        class="w-full h-full object-cover" alt="Avatar">
                @endif
                <span
                    class="absolute shadow-xl capitalize text-nowrap left-18 bottom-1.5 bg-[var(--h-secondary-bg-color)] text-[var(--text-color)] border border-gray-600 text-sm rounded-lg px-2 py-1 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
                    {{ Auth::user()->name }}
                </span>
            </button>

            <!-- Dropdown Menu -->
            <div
                class="dropdownMenu text-sm absolute bottom-0 left-16 hidden border border-gray-600 w-48 bg-[var(--h-secondary-bg-color)] text-[var(--text-color)] shadow-lg rounded-2xl opacity-0 transform scale-95 transition-all duration-300 ease-in-out z-50">
                <ul class="p-2">
                    @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
                        <!-- Setups -->
                        <li>
                            <a href="{{ route('setups.index') }}"
                                class="block px-4 py-2 hover:bg-[var(--h-bg-color)] rounded-lg transition-all duration-200 ease-in-out cursor-pointer">
                                <i class="fas fa-cog text-[var(--secondary-color)] mr-3"></i>
                                Setups
                            </a>
                        </li>
                    @endif
                    @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
                        <!-- rates -->
                        <li>
                            <a href="{{ route('rates.index') }}"
                                class="block px-4 py-2 hover:bg-[var(--h-bg-color)] rounded-lg transition-all duration-200 ease-in-out cursor-pointer">
                                <i class="fas fa-cog text-[var(--secondary-color)] mr-3"></i>
                                Rates
                            </a>
                        </li>
                    @endif
                    @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin']))
                        <!-- rates -->
                        <li>
                            <button id="backupDB" onclick="backupDB()"
                                class="block w-full text-left px-4 py-2 hover:bg-[var(--h-bg-color)] rounded-lg transition-all duration-200 ease-in-out cursor-pointer">
                                <i class="fas fa-database text-[var(--secondary-color)] mr-3"></i>
                                Backup DB
                            </button>
                        </li>
                    @endif
                    <!-- Theme Toggle -->
                    <li>
                        <button id="themeToggle"
                            class="block w-full text-left px-4 py-2 hover:bg-[var(--h-bg-color)] rounded-lg transition-all duration-200 ease-in-out cursor-pointer">
                            <i class="fas fa-moon text-[var(--secondary-color)] mr-3"></i>
                            Theme
                        </button>
                    </li>
                    <!-- Logout Button -->
                    <li>
                        <button onclick="openLogoutModal()"
                            class="block w-full text-left px-4 py-2 text-[var(--border-error)] hover:bg-[var(--bg-error)] hover:text-[var(--text-error)] rounded-lg transition-all duration-200 ease-in-out cursor-pointer">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            Logout
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </aside>
    {{-- mobile menu --}}
    <div id="mobileMenuOverlay"
        class="mobileMenuOverlay w-screen h-screen bg-[var(--overlay-color)] opacity-zero opacity-transition pointer-events-none fixed z-30">
        <div id="mobileMenu"
            class="fixed md:hidden w-full bg-[var(--secondary-bg-color)] z-30 flex flex-col items-start justify-start p-4 space-y-4 transform -translate-y-full transition-all 0.5s ease-in-out">
            <!-- Main Menu Items -->
            <div class="flex flex-col space-y-2 w-full">
                <x-mobile-menu-item href="/" title="Home" active="{{ request()->is('home') }}" />

                <x-mobile-menu-item title="Users" includesDropdown :dropdown="[
                    ['href' => route('users.index'), 'title' => 'Show Users'],
                    ['href' => route('users.create'), 'title' => 'Add User'],
                ]" />

                <x-mobile-menu-item title="Suppliers" includesDropdown :dropdown="[
                    ['href' => route('suppliers.index'), 'title' => 'Show Suppliers'],
                    ['href' => route('suppliers.create'), 'title' => 'Add Supplier'],
                ]" />

                <x-mobile-menu-item title="Customer" includesDropdown :dropdown="[
                    ['href' => route('customers.index'), 'title' => 'Show Customers'],
                    ['href' => route('customers.create'), 'title' => 'Add Customer'],
                ]" />

                <x-mobile-menu-item title="Articles" includesDropdown :dropdown="[
                    ['href' => route('articles.index'), 'title' => 'Show Articles'],
                    ['href' => route('articles.create'), 'title' => 'Add Article'],
                ]" />

                <x-mobile-menu-item title="Orders" includesDropdown :dropdown="[
                    ['href' => route('orders.index'), 'title' => 'Show Orders'],
                    ['href' => route('payment-programs.index'), 'title' => 'Show Payment Prg.'],
                    ['href' => route('orders.create'), 'title' => 'Generate Order'],
                    ['href' => route('payment-programs.create'), 'title' => 'Add Payment Prg.'],
                ]" />
            </div>

            <!-- Divider -->
            <div class="border-t border-gray-600 w-full my-4"></div>

            <!-- Profile Section -->
            <div class="flex items-center space-x-4 px-4">
                @if (Auth::user()->profile_picture == 'default_avatar.png')
                    <img src="{{ asset('images/default_avatar.png') }}" alt="Avatar"
                        class="w-10 h-10 rounded-[41.5%]">
                @else
                    <img src="{{ asset('storage/uploads/images/' . auth()->user()->profile_picture) }}" alt="Avatar"
                        class="w-10 h-10 rounded-[41.5%]">
                @endif
                <div>
                    <div class="text-[var(--text-color)] font-semibold capitalize">{{ Auth::user()->name }}</div>
                    <div class="text-gray-400 text-sm">username: {{ Auth::user()->username }}</div>
                </div>
            </div>

            <!-- Additional Links -->
            <div class="flex flex-col space-y-2 w-full mt-2">
                <x-mobile-menu-item href="{{ route('setups.index') }}" title="Setups"
                    active="{{ request()->is('setups') }}" />

                <x-mobile-menu-item title="Theme" asButton="true" id="themeToggleMobile" />

                <x-mobile-menu-item title="Logout" asButton="true" onclick="openLogoutModal()" />
            </div>
        </div>
    </div>
</div>
</div>
<script>
    const menuData = [
        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "users",
                name: "Users",
                details: {
                    '': 'Manage your users',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1343.97 1363.9"><circle cx="671.99" cy="300.42" r="300.42"/><path d="M715.46,931.61H-214.71c-163.37,0-262-180.66-173.85-318.17C-253.7,403.21-17.93,263.9,250.38,263.9S754.46,403.21,889.31,613.44C977.52,751,878.83,931.61,715.46,931.61Z" transform="translate(421.61 432.3)"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Users', href: "/users"},
                    {name: 'Add User', href: "/users/create"},
                ],
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "suppliers",
                name: "Suppliers",
                details: {
                    '': 'Manage your suppliers',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M32 160C32 124.7 60.7 96 96 96L384 96C419.3 96 448 124.7 448 160L448 192L498.7 192C515.7 192 532 198.7 544 210.7L589.3 256C601.3 268 608 284.3 608 301.3L608 448C608 483.3 579.3 512 544 512L540.7 512C530.3 548.9 496.3 576 456 576C415.7 576 381.8 548.9 371.3 512L268.7 512C258.3 548.9 224.3 576 184 576C143.7 576 109.8 548.9 99.3 512L96 512C60.7 512 32 483.3 32 448L32 160zM544 352L544 301.3L498.7 256L448 256L448 352L544 352zM224 488C224 465.9 206.1 448 184 448C161.9 448 144 465.9 144 488C144 510.1 161.9 528 184 528C206.1 528 224 510.1 224 488zM456 528C478.1 528 496 510.1 496 488C496 465.9 478.1 448 456 448C433.9 448 416 465.9 416 488C416 510.1 433.9 528 456 528z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Suppliers', href: "/suppliers"},
                    {name: 'Add Supplier', href: "/suppliers/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "vouchers",
                name: "Vouchers",
                details: {
                    '': 'Manage your vouchers',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M32 160C32 124.7 60.7 96 96 96L384 96C419.3 96 448 124.7 448 160L448 192L498.7 192C515.7 192 532 198.7 544 210.7L589.3 256C601.3 268 608 284.3 608 301.3L608 448C608 483.3 579.3 512 544 512L540.7 512C530.3 548.9 496.3 576 456 576C415.7 576 381.8 548.9 371.3 512L268.7 512C258.3 548.9 224.3 576 184 576C143.7 576 109.8 548.9 99.3 512L96 512C60.7 512 32 483.3 32 448L32 160zM544 352L544 301.3L498.7 256L448 256L448 352L544 352zM224 488C224 465.9 206.1 448 184 448C161.9 448 144 465.9 144 488C144 510.1 161.9 528 184 528C206.1 528 224 510.1 224 488zM456 528C478.1 528 496 510.1 496 488C496 465.9 478.1 448 456 448C433.9 448 416 465.9 416 488C416 510.1 433.9 528 456 528z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Vouchers', href: "/vouchers"},
                    {name: 'Add Voucher', href: "/vouchers/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "customers",
                name: "Customers",
                details: {
                    '': 'Manage your customers',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M256.1 72C322.4 72 376.1 125.7 376.1 192C376.1 258.3 322.4 312 256.1 312C189.8 312 136.1 258.3 136.1 192C136.1 125.7 189.8 72 256.1 72zM226.4 368L285.8 368C292.5 368 299 368.4 305.5 369.1C304.6 374 304.1 379 304.1 384.1L304.1 476.2C304.1 501.7 314.2 526.1 332.2 544.1L364.1 576L77.8 576C61.4 576 48.1 562.7 48.1 546.3C48.1 447.8 127.9 368 226.4 368zM352.1 476.2L352.1 384.1C352.1 366.4 366.4 352.1 384.1 352.1L476.2 352.1C488.9 352.1 501.1 357.2 510.1 366.2L606.1 462.2C624.8 480.9 624.8 511.3 606.1 530.1L530 606.2C511.3 624.9 480.9 624.9 462.1 606.2L366.1 510.2C357.1 501.2 352 489 352 476.3zM456.1 432C456.1 418.7 445.4 408 432.1 408C418.8 408 408.1 418.7 408.1 432C408.1 445.3 418.8 456 432.1 456C445.4 456 456.1 445.3 456.1 432z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Customers', href: "/customers"},
                    {name: 'Add Customer', href: "/customers/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "customer-payments",
                name: "Payments",
                details: {
                    '': 'Manage your payments',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M256.1 72C322.4 72 376.1 125.7 376.1 192C376.1 258.3 322.4 312 256.1 312C189.8 312 136.1 258.3 136.1 192C136.1 125.7 189.8 72 256.1 72zM226.4 368L285.8 368C292.5 368 299 368.4 305.5 369.1C304.6 374 304.1 379 304.1 384.1L304.1 476.2C304.1 501.7 314.2 526.1 332.2 544.1L364.1 576L77.8 576C61.4 576 48.1 562.7 48.1 546.3C48.1 447.8 127.9 368 226.4 368zM352.1 476.2L352.1 384.1C352.1 366.4 366.4 352.1 384.1 352.1L476.2 352.1C488.9 352.1 501.1 357.2 510.1 366.2L606.1 462.2C624.8 480.9 624.8 511.3 606.1 530.1L530 606.2C511.3 624.9 480.9 624.9 462.1 606.2L366.1 510.2C357.1 501.2 352 489 352 476.3zM456.1 432C456.1 418.7 445.4 408 432.1 408C418.8 408 408.1 418.7 408.1 432C408.1 445.3 418.8 456 432.1 456C445.4 456 456.1 445.3 456.1 432z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Payments', href: "/customer-payments"},
                    {name: 'Add Payment', href: "/customer-payments/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
            {
                id: "articles",
                name: "Articles",
                details: {
                    '': 'Manage your articles and content',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M320.2 176C364.4 176 400.2 140.2 400.2 96L453.7 96C470.7 96 487 102.7 499 114.7L617.6 233.4C630.1 245.9 630.1 266.2 617.6 278.7L566.9 329.4C554.4 341.9 534.1 341.9 521.6 329.4L480.2 288L480.2 512C480.2 547.3 451.5 576 416.2 576L224.2 576C188.9 576 160.2 547.3 160.2 512L160.2 288L118.8 329.4C106.3 341.9 86 341.9 73.5 329.4L22.9 278.6C10.4 266.1 10.4 245.8 22.9 233.3L141.5 114.7C153.5 102.7 169.8 96 186.8 96L240.3 96C240.3 140.2 276.1 176 320.3 176z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Articles', href: "/articles"},
                    {name: 'Add Article', href: "/articles/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
            {
                id: "physical-quantities",
                name: "Physical Quantities",
                details: {
                    '': 'Manage your physical quantity',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M320.2 176C364.4 176 400.2 140.2 400.2 96L453.7 96C470.7 96 487 102.7 499 114.7L617.6 233.4C630.1 245.9 630.1 266.2 617.6 278.7L566.9 329.4C554.4 341.9 534.1 341.9 521.6 329.4L480.2 288L480.2 512C480.2 547.3 451.5 576 416.2 576L224.2 576C188.9 576 160.2 547.3 160.2 512L160.2 288L118.8 329.4C106.3 341.9 86 341.9 73.5 329.4L22.9 278.6C10.4 266.1 10.4 245.8 22.9 233.3L141.5 114.7C153.5 102.7 169.8 96 186.8 96L240.3 96C240.3 140.2 276.1 176 320.3 176z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Physical Qty.', href: "/physical-quantities"},
                    {name: 'Add Physical Qty.', href: "/physical-quantities/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "orders",
                name: "Orders",
                details: {
                    '': 'Manage your orders',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M24 48C10.7 48 0 58.7 0 72C0 85.3 10.7 96 24 96L69.3 96C73.2 96 76.5 98.8 77.2 102.6L129.3 388.9C135.5 423.1 165.3 448 200.1 448L456 448C469.3 448 480 437.3 480 424C480 410.7 469.3 400 456 400L200.1 400C188.5 400 178.6 391.7 176.5 380.3L171.4 352L475 352C505.8 352 532.2 330.1 537.9 299.8L568.9 133.9C572.6 114.2 557.5 96 537.4 96L124.7 96L124.3 94C119.5 67.4 96.3 48 69.2 48L24 48zM208 576C234.5 576 256 554.5 256 528C256 501.5 234.5 480 208 480C181.5 480 160 501.5 160 528C160 554.5 181.5 576 208 576zM432 576C458.5 576 480 554.5 480 528C480 501.5 458.5 480 432 480C405.5 480 384 501.5 384 528C384 554.5 405.5 576 432 576z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Orders', href: "/orders"},
                    {name: 'Generate Order', href: "/orders/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "payment-programs",
                name: "Payment Programs",
                details: {
                    '': 'Manage your programs',
                },
                bottomChip: '3 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M24 48C10.7 48 0 58.7 0 72C0 85.3 10.7 96 24 96L69.3 96C73.2 96 76.5 98.8 77.2 102.6L129.3 388.9C135.5 423.1 165.3 448 200.1 448L456 448C469.3 448 480 437.3 480 424C480 410.7 469.3 400 456 400L200.1 400C188.5 400 178.6 391.7 176.5 380.3L171.4 352L475 352C505.8 352 532.2 330.1 537.9 299.8L568.9 133.9C572.6 114.2 557.5 96 537.4 96L124.7 96L124.3 94C119.5 67.4 96.3 48 69.2 48L24 48zM208 576C234.5 576 256 554.5 256 528C256 501.5 234.5 480 208 480C181.5 480 160 501.5 160 528C160 554.5 181.5 576 208 576zM432 576C458.5 576 480 554.5 480 528C480 501.5 458.5 480 432 480C405.5 480 384 501.5 384 528C384 554.5 405.5 576 432 576z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Program Summary', href: "/payment-programs/summary"},
                    {name: 'Show Programs', href: "/payment-programs"},
                    {name: 'Add Program', href: "/payment-programs/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "shipments",
                name: "Shipments",
                details: {
                    '': 'Manage your shipments',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M560.3 301.2C570.7 313 588.6 315.6 602.1 306.7C616.8 296.9 620.8 277 611 262.3L563 190.3C560.2 186.1 556.4 182.6 551.9 180.1L351.4 68.7C332.1 58 308.6 58 289.2 68.7L88.8 180C83.4 183 79.1 187.4 76.2 192.8L27.7 282.7C15.1 306.1 23.9 335.2 47.3 347.8L80.3 365.5L80.3 418.8C80.3 441.8 92.7 463.1 112.7 474.5L288.7 574.2C308.3 585.3 332.2 585.3 351.8 574.2L527.8 474.5C547.9 463.1 560.2 441.9 560.2 418.8L560.2 301.3zM320.3 291.4L170.2 208L320.3 124.6L470.4 208L320.3 291.4zM278.8 341.6L257.5 387.8L91.7 299L117.1 251.8L278.8 341.6z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Shipments', href: "/shipments"},
                    {name: 'Add Shipment', href: "/shipments/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "expenses",
                name: "Expenses",
                details: {
                    '': 'Manage your expenses',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 578 578" style="enable-background:new 0 0 578 578;" xml:space="preserve"><g> <path d="M523.7,308.6c-30.7,0-59.6,0-88.6,0c-36.9,0-51.8,11.3-56.3,47.9c-2.2,18.2-0.6,37.5,3.2,55.5 c4.9,23.3,20.7,33.5,44.5,33.7c31.8,0.2,63.6,0,95.1,0c0,24.6,2.4,49.1-0.7,72.8c-2.8,20.8-23.3,34.7-44.6,34.7 c-141.1,0.2-282.3,0.2-423.4,0c-26.2,0-47.6-21.4-47.7-47.6c-0.3-85.7-0.3-171.3,0-257c0.1-26.2,21.5-47.5,47.7-47.6 c141.1-0.2,282.3-0.2,423.4,0c24.2,0,45.5,18.9,47.1,42.9C524.9,265,523.7,286.2,523.7,308.6z" /> <path d="M90,181.2c58.4-51.1,114.1-99.9,169.9-148.6c9-7.9,19.3-10.5,30.5-5.2c11.3,5.3,17.7,14.3,18,26.8 c0.3,13.8,4.4,30.1-1.5,40.8c-5.4,9.8-21.7,13.9-33.4,19.9c-36.2,18.3-73.5,34.7-108.7,54.9C141.5,183.1,117.8,181,90,181.2z" /> <path d="M484.3,426.1c-19.1,0-38.3,0.1-57.4,0c-15.8-0.1-25.6-6.5-27.2-22.1c-1.8-18-1.8-36.5,0.2-54.4 c1.6-14.9,11.4-21.2,26.6-21.2c38.3-0.1,76.5-0.1,114.8,0c19.3,0.1,31.3,11.9,31.6,31c0.2,12.2,0.2,24.4,0,36.6 c-0.3,17.7-12.4,29.9-30,30.1C523.3,426.3,503.8,426.1,484.3,426.1z M456,416.3c21.1-0.3,38.6-18,38.6-39.1 c0-21.6-18.1-39.5-39.8-39.2c-21.6,0.3-39.2,18.8-38.6,40.3C416.9,399.4,434.9,416.7,456,416.3z" /> <path d="M395.4,181c-68.9,0-137.4,0-208.3,0c4.1-2.7,6.3-4.4,8.8-5.7c51.2-25.7,102.5-51.3,153.9-76.8c26.1-12.9,46.8,0,46.9,29 c0,16.7-0.1,33.4-0.2,50.1C396.5,178.4,396,179.2,395.4,181z" /> <path d="M455,396.7c-10.6-0.2-19.2-9-19.1-19.6c0.1-11.1,9.4-20,20.5-19.4c10.5,0.5,18.9,9.7,18.5,20.2 C474.6,388.4,465.6,396.9,455,396.7z" /></g></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Expenses', href: "/expenses"},
                    {name: 'Add Expense', href: "/expenses/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "invoices",
                name: "Invoices",
                details: {
                    '': 'Manage your invoices',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M142 66.2C150.5 62.3 160.5 63.7 167.6 69.8L208 104.4L248.4 69.8C257.4 62.1 270.7 62.1 279.6 69.8L320 104.4L360.4 69.8C369.4 62.1 382.6 62.1 391.6 69.8L432 104.4L472.4 69.8C479.5 63.7 489.5 62.3 498 66.2C506.5 70.1 512 78.6 512 88L512 552C512 561.4 506.5 569.9 498 573.8C489.5 577.7 479.5 576.3 472.4 570.2L432 535.6L391.6 570.2C382.6 577.9 369.4 577.9 360.4 570.2L320 535.6L279.6 570.2C270.6 577.9 257.3 577.9 248.4 570.2L208 535.6L167.6 570.2C160.5 576.3 150.5 577.7 142 573.8C133.5 569.9 128 561.4 128 552L128 88C128 78.6 133.5 70.1 142 66.2zM232 200C218.7 200 208 210.7 208 224C208 237.3 218.7 248 232 248L408 248C421.3 248 432 237.3 432 224C432 210.7 421.3 200 408 200L232 200zM208 416C208 429.3 218.7 440 232 440L408 440C421.3 440 432 429.3 432 416C432 402.7 421.3 392 408 392L232 392C218.7 392 208 402.7 208 416zM232 296C218.7 296 208 306.7 208 320C208 333.3 218.7 344 232 344L408 344C421.3 344 432 333.3 432 320C432 306.7 421.3 296 408 296L232 296z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Invoices', href: "/invoices"},
                    {name: 'Add Invoice', href: "/invoices/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "cargos",
                name: "Cargos",
                details: {
                    '': 'Manage your cargos',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M142 66.2C150.5 62.3 160.5 63.7 167.6 69.8L208 104.4L248.4 69.8C257.4 62.1 270.7 62.1 279.6 69.8L320 104.4L360.4 69.8C369.4 62.1 382.6 62.1 391.6 69.8L432 104.4L472.4 69.8C479.5 63.7 489.5 62.3 498 66.2C506.5 70.1 512 78.6 512 88L512 552C512 561.4 506.5 569.9 498 573.8C489.5 577.7 479.5 576.3 472.4 570.2L432 535.6L391.6 570.2C382.6 577.9 369.4 577.9 360.4 570.2L320 535.6L279.6 570.2C270.6 577.9 257.3 577.9 248.4 570.2L208 535.6L167.6 570.2C160.5 576.3 150.5 577.7 142 573.8C133.5 569.9 128 561.4 128 552L128 88C128 78.6 133.5 70.1 142 66.2zM232 200C218.7 200 208 210.7 208 224C208 237.3 218.7 248 232 248L408 248C421.3 248 432 237.3 432 224C432 210.7 421.3 200 408 200L232 200zM208 416C208 429.3 218.7 440 232 440L408 440C421.3 440 432 429.3 432 416C432 402.7 421.3 392 408 392L232 392C218.7 392 208 402.7 208 416zM232 296C218.7 296 208 306.7 208 320C208 333.3 218.7 344 232 344L408 344C421.3 344 432 333.3 432 320C432 306.7 421.3 296 408 296L232 296z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Cargos', href: "/cargos"},
                    {name: 'Add Cargo', href: "/cargos/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "bilties",
                name: "Bilties",
                details: {
                    '': 'Manage your bilties',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M142 66.2C150.5 62.3 160.5 63.7 167.6 69.8L208 104.4L248.4 69.8C257.4 62.1 270.7 62.1 279.6 69.8L320 104.4L360.4 69.8C369.4 62.1 382.6 62.1 391.6 69.8L432 104.4L472.4 69.8C479.5 63.7 489.5 62.3 498 66.2C506.5 70.1 512 78.6 512 88L512 552C512 561.4 506.5 569.9 498 573.8C489.5 577.7 479.5 576.3 472.4 570.2L432 535.6L391.6 570.2C382.6 577.9 369.4 577.9 360.4 570.2L320 535.6L279.6 570.2C270.6 577.9 257.3 577.9 248.4 570.2L208 535.6L167.6 570.2C160.5 576.3 150.5 577.7 142 573.8C133.5 569.9 128 561.4 128 552L128 88C128 78.6 133.5 70.1 142 66.2zM232 200C218.7 200 208 210.7 208 224C208 237.3 218.7 248 232 248L408 248C421.3 248 432 237.3 432 224C432 210.7 421.3 200 408 200L232 200zM208 416C208 429.3 218.7 440 232 440L408 440C421.3 440 432 429.3 432 416C432 402.7 421.3 392 408 392L232 392C218.7 392 208 402.7 208 416zM232 296C218.7 296 208 306.7 208 320C208 333.3 218.7 344 232 344L408 344C421.3 344 432 333.3 432 320C432 306.7 421.3 296 408 296L232 296z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Bilties', href: "/bilties"},
                    {name: 'Add Bilty', href: "/bilties/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "bank-accounts",
                name: "Bank Accounts",
                details: {
                    '': 'Manage your bank account',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M335.9 84.2C326.1 78.6 314 78.6 304.1 84.2L80.1 212.2C67.5 219.4 61.3 234.2 65 248.2C68.7 262.2 81.5 272 96 272L128 272L128 480L128 480L76.8 518.4C68.7 524.4 64 533.9 64 544C64 561.7 78.3 576 96 576L544 576C561.7 576 576 561.7 576 544C576 533.9 571.3 524.4 563.2 518.4L512 480L512 272L544 272C558.5 272 571.2 262.2 574.9 248.2C578.6 234.2 572.4 219.4 559.8 212.2L335.8 84.2zM464 272L464 480L400 480L400 272L464 272zM352 272L352 480L288 480L288 272L352 272zM240 272L240 480L176 480L176 272L240 272zM320 160C337.7 160 352 174.3 352 192C352 209.7 337.7 224 320 224C302.3 224 288 209.7 288 192C288 174.3 302.3 160 320 160z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Accounts', href: "/bank-accounts"},
                    {name: 'Add Account', href: "/bank-accounts/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
            {
                id: "fabrics",
                name: "Fabrics",
                details: {
                    '': 'Manage your fabrics',
                },
                bottomChip: '4 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 150.73 150.73"> <path d="M20.63,155.18v-5.3a1.87,1.87,0,0,0,.28-.49c1.92-9.13,9.18-15.43,18.47-15.53,15-.15,29.93-.05,44.89,0A7,7,0,0,1,90,136.56a6.76,6.76,0,0,1,1,7.2,6.68,6.68,0,0,1-6,4.33c-4,.19-7.94.1-11.92.1-9.51,0-19,0-28.55,0-3,0-4.92,1.82-4.86,4.52s2,4.24,4.94,4.24q19.57,0,39.15,0a17.67,17.67,0,0,0,4.36-.49,16.09,16.09,0,0,0,12.28-16.11q0-50.26,0-100.54V38.13h60.81c.54,0,1.08,0,1.62,0a8.77,8.77,0,0,1,8.51,9.22q0,57.4,0,114.81a8.74,8.74,0,0,1-9.14,9.16H39.31a18.73,18.73,0,0,1-18-13.5C21.08,157,20.87,156.07,20.63,155.18Z" transform="translate(-20.63 -20.63)" /> <path d="M36.83,20.63H75.39a1.91,1.91,0,0,0,.52.25C85.62,22.83,91.58,30.1,91.58,40v86.35a62.22,62.22,0,0,0-6.21-1.2c-2.14-.21-4.31-.07-6.47-.07-12.6,0-25.21.11-37.82,0a29.07,29.07,0,0,0-20.45,7.46V36.83a5,5,0,0,0,.29-.82,17.84,17.84,0,0,1,8.75-12.58C31.86,22.16,34.43,21.55,36.83,20.63Z" transform="translate(-20.63 -20.63)" /> </svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Fabrics', href: "/fabrics"},
                    {name: 'Add Fabric', href: "/fabrics/create"},
                    {name: 'Issue Fabric', href: "/fabrics/issue"},
                    {name: 'Return Fabric', href: "/fabrics/return"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "employees",
                name: "Employees",
                details: {
                    '': 'Manage your employees',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M320 80C377.4 80 424 126.6 424 184C424 241.4 377.4 288 320 288C262.6 288 216 241.4 216 184C216 126.6 262.6 80 320 80zM96 152C135.8 152 168 184.2 168 224C168 263.8 135.8 296 96 296C56.2 296 24 263.8 24 224C24 184.2 56.2 152 96 152zM0 480C0 409.3 57.3 352 128 352C140.8 352 153.2 353.9 164.9 357.4C132 394.2 112 442.8 112 496L112 512C112 523.4 114.4 534.2 118.7 544L32 544C14.3 544 0 529.7 0 512L0 480zM521.3 544C525.6 534.2 528 523.4 528 512L528 496C528 442.8 508 394.2 475.1 357.4C486.8 353.9 499.2 352 512 352C582.7 352 640 409.3 640 480L640 512C640 529.7 625.7 544 608 544L521.3 544zM472 224C472 184.2 504.2 152 544 152C583.8 152 616 184.2 616 224C616 263.8 583.8 296 544 296C504.2 296 472 263.8 472 224zM160 496C160 407.6 231.6 336 320 336C408.4 336 480 407.6 480 496L480 512C480 529.7 465.7 544 448 544L192 544C174.3 544 160 529.7 160 512L160 496z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Employees', href: "/employees"},
                    {name: 'Add Employee', href: "/employees/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "employee-payments",
                name: "Employee Payments",
                details: {
                    '': 'Manage your employee paymetns',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M320 80C377.4 80 424 126.6 424 184C424 241.4 377.4 288 320 288C262.6 288 216 241.4 216 184C216 126.6 262.6 80 320 80zM96 152C135.8 152 168 184.2 168 224C168 263.8 135.8 296 96 296C56.2 296 24 263.8 24 224C24 184.2 56.2 152 96 152zM0 480C0 409.3 57.3 352 128 352C140.8 352 153.2 353.9 164.9 357.4C132 394.2 112 442.8 112 496L112 512C112 523.4 114.4 534.2 118.7 544L32 544C14.3 544 0 529.7 0 512L0 480zM521.3 544C525.6 534.2 528 523.4 528 512L528 496C528 442.8 508 394.2 475.1 357.4C486.8 353.9 499.2 352 512 352C582.7 352 640 409.3 640 480L640 512C640 529.7 625.7 544 608 544L521.3 544zM472 224C472 184.2 504.2 152 544 152C583.8 152 616 184.2 616 224C616 263.8 583.8 296 544 296C504.2 296 472 263.8 472 224zM160 496C160 407.6 231.6 336 320 336C408.4 336 480 407.6 480 496L480 512C480 529.7 465.7 544 448 544L192 544C174.3 544 160 529.7 160 512L160 496z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Payments', href: "/employee-payments"},
                    {name: 'Add Payments', href: "/employee-payments/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "daily-ledger",
                name: "Daily Ledger",
                details: {
                    '': 'Manage your daily ledger',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M320 80C377.4 80 424 126.6 424 184C424 241.4 377.4 288 320 288C262.6 288 216 241.4 216 184C216 126.6 262.6 80 320 80zM96 152C135.8 152 168 184.2 168 224C168 263.8 135.8 296 96 296C56.2 296 24 263.8 24 224C24 184.2 56.2 152 96 152zM0 480C0 409.3 57.3 352 128 352C140.8 352 153.2 353.9 164.9 357.4C132 394.2 112 442.8 112 496L112 512C112 523.4 114.4 534.2 118.7 544L32 544C14.3 544 0 529.7 0 512L0 480zM521.3 544C525.6 534.2 528 523.4 528 512L528 496C528 442.8 508 394.2 475.1 357.4C486.8 353.9 499.2 352 512 352C582.7 352 640 409.3 640 480L640 512C640 529.7 625.7 544 608 544L521.3 544zM472 224C472 184.2 504.2 152 544 152C583.8 152 616 184.2 616 224C616 263.8 583.8 296 544 296C504.2 296 472 263.8 472 224zM160 496C160 407.6 231.6 336 320 336C408.4 336 480 407.6 480 496L480 512C480 529.7 465.7 544 448 544L192 544C174.3 544 160 529.7 160 512L160 496z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Ledger', href: "/daily-ledger"},
                    {name: 'Deposit | Use', href: "/daily-ledger/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "cr",
                name: "CR",
                details: {
                    '': 'Manage your CR',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M320 80C377.4 80 424 126.6 424 184C424 241.4 377.4 288 320 288C262.6 288 216 241.4 216 184C216 126.6 262.6 80 320 80zM96 152C135.8 152 168 184.2 168 224C168 263.8 135.8 296 96 296C56.2 296 24 263.8 24 224C24 184.2 56.2 152 96 152zM0 480C0 409.3 57.3 352 128 352C140.8 352 153.2 353.9 164.9 357.4C132 394.2 112 442.8 112 496L112 512C112 523.4 114.4 534.2 118.7 544L32 544C14.3 544 0 529.7 0 512L0 480zM521.3 544C525.6 534.2 528 523.4 528 512L528 496C528 442.8 508 394.2 475.1 357.4C486.8 353.9 499.2 352 512 352C582.7 352 640 409.3 640 480L640 512C640 529.7 625.7 544 608 544L521.3 544zM472 224C472 184.2 504.2 152 544 152C583.8 152 616 184.2 616 224C616 263.8 583.8 296 544 296C504.2 296 472 263.8 472 224zM160 496C160 407.6 231.6 336 320 336C408.4 336 480 407.6 480 496L480 512C480 529.7 465.7 544 448 544L192 544C174.3 544 160 529.7 160 512L160 496z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show CRs', href: "/cr"},
                    {name: 'Generate CR', href: "/cr/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "dr",
                name: "DR",
                details: {
                    '': 'Manage your DR',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M320 80C377.4 80 424 126.6 424 184C424 241.4 377.4 288 320 288C262.6 288 216 241.4 216 184C216 126.6 262.6 80 320 80zM96 152C135.8 152 168 184.2 168 224C168 263.8 135.8 296 96 296C56.2 296 24 263.8 24 224C24 184.2 56.2 152 96 152zM0 480C0 409.3 57.3 352 128 352C140.8 352 153.2 353.9 164.9 357.4C132 394.2 112 442.8 112 496L112 512C112 523.4 114.4 534.2 118.7 544L32 544C14.3 544 0 529.7 0 512L0 480zM521.3 544C525.6 534.2 528 523.4 528 512L528 496C528 442.8 508 394.2 475.1 357.4C486.8 353.9 499.2 352 512 352C582.7 352 640 409.3 640 480L640 512C640 529.7 625.7 544 608 544L521.3 544zM472 224C472 184.2 504.2 152 544 152C583.8 152 616 184.2 616 224C616 263.8 583.8 296 544 296C504.2 296 472 263.8 472 224zM160 496C160 407.6 231.6 336 320 336C408.4 336 480 407.6 480 496L480 512C480 529.7 465.7 544 448 544L192 544C174.3 544 160 529.7 160 512L160 496z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show DRs', href: "/dr"},
                    {name: 'Generate DR', href: "/dr/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "reports",
                name: "Reports",
                details: {
                    '': 'Manage your reports',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M192 64C156.7 64 128 92.7 128 128L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 234.5C512 217.5 505.3 201.2 493.3 189.2L386.7 82.7C374.7 70.7 358.5 64 341.5 64L192 64zM453.5 240L360 240C346.7 240 336 229.3 336 216L336 122.5L453.5 240z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Statement', href: "/reports/statement"},
                    {name: 'Pending Payments', href: "/reports/pending-payments"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "productions",
                name: "Productions",
                details: {
                    '': 'Manage your productions',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1411.82 1222.49"><path d="M1293.89,1033.7H636.33V799.41h15.52q93.3,0,186.6,0c24.51,0,32.26-7.88,32.27-32.7q.06-132.23,0-264.43c0-23.92-8-32.06-31.4-32.08q-144.72-.07-289.45,0c-23.78,0-31,7.19-32.07,31.51-2.58,58-54,108.29-112.23,109.42-16.63.32-33.27,0-52.26,0,0,23.3-.11,46.07,0,68.84.08,13-6,21.65-18.45,24.77-14.82,3.71-28.08-7.17-28.38-23.38-.43-22.88-.11-45.78-.11-70.18-16.87,0-32.4.1-47.94,0a117,117,0,0,1-116.6-116.93q-.36-155,0-310C142.16,85.21,226.59.26,325.49.17q437.1-.42,874.22-.06c52.78,0,94.1,41.25,94.12,94q.17,463.47.06,927Zm-234.8-916.12c-76.86-.21-141.4,64.21-141.35,141.1,0,76.28,63.55,140.21,140,141,76.66.76,141.6-63.41,142.07-140.4C1200.31,182.43,1136.09,117.79,1059.09,117.58Z"/><path d="M705.67,1222.48H72.54c-30.49,0-52.87-14-65.47-41.25-12.38-26.78-8.32-52.68,10.74-75.36,12.84-15.29,29.68-24,49.91-24.21,38.68-.35,77.37-.37,116,.22,4.5.07,10.87,3.81,13.08,7.71,15,26.43,37.59,38.92,67.47,38.94q146.91.09,293.8.06c31,0,54.46-12.18,69.92-39.91,2.32-4.16,10.85-6.84,16.52-6.86q180.68-.57,361.37-.29,167.46,0,334.93.06c36.46,0,67.06,27.23,70.4,61.89,3.61,37.49-20.07,70.52-56.37,77.93-6.62,1.35-13.64,1-20.48,1Q1020,1222.51,705.67,1222.48Z"/><path d="M411.75,1081.42q-73.44,0-146.88,0c-21.6,0-29-7.42-29-29.06q-.06-135.84,0-271.68c0-20.69,7.58-28.23,28.32-28.25q148.35-.07,296.7,0c19.66,0,27.51,7.88,27.53,27.57q.11,136.57,0,273.15c0,20.64-7.64,28.23-28.34,28.26Q485.92,1081.52,411.75,1081.42Z"/><path d="M71.7,1033.61c0-51.62-2.31-102.52.68-153.11,2.69-45.39,45.32-80.57,91.25-81.07,7.75-.08,15.51,0,24.16,0v234.19Z"/><path d="M1341.69,469V165.73c34.69-2.3,68.85,28.14,69.42,63.63q1.42,88,0,176.15C1410.52,441,1376.11,471.52,1341.69,469Z"/><path d="M1152.79,258.23c.09,52.79-41.09,94.28-93.63,94.37-52.79.08-94.28-41.09-94.35-93.63-.08-52.86,41-94.25,93.6-94.33C1111.27,164.56,1152.71,205.65,1152.79,258.23Z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Productions', href: "/productions"},
                    {name: 'Add Production', href: "/productions/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "sales-returns",
                name: "Sales Return",
                details: {
                    '': 'Manage your sale returns',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M192 64C156.7 64 128 92.7 128 128L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 234.5C512 217.5 505.3 201.2 493.3 189.2L386.7 82.7C374.7 70.7 358.5 64 341.5 64L192 64zM453.5 240L360 240C346.7 240 336 229.3 336 216L336 122.5L453.5 240z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Returns', href: "/sales-returns"},
                    {name: 'Return a Sale', href: "/sales-returns/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "attendances",
                name: "Attendance",
                details: {
                    '': 'Manage your attendances',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M192 64C156.7 64 128 92.7 128 128L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 234.5C512 217.5 505.3 201.2 493.3 189.2L386.7 82.7C374.7 70.7 358.5 64 341.5 64L192 64zM453.5 240L360 240C346.7 240 336 229.3 336 216L336 122.5L453.5 240z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Generate Slip', href: "/attendances/generate-slip"},
                    {name: 'Manage Salary', href: "/attendances/manage-salary"},
                    {name: 'Record Attendance', href: "/attendances/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "utility-bills",
                name: "Utility Bills",
                details: {
                    '': 'Manage your utility Bills',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M192 64C156.7 64 128 92.7 128 128L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 234.5C512 217.5 505.3 201.2 493.3 189.2L386.7 82.7C374.7 70.7 358.5 64 341.5 64L192 64zM453.5 240L360 240C346.7 240 336 229.3 336 216L336 122.5L453.5 240z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Utility Bills', href: "/utility-bills"},
                    {name: 'Add Utility Bill', href: "/utility-bills/create"},
                ]
            },
        @endif

        @if (in_array(Auth::user()->role, ['developer', 'owner', 'admin', 'accountant']))
            {
                id: "utility-accounts",
                name: "Utility Accounts",
                details: {
                    '': 'Manage your utility Accounts',
                },
                bottomChip: '2 actions',
                svgIcon:'<svg class="size-5 fill-[var(--text-color)] group-hover:fill-[var(--primary-color)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M192 64C156.7 64 128 92.7 128 128L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 234.5C512 217.5 505.3 201.2 493.3 189.2L386.7 82.7C374.7 70.7 358.5 64 341.5 64L192 64zM453.5 240L360 240C346.7 240 336 229.3 336 216L336 122.5L453.5 240z"/></svg>',
                noMargin: true,
                onclick: 'openSubMenu(event, this)',
                oncontextmenu: 'openSubMenu(event, this)',
                switchBtn: {
                    active: false,
                },
                subMenu: [
                    {name: 'Show Utility Accounts', href: "/utility-accounts"},
                    {name: 'Add Utility Account', href: "/utility-accounts/create"},
                ]
            },
        @endif
    ];

    const pageName = window.location.href.toLowerCase().split('/')[3];

    function renderMenuShortcuts() {
        const customMenuShortcutsDom = document.getElementById('customMenuShortcuts');
        const filteredModules = menuData.filter(module =>
            menu_shortcuts.includes(module.id)
        );

        let clutter = '';
        filteredModules.forEach(shortcut => {
            const isActive = pageName == shortcut.id.toLowerCase();
            clutter += `
                <div class="relative group">
                    <!-- Main Icon Button -->
                    <button
                        onclick="openDropDown(event, this)"
                        class="nav-link ${shortcut.name.toLowerCase()} ${isActive && 'active'} dropdown-trigger text-[var(--text-color)] p-3 rounded-[41.5%] group-hover:bg-[var(--h-bg-color)] transition-all duration-300 ease-in-out w-10 h-10 flex items-center justify-center cursor-pointer relative"
                    >
                        ${shortcut.svgIcon}

                        <span
                            class="absolute shadow-xl left-18 top-1/2 transform -translate-y-1/2 bg-[var(--h-secondary-bg-color)] border border-gray-600 text-[var(--text-color)] text-xs rounded-lg px-2 py-1 opacity-0 group-hover:opacity-100 transition-all duration-300 pointer-events-none text-nowrap"
                        >
                            ${shortcut.name}
                        </span>
                    </button>

                    <!-- Dropdown Menu -->
                    <div
                        class="dropdownMenu text-sm absolute top-0 left-16 border border-gray-600 w-48 bg-[var(--h-secondary-bg-color)] text-[var(--text-color)] shadow-lg rounded-2xl transform scale-95 transition-all duration-300 ease-in-out z-50 opacity-0 scale-out hidden"
                    >
                        <ul class="p-2">
                            ${shortcut.subMenu.map(item => `
                                <li>
                                    <a
                                        href="${item.href}"
                                        class="block px-4 py-2 hover:bg-[var(--h-bg-color)] rounded-lg transition-all duration-200 ease-in-out"
                                    >
                                        ${item.name}
                                    </a>
                                </li>
                            `).join('')}
                        </ul>
                    </div>
                </div>
            `;
        });
        customMenuShortcutsDom.innerHTML = clutter;
    }
    renderMenuShortcuts();

    let modalData = {
        id: 'menuModal',
        class: 'h-[80%] w-full',
        cards: {name: 'Menu', count: 3, data: menuData},
        basicSearch: true,
        onBasicSearch: 'menuBasicSearch(this.value)',
        info: `Enabled: ${menu_shortcuts.length}/${maxShortcutsLimit}`,
        flex_col: true,
    }

    function generateMenuModal(){
        menuData.forEach((item)=>{
            item.switchBtn.active = menu_shortcuts.includes(item.id);
        });
        modalData.cards.data = menuData;
        createModal(modalData)
    }

    // pressing ctrl + space to open menu modal, also don't if already opened
    document.addEventListener('keydown', function(event) {
        if (event.ctrlKey && event.key === ' ') {
            event.preventDefault();
            const existingModal = document.getElementById(modalData.id);
            if (!existingModal) {
                generateMenuModal();
            }
        }
    });

    function menuBasicSearch(searchValue) {
        modalData.cards.data = menuData.filter((item) => item.name.toLowerCase().includes(searchValue.toLowerCase()));
        renderCardsInModal(modalData);
    }

    document.querySelectorAll('.dropdown-toggle').forEach(button => {
        button.addEventListener('click', () => {
            // Close other open dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (menu !== button.nextElementSibling) {
                    menu.classList.add('hidden');
                    menu.previousElementSibling.querySelector('i').classList.remove(
                        'rotate-180');
                }
            });

            // Toggle clicked dropdown
            const dropdownMenu = button.nextElementSibling;
            dropdownMenu.classList.toggle('hidden');
            button.querySelector('i').classList.toggle('rotate-180');
        });
    });

    function closeAllMobileMenuDropdowns() {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.add('hidden');
            menu.previousElementSibling.querySelector('i').classList.remove('rotate-180');
        });
    }

    const menuToggle = document.getElementById('menuToggle');
    const menuToggleIcon = document.querySelector('#menuToggle i');
    const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');
    const mobileMenu = document.getElementById('mobileMenu');

    menuToggle.addEventListener('click', () => {
        toggleMobileMenu();
    });

    function toggleMobileMenu() {
        closeAllMobileMenuDropdowns();

        // Toggle between bars and xmark icons
        menuToggleIcon.classList.toggle('fa-bars');
        menuToggleIcon.classList.toggle('fa-xmark');

        // Toggle menu visibility
        mobileMenu.classList.toggle('-translate-y-full'); // Moves out of view
        mobileMenu.classList.toggle('translate-y-0'); // Brings into view

        mobileMenuOverlay.classList.toggle('opacity-zero');
        mobileMenuOverlay.classList.toggle('pointer-events-none');
    }

    mobileMenuOverlay.addEventListener('mousedown', (e) => {
        if (e.target.classList.contains("mobileMenuOverlay")) {
            toggleMobileMenu();
        }
    })

    const html = document.documentElement;
    const themeIcon = document.querySelector('#themeToggle i');
    const themeToggle = document.getElementById('themeToggle');
    const themeToggleMobile = document.getElementById('themeToggleMobile');
    let isLogoutModalOpened = false;

    themeToggle?.addEventListener('click', () => {
        themefunction();
    });

    themeToggleMobile?.addEventListener('click', () => {
        themefunction();
    });

    function themefunction() {
        changeTheme();

        // Get the current theme from the HTML element
        const currentTheme = $('html').attr('data-theme');

        // Send an AJAX request to update the theme in the database
        $.ajax({
            url: '/update-theme', // Route to your controller
            type: 'POST',
            data: {
                theme: currentTheme,
                _token: $('meta[name="csrf-token"]').attr('content') // CSRF token
            },
            success: function(response) {
                // Check if messageBox exists
                if (messageBox) {
                    if (response.success) {
                        messageBox.innerHTML = `
                            <x-alert type="success" :messages="'${response.message}'" />
                        `;
                        messageBoxAnimation()
                    } else {
                        messageBox.innerHTML = `
                            <x-alert type="error" :messages="'Failed to update theme. Please try again later.'" />
                        `;
                        messageBoxAnimation()
                    }
                } else {
                    console.error('Element with ID "ajax-message" not found.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                if (messageBox) {
                    messageBox.innerHTML = `
                        <x-alert type="error" :messages="'An error occurred while updating the theme. Please try again later.'" />
                    `;
                    messageBoxAnimation()
                } else {
                    console.error('Element with ID "ajax-message" not found.');
                }
            }
        });
    }

    function changeTheme() {
        const currentTheme = html.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', newTheme);

        themeIcon?.classList.toggle('fa-sun');
        themeIcon?.classList.toggle('fa-moon');
    }

    document.getElementById('logoutModal').addEventListener('click', (e) => {
        if (e.target.id === 'logoutModal') {
            closeLogoutModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeLogoutModal();
        }
    });

    // Close any open dropdown when clicking anywhere else on the document
    document.addEventListener('mousedown', function(e) {
        // Check if the click is outside of any dropdown trigger or menu
        if (!e.target.closest('.dropdown-trigger') && !e.target.closest('.dropdownMenu')) {
            closeAllDropdowns();
        }
    });

    function openLogoutModal() {
        isLogoutModalOpened = true;
        document.getElementById('logoutModal').classList.remove('hidden');
        closeAllDropdowns();
    }

    function closeLogoutModal() {
        let logoutModal = document.getElementById('logoutModal')
        logoutModal.classList.add('fade-out');

        // Wait for the animation to complete
        logoutModal.addEventListener('animationend', () => {
            logoutModal.classList.add('hidden'); // Add hidden class after animation ends
            logoutModal.classList.remove('fade-out'); // Optional: Remove fade-out class to reset
        }, {
            once: true
        });
    }
</script>
