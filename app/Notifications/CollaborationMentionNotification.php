<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class CollaborationMentionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $commentId,
        private readonly string $authorName,
        private readonly string $body,
        private readonly string $subjectType,
        private readonly int $subjectId,
        private readonly string $subjectLabel,
        private readonly string $subjectTitle,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'collaboration_mention',
            'title' => $this->authorName.' te mencionó',
            'message' => $this->subjectLabel.' · '
                .$this->subjectTitle.' — '
                .Str::limit($this->body, 180),
            'comment_id' => $this->commentId,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'url' => route(
                'collaboration.thread',
                [
                    'type' => $this->subjectType,
                    'id' => $this->subjectId,
                ],
            ),
        ];
    }
}
