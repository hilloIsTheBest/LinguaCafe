<template>
  <div>
    <div class="d-flex subheader mt-4 mb-4 px-2">Branding</div>
    <v-card outlined class="rounded-lg pa-4">
      <label class="font-weight-bold">Site title</label>
      <v-text-field v-model="siteTitle" filled dense rounded placeholder="Site title"></v-text-field>
      <label class="font-weight-bold">Site icon URL</label>
      <v-text-field v-model="siteIconUrl" filled dense rounded placeholder="https://.../logo.png"></v-text-field>

      <v-divider class="my-6"></v-divider>
      <div class="font-weight-bold mb-2">Mobile PWA UI</div>
      <label class="font-weight-bold">Mobile UI style</label>
      <v-select :items="mobileUiStyleItems" v-model="mobileUiStyle" filled dense rounded></v-select>
      <v-switch v-model="mobileOnboardingEnabled" label="Enable onboarding flow on mobile" class="mb-2"></v-switch>

      <v-btn color="primary" rounded depressed :loading="saving" @click="save">Save</v-btn>
      <v-btn text rounded class="ml-2" @click="load">Reset</v-btn>
      <div class="mt-4">
        <label class="font-weight-bold">Upload icon (png/jpg)</label>
        <input type="file" ref="icon" accept="image/*"/>
        <v-btn small class="ml-2" @click="upload">Upload</v-btn>
      </div>
    </v-card>
  </div>
  
</template>

<script>
export default {
  data: () => ({ 
    siteTitle: '', 
    siteIconUrl: '', 
    mobileUiStyle: 'default',
    mobileUiStyleItems: [
      { text: 'Default', value: 'default' },
      { text: 'LingQ-like', value: 'lingq' },
    ],
    mobileOnboardingEnabled: false,
    saving: false 
  }),
  mounted() { this.load(); },
  methods: {
    load() {
      axios.post('/settings/global/get', { settingNames: ['siteTitle','siteIconUrl','mobileUiStyle','mobileOnboardingEnabled']})
        .then(r => { 
          this.siteTitle = r.data.siteTitle || ''; 
          this.siteIconUrl = r.data.siteIconUrl || ''; 
          this.mobileUiStyle = r.data.mobileUiStyle || 'default';
          this.mobileOnboardingEnabled = !!r.data.mobileOnboardingEnabled;
        });
    },
    save() {
      this.saving = true;
      axios.post('/settings/global/update', { settings: { 
        siteTitle: this.siteTitle, 
        siteIconUrl: this.siteIconUrl,
        mobileUiStyle: this.mobileUiStyle,
        mobileOnboardingEnabled: this.mobileOnboardingEnabled,
      }})
        .then(() => { this.saving = false; })
        .catch(() => { this.saving = false; });
    },
    upload() {
      const f = this.$refs.icon && this.$refs.icon.files && this.$refs.icon.files[0];
      if (!f) return;
      const fd = new FormData();
      fd.append('icon', f);
      axios.post('/settings/branding/upload-icon', fd, { headers: { 'Content-Type': 'multipart/form-data' }})
        .then(r => { if (r.data && r.data.url) this.siteIconUrl = r.data.url; });
    }
  }
}
</script>
