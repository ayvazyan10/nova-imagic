<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imagic_media_folders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('owner_type');
            $table->string('owner_id', 191);
            $table->foreignId('parent_id')->nullable()->constrained('imagic_media_folders')->nullOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index(['owner_type', 'owner_id'], 'imagic_folders_owner_index');
            $table->index(['owner_type', 'owner_id', 'parent_id'], 'imagic_folders_parent_index');
        });

        Schema::create('imagic_media_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('owner_type');
            $table->string('owner_id', 191);
            $table->foreignId('folder_id')->nullable()->constrained('imagic_media_folders')->nullOnDelete();
            $table->string('disk', 64);
            $table->string('path', 1024);
            $table->char('path_hash', 64);
            $table->string('thumbnail_path', 1024)->nullable();
            $table->string('name');
            $table->string('original_name');
            $table->string('mime_type', 127);
            $table->string('extension', 16);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->string('visibility', 16)->default('private');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['disk', 'path_hash'], 'imagic_media_disk_path_unique');
            $table->index(['owner_type', 'owner_id', 'created_at'], 'imagic_media_owner_created_index');
            $table->index(['owner_type', 'owner_id', 'folder_id'], 'imagic_media_owner_folder_index');
            $table->index(['owner_type', 'owner_id', 'mime_type'], 'imagic_media_owner_mime_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imagic_media_assets');
        Schema::dropIfExists('imagic_media_folders');
    }
};
