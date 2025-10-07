<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class SearchSuggestionResource extends JsonResource
{
    public function __construct(
        private readonly Collection $suggestion,
        private readonly ?string $highlightQuery = null
    ) {
        parent::__construct($suggestion);
    }

    public static function withHighlight(Collection $suggestion, string $query): self
    {
        return new self($suggestion, $query);
    }

    public function toArray(Request $request): array
    {
        return [
            'type' => $this->suggestion['type'],
            'value' => $this->suggestion['value'],
            'label' => $this->suggestion['label'],
            'context' => $this->suggestion['context'] ?? null,
            'highlight' => $this->highlightQuery 
                ? $this->highlightMatch($this->suggestion['label'], $this->highlightQuery)
                : $this->suggestion['label'],
        ];
    }

    private function highlightMatch(string $text, string $query): string
    {
        if (empty($query) || empty($text)) {
            return $text;
        }

        $pattern = '/(' . preg_quote($query, '/') . ')/i';
        return preg_replace($pattern, '<mark>$1</mark>', $text);
    }
}