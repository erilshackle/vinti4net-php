import { defineConfig } from 'vitepress'

export default defineConfig({
  lang: 'pt-PT',
  title: 'Vinti4Net PHP',
  description: 'SDK PHP para pagamentos com a Rede Vinti4 / SISP em Cabo Verde',
  base: '/vinti4net-php/',
  cleanUrls: true,
  head: [['link', { rel: 'icon', href: '/vinti4net-php/assets/icon.png' }]],
  themeConfig: {
    logo: '/assets/logo.png',
    siteTitle: 'Vinti4Net PHP',
    nav: [
      { text: 'Guia', link: '/quickstart' },
      { text: 'API', link: '/api' },
      { text: 'Notas de versão', link: '/release-notes' }
    ],
    sidebar: [
      { text: 'Início', link: '/' },
      { text: 'Primeiros passos', items: [
        { text: 'Instalação', link: '/installation' },
        { text: 'Configuração', link: '/configuration' },
        { text: 'Guia rápido', link: '/quickstart' },
        { text: 'Integração completa', link: '/payment-integration-guide' }
      ] },
      { text: 'Pagamentos', items: [
        { text: 'Operações', link: '/payments' },
        { text: 'Billing 3DS', link: '/billing' },
        { text: 'Reembolsos', link: '/refunds' }
      ] },
      { text: 'Respostas e recibos', items: [
        { text: 'Processar respostas', link: '/responses' },
        { text: 'Campos da resposta', link: '/response-fields' },
        { text: 'Recibos', link: '/receipt' },
        { text: 'Conversão DCC', link: '/dcc' }
      ] },
      { text: 'Referência da API', link: '/api' },
      { text: 'Segurança', link: '/security' },
      { text: 'Perguntas frequentes', link: '/faq' },
      { text: 'Notas de versão', link: '/release-notes' },
      { text: 'Sobre', link: '/about' }
    ],
    socialLinks: [{ icon: 'github', link: 'https://github.com/erilshackle/vinti4net-php' }],
    search: { provider: 'local' },
    outline: { label: 'Nesta página' },
    docFooter: { prev: 'Anterior', next: 'Próxima' },
    sidebarMenuLabel: 'Menu',
    darkModeSwitchLabel: 'Tema',
    returnToTopLabel: 'Voltar ao topo',
    editLink: {
      pattern: 'https://github.com/erilshackle/vinti4net-php/edit/main/docs/:path',
      text: 'Editar esta página no GitHub'
    },
    footer: { message: 'Projeto comunitário independente da SISP. Distribuído sob licença MIT.' }
  }
})
