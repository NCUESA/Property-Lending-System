import './bootstrap';
import 'bootstrap';
import jQuery from 'jquery';
import { createApp } from 'vue';
import AppSidebar from './components/layouts/AppSidebar.vue';

window.$ = jQuery;
window.jQuery = jQuery;

console.log('Vite is working and app.js is loaded!');

const sidebarContainer = document.getElementById('sidebar-app');
console.log('Sidebar container found:', sidebarContainer);

if (sidebarContainer) {
    const app = createApp(AppSidebar, {
        currentPath: sidebarContainer.dataset.path,
        userLevel: sidebarContainer.dataset.userLevel
    });
    
    app.mount('#sidebar-app');
}