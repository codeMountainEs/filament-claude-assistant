<x-filament-panels::page>
    {{-- ── API key warning ─────────────────────────────────────────────────────── --}}
    @php $hasKey = !empty(config('services.anthropic.key')); @endphp

    @unless($hasKey)
        <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-300">
            ⚠️ <strong>ANTHROPIC_API_KEY</strong> not set. Add it to your <code>.env</code> and run
            <code>php artisan config:cache</code>.
        </div>
    @endunless

    {{-- ── Chat container ──────────────────────────────────────────────────────── --}}
    <div
        class="flex flex-col rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900"
        style="height: calc(100vh - 14rem); min-height: 400px;"
        x-data="{
            scrollToBottom() {
                this.$nextTick(() => {
                    const el = this.$refs.messageList;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            }
        }"
        x-init="scrollToBottom()"
        @message-sent.window="scrollToBottom()"
    >

        {{-- ── Message list ─────────────────────────────────────────────────────── --}}
        <div
            x-ref="messageList"
            class="flex-1 overflow-y-auto space-y-4 p-4 md:p-6"
        >
            @foreach ($messages as $msg)
                @if ($msg['role'] === 'assistant')
                    {{-- Assistant bubble --}}
                    <div class="flex items-start gap-3">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 text-white shadow-sm">
                            <x-heroicon-s-sparkles class="h-4 w-4"/>
                        </div>
                        <div class="max-w-[80%] rounded-2xl rounded-tl-sm bg-gray-100 px-4 py-3 text-sm leading-relaxed text-gray-800 shadow-sm dark:bg-gray-800 dark:text-gray-100">
                            {!! nl2br(e($msg['text'])) !!}
                        </div>
                    </div>
                @else
                    {{-- User bubble --}}
                    <div class="flex items-start justify-end gap-3">
                        <div class="max-w-[80%] rounded-2xl rounded-tr-sm bg-gradient-to-br from-violet-600 to-indigo-600 px-4 py-3 text-sm leading-relaxed text-white shadow-sm">
                            {!! nl2br(e($msg['text'])) !!}
                        </div>
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-200 text-gray-600 shadow-sm dark:bg-gray-700 dark:text-gray-300">
                            <x-heroicon-s-user class="h-4 w-4"/>
                        </div>
                    </div>
                @endif
            @endforeach

            {{-- Typing indicator --}}
            <div wire:loading wire:target="send" class="flex items-start gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 text-white shadow-sm">
                    <x-heroicon-s-sparkles class="h-4 w-4"/>
                </div>
                <div class="rounded-2xl rounded-tl-sm bg-gray-100 px-4 py-3 shadow-sm dark:bg-gray-800">
                    <div class="flex gap-1">
                        <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400 [animation-delay:0ms]"></span>
                        <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400 [animation-delay:150ms]"></span>
                        <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400 [animation-delay:300ms]"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Suggestions ──────────────────────────────────────────────────────── --}}
        @php $suggestions = $this->getPlugin()->getSuggestions(); @endphp
        @if (count($suggestions) > 0 && count($messages) <= 1)
            <div class="flex flex-wrap gap-2 border-t border-gray-100 px-4 py-3 dark:border-gray-700">
                @foreach (array_slice($suggestions, 0, 5) as $suggestion)
                    <button
                        wire:click="$set('input', @js($suggestion))"
                        type="button"
                        class="rounded-full border border-gray-200 bg-white px-3 py-1 text-xs text-gray-600 transition hover:border-violet-400 hover:bg-violet-50 hover:text-violet-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-violet-500 dark:hover:bg-violet-900/30 dark:hover:text-violet-300"
                    >
                        {{ $suggestion }}
                    </button>
                @endforeach
            </div>
        @endif

        {{-- ── Input bar ────────────────────────────────────────────────────────── --}}
        <div class="border-t border-gray-200 p-3 dark:border-gray-700">
            <form
                wire:submit.prevent="send"
                x-on:submit="$dispatch('message-sent')"
                class="flex items-end gap-2"
            >
                <textarea
                    wire:model="input"
                    rows="1"
                    placeholder="Write a message…"
                    class="flex-1 resize-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 shadow-sm transition focus:border-violet-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-violet-200 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-500 dark:focus:border-violet-500 dark:focus:bg-gray-750 dark:focus:ring-violet-800"
                    x-data
                    x-on:keydown.enter.prevent="if (!$event.shiftKey) $el.closest('form').dispatchEvent(new Event('submit', {bubbles:true, cancelable:true}))"
                    x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 160) + 'px'"
                ></textarea>

                {{-- Send --}}
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:target="send"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-600 to-indigo-600 text-white shadow-sm transition hover:from-violet-500 hover:to-indigo-500 disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="send">
                        <x-heroicon-s-paper-airplane class="h-4 w-4"/>
                    </span>
                    <span wire:loading wire:target="send">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                    </span>
                </button>

                {{-- Clear --}}
                @if (count($messages) > 1)
                    <button
                        type="button"
                        wire:click="clearChat"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:border-red-300 hover:text-red-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:border-red-600 dark:hover:text-red-400"
                        title="Clear chat"
                    >
                        <x-heroicon-o-trash class="h-4 w-4"/>
                    </button>
                @endif
            </form>

            <p class="mt-1.5 text-center text-[10px] text-gray-400 dark:text-gray-600">
                Powered by <span class="font-medium">Anthropic Claude</span> · Enter to send · Shift+Enter for new line
            </p>
        </div>
    </div>
</x-filament-panels::page>
