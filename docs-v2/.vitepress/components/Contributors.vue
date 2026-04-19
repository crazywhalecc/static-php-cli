<template>
  <div class="contributors-container">
    <div class="contributors-header">
      <h2>Contributors</h2>
      <p class="contributors-description">
        Thanks to all the amazing people who have contributed to this project!
      </p>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Loading contributors...</p>
    </div>

    <div v-else-if="error" class="error-state">
      <p>{{ error }}</p>
    </div>

    <div v-else class="contributors-grid">
      <a
        v-for="contributor in contributors"
        :key="contributor.id"
        :href="contributor.html_url"
        target="_blank"
        rel="noopener noreferrer"
        class="contributor-card"
        :title="contributor.login"
      >
        <img
          :src="contributor.avatar_url"
          :alt="contributor.login"
          class="contributor-avatar"
          loading="lazy"
        />
        <div class="contributor-name">{{ contributor.login }}</div>
      </a>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';

interface Contributor {
  id: number;
  login: string;
  avatar_url: string;
  html_url: string;
  contributions: number;
}

const contributors = ref<Contributor[]>([]);
const loading = ref(true);
const error = ref('');

const fetchContributors = async () => {
  try {
    loading.value = true;
    error.value = '';

    const response = await fetch(
      'https://api.github.com/repos/crazywhalecc/static-php-cli/contributors?per_page=24'
    );

    if (!response.ok) {
      throw new Error('Failed to fetch contributors');
    }

    const data = await response.json();
    contributors.value = data;
  } catch (err) {
    error.value = 'Failed to load contributors. Please try again later.';
    console.error('Error fetching contributors:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  fetchContributors();
});
</script>

<style scoped>
.contributors-container {
  margin: 48px auto;
  padding: 32px 24px;
  max-width: 1152px;
  background: linear-gradient(135deg, var(--vp-c-bg-soft) 0%, var(--vp-c-bg) 100%);
  border-radius: 16px;
  border: 1px solid var(--vp-c-divider);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
}

.contributors-header {
  text-align: center;
  margin-bottom: 24px;
}

.contributors-header h2 {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0 0 8px 0;
  background: linear-gradient(120deg, var(--vp-c-brand-1), var(--vp-c-brand-2));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.contributors-description {
  font-size: 0.95rem;
  color: var(--vp-c-text-2);
  margin: 0;
  line-height: 1.5;
}

.loading-state,
.error-state {
  text-align: center;
  padding: 40px 20px;
  color: var(--vp-c-text-2);
}

.spinner {
  width: 40px;
  height: 40px;
  margin: 0 auto 16px;
  border: 4px solid var(--vp-c-divider);
  border-top-color: var(--vp-c-brand-1);
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.contributors-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
  gap: 16px;
}

.contributor-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 12px;
  background: var(--vp-c-bg);
  border-radius: 12px;
  border: 1px solid var(--vp-c-divider);
  transition: all 0.3s ease;
  text-decoration: none;
  color: var(--vp-c-text-1);
}

.contributor-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  border-color: var(--vp-c-brand-1);
}

.contributor-avatar {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  border: 2px solid var(--vp-c-divider);
  transition: all 0.3s ease;
  margin-bottom: 8px;
}

.contributor-card:hover .contributor-avatar {
  border-color: var(--vp-c-brand-1);
  transform: scale(1.05);
}

.contributor-name {
  font-size: 12px;
  font-weight: 500;
  text-align: center;
  word-break: break-word;
  max-width: 100%;
}

@media (max-width: 768px) {
  .contributors-container {
    margin: 32px 16px;
    padding: 24px 16px;
  }

  .contributors-header h2 {
    font-size: 1.25rem;
  }

  .contributors-description {
    font-size: 0.9rem;
  }

  .contributors-grid {
    grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
    gap: 12px;
  }

  .contributor-card {
    padding: 8px;
  }

  .contributor-avatar {
    width: 48px;
    height: 48px;
  }

  .contributor-name {
    font-size: 11px;
  }
}
</style>
