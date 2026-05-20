import { reactive } from 'vue';

export const session = reactive({
  user: null,
  csrfToken: null,
  unreadMessages: 0,
  unreadNotifications: 0
});
