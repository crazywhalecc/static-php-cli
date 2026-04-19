import sidebarEn from "./sidebar.en";
import sidebarZh from "./sidebar.zh";

// https://vitepress.dev/reference/site-config
export default {
  title: "StaticPHP",
  description: "A powerful tool designed for building portable executables including PHP, extensions, and more.",
  locales: {
    en: {
      label: 'English',
      lang: 'en',
      themeConfig: {
        nav: [
          { text: 'Guide', link: '/en/guide/' },
          { text: 'Develop', link: '/en/develop/' },
          { text: 'Contributing', link: '/en/contributing/' },
          { text: 'FAQ', link: '/en/faq/' },
          {
            text: 'v3 (alpha)',
            items: [
              { text: 'v3 (alpha)', link: '/en/' },
              { text: 'v2', link: '/v2/en/guide/' },
            ],
          },
        ],
        sidebar: sidebarEn,
        footer: {
          message: 'Released under the MIT License.',
          copyright: 'Copyright © 2023-present crazywhalecc',
        },
      },
    },
    zh: {
      label: '简体中文',
      lang: 'zh',
      themeConfig: {
        nav: [
          { text: '构建指南', link: '/zh/guide/' },
          { text: '开发者', link: '/zh/develop/' },
          { text: '贡献', link: '/zh/contributing/' },
          { text: 'FAQ', link: '/zh/faq/' },
          {
            text: 'v3 (alpha)',
            items: [
              { text: 'v3 (alpha)', link: '/zh/' },
              { text: 'v2', link: '/v2/zh/guide/' },
            ],
          },
        ],
        sidebar: sidebarZh,
        footer: {
          message: 'Released under the MIT License.',
          copyright: 'Copyright © 2023-present crazywhalecc',
        },
      },
    },
  },
  themeConfig: {
    logo: '/images/static-php_nobg.png',
    nav: [],
    socialLinks: [
      { icon: 'github', link: 'https://github.com/crazywhalecc/static-php-cli' },
    ],
    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2023-present crazywhalecc',
    },
    externalLinkIcon: true,
    search: {
      provider: 'algolia',
      options: {
        appId: 'IHJHUB1SF1',
        apiKey: '8266d31cc2ffbd0e059f1c6e5bdaf8fc',
        indexName: 'static-php docs',
        askAi: {
          assistantId: 'b72369b2-60a5-461d-902c-5c18d8c05902',
          agentStudio: true,
          sidePanel: true,
        },
      },
    },
  },
}

