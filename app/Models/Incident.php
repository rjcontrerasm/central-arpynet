<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class Incident extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'client_id',
        'service_order_id',
        'project_id',
        'title',
        'description',
        'category',
        'severity',
        'status',
        'affected_service',
        'source',
        'external_id',
        'external_url',
        'detected_at',
        'acknowledged_at',
        'mitigated_at',
        'resolved_at',
        'closed_at',
        'response_due_at',
        'resolution_due_at',
        'next_action',
        'next_action_at',
        'root_cause',
        'resolution_summary',
        'notes',
        'assigned_to',
        'created_by',
        'last_activity_at',
        'is_private',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'mitigated_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'next_action_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'is_private' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Incident $incident): void {
            $incident->created_by ??= auth()->id();
            $incident->assigned_to ??= auth()->id();
            $incident->detected_at ??= now();
            $incident->last_activity_at ??= now();

            $incident->synchronizeMilestones();
            $incident->validateRelations();
        });

        static::updating(function (Incident $incident): void {
            $incident->validateRelations();
            $incident->synchronizeMilestones();

            $activityFields = [
                'organization_id',
                'client_id',
                'service_order_id',
                'project_id',
                'title',
                'description',
                'category',
                'severity',
                'status',
                'affected_service',
                'source',
                'external_id',
                'external_url',
                'acknowledged_at',
                'mitigated_at',
                'resolved_at',
                'closed_at',
                'response_due_at',
                'resolution_due_at',
                'next_action',
                'next_action_at',
                'root_cause',
                'resolution_summary',
                'notes',
                'assigned_to',
                'is_private',
            ];

            foreach ($activityFields as $field) {
                if ($incident->isDirty($field)) {
                    $incident->last_activity_at = now();
                    break;
                }
            }
        });
    }

    public static function categoryOptions(): array
    {
        return [
            'availability' => 'Disponibilidad',
            'performance' => 'Rendimiento',
            'security' => 'Seguridad',
            'email' => 'Correo / entregabilidad',
            'backup' => 'Backup / recuperación',
            'certificate' => 'Certificado SSL',
            'application' => 'Aplicación',
            'database' => 'Base de datos',
            'infrastructure' => 'Infraestructura',
            'other' => 'Otro',
        ];
    }

    public static function severityOptions(): array
    {
        return [
            'info' => 'Informativo',
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Crítica',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            'new' => 'Nuevo',
            'investigating' => 'En investigación',
            'mitigated' => 'Mitigado',
            'monitoring' => 'En observación',
            'resolved' => 'Resuelto',
            'closed' => 'Cerrado',
            'cancelled' => 'Cancelado',
        ];
    }

    public static function sourceOptions(): array
    {
        return [
            'manual' => 'Manual',
            'monitor' => 'Monitor Central',
            'cloudflare' => 'Cloudflare',
            'aws' => 'AWS',
            'uptimerobot' => 'UptimeRobot',
            'wordfence' => 'Wordfence',
            'email' => 'Correo',
            'client' => 'Reportado por cliente',
            'other' => 'Otro',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeVisibleTo(
        Builder $query,
        User $user,
    ): Builder {
        return $query->whereHas(
            'organization.users',
            fn (Builder $membershipQuery): Builder =>
                $membershipQuery
                    ->where('users.id', $user->id)
                    ->where(
                        'organization_user.is_active',
                        true,
                    ),
        );
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn(
            'status',
            ['resolved', 'closed', 'cancelled'],
        );
    }

    public function getOpenMinutesAttribute(): int
    {
        $from = $this->detected_at
            ?? $this->created_at
            ?? now();

        $to = $this->resolved_at
            ?? $this->closed_at
            ?? now();

        return max(
            0,
            (int) $from->diffInMinutes($to),
        );
    }

    public function getOpenDurationLabelAttribute(): string
    {
        $minutes = $this->open_minutes;

        if ($minutes < 60) {
            return $minutes.' min';
        }

        if ($minutes < 1440) {
            return floor($minutes / 60).' h';
        }

        return floor($minutes / 1440).' d';
    }

    public function getAttentionLabelAttribute(): string
    {
        if (in_array(
            $this->status,
            ['resolved', 'closed'],
            true,
        )) {
            return 'Resuelto';
        }

        if ($this->status === 'cancelled') {
            return 'Cancelado';
        }

        if (
            $this->resolution_due_at
            && $this->resolution_due_at->isPast()
        ) {
            return 'SLA solución vencido';
        }

        if (
            ! $this->acknowledged_at
            && $this->response_due_at
            && $this->response_due_at->isPast()
        ) {
            return 'SLA respuesta vencido';
        }

        if (
            $this->next_action_at
            && $this->next_action_at->isPast()
        ) {
            return 'Seguimiento vencido';
        }

        if ($this->severity === 'critical') {
            return 'Crítico abierto';
        }

        $activity = $this->last_activity_at
            ?? $this->updated_at
            ?? $this->created_at;

        if (
            $activity
            && $activity->diffInHours(now()) >= 24
        ) {
            return 'Sin movimiento';
        }

        return 'En seguimiento';
    }

    public function getAttentionColorAttribute(): string
    {
        return match ($this->attention_label) {
            'SLA solución vencido',
            'SLA respuesta vencido',
            'Seguimiento vencido',
            'Crítico abierto' => 'danger',

            'Sin movimiento' => 'warning',

            'Resuelto' => 'success',

            'En seguimiento' => 'info',

            default => 'gray',
        };
    }

    private function synchronizeMilestones(): void
    {
        if (
            in_array(
                $this->status,
                [
                    'investigating',
                    'mitigated',
                    'monitoring',
                    'resolved',
                    'closed',
                ],
                true,
            )
            && ! $this->acknowledged_at
        ) {
            $this->acknowledged_at = now();
        }

        if (
            in_array(
                $this->status,
                ['mitigated', 'monitoring', 'resolved', 'closed'],
                true,
            )
            && ! $this->mitigated_at
        ) {
            $this->mitigated_at = now();
        }

        if (
            in_array(
                $this->status,
                ['resolved', 'closed'],
                true,
            )
            && ! $this->resolved_at
        ) {
            $this->resolved_at = now();
        }

        if (
            $this->status === 'closed'
            && ! $this->closed_at
        ) {
            $this->closed_at = now();
        }
    }

    private function validateRelations(): void
    {
        if (
            $this->client_id
            && ! Client::query()
                ->whereKey($this->client_id)
                ->where(
                    'organization_id',
                    $this->organization_id,
                )
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'client_id' =>
                    'El cliente no pertenece a la empresa seleccionada.',
            ]);
        }

        if (
            $this->service_order_id
            && ! ServiceOrder::query()
                ->whereKey($this->service_order_id)
                ->where(
                    'organization_id',
                    $this->organization_id,
                )
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'service_order_id' =>
                    'La orden/servicio no pertenece a la empresa seleccionada.',
            ]);
        }

        if (
            $this->project_id
            && ! Project::query()
                ->whereKey($this->project_id)
                ->where(
                    'organization_id',
                    $this->organization_id,
                )
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'project_id' =>
                    'El proyecto no pertenece al ámbito seleccionado.',
            ]);
        }
    }
}
