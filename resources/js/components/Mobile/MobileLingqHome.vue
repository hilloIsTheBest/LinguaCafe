<template>
  <v-container>
    <v-card outlined class="rounded-xl pa-4 mb-4" color="foreground">
      <div class="d-flex align-center">
        <v-img :src="'/images/flags/' + (language||'english').toLowerCase() + '.png'" max-width="28" class="mr-3 border"></v-img>
        <div class="text-h6 font-weight-bold">{{ title }}</div>
        <v-spacer></v-spacer>
        <v-btn small icon @click="$router.push('/user-settings')"><v-icon>mdi-cog</v-icon></v-btn>
      </div>
    </v-card>

    <div class="section">
      <div class="section-title">Guided Courses</div>
      <v-slide-group show-arrows>
        <v-slide-item v-for="b in books" :key="b.id">
          <v-card outlined class="rounded-xl mr-3" width="260">
            <v-img :src="b.cover_image ? '/images/book_images/'+b.cover_image : '/icon512rounded.png'" height="140" class="rounded-t-xl"></v-img>
            <v-card-title class="py-2">{{ b.name }}</v-card-title>
            <v-card-subtitle class="py-0">{{ b.language }}</v-card-subtitle>
          </v-card>
        </v-slide-item>
      </v-slide-group>
    </div>

    <div class="section">
      <div class="section-title">Trending</div>
      <v-slide-group show-arrows>
        <v-slide-item v-for="b in trending" :key="'t'+b.id">
          <v-card outlined class="rounded-xl mr-3" width="260">
            <v-img :src="b.cover_image ? '/images/book_images/'+b.cover_image : '/icon512rounded.png'" height="140" class="rounded-t-xl"></v-img>
            <v-card-title class="py-2">{{ b.name }}</v-card-title>
            <v-card-subtitle class="py-0">{{ b.language }}</v-card-subtitle>
          </v-card>
        </v-slide-item>
      </v-slide-group>
    </div>
  </v-container>
</template>

<script>
export default {
  props: { language: String, title: {type:String, default:'Language'} },
  data: () => ({ books: [], trending: [] }),
  mounted() {
    axios.get('/books').then(r => { this.books = r.data || []; }).catch(()=>{});
    axios.get('/public/books').then(r => { this.trending = r.data || []; }).catch(()=>{});
  }
}
</script>

<style scoped>
.section { margin-bottom: 16px; }
.section-title { font-weight: 700; margin: 8px 0 8px 4px; }
</style>

