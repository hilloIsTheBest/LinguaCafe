<template>
  <div class="pa-4">
    <v-tabs v-model="tab" background-color="primary" dark>
      <v-tab>Stats</v-tab>
      <v-tab>Shop</v-tab>
      <v-tab>Leaderboard</v-tab>
    </v-tabs>
    <v-tabs-items v-model="tab">
      <!-- Stats -->
      <v-tab-item>
        <v-card flat class="pa-4">
          <div class="text-h6 mb-2">Your Stats</div>
          <div class="mb-1">Coins: <b>{{ stats.coins }}</b></div>
          <div class="mb-1">Streak: <b>{{ stats.streak }}</b> (Best: {{ stats.bestStreak }})</div>
          <div class="mb-1">Saved words: <b>{{ stats.savedWords }}</b><template v-if="stats.savedWordsLimit && stats.savedWordsLimit > 0"> / Limit: <b>{{ stats.savedWordsLimit }}</b></template></div>
          <v-btn small color="primary" class="mt-3" @click="loadStats">Refresh</v-btn>
        </v-card>
      </v-tab-item>
      <!-- Shop -->
      <v-tab-item>
        <v-card flat class="pa-4">
          <div class="text-h6 mb-2">Shop</div>
          <v-simple-table class="no-hover border rounded-lg">
            <thead><tr><th>Item</th><th>Price</th><th></th></tr></thead>
            <tbody>
              <tr v-for="(price, key) in shop" :key="key">
                <td>{{ key }}</td>
                <td>{{ price }}</td>
                <td>
                  <v-chip v-if="owned(key)" small color="success" text-color="white">Owned</v-chip>
                  <v-btn v-else small color="primary" @click="purchase(key, price)" :disabled="loading">Buy</v-btn>
                </td>
              </tr>
            </tbody>
          </v-simple-table>
        </v-card>
      </v-tab-item>
      <!-- Leaderboard -->
      <v-tab-item>
        <v-card flat class="pa-4">
          <div class="text-h6 mb-2">Leaderboard</div>
          <div class="d-flex flex-wrap">
            <div class="mr-8">
              <div class="subtitle-2 mb-2">Top Coins</div>
              <v-simple-table class="no-hover border rounded-lg">
                <tbody>
                  <tr v-for="(u, idx) in leaderboard.coins" :key="'c'+idx"><td>{{ u.name }}</td><td class="text-right">{{ u.coins }}</td></tr>
                </tbody>
              </v-simple-table>
            </div>
            <div>
              <div class="subtitle-2 mb-2">Top Streaks</div>
              <v-simple-table class="no-hover border rounded-lg">
                <tbody>
                  <tr v-for="(u, idx) in leaderboard.streaks" :key="'s'+idx"><td>{{ u.name }}</td><td class="text-right">{{ u.streak_count }}</td></tr>
                </tbody>
              </v-simple-table>
            </div>
          </div>
          <v-btn small color="primary" class="mt-3" @click="loadLeaderboard">Refresh</v-btn>
        </v-card>
      </v-tab-item>
    </v-tabs-items>
  </div>
</template>

<script>
export default {
  data: () => ({
    tab: 0,
    stats: { coins: 0, streak: 0, bestStreak: 0, purchases: [] },
    shop: {},
    leaderboard: { coins: [], streaks: [] },
    loading: false,
  }),
  mounted() { this.loadAll(); },
  methods: {
    loadAll() { this.loadStats(); this.loadShop(); this.loadLeaderboard(); },
    loadStats() {
      axios.get('/social/stats').then(r => { this.stats = r.data; });
    },
    loadShop() {
      axios.get('/social/shop').then(r => { this.shop = r.data; });
    },
    loadLeaderboard() {
      axios.get('/social/leaderboard').then(r => { this.leaderboard = r.data; });
    },
    owned(key) { return (this.stats.purchases || []).includes(key); },
    purchase(key, price) {
      this.loading = true;
      axios.post('/social/purchase', { itemKey: key, price: price })
        .then(() => { this.loadStats(); })
        .finally(() => { this.loading = false; });
    }
  }
}
</script>

<style scoped>
.border { border: 1px solid var(--v-border-base); }
</style>



