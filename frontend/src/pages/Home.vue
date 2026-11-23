<template>
  <div class="landing" v-if="!loading">
    <section class="hero">
      <div class="hero-badge">💘 LeSwipe franco-matcha</div>
      <h1 class="hero-title">
        Swipe. Matche.<br />
        <span class="hero-gradient">Rencarde.</span>
      </h1>
      <p class="hero-sub">
        Matcha connecte les gens autour de critères qui comptent vraiment :
        proximité, affinités et étincelles. C'est le romantisme, version 2025.
      </p>
      <div class="hero-actions">
        <template v-if="session.user">
          <router-link class="btn-grad" to="/swipe">Continuer à swiper →</router-link>
          <router-link class="btn-ghost" to="/matches">Voir mes matchs</router-link>
        </template>
        <template v-else>
          <router-link class="btn-grad" to="/register">Créer mon compte</router-link>
          <router-link class="btn-ghost" to="/login">J'ai déjà un compte</router-link>
        </template>
      </div>
      <div class="hero-card-mock">
        <div class="mock-card">
          <div class="mock-photo">🔥</div>
          <div class="mock-name">Matcha <span>2025</span></div>
          <div class="mock-tags"><span>#chalant</span><span>#vegan</span><span>#geek</span></div>
        </div>
      </div>
    </section>

    <section class="features">
      <div class="feature">
        <div class="feature-icon feature-icon--pink">💘</div>
        <h3>Match instantané</h3>
        <p>Un like réciproque et c'est parti : « C'est un Match ! » s'affiche, le chat s'ouvre.</p>
      </div>
      <div class="feature">
        <div class="feature-icon feature-icon--violet">📍</div>
        <h3>Profils proches de toi</h3>
        <p>Suggestions triées par proximité, affinités de tags et popularité.</p>
      </div>
      <div class="feature">
        <div class="feature-icon feature-icon--orange">⚡</div>
        <h3>Chat temps réel</h3>
        <p>Messages et notifications live en moins de 10 secondes chrono.</p>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { session } from '../stores/session.js';
import { refreshSession } from '../api.js';

const router = useRouter();
const loading = ref(true);

onMounted(async () => {
  await refreshSession();
  if (session.user) {
    router.replace('/swipe');
  }
  loading.value = false;
});
</script>

<style scoped>
.landing {
  width: 100%;
  max-width: 900px;
  text-align: center;
}

.hero-badge {
  display: inline-block;
  background: var(--primary-soft);
  color: var(--primary);
  font-weight: 800;
  font-size: 0.8rem;
  padding: 6px 16px;
  border-radius: 999px;
  margin-bottom: 18px;
}

.hero-title {
  font-size: 3.2rem;
  font-weight: 900;
  line-height: 1.1;
  letter-spacing: -1.5px;
  margin: 0 0 16px;
  color: var(--text);
}

.hero-gradient {
  background: var(--grad);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-sub {
  color: var(--text-muted);
  font-size: 1.05rem;
  max-width: 520px;
  margin: 0 auto 26px;
  line-height: 1.6;
}

.hero-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 40px;
}

.btn-grad {
  background: var(--grad);
  color: #fff;
  font-weight: 800;
  padding: 12px 30px;
  border-radius: 999px;
  text-decoration: none;
  box-shadow: var(--shadow);
  transition: transform 0.15s;
}

.btn-grad:hover { transform: scale(1.04); }

.btn-ghost {
  border: 2px solid var(--primary);
  color: var(--primary);
  font-weight: 700;
  padding: 10px 26px;
  border-radius: 999px;
  text-decoration: none;
  transition: all 0.15s;
}

.btn-ghost:hover { background: var(--primary-soft); }

.hero-card-mock { display: flex; justify-content: center; }

.mock-card {
  background: var(--card);
  border-radius: 20px;
  box-shadow: var(--shadow-lg);
  padding: 28px 48px;
  transform: rotate(-2deg);
}

.mock-photo { font-size: 3rem; }

.mock-name {
  font-weight: 900;
  font-size: 1.3rem;
  color: var(--text);
}

.mock-name span { color: var(--text-muted); font-weight: 500; }

.mock-tags {
  display: flex;
  gap: 6px;
  justify-content: center;
  margin-top: 10px;
}

.mock-tags span {
  background: var(--primary-soft);
  color: var(--primary);
  font-size: 0.75rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 999px;
}

.features {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 18px;
  margin-top: 48px;
}

.feature {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 22px;
  box-shadow: var(--shadow-sm);
  text-align: left;
}

.feature h3 {
  font-size: 1rem;
  font-weight: 800;
  margin: 10px 0 6px;
  color: var(--text);
}

.feature p {
  font-size: 0.85rem;
  color: var(--text-muted);
  margin: 0;
  line-height: 1.5;
}

.feature-icon {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
}

.feature-icon--pink { background: var(--primary-soft); }
.feature-icon--violet { background: var(--info-soft); }
.feature-icon--orange { background: var(--success-soft); }

@media (max-width: 576px) {
  .hero-title { font-size: 2.3rem; }
}
</style>
