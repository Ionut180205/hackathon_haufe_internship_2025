<div>
    @if($open)
        {{-- Backdrop --}}
        <div class="fixed inset-0 z-40 bg-black/60"></div>

        {{-- Modal --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" wire:keydown.escape.window="close">
            <div class="w-full max-w-3xl md:max-w-4xl bg-gray-900 border border-gray-700 rounded-2xl shadow-2xl overflow-hidden max-h-[85vh] flex flex-col">
                {{-- Header sticky --}}
                <div class="sticky top-0 z-10 flex items-center justify-between px-5 py-3 bg-gray-800/95 backdrop-blur border-b border-gray-700">
                    <div>
                        <h3 class="text-lg font-semibold text-white">🔧 Propune rezolvare</h3>
                        <p class="text-xs text-gray-400">Context: <span class="font-mono text-gray-200">{{ $fileName }}</span></p>
                    </div>
                    <button wire:click="close" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white rounded-md">Închide</button>
                </div>

                {{-- Body scrollabil --}}
                <div class="flex-1 overflow-y-auto p-5 space-y-4">
                    <div class="rounded-xl border border-gray-800 bg-gray-950/70 p-3">
                        <h4 class="text-sm font-semibold text-gray-200 mb-2">Rezumat probleme (din review)</h4>
                        <pre class="text-xs text-gray-300 whitespace-pre-wrap leading-relaxed max-h-40 overflow-auto">{{ $review }}</pre>
                    </div>

                    <div class="flex items-center gap-2">
                        <button wire:click="generateFix" wire:loading.attr="disabled" wire:target="generateFix"
                                class="px-4 py-2 rounded-lg text-white bg-emerald-600 hover:bg-emerald-700">
                            Generează cod reparat
                        </button>
                        @if($generating)
                            <span class="text-sm text-emerald-300 animate-pulse">Se generează propunerea…</span>
                        @endif
                    </div>

                    @if($error)
                        <div class="rounded-lg border border-red-700 bg-red-900/40 text-red-200 px-3 py-2">
                            {{ $error }}
                        </div>
                    @endif

                    {{-- Notă: în acest UX aplicăm automat fixul și închidem modalul, deci nu mai afișăm preview sau buton de aplicare. --}}
                </div>

                {{-- Footer (gol în acest UX simplificat) --}}
                <div class="sticky bottom-0 z-10 px-5 py-3 bg-gray-800/95 backdrop-blur border-t border-gray-700 flex justify-end gap-2">
                    {{-- intentionally empty --}}
                </div>
            </div>
        </div>
    @endif
</div>
