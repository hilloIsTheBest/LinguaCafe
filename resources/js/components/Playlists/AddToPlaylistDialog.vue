<template>
  <v-dialog v-model="value" max-width="520px">
    <v-card class="rounded-lg">
      <v-card-title>
        <v-icon class="mr-2">mdi-playlist-plus</v-icon>
        Add to playlist
        <v-spacer></v-spacer>
        <v-btn icon @click="close"><v-icon>mdi-close</v-icon></v-btn>
      </v-card-title>
      <v-card-text>
        <v-alert v-if="error" type="error" border="left" class="rounded-lg mb-4">{{error}}</v-alert>
        <label class="font-weight-bold">Select playlist</label>
        <v-select v-model="selectedId" :items="playlistItems" item-text="name" item-value="id" filled dense rounded placeholder="Choose a playlist"></v-select>
        <div class="text-caption mb-2">or create a new playlist</div>
        <v-text-field v-model="newName" filled dense rounded placeholder="New playlist name"/>
        <v-switch v-model="newPublic" label="Public"/>
      </v-card-text>
      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn rounded text @click="close">Cancel</v-btn>
        <v-btn color="primary" rounded depressed :loading="saving" @click="save">Add</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
export default {
  props: {
    value: Boolean,
    itemType: String, // 'book' | 'chapter'
    itemId: Number,
  },
  data: () => ({
    playlists: [],
    selectedId: null,
    newName: '',
    newPublic: false,
    saving: false,
    error: '',
  }),
  computed: {
    playlistItems() { return this.playlists || []; }
  },
  watch: {
    value(v) { if (v) this.load(); }
  },
  methods: {
    load() {
      axios.get('/playlists').then(r => { this.playlists = r.data || []; if (this.playlists.length) this.selectedId = this.playlists[0].id; }).catch(()=>{});
    },
    ensurePlaylist() {
      if (this.selectedId) return Promise.resolve(this.selectedId);
      if (!this.newName) return Promise.reject('Please select or create a playlist.');
      return axios.post('/playlists/create', { name: this.newName, isPublic: this.newPublic }).then(r => r.data.id);
    },
    save() {
      this.saving = true; this.error='';
      this.ensurePlaylist().then((pid) => {
        return axios.post('/playlists/items/add', { playlistId: pid, itemType: this.itemType, itemId: this.itemId });
      }).then(() => {
        this.saving = false; this.close();
      }).catch(e => { this.saving=false; this.error=(e&&e.response&&e.response.data)|| (e.message||'Error'); });
    },
    close() { this.$emit('input', false); }
  }
}
</script>

