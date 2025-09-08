<template>
  <v-container>
    <v-card outlined class="rounded-lg pa-4">
      <v-card-title>
        <v-icon class="mr-2">mdi-earth</v-icon>
        Public Library
        <v-spacer></v-spacer>
        <v-text-field v-model="q" append-icon="mdi-magnify" dense rounded filled placeholder="Filter by name or tag" hide-details class="mr-4" style="max-width:300px"/>
        <v-select v-model="lang" :items="languages" dense rounded filled hide-details style="max-width:160px" placeholder="Language"></v-select>
      </v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" md="4" v-for="b in filtered" :key="b.id">
            <v-card outlined class="rounded-lg">
              <v-img v-if="b.cover_image" :src="'/images/book_images/' + b.cover_image" height="180px"/>
              <v-card-title class="py-2">{{ b.name }}</v-card-title>
              <v-card-subtitle class="py-0 px-4">{{ b.language }} • by {{ b.author || 'unknown' }}</v-card-subtitle>
              <v-card-text>
                <v-chip v-for="t in b.tags" :key="t" small class="mr-1 mb-1">{{t}}</v-chip>
              </v-card-text>
              <v-card-actions>
                <v-spacer></v-spacer>
                <v-btn text rounded :to="'/books/'+b.id">
                  <v-icon class="mr-1">mdi-book-open-variant</v-icon>
                  View
                </v-btn>
              </v-card-actions>
            </v-card>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<script>
export default {
  data: () => ({
    books: [],
    q: '',
    lang: null,
  }),
  computed: {
    languages() {
      const s = new Set(this.books.map(b=>b.language));
      return ['All', ...[...s]];
    },
    filtered() {
      const q = (this.q||'').toLowerCase();
      const lang = this.lang && this.lang!=='All' ? this.lang : null;
      return this.books.filter(b => {
        if (lang && b.language!==lang) return false;
        if (!q) return true;
        const hay = (b.name||'')+' '+(b.tags||[]).join(' ');
        return hay.toLowerCase().includes(q);
      });
    }
  },
  mounted() { axios.get('/public/books').then(r => { this.books = r.data||[]; }); }
}
</script>

