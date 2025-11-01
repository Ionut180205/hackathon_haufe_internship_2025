<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Http;

class QuickFix extends Component
{
    public bool $open = false;

    // Context primit de la CodeReview
    public ?string $code = null;
    public ?string $review = null;
    public ?string $fileName = null;

    // Rezultate generate
    public string $fixedCode = '';
    public bool $generating = false;
    public string $error = '';

    protected $listeners = [
        'open-quick-fix' => 'openWithContext',
    ];

    public function render()
    {
        return view('livewire.quick-fix');
    }

    public function openWithContext(string $code, string $review, string $fileName = ''): void
    {
        $this->resetState();
        $this->code = $code;
        $this->review = $review;
        $this->fileName = $fileName ?: 'fișier';
        $this->open = true;
    }

    public function close(): void
    {
        $this->resetState();
        $this->open = false;
    }

    public function generateFix(): void
    {
        if (!$this->code || !$this->review) {
            $this->error = 'Nu există cod sau review pentru a propune o rezolvare.';
            return;
        }

        $this->generating = true;
        $this->error = '';
        $this->fixedCode = '';

        try {
            $base  = rtrim(env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434'), '/');
            $model = env('OLLAMA_MODEL', 'llama3:3b');

            $system = 'Ești un senior developer. Primești codul original și un review cu probleme. '
                    . 'Generează o versiune REPARATĂ a codului, sigură, idiomatică și echivalentă ca funcționalitate. '
                    . 'Răspunde STRICT cu un singur bloc de cod între ``` (fără explicații).';

            $user = "REVIEW (rezumat probleme):\n{$this->review}\n\n"
                  . "COD ORIGINAL ({$this->fileName}):\n```text\n{$this->code}\n```\n\n"
                  . "Returnează DOAR codul reparat între ```.";

            $payload = [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
                'stream' => false,
                'options' => [
                    'temperature' => 0.2,
                    'num_ctx'     => 8192,
                ],
            ];

            $response = Http::timeout(120)->post($base . '/api/chat', $payload);

            if ($response->failed()) {
                $this->error = "Eroare Ollama: " . $response->body();
                return;
            }

            $data = $response->json();
            $text = $data['message']['content'] ?? '';
            $fixed = $this->extractCodeBlock($text);
            $this->fixedCode = $fixed ?: trim($text);

            if (!$this->fixedCode) {
                $this->error = 'Nu am putut extrage codul reparat din răspuns.';
                return;
            }

            // >>> Aplicare automată în editorul din CodeReview + închidere modal
            $this->dispatch('apply-fixed-code', code: $this->fixedCode);
            $this->close();
        } catch (\Throwable $e) {
            $this->error = "Eroare generare fix: " . $e->getMessage();
        } finally {
            $this->generating = false;
        }
    }

    private function resetState(): void
    {
        $this->code = null;
        $this->review = null;
        $this->fileName = null;
        $this->fixedCode = '';
        $this->generating = false;
        $this->error = '';
    }

    private function extractCodeBlock(string $text): string
    {
        // ```lang\n...\n```
        if (preg_match('/```[a-zA-Z0-9\+\#\-\_\.]*\R(.*)\R```/sU', $text, $m)) {
            return trim($m[1]);
        }
        // ```...\n```
        if (preg_match('/```\R?(.*)\R?```/sU', $text, $m2)) {
            return trim($m2[1]);
        }
        return '';
    }
}
