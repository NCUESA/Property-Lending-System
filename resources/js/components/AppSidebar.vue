<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    AlarmClock,
    Boxes,
    Building2,
    ChartColumn,
    CalendarDays,
    CalendarRange,
    ClipboardList,
    FileText,
    Globe,
    Flame,
    MapPin,
    Package,
    Replace,
    Search,
    Settings2,
    ShieldCheck,
    ShieldAlert,
    Tags,
    Users,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import type { NavGroup } from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { home } from '@/routes';
import { index as borrowsIndex } from '@/routes/admin/borrows';
import { index as categoriesIndex } from '@/routes/admin/categories';
import { index as groupsIndex } from '@/routes/admin/groups';
import { index as locationsIndex } from '@/routes/admin/locations';
import { index as periodsIndex } from '@/routes/admin/periods';
import { index as poolsIndex } from '@/routes/admin/pools';
import { index as propertiesIndex } from '@/routes/admin/properties';
import { edit as settingsSection } from '@/routes/admin/settings/section';
import {
    departments as statsDepartments,
    heatmap as statsHeatmap,
    overdue as statsOverdue,
    popular as statsPopular,
} from '@/routes/admin/stats';
import { index as aliasesIndex } from '@/routes/admin/stats/aliases';
import { index as usersIndex } from '@/routes/admin/users';
import { index as violationsIndex } from '@/routes/admin/violations';
import { create as borrowCreate } from '@/routes/borrow';
import { index as myBorrowsIndex } from '@/routes/my/borrows';
import { status as propertyStatus } from '@/routes/properties';
import type { NavItem } from '@/types';

type SettingsSection = { slug: string; title: string; description: string };

const can = computed(() => usePage().props.auth.can);

const borrowNav = computed<NavItem[]>(() =>
    can.value.staff
        ? [
              { title: '借用單', href: borrowsIndex(), icon: ClipboardList },
              {
                  title: '違規紀錄',
                  href: violationsIndex(),
                  icon: ShieldAlert,
              },
          ]
        : [],
);

const propertyNav = computed<NavItem[]>(() =>
    can.value.admin
        ? [
              { title: '器材', href: propertiesIndex(), icon: Package },
              { title: '大量借用品項', href: poolsIndex(), icon: Boxes },
              { title: '編碼規則', href: categoriesIndex(), icon: Tags },
          ]
        : [],
);

const adminNav = computed<NavItem[]>(() =>
    can.value.admin
        ? [
              { title: '人員', href: usersIndex(), icon: Users },
              { title: '群組與授權', href: groupsIndex(), icon: UsersRound },
              { title: '借用期間', href: periodsIndex(), icon: CalendarRange },
              { title: '地點', href: locationsIndex(), icon: MapPin },
          ]
        : [],
);

const dataNav = computed<NavItem[]>(() =>
    can.value.admin
        ? [
              { title: '熱門清單', href: statsPopular(), icon: Flame },
              { title: '常借單位', href: statsDepartments(), icon: Building2 },
              { title: '逾期排行', href: statsOverdue(), icon: AlarmClock },
              {
                  title: '借用量熱力圖',
                  href: statsHeatmap(),
                  icon: CalendarDays,
              },
              { title: '單位同義詞設定', href: aliasesIndex(), icon: Replace },
          ]
        : [],
);

/**
 * 系統設定的各個分頁。清單來自後端的 SettingController::SECTIONS（共享 prop），
 * 所以在那裡新增一頁設定，這裡就會自己多一個連結。
 */
const systemNav = computed<NavItem[]>(() =>
    (usePage().props.settingsSections as SettingsSection[]).map((s) => ({
        title: s.title,
        href: settingsSection(s.slug),
        icon: Settings2,
    })),
);

const frontendNav = computed<NavItem[]>(() => [
    { title: '借用申請', href: borrowCreate(), icon: FileText },
    // 訪客的申請沒有帳號可以歸屬，所以登入後才看得到
    ...(usePage().props.auth.user
        ? [
              {
                  title: '我的借用',
                  href: myBorrowsIndex(),
                  icon: ClipboardList,
              },
          ]
        : []),
    { title: '器材狀態', href: propertyStatus(), icon: Search },
]);

const navGroups = computed<NavGroup[]>(() => [
    { label: '借用', icon: ClipboardList, items: borrowNav.value },
    { label: '器材', icon: Package, items: propertyNav.value },
    { label: '管理', icon: ShieldCheck, items: adminNav.value },
    { label: '數據', icon: ChartColumn, items: dataNav.value },
    { label: '系統', icon: Settings2, items: systemNav.value },
    { label: '前台', icon: Globe, items: frontendNav.value },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="home()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="navGroups" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
