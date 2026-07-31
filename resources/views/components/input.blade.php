@props([
    'label' => '',
    'name' => '',
    'type' => 'text',
    'placeholder' => '',
    'value' => '',
    'required' => false,
    'disabled' => false,
    'uppercased' => false,
    'capitalized' => false,
    'class' => '',
    'id' => '',
    'list' => '',
    'autocomplete' => 'on',
    'listOptions' => [],
    'max' => '',
    'validateMax' => false,
    'min' => '',
    'validateMin' => false,
    'readonly' => false,
    'withImg' => false,
    'imgUrl' => '',
    'withButton' => false,
    'btnId' => '',
    'btnText' => '+',
    'btnClass' => '',
    'onchange' => '',
    'oninput' => '',
    'minlength' => '',
    'dualInput' => '',
    'type2' => '',
    'id2' => '',
    'value2' => '',
    'dataFilterPath' => '',
    'dataClearable' => false,
    'parentGrow' => false,
    'dataValidate' => '',
    'dataClean' => '',
    'withCheckbox' => false,
    'checkBoxes' => [],
    'addBtnLink' => '',
    'showError' => true,
])

@if ($uppercased)
    <style>
        input#{{ $id }} { text-transform: uppercase; }
        input#{{ $id }}::placeholder { text-transform: none; }
    </style>
@endif

@if ($capitalized)
    <style>
        input#{{ $id }} { text-transform: capitalize; }
        input#{{ $id }}::placeholder { text-transform: none; }
    </style>
@endif

@if ($type === 'username')
    @php
        $type = 'text';
        $oninput = 'formatUsername(this)';
        $minlength = '6';
    @endphp
@endif

@php
    // For custom selects, data-error-for is the real hidden database field name.
    $errorTargetName = $attributes->get('data-error-for') ?: $name;
    $hasServerError = $showError && $errors->has($errorTargetName);
    $hasRightAccessory = $withImg;
    $inputRightPadding = $showError
        ? ($hasRightAccessory ? 'pr-14' : 'pr-3')
        : ($hasRightAccessory ? 'pr-3' : '');
    $errorIconRight = $hasRightAccessory ? 'right-8' : 'right-3';
@endphp

<div class="form-group relative {{ $parentGrow ? 'grow' : '' }} {{ $hasServerError ? 'has-field-error' : '' }}">
    @if ($label)
        <span class="mb-2 flex items-center justify-between">
            <label for="{{ $id ?: $name }}" class="block font-medium text-[var(--secondary-text)]">
                {{ $label }}{{ !$required && !$readonly && !$disabled ? ' (optional)' : '' }}
            </label>
            @if ($addBtnLink !== '')
                <a class="px-2 text-lg leading-none" href="{{ $addBtnLink }}">+</a>
            @endif
        </span>
    @endif

    <div class="field-control relative flex gap-4">
        @if ($withCheckbox)
            <div {{ $attributes->merge([
                'class' => $class . ' w-full rounded-lg border px-1 py-1 text-[var(--text-color)] ' .
                    ($hasServerError ? 'border-[var(--border-error)]' : 'border-gray-600')
            ]) }}>
                <div class="checkboxes_container grid grid-cols-4 gap-1">
                    @foreach ($checkBoxes as $checkbox)
                        <label class="flex cursor-pointer items-center gap-2 rounded-md border border-[var(--h-bg-color)] bg-[var(--h-bg-color)] px-2 py-[0.1875rem] shadow-sm transition hover:border-primary hover:shadow-md">
                            <input
                                type="checkbox"
                                onchange="toggleThisCheckbox(this)"
                                data-checkbox="{{ $checkbox }}"
                                class="checkbox h-4 w-4 appearance-none rounded-sm border border-gray-600 bg-[var(--secondary-bg-color)] transition checked:bg-[var(--primary-color)]"
                            />
                            <span class="text-sm font-medium capitalize text-[var(--secondary-text)]">{{ ucfirst($checkbox) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @else
            <input
                id="{{ $id }}"
                type="{{ $type }}"
                name="{{ $name }}"
                @if ($value !== '') value="{{ old($name, $value) }}" @endif
                placeholder="{{ $placeholder }}"
                autocomplete="{{ $autocomplete }}"
                @if ($list !== '') list="{{ $list }}" @endif
                @if ($required) required @endif
                @if ($readonly) readonly @endif
                @if ($disabled) disabled @endif
                @if ($minlength !== '') minlength="{{ $minlength }}" @endif
                {{ $attributes->merge([
                    'class' => trim(
                        $class . ' w-full rounded-lg border bg-[var(--h-bg-color)] px-3 text-[var(--text-color)] ' .
                        ($type === 'date' ? 'py-[7px] ' : 'py-2 ') .
                        $inputRightPadding . ' ' .
                        ($hasServerError ? 'border-[var(--border-error)]' : 'border-gray-600') .
                        ' transition-all duration-200 ease-out placeholder:capitalize disabled:bg-transparent disabled:opacity-70'
                    )
                ]) }}
                @if ($dataValidate) data-validate="{{ $dataValidate }}" @endif
                @if ($dataClean) data-clean="{{ $dataClean }}" @endif
                @if ($validateMax) max="{{ $max }}" @endif
                @if ($validateMin) min="{{ $min }}" @endif
                @if ($onchange) onchange="{{ $onchange }}" @endif
                @if ($oninput) oninput="{{ $oninput }}" @endif
                @if ($dataFilterPath) data-filter-path="{{ $dataFilterPath }}" @endif
                @if ($dataClearable) data-clearable @endif
                @if ($showError) aria-describedby="{{ $errorTargetName }}-error" @endif
                @if ($hasServerError) aria-invalid="true" @endif
            />
        @endif

        @if ($dualInput)
            <input
                id="{{ $id2 }}"
                type="{{ $type2 }}"
                value="{{ $value2 }}"
                class="{{ $class }} w-full rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 py-2 text-[var(--text-color)] transition-all duration-200"
                @if ($oninput) oninput="{{ $oninput }}" @endif
                @if ($dataFilterPath) data-filter-path="{{ $dataFilterPath }}" @endif
                @if ($dataClearable) data-clearable @endif
            />
        @endif

        @if ($withImg)
            <img id="img-{{ $id }}" src="{{ $imgUrl }}" alt="image"
                class="absolute right-2 top-1/2 h-6 w-6 -translate-y-1/2 cursor-pointer rounded object-cover {{ $imgUrl === '' ? 'opacity-0' : '' }}"
                onclick="openArticleModal()">
        @endif

        @if ($withButton)
            <button id="{{ $btnId }}" type="button"
                class="{{ $btnClass }} cursor-pointer rounded-lg bg-[var(--primary-color)] px-4 transition-all duration-300 hover:bg-[var(--h-primary-color)] disabled:cursor-not-allowed disabled:opacity-50 {{ $btnText === '+' ? 'text-lg font-bold' : 'text-nowrap' }}">
                {!! $btnText !!}
            </button>
        @endif

        @if ($showError)
            <div class="errorIconWrap absolute {{ $errorIconRight }} top-1/2 z-20 -translate-y-1/2">
                <button type="button" tabindex="-1" aria-label="Validation error"
                    class="errorIcon peer flex size-[20px] items-center justify-center rounded-full border border-[var(--border-error)] bg-[color-mix(in_srgb,var(--border-error)_10%,var(--secondary-bg-color))] text-[13px] font-bold leading-none text-[var(--border-error)] opacity-0 pointer-events-none transition-all duration-200">
                    !
                </button>

                <div id="{{ $errorTargetName }}-error" role="alert"
                    class="field-error-msg {{ $hasServerError ? '' : 'hidden' }} absolute bottom-[calc(100%+8px)] right-0 z-50 w-max min-w-[9rem] max-w-[230px] rounded-md border border-[color-mix(in_srgb,var(--border-error)_35%,transparent)] bg-[var(--secondary-bg-color)] px-3 py-2 text-xs font-medium leading-4 text-[var(--text-color)] shadow-[0_10px_30px_rgba(15,23,42,0.16)] opacity-0 pointer-events-none translate-y-1 transition-all duration-150 peer-hover:translate-y-0 peer-hover:opacity-100 peer-focus:translate-y-0 peer-focus:opacity-100">
                    @error($errorTargetName){{ $message }}@enderror
                </div>
            </div>
        @endif
    </div>

    @if ($list !== '')
        <datalist id="{{ $list }}">
            @foreach ($listOptions as $option)
                <option value="{{ $option }}"></option>
            @endforeach
        </datalist>
    @endif
</div>