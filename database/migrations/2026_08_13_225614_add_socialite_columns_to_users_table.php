<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('socialite_provider')->nullable()->index();
            $table->string('socialite_id')->nullable();
            $table->unique(['socialite_provider', 'socialite_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['socialite_provider', 'socialite_id']);
            $table->dropColumn(['socialite_provider', 'socialite_id']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
