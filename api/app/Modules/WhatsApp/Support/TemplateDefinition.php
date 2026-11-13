<?php

namespace App\Modules\WhatsApp\Support;

/**
 * Template aprovado da Meta, reduzido ao que o painel consegue enviar:
 * variáveis de texto no cabeçalho e no corpo (posicionais {{1}} ou nomeadas
 * {{nome}}). Cabeçalhos com mídia ou localização não são suportados ainda.
 */
final class TemplateDefinition
{
    private const PLACEHOLDER = '/\{\{\s*(\w+)\s*\}\}/';

    /**
     * @param  list<string>  $headerParams
     * @param  list<string>  $bodyParams
     */
    public function __construct(
        public readonly string $name,
        public readonly string $language,
        public readonly string $category,
        public readonly ?string $headerText,
        public readonly string $body,
        public readonly ?string $footer,
        public readonly array $headerParams,
        public readonly array $bodyParams,
        public readonly bool $named,
        public readonly bool $supported,
    ) {}

    public static function fromGraph(array $template): self
    {
        $components = collect($template['components'] ?? []);
        $header = $components->firstWhere('type', 'HEADER');
        $body = $components->firstWhere('type', 'BODY');
        $footer = $components->firstWhere('type', 'FOOTER');
        $headerIsText = $header === null || ($header['format'] ?? 'TEXT') === 'TEXT';

        return new self(
            name: $template['name'],
            language: $template['language'],
            category: $template['category'] ?? '',
            headerText: $headerIsText ? ($header['text'] ?? null) : null,
            body: $body['text'] ?? '',
            footer: $footer['text'] ?? null,
            headerParams: self::placeholders($headerIsText ? ($header['text'] ?? '') : ''),
            bodyParams: self::placeholders($body['text'] ?? ''),
            named: ($template['parameter_format'] ?? 'POSITIONAL') === 'NAMED',
            supported: $headerIsText,
        );
    }

    /** @return list<string> */
    private static function placeholders(string $text): array
    {
        preg_match_all(self::PLACEHOLDER, $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  array<string, string>  $header  valores por variável
     * @param  array<string, string>  $body
     * @return list<array>
     */
    public function components(array $header, array $body): array
    {
        $components = [];
        foreach (['header' => [$this->headerParams, $header], 'body' => [$this->bodyParams, $body]] as $type => [$params, $values]) {
            if ($params === []) {
                continue;
            }
            $components[] = [
                'type' => $type,
                'parameters' => array_map(fn (string $param) => array_filter([
                    'type' => 'text',
                    'parameter_name' => $this->named ? $param : null,
                    'text' => (string) ($values[$param] ?? ''),
                ], fn ($v) => $v !== null), $params),
            ];
        }

        return $components;
    }

    /** Texto como o contato vai ver, para guardar no histórico. */
    public function render(array $header, array $body): string
    {
        $fill = fn (string $text, array $values) => preg_replace_callback(
            self::PLACEHOLDER,
            fn ($m) => (string) ($values[$m[1]] ?? $m[0]),
            $text,
        );

        return trim(implode("\n\n", array_filter([
            $this->headerText ? $fill($this->headerText, $header) : null,
            $fill($this->body, $body),
            $this->footer,
        ])));
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name, 'language' => $this->language, 'category' => $this->category,
            'header' => $this->headerText, 'body' => $this->body, 'footer' => $this->footer,
            'header_params' => $this->headerParams, 'body_params' => $this->bodyParams,
            'supported' => $this->supported,
        ];
    }
}
