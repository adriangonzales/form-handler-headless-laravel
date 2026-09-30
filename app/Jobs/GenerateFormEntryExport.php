<?php

namespace App\Jobs;

use App\Actions\FormEntries\FilterEntries;
use App\Actions\FormEntries\WriteEntriesCsv;
use App\Models\FormEntryExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateFormEntryExport implements ShouldQueue
{
    use Queueable;

    /**
     * Skip the job if the export was pruned before it ran.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public FormEntryExport $export) {}

    /**
     * Write the filtered entries to a CSV file on the export's disk.
     */
    public function handle(FilterEntries $filterEntries, WriteEntriesCsv $writeEntriesCsv): void
    {
        $form = $this->export->form;

        if ($form === null) {
            $this->export->update(['status' => FormEntryExport::STATUS_FAILED, 'error' => 'The form was deleted.']);

            return;
        }

        $this->export->update(['status' => FormEntryExport::STATUS_PROCESSING]);

        $path = 'entry-exports/'.$this->export->id.'.csv';
        $handle = fopen('php://temp', 'w+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open a temporary stream.');
        }

        try {
            $rowCount = $writeEntriesCsv($form, $filterEntries($form, $this->export->parameters), $handle);
            rewind($handle);

            Storage::disk($this->export->disk)->writeStream($path, $handle);
        } finally {
            fclose($handle);
        }

        $this->export->update([
            'status' => FormEntryExport::STATUS_COMPLETED,
            'path' => $path,
            'row_count' => $rowCount,
            'completed_at' => now(),
        ]);
    }

    /**
     * Record the failure so clients polling the export stop waiting.
     */
    public function failed(?Throwable $exception): void
    {
        $this->export->update([
            'status' => FormEntryExport::STATUS_FAILED,
            'error' => 'The export could not be generated.',
        ]);
    }
}
