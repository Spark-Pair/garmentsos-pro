@props([
    'label',
    'icon' => '',
    'svgIcon' => '',
    'includesDropdown' => false,
    'href' => '#',
    'items' => [],
    'activatorTags' => [],
    'onclick' => '',
])

@if ($includesDropdown)
    <!-- Main Icon Button -->
    <button type="button" onclick="openDropDown(event, this)" onkeydown="handleSidebarDropdownKeydown(event, this)"
        aria-haspopup="menu" aria-expanded="false" aria-label="{{ $label }}"
        data-nav-label="{{ strtolower($label) }}"
        data-activators='@json(collect($activatorTags ?? [])->map(fn ($t) => strtolower($t))->values())'
        class="nav-link {{ strtolower($label) }} dropdown-trigger text-[var(--text-color)] p-3 rounded-[41.5%] group-hover:bg-[var(--h-bg-color)] transition-all duration-300 ease-in-out w-10 h-10 flex items-center justify-center cursor-pointer relative">
        @if ($icon)
            <i class="{{ $icon }} group-hover:text-[var(--primary-color)]"></i>
        @else
            {!! $svgIcon !!}
        @endif
        <span
            class="sidebar-nav-label absolute shadow-xl left-18 top-1/2 transform -translate-y-1/2 bg-[var(--h-secondary-bg-color)] border border-gray-600 text-[var(--text-color)] text-xs rounded-lg px-2 py-1 opacity-0 group-hover:opacity-100 transition-all duration-300 pointer-events-none text-nowrap">
            {{ $label }}
        </span>
    </button>

    <!-- Dropdown Menu -->
    <div
        role="menu" aria-label="{{ $label }}"
        class="dropdownMenu text-sm absolute top-0 left-16 hidden border border-gray-600 w-48 bg-[var(--h-secondary-bg-color)] text-[var(--text-color)] shadow-lg rounded-2xl opacity-0 transform scale-95 transition-all duration-300 ease-in-out z-50">
        <ul class="p-2">
            @foreach ($items as $item)
                @if ($item['type'] === 'group')
                    <li class="relative open-dropdown">
                        <div
                            class="flex items-center justify-between px-4 py-2 hover:bg-[var(--h-bg-color)] rounded-lg cursor-pointer transition-all duration-200 ease-in-out">
                            {{ $item['label'] }}
                            <i class="fas fa-chevron-right text-xs ml-2"></i>
                        </div>

                        <!-- Submenu -->
                        <ul
                            class="absolute top-0 left-full -ml-2.5 hidden open-dropdown-hover:block w-48 bg-[var(--h-secondary-bg-color)] border border-gray-600 shadow-lg rounded-2xl scale-90 z-50 p-2">
                            @foreach ($item['children'] as $child)
                                <li>
                                    <a href="{{ $child['href'] }}"
                                        role="menuitem"
                                        class="block px-4 py-2 hover:bg-[var(--h-bg-color)] rounded-lg transition-all duration-200 ease-in-out">
                                        {{ $child['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @elseif ($item['type'] === 'link')
                    <li>
                        <a href="{{ $item['href'] }}"
                            role="menuitem"
                            class="block px-4 py-2 hover:bg-[var(--h-bg-color)] rounded-lg transition-all duration-200 ease-in-out">
                            {{ $item['label'] }}
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
@else
    <!-- No Dropdown, Just Link -->
    @if ($href === '#' && $onclick)
        <button type="button" onclick="{{ $onclick }}"
            aria-label="{{ $label }}"
            data-nav-label="{{ strtolower($label) }}"
            data-activators='@json(collect($activatorTags ?? [])->map(fn ($t) => strtolower($t))->values())'
            class="nav-link {{ strtolower($label) }} text-[var(--text-color)] p-3 rounded-[41.5%] hover:bg-[var(--h-bg-color)] transition-all duration-300 ease-in-out w-10 h-10 flex items-center justify-center group relative">
    @else
        <a href="{{ $href }}" onclick="{{ $onclick }}"
            aria-label="{{ $label }}"
            data-nav-label="{{ strtolower($label) }}"
            data-activators='@json(collect($activatorTags ?? [])->map(fn ($t) => strtolower($t))->values())'
            class="nav-link {{ strtolower($label) }} text-[var(--text-color)] p-3 rounded-[41.5%] hover:bg-[var(--h-bg-color)] transition-all duration-300 ease-in-out w-10 h-10 flex items-center justify-center group relative">
    @endif
        @if ($icon)
            <i class="{{ $icon }} group-hover:text-[var(--primary-color)]"></i>
        @else
            {!! $svgIcon !!}
        @endif
        <span
            class="sidebar-nav-label absolute shadow-xl left-18 top-1/2 transform -translate-y-1/2 bg-[var(--h-secondary-bg-color)] border border-gray-600 text-[var(--text-color)] text-xs rounded-lg px-2 py-1 opacity-0 group-hover:opacity-100 transition-all duration-300 pointer-events-none text-nowrap">
            {{ $label }}
        </span>
    @if ($href === '#' && $onclick)
        </button>
    @else
        </a>
    @endif
@endif

@once
    <script defer src="{{ asset('js/components/nav-link-item.js') }}"></script>
@endonce
