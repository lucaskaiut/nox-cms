<?php

namespace App\Modules\ACL\Enums;

enum Permission: string
{
    case USER_CREATE = 'user.create';
    case USER_READ = 'user.read';
    case USER_UPDATE = 'user.update';
    case USER_DELETE = 'user.delete';

    case TENANT_READ = 'tenant.read';
    case TENANT_UPDATE = 'tenant.update';

    case ROLE_CREATE = 'role.create';
    case ROLE_READ = 'role.read';
    case ROLE_UPDATE = 'role.update';
    case ROLE_DELETE = 'role.delete';

    case API_TOKEN_CREATE = 'api-token.create';
    case API_TOKEN_READ = 'api-token.read';
    case API_TOKEN_UPDATE = 'api-token.update';
    case API_TOKEN_DELETE = 'api-token.delete';

    // --- Projeto: Posts ---
    case POST_CREATE = 'post.create';
    case POST_READ = 'post.read';
    case POST_UPDATE = 'post.update';
    case POST_DELETE = 'post.delete';
    case POST_PUBLISH = 'post.publish';

    // --- Template: Webhooks ---
    case WEBHOOK_CREATE = 'webhook.create';
    case WEBHOOK_READ = 'webhook.read';
    case WEBHOOK_UPDATE = 'webhook.update';
    case WEBHOOK_DELETE = 'webhook.delete';

    // --- Projeto: AI Publisher ---
    case AI_PUBLISH = 'ai.publish';
    case AI_READ = 'ai.read';

    // --- Projeto: Leads ---
    case LEAD_CREATE = 'lead.create';
    case LEAD_READ = 'lead.read';
    case LEAD_UPDATE = 'lead.update';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
