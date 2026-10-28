<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('tipo', ['apoiado', 'apoiador'])->default('apoiado');
            $table->enum('status_verificacao', ['nao_aplicavel', 'pendente', 'aprovado', 'recusado'])
                  ->default('nao_aplicavel');
            $table->string('tema_experiencia', 100)->nullable();
            $table->boolean('eh_admin')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'status_verificacao', 'tema_experiencia', 'eh_admin']);
        });
    }
};