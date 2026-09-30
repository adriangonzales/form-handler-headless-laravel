<?php

declare(strict_types=1);

namespace App\Actions\Accounts;

use App\Models\Form;
use App\Models\FormEntry;
use App\Models\FormEntryExport;
use App\Models\FormNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;

class DeleteAccount
{
    /**
     * Permanently delete the user and everything they own: forms (including deleted ones), their
     * entries, notifications and exports, export files, and any pending password reset token.
     */
    public function __invoke(User $user): void
    {
        $formIds = Form::withTrashed()->where('user_id', $user->id)->select('id');

        $exportFiles = FormEntryExport::query()
            ->whereIn('form_id', $formIds)
            ->whereNotNull('path')
            ->get(['disk', 'path']);

        DB::transaction(function () use ($user, $formIds): void {
            FormEntryExport::query()->whereIn('form_id', $formIds)->delete();
            FormEntry::withTrashed()->whereIn('form_id', $formIds)->forceDelete();
            FormNotification::withTrashed()->whereIn('form_id', $formIds)->forceDelete();
            Form::withTrashed()->where('user_id', $user->id)->forceDelete();
            Password::broker()->deleteToken($user);
            $user->delete();
        });

        foreach ($exportFiles as $export) {
            Storage::disk($export->disk)->delete((string) $export->path);
        }
    }
}
