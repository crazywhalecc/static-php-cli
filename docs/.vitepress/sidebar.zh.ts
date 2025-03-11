export default {
  '/zh/guide/': [
    {
      text: '构建指南',
      items: [
        {text: '指南', link: '/zh/guide/'},
        {text: '本地构建', link: '/zh/guide/manual-build'},
        {text: 'Actions 构建', link: '/zh/guide/action-build'},
        {text: '扩展列表', link: '/zh/guide/extensions'},
        {text: '扩展注意事项', link: '/zh/guide/extension-notes'},
        {text: '编译命令生成器', link: '/zh/guide/cli-generator'},
        {text: '环境变量列表', link: '/zh/guide/env-vars'},
        {text: '依赖关系图表', link: '/zh/guide/deps-map'},
      ]
    },
    {
      text: '扩展构建指南',
      items: [
        {text: '故障排除', link: '/zh/guide/troubleshooting'},
        {text: '在 Windows 上构建', link: '/zh/guide/build-on-windows'},
        {text: '构建 GNU libc 兼容的二进制', link: '/zh/guide/build-with-glibc'},
      ],
    }
  ],
  '/zh/develop/': [
    {
      text: '开发指南',
      items: [
        { text: '开发简介', link: '/zh/develop/' },
        { text: '项目结构简介', link: '/zh/develop/structure' },
        {text: '对 PHP 源码的修改', link: '/zh/develop/php-src-changes'},
      ],
    },
    {
      text: '模块',
      items: [
        { text: 'Doctor 环境检查工具', link: '/zh/develop/doctor-module' },
        { text: '资源模块', link: '/zh/develop/source-module' },
      ]
    },
    {
      text: '其他',
      items: [
        {text: '系统编译工具', link: '/zh/develop/system-build-tools'},
      ]
    }
  ],
  '/zh/contributing/': [
    {
      text: '贡献指南',
      items: [
        {text: '贡献指南', link: '/zh/contributing/'},
      ],
    }
  ],
};
