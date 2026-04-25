<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spatie Permission Tables - Multi-tenant version
 * 
 * All permission tables include school_id for tenant isolation.
 * This prevents cross-tenant permission leakage.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Roles table - scoped to school
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->json('permissions_cache')->nullable(); // Cached permissions for this role
            $table->timestamps();
            $table->softDeletes();

            // Unique constraint includes school_id
            $table->unique(['school_id', 'name', 'guard_name']);
            $table->index(['school_id', 'guard_name']);
        });

        // Permissions table - scoped to school
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->string('group')->nullable(); // e.g., 'students', 'courses', 'billing'
            $table->string('description')->nullable();
            $table->timestamps();

            // Unique constraint includes school_id
            $table->unique(['school_id', 'name', 'guard_name']);
            $table->index(['school_id', 'guard_name']);
            $table->index(['school_id', 'group']);
        });

        // Model-role pivot (scoped to school)
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['school_id', 'role_id']);
            $table->index(['school_id', 'model_type', 'model_id']);
            
            // Foreign key to roles
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            // Unique: one role per model per school
            $table->unique(['school_id', 'role_id', 'model_type', 'model_id']);
        });

        // Model-permission pivot (scoped to school)
        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->index(['school_id', 'permission_id']);
            $table->index(['school_id', 'model_type', 'model_id']);
            
            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');

            $table->unique(['school_id', 'permission_id', 'model_type', 'model_id']);
        });

        // Role-permission pivot (scoped to school)
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->index(['school_id', 'permission_id']);
            $table->index(['school_id', 'role_id']);
            
            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');
            
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('cascade');

            $table->primary(['school_id', 'permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
