<div class="p-6">
    <h1 class="text-3xl font-bold text-center mb-6 text-white">🗂️ Reviewed Codes</h1>

    {{-- Toolbar: search + perPage --}}
    <div class="flex flex-col md:flex-row items-center gap-4 mb-6">
        <input
            type="text"
            wire:model.debounce.400ms="search"
            placeholder="Caută după nume fișier, cod sau review..."
            class="w-full md:flex-1 bg-gray-800 text-gray-200 border border-gray-700 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
        />

        <div class="flex items-center gap-2">
            <label class="text-gray-300 text-sm">Pe pagină</label>
            <select wire:model="perPage"
                class="bg-gray-800 text-gray-200 border border-gray-700 rounded-lg px-3 py-2">
                <option>10</option>
                <option>20</option>
                <option>50</option>
            </select>
        </div>
    </div>

    {{-- Listă în tabel --}}
    <div class="bg-gray-900 border border-gray-700 rounded-2xl overflow-hidden shadow-lg">
        <table class="min-w-full divide-y divide-gray-700">
            <thead class="bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">#</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">Fișier</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">Tip</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">Dimensiune</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">Data</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-300 uppercase tracking-wider">Acțiuni</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($items as $idx => $item)
                    <tr class="hover:bg-gray-800/70">
                        <td class="px-4 py-3 text-gray-400">{{ ($items->currentPage()-1)*$items->perPage() + $idx + 1 }}</td>
                        <td class="px-4 py-3 text-gray-100">{{ $item->file_name ?? '(fără nume)' }}</td>
                        <td class="px-4 py-3 text-gray-300">{{ $item->mime_type ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-300">
                            @if(!is_null($item->file_size))
                                {{ number_format($item->file_size / 1024, 1) }} KB
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-300">{{ $item->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button wire:click="show({{ $item->id }})"
                                    class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm">
                                    Vezi
                                </button>
                                <button wire:click="openNewCommit({{ $item->id }})"
                                    class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-sm">
                                    New Commit
                                </button>
                                <button wire:click="delete({{ $item->id }})"
                                    class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white rounded-md text-sm">
                                    Șterge
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-400">
                            Nu există review-uri salvate încă.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-4 py-3 bg-gray-900">
            {{ $items->links() }}
        </div>
    </div>

    {{-- MODAL Detalii --}}
    @if($showModal)
        <div class="fixed inset-0 z-40 bg-black/60"></div>

        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="w-full max-w-5xl bg-gray-900 border border-gray-700 rounded-2xl shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3 bg-gray-800 border-b border-gray-700">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Detalii review</h3>
                        <p class="text-sm text-gray-400">
                            {{ $selectedFile }} <span class="text-gray-500">({{ $selectedMime }})</span>
                        </p>
                    </div>
                    
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-5">
                    <div class="bg-gray-950 border border-gray-800 rounded-xl p-3 overflow-auto h-[60vh]">
                        <h4 class="text-blue-400 font-semibold mb-2">📄 Cod</h4>
                        <pre class="text-xs md:text-sm text-gray-200 whitespace-pre-wrap leading-relaxed"><code>{{ $selectedCode }}</code></pre>
                    </div>

                    <div class="bg-gray-950 border border-gray-800 rounded-xl p-3 overflow-auto h-[60vh]">
                        <h4 class="text-green-400 font-semibold mb-2">🔍 Review</h4>
                        <pre class="text-xs md:text-sm text-gray-200 whitespace-pre-wrap leading-relaxed"><code>{{ $selectedReview }}</code></pre>
                    </div>
                </div>

                <div class="px-5 py-3 bg-gray-800 border-t border-gray-700 flex justify-end">
                    <button wire:click="closeModal"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg">
                        Închide
                    </button>
                </div>
            </div>
        </div>
    @endif

    
    <livewire:new-commit />
</div>
