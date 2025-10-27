---
# https://vitepress.dev/reference/default-theme-home-page
layout: home

hero:
  name: "Static PHP"
  tagline: "在 Linux、macOS、FreeBSD、Windows 上与 PHP 项目一起构建独立的 PHP 二进制文件，并包含流行的扩展。"
  image:
    src: /images/static-php_nobg.png
    alt: Static PHP CLI Logo
  actions:
    - theme: brand
      text: 开始使用
      link: ./guide/

features:
  - icon: 🚀
    title: 静态 CLI 二进制
    details: 您可以轻松地编译一个独立的 PHP 二进制文件以供通用使用，包括 CLI、FPM SAPI。
  - icon: 📦
    title: Micro 自解压可执行文件
    details: 您可以编译一个自解压的可执行文件，并将 PHP 源代码与二进制文件打包在一起。
  - icon: 🔧
    title: 依赖管理
    details: static-php-cli 附带依赖项管理，支持安装不同类型的 PHP 扩展。
---

<script setup>
import {VPSponsors} from "vitepress/theme";
import Contributors from '../.vitepress/components/Contributors.vue';

const sponsors = [
  { name: 'Beyond Code', img: '/images/beyondcode-seeklogo.png', url: 'https://beyondco.de/' },
  { name: 'NativePHP', img: '/images/nativephp-logo.svg', url: 'https://nativephp.com/' },
];
</script>

<div class="sponsors-section">
  <div class="sponsors-header">
    <h2>💝 特别赞助商</h2>
    <p class="sponsors-description">
      感谢我们出色的赞助商对本项目的支持！
    </p>
  </div>
  <VPSponsors :data="sponsors"/>
</div>

<style scoped>
.sponsors-section {
  margin: 48px auto;
  padding: 32px 24px;
  max-width: 1152px;
  background: linear-gradient(135deg, var(--vp-c-bg-soft) 0%, var(--vp-c-bg) 100%);
  border-radius: 16px;
  border: 1px solid var(--vp-c-divider);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
  transition: all 0.3s ease;
}

.sponsors-section:hover {
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
}

.sponsors-header {
  text-align: center;
  margin-bottom: 24px;
}

.sponsors-header h2 {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0 0 8px 0;
  background: linear-gradient(120deg, var(--vp-c-brand-1), var(--vp-c-brand-2));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.sponsors-description {
  font-size: 0.95rem;
  color: var(--vp-c-text-2);
  margin: 0;
  line-height: 1.5;
}

@media (max-width: 768px) {
  .sponsors-section {
    margin: 32px 16px;
    padding: 24px 16px;
  }
  
  .sponsors-header h2 {
    font-size: 1.25rem;
  }
  
  .sponsors-description {
    font-size: 0.9rem;
  }
}

/* Hero logo styling */
:deep(.VPImage.image-src) {
  border-radius: 20px;
  background: linear-gradient(135deg, var(--vp-c-bg-soft) 0%, var(--vp-c-default-soft) 100%);
  padding: 40px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

:deep(.VPImage.image-src:hover) {
  transform: translateY(-4px);
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

/* Dark mode adjustments for logo */
.dark :deep(.VPImage.image-src) {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.02) 100%);
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
  opacity: 0.9;
}

.dark :deep(.VPImage.image-src:hover) {
  opacity: 1;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.7);
}

/* Additional styling for the logo image itself */
:deep(.VPImage.image-src img) {
  max-height: 280px;
  width: auto;
}

@media (max-width: 768px) {
  :deep(.VPImage.image-src) {
    padding: 24px;
  }
  
  :deep(.VPImage.image-src img) {
    max-height: 200px;
  }
}
</style>

<Contributors />
