import type { ComponentType, SVGProps } from 'react';
import type { SidebarGroup } from '@/components/app/app-sidebar';
import {
    BellIcon,
    CheckCircleIcon,
    FileIcon,
    GridIcon,
    GroupIcon,
    ListIcon,
    SearchIcon,
    ShieldIcon,
    UserCircleIcon,
} from '@/components/icons';
import { PORTAL_NAV_GROUPS, type PortalNavIcon } from '@/navigation/portal-nav';

export const PORTAL_NAV_ICONS: Record<PortalNavIcon, ComponentType<SVGProps<SVGSVGElement>>> = {
    grid: GridIcon,
    bell: BellIcon,
    search: SearchIcon,
    file: FileIcon,
    'check-circle': CheckCircleIcon,
    list: ListIcon,
    group: GroupIcon,
    user: UserCircleIcon,
    shield: ShieldIcon,
};

/**
 * Grupos da sidebar do portal montados a partir do catálogo único.
 * Com os serviços desligados, o grupo some: o aviso fica no layout.
 */
export function portalSidebarGroups(servicosHabilitados = true): SidebarGroup[] {
    const groups = servicosHabilitados
        ? PORTAL_NAV_GROUPS
        : PORTAL_NAV_GROUPS.filter((group) => group.id !== 'servicos');

    return groups.map((group) => ({
        label: group.label,
        items: group.items.map((item) => {
            const Icon = PORTAL_NAV_ICONS[item.icon];

            return { name: item.name, href: item.href, icon: <Icon /> };
        }),
    }));
}
