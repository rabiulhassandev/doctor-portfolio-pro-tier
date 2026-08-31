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

    ---------------------------------------------------------------------------
    WHERE STRAY ATTRIBUTES GO
    ---------------------------------------------------------------------------

    Anything not declared as a prop is forwarded to the CONTROL, not to the
    wrapping div — `class` is the one exception, which styles the wrapper so a
    caller can still say `class="sm:col-span-2"`.

    That split is not cosmetic. Every `autocomplete` on every one of these forms
    used to land on the wrapper, where it means nothing: the browser saw an
    unannotated password field, and neither its own autofill nor a password
    manager could offer the saved credential. The forms looked completely
    normal and simply never filled themselves in.
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

    // A password field gets a reveal button inside it, so the control needs
    // room on the trailing edge for the button to sit in.
    $isPassword = $as === 'input' && $type === 'password';

    /*
     | Square corners and a brass focus ring, matching the buttons. Inputs are
     | the place a design most often forgets itself — a rounded pill field
     | under a square button is the sort of mismatch nobody can name but
     | everybody notices.
     */
    $control = implode(' ', [
        'w-full rounded-[3px] border bg-surface px-4 py-3 text-ink placeholder:text-muted/55',
        'transition-colors duration-200',
        'focus:border-brass focus:outline-2 focus:outline-offset-2 focus:outline-brass/35',
        $hasError ? 'border-negative' : 'border-line-strong',
        $isPassword ? 'pr-12' : '',
    ]);

    $forwarded = $attributes->except(['class']);
@endphp

<div class="flex flex-col gap-1.5 {{ $attributes->get('class') }}">
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
            {{ $forwarded }}
            class="{{ $control }} resize-y"
        >{{ $current }}</textarea>

    @elseif ($as === 'select')
        <select
            id="{{ $id }}"
            name="{{ $name }}"
            @if ($required) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($hasError) aria-invalid="true" @endif
            {{ $forwarded }}
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

    @elseif ($isPassword)
        {{-- The reveal.

             Worth having on any password field and close to essential on a
             phone, where the keyboard hides half the screen and a mistyped
             character is invisible. It defaults to hidden and is a real
             <button>, so it is reachable by keyboard and announced properly.

             The `type` is bound rather than swapped in JavaScript by hand,
             which keeps the browser's own password manager attached to the
             field either way. --}}
        <div class="relative" x-data="{ show: false }">
            <input
                id="{{ $id }}"
                name="{{ $name }}"
                :type="show ? 'text' : 'password'"
                type="password"
                {{-- Passwords must never be repopulated from old input. --}}
                @if ($required) required @endif
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif
                @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                @if ($hasError) aria-invalid="true" @endif
                {{ $forwarded }}
                class="{{ $control }}"
            >

            <button type="button"
                    @click="show = ! show"
                    :aria-label="show ? 'Hide password' : 'Show password'"
                    aria-label="Show password"
                    class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-muted transition-colors hover:text-brass focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-brass">
                <svg x-show="! show" class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>

                <svg x-cloak x-show="show" class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243" />
                </svg>
            </button>
        </div>

    @else
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ $current }}"
            @if ($required) required @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($hasError) aria-invalid="true" @endif
            {{ $forwarded }}
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
