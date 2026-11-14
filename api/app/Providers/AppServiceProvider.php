<?php

namespace App\Providers;

use App\Modules\ACL\Models\Role;
use App\Modules\ACL\Policies\RolePolicy;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Policies\LeadPolicy;
use App\Modules\Post\Models\Post;
use App\Modules\Post\Policies\PostPolicy;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Policies\TenantPolicy;
use App\Modules\User\Models\User;
use App\Modules\User\Policies\UserPolicy;
use App\Modules\Webhook\Models\Webhook;
use App\Modules\Webhook\Policies\WebhookPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use App\Modules\Crm\Listeners\NotifyNewLead;
use App\Modules\Crm\Listeners\SendAutoReply;
use App\Modules\GoogleAds\Listeners\QueueConversionUploads;
use App\Modules\Lead\Events\LeadCreated;
use App\Modules\Lead\Events\LeadStatusChanged;
use App\Modules\Lead\Events\LeadTagged;
use App\Modules\Nurture\Listeners\HandleNurtureTriggers;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configurePolicies();
        $this->configureEvents();
    }

    private function configureEvents(): void
    {
        Event::listen(LeadCreated::class, NotifyNewLead::class);
        Event::listen(LeadCreated::class, SendAutoReply::class);
        Event::listen(LeadStatusChanged::class, QueueConversionUploads::class);
        Event::listen(LeadStatusChanged::class, [HandleNurtureTriggers::class, 'handleStatusChanged']);
        Event::listen(LeadTagged::class, [HandleNurtureTriggers::class, 'handleTagged']);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->getKey() ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('leads', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('whatsapp-webhook', function (Request $request) {
            return Limit::perMinute(600)->by($request->ip());
        });

        RateLimiter::for('whatsapp-send', function (Request $request) {
            return Limit::perMinute(40)->by($request->user()?->getKey() ?: $request->ip());
        });

        RateLimiter::for('ai', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->getKey() ?: $request->ip());
        });
    }

    private function configurePolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(Webhook::class, WebhookPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
    }
}
