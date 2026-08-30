{{--
    One labelled form field, with its error message.

        <x-ui.field name="email" label="Email address" type="email" required />
        <x-ui.field name="notes" label="Anything we should know?" as="textarea" :rows="4" />

    Wires up the three things that are easy to forget and invisible when
    missing: the label's `for`, the `aria-describedby` pointing at the error,
    and `aria-invalid`. Without them a screen reader announces a field that has
    failed validation as though it were fine.

    Old input is restored automatically, so a patient who mistypes their email
    does not have to fill the whole form in again.
--}}

@props([
    'name',
    'label' => null,
    'type' => 'text',
    // input | textarea | select
    'as' => 'input',
    'rows' => 4,
    'hint' => null,
    'required' => false,
    'value' => null,
    // For `as="select"`: [value => label]
    'options' => [],
    'placeholder' => null,
])

@php
    // Handles dotted and bracketed names: social_links[facebook] → social_links_facebook
    $id = 'field-' . str_replace(['.', '[', ']'], ['-', '-', ''], $name);
    $errorId = $id . '-error';
    $hintId = $id . '-hint';

    $hasError = $errors->has($name);
    $current = old($name, $value);

    $describedBy = trim(($hint ? $hintId : '') . ' ' . ($hasError ? $errorId : ''));

    $control = implode(' ', [
        'w-full rounded-xl border bg-surface px-4 py-3 text-ink placeholder:text-muted/60',
        'transition-colors duration-200',
        'focus:border-brand focus:outline-2 focus:outline-offset-2 focus:outline-accent/40',
        $hasError ? 'border-negative' : 'border-line-strong',
    ]);
@endphp

<div {{ $attributes->class('flex flex-col gap-1.5') }}>
    @if ($label)
        <label for="{{ $id }}" class="text-sm font-semibold text-ink">
            {{ $label }}

            @if ($required)
                <span class="text-negative" aria-hidden="true">*</span>
                <span class="sr-only">(required)</span>
            @endif
        </label>
    @endif

    @if ($as === 'textarea')
        <textarea
            id="{{ $id }}"
            name="{{ $name }}"
            rows="{{ $rows }}"
            @if ($required) required @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($hasError) aria-invalid="true" @endif
            class="{{ $control }} resize-y"
        >{{ $current }}</textarea>

    @elseif ($as === 'select')
        <select
            id="{{ $id }}"
            name="{{ $name }}"
            @if ($required) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($hasError) aria-invalid="true" @endif
            class="{{ $control }}"
        >
            @if ($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif

            @foreach ($options as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>
                    {{ $optionLabel }}
                </option>
            @endforeach
        </select>

    @else
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            {{-- Passwords must never be repopulated from old input. --}}
            @if ($type !== 'password') value="{{ $current }}" @endif
            @if ($required) required @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($hasError) aria-invalid="true" @endif
            class="{{ $control }}"
        >
    @endif

    @if ($hint)
        <p id="{{ $hintId }}" class="text-sm text-muted">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $errorId }}" class="text-sm font-medium text-negative">{{ $message }}</p>
    @enderror
</div>
