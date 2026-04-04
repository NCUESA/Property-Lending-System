<template>
  <ul class="nav nav-pills flex-column mb-auto">
    <li class="nav-item" v-for="item in menuItems" :key="item.path">
      <a
        :href="item.path"
        :class="['nav-link', currentPath === item.path ? 'active' : 'text-dark']"
      >
        <i :class="['bi', item.icon, 'me-2']"></i> {{ item.name }}
      </a>
    </li>
  </ul>
</template>

<script setup>
// Using props to get data from Laravel Blade
const props = defineProps({
  currentPath: String,
  isAdmin: Boolean,
});

// Define your menu items here
const menuItems = [
  { name: "首頁", path: "/", icon: "bi-house" },
  { name: "借用表單", path: "/form", icon: "bi-file-text" },
  { name: "借用狀態", path: "/status", icon: "bi-bar-chart-steps" },
];

const adminMenuItems = [
  { name: "借用總表", path: "/status_table", icon: "bi-tools" },
  { name: "人員控管", path: "/responsible", icon: "bi-people" },
  { name: "IP通過設定", path: "/ip", icon: "bi-router" },
];

if (props.isAdmin) {
  menuItems.push(...adminMenuItems);
}
</script>
