<template>
  <v-container>
    <v-card outlined class="rounded-lg pa-4">
      <v-card-title>
        <v-icon class="mr-2">mdi-playlist-music</v-icon>
        Playlists
        <v-spacer></v-spacer>
        <v-btn color="primary" rounded depressed @click="createDialog=true">
          <v-icon class="mr-2">mdi-plus</v-icon>
          New playlist
        </v-btn>
      </v-card-title>

      <v-card-text>
        <v-alert v-if="error" type="error" border="left" class="rounded-lg mb-4">{{error}}</v-alert>
        <v-list two-line>
          <v-list-item v-for="pl in playlists" :key="pl.id" :to="'/playlists/'+pl.id" @click.prevent="open(pl)">
            <v-list-item-icon><v-icon>mdi-playlist-music</v-icon></v-list-item-icon>
            <v-list-item-content>
              <v-list-item-title>{{pl.name}}</v-list-item-title>
              <v-list-item-subtitle>{{pl.is_public? 'Public' : 'Private'}}</v-list-item-subtitle>
            </v-list-item-content>
            <v-list-item-action>
              <v-btn icon @click.stop="remove(pl)"><v-icon>mdi-delete</v-icon></v-btn>
            </v-list-item-action>
          </v-list-item>
          <v-list-item v-if="!playlists.length">
            <v-list-item-content>
              <v-list-item-title>No playlists yet</v-list-item-title>
            </v-list-item-content>
          </v-list-item>
        </v-list>
      </v-card-text>
    </v-card>

    <v-dialog v-model="createDialog" max-width="500px">
      <v-card class="rounded-lg">
        <v-card-title>
          <v-icon class="mr-2">mdi-playlist-plus</v-icon>
          Create playlist
          <v-spacer></v-spacer>
          <v-btn icon @click="createDialog=false"><v-icon>mdi-close</v-icon></v-btn>
        </v-card-title>
        <v-card-text>
          <label class="font-weight-bold">Name</label>
          <v-text-field v-model="newName" filled dense rounded placeholder="My playlist"/>
          <v-switch v-model="newPublic" label="Public"></v-switch>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn rounded text @click="createDialog=false">Cancel</v-btn>
          <v-btn color="primary" rounded depressed :loading="saving" @click="create">Create</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script>
export default {
  data: () => ({
    playlists: [],
    error: '',
    createDialog: false,
    newName: '',
    newPublic: false,
    saving: false,
  }),
  mounted() { this.load(); },
  methods: {
    load() {
      axios.get('/playlists').then(r => { this.playlists = r.data || []; }).catch(()=>{});
    },
    create() {
      if (!this.newName) return;
      this.saving = true;
      axios.post('/playlists/create', { name: this.newName, isPublic: this.newPublic }).then(() => {
        this.saving = false; this.createDialog=false; this.newName=''; this.newPublic=false; this.load();
      }).catch(e => { this.saving=false; this.error = (e && e.response && e.response.data) || 'Error'; });
    },
    remove(pl) {
      axios.post('/playlists/delete/'+pl.id).then(()=> this.load());
    },
    open(pl) {
      // placeholder for future detailed view
    }
  }
}
</script>

