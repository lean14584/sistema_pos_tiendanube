<div class="p-8 max-w-5xl mx-auto" wire:poll.5s>
    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Mensajes</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Chat interno entre usuarios</p>
    </div>

    <div class="bg-gradient-to-b from-white to-gray-50/60 dark:from-gray-900 dark:to-gray-900/70 rounded-xl border border-gray-200 dark:border-gray-800 shadow-md shadow-gray-200/70 dark:shadow-black/40 flex h-[32rem] overflow-hidden">
        <div class="w-60 shrink-0 border-r border-gray-200 dark:border-gray-800 overflow-y-auto">
            @forelse ($users as $user)
                <a
                    href="{{ route('messages.index', $user) }}"
                    wire:navigate
                    class="flex items-center justify-between gap-2 px-4 py-3 border-b border-gray-50 dark:border-gray-800/60 last:border-0 transition-colors {{ $with?->id === $user->id
                        ? 'bg-indigo-50 dark:bg-indigo-500/10'
                        : 'hover:bg-gray-50 dark:hover:bg-gray-800/50' }}"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $user->name }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $user->role->label() }}</p>
                    </div>
                    @if ($user->unread_count > 0)
                        <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full bg-red-500 text-white text-[11px] font-semibold shrink-0">
                            {{ $user->unread_count }}
                        </span>
                    @endif
                </a>
            @empty
                <p class="p-4 text-sm text-gray-400 dark:text-gray-500">No hay otros usuarios activos.</p>
            @endforelse
        </div>

        <div class="flex-1 flex flex-col min-w-0">
            @if (! $with)
                <div class="flex-1 flex items-center justify-center text-center text-gray-400 dark:text-gray-500 p-8">
                    <div>
                        <x-heroicon-o-chat-bubble-left-right class="w-10 h-10 mx-auto mb-3 text-gray-300 dark:text-gray-700" />
                        <p class="text-sm">Elegí un usuario para escribirle.</p>
                    </div>
                </div>
            @else
                <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-800">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $with->name }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $with->role->label() }}</p>
                </div>

                <div class="flex-1 overflow-y-auto p-5 space-y-3" x-data x-init="$el.scrollTop = $el.scrollHeight" x-on:livewire:navigated.window="$el.scrollTop = $el.scrollHeight">
                    @forelse ($thread as $message)
                        @php $isMine = $message->sender_id === auth()->id(); @endphp
                        <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[75%] rounded-xl px-3.5 py-2 text-sm {{ $isMine
                                ? 'bg-indigo-600 text-white rounded-br-sm'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 rounded-bl-sm' }}">
                                <p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>
                                <p class="text-[10px] mt-1 opacity-70">{{ $message->created_at->format('d/m H:i') }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400 dark:text-gray-500 text-center">Todavía no hay mensajes con {{ $with->name }}.</p>
                    @endforelse
                </div>

                <form wire:submit="send" class="p-4 border-t border-gray-200 dark:border-gray-800 flex items-end gap-2">
                    <textarea
                        wire:model="body"
                        rows="1"
                        placeholder="Escribí un mensaje..."
                        class="flex-1 resize-none rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-500 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    ></textarea>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-indigo-500 px-4 py-2 text-sm font-medium text-white shadow-md shadow-indigo-600/30 hover:from-indigo-700 hover:to-indigo-600 active:scale-[0.98] transition-all"
                    >
                        Enviar
                    </button>
                </form>
                @error('body')
                    <p class="px-4 pb-3 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            @endif
        </div>
    </div>
</div>
