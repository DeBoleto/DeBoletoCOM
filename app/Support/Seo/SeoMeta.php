<?php

namespace App\Support\Seo;

use Illuminate\Support\Str;

/**
 * Metadatos de una página pública (título, descripción, Open Graph, Twitter,
 * canonical, robots y JSON-LD). Se mandan como el prop `seo` de Inertia: el
 * Blade raíz los imprime en el HTML para los robots (Google, Facebook,
 * WhatsApp) y el componente SeoHead los mantiene al navegar en el cliente.
 */
final class SeoMeta
{
    public const DEFAULT_DESCRIPTION = 'Compra boletos para conciertos, festivales, teatro, deportes y más eventos en México.';

    private string $description;

    private ?string $url = null;

    private ?array $image = null;

    private string $type = 'website';

    private bool $indexable = true;

    private array $schemas = [];

    private function __construct(private string $title, string $description)
    {
        $this->description = self::clean($description);
    }

    public static function make(string $title, string $description = ''): self
    {
        return new self(trim($title), $description !== '' ? $description : self::DEFAULT_DESCRIPTION);
    }

    public static function defaults(): self
    {
        return self::make('')->image(asset('og-image.png'), 1200, 630);
    }

    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function image(?string $url, ?int $width = null, ?int $height = null): self
    {
        $this->image = $url ? ['url' => $url, 'width' => $width, 'height' => $height] : null;

        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function indexable(bool $indexable = true): self
    {
        $this->indexable = $indexable;

        return $this;
    }

    public function schema(array ...$schemas): self
    {
        foreach ($schemas as $schema) {
            $this->schemas[] = ['@context' => 'https://schema.org', ...$schema];
        }

        return $this;
    }

    public function toArray(): array
    {
        $siteName = (string) config('app.name');
        $image = $this->image ?? ['url' => asset('og-image.png'), 'width' => 1200, 'height' => 630];

        return [
            'title' => $this->title,
            'fullTitle' => $this->title !== '' ? $this->title.' — '.$siteName : $siteName,
            'description' => $this->description,
            'url' => self::absolute($this->url ?? request()->url()),
            'image' => [...$image, 'url' => self::absolute($image['url'])],
            'type' => $this->type,
            'siteName' => $siteName,
            'robots' => $this->indexable ? 'index, follow' : 'noindex, nofollow',
            'schemas' => $this->schemas,
        ];
    }

    public static function absolute(string $url): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        if (Str::startsWith($url, '//')) {
            return 'https:'.$url;
        }

        return url($url);
    }

    public static function clean(string $text, int $limit = 160): string
    {
        $text = html_entity_decode(preg_replace('/<[^>]*>/', ' ', $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text));

        return Str::limit($text, $limit, '…');
    }
}
