@props([
    'label' => '',
    'name' => '',
    'options' => [],
    'value' => '',
    'showDefault' => false,
    'class' => '',
    'withButton' => false,
    'btnId' => '',
    'id' => '',
    'btnText' => '+',
    'onchange' => '',
    'btnOnclick' => '',
    'dataFilterPath' => '',
    'dataClearable' => false,
    'disabled' => false,
    'addBtnLink' => '',
    'required' => false,
    'multiple' => false,
])

@php
    $haveOptions = count($options) > 0;
    $resolvedValue = old($name, $value);
    $selectedValues = collect(is_array($resolvedValue) ? $resolvedValue : explode(',', (string) $resolvedValue))
        ->map(fn ($item) => trim((string) $item))
        ->filter(fn ($item) => $item !== '')
        ->values()
        ->all();
    $hiddenValue = $multiple ? implode(',', $selectedValues) : $resolvedValue;
    $isDisabled = !$haveOptions || $disabled;

    // Determine selected option
    $selectedText = '';
    if ($multiple && count($selectedValues) > 0) {
        $selectedText = collect($selectedValues)
            ->map(fn ($selectedValue) => $options[$selectedValue]['text'] ?? null)
            ->filter()
            ->implode(', ');
    } elseif ($resolvedValue && isset($options[$resolvedValue])) {
        $selectedText = $options[$resolvedValue]['text'];
    }

    // Placeholder logic
    $placeholderText = '';
    if ($isDisabled && $selectedText) {
        $placeholderText = $selectedText;
    } elseif (!$haveOptions) {
        $placeholderText = '-- No options available --';
    } elseif ($showDefault === true && !$resolvedValue) {
        $placeholderText = $multiple ? '-- Select one or more ' . $label . ' --' : '-- Select ' . $label . ' --';
    }

    // Highlight default if not disabled and no selection
    $showDefaultSelected = !$isDisabled && count($selectedValues) === 0 && $showDefault;

    $hasServerError = $errors->has($name);
@endphp

<style>
    /*
     * Original select dropdown behaviour.
     * DOM structure and all JS hooks remain unchanged.
     */
    .dropDownParent {
        opacity: 0;
        pointer-events: none;
        transition:
            opacity 0.3s ease-in-out,
            translate 0.3s ease-in-out;
        translate: 0 -10px;
    }

    .selectParent:has(input:focus) .dropDownParent {
        opacity: 1;
        pointer-events: all;
        translate: 0;
    }

    .selectParent:focus-within > .form-group > .field-control > input:first-child {
        outline: 1px solid var(--primary-color);
        outline-offset: 2px;
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 22%, transparent);
    }

    .form-group.has-field-error > .selectParent:focus-within > .form-group > .field-control > input:first-child {
        outline-color: var(--border-error) !important;
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--border-error) 12%, transparent) !important;
    }

    .selected {
        background-color: var(--h-bg-color);
        color: var(--text-color);
    }

    .select-active {
        background-color: var(--h-bg-color);
    }

    .optionsDropdown li[data-multiple="true"].selected::after {
        content: '✓';
        position: absolute;
        right: 0.75rem;
        top: 50%;
        color: var(--primary-color);
        font-weight: 700;
        transform: translateY(-50%);
    }

</style>

<div class="{{ $class }} form-group">
    @if ($label)
        <span class="flex items-center justify-between mb-2">
            <label
                for="{{ $name }}"
                class="block font-medium text-[var(--secondary-text)]"
            >
                {{ $label }}{{ !$required && !$disabled ? ' (optional)' : '' }}
            </label>
            <span class="flex items-center gap-2">
                @if ($multiple)
                    <span class="inline-flex h-5 items-center rounded-lg border border-[var(--primary-color)]/25 bg-[color-mix(in_srgb,var(--primary-color)_10%,transparent)] px-2 text-[10px] font-semibold leading-none text-[var(--primary-color)] shadow-[inset_0_1px_0_rgb(255_255_255_/_0.16)]">Multi</span>
                @endif
                @if ($addBtnLink !== '')
                    <a
                        class="select-add-btn text-lg px-2 leading-none"
                        href="{{ $addBtnLink }}"
                    >
                        +
                    </a>
                @endif
            </span>
        </span>
    @endif

    <div class="selectParent flex gap-4">
        {{-- Visible Input --}}
        <x-input
            id="{{ $id }}"
            name="{{ $id }}_name"
            parentGrow
            autocomplete="off"
            :disabled="$isDisabled"
            :value="$isDisabled ? '' : $selectedText"
            :placeholder="$placeholderText"
            onfocus="selectClicked(this)"
            :dataClearable="$dataClearable"
            :dataValidate="$required ? 'required' : ''"
            data-error-for="{{ $name }}"
            :showError="true"
        />

        {{-- Hidden Input --}}
        <input
            type="hidden"
            class="dbInput"
            data-for="{{ $id }}"
            name="{{ $name }}"
            value="{{ $isDisabled ? '' : $hiddenValue }}"
            {!! $onchange ? 'onchange="' . $onchange . '"' : '' !!}
            {!! $dataFilterPath ? 'data-filter-path="' . $dataFilterPath . '"' : '' !!}
            @if ($multiple)
                data-multiple="true"
            @endif
            @if ($dataClearable)
                data-clearable
            @endif
        >

        {{-- Dropdown List --}}
        <div
            class="dropDownParent flex flex-col gap-2 fixed z-50 mt-2 w-full rounded-xl bg-[var(--secondary-bg-color)] border-gray-600 text-[var(--text-color)] p-1.5 border appearance-none focus:ring-2 focus:ring-primary focus:border-transparent max-h-[13rem]"
        >
            <x-input
                data-for="{{ $id }}"
                oninput="searchSelect(this)"
                onblur="validateSelectInput(this)"
                autocomplete="off"
                :value="$isDisabled ? '' : $selectedText"
                :placeholder="$placeholderText"
                onkeydown="selectKeyDown(event, this)"
                :dataClearable="$dataClearable"
                :showError="false"
            />

            <ul
                class="optionsDropdown overflow-auto my-scrollbar-2 space-y-0.5 grow"
                data-for="{{ $id }}"
            >
                @if ($showDefault === true && $haveOptions)
                    <li
                        data-for="{{ $id }}"
                        data-value=""
                        onmousedown="selectThisOption(this)"
                        class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] {{ $showDefaultSelected ? 'selected' : '' }}"
                    >
                        {{ $multiple ? '-- Select one or more ' . $label . ' --' : '-- Select ' . $label . ' --' }}
                    </li>
                @endif

                @foreach ($options as $optionValue => $option)
                    @php
                        $dataOptionAttr = null;

                        if (isset($option['data_option'])) {
                            $rawDataOption = $option['data_option'];

                            if (
                                $rawDataOption instanceof
                                \Illuminate\Database\Eloquent\Model
                            ) {
                                $dataOptionAttr = json_encode(
                                    $rawDataOption->attributesToArray(),
                                    JSON_UNESCAPED_UNICODE |
                                    JSON_UNESCAPED_SLASHES
                                );
                            } elseif (
                                $rawDataOption instanceof
                                \Illuminate\Support\Collection
                            ) {
                                $safeCollection = $rawDataOption
                                    ->map(
                                        fn ($item) =>
                                            $item instanceof
                                            \Illuminate\Database\Eloquent\Model
                                                ? $item->attributesToArray()
                                                : $item
                                    )
                                    ->all();

                                $dataOptionAttr = json_encode(
                                    $safeCollection,
                                    JSON_UNESCAPED_UNICODE |
                                    JSON_UNESCAPED_SLASHES
                                );
                            } elseif (
                                is_array($rawDataOption) ||
                                is_object($rawDataOption)
                            ) {
                                $dataOptionAttr = json_encode(
                                    $rawDataOption,
                                    JSON_UNESCAPED_UNICODE |
                                    JSON_UNESCAPED_SLASHES
                                );
                            } else {
                                $dataOptionAttr =
                                    (string) $rawDataOption;
                            }
                        }
                    @endphp

                    <li
                        data-for="{{ $id }}"
                        data-value="{{ $optionValue }}"
                        onmousedown="selectThisOption(this)"
                        @if ($multiple)
                            data-multiple="true"
                        @endif
                        @if (!is_null($dataOptionAttr))
                            data-option="{{ $dataOptionAttr }}"
                        @endif
                        @if (isset($option['selected']))
                            data-auto-select="true"
                        @endif
                        class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden {{ $multiple ? 'relative pr-8' : '' }} {{ !$isDisabled && in_array((string) $optionValue, $selectedValues, true) ? 'selected' : '' }}"
                    >
                        {{ $option['text'] }}
                    </li>

                    @if (isset($option['selected']))
                        @once
                            <script
                                defer
                                src="{{ asset('js/components/select-autoselect.js') }}"
                            ></script>
                        @endonce
                    @endif
                @endforeach
            </ul>
        </div>

        {{-- Optional Button --}}
        @if ($withButton)
            <button
                onclick="{{ $btnOnclick }}"
                id="{{ $btnId }}"
                type="button"
                class="bg-[var(--primary-color)] px-4 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out cursor-pointer {{ $btnText === '+' ? 'text-lg font-bold' : 'text-nowrap' }} disabled:opacity-50 disabled:cursor-not-allowed"
            >
                {{ $btnText }}
            </button>
        @endif
    </div>
</div>
