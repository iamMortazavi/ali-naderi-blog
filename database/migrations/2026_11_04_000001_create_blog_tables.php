<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blog schema. See docs/content-model.md for the meaning of each table.
 *
 * Identity rules:
 *  - a post's address is (locale, slug): unique, never reused;
 *  - a post and its translation share translation_key: (translation_key, locale) is unique;
 *  - posts imported from Obsidian are identified by source_path and are read-only in the panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5);
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['locale', 'slug']);
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5)->index();
            $table->string('translation_key', 80);
            $table->string('slug', 80);
            $table->string('title', 160);
            $table->string('description', 300)->nullable();
            $table->longText('body');                       // Markdown, as written
            $table->longText('body_html')->nullable();      // rendered on save
            $table->longText('search_text')->nullable();    // normalized plain text for Persian-aware search
            $table->json('toc')->nullable();                // [{level, id, text}]
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->string('cover_path')->nullable();
            $table->string('cover_alt', 200)->nullable();
            $table->string('audio_url')->nullable();
            $table->string('video_url')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft | published
            $table->timestamp('published_at')->nullable()->index(); // in the future = scheduled
            $table->timestamp('content_updated_at')->nullable();    // shown as "updated" and dateModified
            $table->boolean('show_toc')->default(true);
            $table->boolean('send_to_telegram')->default(true);
            $table->foreignId('series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('panel');   // panel | obsidian
            $table->string('source_path')->nullable();          // path inside the vault
            $table->string('source_hash', 64)->nullable();      // sha256 of the note, to skip unchanged notes
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['locale', 'slug']);
            $table->unique(['translation_key', 'locale']);
            $table->index(['source', 'source_path']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5);
            $table->string('name', 60);
            $table->string('slug', 80);
            $table->timestamps();
            $table->unique(['locale', 'slug']);
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'tag_id']);
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5);
            $table->string('slug', 80);
            $table->string('title', 160);
            $table->string('description', 300)->nullable();
            $table->longText('body');
            $table->longText('body_html')->nullable();
            $table->boolean('in_sitemap')->default(true);
            $table->timestamps();
            $table->unique(['locale', 'slug']);
        });

        // One row per settings group (site, look, author, menu, social, telegram, seo, sync).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 40)->primary();
            $table->json('value');
            $table->timestamps();
        });

        // The memory of what was sent to the channel: one message per post, ever.
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('chat_id', 64);
            $table->bigInteger('message_id')->nullable(); // null = marked as sent without sending (baseline)
            $table->timestamp('sent_at');
            $table->timestamps();
        });

        // Old address → new address (301). Added automatically when a published post's slug changes.
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();
        });

        // History of Obsidian imports, shown in the panel.
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20);                 // local | github
            $table->string('status', 20)->default('running'); // running | success | failed
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('unpublished_count')->default(0);
            $table->unsignedInteger('unchanged_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);  // private notes (no publish: true)
            $table->json('messages')->nullable();         // [{level, file, text}]
            $table->string('commit', 64)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('telegram_messages');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('series');
    }
};
