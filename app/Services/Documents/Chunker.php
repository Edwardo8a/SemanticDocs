<?php

namespace App\Services\Documents;

class Chunker
{
    protected int $targetWords;

    protected int $overlapWords;

    /**
     * @param  int  $targetWords  Palabras objetivo por fragmento (~400-550 palabras equivalen a ~550-750 tokens)
     * @param  int  $overlapWords  Palabras de traslape (~10-15%)
     */
    public function __construct(int $targetWords = 450, int $overlapWords = 50)
    {
        $this->targetWords = $targetWords;
        $this->overlapWords = min($overlapWords, (int) ($targetWords / 2));
    }

    /**
     * Chunk array of pages into structured fragments with metadata.
     *
     * @param  array<int, array{page: int, text: string}>  $pages
     * @return array<int, array{chunk_index: int, page: int, text: string}>
     */
    public function chunkPages(array $pages): array
    {
        $chunks = [];
        $chunkIndex = 0;

        foreach ($pages as $pageData) {
            $pageNumber = $pageData['page'] ?? 1;
            $text = trim($pageData['text'] ?? '');

            if (empty($text)) {
                continue;
            }

            $pageChunks = $this->chunkText($text, $pageNumber, $chunkIndex);

            foreach ($pageChunks as $chunk) {
                $chunks[] = $chunk;
                $chunkIndex++;
            }
        }

        return $chunks;
    }

    /**
     * Split a single block of text into overlapping chunks.
     *
     * @return array<int, array{chunk_index: int, page: int, text: string}>
     */
    public function chunkText(string $text, int $page = 1, int $startIndex = 0): array
    {
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $totalWords = count($words);

        if ($totalWords === 0) {
            return [];
        }

        // Si el texto es menor o igual al tamaño objetivo, devolver un único fragmento
        if ($totalWords <= $this->targetWords) {
            return [
                [
                    'chunk_index' => $startIndex,
                    'page' => $page,
                    'text' => implode(' ', $words),
                ],
            ];
        }

        $chunks = [];
        $currentIndex = $startIndex;
        $step = max(1, $this->targetWords - $this->overlapWords);

        for ($i = 0; $i < $totalWords; $i += $step) {
            $slice = array_slice($words, $i, $this->targetWords);
            $chunkText = implode(' ', $slice);

            $chunks[] = [
                'chunk_index' => $currentIndex,
                'page' => $page,
                'text' => $chunkText,
            ];

            $currentIndex++;

            // Si el restante es menor que el overlap, ya abarcamos todo
            if ($i + $this->targetWords >= $totalWords) {
                break;
            }
        }

        return $chunks;
    }
}
