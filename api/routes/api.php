<?php

use App\Modules\ACL\Http\Controllers\RoleController;
use App\Modules\AiPublisher\Http\Controllers\AiPublisherController;
use App\Modules\AiPublisher\Http\Controllers\EditorialSettingsController;
use App\Modules\ApiToken\Http\Controllers\ApiTokenController;
use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Post\Http\Controllers\CategoryController;
use App\Modules\Post\Http\Controllers\PostController;
use App\Modules\Post\Http\Controllers\SitemapController;
use App\Modules\Shared\Http\Controllers\FileUploadController;
use App\Modules\Tenant\Http\Controllers\TenantController;
use App\Modules\User\Http\Controllers\UserController;
use App\Modules\Webhook\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('sitemap.xml', SitemapController::class);

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth.multi:sanctum', 'tenant'])->group(function (): void {
    Route::get('tenant', [TenantController::class, 'show'])->middleware('permission:tenant.read');
    Route::match(['put', 'patch'], 'tenant', [TenantController::class, 'update'])->middleware('permission:tenant.update');

    Route::get('users', [UserController::class, 'index'])->middleware('permission:user.read');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:user.create');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:user.read');
    Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->middleware('permission:user.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:user.delete');

    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:role.read');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:role.create');
    Route::get('roles/{role}', [RoleController::class, 'show'])->middleware('permission:role.read');
    Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->middleware('permission:role.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:role.delete');

    Route::get('api-tokens', [ApiTokenController::class, 'index'])->middleware('permission:api-token.read');
    Route::post('api-tokens', [ApiTokenController::class, 'store'])->middleware('permission:api-token.create');
    Route::delete('api-tokens/{apiToken}', [ApiTokenController::class, 'destroy'])->middleware('permission:api-token.delete');

    Route::get('webhooks', [WebhookController::class, 'index'])->middleware('permission:webhook.read');
    Route::get('webhooks/events', [WebhookController::class, 'events'])->middleware('permission:webhook.read');
    Route::post('webhooks', [WebhookController::class, 'store'])->middleware('permission:webhook.create');
    Route::get('webhooks/{webhook}', [WebhookController::class, 'show'])->middleware('permission:webhook.read');
    Route::match(['put', 'patch'], 'webhooks/{webhook}', [WebhookController::class, 'update'])->middleware('permission:webhook.update');
    Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->middleware('permission:webhook.delete');
    Route::get('webhooks/{webhook}/logs', [WebhookController::class, 'logs'])->middleware('permission:webhook.read');

    Route::post('uploads', FileUploadController::class);

    Route::get('posts', [PostController::class, 'index'])->middleware('permission:post.read');
    Route::post('posts', [PostController::class, 'store'])->middleware('permission:post.create');
    Route::get('posts/{post}', [PostController::class, 'show'])->middleware('permission:post.read');
    Route::match(['put', 'patch'], 'posts/{post}', [PostController::class, 'update'])->middleware('permission:post.update');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->middleware('permission:post.delete');

    Route::get('categories', [CategoryController::class, 'index']);
    Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:post.create');
    Route::get('categories/{category}', [CategoryController::class, 'show']);
    Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update'])->middleware('permission:post.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:post.delete');

    Route::middleware(['throttle:ai', 'permission:ai.read'])->prefix('ai')->group(function (): void {
        Route::get('discovery', [AiPublisherController::class, 'discovery']);
        Route::get('docs', [AiPublisherController::class, 'docs']);
        Route::get('schema/post', [AiPublisherController::class, 'schemaPost']);
        Route::get('schema/category', [AiPublisherController::class, 'schemaCategory']);
        Route::get('editorial-guide', [AiPublisherController::class, 'editorialGuide']);
        Route::get('editorial-settings', [EditorialSettingsController::class, 'show']);
        Route::match(['put', 'patch'], 'editorial-settings', [EditorialSettingsController::class, 'update']);
        Route::post('posts', [AiPublisherController::class, 'publish'])->name('ai.publish')->middleware('permission:ai.publish');
    });
});
