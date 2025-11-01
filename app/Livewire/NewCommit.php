<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\CodeReviewEntry;

class NewCommit extends Component
{
    use WithFileUploads;

    public bool $open = false;

    // baza pe care o actualizăm (nu mai creăm linie nouă)
    public ?int $baseId = null;
    public ?string $baseFile = null;
    public ?string $baseCode = null;

    // fișierul nou
    public $newFile;
    public ?string $newCode = null;

    // rezultate
    public string $diffText = '';
    public string $reviewText = '';
    public string $decision = ''; // PASS | WARN | BLOCK
    public string $precommitError = '';
    public bool $analyzing = false;
    public bool $saving = false;

    protected $listeners = [
        'open-new-commit' => 'openForBase',
    ];

    public function render()
    {
        return view('livewire.new-commit');
    }

    /**
     * Deschide modalul pentru entry-ul selectat din ReviewedCodes.
     */
    public function openForBase(int $id): void
    {
        $entry = CodeReviewEntry::findOrFail($id);

        $this->resetState();

        $this->baseId   = $entry->id;
        $this->baseFile = $entry->file_name;   // menținem numele original
        $this->baseCode = $entry->code;        // pentru diff

        $this->open = true;
    }

    public function close(): void
    {
        $this->resetState();
        $this->open = false;
    }

    public function analyze(): void
    {
        $this->validate([
            'newFile' => 'required|file|max:4096|mimes:php,js,py,java,cpp,txt',
        ], [
            'newFile.required' => 'Încarcă noua versiune a fișierului.',
        ]);

        $this->analyzing = true;
        $this->reviewText = '';
        $this->decision = '';
        $this->precommitError = '';
        $this->diffText = '';

        try {
            // Citește codul nou
            $this->newCode = file_get_contents($this->newFile->getRealPath());

            // (opțional, recomandat): impune același nume ca baza, ca să nu actualizezi alt fișier
            // Dacă vrei strict același nume, decomentează:
            // if ($this->newFile->getClientOriginalName() !== $this->baseFile) {
            //     $this->reviewText = "Numele fișierului încărcat diferă de baza selectată.";
            //     $this->decision   = 'WARN';
            //     $this->analyzing  = false;
            //     return;
            // }

            // Diff unificat
            $this->diffText = $this->buildUnifiedDiff(
                $this->baseCode ?? '',
                $this->newCode ?? '',
                $this->baseFile ?? 'file'
            );

            if (!$this->hasMeaningfulDiff($this->diffText)) {
                $this->reviewText = "Nu am detectat schimbări semnificative față de versiunea de bază (#{$this->baseId}).";
                $this->decision   = 'PASS';
                $this->analyzing  = false;
                return;
            }

            // ===== OLLAMA local =====
            $base  = rtrim(env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'), '/');
            $model = env('OLLAMA_MODEL', 'llama3:3b');

            $system = 'Ești un expert în code review. Răspunde în română. '
                    . 'Structurează pe: Probleme, De ce contează, Cum se repară. '
                    . 'LA FINAL, pe o linie separată, scrie EXACT: DECISION: PASS sau WARN sau BLOCK '
                    . '(BLOCK pentru vulnerabilități/erori majore; WARN pentru probleme minore; PASS dacă e ok). '
                    . 'Analizează STRICT schimbările primite ca unified diff.';

            $user = "Analizează DOAR schimbările din acest unified diff, fără a comenta părțile neschimbate.\n\n"
                  . "```diff\n{$this->diffText}\n```";

            $payload = [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
                'stream' => false,
                'options' => [
                    'temperature' => 0.35,
                    'num_ctx'     => 8192,
                ],
            ];

            $response = Http::timeout(90)->post($base . '/api/chat', $payload);

            if ($response->failed()) {
                $this->reviewText = "Eroare Ollama: " . $response->body();
            } else {
                $data = $response->json();
                $text = $data['message']['content'] ?? '';

                $this->decision   = $this->extractDecision($text) ?: 'WARN';
                $this->reviewText = $this->stripDecisionLine($text);
            }
        } catch (\Throwable $e) {
            $this->reviewText = "A apărut o eroare: " . $e->getMessage();
        } finally {
            $this->analyzing = false;
        }
    }

    /**
     * UPDATE pe rândul existent (nu create).
     * - păstrăm file_name original (ca în Git: același path)
     * - actualizăm code, review, mime, size
     * - nu schimbăm user_id
     */
    public function save(): void
    {
        if ($this->decision === 'BLOCK') {
            $this->precommitError = 'Commit blocat: codul are probleme majore. Corectează și re-analizează înainte de salvare.';
            return;
        }

        if (!$this->baseId) {
            $this->precommitError = 'Nu există o bază selectată pentru actualizare.';
            return;
        }

        // fallback: dacă s-a pierdut conținutul între request-uri, recitește-l
        if (!$this->newCode && $this->newFile) {
            try {
                $this->newCode = file_get_contents($this->newFile->getRealPath());
            } catch (\Throwable $e) {
                // continuăm pe validarea de mai jos
            }
        }

        if (!$this->newCode || !$this->reviewText) {
            $this->precommitError = 'Nu există review generat sau fișierul este gol.';
            return;
        }

        $this->saving = true;

        try {
            $entry = CodeReviewEntry::findOrFail($this->baseId);

            // (opțional) protejează-ți înregistrările doar ale user-ului curent
            if ($entry->user_id && $entry->user_id !== Auth::id()) {
                $this->precommitError = 'Nu poți actualiza această înregistrare.';
                $this->saving = false;
                return;
            }

            // păstrăm numele inițial; doar metadatele și conținutul se schimbă
            $entry->code      = $this->newCode;
            $entry->review    = $this->reviewText . "\n\n[DECISION: {$this->decision}]";
            $entry->mime_type = $this->newFile?->getMimeType();
            $entry->file_size = $this->newFile?->getSize();
            // $entry->file_name rămâne același
            $entry->save();

            // anunță lista să se refacă și închide modalul
            $this->dispatch('new-commit-saved');
            $this->close();
        } catch (\Throwable $e) {
            $this->precommitError = "Eroare la actualizare: " . $e->getMessage();
        } finally {
            $this->saving = false;
        }
    }

    // ---------- utilitare ----------

    private function resetState(): void
    {
        $this->baseId = null;
        $this->baseFile = null;
        $this->baseCode = null;

        $this->newFile = null;
        $this->newCode = null;

        $this->diffText = '';
        $this->reviewText = '';
        $this->decision = '';
        $this->precommitError = '';
        $this->analyzing = false;
        $this->saving = false;
    }

    private function extractDecision(string $text): string
    {
        if (preg_match('/DECISION:\s*(PASS|WARN|BLOCK)\s*$/i', trim($text), $m)) {
            return strtoupper($m[1]);
        }
        return '';
    }

    private function stripDecisionLine(string $text): string
    {
        return preg_replace('/\n?DECISION:\s*(PASS|WARN|BLOCK)\s*$/i', '', trim($text));
    }

    private function hasMeaningfulDiff(string $diff): bool
    {
        foreach (preg_split("/\r\n|\n|\r/", $diff) as $line) {
            if ($line === '' || $line[0] === '@' || str_starts_with($line, '---') || str_starts_with($line, '+++')) {
                continue;
            }
            if ($line[0] === '+' || $line[0] === '-') {
                return true;
            }
        }
        return false;
    }

    /**
     * Diff unificat simplu (fără pachet extern).
     */
    private function buildUnifiedDiff(string $old, string $new, string $fileName = 'file'): string
    {
        $a = preg_split("/\r\n|\n|\r/", $old);
        $b = preg_split("/\r\n|\n|\r/", $new);

        $L = $this->lcsMatrix($a, $b);
        $ops = $this->backtrackOps($L, $a, $b);

        $context = 3;
        $hunks = [];
        $buf = [];
        $inChange = false;

        $iOld = 0; $iNew = 0;
        $hStartOld = 0; $hStartNew = 0;
        $lenOld = 0; $lenNew = 0;
        $ctxAfter = 0;

        $emit = function() use (&$hunks, &$buf, &$hStartOld, &$hStartNew, &$lenOld, &$lenNew) {
            if ($lenOld === 0 && $lenNew === 0) {
                $buf = [];
                return;
            }
            $hunks[] = [
                'start_old' => max(1, $hStartOld),
                'len_old'   => max(1, $lenOld),
                'start_new' => max(1, $hStartNew),
                'len_new'   => max(1, $lenNew),
                'lines'     => $buf,
            ];
            $buf = [];
            $hStartOld = $hStartNew = 0;
            $lenOld = $lenNew = 0;
        };

        foreach ($ops as $op) {
            if ($op === 'equal') {
                if ($inChange) {
                    if ($ctxAfter < $context) {
                        $buf[] = ['type' => ' ', 'text' => $a[$iOld]];
                        $iOld++; $iNew++;
                        $lenOld++; $lenNew++;
                        $ctxAfter++;
                        continue;
                    } else {
                        $emit();
                        $inChange = false;
                        $ctxAfter = 0;
                    }
                }
                $iOld++; $iNew++;
            } elseif ($op === 'delete') {
                if (!$inChange) {
                    $inChange = true;
                    $ctxAfter = 0;
                    $hStartOld = max(1, $iOld + 1 - $context);
                    $hStartNew = max(1, $iNew + 1 - $context);
                    for ($k = max(0, $iOld - $context); $k < $iOld; $k++) {
                        $buf[] = ['type' => ' ', 'text' => $a[$k]];
                        $lenOld++; $lenNew++;
                    }
                }
                $buf[] = ['type' => '-', 'text' => $a[$iOld]];
                $iOld++; $lenOld++;
                $ctxAfter = 0;
            } else { // insert
                if (!$inChange) {
                    $inChange = true;
                    $ctxAfter = 0;
                    $hStartOld = max(1, $iOld + 1 - $context);
                    $hStartNew = max(1, $iNew + 1 - $context);
                    for ($k = max(0, $iOld - $context); $k < $iOld; $k++) {
                        $buf[] = ['type' => ' ', 'text' => $a[$k]];
                        $lenOld++; $lenNew++;
                    }
                }
                $buf[] = ['type' => '+', 'text' => $b[$iNew]];
                $iNew++; $lenNew++;
                $ctxAfter = 0;
            }
        }
        if ($inChange) {
            $emit();
        }

        $header = "--- a/{$fileName}\n+++ b/{$fileName}\n";
        $body = '';
        foreach ($hunks as $h) {
            $body .= "@@ -{$h['start_old']},{$h['len_old']} +{$h['start_new']},{$h['len_new']} @@\n";
            foreach ($h['lines'] as $ln) {
                $body .= $ln['type'] . $ln['text'] . "\n";
            }
        }

        return rtrim($header . $body);
    }

    private function lcsMatrix(array $a, array $b): array
    {
        $m = count($a); $n = count($b);
        $L = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));
        for ($i = $m - 1; $i >= 0; $i--) {
            for ($j = $n - 1; $j >= 0; $j--) {
                $L[$i][$j] = ($a[$i] === $b[$j]) ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
            }
        }
        return $L;
    }

    private function backtrackOps(array $L, array $a, array $b): array
    {
        $ops = [];
        $i = 0; $j = 0;
        $m = count($a); $n = count($b);
        while ($i < $m && $j < $n) {
            if ($a[$i] === $b[$j]) {
                $ops[] = 'equal'; $i++; $j++;
            } elseif ($L[$i + 1][$j] >= $L[$i][$j + 1]) {
                $ops[] = 'delete'; $i++;
            } else {
                $ops[] = 'insert'; $j++;
            }
        }
        while ($i < $m) { $ops[] = 'delete'; $i++; }
        while ($j < $n) { $ops[] = 'insert'; $j++; }
        return $ops;
    }
}
