---
# https://vitepress.dev/reference/default-theme-home-page
layout: home

hero:
  name: "Static PHP"
  tagline: "Build standalone PHP binary on Linux, macOS, FreeBSD, Windows, with PHP project together, with popular extensions included."
  image:
    src: /images/static-php_nobg.png
    alt: Static PHP CLI Logo
  actions:
    - theme: brand
      text: Get Started
      link: ./guide/

features:
  - title: Static CLI Binary
    details: You can easily compile a standalone php binary for general use. Including CLI, FPM sapi.
  - title: Micro Self-Extracted Executable
    details: You can compile a self-extracted executable and build with your php source code.
  - title: Dependency Management
    details: static-php-cli comes with dependency management and supports installation of different types of PHP extensions.
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
    <h2>Special Sponsors</h2>
    <p class="sponsors-description">
      Thank you to our amazing sponsors for supporting this project!
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
