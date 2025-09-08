<template>
  <v-container>
    <v-card outlined class="rounded-lg pa-4">
      <v-card-title>
        <v-icon class="mr-2">mdi-playlist-music</v-icon>
        {{ playlist ? playlist.name : 'Playlist' }}
        <v-spacer></v-spacer>
        <v-btn v-if="playlist" rounded color="primary" depressed @click="playFirst"><v-icon class="mr-2">mdi-play</v-icon>Play</v-btn>
      </v-card-title>
      <v-card-text>
        <v-alert v-if="error" type="error" border="left" class="rounded-lg mb-4">{{ error }}</v-alert>
        <v-simple-table dense>
          <thead>
            <tr>
              <th>#</th>
              <th>Type</th>
              <th>ID</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="it in itemsSorted" :key="it.id">
              <td>{{ it.position }}</td>
              <td>{{ it.item_type }}</td>
              <td>{{ it.item_id }}</td>
              <td>
                <v-btn icon small @click="move(it, 'up')"><v-icon small>mdi-arrow-up</v-icon></v-btn>
                <v-btn icon small @click="move(it, 'down')"><v-icon small>mdi-arrow-down</v-icon></v-btn>
                <v-btn icon small @click="open(it)"><v-icon small>mdi-open-in-new</v-icon></v-btn>
                <v-btn icon small @click="remove(it)"><v-icon small>mdi-delete</v-icon></v-btn>
              </td>
            </tr>
            <tr v-if="!items.length"><td colspan="4">No items yet</td></tr>
          </tbody>
        </v-simple-table>
      </v-card-text>
    </v-card>
  </v-container>
</template>

<script>
export default {
  data: () => ({
    playlistId: null,
    playlist: null,
    items: [],
    error: '',
  }),
  computed: {
    itemsSorted() { return [...this.items].sort((a,b)=>a.position-b.position); }
  },
  mounted() {
    this.playlistId = parseInt(this.$route.params.playlistId);
    this.load();
  },
  methods: {
    load() {
      axios.get('/playlists').then(r => {
        const pls = r.data||[];
        this.playlist = pls.find(p=>p.id===this.playlistId) || null;
      });
      axios.get('/playlists/items/'+this.playlistId).then(r => { this.items = r.data || []; });
    },
    move(it, dir) {
      axios.post('/playlists/items/move', { playlistItemId: it.id, direction: dir }).then(()=> this.load());
    },
    open(it) {
      if (it.item_type === 'chapter') {
        this.$router.push('/chapters/read/'+it.item_id);
      } else {
        this.$router.push('/books/'+it.item_id);
      }
    },
    playFirst() {
      if (!this.items.length) return;
      const it = this.itemsSorted[0];
      this.open(it);
    },
    remove(it) {
      axios.post('/playlists/items/remove', { playlistItemId: it.id }).then(()=> this.load());
    }
  }
}
</script>

