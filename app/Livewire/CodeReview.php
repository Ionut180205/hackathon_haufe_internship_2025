<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Http;
use App\Models\CodeReviewEntry;

class CodeReview extends Component
{
    use WithFileUploads;

    public $uploadedFile;
    public $code = '';
    public $review = '';
    public $loading = false;
    public $saved = false;

    // Pre-commit
    public $decision = ''; // PASS | WARN | BLOCK
    public $precommitError = '';

    protected $listeners = [
        // QuickFix aplică automat codul reparat aici
        'apply-fixed-code' => 'applyFixedCode',
    ];

    public function render()
    {
        return view('livewire.code-review');
    }
    public function openQuickFix(): void
{
    // deschidem modalul doar dacă există un review și nu e deja PASS
    if (!$this->review || $this->decision === 'PASS') {
        return;
    }

    $fileName = $this->uploadedFile?->getClientOriginalName() ?? '';

    // trimite contextul către componenta QuickFix
    $this->dispatch('open-quick-fix', code: $this->code, review: $this->review, fileName: $fileName);
}


    public function analyzeCode()
    {
        $this->validate([
            'uploadedFile' => 'required|file|max:2048',
        ]);

        // Afișează codul imediat
        $this->code = file_get_contents($this->uploadedFile->getRealPath());
        $this->review = '';
        $this->decision = '';
        $this->precommitError = '';
        $this->saved = false;
        $this->loading = true;

        try {
            // ===== OLLAMA local =====
            $base  = rtrim(env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'), '/');
            $model = env('OLLAMA_MODEL', 'llama3:3b');

            $system = 'Ești un expert în code review. Răspunde în română. '
                    . 'Structurează pe: Probleme, De ce contează, Cum se repară. '
                    . 'LA FINAL, pe o linie separată, scrie EXACT: DECISION: PASS sau WARN sau BLOCK '
                    . '(BLOCK pentru vulnerabilități/erori majore; WARN pentru probleme minore; PASS dacă e ok).';

            $user = "Fă un code review clar și acționabil pentru următorul cod:\n\n" . $this->code;

            $payload = [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
                'stream' => false,
                'options' => [
                    'temperature' => 0.6,
                    'num_ctx'     => 8192,
                ],
            ];

            $response = Http::timeout(90)->post($base . '/api/chat', $payload);

            if ($response->failed()) {
                $this->review = "Eroare Ollama: " . $response->body();
            } else {
                $data = $response->json();
                $text = $data['message']['content'] ?? '';

                // Extrage DECISION: PASS/WARN/BLOCK
                $this->decision = $this->extractDecision($text) ?: 'WARN';

                // Păstrează review-ul fără linia DECISION pentru display
                $this->review = $this->stripDecisionLine($text);
            }

            // (opțional) notifică ReviewChat că există un review nou
            $this->dispatch('review-updated', review: $this->review, code: $this->code, file: ($this->uploadedFile?->getClientOriginalName() ?? ''));
        } catch (\Exception $e) {
            $this->review = "A apărut o eroare: " . $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    /**
     * QuickFix a generat codul și l-a aplicat automat.
     * Setăm un review minim și DECISION=PASS ca să poți da Commit.
     */
    public function applyFixedCode(string $code): void
    {
        // pune codul reparat în containerul din stânga
        $this->code = $code;

        // setează un review minim pentru a permite Commit
        $this->review = "🔧 Auto-Fix aplicat pe baza review-ului precedent.\n"
                      . "Notă: Nu a fost rulată o re-analiză automată.";

        // activăm butonul de Commit
        $this->decision = 'PASS';
        $this->precommitError = '';
    }

    public function saveReview()
    {
        // Pre-commit gate
        if ($this->decision === 'BLOCK') {
            $this->precommitError = 'Commit blocat: codul are probleme majore. Corectează și re-analizează înainte de salvare.';
            return;
        }

        if (!$this->code || !$this->review) {
            $this->addError('uploadedFile', 'Nu există review generat sau codul este gol.');
            return;
        }

        CodeReviewEntry::create([
            'file_name' => $this->uploadedFile?->getClientOriginalName(),
            'mime_type' => $this->uploadedFile?->getMimeType(),
            'file_size' => $this->uploadedFile?->getSize(),
            'code'      => $this->code,
            'review'    => $this->review . "\n\n[DECISION: {$this->decision}]",
        ]);

        $this->saved = true;
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

    public function clearSaved(){
        $this->saved = false;
    }
}
