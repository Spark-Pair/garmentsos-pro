@props(['moduleKey', 'mode' => 'auto'])

@php
    $branchService = app(\App\Services\Branches\ModuleBranchService::class);

    $isMulti = $mode === 'multiple'
        ? $branchService->supportsMultiBranchSelector($moduleKey)
        : (
            $mode === 'auto'
            && $branchService->shouldUseMultiBranchSelector($moduleKey)
        );

    $canRender = $branchService->canShowSelector($moduleKey);

    $branches = $canRender
        ? $branchService->availableBranchesForModule($moduleKey)
        : collect();

    $selected = $canRender
        ? $branchService->selectedBranchForModule($moduleKey)
        : null;

    $selectedIds = $isMulti
        ? $branchService->selectedBranchIdsForModule($moduleKey)
        : collect([$selected?->id])->filter()->all();

    $summary = $isMulti
        ? $branchService->selectedBranchSummaryForModule($moduleKey)
        : ($selected?->name ?? 'Select Branch');

    $selectedCount = count($selectedIds);
    $totalBranches = $branches->count();
@endphp

@if ($canRender)
    @once
        <style>
            .branch-switcher > summary::-webkit-details-marker {
                display: none;
            }

            .branch-switcher > summary::marker {
                content: "";
            }

            .branch-switcher,
            .branch-switcher *,
            .branch-switcher *::before,
            .branch-switcher *::after {
                transition:
                    color 180ms ease,
                    background-color 180ms ease,
                    border-color 180ms ease,
                    opacity 180ms ease,
                    transform 180ms ease,
                    box-shadow 180ms ease;
            }

            .branch-switcher-chevron {
                transform: rotate(0deg);
            }

            .branch-switcher[open] .branch-switcher-chevron {
                transform: rotate(180deg);
            }

            .branch-switcher-menu {
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transform: translateY(-0.35rem) scale(0.985);
                transform-origin: top left;
            }

            .branch-switcher[open] .branch-switcher-menu {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
                transform: translateY(0) scale(1);
            }

            /*
             * Selected state is intentionally softer than hover.
             * Hover remains var(--h-bg-color), while selected uses a lighter mix.
             */
            .branch-selected-row,
            .branch-multi-option:has(.branch-multi-input:checked) {
                background-color: color-mix(
                    in srgb,
                    var(--h-bg-color) 58%,
                    var(--bg-color)
                );
                color: var(--text-color);
                font-weight: 600;
            }

            .branch-selected-row:hover,
            .branch-multi-option:hover {
                background-color: var(--h-bg-color);
            }

            .branch-multi-option {
                position: relative;
                color: var(--secondary-text);
            }

            .branch-multi-check {
                opacity: 0;
                transform: scale(0.82);
            }

            .branch-multi-option:has(.branch-multi-input:checked)
            .branch-multi-check {
                opacity: 1;
                transform: scale(1);
            }

            .branch-check-badge {
                background-color: var(--bg-color);
                border: 1px solid rgba(75, 85, 99, 0.66);
            }

            /*
             * "All Branches" stays visually distinct without looking like
             * a large card or another normal branch row.
             */
            .branch-all-option {
                color: var(--secondary-text);
                background-color: color-mix(
                    in srgb,
                    var(--h-bg-color) 20%,
                    var(--bg-color)
                );
                border: 1px solid rgba(75, 85, 99, 0.36);
            }

            .branch-all-option:hover {
                color: var(--text-color);
                background-color: color-mix(
                    in srgb,
                    var(--h-bg-color) 64%,
                    var(--bg-color)
                );
                border-color: rgba(75, 85, 99, 0.64);
            }

            .branch-all-option:has(.branch-multi-input:checked) {
                color: var(--text-color);
                background-color: color-mix(
                    in srgb,
                    var(--h-bg-color) 44%,
                    var(--bg-color)
                );
                border-color: rgba(75, 85, 99, 0.56);
                font-weight: 600;
            }

            .branch-all-count {
                min-width: 1.25rem;
                background-color: var(--bg-color);
                border: 1px solid rgba(75, 85, 99, 0.38);
            }

            /*
             * All Branches uses a subtle status pill instead of a branch tick.
             * It appears only when every branch is selected.
             */
            .branch-all-selected-badge {
                max-width: 0;
                margin-left: 0;
                padding-left: 0;
                padding-right: 0;
                overflow: hidden;
                border-width: 0;
                opacity: 0;
                transform: translateX(0.2rem) scale(0.94);
                white-space: nowrap;
            }

            .branch-all-option:has(.branch-multi-input:checked)
            .branch-all-selected-badge {
                max-width: 3.75rem;
                margin-left: 0.25rem;
                padding-left: 0.4rem;
                padding-right: 0.4rem;
                border-width: 1px;
                opacity: 1;
                transform: translateX(0) scale(1);
            }


            /*
             * Play a short visual confirmation before single-branch submit.
             */
            .branch-switcher-option.is-switching {
                background-color: var(--h-bg-color) !important;
                color: var(--text-color) !important;
                pointer-events: none;
            }

            .branch-switcher-option.is-switching .branch-switching-indicator {
                opacity: 1;
                transform: scale(1);
            }

            .branch-switching-indicator {
                opacity: 0;
                transform: scale(0.8);
            }

            .branch-switcher.is-submitting .branch-switcher-menu {
                pointer-events: none;
            }
        </style>
    @endonce

    <details
        class="branch-switcher relative h-full"
        title="Current branch: {{ $selected?->name ?? 'Select branch' }}"
    >
        {{-- Trigger --}}
        <summary
            aria-label="Switch branch"
            class="border border-gray-600 bg-[var(--bg-color)] h-full rounded-xl cursor-pointer flex items-center p-1 pr-3 overflow-hidden transition-all duration-300 ease-in-out list-none hover:bg-[var(--h-secondary-bg-color)]"
            style="list-style: none;"
        >
            <div class="flex items-center justify-center bg-[var(--h-bg-color)] rounded-lg p-2">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 512 512"
                    fill="none"
                    class="size-4 text-[var(--secondary-text)]"
                    aria-hidden="true"
                >
                    <path
                        d="M149 106V406M149 235H264C312 235 351 196 351 148V106"
                        stroke="currentColor"
                        stroke-width="43"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />

                    <circle cx="149" cy="106" r="64" fill="currentColor" />
                    <circle cx="351" cy="106" r="64" fill="currentColor" />
                    <circle cx="149" cy="406" r="64" fill="currentColor" />
                </svg>
            </div>

            <span class="ml-2 max-w-[190px] overflow-hidden text-ellipsis whitespace-nowrap text-xs font-semibold text-[var(--secondary-text)]">
                {{ $summary }}
            </span>

            <i class="branch-switcher-chevron fas fa-chevron-down ml-2 text-[10px] text-[var(--secondary-text)] transition-all duration-300 ease-in-out"></i>
        </summary>

        {{-- Dropdown --}}
        <div class="branch-switcher-menu absolute left-0 top-[calc(100%+0.5rem)] z-50 w-[18rem] rounded-xl border border-gray-600 bg-[var(--bg-color)] p-2 shadow-xl">

            {{-- Header --}}
            <div class="flex items-center justify-between gap-3 border-b border-gray-600 pb-2.5 mb-2">
                <div class="flex min-w-0 items-center gap-2">
                    <div class="flex shrink-0 items-center justify-center rounded-md bg-[var(--h-bg-color)] p-1.5">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 512 512"
                            fill="none"
                            class="size-4 text-[var(--primary-color)]"
                            aria-hidden="true"
                        >
                            <path
                                d="M149 106V406M149 235H264C312 235 351 196 351 148V106"
                                stroke="currentColor"
                                stroke-width="43"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />

                            <circle cx="149" cy="106" r="64" fill="currentColor" />
                            <circle cx="351" cy="106" r="64" fill="currentColor" />
                            <circle cx="149" cy="406" r="64" fill="currentColor" />
                        </svg>
                    </div>

                    <div class="min-w-0">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-[var(--text-color)]">
                            {{ $isMulti ? 'Select Branches' : 'Switch Branch' }}
                        </div>

                        <div class="overflow-hidden text-ellipsis whitespace-nowrap text-[10px] leading-tight text-[var(--secondary-text)]">
                            {{ $isMulti
                                ? 'Choose one or more branches'
                                : 'Choose your active branch'
                            }}
                        </div>
                    </div>
                </div>
                <span
                    class="size-2.5 shrink-0 rounded-full bg-[var(--primary-color)] mr-3.5"
                    title="Active branch selected"
                ></span>
            </div>

            {{-- Options --}}
            <div class="space-y-1">
                @if ($isMulti)
                    {{-- All branches --}}
                    <label
                        class="branch-all-option mb-1.5 flex w-full cursor-pointer items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-xs"
                    >
                        {{-- Keep the real checkbox for the existing JS logic --}}
                        <input
                            type="checkbox"
                            data-module-branch-all
                            class="branch-multi-input sr-only"
                            @checked($selectedCount === $totalBranches)
                        >

                        <span class="min-w-0 flex-1">
                            <span class="block overflow-hidden text-ellipsis whitespace-nowrap font-semibold leading-tight">
                                All Branches
                            </span>

                            <span class="mt-0.5 block text-[9px] font-normal leading-none text-[var(--secondary-text)]">
                                Select all at once
                            </span>
                        </span>

                        <span class="flex shrink-0 items-center">
                            <span class="branch-all-count flex h-5 items-center justify-center rounded-md px-1 text-[9px] font-medium text-[var(--secondary-text)]">
                                {{ $totalBranches }}
                            </span>

                            <span class="branch-all-selected-badge flex h-5 shrink-0 items-center justify-center rounded-md border border-gray-600 bg-[var(--bg-color)] text-[8px] font-semibold tracking-wide text-[var(--primary-color)]">
                                Selected
                            </span>
                        </span>
                    </label>

                    {{-- Individual branches --}}
                    @foreach ($branches as $branch)
                        @php
                            $isChecked = in_array(
                                (int) $branch->id,
                                $selectedIds,
                                true
                            );
                        @endphp

                        <label
                            class="branch-multi-option flex w-full cursor-pointer items-center justify-between gap-3 rounded-lg pl-3 pr-2 py-2 text-left text-xs transition-all duration-300 ease-in-out"
                        >
                            {{-- Keep the real checkbox for the existing JS logic --}}
                            <input
                                type="checkbox"
                                data-module-branch-checkbox
                                data-branch-id="{{ $branch->id }}"
                                class="branch-multi-input sr-only"
                                @checked($isChecked)
                            >

                            <span class="min-w-0 flex-1 overflow-hidden text-ellipsis whitespace-nowrap transition-all duration-300 ease-in-out">
                                {{ $branch->name }}
                            </span>

                            <span class="branch-multi-check branch-check-badge flex size-5 shrink-0 items-center justify-center rounded-sm">
                                <i class="fas fa-check text-[9px] text-[var(--primary-color)]"></i>
                            </span>
                        </label>
                    @endforeach

                    {{-- Actions --}}
                    <div class="mt-2 flex gap-2 border-t border-gray-600 pt-2">
                        <button
                            type="button"
                            data-module-branch-apply
                            data-module-key="{{ $moduleKey }}"
                            data-selection-mode="multiple"
                            class="flex-1 rounded-lg bg-[var(--primary-color)] px-3 py-2.5 text-xs font-semibold text-[var(--text-color)] transition-all duration-300 ease-in-out hover:bg-[var(--h-primary-color)]"
                        >
                            Apply
                        </button>

                        <button
                            type="button"
                            onclick="this.closest('details').removeAttribute('open')"
                            class="rounded-lg border border-gray-600 bg-[var(--bg-color)] px-3 py-2.5 text-xs font-semibold text-[var(--secondary-text)] transition-all duration-300 ease-in-out hover:bg-[var(--h-bg-color)]"
                        >
                            Cancel
                        </button>
                    </div>
                @else
                    {{-- Single branch mode --}}
                    @foreach ($branches as $branch)
                        @php
                            $isCurrentBranch = $selected?->id === $branch->id;
                        @endphp

                        <button
                            type="button"
                            data-module-branch-option
                            data-module-key="{{ $moduleKey }}"
                            data-branch-id="{{ $branch->id }}"
                            class="branch-switcher-option flex w-full items-center justify-between gap-3 rounded-lg pl-3 pr-2 py-2 text-left text-xs {{ $isCurrentBranch
                                ? 'branch-selected-row'
                                : 'text-[var(--secondary-text)] hover:bg-[var(--h-bg-color)]'
                            }}"
                            @disabled($isCurrentBranch)
                        >
                            <span class="min-w-0 flex-1 overflow-hidden text-ellipsis whitespace-nowrap">
                                {{ $branch->name }}
                            </span>

                            @if ($isCurrentBranch)
                                <span class="branch-check-badge flex size-5 shrink-0 items-center justify-center rounded-sm">
                                    <i class="fas fa-check text-[9px] text-[var(--primary-color)]"></i>
                                </span>
                            @else
                                <span class="branch-switching-indicator branch-check-badge flex size-5 shrink-0 items-center justify-center rounded-sm">
                                    <i class="fas fa-check text-[9px] text-[var(--primary-color)]"></i>
                                </span>
                            @endif
                        </button>
                    @endforeach
                @endif
            </div>
        </div>
    </details>
@endif