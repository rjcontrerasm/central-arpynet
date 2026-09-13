<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaboration_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('commentable_type');
            $table->unsignedBigInteger('commentable_id');
            $table->text('body');
            $table->timestamps();

            $table->index(
                ['commentable_type', 'commentable_id'],
                'collaboration_comments_subject_index',
            );
            $table->index(
                ['organization_id', 'created_at'],
                'collaboration_comments_org_created_index',
            );
        });

        Schema::create('collaboration_comment_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collaboration_comment_id')
                ->constrained('collaboration_comments')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['collaboration_comment_id', 'user_id'],
                'collaboration_comment_mentions_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_comment_mentions');
        Schema::dropIfExists('collaboration_comments');
    }
};
