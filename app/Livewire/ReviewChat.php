<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Http;

class ReviewChat extends Component
{
    /**
     * Props primite din CodeReview (sau prin eveniment):
     * - $review: textul review-ului generat de AI (fără linia DECISION).
     * - $code:   conținutul fișierului curent (opțional, pentru context).
     * - $fileName: numele fișierului (opțional).
     */
    public ?string $review = null;
    public ?string $code = null;
    public ?string $fileName = null;

    /** Mesajele afișate în UI (user/assistant). */
    public array $messages = [];

    /** Buffer-ul inputului din textarea. */
    public string $input = '';

    /** Busy flag pentru trimitere. */
    public bool $sending = false;

    /** Ascultăm evenimentul din CodeReview ca să resetăm conversația când apare un review nou. */
    protected $listeners = [
        'review-updated' => 'resetWithContext',
    ];

    public function render()
    {
        return view('livewire.review-chat');
    }

    /**
     * Reset la conversație când vine un review nou.
     */
    public function resetWithContext(?string $review = null, ?string $code = null, ?string $file = null): void
    {
        $this->review   = $review ?? $this->review;
        $this->code     = $code ?? $this->code;
        $this->fileName = $file ?? $this->fileName;

        // Reset thread și mesaj introductiv (nu e solicitare către LLM)
        $this->messages = [];
        if ($this->review) {
            $this->messages[] = [
                'role' => 'assistant',
                'text' => "Am încărcat review-ul pentru **{$this->fileName}**. Îmi poți pune întrebări de tipul:\n- „Poți explica mai clar punctul 2?”\n- „Dă-mi un exemplu de cod pentru fix.”\n- „Arată-mi o variantă sigură pentru verificarea existenței folderului.”",
            ];
        }
        $this->input = '';
    }

    /**
     * Trimite întrebarea curentă la LLM, cu tot contextul și istoricul.
     */
    public function ask(): void
    {
        if (!$this->review) {
            // fără review, chat-ul nu are context
            return;
        }

        $q = trim($this->input);
        if ($q === '') return;

        // adaugă mesajul userului în UI
        $this->messages[] = ['role' => 'user', 'text' => $q];
        $this->input = '';
        $this->sending = true;

        try {
            // Construim thread-ul pentru LLM (system + context + istoric)
            $system = 'Ești un asistent tehnic care răspunde STRICT pe marginea unui code review deja generat. '
                    . 'Răspunde în română. Fii concis, dar clar. Dacă utilizatorul cere exemple, oferă cod minimal. '
                    . 'Nu reanaliza întreg fișierul, ci explică/reformulează/argumentează punctele din review.';

            $contextParts = [];
            if ($this->fileName) $contextParts[] = "Fișier: {$this->fileName}";
            $contextParts[] = "REVIEW:\n" . $this->review;
            if ($this->code) {
                $contextParts[] = "COD (referință, nu reanaliza tot):\n" . $this->code;
            }
            $context = implode("\n\n---\n\n", $contextParts);

            $thread = [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => "Context pentru conversație:\n\n" . $context],
            ];

            // Adaugă istoric (user/assistant) din $this->messages după context.
            foreach ($this->messages as $m) {
                if ($m['role'] === 'user') {
                    $thread[] = ['role' => 'user', 'content' => $m['text']];
                } else {
                    // assistant
                    $thread[] = ['role' => 'assistant', 'content' => $m['text']];
                }
            }

            // Apel OLLAMA
            $base  = rtrim(env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'), '/');
            $model = env('OLLAMA_MODEL', 'llama3:3b');

            $payload = [
                'model' => $model,
                'messages' => $thread,
                'stream'   => false,
                'options'  => [
                    'temperature' => 0.3,
                    'num_ctx'     => 8192,
                ],
            ];

            $response = Http::timeout(90)->post($base . '/api/chat', $payload);

            if ($response->failed()) {
                $this->messages[] = [
                    'role' => 'assistant',
                    'text' => "Eroare Ollama: " . $response->body(),
                ];
            } else {
                $data = $response->json();
                $text = $data['message']['content'] ?? '(răspuns gol)';
                $this->messages[] = [
                    'role' => 'assistant',
                    'text' => $text,
                ];
            }
        } catch (\Throwable $e) {
            $this->messages[] = [
                'role' => 'assistant',
                'text' => "A apărut o eroare: " . $e->getMessage(),
            ];
        } finally {
            $this->sending = false;
        }
    }
}
