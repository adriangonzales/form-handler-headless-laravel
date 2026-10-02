<?php

declare(strict_types=1);

namespace App\Actions\FormEntries;

use App\Models\Form;
use App\Models\FormEntry;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class WriteEntriesCsv
{
    /**
     * Characters that make spreadsheet applications treat a cell as a formula.
     *
     * @var list<string>
     */
    private const array FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Write the entries as CSV: a column per schema field in the schema's order (headed by its label), a
     * column per input key the schema no longer has (headed by the key), then submission metadata. Returns
     * the number of entries written.
     *
     * @param  Builder<FormEntry>  $entries
     * @param  resource  $handle
     */
    public function __invoke(Form $form, Builder $entries, $handle): int
    {
        $rowCount = 0;

        /** @var array<string, string> $fields input key => column heading */
        $fields = [];

        foreach ($form->orderedSchema() as $fieldSettings) {
            $inputKey = $fieldSettings['name'] ?? $fieldSettings['id'];
            $fields[$inputKey] = $fieldSettings['label'] ?? $inputKey;
        }

        foreach ($this->storedInputKeys($entries) as $inputKey) {
            $fields[$inputKey] ??= $inputKey;
        }

        $this->writeRow($handle, [
            'id',
            'created_at',
            ...array_map(strval(...), array_values($fields)),
            'read_at',
            'starred',
            'spam',
            'spam_score',
            'spam_reason',
            'spam_checked_at',
            'ip',
            'referer',
            'user_agent',
            'deleted_at',
        ]);

        foreach ($entries->cursor() as $entry) {
            $input = $entry->input ?? [];

            $this->writeRow($handle, [
                $entry->id,
                $this->formatDate($entry->created_at),
                ...array_map(fn (int|string $inputKey): string => $this->formatValue($input[$inputKey] ?? null), array_keys($fields)),
                $this->formatDate($entry->read_at),
                $this->formatValue($entry->starred),
                $this->formatValue($entry->spam),
                (string) $entry->spam_score,
                $this->formatValue($entry->spam_reason),
                $this->formatDate($entry->spam_checked_at),
                $this->formatValue($entry->ip),
                $this->formatValue($entry->referer),
                $this->formatValue($entry->user_agent),
                $this->formatDate($entry->deleted_at),
            ]);

            $rowCount++;
        }

        return $rowCount;
    }

    /**
     * Collect every input key stored on the entries, sorted, without hydrating models. Entries submitted
     * before a field was removed or renamed keep values under keys the current schema does not list.
     *
     * @param  Builder<FormEntry>  $entries
     * @return list<string>
     */
    private function storedInputKeys(Builder $entries): array
    {
        $keys = [];

        foreach ($entries->clone()->reorder()->select('input')->toBase()->cursor() as $row) {
            $input = is_string($row->input) ? json_decode($row->input, true) : null;

            if (is_array($input)) {
                $keys += array_fill_keys(array_map(strval(...), array_keys($input)), true);
            }
        }

        $keys = array_keys($keys);
        sort($keys, SORT_STRING);

        return array_map(strval(...), $keys);
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
