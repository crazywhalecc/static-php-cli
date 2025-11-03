import sidebarEn from "./sidebar.en";
import sidebarZh from "./sidebar.zh";


// https://vitepress.dev/reference/site-config
export default {
  title: "Static PHP",
  description: "Build single static PHP binary, with PHP project together, with popular extensions included.",
  locales: {
    en: {
      label: 'English',
      lang: 'en',
      themeConfig: {
        nav: [
          {text: 'Guide', link: '/en/guide/',},
          {text: 'Advanced', link: '/en/develop/'},
          {text: 'Contributing', link: '/en/contributing/'},
          {text: 'FAQ', link: '/en/faq/'},
        ],
        sidebar: sidebarEn,
        footer: {
          message: 'Released under the MIT License.',
          copyright: 'Copyright © 2023-present crazywhalecc'
        }
      },
    },
    zh: {
      label: '简体中文',
      lang: 'zh', // optional, will be added  as `lang` attribute on `html` tag
      themeConfig: {
        nav: [
          {text: '构建指南', link: '/zh/guide/'},
          {text: '进阶', link: '/zh/develop/'},
          {text: '贡献', link: '/zh/contributing/'},
          {text: 'FAQ', link: '/zh/faq/'},
        ],
        sidebar: sidebarZh,
        footer: {
          message: 'Released under the MIT License.',
          copyright: 'Copyright © 2023-present crazywhalecc'
        }
      },
    }
  },
  themeConfig: {
    // https://vitepress.dev/reference/default-theme-config
    logo: '/images/static-php_nobg.png',
    nav: [],
    socialLinks: [
      {icon: 'github', link: 'https://github.com/crazywhalecc/static-php-cli'}
    ],
    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2023-present crazywhalecc'
    },
    search: {
      provider: 'algolia',
      options: {
        appId: 'IHJHUB1SF1',
        apiKey: '8266d31cc2ffbd0e059f1c6e5bdaf8fc',
        indexName: 'static-php docs',
      },
    },
  }
}
