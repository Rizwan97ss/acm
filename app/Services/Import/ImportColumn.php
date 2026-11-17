<?php

namespace App\Services\Import;

/**
 * One column of an import spreadsheet. The header in the file may be the key
 * (first_name) or the label (First name), in any case, with or without the "*".
 */
final readonly class ImportColumn
{
    public const string TEXT = 'text';

    public const string DATE = 'date';

    public const string BOOLEAN = 'boolean';

    public const string INTEGER = 'integer';

    public const string CHOICE = 'choice';

    public const string CODE = 'code';

    public const string DIGITS = 'digits';

    /**
     * @param  array<string, string>  $choices  stored value => label, for CHOICE columns
     */
    public function __construct(
        public string $key,
        public string $label,
        public bool $required = false,
        public string $type = self::TEXT,
        public string $hint = '',
        public string $example = '',
        public array $choices = [],
        public bool $preview = false,
    ) {}

    /**
     * Header text written to the template.
     */
    public function header(): string
    {
        return $this->required ? $this->label.' *' : $this->label;
    }

    /**
     * What the instructions sheet and the page say about accepted values.
     */
    public function format(): string
    {
        return match ($this->type) {
            self::DATE => trim('Date, e.g. 2015-06-21 or 21/06/2015. '.$this->hint),
            self::BOOLEAN => trim('Yes or No. '.$this->hint),
            self::INTEGER => trim('Whole number. '.$this->hint),
            self::CHOICE => trim('One of: '.implode(', ', $this->choices).'. '.$this->hint),
            default => $this->hint,
        };
    }

    /**
     * @return array{key: string, label: string, required: bool, format: string, example: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'required' => $this->required,
            'format' => $this->format(),
            'example' => $this->example,
        ];
    }
}
