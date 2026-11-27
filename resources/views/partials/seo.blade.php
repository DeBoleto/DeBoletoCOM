@php($seo = $page['props']['seo'] ?? null)
@if (is_array($seo))
        <title inertia>{{ $seo['fullTitle'] }}</title>
        <meta inertia="description" name="description" content="{{ $seo['description'] }}">
        <meta inertia="robots" name="robots" content="{{ $seo['robots'] }}">
        <link inertia="canonical" rel="canonical" href="{{ $seo['url'] }}">
        <meta inertia="og:site_name" property="og:site_name" content="{{ $seo['siteName'] }}">
        <meta inertia="og:locale" property="og:locale" content="es_MX">
        <meta inertia="og:type" property="og:type" content="{{ $seo['type'] }}">
        <meta inertia="og:url" property="og:url" content="{{ $seo['url'] }}">
        <meta inertia="og:title" property="og:title" content="{{ $seo['title'] ?: $seo['siteName'] }}">
        <meta inertia="og:description" property="og:description" content="{{ $seo['description'] }}">
        <meta inertia="og:image" property="og:image" content="{{ $seo['image']['url'] }}">
@if (! empty($seo['image']['width']) && ! empty($seo['image']['height']))
        <meta inertia="og:image:width" property="og:image:width" content="{{ $seo['image']['width'] }}">
        <meta inertia="og:image:height" property="og:image:height" content="{{ $seo['image']['height'] }}">
@endif
        <meta inertia="twitter:card" name="twitter:card" content="summary_large_image">
        <meta inertia="twitter:title" name="twitter:title" content="{{ $seo['title'] ?: $seo['siteName'] }}">
        <meta inertia="twitter:description" name="twitter:description" content="{{ $seo['description'] }}">
        <meta inertia="twitter:image" name="twitter:image" content="{{ $seo['image']['url'] }}">
@foreach ($seo['schemas'] as $index => $schema)
        <script inertia="schema-{{ $index }}" type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
@else
        <title inertia>{{ config('app.name') }}</title>
@endif
