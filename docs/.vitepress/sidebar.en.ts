export default {
  '/en/guide/': [
    {
      text: 'Basic Build Guides',
      items: [
        {text: 'Guide', link: '/en/guide/'},
        {text: 'Build (Local)', link: '/en/guide/manual-build'},
        {text: 'Build (CI)', link: '/en/guide/action-build'},
        {text: 'Supported Extensions', link: '/en/guide/extensions'},
        {text: 'Extension Notes', link: '/en/guide/extension-notes'},
        {text: 'Build Command Generator', link: '/en/guide/cli-generator'},
        {text: 'Environment Variables', link: '/en/guide/env-vars', collapsed: true,},
        {text: 'Dependency Table', link: '/en/guide/deps-map'},
      ]
    },
    {
      text: 'Extended Build Guides',
      items: [
        {text: 'Troubleshooting', link: '/en/guide/troubleshooting'},
        {text: 'Build on Windows', link: '/en/guide/build-on-windows'},
        {text: 'Build with GNU libc', link: '/en/guide/build-with-glibc'},
      ],
    }
  ],
  '/en/develop/': [
    {
      text: 'Development',
      items: [
        {text: 'Get Started', link: '/en/develop/'},
        {text: 'Project Structure', link: '/en/develop/structure'},
        {text: 'PHP Source Modification', link: '/en/develop/php-src-changes'},
      ],
    },
    {
      text: 'Module',
      items: [
        {text: 'Doctor ', link: '/en/develop/doctor-module'},
        {text: 'Source', link: '/en/develop/source-module'},
      ]
    },
    {
      text: 'Extra',
      items: [
        {text: 'Compilation Tools', link: '/en/develop/system-build-tools'},
      ]
    }
  ],
  '/en/contributing/': [
    {
      text: 'Contributing',
      items: [
        {text: 'Contributing', link: '/en/contributing/'},
      ],
    }
  ],
};
