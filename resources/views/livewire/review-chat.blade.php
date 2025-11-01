<div class="bg-gray-900 border border-gray-800 rounded-2xl shadow-lg overflow-hidden">
    {{-- Header --}}
    <div class="px-4 py-3 bg-gray-800 border-b border-gray-700 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-200">💬 Discuții pe marginea review-ului</h3>
        @if(!$review)
            <span class="text-xs text-gray-400">Generează un review ca să începi conversația</span>
        @else
            <span class="text-xs text-gray-400">Context: {{ $fileName ?? 'fișier' }}</span>
        @endif
    </div>

    {{-- Thread (scrollabil) --}}
    <div class="p-4 space-y-3 max-h-[40vh] overflow-y-auto">
        @forelse($messages as $m)
            @if($m['role'] === 'user')
                <div class="flex justify-end">
                    <div class="max-w-[85%] rounded-xl px-3 py-2 bg-blue-600 text-white text-sm shadow">
                        {!! nl2br(e($m['text'])) !!}
                    </div>
                </div>
            @else
                <div class="flex justify-start">
                    <div class="max-w-[85%] rounded-xl px-3 py-2 bg-gray-800 text-gray-100 text-sm border border-gray-700 shadow">
                        {!! nl2br(e($m['text'])) !!}
                    </div>
                </div>
            @endif
        @empty
            <div class="text-sm text-gray-400">
                Scrie o întrebare despre punctele din review (ex: <em>„Poți explica mai clar punctul 2?”</em>).
            </div>
        @endforelse
    </div>

    {{-- Composer --}}
    <div class="px-4 py-3 bg-gray-800 border-t border-gray-700">
        <div class="flex items-end gap-2">
            <textarea
                wire:model.defer="input"
                class="flex-1 min-h-[42px] max-h-40 rounded-lg bg-gray-900 text-gray-100 text-sm border border-gray-700 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600 disabled:opacity-50"
                placeholder="{{ $review ? 'Întreabă despre un punct din review…' : 'Generează întâi un review' }}"
                @disabled(!$review || $sending)
            ></textarea>

            <button
                wire:click="ask"
                wire:loading.attr="disabled"
                wire:target="ask"
                @class([
                    'px-4 py-2 rounded-lg text-white text-sm transition',
                    'bg-blue-600 hover:bg-blue-700' => $review && !$sending,
                    'bg-gray-600 cursor-not-allowed' => !$review || $sending,
                ])
            >
                Trimite
            </button>
        </div>

        @if($sending)
            <div class="mt-2 text-xs text-blue-300 animate-pulse">Se generează răspunsul…</div>
        @endif
    </div>
</div>
