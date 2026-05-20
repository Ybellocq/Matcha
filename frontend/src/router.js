import { createRouter, createWebHistory } from 'vue-router';
import Home from './pages/Home.vue';
import Login from './pages/Login.vue';
import Register from './pages/Register.vue';
import Verify from './pages/Verify.vue';
import ResetRequest from './pages/ResetRequest.vue';
import ResetPassword from './pages/ResetPassword.vue';
import Profile from './pages/Profile.vue';
import UserProfile from './pages/UserProfile.vue';
import Search from './pages/Search.vue';
import Matches from './pages/Matches.vue';
import Chat from './pages/Chat.vue';
import Notifications from './pages/Notifications.vue';

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: Home },
    { path: '/login', component: Login },
    { path: '/register', component: Register },
    { path: '/verify', component: Verify },
    { path: '/reset-request', component: ResetRequest },
    { path: '/reset-password', component: ResetPassword },
    { path: '/profile', component: Profile },
    { path: '/users/:username', component: UserProfile },
    { path: '/search', component: Search },
    { path: '/matches', component: Matches },
    { path: '/chat/:username?', component: Chat },
    { path: '/notifications', component: Notifications }
  ]
});

export default router;
