export function useStructuredData() {
  function organizationSchema() {
    return {
      '@context': 'https://schema.org',
      '@type': 'Organization',
      name: 'Boleto',
      url: window.location.origin,
      logo: `${window.location.origin}/logo_blanco.png`,
      description:
        'Plataforma líder de venta de boletos para conciertos, festivales, teatro, conferencias y más.',
      potentialAction: {
        '@type': 'SearchAction',
        target: {
          '@type': 'EntryPoint',
          urlTemplate: `${window.location.origin}/buscar?q={search_term_string}`,
        },
        'query-input': 'required name=search_term_string',
      },
    }
  }

  function webSiteSchema() {
    return {
      '@context': 'https://schema.org',
      '@type': 'WebSite',
      name: 'Boleto',
      url: window.location.origin,
      potentialAction: {
        '@type': 'SearchAction',
        target: {
          '@type': 'EntryPoint',
          urlTemplate: `${window.location.origin}/buscar?q={search_term_string}`,
        },
        'query-input': 'required name=search_term_string',
      },
    }
  }

  return { organizationSchema, webSiteSchema }
}
