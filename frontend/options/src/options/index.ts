import '@arco-design/web-vue/dist/arco.css';
import '../styles/global.css';
import { createApp } from 'vue';
import ArcoVue from '@arco-design/web-vue';
import SettingsApp from '../components/SettingsApp.vue';

const info = (window as unknown as { ttdfOptionsInfo?: Record<string, string> }).ttdfOptionsInfo ?? {};
const mount = document.getElementById('options-app');

if (mount) {
    mount.closest('.col-mb-12, .col-tb-8')?.classList.add('ttdf-options-column');
    mount.closest('form')?.classList.add('ttdf-options-form');

    createApp(SettingsApp, {
        apiUrl: String(info.apiUrl || ''),
        nonce: String(info.nonce || ''),
        initError: String(info.initError || ''),
        customizerUrl: String(info.customizerUrl || ''),
    }).use(ArcoVue).mount(mount);
}
