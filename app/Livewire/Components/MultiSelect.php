<?php

declare(strict_types=1);

namespace App\Livewire\Components;

use Livewire\Attributes\Modelable;
use Livewire\Component;

class MultiSelect extends Component
{
    #[Modelable]
    public array $selected = [];

    public array $options = [];

    public string $placeholder = 'Select options...';

    public ?string $label = null;

    public bool $searchable = false;

    public ?string $emptyMessage = 'No options available';

    public int $maxHeight = 200;

    public bool $isOpen = false;

    public string $search = '';

    protected $listeners = ['close-dropdowns' => 'close'];

    public function mount(
        array $options = [],
        array $selected = [],
        ?string $placeholder = null,
        ?string $label = null,
        bool $searchable = false,
        ?string $emptyMessage = null,
        int $maxHeight = 200
    ): void {
        $this->options = $options;
        $this->selected = $selected;
        $this->placeholder = $placeholder ?? $this->placeholder;
        $this->label = $label;
        $this->searchable = $searchable;
        $this->emptyMessage = $emptyMessage ?? $this->emptyMessage;
        $this->maxHeight = $maxHeight;
    }

    public function toggle(): void
    {
        if ($this->isOpen) {
            $this->close();
        } else {
            $this->open();
        }
    }

    public function open(): void
    {
        $this->dispatch('close-dropdowns');
        $this->isOpen = true;
        $this->search = '';
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->search = '';
    }

    public function toggleOption(string $value): void
    {
        if (in_array($value, $this->selected)) {
            $this->selected = array_values(array_filter($this->selected, fn ($item) => $item !== $value));
        } else {
            $this->selected[] = $value;
        }
    }

    public function selectAll(): void
    {
        $this->selected = array_map(function ($option) {
            return is_array($option) ? $option['value'] : $option;
        }, $this->getFilteredOptions());
    }

    public function deselectAll(): void
    {
        $this->selected = [];
    }

    public function getFilteredOptions(): array
    {
        if (! $this->searchable || empty($this->search)) {
            return $this->options;
        }

        return array_filter($this->options, function ($option) {
            $label = is_array($option) ? ($option['label'] ?? $option['value']) : $option;

            return str_contains(strtolower($label), strtolower($this->search));
        });
    }

    public function getSelectedLabels(): array
    {
        return array_map(function ($value) {
            foreach ($this->options as $option) {
                $optionValue = is_array($option) ? $option['value'] : $option;
                if ($optionValue === $value) {
                    return is_array($option) ? ($option['label'] ?? $option['value']) : $option;
                }
            }

            return $value;
        }, $this->selected);
    }

    public function getDisplayText(): string
    {
        $count = count($this->selected);

        if ($count === 0) {
            return $this->placeholder;
        }

        if ($count === 1) {
            return $this->getSelectedLabels()[0];
        }

        if ($count <= 3) {
            return implode(', ', $this->getSelectedLabels());
        }

        return "{$count} selected";
    }

    public function render()
    {
        return view('livewire.components.multi-select');
    }
}
