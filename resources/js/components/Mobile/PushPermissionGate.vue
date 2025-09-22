<template>
  <v-snackbar v-model="open" color="primary" timeout="-1" bottom multi-line rounded class="elevation-6">
    <div class="d-flex align-center">
      <v-icon class="mr-2">mdi-bell</v-icon>
      Enable notifications to get daily reminders.
      <v-spacer></v-spacer>
      <v-btn small rounded color="white" class="black--text mr-2" @click="enable">Enable</v-btn>
      <v-btn small rounded text @click="dismiss">Not now</v-btn>
    </div>
  </v-snackbar>
</template>

<script>
export default {
  data: () => ({ open: false, vapid: null }),
  mounted() {
    // show only on Android-ish browsers and if not granted/denied
    const ua = navigator.userAgent.toLowerCase();
    const maybeAndroid = ua.includes('android') || ua.includes('linux;') || ua.includes('mobile');
    if (maybeAndroid && 'Notification' in window && Notification.permission === 'default') {
      this.open = true;
    }
  },
  methods: {
    dismiss(){ this.open=false; },
    async enable() {
      try {
        if (!('serviceWorker' in navigator)) return this.dismiss();
        const reg = await navigator.serviceWorker.ready;
        // fetch VAPID
        const cfg = await fetch('/push/config').then(r=>r.json());
        const pub = cfg.publicKey;
        const sub = await reg.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: this.urlBase64ToUint8Array(pub)
        });
        await fetch('/push/subscribe', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')}, body: JSON.stringify(sub)});
        this.open = false;
      } catch(e) { this.open=false; }
    },
    urlBase64ToUint8Array(base64String) {
      const padding = '='.repeat((4 - base64String.length % 4) % 4);
      const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
      const rawData = window.atob(base64); const outputArray = new Uint8Array(rawData.length);
      for (let i = 0; i < rawData.length; ++i) { outputArray[i] = rawData.charCodeAt(i); }
      return outputArray;
    }
  }
}
</script>

