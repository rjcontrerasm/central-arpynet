<?php

namespace App\Observers;

use App\Support\AuditRecorder;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function __construct(
        private readonly AuditRecorder $recorder,
    ) {
    }

    public function created(Model $model): void
    {
        $this->recorder->record(
            $model,
            'created',
        );
    }

    public function updated(Model $model): void
    {
        $this->recorder->record(
            $model,
            'updated',
        );
    }

    public function deleted(Model $model): void
    {
        $this->recorder->record(
            $model,
            'deleted',
        );
    }
}
