export default {
  '/zh/guide/': [
    {
      text: '快速上手',
      items: [
        { text: '概览', link: '/zh/guide/' },
        { text: '安装', link: '/zh/guide/installation' },
        { text: '第一次构建', link: '/zh/guide/first-build' },
        { text: 'PHP SAPI 构建参考', link: '/zh/guide/sapi-reference' },
        { text: '命令行参考', link: '/zh/guide/cli-reference' },
      ],
    },
    {
      text: '扩展',
      items: [
        { text: '支持的扩展列表', link: '/zh/guide/extensions' },
        { text: '扩展注意事项', link: '/zh/guide/extension-notes' },
        { text: '命令生成器', link: '/zh/guide/cli-generator' },
      ],
    },
    {
      text: '参考',
      items: [
        { text: '环境变量', link: '/zh/guide/env-vars' },
        { text: '依赖关系图', link: '/zh/guide/deps-map' },
        { text: '故障排除', link: '/zh/guide/troubleshooting' },
      ],
    },
  ],
  '/zh/develop/': [
    {
      text: '开发者指南',
      items: [
        { text: '开发简介', link: '/zh/develop/' },
        { text: '项目结构', link: '/zh/develop/structure' },
        { text: '对 PHP 源码的修改', link: '/zh/develop/php-src-changes' },
      ],
    },
    {
      text: '核心概念',
      items: [
        { text: 'Package 模型', link: '/zh/develop/package-model' },
        { text: 'Registry 与插件系统', link: '/zh/develop/registry' },
        { text: '构建生命周期', link: '/zh/develop/build-lifecycle' },
      ],
    },
    {
      text: '模块',
      items: [
        { text: 'Doctor 环境检查', link: '/zh/develop/doctor-module' },
        { text: '资源模块', link: '/zh/develop/source-module' },
        { text: 'craft.yml 配置', link: '/zh/develop/craft-yml' },
        { text: '编译工具', link: '/zh/develop/system-build-tools' },
      ],
    },
    {
      text: 'Vendor 模式',
      items: [
        { text: '简介', link: '/zh/develop/vendor-mode/' },
        { text: '编写 Package 类', link: '/zh/develop/vendor-mode/package-classes' },
        { text: '注解参考', link: '/zh/develop/vendor-mode/annotations' },
        { text: '依赖注入', link: '/zh/develop/vendor-mode/dependency-injection' },
        { text: '生命周期 Hook', link: '/zh/develop/vendor-mode/lifecycle-hooks' },
      ],
    },
  ],
  '/zh/contributing/': [
    {
      text: '贡献指南',
      items: [
        { text: '贡献指南', link: '/zh/contributing/' },
      ],
    },
  ],
  '/zh/faq/': [
    {
      text: 'FAQ',
      items: [
        { text: '常见问题', link: '/zh/faq/' },
      ],
    },
  ],
};
