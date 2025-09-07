<template>
  <div>
    <div class="d-flex subheader mt-4 mb-4 px-2">LDAP</div>
    <v-card outlined class="rounded-lg pa-4">
      <v-switch v-model="enabled" label="Enable LDAP login" class="mb-4"></v-switch>

      <label class="font-weight-bold">LDAP Host (e.g. ldap://host or ldaps://host)</label>
      <v-text-field v-model="host" filled dense rounded placeholder="ldaps://ldap.example.com"/>

      <label class="font-weight-bold">LDAP Port (optional)</label>
      <v-text-field v-model.number="port" type="number" min="0" filled dense rounded placeholder="636"/>

      <v-switch v-model="useStartTls" label="Use StartTLS" class="mb-4"></v-switch>

      <label class="font-weight-bold">Base DN</label>
      <v-text-field v-model="baseDn" filled dense rounded placeholder="dc=example,dc=com"/>

      <label class="font-weight-bold">Bind DN (service account, optional)</label>
      <v-text-field v-model="bindDn" filled dense rounded placeholder="cn=reader,ou=apps,dc=example,dc=com"/>

      <label class="font-weight-bold">Bind Password (optional)</label>
      <v-text-field v-model="bindPassword" type="password" filled dense rounded placeholder="********"/>

      <label class="font-weight-bold">User filter (use %s for the username)</label>
      <v-text-field v-model="userFilter" filled dense rounded placeholder="(uid=%s)"/>

      <label class="font-weight-bold">Email attribute</label>
      <v-text-field v-model="emailAttr" filled dense rounded placeholder="mail"/>

      <label class="font-weight-bold">Name attribute</label>
      <v-text-field v-model="nameAttr" filled dense rounded placeholder="cn"/>

      <v-switch v-model="autoRegister" label="Auto-register users on first login" class="mb-2"></v-switch>

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
    host: '',
    port: null,
    useStartTls: false,
    baseDn: '',
    bindDn: '',
    bindPassword: '',
    userFilter: '(uid=%s)',
    emailAttr: 'mail',
    nameAttr: 'cn',
    autoRegister: true,
    saving: false,
  }),
  mounted() { this.load(); },
  methods: {
    load() {
      axios.post('/settings/global/get', { settingNames: [
        'ldapEnabled','ldapHost','ldapPort','ldapUseStartTls','ldapBaseDn','ldapBindDn','ldapBindPassword',
        'ldapUserFilter','ldapEmailAttribute','ldapNameAttribute','ldapAutoRegister'
      ]}).then(r => {
        const d = r.data || {};
        this.enabled = !!d.ldapEnabled;
        this.host = d.ldapHost || '';
        this.port = d.ldapPort || null;
        this.useStartTls = !!d.ldapUseStartTls;
        this.baseDn = d.ldapBaseDn || '';
        this.bindDn = d.ldapBindDn || '';
        this.bindPassword = d.ldapBindPassword || '';
        this.userFilter = d.ldapUserFilter || '(uid=%s)';
        this.emailAttr = d.ldapEmailAttribute || 'mail';
        this.nameAttr = d.ldapNameAttribute || 'cn';
        this.autoRegister = d.ldapAutoRegister === undefined ? true : !!d.ldapAutoRegister;
      });
    },
    save() {
      this.saving = true;
      axios.post('/settings/global/update', { settings: {
        ldapEnabled: this.enabled,
        ldapHost: this.host,
        ldapPort: this.port,
        ldapUseStartTls: this.useStartTls,
        ldapBaseDn: this.baseDn,
        ldapBindDn: this.bindDn,
        ldapBindPassword: this.bindPassword,
        ldapUserFilter: this.userFilter,
        ldapEmailAttribute: this.emailAttr,
        ldapNameAttribute: this.nameAttr,
        ldapAutoRegister: this.autoRegister,
      }}).then(() => { this.saving = false; }).catch(() => { this.saving = false; });
    }
  }
}
</script>

