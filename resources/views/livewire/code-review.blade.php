{{-- resources/views/livewire/code-review.blade.php --}}
<div class="p-6">
    <h1 class="text-3xl font-bold text-center mb-6 text-white">🧠 Code Review AI</h1>

    {{-- Upload form --}}
    <form wire:submit.prevent="analyzeCode" class="flex items-center justify-center gap-4 mb-6">
        <input
            type="file"
            wire:model="uploadedFile"
            accept=".py,.js,.php,.java,.cpp,.txt"
            class="block w-1/2 text-sm text-gray-300 border border-gray-600 rounded-lg bg-gray-800
                   file:mr-4 file:py-2 file:px-4
                   file:rounded-lg file:border-0
                   file:text-sm file:font-semibold
                   file:bg-blue-600 file:text-white
                   hover:file:bg-blue-700" />

        <button
            type="submit"
            class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg shadow-md transition">
            Analizează
        </button>
    </form>

    {{-- Split screen --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        {{-- STÂNGA: Cod --}}
        <div class="bg-gray-900 text-gray-100 rounded-2xl p-4 shadow-lg border border-gray-700 overflow-auto h-[60vh]">
            <h2 class="text-lg font-semibold mb-3 text-blue-400">📄 Codul tău</h2>

            @if($code)
                <pre class="text-sm leading-relaxed whitespace-pre-wrap"><code>{{ $code }}</code></pre>
            @else
                <p class="text-gray-500 italic">Încarcă un fișier și apasă „Analizează” pentru a-l vedea aici.</p>
            @endif
        </div>

        {{-- DREAPTA: Review --}}
        <div class="relative bg-gray-800 text-gray-100 rounded-2xl p-4 shadow-lg border border-gray-700 overflow-auto h-[60vh]">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-green-400">🔍 Rezultatul analizei</h2>

                    {{-- Badge decizie --}}
                    @if($decision)
                        @if($decision === 'PASS')
                            <span class="inline-block px-2 py-1 rounded-md text-xs font-semibold bg-green-700/40 text-green-200 border border-green-600">
                                DECISION: PASS
                            </span>
                        @elseif($decision === 'WARN')
                            <span class="inline-block px-2 py-1 rounded-md text-xs font-semibold bg-yellow-700/30 text-yellow-200 border border-yellow-600">
                                DECISION: WARN
                            </span>
                        @else
                            <span class="inline-block px-2 py-1 rounded-md text-xs font-semibold bg-red-800/40 text-red-200 border border-red-600">
                                DECISION: BLOCK
                            </span>
                        @endif
                    @endif
                </div>

                {{-- Buton Quick Fix: disponibil doar dacă avem review și decizia != PASS --}}
                @if($review && $decision !== 'PASS')
                    <button wire:click="openQuickFix"
                            class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm shadow">
                        Propune rezolvare
                    </button>
                @endif
            </div>

            {{-- Mesaj pre-commit / erori --}}
            @if($precommitError)
                <div class="mb-3 rounded-lg border border-red-700 bg-red-900/40 text-red-200 px-3 py-2">
                    {{ $precommitError }}
                </div>
            @endif

            {{-- Loader analiză inițială --}}
            <div
                class="absolute inset-0 h-full flex items-start justify-center pt-24 bg-gray-900/70 rounded-2xl backdrop-blur-sm"
                wire:loading
                wire:target="analyzeCode">
                <div role="status" class="flex flex-col items-center">
                    <svg aria-hidden="true"
                         class="w-12 h-12 text-gray-200 animate-spin dark:text-gray-600 fill-blue-600"
                         viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                        <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                    </svg>
                    <p class="mt-3 text-green-300 font-semibold animate-pulse">Analizăm codul...</p>
                </div>
            </div>

            {{-- Conținutul review-ului --}}
            <div wire:loading.remove wire:target="analyzeCode">
                @if($review)
                    <pre class="text-sm leading-relaxed whitespace-pre-wrap"><code>{{ $review }}</code></pre>
                @else
                    <p class="text-gray-500 italic">Aici va apărea analiza AI după procesare.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Chat pe marginea review-ului (opțional) --}}
    <div class="mt-6">
        <livewire:review-chat />
    </div>

    {{-- Toast salvare reușită (auto-hide în 3s) --}}
    @if($saved)
        <div wire:poll.3s="clearSaved"
             class="fixed bottom-24 right-6 rounded-lg border border-green-700 bg-green-900/70 text-green-100 px-4 py-3 shadow-lg transition-opacity duration-300"
             aria-live="polite">
            ✅ Review-ul a fost salvat cu succes.
        </div>
    @endif

    {{-- BUTON SALVEAZĂ: blocat dacă DECISION este BLOCK sau nu există review --}}
    <div class="fixed bottom-6 right-6">
        <button
            wire:click="saveReview"
            wire:loading.attr="disabled"
            wire:target="saveReview"
            @class([
                'px-5 py-3 rounded-xl shadow-lg transition text-white font-medium',
                'bg-blue-600 hover:bg-blue-700' => $review && $decision !== 'BLOCK',
                'bg-gray-600 cursor-not-allowed' => !$review || $decision === 'BLOCK',
            ])>
            Commit
        </button>
    </div>

    {{-- Montează QuickFix (modalul) --}}
    <livewire:quick-fix />
</div>
