{{-- resources/views/livewire/new-commit.blade.php --}}
<div>
    @if($open)
        {{-- Backdrop --}}
        <div class="fixed inset-0 z-40 bg-black/60"></div>

        {{-- Modal container (înălțime limitată, centrat) --}}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            wire:keydown.escape.window="close" tabindex="0"
        >
            <div
                class="w-full max-w-3xl md:max-w-4xl bg-gray-900 border border-gray-700 rounded-2xl shadow-2xl overflow-hidden
                       max-h-[85vh] flex flex-col"
                role="dialog" aria-modal="true" aria-labelledby="new-commit-title"
            >
                {{-- Header sticky --}}
                <div class="sticky top-0 z-10 flex items-center justify-between px-5 py-3 bg-gray-800/95 backdrop-blur border-b border-gray-700">
                    <div>
                        <h3 id="new-commit-title" class="text-lg font-semibold text-white">
                            New Commit <span class="text-gray-400 text-sm">(review pe diferențe)</span>
                        </h3>
                        <p class="text-sm text-gray-400">
                            Bază: <span class="font-mono text-gray-200">{{ $baseFile }}</span>
                            @if($baseId) <span class="text-gray-500"> (#{{ $baseId }})</span> @endif
                        </p>
                    </div>
                    <button wire:click="close"
                            class="px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white rounded-md transition">
                        Închide
                    </button>
                </div>

                {{-- Body scrollabil --}}
                <div class="flex-1 overflow-y-auto p-5 space-y-5">
                    {{-- Upload noua versiune --}}
                    <div>
                        <label class="block text-sm text-gray-300 mb-1">Încarcă noua versiune a fișierului</label>
                        <input type="file" wire:model="newFile"
                               accept=".py,.js,.php,.java,.cpp,.txt"
                               class="w-full bg-gray-800 text-gray-200 border border-gray-700 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600" />
                        @error('newFile')
                            <p class="text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2">
                        <button wire:click="analyze"
                                wire:loading.attr="disabled"
                                wire:target="analyze,newFile"
                                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">
                            Analizează diferențele
                        </button>

                        @if($analyzing)
                            <span class="text-sm text-emerald-300 animate-pulse">Se analizează...</span>
                        @endif
                    </div>

                    {{-- Previzualizare diff (înălțime limitată + scroll) --}}
                    @if($diffText)
                        <div class="rounded-xl border border-blue-800 bg-gradient-to-b from-blue-950/50 to-blue-900/30 shadow-inner">
                            <div class="flex items-center justify-between px-3 py-2 border-b border-blue-800/60">
                                <h4 class="text-blue-300 font-semibold">Δ Diff</h4>
                                <span class="text-[11px] text-blue-300/80">unified</span>
                            </div>
                            <div class="p-3">
                                <pre class="text-[11px] md:text-xs text-blue-100 whitespace-pre leading-relaxed
                                            rounded-lg bg-blue-950/40 border border-blue-900/70 shadow-inner
                                            h-56 md:h-72 overflow-auto scrollbar-thin scrollbar-thumb-blue-800 scrollbar-track-transparent">
<code>{{ $diffText }}</code></pre>
                            </div>
                        </div>
                    @endif

                    {{-- Rezultatul review-ului (înălțime limitată + scroll) --}}
                    @if($reviewText)
                        <div class="rounded-xl border border-gray-800 bg-gray-950/70 shadow-inner">
                            <div class="flex items-center justify-between px-3 py-2 border-b border-gray-800/70">
                                <h4 class="text-green-300 font-semibold">Rezultatul analizei</h4>
                                <div>
                                    @if($decision === 'PASS')
                                        <span class="px-2 py-0.5 rounded bg-green-700/40 border border-green-600 text-green-200 text-xs">DECISION: PASS</span>
                                    @elseif($decision === 'WARN')
                                        <span class="px-2 py-0.5 rounded bg-yellow-700/30 border border-yellow-600 text-yellow-200 text-xs">DECISION: WARN</span>
                                    @elseif($decision === 'BLOCK')
                                        <span class="px-2 py-0.5 rounded bg-red-800/40 border border-red-600 text-red-200 text-xs">DECISION: BLOCK</span>
                                    @endif
                                </div>
                            </div>

                            @if($precommitError)
                                <div class="m-3 rounded border border-red-700 bg-red-900/40 text-red-200 px-3 py-2">
                                    {{ $precommitError }}
                                </div>
                            @endif

                            <div class="p-3">
                                <pre class="text-[12px] md:text-sm text-gray-200 whitespace-pre-wrap leading-relaxed
                                            rounded-lg bg-gray-900/60 border border-gray-800 shadow-inner
                                            h-56 md:h-72 overflow-auto scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-transparent"><code>{{ $reviewText }}</code></pre>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Footer sticky --}}
                <div class="sticky bottom-0 z-10 px-5 py-3 bg-gray-800/95 backdrop-blur border-t border-gray-700 flex justify-end gap-2">
                    <button wire:click="close"
                            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition">
                        Renunță
                    </button>
                    <button wire:click="save"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            @class([
                                'px-4 py-2 rounded-lg text-white transition',
                                'bg-blue-600 hover:bg-blue-700' => $reviewText && $decision !== 'BLOCK',
                                'bg-gray-600 cursor-not-allowed' => !$reviewText || $decision === 'BLOCK',
                            ])>
                        Salvează commit
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
