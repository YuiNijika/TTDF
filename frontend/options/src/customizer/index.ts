import '@arco-design/web-vue/dist/arco.css';
import '../styles/global.css';
import { createApp } from 'vue';
import ArcoVue from '@arco-design/web-vue';
import Customizer from './Customizer.vue';

const mount = document.getElementById('ttdf-custom-theme');

if (mount) {
    document.body.classList.add('ttdf-customizer-body');
    createApp(Customizer).use(ArcoVue).mount(mount);
}
