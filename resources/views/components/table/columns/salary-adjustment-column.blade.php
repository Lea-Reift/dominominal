@php
    use Filament\Support\Enums\Alignment;

    $isDisabled = $isDisabled();
    $state = $getState();
    $mask = $getMask();

    $alignment = $getAlignment() ?? Alignment::Start;

    if (! $alignment instanceof Alignment) {
        $alignment = filled($alignment) ? (Alignment::tryFrom($alignment) ?? $alignment) : null;
    }

    if (filled($mask)) {
        $type = 'text';
    } else {
        $type = $getType();
    }
@endphp

<div
    x-data="{
        error: undefined,

        isEditing: false,

        isLoading: false,

        name: @js($getName()),

        recordKey: @js($recordKey),

        state: @js($state),

        tooltip: @js($getTooltip()),
    }"
    x-init="
        () => {
            Livewire.hook('commit', ({ component, commit, succeed, fail, respond }) => {
                succeed(({ snapshot, effect }) => {
                    $nextTick(() => {
                        if (component.id !== @js($this->getId())) {
                            return
                        }

                        if (isEditing) {
                            return
                        }

                        if (! $refs.newState) {
                            return
                        }

                        let newState = $refs.newState.value.replaceAll('\\'+String.fromCharCode(34), String.fromCharCode(34))

                        if (state === newState) {
                            return
                        }

                        state = newState
                    })
                })
            })
        }
    "
    {{
        $attributes
            ->merge($getExtraAttributes(), escape: false)
            ->class([
                'fi-ta-text-input w-full min-w-48',
                'px-3 py-4' => ! $isInline(),
            ])
    }}
>
    <input
        type="hidden"
        value="{{ str($state)->replace('"', '\\"') }}"
        x-ref="newState"
    />

    <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
        {{ $getLabel() }}
        @if ($getHint())
            <div class="fi-sc-component">
                <span class="fi-sc-text">{{ $getHint() }}</span>
            </div>
        @endif
    </span>

    <x-filament::input.wrapper
        :alpine-disabled="'isLoading || ' . \Illuminate\Support\Js::from($isDisabled)"
        alpine-valid="error === undefined"
        x-tooltip="error !== undefined ? { content: error, theme: $store.theme, delay: [100, 50] } : tooltip ? { content: tooltip, theme: $store.theme, delay: [100, 50] } : false"
        x-on:click.stop.prevent=""
    >
        {{-- format-ignore-start --}}
        <x-filament::input
            :disabled="$isDisabled"
            :input-mode="$getInputMode()"
            :placeholder="$getPlaceholder()"
            :step="$getStep()"
            :type="$type"
            :x-bind:disabled="$isDisabled ? null : 'isLoading'"
            x-model="state"
            x-on:blur="isEditing = false"
            x-on:focus="isEditing = true"
            :attributes="
                \Filament\Support\prepare_inherited_attributes(
                    $getExtraInputAttributeBag()
                        ->merge([
                            'x-on:change' . ($type === 'number' ? '.debounce.1s' : null) => '
                                isLoading = true

                                const response = await $wire.updateTableColumnState(
                                    name,
                                    recordKey,
                                    $event.target.value,
                                )

                                error = response?.error ?? undefined

                                if (! error) {
                                    state = response
                                }

                                isLoading = false
                            ',
                            'x-mask' . ($mask instanceof \Filament\Support\RawJs ? ':dynamic' : '') => filled($mask) ? $mask : null,
                        ])
                        ->class([
                            match ($alignment) {
                                Alignment::Start => 'text-start',
                                Alignment::Center => 'text-center',
                                Alignment::End => 'text-end',
                                Alignment::Left => 'text-left',
                                Alignment::Right => 'text-right',
                                Alignment::Justify, Alignment::Between => 'text-justify',
                                default => $alignment,
                            },
                        ])
                )
            "
        />
        {{-- format-ignore-end --}}
    </x-filament::input.wrapper>
</div>
