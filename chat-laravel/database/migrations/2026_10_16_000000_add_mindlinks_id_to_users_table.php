<?php
// database/migrations/2026_10_16_000000_add_mindlinks_id_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // id do usuário na tabela `usuarios` do MindLinks (INT UNSIGNED)
            $table->unsignedInteger('mindlinks_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['mindlinks_id']);
            $table->dropColumn('mindlinks_id');
        });
    }
};