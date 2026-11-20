<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LayoutDashboard, LogIn, LogOut, Menu } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Toaster } from '@/components/ui/sonner';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { roleLabels } from '@/lib/format';
import { login, logout } from '@/routes';
import { index as adminBorrows } from '@/routes/admin/borrows';
import { create as borrowCreate } from '@/routes/borrow';
import { status as propertyStatus } from '@/routes/properties';

const page = usePage();
const user = computed(
    () => page.props.auth.user as typeof page.props.auth.user | null,
);
const can = computed(() => page.props.auth.can);
const { isCurrentUrl } = useCurrentUrl();
const mobileOpen = ref(false);

const nav = [
    { title: '借用申請', href: borrowCreate() },
    { title: '器材狀態', href: propertyStatus() },
];

const handleLogout = () => router.flushAll();
</script>

<template>
    <div class="min-h-svh bg-background">
        <header
            class="sticky top-0 z-30 border-b bg-background/90 backdrop-blur"
        >
            <div class="mx-auto flex h-14 max-w-6xl items-center gap-4 px-4">
                <Link
                    :href="borrowCreate()"
                    class="flex items-center gap-2 font-semibold"
                >
                    <AppLogoIcon class="size-6 fill-current" />
                    <span>{{ page.props.name }}</span>
                </Link>

                <nav class="hidden items-center gap-1 sm:flex">
                    <Link
                        v-for="item in nav"
                        :key="item.title"
                        :href="item.href"
                        class="rounded-md px-3 py-1.5 text-sm transition-colors hover:bg-accent"
                        :class="
                            isCurrentUrl(item.href)
                                ? 'bg-accent font-medium'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ item.title }}
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-2">
                    <DropdownMenu v-if="user">
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="sm">{{
                                user.name
                            }}</Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-48">
                            <DropdownMenuLabel class="font-normal">
                                <div class="text-sm font-medium">
                                    {{ user.name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ roleLabels[user.role] }}
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem v-if="can.staff" as-child>
                                <Link
                                    :href="adminBorrows()"
                                    class="w-full cursor-pointer"
                                >
                                    <LayoutDashboard class="mr-2 size-4" />後台
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link
                                    :href="logout()"
                                    as="button"
                                    class="w-full cursor-pointer"
                                    @click="handleLogout"
                                >
                                    <LogOut class="mr-2 size-4" />登出
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Button v-else as-child size="sm" variant="outline">
                        <Link :href="login()"
                            ><LogIn class="mr-1 size-4" />登入</Link
                        >
                    </Button>

                    <Button
                        variant="ghost"
                        size="icon"
                        class="sm:hidden"
                        aria-label="選單"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <Menu class="size-5" />
                    </Button>
                </div>
            </div>

            <nav v-if="mobileOpen" class="border-t px-4 py-2 sm:hidden">
                <Link
                    v-for="item in nav"
                    :key="item.title"
                    :href="item.href"
                    class="block rounded-md px-3 py-2 text-sm hover:bg-accent"
                    @click="mobileOpen = false"
                >
                    {{ item.title }}
                </Link>
            </nav>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8">
            <slot />
        </main>

        <Toaster rich-colors />
    </div>
</template>
