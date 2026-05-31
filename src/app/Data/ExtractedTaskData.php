<?php

namespace App\Data;

use App\Enums\TaskCategory;

readonly class ExtractedTaskData
{
    public function __construct(
        public string $title,
        public ?string $dueDate,
        public TaskCategory $category,
        public ?string $location,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $categoryValue = $data['category'] ?? 'other';

        if (! is_string($categoryValue)) {
            $categoryValue = 'other';
        }

        $category = TaskCategory::tryFrom($categoryValue) ?? TaskCategory::Other;

        $dueDate = $data['due_date'] ?? null;

        return new self(
            title: is_string($data['title'] ?? null) ? trim($data['title']) : '',
            dueDate: is_string($dueDate) && $dueDate !== '' ? $dueDate : null,
            category: $category,
            location: is_string($data['location'] ?? null) && $data['location'] !== ''
                ? trim($data['location'])
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toTaskAttributes(): array
    {
        return [
            'title' => $this->title,
            'due_date' => $this->dueDate,
            'category' => $this->category,
            'location' => $this->location,
        ];
    }
}
