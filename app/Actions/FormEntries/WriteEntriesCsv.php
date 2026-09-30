<?php

declare(strict_types=1);

namespace App\Actions\FormEntries;

use App\Models\Form;
use App\Models\FormEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class WriteEntriesCsv
{
    /**
     * Characters that make spreadsheet applications treat a cell as a formula.
     *
     * @var list<string>
     */
    private const array FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Write the entries as CSV: a column per schema field (headed by its label), then submission metadata.
     *
     * @param  iterable<FormEntry>  $entries
     * @param  resource  $handle
     */
    public function __invoke(Form $form, iterable $entries, $handle): void
    {
        /** @var array<string, string> $fields input key => column heading */
        $fields = [];

        foreach ($form->schema ?? [] as $fieldKey => $fieldSettings) {
            $inputKey = $fieldSettings['name'] ?? $fieldKey;
            $fields[$inputKey] = $fieldSettings['label'] ?? $inputKey;
        }

        $this->writeRow($handle, [
            'id',
            'created_at',
            ...array_values($fields),
            'read_at',
            'starred',
            'spam',
            'spam_score',
            'spam_reason',
            'ip',
            'referer',
            'user_agent',
            'deleted_at',
        ]);

        foreach ($entries as $entry) {
            $input = $entry->input ?? [];

            $this->writeRow($handle, [
                $entry->id,
                $this->formatDate($entry->created_at),
                ...array_map(fn (string $inputKey): string => $this->formatValue($input[$inputKey] ?? null), array_keys($fields)),
                $this->formatDate($entry->read_at === null ? null : Carbon::createFromTimestampUTC($entry->read_at)),
                $this->formatValue($entry->starred),
                $this->formatValue($entry->spam),
                (string) $entry->spam_score,
                $this->formatValue($entry->spam_reason),
                $this->formatValue($entry->ip),
                $this->formatValue($entry->referer),
                $this->formatValue($entry->user_agent),
                $this->formatDate($entry->deleted_at),
            ]);
        }
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $row
     */
    private function writeRow($handle, array $row): void
    {
        fputcsv($handle, array_map($this->escapeFormula(...), $row), escape: '');
    }

    private function formatValue(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => array_is_list($value) && array_filter($value, is_scalar(...)) === $value
                ? implode(', ', $value)
                : (string) json_encode($value),
            default => (string) $value,
        };
    }

    private function formatDate(?CarbonInterface $date): string
    {
        return $date?->toIso8601ZuluString() ?? '';
    }

    /**
     * Prefix cells that would otherwise be evaluated as formulas, per OWASP CSV injection guidance.
     */
    private function escapeFormula(string $value): string
    {
        return $value !== '' && in_array($value[0], self::FORMULA_TRIGGERS, true)
            ? "'".$value
            : $value;
    }
}
