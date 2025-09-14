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

      <v-divider class="my-6"></v-divider>

      <div class="font-weight-bold mb-2">Optional Endpoint Overrides</div>
      <label class="font-weight-bold">Authorize URL</label>
      <v-text-field v-model="authorizeUrl" filled dense rounded placeholder="https://.../authorize"/>
      <label class="font-weight-bold">Userinfo URL</label>
      <v-text-field v-model="userinfoUrl" filled dense rounded placeholder="https://.../userinfo"/>
      <label class="font-weight-bold">JWKS URL</label>
      <v-text-field v-model="jwksUrl" filled dense rounded placeholder="https://.../.well-known/jwks.json"/>
      <label class="font-weight-bold">Logout URL</label>
      <v-text-field v-model="logoutUrl" filled dense rounded placeholder="https://.../logout"/>

      <v-divider class="my-6"></v-divider>

      <div class="font-weight-bold mb-2">Client Options</div>
      <label class="font-weight-bold">Scopes (space-separated)</label>
      <v-text-field v-model="scopes" filled dense rounded placeholder="openid profile email"/>
      <label class="font-weight-bold">Signing algorithm (optional)</label>
      <v-text-field v-model="signingAlg" filled dense rounded placeholder="RS256"/>
      <v-switch v-model="verifyTls" label="Verify TLS certificates (recommended)" class="mb-2"></v-switch>
      <label class="font-weight-bold">Clock skew leeway (seconds)</label>
      <v-text-field v-model.number="leeway" type="number" min="0" filled dense rounded placeholder="60"/>
      <label class="font-weight-bold">Allowed Mobile Redirect URIs (one per line)</label>
      <v-textarea v-model="mobileRedirectUrisRaw" filled dense rounded rows="3" placeholder="myapp://callback"></v-textarea>

      <v-divider class="my-6"></v-divider>

      <div class="font-weight-bold mb-2">Login Button</div>
      <label class="font-weight-bold">Button text</label>
      <v-text-field v-model="buttonText" filled dense rounded placeholder="Login with SSO"/>
      <label class="font-weight-bold">Button icon (Material Design Icon name)</label>
      <v-text-field v-model="buttonIcon" filled dense rounded placeholder="mdi-shield-account"/>

      <v-divider class="my-6"></v-divider>

      <div class="font-weight-bold mb-2">Behavior</div>
      <v-switch v-model="autoLaunch" label="Auto-launch OIDC on login page" class="mb-2"></v-switch>
      <v-switch v-model="autoRegister" label="Auto-register users on first login" class="mb-2"></v-switch>

      <v-divider class="my-6"></v-divider>

      <div class="font-weight-bold mb-2">Claims Mapping</div>
      <label class="font-weight-bold">Group claim name</label>
      <v-text-field v-model="groupClaim" filled dense rounded placeholder="groups"/>
      <label class="font-weight-bold">Permission claim name</label>
      <v-text-field v-model="permissionClaim" filled dense rounded placeholder="permissions"/>
      <label class="font-weight-bold">Admin group value (optional)</label>
      <v-text-field v-model="adminGroup" filled dense rounded placeholder="admin"/>
      <label class="font-weight-bold">Admin permission value (optional)</label>
      <v-text-field v-model="adminPermission" filled dense rounded placeholder="admin"/>

      <div class="mt-4">
        <v-btn color="primary" rounded depressed :loading="saving" @click="save">Save</v-btn>
        <v-btn text rounded class="ml-2" @click="load">Reset</v-btn>
        <v-spacer></v-spacer>
        <v-btn color="secondary" rounded depressed class="ml-4" @click="testOidc">
          <v-icon class="mr-2">mdi-open-in-new</v-icon>
          Test OIDC Login
        </v-btn>
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
    authorizeUrl: '',
    userinfoUrl: '',
    jwksUrl: '',
    logoutUrl: '',
    scopes: 'openid profile email',
    signingAlg: '',
    verifyTls: true,
    leeway: 60,
    mobileRedirectUrisRaw: '',
    buttonText: 'Login with SSO',
    buttonIcon: 'mdi-shield-account',
    autoLaunch: false,
    autoRegister: true,
    groupClaim: '',
    permissionClaim: '',
    adminGroup: '',
    adminPermission: '',
    saving: false,
  }),
  mounted() { this.load(); },
  methods: {
    load() {
      axios.post('/settings/global/get', { settingNames: [
        'oidcEnabled','oidcIssuer','oidcClientId','oidcClientSecret','oidcRedirectUri',
        'oidcAuthorizeUrl','oidcUserinfoUrl','oidcJwksUrl','oidcLogoutUrl',
        'oidcScopes','oidcSigningAlg','oidcVerifyTls','oidcLeeway','oidcMobileRedirectUris',
        'oidcButtonText','oidcButtonIcon','oidcAutoLaunch','oidcAutoRegister',
        'oidcGroupClaim','oidcPermissionClaim','oidcAdminGroup','oidcAdminPermission'
      ]}).then(r => {
        const d = r.data || {};
        this.enabled = !!d.oidcEnabled;
        this.issuer = d.oidcIssuer || '';
        this.clientId = d.oidcClientId || '';
        this.clientSecret = d.oidcClientSecret || '';
        this.redirectUri = d.oidcRedirectUri || '';
        this.authorizeUrl = d.oidcAuthorizeUrl || '';
        this.userinfoUrl = d.oidcUserinfoUrl || '';
        this.jwksUrl = d.oidcJwksUrl || '';
        this.logoutUrl = d.oidcLogoutUrl || '';
        this.scopes = d.oidcScopes || 'openid profile email';
        this.signingAlg = d.oidcSigningAlg || '';
        this.verifyTls = d.oidcVerifyTls === undefined ? true : !!d.oidcVerifyTls;
        this.leeway = d.oidcLeeway === undefined ? 60 : (d.oidcLeeway || 0);
        this.mobileRedirectUrisRaw = Array.isArray(d.oidcMobileRedirectUris) ? d.oidcMobileRedirectUris.join('\n') : (d.oidcMobileRedirectUris || '');
        this.buttonText = d.oidcButtonText || 'Login with SSO';
        this.buttonIcon = d.oidcButtonIcon || 'mdi-shield-account';
        this.autoLaunch = !!d.oidcAutoLaunch;
        this.autoRegister = d.oidcAutoRegister === undefined ? true : !!d.oidcAutoRegister;
        this.groupClaim = d.oidcGroupClaim || '';
        this.permissionClaim = d.oidcPermissionClaim || '';
        this.adminGroup = d.oidcAdminGroup || '';
        this.adminPermission = d.oidcAdminPermission || '';
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
        oidcAuthorizeUrl: this.authorizeUrl,
        oidcUserinfoUrl: this.userinfoUrl,
        oidcJwksUrl: this.jwksUrl,
        oidcLogoutUrl: this.logoutUrl,
        oidcScopes: this.scopes,
        oidcSigningAlg: this.signingAlg,
        oidcVerifyTls: this.verifyTls,
        oidcLeeway: this.leeway,
        oidcMobileRedirectUris: this.mobileRedirectUrisRaw
          ? this.mobileRedirectUrisRaw.split(/\r?\n/).map(s => s.trim()).filter(Boolean)
          : [],
        oidcButtonText: this.buttonText,
        oidcButtonIcon: this.buttonIcon,
        oidcAutoLaunch: this.autoLaunch,
        oidcAutoRegister: this.autoRegister,
        oidcGroupClaim: this.groupClaim,
        oidcPermissionClaim: this.permissionClaim,
        oidcAdminGroup: this.adminGroup,
        oidcAdminPermission: this.adminPermission,
      }}).then(() => { this.saving = false; }).catch(() => { this.saving = false; });
    },
    testOidc() {
      // Basic validation so we don't send user into a broken flow
      if (!this.enabled) {
        this.$root.$emit('showSnackbar', {color:'error', text:'Enable OIDC first, then save'});
        return;
      }
      if (!this.issuer || !this.clientId) {
        this.$root.$emit('showSnackbar', {color:'error', text:'Please set Issuer and Client ID, then save'});
        return;
      }
      window.location.href = '/auth/oidc';
    }
  }
}
</script>
