<?php

use App\Modules\ACL\Http\Controllers\RoleController;
use App\Modules\AiPublisher\Http\Controllers\AiPublisherController;
use App\Modules\AiPublisher\Http\Controllers\EditorialSettingsController;
use App\Modules\ApiToken\Http\Controllers\ApiTokenController;
use App\Modules\Auth\Http\Controllers\AuthController;
use App\Modules\Analytics\Http\Controllers\AdSpendController;
use App\Modules\Analytics\Http\Controllers\ReportController;
use App\Modules\Crm\Http\Controllers\CrmSettingsController;
use App\Modules\Crm\Http\Controllers\LeadTagController;
use App\Modules\Crm\Http\Controllers\LeadTaskController;
use App\Modules\Crm\Http\Controllers\PipelineController;
use App\Modules\GoogleAds\Http\Controllers\GoogleAdsController;
use App\Modules\Nurture\Http\Controllers\NurtureController;
use App\Modules\Nurture\Http\Controllers\UnsubscribeController;
use App\Modules\SearchConsole\Http\Controllers\SearchConsoleController;
use App\Modules\Crm\Http\Controllers\PipelineStageController;
use App\Modules\Lead\Http\Controllers\LeadController;
use App\Modules\Post\Http\Controllers\CategoryController;
use App\Modules\Post\Http\Controllers\PostController;
use App\Modules\Post\Http\Controllers\SitemapController;
use App\Modules\Shared\Http\Controllers\FileUploadController;
use App\Modules\Tenant\Http\Controllers\TenantController;
use App\Modules\User\Http\Controllers\UserController;
use App\Modules\Webhook\Http\Controllers\WebhookController;
use App\Modules\WhatsApp\Http\Controllers\ConversationController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppAccountController;
use App\Modules\WhatsApp\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('sitemap.xml', SitemapController::class);

// Descadastro dos e-mails automáticos (link assinado; POST = one-click do cliente de e-mail).
Route::middleware(['signed', 'throttle:30,1'])->group(function (): void {
    Route::get('public/unsubscribe/{lead}', [UnsubscribeController::class, 'show'])->name('leads.unsubscribe');
    Route::post('public/unsubscribe/{lead}', [UnsubscribeController::class, 'store']);
});

// Webhook da WhatsApp Cloud API (público; autenticado pela assinatura da Meta).
Route::middleware('throttle:whatsapp-webhook')->group(function (): void {
    Route::get('whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
    Route::post('whatsapp/webhook', [WhatsAppWebhookController::class, 'receive']);
});

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
    Route::match(['put', 'patch'], 'api-tokens/{apiToken}', [ApiTokenController::class, 'update'])->middleware('permission:api-token.update');
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

    Route::get('leads', [LeadController::class, 'index'])->middleware('permission:lead.read');
    Route::post('leads', [LeadController::class, 'store'])->middleware(['throttle:leads', 'permission:lead.create']);
    Route::get('leads/export/google-ads', [LeadController::class, 'exportGoogleAds'])->middleware('permission:lead.read');
    Route::get('leads/{lead}', [LeadController::class, 'show'])->middleware('permission:lead.read');
    Route::match(['put', 'patch'], 'leads/{lead}', [LeadController::class, 'update'])->middleware('permission:lead.update');

    // --- CRM: funil (kanban) ---
    Route::get('pipeline/stages', [PipelineStageController::class, 'index'])->middleware('permission:lead.read');
    Route::post('pipeline/stages', [PipelineStageController::class, 'store'])->middleware('permission:pipeline.manage');
    Route::post('pipeline/stages/reorder', [PipelineStageController::class, 'reorder'])->middleware('permission:pipeline.manage');
    Route::match(['put', 'patch'], 'pipeline/stages/{stage}', [PipelineStageController::class, 'update'])->middleware('permission:pipeline.manage');
    Route::delete('pipeline/stages/{stage}', [PipelineStageController::class, 'destroy'])->middleware('permission:pipeline.manage');
    Route::get('pipeline/board', [PipelineController::class, 'board'])->middleware('permission:lead.read');
    Route::post('pipeline/leads', [PipelineController::class, 'storeLead'])->middleware('permission:lead.update');
    Route::post('leads/{lead}/move', [PipelineController::class, 'move'])->middleware('permission:lead.update');
    Route::get('leads/{lead}/activities', [PipelineController::class, 'activities'])->middleware('permission:lead.read');
    Route::post('leads/{lead}/notes', [PipelineController::class, 'note'])->middleware('permission:lead.update');
    Route::put('leads/{lead}/tags', [LeadController::class, 'syncTags'])->middleware('permission:lead.update');

    // --- CRM: tags, tarefas, leads parados ---
    Route::get('lead-tags', [LeadTagController::class, 'index'])->middleware('permission:lead.read');
    Route::match(['put', 'patch'], 'lead-tags/{tag}', [LeadTagController::class, 'update'])->middleware('permission:pipeline.manage');
    Route::delete('lead-tags/{tag}', [LeadTagController::class, 'destroy'])->middleware('permission:pipeline.manage');
    Route::get('tasks', [LeadTaskController::class, 'index'])->middleware('permission:lead.read');
    Route::post('leads/{lead}/tasks', [LeadTaskController::class, 'store'])->middleware('permission:lead.update');
    Route::match(['put', 'patch'], 'tasks/{task}', [LeadTaskController::class, 'update'])->middleware('permission:lead.update');
    Route::delete('tasks/{task}', [LeadTaskController::class, 'destroy'])->middleware('permission:lead.update');
    Route::get('crm/stale-leads', [LeadTaskController::class, 'stale'])->middleware('permission:lead.read');
    Route::get('crm/assignees', [LeadTaskController::class, 'assignees'])->middleware('permission:lead.read');
    Route::get('crm/lost-reasons', [CrmSettingsController::class, 'lostReasons'])->middleware('permission:lead.read');
    Route::get('crm/settings', [CrmSettingsController::class, 'show'])->middleware('permission:pipeline.manage');
    Route::match(['put', 'patch'], 'crm/settings', [CrmSettingsController::class, 'update'])->middleware('permission:pipeline.manage');
    Route::post('crm/settings/test', [CrmSettingsController::class, 'test'])->middleware(['permission:pipeline.manage', 'throttle:5,1']);

    // --- Nutrição por e-mail ---
    Route::get('leads/{lead}/enrollments', [NurtureController::class, 'leadEnrollments'])->middleware('permission:lead.read');
    Route::post('leads/{lead}/enrollments', [NurtureController::class, 'enroll'])->middleware('permission:lead.update');
    Route::post('enrollments/{enrollment}/stop', [NurtureController::class, 'stop'])->middleware('permission:lead.update');
    Route::get('nurture/sequences', [NurtureController::class, 'index'])->middleware('permission:lead.read');
    Route::middleware('permission:nurture.manage')->group(function (): void {
        Route::post('nurture/sequences', [NurtureController::class, 'store']);
        Route::get('nurture/sequences/{sequence}', [NurtureController::class, 'show']);
        Route::match(['put', 'patch'], 'nurture/sequences/{sequence}', [NurtureController::class, 'update']);
        Route::delete('nurture/sequences/{sequence}', [NurtureController::class, 'destroy']);
        Route::get('nurture/sequences/{sequence}/enrollments', [NurtureController::class, 'enrollments']);
        Route::post('nurture/sequences/{sequence}/test', [NurtureController::class, 'test'])->middleware('throttle:10,1');
    });

    // --- Relatórios ---
    Route::middleware('permission:report.read')->group(function (): void {
        Route::get('reports/funnel', [ReportController::class, 'funnel']);
        Route::get('reports/content', [ReportController::class, 'content']);
        Route::get('reports/stale-posts', [ReportController::class, 'stalePosts']);
        Route::get('ad-spends', [AdSpendController::class, 'index']);
    });
    Route::get('posts/{post}/performance', [ReportController::class, 'postPerformance'])->middleware('permission:post.read');
    Route::get('posts/{post}/link-suggestions', [ReportController::class, 'linkSuggestions'])->middleware('permission:post.read');

    // --- Integrações (Google Ads, Search Console, investimento) ---
    Route::middleware('permission:integration.manage')->group(function (): void {
        Route::post('ad-spends', [AdSpendController::class, 'store']);
        Route::match(['put', 'patch'], 'ad-spends/{spend}', [AdSpendController::class, 'update']);
        Route::delete('ad-spends/{spend}', [AdSpendController::class, 'destroy']);

        Route::get('integrations/google-ads', [GoogleAdsController::class, 'show']);
        Route::match(['put', 'patch'], 'integrations/google-ads', [GoogleAdsController::class, 'update']);
        Route::post('integrations/google-ads/activate', [GoogleAdsController::class, 'activate']);
        Route::post('integrations/google-ads/deactivate', [GoogleAdsController::class, 'deactivate']);
        Route::get('integrations/google-ads/conversion-actions', [GoogleAdsController::class, 'conversionActions']);
        Route::get('integrations/google-ads/uploads', [GoogleAdsController::class, 'uploads']);
        Route::post('integrations/google-ads/retry', [GoogleAdsController::class, 'retry']);

        Route::get('integrations/search-console', [SearchConsoleController::class, 'show']);
        Route::match(['put', 'patch'], 'integrations/search-console', [SearchConsoleController::class, 'update']);
        Route::post('integrations/search-console/activate', [SearchConsoleController::class, 'activate']);
        Route::post('integrations/search-console/deactivate', [SearchConsoleController::class, 'deactivate']);
        Route::post('integrations/search-console/sync', [SearchConsoleController::class, 'sync'])->middleware('throttle:5,1');
    });

    // --- WhatsApp ---
    Route::get('whatsapp/account', [WhatsAppAccountController::class, 'show'])->middleware('permission:whatsapp.manage');
    Route::match(['put', 'patch'], 'whatsapp/account', [WhatsAppAccountController::class, 'update'])->middleware('permission:whatsapp.manage');
    Route::post('whatsapp/account/activate', [WhatsAppAccountController::class, 'activate'])->middleware('permission:whatsapp.manage');
    Route::post('whatsapp/account/deactivate', [WhatsAppAccountController::class, 'deactivate'])->middleware('permission:whatsapp.manage');
    Route::middleware('permission:whatsapp.read')->group(function (): void {
        Route::get('whatsapp/conversations', [ConversationController::class, 'index']);
        Route::get('whatsapp/unread', [ConversationController::class, 'unread']);
        Route::get('whatsapp/conversations/{conversation}', [ConversationController::class, 'show']);
        Route::get('whatsapp/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::post('whatsapp/conversations/{conversation}/read', [ConversationController::class, 'read']);
        Route::get('whatsapp/messages/{message}/media', [ConversationController::class, 'media']);
    });
    Route::middleware('permission:whatsapp.send')->group(function (): void {
        Route::get('whatsapp/templates', [ConversationController::class, 'templates']);
        Route::post('whatsapp/conversations/{conversation}/messages', [ConversationController::class, 'send'])->middleware('throttle:whatsapp-send');
        Route::post('whatsapp/conversations/{conversation}/templates', [ConversationController::class, 'sendTemplate'])->middleware('throttle:whatsapp-send');
        Route::post('whatsapp/conversations/{conversation}/lead', [ConversationController::class, 'createLead'])->middleware('permission:lead.update');
        Route::post('leads/{lead}/whatsapp', [ConversationController::class, 'startForLead']);
    });

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
