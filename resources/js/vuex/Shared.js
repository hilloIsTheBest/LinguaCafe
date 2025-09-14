import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export default {
    namespaced: true,
    state: () => ({
        userUuid: '',
        userName: false,
        userEmail: false,
        userAdmin: false,
        mobileUiStyle: 'default', // 'default' | 'lingq'
        mobileOnboardingEnabled: false,
        oidcEnabled: false,
        oidcButtonText: 'Login with SSO',
        oidcButtonIcon: 'mdi-shield-account',
        oidcAutoLaunch: false,
        vuetifyThemeSettings: null,
        textStylingSettings: null,
        echo: new Echo({
            broadcaster: 'pusher',
            key: 'wjp2pou6ebgibtwccqsj',
            cluster: 'mt1',
            forceTLS: false,
            wsHost: window.location.hostname,
            wsPort: 6001,
            enabledTransports: ['ws', 'wss'],
        })
    }),
    mutations: {
        setUuid (state, userUuid) {
            state.userUuid = userUuid;
        },
        setUserName (state, userName) {
            state.userName = userName;
        },
        setUserEmail (state, userEmail) {
            state.userEmail = userEmail;
        },
        setUserAdmin (state, userAdmin) {
            state.userAdmin = userAdmin;
        },
        setMobileUiStyle (state, style) {
            state.mobileUiStyle = style || 'default';
        },
        setMobileOnboardingEnabled (state, enabled) {
            state.mobileOnboardingEnabled = !!enabled;
        },
        setOidcEnabled (state, enabled) {
            state.oidcEnabled = !!enabled;
        },
        setOidcButtonText (state, text) {
            state.oidcButtonText = text || 'Login with SSO';
        },
        setOidcButtonIcon (state, icon) {
            state.oidcButtonIcon = icon || 'mdi-shield-account';
        },
        setOidcAutoLaunch (state, enabled) {
            state.oidcAutoLaunch = !!enabled;
        },
        setVuetifyThemeSettings (state, vuetifyThemeSettings) {
            state.vuetifyThemeSettings = vuetifyThemeSettings;
        },
        setTextStylingSettings (state, textStylingSettings) {
            state.textStylingSettings = textStylingSettings;
        }
    },
    getters: {
        echo (state) {
            return state.echo;
        },
        userUuid(state) {
            return state.userUuid;
        },
        userAdmin(state) {
            return state.userAdmin;
        },
        mobileUiStyle(state) {
            return state.mobileUiStyle;
        },
        mobileOnboardingEnabled(state) {
            return state.mobileOnboardingEnabled;
        },
        oidcEnabled(state) {
            return state.oidcEnabled;
        },
        oidcButtonText(state) {
            return state.oidcButtonText;
        },
        oidcButtonIcon(state) {
            return state.oidcButtonIcon;
        },
        oidcAutoLaunch(state) {
            return state.oidcAutoLaunch;
        }
    }
}
