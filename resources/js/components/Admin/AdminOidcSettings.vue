<template>
  <div>
    <div class="d-flex subheader mt-4 mb-4 px-2">OIDC (OpenID Connect)</div>
    <v-card outlined class="rounded-lg pa-4">
      <v-switch v-model="enabled" label="Enable OIDC login" class="mb-4"></v-switch>

      <label class="font-weight-bold">Issuer URL</label>
      <v-text-field v-model="issuer" filled dense rounded placeholder="https://accounts.example.com"/>

      <label class="font-weight-bold">Client ID</label>
      <v-text-field v-model="clientId" filled dense rounded placeholder="client id"/>

      <label class="font-weight-bold">Client Secret</label>
      <v-text-field v-model="clientSecret" type="password" filled dense rounded placeholder="client secret"/>

      <label class="font-weight-bold">Redirect URI</label>
      <v-text-field v-model="redirectUri" filled dense rounded placeholder="https://your.host/oidc/callback"/>

      <div class="mt-4">
        <v-btn color="primary" rounded depressed :loading="saving" @click="save">Save</v-btn>
        <v-btn text rounded class="ml-2" @click="load">Reset</v-btn>
      </div>
    </v-card>
  </div>
</template>

<script>
export default {
  data: () => ({
    enabled: false,
    issuer: '',
    clientId: '',
    clientSecret: '',
    redirectUri: '',
    saving: false,
  }),
  mounted() { this.load(); },
  methods: {
    load() {
      axios.post('/settings/global/get', { settingNames: [
        'oidcEnabled','oidcIssuer','oidcClientId','oidcClientSecret','oidcRedirectUri'
      ]}).then(r => {
        const d = r.data || {};
        this.enabled = !!d.oidcEnabled;
        this.issuer = d.oidcIssuer || '';
        this.clientId = d.oidcClientId || '';
        this.clientSecret = d.oidcClientSecret || '';
        this.redirectUri = d.oidcRedirectUri || '';
      });
    },
    save() {
      this.saving = true;
      axios.post('/settings/global/update', { settings: {
        oidcEnabled: this.enabled,
        oidcIssuer: this.issuer,
        oidcClientId: this.clientId,
        oidcClientSecret: this.clientSecret,
        oidcRedirectUri: this.redirectUri,
      }}).then(() => { this.saving = false; }).catch(() => { this.saving = false; });
    }
  }
}
</script>

