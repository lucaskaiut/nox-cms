<?php

namespace App\Modules\WhatsApp\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Erro devolvido pela Graph API, com mensagem já traduzida para o painel. */
class WhatsAppApiException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $graphCode = null, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function fromResponse(array $body, int $httpStatus): self
    {
        $error = $body['error'] ?? [];
        $code = isset($error['code']) ? (int) $error['code'] : null;
        $detail = $error['error_data']['details'] ?? $error['message'] ?? 'erro desconhecido';

        $message = match ($code) {
            190 => 'Token de acesso inválido ou expirado. Gere um token permanente (usuário do sistema) e salve nas configurações.',
            10, 200, 294 => 'O token não tem permissão para esta ação (precisa de whatsapp_business_messaging e whatsapp_business_management).',
            100 => "Parâmetro inválido na Graph API: {$detail}",
            131047 => 'Passaram mais de 24 horas desde a última mensagem do contato. Envie um template aprovado.',
            131026 => 'Mensagem não entregue: o número não tem WhatsApp ou não pode receber mensagens.',
            131030 => 'Número de teste: adicione este destinatário à lista de números permitidos no app da Meta.',
            131056 => 'Muitas mensagens para este contato em pouco tempo. Aguarde e tente de novo.',
            132000, 132001, 132012, 132015 => "Template inválido ou com parâmetros errados: {$detail}",
            133010 => 'O número ainda não está registrado na Cloud API.',
            default => "WhatsApp recusou a operação ({$code}): {$detail}",
        };

        return new self($message, $code, $httpStatus >= 500 ? 502 : 422);
    }

    public function render(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $this->getMessage(), 'errors' => []], $this->status);
    }
}
